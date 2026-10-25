<?php
namespace dashboard;

/**
 * Les bandes d'univers (chantier dashboard, L10, morceau 2) : une bande par univers, une seule
 * colorée, et les cellules qui y lisent leur chiffre et leur pli dans le socle. Depuis L45 l'accueil
 * et son réglage rangent en colonnes (`Column.t.php`) : les bandes se lisent ici par `getUnivers()`, la
 * forme du tableau de bord d'une AMAP, la seule qui les garde.
 *
 * Ce qui s'y prouve :
 *
 * 1. la bande colorée est la première de Vente ou de Gestion, quelle que soit la place de Production
 *    ou de l'AMAP avant elle, et aucune ne l'est sans l'un des deux ;
 * 2. elle seule se colore ;
 * 3. le chiffre d'une cellule pose son complément et sa ligne seulement quand on les lui donne ;
 * 4. le pli est fermé, sauf quand le package le demande ouvert.
 *
 * Aucune base ouverte au-delà de la structure F1, qui ne sert qu'à nommer les adresses. Les blocs sont
 * des blocs lourds (`data` à `NULL`) : le socle rend leur cadre sans appeler le `render()` du package.
 *
 * Lancement : dev/test package=dashboard file=Univers
 */
class UniversTest extends \Test {

	public function testTheColoredBandIsTheFirstOfSellingOrAccounting(): void {

		$ui = new DashboardUi();

		$this->assertEquals('commercialisation', $ui->getColoredSection(['production', 'amap', 'commercialisation', 'accounting']), 'Vente avant Gestion, après Production et l\'AMAP');
		$this->assertEquals('accounting', $ui->getColoredSection(['production', 'accounting', 'commercialisation']), 'Gestion quand elle vient la première des deux');
		$this->assertEquals('accounting', $ui->getColoredSection(['amap', 'accounting']), 'Gestion sans Vente');
		$this->assertEquals(NULL, $ui->getColoredSection(['production', 'amap', 'settings']), 'aucune bande colorée sans Vente ni Gestion');

	}

	public function testAtMostOneBandIsColored(): void {

		$html = $this->getBands(['series-week' => 'production', 'selling-unpaid' => 'commercialisation', 'bank-reconciliation' => 'accounting']);

		$this->assertEquals(1, substr_count($html, 'is-colored'), 'une seule bande colorée, Vente et Gestion présentes');

		$html = $this->getBands(['series-week' => 'production', 'amap-duties' => 'amap']);

		$this->assertFalse(str_contains($html, 'is-colored'), 'aucune sans Vente ni Gestion');

	}

	public function testAValueCarriesWhatItIsGiven(): void {

		$html = DashboardUi::getValue('12,00 €', 'restant dû', 'TTC');

		$this->assertTrue(str_contains($html, 'TTC') and str_contains($html, 'restant dû'), 'le complément et la ligne quand on les donne');
		$this->assertEquals('3', strip_tags(DashboardUi::getValue('3')), 'ni complément ni ligne quand on ne les donne pas');

	}

	public function testAFoldIsClosedUnlessAsked(): void {

		$this->assertFalse(str_contains(DashboardUi::getFold('2 sans jour', '<div></div>'), ' open'), 'fermé par défaut');
		$this->assertTrue(str_contains(DashboardUi::getFold('2 sans jour', '<div></div>', TRUE), ' open'), 'ouvert à la demande');

	}

	/**
	 * Les bandes rendues sur des blocs lourds, un par univers donné : `$blocks` porte le nom du bloc et
	 * son univers, dans l'ordre où le moteur les aurait rangés.
	 */
	private function getBands(array $blocks): string {

		$eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);

		$entries = [];

		foreach($blocks as $block => $section) {
			$entries[$block] = ['declaration' => ['section' => $section, 'shape' => DashboardSetting::TILE, 'choose' => FALSE], 'url' => '/', 'data' => NULL];
		}

		return new DashboardUi()->getUnivers($eStructure, $entries);

	}

}
?>
