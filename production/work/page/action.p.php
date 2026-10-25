<?php
new \work\ActionPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \work\Action([
			'structure' => $data->eStructure,
		]);

	})
	->create(function($data) {

		$data->cCategory = \work\CategoryLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	->doCreate(fn($data) => throw new ViewAction($data));

new \work\ActionPage()
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ReloadAction('work', 'Action::updated'.ucfirst($data->e['status'])), validate: ['canUpdate', 'isFree'])
	->doUpdateProperties('doUpdateFavorite', ['favorite'], fn($data) => throw new ReloadAction(), validate: ['canUpdate'])
	->doDelete(fn($data) => throw new ViewAction($data));

new \work\ActionPage()
	->applyElement(function($data, \work\Action $e) {

		$e->validate('canWrite');

		$data->eStructure = $e['structure'];

		\structure\Structure::model()
			->select('status', 'name')
			->get($data->eStructure);

		$e['cCategory'] = \work\CategoryLib::getByStructure($data->eStructure);

		$data->eStructure->validate('active');

	})
	->update(function($data) {
		throw new ViewAction($data);
	})
	->doUpdate(fn($data) => throw new ViewAction($data));

new Page()
	->get('analyzeTime', function($data) {

		$data->year = GET('year', 'int', \Clock::year());

		$data->eCategory = \work\CategoryLib::getById(GET('category'))->validate();
		$data->eCategory['structure']->validate('canAnalyzeProduction');

		$data->eAction = \work\ActionLib::getById(GET('action'));

		if($data->eAction->notEmpty()) {
			$data->eAction->validateProperty('structure' , $data->eCategory['structure']);
		}

		$data->cTimesheetTarget = \work\AnalyzeLib::getActionTimesheet($data->eAction, $data->eCategory, $data->year);
		[$data->cTimesheetMonth, $data->cTimesheetUser] = \work\AnalyzeLib::getActionMonths($data->eAction, $data->eCategory, $data->year);
		[$data->cTimesheetMonthBefore] = \work\AnalyzeLib::getActionMonths($data->eAction, $data->eCategory, $data->year - 1);
		
		[$data->cTimesheetWeek] = \work\AnalyzeLib::getActionWeeks($data->eAction, $data->eCategory, $data->year);
		[$data->cTimesheetWeekBefore] = \work\AnalyzeLib::getActionWeeks($data->eAction, $data->eCategory, $data->year - 1);
		$data->cMethod = $data->eAction->notEmpty() ? \work\AnalyzeLib::getActionMethods($data->eAction, $data->eCategory, $data->year) : new \Collection();

		throw new ViewAction($data);

	})
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canManage');


		$data->actions = \work\ActionLib::countByStructure($data->eStructure);

		if(
			get_exists('status') and
			$data->actions[\work\Action::INACTIVE] === 0
		) {
			throw new RedirectAction('/work/action:manage?structure='.$data->eStructure['id']);
		}

		$data->search = new Search([
			'status' => GET('status', default: \work\Action::ACTIVE),
		]);

		$data->cCategory = \work\CategoryLib::getByStructure($data->eStructure, index: 'id');
		$data->cAction = \work\ActionLib::getForManage($data->eStructure, search: $data->search);


		throw new \ViewAction($data);

	});
?>
