<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les dates d'une formation de réseau : ce que l'écriture accepte et refuse, ce qu'une annulation
 * fige, ce qu'une suppression protège, et ce que la publication et les compteurs en lisent.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test : le réseau
 * G3 du décor commun (`COMMUNITY_NETWORK`), de type `network`, dont `setUp()` fait de
 * l'utilisateur 1 (`USER_ADMIN`) un conseiller IN. Tout le reste se pose dans la transaction
 * du test, et les dates s'écrivent par le chemin de `doCreate`.
 */
class DateTest extends LearningFixtureTest {

	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	private \structure\Structure $eStructureNetwork;

	protected function getMemberships(): array {
		return parent::getMemberships() + [
			self::COMMUNITY_NETWORK => 'advisor',
		];
	}

	protected function assertFixtures(): bool {

		if(parent::assertFixtures() === FALSE) {
			return FALSE;
		}

		$this->eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);

		if($this->eStructureNetwork->empty() or $this->eStructureNetwork->isNetwork() === FALSE) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' est absent de cette base, ou n\'est pas un réseau');
			return FALSE;
		}

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureNetwork);

		if(
			$eMember->empty() or
			$eMember['status'] !== \structure\Member::IN or
			$eMember['role']['fqn'] !== 'advisor'
		) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'est pas conseiller IN du réseau '.self::COMMUNITY_NETWORK.' dans cette base');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Une date en visio porte son lien et ses séances pas de lieu, une date en présentiel
	 * l'inverse, et une date peut courir sur deux jours, une séance par jour (lot date-mixte). Le
	 * jour et l'heure d'une séance arrivent séparés du formulaire ; la date prend pour bornes le
	 * début de la première séance et la fin de la dernière.
	 */
	public function testCreate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseRemote = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doCreate', $this->posted($eCourseRemote, $this->postSequences([
			[$this->day(10), '14:00', '16:30', DateSequence::REMOTE, 'Un lieu que le formulaire cache et poste encore'],
		]) + [
			'link' => 'https://visio.example.org/salle',
			'seats' => '',
		])), 'une visio');

		// Les séances postées dans le désordre : la date les range.
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doCreate', $this->posted($eCourseOnsite, $this->postSequences([
			[$this->day(21), '09:00', '17:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
			[$this->day(20), '09:00', '17:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
		]) + [
			'link' => 'https://visio.example.org/oubliee',
			'seats' => '12',
		])), 'deux jours sur place');

		$eDateRemote = DateLib::getByCourse($eCourseRemote)->first();
		$eDateOnsite = DateLib::getByCourse($eCourseOnsite)->first();

		$this->assertEquals($this->day(10).' 14:00:00', $eDateRemote['startAt'], 'le jour et l\'heure recomposés');
		$this->assertEquals($this->day(10).' 16:30:00', $eDateRemote['endAt'], 'la fin aussi');
		$this->assertEquals('https://visio.example.org/salle', $eDateRemote['link'], 'le lien de la visio');
		$this->assertEquals(1, $eDateRemote['cDateSequence']->count(), 'une séance');
		$this->assertEquals(NULL, $eDateRemote['cDateSequence']->first()['place'], 'pas de lieu pour une visio, même posté');
		$this->assertEquals(NULL, $eDateRemote['seats'], 'sans nombre de places');
		$this->assertEquals(Date::PLANNED, $eDateRemote['status'], 'planifiée à la création');

		$this->assertEquals($this->day(20).' 09:00:00', $eDateOnsite['startAt'], 'le début de la première séance');
		$this->assertEquals($this->day(21).' 17:00:00', $eDateOnsite['endAt'], 'la fin sur le lendemain');
		$this->assertEquals([$this->day(20).' 09:00:00', $this->day(21).' 09:00:00'], $eDateOnsite['cDateSequence']->getColumn('startAt'), 'une séance par jour, rangées');
		$this->assertEquals('Salle des fêtes, Crest', $eDateOnsite->getPlace(), 'le lieu');
		$this->assertEquals(NULL, $eDateOnsite['link'], 'pas de lien sur place, même posté');
		$this->assertEquals(12, $eDateOnsite['seats'], 'les places');

	}

	/**
	 * Une fin avant le début, un lien qui n'est pas une adresse, une séance en présentiel sans
	 * lieu : refusés avec leur nom, sur leur séance. Une date sans séance, deux séances qui se
	 * chevauchent, plus de séances que le plafond : refusés sur la date. Une visio sans lien
	 * s'écrit (`DateLink.t.php`). Une formation d'école n'a pas de dates, une formation de réseau
	 * en ligne non plus, et un compte qui n'écrit pas la formation n'en date pas.
	 */
	public function testRefusals(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);

		$link = ['link' => 'https://visio.example.org/salle'];
		$remote = $this->postSequences([[$this->day(10), '14:00', '16:30', DateSequence::REMOTE]]) + $link;

		$sequence = fn(string $day, string $start, string $end, string $mode = DateSequence::REMOTE, string $place = '') => $this->postSequences([[$day, $start, $end, $mode, $place]]) + $link;

		$this->assertEquals([\FailAction::class, 'DateSequence::endAt.consistent'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $sequence($this->day(10), '14:00', '12:00'))), 'fin avant début');
		$this->assertEquals([\FailAction::class, 'DateSequence::endAt.consistent'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $sequence($this->day(10), '14:00', '14:00'))), 'fin au même instant que le début');
		$this->assertEquals([\FailAction::class, 'Date::link.check'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, ['link' => 'salle'] + $remote)), 'lien qui n\'est pas une adresse');
		$this->assertEquals([\FailAction::class, 'DateSequence::place.required'], $this->executePage('learning/date:doCreate', $this->posted($eCourseOnsite, $sequence($this->day(10), '14:00', '16:30', DateSequence::ONSITE))), 'sur place sans lieu');
		$this->assertEquals([\FailAction::class, 'DateSequence::startAt.check'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $sequence('', '14:00', '16:30'))), 'sans jour');
		$this->assertEquals([\FailAction::class, 'DateSequence::endAt.check'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $sequence($this->day(10), '14:00', ''))), 'sans heure de fin');
		$this->assertEquals([\FailAction::class, 'Date::sequences.none'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $link)), 'sans séance');
		$this->assertEquals([\FailAction::class, 'Date::sequences.overlap'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $this->postSequences([
			[$this->day(10), '09:00', '12:30', DateSequence::REMOTE],
			[$this->day(10), '12:00', '17:00', DateSequence::REMOTE],
		]) + $link)), 'deux séances qui se chevauchent');
		$this->assertEquals([\FailAction::class, 'Date::sequences.tooMany'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $this->postSequences(array_map(
			fn(int $offset) => [$this->day($offset), '09:00', '12:00', DateSequence::REMOTE],
			range(10, 10 + DateSequence::MAX_BY_DATE)
		)) + $link)), 'une séance de plus que le plafond');
		$this->assertEquals([\FailAction::class, 'Date::sequences.check'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, ['sequences' => [['x' => '3']]] + $remote)), 'un index de séance posté en tableau');

		// Une séance refusée arrête la date sans autre refus : le chevauchement ne se juge que sur des
		// séances construites.
		$this->assertEquals([\FailAction::class, 'DateSequence::endAt.consistent'], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $this->postSequences([
			[$this->day(10), '09:00', '08:00', DateSequence::REMOTE],
			[$this->day(10), '07:00', '17:00', DateSequence::REMOTE],
		]) + $link)), 'le premier refus seul');

		$this->assertEquals(0, DateLib::getByCourse($eCourse)->count(), 'rien d\'écrit');
		$this->assertEquals(0, DateLib::getByCourse($eCourseOnsite)->count(), 'ni sur place');

		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doCreate', $this->posted($eCourseOnline, $remote)), 'une formation de réseau en ligne ne se date pas');
		$this->assertFalse(DateLib::existsByCourse($eCourseOnline), 'et n\'en a aucune');

		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doCreate', $this->posted($eCourseEducation, $remote)), 'une formation d\'école ne se date pas');
		$this->assertFalse(DateLib::existsByCourse($eCourseEducation), 'et n\'en a aucune');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/date:doCreate', $this->posted($eCourse, $remote)), 'un compte hors du réseau ne date pas');

	}

	/**
	 * Une date annulée reste écrite et lisible, mais ne se modifie plus ni ne s'annule deux fois ;
	 * une date passée non plus. Toutes deux se suppriment encore.
	 */
	public function testCancel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$eDate = $this->createDate($eCourse, 10);

		$this->assertTrue($eDate->acceptCancel(), 'planifiée à venir, annulable');
		$this->assertTrue($eDate->acceptUpdate(), 'et modifiable');

		$eDatePanel = $this->getDate($eDate['id']);
		DateEnrollmentLib::fillCounts(new \Collection([$eDatePanel]));
		DateEnrollmentLib::fillLosesLessonsByDate($eDatePanel);

		$panel = new DateUi()->cancel($eDatePanel);

		$this->assertTrue(str_contains($panel->body, 'data-ajax="/learning/date:doCancel"'), 'le panneau d\'annulation porte son geste : '.$panel->body);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doCancel', ['id' => (string)$eDate['id']]), 'annulée');

		$eDate = $this->getDate($eDate['id']);

		$this->assertEquals(Date::CANCELED, $eDate['status'], 'l\'état écrit');
		$this->assertFalse($eDate->acceptUpdate(), 'ne se modifie plus');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doUpdate', ['id' => (string)$eDate['id'], 'link' => 'https://visio.example.org/salle'] + $this->postSequences([[$this->day(11), '09:00', '12:00', DateSequence::REMOTE]])), 'la modification refusée');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doCancel', ['id' => (string)$eDate['id']]), 'ne s\'annule pas deux fois');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doDelete', ['id' => (string)$eDate['id']]), 'se supprime encore');

		$eDatePast = $this->createDate($eCourse, -5);

		$this->assertTrue($eDatePast->isPast(), 'passée');
		$this->assertFalse($eDatePast->acceptCancel(), 'une date passée ne s\'annule pas');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doCancel', ['id' => (string)$eDatePast['id']]), 'et l\'action le refuse');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doDelete', ['id' => (string)$eDatePast['id']]), 'mais se supprime');

		$this->assertEquals(0, DateLib::getByCourse($eCourse)->count(), 'plus aucune date');

	}

	/**
	 * La modification remplace les séances et recompose les bornes ; le lien posté sur une date
	 * sans séance en visio est effacé.
	 */
	public function testUpdate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		$eDate = $this->createDate($eCourse, 10);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doUpdate', [
			'id' => (string)$eDate['id'],
			'link' => 'https://visio.example.org/salle',
			'seats' => '8',
		] + $this->postSequences([[$this->day(12), '09:00', '12:00', DateSequence::ONSITE, 'Aubenas']])), 'modifiée');

		$eDate = $this->getDate($eDate['id']);

		$this->assertEquals($this->day(12).' 09:00:00', $eDate['startAt'], 'le début');
		$this->assertEquals($this->day(12).' 12:00:00', $eDate['endAt'], 'la fin');
		$this->assertEquals(1, $eDate['cDateSequence']->count(), 'la séance remplacée, pas ajoutée');
		$this->assertEquals('Aubenas', $eDate->getPlace(), 'le lieu');
		$this->assertEquals(NULL, $eDate['link'], 'le lien de la visio effacé');
		$this->assertEquals(8, $eDate['seats'], 'les places');

	}

	/**
	 * Une formation publiée sans leçon se publie vide (décision de Vincent du 04/10) : sa dernière
	 * date planifiée se supprime comme les autres, et la formation reste publiée.
	 */
	public function testDeleteLastPublished(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::REMOTE);
		$eDate = $this->createDate($eCourse, 10);
		$eDateCanceled = $this->createDate($eCourse, 15);
		DateLib::cancel($eDateCanceled);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doDelete', ['id' => (string)$eDate['id']]), 'la dernière planifiée d\'une formation publiée sans leçon');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doDelete', ['id' => (string)$eDateCanceled['id']]), 'l\'annulée');

		$this->assertEquals([0, Course::PUBLISHED], [
			DateLib::getByCourse($eCourse)->count(),
			$this->getCourse($eCourse['id'])['status']
		], 'la formation reste publiée, sans date');

	}

	/**
	 * Une formation de réseau se publie sans leçon ni date, et la date annulée n'y change rien : le
	 * programme ne retient plus la publication. Ce qui la retient encore est éprouvé dans
	 * `Classified.t.php`.
	 */
	public function testPublication(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);

		$this->assertEquals(['price'], CourseLib::getMissingForPublication($eCourse, new \Collection())[0], 'sans prix, le panneau le nomme');

		$eCourse = $this->setFree($eCourse);

		$this->assertEquals([], $this->publish($eCourse), 'sans leçon ni date');

		[$properties, $cLesson] = CourseLib::getMissingForPublication($eCourse, new \Collection());

		$this->assertEquals([[], []], [$properties, $cLesson->getIds()], 'le panneau ne nomme rien');

		$eDate = $this->createDate($eCourse, 10);
		DateLib::cancel($eDate);

		$this->assertEquals([], $this->publish($eCourse), 'avec sa seule date annulée');

	}

	/**
	 * Les compteurs des cartes ne voient que les planifiées à venir, la fiche montre aussi les
	 * annulées à venir, et l'éditeur tout.
	 */
	public function testUpcoming(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		$this->createDate($eCourse, -5);
		$eDateCanceled = $this->createDate($eCourse, 3);
		DateLib::cancel($eDateCanceled);
		$this->createDate($eCourse, 20);
		$this->createDate($eCourse, 10);

		$cCourse = new \Collection([$eCourse, $eCourseEducation]);

		CourseLib::fillCounts($cCourse);

		$this->assertEquals(2, $eCourse['dates'], 'deux planifiées à venir, la passée et l\'annulée exclues');
		$this->assertEquals($this->day(10).' 14:00:00', $eCourse['dateNext'], 'la prochaine, pas la première écrite');
		$this->assertEquals(2, DateLib::countUpcomingByCourse($eCourse), 'le compte seul dit la même chose');
		$this->assertFalse($eCourseEducation->offsetExists('dates'), 'une formation d\'école ne porte pas le compte');

		$eCourseEmpty = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		CourseLib::fillCounts(new \Collection([$eCourseEmpty]));

		$this->assertEquals(0, $eCourseEmpty['dates'], 'zéro sans date');
		$this->assertEquals(NULL, $eCourseEmpty['dateNext'], 'et pas de prochaine');
		$this->assertEquals('', DateUi::getCardWhen($eCourseEmpty), 'pas de jour sous le titre sans prochaine date');

		$cDateUpcoming = DateLib::getUpcomingByCourse($eCourse);

		$this->assertEquals(3, $cDateUpcoming->count(), 'la fiche : annulée comprise, passée exclue');
		$this->assertEquals([$this->day(3), $this->day(10), $this->day(20)], array_map(fn(string $startAt) => substr($startAt, 0, 10), $cDateUpcoming->getColumn('startAt')), 'dans l\'ordre du calendrier');
		$this->assertEquals(4, DateLib::getByCourse($eCourse)->count(), 'l\'éditeur : tout');

		$this->assertEquals(NULL, CourseUi::getCounts($eCourse), 'deux dates à venir et aucune leçon, rien à compter : le pied de la carte dit la prochaine date (retour de Vincent du 03/10)');
		$this->assertEquals('Aucune date à venir', CourseUi::getCounts($eCourseEmpty), 'ni de dates à zéro');
		$this->assertEquals('Aucune leçon', CourseUi::getCounts($eCourseEducation), 'une formation d\'école sans leçon');

		$this->createLessonWritten($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);
		CourseLib::fillCounts($cCourse);

		$this->assertEquals('1 leçon · 10&nbsp;min', CourseUi::getCounts($eCourse), 'les leçons seules, sans le compte des dates');

		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);
		CourseLib::fillCounts(new \Collection([$eCourseOnline]));

		$this->assertFalse($eCourseOnline->offsetExists('dates'), 'une formation de réseau en ligne ne porte pas le compte');

	}

	/**
	 * Les dates partent avec la formation, et leurs séances avec elles.
	 */
	public function testDeleteCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);
		$cDate = new \Collection([
			$this->createDate($eCourse, 10),
			$this->createDate($eCourse, 20),
		]);

		CourseLib::delete($eCourse);

		$this->assertEquals(0, Date::model()->whereCourse($eCourse)->count(), 'plus de date');
		$this->assertEquals(0, DateSequence::model()->whereDate('IN', $cDate)->count(), 'ni de séance');

	}

	private function posted(Course $eCourse, array $input): array {
		return ['course' => (string)$eCourse['id']] + $input;
	}

	/**
	 * Une date planifiée de 14:00 à 16:30, `$offset` jours après aujourd'hui, par un lien ou à
	 * Crest selon le type de la formation.
	 */
	private function createDate(Course $eCourse, int $offset): Date {

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), $this->postSequences([
			[$this->day($offset), '14:00', '16:30', $eCourse->getDefaultSequence()['mode'], 'Salle des fêtes, Crest'],
		]) + [
			'link' => 'https://visio.example.org/salle',
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		return $this->getDate($eDate['id']);

	}

	private function getDate(int $id): Date {

		return Date::model()
			->select(Date::getSelection())
			->whereId($id)
			->get();

	}

	private function publish(Course $eCourse): array {

		$fw = new \FailWatch();

		$eCourseClone = clone $eCourse;
		$eCourseClone->build(['status'], ['status' => Course::PUBLISHED]);

		return array_column($fw->get(), 1);

	}

}
?>
