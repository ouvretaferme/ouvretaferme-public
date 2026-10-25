<?php
namespace structure;

/**
 * Qui peut créer quel groupe, et ce qu'un compte sans structure se voit proposer (lot MR1 du
 * chantier e-learning) :
 * 1. `Structure::acceptCreateType()` juge un groupe sur son `communityType` et sur les rôles du
 *    propriétaire : la formatrice crée un groupe de formation et un réseau, le conseiller une
 *    communauté et un réseau, le producteur une communauté, et personne un groupe comptable ;
 * 2. le serveur l'applique aux pages `/demarrer/*` et à la création postée, `communityType` compris ;
 * 3. l'accueil d'un compte sans structure propose le code d'invitation, quel que soit le rôle.
 *
 * Décor : les comptes de `FixtureAiLib::USERS`, lus et jamais écrits. Un rôle de plus se pose sur
 * l'élément du compte connecté, pas en base. Aucune création n'aboutit : une création permise
 * s'arrête au nom manquant, avant le `CREATE DATABASE` de `StructureLib::create()`.
 *
 * Lancement : dev/test package=structure file=CommunityCreateAi
 */
class CommunityCreateAiTest extends \Test {

	const EMAIL_TEACHER = 'decor.g2.enseignant@example.org';
	const EMAIL_CONSULTANT = 'decor.g3.conseiller@example.org';
	const EMAIL_PRODUCER = 'decor.f1.admin@example.org';

	/** Ce que le formulaire de création exige d'une boutique comme d'un groupe, hors du type */
	const LEGAL = [
		'legalEmail' => 'zztest.mr1@example.org',
		'legalCountry' => \user\UserSetting::FR,
	];

	public function tearDown(): void {

		\user\ConnectionLib::setOnline(new \user\User());
		MemberLib::clearCache();

	}

	public function testAcceptCreateType(): void {

		$cases = [
			'roleTeacher' => [Structure::EDUCATION => TRUE, Structure::NETWORK => TRUE, Structure::PRODUCER => FALSE],
			'roleConsultant' => [Structure::EDUCATION => FALSE, Structure::NETWORK => TRUE, Structure::PRODUCER => TRUE],
			'roleProducer' => [Structure::EDUCATION => FALSE, Structure::NETWORK => FALSE, Structure::PRODUCER => TRUE],
		];

		foreach($cases as $role => $expected) {

			$eUser = $this->getUserWithRoles([$role]);

			foreach($expected as $communityType => $accept) {
				$this->assertEquals($accept, $this->getCommunity($eUser, $communityType)->acceptCreateType(), $role.($accept ? ' crée' : ' ne crée pas').' un groupe '.$communityType);
			}

			$this->assertFalse($this->getCommunity($eUser, Structure::ACCOUNTING)->acceptCreateType(), $role.' ne crée jamais un groupe comptable');
			$this->assertFalse($this->getCommunity($eUser, NULL)->acceptCreateType(), $role.' ne crée pas un groupe sans type');
			foreach([Structure::PRODUCER, Structure::SHOP] as $type) {
				$this->assertTrue(new Structure(['type' => $type, 'owner' => $eUser])->acceptCreateType(), $role.' : une structure '.$type.' ne se juge pas ici');
			}

		}

	}

	public function testDemarrer(): void {

		if($this->connect(self::EMAIL_TEACHER) === FALSE) {
			return;
		}

		$this->assertEquals(\ViewAction::class, $this->runPageClass('demarrer/reseau', [], 'GET'), 'la formatrice ouvre /demarrer/reseau');
		$this->assertEquals(\ViewAction::class, $this->runPageClass('demarrer/education', [], 'GET'), 'et /demarrer/education');
		$this->assertRefused($this->runPage('demarrer/communaute', [], 'GET'), 'mais pas /demarrer/communaute');

		if($this->connect(self::EMAIL_PRODUCER) === FALSE) {
			return;
		}

		$this->assertRefused($this->runPage('demarrer/reseau', [], 'GET'), 'le producteur n\'ouvre pas /demarrer/reseau');
		$this->assertEquals(\ViewAction::class, $this->runPageClass('demarrer/communaute', [], 'GET'), 'il ouvre /demarrer/communaute');

		if($this->connect(self::EMAIL_CONSULTANT) === FALSE) {
			return;
		}

		$this->assertRefused($this->runPage('demarrer/education', [], 'GET'), 'le conseiller n\'ouvre pas /demarrer/education');

	}

	public function testDoCreate(): void {

		$post = fn(?string $communityType) => ['type' => Structure::COMMUNITY] + ($communityType === NULL ? [] : ['communityType' => $communityType]);

		if($this->connect(self::EMAIL_TEACHER) === FALSE) {
			return;
		}

		// Sans nom, build() refuse : la création franchit la règle sans aller jusqu'à StructureLib::create()
		$this->assertEquals(\FailAction::class, $this->runPageClass('structure/structure:doCreate', $post(Structure::NETWORK), 'POST'), 'la formatrice franchit la règle pour un réseau');
		$this->assertRefused($this->runPage('structure/structure:doCreate', $post(Structure::PRODUCER), 'POST'), 'pas pour une communauté de producteurs');
		$this->assertRefused($this->runPage('structure/structure:doCreate', $post(NULL), 'POST'), 'ni pour un groupe sans type');

		if($this->connect(self::EMAIL_PRODUCER) === FALSE) {
			return;
		}

		$this->assertRefused($this->runPage('structure/structure:doCreate', $post(Structure::NETWORK), 'POST'), 'le producteur ne crée pas de réseau');

		if($this->connect(self::EMAIL_CONSULTANT) === FALSE) {
			return;
		}

		$this->assertEquals(\FailAction::class, $this->runPageClass('structure/structure:doCreate', $post(Structure::NETWORK), 'POST'), 'le conseiller franchit la règle pour un réseau');
		$this->assertRefused($this->runPage('structure/structure:doCreate', $post(Structure::ACCOUNTING), 'POST'), 'personne ne crée un groupe comptable');

	}

	public function testCommunityTypeOutsideCommunity(): void {

		if($this->connect(self::EMAIL_PRODUCER) === FALSE) {
			return;
		}

		$eStructureShop = $this->buildCreateElement(['type' => Structure::SHOP, 'communityType' => Structure::NETWORK, 'name' => 'zzTest MR1 boutique'] + self::LEGAL);

		$this->assertFalse($eStructureShop->offsetExists('communityType'), 'une boutique postée avec communityType=network ne porte pas la clé : l\'insertion la laisse à NULL');

		if($this->connect(self::EMAIL_CONSULTANT) === FALSE) {
			return;
		}

		$eStructureNetwork = $this->buildCreateElement(['type' => Structure::COMMUNITY, 'communityType' => Structure::NETWORK, 'name' => 'zzTest MR1 réseau'] + self::LEGAL);

		$this->assertEquals(Structure::NETWORK, $eStructureNetwork['communityType'], 'un groupe garde le type posté');

	}

	public function testNoStructures(): void {

		$eStructureCreate = new Structure([
			'type' => Structure::PRODUCER,
			'legalCountry' => new \user\Country(['id' => \user\UserSetting::FR]),
			'isFirst' => TRUE,
			'canCreateAmap' => FALSE,
			'producerType' => NULL,
			'inviteKey' => NULL,
		]);

		foreach([['roleTeacher'], ['roleConsultant'], ['roleProducer'], ['roleProducer', 'roleTeacher'], ['roleConsultant', 'roleTeacher']] as $roles) {

			// En ligne comme sur l'accueil : le formulaire de création propose l'adresse du compte connecté
			$eUser = $this->getUserWithRoles($roles);
			\user\ConnectionLib::setOnline($eUser);

			$html = new MemberUi()->getNoStructures($eUser, $eStructureCreate);

			$this->assertTrue(str_contains($html, 'data-ajax-form="/invitation"'), implode(' et ', $roles).' : le code d\'invitation se poste à /invitation');

		}

	}

	private function assertRefused(?\Action $action, string $message): void {

		$this->assertEquals(\NotExpectedAction::class, $action === NULL ? NULL : get_class($action), $message);

		if($action instanceof \NotExpectedAction) {
			$this->assertTrue(str_contains(new \ReflectionProperty(\NotAction::class, 'wrong')->getValue($action) ?? '', 'acceptCreateType'), $message.', par la règle du type de structure');
		}

	}

	private function getCommunity(\user\User $eUser, ?string $communityType): Structure {

		return new Structure([
			'type' => Structure::COMMUNITY,
			'communityType' => $communityType,
			'owner' => $eUser,
		]);

	}

	private function getUserWithRoles(array $roles): \user\User {

		// Un rôle ne compte que sur un compte qui existe (`User::isRole()`) : l'identifiant n'est jamais lu en base
		$eUser = new \user\User([
			'id' => PHP_INT_MAX,
			'email' => 'zztest.mr1@example.org',
			'roleCustomer' => FALSE,
		]);

		foreach(['roleProducer', 'roleConsultant', 'roleTeacher'] as $role) {
			$eUser[$role] = in_array($role, $roles);
		}

		return $eUser;

	}

	/**
	 * Le compte du décor connecté, avec des rôles de plus posés sur l'élément seulement : les droits
	 * se lisent sur l'élément en ligne, et la base n'est pas écrite.
	 */
	private function connect(string $email, array $roles = []): bool {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

		if($this->assertDecorPresent($eUser->notEmpty(), 'le compte '.$email.' de FixtureAiLib::USERS') === FALSE) {
			return FALSE;
		}

		foreach($roles as $role) {
			$eUser[$role] = TRUE;
		}

		MemberLib::clearCache();
		\user\ConnectionLib::setOnline($eUser);

		return TRUE;

	}

	/**
	 * L'élément que `getCreateElement()` de `structure:doCreate` prépare, construit comme `doCreate()`
	 * le construit (`framework/core/Module.php`), sans aller jusqu'à `StructureLib::create()`, dont le
	 * `CREATE DATABASE` validerait toute transaction. La page jouée une fois sans nom désigne la
	 * `ModulePage`, dont la fabrique d'élément est lue telle que le produit l'appelle.
	 */
	private function buildCreateElement(array $input): Structure {

		$this->runPage('structure/structure:doCreate', array_diff_key($input, ['name' => TRUE]), 'POST');

		$page = new \ReflectionProperty(\Page::class, 'currentPage')->getValue();
		$create = new \ReflectionFunction($page['create'])->getStaticVariables()['create'];
		$modulePage = new \ReflectionFunction($create)->getClosureThis();
		$createElement = new \ReflectionProperty(\ModulePage::class, 'createElement')->getValue($modulePage);

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		$postInitial = $_POST;
		$_POST = $input;

		try {

			$eStructure = $createElement->call($modulePage, $data);
			$eStructure['id'] = NULL;

			$fw = new \FailWatch();

			$eStructure->build(StructureLib::getPropertiesCreate()($eStructure), $input, new \PropertyList('create'));

			$this->assertTrue($fw->ok(), 'build() accepte la création '.$input['type']);

		} finally {
			$_POST = $postInitial;
		}

		return $eStructure;

	}

	private function runPageClass(string $path, array $input, string $method): ?string {

		$action = $this->runPage($path, $input, $method);

		return ($action === NULL) ? NULL : get_class($action);

	}

	/**
	 * La page jouée comme `executePageAction()`, l'utilisateur connecté et ses structures posés sur
	 * `$data` comme la route HTTP les pose avant la page (`main\PageLib::common()`), que le lanceur de
	 * tests ne joue pas.
	 */
	private function runPage(string $path, array $input, string $method): ?\Action {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		$_REQUEST = $input;

		if($method === 'GET') {
			$_GET = $input;
		} else {
			$_POST = $input;
		}

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->ccStructureUser = StructureLib::getOnline();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

}
?>
