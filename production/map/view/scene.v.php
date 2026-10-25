<?php
new AdaptativeView('scene', function($data, StructureTemplate $t) {

	$uiPlan = new \map\PlanUi();

	$t->title = s("Plan de {value}", $data->eStructure['name']);
	$t->canonical = \map\PlanUi::url($data->eStructure, $data->season, \map\PlanUi::VIEW_SCENE);

	$t->nav = 'cultivation';
	$t->subNav = 'map';

	$t->mainTitle = $uiPlan->getTitle($data->eStructure, $data->season, \map\PlanUi::VIEW_SCENE);

	echo $uiPlan->getViews($data->eStructure, $data->season, \map\PlanUi::VIEW_SCENE);
	echo new \map\SceneUi()->get($data->eStructure, $data->eUserOnline, $data->season, $data->scene, $data->zones, $data->bake, $data->week, $data->weeks);

});
?>
