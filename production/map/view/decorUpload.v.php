<?php
new AdaptativeView('doPreview', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapSceneBuilding.previewed('.json_encode($data->upload).')');

});

// Le bâtiment créé entre dans la rangée et le panneau se ferme, comme après un bâtiment composé enregistré : la vignette
// qui s'éclaire dit où il est arrivé, sans message
new AdaptativeView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-map-decor-building-create');
	$t->js()->eval('MapSceneBuilding.saved('.json_encode($data->building).', '.json_encode($data->decor).')');

});
?>
