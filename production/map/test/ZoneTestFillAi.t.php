<?php
namespace map;

/**
 * La surface libre d'un jardin porte le mode de ce jardin, et rien d'autre, dans les attributs de test
 * de l'assolement (`ZoneLib::test()`, `BedUi::getTestAttributes()`) : c'est ce qui permet au filtre
 * « Mode » de la replier, comme une planche, quand le jardin n'est pas du mode cherché. Elle n'a ni
 * largeur, ni rotation, ni occupation à comparer, donc ne porte aucun autre attribut.
 *
 * Le décor est une parcelle et deux jardins, l'un sous abri et l'autre en plein champ, posés par
 * `ZoneLib::create()` et `PlotLib::create()` dans la transaction de la méthode, sur la structure
 * `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=ZoneTestFillAi
 */
class ZoneTestFillAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createPlot(Zone $eZone, string $mode): Plot {

		$ePlot = new Plot([
			'zone' => $eZone,
			'structure' => $this->eStructure,
			'mode' => $mode
		]);

		$fw = new \FailWatch();

		$ePlot->build(PlotLib::getPropertiesCreate(), [
			'name' => 'Jardin '.uniqid(),
			'mode' => $mode,
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		] + (($mode === Plot::GREENHOUSE) ? ['length' => 30, 'width' => 8] : []), new \PropertyList('create'));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		PlotLib::create($ePlot);

		return $ePlot;

	}

	public function testFillBedCarriesOnlyTheModeOfItsPlot(): void {

		$eZone = new Zone([
			'name' => 'Parcelle des surfaces libres '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		ZoneLib::create($eZone);

		$ePlotGreenhouse = $this->createPlot($eZone, Plot::GREENHOUSE);
		$ePlotOpenField = $this->createPlot($eZone, Plot::OPEN_FIELD);

		$season = (int)\Clock::year();

		$cZone = ZoneLib::getByStructure($this->eStructure, season: $season);
		PlotLib::putFromZoneWithSeries($this->eStructure, $cZone, $season, [$season], onlySeries: TRUE);

		$eZoneRead = $cZone->find(fn($e) => $e['id'] === $eZone['id'])->first();

		if($this->assertDecorPresent($eZoneRead !== NULL, 'getByStructure() rend la parcelle du décor') === FALSE) {
			return;
		}

		// Une série en planches sans dates ni culture : seul le mode du jardin entre en jeu
		$eSeries = new \series\Series([
			'id' => 0,
			'structure' => $this->eStructure,
			'season' => $season,
			'use' => \series\Series::BED,
			'bedWidth' => NULL,
			'bedStartCalculated' => NULL,
			'bedStopCalculated' => NULL,
			'cCultivation' => new \Collection()
		]);

		ZoneLib::test($cZone, new \Search(['mode' => Plot::GREENHOUSE]), $eSeries);

		$expected = [
			$ePlotGreenhouse['id'] => '1',
			$ePlotOpenField['id'] => '0',
		];

		foreach($eZoneRead['cPlot'] as $ePlot) {

			if(isset($expected[$ePlot['id']]) === FALSE) {
				continue;
			}

			$cBedFill = $ePlot['cBed']->find(fn($eBed) => $eBed['plotFill']);

			if($this->assertDecorPresent($cBedFill->count() === 1, 'PlotLib::create() pose la surface libre du jardin '.$ePlot['mode']) === FALSE) {
				return;
			}

			$this->assertEquals(
				['data-greenhouse' => $expected[$ePlot['id']]],
				\map\BedUi::getTestAttributes($cBedFill->first()),
				'la surface libre du jardin '.$ePlot['mode'].' ne porte que le mode de son jardin'
			);

			unset($expected[$ePlot['id']]);

		}

		$this->assertEquals([], $expected, 'les deux jardins du décor sont lus');

	}

}
?>
