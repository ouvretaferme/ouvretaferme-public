<?php
namespace user;

/**
 * La limitation des tentatives sur les entrées anonymes (`ThrottleAiLib`).
 *
 * Rien ne bornait le nombre d'essais sur la connexion, le mot de passe oublié, l'inscription ni
 * les formulaires publics d'un site de structure : un mot de passe se devinait à volonté, une
 * adresse recevait autant de liens qu'on en demandait.
 *
 * Chaque limite se prouve dans les deux sens : la tentative sous la limite passe, celle qui la
 * dépasse est refusée, et le refus porte le bon nom. La connexion se joue par la vraie
 * `ConnectionLib::logIn()` sur un compte semé dans la transaction, parce que c'est elle qui
 * décide ce qui compte comme un échec et ce qui remet à zéro.
 *
 * Les compteurs vivent dans Redis, hors transaction : chaque cas part de compteurs vides et les
 * efface en sortant. L'adresse IP de la ligne de commande est remplacée par une adresse de test
 * (TEST-NET-3) pour ne pas peser sur `127.0.0.1`.
 *
 * Lancement : dev/test package=user file=ThrottleAi
 */
class ThrottleAiTest extends \Test {

	const IP = '203.0.113.42';
	const PASSWORD = 'zzTestMotDePasseJuste42';

	private array $serverInitial;

	private User $eUser;

	public function init(): void {

		if(\Cache::isDisabled()) {
			throw new \Exception('Le cache est désactivé, la limitation ne peut pas se mesurer');
		}

	}

	public function setUp(): void {

		$this->serverInitial = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = self::IP;

		User::model()->beginTransaction();

		$this->eUser = $this->createUser();

		$this->clearCounters();

	}

	/**
	 * Une connexion réussie met le compte en ligne pour tout le processus, et les fichiers suivants
	 * du paquet croiraient un utilisateur connecté (`EmailHashAi`, qui refuse de vérifier l'adresse
	 * d'un autre compte que celui en ligne).
	 */
	public function tearDown(): void {

		$this->clearCounters();

		\session\SessionLib::clean();
		ConnectionLib::setOnline(new User());

		User::model()->rollBack();

		$_SERVER = $this->serverInitial;

	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Limitation',
			'email' => 'zztest.throttle.'.uniqid().'@example.org',
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

		foreach(['login', 'forgotten'] as $scope) {
			ThrottleAiLib::clear($scope, hash('sha256', $this->eUser['email']));
		}

		foreach(['login-ip', 'forgotten-ip', 'sign-up-ip', 'contact-ip'] as $scope) {
			ThrottleAiLib::clear($scope, self::IP);
		}

	}

	private function logIn(string $password): \FailWatch {

		$fw = new \FailWatch();

		ConnectionLib::logIn($this->eUser['email'], $password);

		return $fw;

	}

	public function testWrongPasswordsAreCountedUntilTheLimit(): void {

		for($i = 1; $i < ThrottleAiLib::LIMIT_LOGIN; $i++) {

			$fw = $this->logIn('zzTestFaux'.$i);

			$this->assertTrue($fw->has('User::connectionInvalid'), 'tentative '.$i.' : le refus est celui du mot de passe');
			$this->assertFalse($fw->has('User::connectionThrottled'), 'tentative '.$i.' : pas encore limitée');

		}

		$this->logIn('zzTestFauxDernier');

		$fw = $this->logIn(self::PASSWORD);

		$this->assertTrue($fw->has('User::connectionThrottled'), 'au-delà de la limite, même le bon mot de passe est refusé');
		$this->assertFalse($fw->has('User::connectionInvalid'), 'et il n\'est pas vérifié');

	}

	public function testASuccessfulLoginResetsTheCounter(): void {

		for($i = 1; $i < ThrottleAiLib::LIMIT_LOGIN; $i++) {
			$this->logIn('zzTestFaux'.$i);
		}

		$fw = $this->logIn(self::PASSWORD);

		$this->assertTrue($fw->ok(), 'sous la limite, le bon mot de passe ouvre le compte : '.$fw);

		for($i = 1; $i < ThrottleAiLib::LIMIT_LOGIN; $i++) {
			$this->logIn('zzTestFaux'.$i);
		}

		$fw = $this->logIn(self::PASSWORD);

		$this->assertTrue($fw->ok(), 'les échecs d\'avant la connexion ne comptent plus : '.$fw);

	}

	/** La casse de l'identifiant ne rouvre pas un compteur. */
	public function testTheLoginIsCountedWithoutCase(): void {

		for($i = 0; $i < ThrottleAiLib::LIMIT_LOGIN; $i++) {
			ThrottleAiLib::failLogin($this->eUser['email']);
		}

		$fw = new \FailWatch();

		ConnectionLib::logIn(mb_strtoupper($this->eUser['email']), self::PASSWORD);

		$this->assertTrue($fw->has('User::connectionThrottled'));

	}

	public function testTheAddressIsLimitedAcrossLogins(): void {

		for($i = 0; $i < ThrottleAiLib::LIMIT_LOGIN_IP; $i++) {
			ThrottleAiLib::hit('login-ip', self::IP, ThrottleAiLib::WINDOW_LOGIN);
		}

		$fw = $this->logIn(self::PASSWORD);

		$this->assertTrue($fw->has('User::connectionThrottled'), 'un poste qui a épuisé sa limite ne se connecte plus, quel que soit l\'identifiant');

	}

	public function testTheForgottenPasswordIsLimitedByAddress(): void {

		for($i = 1; $i <= ThrottleAiLib::LIMIT_FORGOTTEN; $i++) {

			$fw = new \FailWatch();
			ThrottleAiLib::checkForgotten($this->eUser['email']);

			$this->assertTrue($fw->ok(), 'demande '.$i.' acceptée : '.$fw);

		}

		$fw = new \FailWatch();
		ThrottleAiLib::checkForgotten(mb_strtoupper($this->eUser['email']));

		$this->assertTrue($fw->has('User::forgottenThrottled'), 'la demande de trop est refusée, quelle que soit la casse');

	}

	private function getMessage(\FailWatch $fw): string {
		return implode(' ', array_map(fn($fail) => $fail[1], $fw->format()));
	}

	/**
	 * Le refus propose de choisir un nouveau mot de passe à partir du cinquième échec, avec
	 * l'identifiant tenté déjà rempli, et le refus de la limitation aussi.
	 */
	public function testTheRefusalOffersANewPasswordFromTheFifthFailure(): void {

		$url = '/user/log:forgottenPassword?email='.urlencode($this->eUser['email']);

		for($i = 1; $i < ThrottleAiLib::FORGOTTEN_LOGIN; $i++) {

			$fw = $this->logIn('zzTestFaux'.$i);

			$this->assertFalse(str_contains($this->getMessage($fw), 'log:forgottenPassword'), 'échec '.$i.' : le refus ne propose rien');

		}

		$fw = $this->logIn('zzTestFaux'.ThrottleAiLib::FORGOTTEN_LOGIN);

		$this->assertTrue($fw->has('User::connectionInvalid'), 'le cinquième échec reste un refus du mot de passe');
		$this->assertTrue(str_contains($this->getMessage($fw), 'href="'.$url.'"'), 'qui propose un nouveau mot de passe pour l\'adresse tentée : '.$this->getMessage($fw));

		for($i = ThrottleAiLib::FORGOTTEN_LOGIN + 1; $i <= ThrottleAiLib::LIMIT_LOGIN; $i++) {
			$this->logIn('zzTestFaux'.$i);
		}

		$fw = $this->logIn(self::PASSWORD);

		$this->assertTrue($fw->has('User::connectionThrottled'), 'la limite atteinte refuse la connexion');
		$this->assertTrue(str_contains($this->getMessage($fw), 'href="'.$url.'"'), 'et son refus propose aussi un nouveau mot de passe : '.$this->getMessage($fw));

	}

	/** Un identifiant qui n'est pas une adresse ne se reporte pas dans le formulaire. */
	public function testTheForgottenLinkKeepsOnlyAnAddress(): void {

		$this->assertEquals('/user/log:forgottenPassword', UserUi::urlForgottenPassword('zztest-pas-une-adresse'));
		$this->assertEquals('/user/log:forgottenPassword', UserUi::urlForgottenPassword(NULL));

	}

	/**
	 * Une adresse inconnue ne se signale pas au mot de passe oublié : la réponse serait un moyen de
	 * savoir quelles adresses portent un compte.
	 */
	public function testAnUnknownAddressIsNotRevealed(): void {

		$fw = new \FailWatch();

		$eUserAuth = UserLib::checkForgottenPasswordLink('zztest.inconnu.'.uniqid().'@example.org');

		$this->assertTrue($eUserAuth === NULL, 'aucun lien ne part vers une adresse inconnue');
		$this->assertTrue($fw->ok(), 'et rien ne le dit : '.$fw);

		$this->assertTrue(UserLib::checkForgottenPasswordLink($this->eUser['email']) instanceof UserAuth, 'une adresse connue reçoit son lien');

	}

	public function testTheSignUpIsLimitedByIp(): void {

		for($i = 1; $i <= ThrottleAiLib::LIMIT_SIGN_UP_IP; $i++) {

			$fw = new \FailWatch();
			ThrottleAiLib::checkSignUp();

			$this->assertTrue($fw->ok(), 'inscription '.$i.' acceptée : '.$fw);

		}

		$fw = new \FailWatch();
		ThrottleAiLib::checkSignUp();

		$this->assertTrue($fw->has('User::signUpThrottled'));

	}

	public function testThePublicContactIsLimitedByIp(): void {

		for($i = 1; $i <= ThrottleAiLib::LIMIT_CONTACT_IP; $i++) {
			$this->assertTrue(ThrottleAiLib::acceptContact(), 'message '.$i.' accepté');
		}

		$this->assertFalse(ThrottleAiLib::acceptContact(), 'le message de trop est refusé');

	}

	/**
	 * La fenêtre part de la première tentative : les suivantes ne la prolongent pas, sinon un flux
	 * régulier maintiendrait le refus sans fin.
	 */
	public function testTheWindowStartsAtTheFirstAttempt(): void {

		ThrottleAiLib::hit('login-ip', self::IP, 60);

		$client = \Cache::redis()->getClient();
		$ttlFirst = $client->ttl(ThrottleAiLib::PREFIX.'login-ip-'.self::IP);

		$this->assertTrue($ttlFirst > 0 and $ttlFirst <= 60, 'la première tentative pose la fenêtre');

		ThrottleAiLib::hit('login-ip', self::IP, 3600);

		$ttlSecond = $client->ttl(ThrottleAiLib::PREFIX.'login-ip-'.self::IP);

		$this->assertTrue($ttlSecond <= 60, 'la seconde ne la prolonge pas');

	}

}
?>
