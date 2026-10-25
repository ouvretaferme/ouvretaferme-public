<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \map\DecorBuildingUi()->create($data->e, $data->choices, $data->bake);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \map\DecorBuildingUi()->update($data->e, $data->choices, $data->bake);

});

// La scène ouverte derrière le panneau prend le bâtiment enregistré, et le hangar personnalisé qu'il remplace
new AdaptativeView('doCreate', function($data, AjaxTemplate $t) {

	$decor = $data->e['eDecor']->empty() ? NULL : $data->e['eDecor']['id'];

	$t->js()->closePanel('#panel-map-decor-building-create');
	$t->js()->eval('MapSceneBuilding.saved('.json_encode($data->building).', '.json_encode($decor).')');

});

new AdaptativeView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-map-decor-building-update');
	$t->js()->eval('MapSceneBuilding.saved('.json_encode($data->building).', null)');

});

// La poubelle est dans la feuille de pose de la scène, et `sceneBuilding.js` ne vient qu'avec l'éditeur : la réponse s'adresse
// à la scène
new AdaptativeView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapScene.current?.decor?.deleted('.json_encode($data->e['id']).')');

});
?>
