<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le statut d'un encadrant (lot des encadrants du réseau, remarque 22) : une personne
 * désactivée depuis la liste de son réseau n'est plus proposée aux formations, et rien d'autre ne
 * change. Elle reste sur les formations qu'elle encadre, leur fiche, leur feuille d'émargement, et
 * le contact qu'elle était, d'une formation ou du réseau, dans les e-mails aux stagiaires.
 *
 * Le fichier éprouve la règle des deux côtés : la seule lecture que le statut filtre
 * (`SupervisorLib::getForCourse()`, que le panneau d'ajout propose et que l'étape
 * `Staff::supervisor.owned` relit), puis chacune de celles qu'il ne filtre pas. Un filtre de statut
 * ajouté à l'une d'elles rougit ici.
 *
 * Tout s'écrit par les routes du produit, dans la transaction. Les e-mails sont rendus par
 * `DateEnrollmentLib::getMails()`, sans envoi.
 *
 * Lancement : dev/test package=learning file=SupervisorStatus
 */
class SupervisorStatusTest extends LearningDateFixtureTest {

	/**
	 * Désactivée, une personne quitte la liste proposée, le panneau d'ajout et ne se rattache plus ;
	 * réactivée, elle y revient.
	 */
	public function testProposed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$eSupervisorAda = $this->createSupervisor('zzTest Ada Martin', 'ada@example.org', FALSE);
		$eSupervisorCarla = $this->createSupervisor('zzTest Carla Roy', 'carla@example.org', FALSE);

		$this->assertEquals([Supervisor::ACTIVE, Supervisor::ACTIVE], [$eSupervisorAda['status'], $eSupervisorCarla['status']], 'une personne nait active');
		$this->assertEquals([TRUE, TRUE], $this->isProposed($eCourse, $eSupervisorCarla), 'active, Carla est proposée, par le Lib et dans le panneau');

		$this->assertEquals([\ViewAction::class, NULL], $this->setStatus($eSupervisorCarla, Supervisor::INACTIVE), 'Carla désactivée');
		$this->assertEquals(Supervisor::INACTIVE, $this->getStatus($eSupervisorCarla), 'le statut est écrit');

		$this->assertEquals([FALSE, FALSE], $this->isProposed($eCourse, $eSupervisorCarla), 'désactivée, Carla n\'est plus proposée, ni par le Lib ni dans le panneau');
		$this->assertEquals([TRUE, TRUE], $this->isProposed($eCourse, $eSupervisorAda), 'Ada, active, l\'est toujours');

		$this->assertEquals([\FailAction::class, 'Staff::supervisor.owned'], $this->pick($eCourse, $eSupervisorCarla), 'un envoi forgé qui la choisit est refusé');
		$this->assertTrue(AlertUi::getError('Staff::supervisor.owned') !== NULL, 'le refus a son message');
		$this->assertEquals(0, Staff::model()->whereCourse($eCourse)->count(), 'rien n\'est écrit');

		$this->assertEquals([\ViewAction::class, NULL], $this->setStatus($eSupervisorCarla, Supervisor::ACTIVE), 'Carla réactivée');
		$this->assertEquals([TRUE, TRUE], $this->isProposed($eCourse, $eSupervisorCarla), 'elle est proposée de nouveau');
		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorCarla), 'et se rattache');

	}

	/**
	 * L'autre côté : une personne désactivée déjà posée sur une formation y reste. Sa carte, la fiche,
	 * la feuille d'émargement et les quatre e-mails qui donnent le contact la nomment toujours.
	 */
	public function testKept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.statut-encadrant@example.org');

		$this->actAs($this->eUserAdmin);

		$eSupervisorAda = $this->createSupervisor('zzTest Ada Martin', 'ada@example.org', FALSE);

		$this->assertEquals([\ReloadAction::class, NULL], $this->pick($eCourse, $eSupervisorAda, contact: TRUE), 'Ada encadre la formation et en est le contact');
		$this->assertEquals([\ViewAction::class, NULL], $this->setStatus($eSupervisorAda, Supervisor::INACTIVE), 'puis elle est désactivée');

		$this->assertEquals([$eSupervisorAda['id']], StaffLib::getByCourse($eCourse)->getColumnCollection('supervisor')->getIds(), 'elle encadre toujours la formation');
		$this->assertEquals($eSupervisorAda['id'], SupervisorLib::getContactByCourse($eCourse)['id'], 'et en reste le contact');

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		if($this->assertTrue($data instanceof \stdClass, 'la fiche se lit') === FALSE) {
			return;
		}

		$details = new CourseUi()->getDetails($data->eCourse, $data->cStaff);

		$this->assertTrue(str_contains($details, 'zzTest Ada Martin'), 'la fiche la nomme : '.$details);
		$this->assertTrue(str_contains($details, 'mailto:ada@example.org'), 'et en contact');
		$this->assertFalse(str_contains($details, s("Désactivé")), 'sans dire son statut, qui ne regarde que le réseau');
		$this->assertTrue(str_contains(new CourseUi()->getAudience($data->eCourse, $data->cStaff), 'mailto:ada@example.org'), 'le panneau d\'inscription donne son adresse');

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		$this->assertTrue(str_contains(new StaffUi()->getEditionSection($data->eCourse, $data->cStaff), 'zzTest Ada Martin'), 'l\'onglet de l\'éditeur garde sa carte');

		$sheet = new SheetUi()->get(DateLib::getById($eDate['id']), DateEnrollmentLib::getConfirmedByDate($eDate), StaffLib::getByCourse($eCourse), \structure\StructureLib::getById($eCourse['community']), DateLib::getHalfDays($eDate), '2026-11-06');

		$this->assertTrue(str_contains($sheet, '<b>zzTest Ada Martin</b>'), 'la feuille d\'émargement lui garde sa ligne de signature');

		foreach($this->getMails($eDateEnrollment, $eDate) as $mail => $content) {
			$this->assertTrue(str_contains($content, $this->getSentence('zzTest Ada Martin', 'ada@example.org')), $mail.' la nomme toujours et donne son adresse');
		}

	}

	/**
	 * Le contact du réseau désactivé reste celui des formations qui n'ont désigné personne, qu'il les
	 * encadre ou non : sur la fiche et dans les e-mails.
	 */
	public function testNetworkContactKept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse();

		$eDate = $this->createDate($eCourse, 2);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.statut-encadrant@example.org');

		$this->actAs($this->eUserAdmin);

		$eSupervisorDiane = $this->createSupervisor('zzTest Diane Faure', 'diane@example.org', TRUE);

		$this->assertEquals([\ViewAction::class, NULL], $this->setStatus($eSupervisorDiane, Supervisor::INACTIVE), 'le contact du réseau se désactive');

		$eSupervisorDiane = SupervisorLib::getById($eSupervisorDiane['id']);

		$this->assertEquals([Supervisor::INACTIVE, TRUE], [$eSupervisorDiane['status'], $eSupervisorDiane['contact']], 'désactivé, il garde sa marque de contact');
		$this->assertEquals($eSupervisorDiane['id'], SupervisorLib::getContactByCommunity($this->eStructureNetwork)['id'], 'et reste le contact du réseau');

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		$this->assertTrue(str_contains(new CourseUi()->getDetails($data->eCourse, $data->cStaff), 'mailto:diane@example.org'), 'la fiche d\'une formation sans contact le donne toujours');

		foreach($this->getMails($eDateEnrollment, $eDate) as $mail => $content) {
			$this->assertTrue(str_contains($content, $this->getSentence('zzTest Diane Faure', 'diane@example.org')), $mail.' le nomme toujours et donne son adresse');
		}

	}

	/**
	 * Le geste de la ligne : toute l'équipe du réseau le joue, personne d'autre ; il n'écrit que le
	 * statut, refuse une valeur hors de l'énumération, et rend à sa vue la personne telle que sa ligne
	 * la dessine. Le statut n'est pas un champ de la fiche : le panneau de la personne ne peut pas
	 * l'écrire.
	 *
	 * La vue elle-même ne se rend pas ici : les vues du package partagent leurs noms (`create`,
	 * `update`), et deux fichiers de vues ne se chargent pas dans le même processus.
	 */
	public function testRoute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eSupervisorAda = $this->createSupervisor('zzTest Ada Martin', 'ada@example.org', FALSE);
		$eSupervisorBob = $this->createSupervisor('zzTest Bob Petit', '', FALSE);

		$this->assertFalse(in_array('status', SupervisorLib::getPropertiesCreate(), TRUE) or in_array('status', SupervisorLib::getPropertiesUpdate(), TRUE), 'le statut n\'est dans aucune liste de la fiche');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/supervisor:doUpdate', [
			'id' => (string)$eSupervisorAda['id'],
			'name' => 'zzTest Ada Martin',
			'role' => '',
			'description' => '',
			'email' => 'ada@example.org',
			'handicap' => '0',
			'contact' => '0',
			'status' => Supervisor::INACTIVE
		]), 'le panneau de la personne enregistre');
		$this->assertEquals(Supervisor::ACTIVE, $this->getStatus($eSupervisorAda), 'sans toucher au statut, même posté');

		$this->assertEquals([\FailAction::class, 'Supervisor::status.check'], $this->setStatus($eSupervisorAda, 'deleted'), 'un statut hors de l\'énumération est refusé');
		$this->assertTrue(AlertUi::getError('Supervisor::status.check') !== NULL, 'le refus a son message');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->setStatus($eSupervisorAda, Supervisor::INACTIVE), 'qui n\'est pas de l\'équipe du réseau ne désactive personne');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(Supervisor::ACTIVE, $this->getStatus($eSupervisorAda), 'rien n\'est écrit');

		$data = $this->getStatusData($eSupervisorAda, Supervisor::INACTIVE);

		$this->assertEquals(Supervisor::INACTIVE, $this->getStatus($eSupervisorAda), 'un conseiller du réseau, qui ne le gère pas, désactive');

		if($this->assertTrue($data instanceof \stdClass, 'le geste rend la personne à sa vue') === FALSE) {
			return;
		}

		$row = new SupervisorUi()->getRow($data->e);

		$this->assertEquals(0, $data->e['courses'], 'avec le nombre de ses formations, que la page a posé');
		$this->assertTrue(str_contains($row, 'id="supervisor-row-'.$eSupervisorAda['id'].'"'), 'sa ligne se redessine : '.$row);
		$this->assertTrue(str_contains($row, 'post-status="'.Supervisor::ACTIVE.'"'), 'et son interrupteur la réactive');
		$this->assertFalse(str_contains($row, 'supervisor-row-'.$eSupervisorBob['id']), 'sans sa voisine');

	}

	/**
	 * L'état, que la route pèse avec le droit (`acceptUpdate`, donc `Supervisor::acceptWrite()`) : dans
	 * un réseau dont les formations sont éteintes, personne ne se désactive ni ne se réactive, même par
	 * un membre de son équipe, dont le droit est intact.
	 */
	public function testState(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eSupervisorAda = $this->createSupervisor('zzTest Ada Martin', 'ada@example.org', FALSE);

		$this->setLearning(FALSE);

		$eSupervisorRead = SupervisorLib::getById($eSupervisorAda['id'], Supervisor::getSelection());

		$this->assertEquals([TRUE, FALSE], [$eSupervisorRead->canUpdate(), $eSupervisorRead->acceptUpdate()], 'formations éteintes, le droit du conseiller est intact et l\'état refuse');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->setStatus($eSupervisorAda, Supervisor::INACTIVE), 'la route refuse de désactiver');
		$this->assertEquals(Supervisor::ACTIVE, $this->getStatus($eSupervisorAda), 'rien n\'est écrit');

		$this->setLearning(TRUE);

		$this->assertEquals([\ViewAction::class, NULL], $this->setStatus($eSupervisorAda, Supervisor::INACTIVE), 'formations rallumées, elle désactive');
		$this->assertEquals(Supervisor::INACTIVE, $this->getStatus($eSupervisorAda), 'et le statut est écrit');

	}

	/**
	 * Allume ou éteint les formations du réseau du décor. Par le Lib et sans `build()` : son étape
	 * `enrolled` refuserait d'éteindre un réseau qui porte des inscriptions, et l'état éteint est
	 * celui que le test veut. Les fonctionnalités d'une structure sont gardées en statique de
	 * processus, vidée pour que la lecture suivante relise la colonne.
	 */
	private function setLearning(bool $value): void {

		$eFeature = \structure\FeatureLib::readByStructure($this->eStructureNetwork);
		$eFeature['hasCommunityLearning'] = $value;

		\structure\FeatureLib::update($eFeature, ['hasCommunityLearning']);

		$this->forgetMemoization();

	}

	/**
	 * Une personne du réseau par le panneau de sa liste. Rendue relue.
	 */
	private function createSupervisor(string $name, string $email, bool $contact): Supervisor {

		$result = $this->executePage('learning/supervisor:doCreate', [
			'community' => (string)$this->eStructureNetwork['id'],
			'name' => $name,
			'role' => 'formatrice',
			'description' => '',
			'email' => $email,
			'handicap' => '0',
			'contact' => $contact ? '1' : '0'
		]);

		$this->assertEquals([\ReloadAction::class, NULL], $result, $name.' ajoutée au réseau');

		return Supervisor::model()
			->select(Supervisor::getSelection())
			->whereCommunity($this->eStructureNetwork)
			->whereName($name)
			->get();

	}

	/**
	 * Le geste de l'interrupteur d'une ligne.
	 */
	private function setStatus(Supervisor $eSupervisor, string $status): array {

		return $this->executePage('learning/supervisor:doUpdateStatus', [
			'id' => (string)$eSupervisor['id'],
			'status' => $status
		]);

	}

	private function getStatus(Supervisor $eSupervisor): string {

		return Supervisor::model()
			->select('status')
			->whereId($eSupervisor['id'])
			->get()['status'];

	}

	/**
	 * @return array{0: bool, 1: bool} la personne est-elle proposée à la formation, par le Lib puis
	 * parmi les options du panneau d'ajout
	 */
	private function isProposed(Course $eCourse, Supervisor $eSupervisor): array {

		$data = $this->executeView('learning/staff:create', ['course' => (string)$eCourse['id']]);

		return [
			SupervisorLib::getForCourse($this->getCourse($eCourse['id']))->offsetExists($eSupervisor['id']),
			str_contains(new StaffUi()->create($data->e, $data->eSupervisor)->body, '<option value="'.$eSupervisor['id'].'"')
		];

	}

	/**
	 * L'envoi du chemin « Encadrant du réseau » du panneau d'ajout.
	 */
	private function pick(Course $eCourse, Supervisor $eSupervisor, bool $contact = FALSE): array {

		return $this->executePage('learning/staff:doCreate', [
			'course' => (string)$eCourse['id'],
			'source' => 'pick',
			'supervisor' => (string)$eSupervisor['id'],
			'role' => 'formatrice',
			'contact' => $contact ? '1' : '0'
		]);

	}

	private function getSentence(string $name, string $email): string {
		return 'écrivez à '.encode($name).' à l\'adresse '.\main\MailSoftwareAiUi::linkTag('mailto:'.$email).encode($email).'</a>';
	}

	/**
	 * Les quatre e-mails qui donnent le contact (`DateEnrollmentContactTest`), rendus sur la ligne
	 * relue à chaque fois. L'annulation est posée sur l'élément lu, rien n'est écrit pour elle.
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
			$contents[$mail] = implode(' ', $sent[0]['content']);
		}

		return $contents;

	}

	/**
	 * Le geste de l'interrupteur, et ce que la page passe à sa vue, ou le nom de l'action levée.
	 */
	private function getStatusData(Supervisor $eSupervisor, string $status): \stdClass|string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		$input = ['id' => (string)$eSupervisor['id'], 'status' => $status];

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'learning/supervisor:doUpdateStatus'), $data);
			return 'aucune action';
		} catch(\ViewAction) {
			return $data;
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

}
?>
