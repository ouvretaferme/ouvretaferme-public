<?php
namespace user;

class TabLib {

	const COOKIE = 'tabidentity';

	const SESSION = 'tabToken';

	/**
	 * Le temps qui sépare le geste de l'administrateur de la requête que son onglet envoie aussitôt :
	 * le jeton ne sert qu'à ce passage, et un jeton qui traîne est un compte à prendre.
	 */
	const LOGIN_TOKEN_TIMEOUT = 30;

	/** Le préfixe de la clé d'un jeton nomme ce qu'il connecte : un onglet, ou la session du navigateur. */
	const TOKEN_TAB = 'tab-login-';

	const TOKEN_BROWSER = 'tab-browser-';

	/**
	 * Un onglet fermé sans se déconnecter laisse son jeton, que rien ne peut retirer ensuite : le
	 * serveur ne voit pas un onglet se fermer. Le cookie se réécrit en entier à chaque connexion, et
	 * une réponse dont l'en-tête dépasse ce que le serveur web accepte ne part pas du tout, donc plus
	 * aucun onglet ne se connecte. Il ne garde que les derniers jetons posés. Un onglet dont le jeton
	 * est sorti reste connecté, et ne perdrait le shell que si tous les onglets plus récents se
	 * déconnectaient avant lui.
	 */
	const TOKENS_MAX = 20;

	/**
	 * Le compte du poste, que `/user/tab:browser` rend au navigateur qu'un agent a déconnecté. Il
	 * change d'un poste à l'autre, donc seul le `secret.c.php` du poste le déclare, par
	 * `setWorkstationUser()`.
	 */
	private static User $eUserWorkstation;

	public static function setWorkstationUser(User $eUser): void {
		self::$eUserWorkstation = $eUser;
	}

	public static function getWorkstationUser(): User {
		return self::$eUserWorkstation ?? new User();
	}

	public static function getByEmails(array $emails): \Collection {

		return User::model()
			->select(User::getSelection() + [
				'cMember' => \structure\Member::model()
					->select([
						'id',
						'structure' => ['name'],
						'role' => ['name']
					])
					->whereStatus(\structure\Member::IN)
					->delegateCollection('user')
			])
			->whereEmail('IN', $emails)
			->sort(['id' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

	/**
	 * Le choix de l'administrateur, fait sous le cookie du navigateur, passe à l'onglet qui part en
	 * session neuve et sans compte, donc sans les droits qui autorisent le geste. Le jeton est cette
	 * preuve : seule une action de l'admin l'émet, et il ne connecte qu'une fois.
	 */
	public static function createLoginToken(User $eUser): string {
		return self::createToken(self::TOKEN_TAB, $eUser);
	}

	/**
	 * Le jeton qui rend le compte du poste à la session du navigateur, celle du cookie que partagent
	 * les onglets sans compte à eux. Seule la commande `user/tab:browserToken` l'émet : qui lance une
	 * commande sur le poste écrit déjà toute la base de dev, et n'y gagne aucun pouvoir.
	 *
	 * Son préfixe le sépare du jeton d'un onglet : un compte du décor pris par un agent sur
	 * `/user/admin/agent` ne doit jamais devenir celui de tout le navigateur.
	 */
	public static function createBrowserToken(User $eUser): string {
		return self::createToken(self::TOKEN_BROWSER, $eUser);
	}

	private static function createToken(string $prefix, User $eUser): string {

		$eUser->expects(['id']);

		$token = bin2hex(random_bytes(16));

		\Cache::redis()->set($prefix.$token, $eUser['id'], self::LOGIN_TOKEN_TIMEOUT);

		return $token;

	}

	public static function logInByToken(string $token): bool {

		$eUser = self::consumeToken(self::TOKEN_TAB, $token);

		if($eUser->empty()) {
			User::fail('tabToken');
			return FALSE;
		}

		return self::logIn($eUser);

	}

	/**
	 * Connecte la session du navigateur, pas celle d'un onglet : le cookie des onglets n'est pas
	 * touché, et chaque onglet qui a son compte le garde.
	 */
	public static function logInBrowserByToken(string $token): bool {

		$eUser = self::consumeToken(self::TOKEN_BROWSER, $token);

		if($eUser->empty()) {
			User::fail('browserToken');
			return FALSE;
		}

		return ConnectionLib::logInUser($eUser);

	}

	/**
	 * Deux requêtes qui présentent le même jeton le lisent toutes les deux, mais une seule le
	 * supprime : `delete()` ne rend vrai qu'à celle-là, et l'autre repart sans compte.
	 */
	private static function consumeToken(string $prefix, string $token): User {

		if(preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
			return new User();
		}

		$key = $prefix.$token;
		$userId = \Cache::redis()->get($key);

		if(
			$userId === FALSE or
			\Cache::redis()->delete($key) === FALSE
		) {
			return new User();
		}

		return UserLib::getById($userId);

	}

	/**
	 * Connecte l'onglet et ajoute son jeton au cookie, qui marque le navigateur tant qu'un de ses
	 * onglets au moins est connecté. Sans secret, et de la durée de la session du navigateur comme le
	 * `sessionStorage` des onglets qu'il signale, il dit seulement au serveur qu'un chargement complet
	 * doit d'abord relire la session de l'onglet (`SessionLib::needsTabShell()`).
	 *
	 * Le jeton se lit avant `logInUser()`, qui déconnecte le compte précédent de l'onglet et vide sa
	 * session : un onglet qui change de compte garde le sien au lieu d'en laisser un orphelin.
	 * Angle mort accepté en dev : deux requêtes d'onglets en vol en même temps lisent le même cookie
	 * avant que l'une ne l'ait réécrit, et la réponse arrivée la dernière efface le jeton de l'autre.
	 */
	public static function logIn(User $eUser): bool {

		$token = self::getSessionToken();

		if(ConnectionLib::logInUser($eUser) === FALSE) {
			return FALSE;
		}

		if($token === '') {
			$token = bin2hex(random_bytes(8));
		}

		\session\SessionLib::set(self::SESSION, $token);

		// Le jeton de l'onglet qui se connecte repasse en dernier : il est le plus récent, y compris quand il change de compte
		$tokens = [...array_diff(self::getTokens(), [$token]), $token];

		self::write(array_slice($tokens, -self::TOKENS_MAX));

		return TRUE;

	}

	/**
	 * Déconnecte l'onglet et retire son jeton du cookie : le dernier onglet connecté qui se déconnecte
	 * l'efface, et le navigateur ne passe plus par le shell. Le jeton se retire avant
	 * `ConnectionLib::logOut()`, qui vide la session où l'onglet le garde. La déconnexion ordinaire
	 * (`/user/log:out`) ne passe pas par ici : son jeton reste, et le cookie avec lui jusqu'à la
	 * fermeture du navigateur.
	 */
	public static function logOut(User $eUserOnline): void {

		$token = self::getSessionToken();

		if($token !== '') {
			\session\SessionLib::delete(self::SESSION);
			self::write(array_values(array_diff(self::getTokens(), [$token])));
		}

		if($eUserOnline->notEmpty()) {
			ConnectionLib::logOut($eUserOnline);
		}

	}

	private static function getSessionToken(): string {

		if(\session\SessionLib::exists(self::SESSION)) {
			return \session\SessionLib::get(self::SESSION);
		} else {
			return '';
		}

	}

	/**
	 * Une valeur d'une autre forme ne retient aucun onglet : elle vient d'une main, ou d'un navigateur
	 * marqué avant les jetons, que nettoie la prochaine écriture du cookie par un onglet qui se
	 * connecte, ou qui se déconnecte avec un jeton.
	 */
	private static function getTokens(): array {

		return array_values(array_unique(array_filter(
			explode('.', COOKIE(self::COOKIE, 'string', '')),
			fn(string $token) => preg_match('/^[0-9a-f]{16}\z/', $token) === 1
		)));

	}

	/**
	 * `$_COOKIE` suit le cookie dans la même requête, CLI comprise, où il n'y a pas de réponse HTTP à
	 * qui le donner et où `setcookie()` avertirait après la première sortie (même motif que
	 * `ChallengeAiLib::trust()`).
	 */
	private static function write(array $tokens): void {

		$value = implode('.', $tokens);

		if(\Route::getRequestedWith() !== 'cli') {

			// Mêmes attributs qu'à la pose : un cookie que le navigateur ne reconnaît pas n'est pas remplacé
			setcookie(self::COOKIE, $value, [
				'expires' => ($value === '') ? \Clock::machine() - 3600 * 24 : 0,
				'path' => '/',
				'domain' => '.'.\Lime::getDomain(),
				'secure' => (\Lime::getProtocol() === 'https'),
				'httponly' => TRUE,
				'samesite' => \session\SessionLib::COOKIE_SAMESITE,
			]);

		}

		if($value === '') {
			unset($_COOKIE[self::COOKIE]);
		} else {
			$_COOKIE[self::COOKIE] = $value;
		}

	}

}
?>
