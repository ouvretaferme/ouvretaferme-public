<?php
namespace hr;

class Teammate_0_1betaApi extends \ModuleApi {

	// L'équipe se gère depuis l'écran d'équipe, au même titre que les présences, les membres et les rôles.
	public string $scopeName = 'general';

	public function __construct() {

		$this->list(
			'teammateList',
			[
				'?user' => Teammate::getValidator('user')
			]
		);

		$this->create('teammate');
		$this->read('teammate/{id}');

		$this->element(
			'hr\Teammate',
			fn() => [
				'id', 'user'
			]
		);

	}

	public function getList(\ApiData $data): TeammateModel {

		return $this->getNewModel($data)
			->whereUser(fn() => $data->get('user'), if: $data->exists('user'));

	}

	public function getNewModel(\ApiData $data): TeammateModel {

		return new \hr\TeammateModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Teammate {

		return new \hr\Teammate([
			'structure' => $data->eStructure
		]);

	}

}
?>
