<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le contact que donnent les e-mails aux stagiaires d'une date (lot contact-mails) : « Pour en savoir
 * plus, écrivez à … » nomme l'encadrant contact de la formation et donne son adresse, et l'adresse
 * légale du réseau ne sert plus que de repli, la même règle que la ligne Contact de la fiche
 * (`StaffContactTest::testFiche()`). Entre les deux passe le contact du réseau, qu'il encadre ou non
 * la formation (lot des encadrants du réseau). Quatre e-mails portent la phrase : l'accusé de réception d'une
 * ligne de la page publique, le rappel, l'ouverture du quiz et l'annulation par le réseau. Les
 * e-mails qui partent au réseau gardent son adresse légale pour destinataire.
 *
 * Chaque e-mail est rendu par `DateEnrollmentLib::getMails()`, sans envoi, pour une ligne sans compte
 * de la page publique, qui les reçoit tous avec la phrase. L'annulation est posée sur l'élément lu,
 * rien n'est écrit pour elle.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentContact
 */
class DateEnrollmentContactTest extends LearningDateFixtureTest {

	public function testMails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.contact-courriel@example.org');

		$eStructureNetwork = \structure\StructureLib::getById($this->eStructureNetwork['id']);

		if($eStructureNetwork['legalEmail'] === '') {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' n\'a pas d\'adresse légale dans cette base, le repli ne peut pas se lire');
			return;
		}

		if(SupervisorLib::getContactByCommunity($eStructureNetwork)->notEmpty()) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' a un contact du réseau dans cette base, le repli sur l\'adresse légale ne peut pas se lire');
			return;
		}

		$this->actAs($this->eUserAdmin);

		// Un encadrant qui n'est pas contact : son adresse ne paraît dans aucun e-mail.
		$this->createStaff($eCourse, 'Bob Petit', 'bob@example.org', FALSE);

		$network = $this->getSentence($eStructureNetwork['name'], $eStructureNetwork['legalEmail']);

		foreach($this->getMails($eDateEnrollment, $eDate) as $mail => $content) {
			$this->assertTrue(str_contains($content, $network), 'sans contact, '.$mail.' donne l\'adresse légale du réseau');
			$this->assertFalse(str_contains($content, 'bob@example.org'), 'sans contact, '.$mail.' ne donne pas l\'adresse d\'un encadrant');
		}

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/supervisor:doCreate', [
			'community' => (string)$this->eStructureNetwork['id'],
			'name' => 'Diane Faure',
			'role' => '',
			'description' => '',
			'email' => 'diane@example.org',
			'handicap' => '0',
			'contact' => '1'
		]), 'le réseau désigne son contact, qui n\'encadre pas la formation');

		$contact = $this->getSentence('Diane Faure', 'diane@example.org');

		foreach($this->getMails($eDateEnrollment, $eDate) as $mail => $content) {
			$this->assertTrue(str_contains($content, $contact), 'sans contact désigné, '.$mail.' nomme le contact du réseau et donne son adresse');
			$this->assertFalse(str_contains($content, $network), 'sans contact désigné, '.$mail.' ne donne plus l\'adresse légale du réseau');
		}

		$this->createStaff($eCourse, 'Ada Martin', 'ada@example.org', TRUE);

		$staff = $this->getSentence('Ada Martin', 'ada@example.org');

		foreach($this->getMails($eDateEnrollment, $eDate) as $mail => $content) {
			$this->assertTrue(str_contains($content, $staff), 'avec un contact, '.$mail.' nomme Ada et donne son adresse');
			$this->assertFalse(str_contains($content, $network), 'avec un contact, '.$mail.' ne donne plus l\'adresse légale du réseau');
			$this->assertFalse(str_contains($content, $contact), 'avec un contact, '.$mail.' ne donne plus le contact du réseau');
			$this->assertFalse(str_contains($content, 'bob@example.org'), 'avec un contact, '.$mail.' ne donne pas l\'adresse de Bob');
		}

		$e = $this->getLine($eDateEnrollment['id']);
		$e['status'] = DateEnrollment::CANCELED;
		$e['canceledBy'] = DateEnrollment::USER;

		$this->assertEquals([$eStructureNetwork['legalEmail']], array_column(DateEnrollmentLib::getMails($e, 'canceled'), 'to'), 'l\'annulation par la personne part toujours à l\'adresse légale du réseau');

	}

	private function getSentence(string $name, string $email): string {
		return 'écrivez à '.encode($name).' à l\'adresse '.\main\MailSoftwareAiUi::linkTag('mailto:'.$email).encode($email).'</a>';
	}

	/**
	 * Les quatre e-mails qui donnent le contact, rendus sur la ligne relue à chaque fois.
	 *
	 * @return array<string, string>
	 */
	private function getMails(DateEnrollment $eDateEnrollment, Date $eDate): array {

		$mails = [];

		$e = $this->getLine($eDateEnrollment['id']);
		$mails['l\'accusé de réception'] = DateEnrollmentLib::getMails($e, 'created');

		$e = $this->getLine($eDateEnrollment['id']);
		$mails['le rappel'] = DateEnrollmentLib::getMails($e, 'reminded');

		$e = $this->getLine($eDateEnrollment['id']);
		$e['date'] = $this->prepareDate($eDate);
		$e['date']['eLessonQuiz']['questions'] = 2;
		$e['date']['eLessonQuiz']['needed'] = 1;
		$mails['l\'ouverture du quiz'] = DateEnrollmentLib::getMails($e, 'quizOpened');

		$e = $this->getLine($eDateEnrollment['id']);
		$e['status'] = DateEnrollment::CANCELED;
		$e['canceledBy'] = DateEnrollment::COMMUNITY;
		$mails['l\'annulation par le réseau'] = DateEnrollmentLib::getMails($e, 'canceled');

		$contents = [];

		foreach($mails as $mail => $sent) {

			$this->assertEquals(['zztest.contact-courriel@example.org'], array_column($sent, 'to'), $mail.' part à la personne');

			$contents[$mail] = implode(' ', $sent[0]['content']);

		}

		return $contents;

	}

	private function createStaff(Course $eCourse, string $name, string $email, bool $contact): void {

		$result = $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'name' => $name,
			'role' => 'formatrice',
			'description' => '',
			'email' => $email
		] + ($contact ? ['contact' => '1'] : []));

		$this->assertEquals([\ReloadAction::class, NULL], $result, $name.' encadrant');

	}

}
?>
