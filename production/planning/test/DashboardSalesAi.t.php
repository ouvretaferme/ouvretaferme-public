<?php
namespace planning;

/**
 * Le bloc `planning-sales` de l'accueil d'une structure : les espèces qui ont des ventes à livrer, leur
 * stock et ce qui en a été récolté cette semaine, tels que `DashboardLib::load()` les résume depuis
 * `HarvestWeekLib::get()`.
 *
 * Le décor est celui de `HarvestSalesTest`, monté dans la transaction de la méthode : espèces, cultures
 * prévues cette semaine, produits liés à l'espèce et ventes d'un client (`HarvestFixtureTest`). Chaque cas
 * part d'un accueil où le bloc se tait, ce que `requireSilent()` vérifie avant d'écrire quoi que ce soit :
 * les comptes se lisent alors en absolu, sans qu'une ligne de la base de dev pèse sur eux.
 *
 * Lancement : dev/test package=planning file=DashboardSalesAi
 */
class DashboardSalesAiTest extends HarvestFixtureTest {

	private function load(): ?array {
		return DashboardLib::load('planning-sales', $this->eStructure);
	}

	private function requireSilent(): bool {

		return (
			$this->assertDecorPresent($this->eStructure->canSelling(), 'la structure '.static::STRUCTURE.' ouvre la vente au membre du décor') and
			$this->assertDecorPresent($this->load() === NULL, 'la structure '.static::STRUCTURE.' n\'a aucune espèce vendue cette semaine avant le décor')
		);

	}

	/**
	 * Une culture prévue cette semaine, de l'espèce donnée, dans une série à elle : `UNIQUE (series, plant)`
	 * refuse deux cultures de la même espèce dans une série.
	 */
	private function createCultivationThisWeek(\plant\Plant $ePlant): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries('Série '.uniqid()), $ePlant);

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => [\Clock::week()]]);

		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestWeeksExpected']);

		return $eCultivation;

	}

	private function harvest(\series\Cultivation $eCultivation, float $quantity): void {

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

		$this->createHarvest($eTask, $quantity, \Clock::date());

	}

	/**
	 * La garde des ventes de l'écran Récoltes est dans la déclaration, pour jouer avant la pose de la
	 * cellule différée et non dans `load()`, après elle.
	 */
	public function testSellingIsDeclared(): void {

		$this->assertEquals(['canPlanningLimited', 'canSelling'], \dashboard\DashboardLib::getBlocks()['planning-sales']['right'], 'le bloc ne porte pas les deux droits de l\'écran Récoltes');

	}

	/**
	 * Une espèce cultivée cette semaine mais sans vente ne fait pas parler le bloc, même avec du stock : il
	 * n'existe que pour la confrontation des ventes et des récoltes. La première vente le fait parler.
	 */
	public function testSilentWithoutSales(): void {

		if($this->requireSilent() === FALSE) {
			return;
		}

		$ePlant = $this->getPlantSales();
		$this->createCultivationThisWeek($ePlant);
		$eProduct = $this->createProduct('Au kilo', $this->getUnit('kg'), stock: 5.0, ePlant: $ePlant);

		$this->assertEquals(NULL, $this->load(), 'une espèce sans vente fait parler le bloc');

		$this->createSaleWith($eProduct, 2.0, 1);

		$this->assertTrue($this->load() !== NULL, 'une vente à livrer ne fait pas parler le bloc');

	}

	/**
	 * Deux séries de la même espèce font une seule rangée. La vente et le stock sont ceux de l'espèce, que
	 * `prepareSales()` pose à l'identique sur les deux cultures : ils ne doublent pas. Le récolté est propre à
	 * chaque culture et s'additionne. Deux unités de vente restent deux valeurs.
	 */
	public function testMergedBySpecies(): void {

		$eUnitKg = $this->getUnit('kg');
		$eUnitBunch = $this->getUnit('bunch');

		if(
			$this->requireSilent() === FALSE or
			$this->assertDecorPresent($eUnitKg->notEmpty() and $eUnitBunch->notEmpty(), 'la structure '.static::STRUCTURE.' porte les unités « kg » et « bunch »') === FALSE
		) {
			return;
		}

		$ePlant = $this->getPlantSales();

		$eCultivationFirst = $this->createCultivationThisWeek($ePlant);
		$eCultivationSecond = $this->createCultivationThisWeek($ePlant);

		$this->createSaleWith($this->createProduct('Au kilo', $eUnitKg, stock: 4.0, ePlant: $ePlant), 3.0, 1);
		$this->createSaleWith($this->createProduct('En botte', $eUnitBunch, ePlant: $ePlant), 2.0, 2);

		$this->harvest($eCultivationFirst, 2.0);
		$this->harvest($eCultivationSecond, 1.5);

		$sales = $this->load();

		if($this->assertTrue($sales !== NULL, 'le bloc se tait sur une espèce vendue') === FALSE) {
			return;
		}

		$this->assertEquals(1, count($sales['plants']), 'deux séries de la même espèce font deux rangées');

		$plant = $sales['plants'][0];

		$this->assertEquals($ePlant['name'], $plant['name'], 'la rangée ne porte pas le nom de l\'espèce');
		$this->assertEquals([3.0, 2.0], array_column($plant['sold'], 'value'), 'la vente double avec les séries, ou mêle deux unités');
		$this->assertEquals($eUnitBunch['short'], $plant['sold'][1]['unit']['short'], 'l\'unité de vente ne part pas avec sa valeur');
		$this->assertEquals([4.0], array_column($plant['stock'], 'value'), 'le stock double avec les séries');
		$this->assertEquals(['kg' => 3.5], $plant['harvested'], 'le récolté des deux séries ne s\'additionne pas par unité');
		$this->assertEquals(0, $sales['hidden'], 'une espèce affichée se compte parmi les cachées');
		$this->assertEquals(date('Y-m-d', \Clock::shift('+7 days')), $sales['end'], 'la fin de la fenêtre n\'est pas celle que le membre a choisie');

	}

	/**
	 * Au-delà de `MAX` espèces vendues, la carte s'arrête et compte le reste : `get()` n'a pas de limite, la
	 * coupe se fait après lui.
	 */
	public function testCut(): void {

		if($this->requireSilent() === FALSE) {
			return;
		}

		$eUnitKg = $this->getUnit('kg');

		for($plant = 0; $plant < DashboardLib::MAX + 1; $plant++) {

			$ePlant = $this->createPlant();

			$this->createCultivationThisWeek($ePlant);
			$this->createSaleWith($this->createProduct('Au kilo', $eUnitKg, ePlant: $ePlant), 1.0, 1);

		}

		$sales = $this->load();

		if($this->assertTrue($sales !== NULL, 'le bloc se tait sur des espèces vendues') === FALSE) {
			return;
		}

		$this->assertEquals(DashboardLib::MAX, count($sales['plants']), 'la carte ne s\'arrête pas au plafond');
		$this->assertEquals(1, $sales['hidden'], 'l\'espèce au-delà du plafond ne se compte pas');

	}

}
?>
