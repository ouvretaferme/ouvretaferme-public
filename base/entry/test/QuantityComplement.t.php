<?php
namespace entry;

class QuantityComplementTest extends \Test {

	private function read(string $text): array {

		$words = EntryLib::split($text);
		$value = NULL;

		QuantityComplementLib::read($words, $value, EntryLib::getTyping($text, $words), 'harvest');

		return [$value, array_values($words)];

	}

	public function testNumbers(): void {

		$this->assertEquals([12.0, ['courgette']], $this->read('courgette 12'));
		$this->assertEquals([12.5, ['courgette']], $this->read('courgette 12,5 '));
		$this->assertEquals([12.5, ['courgette']], $this->read('courgette 12.5 '));
		$this->assertEquals([12.0, ['kg']], $this->read('12kg'));
		$this->assertEquals([NULL, ['courgette']], $this->read('courgette'));

	}

	/**
	 * La récolte s'écrit au dixième : la lecture arrondit de même, pour que la confirmation dise ce qui
	 * sera écrit.
	 */
	public function testTenth(): void {

		$this->assertEquals([12.2, ['courgette']], $this->read('courgette 12,24 '));
		$this->assertEquals([12.3, ['courgette']], $this->read('courgette 12,25 '));
		$this->assertEquals([0.0, ['courgette']], $this->read('courgette 0,04 '));

	}

	/**
	 * Un zéro en cours de frappe est le début d'une décimale : il est pris, sans valoir de quantité.
	 * Fini, il vaut zéro, que le moteur refuse.
	 */
	public function testZeroTyping(): void {

		$this->assertEquals([NULL, ['courgette']], $this->read('courgette 0'));
		$this->assertEquals([NULL, ['courgette']], $this->read('courgette 0,'));
		$this->assertEquals([NULL, ['courgette']], $this->read('courgette 0,0'));
		$this->assertEquals([0.5, ['courgette']], $this->read('courgette 0,5'));
		$this->assertEquals([0.0, ['courgette']], $this->read('courgette 0 '));
		$this->assertEquals([NULL, ['courgette', 'kg']], $this->read('courgette 0 kg'));
		$this->assertEquals([0.0, ['courgette', 'kg']], $this->read('courgette 0 kg '));
		$this->assertEquals([12.0, ['courgette', 'kg']], $this->read('courgette 12, kg'));
		$this->assertEquals([NULL, ['courgette', 'kg']], $this->read('courgette 0, kg'));

	}

	/**
	 * La première quantité seulement : la seconde reste un mot en attente, que le moteur refuse.
	 */
	public function testFirstOnly(): void {

		$this->assertEquals([3.0, ['4']], $this->read('3 4 '));

	}

	/**
	 * Un signe moins retire : la valeur est négative. Seul en cours de frappe, il est pris sans valoir de quantité,
	 * et fini, il reste un mot en attente, que le moteur refuse.
	 */
	public function testNegative(): void {

		$this->assertEquals([-12.0, ['courgette']], $this->read('courgette -12 '));
		$this->assertEquals([-12.5, []], $this->read('-12,5'));
		$this->assertEquals([-12.0, ['kg']], $this->read('-12kg'));
		$this->assertEquals([NULL, []], $this->read('-'));
		$this->assertEquals([NULL, ['-']], $this->read('- '));
		$this->assertEquals([NULL, []], $this->read('-0'));
		$this->assertEquals([-0.5, []], $this->read('-0,5'));

	}

	/**
	 * Une virgule qui attend sa décimale n'est admise que sur le mot en cours de frappe.
	 */
	public function testDanglingComma(): void {

		$this->assertEquals([12.0, []], $this->read('12,'));
		$this->assertEquals([NULL, ['12,']], $this->read('12, '));

	}

}
?>
