<?php
namespace work;

/**
 * Test bout-en-bout de la ressource work\PhytoUsage (production/work/api/PhytoUsage_0_1beta.a.php).
 *
 * Même régime que le produit qui les porte : un référentiel national, que la ressource ne borne par
 * aucune structure et sur lequel elle n'écrit pas. Le décor est le catalogue en base, et les attendus
 * se recalculent depuis la table.
 *
 * Lancement : dev/test package=work file=PhytoUsageApiAi
 */
class PhytoUsageApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PhytoUsageModel();
	}

	private function getUsage(): PhytoUsage {

		return PhytoUsage::model()
			->select(PhytoUsage::getSelection())
			->sort(['id' => SORT_ASC])
			->get();

	}

	private function requireUsage(): bool {

		return $this->assertTrue($this->getUsage()->notEmpty(), 'le catalogue E-Phy est chargé en base');

	}

	/** Un usage dont la culture désigne des espèces du référentiel partagé. */
	private function getUsageWithPlants(): PhytoUsage {

		return PhytoUsage::model()
			->select(PhytoUsage::getSelection())
			->where('JSON_LENGTH(plants) > 0')
			->sort(['id' => SORT_ASC])
			->get();

	}

	private function getIdUnknown(): int {

		return (int)PhytoUsage::model()->select(['id' => new \Sql('MAX(id)', 'int')])->get()['id'] + 1;

	}

	public function testListIsTheWholeCatalog(): void {

		$expected = PhytoUsage::model()->count();

		$response = $this->callApi('GET', 'phytoUsageList', get: ['limit' => 5]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste rend les usages du catalogue entier, qu\'aucune structure ne borne');

		if($response['body']['list'] !== []) {

			$this->assertEquals(
				['id', 'product', 'crop', 'treatment', 'target', 'dose', 'doseUnit', 'harvestDelay', 'plants'],
				array_keys($response['body']['list'][0]),
				'la ressource rend exactement ces champs'
			);

		}

	}

	public function testListFilterProduct(): void {

		if($this->requireUsage() === FALSE) {
			return;
		}

		$ePhytoUsage = $this->getUsage();

		$expected = PhytoUsage::model()
			->whereProduct($ePhytoUsage['product'])
			->sort(['id' => SORT_ASC])
			->getColumn('id');

		$response = $this->callApi('GET', 'phytoUsageList', get: ['product' => $ePhytoUsage['product']['id'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, array_column($response['body']['list'], 'id'), 'le filtre rend exactement les usages du produit');

		$products = array_unique(array_column($response['body']['list'], 'product'));

		$this->assertEquals([$ePhytoUsage['product']['id']], array_values($products));

	}

	public function testListFilterCrop(): void {

		if($this->requireUsage() === FALSE) {
			return;
		}

		$ePhytoUsage = $this->getUsage();

		$expected = PhytoUsage::model()
			->whereCrop($ePhytoUsage['crop'])
			->count();

		$response = $this->callApi('GET', 'phytoUsageList', get: ['crop' => $ePhytoUsage['crop'], 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count']);

		$crops = array_unique(array_column($response['body']['list'], 'crop'));

		if($crops !== []) {
			$this->assertEquals([$ePhytoUsage['crop']], array_values($crops));
		}

		// Et le filtre sait ne rien trouver : une culture que le catalogue n'écrit pas rend zéro, sans
		// quoi une condition qui ne s'applique pas rendrait la table entière.
		$nothing = $this->callApi('GET', 'phytoUsageList', get: ['crop' => 'Culture qui n\'existe pas']);

		$this->assertEquals(200, $nothing['status']);
		$this->assertEquals(0, $nothing['body']['count']);

	}

	public function testRead(): void {

		if($this->requireUsage() === FALSE) {
			return;
		}

		$ePhytoUsage = $this->getUsage();

		$response = $this->callApi('GET', 'phytoUsage/{id}', get: ['id' => $ePhytoUsage['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($ePhytoUsage['product']['id'], $response['body']['product']);
		$this->assertEquals($ePhytoUsage['crop'], $response['body']['crop']);
		$this->assertEquals($ePhytoUsage['target'], $response['body']['target']);
		$this->assertEquals($ePhytoUsage['dose'], $response['body']['dose']);
		$this->assertEquals($ePhytoUsage['harvestDelay'], $response['body']['harvestDelay']);

	}

	public function testReadPlants(): void {

		$ePhytoUsage = $this->getUsageWithPlants();

		if($this->assertTrue($ePhytoUsage->notEmpty(), 'un usage du catalogue désigne des espèces du référentiel partagé') === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'phytoUsage/{id}', get: ['id' => $ePhytoUsage['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($ePhytoUsage['plants'], $response['body']['plants']);

		// Les espèces citées sont celles du référentiel partagé, que l'API expose déjà : un client peut
		// donc les relire, ce qui ne serait pas vrai d'espèces propres à une structure.
		$shared = \plant\Plant::model()
			->whereId('IN', $ePhytoUsage['plants'])
			->whereStructure(NULL)
			->count();

		$this->assertEquals(count($ePhytoUsage['plants']), $shared, 'chaque espèce citée est une espèce du référentiel partagé');

	}

	public function testReadUnknown(): void {

		$response = $this->callApi('GET', 'phytoUsage/{id}', get: ['id' => $this->getIdUnknown()]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testWriteRoutesAbsent(): void {

		if($this->requireUsage() === FALSE) {
			return;
		}

		$id = $this->getUsage()['id'];

		foreach([
			['POST', 'phytoUsage', [], 404],
			['PUT', 'phytoUsage/{id}', ['id' => $id], 405],
			['DELETE', 'phytoUsage/{id}', ['id' => $id], 405]
		] as [$method, $route, $get, $status]) {

			$response = $this->callApi($method, $route, get: $get);

			$this->assertEquals($status, $response['status'], $method.' '.$route.' n\'est pas une route');
			$this->assertEquals(match($status) { 404 => 'unknown_route', 405 => 'method_not_allowed' }, $response['body'][0]['code'] ?? NULL, $method.' '.$route.' n\'est pas une route');

		}

	}

}
?>
