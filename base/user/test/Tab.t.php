<?php
namespace user;

/**
 * Les identités d'onglet, sur un poste de dev qui les a ouvertes par son secret : les comptes du
 * décor que propose `/user/admin/agent`, le jeton par lequel le compte choisi passe à l'onglet, et le cookie qui marque le navigateur.
 * Et le jeton qui rend le compte du poste à la session du navigateur.
 *
 * Lancement : dev/test package=user file=Tab
 */
class TabTest extends \dev\TransactionTest {

	/** Ce que la connexion d'un onglet écrit en session, et que le test rend en fin de cas. */
	const SESSION_KEYS = ['user', 'userLoggedAt', 'userDeletedAt', TabLib::SESSION];

	private array $sessionInitial = [];

	private User $eUserWorkstationInitial;

	public function setUp(): void {

		parent::setUp();

		$this->eUserWorkstationInitial = TabLib::getWorkstationUser();

		foreach(self::SESSION_KEYS as $key) {
			if(\session\SessionLib::exists($key)) {
				$this->sessionInitial[$key] = \session\SessionLib::get($key);
			}
		}

	}

	public function tearDown(): void {

		unset($_COOKIE[TabLib::COOKIE]);

		TabLib::setWorkstationUser($this->eUserWorkstationInitial);

		foreach(self::SESSION_KEYS as $key) {
			\session\SessionLib::delete($key);
		}

		foreach($this->sessionInitial as $key => $value) {
			\session\SessionLib::set($key, $value);
		}

		parent::tearDown();

	}

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	/** La page connecte sans mot de passe : un compte réel ajouté au décor y serait pris par n'importe quel agent. */
	public function testFixtureAccountsAreFictitious(): void {

		$real = array_filter(
			array_keys(\structure\FixtureAiLib::USERS),
			fn(string $email) => str_ends_with($email, '@example.org') === FALSE
		);

		$this->assertEquals([], array_values($real), 'compte du décor hors du domaine réservé example.org : '.implode(', ', $real));

	}

	public function testOnlyListedEmailsAreReturned(): void {

		$eUserListed = $this->createUser('listed');
		$eUserOther = $this->createUser('other');

		$cUser = TabLib::getByEmails([$eUserListed['email']]);

		$this->assertEquals([$eUserListed['id']], $cUser->getIds(), 'seul le compte dont l\'adresse est listée est rendu');
		$this->assertTrue($cUser->offsetExists($eUserListed['id']), 'la collection est indexée par compte, comme la page la lit');
		$this->assertFalse($cUser->offsetExists($eUserOther['id']), 'un compte hors liste ne peut pas être choisi');
		$this->assertTrue($cUser[$eUserListed['id']]['cMember']->empty(), 'un compte sans structure n\'a aucune adhésion');

	}

	public function testLoginTokenConnectsOnce(): void {

		$eUser = $this->createUser('token');
		$token = TabLib::createLoginToken($eUser);

		$this->assertEquals(1, preg_match('/^[0-9a-f]{32}$/', $token), 'le jeton est un aléa de 128 bits');

		$fw = new \FailWatch();

		$this->assertTrue(TabLib::logInByToken($token), 'le jeton émis par l\'administrateur connecte l\'onglet');
		$this->assertEquals($eUser['id'], \session\SessionLib::get('user'), 'l\'onglet porte le compte choisi par l\'administrateur');
		$this->assertFalse($fw->ko(), 'aucun échec sur le jeton valide');

		$this->assertFalse(TabLib::logInByToken($token), 'le même jeton ne connecte pas une seconde fois');
		$this->assertTrue($fw->has('User::tabToken'), 'le refus est nommé, et son message vit dans Alert.u.php');

		TabLib::logOut($eUser);

	}

	public function testLoginTokenRefusesClosedAccount(): void {

		$eUser = $this->createUser('closed', User::CLOSED);
		$token = TabLib::createLoginToken($eUser);

		$fw = new \FailWatch();

		$this->assertFalse(TabLib::logInByToken($token), 'un compte fermé ne se prend pas, même par un jeton valide');
		$this->assertFalse(\session\SessionLib::exists(TabLib::SESSION), 'aucune session d\'onglet n\'est ouverte');
		$this->assertTrue($fw->has('User::connectionInvalid'), 'le refus est celui de la connexion ordinaire');

	}

	public function testLoginTokenRefusesForgery(): void {

		$fw = new \FailWatch();

		$this->assertFalse(TabLib::logInByToken(''), 'sans jeton, aucun compte');
		$this->assertFalse(TabLib::logInByToken(str_repeat('a', 32)), 'un jeton de la bonne forme mais jamais émis ne connecte rien');
		$this->assertFalse(TabLib::logInByToken('../'.str_repeat('a', 29)), 'un jeton mal formé ne forme aucune clé');
		$this->assertFalse(\session\SessionLib::exists(TabLib::SESSION), 'aucune session d\'onglet n\'est ouverte');
		$this->assertTrue($fw->has('User::tabToken'), 'le refus est nommé');

	}

	public function testBrowserTokenConnectsOnce(): void {

		$eUser = $this->createUser('workstation');
		TabLib::setWorkstationUser($eUser);

		$token = TabLib::createBrowserToken(TabLib::getWorkstationUser());

		$fw = new \FailWatch();

		$this->assertTrue(TabLib::logInBrowserByToken($token), 'le jeton émis par la commande reconnecte le navigateur');
		$this->assertEquals($eUser['id'], \session\SessionLib::get('user'), 'la session porte le compte du poste');
		$this->assertFalse(\session\SessionLib::exists(TabLib::SESSION), 'la session du navigateur n\'est pas une session d\'onglet');
		$this->assertFalse(cookie_exists(TabLib::COOKIE), 'le cookie des onglets n\'est pas touché');
		$this->assertFalse($fw->ko(), 'aucun échec sur le jeton valide');

		$this->assertFalse(TabLib::logInBrowserByToken($token), 'le même jeton ne reconnecte pas une seconde fois');
		$this->assertTrue($fw->has('User::browserToken'), 'le refus est nommé, et son message vit dans Alert.u.php');

		ConnectionLib::logOut($eUser);

	}

	/** Un compte du décor pris par un agent pour son onglet ne devient jamais celui de tout le navigateur. */
	public function testTokensDoNotCross(): void {

		$eUser = $this->createUser('cross');

		$fw = new \FailWatch();

		$this->assertFalse(TabLib::logInBrowserByToken(TabLib::createLoginToken($eUser)), 'le jeton d\'un onglet ne reconnecte pas le navigateur');
		$this->assertFalse(TabLib::logInByToken(TabLib::createBrowserToken($eUser)), 'le jeton du navigateur ne connecte pas un onglet');
		$this->assertFalse(\session\SessionLib::exists('user'), 'aucun compte n\'est pris');

		$this->assertTrue($fw->has('User::browserToken'), 'le refus du navigateur est nommé');
		$this->assertTrue($fw->has('User::tabToken'), 'le refus de l\'onglet est nommé');

	}

	public function testBrowserTokenRefusesClosedAccount(): void {

		$eUser = $this->createUser('closed', User::CLOSED);
		$token = TabLib::createBrowserToken($eUser);

		$fw = new \FailWatch();

		$this->assertFalse(TabLib::logInBrowserByToken($token), 'un compte du poste fermé ne se reprend pas');
		$this->assertTrue($fw->has('User::connectionInvalid'), 'le refus est celui de la connexion ordinaire');

	}

	public function testLastTabToQuitClearsTheBrowserFlag(): void {

		$eUserFirst = $this->createUser('first');
		$eUserSecond = $this->createUser('second');

		$this->assertTrue(TabLib::logIn($eUserFirst), 'un compte du décor se connecte');
		$tokenFirst = \session\SessionLib::get(TabLib::SESSION);

		// Un autre onglet du même navigateur, dont la session ne porte pas le jeton du premier
		\session\SessionLib::delete(TabLib::SESSION);

		TabLib::logIn($eUserSecond);
		$tokenSecond = \session\SessionLib::get(TabLib::SESSION);

		$this->assertEquals($tokenFirst.'.'.$tokenSecond, $_COOKIE[TabLib::COOKIE], 'chaque onglet connecté ajoute son jeton au cookie du navigateur');

		TabLib::logOut($eUserSecond);

		$this->assertEquals($tokenFirst, $_COOKIE[TabLib::COOKIE], 'un onglet qui se déconnecte retire son seul jeton, l\'autre onglet garde le navigateur marqué');
		$this->assertFalse(\session\SessionLib::exists(TabLib::SESSION), 'la session de l\'onglet ne garde plus de jeton');

		// Retour au premier onglet
		\session\SessionLib::set(TabLib::SESSION, $tokenFirst);
		TabLib::logOut($eUserFirst);

		$this->assertFalse(cookie_exists(TabLib::COOKIE), 'le dernier onglet connecté qui se déconnecte efface le cookie');

	}

	public function testSwitchingAccountKeepsTheToken(): void {

		TabLib::logIn($this->createUser('first'));
		$token = \session\SessionLib::get(TabLib::SESSION);

		$eUserSecond = $this->createUser('second');
		TabLib::logIn($eUserSecond);

		$this->assertEquals($token, \session\SessionLib::get(TabLib::SESSION), 'l\'onglet qui change de compte garde son jeton, bien que la connexion vide sa session');
		$this->assertEquals($token, $_COOKIE[TabLib::COOKIE], 'le cookie ne garde aucun jeton orphelin');

		TabLib::logOut($eUserSecond);

		$this->assertFalse(cookie_exists(TabLib::COOKIE), 'la déconnexion de l\'onglet efface le cookie');

	}

	public function testUnknownValueIsCleared(): void {

		// La valeur que posait le navigateur avant les jetons
		$_COOKIE[TabLib::COOKIE] = '1';

		TabLib::logIn($this->createUser('first'));
		$token = \session\SessionLib::get(TabLib::SESSION);

		$this->assertEquals($token, $_COOKIE[TabLib::COOKIE], 'une valeur d\'une autre forme ne retient aucun onglet');

	}

	public function testCookieKeepsTheLastTokens(): void {

		// Des onglets fermés sans se déconnecter : plus de jetons que le cookie n'en garde
		$tokensLeft = array_map(fn(int $position) => sprintf('%016x', $position), range(1, TabLib::TOKENS_MAX + 5));
		$_COOKIE[TabLib::COOKIE] = implode('.', $tokensLeft);

		TabLib::logIn($this->createUser('first'));
		$token = \session\SessionLib::get(TabLib::SESSION);

		$tokens = explode('.', $_COOKIE[TabLib::COOKIE]);

		$this->assertEquals(TabLib::TOKENS_MAX, count($tokens), 'le cookie ne dépasse pas son plafond de jetons');
		$this->assertEquals($token, end($tokens), 'le jeton de l\'onglet qui vient de se connecter est gardé');
		$this->assertFalse(in_array($tokensLeft[0], $tokens), 'les jetons les plus anciens sortent les premiers');
		$this->assertTrue(in_array(end($tokensLeft), $tokens), 'les jetons les plus récents restent');

		TabLib::logIn($this->createUser('second'));

		$this->assertEquals($tokens, explode('.', $_COOKIE[TabLib::COOKIE]), 'un onglet qui se reconnecte ne fait sortir aucun autre jeton');

	}

	public function testQuitWithoutTokenKeepsOtherTabs(): void {

		$_COOKIE[TabLib::COOKIE] = str_repeat('a', 16);

		TabLib::logOut(new User());

		$this->assertEquals(str_repeat('a', 16), $_COOKIE[TabLib::COOKIE], 'un onglet sans jeton, jamais connecté ou dont la session a expiré, ne retire rien aux autres');

	}

	private function createUser(string $name, string $status = User::ACTIVE): User {

		// Sans build(), qui contrôlerait la forme et l'unicité de l'adresse : le test la pose lui-même, neuve et en example.org, et rien ne vient d'une saisie
		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => $name,
			'email' => 'zztest.tab.'.$name.'.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE,
			'status' => $status
		]);

		UserLib::create($eUser);

		return $eUser;

	}

}
?>
