<?php
new \structure\StructurePage()
	->read('tasks', function($data) {

		$data->e->validate('canAnalyzeProduction', 'canPersonalData');

		$data->page = GET('page', 'int');

		$data->year = GET('year', 'int');
		$data->month = GET('month', fn($value) => Filter::check(['int', 'min' => 1, 'max' => 12], $value));
		$data->week = GET('week', fn($value) => Filter::check('?week', $value));
		$data->years = \series\AnalyzeLib::getYears($data->e);

		$data->eStructure = $data->e;

		$data->search = new Search([
			'year' => $data->year,
			'month' => $data->month,
			'week' => $data->week,
			'plant' => get_exists('plant') ? \plant\PlantLib::getById(GET('plant'))->validate() : NULL,
			'user' => get_exists('user') ? \user\UserLib::getById(GET('user'))->validate() : new \user\User(),
			'action' => get_exists('action') ? \work\ActionLib::getByStructure($data->eStructure, id: GET('action'))->validate() : NULL,
			'series' => get_exists('series') ? \series\SeriesLib::getById(GET('series'), ['id', 'name'])->validate() : NULL,
			'category' => get_exists('category') ? \work\CategoryLib::getByStructure($data->eStructure, id: GET('category'))->validate() : NULL,
			'status' => \series\Task::GET('status', 'status'),
		]);

		if(get_exists('noSeries')) {
			$data->search->set('series', new \series\Series());
		}

		[$data->cTask, $nTask] = \series\TaskLib::getByStructure($data->e, $data->page, $data->search);
		$data->cUserStructure = \hr\TeammateLib::getUsersForPlanning($data->e);

		\work\ActionLib::getMainByStructure($data->e);

		$data->pages = $nTask / 100;

		throw new ViewAction($data);

	});
?>
