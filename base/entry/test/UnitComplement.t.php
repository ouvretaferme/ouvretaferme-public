<?php
namespace entry;

class UnitComplementTest extends \Test {

	private function read(string $text): array {

		$words = EntryLib::split($text);
		$value = NULL;

		UnitComplementLib::read($words, $value, EntryLib::getTyping($text, $words), 'harvest');

		return [$value, array_values($words)];

	}

	public function testWords(): void {

		$this->assertEquals([\series\Task::KG, ['12']], $this->read('12 kg '));
		$this->assertEquals([\series\Task::KG, ['12']], $this->read('12 Kilos '));
		$this->assertEquals([\series\Task::UNIT, ['12']], $this->read('12 pièces '));
		$this->assertEquals([\series\Task::BUNCH, ['3']], $this->read('3 bottes '));
		$this->assertEquals([UnitComplementLib::CRATE, ['3']], $this->read('3 caisses '));
		$this->assertEquals([UnitComplementLib::CRATE, ['1']], $this->read('1 caisse '));
		$this->assertEquals([UnitComplementLib::CRATE, ['3']], $this->read('3 cs '));
		$this->assertEquals([NULL, ['3', 'sacs']], $this->read('3 sacs '));

	}

	/**
	 * Un début de nom d'unité en cours de frappe est réclamé ; il ne pose l'unité que s'il n'en
	 * désigne qu'une (aucun début n'en désigne deux avec les mots d'aujourd'hui). Une fois la frappe finie, il n'est plus qu'un mot inconnu.
	 */
	public function testTyping(): void {

		$this->assertEquals([\series\Task::KG, ['12']], $this->read('12 k'));
		$this->assertEquals([\series\Task::BUNCH, ['12']], $this->read('12 b'));
		$this->assertEquals([\series\Task::UNIT, ['12']], $this->read('12 u'));
		$this->assertEquals([UnitComplementLib::CRATE, ['12']], $this->read('12 c'));
		$this->assertEquals([NULL, ['12', 'k']], $this->read('12 k '));

	}

}
?>
