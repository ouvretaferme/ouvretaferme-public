<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \hr\TeammateUi()->create($data->eStructure, $data->e, $data->ePresence, $data->eContract);
});
?>
