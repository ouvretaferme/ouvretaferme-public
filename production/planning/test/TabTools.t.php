<?php
namespace planning;

/**
 * La rangée des outils de la Semaine, du Jour et de l'Année (R-Retours-0310, points 9 à 16) : l'ordre des
 * groupes de la recherche, et le menu à la suite des onglets, qui porte la personnalisation (points 12 et 16,
 * retour du 05/10). La période, les catégories et leur disposition à chaque largeur se regardent dans Chrome.
 *
 * Aucune donnée n'est lue ni écrite : les éléments sont construits en mémoire, seul leur identifiant
 * servant aux adresses.
 *
 * Lancement : dev/test package=planning file=TabTools
 */
class TabToolsTest extends \Test {

	/**
	 * La période et la recherche se rendent sans erreur ; ce qu'elles montrent se regarde dans
	 * Chrome.
	 */
	public function testRender(): void {

		$this->assertTrue(new TabUi()->getPeriod('day', '/s', '/j', '/a') !== '', 'la période ne se rend pas');
		$this->assertTrue(new TabUi()->getPeriod('week', '/s', '/j', '/a', ['cards' => ["Cartes", '/s?view=cards', FALSE], 'grid' => ["Grille", '/s?view=grid', TRUE]]) !== '', 'la période de la Semaine ne se rend pas avec ses affichages');
		$this->assertTrue(new TabUi()->getSearch() !== '', 'la recherche ne se rend pas');

	}

	/**
	 * Le jeton « Semaine » ouvre le menu des affichages que la Semaine lui donne, et reste un lien sans eux. Le
	 * test lit `data-dropdown`, l'attribut qui ouvre le menu (`Lime.Dropdown`), et rien de ce que montre le jeton.
	 */
	public function testWeekViewMenu(): void {

		$hasMenu = function(array $weekViews): bool {
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new TabUi()->getPeriod('week', '/s', '/j', '/a', $weekViews).'</body></html>', LIBXML_NOERROR);
			return $document->querySelector('.util-filter-options [data-dropdown]') !== NULL;
		};

		$this->assertTrue($hasMenu(['cards' => ["Cartes", '/s?view=cards', TRUE], 'grid' => ["Grille", '/s?view=grid', FALSE]]), 'deux affichages n\'ouvrent pas de menu');
		$this->assertFalse($hasMenu([]), 'la Semaine sans affichages ouvre un menu');

	}

	/**
	 * La catégorie d'abord, qui remplace les onglets de catégorie (V58), puis les personnes (R-Retours-0810, 232),
	 * les interventions, les espèces et les emplacements :
	 * la Semaine et le Jour rangent leurs sections dans l'ordre des groupes (`PlanningTaskSearch.suggest()`),
	 * l'Année dans celui de ses suggestions (`TaskSearchLib::GROUPS`).
	 */
	public function testSearchGroupsOrder(): void {

		$this->assertEquals(['category', 'user', 'action', 'plant', 'place'], array_keys(new TabUi()->getSearchGroups()), 'les groupes de la recherche de la Semaine et du Jour ne commencent pas par la catégorie');
		$this->assertEquals(['category', 'user', 'action', 'plant', 'place'], TaskSearchLib::GROUPS, 'les suggestions de l\'Année ne commencent pas par la catégorie');

	}

	/**
	 * La roue à la suite des onglets (retours 12 et 16 du 03/10, 13 du 04/10, 359) : un onglet qui mène à la
	 * personnalisation de chaque écran. Sans membre, il n'a rien à porter et
	 * ne se rend pas.
	 */
	public function testMenu(): void {

		$eStructure = new \structure\Structure(['id' => 1]);

		$this->assertEquals('', new TabUi()->getMenu($eStructure, '2026-W40', 'semaine', new \structure\Member()), 'le menu se rend sans membre, alors qu\'il n\'a rien à porter');

		foreach(TabUi::SCREENS as $screen) {

			$menu = new TabUi()->getMenu($eStructure, '2026-W40', $screen, new \structure\Member(['id' => 1]));

			$this->assertTrue(str_contains($menu, 'href="'.TaskWeekUi::urlCustomize($eStructure, '2026-W40', $screen).'"'), 'la roue manque sur l\'écran '.$screen.', ou ne mène pas à sa page');

		}

		$this->assertFalse(str_contains(TaskWeekUi::urlCustomize($eStructure, '2026-W40'), 'origin='), 'la personnalisation ouverte depuis la Semaine porte une origine');
		$this->assertTrue(str_ends_with(TaskWeekUi::urlCustomize($eStructure, '2026-W40', 'jour'), '/planning-v2/personnaliser/2026-W40?origin=jour'), 'la personnalisation ouverte depuis le Jour ne porte pas son origine');

	}

}
?>
