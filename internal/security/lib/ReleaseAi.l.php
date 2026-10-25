<?php
namespace security;

/**
 * Les versions publiées par les éditeurs : fin de support et dernière version d'une branche
 * (endoflife.date), versions de sécurité de PHP et de Node.js, et flux de changements d'un service.
 * Toutes ces sources sont ouvertes, sans compte.
 */
class ReleaseAiLib {

	const ENDOFLIFE_URL = 'https://endoflife.date/api/v1/products/';
	const ENDOFLIFE_PAGE_URL = 'https://endoflife.date/';
	const PHP_RELEASES_URL = 'https://www.php.net/releases/index.php';
	const PHP_ARCHIVE_URL = 'https://www.php.net/archive/';
	const NODE_RELEASES_URL = 'https://nodejs.org/dist/index.json';

	const REMOVAL_PATTERN = '/(deprecat|sunset|end[ -]of[ -]life|retir|decommission|breaking|will be removed|no longer (be )?supported)/i';

	/**
	 * Les branches d'un produit : nom (`8.4`, `22`, `24.04`), fin de support, dernière version.
	 *
	 * @return array<int, array{name: string, eolFrom: ?string, latest: ?string, latestAt: ?string}>
	 */
	public static function getCycles(string $product): array {

		$content = self::request(self::ENDOFLIFE_URL.rawurlencode($product));

		return array_map(fn(array $release) => [
			'name' => $release['name'],
			'eolFrom' => $release['eolFrom'],
			'isLts' => $release['isLts'],
			'isEol' => $release['isEol'],
			'latest' => $release['latest']['name'] ?? NULL,
			'latestAt' => $release['latest']['date'] ?? NULL,
		], $content['result']['releases']);

	}

	/**
	 * La branche vers laquelle monter : la plus récente des branches suivies à long terme quand le
	 * produit en a, la plus récente tout court sinon. Une branche courte (MySQL 26.7, Node 25) n'a
	 * que quelques mois de support et ferait remonter la même alerte au printemps suivant.
	 */
	public static function getRecommendedCycle(array $cycles): array {

		foreach($cycles as $cycle) {
			if($cycle['isLts'] and $cycle['isEol'] === FALSE) {
				return $cycle;
			}
		}

		return $cycles[0];

	}

	/**
	 * La branche d'une version : la plus longue dont le nom la préfixe (`8.4.26` est dans `8.4`,
	 * `150.0.7871.181` dans `150`). NULL quand le produit ne la connaît pas.
	 */
	public static function findCycle(array $cycles, string $version): ?array {

		$found = NULL;

		foreach($cycles as $cycle) {

			$matches = ($version === $cycle['name'] or str_starts_with($version, $cycle['name'].'.'));

			if($matches and ($found === NULL or strlen($cycle['name']) > strlen($found['name']))) {
				$found = $cycle;
			}

		}

		return $found;

	}

	/**
	 * Les versions de sécurité publiées après `$version` dans sa branche, avec leur date. PHP marque
	 * chaque annonce d'une étiquette `security` (https://www.php.net/archive/), Node.js chaque
	 * version d'un booléen `security`.
	 *
	 * @return array<string, string> version => date
	 */
	public static function getSecurityReleases(string $source, string $version): array {

		$releases = match($source) {
			'php' => self::getPhpSecurityReleases($version),
			'node' => self::getNodeSecurityReleases($version),
		};

		return array_filter($releases, fn(string $release) => version_compare($release, $version, '>'), ARRAY_FILTER_USE_KEY);

	}

	private static function getPhpSecurityReleases(string $version): array {

		$branch = implode('.', array_slice(explode('.', $version), 0, 2));

		$content = self::request(self::PHP_RELEASES_URL.'?'.http_build_query(['json' => '', 'version' => $branch, 'max' => 100]));

		$releases = [];

		foreach($content as $release => $description) {
			if(in_array('security', $description['tags'] ?? [], TRUE)) {
				$releases[$release] = \DateTime::createFromFormat('d M Y', $description['date'])->format('Y-m-d');
			}
		}

		return $releases;

	}

	private static function getNodeSecurityReleases(string $version): array {

		$major = strstr($version, '.', TRUE);

		$releases = [];

		foreach(self::request(self::NODE_RELEASES_URL) as $release) {
			if($release['security'] and str_starts_with($release['version'], 'v'.$major.'.')) {
				$releases[ltrim($release['version'], 'v')] = $release['date'];
			}
		}

		return $releases;

	}

	/**
	 * Les entrées d'un flux RSS ou Atom publiées depuis `$since` qui annoncent un retrait : une
	 * dépréciation, une fin de vie, un changement incompatible. Les autres annonces (une version
	 * nouvelle, une fonction de plus) ne demandent rien à OTF. `$topics` borne aux entrées qui
	 * parlent de ce qu'OTF utilise, pour un service dont le flux couvre bien d'autres produits.
	 *
	 * @param string[] $topics
	 * @return array<int, array{title: string, url: string, date: string}>
	 */
	public static function getFeedRemovals(string $url, string $since, array $topics = []): array {

		$content = self::fetch($url);

		$xml = simplexml_load_string($content, options: LIBXML_NOCDATA | LIBXML_NONET);

		if($xml === FALSE) {
			throw new \Exception('le flux ne se lit pas');
		}

		$items = [];

		// RSS range ses entrées sous channel/item, Atom sous entry ; les clés d'un parcours de
		// SimpleXML sont les noms des balises, donc se répètent et ne se fusionnent pas.
		$entries = isset($xml->channel) ? iterator_to_array($xml->channel->item, FALSE) : iterator_to_array($xml->entry, FALSE);

		foreach($entries as $entry) {

			$date = (string)($entry->pubDate ?? $entry->updated ?? '');

			if($date === '' or date('Y-m-d', strtotime($date)) < $since) {
				continue;
			}

			$title = self::toPlain((string)$entry->title);
			$link = (string)($entry->link['href'] ?? '') ?: (string)$entry->link;
			$plain = self::toPlain((string)$entry->description.' '.(string)$entry->summary.' '.(string)$entry->children('content', TRUE)->encoded);

			if($topics !== [] and preg_match('/'.implode('|', array_map(fn(string $topic) => preg_quote($topic, '/'), $topics)).'/i', $title.' '.$plain) !== 1) {
				continue;
			}

			// Un titre de journal des changements n'est souvent qu'une date : la phrase qui annonce le
			// retrait le suit.
			if(preg_match(self::REMOVAL_PATTERN, $title) === 1) {
				$items[] = ['title' => $title, 'url' => $link, 'date' => date('Y-m-d', strtotime($date))];
			} else if(preg_match('/[^.!?]*'.substr(self::REMOVAL_PATTERN, 1, -2).'[^.!?]*/i', $plain, $match) === 1) {
				$items[] = ['title' => $title.' · '.mb_strimwidth(trim($match[0]), 0, 200, '…'), 'url' => $link, 'date' => date('Y-m-d', strtotime($date))];
			}

		}

		return $items;

	}

	/**
	 * Le texte d'un fragment HTML de flux, sur une ligne : une balise vaut une espace, pour que deux
	 * paragraphes ne se collent pas.
	 */
	private static function toPlain(string $html): string {
		return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)))));
	}

	/**
	 * La ligne d'un tableau Markdown dont une cellule nomme `$name`, en cellules, sans les
	 * accents graves d'une cellule de code. Les éditeurs de modèles publient ainsi l'état de
	 * chacun (Anthropic, « Model deprecations »). NULL quand aucune ligne ne le nomme.
	 *
	 * @return string[]|null
	 */
	public static function getTableRow(string $url, string $name): ?array {

		foreach(explode("\n", self::fetch($url)) as $line) {

			if(str_starts_with($line, '|') === FALSE) {
				continue;
			}

			$cells = array_map(fn(string $cell) => trim($cell, " \t`"), explode('|', trim($line, " \t|")));

			if(in_array($name, $cells, TRUE)) {
				return $cells;
			}

		}

		return NULL;

	}

	public static function fetch(string $url): string {

		$curl = new \util\CurlLib();
		$response = $curl->exec($url, []);

		$httpCode = $curl->getLastInfos()['httpCode'];

		if($httpCode !== 200) {
			throw new \Exception(parse_url($url, PHP_URL_HOST).' a répondu '.$httpCode);
		}

		return $response;

	}

	private static function request(string $url): array {
		return json_decode(self::fetch($url), TRUE, flags: JSON_THROW_ON_ERROR);
	}

}
?>
