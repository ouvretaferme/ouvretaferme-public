<?php
namespace hr;

class Absence_0_1betaApi extends \ModuleApi {

	// Cf. hr\Presence : même écran, même audience.
	public string $scopeName = 'general';

	public function __construct() {

		$this->list(
			'absenceList',
			[
				'?user' => Absence::getValidator('user')
			]
		);

		$this->create('absence');
		$this->read('absence/{id}');
		$this->update('absence/{id}');
		$this->delete('absence/{id}');

		$this->element(
			'hr\Absence',
			fn() => [
				'id', 'user', 'type', 'unit', 'status', 'from', 'to', 'duration', 'createdAt'
			]
		);

	}

	/**
	 * RH éteinte, la ressource est fermée en lecture comme en écriture. Le refus vient avant le chargement de
	 * l'élément, pour qu'un 404 ne dise pas quel identifiant existe.
	 */
	public function load(\ApiData $data, array $routeInfo): void {

		if($data->eStructure->acceptHr() === FALSE) {
			throw new \ApiException('HR is disabled', 'accept_hr', 412);
		}

		parent::load($data, $routeInfo);

	}

	public function getList(\ApiData $data): AbsenceModel {

		return $this->getNewModel($data)
			->whereUser(fn() => $data->get('user'), if: $data->exists('user'));

	}

	public function getNewModel(\ApiData $data): AbsenceModel {

		return new \hr\AbsenceModel()
			->whereStructure($data->eStructure);

	}

	/**
	 * Une clé d'API saisit comme le gérant : l'absence est validée d'emblée, et à la journée, la seule unité que
	 * l'API sait créer (`AbsenceLib::getPropertiesCreate()`). La durée se poste, en jours.
	 */
	public function getNewElement(\ApiData $data): Absence {

		return new \hr\Absence([
			'structure' => $data->eStructure,
			'unit' => Absence::DAY,
			'status' => Absence::VALIDATED
		]);

	}

}
?>
