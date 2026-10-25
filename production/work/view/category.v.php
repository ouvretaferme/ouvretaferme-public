<?php
new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->title = s("Les catégories d'interventions de {value}", $data->eStructure['name']);
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = new \work\CategoryUi()->getManageTitle($data->eStructure, $data->cCategory);
	echo new \work\CategoryUi()->getManage($data->eStructure, $data->cCategory);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \work\CategoryUi()->create($data->eStructure);

});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->moveHistory(-1);
	$t->js()->success('work', 'Category::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \work\CategoryUi()->update($data->e);

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Category::updated');
	$t->js()->moveHistory(-1);

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Category::deleted');
	$t->ajaxReloadLayer();

});
?>
