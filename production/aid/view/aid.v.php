<?php
new AdaptativeView('/ferme/{id}/outils/aides', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::AID;

	$t->title = s("Aides agricoles");

	$uiAid = new \aid\AidUi();

	$t->mainTitle = $uiAid->getHeader($data->eStructure, $data->cFavorite, $data->filters);

	echo $uiAid->get($data->eStructure, $data->filters, $data->cAid, $data->projects, $data->cTag, $data->cAidShown, $data->count, $data->pages, $data->countFrance);

});

new AdaptativeView('/ferme/{id}/outils/aides/{aid@int}', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::AID;

	$t->mainTitle = \main\DesignUi::getPageHeader(\toolbox\ToolUi::getName(\toolbox\Favorite::AID), back: \aid\AidUi::url($data->eStructure, $data->filters));

	if($data->isHidden) {
		$t->title = \toolbox\ToolUi::getName(\toolbox\Favorite::AID);
		echo new \aid\AidUi()->getSheetHidden($data->eStructure, $data->filters, $data->eAid);
	} else {
		$t->title = $data->eAid['name'];
		echo new \aid\AidUi()->getSheet($data->eStructure, $data->filters, $data->eAid);
	}

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \aid\AidUi::getSuggestions($data->eStructure, $data->filters, $data->query, $data->suggestions));

});

new JsonView('departments', function($data, AjaxTemplate $t) {

	$t->push('results', \aid\AidUi::getDepartments($data->eStructure, $data->filters, $data->departments));

});
?>
