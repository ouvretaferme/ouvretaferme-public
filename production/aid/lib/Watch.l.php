<?php
namespace aid;

/**
 * La veille de nuit sur les sources du catalogue (aid/cron/watch) : chaque adresse distincte des sources
 * officielles, des relais et des listes des pièces d'une aide non archivée se lit une fois, et ce
 * qu'elle rend tient les colonnes de veille de ses sources (SourceLib::getPropertiesWatch()), jamais
 * l'aide ni l'adresse. Les agrégateurs ne se lisent pas : ils ne décident ni de l'affichage ni de la
 * confiance (cadrage de L5, R17).
 *
 * La nuit se lit en entier avant la première écriture : une nuit où plus de la moitié des adresses ne
 * se lit pas est une panne de la veille, pas de 800 pages, et n'en fait progresser aucune (CL-14).
 */
class WatchLib {

	// Ce qu'une lecture rend d'une adresse.
	const READ = 'read';
	const MOVED = 'moved';
	const FAILED = 'failed';
	const UNREAD = 'unread';

	// Trois nuits d'échec d'affilée passent une source DEAD (charte, Q6 A).
	const FAILURES_DEAD = 3;

	const REDIRECTIONS = 5;
	const TIMEOUT = 20;
	const SIZE = 10 * 1024 * 1024;

	/**
	 * Les plages que FILTER_FLAG_NO_PRIV_RANGE et FILTER_FLAG_NO_RES_RANGE laissent passer et qu'une page
	 * publique n'emploie pas : le partage d'adresses des opérateurs (100.64.0.0/10), les blocs de
	 * l'IETF, de test et d'évaluation, la multidiffusion.
	 */
	const BLOCKS = [
		['100.64.0.0', 10],
		['192.0.0.0', 24],
		['192.0.2.0', 24],
		['198.18.0.0', 15],
		['198.51.100.0', 24],
		['203.0.113.0', 24],
		['224.0.0.0', 4],
	];

	// La nuit du cron : toutes les sources suivies, lues sur le réseau.
	public static function run(): array {
		return self::watch(self::getSources(), self::getRequest());
	}

	// Les sources que la veille lit : officielles, relais et listes des pièces, d'une aide qui n'est pas
	// archivée (R8, R17 ; règle 10 de L6). La nuit les lit toutes, donc la table entière, sans index à prendre.
	public static function getSources(): \Collection {

		return Source::model()
			->select(array_merge(['id', 'url'], SourceLib::getPropertiesWatch()))
			->whereKind('IN', [Source::PRIMARY, Source::RELAY, Source::PIECES])
			->whereAid('IN', new AidModel()->select('id')->whereStatus('!=', Aid::ARCHIVED)->getSql())
			->sort('id')
			->getCollection();

	}

	/**
	 * Lit les sources et écrit ce qui a changé. `$request` lit une adresse sur une IP déjà contrôlée
	 * (getRequest()), et le test en passe une qui ne joint pas le réseau.
	 */
	public static function watch(\Collection $cSource, \Closure $request): array {

		$sourcesByUrl = [];

		foreach($cSource as $eSource) {
			$sourcesByUrl[$eSource['url']][] = $eSource;
		}

		$robots = [];
		$results = [];

		foreach(self::sortByHost(array_keys($sourcesByUrl)) as $url) {
			$results[$url] = self::fetch($url, $request, $robots);
		}

		$notRead = count(array_filter($results, fn(array $result) => in_array($result['result'], [self::FAILED, self::UNREAD], TRUE)));
		$isBroken = ($notRead * 2 > count($results));

		$report = [
			'urls' => count($results),
			'broken' => $isBroken,
			'read' => 0,
			'failed' => 0,
			'unread' => 0,
			'dead' => 0,
			'alive' => 0,
			'moved' => 0,
			'unreadable' => 0,
			'changed' => 0,
		];

		foreach($results as $url => $result) {

			$report[match($result['result']) {
				self::READ, self::MOVED => 'read',
				self::FAILED => 'failed',
				self::UNREAD => 'unread',
			}]++;

			foreach($sourcesByUrl[$url] as $eSource) {
				self::write($eSource, $url, $result, $isBroken, $report);
			}

		}

		return $report;

	}

	/**
	 * Ce que la lecture d'une adresse fait à une source : une écriture des seules colonnes qui changent,
	 * comptée dans le rapport de la nuit.
	 */
	private static function write(Source $eSource, string $url, array $result, bool $isBroken, array &$report): void {

		$today = \Clock::date();
		$now = \Clock::datetime();

		$eSourceBefore = clone $eSource;

		switch($result['result']) {

			case self::READ :
			case self::MOVED :

				$status = match($result['result']) {
					self::MOVED => Source::MOVED,
					self::READ => Source::ALIVE,
				};

				if($eSource['status'] !== $status) {
					$eSource['status'] = $status;
					$eSource['statusAt'] = $now;
				}

				$eSource['movedUrl'] = $result['movedUrl'];
				$eSource['failures'] = 0;
				$eSource['failedAt'] = NULL;
				$eSource['unreadSince'] = NULL;
				$eSource['unreadReason'] = NULL;
				$eSource['checkedAt'] = $now;

				// La première lecture pose l'empreinte sans rien signaler (cadrage de L5, R14).
				if($eSource['fingerprint'] !== NULL and $eSource['fingerprint'] !== $result['fingerprint']) {
					$eSource['changedAt'] = $now;
				}

				$eSource['fingerprint'] = $result['fingerprint'];

				break;

			case self::FAILED :

				// Un échec se compte par nuit, pas par passage (CL-10).
				if($isBroken or $eSource['failedAt'] === $today) {
					return;
				}

				$eSource['failures']++;
				$eSource['failedAt'] = $today;

				if($eSource['failures'] >= self::FAILURES_DEAD and $eSource['status'] !== Source::DEAD) {
					$eSource['status'] = Source::DEAD;
					$eSource['statusAt'] = $now;
					$eSource['movedUrl'] = NULL;
				}

				break;

			case self::UNREAD :

				// Une page qui ne se laisse pas lire ne dit rien d'elle-même : son état ne change pas (R13).
				if($isBroken) {
					return;
				}

				$eSource['unreadSince'] ??= $today;
				$eSource['unreadReason'] = $result['reason'];

				break;

		}

		// La nuit où une source atteint le seuil d'illisible, elle entre dans la file (AidLib::getReviewUnreadableSql()).
		if(
			$result['result'] === self::UNREAD and
			$eSource['unreadSince'] === date('Y-m-d', \Clock::shift('-'.AidSetting::WATCH_UNREAD_DAYS.' days'))
		) {
			$report['unreadable']++;
		}

		$properties = array_values(array_filter(
			SourceLib::getPropertiesWatch(),
			fn(string $property) => $eSource[$property] !== $eSourceBefore[$property]
		));

		if($properties === []) {
			return;
		}

		SourceLib::updateWatch($eSource, $url, $properties);

		if($eSource['status'] !== $eSourceBefore['status']) {

			$report[match($eSource['status']) {
				Source::ALIVE => 'alive',
				Source::MOVED => 'moved',
				Source::DEAD => 'dead',
			}]++;

		}

		if($eSource['changedAt'] !== $eSourceBefore['changedAt']) {
			$report['changed']++;
		}

	}

	/**
	 * Les adresses rangées pour qu'un hôte ne soit jamais lu deux fois de suite tant qu'un autre attend :
	 * un hôte après l'autre, à tour de rôle (cadrage de L5, R15).
	 */
	private static function sortByHost(array $urls): array {

		$urlsByHost = [];

		foreach($urls as $url) {
			$urlsByHost[strtolower((string)parse_url($url, PHP_URL_HOST))][] = $url;
		}

		$sorted = [];

		while($urlsByHost !== []) {

			foreach($urlsByHost as $host => $urlsHost) {

				$sorted[] = array_shift($urlsByHost[$host]);

				if($urlsByHost[$host] === []) {
					unset($urlsByHost[$host]);
				}

			}

		}

		return $sorted;

	}

	/**
	 * La lecture d'une adresse, redirections suivies une à une : chaque saut et le `robots.txt` de son
	 * hôte se résolvent ici, et seule une IP publique contrôlée est jointe, épinglée pour que curl ne
	 * résolve pas une seconde fois (securite.md de L5). Rend `result`, et selon lui `fingerprint`,
	 * `movedUrl` ou `reason`.
	 */
	public static function fetch(string $url, \Closure $request, array &$robots): array {

		$current = $url;

		for($redirection = 0; $redirection <= self::REDIRECTIONS; $redirection++) {

			$target = self::resolve($current);

			if($target['ip'] === NULL) {

				// Un nom de domaine inconnu dit que la page n'existe plus (arbitrage Q2 a de L5), mais
				// seulement sur l'adresse que l'équipe a saisie : une redirection vers un domaine inconnu
				// ou une adresse refusée ne dit rien de la page de départ.
				if($target['unknown'] and $redirection === 0) {
					return ['result' => self::FAILED];
				}

				return self::unread(Source::SERVER);

			}

			if(self::acceptRobots($current, $target, $request, $robots) === FALSE) {
				return self::unread(Source::ROBOTS);
			}

			$response = $request($current, $target['ip']);

			if($response['error'] !== NULL) {
				return self::unread(($response['error'] === CURLE_OPERATION_TIMEDOUT) ? Source::TIMEOUT : Source::SERVER);
			}

			$code = $response['code'];

			if($code >= 300 and $code < 400 and $response['location'] !== NULL) {

				$next = self::getAbsoluteUrl($response['location'], $current);

				if($next === NULL) {
					return self::unread(Source::SERVER);
				}

				// Une page de défi (`/shield?u=…`) se reconnaît à son adresse avant de se lire.
				if(str_starts_with((string)parse_url($next, PHP_URL_PATH), '/shield')) {
					return self::unread(Source::CHALLENGE);
				}

				$current = $next;
				continue;

			}

			if($code === 200) {
				return self::read($url, $current, $response);
			} else if(in_array($code, [404, 410], TRUE)) {
				return ['result' => self::FAILED];
			} else if(in_array($code, [401, 403], TRUE)) {
				return self::unread(Source::FORBIDDEN);
			} else if($code === 429) {
				return self::unread(Source::LIMITED);
			} else {
				return self::unread(Source::SERVER);
			}

		}

		return self::unread(Source::SERVER);

	}

	private static function unread(string $reason): array {
		return ['result' => self::UNREAD, 'reason' => $reason];
	}

	/**
	 * Une page lue en 200, sur l'adresse saisie ou au bout de ses redirections. Une redirection de forme
	 * (`http` vers `https`, `www.`, barre finale) ne change pas de page ; toute autre passe la source
	 * MOVED, l'adresse d'arrivée gardée pour l'équipe, jamais écrite dans `url` (R9, R12).
	 */
	private static function read(string $url, string $current, array $response): array {

		$body = $response['body'];
		$isPdf = (str_starts_with($body, '%PDF') or str_contains((string)$response['contentType'], 'application/pdf'));

		if($isPdf) {
			$fingerprint = hash('sha256', $body);
		} else {

			$text = self::getText($body);

			// Une page sans texte qui ne porte qu'un script est une page de défi, pas la page (CL-22).
			if($text === '' and stripos($body, '<script') !== FALSE) {
				return self::unread(Source::CHALLENGE);
			}

			$fingerprint = hash('sha256', $text);

		}

		$isMoved = (self::getComparableUrl($url) !== self::getComparableUrl($current));

		if($isMoved and Source::model()->check('movedUrl', $current) === FALSE) {
			return self::unread(Source::SERVER);
		}

		return [
			'result' => $isMoved ? self::MOVED : self::READ,
			'fingerprint' => $fingerprint,
			'movedUrl' => $isMoved ? $current : NULL,
		];

	}

	/**
	 * Le texte de la page que l'empreinte suit : celui de `<main>` quand la page en a un, sinon tout le
	 * texte visible. Les scripts, les styles et les champs de formulaire changent d'une lecture à l'autre
	 * sans que la page change (bruit mesuré au cadrage de L5 : 44 % des corps bruts, 2 pages sur 57 pour
	 * `<main>`).
	 */
	private static function getText(string $html): string {

		if(trim($html) === '') {
			return '';
		}

		$document = new \DOMDocument();

		$errors = libxml_use_internal_errors(TRUE);
		$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		foreach(['script', 'style', 'noscript', 'template', 'form'] as $tag) {
			foreach(iterator_to_array($document->getElementsByTagName($tag)) as $node) {
				$node->parentNode->removeChild($node);
			}
		}

		$node = $document->getElementsByTagName('main')->item(0) ?? $document->getElementsByTagName('body')->item(0) ?? $document->documentElement;

		if($node === NULL) {
			return '';
		}

		return trim(preg_replace('/\s+/u', ' ', $node->textContent));

	}

	// Une adresse sans ce qu'une redirection de forme change : le schéma, `www.`, la barre finale.
	public static function getComparableUrl(string $url): string {

		$parts = parse_url($url);

		$host = strtolower($parts['host'] ?? '');

		if(str_starts_with($host, 'www.')) {
			$host = substr($host, 4);
		}

		return $host.rtrim($parts['path'] ?? '', '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

	}

	/**
	 * L'adresse d'un en-tête `Location`, rendue absolue sur celle de la page qui redirige, ou NULL quand
	 * elle ne mène pas à une page web : un `javascript:` ou un `file:` ne se suit ni ne s'écrit.
	 */
	public static function getAbsoluteUrl(string $location, string $base): ?string {

		$location = trim($location);

		if($location === '' or preg_match('/[\x00-\x1F\x7F]/', $location)) {
			return NULL;
		}

		$baseParts = parse_url($base);

		if(str_starts_with($location, '//')) {
			$location = $baseParts['scheme'].':'.$location;
		} else if(preg_match('/^[a-z][a-z0-9+.\-]*:/i', $location) === 0) {

			$origin = $baseParts['scheme'].'://'.$baseParts['host'].(isset($baseParts['port']) ? ':'.$baseParts['port'] : '');

			if(str_starts_with($location, '/')) {
				$location = $origin.$location;
			} else {
				$path = $baseParts['path'] ?? '/';
				$location = $origin.substr($path, 0, strrpos($path, '/') + 1).$location;
			}

		}

		$scheme = strtolower((string)parse_url($location, PHP_URL_SCHEME));

		return in_array($scheme, ['http', 'https'], TRUE) ? $location : NULL;

	}

	/**
	 * L'IP publique où joindre une adresse, ou NULL avec `unknown` vrai quand son nom ne se résout pas.
	 * Le contrôle porte sur l'IP résolue, jamais sur le nom : `http://2130706433/` ou `http://0/` sont des
	 * adresses valides qui mènent à la machine elle-même. IPv4 seulement, curl étant tenu à IPv4 par
	 * request() : une IPv6 qu'on ne contrôle pas ne se joint jamais.
	 */
	public static function resolve(string $url): array {

		$refused = ['ip' => NULL, 'unknown' => FALSE];

		$parts = parse_url($url);

		if(
			$parts === FALSE or
			in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], TRUE) === FALSE or
			($parts['host'] ?? '') === '' or
			isset($parts['user']) or
			isset($parts['pass']) or
			(isset($parts['port']) and in_array($parts['port'], [80, 443], TRUE) === FALSE)
		) {
			return $refused;
		}

		$host = $parts['host'];

		if(str_starts_with($host, '[')) {
			return $refused;
		}

		$ips = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? [$host] : gethostbynamel($host);

		if($ips === FALSE or $ips === []) {
			return ['ip' => NULL, 'unknown' => TRUE];
		}

		foreach($ips as $ip) {
			if(self::isPublic($ip) === FALSE) {
				return $refused;
			}
		}

		return ['ip' => $ips[0], 'unknown' => FALSE];

	}

	public static function isPublic(string $ip): bool {

		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === FALSE) {
			return FALSE;
		}

		$long = ip2long($ip);

		foreach(self::BLOCKS as [$network, $bits]) {

			$mask = -1 << (32 - $bits);

			if(($long & $mask) === (ip2long($network) & $mask)) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Le `robots.txt` d'un hôte se lit une fois par nuit, dans la variable de la nuit : aucune propriété
	 * statique ne le garde d'une lecture à l'autre. Un fichier absent ou illisible n'interdit rien.
	 */
	private static function acceptRobots(string $url, array $target, \Closure $request, array &$robots): bool {

		$parts = parse_url($url);
		$origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');

		if(array_key_exists($origin, $robots) === FALSE) {
			$robots[$origin] = self::getRobots($origin.'/robots.txt', $request);
		}

		$path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

		return self::acceptPath($robots[$origin], $path);

	}

	/**
	 * Les règles d'un `robots.txt` qui visent la veille : le groupe qui la nomme, sinon le groupe `*`.
	 * Ses redirections se suivent et se contrôlent comme celles d'une page.
	 */
	private static function getRobots(string $url, \Closure $request): array {

		$current = $url;

		for($redirection = 0; $redirection <= self::REDIRECTIONS; $redirection++) {

			$target = self::resolve($current);

			if($target['ip'] === NULL) {
				return [];
			}

			$response = $request($current, $target['ip']);

			if($response['error'] !== NULL) {
				return [];
			}

			if($response['code'] >= 300 and $response['code'] < 400 and $response['location'] !== NULL) {

				$current = self::getAbsoluteUrl($response['location'], $current);

				if($current === NULL) {
					return [];
				}

				continue;

			}

			return ($response['code'] === 200) ? self::parseRobots($response['body']) : [];

		}

		return [];

	}

	public static function parseRobots(string $content): array {

		$groups = [];
		$agents = [];
		$isReadingAgents = FALSE;

		foreach(preg_split('/\r\n|\r|\n/', $content) as $line) {

			$line = trim(preg_replace('/#.*$/', '', $line));

			if(preg_match('/^([a-z-]+)\s*:\s*(.*)$/i', $line, $match) === 0) {
				continue;
			}

			$field = strtolower($match[1]);
			$value = trim($match[2]);

			if($field === 'user-agent') {

				if($isReadingAgents === FALSE) {
					$agents = [];
				}

				$agents[] = strtolower($value);
				$isReadingAgents = TRUE;

			} else if(in_array($field, ['allow', 'disallow'], TRUE)) {

				$isReadingAgents = FALSE;

				if($value === '') {
					continue;
				}

				foreach($agents as $agent) {
					$groups[$agent][] = [$field === 'allow', $value];
				}

			}

		}

		return $groups['ouvretaferme-veille'] ?? $groups['*'] ?? [];

	}

	// La règle la plus longue qui couvre le chemin l'emporte, Allow à longueur égale.
	public static function acceptPath(array $rules, string $path): bool {

		$isAllowed = TRUE;
		$length = -1;

		foreach($rules as [$allow, $pattern]) {

			$regex = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')).'#';

			// Un motif que PCRE abandonne (FALSE, retour arrière épuisé sur un robots.txt écrit pour) ne
			// couvre rien : il ne peut ni ouvrir ni fermer un chemin.
			if(preg_match($regex, $path) !== 1) {
				continue;
			}

			if(strlen($pattern) > $length or (strlen($pattern) === $length and $allow)) {
				$isAllowed = $allow;
				$length = strlen($pattern);
			}

		}

		return $isAllowed;

	}

	/**
	 * La lecture d'une adresse par le réseau, sur l'IP que resolve() a contrôlée : par CurlLib, que
	 * CurlLib::setLocked() ferme dans un processus qui ne doit rien joindre. Une page d'un hôte qui vient
	 * d'être lu attend une seconde, l'ordre de sortByHost() ne les rapprochant que pour le dernier hôte.
	 */
	private static function getRequest(): \Closure {

		$hostLast = NULL;

		return function(string $url, string $ip) use(&$hostLast): array {

			$parts = parse_url($url);
			$host = strtolower($parts['host']);
			$port = $parts['port'] ?? ((strtolower($parts['scheme']) === 'https') ? 443 : 80);

			if($host === $hostLast) {
				sleep(1);
			}

			$hostLast = $host;

			$curl = new \util\CurlLib();
			$curl->setUrlAsIs(TRUE);

			try {

				$body = $curl->exec($url, [], 'GET', [
					CURLOPT_FOLLOWLOCATION => FALSE,
					CURLOPT_RESOLVE => [$host.':'.$port.':'.$ip],
					CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
					CURLOPT_PROXY => '',
					CURLOPT_PROTOCOLS_STR => 'http,https',
					CURLOPT_TIMEOUT => self::TIMEOUT,
					CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
					CURLOPT_MAXFILESIZE => self::SIZE,
					CURLOPT_REFERER => NULL,
					CURLOPT_USERAGENT => AidSetting::WATCH_USER_AGENT,
				]);

			} catch(\Exception $e) {
				return ['error' => $e->getCode(), 'code' => 0, 'body' => '', 'contentType' => NULL, 'location' => NULL];
			}

			$infos = $curl->getLastInfos();

			// L'IP jointe doit être celle que resolve() a contrôlée : une entrée de CURLOPT_RESOLVE que curl
			// n'appliquerait pas à l'hôte qu'il analyse le laisserait résoudre de lui-même.
			if($infos['primaryIp'] !== $ip) {
				return ['error' => CURLE_COULDNT_CONNECT, 'code' => 0, 'body' => '', 'contentType' => NULL, 'location' => NULL];
			}

			return [
				'error' => NULL,
				'code' => $infos['httpCode'],
				'body' => (string)$body,
				'contentType' => $infos['contentType'] ?: NULL,
				'location' => $infos['redirectUrl'] ?: NULL,
			];

		};

	}

}
?>
