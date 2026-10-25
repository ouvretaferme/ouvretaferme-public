<?php
new JsonView('index', function($data, AjaxTemplate $t) {

	$t->qs('[data-dropdown-id="structure-selector-list"]')->innerHtml(
		new \structure\SelectorAiUi()->get($data->eStructure, $data->cStructureOwn, $data->cStructureCommunity, $data->cStructureRecent, $data->home, $data->search)
	);

});
?>
