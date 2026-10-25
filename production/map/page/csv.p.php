<?php
new \structure\StructurePage()
	->read('exportPlan', function($data) {

		$data->e->validate('canAnalyzeProduction', 'canPersonalData');

		$year = GET('year', 'int');

		$export = \map\CsvLib::getExportPlan($data->e, $year);
		array_unshift($export, new \map\CsvUi()->getExportPlanHeader());

		throw new CsvAction($export, 'plan-'.$year.'.csv');

	});
?>
