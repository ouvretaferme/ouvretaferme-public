<?php
namespace product;

class Category_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'commercialisation';

	public function __construct() {

		$this->list('productCategoryList');

		$this->create('productCategory');
		$this->read('productCategory/{id}');
		$this->update('productCategory/{id}');
		$this->delete('productCategory/{id}');

		$this->element(
			'product\Category',
			fn() => [
				'id', 'name', 'icon', 'position'
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
