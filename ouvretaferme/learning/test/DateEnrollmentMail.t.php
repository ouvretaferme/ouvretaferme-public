<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le jour d'une date dans chaque e-mail d'inscription : `DateUi::getDays()` rend « Du samedi 26 au
 * lundi 28 septembre 2026 » sur plusieurs jours, avec son « du », qu'une phrase en « du {day} » ou
 * « le {day} » doublait (« Votre inscription du du samedi 26 … »). Chaque e-mail est rendu par
 * `DateEnrollmentLib::getMails()`, sans envoi, sur une date d'un jour et une de plusieurs, pour une
 * ligne de membre et une ligne sans compte ; les états que l'e-mail distingue sont posés sur
 * l'élément lu, rien n'est écrit pour eux.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentMail
 */
class DateEnrollmentMailTest extends LearningDateFixtureTest {

	public function testDays(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 4);

		$eDateEnrollmentMember = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.jour-courriel@example.org');

		$shapes = [
			'un jour' => fn() => DateLib::getById($eDate['id']),
			'plusieurs jours' => fn() => $this->extendDate($eDate, 6),
		];

		foreach($shapes as $shape => $getDate) {

			$eDateShape = $getDate();
			$days = mb_strtolower(DateUi::getDays($eDateShape));

			$this->assertEquals($shape === 'plusieurs jours', str_starts_with($days, 'du '), $shape.' : '.$days);

			$mails = [];

			foreach(['membre' => $eDateEnrollmentMember, 'sans compte' => $eDateEnrollmentPublic] as $who => $eDateEnrollment) {

				foreach($this->getVariants($eDateEnrollment) as $variant => [$event, $values]) {

					$e = $this->getLine($eDateEnrollment['id']);

					foreach($values as $property => $value) {
						$e[$property] = $value;
					}

					if($event === 'quizOpened') {
						$e['date'] = $this->prepareDate($eDateShape);
						$e['date']['eLessonQuiz']['questions'] = 2;
						$e['date']['eLessonQuiz']['needed'] = 1;
					}

					foreach(DateEnrollmentLib::getMails($e, $event) as $position => ['content' => [$title, $text, $html]]) {
						$mails[$who.', '.$variant.' #'.$position] = $title.' '.$text.' '.html_entity_decode(strip_tags($html));
					}

				}

			}

			$this->assertTrue(count($mails) >= 20, $shape.' : les e-mails sont rendus ('.count($mails).')');

			$doubled = array_keys(array_filter($mails, fn(string $mail) => preg_match('/\b(du|le) du /u', $mail) === 1));
			$this->assertEquals([], $doubled, $shape.' : « du du » ou « le du » dans '.implode(', ', $doubled));

			$missing = array_keys(array_filter($mails, fn(string $mail) => str_contains($mail, $days) === FALSE));
			$this->assertEquals([], $missing, $shape.' : le jour « '.$days.' » absent de '.implode(', ', $missing));

		}

	}

	/**
	 * Chaque e-mail que la ligne peut recevoir, avec l'état qu'il lit.
	 *
	 * @return array<string, array{0: string, 1: array}>
	 */
	private function getVariants(DateEnrollment $eDateEnrollment): array {

		$confirmedAt = $eDateEnrollment['confirmedAt'];

		$variants = [
			'confirmée' => ['confirmed', []],
			'place donnée' => ['seatGiven', []],
			'rappel' => ['reminded', []],
			'quiz ouvert' => ['quizOpened', []],
			'annulée par le réseau, demande' => ['canceled', ['status' => DateEnrollment::CANCELED, 'canceledBy' => DateEnrollment::COMMUNITY, 'confirmedAt' => NULL]],
			'annulée par le réseau, inscription' => ['canceled', ['status' => DateEnrollment::CANCELED, 'canceledBy' => DateEnrollment::COMMUNITY, 'confirmedAt' => $confirmedAt]],
			'annulée par la personne, demande' => ['canceled', ['status' => DateEnrollment::CANCELED, 'canceledBy' => DateEnrollment::USER, 'confirmedAt' => NULL]],
			'annulée par la personne, inscription' => ['canceled', ['status' => DateEnrollment::CANCELED, 'canceledBy' => DateEnrollment::USER, 'confirmedAt' => $confirmedAt]],
			'date annulée, demande' => ['canceled', ['status' => DateEnrollment::CANCELED, 'canceledBy' => DateEnrollment::DATE, 'confirmedAt' => NULL]],
			'date annulée, inscription' => ['canceled', ['status' => DateEnrollment::CANCELED, 'canceledBy' => DateEnrollment::DATE, 'confirmedAt' => $confirmedAt]],
		];

		if($eDateEnrollment->isPublic()) {
			$variants['reçue, demande'] = ['created', ['status' => DateEnrollment::REQUESTED, 'confirmedAt' => NULL]];
			$variants['reçue, confirmée'] = ['created', ['status' => DateEnrollment::CONFIRMED]];
			$variants['reçue, liste d\'attente'] = ['created', ['status' => DateEnrollment::WAITING, 'confirmedAt' => NULL]];
		} else {
			$variants['créée, confirmée'] = ['created', ['status' => DateEnrollment::CONFIRMED]];
		}

		return $variants;

	}

}
?>
