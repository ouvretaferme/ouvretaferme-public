<?php

new AdaptativeView('plant', function($data, StructureTemplate $t) {

	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->title = s("Les espèces de {value}", $data->eStructure['name']);
	$t->canonical = \plant\PlantUi::urlManage($data->eStructure);

	$create = '';

	if($data->eStructure->canManage()) {
		$create = '<a href="/plant/plant:create?structure='.$data->eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvelle espèce").'</span></a>';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Espèces"),
		back: \structure\StructureUi::urlSettingsProduction($data->eStructure),
		tools: '<a '.attr('onclick', 'Lime.Search.toggle("#plant-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>',
		create: $create
	);

	echo new \plant\PlantUi()->getTabs($data->eStructure, $data->plants, $data->search);
	echo new \plant\PlantUi()->getSearch($data->eStructure, $data->search);
	echo new \plant\PlantUi()->getManage($data->eStructure, $data->cPlant);


});

new AdaptativeView('/espece/{id@int}', function($data, PanelTemplate $t) {

	return new \plant\PlantUi()->display($data->e, $data->eStructure, $data->ccCrop, $data->cActionMain, $data->search, $data->cNoteLinked);

});

new AdaptativeView('analyzeTime', function($data, PanelTemplate $t) {
	return new \series\AnalyzeUi()->getPlantTime($data->e, $data->year, $data->cPlantTimesheet, $data->cTimesheetByAction, $data->cTimesheetByUser, $data->cPlantMonth, $data->cPlantMonthBefore, $data->cPlantWeek, $data->cPlantWeekBefore);
});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \plant\PlantUi()->create($data->eStructure, $data->cFamily);
});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	if(Route::getRequestedOrigin() === 'panel') {
		$t->js()->moveHistory(-1);
	} else {
		$t->ajaxReloadLayer();
	}

	$t->js()->success('plant', 'Plant::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \plant\PlantUi()->update($data->e);
});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {
	$t->js()->moveHistory(-1);
});

new JsonView('doUpdateStatus', function($data, AjaxTemplate $t) {
	$t->js()->success('plant', 'Plant::updated'.ucfirst($data->e['status']));
	$t->qs('#plant-switch-'.$data->e['id'])->toggleSwitch('post-status', [\plant\Plant::ACTIVE, \plant\Plant::INACTIVE]);
});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('plant', 'Plant::deleted');
	$t->ajaxReloadLayer();

});
?>
