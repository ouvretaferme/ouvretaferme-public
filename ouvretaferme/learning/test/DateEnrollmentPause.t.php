<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * La pause des inscriptions d'une date (lot T13) : une date ne prend plus d'inscription neuve quand
 * elle est en pause, ou quand sa formation l'est, par aucun des chemins où une inscription peut
 * naître :
 * - le panneau du membre, ses trois étapes (`create`, `doCommit`, `doCreate`) ;
 * - la page publique du réseau, dont les deux envois ne valident que `acceptPublicCreate()` ;
 * - l'écriture sous le verrou, `DateEnrollmentLib::create()` et `createPublic()`, pour un élément
 *   chargé avant la pause (un onglet resté ouvert).
 *
 * Les lignes déjà reçues restent gérées : le réseau les confirme, fait avancer la liste d'attente, et
 * la personne les annule. Une ligne annulée ne se rouvre pas pendant la pause.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentPause
 */
class DateEnrollmentPauseTest extends LearningDateFixtureTest {

	/**
	 * La date seule en pause, sous une formation ouverte, puis l'inverse : chacune des deux pauses
	 * suffit à fermer la date, et la reprise des deux la rouvre.
	 */
	public function testMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		// Le panneau ne s'ouvre qu'à qui lit la formation : retenue au catalogue public, elle se lit
		// sans date confirmée.
		$eCourse['catalog'] = Course::ACCEPTED;
		CourseLib::updateCatalog($eCourse);

		$eDate = $this->createDate($eCourse, 10);

		$this->assertEquals([\ViewAction::class, NULL], $this->pauseDate($eDate, TRUE), 'le réseau met la date en pause');
		$this->assertTrue(DateLib::getById($eDate['id'])['enrollmentPaused'], 'la pause de la date est écrite');

		$this->assertRefused($eDate, 'la date en pause');

		$this->assertEquals([\ViewAction::class, NULL], $this->pauseDate($eDate, FALSE), 'le réseau rouvre la date');
		$this->assertEquals([\ViewAction::class, NULL], $this->pauseCourse($eCourse, TRUE), 'le réseau met la formation en pause');

		$this->assertRefused($eDate, 'la formation en pause');

		$this->assertEquals([\ViewAction::class, NULL], $this->pauseCourse($eCourse, FALSE), 'le réseau rouvre la formation');

		$eUserOpen = $this->createPerson();

		$this->actAs($eUserOpen);
		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/dateEnrollment:doCreate', $this->getMemberInput($eDate))[0], 'rouverte, l\'inscription s\'envoie');
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUserOpen)->isOpen(), 'rouverte, la ligne est écrite');

	}

	/**
	 * La page publique : ses deux envois valident `acceptPublicCreate()`, que la pause ferme.
	 */
	public function testPublic(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->assertTrue($this->preparePublic($eDate, 'zztest.pause-ouverte@example.org')->acceptPublicCreate(), 'ouverte, la page publique accepte');

		$this->pauseDate($eDate, TRUE);
		$this->assertFalse($this->preparePublic($eDate, 'zztest.pause-date@example.org')->acceptPublicCreate(), 'la date en pause, la page publique refuse');

		$this->pauseDate($eDate, FALSE);
		$this->pauseCourse($eCourse, TRUE);
		$this->assertFalse($this->preparePublic($eDate, 'zztest.pause-formation@example.org')->acceptPublicCreate(), 'la formation en pause, la page publique refuse');

	}

	/**
	 * Les lignes reçues avant la pause : le réseau confirme la demande et donne la place libérée à la
	 * liste d'attente, la personne annule, et sa ligne annulée ne se rouvre pas.
	 */
	public function testOngoing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		// Posé sans `build()`, qui borne `seats` à un entier d'au moins 1 : la valeur est dans la borne,
		// et le décor veut une date pleine à la première ligne.
		$eDate['seats'] = 1;
		DateLib::update($eDate, ['seats']);

		$eUserRequested = $this->createPerson();
		$eUserWaiting = $this->createPerson();

		$eDateEnrollmentRequested = $this->enrollRequested($eDate, $eUserRequested);

		$this->writeMember($eDate, $eUserWaiting);
		$eDateEnrollmentWaiting = DateEnrollmentLib::getByDateAndUser($eDate, $eUserWaiting);

		if($this->assertDecorPresent($eDateEnrollmentWaiting['status'] === DateEnrollment::WAITING, 'une seconde ligne sur une date d\'une place part en liste d\'attente') === FALSE) {
			return;
		}

		$this->pauseCourse($eCourse, TRUE);

		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/dateEnrollment:doConfirm', ['id' => (string)$eDateEnrollmentRequested['id']])[0], 'en pause, le réseau confirme une demande en cours');
		$this->assertEquals(DateEnrollment::CONFIRMED, $this->getLine($eDateEnrollmentRequested['id'])['status'], 'la demande est confirmée');

		$this->actAs($eUserRequested);
		$this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$eDateEnrollmentRequested['id']]);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(DateEnrollment::CANCELED, $this->getLine($eDateEnrollmentRequested['id'])['status'], 'en pause, la personne annule sa place');

		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/dateEnrollment:doGiveSeat', ['id' => (string)$eDateEnrollmentWaiting['id']])[0], 'en pause, le réseau donne la place libérée à la liste d\'attente');
		$this->assertTrue($this->getLine($eDateEnrollmentWaiting['id'])->isOpen(), 'la ligne en attente prend la place');
		$this->assertTrue($this->getLine($eDateEnrollmentWaiting['id'])['status'] !== DateEnrollment::WAITING, 'et sort de la liste d\'attente');

		$this->actAs($eUserRequested);
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::paused'], $this->executePage('learning/dateEnrollment:doCreate', $this->getMemberInput($eDate)), 'en pause, une ligne annulée ne se rouvre pas');
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(DateEnrollment::CANCELED, $this->getLine($eDateEnrollmentRequested['id'])['status'], 'la ligne reste annulée');

	}

	/**
	 * L'élément chargé avant la pause, écrit après : le contrôle sous le verrou relit la date et la
	 * formation en base, et refuse avec le message de la pause.
	 */
	public function testLock(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		foreach(['date' => fn(bool $paused) => $this->pauseDate($eDate, $paused), 'formation' => fn(bool $paused) => $this->pauseCourse($eCourse, $paused)] as $label => $pause) {

			$eUser = $this->createPerson();
			$email = 'zztest.verrou-'.$label.'@example.org';

			$eDateEnrollment = $this->prepareMember($eDate, $eUser);
			$eDateEnrollmentPublic = $this->preparePublic($eDate, $email);

			$pause(TRUE);

			$fw = new \FailWatch();
			DateEnrollmentLib::create($eDateEnrollment);
			$this->assertEquals([['DateEnrollment::paused', NULL]], $this->getFails($fw), 'la '.$label.' en pause, create() refuse sous le verrou');

			$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUser)->empty(), 'la '.$label.' en pause, aucune ligne du membre');

			$fw = new \FailWatch();
			DateEnrollmentLib::createPublic($eDateEnrollmentPublic);
			$this->assertEquals([['DateEnrollment::paused', NULL]], $this->getFails($fw), 'la '.$label.' en pause, createPublic() refuse sous le verrou');

			$this->assertTrue($this->getLineByEmail($eDate, $email)->empty(), 'la '.$label.' en pause, aucune ligne publique');

			$pause(FALSE);

		}

		// Annulée et en pause : le refus définitif passe avant la pause, qui inviterait à revenir.
		$eUser = $this->createPerson();
		$eDateEnrollment = $this->prepareMember($eDate, $eUser);

		$this->pauseDate($eDate, TRUE);
		DateLib::cancel(DateLib::getById($eDate['id']));

		$fw = new \FailWatch();
		DateEnrollmentLib::create($eDateEnrollment);
		$this->assertEquals([['DateEnrollment::closed', NULL]], $this->getFails($fw), 'annulée et en pause, create() refuse comme fermée');

	}

	/**
	 * La date, puis la formation, supprimées entre l'affichage et l'envoi : la relecture sous le
	 * verrou ne les trouve plus et refuse comme fermée, sans promettre de retour.
	 */
	public function testDeleted(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		// Une formation par test (le décor du réseau ne se pose qu'une fois) : la date se supprime
		// d'abord seule, puis la formation avec sa seconde date.
		[$eCourse] = $this->seedCourse(quiz: FALSE);

		foreach([
			'la date' => fn(Date $eDate) => DateLib::delete(DateLib::getById($eDate['id'])),
			'la formation' => fn(Date $eDate) => CourseLib::delete($this->getCourse($eCourse['id']))
		] as $label => $delete) {

			$eDate = $this->createDate($eCourse, 10);

			$eUser = $this->createPerson();
			$email = 'zztest.supprimee-'.$eDate['id'].'@example.org';

			$eDateEnrollment = $this->prepareMember($eDate, $eUser);
			$eDateEnrollmentPublic = $this->preparePublic($eDate, $email);

			$delete($eDate);

			$this->assertTrue(DateLib::getById($eDate['id'])->empty(), $label.' supprimée, la date n\'est plus en base');

			$fw = new \FailWatch();
			DateEnrollmentLib::create($eDateEnrollment);
			$this->assertEquals([['DateEnrollment::closed', NULL]], $this->getFails($fw), $label.' supprimée, create() refuse comme fermée');

			$fw = new \FailWatch();
			DateEnrollmentLib::createPublic($eDateEnrollmentPublic);
			$this->assertEquals([['DateEnrollment::closed', NULL]], $this->getFails($fw), $label.' supprimée, createPublic() refuse comme fermée');

			$this->assertTrue(DateEnrollment::model()->whereDate($eDate)->exists() === FALSE, $label.' supprimée, aucune ligne écrite');

		}

	}

	/**
	 * L'interrupteur d'une date se règle par qui écrit la formation, tant que la formation n'est pas
	 * elle-même en pause : il est alors caché, et le POST forgé refuse sans rien écrire.
	 */
	public function testSwitch(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->assertTrue(DateLib::getById($eDate['id'])->assertUpdateEnrollmentPaused(), 'l\'interrupteur de la date se montre au réseau');

		$eUser = $this->createPerson();

		$this->actAs($eUser);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->pauseDate($eDate, TRUE), 'qui n\'écrit pas la formation ne met pas la date en pause');
		$this->actAs($this->eUserAdmin);

		$this->assertFalse(DateLib::getById($eDate['id'])['enrollmentPaused'], 'rien n\'est écrit');

		$this->pauseCourse($eCourse, TRUE);

		$this->assertFalse(DateLib::getById($eDate['id'])->assertUpdateEnrollmentPaused(), 'la formation en pause, l\'interrupteur de la date est caché');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->pauseDate($eDate, TRUE), 'la formation en pause, le POST forgé refuse');
		$this->assertFalse(DateLib::getById($eDate['id'])['enrollmentPaused'], 'rien n\'est écrit');

		// La formation se met en pause depuis sa date à venir ; sans elle, l'interrupteur est caché.
		$this->pauseCourse($eCourse, FALSE);

		$eCourseEdition = $this->getCourse($eCourse['id']);
		CourseLib::fillHasDateUpcoming($eCourseEdition);
		$this->assertTrue($eCourseEdition->assertUpdateEnrollmentPaused(), 'une date à venir, l\'interrupteur de la formation se montre');

		$this->moveDate($eDate, -2);

		$eCourseEdition = $this->getCourse($eCourse['id']);
		CourseLib::fillHasDateUpcoming($eCourseEdition);
		$this->assertFalse($eCourseEdition->assertUpdateEnrollmentPaused(), 'sans date à venir, l\'interrupteur de la formation est caché');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->pauseCourse($eCourse, TRUE), 'sans date à venir, le POST forgé refuse');
		$this->assertFalse($this->getCourse($eCourse['id'])['enrollmentPaused'], 'rien n\'est écrit');

	}

	/**
	 * Les trois étapes du panneau refusent avec le message de la pause, pour une personne sans ligne.
	 */
	private function assertRefused(Date $eDate, string $label): void {

		$eUser = $this->createPerson();

		$this->actAs($eUser);
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::paused'], $this->executePage('learning/dateEnrollment:create', ['date' => (string)$eDate['id']], 'GET'), $label.', le panneau refuse');
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::paused'], $this->executePage('learning/dateEnrollment:doCommit', $this->getMemberInput($eDate)), $label.', l\'étape 2 refuse');
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::paused'], $this->executePage('learning/dateEnrollment:doCreate', $this->getMemberInput($eDate)), $label.', le POST forgé refuse');
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUser)->empty(), $label.', aucune ligne écrite');

	}

	private function pauseDate(Date $eDate, bool $paused): array {

		return $this->executePage('learning/date:doUpdateEnrollmentPaused', [
			'id' => (string)$eDate['id'],
			'enrollmentPaused' => $paused ? '1' : '0',
		]);

	}

	private function pauseCourse(Course $eCourse, bool $paused): array {

		return $this->executePage('learning/course:doUpdateEnrollmentPaused', [
			'id' => (string)$eCourse['id'],
			'enrollmentPaused' => $paused ? '1' : '0',
		]);

	}

	private function getMemberInput(Date $eDate): array {

		return [
			'date' => (string)$eDate['id'],
			'commitment' => ['presence', 'cancel', 'conditions'],
			'structure' => '',
			'trainee' => DateEnrollment::FARMER,
		];

	}

	/**
	 * Les deux éléments sont remplis sans `build()` : il aurait vérifié les engagements et l'identité
	 * postés, que `testMember()` éprouve par les pages. Ici, seul compte ce que `create()` et
	 * `createPublic()` relisent sous le verrou, et ce que `acceptPublicCreate()` juge.
	 */
	private function prepareMember(Date $eDate, \user\User $eUser): DateEnrollment {

		$e = DateEnrollmentLib::getCreateElement(DateLib::getById($eDate['id']), $eUser);
		$e['structure'] = new \structure\Structure();
		$e['trainee'] = DateEnrollment::FARMER;
		$e['committedAt'] = \Clock::datetime();

		return $e;

	}

	private function preparePublic(Date $eDate, string $email): DateEnrollment {

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$eStructureCommunity['community'] = \structure\CommunityLib::getByStructure($eStructureCommunity);

		$e = DateEnrollmentLib::getPublicCreateElement(DateLib::getById($eDate['id']), $eStructureCommunity, new \user\User());
		$e['firstName'] = 'Zztest';
		$e['lastName'] = 'Public';
		$e['email'] = $email;
		$e['trainee'] = DateEnrollment::FARMER;
		$e['committedAt'] = \Clock::datetime();

		return $e;

	}

	private function writeMember(Date $eDate, \user\User $eUser): void {
		DateEnrollmentLib::create($this->prepareMember($eDate, $eUser));
	}

	private function getLineByEmail(Date $eDate, string $email): DateEnrollment {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereEmail($email)
			->get();

	}

}
?>
