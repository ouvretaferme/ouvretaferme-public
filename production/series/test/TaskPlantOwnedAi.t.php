<?php
namespace series;

/**
 * L'espèce d'une intervention appartient à la structure de l'intervention (lot R-Retours-0410-soir P,
 * décision du 05/10) : `Task::plant.owned`, posé après `Task::plant.action` dans
 * `Task::properties()`. Avant lui, une espèce d'une autre structure, ou du référentiel partagé, postée
 * à la main passait : `plant.action` ne vérifiait que son existence.
 *
 * Le référentiel partagé (`plant\Plant.structure IS NULL`) est refusé lui aussi : une structure travaille
 * sur sa copie de l'espèce, et aucune intervention de la base de dev ne porte une espèce partagée ni
 * celle d'une autre structure (mesuré le 05/10 sur 566 974 interventions avec espèce : 0 et 0).
 *
 * Décor emprunté à la dev, en lecture seule : deux espèces de deux structures différentes, et une espèce
 * du référentiel partagé. L'intervention est construite à la main, seule `plant` est passée à `build()`,
 * avec l'action et la catégorie que `plant.action` attend.
 *
 * Lancement : dev/test package=series file=TaskPlantOwnedAi
 */
class TaskPlantOwnedAiTest extends \Test {

	private function getPlantOwned(): \plant\Plant {

		return \plant\Plant::model()
			->select(['id', 'structure'])
			->where('structure IS NOT NULL')
			->sort(['id' => SORT_ASC])
			->get();

	}

	private function getPlantOther(\structure\Structure $eStructure): \plant\Plant {

		return \plant\Plant::model()
			->select(['id', 'structure'])
			->where('structure IS NOT NULL')
			->whereStructure('!=', $eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	private function getPlantShared(): \plant\Plant {

		return \plant\Plant::model()
			->select(['id', 'structure'])
			->where('structure IS NULL')
			->sort(['id' => SORT_ASC])
			->get();

	}

	/**
	 * Construit `plant` sur une intervention de culture de la structure, et rend les fails levés.
	 */
	private function build(\structure\Structure $eStructure, string $actionFqn, string $plant): array {

		$eTask = new Task([
			'structure' => $eStructure,
			'category' => new \work\Category(['fqn' => CATEGORIE_CULTURE]),
			'action' => new \work\Action(['fqn' => $actionFqn])
		]);

		$fw = new \FailWatch();
		$eTask->build(['plant'], ['plant' => $plant]);

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	public function testOwned(): void {

		$ePlant = $this->getPlantOwned();

		if($this->assertDecorPresent($ePlant->notEmpty(), 'une espèce propre à une structure') === FALSE) {
			return;
		}

		$this->assertEquals([], $this->build($ePlant['structure'], ACTION_RECOLTE, (string)$ePlant['id']), 'l\'espèce de la structure est acceptée');
		$this->assertEquals([], $this->build($ePlant['structure'], ACTION_PLANTATION, ''), 'sans espèce, hors récolte');
		$this->assertEquals(['Task::plant.action'], $this->build($ePlant['structure'], ACTION_RECOLTE, ''), 'une récolte exige toujours une espèce');

	}

	public function testOtherStructure(): void {

		$ePlant = $this->getPlantOwned();
		$ePlantOther = $ePlant->notEmpty() ? $this->getPlantOther($ePlant['structure']) : new \plant\Plant();

		if($this->assertDecorPresent($ePlantOther->notEmpty(), 'deux espèces propres à deux structures différentes') === FALSE) {
			return;
		}

		$this->assertEquals(['Task::plant.owned'], $this->build($ePlant['structure'], ACTION_RECOLTE, (string)$ePlantOther['id']), 'l\'espèce d\'une autre structure est refusée');
		$this->assertEquals(['Task::plant.owned'], $this->build($ePlant['structure'], ACTION_PLANTATION, (string)$ePlantOther['id']), 'hors récolte aussi');

	}

	public function testShared(): void {

		$ePlant = $this->getPlantOwned();
		$ePlantShared = $this->getPlantShared();

		if($this->assertDecorPresent($ePlant->notEmpty() and $ePlantShared->notEmpty(), 'une espèce propre à une structure et une espèce du référentiel partagé') === FALSE) {
			return;
		}

		$this->assertEquals(['Task::plant.owned'], $this->build($ePlant['structure'], ACTION_RECOLTE, (string)$ePlantShared['id']), 'l\'espèce du référentiel partagé est refusée');

	}

}
?>
