<?php
namespace learning;

require_once __DIR__.'/LearningTutorialFixture.t.php';

/**
 * Les tutoriels de Ouvretaferme (lot T0-portes) : les formations du réseau de
 * `LearningSetting::$structureTutorial` se lisent sans compte, et leur brouillon reste fermé.
 *
 * Le réseau des tutoriels est celui du décor (`LearningTutorialFixtureTest`). La production se joue
 * par `LearningSetting::$active` à faux, comme dans `ProductionAccess.t.php`.
 *
 * L'écriture par l'API, pour la même structure, est éprouvée dans `TutorialApi.t.php`.
 *
 * Lancement : dev/test package=learning file=Tutorial
 */
class TutorialTest extends LearningTutorialFixtureTest {

	private bool $activeInitial;

	public function setUp(): void {

		parent::setUp();

		$this->activeInitial = LearningSetting::$active;

	}

	public function tearDown(): void {

		LearningSetting::$active = $this->activeInitial;

		parent::tearDown();

	}

	/**
	 * Un tutoriel en brouillon : le droit de lire est donné à tous (`canRead()`), l'état le ferme
	 * (`acceptRead()`) sur chaque chemin, la formation, le module, la leçon, ses blocs et
	 * l'inscription. Publié, la lecture s'ouvre au visiteur, ce qui prouve que seul le statut la
	 * fermait ; l'inscription reste refusée, un tutoriel ne se commence pas (`Enrollment::acceptCourse()`).
	 */
	public function testDraftThenPublished(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module tutoriel');
		$eLesson = $this->createLesson($eChapter, 'zzTest leçon tutoriel', 5);

		LearningSetting::$active = FALSE;

		$this->assertReads($eCourse, $eChapter, $eLesson, FALSE, 'brouillon');

		$this->actAs($this->eUserReader);

		$this->assertDiffer(
			[\RedirectAction::class, NULL],
			$this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => '', 'fundingRequested' => '0']),
			'brouillon : le POST d\'inscription est refusé'
		);
		$this->assertEquals(0, Enrollment::model()->whereCourse($eCourse)->count(), 'brouillon : aucune inscription écrite');

		// Posé hors de `build()`, comme `createCourse()` le pose : la formation n'a pas à être
		// classée pour ce que le test éprouve, la lecture d'un statut.
		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$this->assertReads($eCourse, $eChapter, $eLesson, TRUE, 'publié');

		$this->assertTrue($this->executeView('formation/lecon/'.$eLesson['id'], []) instanceof \stdClass, 'publié : la leçon répond au visiteur');

		$this->actAs($this->eUserReader);

		$this->assertEquals(
			[\FailAction::class, 'learning\\Enrollment::tutorial'],
			$this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => '', 'fundingRequested' => '0']),
			'publié : le POST d\'inscription est refusé, et la page dit pourquoi'
		);
		$this->assertEquals(0, Enrollment::model()->whereCourse($eCourse)->count(), 'publié : aucune inscription écrite');

	}

	/**
	 * Un tutoriel ne se commence pas et n'a pas de fil, quand la formation publiée d'une autre
	 * structure garde l'un et l'autre : c'est la structure des tutoriels qui ferme, pas la
	 * publication ni le compte.
	 */
	public function testNoEnrollmentNoComment(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$active = TRUE;

		$this->actAs($this->eUserAdmin);

		$eCourseOther = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$eLessonOther = $this->createLesson($this->createChapter($eCourseOther, 'zzTest module autre'), 'zzTest leçon autre', 5);

		$eCourseTutorial = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eLessonTutorial = $this->createLesson($this->createChapter($eCourseTutorial, 'zzTest module tutoriel'), 'zzTest leçon tutoriel', 5);

		$this->actAs($this->eUserReader);

		foreach([
			[$eCourseOther, $eLessonOther, TRUE, 'l\'autre formation'],
			[$eCourseTutorial, $eLessonTutorial, FALSE, 'le tutoriel']
		] as [$eCourse, $eLesson, $expected, $case]) {

			$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $this->getCourse($eCourse['id'])]);
			$this->assertEquals($expected, $eEnrollment->acceptCourse(), $case.' : la formation se commence');

			$eComment = new Comment(['lesson' => LessonLib::getById($eLesson['id'])]);
			$this->assertEquals($expected, $eComment->acceptRead(), $case.' : le fil se lit');
			$this->assertEquals($expected, $eComment->acceptWrite(), $case.' : le fil s\'écrit');

		}

		$this->assertEquals(
			[\NotExpectedAction::class, NULL],
			$this->executePage('learning/comment:doCreate', ['lesson' => (string)$eLessonTutorial['id'], 'text' => 'zzTest commentaire']),
			'le POST d\'un commentaire sous le tutoriel est refusé'
		);
		$this->assertEquals(0, Comment::model()->whereLesson($eLessonTutorial)->count(), 'aucun commentaire écrit sous le tutoriel');

	}

	/**
	 * La formation publiée d'une autre structure ne se lit pas sans compte : c'est la structure
	 * des tutoriels qui ouvre, pas la publication. Tout est ouvert (`$active`), pour que le refus
	 * vienne de `acceptTutorial()` et non de `acceptLearning()`. La propriété à NULL, celle du
	 * premier push, n'ouvre rien non plus.
	 */
	public function testOtherStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$active = TRUE;

		$this->actAs($this->eUserAdmin);

		$eCourseOther = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$eCourseTutorial = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$this->actAs(new \user\User());

		$this->assertTrue($eCourseOther->acceptRead(), 'l\'autre formation est publiée et ouverte');
		$this->assertFalse($eCourseOther->acceptTutorial(), 'elle n\'est pas un tutoriel');
		$this->assertFalse($eCourseOther->canRead(), 'le visiteur ne la lit pas');

		$this->assertTrue($eCourseTutorial->canRead(), 'le visiteur lit le tutoriel publié');

		LearningSetting::$structureTutorial = NULL;

		$this->assertFalse($eCourseTutorial->acceptTutorial(), 'sans réseau des tutoriels, aucune formation n\'en est un');
		$this->assertFalse($eCourseTutorial->canRead(), 'et le visiteur ne lit plus la formation du réseau');

	}

	/**
	 * Ce que voit le visiteur sans compte, relu en base à chaque fois : le droit de lire (`canRead()`)
	 * est donné au tutoriel quel que soit son statut, la lecture (`assertRead()`) suit le statut.
	 */
	private function assertReads(Course $eCourse, Chapter $eChapter, Lesson $eLesson, bool $expected, string $case): void {

		$this->actAs(new \user\User());

		$eCourse = $this->getCourse($eCourse['id']);
		$eChapter = $this->getChapter($eChapter['id']);
		$eLesson = LessonLib::getById($eLesson['id']);

		$this->assertTrue($eCourse->canRead(), $case.' : le droit de lire la formation est donné');
		$this->assertTrue($eLesson->canRead(), $case.' : celui de lire la leçon sans compte aussi');

		$this->assertEquals($expected, $eCourse->assertRead(), $case.' : la formation');
		$this->assertEquals($expected, $eChapter->assertRead(), $case.' : le module');
		$this->assertEquals($expected, $eLesson->assertRead(), $case.' : la leçon');
		$eBlock = new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'eOwner' => $eLesson,
		]);

		$this->assertEquals($expected, $eBlock->canRead(), $case.' : les blocs de la leçon');

	}

}
?>
