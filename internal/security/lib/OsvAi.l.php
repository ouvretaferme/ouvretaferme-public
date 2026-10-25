<?php
namespace security;

/**
 * Les failles connues d'une version précise d'un paquet, lues sur OSV.dev, sans compte.
 *
 * OSV rassemble sous un même format les avis des distributions (Ubuntu, Debian), de GitHub pour
 * Packagist et npm, et d'autres. Pour un paquet de distribution, il compare la version installée
 * à celle qui corrige, rétroportages compris : une version Ubuntu ne se compare jamais au numéro
 * de l'éditeur.
 */
class OsvAiLib {

	const QUERY_BATCH_URL = 'https://api.osv.dev/v1/querybatch';
	const VULNERABILITY_URL = 'https://api.osv.dev/v1/vulns/';
	const PAGE_URL = 'https://osv.dev/vulnerability/';

	/**
	 * OSV accepte au plus mille requêtes par appel.
	 */
	const BATCH_SIZE = 1000;

	const CRITICAL = 4;
	const HIGH = 3;
	const MEDIUM = 2;
	const LOW = 1;

	/**
	 * Les identifiants des failles de chaque paquet demandé, dans l'ordre des requêtes.
	 *
	 * @param array<int, array{ecosystem: string, package: string, version: string}> $queries
	 * @return array<int, string[]>
	 */
	public static function query(array $queries): array {

		$results = [];

		foreach(array_chunk($queries, self::BATCH_SIZE, preserve_keys: TRUE) as $chunk) {

			$body = ['queries' => array_values(array_map(fn(array $query) => [
				'package' => ['name' => $query['package'], 'ecosystem' => $query['ecosystem']],
				'version' => $query['version'],
			], $chunk))];

			$response = self::request(self::QUERY_BATCH_URL, json_encode($body, JSON_THROW_ON_ERROR), 'POST');

			foreach(array_keys($chunk) as $position => $index) {

				$ids = [];

				foreach($response['results'][$position]['vulns'] ?? [] as $vulnerability) {
					$ids[] = $vulnerability['id'];
				}

				// Une réponse paginée ne rend la suite d'un paquet que sur un nouvel appel pour lui seul.
				if(isset($response['results'][$position]['next_page_token'])) {
					$ids = [...$ids, ...self::queryNextPages($chunk[$index], $response['results'][$position]['next_page_token'])];
				}

				$results[$index] = $ids;

			}

		}

		return $results;

	}

	private static function queryNextPages(array $query, string $token): array {

		$ids = [];

		while($token !== '') {

			$response = self::request('https://api.osv.dev/v1/query', json_encode([
				'package' => ['name' => $query['package'], 'ecosystem' => $query['ecosystem']],
				'version' => $query['version'],
				'page_token' => $token,
			], JSON_THROW_ON_ERROR), 'POST');

			foreach($response['vulns'] ?? [] as $vulnerability) {
				$ids[] = $vulnerability['id'];
			}

			$token = $response['next_page_token'] ?? '';

		}

		return $ids;

	}

	/**
	 * La fiche d'une faille, telle que OSV la publie.
	 */
	public static function fetch(string $id): array {
		return self::request(self::VULNERABILITY_URL.rawurlencode($id), [], 'GET');
	}

	/**
	 * Ce que la veille lit d'une fiche : résumé, date de publication, gravité, et version qui la
	 * corrige dans l'écosystème et pour le paquet demandés (NULL quand aucune n'est publiée).
	 *
	 * Une fiche de bibliothèque porte un intervalle par branche (`ip` corrigé en 1.1.9 sur la 1.x et
	 * en 2.0.1 sur la 2.x) : la correction retenue est la plus petite au-dessus de la version
	 * installée. Une version de distribution ne se compare pas par `version_compare()`
	 * (`2:3.87.1-1+deb12u3`), et OSV l'a déjà située dans l'intervalle : toute correction suffit.
	 *
	 * @return array{id: string, summary: string, published: string, level: int, fixed: ?string, url: string}
	 */
	public static function describe(array $vulnerability, string $ecosystem, string $package, string $version): array {

		$fixes = [];

		foreach($vulnerability['affected'] ?? [] as $affected) {

			if(($affected['package']['ecosystem'] ?? NULL) !== $ecosystem or ($affected['package']['name'] ?? NULL) !== $package) {
				continue;
			}

			foreach($affected['ranges'] ?? [] as $range) {
				foreach($range['events'] as $event) {
					if(isset($event['fixed'])) {
						$fixes[] = $event['fixed'];
					}
				}
			}

		}

		if(in_array($ecosystem, ['Packagist', 'npm'], TRUE)) {
			$fixes = array_filter($fixes, fn(string $fix) => version_compare($fix, $version, '>'));
			usort($fixes, 'version_compare');
		}

		$fixed = ($fixes === []) ? NULL : reset($fixes);

		return [
			'id' => $vulnerability['id'],
			'summary' => $vulnerability['summary'] ?? mb_substr($vulnerability['details'] ?? '', 0, 160),
			'published' => substr($vulnerability['published'] ?? '', 0, 10),
			'level' => self::getLevel($vulnerability),
			'fixed' => $fixed,
			'url' => self::PAGE_URL.rawurlencode($vulnerability['id']),
		];

	}

	/**
	 * La gravité, dans cet ordre : la priorité que la distribution a donnée (elle tient compte de
	 * la façon dont elle construit le paquet), puis celle de l'avis GitHub, puis le score CVSS le
	 * plus fort. Sans aucune des trois, la faille compte comme moyenne : ne rien savoir n'est pas
	 * une raison de la taire.
	 */
	public static function getLevel(array $vulnerability): int {

		foreach($vulnerability['severity'] ?? [] as $severity) {
			if(in_array($severity['type'], ['Ubuntu', 'Debian'], TRUE)) {
				return match(strtolower($severity['score'])) {
					'critical' => self::CRITICAL,
					'high' => self::HIGH,
					'medium' => self::MEDIUM,
					'low', 'negligible', 'unimportant' => self::LOW,
					default => self::MEDIUM,
				};
			}
		}

		$advisory = $vulnerability['database_specific']['severity'] ?? NULL;

		if(is_string($advisory)) {
			return match(strtoupper($advisory)) {
				'CRITICAL' => self::CRITICAL,
				'HIGH' => self::HIGH,
				'MODERATE', 'MEDIUM' => self::MEDIUM,
				'LOW' => self::LOW,
				default => self::MEDIUM,
			};
		}

		$scores = [];

		foreach($vulnerability['severity'] ?? [] as $severity) {
			if($severity['type'] === 'CVSS_V3') {
				$scores[] = self::getCvss3Score($severity['score']);
			}
		}

		if($scores === []) {
			return self::MEDIUM;
		}

		$score = max($scores);

		if($score >= 9) {
			return self::CRITICAL;
		} else if($score >= 7) {
			return self::HIGH;
		} else if($score >= 4) {
			return self::MEDIUM;
		} else {
			return self::LOW;
		}

	}

	/**
	 * Le score de base d'un vecteur CVSS 3.x, d'après la spécification du FIRST
	 * (https://www.first.org/cvss/v3.1/specification-document, § 7.1).
	 */
	public static function getCvss3Score(string $vector): float {

		$metrics = [];

		foreach(explode('/', $vector) as $part) {
			if(str_contains($part, ':')) {
				[$metric, $value] = explode(':', $part, 2);
				$metrics[$metric] = $value;
			}
		}

		$scopeChanged = (($metrics['S'] ?? 'U') === 'C');

		$attackVector = match($metrics['AV'] ?? 'N') { 'N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2 };
		$attackComplexity = match($metrics['AC'] ?? 'L') { 'L' => 0.77, 'H' => 0.44 };
		$privileges = match($metrics['PR'] ?? 'N') { 'N' => 0.85, 'L' => $scopeChanged ? 0.68 : 0.62, 'H' => $scopeChanged ? 0.5 : 0.27 };
		$interaction = match($metrics['UI'] ?? 'N') { 'N' => 0.85, 'R' => 0.62 };
		$impacts = array_map(fn(string $metric) => match($metrics[$metric] ?? 'N') { 'H' => 0.56, 'L' => 0.22, 'N' => 0.0 }, ['C', 'I', 'A']);

		$iss = 1 - (1 - $impacts[0]) * (1 - $impacts[1]) * (1 - $impacts[2]);

		$impact = $scopeChanged ? 7.52 * ($iss - 0.029) - 3.25 * pow($iss - 0.02, 15) : 6.42 * $iss;

		if($impact <= 0) {
			return 0.0;
		}

		$exploitability = 8.22 * $attackVector * $attackComplexity * $privileges * $interaction;

		$score = $scopeChanged ? min(1.08 * ($impact + $exploitability), 10) : min($impact + $exploitability, 10);

		return self::roundUp($score);

	}

	/**
	 * L'arrondi au dixième supérieur de la spécification (annexe A), qui écarte l'erreur de virgule
	 * flottante : 4.000000001 donne 4.0 et non 4.1.
	 */
	private static function roundUp(float $value): float {

		$integer = (int)round($value * 100000);

		if($integer % 10000 === 0) {
			return $integer / 100000;
		}

		return (floor($integer / 10000) + 1) / 10;

	}

	private static function request(string $url, string|array $body, string $mode): array {

		$curl = new \util\CurlLib();

		$response = $curl->exec($url, $body, $mode, [
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_TIMEOUT => 60,
		]);

		$httpCode = $curl->getLastInfos()['httpCode'];

		if($httpCode !== 200) {
			throw new \Exception('OSV a répondu '.$httpCode);
		}

		return json_decode($response, TRUE, flags: JSON_THROW_ON_ERROR);

	}

}
?>
