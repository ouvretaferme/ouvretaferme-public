<?php
namespace map;

class Zone_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'zoneList',
			[
				'season' => Zone::getValidator('seasonFirst'),
			]
		);

		$this->create('zone');
		$this->read('zone/{id}');
		$this->update('zone/{id}');
		$this->delete('zone/{id}');

		$this->element(
			'map\Zone',
			fn() => [
				'id', 'name', 'area', 'coordinates', 'seasonFirst', 'seasonLast'
			],
			[
				'coordinates' => fn(\ApiData $data, mixed $value, string $direction) => match($direction) {
					'out' => $value['coordinates'],
					'in' => is_array($value) ? json_encode($value) : $value
				}
			]
		);

	}

	public function getList(\ApiData $data): ZoneModel {

		$mZone = $this->getNewModel($data);

		SeasonLib::whereSeason($mZone, $data->get('season'));

		return $mZone;

	}

	public function getNewModel(\ApiData $data): ZoneModel {

		return new ZoneModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Zone {

		return new Zone([
			'structure' => $data->eStructure
		]);

	}

	/**
	 * Un contour posté donne sa surface : modifier l'un sans l'autre écrirait une surface qui ne lui correspond plus. Sur une
	 * parcelle tracée, `coordinates: null` efface le contour, il ne se poste avec une surface que pour une parcelle non tracée.
	 */
	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['area', 'coordinates'],
			]
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
