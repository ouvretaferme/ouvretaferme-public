<?php
namespace work;

/**
 * Test bout-en-bout de l'API publique work\Tool (production/work/api/Tool_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=work file=ToolApi
 */
class ToolApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ToolModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'toolList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		// action/routineName/routineValue NULL : outil "libre", pas rattaché à une routine standalone
		// (cf Tool::isStandaloneRoutine()) -> ToolLib::getPropertiesUpdate() retombe sur getPropertiesCreate().
		$create = $this->callApi('POST', 'tool', body: [
			'name' => 'Test API Tool',
			'action' => NULL,
			'stock' => 10,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Tool', $create['body']['name'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'tool/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Tool', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'tool/{id}', body: [
			'name' => 'Test API Tool 2',
			'action' => NULL,
			'stock' => 20,
			'comment' => 'Updated',
			'routineName' => NULL,
			'routineValue' => NULL
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Tool 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(20, $update['body']['stock'] ?? NULL);

		$delete = $this->callApi('DELETE', 'tool/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'tool/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	/**
	 * Tool_0_1betaApi::getStandalonePropertiesUpdate() groupe ['routineName', 'routineValue'] : un PUT
	 * "standalone" (body['properties'] renseigné) doit pouvoir mettre à jour un champ hors groupe seul
	 * (ici 'name'), sans toucher aux autres champs déjà en base (ex. 'stock').
	 */
	public function testStandaloneUpdateSingleField(): void {

		$create = $this->callApi('POST', 'tool', body: [
			'name' => 'Test API Tool',
			'action' => NULL,
			'stock' => 10,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$id = $create['body']['id'];

		$update = $this->callApi('PUT', 'tool/{id}', body: [
			'properties' => ['name'],
			'name' => 'Test API Tool Renamed Alone'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Tool Renamed Alone', $update['body']['name'] ?? NULL);
		$this->assertEquals(10, $update['body']['stock'] ?? NULL);

	}

	/**
	 * 'routineName' et 'routineValue' sont groupés : un PUT standalone qui n'en cite qu'un seul doit
	 * être rejeté (les deux doivent être mis à jour ensemble), cf ModuleApi::validateStandalonePropertiesUpdate().
	 */
	public function testStandaloneUpdateGroupPartialIsRejected(): void {

		$create = $this->callApi('POST', 'tool', body: [
			'name' => 'Test API Tool',
			'action' => NULL,
			'stock' => 10,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$id = $create['body']['id'];

		$response = $this->callApi('PUT', 'tool/{id}', body: [
			'properties' => ['routineName'],
			'routineName' => NULL
		], get: ['id' => $id]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Une propriété absente de ToolLib::getPropertiesUpdate() (ex. 'status', qui n'est modifiable par
	 * aucun chemin de l'API) doit être rejetée en standalone, pas silencieusement acceptée ou ignorée.
	 */
	public function testStandaloneUpdateUnknownPropertyIsRejected(): void {

		$create = $this->callApi('POST', 'tool', body: [
			'name' => 'Test API Tool',
			'action' => NULL,
			'stock' => 10,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$id = $create['body']['id'];

		$response = $this->callApi('PUT', 'tool/{id}', body: [
			'properties' => ['status'],
			'status' => 'inactive'
		], get: ['id' => $id]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'tool/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'tool', body: [
			'name' => '',
			'action' => NULL,
			'stock' => NULL,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	public function testListHidesDeleted(): void {

		$toolActive = $this->createTool('Test API Tool actif');
		$tool = $this->createTool('Test API Tool retiré');

		// build() ne construit pas `status` : `deleted` ne se pose que par ToolLib::delete(), sur un
		// matériel cité par une intervention, un itinéraire ou le carnet, que ce test n'a pas à monter.
		Tool::model()->update(new Tool(['id' => $tool]), ['status' => Tool::DELETED]);

		$ids = fn(array $response) => array_column($response['body']['list'], 'id');

		$default = $this->callApi('GET', 'toolList', get: ['limit' => 500]);

		$this->assertEquals(200, $default['status']);
		$this->assertFalse(in_array($tool, $ids($default)), 'un matériel retiré sort de la liste');
		$this->assertEquals([], array_filter(array_column($default['body']['list'], 'status'), fn($status) => $status === Tool::DELETED), 'la liste ne rend aucun matériel retiré');

		$deleted = $this->callApi('GET', 'toolList', get: ['limit' => 500, 'status' => Tool::DELETED]);

		$this->assertEquals(200, $deleted['status']);
		$this->assertTrue(in_array($tool, $ids($deleted)), '?status=deleted le retrouve');
		$this->assertEquals([Tool::DELETED], array_values(array_unique(array_column($deleted['body']['list'], 'status'))));

		$active = $this->callApi('GET', 'toolList', get: ['limit' => 500, 'status' => Tool::ACTIVE]);

		$this->assertEquals([Tool::ACTIVE], array_values(array_unique(array_column($active['body']['list'], 'status'))));
		$this->assertTrue(in_array($toolActive, $ids($active)), '?status=active rend le matériel actif');
		$this->assertFalse(in_array($tool, $ids($active)));

		$unknown = $this->callApi('GET', 'toolList', get: ['status' => 'perdu']);

		$this->assertEquals(400, $unknown['status']);

	}

	/** La correlation 'vignette' doit rendre une URL du bon média, pas un hash brut. */
	public function testVignetteUrl(): void {

		$tool = $this->createTool('Test API Tool vignette');

		$image = new \Imagick();
		$image->newImage(600, 600, new \ImagickPixel('#4a7c2f'));
		$image->setImageFormat('jpeg');

		$put = $this->callApi('PUT', 'tool/{id}/vignette', get: ['id' => $tool], body: ['content' => base64_encode($image->getImageBlob())]);

		$image->clear();

		$this->assertEquals(200, $put['status']);

		$response = $this->callApi('GET', 'tool/{id}', get: ['id' => $tool]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(str_contains($response['body']['vignette'] ?? '', 'tool-vignette/'));

		$this->callApi('DELETE', 'tool/{id}/vignette', get: ['id' => $tool]);

	}

	private function createTool(string $name): int {

		$create = $this->callApi('POST', 'tool', body: [
			'name' => $name,
			'action' => NULL,
			'stock' => NULL,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		$this->assertEquals(201, $create['status']);

		return $create['body']['id'];

	}

}
?>
