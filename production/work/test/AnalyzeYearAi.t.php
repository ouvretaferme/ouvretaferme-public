<?php
namespace work;

/**
 * Le contexte des analyses par année, ventes et temps de travail (`AnalyzeUi::getYear()`).
 *
 * Une structure qui n'a qu'une année de données n'a pas de liste d'années à ouvrir, mais elle peut
 * toujours filtrer sur un mois ou une semaine : le filtre posé doit rester visible et s'effacer,
 * sinon on ne revient à l'année entière qu'en retouchant l'adresse. Le défaut d'origine : la branche
 * « une seule année » rendait le libellé et sortait avant le filtre.
 *
 * Tout se rend en mémoire, sans base.
 *
 * Lancement : dev/test package=work file=AnalyzeYearAi
 */
class AnalyzeYearAiTest extends \Test {

	private function getYear(array $years, ?int $month, ?string $week): string {
		return new AnalyzeUi()->getYear($years, 2026, $month, $week, fn(int $year) => '/analyse/'.$year);
	}

	public function testSingleYearKeepsTheMonthErasable(): void {

		$context = $this->getYear([2026], 3, NULL);

		$this->assertTrue(str_contains($context, 'href="/analyse/2026"'), 'le mois filtré s\'efface vers l\'année entière : '.$context);

	}

	public function testSingleYearKeepsTheWeekErasable(): void {

		$context = $this->getYear([2026], NULL, '2026-W12');

		$this->assertTrue(str_contains($context, 'href="/analyse/2026"'), 'la semaine filtrée s\'efface vers l\'année entière : '.$context);

	}

	public function testSingleYearWithoutFilterHasNothingToErase(): void {

		$this->assertFalse(str_contains($this->getYear([2026], NULL, NULL), 'href='), 'sans filtre, une seule année n\'a rien à effacer');

	}

	public function testSeveralYearsRenderTheSameFilter(): void {

		$single = $this->getYear([2026], 3, NULL);
		$several = $this->getYear([2025, 2026], 3, NULL);

		$filter = substr($single, strlen(s("Année {value}", 2026)));

		$this->assertTrue(str_ends_with($several, $filter), 'le filtre d\'une seule année est celui de plusieurs années, au même balisage : '.$filter);
		$this->assertEquals(substr_count($single, 'data-dropdown=') + 1, substr_count($several, 'data-dropdown='), 'avec deux années, l\'année s\'ouvre en liste, et pas sans autre année : '.$several);

	}

}
?>
