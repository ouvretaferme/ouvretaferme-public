<?php
namespace toolbox;

/**
 * Les relevés d'un compteur d'eau (`waterMeter`) : ce que le compteur affiche un jour donné, en m³,
 * au dixième. Un relevé n'est pas une consommation : la consommation est l'écart entre deux relevés
 * qui se suivent, et elle ne se lit pas ici, elle se calcule chez le client.
 *
 * ⚠️ La création **remplace** le relevé du jour, elle n'en écrit pas un second : la table ne porte
 * qu'une ligne par compteur et par jour. Elle rend donc 200 et le relevé du jour, jamais 201
 * (`pushCreated()` plus bas), comme le relevé d'un matériel (`EquipmentReading_0_1betaApi`). Un
 * client qui réémet un appel après une coupure ne change rien.
 *
 * Un relevé plus bas que le précédent n'est **pas** refusé, à l'inverse du compteur d'un matériel :
 * c'est un compteur qui a fait le tour, et la consommation se compte alors en passant par zéro,
 * avec le nombre de chiffres du compteur (`digits` de `waterMeter`). Rien ne le marque en base, et
 * il n'y a donc pas de confirmation à donner. Seul ce nombre de chiffres borne un relevé
 * (`WaterReading::value.digits`), celui du compteur en place le jour du relevé.
 *
 * Un compteur remplacé garde ses relevés : celui du jour du changement porte `changed`, `value` y
 * est ce que le nouveau compteur affichait à sa pose, d'où les relevés suivants se comptent, et
 * `changedValue` ce que l'ancien affichait à sa dépose, vide quand il n'a pas été relevé. La
 * consommation de ce jour-là est l'écart entre le relevé d'avant et `changedValue`, 0 sans lui. Les
 * deux champs sont en lecture seule : un changement de compteur se déclare dans l'écran, qui règle
 * aussi le nombre de chiffres du nouveau compteur.
 *
 * Une modification ne change que `value` : un relevé garde son compteur et son jour, et se supprime
 * s'il a été posé au mauvais endroit. Les relevés d'un compteur inactif ne se créent
 * (`WaterReading::meter.owned`), ne se modifient et ne se suppriment plus (`acceptUpdate()`,
 * `acceptDelete()`, 412).
 */
class WaterReading_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'waterReadingList',
			[
				'?meter' => WaterReading::getValidator('meter'),
				'?dateFrom' => new \FilterValidator('date'),
				'?dateTo' => new \FilterValidator('date')
			]
		);

		$this->read('waterReading/{id}');
		$this->create('waterReading');
		$this->update('waterReading/{id}');
		$this->delete('waterReading/{id}');

		$this->element(
			'toolbox\WaterReading',
			fn() => [
				'id',
				'meter',
				'date',
				'value',
				'changed',
				'changedValue'
			]
		);

	}

	/**
	 * Le relevé du jour après l'écriture, qu'elle l'ait posé ou remplacé : 200 dans les deux cas, la
	 * création ne faisant pas une ressource de plus quand le jour en portait déjà une. Le défaut du
	 * cadriciel le relit par son id, que `WaterReadingLib::create()` pose aussi sur un relevé remplacé.
	 */
	public function pushCreated(\ApiData $data): void {

		parent::pushCreated($data);

		$data->httpCode = 200;

	}

	/**
	 * `acceptUpdate()` et `acceptDelete()` jugent le compteur du relevé : le relevé se lit avec son
	 * statut.
	 */
	public function getSelection(\ModuleModel $m): array {

		$select = parent::getSelection($m);
		$select['meter'] = ['status'];

		return $select;

	}

	public function getList(\ApiData $data): WaterReadingModel {

		return $this->getNewModel($data)
			->whereMeter(fn() => $data->get('meter'), if: $data->exists('meter'))
			->whereDate('>=', fn() => $data->get('dateFrom'), if: $data->exists('dateFrom'))
			->whereDate('<=', fn() => $data->get('dateTo'), if: $data->exists('dateTo'));

	}

	public function getNewModel(\ApiData $data): WaterReadingModel {

		return new WaterReadingModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): WaterReading {

		return new WaterReading([
			'structure' => $data->eStructure
		]);

	}

}
?>
