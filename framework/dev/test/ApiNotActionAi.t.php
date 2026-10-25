<?php
namespace dev;

/**
 * \ApiRoute::run() ne connaissait que les ApiException. Un Lib qui refuse une donnée lève une action
 * web — NotExpectedAction, NotExistsAction —, dont la requête est `error:404` : l'appelant recevait
 * une page d'erreur HTML là où il décode du JSON, et se cassait au décodage plutôt que de lire le
 * refus. 57 de ces levées vivent dans les Libs, dont huit fichiers de modules exposés.
 *
 * Le cas passe par une route sonde plutôt que par une route du produit : celles qui pourraient y
 * mener portent toutes une garde qui refuse avant, et une garde de plus les rendrait à leur tour
 * intestables. La sonde lève depuis son callback, exactement là où un Lib lèverait.
 *
 * Lancement : dev/test package=dev file=ApiNotActionAi
 */
class ApiNotActionAiTest extends ApiTest {

	const ROUTE = 'devNotActionProbeAi';

	protected function getTransactionModel(): \ModuleModel {
		return new \api\LogModel();
	}

	public function tearDown(): void {

		$this->forgetProbeRoutes();

		parent::tearDown();

	}

	/** Une donnée refusée par un Lib se lit comme une erreur d'API, pas comme une page introuvable. */
	public function testNotExpectedActionIsRenderedAsApiError(): void {

		$this->registerProbeRoute('PATCH', fn(\ApiData $data) => throw new \NotExpectedAction('sonde'));

		$response = $this->callProbe();

		if($response === NULL) {
			return;
		}

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('unexpected_data', $response['body'][0]['code'] ?? NULL);

		// Le message d'une NotAction est documenté comme interne : il ne part pas chez l'appelant.
		$this->assertDiffer('sonde', $response['body'][0]['message'] ?? NULL);

	}

	/** Les deux sous-classes ne disent pas la même chose et ne rendent donc pas le même code. */
	public function testNotExistsActionIsRenderedAsApiError(): void {

		$this->registerProbeRoute('PATCH', fn(\ApiData $data) => throw new \NotExistsAction('sonde'));

		$response = $this->callProbe();

		if($response === NULL) {
			return;
		}

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Contrôle du contrôle : les deux cas passeraient si la sonde ne levait plus rien. Une route qui
	 * rend normalement doit continuer de rendre 200.
	 */
	public function testProbeRouteAnswersWhenNothingIsThrown(): void {

		$this->registerProbeRoute('PATCH', fn(\ApiData $data) => $data->push('probe', TRUE));

		$response = $this->callApi('PATCH', self::ROUTE, body: []);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(TRUE, $response['body']['probe'] ?? NULL);

	}

	/**
	 * L'appel de la sonde, dont l'échec attendu est justement que rien ne soit levé jusqu'ici : sans le
	 * filet, l'action web traverse \ApiRoute::run() et interrompt le cas sans qu'aucune assertion ne
	 * soit comptée — le test resterait vert sur la régression qu'il est censé voir.
	 */
	private function callProbe(): ?array {

		try {
			return $this->callApi('PATCH', self::ROUTE, body: []);
		} catch(\NotAction) {
			$this->assertFail('l\'action web traverse ApiRoute::run() au lieu d\'être rendue en JSON');
			return NULL;
		}

	}

	/**
	 * \Api::$routes est privée, et la documentation la lit déjà par réflexion
	 * (\dev\ApiDocLib::getRouteInfoForClass). L'y écrire est le seul moyen de faire passer un callback
	 * par le vrai \ApiRoute::run() sans exposer une ressource pour de bon.
	 */
	private function registerProbeRoute(string $requestMethod, \Closure $callback): void {

		$property = new \ReflectionProperty(\Api::class, 'routes');
		$property->setAccessible(TRUE);

		$routes = $property->getValue();

		$routes[$requestMethod][self::ROUTE] = [
			'requestMethod' => $requestMethod,
			'requestRoute' => self::ROUTE,
			'class' => ApiNotActionProbe::class,
			'id' => FALSE,
			'callback' => $callback,
			'arguments' => [],
			'scope' => 'general:write'
		];

		$property->setValue(NULL, $routes);

	}

	/** La route ne survit pas au cas : le registre est statique, les tests suivants la verraient. */
	private function forgetProbeRoutes(): void {

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
 * \ApiData en a seulement besoin pour instancier la classe de la route et lui faire lire ses
 * arguments, qu'elle n'a pas.
 */
class ApiNotActionProbe extends \Api {

	public string $scopeName = 'general';

}
?>
