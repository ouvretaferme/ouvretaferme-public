<?php
namespace work;

/**
 * La fiche d'un produit de traitement (lot L4' de production-finir, planche pf-l4-v3) : la recherche
 * trouve un second nom quelle que soit la casse, et les usages E-Phy se lisent un par culture et par
 * dose, cibles réunies, avec le délai avant récolte le plus court.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(). Le produit est inventé, pour ne
 * pas dépendre du catalogue que le cron recharge chaque semaine.
 *
 * Lancement : dev/test package=work file=PhytoUsageAi
 */
class PhytoUsageAiTest extends \Test {

	public function setUp(): void {
		PhytoProduct::model()->beginTransaction();
	}

	public function tearDown(): void {
		PhytoProduct::model()->rollBack();
	}

	private function createProduct(): PhytoProduct {

		$ePhytoProduct = new PhytoProduct([
			'amm' => 'TEST'.uniqid(),
			'name' => 'Produit de test '.uniqid(),
			'otherNames' => ['SECOND NOM '.uniqid()],
		]);

		PhytoProductLib::create($ePhytoProduct);

		$usages = [
			['Tomate - Aubergine', 'Bactériose(s)', 2.0, 7],
			['Tomate - Aubergine', 'Mildiou(s)', 2.0, 7],
			['Tomate - Aubergine', 'Mildiou(s)', 2.0, 3],
			['Tomate - Aubergine', 'Mildiou(s)', 1.5, 14],
			['Traitements généraux', 'Limaces et escargots', 7.0, NULL],
			['Blé', 'Rouille(s)', 2.0, 30],
		];

		$plantsByCrop = PhytoUsageLib::getPlantsByCrop();

		foreach($usages as [$crop, $target, $dose, $harvestDelay]) {
			PhytoUsageLib::create(new PhytoUsage([
				'product' => $ePhytoProduct,
				'crop' => $crop,
				'treatment' => 'Trt Part.Aer.',
				'target' => $target,
				'dose' => $dose,
				'doseUnit' => 'kg/ha',
				'harvestDelay' => $harvestDelay,
				'plants' => $plantsByCrop[PhytoUsageLib::getCropKey($crop)] ?? [],
			]));
		}

		return $ePhytoProduct;

	}

	public function testSearch(): void {

		$ePhytoProduct = $this->createProduct();

		$otherName = $ePhytoProduct['otherNames'][0];

		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search(mb_strtolower($otherName))->getIds(), 'un second nom en minuscules');
		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search($otherName)->getIds());
		$this->assertEquals([], PhytoProductLib::search($otherName, organic: TRUE)->getIds(), 'une structure bio ne voit pas un produit que le catalogue ne dit pas utilisable en bio');

		PhytoProduct::model()->update($ePhytoProduct, ['organic' => TRUE]);

		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search($otherName, organic: TRUE)->getIds());

		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search($ePhytoProduct['amm'])->getIds(), 'par son AMM');
		$this->assertTrue(PhytoProductLib::search('matériel noté à la main')->empty(), 'une saisie accentuée ne se compare pas à l\'AMM, qui est en ascii');

	}

	/**
	 * Signalé en production le 29/09/2026 : « Essenciel » ne trouvait pas ESSEN'CIEL, et l'utilisateur
	 * lisait le lien de saisie manuelle placé sous le champ comme un refus.
	 */
	public function testSearchIgnoresPunctuation(): void {

		$suffix = strtoupper(uniqid());

		$ePhytoProduct = new PhytoProduct([
			'amm' => 'TEST'.uniqid(),
			'name' => 'ESSEN\'CIEL '.$suffix,
			'otherNames' => ['AURAN-CIEL '.$suffix, 'LIMOIL'],
		]);

		PhytoProductLib::create($ePhytoProduct);

		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search('Essenciel '.$suffix)->getIds(), 'sans l\'apostrophe');
		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search('essen ciel'.$suffix)->getIds(), 'une espace à la place de l\'apostrophe');
		$this->assertEquals([$ePhytoProduct['id']], PhytoProductLib::search('auranciel '.mb_strtolower($suffix))->getIds(), 'un second nom sans son tiret, en minuscules');
		$this->assertEquals([], PhytoProductLib::search($suffix.'LIMOIL')->getIds(), 'une saisie n\'enjambe pas deux seconds noms');

	}

	/**
	 * La même tolérance vaut pour les produits de la structure : sans elle, « Essenciel » ne trouvait
	 * pas le Essen'ciel déjà noté et le champ ne proposait que de le créer une seconde fois.
	 */
	public function testStructureProductIgnoresPunctuation(): void {

		$eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);

		$suffix = strtoupper(uniqid());

		$eTool = new Tool([
			'structure' => $eStructure,
			'name' => 'Essen\'ciel '.$suffix,
			'action' => new Action(),
			'routineName' => 'phyto',
			'routineValue' => [],
		]);

		ToolLib::create($eTool);

		$this->assertEquals([$eTool['id']], ToolLib::getProductsFromQuery('Essenciel '.$suffix, $eStructure, 'phyto')->getIds(), 'sans l\'apostrophe');
		$this->assertEquals([$eTool['id']], ToolLib::getProductsFromQuery('essen ciel'.mb_strtolower($suffix), $eStructure, 'phyto')->getIds(), 'une espace à la place de l\'apostrophe, en minuscules');
		$this->assertEquals([$eTool['id']], ToolLib::getProductsFromQuery('Essen\'ciel '.$suffix, $eStructure, 'phyto')->getIds(), 'telle qu\'écrite');
		$this->assertEquals([], ToolLib::getProductsFromQuery('Essencielle '.$suffix, $eStructure, 'phyto')->getIds());

	}

	/**
	 * Les espèces partagées tomate et carotte, deux espèces d'une structure, l'une copiée de la tomate
	 * et l'autre sans référence, et la culture « Blé », écartée par CROPS_OUTSIDE.
	 */
	private function getPlants(): \Collection {

		$cPlantShared = \plant\Plant::model()
			->select('id', 'fqn', 'reference', 'structure')
			->whereStructure(NULL)
			->whereFqn('IN', ['tomate', 'carotte'])
			->getCollection(index: 'fqn');

		$eStructure = new \structure\Structure(['id' => \structure\FixtureAiLib::F1]);

		return new \Collection([
			1 => $cPlantShared['tomate'],
			2 => $cPlantShared['carotte'],
			3 => new \plant\Plant(['id' => -3, 'fqn' => NULL, 'reference' => $cPlantShared['tomate'], 'structure' => $eStructure]),
			4 => new \plant\Plant(['id' => -4, 'fqn' => NULL, 'reference' => new \plant\Plant(), 'structure' => $eStructure]),
		]);

	}

	public function testGroup(): void {

		$ePhytoProduct = $this->createProduct();

		$cPlant = $this->getPlants();
		$tomato = $cPlant[1]['id'];
		$carrot = $cPlant[2]['id'];

		$usages = [];

		foreach(PhytoUsageLib::getByProductForPlants($ePhytoProduct, $cPlant) as $ePhytoUsage) {
			$usages[] = [$ePhytoUsage['cropName'], $ePhytoUsage['targets'], $ePhytoUsage['dose'], $ePhytoUsage['harvestDelay'], $ePhytoUsage['cPlant']->getColumn('id')];
		}

		$this->assertEquals([
			['Tomate, aubergine', ['Bactériose', 'mildiou'], 2.0, 3, [$tomato, -3]],
			['Tomate, aubergine', ['Mildiou'], 1.5, 14, [$tomato, -3]],
			['Traitements généraux', ['Limaces et escargots'], 7.0, NULL, [$tomato, $carrot, -3, -4]],
		], $usages, 'une ligne par culture et par dose, le délai le plus court, une copie couverte par son espèce de référence, une espèce sans référence par les seuls traitements généraux, et rien pour une culture hors maraîchage');

	}

	public function testPlantsByCrop(): void {

		$plantsByCrop = PhytoUsageLib::getPlantsByCrop();

		$ids = \plant\Plant::model()
			->select('id')
			->whereStructure(NULL)
			->whereFqn('IN', ['tomate', 'aubergine'])
			->sort(['id' => SORT_ASC])
			->getColumn('id');

		$this->assertEquals(2, count($ids));
		$this->assertEquals($ids, $plantsByCrop['tomate, aubergine'], 'les espèces de la table, en ids triés');
		$this->assertEquals(\plant\Plant::model()->whereStructure(NULL)->count(), count($plantsByCrop['traitements généraux']), 'toutes les espèces partagées');
		$this->assertFalse(array_key_exists('blé', $plantsByCrop), 'une culture écartée ne reçoit rien');

	}

	public function testUpdatePlants(): void {

		$ePhytoProduct = new PhytoProduct(['amm' => 'TEST'.uniqid(), 'name' => 'Produit de test '.uniqid(), 'otherNames' => []]);
		PhytoProductLib::create($ePhytoProduct);

		$crop = 'Culture inventée '.uniqid();

		foreach(['Tomate - Aubergine', $crop] as $usageCrop) {
			PhytoUsageLib::create(new PhytoUsage([
				'product' => $ePhytoProduct,
				'crop' => $usageCrop,
				'treatment' => 'Trt Part.Aer.',
				'target' => 'Mildiou(s)',
				'dose' => 1.0,
				'doseUnit' => 'kg/ha',
				'harvestDelay' => NULL,
			]));
		}

		$this->assertEquals([$crop], PhytoUsageLib::updatePlants(), 'la culture que la table ne déclare pas est rendue');

		$plants = PhytoUsage::model()
			->select('crop', 'plants')
			->whereProduct($ePhytoProduct)
			->getColumn('plants', index: 'crop');

		$this->assertEquals(PhytoUsageLib::getPlantsByCrop()['tomate, aubergine'], $plants['Tomate - Aubergine'], 'le recalcul pose les espèces de la table');
		$this->assertEquals([], $plants[$crop], 'une culture non déclarée reste sans espèce');

	}

	public function testDefaultUnit(): void {

		$ePhytoProduct = $this->createProduct();

		$this->assertEquals('kg/ha', PhytoUsageLib::getDefaultUnit($ePhytoProduct));

		foreach(['L/ha', 'L/ha', 'L/ha', 'L/ha', 'L/ha', 'L/ha', 'L/ha', 'g/hL'] as $unit) {
			PhytoUsageLib::create(new PhytoUsage([
				'product' => $ePhytoProduct,
				'crop' => 'Vigne',
				'treatment' => 'Trt Part.Aer.',
				'target' => 'Mildiou(s)',
				'dose' => 1.0,
				'doseUnit' => $unit,
				'harvestDelay' => NULL,
			]));
		}

		$this->assertEquals('L/ha', PhytoUsageLib::getDefaultUnit($ePhytoProduct), 'l\'unité la plus fréquente, toutes cultures confondues');

		$ePhytoProductEmpty = new PhytoProduct(['amm' => 'TEST'.uniqid(), 'name' => 'Sans usage '.uniqid(), 'otherNames' => []]);
		PhytoProductLib::create($ePhytoProductEmpty);

		$this->assertEquals(NULL, PhytoUsageLib::getDefaultUnit($ePhytoProductEmpty));

	}

	public function testWithdrawn(): void {

		$products = implode("\n", [
			'type produit;numero AMM;nom produit;seconds noms commerciaux;titulaire;type commercial;gamme usage;mentions autorisees;restrictions usage;restrictions usage libelle;Substances actives;fonctions;formulations;Etat d’autorisation;Date de retrait du produit;Date de première autorisation;Numéro AMM du produit de référence;Nom du produit de référence;',
			'PPP;1000001;AUTORISE;;;;;Utilisable en agriculture biologique;;;;Fongicide;;AUTORISE;;;;;',
			'PPP;1000002;RETIRE UTILISABLE;;;;;;;;;Fongicide;;RETIRE;15/07/2025;;;;',
			'PPP;1000003;RETIRE PASSE;;;;;;;;;Fongicide;;RETIRE;01/02/2016;;;;',
			'PPP;1000004;RETIRE SANS DATE;;;;;;;;;Fongicide;;RETIRE;01/02/2016;;;;',
			'ADJUVANT;1000005;HORS TYPE;;;;;;;;;;;AUTORISE;;;;;',
		]);

		$usages = implode("\n", [
			'numero AMM;nom produit;identifiant usage; date decision;stade cultural min (BBCH);tade cultural max (BBCH);etat usage;dose retenue;dose retenue unite;delai avant recolte jour;delai avant recolte bbch;nombre max d\'application;date fin distribution;date fin utilisation;condition emploi;ZNT aquatique (en m);ZNT arthropodes non cibles (en m);ZNT plantes non cibles (en m);mentions autorisees;intervalle minimum entre applications (jour);',
			'1000001;AUTORISE;Tomate*Trt Part.Aer.*Mildiou(s);;;;Autorisé;2;kg/ha;3;;;;;;;;;;;',
			'1000001;AUTORISE;Blé*Trt Part.Aer.*Rouille(s);;;;Retrait;2;kg/ha;3;;;;;;;;;;;',
			'1000002;RETIRE UTILISABLE;Tomate*Trt Part.Aer.*Mildiou(s);;;;Retrait;2;kg/ha;3;;;15/01/2026;15/01/2027;;;;;;;',
			'1000002;RETIRE UTILISABLE;Vigne*Trt Part.Aer.*Mildiou(s);;;;Retrait;2;kg/ha;3;;;15/01/2026;15/03/2027;;;;;;;',
			'1000002;RETIRE UTILISABLE;Blé*Trt Part.Aer.*Rouille(s);;;;Retrait;2;kg/ha;3;;;15/01/2020;15/01/2021;;;;;;;',
			'1000003;RETIRE PASSE;Tomate*Trt Part.Aer.*Mildiou(s);;;;Retrait;2;kg/ha;3;;;01/02/2016;01/02/2017;;;;;;;',
			'1000004;RETIRE SANS DATE;Tomate*Trt Part.Aer.*Mildiou(s);;;;Retrait;2;kg/ha;3;;;;;;;;;;;',
		]);

		$read = fn(string $method, ...$arguments) => new \ReflectionMethod(PhytoProductLib::class, $method)->invoke(NULL, ...$arguments);

		$cataloguedProducts = $read('readProducts', $products);

		$this->assertEquals(['1000001', '1000002', '1000003', '1000004'], array_map('strval', array_keys($cataloguedProducts)), 'les retirés sont lus, pas ce qui sort des types');
		$this->assertEquals([PhytoProduct::AUTHORIZED, PhytoProduct::WITHDRAWN], [$cataloguedProducts['1000001']['status'], $cataloguedProducts['1000002']['status']]);

		[$cataloguedUsages, $usableUntil] = $read('readUsages', $usages, $cataloguedProducts, '2026-09-27');

		$this->assertEquals(['1000002' => '2027-03-15'], $usableUntil, 'la plus tardive des fins d\'utilisation, et rien pour un retiré dont les usages sont passés ou sans date');
		$this->assertEquals(['Tomate', 'Vigne'], array_column($cataloguedUsages['1000002'], 'crop'), 'un retiré ne garde que ses usages encore utilisables');
		$this->assertEquals(['Tomate'], array_column($cataloguedUsages['1000001'], 'crop'), 'un autorisé ne garde que ses usages autorisés');

		$this->assertEquals('AMM retirée, utilisable jusqu\'au 15/03/2027', RoutineUi::getPhytoWithdrawn('1000002', new PhytoProduct(['status' => PhytoProduct::WITHDRAWN, 'usableUntil' => '2027-03-15'])));
		$this->assertEquals('AMM retirée', RoutineUi::getPhytoWithdrawn('1000003', new PhytoProduct()), 'une AMM qui n\'est plus au catalogue');
		$this->assertEquals(NULL, RoutineUi::getPhytoWithdrawn(NULL, new PhytoProduct()), 'un produit saisi hors catalogue');
		$this->assertEquals(NULL, RoutineUi::getPhytoWithdrawn('1000001', new PhytoProduct(['status' => PhytoProduct::AUTHORIZED, 'usableUntil' => NULL])));

	}

	public function testLabel(): void {

		$this->assertEquals('Tomate, aubergine', PhytoUsageLib::getLabel('Tomate - Aubergine'));
		$this->assertEquals('Mildiou', PhytoUsageLib::getLabel('Mildiou(s)'));
		$this->assertEquals('Mousses, lichens, algues', PhytoUsageLib::getLabel('Mousses, lichens, algues (1)'));
		$this->assertEquals('Locx Struct. Matér. (POV...)', PhytoUsageLib::getLabel('Locx Struct. Matér. (POV...)'), 'une parenthèse qui n\'est ni un pluriel ni un renvoi reste');

	}

}
?>
