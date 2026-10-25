<?php
new \structure\StructurePage(function($data) {

		$data->week = GET('week', fn($value) => Filter::check('?week', $value));

	})
	->remote('getWeeklyPdf', 'selling', function($data) {

		$data->cAction = \work\ActionLib::getByStructure($data->e, index: 'id');
		\work\ActionLib::saveMain($data->cAction);

		\series\RepeatLib::createForWeek($data->e, $data->week);

		$data->ccTask = \series\TaskLib::getForExport($data->e, $data->week, $data->cAction);
		$data->ccTaskDelayed = \series\TaskLib::getForExportDelayed($data->e, $data->week, $data->cAction);

		throw new ViewAction($data);

	})
	->read('downloadWeeklyPdf', function($data) {

		$filename = 'Planning '.$data->week.'.pdf';
		$content = \main\PdfLib::build('/work/planning:getWeeklyPdf?id='.$data->e['id'].'&week='.$data->week, $filename);

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canPlanningLimited']);
?>
