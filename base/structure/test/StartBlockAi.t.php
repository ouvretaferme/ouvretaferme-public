<?php
namespace structure;

/**
 * Le bloc « À essayer » de l'accueil (`structure-start`) : les entrées du catalogue de la page
 * d'arrivée que la ferme n'a jamais essayées, et rien quand elle les a toutes essayées.
 *
 * Monté sur F1. Les sept drapeaux de `structureFeature`, et `hasSales` que lit le premier pas, se
 * posent sur la ligne que `Structure::conf()` garde en mémoire, sans écrire en base. Le site n'a pas de drapeau : il s'écrit dans la transaction.
 *
 * Lancement : dev/test package=structure file=StartBlockAi
 */
class StartBlockAiTest extends \dev\TransactionTest {

	const STRUCTURE = FixtureAiLib::F1;

	/** Les drapeaux que le bloc lit, par entrée du catalogue. */
	const array FLAGS = [
		'shop' => 'hasShops',
		'invoice' => 'hasInvoices',
		'pdp' => 'hasPdp',
		'market' => 'hasMarket',
		'accounting' => 'hasAccounting',
		'planning' => 'hasCultivations',
		'quotation' => 'hasQuotation',
	];

	protected Structure $eStructure;

	private array $appsInitial = [];

	private array $observersInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return new \website\WebsiteModel();
	}

	public function init(): void {

		$this->eStructure = StructureLib::getById(self::STRUCTURE);

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

	}

	public function setUp(): void {

		parent::setUp();

		$this->eStructure = StructureLib::getById(self::STRUCTURE);
		$this->setFlags([]);

		$this->appsInitial = \Lime::getApps();
		$this->observersInitial = \Package::getObservers();

	}

	public function tearDown(): void {

		\Lime::setApps($this->appsInitial);
		\Package::setObservers($this->observersInitial);

		parent::tearDown();

	}

	/**
	 * Chaque entrée est proposée tant que son critère est éteint, et disparaît quand il s'allume. Les
	 * autres restent : un critère ne parle que de son entrée.
	 */
	public function testEachFlagRemovesOnlyItsEntry(): void {

		foreach(self::FLAGS as $name => $flag) {

			$this->setFlags([$flag]);

			$remaining = $this->getRemaining($this->eStructure);

			$this->assertFalse(in_array($name, $remaining, TRUE), $flag.' allumé : l\'entrée '.$name.' n\'est plus proposée');
			$this->assertTrue(in_array('website', $remaining, TRUE), $flag.' allumé : le site reste proposé');

		}

	}

	/**
	 * Une production désactivée ne se repropose pas sur l'accueil : la carte rouvrirait un univers que la
	 * structure a fermé (L67, relevé sur 5522). Les autres entrées restent.
	 */
	public function testADisabledProductionIsNotProposed(): void {

		$this->eStructure->conf(Feature::class)['production'] = FALSE;

		$remaining = $this->getRemaining($this->eStructure);

		$this->assertFalse(in_array('planning', $remaining, TRUE), 'production désactivée : la planification n\'est plus proposée');
		$this->assertTrue(in_array('website', $remaining, TRUE), 'le site reste proposé');

	}

	/**
	 * L'attendu se lit dans le catalogue de la page d'arrivée : une entrée qui y entre sans critère dans
	 * `DashboardLib::getStart()` ne serait jamais proposée, et c'est ici que ça se voit.
	 */
	public function testEverythingOffProposesTheCatalogue(): void {

		$remaining = $this->getRemaining($this->eStructure);

		$expected = [];

		foreach(new StructureUi()->getStartedFeatures($this->eStructure) as $feature) {

			if($feature['display'] ?? TRUE) {
				$expected[] = $feature['name'];
			}

		}

		$this->assertEquals($expected, $remaining, 'rien d\'essayé : tout le catalogue, dans son ordre');

	}

	/**
	 * Sans commercialisation/, comme dans la version publique, la boutique, les factures et la caisse
	 * mèneraient à des écrans absents : le catalogue ne les montre pas, et le bloc les tient pour
	 * essayées.
	 */
	public function testWithoutCommercialisationTheSellingEntriesLeave(): void {

		\Lime::setApps(array_values(array_diff($this->appsInitial, ['commercialisation'])));

		$remaining = $this->getRemaining($this->eStructure);

		foreach(['shop', 'invoice', 'market'] as $name) {
			$this->assertFalse(in_array($name, $remaining, TRUE), $name.' : pas proposé sans commercialisation/');
		}

		$this->assertTrue(in_array('website', $remaining, TRUE), 'le site reste proposé');

		$displayed = [];

		foreach(new StructureUi()->getStartedFeatures($this->eStructure) as $feature) {
			if($feature['display'] ?? TRUE) {
				$displayed[] = $feature['name'];
			}
		}

		$this->assertEquals([], array_intersect(['shop', 'invoice', 'market'], $displayed), 'la page d\'arrivée ne les montre pas non plus');

	}

	/**
	 * La page d'arrivée sans commercialisation/ : les invitations d'AMAP ne sont pas demandées, la page
	 * se rend sans les observateurs de l'AMAP, et « Passer cette étape » ne mène pas aux ventes.
	 */
	public function testWithoutCommercialisationTheStartPageStandsAlone(): void {

		\Lime::setApps(array_values(array_diff($this->appsInitial, ['commercialisation'])));

		$observers = \Package::getObservers();
		unset($observers['lib']['structure']['loadStart'], $observers['ui']['structure']['getStartedInvitations'], $observers['ui']['structure']['getStartedJoins'], $observers['ui']['structure']['getStartedStepUrls']);
		\Package::setObservers($observers);

		StructureLib::loadStart($this->eStructure, NULL);

		$this->assertFalse($this->eStructure->offsetExists('cInvite'), 'aucune invitation n\'est demandée à l\'AMAP');

		$html = new StructureUi()->getStarted($this->eStructure);

		$this->assertTrue(str_contains($html, 'href="'.StructureUi::url($this->eStructure).'" class="btn btn-lg btn-outline-primary"'), '« Passer cette étape » mène à l\'accueil');
		$this->assertFalse(str_contains($html, 'href="'.StructureUi::urlSellingSales($this->eStructure).'"'), 'et non aux ventes');

	}

	public function testAWebsiteRemovesTheWebsite(): void {

		$this->createWebsite();

		$this->assertFalse(in_array('website', $this->getRemaining($this->eStructure), TRUE), 'un site créé : l\'entrée du site n\'est plus proposée');

	}

	/** Tout essayé, le bloc n'a rien à dire et disparaît. */
	public function testEverythingTriedSaysNothing(): void {

		$this->setFlags(array_values(self::FLAGS));

		$this->createWebsite();

		$this->assertEquals(NULL, DashboardLib::load('structure-start', $this->eStructure));

	}

	/**
	 * Le catalogue est celui d'une ferme ou d'une boutique : la déclaration du bloc le refuse à un groupe
	 * et à une AMAP, et le panneau de personnalisation ne le leur propose donc pas.
	 */
	public function testOnlyAProducerOrAShopGetsTheCatalogue(): void {

		$this->assertEquals('assertStartedFeatures', DashboardLib::getBlocks()['structure-start']['right'], 'le droit du bloc est celui que ce test éprouve');

		$this->assertTrue($this->eStructure->acceptStartedFeatures(), 'une ferme');
		$this->assertFalse(StructureLib::getById(FixtureAiLib::G1)->acceptStartedFeatures(), 'un groupe');
		$this->assertFalse(StructureLib::getById(FixtureAiLib::A1)->acceptStartedFeatures(), 'une AMAP');
		$this->assertTrue(StructureLib::getById(FixtureAiLib::B1)->acceptStartedFeatures(), 'une boutique');

	}

	/**
	 * Une boutique ne reçoit que les entrées des univers qu'elle peut ouvrir : ni la production, ni les
	 * relevés de prix, qui mèneraient à un écran qui la refuse.
	 */
	public function testAShopIsOfferedOnlyItsUniverses(): void {

		$remaining = $this->getRemaining(StructureLib::getById(FixtureAiLib::B1));

		$this->assertFalse(in_array('planning', $remaining, TRUE), 'pas de planification des cultures');
		$this->assertFalse(in_array('quotation', $remaining, TRUE), 'pas de relevés de prix');

	}

	/**
	 * Le premier pas suit l'ordre des premiers essais (`StructureSetting::START_STEPS`), réduit à ce qui
	 * reste : rien d'essayé, les cultures ; les cultures faites, la vente ; une production désactivée,
	 * la vente aussi (L68).
	 */
	public function testTheStepFollowsTheFirstTries(): void {

		$this->assertEquals('planning', DashboardLib::load('structure-start', $this->eStructure)['step'], 'rien d\'essayé : les cultures');

		$this->setFlags(['hasCultivations']);
		$this->assertEquals('invoice', DashboardLib::load('structure-start', $this->eStructure)['step'], 'les cultures faites : la vente');

		$this->setFlags([]);
		$this->eStructure->conf(Feature::class)['production'] = FALSE;
		$this->assertEquals('invoice', DashboardLib::load('structure-start', $this->eStructure)['step'], 'production désactivée : la vente');

	}

	/**
	 * Une structure qui vend sans facturer garde la carte des factures, mais la vente ne lui est pas
	 * désignée : elle la fait déjà, et le pas passe au suivant (objection de V-Dashboard, L68).
	 */
	public function testASellingStructureIsNotOfferedTheSale(): void {

		$this->setFlags(['hasCultivations', 'hasSales']);

		$values = DashboardLib::load('structure-start', $this->eStructure);

		$this->assertEquals('shop', $values['step'], 'le pas passe à la boutique');
		$this->assertTrue(in_array('invoice', $values['remaining'], TRUE), 'la carte des factures reste');

	}

	/** Les entrées de l'ordre toutes essayées, il reste des cartes mais aucun pas. */
	public function testNoStepWhenTheOrderIsExhausted(): void {

		$this->setFlags(['hasCultivations', 'hasInvoices', 'hasShops', 'hasMarket']);
		$this->createWebsite();

		$values = DashboardLib::load('structure-start', $this->eStructure);

		$this->assertTrue($values !== NULL, 'des entrées hors de l\'ordre restent');
		$this->assertEquals(NULL, $values['step'], 'sans premier pas');

	}

	/**
	 * Chaque entrée de l'ordre existe au catalogue et porte le bouton de sa bande : un nom ajouté à
	 * `START_STEPS`, ou une entrée renommée, ne casserait sinon qu'à l'affichage.
	 */
	public function testEachStepHasItsButton(): void {

		$features = array_column(new StructureUi()->getStartedFeatures($this->eStructure), NULL, 'name');

		foreach(StructureSetting::START_STEPS as $name) {
			$this->assertTrue(array_key_exists($name, $features), $name.' : une entrée du catalogue');
			$this->assertTrue(array_key_exists('button', $features[$name]), $name.' : le bouton de sa bande');
		}

	}

	/**
	 * La création d'une vente refuse une structure dont le pays n'est pas confirmé : le pas de la vente
	 * la mène alors aux ventes, qui le lui demandent.
	 */
	public function testAnUnverifiedCountryIsLedToTheSales(): void {

		$eStructure = clone $this->eStructure;
		$eStructure['isLegal'] = TRUE;
		$eStructure['verifiedCountry'] = TRUE;

		$features = array_column(new StructureUi()->getStartedFeatures($eStructure), NULL, 'name');
		$this->assertEquals('/selling/sale:create?structure='.$eStructure['id'], $features['invoice']['stepUrl'], 'pays confirmé : la création d\'une vente');

		$eStructure['verifiedCountry'] = FALSE;

		$features = array_column(new StructureUi()->getStartedFeatures($eStructure), NULL, 'name');
		$this->assertEquals(StructureUi::urlSellingSales($eStructure), $features['invoice']['stepUrl'], 'pays à confirmer : les ventes');

	}

	/** Les entrées restantes, vides quand le bloc n'a rien à dire. */
	private function getRemaining(Structure $eStructure): array {

		$values = DashboardLib::load('structure-start', $eStructure);

		return ($values === NULL) ? [] : $values['remaining'];

	}

	private function createWebsite(): void {

		$eWebsite = new \website\Website(['structure' => $this->eStructure]);

		$fw = new \FailWatch();

		$eWebsite->build(\website\WebsiteLib::getPropertiesCreate(), [
			'internalDomain' => 'zzstart'.uniqid(),
			'name' => 'zzStart site'
		]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('site refusé : '.json_encode($fw->get())));

		\website\WebsiteLib::create($eWebsite);

	}

	/** Pose les drapeaux nommés sur la ligne de fonctionnalités en mémoire, éteint les autres. */
	private function setFlags(array $flags): void {

		foreach([...self::FLAGS, 'hasSales'] as $flag) {
			$this->eStructure->conf(Feature::class)[$flag] = in_array($flag, $flags, TRUE);
		}

	}

}
?>
