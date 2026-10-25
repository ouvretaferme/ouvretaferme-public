<?php
namespace plant;

class Forecast_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'forecastList',
			[
				'season' => Forecast::getValidator('season'),
			]
		);

		$this->create('forecast');
		$this->read('forecast/{id}');
		$this->update('forecast/{id}');
		$this->delete('forecast/{id}');

		$this->element(
			'plant\Forecast',
			fn() => [
				'id', 'season', 'plant', 'unit', 'quantity', 'proPart', 'proPrice', 'privatePart', 'privatePrice'
			]
		);

	}

	public function getNewModel(\ApiData $data): ForecastModel {

		return new ForecastModel()
			->whereStructure($data->eStructure)
			->whereSeason(fn() => $data->get('season'), if: $data->exists('season'));

	}

	public function getNewElement(\ApiData $data): Forecast {

		return new Forecast([
			'structure' => $data->eStructure
		]);

	}

	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['privatePart', 'proPart'],
			]
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
