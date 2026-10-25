<?php
namespace work;

/**
 * Test bout-en-bout de la ressource work\PhytoProduct (production/work/api/PhytoProduct_0_1beta.a.php).
 *
 * Le catalogue E-Phy est un référentiel national : la ressource n'est bornée par aucune structure, et
 * c'est ce que ce fichier vérifie d'abord, en comparant le compte rendu par la liste au compte de la
 * table entière. Rien n'y est écrit, la table n'ayant qu'un auteur, le rechargement hebdomadaire.
 *
 * Lancement : dev/test package=work file=PhytoProductApiAi
 */
class PhytoProductApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PhytoProductModel();
	}

	private function getProduct(): PhytoProduct {

		return PhytoProduct::model()
			->select(PhytoProduct::getSelection())
			->sort(['id' => SORT_ASC])
			->get();

	}

	private function requireProduct(): bool {

		return $this->assertTrue($this->getProduct()->notEmpty(), 'le catalogue E-Phy est chargé en base');

	}

	/** Un identifiant que la table ne porte pas, pour éprouver le 404. */
	private function getIdUnknown(): int {

		return (int)PhytoProduct::model()->select(['id' => new \Sql('MAX(id)', 'int')])->get()['id'] + 1;

	}

	public function testListIsTheWholeCatalog(): void {

		$expected = PhytoProduct::model()->count();

		$response = $this->callApi('GET', 'phytoProductList', get: ['limit' => 5]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste rend le catalogue entier, qu\'aucune structure ne borne');

		if($response['body']['list'] !== []) {

			$this->assertEquals(
				['id', 'amm', 'name', 'otherNames', 'functions', 'formulation', 'organic', 'amateur', 'substances', 'status', 'usableUntil'],
				array_keys($response['body']['list'][0]),
				'la ressource rend exactement ces champs'
			);

		}

	}

	public function testListFilterAmm(): void {

		if($this->requireProduct() === FALSE) {
			return;
		}

		$ePhytoProduct = $this->getProduct();

		$response = $this->callApi('GET', 'phytoProductList', get: ['amm' => $ePhytoProduct['amm']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1, $response['body']['count'], 'l\'AMM est unique au catalogue');
		$this->assertEquals($ePhytoProduct['id'], $response['body']['list'][0]['id']);

	}

	public function testListFilterStatus(): void {

		$expected = PhytoProduct::model()
			->whereStatus(PhytoProduct::WITHDRAWN)
			->count();

		$response = $this->callApi('GET', 'phytoProductList', get: ['status' => PhytoProduct::WITHDRAWN, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count']);

		$statuses = array_unique(array_column($response['body']['list'], 'status'));

		if($statuses !== []) {
			$this->assertEquals([PhytoProduct::WITHDRAWN], array_values($statuses));
		}

	}

	public function testListFilterOrganic(): void {

		$expected = PhytoProduct::model()
			->whereOrganic(TRUE)
			->count();

		$response = $this->callApi('GET', 'phytoProductList', get: ['organic' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count']);

		$organic = array_unique(array_column($response['body']['list'], 'organic'));

		if($organic !== []) {
			$this->assertEquals([TRUE], array_values($organic));
		}

	}

	public function testListSearch(): void {

		if($this->requireProduct() === FALSE) {
			return;
		}

		$ePhytoProduct = $this->getProduct();

		// La recherche est celle de la Boîte à outils : elle trouve par le nom, un second nom ou le
		// début de l'AMM. Le nom complet d'un produit du catalogue doit donc le rendre.
		$response = $this->callApi('GET', 'phytoProductList', get: ['search' => $ePhytoProduct['name'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(in_array($ePhytoProduct['id'], array_column($response['body']['list'], 'id'), TRUE), 'la recherche par nom rend le produit cherché');

		$expected = PhytoProduct::model()
			->whereName('LIKE', '%'.$ePhytoProduct['name'].'%')
			->count();

		$this->assertTrue($response['body']['count'] >= $expected, 'la recherche ne rend pas moins que le seul nom');

		// Et elle sait ne rien trouver : sans ce contrôle, un filtre qui ne s'applique pas rendrait
		// le catalogue entier sans que rien ne le signale.
		$nothing = $this->callApi('GET', 'phytoProductList', get: ['search' => 'zzzzaucunproduitzzzz']);

		$this->assertEquals(200, $nothing['status']);
		$this->assertEquals(0, $nothing['body']['count']);

	}

	public function testListSearchByAmm(): void {

		if($this->requireProduct() === FALSE) {
			return;
		}

		$ePhytoProduct = $this->getProduct();

		$response = $this->callApi('GET', 'phytoProductList', get: ['search' => $ePhytoProduct['amm'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(in_array($ePhytoProduct['id'], array_column($response['body']['list'], 'id'), TRUE), 'la recherche par AMM rend le produit cherché');

	}

	public function testRead(): void {

		if($this->requireProduct() === FALSE) {
			return;
		}

		$ePhytoProduct = $this->getProduct();

		$response = $this->callApi('GET', 'phytoProduct/{id}', get: ['id' => $ePhytoProduct['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($ePhytoProduct['amm'], $response['body']['amm']);
		$this->assertEquals($ePhytoProduct['name'], $response['body']['name']);
		$this->assertEquals($ePhytoProduct['status'], $response['body']['status']);
		$this->assertEquals($ePhytoProduct['substances'], $response['body']['substances'], 'les substances sortent telles que le catalogue les a réunies');

	}

	public function testReadUnknown(): void {

		$response = $this->callApi('GET', 'phytoProduct/{id}', get: ['id' => $this->getIdUnknown()]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testWriteRoutesAbsent(): void {

		if($this->requireProduct() === FALSE) {
			return;
		}

		$id = $this->getProduct()['id'];

		// Le catalogue n'a qu'un auteur : aucune route d'écriture n'est déclarée, et l'absence se
		// vérifie route par route plutôt que par la lecture du fichier.
		foreach([
			['POST', 'phytoProduct', [], 404],
			['PUT', 'phytoProduct/{id}', ['id' => $id], 405],
			['DELETE', 'phytoProduct/{id}', ['id' => $id], 405]
		] as [$method, $route, $get, $status]) {

			$response = $this->callApi($method, $route, get: $get);

			$this->assertEquals($status, $response['status'], $method.' '.$route.' n\'est pas une route');
			$this->assertEquals(match($status) { 404 => 'unknown_route', 405 => 'method_not_allowed' }, $response['body'][0]['code'] ?? NULL, $method.' '.$route.' n\'est pas une route');

		}

	}

}
?>
