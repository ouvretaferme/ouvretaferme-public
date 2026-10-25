<?php
// Un geste du troisième niveau ne recharge pas la vue Carte : le niveau et sa fiche repartent de ce qui vient d'être écrit,
// avec ce que les deux niveaux du dessus et le compte du jardin sur la carte en disent
$renderDrawing = function(AjaxTemplate $t, \structure\Structure $eStructure, int $season, \Collection $cZone, \map\Zone $eZone, \map\Plot $ePlot) {

	$uiDraw = new \map\DrawUi();

	// La liste rendue repartirait du haut, et les planches cochées plus bas sortiraient de la vue : sa position se lit avant, et MapPlan.showDrawing() la repose
	$t->js()->eval('MapPlan.keepConsoleScroll()');

	$t->qs('#'.\map\DrawUi::LEVEL)->outerHtml($uiDraw->getLevel($eStructure, $season, $eZone, $ePlot));
	$t->qs('#'.\map\DrawUi::CARD)->outerHtml($uiDraw->getCard($eStructure, $season, $eZone, $ePlot));

	foreach(new \map\PlanUi()->getMapZoneNodes($eStructure, $season, $cZone, $eZone, $ePlot) as $selector => $node) {
		$t->qs($selector)->outerHtml($node);
	}

	$t->js()->eval('MapCartography.get("'.\map\PlanUi::MAP.'").refreshPlotCounter('.$ePlot['id'].', '.$ePlot['bedsUndrawn'].')');

	// La carte montre les planches cochées, que la liste rendue vient peut-être de changer
	$t->js()->eval('MapPlan.showDrawing(false)');

	// L'adresse désigne les planches que le geste laisse cochées (`ids[]`), et aucune quand il n'en laisse pas : un rechargement doit rendre ce qu'il laisse à l'écran
	$t->js()->replaceHistory(\map\DrawUi::urlPlot($eStructure, $season, $ePlot, $ePlot['cBedSelected']->getIds()));

};

// Des planches modifiées depuis la vue Carte y rendent le troisième niveau, et le panneau se ferme
$renderDrawingEdited = function(AjaxTemplate $t, \structure\Structure $eStructure, int $season, \Collection $cZone, \map\Zone $eZone, \map\Plot $ePlot, string $panel) use($renderDrawing) {

	$renderDrawing($t, $eStructure, $season, $cZone, $eZone, $ePlot);

	$t->js()->closePanel($panel);

	$t->js()->success('map', 'Bed::updatedCollection');

};

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \map\BedUi()->createCollection($data->eStructure, $data->season, $data->eZone, $data->ePlot, $data->origin);
});

new AdaptativeView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->ePlot['zone']['id'].')');
	$t->js()->closePanel('#panel-bed-create');

});

// Des planches créées depuis la vue Carte y rendent le troisième niveau du jardin, d'où l'ajout s'est ouvert, et la carte reçoit
// ses traits, dont le rang que le panneau vient de dessiner
new AdaptativeView('doCreateMap', function($data, AjaxTemplate $t) use($renderDrawing) {

	$renderDrawing($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot);

	$t->js()->eval('MapCartography.get("'.\map\PlanUi::MAP.'").removeBeds('.$data->ePlot['id'].')'.new \map\MapUi()->addBeds($data->ePlot));
	$t->js()->closePanel('#panel-bed-create');

	$t->js()->success('map', 'Bed::createdCollection');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \map\BedUi()->update($data->season, $data->e, $data->cZone);

});

new AdaptativeView('swapSeries', function($data, PanelTemplate $t) {

	return new \map\BedUi()->swapSeries($data->e, $data->season, $data->cZone);

});

new AdaptativeView('updateSizeCollection', function($data, PanelTemplate $t) {

	return new \map\BedUi()->updateSizeCollection($data->season, $data->ePlot, $data->cBed, $data->eBedSize, $data->origin);

});

// Les dimensions des planches cochées : la vue Carte y rend leur troisième niveau, l'onglet Plan relit la parcelle
new AdaptativeView('doUpdateSizeCollection', function($data, AjaxTemplate $t) use($renderDrawingEdited) {

	if($data->origin === \map\PlanUi::VIEW_MAP) {
		$renderDrawingEdited($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot, '#panel-bed-size');
	} else {
		$t->js()->eval('MapCartography.queryZone('.$data->ePlot['zone']['id'].')');
		$t->js()->closePanel('#panel-bed-size');
	}

});

new AdaptativeView('updatePlotCollection', function($data, PanelTemplate $t) {

	return new \map\BedUi()->updatePlotCollection($data->season, $data->ePlot, $data->cBed, $data->cZone);

});

new AdaptativeView('doUpdatePlotCollection', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-bed-plot');
	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');

});

new AdaptativeView('updateSeasonCollection', function($data, PanelTemplate $t) {

	return new \map\BedUi()->updateSeasonCollection($data->season, $data->ePlot, $data->cBed, $data->origin);

});

// Les saisons des planches cochées : la vue Carte y rend leur troisième niveau, l'onglet Plan relit la parcelle
new AdaptativeView('doUpdateSeasonCollection', function($data, AjaxTemplate $t) use($renderDrawingEdited) {

	if($data->origin === \map\PlanUi::VIEW_MAP) {
		$renderDrawingEdited($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot, '#panel-bed-season');
	} else {
		$t->js()->eval('MapCartography.queryZone('.$data->ePlot['zone']['id'].')');
		$t->js()->closePanel('#panel-bed-season');
	}

});

new AdaptativeView('doUpdateBedLineCollection', function($data, AjaxTemplate $t) use($renderDrawing) {

	$renderDrawing($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot);

	// La carte reçoit les planches déplacées, que MapDrawing pose à la place des anciennes en fermant le geste
	$t->push('plot', $data->ePlot['id']);
	$t->push('beds', $data->beds);
	$t->push('bedsDrawn', $data->bedsDrawn);

	$t->js()->success('map', 'Draw::created');

});

new AdaptativeView('doUpdateBedLineSpacing', function($data, AjaxTemplate $t) use($renderDrawing) {

	$renderDrawing($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot);

	$t->js()->eval('MapPlan.openConsoleDrawing('.json_encode(['map' => \map\PlanUi::MAP, 'plot' => $data->ePlot['id'], 'beds' => $data->beds, 'bedsDrawn' => $data->bedsDrawn]).')');

	$t->js()->success('map', 'Draw::respaced');

});

new AdaptativeView('doDeleteBedLineCollection', function($data, AjaxTemplate $t) use($renderDrawing) {

	if($data->origin === \map\PlanUi::VIEW_MAP) {

		$renderDrawing($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot);

		// Aucun geste n'est ouvert pour poser les planches tracées sur la carte : la réponse le fait elle-même
		$t->js()->eval('MapPlan.refreshConsoleDraws('.json_encode(['map' => \map\PlanUi::MAP, 'plot' => $data->ePlot['id'], 'beds' => $data->beds, 'bedsDrawn' => $data->bedsDrawn]).')');

	} else {
		$t->js()->eval('MapCartography.queryZone('.$data->ePlot['zone']['id'].')');
	}

	$t->js()->success('map', 'Draw::deleted');

});

new AdaptativeView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-bed-update');
	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');

});

new AdaptativeView('doUpdateQuick', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');

});

new AdaptativeView('doDeleteCollection', function($data, AjaxTemplate $t) use($renderDrawing) {

	if($data->origin === \map\PlanUi::VIEW_MAP) {

		$renderDrawing($t, $data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot);

		// Les planches supprimées quittent la carte, comme après un effacement
		$t->js()->eval('MapPlan.refreshConsoleDraws('.json_encode(['map' => \map\PlanUi::MAP, 'plot' => $data->ePlot['id'], 'beds' => $data->beds, 'bedsDrawn' => $data->bedsDrawn]).')');
		$t->js()->success('map', 'Bed::deletedCollection');

	} else {
		$t->js()->eval('MapCartography.queryZone('.$data->ePlot['zone']['id'].')');
	}

});

new AdaptativeView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->eval('MapCartography.queryZone('.$data->e['zone']['id'].')');

});
?>
