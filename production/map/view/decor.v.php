<?php
// Le client garde l'id de chaque élément qu'il pose, pour ses gestes suivants et pour défaire la séance
new JsonView('doCreate', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
});

new JsonView('doUpdatePosition', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
});

new JsonView('doUpdateEnclosure', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
});

new JsonView('doUpdatePipe', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
});

// Les ids dans l'ordre où la scène a posté les éléments du décor par défaut, qu'elle reprend un par un
new JsonView('doAdopt', function($data, AjaxTemplate $t) {
	$t->push('ids', array_values($data->c->getColumn('id')));
});

new JsonView('doDelete', function($data, AjaxTemplate $t) {
	$t->push('id', $data->e['id']);
});
?>
