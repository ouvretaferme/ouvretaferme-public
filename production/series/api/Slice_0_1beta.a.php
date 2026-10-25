<?php
namespace series;

class Slice_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->element(
			'series\Slice',
			fn() => [
				'id', 'variety', 'partPercent', 'partArea', 'partLength', 'partPlant', 'partTray'
			]
		);

	}

	public function getNewModel(\ApiData $data): SliceModel {
		throw new \UnsupportedException();
	}

	public function getNewElement(\ApiData $data): Slice {
		throw new \UnsupportedException();
	}

}
?>
