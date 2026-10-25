<?php
namespace plant;

/**
 * Test bout-en-bout de l'API publique plant\Size (production/plant/api/Size_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * L'espèce et la taille lues sont celles du décor (`PlantFixtureAiTest`), posées dans la
 * transaction de la méthode ; la liste ne s'affirme que sur la taille que le cas a posée.
 *
 * Lancement : dev/test package=plant file=SizeApi
 */
class SizeApiTest extends PlantFixtureAiTest {

	public function testList(): void {

		$eSize = $this->getSize();

		$response = $this->callApi('GET', 'plantSizeList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

		$filtered = $this->callApi('GET', 'plantSizeList', get: ['ids' => [$eSize['id']]]);

		$this->assertEquals(200, $filtered['status']);
		$this->assertEquals(1, $filtered['body']['count']);
		$this->assertEquals([$eSize['id']], array_column($filtered['body']['list'], 'id'), 'la taille du décor est dans la liste');

	}

	public function testCreate(): void {

		$ePlant = $this->createPlant();

		$create = $this->callApi('POST', 'plantSize', body: [
			'plant' => $ePlant['id'],
			'name' => 'Test API Size',
			'comment' => NULL,
			'yield' => TRUE
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Size', $create['body']['name'] ?? NULL);
		$this->assertEquals($ePlant['id'], $create['body']['plant'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

	}

	/**
	 * 'plant' fait partie de SizeLib::getPropertiesCreate() (cf commit "Fix Api create() size") et
	 * Size::build() le vérifie via 'plant.check' : sans lui, la création doit être rejetée plutôt
	 * que de planter avec une \ElementException (bug initial de Size_0_1betaApi::getNewElement()).
	 */
	public function testCreateMissingPlantIsRejected(): void {

		$response = $this->callApi('POST', 'plantSize', body: [
			'name' => 'Test API Size',
			'comment' => NULL,
			'yield' => TRUE
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	public function testReadUpdateDelete(): void {

		$eSize = $this->getSize();

		$id = $eSize['id'];

		$read = $this->callApi('GET', 'plantSize/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($eSize['name'], $read['body']['name'] ?? NULL);
		$this->assertEquals($eSize['plant']['id'], $read['body']['plant'] ?? NULL);

		$update = $this->callApi('PUT', 'plantSize/{id}', body: [
			'name' => 'Test API Size 2',
			'comment' => '<p>Un commentaire</p>',
			'yield' => FALSE
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Size 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(FALSE, $update['body']['yield'] ?? NULL);

		$delete = $this->callApi('DELETE', 'plantSize/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'plantSize/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'plantSize/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'plantSize', body: [
			'name' => '',
			'comment' => NULL,
			'yield' => TRUE
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
