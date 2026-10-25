<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\StaffUi()->create($data->e, $data->eSupervisor);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\StaffUi()->update($data->e);

});
?>
