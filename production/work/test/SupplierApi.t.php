<?php
namespace work;

/**
 * Test bout-en-bout de l'API publique work\Supplier (production/work/api/Supplier_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=work file=SupplierApi
 */
class SupplierApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new SupplierModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'supplierList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'supplier', body: [
			'name' => 'Test API Supplier'
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Supplier', $create['body']['name'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'supplier/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Supplier', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'supplier/{id}', body: [
			'name' => 'Test API Supplier 2'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Supplier 2', $update['body']['name'] ?? NULL);

		$delete = $this->callApi('DELETE', 'supplier/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'supplier/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'supplier/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'supplier', body: [
			'name' => ''
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
