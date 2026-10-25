<?php
namespace plant;

/**
 * Test bout-en-bout de l'API publique plant\Plant (production/plant/api/Plant_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * L'espèce lue est celle du décor (`PlantFixtureAiTest`), posée dans la transaction de la
 * méthode : le cas de la vignette cherchait avant cela une espèce de la structure qui en portait
 * une, et passait sans rien vérifier quand il n'en trouvait pas.
 *
 * Lancement : dev/test package=plant file=PlantApi
 */
class PlantApiTest extends PlantFixtureAiTest {

	public function testList(): void {

		$ePlant = $this->getPlant();

		$response = $this->callApi('GET', 'plantList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

		$filtered = $this->callApi('GET', 'plantList', get: ['ids' => [$ePlant['id']]]);

		$this->assertEquals(200, $filtered['status']);
		$this->assertEquals(1, $filtered['body']['count']);
		$this->assertEquals([$ePlant['id']], array_column($filtered['body']['list'], 'id'), 'l\'espèce du décor est dans la liste');

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'plant', body: [
			'name' => 'Test API Plant',
			'color' => '#123456',
			'family' => NULL,
			'cycle' => 'annual',
			'plantsSafetyMargin' => 10,
			'seedsSafetyMargin' => 5
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Plant', $create['body']['name'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'plant/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Plant', $read['body']['name'] ?? NULL);

		// PlantLib::getPropertiesUpdate() n'autorise 'cycle'/'family' que si Plant::isOwner() est vrai
		// (utilisateur avec droits "manage" sur la structure) ; l'utilisateur système de l'API
		// (\user\UserSetting::$apiUserId) ne les a pas forcément : ces deux champs peuvent donc
		// rester silencieusement inchangés (pas d'erreur retournée par l'API dans ce cas).
		$update = $this->callApi('PUT', 'plant/{id}', body: [
			'name' => 'Test API Plant 2',
			'color' => '#654321',
			'family' => NULL,
			'cycle' => 'perennial',
			'plantsSafetyMargin' => 20,
			'seedsSafetyMargin' => 15
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Plant 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(20, $update['body']['plantsSafetyMargin'] ?? NULL);

		$delete = $this->callApi('DELETE', 'plant/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'plant/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'plant/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		$response = $this->callApi('POST', 'plant', body: [
			'name' => '',
			'color' => '#123456',
			'family' => NULL,
			'cycle' => 'annual',
			'plantsSafetyMargin' => 10,
			'seedsSafetyMargin' => 5
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * La correlation 'vignette' doit rendre une URL du bon média, pas un hash brut. La vignette est
	 * posée par la route qui la dépose, puis retirée : le fichier écrit sur le disque ne suit pas la
	 * transaction.
	 */
	public function testVignetteUrl(): void {

		$ePlant = $this->getPlant();

		$put = $this->callApi('PUT', 'plant/{id}/vignette', get: ['id' => $ePlant['id']], body: ['content' => $this->getImage()]);

		$this->assertEquals(200, $put['status']);

		$response = $this->callApi('GET', 'plant/{id}', get: ['id' => $ePlant['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(str_contains($response['body']['vignette'] ?? '', 'plant-vignette/'));

		$delete = $this->callApi('DELETE', 'plant/{id}/vignette', get: ['id' => $ePlant['id']]);

		$this->assertEquals(200, $delete['status']);

	}

}
?>
