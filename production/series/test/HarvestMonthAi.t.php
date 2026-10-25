<?php
namespace series;

/**
 * Le mois de la récolte attendue, sur une semaine à cheval sur deux mois.
 *
 * `CultivationLib::updateHarvestPeriodExpected()` écrit `harvestMonthsExpected` sans passer par
 * `build()`, et rattachait la semaine à son **lundi** là où `\util\DateLib::convertWeeksToMonths()`,
 * qu'emploient `build()` et le réalisé, rend le mois de son **jeudi**. 56 des 574 semaines ISO de
 * 2020 à 2030 changent de mois selon la règle lue.
 *
 * Les cas portent sur `2024-W31`, lundi le 29/07 et jeudi le 01/08 : c'est la seule forme de semaine
 * sur laquelle les deux règles se séparent, et le résultat attendu ne s'écrit pas en dur mais se
 * dérive du jeudi, pour que le test dise la règle et non un mois.
 *
 * L'inventaire des sites qui rattachent une semaine à un mois est ailleurs, dans
 * `WeekMonthSentinelAi` : celui-ci ne mesure qu'un comportement.
 *
 * Lancement : dev/test package=series file=HarvestMonthAi
 */
class HarvestMonthAiTest extends SeriesFixtureAiTest {

	/** Une semaine dont le lundi et le jeudi ne tombent pas dans le même mois. */
	const WEEK_ASTRIDE = '2024-W31';

	/** Deux semaines plus tôt et une plus tard : la campagne couvre les deux mois. */
	const WEEK_BEFORE = '2024-W30';
	const WEEK_AFTER = '2024-W32';

	/** Le mois que porte le jeudi d'une semaine, c'est-à-dire celui qui en porte le plus de jours. */
	private function getMonthOfThursday(string $week): string {

		return date('Y-m', strtotime($week.' + 3 DAYS'));

	}

	private function getTimestamp(string $week): int {

		return strtotime($week);

	}

	/**
	 * Une semaine à cheval se range dans le mois de son jeudi.
	 */
	public function testWeekAstrideGoesToThursdayMonth(): void {

		$eCultivation = $this->createCultivation();

		CultivationLib::updateHarvestPeriodExpected(
			$eCultivation,
			$this->getTimestamp(self::WEEK_ASTRIDE),
			$this->getTimestamp(self::WEEK_ASTRIDE)
		);

		$this->assertEquals([self::WEEK_ASTRIDE], $eCultivation['harvestWeeksExpected'], 'la semaine écrite n\'est pas celle demandée');

		$thursday = $this->getMonthOfThursday(self::WEEK_ASTRIDE);
		$monday = date('Y-m', $this->getTimestamp(self::WEEK_ASTRIDE));

		// Sans quoi le cas ne prouve rien : c'est la divergence qui le rend intéressant.
		$this->assertTrue($monday !== $thursday, self::WEEK_ASTRIDE.' ne sépare plus les deux règles, le cas est à changer');

		$this->assertEquals([$thursday], $eCultivation['harvestMonthsExpected'], 'la semaine à cheval n\'est pas rangée dans le mois de son jeudi');

	}

	/**
	 * Une campagne de trois semaines porte les deux mois que ses jeudis traversent, et rien d'autre.
	 */
	public function testCampaignCarriesEveryThursdayMonth(): void {

		$eCultivation = $this->createCultivation();

		CultivationLib::updateHarvestPeriodExpected(
			$eCultivation,
			$this->getTimestamp(self::WEEK_BEFORE),
			$this->getTimestamp(self::WEEK_AFTER)
		);

		$expected = [
			$this->getMonthOfThursday(self::WEEK_BEFORE),
			$this->getMonthOfThursday(self::WEEK_ASTRIDE),
			$this->getMonthOfThursday(self::WEEK_AFTER),
		];

		$expected = array_merge(array_unique($expected));
		sort($expected);

		$months = $eCultivation['harvestMonthsExpected'];
		sort($months);

		$this->assertEquals([self::WEEK_BEFORE, self::WEEK_ASTRIDE, self::WEEK_AFTER], $eCultivation['harvestWeeksExpected'], 'les semaines de la campagne ne sont pas celles demandées');
		$this->assertEquals($expected, $months, 'les mois de la campagne ne sont pas ceux que traversent ses jeudis');

	}

	/**
	 * Ce que la méthode écrit est ce que `build()` écrirait des mêmes semaines.
	 *
	 * C'est le contrat que la divergence rompait : la méthode écrit hors `build()`, et l'API comme
	 * le formulaire passent par lui.
	 */
	public function testWrittenMonthsMatchBuild(): void {

		$eCultivation = $this->createCultivation();

		CultivationLib::updateHarvestPeriodExpected(
			$eCultivation,
			$this->getTimestamp(self::WEEK_BEFORE),
			$this->getTimestamp(self::WEEK_AFTER)
		);

		// En mémoire : une seconde culture de la même espèce sur la même série est refusée par
		// l'index unique (series, plant), et create() annule alors la transaction du test
		$eCultivationBuilt = new Cultivation([
			'series' => $eCultivation['series'],
			'harvestPeriodExpected' => Cultivation::WEEK,
		]);

		$eCultivationBuilt->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => $eCultivation['harvestWeeksExpected']]);

		$this->assertEquals($eCultivationBuilt['harvestMonthsExpected'], $eCultivation['harvestMonthsExpected'], 'la méthode et build() ne rangent pas les mêmes semaines dans les mêmes mois');

	}

}
?>
