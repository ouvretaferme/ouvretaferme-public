<?php
namespace toolbox;

/**
 * Les entretiens planifiés sur un matériel suivi (`equipment`), dus à une « prochaine fois » :
 * `plannedDate`, `plannedValue` (au compteur du matériel), ou les deux, et alors au premier des deux.
 *
 * Un entretien revient régulièrement quand il porte une fréquence, `everyValue` (toutes les N unités
 * du compteur), `everyMonths`, ou les deux. Les deux à `null` en font un entretien à faire une seule
 * fois : c'est la seule lecture possible d'un corps qui porte toujours les deux propriétés, là où
 * l'écran demande d'abord de choisir entre « Une seule fois » et « Régulièrement »
 * (`convertProperties()` plus bas). Les valeurs au compteur ne s'acceptent que sur un matériel à
 * compteur (`Maintenance::everyValue.meter`, `plannedValue.meter`).
 *
 * En lecture seule, ce que le carnet calcule et qu'un client recalculerait autrement
 * (`MaintenanceLib::fill()`) : `due` vaut `overdue`, `soon` ou `null`, `remainingDays` et
 * `remainingValue` disent ce qui reste avant l'échéance, négatifs une fois dépassée. Les trois sont
 * `null` sur un entretien qui n'est plus actif, ou dont le matériel n'est plus suivi ;
 * `remainingValue` l'est aussi tant que le matériel n'a aucun relevé.
 *
 * Le matériel se donne à la création et ne change plus. Pas de route de suppression : un entretien
 * retiré reste nommé par les entretiens faits de l'historique, et s'écrit `PUT maintenance/{id}` avec
 * `{"properties": ["status"], "status": "deleted"}`. Un corps sans `properties` doit tout porter,
 * `status` compris, dont la seule valeur acceptée est `deleted` : une modification porte donc
 * toujours `properties`, les fréquences ensemble, les deux échéances ensemble.
 *
 * `maintenanceList` écarte les entretiens retirés, que `?status=deleted` retrouve, comme `toolList`
 * écarte les matériels supprimés. Les entretiens faits une seule fois (`done`) restent rendus.
 *
 * `ignoredDate` se lit et ne s'écrit pas : « Ignorer ce retard » n'est pas un geste de l'API.
 */
class Maintenance_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	/**
	 * Les matériels de la structure tels que le carnet les montre, par requête : une liste de cent
	 * entretiens ne relit le carnet qu'une fois. La clé est la requête et non la structure, pour que
	 * rien ne survive à l'écriture qui a précédé la lecture.
	 */
	private \WeakMap $cEquipmentByData;

	public function __construct() {

		$this->cEquipmentByData = new \WeakMap();

		$this->list(
			'maintenanceList',
			[
				'?equipment' => Maintenance::getValidator('equipment'),
				'?status' => Maintenance::getValidator('status')
			]
		);

		$this->read('maintenance/{id}');
		$this->create('maintenance');
		$this->update('maintenance/{id}');

		$this->element(
			'toolbox\Maintenance',
			fn() => [
				'id',
				'equipment',
				'name',
				'everyValue',
				'everyMonths',
				'plannedDate',
				'plannedValue',
				'ignoredDate',
				'status',
				'due',
				'remainingDays',
				'remainingValue'
			],
			[
				'due' => fn(\ApiData $data, Maintenance $e) => $this->getFilled($data, $e, 'due'),
				'remainingDays' => fn(\ApiData $data, Maintenance $e) => $this->getFilled($data, $e, 'remainingDays'),
				'remainingValue' => fn(\ApiData $data, Maintenance $e) => $this->getFilled($data, $e, 'remainingValue')
			]
		);

	}

	/**
	 * Ce que `MaintenanceLib::fill()` a posé sur l'entretien, lu dans le carnet de la structure : le
	 * calcul de l'écran, pour que l'API et le carnet ne se contredisent jamais. Le carnet ne porte que
	 * les entretiens actifs des matériels suivis, d'où `NULL` pour les autres.
	 */
	private function getFilled(\ApiData $data, Maintenance $e, string $property): mixed {

		$this->cEquipmentByData[$data] ??= EquipmentLib::getByStructure($data->eStructure);

		$cEquipment = $this->cEquipmentByData[$data];

		if($cEquipment->offsetExists($e['equipment']['id']) === FALSE) {
			return NULL;
		}

		$cMaintenance = $cEquipment[$e['equipment']['id']]['cMaintenance'];

		if($cMaintenance->offsetExists($e['id']) === FALSE) {
			return NULL;
		}

		return $cMaintenance[$e['id']][$property];

	}

	/**
	 * « Une seule fois » ou « Régulièrement » n'est pas une colonne : les étapes de la fréquence le
	 * lisent dans l'entrée de `build()`, où le formulaire de l'écran le pose (`Maintenance::properties()`).
	 * L'API le déduit des deux fréquences, et ne le laisse pas venir du corps.
	 */
	public function convertProperties(\ApiData $data, array $properties, \PropertyList $l): array {

		$input = parent::convertProperties($data, $properties, $l);

		unset($input['frequency']);

		if(
			in_array('everyValue', $properties) and
			($input['everyValue'] ?? NULL) === NULL and
			($input['everyMonths'] ?? NULL) === NULL
		) {
			$input['frequency'] = 'once';
		}

		return $input;

	}

	/**
	 * Les fréquences se jugent l'une par l'autre (`everyMonths.frequency`), les échéances aussi
	 * (`plannedDate.planned`) : chaque paire se modifie ensemble.
	 */
	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['everyValue', 'everyMonths'],
				['plannedValue', 'plannedDate']
			]
		] + parent::getStandalonePropertiesUpdate($data);

	}

	/** Les étapes `meter` lisent le compteur du matériel : l'entretien se lit avec lui. */
	public function getSelection(\ModuleModel $m): array {

		$select = parent::getSelection($m);
		$select['equipment'] = ['meter'];

		return $select;

	}

	public function getList(\ApiData $data): MaintenanceModel {

		return $this->getNewModel($data)
			->whereEquipment(fn() => $data->get('equipment'), if: $data->exists('equipment'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'))
			->whereStatus('!=', Maintenance::DELETED, if: $data->exists('status') === FALSE);

	}

	public function getNewModel(\ApiData $data): MaintenanceModel {

		return new MaintenanceModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Maintenance {

		return new Maintenance([
			'structure' => $data->eStructure
		]);

	}

}
?>
