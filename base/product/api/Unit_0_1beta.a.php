<?php
namespace product;

class Unit_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'commercialisation';

	public function __construct() {

		$this->list('unitList');

		$this->create('unit');
		$this->read('unit/{id}');
		$this->update('unit/{id}');
		$this->delete('unit/{id}');

		$this->element(
			'product\Unit',
			fn() => [
				'id', 'fqn',
				'singular', 'plural', 'short', 'approximate', 'type'
			]
		);

	}

	public function getNewModel(\ApiData $data): UnitModel {

		return new UnitModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Unit {

		return new Unit([
			'structure' => $data->eStructure
		]);

	}

}
?>
