<?php
namespace user;

/**
 * Le défi d'un administrateur (`ChallengeAiLib`) : ce que le mot de passe ouvre, ce que le code et
 * le lien ferment, ce que cinq refus et l'expiration font, et ce que la connexion en fait.
 *
 * Tout se joue en transaction annulée par `tearDown()`, la dev étant partagée. Le compte semé est
 * poussé dans `UserSetting::$admin` le temps du cas, l'adresse est une adresse de test (TEST-NET-3),
 * le webhook Discord est vidé pour que rien ne parte, et le cookie de confiance se lit et s'écrit
 * dans `$_COOKIE`, qu'un cas nettoie. Le mail ne part pas : en dev, `SendLib::send()` n'envoie qu'aux
 * adresses de `MailSetting::$devSendOnly`, et l'adresse semée n'en est pas.
 *
 * Les compteurs d'essais vivent dans Redis : les cas qui comptent les refus se passent quand le
 * cache est désactivé, et le disent.
 *
 * Lancement : dev/test package=user file=ChallengeAi
 */
class ChallengeAiTest extends \Test {

	const IP = '203.0.113.77';
	const PASSWORD = 'zzTestMotDePasseJuste42';

	private array $serverInitial;
	private array $cookieInitial;
	private array $adminInitial;
	private bool $challengeInitial;
	private string $webhookInitial;

	private User $eUser;

	public function setUp(): void {

		$this->serverInitial = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = self::IP;

		$this->cookieInitial = $_COOKIE;
		unset($_COOKIE[ChallengeAiLib::COOKIE]);

		$this->webhookInitial = \communication\CommunicationSetting::$discordSecurityWebhookUrl;
		\communication\CommunicationSetting::$discordSecurityWebhookUrl = '';

		Challenge::model()->beginTransaction();

		$this->eUser = $this->createUser();

		$this->adminInitial = UserSetting::$admin;
		UserSetting::$admin[] = $this->eUser['id'];

		$this->challengeInitial = UserSetting::$challenge;
		UserSetting::$challenge = TRUE;

		\session\SessionLib::clean();
		ThrottleAiLib::clear('login', hash('sha256', $this->eUser['email']));
		ThrottleAiLib::clear('login-ip', self::IP);

	}

	public function tearDown(): void {

		foreach(Challenge::model()->whereUser($this->eUser)->getColumn('id') as $id) {
			ThrottleAiLib::clear('challenge-code', (string)$id);
		}

		ThrottleAiLib::clear('challenge-mail', (string)$this->eUser['id']);
		ThrottleAiLib::clear('login', hash('sha256', $this->eUser['email']));
		ThrottleAiLib::clear('login-ip', self::IP);

		\session\SessionLib::clean();
		ConnectionLib::setOnline(new User());

		Challenge::model()->rollBack();

		UserSetting::$admin = $this->adminInitial;
		UserSetting::$challenge = $this->challengeInitial;
		\communication\CommunicationSetting::$discordSecurityWebhookUrl = $this->webhookInitial;
		$_COOKIE = $this->cookieInitial;
		$_SERVER = $this->serverInitial;

	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Défi',
			'email' => 'zztest.challenge.'.uniqid().'@example.org',
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

	private function reload(Challenge $e): Challenge {

		return Challenge::model()
			->select(ChallengeAiLib::getSelectionWithUser())
			->whereId($e['id'])
			->get();

	}

	public function testIsRequiredForAdminOnly(): void {

		$this->assertTrue(ChallengeAiLib::isRequired($this->eUser), 'un administrateur sans cookie doit répondre');

		UserSetting::$admin = $this->adminInitial;

		$this->assertFalse(ChallengeAiLib::isRequired($this->eUser), 'un compte ordinaire ne répond jamais');

	}

	public function testOpenHashesSecretsAndRemembersTheSession(): void {

		$e = ChallengeAiLib::open($this->eUser, TRUE, '/ferme/1/');

		$this->assertTrue($e->notEmpty());
		$this->assertEquals(1, preg_match('/^[0-9]{6}$/', $e['clearCode']), 'six chiffres');
		$this->assertEquals(1, preg_match('/^[0-9a-f]{64}$/', $e['clearToken']), '32 octets en hexadécimal');

		$eChallengeStored = $this->reload($e);

		$this->assertDiffer($e['clearCode'], $eChallengeStored['code'], 'le code ne s\'écrit pas en clair');
		$this->assertTrue(password_verify($e['clearCode'], $eChallengeStored['code']));
		$this->assertEquals(hash('sha256', $e['clearToken']), $eChallengeStored['token']);
		$this->assertEquals(Challenge::WEB, $eChallengeStored['device']);
		$this->assertEquals(self::IP, $eChallengeStored['ip']);
		$this->assertEquals(NULL, $eChallengeStored['closedAt']);
		$this->assertEquals(NULL, $eChallengeStored['trustedUntil']);
		$this->assertTrue(strtotime($eChallengeStored['expiresAt']) > time() + ChallengeAiLib::LIFETIME - 60);

		$eChallengePending = ChallengeAiLib::getPending();

		$this->assertEquals($e['id'], $eChallengePending['id']);
		$this->assertEquals(TRUE, $eChallengePending['remember']);
		$this->assertEquals('/ferme/1/', $eChallengePending['redirect']);
		$this->assertEquals($this->eUser['id'], $eChallengePending['user']['id']);

	}

	public function testRightCodeSucceedsAndTrustsTheBrowser(): void {

		$e = ChallengeAiLib::open($this->eUser);

		$fw = new \FailWatch();

		$this->assertTrue(ChallengeAiLib::verifyCode(ChallengeAiLib::getPending(), ChallengeAiUi::formatCode($e['clearCode'])), 'le code se saisit avec l\'espace du mail');
		$this->assertTrue($fw->ok());

		$eChallengeStored = $this->reload($e);

		$this->assertEquals(Challenge::CODE, $eChallengeStored['closedBy']);
		$this->assertDiffer(NULL, $eChallengeStored['closedAt']);
		$this->assertTrue(strtotime($eChallengeStored['trustedUntil']) > time() + 3600 * 24 * (ChallengeAiLib::TRUST_DAYS - 1));

		$this->assertTrue(isset($_COOKIE[ChallengeAiLib::COOKIE]), 'le cookie est posé');
		$this->assertTrue(ChallengeAiLib::isTrusted($this->eUser));
		$this->assertFalse(ChallengeAiLib::isRequired($this->eUser));
		$this->assertTrue(ChallengeAiLib::getPending()->empty(), 'la session n\'attend plus rien');

		$eUserOther = new User(['id' => $this->eUser['id'] + 1]);
		$this->assertFalse(ChallengeAiLib::isTrusted($eUserOther), 'le cookie ne vaut que pour son compte');

		$_COOKIE[ChallengeAiLib::COOKIE] = 'trusted:'.$e['id'].':'.$this->eUser['id'].':falsifie';
		$this->assertFalse(ChallengeAiLib::isTrusted($this->eUser), 'un cookie sans signature valable ne vaut rien');

	}

	public function testWrongCodeCountsAndFiveCloseTheChallenge(): void {

		if($this->assertDecorPresent(\Cache::isDisabled() === FALSE, 'le cache actif sur ce poste, qui compte les essais') === FALSE) {
			return;
		}

		$e = ChallengeAiLib::open($this->eUser);
		$wrong = ($e['clearCode'] === '000000') ? '000001' : '000000';

		$fw = new \FailWatch();

		$this->assertFalse(ChallengeAiLib::verifyCode(ChallengeAiLib::getPending(), $wrong));
		$this->assertTrue($fw->has('Challenge::code.invalid'));
		$this->assertEquals(1, ThrottleAiLib::count('challenge-code', (string)$e['id']));
		$this->assertTrue(ChallengeAiLib::getPending()->notEmpty(), 'un refus ne ferme pas le défi');

		for($i = 2; $i < ChallengeAiLib::MAX_FAILURES; $i++) {
			ChallengeAiLib::verifyCode(ChallengeAiLib::getPending(), $wrong);
		}

		$this->assertFalse($fw->has('Challenge::exceeded'));

		$fw = new \FailWatch();

		$this->assertFalse(ChallengeAiLib::verifyCode(ChallengeAiLib::getPending(), $wrong));
		$this->assertTrue($fw->has('Challenge::exceeded'), 'le cinquième refus ferme');

		$eChallengeStored = $this->reload($e);

		$this->assertEquals(Challenge::FAILURES, $eChallengeStored['closedBy']);
		$this->assertEquals(NULL, $eChallengeStored['trustedUntil']);
		$this->assertTrue(ChallengeAiLib::getPending()->empty());
		$this->assertTrue(ChallengeAiLib::getFromSession()->empty(), 'la session oublie le défi fermé');
		$this->assertFalse(isset($_COOKIE[ChallengeAiLib::COOKIE]));

		$this->assertEquals(0, ThrottleAiLib::count('challenge-code', (string)$e['id']), 'le compteur ne survit pas au défi');

	}

	public function testLinkSucceedsInTheSameBrowserOnly(): void {

		$e = ChallengeAiLib::open($this->eUser, FALSE, '/ferme/1/');

		$this->assertTrue(ChallengeAiLib::getByToken('pas-un-jeton')->empty());
		$this->assertTrue(ChallengeAiLib::getByToken(strrev($e['clearToken']))->empty(), 'un jeton de la bonne forme mais inconnu');

		$eChallengeByToken = ChallengeAiLib::getByToken($e['clearToken']);

		$this->assertEquals($e['id'], $eChallengeByToken['id']);
		$this->assertTrue($eChallengeByToken['isSameBrowser']);
		$this->assertEquals('/ferme/1/', $eChallengeByToken['redirect']);

		\session\SessionLib::clean();

		$eChallengeElsewhere = ChallengeAiLib::getByToken($e['clearToken']);

		$this->assertFalse($eChallengeElsewhere['isSameBrowser'], 'sans la session du mot de passe, le lien ne connecte pas');
		$this->assertEquals(NULL, $eChallengeElsewhere['redirect']);

		ChallengeAiLib::succeed($eChallengeByToken, Challenge::LINK);

		$eChallengeStored = $this->reload($e);

		$this->assertEquals(Challenge::LINK, $eChallengeStored['closedBy']);
		$this->assertTrue(ChallengeAiLib::isTrusted($this->eUser));
		$this->assertTrue(ChallengeAiLib::getByToken($e['clearToken'])->empty(), 'un lien qui a servi ne se retrouve plus');

	}

	public function testResendClosesTheOldAndOpensANew(): void {

		$e = ChallengeAiLib::open($this->eUser, TRUE, '/ferme/1/');

		$this->assertTrue(ChallengeAiLib::acceptResend(ChallengeAiLib::getFromSession()));

		$eChallengeNew = ChallengeAiLib::resend(ChallengeAiLib::getFromSession());

		$this->assertDiffer($e['id'], $eChallengeNew['id']);
		$this->assertEquals(Challenge::RESENT, $this->reload($e)['closedBy']);

		$eChallengePending = ChallengeAiLib::getPending();

		$this->assertEquals($eChallengeNew['id'], $eChallengePending['id'], 'la session suit le nouveau défi');
		$this->assertEquals(TRUE, $eChallengePending['remember']);
		$this->assertEquals('/ferme/1/', $eChallengePending['redirect']);

		$this->assertFalse(password_verify($e['clearCode'], $this->reload($eChallengeNew)['code']), 'l\'ancien code ne vaut pas pour le nouveau');

	}

	public function testExpiredChallengeAcceptsAResendForAnHour(): void {

		// La base de dev peut porter un défi abandonné pendant une recette : le cron le fermerait aussi et
		// fausserait le compte. La transaction du test défait cette fermeture.
		ChallengeAiLib::notifyExpired();

		$e = ChallengeAiLib::open($this->eUser);

		Challenge::model()->whereId($e['id'])->update(['expiresAt' => new \Sql('NOW() - INTERVAL 1 MINUTE')]);

		$this->assertTrue(ChallengeAiLib::getPending()->empty(), 'expiré, le défi n\'attend plus');
		$this->assertTrue(ChallengeAiLib::acceptResend(ChallengeAiLib::getFromSession()), 'mais un nouveau code se redemande');

		$this->assertEquals(1, ChallengeAiLib::notifyExpired(), 'le cron le ferme');
		$this->assertEquals(Challenge::EXPIRED, $this->reload($e)['closedBy']);
		$this->assertEquals(0, ChallengeAiLib::notifyExpired(), 'et ne le redit pas');

		$this->assertTrue(ChallengeAiLib::acceptResend(ChallengeAiLib::getFromSession()), 'fermé par le cron, il se redemande encore');

		Challenge::model()->whereId($e['id'])->update(['createdAt' => new \Sql('NOW() - INTERVAL 2 HOUR')]);

		$this->assertFalse(ChallengeAiLib::acceptResend(ChallengeAiLib::getFromSession()), 'au-delà d\'une heure, le mot de passe se ressaisit');

		$fw = new \FailWatch();

		$this->assertTrue(ChallengeAiLib::resend(ChallengeAiLib::getFromSession())->empty());
		$this->assertTrue($fw->has('Challenge::expired'));

	}

	public function testRevokeForgetsTheBrowsers(): void {

		$e = ChallengeAiLib::open($this->eUser);

		ChallengeAiLib::succeed(ChallengeAiLib::getPending(), Challenge::CODE);

		$this->assertTrue(ChallengeAiLib::isTrusted($this->eUser));

		ChallengeAiLib::revoke($this->eUser);

		$this->assertFalse(ChallengeAiLib::isTrusted($this->eUser), 'le cookie reste, la base ne le reconnaît plus');
		$this->assertEquals(NULL, $this->reload($e)['trustedUntil']);
		$this->assertEquals(Challenge::CODE, $this->reload($e)['closedBy'], 'le journal ne change pas');

	}

	public function testNewPasswordRevokes(): void {

		ChallengeAiLib::open($this->eUser);
		ChallengeAiLib::succeed(ChallengeAiLib::getPending(), Challenge::CODE);

		$this->eUser['auth'] = new UserAuth([
			'user' => $this->eUser,
			'type' => UserAuth::BASIC,
			'login' => $this->eUser['email'],
			'password' => password_hash(self::PASSWORD.'2', PASSWORD_DEFAULT)
		]);

		SignUpLib::updatePassword($this->eUser);

		$this->assertFalse(ChallengeAiLib::isTrusted($this->eUser));

	}

	public function testLogInOpensAChallengeInsteadOfASession(): void {

		$fw = new \FailWatch();

		$this->assertFalse(ConnectionLib::logIn($this->eUser['email'], self::PASSWORD), 'le mot de passe seul n\'ouvre pas la session');
		$this->assertTrue($fw->ok(), 'et ce n\'est pas un échec');

		$e = ChallengeAiLib::getPending();

		$this->assertTrue($e->notEmpty(), 'un défi attend');
		$this->assertEquals($this->eUser['id'], $e['user']['id']);
		$this->assertTrue(ConnectionLib::getOnline()->empty(), 'personne n\'est connecté');

		ChallengeAiLib::succeed($e, Challenge::CODE);

		$this->assertTrue(ConnectionLib::logIn($this->eUser['email'], self::PASSWORD), 'le navigateur de confiance se connecte au mot de passe');
		$this->assertEquals($this->eUser['id'], ConnectionLib::getOnline()['id']);

	}

	public function testLogInWithAWrongPasswordStillFails(): void {

		$fw = new \FailWatch();

		$this->assertFalse(ConnectionLib::logIn($this->eUser['email'], 'zzTestFaux'));
		$this->assertTrue($fw->has('User::connectionInvalid'));
		$this->assertTrue(ChallengeAiLib::getPending()->empty(), 'aucun défi sans le bon mot de passe');

	}

	public function testMailThrottled(): void {

		if($this->assertDecorPresent(\Cache::isDisabled() === FALSE, 'le cache actif sur ce poste, qui compte les envois') === FALSE) {
			return;
		}

		for($i = 0; $i < ChallengeAiLib::LIMIT_MAIL; $i++) {
			$this->assertTrue(ChallengeAiLib::open($this->eUser)->notEmpty());
		}

		$fw = new \FailWatch();

		$this->assertTrue(ChallengeAiLib::open($this->eUser)->empty());
		$this->assertTrue($fw->has('Challenge::mailThrottled'));

	}

	public function testMessages(): void {

		$e = ChallengeAiLib::open($this->eUser);
		$eChallengeStored = $this->reload($e);

		$failures = ChallengeAiLib::getMessage($eChallengeStored, ChallengeAiLib::NOTIFY_FAILURES);
		$expired = ChallengeAiLib::getMessage($eChallengeStored, ChallengeAiLib::NOTIFY_EXPIRED);

		$this->assertTrue(str_contains($failures, Challenge::WEB), 'le type d\'appareil reste, il ne désigne personne');
		$this->assertTrue(str_contains($expired, '/user/admin/connectionAi?challenge='.$eChallengeStored['id']), 'le lien ouvre les connexions par l\'identifiant du challenge, qui seul résout l\'adresse');

		// Discord est un tiers dont le salon ne purge rien, là où le challenge se purge au bout de
		// KEEP_DAYS, et qui n'est déclaré destinataire dans aucun des deux documents que l'article 13
		// du RGPD oblige à tenir : aucune des quatre données qui désignent une personne ne traverse.
		foreach(['refus' => $failures, 'expiration' => $expired] as $which => $message) {

			$this->assertFalse(str_contains($message, $this->eUser['email']), $which.' : l\'adresse e-mail ne part pas dans Discord');
			$this->assertFalse(str_contains($message, $this->eUser['firstName']), $which.' : le prénom non plus');
			$this->assertFalse(str_contains($message, $this->eUser['lastName']), $which.' : le nom non plus');
			$this->assertFalse(str_contains($message, self::IP), $which.' : l\'adresse IP non plus');

		}

		$this->assertEquals(self::IP, ChallengeAiLib::getIpById($eChallengeStored['id']), 'le lien de l\'alerte retrouve l\'adresse derrière l\'authentification');
		$this->assertEquals(NULL, ChallengeAiLib::getIpById(NULL), 'un écran ouvert sans identifiant ne filtre rien');
		$this->assertEquals(NULL, ChallengeAiLib::getIpById($eChallengeStored['id'] + 1000), 'un challenge purgé non plus');

		[$subject, $text, $html] = ChallengeAiUi::getMail($eChallengeStored, $e['clearCode'], $e['clearToken']);

		$this->assertTrue(str_contains($html, ChallengeAiUi::formatCode($e['clearCode'])), 'le code est dans le mail');
		$this->assertTrue(str_contains($html, ChallengeAiUi::getLink($e['clearToken'])), 'le lien aussi');
		$this->assertTrue(str_contains($text, $e['clearToken']), 'la version texte porte le lien');

	}

	public function testNormalizeCode(): void {

		$this->assertEquals('482913', ChallengeAiLib::normalizeCode('482 913'));
		$this->assertEquals('482913', ChallengeAiLib::normalizeCode(' 482-913 '));
		$this->assertEquals('', ChallengeAiLib::normalizeCode(NULL));
		$this->assertEquals('482 913', ChallengeAiUi::formatCode('482913'));

	}

}
?>
