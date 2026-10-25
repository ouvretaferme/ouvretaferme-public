<?php
namespace plant;

class Variety_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		/**
		 * Le référentiel national des variétés n'a pas de structure, donc pas de `whereStructure()` qui
		 * le borne : une liste de l'API ne s'en passe que pour un référentiel national. Il se lit parce
		 * que `reference` de `plantVarietyList` le désigne, il ne s'écrit jamais (`getNewModel()`).
		 * `?plant` ne fait que restreindre : ce qui sort reste du référentiel.
		 */
		$this->list(
			'plantVarietyListGlobal',
			[
				'?plant' => Variety::getValidator('plant')
			],
			get:
				fn($data) => $this->getNewModel($data)
					->whereStructure(NULL)
					->whereId('NOT IN', [PlantSetting::VARIETY_MIX, PlantSetting::VARIETY_UNKNOWN])
		);

		$this->list(
			'plantVarietyList',
			[
				'?plant' => Variety::getValidator('plant')
			],
			get:
				fn($data) => $this->getNewModel($data)
					->whereStructure($data->eStructure)
		);

		$this->create('plantVariety');
		$this->read('plantVariety/{id}');
		$this->update('plantVariety/{id}');
		$this->delete('plantVariety/{id}');

		$this->element(
			'plant\Variety',
			fn(\ApiData $data, Variety $e) => array_merge(
				[
					'id', 'name', 'fqn', 'plant'
				],
				$e['structure']->empty() ? ['tags'] : ['reference', 'supplierSeed', 'supplierPlant', 'weightSeed1000', 'numberPlantKilogram', 'stock']
			)
		);

	}

	/**
	 * Le référentiel partagé (structure NULL) se lit mais ne s'écrit pas : getById() sert aussi à
	 * update() et delete(), l'élargir sans distinguer la méthode laisserait n'importe quelle clé API
	 * renommer une variété pour toutes les structures.
	 */
	public function getNewModel(\ApiData $data): VarietyModel {

		if($data->routeInfo['requestMethod'] === 'GET') {

			$m = new VarietyModel()
				->or(
					fn() => $this->whereStructure($data->eStructure),
					fn() => $this->whereStructure(NULL),
				);

		} else {

			$m = new VarietyModel()->whereStructure($data->eStructure);

		}

		$m->wherePlant(fn() => $data->get('plant'), if: $data->exists('plant'));

		return $m;

	}

	public function getNewElement(\ApiData $data): Variety {

		return new Variety([
			'structure' => $data->eStructure
		]);

	}

}
?>
