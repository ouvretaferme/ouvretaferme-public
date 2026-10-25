<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\WaterMeter (production/toolbox/api/WaterMeter_0_1beta.a.php).
 *
 * Le décor ne dépend d'aucune donnée de la base : chaque cas crée ses compteurs par l'API, comme le
 * ferait un intégrateur, et le compteur d'une autre structure est posé par le Lib sur une seconde
 * structure de référence.
 *
 * Lancement : dev/test package=toolbox file=WaterMeterApi
 */
class WaterMeterApiTest extends \dev\ApiTest {

	const CLOCK = '2026-06-15 10:00:00';

	protected function getTransactionModel(): \ModuleModel {
		return new WaterMeterModel();
	}

	private function create(string $name, int $digits = 5): array {

		return $this->callApi('POST', 'waterMeter', body: ['name' => $name, 'digits' => $digits]);

	}

	/** Un compteur d'une autre structure : la ressource ne doit jamais le rendre. */
	private function getWaterMeterForeign(): WaterMeter {

		$eStructureForeign = \structure\StructureLib::getById(\structure\FixtureAiLib::F2);

		// Hors build() : il vérifierait le nom et le nombre de chiffres, posés ici à des valeurs que le
		// schéma accepte, et aucun appel de l'API n'écrit sur une autre structure que celle du jeton.
		$eWaterMeter = new WaterMeter([
			'structure' => $eStructureForeign,
			'name' => 'Compteur API hors structure',
			'digits' => 5
		]);

		WaterMeterLib::create($eWaterMeter);

		return $eWaterMeter;

	}

	public function testList(): void {

		$this->create('Compteur API 1');
		$this->create('Compteur API 2', 4);
		$eWaterMeterForeign = $this->getWaterMeterForeign();

		$expected = WaterMeter::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('GET', 'waterMeterList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les compteurs de la structure');
		$this->assertEquals(['id', 'name', 'digits', 'status'], array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs');
		$this->assertFalse(in_array($eWaterMeterForeign['id'], array_column($response['body']['list'], 'id')), 'la liste ne rend pas le compteur d\'une autre structure');

	}

	public function testListFilterStatus(): void {

		$kept = $this->create('Compteur API gardé');
		$deleted = $this->create('Compteur API supprimé');

		$this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['status'], 'status' => WaterMeter::INACTIVE], get: ['id' => $deleted['body']['id']]);

		$response = $this->callApi('GET', 'waterMeterList', get: ['status' => WaterMeter::INACTIVE, 'limit' => 500]);
		$ids = array_column($response['body']['list'], 'id');

		$this->assertTrue(in_array($deleted['body']['id'], $ids), 'le filtre rend le compteur supprimé');
		$this->assertFalse(in_array($kept['body']['id'], $ids), 'et pas celui qui est resté actif');

		// Sans filtre, la liste rend aussi les compteurs supprimés : c'est la seule voie pour relire
		// leurs relevés, que la ressource waterReading rend toujours.
		$response = $this->callApi('GET', 'waterMeterList', get: ['limit' => 500]);
		$ids = array_column($response['body']['list'], 'id');

		$this->assertTrue(in_array($deleted['body']['id'], $ids) and in_array($kept['body']['id'], $ids), 'sans filtre, la liste rend les deux');

	}

	public function testReadForeign(): void {

		$response = $this->callApi('GET', 'waterMeter/{id}', get: ['id' => $this->getWaterMeterForeign()['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreate(): void {

		$response = $this->create('Forage API', 6);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals('Forage API', $response['body']['name']);
		$this->assertEquals(6, $response['body']['digits']);
		$this->assertEquals(WaterMeter::ACTIVE, $response['body']['status']);

		$read = $this->callApi('GET', 'waterMeter/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($response['body'], $read['body'], 'la lecture rend ce que la création a rendu');

		$eWaterMeter = WaterMeter::model()
			->select(['structure'])
			->whereId($response['body']['id'])
			->get();

		$this->assertEquals($this->eStructure['id'], $eWaterMeter['structure']['id'], 'le compteur est posé sur la structure du jeton');

		// Deux compteurs actifs du même nom feraient deux onglets qu'on ne distingue pas.
		$again = $this->create('Forage API');

		$this->assertEquals(400, $again['status']);
		$this->assertEquals('WaterMeter::name.duplicate', $again['body'][0]['code'] ?? NULL);

	}

	public function testCreateDigitsOutOfRange(): void {

		foreach([3, 10] as $digits) {

			$response = $this->create('Compteur API à '.$digits.' chiffres', $digits);

			$this->assertEquals(400, $response['status'], $digits.' chiffres sont hors des bornes du schéma');
			$this->assertEquals('WaterMeter::digits.check', $response['body'][0]['code'] ?? NULL);

		}

	}

	public function testCreateMissing(): void {

		// Un corps qui ne porte pas toutes les propriétés de la liste est refusé avant build().
		$response = $this->callApi('POST', 'waterMeter', body: ['name' => 'Compteur API sans chiffres']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterMeter::digits.missing', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdate(): void {

		$this->setClock(self::CLOCK);

		$created = $this->create('Puits API', 5);
		$other = $this->create('Réseau API', 5);
		$get = ['id' => $created['body']['id']];

		// `properties` restreint la modification : le corps complet exigerait `status`, dont la seule
		// valeur acceptée est `inactive`, ce qui supprimerait le compteur.
		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['name', 'digits'], 'name' => 'Puits du bas API', 'digits' => 6], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('Puits du bas API', $response['body']['name']);
		$this->assertEquals(6, $response['body']['digits']);

		// Un compteur qui garde son nom ne se compte pas lui-même, celui d'un autre reste pris.
		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['name'], 'name' => 'Puits du bas API'], get: $get);

		$this->assertEquals(200, $response['status'], 'un compteur garde son nom');

		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['name'], 'name' => $other['body']['name']], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterMeter::name.duplicate', $response['body'][0]['code'] ?? NULL);

		// Un compteur ne perd pas de chiffres sous ses relevés.
		$this->callApi('POST', 'waterReading', body: ['meter' => $created['body']['id'], 'date' => '2026-06-10', 'value' => 124820]);

		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['digits'], 'digits' => 5], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterMeter::digits.readings', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateDelete(): void {

		$created = $this->create('Compteur API à supprimer');
		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['status'], 'status' => WaterMeter::INACTIVE], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(WaterMeter::INACTIVE, $response['body']['status']);

		// Un compteur supprimé ne se modifie plus : c'est `acceptUpdate()`, la seule garde que l'API
		// interroge, les `can*` n'étant jamais appelés derrière une clé.
		$again = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['name'], 'name' => 'Compteur API revenu'], get: $get);

		$this->assertEquals(412, $again['status']);
		$this->assertEquals('accept_update', $again['body'][0]['code'] ?? NULL);

		// Son nom redevient libre.
		$response = $this->create('Compteur API à supprimer');

		$this->assertEquals(201, $response['status'], 'le nom d\'un compteur supprimé se reprend');

	}

	public function testUpdateActiveRefused(): void {

		$created = $this->create('Compteur API actif');

		// Le seul changement de statut est la suppression.
		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['status'], 'status' => WaterMeter::ACTIVE], get: ['id' => $created['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterMeter::status.deleted', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateForeign(): void {

		$eWaterMeterForeign = $this->getWaterMeterForeign();

		$response = $this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['status'], 'status' => WaterMeter::INACTIVE], get: ['id' => $eWaterMeterForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(WaterMeter::ACTIVE, WaterMeter::model()->whereId($eWaterMeterForeign['id'])->getValue('status'), 'le compteur d\'une autre structure est resté actif');

	}

	public function testDeleteRouteAbsent(): void {

		$created = $this->create('Compteur API sans route');

		// La suppression d'un compteur est un changement de statut : aucune route DELETE n'existe.
		$response = $this->callApi('DELETE', 'waterMeter/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(405, $response['status']);
		$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

}
?>
