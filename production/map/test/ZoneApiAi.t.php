<?php
namespace map;

/**
 * Test bout-en-bout de l'API publique map\Zone (production/map/api/Zone_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=map file=ZoneApiAi
 */
class ZoneApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'zoneList', get: ['season' => date('Y'), 'start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle API',
			'area' => 5000,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Parcelle API', $create['body']['name'] ?? NULL);
		$this->assertEquals(5000, $create['body']['area'] ?? NULL);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'zone/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Parcelle API', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'zone/{id}', body: [
			'name' => 'Parcelle API 2',
			'area' => 6000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Parcelle API 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(6000, $update['body']['area'] ?? NULL);

		$delete = $this->callApi('DELETE', 'zone/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(404, $this->callApi('GET', 'zone/{id}', get: ['id' => $id])['status']);

	}

	/** Un polygone est un tableau de points, ni une chaîne JSON ni du WKT : il doit faire l'aller-retour tel quel. */
	public function testCoordinatesRoundTrip(): void {

		$coordinates = [
			[3.128768, 45.664010],
			[3.128903, 45.663964],
			[3.129082, 45.663926],
			[3.128768, 45.664010]
		];

		$create = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle tracée',
			'area' => 5000,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);

		$read = $this->callApi('GET', 'zone/{id}', get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(count($coordinates), count($read['body']['coordinates'] ?? []));

	}

	/**
	 * Une parcelle bornée à une saison future ne doit pas ressortir sur une saison antérieure.
	 * Le filtre ids borne la liste à la donnée créée ici, sans dépendre de ce qui est déjà en base.
	 */
	public function testSeasonFilters(): void {

		$create = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle 2030',
			'area' => 5000,
			'coordinates' => NULL,
			'seasonFirst' => 2030
		]);

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$before = $this->callApi('GET', 'zoneList', get: ['season' => 2025, 'ids' => [$id]]);
		$after = $this->callApi('GET', 'zoneList', get: ['season' => 2030, 'ids' => [$id]]);

		$this->assertEquals(0, $before['body']['count']);
		$this->assertEquals(1, $after['body']['count']);

	}

	/**
	 * Un contour compte trois sommets distincts au moins : l'anneau peut rester ouvert, la base le referme,
	 * mais deux points, un point hors du globe ou un contour démesuré sont refusés au lieu de lever en SQL.
	 */
	public function testCoordinatesRing(): void {

		$create = fn(?array $coordinates) => $this->callApi('POST', 'zone', body: [
			'name' => 'Contour à juger',
			'area' => 5000,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL
		]);

		$open = $create([[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]]);

		$this->assertEquals(201, $open['status'], 'trois sommets sans point de fermeture');

		$read = $this->callApi('GET', 'zone/{id}', get: ['id' => $open['body']['id']]);

		$this->assertEquals(4, count($read['body']['coordinates'] ?? []), 'la base referme l\'anneau');

		$refused = [
			'aucun point' => [],
			'deux sommets' => [[3.128768, 45.664010], [3.128903, 45.663964]],
			'deux sommets et leur fermeture' => [[3.128768, 45.664010], [3.128903, 45.663964], [3.128768, 45.664010]],
			'trois points dont deux identiques' => [[3.128768, 45.664010], [3.128903, 45.663964], [3.128903, 45.663964]],
			'latitude hors du globe' => [[3.128768, 95.0], [3.128903, 45.663964], [3.129082, 45.663926]],
			'longitude hors du globe' => [[183.0, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]],
			'plus de sommets que le plafond' => array_map(fn(int $i) => [3 + $i / 100000, 45 + ($i % 2) / 100000], range(0, MapSetting::SHAPE_POINTS_MAX))
		];

		foreach($refused as $case => $coordinates) {

			$response = $create($coordinates);

			$this->assertEquals(400, $response['status'], $case);
			$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL, $case);

		}

		// Un polygone régulier : un tracé en dents de scie n'a pas de surface, et le schéma refuserait la surface nulle qu'il donne
		$max = $create(array_map(fn(int $i) => [
			3 + cos(2 * M_PI * $i / MapSetting::SHAPE_POINTS_MAX) / 1000,
			45 + sin(2 * M_PI * $i / MapSetting::SHAPE_POINTS_MAX) / 1000
		], range(1, MapSetting::SHAPE_POINTS_MAX)));

		$this->assertEquals(201, $max['status'], 'autant de sommets que le plafond');

		// Effacer un contour reste possible par l'API, qui le pose à NULL avec la surface que la forme gardera
		$erase = $this->callApi('PUT', 'zone/{id}', body: [
			'properties' => ['area', 'coordinates'],
			'area' => 5000,
			'coordinates' => NULL
		], get: ['id' => $open['body']['id']]);

		$this->assertEquals(200, $erase['status']);
		$this->assertEquals(NULL, $this->callApi('GET', 'zone/{id}', get: ['id' => $open['body']['id']])['body']['coordinates']);

	}

	/**
	 * Un contour posté donne sa surface, quelle que soit celle qu'envoie le client et l'ordre dans lequel une mise à jour
	 * partielle les liste (Zone::properties(), étape `drawn`).
	 */
	public function testDrawnAreaComesFromTheContour(): void {

		$triangle = [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]];
		$square = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010]];

		$create = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle mesurée',
			'area' => 5000,
			'coordinates' => $triangle,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(MapLib::getArea($triangle), $create['body']['area'] ?? NULL);

		$update = $this->callApi('PUT', 'zone/{id}', body: [
			'properties' => ['area', 'coordinates'],
			'area' => 7000,
			'coordinates' => $square
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(MapLib::getArea($square), $update['body']['area'] ?? NULL);

	}

	/** La surface et le contour se modifient ensemble, l'un ne se poste pas sans l'autre (Zone_0_1betaApi::getStandalonePropertiesUpdate()). */
	public function testAreaAndContourTravelTogether(): void {

		$create = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle non tracée',
			'area' => 5000,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$alone = [
			'contour seul' => ['coordinates' => [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]]],
			'surface seule' => ['area' => 6000]
		];

		foreach($alone as $case => $values) {

			$response = $this->callApi('PUT', 'zone/{id}', body: [
				'properties' => array_keys($values)
			] + $values, get: ['id' => $create['body']['id']]);

			$this->assertEquals(400, $response['status'], $case);
			$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL, $case);

		}

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'zone/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'zone', body: [
			'name' => '',
			'area' => 5000,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
