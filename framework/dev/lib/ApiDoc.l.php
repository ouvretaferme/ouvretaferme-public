<?php
namespace dev;

/**
 * Introspect exposed API modules (ApiRoute::expose / *.a.php) and generate the OpenAPI 3.0
 * specification (api-doc repository) rendered by GitBook's native OpenAPI reference — méthode
 * HTTP + route en titre, panneaux Autorisations/Paramètres/Corps/Réponses, exemples JSON à
 * droite (cf MEMORY project_api_developer_doc).
 *
 * V1 : heuristique, pensée pour être affinée au fur et à mesure. Les libellés/genres/descriptions
 * métier ne peuvent pas être déduits du code : ils vivent dans un DevObserverUi::apiLabels() par
 * package concerné (ui/Dev.o.php, cf Notifiable/Package::getObservers()), collectés par ApiUi::getLabels()
 * (framework/dev/ui/Api.u.php, pur trigger sans contenu métier) — à compléter à chaque nouveau module exposé.
 * Même chose par route pour ce que son motif ne dit pas — le résumé d'une action, ce que rend une
 * sous-liste : DevObserverUi::apiRoutes(), collecté par ApiUi::getRoutes(). Rien de tout cela ne
 * remonte dans Api : une route se déclare sans se documenter.
 */
class ApiDocLib {

	const BASE_URL = 'https://api.ouvretaferme.org';

	/**
	 * ID fixe (généré une fois, ne jamais changer) : Postman s'en sert pour reconnaître qu'un
	 * réimport correspond à une collection déjà importée et proposer une mise à jour en place
	 * plutôt que de créer un doublon.
	 */
	const POSTMAN_COLLECTION_ID = '9093b625-1fe2-40df-95ad-fab45777ba26';

	/**
	 * Regroupement des endpoints par scope, utilisé comme tag OpenAPI (rendus par GitBook comme
	 * sous-groupes dans le menu de la référence). Clé = ApiXxx::$scopeName ; 'general' est le
	 * fourre-tout pour tout scope non mappé ici ni déclaré par un package (cf getScopes()).
	 */
	protected const SCOPE_LABELS = [
		'general' => 'Général',
		'production' => 'Production',
		'commercialisation' => 'Commercialisation',
		'accounting' => 'Gestion'
	];

	/**
	 * Les sections de la référence, dans leur ordre : celles de SCOPE_LABELS, puis celles qu'un
	 * package déclare dans son DevObserverUi::apiScopes(), avec l'accroche de leur page. Le libellé
	 * d'un package s'écrit dans son interface, seule couche où s() le donne traduit. Indexé par
	 * scope, chaque entrée portant 'title' et 'description'.
	 */
	protected static function getScopes(): array {

		$scopes = [];

		foreach(self::SCOPE_LABELS as $scope => $title) {
			$scopes[$scope] = ['title' => $title, 'description' => ''];
		}

		return $scopes + ApiUi::getScopes();

	}

	/**
	 * Ce que font les arguments que ModuleApi::list() et listBy() posent sur toutes les listes
	 * (framework/core/Api.php), et que rien d'autre ne décrit.
	 */
	protected const PAGINATION_PARAMETERS = [
		'start' => 'Rang du premier élément rendu, à partir de 1.',
		'limit' => 'Nombre d\'éléments rendus, 100 par défaut et 500 au plus. À 0, seul le total est rendu.',
		'ids' => 'Ne rend que ces identifiants, 100 au plus, séparés par des virgules.',
		'afterId' => 'Ne rend que les éléments d\'identifiant supérieur, pour parcourir une liste sans décalage quand elle change pendant la lecture.',
		'beforeId' => 'Ne rend que les éléments d\'identifiant inférieur.'
	];

	/**
	 * Le nom de schéma de chaque module documenté, par classe : une référence s'en sert pour nommer
	 * la ressource qu'elle désigne. Posé au début de chaque génération, il ne dépend que du code.
	 */
	protected static array $schemaNames = [];

	/**
	 * Parse ouvretaferme/package.c.php for every ApiRoute::expose('namespace\Class') call.
	 *
	 * @return array List of FQCN (ex: 'product\Unit')
	 */
	public static function getExposedModules(): array {

		$path = \Lime::getPath(LIME_APP).'/package.c.php';
		$content = file_get_contents($path);

		preg_match_all('/ApiRoute::expose\(\'([^\']+)\'\)/', $content, $matches);

		return $matches[1];

	}

	/**
	 * The exposed modules that the documentation publishes, minus those whose versioned API class
	 * sets `const DOCUMENTED = FALSE`: a beta module routes normally but stays out of openapi.json
	 * and postman-collection.json.
	 *
	 * @return array List of FQCN (ex: 'product\Unit')
	 */
	public static function getDocumentedModules(): array {
		return self::filterDocumented(self::getExposedModules());
	}

	/**
	 * Drops the modules whose versioned API class sets `const DOCUMENTED = FALSE`. A class that
	 * carries no such constant stays documented, so a misspelled constant name is silently
	 * ignored here: dev\ApiUndocumentedSentinelTest is what catches it.
	 *
	 * @return array List of FQCN (ex: 'product\Unit')
	 */
	public static function filterDocumented(array $modules): array {

		$version = \api\ApiSetting::VERSIONS[count(\api\ApiSetting::VERSIONS) - 1];

		return array_values(array_filter($modules, function(string $class) use ($version) {

			$classApi = $class.'_'.str_replace('.', '_', $version).'Api';

			return (
				class_exists($classApi) === FALSE or
				defined($classApi.'::DOCUMENTED') === FALSE or
				constant($classApi.'::DOCUMENTED') !== FALSE
			);

		}));

	}

	/**
	 * Build the whole OpenAPI 3.0 document for every exposed module.
	 */
	public static function buildOpenApiSpec(array $modules, ModuleLib $libModule): array {

		$version = \api\ApiSetting::VERSIONS[count(\api\ApiSetting::VERSIONS) - 1];

		$spec = [
			'openapi' => '3.0.3',
			'info' => [
				'title' => 'API Ouvretaferme',
				'version' => $version,
				'description' => "Référence des routes de l'API de Ouvretaferme. La section Authentification explique comment obtenir une clé API ou un jeton."
			],
			'servers' => [
				['url' => self::BASE_URL.'/'.$version]
			],
			'tags' => [],
			'security' => [['bearerAuth' => []]],
			'components' => [
				'securitySchemes' => [
					'bearerAuth' => [
						'type' => 'http',
						'scheme' => 'bearer',
						'description' => 'Clé API personnelle ou jeton OAuth2 d\'une application tierce, présenté dans l\'en-tête `Authorization: Bearer`. Voir la section Authentification.'
					]
				],
				'schemas' => [
					'Error' => [
						'type' => 'object',
						'properties' => [
							'type' => ['type' => 'string', 'enum' => ['authentication_error', 'resource_error', 'api_error']],
							'code' => ['type' => 'string'],
							'message' => ['type' => 'string']
						]
					]
				],
				'responses' => [
					'AuthenticationError' => self::errorResponse('Clé API ou jeton absent, invalide, expiré ou révoqué.'),
					'Forbidden' => self::errorResponse("Le jeton ne porte pas le scope requis, l'écriture n'est pas ouverte à cette structure, la structure n'a pas l'abonnement qui ouvre l'API, ou la personne qui a créé la clé n'administre plus la structure."),
					'ValidationError' => self::errorResponse("Un champ, un filtre ou un argument ne respecte pas les règles de validation, ou le corps n'est pas un objet JSON."),
					'NotFound' => self::errorResponse("Aucun élément avec cet identifiant pour votre structure."),
					'RateLimited' => self::errorResponse("Trop d'appels. L'en-tête `Retry-After` donne le nombre de secondes à attendre.") + [
						'headers' => [
							'Retry-After' => ['description' => 'Nombre de secondes à attendre avant le prochain appel.', 'schema' => ['type' => 'integer']]
						]
					]
				]
			],
			'paths' => []
		];

		$uniqueNames = self::buildUniqueNames($modules);
		self::$schemaNames = $uniqueNames;
		$labels = ApiUi::getLabels();
		$routes = ApiUi::getRoutes();
		$fieldsDoc = ApiUi::getFields();

		// Tag du scope (x-parent) par tag de groupe, et tag du groupe (x-parent) par tag de
		// ressource — remplis pendant la boucle, utilisés ensuite pour reconstruire spec['tags']
		// en un vrai arbre à 3 niveaux (scope > groupe > ressource, cf ci-dessous). $menus porte
		// le libellé de navigation de chaque tag, distinct de son nom quand celui-ci doit rester
		// unique (cf buildTagTree), $descriptions celui de la ressource, repris au sommaire des
		// pages de groupe et de scope.
		$scopeByGroupTag = [];
		$groupByResourceTag = [];
		$menus = [];
		$descriptions = [];

		$scopes = self::getScopes();

		foreach($scopes as $scope) {
			$descriptions[$scope['title']] = $scope['description'];
		}

		// Les schémas d'écriture publiés, pour ne garder à la fin que ceux qu'une route attend
		$writeNames = [];

		// Nom de schéma et libellés de chaque module exposé, pour les routes qui rendent autre chose
		// que la ressource dont elles portent le nom (cf addPaths()).
		$exposed = [];

		foreach($modules as $class) {
			$exposed[$class] = ['name' => $uniqueNames[$class], 'label' => $labels[$class] ?? self::getFallbackLabel($class)];
		}

		foreach($modules as $class) {

			$apiClass = $class.'_'.str_replace('.', '_', $version).'Api';
			$instance = new $apiClass();

			$routeInfo = self::getRouteInfoForClass($apiClass);
			$element = self::getElementForClass($apiClass);
			$module = $element['module'];
			$fields = self::getObjectFields($element);
			$schema = self::getSchema($libModule, $module);
			$writeLists = self::getWriteLists($instance, $module, $routeInfo, $schema, $fields, $libModule);
			$label = $exposed[$class]['label'];
			$package = substr($class, 0, strpos($class, '\\'));
			$scopeTag = ($scopes[$instance->scopeName] ?? $scopes['general'])['title'];
			$group = self::getGroupLabel($labels, $label, $package);
			$resourceTag = $label['title'];
			$name = $uniqueNames[$class];
			$standaloneRules = self::getStandaloneRules($instance) + [
				'properties' => ($writeLists['update'] ?? $writeLists['write'])['properties']
			];

			[$readSchema, $writeSchema] = self::buildResourceSchemas($module, $fields, $schema, $writeLists['write'], $label, $fieldsDoc[$class] ?? []);

			$readSchema['title'] = $label['title'];
			$readSchema['description'] = $label['description'];
			$readSchema['x-scope'] = $instance->scopeName;

			$spec['components']['schemas'][$name] = $readSchema;
			$spec['components']['schemas'][$name.'Write'] = $writeSchema;

			$writeNames[] = $name.'Write';
			$updateName = $name.'Write';

			// La modification n'accepte pas toujours ce que la création accepte : elle n'a alors
			// pas le même corps de requête, et le PUT le référence sous son propre nom.
			if($writeLists['update'] !== NULL) {

				[, $updateSchema] = self::buildResourceSchemas($module, $fields, $schema, $writeLists['update'], $label, $fieldsDoc[$class] ?? []);

				$updateName = $name.'Update';
				$spec['components']['schemas'][$updateName] = $updateSchema;
				$writeNames[] = $updateName;

			}

			$scopeByGroupTag[$group['tag']] = $scopeTag;
			$groupByResourceTag[$resourceTag] = $group['tag'];

			$menus[$group['tag']] = $group['menu'];
			$menus[$resourceTag] = $label['menu'] ?? $label['title'];
			$descriptions[$resourceTag] = $label['description'] ?? '';
			$descriptions[$group['tag']] = $group['description'];

			self::addPaths($spec['paths'], $routeInfo, $name, $updateName, $label, $resourceTag, $class, $libModule, $standaloneRules, ['exposed' => $exposed, 'routes' => $routes], $instance);

		}

		self::addSharedResponses($spec['paths']);
		self::addOperationIds($spec['paths']);

		$spec['tags'] = self::buildTagTree(array_map(fn(array $scope) => $scope['title'], $scopes), $scopeByGroupTag, $groupByResourceTag, $menus, $descriptions, self::getUsedTags($spec['paths']));

		self::removeUnusedWriteSchemas($spec, $writeNames);

		// Les modèles suivaient l'ordre d'exposition de package.c.php : la page Models de GitBook les
		// déroule tels quels, et rien n'y guidait la lecture. Chaque ressource retrouve ainsi son
		// schéma d'écriture juste après elle (Invoice, InvoiceWrite).
		ksort($spec['components']['schemas']);

		self::replaceDatetimeFormat($spec);

		return $spec;

	}

	/**
	 * Un package qui décrit un objet à la main peut écrire `date-time`, le format qu'OpenAPI connaît : il
	 * prend ici la forme que l'API rend (`datetimeSchema()`), en gardant sa description.
	 */
	protected static function replaceDatetimeFormat(array &$node): void {

		if(($node['format'] ?? NULL) === 'date-time') {

			unset($node['format']);

			$node += self::datetimeSchema();
			$node['example'] ??= '2026-04-15 09:30:00';

		}

		foreach($node as &$child) {

			if(is_array($child)) {
				self::replaceDatetimeFormat($child);
			}

		}

	}

	/**
	 * Les refus que toute route peut rendre, avant même d'atteindre sa ressource : le scope, l'accès
	 * de la structure à l'API, et la limite d'appels. Les codes sont ensuite rangés dans l'ordre, la
	 * référence les affichant tels qu'ils sont déclarés.
	 */
	protected static function addSharedResponses(array &$paths): void {

		foreach($paths as &$operations) {

			foreach($operations as &$operation) {

				$operation['responses'] += [
					'401' => ['$ref' => '#/components/responses/AuthenticationError'],
					'403' => ['$ref' => '#/components/responses/Forbidden'],
					'429' => ['$ref' => '#/components/responses/RateLimited']
				];

				ksort($operation['responses']);

			}

		}

	}

	/**
	 * Un identifiant par opération, que les générateurs de clients prennent pour nom de méthode : la
	 * méthode HTTP suivie des segments de la route, `{id}` se lisant `ById` (GET sale/{id} donne
	 * getSaleById). Dérivé de ce qui rend la route unique, il l'est aussi.
	 */
	protected static function addOperationIds(array &$paths): void {

		foreach($paths as $route => &$operations) {

			$segments = array_map(
				fn($segment) => ($segment === '{id}') ? 'ById' : ucfirst($segment),
				explode('/', trim($route, '/'))
			);

			foreach($operations as $method => &$operation) {
				$operation = ['operationId' => $method.implode('', $segments)] + $operation;
			}

		}

	}

	/**
	 * Un schéma d'écriture n'a de sens que si une route l'attend en corps de requête. Les ressources
	 * en lecture seule en publiaient un quand même, vide, qui se lisait dans la page Models comme une
	 * possibilité d'écriture inexistante ; et une ressource embarquée, qui n'a aucune route à elle,
	 * en publiait un aussi.
	 *
	 * Le tri se fait sur la spécification terminée plutôt que module par module : une action peut
	 * réclamer le corps d'une autre ressource que la sienne, et seule la liste complète des routes
	 * dit ce qui sert vraiment.
	 */
	protected static function removeUnusedWriteSchemas(array &$spec, array $writeNames): void {

		$used = [];

		array_walk_recursive($spec, function($value, $key) use (&$used) {

			if($key === '$ref' and is_string($value)) {
				$used[substr($value, strrpos($value, '/') + 1)] = TRUE;
			}

		});

		foreach($writeNames as $name) {

			if(array_key_exists($name, $used) === FALSE) {
				unset($spec['components']['schemas'][$name]);
			}

		}

	}

	/**
	 * Rebuild spec['tags'] as a 3-level tree (scope > groupe > ressource) via l'extension
	 * GitBook x-parent (une page par ressource, ex. "Espèces", nichée sous son groupe, ex.
	 * "Espèces et variétés", lui-même sous son scope, ex. "Production" — cf MEMORY
	 * project_api_developer_doc). Parcours en pré-ordre : les scopes gardent l'ordre fixe de
	 * getScopes(), groupes et ressources triés par ordre alphabétique (FR-aware) sur le
	 * libellé affiché, au sein de leur parent.
	 *
	 * Chaque tag porte en plus son accroche (x-page-description), le texte que GitBook affiche
	 * sous le titre de sa page et sur la carte qui y mène depuis la page parente.
	 */
	protected static function buildTagTree(array $scopeTitles, array $scopeByGroupTag, array $groupByResourceTag, array $menus, array $descriptions, array $usedTags): array {

		$collator = \L::getCollator();
		$tags = [];

		$menu = fn(string $tag) => $menus[$tag] ?? $tag;

		$sort = function(array $tags) use ($collator, $menu) {

			usort($tags, fn($a, $b) => $collator->compare($menu($a), $menu($b)));

			return $tags;

		};

		// GitBook fabrique le titre de page à partir du nom du tag en passant chaque mot en
		// capitale ("Comptes bancaires" -> "Comptes Bancaires", "Équipe et temps de travail" ->
		// "Équipe Et Temps De Travail"), y compris les mots outils, ce qui ne convient pas au
		// français. x-displayName court-circuite cette transformation : GitBook reprend la valeur
		// telle quelle pour le titre et le menu.
		//
		// Le nom du tag reste l'identifiant, unique et complet ("Ventes en caisse") ; le libellé
		// affiché peut, lui, être court et se répéter d'un groupe à l'autre ("Ventes" sous la
		// caisse enregistreuse comme sous les ventes), ce qu'un nom de tag ne peut pas faire.
		// Sans 'description', GitBook remplit la page d'un tag parent avec les cartes de ses enfants,
		// et c'est le rendu que l'on veut : un scope montre ses groupes, un groupe ses ressources.
		// Une 'description' prendrait toute la place à la place des cartes — ne rien mettre est ici
		// le bon choix. Reste x-page-description, l'accroche : rendue sous le titre de la page, elle
		// est aussi le texte de la carte qui y mène. GitBook la déduit du schéma pour une ressource,
		// mais un groupe n'a pas de schéma : sans libellé de groupe qui la porte, sa carte reste nue.
		$buildTag = function(string $name, ?string $parent = NULL) use ($menu, $descriptions) {

			$tag = ['name' => $name, 'x-displayName' => $menu($name)];

			if($parent !== NULL) {
				$tag['x-parent'] = $parent;
			}

			if(($descriptions[$name] ?? '') !== '') {
				$tag['x-page-description'] = strip_tags($descriptions[$name]);
			}

			return $tag;

		};

		// Un groupe qui n'expose qu'un seul module ne mérite pas son propre niveau : la ressource
		// remonte directement sous le scope. Sans ce garde-fou, le tag de groupe et celui de son
		// unique ressource portent le même nom (ex. bank\BankAccount, seul module du package bank)
		// -> deux tags OpenAPI avec un 'name' identique, invalide au sens de la spec, et qui a fait
		// disparaître toute la référence côté GitBook lors du premier essai (cf MEMORY
		// project_api_developer_doc). Le comptage porte sur les modules déclarés, ressources sans
		// route comprises : les masquer ne doit pas remonter d'un cran celles qui restent.
		$resourceCountByGroup = array_count_values($groupByResourceTag);

		// Un module exposé sans route (schéma imbriqué seul, ex. selling\History, embarqué dans
		// Sale.events) n'a pas de page à ouvrir : GitBook ne rend que les tags portant au moins une
		// opération. Autant ne pas les déclarer plutôt que de laisser des entrées fantômes.
		$visible = fn(array $tags) => array_values(array_filter($tags, fn($tag) => isset($usedTags[$tag])));

		foreach($scopeTitles as $scopeTag) {

			$scopeTags = [];
			$directResourceTags = [];

			foreach($sort(array_keys(array_filter($scopeByGroupTag, fn($tag) => $tag === $scopeTag))) as $groupTag) {

				$resourceTags = array_keys(array_filter($groupByResourceTag, fn($tag) => $tag === $groupTag));

				if($resourceCountByGroup[$groupTag] === 1) {
					$directResourceTags = array_merge($directResourceTags, $resourceTags);
					continue;
				}

				$resourceTags = $sort($visible($resourceTags));

				if($resourceTags === []) {
					continue;
				}

				$scopeTags[] = $buildTag($groupTag, $scopeTag);

				foreach($resourceTags as $resourceTag) {
					$scopeTags[] = $buildTag($resourceTag, $groupTag);
				}

			}

			foreach($sort($visible($directResourceTags)) as $resourceTag) {
				$scopeTags[] = $buildTag($resourceTag, $scopeTag);
			}

			if($scopeTags !== []) {
				$tags = array_merge($tags, [$buildTag($scopeTag)], $scopeTags);
			}

		}

		return $tags;

	}

	/**
	 * Les tags portés par au moins une opération, seuls à mériter une entrée dans spec['tags'].
	 */
	protected static function getUsedTags(array $paths): array {

		$tags = [];

		foreach($paths as $operations) {
			foreach($operations as $operation) {
				foreach($operation['tags'] as $tag) {
					$tags[$tag] = TRUE;
				}
			}
		}

		return $tags;

	}

	/**
	 * Libellé du groupe qui porte un module (ex. 'plant' => "Espèces et variétés"), fourni par le
	 * même mécanisme Observer que les libellés par module (DevObserverUi::apiLabels(), une entrée
	 * supplémentaire indexée par clé de groupe plutôt que par classe complète) — cf getFallbackLabel
	 * pour le même principe de repli côté module.
	 *
	 * Le package fait le groupe par défaut, ce qui suffit tant qu'il ne documente qu'un seul sujet.
	 * Un module peut lui préférer une clé de groupe explicite ('group'), seul moyen d'éclater un
	 * package qui en couvre plusieurs (selling porte les clients, la caisse enregistreuse et les
	 * ventes) ou, à l'inverse, de réunir des packages voisins.
	 */
	protected static function getGroupLabel(array $labels, array $label, string $package): array {

		$key = $label['group'] ?? $package;
		$group = $labels[$key] ?? ['title' => ucfirst($key).' <!-- TODO: libellé français -->'];

		// 'description' est facultative là où elle est obligatoire pour un module : un groupe n'est
		// qu'un rangement, et la plupart se passent d'un texte à eux. Celui qui en porte un le voit
		// en accroche de sa page et sur la carte qui y mène depuis son scope.
		return ['tag' => $group['title'], 'menu' => $group['menu'] ?? $group['title'], 'description' => $group['description'] ?? ''];

	}

	/**
	 * Build a Postman Collection v2.1 covering every exposed module, folders nested as
	 * <scope> > <package> > <module> > <une requête par opération> — même arbre que la référence
	 * OpenAPI rendue par GitBook (cf buildTagTree), même règle de repli : un package qui n'expose
	 * qu'un seul module remonte directement sous le scope (pas de niveau à un seul enfant), et
	 * mêmes libellés affichés : le dossier porte le libellé de menu ('Clients'), pas le nom du tag
	 * ('Clientèle') qui n'est un identifiant que pour OpenAPI. Postman n'exigeant pas l'unicité, un
	 * même libellé peut se répéter sous deux parents ('Ventes' sous la caisse comme sous les ventes).
	 * Auth Bearer et {{baseUrl}} portés au niveau de la collection (variables), à renseigner une
	 * fois par l'utilisateur qui l'importe.
	 */
	public static function buildPostmanCollection(array $modules, ModuleLib $libModule): array {

		$version = \api\ApiSetting::VERSIONS[count(\api\ApiSetting::VERSIONS) - 1];
		$labels = ApiUi::getLabels();
		$routes = ApiUi::getRoutes();
		$fieldsDoc = ApiUi::getFields();

		$scopes = self::getScopes();

		$scopeByPackageTag = [];
		$packageByResourceTag = [];
		$requestsByResourceTag = [];
		$menus = [];

		foreach($modules as $class) {

			$apiClass = $class.'_'.str_replace('.', '_', $version).'Api';
			$instance = new $apiClass();

			$routeInfo = self::getRouteInfoForClass($apiClass);
			$element = self::getElementForClass($apiClass);
			$module = $element['module'];
			$fields = self::getObjectFields($element);
			$schema = self::getSchema($libModule, $module);
			$writeLists = self::getWriteLists($instance, $module, $routeInfo, $schema, $fields, $libModule);
			$label = $labels[$class] ?? self::getFallbackLabel($class);
			$package = substr($class, 0, strpos($class, '\\'));
			$scopeTag = ($scopes[$instance->scopeName] ?? $scopes['general'])['title'];
			$group = self::getGroupLabel($labels, $label, $package);
			$groupTag = $group['tag'];
			$resourceTag = $label['title'];

			[, $writeSchema] = self::buildResourceSchemas($module, $fields, $schema, $writeLists['write'], $label, $fieldsDoc[$class] ?? []);

			$updateSchema = ($writeLists['update'] === NULL)
				? $writeSchema
				: self::buildResourceSchemas($module, $fields, $schema, $writeLists['update'], $label, $fieldsDoc[$class] ?? [])[1];

			$scopeByPackageTag[$groupTag] = $scopeTag;
			$packageByResourceTag[$resourceTag] = $groupTag;
			$requestsByResourceTag[$resourceTag] = self::buildPostmanRequests($routeInfo, $writeSchema, $updateSchema, $routes, $label, $libModule);

			$menus[$groupTag] = $group['menu'];
			$menus[$resourceTag] = $label['menu'] ?? $label['title'];

		}

		return [
			'info' => [
				'_postman_id' => self::POSTMAN_COLLECTION_ID,
				'name' => 'Ouvretaferme',
				'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
			],
			'auth' => [
				'type' => 'bearer',
				'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']]
			],
			'variable' => [
				['key' => 'baseUrl', 'value' => self::BASE_URL.'/'.$version],
				['key' => 'token', 'value' => '']
			],
			'item' => self::buildPostmanFolderTree(array_map(fn(array $scope) => $scope['title'], $scopes), $scopeByPackageTag, $packageByResourceTag, $requestsByResourceTag, $menus)
		];

	}

	/**
	 * Même parcours que buildTagTree (scope > package > ressource, repli des packages à une seule
	 * ressource, tri sur le libellé affiché), mais construit des dossiers Postman imbriqués
	 * (item.item.item) plutôt qu'une liste plate de tags OpenAPI avec x-parent. Les tags restent
	 * l'identifiant interne, $menus donne le nom du dossier — comme x-displayName côté GitBook.
	 */
	protected static function buildPostmanFolderTree(array $scopeTitles, array $scopeByPackageTag, array $packageByResourceTag, array $requestsByResourceTag, array $menus): array {

		$collator = \L::getCollator();
		$resourceCountByPackage = array_count_values($packageByResourceTag);
		$items = [];

		$menu = fn(string $tag) => $menus[$tag] ?? $tag;

		$sort = function(array $tags) use ($collator, $menu) {

			usort($tags, fn($a, $b) => $collator->compare($menu($a), $menu($b)));

			return $tags;

		};

		foreach($scopeTitles as $scopeTag) {

			$packageTags = $sort(array_keys(array_filter($scopeByPackageTag, fn($tag) => $tag === $scopeTag)));

			$scopeItems = [];
			$directResourceTags = [];

			foreach($packageTags as $packageTag) {

				$resourceTags = array_keys(array_filter($packageByResourceTag, fn($tag) => $tag === $packageTag));

				if($resourceCountByPackage[$packageTag] === 1) {
					$directResourceTags = array_merge($directResourceTags, $resourceTags);
					continue;
				}

				$packageItems = [];
				foreach($sort($resourceTags) as $resourceTag) {
					if(($requestsByResourceTag[$resourceTag] ?? []) !== []) {
						$packageItems[] = ['name' => $menu($resourceTag), 'item' => $requestsByResourceTag[$resourceTag]];
					}
				}

				if($packageItems !== []) {
					$scopeItems[] = ['name' => $menu($packageTag), 'item' => $packageItems];
				}

			}

			foreach($sort($directResourceTags) as $resourceTag) {
				if(($requestsByResourceTag[$resourceTag] ?? []) !== []) {
					$scopeItems[] = ['name' => $menu($resourceTag), 'item' => $requestsByResourceTag[$resourceTag]];
				}
			}

			if($scopeItems !== []) {
				$items[] = ['name' => $scopeTag, 'item' => $scopeItems];
			}

		}

		return $items;

	}

	/**
	 * Les requêtes d'une ressource, dans un ordre fixe : list, get, update, create, delete, puis les
	 * actions. Chacune porte le résumé de la même opération dans la référence, l'identifiant en
	 * variable de chemin (`:id`) plutôt qu'en dur, ses filtres obligatoires, et le corps que la route
	 * lit vraiment : aucun pour une lecture ou une action qui ne lit rien, celui qu'elle déclare dans
	 * apiRoutes() sinon.
	 */
	protected static function buildPostmanRequests(array $routeInfo, array $writeSchema, array $updateSchema, array $routes = [], array $label = [], ModuleLib $libModule = new ModuleLib()): array {

		$requests = [];

		$exampleBody = self::postmanBody($writeSchema);
		$updateBody = self::postmanBody($updateSchema);

		$singular = $label['singular'] ?? NULL;
		$article = (($label['gender'] ?? 'm') === 'f') ? 'une' : 'un';

		$name = fn(string $verb, string $summary) => ($singular === NULL) ? $verb : $summary;

		// Une ressource qui expose plusieurs listes les nomme d'après leur route, sans quoi elles
		// porteraient toutes le même intitulé dans Postman.
		foreach($routeInfo['list'] as $list) {

			$route = self::getRouteLabel($routes, 'GET', $list['requestRoute']);

			$requests[] = self::postmanRequest(
				$route['label'] ?? ((count($routeInfo['list']) === 1) ? $name('list', 'Lister les '.($label['plural'] ?? '')) : $list['requestRoute']),
				'GET',
				$list['requestRoute'],
				$list['scope'],
				['start' => '1', 'limit' => '20'] + self::postmanRequiredQuery($list['arguments'] ?? [], $libModule)
			);

		}

		if($routeInfo['read'] !== NULL) {
			$requests[] = self::postmanRequest($name('get', 'Consulter '.$article.' '.$singular), 'GET', $routeInfo['read']['requestRoute'], $routeInfo['read']['scope']);
		}

		if($routeInfo['update'] !== NULL) {
			$requests[] = self::postmanRequest($name('update', 'Modifier '.$article.' '.$singular), 'PUT', $routeInfo['update']['requestRoute'], $routeInfo['update']['scope'], [], $updateBody);
		}

		if($routeInfo['create'] !== NULL) {

			$route = self::getRouteLabel($routes, 'POST', $routeInfo['create']['requestRoute']);

			$requests[] = self::postmanRequest($name('create', 'Créer '.$article.' '.$singular), 'POST', $routeInfo['create']['requestRoute'], $routeInfo['create']['scope'], [], self::postmanActionBody($route, $exampleBody));

		}

		if($routeInfo['delete'] !== NULL) {
			$requests[] = self::postmanRequest($name('delete', 'Supprimer '.$article.' '.$singular), 'DELETE', $routeInfo['delete']['requestRoute'], $routeInfo['delete']['scope']);
		}

		foreach($routeInfo['actions'] as $action) {

			$route = self::getRouteLabel($routes, $action['requestMethod'], $action['requestRoute']);
			$isRead = ($action['requestMethod'] === 'GET');

			$segments = explode('/', $action['requestRoute']);

			$requests[] = self::postmanRequest(
				$route['label'] ?? end($segments),
				$action['requestMethod'],
				$action['requestRoute'],
				$action['scope'],
				$isRead ? self::postmanRequiredQuery($action['arguments'] ?? [], $libModule) : [],
				($isRead or $action['requestMethod'] === 'DELETE') ? NULL : self::postmanActionBody($route, $exampleBody)
			);

		}

		return $requests;

	}

	/** Le corps d'une route qui peut déclarer le sien (cf getActionBody()), NULL quand elle n'en lit pas. */
	protected static function postmanActionBody(array $route, array $exampleBody): ?array {

		if(array_key_exists('body', $route) === FALSE) {
			return $exampleBody;
		}

		if($route['body'] === FALSE) {
			return NULL;
		}

		return self::postmanBody($route['body']);

	}

	/**
	 * Les arguments sans lesquels la route refuse l'appel, à une valeur d'exemple : une liste dont la
	 * période est obligatoire répondait 400 dès le premier envoi depuis Postman.
	 */
	protected static function postmanRequiredQuery(array $arguments, ModuleLib $libModule): array {

		$query = [];

		foreach(self::queryParameters($arguments, $libModule) as $parameter) {

			if($parameter['required']) {
				$query[$parameter['name']] = self::postmanValue($parameter['schema']);
			}

		}

		return $query;

	}

	protected static function postmanValue(array $schema): string {

		if(isset($schema['enum'][0])) {
			return (string)$schema['enum'][0];
		}

		$example = $schema['example'] ?? NULL;

		if(is_bool($example)) {
			return $example ? 'true' : 'false';
		}

		if(is_scalar($example)) {
			return (string)$example;
		}

		if($schema['type'] === 'string') {

			return match($schema['format'] ?? '') {
				'date' => '2026-04-15',
				default => ''
			};

		}

		return match($schema['type']) {
			'integer' => '1',
			'number' => '1.5',
			'boolean' => 'false',
			default => ''
		};

	}

	/** Le corps d'exemple d'une requête Postman : un champ par propriété acceptée, à sa valeur d'exemple. */
	protected static function postmanBody(array $writeSchema): array {

		$body = [];

		foreach($writeSchema['properties'] ?? [] as $field => $fieldSchema) {
			$body[$field] = $fieldSchema['example'] ?? NULL;
		}

		return $body;

	}

	/**
	 * L'identifiant passe par une variable de chemin (`:id`), que Postman présente dans l'onglet
	 * Params : il valait 365 en dur dans chaque route, un identifiant que personne ne possède.
	 */
	protected static function postmanRequest(string $name, string $method, string $route, string $scope, array $query = [], ?array $body = NULL): array {

		$route = str_replace('{id}', ':id', $route);

		$queryParams = [];
		foreach($query as $key => $value) {
			$queryParams[] = ['key' => $key, 'value' => (string)$value];
		}

		$request = [
			'method' => $method,
			'header' => $body !== NULL ? [['key' => 'Content-Type', 'value' => 'application/json']] : [],
			'description' => self::scopeDescription($scope),
			'url' => [
				'raw' => '{{baseUrl}}/'.$route.($query !== [] ? '?'.http_build_query($query) : ''),
				'host' => ['{{baseUrl}}'],
				'path' => explode('/', $route)
			]
		];

		if($queryParams !== []) {
			$request['url']['query'] = $queryParams;
		}

		if(str_contains($route, ':id')) {
			$request['url']['variable'] = [['key' => 'id', 'value' => '', 'description' => 'Identifiant de l\'élément']];
		}

		if($body !== NULL) {
			$request['body'] = [
				'mode' => 'raw',
				'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
				'options' => ['raw' => ['language' => 'json']]
			];
		}

		return ['name' => $name, 'request' => $request];

	}

	/**
	 * Short class name is used as the OpenAPI schema/tag name, but several packages reuse the
	 * same short name (ex. product\Category vs work\Category) : disambiguate those with their
	 * namespace prefix so schema keys stay unique instead of silently overwriting each other.
	 */
	protected static function buildUniqueNames(array $modules): array {

		$shortNames = [];

		foreach($modules as $class) {
			$shortNames[$class] = substr($class, strrpos($class, '\\') + 1);
		}

		$counts = array_count_values($shortNames);
		$names = [];

		foreach($shortNames as $class => $shortName) {
			$names[$class] = $counts[$shortName] > 1 ? ucfirst(substr($class, 0, strrpos($class, '\\'))).$shortName : $shortName;
		}

		return $names;

	}

	/**
	 * La déclaration element() portée par une ressource. Api::getElement() choisit par élément, ce que
	 * la documentation ne peut pas faire : elle part d'une classe exposée et n'a rien à exporter. Le
	 * détour par la réflexion vaut mieux qu'un accesseur public sur Api, qui n'aurait pas d'appelant en
	 * production — c'est déjà le parti pris de getRouteInfoForClass() pour les routes.
	 */
	protected static function getElementForClass(string $apiClass): array {

		$reflection = new \ReflectionClass('Api');
		$property = $reflection->getProperty('elements');
		$property->setAccessible(TRUE);

		foreach($property->getValue() as $list) {

			foreach($list as $element) {

				if($element['class'] === $apiClass) {
					return $element;
				}

			}

		}

		throw new \Exception('No element declared by '.$apiClass);

	}

	/**
	 * 'list' est un tableau : un module peut exposer plusieurs listes sur la même ressource (le
	 * catalogue commun et celui de la structure, cf plant\Plant et plant\Variety). Les autres verbes
	 * ne portent qu'une route par module.
	 */
	protected static function getRouteInfoForClass(string $apiClass): array {

		$reflection = new \ReflectionClass('Api');
		$property = $reflection->getProperty('routes');
		$property->setAccessible(TRUE);

		$allRoutes = $property->getValue();

		$info = ['create' => NULL, 'read' => NULL, 'list' => [], 'update' => NULL, 'delete' => NULL, 'actions' => []];

		// Une route est le verbe CRUD de sa ressource quand son motif est la ressource elle-même
		// (marketSale, marketSale/{id}). Tout ce qui porte un segment de plus nomme une action
		// (marketSale/{id}/payment, sale/{id}/addProducts) : sans cette distinction, la dernière déclarée
		// écrasait le verbe CRUD et le faisait disparaître de la documentation avec elle.
		$isResource = fn(array $data) => preg_match('#^[a-zA-Z]+(/\{id\})?$#', $data['requestRoute']) === 1;

		foreach(['POST' => 'create', 'PUT' => 'update', 'DELETE' => 'delete', 'PATCH' => NULL] as $requestMethod => $verb) {

			foreach($allRoutes[$requestMethod] ?? [] as $data) {

				if($data['class'] !== $apiClass) {
					continue;
				}

				if($verb !== NULL and $isResource($data)) {
					$info[$verb] = $data;
				} else {
					$info['actions'][] = $data;
				}

			}

		}

		// ModuleApi::list() et listBy() posent toujours la pagination : c'est ce qui distingue une
		// liste d'une route GET sur mesure, que requestGet() enregistre exactement comme elles.
		// Sans cette distinction, financialYear/{id}/fec, invoice/{id}/download et me sortaient
		// documentées comme « la liste paginée » de leur ressource, avec le schéma qui va avec.
		$isList = fn(array $data) => array_key_exists('?start', $data['arguments']);

		foreach($allRoutes['GET'] ?? [] as $data) {

			if($data['class'] === $apiClass) {

				// nom/{id} lit la ressource ; une liste est cadrée ou non (nom, nom/{id}/itemsList).
				if($data['id'] and $isResource($data)) {
					$info['read'] = $data;
				} else if($isList($data)) {
					$info['list'][] = $data;
				} else {
					$info['actions'][] = $data;
				}

			}

		}

		return $info;

	}

	/**
	 * ModuleApi::update() accepts a 'properties' key in the PUT body to update only a subset of
	 * fields instead of the full resource (ModuleApi::$allowStandalone, TRUE by default and never
	 * disabled by any exposed module) — getStandalonePropertiesUpdate() just adds constraints on
	 * top (groups that must travel together, properties excluded from partial update). No override
	 * found in the repo actually reads its $data argument, so a constructor-less ApiData instance
	 * is enough to call it here for introspection.
	 *
	 * Un module exposé n'étend pas forcément ModuleApi : \Api suffit pour une route sur mesure
	 * (structure\Structure) ou pour un objet sans route (user\User). Sans routes CRUD, il n'y a
	 * pas de PUT et donc aucune règle à en tirer.
	 */
	protected static function getStandaloneRules(\Api $instance): array {

		if($instance instanceof \ModuleApi === FALSE) {
			return ['group' => [], 'exclude' => []];
		}

		$data = (new \ReflectionClass('ApiData'))->newInstanceWithoutConstructor();
		$rules = $instance->getStandalonePropertiesUpdate($data);

		return [
			'group' => $rules['group'] ?? [],
			'exclude' => $rules['exclude'] ?? []
		];

	}

	/**
	 * Extract the field names passed to $this->object(...) by reading the closure source
	 * (apize(['a', 'b']) or ['a' => $e['a'], ...] are both literal, so we just collect the
	 * quoted identifiers used in the closure body, in order, deduplicated).
	 */
	protected static function getObjectFields(array $element): array {

		preg_match_all('/\'([a-zA-Z0-9_]+)\'/', self::getSource(new \ReflectionFunction($element['properties'])), $matches);

		return array_values(array_unique($matches[1]));

	}

	protected static function getSchema(ModuleLib $libModule, string $class): array {

		$reflection = new \ReflectionClass($libModule);
		$property = $reflection->getProperty('elements');
		$property->setAccessible(TRUE);

		$elements = $property->getValue($libModule);

		return $elements[$class]['list'] ?? [];

	}

	/**
	 * Les deux corps de requête d'une ressource : celui de l'écriture générale (la création, et les
	 * actions faute de mieux) et celui de la modification quand il en diffère.
	 *
	 * Une ressource qui ne se crée pas par l'API mais s'y modifie n'a rien à déclarer en création :
	 * shop\Shop n'ouvre que la modification, structure\Member n'expose que le rôle. Leur corps de
	 * requête se construisait quand même sur getPropertiesCreate(), qui annonçait modifiables
	 * l'adresse de la boutique et son caractère collectif — deux champs que le PUT refuse.
	 */
	protected static function getWriteLists(\Api $instance, string $module, array $routeInfo, array $schema, array $fields, ModuleLib $libModule): array {

		$read = function(string $method) use ($instance, $module, $schema, $fields, $libModule) {

			$properties = self::getWritableProperties($instance, $module, $method, $schema, $fields, $libModule);

			return [
				'properties' => $properties,
				'union' => self::getUnionKind($instance, $module, $method, $properties, $schema, $fields),
				'gestures' => [($method === 'getPropertiesCreate') ? 'create' : 'update']
			];

		};

		if($routeInfo['create'] === NULL and $routeInfo['update'] !== NULL) {
			return ['write' => $read('getPropertiesUpdate'), 'update' => NULL];
		}

		$create = $read('getPropertiesCreate');

		// Le repli garde un corps de requête aux ressources dont la création n'ouvre rien : sans
		// route de modification, il n'y a de toute façon qu'un seul corps à documenter.
		if($routeInfo['update'] === NULL) {

			// Le repli sert toujours la création, seule route à documenter
			if($create['properties'] === []) {
				$create = ['gestures' => ['create']] + $read('getPropertiesUpdate');
			}

			return ['write' => $create, 'update' => NULL];

		}

		$update = $read('getPropertiesUpdate');

		// Un seul corps sert alors les deux routes
		if(self::sameProperties($create['properties'], $update['properties'])) {
			$create['gestures'] = ['create', 'update'];
			return ['write' => $create, 'update' => NULL];
		}

		return ['write' => $create, 'update' => $update];

	}

	/** Deux listes de champs disent-elles la même chose ? Leur ordre ne fait pas partie du contrat. */
	protected static function sameProperties(?array $first, ?array $second): bool {

		if($first === NULL or $second === NULL) {
			return $first === $second;
		}

		sort($first);
		sort($second);

		return $first === $second;

	}

	/**
	 * Les propriétés acceptées pour un geste ('getPropertiesCreate' ou 'getPropertiesUpdate'), ou
	 * NULL quand elles ne peuvent pas être établies.
	 *
	 * La question se pose à la classe API et non au Lib : c'est elle que la requête traverse
	 * (ModuleApi::create()/update()), et plusieurs la redéfinissent — shop\Shop réserve la
	 * modification à la structure propriétaire, shop\Date retire du corps le champ que le mode
	 * catalogue ne lit pas. Interroger le Lib publiait un contrat que l'API n'applique pas. Un
	 * module exposé n'étend pas forcément ModuleApi (\Api suffit pour une route sur mesure) : on
	 * retombe alors sur le Lib, seule source disponible.
	 *
	 * L'origine 'api' est celle que passe ModuleApi (framework/core/Api.php) : plusieurs Libs
	 * n'ouvrent certains champs qu'à l'API, et catalog\OfferLib refuse tout le reste. Documenter
	 * la variante web reviendrait à décrire un autre produit que celui qu'on expose.
	 *
	 * La liste peut être une \Closure plutôt qu'un tableau : elle dépend alors de l'élément envoyé
	 * (selling\InvoiceLib n'accepte pas les mêmes champs sur une facture de solde et sur une facture
	 * d'acompte). On la déroule sur des éléments sondes pour en tirer l'union des branches, et NULL
	 * ne subsiste que si aucune n'a abouti.
	 */
	protected static function getWritableProperties(\Api $instance, string $class, string $method, array $schema, array $fields, ModuleLib $libModule): ?array {

		$properties = self::readWritableProperties($instance, $class, $method);

		if(is_array($properties)) {
			return $properties;
		}

		if($properties instanceof \Closure) {
			return self::getWritablePropertiesUnion($class, $properties, $schema, $fields, $libModule);
		}

		return NULL;

	}

	/** Ce que la classe API déclare accepter pour ce geste, ou ce que son Lib déclare à défaut. */
	protected static function readWritableProperties(\Api $instance, string $class, string $method): array|\Closure|null {

		$lib = $class.'Lib';

		if(class_exists($lib) === FALSE or method_exists($lib, $method) === FALSE) {
			return [];
		}

		// Une redéfinition lit la requête sur une sonde qui n'en est pas une : ces avertissements
		// n'apprennent rien ici et noieraient la sortie du générateur
		set_error_handler(fn() => TRUE);

		try {

			if($instance instanceof \ModuleApi) {
				return $instance->$method(self::getProbeData($class));
			}

			return $lib::$method('api');

		} catch(\Throwable) {
			return NULL;
		} finally {
			restore_error_handler();
		}

	}

	/**
	 * L'ApiData que reçoit une redéfinition de getPropertiesCreate()/getPropertiesUpdate().
	 *
	 * Ces redéfinitions y lisent la structure du jeton et l'élément visé pour refuser une écriture
	 * qui ne leur appartient pas : une sonde neutre traverse ces contrôles sans les déclencher.
	 * ApiData est complété par l'application (ouvretaferme/Lime.s.php y ajoute la structure du
	 * jeton), et une propriété typée lève tant qu'elle n'est pas posée : chacune reçoit un élément
	 * vide de son propre type, lu par réflexion, pour que framework/ n'ait à nommer aucun module
	 * métier. Celles que la réflexion ne peut pas remplir (readonly, classe abstraite) restent
	 * vides : une redéfinition qui les lit échoue, et la ressource retombe sur un corps libre.
	 */
	protected static function getProbeData(string $class): \ApiData {

		$reflection = new \ReflectionClass('ApiData');

		$data = $reflection->newInstanceWithoutConstructor();
		$data->module = $class;
		$data->body = [];

		foreach($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {

			if($property->isReadOnly() or $property->isStatic() or $property->isInitialized($data)) {
				continue;
			}

			$type = $property->getType();

			if($type instanceof \ReflectionNamedType === FALSE or $type->isBuiltin() or class_exists($type->getName()) === FALSE) {
				continue;
			}

			$linked = new \ReflectionClass($type->getName());

			if($linked->isInstantiable() and ($linked->getConstructor()?->getNumberOfRequiredParameters() ?? 0) === 0) {
				$property->setValue($data, $linked->newInstance());
			}

		}

		return $data;

	}

	/**
	 * L'union des champs que la closure accepte, toutes branches confondues.
	 *
	 * Une branche qui échoue est passée : ces closures lisent des éléments liés et appellent des
	 * méthodes métier (series\TaskLib lit la catégorie de l'intervention), qu'une sonde ne peut pas
	 * toujours satisfaire. Le résultat est donc l'union de ce qui a abouti, jamais une garantie
	 * d'exhaustivité — d'où le repli sur un schéma libre quand rien n'aboutit.
	 */
	protected static function getWritablePropertiesUnion(string $class, \Closure $closure, array $schema, array $fields, ModuleLib $libModule): ?array {

		$union = [];

		foreach(self::getProbeElements($class, $schema, $fields, $libModule) as $eProbe) {

			// Une sonde incomplète fait râler le code métier (accès de tableau sur NULL) : ces
			// avertissements n'apprennent rien ici et noieraient la sortie du générateur
			set_error_handler(fn() => TRUE);

			try {
				$properties = $closure($eProbe);
			} catch(\Throwable) {
				$properties = NULL;
			} finally {
				restore_error_handler();
			}

			if(is_array($properties)) {
				$union = array_merge($union, $properties);
			}

		}

		return ($union === []) ? NULL : array_values(array_unique($union));

	}

	/**
	 * Les éléments à faire passer dans la closure : un élément neutre, puis un par valeur possible
	 * de ce sur quoi ces closures se branchent, sur l'élément comme sur ses éléments liés.
	 *
	 * Les liés comptent autant que l'élément : shop\DateLib lit l'ouverture de la boutique et ses
	 * points de retrait, jamais une colonne du créneau. Sans eux, expects(['shop' => [...]]) levait
	 * avant toute branche et six ressources publiaient un corps de requête libre faute d'union.
	 *
	 * Toutes les propriétés sont posées, à NULL, sur l'élément comme sur ses liés : les closures
	 * appellent expects(), qui exige que la clé existe et non qu'elle porte une valeur. Une valeur
	 * sans rapport avec le champ est sans effet, la closure ne lit que ce qui la concerne.
	 */
	protected static function getProbeElements(string $class, array $schema, array $fields, ModuleLib $libModule): array {

		if(class_exists($class) === FALSE or $schema === []) {
			return [];
		}

		$linkedClasses = self::getLinkedClasses($schema);
		$blank = array_fill_keys(array_merge(array_keys($schema), $fields), NULL);

		// Deux fonds, parce qu'un élément lié posé vide n'est pas un élément lié absent : notEmpty()
		// compte les clés (framework/core/Data.php), et une closure teste tantôt la présence du lié
		// (cash\CashLib ne construit le montant que hors transaction Stripe), tantôt son contenu
		// (shop\DateLib lit l'ouverture de la boutique). Ne servir que le second retirait des champs
		// réellement acceptés.
		$linkedBlank = [];

		foreach($linkedClasses as $field => $linked) {
			$linkedBlank[$field] = new $linked(self::getBlankValues($linked, $libModule));
		}

		// Un troisième fond, tout renseigné : une branche demande parfois deux conditions à la fois,
		// et une sonde qui ne pose qu'un champ n'en satisfait jamais qu'une. cash\CashLib n'ouvre la
		// description qu'à une écriture en brouillon *et* rattachée à une source ;
		// financialYear\FinancialYear::isClosed() veut le statut clos *et* une date de clôture.
		$filled = $linkedBlank + self::getFilledValues($class, $schema) + $blank;

		$elements = [new $class($blank), new $class($linkedBlank + $blank), new $class($filled)];

		foreach(self::getProbeValues($class, $schema, $fields) as $field => $values) {

			foreach($values as $value) {
				$elements[] = new $class([$field => $value] + $blank);
				$elements[] = new $class([$field => $value] + $linkedBlank + $blank);
				$elements[] = new $class([$field => $value] + $filled);
			}

		}

		foreach($linkedClasses as $field => $linked) {

			$linkedSchema = self::getSchema($libModule, $linked);
			$linkedValues = self::getBlankValues($linked, $libModule);

			foreach(self::getProbeValues($linked, $linkedSchema, []) as $linkedField => $values) {

				foreach($values as $value) {
					$elements[] = new $class([$field => new $linked([$linkedField => $value] + $linkedValues)] + $linkedBlank + $blank);
				}

			}

		}

		return $elements;

	}

	/**
	 * Toutes les colonnes d'un module, renseignées : de quoi passer les branches qui ne demandent
	 * qu'une valeur, quelle qu'elle soit. Une valeur que le champ ne saurait pas porter est sans
	 * effet, la closure ne lit que ce qui la concerne, et une branche qui échoue est passée.
	 */
	protected static function getFilledValues(string $class, array $schema): array {

		$values = [];

		foreach($schema as $field => $property) {

			$type = $property['type'] ?? '';

			// Les propriétés déléguées portent déjà un élément vide, qui satisfait expects()
			if(str_starts_with($type, 'element')) {
				continue;
			}

			$values[$field] = match(TRUE) {
				($type === 'enum' and is_array($property['params'] ?? NULL)) => self::resolveEnumValue($class, $property['params'][0]),
				$type === 'bool' => TRUE,
				$type === 'json' => [],
				$type === 'date' => '2000-01-01',
				$type === 'datetime' => '2000-01-01 00:00:00',
				(str_starts_with($type, 'int') or str_starts_with($type, 'serial') or $type === 'decimal' or $type === 'float') => 1,
				default => '1'
			};

		}

		return $values;

	}

	/** Toutes les colonnes d'un module, à NULL : de quoi satisfaire expects() sans rien affirmer. */
	protected static function getBlankValues(string $class, ModuleLib $libModule): array {
		return array_fill_keys(array_keys(self::getSchema($libModule, $class)), NULL);
	}

	/** Les propriétés déléguées du schéma, par champ : le module que chacune vise. */
	protected static function getLinkedClasses(array $schema): array {

		$linked = [];

		foreach($schema as $field => $property) {

			if(
				str_starts_with($property['type'] ?? '', 'element') and
				is_string($property['params'] ?? NULL) and
				class_exists($property['params'])
			) {
				$linked[$field] = $property['params'];
			}

		}

		return $linked;

	}

	/**
	 * Les valeurs à essayer sur chaque champ d'un module : celles de ses énumérations, les deux d'un
	 * booléen, et les constantes de chaîne du module sur les champs exposés qui n'ont pas de colonne
	 * (selling\Customer se branche sur 'category', qui est calculé et que l'API pose sur l'élément).
	 *
	 * Les booléens comptent autant que les énumérations : shop\Shop::isPersonal() se lit sur
	 * 'shared', et le créneau n'ouvre ses points de retrait que si la boutique en a. Laissés à NULL,
	 * ces branches n'étaient jamais prises.
	 */
	protected static function getProbeValues(string $class, array $schema, array $fields): array {

		$values = [];

		foreach($schema as $field => $property) {

			$type = $property['type'] ?? '';

			if($type === 'enum' and is_array($property['params'] ?? NULL)) {
				$values[$field] = array_map(fn($value) => self::resolveEnumValue($class, $value), $property['params']);
			} else if($type === 'bool') {
				$values[$field] = [TRUE, FALSE];
			}

		}

		$constants = array_filter(new \ReflectionClass($class)->getConstants(), fn($value) => is_string($value));

		foreach(array_diff($fields, array_keys($schema)) as $field) {
			$values[$field] = array_values($constants);
		}

		return $values;

	}

	/**
	 * La nature du corps de requête : NULL pour une liste fixe, 'complete' ou 'partial' quand les
	 * champs dépendent de l'élément envoyé.
	 *
	 * La distinction commande ce que la référence promet au lecteur. Une union 'partial' se déclare
	 * ouverte (additionalProperties), ce que GitBook affiche en « Other properties » : c'est un aveu,
	 * pas une propriété du produit, et il ne doit apparaître que là où le doute est réel.
	 */
	protected static function getUnionKind(\Api $instance, string $class, string $method, ?array $writable, array $schema, array $fields): ?string {

		$properties = self::readWritableProperties($instance, $class, $method);

		if($properties instanceof \Closure) {
			return self::isUnionComplete($properties, $writable ?? [], array_merge(array_keys($schema), $fields)) ? 'complete' : 'partial';
		}

		// Une redéfinition qui rend une liste fixe se branche sur la requête (à qui appartient
		// l'élément visé) et non sur l'élément : la sonde n'en prend qu'une branche, et rien ne dit
		// lesquelles restent. shop\Share ouvre le moyen de paiement à qui tient la boutique, le
		// libellé aux autres — publier la première seule dirait moins que ne rien affirmer.
		return self::isOverridden($instance, $method) ? 'partial' : NULL;

	}

	/** La classe API redéfinit-elle la liste que ModuleApi tient du Lib ? */
	protected static function isOverridden(\Api $instance, string $method): bool {

		if($instance instanceof \ModuleApi === FALSE) {
			return FALSE;
		}

		return new \ReflectionMethod($instance, $method)->getDeclaringClass()->getName() !== 'ModuleApi';

	}

	/**
	 * L'union tient-elle tout ce que la closure nomme ?
	 *
	 * Les sondes ne peuvent pas satisfaire toutes les branches (une closure qui lit un élément lié,
	 * une autre qui délègue à une méthode), et rien à l'exécution ne dit ce qu'elles ont manqué. Le
	 * source, lui, le dit : on y relève les noms de propriétés que la closure cite en sortie, et
	 * l'union est complète si elle les porte tous.
	 *
	 * Les noms lus sur l'élément ($e['profile']) sont écartés : ce sont les aiguillages, pas des
	 * champs acceptés. Une closure qui ne cite aucune propriété, parce qu'elle en appelle une autre,
	 * ne prouve rien et laisse donc l'union ouverte.
	 */
	protected static function isUnionComplete(\Closure $closure, array $union, array $known): bool {

		$source = self::getSource(new \ReflectionFunction($closure));

		preg_match_all('/\'([a-zA-Z][a-zA-Z0-9_]*)\'/', $source, $cites);
		preg_match_all('/\[\s*\'([a-zA-Z][a-zA-Z0-9_]*)\'\s*\]/', $source, $lus);

		$nommes = array_intersect(array_diff($cites[1], $lus[1]), $known);

		if($nommes === []) {
			return FALSE;
		}

		return (array_diff($nommes, $union) === []);

	}

	protected static function getFallbackLabel(string $class): array {

		$name = substr($class, strrpos($class, '\\') + 1);
		$title = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $name));

		return [
			'title' => $title.' <!-- TODO: libellé français -->',
			'plural' => strtolower($title),
			'singular' => strtolower($title),
			'gender' => 'm',
			'description' => '<!-- TODO: description métier -->'
		];

	}

	/**
	 * Build the OpenAPI schema (+ example) for one object field, from its ModuleLib property
	 * ({type, params, nullable}, cf dev/module).
	 *
	 * Le type se lit sans sa taille (int32, text16, element64) : la taille ne change rien à ce que
	 * le client envoie ou reçoit.
	 */
	protected static function propertySchema(array $property, string $class): array {

		if($property === []) {
			return ['type' => 'string', 'description' => '<!-- TODO: type introuvable dans le schéma -->'];
		}

		$type = $property['type'] ?? '';
		$params = $property['params'] ?? NULL;
		$nullable = $property['nullable'] ?? FALSE;

		$fieldSchema = match(preg_replace('/[0-9]+$/', '', $type)) {
			'element' => self::elementSchema($params),
			'serial' => ['type' => 'integer', 'format' => 'int64'],
			'int' => ['type' => 'integer'],
			'float', 'decimal' => ['type' => 'number'],
			'bool' => ['type' => 'boolean'],
			'date' => ['type' => 'string', 'format' => 'date'],
			'datetime' => self::datetimeSchema(),
			'week' => ['type' => 'string', 'pattern' => '^[0-9]{4}-W[0-9]{2}$', 'description' => 'Semaine ISO 8601'],
			'month' => ['type' => 'string', 'pattern' => '^[0-9]{4}-[0-9]{2}$'],
			'time' => ['type' => 'string', 'pattern' => '^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$'],
			'email' => ['type' => 'string', 'format' => 'email'] + self::textSchema($params),
			'url' => ['type' => 'string', 'format' => 'uri'],
			'color' => ['type' => 'string', 'pattern' => '^#[0-9a-fA-F]{6}$', 'description' => 'Couleur hexadécimale'],
			'enum' => self::enumSchema($params, $class),
			// Une colonne json porte aussi bien une liste qu'un objet : son défaut est le seul indice
			'json' => (($property['default'] ?? NULL) === 'PHP([])') ? ['type' => 'array', 'items' => new \stdClass()] : ['type' => 'object'],
			'text', 'textFixed', 'editor', 'fqn' => self::textSchema($params),
			default => ['type' => 'string']
		};

		if($nullable) {
			$fieldSchema['nullable'] = TRUE;
		}

		$example = self::exampleValue($fieldSchema, $type);

		if($example !== NULL) {
			$fieldSchema['example'] = $example;
		}

		return $fieldSchema;

	}

	/**
	 * Une référence nomme la ressource qu'elle désigne quand celle-ci a sa page dans la référence :
	 * « Référence (identifiant) » laissait le lecteur deviner de quoi.
	 */
	protected static function elementSchema(mixed $params): array {

		$name = is_string($params) ? (self::$schemaNames[$params] ?? NULL) : NULL;

		return [
			'type' => 'integer',
			'format' => 'int64',
			'description' => ($name === NULL) ? 'Identifiant' : 'Identifiant d\'un élément `'.$name.'`'
		];

	}

	/**
	 * Les valeurs d'une énumération, déclarées par leurs constantes, ou empruntées à une autre colonne
	 * du module (`enum(PHP(Sale::model()->getPropertyEnum('preparationStatus')))`), que le modèle a
	 * déjà résolues. Une source qui ne se lit pas ne publie aucune liste plutôt qu'une liste vide, qui
	 * se lirait « aucune valeur acceptée » et que les validateurs OpenAPI refusent.
	 */
	protected static function enumSchema(mixed $params, string $class): array {

		if(is_array($params)) {
			return ['type' => 'string', 'enum' => array_map(fn($value) => self::resolveEnumValue($class, $value), $params)];
		}

		if(
			is_string($params) and
			preg_match('/([A-Za-z]+)::model\(\)->getPropertyEnum\(\'([a-zA-Z0-9]+)\'\)/', $params, $match) === 1
		) {

			$source = substr($class, 0, strrpos($class, '\\') + 1).$match[1];

			if(class_exists($source)) {
				return ['type' => 'string', 'enum' => array_values($source::model()->getPropertyEnum($match[2]))];
			}

		}

		return ['type' => 'string'];

	}

	protected static function textSchema(mixed $params): array {

		$schema = ['type' => 'string'];

		if(is_array($params) and count($params) === 2) {

			[$min, $max] = $params;

			if($min !== NULL and (int)$min > 0) {
				$schema['minLength'] = (int)$min;
			}

			if($max !== NULL) {
				$schema['maxLength'] = (int)$max;
			}

		}

		return $schema;

	}

	/**
	 * L'API rend et lit une date et heure telle que la base la porte, sans `T` ni fuseau : le
	 * `date-time` d'OpenAPI, qui est celui de la RFC 3339, promettait une autre forme.
	 */
	protected static function datetimeSchema(): array {

		return [
			'type' => 'string',
			'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$',
			'description' => 'Date et heure, au format AAAA-MM-JJ HH:MM:SS'
		];

	}

	/**
	 * Une valeur d'exemple que l'API accepterait, NULL quand il n'y en a pas de vraisemblable. Les
	 * dates sont fixes : tirées de l'horloge, elles changeaient la référence à chaque génération sans
	 * que rien d'autre ne change. Un champ qui peut être vide a aussi son exemple : sans lui, la
	 * documentation publiée en invente un, au format de JavaScript.
	 */
	protected static function exampleValue(array $fieldSchema, string $type): mixed {

		if(isset($fieldSchema['enum'])) {
			return $fieldSchema['enum'][0] ?? NULL;
		}

		return match(preg_replace('/[0-9]+$/', '', $type)) {
			'element', 'serial', 'int' => 1,
			'float', 'decimal' => 1.5,
			'bool' => FALSE,
			'date' => '2026-04-15',
			'datetime' => '2026-04-15 09:30:00',
			'week' => '2026-W16',
			'month' => '2026-04',
			'time' => '09:30:00',
			'email' => 'contact@example.org',
			'url' => 'https://example.org',
			'color' => '#373737',
			'json' => ($fieldSchema['type'] === 'array') ? [] : NULL,
			'text', 'textFixed', 'editor', 'fqn' => 'exemple',
			default => NULL
		};

	}

	/**
	 * Le yml ne connaît que le NOM de la constante enum (ex: BA_REEL_NORMAL) ; la valeur réellement
	 * échangée sur le fil est la valeur PHP de la constante, qui peut contenir des tirets au lieu
	 * d'underscores (financialYear\FinancialYear::BA_REEL_NORMAL === 'ba-reel-normal'). On la résout par
	 * réflexion et on ne retombe sur une approximation que si la constante n'existe pas.
	 */
	protected static function resolveEnumValue(string $class, string $constantName): string {

		if(defined($class.'::'.$constantName)) {
			return (string)constant($class.'::'.$constantName);
		}

		return strtolower($constantName);

	}

	/**
	 * Build the OpenAPI schema for a query/path parameter, from its Validator instance.
	 * Handles the two Validator subtypes actually used by ApiXxx::list()/getValidator() :
	 * FilterValidator (raw mask, ex. ['int32', 'min' => 1]) and PropertyValidator (delegates to
	 * a ModuleModel property, ex. Product::getValidator('id')) — resolved back to the same
	 * {type, params, nullable} shape as object fields via propertySchema().
	 */
	protected static function validatorSchema(\Validator $validator, ModuleLib $libModule): array {

		if($validator instanceof \FilterValidator) {

			$property = new \ReflectionProperty(\FilterValidator::class, 'mask');
			$property->setAccessible(TRUE);

			return self::maskSchema($property->getValue($validator));

		}

		if($validator instanceof \PropertyValidator) {

			$mProperty = new \ReflectionProperty(\PropertyValidator::class, 'm');
			$mProperty->setAccessible(TRUE);

			$propertyNameProperty = new \ReflectionProperty(\PropertyValidator::class, 'property');
			$propertyNameProperty->setAccessible(TRUE);

			$m = $mProperty->getValue($validator);
			$propertyName = $propertyNameProperty->getValue($validator);
			$targetClass = $m->getModule();

			return self::describe(self::propertySchema(self::getSchema($libModule, $targetClass)[$propertyName] ?? [], $targetClass), $targetClass, $propertyName);

		}

		if($validator instanceof \ArrayValidator) {

			$itemProperty = new \ReflectionProperty(\ArrayValidator::class, 'validator');
			$maxProperty = new \ReflectionProperty(\ArrayValidator::class, 'max');

			$schema = ['type' => 'array', 'items' => self::validatorSchema($itemProperty->getValue($validator), $libModule)];

			if($maxProperty->getValue($validator) !== NULL) {
				$schema['maxItems'] = $maxProperty->getValue($validator);
			}

			return $schema;

		}

		return ['type' => 'string'];

	}

	protected static function maskSchema(string|array $mask): array {

		$type = is_array($mask) ? ($mask[0] ?? '') : $mask;
		$options = is_array($mask) ? $mask : [];
		$nullable = ($options['null'] ?? FALSE) === TRUE;

		$schema = match(TRUE) {
			$type === 'serial' => ['type' => 'integer', 'format' => 'int64'],
			str_starts_with($type, 'int') => ['type' => 'integer'],
			str_starts_with($type, 'float'), $type === 'decimal' => ['type' => 'number'],
			$type === 'bool' => ['type' => 'boolean'],
			$type === 'date' => ['type' => 'string', 'format' => 'date'],
			$type === 'datetime' => self::datetimeSchema(),
			$type === 'enum' => ['type' => 'string', 'enum' => array_values($options[1])],
			default => ['type' => 'string']
		};

		if(array_key_exists('min', $options) and in_array($schema['type'], ['integer', 'number'], TRUE)) {
			$schema['minimum'] = $options['min'];
		}

		if(array_key_exists('max', $options) and in_array($schema['type'], ['integer', 'number'], TRUE)) {
			$schema['maximum'] = $options['max'];
		}

		if($nullable) {
			$schema['nullable'] = TRUE;
		}

		return $schema;

	}

	protected static function acceptsOverridden(string $class, string $method): bool {

		if(method_exists($class, $method) === FALSE) {
			return FALSE;
		}

		$reflection = new \ReflectionMethod($class, $method);

		return $reflection->getDeclaringClass()->getName() !== 'Element';

	}

	/**
	 * Split the object fields into the full read schema (every field pushed in the API output)
	 * and the write schema (subset accepted on create/update, cf getPropertiesCreate()). Every
	 * writable field is required, nullable or not : Api::convertProperties() refuses a body that
	 * lacks one of them (`.missing`), null being a value to send and not an absence.
	 *
	 * `$writable` à NULL veut dire que les champs acceptés n'ont pas pu être établis
	 * (getWritableProperties()) : plutôt qu'un objet sans propriété, qui se lit comme « rien n'est
	 * accepté », on publie un objet libre renvoyant à la description de la ressource.
	 *
	 * `$union` dit que la liste réunit des branches qui s'excluent (une facture de solde et une
	 * facture d'acompte n'acceptent pas les mêmes champs) : aucun champ n'y est alors marqué
	 * obligatoire, puisqu'aucun ne l'est dans tous les cas. À 'partial', l'union n'a pas pu être
	 * prouvée exhaustive et l'objet reste ouvert (cf getUnionKind()).
	 */
	protected static function buildResourceSchemas(string $module, array $fields, array $schema, array $write, array $label = [], array $fieldsDoc = []): array {

		$writable = $write['properties'];
		$union = $write['union'];

		$readProperties = [];
		$writeProperties = [];
		$required = [];

		foreach($fields as $field) {

			$property = $schema[$field] ?? [];
			// Un champ sans colonne n'a de type que celui que son package déclare (ApiUi::getFields())
			$fieldSchema = $fieldsDoc[$field] ?? self::describe(self::propertySchema($property, $module), $module, $field);
			unset($fieldSchema['gestures'], $fieldSchema['required']);

			// readOnly ne sort qu'en lecture : le même fragment sert le schéma d'écriture
			$readProperties[$field] = ($field === 'id') ? $fieldSchema + ['readOnly' => TRUE] : $fieldSchema;

			if($writable !== NULL and in_array($field, $writable, TRUE)) {
				$writeProperties[$field] = $fieldSchema;
				$required[] = $field;
			}

		}

		$readSchema = ['type' => 'object', 'properties' => $readProperties];

		if($writable === NULL) {
			return [$readSchema, self::freeWriteSchema($label)];
		}

		// Les champs qui n'existent qu'en écriture : une vente se crée avec 'productsList' et
		// 'source', qu'aucune lecture ne rend. Le schéma d'écriture étant bâti sur les champs de
		// sortie, ils n'apparaissaient nulle part, et le corps de requête publié était inutilisable.
		foreach(array_diff($writable, $fields) as $field) {

			$property = $schema[$field] ?? NULL;

			// Sans colonne, pas de type à annoncer : un schéma sans contrainte dit ce qu'on sait,
			// plutôt que d'inventer une chaîne là où l'API attend une liste ou un booléen
			if($property === NULL) {
				$writeProperties[$field] = $fieldsDoc[$field] ?? ['description' => "Champ accepté en écriture, sans équivalent en lecture."];
			} else {
				$writeProperties[$field] = self::describe(self::propertySchema($property, $module), $module, $field);
			}

			$required[] = $field;

		}

		// Un champ que la ressource lit dans le corps sans le construire n'est dans aucune liste de
		// propriétés : seule sa déclaration dit qu'il s'envoie. writeOnly (mot-clé OpenAPI) pour un
		// champ qui ne se relit pas, 'gestures' pour dire à quels gestes, la confirmation d'un
		// compteur ne servant parfois qu'à la création, et qui suffit seule pour un champ qui se relit,
		// comme l'intervention d'un temps de travail. 'required' le rend obligatoire. Ces deux clés ne
		// sont pas des mots d'OpenAPI et ne sortent pas.
		foreach($fieldsDoc as $field => $fieldSchema) {

			if(
				(
					($fieldSchema['writeOnly'] ?? FALSE) === TRUE or
					array_key_exists('gestures', $fieldSchema)
				) and
				array_intersect($fieldSchema['gestures'] ?? ['create', 'update'], $write['gestures']) !== []
			) {

				if(($fieldSchema['required'] ?? FALSE) === TRUE) {
					$required[] = $field;
				}

				unset($fieldSchema['gestures'], $fieldSchema['required']);
				$writeProperties[$field] = $fieldSchema;

			}

		}

		$writeSchema = ['type' => 'object', 'properties' => $writeProperties];

		if($union !== NULL) {

			// Sans champ obligatoire : la liste réunit des cas qui s'excluent, aucun ne vaut pour tous
			$writeSchema['description'] = "Les champs acceptés dépendent de l'élément envoyé : cette liste réunit ceux des différents cas et aucun n'y est obligatoire dans tous.";

			if($union === 'partial') {
				$writeSchema['additionalProperties'] = TRUE;
				$writeSchema['description'] .= " D'autres champs peuvent être acceptés selon l'élément.";
			}

			if(($label['description'] ?? '') !== '') {
				$writeSchema['description'] .= ' '.strip_tags($label['description']);
			}

			return [$readSchema, $writeSchema];

		}

		if($required !== []) {
			$writeSchema['required'] = $required;
		}

		return [$readSchema, $writeSchema];

	}

	/**
	 * Le libellé que l'écran donne à la colonne, et celui de chaque valeur d'une énumération. Une
	 * description déjà posée par le type (la ressource que désigne une référence) vient après.
	 */
	protected static function describe(array $fieldSchema, string $module, string $field): array {

		$describer = ApiUi::getDescriber($module, $field);

		$sentences = [];

		if($describer['label'] !== NULL) {
			$sentences[] = rtrim($describer['label'], '.').'.';
		}

		if(isset($fieldSchema['description'])) {
			$sentences[] = rtrim($fieldSchema['description'], '.').'.';
		}

		$values = array_intersect_key($describer['values'], array_flip($fieldSchema['enum'] ?? []));

		if($values !== []) {
			$sentences[] = 'Valeurs : '.implode(', ', array_map(fn($value, $label) => '`'.$value.'` ('.$label.')', array_keys($values), $values)).'.';
		}

		if($sentences !== []) {
			$fieldSchema['description'] = implode(' ', $sentences);
		}

		return $fieldSchema;

	}

	/**
	 * Le corps de requête d'une ressource dont les champs ne s'énumèrent pas : un objet libre, et
	 * la description de la ressource pour dire au lecteur où trouver ce qu'elle attend.
	 */
	protected static function freeWriteSchema(array $label): array {

		$description = "Les champs acceptés dépendent de l'élément envoyé et ne peuvent pas être listés ici.";

		if(($label['description'] ?? '') !== '') {
			$description .= ' '.strip_tags($label['description']);
		}

		return [
			'type' => 'object',
			'additionalProperties' => TRUE,
			'description' => $description,
		];

	}

	/**
	 * La ligne qui dit le droit nécessaire, posée sur chaque opération de la référence OpenAPI comme
	 * sur chaque requête de la collection Postman. La collection ne le portait nulle part, alors que
	 * c'est la première question d'un intégrateur qui vient d'obtenir une clé.
	 */
	protected static function scopeDescription(string $scope): string {
		return 'Scope requis : `'.$scope.'`.';
	}

	/**
	 * Une intégration qui lit ses éléments un par un épuise vite les appels du jour : la page d'un
	 * élément renvoie donc à la liste, qui en rend plusieurs par leurs identifiants en un seul appel.
	 * Seule une liste de premier niveau s'y prête, une sous-collection exigeant l'élément qui la cadre,
	 * et celle qui porte le nom de la ressource passe devant une variante comme le référentiel partagé
	 * d'une espèce, dont les identifiants ne sont pas ceux de la structure.
	 */
	protected static function readManyDescription(string $readRoute, array $lists, string $plural): string {

		$routes = [];

		foreach($lists as $list) {

			if($list['id'] === FALSE and array_key_exists('?ids', $list['arguments'])) {
				$routes[] = $list['requestRoute'];
			}

		}

		if($routes === []) {
			return '';
		}

		$route = strstr($readRoute, '/', TRUE).'List';

		if(in_array($route, $routes, TRUE) === FALSE) {
			$route = $routes[0];
		}

		return ' Pour lire plusieurs '.$plural.', `GET /'.$route.'?ids=12,15,18` en rend jusqu\'à 100, et ne compte que pour un appel.';

	}

	/**
	 * Les arguments d'une route GET, rendus en paramètres de query OpenAPI.
	 *
	 * Le nom porte son point d'interrogation en tête, là où Api::getArgument() le lit
	 * (str_starts_with) : le retirer est ce qui distingue le nom réel du marqueur de facultatif. Il
	 * était cherché en fin de nom, si bien que la référence publiée nommait les paramètres « ?start »,
	 * « ?limit », et les déclarait tous obligatoires.
	 *
	 * Un paramètre de query n'est jamais nul : absent, il n'est pas envoyé, d'où le retrait de
	 * nullable que porte la colonne dont il emprunte le type.
	 */
	protected static function queryParameters(array $arguments, ModuleLib $libModule): array {

		$parameters = [];

		foreach($arguments as $argName => $validator) {

			$optional = str_starts_with($argName, '?');
			$name = $optional ? substr($argName, 1) : $argName;

			$schema = self::validatorSchema($validator, $libModule);
			unset($schema['nullable']);

			$parameter = [
				'name' => $name,
				'in' => 'query',
				'required' => $optional === FALSE
			];

			$description = self::PAGINATION_PARAMETERS[$name] ?? $schema['description'] ?? NULL;
			unset($schema['description']);

			if($description !== NULL) {
				$parameter['description'] = $description;
			}

			// ids=1,2,3 comme ids[]=1&ids[]=2 : la forme documentée est la plus courte
			if($schema['type'] === 'array') {
				$parameter['style'] = 'form';
				$parameter['explode'] = FALSE;
			}

			$parameter['schema'] = $schema;

			if($name === 'limit') {
				$parameter['schema']['default'] = 100;
			} else if($name === 'start') {
				$parameter['schema']['default'] = 1;
			}

			$parameters[] = $parameter;

		}

		return $parameters;

	}

	protected static function addPaths(array &$paths, array $routeInfo, string $name, string $updateName, array $label, string $tag, string $class, ModuleLib $libModule, array $standaloneRules, array $doc, \Api $instance): void {

		$singular = $label['singular'];
		$plural = $label['plural'];
		$article = $label['gender'] === 'f' ? 'une' : 'un';

		foreach($routeInfo['list'] as $list) {

			$parameters = self::queryParameters($list['arguments'], $libModule);

			if($list['id']) {
				$parameters[] = self::idParameter();
			}

			// Une sous-collection ne rend pas la ressource qui la cadre : market/{id}/itemsList liste
			// des articles de vente, pas des marchés — ce que seul son libellé déclaré peut dire.
			$route = self::getRouteLabel($doc['routes'], 'GET', $list['requestRoute']);
			$listed = self::getListedResource($route, $doc['exposed'], $name, $label);

			$paths['/'.$list['requestRoute']]['get'] = [
				'tags' => [$tag],
				'summary' => $route['label'] ?? 'Lister les '.$plural,
				'description' => self::scopeDescription($list['scope']),
				'parameters' => $parameters,
				'responses' => [
					'200' => [
						'description' => 'Liste paginée des '.$listed['label']['plural'].'.',
						'content' => [
							'application/json' => [
								'schema' => [
									'type' => 'object',
									'properties' => [
										'count' => ['type' => 'integer', 'description' => "Nombre d'éléments que retiennent les filtres, sans tenir compte de la pagination (`start`, `limit`, `afterId`, `beforeId`)."],
										'list' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/'.$listed['name']]]
									]
								]
							]
						]
					],
					'400' => ['$ref' => '#/components/responses/ValidationError']
				]
			];

		}

		if($routeInfo['read'] !== NULL) {

			$paths['/'.$routeInfo['read']['requestRoute']]['get'] = [
				'tags' => [$tag],
				'summary' => 'Consulter '.$article.' '.$singular,
				'description' => self::scopeDescription($routeInfo['read']['scope']).self::readManyDescription($routeInfo['read']['requestRoute'], $routeInfo['list'], $plural),
				'parameters' => [self::idParameter()],
				'responses' => [
					'200' => self::resourceResponse($name, ucfirst($singular).' demandé'.($label['gender'] === 'f' ? 'e' : '').'.'),
					'400' => ['$ref' => '#/components/responses/ValidationError'],
					'404' => ['$ref' => '#/components/responses/NotFound']
				]
			];

		}

		if($routeInfo['create'] !== NULL) {

			$responses = [];

			foreach(self::getSuccessCodes($routeInfo['create'], $instance) as $code) {

				$responses[$code] = self::resourceResponse($name, match($code) {
					201 => ucfirst($singular).' créé'.($label['gender'] === 'f' ? 'e' : '').'.',
					default => ucfirst($singular).' à jour.'
				});

			}

			if(self::acceptsOverridden($class, 'acceptCreate')) {
				$responses['412'] = self::errorResponse('Condition métier interdisant la création.');
			}

			$paths['/'.$routeInfo['create']['requestRoute']]['post'] = [
				'tags' => [$tag],
				'summary' => 'Créer '.$article.' '.$singular,
				'description' => self::scopeDescription($routeInfo['create']['scope']),
				// Une création peut elle aussi attendre autre chose que le schéma d'écriture : POST
				// marketSale ouvre un panier vide et ne lit que la référence du marché.
				'requestBody' => self::getActionBody(
					self::getRouteLabel($doc['routes'], $routeInfo['create']['requestMethod'], $routeInfo['create']['requestRoute']),
					$name.'Write',
				) ?? self::requestBody($name.'Write'),
				'responses' => $responses + [
					'400' => ['$ref' => '#/components/responses/ValidationError']
				]
			];

		}

		if($routeInfo['update'] !== NULL) {

			$responses = [
				'200' => self::resourceResponse($name, ucfirst($singular).' modifié'.($label['gender'] === 'f' ? 'e' : '').'.'),
				'400' => ['$ref' => '#/components/responses/ValidationError'],
				'404' => ['$ref' => '#/components/responses/NotFound']
			];

			if(self::acceptsOverridden($class, 'acceptUpdate')) {
				$responses['412'] = self::errorResponse('Condition métier interdisant la modification.');
			}

			$description = self::scopeDescription($routeInfo['update']['scope']).' Le corps porte tous les champs modifiables, ou seulement ceux que nomme sa clé `properties` (mise à jour partielle, voir la vue d\'ensemble).';

			if($standaloneRules['group'] !== []) {

				$groups = implode(' ; ', array_map(
					fn($group) => implode(' + ', array_map(fn($property) => '`'.$property.'`', $group)),
					$standaloneRules['group']
				));

				$description .= ' En partiel, doivent être envoyées ensemble : '.$groups.'.';

			}

			if($standaloneRules['exclude'] !== []) {
				$description .= ' Non éligibles à la mise à jour partielle : `'.implode('`, `', $standaloneRules['exclude']).'`.';
			}

			$paths['/'.$routeInfo['update']['requestRoute']]['put'] = [
				'tags' => [$tag],
				'summary' => 'Modifier '.$article.' '.$singular,
				'description' => $description,
				'parameters' => [self::idParameter()],
				'requestBody' => self::updateBody($updateName, $standaloneRules),
				'responses' => $responses
			];

		}

		if($routeInfo['delete'] !== NULL) {

			$responses = [
				'200' => ['description' => 'Supprimé. Le corps de la réponse est vide.'],
				'404' => ['$ref' => '#/components/responses/NotFound']
			];

			if(self::acceptsOverridden($class, 'acceptDelete')) {
				$responses['412'] = self::errorResponse('Condition métier interdisant la suppression.');
			}

			$paths['/'.$routeInfo['delete']['requestRoute']]['delete'] = [
				'tags' => [$tag],
				'summary' => 'Supprimer '.$article.' '.$singular,
				'description' => self::scopeDescription($routeInfo['delete']['scope']),
				'parameters' => [self::idParameter()],
				'responses' => $responses
			];

		}

		foreach($routeInfo['actions'] as $action) {

			$route = self::getRouteLabel($doc['routes'], $action['requestMethod'], $action['requestRoute']);
			$isRead = ($action['requestMethod'] === 'GET');

			$parameters = $action['id'] ? [self::idParameter()] : [];

			// Un GET porte ses arguments dans la query ; les autres verbes les prennent dans le corps,
			// que 'body' décrit déjà.
			if($isRead) {
				$parameters = array_merge($parameters, self::queryParameters($action['arguments'], $libModule));
			}

			$path = [
				'tags' => [$tag],
				'summary' => $route['label'] ?? self::getActionSummary($action['requestRoute'], $label, $isRead),
				'description' => self::scopeDescription($action['scope']),
				'parameters' => $parameters
			];

			if($isRead === FALSE) {

				$body = self::getActionBody($route, $name.'Write');

				if($body !== NULL) {
					$path['requestBody'] = $body;
				}

			}

			$path['responses'] = array_fill_keys(
				self::getSuccessCodes($action, $instance),
				self::getActionResponse($route, $name, $singular, $isRead)
			) + [
				'400' => ['$ref' => '#/components/responses/ValidationError'],
				'404' => ['$ref' => '#/components/responses/NotFound'],
				'412' => self::errorResponse('Condition métier interdisant cette action.')
			] + self::getThrownResponses($action);

			$paths['/'.$action['requestRoute']][strtolower($action['requestMethod'])] = $path;

		}

	}

	/**
	 * Les refus qu'une route lève elle-même et qu'aucune réponse partagée ne couvre, lus dans son
	 * source comme ses codes de succès : un 409 posé dans une seule action n'apparaissait nulle part.
	 */
	protected static function getThrownResponses(array $route): array {

		preg_match_all('/ApiException\([^;]*?,\s*(\d{3})\s*[,)]/', self::getSource(new \ReflectionFunction($route['callback'])), $matches);

		$responses = [];

		if(in_array('409', $matches[1], TRUE)) {
			$responses['409'] = self::errorResponse("L'état de la ressource a changé pendant l'appel.");
		}

		return $responses;

	}

	/**
	 * Le corps d'une modification, complet ou partiel. Le partiel nomme dans `properties` les champs
	 * qu'il envoie, et seuls ceux-là sont lus et exigés (ModuleApi::update()) : la référence ne le
	 * disait qu'en prose, et son schéma réclamait tous les champs.
	 */
	protected static function updateBody(string $schemaName, array $standaloneRules): array {

		$items = ['type' => 'string'];

		if($standaloneRules['properties'] !== NULL) {

			$standalone = array_values(array_diff($standaloneRules['properties'], $standaloneRules['exclude']));

			if($standalone !== []) {
				$items['enum'] = $standalone;
			}

		}

		return [
			'required' => TRUE,
			'content' => [
				'application/json' => [
					'schema' => [
						'oneOf' => [
							['$ref' => '#/components/schemas/'.$schemaName],
							[
								'type' => 'object',
								'title' => 'Mise à jour partielle',
								'description' => 'Les champs nommés dans `properties`, et eux seuls, chacun à sa nouvelle valeur.',
								'required' => ['properties'],
								'properties' => [
									'properties' => ['type' => 'array', 'minItems' => 1, 'items' => $items]
								],
								'additionalProperties' => TRUE
							]
						]
					]
				]
			]
		];

	}

	/**
	 * Les codes de succès qu'une route peut rendre, lus dans son source : elle les pose en littéral
	 * sur ApiData::$httpCode, et une création du socle les tient de pushCreated(), qu'une ressource
	 * redéfinit quand son écriture ne produit pas toujours une ligne neuve. La lecture ne pèse pas
	 * les conditions : un code posé sous condition s'annonce à côté de celui qu'il remplace.
	 */
	protected static function getSuccessCodes(array $route, \Api $instance): array {

		$codes = self::readHttpCodes(new \ReflectionFunction($route['callback']), $instance);

		if($codes === []) {
			$codes[] = new \ReflectionProperty('ApiData', 'httpCode')->getDefaultValue();
		}

		sort($codes);

		return $codes;

	}

	protected static function readHttpCodes(\ReflectionFunctionAbstract $reflection, \Api $instance): array {

		$source = self::getSource($reflection);

		preg_match_all('/->httpCode\s*=\s*(\d{3})\s*;/', $source, $literals);

		// Un code calculé ne se lit pas dans le source : la référence annoncerait le défaut à sa place.
		if(count($literals[1]) !== preg_match_all('/->httpCode\s*=(?!=)/', $source)) {
			throw new \Exception($reflection->getFileName().':'.$reflection->getStartLine().' sets httpCode from something else than a literal');
		}

		$codes = array_map('intval', $literals[1]);

		if(str_contains($source, 'parent::pushCreated(')) {

			$class = $reflection instanceof \ReflectionMethod ? $reflection->getDeclaringClass() : $reflection->getClosureScopeClass();

			$codes = array_merge($codes, self::readHttpCodes($class->getParentClass()->getMethod('pushCreated'), $instance));

		}

		if(str_contains($source, '$this->pushCreated(')) {
			$codes = array_merge($codes, self::readHttpCodes(new \ReflectionMethod($instance, 'pushCreated'), $instance));
		}

		return array_values(array_unique($codes));

	}

	protected static function getSource(\ReflectionFunctionAbstract $reflection): string {

		return implode('', array_slice(
			file($reflection->getFileName()),
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));

	}

	/**
	 * Ce qu'une action rend en cas de succès.
	 *
	 * Une action d'écriture rend la ressource à jour, et c'est le cas général. Une route GET sur
	 * mesure rend ce qu'elle veut : le FEC rend un fichier, le grand livre des comptes, et présenter
	 * la ressource à leur place ferait écrire un client contre un schéma qu'il ne recevra jamais.
	 * Elle le déclare dans son entrée d'apiRoutes() sous la clé 'response', avec 'contentType' quand
	 * elle ne rend pas du JSON (un PDF, par ApiData::pushPdf()) ; à défaut, la documentation dit
	 * qu'elle ne connaît pas la forme plutôt que d'en inventer une.
	 */
	protected static function getActionResponse(array $route, string $name, string $singular, bool $isRead): array {

		if(isset($route['response'])) {

			return [
				'description' => $route['response']['description'] ?? 'Réponse de la route.',
				'content' => [($route['response']['contentType'] ?? 'application/json') => ['schema' => $route['response']['schema'] ?? ['type' => 'object']]]
			];

		}

		if($isRead) {

			return [
				'description' => 'Réponse propre à cette route.',
				'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => TRUE]]]
			];

		}

		return self::resourceResponse($name, ucfirst($singular).' à jour.');

	}

	/**
	 * Ce qu'une route déclare d'elle-même à la documentation (ApiUi::getRoutes(), rempli par les
	 * DevObserverUi::apiRoutes() des packages) : son résumé, et pour une sous-liste le module qu'elle
	 * rend. Une entrée qui n'est qu'une phrase se lit comme un libellé seul.
	 */
	protected static function getRouteLabel(array $routes, string $requestMethod, string $requestRoute): array {

		$route = $routes[$requestMethod.' '.$requestRoute] ?? [];

		return is_array($route) ? $route : ['label' => $route];

	}

	/**
	 * Le module rendu par une liste : celui qu'elle déclare, ou à défaut la ressource qui porte la
	 * route. Un module cité mais non exposé n'aurait pas de schéma à référencer — autant le dire à la
	 * génération que laisser la référence pendre dans la spec publiée.
	 */
	protected static function getListedResource(array $route, array $exposed, string $name, array $label): array {

		$resource = $route['resource'] ?? NULL;

		if($resource === NULL) {
			return ['name' => $name, 'label' => $label];
		}

		return $exposed[$resource] ?? throw new \Exception($resource.' is listed by a route but not exposed');

	}

	/**
	 * Le résumé de repli d'une route qui ne déclare pas le sien.
	 *
	 * Une lecture sans identifiant ni pagination lit la fiche unique de la structure (configuration,
	 * system, me) et se dit comme telle. Ailleurs, le dernier segment nomme le verbe métier
	 * (sale/{id}/addProducts), en anglais et sans le complément que la phrase demanderait.
	 */
	protected static function getActionSummary(string $route, array $label, bool $isRead): string {

		$singular = $label['singular'];

		if($isRead and str_contains($route, '/') === FALSE) {

			if(preg_match('/^[aeiouyéèêh]/iu', $singular) === 1) {
				$article = 'l\'';
			} else if($label['gender'] === 'f') {
				$article = 'la ';
			} else {
				$article = 'le ';
			}

			return 'Consulter '.$article.$singular;

		}

		$segments = explode('/', $route);

		return ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', end($segments)))).' ('.$singular.')';

	}

	protected static function idParameter(): array {

		return [
			'name' => 'id',
			'in' => 'path',
			'required' => TRUE,
			'schema' => ['type' => 'integer', 'format' => 'int64']
		];

	}

	protected static function resourceResponse(string $name, string $description): array {

		return [
			'description' => $description,
			'content' => [
				'application/json' => [
					'schema' => ['$ref' => '#/components/schemas/'.$name]
				]
			]
		];

	}

	/**
	 * Le corps qu'attend une route d'action.
	 *
	 * Le générateur ne peut pas le déduire : ce qu'une action lit est enfoui dans sa closure. Faute
	 * de mieux il publiait le schéma d'écriture de la ressource pour toutes, ce qui annonçait les
	 * trente et un champs d'un produit à qui voulait seulement déposer sa vignette, et sept champs
	 * de facture à qui voulait confirmer une facture sans rien envoyer du tout.
	 *
	 * La route le déclare donc elle-même dans l'apiRoutes() de son package : FALSE pour une action
	 * qui ne lit pas le corps, un schéma pour celles qui en attendent un sur mesure. Sans
	 * déclaration, le schéma de la ressource reste publié comme avant.
	 */
	protected static function getActionBody(array $route, string $schemaName): ?array {

		if(array_key_exists('body', $route) === FALSE) {
			return self::requestBody($schemaName);
		}

		if($route['body'] === FALSE) {
			return NULL;
		}

		return [
			'required' => TRUE,
			'content' => [
				'application/json' => [
					'schema' => $route['body']
				]
			]
		];

	}

	protected static function requestBody(string $schemaName): array {

		return [
			'required' => TRUE,
			'content' => [
				'application/json' => [
					'schema' => ['$ref' => '#/components/schemas/'.$schemaName]
				]
			]
		];

	}

	protected static function errorResponse(string $description): array {

		return [
			'description' => $description,
			'content' => [
				'application/json' => [
					'schema' => [
						'type' => 'array',
						'items' => ['$ref' => '#/components/schemas/Error']
					]
				]
			]
		];

	}

	/**
	 * Rebuild the 'Ressources disponibles' section of endpoints/README.md from the resource
	 * schemas of the generated OpenAPI spec (title/description/x-scope) — single source of
	 * truth shared with openapi.json, no more per-page links since the detail now lives in the
	 * OpenAPI reference.
	 *
	 * Un bloc dépliant par scope plutôt qu'une table unique : à plus de soixante ressources, la
	 * table couvrait plusieurs écrans et rien n'indiquait qu'il y avait autre chose en dessous.
	 * Les quatre scopes tiennent maintenant sur un écran, repliés (GitBook rend <details> comme
	 * son bloc Expandable, fermé tant qu'il ne porte pas 'open'), et la colonne Scope disparaît
	 * puisque c'est le bloc qui la porte.
	 *
	 * Une liste et non une table à l'intérieur : le bloc Expandable n'accepte que paragraphes,
	 * titres, listes et images en ligne. Les descriptions restent ici — la table des pages de
	 * scope les reprend (cf buildTagSummary), mais ce sommaire-ci se parcourt d'un seul Ctrl+F.
	 */
	public static function updateReadme(string $path, array $spec): void {

		if(file_exists($path) === FALSE) {
			return;
		}

		$content = file_get_contents($path);

		$scopes = self::getScopes();
		$entriesByScope = [];

		foreach($spec['components']['schemas'] as $schemaName => $schema) {

			// Seules les ressources portent un scope : les schémas d'écriture et Error l'ignorent
			if(array_key_exists('x-scope', $schema) === FALSE) {
				continue;
			}

			$title = strip_tags($schema['title'] ?? $schemaName);

			// Le bloc porte le scope, que chaque ligne n'a donc plus à répéter. Sans filtre sur les
			// modules exposés sans route de leur cru (ex. les dépréciations d'une immobilisation) :
			// ils n'ont pas de page dans la référence mais se lisent dans la ressource qui les
			// embarque, et ce sommaire-ci les annonce — le sommaire d'une page de scope, lui, ne
			// peut lister que les pages ouvertes sous elle (cf buildTagSummary).
			$scope = ($scopes[$schema['x-scope'] ?? ''] ?? $scopes['general'])['title'];

			$entriesByScope[$scope][] = [$title, '* **'.$title.'**. '.strip_tags($schema['description'] ?? '')];

		}

		// Tri alphabétique par titre (FR-aware, accents) plutôt que par ordre d'exposition des modules
		$collator = \L::getCollator();
		$blocks = [];

		// Les scopes dans l'ordre de getScopes(), le même que celui de la référence OpenAPI
		foreach($scopes as $scope) {

			$entries = $entriesByScope[$scope['title']] ?? [];

			if($entries === []) {
				continue;
			}

			usort($entries, fn($a, $b) => $collator->compare($a[0], $b[0]));

			$blocks[] = "<details>\n\n<summary>".$scope['title']."</summary>\n\n".implode("\n", array_column($entries, 1))."\n\n</details>";

		}

		// Ne remplace que le bloc généré : les blocs dépliants qui suivent le titre de section, ou
		// la table qui les précédait, et rien au-delà — la prose qui ferme la section est écrite à
		// la main dans api-doc.
		$content = preg_replace_callback(
			'/(## Ressources disponibles\n\n)(?:(?:<details>.*?<\/details>|\|[^\n]*)\n+)*/s',
			fn($matches) => $matches[1].implode("\n\n", $blocks)."\n\n",
			$content,
			1
		);

		file_put_contents($path, $content);

	}

	/**
	 * Endpoints menu of SUMMARY.md : just the 'Vue d'ensemble' page now — the per-route detail
	 * (methode/route, autorisations, paramètres, corps, réponses) lives in the OpenAPI reference
	 * that GitBook generates once openapi.json is registered (Space > OpenAPI), which manages
	 * its own navigation outside of SUMMARY.md.
	 */
	public static function updateSummary(string $path): void {

		if(file_exists($path) === FALSE) {
			return;
		}

		$content = file_get_contents($path);

		$bullet = "* [Vue d'ensemble](endpoints/README.md)\n";

		if(str_contains($content, '## Endpoints') === FALSE) {
			$content = rtrim($content)."\n\n## Endpoints\n\n".$bullet;
		} else if(str_contains($content, $bullet) === FALSE) {
			// Insert right after the '## Endpoints' header without touching whatever follows —
			// ex. GitBook's own OpenAPI reference block, injected via its UI (Space > OpenAPI),
			// must survive untouched across regenerations.
			$content = preg_replace('/## Endpoints\n\n?/', "## Endpoints\n\n".$bullet, $content, 1);
		}

		file_put_contents($path, $content);

	}

}
?>
