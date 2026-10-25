<?php
namespace structure;

/**
 * Test bout-en-bout de l'API publique structure\Production (base/structure/api/Production_0_1beta.a.php).
 *
 * Lancement : dev/test package=structure file=ProductionApiAi
 */
class ProductionApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ProductionModel();
	}

	/**
	 * Le cache de ProductionLib vit dans redis, hors de la transaction : une lecture faite après une
	 * création y garderait 24 h un atelier que le retour arrière a effacé, et que le fichier suivant
	 * lirait sur le décor.
	 */
	public function tearDown(): void {

		parent::tearDown();

		ProductionLib::deleteCache($this->eStructure);

	}

	/** Un type d'atelier que la structure de référence n'a pas encore, la clé (structure, fqn) étant unique. */
	protected function getFreeFqn(): string {

		$used = Production::model()
			->select(['fqn'])
			->whereStructure($this->eStructure)
			->getCollection()
			->getColumn('fqn');

		foreach(Production::getOptions('systemFarming') as $fqn) {

			if(in_array($fqn, $used) === FALSE) {
				return $fqn;
			}

		}

		throw new \Exception('La structure de référence utilise déjà tous les types d\'atelier');

	}

	protected function createProduction(): array {

		return $this->callApi('POST', 'production', body: [
			'fqn' => $this->getFreeFqn(),
			'area' => NULL,
			'areaGreenhouse' => NULL,
			'organic' => 'no',
			'startedAt' => NULL,
			'description' => NULL,
			'mechanisation' => NULL
		]);

	}

	public function testList(): void {

		$create = $this->createProduction();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('GET', 'productionList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));
		$this->assertTrue(in_array($create['body']['id'], array_column($response['body']['list'], 'id')), 'la liste rend l\'atelier créé');

	}

	public function testCreateReadUpdateDelete(): void {

		$fqn = $this->getFreeFqn();

		$create = $this->callApi('POST', 'production', body: [
			'fqn' => $fqn,
			'area' => NULL,
			'areaGreenhouse' => NULL,
			'organic' => 'yes',
			'startedAt' => 2020,
			'description' => 'Atelier de test API',
			'mechanisation' => NULL
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals($fqn, $create['body']['fqn'] ?? NULL);
		$this->assertEquals('yes', $create['body']['organic'] ?? NULL);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'production/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Atelier de test API', $read['body']['description'] ?? NULL);

		$update = $this->callApi('PUT', 'production/{id}', body: [
			'properties' => ['organic', 'description'],
			'organic' => 'conversion',
			'description' => 'Atelier modifié'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('conversion', $update['body']['organic'] ?? NULL);
		$this->assertEquals('Atelier modifié', $update['body']['description'] ?? NULL);

		$delete = $this->callApi('DELETE', 'production/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'production/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);

	}

	/**
	 * fqn est un text8 côté schéma mais un vocabulaire fermé côté métier : sans vérification, l'API
	 * écrirait une valeur qu'acceptOption() ne reconnaît nulle part.
	 */
	public function testUnknownFqnIsRejected(): void {

		$response = $this->callApi('POST', 'production', body: [
			'fqn' => 'nimportequoi',
			'area' => NULL,
			'areaGreenhouse' => NULL,
			'organic' => 'no',
			'startedAt' => NULL,
			'description' => NULL,
			'mechanisation' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Production::fqn.check', $response['body'][0]['code'] ?? NULL);

	}

	/** UNIQUE (structure, fqn) : le doublon doit rendre une erreur métier, pas une 500. */
	public function testDuplicateFqnIsRejected(): void {

		$fqn = $this->getFreeFqn();

		$body = [
			'fqn' => $fqn,
			'area' => NULL,
			'areaGreenhouse' => NULL,
			'organic' => 'no',
			'startedAt' => NULL,
			'description' => NULL,
			'mechanisation' => NULL
		];

		$this->assertEquals(201, $this->callApi('POST', 'production', body: $body)['status']);

		$response = $this->callApi('POST', 'production', body: $body);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Production::fqn.duplicate', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * fqn n'est pas modifiable : il décide des champs que l'atelier accepte.
	 */
	public function testFqnIsNotUpdatable(): void {

		$this->assertFalse(in_array('fqn', ProductionLib::getPropertiesUpdate()));

		$create = $this->createProduction();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('PUT', 'production/{id}', body: [
			'properties' => ['fqn'],
			'fqn' => 'apiculture'
		], get: ['id' => $create['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * ProductionLib met en cache la liste par structure pendant 24 h : une écriture par l'API doit
	 * l'invalider, sinon la modification reste invisible côté web.
	 */
	public function testWriteInvalidatesCache(): void {

		$fqn = $this->getFreeFqn();

		ProductionLib::getByStructure($this->eStructure);

		$create = $this->callApi('POST', 'production', body: [
			'fqn' => $fqn,
			'area' => NULL,
			'areaGreenhouse' => NULL,
			'organic' => 'no',
			'startedAt' => NULL,
			'description' => NULL,
			'mechanisation' => NULL
		]);

		$this->assertEquals(201, $create['status']);

		$cProduction = ProductionLib::getByStructure($this->eStructure);

		$this->assertTrue($cProduction->offsetExists($create['body']['id']));

	}

	/**
	 * Signalé par une utilisatrice boulangère : les ateliers d'artisanat étaient proposés dans le
	 * sélecteur mais refusés par fqn.check, qui ne validait que les productions agricoles.
	 */
	public function testCraftFqnIsAccepted(): void {

		$used = Production::model()
			->select(['fqn'])
			->whereStructure($this->eStructure)
			->getCollection()
			->getColumn('fqn');

		$craft = array_values(array_diff(Production::CRAFT, $used));

		$this->assertTrue($craft !== []);

		$response = $this->callApi('POST', 'production', body: [
			'fqn' => $craft[0],
			'area' => NULL,
			'areaGreenhouse' => NULL,
			'organic' => 'no',
			'startedAt' => NULL,
			'description' => NULL,
			'mechanisation' => NULL
		]);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals($craft[0], $response['body']['fqn'] ?? NULL);

	}

	/**
	 * Le bug venait d'une divergence entre le vocabulaire validé et celui proposé dans le
	 * sélecteur : tout fqn affichable doit être acceptable, et réciproquement.
	 */
	public function testAllFqnAreSelectable(): void {

		$selectable = [];

		foreach(ProductionUi::getCategories() as ['productions' => $productions]) {
			$selectable = array_merge($selectable, $productions);
		}

		$selectable = array_merge($selectable, Production::OTHER);

		sort($selectable);

		$all = Production::getAll();
		sort($all);

		$this->assertEquals($all, $selectable);
		$this->assertEquals($all, array_values(array_diff($all, array_diff($all, array_keys(ProductionUi::getList())))));

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'production/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
