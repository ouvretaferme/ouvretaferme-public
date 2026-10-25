<?php
namespace learning;

/**
 * Le quiz d'une leçon selon le lecteur (lot L12a) : des cases seulement pour l'inscrit, qui a le
 * bouton qui les envoie. Le non-inscrit qui peut commencer la formation lit les questions sans
 * cases, comme le visiteur (2026-09-25).
 *
 * La leçon, ses questions et la formation sont construites en mémoire : aucune lecture ni écriture
 * en base.
 */
class QuizReaderTest extends \Test {

	/**
	 * Un seul contrat, lu sur les cinq lecteurs d'un quiz jamais tenté : un champ à cocher n'existe
	 * que dans le formulaire qui l'envoie, que seul l'inscrit a. Les autres lisent les questions,
	 * hors de tout formulaire.
	 */
	public function testNoFieldWithoutButton(): void {

		$cases = [
			'visiteur' => [FALSE, TRUE, FALSE, FALSE, 'read'],
			'inscrit' => [TRUE, TRUE, TRUE, FALSE, 'answer'],
			'non inscrit qui peut commencer' => [TRUE, TRUE, FALSE, TRUE, 'start'],
			'non inscrit refusé (formation datée sans inscription confirmée, promotion close)' => [TRUE, TRUE, FALSE, FALSE, 'read'],
			'inscrit dont l\'échéance est passée' => [TRUE, FALSE, TRUE, FALSE, 'read'],
		];

		foreach($cases as $case => [$isLogged, $canProgress, $enrolled, $acceptEnrollment, $expected]) {

			$eLesson = $this->getLesson(canProgress: $canProgress, acceptEnrollment: $acceptEnrollment);
			$eEnrollment = $enrolled ? new Enrollment(['id' => 1]) : new Enrollment();

			$quiz = new QuizUi()->get($eLesson, $eEnrollment, new \Collection(), new \Collection(), $isLogged);

			$fields = substr_count($quiz, 'type="radio"') + substr_count($quiz, 'type="checkbox"');
			$form = str_contains($quiz, 'response:doAnswer');

			switch($expected) {

				case 'answer' :
					$this->assertTrue($fields > 0 and $form, $case.' : les cases et leur formulaire');
					break;

				case 'start' :
					$this->assertEquals(0, $fields, $case.' : aucune case, « Commencer » ne garderait pas les réponses');
					$this->assertFalse($form, $case.' : aucun formulaire');
					$this->assertReading($quiz, $case);
					$this->assertTrue(str_contains($quiz, 'enrollment:doCreate'), $case.' : « Commencer la formation »');
					$this->assertTrue(str_contains($quiz, 'post-origin="'.$eLesson['id'].'"'), $case.' : « Commencer » emporte la leçon lue');
					break;

				case 'read' :
					$this->assertEquals(0, $fields, $case.' : aucune case');
					$this->assertFalse($form, $case.' : aucun formulaire');
					$this->assertReading($quiz, $case);
					break;

			}

		}

	}

	/** L'énoncé et ses choix restent lisibles sans les cases. */
	private function assertReading(string $quiz, string $case): void {

		$this->assertTrue(str_contains($quiz, 'Question du test') and str_contains($quiz, 'Choix juste'), $case.' : l\'énoncé et les choix');

	}

	/**
	 * « Commencer la formation » ramène sur le quiz par son ancre : l'adresse et la section qu'elle
	 * vise doivent porter le même nom.
	 */
	public function testUrlTargetsQuiz(): void {

		$eLesson = $this->getLesson(canProgress: TRUE, acceptEnrollment: TRUE);

		$url = QuizUi::url($eLesson);
		$anchor = substr($url, strpos($url, '#') + 1);

		$this->assertTrue(str_starts_with($url, LessonUi::url($eLesson).'#'), 'l\'adresse est celle de la leçon, ancrée');
		$this->assertTrue(str_contains(new QuizUi()->get($eLesson, new Enrollment(), new \Collection(), new \Collection(), TRUE), '<div id="'.$anchor.'">'), 'la section du quiz porte l\'ancre');

	}

	private function getLesson(bool $canProgress, bool $acceptEnrollment): Lesson {

		$eCourse = new Course([
			'id' => 1,
			'canProgress' => $canProgress,
			'acceptEnrollment' => $acceptEnrollment,
			'eEnrollmentRequest' => new Enrollment(),
			'onlineConfirm' => NULL,
			'eShare' => new Share(['dueAt' => '2026-09-30']),
		]);

		$eQuestion = new Question([
			'id' => 1,
			'number' => 1,
			'statement' => 'Question du test',
			'type' => Question::SINGLE,
			'image' => NULL,
			'hasChoiceImage' => FALSE,
			'explanation' => NULL,
			'cChoice' => new \Collection([
				1 => new Choice(['id' => 1, 'label' => 'Choix juste', 'image' => NULL]),
				2 => new Choice(['id' => 2, 'label' => 'Choix faux', 'image' => NULL]),
			]),
			'picked' => [],
			'isRight' => FALSE,
		]);

		return new Lesson([
			'id' => 3,
			'course' => $eCourse,
			'validation' => Lesson::QUIZ,
			// Le quiz ouvert : les états fermés d'une formation datée sont ceux de `DateQuiz.t.php`.
			'quiz' => ResponseLib::QUIZ_OPEN,
			'cQuestion' => new \Collection([1 => $eQuestion]),
			'attempted' => FALSE,
			'right' => 0,
			'needed' => 1,
			'attempts' => 0,
		]);

	}

}
?>
