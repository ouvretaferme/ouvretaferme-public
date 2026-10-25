<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * Ce qu'un fils de la formation charge d'elle (lot perf-selection-formation). Un Lib qui reçoit la
 * formation y accroche ses fils sans la relire ; un fils chargé seul, par son id, relit la formation
 * avec son groupe, sans la thématique, la sous-thématique ni le niveau, que seules les cartes
 * lisent. Le nombre de requêtes ne dépend donc pas du classement de la formation : il se compare
 * entre une formation classée et une formation qui ne l'est pas, sans plafond.
 *
 * « Mes formations » garde la formation entière : ses cartes lisent la thématique et le niveau.
 *
 * Lancement : dev/test package=learning file=CourseChildSelection
 */
class CourseChildSelectionTest extends LearningReviewFixtureTest {

	public function testAttached(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eUser, $eUserRequest] = $this->seedChildren(TRUE, TRUE);

		foreach($this->getReaders($eCourse, $eUser, $eUserRequest) as $call => $read) {

			$cElement = $read();

			if($this->assertTrue($cElement->notEmpty() and $cElement->first()->notEmpty(), $call.' rend un fils dans le décor') === FALSE) {
				continue;
			}

			foreach($cElement as $eElement) {
				$this->assertTrue($eElement['course'] === $eCourse, $call.' accroche la formation reçue');
			}

		}

		foreach(ChapterLib::getByCourse($eCourse) as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$this->assertTrue($eLesson['course'] === $eCourse, 'les leçons du programme portent la formation reçue');
			}
		}

	}

	/**
	 * La formation non classée se sème la première : la page publique du réseau, qui lit les dates de
	 * toutes ses formations, compte la seconde avec la première.
	 */
	public function testQueries(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourseBare, $eUserBare, $eUserRequestBare] = $this->seedChildren(TRUE, FALSE);
		$queriesBare = $this->measure($eCourseBare, $eUserBare, $eUserRequestBare);

		[$eCourseClassified, $eUserClassified, $eUserRequestClassified] = $this->seedChildren(FALSE, TRUE);

		if($this->assertDecorPresent($eCourseClassified['subtheme']->notEmpty(), 'une thématique avec ses sous-thématiques, learning/configure/theme à jouer') === FALSE) {
			return;
		}

		$queriesClassified = $this->measure($eCourseClassified, $eUserClassified, $eUserRequestClassified);

		foreach($queriesBare as $call => $queries) {
			$this->assertEquals($queries, $queriesClassified[$call], $call.' compte autant de requêtes sur une formation classée que sur une formation qui ne l\'est pas');
		}

	}

	/**
	 * Les cartes de « Mes formations » lisent la thématique, la sous-thématique et le niveau de la
	 * formation à travers l'inscription : un nom manquant y lèverait une clé absente.
	 */
	public function testMine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed(Course::ONLINE);
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$eCourse = $this->classify($this->getCourse($eCourse['id']), TRUE);

		if($this->assertDecorPresent($eCourse['subtheme']->notEmpty(), 'une thématique avec ses sous-thématiques, learning/configure/theme à jouer') === FALSE) {
			return;
		}

		$this->enrollReader($eCourse);

		$this->actAs($this->eUserReader);

		$data = $this->executeView('mes-formations', []);

		$this->actAs($this->eUserAdmin);

		if($data instanceof \stdClass === FALSE) {
			$this->assertFail('/mes-formations ne s\'ouvre pas : '.$data);
			return;
		}

		$eCourseMine = $data->cCourseRunning->find(fn(Course $eCourseRunning) => $eCourseRunning['id'] === $eCourse['id'])->first() ?? new Course();

		$this->assertEquals(
			[$eCourse['theme']['name'], $eCourse['subtheme']['name'], $eCourse['level']['name']],
			$eCourseMine->empty() ? NULL : [$eCourseMine['theme']['name'], $eCourseMine['subtheme']['name'], $eCourseMine['level']['name']],
			'la formation suivie porte sa thématique, sa sous-thématique et son niveau'
		);

		$mine = new CatalogUi()->getMine($data->ccShareSection, $data->ccShareProposed, $data->cCourseRunning, $data->cCourseDone, $data->started, $data->hasCatalog);

		$this->assertTrue($mine !== '', 'les cartes se rendent');

	}

	/**
	 * Une formation au catalogue, sur place, avec chaque fils : un module, une leçon quiz, une date
	 * passée où la personne était confirmée et une date à venir, son inscription, une demande d'une
	 * autre personne, son avis et un encadrant.
	 *
	 * @return array{0: Course, 1: \user\User, 2: \user\User}
	 */
	private function seedChildren(bool $first, bool $classified): array {

		[$eCourse, $lessons] = $this->seedCatalog(Course::ONSITE, $first);

		// Posé sans `build()`, donc sans l'étape `validation.questioned` : aucune lecture mesurée ne lit
		// les questions du quiz, seulement la leçon qui le porte.
		$eLesson = $this->getLesson($lessons[0]['id']);
		$eLesson['validation'] = Lesson::QUIZ;
		LessonLib::update($eLesson, ['validation']);

		$eUser = $this->createPerson();
		$eUserRequest = $this->createPerson();

		$this->followDate($eCourse, $eUser);
		$this->createDate($eCourse, 10);

		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 5), 'l\'avis du décor s\'écrit');

		// Les deux inscriptions sont posées sans `build()` : l'étape `structure.owned` vérifierait que la
		// structure est l'une de celles du compte connecté, et le décor inscrit deux personnes au nom de la
		// structure rattachée pendant qu'il agit en administrateur du réseau. La structure est celle du décor,
		// jamais une valeur postée.
		EnrollmentLib::create(new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => $this->eStructureFarm]));

		$eEnrollmentRequest = new Enrollment(['user' => $eUserRequest, 'course' => $eCourse, 'structure' => $this->eStructureFarm]);
		EnrollmentLib::create($eEnrollmentRequest);

		// Posé sans `build()` : le réglage du réseau qui met une inscription en demande ne vaut que pour
		// une formation en ligne, et le décor veut la demande sur la même formation que les dates.
		$eEnrollmentRequest['status'] = Enrollment::REQUESTED;
		EnrollmentLib::update($eEnrollmentRequest, ['status']);

		$this->createStaff($eCourse);

		return [$this->classify($this->getCourse($eCourse['id']), $classified), $eUser, $eUserRequest];

	}

	/**
	 * Classée, la formation prend la première thématique qui a des sous-thématiques, sa première
	 * sous-thématique et le niveau du décor ; sinon elle perd les trois.
	 */
	private function classify(Course $eCourse, bool $classified): Course {

		if($classified) {

			$eTheme = ThemeLib::getAll()->find(fn(Theme $eTheme) => $eTheme['cSubtheme']->notEmpty())->first() ?? new Theme();

			$eCourse['theme'] = $eTheme;
			$eCourse['subtheme'] = $eTheme->empty() ? new Subtheme() : $eTheme['cSubtheme']->first();
			$eCourse['level'] = $this->eLevel;

		} else {

			$eCourse['theme'] = new Theme();
			$eCourse['subtheme'] = new Subtheme();
			$eCourse['level'] = new Level();

		}

		// Posé sans `build()` : la sous-thématique est lue sur sa thématique, ce que l'étape
		// `subtheme.consistency` vérifierait, et une formation publiée sans classement est l'état que
		// le décor compare et que `status.classified` refuserait.
		CourseLib::update($eCourse, ['theme', 'subtheme', 'level']);

		return $this->getCourse($eCourse['id']);

	}

	/**
	 * Un encadrant du réseau, par le chemin du panneau : la personne, puis sa ligne sur la formation.
	 */
	private function createStaff(Course $eCourse): void {

		$eSupervisor = SupervisorLib::getCreateElement($this->eStructureNetwork);

		$fw = new \FailWatch();

		$eSupervisor->build(SupervisorLib::getPropertiesCreate(), ['name' => 'zzTest Ada Lovelace', 'role' => '', 'description' => '', 'email' => ''], new \PropertyList('create'));
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('encadrant refusé : '.json_encode($this->getFails($fw))));

		SupervisorLib::create($eSupervisor);

		$eStaff = new Staff([
			'course' => $eCourse,
			'cStaff' => StaffLib::getByCourse($eCourse),
			'cSupervisor' => SupervisorLib::getForCourse($eCourse)
		]);

		$eStaff->build(StaffLib::getPropertiesCreate(), ['supervisor' => (string)$eSupervisor['id'], 'role' => 'formatrice', 'contact' => '0'], new \PropertyList('create'));
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('ligne d\'encadrant refusée : '.json_encode($this->getFails($fw))));

		StaffLib::create($eStaff);

	}

	/**
	 * Les lectures qui partent de la formation, chacune rendue en collection pour les comparer d'un bloc :
	 * une lecture d'un seul fils y met l'élément rendu, vide quand il n'est pas trouvé.
	 *
	 * @return array<string, \Closure>
	 */
	private function getReaders(Course $eCourse, \user\User $eUser, \user\User $eUserRequest): array {

		return [
			'ChapterLib::getByCourse' => fn() => ChapterLib::getByCourse($eCourse),
			'LessonLib::getByCourse' => fn() => LessonLib::getByCourse($eCourse),
			'LessonLib::getLastQuiz' => fn() => new \Collection([LessonLib::getLastQuiz($eCourse)]),
			'StaffLib::getByCourse' => fn() => StaffLib::getByCourse($eCourse),
			'DateLib::getByCourse' => fn() => DateLib::getByCourse($eCourse),
			'DateLib::getUpcomingByCourse' => fn() => DateLib::getUpcomingByCourse($eCourse),
			'DateLib::getPlannedByCourse' => fn() => DateLib::getPlannedByCourse($eCourse),
			'DateLib::getConfirmedByUser' => fn() => new \Collection([DateLib::getConfirmedByUser($eUser, $eCourse)]),
			'EnrollmentLib::getByUserAndCourse' => fn() => new \Collection([EnrollmentLib::getByUserAndCourse($eUser, $eCourse)]),
			'EnrollmentLib::getRequestByUserAndCourse' => fn() => new \Collection([EnrollmentLib::getRequestByUserAndCourse($eUserRequest, $eCourse)]),
			'ReviewLib::getByCourseAndUser' => fn() => new \Collection([ReviewLib::getByCourseAndUser($eCourse, $eUser)]),
			'ReviewLib::getForCourse' => fn() => ReviewLib::getForCourse($eCourse, TRUE),
		];

	}

	/**
	 * Les requêtes de chaque lecture : par la formation reçue, puis chaque fils relu seul par son id,
	 * comme une page ou l'API le chargent, puis les dates de la page publique du réseau.
	 *
	 * @return array<string, int>
	 */
	protected function measure(Course $eCourse, \user\User $eUser, \user\User $eUserRequest): array {

		$queries = [];
		$children = [];

		foreach($this->getReaders($eCourse, $eUser, $eUserRequest) as $call => $read) {
			$queries[$call] = $this->countQueries(function() use($read, $call, &$children) {
				$children[$call] = $read();
			});
		}

		$modules = [
			'Chapter' => [new ChapterModel(), Chapter::getSelection(), $children['ChapterLib::getByCourse']->first()],
			'Lesson' => [new LessonModel(), Lesson::getSelection(), $children['LessonLib::getByCourse']->first()],
			'Staff' => [new StaffModel(), Staff::getSelection(), $children['StaffLib::getByCourse']->first()],
			'Date' => [new DateModel(), Date::getSelection(), $children['DateLib::getByCourse']->first()],
			'Enrollment' => [new EnrollmentModel(), Enrollment::getSelection(), $children['EnrollmentLib::getByUserAndCourse']->first()],
			'Review' => [new ReviewModel(), Review::getSelection(), $children['ReviewLib::getByCourseAndUser']->first()],
		];

		foreach($modules as $module => [$m, $selection, $eElement]) {
			$queries[$module.'::getSelection'] = $this->countQueries(fn() => $m->select($selection)->whereId($eElement['id'])->get());
		}

		$queries['DateLib::getPublicByCommunity'] = $this->countQueries(fn() => DateLib::getPublicByCommunity($this->eStructureNetwork));

		return $queries;

	}

}
?>
