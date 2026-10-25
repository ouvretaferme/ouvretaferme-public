<?php
namespace structure;

class Role_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'general';

	public function __construct() {

		$this->list('roleList');

		$this->create('role');
		$this->read('role/{id}');
		$this->update('role/{id}');
		$this->delete('role/{id}');

		$this->element(
			'structure\Role',
			fn() => [
				'id', 'name', 'fqn',
				'visibility', 'admin',
				'planning', 'selling',
				'productionObserve', 'productionAnalyze',
				'commercialisationAnalyze', 'accounting', 'accountingAnalyze'
			]
		);

	}

	/**
	 * Les rôles que l'écran montre, comme RoleLib::getByStructure() : les rôles publics, admin compris,
	 * que memberList désigne. Un rôle privé (fantôme, référent d'AMAP) n'est ni listé ni lu par son id.
	 */
	public function getNewModel(\ApiData $data): RoleModel {

		return new \structure\RoleModel()
			->whereStructure($data->eStructure)
			->whereVisibility(Role::PUBLIC);

	}

	public function getNewElement(\ApiData $data): Role {

		return new \structure\Role([
			'structure' => $data->eStructure
		]);

	}

}
?>
