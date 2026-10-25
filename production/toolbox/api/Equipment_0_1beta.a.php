<?php
namespace toolbox;

/**
 * Les matériels que suit le carnet d'entretien. Un matériel de « Mon matériel » (`tool`, ressource
 * `tool` de l'API) entre au carnet ici, avec ou sans compteur (`meter` : `hours`, `kilometers`, ou
 * `null`), et c'est lui qui porte les relevés (`equipmentReading`) et les entretiens (`maintenance`).
 *
 * `POST equipment` suit un matériel qui existe déjà : le créer reste `POST tool`. Un matériel ne se
 * suit qu'une fois (`Equipment::tool.followed`). Celui qu'on ne suivait plus se réveille avec ses
 * relevés et son historique, et la réponse le dit par son code : 201 pour une ligne neuve, 200 pour
 * une ligne réveillée, dont l'`id` est celui d'avant. Il ne revient que dans l'unité de ses anciennes
 * valeurs de compteur (`Equipment::meter.locked`).
 *
 * Le premier relevé ne voyage pas ici, contrairement à l'écran « Suivre un matériel » : il se poste
 * ensuite sur `equipmentReading`, qui porte seul la règle du compteur qui ne recule pas.
 *
 * Pas de route de suppression, comme pour un pluviomètre (`Gauge_0_1betaApi`) : « Ne plus suivre »
 * garde les relevés et l'historique, et s'écrit `PUT equipment/{id}` avec
 * `{"properties": ["status"], "status": "inactive"}`. Un matériel inactif ne se modifie plus
 * (`acceptUpdate()`, 412) : il revient par `POST equipment`. Un corps sans `properties` doit porter
 * `meter` et `status`, dont la seule valeur acceptée est `inactive`.
 */
class Equipment_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'equipmentList',
			[
				'?tool' => Equipment::getValidator('tool'),
				'?status' => Equipment::getValidator('status')
			]
		);

		$this->read('equipment/{id}');
		$this->create('equipment');
		$this->update('equipment/{id}');

		$this->element(
			'toolbox\Equipment',
			fn() => [
				'id',
				'tool',
				'meter',
				'registration',
				'status'
			]
		);

	}

	public function pushCreated(\ApiData $data): void {

		parent::pushCreated($data);

		if($data->e['awakened']) {
			$data->httpCode = 200;
		}

	}

	public function getList(\ApiData $data): EquipmentModel {

		return $this->getNewModel($data)
			->whereTool(fn() => $data->get('tool'), if: $data->exists('tool'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): EquipmentModel {

		return new EquipmentModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Equipment {

		return new Equipment([
			'structure' => $data->eStructure
		]);

	}

}
?>
