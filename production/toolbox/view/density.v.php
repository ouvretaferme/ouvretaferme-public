<?php
new AdaptativeView('/ferme/{id}/outils/densites', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::DENSITY;

	$t->title = s("Densités");

	$uiDensity = new \toolbox\DensityUi();

	$t->mainTitle = $uiDensity->getHeader($data->eStructure, $data->cFavorite);

	if($data->start) {
		echo $uiDensity->getStart($data->eStructure);
	} else {
		echo $uiDensity->get($data->eStructure, $data->eSeries, $data->eCultivation, $data->plan);
	}

});

new JsonView('calculate', function($data, AjaxTemplate $t) {

	$t->qs('#density-result')->outerHtml(new \toolbox\DensityUi()->getResult($data->plan));

});
?>
