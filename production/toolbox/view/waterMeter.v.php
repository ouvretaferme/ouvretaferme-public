<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\WaterMeterUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\WaterMeterUi()->update($data->e);

});

new AdaptativeView('change', function($data, PanelTemplate $t) {

	return new \toolbox\WaterReadingUi()->createChange($data->e, $data->eWaterReading);

});
?>
