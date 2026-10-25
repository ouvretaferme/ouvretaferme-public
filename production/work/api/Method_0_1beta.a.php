<?php
namespace work;

class Method_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list('actionMethodList');

		$this->create('actionMethod');
		$this->read('actionMethod/{id}');
		$this->update('actionMethod/{id}');
		$this->delete('actionMethod/{id}');

		$this->element(
			'work\Method',
			fn() => [
				'id', 'name', 'action'
			]
		);

	}

	public function getNewModel(\ApiData $data): MethodModel {

		return new MethodModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Method {

		return new Method([
			'structure' => $data->eStructure
		]);

	}

}
?>
