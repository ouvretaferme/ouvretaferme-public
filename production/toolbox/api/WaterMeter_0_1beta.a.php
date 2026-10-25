<?php
namespace toolbox;

/**
 * Les compteurs d'eau d'une structure. Un compteur porte un nom, le nombre de chiffres qu'il affiche
 * pour les m³ entiers (`digits`), et ses relevés (`waterReading`). Il existe avant le premier : on
 * le crée, puis on le relève.
 *
 * `digits` n'est pas une décoration : c'est lui qui dit ce qu'un compteur revenu à une faible valeur
 * a compté en repassant par zéro, et qui borne un relevé (`WaterReading::value.digits`). Il ne
 * descend pas sous le plus haut relevé du compteur (`WaterMeter::digits.readings`).
 *
 * Pas de route de suppression, comme pour un pluviomètre (`Gauge_0_1betaApi`) : un compteur supprimé
 * garde ses relevés en base, il passe seulement inactif. La suppression s'écrit donc
 * `PUT waterMeter/{id}` avec `{"properties": ["status"], "status": "inactive"}`. Le chemin inverse
 * est refusé (`status.deleted`), et un compteur inactif ne se modifie plus du tout (`acceptUpdate()`,
 * 412). Un corps sans `properties` doit porter `name`, `digits` et `status`, dont la seule valeur
 * acceptée est `inactive` : il supprime le compteur en même temps qu'il le règle, ce qui n'est un
 * geste du produit dans aucun écran.
 *
 * Deux compteurs actifs ne portent pas le même nom (`WaterMeter::name.duplicate`) ; le nom d'un
 * compteur inactif redevient libre.
 */
class WaterMeter_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'waterMeterList',
			[
				'?status' => WaterMeter::getValidator('status')
			]
		);

		$this->read('waterMeter/{id}');
		$this->create('waterMeter');
		$this->update('waterMeter/{id}');

		$this->element(
			'toolbox\WaterMeter',
			fn() => [
				'id',
				'name',
				'digits',
				'status'
			]
		);

	}

	public function getList(\ApiData $data): WaterMeterModel {

		return $this->getNewModel($data)
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): WaterMeterModel {

		return new WaterMeterModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): WaterMeter {

		return new WaterMeter([
			'structure' => $data->eStructure
		]);

	}

}
?>
