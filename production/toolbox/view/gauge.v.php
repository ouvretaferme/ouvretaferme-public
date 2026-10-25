<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\GaugeUi()->create($data->e, $data->cZoneFree);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\GaugeUi()->update($data->e, $data->cZoneFree);

});

new AdaptativeView('import', function($data, PanelTemplate $t) {

	return new \toolbox\GaugeUi()->import($data->e, $data->import);

});

// L'aperçu repeint le même panneau, depuis le fichier posté : `PanelTemplate` n'ouvre un panneau que sur GET.
new JsonView('doUpload', function($data, AjaxTemplate $t) {
	$t->pushPanel(new \toolbox\GaugeUi()->import($data->e, $data->import));
});
?>
