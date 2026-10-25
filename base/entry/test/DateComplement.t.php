<?php
namespace entry;

/**
 * La grammaire des dates, sans base, l'horloge figée un vendredi.
 */
class DateComplementTest extends \Test {

	private function read(string $text): array {

		$words = EntryLib::split($text);
		$value = NULL;

		DateComplementLib::read($words, $value, EntryLib::getTyping($text, $words), 'harvest');

		return [$value, array_values($words)];

	}

	private function assertDate(?string $expected, string $text): void {
		$this->assertEquals($expected, $this->read($text)[0], '« '.$text.' »');
	}

	public function testDays(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertDate('2026-10-16', 'aujourd\'hui ');
		$this->assertDate('2026-10-15', 'Hier ');
		$this->assertDate('2026-10-14', 'avant-hier ');
		$this->assertDate('2026-10-14', 'avant hier ');
		$this->assertDate('2026-10-17', 'demain ');

	}

	/**
	 * Un jour de semaine nu est le dernier de ce nom strictement passé : dit le jour même, c'est celui
	 * de la semaine d'avant.
	 */
	public function testWeekdays(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertDate('2026-10-15', 'jeudi ');
		$this->assertDate('2026-10-12', 'lundi ');
		$this->assertDate('2026-10-10', 'samedi ');
		$this->assertDate('2026-10-09', 'vendredi ');
		$this->assertDate('2026-10-12', 'lundi dernier ');
		$this->assertDate('2026-10-19', 'lundi prochain ');
		$this->assertDate('2026-10-23', 'vendredi prochain ');

	}

	/**
	 * Une date sans année est la dernière qui n'est pas à venir.
	 */
	public function testNumbers(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertDate('2026-10-12', 'le 12 ');
		$this->assertDate('2026-10-16', 'le 16 ');
		$this->assertDate('2026-09-20', 'le 20 ');
		$this->assertDate('2026-10-01', 'le 1er ');
		$this->assertDate('2026-10-01', 'le 1er octobre ');
		$this->assertDate('2026-10-12', 'le 12 octobre ');
		$this->assertDate('2026-10-12', '12 octobre ');
		$this->assertDate('2025-10-20', '20 octobre ');
		$this->assertDate('2026-08-15', 'le 15 août ');
		$this->assertDate('2026-10-12', '12/10 ');
		$this->assertDate('2026-01-05', '5/1 ');

	}

	public function testInvalid(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertDate(DateComplementLib::INVALID, 'le 31 ');
		$this->assertDate(DateComplementLib::INVALID, '31/02 ');
		$this->assertDate(DateComplementLib::INVALID, '12/13 ');
		$this->assertDate(DateComplementLib::INVALID, 'le 29 février ');

	}

	public function testNewYear(): void {

		$this->setClock('2027-01-03 10:00:00');

		$this->assertDate('2026-12-28', 'le 28 décembre ');
		$this->assertDate('2026-12-28', 'le 28 ');
		$this->assertDate('2026-12-31', 'jeudi ');

	}

	/**
	 * Un début de mot en cours de frappe vaut le jour qu'il commence quand il n'en commence qu'un ;
	 * sinon la date se tape encore. Une fois la phrase finie, il n'est plus qu'un mot inconnu.
	 */
	public function testTyping(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertDate('2026-10-15', 'hie');
		$this->assertDate('2026-10-09', 'ven');
		$this->assertDate('2026-10-13', 'mar');
		$this->assertDate('2026-10-14', 'avant h');
		$this->assertDate('2026-10-12', 'le 12 o');
		$this->assertDate('2026-10-12', 'lundi d');
		$this->assertDate('2026-10-19', 'lundi p');
		$this->assertDate(DateComplementLib::INCOMPLETE, 'm');
		$this->assertDate(DateComplementLib::INCOMPLETE, 'le');
		$this->assertDate(DateComplementLib::INCOMPLETE, 'le ');
		$this->assertDate(DateComplementLib::INCOMPLETE, 'le 12 ma');
		$this->assertDate(DateComplementLib::INCOMPLETE, '12 j');
		$this->assertDate(DateComplementLib::INCOMPLETE, '12/');

		$this->assertEquals([NULL, ['hie']], $this->read('hie '));

	}

	/**
	 * Le complément ne réclame que ses mots : un nombre sans mois reste à la quantité, « mai » sans
	 * nombre reste au nom de l'espèce.
	 */
	public function testWords(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertEquals(['2026-10-15', ['courgette', '12', 'kg']], $this->read('courgette 12 kg hier '));
		$this->assertEquals(['2026-10-12', ['courgette', '5', 'kg']], $this->read('courgette le 12 5 kg '));
		$this->assertEquals([NULL, ['courgette', '12', 'kg']], $this->read('courgette 12 kg'));
		$this->assertEquals([NULL, ['baie', 'de', 'mai', '3']], $this->read('baie de mai 3 '));
		$this->assertEquals([NULL, ['le', 'champ']], $this->read('le champ '));

	}

	/**
	 * Le jeton de date posé par l'écran vient en tête de phrase : ce qu'on tape ensuite le remplace.
	 */
	public function testLastWins(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertEquals(['2026-10-15', ['courgette', '12', 'kg']], $this->read('le 9 octobre courgette 12 kg hier '));

		// Une récolte n'a pas de récapitulatif : une date qui se tape encore efface celui du jeton.
		$this->assertEquals([DateComplementLib::INCOMPLETE, ['courgette', '12', 'kg']], $this->read('hier courgette 12 kg le '));
		$this->assertEquals(['2026-10-13', ['courgette']], $this->read('hier courgette le 13 '));

		// La valeur d'une intention qui retient le jour du jeton (`IntentionLib::hasRecap()`).
		$reading = EntryLib::getEmptyReading();

		DateComplementLib::apply($reading, [DateComplementLib::INCOMPLETE, '2026-10-15']);
		$this->assertEquals('2026-10-15', $reading['date']);
		$this->assertFalse(DateComplementLib::isComplete([DateComplementLib::INCOMPLETE, '2026-10-15']));
		$this->assertFalse(DateComplementLib::isDate([DateComplementLib::INCOMPLETE, '2026-10-15']));

	}

	public function testApply(): void {

		$this->setClock('2026-10-16 10:00:00');

		$reading = EntryLib::getEmptyReading();

		DateComplementLib::apply($reading, '2026-10-15');
		$this->assertEquals('2026-10-15', $reading['date']);

		$reading = EntryLib::getEmptyReading();

		DateComplementLib::apply($reading, DateComplementLib::INCOMPLETE);
		$this->assertEquals('2026-10-16', $reading['date']);
		$this->assertEquals(NULL, $reading['refusal']);
		$this->assertFalse(DateComplementLib::isComplete(DateComplementLib::INCOMPLETE));

		DateComplementLib::apply($reading, DateComplementLib::INVALID);
		$this->assertEquals(['reason' => 'date'], $reading['refusal']);

	}

	/**
	 * Le libellé du jeton gardé d'une saisie à l'autre se relit comme le jour qu'il nomme.
	 */
	public function testToken(): void {

		$this->setClock('2026-10-16 10:00:00');

		foreach(['2026-10-15', '2026-10-01', '2026-09-20'] as $date) {
			$this->assertDate($date, DateComplementUi::getToken($date).' ');
		}

		$this->setClock('2027-01-03 10:00:00');

		$this->assertDate('2026-12-28', DateComplementUi::getToken('2026-12-28').' ');

	}

}
?>
