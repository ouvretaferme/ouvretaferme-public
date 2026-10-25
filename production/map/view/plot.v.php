<?php
// Un jardin modifié sur la Carte y reste désigné, sans ses planches. Leur niveau, s'il était ouvert, porte le nom et le contour
// d'avant : il revient vide, et « Planches » le relira
$renderPlot = function(AjaxTemplate $t, \structure\Structure $eStructure, int $season, \map\Zone $eZone, \map\Plot $ePlot) {

	$uiDraw = new \map\DrawUi();

	$t->qs('#'.\map\DrawUi::LEVEL)->outerHtml($uiDraw->getLevel($eStructure, $season, $eZone, new \map\Plot()));
	$t->qs('#'.\map\DrawUi::CARD)->outerHtml($uiDraw->getCard($eStructure, $season, $eZone, new \map\Plot()));

	$t->js()->eval('MapPlan.showPlot('.$ePlot['id'].', '.$eZone['id'].')');

};

// Le troisième niveau du jardin, ses planches ouvertes, et l'adresse qui les rouvre au rechargement
$renderBeds = function(AjaxTemplate $t, \structure\Structure $eStructure, int $season, \map\Zone $eZone, \map\Plot $ePlot) {

	$uiDraw = new \map\DrawUi();

	$t->qs('#'.\map\DrawUi::LEVEL)->outerHtml($uiDraw->getLevel($eStructure, $season, $eZone, $ePlot));
	$t->qs('#'.\map\DrawUi::CARD)->outerHtml($uiDraw->getCard($eStructure, $season, $eZone, $ePlot));

	$t->js()->eval('MapPlan.showDrawing(true)');

};

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \map\PlotUi()->create($data->e, $data->season, $data->eZone);

});

new AdaptativeView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->eZone['id'].')');
	$t->js()->closePanel('#panel-plot-create');
	$t->js()->success('map', $data->success);

});

// Un jardin dessiné sur la carte change sa parcelle, qui le compte, et la structure. Ses actions n'ont pas encore leur enveloppe dans la page
new AdaptativeView('doCreateMap', function($data, AjaxTemplate $t) {

	$uiPlan = new \map\PlanUi();

	foreach($uiPlan->getMapZoneNodes($data->eStructure, $data->season, $data->cZone, $data->eZone) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	$t->qs('.plan-mapview')->insertAdjacentHtml('beforeend', $uiPlan->getMapPlotActions($data->eStructure, $data->season, $data->eZone, $data->ePlot));

	$t->js()->eval('MapPlan.showPlot('.$data->ePlot['id'].', '.$data->eZone['id'].')');

	// Le contour tel qu'il est enregistré, que la carte dessine à la place de celui du tracé
	$t->push('plot', $data->ePlot['id']);
	$t->push('zone', $data->eZone['id']);
	$t->push('name', $data->ePlot['name']);
	$t->push('mode', $data->ePlot['mode']);
	$t->push('coordinates', $data->ePlot['coordinates']);
	$t->push('undrawn', $data->ePlot['bedsUndrawn']);

	$t->js()->success('map', $data->success);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \map\PlotUi()->update($data->e, $data->origin, $data->season, $data->eZone, $data->ePlot);

});

new AdaptativeView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');
	$t->js()->closePanel('#panel-plot-update');
	$t->js()->success('map', $data->success);

});

// Un jardin modifié depuis la vue Carte change sa parcelle, qui le liste, et son nom sur la carte. Il reste désigné, sans ses planches.
// Un contour déplacé dans le panneau a déplacé ses traits avec lui (PlotLib::update()) : la carte les relit
new AdaptativeView('doUpdateMap', function($data, AjaxTemplate $t) use($renderPlot) {

	foreach(new \map\PlanUi()->getMapZoneNodes($data->eStructure, $data->season, $data->cZone, $data->eZone) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	$t->js()->closePanel('#panel-plot-update');

	$t->js()->eval('MapCartography.get("'.\map\PlanUi::MAP.'").addPlot('.$data->ePlot['id'].', '.json_encode($data->ePlot['name']).', '.$data->eZone['id'].', '.json_encode($data->ePlot['coordinates']).', true, "'.$data->ePlot['mode'].'", '.$data->ePlot['bedsUndrawn'].')');

	if($data->shapeChanged) {
		$t->js()->eval('MapCartography.get("'.\map\PlanUi::MAP.'").removeBeds('.$data->ePlot['id'].')'.new \map\MapUi()->addBeds($data->ePlot));
	}

	$renderPlot($t, $data->eStructure, $data->season, $data->eZone, $data->ePlot);

	$t->js()->success('map', $data->success);

});

// Le troisième niveau d'un jardin, à la place du nœud vide que la vue Carte lui garde
new AdaptativeView('drawing', function($data, AjaxTemplate $t) use($renderBeds) {

	$renderBeds($t, $data->eStructure, $data->season, $data->eZone, $data->ePlot);

});

// Un contour tracé change le jardin, ce qui le compte dans sa parcelle et la structure, et ses actions sur la carte. Il reste
// désigné parmi ceux de sa parcelle, avec ses planches si le tracé est parti d'elles
new AdaptativeView('doUpdateShape', function($data, AjaxTemplate $t) use($renderPlot, $renderBeds) {

	foreach(new \map\PlanUi()->getMapZoneNodes($data->eStructure, $data->season, $data->cZone, $data->eZone) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	if($data->bedsShown) {
		$renderBeds($t, $data->eStructure, $data->season, $data->eZone, $data->ePlot);
	} else {
		$renderPlot($t, $data->eStructure, $data->season, $data->eZone, $data->ePlot);
	}

	// Le contour tel qu'il est enregistré, que la carte dessine à la place de celui du tracé
	$t->push('plot', $data->ePlot['id']);
	$t->push('zone', $data->eZone['id']);
	$t->push('name', $data->ePlot['name']);
	$t->push('mode', $data->ePlot['mode']);
	$t->push('coordinates', $data->ePlot['coordinates']);
	$t->push('undrawn', $data->ePlot['bedsUndrawn']);

	$t->js()->success('map', 'Plot::updated');

});

new AdaptativeView('duplicate', function($data, PanelTemplate $t) {

	return new \map\PlotUi()->duplicate($data->e, $data->season, $data->eZone, $data->ePlot);

});

new AdaptativeView('doDuplicate', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');
	$t->js()->closePanel('#panel-plot-duplicate');
	$t->js()->success('map', $data->success);

});

new AdaptativeView('updateGreenhouse', function($data, PanelTemplate $t) {

	return new \map\PlotUi()->updateGreenhouse($data->e);

});

new AdaptativeView('doUpdateGreenhouse', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');
	$t->js()->closePanel('#panel-plot-greenhouse');
	$t->js()->success('map', $data->success);

});

// Un jardin supprimé dans la vue Carte quitte sa parcelle, qui le comptait, la carte et ses actions. Sa parcelle reprend la main
new AdaptativeView('doDeleteMap', function($data, AjaxTemplate $t) {

	foreach(new \map\PlanUi()->getMapZoneNodes($data->eStructure, $data->season, $data->cZone, $data->eZone) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	$t->qs('[data-plot-actions="'.$data->e['id'].'"]')->remove();

	// Le troisième niveau et sa fiche reviennent vides, comme la vue les pose à l'adresse de la parcelle
	$uiDraw = new \map\DrawUi();

	$t->qs('#'.\map\DrawUi::LEVEL)->outerHtml($uiDraw->getLevel($data->eStructure, $data->season, $data->eZone, new \map\Plot()));
	$t->qs('#'.\map\DrawUi::CARD)->outerHtml($uiDraw->getCard($data->eStructure, $data->season, $data->eZone, new \map\Plot()));

	// La carte quitte l'emprise du jardin retiré pour celle de sa parcelle, qui reprend la main
	$t->js()->eval('MapCartography.get("'.\map\PlanUi::MAP.'").removePlot('.$data->e['id'].').clickZone('.$data->eZone['id'].')');

	$t->js()->success('map', $data->success);

});

new AdaptativeView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');
	$t->js()->success('map', $data->success);

});
new AdaptativeView('doUpdatePositions', function($data, AjaxTemplate $t) {

});
?>
