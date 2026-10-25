<?php
namespace dashboard;

/**
 * L'écart que le bilan écrit (`DashboardLib::getVariation()`), tenu par le socle pour que deux
 * packages l'écrivent pareil. Aucune base ouverte.
 *
 * Lancement : dev/test package=dashboard file=Variation
 */
class VariationTest extends \Test {

	/**
	 * L'écart arrondi à l'unité garde son signe, et rien ne s'écrit quand il ne dit rien : pas de
	 * période d'avant, ou zéro une fois arrondi.
	 */
	public function testAVariationThatSaysNothingIsNull(): void {

		$this->assertEquals(50, DashboardLib::getVariation(45, 30), 'une hausse');
		$this->assertEquals(-50, DashboardLib::getVariation(20, 40), 'une baisse garde son signe');
		$this->assertEquals(-12, DashboardLib::getVariation(23, 26.1), 'arrondi à l\'unité');
		$this->assertEquals(NULL, DashboardLib::getVariation(3, 0), 'pas de période d\'avant');
		$this->assertEquals(NULL, DashboardLib::getVariation(100, 100.4), 'zéro une fois arrondi');

	}

}
?>
