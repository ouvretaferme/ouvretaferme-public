<?php
namespace toolbox;

/**
 * Le choix des semaines livrées (`CompositionUi::getWeeksDates()`), que le panneau « Les semaines
 * livrées » et l'étape « Quelles semaines livrez-vous ? » du départ partagent : la case « Toute
 * l'année » (lot r43, « Ajouter une case à cocher "Toute l'année" ») se pose en tête, avant les mois,
 * avec le rendu d'une case de mois, et ne poste rien.
 *
 * Seul le rendu s'éprouve ici : ce que la case coche et l'état qu'elle prend se jouent dans le
 * navigateur (`ToolboxComposition.checkWeeks()` et `updateMonths()` dans `composition.js`).
 *
 * Aucune lecture ni écriture en base.
 *
 * Lancement : dev/test package=toolbox file=CompositionWeeks
 */
class CompositionWeeksTest extends \Test {

	const SEASON = 2026;

	/**
	 * « Toute l'année » est la première case du choix, hors de tout mois, dans le même `util-label`
	 * qu'une case de mois et rendue par le même champ du cadriciel.
	 */
	public function testAllBeforeMonths(): void {

		$xpath = $this->getXpath([]);

		$months = count(CompositionLib::getSeasonMonths(self::SEASON));

		$all = '//div[contains(concat(" ", @class, " "), " toolbox-composition-weeks ")]/div[contains(concat(" ", @class, " "), " toolbox-composition-weeks-all ")]';

		$this->assertEquals(1, $xpath->query($all)->length, 'une seule case « Toute l\'année », enfant direct du choix');
		$this->assertEquals(0, $xpath->query($all.'/preceding-sibling::*')->length, 'en tête, avant le premier mois');
		$this->assertEquals($months, $xpath->query($all.'/following-sibling::div[@class="util-dates-month"]')->length, 'suivie de tous les mois');
		$this->assertEquals(1, $xpath->query($all.'[contains(concat(" ", @class, " "), " util-label ")]/label[contains(concat(" ", @class, " "), " field-checkbox ")]/input[@type="checkbox"]')->length, 'un champ du cadriciel dans un util-label, comme la case d\'un mois');
		$this->assertEquals($months, $xpath->query('//div[@class="util-dates-month"]/div[contains(concat(" ", @class, " "), " util-label ")]/label[contains(concat(" ", @class, " "), " field-checkbox ")]/input[@type="checkbox"]')->length, 'la forme de référence, celle des cases de mois');
		$this->assertEquals(s("Toute l'année"), trim($xpath->query($all.'/label')->item(0)->textContent), 'son libellé');

	}

	/**
	 * La case ne poste rien : ni elle ni les cases de mois ne portent de nom, et le seul nom du choix
	 * est `weeks[]`, celui que `build()` lit. Elle n'est jamais cochée à l'écriture, même quand toutes
	 * les semaines le sont : son état se dérive dans le navigateur.
	 */
	public function testAllPostsNothing(): void {

		$weeks = CompositionLib::getSeasonWeeks();

		foreach(['aucune semaine' => [], 'toutes les semaines' => $weeks] as $case => $weeksChecked) {

			$xpath = $this->getXpath($weeksChecked);

			$names = [];

			foreach($xpath->query('//input[@name]') as $input) {
				$names[$input->getAttribute('name')] = TRUE;
			}

			$this->assertEquals(['weeks[]'], array_keys($names), $case.' : le seul nom posté est celui des semaines');
			$this->assertEquals(count($weeks), $xpath->query('//input[@name="weeks[]"]')->length, $case.' : une case par semaine de la saison');
			$this->assertEquals(count($weeksChecked), $xpath->query('//input[@name="weeks[]"][@checked]')->length, $case.' : les semaines cochées');
			$this->assertEquals(0, $xpath->query('//div[contains(concat(" ", @class, " "), " util-label ")]//input[@name or @checked]')->length, $case.' : les cases de tête n\'ont ni nom, ni état écrit');

		}

	}

	/**
	 * « Toute l'année » porte sur tout le choix, la case d'un mois sur son mois, par le même geste ; le
	 * rendu du choix recalcule les cases de tête.
	 */
	public function testAllScope(): void {

		$xpath = $this->getXpath([]);

		$onclick = fn(string $query) => array_values(array_unique(array_map(fn(\DOMElement $input) => $input->getAttribute('onclick'), iterator_to_array($xpath->query($query)))));

		$this->assertEquals(['ToolboxComposition.checkWeeks(this, \'.toolbox-composition-weeks\')'], $onclick('//div[contains(concat(" ", @class, " "), " toolbox-composition-weeks-all ")]//input'), '« Toute l\'année » coche tout le choix');
		$this->assertEquals(['ToolboxComposition.checkWeeks(this, \'.util-dates-month\')'], $onclick('//div[@class="util-dates-month"]/div[contains(concat(" ", @class, " "), " util-label ")]//input'), 'la case d\'un mois coche son mois');
		$this->assertEquals('ToolboxComposition.updateMonths(this)', $xpath->query('//div[contains(concat(" ", @class, " "), " toolbox-composition-weeks ")]')->item(0)->getAttribute('onrender'), 'l\'état des cases de tête se pose au rendu');

	}

	/**
	 * Le choix rendu pour la saison, enveloppé pour que son conteneur se lise par sa classe.
	 */
	private function getXpath(array $weeks): \DOMXPath {

		$html = CompositionUi::getWeeksDates(new \util\FormUi(), self::SEASON, CompositionLib::getSeasonMonths(self::SEASON), $weeks);

		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return new \DOMXPath($document);

	}

}
?>
