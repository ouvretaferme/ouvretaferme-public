<?php
namespace map;

class Bed_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'bedList',
			[
				'season' => Bed::getValidator('seasonFirst'),
				'?plot' => Bed::getValidator('plot'),
				'?zone' => Bed::getValidator('zone'),
			]
		);

		$optional = function(\ApiData $data, array $properties) {

			if(array_key_exists('alleyWidth', $data->body) === FALSE) {
				$properties = array_values(array_diff($properties, ['alleyWidth']));
			}

			$l = new \PropertyList($data->e->exists() ? 'update' : 'create', 'api');

			$data->e->build($properties, $this->convertProperties($data, $properties, $l), $l);

		};

		$this->create('bed', $optional);
		$this->read('bed/{id}');
		$this->update('bed/{id}', $optional);
		$this->delete('bed/{id}');

		$this->element(
			'map\Bed',
			fn() => [
				'id', 'zone', 'zoneFill', 'plot', 'plotFill', 'name',
				'length', 'width', 'alleyWidth', 'area',
				'seasonFirst', 'seasonLast'
			]
		);

	}

	public function getList(\ApiData $data): BedModel {

		$mBed = $this->getNewModel($data)
			->wherePlot(fn() => $data->get('plot'), if: $data->exists('plot'))
			->whereZone(fn() => $data->get('zone'), if: $data->exists('zone'));

		SeasonLib::whereSeason($mBed, $data->get('season'));

		return $mBed;

	}

	/**
	 * Les planches de remplissage sont écartées : créées et maintenues par le produit pour porter
	 * une culture posée sur un jardin ou une parcelle entière, elles n'ont ni nom ni dimensions et
	 * ne se gèrent pas une par une. Une planche supprimée reste en base tant qu'elle a été cultivée
	 * (BedLib::delete()) et n'a pas davantage à ressortir ici.
	 */
	public function getNewModel(\ApiData $data): BedModel {

		return new BedModel()
			->whereStructure($data->eStructure)
			->whereStatus(Bed::ACTIVE)
			->whereZoneFill(FALSE)
			->wherePlotFill(FALSE);

	}

	public function getNewElement(\ApiData $data): Bed {

		return new Bed([
			'structure' => $data->eStructure
		]);

	}

}
?>
