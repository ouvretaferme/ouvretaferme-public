<?php
namespace user;

/**
 * Journal des pages de l'administration du produit : qui a ouvert quoi, et quand.
 *
 * `User::validateAdmin()` enregistre une ligne par requête acceptée, page lue comme action postée,
 * et chaque page de `page/admin/` l'appelle dans son constructeur : c'est le seul point d'accroche,
 * il n'y a rien à poser dans les pages. Une requête n'écrit qu'une ligne, même quand elle valide
 * deux fois (le constructeur de la page puis l'action, ou une page d'erreur qui rejoue le
 * constructeur).
 *
 * Les connexions en tant qu'un autre compte s'y lisent comme une écriture ordinaire de
 * `/user/log:doLoginExternal`. Celles enregistrées dans `userLog` avant l'existence de ce journal
 * y ont été reprises sous la même forme à la mise en production du 23/09/2026.
 *
 * Ce que l'administrateur fait ensuite sur le compte emprunté s'écrit aussi (`recordExternal()`,
 * appelé par `\main\PageLib::common()` que toute page traverse), avec ce compte dans
 * `userExternal` : les pages lues comme les écritures, jusqu'au retour à son propre compte. La
 * connexion elle-même porte le compte emprunté, pour que la recherche par compte trouve la session
 * entière.
 *
 * Les paramètres de la requête sont gardés après le masquage du journal des erreurs
 * (`ErrorPhpLib::hideSecret()`), pour la même raison que lui : ce journal se lit dans l'admin, et
 * il ne doit pas y montrer un mot de passe ni un jeton qu'un formulaire aurait posté.
 */
class AdminLogAiLib extends AdminLogCrud {

	const REQUEST_LOGIN_EXTERNAL = '/user/log:doLoginExternal';
	const REQUEST_LOGOUT_EXTERNAL = '/user/log:doLogoutExternal';

	/**
	 * Les actions postées qui ne font que lire, sur un compte emprunté : une autocomplétion, un
	 * champ ou un fragment qu'une page recharge. La page qui les porte est déjà écrite en lecture,
	 * et une autocomplétion écrirait une ligne par touche frappée.
	 */
	const EXTERNAL_READ_ACTIONS = ['query', 'get', 'select'];

	/**
	 * Les requêtes qu'une page déclenche d'elle-même sur un compte emprunté, sans geste de
	 * l'administrateur : les feuilles de style et les scripts, que la production sert par la route
	 * PHP de `framework/dev/page/minify.p.php` dès que `DevSetting::$minify` est allumé (en dev, ils
	 * sont servis en statique et ne passent jamais ici).
	 */
	const EXTERNAL_IGNORED_REQUESTS = ['/minify/'];

	const TYPE_READ = 'read';
	const TYPE_WRITE = 'write';
	const TYPE_LOGIN_EXTERNAL = 'login-external';

	const TARGET_USER = 'user';
	const TARGET_STRUCTURE = 'structure';
	const TARGET_ERROR = 'error';

	/**
	 * Une valeur postée plus longue que ça est coupée : le journal dit sur quoi l'action a porté, il
	 * ne garde pas le texte d'un message ou d'un formulaire entier.
	 */
	const VALUE_LENGTH = 200;

	/**
	 * Le paramètre qui désigne la cible d'une action, quand la route seule ne suffit pas : `id` sur
	 * une page d'utilisateur désigne un utilisateur, sur une page de structure une structure.
	 */
	const TARGET_PARAMETERS = [
		'user' => self::TARGET_USER,
		'userToBan' => self::TARGET_USER,
		'userId' => self::TARGET_USER,
		'structure' => self::TARGET_STRUCTURE,
	];

	const TARGET_REQUESTS = [
		'/user/' => self::TARGET_USER,
		'/structure/' => self::TARGET_STRUCTURE,
		'/dev/admin/' => self::TARGET_ERROR,
	];

	private static bool $recorded = FALSE;

	/**
	 * Enregistre la requête en cours pour cet administrateur, une seule fois par requête.
	 */
	public static function record(User $eUser): void {

		if(self::$recorded) {
			return;
		}

		self::$recorded = TRUE;

		$eAdminLog = self::prepareEntry(
			$eUser,
			\Page::getRequest(),
			\Page::getName(),
			\Route::getRequestMethod(),
			$_GET,
			$_POST
		);

		self::create($eAdminLog);

	}

	/**
	 * Enregistre la requête en cours, faite par `$eUser` sur le compte `$eUserExternal` qu'il a
	 * emprunté, une seule fois par requête.
	 */
	public static function recordExternal(User $eUser, User $eUserExternal): void {

		if(self::$recorded) {
			return;
		}

		self::$recorded = TRUE;

		$method = \Route::getRequestMethod();

		if(self::acceptExternal($method, LIME_REQUEST_PATH, \Page::getName()) === FALSE) {
			return;
		}

		$eAdminLog = self::prepareExternalEntry(
			$eUser,
			$eUserExternal,
			LIME_REQUEST_PATH,
			$method,
			$_GET,
			$_POST
		);

		self::create($eAdminLog);

	}

	public static function acceptExternal(string $method, string $request, string $name): bool {

		foreach(self::EXTERNAL_IGNORED_REQUESTS as $prefix) {

			if(str_starts_with($request, $prefix)) {
				return FALSE;
			}

		}

		if($method !== 'POST') {
			return TRUE;
		}

		foreach(self::EXTERNAL_READ_ACTIONS as $prefix) {

			if(str_starts_with($name, $prefix)) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Construit la ligne d'une requête. La route est celle que le cadriciel a résolue, avec le nom
	 * de l'action quand ce n'est pas la page elle-même : `/user/admin/`, `/dev/admin/:doStatus`.
	 */
	public static function prepareEntry(User $eUser, string $request, string $name, string $method, array $get, array $post): AdminLog {

		$eUser->expects(['id']);

		$route = self::getRoute($request, $name);

		return new AdminLog([
			'user' => $eUser,
			'userExternal' => self::getLoginExternalUser($route, $post),
			'request' => $route,
			'method' => self::getMethod($method),
			'parameters' => self::hide($post + $get),
		]);

	}

	/**
	 * Construit la ligne d'une requête faite sur un compte emprunté. La page s'y écrit par son
	 * adresse et non par son fichier : dans le produit, `/ferme/3994/ventes` dit la structure que
	 * `selling/sale` tait.
	 */
	public static function prepareExternalEntry(User $eUser, User $eUserExternal, string $path, string $method, array $get, array $post): AdminLog {

		$eUser->expects(['id']);
		$eUserExternal->expects(['id']);

		return new AdminLog([
			'user' => $eUser,
			'userExternal' => $eUserExternal,
			'request' => mb_substr($path, 0, AdminLog::model()->getPropertyRange('request')[1]),
			'method' => self::getMethod($method),
			'parameters' => self::hide($post + $get),
		]);

	}

	/**
	 * Les lignes qui répondent à la recherche, la page demandée et leur nombre total.
	 */
	public static function getAll(\Search $search, int $page): array {

		self::applySearch($search);

		$cAdminLog = AdminLog::model()
			->select(AdminLog::getSelection() + [
				'user' => User::getSelection(),
				'userExternal' => User::getSelection()
			])
			->sort(['createdAt' => SORT_DESC, 'id' => SORT_DESC])
			->option('count')
			->getCollection($page * UserSetting::MAX_BY_PAGE, UserSetting::MAX_BY_PAGE);

		$nAdminLog = AdminLog::model()->found();

		self::loadTargets($cAdminLog);

		return [$cAdminLog, $nAdminLog];

	}

	public static function applySearch(\Search $search): void {

		if($search->get('external')) {

			$mUser = User::model()
				->select('id')
				->or(
					fn() => $this->whereEmail('LIKE', '%'.$search->get('external').'%'),
					fn() => $this->where(new \Sql("CONCAT_WS(' ', firstName, lastName)"), 'LIKE', '%'.$search->get('external').'%')
				);

			AdminLog::model()->whereUserExternal('IN', $mUser->getSql());

		}

		AdminLog::model()
			->whereUser($search->get('user'), if: $search->get('user'))
			->where('m1.request', 'LIKE', '%'.$search->get('request').'%', if: $search->get('request'))
			->where('m1.createdAt', '>=', $search->get('from'), if: $search->get('from'))
			->where('m1.createdAt', '<', new \Sql(AdminLog::model()->format($search->get('to')).' + INTERVAL 1 DAY'), if: $search->get('to'));

		switch($search->get('type')) {

			case self::TYPE_READ :
				AdminLog::model()->whereMethod(AdminLog::GET);
				break;

			case self::TYPE_WRITE :
				AdminLog::model()
					->whereMethod(AdminLog::POST)
					->whereRequest('!=', self::REQUEST_LOGIN_EXTERNAL);
				break;

			case self::TYPE_LOGIN_EXTERNAL :
				AdminLog::model()->whereRequest(self::REQUEST_LOGIN_EXTERNAL);
				break;

		}

	}

	/**
	 * Ce sur quoi la requête a porté, quand un paramètre le dit : `[type, id]`, ou `[]`.
	 */
	public static function getTarget(AdminLog $e): array {

		$e->expects(['request', 'parameters']);

		$parameters = $e['parameters'] ?? [];

		foreach(self::TARGET_PARAMETERS as $parameter => $type) {

			if(is_numeric($parameters[$parameter] ?? NULL)) {
				return [$type, (int)$parameters[$parameter]];
			}

		}

		if(preg_match('#^/ferme/([0-9]+)#', $e['request'], $match) === 1) {
			return [self::TARGET_STRUCTURE, (int)$match[1]];
		}

		if(is_numeric($parameters['id'] ?? NULL)) {

			foreach(self::TARGET_REQUESTS as $prefix => $type) {

				if(str_starts_with($e['request'], $prefix)) {
					return [$type, (int)$parameters['id']];
				}

			}

		}

		return [];

	}

	/**
	 * Pose sur chaque ligne sa cible : `eUserTarget` chargé quand c'est un utilisateur,
	 * `structureTarget` et `errorTarget` en identifiant, le journal ne chargeant pas les autres
	 * packages.
	 */
	public static function loadTargets(\Collection $cAdminLog): void {

		$userIds = [];

		foreach($cAdminLog as $eAdminLog) {

			$eAdminLog['eUserTarget'] = new User();
			$eAdminLog['structureTarget'] = NULL;
			$eAdminLog['errorTarget'] = NULL;

			$target = self::getTarget($eAdminLog);

			if($target === []) {
				continue;
			}

			[$type, $id] = $target;

			switch($type) {

				case self::TARGET_USER :
					$userIds[] = $id;
					break;

				case self::TARGET_STRUCTURE :
					$eAdminLog['structureTarget'] = $id;
					break;

				case self::TARGET_ERROR :
					$eAdminLog['errorTarget'] = $id;
					break;

			}

		}

		if($userIds === []) {
			return;
		}

		$cUser = User::model()
			->select(User::getSelection())
			->whereId('IN', $userIds)
			->getCollection(NULL, NULL, 'id');

		foreach($cAdminLog as $eAdminLog) {

			$target = self::getTarget($eAdminLog);

			if($target !== [] and $target[0] === self::TARGET_USER and $cUser->offsetExists($target[1])) {
				$eAdminLog['eUserTarget'] = $cUser[$target[1]];
			}

		}

	}

	/**
	 * Le journal se garde aussi longtemps que `userLog`.
	 */
	public static function clean(): void {

		AdminLog::model()
			->where('createdAt < NOW() - INTERVAL '.UserSetting::KEEP_LOGS.' DAY')
			->delete();

	}

	/**
	 * La requête que porte `\Page` est le chemin du fichier de page, donc `structure/admin/index`
	 * pour `index.p.php` : la route rendue reprend la forme de l'URL, `/structure/admin/`, celle
	 * que les libellés et les cibles connaissent.
	 */
	private static function getRoute(string $request, string $name): string {

		$request = '/'.ltrim($request, '/');

		if(str_ends_with($request, '/index')) {
			$request = substr($request, 0, -strlen('index'));
		}

		return $request.($name === 'index' ? '' : ':'.$name);

	}

	/**
	 * Le compte qu'emprunte une connexion en tant qu'un autre compte, lu dans ce qu'elle poste.
	 */
	private static function getLoginExternalUser(string $route, array $post): User {

		if($route === self::REQUEST_LOGIN_EXTERNAL and is_numeric($post['user'] ?? NULL)) {
			return new User(['id' => (int)$post['user']]);
		}

		return new User();

	}

	private static function getMethod(string $method): string {

		return match($method) {
			'POST' => AdminLog::POST,
			default => AdminLog::GET
		};

	}

	private static function hide(array $parameters): array {

		foreach($parameters as $name => $value) {

			if(is_array($value)) {
				$parameters[$name] = self::hide($value);
				continue;
			}

			$value = \dev\ErrorPhpLib::hideSecret('request', (string)$name, $value);

			$parameters[$name] = is_string($value) ? mb_substr($value, 0, self::VALUE_LENGTH) : $value;

		}

		return $parameters;

	}

}
?>
