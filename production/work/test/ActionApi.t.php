<?php
namespace work;

/**
 * Test bout-en-bout de l'API publique work\Action (production/work/api/Action_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=work file=ActionApi
 */
class ActionApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ActionModel();
	}

	protected function createCategory(): Category {

		$eCategory = new Category([
			'name' => 'Test API Category for Action',
			'structure' => $this->eStructure
		]);

		CategoryLib::create($eCategory);

		return $eCategory;

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'actionList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$eCategory = $this->createCategory();

		$create = $this->callApi('POST', 'action', body: [
			'name' => 'Test API Action',
			'categories' => [$eCategory['id']],
			'color' => '#112233'
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Action', $create['body']['name'] ?? NULL);
		$this->assertEquals([$eCategory['id']], $create['body']['categories'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'action/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Action', $read['body']['name'] ?? NULL);
		$this->assertEquals([], $read['body']['methods'] ?? NULL);

		// L'action créée n'a pas de fqn (isProtected() === FALSE) : le jeu complet de propriétés
		// s'applique (cf ActionLib::getPropertiesUpdate()). pace/soil sont de toute façon forcés à
		// NULL/FALSE par Action::build() tant qu'aucune catégorie de fqn CATEGORIE_CULTURE n'est liée.
		$update = $this->callApi('PUT', 'action/{id}', body: [
			'name' => 'Test API Action 2',
			'categories' => [$eCategory['id']],
			'favorite' => TRUE,
			'color' => '#445566',
			'pace' => NULL,
			'soil' => FALSE
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Action 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(TRUE, $update['body']['favorite'] ?? NULL);

		$delete = $this->callApi('DELETE', 'action/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'action/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'action/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		// 'categories' vide est rejeté par Action::build() (callback 'categories.check')
		$response = $this->callApi('POST', 'action', body: [
			'name' => 'Test API Action',
			'categories' => [],
			'color' => '#112233'
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
