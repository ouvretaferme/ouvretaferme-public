<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\EquipmentReadingUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\EquipmentReadingUi()->update($data->e);

});

new JsonView('confirm', function($data, AjaxTemplate $t) {

	$t->package('toolbox')->confirmReading(new \toolbox\EquipmentReadingUi()->getConfirm($data->eEquipmentReading));

});
?>
