<?php
namespace main;

/**
 * La version texte d'un e-mail ne laisse passer aucune entité HTML.
 *
 * `MailStructureUi::toText()` retire les balises d'un contenu rédigé en HTML, qui porte des entités que
 * le texte brut n'interprète pas : l'espace insécable d'un montant ou d'une durée, une lettre
 * accentuée nommée, une apostrophe encodée par `encode()`.
 *
 * Aucune écriture en base.
 *
 * Lancement : dev/test package=main file=MailTextEntitiesAi
 */
class MailTextEntitiesAiTest extends \Test {

	public function testNamedEntities() {

		$this->assertEquals("Durée : 3\u{a0}h", MailStructureUi::toText('Dur&eacute;e : 3&nbsp;h'), 'les entités nommées');
		$this->assertEquals("12\u{202f}€", MailStructureUi::toText('12&#8239;&euro;'), 'une entité numérique et une entité nommée');

	}

	public function testEncodedContent() {

		$this->assertEquals('L\'offre "A & B" <ici>', MailStructureUi::toText(encode('L\'offre "A & B" <ici>')), 'ce que encode() a écrit se relit tel quel');

	}

	public function testTagsAndBreaks() {

		$this->assertEquals("Bonjour\nà demain\n", MailStructureUi::toText('<p><b>Bonjour</b><br/>&agrave; demain</p>'), 'les balises partent, les sauts de ligne restent');

	}

	public function testItemsLines() {

		$this->assertEquals(["Durée : 3\u{a0}h"], MailStructureUi::getItemsLines([['Durée', '3&nbsp;h']]), 'un fait rendu en ligne de texte');

	}

}
?>
