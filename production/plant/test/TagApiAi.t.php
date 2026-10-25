<?php
namespace plant;

/**
 * Test bout-en-bout de l'API publique plant\Tag (production/plant/api/Tag_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Les étiquettes lues sont celles du décor (`PlantFixtureAiTest`), posées dans la transaction de
 * la méthode : les cas prenaient avant cela les premières du vocabulaire par identifiant, et une
 * liste vide passait pour une première page.
 *
 * Lancement : dev/test package=plant file=TagApiAi
 */
class TagApiAiTest extends PlantFixtureAiTest {

	/** Trois étiquettes du décor, dans l'ordre de leurs identifiants, tel que la liste les rend. */
	private function getTagIds(): array {

		return $this->once('tagIds', function() {

			$ids = [];

			for($i = 0; $i < 3; $i++) {
				$ids[] = $this->createTag()['id'];
			}

			sort($ids);

			return $ids;

		});

	}

	public function testList(): void {

		$eTag = $this->getTag();

		$response = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

		$filtered = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => [$eTag['id']]]);

		$this->assertEquals(200, $filtered['status']);
		$this->assertEquals(1, $filtered['body']['count']);
		$this->assertEquals([$eTag['id']], array_column($filtered['body']['list'], 'id'), 'l\'étiquette du décor est dans la liste');

	}

	/** start est optionnel : l'omettre doit rendre la première page, pas une liste vide. */
	public function testListWithoutStart(): void {

		$eTag = $this->getTag();

		$withoutStart = $this->callApi('GET', 'plantTagList', get: ['limit' => 20, 'ids' => [$eTag['id']]]);
		$firstPage = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => [$eTag['id']]]);

		$this->assertEquals(200, $withoutStart['status']);
		$this->assertEquals([$eTag['id']], array_column($withoutStart['body']['list'], 'id'), 'sans start, l\'étiquette du décor est rendue');
		$this->assertEquals($firstPage['body']['list'], $withoutStart['body']['list'], 'sans start, la liste est la première page');

	}

	public function testRead(): void {

		$eTag = $this->getTag();

		$response = $this->callApi('GET', 'plantTag/{id}', get: ['id' => $eTag['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eTag['name'], $response['body']['name'] ?? NULL);
		$this->assertEquals($eTag['fqn'], $response['body']['fqn'] ?? NULL);

	}

	public function testListByIds(): void {

		$ids = $this->getTagIds();

		$response = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => implode(',', $ids)]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($ids, array_column($response['body']['list'], 'id'));

		// ids est un filtre, pas de la pagination : count doit suivre, sinon un client qui s'en sert
		// pour paginer croit qu'il reste des pages.
		$this->assertEquals(count($ids), $response['body']['count']);

	}

	/** ids[]=1&ids[]=2 doit marcher aussi bien que la forme séparée par des virgules. */
	public function testListByIdsAsArray(): void {

		$ids = $this->getTagIds();

		$response = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => array_map('strval', $ids)]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($ids, array_column($response['body']['list'], 'id'));

	}

	public function testListByIdsEmpty(): void {

		$response = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => '']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([], $response['body']['list']);

	}

	public function testListByIdsUnknown(): void {

		$response = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => '999999999']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([], $response['body']['list']);

	}

	public function testListByIdsInvalidIsRejected(): void {

		$response = $this->callApi('GET', 'plantTagList', get: ['start' => 1, 'limit' => 20, 'ids' => '1,abc,3']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/** Le plafond protège le IN() : sans lui, un client peut faire grossir le SQL sans limite. */
	public function testListByIdsAboveMaxIsRejected(): void {

		$response = $this->callApi('GET', 'plantTagList', get: [
			'start' => 1,
			'limit' => 20,
			'ids' => implode(',', range(1, 501))
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'plantTag/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Vocabulaire commun à toutes les structures : aucune route d'écriture, comme pour les familles.
	 */
	public function testWriteRoutesAreNotExposed(): void {

		$eTag = $this->getTag();

		foreach([
			['POST', 'plantTag', 404],
			['PUT', 'plantTag/{id}', 405],
			['DELETE', 'plantTag/{id}', 405]
		] as [$method, $route, $status]) {

			$response = $this->callApi($method, $route, get: ['id' => $eTag['id']]);

			$this->assertEquals($status, $response['status']);
			$this->assertEquals(match($status) { 404 => 'unknown_route', 405 => 'method_not_allowed' }, $response['body'][0]['code'] ?? NULL);

		}

	}

}
?>
