<?php
namespace util;

class FormGroupTest extends \Test {

	public function testClassWrittenOnce() {

		$html = new FormUi()->group('Étape', '<input name="step"/>', ['data-wrapper' => 'step', 'class' => 'hide']);

		$this->assertTrue(preg_match('/^<div [^>]*>/', $html, $match) === 1);
		$this->assertEquals(1, substr_count($match[0], 'class='), 'la classe passe par l\'attribut : '.$match[0]);
		$this->assertEquals(1, substr_count($match[0], 'hide'), 'la classe ne paraît qu\'une fois dans la balise : '.$match[0]);
		$this->assertEquals(1, substr_count($match[0], 'form-group'), 'form-group ne paraît qu\'une fois dans la balise : '.$match[0]);

	}

	public function testForStaysOnLabel() {

		$html = new FormUi()->group('Période', '<input name="opensAt"/>', ['data-wrapper' => 'opensAt', 'for' => FALSE]);

		$this->assertTrue(preg_match('/^<div [^>]*>/', $html, $match) === 1);
		$this->assertFalse(str_contains($match[0], 'for='), 'for gouverne le label et ne s\'écrit pas sur la div : '.$match[0]);
		$this->assertFalse(str_contains($html, 'for='), 'le label reste sans for : '.$html);

	}

	public function testNested() {

		$html = new FormUi()->group('Étape', '<input name="step"/>', ['data-wrapper' => 'step'], nested: TRUE);

		$this->assertTrue(preg_match('/^<div [^>]*>/', $html, $match) === 1);
		$this->assertEquals(1, substr_count($match[0], 'class='), 'la classe ne paraît qu\'une fois dans la balise : '.$match[0]);
		$this->assertTrue($html !== new FormUi()->group('Étape', '<input name="step"/>', ['data-wrapper' => 'step']), 'un groupe imbriqué se distingue d\'un groupe simple');

	}

}
?>
