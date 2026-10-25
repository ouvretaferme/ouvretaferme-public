<?php
namespace work;

class Action_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list('actionList');

		$this->create('action');
		$this->read('action/{id}');
		$this->update('action/{id}');
		$this->delete('action/{id}');

		$this->element(
			'work\Action',
			fn() => [
				'id', 'name', 'favorite', 'fqn', 'short', 'color', 'pace', 'soil', 'categories', 'methods', 'status'
			],
			[
				'methods' => fn(\ApiData $data, Action $e) => $e['cMethod']->getIds()
			]
		);

	}

	public function getNewModel(\ApiData $data): ActionModel {

		return new ActionModel()
			->whereStructure($data->eStructure)
			->select([
				'cMethod' => Method::model()
					->select(Method::getSelection())
					->sort('id')
					->delegateCollection('action')
			]);

	}

	public function getNewElement(\ApiData $data): Action {

		return new Action([
			'structure' => $data->eStructure
		]);

	}

}
?>
