<?php
namespace main;

/**
 * Les onglets de tête d'un écran (`DesignUi::getPageTabs()`).
 *
 * `component.css` reconnaît la rangée par filiation directe (`.tabs-page > .tabs-page-head > .tabs-item`)
 * et `Lime.Tab` cherche les panneaux parmi les enfants du `.tabs` : un nœud glissé entre deux niveaux
 * rendrait des onglets sans leur forme, ou des panneaux qui ne basculent plus, sans aucune erreur.
 * Le test fixe donc l'arbre rendu, pas seulement la présence des classes.
 *
 * Lancement : dev/test package=main file=PageTabsAi
 */
class PageTabsAiTest extends \Test {

	const string TABS = '<a class="tab-item selected">Plan</a><a class="tab-item">Liste</a>';

	private function getTabs(string $html): \Dom\Element {
		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR)->body->firstElementChild;
	}

	public function testTabsAlone(): void {

		$html = DesignUi::getPageTabs(self::TABS);

		$this->assertEquals('<div class="tabs tabs-lg tabs-page "><div class="tabs-page-head"><div class="tabs-item">'.self::TABS.'</div></div></div>', $html, 'des onglets seuls ne rendent pas la rangée et rien d\'autre');

	}

	/**
	 * `Lime.TabFold` cherche la rangée par `:scope > .tabs-item` dans l'élément qui porte `tabs-h-fold`,
	 * et `component.css` masque les onglets repliés par `.tabs-h-folded > .tabs-item > .tab-item` : posée
	 * sur le `.tabs`, la classe ne replierait rien, sans aucune erreur.
	 */
	public function testFoldGoesOnTheRow(): void {

		$tabs = $this->getTabs(DesignUi::getPageTabs(self::TABS, fold: TRUE));

		$this->assertTrue($tabs->classList->contains('tabs-h-fold') === FALSE, 'tabs-h-fold est sur le .tabs, où Lime.TabFold ne trouve pas la rangée');
		$this->assertEquals(1, $tabs->querySelectorAll('.tabs-page-head.tabs-h-fold > .tabs-item')->length, 'tabs-h-fold n\'est pas sur le parent direct des onglets');
		$this->assertEquals(0, $this->getTabs(DesignUi::getPageTabs(self::TABS))->querySelectorAll('.tabs-h-fold')->length, 'des onglets se replient sans qu\'on l\'ait demandé');

	}

	public function testToolsAndPanelsKeepTheirLevel(): void {

		$tabs = $this->getTabs(DesignUi::getPageTabs(
			self::TABS,
			tools: '<div class="tools-first"></div><div class="tools-second"></div>',
			panels: '<div class="tab-panel selected" data-tab="plan"></div><div class="tab-panel" data-tab="list"></div>'
		));

		$head = $tabs->firstElementChild;

		$this->assertEquals('tabs-page-head', $head->className, 'la rangée n\'est pas le premier enfant du .tabs');
		$this->assertEquals(3, $head->childElementCount, 'chaque outil de premier niveau n\'est pas une colonne de la rangée, à la suite des onglets');
		$this->assertEquals('tabs-item', $head->firstElementChild->className, 'les onglets ne sont pas la première colonne de la rangée');
		$this->assertEquals(2, $head->firstElementChild->childElementCount, 'les onglets ne sont pas les enfants directs de .tabs-item');
		$this->assertEquals(3, $tabs->childElementCount, 'les panneaux ne sont pas des enfants directs du .tabs, où Lime.Tab les cherche');
		$this->assertTrue($head->nextElementSibling?->classList->contains('tab-panel') === TRUE, 'les panneaux ne suivent pas la rangée');
		$this->assertTrue($tabs->lastElementChild->classList->contains('tab-panel'), 'le dernier panneau n\'est pas au niveau du premier');

	}

	public function testAttributesGoOnTabs(): void {

		$tabs = $this->getTabs(DesignUi::getPageTabs(self::TABS, attributes: [
			'id' => 'screen-tabs',
			'class' => 'screen-views',
			'onrender' => 'Lime.Tab.restore(this, "plan")'
		]));

		$this->assertEquals('screen-tabs', $tabs->id, 'l\'id ne va pas sur le .tabs');
		$this->assertEquals(['tabs', 'tabs-lg', 'tabs-page', 'screen-views'], iterator_to_array($tabs->classList), 'la classe de l\'écran ne s\'ajoute pas à celles du composant');
		$this->assertEquals('Lime.Tab.restore(this, "plan")', $tabs->getAttribute('onrender'), 'un attribut qui porte des guillemets n\'arrive pas intact');

	}

	/**
	 * Les places d'où les onglets montent dans la tête de l'écran sont écrites trois fois dans
	 * `component.css`, pour la tête, pour les onglets, puis pour la tête d'onglets à second libellé, et
	 * les deux premières listes sont jouées ici telles que la feuille les écrit : une place ajoutée à
	 * l'une seulement ferait monter des onglets sans que la tête leur garde leur hauteur ni leur filet, ou
	 * l'inverse. La troisième doit égaler la première, sans quoi ces onglets monteraient dans une tête
	 * qui ne garde pas la place de leur second libellé.
	 */
	public function testTabsRiseOnlyThroughScreenBlocks(): void {

		$css = file_get_contents(LIME_DIRECTORY.'/ouvretaferme/main/static/css/component.css');

		$foundHeader = preg_match('/^main:has\(\n(.*?)\n\) \{\n(.*?)\n\}/ms', $css, $matchesHeader);
		$foundTabs = preg_match('/^\.tabs-page:not\(\n(.*?)\n\) \{\n(.*?)\n\}/ms', $css, $matchesTabs);
		$foundDetail = preg_match('/^main:has\(:is\(\n(.*?)\n\) > .*?\{\n(.*?)\n\}/ms', $css, $matchesDetail);

		if($this->assertTrue($foundHeader === 1 and $foundTabs === 1 and $foundDetail === 1, 'les trois listes de places ne se lisent plus dans component.css, une place par ligne') === FALSE) {
			return;
		}

		$this->assertEquals($matchesHeader[1], $matchesTabs[1], 'la tête et les onglets ne lisent pas la même liste de places');
		$this->assertEquals($matchesHeader[1], $matchesDetail[1], 'la tête d\'onglets à second libellé ne lit pas la même liste de places que la tête');
		$this->assertTrue(str_contains($matchesDetail[2], '--structure-onglets-hauteur:'), 'la tête ne reçoit plus la hauteur des onglets à second libellé');
		$this->assertTrue(str_contains($matchesHeader[2], '--structure-onglets-hauteur:') and str_contains($matchesHeader[2], '--structure-onglets-filet:'), 'la tête ne reçoit plus la hauteur des onglets et l\'épaisseur de leur filet');
		$this->assertTrue(str_contains($matchesTabs[2], '--structure-onglets-montes: 0;'), 'des onglets hors de ces places ne restent plus dans le contenu');

		$tabs = DesignUi::getPageTabs(self::TABS);

		$cases = [
			'en tête de l\'écran' => [TRUE, $tabs],
			'après un script' => [TRUE, '<script></script>'.$tabs],
			'dans un bloc d\'écran' => [TRUE, '<div id="screen">'.$tabs.'</div>'],
			'dans le bloc d\'écran d\'une famille d\'écrans' => [TRUE, '<div id="family"><div id="screen"><script></script>'.$tabs.'</div></div>'],
			'après un encart' => [FALSE, '<div class="util-block-info"></div>'.$tabs],
			'après un encart, dans un bloc d\'écran' => [FALSE, '<div id="screen"><div class="util-block-info"></div>'.$tabs.'</div>'],
			'dans un second bloc d\'écran' => [FALSE, '<div id="first"></div><div id="screen">'.$tabs.'</div>'],
			'à trois blocs du conteneur' => [FALSE, '<div id="family"><div id="group"><div id="screen">'.$tabs.'</div></div></div>'],
			'dans une boîte' => [FALSE, '<div class="util-block">'.$tabs.'</div>'],
			'dans une boîte qui porte un identifiant' => [FALSE, '<div id="screen" class="util-block">'.$tabs.'</div>'],
			'dans une carte' => [FALSE, '<div class="util-card"><div class="util-block">'.$tabs.'</div></div>'],
			'dans le panneau d\'autres onglets' => [FALSE, '<div class="tabs"><div class="tab-panel selected">'.$tabs.'</div></div>'],
			'dans un bloc sans identifiant' => [FALSE, '<div>'.$tabs.'</div>'],
		];

		foreach($cases as $case => [$rises, $html]) {

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body><main><div class="structure-template-main-container">'.$html.'</div></main></body></html>', LIBXML_NOERROR);

			$this->assertEquals($rises ? 0 : 1, $document->querySelectorAll('.tabs-page:not('.$matchesTabs[1].')')->length, 'les onglets ne sont pas à la place attendue, '.$case);
			$this->assertEquals($rises ? 1 : 0, $document->querySelectorAll('main:has('.$matchesHeader[1].')')->length, 'la tête ne garde pas la place attendue, '.$case);

		}

	}

}
