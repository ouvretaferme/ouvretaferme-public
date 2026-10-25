<?php
namespace work;

/**
 * Test bout-en-bout de l'API publique work\Method (production/work/api/Method_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=work file=MethodApi
 */
class MethodApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new MethodModel();
	}

	/**
	 * work\Method.action est requis (non nullable) : on passe par l'API elle-même (actionCategory
	 * puis action) pour obtenir un id d'action réel, plutôt que de reconstruire l'entité à la main.
	 */
	protected function createAction(): int {

		$category = $this->callApi('POST', 'actionCategory', body: ['name' => 'Test API Category for Method']);

		$action = $this->callApi('POST', 'action', body: [
			'name' => 'Test API Action for Method',
			'categories' => [$category['body']['id']],
			'color' => '#112233'
		]);

		return $action['body']['id'];

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'actionMethodList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$actionId = $this->createAction();

		$create = $this->callApi('POST', 'actionMethod', body: [
			'name' => 'Test API Method',
			'action' => $actionId
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Method', $create['body']['name'] ?? NULL);
		$this->assertEquals($actionId, $create['body']['action'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'actionMethod/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Method', $read['body']['name'] ?? NULL);

		// MethodLib::getPropertiesUpdate() n'autorise que 'name' ('action' n'est pas modifiable après création)
		$update = $this->callApi('PUT', 'actionMethod/{id}', body: [
			'name' => 'Test API Method 2'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Method 2', $update['body']['name'] ?? NULL);

		$delete = $this->callApi('DELETE', 'actionMethod/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'actionMethod/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	/**
	 * L'action d'une méthode est une action de la structure. Le chemin web la passe par
	 * `ActionLib::getById(POST('action'))->validate()` (`page/method.p.php`), qui pose la question du
	 * droit ; l'API la construit par `build()`, donc sans elle, et l'identifiant d'une action propre à
	 * une autre structure était accepté. La méthode créée publiait alors le nom de cette action en
	 * lecture. Mesuré le 2026-10-02 sur la copie de production : 0 des 4 361 méthodes de la base
	 * commune pointe vers une action qui n'est pas de leur structure.
	 */
	public function testActionOfAnotherStructureIsRefused(): void {

		// Le décor écrit sans build() : c'est l'appartenance à une autre structure que le cas éprouve,
		// et build() la refuserait désormais.
		$eActionOther = new Action([
			'name' => 'Action API autre structure',
			'structure' => new \structure\Structure(['id' => \structure\FixtureAiLib::F2]),
			'favorite' => FALSE,
			'color' => '#445566',
			'soil' => FALSE,
			'categories' => [],
			'series' => FALSE,
			'status' => Action::ACTIVE,
		]);

		ActionLib::create($eActionOther);

		$this->assertTrue($eActionOther['id'] > 0, 'le décor porte une action d\'une autre structure');
		$this->assertTrue($this->eStructure['id'] !== \structure\FixtureAiLib::F2, 'et cette structure n\'est pas celle du jeton');

		$response = $this->callApi('POST', 'actionMethod', body: ['name' => 'Méthode hors structure', 'action' => $eActionOther['id']]);

		$this->assertEquals(400, $response['status'], 'une action d\'une autre structure est refusée');
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL, 'le refus vient de la ressource');
		$this->assertEquals('Method::action.owned', $response['body'][0]['code'] ?? NULL, 'et il nomme l\'étape');
		$this->assertTrue(is_string($response['body'][0]['message'] ?? NULL) and $response['body'][0]['message'] !== '', 'le message est rendu');
		$this->assertEquals(0, Method::model()->whereStructure($this->eStructure)->whereName('Méthode hors structure')->count(), 'aucune méthode n\'est créée');

		$response = $this->callApi('POST', 'actionMethod', body: ['name' => 'Méthode de la structure', 'action' => $this->createAction()]);

		$this->assertEquals(201, $response['status'], 'une action de la structure reste acceptée');

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'actionMethod/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'actionMethod', body: [
			'name' => '',
			'action' => $this->createAction()
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
