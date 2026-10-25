<?php
namespace user;

/**
 * Le lien de connexion reçu par e-mail (`LoginLinkAiLib`) : à qui il est émis, ce qu'il écrit, ce
 * qui le ferme, ce qu'il lève en connectant, et l'e-mail de mot de passe oublié qui le porte.
 *
 * Tout se joue dans la transaction de `TransactionTest`, annulée après chaque cas, la dev étant
 * partagée. Les comptes semés portent une adresse en `example.org`, où aucun envoi n'est délivré,
 * et le poste une adresse de test (TEST-NET-3). Le webhook Discord est vidé pour que rien ne parte.
 *
 * Les compteurs de la limitation vivent dans Redis : le cas qui vérifie le blocage levé se passe
 * quand le cache est désactivé, et le dit.
 *
 * Lancement : dev/test package=user file=LoginLinkAi
 */
class LoginLinkAiTest extends \dev\TransactionTest {

	const IP = '203.0.113.78';
	const PASSWORD = 'zzTestMotDePasseJuste42';

	private array $serverInitial;
	private array $adminInitial;
	private string $webhookInitial;

	private User $eUser;

	public function setUp(): void {

		$this->serverInitial = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = self::IP;

		$this->webhookInitial = \communication\CommunicationSetting::$discordSecurityWebhookUrl;
		\communication\CommunicationSetting::$discordSecurityWebhookUrl = '';

		$this->adminInitial = UserSetting::$admin;

		parent::setUp();

		$this->eUser = $this->createUser('lien');

		\session\SessionLib::clean();
		ConnectionLib::setOnline(new User());

		$this->clearThrottle();

	}

	public function tearDown(): void {

		$this->clearThrottle();

		\session\SessionLib::clean();
		ConnectionLib::setOnline(new User());

		parent::tearDown();

		UserSetting::$admin = $this->adminInitial;
		\communication\CommunicationSetting::$discordSecurityWebhookUrl = $this->webhookInitial;
		$_SERVER = $this->serverInitial;

	}

	protected function getTransactionModel(): \ModuleModel {
		return LoginLink::model();
	}

	private function clearThrottle(): void {

		ThrottleAiLib::clear('login', hash('sha256', $this->eUser['email']));
		ThrottleAiLib::clear('login-ip', self::IP);

	}

	private function createUser(string $name, bool $withPassword = TRUE): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => $name,
			'email' => 'zztest.loginlink.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		if($withPassword) {

			$eUser['auth'] = new UserAuth([
				'user' => $eUser,
				'type' => UserAuth::BASIC,
				'login' => $eUser['email'],
				'password' => password_hash(self::PASSWORD, PASSWORD_DEFAULT)
			]);

			SignUpLib::updatePassword($eUser);

		}

		return User::model()
			->select(User::getSelection())
			->whereId($eUser['id'])
			->get();

	}

	private function reload(LoginLink $e): LoginLink {

		return LoginLink::model()
			->select(LoginLink::getSelection())
			->whereId($e['id'])
			->get();

	}

	private function countLinks(User $eUser): int {

		return LoginLink::model()
			->whereUser($eUser)
			->count();

	}

	public function testOpenStoresTheTokenHashed(): void {

		$e = LoginLinkAiLib::open($this->eUser);

		$this->assertTrue($e->notEmpty(), 'un compte actif reçoit un lien');
		$this->assertEquals(ChallengeAiLib::TOKEN_BYTES * 2, strlen($e['clearToken']));

		$eLoginLinkStored = $this->reload($e);

		$this->assertEquals(hash('sha256', $e['clearToken']), $eLoginLinkStored['token'], 'la base ne porte que le hachage');
		$this->assertDiffer($e['clearToken'], $eLoginLinkStored['token']);
		$this->assertEquals(NULL, $eLoginLinkStored['closedAt']);
		$this->assertEquals(LoginLinkAiLib::LIFETIME, strtotime($eLoginLinkStored['expiresAt']) - strtotime($eLoginLinkStored['createdAt']), 'dix minutes');

	}

	public function testOpenRefusesAnAdministrator(): void {

		UserSetting::$admin[] = $this->eUser['id'];

		$this->assertTrue(LoginLinkAiLib::open($this->eUser)->empty(), 'b2 : aucun lien pour un administrateur');
		$this->assertEquals(0, $this->countLinks($this->eUser));

	}

	public function testOpenRefusesABannedAccount(): void {

		$eUserAdmin = $this->createUser('banisseur');

		$fw = new \FailWatch();
		BanLib::createBan($this->eUser, $eUserAdmin, ['user'], 'recette', 1, $fw);
		$this->assertTrue($fw->ok(), 'le bannissement du décor est posé');

		$this->assertTrue(LoginLinkAiLib::open($this->eUser)->empty(), 'aucun lien pour un compte banni');
		$this->assertEquals(0, $this->countLinks($this->eUser));

	}

	public function testOpenRefusesAnInactiveAccount(): void {

		$this->eUser['status'] = User::SUSPENDED;

		$this->assertTrue(LoginLinkAiLib::open($this->eUser)->empty(), 'aucun lien pour un compte suspendu');
		$this->assertEquals(0, $this->countLinks($this->eUser));

	}

	public function testOpenClosesThePreviousLinkAsResent(): void {

		$eLoginLinkFirst = LoginLinkAiLib::open($this->eUser);
		$eLoginLinkSecond = LoginLinkAiLib::open($this->eUser);

		$this->assertEquals(LoginLink::RESENT, $this->reload($eLoginLinkFirst)['closedBy'], 'un nouveau lien ferme le précédent');
		$this->assertEquals(NULL, $this->reload($eLoginLinkSecond)['closedAt']);
		$this->assertTrue(LoginLinkAiLib::getByToken($eLoginLinkFirst['clearToken'])->empty(), 'le premier jeton ne vaut plus rien');
		$this->assertTrue(LoginLinkAiLib::getByToken($eLoginLinkSecond['clearToken'])->notEmpty());

	}

	public function testGetByTokenRefusesWhatDoesNotRun(): void {

		$e = LoginLinkAiLib::open($this->eUser);

		$this->assertTrue(LoginLinkAiLib::getByToken(NULL)->empty());
		$this->assertTrue(LoginLinkAiLib::getByToken('abc')->empty(), 'jeton trop court');
		$this->assertTrue(LoginLinkAiLib::getByToken(str_repeat('z', ChallengeAiLib::TOKEN_BYTES * 2))->empty(), 'jeton non hexadécimal');
		$this->assertTrue(LoginLinkAiLib::getByToken(str_repeat('0', ChallengeAiLib::TOKEN_BYTES * 2))->empty(), 'jeton inconnu');

		$eLoginLinkFound = LoginLinkAiLib::getByToken($e['clearToken']);

		$this->assertEquals($e['id'], $eLoginLinkFound['id']);
		$this->assertEquals($this->eUser['id'], $eLoginLinkFound['user']['id']);
		$this->assertEquals(NULL, $this->reload($e)['closedAt'], 'lire le lien, comme la page du GET, ne le consomme pas');

		// Un lien expiré ne se fabrique pas par le Lib, qui pose toujours dix minutes
		LoginLink::model()
			->whereId($e['id'])
			->update(['expiresAt' => new \Sql('NOW() - INTERVAL 1 SECOND')]);

		$this->assertTrue(LoginLinkAiLib::getByToken($e['clearToken'])->empty(), 'un lien expiré ne vaut plus');

	}

	public function testSucceedLogsInAndServesOnce(): void {

		$e = LoginLinkAiLib::open($this->eUser);
		$eLoginLink = LoginLinkAiLib::getByToken($e['clearToken']);

		$this->assertTrue(ConnectionLib::getOnline()->empty(), 'personne n\'est connecté avant');

		$fw = new \FailWatch();

		$this->assertTrue(LoginLinkAiLib::succeed($eLoginLink));
		$this->assertTrue($fw->ok());
		$this->assertEquals($this->eUser['id'], ConnectionLib::getOnline()['id'], 'le lien ouvre la session de son compte');
		$this->assertEquals(LoginLink::LINK, $this->reload($e)['closedBy']);
		$this->assertTrue(LoginLinkAiLib::getByToken($e['clearToken'])->empty(), 'un lien qui a servi ne se relit plus');

	}

	public function testSucceedRefusesALinkClosedMeanwhile(): void {

		$e = LoginLinkAiLib::open($this->eUser);
		$eLoginLink = LoginLinkAiLib::getByToken($e['clearToken']);

		// Le second envoi du même lien : l'autre navigateur l'a fermé entre la lecture et l'écriture
		LoginLink::model()
			->whereId($e['id'])
			->update([
				'closedAt' => new \Sql('NOW()'),
				'closedBy' => LoginLink::LINK
			]);

		$this->assertFalse(LoginLinkAiLib::succeed($eLoginLink));
		$this->assertTrue(ConnectionLib::getOnline()->empty(), 'aucune session ne s\'ouvre sur un lien déjà servi');

	}

	public function testSucceedRefusesAnAccountThatBecameAdministrator(): void {

		$e = LoginLinkAiLib::open($this->eUser);
		$eLoginLink = LoginLinkAiLib::getByToken($e['clearToken']);

		UserSetting::$admin[] = $this->eUser['id'];

		$this->assertFalse(LoginLinkAiLib::succeed($eLoginLink));
		$this->assertTrue(ConnectionLib::getOnline()->empty());
		$this->assertEquals(NULL, $this->reload($e)['closedAt'], 'un refus ne consomme pas le lien');
		$this->assertTrue(LoginLinkAiLib::getByToken($e['clearToken'])->empty(), 'le lien d\'un compte devenu administrateur est mort');

	}

	public function testSucceedRefusesAnAccountBannedMeanwhile(): void {

		$e = LoginLinkAiLib::open($this->eUser);
		$eLoginLink = LoginLinkAiLib::getByToken($e['clearToken']);

		$fw = new \FailWatch();
		BanLib::createBan($this->eUser, $this->createUser('banisseur'), ['user'], 'recette', 1, $fw);
		$this->assertTrue($fw->ok(), 'le bannissement du décor est posé');

		$this->assertFalse(LoginLinkAiLib::succeed($eLoginLink));
		$this->assertTrue(ConnectionLib::getOnline()->empty());
		$this->assertTrue(LoginLinkAiLib::getByToken($e['clearToken'])->empty(), 'le lien d\'un compte banni est mort');

	}

	public function testGetByTokenDropsAnAccountSuspendedMeanwhile(): void {

		$e = LoginLinkAiLib::open($this->eUser);

		$this->eUser['status'] = User::SUSPENDED;
		UserLib::update($this->eUser, ['status']);

		$this->assertTrue(LoginLinkAiLib::getByToken($e['clearToken'])->empty(), 'le lien d\'un compte suspendu après l\'envoi est mort');
		$this->assertEquals(NULL, $this->reload($e)['closedAt'], 'il n\'est pas consommé pour autant');

	}

	public function testSucceedRefusesAnInactiveAccount(): void {

		$e = LoginLinkAiLib::open($this->eUser);
		$eLoginLink = LoginLinkAiLib::getByToken($e['clearToken']);

		// succeed() garde son propre contrôle : il reçoit un élément, pas un jeton
		$eLoginLink['user']['status'] = User::SUSPENDED;

		$this->assertFalse(LoginLinkAiLib::succeed($eLoginLink));
		$this->assertTrue(ConnectionLib::getOnline()->empty());
		$this->assertEquals(NULL, $this->reload($e)['closedAt'], 'un refus ne consomme pas le lien');

	}

	public function testSucceedClosesTheSeriesAsLink(): void {

		for($i = 0; $i < 3; $i++) {
			LoginAttemptAiLib::fail($this->eUser['email'], throttled: FALSE);
		}

		$e = LoginLinkAiLib::open($this->eUser);

		$this->assertTrue(LoginLinkAiLib::succeed(LoginLinkAiLib::getByToken($e['clearToken'])));

		$eLoginAttempt = LoginAttempt::model()
			->select(LoginAttempt::getSelection())
			->whereLogin(LoginAttemptAiLib::normalize($this->eUser['email']))
			->get();

		$this->assertEquals(LoginAttempt::LINK, $eLoginAttempt['status'], 'd1 : la série est close comme réussie par un lien');
		$this->assertFalse(LoginAttemptAiLib::isOpen($eLoginAttempt));
		$this->assertTrue(LoginAttemptAiLib::getOpen(LoginAttemptAiLib::normalize($this->eUser['email']))->empty(), 'un échec suivant ouvre une nouvelle série');

	}

	public function testSucceedLiftsTheLoginThrottleOnly(): void {

		if($this->assertDecorPresent(\Cache::isDisabled() === FALSE, 'le cache actif sur ce poste, qui compte les essais') === FALSE) {
			return;
		}

		for($i = 0; $i < ThrottleAiLib::LIMIT_LOGIN; $i++) {
			ThrottleAiLib::failLogin($this->eUser['email']);
		}

		$this->assertFalse(ThrottleAiLib::checkLogin($this->eUser['email']), 'le décor est bloqué');

		$ipCount = ThrottleAiLib::count('login-ip', self::IP);

		$e = LoginLinkAiLib::open($this->eUser);

		$this->assertTrue(LoginLinkAiLib::succeed(LoginLinkAiLib::getByToken($e['clearToken'])), 'd1 : le lien passe pendant le blocage');
		$this->assertEquals(0, ThrottleAiLib::count('login', hash('sha256', $this->eUser['email'])), 'le blocage de l\'adresse est levé');
		$this->assertEquals($ipCount, ThrottleAiLib::count('login-ip', self::IP), 'le compteur du poste reste');

	}

	public function testForgottenPasswordMailCarriesTheLink(): void {

		[, $text, $html] = UserUi::getForgottenPasswordMail('hash', $this->eUser['email'], 'jeton');

		$this->assertTrue(str_contains($html, LoginLinkAiUi::getLink('jeton')), 'le bouton connecte');
		$this->assertTrue(str_contains($html, encode(UserUi::getForgottenPasswordLink('hash', $this->eUser['email']))), 'le nouveau mot de passe reste proposé');
		$this->assertTrue(str_contains($text, LoginLinkAiUi::getLink('jeton')));
		$this->assertTrue(str_contains($text, UserUi::getForgottenPasswordLink('hash', $this->eUser['email'])));

		[, , $html] = UserUi::getForgottenPasswordMail('hash', $this->eUser['email']);

		$this->assertFalse(str_contains($html, '/user/log:loginLink'), 'sans jeton, pas de bouton de connexion');

	}

	public function testSendForgottenPasswordLinkOpensALink(): void {

		$eUserAuth = UserLib::checkForgottenPasswordLink($this->eUser['email']);

		$this->assertTrue(UserLib::sendForgottenPasswordLink($eUserAuth));
		$this->assertEquals(1, $this->countLinks($this->eUser), 'l\'e-mail D porte un lien de connexion');

		$eUserAdmin = $this->createUser('administrateur');
		UserSetting::$admin[] = $eUserAdmin['id'];

		$eUserAuth = UserLib::checkForgottenPasswordLink($eUserAdmin['email']);

		$this->assertTrue(UserLib::sendForgottenPasswordLink($eUserAuth), 'l\'e-mail de l\'administrateur part quand même');
		$this->assertEquals(0, $this->countLinks($eUserAdmin), 'sans lien de connexion');

	}

	public function testForgottenPasswordIsSilentForAnAccountWithoutPassword(): void {

		$eUser = $this->createUser('sans mot de passe', withPassword: FALSE);

		$fw = new \FailWatch();

		$this->assertEquals(NULL, UserLib::checkForgottenPasswordLink($eUser['email']));
		$this->assertTrue($fw->ok(), 'aucune erreur : la réponse est celle d\'une adresse inconnue');

	}

}
?>
