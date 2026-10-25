<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * Le programme d'une formation en présentiel face à la publication (décision du 05/10) :
 * elle se publie sans leçon, et sa dernière leçon se supprime sous une formation publiée tant
 * qu'aucun inscrit ne l'a suivie en entier. Le pendant en ligne, qui exige une leçon dans les deux
 * sens, est éprouvé dans `Classified.t.php`.
 *
 * Le décor est le réseau de `LearningNetworkFixture.t.php`, seul à écrire une formation à dates.
 * Aucun partage n'est posé : la publication n'annonce rien à personne.
 */
class PublishProgramTest extends LearningNetworkFixtureTest {

	public function testPublishWithoutLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->setFree($this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE));

		$this->assertEquals([], $this->publish($eCourse), 'publier une formation en présentiel sans module');

		$this->createChapter($eCourse, 'zzTest point abordé');

		$this->assertEquals([], $this->publish($eCourse), 'publier une formation en présentiel dont le seul module est vide');
		$this->assertFalse(CourseLib::getMissingForPublication($eCourse, ChapterLib::getByCourse($eCourse))[2], 'le panneau ne demande pas de leçon');

		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONLINE);

		$this->assertEquals(['Course::status.program'], $this->publish($eCourseOnline), 'la même formation du réseau, en ligne, est refusée');

		$this->assertTrue(CourseLib::getMissingForPublication($eCourseOnline, ChapterLib::getByCourse($eCourseOnline))[2], 'le panneau la nomme comme un manque');

	}

	/**
	 * Une formation en présentiel publiée sans leçon ne devient pas en ligne : ce serait publier en
	 * ligne sans leçon par le type plutôt que par le statut (retour du relecteur du 05/10). En
	 * brouillon, ou avec une leçon, le type change librement.
	 */
	public function testTypeOnlineWithoutLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);

		$this->assertEquals(['Course::type.program'], $this->changeType($eCourse), 'publiée sans leçon, refusée en ligne');

		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);

		$this->assertEquals([], $this->changeType($eCourseDraft), 'en brouillon, libre');

		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$this->assertEquals([], $this->changeType($this->getCourse($eCourse['id'])), 'avec une leçon, libre');

	}

	/** Les noms des refus levés par le passage en ligne, sans l'écrire. */
	private function changeType(Course $eCourse): array {

		$fw = new \FailWatch();

		$eCourseClone = clone $eCourse;
		$eCourseClone->build(['type'], ['type' => Course::ONLINE]);

		return array_column($fw->get(), 1);

	}

	/**
	 * Sous une formation en présentiel publiée, la dernière leçon et son module se proposent et se
	 * suppriment, sauf si un inscrit a suivi toute la formation (décision du 04/10) : la
	 * roue ne porte alors plus la suppression, et le Lib la refuse en le nommant.
	 */
	public function testDeleteLastLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module unique');
		$eLesson = $this->createLesson($eChapter, 'zzTest leçon unique', 15);

		[$eChapterProgram, $eLessonProgram] = $this->getProgram($eCourse, $eChapter, $eLesson);

		$this->assertTrue($eLessonProgram->assertDelete(), 'l\'écran propose la dernière leçon');
		$this->assertTrue($eChapterProgram->assertDelete(), 'et le module qui la porte');

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLesson);

		[$eChapterProgram, $eLessonProgram] = $this->getProgram($eCourse, $eChapter, $eLesson);

		$this->assertFalse($eLessonProgram->assertDelete(), 'un inscrit a tout suivi : la dernière leçon ne se propose plus');

		$this->assertTrue($eLessonProgram['holdsCompleted'] and $eChapterProgram['holdsCompleted'], 'la leçon et son module se tiennent par l\'inscrit qui a tout suivi');
		$this->assertFalse(str_contains(new LessonUi()->getActions($eLessonProgram), '/learning/lesson:doDelete'), 'la roue de la leçon ne porte plus la suppression');
		$this->assertFalse(str_contains(new ChapterUi()->getActions($eChapterProgram), '/learning/chapter:doDelete'), 'celle du module non plus');

		$this->assertEquals(['Lesson::deleteCompleted'], $this->deleteLesson($eLessonProgram), 'le Lib refuse la dernière leçon');
		$this->assertEquals(['Chapter::deleteCompleted'], $this->deleteChapter($eChapterProgram), 'et le module qui la porte');

		ProgressLib::undo($eEnrollment, $eLesson);

		[$eChapterProgram, $eLessonProgram] = $this->getProgram($eCourse, $eChapter, $eLesson);

		$this->assertEquals([], $this->deleteLesson($eLessonProgram), 'sans inscrit qui a tout suivi, la dernière leçon se supprime');
		$this->assertEquals([0, Course::PUBLISHED], [
			Lesson::model()->whereCourse($eCourse)->count(),
			$this->getCourse($eCourse['id'])['status']
		], 'la formation reste publiée, sans leçon');

	}

	/** Le module et la leçon tels que l'écran du programme les tient. */
	private function getProgram(Course $eCourse, Chapter $eChapter, Lesson $eLesson): array {

		$eCourseProgram = $this->getCourse($eCourse['id']);

		$cChapter = ChapterLib::getByCourse($eCourseProgram);
		ChapterLib::fillHolds($eCourseProgram, $cChapter);

		$eChapterProgram = $cChapter[$eChapter['id']];
		$eLessonProgram = $eChapterProgram['cLesson']->find(fn(Lesson $e) => $e['id'] === $eLesson['id'])->first();

		return [$eChapterProgram, $eLessonProgram];

	}

	/** Les noms des refus levés par la publication, sans l'écrire. */
	private function publish(Course $eCourse): array {

		$fw = new \FailWatch();

		$eCourseClone = clone $eCourse;
		$eCourseClone->build(['status'], ['status' => Course::PUBLISHED]);

		return array_column($fw->get(), 1);

	}

	private function deleteLesson(Lesson $eLesson): array {

		$fw = new \FailWatch();

		LessonLib::delete($eLesson);

		return array_column($fw->get(), 1);

	}

	private function deleteChapter(Chapter $eChapter): array {

		$fw = new \FailWatch();

		ChapterLib::delete($eChapter);

		return array_column($fw->get(), 1);

	}

}
?>
