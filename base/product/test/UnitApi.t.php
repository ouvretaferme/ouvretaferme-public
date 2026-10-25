<?php
namespace product;

/**
 * Test bout-en-bout de l'API publique product\Unit (base/product/api/Unit_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTestCase pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=product file=UnitApi
 */
class UnitApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new UnitModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'unitList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'unit', body: [
			'singular' => 'test-api-unite',
			'plural' => 'test-api-unites',
			'short' => 'tau',
			'type' => 'integer',
			'approximate' => FALSE
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('test-api-unite', $create['body']['singular'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'unit/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('test-api-unite', $read['body']['singular'] ?? NULL);

		$update = $this->callApi('PUT', 'unit/{id}', body: [
			'singular' => 'test-api-un-2',
			'plural' => 'test-api-uns-2',
			'short' => 'tau2',
			'type' => 'integer',
			'approximate' => TRUE
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('test-api-un-2', $update['body']['singular'] ?? NULL);
		$this->assertEquals(TRUE, $update['body']['approximate'] ?? NULL);

		$delete = $this->callApi('DELETE', 'unit/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'unit/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'unit/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'unit', body: [
			'singular' => '',
			'plural' => 'test-api-unites',
			'short' => 'tau',
			'type' => 'integer',
			'approximate' => FALSE
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
