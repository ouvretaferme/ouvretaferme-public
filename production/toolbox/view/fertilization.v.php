<?php
new AdaptativeView('/ferme/{id}/outils/fertilisation', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::FERTILIZATION;

	$t->title = s("Besoins en fertilisation");

	$uiFertilization = new \toolbox\FertilizationUi();

	$t->mainTitle = $uiFertilization->getHeader($data->eStructure, $data->cFavorite);

	if($data->start) {
		echo $uiFertilization->getStart($data->eStructure);
	} else {
		echo $uiFertilization->get($data->eStructure, $data->cFertilizationCrop, $data->cFertilizationMaterial, $data->cTool, $data->eSeries, $data->eFertilizationCrop, $data->eFertilizationYield, $data->materials, $data->result, $data->tab);
	}

});

new JsonView('calculate', function($data, AjaxTemplate $t) {

	$t->qs('#fertilization-result')->outerHtml(new \toolbox\FertilizationUi()->getResult($data->eSeries, $data->materials, $data->result));

});

new JsonView('crop', function($data, AjaxTemplate $t) {

	$t->qs('#fertilization-crop')->outerHtml(new \toolbox\FertilizationUi()->getCrop(new \util\FormUi(['columns' => 1]), $data->eStructure, $data->eFertilizationCrop, $data->eFertilizationYield));

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', $data->cFertilizationCrop->makeArray(fn($eFertilizationCrop) => \toolbox\FertilizationUi::getCropAutocomplete($eFertilizationCrop)));

});

new AdaptativeView('notice', function($data, PanelTemplate $t) {

	return new \toolbox\FertilizationUi()->getNoticePanel($data->notice, $data->sources);

});

new JsonView('material', function($data, AjaxTemplate $t) {

	$h = new \toolbox\FertilizationUi()->getMaterial(new \util\FormUi(['columns' => 1]), $data->cFertilizationMaterial, $data->cTool, $data->index, $data->material);

	// Un index que l'écran n'a pas encore est un complément ajouté : il prend place en fin de liste.
	if(POST('add', 'bool')) {
		$t->qs('#fertilization-complements')->insertAdjacentHtml('beforeend', $h);
	} else {
		$t->qs('#fertilization-material-'.$data->index)->outerHtml($h);
	}

});
?>
