<?php
namespace dev;

/**
 * Une requête sans jeton valide ne consomme aucun quota de structure : \api\RateLimitLib compte sur
 * la référence de la clé, qu'un appel refusé n'a pas. Chaque refus coûtait pourtant le chargement de
 * PHP, la résolution de la route et la vérification de la signature, et rien ne le comptait. Les
 * refus d'authentification se comptent donc par adresse (\user\ThrottleAiLib, comme les échecs de
 * connexion), et l'adresse qui a atteint le seuil est refusée avant la vérification.
 *
 * Les cas tournent sous une adresse de test (TEST-NET-3), pour ne pas peser sur celle de la ligne de
 * commande que partagent les autres fichiers de test, et effacent leur compteur en sortant.
 *
 * Lancement : dev/test package=dev file=ApiThrottle
 */
class ApiThrottleTest extends ApiTest {

	const IP = '203.0.113.77';
	const IP_OTHER = '203.0.113.78';

	const ROUTE = 'system';

	private array $serverInitial;

	protected function getTransactionModel(): \ModuleModel {
		return new \api\LogModel();
	}

	public function init(): void {

		parent::init();

		if(\Cache::isDisabled()) {
			throw new \Exception('Le cache est désactivé, la limitation ne peut pas se mesurer');
		}

	}

	public function setUp(): void {

		parent::setUp();

		$this->serverInitial = $_SERVER;

		\user\ThrottleAiLib::clear('api-ip', self::IP);
		\user\ThrottleAiLib::clear('api-ip', self::IP_OTHER);

	}

	public function tearDown(): void {

		\user\ThrottleAiLib::clear('api-ip', self::IP);
		\user\ThrottleAiLib::clear('api-ip', self::IP_OTHER);

		$_SERVER = $this->serverInitial;

		parent::tearDown();

	}

	public function testRefusedAuthenticationIsCounted(): void {

		$response = $this->callWithoutToken(self::IP);

		$this->assertEquals(401, $response['status']);
		$this->assertEquals(1, \user\ThrottleAiLib::count('api-ip', self::IP));

	}

	/** Une intégration dont le jeton est valide n'use rien : seuls les refus comptent. */
	public function testAcceptedCallIsNotCounted(): void {

		$_SERVER['REMOTE_ADDR'] = self::IP;

		$response = $this->callApi('GET', self::ROUTE);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(0, \user\ThrottleAiLib::count('api-ip', self::IP));

	}

	/**
	 * Au seuil, l'adresse est refusée en 429 avant toute vérification, jeton valide compris, et une
	 * autre adresse passe toujours.
	 */
	public function testThrottledAddressIsRefusedBeforeAuthentication(): void {

		$_SERVER['REMOTE_ADDR'] = self::IP;

		for($i = 1; $i < \user\ThrottleAiLib::LIMIT_API_IP; $i++) {
			\user\ThrottleAiLib::failApi();
		}

		$this->assertEquals(200, $this->callApi('GET', self::ROUTE)['status'], 'un refus sous le seuil');

		\user\ThrottleAiLib::failApi();

		$response = $this->callApi('GET', self::ROUTE);

		$this->assertEquals(429, $response['status'], 'au seuil');
		$this->assertEquals('rate_limit_authentication', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(\user\ThrottleAiLib::LIMIT_API_IP, \user\ThrottleAiLib::count('api-ip', self::IP), 'le refus au seuil ne compte pas lui-même');

		$_SERVER['REMOTE_ADDR'] = self::IP_OTHER;

		$this->assertEquals(200, $this->callApi('GET', self::ROUTE)['status'], 'une autre adresse');

	}

	public function testRefusalHasMessage(): void {

		$this->assertEquals(
			"Trop d'appels ont été refusés à l'authentification depuis cette adresse, réessayez dans quelques minutes",
			\api\AlertUi::getError('Api::rate_limit_authentication', [])
		);

	}

	/**
	 * Le vrai getAuthorization(), sans en-tête : $devAccessToken se retire le temps de l'appel, comme
	 * dans ApiAuthenticationLog.t.php, et se repose ensuite.
	 */
	private function callWithoutToken(string $ip): array {

		$_SERVER['REMOTE_ADDR'] = $ip;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_HOST'] = 'dev-ouvretaferme.org';
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		unset($_SERVER['HTTP_AUTHORIZATION']);

		\api\ApiSetting::$devAccessToken = NULL;

		try {
			return $this->callApi('GET', self::ROUTE);
		} finally {
			\api\ApiSetting::$devAccessToken = fn() => $this->getDevAccessToken($this->eStructure['id']);
		}

	}

}
?>
