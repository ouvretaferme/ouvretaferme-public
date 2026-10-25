<?php
namespace dev;

/**
 * Les codes HTTP que la documentation publique promet pour les refus du socle : une route absente
 * rend 404, une route qui existe sous une autre méthode 405, un identifiant non numérique 400 avec le
 * refus de l'argument, et un corps vide vaut l'objet vide au lieu d'être refusé pour lui-même.
 *
 * Avant ce test, les trois premiers cas rendaient 400, 400 et 500 `missing_argument`, et une action
 * sans argument appelée sans corps rendait 400 `missing_body`, alors que la référence publiait ces
 * actions sans corps.
 *
 * Lancement : dev/test package=dev file=ApiStatusCodeAi
 */
class ApiStatusCodeAiTest extends ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new \api\LogModel();
	}

	public function testUnknownRouteIsNotFound(): void {

		$response = $this->callApi('GET', 'devRouteThatDoesNotExist');

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_route', $response['body'][0]['code'] ?? NULL);

	}

	public function testRouteUnderAnotherMethodIsNotAllowed(): void {

		$response = $this->callApi('DELETE', 'gaugeList');

		$this->assertEquals(405, $response['status']);
		$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

	/** Contrôle du contrôle : la même route sous sa méthode passe la résolution. */
	public function testRouteUnderItsMethodIsResolved(): void {

		$response = $this->callApi('GET', 'gaugeList');

		$this->assertEquals(200, $response['status']);

	}

	public function testNonNumericIdIsRefusedAsArgument(): void {

		$response = $this->callApi('GET', 'gauge/{id}', get: ['id' => 'abc']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('id.check', $response['body'][0]['code'] ?? NULL);

	}

	/** Un corps vide va jusqu'aux propriétés, qui disent chacune ce qui manque. */
	public function testEmptyBodyIsAnEmptyObject(): void {

		$response = $this->callApi('POST', 'gauge', '');

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);
		$this->assertTrue(str_ends_with($response['body'][0]['code'] ?? '', '.missing'), json_encode($response['body']));

	}

}
?>
