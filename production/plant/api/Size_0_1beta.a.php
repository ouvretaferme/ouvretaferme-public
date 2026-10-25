<?php
namespace plant;

class Size_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'plantSizeList',
			[
				'?plant' => Size::getValidator('plant')
			]
		);

		$this->create('plantSize');
		$this->read('plantSize/{id}');
		$this->update('plantSize/{id}');
		$this->delete('plantSize/{id}');

		$this->element(
			'plant\Size',
			fn() => [
				'id', 'name', 'comment', 'plant', 'yield'
			]
		);

	}

	public function getNewModel(\ApiData $data): SizeModel {

		return new SizeModel()
			->whereStructure($data->eStructure)
			->wherePlant(fn() => $data->get('plant'), if: $data->exists('plant'));

	}

	public function getNewElement(\ApiData $data): Size {

		return new Size([
			'structure' => $data->eStructure
		]);

	}

}
?>
