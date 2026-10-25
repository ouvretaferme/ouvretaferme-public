<?php
new AdaptativeView('/outil/{id@int}', function($data, PanelTemplate $t) {
	return new \work\ToolUi()->display($data->eStructure, $data->eTool);
});

new JsonView('query', function($data, AjaxTemplate $t) {

	$results = $data->cTool->makeArray(fn($eTool) => \work\ToolUi::getAutocomplete($eTool));
	$t->push('results', $results);

});

new JsonView('queryProduct', function($data, AjaxTemplate $t) {

	$results = [];

	if($data->cTool->notEmpty()) {

		$results[] = \work\ToolUi::getAutocompleteTitle(s("Mes produits"));

		foreach($data->cTool as $eTool) {
			$withdrawn = ($eTool['routineName'] === 'phyto') ? \work\RoutineUi::getPhytoWithdrawn($eTool['routineValue']['amm'], $eTool['ePhytoProduct']) : NULL;
			$results[] = \work\ToolUi::getProductAutocomplete($eTool, $withdrawn);
		}

	}

	if($data->cPhytoProduct->notEmpty()) {

		$results[] = \work\ToolUi::getAutocompleteTitle($data->query === '' ? s("Autres produits") : s("Catalogue E-Phy"));

		foreach($data->cPhytoProduct as $ePhytoProduct) {
			$results[] = \work\ToolUi::getCatalogAutocomplete($ePhytoProduct);
		}

		if($data->others > 0) {
			$results[] = \work\ToolUi::getAutocompleteOthers($data->others);
		}

	}

	if($data->create) {
		$results[] = \work\ToolUi::getProductCreateAutocomplete($data->query);
	}

	$t->push('results', $results);

});

new JsonView('queryPhyto', function($data, AjaxTemplate $t) {

	$results = $data->cPhytoProduct->makeArray(fn($ePhytoProduct) => \work\RoutineUi::getPhytoAutocomplete($ePhytoProduct));

	if($data->query !== '') {
		$results[] = \work\RoutineUi::getPhytoManualAutocomplete($data->query);
	}

	$t->push('results', $results);

});

new JsonView('getPhytoField', function($data, AjaxTemplate $t) {

	$t->push('field', new \work\RoutineUi()->getFieldPhyto(new \util\FormUi(), $data->eTool));

});

new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->title = ($data->routineName ? \work\RoutineUi::getProperty($data->routineName, 'pageTitle')($data->eStructure) : s("Le matériel de {value}", $data->eStructure['name']));
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = new \work\ToolUi()->getManageTitle($data->eStructure, $data->routineName, $data->tools, $data->cTool, $data->search);
	echo new \work\ToolUi()->getManage($data->eStructure, $data->routineName, $data->tools, $data->cTool, $data->eToolNew, $data->cActionUsed, $data->search);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \work\ToolUi()->create($data->e);
});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	if(Route::getRequestedOrigin() === 'panel') {
		$t->js()->moveHistory(-1);
	} else {
		$t->ajaxReloadLayer();
	}

	$t->js()->success('work', 'Tool::created');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \work\ToolUi()->update($data->e, $data->routines);

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->moveHistory(-1);

});

new JsonView('doRefusePhyto', function($data, AjaxTemplate $t) {

	$t->qs('#treatment-reprise')->remove();

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('work', 'Tool::deleted');
	$t->ajaxReloadLayer();

});

new AdaptativeView('getRoutinesField', function($data, AjaxTemplate $t) {

	if($data->routines === []) {
		$t->push('field', '');
	} else {
		$t->push('field', new \work\RoutineUi()->getFields(array_keys($data->routines), $data->eTool));
	}


});
?>
