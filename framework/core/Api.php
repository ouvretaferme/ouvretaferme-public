<?php
class ApiRoute {

	private static ?string $version = NULL;

	private static array $autoloadCallback = [];

	// PHP n'admet pas de new dans l'initialiseur d'une propriété : elle reste non initialisée jusqu'au premier appel d'API
	private static \api\Log $eLog;

	public static function getVersion(): string {
		return self::$version ?? throw new ApiException('Missing API version', 'missing_api_version', 500, []);
	}

	public static function getOrigin(): ?string {
		return self::getLog()->notEmpty() ? 'api' : NULL;
	}

	public static function getLog(): \api\Log {
		return self::$eLog ?? new \api\Log();
	}

	public static function setVersion(string $version): void {
		if(in_array($version, \api\ApiSetting::VERSIONS)) {
			self::$version = $version;
		} else {
			throw new ApiException('Unknown version', 'invalid_api_version', 400, []);
		}
	}

	public static function autoload(Closure $autoloadCallback): void {
		self::$autoloadCallback[] = $autoloadCallback;
	}

	public static function expose(string $class): void {

		$newClass = $class.'_'.str_replace('.', '_', self::getVersion()).'Api';
		new $newClass();

	}

	public static function run(string $version, string $route): void {

		$errors = [];
		$eLog = new \api\Log();
		$routeInfo = NULL;

		try {

			if($version === '') {
				throw new ApiException('Bad request', 'bad_request', 400, []);
			}

			self::setVersion($version);

			foreach(self::$autoloadCallback as $autoload) {
				($autoload)();
			}
			self::$autoloadCallback = [];

			if($route === '') {
				throw new ApiException('Missing route', 'missing_route', 400, []);
			}

			$routeInfo = Api::getRoute($route, GET('id'));

			if(\user\ThrottleAiLib::acceptApi() === FALSE) {
				throw new RateLimitApiException('Too many failed authentications from this address, retry later', 'rate_limit_authentication', [], \user\ThrottleAiLib::WINDOW_API);
			}

			[
				$eAccessToken,
				$authorizedScopes
			] = self::getAuthorization();

			$data = new ApiData(
				$routeInfo,
				$eAccessToken
			);

			$eLog = \api\LogLib::createFromRoute($routeInfo, $eAccessToken);
			self::$eLog = $eLog;

			\api\RateLimitLib::validate($eAccessToken['reference']);

			$data->authorized();

			self::validateScope($data, $authorizedScopes, $routeInfo['scope']);
			self::loadInput($data, $routeInfo);

			($routeInfo['callback'])($data);

			$file = $data->getFile();

			if($file !== NULL) {

				// Le journal garde le nom du fichier, pas son contenu, qu'une colonne JSON ne porte pas.
				\api\LogLib::updateResponse($eLog, \api\Log::SUCCESS, ['filename' => $file['filename']], $data->httpCode);

				$action = new PdfAction($file['content'], $file['filename']);

			} else {

				$output = $data->getOutput();

				\api\LogLib::updateResponse($eLog, \api\Log::SUCCESS, $output, $data->httpCode);

				if($output) {
					$action = new JsonAction($output);
				} else {
					$action = new VoidAction();
				}

			}

			$action->setStatusCode($data->httpCode);

			self::$eLog = new \api\Log();

			throw $action;

		} catch(AuthentificationApiException $e) {

			\user\ThrottleAiLib::failApi();

			$errors[] = [
				'type' => 'authentication_error',
				'code' => $e->getCode(),
				'message' => $e->getOutputMessage()
			];

			// Un refus d'authentification précède la création du journal : sans cette ligne, un appel
			// refusé ne laisse aucune trace sur l'écran de clés de la structure dont il employait la
			// clé. L'exception ne porte un jeton que si le serveur l'a signé lui-même.
			$eLog = self::logRefusal($e->eAccessToken, $routeInfo, $route);

			$errorStatus = \api\Log::ERROR;

		} catch(ResourceApiException $e) {

			foreach($e->fw->get() as [$namespace, $failName, $arguments, $wrapper]) {

				$errors[] = [
					'type' => 'resource_error',
					'code' => $failName,
					'message' => self::getTextMessage($e->fw->getMessage($namespace, $failName, $arguments)) ?? self::getCoreFailMessage($failName, $arguments),
				];

			}

			$errorStatus = \api\Log::ERROR;

		} catch(RateLimitApiException $e) {

			$errors[] = [
				'type' => 'api_error',
				'code' => $e->getCode(),
				'message' => $e->getOutputMessage()
			];

			$errorStatus = \api\Log::RATE_LIMIT;

		} catch(ApiException $e) {

			$errors[] = [
				'type' => 'api_error',
				'code' => $e->getCode(),
				'message' => $e->getOutputMessage()
			];

			// Version vide ou inconnue, route vide ou inconnue, clés du serveur absentes : ces refus
			// tombent avant que le journal existe, et l'appel ne laissait alors aucune trace sur
			// l'écran de clés de la structure dont il employait la clé. Les refus postérieurs ont déjà
			// leur ligne, que ce garde préserve.
			if($eLog->empty()) {
				$eLog = self::logRefusal(self::getRefusalAccessToken(), $routeInfo, $route);
			}

			$errorStatus = \api\Log::ERROR;

		} catch(NotAction $notAction) {

			// Un Lib qui refuse une donnée lève une action web : NotExpectedAction et NotExistsAction
			// portent `error:404`, une page d'erreur HTML, là où l'appelant attend le contrat d'erreur
			// en JSON et se casserait au décodage. Leur message est documenté comme interne (cf
			// NotAction::__construct) et ne sort donc pas d'ici.
			$e = new ApiException(
				$notAction instanceof NotExpectedAction ? 'Unexpected data' : 'Not found',
				$notAction instanceof NotExpectedAction ? 'unexpected_data' : 'not_found',
				404,
				[]
			);

			$errors[] = [
				'type' => 'api_error',
				'code' => $e->getCode(),
				'message' => $e->getOutputMessage()
			];

			$errorStatus = \api\Log::ERROR;

		}

		$action = new JsonAction($errors);
		$action->setStatusCode($e->getStatusCode());

		if($e instanceof RateLimitApiException) {
			header('Retry-After: '.$e->retryAfter);
		}

		if($eLog->notEmpty()) {
			\api\LogLib::updateResponse($eLog, $errorStatus, $errors, $e->getStatusCode());
		}

		self::$eLog = new \api\Log();

		throw $action;

	}

	/**
	 * Un message de package est écrit pour l'écran : il peut porter du balisage et un bouton qui mène
	 * au formulaire à corriger, un geste qu'aucun client d'API ne peut faire. Le bouton tombe avec son
	 * libellé, le reste du texte garde ses mots.
	 */
	private static function getTextMessage(?string $message): ?string {

		if($message === NULL) {
			return NULL;
		}

		$message = preg_replace('/<a\b[^>]*\bclass="[^"]*\bbtn\b[^"]*"[^>]*>.*?<\/a>/is', '', $message);
		$message = preg_replace('/<br\s*\/?>/i', ' ', $message);
		$message = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim(preg_replace('/\s+/u', ' ', $message));

	}

	/**
	 * Le message d'un fail que son package ne traduit pas. Le socle en lève lui-même qu'aucun package
	 * ne connaît (`.missing`, `.input`, et les refus des validateurs d'argument, globaux, dont le nom
	 * ne porte pas de module), et un refus du schéma peut n'avoir jamais été atteint par l'écran qui
	 * aurait demandé son message.
	 */
	private static function getCoreFailMessage(string $failName, array $arguments): ?string {

		if(str_contains($failName, '::')) {

			[, $name] = explode('::', $failName, 2);
			// Un fail peut ne pas porter d'étape (`Module::internal`)
			[$property, $step] = explode('.', $name, 2) + [1 => NULL];

			return match($step) {
				'missing' => \api\AlertUi::getError('Api::property.missing', ['property' => $property]),
				'html' => \api\AlertUi::getError('Api::property.html', ['property' => $property]),
				default => \api\AlertUi::getError('Api::property.check', ['property' => $property])
			};

		} else {

			[$argument, $step] = explode('.', $failName, 2) + [1 => NULL];

			return match($step) {
				'max' => \api\AlertUi::getError('Api::argument.max', ['argument' => $argument, 'max' => $arguments['max']]),
				default => \api\AlertUi::getError('Api::argument.check', ['argument' => $argument])
			};

		}

	}

	/**
	 * La ligne de journal d'un appel refusé, vide quand rien n'autorise à l'écrire.
	 *
	 * Le limiteur garde cette écriture au lieu de la suivre : le chemin d'un refus ne passe jamais par
	 * le point où run() l'appelle, et un jeton mort écrirait donc sans consommer de quota. Au-delà du
	 * plafond, l'appel garde le code de son propre refus, qui dit à l'appelant ce qu'il a à corriger,
	 * là où un 429 l'enverrait seulement ralentir.
	 */
	private static function logRefusal(\api\AccessToken $eAccessToken, ?array $routeInfo, string $route): \api\Log {

		if($eAccessToken->empty()) {
			return new \api\Log();
		}

		try {
			\api\RateLimitLib::validate($eAccessToken['reference']);
		} catch(RateLimitApiException) {
			return new \api\Log();
		}

		if($routeInfo === NULL) {
			return \api\LogLib::createFromRequest($eAccessToken, $route);
		}

		return \api\LogLib::createFromRoute($routeInfo, $eAccessToken);

	}

	/**
	 * La clé à qui imputer un appel refusé avant que l'authentification ait eu lieu : celle que porte
	 * un jeton signé par le serveur, ou, en dev, celle que getAuthorization() aurait rendue.
	 */
	private static function getRefusalAccessToken(): \api\AccessToken {

		if(
			LIME_ENV === 'dev' and
			\api\ApiSetting::$devAccessToken !== NULL
		) {
			return (\api\ApiSetting::$devAccessToken)();
		}

		return \api\AccessTokenLib::getFromSignedRequest(\api\ServerLib::getRequestFromGlobals());

	}

	private static function getAuthorization(): array {

		if(
			LIME_ENV === 'dev' and
			\api\ApiSetting::$devAccessToken !== NULL
		) {

			$eAccessToken = (\api\ApiSetting::$devAccessToken)();
			$scopes = \api\ApiSetting::$devScopes ?? \api\ApiSetting::PERSONAL_SCOPE_NAMES;

		} else {


			$request = \api\ServerLib::getRequestFromGlobals();

			try {

				$request = \api\ServerLib::getResourceServer()->validateAuthenticatedRequest($request);

			} catch(\League\OAuth2\Server\Exception\OAuthServerException $exception) {

				$response = $exception->generateHttpResponse(\api\ServerLib::getResponse());
				$responseArray = json_decode((string)$response->getBody(), TRUE);

				throw new AuthentificationApiException(
					$responseArray['error_description'].' ('.$responseArray['hint'].')',
					$responseArray['error'],
					401,
					NULL,
					\api\AccessTokenLib::getFromSignedRequest($request)
				);

			} catch(\LogicException $exception) {

				throw new ApiException('API keys are missing or invalid on the server', 'invalid_server_keys', 500, []);

			}

			$eAccessToken = \api\AccessTokenLib::getByTokenId($request->getAttribute('oauth_access_token_id'));

			if($eAccessToken->empty()) {
				throw new AuthentificationApiException('Invalid token', 'invalid_token', 401, []);
			}

			$scopes = $request->getAttribute('oauth_scopes');

		}

		return [
			$eAccessToken,
			$scopes
		];

	}

	private static function validateScope(ApiInternalData $data, array $authorizedScopes, string $scope): void {

		if($data->acceptScope($scope) === FALSE) {
			throw new ApiException('Scope \''.$scope.'\' is not open yet', 'scope_not_open', 403, ['scope' => $scope]);
		}

		if(in_array($scope, $authorizedScopes) === FALSE) {
			throw new ApiException('Scope \''.$scope.'\' is required', 'scope_not_allowed', 403, ['scope' => $scope]);
		};
	}

	private static function loadInput(ApiData $data, array $routeInfo): void {

		$fw = new FailWatch();

		$data->api->load($data, $routeInfo);

		if($fw->ko()) {
			throw new ResourceApiException($fw);
		}
	}

}

abstract class Api {

	public string $scopeName;

	private static array $routes = [
		'POST' => [], // CREATE
		'GET' => [], // READ
		'PUT' => [], // UPDATE
		'DELETE' => [], // DELETE
		'PATCH' => [], // ACTION
	];

	private static array $elements = [
	];

	public function requestGet(string $route, array $arguments, Closure $callback, ?string $scope = NULL): void {
		$this->setRoute('GET', $route, $arguments, $callback, $scope ?? $this->scopeName.':read');
	}

	public function requestPost(string $route, array $arguments, Closure $callback, ?string $scope = NULL): void {
		$this->setRoute('POST', $route, $arguments, $callback, $scope ?? $this->scopeName.':write');
	}

	public function requestPut(string $route, array $arguments, Closure $callback, ?string $scope = NULL): void {
		$this->setRoute('PUT', $route, $arguments, $callback, $scope ?? $this->scopeName.':write');
	}

	public function requestPatch(string $route, array $arguments, Closure $callback, ?string $scope = NULL): void {
		$this->setRoute('PATCH', $route, $arguments, $callback, $scope ?? $this->scopeName.':write');
	}

	public function requestDelete(string $route, array $arguments, Closure $callback, ?string $scope = NULL): void {
		$this->setRoute('DELETE', $route, $arguments, $callback, $scope ?? $this->scopeName.':write');
	}

	public static function setRoute(string $requestMethod, string $route, array $arguments, Closure $callback, string $scope): void {

		$class = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[2]['class'];

		self::$routes[$requestMethod][$route] = [
			'requestMethod' => $requestMethod,
			'requestRoute' => $route,
			'class' => $class,
			'id' => str_contains($route, '/{id}'),
			'callback' => $callback,
			'arguments' => $arguments,
			'scope' => $scope
		];

	}

	/**
	 * La réécriture du serveur lit tout second segment comme un identifiant (main/conf/rewrite) :
	 * timesheet/distribution arrive en timesheet/{id}. Une route déclarée sous ce nom littéral passe
	 * donc avant, un identifiant étant toujours numérique (ModuleApi::load()).
	 */
	public static function getRoute(string $route, string $id = ''): array {

		$routes = self::$routes[Route::getRequestMethod()];
		$routeLiteral = str_replace('{id}', $id, $route);

		if($id !== '' and array_key_exists($routeLiteral, $routes)) {
			return $routes[$routeLiteral];
		}

		if(array_key_exists($route, $routes)) {
			return $routes[$route];
		}

		foreach(self::$routes as $routesOther) {

			if(
				array_key_exists($route, $routesOther) or
				($id !== '' and array_key_exists($routeLiteral, $routesOther))
			) {
				throw new ApiException('Method '.Route::getRequestMethod().' not allowed on route \''.$route.'\'', 'method_not_allowed', 405, ['route' => $route, 'method' => Route::getRequestMethod()]);
			}

		}

		throw new ApiException('Unknown route \''.$route.'\'', 'unknown_route', 404, ['route' => $route]);

	}

	public static function element(string $module, Closure $properties, array $correlations = [], ?Closure $application = NULL): void {

		self::$elements[$module][] = [
			'module' => $module,
			'class' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]['class'],
			'properties' => $properties,
			'correlations' => $correlations,
			'application' => $application,
		];

	}

	public static function getElement(string $module, Element $e): array {

		$list = self::$elements[$module] ?? throw new ApiException('No element for module \''.$module.'\'', 'internal_error', 500, []);

		$default = NULL;

		foreach($list as $element) {

			if($element['application'] === NULL) {
				$default ??= $element;
			} else if(($element['application'])($e)) {
				return $element;
			}

		}

		return $default ?? throw new ApiException('No element applies to this '.$module, 'internal_error', 500, []);

	}

	public function load(ApiData $data, array $routeInfo): void {

		$arguments = $routeInfo['arguments'];

		if(array_key_exists('id', $arguments)) {
			throw new ApiException('Argument \'id\' must be used in route only', 'internal_error', 500, []);
		}

		switch($routeInfo['requestMethod']) {

			// Ni l'un ni l'autre n'a de corps : leurs arguments sont dans la query string.
			case 'GET' :
			case 'DELETE' :

				foreach($arguments as $name => $validator) {
					$this->getArgument($data, $_GET, $name, $validator);
				}

				break;

			case 'POST' :
			case 'PUT' :
			case 'PATCH' :

				// Une action sans argument n'a rien à envoyer : un corps vide vaut l'objet vide, et une
				// route qui attend des champs les refuse alors un par un, en nommant ceux qui manquent.
				$bodyJson = file_get_contents('php://input');

				$body = ($bodyJson === '') ? [] : json_decode($bodyJson, TRUE);

				if($body === NULL) {
					throw new ApiException('Body must be valid JSON', 'syntax_error', 400, []);
				}

				if(is_array($body) === FALSE) {
					throw new ApiException('Body must be a JSON object', 'body_not_object', 400, []);
				}

				$data->body = $body;

				if($arguments) {

					foreach($arguments as $name => $validator) {
						$this->getArgument($data, $data->body, $name, $validator);
					}

				}

				break;

		}

	}

	public function getArgument(ApiData $data, array $source, string $name, Validator $validator): void {

		$fw = new FailWatch();

		if(str_starts_with($name, '?')) {
			$name = substr($name, 1);
			$optional = TRUE;
		} else {
			$optional = FALSE;
		}

		if(array_key_exists($name, $source) === FALSE) {

			if($optional) {
				return;
			} else {
				Fail::log($name.'.check');
			}

		}

		if($fw->ok()) {

			$value = $source[$name];
			$validator->validate($name, $value);

			if($fw->ok()) {
				$data->set($name, $value);
			}

		}

	}

	/**
	 * Convertit le corps de la requête vers les noms et valeurs attendus par Element::build(),
	 * en appliquant les corrélations déclarées par element().
	 *
	 * Porté par Api et non par ApiData pour être redéfinissable : ApiData est instancié en dur par
	 * ApiRoute::run(), un module ne peut donc pas y substituer sa propre classe.
	 *
	 * Hors d'une modification qui nomme ses properties, la liste est tout getPropertiesCreate() ou
	 * getPropertiesUpdate() du Lib : y ajouter une propriété la rend obligatoire pour chaque client.
	 */
	public function convertProperties(ApiData $data, array $properties, \PropertyList $l): array {

		$module = $data->module;
		$input = $data->body;

		$correlations = Api::getElement($module, $data->e)['correlations'];

		foreach($properties as $property) {

			if(array_key_exists($property, $input) === FALSE) {
				$module::fail($property.'.missing');
				continue;
			}

			$correlation = $correlations[$property] ?? NULL;

			if($correlation === NULL) {
				continue;
			}

			$value = $input[$property];

			if(is_array($correlation)) {

				$key = array_search($value, $correlation);

				if($key === FALSE) {
					$module::fail($property.'.input');
				} else {
					$input[$property] = $key;
				}

			} else if($correlation instanceof Closure) {
				$input[$property] = $correlation($data, $value, 'in');
			}

		}

		return $input;

	}

}

// Une clé d'API n'a pas de membre connecté à peser : les écritures n'interrogent que les accept*, et une
// règle d'état rangée dans un can* ne protège pas l'API.
abstract class ModuleApi extends Api {

	public function create(string $route, ?Closure $build = NULL): void {

		$this->setRoute('POST', $route, [], function(ApiData $data) use ($build) {

			if($data->e->acceptCreate() === FALSE) {
				throw new \ApiException('Can not create', 'accept_create', 412, []);
			}

			$properties = $this->getPropertiesCreate($data);

			if(is_closure($properties)) {
				$properties = $properties->call($this, $data->e);
			}

			$fw = new FailWatch();

			$build ??= function(ApiData $data, $properties) use ($fw) {

				$l = new \PropertyList('create', 'api');

				$body = $this->convertProperties($data, $properties, $l);

				if($fw->ko()) {
					throw new ResourceApiException($fw);
				}

				$data->e->build($properties, $body, $l);

			};

			$build($data, $properties);

			if($fw->ko()) {
				throw new ResourceApiException($fw);
			}

			($data->module.'Lib')::create($data->e);

			if($fw->ko()) {
				throw new ResourceApiException($fw);
			}

			$this->afterCreate($data);

			$this->pushCreated($data);

		}, $this->scopeName.':write');

	}

	public function read(string $route): void {
		$this->setRoute('GET', $route, [], function(ApiData $data) {
			$data->pushObject(NULL, $data->e);
		}, $this->scopeName.':read');
	}

	public function list(string $route, array $moreArguments = [], ?Closure $get = NULL): void {

		$arguments = array_merge([
			'?start' => new \FilterValidator(['int32', 'min' => 1]),
			'?limit' => new \FilterValidator(['int32', 'min' => 0, 'max' => 500]),
			'?ids' => new \ArrayValidator(new \FilterValidator('serial'), max: 100),
			'?afterId' => new \FilterValidator('serial'),
			'?beforeId' => new \FilterValidator('serial')
		], $moreArguments);

		$get ??= fn($data) => $this->getList($data);

		$this->setRoute('GET', $route, $arguments, function(ApiData $data) use ($get) {

			$n = $get($data)
				->where('id', 'IN', fn() => $data->get('ids'), if: $data->exists('ids'))
				->count();

			$c = $get($data)
				->select($data->e::getSelection())
				->where('id', 'IN', fn() => $data->get('ids'), if: $data->exists('ids'))
				->where('id', '>', fn() => $data->get('afterId'), if: $data->exists('afterId'))
				->where('id', '<', fn() => $data->get('beforeId'), if: $data->exists('beforeId'))
				->sort([
					'id' => SORT_ASC
				])
				->getCollection(
					$data->get('?start', 1) - 1,
					$data->get('?limit', 100),
				);

			$data->push('count', $n);
			$data->pushCollection('list', $c);

		}, $this->scopeName.':read');
	}

	/**
	 * La liste d'une sous-collection, cadrée par la ressource qui la porte : market/{id}/itemsList rend
	 * les articles du marché {id}. C'est $data->e — la ressource de cette classe, chargée par son id —
	 * qui cadre la requête, et les éléments listés relèvent d'un autre module, exporté par sa propre
	 * déclaration element().
	 *
	 * Le cadrage est ici structurel plutôt que laissé à un filtre : une sous-collection n'a pas de sens
	 * hors de son parent, et l'oublier ne peut donc plus déverser toute la table.
	 */
	public function listBy(string $route, Closure $get, array $moreArguments = []): void {

		$arguments = array_merge([
			'?start' => new \FilterValidator(['int32', 'min' => 1]),
			'?limit' => new \FilterValidator(['int32', 'min' => 0, 'max' => 500]),
		], $moreArguments);

		$this->setRoute('GET', $route, $arguments, function(ApiData $data) use ($get) {

			$data->push('count', $get($data)->count());

			$data->pushCollection('list', $get($data)
				->select(($get($data)->getModule())::getSelection())
				->sort(['id' => SORT_ASC])
				->getCollection(
					$data->get('?start', 1) - 1,
					$data->get('?limit', 100),
				));

		}, $this->scopeName.':read');

	}

	public function update(string $route, ?Closure $build = NULL, bool $allowStandalone = TRUE): void {

		$this->setRoute('PUT', $route, [], function(ApiData $data) use ($build, $allowStandalone) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412, []);
			}

			$data->body['properties'] ??= NULL;

			$baseProperties = $this->getPropertiesUpdate($data);

			if(is_closure($baseProperties)) {
				$baseProperties = $baseProperties->call($this, $data->e);
			}

			$isStandalone = (
				$allowStandalone and
				$data->body['properties'] !== NULL
			);

			if($isStandalone) {

				$bodyProperties = $data->body['properties'];

				// Une liste vide ne modifie rien, et l'écriture partirait en UPDATE sans aucune colonne
				if(is_array($bodyProperties) === FALSE or $bodyProperties === []) {
					throw new ApiException('Properties must be a non-empty list', 'properties_empty', 400, []);
				}

				$this->validateStandalonePropertiesUpdate($data, $baseProperties, $bodyProperties);
				$selectedProperties = $bodyProperties;

			} else {
				$selectedProperties = $baseProperties;
			}

			$fw = new FailWatch();

			$build ??= function(ApiData $data, $selectedProperties) use ($isStandalone) {

				$l = new \PropertyList('update', 'api');
				$body = $this->convertProperties($data, $selectedProperties, $l);

				if($isStandalone) {
					($this->getStandalonePropertiesUpdate($data)['addBuildOnly'])($selectedProperties);
				}

				$data->e->build($selectedProperties, $body, $l);

			};

			$build($data, $selectedProperties);

			if($fw->ko()) {
				throw new ResourceApiException($fw);
			}

			($data->module.'Lib')::update($data->e, $selectedProperties);

			if($fw->ko()) {
				throw new ResourceApiException($fw);
			}

			$this->afterUpdate($data, $selectedProperties);

			$data->httpCode = 200;
			$data->set('id', $data->e['id']);

			$data->pushObject(NULL, $this->getById($data));

		}, $this->scopeName.':write');

	}

	public function delete(string $route): void {

		$this->setRoute('DELETE', $route, [], function(ApiData $data) {

			if($data->e->acceptDelete() === FALSE) {
				throw new \ApiException('Can not delete', 'accept_delete', 412, []);
			}

			$fw = new FailWatch();

			($data->module.'Lib')::delete($data->e);

			if($fw->ko()) {
				throw new ResourceApiException($fw);
			}

			$this->afterDelete($data);

		}, $this->scopeName.':write');

	}

	/**
	 * Ce qu'une ressource fait après l'écriture, une fois l'élément porteur de son id. Un module qui
	 * confie un effet à sa page plutôt qu'à son Lib, parce que d'autres chemins appellent ce Lib sans
	 * avoir de geste à raconter, n'a aucun autre endroit où le produire pour une clé sans page.
	 */
	public function afterCreate(ApiData $data): void {}

	public function afterUpdate(ApiData $data, array $properties): void {}

	public function afterDelete(ApiData $data): void {}

	/**
	 * Ce qu'une création rend, et sous quel code. Une ressource dont l'écriture ne produit pas
	 * toujours une ligne neuve la remplace : elle seule sait par quoi désigner ce que l'écriture a
	 * laissé, là où le défaut ci-dessous relit l'élément par son id.
	 */
	public function pushCreated(ApiData $data): void {

		$data->httpCode = 201;
		$data->set('id', $data->e['id']);

		$data->pushObject(NULL, $this->getById($data));

	}

	public function load(ApiData $data, array $routeInfo): void {

		if($routeInfo['id']) {

			$fw = new FailWatch();

			$this->getArgument($data, $_GET, 'id', new FilterValidator('serial'));

			$fw->validate(onKo: fn(FailWatch $fw) => throw new ResourceApiException($fw));

			$data->e = $this->getById($data);

		} else {
			$data->e = $this->getNewElement($data);
		}

		$data->module = $data->e->getModule();

		parent::load($data, $routeInfo);

	}

	public function getById(\ApiData $data): Element {

		$m = $this->getNewModel($data);

		$e = $m
			->select($this->getSelection($m))
			->whereId($data->get('id'))
			->get();

		if($e->empty()) {
			throw new \ApiException('Not found', 'not_found', 404, []);
		}

		return $e;

	}

	public function getList(\ApiData $data): ModuleModel {

		return $this->getNewModel($data);

	}

	public function getSelection(ModuleModel $m): array {

		return ($m->getModule())::getSelection();

	}

	abstract public function getNewModel(\ApiData $data): ModuleModel;
	abstract public function getNewElement(\ApiData $data): Element;

	public function getPropertiesCreate(ApiData $data): array|Closure {
		return ($data->module.'Lib')::getPropertiesCreate('api');
	}

	public function getPropertiesUpdate(ApiData $data): array|Closure {
		return ($data->module.'Lib')::getPropertiesUpdate('api');
	}

	public function getStandalonePropertiesUpdate(ApiData $data): array {
		return [
			'exclude' => [],
			'group' => [],
			'addBuildOnly' => function(&$properties) {

			}
		];
	}

	public function validateStandalonePropertiesUpdate(ApiData $data, array $baseProperties, array &$bodyProperties): void {

		$rules = $this->getStandalonePropertiesUpdate($data);

		foreach($bodyProperties as $property) {

			if(in_array($property, $baseProperties) === FALSE) {
				throw new ApiException('Unknown property \''.$property.'\'', 'property_unknown', 400, ['property' => $property]);
			}

			if(in_array($property, $rules['exclude'])) {
				throw new ApiException('Property \''.$property.'\' can not be updated standalone', 'property_not_allowed', 400, ['property' => $property]);
			}

			foreach($rules['group'] as $group) {

				if(
					in_array($property, $group) and
					count(array_intersect($group, $bodyProperties)) !== count($group)
				) {
					throw new ApiException('Properties \''.implode('\', \'', $group).'\' must be updated together', 'property_together', 400, ['properties' => implode(', ', $group)]);
				}

			}

		}

		// On réordonne les propriétés par rapport à $baseProperties
		$bodyProperties = array_intersect($baseProperties, $bodyProperties);

	}

}


/**
 * Une exception du socle passe les arguments de son message, même vides : le message rendu est
 * alors celui de \api\AlertUi sous la clé `Api::<code>`, le texte anglais n'étant qu'un repli.
 * Une ressource qui lève la sienne n'en passe pas, son message sort tel qu'elle l'a écrit, et le
 * code qu'elle réemploie ne lui impose pas le message générique du socle.
 */
class ApiException extends Exception {

	public function __construct(
		protected $message,
		protected $code,
		protected int $statusCode,
		protected ?array $arguments = NULL
	) {
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function getOutputMessage(): ?string {

		if($this->arguments === NULL) {
			return $this->message;
		}

		return \api\AlertUi::getError('Api::'.$this->code, $this->arguments) ?? $this->message;

	}

}

class RateLimitApiException extends ApiException {

	protected int $statusCode = 429;

	public function __construct(
		protected $message,
		protected $code,
		protected ?array $arguments = NULL,
		public readonly int $retryAfter = 1
	) {

	}

}

class AuthentificationApiException extends ApiException {

	public function __construct(
		protected $message,
		protected $code,
		protected int $statusCode,
		protected ?array $arguments = NULL,
		public readonly \api\AccessToken $eAccessToken = new \api\AccessToken()
	) {
	}

}

class ResourceApiException extends ApiException {

	protected int $statusCode = 400;

	public function __construct(
		public readonly FailWatch $fw
	) {

	}

}


abstract class ApiInternalData {

	public array $body = [];

	protected array $input = [];
	protected array $output = [];
	protected ?array $file = NULL;

	public int $httpCode = 200;

	public Api $api;
	public Element $e;
	public string $module;

	public function __construct(
		public readonly array $routeInfo,
		public readonly \api\AccessToken $eAccessToken
	) {

		$this->api = new $this->routeInfo['class'];

	}

	/**
	 * Le scope s'ouvre-t-il à la référence du jeton ? Le cadriciel ne connaît que l'ouverture
	 * générale de l'écriture ; l'application qui ouvre un domaine à certaines références seulement
	 * redéfinit la méthode, et la page qui propose les scopes d'une clé doit répondre par la même
	 * règle, sans quoi elle proposerait un accès que l'appel refuse.
	 */
	public function acceptScope(string $scope): bool {
		return \api\ApiSetting::isScopeOpen($scope, \api\ApiSetting::IS_API_WRITE_ACTIVE);
	}

	public function get(string $key, mixed $default = NULL): mixed {

		if(str_starts_with($key, '?')) {
			return $this->input[substr($key, 1)] ?? $default;
		} else {
			return $this->input[$key] ?? throw new ApiException('Key \''.$key.'\' not found', 'missing_argument', 500, []);
		}

	}

	public function set(string $key, mixed $value): void {
		$this->input[$key] = $value;
	}

	public function exists(string $key): mixed {
		return array_key_exists($key, $this->input);
	}

	public function getOutput(): array {
		return $this->output;
	}

	/** Une route qui rend un document PDF plutôt que du JSON : le corps de la réponse est le fichier. */
	public function pushPdf(string $content, string $filename): void {
		$this->file = ['content' => $content, 'filename' => $filename];
	}

	public function getFile(): ?array {
		return $this->file;
	}

	public function push(string $key, int|float|string|bool|array|null $value): self {
		$this->output[$key] = $value;
		return $this;
	}

	public function pushObject(?string $key, Element $e): self {

		$value = $this->exportElement($e);

		if($key === NULL) {
			$this->output = array_merge($this->output, $value);
		} else {
			$this->output[$key] = $value;
		}

		return $this;

	}

	public function pushCollection(?string $key, Collection $c): self {

		$values = $this->exportCollection($c);

		if($key === NULL) {
			$this->output = array_merge($this->output, $values);
		} else {
			$this->output[$key] = $values;
		}

		return $this;
	}

	public function exportCollection(Collection $c): array {

		$values = [];

		foreach($c as $e) {
			$values[] = $this->exportElement($e);
		}

		return $values;

	}

	public function exportElement(Element $e): array {

		$module = $e->getModule();

		[
			'properties' => $properties,
			'correlations' => $correlations
		] = Api::getElement($module, $e);

		$output = [];

		foreach($properties($this, $e) as $property) {

			$correlation = $correlations[$property] ?? NULL;

			if($correlation instanceof Closure) {
				$newValue = $correlation($this, $e, 'out');
			} else {

				if($e->offsetExists($property) === FALSE) {
					continue;
				}

				$value = $e->offsetGet($property);

				if(is_array($correlation)) {
					$newValue = $correlation[$value] ?? throw new Exception('Missing value');
				} else if($correlation instanceof Closure) {
					$newValue = $correlation($this, $value, 'out');
				} else if($value instanceof Element) {
					$newValue = $value->empty() ? NULL : $value['id'];
				} else {
					$newValue = $value;
				}

			}

			$output[$property] = $newValue;

		}

		return $output;

	}

}
?>
