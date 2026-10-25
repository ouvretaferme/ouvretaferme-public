<?php
namespace util;

class FormHelperAiTest extends \Test {

	/**
	 * Le champ de fichier reste caché dans son bouton, même quand l'appelant lui passe une classe : un champ
	 * visible doublerait le bouton.
	 */
	public function testFileButtonKeepsTheFieldHidden() {

		$h = new FormUi()->fileButton('file', 'Joindre', ['class' => 'charter-file', 'accept' => 'application/pdf']);

		$this->assertEquals(1, preg_match('/<input[^>]*type="file"[^>]*>/', $h, $match), 'le bouton ne porte pas de champ de fichier');
		$this->assertEquals(1, preg_match('/class="[^"]*\bhide\b[^"]*"/', $match[0]), 'le champ de fichier n\'est plus caché');
		$this->assertEquals(1, preg_match('/class="[^"]*\bcharter-file\b[^"]*"/', $match[0]), 'la classe passée par l\'appelant est perdue');

	}

	/**
	 * L'adresse de la croix qui vide la recherche est échappée : elle porte souvent plusieurs paramètres.
	 */
	public function testSearchSubmitEscapesTheResetUrl() {

		$h = new FormUi()->searchSubmit('/liste?a=1&b=2', TRUE);

		$this->assertTrue(str_contains($h, 'href="/liste?a=1&amp;b=2"'), 'l\'adresse de la croix n\'est pas échappée');

	}

}
?>
