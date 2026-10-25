<?php
namespace entry;

/**
 * Les unités en puces sous la ligne qui écrit une récolte (V45) : celles où la phrase peut s'écrire,
 * l'unité qui s'écrira marquée, et chaque puce pose un mot que la lecture relit comme s'il était tapé
 * (`HarvestIntentionLib::getUnitChoices()`, `UnitComplementLib::getWord()`).
 */
class HarvestUnitChipTest extends EntryFixtureTest {

	/**
	 * Le poids d'une caisse de l'espèce du décor.
	 */
	private function setCrateWeight(?float $crateWeight): void {

		$ePlant = $this->getPlant();
		$ePlant['crateWeight'] = $crateWeight;

		// Sans `build()` : il lit le poids posté par le panneau et le refuse à qui ne peut pas écrire l'espèce,
		// alors que le décor fixe lui-même le poids, absent compris, que seule une écriture directe remet à NULL.
		\plant\PlantLib::update($ePlant, ['crateWeight']);

	}

	/**
	 * Les unités proposées, par leur valeur, celle qui s'écrira suivie d'une étoile.
	 */
	private function getUnits(array $reading): array {
		return array_map(fn(array $unit) => $unit['unit'].($unit['selected'] ? '*' : ''), $reading['units']);
	}

	/**
	 * Les trois unités d'une intervention de récolte, dans l'ordre de l'énumération, l'unité retenue marquée,
	 * qu'elle soit dite ou non.
	 */
	public function testUnits(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('12 '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([\series\Task::KG.'*', \series\Task::UNIT, \series\Task::BUNCH], $this->getUnits($reading), 'sans unité dite, celle de la culture');

		$reading = $this->read($this->getText('12 bottes '));

		$this->assertEquals([\series\Task::KG, \series\Task::UNIT, \series\Task::BUNCH.'*'], $this->getUnits($reading), 'les bottes dites');

	}

	/**
	 * Les caisses, au poids de caisse de l'espèce et sur une récolte en kilos : aux seules conditions où la
	 * lecture les écrit. L'unité de la semaine décide, pas l'unité dite : des bottes dites sur une culture en
	 * kilos offrent les caisses, des kilos dits sur une culture en bottes ne les offrent pas.
	 */
	public function testCrate(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->assertEquals([\series\Task::KG.'*', \series\Task::UNIT, \series\Task::BUNCH], $this->getUnits($this->read($this->getText('12 '))), 'sans poids de caisse');

		$this->setCrateWeight(8.0);

		$this->assertEquals([\series\Task::KG.'*', UnitComplementLib::CRATE, \series\Task::UNIT, \series\Task::BUNCH], $this->getUnits($this->read($this->getText('12 '))));
		$this->assertEquals([\series\Task::KG, UnitComplementLib::CRATE, \series\Task::UNIT, \series\Task::BUNCH.'*'], $this->getUnits($this->read($this->getText('12 bottes '))));
		$this->assertEquals([\series\Task::KG, UnitComplementLib::CRATE.'*', \series\Task::UNIT, \series\Task::BUNCH], $this->getUnits($this->read($this->getText('3 caisses '))));

		// Sans `build()` : l'unité principale se choisit dans le formulaire de la culture, que le décor de `series`
		// ne poste pas.
		$eCultivation['mainUnit'] = \series\Cultivation::BUNCH;

		\series\Cultivation::model()
			->select(['mainUnit'])
			->update($eCultivation);

		$this->assertEquals([\series\Task::KG, \series\Task::UNIT, \series\Task::BUNCH.'*'], $this->getUnits($this->read($this->getText('12 '))), 'culture en bottes');
		$this->assertEquals([\series\Task::KG.'*', \series\Task::UNIT, \series\Task::BUNCH], $this->getUnits($this->read($this->getText('12 kg '))), 'kilos dits sur une culture en bottes');

	}

	/**
	 * Une quantité décimale ne se compte ni en pièces, ni en bottes, ni en caisses : il ne reste que les kilos, et
	 * une puce seule ne propose rien.
	 */
	public function testDecimal(): void {

		$this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		$reading = $this->read($this->getText('2,5 '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([], $this->getUnits($reading));

	}

	/**
	 * Un retrait corrige l'intervention où la récolte est écrite, ni le temps seul ni lui n'ont d'unité à proposer.
	 */
	public function testWithoutQuantity(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('5 kg '));
		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read($this->getText('-2 kg '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([], $this->getUnits($reading), 'retrait');

		$reading = $this->read($this->getText('30 min '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([], $this->getUnits($reading), 'temps seul');

	}

	/**
	 * Une puce remplace les mots qui disaient l'unité, que la lecture garde (`complementsNamed`), début de mot
	 * compris, et le mot qu'elle pose se relit dans son unité : choisir une puce revient à taper son mot.
	 */
	public function testPick(): void {

		$this->createCultivationInPhase(TRUE);
		$this->setCrateWeight(8.0);

		$this->assertEquals(['bottes'], $this->read($this->getText('12 bottes '))['complementsNamed']['unit']);
		$this->assertEquals(['b'], $this->read($this->getText('12 b'))['complementsNamed']['unit'], 'début de mot');
		$this->assertEquals([], $this->read($this->getText('12 '))['complementsNamed']['unit'], 'sans unité dite');

		foreach($this->read($this->getText('12 '))['units'] as $unit) {

			$reading = $this->read($unit['word'].' '.$this->getText('12 '));

			$this->assertEquals(EntryLib::WRITE, $reading['outcome'], $unit['unit']);
			$this->assertEquals($unit['unit'], $reading['complements']['unit'], $unit['unit']);

		}

	}

	/**
	 * Les puces suivent la ligne qui écrit, une par unité, et la valeur de chacune est le mot que la phrase relit.
	 */
	public function testResults(): void {

		$this->createCultivationInPhase(TRUE);

		$cElement = new \Collection();
		$reading = EntryLib::read($this->eStructure, $this->getText('12 bottes '), cElement: $cElement);

		$results = EntryUi::getResults($this->eStructure, $reading, EntryLib::findObjects($cElement, $reading['pending']), EntryLib::findIntentions($this->eStructure, $reading['pending']));

		$this->assertEquals('write', $results[0]['value']);

		$units = array_values(array_filter($results, fn(array $result) => str_starts_with($result['value'], 'unit:')));

		$this->assertEquals(['unit:kg', 'unit:piece', 'unit:botte'], array_column($units, 'value'));
		$this->assertEquals([FALSE, FALSE, TRUE], array_column($units, 'selected'));
		$this->assertEquals($results[array_key_last($results)], $units[array_key_last($units)], 'les unités ferment la liste');

	}

}
?>
