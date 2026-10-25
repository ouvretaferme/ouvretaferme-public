<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * Le réglage de l'inscription d'une formation (lot annuaire) : fermer sur Ouvretaferme renvoie les
 * nouvelles inscriptions sur le site de l'organisme, exige la page de la formation sauf si chacune de
 * ses dates à venir a la sienne, et ne vaut que pour une formation en présentiel ou en visio.
 * L'offre et le prix des dates ne se montrent ni ne se saisissent tant que c'est fermé, et restent
 * en base : la réouverture les retrouve.
 *
 * Au catalogue public, une formation fermée compte comme proposée sans offre ; la rouvrir y rend
 * l'exigence d'une date à venir qui porte une offre (`Course::externalOnly.catalog`).
 *
 * Lancement : dev/test package=learning file=CourseExternal
 */
class CourseExternalTest extends LearningReviewFixtureTest {

	const COURSE_URL = 'https://formations.example.org/zztest-formation';
	const DATE_URL = 'https://formations.example.org/zztest-date';

	/**
	 * Fermer sans adresse est refusé sur le champ de l'adresse ; avec, la formation se ferme, et rien
	 * d'autre ne change. Rouvrir efface l'adresse (retour de Vincent du 30/09) : le panneau la
	 * poste avec la réponse non, son champ n'étant que masqué, et elle n'est pas gardée.
	 */
	public function testClose(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$this->assertFalse($eCourse['externalOnly'], 'une formation naît ouverte');
		$this->assertEquals(NULL, $eCourse['externalUrl'], 'sans adresse');

		$this->assertEquals([\FailAction::class, 'Course::externalUrl.uncovered'], $this->update($eCourse, TRUE, ''), 'sans date, fermer sans page de la formation est refusé');
		$this->assertTrue(NULL !== AlertUi::getError('Course::externalUrl.uncovered'), 'le refus a son message');
		$this->assertFalse($this->getCourse($eCourse['id'])['externalOnly'], 'rien d\'écrit');

		$this->assertEquals([\FailAction::class, 'Course::externalUrl.check'], $this->update($eCourse, TRUE, 'pas une adresse'), 'une adresse mal formée est refusée');
		$this->assertTrue(NULL !== AlertUi::getError('Course::externalUrl.check'), 'le refus du schéma a son message');

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, TRUE, self::COURSE_URL), 'fermer avec l\'adresse');
		$this->assertTrue(NULL !== AlertUi::getSuccess('Course::externalUpdated'), 'le succès a son message');

		$eCourseClosed = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseClosed->isExternal(), 'la formation est fermée');
		$this->assertEquals(self::COURSE_URL, $eCourseClosed['externalUrl'], 'à son adresse');

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, FALSE, NULL), 'rouvrir sans poster d\'adresse');
		$this->assertFalse($this->getCourse($eCourse['id'])->isExternal(), 'la formation est rouverte');
		$this->assertEquals(NULL, $this->getCourse($eCourse['id'])['externalUrl'], 'sans adresse');

		$this->update($eCourse, TRUE, self::COURSE_URL);
		$this->assertEquals(self::COURSE_URL, $this->getCourse($eCourse['id'])['externalUrl'], 'refermée, à son adresse');

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, FALSE, self::COURSE_URL), 'rouvrir en postant une adresse quand même');
		$this->assertFalse($this->getCourse($eCourse['id'])->isExternal(), 'la formation est rouverte');
		$this->assertEquals(NULL, $this->getCourse($eCourse['id'])['externalUrl'], 'aucune adresse gardée quand la réponse est non');

		// Le champ masqué par la réponse non part quand même, avec ce qui y a été tapé avant.
		$this->update($eCourse, TRUE, self::COURSE_URL);
		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, FALSE, 'pas une adresse'), 'rouvrir avec une adresse mal formée dans le champ masqué n\'est pas refusé');
		$this->assertFalse($this->getCourse($eCourse['id'])->isExternal(), 'la formation est rouverte');
		$this->assertEquals(NULL, $this->getCourse($eCourse['id'])['externalUrl'], 'sans adresse');

	}

	/**
	 * Le panneau en une ligne oui / non (retour de Vincent du 01/10) et la ligne qui le résume (retour
	 * du 30/09, point 1), en mémoire. La question est rendue par le oui / non du cadriciel ; la page
	 * de la formation est le seul champ que la réponse fait paraître (`field-toggle`), et son
	 * astérisque suit `externalUrlRequired`. L'onglet Organisation dit où se font les nouvelles
	 * inscriptions, avec la page de la formation quand c'est chez l'organisme.
	 */
	public function testScreens(): void {

		$eCourse = new Course(['id' => 1, 'type' => Course::ONSITE, 'externalOnly' => FALSE, 'externalUrl' => NULL]);

		$eCourse['externalUrlRequired'] = TRUE;

		$panel = new CourseUi()->updateExternal($eCourse)->body;

		$this->assertTrue((bool)preg_match('#^<form class="[^"]*\bfield-toggle-wrapper"[^>]* novalidate>#', $panel), 'le formulaire porte la bascule du socle, et laisse le schéma juger une adresse masquée : '.$panel);
		$this->assertEquals(['1' => FALSE, '0' => TRUE], $this->getYesNo($panel), 'oui puis non, non retenu');
		$this->assertTrue(str_contains($panel, 'data-type="yesNo"'), 'le oui / non du cadriciel, que lit la bascule');
		$this->assertEquals(1, substr_count($panel, 'name="externalUrl"'), 'un seul champ d\'adresse');
		$this->assertTrue((bool)preg_match('#<div data-wrapper="externalUrl"[^>]*class="form-group\s+field-toggle"#', $panel), 'que la réponse fait paraître');
		$this->assertEquals(1, substr_count($panel, 'class="form-group  field-toggle"'), 'et lui seul');
		$this->assertTrue(str_contains($panel, 'form-asterisk'), 'la page de la formation, obligatoire');

		$htmlOpen = $this->getEditionExternal($eCourse);

		$eCourse['externalOnly'] = TRUE;
		$eCourse['externalUrl'] = self::COURSE_URL;

		$panel = new CourseUi()->updateExternal($eCourse)->body;

		$this->assertEquals(['1' => TRUE, '0' => FALSE], $this->getYesNo($panel), 'fermée, le panneau s\'ouvre sur oui');
		$this->assertTrue(str_contains($panel, 'value="'.self::COURSE_URL.'"'), 'avec l\'adresse enregistrée');

		$html = $this->getEditionExternal($eCourse);

		$this->assertTrue(str_contains($html, '<a href="'.self::COURSE_URL.'" target="_blank">'), 'sur le site de l\'organisme, suivi du lien : '.$html);

		// Chaque date à venir a sa page : celle de la formation n'est plus obligatoire, et peut manquer.
		$eCourse['externalUrlRequired'] = FALSE;
		$eCourse['externalUrl'] = NULL;

		$panel = new CourseUi()->updateExternal($eCourse)->body;

		$this->assertFalse(str_contains($panel, 'form-asterisk'), 'aucun champ obligatoire : '.$panel);

		$html = $this->getEditionExternal($eCourse);

		$this->assertFalse(str_contains($html, 'target="_blank"'), 'sur le site de l\'organisme, sans lien : '.$html);
		$this->assertTrue($html !== $htmlOpen, 'et pas sur Ouvretaferme');

	}

	/**
	 * Le réglage n'existe pas pour une formation en ligne, et une formation passée en ligne avant sa
	 * première date le perd.
	 */
	public function testOnline(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourseOnline = $this->seed(Course::ONLINE);

		$this->assertTrue($this->update($eCourseOnline, TRUE, self::COURSE_URL)[0] !== \ReloadAction::class, 'une formation en ligne ne se ferme pas');
		$this->assertFalse($this->getCourse($eCourseOnline['id'])['externalOnly'], 'rien d\'écrit');

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, TRUE, self::COURSE_URL), 'une formation sur place, sans date, se ferme');

		$this->assertEquals(\RedirectAction::class, $this->executePage('learning/course:doUpdateStartType', ['id' => (string)$eCourse['id'], 'type' => Course::ONLINE])[0], 'elle passe en ligne avant sa première date');

		$eCourseOnline = $this->getCourse($eCourse['id']);

		$this->assertEquals(Course::ONLINE, $eCourseOnline['type'], 'elle est en ligne');
		$this->assertFalse($eCourseOnline['externalOnly'], 'et rouverte');
		$this->assertEquals(NULL, $eCourseOnline['externalUrl'], 'sans adresse');

	}

	/**
	 * Fermée, l'offre d'une date ne se change plus, la formation est proposée sans offre ; rouverte,
	 * l'offre et le prix sont ceux d'avant.
	 */
	public function testReopenKeepsOffer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		// Les trois dates d'abord : une date neuve reprend l'offre de la dernière (`DateLib::create()`).
		$eDateNetwork = $this->createDate($eCourse, 10);
		$eDateTrainee = $this->createDate($eCourse, 20);
		$eDateNone = $this->createDate($eCourse, 30);

		$eDateNetwork = $this->setOffer($eDateNetwork, Date::NETWORK, NULL);
		$eDateTrainee = $this->setOffer($eDateTrainee, Date::TRAINEE, 90.0);

		$this->assertTrue($this->acceptUpdateOffer($eDateNetwork), 'ouverte, l\'offre d\'une date se change');

		$this->update($eCourse, TRUE, self::COURSE_URL);

		$this->assertFalse($this->acceptUpdateOffer($eDateNetwork), 'fermée, l\'offre ne se change plus');
		$this->assertEquals(\NotExpectedAction::class, $this->executePage('learning/date:doUpdateOffer', ['id' => (string)$eDateNetwork['id'], 'offer' => Date::NONE])[0], 'ni par son action');
		$this->assertTrue(CourseLib::isOffered($this->getCourse($eCourse['id'])), 'fermée, la formation est proposée sans offre à choisir');

		$this->update($eCourse, FALSE, self::COURSE_URL);

		$this->assertEquals([Date::NETWORK, NULL], $this->getOffer($eDateNetwork), 'rouverte, l\'offre payée par le réseau est retrouvée');
		$this->assertEquals([Date::TRAINEE, 90.0], $this->getOffer($eDateTrainee), 'l\'offre payée par le stagiaire et son prix aussi');
		$this->assertEquals([Date::NONE, NULL], $this->getOffer($eDateNone), 'une date sans offre le reste');
		$this->assertTrue($this->acceptUpdateOffer($eDateNetwork), 'rouverte, l\'offre se change de nouveau');

	}

	/**
	 * Au catalogue, fermée sans aucune offre : rouvrir est refusé tant qu'aucune date à venir ne porte
	 * d'offre, accepté dès qu'une en porte une. Hors du catalogue, rouvrir n'est jamais refusé.
	 */
	public function testReopenCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->update($eCourse, TRUE, self::COURSE_URL);

		$this->assertFalse(DateLib::existsOfferedByCourse($eCourse), 'aucune date à venir ne porte d\'offre');
		$this->assertTrue(CourseLib::isOffered($this->getCourse($eCourse['id'])), 'fermée, la formation se propose quand même');

		$this->setCatalog($eCourse, Course::ACCEPTED);

		$this->assertEquals([\FailAction::class, 'Course::externalOnly.catalog'], $this->update($eCourse, FALSE, self::COURSE_URL), 'au catalogue, rouvrir sans date offerte à venir est refusé');
		$this->assertTrue(NULL !== AlertUi::getError('Course::externalOnly.catalog'), 'le refus a son message');
		$this->assertTrue($this->getCourse($eCourse['id'])->isExternal(), 'la formation reste fermée');

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, TRUE, self::DATE_URL), 'fermée, l\'adresse se change encore');

		$this->setOffer($eDate, Date::NETWORK, NULL);

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, FALSE, self::COURSE_URL), 'avec une date offerte à venir, rouvrir est accepté');
		$this->assertFalse($this->getCourse($eCourse['id'])->isExternal(), 'la formation est rouverte');

		// Hors du catalogue, aucune condition.
		[$eCourseOther] = $this->seedCourse(quiz: FALSE, first: FALSE);
		$this->createDate($eCourseOther, 10);

		$this->update($eCourseOther, TRUE, self::COURSE_URL);
		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourseOther, FALSE, self::COURSE_URL), 'hors du catalogue, rouvrir est toujours accepté');

	}

	/**
	 * L'adresse d'une date prime sur celle de la formation ; sans elle, la formation.
	 */
	public function testDateUrl(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->update($eCourse, TRUE, self::COURSE_URL);

		$this->assertEquals(self::COURSE_URL, DateLib::getById($eDate['id'])->getExternalUrl(), 'sans adresse, la date renvoie à la formation');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doUpdate', [
			'id' => (string)$eDate['id'],
			'seats' => '',
			'externalUrl' => self::DATE_URL,
		] + $this->postSequences([
			[substr($eDate['startAt'], 0, 10), substr($eDate['startAt'], 11, 5), substr($eDate['endAt'], 11, 5), DateSequence::ONSITE, 'Salle des fêtes, Crest'],
		])), 'le panneau de la date écrit son adresse');

		$this->assertEquals(self::DATE_URL, DateLib::getById($eDate['id'])->getExternalUrl(), 'l\'adresse de la date prime');

	}

	/**
	 * Une des deux pages suffit (Vincent, 01/10/2026) : fermer sans page de la formation est refusé
	 * tant qu'une date à venir n'a pas la sienne, accepté quand chacune l'a. Une date annulée ou
	 * passée ne reçoit plus personne et ne compte pas. Le panneau ne marque le champ obligatoire que
	 * dans le premier cas.
	 */
	public function testCoveredByDates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDateFirst = $this->createDate($eCourse, 10);
		$eDateSecond = $this->createDate($eCourse, 20);

		$this->assertEquals([\FailAction::class, 'Course::externalUrl.uncovered'], $this->update($eCourse, TRUE, ''), 'aucune date n\'a sa page, fermer sans page de la formation est refusé');
		$this->assertTrue($this->getExternalUrlRequired($eCourse), 'le panneau marque la page de la formation obligatoire');

		$this->assertEquals([\ReloadAction::class, NULL], $this->writeDate($eDateFirst, self::DATE_URL), 'la première date reçoit sa page');

		$this->assertEquals([\FailAction::class, 'Course::externalUrl.uncovered'], $this->update($eCourse, TRUE, ''), 'une date sur deux a sa page, fermer sans page de la formation est refusé');
		$this->assertFalse($this->getCourse($eCourse['id'])['externalOnly'], 'rien d\'écrit');

		$this->assertEquals([\ReloadAction::class, NULL], $this->writeDate($eDateSecond, self::DATE_URL), 'la seconde aussi');

		// Une date annulée et une date passée, sans page : elles ne comptent pas.
		$eDateCanceled = $this->createDate($eCourse, 30);
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));
		$this->createDate($eCourse, -10);

		$this->assertFalse($this->getExternalUrlRequired($eCourse), 'chaque date à venir a sa page, le champ n\'est plus obligatoire');

		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, TRUE, ''), 'chaque date à venir a sa page, fermer sans page de la formation est accepté');

		$eCourseClosed = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseClosed->isExternal(), 'la formation est fermée');
		$this->assertEquals(NULL, $eCourseClosed['externalUrl'], 'sans page à elle');
		$this->assertEquals(self::DATE_URL, DateLib::getById($eDateFirst['id'])->getExternalUrl(), 'la date envoie à sa page');

		$html = $this->getFicheHtml($eCourse);
		$this->assertEquals(2, substr_count($html, 'href="'.self::DATE_URL.'"'), 'chaque date planifiée porte son lien sortant');

	}

	/**
	 * Une date ajoutée ou modifiée après coup sur une formation fermée sans page redemande la sienne,
	 * avec un message qui dit quoi faire. Avec la page de la formation, une date s'en passe.
	 */
	public function testDateUncovered(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->writeDate($eDate, self::DATE_URL);
		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, TRUE, ''), 'fermée sur la page de sa seule date');

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertTrue(new Date(['course' => $eCourse])->requiresExternalUrl(), 'une date neuve doit porter sa page');

		$this->assertEquals([\FailAction::class, 'Date::externalUrl.uncovered'], $this->writeDate($eCourse, ''), 'une date ajoutée sans page est refusée');
		$this->assertTrue(NULL !== AlertUi::getError('Date::externalUrl.uncovered'), 'le refus a son message');
		$this->assertEquals(1, DateLib::getByCourse($eCourse)->count(), 'aucune date écrite');

		$this->assertEquals([\ReloadAction::class, NULL], $this->writeDate($eCourse, self::DATE_URL), 'une date ajoutée avec sa page est acceptée');
		$this->assertEquals(2, DateLib::getByCourse($eCourse)->count(), 'la date est écrite');

		$this->assertEquals([\FailAction::class, 'Date::externalUrl.uncovered'], $this->writeDate($eDate, ''), 'retirer la page d\'une date est refusé');
		$this->assertEquals(self::DATE_URL, DateLib::getById($eDate['id'])['externalUrl'], 'la page reste');

		// La page de la formation posée, une date s'en passe.
		$this->assertEquals([\ReloadAction::class, NULL], $this->update($eCourse, TRUE, self::COURSE_URL), 'la formation reçoit sa page');

		$this->assertFalse(new Date(['course' => $this->getCourse($eCourse['id'])])->requiresExternalUrl(), 'une date neuve ne la doit plus');
		$this->assertEquals([\ReloadAction::class, NULL], $this->writeDate($eDate, ''), 'retirer la page d\'une date est accepté');
		$this->assertEquals([\ReloadAction::class, NULL], $this->writeDate($eCourse, ''), 'ajouter une date sans page aussi');
		$this->assertEquals(self::COURSE_URL, DateLib::getById($eDate['id'])->getExternalUrl(), 'la date renvoie à la formation');

		// Ouverte, la page d'une date ne regarde personne.
		$this->update($eCourse, FALSE, NULL);
		$this->assertEquals([\ReloadAction::class, NULL], $this->writeDate($eCourse, ''), 'ouverte, une date sans page est acceptée');

	}

	/**
	 * Fermée, la fiche pose le lien sortant en tête quand aucune date planifiée à venir ne le porte :
	 * une date annulée, qui reste listée, n'a pas de porte et ne compte donc pas. Avec une date
	 * planifiée, le lien est sur sa pile, une seule fois.
	 */
	public function testFicheWithoutPlannedDate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->update($eCourse, TRUE, self::COURSE_URL);

		$this->assertEquals(1, substr_count($this->getFicheHtml($eCourse), 'href="'.self::COURSE_URL.'"'), 'une date planifiée porte le lien, la tête de fiche non');

		DateLib::cancel(DateLib::getById($eDate['id']));

		$html = $this->getFicheHtml($eCourse);

		$this->assertEquals(1, substr_count($html, 'href="'.self::COURSE_URL.'"'), 'seule une date annulée à venir : le lien passe en tête de fiche');

	}

	/**
	 * Au catalogue, fermée et sans avis, la fiche n'a pas de section « Avis » : plus personne ne s'y
	 * confirme sur Ouvretaferme, la mention promettrait des avis qui ne viendront pas. Fermée avec
	 * un avis donné avant la fermeture, la section reste ; ouverte sans avis aussi.
	 */
	public function testReviewSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourseEmpty] = $this->seedCatalog();
		$this->createDate($eCourseEmpty, 10);

		$this->assertTrue(str_contains($this->getFicheHtml($eCourseEmpty), 'id="avis"'), 'ouverte sans avis, la section est là');

		$this->update($eCourseEmpty, TRUE, self::COURSE_URL);

		$this->assertFalse(str_contains($this->getFicheHtml($eCourseEmpty), 'id="avis"'), 'fermée sans avis, pas de section');

		[$eCourseReviewed] = $this->seedCatalog(first: FALSE);

		$eUser = $this->createPerson();
		$this->followDate($eCourseReviewed, $eUser);

		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourseReviewed, $eUser, 4, 'zzTest avis'), 'avis donné avant la fermeture');

		$this->update($eCourseReviewed, TRUE, self::COURSE_URL);

		$html = $this->getFicheHtml($eCourseReviewed);

		$this->assertTrue(str_contains($html, 'id="avis"'), 'fermée avec un avis, la section reste');
		$this->assertTrue(str_contains($html, 'zzTest avis'), 'avec l\'avis');

	}

	/**
	 * Une date posée par son panneau, comme la page le poste : ajoutée à la formation, ou modifiée à
	 * ses jours. `$externalUrl` vide efface la page de la date.
	 */
	private function writeDate(Course|Date $e, string $externalUrl): array {

		if($e instanceof Course) {
			$day = $this->day(40);
			$input = ['course' => (string)$e['id']];
			$page = 'learning/date:doCreate';
		} else {
			$e = DateLib::getById($e['id']);
			$day = substr($e['startAt'], 0, 10);
			$input = ['id' => (string)$e['id']];
			$page = 'learning/date:doUpdate';
		}

		return $this->executePage($page, $input + [
			'seats' => '',
			'externalUrl' => $externalUrl,
		] + $this->postSequences([
			[$day, '09:00', '17:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
		]));

	}

	/**
	 * L'astérisque du panneau de l'inscription, tel que la page le pose.
	 */
	private function getExternalUrlRequired(Course $eCourse): bool {

		$data = $this->executeView('learning/course:updateExternal', ['id' => (string)$eCourse['id']]);
		$this->assertTrue($data instanceof \stdClass, 'le panneau s\'ouvre : '.(is_string($data) ? $data : ''));

		return $data->e['externalUrlRequired'];

	}

	/**
	 * Les deux réponses du oui / non dans l'ordre, chacune avec son état coché.
	 *
	 * @return bool[]
	 */
	private function getYesNo(string $panel): array {

		preg_match_all('#<input id="externalOnly[01]"( checked="checked")? name="externalOnly" type="radio" value="([01])"/>#', $panel, $matches, PREG_SET_ORDER);

		return array_column(array_map(fn(array $match) => [$match[2], $match[1] !== ''], $matches), 1, 0);

	}

	private function getEditionExternal(Course $eCourse): string {
		return new \ReflectionMethod(CourseUi::class, 'getEditionExternal')->invoke(new CourseUi(), $eCourse);
	}

	private function getFicheHtml(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'], []);
		$this->assertTrue($data instanceof \stdClass, 'la fiche s\'ouvre : '.(is_string($data) ? $data : ''));

		if(is_string($data)) {
			return '';
		}

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

	}

	/**
	 * Le panneau de l'inscription, comme la page le poste. `$externalUrl` à NULL : l'adresse n'est pas
	 * postée, ce que ne fait jamais le panneau, dont le champ masqué part avec le reste.
	 */
	private function update(Course $eCourse, bool $externalOnly, ?string $externalUrl): array {

		$input = [
			'id' => (string)$eCourse['id'],
			'externalOnly' => $externalOnly ? '1' : '0',
		];

		if($externalUrl !== NULL) {
			$input['externalUrl'] = $externalUrl;
		}

		return $this->executePage('learning/course:doUpdateExternal', $input);

	}

	/**
	 * L'offre posée sans `build()` : les offres payantes ne s'ouvrent qu'avec `LearningSetting::$active`
	 * (`Date::offer.paid`), que ce test n'éprouve pas ; il lui faut seulement des dates qui en portent
	 * une.
	 */
	private function setOffer(Date $eDate, string $offer, ?float $price): Date {

		$eDate = DateLib::getById($eDate['id']);
		$eDate['offer'] = $offer;
		$eDate['price'] = $price;

		DateLib::update($eDate, ['offer', 'price']);

		return DateLib::getById($eDate['id']);

	}

	/**
	 * La place au catalogue posée sans le circuit de demande et de décision (`updateCatalog()`), que
	 * `CatalogCircuit.t.php` éprouve et qui demande l'équipe de Ouvretaferme.
	 */
	private function setCatalog(Course $eCourse, string $catalog): void {

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['catalog'] = $catalog;

		CourseLib::update($eCourse, ['catalog']);

	}

	private function getOffer(Date $eDate): array {

		$eDate = DateLib::getById($eDate['id']);

		return [$eDate['offer'], $eDate['price']];

	}

	private function acceptUpdateOffer(Date $eDate): bool {

		return DateLib::getById($eDate['id'])->acceptUpdateOffer();

	}

}
?>
