<?php
namespace work;

/**
 * Test bout-en-bout de l'API publique work\Category (production/work/api/Category_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=work file=CategoryApi
 */
class CategoryApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new CategoryModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'actionCategoryList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'actionCategory', body: [
			'name' => 'Test API Category'
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Category', $create['body']['name'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'actionCategory/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Category', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'actionCategory/{id}', body: [
			'name' => 'Test API Category 2'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Category 2', $update['body']['name'] ?? NULL);

		$delete = $this->callApi('DELETE', 'actionCategory/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'actionCategory/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'actionCategory/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'actionCategory', body: [
			'name' => ''
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
