<?php
namespace toolbox;

/**
 * Les pluviomètres d'une structure. Un pluviomètre est un emplacement, pas un appareil : il est posé
 * sur une zone du parcellaire, ou sur la ferme entière quand la pluie n'est pas relevée parcelle par
 * parcelle, et c'est lui qui porte les relevés (`Rain`). Un lieu ne reçoit qu'un pluviomètre actif à
 * la fois (`Gauge::properties()`, étape `placed`).
 *
 * Pas de route de suppression, et ce n'est pas un oubli : un pluviomètre retiré garde ses relevés en
 * base, il passe seulement inactif. La suppression s'écrit donc `PUT gauge/{id}` avec `status` à
 * `inactive`, comme la roue crantée de l'écran. Le chemin inverse est refusé par l'élément
 * (`status.deleted`), et un pluviomètre inactif ne se modifie plus du tout (`acceptUpdate()`) :
 * en reposer un au même endroit en crée un neuf, qui repart de zéro.
 *
 * Une modification porte donc presque toujours `properties`, la liste des propriétés à écrire :
 * `{"properties": ["zone"], "zone": 12}` déplace, `{"properties": ["status"], "status": "inactive"}`
 * retire. Un corps sans `properties` doit porter les deux propriétés, `status` compris, dont la seule
 * valeur acceptée est `inactive` : il retire le pluviomètre en même temps qu'il le déplace, ce qui
 * n'est un geste du produit dans aucun écran.
 */
class Gauge_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'gaugeList',
			[
				'?status' => Gauge::getValidator('status'),
				'?zone' => Gauge::getValidator('zone')
			]
		);

		$this->read('gauge/{id}');
		$this->create('gauge');
		$this->update('gauge/{id}');

		$this->element(
			'toolbox\Gauge',
			fn() => [
				'id',
				'zone',
				'status'
			],
			[
				/**
				 * Le pluviomètre de la ferme entière n'a pas de zone, et sort donc en `null`. Le même
				 * `null` doit pouvoir rentrer, sans quoi ce pluviomètre-là ne se poserait pas par l'API :
				 * l'élément distingue « la ferme » (chaîne vide) de « aucun lieu choisi » (rien posté,
				 * étape `chosen`), alors qu'un corps de requête porte toutes les propriétés de la liste
				 * ou tombe en `zone.missing`. La distinction est donc déjà faite quand la valeur arrive
				 * ici, et `null` ne peut plus vouloir dire que la ferme.
				 */
				'zone' => fn(\ApiData $data, mixed $value, string $way) => match($way) {
					'out' => $value['zone']->empty() ? NULL : $value['zone']['id'],
					'in' => $value ?? ''
				}
			]
		);

	}

	public function getList(\ApiData $data): GaugeModel {

		return $this->getNewModel($data)
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'))
			->whereZone(fn() => $data->get('zone'), if: $data->exists('zone'));

	}

	public function getNewModel(\ApiData $data): GaugeModel {

		return new GaugeModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Gauge {

		return new Gauge([
			'structure' => $data->eStructure
		]);

	}

}
?>
