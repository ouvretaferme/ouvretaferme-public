<?php
namespace plant;

/**
 * Test bout-en-bout de l'API publique plant\Family (production/plant/api/Family_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * La famille lue est celle du décor (`PlantFixtureAiTest`), posée dans la transaction de la
 * méthode : le cas cherchait avant cela « asteraceae » dans le référentiel, qui pouvait ne pas
 * la porter.
 *
 * Lancement : dev/test package=plant file=FamilyApiAi
 */
class FamilyApiAiTest extends PlantFixtureAiTest {

	public function testList(): void {

		$eFamily = $this->getFamily();

		$response = $this->callApi('GET', 'plantFamilyList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

		$filtered = $this->callApi('GET', 'plantFamilyList', get: ['ids' => [$eFamily['id']]]);

		$this->assertEquals(200, $filtered['status']);
		$this->assertEquals(1, $filtered['body']['count']);
		$this->assertEquals([$eFamily['id']], array_column($filtered['body']['list'], 'id'), 'la famille du décor est dans la liste');

	}

	public function testRead(): void {

		$eFamily = $this->getFamily();

		$response = $this->callApi('GET', 'plantFamily/{id}', get: ['id' => $eFamily['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eFamily['fqn'], $response['body']['fqn'] ?? NULL);
		$this->assertEquals($eFamily['name'], $response['body']['name'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'plantFamily/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Le référentiel est commun à toutes les structures : aucune route d'écriture ne doit exister,
	 * sans quoi une clé API modifierait les familles de tout le monde.
	 */
	public function testWriteRoutesAreNotExposed(): void {

		$eFamily = $this->getFamily();

		foreach([
			['POST', 'plantFamily', 404],
			['PUT', 'plantFamily/{id}', 405],
			['DELETE', 'plantFamily/{id}', 405]
		] as [$method, $route, $status]) {

			$response = $this->callApi($method, $route, get: ['id' => $eFamily['id']]);

			$this->assertEquals($status, $response['status']);
			$this->assertEquals(match($status) { 404 => 'unknown_route', 405 => 'method_not_allowed' }, $response['body'][0]['code'] ?? NULL);

		}

	}

}
?>
