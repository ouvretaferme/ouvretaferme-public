<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \note\NotebookUi()->create($data->e);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \note\NotebookUi()->update($data->e);
});
?>
