<?php
namespace dev;

/**
 * Un collateur rendu par `\L::getCollator()` garde son réglage, quoi qu'on demande après lui.
 *
 * Un appelant prend le collateur, puis appelle du code qui en demande un autre avant de trier : le
 * planning prenait le sien avant une lecture des emplacements, triée en ordre naturel, et rangeait
 * alors « Laitue 10 » après « Laitue 7 ».
 *
 * Lancement : dev/test package=dev file=Collator
 */
class CollatorTest extends \Test {

	public function testCollatorKeepsItsOrderAfterANaturalCall(): void {

		$collator = \L::getCollator();

		\L::getCollator(TRUE);

		$this->assertEquals(['Laitue 10', 'Laitue 7'], $this->sort($collator, ['Laitue 7', 'Laitue 10']), 'un appel naturel intercalé a changé l\'ordre d\'un collateur déjà pris');

	}

	public function testNaturalCollatorKeepsItsOrderAfterACall(): void {

		$collator = \L::getCollator(TRUE);

		\L::getCollator();

		$this->assertEquals(['Laitue 7', 'Laitue 10'], $this->sort($collator, ['Laitue 10', 'Laitue 7']), 'un appel intercalé a changé l\'ordre d\'un collateur naturel déjà pris');

	}

	public function testCollatorIsKeptBySetting(): void {

		$this->assertTrue(\L::getCollator() === \L::getCollator(), 'le collateur se recrée à chaque appel');
		$this->assertTrue(\L::getCollator(TRUE) === \L::getCollator(TRUE), 'le collateur naturel se recrée à chaque appel');
		$this->assertTrue(\L::getCollator() !== \L::getCollator(TRUE), 'les deux réglages partagent un même collateur');

	}

	private function sort(\Collator $collator, array $names): array {

		usort($names, fn(string $name1, string $name2) => $collator->compare($name1, $name2));

		return $names;

	}

}
