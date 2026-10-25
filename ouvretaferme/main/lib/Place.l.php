<?php
namespace main;

class PlaceLib {

	public static function searchCitiesByName(\structure\Structure $eStructure, string $name): array {

		$name = trim($name);

		if(
			$eStructure->notEmpty() and
			$eStructure['legalCountry']['id'] === \user\UserSetting::FR and
			preg_match('/^\d{5}$/', $name)
		) {
			return self::searchCitiesByPostcode($name);
		}

		$features = \map\MapboxLib::getGeocodingPlaces($name, self::getCountryArguments($eStructure));
		$countries = self::getCountriesByFeatures($features);

		$locations = [];

		foreach($features as $location) {

			$context = $location['properties']['context'];
			$countryCode = strtoupper($context['country']['country_code'] ?? '');
			$country = $countries[$countryCode] ?? NULL;

			$locations[] = [
				'name' => $location['properties']['name'],
				'address' => $location['properties']['full_address'],
				'lngLat' => [
					$location['properties']['coordinates']['longitude'],
					$location['properties']['coordinates']['latitude'],
				],
				'department' => self::getDepartment($countryCode, $context),
				'country' => $country
			];

		}

		return $locations;

	}

	public static function searchCitiesByPostcode(string $postcode): array {

		$values = new \util\CurlLib()->exec('https://geo.api.gouv.fr/communes', [
			'codePostal' => $postcode,
			'fields' => 'nom,centre,departement',
			'format' => 'json'
		]);

		$locations = [];

		foreach(json_decode($values, TRUE) ?: [] as $city) {

			if(isset($city['centre']['coordinates']) === FALSE) {
				continue;
			}

			$locations[] = [
				'name' => $city['nom'],
				'address' => $city['nom'].', '.$city['departement']['nom'].', France',
				'lngLat' => $city['centre']['coordinates'],
				'department' => $city['departement']['code'],
				'country' => \user\UserSetting::FR
			];

		}

		return $locations;

	}

	public static function searchAddressesByName(\structure\Structure $eStructure, string $name): array {

		$features = \map\MapboxLib::getGeocodingPlaces(trim($name), self::getCountryArguments($eStructure) + [
			'types' => 'address',
			'limit' => 10
		]);
		$countries = self::getCountriesByFeatures($features);

		$addresses = [];

		foreach($features as $location) {

			$context = $location['properties']['context'];

			$addresses[] = [
				'street' => $context['address']['name'] ?? $location['properties']['name'],
				'address' => $location['properties']['full_address'],
				'postcode' => $context['postcode']['name'] ?? NULL,
				'city' => $context['place']['name'] ?? NULL,
				'country' => $countries[strtoupper($context['country']['country_code'] ?? '')] ?? NULL
			];

		}

		return $addresses;

	}

	/**
	 * Mapbox nomme un département d'outre-mer comme un pays, sans région : son numéro se lit dans le pays.
	 * Hors de France, la région de Mapbox est une province (Namur donne WNA), pas un département.
	 */
	private static function getDepartment(string $countryCode, array $context): ?string {

		return match($countryCode) {
			'FR' => $context['region']['region_code'] ?? NULL,
			'GP' => '971',
			'MQ' => '972',
			'GF' => '973',
			'RE' => '974',
			'PM' => '975',
			'YT' => '976',
			default => NULL
		};

	}

	private static function getCountryArguments(\structure\Structure $eStructure): array {

		if($eStructure->empty()) {
			return [];
		}

		\user\Country::model()
			->select('code')
			->get($eStructure['legalCountry']);

		return ['country' => $eStructure['legalCountry']['code']];

	}

	private static function getCountriesByFeatures(array $features): array {

		$codes = [];

		foreach($features as $location) {
			if(isset($location['properties']['context']['country']['country_code'])) {
				$codes[] = strtoupper($location['properties']['context']['country']['country_code']);
			}
		}

		if($codes === []) {
			return [];
		}

		$countries = [];

		foreach(\user\Country::model()
			->select('id', 'code')
			->whereCode('IN', array_unique($codes))
			->getCollection() as $eCountry) {
			$countries[$eCountry['code']] = $eCountry['id'];
		}

		return $countries;

	}

	public static function searchCityByLngLat(array $lngLat): ?string {

		$location = \map\MapboxLib::getGeocodingPlaceByLngLat($lngLat);

		return $location['properties']['name'] ?? NULL;

	}

	const CODES_UNKNOWN = ['placeCity' => NULL, 'placeDepartment' => NULL, 'placeEpci' => NULL];

	private static ?\Closure $fetcher = NULL;

	/**
	 * Les tests répondent à la place de geo.api.gouv.fr : `fn(array $lngLat): array`, qui rend la
	 * liste des communes décodée. Elle voit chaque appel, et peut donc dire qu'aucun n'est parti.
	 */
	public static function setFetcher(?\Closure $fetcher): void {
		self::$fetcher = $fetcher;
	}

	/**
	 * La commune, le département et l'intercommunalité d'un point, `CODES_UNKNOWN` quand
	 * geo.api.gouv.fr ne répond pas, ne répond rien (point hors de France) ou répond une forme
	 * inattendue.
	 *
	 * L'appel part pendant l'enregistrement du lieu, qui ne doit jamais échouer ni longtemps attendre
	 * à cause de lui : d'où un délai de 6 s au lieu des 30 s de `CurlLib`, et aucune exception rendue,
	 * le rattrapage de la nuit `structure/cron/placeCodesAi` refaisant ce qui a manqué.
	 * Le délai de connexion compte la résolution du nom, qui prend plusieurs secondes sur un résolveur
	 * lent : 5 s la laissent aboutir, et 6 s restent l'attente la plus longue qu'on accepte pendant un
	 * enregistrement.
	 * Le point part arrondi à 3 décimales, une centaine de mètres, ce qui suffit à trouver la
	 * commune sans donner la ferme au mètre.
	 */
	public static function getCodesByLngLat(array $lngLat): array {

		if(self::$fetcher !== NULL) {
			return self::extractCodes((self::$fetcher)($lngLat));
		}

		try {

			$curl = new \util\CurlLib();

			$values = $curl->exec('https://geo.api.gouv.fr/communes', [
				'lon' => round($lngLat[0], 3),
				'lat' => round($lngLat[1], 3),
				'fields' => 'code,codeDepartement,codeEpci',
				'format' => 'json'
			], options: [
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 6,
				CURLOPT_FOLLOWLOCATION => FALSE
			]);

		} catch(\Exception) {
			return self::CODES_UNKNOWN;
		}

		if($curl->getLastInfos()['httpCode'] !== 200) {
			return self::CODES_UNKNOWN;
		}

		$communes = json_decode($values, TRUE);

		return is_array($communes) ? self::extractCodes($communes) : self::CODES_UNKNOWN;

	}

	/**
	 * Les codes de la première commune d'une réponse de geo.api.gouv.fr, chacun contrôlé dans sa forme
	 * avant d'entrer en base : la réponse vient d'un tiers. Le département est l'un des 101, sans les
	 * collectivités d'outre-mer (975, 977…) qui n'en sont pas. Une commune sans intercommunalité
	 * (L'Île-d'Yeu, Bréhat) garde son département.
	 */
	public static function extractCodes(array $communes): array {

		$commune = $communes[0] ?? NULL;

		if(
			is_array($commune) === FALSE or
			is_string($commune['code'] ?? NULL) === FALSE or
			is_string($commune['codeDepartement'] ?? NULL) === FALSE or
			preg_match('/^[0-9][0-9AB][0-9]{3}$/', $commune['code']) === 0 or
			preg_match('/^(0[1-9]|1[0-9]|2[1-9]|2A|2B|[3-8][0-9]|9[0-5]|97[1-46])$/', $commune['codeDepartement']) === 0
		) {
			return self::CODES_UNKNOWN;
		}

		$epci = $commune['codeEpci'] ?? NULL;

		return [
			'placeCity' => $commune['code'],
			'placeDepartment' => $commune['codeDepartement'],
			'placeEpci' => (is_string($epci) and preg_match('/^[0-9]{9}$/', $epci) === 1) ? $epci : NULL,
		];

	}

	public static function searchAltitudeByLngLat(array $lngLat): ?int {

		$values = new \util\CurlLib()->exec('https://api.open-meteo.com/v1/elevation', [
			'longitude' => $lngLat[0],
			'latitude' => $lngLat[1]
		]);

		$altitude = json_decode($values, TRUE)['elevation'][0] ?? NULL;

		// La précision du modèle numérique de terrain ne justifie pas mieux que la dizaine de mètres
		return $altitude === NULL ? NULL : (int)ceil($altitude / 10) * 10;

	}

}
