<?php
namespace user;

/**
 * Les tentatives de connexion échouées (`LoginAttemptAiLib`) : ce qu'un échec écrit, ce qu'une
 * réussite clôt, ce que l'écran retrouve.
 *
 * Tout se joue en transaction annulée par `tearDown()`, la dev étant partagée par plusieurs
 * sessions. L'adresse de la ligne de commande est remplacée par une adresse de test (TEST-NET-3),
 * le webhook Discord est vidé pour que rien ne parte, et les compteurs Redis de la limitation sont
 * effacés autour du cas qui passe par la vraie `ConnectionLib::logIn()`.
 *
 * Lancement : dev/test package=user file=LoginAttemptAi
 */
class LoginAttemptAiTest extends \Test {

	const IP = '203.0.113.77';
	const IP_OTHER = '203.0.113.78';
	const PASSWORD = 'zzTestMotDePasseJuste42';
	const UNKNOWN = 'zztest.inconnu.'.'@example.org';

	private array $serverInitial;
	private string $webhookInitial;

	private User $eUser;

	public function setUp(): void {

		$this->serverInitial = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = self::IP;

		$this->webhookInitial = \communication\CommunicationSetting::$discordSecurityWebhookUrl;
		\communication\CommunicationSetting::$discordSecurityWebhookUrl = '';

		LoginAttempt::model()->beginTransaction();

		$this->eUser = $this->createUser();

		$this->clearCounters();

	}

	public function tearDown(): void {

		$this->clearCounters();

		\session\SessionLib::clean();
		ConnectionLib::setOnline(new User());

		LoginAttempt::model()->rollBack();

		\communication\CommunicationSetting::$discordSecurityWebhookUrl = $this->webhookInitial;
		$_SERVER = $this->serverInitial;

	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Tentative',
			'email' => 'zztest.attempt.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		$eUser['auth'] = new UserAuth([
			'user' => $eUser,
			'type' => UserAuth::BASIC,
			'login' => $eUser['email'],
			'password' => password_hash(self::PASSWORD, PASSWORD_DEFAULT)
		]);

		SignUpLib::updatePassword($eUser);

		return $eUser;

	}

	private function clearCounters(): void {

		ThrottleAiLib::clear('login', hash('sha256', $this->eUser['email']));
		ThrottleAiLib::clear('login', hash('sha256', self::UNKNOWN));
		ThrottleAiLib::clear('login-ip', self::IP);

	}

	private function countSeries(string $login): int {

		return LoginAttempt::model()
			->whereLogin(LoginAttemptAiLib::normalize($login))
			->count();

	}

	public function testNormalize(): void {

		$this->assertEquals('foo@bar.fr', LoginAttemptAiLib::normalize('  Foo@Bar.FR '));
		$this->assertEquals(LoginAttemptAiLib::LOGIN_LENGTH, mb_strlen(LoginAttemptAiLib::normalize(str_repeat('a', 300))));

	}

	public function testFailOpensASeriesAndCounts(): void {

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);
		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);
		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);

		$this->assertEquals(1, $this->countSeries(self::UNKNOWN), 'trois échecs font une série');

		$e = LoginAttemptAiLib::getOpen(self::UNKNOWN, self::IP);

		$this->assertTrue($e->notEmpty());
		$this->assertEquals(3, $e['failures']);
		$this->assertEquals(LoginAttempt::RUNNING, $e['status']);
		$this->assertEquals(self::UNKNOWN, $e['login'], 'l\'identifiant est en clair, le mot de passe n\'est nulle part');
		$this->assertTrue($e['user']->empty(), 'aucun compte ne porte cet identifiant');
		$this->assertEquals(self::IP, $e['ip']);
		$this->assertEquals(NULL, $e['notifiedAt']);

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: TRUE);

		$e = LoginAttemptAiLib::getOpen(self::UNKNOWN, self::IP);

		$this->assertEquals(4, $e['failures'], 'le refus de la limitation compte aussi');
		$this->assertEquals(LoginAttempt::THROTTLED, $e['status']);

	}

	public function testFailFindsTheAccountWhateverTheCase(): void {

		LoginAttemptAiLib::fail('  '.mb_strtoupper($this->eUser['email']).' ', throttled: FALSE);

		$e = LoginAttemptAiLib::getOpen($this->eUser['email'], self::IP);

		$this->assertTrue($e->notEmpty());
		$this->assertEquals($this->eUser['id'], $e['user']['id']);
		$this->assertEquals($this->eUser['email'], $e['login'], 'l\'identifiant est écrit normalisé');

	}

	public function testASeriesIsPerAddress(): void {

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);

		$_SERVER['REMOTE_ADDR'] = self::IP_OTHER;

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);

		$this->assertEquals(2, $this->countSeries(self::UNKNOWN), 'deux postes font deux séries');
		$this->assertEquals(1, LoginAttemptAiLib::getOpen(self::UNKNOWN, self::IP_OTHER)['failures']);

	}

	public function testTheWindowClosesASeries(): void {

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);

		$e = LoginAttemptAiLib::getOpen(self::UNKNOWN, self::IP);
		$e['lastAt'] = new \Sql('NOW() - INTERVAL '.(LoginAttemptAiLib::WINDOW + 60).' SECOND');

		LoginAttempt::model()->select(['lastAt'])->update($e);

		$this->assertTrue(LoginAttemptAiLib::getOpen(self::UNKNOWN, self::IP)->empty(), 'passée la fenêtre, la série n\'est plus ouverte');

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);

		$this->assertEquals(2, $this->countSeries(self::UNKNOWN), 'un échec après la fenêtre ouvre une nouvelle série');

	}

	public function testSucceedClosesTheSeries(): void {

		$this->assertEquals(0, $this->countSeries($this->eUser['email']));

		LoginAttemptAiLib::succeed($this->eUser['email'], $this->eUser);

		$this->assertEquals(0, $this->countSeries($this->eUser['email']), 'une réussite sans série n\'écrit rien');

		for($i = 0; $i < LoginAttemptAiLib::THRESHOLD; $i++) {
			LoginAttemptAiLib::fail($this->eUser['email'], throttled: FALSE);
		}

		$_SERVER['REMOTE_ADDR'] = self::IP_OTHER;

		LoginAttemptAiLib::succeed($this->eUser['email'], $this->eUser);

		$this->assertTrue(LoginAttemptAiLib::getOpen($this->eUser['email'])->empty(), 'la série est close');

		$e = LoginAttempt::model()
			->select(LoginAttempt::getSelection())
			->whereLogin($this->eUser['email'])
			->get();

		$this->assertEquals(LoginAttempt::SUCCEEDED, $e['status'], 'quel que soit le poste de la réussite');
		$this->assertEquals(LoginAttemptAiLib::THRESHOLD, $e['failures']);
		$this->assertEquals($this->eUser['id'], $e['user']['id']);
		$this->assertEquals(NULL, $e['notifiedAt'], 'sans webhook, rien n\'est parti');
		$this->assertFalse(LoginAttemptAiLib::isOpen($e));

	}

	public function testLogInWritesTheSeries(): void {

		$fw = new \FailWatch();

		ConnectionLib::logIn($this->eUser['email'], 'zzTestFaux');

		$this->assertTrue($fw->has('User::connectionInvalid'));

		$e = LoginAttemptAiLib::getOpen($this->eUser['email'], self::IP);

		$this->assertTrue($e->notEmpty(), 'un mauvais mot de passe ouvre la série');
		$this->assertEquals(1, $e['failures']);
		$this->assertEquals($this->eUser['id'], $e['user']['id']);

		ConnectionLib::logIn($this->eUser['email'], self::PASSWORD);

		$this->assertTrue(LoginAttemptAiLib::getOpen($this->eUser['email'], self::IP)->empty(), 'le bon mot de passe la clôt');

		$e = LoginAttempt::model()
			->select(['status', 'failures'])
			->whereLogin($this->eUser['email'])
			->get();

		$this->assertEquals(LoginAttempt::SUCCEEDED, $e['status']);
		$this->assertEquals(1, $e['failures']);

	}

	public function testMessage(): void {

		$e = new LoginAttempt([
			'id' => 18,
			'login' => 'ad…',
			'user' => new User(),
			'ip' => self::IP,
			'failures' => 7,
			'status' => LoginAttempt::SUCCEEDED,
		]);

		$message = LoginAttemptAiLib::getMessage($e, LoginAttemptAiLib::NOTIFY_SUCCEEDED);

		$this->assertTrue(str_contains($message, '7'));
		$this->assertTrue(str_contains($message, '/user/admin/loginAttemptAi?attempt=18'), 'le lien ouvre l\'écran par l\'identifiant de la série, qui seul résout l\'adresse');
		$this->assertTrue(str_starts_with($message, '🔑 Connexion réussie par mot de passe après 7 échecs'), 'la clé dit le mot de passe, comme l\'icône de l\'écran');
		$this->assertFalse(str_contains($message, 'par un lien'));
		$this->assertFalse(str_contains($message, 'ad…'), 'l\'identifiant tenté ne sort pas du produit');

		$e['status'] = LoginAttempt::LINK;

		$message = LoginAttemptAiLib::getMessage($e, LoginAttemptAiLib::NOTIFY_SUCCEEDED);

		$this->assertTrue(str_starts_with($message, '📧 Connexion réussie par un lien reçu par e-mail après 7 échecs'), 'l\'enveloppe dit le lien reçu par e-mail, et chaque moyen porte l\'icône que l\'écran lui donne');

		$e['user'] = $this->eUser;

		$message = LoginAttemptAiLib::getMessage($e, LoginAttemptAiLib::NOTIFY_ADMIN);

		$this->assertEquals('🔐 Échec de connexion sur un compte administrateur. '.\Lime::getUrl().'/user/admin/loginAttemptAi?attempt=18', $message, 'le message dit ce qui est arrivé, ni à qui ni d\'où');

		// Discord est un tiers dont le salon ne purge rien, là où la série se purge au bout de
		// KEEP_DAYS, et qui n'est déclaré destinataire dans aucun des deux documents que l'article 13
		// du RGPD oblige à tenir : aucune des quatre données qui désignent une personne ne traverse.
		foreach([LoginAttemptAiLib::NOTIFY_ADMIN, LoginAttemptAiLib::NOTIFY_SUCCEEDED] as $reason) {

			$message = LoginAttemptAiLib::getMessage($e, $reason);

			$this->assertFalse(str_contains($message, $this->eUser['email']), $reason.' : l\'adresse e-mail ne part pas dans Discord');
			$this->assertFalse(str_contains($message, $this->eUser['firstName']), $reason.' : le prénom non plus');
			$this->assertFalse(str_contains($message, $this->eUser['lastName']), $reason.' : le nom non plus');
			$this->assertFalse(str_contains($message, self::IP), $reason.' : l\'adresse IP non plus');

		}

	}

	public function testOnlyAnAdminSuccessIsNotified(): void {

		$e = new LoginAttempt([
			'id' => 19,
			'failures' => LoginAttemptAiLib::THRESHOLD,
			'user' => $this->eUser,
			'status' => LoginAttempt::SUCCEEDED,
		]);

		$this->assertFalse(LoginAttemptAiLib::isNotifiableSuccess($e), 'sur un compte ordinaire, une réussite après des échecs est un mot de passe retrouvé et n\'alerte personne');

		$adminInitial = UserSetting::$admin;
		UserSetting::$admin[] = $this->eUser['id'];

		try {

			$this->assertTrue(LoginAttemptAiLib::isNotifiableSuccess($e), 'sur un compte administrateur, la même série alerte');

			$e['failures'] = LoginAttemptAiLib::THRESHOLD - 1;

			$this->assertFalse(LoginAttemptAiLib::isNotifiableSuccess($e), 'en dessous du seuil, même sur un administrateur');

			$e['failures'] = LoginAttemptAiLib::THRESHOLD;
			$e['user'] = new User();

			$this->assertFalse(LoginAttemptAiLib::isNotifiableSuccess($e), 'un identifiant qui ne désigne aucun compte n\'alerte pas');

		} finally {
			UserSetting::$admin = $adminInitial;
		}

	}

	public function testGetIpById(): void {

		LoginAttemptAiLib::fail($this->eUser['email'], throttled: FALSE);

		$e = LoginAttemptAiLib::getOpen($this->eUser['email']);

		$this->assertEquals(self::IP, LoginAttemptAiLib::getIpById($e['id']), 'le lien de l\'alerte retrouve l\'adresse derrière l\'authentification');
		$this->assertEquals(NULL, LoginAttemptAiLib::getIpById(NULL), 'un écran ouvert sans identifiant ne filtre rien');
		$this->assertEquals(NULL, LoginAttemptAiLib::getIpById($e['id'] + 1000), 'une série purgée non plus');

	}

	public function testSearch(): void {

		for($i = 0; $i < LoginAttemptAiLib::THRESHOLD; $i++) {
			LoginAttemptAiLib::fail($this->eUser['email'], throttled: FALSE);
		}

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);
		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: TRUE);

		$count = fn(array $properties) => LoginAttemptAiLib::getAll(new \Search($properties + ['ip' => self::IP]), 0)[1];

		$this->assertEquals(2, $count([]), 'sans filtre, toutes les séries');
		$this->assertEquals(1, $count(['status' => LoginAttemptAiLib::FILTER_THRESHOLD]), 'seule la série au seuil');
		$this->assertEquals(1, $count(['status' => LoginAttemptAiLib::FILTER_THROTTLED]));
		$this->assertEquals(0, $count(['status' => LoginAttemptAiLib::FILTER_SUCCEEDED]));

		$this->assertEquals(1, $count(['login' => mb_strtoupper(self::UNKNOWN)]), 'un identifiant entier se retrouve quelle que soit la casse');
		$this->assertEquals(1, $count(['login' => 'zztest.attempt']), 'un morceau d\'identifiant retrouve le compte');
		$this->assertEquals(1, $count(['login' => 'zztest.inconnu']), 'un morceau d\'identifiant sans compte se retrouve aussi');
		$this->assertEquals(2, $count(['login' => 'example.org']), 'un domaine seul retrouve ses séries');
		$this->assertEquals(0, $count(['login' => 'zztest.absent']));
		$this->assertEquals(0, $count(['ip' => self::IP_OTHER]));

		[$cLoginAttempt] = LoginAttemptAiLib::getAll(new \Search(['ip' => self::IP]), 0);

		foreach($cLoginAttempt as $eLoginAttempt) {
			$this->assertTrue($eLoginAttempt['isOpen']);
		}

	}

	public function testClean(): void {

		LoginAttemptAiLib::fail(self::UNKNOWN, throttled: FALSE);

		$e = LoginAttemptAiLib::getOpen(self::UNKNOWN, self::IP);
		$e['lastAt'] = new \Sql('NOW() - INTERVAL '.(LoginAttemptAiLib::KEEP_DAYS + 1).' DAY');

		LoginAttempt::model()->select(['lastAt'])->update($e);

		LoginAttemptAiLib::fail($this->eUser['email'], throttled: FALSE);

		LoginAttemptAiLib::clean();

		$this->assertEquals(0, $this->countSeries(self::UNKNOWN), 'la vieille série est purgée');
		$this->assertEquals(1, $this->countSeries($this->eUser['email']), 'la récente reste');

	}

}
?>
