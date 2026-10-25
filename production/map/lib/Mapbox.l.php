<?php
namespace map;

class MapboxLib {

	public static function getGeocodingPlaces(string $query, array $arguments = []): array {

		$arguments += [
			'q' => $query,
			'autocomplete' => 'true',
			'language' => 'fr',
			'types' => 'place',
			'limit' => 20
		];

		$response = self::call('/search/geocode/v6/forward', $arguments);

		return $response['features'] ?? [];

	}

	public static function getGeocodingPlaceByLngLat(array $lngLat, array $arguments = []): ?array {

		$arguments += [
			'longitude' => $lngLat[0],
			'latitude' => $lngLat[1],
			'language' => 'fr',
			'types' => 'place',
			'limit' => 1
		];

		$response = self::call('/search/geocode/v6/reverse', $arguments);

		return $response['features'][0] ?? NULL;

	}

	protected static function call(string $uri, array $arguments = []): array {

		$arguments['access_token'] = MapSetting::$mapboxToken;

		$url = 'https://api.mapbox.com'.$uri;
		$values = new \util\CurlLib()->exec($url, $arguments);

		return json_decode($values, TRUE);

	}

}
