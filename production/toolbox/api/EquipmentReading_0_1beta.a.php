<?php
namespace toolbox;

/**
 * Les relevés du compteur d'un matériel suivi (`equipment`) : ce que le compteur affiche un jour
 * donné, dans l'unité du matériel (`meter`, en lecture).
 *
 * ⚠️ La création **remplace** le relevé du jour, elle n'en écrit pas un second : la table ne porte
 * qu'une ligne par matériel et par jour. Elle rend donc 200 et le relevé du jour, jamais 201
 * (`pushCreated()` plus bas). Un client qui réémet un appel après une coupure ne change rien, à
 * l'inverse de la pluie, qui s'ajoute (`Rain_0_1betaApi`).
 *
 * Un compteur ne recule pas : un relevé sous un relevé plus ancien (`EquipmentReading::value.backwards`)
 * ou au-dessus d'un relevé plus récent (`value.ahead`) est refusé en 400. L'écran en fait une
 * question, parce que le relevé peut corriger une erreur ; l'API n'a pas d'écran, et la réponse
 * voyage donc dans le corps : le même appel, renvoyé avec `"confirmed": true`, passe. `confirmed`
 * n'est pas une propriété du relevé, il ne se relit pas.
 *
 * Une modification ne change que `value` : un relevé garde son matériel et son jour, et se supprime
 * s'il a été posé au mauvais endroit.
 */
class EquipmentReading_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'equipmentReadingList',
			[
				'?equipment' => EquipmentReading::getValidator('equipment'),
				'?dateFrom' => new \FilterValidator('date'),
				'?dateTo' => new \FilterValidator('date')
			]
		);

		$this->read('equipmentReading/{id}');
		$this->create('equipmentReading');
		$this->update('equipmentReading/{id}');
		$this->delete('equipmentReading/{id}');

		$this->element(
			'toolbox\EquipmentReading',
			fn() => [
				'id',
				'equipment',
				'date',
				'value',
				'meter'
			]
		);

	}

	/**
	 * La confirmation d'un relevé hors de l'ordre du compteur. Les étapes `backwards` et `ahead` la
	 * lisent dans l'entrée de `build()` sous le nom que pose le formulaire de l'écran
	 * (`EquipmentReadingLib::isConfirmed()`), et l'entrée de l'API est le corps de la requête : sans ce
	 * filtre, un corps qui porterait ce nom passerait la règle sans l'avoir demandé. Seul
	 * `confirmed` à `true`, booléen, la pose.
	 */
	public function convertProperties(\ApiData $data, array $properties, \PropertyList $l): array {

		$input = parent::convertProperties($data, $properties, $l);

		unset($input['meterConfirmed']);

		if(($data->body['confirmed'] ?? NULL) === TRUE) {
			$input['meterConfirmed'] = '1';
		}

		return $input;

	}

	/**
	 * Le relevé du jour après l'écriture, qu'elle l'ait posé ou remplacé. Il se relit par (equipment,
	 * date), que l'unicité de la table rend fidèle : un relevé remplacé n'a pas donné d'id à l'élément
	 * construit, et le défaut du cadriciel, qui relit par l'id, ne le retrouverait pas.
	 */
	public function pushCreated(\ApiData $data): void {

		$data->httpCode = 200;

		$m = $this->getNewModel($data);

		$data->pushObject(NULL, $m
			->select($this->getSelection($m))
			->whereEquipment($data->e['equipment'])
			->whereDate($data->e['date'])
			->get());

	}

	/**
	 * `acceptUpdate()` et `acceptDelete()` jugent le matériel du relevé, et les étapes du compteur le
	 * comparent à ses voisins : le relevé se lit avec lui.
	 */
	public function getSelection(\ModuleModel $m): array {

		$select = parent::getSelection($m);
		$select['equipment'] = ['status', 'meter'];

		return $select;

	}

	public function getList(\ApiData $data): EquipmentReadingModel {

		return $this->getNewModel($data)
			->whereEquipment(fn() => $data->get('equipment'), if: $data->exists('equipment'))
			->whereDate('>=', fn() => $data->get('dateFrom'), if: $data->exists('dateFrom'))
			->whereDate('<=', fn() => $data->get('dateTo'), if: $data->exists('dateTo'));

	}

	public function getNewModel(\ApiData $data): EquipmentReadingModel {

		return new EquipmentReadingModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): EquipmentReading {

		return new EquipmentReading([
			'structure' => $data->eStructure
		]);

	}

}
?>
