<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \map\DecorObjectUi()->create($data->e, $data->bake);

});

new AdaptativeView('doPreview', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapSceneObject.previewed('.json_encode($data->upload).')');

});

// L'élément créé entre dans sa rangée et le panneau se ferme, comme après un bâtiment importé : la vignette qui s'éclaire
// dit où il est arrivé, sans message
new AdaptativeView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-map-decor-object-create');
	$t->js()->eval('MapSceneObject.saved('.json_encode($data->object).')');

});

// La poubelle est dans la feuille de pose de la scène, et `sceneObject.js` ne vient qu'avec le panneau d'import : la réponse
// s'adresse à la scène
new AdaptativeView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapScene.current?.decor?.objectDeleted('.json_encode($data->e['id']).')');

});
?>
