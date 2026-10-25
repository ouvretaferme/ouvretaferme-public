<?php
namespace plant;

/**
 * Les espèces auxquelles une note se rattache (`base/note`, contrat tenu par `NoteTargetSentinel`).
 *
 * Une note se rattache aux espèces de la structure et à celles que la base partage (`structure` nulle).
 * Les suggestions ne proposent que les premières : une espèce partagée est le modèle que chaque structure
 * recopie à sa création (`PlantLib::duplicateForStructure()`), et la proposer à côté de sa copie
 * montrerait deux fois la même blette.
 */
class NoteTargetLib {

	public static function getType(): string {
		return 'plant-plant';
	}

	public static function getPosition(): int {
		return 1;
	}

	/**
	 * Les espèces qui répondent à la recherche. Sans `$ids`, ce sont les suggestions du champ : les espèces
	 * actives de la structure, par nom ou par alias, les plus proches d'abord. Avec `$ids`, ce sont celles
	 * dont parlent déjà des notes, que le filtre propose quel que soit leur statut et partagées comprises,
	 * puisque `getByIds()` les rattache.
	 */
	public static function search(\structure\Structure $eStructure, string $query, int $limit, ?array $ids = NULL): \Collection {

		if($ids !== NULL) {
			$cPlant = self::getByIds($eStructure, $ids)
				->find(fn(Plant $ePlant) => ($query === '' or mb_stripos($ePlant['name'], $query) !== FALSE))
				->sort('name');

			return $cPlant->slice(0, min($limit, $cPlant->count()), preserveKeys: TRUE);

		}

		$cPlant = PlantLib::getByStructure($eStructure, properties: ['id', 'name', 'fqn'], search: new \Search([
			'name' => $query
		]));

		return $cPlant->slice(0, min($limit, $cPlant->count()), preserveKeys: TRUE);

	}

	/**
	 * Les espèces de la structure ou partagées parmi `$ids`, rangées par id, actives ou non : une espèce
	 * qu'on n'a plus au plan reste le sujet des notes qui en parlent.
	 */
	public static function getByIds(\structure\Structure $eStructure, array $ids): \Collection {

		return Plant::model()
			->select(['id', 'name', 'fqn'])
			->whereId('IN', $ids)
			->or(
				fn() => $this->whereStructure($eStructure),
				fn() => $this->whereStructure(NULL)
			)
			->getCollection(index: 'id');

	}

}
?>
