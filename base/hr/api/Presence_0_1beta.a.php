<?php
namespace hr;

class Presence_0_1betaApi extends \ModuleApi {

	// Les présences se gèrent depuis l'écran d'équipe, au même titre que les membres et les rôles.
	public string $scopeName = 'general';

	public function __construct() {

		$this->list(
			'presenceList',
			[
				'?user' => Presence::getValidator('user')
			]
		);

		$this->create('presence');
		$this->read('presence/{id}');
		$this->update('presence/{id}');
		$this->delete('presence/{id}');

		$this->element(
			'hr\Presence',
			fn() => [
				'id', 'user', 'from', 'to'
			]
		);

	}

	public function getList(\ApiData $data): PresenceModel {

		return $this->getNewModel($data)
			->whereUser(fn() => $data->get('user'), if: $data->exists('user'));

	}

	public function getNewModel(\ApiData $data): PresenceModel {

		return new \hr\PresenceModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Presence {

		return new \hr\Presence([
			'structure' => $data->eStructure
		]);

	}

}
?>
