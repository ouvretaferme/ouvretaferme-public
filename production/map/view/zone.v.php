<?php
new AdaptativeView('doCreateMap', function($data, AjaxTemplate $t) {

	$t->ajaxRedirect($data->url);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \map\ZoneUi()->create($data->eStructure, $data->season, $data->mapCenter);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \map\ZoneUi()->update($data->e, $data->origin);

});

// Une parcelle modifiée depuis la vue Carte change sa liste, la colonne qui la compte et son nom sur la carte. Elle reste désignée
new AdaptativeView('doUpdateMap', function($data, AjaxTemplate $t) {

	foreach(new \map\PlanUi()->getMapZoneNodes($data->eStructure, $data->season, $data->cZone, $data->eZone) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	$t->js()->closePanel('#panel-zone-update');

	$t->js()->eval('MapCartography.get("'.\map\PlanUi::MAP.'").addZone('.$data->eZone['id'].', '.json_encode($data->eZone['name']).', '.json_encode($data->eZone['coordinates']).')');
	$t->js()->eval('MapPlan.showZone('.$data->eZone['id'].')');

	$t->js()->success('map', 'Zone::updated');

});

new AdaptativeView('doUpdateShape', function($data, AjaxTemplate $t) {

	foreach(new \map\PlanUi()->getMapZoneNodes($data->e['structure'], $data->season, $data->cZone, $data->e) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	// Le contour tel qu'il est enregistré, que la carte dessine à la place de celui du tracé
	$t->push('zone', $data->e['id']);
	$t->push('name', $data->e['name']);
	$t->push('coordinates', $data->e['coordinates']);

	$t->js()->success('map', 'Zone::updated');

});
?>
