<?php
namespace entry;

class DurationComplementTest extends \Test {

	private function read(string $text, string $intention = 'harvest'): array {

		$words = EntryLib::split($text);
		$value = NULL;

		DurationComplementLib::read($words, $value, EntryLib::getTyping($text, $words), $intention);

		return [$value, array_values($words)];

	}

	public function testForms(): void {

		$this->assertEquals([30, ['courgette', '12']], $this->read('courgette 12 30 min '));
		$this->assertEquals([30, ['courgette']], $this->read('courgette 30min '));
		$this->assertEquals([45, ['courgette']], $this->read('courgette 45 minutes '));
		$this->assertEquals([10, ['courgette']], $this->read('courgette 10 mn '));
		$this->assertEquals([60, ['courgette']], $this->read('courgette 1 h '));
		$this->assertEquals([120, ['courgette']], $this->read('courgette 2 heures '));
		$this->assertEquals([90, ['courgette']], $this->read('courgette 1h30 '));
		$this->assertEquals([90, ['courgette']], $this->read('courgette 1h30min '));
		$this->assertEquals([90, ['courgette']], $this->read('courgette 1 h 30 '));
		$this->assertEquals([90, ['courgette']], $this->read('courgette 1 h 30 min '));
		$this->assertEquals([90, ['courgette']], $this->read('courgette 1,5 h '));
		$this->assertEquals([90, ['courgette']], $this->read('courgette 90 min '));

	}

	/**
	 * Un nombre nu reste à la quantité, et des minutes suivies d'une unité de récolte aussi :
	 * « 1 h 30 kg » dit une heure et 30 kilos.
	 */
	public function testQuantityKept(): void {

		$this->assertEquals([NULL, ['courgette', '12']], $this->read('courgette 12 '));
		$this->assertEquals([NULL, ['courgette', '12', 'kg']], $this->read('courgette 12 kg '));
		$this->assertEquals([60, ['courgette', '30', 'kg']], $this->read('courgette 1 h 30 kg '));

	}

	/**
	 * En cours de frappe, un début de mot de durée suffit, et un nombre tapé après la quantité attend
	 * son mot sans être refusé. La frappe finie, il redevient un mot que personne ne lit.
	 */
	public function testTyping(): void {

		$this->assertEquals([30, ['courgette', '12', 'kg']], $this->read('courgette 12 kg 30 mi'));
		$this->assertEquals([NULL, ['courgette', '12', 'kg', '30', 'm']], $this->read('courgette 12 kg 30 m'), 'un « m » seul attend sa deuxième lettre');
		$this->assertEquals([60, ['courgette', '12', 'kg']], $this->read('courgette 12 kg 1 he'));
		$this->assertEquals([63, ['courgette']], $this->read('courgette 1h3'));
		$this->assertEquals([DurationComplementLib::TYPING, ['courgette', '12', 'kg']], $this->read('courgette 12 kg 30'));
		$this->assertEquals([NULL, ['courgette', '12']], $this->read('courgette 12'));
		$this->assertEquals([NULL, ['courgette', '12', 'kg', '30']], $this->read('courgette 12 kg 30 '));
		$this->assertEquals([NULL, ['courgette', '12', 'kg', '30', 'm']], $this->read('courgette 12 kg 30 m '));

		$this->assertFalse(DurationComplementLib::isComplete(DurationComplementLib::TYPING));
		$this->assertTrue(DurationComplementLib::isComplete(30));
		$this->assertTrue(DurationComplementLib::isComplete(NULL));

	}

	/**
	 * Une durée nulle, de plus d'une journée, ou des minutes de 60 et plus après des heures : la phrase
	 * est refusée, rien ne s'arrondit à la place de l'utilisateur.
	 */
	public function testRefused(): void {

		$this->assertEquals([DurationComplementLib::OVERFLOW, ['courgette']], $this->read('courgette 1h75 '));
		$this->assertEquals([DurationComplementLib::OVERFLOW, ['courgette']], $this->read('courgette 1,5 h 30 '));

		foreach([
			[0, 'duration'],
			[DurationComplementLib::DAY + 1, 'duration'],
			[DurationComplementLib::OVERFLOW, 'minutes'],
		] as [$value, $reason]) {

			$reading = EntryLib::getEmptyReading();

			DurationComplementLib::apply($reading, $value);

			$this->assertEquals(['reason' => $reason], $reading['refusal'], (string)$value);

		}

		foreach([1, DurationComplementLib::DAY, NULL, DurationComplementLib::TYPING] as $value) {

			$reading = EntryLib::getEmptyReading();

			DurationComplementLib::apply($reading, $value);

			$this->assertEquals(NULL, $reading['refusal'], (string)$value);

		}

	}

	public function testLabels(): void {

		$this->assertEquals('30 min', DurationComplementUi::getDuration(30));
		$this->assertEquals('1 h', DurationComplementUi::getDuration(60));
		$this->assertEquals('1 h 05', DurationComplementUi::getDuration(65));

		// Chaque durée qu'une puce donne se relit en elle-même : son jeton est son libellé.
		foreach(DurationComplementLib::ADDITIONS as $minutes) {
			$this->assertEquals([$minutes, ['courgette']], $this->read('courgette '.DurationComplementUi::getDuration($minutes).' '));
		}

	}

}
?>
