<?php
new AdaptativeView('/ferme/{id}/outils/pluviometrie', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::RAIN;

	$t->title = s("Pluviométrie");

	$uiRain = new \toolbox\RainUi();

	$t->mainTitle = $uiRain->getHeader($data->eStructure, $data->cGauge, $data->cZoneFree, $data->cFavorite);

	if($data->cGauge->empty()) {
		echo $uiRain->getStart($data->eStructure, $data->cZoneFree);
	} else if($data->cGauge->count() === 1) {
		echo $uiRain->getOne($data->eStructure, $data->cGauge->first());
	} else {
		echo $uiRain->getSeveral($data->eStructure, $data->cGauge, $data->eGaugeSelected);
	}

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\RainUi()->create($data->e, $data->cGauge, $data->day);

});

new JsonView('day', function($data, AjaxTemplate $t) {

	$t->qs('#rain-create-amount')->outerHtml(new \toolbox\RainUi()->getAmount($data->e, $data->day));

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\RainUi()->update($data->e);

});
?>
