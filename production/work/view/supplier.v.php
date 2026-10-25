<?php
new JsonView('query', function($data, AjaxTemplate $t) {

	$results = $data->cSupplier->makeArray(fn($eSupplier) => \work\SupplierUi::getAutocomplete($eSupplier));
	$t->push('results', $results);

});

new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->title = s("Les fournisseurs de {value}", $data->eStructure['name']);
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = new \work\SupplierUi()->getManageTitle($data->eStructure);
	echo new \work\SupplierUi()->getManage($data->eStructure, $data->cSupplier);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \work\SupplierUi()->create($data->e);
});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	if(Route::getRequestedOrigin() === 'panel') {
		$t->js()->moveHistory(-1);
	} else {
		$t->ajaxReloadLayer();
	}

	$t->js()->success('work', 'Supplier::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \work\SupplierUi()->update($data->e);

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->moveHistory(-1);

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Supplier::deleted');
	$t->ajaxReloadLayer();

});
?>
