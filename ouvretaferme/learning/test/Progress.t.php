<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Une ligne par couple (compte, leçon) : cocher deux fois, décocher, et ce que le catalogue en
 * compte.
 */
class ProgressTest extends LearningFixtureTest {

	public function testDoneUndo(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du test');
		$eLesson = $this->createLessonWritten($eChapter, 'Première leçon', 10);
		$eLessonSecond = $this->createLessonWritten($eChapter, 'Seconde leçon', 25);

		$this->actAs($this->eUserReader);

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);

		ProgressLib::done($eEnrollment, $eLesson);
		ProgressLib::done($eEnrollment, $eLesson);

		$this->assertEquals(1, Progress::model()->whereUser($this->eUserReader)->whereLesson($eLesson)->count(), 'cochée deux fois, une ligne');

		ProgressLib::done($eEnrollment, $eLessonSecond);

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);
		ProgressLib::fillDone($this->eUserReader, $cCourse);

		$this->assertEquals([2, 35], [$eCourse['done'], $eCourse['doneMinutes']], 'deux leçons suivies, 35 minutes');

		$eCourse['lessons'] = 2;
		$this->assertTrue($eCourse->isDone(), 'deux leçons sur deux, suivie en entier');

		$cChapter = ChapterLib::getByCourse($eCourse);
		ProgressLib::fillChapterDone($cChapter, ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse));
		$this->assertTrue($cChapter[$eChapter['id']]['isDone'], 'module suivi en entier');

		ProgressLib::undo($eEnrollment, $eLessonSecond);

		$eProgress = Progress::model()
			->select(['status', 'doneAt'])
			->whereUser($this->eUserReader)
			->whereLesson($eLessonSecond)
			->get();

		$this->assertEquals([Progress::PENDING, NULL], [$eProgress['status'], $eProgress['doneAt']], 'décochée, la ligne reste en attente');

		ProgressLib::fillDone($this->eUserReader, $cCourse);
		$this->assertEquals([1, 10], [$eCourse['done'], $eCourse['doneMinutes']], 'une leçon décochée ne compte plus');
		$this->assertFalse($eCourse->isDone(), 'une leçon sur deux');

		ProgressLib::fillChapterDone($cChapter, ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse));
		$this->assertFalse($cChapter[$eChapter['id']]['isDone'], 'module entamé');

		$this->assertEquals([$eLesson['id']], array_keys(ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse)->getArrayCopy()), 'les leçons suivies, indexées par leçon');

		ProgressLib::fillDone(new \user\User(), $cCourse);
		$this->assertEquals([0, 0], [$eCourse['done'], $eCourse['doneMinutes']], 'un visiteur n\'a rien suivi');

	}

	/**
	 * Sous une leçon, la rangée d'action coche la leçon, puis porte son état à la place du bouton :
	 * « Suivie le … », dont le menu ouvre le retrait. La suite est la paire du bas de page
	 * (`LessonUi::getPager()`), pas la rangée.
	 */
	public function testFollowRanks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du test');
		$eLesson = $this->createLessonWritten($eChapter, 'Première leçon', 10);
		$this->createLessonWritten($eChapter, 'Seconde leçon : suite', 25);

		$this->actAs($this->eUserReader);

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);

		$render = function() use($eCourse, $eEnrollment, $eLesson): string {

			$eLessonRead = $this->getLesson($eLesson['id']);

			// Ce que la page de la leçon pose : une formation de l'équipe se coche toujours.
			$eLessonRead['course']['canProgress'] = TRUE;
			$eLessonRead['course']['acceptEnrollment'] = TRUE;

			$cProgress = ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse);

			return new ProgressUi()->getFollow($eLessonRead, $eEnrollment, $cProgress, new \Collection(), TRUE);

		};

		$follow = $render();

		$this->assertTrue(str_contains($follow, 'lesson:doDone'), 'à suivre, la leçon se coche');
		$this->assertFalse(str_contains($follow, 'lesson:doUndo'), 'à suivre, rien à retirer');

		ProgressLib::done($eEnrollment, $eLesson);

		$follow = $render();

		$this->assertFalse(str_contains($follow, 'lesson:doDone'), 'suivie, la leçon ne se coche plus');
		$this->assertTrue(str_contains($follow, 'dropdown-toggle') and str_contains($follow, 'data-dropdown='), 'suivie, la marque ouvre un menu');
		$this->assertTrue(str_contains($follow, '</a><div class="dropdown-list">'), 'suivie, la liste suit la marque, la seule fratrie que `getListFromButton()` sait résoudre');
		// La chaîne dit aussi que le menu s'ouvre sur le retrait, donc sans titre : c'est la forme tranchée le 20/09.
		$this->assertTrue(str_contains($follow, 'data-ajax="/learning/lesson:doUndo" post-id="'.$eLesson['id'].'"'), 'suivie, le retrait à la place du bouton');

	}

	/**
	 * Supprimer une leçon emporte ce que les lecteurs en avaient suivi.
	 */
	public function testDeleteLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'Module du test');
		$eLesson = $this->createLessonWritten($eChapter, 'Leçon du test', 10);

		// La seconde leçon n'est pas un décor : sans elle, la première est la dernière d'une
		// formation publiée et sa suppression est refusée. Ce contrôle-ci porte sur la progression.
		$this->createLessonWritten($eChapter, 'Leçon qui reste', 10);

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);
		ProgressLib::done($eEnrollment, $eLesson);

		$this->assertEquals(1, Progress::model()->whereLesson($eLesson)->count(), 'la leçon est suivie');

		LessonLib::delete($eLesson);

		$this->assertEquals(0, Progress::model()->whereLesson($eLesson)->count(), 'sa suppression emporte le suivi');

	}

}
?>
