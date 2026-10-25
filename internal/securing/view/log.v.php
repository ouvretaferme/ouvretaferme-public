<?php
new AdaptativeView('/historique', function($data, DynamicTemplate $t) {

	if($t instanceof StructureTemplate) {
		$t->nav = 'structure';
		$t->subNav = 'general';
	}

	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
		$t->subNav = 'general';
	}

	$t->title = s("Activité de {value}", $data->eStructure['name']);
	$t->canonical = '/historique';

	$t->mainTitle = new \securing\LogUi()->getTitle($data->eStructure);

	echo new \securing\LogUi()->getList($data->cLog, $data->page, $data->nLog);

});
?>
