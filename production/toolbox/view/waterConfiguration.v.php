<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\WaterConfigurationUi()->create($data->e);

});
?>
