<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * L'attestation de présence d'une formation datée sans leçon (lot L8f) : à qui elle se délivre, son
 * envoi par l'équipe, une fois par place, et les trois endroits où elle se télécharge (la roue de la
 * liste des inscrits, la fiche de la formation, la page du jeton).
 *
 * Le PDF lui-même ne se rend pas ici : `PdfLib::build()` le fait lire par un navigateur sans tête,
 * qui ne voit pas la transaction du test. Les routes sont éprouvées sur leurs refus, le document sur
 * le HTML que la page signée rend.
 */
class PresenceAttestationTest extends LearningDateFixtureTest {

	const OBJECTIVES = '<p>Tenir le registre des traitements.</p>';

	/**
	 * Une place confirmée et pointée d'une date finie, d'une formation sans leçon, et elle seule.
	 */
	public function testAccept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());

		$this->assertFalse($this->getPresenceLine($eDateEnrollment)->acceptPresenceAttestation(), 'une date à venir');

		$eDate = $this->moveDate($eDate, -2);

		$this->assertFalse($this->getPresenceLine($eDateEnrollment)->acceptPresenceAttestation(), 'une date finie, la place n\'est pas pointée');

		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$this->assertTrue($this->getPresenceLine($eDateEnrollment)->acceptPresenceAttestation(), 'pointée sur une date finie');

		$eDateEnrollmentRequested = $this->enrollRequested($this->createDate($eCourse, 3), $this->createPerson());
		$this->moveDate($eDateEnrollmentRequested['date'], -2);
		$this->assertFalse($this->getPresenceLine($eDateEnrollmentRequested)->acceptPresenceAttestation(), 'une demande à confirmer');

		// Le même parcours sur une formation qui a une leçon : c'est l'attestation de suivi qui la couvre.
		[$eCourseLesson] = $this->seedCourse(quiz: FALSE, first: FALSE);

		$eDateLesson = $this->createDate($eCourseLesson, 2);
		$eDateEnrollmentLesson = $this->enrollConfirmed($eDateLesson, $this->createPerson());
		$this->moveDate($eDateLesson, -2);
		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentLesson['id']));

		$this->assertFalse($this->getPresenceLine($eDateEnrollmentLesson)->acceptPresenceAttestation(), 'une formation avec une leçon');

		$eDateCanceled = $this->createDate($eCourse, 4);
		$eDateEnrollmentCanceled = $this->enrollConfirmed($eDateCanceled, $this->createPerson());
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));
		$this->moveDate($eDateCanceled, -2);

		$this->assertFalse($this->getPresenceLine($eDateEnrollmentCanceled)->acceptPresenceAttestation(), 'une date annulée');

	}

	/**
	 * L'envoi part à chaque place qui attend la sienne et la marque : un second geste n'envoie rien,
	 * une personne pointée ensuite part seule au suivant. Aucun e-mail n'est écrit en base, les
	 * adresses du décor étant hors de `MailSetting::$devSendOnly`.
	 */
	public function testSend(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollmentFirst = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentLater = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.presence-public@example.org');
		$eDateEnrollmentAbsent = $this->enrollConfirmed($eDate, $this->createPerson());

		$eDate = $this->moveDate($eDate, -2);

		$this->assertFalse($this->prepareAttestation($eDate)->acceptSendAttestations(), 'personne n\'est pointé');

		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentFirst['id']));
		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentPublic['id']));

		$eDatePrepared = $this->prepareAttestation($eDate);
		$this->assertEquals([2, 0, NULL], [$eDatePrepared['attestationRecipients'], $eDatePrepared['attestationMailed'], $eDatePrepared['attestationMailedAt']], 'deux places attendent la leur');
		$this->assertTrue($eDatePrepared->acceptSendAttestations(), 'l\'envoi se propose');

		$lastMail = $this->getLastMail();

		$this->assertEquals(2, DateEnrollmentLib::sendAttestations(DateLib::getById($eDate['id'])), 'deux attestations parties');
		$this->assertEquals(0, DateEnrollmentLib::sendAttestations(DateLib::getById($eDate['id'])), 'un second geste n\'envoie rien');

		$this->assertTrue($this->getLine($eDateEnrollmentFirst['id'])['attestationMailedAt'] !== NULL, 'la place du compte est marquée');
		$this->assertTrue($this->getLine($eDateEnrollmentPublic['id'])['attestationMailedAt'] !== NULL, 'la place sans compte aussi');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollmentAbsent['id'])['attestationMailedAt'], 'pas l\'absente');

		$eDatePrepared = $this->prepareAttestation($eDate);
		$this->assertEquals([0, 2], [$eDatePrepared['attestationRecipients'], $eDatePrepared['attestationMailed']], 'plus rien à envoyer');
		$this->assertFalse($eDatePrepared->acceptSendAttestations(), 'l\'envoi ne se propose plus');

		$this->assertEquals([\FailAction::class, 'learning\Date::attestationsAlreadyMailed'], $this->executePage('learning/date:doSendAttestations', ['id' => (string)$eDate['id']]), 'refusé, rien à envoyer');

		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentLater['id']));

		$this->assertEquals(1, $this->prepareAttestation($eDate)['attestationRecipients'], 'la personne pointée ensuite attend la sienne');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doSendAttestations', ['id' => (string)$eDate['id']]), 'le geste de l\'équipe l\'envoie');
		$this->assertTrue($this->getLine($eDateEnrollmentLater['id'])['attestationMailedAt'] !== NULL, 'et la marque');

		$this->assertEquals($lastMail, $this->getLastMail(), 'aucun e-mail écrit en base');

		$mails = DateEnrollmentLib::getMails($this->getPresenceLine($eDateEnrollmentPublic), 'attestationMailed');
		$this->assertEquals(1, count($mails), 'un e-mail par place');
		$this->assertTrue(str_contains(implode(' ', $mails[0]['content']), '/inscription/'.$eDateEnrollmentPublic['token']), 'la place sans compte est menée à sa page du jeton');

	}

	/**
	 * L'envoi n'est proposé qu'au réseau, sur une date finie d'une formation sans leçon.
	 */
	public function testSendRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/date:doSendAttestations', ['id' => (string)$eDate['id']]), 'refusé sur une date à venir');

		$this->moveDate($eDate, -2);
		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/date:doSendAttestations', ['id' => (string)$eDate['id']]), 'refusé hors du réseau');
		$this->assertEquals(NULL, $this->getLine($eDateEnrollment['id'])['attestationMailedAt'], 'rien n\'est marqué');

	}

	/**
	 * La route du compte refuse une autre personne et une place sans présence ; celle du jeton refuse
	 * une place sans présence. La fiche propose le téléchargement à la seule place pointée.
	 */
	public function testRoutes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson();

		$eUser = $this->createPerson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $eUser);
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.presence-jeton@example.org');
		$this->moveDate($eDate, -2);

		$path = 'formation/date/inscription/'.$eDateEnrollment['id'].'/attestation';
		$pathToken = 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'].'/inscription/'.$eDateEnrollmentPublic['token'].'/attestation';

		$this->actAs($eUser);

		$this->assertEquals(\NotExpectedAction::class, $this->executeView($path, []), 'la place n\'est pas pointée');
		$this->assertEquals(\NotExpectedAction::class, $this->executeView($pathToken, []), 'ni celle du jeton');
		$this->assertFalse(str_contains($this->getFicheHtml($eCourse), DateEnrollmentUi::urlPresence($eDateEnrollment)), 'la fiche ne propose rien');

		$this->actAs($this->eUserAdmin);

		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView($path, []), 'une autre personne');

		$this->actAs($eUser);

		$fiche = $this->getFicheHtml($eCourse);
		$this->assertTrue(str_contains($fiche, DateEnrollmentUi::urlPresence($eDateEnrollment)), 'la fiche propose la sienne, vers la route du compte');

		// Une seconde date pointée : une attestation par date.
		$this->actAs($this->eUserAdmin);

		$eDateSecond = $this->createDate($eCourse, 3);
		$eDateEnrollmentSecond = $this->enrollConfirmed($eDateSecond, $eUser);
		$this->moveDate($eDateSecond, -1);
		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentSecond['id']));

		$this->actAs($eUser);

		$fiche = $this->getFicheHtml($eCourse);
		$this->assertTrue(str_contains($fiche, DateEnrollmentUi::urlPresence($eDateEnrollment)) and str_contains($fiche, DateEnrollmentUi::urlPresence($eDateEnrollmentSecond)), 'deux attestations sur la fiche');

		// La page du jeton : la porte paraît une fois la place pointée.
		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$ui = new DatePublicUi($eStructureCommunity, \structure\CommunityLib::getByStructure($eStructureCommunity), FALSE);

		$this->assertFalse(str_contains($ui->getEnrollment($this->getPresenceLine($eDateEnrollmentPublic)), '/attestation'), 'la page du jeton ne propose rien');

		$this->actAs($this->eUserAdmin);
		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentPublic['id']));

		$this->assertTrue(str_contains($ui->getEnrollment($this->getPresenceLine($eDateEnrollmentPublic)), '/inscription/'.$eDateEnrollmentPublic['token'].'/attestation'), 'puis la sienne');

	}

	/**
	 * La liste d'une date finie : l'envoi caché quand rien n'attend, l'attestation de la seule place
	 * pointée, et la date de l'envoi.
	 */
	public function testList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());
		$eDateEnrollmentAbsent = $this->enrollConfirmed($eDate, $this->createPerson());

		$eDate = $this->moveDate($eDate, -2);

		$html = $this->renderList($eDate);
		$this->assertTrue(str_contains($html, 'hide" id="learning-date-attestation-send"'), 'caché, personne n\'est pointé');

		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$html = $this->renderList($eDate);
		$this->assertFalse(str_contains($html, 'hide" id="learning-date-attestation-send"'), 'l\'envoi paraît');
		$this->assertTrue(str_contains($html, DateEnrollmentUi::urlPresence($eDateEnrollment)), 'vers son attestation');
		$this->assertFalse(str_contains($html, DateEnrollmentUi::urlPresence($eDateEnrollmentAbsent)), 'pas celle de l\'absente');

		DateEnrollmentLib::sendAttestations(DateLib::getById($eDate['id']));

		$this->assertEquals(\Clock::date(), substr($this->prepareAttestation($eDate)['attestationMailedAt'], 0, 10), 'l\'état après l\'envoi, au jour de l\'envoi');

	}

	/**
	 * Une formation sans objectifs ne délivre pas d'attestation, que la loi veut citant ses objectifs :
	 * l'équipe est menée à les écrire, depuis l'envoi comme depuis la roue ; la personne n'a rien à
	 * télécharger, sans message ; le serveur refuse l'envoi en le disant, et le téléchargement. Une
	 * fois les objectifs écrits, tout se délivre.
	 */
	public function testWithoutObjectives(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson(objectives: FALSE);

		$eUser = $this->createPerson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $eUser);
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.presence-objectifs@example.org');
		$eDate = $this->moveDate($eDate, -2);

		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));
		DateEnrollmentLib::attend($this->getLine($eDateEnrollmentPublic['id']));

		$eDateEnrollmentPresence = $this->getPresenceLine($eDateEnrollment);
		$this->assertTrue($eDateEnrollmentPresence->hasPresenceAttestation(), 'la place relève de l\'attestation');
		$this->assertFalse($eDateEnrollmentPresence->acceptPresenceAttestation(), 'qui ne se délivre pas sans objectifs');

		$eDatePrepared = $this->prepareAttestation($eDate);
		$this->assertEquals(2, $eDatePrepared['attestationRecipients'], 'deux places l\'attendent');
		$this->assertFalse($eDatePrepared->acceptSendAttestations(), 'l\'envoi ne s\'ouvre pas');

		$html = $this->renderList($eDate);
		$this->assertFalse(str_contains($html, 'date:sendAttestations'), 'pas d\'envoi');
		$this->assertEquals(3, substr_count($html, DateEnrollmentUi::urlObjectives($eCourse)), 'à sa place, le lien vers les objectifs, et sur chacune des deux places pointées');
		$this->assertFalse(str_contains($html, DateEnrollmentUi::urlPresence($eDateEnrollment)), 'sans attestation à télécharger');

		$lastMail = $this->getLastMail();

		$this->assertEquals([\FailAction::class, 'learning\Date::attestationsObjectives'], $this->executePage('learning/date:doSendAttestations', ['id' => (string)$eDate['id']]), 'l\'envoi refusé, en le disant');
		$this->assertEquals([NULL, NULL], [$this->getLine($eDateEnrollment['id'])['attestationMailedAt'], $this->getLine($eDateEnrollmentPublic['id'])['attestationMailedAt']], 'rien n\'est marqué');
		$this->assertEquals($lastMail, $this->getLastMail(), 'aucun e-mail écrit en base');

		$path = 'formation/date/inscription/'.$eDateEnrollment['id'].'/attestation';
		$pathToken = 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'].'/inscription/'.$eDateEnrollmentPublic['token'].'/attestation';

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$ui = new DatePublicUi($eStructureCommunity, \structure\CommunityLib::getByStructure($eStructureCommunity), FALSE);

		$this->assertEquals(\NotExpectedAction::class, $this->executeView($pathToken, []), 'le téléchargement du jeton refusé');
		$this->assertFalse(str_contains($ui->getEnrollment($this->getPresenceLine($eDateEnrollmentPublic)), '/attestation'), 'la page du jeton ne propose rien');

		$this->actAs($eUser);

		$this->assertEquals(\NotExpectedAction::class, $this->executeView($path, []), 'le téléchargement du compte refusé');
		$fiche = $this->getFicheHtml($eCourse);
		$this->assertFalse(str_contains($fiche, DateEnrollmentUi::urlPresence($eDateEnrollment)), 'la fiche ne propose rien');
		$this->assertFalse(str_contains($fiche, DateEnrollmentUi::urlObjectives($eCourse)), 'et ne mène pas aux objectifs');

		$this->actAs($this->eUserAdmin);

		$this->writeObjectives($eCourse);

		$this->assertTrue($this->getPresenceLine($eDateEnrollment)->acceptPresenceAttestation(), 'les objectifs écrits, elle se délivre');

		$html = $this->renderList($eDate);
		$this->assertTrue(str_contains($html, 'date:sendAttestations?id='.$eDate['id']), 'l\'envoi revient');
		$this->assertFalse(str_contains($html, DateEnrollmentUi::urlObjectives($eCourse)), 'le lien vers les objectifs s\'en va');
		$this->assertTrue(str_contains($html, DateEnrollmentUi::urlPresence($eDateEnrollment)), 'la roue propose l\'attestation');

	}

	/**
	 * Le document de la page signée : la présence entière, puis partielle quand l'équipe corrige les
	 * heures, et le lieu de la séance en présentiel.
	 */
	public function testDocument(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedCourseWithoutLesson();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());
		$this->moveDate($eDate, -2);
		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);

		$html = strip_tags(new AttestationUi()->getPresence($this->getPresenceLine($eDateEnrollment), $eStructureCommunity, \Clock::date()));

		$this->assertTrue(str_contains($html, 'Attestation de présence'), 'le titre');
		$this->assertTrue(str_contains($html, 'De présence'), 'la durée de présence');
		$this->assertFalse(str_contains($html, 'De présence, sur'), 'entière');
		$this->assertTrue(str_contains($html, 'Salle des fêtes, Crest'), 'le lieu');
		$this->assertTrue(str_contains($html, 'Cette attestation ne vaut pas certification.'), 'la mention');

		// Les quatre mentions de l'article L6353-1 du code du travail.
		$this->assertTrue(str_contains($html, 'Objectifs de la formation') and str_contains($html, 'Tenir le registre des traitements.'), 'les objectifs, avec leur texte');
		$this->assertTrue(str_contains($html, 'Nature de l\'action'), 'la nature de l\'action');
		$this->assertTrue(str_contains($html, 'De présence'), 'la durée');
		$this->assertTrue(str_contains($html, 'Évaluation des acquis'), 'l\'évaluation des acquis');

		$eDateEnrollmentPartial = $this->getLine($eDateEnrollment['id']);
		$eDateEnrollmentPartial['attendedMinutes'] = 60;
		DateEnrollmentLib::update($eDateEnrollmentPartial, ['attendedMinutes']);

		$html = strip_tags(new AttestationUi()->getPresence($this->getPresenceLine($eDateEnrollment), $eStructureCommunity, \Clock::date()));

		$this->assertTrue(str_contains($html, 'De présence, sur'), 'partielle');
		$this->assertTrue(str_contains($html, 'Elle porte sur'), 'la note le dit');

	}

	/**
	 * La formation du décor sans chapitre ni leçon, publiée, sur place, ouverte aux fermes du réseau.
	 * Ses objectifs s'écrivent par le panneau du détail, comme l'équipe le fait, sauf quand le test
	 * éprouve leur absence.
	 */
	private function seedCourseWithoutLesson(bool $objectives = TRUE): Course {

		$eCourse = $this->seed(Course::ONSITE);

		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		if($objectives) {
			$this->writeObjectives($eCourse);
		}

		return $this->getCourse($eCourse['id']);

	}

	private function writeObjectives(Course $eCourse): void {

		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/course:doUpdateDetails', [
			'id' => (string)$eCourse['id'],
			'objectives' => self::OBJECTIVES,
		])[0], 'les objectifs s\'écrivent');

	}

	/**
	 * La place telle que la route et la page signée la jugent : `lessons` posé.
	 */
	private function getPresenceLine(DateEnrollment $eDateEnrollment): DateEnrollment {

		$e = $this->getLine($eDateEnrollment['id']);

		DateEnrollmentLib::fillLessons(new \Collection([$e]));

		return $e;

	}

	/**
	 * La date telle que la liste et le panneau de l'envoi la préparent.
	 */
	private function prepareAttestation(Date $eDate): Date {

		$eDate = $this->prepareDate($eDate);

		DateEnrollmentLib::fillAttestationRecipients($eDate, DateEnrollmentLib::getByDate($eDate));

		return $eDate;

	}

	private function renderList(Date $eDate): string {

		$eDate = $this->prepareDate($eDate);
		DateEnrollmentLib::fillAttendedByDate($eDate);

		$cDateSheet = DateSheetLib::getByDate($eDate);
		$eDate['sheets'] = $cDateSheet->count();

		$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
		DateEnrollmentLib::fillLosesLessons($eDate['course'], $cDateEnrollment);
		DateEnrollmentLib::fillAttestationRecipients($eDate, $cDateEnrollment);

		return new DateEnrollmentUi()->getList($eDate, $cDateEnrollment, $cDateSheet, '');

	}

	private function getFicheHtml(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'], []);
		$this->assertTrue($data instanceof \stdClass, 'la fiche s\'ouvre : '.(is_string($data) ? $data : ''));

		if(is_string($data)) {
			return '';
		}

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

	}

}
?>
