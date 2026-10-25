<?php
namespace series;

/**
 * L'occupation physique des planches : une ligne par planche et par série (ou par intervention
 * posée hors série). C'est le maillon que ni la ressource series (surface et linéaire totaux, sans
 * dire où) ni la ressource bed (les planches, sans dire ce qu'elles portent) ne rendent.
 *
 * Lecture seule : l'assolement se modifie par les actions de la ressource series (addBeds,
 * removeBeds, bedSize, clearBeds), qui recalculent la surface et le linéaire de la série. Une ligne
 * ne se crée pas isolément, ce qui exclut création, modification et suppression ici.
 *
 * Le fichier ne porte pas le suffixe Ai : le cadriciel dérive le nom de la classe de celui du
 * module exposé (\ApiRoute::expose('series\Place') → series\Place_0_1betaApi), comme il le fait pour
 * un élément ou un modèle.
 */
class Place_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'placeList',
			[
				'season' => Place::getValidator('season'),
				'?series' => Place::getValidator('series'),
				'?task' => Place::getValidator('task'),
				'?bed' => Place::getValidator('bed'),
				'?plot' => Place::getValidator('plot'),
				'?zone' => Place::getValidator('zone')
			]
		);

		$this->read('place/{id}');

		$this->element(
			'series\Place',
			fn() => [
				'id', 'season',
				'series', 'task',
				'zone', 'plot', 'bed',
				'length', 'width', 'area'
			]
		);

	}

	public function getList(\ApiData $data): PlaceModel {

		return $this->getNewModel($data)
			->whereSeason($data->get('season'))
			->whereSeries(fn() => $data->get('series'), if: $data->exists('series'))
			->whereTask(fn() => $data->get('task'), if: $data->exists('task'))
			->whereBed(fn() => $data->get('bed'), if: $data->exists('bed'))
			->wherePlot(fn() => $data->get('plot'), if: $data->exists('plot'))
			->whereZone(fn() => $data->get('zone'), if: $data->exists('zone'));

	}

	public function getNewModel(\ApiData $data): PlaceModel {

		return new PlaceModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Place {

		return new Place([
			'structure' => $data->eStructure
		]);

	}

}
?>
