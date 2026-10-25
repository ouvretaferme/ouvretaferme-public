<?php
namespace plant;

/**
 * Plant basic functions
 */
class PlantLib extends PlantCrud {

	use \Notifiable;

	public static function getVegetableByUsage(\structure\Structure $eStructure): \Collection {

		return \series\Cultivation::model()
			->select([
				'plant' => PlantElement::getSelection(),
				'total' => new \Sql('COUNT(*)', 'int'),
			])
			->whereStructure($eStructure)
			->group(['plant'])
			// Départage nécessaire : sans lui, les nombreuses égalités d'usage laissent
			// MySQL libre de changer l'ordre d'une requête à l'autre, et la navigation
			// par flèches ne ramène plus sur la même espèce.
			->sort(new \Sql('total DESC, plant ASC'))
			->getColumn('plant')
			->find(fn(Plant $ePlant) => $ePlant['usageVegetable']);

	}

	/**
	 * Les espèces qu'une structure peut évaluer, chacune portant les critères que la
	 * communauté retient pour elle.
	 *
	 * Le bloc s'affiche sur deux écrans, la page des critères et celle des variétés
	 * préférées : la sélection vit donc ici, faute de quoi les deux pages en tiendraient
	 * chacune leur version et divergeraient au premier changement.
	 *
	 * Ordre alphabétique et non par usage : sur une vue d'ensemble, on vient chercher
	 * une espèce qu'on a en tête, et c'est son nom qu'on connaît.
	 */
	public static function getForCriteria(\structure\Structure $eStructure): \Collection {

		$cPlant = self::getVegetableByUsage($eStructure);

		$cPlant->sort(fn(Plant $ePlant1, Plant $ePlant2) => \L::getCollator()->compare($ePlant1['name'], $ePlant2['name']));

		$criteriaReference = CriterionLib::getReferenceByPlant($cPlant);

		foreach($cPlant as $ePlant) {
			$ePlant['criteriaReference'] = $criteriaReference[$ePlant['id']];
		}

		return $cPlant;

	}

	/**
	 * Les espèces représentées dans une liste de variétés, chacune portant le nombre
	 * de variétés qu'elle y apporte.
	 *
	 * Rien à interroger : les variétés portent déjà leur espèce. L'ordre de la liste
	 * est repris tel quel, de sorte que le sélecteur de l'écran de notation s'ouvre
	 * sur la même espèce que la variété affichée.
	 */
	public static function getByVariety(\Collection $cVariety): \Collection {

		$cPlant = new \Collection();

		foreach($cVariety as $eVariety) {

			$ePlant = $eVariety['plant'];

			if($ePlant->empty()) {
				continue;
			}

			if($cPlant->offsetExists($ePlant['id'])) {
				$cPlant[$ePlant['id']]['varieties']++;
			} else {
				$cPlant[$ePlant['id']] = $ePlant->merge(['varieties' => 1]);
			}

		}

		return $cPlant;

	}

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'color', 'family', 'cycle', 'plantsSafetyMargin', 'seedsSafetyMargin'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {
		return function(Plant $e) use($origin) {

			if($origin === 'crate') {
				return ['crateWeight'];
			}

			if($e->isOwner()) {
				$properties = ['name', 'color', 'family', 'cycle', 'plantsSafetyMargin', 'seedsSafetyMargin'];
			} else {
				$properties = ['name', 'color', 'plantsSafetyMargin', 'seedsSafetyMargin'];
			}

			// `ModuleApi` exige dans le corps toute propriété de la liste : l'API la rendrait
			// obligatoire à chaque modification.
			if($origin !== 'api') {
				$properties[] = 'storage';
			}

			if($origin !== 'api' and $e->acceptRating()) {
				$properties[] = 'criteria';
			}

			return $properties;

		};
	}

	public static function getFromQuery(string $query, \structure\Structure $eStructure, \Search $search = new \Search(), ?array $properties = []): \Collection {

		if(strpos($query, '#') === 0 and ctype_digit(substr($query, 1))) {

			Plant::model()->whereId(substr($query, 1));

		} else if($query !== '') {

			self::applyWhereName($query);

		} else {
			Plant::model()->sort('name');
		}

		if($search->get('ids')) {
			Plant::model()->whereId('IN', $search->get('ids'));
		}

		return Plant::model()
			->select($properties ?: Plant::getSelection())
			->whereStructure($eStructure)
			->whereStatus(Plant::ACTIVE)
			->getCollection();

	}

	private static function applyWhereName(string $query): void {

		Plant::model()
			->where('
					aliases LIKE '.\structure\Structure::model()->format('%'.$query.'%').' OR
					name LIKE '.\structure\Structure::model()->format('%'.$query.'%').'
				')
			->sort([
				new \Sql('
						IF(
							name LIKE '.\structure\Structure::model()->format($query.'%').',
							3,
							IF(
								name LIKE '.\structure\Structure::model()->format('%'.$query.'%').',
								2,
								IF(aliases LIKE '.\structure\Structure::model()->format('%'.$query.'%').', 1, 0)
							)
						) DESC'),
				'name' => SORT_ASC
			]);

	}

	public static function getStorageDefault(Plant $ePlant): int {

		if($ePlant['reference']->empty()) {
			return 0;
		}

		return Plant::model()
			->select(['storage'])
			->whereId($ePlant['reference'])
			->get()['storage'];

	}

	public static function duplicateForStructure(\structure\Structure $eStructure): void {

		$cPlant = Plant::model()
			->select(Plant::getSelection())
			->whereStructure(NULL)
			->getCollection();

		$cPlant->map(function(Plant $ePlant) use($eStructure) {

			// À lire avant d'effacer l'id : la copie pend de l'espèce dont elle sort.
			$ePlant['reference'] = new Plant(['id' => $ePlant['id']]);

			$ePlant['id'] = NULL;
			$ePlant['structure'] = $eStructure;

			// Cette information est reliée uniquement aux plantes = NULL
			$ePlant['tagsAccepted'] = [];

			// Sur l'espèce de référence, `criteria` est le classement communautaire, pas un
			// choix : le recopier ferait voter la structure pour des critères qu'elle n'a
			// pas retenus. Elle les voit comme suggestions via `criteriaReference`.
			$ePlant['criteria'] = [];

			// NULL suit la durée de l'espèce de référence : recopiée, elle passerait pour un
			// réglage de la structure, qu'une révision du référentiel ne toucherait plus.
			$ePlant['storage'] = NULL;

			// La copie naît avec la structure : sans cette remise, elle hériterait de la date
			// de l'espèce mère. Absente, la colonne retombe sur le défaut du modèle.
			unset($ePlant['createdAt']);

		});

		Plant::model()->insert($cPlant);

	}

	public static function countByStructure(\structure\Structure $eStructure): array {

		return Plant::model()
			->select([
				Plant::ACTIVE => new \Sql('SUM(status = "'.Plant::ACTIVE.'")', 'int'),
				Plant::INACTIVE => new \Sql('SUM(status = "'.Plant::INACTIVE.'")', 'int')
			])
			->whereStructure($eStructure)
			->get()
			->getArrayCopy() ?: [Plant::ACTIVE => 0, Plant::INACTIVE => 0];

	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, bool $selectMetadata = FALSE, ?array $properties = NULL, \Search $search = new \Search()): \Collection|\Element {

		$expects = 'collection';

		if($id !== NULL) {
			$expects = 'element';
			Plant::model()->whereId($id);
		}

		if($selectMetadata) {

			Plant::model()
				->select([
					'varieties' => Variety::model()
						->whereStructure($eStructure)
						->group('plant')
						->delegateProperty('plant', new \Sql('COUNT(*)', 'int')),
					'sizes' => Size::model()
						->whereStructure($eStructure)
						->group('plant')
						->delegateProperty('plant', new \Sql('COUNT(*)', 'int'))
				]);

		}

		if($search->get('name')) {
			self::applyWhereName($search->get('name'));
		}

		if($search->get('status')) {
			Plant::model()->whereStatus($search->get('status'));
		} else {
			Plant::model()->whereStatus(Plant::ACTIVE);
		}

		Plant::model()
			->select($properties ?? Plant::getSelection())
			->whereId($search->get('id'), if: $search->get('id'))
			->whereId('IN', $search->get('ids'), if: $search->get('ids'))
			->whereStructure($eStructure)
			->whereFamily($search->get('family'), if: $search->get('family')?->notEmpty())
			->sort('name');

		if($expects === 'element') {
			return Plant::model()->get();
		} else {
			return Plant::model()->getCollection(NULL, NULL, 'id');
		}

	}

	public static function getList(?array $properties = NULL): \Collection {

		return Plant::model()
			->select($properties ?? Plant::getSelection())
			->whereStructure(NULL)
			->sort('name')
			->getCollection(NULL, NULL, 'id');

	}

	public static function create(Plant $e): void {

		try {

			parent::create($e);

		} catch(\DuplicateException) {
			Plant::fail('name.duplicate');
		}

	}

	public static function update(Plant $e, array $properties): void {

		$e->expects(['structure']);

		Plant::model()->beginTransaction();

			try {
				parent::update($e, $properties);
			}
			catch(\DuplicateException) {
				Plant::fail('name.duplicate');
			}


		Plant::model()->commit();
	}

	public static function prepareCrateWeight(Plant $e, array $input): void {

		$e->expects(['structure', 'crateWeight']);

		$posted = (($input['crateWeight'] ?? '') !== '');

		if($e->assertWrite() and ($posted or $e['crateWeight'] === NULL)) {
			$e->build(self::getPropertiesUpdate('crate')($e), $input);
		} else if($e['crateWeight'] === NULL) {
			Plant::fail('crateWeight.denied');
		}

	}

	// Les ventes de l'année des produits de l'espèce sont posées sur elle par selling\PlantObserverLib,
	// et seul selling\PlantObserverUi les lit : la version publique n'en a pas.
	public static function loadDisplay(\structure\Structure $eStructure, Plant $e): void {

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('loadDisplay', $eStructure, $e);
		}

	}

	// Les compositions de panier, les prix au détail et les saisons de vente de la bibliothèque de
	// cultures se lisent dans les ventes de tout le parc : selling\PlantObserverLib les projette, et
	// la version publique n'en a pas.
	public static function projectCommunity(): void {

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('projectCommunity');
		}

	}

	/**
	 * L'espèce portée par un rapport d'analyse. Le package analyze y répond, et il est absent de la
	 * version publique.
	 */
	public static function isUsed(Plant $e): bool {

		if(\structure\StructureSetting::hasAnalyzePackage() === FALSE) {
			return FALSE;
		}

		return in_array(TRUE, self::notify('isUsed', $e), TRUE);

	}

	public static function delete(Plant $e): void {

		$e->expects(['id', 'structure']);

		if(
			\sequence\Crop::model()
				->whereStructure($e['structure'])
				->wherePlant($e)
				->exists() or
			\series\Cultivation::model()
				->whereStructure($e['structure'])
				->wherePlant($e)
				->exists() or
			Variety::model()
				->wherePlant($e)
				->exists() or
			\series\Task::model()
				->whereStructure($e['structure'])
				->wherePlant($e)
				->exists() or
			self::isUsed($e)
		) {
			Plant::fail('deleteUsed');
			return;
		}

		$cCompositionSpecies = \toolbox\CompositionSpeciesLib::getByPlant($e);

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			if(\toolbox\CompositionSpeciesLib::isDeletable($eCompositionSpecies) === FALSE) {
				Plant::fail('deleteComposition');
				return;
			}

		}

		Plant::model()->beginTransaction();

			foreach($cCompositionSpecies as $eCompositionSpecies) {
				\toolbox\CompositionSpeciesLib::delete($eCompositionSpecies);
			}

			Variety::model()
				->whereStructure($e['structure'])
				->wherePlant($e)
				->delete();

			Forecast::model()
				->whereStructure($e['structure'])
				->wherePlant($e)
				->delete();

			\product\Product::model()
				->whereStructure($e['structure'])
				->wherePlant($e)
				->update([
					'plant' => new Plant()
				]);

		Plant::model()->delete($e);

		Plant::model()->commit();

	}

}
?>
