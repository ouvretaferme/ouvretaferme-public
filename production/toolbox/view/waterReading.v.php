<?php
new AdaptativeView('/ferme/{id}/outils/eau', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::WATER;

	$t->title = s("Consommation d'eau");

	$uiWaterReading = new \toolbox\WaterReadingUi();

	$t->mainTitle = $uiWaterReading->getHeader($data->eStructure, $data->cFavorite, $data->waters);

	if($data->waters['cWaterMeter']->empty()) {
		echo $uiWaterReading->getStart($data->eStructure);
	} else if($data->waters['cWaterMeter']->count() === 1) {
		echo $uiWaterReading->getOne($data->eStructure, $data->waters['cWaterMeter']->first());
	} else {
		echo $uiWaterReading->getSeveral($data->eStructure, $data->waters, $data->eWaterMeterSelected);
	}

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\WaterReadingUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\WaterReadingUi()->update($data->e);

});
?>
