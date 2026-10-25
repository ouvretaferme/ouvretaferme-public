<?php
new JsonView('index', function($data, AjaxTemplate $t) {

	$t->qs('#series-selector')->outerHtml(
		new \series\SeriesUi()->getSelector($data->eStructure, $data->ccCultivation)
	);

	$t->js()->eval('SeriesSelector.restore()');

});
?>
