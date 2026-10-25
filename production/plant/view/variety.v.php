<?php
new AdaptativeView('index', function($data, PanelTemplate $t) {
	return new \plant\VarietyUi()->displayByPlant($data->e, $data->ePlant, $data->cVariety, $data->cSupplier);
});

new JsonView('query', function($data, AjaxTemplate $t) {

	$results = [];

	if($data->cVariety->notEmpty()) {

		$results[] = \plant\VarietyUi::getAutocompleteTitle(s("Mes variétés"));

		foreach($data->cVariety as $eVariety) {
			$results[] = \plant\VarietyUi::getAutocomplete($eVariety);
		}

	}

	if($data->cVarietyReference->notEmpty()) {

		$results[] = \plant\VarietyUi::getAutocompleteTitle(s("Bibliothèque de variétés"));

		foreach($data->cVarietyReference as $eVariety) {
			$results[] = \plant\VarietyUi::getAutocomplete($eVariety);
		}

	}

	// Un nom tapé qui n'existe pas encore se valide depuis la liste : sur mobile, la liste occupe
	// tout l'écran et rien ne permet d'en sortir sans choisir un élément.
	if($data->search !== '') {

		$exists = (
			$data->cVariety->contains(fn($eVariety) => mb_strtolower($eVariety['name']) === mb_strtolower($data->search)) or $data->cVarietyReference->contains(fn($eVariety) => mb_strtolower($eVariety['name']) === mb_strtolower($data->search))
		);

		if($exists === FALSE) {
			$results[] = \plant\VarietyUi::getAutocompleteValidate($data->search);
		}

	}

	$t->push('results', $results);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \plant\VarietyUi()->create($data->e);
});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	if(Route::getRequestedOrigin() === 'panel') {
		$t->js()->moveHistory(-1);
	} else {
		$t->ajaxReloadLayer();
	}

	$t->js()->success('plant', 'Variety::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \plant\VarietyUi()->update($data->e);
});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {
	$t->js()->moveHistory(-1);
});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('plant', 'Variety::deleted');
	$t->ajaxReloadLayer();

});
?>
