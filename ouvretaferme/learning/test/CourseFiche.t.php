<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * La fiche publique d'une formation, après les retours bêta des formations FRAB (planche
 * fiche-publique tranchée le 01/10/2026) :
 * - les Enjeux ouvrent « La formation en détail », les Objectifs juste dessous, et ne se répètent pas
 *   dans le « Pour qui » du panneau d'inscription, qui les porte sous ses propres titres ;
 * - un module se nomme d'après le type de la formation, sur la fiche, à l'étape 1 de l'inscription
 *   et dans le menu de gauche : « Module N · » en ligne, son seul nom en visio et en présentiel
 *   (01/10/2026, « le terme module est réservé aux formations en ligne ») ; les leçons
 *   gardent leur numéro continu sur la formation ;
 * - le menu de gauche perd les modules sans leçon.
 *
 * Chaque écran est préparé par sa page (`executeView()`) puis rendu par l'UI.
 *
 * Lancement : dev/test package=learning file=CourseFiche
 */
class CourseFicheTest extends LearningDateFixtureTest {

	const DESCRIPTION = '<p>Le contrôle annuel se prépare toute l\'année.</p>';
	const OBJECTIVES = '<p>Réunir les pièces demandées au contrôle.</p>';

	/**
	 * Avec des Enjeux, la fiche les montre avec les Objectifs ; le panneau d'inscription n'en reprend
	 * aucun des deux dans son tableau.
	 */
	public function testEnjeux(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/course:doUpdateDetails', [
			'id' => (string)$eCourse['id'],
			'description' => self::DESCRIPTION,
			'objectives' => self::OBJECTIVES,
			'audience' => 'Producteurs en conversion.',
		])[0], 'les Enjeux et les Objectifs s\'écrivent');

		$data = $this->getFiche($eCourse);
		$details = new CourseUi()->getDetails($data->eCourse, $data->cStaff);

		$this->assertTrue(str_contains($details, 'annuel se pr'), 'les Enjeux rendus, avec leur texte : '.$details);
		$this->assertTrue(str_contains($details, 'unir les pi'), 'et les Objectifs');

		$audience = new CourseUi()->getAudience($data->eCourse, $data->cStaff);

		$this->assertFalse(str_contains($audience, 'annuel se pr'), 'le « Pour qui » du panneau n\'a pas d\'Enjeux : '.$audience);
		$this->assertFalse(str_contains($audience, 'unir les pi'), 'ni d\'Objectifs');

	}

	/**
	 * Trois modules d'une formation en présentiel, le deuxième sans leçon : les leçons gardent leur
	 * numéro continu (la leçon du troisième module est la deuxième de la formation), le menu perd le
	 * module vide, et l'étape 1 de l'inscription reprend la liste de la fiche, module vide compris.
	 */
	public function testProgram(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$this->createChapter($eCourse, 'zzTest points');
		$this->createLesson($this->createChapter($eCourse, 'zzTest suite'), 'zzTest leçon suivante', 10);

		$eDate = $this->createDate($eCourse, 10);

		$data = $this->getFiche($eCourse);

		$program = new CourseUi()->getProgram($data->eCourse, $data->cChapter, $data->cProgress);

		$this->assertTrue(str_contains($program, '2. zzTest leçon suivante'), 'la leçon du troisième module garde son numéro sur la formation');

		$menu = new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, FALSE, TRUE);

		$this->assertEquals([TRUE, FALSE], [str_contains($menu, 'zzTest suite'), str_contains($menu, 'zzTest points')], 'le menu garde chaque module qui a des leçons, sans le module vide : '.$menu);

		$eDateRead = DateLib::getById($eDate['id']);
		$eDateRead['course']['eSupervisorContact'] = SupervisorLib::getContactByCourse($eDateRead['course']);

		$reading = new DateEnrollmentUi()->getReading($eDateRead, $data->cChapter, $data->cStaff, withSummary: TRUE);

		$this->assertTrue(str_contains($reading, 'zzTest points'), 'l\'étape 1 de l\'inscription reprend la liste de la fiche, module vide compris');

	}

	/**
	 * Le mot Module et son numéro ne se lisent que sur une formation en ligne ; en visio et en
	 * présentiel, le module porte son seul nom. La même règle sur la fiche, dans le menu et sur les
	 * têtes de module de l'inscription.
	 */
	public function testChapterNameByType(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$name = 'zzTest module nommé';

		foreach([
			Course::ONLINE => FALSE,
			Course::REMOTE => TRUE,
			Course::ONSITE => TRUE,
		] as $type => $bare) {

			$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: $type);
			$this->createLesson($this->createChapter($eCourse, $name), 'zzTest leçon nommée', 10);

			$data = $this->getFiche($eCourse);

			$this->assertEquals($type, $data->eCourse['type'], 'la fiche porte le type de la formation');
			$this->assertEquals($bare, $this->isBare($this->getDeckTitles(new CourseUi()->getProgram($data->eCourse, $data->cChapter, $data->cProgress)), $name), $type.', le programme de la fiche');
			$this->assertEquals($bare, $this->isBare($this->getMenuTitles(new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, FALSE, TRUE)), $name), $type.', le menu de gauche');
			$this->assertEquals($bare, $this->isBare($this->getDeckTitles(new CourseUi()->getProgramHeads($data->eCourse, $data->cChapter)), $name), $type.', les têtes de module de l\'inscription');

		}

	}

	/**
	 * Une formation dont aucun module n'a de leçon, la visio courte du retour FRAB : son menu ne
	 * garde que la fiche.
	 */
	public function testMenuWithoutLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$this->createChapter($eCourse, 'zzTest premier point');
		$this->createChapter($eCourse, 'zzTest second point');

		$data = $this->getFiche($eCourse);

		$menu = new CourseUi()->getMenu($data->eCourse, $data->cChapter, $data->cProgress, FALSE, TRUE);

		$this->assertEquals([], $this->getMenuTitles($menu), 'aucun module au menu : '.$menu);
		$this->assertTrue(str_contains($menu, 'href="'.CourseUi::url($eCourse).'"'), 'la fiche reste');

		$this->assertEquals(['zzTest premier point', 'zzTest second point'], $this->getDeckTitles(new CourseUi()->getProgram($data->eCourse, $data->cChapter, $data->cProgress)), 'le programme de la fiche les garde');

	}

	/**
	 * Une formation en ligne publiée dont le seul module n'a plus de leçon (une dernière leçon
	 * supprimée après dépublication, puis republiée) : sa fiche ne montre pas de pile sans rien
	 * dessous, au visiteur comme au membre. La première leçon rend la pile.
	 */
	public function testProgramWithoutLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module vide');

		foreach([FALSE => 'au visiteur', TRUE => 'au membre'] as $isLogged => $who) {

			$program = strstr(strstr($this->renderFiche($eCourse, $isLogged), '<h2>'.s("Programme").'</h2>'), '<h2>'.s("La formation en détail").'</h2>', TRUE);

			$this->assertEquals([], $this->getDeckTitles($program), 'aucune pile de module vide, '.$who.' : '.$program);

		}

		$this->createLesson($eChapter, 'zzTest première leçon', 10);

		$data = $this->getFiche($eCourse);
		$program = new CourseUi()->getProgram($data->eCourse, $data->cChapter, $data->cProgress);

		$this->assertEquals(FALSE, $this->isBare($this->getDeckTitles($program), 'zzTest module vide'), 'une leçon rend la pile du module');

	}

	/**
	 * La ligne d'accès de la tête de fiche : au visiteur, la condition que pose le bouton « Lire la
	 * première leçon » (lot L4e), y compris sous un prix ; rien une fois connecté, ni en lecture
	 * libre.
	 */
	public function testAccess(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$this->createLessonWritten($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$access = CourseUi::getAccess($this->getCourse($eCourse['id']));

		$this->assertTrue(str_contains($this->renderFiche($eCourse, FALSE), $access), 'au visiteur, la condition du bouton de tête');
		$this->assertFalse(str_contains($this->renderFiche($eCourse, TRUE), $access), 'connecté, rien');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateOffer', ['id' => $eCourse['id'], 'offer' => Course::NETWORK, 'price' => '']), 'diffusée, payée avec l\'organisme');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdatePrice', [
			'id' => (string)$eCourse['id'],
			'priceFunded' => '42',
			'price' => '210',
			'funding' => [Course::VIVEA],
			'fundingNote' => '',
		]), 'deux montants et un financeur');

		$this->assertTrue(str_contains($this->renderFiche($eCourse, FALSE), $access), 'la condition malgré le prix');

		$eCourse = $this->getCourse($eCourse['id']);

		$fw = new \FailWatch();
		$eCourse->build(['open'], ['open' => '1']);
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('lecture libre refusée : '.json_encode($fw->get())));

		CourseLib::update($eCourse, ['open']);

		$this->assertFalse(str_contains($this->renderFiche($eCourse, FALSE), CourseUi::getAccess($this->getCourse($eCourse['id']))), 'en lecture libre, le bouton ne demande rien et la fiche non plus');

	}

	/**
	 * Une formation à des dates prend le prix de sa prochaine date, comme sa carte ; sans leçon, le
	 * visiteur s'inscrit sans compte et la fiche ne lui en demande pas.
	 */
	public function testAccessDated(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed(Course::ONSITE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdatePrice', ['id' => (string)$eCourse['id'], 'priceFunded' => '', 'price' => '90', 'fundingNote' => '']), 'la formation à 90 €');

		$eDate = $this->createDate($this->getCourse($eCourse['id']), 20);
		$eDateNext = $this->createDate($this->getCourse($eCourse['id']), 12);

		// Le prix posé sans le panneau de la date, qui ne fait que ce build() sur les mêmes propriétés.
		$eDateNext = DateLib::getById($eDateNext['id']);
		$fw = new \FailWatch();
		$eDateNext->build(CourseLib::getPropertiesPrice(), ['priceFunded' => '', 'price' => '60', 'fundingNote' => ''], new \PropertyList('update'));
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('prix de la date refusé : '.json_encode($fw->get())));
		DateLib::update($eDateNext, CourseLib::getPropertiesPrice());

		$data = $this->getFiche($eCourse);

		$this->assertEquals([$eDateNext['id'], 60.0], [$data->eCourse['eDateNext']['id'], $data->eCourse['eDateNext']['price']], 'le prix de la prochaine date, pas celui de la formation');
		$this->assertFalse(str_contains($this->renderFiche($eCourse, FALSE), CourseUi::getAccess($this->getCourse($eCourse['id']))), 'sans leçon, aucune condition de compte');

	}

	/**
	 * Le programme, le menu et les boutons de tête suivent la règle de la leçon vide de la colonne
	 * d'une leçon (`Lesson::isLinked()`) : une leçon vide se lit « À venir » et ne mène nulle part,
	 * sauf pour l'inscrit qui peut la cocher. « Lire la première leçon » et « Reprendre » sautent les
	 * leçons vides, qui restent comptées, comme `Course::isDone()` les compte. L'absence de lien se lit
	 * dans le rendu : c'est le contrat de la règle (aucune adresse vers une page vide), que l'écran ne
	 * montre pas.
	 */
	public function testEmptyLessons(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$eChapterFirst = $this->createChapter($eCourse, 'zzTest premier module');
		$eLessonEmpty = $this->createLesson($eChapterFirst, 'zzTest leçon vide', 5);
		$eLessonFirst = $this->createLessonWritten($eChapterFirst, 'zzTest première leçon écrite', 5);
		$eLessonSecond = $this->createLessonWritten($this->createChapter($eCourse, 'zzTest second module'), 'zzTest seconde leçon écrite', 5);

		$this->actAs($this->eUserReader);

		$read = function() use($eCourse, $eLessonEmpty): array {

			$data = $this->getFiche($eCourse);
			$ui = new CourseUi();

			// Une adresse se cherche entière, jamais comme le début d'une autre (`/lecon/12` dans `/lecon/123`).
			$hasUrl = fn(string $h, Lesson $eLesson): bool => preg_match('#href="'.preg_quote(LessonUi::url($eLesson), '#').'"#', $h) === 1;

			$program = $ui->getProgram($data->eCourse, $data->cChapter, $data->cProgress);
			$menu = $ui->getMenu($data->eCourse, $data->cChapterMenu, $data->cProgress, TRUE, FALSE);

			return [
				$data,
				[$hasUrl($program, $eLessonEmpty), $hasUrl($menu, $eLessonEmpty)],
			];

		};

		[$data, $links] = $read();

		$this->assertFalse($data->eCourse['canFollow'], 'sans inscription, le lecteur ne coche rien');
		$this->assertEquals([FALSE, FALSE], $links, 'la leçon vide ne mène nulle part, ni au programme ni au menu');
		$this->assertEquals([$eLessonFirst['id'], $eLessonFirst['id']], [$data->eCourse['eLessonFirst']['id'], $data->eCourse['eLessonResume']['id']], 'la tête mène à la première leçon écrite');
		$this->assertEquals([3, 0], [$data->eCourse['lessons'], $data->eCourse['done']], 'la leçon vide reste comptée');

		// Hors de `build()`, comme `LessonPage.t.php` inscrit son lecteur : le test éprouve ce que
		// l'inscription ouvre, pas la demande qui y mène.
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/lesson:doDone', ['id' => (string)$eLessonFirst['id']])[0], 'la première leçon écrite se coche');

		[$data, $links] = $read();

		$this->assertTrue($data->eCourse['canFollow'], 'inscrit, le lecteur coche');
		$this->assertEquals([TRUE, TRUE], $links, 'la leçon vide mène à sa page, pour qu\'il la coche');
		$this->assertEquals($eLessonSecond['id'], $data->eCourse['eLessonResume']['id'], '« Reprendre » saute la leçon vide non suivie');
		$this->assertEquals([3, 1], [$data->eCourse['lessons'], $data->eCourse['done']], 'et la bande compte toujours trois leçons');

	}

	/**
	 * Une formation dont aucune leçon n'est écrite se commence et se termine comme une autre pour
	 * l'inscrit : la tête mène alors à la première leçon vide, qu'il peut cocher, et l'attestation
	 * suit la dernière coche. Le lecteur qui ne peut pas cocher n'a aucune leçon à ouvrir, donc pas de
	 * bouton de tête.
	 */
	public function testNoLessonWritten(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eLessonFirst = $this->createLesson($eChapter, 'zzTest première leçon vide', 5);
		$eLessonSecond = $this->createLesson($eChapter, 'zzTest seconde leçon vide', 5);

		$this->actAs($this->eUserReader);

		$data = $this->getFiche($eCourse);

		$this->assertTrue($data->eCourse['eLessonFirst']->empty(), 'sans inscription, aucune leçon à ouvrir');
		$this->assertFalse(str_contains($this->renderFiche($eCourse, TRUE), LessonUi::url($eLessonFirst)), 'ni lien vers la leçon vide');

		// Hors de `build()`, comme `testEmptyLessons()` : le test éprouve ce que l'inscription ouvre.
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$data = $this->getFiche($eCourse);

		$this->assertEquals([$eLessonFirst['id'], $eLessonFirst['id']], [$data->eCourse['eLessonFirst']['id'], $data->eCourse['eLessonResume']['id']], 'inscrit, la tête mène à la première leçon vide');

		foreach([$eLessonFirst, $eLessonSecond] as $eLesson) {
			$this->assertEquals(\ReloadAction::class, $this->executePage('learning/lesson:doDone', ['id' => (string)$eLesson['id']])[0], 'la leçon vide se coche');
		}

		$data = $this->getFiche($eCourse);

		$this->assertTrue($data->eCourse['eLessonResume']->empty(), 'plus rien à reprendre');
		$this->assertTrue(str_contains($this->renderFiche($eCourse, TRUE), 'href="'.CourseUi::urlAttestation($eCourse).'"'), 'l\'attestation de suivi se télécharge');

	}

	private function renderFiche(Course $eCourse, bool $isLogged): string {

		$data = $this->getFiche($eCourse);

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $isLogged, $data->cDate);

	}

	private function getFiche(Course $eCourse): \stdClass {

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		if($data instanceof \stdClass) {
			return $data;
		}

		throw new \Exception('la fiche ne s\'ouvre pas : '.$data);

	}

	/**
	 * Les modules du menu de gauche, dans l'ordre.
	 *
	 * @return string[]
	 */
	private function getMenuTitles(string $html): array {

		preg_match_all('#<div class="util-filter-options-title util-filter-options-title-group">(.*?)</div>#s', $html, $matches);

		return $matches[1];

	}

	/**
	 * Les titres des piles, dans l'ordre.
	 *
	 * @return string[]
	 */
	private function getDeckTitles(string $html): array {

		preg_match_all('#<div class="util-deck-title">(.*?)</div>#s', $html, $matches);

		return $matches[1];

	}

	/**
	 * Le module porte-t-il son seul nom : NULL quand le titre unique attendu ne le nomme pas.
	 */
	private function isBare(array $titles, string $name): ?bool {

		if(count($titles) !== 1 or str_contains((string)$titles[0], $name) === FALSE) {
			return NULL;
		}

		return ($titles[0] === $name);

	}

}
?>
