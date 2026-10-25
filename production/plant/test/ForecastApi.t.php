<?php
namespace plant;

/**
 * Test bout-en-bout de l'API publique plant\Forecast (production/plant/api/Forecast_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * L'espèce des prévisions est celle du décor (`PlantFixtureAiTest`), posée dans la transaction de
 * la méthode ; la liste ne s'affirme que sur la prévision que le cas a posée.
 *
 * Lancement : dev/test package=plant file=ForecastApi
 */
class ForecastApiTest extends PlantFixtureAiTest {

	/**
	 * La saison sur laquelle le fichier travaille, lue sur la structure et non écrite en dur.
	 *
	 * `Configuration::checkSeason()` refuse une saison hors de [`seasonFirst`, `seasonLast`], et
	 * `MemberLib::getDynamicSeason()` retombe alors en silence sur `seasonLast` : une année figée
	 * finit par écrire sur une saison et relire sur une autre, sans que rien ne le signale.
	 * `seasonLast` est la seule valeur qu'aucune horloge ne périme.
	 */
	protected int $season;

	public function init(): void {

		parent::init();

		$this->season = $this->eStructure->conf(\map\Configuration::class)['seasonLast'];

	}


	protected function createForecast(Plant $ePlant, array $override = []): array {

		$create = $this->callApi('POST', 'forecast', body: array_merge([
			'season' => $this->season,
			'plant' => $ePlant['id'],
			'unit' => 'kg',
			'quantity' => 100,
			'privatePrice' => 2.5,
			'privatePart' => 70,
			'proPrice' => 3.5,
			'proPart' => 30
		], $override));

		return $create['body'];

	}

	public function testList(): void {

		$eForecast = $this->createForecast($this->getPlant());

		$response = $this->callApi('GET', 'forecastList', get: ['start' => 1, 'limit' => 20, 'season' => $this->season]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

		$filtered = $this->callApi('GET', 'forecastList', get: ['season' => $this->season, 'ids' => [$eForecast['id']]]);

		$this->assertEquals(200, $filtered['status']);
		$this->assertEquals(1, $filtered['body']['count']);
		$this->assertEquals([$eForecast['id']], array_column($filtered['body']['list'], 'id'), 'la prévision du décor est dans la liste de sa saison');

	}

	public function testCreateReadUpdateDelete(): void {

		$ePlant = $this->createPlant();
		$eForecast = $this->createForecast($ePlant);

		$this->assertEquals($this->season, $eForecast['season'] ?? NULL);
		$this->assertEquals($ePlant['id'], $eForecast['plant'] ?? NULL);
		$this->assertTrue(($eForecast['id'] ?? NULL) > 0);

		$id = $eForecast['id'];

		$read = $this->callApi('GET', 'forecast/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($ePlant['id'], $read['body']['plant'] ?? NULL);

		$update = $this->callApi('PUT', 'forecast/{id}', body: [
			'quantity' => 200,
			'privatePrice' => 2.0,
			'privatePart' => 50,
			'proPrice' => 3.0,
			'proPart' => 50
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(200, $update['body']['quantity'] ?? NULL);

		$delete = $this->callApi('DELETE', 'forecast/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'forecast/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	/**
	 * Forecast_0_1betaApi::getStandalonePropertiesUpdate() groupe ['privatePart', 'proPart'] : un PUT
	 * standalone doit pouvoir mettre à jour un champ hors groupe seul (ici 'quantity'), sans exiger
	 * privatePart/proPart.
	 */
	public function testStandaloneUpdateSingleField(): void {

		$eForecast = $this->createForecast($this->createPlant());

		$update = $this->callApi('PUT', 'forecast/{id}', body: [
			'properties' => ['quantity'],
			'quantity' => 999
		], get: ['id' => $eForecast['id']]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(999, $update['body']['quantity'] ?? NULL);
		// privatePart n'a pas été touché par le PUT standalone
		$this->assertEquals(70, $update['body']['privatePart'] ?? NULL);

	}

	/**
	 * 'privatePart' et 'proPart' sont groupés (doivent totaliser 100) : un PUT standalone qui n'en cite
	 * qu'un seul doit être rejeté, cf ModuleApi::validateStandalonePropertiesUpdate().
	 */
	public function testStandaloneUpdateGroupPartialIsRejected(): void {

		$eForecast = $this->createForecast($this->createPlant());

		$response = $this->callApi('PUT', 'forecast/{id}', body: [
			'properties' => ['privatePart'],
			'privatePart' => 80
		], get: ['id' => $eForecast['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * 'plant' fait partie de ForecastLib::getPropertiesCreate() mais pas de getPropertiesUpdate()
	 * (non modifiable après création) : doit être rejeté en standalone, pas silencieusement accepté.
	 */
	public function testStandaloneUpdateUnknownPropertyIsRejected(): void {

		$eForecast = $this->createForecast($this->createPlant());

		$response = $this->callApi('PUT', 'forecast/{id}', body: [
			'properties' => ['plant'],
			'plant' => $this->createPlant()['id']
		], get: ['id' => $eForecast['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'forecast/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		// privatePart + proPart != 100 : rejeté par Forecast::build()
		$response = $this->callApi('POST', 'forecast', body: [
			'plant' => $this->createPlant()['id'],
			'unit' => 'kg',
			'quantity' => 100,
			'privatePrice' => 2.5,
			'privatePart' => 70,
			'proPrice' => 3.5,
			'proPart' => 20
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
