<?php
namespace work;

class Category_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list('actionCategoryList');

		$this->create('actionCategory');
		$this->read('actionCategory/{id}');
		$this->update('actionCategory/{id}');
		$this->delete('actionCategory/{id}');

		$this->element(
			'work\Category',
			fn() => [
				'id', 'name', 'fqn', 'position'
			]
		);

	}

	public function getNewModel(\ApiData $data): CategoryModel {

		return new CategoryModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Category {

		return new Category([
			'structure' => $data->eStructure
		]);

	}

}
?>
