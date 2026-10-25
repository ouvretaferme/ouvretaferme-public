<?php
new \series\CultivationPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eSeries = \series\SeriesLib::getById(INPUT('series'))->validate('canWrite');

	})
	->post('addPlant', function($data) {

		$data->ePlant = \plant\PlantLib::getById(POST('plant'));
		$data->cAction = \work\ActionLib::getByStructure($data->eSeries['structure'], fqn: [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION], index: 'fqn');

		$data->eCultivation = \series\CultivationLib::getNew($data->eSeries, $data->ePlant);

		throw new \ViewAction($data);

	})
	->getCreateElement(function($data) {

		return new \series\Cultivation([
			'structure' => $data->eSeries['structure'],
			'series' => $data->eSeries
		]);

	})
	->create()
	->doCreate(fn() => throw new ReloadAction());

new \series\CultivationPage()
	->applyElement(function($data, \series\Cultivation $e) {

		$e->validate('canWrite');

	})
	->quick(['yieldExpected', 'seedlingSeeds'])
	->write('doUpdateHarvestPeriod', function($data) {

		$fw = new FailWatch();

		\series\CultivationLib::updateHarvestPeriodExpected($data->e, POST('first', 'int'), POST('last', 'int'));

		$fw->validate();

		throw new ReloadAction();

	})
	->read('harvest', function($data) {

		$data->cTask = \series\TaskLib::getHarvestedByCultivation($data->e);
		\series\CultivationLib::fillSliceStats($data->e);

		throw new ViewAction($data);

	})
	->read('changePlant', function($data) {

		$data->ePlant = \plant\PlantLib::getById(POST('plant'));

		if($data->ePlant->notEmpty()) {

			$data->ePlant->validate('canRead');

			// On récupère les répartitions actuelles des variétés si l'espèce recherchée est celle actuellement utilisée
			if($data->ePlant['id'] === $data->e['plant']['id']) {
				$data->cSlice = $data->e['cSlice'];
			} else {
				$data->cSlice = new Collection();
			}

			$data->e['cTray'] = \work\ToolLib::getTraysByStructure($data->e['structure']);

			$data->e['plant'] = $data->ePlant;

		} else {

			$data->cSlice = new Collection();

			$data->e['cTray'] = new Collection();

		}

		throw new \ViewAction($data);

	}, method: 'post')
	->update(function($data) {

		$data->e['cTray'] = \work\ToolLib::getTraysByStructure($data->e['structure']);

		$data->cAction = \work\ActionLib::getByStructure($data->e['structure'], fqn: [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION], index: 'fqn');

		$cTaskImplantation = \series\TaskLib::getImplantationBySeries($data->e['series'], $data->cAction);

		$data->implantation = [];

		foreach($data->cAction as $fqn => $eAction) {
			$data->implantation[$fqn] = [
				'writable' => \series\TaskLib::acceptImplantationUpdate($cTaskImplantation, $fqn),
				'weeks' => []
			];
		}

		foreach($cTaskImplantation as $eTask) {
			$data->implantation[$eTask['action']['fqn']]['weeks'][] = [
				'week' => $eTask['doneWeek'] ?? $eTask['plannedWeek'],
				'done' => ($eTask['status'] === \series\Task::DONE)
			];
		}

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new ReloadAction(highlight: ['#cultivation-item-'.$data->e['id']]))
	->doDelete(function($data) {
		throw new ReloadAction('series', 'Cultivation::deleted');
	});
?>
