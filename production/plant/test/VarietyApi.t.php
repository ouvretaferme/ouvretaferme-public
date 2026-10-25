<?php
namespace plant;

/**
 * Test bout-en-bout de l'API publique plant\Variety (production/plant/api/Variety_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * L'espèce et la variété lues sont celles du décor (`PlantFixtureAiTest`), posées dans la
 * transaction de la méthode ; la liste ne s'affirme que sur la variété que le cas a posée.
 *
 * Lancement : dev/test package=plant file=VarietyApi
 */
class VarietyApiTest extends PlantFixtureAiTest {

	public function testList(): void {

		$eVariety = $this->getVariety();

		$response = $this->callApi('GET', 'plantVarietyList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

		$filtered = $this->callApi('GET', 'plantVarietyList', get: ['ids' => [$eVariety['id']]]);

		$this->assertEquals(200, $filtered['status']);
		$this->assertEquals(1, $filtered['body']['count']);
		$this->assertEquals([$eVariety['id']], array_column($filtered['body']['list'], 'id'), 'la variété du décor est dans la liste');

	}

	public function testCreate(): void {

		$ePlant = $this->createPlant();

		$create = $this->callApi('POST', 'plantVariety', body: [
			'plant' => $ePlant['id'],
			'name' => 'Test API Variety',
			'supplierSeed' => NULL,
			'supplierPlant' => NULL,
			'weightSeed1000' => NULL,
			'numberPlantKilogram' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Variety', $create['body']['name'] ?? NULL);
		$this->assertEquals($ePlant['id'], $create['body']['plant'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

	}

	/**
	 * 'plant' fait partie de VarietyLib::getPropertiesCreate() (cf commit "Fix Api create() variety")
	 * et Variety::build() le vérifie via 'plant.check' : sans lui, la création doit être rejetée
	 * plutôt que de planter avec une \ElementException (bug initial de Variety_0_1betaApi::getNewElement()).
	 */
	public function testCreateMissingPlantIsRejected(): void {

		$response = $this->callApi('POST', 'plantVariety', body: [
			'name' => 'Test API Variety',
			'supplierSeed' => NULL,
			'supplierPlant' => NULL,
			'weightSeed1000' => NULL,
			'numberPlantKilogram' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	public function testReadUpdateDelete(): void {

		$eVariety = $this->getVariety();

		$id = $eVariety['id'];

		$read = $this->callApi('GET', 'plantVariety/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($eVariety['name'], $read['body']['name'] ?? NULL);
		$this->assertEquals($eVariety['plant']['id'], $read['body']['plant'] ?? NULL);

		// ⚠️ Les sept propriétés de VarietyLib::getPropertiesUpdate() sont envoyées, `unit` et
		// `stock` comprises : hors mode standalone l'API construit la liste entière, et toute
		// propriété absente du corps échoue en `<propriété>.missing`
		// (`framework/core/Api.php:405`). Un PUT partiel rend 400.
		$update = $this->callApi('PUT', 'plantVariety/{id}', body: [
			'name' => 'Test API Variety 2',
			'supplierSeed' => NULL,
			'supplierPlant' => NULL,
			'weightSeed1000' => 3.5,
			'numberPlantKilogram' => 100,
			'unit' => 'seed',
			'stock' => 42
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Variety 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(100, $update['body']['numberPlantKilogram'] ?? NULL);

		// `stock` se relit : c'est ce que l'exposer veut dire.
		$this->assertEquals(42, $update['body']['stock'] ?? NULL);

		$delete = $this->callApi('DELETE', 'plantVariety/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'plantVariety/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'plantVariety/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'plantVariety', body: [
			'name' => '',
			'supplierSeed' => NULL,
			'supplierPlant' => NULL,
			'weightSeed1000' => NULL,
			'numberPlantKilogram' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * `unit` dit dans quelle unité le stock est envoyé, et l'API convertit comme le formulaire.
	 *
	 * Le stock se range toujours en graines. Envoyé en grammes, il est divisé par le poids de
	 * mille graines : 42 g pour un poids de 3,5 g / 1000 font 12 000 graines. C'est la seule
	 * raison d'être de `unit`, et la preuve que l'exposer sert à autre chose qu'à satisfaire
	 * `convertProperties()`.
	 *
	 * ⚠️ La conversion remplace le cast au lieu de le suivre (`module/Variety.e.php:98`) :
	 * convertir un entier déjà arrondi ferait tomber à zéro un demi-gramme de tomate, qui vaut
	 * pourtant cent cinquante graines.
	 */
	public function testStockConvertsFromTheGivenUnit(): void {

		$ePlant = $this->createPlant();

		$create = $this->callApi('POST', 'plantVariety', body: [
			'plant' => $ePlant['id'],
			'name' => 'Test API Variety stock',
			'supplierSeed' => NULL,
			'supplierPlant' => NULL,
			'weightSeed1000' => 3.5,
			'numberPlantKilogram' => NULL
		]);

		$id = $create['body']['id'];

		$body = [
			'name' => 'Test API Variety stock',
			'supplierSeed' => NULL,
			'supplierPlant' => NULL,
			'weightSeed1000' => 3.5,
			'numberPlantKilogram' => NULL,
			'unit' => 'seed',
			'stock' => 42
		];

		$seed = $this->callApi('PUT', 'plantVariety/{id}', body: $body, get: ['id' => $id]);

		$this->assertEquals(200, $seed['status']);
		$this->assertEquals(42, $seed['body']['stock'] ?? NULL);

		$gram = $this->callApi('PUT', 'plantVariety/{id}', body: ['unit' => 'gram'] + $body, get: ['id' => $id]);

		$this->assertEquals(200, $gram['status']);
		$this->assertEquals(12000, $gram['body']['stock'] ?? NULL);

		// 1 g pour 3,5 g les mille graines font 285,7 graines : la conversion garde 285
		$gramPartial = $this->callApi('PUT', 'plantVariety/{id}', body: ['unit' => 'gram', 'stock' => 1] + $body, get: ['id' => $id]);

		$this->assertEquals(200, $gramPartial['status']);
		$this->assertEquals(285, $gramPartial['body']['stock'] ?? NULL);

		// Et le pendant qui prouve que le contrôle sait refuser : une unité inconnue est rejetée.
		$wrong = $this->callApi('PUT', 'plantVariety/{id}', body: ['unit' => 'kilogram'] + $body, get: ['id' => $id]);

		$this->assertEquals(400, $wrong['status']);

	}

}
?>
