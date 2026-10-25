<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \hr\PresenceUi()->create($data->eStructure, $data->e, $data->eContract);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \hr\PresenceUi()->update($data->e, $data->eContract);
});
?>
