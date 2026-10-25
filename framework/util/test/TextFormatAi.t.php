<?php
namespace util;

class TextFormatAiTest extends \Test {

	/**
	 * Un montant dont la partie coupée vaut exactement la moitié s'arrondit loin de zéro, comme round() et
	 * l'arrondi fiscal à l'euro. Les cas tombent tous sur un milieu, là où l'arrondi au pair rendrait autre chose.
	 */
	public function testMoneyRoundsHalfUp() {

		$this->assertEquals("1\u{202F}241\u{A0}€", TextUi::money(1240.5, precision: 0), 'à l\'euro, 1 240,50 € donne 1 241 €');
		$this->assertEquals("-1\u{202F}241\u{A0}€", TextUi::money(-1240.5, precision: 0), 'à l\'euro, un négatif s\'éloigne de zéro');
		$this->assertEquals("0,13\u{A0}€", TextUi::money(0.125, precision: 2), 'au centime, 0,125 donne 0,13 et non 0,12');
		$this->assertEquals("-0,13\u{A0}€", TextUi::money(-0.125, precision: 2), 'au centime, -0,125 donne -0,13');

	}

	/**
	 * Un pourcentage s'écrit avec le séparateur décimal de la langue, la plus petite valeur affichable comprise,
	 * sans décimale quand la valeur arrondie est entière, et ses milliers séparés comme ceux d'un montant.
	 */
	public function testPcWritesTheLanguageSeparator() {

		$this->assertEquals('<span style="white-space: nowrap">12,3 %</span>', TextUi::pc(12.34, 1), 'une décimale, à la virgule');
		$this->assertEquals('<span style="white-space: nowrap">50 %</span>', TextUi::pc(50.0, 1), 'une valeur entière ne prend pas de décimale');
		$this->assertEquals('<span style="white-space: nowrap">&lt; 0,1 %</span>', TextUi::pc(0.04, 1), 'la borne d\'une part qui s\'arrondit à zéro, à la virgule');
		$this->assertEquals('<span style="white-space: nowrap">&lt; 1 %</span>', TextUi::pc(0.2), 'la borne à la précision 0');
		$this->assertEquals('<span style="white-space: nowrap">38 %</span>', TextUi::pc(37.6), 'à la précision 0, l\'arrondi au plus proche');
		$this->assertEquals("<span style=\"white-space: nowrap\">1\u{202F}500 %</span>", TextUi::pc(1500), 'les milliers se séparent comme dans un montant');

	}

	/**
	 * Un texte saisi s'échappe avant que ses retours à la ligne deviennent des <br />, sans quoi ceux-ci seraient
	 * échappés avec lui, ou le balisage saisi passerait tel quel.
	 */
	public function testMultilineEscapesThenBreaks() {

		$this->assertEquals("&lt;b&gt;Livrer&lt;/b&gt;<br />\nSonner", TextUi::multiline("<b>Livrer</b>\nSonner"));

	}

	public function testNonBreaking() {

		$this->assertEquals("12,5\u{A0}kg\u{A0}en\u{A0}vrac", TextUi::nonBreaking('12,5 kg en vrac'));

	}

	/**
	 * Les attributs du bouton de copie sont échappés : un sélecteur ou un message qui porte un guillemet ne sort
	 * pas de son attribut, et doCopy() lit ce qu'on lui a donné.
	 */
	public function testCopyEscapesItsAttributes() {

		$h = TextUi::copy('[data-id="12"]', 'Copié "ici"', 'Copier', 'btn btn-xs btn-outline-primary', icon: FALSE);

		$this->assertEquals('<a onclick="doCopy(this)" data-selector="[data-id=&quot;12&quot;]" data-message="Copié &quot;ici&quot;" class="btn btn-xs btn-outline-primary">Copier</a>', $h);

	}

}
?>
