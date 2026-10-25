<?php
namespace dev;

/**
 * Un appel d'API refusé à l'authentification ne laissait aucune trace. \ApiRoute::run() crée son
 * journal après getAuthorization(), et sa journalisation finale est gardée par `$eLog->notEmpty()` :
 * la structure dont la clé venait d'être refusée ne voyait rien sur son écran de clés, et personne
 * ne pouvait dire si une intégration tombait sur une clé expirée ou révoquée.
 *
 * Ce qui rend la trace possible sans ouvrir une écriture en base à un appelant anonyme est la
 * signature du jeton : \api\AccessTokenLib::getFromSignedRequest() ne rend une clé que si le serveur
 * a signé le jeton lui-même, ce qu'un appelant ne peut pas produire. Les cas ci-dessous éprouvent les
 * deux sens, le refus imputable et les refus qui ne le sont pas.
 *
 * La seconde moitié du fichier porte la même question un cran plus tôt : version vide ou inconnue,
 * route vide ou inconnue tombent avant même que la route soit résolue, donc avant que
 * createFromRoute() ait de quoi être appelée, et ces quatre refus ne laissaient aucune trace non plus.
 *
 * Lancement : dev/test package=dev file=ApiAuthenticationLog
 */
class ApiAuthenticationLogTest extends ApiTest {

	const ROUTE = 'devAuthenticationProbe';

	protected function getTransactionModel(): \ModuleModel {
		return new \api\LogModel();
	}

	public function setUp(): void {

		parent::setUp();

		$this->registerProbeRoute();

	}

	public function tearDown(): void {

		$this->forgetProbeRoute();

		parent::tearDown();

	}

	/** Un appel refusé parce que la clé est révoquée se retrouve sur l'écran de clés de sa structure. */
	public function testRevokedKeyIsLogged(): void {

		[$eAccessToken, $jwt] = $this->issueKey();

		\api\AccessTokenLib::revoke($eAccessToken);

		$before = \api\Log::model()->count();

		$response = $this->callWithAuthorization($jwt);

		$this->assertEquals(401, $response['status']);
		$this->assertEquals('authentication_error', $response['body'][0]['type'] ?? NULL);

		$this->assertEquals($before + 1, \api\Log::model()->count());

		// La ligne se lit par sa clé : lue par son seul rang, l'assertion nommerait l'appel d'un autre
		// test du paquet le jour où le refus cesse d'être journalisé.
		$eLog = \api\Log::model()
			->select(['id', 'reference', 'route', 'status', 'responseStatusCode'])
			->whereToken($eAccessToken)
			->get();

		$this->assertEquals(TRUE, $eLog->notEmpty());
		$this->assertEquals($this->eStructure['id'], $eLog['reference']);
		$this->assertEquals(self::ROUTE, $eLog['route']);
		$this->assertEquals(\api\Log::ERROR, $eLog['status']);
		$this->assertEquals(401, $eLog['responseStatusCode']);

	}

	/**
	 * Le journal ne porte pas le jeton qu'il refuse : createFromRoute() retire l'en-tête
	 * Authorization, et une ligne de refus le rendrait lisible à tout administrateur de la structure.
	 */
	public function testLoggedHeadersDoNotCarryTheToken(): void {

		[$eAccessToken, $jwt] = $this->issueKey();

		\api\AccessTokenLib::revoke($eAccessToken);

		$this->callWithAuthorization($jwt);

		$eLog = \api\Log::model()
			->select(['id', 'requestHeaders'])
			->whereToken($eAccessToken)
			->get();

		$this->assertEquals(TRUE, $eLog->notEmpty());
		$this->assertEquals(FALSE, str_contains(strtolower(json_encode($eLog['requestHeaders'])), 'bearer'));

	}

	/**
	 * Contrôle du contrôle, dans l'autre sens : un jeton que le serveur n'a pas signé n'écrit rien.
	 * Sans cette borne, n'importe qui ferait grossir le journal d'une structure en lui envoyant des
	 * jetons inventés.
	 */
	public function testForgedSignatureIsNotLogged(): void {

		[, $jwt] = $this->issueKey();

		$before = \api\Log::model()->count();

		$response = $this->callWithAuthorization($this->breakSignature($jwt));

		$this->assertEquals(401, $response['status']);
		$this->assertEquals($before, \api\Log::model()->count());

	}

	/** Un appel sans en-tête d'autorisation n'a aucune clé à qui être imputé. */
	public function testMissingAuthorizationIsNotLogged(): void {

		$before = \api\Log::model()->count();

		$response = $this->callWithAuthorization(NULL);

		$this->assertEquals(401, $response['status']);
		$this->assertEquals($before, \api\Log::model()->count());

	}

	/**
	 * Les quatre refus qui tombent avant que la route soit résolue laissent chacun leur ligne, imputée
	 * à la structure dont l'appel employait la clé.
	 */
	public function testRefusalBeforeRouteResolutionIsLogged(): void {

		$refusals = [
			'version vide' => ['', self::ROUTE, 400],
			'version inconnue' => ['9.9', self::ROUTE, 400],
			'route vide' => [self::VERSION, '', 400],
			'route inconnue' => [self::VERSION, 'devRouteThatDoesNotExist', 404],
		];

		foreach($refusals as $name => [$version, $route, $status]) {

			$before = \api\Log::model()->count();

			$response = $this->callApi('GET', $route, version: $version);

			$this->assertEquals($status, $response['status'], $name.' : le code rendu');
			$this->assertEquals($before + 1, \api\Log::model()->count(), $name.' : une ligne de journal');

			$eLog = \api\Log::model()
				->select(['id', 'reference', 'route', 'status', 'responseStatusCode'])
				->sort(['id' => SORT_DESC])
				->get();

			$this->assertEquals($this->eStructure['id'], $eLog['reference'], $name.' : imputée à la structure');
			$this->assertEquals(\api\Log::ERROR, $eLog['status'], $name.' : en échec');
			$this->assertEquals($status, $eLog['responseStatusCode'], $name.' : le code du refus');
			$this->assertEquals($route, $eLog['route'], $name.' : la route demandée');

		}

	}

	/**
	 * La route vient du client, sa longueur n'est bornée par rien : sans troncature, l'INSERT du
	 * journal ferait échouer l'appel qu'il journalise, ce qui est le contraire de ce que ce lot pose.
	 */
	public function testAskedRouteIsTruncatedToTheColumn(): void {

		$length = \api\Log::model()->getPropertyRange('route')[1];

		$response = $this->callApi('GET', str_repeat('z', $length * 2), version: self::VERSION);

		$this->assertEquals(404, $response['status']);

		$eLog = \api\Log::model()
			->select(['id', 'route'])
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertEquals($length, mb_strlen($eLog['route']));

	}

	/**
	 * Contrôle du contrôle, dans l'autre sens : un refus antérieur à la route n'a pas davantage de clé
	 * à qui être imputé quand l'appelant n'en présente aucune.
	 */
	public function testAnonymousRefusalBeforeRouteResolutionIsNotLogged(): void {

		$before = \api\Log::model()->count();

		$response = $this->callWithAuthorization(NULL, 'devRouteThatDoesNotExist');

		$this->assertEquals(404, $response['status']);
		$this->assertEquals($before, \api\Log::model()->count());

	}

	/** Un appel qui aboutit garde une seule ligne : le garde du catch ne la double pas. */
	public function testSuccessfulCallIsLoggedOnce(): void {

		$before = \api\Log::model()->count();

		$response = $this->callApi('GET', self::ROUTE);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($before + 1, \api\Log::model()->count());

	}

	/**
	 * Une clé réelle et le jeton qui la porte. L'émission est le seul chemin qui produise un jeton
	 * signé par le serveur, et la ligne qu'elle écrit est celle que le refus doit retrouver.
	 */
	private function issueKey(): array {

		$eUser = (\api\ApiSetting::$devAccessTokenUser)($this->eStructure['id']);

		$name = 'Clé du test d\'authentification';

		$jwt = \api\PersonalAccessTokenLib::issue($eUser, $name, $this->eStructure['id'], ['production:read']);

		$this->assertDiffer(NULL, $jwt, 'aucun jeton émis : la paire RSA du serveur manque, la poser par api/configure/keyAi');

		$eAccessToken = \api\AccessToken::model()
			->select(\api\AccessToken::getSelection())
			->whereReference($this->eStructure['id'])
			->whereName($name)
			->sort(['id' => SORT_DESC])
			->get();

		return [$eAccessToken, $jwt];

	}

	/** La signature d'un JWT est son troisième segment : un caractère changé suffit à l'invalider. */
	private function breakSignature(string $jwt): string {

		$segments = explode('.', $jwt);

		$last = substr($segments[2], -1);
		$segments[2] = substr($segments[2], 0, -1).($last === 'A' ? 'B' : 'A');

		return implode('.', $segments);

	}

	/**
	 * L'appel passe par le vrai getAuthorization() : $devAccessToken, que dev\ApiTest pose pour
	 * court-circuiter OAuth, se retire le temps du cas et se repose ensuite, le réglage étant statique
	 * et partagé avec les fichiers de test suivants du paquet.
	 *
	 * L'en-tête voyage par $_SERVER parce que ServerRequestCreator::fromGlobals() lit les en-têtes par
	 * getallheaders(), que GetAllHeadersPolyfill dérive de $_SERVER en CLI. $_SERVER se restaure en
	 * entier, le paquet entier tournant dans un seul processus : une clé laissée ici serait ensuite
	 * journalisée par createFromRoute() dans tous les fichiers de test qui suivent.
	 */
	private function callWithAuthorization(?string $jwt, string $route = self::ROUTE, ?string $version = NULL): array {

		$server = $_SERVER;

		\api\ApiSetting::$devAccessToken = NULL;

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_HOST'] = 'dev-ouvretaferme.org';
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

		if($jwt === NULL) {
			unset($_SERVER['HTTP_AUTHORIZATION']);
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$jwt;
		}

		try {

			return $this->callApi('GET', $route, version: $version);

		} finally {

			$_SERVER = $server;

			\api\ApiSetting::$devAccessToken = fn() => $this->getDevAccessToken($this->eStructure['id']);

		}

	}

	/**
	 * \Api::$routes est privée, et la documentation la lit déjà par réflexion
	 * (\dev\ApiDocLib::getRouteInfoForClass). L'y écrire évite de faire dépendre le cas d'une route du
	 * produit, dont les gardes refuseraient avant le point éprouvé.
	 */
	private function registerProbeRoute(): void {

		$property = new \ReflectionProperty(\Api::class, 'routes');
		$property->setAccessible(TRUE);

		$routes = $property->getValue();

		$routes['GET'][self::ROUTE] = [
			'requestMethod' => 'GET',
			'requestRoute' => self::ROUTE,
			'class' => ApiAuthenticationProbe::class,
			'id' => FALSE,
			'callback' => fn(\ApiData $data) => $data->push('probe', TRUE),
			'arguments' => [],
			'scope' => 'production:read'
		];

		$property->setValue(NULL, $routes);

	}

	/** La route ne survit pas au cas : le registre est statique, les tests suivants la verraient. */
	private function forgetProbeRoute(): void {

		$property = new \ReflectionProperty(\Api::class, 'routes');
		$property->setAccessible(TRUE);

		$routes = $property->getValue();

		foreach($routes as $requestMethod => $cRoute) {
			unset($routes[$requestMethod][self::ROUTE]);
		}

		$property->setValue(NULL, $routes);

	}

}

/**
 * La ressource de la sonde. Elle étend \Api et non \ModuleApi : le cas ne porte sur aucun module, et
 * \ApiData en a seulement besoin pour instancier la classe de la route.
 */
class ApiAuthenticationProbe extends \Api {

	public string $scopeName = 'production';

}
?>
