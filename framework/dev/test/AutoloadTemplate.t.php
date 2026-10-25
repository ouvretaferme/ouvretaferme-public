<?php
namespace dev;

/**
 * Le chargement d'une classe à espace de noms dont le nom finit par `Template`.
 *
 * Un gabarit de package se lit dans `view/<Xxx>.php`, mais le suffixe ne suffit pas à le reconnaître :
 * `website\Template` est un élément ORM, et le chercher seulement comme gabarit le rendrait introuvable.
 */
class AutoloadTemplateTest extends \Test {

	public function testElementNamedTemplateStillLoads(): void {

		$this->assertTrue(class_exists('website\Template'), 'un élément nommé Template se charge');
		$this->assertTrue(is_subclass_of('website\Template', \Element::class), 'et c\'est bien l\'élément');

	}

	public function testUnknownTemplateIsNotFound(): void {

		$this->assertFalse(class_exists('dev\AutoloadUnknownTemplate'), 'un gabarit absent ne se trouve ni dans view/ ni comme élément');

	}

}
?>
