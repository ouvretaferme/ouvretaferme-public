<?php
namespace session;
/**
 * Session package
 */
class SessionLib {

	use \Notifiable;

	/**
	 * Session content max length
	 *
	 */
	const MAX_LENGTH = 16384;

	/**
	 * Session lifetime
	 *
	 * @var int
	 */
	const LIFETIME = 10800;

	/**
	 * Timeout before session regeneration
	 *
	 * @var int
	 */
	const REGENERATION = 900;

	/**
	 * Valeur de l'attribut `SameSite` des cookies d'authentification.
	 *
	 * `Lax` laisse le cookie partir avec une navigation venue d'un autre site, ce dont le produit a
	 * besoin (les liens des courriels ouvrent des pages authentifiées), et le retient sur les
	 * requêtes de fond, qui sont la matière d'une attaque CSRF.
	 */
	const COOKIE_SAMESITE = 'Lax';

	/**
	 * Les méthodes HTTP qui ne changent rien, et que `checkOrigin()` laisse donc passer.
	 */
	const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

	/**
	 * La valeur de l'en-tête d'un onglet qui n'a pas de session à lui : il suit le cookie du
	 * navigateur, et le dit pour ne pas repasser par le shell.
	 */
	const TAB_COOKIE = 'cookie';

	private static string $content = ''; // Session content
	private static bool $regenerate = FALSE; // Must we regenerate session ?
	private static bool $tab = FALSE;
	private static bool $tabEnabled = FALSE;

	protected static ?\Cache $cache = NULL;
	protected static string $cachePrefix = 'session-';

	/**
	 * Has session been regenerated ?
	 *
	 * @return bool
	 */
	public static function isRegenerated(): bool {
		return self::$regenerate;
	}

	/**
	 * Is a session opened ?
	 *
	 * En CLI il n'y en a jamais et il n'en faut pas : les écritures y sont acceptées.
	 */
	public static function isInitialized(): bool {

		if(\Route::getRequestedWith() === 'cli') {
			return TRUE;
		}

		return isset($_SESSION);

	}

	public static function isTab(): bool {
		return self::$tab;
	}

	/**
	 * Appelé par le `secret.c.php` d'un poste, et par les seuls tests qui éprouvent le mécanisme :
	 * l'identité par onglet ne s'ouvre que sur la machine qui l'a demandée.
	 */
	public static function setTabEnabled(bool $tabEnabled): void {
		self::$tabEnabled = $tabEnabled;
	}

	public static function isTabEnabled(): bool {
		return (LIME_ENV === 'dev' and self::$tabEnabled);
	}

	/**
	 * `NULL` : la requête suit le cookie. `TAB_COOKIE` : elle le suit aussi, envoyée par le shell pour
	 * un onglet sans session à lui. `''` : l'onglet demande une session neuve. Un identifiant mal
	 * formé vaut une demande de session neuve, `session_id()` levant sur un caractère hors de son
	 * alphabet ; un identifiant bien formé mais mort est remplacé par `use_strict_mode`.
	 *
	 * L'en-tête passe avant le champ : seul `Ajax.Query` le pose, alors que le champ peut venir de
	 * n'importe quel formulaire de la page.
	 */
	public static function getTabSid(): ?string {

		if(self::isTabEnabled() === FALSE) {
			return NULL;
		}

		$sid = SERVER('HTTP_X_SESSION_TAB', '?string') ?? self::getTabSidPosted();

		if(
			$sid === NULL or
			$sid === self::TAB_COOKIE
		) {
			return $sid;
		}

		return (preg_match('/^[0-9a-f]+$/', $sid) === 1) ? $sid : '';

	}

	/**
	 * Le champ qu'`Ajax.Tab` ajoute, à l'envoi, à un formulaire posté hors ajax : la navigation qui
	 * suit ne peut pas porter d'en-tête. `$_POST` ne se remplit que sur un `POST`, donc un formulaire
	 * en `GET` ou une adresse qui citerait le champ n'atteignent rien ici.
	 */
	private static function getTabSidPosted(): ?string {

		if(self::isTabEnabled() === FALSE) {
			return NULL;
		}

		return POST('x-session-tab', '?string');

	}

	/**
	 * Init session
	 *
	 */
	public static function init(?string $domain = NULL, ?string $path = NULL): void {

		if(isset($_SESSION) === FALSE) {

			$tabSid = self::getTabSid();
			self::$tab = ($tabSid !== NULL and $tabSid !== self::TAB_COOKIE);

			if($tabSid === NULL and self::needsTabShell()) {
				header('Cache-Control: no-store');
				throw new \DataAction(self::getTabShell());
			}

			session_set_save_handler(new SessionHandler());

			register_shutdown_function('session_write_close');

			if($domain === NULL or $path === NULL) {
				$domain = '.'.\Lime::getDomain();
				$path = '/';

			}

			session_set_cookie_params([
				'lifetime' => 0,
				'path' => $path,
				'domain' => $domain,
				// L'URL configurée du site, et non `$_SERVER['HTTPS']`, que la terminaison TLS d'un
				// proxy laisse vide alors que le visiteur est bien en HTTPS : le cookie repartirait
				// en clair en production
				'secure' => (\Lime::getProtocol() === 'https'),
				'httponly' => TRUE,
				'samesite' => static::COOKIE_SAMESITE,
			]);

			session_name('session'.LIME_ENV);

			\dev\ErrorPhpLib::createExceptionFromError(TRUE);

				if(self::$tab) {

					if($tabSid !== '') {
						session_id($tabSid);
					}

					// Sans `use_cookies` à 0, PHP émettrait le cookie du navigateur avec l'identifiant de
					// l'onglet, et tous les autres onglets passeraient sous ce compte
					session_start(['use_strict_mode' => TRUE, 'use_cookies' => 0]);
					self::sendTabSid();

				} else {
					session_start(['use_strict_mode' => TRUE]);
				}

			\dev\ErrorPhpLib::createExceptionFromError(FALSE);

			// Après l'ouverture, et pas avant : le refus rend la page d'erreur, qui rejoue le
			// constructeur de page et repasse donc ici. `$_SESSION` posé, la garde au-dessus ferme
			// ce second tour ; refuser avant l'ouverture bouclerait jusqu'à épuiser la mémoire
			self::checkOrigin();

		}

		$time = \Clock::machine();

		try {
			self::$regenerate = (self::get('regenerate') <= $time);
		}
		catch(\Exception $e) {
			self::$regenerate = TRUE;
		}

		if(self::$regenerate) {

			self::set('regenerate', $time + self::REGENERATION * 2);
			self::refreshCookie();

		}

	}

	/**
	 * Refuse une écriture dont l'origine n'est pas la page qui l'a servie.
	 *
	 * Le produit n'a ni jeton anti-CSRF ni contrôle d'origine, et sa seule protection est que les
	 * écritures se déclarent en `->post()` et que le cookie de session est en `SameSite=Lax`. Lax
	 * raisonne par site, pas par hôte, et le cookie porte sur `.<domaine>` : un sous-domaine du parc
	 * peut donc poster vers l'application avec le cookie de sa victime, et Lax ne le voit pas.
	 * L'origine, elle, porte l'hôte exact.
	 *
	 * Le contrôle se compare à l'hôte de la requête et non à celui du site : un site vitrine est
	 * servi sur le domaine de sa structure (`ouvretaferme/website/page/public.p.php`), et son
	 * formulaire de contact poste chez lui. `Host` vient du client, comme `Origin`, mais un
	 * navigateur ne laisse choisir ni l'un ni l'autre, et c'est le navigateur de la victime qui fait
	 * une attaque CSRF.
	 *
	 * Trois conditions, et l'ordre des lectures n'a pas d'importance :
	 *
	 * - la méthode écrit. `GET`, `HEAD` et `OPTIONS` sont listées plutôt que les autres, pour qu'une
	 *   méthode inconnue soit contrôlée au lieu d'être oubliée.
	 * - la requête emporte de quoi agir au nom de quelqu'un : un cookie de session, ou, en dev, la
	 *   session d'un onglet (`getTabSid()`). Un webhook (Stripe, Brevo, Mailjet, le retour d'un
	 *   prestataire de paiement) ne porte ni l'un ni l'autre, et traverse donc sans que rien n'ait à
	 *   le déclarer.
	 * - l'origine est présente. Absente, on laisse passer : un navigateur l'envoie toujours sur une
	 *   requête qui écrit, donc l'attaque en porte une, alors qu'un client qui ne l'envoie pas
	 *   n'a de toute façon pas la session d'une victime.
	 *
	 * Sauf pour une requête en session d'onglet dont le corps porte le champ de `getTabSidPosted()`,
	 * même quand l'en-tête l'emporte : un formulaire se poste depuis n'importe quelle page, y compris
	 * d'un autre port du même hôte, et le seul témoin qu'il vient de celle qui détient l'identifiant
	 * est l'origine. Elle est donc exigée, égale au protocole du site suivi de l'hôte de la
	 * requête, port compris.
	 */
	private static function checkOrigin(): void {

		if(in_array(\Route::getRequestMethod(), self::SAFE_METHODS, TRUE)) {
			return;
		}

		$origin = SERVER('HTTP_ORIGIN', '?string');

		if(
			self::$tab and
			self::getTabSidPosted() !== NULL
		) {

			$expected = \Lime::getProtocol().'://'.SERVER('HTTP_HOST');

			if($origin !== $expected) {
				throw new \NotAllowedAction('Origin '.($origin ?? 'missing').' does not match '.$expected.' for a tab session posted in the body');
			}

			return;

		}

		if(
			$origin === NULL or
			(
				cookie_exists(session_name()) === FALSE and
				self::$tab === FALSE
			)
		) {
			return;
		}

		if(parse_url($origin, PHP_URL_HOST) !== parse_url('http://'.SERVER('HTTP_HOST'), PHP_URL_HOST)) {
			throw new \NotAllowedAction('Origin '.$origin.' does not match host '.SERVER('HTTP_HOST'));
		}

	}

	/**
	 * Réécrit le cookie de session avec ses attributs, sans changer sa valeur.
	 *
	 * Le navigateur n'envoie qu'un nom et une valeur : le serveur ne peut pas savoir quels attributs
	 * porte le cookie qu'il reçoit. Une session ouverte avant que ces attributs existent garderait
	 * donc un cookie lisible en JavaScript aussi longtemps qu'elle dure, et une session active se
	 * prolonge sans fin. La réécriture se fait avec le rafraîchissement déjà espacé par
	 * `REGENERATION`, ce qui borne la bascule du parc à une demi-heure pour un en-tête de plus toutes
	 * les demi-heures.
	 *
	 * Elle ne vise que le cookie venu du client : quand il n'en portait pas, PHP vient d'en émettre
	 * un avec ses attributs, et le réécrire enverrait deux fois le même en-tête.
	 */
	private static function refreshCookie(): void {

		if(COOKIE(session_name()) !== session_id()) {
			return;
		}

		$params = session_get_cookie_params();

		setcookie(session_name(), session_id(), [
			'expires' => 0,
			'path' => $params['path'],
			'domain' => $params['domain'],
			'secure' => $params['secure'],
			'httponly' => $params['httponly'],
			'samesite' => $params['samesite'],
		]);

	}

	/**
	 * Donne un nouvel identifiant à la session en cours, en lui gardant son contenu.
	 *
	 * À appeler chaque fois que le niveau de privilège change. Sans cela, un identifiant choisi par un
	 * tiers et posé chez la victime avant qu'elle se connecte devient, à sa connexion, une session
	 * authentifiée que le tiers possède déjà. `session.use_strict_mode` refuse un identifiant inconnu,
	 * pas celui d'une session anonyme que le tiers a ouverte lui-même avant de le poser.
	 *
	 * L'ancien identifiant est détruit plutôt qu'abandonné : abandonné, il resterait valable jusqu'à
	 * `LIFETIME`, et le vol qu'on vient d'annuler redeviendrait bon pendant trois heures.
	 */
	public static function regenerate(): void {

		// Pas de session en CLI : rien à régénérer, et session_regenerate_id() y lèverait. $_SESSION
		// ne le dit pas, une écriture par set() suffit à le faire exister sans session ouverte.
		if(session_status() !== PHP_SESSION_ACTIVE) {
			return;
		}

		session_regenerate_id(TRUE);

		// L'onglet ne reçoit pas de cookie : sans l'en-tête, il rejouerait l'identifiant détruit
		if(self::$tab) {
			self::sendTabSid();
		}

	}

	private static function sendTabSid(): void {
		header('x-session-tab: '.session_id());
	}

	/**
	 * Un chargement complet dans un navigateur dont les onglets ont chacun leur session : la page part
	 * sans en-tête, et rendue telle quelle elle le serait sous le cookie. Le shell relit l'identifiant
	 * de l'onglet et rejoue la même adresse avec lui.
	 *
	 * Seule une navigation le reçoit, page ou cadre : une image ou un script le recevrait à la place de
	 * son contenu, et un lien `download` l'enregistrerait comme fichier. Le navigateur pose
	 * `Upgrade-Insecure-Requests` sur toute navigation, quelle que soit l'origine, alors qu'il n'envoie
	 * `Sec-Fetch-Dest` qu'à une origine sûre, https ou localhost. Un cadre passe par le shell comme
	 * une page parce qu'il partage le `sessionStorage` de son onglet : servi sans lui, il le serait
	 * sous le compte du cookie et non sous celui de l'onglet qui l'affiche.
	 */
	private static function needsTabShell(): bool {

		if(self::isTabEnabled() === FALSE) {
			return FALSE;
		}

		return (
			\Route::getRequestedWith() === 'http' and
			\Route::getRequestMethod() === 'GET' and
			SERVER('HTTP_UPGRADE_INSECURE_REQUESTS') === '1' and
			cookie_exists('tabidentity')
		);

	}

	private static function getTabShell(): string {

		return <<<'HTML'
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title></title></head><body><script>
(async () => {

	const key = 'session-tab';

	const stop = () => {
		const link = document.createElement('a');
		link.href = location.href;
		link.textContent = location.href;
		document.body.replaceChildren(link);
	};

	// Un onglet sans session à lui suit le cookie du navigateur : un nouvel onglet, ou celui qu'on vient de déconnecter
	let sid = null;

	try {
		sid = sessionStorage.getItem(key);
	} catch(e) {
	}

	// fetch() brut : la page n'a encore chargé ni lime.js ni Ajax.Query, et ce n'est qu'un GET
	let response;

	try {
		response = await fetch(location.href, {headers: {'x-session-tab': sid ?? 'cookie'}, cache: 'no-store'});
	} catch(e) {
		stop();
		return;
	}

	if(sid !== null) {

		const next = response.headers.get('x-session-tab');

		// Sans l'en-tête, la réponse n'a pas été vue comme celle d'un onglet : l'écrire relancerait ce shell sans fin
		if(next === null) {
			stop();
			return;
		}

		try {
			sessionStorage.setItem(key, next);
		} catch(e) {
			// Sans stockage, l'onglet suivra le cookie au prochain chargement
		}

	}

	if(response.redirected) {
		history.replaceState(null, '', response.url);
	}

	const type = response.headers.get('content-type') ?? '';
	const disposition = response.headers.get('content-disposition') ?? '';

	if(type.startsWith('text/html') && disposition.startsWith('attachment') === false) {
		const html = await response.text();
		document.open();
		document.write(html);
		document.close();
		return;
	}

	const url = URL.createObjectURL(await response.blob());

	if(disposition.startsWith('attachment')) {
		const link = document.createElement('a');
		link.href = url;
		link.download = decodeURIComponent((disposition.match(/filename\*=UTF-8''([^;]+)/i) ?? disposition.match(/filename="([^"]+)"/) ?? [null, ''])[1]);
		document.body.append(link);
		link.click();
		history.back();
	} else {
		location.replace(url);
	}

})();
</script></body></html>
HTML;

	}

	/**
	 * Oublie une session, de la base comme du cache.
	 *
	 * Le gestionnaire l'appelle à la régénération et à `session_destroy()` ; il ne peut pas descendre
	 * sur `deleteSession()`, qui est protégée.
	 */
	public static function destroy(string $sid): bool {

		self::deleteSession($sid);

		return TRUE;

	}

	/**
	 * Change a value in the session
	 */
	public static function set(string $name, $value): void {

		self::validate();

		$_SESSION['data'][$name] = is_object($value) ? clone $value : $value;

	}

	public static function createSid(): string {

		$strong = TRUE;
		return bin2hex(openssl_random_pseudo_bytes(11, $strong));

	}

	/**
	 * Get a value in the session
	 */
	public static function get(string $name) {

		if(self::exists($name)) {
			$value = $_SESSION['data'][$name];
			return is_object($value) ? clone $value : $value;
		} else {
			throw new \Exception('Cannot find the session key \''.$name.'\'');
		}

	}

	/**
	 * Check if a value exists in the session
	 */
	public static function exists(string $name): bool {

		self::validate();

		if(isset($_SESSION['data'])) {
			return array_key_exists($name, $_SESSION['data']);
		} else {
			return FALSE;
		}

	}

	/**
	 * Unset a value in the session
	 */
	public static function delete(string $name): void {

		self::validate();

		unset($_SESSION['data'][$name]);

	}

	/**
	 * Delete all values in the session
	 *
	 */
	public static function clean(): void {

		self::validate();

		$_SESSION['data'] = [];

	}

	/**
	 * Checks if there is currently a valid session opened
	 */
	protected static function validate(): void {

		if(self::isInitialized() === FALSE) {
			throw new \Exception("No session initialized");
		}

	}

	/**
	 * Create a new session with current content
	 */
	protected static function createSession(string $sid): bool {

		$eSession = new Session([
			'sid' => $sid,
			'content' => self::$content,
		]);

		$affected = Session::model()
			->option('add-ignore')
			->insert($eSession);

		if($affected > 0) {

			self::notify('create', $sid);

			self::getCache()->set(self::$cachePrefix.$sid, self::$content, self::REGENERATION * 2);
			return TRUE;

		} else {
			return FALSE;

		}

	}

	/**
	 * Delete current session but do not remove the current content
	 */
	protected static function deleteSession(string $sid): void {

		$affected = Session::model()
			->whereSid($sid)
			->delete();

		self::getCache()->delete(self::$cachePrefix.$sid);

		if($affected > 0) {
			self::notify('delete', $sid);
		}

	}

	/**
	 * L'identifiant désigne-t-il une session encore vivante ? `session.use_strict_mode` le demande au
	 * gestionnaire avant `read()`, et remplace par un identifiant neuf celui qui ne passe pas.
	 */
	public static function validateSid(string $sid): bool {

		if(Session::model()->check('sid', $sid) === FALSE) {
			return FALSE;
		}

		if(self::getCache()->get(self::$cachePrefix.$sid) !== FALSE) {
			return TRUE;
		}

		return Session::model()
			->where('updatedAt >= NOW() - INTERVAL '.self::LIFETIME.' SECOND')
			->whereSid($sid)
			->exists();

	}

	/**
	 * Read session
	 */
	public static function read(string $sid): string {

		$result = self::getCache()->get(self::$cachePrefix.$sid);

		if($result === FALSE) {

			if(Session::model()->check('sid', $sid) !== FALSE) {

				$eSession = Session::model()
					->select([
						'content',
						'lastUpdate' => new \Sql('UNIX_TIMESTAMP() - UNIX_TIMESTAMP(updatedAt)', 'int'),
					])
					->where('updatedAt >= NOW() - INTERVAL '.self::LIFETIME.' SECOND')
					->whereSid($sid)
					->get();


			} else {
				throw new \Exception('Invalid sid "'.$sid.'"');
			}

			// No existing session
			// Create a new one
			if($eSession->empty()) {

				$sid = session_id();

				self::$content = '';

				if(!self::createSession($sid)) {

					self::$content = Session::model()
						->where('updatedAt >= NOW() - INTERVAL '.self::LIFETIME.' SECOND')
						->where('sid', $sid)
						->getValue('content') ?? '';

				}
			} else {

				self::$content = $eSession['content'];

				self::getCache()->set(self::$cachePrefix.$sid, self::$content, self::REGENERATION * 2);

				if($eSession['lastUpdate'] > self::REGENERATION) {

					Session::model()
						->whereSid($sid)
						->update('updatedAt = NOW()');

				}

			}

		} else {
			self::$content = $result;
		}

		return self::$content;

	}

	/**
	 * Write session
	 */
	public static function write(string $sid, string $content): bool {

		// Don't update session if regeneration date has not been reached and session has not changed
		if(self::$regenerate === FALSE and self::$content === $content) {
			return TRUE;
		}

		$properties = [
			'content' => $content,
			'updatedAt' => new \Sql('NOW()')
		];

		$length = strlen($properties['content']);

		if($length > self::MAX_LENGTH / (LIME_ENV !== 'dev' ? 1 : 2)) {
			throw new \Exception("Session length exhausted (".$length." bytes): ".var_export(array_keys($_SESSION), TRUE));
		}

		$affected = Session::model()
			->whereSid($sid)
			->update($properties);

		if($affected === 0) {
			self::getCache()->delete(self::$cachePrefix.$sid);
		} else {
			self::getCache()->set(self::$cachePrefix.$sid, $content, self::REGENERATION);
		}

		return TRUE;

	}

	/**
	 * Kill a session
	 */
	public static function kill(string $sid): bool {

		if($sid === NULL) {
			return FALSE;
		}

		Session::model()
			->whereSid($sid)
			->delete();

		self::getCache()->delete(self::$cachePrefix.$sid);

		return TRUE;
	}

	/**
	 * Kill all sessions for a given user, except the given one
	 */
	public static function killByUser(\user\User $eUser, ?string $exceptSid = NULL): void {

		$cSession = Session::model()
			->select('sid')
			->union()
			->whereUser($eUser)
			->whereSid('!=', $exceptSid, if: $exceptSid !== NULL)
			->getCollection();

		foreach($cSession as $eSession) {
			self::kill($eSession['sid']);
		}

	}

	/**
	 * Kill all sessions for a given group of users.
	 */
	public static function killByUsers(array $users): void {

		$cSession = Session::model()
			->select('sid')
			->union()
			->whereUser('IN', $users)
			->getCollection();

		foreach($cSession as $eSession) {
			self::kill($eSession['sid']);
		}

	}

	/**
	 * Kill all active sessions
	 */
	public static function killAll(): void {

		Session::model()
			->all()
			->delete();

		self::getCache()->flush();

	}

	public static function getCache(): \Cache {

		if(self::$cache === NULL) {
			self::$cache = new \EmptyCache();
		}

		return self::$cache;

	}

	public static function setCache(\Cache $cache): void {
		self::$cache = $cache;
	}

	public static function setCachePrefix(string $prefix): void {
		self::$cachePrefix = $prefix;
	}

}

class SessionHandler implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {

	public function open(string $path, string $name): bool {
		return TRUE;
	}

	public function close(): bool {
		return TRUE;
	}

	public function read(string $sid): string|false {
		return SessionLib::read($sid);
	}

	public function write(string $sid, string $content): bool {
		return SessionLib::write($sid, $content);
	}

	public function destroy(string $sid): bool {
		return SessionLib::destroy($sid);
	}

	public function gc(int $maxLifeTime): int|false {
		return FALSE;
	}

	public function create_sid(): string {
		return SessionLib::createSid();
	}

	public function validateId(string $sid): bool {
		return SessionLib::validateSid($sid);
	}

	public function updateTimestamp(string $sid, string $content): bool {
		return SessionLib::write($sid, $content);
	}

}

?>
