<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \structure\SectionUi()->create($data->eStructure, $data->e);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \structure\SectionUi()->update($data->eStructure, $data->e);
});

new AdaptativeView('compose', function($data, PanelTemplate $t) {
	return new \structure\SectionUi()->compose($data->eStructure, $data->e);
});
?>
