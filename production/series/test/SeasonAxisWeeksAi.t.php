<?php
namespace series;

/**
 * L'axe de saison compté en semaines (`CultivationUi::getListSeason()`, paramètre `$weekMonths`) :
 * l'axe d'une grille dont chaque semaine est une colonne de même largeur, comme celle des paniers de
 * l'année. Aucun test ne touche la base.
 *
 * Le rendu par défaut, compté en jours, n'est pas rejoué ici : son identité octet pour octet avant et
 * après l'ajout du paramètre a été contrôlée une fois, sur 320 combinaisons de configuration, de
 * saison et d'options (lot S du chantier des paniers, 21/09/2026).
 */
class SeasonAxisWeeksAiTest extends \Test {

	public function testFiftyTwoEqualColumns() {

		$html = $this->render($this->getWeekMonths('2027-W01', 52));

		$marks = $this->getMarks($html);

		$this->assertTrue($marks !== [], 'aucune semaine sur l\'axe, le cas ne prouve rien');

		foreach($marks as $index => $mark) {
			$this->assertTrue(abs(100 / 52 - $mark['width']) < 0.0001, 'Largeur de la semaine '.($index + 1).' : '.$mark['width']);
			$this->assertTrue(abs($index * 100 / 52 - $mark['left']) < 0.0001, 'Position de la semaine '.($index + 1).' : '.$mark['left']);
		}

	}

	public function testMonthsCountTheirThursdays() {

		$weekMonths = $this->getWeekMonths('2027-W01', 52);
		$html = $this->render($weekMonths);

		$thursdays = array_count_values(array_map(fn($week) => substr(week_date_day($week, 4), 0, 7), array_keys($weekMonths)));

		$columns = $this->getColumns($html);

		$this->assertEquals(array_map(fn($count) => round($count / 52 * 100, 6), array_values($thursdays)), array_map(fn($column) => round($column, 6), $columns));

	}

	public function testDayAxisWouldShiftTheColumns() {

		$weekMonths = $this->getWeekMonths('2026-W01', 52);

		$columns = $this->getColumns($this->render($weekMonths));
		$daysInJanuary = 31 / 365 * 100;

		$this->assertEquals(round(5 / 52 * 100, 6), round($columns[0], 6), 'Janvier 2026 prend ses cinq jeudis');
		$this->assertTrue(abs($columns[0] - $daysInJanuary) > 1, 'Au jour près, janvier ne tomberait pas sur une frontière de colonne : '.$daysInJanuary);

	}

	public function testWeeksBeyondTheSeasonAddExternalMonths() {

		$html = $this->render($this->getWeekMonths('2025-W49', 58), filterable: TRUE);

		$this->assertEquals(1, substr_count($html, 'data-month="2025-12"'));
		$this->assertEquals(1, substr_count($html, 'data-month="2027-01"'));

	}

	public function testPartialYearSpansItsMonths() {

		$html = $this->render($this->getWeekMonths('2027-W10', 20));

		$this->assertEquals(1, preg_match('/style="grid-column: span ([0-9]+);">2027<\/div>/', $html, $match));
		$this->assertEquals(count($this->getColumns($html)), (int)$match[1]);

	}

	private function render(array $weekMonths, bool $filterable = FALSE): string {

		$eConfiguration = new \map\Configuration(['calendarMonthStart' => NULL, 'calendarMonthStop' => NULL, 'calendarMonths' => 12]);
		$season = (int)substr(array_keys($weekMonths)[intdiv(count($weekMonths), 2)], 0, 4);

		return new CultivationUi()->getListSeason($eConfiguration, $season, hasWeeks: TRUE, filterable: $filterable, weekMonths: $weekMonths);

	}

	private function getWeekMonths(string $weekFirst, int $count): array {

		$weekMonths = [];
		$week = $weekFirst;

		for($index = 0; $index < $count; $index++) {
			$weekMonths[$week] = \util\DateLib::convertWeeksToMonths([$week])[0];
			$week = toWeek(strtotime($week.' + 1 WEEK'));
		}

		return $weekMonths;

	}

	private function getColumns(string $html): array {

		preg_match('/grid-template-columns: ([^;]+);/', $html, $match);

		return array_map(fn($width) => (float)$width, explode(' ', $match[1]));

	}

	private function getMarks(string $html): array {

		preg_match_all('/style="left: ([0-9.E-]+)%; width: ([0-9.E-]+)%">[0-9]+<\/div>/', $html, $matches, PREG_SET_ORDER);

		return array_map(fn($match) => ['left' => (float)$match[1], 'width' => (float)$match[2]], $matches);

	}

}
?>
