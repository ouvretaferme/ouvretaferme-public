<?php
namespace series;

/**
 * Les plans des densités d'une culture (planche pf-t3, arbitrée le 2026-09-28) : un plan
 * par format de planche des emplacements, la densité de chaque format, le quinconce que la culture
 * retient, et la distance entre deux plants voisins que ce quinconce change.
 *
 * Lancement : dev/test package=series file=DensityPlanAi
 */
class DensityPlanAiTest extends SeriesFixtureAiTest {

	/** Quatre rangs à 30 cm sur une planche : les deux cas de la distance entre voisins s'y séparent. */
	private function createCultivationStagger(Series $eSeries, bool $stagger): Cultivation {

		$eCultivation = $this->createCultivation($eSeries);

		$eCultivation['distance'] = Cultivation::SPACING;
		$eCultivation['rows'] = 4;
		$eCultivation['rowSpacing'] = NULL;
		$eCultivation['plantSpacing'] = 30;
		$eCultivation['stagger'] = $stagger;

		CultivationLib::update($eCultivation, ['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing', 'stagger']);

		return $eCultivation;

	}

	private function readSeries(Series $eSeries): Series {

		return Series::model()
			->select(['id', 'use', 'bedWidth', 'alleyWidth', 'length', 'lengthTarget', 'area', 'areaTarget'])
			->whereId($eSeries['id'])
			->get();

	}

	/**
	 * Deux planches de 100 cm et une de 200 : deux formats, le plus large en tête, chacun avec son
	 * nombre de planches et sa longueur cumulée. Le passe-pied manquant d'une planche est celui de la
	 * série.
	 */
	public function testFormatsGroupThePlaces(): void {

		$eSeries = $this->createSeriesBed('Formats du décor');

		$this->createPlace($eSeries, $this->createBed(length: 20, width: 100), 20);
		$this->createPlace($eSeries, $this->createBed(length: 15, width: 100), 15);
		$this->createPlace($eSeries, $this->createBed(length: 30, width: 200), 30);

		$formats = PlaceLib::getBedFormats($this->readSeries($eSeries), PlaceLib::getByElement($eSeries));

		$this->assertEquals([
			['bedWidth' => 200, 'alleyWidth' => 40, 'beds' => 1, 'length' => 30],
			['bedWidth' => 100, 'alleyWidth' => 40, 'beds' => 2, 'length' => 35],
		], $formats);

	}

	/** Sans emplacement, un seul format, tiré de la série et de son objectif. */
	public function testFormatWithoutPlaceIsTheSeries(): void {

		$eSeries = $this->createSeriesBed('Format sans emplacement du décor');

		$formats = PlaceLib::getBedFormats($this->readSeries($eSeries), new \Collection());

		$this->assertEquals([['bedWidth' => 80, 'alleyWidth' => 40, 'beds' => NULL, 'length' => 100]], $formats);

	}

	/**
	 * Chaque format a sa densité, celle que `CropLib::calculateDensity()` rendrait sur une série de
	 * cette planche, et le quinconce enregistré sur la culture arrive sur chaque plan.
	 */
	public function testPlansFollowEachFormat(): void {

		$eSeries = $this->createSeriesBed('Plans du décor');
		$eCultivation = $this->createCultivationStagger($eSeries, TRUE);

		$this->createPlace($eSeries, $this->createBed(length: 20, width: 100), 20);
		$this->createPlace($eSeries, $this->createBed(length: 30, width: 200), 30);

		$eSeriesRead = $this->readSeries($eSeries);

		$eCultivationRead = Cultivation::model()
			->select(['id', 'distance', 'rows', 'rowSpacing', 'plantSpacing', 'stagger'])
			->whereId($eCultivation['id'])
			->get();

		$this->assertTrue($eCultivationRead['stagger'], 'le quinconce ne s\'enregistre pas');

		$plans = CultivationLib::getDensityPlans($eSeriesRead, $eCultivationRead, PlaceLib::getBedFormats($eSeriesRead, PlaceLib::getByElement($eSeries)));

		$this->assertEquals(2, count($plans));

		foreach($plans as $plan) {

			$expected = \sequence\CropLib::calculateDensity($eCultivationRead, new Series(['use' => Series::BED, 'bedWidth' => $plan['bedWidth'], 'alleyWidth' => $plan['alleyWidth']]));

			$this->assertEquals(round($expected, 4), round($plan['density'], 4), 'la densité du plan de '.$plan['bedWidth'].' cm n\'est pas celle de la formule');
			$this->assertTrue($plan['stagger'], 'le plan de '.$plan['bedWidth'].' cm ne porte pas le quinconce');

		}

		$this->assertTrue($plans[0]['density'] !== $plans[1]['density'], 'les deux formats rendent la même densité, le cas ne distingue rien');

	}

	/** Une culture raisonnée en densité au m² n'a pas de plan, et ne retient pas de quinconce. */
	public function testDensityDistanceHasNoPlanNorStagger(): void {

		$eSeries = $this->createSeriesBed('Densité au m² du décor');

		$eCultivation = new Cultivation(['series' => $this->readSeries($eSeries), 'distance' => Cultivation::DENSITY, 'stagger' => NULL]);
		$eCultivation->build(['stagger'], ['stagger' => '1']);

		$this->assertFalse($eCultivation['stagger'], 'une culture en densité au m² retient le quinconce');
		$this->assertEquals([], CultivationLib::getDensityPlans($this->readSeries($eSeries), $eCultivation, [['bedWidth' => 80, 'alleyWidth' => 40, 'beds' => NULL, 'length' => 100]]));

	}

	/** Le quinconce décale les plants sans changer la densité (arbitrage Q4 A). */
	public function testStaggerKeepsTheDensity(): void {

		$straight = \sequence\DensityPlanAiLib::get(\sequence\Sequence::BED, 100, 50, 4, 30.0, NULL, FALSE);
		$stagger = \sequence\DensityPlanAiLib::get(\sequence\Sequence::BED, 100, 50, 4, 30.0, NULL, TRUE);

		$this->assertTrue($stagger['stagger']);
		$this->assertEquals($straight['density'], $stagger['density']);

	}

	/**
	 * Sous chaque plan d'une planche, la coupe qui nomme les passe-pieds, comme dans le calculateur
	 * (« faut que ce soit cohérent », le 29/09). Une surface libre n'a pas de planche à couper.
	 */
	public function testFiguresShowTheCut(): void {

		$bed = \sequence\DensityPlanAiLib::get(\sequence\Sequence::BED, 100, 50, 4, 30.0, NULL, FALSE) + ['beds' => NULL, 'length' => NULL];
		$block = \sequence\DensityPlanAiLib::get(\sequence\Sequence::BLOCK, NULL, NULL, NULL, 30.0, 40.0, FALSE) + ['area' => NULL];

		$this->assertTrue(
			substr_count(new \sequence\DensityPlanAiUi()->getFigures([$bed]), '<svg ') > substr_count(new \sequence\DensityPlanAiUi()->getFigures([$block]), '<svg '),
			'une planche ne porte pas sa coupe, ou une surface libre en porte une'
		);

	}

	/** Une saisie que le dessin ne peut pas porter ne rend pas de plan. */
	public function testImpossibleInputHasNoPlan(): void {

		$this->assertEquals([], \sequence\DensityPlanAiLib::get(\sequence\Sequence::BED, 100, 50, 4, 0.5, NULL, FALSE));
		$this->assertEquals([], \sequence\DensityPlanAiLib::get(\sequence\Sequence::BED, 10, 50, 20, 30.0, NULL, FALSE));
		$this->assertEquals([], \sequence\DensityPlanAiLib::get(\sequence\Sequence::BLOCK, NULL, NULL, NULL, 30.0, NULL, FALSE));

	}

}
?>
