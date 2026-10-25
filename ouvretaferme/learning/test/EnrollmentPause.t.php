<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La pause des inscriptions d'une formation en ligne (lot T13) : le réseau la règle par
 * l'interrupteur de l'éditeur, et pendant qu'elle dure aucune inscription neuve ne s'écrit, ni par
 * le bouton de la fiche, ni par un POST forgé, ni par l'écriture d'un élément chargé avant la pause.
 * Les inscriptions déjà reçues restent, et la reprise rouvre le même chemin.
 *
 * Chaque chemin est joué ouvert d'abord, où il aboutit, ce qui prouve que le test sait trouver une
 * inscription, puis en pause, où il est refusé sans écrire de ligne.
 *
 * Lancement : dev/test package=learning file=EnrollmentPause
 */
class EnrollmentPauseTest extends LearningNetworkFixtureTest {

	public function testEnroll(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedOnline();

		$this->assertEquals([\ViewAction::class, NULL], $this->pause($eCourse, TRUE), 'le réseau met les inscriptions en pause');
		$this->assertTrue($this->getCourse($eCourse['id'])['enrollmentPaused'], 'la pause est écrite');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\FailAction::class, 'learning\Enrollment::paused'], $this->executePage('learning/enrollment:create', ['course' => (string)$eCourse['id']], 'GET'), 'en pause, le panneau refuse avec le message de la pause');
		$this->assertEquals([\FailAction::class, 'learning\Enrollment::paused'], $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => '']), 'en pause, le POST forgé refuse');

		$this->assertTrue(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse)->empty(), 'en pause, aucune inscription écrite');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\ViewAction::class, NULL], $this->pause($eCourse, FALSE), 'le réseau rouvre les inscriptions');

		$this->actAs($this->eUserReader);
		$this->assertEquals(\RedirectAction::class, $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => ''])[0], 'rouverte, l\'inscription s\'écrit');
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse)->notEmpty(), 'rouverte, la ligne est écrite');

		// Inscrit avant la pause, le compte garde sa place et lit toujours.
		$this->pause($eCourse, TRUE);

		$this->actAs($this->eUserReader);
		$this->assertTrue($this->getCourse($eCourse['id'])->canReadLessons(), 'en pause, l\'inscrit lit toujours les leçons');
		$this->actAs($this->eUserAdmin);

	}

	/**
	 * L'élément chargé avant la pause, écrit après : `EnrollmentLib::create()` relit la formation sous
	 * verrou et refuse. C'est l'onglet resté ouvert pendant que le réseau met en pause.
	 */
	public function testLock(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedOnline();

		$eEnrollment = new Enrollment([
			'user' => $this->eUserReader,
			'course' => $this->getCourse($eCourse['id']),
			'structure' => new \structure\Structure(),
		]);

		$this->assertTrue($eEnrollment->acceptCreate(), 'chargé ouvert, l\'élément s\'accepte');

		$this->pause($eCourse, TRUE);

		$fw = new \FailWatch();
		EnrollmentLib::create($eEnrollment);
		$this->assertEquals([['Enrollment::paused', NULL]], $this->getFails($fw), 'en pause, create() refuse sous le verrou');

		$this->assertTrue(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse)->empty(), 'aucune inscription écrite');

	}

	/**
	 * L'interrupteur ne se règle que par qui écrit la formation, et tant qu'elle prend des
	 * inscriptions : publiée. Ailleurs il est caché, et le POST forgé refuse sans rien écrire.
	 */
	public function testSwitch(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedOnline();

		$this->assertTrue($this->getEditionCourse($eCourse)->assertUpdateEnrollmentPaused(), 'publiée, l\'interrupteur se montre au réseau');

		$this->actAs($this->eUserReader);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->pause($eCourse, TRUE), 'qui n\'écrit pas la formation ne la met pas en pause');
		$this->actAs($this->eUserAdmin);

		$this->assertFalse($this->getCourse($eCourse['id'])['enrollmentPaused'], 'rien n\'est écrit');

		// Repassée en brouillon sans `build()`, qui ne contrôle de `status` que sa valeur : le décor
		// veut l'état qu'aucun interrupteur ne règle, sans le chemin de dépublication qui l'entoure.
		$eCourseDraft = $this->getCourse($eCourse['id']);
		$eCourseDraft['status'] = Course::DRAFT;
		CourseLib::update($eCourseDraft, ['status']);

		$this->assertFalse($this->getEditionCourse($eCourse)->assertUpdateEnrollmentPaused(), 'en brouillon, l\'interrupteur est caché');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->pause($eCourse, TRUE), 'en brouillon, le POST forgé refuse');
		$this->assertFalse($this->getCourse($eCourse['id'])['enrollmentPaused'], 'rien n\'est écrit');

	}

	private function seedOnline(): Course {

		$eCourse = $this->seed();

		$this->setOnlineConfirm(\structure\Community::IMMEDIATE);

		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		return $this->getCourse($eCourse['id']);

	}

	/**
	 * La formation telle que l'éditeur la prépare pour son en-tête.
	 */
	private function getEditionCourse(Course $eCourse): Course {

		$eCourse = $this->getCourse($eCourse['id']);
		CourseLib::fillHasDateUpcoming($eCourse);

		return $eCourse;

	}

	private function pause(Course $eCourse, bool $paused): array {

		return $this->executePage('learning/course:doUpdateEnrollmentPaused', [
			'id' => (string)$eCourse['id'],
			'enrollmentPaused' => $paused ? '1' : '0',
		]);

	}

}
?>
