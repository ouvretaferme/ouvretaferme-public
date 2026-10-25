<?php
// Le client garde l'id de chaque calque qu'il crée, pour y poser le décor, et le nom tel que le serveur l'a enregistré
new JsonView('doCreate', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
	$t->push('name', $data->e['name']);
});

new JsonView('doUpdateName', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
	$t->push('name', $data->e['name']);
});

// La confirmation dit ce que la suppression emporte, compté en base : les éléments que la scène n'a pas pu monter s'en vont aussi
new JsonView('getDelete', function($data, AjaxTemplate $t) {
	$t->push('decor', new \map\DecorLayerUi()->getDeleteDecor($data->decor));
});

new JsonView('doDelete', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
});
?>
