<?php
namespace plant;

/**
 * Criterion basic functions
 */
class CriterionLib extends CriterionCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'status'];
	}

	/**
	 * Le référentiel des critères, commun à toutes les structures.
	 */
	public static function getAll(): \Collection {

		return Criterion::model()
			->select(Criterion::getSelection())
			->whereStatus(Criterion::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Le référentiel regroupé par famille, dans l'ordre du schéma : ce qui sort du
	 * champ, ce qui résiste, ce que ça coûte en travail, ce que ça vaut à la vente.
	 * Vingt critères en liste plate ne se lisent pas.
	 */
	public static function getAllByType(): array {

		$cCriterionByType = [];

		foreach(Criterion::model()->getPropertyEnum('type') as $type) {
			$cCriterionByType[$type] = new \Collection();
		}

		foreach(self::getAll() as $eCriterion) {
			$cCriterionByType[$eCriterion['type']][$eCriterion['id']] = $eCriterion;
		}

		return $cCriterionByType;

	}

	/**
	 * Les critères sur lesquels une espèce est jugée dans cette structure.
	 *
	 * Rien tant que l'espèce n'a pas été configurée : les critères servis sont ceux que
	 * la structure a choisis, pas un socle imposé. Ils vivent sur sa copie de l'espèce,
	 * qui lui est propre — la structure n'a donc pas à être passée.
	 */
	public static function getByPlant(Plant $ePlant): \Collection {

		if($ePlant->empty()) {
			return new \Collection();
		}

		// expects() et non une garde sur la propriété : une sélection qui ne charge pas
		// `criteria` rendait ici une collection vide, et l'écran de notation perdait
		// tous ses critères sans que rien ne le signale.
		$ePlant->expects(['criteria']);

		if($ePlant['criteria'] === []) {
			return new \Collection();
		}

		// Tri alphabétique, comme le référentiel complet : l'ordre d'affichage ne dépend
		// pas de celui dans lequel les critères ont été retenus. Un critère désactivé
		// depuis est écarté d'office.
		return Criterion::model()
			->select(Criterion::getSelection())
			->whereId('IN', $ePlant['criteria'])
			->whereStatus(Criterion::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Les critères que l'espèce de référence ajoute à ceux d'une structure, jusqu'à
	 * $max critères notés en tout.
	 *
	 * L'espèce mère range les siens par popularité décroissante : c'est cet ordre qui
	 * décide lesquels entrent quand il reste moins de place que de candidats. Une
	 * structure qui a retenu ses cinq critères en reçoit donc un, le plus répandu.
	 *
	 * Le classement dit lesquels montrer, pas dans quel ordre les lire : la carte les
	 * rend dans l'alphabet, comme les critères de la structure juste au-dessus.
	 */
	public static function getCompletion(Plant $ePlant, \Collection $cCriterion, int $max): \Collection {

		if($ePlant->empty()) {
			return new \Collection();
		}

		$ePlant->expects(['reference']);

		$slots = $max - $cCriterion->count();

		if($ePlant['reference']->empty() or $slots <= 0) {
			return new \Collection();
		}

		// La sélection par défaut d'une espèce ne ramène sa référence qu'en identifiant :
		// les critères populaires se relisent donc ici, plutôt que d'imposer une
		// sélection imbriquée à chaque appelant.
		$ePlantReference = Plant::model()
			->select(['id', 'criteria'])
			->whereId($ePlant['reference'])
			->get();

		if($ePlantReference->empty()) {
			return new \Collection();
		}

		$criterionIds = array_diff($ePlantReference['criteria'], $cCriterion->getIds());
		$criterionIds = array_slice($criterionIds, 0, $slots);

		if($criterionIds === []) {
			return new \Collection();
		}

		return Criterion::model()
			->select(Criterion::getSelection())
			->whereId('IN', $criterionIds)
			->whereStatus(Criterion::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Aligne les critères des espèces de référence sur les choix des structures.
	 *
	 * Chaque structure retient jusqu'à PLANT_CRITERIA_MAX critères sur sa propre copie
	 * d'une espèce. Ce dépouillement range les PLANT_CRITERIA_REFERENCE plus répandus
	 * sur l'espèce mère, par popularité décroissante : c'est de là que la carte de
	 * notation tire les critères qui complètent ceux d'une structure, et l'ordre du
	 * tableau y porte donc une information qu'il ne porte pas sur la copie d'une
	 * structure.
	 *
	 * Le fqn sert de pivot, et le classement compte les structures et non les lignes :
	 * une même structure peut porter deux espèces de même fqn sous deux noms.
	 *
	 * Seules les structures qui ont consenti au partage sont dépouillées, la même
	 * réciprocité que le reste de la communauté.
	 *
	 * Rejouable : une espèce dont le classement n'a pas bougé n'est pas réécrite.
	 */
	public static function synchronizeReference(): void {

		$cPlantReference = Plant::model()
			->select(['id', 'fqn', 'structure', 'criteria'])
			->whereStructure(NULL)
			->whereFqn('!=', NULL)
			->getCollection();

		// Un seul passage sur les votes plutôt qu'une requête par espèce : ils tiennent
		// en quelques milliers de lignes, une par structure et par espèce configurée.
		$cPlant = Plant::model()
			->select(['id', 'fqn', 'structure', 'criteria'])
			->join(\structure\Structure::model(), 'm1.structure = m2.id')
			->where('m1.fqn', '!=', NULL)
			->where('m2.consentData', TRUE)
			->where('m2.id', 'IN', \structure\StructureLib::getInstalledSql())
			->where('JSON_LENGTH(m1.criteria) > 0')
			->getCollection();

		$structuresByFqn = [];

		foreach($cPlant as $ePlant) {

			foreach($ePlant['criteria'] as $criterionId) {
				$structuresByFqn[$ePlant['fqn']][$criterionId][$ePlant['structure']['id']] = TRUE;
			}

		}

		foreach($cPlantReference as $ePlantReference) {

			$structures = array_map('count', $structuresByFqn[$ePlantReference['fqn']] ?? []);
			$structures = array_filter($structures, fn(int $count) => $count >= PlantSetting::PLANT_CRITERIA_REFERENCE_STRUCTURES_MIN);

			$criterionIds = array_keys($structures);

			// Départage nécessaire : sans lui, les égalités d'effectif laissent PHP libre
			// de changer l'ordre d'un passage à l'autre, et l'espèce mère proposerait un
			// jour un critère, la quinzaine suivante un autre, sans qu'aucun vote ait bougé.
			usort($criterionIds, fn(int $id1, int $id2) => ($structures[$id2] <=> $structures[$id1]) ?: ($id1 <=> $id2));

			$criterionIds = array_slice($criterionIds, 0, PlantSetting::PLANT_CRITERIA_REFERENCE);

			if($criterionIds === $ePlantReference['criteria']) {
				continue;
			}

			$ePlantReference['criteria'] = $criterionIds;

			PlantLib::update($ePlantReference, ['criteria']);

		}

	}

	/**
	 * Les critères que la communauté retient pour chacune de ces espèces, lus sur les
	 * espèces de référence en une requête.
	 *
	 * Rendu indexé par identifiant d'espèce de la structure, avec une entrée pour
	 * chacune : une espèce sans référence, ou dont la référence n'a pas encore été
	 * classée, porte un tableau vide plutôt que rien.
	 */
	public static function getReferenceByPlant(\Collection $cPlant): array {

		$referenceIds = [];

		foreach($cPlant as $ePlant) {

			if($ePlant['reference']->notEmpty()) {
				$referenceIds[] = $ePlant['reference']['id'];
			}

		}

		$cPlantReference = ($referenceIds === []) ?
			new \Collection() :
			Plant::model()
				->select(['id', 'criteria'])
				->whereId('IN', $referenceIds)
				->getCollection(index: 'id');

		$criteria = [];

		foreach($cPlant as $ePlant) {

			$referenceId = $ePlant['reference']->notEmpty() ? $ePlant['reference']['id'] : NULL;

			$criteria[$ePlant['id']] = $cPlantReference->offsetExists($referenceId) ?
				$cPlantReference[$referenceId]['criteria'] :
				[];

		}

		return $criteria;

	}

}
?>
