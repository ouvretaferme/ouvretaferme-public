<?php
namespace learning;

/**
 * La colonne `written` d'une leçon suit son contenu à chaque écriture (`LessonLib::synchronizeWritten()`),
 * et seules les leçons écrites portent leur durée dans les totaux : module, compteurs de la liste,
 * avancement du lecteur et relevé de l'attestation. Le nombre de leçons, lui, les compte toutes.
 */
class LessonWrittenTest extends LearningFixtureTest {

	public function testSynchronize(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');

		$eLesson = $this->createLesson($eChapter, 'zzTest leçon', 10);
		$read = fn() => $this->getLesson($eLesson['id'])['written'];

		$this->assertFalse($read(), 'une leçon neuve sans contenu naît vide');

		$eBlock = new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $eLesson['id'],
			'type' => \block\BlockTypeLib::TEXT,
			'variant' => NULL,
			'title' => NULL,
			'content' => '<p>Un texte.</p>',
		]);

		// Hors de `build()` : le texte est posé tel que le formulaire l'enverrait, seule la colonne de la
		// leçon est éprouvée ici.
		\block\BlockLib::create($eBlock);

		$this->assertTrue($read(), 'un bloc posé l\'écrit');

		$eBlock['content'] = NULL;
		\block\BlockLib::update($eBlock, ['content']);

		$this->assertFalse($read(), 'un bloc vidé la rend vide');

		$eBlock['content'] = '<p>Un texte.</p>';
		\block\BlockLib::update($eBlock, ['content']);

		$this->assertTrue($read(), 'rempli de nouveau, elle est écrite');

		\block\BlockLib::delete($eBlock);

		$this->assertFalse($read(), 'un bloc retiré la rend vide');

		$eLessonQuiz = $this->getLesson($eLesson['id']);
		$eLessonQuiz['validation'] = Lesson::QUIZ;
		LessonLib::update($eLessonQuiz, ['validation']);

		$this->assertTrue($read(), 'un quiz suffit à l\'écrire, il est à faire');

		$eLessonQuiz['validation'] = Lesson::DECLARED;
		LessonLib::update($eLessonQuiz, ['validation']);

		$this->assertFalse($read(), 'revenue à la déclaration sans bloc, elle est vide');

		$eLessonCreated = new Lesson(['course' => $eCourse, 'chapter' => $eChapter]);

		$fw = new \FailWatch();
		$eLessonCreated->build(['name', 'minutes', 'validation', 'quizThreshold'], ['name' => 'zzTest leçon à quiz', 'minutes' => 5, 'validation' => Lesson::QUIZ, 'quizThreshold' => 80], new \PropertyList('create'));
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception(json_encode($fw->get())));

		LessonLib::create($eLessonCreated);

		$this->assertTrue($this->getLesson($eLessonCreated['id'])['written'], 'créée à quiz, elle naît écrite');

	}

	public function testFormat(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');

		$eLessonEmpty = $this->createLesson($eChapter, 'zzTest leçon vide', 10);
		$eLessonSteps = $this->createLessonWritten($eChapter, 'zzTest leçon à étapes', 10);

		$cLesson = new \Collection([
			$eLessonEmpty['id'] => $this->getLesson($eLessonEmpty['id']),
			$eLessonSteps['id'] => $this->getLesson($eLessonSteps['id']),
		]);

		LessonLib::fillFormat($cLesson);

		$this->assertEquals([Lesson::EMPTY, 0], [$cLesson[$eLessonEmpty['id']]['format'], $cLesson[$eLessonEmpty['id']]['steps']], 'vide, sans étape');
		$this->assertEquals([Lesson::STEPS, 1], [$cLesson[$eLessonSteps['id']]['format'], $cLesson[$eLessonSteps['id']]['steps']], 'des étapes, comptées pour l\'éditeur');

	}

	public function testDurations(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');

		$eLessonWritten = $this->createLessonWritten($eChapter, 'zzTest leçon écrite', 10);
		$eLessonEmpty = $this->createLesson($eChapter, 'zzTest leçon vide', 25);

		$cChapter = ChapterLib::getByCourse($eCourse);

		$this->assertEquals(10, $cChapter[$eChapter['id']]['minutes'], 'le module ne porte que la durée de la leçon écrite');

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);
		CourseLib::fillCounts($cCourse);

		$this->assertEquals([2, 10], [$eCourse['lessons'], $eCourse['minutes']], 'la liste compte les deux leçons et la durée de l\'écrite');

		$this->actAs($this->eUserReader);

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);

		ProgressLib::done($eEnrollment, $eLessonWritten);
		ProgressLib::done($eEnrollment, $eLessonEmpty);

		ProgressLib::fillDone($this->eUserReader, $cCourse);

		$this->assertEquals([2, 10], [$eCourse['done'], $eCourse['doneMinutes']], 'l\'avancement compte les deux leçons suivies, et la durée de l\'écrite');

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);

		$this->assertEquals([2, 10, 10], [$eUser['validated'], $eUser['minutesValidated'], $eUser['minutesAll']], 'le relevé de l\'attestation : deux leçons validées, la durée de l\'écrite seule');

	}

}
?>
