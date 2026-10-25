<?php
namespace product;

/**
 * Test bout-en-bout de l'API publique product\Category (base/product/api/Category_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=product file=CategoryApi
 */
class CategoryApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new CategoryModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'productCategoryList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'productCategory', body: [
			'name' => 'Test API Category',
			'icon' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Category', $create['body']['name'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'productCategory/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Category', $read['body']['name'] ?? NULL);

		// 'icon' doit appartenir à Category::getIcons() (cf Category::build(), callback 'icon.check')
		$update = $this->callApi('PUT', 'productCategory/{id}', body: [
			'name' => 'Test API Category 2',
			'icon' => 'fruit'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Category 2', $update['body']['name'] ?? NULL);
		$this->assertEquals('fruit', $update['body']['icon'] ?? NULL);

		$delete = $this->callApi('DELETE', 'productCategory/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'productCategory/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'productCategory/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'productCategory', body: [
			'name' => '',
			'icon' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
