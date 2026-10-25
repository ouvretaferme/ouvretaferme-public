<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\MaintenanceUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\MaintenanceUi()->update($data->e);

});
?>
