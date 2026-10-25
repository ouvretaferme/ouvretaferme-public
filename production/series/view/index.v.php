<?php
new AdaptativeView('planning', function($data, StructureTemplate $t) {

	$t->title = s("Planning de {value}", $data->eStructure['name']);
	$t->nav = 'planning';

	$uiPlanning = new \series\PlanningUi();

	$period = $data->eStructure->getView('viewPlanning');

	switch($period) {

		case \structure\Member::DAILY :

			$t->subNav = 'daily';

			$t->template = 'structure structure-planning-daily';
			$t->mainContainer = FALSE;
			$t->footer = '';
			$t->canonical = \structure\StructureUi::urlPlanningDaily($data->eStructure, $data->week);

			$t->mainTitle = $uiPlanning->getCalendar(
				$data->eStructure,
				$data->week,
				fn($week) => \structure\StructureUi::urlPlanningDaily($data->eStructure, $week),
				fn() => $uiPlanning->getCalendarFilter()
			);

			$cccTaskAction = new Collection()
				->setDepth(3)
				->mergeCollection($data->cccTaskAssign)
				->mergeCollection($data->cccTask);

			echo '<div id="planning-daily-wrapper">';
				echo '<div id="planning-daily-header">';
					echo '<div class="container">'.$uiPlanning->getNewPlanning(\planning\TaskDayUi::url($data->eStructure, $data->week)).'</div>';
					echo '<div class="container mb-1">'.$uiPlanning->getFilters($data->eStructure, LIME_REQUEST, $cccTaskAction, $data->cUserStructure, $data->search->get('user'), $data->cAction, $data->eActionSelected, NULL, $data->ePlantSelected).'</div>';
					echo $uiPlanning->getSearch($data->eStructure, $data->search, $data->cZone);
				echo '</div>';

				echo $uiPlanning->getByDay($data->eStructure, $data->week, $data->cccTask, $data->cccTaskAssign, $data->eActionSelected, $data->ePlantSelected, $data->cUserStructure, $data->eUserDaily, $data->seasonsWithSeries, $data->cCategory);
			echo '</div>';

			break;

		case \structure\Member::WEEKLY :

			$t->subNav = 'weekly';

			$t->template = 'structure structure-planning-weekly';
			$t->canonical = \structure\StructureUi::urlPlanningWeekly($data->eStructure, $data->week);

			$t->mainTitle = $uiPlanning->getCalendar(
				$data->eStructure,
				$data->week,
				fn($week) => \structure\StructureUi::urlPlanningWeekly($data->eStructure, $week),
				fn() => $uiPlanning->getExport($data->eStructure, $data->week).' '.$uiPlanning->getCalendarFilter()
			);

			echo $uiPlanning->getNewPlanning(\planning\TaskWeekUi::url($data->eStructure, $data->week));

			echo new \main\HomeUi()->getTraining(TRUE);

			echo $data->entry ? new \entry\EntryUi()->getContext($data->week, $data->search->get('plot')) : '';

			echo $uiPlanning->getSearch($data->eStructure, $data->search, $data->cZone);

			echo $uiPlanning->getByWeek($data->eStructure, $data->week, $data->cccTask, $data->cUserStructure, $data->eUserTime, $data->seasonsWithSeries, $data->cAction, $data->search->get('user'), $data->eActionSelected, $data->ePlantSelected, $data->cCategory, new \planning\DueUi()->getWeek($data->eStructure, $data->week, $data->dues));

			break;

		case \structure\Member::YEARLY :

			$t->subNav = 'yearly';

			$t->template = 'structure structure-planning-yearly';

			$t->canonical = \structure\StructureUi::urlPlanningYear($data->eStructure, $data->year, $data->month);

			$t->mainTitle = $uiPlanning->getYearCalendar(
				$data->eStructure,
				$data->year,
				fn() => $uiPlanning->getCalendarFilter()
			);

			$t->main = '<div class="container mb-2">';
				$t->main .= $uiPlanning->getNewPlanning(\planning\TaskYearUi::url($data->eStructure, $data->weekNewPlanning));
				$t->main .= $uiPlanning->getSearch($data->eStructure, $data->search, $data->cZone);
				$t->main .= $uiPlanning->getFilters($data->eStructure, LIME_REQUEST, $data->ccTask, $data->cUserStructure, $data->search->get('user'), $data->cAction, $data->eActionSelected, $data->cPlant, $data->ePlantSelected, filterSelected: FALSE);
			$t->main .= '</div>';

			$t->main .= $uiPlanning->getYearSearch(
				$data->eStructure,
				$data->year
			);

			$t->main .= $uiPlanning->getYearMonths($data->year, $data->month, $data->ccTask);

			$t->footer = '';

			break;

	}

	$t->package('main')->updateNavPlanning($t->canonical, $period);

	$t->js()->replaceHistory($t->canonical);

});
new AdaptativeView('/ferme/{id}/intervention/{week}/{action}', function($data, PanelTemplate $t) {

	return new \series\TaskUi()->displayByAction($data->eAction, $data->cTask);

});
new AdaptativeView('series', function($data, StructureTemplate $t) {

	$t->canonical = \structure\StructureUi::urlCultivationSeries($data->eStructure, $data->view, season: $data->season);
	$t->title = s("Plan de culture de {value}", $data->eStructure['name']);

	$t->nav = 'cultivation';
	$t->subNav = 'series';
	$t->subNavTarget = $t->canonical;

	$t->js()->replaceHistory($t->canonical);

	$uiSeries = new \series\SeriesUi();

	if(OTF_DEMO and $view === \structure\Member::AREA) {
		echo '<div class="util-block bg-secondary color-white">';
			echo '<h4>'.s("Bienvenue sur la démo !").'</h4>';
			echo '<p>'.s("Cette ferme est en libre service partagé entre tous les utilisateurs de {siteName} pour vous aider à découvrir les fonctionnalités de notre site. N'hésitez pas à parcourir toutes les pages et faire toutes sortes d'actions ! Les données de cette ferme sont automatiquement remises à zéro toutes les nuits.").'</p>';
			echo '<p>'.s("Vous pouvez quitter à tout moment la démo en utilisant le lien <i>Quitter la démo</i> en haut de la page.").'</p>';
		echo '</div>';
	}

	$t->mainTitle = \series\HeaderUi::getSeries($data->eStructure, $data->season, $data->view, $data->nSeries, $data->firstSeries, $data->eStructureCommunityExport);

	echo $uiSeries->displayImport($data->eStructure, $data->nSeries, $data->cSeriesImportPerennial, $data->firstSeries, $data->season);

	if($data->nSeries === 0) {

		echo $uiSeries->deleteSeason($data->eStructure, $data->season);

		// ⚠️ **Deux absences, et deux phrases.** « Pas de série sur cette saison » quand la ferme
		// en a ailleurs : l'absence est ordinaire, elle ne dit rien de la ferme et le lecteur est
		// peut-être sur la mauvaise année. « Pas de plan de culture du tout » : la donnée n'existe
		// pas, et c'est ce que le visiteur venu d'un lien de groupe doit apprendre. La distinction
		// existe déjà côté page (`$data->firstSeries`), elle n'était simplement pas dite.
		//
		// ⚠️ Sans ce bloc, l'écran ne rendait **rien** pour un lecteur sans droit — ni proposition
		// ni constat — et c'est pourtant celui où un lien de groupe dépose le visiteur. Pas de
		// lien : les deux écrans où il mènerait demandent `canManage()`.
		if($data->eStructure->canManage() === FALSE) {

			echo \main\DesignUi::getEmptyRead(
				$data->eStructure,
				$data->firstSeries
					? s("Aucun plan de culture n'a encore été créé, et seul un responsable de la structure peut en créer.")
					: s("Aucune série sur la saison {value}, et seul un responsable de la structure peut en créer.", $data->season),
				$data->firstSeries
					? s("Cette ferme n'a pas encore créé de plan de culture.")
					: s("Cette ferme n'a pas de série sur la saison {value}.", $data->season)
			);

		}

	} else {

		// ⚠️ Ce bloc ne se rend que chez soi, et n'a pas de version pour un visiteur. Son texte
		// — « Renseignez les ateliers de votre ferme pour mieux profiter des fonctionnalités à
		// venir » — est une promesse adressée au propriétaire, avec un bouton vers un écran de
		// modification qui refuserait : il n'a pas de formulation juste pour quelqu'un d'autre, et
		// tout vide ne mérite pas un bloc de remplacement.
		if(
			FEATURE_SYSTEM_PROMO and
			$data->eStructure->canManage() and
			\structure\ProductionLib::getByStructure($data->eStructure)->empty()
		) {
			echo new \structure\SystemUi()->getStart($data->eStructure->system());
		}

		echo match($data->view)  {
			\structure\Member::AREA => new \series\CultivationUi()->displayByArea($data->season, $data->eStructure, $data->ccCultivation, $data->eActionHarvest, $data->eStructureCommunityExport),
			\structure\Member::HARVESTING => new \series\CultivationUi()->displayByHarvesting($data->ccCultivation),
			\structure\Member::WORKING_TIME => new \series\CultivationUi()->displayByWorkingTime($data->eStructure, $data->ccCultivation)
		};


	}

});
new AdaptativeView('seedling', function($data, StructureTemplate $t) {

	$t->canonical = LIME_REQUEST;
	$t->title = s("Semences et plants de {value}", $data->eStructure['name']);
	$t->nav = 'cultivation';
	$t->subNav = 'seedling';

	$t->js()->replaceHistory($t->canonical);

	$t->mainTitle = \series\HeaderUi::getSeedling($data->eStructure, $data->season);

	if($data->nSeries > 0) {
		echo \series\HeaderUi::getSeedlingSearch($data->eStructure, $data->season, $data->search, $data->cSupplier);
	}

	echo $data->search->get('groupWeek') ?
			new \series\CultivationUi()->displayBySeedlingByWeek($data->eStructure, $data->items, $data->cSupplier, $data->search) :
			new \series\CultivationUi()->displayBySeedling($data->eStructure, $data->items, $data->cSupplier, $data->search);

});
new AdaptativeView('forecast', function($data, StructureTemplate $t) {

	$t->canonical = \structure\StructureUi::urlCultivationForecast($data->eStructure, season: $data->season);
	$t->title = s("Prévisionnel financier de {value}", $data->eStructure['name']);
	$t->nav = 'cultivation';
	$t->subNav = 'forecast';

	$t->js()->replaceHistory($t->canonical);

	$t->mainTitle = \series\HeaderUi::getForecast($data->eStructure, $data->season);

	if($data->ccForecast->empty()) {

		// ⚠️ Un écran vide propose un geste, et un geste suppose un droit. Sans `canManage()`,
		// le bloc ci-dessus proposait de créer des séries à qui ne peut pas en créer, et disait
		// « votre ferme » à qui visite celle d'un autre. Les deux lecteurs sans écriture ne lisent
		// pas la même chose : le membre a la raison, qui le concerne ; le visiteur a l'état de la
		// ferme, parce qu'il n'a aucun droit à aller chercher.
		if($data->eStructure->canManage()) {

			$start = '<p>'.s("Le prévisionnel financier met face à face ce que vous comptez vendre et ce que votre production peut donner.").'</p>';
			$start .= '<p>'.s("Il se remplit avec les séries de la saison.").'</p>';

			echo new \main\DesignUi()->getStart(
				\Asset::icon('graph-up'),
				s("Le prévisionnel financier de votre ferme"),
				$start,
				buttons: '<a href="'.\structure\StructureUi::urlCultivationSeries($data->eStructure, season: $data->season).'" class="btn btn-xl btn-outline-primary">'.s("Voir le plan de culture").'</a>'
			);

		} else {

			echo \main\DesignUi::getEmptyRead(
				$data->eStructure,
				s("Le prévisionnel financier de cette saison est vide : il se remplit avec les séries, et seul un responsable de la structure peut en créer."),
				s("Cette ferme n'a pas encore de prévisionnel financier pour cette saison.")
			);

		}

	} else {

		$uiForecast = new \plant\ForecastUi();

		echo $uiForecast->getFigures($data->ccForecast, $data->salesForecast, $data->salesSeries);
		echo $uiForecast->display($data->ccForecast);

	}

});
new AdaptativeView('soil', function($data, StructureTemplate $t) {

	$t->canonical = \structure\StructureUi::urlCultivationSoil($data->eStructure, season: $data->season);
	$t->title = s("Assolement de {value}", $data->eStructure['name']);

	$t->nav = 'cultivation';
	$t->subNav = 'soil';
	$t->subNavTarget = $t->canonical;

	$view = $data->soilView;

	$t->mainTitle = \series\HeaderUi::getSoilTitle($data->eStructure, $data->season, $view, $data->cZone, ($view === \structure\Member::ROTATION) ? FALSE : $data->hasGardenGroups);

	if($data->cZone->notEmpty()) {

		echo \series\HeaderUi::getSoilViews(
			$data->eStructure,
			$data->season,
			$view,
			new \map\ZoneUi()->getPlanCorner($data->eStructure, $data->cZone, $data->eZoneSelected, $data->season)
		);

		if(in_array($view, [\structure\Member::PLAN, \structure\Member::GARDEN], TRUE)) {
			echo \series\HeaderUi::getSoilFilter();
			echo \series\PlaceUi::getLabels();
			echo \series\PlaceUi::getEditingTip($data->placeHelper);
		}

		echo \map\ZoneUi::getPathLabels();

		switch($view) {

			case \structure\Member::PLAN :

				$this->template .= ' structure-fixed ';

				echo \series\SeriesUi::getSelectorShell($data->eStructure, $data->season);

				echo '<div class="zone-sticky-overlay-left"></div>';
				echo '<div class="zone-sticky-overlay-right"></div>';

				echo new \map\ZoneUi()->getPlan($data->eStructure, $data->cZone, $data->eZoneSelected, $data->season);
				echo new \series\PlaceSheetUi()->getEmpty($data->eStructure);

				break;

			case \structure\Member::GARDEN :

				echo \series\SeriesUi::getSelectorShell($data->eStructure, $data->season);

				echo new \series\PlaceGardenAiUi()->getGarden($data->eStructure, $data->cZone, $data->eZoneSelected, $data->season);
				echo new \series\PlaceSheetUi()->getEmpty($data->eStructure);

				break;

			case \structure\Member::ROTATION :
				echo \series\HeaderUi::getRotationSearch($data->search, $data->selectedSeasons);
				echo new \map\ZoneUi()->getRotation($data->eStructure, $data->cZone, $data->eZoneSelected, $data->season, $data->search);
				break;

		}

	} else {

		// ⚠️ Même règle qu'au prévisionnel : le bouton mène au plan, que seul un responsable
		// peut configurer. Le visiteur n'a donc ni le geste ni la raison d'en chercher un — il
		// lit l'état de la ferme, et rien d'autre.
		if($data->eStructure->canManage()) {

			$start = '<p>'.s("Vous pourrez consulter votre plan d'assolement dès lors que vous aurez configuré le plan de votre ferme.").'</p>';

			echo new \main\DesignUi()->getStart(
				\Asset::icon('journals'),
				s("Le plan d'assolement de votre ferme"),
				$start,
				buttons: '<a href="'.\map\PlanUi::url($data->eStructure, $data->season).'" class="btn btn-xl btn-outline-primary">'.s("Configurer le plan de la ferme").'</a>'
			);

		} else {

			echo \main\DesignUi::getEmptyRead(
				$data->eStructure,
				s("Le plan d'assolement demande que le plan de la ferme soit configuré, et seul un responsable de la structure peut le faire."),
				s("Cette ferme n'a pas encore configuré son plan, son assolement n'est donc pas consultable.")
			);

		}

	}


});
new AdaptativeView('analyzeWorkingTime', function($data, StructureTemplate $t) {

	$t->nav = 'analyze-production';
	$t->subNav = 'working-time';

	$t->title = s("Analyse du planning de {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlAnalyzeWorkingTime($data->eStructure, $data->year, $data->category);

	if($data->years === []) {

		$t->mainTitle = \main\DesignUi::getPageHeader(s("Analyse du planning"));
		echo '<div class="util-block-info">';
			echo '<h4>'.s("Vous n'avez pas encore renseigné de temps de travail").'</h4>';
			echo '<p>'.s("L'analyse du planning sera disponible lorsque votre ferme aura démarré son activité !").'</p>';
		echo '</div>';

	} else {

		$t->mainTitle = \series\HeaderUi::getAnalyzeWorkingTime($data->eStructure, $data->years, $data->year, $data->month, $data->week, $data->category);

		$uiAnalyze = new \series\AnalyzeUi();

		echo match($data->category) {
			\structure\Member::TIME => $uiAnalyze->getBestActions($data->eStructure, $data->year, $data->month, $data->week, $data->globalTime, $data->cTimesheetAction, $data->cccTimesheetActionMonthly, $data->cTimesheetCategory, $data->ccTimesheetCategoryMonthly, $data->cTimesheetPlant, $data->ccTimesheetPlantMonthly, $data->cTimesheetSeries, $data->ccTimesheetSeriesMonthly, $data->monthly),
			\structure\Member::TEAM => $uiAnalyze->getWorkingTime($data->eStructure, $data->year, $data->month, $data->week, $data->ccWorkingTimeMonthly, $data->workingTimeWeekly, $data->ccTimesheetAction, $data->ccTimesheetCategory),
			\structure\Member::PACE => $uiAnalyze->getPace($data->eStructure, $data->years, $data->year, $data->cAction, $data->ccPlant, $data->ccPlantCompare, $data->yearCompare),
			\structure\Member::PERIOD => $uiAnalyze->getPeriod($data->year, $data->cWorkingTimeMonth, $data->cWorkingTimeMonthBefore, $data->cWorkingTimeWeek, $data->cWorkingTimeWeekBefore),
		};

	}


});
new AdaptativeView('analyzeCultivation', function($data, StructureTemplate $t) {

	$t->nav = 'analyze-production';
	$t->subNav = 'cultivation';

	$t->title = s("Analyse des cultures de {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlAnalyzeCultivation($data->eStructure, $data->season, $data->category);

	$t->js()->replaceHistory($t->canonical);

	$uiAnalyze = new \plant\AnalyzeUi();

	$t->mainTitle = \series\HeaderUi::getAnalyzeCultivation($data->eStructure, $data->seasons, $data->season, $data->category);

	echo match($data->category) {
		\structure\Member::AREA => $uiAnalyze->getArea($data->seasons, $data->season, $data->area, $data->areaByGreenhouse),
		\structure\Member::PLANT => $uiAnalyze->getPlant($data->season, $data->ccCultivationPlant, $data->search),
		\structure\Member::FAMILY => $uiAnalyze->getFamily($data->season, $data->area, $data->ccCultivationFamily, $data->search),
		\structure\Member::ROTATION => $uiAnalyze->getRotation($data->eStructure, $data->selectedSeasons, $data->area, $data->cFamily, $data->cBed, $data->rotations),
	};


});
?>
