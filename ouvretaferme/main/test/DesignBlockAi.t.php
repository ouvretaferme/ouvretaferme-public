<?php
namespace main;

class DesignBlockAiTest extends \Test {

	/**
	 * L'adresse d'une tuile en lien est échappée : celle d'une liste filtrée porte plusieurs
	 * paramètres.
	 */
	public function testFigureEscapesItsUrl() {

		$h = DesignUi::getFigure('3', 'Aides', '/aid/admin/?verification=conflict&closed=1');

		$this->assertTrue(str_contains($h, 'href="/aid/admin/?verification=conflict&amp;closed=1"'), 'l\'adresse de la tuile n\'est pas échappée');

	}

	/**
	 * L'adresse de chaque valeur d'un jeton est échappée, pour la même raison.
	 */
	public function testChipDropdownEscapesItsUrls() {

		$h = DesignUi::getChipDropdown('Tout', [['/series?a=1&b=2', 'Une valeur', TRUE]]);

		$this->assertTrue(str_contains($h, 'href="/series?a=1&amp;b=2"'), 'l\'adresse d\'une valeur du jeton n\'est pas échappée');

	}

}
?>
