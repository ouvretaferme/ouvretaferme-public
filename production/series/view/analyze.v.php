<?php
new AdaptativeView('tasks', function($data, StructureTemplate $t) {

	$t->title = s("Liste des interventions");
	$t->nav = 'planning';
	$t->subNav = $data->eStructure->getView('viewPlanning');

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Liste des interventions"));

	echo new \series\AnalyzeUi()->getTasks($data->e, $data->cTask, $data->cUserStructure, $data->pages, $data->page, $data->search);
});
?>
