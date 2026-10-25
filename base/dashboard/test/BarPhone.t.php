<?php
namespace dashboard;

/**
 * La barre du téléphone (L23, décision du 2026-10-11 : « on garde uniquement la nouvelle nav
 * mobile »). Elle est rendue avec les onglets, et c'est la largeur qui choisit : sous 768 px de large ou
 * 600 de haut, la barre paraît en bas de l'écran et les onglets de l'en-tête se retirent.
 *
 * Trois promesses, lues dans la feuille, aucune ne passant par le rendu :
 * - hors de ce bloc, la barre n'est montrée à aucune largeur ;
 * - dans ce bloc, les onglets de l'en-tête se retirent ;
 * - les entrées de la barre se partagent toute sa largeur, quel que soit leur nombre : deux pour une
 *   AMAP, cinq pour un producteur, sans colonne attitrée qui laisserait une place vide.
 *
 * Que la feuille donne bien ce rendu dans un navigateur ne s'éprouve pas ici : le contrôle est fait
 * dans Chrome, à 360, 772 et 1000 px (livraison de L23).
 *
 * Lancement : dev/test package=dashboard file=BarPhone
 */
class BarPhoneTest extends \Test {

	public function testBarIsHiddenOutsidePhone(): void {

		[$source, $phone] = $this->getSheet();

		if($this->assertDecorPresent($phone !== NULL, 'la feuille déclare le bloc du téléphone') === FALSE) {
			return;
		}

		$this->assertEquals(1, preg_match('/^\.structure-bar,\n[^{]*\{\s*display: none;\s*\}/m', $source), 'la barre est masquée hors de tout bloc de largeur');

	}

	public function testTabsAreHiddenOnPhone(): void {

		[, $phone] = $this->getSheet();

		$rules = $this->getRules($phone);

		$this->assertEquals('none', $rules['body[data-template~="structure"] > header #structure-nav-sections']['display'] ?? NULL, 'les onglets de l\'en-tête se retirent sous la barre');

	}

	public function testEntriesShareTheWidth(): void {

		[, $phone] = $this->getSheet();

		$rules = $this->getRules($phone);

		$bar = $rules['body[data-template~="structure"] .structure-bar'] ?? [];

		if($this->assertDecorPresent(isset($bar['grid-auto-flow']), 'la barre déclare sa grille') === FALSE) {
			return;
		}

		$this->assertEquals('column', $bar['grid-auto-flow'], 'les entrées se suivent en colonnes');
		$this->assertEquals('minmax(0, 1fr)', $bar['grid-auto-columns'] ?? NULL, 'les colonnes se partagent la largeur à parts égales');
		$this->assertEquals(FALSE, isset($bar['grid-template-columns']), 'la barre ne fixe pas son nombre de colonnes : une AMAP en a deux, un producteur cinq');

		$selectors = [];

		foreach($rules as $selector => $declarations) {
			if(isset($declarations['grid-column']) and str_contains($selector, '.structure-bar')) {
				$selectors[] = $selector;
			}
		}

		$this->assertEquals([], $selectors, 'aucune entrée n\'a de colonne attitrée, qui laisserait les autres vides');

	}

	/**
	 * La feuille et son bloc du téléphone, lu jusqu'à l'accolade qui le ferme.
	 */
	protected function getSheet(): array {

		$source = file_get_contents(LIME_DIRECTORY.'/ouvretaferme/main/static/css/template-structure.css');

		$start = strpos($source, "\n@media (max-width: 767px), (max-height: 599px) {");

		if($start === FALSE) {
			return [$source, NULL];
		}

		$open = strpos($source, '{', $start);
		$depth = 0;

		for($position = $open; $position < strlen($source); $position++) {

			if($source[$position] === '{') {
				$depth++;
			} else if($source[$position] === '}') {

				$depth--;

				if($depth === 0) {
					return [$source, substr($source, $open + 1, $position - $open - 1)];
				}

			}

		}

		return [$source, NULL];

	}

	/**
	 * Chaque sélecteur du bloc, un par ligne d'une liste, avec ses déclarations, sans les commentaires.
	 */
	protected function getRules(string $block): array {

		$block = preg_replace('/\/\*.*?\*\//s', '', $block);

		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $block, $matches, PREG_SET_ORDER);

		$rules = [];

		foreach($matches as [, $selectors, $body]) {

			preg_match_all('/([a-z-]+)\s*:\s*([^;]+);/', $body, $declarations, PREG_SET_ORDER);

			$values = [];

			foreach($declarations as [, $property, $value]) {
				$values[$property] = trim($value);
			}

			foreach(explode(',', $selectors) as $selector) {
				$selector = trim($selector);
				$rules[$selector] = array_merge($rules[$selector] ?? [], $values);
			}

		}

		return $rules;

	}

}
?>
