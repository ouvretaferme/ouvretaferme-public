<?php
namespace planning;

/**
 * Chaque onglet du nouveau planning mène à un écran qui existe, et chaque page du package est déclarée.
 *
 * Le nouveau planning s'écrit lot par lot, un fichier de page par écran. Un onglet ajouté à
 * `PlanningSetting::PAGES` sans sa page mène à une erreur, et une page ajoutée sans être rattachée à un
 * onglet n'est atteinte par aucun parcours. D'où deux inventaires, dans les deux sens :
 * - chaque `page/*.p.php` du package est déclaré dans `SCREENS`, ou dans `PAGES_WITHOUT_TAB` pour une
 *   page qu'aucun onglet n'ouvre, et chaque déclaration a son fichier ;
 * - chaque écran de `PlanningSetting::PAGES` est déclaré dans `SCREENS`, et chaque déclaration est
 *   dans `PAGES`.
 *
 * C'est le code qui fait foi : un écart se corrige en déclarant l'écran apparu, ou en retirant la
 * déclaration de l'écran disparu.
 *
 * Inventaire pur, sans écriture en base.
 *
 * Lancement : dev/test package=planning file=ScreenSentinel
 */
class ScreenSentinelTest extends \Test {

	/**
	 * Les écrans, par clé d'onglet (celle de `PlanningSetting::PAGES`), avec les noms de leurs fichiers
	 * de page. Chaque lot ajoute le sien.
	 * - `harvestWeek` : la semaine des récoltes, l'écran que l'onglet ouvre (L2, L3a) ;
	 * - `harvestDay` : l'ancienne adresse du jour des récoltes (L3b), qui mène à la semaine de la date
	 *   depuis que la vue Jour est retirée (R143) ;
	 * - `taskWeek` : les interventions de la semaine, onglet « Interventions » (L5a) ;
	 * - `taskDay` : les mêmes, rangées par jour, la seconde période de l'onglet (L5c) ;
	 * - `taskYear` : les mêmes, rangées par semaine sur l'année, la troisième période de l'onglet (R-Annee) ;
	 * - `timeDay` : la fiche de temps d'une personne, un jour de la semaine (L6b) ;
	 * - `absence` : les absences de qui les demande (rh, lot 2, recette du 18/10).
	 */
	const SCREENS = [
		'semaine' => ['taskWeek', 'taskDay', 'taskYear'],
		'recoltes' => ['harvestWeek', 'harvestDay'],
		'temps' => ['timeDay'],
		'absences' => ['absence'],
	];

	/**
	 * Les pages hors des onglets, qu'aucun onglet n'ouvre et que `PlanningSetting::PAGES` ne liste donc pas.
	 * - `taskDetail` : la page d'une intervention (L5b), ouverte depuis une carte de l'écran de la semaine ;
	 * - `dashboard` : la coche d'un rappel depuis le pense-bête de l'accueil (L37 du dashboard), une action
	 *   sans écran.
	 */
	const PAGES_WITHOUT_TAB = ['taskDetail', 'dashboard'];

	/**
	 * Chaque fichier de page est déclaré, et chaque déclaration a son fichier.
	 */
	public function testPagesDeclared(): void {

		$files = $this->getPageFiles();
		$declared = array_merge(self::PAGES_WITHOUT_TAB, ...array_values(self::SCREENS));

		$undeclared = array_diff($files, $declared);
		$missing = array_diff($declared, $files);

		$this->assertEquals([], array_values($undeclared), 'une page du nouveau planning n\'est déclarée ni dans ScreenSentinelTest::SCREENS ni dans PAGES_WITHOUT_TAB, c\'est la déclaration qui s\'ajoute : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($missing), 'une page déclarée dans ScreenSentinelTest::SCREENS ou PAGES_WITHOUT_TAB n\'a plus de fichier, c\'est la déclaration qui se retire : '.implode(', ', $missing));

	}

	/**
	 * Chaque onglet de `PlanningSetting::PAGES` a son écran déclaré, et chaque écran déclaré est un
	 * onglet.
	 */
	public function testTabsHaveScreens(): void {

		$screens = array_keys(self::SCREENS);

		$tabsWithoutScreen = array_diff(PlanningSetting::PAGES, $screens);
		$screensWithoutTab = array_diff($screens, PlanningSetting::PAGES);

		$this->assertEquals([], array_values($tabsWithoutScreen), 'un onglet de PlanningSetting::PAGES n\'a pas d\'écran déclaré, c\'est son écran qui s\'ajoute à ScreenSentinelTest::SCREENS : '.implode(', ', $tabsWithoutScreen));
		$this->assertEquals([], array_values($screensWithoutTab), 'un écran de ScreenSentinelTest::SCREENS n\'est pas dans PlanningSetting::PAGES, son onglet ne s\'affiche pas, c\'est l\'onglet qui s\'ajoute à PlanningSetting::PAGES : '.implode(', ', $screensWithoutTab));

	}

	/** Les fichiers de page du package, par leur nom sans `.p.php`. */
	private function getPageFiles(): array {

		$files = array_map(fn($path) => basename($path, '.p.php'), glob($this->getPagePath('*')));
		sort($files);

		return $files;

	}

	private function getPagePath(string $file): string {

		return \Lime::getPath('production').'/planning/page/'.$file.'.p.php';

	}

}
?>
