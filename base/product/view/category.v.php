<?php
new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->title = s("Les catégories de produits de {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlSellingProductsCategories($data->eStructure);

	$t->nav = 'selling';
	$t->subNav = 'product';
	$t->subNavTarget = $t->canonical;

	$t->mainTitle = \product\HeaderUi::getProducts($data->eStructure, \structure\Member::CATEGORY);

	echo new \product\CategoryUi()->getManage($data->eStructure, $data->cCategory);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \product\CategoryUi()->create($data->eStructure);

});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->moveHistory(-1);
	$t->js()->success('product', 'Category::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \product\CategoryUi()->update($data->e);

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->success('product', 'Category::updated');
	$t->js()->moveHistory(-1);

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('product', 'Category::deleted');
	$t->ajaxReloadLayer();

});
?>
