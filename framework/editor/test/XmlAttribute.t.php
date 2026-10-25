<?php
namespace editor;

/**
 * Aucune balise que le nettoyage admet ne garde un attribut que l'éditeur ne pose pas.
 *
 * Ce qui sort de XmlLib::fromHtml() est rendu tel quel par les formateurs, sur les sites publics
 * compris : un onclick ou un onrender qui traverse le nettoyage s'exécute chez le lecteur, un
 * data-ajax fait de l'élément un déclencheur d'action. Les deux listes sont lues dans le code, pour
 * qu'une balise admise ou un attribut interprété plus tard soit sondé sans qu'on pense à l'ajouter ici :
 * - les balises, dans les sources de l'éditeur, et une balise compte quand elle change la sortie par
 *   rapport à une balise inconnue placée au même endroit ;
 * - les data-* que le JS du cadriciel interprète, moins ceux que l'éditeur admet ou dont il lit
 *   la valeur, dont les valeurs sont éprouvées balise par balise dans XmlSpan, XmlUrl et XmlFigure.
 *
 * Angle mort : les data-* que lit le JS d'un package hors de framework/ ne sont pas lus ici. Seul
 * data-block-video, que lit base/block, est posé à la main. Le nettoyage est une liste blanche et les
 * retire comme les autres, mais le test ne le prouve que pour les attributs qu'il pose.
 *
 * Lancement : dev/test package=editor file=XmlAttribute
 */
class XmlAttributeTest extends \Test {

	const IMAGE = '<div data-type="image" data-url="https://example.org/a.jpg" data-color="#ffffff" data-w="10" data-h="10"></div>';

	// La poignée de fin que l'éditeur pose dans chaque figure, et que le nettoyage retire
	const HANDLE = '<span contenteditable="false"></span>';

	// Ce qu'exigent les balises qui ne survivent qu'avec un attribut : lien, figure, média
	const REQUIRED = 'href="https://example.org/" data-interactive="true" data-type="image" data-url="https://example.org/b.jpg" data-color="#000000" data-w="20" data-h="20"';

	const EVENTS = ['onclick', 'onmouseover', 'onfocus', 'onerror', 'onload', 'onanimationstart', 'onrender'];

	// Chaque valeur interdite porte « evil » ou « alert( » : la retrouver dans une sortie suffit.
	// Pas de « background » : le rendu d'une image pose lui-même un background-color
	const MARKERS = ['alert(', 'evil', 'javascript:'];

	public function testReportedListItemLosesItsAttributes(): void {

		$xml = new \editor\XmlLib()->fromHtml('<ul><li onclick="alert(1)" onrender="alert(3)" data-ajax="/evil:doDelete" style="color:red" data-block-video="x">a</li></ul><p onclick="alert(2)">b</p>');

		$this->assertEquals('<main><ul><li>a</li></ul><p>b</p></main>', $xml);

		$html = new \editor\EditorUi()->value($xml);

		foreach(['onclick', 'onrender', 'data-ajax', 'data-block-video', 'style'] as $attribute) {
			$this->assertFalse(str_contains($html, $attribute), $attribute.' dans le rendu');
		}

	}

	public function testForbiddenAttributesAreRemovedFromEveryAdmittedTag(): void {

		// Un niveau de plus que EditorSetting::LIST_DEPTH_LIMIT : unwrapListNode() remonte ses items
		$deepList = '<ul>%s</ul>';

		for($depth = 0; $depth < EditorSetting::LIST_DEPTH_LIMIT; $depth++) {
			$deepList = '<ul><li>a'.$deepList.'</li></ul>';
		}

		$contexts = [
			'racine' => '%s',
			'p' => '<p>%s</p>',
			'h2' => '<h2>%s</h2>',
			'ul' => '<ul>%s</ul>',
			'li' => '<ul><li>%s</li></ul>',
			'liste imbriquée' => '<ul><li>a<ol>%s</ol></li></ul>',
			'liste au-delà de la profondeur' => $deepList,
			'figure' => '<figure data-interactive="true">'.self::IMAGE.'%s'.self::HANDLE.'</figure>',
			'citation' => '<figure data-interactive="true"><div data-type="quote"><div class="editor-quote"><div></div><blockquote>%s</blockquote></div></div>'.self::HANDLE.'</figure>',
			'grille' => '<figure data-interactive="true"><div data-type="grid"><div class="editor-grid" data-columns="2"><div contenteditable="true">%s</div></div></div>'.self::HANDLE.'</figure>',
		];

		$bodies = [
			'texte' => ['x', array_keys($contexts)],
			// Une liste vide est retirée : sans item, un <ul> ou un <ol> ne garderait rien à sonder
			'item' => ['<li>x</li>', array_keys($contexts)],
			'image' => [self::IMAGE, ['racine', 'figure']],
		];

		// draft ne retire que repair(), qui enveloppe dans un <p> un nœud de racine inconnu et lève une
		// notice en dev : ce <p> est nettoyé comme le contexte p, sondé à part
		$options = ['acceptFigure' => TRUE, 'draft' => TRUE];

		$forbidden = $this->getForbiddenAttributes();

		$tagsProbed = [];

		foreach($this->getTags() as $tag) {

			foreach($bodies as $bodyName => [$body, $contextNames]) {

				foreach($contextNames as $contextName) {

					$context = $contexts[$contextName];

					$unknown = new \editor\XmlLib()->fromHtml(sprintf($context, '<zzzz '.self::REQUIRED.'>'.$body.'</zzzz>'), $options);
					$clean = new \editor\XmlLib()->fromHtml(sprintf($context, '<'.$tag.' '.self::REQUIRED.'>'.$body.'</'.$tag.'>'), $options);

					if($clean === NULL or $clean === $unknown) {
						continue;
					}

					$tagsProbed[$tag] = TRUE;

					$xml = (string)new \editor\XmlLib()->fromHtml(sprintf($context, '<'.$tag.' '.self::REQUIRED.' '.$forbidden.'>'.$body.'</'.$tag.'>'), $options);

					$outputs = [
						'XML' => $xml,
						'lecture' => new \editor\ReadorFormatterUi()->getFromXml($xml),
						'éditeur' => new \editor\EditorFormatterUi()->getFromXml($xml),
					];

					$leaks = [];

					foreach($outputs as $output => $value) {
						foreach(self::MARKERS as $marker) {
							if(str_contains($value, $marker)) {
								$leaks[] = $output.' '.$marker;
							}
						}
					}

					$this->assertEquals([], $leaks, '<'.$tag.'> dans le contexte '.$contextName.' / '.$bodyName);

				}

			}

		}

		// Sans ces balises et ces attributs, le balayage aurait cessé de sonder ce que l'éditeur produit
		foreach(['p', 'h2', 'ul', 'ol', 'li', 'a', 'b', 'span', 'figure', 'figcaption', 'div'] as $tag) {
			$this->assertTrue(array_key_exists($tag, $tagsProbed), '<'.$tag.'> n\'est plus sondée');
		}

		foreach(['data-ajax', 'data-ajax-method', 'data-ajax-body', 'data-ajax-form', 'data-ajax-target', 'data-confirm', 'data-dropdown', 'data-href'] as $attribute) {
			$this->assertTrue(str_contains($forbidden, ' '.$attribute.'='), $attribute.' n\'est plus sondé');
		}

	}

	/**
	 * Chaque nom de balise possible que le code de l'éditeur écrit en toutes lettres
	 */
	private function getTags(): array {

		$tags = [];

		foreach($this->getEditorSources() as $file) {
			preg_match_all("/'([a-z][a-z0-9]*)'/", file_get_contents($file), $matches);
			$tags = array_merge($tags, $matches[1]);
		}

		return array_values(array_unique($tags));

	}

	/**
	 * Les événements, le style, id, class, et les data-* que le JS du cadriciel interprète sans que l'éditeur les lise
	 */
	private function getForbiddenAttributes(): string {

		$attributes = [];

		foreach(glob(LIME_DIRECTORY.'/framework/*/static/js/*.js') as $file) {

			$content = file_get_contents($file);

			preg_match_all('/data-[a-z][a-z0-9-]*[a-z0-9]/', $content, $matches);
			$attributes = array_merge($attributes, $matches[0]);

			preg_match_all('/\.dataset\.([a-zA-Z]+)/', $content, $matches);

			foreach($matches[1] as $property) {
				$attributes[] = 'data-'.strtolower(preg_replace('/([A-Z])/', '-$1', $property));
			}

		}

		// Seuls les noms que l'éditeur écrit en chaîne, ceux qu'il admet ou dont il lit la valeur : un nom cité en commentaire reste sondé
		$editor = [];

		foreach($this->getEditorSources() as $file) {
			preg_match_all("/'(data-[a-z][a-z0-9-]*[a-z0-9])\\??'/", file_get_contents($file), $matches);
			$editor = array_merge($editor, $matches[1]);
		}

		$attributes = array_unique(array_diff($attributes, $editor));
		sort($attributes);

		$values = [];

		foreach(self::EVENTS as $event) {
			$values[] = $event.'="alert(1)"';
		}

		$values[] = 'style="background-image:url(javascript:alert(1))"';
		$values[] = 'id="evil"';
		$values[] = 'class="evil"';
		$values[] = 'data-block-video="evil"';

		foreach($attributes as $attribute) {
			$values[] = $attribute.'="/evil:doDelete"';
		}

		return implode(' ', $values);

	}

	private function getEditorSources(): array {

		$files = glob(LIME_DIRECTORY.'/framework/editor/lib/*.php');
		$files[] = LIME_DIRECTORY.'/framework/editor/Editor.s.php';

		return $files;

	}

}
?>
