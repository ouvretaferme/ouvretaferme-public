<?php
namespace map;

/**
 * Test bout-en-bout de l'API publique map\Bed (production/map/api/Bed_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=map file=BedApiAi
 */
class BedApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new BedModel();
	}

	/** Crée une parcelle puis un jardin d'accueil par l'API, pour ne dépendre d'aucune donnée en place. */
	private function createPlot(): int {

		$zone = $this->callApi('POST', 'zone', body: [
			'name' => 'Parcelle hôte',
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$plot = $this->callApi('POST', 'plot', body: [
			'zone' => $zone['body']['id'],
			'name' => 'Jardin hôte',
			'mode' => Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		return $plot['body']['id'];

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'bedList', get: ['season' => date('Y'), 'start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	public function testCreateReadUpdateDelete(): void {

		$plot = $this->createPlot();

		$create = $this->callApi('POST', 'bed', body: [
			'plot' => $plot,
			'name' => 'p1',
			'length' => 25,
			'width' => 200
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('p1', $create['body']['name'] ?? NULL);
		$this->assertEquals($plot, $create['body']['plot'] ?? NULL);
		// area = length * width / 100, calculée en base
		$this->assertEquals(50.0, $create['body']['area'] ?? NULL);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'bed/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('p1', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'bed/{id}', body: [
			'properties' => ['name'],
			'name' => 'p2'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('p2', $update['body']['name'] ?? NULL);

		$delete = $this->callApi('DELETE', 'bed/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(404, $this->callApi('GET', 'bed/{id}', get: ['id' => $id])['status']);

	}

	/**
	 * Créer un jardin crée aussi une planche de remplissage (PlotLib::create()), qui porte la culture
	 * posée sur le jardin entier. Elle n'a ni nom ni dimensions et ne doit pas ressortir de l'API,
	 * cf Bed_0_1betaApi::getNewModel().
	 */
	public function testFillBedIsHidden(): void {

		$plot = $this->createPlot();

		$list = $this->callApi('GET', 'bedList', get: ['season' => date('Y'), 'plot' => $plot, 'start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(0, $list['body']['count']);

		$eBedFill = Bed::model()
			->select(['id'])
			->wherePlot($plot)
			->wherePlotFill(TRUE)
			->get();

		$this->assertTrue($eBedFill->notEmpty());
		$this->assertEquals(404, $this->callApi('GET', 'bed/{id}', get: ['id' => $eBedFill['id']])['status']);

	}

	/** Le filtre par jardin ne doit rendre que les planches de ce jardin. */
	public function testListByPlot(): void {

		$plot = $this->createPlot();

		$this->callApi('POST', 'bed', body: ['plot' => $plot, 'name' => 'p1', 'length' => 25, 'width' => 200]);
		$this->callApi('POST', 'bed', body: ['plot' => $plot, 'name' => 'p2', 'length' => 25, 'width' => 200]);

		$list = $this->callApi('GET', 'bedList', get: ['season' => date('Y'), 'plot' => $plot, 'start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(2, $list['body']['count']);

	}

	/**
	 * Bed::build() vérifie le jardin contre la structure de la planche : un membre de deux structures
	 * ne doit pas pouvoir poser une planche dans le jardin de l'autre.
	 */
	public function testPlotOfAnotherStructureIsRejected(): void {

		$ePlotOther = Plot::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->get();

		if($this->assertDecorPresent($ePlotOther->notEmpty(), 'une parcelle d\'une autre structure que la '.$this->eStructure['id']) === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'bed', body: [
			'plot' => $ePlotOther['id'],
			'name' => 'p1',
			'length' => 25,
			'width' => 200
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/** Deux planches d'un même jardin ne peuvent pas porter le même nom (Bed::build(), name.duplicate). */
	public function testDuplicateNameInPlotIsRejected(): void {

		$plot = $this->createPlot();

		$this->callApi('POST', 'bed', body: ['plot' => $plot, 'name' => 'p1', 'length' => 25, 'width' => 200]);

		$response = $this->callApi('POST', 'bed', body: ['plot' => $plot, 'name' => 'p1', 'length' => 25, 'width' => 200]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * Le filtre par parcelle passe par Bed.zone, que Bed::build() déduit du jardin posté : une
	 * planche créée sans mentionner sa parcelle doit malgré tout y être rattachée.
	 */
	public function testListByZone(): void {

		$plot = $this->createPlot();

		$ePlot = Plot::model()->select(['id', 'zone'])->whereId($plot)->get();

		$this->callApi('POST', 'bed', body: ['plot' => $plot, 'name' => 'p1', 'length' => 25, 'width' => 200]);

		$list = $this->callApi('GET', 'bedList', get: ['season' => date('Y'), 'zone' => $ePlot['zone']['id']]);

		$this->assertEquals(200, $list['status']);
		$this->assertEquals(1, $list['body']['count']);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'bed/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testAlleyWidth(): void {

		$plot = $this->createPlot();

		$create = $this->callApi('POST', 'bed', body: [
			'plot' => $plot,
			'name' => 'pp1',
			'length' => 25,
			'width' => 100,
			'alleyWidth' => 30
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(30, $create['body']['alleyWidth'] ?? NULL);

		$id = $create['body']['id'];

		$update = $this->callApi('PUT', 'bed/{id}', get: ['id' => $id], body: [
			'plot' => $plot,
			'name' => 'pp1',
			'length' => 25,
			'width' => 100,
			'alleyWidth' => 60,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(60, $update['body']['alleyWidth'] ?? NULL);

		$clear = $this->callApi('PUT', 'bed/{id}', get: ['id' => $id], body: [
			'plot' => $plot,
			'name' => 'pp1',
			'length' => 25,
			'width' => 100,
			'alleyWidth' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		]);

		$this->assertEquals(200, $clear['status']);

		$read = $this->callApi('GET', 'bed/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(NULL, $read['body']['alleyWidth'] ?? NULL);

	}

	public function testAlleyWidthOptional(): void {

		$plot = $this->createPlot();

		$create = $this->callApi('POST', 'bed', body: [
			'plot' => $plot,
			'name' => 'pp2',
			'length' => 25,
			'width' => 100,
			'alleyWidth' => 30
		]);

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$update = $this->callApi('PUT', 'bed/{id}', get: ['id' => $id], body: [
			'plot' => $plot,
			'name' => 'pp3',
			'length' => 25,
			'width' => 100,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('pp3', $update['body']['name'] ?? NULL);
		$this->assertEquals(30, $update['body']['alleyWidth'] ?? NULL);

	}

}
?>
