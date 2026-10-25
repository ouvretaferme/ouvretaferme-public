<?php
namespace series;

/**
 * La récolte attendue d'une série dupliquée avec décalage.
 *
 * Une récolte attendue saisie au mois se décalait d'un nombre de mois arrondi (`round($taskInterval /
 * 4.33)`) : deux semaines faisaient zéro mois, quatre en faisaient un, et dix copies espacées de deux
 * semaines portaient des récoltes qui avançaient par à-coups pendant que leurs interventions
 * avançaient régulièrement (signalé par une structure le 08/10/2026). La récolte d'une copie décalée
 * se compte désormais à la semaine.
 *
 * Lancement : dev/test package=series file=DuplicateHarvestAi
 */
class DuplicateHarvestAiTest extends SeriesFixtureAiTest {

	/** Deux mois entiers, loin d'un changement d'année pour que le décalage reste dans la saison. */
	private function getMonths(): array {

		return [$this->getSeason().'-06', $this->getSeason().'-07'];

	}

	private function shift(array $weeks, int $interval): array {

		return array_map(fn(string $week) => toWeek(strtotime($week.' +'.$interval.' WEEK')), $weeks);

	}

	/**
	 * Une série dont la récolte est attendue en juin et juillet, puis ses copies décalées chacune du
	 * nombre de semaines demandé, rendues avec la récolte attendue de leur culture.
	 */
	private function duplicate(array $intervals): \Collection {

		$eSeries = $this->createSeries('Série à dupliquer');
		$eCultivation = $this->createCultivation($eSeries);

		$eCultivation['harvestPeriodExpected'] = Cultivation::MONTH;
		$eCultivation['harvestMonthsExpected'] = $this->getMonths();
		$eCultivation['harvestWeeksExpected'] = \util\DateLib::convertMonthsToWeeks($this->getMonths());

		// Le décor veut exactement ces mois : build() les dériverait du formulaire de la culture, que le cas n'éprouve pas.
		Cultivation::model()
			->select('harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected')
			->update($eCultivation);

		$cSeries = Series::model()
			->select(Series::getSelection())
			->whereId($eSeries)
			->getCollection();

		$cSeriesNew = SeriesLib::duplicateCollection(
			$cSeries,
			$this->getSeason(),
			new \Collection(),
			count($intervals),
			[
				'name' => [$eSeries['id'] => array_map(fn(int $interval) => 'Copie +'.$interval, $intervals)],
				'taskInterval' => [$eSeries['id'] => $intervals],
			],
			FALSE,
			FALSE
		);

		$this->assertEquals(count($intervals), $cSeriesNew->count(), 'la duplication n\'a pas rendu toutes les copies');

		return Cultivation::model()
			->select('series', 'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected')
			->whereSeries('IN', $cSeriesNew)
			->sort('series')
			->getCollection(NULL, NULL, 'series');

	}

	/**
	 * Chaque copie récolte exactement son décalage plus tard, y compris quand il ne fait pas un mois.
	 */
	public function testHarvestFollowsTheWeekInterval(): void {

		$intervals = [2, 4, 6, 8];

		$cCultivation = $this->duplicate($intervals);

		$weeks = \util\DateLib::convertMonthsToWeeks($this->getMonths());

		foreach(array_values($cCultivation->getArrayCopy()) as $position => $eCultivation) {

			$interval = $intervals[$position];
			$expected = $this->shift($weeks, $interval);

			$this->assertEquals(Cultivation::WEEK, $eCultivation['harvestPeriodExpected'], 'la récolte d\'une copie décalée de '.$interval.' semaines ne se compte pas à la semaine');
			$this->assertEquals($expected, $eCultivation['harvestWeeksExpected'], 'la récolte d\'une copie décalée de '.$interval.' semaines n\'est pas décalée d\'autant');
			$this->assertEquals(\util\DateLib::convertWeeksToMonths($expected), $eCultivation['harvestMonthsExpected'], 'les mois d\'une copie décalée de '.$interval.' semaines ne sont pas ceux de ses semaines');

		}

	}

	/**
	 * Sans décalage, la copie garde la récolte au mois de l'origine.
	 */
	public function testHarvestWithoutIntervalStaysMonthly(): void {

		$eCultivation = $this->duplicate([0])->first();

		$this->assertEquals(Cultivation::MONTH, $eCultivation['harvestPeriodExpected'], 'la récolte d\'une copie sans décalage a changé de période');
		$this->assertEquals($this->getMonths(), $eCultivation['harvestMonthsExpected'], 'les mois d\'une copie sans décalage ont bougé');

	}

}
?>
