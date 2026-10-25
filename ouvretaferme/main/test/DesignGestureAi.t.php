<?php
namespace main;

class DesignGestureAiTest extends \Test {

	/**
	 * L'adresse du bouton de création est échappée, elle porte souvent plusieurs paramètres, et une
	 * classe passée par l'appelant rejoint celles du bouton : un second attribut class serait ignoré.
	 */
	public function testCreateButtonEscapesTheUrlAndKeepsOneClass() {

		$h = DesignUi::getCreateButton('/liste?a=1&b=2', 'Nouveau', attributes: ['class' => 'dropdown-toggle']);

		$this->assertTrue(str_contains($h, 'href="/liste?a=1&amp;b=2"'), 'l\'adresse du bouton n\'est pas échappée');
		$this->assertEquals(1, preg_match('/^<a [^>]*>/', $h, $match), 'le bouton n\'est pas un lien');
		$this->assertEquals(1, preg_match_all('/\bclass="/', $match[0]), 'le bouton porte plusieurs attributs class');
		$this->assertEquals(1, preg_match('/class="btn btn-secondary dropdown-toggle"/', $match[0]), 'la classe passée par l\'appelant est perdue');

	}

	/**
	 * Le data-tab d'un onglet est échappé : Lime.Tab le compare à celui du panneau, une valeur
	 * calculée ne doit pas casser l'attribut.
	 */
	public function testTabItemEscapesItsTab() {

		$h = DesignUi::getTabItem('a"b', 'Onglet');

		$this->assertTrue(str_contains($h, 'data-tab="a&quot;b"'), 'le data-tab de l\'onglet n\'est pas échappé');

	}

	/**
	 * La bascule ferme sur le bloc qu'on lui nomme : un emplacement d'un deck à plusieurs se déplie
	 * seul, sans ouvrir tout le deck.
	 */
	public function testDeckToggleClosesOnItsContainer() {

		$this->assertTrue(str_contains(DesignUi::getDeckToggle(), 'closest(&quot;.util-deck&quot;)'), 'la bascule ne ferme pas sur util-deck');
		$this->assertTrue(str_contains(DesignUi::getDeckToggle('util-deck-slot'), 'closest(&quot;.util-deck-slot&quot;)'), 'la bascule ignore le conteneur passé');

	}

}
?>
