<?php
namespace structure;

class Production_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'general';

	public function __construct() {

		$this->list('productionList');

		$this->create('production');
		$this->read('production/{id}');
		$this->update('production/{id}');
		$this->delete('production/{id}');

		$this->element(
			'structure\Production',
			fn() => [
				'id', 'fqn',
				'area', 'areaGreenhouse', 'mechanisation',
				'organic', 'startedAt', 'description',
				'status'
			]
		);

	}

	public function getNewModel(\ApiData $data): ProductionModel {

		return new \structure\ProductionModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Production {

		return new \structure\Production([
			'structure' => $data->eStructure
		]);

	}

}
?>
