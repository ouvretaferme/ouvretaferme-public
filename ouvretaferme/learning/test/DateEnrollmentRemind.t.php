<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le rappel d'avant la date (lot L10d, R1) : la fenêtre de deux jours, jamais le jour même, les
 * seules personnes confirmées depuis plus de 24 heures, un seul rappel par ligne même sur deux
 * passages, le rappel remis à vide quand le jour de la date change et gardé quand seule l'heure
 * change, le rappel remis à vide quand une ligne annulée est rouverte, et les trois variantes de
 * l'e-mail.
 *
 * `remind()` lit toutes les dates de la base : sur une copie de la production, d'autres lignes que
 * celles du test peuvent être dans la fenêtre. Le test juge donc ses propres lignes, et un second
 * passage, qui ne trouve plus rien après le premier.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentRemind
 */
class DateEnrollmentRemindTest extends LearningDateFixtureTest {

	/**
	 * Demain et après-demain, pas aujourd'hui ni dans trois jours ; confirmées depuis plus de 24
	 * heures, pas une confirmation de la veille au soir ni une demande.
	 */
	public function testWindow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$lines = [];

		foreach([0, 1, 2, 3] as $offset) {
			$eDate = $this->createDate($eCourse, $offset);
			$lines['J+'.$offset] = $this->age($this->enrollConfirmed($eDate, $this->createPerson()));
		}

		$eDateTomorrow = $this->createDate($eCourse, 1, '23:30:00');
		$lines['demain soir'] = $this->age($this->enrollConfirmed($eDateTomorrow, $this->createPerson()));
		$lines['confirmée à l\'instant'] = $this->enrollConfirmed($eDateTomorrow, $this->createPerson());
		$lines['à confirmer'] = $this->enrollRequested($eDateTomorrow, $this->createPerson());

		$expected = ['J+1', 'J+2', 'demain soir'];

		$remindable = DateEnrollmentLib::getRemindable()->getIds();
		$this->assertEquals($expected, array_keys(array_filter($lines, fn(DateEnrollment $e) => in_array($e['id'], $remindable))), 'les lignes à rappeler');

		$this->assertTrue(DateEnrollmentLib::remind() >= count($expected), 'le premier passage rappelle au moins les trois');

		foreach($lines as $case => $eDateEnrollment) {
			$this->assertEquals(in_array($case, $expected), $this->getLine($eDateEnrollment['id'])['remindedAt'] !== NULL, $case.' : rappel posé ou non');
		}

		$this->assertEquals(0, DateEnrollmentLib::remind(), 'le second passage, la nuit suivante ou le même matin, ne rappelle personne');

	}

	/**
	 * Le rappel annonçait un jour : il repart si le jour change, pas si seule l'heure change. Les
	 * autres lignes de la date suivent, l'écriture étant de masse.
	 */
	public function testMovedDate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 1);
		$eDateEnrollmentA = $this->age($this->enrollConfirmed($eDate, $this->createPerson()));
		$eDateEnrollmentB = $this->age($this->enrollConfirmed($eDate, $this->createPerson()));

		DateEnrollmentLib::remind();

		$this->assertTrue($this->getLine($eDateEnrollmentA['id'])['remindedAt'] !== NULL, 'A rappelée');

		$this->moveDate($eDate, 1, '14:00:00');
		$this->assertTrue($this->getLine($eDateEnrollmentA['id'])['remindedAt'] !== NULL, 'même jour, autre heure : le rappel tient');
		$this->assertEquals(0, DateEnrollmentLib::remind(), 'et ne repart pas');

		$this->moveDate($eDate, 2);
		$this->assertEquals(NULL, $this->getLine($eDateEnrollmentA['id'])['remindedAt'], 'autre jour : le rappel de A est remis à vide');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollmentB['id'])['remindedAt'], 'et celui de B');
		$this->assertTrue(DateEnrollmentLib::remind() >= 2, 'le rappel repart pour le nouveau jour');

	}

	/**
	 * Une ligne rappelée, annulée puis rouverte, par le membre comme par la page publique : la
	 * réouverture rend le rappel dû, et la règle des 24 heures joue sur la confirmation reposée.
	 */
	public function testReopened(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);

		$eUser = $this->createPerson();
		$email = 'zztest.rappel-rouverte@example.org';

		$lines = [
			'membre' => $this->age($this->enrollConfirmed($eDate, $eUser)),
			'page publique' => $this->age($this->enrollPublicConfirmed($eDate, $email))
		];

		DateEnrollmentLib::remind();

		foreach($lines as $case => $eDateEnrollment) {
			$this->assertTrue($this->getLine($eDateEnrollment['id'])['remindedAt'] !== NULL, $case.' : rappelée');
			DateEnrollmentLib::cancel($this->getLine($eDateEnrollment['id']), DateEnrollment::USER);
		}

		$reopened = [
			'membre' => $this->enrollConfirmed($eDate, $eUser),
			'page publique' => $this->enrollPublicConfirmed($eDate, $email)
		];

		foreach($reopened as $case => $eDateEnrollment) {
			$this->assertEquals($lines[$case]['id'], $eDateEnrollment['id'], $case.' : la même ligne rouverte');
			$this->assertEquals(DateEnrollment::CONFIRMED, $eDateEnrollment['status'], $case.' : confirmée de nouveau');
			$this->assertEquals(NULL, $eDateEnrollment['remindedAt'], $case.' : le rappel est remis à vide');
		}

		$remindable = DateEnrollmentLib::getRemindable()->getIds();
		$this->assertEquals([], array_intersect(array_column($reopened, 'id'), $remindable), 'confirmées à l\'instant : pas encore de rappel');

		foreach($reopened as $case => $eDateEnrollment) {
			$this->age($eDateEnrollment);
		}

		$this->assertTrue(DateEnrollmentLib::remind() >= 2, 'le rappel repart');

		foreach($reopened as $case => $eDateEnrollment) {
			$this->assertTrue($this->getLine($eDateEnrollment['id'])['remindedAt'] !== NULL, $case.' : rappelée de nouveau');
		}

	}

	/**
	 * Un membre annule depuis la fiche ; une ligne publique depuis la page de son inscription, avec
	 * le contact du réseau ; sans compte à l'adresse, l'invitation à en créer un.
	 */
	public function testMails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);

		$eDateEnrollmentMember = $this->enrollConfirmed($eDate, $this->createPerson());

		$this->createUser('zztest.rappel-compte@example.org');
		$eDateEnrollmentAccount = $this->enrollPublicConfirmed($eDate, 'zztest.rappel-compte@example.org');
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.rappel-sans-compte@example.org');

		$mail = function(DateEnrollment $e): string {
			[$title, , $html] = DateEnrollmentLib::getMails($this->getLine($e['id']), 'reminded')[0]['content'];
			return $title.$html;
		};

		$member = $mail($eDateEnrollmentMember);
		$this->assertTrue(str_starts_with($member, 'Votre formation du '.mb_strtolower(DateUi::getDays($eDate))), 'le titre dit le jour');
		$this->assertTrue(str_contains($member, 'Votre formation approche'), 'le titre de l\'e-mail');
		$this->assertTrue(str_contains($member, 'Voir la formation'), 'le membre annule depuis la fiche');
		$this->assertFalse(str_contains($member, 'Créer mon compte'), 'sans invitation');
		$this->assertFalse(str_contains($member, 'Pour en savoir plus'), 'ni contact');

		$account = $mail($eDateEnrollmentAccount);
		$this->assertTrue(str_contains($account, 'Voir mon inscription'), 'une ligne publique annule depuis son inscription');
		$this->assertTrue(str_contains($account, 'Pour en savoir plus'), 'avec le contact du réseau');
		$this->assertFalse(str_contains($account, 'Créer mon compte'), 'un compte existe à l\'adresse : pas d\'invitation');

		$public = $mail($eDateEnrollmentPublic);
		$this->assertTrue(str_contains($public, 'Voir mon inscription') and str_contains($public, 'Pour en savoir plus'), 'sans compte : son inscription et le contact');
		$this->assertTrue(str_contains($public, 'Créer mon compte') and str_contains($public, 'zztest.rappel-sans-compte@example.org'), 'et l\'invitation à créer un compte à son adresse');

		// Sur plusieurs jours, `DateUi::getDays()` porte déjà son « du ».
		$eDate = $this->extendDate($eDate, 3);
		$days = mb_strtolower(DateUi::getDays($eDate));

		$this->assertTrue(str_starts_with($days, 'du '), 'une date de plusieurs jours : '.$days);

		$member = $mail($eDateEnrollmentMember);
		$this->assertTrue(str_starts_with($member, 'Votre formation '.$days), 'plusieurs jours : le titre dit « '.$days.' »');
		$this->assertFalse(str_contains($member, 'du du'), 'plusieurs jours : un seul « du »');

	}

	/**
	 * La confirmation reculée de deux jours : le rappel ne suit pas une confirmation de moins de 24
	 * heures, et le décor ne peut pas attendre. Écrite hors du Lib, qui n'a pas de raison de dater une
	 * confirmation dans le passé.
	 */
	private function age(DateEnrollment $eDateEnrollment): DateEnrollment {

		DateEnrollment::model()
			->whereId($eDateEnrollment['id'])
			->update(['confirmedAt' => new \Sql('NOW() - INTERVAL 2 DAY')]);

		return $this->getLine($eDateEnrollment['id']);

	}

}
?>
