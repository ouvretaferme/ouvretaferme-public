<?php
namespace map;

/**
 * Le PDF du plan de la ferme (`map:downloadPlan`, puis `map:getPlan` que Puppeteer imprime) : quelles parcelles ont leur carte
 * après celle de la structure (`MapLib::getZonesPrinted()`), où elle se pose dans le plan en liste, et ce que la page imprimée
 * pose pour le rendu des cartes, sur le fond demandé.
 *
 * Aucune écriture, aucune base ouverte : les parcelles sont des éléments construits dans le cas, et la page se rend depuis
 * l'UI sur ces éléments.
 *
 * Lancement : dev/test package=map file=PrintAi
 */
class PrintAiTest extends \Test {

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	private function getZones(array $drawn): \Collection {

		$cZone = new \Collection();

		foreach($drawn as $position => $isDrawn) {

			$id = 900000 + $position;

			$cZone[$id] = new Zone([
				'id' => $id,
				'name' => 'Parcelle '.$position,
				'area' => 10000,
				'coordinates' => $isDrawn ? self::SQUARE : NULL,
				'cPlot' => new \Collection()
			]);

		}

		return $cZone;

	}

	public function testEveryDrawnZoneHasItsMap(): void {

		$cZone = $this->getZones([TRUE, FALSE, TRUE, TRUE]);

		$this->assertEquals([900000, 900002, 900003], MapLib::getZonesPrinted($cZone)->getIds(), 'chaque parcelle tracée a sa carte, celle sans contour n\'en a pas');

	}

	public function testSingleDrawnZoneHasNoMapOfItsOwn(): void {

		$this->assertTrue(MapLib::getZonesPrinted($this->getZones([TRUE]))->empty(), 'une structure d\'une seule parcelle n\'a que la carte de la structure, déjà cadrée sur elle');
		$this->assertTrue(MapLib::getZonesPrinted($this->getZones([TRUE, FALSE]))->empty(), 'de même quand une seule de ses deux parcelles est tracée');
		$this->assertTrue(MapLib::getZonesPrinted($this->getZones([FALSE, FALSE]))->empty(), 'et sans parcelle tracée, aucune carte');

	}

	public function testPrintingPosesZoneMapsInTheList(): void {

		$eStructure = new \structure\Structure(['id' => \structure\FixtureAiLib::F1, 'name' => 'Structure imprimée']);
		$cZone = $this->getZones([TRUE, FALSE, TRUE]);
		$cZonePrinted = MapLib::getZonesPrinted($cZone);

		$h = new PlanUi()->getPlanPrinting($eStructure, 2026, $cZone, $cZonePrinted, MapLib::getBounds($cZone), \structure\Member::SATELLITE);

		$this->assertEquals(1, substr_count($h, 'class="plan-print-page plan-print-satellite"'), 'une seule page de carte, celle de la structure, sur le fond demandé');
		$this->assertTrue(str_contains($h, 'id="plan-print-zone-900000" class="plan-print-map plan-print-zone plan-print-satellite"') and str_contains($h, 'id="plan-print-zone-900002"'), 'les cartes des deux parcelles tracées, sur le même fond');
		$this->assertFalse(str_contains($h, 'id="plan-print-zone-900001"'), 'aucune pour la parcelle sans contour');
		$this->assertTrue(str_contains($h, '.printMaps([{"map":"plan-print-structure","zone":null},{"map":"plan-print-zone-900000","zone":900000},{"map":"plan-print-zone-900002","zone":900002}])'), 'le rendu reçoit les cadres dans l\'ordre des cartes');
		$this->assertTrue(str_contains($h, 'basemap: "satellite"'), 'la carte se rend sur le fond demandé');
		$this->assertEquals(1, substr_count($h, 'pdf-structure-heading">'.s("Plan de la ferme {value}", 2026)), 'l\'en-tête du plan en liste ne se pose qu\'une fois');
		$this->assertTrue(strpos($h, 'pdf-structure-heading">'.s("Plan de la ferme {value}", 2026)) < strpos($h, 'id="plan-zone-900000"'), 'et ouvre la liste, avant la première parcelle');
		$this->assertTrue(strpos($h, 'id="plan-zone-900000"') < strpos($h, 'id="plan-print-zone-900000"') and strpos($h, 'id="plan-print-zone-900000"') < strpos($h, 'data-zone="900000"'), 'la carte d\'une parcelle se pose sous sa barre, avant ses jardins');
		$this->assertEquals(1, substr_count($h, 'class="plan-schema plan-schema-print"'), 'les parcelles se suivent dans un seul plan, sans page à part');

	}

	public function testPrintingWithoutDrawnZoneHasNoMap(): void {

		$eStructure = new \structure\Structure(['id' => \structure\FixtureAiLib::F1, 'name' => 'Structure imprimée']);
		$cZone = $this->getZones([FALSE]);

		$h = new PlanUi()->getPlanPrinting($eStructure, 2026, $cZone, MapLib::getZonesPrinted($cZone), MapLib::getBounds($cZone), \structure\Member::DRAWING);

		$this->assertFalse(str_contains($h, 'plan-print-page'), 'sans parcelle tracée, le PDF commence par le plan en liste');
		$this->assertFalse(str_contains($h, 'pdfWait'), 'et n\'attend aucune carte');

	}

}
?>
