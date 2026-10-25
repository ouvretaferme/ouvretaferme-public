<?php
namespace planning;

/**
 * Le bloc `planning-harvest` de l'accueil, ce qui a été récolté cette semaine unité par unité, et le bilan
 * `planning-bilan` du lundi, ce qui l'a été la semaine passée.
 *
 * L'heure est figée sur une semaine de 2010, antérieure à toute saisie du décor : les nombres se lisent
 * alors en absolu, la semaine et celle d'avant ne portant que ce que le test y pose.
 *
 * Isolation : la transaction de `SeriesFixtureAiTest`, annulée en tearDown().
 *
 * Lancement : dev/test package=planning file=DashboardHarvest
 */
class DashboardHarvestTest extends \series\SeriesFixtureAiTest {

	const string THURSDAY = '2010-06-17 10:00:00';
	const string MONDAY = '2010-06-21 10:00:00';

	/**
	 * Une intervention de récolte saisie dans l'unité donnée, et sa quantité, au jour donné, sur la
	 * culture du décor sauf mention.
	 */
	private function harvest(string $unit, float $quantity, string $date, \series\Cultivation $eCultivation = new \series\Cultivation()): void {

		$eTask = $this->createTaskHarvest($eCultivation, week: toWeek($date), date: $date);
		$eTask['harvestUnit'] = $unit;

		\series\TaskLib::update($eTask, ['harvestUnit']);

		$this->createHarvest($eTask, $quantity, $date);

	}

	/**
	 * Le kilo et la botte gardent chacun leur total, le chiffre en grand est celui du kilo, et la semaine
	 * passée se compte dans la même unité que lui.
	 */
	public function testUnitsAreNeverSummed(): void {

		$this->setClock(self::THURSDAY);

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->harvest(\series\Harvest::KG, 2.5, \Clock::date());
		$this->harvest(\series\Harvest::BUNCH, 3.0, \Clock::date());
		$this->harvest(\series\Harvest::KG, 4.0, date('Y-m-d', strtotime(\Clock::date().' - 7 days')));

		$after = DashboardLib::load('planning-harvest', $this->eStructure);

		$this->assertEquals(\series\Harvest::KG, $after['main'], 'le kilo en grand dès qu\'il y en a');
		$this->assertEquals([\series\Harvest::KG => 2.5, \series\Harvest::BUNCH => 3.0], $after['totals'], 'chaque unité son total');
		$this->assertEquals(4.0, $after['previous'], 'la semaine passée en kilos');
		$this->assertEquals([[\series\Harvest::BUNCH, 3.0], [\series\Harvest::KG, 2.5]], array_map(fn(array $plant) => [$plant['unit'], $plant['quantity']], $after['plants']), 'une ligne par unité pour la même espèce, la plus grosse quantité d\'abord');

	}

	/**
	 * Le lundi, le bilan compte la semaine passée unité par unité, compare le kilo seul à la même semaine
	 * de l'an passé, et ne nomme que les trois premières espèces.
	 */
	public function testTheBilanComparesTheMainUnitOnly(): void {

		$this->setClock(self::MONDAY);

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->harvest(\series\Harvest::KG, 20.0, '2010-06-15');
		$this->harvest(\series\Harvest::KG, 10.0, '2010-06-16');
		$this->harvest(\series\Harvest::KG, 10.0, '2010-06-16', $this->createCultivation(ePlant: $this->createPlant()));
		$this->harvest(\series\Harvest::KG, 5.0, '2010-06-17', $this->createCultivation(ePlant: $this->createPlant()));
		$this->harvest(\series\Harvest::BUNCH, 8.0, '2010-06-18', $this->createCultivation(ePlant: $this->createPlant()));
		$this->harvest(\series\Harvest::KG, 99.0, \Clock::date());
		$this->harvest(\series\Harvest::KG, 36.0, '2009-06-10');
		$this->harvest(\series\Harvest::BUNCH, 100.0, '2009-06-10');

		$values = DashboardLib::load('planning-bilan', $this->eStructure);

		$this->assertEquals('2010-W24', $values['week'], 'la semaine passée');
		$this->assertEquals(\series\Harvest::KG, $values['main'], 'le kilo en grand');
		$this->assertEquals([\series\Harvest::KG => 45.0, \series\Harvest::BUNCH => 8.0], $values['totals'], 'chaque unité son total, sans la récolte de ce lundi');
		$this->assertEquals(25, $values['variation'], 'les kilos comparés à ceux de la semaine 24 de 2009, les bottes jamais');
		$this->assertEquals([30.0, 10.0, 8.0], array_column($values['plants'], 'quantity'), 'les trois premières espèces, chacune une fois par unité');

	}

	/** Le bilan ne parle que le lundi, et pas d'une semaine passée sans récolte. */
	public function testTheBilanIsSilentOutsideMondaysAndWithoutHarvest(): void {

		$this->setClock(self::MONDAY);

		$this->assertEquals(NULL, DashboardLib::load('planning-bilan', $this->eStructure), 'une semaine passée sans récolte');

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->harvest(\series\Harvest::KG, 2.0, '2010-06-15');

		$this->assertEquals(NULL, DashboardLib::load('planning-bilan', $this->eStructure)['variation'], 'sans récolte la même semaine de l\'an passé, aucune comparaison');

		$this->setClock(self::THURSDAY);

		$this->assertEquals(NULL, DashboardLib::load('planning-bilan', $this->eStructure), 'un autre jour que le lundi');

	}

	/** Une semaine sans récolte saisie n'est pas `NULL`. */
	public function testAnEmptyWeekStillSpeaks(): void {

		$this->setClock(self::THURSDAY);

		$values = DashboardLib::load('planning-harvest', $this->eStructure);

		$this->assertEquals([NULL, [], NULL, []], [$values['main'], $values['totals'], $values['previous'], $values['plants']]);

	}

}
?>
