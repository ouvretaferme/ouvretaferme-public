<?php
namespace map;

/**
 * Test bout-en-bout de l'API publique map\Plot (production/map/api/Plot_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=map file=PlotApiAi
 */
class PlotApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	/** Crée une parcelle d'accueil par l'API, pour ne dépendre d'aucune donnée en place. */
	// Une parcelle tracée autour des contours d'abri des tests : seul un abri d'une parcelle tracée tire ses dimensions de son contour
	const array ZONE_DRAWN = [[3.09, 45.59], [3.14, 45.59], [3.14, 45.67], [3.09, 45.67]];

	private function createZone(?int $seasonFirst = NULL, ?int $seasonLast = NULL, ?array $coordinates = NULL): int {

		$create = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle hôte',
			'area' => 10000,
			'coordinates' => $coordinates,
			'seasonFirst' => $seasonFirst
		]);

		if($seasonLast !== NULL) {

			$this->callApi('PUT', 'zone/{id}', body: [
				'properties' => ['seasonLast'],
				'seasonLast' => $seasonLast
			], get: ['id' => $create['body']['id']]);

		}

		return $create['body']['id'];

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'plotList', get: ['season' => date('Y'), 'start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$zone = $this->createZone();

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $zone,
			'name' => 'Jardin API',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Jardin API', $create['body']['name'] ?? NULL);
		$this->assertEquals($zone, $create['body']['zone'] ?? NULL);
		$this->assertEquals(Plot::OPEN_FIELD, $create['body']['mode'] ?? NULL);
		$this->assertEquals(300, $create['body']['area'] ?? NULL);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'plot/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Jardin API', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['name'],
			'name' => 'Jardin API 2'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Jardin API 2', $update['body']['name'] ?? NULL);

		$delete = $this->callApi('DELETE', 'plot/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(404, $this->callApi('GET', 'plot/{id}', get: ['id' => $id])['status']);

	}

	/**
	 * Sous abri la surface n'est pas saisie mais dérivée des dimensions (Plot::build(), area.prepare) :
	 * la valeur envoyée par le client est écrasée, y compris si elle est absente.
	 */
	public function testGreenhouseDerivesArea(): void {

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(),
			'name' => 'Abri API',
			'mode' => Plot::GREENHOUSE,
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(Plot::GREENHOUSE, $create['body']['mode'] ?? NULL);
		$this->assertEquals(240, $create['body']['area'] ?? NULL);
		$this->assertEquals(30.0, $create['body']['length'] ?? NULL);

	}

	/** En plein champ les dimensions n'ont pas de sens : length.mode/width.mode les écartent. */
	public function testOpenFieldDropsDimensions(): void {

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(),
			'name' => 'Jardin plein champ',
			'mode' => Plot::OPEN_FIELD,
			'length' => 30,
			'width' => 8,
			'area' => 500,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(500, $create['body']['area'] ?? NULL);
		// ?? masquerait la valeur cherchée : c'est bien NULL, pas l'absence de la clé, qu'on vérifie
		$this->assertTrue(array_key_exists('length', $create['body']));
		$this->assertEquals(NULL, $create['body']['length']);
		$this->assertEquals(NULL, $create['body']['width']);

	}

	/**
	 * Plot::build() vérifie la parcelle contre la structure du jardin, pas par un simple contrôle de
	 * droits : un membre de deux structures ne doit pas pouvoir poser un jardin dans celle de l'autre.
	 */
	public function testZoneOfAnotherStructureIsRejected(): void {

		$eZoneOther = Zone::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->get();

		if($this->assertDecorPresent($eZoneOther->notEmpty(), 'une zone d\'une autre structure que la '.$this->eStructure['id']) === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'plot', body: [
			'zone' => $eZoneOther['id'],
			'name' => 'Jardin intrus',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * Aucun écran ne déplace un jardin d'une parcelle à l'autre, l'API non plus (PlotLib::getPropertiesUpdate()) : la nommer dans
	 * une modification partielle est refusé, la poster dans une modification complète est sans effet, comme `mode`. Planches et
	 * traits restent avec le jardin.
	 */
	public function testZoneIsNotUpdated(): void {

		$zoneSource = $this->createZone();
		$zoneTarget = $this->createZone();

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $zoneSource,
			'name' => 'Jardin qui reste',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$partial = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['zone', 'seasonFirst', 'seasonLast'],
			'zone' => $zoneTarget,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		], get: ['id' => $id]);

		$this->assertEquals(400, $partial['status']);
		$this->assertEquals('property_unknown', $partial['body'][0]['code'] ?? NULL);

		$complete = $this->callApi('PUT', 'plot/{id}', body: [
			'zone' => $zoneTarget,
			'name' => 'Jardin qui reste',
			'coordinates' => NULL,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		], get: ['id' => $id]);

		$this->assertEquals(200, $complete['status'], json_encode($complete['body']));
		$this->assertEquals($zoneSource, $complete['body']['zone'] ?? NULL);

		$this->assertEquals($zoneSource, Plot::model()->select('zone')->whereId($id)->get()['zone']['id']);

		$zonesBed = Bed::model()
			->select(['zone'])
			->wherePlot($id)
			->getCollection()
			->makeArray(fn($eBed) => $eBed['zone']['id']);

		$this->assertTrue($zonesBed !== [], 'le jardin crée la planche qui le remplit');
		$this->assertEquals([$zoneSource], array_values(array_unique($zonesBed)));

	}

	/** Les saisons vont ensemble : leur cohérence n'est jugée que sur `seasonLast`, cf Plot_0_1betaApi::getStandalonePropertiesUpdate(). */
	public function testStandaloneSeasonFirstIsRejected(): void {

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(),
			'name' => 'Jardin daté',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$response = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['seasonFirst'],
			'seasonFirst' => (int)date('Y')
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Créer une parcelle crée aussi un jardin qui la remplit (PlotLib::createFromZone()), sans nom.
	 * Contrairement aux planches de remplissage il reste exposé : une planche saisie par
	 * l'utilisateur s'y rattache, et l'écarter ferait pointer bed.plot sur une ressource absente.
	 */
	public function testListByZoneIncludesFillPlot(): void {

		$zone = $this->createZone();

		$before = $this->callApi('GET', 'plotList', get: ['season' => date('Y'), 'zone' => $zone]);

		$this->assertEquals(200, $before['status']);
		$this->assertEquals(1, $before['body']['count']);

		$this->callApi('POST', 'plot', body: [
			'zone' => $zone,
			'name' => 'Jardin ajouté',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$after = $this->callApi('GET', 'plotList', get: ['season' => date('Y'), 'zone' => $zone]);

		$this->assertEquals(2, $after['body']['count']);

	}

	/** La corrélation coordinates vaut aussi pour le jardin : un tableau de points fait l'aller-retour. */
	public function testCoordinatesRoundTrip(): void {

		$coordinates = [
			[3.128768, 45.664010],
			[3.128903, 45.663964],
			[3.129082, 45.663926],
			[3.128768, 45.664010]
		];

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(),
			'name' => 'Jardin tracé',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);

		$read = $this->callApi('GET', 'plot/{id}', get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(count($coordinates), count($read['body']['coordinates'] ?? []));

	}

	/**
	 * Un contour compte trois sommets distincts au moins : l'anneau peut rester ouvert, la base le referme,
	 * mais deux points, un point hors du globe ou un contour démesuré sont refusés au lieu de lever en SQL.
	 */
	public function testCoordinatesRing(): void {

		$zone = $this->createZone();

		$create = fn(?array $coordinates) => $this->callApi('POST', 'plot', body: [
			'zone' => $zone,
			'name' => 'Contour à juger',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 5000,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL
		]);

		$open = $create([[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]]);

		$this->assertEquals(201, $open['status'], 'trois sommets sans point de fermeture');

		$read = $this->callApi('GET', 'plot/{id}', get: ['id' => $open['body']['id']]);

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
		$erase = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['area', 'coordinates'],
			'area' => 5000,
			'coordinates' => NULL
		], get: ['id' => $open['body']['id']]);

		$this->assertEquals(200, $erase['status']);
		$this->assertEquals(NULL, $this->callApi('GET', 'plot/{id}', get: ['id' => $open['body']['id']])['body']['coordinates']);

	}

	/**
	 * En plein champ, un contour posté donne sa surface, quelle que soit celle qu'envoie le client et l'ordre dans lequel une
	 * mise à jour partielle les liste (Plot::properties(), étape `drawn`).
	 */
	public function testDrawnAreaComesFromTheContour(): void {

		$triangle = [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]];
		$rectangle = [[3.1000, 45.6000], [3.1003, 45.6000], [3.1003, 45.6001], [3.1000, 45.6001]];

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(),
			'name' => 'Jardin mesuré',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 5000,
			'coordinates' => $triangle,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(MapLib::getArea($triangle), $create['body']['area'] ?? NULL);

		$update = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['area', 'coordinates'],
			'area' => 7000,
			'coordinates' => $rectangle
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(MapLib::getArea($rectangle), $update['body']['area'] ?? NULL);

		$alone = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['coordinates'],
			'coordinates' => $triangle
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $alone['status'], 'un contour sans la surface qu\'il donne');
		$this->assertEquals('property_together', $alone['body'][0]['code'] ?? NULL);

	}

	/**
	 * Un abri tracé tire ses dimensions de son contour, et celles qu'on lui envoie sont remplacées. Le contour se poste seul et
	 * entraîne ses dimensions, les dimensions se postent avec la surface (Plot_0_1betaApi::getStandalonePropertiesUpdate()). Sans
	 * contour, il garde celles qu'on lui donne, et un contour trop étroit pour en donner est refusé.
	 */
	public function testGreenhouseDimensionsFollowItsContour(): void {

		$rectangle = [[3.1000, 45.6000], [3.1003, 45.6000], [3.1003, 45.6001], [3.1000, 45.6001]];
		$triangle = [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]];

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(coordinates: self::ZONE_DRAWN),
			'name' => 'Abri placé',
			'mode' => Plot::GREENHOUSE,
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertEquals(240, $create['body']['area'] ?? NULL, 'sans contour, la surface vient des dimensions envoyées');

		$untraced = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['coordinates', 'length', 'width', 'area'],
			'coordinates' => NULL,
			'length' => 30,
			'width' => 10,
			'area' => NULL
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $untraced['status'], json_encode($untraced['body']));
		$this->assertEquals(300, $untraced['body']['area'] ?? NULL);

		$dimensions = MapLib::getDimensions($rectangle);

		$traced = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['coordinates', 'length', 'width', 'area'],
			'coordinates' => $rectangle,
			'length' => 30,
			'width' => 10,
			'area' => NULL
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $traced['status'], json_encode($traced['body']));
		$this->assertEquals($dimensions[0], $traced['body']['length'] ?? NULL, 'les dimensions envoyées sont remplacées par celles du contour');
		$this->assertEquals($dimensions[1], $traced['body']['width'] ?? NULL);
		$this->assertEquals((int)round($dimensions[0] * $dimensions[1]), $traced['body']['area'] ?? NULL, 'la surface suit');

		$alone = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['coordinates'],
			'coordinates' => $triangle
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $alone['status'], json_encode($alone['body']));
		$this->assertEquals(MapLib::getDimensions($triangle), [$alone['body']['length'] ?? NULL, $alone['body']['width'] ?? NULL], 'un contour posté seul entraîne ses dimensions');

		$dimensionsAlone = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['length', 'width', 'area'],
			'length' => 30,
			'width' => 10,
			'area' => NULL
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $dimensionsAlone['status'], json_encode($dimensionsAlone['body']));
		$this->assertEquals(MapLib::getDimensions($triangle), [$dimensionsAlone['body']['length'] ?? NULL, $dimensionsAlone['body']['width'] ?? NULL], 'le contour enregistré l\'emporte sur des dimensions postées seules');

		// 30 m sur 30 cm
		$narrow = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['coordinates'],
			'coordinates' => [[3.1000, 45.6000], [3.1004, 45.6000], [3.1004, 45.6000027], [3.1000, 45.6000027]]
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $narrow['status'], 'un contour plus étroit que ce qu\'admet le schéma');
		$this->assertEquals(['Plot::length.drawn'], array_column($narrow['body'], 'code'), 'un seul message, qui dit de redessiner');

		$createTraced = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(coordinates: self::ZONE_DRAWN),
			'name' => 'Abri tracé',
			'mode' => Plot::GREENHOUSE,
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => $triangle,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $createTraced['status'], json_encode($createTraced['body']));
		$this->assertEquals(MapLib::getDimensions($triangle), [$createTraced['body']['length'] ?? NULL, $createTraced['body']['width'] ?? NULL], 'un contour libre se mesure sur le rectangle qui l\'englobe');

	}

	/** Dans une parcelle non tracée, un abri garde les dimensions qu'on lui donne, même s'il porte un contour d'un ancien tracé. */
	public function testUndrawnZoneKeepsTypedDimensions(): void {

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(),
			'name' => 'Abri sans carte',
			'mode' => Plot::GREENHOUSE,
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => [[3.1000, 45.6000], [3.1003, 45.6000], [3.1003, 45.6001], [3.1000, 45.6001]],
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertEquals([30.0, 8.0], [$create['body']['length'] ?? NULL, $create['body']['width'] ?? NULL], 'à la création');

		$update = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['length', 'width', 'area'],
			'length' => 12,
			'width' => 5,
			'area' => NULL
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertEquals([12.0, 5.0], [$update['body']['length'] ?? NULL, $update['body']['width'] ?? NULL], 'à la modification');

	}

	/** Un abri déjà enregistré sur un contour trop étroit se renomme sans être redessiné : son contour n'a pas bougé. */
	public function testNarrowGreenhouseKeepsItsDimensions(): void {

		// 30 m sur 30 cm, refermé comme la base l'enregistre
		$narrow = [[3.1000, 45.6000], [3.1004, 45.6000], [3.1004, 45.6000027], [3.1000, 45.6000027], [3.1000, 45.6000]];

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone(coordinates: self::ZONE_DRAWN),
			'name' => 'Abri étroit',
			'mode' => Plot::GREENHOUSE,
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status'], json_encode($create['body']));

		// Le décor est l'abri que le refus `drawn` empêche désormais de créer, tracé avant lui : build() le refuserait, c'est l'état éprouvé
		Plot::model()->update(new Plot(['id' => $create['body']['id']]), ['coordinates' => $narrow]);

		$renamed = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['name', 'coordinates'],
			'name' => 'Abri renommé',
			'coordinates' => array_slice($narrow, 0, 4)
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $renamed['status'], json_encode($renamed['body']));
		$this->assertEquals([30.0, 8.0], [$renamed['body']['length'] ?? NULL, $renamed['body']['width'] ?? NULL], 'le contour reposté à l\'identique garde les dimensions');

		$dimensions = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['length', 'width', 'area'],
			'length' => 20,
			'width' => 6,
			'area' => NULL
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $dimensions['status'], json_encode($dimensions['body']));
		$this->assertEquals([30.0, 8.0], [$dimensions['body']['length'] ?? NULL, $dimensions['body']['width'] ?? NULL], 'sans contour posté, rien ne bouge');

		$moved = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['coordinates'],
			'coordinates' => array_map(fn(array $point) => [$point[0] + 0.0001, $point[1]], $narrow)
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(['Plot::length.drawn'], array_column($moved['body'], 'code'), 'un contour qui change se redessine');

	}

	/**
	 * Le jardin des planches posées à même la parcelle se lit, mais ne se modifie ni ne se supprime : la carte ne lui offre aucun
	 * geste, il suit sa parcelle (Plot::acceptUpdate(), acceptDelete()).
	 */
	public function testZoneFillIsReadOnly(): void {

		$zoneId = $this->createZone();

		$ePlotFill = Plot::model()
			->select(Plot::getSelection())
			->whereZone($zoneId)
			->whereZoneFill(TRUE)
			->get();

		if($this->assertDecorPresent($ePlotFill->notEmpty(), 'la parcelle crée le jardin de ses planches') === FALSE) {
			return;
		}

		$read = $this->callApi('GET', 'plot/{id}', get: ['id' => $ePlotFill['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertTrue($read['body']['zoneFill'] ?? NULL);

		$partial = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['name'],
			'name' => 'Jardin renommé'
		], get: ['id' => $ePlotFill['id']]);

		$this->assertEquals(412, $partial['status']);
		$this->assertEquals('accept_update', $partial['body'][0]['code'] ?? NULL);

		$complete = $this->callApi('PUT', 'plot/{id}', body: [
			'name' => 'Jardin renommé',
			'coordinates' => NULL,
			'length' => NULL,
			'width' => NULL,
			'area' => 500,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		], get: ['id' => $ePlotFill['id']]);

		$this->assertEquals(412, $complete['status']);
		$this->assertEquals('accept_update', $complete['body'][0]['code'] ?? NULL);

		$delete = $this->callApi('DELETE', 'plot/{id}', get: ['id' => $ePlotFill['id']]);

		$this->assertEquals(412, $delete['status']);
		$this->assertEquals('accept_delete', $delete['body'][0]['code'] ?? NULL);

		$ePlotAfter = Plot::model()
			->select(['name', 'area', 'updatedAt'])
			->whereId($ePlotFill['id'])
			->get();

		$this->assertTrue($ePlotAfter->notEmpty(), 'le jardin de la parcelle est toujours là');
		$this->assertEquals($ePlotFill['name'], $ePlotAfter['name']);
		$this->assertEquals($ePlotFill['area'], $ePlotAfter['area']);
		$this->assertEquals($ePlotFill['updatedAt'], $ePlotAfter['updatedAt']);

	}

	/**
	 * Le jardin des planches posées à même la parcelle en a la surface (Plot::properties(), étape `filled` de `area`), que
	 * ZoneLib::update() lui reporte. L'étape juge l'élément, quelle que soit la porte qui le construit : elle s'éprouve sur lui.
	 */
	public function testZoneFillTakesTheAreaOfItsZone(): void {

		$zoneId = $this->createZone();

		$ePlotFill = Plot::model()
			->select(Plot::getSelection())
			->whereZone($zoneId)
			->whereZoneFill(TRUE)
			->get();

		if($this->assertDecorPresent($ePlotFill->notEmpty(), 'la parcelle crée le jardin de ses planches') === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$ePlotFill->build(['area'], ['area' => 500], new \PropertyList('update'));

		$this->assertTrue($fw->ok());
		$this->assertEquals(10000, $ePlotFill['area']);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'plot/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
