<?php
namespace entry;

/**
 * La semaine dite à une intervention à faire (HN-B16) : cette semaine, la prochaine, ou la prochaine du numéro
 * tapé, celle d'aujourd'hui comprise. L'horloge est figée un samedi de la semaine 43 de 2026, une année de
 * 53 semaines.
 */
class WeekComplementTest extends \Test {

	/**
	 * La semaine, puis la durée lue dans les mots qu'elle laisse, dans l'ordre du registre
	 * (`EntrySetting::COMPLEMENTS`).
	 */
	private function read(string $text): array {

		$words = EntryLib::split($text);
		$week = NULL;
		$duration = NULL;

		$typing = EntryLib::getTyping($text, $words);

		WeekComplementLib::read($words, $week, $typing, 'intervention');
		DurationComplementLib::read($words, $duration, $typing, 'intervention');

		return [$week, $duration, array_values($words)];

	}

	public function testWeek(): void {

		$this->setClock('2026-10-24 10:00:00');

		$this->assertEquals(['2026-W43', NULL, []], $this->read('cette semaine '));
		$this->assertEquals(['2026-W44', NULL, []], $this->read('semaine prochaine '));
		$this->assertEquals(['2026-W46', 60, []], $this->read('semaine 46 1 h '));
		$this->assertEquals(['2026-W46', 60, []], $this->read('1 h semaine 46 '));

	}

	/**
	 * Un numéro déjà passé dans l'année est celui de l'année suivante, celui d'aujourd'hui reste cette semaine.
	 */
	public function testNumbered(): void {

		$this->setClock('2026-10-24 10:00:00');

		$this->assertEquals('2026-W43', $this->read('semaine 43 ')[0]);
		$this->assertEquals('2027-W42', $this->read('semaine 42 ')[0]);
		$this->assertEquals('2027-W02', $this->read('semaine 2 ')[0]);
		$this->assertEquals('2026-W53', $this->read('semaine 53 ')[0], 'une année de 53 semaines');

		// Le 1er janvier 2027 est encore dans la semaine 53 de 2026 : l'année est celle des semaines.
		$this->setClock('2027-01-01 10:00:00');

		$this->assertEquals('2026-W53', $this->read('semaine 53 ')[0], 'la semaine d\'aujourd\'hui, au passage de l\'année');
		$this->assertEquals('2027-W01', $this->read('semaine 1 ')[0]);

	}

	/**
	 * Un numéro que l'année n'a pas est refusé, et le mot seul attend son numéro : la durée ne prend pas le
	 * numéro en cours de frappe.
	 */
	public function testIncomplete(): void {

		$this->setClock('2026-10-24 10:00:00');

		$this->assertEquals(WeekComplementLib::INVALID, $this->read('semaine 54 ')[0]);
		$this->assertEquals(WeekComplementLib::INVALID, $this->read('semaine 0 ')[0]);
		$this->assertEquals([WeekComplementLib::INCOMPLETE, NULL, []], $this->read('semaine '));
		$this->assertEquals(['2026-W44', NULL, []], $this->read('semaine 44'), 'en cours de frappe, le numéro est déjà la semaine');

		$this->assertFalse(WeekComplementLib::isComplete(WeekComplementLib::INCOMPLETE));
		$this->assertFalse(WeekComplementLib::isWeek(WeekComplementLib::INVALID));

	}

}
?>
