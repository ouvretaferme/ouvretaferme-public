<?php
namespace work;

class Supplier_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list('supplierList');

		$this->create('supplier');
		$this->read('supplier/{id}');
		$this->update('supplier/{id}');
		$this->delete('supplier/{id}');

		$this->element(
			'work\Supplier',
			fn() => [
				'id', 'name'
			]
		);

	}

	public function getNewModel(\ApiData $data): SupplierModel {

		return new SupplierModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Supplier {

		return new Supplier([
			'structure' => $data->eStructure
		]);

	}

}
?>
