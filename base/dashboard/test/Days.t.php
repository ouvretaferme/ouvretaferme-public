<?php
namespace dashboard;

/**
 * La bande de jours que le socle dessine pour un bloc : deux marques de nature différente, celle du
 * jour choisi et celle d'aujourd'hui.
 *
 * Elles sont éprouvées ici parce que la seconde est invisible à l'ouverture d'un accueil, le jour
 * choisi étant alors aujourd'hui : elle ne se voit qu'après un clic sur un autre jour, et elle a déjà
 * disparu une fois sans qu'aucun contrôle de livraison ne le remarque. Un balisage rendu le dit, là
 * où un écran ouvert au chargement ne dit rien.
 *
 * Aucune base ouverte : `getDays()` ne lit qu'un id de structure pour écrire l'adresse du geste, et
 * les tuiles sont fabriquées à la main, comme celles de `ContributionTest`.
 *
 * Lancement : dev/test package=dashboard file=Days
 */
class DaysTest extends \Test {

	/**
	 * La bande n'écrit cet identifiant que dans l'adresse du geste, qu'aucune assertion ne lit : il est
	 * donc arbitraire, et pris hors de la plage des identifiants réels (colonne `int unsigned`, plafond
	 * 4 294 967 295) pour qu'il ne se lise pas comme une structure du parc, comme
	 * `amap\BureauTest::STRUCTURE_FICTITIOUS`.
	 */
	const STRUCTURE_FICTITIOUS = 4000000000;

	/**
	 * Le nom du jour d'aujourd'hui reste reconnaissable quand un autre jour est choisi : le fond marque
	 * le jour choisi, `dashboard-day-today` marque aujourd'hui, et les deux se posent sur des tuiles
	 * différentes dès qu'on regarde ailleurs.
	 */
	public function testTodayKeepsItsMarkWhenAnotherDayIsChosen(): void {

		$today = \Clock::date();
		$tomorrow = date('Y-m-d', strtotime($today.' + 1 day'));

		$h = $this->getBand($tomorrow);

		$this->assertEquals(['dashboard-day-today'], $this->getMarks($h, $today), "aujourd'hui porte sa marque sans porter celle du jour choisi");
		$this->assertEquals(['dashboard-day-selected'], $this->getMarks($h, $tomorrow), 'et le jour choisi porte la sienne sans porter celle d\'aujourd\'hui');

	}

	/** À l'ouverture, le jour choisi est aujourd'hui : la même tuile porte les deux marques. */
	public function testTheChosenDayCanBeToday(): void {

		$today = \Clock::date();

		$this->assertEquals(['dashboard-day-selected', 'dashboard-day-today'], $this->getMarks($this->getBand($today), $today), 'le jour choisi qui est aujourd\'hui porte les deux marques');

	}

	/** Les marques de jour que porte la tuile de `$date`, dans l'ordre où la bande les écrit. */
	private function getMarks(string $h, string $date): array {

		preg_match('/id="'.DashboardUi::getDayId('x-band', $date).'" class="([^"]*)"/', $h, $match);

		return array_values(array_filter(explode(' ', $match[1]), fn(string $class): bool => str_starts_with($class, 'dashboard-day-')));

	}

	/** Trois jours autour d'aujourd'hui, celui que l'argument désigne étant le jour choisi. */
	private function getBand(string $selected): string {

		$today = \Clock::date();

		$days = [];

		foreach([-1, 0, 1] as $offset) {

			$date = date('Y-m-d', strtotime($today.' '.$offset.' day'));

			$days[] = ['date' => $date, 'label' => $date, 'value' => NULL, 'state' => NULL];

		}

		return new DashboardUi()->getDays(new \structure\Structure(['id' => self::STRUCTURE_FICTITIOUS]), 'x-band', $days, $selected);

	}

}
?>
