<?php
namespace util;

class FormClearableTest extends \Test {

	public function testTextWithoutPlaceholder() {

		$html = new FormUi()->text('name', attributes: ['id' => 'field', 'clearable' => TRUE]);

		$this->assertTrue(preg_match('/<input [^>]*name="name"[^>]*placeholder=" "\/><a [^>]*data-clear[ >]/', $html) === 1, 'la croix suit le champ, qui porte un placeholder vide : '.$html);

	}

	public function testTextWithPlaceholder() {

		$html = new FormUi()->text('name', attributes: ['id' => 'field', 'placeholder' => 'Tapez...', 'clearable' => TRUE]);

		$this->assertTrue(str_contains($html, 'placeholder="Tapez..."'));
		$this->assertFalse(str_contains($html, 'placeholder=" "'));

	}

	public function testTextWithAppend() {

		$html = new FormUi()->text('name', attributes: ['id' => 'field', 'append' => 'kg', 'clearable' => TRUE]);

		$this->assertTrue(preg_match('/<input [^>]+\/><a [^>]*data-clear[ >]/', $html) === 1, 'la croix suit le champ malgré l\'append : '.$html);

	}

	public function testTextNotClearable() {

		$html = new FormUi()->text('name', attributes: ['id' => 'field']);

		$this->assertFalse(str_contains($html, 'data-clear'));
		$this->assertFalse(str_contains($html, 'clearable'));

	}

	public function testSelect() {

		$html = new FormUi()->select('sold', [15 => '15 jours'], attributes: ['id' => 'field', 'clearable' => TRUE]);

		$this->assertTrue(preg_match('/<\/select><a [^>]*data-clear[ >]/', $html) === 1, 'la croix suit la liste : '.$html);
		$this->assertTrue(str_contains($html, '<option value=""'));
		$this->assertFalse(str_contains($html, 'clearable'));

	}

	public function testSelectRequired() {

		$this->assertSelectRefused(['required' => TRUE]);

	}

	public function testSelectMultiple() {

		$this->assertSelectRefused(['multiple' => TRUE]);

	}

	public function testTextareaRefused() {

		$this->assertFieldRefused('textarea', fn() => new FormUi()->textarea('comment', attributes: ['clearable' => TRUE]));

	}

	public function testHiddenRefused() {

		$this->assertFieldRefused('hidden', fn() => new FormUi()->hidden('sale', 1, ['clearable' => TRUE]));

	}

	public function testDateRefused() {

		$this->assertFieldRefused('date', fn() => new FormUi()->date('deliveredAt', attributes: ['clearable' => TRUE]));

	}

	public function testAutocompleteRefused() {

		$this->assertFieldRefused('autocomplete', fn() => new FormUi()->autocomplete('customer', '/selling/customer:query', [], NULL, [], FALSE, FALSE, ['clearable' => TRUE]));

	}

	private function assertFieldRefused(string $type, \Closure $render): void {

		try {
			$render();
			$this->assertFail('Champ '.$type.' accepté');
		} catch(\Exception $e) {
			$this->assertEquals('Field type '.$type.' cannot be clearable', $e->getMessage());
		}

	}

	private function assertSelectRefused(array $attributes): void {

		try {
			new FormUi()->select('sold', [15 => '15 jours'], attributes: $attributes + ['clearable' => TRUE]);
			$this->assertFail('Select '.implode(', ', array_keys($attributes)).' accepté');
		} catch(\Exception $e) {
			$this->assertEquals('A clearable select cannot be required or multiple', $e->getMessage());
		}

	}

}
?>
