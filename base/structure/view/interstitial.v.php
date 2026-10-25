<?php
new AdaptativeView('/ferme/{id}/interstitiel/{name}', function($data, PanelTemplate $t) {

	return new \structure\InterstitialUi()->get($data->name, $data->e);

});
?>
