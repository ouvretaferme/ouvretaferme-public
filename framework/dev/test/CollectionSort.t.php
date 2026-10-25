<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=CollectionSort
 */
class CollectionSortTest extends \Test {

	/**
	 * Une propriété lue à travers deux éléments imbriqués, comme la date de livraison d'une ligne
	 * (`['distribution' => ['shopDate' => ['deliveryDate']]]`).
	 */
	public function testSortReadsAPropertyTwoElementsDown(): void {

		$cUser = $this->users(['2026-10-22', '2026-10-08', '2026-10-15']);

		$cUser->sort(['parent' => ['parent' => ['name']]]);

		$this->assertEquals(['2026-10-08', '2026-10-15', '2026-10-22'], $this->names($cUser), 'le tri ne descend pas à deux niveaux');

	}

	public function testSortReadsADirectionTwoElementsDown(): void {

		$cUser = $this->users(['2026-10-08', '2026-10-22', '2026-10-15']);

		$cUser->sort(['parent' => ['parent' => ['name' => SORT_DESC]]]);

		$this->assertEquals(['2026-10-22', '2026-10-15', '2026-10-08'], $this->names($cUser), 'le tri descendant ne descend pas à deux niveaux');

	}

	/**
	 * Témoins : un seul niveau, sous ses deux écritures, ne change pas.
	 */
	public function testSortOneElementDownIsUnchanged(): void {

		$cUser = new \Collection();

		foreach(['Chloé', 'Alice', 'Bruno'] as $id => $name) {
			$cUser[$id] = new \user\User(['id' => $id, 'parent' => new \user\User(['name' => $name])]);
		}

		$cUser->sort(['parent' => ['name']]);
		$this->assertEquals(['Alice', 'Bruno', 'Chloé'], array_values($cUser->makeArray(fn($eUser) => $eUser['parent']['name'])), 'le tri à un niveau a changé');

		$cUser->sort(['parent' => ['name' => SORT_DESC]]);
		$this->assertEquals(['Chloé', 'Bruno', 'Alice'], array_values($cUser->makeArray(fn($eUser) => $eUser['parent']['name'])), 'le tri descendant à un niveau a changé');

	}

	private function users(array $names): \Collection {

		$cUser = new \Collection();

		foreach($names as $id => $name) {
			$cUser[$id] = new \user\User([
				'id' => $id,
				'parent' => new \user\User([
					'parent' => new \user\User(['name' => $name])
				])
			]);
		}

		return $cUser;

	}

	private function names(\Collection $cUser): array {
		return array_values($cUser->makeArray(fn(\user\User $eUser) => $eUser['parent']['parent']['name']));
	}

}
?>
