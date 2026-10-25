<?php
namespace toolbox;

/**
 * Les produits autorisés (lot F4 de production-finir, planche pf-catalogue-phyto) : une cible réunit
 * les intitulés que le catalogue sépare, la liste ne garde que les produits autorisés hors gamme
 * amateur, se range par substance du plus fourni au moins fourni, compte une fois le produit à deux
 * substances, et reconnaît les produits que la structure a déjà.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode. Les produits et leur cible sont
 * inventés, pour ne pas dépendre du catalogue que le cron recharge chaque semaine.
 *
 * Lancement : dev/test package=toolbox file=Catalog
 */
class CatalogTest extends \dev\TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return \work\PhytoProduct::model();
	}

	private function getStructure(): \structure\Structure {

		return \structure\StructureLib::getById(
			\structure\Feature::model()
				->sort(['id' => SORT_ASC])
				->getValue('structure')
		);

	}

	/**
	 * Cinq produits sur une cible inventée : deux au soufre, un au cuivre et au soufre, un amateur et
	 * un retiré, qui ne doivent jamais paraître. Le mixte porte deux fonctions dans la même colonne,
	 * dont une inventée, pour qu'un filtre par fonction ne ramène que lui.
	 */
	private function createProducts(string $target, string $function = 'Fongicide'): array {

		$sulphur = ['international' => 'sulphur', 'name' => 'soufre'];
		$copper = ['international' => 'copper', 'name' => 'cuivre'];

		$products = [
			'soufreA' => [[$sulphur], TRUE, FALSE, \work\PhytoProduct::AUTHORIZED, [[2.0, 3], [3.0, 5]]],
			'soufreB' => [[$sulphur], FALSE, FALSE, \work\PhytoProduct::AUTHORIZED, [[1.0, NULL]]],
			'mixte' => [[$copper, $sulphur], TRUE, FALSE, \work\PhytoProduct::AUTHORIZED, [[4.0, 1]]],
			'amateur' => [[$sulphur], FALSE, TRUE, \work\PhytoProduct::AUTHORIZED, [[1.0, 1]]],
			'retire' => [[$sulphur], TRUE, FALSE, \work\PhytoProduct::WITHDRAWN, [[1.0, 1]]],
		];

		$created = [];

		foreach($products as $key => [$substances, $organic, $amateur, $status, $usages]) {

			$ePhytoProduct = new \work\PhytoProduct([
				'amm' => 'TEST'.uniqid(),
				'name' => 'PRODUIT '.strtoupper($key).' '.uniqid(),
				'otherNames' => [],
				'functions' => ($key === 'mixte') ? 'Fongicide, '.$function : 'Fongicide',
				'organic' => $organic,
				'amateur' => $amateur,
				'substances' => $substances,
				'status' => $status,
			]);

			\work\PhytoProductLib::create($ePhytoProduct);

			foreach($usages as $position => [$dose, $harvestDelay]) {
				\work\PhytoUsageLib::create(new \work\PhytoUsage([
					'product' => $ePhytoProduct,
					'crop' => 'Tomate - Aubergine',
					'treatment' => 'Trt Part.Aer.',
					// Le second usage porte l'intitulé à renvoi de note, que la cible doit réunir au premier.
					'target' => ($position === 0) ? $target.'(s)' : $target.' (1)',
					'dose' => $dose,
					'doseUnit' => 'kg/ha',
					'harvestDelay' => $harvestDelay,
					'plants' => [],
				]));
			}

			$created[$key] = $ePhytoProduct;

		}

		return $created;

	}

	public function testProducts(): void {

		$eStructure = $this->getStructure();
		$target = 'Cible de test '.uniqid();

		$products = $this->createProducts($target);

		$filters = CatalogLib::getFilters($eStructure, ['target' => $target, 'organic' => '0']);

		$this->assertEquals($target, $filters['target'], 'la cible lue sans pluriel ni renvoi de note doit être reconnue');

		$result = CatalogLib::getProducts($eStructure, $filters);

		$this->assertEquals(3, $result['count'], 'le produit à deux substances compte une fois, l\'amateur et le retiré sont écartés');
		$this->assertEquals(['soufre', 'cuivre'], array_column($result['groups'], 'name'), 'du groupe le plus fourni au moins fourni');
		$this->assertEquals(3, $result['groups'][0]['cPhytoProduct']->count(), 'le mixte paraît aussi sous le soufre');

		$ePhytoProductA = $result['groups'][0]['cPhytoProduct']->find(fn($e) => $e['id'] === $products['soufreA']['id'])->first();
		$this->assertEquals([], $ePhytoProductA['crops'], 'aucune culture du produit ne couvre une espèce de la structure');
		$this->assertEquals(['names' => ['Tomate, aubergine'], 'doses' => ['kg/ha' => [2.0, 3.0]], 'harvestDelays' => [3, 5]], $ePhytoProductA['cropsOther'], 'les deux intitulés de la cible sont réunis');
		$this->assertTrue($ePhytoProductA['organic']);
		$this->assertEquals(NULL, $ePhytoProductA['tool']);

		$ePhytoProductB = $result['groups'][0]['cPhytoProduct']->find(fn($e) => $e['id'] === $products['soufreB']['id'])->first();
		$this->assertEquals([], $ePhytoProductB['cropsOther']['harvestDelays'], 'sans délai dans le catalogue, aucun délai');

		$filters = CatalogLib::getFilters($eStructure, ['target' => $target, 'organic' => '1']);
		$this->assertEquals(2, CatalogLib::getProducts($eStructure, $filters)['count'], 'le filtre bio écarte le produit conventionnel');

		$filters = CatalogLib::getFilters($eStructure, ['target' => $target, 'product' => $products['amateur']['amm'], 'organic' => '0']);
		$this->assertTrue($filters['product']->empty(), 'un produit amateur ne peut pas être posé en critère');

	}

	/**
	 * Piste C de pf-catalogue-usage : une culture qui couvre une espèce de la structure prend sa ligne,
	 * avec sa dose, son délai et ses autres cibles ; une cible qui n'est pas celle posée n'entre ni dans
	 * la dose ni dans le délai ; les autres cultures se résument en plages.
	 */
	public function testProductsGrownFirst(): void {

		$eStructure = $this->getStructure();
		$target = 'Cible de test '.uniqid();

		$grown = new \ReflectionMethod(CatalogLib::class, 'getGrown')->invoke(NULL, $eStructure);
		$this->assertTrue($grown !== [], 'la structure du test doit cultiver au moins une espèce');

		$products = $this->createProducts($target);

		foreach([[$target, 5.0, 7], ['Autre cible', 9.0, 30]] as [$usageTarget, $dose, $harvestDelay]) {
			\work\PhytoUsageLib::create(new \work\PhytoUsage([
				'product' => $products['soufreA'],
				'crop' => 'Oignon',
				'treatment' => 'Trt Part.Aer.',
				'target' => $usageTarget,
				'dose' => $dose,
				'doseUnit' => 'L/ha',
				'harvestDelay' => $harvestDelay,
				'plants' => [$grown[0]],
			]));
		}

		$filters = CatalogLib::getFilters($eStructure, ['target' => $target, 'organic' => '0']);
		$ePhytoProductA = CatalogLib::getProducts($eStructure, $filters)['groups'][0]['cPhytoProduct']->find(fn($e) => $e['id'] === $products['soufreA']['id'])->first();

		$this->assertEquals([[
			'name' => 'Oignon',
			'plant' => 'oignon',
			'targets' => ['Autre cible'],
			'doses' => ['L/ha' => [5.0, 5.0]],
			'harvestDelays' => [7, 7],
		]], $ePhytoProductA['crops'], 'la cible posée donne la dose et le délai, l\'autre ne fait que se nommer');
		$this->assertEquals(['Tomate, aubergine'], $ePhytoProductA['cropsOther']['names']);

		$filters = CatalogLib::getFilters($eStructure, ['organic' => '0']);
		$filters['product'] = $products['soufreA'];
		$ePhytoProductA = CatalogLib::getProducts($eStructure, $filters)['groups'][0]['cPhytoProduct']->first();

		$this->assertEquals([$target, 'Autre cible'], $ePhytoProductA['crops'][0]['targets'], 'sans cible posée, la culture porte toutes les siennes');
		$this->assertEquals(['L/ha' => [5.0, 9.0]], $ePhytoProductA['crops'][0]['doses']);
		$this->assertEquals([7, 30], $ePhytoProductA['crops'][0]['harvestDelays']);

	}

	public function testFunction(): void {

		$eStructure = $this->getStructure();
		$target = 'Cible de test '.uniqid();
		$function = 'Fonction'.uniqid();

		$products = $this->createProducts($target, $function);

		$filters = CatalogLib::getFilters($eStructure, ['function' => $function, 'organic' => '0']);
		$this->assertEquals($function, $filters['function'], 'une fonction portée en seconde position de la colonne est reconnue');

		$result = CatalogLib::getProducts($eStructure, $filters);
		$this->assertEquals(1, $result['count'], 'seul le mixte porte la fonction');
		$this->assertEquals($products['mixte']['id'], $result['groups'][0]['cPhytoProduct']->first()['id']);

		$filters = CatalogLib::getFilters($eStructure, ['function' => 'Fonction qui n\'existe pas', 'organic' => '0']);
		$this->assertEquals(NULL, $filters['function'], 'une fonction inconnue est ignorée');

		$filters = CatalogLib::getFilters($eStructure, ['organic' => '0']);
		$suggestions = CatalogLib::getSuggestions($eStructure, $filters, mb_substr($function, 0, 12));
		$this->assertEquals([$function], $suggestions['functions'], 'la fonction se propose par le début de son nom');

		$suggestions = CatalogLib::getSuggestions($eStructure, $filters, 'fongi');
		$this->assertTrue(in_array('Fongicide', $suggestions['functions']));

	}

	/**
	 * Sans critère, la liste porte tout le catalogue ; la page compte des produits, et un groupe coupé
	 * reprend son intertitre avec son compte entier.
	 */
	public function testPages(): void {

		$eStructure = $this->getStructure();

		$filters = CatalogLib::getFilters($eStructure, ['organic' => '0']);

		$first = CatalogLib::getProducts($eStructure, $filters);
		$this->assertTrue($first['count'] > ToolboxSetting::CATALOG_PER_PAGE, 'sans critère, tout le catalogue');
		$this->assertEquals((int)ceil($first['count'] / ToolboxSetting::CATALOG_PER_PAGE), $first['pages']);

		$ids = fn(array $result) => array_unique(array_merge(...array_map(fn($group) => $group['cPhytoProduct']->getIds(), $result['groups'])));

		$this->assertEquals(ToolboxSetting::CATALOG_PER_PAGE, count($ids($first)), 'cent produits par page, un produit à deux substances compté une fois');

		$second = CatalogLib::getProducts($eStructure, $filters, 1);
		$this->assertEquals([], array_intersect($ids($first), $ids($second)), 'aucun produit sur deux pages');

		$last = end($first['groups']);
		$continued = $second['groups'][0];

		if($last['name'] === $continued['name']) {
			$this->assertEquals($last['count'], $continued['count'], 'un groupe coupé garde son compte entier');
			$this->assertTrue($last['cPhytoProduct']->count() + $continued['cPhytoProduct']->count() <= $last['count']);
		}

		$beyond = CatalogLib::getProducts($eStructure, $filters, $first['pages']);
		$this->assertEquals([], $beyond['groups'], 'au-delà de la dernière page, rien');

	}

	/**
	 * La fiche ouverte depuis une recherche sur l'ail garde l'ail, et ne le reprend pas quand la
	 * structure le cultive déjà.
	 */
	public function testProductPlant(): void {

		$eStructure = $this->getStructure();
		$target = 'Cible de test '.uniqid();

		$products = $this->createProducts($target);

		$ePlant = \plant\Plant::model()
			->select('id', 'fqn', 'name', 'reference', 'structure')
			->whereStructure(NULL)
			->whereFqn('tomate')
			->get();

		\work\PhytoUsage::model()
			->whereProduct($products['soufreA'])
			->update(['plants' => new \Sql('JSON_ARRAY('.(int)$ePlant['id'].')')]);

		$ePhytoProduct = CatalogLib::getProduct($eStructure, $products['soufreA']['amm'], $ePlant);

		// Deux doses sur la même culture E-Phy font deux lignes.
		$this->assertEquals(2, $ePhytoProduct['cPhytoUsage']->count());

		foreach($ePhytoProduct['cPhytoUsage'] as $ePhytoUsage) {
			$this->assertEquals(['Tomate'], $ePhytoUsage['cPlant']->getColumn('name'), 'l\'espèce cherchée une seule fois, cultivée ou non');
		}

	}

	public function testStructureProduct(): void {

		$eStructure = $this->getStructure();
		$target = 'Cible de test '.uniqid();

		$products = $this->createProducts($target);

		$eTool = \work\ToolLib::createPhytoFromCatalog($eStructure, $products['mixte'], ['dose' => 4, 'unit' => 'kg/ha']);

		$filters = CatalogLib::getFilters($eStructure, ['target' => $target, 'organic' => '0']);
		$result = CatalogLib::getProducts($eStructure, $filters);

		foreach($result['groups'] as $group) {
			foreach($group['cPhytoProduct'] as $ePhytoProduct) {
				$this->assertEquals(($ePhytoProduct['id'] === $products['mixte']['id']) ? $eTool['id'] : NULL, $ePhytoProduct['tool']);
			}
		}

		$this->assertEquals('/outil/'.$eTool['id'], CatalogUi::urlProduct($eStructure, $result['groups'][1]['cPhytoProduct']->first(), new \plant\Plant()));

	}

	public function testSuggestions(): void {

		$eStructure = $this->getStructure();
		$target = 'Cible de test '.uniqid();

		$products = $this->createProducts($target);

		$filters = CatalogLib::getFilters($eStructure, ['organic' => '0']);

		$suggestions = CatalogLib::getSuggestions($eStructure, $filters, $target);
		$this->assertEquals([$target], array_values($suggestions['targets']), 'une seule cible pour les deux intitulés');

		$suggestions = CatalogLib::getSuggestions($eStructure, $filters, $products['soufreA']['name']);
		$this->assertEquals([$products['soufreA']['id']], $suggestions['cPhytoProduct']->getIds());

		$suggestions = CatalogLib::getSuggestions($eStructure, $filters, $products['amateur']['name']);
		$this->assertEquals(0, $suggestions['cPhytoProduct']->count(), 'un produit amateur n\'est pas proposé');

		$suggestions = CatalogLib::getSuggestions($eStructure, $filters, 'c');
		$this->assertEquals(0, $suggestions['cPhytoProduct']->count(), 'sous deux caractères, aucun produit');

	}

	/**
	 * La désignation d'une souche n'est pas le nom de la substance : « sou » trouve le soufre, et non
	 * « Ampelomyces quisqualis souche AQ10 » (retour de M-Prod du 29/09). Le nom international se
	 * cherche comme le nom français.
	 */
	public function testSuggestionsIgnoreStrain(): void {

		$eStructure = $this->getStructure();
		$filters = CatalogLib::getFilters($eStructure, ['organic' => '0']);

		$ePhytoProduct = new \work\PhytoProduct([
			'amm' => 'TEST'.uniqid(),
			'name' => 'PRODUIT MICROBIEN '.uniqid(),
			'otherNames' => [],
			// La souche s'écrit « souche », « Souche » ou « SOUCHE: », et « strain » dans le nom international.
			'substances' => [
				['international' => 'Ampelomyces quisqualis zyx'.uniqid().' strain SOU10', 'name' => 'ampelomyces quisqualis souche AQ10'],
				['international' => 'Bacillus thuringiensis zyx'.uniqid(), 'name' => 'Bacillus thuringiensis SOUCHE: SOU-1857'],
			],
			'status' => \work\PhytoProduct::AUTHORIZED,
		]);

		\work\PhytoProductLib::create($ePhytoProduct);

		$found = fn(string $query) => in_array($ePhytoProduct['id'], CatalogLib::getSuggestions($eStructure, $filters, $query, 1000)['cPhytoProduct']->getIds());

		$this->assertFalse($found('sou'), 'la souche ne doit pas se trouver par « sou »');
		$this->assertFalse($found('aq10'), 'la désignation de la souche ne se cherche pas');
		$this->assertTrue($found('quisq'), 'le nom de la substance se trouve par le début d\'un mot');
		$this->assertTrue($found('zyx'), 'le nom international se trouve aussi');

	}

	public function testCropPlant(): void {

		$this->assertEquals('oignon', \work\PhytoUsageLib::getCropPlant('Oignon'), 'l\'espèce que la culture nomme');
		$this->assertEquals('pomme', \work\PhytoUsageLib::getCropPlant('Fruits à pépins'));
		$this->assertEquals(NULL, \work\PhytoUsageLib::getCropPlant('Cultures légumières'), 'une culture qui réunit sans désigner');
		$this->assertEquals(NULL, \work\PhytoUsageLib::getCropPlant('Traitements généraux'));
		$this->assertEquals('ble', \work\PhytoUsageLib::getCropPlant('Blé'), 'hors du référentiel, mais dessiné');
		$this->assertEquals(NULL, \work\PhytoUsageLib::getCropPlant('Rosier'), 'ni dans le référentiel ni dessiné');

		$missing = array_diff(\work\PhytoUsageLib::CROPS_GROUPS, array_keys(\work\PhytoUsageLib::CROPS));
		$this->assertEquals([], $missing, 'CROPS_GROUPS nomme une culture absente de CROPS : '.implode(', ', $missing));

	}

	public function testReadSubstances(): void {

		$this->assertEquals(
			[['soufre micronisé', 'Sulphur'], ['hydroxyde de cuivre', 'copper hydroxide']],
			\work\PhytoProductLib::readSubstances('soufre micronisé (Sulphur) 80.0 %  | hydroxyde de cuivre (copper hydroxide) 300.0 g/kg')
		);

	}

}
?>
