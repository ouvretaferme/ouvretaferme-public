<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\Gauge (production/toolbox/api/Gauge_0_1beta.a.php).
 *
 * Chaque cas pose ses zones et ses pluviomètres dans la transaction de la méthode, sur la structure du
 * décor. Les témoins d'une autre structure se lisent dans la base, le cas ne demandant qu'une ligne
 * que la borne de structure doit écarter.
 *
 * Lancement : dev/test package=toolbox file=GaugeApi
 */
class GaugeApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new GaugeModel();
	}

	private function createZone(): \map\Zone {

		// `createdBy` prend l'utilisateur en ligne, que la colonne exige
		\user\ConnectionLib::setOnline($this->getAdmin());

		$eZone = new \map\Zone([
			'name' => 'Zone API '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build() : il jugerait le nom, la surface, le tracé et les saisons que saisit l'utilisateur, le cas ne demande qu'un lieu où poser un pluviomètre
		\map\ZoneLib::create($eZone);

		return $eZone;

	}

	/** Un pluviomètre actif sur une zone neuve, posé par l'API. */
	private function createGauge(): Gauge {

		$response = $this->callApi('POST', 'gauge', body: ['zone' => $this->createZone()['id']]);

		$this->assertEquals(201, $response['status'], 'le pluviomètre du cas se crée');

		return Gauge::model()
			->select(Gauge::getSelection())
			->whereId($response['body']['id'])
			->get();

	}

	/** Une zone d'une autre structure : l'étape `owned` doit la refuser. */
	private function getZoneForeign(): \map\Zone {

		return \map\Zone::model()
			->select(['id', 'structure'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	/** Un pluviomètre d'une autre structure : la ressource ne doit jamais le rendre. */
	private function getGaugeForeign(): Gauge {

		return Gauge::model()
			->select(['id', 'structure'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	public function testList(): void {

		$gaugeIds = [$this->createGauge()['id'], $this->createGauge()['id']];

		$expected = Gauge::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('GET', 'gaugeList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les pluviomètres de la structure');

		$ids = Gauge::model()
			->whereStructure($this->eStructure)
			->getColumn('id');

		$outside = array_diff(array_column($response['body']['list'], 'id'), $ids);

		$this->assertEquals([], array_values($outside), 'la liste ne rend aucun pluviomètre hors de la structure');
		$this->assertEquals([], array_values(array_diff($gaugeIds, array_column($response['body']['list'], 'id'))), 'la liste rend les pluviomètres posés par le cas');
		$this->assertEquals(['id', 'zone', 'status'], array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs');

	}

	public function testListFilterStatus(): void {

		$eGaugeActive = $this->createGauge();
		$eGaugeInactive = $this->createGauge();

		$this->callApi('PUT', 'gauge/{id}', body: ['properties' => ['status'], 'status' => Gauge::INACTIVE], get: ['id' => $eGaugeInactive['id']]);

		$expected = Gauge::model()
			->whereStructure($this->eStructure)
			->whereStatus(Gauge::ACTIVE)
			->count();

		$response = $this->callApi('GET', 'gaugeList', get: ['status' => Gauge::ACTIVE, 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count']);

		$statuses = array_unique(array_column($response['body']['list'], 'status'));

		$this->assertEquals([Gauge::ACTIVE], array_values($statuses), 'le filtre ne laisse passer que le statut demandé');
		$this->assertTrue(in_array($eGaugeActive['id'], array_column($response['body']['list'], 'id')), 'le pluviomètre actif passe le filtre');
		$this->assertFalse(in_array($eGaugeInactive['id'], array_column($response['body']['list'], 'id')), 'le pluviomètre retiré ne le passe pas');

	}

	public function testListFilterZone(): void {

		$eGauge = $this->createGauge();
		$this->createGauge();

		$response = $this->callApi('GET', 'gaugeList', get: ['zone' => $eGauge['zone']['id'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$eGauge['id']], array_column($response['body']['list'], 'id'), 'le filtre ne rend que le pluviomètre de la zone');

	}

	public function testRead(): void {

		$eGauge = $this->createGauge();

		$response = $this->callApi('GET', 'gauge/{id}', get: ['id' => $eGauge['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eGauge['id'], $response['body']['id']);
		$this->assertEquals($eGauge['zone']->empty() ? NULL : $eGauge['zone']['id'], $response['body']['zone']);
		$this->assertEquals($eGauge['status'], $response['body']['status']);

	}

	public function testReadForeign(): void {

		$eGaugeForeign = $this->getGaugeForeign();

		if($this->assertTrue($eGaugeForeign->notEmpty(), 'la base porte un pluviomètre hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'gauge/{id}', get: ['id' => $eGaugeForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateOnZone(): void {

		$eZone = $this->createZone();

		$response = $this->callApi('POST', 'gauge', body: ['zone' => $eZone['id']]);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals($eZone['id'], $response['body']['zone']);
		$this->assertEquals(Gauge::ACTIVE, $response['body']['status']);

		$eGauge = Gauge::model()
			->select(['structure', 'zone', 'status'])
			->whereId($response['body']['id'])
			->get();

		$this->assertTrue($eGauge->notEmpty(), 'le pluviomètre créé se relit en base');
		$this->assertEquals($this->eStructure['id'], $eGauge['structure']['id'], 'le pluviomètre est posé sur la structure du jeton');

		// Le lieu est désormais pris : l'étape `placed` refuse le second, comme elle le fait dans l'écran.
		$again = $this->callApi('POST', 'gauge', body: ['zone' => $eZone['id']]);

		$this->assertEquals(400, $again['status']);
		$this->assertEquals('Gauge::zone.placed', $again['body'][0]['code'] ?? NULL);

	}

	public function testCreateOnFarm(): void {

		$placed = Gauge::model()
			->whereStructure($this->eStructure)
			->whereStatus(Gauge::ACTIVE)
			->whereZone(NULL)
			->count();

		if($placed > 0) {
			$this->assertTrue(TRUE, 'la ferme porte déjà un pluviomètre, le cas de la ferme libre ne se joue pas');
			return;
		}

		$response = $this->callApi('POST', 'gauge', body: ['zone' => NULL]);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(NULL, $response['body']['zone'], 'le pluviomètre de la ferme sort sans zone, comme il est entré');

		$eGauge = Gauge::model()
			->select(['zone'])
			->whereId($response['body']['id'])
			->get();

		$this->assertTrue($eGauge['zone']->empty(), 'la ferme est bien une absence de zone en base');

	}

	public function testCreateZoneForeign(): void {

		$eZoneForeign = $this->getZoneForeign();

		if($this->assertTrue($eZoneForeign->notEmpty(), 'la base porte une zone hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'gauge', body: ['zone' => $eZoneForeign['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Gauge::zone.owned', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateWithoutZone(): void {

		// Un corps qui ne porte pas toutes les propriétés de la liste est refusé avant build().
		$response = $this->callApi('POST', 'gauge', body: []);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Gauge::zone.missing', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateMove(): void {

		$eGauge = $this->createGauge();
		$eZone = $this->createZone();

		// `properties` restreint la modification au seul lieu : le corps complet exigerait `status`, et
		// le seul statut que l'élément accepte est `inactive`, ce qui retirerait le pluviomètre.
		$response = $this->callApi('PUT', 'gauge/{id}', body: ['properties' => ['zone'], 'zone' => $eZone['id']], get: ['id' => $eGauge['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eZone['id'], $response['body']['zone']);

		$this->assertEquals($eZone['id'], Gauge::model()->whereId($eGauge['id'])->getValue('zone')['id'], 'le pluviomètre a changé de lieu en base');

	}

	public function testUpdateDelete(): void {

		$eGauge = $this->createGauge();

		$response = $this->callApi('PUT', 'gauge/{id}', body: ['properties' => ['status'], 'status' => Gauge::INACTIVE], get: ['id' => $eGauge['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(Gauge::INACTIVE, $response['body']['status']);

		// Un pluviomètre retiré ne se modifie plus : c'est `acceptUpdate()`, la seule garde que l'API
		// interroge, les `can*` n'étant jamais appelés derrière une clé.
		$again = $this->callApi('PUT', 'gauge/{id}', body: ['properties' => ['status'], 'status' => Gauge::INACTIVE], get: ['id' => $eGauge['id']]);

		$this->assertEquals(412, $again['status']);
		$this->assertEquals('accept_update', $again['body'][0]['code'] ?? NULL);

	}

	public function testUpdateActiveRefused(): void {

		$eZone = $this->createZone();

		$created = $this->callApi('POST', 'gauge', body: ['zone' => $eZone['id']]);

		// Le seul changement de statut est la suppression : repasser un pluviomètre actif poserait un
		// second pluviomètre au même endroit sans passer par l'étape `placed`.
		$response = $this->callApi('PUT', 'gauge/{id}', body: ['properties' => ['status'], 'status' => Gauge::ACTIVE], get: ['id' => $created['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Gauge::status.deleted', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateForeign(): void {

		$eGaugeForeign = $this->getGaugeForeign();

		if($this->assertTrue($eGaugeForeign->notEmpty(), 'la base porte un pluviomètre hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('PUT', 'gauge/{id}', body: ['properties' => ['status'], 'status' => Gauge::INACTIVE], get: ['id' => $eGaugeForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testDeleteRouteAbsent(): void {

		// La suppression d'un pluviomètre est un changement de statut : aucune route DELETE n'existe.
		$response = $this->callApi('DELETE', 'gauge/{id}', get: ['id' => $this->createGauge()['id']]);

		$this->assertEquals(405, $response['status']);
		$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

}
?>
