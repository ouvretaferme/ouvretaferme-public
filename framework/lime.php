<?php
declare(strict_types = 1);

// Current time
define("LIME_TIME", hrtime(TRUE));

// Configuration
mb_internal_encoding('UTF-8');
ignore_user_abort(TRUE);

// Path to Lime
define('LIME_DIRECTORY', realpath( __DIR__.'/..'));

/**
 * Lime main configuration class
 *
 */
class Lime {

	/*
	 * Site URL (protocol + host + port)
	 */
	private static ?string $url = NULL;

	/*
	 * Site host
	 */
	private static ?string $host = NULL;

	/*
	 * Site domain
	 */
	private static ?string $domain = NULL;

	/*
	 * Site protocol
	 */
	private static ?string $protocol = NULL;

	/*
	 * Site port
	 */
	private static ?int $port = NULL;

	/*
	 * Site name
	 */
	private static ?string $name = NULL;

	/**
	 * Registered apps
	 *
	 * @var array
	 */
	private static array $apps = [];

	/**
	 * Sibling apps for internal builds
	 *
	 * @var array
	 */
	private static array $siblings = [];

	/**
	 * Remote key value
	 *
	 * @var array
	 */
	private static array $remoteKey = [];

	/**
	 * Validity of a remote signature, in seconds
	 *
	 * @var int
	 */
	const REMOTE_LIFETIME = 300;

	/**
	 * Define main app
	 *
	 * @param string $app
	 */
	public static function init(string $app): void {

		if(ctype_alpha($app) === FALSE) {
			throw new Exception("App name must only contain alphabetic characters");
		} else {
			define('LIME_APP', $app);
		}

	}

	/**
	 * Get the path for the given app
	 *
	 * @param string $app
	 */
	public static function getPath(string $app = LIME_APP): string {
		return LIME_DIRECTORY.'/'.$app;
	}


	/**
	 * Set apps
	 *
	 * @param array $apps
	 */
	public static function setApps(array $apps): void {
		self::$apps = $apps;
	}

	/**
	 * Get apps
	 *
	 * @return array
	 */
	public static function getApps(): array {
		return self::$apps;
	}

	/**
	 * Set siblings
	 *
	 * @param array $siblings
	 */
	public static function setSiblings(array $siblings): void {
		self::$siblings = $siblings;
	}

	/**
	 * Set remoteKey
	 *
	 * @param string $name
	 * @param ?string $value
	 */
	public static function setRemoteKey(string $name, ?string $value): void {
		self::$remoteKey[$name] = $value;
	}

	/**
	 * Get siblings
	 *
	 * @return array
	 */
	public static function getSiblings(): array {
		return self::$siblings;
	}

	/**
	 * Get remoteKey
	 *
	 * @return ?string
	 */
	public static function getRemoteKey(string $name): ?string {
		return self::$remoteKey[$name] ?? NULL;
	}

	/**
	 * Sign a remote URL, so that the key never travels and only opens that exact document.
	 *
	 * La clé d'un paquet vaut pour tout le parc et voyage en clair dans la chaîne de requête, donc
	 * dans les journaux d'accès du serveur : ce qu'elle ouvre, elle l'ouvre à qui l'a lue, sur
	 * n'importe quel identifiant. La signature lie l'autorisation aux paramètres demandés et à
	 * l'instant, et la clé reste sur le serveur.
	 *
	 * @param string $name Name of the key
	 * @param string $url Absolute or relative URL, without any remoteKey argument
	 * @return string The same URL, with its signature appended
	 */
	public static function signRemote(string $name, string $url): string {

		$key = self::getRemoteKey($name);

		if($key === NULL or $key === '') {
			throw new Exception('Missing remote key \''.$name.'\'');
		}

		$get = [];
		parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $get);

		$time = \Clock::machine();

		$signature = hash_hmac('sha256', self::getRemotePayload($name, parse_url($url, PHP_URL_PATH) ?? '', $get, $time), $key);

		return $url.(str_contains($url, '?') ? '&' : '?').'remoteKey='.$time.'-'.$signature;

	}

	/**
	 * Does the signature carried by the request open this exact path and these exact arguments ?
	 *
	 * @param string $name Name of the key
	 * @param string $path Requested path, without its query string
	 * @param array $get Requested arguments, signature included
	 */
	public static function verifyRemote(string $name, string $path, array $get): bool {

		$key = self::getRemoteKey($name);

		// Une clé absente ouvrait la route : NULL !== NULL est faux, et une requête sans argument
		// remoteKey passait donc le contrôle sur toute installation qui n'avait pas posé la clé.
		if($key === NULL or $key === '') {
			return FALSE;
		}

		$remoteKey = $get['remoteKey'] ?? NULL;

		if(is_string($remoteKey) === FALSE) {
			return FALSE;
		}

		$position = strpos($remoteKey, '-');

		if($position === FALSE) {
			return FALSE;
		}

		$time = substr($remoteKey, 0, $position);
		$signature = substr($remoteKey, $position + 1);

		if(ctype_digit($time) === FALSE) {
			return FALSE;
		}

		if(abs(\Clock::machine() - (int)$time) > self::REMOTE_LIFETIME) {
			return FALSE;
		}

		return hash_equals(hash_hmac('sha256', self::getRemotePayload($name, $path, $get, (int)$time), $key), $signature);

	}

	/**
	 * What gets signed, built the same way on both sides.
	 *
	 * Les arguments sont triés et réassemblés plutôt que repris tels quels : celui qui signe part
	 * d'une chaîne d'URL et celui qui vérifie part de $_GET, et rien ne garantit que les deux
	 * portent leurs clés dans le même ordre.
	 */
	private static function getRemotePayload(string $name, string $path, array $get, int $time): string {

		unset($get['remoteKey']);
		ksort($get);

		return $name."\n".$path."\n".http_build_query($get)."\n".$time;

	}

	/**
	 * Set urls for each mode (dev, prod...) and select the right URL according to the current mode
	 *
	 * @param array $urls
	 */
	public static function setUrls(array $urls): void {

		if(isset($urls[LIME_ENV]) === FALSE) {
			throw new Exception("No URL for mode '".LIME_ENV."'");
		}

		self::$url = $urls[LIME_ENV];

		[
			'host' => self::$host,
			'domain' => self::$domain,
			'protocol' => self::$protocol,
			'port' => self::$port,
		] = analyze_url(self::$url);

	}

	/**
	 * Set site name
	 *
	 * @return array
	 */
	public static function setName(string $name): void {
		self::$name = $name;
	}

	/**
	 * Get site name
	 *
	 * @return array
	 */
	public static function getName(): ?string {
		return self::$name;
	}

	/**
	 * Get url
	 *
	 * @return array
	 */
	public static function getUrl(): ?string {
		return self::$url;
	}

	/**
	 * Get host from url
	 *
	 * @return array
	 */
	public static function getHost(): ?string {
		return self::$host;
	}

	/**
	 * Get domain from url
	 *
	 * @return array
	 */
	public static function getDomain(): ?string {
		return self::$domain;
	}

	/**
	 * Get protocol from url
	 *
	 * @return array
	 */
	public static function getProtocol(): ?string {
		return self::$protocol;
	}

	/**
	 * Get port from url
	 *
	 * @return array
	 */
	public static function getPort(): ?int {
		return self::$port;
	}

	/**
	 * Tells whether a URL stays on the site: an absolute path, or an absolute URL on the site host or one of its subdomains
	 */
	public static function isSameSite(string $url): bool {

		// Browsers strip tabs and newlines anywhere in a URL, so "/\t/host" is read as "//host"
		if(preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
			return FALSE;
		}

		if(str_starts_with($url, '/')) {
			// Browsers read "//host" and "/\host" as scheme-relative URLs, hence as another host
			return (preg_match('/^\/[\/\\\\]/', $url) === 0);
		}

		if(preg_match('/^https?:\/\/([a-z0-9\.\-]+)(:[0-9]+)?(?=[\/?#]|$)/i', $url, $match) === 0) {
			return FALSE;
		}

		$host = strtolower($match[1]);

		return (
			$host === self::$host or
			str_ends_with($host, '.'.self::$domain)
		);

	}

	/**
	 * Returns a URL requested by the client if it stays on the site, $default otherwise
	 */
	public static function getRedirect(?string $url, string $default = '/'): string {

		if($url === NULL or $url === '' or self::isSameSite($url) === FALSE) {
			return $default;
		}

		return $url;

	}

}

require_once LIME_DIRECTORY.'/framework/core/Function.php';
require_once LIME_DIRECTORY.'/framework/core/Package.php';
require_once LIME_DIRECTORY.'/framework/core/Route.php';
require_once LIME_DIRECTORY.'/framework/core/Page.php';
require_once LIME_DIRECTORY.'/framework/core/Action.php';
require_once LIME_DIRECTORY.'/framework/core/Instruction.php';
require_once LIME_DIRECTORY.'/framework/core/Template.php';
require_once LIME_DIRECTORY.'/framework/core/View.php';
require_once LIME_DIRECTORY.'/framework/core/Module.php';
require_once LIME_DIRECTORY.'/framework/core/Data.php';

if(server_exists('SERVER_NAME')) {
	new HttpRoute()->run();
} else {
	new CliRoute()->run();
}
?>
