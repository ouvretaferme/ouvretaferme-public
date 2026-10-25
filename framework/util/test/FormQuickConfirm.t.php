<?php
namespace util;

class ProbeQuickConfirm extends \user\User {
}

class ProbeQuickConfirmUi {

	public static function p(string $property): \PropertyDescriber {

		$d = \user\User::model()->describer($property, [
			'firstName' => s("Prénom"),
			'lastName' => s("Nom"),
			'email' => s("Adresse e-mail"),
		]);

		switch($property) {

			case 'lastName' :
				$d->quickConfirm = s("Changer le nom le modifie aussi sur les documents déjà envoyés. Continuer ?");
				break;

			case 'email' :
				$d->quickConfirm = fn(\user\User $e) => ($e['shared'] > 0) ? s("Cette adresse est partagée avec {value} structures. La changer ?", $e['shared']) : NULL;
				break;

		}

		return $d;

	}

}

class FormQuickConfirmTest extends \Test {

	public function testWithoutConfirm() {

		$html = new FormUi()->quick($this->getElement(), 'firstName');

		$this->assertEquals(1, substr_count($html, 'type="submit"'));
		$this->assertFalse(str_contains($html, 'data-confirm'));

	}

	public function testConfirmString() {

		$html = new FormUi()->quick($this->getElement(), 'lastName');

		$this->assertEquals(1, $this->countConfirm($html, 'Changer le nom le modifie aussi sur les documents déjà envoyés. Continuer ?'));

	}

	public function testConfirmClosure() {

		$html = new FormUi()->quick($this->getElement(shared: 3), 'email');

		$this->assertEquals(1, $this->countConfirm($html, 'Cette adresse est partagée avec 3 structures. La changer ?'));

	}

	public function testConfirmClosureNull() {

		$html = new FormUi()->quick($this->getElement(shared: 0), 'email');

		$this->assertFalse(str_contains($html, 'data-confirm'));

	}

	public function testConfirmOnSubmit() {

		$html = new FormUi()->quick($this->getElement(), 'lastName');

		$this->assertEquals(1, preg_match_all('/<button [^>]*data-confirm="[^"]+"[^>]*>/', $html, $matches));
		$this->assertTrue(str_contains($matches[0][0], 'type="submit"'), 'Le message est porté par le bouton d\'enregistrement : '.$matches[0][0]);

	}

	private function countConfirm(string $html, string $message): int {
		return substr_count($html, 'data-confirm="'.encode($message).'"');
	}

	private function getElement(int $shared = 0): ProbeQuickConfirm {

		return new ProbeQuickConfirm([
			'id' => 1,
			'firstName' => 'Paul',
			'lastName' => 'Appert',
			'email' => 'paul@example.org',
			'shared' => $shared,
		]);

	}

}
?>
