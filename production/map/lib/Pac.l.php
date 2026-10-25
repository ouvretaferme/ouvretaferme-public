<?php
namespace map;

/**
 * Les parcelles déclarées à la PAC, lues dans le Registre parcellaire graphique de l'IGN pour l'outil « Parcelle PAC » de la Carte
 * (`MapDrawingPac`, `static/js/drawing.js`). Le registre est public et anonyme. L'outil n'en garde que des contours, et pour son
 * survol le libellé de culture lu dans la table des cultures. Rien ne s'enregistre ici, le contour repartant par la création d'une
 * parcelle.
 */
class PacLib {

	const string YEAR_KEY = 'map-pac-year';
	const string CROPS_KEY = 'map-pac-crops';

	/* Le silence du registre se garde ce temps, en secondes : sans lui, chaque appel d'un registre en panne attendrait un délai de plus */
	const int YEAR_MISSING = 3600;
	const int CROPS_MISSING = 3600;

	/**
	 * Les parcelles du carré de MapSetting::PAC_AROUND mètres de demi-côté autour du point, et ce carré, que le navigateur garde
	 * pour ne rien redemander tant qu'on clique dedans. Chaque parcelle porte le libellé de sa culture, que le survol dit sous la
	 * surface, ou NULL quand la table des cultures ne le connaît pas : le navigateur ne reçoit jamais le code. Un service qui ne
	 * répond pas rend un carré vide : le clic dit alors qu'il n'y a pas de parcelle, ce que l'utilisateur corrige à la main.
	 */
	public static function getAround(array $lngLat): array {

		$box = self::getBox($lngLat, MapSetting::PAC_AROUND);

		// Le carré s'écrit latitude d'abord : c'est l'ordre des axes du système de coordonnées que le WFS 2.0 attend
		$json = self::call([
			'SERVICE' => 'WFS',
			'VERSION' => '2.0.0',
			'REQUEST' => 'GetFeature',
			'TYPENAMES' => MapSetting::PAC_LAYER,
			'OUTPUTFORMAT' => 'application/json',
			'COUNT' => MapSetting::PAC_COUNT,
			'BBOX' => sprintf('%F,%F,%F,%F,urn:ogc:def:crs:EPSG::4326', $box[1], $box[0], $box[3], $box[2])
		]);

		$parcels = ($json === NULL) ? [] : self::parse($json);

		// Un registre muet coûte déjà un délai : la table des cultures ne se demande que pour des parcelles à nommer
		if($parcels !== []) {

			$crops = self::getCrops();

			foreach($parcels as $position => $parcel) {
				$parcels[$position]['crop'] = ($parcel['crop'] === NULL) ? NULL : ($crops[$parcel['crop']] ?? NULL);
			}

		}

		return [
			'box' => $box,
			'parcels' => $parcels
		];

	}

	/**
	 * Une parcelle se rend par le seul anneau extérieur de son plus grand morceau : une parcelle de la Carte n'a qu'un anneau, et
	 * le trou d'une parcelle déclarée est une autre parcelle, qu'un clic ajoute s'il le faut. Tout ce qui sort est un nombre, ou un
	 * code de culture de trois lettres ou chiffres (getFeatureCrop()).
	 */
	public static function parse(string $json): array {

		$parcels = [];

		foreach(self::getFeatures($json) as $feature) {

			$ring = self::getFeatureRing($feature);

			if($ring !== []) {
				$parcels[] = [
					'id' => (int)($feature['properties']['num_parcel'] ?? 0),
					'crop' => self::getFeatureCrop($feature),
					'coordinates' => $ring
				];
			}

		}

		return $parcels;

	}

	/**
	 * Les libellés de toutes les cultures du registre, par code, lus dans sa table des cultures, PAC_CROPS_COUNT au plus, et gardés une journée.
	 * Le libellé de l'IGN se coupe avant sa précision entre parenthèses, qui ne tient pas dans la cote du survol, et s'écrit sans
	 * accent circonflexe sur le i, comme le dépôt écrit « maraichage ». Un service qui ne répond pas rend une liste vide, et le survol
	 * ne dit alors que la surface.
	 */
	public static function getCrops(): array {

		$crops = \Cache::redis()->get(self::CROPS_KEY);

		if(is_array($crops)) {
			return $crops;
		}

		$json = self::call([
			'SERVICE' => 'WFS',
			'VERSION' => '2.0.0',
			'REQUEST' => 'GetFeature',
			'TYPENAMES' => MapSetting::PAC_CROPS_LAYER,
			'COUNT' => MapSetting::PAC_CROPS_COUNT,
			'OUTPUTFORMAT' => 'application/json'
		]);

		$crops = ($json === NULL) ? [] : self::parseCrops($json);

		\Cache::redis()->set(self::CROPS_KEY, $crops, ($crops === []) ? self::CROPS_MISSING : 86400);

		return $crops;

	}

	public static function parseCrops(string $json): array {

		$crops = [];

		foreach(self::getFeatures($json) as $feature) {

			$code = $feature['properties']['code_culture'] ?? NULL;
			$label = $feature['properties']['libelle_culture'] ?? NULL;

			if(is_string($code) and is_string($label)) {
				$crops[$code] = str_replace('î', 'i', explode(' (', $label)[0]);
			}

		}

		return $crops;

	}

	protected static function getFeatures(string $json): array {

		$features = json_decode($json, TRUE)['features'] ?? NULL;

		return is_array($features) ? array_filter($features, 'is_array') : [];

	}

	/* Le code de culture de l'entité, trois lettres ou chiffres, ou NULL : rien d'autre de ce champ ne sort du registre */
	protected static function getFeatureCrop(array $feature): ?string {

		$crop = $feature['properties']['code_cultu'] ?? NULL;

		return (is_string($crop) and preg_match('/^[A-Z0-9]{3}$/', $crop) === 1) ? $crop : NULL;

	}

	/* Le plus grand anneau extérieur de l'entité, en sommets distincts, ou rien quand elle n'en a pas trois */
	protected static function getFeatureRing(array $feature): array {

		$polygons = match($feature['geometry']['type'] ?? NULL) {
			'Polygon' => [$feature['geometry']['coordinates']],
			'MultiPolygon' => $feature['geometry']['coordinates'],
			default => []
		};

		$ring = [];

		foreach($polygons as $polygon) {

			$outer = self::getRing($polygon[0] ?? NULL);

			if(MapLib::getArea($outer) > MapLib::getArea($ring)) {
				$ring = $outer;
			}

		}

		return (count($ring) < 3) ? [] : $ring;

	}

	/* L'anneau en sommets distincts, ceux qui tombent à moins de MapSetting::PAC_MERGE du sommet gardé avant eux fondus en lui, et
	   le dernier, qui referme l'anneau sur le premier, retiré comme la Carte les tient (MapLib::getVertices()) */
	protected static function getRing(mixed $points): array {

		if(is_array($points) === FALSE) {
			return [];
		}

		$ring = [];

		foreach($points as $point) {

			if(is_array($point) === FALSE or is_numeric($point[0] ?? NULL) === FALSE or is_numeric($point[1] ?? NULL) === FALSE) {
				return [];
			}

			$point = [(float)$point[0], (float)$point[1]];

			if($ring === [] or MapLib::getDistance($ring[count($ring) - 1], $point) >= MapSetting::PAC_MERGE) {
				$ring[] = $point;
			}

		}

		while(count($ring) > 1 and MapLib::getDistance($ring[count($ring) - 1], $ring[0]) < MapSetting::PAC_MERGE) {
			array_pop($ring);
		}

		return $ring;

	}

	/**
	 * L'année de la déclaration que le registre publie, lue dans son titre (« Registre parcellaire graphique (RPG) 2025 : … ») et
	 * gardée une journée : le registre change une fois l'an. NULL quand le service ne la dit pas, et la Carte n'affiche alors pas
	 * d'année. Ce silence se garde YEAR_MISSING secondes, noté 0 : sans lui, chaque clic d'un registre en panne attendrait deux
	 * délais, celui de l'année et celui des parcelles.
	 */
	public static function getYear(): ?int {

		$year = \Cache::redis()->get(self::YEAR_KEY);

		if(is_numeric($year)) {
			return ((int)$year > 0) ? (int)$year : NULL;
		}

		// Filtrée sur l'espace du registre, la description du service pèse 134 Ko au lieu de 5,6 Mo
		$xml = self::call([
			'SERVICE' => 'WFS',
			'VERSION' => '2.0.0',
			'REQUEST' => 'GetCapabilities',
			'namespace' => strstr(MapSetting::PAC_LAYER, ':', TRUE)
		]);

		if(
			$xml === NULL or
			preg_match('/<Name>'.preg_quote(MapSetting::PAC_LAYER, '/').'<\/Name>\s*<Title>[^<]*\b(20[0-9]{2})\b/', $xml, $match) !== 1
		) {
			\Cache::redis()->set(self::YEAR_KEY, 0, self::YEAR_MISSING);
			return NULL;
		}

		\Cache::redis()->set(self::YEAR_KEY, (int)$match[1], 86400);

		return (int)$match[1];

	}

	/* Le carré de $distance mètres de demi-côté autour du point : ouest, sud, est, nord */
	protected static function getBox(array $lngLat, int $distance): array {

		return [
			MapLib::getDestination($lngLat, $distance, -90)[0],
			MapLib::getDestination($lngLat, $distance, 180)[1],
			MapLib::getDestination($lngLat, $distance, 90)[0],
			MapLib::getDestination($lngLat, $distance, 0)[1]
		];

	}

	/* La réponse du service, ou NULL quand il ne répond pas, trop tard ou par une erreur */
	protected static function call(array $arguments): ?string {

		$curl = new \util\CurlLib();

		try {

			$response = $curl->exec(MapSetting::PAC_URL, $arguments, options: [
				CURLOPT_TIMEOUT => MapSetting::PAC_TIMEOUT,
				CURLOPT_CONNECTTIMEOUT => MapSetting::PAC_TIMEOUT
			]);

		} catch(\Exception) {
			return NULL;
		}

		if($curl->getLastInfos()['httpCode'] !== 200 or is_string($response) === FALSE) {
			return NULL;
		}

		return $response;

	}

}
?>
