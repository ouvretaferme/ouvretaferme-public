<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

	})
	->get(['/ferme/{id}/planning/{view}', '/ferme/{id}/planning/{view}/{period}', '/ferme/{id}/planning/{view}/{period}/{subPeriod}'], function($data) {

		$data->eStructure->validate('canPlanningLimited');

		$view = GET('view');

		switch($view) {

			case \structure\Member::DAILY :
			case \structure\Member::WEEKLY :
				$period = GET('period', fn($value) => Filter::check('week', $value), \Clock::week());
				$subPeriod = NULL;

				if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid(date_year($period)) === FALSE) {
					throw new NotExpectedAction('Invalid period');
				}
				break;

			case \structure\Member::YEARLY :
				$period = GET('period', 'int', \Clock::year());
				$subPeriod = GET('subPeriod', 'string', \Clock::format('n'));

				if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid($period) === FALSE) {
					throw new NotExpectedAction('Invalid period');
				}
				break;
				
			default :
				throw new NotExpectedAction('Invalid period');

		}

		\structure\MemberLib::setView('viewPlanning', $data->eStructure, $view);

		$data->entry = (\entry\EntrySetting::isEnabled($data->eStructure) and $data->eStructure->canPlanning());

		$data->cAction = \work\ActionLib::getByStructure($data->eStructure, index: 'id');
		\work\ActionLib::saveMain($data->cAction);

		$data->cCategory = \work\CategoryLib::getByStructure($data->eStructure, index: 'id');
		$data->cZone = \map\ZoneLib::getByStructure($data->eStructure);

		\map\PlotLib::putFromZone($data->cZone);

		$ePlot = \map\PlotLib::getById(GET('plot'));

		if($ePlot->notEmpty()) {
			$ePlot->validateProperty('structure', $data->eStructure);
		}

		$data->search = new Search([
			'plot' => $ePlot,
		]);

		if(get_exists('actionSelected')) {

			$eActionSelected = GET('actionSelected', 'work\Action');
			\structure\MemberLib::setView('viewPlanningAction', $data->eStructure, $eActionSelected);

		} else {
			$eActionSelected = $data->eStructure->getView('viewPlanningAction');
		}

		if(get_exists('userSelected')) {

			$eUserSelected = GET('userSelected', 'user\User');
			\structure\MemberLib::setView('viewPlanningUser', $data->eStructure, $eUserSelected);

		} else {
			$eUserSelected = $data->eStructure->getView('viewPlanningUser');
		}

		$data->search->set('user', $eUserSelected);

		if($eActionSelected->empty()) {
			$data->eActionSelected = $eActionSelected;
		} else {
			$data->eActionSelected = $data->cAction[$eActionSelected['id']] ?? new \work\Action();
		}

		if(get_exists('plantSelected')) {

			$ePlantSelected = GET('plantSelected', 'plant\Plant');
			\structure\MemberLib::setView('viewPlanningPlant', $data->eStructure, $ePlantSelected);

		} else {
			$ePlantSelected = $data->eStructure->getView('viewPlanningPlant');
		}

		// L'espèce vient aussi de la vue mémorisée du membre : une espèce d'une autre structure s'ignore, au lieu de refuser la page.
		if($ePlantSelected->empty()) {
			$data->ePlantSelected = $ePlantSelected;
		} else {
			$data->ePlantSelected = \plant\PlantLib::getByStructure($data->eStructure, id: $ePlantSelected['id']);
		}

		switch($data->eStructure->getView('viewPlanning')) {

			case \structure\Member::DAILY :

				$data->date = date('Y-m-d', strtotime(date('Y-m-d', strtotime($period)).' + '.($subPeriod - 1).' DAYS'));
				$data->week = $period;

				\series\RepeatLib::createForWeek($data->eStructure, $data->week);

				if($data->eStructure->canManage()) {

					if(get_exists('user')) {
						$data->eUserDaily = GET('user', 'user\User');
						\structure\MemberLib::setView('viewPlanningUserDaily', $data->eStructure, $data->eUserDaily);
					} else {
						$data->eUserDaily = $data->eStructure->getView('viewPlanningUser') ?? new \user\User();
					}

				} else {
					$data->eUserDaily = $data->eUserOnline;
				}

				$data->seasonsWithSeries = \series\SeriesLib::getSeasonsAround($data->eStructure, week_year($data->week));

				[
					$data->ccTimesheet,
					$data->cccTask
				] = \series\TaskLib::getForDaily($data->eStructure, $data->week, $data->eUserDaily, $data->cAction, $data->search);

				$data->cUserStructure = \structure\MemberLib::getUsersByStructureForPeriod($data->eStructure, week_date_starts($data->week), week_date_ends($data->week), withPresenceAbsence: TRUE);

				$data->cccTaskAssign = \series\TaskLib::getForAssign($data->eStructure, $data->week, $data->cAction);

				\hr\WorkingTimeLib::fillByWeekFromUsers($data->eStructure, $data->cUserStructure, $data->week);
				\series\TimesheetLib::fillTimesByDate($data->eStructure, $data->cUserStructure, $data->week);
				break;

			case \structure\Member::WEEKLY :

				$data->week = $period;

				\series\RepeatLib::createForWeek($data->eStructure, $data->week);

				$data->cccTask = \series\TaskLib::getForWeek($data->eStructure, $data->week, $data->cAction, $data->search);

				$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);

				$data->seasonsWithSeries = \series\SeriesLib::getSeasonsAround($data->eStructure, week_year($data->week));

				$data->cUserStructure = \structure\MemberLib::getUsersByStructureForPeriod($data->eStructure, week_date_starts($data->week), week_date_ends($data->week), withPresenceAbsence: TRUE);

				$data->eUserTime = $data->eUserOnline;
				$data->eUserTime['weekTimesheet'] = \series\TimesheetLib::getTimesByWeek($data->eStructure, $data->eUserTime, $data->week);
				$data->eUserTime['cWorkingTimeWeek'] = \hr\WorkingTimeLib::getByWeek($data->eStructure, $data->eUserTime, $data->week);
				$data->eUserTime['cPresence'] = $data->cUserStructure[$data->eUserTime['id']]['cPresence'] ?? new Collection();
				$data->eUserTime['cAbsence'] = $data->cUserStructure[$data->eUserTime['id']]['cAbsence'] ?? new Collection();
				break;

			case \structure\Member::YEARLY :

				$data->week = NULL;
				$data->year = (int)$period;
				$data->month = (int)$subPeriod;

				// Le 4 d'un mois est toujours dans une semaine ISO de la même année, janvier compris.
				$data->weekNewPlanning = ($data->year === \Clock::year() and $data->month === (int)\Clock::format('n'))
					? \Clock::week()
					: toWeek(sprintf('%04d-%02d-04', $data->year, $data->month));

				$data->search->set('action', $data->eActionSelected);
				$data->search->set('plant', $data->ePlantSelected);

				\series\RepeatLib::createForYear($data->eStructure, $data->year);

				$data->cUserStructure = \structure\MemberLib::getUsersByStructureForPeriod($data->eStructure, $data->year.'-01-01', $data->year.'-12-31', withPresenceAbsence: TRUE);

				$data->ccTask = \series\TaskLib::getByYear($data->eStructure, $data->year, $data->search);

				$data->cPlant = \plant\PlantLib::getByStructure($data->eStructure);

				break;

		}

		if($eUserSelected->notEmpty()) {
			$data->search->set('user', $data->cUserStructure[$eUserSelected['id']] ?? new \user\User());
		}

		throw new ViewAction($data, ':planning');

	})
	->get('/ferme/{id}/intervention/{week}/{action}', function($data) {

		$data->eStructure->validate('canPlanningLimited');

		$data->week = \series\Task::GET('week', 'plannedWeek', \Clock::week());

		$data->eAction = \work\ActionLib::getById(GET('action'))->validate('canRead');
		$data->eAction->validateProperty('structure', $data->eStructure);

		\work\ActionLib::getMainByStructure($data->eStructure);

		\series\RepeatLib::createForWeek($data->eStructure, $data->week);

		$data->cTask = \series\TaskLib::getByWeekAndAction($data->eStructure, $data->week, $data->eAction);

		foreach($data->cTask as $eTask) {

			if($eTask['cultivation']->empty()) {
				$eTask['cCultivation'] = \series\CultivationLib::getBySeries($eTask['series']);
			} else {
				$eTask['cCultivation'] = new Collection([$eTask['cultivation']->merge([
					'series' => $eTask['series'],
					'plant' => $eTask['plant']
				])]);
			}
			foreach($eTask['cCultivation'] as $eCultivation) {
				\series\CultivationLib::fillSliceStats($eCultivation);
			}

		}

		throw new ViewAction($data);

	})
	->get(['/ferme/{id}/series', '/ferme/{id}/series/{season}'], function($data) {

		$data->season = \structure\MemberLib::getDynamicSeason($data->eStructure, GET('season', 'int'));

		if(get_exists('view')) {

			$data->view = \structure\Member::GET('view', 'viewSeries', \structure\Member::AREA);

			if(
				$data->view === \structure\Member::WORKING_TIME and
				$data->eStructure->conf()['time'] === FALSE
			) {
				$data->view = \structure\Member::AREA;
			}

			\structure\MemberLib::setView('viewSeries', $data->eStructure, $data->view);

		} else {
			$data->view = $data->eStructure->getView('viewSeries');
		}

		if(get_exists('harvestExpected')) {
			\structure\MemberLib::setView('viewPlanningHarvestExpected', $data->eStructure, \structure\Member::GET('harvestExpected', 'viewPlanningHarvestExpected', \structure\Member::TOTAL));
		}

		if(get_exists('field')) {
			\structure\MemberLib::setView('viewPlanningField', $data->eStructure, \structure\Member::GET('field', 'viewPlanningField', \structure\Member::SOIL));
		}

		if(get_exists('area')) {
			\structure\MemberLib::setView('viewPlanningArea', $data->eStructure, \structure\Member::GET('area', 'viewPlanningArea', \structure\Member::LENGTH));
		}

		$data->cSeriesImportPerennial = \series\SeriesLib::getImportPerennial($data->eStructure, $data->season);

		$data->nSeries = \series\SeriesLib::countByStructure($data->eStructure, $data->season);

		if($data->nSeries === 0) {
			$data->firstSeries = (\series\SeriesLib::countByStructure($data->eStructure) === 0);
		} else {
			$data->firstSeries = FALSE;
		}

		\work\ActionLib::getMainByStructure($data->eStructure);

		// Par où ce lecteur emporterait ce plan chez lui.
		//
		// ⚠️ Posé avant le `switch` et non dans une seule de ses branches : l'en-tête est le même
		// sur les trois vues et le lit à chaque fois.
		//
		// ⚠️ Rien à chercher pour qui administre la ferme : les deux rendus qui s'en servent, le
		// bouton CSV de l'en-tête et la barre d'emport, se refusent tous deux à ce lecteur, et
		// l'appel lit les rattachements de la ferme.
		$data->eStructureCommunityExport = $data->eStructure->canManage()
			? new \structure\Structure()
			: $data->eStructure->getExportCommunity();

		switch($data->view) {

			case \structure\Member::AREA :
				$data->ccCultivation = \series\CultivationLib::getForArea($data->eStructure, $data->season);
				$data->eActionHarvest = \work\ActionLib::getByStructure($data->eStructure, fqn: ACTION_RECOLTE);
				break;

			case \structure\Member::HARVESTING :
				$data->ccCultivation = \series\CultivationLib::getForHarvesting($data->eStructure, $data->season);
				break;

			case \structure\Member::WORKING_TIME :
				$data->ccCultivation = \series\CultivationLib::getWorkingTimeByStructure($data->eStructure, $data->season);
				break;

		}

		throw new ViewAction($data, ':series');

	})
	->get(['/ferme/{id}/previsionnel', '/ferme/{id}/previsionnel/{season}'], function($data) {

		$data->eStructure->validate('canAnalyzeProduction');

		$data->season = \structure\MemberLib::getDynamicSeason($data->eStructure, GET('season', 'int'));

		\work\ActionLib::getMainByStructure($data->eStructure);

		$cccCultivation = \series\CultivationLib::getForForecast($data->eStructure, $data->season);
		$data->ccForecast = \plant\ForecastLib::getByStructure($data->eStructure, $data->season, $cccCultivation);

		// L'astuce parle des séries déjà là : un prévisionnel vide a sa propre bande de démarrage.
		if($data->ccForecast->notEmpty()) {
			$data->tip = \structure\TipLib::pickOne($data->eUserOnline, 'series-forecast-help');
		}

		$data->salesForecast = $data->ccForecast->sum('salesForecast', depth: 2);
		$data->salesSeries = $data->ccForecast->sum('salesSeries', depth: 2);

		throw new ViewAction($data, ':forecast');

	})
	->get(['/ferme/{id}/semences-plants', '/ferme/{id}/semences-plants/{season}'], function($data) {

		$data->eStructure->validate('canAnalyzeProduction');
		$data->season = \structure\MemberLib::getDynamicSeason($data->eStructure, GET('season', 'int'));

		\work\ActionLib::getMainByStructure($data->eStructure);

		$data->nSeries = \series\SeriesLib::countByStructure($data->eStructure, $data->season);

		$data->search = new Search([
			'supplier' => \work\SupplierLib::getById(GET('supplier')),
			'seedling' => \series\Cultivation::GET('seedling', 'seedling'),
			'week' => \series\CultivationLib::getSeedlingWeek(),
			'groupWeek' => GET('groupWeek', 'bool')
		]);

		$data->cSupplier = \work\SupplierLib::getByStructure($data->eStructure);
		$data->items = $data->search->get('groupWeek') ?
			\series\CultivationLib::getForSeedlingByWeek($data->eStructure, $data->season, $data->search) :
			\series\CultivationLib::getForSeedling($data->eStructure, $data->season, $data->search);

		throw new ViewAction($data, ':seedling');

	})
	->get(['/ferme/{id}/assolement', '/ferme/{id}/assolement/{season}'], function($data) {

		// ⚠️ Le mode voyage sur $data, il ne se relit pas depuis l'adhésion. `setView()` est un
		// souvenir pour la prochaine visite, et il se perd sans adhésion
		// (`structure/lib/Member.l.php:438`) : une vue qui le relirait pour retrouver la décision
		// que sa route vient de prendre obtiendrait le défaut du schéma. Même forme que
		// `series.p.php:440`.
		$data->soilView = \structure\MemberLib::getSoilView($data->eStructure, GET('view'));

		\structure\MemberLib::setView('viewSoil', $data->eStructure, $data->soilView);

		$data->season = \structure\MemberLib::getDynamicSeason($data->eStructure, GET('season', 'int'));
		\map\SeasonLib::setOnline($data->season);

		$data->cZone = \map\ZoneLib::getByStructure($data->eStructure, season: $data->season);
		$data->eZoneSelected = GET('zone', 'map\Zone');

		\work\ActionLib::getMainByStructure($data->eStructure);


		$seasonsSeries = [$data->season + 1, $data->season, $data->season - 1];

		\map\PlotLib::putFromZoneWithSeries($data->eStructure, $data->cZone, $data->season, $seasonsSeries, onlySeries: TRUE);

		\series\CultivationLib::putSearchVocabulary($data->eStructure, $data->cZone);

		$data->hasGardenGroups = (\series\GardenChairsAiLib::getGroups($data->cZone) !== []);

		$data->placeHelper = \structure\MemberLib::hasHelper($data->eStructure, \series\PlaceLib::HELPER_EDITING) ? '' : \series\PlaceLib::HELPER_EDITING;

		throw new ViewAction($data, ':soil');

	})
	->get(['/ferme/{id}/rotation', '/ferme/{id}/rotation/{season}'], function($data) {

		// ⚠️ Sans cette ligne, la rotation d'une ferme sans adhésion rendait une 500 : l'écriture
		// se perdait, la vue relisait `viewSoil` et recevait le défaut du schéma — `garden` —
		// donc elle prenait la branche du plan et lisait `$data->placeHelper`, que seule la route
		// assolement pose. Mesuré le 2026-09-05 sur `/ferme/2/rotation/2027`.
		$data->soilView = \structure\Member::ROTATION;

		\structure\MemberLib::setView('viewSoil', $data->eStructure, $data->soilView);

		$data->season = \structure\MemberLib::getDynamicSeason($data->eStructure, GET('season', 'int'));
		\map\SeasonLib::setOnline($data->season);

		$data->selectedSeasons = $data->eStructure->conf(\map\Configuration::class)->getRotationSeasons($data->season);

		$data->cZone = \map\ZoneLib::getByStructure($data->eStructure, season: $data->season);
		$data->eZoneSelected = GET('zone', 'map\Zone');
		\map\PlotLib::putFromZoneWithSeries($data->eStructure, $data->cZone, $data->season, $data->selectedSeasons, onlySeries: TRUE);
		\map\BedLib::putFamilyReturns($data->cZone, $data->season, $data->eStructure->conf(\map\Configuration::class)['rotationYears']);

		$data->search = new Search([
			'cFamily' => \plant\FamilyLib::getList(),
			'family' => GET('family', 'plant\Family'),
			'seen' => GET('seen', '?int'),
			'bed' => GET('bed', 'bool')
		]);

		\series\PlaceLib::filterRotationsByFamily($data->eStructure, $data->cZone, $data->search);

		throw new ViewAction($data, ':soil');

	})
	->get(['/ferme/{id}/analyses/planning', '/ferme/{id}/analyses/planning/{year}', '/ferme/{id}/analyses/planning/{year}/{category}'], function($data) {

		$data->eStructure->validate('canAnalyzeProduction');

		if($data->eStructure->conf()['time'] === FALSE) {
			throw new RedirectAction(\structure\StructureUi::urlAnalyzeCultivation($data->eStructure));
		}

		$data->years = \series\AnalyzeLib::getYears($data->eStructure);

		if($data->years) {

			if(get_exists('year')) {
				$selectedYear = GET('year', 'int');
				$data->year = in_array($selectedYear, $data->years) ? $selectedYear : first($data->years);
				\structure\MemberLib::setView('viewAnalyzeYear', $data->eStructure, $data->year);
			} else {
				$currentYear = $data->eStructure->getView('viewAnalyzeYear');
				$data->year = in_array($currentYear, $data->years) ? $currentYear : first($data->years);
			}

			$data->category = GET('category', 'string', $data->eStructure->getView('viewPlanningCategory'));
			$data->month = GET('month', fn($value) => Filter::check(['?int', 'min' => 1, 'max' => 12], $value));
			$data->week = GET('week', '?string');

			switch($data->category) {

				case \structure\Member::TIME :

					\structure\MemberLib::setView('viewPlanningCategory', $data->eStructure, \structure\Member::TIME);

					$data->monthly = get_exists('monthly');

					$data->globalTime = \series\AnalyzeLib::getGlobalWorkingTime($data->eStructure, $data->year, $data->month, $data->week);
					$data->cTimesheetAction = \series\AnalyzeLib::getActionTimesheet($data->eStructure, $data->year, $data->month, $data->week);
					$data->cTimesheetCategory = \series\AnalyzeLib::getCategoryTimesheet($data->eStructure, $data->year, $data->month, $data->week);
					$data->cTimesheetPlant = \series\AnalyzeLib::getPlantsTimesheet($data->eStructure, $data->year, $data->month, $data->week);
					$data->cTimesheetSeries = \series\AnalyzeLib::getSeriesTimesheet($data->eStructure, $data->year, $data->month, $data->week);

					if(
						($data->month === NULL and $data->week === NULL) or
						$data->monthly
					) {
						$data->ccTimesheetSeriesMonthly = \series\AnalyzeLib::getSeriesMonthly($data->eStructure, $data->year);
					} else {
						$data->ccTimesheetSeriesMonthly = new Collection();
					}

					if($data->monthly) {
						$data->cccTimesheetActionMonthly = \series\AnalyzeLib::getActionMonthly($data->eStructure, $data->year);
						$data->ccTimesheetCategoryMonthly = \series\AnalyzeLib::getCategoryMonthly($data->eStructure, $data->year);
						$data->ccTimesheetPlantMonthly = \series\AnalyzeLib::getMonthlyPlantsTimesheet($data->eStructure, $data->year);
					} else {
						$data->cccTimesheetActionMonthly = new Collection();
						$data->ccTimesheetCategoryMonthly = new Collection();
						$data->ccTimesheetPlantMonthly = new Collection();
					}

					break;

				case \structure\Member::TEAM :

					\structure\MemberLib::setView('viewPlanningCategory', $data->eStructure, \structure\Member::TEAM);

					$data->ccWorkingTimeMonthly = \series\AnalyzeLib::getMonthlyWorkingTime($data->eStructure, $data->year, $data->month, $data->week);
					$data->workingTimeWeekly = \series\AnalyzeLib::getWeeklyWorkingTime($data->eStructure, $data->year, $data->month, $data->week);
					$data->ccTimesheetAction = \series\AnalyzeLib::getActionTimesheetByUser($data->eStructure, $data->year, $data->month, $data->week);
					$data->ccTimesheetCategory = \series\AnalyzeLib::getCategoryTimesheetByUser($data->eStructure, $data->year, $data->month, $data->week);
					break;

				case \structure\Member::PACE :

					\structure\MemberLib::setView('viewPlanningCategory', $data->eStructure, \structure\Member::PACE);

					$data->cAction = \work\ActionLib::getByStructureWithPace($data->eStructure);
					$data->ccPlant = \series\CultivationLib::getPaceByStructure($data->eStructure, $data->year, $data->cAction);

					$data->yearCompare = GET('compare', '?int');

					if($data->yearCompare !== NULL) {
						$data->ccPlantCompare = \series\CultivationLib::getPaceByStructure($data->eStructure, $data->yearCompare, $data->cAction);
					} else {
						$data->ccPlantCompare = new Collection();
					}
					break;

				case \structure\Member::PERIOD :

					\structure\MemberLib::setView('viewPlanningCategory', $data->eStructure, \structure\Member::PERIOD);

					$data->cWorkingTimeMonth = \series\AnalyzeLib::getStructureMonths($data->eStructure, $data->year);
					$data->cWorkingTimeMonthBefore = \series\AnalyzeLib::getStructureMonths($data->eStructure, $data->year - 1);
					
					$data->cWorkingTimeWeek = \series\AnalyzeLib::getStructureWeeks($data->eStructure, $data->year);
					$data->cWorkingTimeWeekBefore = \series\AnalyzeLib::getStructureWeeks($data->eStructure, $data->year - 1);
					break;

				default :
					throw new NotExpectedAction('Invalid category');

			}

		} else {
			$data->year = NULL;
			$data->category = NULL;
		}

		throw new ViewAction($data, ':analyzeWorkingTime');

	})
	->get(['/ferme/{id}/analyses/cultures', '/ferme/{id}/analyses/cultures/{season}/{category}'], function($data) {

		$data->eStructure->validate('canAnalyzeProduction');

		$data->seasons = $data->eStructure->conf(\map\Configuration::class)->getSeasons();

		if(get_exists('season')) {
			$selectedSeason = GET('season', 'int');
			$data->season = in_array($selectedSeason, $data->seasons) ? $selectedSeason : first($data->seasons);
			\structure\MemberLib::setView('viewAnalyzeYear', $data->eStructure, $data->season);
		} else {
			$currentYear = $data->eStructure->getView('viewAnalyzeYear');
			$data->season = in_array($currentYear, $data->seasons) ? $currentYear : last($data->seasons);
		}

		$data->category = GET('category', 'string', $data->eStructure->getView('viewCultivationCategory'));

		switch($data->category) {

			case \structure\Member::AREA :

				\structure\MemberLib::setView('viewCultivationCategory', $data->eStructure, \structure\Member::AREA);

				$data->area = \plant\AnalyzeLib::getArea($data->eStructure, $data->seasons);
				$data->areaByGreenhouse = \plant\AnalyzeLib::getAreaByGreenhouse($data->eStructure, $data->season);
				break;

			case \structure\Member::PLANT :

				\structure\MemberLib::setView('viewCultivationCategory', $data->eStructure, \structure\Member::PLANT);

				$data->search = new Search([
					'cycle' => \series\Series::GET('cycle', 'cycle'),
					'use' => \series\Series::GET('use', 'use'),
				], REQUEST('sort'));

				$data->ccCultivationPlant = \plant\AnalyzeLib::getPlants($data->eStructure, $data->season, $data->search);
				break;

			case \structure\Member::FAMILY :

				\structure\MemberLib::setView('viewCultivationCategory', $data->eStructure, \structure\Member::FAMILY);

				$data->area = \plant\AnalyzeLib::getArea($data->eStructure, $data->seasons);

				$data->search = new Search([
					'cycle' => \series\Series::GET('cycle', 'cycle'),
					'use' => \series\Series::GET('use', 'use'),
				], REQUEST('sort'));

				$data->ccCultivationFamily = \plant\AnalyzeLib::getFamilies($data->eStructure, $data->season, $data->search);
				break;

			case \structure\Member::ROTATION :

				\structure\MemberLib::setView('viewCultivationCategory', $data->eStructure, \structure\Member::ROTATION);

				$data->selectedSeasons = $data->eStructure->conf(\map\Configuration::class)->getRotationSeasons($data->season);
				$data->selectedSeasons = array_reverse($data->selectedSeasons);

				$data->area = \plant\AnalyzeLib::getArea($data->eStructure, [$data->season])[$data->season];

				// Planches de l'année
				$data->cBed = \map\BedLib::getByStructure($data->eStructure, $data->season);

				// Recherche des cultures dans les planches de l'année
				$ccccPlaceRotation = \series\PlaceLib::getForRotations($data->eStructure, $data->cBed, $data->selectedSeasons);

				$data->cFamily = new Collection();
				$ccccPlaceRotation->map(fn($ePlace) => $ePlace['family']->notEmpty() ? ($data->cFamily[$ePlace['family']['id']] = $ePlace['family']) : NULL, 4);
				$data->cFamily->sort('name');

				$data->rotations = \series\PlaceLib::getRotationsStats($ccccPlaceRotation);
				break;

			default :
				throw new NotExpectedAction('Invalid category');

		}


		throw new ViewAction($data, ':analyzeCultivation');

	});

?>
