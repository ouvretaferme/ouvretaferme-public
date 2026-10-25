<?php
namespace series;

/**
 * Ce qui a été récolté : une ligne par intervention de récolte et par jour, avec sa quantité et son
 * unité. C'est le détail que la mise en culture ne donne pas — elle porte le cumul de la saison
 * (harvestedByUnit) — et que l'intervention ne donne pas non plus, une récolte étalée sur plusieurs
 * jours produisant plusieurs lignes pour une même intervention.
 *
 * Lecture seule, et la raison n'est pas que la ligne serait difficile à construire : c'est qu'elle
 * ne porte rien. La quantité récoltée vit sur l'intervention, dans Task.harvest, et c'est elle que
 * TaskLib::recalculateHarvest() agrège pour les cumuls de la culture — cette table n'entre pas dans
 * le calcul. Elle est le journal de ces quantités : TaskLib::update() y ajoute une ligne datée à
 * chaque fois que `harvest` change, ce qui distingue deux passages sur la même intervention. Une
 * ligne écrite ici ne récolterait donc rien, ni sur l'intervention ni sur la culture : elle
 * daterait un mouvement qui n'a pas eu lieu.
 *
 * La période est obligatoire, comme la saison l'est sur placeList : 105 605 lignes en base de
 * développement, et le tri par identifiant qu'impose la pagination fait balayer la clé primaire dès
 * qu'aucune borne de date ne cadre la requête. Avec elle, l'index (structure, date) rend `range`, et
 * les autres filtres ne s'appliquent plus qu'à ce qu'elle a déjà réduit.
 *
 * Le fichier ne porte pas le suffixe Ai : le cadriciel dérive le nom de la classe de celui du module
 * exposé (\ApiRoute::expose('series\Harvest') → series\Harvest_0_1betaApi).
 */
class Harvest_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'harvestList',
			[
				'dateFrom' => new \FilterValidator('date'),
				'dateTo' => new \FilterValidator('date'),
				'?task' => Harvest::getValidator('task'),
				'?series' => Harvest::getValidator('series'),
				'?cultivation' => Harvest::getValidator('cultivation')
			]
		);

		$this->read('harvest/{id}');

		$this->element(
			'series\Harvest',
			fn() => [
				'id',
				'task', 'series', 'cultivation',
				'quantity', 'unit',
				'date', 'week'
			]
		);

	}

	public function getList(\ApiData $data): HarvestModel {

		return $this->getNewModel($data)
			->whereDate('>=', $data->get('dateFrom'))
			->whereDate('<=', $data->get('dateTo'))
			->whereTask(fn() => $data->get('task'), if: $data->exists('task'))
			->whereSeries(fn() => $data->get('series'), if: $data->exists('series'))
			->whereCultivation(fn() => $data->get('cultivation'), if: $data->exists('cultivation'));

	}

	public function getNewModel(\ApiData $data): HarvestModel {

		return new HarvestModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Harvest {

		return new Harvest([
			'structure' => $data->eStructure
		]);

	}

}
?>
