<?php
namespace structure;

/**
 * Test bout-en-bout de l'API publique structure\System (base/structure/api/System_0_1beta.a.php).
 *
 * Ressource unique par structure : pas de liste, pas de création, pas de suppression, et la route
 * ne porte pas d'id.
 *
 * Lancement : dev/test package=structure file=SystemApiAi
 */
class SystemApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new SystemModel();
	}

	public function testRead(): void {

		$eSystem = SystemLib::getByStructure($this->eStructure);

		$response = $this->callApi('GET', 'system');

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eSystem['id'], $response['body']['id'] ?? NULL);
		$this->assertEquals($eSystem['soil'], $response['body']['soil'] ?? NULL);

	}

	public function testReadExposesNothingElse(): void {

		$response = $this->callApi('GET', 'system');

		$this->assertEquals([
			'id',
			'place', 'placeLngLat', 'totalArea', 'altitude', 'soil', 'ph',
			'hrFarmer', 'hrEmployee', 'hrOther',
			'sellingDirect', 'sellingSemi', 'sellingLong'
		], array_keys($response['body']));

	}

	public function testStandaloneUpdate(): void {

		$update = $this->callApi('PUT', 'system', body: [
			'properties' => ['altitude', 'ph'],
			'altitude' => 320,
			'ph' => 6.5
		]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(320, $update['body']['altitude'] ?? NULL);
		$this->assertEquals(6.5, $update['body']['ph'] ?? NULL);

		$eSystem = SystemLib::getByStructure($this->eStructure);

		$this->assertEquals(320, $eSystem['altitude']);

	}

	/** La répartition des circuits de vente doit faire 100 %. */
	public function testSellingRepartitionMustSumTo100(): void {

		$response = $this->callApi('PUT', 'system', body: [
			'properties' => ['sellingDirect', 'sellingSemi', 'sellingLong'],
			'sellingDirect' => 50,
			'sellingSemi' => 30,
			'sellingLong' => 30
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

		$valid = $this->callApi('PUT', 'system', body: [
			'properties' => ['sellingDirect', 'sellingSemi', 'sellingLong'],
			'sellingDirect' => 50,
			'sellingSemi' => 30,
			'sellingLong' => 20
		]);

		$this->assertEquals(200, $valid['status']);
		$this->assertEquals(50, $valid['body']['sellingDirect'] ?? NULL);

	}

	/** Les trois parts sont groupées : en modifier une seule doit être refusé. */
	public function testSellingPartialGroupIsRejected(): void {

		$response = $this->callApi('PUT', 'system', body: [
			'properties' => ['sellingDirect'],
			'sellingDirect' => 100
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * productionsList sort des propriétés modifiables quand l'origine est l'API : son callback lit
	 * $_POST, qui n'existe pas sur un appel JSON.
	 */
	public function testProductionsListIsNotWritable(): void {

		$this->assertFalse(in_array('productionsList', SystemLib::getPropertiesUpdate('api')));
		$this->assertTrue(in_array('productionsList', SystemLib::getPropertiesUpdate()));

		$response = $this->callApi('PUT', 'system', body: [
			'properties' => ['productionsList'],
			'productionsList' => []
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testWriteRoutesAreNotExposed(): void {

		foreach([['POST', 'system', 405, 'method_not_allowed'], ['DELETE', 'system', 405, 'method_not_allowed'], ['GET', 'systemList', 404, 'unknown_route']] as [$method, $route, $status, $code]) {

			$response = $this->callApi($method, $route);

			$this->assertEquals($status, $response['status']);
			$this->assertEquals($code, $response['body'][0]['code'] ?? NULL);

		}

	}

}
?>
