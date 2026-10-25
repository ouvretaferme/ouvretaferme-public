<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Ce que les formations mettent en file pour les formateurs : un commentaire de stagiaire, une
 * inscription, une fin de formation, et ce qui n'y met rien (une formation de l'équipe, un doublon
 * d'inscription, une leçon facultative, une leçon déjà suivie). Puis la relecture de l'objet au
 * traitement (`describe()`), qui refuse ce qui n'est pas du groupe.
 *
 * Tout se lit dans la file (`queue\Task`) et dans la base commune, jamais dans une base
 * `structure_<id>` : la transaction de la fixture couvre tout, et le traitement lui-même est
 * éprouvé sur le décor du moteur (`base/notification/test/Notification.t.php`, `testEmitByFqn`).
 *
 * Lancement : dev/test package=learning file=Notification
 */
class NotificationTest extends LearningFixtureTest {

	private \Closure $enabledInitial;

	public function setUp(): void {

		parent::setUp();

		$this->enabledInitial = \notification\NotificationSetting::$enabled;

		\notification\NotificationSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function tearDown(): void {

		\notification\NotificationSetting::$enabled = $this->enabledInitial;

		parent::tearDown();

	}

	/**
	 * Le message d'un stagiaire part, celui de l'équipe non, et rien ne part d'une formation de
	 * l'équipe Ouvretaferme. La tâche porte le groupe et son auteur, que le traitement exclut.
	 */
	public function testComment(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $cLesson] = $this->createSharedCourse(['Construire son assolement' => TRUE]);
		$eLesson = $cLesson->first();

		$this->actAs($this->eUserReader);
		$eCommentReader = $this->write($eLesson, 'Question du stagiaire');

		$cTask = $this->getTasks(NotificationLib::COMMENT, $eCommentReader['id']);

		$this->assertEquals(1, $cTask->count(), 'un message de stagiaire est mis en file');
		$this->assertEquals(self::COMMUNITY_EDUCATION, $cTask->first()['reference'], 'pour le groupe de la formation');
		$this->assertEquals($this->eUserReader['id'], $cTask->first()['payload']['author'], 'avec son auteur');

		$this->actAs($this->eUserAdmin);
		$eCommentAdmin = $this->write($eLesson, 'Réponse de l\'équipe');

		$this->assertEquals(0, $this->getTasks(NotificationLib::COMMENT, $eCommentAdmin['id'])->count(), 'un message de l\'équipe ne part pas');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eLessonTeam = $this->getLesson($this->createLesson($this->createChapter($eCourseTeam, 'Module'), 'Leçon de l\'équipe', 5)['id']);

		$this->actAs($this->eUserReader);
		$eCommentTeam = $this->write($eLessonTeam, 'Question sur une formation de l\'équipe');

		$this->assertEquals(0, $this->getTasks(NotificationLib::COMMENT, $eCommentTeam['id'])->count(), 'une formation de l\'équipe n\'a pas de groupe à prévenir');

	}

	/**
	 * L'inscription part une fois : le second clic, ignoré par l'insertion, ne remet rien en file.
	 */
	public function testEnrollment(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->createSharedCourse(['Construire son assolement' => TRUE]);

		$this->actAs($this->eUserReader);

		$eEnrollment = $this->enroll($eCourse);

		$cTask = $this->getTasks(NotificationLib::ENROLLMENT, $eEnrollment['id']);

		$this->assertEquals(1, $cTask->count(), 'une inscription est mise en file');
		$this->assertEquals(self::COMMUNITY_EDUCATION, $cTask->first()['reference'], 'pour le groupe de la formation');

		$this->forgetTasks($cTask);

		$this->enroll($eCourse);

		$this->assertEquals(0, $this->getTasks(NotificationLib::ENROLLMENT, $eEnrollment['id'])->count(), 'un doublon d\'inscription ne remet rien en file');

		// L'API et une page qui ne porte que l'identifiant inscrivent avec une formation construite par
		// son seul id : le groupe se relit, rien n'est exigé de l'appelant.
		$eEnrollmentById = new Enrollment([
			'user' => $this->eUserAdmin,
			'course' => new Course(['id' => $eCourse['id']]),
			'structure' => new \structure\Structure()
		]);

		EnrollmentLib::create($eEnrollmentById);

		$cTaskById = $this->getTasks(NotificationLib::ENROLLMENT, $eEnrollmentById['id']);

		$this->assertEquals(1, $cTaskById->count(), 'une inscription dont la formation n\'a que son id est mise en file');
		$this->assertEquals(self::COMMUNITY_EDUCATION, $cTaskById->first()['reference'], 'pour le groupe relu depuis la formation');

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eEnrollmentTeam = $this->enroll($eCourseTeam);

		$this->assertEquals(0, $this->getTasks(NotificationLib::ENROLLMENT, $eEnrollmentTeam['id'])->count(), 'une formation de l\'équipe n\'a pas de groupe à prévenir');

	}

	/**
	 * La fin part à la dernière leçon obligatoire suivie, et à elle seule : ni avant, ni pour une
	 * leçon facultative suivie ensuite, ni pour une leçon déjà suivie que le stagiaire recoche.
	 * Une leçon décochée puis recochée la remet en file, la déduplication du moteur faisant le reste.
	 */
	public function testCompleted(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $cLesson] = $this->createSharedCourse([
			'Construire son assolement' => TRUE,
			'Dater ses séries' => TRUE,
			'Pour aller plus loin' => FALSE
		]);

		$lessons = [];

		foreach($cLesson as $eLesson) {
			$lessons[] = $eLesson;
		}

		[$eLessonFirst, $eLessonSecond, $eLessonOptional] = $lessons;

		$this->actAs($this->eUserReader);

		$eEnrollment = $this->enroll($eCourse);

		$this->assertEquals(2, ProgressLib::countMandatoryLeft($eEnrollment), 'deux leçons obligatoires à suivre');

		ProgressLib::done($eEnrollment, $eLessonFirst);

		$this->assertEquals(1, ProgressLib::countMandatoryLeft($eEnrollment), 'une leçon obligatoire suivie');
		$this->assertEquals(0, $this->getTasks(NotificationLib::COMPLETED, $eEnrollment['id'])->count(), 'une leçon obligatoire reste : rien en file');

		// Le quiz et l'API passent la leçon par son seul id : `mandatory` se relit, rien n'est exigé.
		ProgressLib::done($eEnrollment, new Lesson(['id' => $eLessonSecond['id']]));

		$this->assertEquals(0, ProgressLib::countMandatoryLeft($eEnrollment), 'toutes les leçons obligatoires suivies');

		$cTask = $this->getTasks(NotificationLib::COMPLETED, $eEnrollment['id']);

		$this->assertEquals(1, $cTask->count(), 'la dernière leçon obligatoire met la fin en file');
		$this->assertEquals(self::COMMUNITY_EDUCATION, $cTask->first()['reference'], 'pour le groupe de la formation');

		$this->forgetTasks($cTask);

		ProgressLib::done($eEnrollment, $eLessonOptional);

		$this->assertEquals(0, $this->getTasks(NotificationLib::COMPLETED, $eEnrollment['id'])->count(), 'une leçon facultative ne termine rien');

		ProgressLib::done($eEnrollment, $eLessonSecond);

		$this->assertEquals(0, $this->getTasks(NotificationLib::COMPLETED, $eEnrollment['id'])->count(), 'une leçon déjà suivie ne termine rien');

		ProgressLib::undo($eEnrollment, $eLessonSecond);
		ProgressLib::done($eEnrollment, $eLessonSecond);

		$this->assertEquals(1, $this->getTasks(NotificationLib::COMPLETED, $eEnrollment['id'])->count(), 'une leçon décochée puis recochée termine à nouveau');

	}

	/**
	 * La relecture au traitement : le contenu porte ce que l'écran montre, la promotion du stagiaire
	 * comprise, et rien ne se décrit pour un autre groupe que celui de la formation, ni pour une fin
	 * qui n'est plus vraie.
	 */
	public function testDescribe(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		[$eCourse, $cLesson, $eSection] = $this->createSharedCourse(['Construire son assolement' => TRUE]);
		$eLesson = $cLesson->first();

		$this->actAs($this->eUserReader);

		$eComment = $this->write($eLesson, 'Question du stagiaire');
		$eEnrollment = $this->enroll($eCourse);

		$eNotification = NotificationLib::describe(NotificationLib::COMMENT, $eCommunity, $eComment['id']);

		$this->assertEquals([
			'firstName' => $this->eUserReader['firstName'],
			'lastName' => $this->eUserReader['lastName'],
			'lesson' => 'Construire son assolement',
			'course' => 'zzTest formation'
		], $eNotification['content'], 'un commentaire : l\'auteur, la leçon et la formation');
		$this->assertTrue($eNotification['group'] === NULL, 'un commentaire ne se groupe pas');

		$this->assertTrue(NotificationLib::describe(NotificationLib::COMMENT, $this->getFarm(), $eComment['id'])->empty(), 'un commentaire ne se décrit pas pour une autre structure');
		$this->assertTrue(NotificationLib::describe(NotificationLib::COMMENT, $eCommunity, 0)->empty(), 'un commentaire disparu ne se décrit pas');

		$eNotification = NotificationLib::describe(NotificationLib::ENROLLMENT, $eCommunity, $eEnrollment['id']);

		$this->assertEquals([
			'requested' => FALSE,
			'firstName' => $this->eUserReader['firstName'],
			'lastName' => $this->eUserReader['lastName'],
			'course' => 'zzTest formation',
			'courseId' => $eCourse['id'],
			'section' => $eSection['name']
		], $eNotification['content'], 'une inscription : le stagiaire, la formation et sa promotion');
		$this->assertEquals($eCourse['id'], $eNotification['group'], 'les inscriptions se groupent par formation');

		$this->assertTrue(NotificationLib::describe(NotificationLib::ENROLLMENT, $this->getFarm(), $eEnrollment['id'])->empty(), 'une inscription ne se décrit pas pour une autre structure');

		$this->assertTrue(NotificationLib::describe(NotificationLib::COMPLETED, $eCommunity, $eEnrollment['id'])->empty(), 'une fin qui n\'est pas vraie ne se décrit pas');

		ProgressLib::done($eEnrollment, $eLesson);

		$eNotification = NotificationLib::describe(NotificationLib::COMPLETED, $eCommunity, $eEnrollment['id']);

		$this->assertEquals($eSection['name'], $eNotification['content']['section'], 'une fin : la promotion du stagiaire');
		$this->assertTrue($eNotification['group'] === NULL, 'une fin ne se groupe pas');

		$eSectionSecond = $this->createSection($eCommunity, 'zzTest seconde promotion', [$this->eUserReader]);

		$this->actAs($this->eUserAdmin);
		$this->share($eCourse, [$eSection['id'] => [$this->day(0), NULL], $eSectionSecond['id'] => [$this->day(0), NULL]]);

		$this->assertTrue(NotificationLib::describe(NotificationLib::ENROLLMENT, $eCommunity, $eEnrollment['id'])['content']['section'] === NULL, 'un stagiaire de deux promotions n\'en a pas une seule');

	}

	/**
	 * Une formation du groupe, ses leçons (`[nom => obligatoire]`), et une promotion où le stagiaire
	 * est rangé, ouverte depuis aujourd'hui.
	 *
	 * @return array{0: Course, 1: \Collection, 2: \structure\Section}
	 */
	private function createSharedCourse(array $lessons): array {

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module');

		$cLesson = new \Collection();

		foreach($lessons as $name => $mandatory) {

			$eLesson = $this->createLesson($eChapter, $name, 10);

			if($mandatory === FALSE) {
				$eLesson['mandatory'] = FALSE;
				LessonLib::update($eLesson, ['mandatory']);
			}

			$cLesson[$eLesson['id']] = $this->getLesson($eLesson['id']);

		}

		$eSection = $this->createSection($eCommunity, 'zzTest promotion', [$this->eUserReader]);
		$this->share($eCourse, [$eSection['id'] => [$this->day(0), NULL]]);

		return [$this->getCourse($eCourse['id']), $cLesson, $eSection];

	}

	private function write(Lesson $eLesson, string $text): Comment {

		$eComment = new Comment(['lesson' => $eLesson]);

		$fw = new \FailWatch();

		$eComment->build(['text'], ['text' => $text], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('message refusé : '.json_encode($this->getFails($fw))));

		CommentLib::create($eComment);

		return $eComment;

	}

	/** L'inscription du compte connecté, par le Lib, comme la page l'écrit. */
	private function enroll(Course $eCourse): Enrollment {

		$eEnrollment = new Enrollment([
			'user' => \user\ConnectionLib::getOnline(),
			'course' => $eCourse,
			'structure' => new \structure\Structure()
		]);

		EnrollmentLib::create($eEnrollment);

		return EnrollmentLib::getByUserAndCourse($eEnrollment['user'], $eCourse);

	}

	/** Les demandes en file pour ce type et cet objet, avec ce que le traitement en lira. */
	private function getTasks(string $type, int $element): \Collection {

		return \queue\Task::model()
			->select(['id', 'reference', 'element', 'payload'])
			->whereAction(\notification\NotificationLib::getAction($type))
			->whereElement($element)
			->getCollection();

	}

	/**
	 * La file dédoublonne une demande par (structure, action, objet) : pour éprouver qu'un geste ne
	 * remet rien en file, la demande du geste précédent s'efface d'abord.
	 */
	private function forgetTasks(\Collection $cTask): void {

		foreach($cTask as $eTask) {
			\queue\TaskLib::delete($eTask);
		}

	}

}
?>
