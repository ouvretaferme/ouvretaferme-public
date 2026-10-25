<?php
namespace entry;

/**
 * La grammaire du champ, sans base : le découpage, le mot en cours de frappe, l'ordre du registre
 * et les intentions proposées par un début de mot.
 */
class GrammarTest extends \Test {

	public function testSplit(): void {

		$this->assertEquals(['recolte', 'courgette', '12', 'kg'], EntryLib::split('Récolte  Courgette 12kg'));
		$this->assertEquals(['recolte', 'd\'', 'ail', '3', 'bottes'], EntryLib::split('récolte d’ail 3 bottes'));
		$this->assertEquals(['pomme', 'de', 'terre', '12,5'], EntryLib::split('Pomme de terre 12,5'));
		$this->assertEquals([], EntryLib::split('   '));

	}

	public function testTyping(): void {

		$this->assertEquals(2, EntryLib::getTyping('récolte courgette 12', EntryLib::split('récolte courgette 12')));
		$this->assertEquals(NULL, EntryLib::getTyping('récolte courgette 12 ', EntryLib::split('récolte courgette 12 ')));
		$this->assertEquals(NULL, EntryLib::getTyping('', []));

	}

	/**
	 * La saison passe avant la quantité, qui passe avant l'unité : « 12 kg » se lit dans cet ordre quel
	 * que soit celui de la phrase, et un complément ne réclame que ses mots.
	 */
	public function testComplementsOrder(): void {

		$this->assertEquals(['duration', 'date', 'season', 'quantity', 'unit'], array_keys(EntryLib::getComplements('harvest')));

		$words = EntryLib::split('kg 12 courgette');
		$quantity = NULL;
		$unit = NULL;

		QuantityComplementLib::read($words, $quantity, NULL, 'harvest');
		UnitComplementLib::read($words, $unit, NULL, 'harvest');

		$this->assertEquals(12.0, $quantity);
		$this->assertEquals(\series\Task::KG, $unit);
		$this->assertEquals([2 => 'courgette'], $words);

	}

	/**
	 * La durée passe avant la quantité : « 30 min » n'est pas une quantité de 30, quel que soit l'ordre
	 * de la phrase, et la quantité garde le nombre nu.
	 */
	public function testDurationBeforeQuantity(): void {

		foreach(['courgette 12 kg 30 min', 'courgette 30 min 12 kg', '30min courgette 12kg'] as $text) {

			$words = EntryLib::split($text);
			$duration = NULL;
			$quantity = NULL;
			$unit = NULL;

			DurationComplementLib::read($words, $duration, NULL, 'harvest');
			QuantityComplementLib::read($words, $quantity, NULL, 'harvest');
			UnitComplementLib::read($words, $unit, NULL, 'harvest');

			$this->assertEquals([30, 12.0, \series\Task::KG, ['courgette']], [$duration, $quantity, $unit, array_values($words)], $text);

		}

	}

	public function testVocabulary(): void {

		$this->assertEquals(['unit' => [\series\Task::KG, \series\Task::UNIT, \series\Task::BUNCH, UnitComplementLib::CRATE]], EntryLib::getVocabulary('harvest'));

	}

	public function testOneQuestion(): void {

		$reading = EntryLib::getEmptyReading();

		EntryLib::ask($reading, ['name' => 'cultivation']);

		$this->assertEquals(EntryLib::ASK, $reading['outcome']);

		try {
			EntryLib::ask($reading, ['name' => 'other']);
			$this->assertFail('une seconde question lève');
		} catch(\Exception) {
			$this->assertEquals('cultivation', $reading['question']['name']);
		}

	}

}
?>
