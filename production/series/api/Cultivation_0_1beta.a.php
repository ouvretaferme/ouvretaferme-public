<?php
namespace series;

class Cultivation_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'seriesCultivationList',
			[
				'?season' => Cultivation::getValidator('season'),
				'?series' => Cultivation::getValidator('series'),
			]
		);

		$this->create('seriesCultivation');
		$this->read('seriesCultivation/{id}');
		$this->update('seriesCultivation/{id}');
		$this->delete('seriesCultivation/{id}');

		$this->element(
			'series\Cultivation',
			fn() => [
				'id',
				'series', 'season', 'plant',
				'distance', 'rows', 'rowSpacing', 'plantSpacing', 'density',
				'varietiesUnit', 'varietiesTool',
				'area', 'areaPermanent', 'length', 'lengthPermanent',
				'seedling', 'seedlingSeeds',
				'harvestedByUnit', 'mainUnit', 'unitWeight', 'bunchWeight', 'yieldExpected',
				'harvestMonths', 'harvestWeeks',
				'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected',
				'varieties'
			],
			[
				'varieties' => fn(\ApiData $data, Cultivation $e) => $data->exportCollection($e['cSlice'])
			]
		);

	}

	public function getList(\ApiData $data): CultivationModel {

		return $this->getNewModel($data)
			->whereSeason(fn() => $data->get('season'), if: $data->exists('season'))
			->whereSeries(fn() => $data->get('series'), if: $data->exists('series'));

	}

	public function getNewModel(\ApiData $data): CultivationModel {

		return new \series\CultivationModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Cultivation {

		return new \series\Cultivation([
			'structure' => $data->eStructure
		]);

	}

	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected'],
				['distance', 'density', 'rows', 'plantSpacing', 'rowSpacing'],
				['plant', 'varietiesUnit', 'varietiesTool', 'varieties'],
			]
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
