<?php
namespace map;

/**
 * Le relief sous le plan 3D, en tuiles d'altitudes lues au RGE ALTI de l'IGN.
 *
 * Les tuiles suivent une grille fixe en degrés, commune à toutes les structures : RELIEF_SIZE points de côté, au pas de
 * 1 / RELIEF_DIVISIONS_LAT degré de latitude et 1 / RELIEF_DIVISIONS_LNG degré de longitude, soit quatre mètres environ en
 * France. Le point (colonne i, rangée j) de la tuile (x, y) est au centre de sa maille, à la longitude
 * (x * RELIEF_SIZE + i + .5) / RELIEF_DIVISIONS_LNG et à la latitude (y * RELIEF_SIZE + j + .5) / RELIEF_DIVISIONS_LAT, ce qui
 * est aussi le centre du pixel que le WMS rend pour la même emprise. Les rangées vont du sud au nord.
 *
 * Seul le cron interroge l'IGN : la scène lit ce que la table porte, rien de plus.
 */
class ReliefLib extends ReliefCrud {

	/**
	 * Les tuiles qui couvrent les zones tracées de la structure élargies de `ScenePlanLand.REACH`, les plus proches des zones
	 * d'abord, au plus RELIEF_TILES_MAX : au-delà, la scène reste plate.
	 *
	 * @return array<array{0: int, 1: int}>
	 */
	public static function getTiles(\structure\Structure $eStructure): array {

		$boxes = [];

		foreach(self::getZones($eStructure) as $coordinates) {

			$lngs = array_column($coordinates, 0);
			$lats = array_column($coordinates, 1);

			$boxes[] = [
				MapLib::getDestination([min($lngs), min($lats)], MapSetting::RELIEF_REACH, -90)[0],
				MapLib::getDestination([min($lngs), min($lats)], MapSetting::RELIEF_REACH, 180)[1],
				MapLib::getDestination([max($lngs), max($lats)], MapSetting::RELIEF_REACH, 90)[0],
				MapLib::getDestination([max($lngs), max($lats)], MapSetting::RELIEF_REACH, 0)[1],
			];

		}

		$tiles = [];

		foreach($boxes as [$west, $south, $east, $north]) {

			// Une emprise plus large que RELIEF_TILES_MAX tuiles se borne autour de son milieu : ses tuiles sont toutes à distance
			// nulle, donc le plafond n'en garderait pas davantage, et une zone en lanière sur des degrés en compterait des millions
			[$xFirst, $xLast] = self::clip(self::tileX($west), self::tileX($east));
			[$yFirst, $yLast] = self::clip(self::tileY($south), self::tileY($north));

			for($y = $yFirst; $y <= $yLast; $y++) {
				for($x = $xFirst; $x <= $xLast; $x++) {

					$center = self::getTileCenter($x, $y);
					$distance = min(array_map(fn(array $box) => self::getDistanceToBox($center, $box), $boxes));

					$key = $x.'/'.$y;

					if(array_key_exists($key, $tiles) === FALSE or $distance < $tiles[$key]) {
						$tiles[$key] = $distance;
					}

				}
			}

		}

		asort($tiles);

		return array_map(
			fn(string $key) => array_map('intval', explode('/', $key)),
			array_slice(array_keys($tiles), 0, MapSetting::RELIEF_TILES_MAX)
		);

	}

	/**
	 * Les structures que le cron passe : celles qui ont tracé une zone, et celles qui gardent des tuiles après avoir effacé leurs
	 * zones, que synchronize() retire. Une structure fermée n'en est pas : personne n'ouvre plus sa scène, et ses tuiles restent
	 * telles quelles si elle rouvre. La 7 en est : le cron ne lit que l'IGN et n'écrit que ses tuiles, jamais son décor.
	 *
	 * @return int[]
	 */
	public static function getStructures(): array {

		$active = fn() => \structure\Structure::model()
			->select('id')
			->whereStatus(\structure\Structure::ACTIVE)
			->getSql();

		return array_values(array_unique(array_merge(
			Zone::model()
				->whereCoordinates('!=', NULL)
				->whereStructure('IN', $active())
				->group('structure')
				->getColumn('structure')
				->getIds(),
			Relief::model()
				->whereStructure('IN', $active())
				->group('structure')
				->getColumn('structure')
				->getIds()
		)));

	}

	/**
	 * Aligne les tuiles de la structure sur celles que ses zones demandent : une tuile manquante est demandée à l'IGN, une
	 * tuile en échec depuis moins de RELIEF_RETRY secondes attend, une tuile que l'IGN ne couvre pas attend
	 * RELIEF_UNCOVERED_RETRY secondes, une tuile qui ne sert plus s'en va. Rend le nombre d'appels faits, `$calls` au plus : les
	 * tuiles qui restent attendent le passage suivant du cron.
	 */
	public static function synchronize(\structure\Structure $eStructure, int $calls): int {

		$tiles = self::getTiles($eStructure);

		$cRelief = Relief::model()
			->select(['id', 'tileX', 'tileY', 'altitudes', 'failedAt', 'uncoveredAt'])
			->whereStructure($eStructure)
			->getCollection();

		$wanted = array_flip(array_map(fn(array $tile) => $tile[0].'/'.$tile[1], $tiles));
		$existing = [];

		foreach($cRelief as $eRelief) {

			$key = $eRelief['tileX'].'/'.$eRelief['tileY'];

			if(array_key_exists($key, $wanted) === FALSE) {
				self::delete($eRelief);
			} else {
				$existing[$key] = $eRelief;
			}

		}

		$retry = date('Y-m-d H:i:s', \Clock::timestamp() - MapSetting::RELIEF_RETRY);
		$uncoveredRetry = date('Y-m-d H:i:s', \Clock::timestamp() - MapSetting::RELIEF_UNCOVERED_RETRY);
		$made = 0;

		foreach($tiles as [$x, $y]) {

			if($made >= $calls) {
				break;
			}

			$key = $x.'/'.$y;

			$eRelief = array_key_exists($key, $existing) ? $existing[$key] : new Relief([
				'structure' => $eStructure,
				'tileX' => $x,
				'tileY' => $y,
				'altitudes' => NULL,
				'failedAt' => NULL,
				'uncoveredAt' => NULL,
			]);

			if(
				$eRelief['altitudes'] !== NULL or
				($eRelief['failedAt'] !== NULL and $eRelief['failedAt'] > $retry) or
				($eRelief['uncoveredAt'] !== NULL and $eRelief['uncoveredAt'] > $uncoveredRetry)
			) {
				continue;
			}

			$response = self::fetch($x, $y);
			$made++;

			// Sans réponse complète, l'échec tient au service ou au réseau, et se retente le lendemain. Une réponse complète qui porte
			// une valeur hors du territoire dit que le RGE ALTI ne couvre pas la tuile, en mer ou hors de France : l'IGN la redonnerait
			// telle quelle au passage suivant, et seule une panne qui aurait rendu le vide se rattrape, après une longue attente. Les
			// deux cas laissent `altitudes` à NULL, et rien d'autre ne les distingue : chacun a sa date
			$eRelief['failedAt'] = NULL;
			$eRelief['uncoveredAt'] = NULL;

			if($response === NULL) {
				$eRelief['failedAt'] = \Clock::datetime();
			} else {

				$eRelief['altitudes'] = self::decode($response);

				if($eRelief['altitudes'] === NULL) {
					$eRelief['uncoveredAt'] = \Clock::datetime();
				}

			}

			if($eRelief->exists()) {
				self::update($eRelief, ['altitudes', 'failedAt', 'uncoveredAt']);
			} else {
				self::create($eRelief);
			}

		}

		return $made;

	}

	/**
	 * La réponse du service pour une tuile, RELIEF_SIZE² float32 petit-boutistes, première rangée au nord, ou NULL s'il ne répond
	 * pas, ou par autre chose que ce nombre d'altitudes.
	 */
	public static function fetch(int $x, int $y): ?string {

		// Les bords se calculent sur les indices entiers des points : deux tuiles voisines partagent exactement le même bord
		$size = MapSetting::RELIEF_SIZE;
		$south = ($y * $size) / MapSetting::RELIEF_DIVISIONS_LAT;
		$north = (($y + 1) * $size) / MapSetting::RELIEF_DIVISIONS_LAT;
		$west = ($x * $size) / MapSetting::RELIEF_DIVISIONS_LNG;
		$east = (($x + 1) * $size) / MapSetting::RELIEF_DIVISIONS_LNG;

		// En EPSG:4326, WMS 1.3.0 range l'emprise latitude d'abord
		$response = (MapSetting::$reliefTransport)(MapSetting::RELIEF_URL, [
			'SERVICE' => 'WMS',
			'VERSION' => '1.3.0',
			'REQUEST' => 'GetMap',
			'LAYERS' => MapSetting::RELIEF_LAYER,
			'STYLES' => '',
			'CRS' => 'EPSG:4326',
			'BBOX' => implode(',', [$south, $west, $north, $east]),
			'WIDTH' => MapSetting::RELIEF_SIZE,
			'HEIGHT' => MapSetting::RELIEF_SIZE,
			'FORMAT' => 'image/x-bil;bits=32',
		]);

		if($response === NULL or strlen($response) !== $size * $size * 4) {
			return NULL;
		}

		return $response;

	}

	/**
	 * Les altitudes d'une réponse complète en int16 de décimètres, petit-boutiste, rangées du sud au nord, ou NULL si l'une n'est
	 * pas vraisemblable : une valeur hors du territoire (-99 999) refuse la tuile entière, qu'une maille à zéro rendrait fausse
	 * sans le dire.
	 */
	public static function decode(string $response): ?string {

		$size = MapSetting::RELIEF_SIZE;
		$values = array_values(unpack('g*', $response));
		$decimeters = [];

		// Le WMS rend sa première rangée au nord
		for($row = $size - 1; $row >= 0; $row--) {
			for($column = 0; $column < $size; $column++) {

				$altitude = $values[$row * $size + $column];

				if(is_finite($altitude) === FALSE or $altitude < MapSetting::RELIEF_ALTITUDE_MIN or $altitude > MapSetting::RELIEF_ALTITUDE_MAX) {
					return NULL;
				}

				$decimeters[] = (int)round($altitude * 10);

			}
		}

		return pack('v*', ...$decimeters);

	}

	/**
	 * Ce que la scène reçoit du relief, ou NULL quand il ne se verrait pas : moins de RELIEF_THRESHOLD mètres entre le plus bas
	 * et le plus haut des sommets des zones.
	 */
	public static function getForScene(\structure\Structure $eStructure): ?array {

		$cRelief = Relief::model()
			->select(['tileX', 'tileY', 'altitudes'])
			->whereStructure($eStructure)
			->whereAltitudes('!=', NULL)
			->getCollection();

		if($cRelief->empty()) {
			return NULL;
		}

		$grid = [];

		foreach($cRelief as $eRelief) {
			$grid[$eRelief['tileX'].'/'.$eRelief['tileY']] = $eRelief['altitudes'];
		}

		$altitudes = [];

		foreach(self::getZones($eStructure) as $coordinates) {
			foreach($coordinates as [$lng, $lat]) {

				$altitude = self::getAltitude($grid, $lng, $lat);

				if($altitude !== NULL) {
					$altitudes[] = $altitude;
				}

			}
		}

		if($altitudes === [] or max($altitudes) - min($altitudes) < MapSetting::RELIEF_THRESHOLD * 10) {
			return NULL;
		}

		$tiles = [];

		foreach($cRelief as $eRelief) {
			$tiles[] = [
				'x' => $eRelief['tileX'],
				'y' => $eRelief['tileY'],
				'altitudes' => base64_encode($eRelief['altitudes']),
			];
		}

		return [
			'size' => MapSetting::RELIEF_SIZE,
			'divisions' => ['lat' => MapSetting::RELIEF_DIVISIONS_LAT, 'lng' => MapSetting::RELIEF_DIVISIONS_LNG],
			'tiles' => $tiles,
		];

	}

	/* Les contours des zones tracées, toutes saisons comprises : une zone d'une autre saison se montre aussi dans la scène */
	private static function getZones(\structure\Structure $eStructure): array {

		return Zone::model()
			->whereStructure($eStructure)
			->whereCoordinates('!=', NULL)
			->getColumn('coordinates');

	}

	/* L'altitude en décimètres du point de la grille le plus proche, ou NULL hors des tuiles connues */
	private static function getAltitude(array $grid, float $lng, float $lat): ?int {

		$size = MapSetting::RELIEF_SIZE;
		$i = (int)floor($lng * MapSetting::RELIEF_DIVISIONS_LNG);
		$j = (int)floor($lat * MapSetting::RELIEF_DIVISIONS_LAT);
		$x = (int)floor($i / $size);
		$y = (int)floor($j / $size);

		$key = $x.'/'.$y;

		// Un sommet de zone peut tomber hors des tuiles connues, celles d'un appel en échec ou au-delà du plafond
		if(array_key_exists($key, $grid) === FALSE) {
			return NULL;
		}

		$altitudes = $grid[$key];

		$offset = (($j - $y * $size) * $size + ($i - $x * $size)) * 2;
		$value = unpack('v', $altitudes, $offset)[1];

		// `v` lit un entier non signé : un point sous le niveau de la mer revient en négatif
		return ($value >= 0x8000) ? $value - 0x10000 : $value;

	}

	/**
	 * L'appel au service par `MapSetting::$reliefTransport` : ni redirection, qui mènerait ailleurs que chez l'IGN, ni en-tête
	 * Referer, qui nommerait le site appelant, et une réponse autre que 200 vaut un échec.
	 */
	public static function call(string $url, array $arguments): ?string {

		$curl = new \util\CurlLib();

		try {

			$response = $curl->exec($url, $arguments, options: [
				CURLOPT_TIMEOUT => MapSetting::RELIEF_TIMEOUT,
				CURLOPT_CONNECTTIMEOUT => MapSetting::RELIEF_TIMEOUT,
				CURLOPT_FOLLOWLOCATION => FALSE,
				CURLOPT_REFERER => '',
				CURLOPT_HTTPHEADER => ['Referer:'],
			]);

		} catch(\Exception) {
			return NULL;
		}

		if($curl->getLastInfos()['httpCode'] !== 200 or is_string($response) === FALSE) {
			return NULL;
		}

		return $response;

	}

	/* Les indices de tuiles d'une emprise sur un axe, RELIEF_TILES_MAX au plus autour de leur milieu */
	private static function clip(int $first, int $last): array {

		if($last - $first < MapSetting::RELIEF_TILES_MAX) {
			return [$first, $last];
		}

		$first = intdiv($first + $last, 2) - intdiv(MapSetting::RELIEF_TILES_MAX, 2);

		return [$first, $first + MapSetting::RELIEF_TILES_MAX - 1];

	}

	private static function tileX(float $lng): int {
		return (int)floor($lng * MapSetting::RELIEF_DIVISIONS_LNG / MapSetting::RELIEF_SIZE);
	}

	private static function tileY(float $lat): int {
		return (int)floor($lat * MapSetting::RELIEF_DIVISIONS_LAT / MapSetting::RELIEF_SIZE);
	}

	private static function getTileCenter(int $x, int $y): array {
		return [
			($x + .5) * MapSetting::RELIEF_SIZE / MapSetting::RELIEF_DIVISIONS_LNG,
			($y + .5) * MapSetting::RELIEF_SIZE / MapSetting::RELIEF_DIVISIONS_LAT,
		];
	}

	/* La distance en mètres d'un point à une emprise [ouest, sud, est, nord], nulle dedans */
	private static function getDistanceToBox(array $point, array $box): float {

		[$lng, $lat] = $point;
		[$west, $south, $east, $north] = $box;

		$closest = [max($west, min($east, $lng)), max($south, min($north, $lat))];

		return MapLib::getDistance($point, $closest);

	}

}
?>
