<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Une place dont le compte a été fermé (`DropLib::closeNow()`, qui vide `user.email`) : elle n'a plus
 * d'adresse. `getEmail()` rend `NULL`, sans relire la saisie de la ligne, et aucun e-mail ne part
 * pour elle ; les gestes qui parcourent plusieurs places (annulation d'une date, ouverture du quiz,
 * rappel de nuit) vont au bout pour les autres, et ne comptent que les e-mails partis. L'export du
 * réseau la garde, avec une adresse vide.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentClosedAccount
 */
class DateEnrollmentClosedAccountTest extends LearningDateFixtureTest {

	public function testEmail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 2);

		$eDateEnrollment = $this->enrollClosed($eDate);

		$this->assertTrue($eDateEnrollment['user']->notEmpty(), 'décor : la place garde son compte');
		$this->assertEquals(NULL, $eDateEnrollment['email'], 'décor : une place de membre ne porte aucune saisie');
		$this->assertEquals(NULL, $eDateEnrollment->getEmail(), 'un compte fermé n\'a plus d\'adresse');
		$this->assertEquals('zzTest Stagiaire', $eDateEnrollment->getName(), 'le nom survit à la fermeture');

	}

	/**
	 * Tous les e-mails que peut recevoir la personne d'une place à compte : aucun ne part.
	 */
	public function testMails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 2);

		$eDateEnrollment = $this->enrollClosed($eDate);
		$eDateEnrollment['date'] = $this->prepareDate($eDate);

		// Ce que `DateLib::openQuiz()` pose sur la leçon avant l'e-mail d'ouverture.
		$eDateEnrollment['date']['eLessonQuiz']['questions'] = 2;
		$eDateEnrollment['date']['eLessonQuiz']['needed'] = 1;

		foreach(['confirmed', 'seatGiven', 'linkMailed', 'reminded', 'quizOpened', 'reviewInvited'] as $event) {
			$this->assertEquals([], DateEnrollmentLib::getMails($eDateEnrollment, $event), $event.' : aucun e-mail');
		}

		foreach([DateEnrollment::COMMUNITY, DateEnrollment::DATE] as $canceledBy) {
			$eDateEnrollment['canceledBy'] = $canceledBy;
			$this->assertEquals([], DateEnrollmentLib::getMails($eDateEnrollment, 'canceled'), 'annulée par '.$canceledBy.' : aucun e-mail');
		}

	}

	/**
	 * L'annulation d'une date annule aussi la place du compte fermé, et prévient l'autre personne.
	 */
	public function testCancelDate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 2);

		$eDateEnrollmentClosed = $this->enrollClosed($eDate);
		$eUserActive = $this->createPerson();
		$eDateEnrollmentActive = $this->enrollConfirmed($eDate, $eUserActive);

		DateLib::cancel(DateLib::getById($eDate['id']));

		foreach(['fermé' => $eDateEnrollmentClosed, 'actif' => $eDateEnrollmentActive] as $case => $eDateEnrollment) {

			$eDateEnrollment = $this->getLine($eDateEnrollment['id']);

			$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::DATE], [$eDateEnrollment['status'], $eDateEnrollment['canceledBy']], 'compte '.$case.' : place annulée avec sa date');

		}

		$this->assertEquals([], DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentClosed['id']), 'canceled'), 'compte fermé : aucun e-mail');
		$this->assertEquals([$eUserActive['email']], array_column(DateEnrollmentLib::getMails($this->getLine($eDateEnrollmentActive['id']), 'canceled'), 'to'), 'compte actif : son e-mail');

	}

	/**
	 * L'ouverture du quiz aboutit et compte un seul e-mail pour deux confirmées.
	 */
	public function testOpenQuiz(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 0);

		$this->enrollClosed($eDate);
		$this->enrollConfirmed($eDate, $this->createPerson());

		$this->assertEquals(1, DateLib::openQuiz($this->prepareDate($eDate)), 'un e-mail, à la seule confirmée qui a une adresse');
		$this->assertTrue(DateLib::getById($eDate['id'])->isQuizOpened(), 'la date est ouverte');

	}

	/**
	 * `remind()` lit toutes les dates de la base, qui est une copie de la production : le test juge
	 * ses deux places et un second passage, pas un total (forme de `DateEnrollmentRemind.t.php`). Les
	 * deux places sont rappelées quel que soit l'ordre du passage : la place fermée ne l'arrête pas, et
	 * n'est pas relue au suivant.
	 */
	public function testRemind(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 1);

		$lines = [
			'fermé' => $this->age($this->enrollClosed($eDate)),
			'actif' => $this->age($this->enrollConfirmed($eDate, $this->createPerson())),
		];

		$this->assertTrue(DateEnrollmentLib::remind() >= 1, 'le premier passage rappelle au moins la place active');

		foreach($lines as $case => $eDateEnrollment) {
			$this->assertTrue($this->getLine($eDateEnrollment['id'])['remindedAt'] !== NULL, 'compte '.$case.' : rappel posé');
		}

		$this->assertEquals(0, DateEnrollmentLib::remind(), 'le second passage ne rappelle personne, la place fermée comprise');

	}

	/**
	 * L'export du réseau garde la place, sans adresse.
	 */
	public function testExport(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();
		$eDate = $this->createDate($eCourse, 2);

		$eDateEnrollment = $this->enrollClosed($eDate);

		$eDate = $this->prepareDate($eDate);
		$lines = new DateEnrollmentUi()->getExport($eDate, DateEnrollmentLib::getByDate($eDate));

		$this->assertEquals(2, count($lines), 'l\'en-tête et la place');
		$this->assertEquals(['Stagiaire', 'zzTest', ''], array_slice($lines[1], 0, 3), 'nom, prénom, adresse vide');
		$this->assertEquals(DateEnrollmentUi::p('status')->values[DateEnrollment::CONFIRMED], $lines[1][6], 'l\'état de la place : '.$eDateEnrollment['id']);

	}

	/**
	 * Une place confirmée d'un compte fictif, puis le compte fermé par le chemin du produit.
	 */
	private function enrollClosed(Date $eDate): DateEnrollment {

		$eUser = $this->createPerson();
		$eDateEnrollment = $this->enrollConfirmed($eDate, $eUser);

		\user\DropLib::closeNow($eUser);

		$eDateEnrollment = $this->getLine($eDateEnrollment['id']);

		$this->assertEquals(NULL, $eDateEnrollment['user']['email'], 'décor : compte fermé, adresse vidée');

		return $eDateEnrollment;

	}

	/**
	 * Une confirmation de plus de 24 heures, que `getRemindable()` exige et qu'aucun geste du produit
	 * ne pose dans le passé : l'écriture est directe, comme dans `DateEnrollmentRemind.t.php`.
	 */
	private function age(DateEnrollment $eDateEnrollment): DateEnrollment {

		DateEnrollment::model()
			->whereId($eDateEnrollment['id'])
			->update(['confirmedAt' => new \Sql('NOW() - INTERVAL 2 DAY')]);

		return $this->getLine($eDateEnrollment['id']);

	}

}
?>
