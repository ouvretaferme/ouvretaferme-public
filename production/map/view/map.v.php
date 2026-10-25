<?php
new AdaptativeView('plan', function($data, StructureTemplate $t) {

	$uiPlan = new \map\PlanUi();

	$t->title = s("Plan de {value}", $data->eStructure['name']);
	$t->canonical = \map\PlanUi::url($data->eStructure, $data->season);

	$t->nav = 'cultivation';
	$t->subNav = 'map';

	$t->mainTitle = $uiPlan->getTitle($data->eStructure, $data->season, \map\PlanUi::VIEW_PLAN);

	echo $uiPlan->getViews($data->eStructure, $data->season, \map\PlanUi::VIEW_PLAN);
	if(GET('onboarded', 'bool')) {
		echo new \map\OnboardingUi()->getDone($data->eStructure, $data->season, $data->cZone);
	}

	echo $uiPlan->getPlan($data->eStructure, $data->season, $data->cZone, $data->eZone, $data->ePlot, $data->table);

});

new AdaptativeView('map', function($data, StructureTemplate $t) {

	$uiPlan = new \map\PlanUi();

	$t->title = s("Plan de {value}", $data->eStructure['name']);
	$t->canonical = \map\PlanUi::url($data->eStructure, $data->season, \map\PlanUi::VIEW_MAP);

	$t->nav = 'cultivation';
	$t->subNav = 'map';

	$t->mainTitle = $uiPlan->getTitle($data->eStructure, $data->season, \map\PlanUi::VIEW_MAP, withZones: $data->cZone->notEmpty(), createOnMap: $data->mapCenter !== NULL);

	echo $uiPlan->getViews($data->eStructure, $data->season, \map\PlanUi::VIEW_MAP);
	// Le troisième niveau, les planches d'un jardin, attend vide le clic qui l'ouvre chez qui peut dessiner
	if($data->eStructure->canManage()) {

		$uiDraw = new \map\DrawUi();

		$drawingLevel = $uiDraw->getLevel($data->eStructure, $data->season, $data->eZone, $data->ePlotDrawing);
		$drawingMap = $uiDraw->getCard($data->eStructure, $data->season, $data->eZone, $data->ePlotDrawing).$uiDraw->getMapDrawingLine();

	} else {
		$drawingLevel = '';
		$drawingMap = '';
	}

	echo $uiPlan->getMapView($data->eStructure, $data->eMember, $data->season, $data->cZone, $data->eZone, $data->ePlot, $data->mapCenter, $data->mapBounds, $data->basemap, $data->ePlotDrawing, $drawingLevel, $drawingMap);

});
new AdaptativeView('getPlan', function($data, PdfTemplate $t) {

	$t->title = s("Plan de la ferme {value}", $data->season);

	echo new \map\PlanUi()->getPlanPrinting($data->eStructure, $data->season, $data->cZone, $data->cZonePrinted, $data->mapBounds, $data->basemap);

});
new AdaptativeView('getZone', function($data, AjaxTemplate $t) {

	$t->qs('#plan-zone-'.$data->eZone['id'])->outerHtml(new \map\PlanUi()->getPlanZone($data->eStructure, $data->season, $data->eZone, $data->ePlot, $data->table));

});
?>
