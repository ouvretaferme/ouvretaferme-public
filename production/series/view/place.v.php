<?php
/**
 * Le bloc « Assolement » de la fiche série, remis à jour depuis le panneau ouvert
 * par-dessus elle. Le panneau est une couche d'historique : sa fermeture repose la
 * page telle qu'elle était sans la redemander au serveur, c'est donc à l'écriture
 * de rafraîchir le bloc.
 *
 * $data->eContext porte la série de la fiche, relue après l'écriture par
 * PlaceLib::putPlanContext(). Le même panneau sert aussi à assoler une
 * intervention, qui ne touche pas l'assolement de la série : d'où le test de type.
 */
$refreshSeriesPlace = function(\stdClass $data, AjaxTemplate $t): void {

	if(
		$data->origin !== 'series' or
		($data->eContext instanceof \series\Series) === FALSE or
		$data->eContext->empty()
	) {
		return;
	}

	$t->qs('#series-soil')->outerHtml(new \series\SeriesUi()->updatePlace($data->eContext, $data->eContext['cPlace']));

};

/**
 * Le pied du panneau d'assolement d'une série, qui dit ce qui est placé face à l'objectif. Les gestes du
 * plan écrivent sans recharger le panneau : sans cette instruction, le pied garde la valeur de son
 * ouverture. Hors du panneau (page d'assolement), le sélecteur ne trouve rien et l'instruction n'agit pas.
 */
$refreshSubmit = function(\Element $eContext, AjaxTemplate $t): void {

	if(
		($eContext instanceof \series\Series) === FALSE or
		$eContext->empty()
	) {
		return;
	}

	$t->qs('#panel-place-update .place-update-submit')->outerHtml(new \series\PlaceUi()->getSubmit('series', $eContext));

};

new AdaptativeView('updateModal', function($data, PanelTemplate $t) {
	return new \series\PlaceUi()->update($data->e['structure'], $data->source, $data->e, $data->cZone, $data->search, $data->viewSoil, $data->eZoneSelected, $data->origin);
});

new JsonView('updateCultivation', function($data, AjaxTemplate $t) {

	if($data->full) {

		$plan = ($data->viewSoil === \structure\Member::GARDEN) ?
			new \series\PlaceGardenAiUi()->setUngrouped($data->ungrouped)->setPlaced($data->e)->getGarden($data->e['structure'], $data->cZone, $data->eZoneSelected, $data->e['season']) :
			new \map\ZoneUi()->setPlaced($data->e)->getPlan($data->e['structure'], $data->cZone, $data->eZoneSelected, $data->e['season']);

		$t->qs('#zone-container')->outerHtml($plan);
		$t->js()->eval('SeriesPlacement.syncPosing()');

	} else if($data->viewSoil === \structure\Member::GARDEN) {

		$t->js()->eval('SeriesPlacement.applyGarden('.json_encode(
			new \series\PlaceGardenAiUi()->setPlaced($data->e)->getGardenState($data->e['structure'], $data->cZone, $data->e['season'])
		).')');

	} else {

		$t->js()->eval('SeriesPlacement.apply('.json_encode(
			new \series\PlaceUi()->getPlacementState($data->e['structure'], $data->cZone, $data->e, $data->e['season'])
		).')');

	}

	$cards = new \series\SeriesUi()->getSelectorCards($data->e['structure'], $data->ccCultivation, $data->e, $data->e, $data->cZone, $data->search);

	foreach($cards as $cultivation => $card) {
		$t->qs('#series-selector-'.$cultivation)->outerHtml($card);
	}

	$t->js()->eval('SeriesSelector.markSeries('.$data->e['id'].')');
	$t->js()->eval('SeriesResize.paintTarget()');

	if($data->full) {

		// Le plan vient d'être refait : il retrouve son défilement et il ignore le
		// filtre que la fiche affiche.
		$t->js()->eval('SeriesScroll.restore()');
		$t->js()->eval('SeriesPlace.search()');

	} else {

		$t->js()->eval('SeriesPlace.searchIfFiltered()');

	}

	$t->js()->eval('SeriesResize.updateJumpersAfterPaint()');

});

new JsonView('panel', function($data, AjaxTemplate $t) {

	$cards = new \series\SeriesUi()->getSelectorCards($data->e['structure'], $data->ccCultivation, $data->e, $data->e, $data->cZone, $data->search);

	foreach($cards as $cultivation => $card) {
		$t->qs('#series-selector-'.$cultivation)->outerHtml($card);
	}

	// Les filtres de la fiche s'appuient sur les attributs de test des colonnes du
	// plan : le panneau les calcule déjà, il lui restait à les y poser.
	$t->js()->eval('SeriesPlacement.applyFilters('.json_encode(
		new \series\PlaceUi()->getFilterState($data->cZone)
	).')');

	$t->js()->eval('SeriesSelector.markSeries('.$data->e['id'].')');
	$t->js()->eval('SeriesSelector.reveal('.$data->e['id'].')');

	$t->js()->eval('SeriesResize.restoreSelection('.$data->e['id'].')');

	$t->js()->eval('SeriesPlace.search()');

});

new JsonView('tipAi', function($data, AjaxTemplate $t) {

	// La reponse ecrit dans une zone tampon et non dans la bulle : elle arrive apres
	// coup, et le survol a pu changer entre-temps. C'est le JS qui decide de la
	// recopier, en verifiant que c'est encore la bonne serie.
	$t->qs('#place-tip-stage')->innerHtml(new \series\PlaceTipAiUi()->get($data->e));

});

new JsonView('bedPanel', function($data, AjaxTemplate $t) {

	$t->qs('#place-panel')->innerHtml(
		new \series\PlacePanelAiUi()->getBedPanel($data->eBed, $data->eSeriesContext, $data->cSeriesBed, $data->rotations, $data->freeGap, $data->cBedSwap, $data->e['season'], $data->cNoteLinked)
	);

});

new JsonView('ungroupAi', function($data, AjaxTemplate $t) {

	$uiGarden = new \series\PlaceGardenAiUi()->setUngrouped($data->ungrouped);

	if($data->eContext->notEmpty()) {
		$uiGarden->setPlaced($data->eContext);
	}

	[$startTs, $stopTs] = $uiGarden->prepareGarden($data->e['structure'], $data->cZone, $data->e['season']);

	$months = $uiGarden->getGardenMonths($startTs, $stopTs, $data->e['season']);

	$targets = array_map('intval', $data->targetBeds ?? []);

	foreach($data->cZone as $eZone) {

		foreach($eZone['cPlot'] as $ePlot) {

			$touched = FALSE;

			foreach($ePlot['cBed'] as $eBed) {

				if(in_array($eBed['id'], $targets, TRUE)) {
					$touched = TRUE;
					break;
				}

			}

			if($touched === FALSE) {
				continue;
			}

			$t->qs('#'.\series\PlaceGardenAiUi::getGardenPlotId($ePlot))->outerHtml(
				$uiGarden->getGardenPlot($data->e['structure'], $ePlot, $data->e['season'], $months, $startTs, $stopTs)
			);

		}

	}

	$t->js()->eval('SeriesHidden.repaint()');
	$t->js()->eval('SeriesPlace.search()');
	$t->js()->eval('SeriesPlace.repaintAfterRender()');
	$t->js()->eval('SeriesResize.updateJumpers()');
	$t->js()->eval('SeriesScroll.restore()');

});

new JsonView('doTargetAi', function($data, AjaxTemplate $t) use($refreshSeriesPlace, $refreshSubmit) {

	$eContext = $data->eContext;

	if($data->e instanceof \series\Series) {

		$cards = new \series\SeriesUi()->getSelectorCards($data->e['structure'], $data->ccCultivation, $data->e, $eContext, $data->cZone, $data->search);

		foreach($cards as $cultivation => $card) {
			$t->qs('#series-selector-'.$cultivation)->outerHtml($card);
		}

	}

	if($eContext->notEmpty()) {
		$t->js()->eval('SeriesSelector.markSeries('.$eContext['id'].')');
	}

	$targets = array_map('intval', $data->targetBeds ?? []);

	$garden = ($data->viewSoil === \structure\Member::GARDEN);

	if($garden) {

		$uiGarden = new \series\PlaceGardenAiUi()->setUngrouped($data->ungrouped);

		if($eContext->notEmpty()) {
			$uiGarden->setPlaced($eContext);
		}

		[$startTs, $stopTs] = $uiGarden->prepareGarden($data->e['structure'], $data->cZone, $data->e['season']);

		$months = $uiGarden->getGardenMonths($startTs, $stopTs, $data->e['season']);

	} else {

		$uiBed = new \map\BedUi();

		// Une planche redessinée seule reprend les bornes que le plan entier lui donne
		// (`PlotUi`) : sans elles, la planche qu'on vient de vider n'offrait plus de bouton Ajouter.
		$target = new \series\PlaceUi()->getPlacedBounds($data->e['structure'], $eContext, $data->e['season']);

	}

	foreach($data->cZone as $eZone) {

		foreach($eZone['cPlot'] as $ePlot) {

			$touched = FALSE;

			foreach($ePlot['cBed'] as $eBed) {

				if(in_array($eBed['id'], $targets, TRUE)) {
					$touched = TRUE;
					break;
				}

			}

			if($touched === FALSE) {
				continue;
			}

			if($garden) {

				$t->qs('#'.\series\PlaceGardenAiUi::getGardenPlotId($ePlot))->outerHtml(
					$uiGarden->getGardenPlot($data->e['structure'], $ePlot, $data->e['season'], $months, $startTs, $stopTs)
				);

				continue;

			}

			foreach($ePlot['cBed'] as $eBed) {

				if(in_array($eBed['id'], $targets, TRUE) === FALSE) {
					continue;
				}

				$t->qs('#'.\map\BedUi::getBedLineId($eBed))->outerHtml(
					$uiBed->displayBedFromPlot($data->e['structure'], $ePlot, $eBed, $data->e['season'], $eContext, FALSE, target: $target)
				);

			}

		}

	}

	$refreshSeriesPlace($data, $t);
	$refreshSubmit($eContext, $t);

	$t->js()->eval('SeriesHidden.repaint()');
	$t->js()->eval('SeriesPlace.search()');
	$t->js()->eval('SeriesResize.updateJumpers()');
	$t->js()->eval('SeriesScroll.restore()');
	$t->js()->eval('SeriesResize.paintTarget()');
	$t->js()->eval('SeriesPlace.flashSeries('.$data->e['id'].', "'.$data->gesture.'", '.json_encode($targets).')');
	$t->js()->eval('SeriesResize.restoreSelection('.$data->e['id'].')');

	$t->js()->eval('SeriesPlace.search()');

});

new JsonView('doMove', function($data, AjaxTemplate $t) use($refreshSeriesPlace, $refreshSubmit) {

	$eContext = $data->eContext;

	$t->qs('#series-selector-list')->outerHtml(new \series\SeriesUi()->getSelectorSeries($data->e['structure'], $data->ccCultivation, $eContext, $data->cZone, $data->search));

	if($eContext->notEmpty()) {
		$t->js()->eval('SeriesSelector.markSeries('.$eContext['id'].')');
	}

	if($data->viewSoil === \structure\Member::GARDEN) {

		$garden = new \series\PlaceGardenAiUi()->setUngrouped($data->ungrouped);

		if($eContext->notEmpty()) {
			$garden->setPlaced($eContext);
		}

		$plan = $garden->getGarden($data->e['structure'], $data->cZone, $data->eZoneSelected, $data->e['season']);

	} else {

		$zone = new \map\ZoneUi();

		if($eContext->notEmpty()) {
			$zone->setPlaced($eContext);
		}

		$plan = $zone->getPlan($data->e['structure'], $data->cZone, $data->eZoneSelected, $data->e['season']);

	}

	$t->qs('#zone-container')->outerHtml($plan);

	$refreshSeriesPlace($data, $t);
	$refreshSubmit($eContext, $t);

	$t->js()->success('series', 'Series::movedSoil');
	$t->js()->eval('SeriesSelector.refreshCount()');
	$t->js()->eval('SeriesSelector.restoreFilter()');
	$t->js()->eval('SeriesHidden.repaint()');
	$t->js()->eval('SeriesPlace.search()');
	$t->js()->eval('SeriesResize.updateJumpers()');
	$t->js()->eval('SeriesScroll.restore()');
	$t->js()->eval('SeriesResize.paintTarget()');
	$t->js()->eval('SeriesPlace.flashSeries('.$data->e['id'].', "'.$data->gesture.'", '.json_encode(array_map('intval', $data->flashBeds ?? [])).')');
	$t->js()->eval('SeriesResize.restoreSelection('.$data->e['id'].')');

	$t->js()->eval('SeriesPlace.search()');

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	switch($data->source) {

		case 'cultivation' :
			$t->qs('#series-selector-list')->outerHtml(new \series\SeriesUi()->getSelectorSeries($data->e['structure'], $data->ccCultivation));
			if($data->viewSoil === \structure\Member::GARDEN) {
				$plan = new \series\PlaceGardenAiUi()->setUngrouped($data->ungrouped)->getGarden($data->e['structure'], $data->cZone, $data->eZoneSelected, $data->e['season']);
			} else {
				$plan = new \map\ZoneUi()->getPlan($data->e['structure'], $data->cZone, $data->eZoneSelected, $data->e['season']);
			}

			$t->qs('#zone-container')->outerHtml($plan);

			$t->js()->eval('SeriesScroll.restore()');
	$t->js()->eval('SeriesResize.paintTarget()');

			$t->js()->success('series', 'Series::updatedSoil');
			$t->js()->eval('SeriesSelector.select('.$data->eCultivation['id'].')');
			$t->js()->eval('SeriesSelector.restoreFilter()');
			$t->js()->eval('SeriesSelector.refreshCount()');
			break;

		case 'series' :
			$t->js()->moveHistory(-1);
			$t->qs('#series-soil')->outerHtml(new \series\SeriesUi()->updatePlace($data->e, $data->cPlace));
			break;

		case 'task' :
			$t->js()->moveHistory(-1);
			$t->ajaxReload(purgeLayers: FALSE);
			break;

	}

});

new JsonView('sheet', function($data, AjaxTemplate $t) {

	$t->qs('#place-sheet')->outerHtml(new \series\PlaceSheetUi()->get($data->eStructure, $data->eSeries, $data->date, $data->sheet, $data->color, $data->inPlace, $data->cCultivation, $data->canHarvest, $data->canTask));

});

new JsonView('doSheet', function($data, AjaxTemplate $t) {

	if($data->panel !== NULL) {
		$t->js()->closePanel('#'.$data->panel);
	}

	// Le panneau fermé, la fiche redessinée s'éclaire : c'est ce qui dit que la saisie est prise.
	$t->qs('#place-sheet')->outerHtml(new \series\PlaceSheetUi()->get($data->eStructure, $data->eSeries, $data->date, $data->sheet, $data->color, $data->inPlace, $data->cCultivation, $data->canHarvest, $data->canTask), highlight: $data->panel !== NULL);

});
?>
