<?php
namespace series;

/**
 * La semaine de planche calculée d'une série (`bedStartCalculated`, `bedStopCalculated`) vaut
 * « semaine ISO + (année ISO - saison) × 100 », et `Series::getBedStart()` et `getBedStop()` la
 * rendent en date : le lundi pour le début, le dimanche pour la fin. Aucun test ne touche la base.
 *
 * L'écart d'années n'a pas de borne : une tâche saisie deux ans avant la saison donne -157, et la
 * lecture ne savait dater que la saison d'avant, la saison et la saison d'après.
 */
class SeriesBedWeekAiTest extends \Test {

	const SEASONS = [2026, 2027];

	public function testWeekAbsent(): void {

		$eSeries = $this->getSeries(2026, NULL);

		$this->assertEquals(NULL, $eSeries->getBedStart());
		$this->assertEquals(NULL, $eSeries->getBedStop());

	}

	/**
	 * Les trois intervalles que la lecture savait déjà dater rendent la même date qu'avant : chacun se
	 * compare à l'écriture d'origine, une année nommée et sa semaine.
	 */
	public function testSeasonAndNeighbours(): void {

		foreach(self::SEASONS as $season) {

			foreach([-1, 0, 1] as $year) {

				for($week = 1; $week <= 53; $week++) {

					$eSeries = $this->getSeries($season, $year * 100 + $week);
					$isoWeek = ($season + $year).'-W'.sprintf('%02d', $week);

					$this->assertEquals(week_date_day($isoWeek, 1), $eSeries->getBedStart(), 'début de '.$isoWeek.' pour la saison '.$season);
					$this->assertEquals(week_date_day($isoWeek, 7), $eSeries->getBedStop(), 'fin de '.$isoWeek.' pour la saison '.$season);

				}

			}

		}

	}

	public function testBounds(): void {

		// 2026 compte 53 semaines, sa première commence en 2025 et sa dernière finit en 2027
		$this->assertDates('2025-12-29', '2026-01-04', 2026, 1);
		$this->assertDates('2026-12-28', '2027-01-03', 2026, 53);
		$this->assertDates('2024-12-30', '2025-01-05', 2026, -99);
		$this->assertDates('2027-01-04', '2027-01-10', 2026, 101);
		$this->assertDates('2028-01-03', '2028-01-09', 2026, 153);

		// 2025 n'a que 52 semaines : sa semaine 53 est la première de 2026
		$this->assertDates('2025-12-29', '2026-01-04', 2026, -47);

	}

	public function testYearsBeyondNeighbours(): void {

		$this->assertDates('2024-10-21', '2024-10-27', 2026, -157);
		$this->assertDates('2024-01-01', '2024-01-07', 2026, -199);
		$this->assertDates('2028-01-03', '2028-01-09', 2026, 201);
		$this->assertDates('2029-01-01', '2029-01-07', 2026, 253);
		$this->assertDates('2028-08-14', '2028-08-20', 2026, 233);

		// 2024 n'a que 52 semaines : sa semaine 53 est la première de 2025
		$this->assertDates('2024-12-30', '2025-01-05', 2026, -147);

		// Des années saisies très loin de la saison sortent telles quelles, la colonne ne les borne pas
		$this->assertDates('1986-03-31', '1986-04-06', 2026, -3986);
		$this->assertDates('2052-07-22', '2052-07-28', 2026, 2630);

	}

	public function testSameWeekFromTwoSeasons(): void {

		$this->assertEquals($this->getSeries(2025, -57)->getBedStart(), $this->getSeries(2026, -157)->getBedStart());
		$this->assertEquals($this->getSeries(2027, 33)->getBedStop(), $this->getSeries(2026, 133)->getBedStop());

	}

	/**
	 * Une semaine 0, ou au-delà de 53, n'est écrite par aucun calcul (`SeriesLib::recalculate()` pose
	 * une semaine ISO) : la lecture ne la garde pas, et elle rend la date nulle de PHP.
	 */
	public function testImpossibleWeek(): void {

		foreach(self::SEASONS as $season) {

			foreach([-200, -146, -100, -46, -1, 0, 54, 99, 100, 154, 200] as $value) {
				$this->assertDates('1970-01-01', '1970-01-01', $season, $value);
			}

		}

	}

	private function assertDates(string $start, string $stop, int $season, int $value): void {

		$eSeries = $this->getSeries($season, $value);

		$this->assertEquals($start, $eSeries->getBedStart(), 'début de la semaine calculée '.$value.' pour la saison '.$season);
		$this->assertEquals($stop, $eSeries->getBedStop(), 'fin de la semaine calculée '.$value.' pour la saison '.$season);

	}

	private function getSeries(int $season, ?int $value): Series {

		return new Series([
			'season' => $season,
			'bedStartCalculated' => $value,
			'bedStopCalculated' => $value,
		]);

	}

}
?>
