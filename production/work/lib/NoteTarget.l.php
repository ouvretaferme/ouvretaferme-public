<?php
namespace work;

/**
 * Les interventions auxquelles une note se rattache (`base/note`, contrat tenu par `NoteTargetSentinel`).
 *
 * Une note se rattache aux interventions de la structure et à celles que la base partage (`structure`
 * nulle). Les suggestions ne proposent que les premières, pour la raison des espèces : une intervention
 * partagée est le modèle que chaque structure recopie (`ActionLib::duplicateForStructure()`).
 */
class NoteTargetLib {

	public static function getType(): string {
		return 'work-action';
	}

	public static function getPosition(): int {
		return 2;
	}

	/**
	 * Les interventions qui répondent à la recherche, par ordre alphabétique. Sans `$ids`, ce sont les
	 * suggestions du champ : les interventions actives de la structure. Avec `$ids`, ce sont celles dont
	 * parlent déjà des notes, que le filtre propose quel que soit leur statut et partagées comprises,
	 * puisque `getByIds()` les rattache.
	 */
	public static function search(\structure\Structure $eStructure, string $query, int $limit, ?array $ids = NULL): \Collection {

		if($ids !== NULL) {
			$cAction = self::getByIds($eStructure, $ids)
				->find(fn(Action $eAction) => ($query === '' or mb_stripos($eAction['name'], $query) !== FALSE))
				->sort('name');

			return $cAction->slice(0, min($limit, $cAction->count()), preserveKeys: TRUE);

		}

		$cAction = ActionLib::getByStructure($eStructure, index: 'id', search: new \Search([
			'name' => $query
		]));

		return $cAction->slice(0, min($limit, $cAction->count()), preserveKeys: TRUE);

	}

	/**
	 * Les interventions de la structure ou partagées parmi `$ids`, rangées par id, actives ou non.
	 */
	public static function getByIds(\structure\Structure $eStructure, array $ids): \Collection {

		return Action::model()
			->select(['id', 'name'])
			->whereId('IN', $ids)
			->or(
				fn() => $this->whereStructure($eStructure),
				fn() => $this->whereStructure(NULL)
			)
			->getCollection(index: 'id');

	}

}
?>
