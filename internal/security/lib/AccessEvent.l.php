<?php
namespace security;

/**
 * Les événements du journal d'accès Nginx : ce que le rapport GoAccess montre à qui le lit, relevé
 * toutes les dix minutes par le cron et écrit en base pour que personne n'ait à le lire.
 *
 * Trois événements, sur une heure : un balayage (`SCAN`), une adresse qui reçoit `SCAN_THRESHOLD`
 * réponses 4xx dans l'heure, la limitation mise à part ; une rafale (`FLOOD`), une adresse que la
 * limitation Nginx refuse `FLOOD_THRESHOLD` fois dans l'heure (429) ; une route en erreur (`ERROR`),
 * un hôte et un chemin qui répondent `ERROR_THRESHOLD` fois en 5xx dans l'heure. Les seuils sont
 * calés sur deux journées de production (04/06 et 23/09/2026) : en dessous, c'est le bruit d'une
 * journée ordinaire, 366 hôtes et 80 000 lignes.
 *
 * Le cron lit le journal depuis son dernier passage, curseur dans un fichier JSON à côté du rapport
 * (`CURSOR`), qui porte l'inode et la position du journal courant, et les seaux horaires en cours :
 * un événement se compte sur une heure, un passage n'en voit que dix minutes. À la rotation de
 * minuit, l'inode change : le passage finit `access.log.1` depuis la position gardée, puis lit
 * `access.log` depuis le début.
 *
 * Un événement s'écrit dès que son seau franchit le seuil, et se met à jour aux passages suivants
 * jusqu'à la fin de l'heure. Discord est prévenu une fois, sur le salon de sécurité, pour une rafale
 * dès qu'elle est écrite et pour un balayage à partir de `NOTIFY_SCAN` réponses qui ont atteint PHP ;
 * jamais pour une route en erreur, que `dev\Error` remonte déjà quand c'est le produit qui échoue.
 *
 * Un balayage ordinaire ne demande rien : une liste fixe de quelques centaines de chemins, 550
 * requêtes dans l'heure, qu'aucun motif ne fera taire. L'alerte ne sert qu'à celui qui charge PHP,
 * d'où `NOTIFY_SCAN`, calé sur la copie de production du 23/09 au 03/10/2026 : 40 balayages de 500
 * réponses ou plus, un seul de plus de 2 000 qui ait atteint PHP (9 786 sur un domaine de boutique).
 *
 * Nginx répond seul à deux réponses 4xx, qui comptent pour le seuil et l'écran mais pas pour
 * l'alerte : le 444 d'un chemin que `framework/dev/conf/rewrite/scan.cfg` refuse, ou d'un hôte
 * inconnu que le serveur par défaut refuse, et le 400 d'une requête illisible.
 *
 * Le journal n'a que des adresses IPv4 (aucune IPv6 sur le journal du 23/09/2026) : une ligne qui
 * n'en porte pas est ignorée.
 */
class AccessEventLib extends AccessEventCrud {

	const CURSOR = 'events.json';

	const SCAN_THRESHOLD = 100;
	const FLOOD_THRESHOLD = 100;
	const ERROR_THRESHOLD = 10;
	const NOTIFY_SCAN = 2000;

	const KEEP_DAYS = 30;

	const HOST_LENGTH = 100;
	const PATH_LENGTH = 255;

	const MONTHS = ['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12];

	public static function isConfigured(): bool {
		return AccessReportLib::isConfigured();
	}

	public static function getCursorFile(): string {
		return rtrim(SecuritySetting::$accessDirectory, '/').'/'.self::CURSOR;
	}

	/**
	 * Le passage du cron : lit ce que le journal a reçu depuis le passage précédent, alimente les
	 * seaux horaires et écrit les événements qui franchissent un seuil. Rend le nombre de lignes
	 * lues, ou NULL quand rien n'est configuré.
	 */
	public static function collect(): ?int {

		if(self::isConfigured() === FALSE) {
			return NULL;
		}

		$cursor = self::readCursor();
		$buckets = $cursor['buckets'];
		$touched = [];
		$lines = 0;
		$latestHour = $cursor['latestHour'];

		foreach(self::getSegments($cursor) as [$file, $offset]) {

			$offset = self::readLines($file, $offset, function(string $line) use(&$buckets, &$touched, &$lines, &$latestHour) {

				$lines++;

				$hit = self::parse($line);

				if($hit === NULL) {
					return;
				}

				if($latestHour === NULL or $hit['hour'] > $latestHour) {
					$latestHour = $hit['hour'];
				}

				$key = self::accumulate($buckets, $hit);

				if($key !== NULL) {
					$touched[$key] = TRUE;
				}

			});

			if($file === SecuritySetting::$accessLog) {
				$cursor['inode'] = fileinode($file);
				$cursor['offset'] = $offset;
			}

		}

		self::flush($buckets, array_keys($touched));

		if($latestHour !== NULL) {
			self::prune($buckets, $latestHour);
		}

		$cursor['buckets'] = $buckets;
		$cursor['latestHour'] = $latestHour;

		self::writeCursor($cursor);

		return $lines;

	}

	public static function readCursor(): array {

		$cursor = [
			'inode' => NULL,
			'offset' => 0,
			'latestHour' => NULL,
			'buckets' => [],
		];

		$file = self::getCursorFile();

		if(is_file($file)) {

			$saved = json_decode(file_get_contents($file), TRUE);

			if(is_array($saved)) {
				$cursor = $saved + $cursor;
			}

		}

		// Le fichier survit aux mises en production : un seau ouvert par une version qui ne comptait pas
		// les refus n'en porte pas, et accumulate() lèverait sur lui à chaque passage, curseur jamais
		// réécrit. Zéro refus le juge comme le faisait cette version.
		foreach($cursor['buckets'] as $key => $bucket) {
			$cursor['buckets'][$key] = $bucket + ['refused' => 0];
		}

		return $cursor;

	}

	public static function writeCursor(array $cursor): void {

		$file = self::getCursorFile();

		// Écrit à côté puis renommé d'un geste : un passage interrompu ne laisse pas un curseur à moitié
		// écrit, que le passage suivant ne saurait pas lire et qui lui ferait relire tout le journal
		file_put_contents($file.'.new', json_encode($cursor));
		rename($file.'.new', $file);

	}

	/**
	 * Les morceaux de journal à lire, dans l'ordre : le fichier et la position de départ.
	 */
	public static function getSegments(array $cursor): array {

		$log = SecuritySetting::$accessLog;
		$logRotated = SecuritySetting::$accessLogRotated;

		clearstatcache();

		if(is_file($log) === FALSE or is_readable($log) === FALSE) {
			return [];
		}

		if($cursor['inode'] === NULL) {
			return [[$log, 0]];
		}

		if($cursor['inode'] === fileinode($log)) {

			if(filesize($log) < $cursor['offset']) {
				return [[$log, 0]];
			}

			return [[$log, $cursor['offset']]];

		}

		if(
			is_file($logRotated) and
			is_readable($logRotated) and
			$cursor['inode'] === fileinode($logRotated) and
			filesize($logRotated) >= $cursor['offset']
		) {
			return [[$logRotated, $cursor['offset']], [$log, 0]];
		}

		return [[$log, 0]];

	}

	/**
	 * Lit les lignes complètes du fichier à partir de la position, et rend la position atteinte. Une
	 * dernière ligne sans saut de ligne est en cours d'écriture par Nginx : elle n'est pas consommée,
	 * le passage suivant la lira entière.
	 */
	public static function readLines(string $file, int $offset, \Closure $callback): int {

		$handle = fopen($file, 'r');

		fseek($handle, $offset);

		while(($line = fgets($handle)) !== FALSE) {

			if(str_ends_with($line, "\n") === FALSE) {
				break;
			}

			$offset = ftell($handle);

			$callback($line);

		}

		fclose($handle);

		return $offset;

	}

	/**
	 * Une ligne du journal au format de `AccessReportLib::LOG_FORMAT`, réduite à ce qui compte :
	 * l'adresse, l'heure, l'hôte, le chemin sans sa query string et le statut. NULL pour une ligne
	 * qui n'a pas ce format ou pas d'adresse IPv4.
	 */
	public static function parse(string $line): ?array {

		if(preg_match('~^(\d{1,3}(?:\.\d{1,3}){3}) - \S+ \[(\d{2})/([A-Za-z]{3})/(\d{4}):(\d{2}):\d{2}:\d{2} [^\]]*\] "([^"]*)" "([^"]*)" (\d{3}) ~', $line, $match) !== 1) {
			return NULL;
		}

		[, $ip, $day, $month, $year, $hour, $host, $request, $status] = $match;

		if(isset(self::MONTHS[$month]) === FALSE) {
			return NULL;
		}

		$parts = explode(' ', $request);
		$path = (count($parts) >= 2) ? $parts[1] : $request;
		$path = explode('?', $path, 2)[0];

		return [
			'ip' => $ip,
			'hour' => sprintf('%04d-%02d-%02d %02d:00:00', $year, self::MONTHS[$month], $day, $hour),
			'host' => mb_substr($host, 0, self::HOST_LENGTH),
			'path' => mb_substr($path, 0, self::PATH_LENGTH),
			'status' => (int)$status,
		];

	}

	/**
	 * Range la ligne dans son seau horaire et rend la clé du seau, ou NULL quand la ligne ne compte
	 * pour aucun événement : les 2xx et 3xx n'intéressent personne.
	 */
	public static function accumulate(array &$buckets, array $hit): ?string {

		if($hit['status'] === 429) {
			$type = AccessEvent::FLOOD;
			$key = $type.'|'.$hit['hour'].'|'.$hit['ip'];
		} else if($hit['status'] >= 400 and $hit['status'] < 500) {
			$type = AccessEvent::SCAN;
			$key = $type.'|'.$hit['hour'].'|'.$hit['ip'];
		} else if($hit['status'] >= 500) {
			$type = AccessEvent::ERROR;
			$key = $type.'|'.$hit['hour'].'|'.$hit['host'].'|'.$hit['path'];
		} else {
			return NULL;
		}

		if(isset($buckets[$key]) === FALSE) {

			$buckets[$key] = [
				'type' => $type,
				'hour' => $hit['hour'],
				'ip' => ($type === AccessEvent::ERROR) ? NULL : $hit['ip'],
				'count' => 0,
				'refused' => 0,
				'id' => NULL,
				'notified' => FALSE,
			];

			if($type === AccessEvent::SCAN) {
				$buckets[$key]['hosts'] = [];
				$buckets[$key]['paths'] = [];
			}

		}

		$bucket = &$buckets[$key];

		$bucket['count']++;

		// Nginx journalise `-` pour une requête qu'il n'a pas su lire, et y répond 400 sans PHP
		if($hit['status'] === 444 or ($hit['status'] === 400 and $hit['path'] === '-')) {
			$bucket['refused']++;
		}

		$bucket['host'] = $hit['host'];
		$bucket['path'] = $hit['path'];
		$bucket['status'] = $hit['status'];

		if($type === AccessEvent::SCAN) {
			$bucket['hosts'][$hit['host']] = 1;
			$bucket['paths'][$hit['path']] = 1;
		}

		return $key;

	}

	/**
	 * Écrit les seaux touchés par le passage qui ont franchi leur seuil : une création au premier
	 * franchissement, une mise à jour ensuite, et Discord quand l'événement le mérite.
	 */
	public static function flush(array &$buckets, array $keys): void {

		foreach($keys as $key) {

			$bucket = &$buckets[$key];

			if($bucket['count'] < self::getThreshold($bucket['type'])) {
				continue;
			}

			$e = new AccessEvent([
				'type' => $bucket['type'],
				'ip' => $bucket['ip'],
				'host' => $bucket['host'],
				'path' => $bucket['path'],
				'status' => $bucket['status'],
				'hour' => $bucket['hour'],
				'count' => $bucket['count'],
				'hosts' => ($bucket['type'] === AccessEvent::SCAN) ? count($bucket['hosts']) : NULL,
				'paths' => ($bucket['type'] === AccessEvent::SCAN) ? count($bucket['paths']) : NULL,
				'notifiedAt' => NULL,
			]);

			$e['refused'] = $bucket['refused'];

			if($bucket['id'] === NULL) {

				self::create($e);

				$bucket['id'] = $e['id'];

			} else {

				$e['id'] = $bucket['id'];

				self::update($e, ['host', 'path', 'status', 'count', 'hosts', 'paths']);

			}

			if(
				$bucket['notified'] === FALSE and
				self::isNotifiable($e) and
				self::notify($e)
			) {
				$bucket['notified'] = TRUE;
			}

		}

	}

	public static function getThreshold(string $type): int {

		return match($type) {
			AccessEvent::SCAN => self::SCAN_THRESHOLD,
			AccessEvent::FLOOD => self::FLOOD_THRESHOLD,
			AccessEvent::ERROR => self::ERROR_THRESHOLD,
		};

	}

	/**
	 * Oublie les seaux des heures révolues : les lignes arrivent dans l'ordre, l'heure précédente est
	 * gardée pour une requête longue journalisée après le changement d'heure.
	 */
	public static function prune(array &$buckets, string $latestHour): void {

		$oldest = date('Y-m-d H:00:00', strtotime($latestHour) - 3600);

		foreach($buckets as $key => $bucket) {

			if($bucket['hour'] < $oldest) {
				unset($buckets[$key]);
			}

		}

	}

	/**
	 * Une rafale se signale toujours, un balayage à partir de `NOTIFY_SCAN` réponses qui ont atteint
	 * PHP, une route en erreur jamais ; et une seule fois. `refused` compte les réponses du seau que
	 * Nginx a rendues seul, il n'est pas en base : seul le passage du cron le connaît.
	 */
	public static function isNotifiable(AccessEvent $e): bool {

		$e->expects(['type', 'count', 'refused', 'notifiedAt']);

		if($e['notifiedAt'] !== NULL) {
			return FALSE;
		}

		return match($e['type']) {
			AccessEvent::FLOOD => TRUE,
			AccessEvent::SCAN => $e['count'] - $e['refused'] >= self::NOTIFY_SCAN,
			AccessEvent::ERROR => FALSE,
		};

	}

	public static function notify(AccessEvent $e): bool {

		$sent = \communication\DiscordWebhookLib::send(self::getMessage($e), [], \communication\DiscordWebhookLib::SECURITY);

		if($sent) {

			$e['notifiedAt'] = new \Sql('NOW()');

			self::update($e, ['notifiedAt']);

		}

		return $sent;

	}

	/**
	 * Le message est écrit pour être lu dans Discord par l'équipe, en dehors de toute traduction :
	 * la langue du site n'y a pas de sens.
	 *
	 * L'adresse n'y figure pas, ni dans le texte ni dans le lien. Discord n'est déclaré destinataire
	 * dans aucun des deux documents que l'article 13 du RGPD oblige à tenir, son salon est hébergé
	 * hors de l'Union et il ne purge rien, là où l'événement disparaît d'ici au bout de KEEP_DAYS.
	 * Le lien porte donc l'identifiant de l'événement, que `getIpById()` résout derrière
	 * l'authentification pour rouvrir l'écran filtré sur l'adresse.
	 */
	public static function getMessage(AccessEvent $e): string {

		$e->expects(['id', 'type', 'host', 'path', 'hour', 'count', 'hosts', 'paths']);

		$start = strtotime($e['hour']);
		$when = 'le '.date('d/m', $start).' entre '.date('H:i', $start).' et '.date('H:i', $start + 3600);
		$url = \Lime::getUrl().'/security/admin/access?event='.$e['id'];

		return match($e['type']) {
			AccessEvent::FLOOD => '🚦 Rafale de '.$e['count'].' refus de la limitation Nginx sur '.$e['host'].' '.$when.', dernier chemin '.$e['path'].'. '.$url,
			AccessEvent::SCAN => '🔍 Balayage de '.$e['count'].' réponses 4xx '.$when.', sur '.$e['host'].self::getOtherHosts($e['hosts']).', '.$e['paths'].' chemins distincts, dernier '.$e['path'].'. '.$url,
		};

	}

	protected static function getOtherHosts(int $hosts): string {

		if($hosts <= 1) {
			return '';
		} else if($hosts === 2) {
			return ' et 1 autre hôte';
		} else {
			return ' et '.($hosts - 1).' autres hôtes';
		}

	}

	/**
	 * Les événements qui répondent à la recherche, la page demandée et leur nombre total.
	 */
	public static function getAll(\Search $search, int $page): array {

		self::applySearch($search);

		$cAccessEvent = AccessEvent::model()
			->select(AccessEvent::getSelection())
			->sort(['hour' => SORT_DESC, 'id' => SORT_DESC])
			->option('count')
			->getCollection($page * SecuritySetting::MAX_BY_PAGE, SecuritySetting::MAX_BY_PAGE);

		$nAccessEvent = AccessEvent::model()->found();

		return [$cAccessEvent, $nAccessEvent];

	}

	/**
	 * L'adresse que porte un événement, pour un lien qui n'a pu transporter que son identifiant
	 * (cf. `getMessage()`). Sans adresse, l'écran appelant s'ouvre sans filtre.
	 */
	public static function getIpById(?int $id): ?string {

		if($id === NULL) {
			return NULL;
		}

		$eAccessEvent = AccessEvent::model()
			->select('ip')
			->whereId($id)
			->get();

		return $eAccessEvent->notEmpty() ? $eAccessEvent['ip'] : NULL;

	}

	public static function applySearch(\Search $search): void {

		AccessEvent::model()
			->whereType($search->get('type'), if: $search->get('type'))
			->whereIp($search->get('ip'), if: $search->get('ip'))
			->where('m1.host', 'LIKE', '%'.$search->get('host').'%', if: $search->get('host'))
			->where('m1.hour', '>=', $search->get('from'), if: $search->get('from'))
			->where('m1.hour', '<', new \Sql(AccessEvent::model()->format($search->get('to')).' + INTERVAL 1 DAY'), if: $search->get('to'));

	}

	public static function clean(): void {

		AccessEvent::model()
			->where('hour < NOW() - INTERVAL '.self::KEEP_DAYS.' DAY')
			->delete();

	}

}
?>
