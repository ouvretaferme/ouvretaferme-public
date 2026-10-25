<?php
namespace toolbox;

/**
 * L'historique d'un matériel suivi (`equipment`) : les entretiens faits, et les interventions.
 *
 * Une ligne qui nomme un entretien planifié (`maintenance`) dit qu'il a été fait ce jour-là : elle
 * fait repartir son échéance, ou le clôt s'il se fait une seule fois (`MaintenanceDoneLib::follow()`),
 * ce qui se relit sur `maintenance/{id}`. L'entretien est un entretien encore actif du matériel
 * donné (`MaintenanceDone::maintenance.owned`). Son libellé est celui de l'entretien : `name` reste
 * `null`, et ce qu'un corps y porterait est oublié.
 *
 * Avec `maintenance` à `null`, la ligne est une intervention : elle n'entre qu'à l'historique, sous
 * le libellé que `name` doit alors porter (`name.required`).
 *
 * `value` est le compteur du matériel ce jour-là. À `null` à la création, il est lu dans les relevés
 * et rendu tel quel, `null` quand le matériel n'en a aucun à cette date ou n'a pas de compteur. Donné,
 * il devient aussi le relevé du jour, qu'il remplace s'il existe (`MaintenanceDoneLib::create()`) :
 * il obéit donc à la règle d'un relevé, un compteur ne recule pas, et le même appel renvoyé avec
 * `"confirmed": true` passe (`EquipmentReading_0_1betaApi`). Il ne se donne plus ensuite : le
 * compteur d'un jour se corrige sur son relevé. Une ligne dont la date change le relit dans les
 * relevés à sa nouvelle date (`MaintenanceDoneLib::update()`), et le relevé de l'ancien jour reste.
 * Compteur coupé, elle garde le sien et l'échéance au compteur de l'entretien reste (`MaintenanceLib::plan()`) :
 * un entretien fait pendant que le compteur est retiré ne fait pas repartir son échéance au compteur.
 *
 * `value` est exigé dans un seul cas (`value.required`) : l'entretien nommé n'est dû qu'au compteur
 * et le matériel n'a aucun relevé, donc rien d'où faire repartir l'échéance
 * (`MaintenanceDoneLib::isValueRequired()`).
 *
 * Le matériel et l'entretien se donnent à la création et ne changent plus. Une ligne se supprime :
 * quand elle était la dernière fois de son entretien, l'échéance repart de la fois d'avant, et un
 * entretien « Une seule fois » redevient à faire. Quand la ligne supprimée était la seule d'un
 * entretien régulier, l'échéance reste celle qu'elle avait fait calculer : celle d'avant n'est plus
 * connue (`follow()`), et elle se corrige sur l'entretien (`plannedDate` et `plannedValue`, donnés
 * ensemble). Décidé le 01/10/2026.
 *
 * Seule la dernière fois d'un entretien tient son échéance : une ligne créée, déplacée ou supprimée
 * avant elle n'entre qu'à l'historique, et une échéance corrigée sur l'entretien lui survit. Quand
 * l'échéance repart d'une ligne, un retard ignoré avant sa date est oublié (`ignoredDate` à `null`).
 *
 * La photo d'un entretien n'est pas portée : elle se dépose par un envoi de fichier que l'API n'a pas.
 */
class MaintenanceDone_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'maintenanceDoneList',
			[
				'?equipment' => MaintenanceDone::getValidator('equipment'),
				'?maintenance' => MaintenanceDone::getValidator('maintenance'),
				'?dateFrom' => new \FilterValidator('date'),
				'?dateTo' => new \FilterValidator('date')
			]
		);

		$this->read('maintenanceDone/{id}');
		$this->create('maintenanceDone');
		$this->update('maintenanceDone/{id}');
		$this->delete('maintenanceDone/{id}');

		$this->element(
			'toolbox\MaintenanceDone',
			fn() => [
				'id',
				'equipment',
				'maintenance',
				'name',
				'date',
				'value',
				'cost',
				'comment'
			]
		);

	}

	/**
	 * L'écran demande d'abord « Mettre à jour le compteur ? », et l'étape `update` de `value` lit la
	 * réponse dans l'entrée de `build()` (`MaintenanceDone::properties()`). L'API n'a pas cette
	 * question : un compteur donné est un compteur à poser, `null` le laisse lire dans les relevés.
	 *
	 * La confirmation d'un compteur hors de l'ordre des relevés voyage comme sur un relevé
	 * (`EquipmentReading_0_1betaApi::convertProperties()`) : seul `confirmed` à `true`, booléen, la
	 * pose, et aucun des deux noms de l'écran ne passe depuis le corps.
	 */
	public function convertProperties(\ApiData $data, array $properties, \PropertyList $l): array {

		$input = parent::convertProperties($data, $properties, $l);

		unset($input['meterUpdate'], $input['meterConfirmed']);

		// Une clé absente du corps est refusée (`value.missing`) sans interrompre la conversion : elle
		// se lit donc ici après son refus.
		if(in_array('value', $properties) and ($input['value'] ?? NULL) !== NULL) {
			$input['meterUpdate'] = '1';
		}

		if(($data->body['confirmed'] ?? NULL) === TRUE) {
			$input['meterConfirmed'] = '1';
		}

		return $input;

	}

	/** `acceptUpdate()` et `acceptDelete()` jugent le matériel de la ligne : elle se lit avec lui. */
	public function getSelection(\ModuleModel $m): array {

		$select = parent::getSelection($m);
		$select['equipment'] = ['status', 'meter'];

		return $select;

	}

	public function getList(\ApiData $data): MaintenanceDoneModel {

		return $this->getNewModel($data)
			->whereEquipment(fn() => $data->get('equipment'), if: $data->exists('equipment'))
			->whereMaintenance(fn() => $data->get('maintenance'), if: $data->exists('maintenance'))
			->whereDate('>=', fn() => $data->get('dateFrom'), if: $data->exists('dateFrom'))
			->whereDate('<=', fn() => $data->get('dateTo'), if: $data->exists('dateTo'));

	}

	public function getNewModel(\ApiData $data): MaintenanceDoneModel {

		return new MaintenanceDoneModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): MaintenanceDone {

		return new MaintenanceDone([
			'structure' => $data->eStructure
		]);

	}

}
?>
