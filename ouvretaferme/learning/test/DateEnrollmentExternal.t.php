<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * L'inscription fermée sur Ouvretaferme (lot annuaire) : une formation en présentiel ou en visio qui
 * envoie ses nouvelles inscriptions sur le site de l'organisme n'en reçoit plus aucune ici, par aucun
 * des chemins où une inscription peut naître :
 * - le panneau du membre, ses trois étapes (`create`, `doCommit`, `doCreate`) ;
 * - la page publique du réseau, sa page d'une date et ses deux envois (`doCommit`, `doCreate`) ;
 * - l'écriture sous le verrou, `DateEnrollmentLib::create()` et `createPublic()`, pour un élément
 *   chargé avant la fermeture (un onglet resté ouvert).
 *
 * Chaque chemin est joué deux fois sur la même date : inscription ouverte d'abord, où il doit
 * aboutir, ce qui prouve que le test sait trouver une inscription ; puis fermée, où il doit être
 * refusé sans écrire de ligne. Les inscriptions déjà ouvertes restent gérées ici : le réseau les
 * confirme et la personne les annule.
 *
 * La page publique d'une date fermée ne rend pas une 404 : hors intégration, elle redirige en 302
 * (jamais 301, que le navigateur garderait après une réouverture) vers l'adresse de la date, sinon
 * de la formation ; intégrée au site du réseau, elle rend l'étape 1 avec le lien sortant, plutôt que
 * d'emmener l'iframe sur un site qui peut refuser l'encadrement.
 *
 * Lancement : dev/test package=learning file=DateEnrollmentExternal
 */
class DateEnrollmentExternalTest extends LearningDateFixtureTest {

	const COURSE_URL = 'https://formations.example.org/zztest-formation';
	const DATE_URL = 'https://formations.example.org/zztest-date';

	/**
	 * La dernière `ViewAction` de `call()`, pour rendre les étapes de la page publique.
	 */
	private ?\stdClass $viewData = NULL;

	public function testMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		// Le panneau de la fiche ne s'ouvre qu'à qui la lit : retenue au catalogue public, la
		// formation se lit sans date confirmée, l'ouverture aux membres du décor ne donnant pas la
		// lecture.
		$eCourse['catalog'] = Course::ACCEPTED;
		CourseLib::updateCatalog($eCourse);

		$eDate = $this->createDate($eCourse, 10);

		// Ouverte : chaque étape du panneau aboutit, et la dernière écrit la ligne.
		$eUserOpen = $this->createPerson();

		$this->actAs($eUserOpen);
		$this->assertEquals(\ViewAction::class, $this->executePage('learning/dateEnrollment:create', ['date' => (string)$eDate['id']], 'GET')[0], 'ouverte, le panneau s\'ouvre');
		$this->assertEquals(\ViewAction::class, $this->executePage('learning/dateEnrollment:doCommit', $this->getMemberInput($eDate))[0], 'ouverte, l\'étape 2 passe');
		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/dateEnrollment:doCreate', $this->getMemberInput($eDate))[0], 'ouverte, l\'inscription s\'envoie');
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUserOpen)->isOpen(), 'ouverte, la ligne est écrite');

		$this->close($eCourse);

		// Fermée : les trois étapes refusent, pour une personne qui n'a pas de ligne.
		$eUserClosed = $this->createPerson();

		$this->actAs($eUserClosed);
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->executePage('learning/dateEnrollment:create', ['date' => (string)$eDate['id']], 'GET'), 'fermée, le panneau refuse');
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->executePage('learning/dateEnrollment:doCommit', $this->getMemberInput($eDate)), 'fermée, l\'étape 2 refuse');
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->executePage('learning/dateEnrollment:doCreate', $this->getMemberInput($eDate)), 'fermée, l\'envoi refuse');
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUserClosed)->empty(), 'fermée, aucune ligne écrite');

	}

	/**
	 * La ligne ouverte avant la fermeture reste gérée ici : le réseau la confirme, la personne l'annule.
	 */
	public function testOngoing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$eUserA = $this->createPerson();
		$eUserB = $this->createPerson();

		$eDateEnrollmentA = $this->enrollRequested($eDate, $eUserA);
		$eDateEnrollmentB = $this->enrollRequested($eDate, $eUserB);

		$this->close($eCourse);

		$this->assertEquals(\ReloadAction::class, $this->executePage('learning/dateEnrollment:doConfirm', ['id' => (string)$eDateEnrollmentA['id']])[0], 'fermée, le réseau confirme une demande en cours');
		$this->assertEquals(DateEnrollment::CONFIRMED, $this->getLine($eDateEnrollmentA['id'])['status'], 'la demande est confirmée');

		$this->actAs($eUserB);
		$this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$eDateEnrollmentB['id']]);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(DateEnrollment::CANCELED, $this->getLine($eDateEnrollmentB['id'])['status'], 'fermée, la personne annule sa demande en cours');

		// Annulée, la ligne ne se rouvre plus ici.
		$this->actAs($eUserB);
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->executePage('learning/dateEnrollment:doCreate', $this->getMemberInput($eDate)), 'fermée, une ligne annulée ne se rouvre pas');
		$this->actAs($this->eUserAdmin);

	}

	public function testPublic(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$path = $this->getPath().'/formations/'.$eDate['id'];

		// Sans compte : un compte qui lit la formation est renvoyé à sa fiche avant toute étape.
		$this->actAs(new \user\User());

		// Ouverte : l'étape 1 se lit, les deux envois aboutissent et le second écrit la ligne.
		$this->assertEquals([\ViewAction::class, NULL, NULL], $this->call('GET', $path), 'ouverte, l\'étape 1 s\'ouvre');
		$this->assertEquals(\RedirectAction::class, $this->call('POST', $path.'/:doCommit', $this->getPublicInput('zztest.ouverte@example.org'))[0], 'ouverte, l\'étape 2 mène à la troisième');
		$this->assertEquals([\ViewAction::class, NULL, NULL], $this->call('POST', $path.'/:doCreate', $this->getPublicInput('zztest.ouverte@example.org')), 'ouverte, l\'inscription s\'envoie');

		$this->assertTrue($this->getLineByEmail($eDate, 'zztest.ouverte@example.org')->notEmpty(), 'ouverte, la ligne est écrite');

		$this->actAs($this->eUserAdmin);
		$this->close($eCourse);
		$this->actAs(new \user\User());

		// Fermée : la page d'une date mène au site de l'organisme, en 302, à l'adresse de la formation
		// puis à celle de la date quand elle en a une.
		$this->assertEquals([\RedirectAction::class, self::COURSE_URL, 'HTTP/1.1 302 Found'], $this->call('GET', $path), 'fermée, la page d\'une date redirige en 302 vers l\'adresse de la formation');

		$eDateWithUrl = DateLib::getById($eDate['id']);
		$eDateWithUrl['externalUrl'] = self::DATE_URL;
		DateLib::update($eDateWithUrl, ['externalUrl']);

		$this->assertEquals([\RedirectAction::class, self::DATE_URL, 'HTTP/1.1 302 Found'], $this->call('GET', $path), 'l\'adresse de la date prime');

		// Intégrée : l'étape 1, sans étape suivante, avec le lien sortant.
		$this->assertEquals([\ViewAction::class, NULL, NULL], $this->call('GET', $path, ['embed' => '1']), 'intégrée, la page d\'une date ne redirige pas l\'iframe');

		$html = $this->getStepsHtml();

		$this->assertTrue(str_contains($html, 'href="'.self::DATE_URL.'"') and str_contains($html, 'target="_blank"'), 'intégrée, l\'étape 1 porte le lien sortant dans un nouvel onglet : '.$html);
		$this->assertFalse(str_contains($html, 'step=2'), 'intégrée, aucune étape suivante n\'est proposée');

		// Les deux envois refusent, et rien ne s'écrit.
		$this->assertEquals([\NotExpectedAction::class, NULL, NULL], $this->call('POST', $path.'/:doCommit', $this->getPublicInput('zztest.fermee@example.org')), 'fermée, l\'étape 2 refuse');
		$this->assertEquals([\NotExpectedAction::class, NULL, NULL], $this->call('POST', $path.'/:doCreate', $this->getPublicInput('zztest.fermee@example.org')), 'fermée, l\'envoi refuse');

		$this->assertTrue($this->getLineByEmail($eDate, 'zztest.fermee@example.org')->empty(), 'fermée, aucune ligne écrite');

		// La ligne ouverte avant la fermeture se lit toujours par son jeton.
		$eDateEnrollmentOpen = $this->getLineByEmail($eDate, 'zztest.ouverte@example.org');
		$this->assertEquals([\ViewAction::class, NULL, NULL], $this->call('GET', $this->getPath().'/inscription/'.$eDateEnrollmentOpen['token']), 'la ligne en cours se lit par son jeton');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * La pause (lot T13) sur la page publique d'une date : l'étape 1 seule se sert, sans étape
	 * suivante par son adresse, et les deux envois refusent avec le message de la pause. Elle prime
	 * sur le site de l'organisme, vers lequel la page ne redirige plus, et la reprise y remène.
	 */
	public function testPause(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$path = $this->getPath().'/formations/'.$eDate['id'];
		$commitments = ['presence', 'cancel', 'conditions'];

		$this->pause($eCourse, TRUE);
		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL, NULL], $this->call('GET', $path, ['step' => '3', 'commitment' => $commitments]), 'en pause, la page d\'une date se lit');
		$this->assertEquals(1, $this->viewData->step, 'en pause, l\'étape 3 demandée par l\'adresse rend l\'étape 1');

		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::paused', NULL], $this->call('POST', $path.'/:doCommit', $this->getPublicInput('zztest.pause@example.org')), 'en pause, l\'étape 2 refuse avec le message de la pause');
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::paused', NULL], $this->call('POST', $path.'/:doCreate', $this->getPublicInput('zztest.pause@example.org')), 'en pause, l\'envoi refuse avec le message de la pause');

		$this->assertTrue($this->getLineByEmail($eDate, 'zztest.pause@example.org')->empty(), 'en pause, aucune ligne écrite');

		$this->actAs($this->eUserAdmin);
		$this->close($eCourse);
		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL, NULL], $this->call('GET', $path), 'en pause, la page d\'une formation sur le site de l\'organisme ne redirige pas');
		$this->assertEquals(1, $this->viewData->step, 'elle rend l\'étape 1');

		$this->actAs($this->eUserAdmin);
		$this->pause($eCourse, FALSE);
		$this->actAs(new \user\User());

		$this->assertEquals([\RedirectAction::class, self::COURSE_URL, 'HTTP/1.1 302 Found'], $this->call('GET', $path), 'rouverte, la page redirige de nouveau vers le site de l\'organisme');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * L'élément chargé avant la fermeture, écrit après : le contrôle sous le verrou relit la formation
	 * en base, et refuse. C'est l'onglet resté ouvert pendant que le réseau ferme.
	 */
	public function testLock(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		// Ouverte, les deux écritures aboutissent.
		$eUserOpen = $this->createPerson();

		$this->assertTrue($this->writeMember($eDate, $eUserOpen)->ok(), 'ouverte, create() écrit');
		$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUserOpen)->isOpen(), 'ouverte, la ligne du membre est écrite');

		$this->assertTrue($this->writePublic($eDate, 'zztest.verrou-ouverte@example.org')->ok(), 'ouverte, createPublic() écrit');
		$this->assertTrue($this->getLineByEmail($eDate, 'zztest.verrou-ouverte@example.org')->notEmpty(), 'ouverte, la ligne publique est écrite');

		// Les éléments se chargent ouverts, la formation se ferme, puis ils s'écrivent.
		$eUserClosed = $this->createPerson();

		$eDateEnrollment = $this->prepareMember($eDate, $eUserClosed);
		$eDateEnrollmentPublic = $this->preparePublic($eDate, 'zztest.verrou-fermee@example.org');

		$this->close($eCourse);

		$fw = new \FailWatch();
		DateEnrollmentLib::create($eDateEnrollment);
		$this->assertEquals([['DateEnrollment::closed', NULL]], $this->getFails($fw), 'fermée, create() refuse sous le verrou');

		$this->assertTrue(DateEnrollmentLib::getByDateAndUser($eDate, $eUserClosed)->empty(), 'fermée, aucune ligne du membre');

		$fw = new \FailWatch();
		DateEnrollmentLib::createPublic($eDateEnrollmentPublic);
		$this->assertEquals([['DateEnrollment::closed', NULL]], $this->getFails($fw), 'fermée, createPublic() refuse sous le verrou');

		$this->assertTrue($this->getLineByEmail($eDate, 'zztest.verrou-fermee@example.org')->empty(), 'fermée, aucune ligne publique');

	}

	/**
	 * La formation fermée par son panneau, à l'adresse de la formation.
	 */
	private function close(Course $eCourse): void {

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/course:doUpdateExternal', [
			'id' => (string)$eCourse['id'],
			'externalOnly' => '1',
			'externalUrl' => self::COURSE_URL,
		]), 'la formation se ferme à l\'inscription');

	}

	private function pause(Course $eCourse, bool $paused): void {

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/course:doUpdateEnrollmentPaused', [
			'id' => (string)$eCourse['id'],
			'enrollmentPaused' => $paused ? '1' : '0',
		]), $paused ? 'le réseau met la formation en pause' : 'le réseau rouvre la formation');

	}

	private function getMemberInput(Date $eDate): array {

		return [
			'date' => (string)$eDate['id'],
			'commitment' => ['presence', 'cancel', 'conditions'],
			'structure' => '',
			'trainee' => DateEnrollment::FARMER,
		];

	}

	private function getPublicInput(string $email): array {

		return [
			'firstName' => 'Zztest',
			'lastName' => 'Public',
			'email' => $email,
			'structureName' => 'zzTest structure',
			'trainee' => DateEnrollment::FARMER,
			'commitment' => ['presence', 'cancel', 'conditions'],
		];

	}

	/**
	 * Les deux éléments sont remplis sans `build()` : il aurait vérifié les engagements et l'identité
	 * postés, que `testMember()` et `testPublic()` éprouvent par les pages. Ici, seul compte ce que
	 * `create()` et `createPublic()` relisent sous le verrou.
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

	private function writeMember(Date $eDate, \user\User $eUser): \FailWatch {

		$fw = new \FailWatch();
		DateEnrollmentLib::create($this->prepareMember($eDate, $eUser));

		return $fw;

	}

	private function writePublic(Date $eDate, string $email): \FailWatch {

		$fw = new \FailWatch();
		DateEnrollmentLib::createPublic($this->preparePublic($eDate, $email));

		return $fw;

	}

	private function getLineByEmail(Date $eDate, string $email): DateEnrollment {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereEmail($email)
			->get();

	}

	private function getPath(): string {
		return 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'];
	}

	private function getStepsHtml(): string {

		$data = $this->viewData;

		$eStructureCommunity = \structure\StructureLib::getById($this->eStructureNetwork['id']);
		$ui = new DatePublicUi($eStructureCommunity, \structure\CommunityLib::getByStructure($eStructureCommunity), TRUE);

		return $ui->getSteps($data->e, $data->step, $data->status, $data->commitments, $data->cChapter, $data->cStaff, $data->online);

	}

	/**
	 * La route jouée par son adresse, comme `DatePublic.t.php` la joue, avec en plus le statut HTTP
	 * d'une redirection : un 301 se garde dans le navigateur, qui ne reverrait pas une réouverture.
	 *
	 * @return array{0: string, 1: ?string, 2: ?string}
	 */
	private function call(string $method, string $path, array $input = []): array {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = match($method) {
			'GET' => [$input, [], $input],
			'POST' => [[], $input, $input],
		};

		$this->viewData = NULL;

		try {

			$pages = \Route::getCompatiblePages($path);

			if($pages === []) {
				return ['aucune route', NULL, NULL];
			}

			$data = new \stdClass();
			$data->eUserOnline = \user\ConnectionLib::getOnline();
			$data->logInExternal = NULL;

			\Page::execute($pages, $data);
			return ['aucune action', NULL, NULL];

		} catch(\ViewAction $action) {
			$this->viewData = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
			return [\ViewAction::class, new \ReflectionProperty(\ViewAction::class, 'path')->getValue($action), NULL];
		} catch(\RedirectAction $action) {
			return [\RedirectAction::class, new \ReflectionProperty($action, 'url')->getValue($action), new \ReflectionProperty($action, 'httpStatus')->getValue($action)];
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return [\FailAction::class, is_string($fail) ? $fail : implode(', ', array_column($fail->format(), 0)), NULL];
		} catch(\Action $action) {
			return [get_class($action), NULL, NULL];
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
			$this->actAs(\user\ConnectionLib::getOnline());
		}

	}

}
?>
