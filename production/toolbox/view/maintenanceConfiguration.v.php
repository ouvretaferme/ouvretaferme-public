<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\MaintenanceConfigurationUi()->create($data->e);

});
?>
