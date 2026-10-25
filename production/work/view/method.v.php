<?php
new JsonView('query', function($data, AjaxTemplate $t) {

	$results = $data->cMethod->makeArray(fn($eMethod) => \work\MethodUi::getAutocomplete($eMethod));
	$t->push('results', $results);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \work\MethodUi()->create($data->e);

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Method::deleted');
	$t->ajaxReloadLayer();

});
?>
