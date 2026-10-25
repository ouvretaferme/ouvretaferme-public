<?php
namespace map;

/**
 * Le relief sous le plan 3D (`ReliefLib`) : les tuiles qu'une structure demande, leur lecture au service de l'IGN, l'échec daté
 * qui attend 24 h, et ce que la scène reçoit. Le service n'est jamais appelé : `MapSetting::$reliefTransport` rend ici une
 * réponse écrite sur le modèle du WMS (64 × 64 float32 petit-boutistes, la première rangée au nord), une pente régulière dont
 * l'altitude se lit à la latitude.
 *
 * Les zones se posent sur la structure `FixtureAiLib::F1`, qui n'en a aucune, dans la transaction de chaque méthode.
 *
 * Lancement : dev/test package=map file=ReliefAi
 */
class ReliefAiTest extends \dev\ApiTest {

	// Un carré d'environ 78 m sur 111 m près de Clermont-Ferrand, et le même à l'ouest de Greenwich, près de Nantes
	const array SQUARE = [[3.099, 45.599], [3.1, 45.599], [3.1, 45.6], [3.099, 45.6], [3.099, 45.599]];
	const array SQUARE_WEST = [[-1.551, 47.199], [-1.55, 47.199], [-1.55, 47.2], [-1.551, 47.2], [-1.551, 47.199]];

	private \Closure $transport;

	// Les appels reçus par le faux service, une emprise par appel
	private array $calls = [];

	protected function getTransactionModel(): \ModuleModel {
		return new ReliefModel();
	}

	public function setUp(): void {

		parent::setUp();

		\user\ConnectionLib::setOnline($this->getAdmin());

		$this->transport = MapSetting::$reliefTransport;
		$this->calls = [];

		$this->answer(fn(float $lat) => 300 + ($lat - 45.599) * 110540 * .05);

	}

	public function tearDown(): void {

		MapSetting::$reliefTransport = $this->transport;

		parent::tearDown();

	}

	public function testTheTilesCoverTheZonesAndTheirReach(): void {

		$this->createZone(self::SQUARE);

		$tiles = ReliefLib::getTiles($this->eStructure);
		$keys = array_map(fn(array $tile) => $tile[0].'/'.$tile[1], $tiles);

		// Le coin sud-ouest de la zone et le point à 100 m au sud-ouest de ce coin
		$this->assertTrue(in_array($this->getTileKey(3.099, 45.599), $keys), 'la tuile sous la zone');
		$this->assertTrue(in_array($this->getTileKey(3.099 - 100 / 77900, 45.599 - 100 / 111200), $keys), 'la tuile à la portée du décor');
		$this->assertEquals($this->getTileKey(3.0995, 45.5995), $keys[0], 'la tuile sous la zone vient en premier');
		$this->assertEquals(count(array_unique($keys)), count($keys), 'chaque tuile une fois');

	}

	public function testTheTilesStopAtTheirCeilingNearestFirst(): void {

		// Un carré de deux kilomètres de côté demande plus de tuiles que le plafond
		$this->createZone([[3.09, 45.59], [3.115, 45.59], [3.115, 45.608], [3.09, 45.608], [3.09, 45.59]]);
		$this->createZone(self::SQUARE);

		$tiles = ReliefLib::getTiles($this->eStructure);

		$this->assertEquals(MapSetting::RELIEF_TILES_MAX, count($tiles), 'le plafond de tuiles');

	}

	public function testAStripOverDegreesStaysBounded(): void {

		// Une lanière d'un mètre de large sur dix degrés : son emprise couvre des millions de tuiles
		$this->createZone([[-1.0, 43.0], [9.0, 49.0], [9.00001, 49.0], [-0.99999, 43.0], [-1.0, 43.0]]);

		$started = microtime(TRUE);
		$tiles = ReliefLib::getTiles($this->eStructure);

		$this->assertEquals(MapSetting::RELIEF_TILES_MAX, count($tiles), 'le plafond de tuiles');
		$this->assertTrue(microtime(TRUE) - $started < 5, 'en quelques secondes au plus');

	}

	public function testATileOfTheWestKeepsItsPlace(): void {

		$this->createZone(self::SQUARE_WEST);

		$tiles = ReliefLib::getTiles($this->eStructure);

		$this->assertTrue($tiles[0][0] < 0, 'une longitude négative donne un indice négatif');
		$this->assertEquals($this->getTileKey(-1.5505, 47.1995), $tiles[0][0].'/'.$tiles[0][1], 'l\'indice se prend par floor(), pas par troncature vers zéro');

		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		// L'emprise demandée est celle des indices entiers de la tuile, quelle que soit la zone
		[$south, $west, $north, $east] = array_map('floatval', explode(',', $this->calls[0]));
		$this->assertTrue(abs($tiles[0][0] * MapSetting::RELIEF_SIZE / MapSetting::RELIEF_DIVISIONS_LNG - $west) < 1e-9, 'l\'ouest de la première tuile');
		$this->assertTrue(abs(($tiles[0][1] + 1) * MapSetting::RELIEF_SIZE / MapSetting::RELIEF_DIVISIONS_LAT - $north) < 1e-9, 'le nord de la première tuile');

	}

	public function testSynchronizeFetchesEachTileOnce(): void {

		$this->createZone(self::SQUARE);

		$tiles = ReliefLib::getTiles($this->eStructure);
		$calls = ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertEquals(count($tiles), $calls, 'un appel par tuile');
		$this->assertEquals(count($tiles), Relief::model()->whereStructure($this->eStructure)->whereAltitudes('!=', NULL)->count(), 'chaque tuile a ses altitudes');

		$this->assertEquals(0, ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX), 'une tuile lue ne se relit pas');

	}

	public function testSynchronizeStopsAtItsCalls(): void {

		$this->createZone(self::SQUARE);

		$this->assertEquals(2, ReliefLib::synchronize($this->eStructure, 2), 'le plafond d\'appels du passage');
		$this->assertEquals(2, count($this->calls), 'aucun appel au-delà');

	}

	public function testAFailureWaitsADay(): void {

		$this->setClock('2037-06-15 10:00:00');

		$this->createZone(self::SQUARE);
		$this->answerNothing();

		$calls = ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertTrue($calls > 0, 'les tuiles sont demandées');
		$this->assertEquals($calls, Relief::model()->whereStructure($this->eStructure)->whereFailedAt('2037-06-15 10:00:00')->whereAltitudes(NULL)->count(), 'chaque échec est daté');

		$this->setClock('2037-06-16 09:59:00');
		$this->assertEquals(0, ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX), 'pas de nouvel essai avant 24 h');

		$this->setClock('2037-06-16 10:01:00');
		$this->answer(fn(float $lat) => 300.0);
		$this->assertEquals($calls, ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX), 'un nouvel essai après 24 h');
		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->whereFailedAt('!=', NULL)->count(), 'la date d\'échec s\'efface');

	}

	public function testAnImplausibleResponseIsRefused(): void {

		$size = MapSetting::RELIEF_SIZE;

		$this->answer(fn(float $lat) => 300.0);
		$this->assertEquals($size * $size * 2, strlen(ReliefLib::decode(ReliefLib::fetch(0, 0))), 'une réponse vraisemblable');

		// La valeur hors du territoire du RGE ALTI
		$this->answer(fn(float $lat) => ($lat > .001) ? -99999.0 : 300.0);
		$this->assertEquals(NULL, ReliefLib::decode(ReliefLib::fetch(0, 0)), 'une seule maille hors du territoire refuse la tuile');

		$this->answer(fn(float $lat) => NAN);
		$this->assertEquals(NULL, ReliefLib::decode(ReliefLib::fetch(0, 0)), 'un nombre qui n\'en est pas un');

		MapSetting::$reliefTransport = fn(string $url, array $arguments): ?string => str_repeat("\0", $size * $size * 4 - 1);
		$this->assertEquals(NULL, ReliefLib::fetch(0, 0), 'une réponse d\'une autre longueur');

		// Des altitudes vraisemblables, une de trop : la réponse ne décrit pas la tuile demandée
		MapSetting::$reliefTransport = fn(string $url, array $arguments): ?string => pack('g*', ...array_fill(0, $size * $size + 1, 300.0));
		$this->assertEquals(NULL, ReliefLib::fetch(0, 0), 'une réponse plus longue que la tuile');

		MapSetting::$reliefTransport = fn(string $url, array $arguments): ?string => '<html>Service indisponible</html>';
		$this->assertEquals(NULL, ReliefLib::fetch(0, 0), 'une page d\'erreur');

	}

	public function testATileOutsideTheCoverageWaitsNinetyDays(): void {

		$this->setClock('2037-06-15 10:00:00');

		$this->createZone(self::SQUARE);
		$this->answer(fn(float $lat) => -99999.0);

		$calls = ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertTrue($calls > 0, 'les tuiles sont demandées');
		$this->assertEquals($calls, Relief::model()->whereStructure($this->eStructure)->whereUncoveredAt('2037-06-15 10:00:00')->whereFailedAt(NULL)->whereAltitudes(NULL)->count(), 'chaque tuile est datée hors couverture, sans date d\'échec');

		// Le lendemain, quand un échec passager serait retenté
		$this->setClock('2037-06-16 10:01:00');
		$this->answer(fn(float $lat) => 300.0);
		$this->assertEquals(0, ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX), 'pas de nouvel essai le lendemain');

		$this->setClock('2037-09-13 09:59:00');
		$this->assertEquals(0, ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX), 'pas de nouvel essai avant 90 jours');

		$this->setClock('2037-09-13 10:01:00');
		$this->assertEquals($calls, ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX), 'un nouvel essai après 90 jours, la panne rattrapée');
		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->whereUncoveredAt('!=', NULL)->count(), 'la date hors couverture s\'efface');
		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->whereAltitudes(NULL)->count(), 'les altitudes sont lues');

	}

	public function testAFailureAfterOutsideTheCoverageKeepsOneDate(): void {

		$this->setClock('2037-06-15 10:00:00');

		$this->createZone(self::SQUARE);
		$this->answer(fn(float $lat) => -99999.0);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->setClock('2037-09-13 10:01:00');
		$this->answerNothing();
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->whereUncoveredAt('!=', NULL)->count(), 'l\'échec passager remplace la date hors couverture');
		$this->assertEquals(Relief::model()->whereStructure($this->eStructure)->count(), Relief::model()->whereStructure($this->eStructure)->whereFailedAt('2037-09-13 10:01:00')->count(), 'et se date');

	}

	public function testAFailureOfTheServiceIsNotOutsideTheCoverage(): void {

		$this->createZone(self::SQUARE);

		MapSetting::$reliefTransport = fn(string $url, array $arguments): ?string => '<html>Service indisponible</html>';
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->whereUncoveredAt('!=', NULL)->count(), 'une page d\'erreur se retente');
		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->whereFailedAt(NULL)->count(), 'et se date');

	}

	public function testTheCronLeavesClosedStructuresOut(): void {

		$eZone = $this->createZone(self::SQUARE);

		$this->assertTrue(in_array($this->eStructure['id'], ReliefLib::getStructures(), TRUE), 'une structure ouverte qui a tracé une zone');
		// La 7 porte des zones tracées dans la copie de dev : elle se lit, sans qu'aucune tuile ne s'y écrive ici
		$this->assertTrue(Zone::model()->whereStructure(7)->whereCoordinates('!=', NULL)->exists(), 'la 7 a tracé des zones');
		$this->assertTrue(in_array(7, ReliefLib::getStructures(), TRUE), 'la 7 en est, comme toute structure ouverte');

		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);
		ZoneLib::delete($eZone);

		$this->assertTrue(in_array($this->eStructure['id'], ReliefLib::getStructures(), TRUE), 'une structure sans zone qui garde des tuiles, pour les retirer');

		$this->eStructure['status'] = \structure\Structure::CLOSED;
		\structure\StructureLib::update($this->eStructure, ['status']);

		$this->assertFalse(in_array($this->eStructure['id'], ReliefLib::getStructures(), TRUE), 'une structure fermée, même avec des tuiles');

	}

	public function testTheRowsGoFromSouthToNorth(): void {

		$size = MapSetting::RELIEF_SIZE;

		// La tuile 19 237 commence à 45,599 degrés de latitude : l'altitude y monte d'un mètre tous les 1 / 10 000 de degré
		$this->answer(fn(float $lat) => ($lat - 45.59) * 10000);

		$altitudes = ReliefLib::decode(ReliefLib::fetch(0, 19237));
		$values = array_values(unpack('v*', $altitudes));

		$this->assertTrue($values[0] < $values[($size - 1) * $size], 'la première rangée stockée est au sud');

	}

	public function testTheSceneSeesTheReliefFromThreeMeters(): void {

		$this->createZone(self::SQUARE);

		// Une pente de 1 % : 1,1 m entre le sud et le nord de la zone
		$this->answer(fn(float $lat) => 300 + ($lat - 45.599) * 111200 * .01);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertEquals(NULL, ReliefLib::getForScene($this->eStructure), 'sous trois mètres, la scène reste plate');

		$this->resetTiles();

		// Une pente de 5 % : 5,5 m
		$this->answer(fn(float $lat) => 300 + ($lat - 45.599) * 111200 * .05);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$relief = ReliefLib::getForScene($this->eStructure);

		$this->assertEquals(['lat' => MapSetting::RELIEF_DIVISIONS_LAT, 'lng' => MapSetting::RELIEF_DIVISIONS_LNG], $relief['divisions'] ?? NULL, 'la maille de la grille');
		$this->assertEquals(count(ReliefLib::getTiles($this->eStructure)), count($relief['tiles']), 'toutes les tuiles lues');

	}

	public function testAZoneBelowTheSeaKeepsItsSign(): void {

		$this->createZone(self::SQUARE);

		// D'un mètre sous la mer au sud à un mètre au-dessus au nord : deux mètres, sous le seuil
		$this->answer(fn(float $lat) => -1 + ($lat - 45.599) * 111200 * 2 / 111);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertEquals(NULL, ReliefLib::getForScene($this->eStructure), 'une altitude négative se lit négative');

	}

	public function testFailedTilesGiveNoRelief(): void {

		$this->createZone(self::SQUARE);

		$this->answerNothing();
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertTrue(Relief::model()->whereStructure($this->eStructure)->count() > 0, 'des tuiles en échec');
		$this->assertEquals(NULL, ReliefLib::getForScene($this->eStructure), 'une tuile en échec ne porte aucune altitude');

	}

	public function testTheSceneOnlyReads(): void {

		$this->createZone(self::SQUARE);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->calls = [];
		$rows = Relief::model()->whereStructure($this->eStructure)->count();

		ReliefLib::getForScene($this->eStructure);

		$this->assertEquals([], $this->calls, 'la scène n\'appelle jamais le service');
		$this->assertEquals($rows, Relief::model()->whereStructure($this->eStructure)->count(), 'ni n\'écrit');

	}

	public function testTilesNoLongerWantedGoAway(): void {

		$eZone = $this->createZone(self::SQUARE);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertTrue(Relief::model()->whereStructure($this->eStructure)->count() > 0, 'des tuiles');

		ZoneLib::delete($eZone);
		ReliefLib::synchronize($this->eStructure, MapSetting::RELIEF_CALLS_MAX);

		$this->assertEquals(0, Relief::model()->whereStructure($this->eStructure)->count(), 'plus aucune zone, plus aucune tuile');

	}

	/**
	 * Le faux service : l'altitude de chaque maille au centre de sa latitude, la première rangée au nord comme le WMS.
	 */
	private function answer(\Closure $altitude): void {

		MapSetting::$reliefTransport = function(string $url, array $arguments) use($altitude): ?string {

			$this->calls[] = $arguments['BBOX'];

			[$south, , $north] = array_map('floatval', explode(',', $arguments['BBOX']));
			$size = (int)$arguments['HEIGHT'];
			$values = [];

			for($row = 0; $row < $size; $row++) {
				$lat = $north - ($row + .5) * ($north - $south) / $size;
				array_push($values, ...array_fill(0, (int)$arguments['WIDTH'], (float)$altitude($lat)));
			}

			return pack('g*', ...$values);

		};

	}

	private function answerNothing(): void {

		MapSetting::$reliefTransport = function(string $url, array $arguments): ?string {
			$this->calls[] = $arguments['BBOX'];
			return NULL;
		};

	}

	private function resetTiles(): void {

		$cRelief = Relief::model()
			->select(['id'])
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cRelief as $eRelief) {
			ReliefLib::delete($eRelief);
		}

	}

	private function getTileKey(float $lng, float $lat): string {
		return (int)floor($lng * MapSetting::RELIEF_DIVISIONS_LNG / MapSetting::RELIEF_SIZE).'/'.(int)floor($lat * MapSetting::RELIEF_DIVISIONS_LAT / MapSetting::RELIEF_SIZE);
	}

	private function createZone(array $coordinates): Zone {

		// Le décor du test : une parcelle tracée, que le formulaire du plan construit en traçant ; build() y vérifierait le contour
		// dessiné et la surface calculée, que le test pose lui-même pour connaitre exactement l'emprise
		$eZone = new Zone([
			'name' => 'Relief',
			'structure' => $this->eStructure,
			'area' => 8000,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		ZoneLib::create($eZone);

		return $eZone;

	}

}
?>
