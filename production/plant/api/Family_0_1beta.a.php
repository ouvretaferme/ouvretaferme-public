<?php
namespace plant;

class Family_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list('plantFamilyList');

		$this->read('plantFamily/{id}');

		$this->element(
			'plant\Family',
			fn() => [
				'id', 'name', 'fqn', 'color'
			]
		);

	}

	public function getNewModel(\ApiData $data): FamilyModel {
		return new FamilyModel();
	}

	public function getNewElement(\ApiData $data): Family {
		return new Family();
	}

}
?>
