<?php
new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->title = s("Les interventions de {value}", $data->eStructure['name']);
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = new \work\ActionUi()->getManageTitle($data->eStructure);
	echo new \work\ActionUi()->getManage($data->eStructure, $data->actions, $data->cAction, $data->cCategory, $data->search);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \work\ActionUi()->create($data->eStructure, $data->cCategory);

});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->moveHistory(-1);
	$t->js()->success('work', 'Action::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \work\ActionUi()->update($data->e);

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Action::updated');
	$t->js()->moveHistory(-1);

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Action::deleted');
	$t->ajaxReloadLayer();

});

new AdaptativeView('analyzeTime', function($data, PanelTemplate $t) {
	return new \work\AnalyzeUi()->getTime($data->eAction, $data->eCategory, $data->year, $data->cTimesheetTarget, $data->cTimesheetMonth, $data->cTimesheetMonthBefore, $data->cTimesheetWeek, $data->cTimesheetWeekBefore, $data->cTimesheetUser, $data->cMethod);
});
?>
