<?php
new \plant\ForecastPage(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure', '?int'))
			->validate('canAnalyzeProduction');


	})
	->getCreateElement(function($data) {
		return new \plant\Forecast([
			'structure' => $data->eStructure
		]);
	})
	->create(function($data) {

		$data->e['season'] = GET('season', 'int');

		throw new ViewAction($data);

	})
	->doCreate(fn($data) => throw new ReloadAction('plant', 'Forecast::created'));

new \plant\ForecastPage()
	->quick(['quantity', 'proPrice', 'privatePart', 'privatePrice', 'proPart'])
	->update(function($data) {

		$data->e['nCultivation'] = \plant\ForecastLib::countCultivations($data->e);

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new ReloadAction('plant', 'Forecast::updated'))
	->doDelete(fn($data) => throw new ReloadAction('plant', 'Forecast::deleted'));
?>
