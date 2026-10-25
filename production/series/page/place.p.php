<?php
$requiresSeries = function(\stdClass $data): void {

	if($data->source === 'bed') {
		throw new NotExpectedAction('Missing series');
	}

};

$given = function(string $name): bool {

	return (input_exists($name) and INPUT($name) !== '');

};

$refusesClosed = function(\stdClass $data): void {

	if($data->source !== 'bed' and $data->e['status'] === \series\Series::CLOSED) {

		// Le FailWatch se crée AVANT le log : createWatcher() part de zéro, donc un
		// watch né après l'échec ne le voit pas et validate() ne lève jamais. Le
		// garde journalisait dans le vide et l'assolement d'une série clôturée
		// restait modifiable.
		$fw = new \FailWatch();

		\Fail::log('series\Series::closed');

		$fw->validate();

	}

};

/**
 * Ce que la bulle de survol montre d'une série, préparé ici parce qu'une UI ne va
 * pas chercher sa donnée. Une seule source de semaines : le réalisé s'il existe,
 * le prévu sinon, et l'étiquette dit laquelle. Les fondre comme le fait
 * $putSoilDates décrit une union qui ne correspond à aucune réalité — mesuré,
 * 6,8 % des cultures portent les deux et leurs ensembles sont distincts.
 */
$putSoilTip = function(\series\Series $eSeries): void {

	$weeks = [];
	$done = FALSE;
	$start = NULL;
	$startAction = NULL;

	foreach($eSeries['cCultivation'] as $eCultivation) {

		$real = $eCultivation['harvestWeeks'] ?? [];
		$expected = $eCultivation['harvestWeeksExpected'] ?? [];

		if($real !== []) {
			$done = TRUE;
			$weeks = array_merge($weeks, $real);
		} else if($done === FALSE) {
			$weeks = array_merge($weeks, $expected);
		}

		if($eCultivation['startAction'] !== NULL) {

			$week = $eCultivation->getStartWeek($eCultivation['season']);

			if($week !== NULL) {

				$number = week_number($week);

				if($start === NULL or $number < $start) {
					$start = $number;
					$startAction = $eCultivation['startAction'];
				}

			}

		}

	}

	$weeks = array_values(array_unique($weeks));
	sort($weeks);

	$gaps = 0;

	for($i = 1; $i < count($weeks); $i++) {

		$previous = strtotime(week_date_starts($weeks[$i - 1]));
		$current = strtotime(week_date_starts($weeks[$i]));

		if($current - $previous > 8 * 24 * 3600) {
			$gaps++;
		}

	}

	$count = count($weeks);

	$eSeries['soilTip'] = [
		'count' => $count,
		'first' => ($count > 0) ? week_number($weeks[0]) : NULL,
		'last' => ($count > 0) ? week_number($weeks[$count - 1]) : NULL,
		'done' => $done,
		'gaps' => $gaps,
		'start' => $start,
		'startAction' => $startAction,
	];

};

$viewSoil = function(\stdClass $data): string {

	return GET('view', [\structure\Member::PLAN, \structure\Member::GARDEN])
		?? $data->e['structure']->getView('viewSoil');

};

$putSoilDates = function(\series\Series $eSeries): void {

	$soilDates = [];

	foreach($eSeries['cCultivation'] as $eCultivation) {

		if($eCultivation['startAction'] !== NULL) {

			$key = ($eCultivation['startAction'] === \series\Cultivation::SOWING) ? 'sowing' : 'planting';
			$week = $eCultivation->getStartWeek($eCultivation['season']);

			if($week !== NULL and ($soilDates[$key] ?? NULL) === NULL) {
				$soilDates[$key] = $week;
			} else if($week !== NULL) {
				$soilDates[$key] = min($soilDates[$key], $week);
			}

		}

		$harvests = array_merge($eCultivation['harvestWeeksExpected'] ?? [], $eCultivation['harvestWeeks'] ?? []);

		if($harvests !== []) {
			$soilDates['harvest'] = [
				min($harvests[0], $soilDates['harvest'][0] ?? $harvests[0], ...$harvests),
				max($harvests[0], $soilDates['harvest'][1] ?? $harvests[0], ...$harvests),
			];
		}

	}

	$eSeries['soilDates'] = $soilDates;

};

$ungrouped = function(): array {

	$value = input_exists('ungroup') ? (string)INPUT('ungroup') : '';

	if($value === '') {
		return [];
	}

	$ungrouped = [];

	foreach(explode('_', $value) as $part) {

		[$series, $beds] = array_pad(explode(':', $part, 2), 2, '');

		if(ctype_digit($series) === FALSE or $beds === '') {
			continue;
		}

		$list = array_values(array_filter(array_map('intval', explode('.', $beds))));

		if($list !== []) {
			$ungrouped[(int)$series] = $list;
		}

	}

	return $ungrouped;

};

new Page(function($data) use($viewSoil, $ungrouped, $given) {

		$data->ungrouped = $ungrouped();

		$data->eZoneSelected = GET('zone', 'map\Zone');

		if($given('cultivation')) {

			$data->eCultivation = \series\CultivationLib::getById(INPUT('cultivation'))->validate('canWrite');

			$data->e = $data->eCultivation['series'];
			$data->e['structure'] = \structure\StructureLib::getById($data->eCultivation['structure']);

			$data->source = 'cultivation';

		} else if($given('series')) {

			$data->e = \series\SeriesLib::getById(INPUT('series'))->validate('canWrite');
			$data->e['structure'] = \structure\StructureLib::getById($data->e['structure']);

			$data->source = 'series';

		} else if($given('task')) {

			$data->e = \series\TaskLib::getById(INPUT('task'))->validate('canWrite', 'acceptSoil');
			$data->e['structure'] = \structure\StructureLib::getById($data->e['structure']);
			$data->e['season'] = ($data->e['doneWeek'] ?? $data->e['plannedWeek']) ?
				week_year($data->e['doneWeek'] ?? $data->e['plannedWeek']) :
				\Clock::year();
			$data->e['use'] = \series\Series::BED;
			$data->e['bedWidth'] = NULL;

			$data->source = 'task';

		} else if($given('bed')) {

			$eBed = \map\BedLib::getById(INPUT('bed'))
				->validate(\Route::getRequestMethod() === 'POST' ? 'canWrite' : 'canRead');

			$data->e = new \series\Series([
				'structure' => \structure\StructureLib::getById($eBed['structure']),
				'season' => INPUT('season', 'int') ?: \Clock::year(),
			]);

			$data->source = 'bed';

		} else {
			throw new NotExpectedAction('Missing entry');
		}

		$data->eContext = input_exists('context') ?
			\series\SeriesLib::getById(INPUT('context'))->validate('canWrite') :
			new \series\Series();

		$data->gesture = POST('gesture', ['move', 'copy', 'add', 'remove', 'resize', 'complete', 'length', 'empty', 'swap'], 'add');

		// D'où le panneau a été ouvert. La fiche série affiche son assolement en
		// dessous, le plan d'assolement non, et les deux postent les mêmes actions sur
		// les mêmes éléments : rien dans la requête ne les distingue sans cela.
		$data->origin = INPUT('origin', ['series']);

		$data->viewSoil = $viewSoil($data);

		$data->search = ($data->source === 'bed') ?
			new Search() :
			new Search([
				'canWidth' => \map\BedLib::countWidthsByStructure($data->e['structure'], $data->e['season']) > 1,
				'mode' => GET('mode', [NULL, \map\Plot::GREENHOUSE, \map\Plot::OPEN_FIELD]) ?? \series\PlaceLib::getSearchMode($data->e),
			] + \series\PlaceLib::getFilterInput());

	})
	->get('update', function($data) use($refusesClosed, $putSoilDates) {

		$refusesClosed($data);

		\structure\MemberLib::addHelper($data->e['structure'], \series\PlaceLib::HELPER_EDITING);

		if(
			$data->source === 'series' or
			$data->source === 'cultivation'
		) {
			\series\SeriesLib::fillTimeline($data->e);
		}

		$view = match($data->source) {
			'cultivation' => 'updateCultivation',
			'series' => (GET('inline') === '1') ? 'updateCultivation' : 'updateModal',
			'task' => 'updateModal',
		};

		// Témoin du contrôle de fidélité, réservé au dev : le plan entier est rendu au
		// lieu du paquet d'état, pour vérifier que le paquet ne perd rien.
		$data->full = (LIME_ENV === 'dev' and GET('full') === '1');

		// On récupère les emplacements
		$data->cZone = \map\ZoneLib::getByStructure($data->e['structure'], season: $data->e['season']);

		if($view === 'updateCultivation' and $data->full === FALSE) {
			\map\PlotLib::putFromZoneWithSeriesBounds($data->cZone, $data->e['season'], [$data->e['season'], $data->e['season'] - 1, $data->e['season'] + 1]);
		} else {
			\map\PlotLib::putFromZoneWithSeries($data->e['structure'], $data->cZone, $data->e['season'], [$data->e['season'], $data->e['season'] - 1, $data->e['season'] + 1], onlySeries: ($data->source !== 'task'));
		}

		$data->e['cPlace'] = \series\PlaceLib::getByElement($data->e);

		\work\ActionLib::getMainByStructure($data->e['structure']);

		switch($data->source) {

			case 'cultivation' :
			case 'series' :

				$putSoilDates($data->e);

				$data->ccCultivation = \series\CultivationLib::getForSelector($data->e['structure'], $data->e['season'], $data->e);

				\map\ZoneLib::test($data->cZone, $data->search, $data->e);

				break;

			case 'task' :

				$data->search = new Search();

				break;

		}

		// Sans bac — sur petit écran — le plan demande le mode placement pour
		// une série, et le veut rendu dans la page plutôt qu'en panneau.
		throw new \ViewAction($data, ':'.$view);

	})
	->get('panel', function($data) use($putSoilDates) {

		\series\SeriesLib::fillTimeline($data->e);

		$data->e['cPlace'] = \series\PlaceLib::getByElement($data->e);

		$putSoilDates($data->e);

		$data->eContext = $data->e;

		\series\PlaceLib::putPlanContext($data, onlySeriesCards: TRUE);

		throw new \ViewAction($data, ':panel');

	})
	->get('bedPanel', function($data) {

		$data->eBed = \map\BedLib::getById(GET('bed'))->validate('canRead');

		if($data->eBed['structure']['id'] !== $data->e['structure']['id']) {
			throw new NotExpectedAction('Invalid bed');
		}

		$data->eSeriesContext = ($data->source === 'bed') ? new \series\Series() : $data->e;

		$data->cSeriesBed = \series\PlaceLib::getSeriesByBed($data->eBed, $data->e['season']);
		$data->rotations = \series\PlaceLib::getRotationsByBed($data->eBed, $data->e['season']);
		$data->freeGap = \series\PlaceLib::getFreeGapByBed($data->cSeriesBed);
		$data->cBedSwap = \map\BedLib::getByPlot($data->eBed['plot'], $data->e['season']);

		// La planche comme rattachement : le menu en tire la liste des notes filtrée sur elle.
		$data->eBed['eTarget'] = new \note\Target([
			'targetType' => \map\NoteTargetLib::getType(),
			'targetId' => $data->eBed['id']
		]);

		// La structure se relit entière, l'ouverture des notes lisant son type.
		$data->cNoteLinked = \note\NoteLib::getByTargets(\structure\StructureLib::getById($data->eBed['structure']['id']), [
			$data->eBed['eTarget']['targetType'] => [$data->eBed['id']]
		]);

		throw new \ViewAction($data, ':bedPanel');

	})
	->get('ungroupAi', function($data) use($requiresSeries) {

		$requiresSeries($data);

		\series\PlaceLib::putPlanContext($data, onlySeriesCards: TRUE);

		$data->targetBeds = GET('beds', 'array');

		throw new \ViewAction($data, ':ungroupAi');

	})
	->post('doMove', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();


		$data->flashBeds = POST('to', 'array');

		\series\PlaceLib::moveBeds($data->e, POST('from', 'array'), POST('to', 'array'), input_exists('size') ? POST('size', 'int') : NULL);

		$fw->validate();

		\series\PlaceLib::putPlanContext($data, onlySeriesCards: TRUE);

		$data->targetBeds = array_merge(POST('from', 'array'), POST('to', 'array'));

		throw new \ViewAction($data, ':doTargetAi');

	})
	->post('doAdd', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();


		$data->flashBeds = POST('beds', 'array');

		\series\PlaceLib::addBeds($data->e, POST('beds', 'array'), input_exists('size') ? POST('size', 'int') : NULL);

		$fw->validate();

		\series\PlaceLib::putPlanContext($data, onlySeriesCards: TRUE);

		$data->targetBeds = POST('beds', 'array');

		throw new \ViewAction($data, ':doTargetAi');

	})
	->post('doRemove', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();


		$data->flashBeds = POST('beds', 'array');

		\series\PlaceLib::removeBeds($data->e, POST('beds', 'array'));

		$fw->validate();

		\series\PlaceLib::putPlanContext($data, onlySeriesCards: TRUE);

		$data->targetBeds = POST('beds', 'array');

		throw new \ViewAction($data, ':doTargetAi');

	})
	->post('doMoveMany', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();

		$moves = json_decode(POST('moves'), TRUE) ?? [];


		$data->flashBeds = array_merge(...array_map(fn($move) => $move['to'] ?? [], $moves));

		\series\PlaceLib::moveMany($data->e['structure'], $moves);

		$fw->validate();

		\series\PlaceLib::putPlanContext($data);

		throw new \ViewAction($data, ':doMove');

	})
	->post('doLength', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();

		$eBed = \map\BedLib::getById(POST('bed'))->validate('canWrite');


		$data->flashBeds = [$eBed['id']];

		\series\PlaceLib::setSize($data->e, $eBed, POST('size', 'int'));

		$fw->validate();

		\series\PlaceLib::putPlanContext($data, onlySeriesCards: TRUE);

		$data->targetBeds = [$eBed['id']];

		throw new \ViewAction($data, ':doTargetAi');

	})
	->post('doEmptyBed', function($data) use($refusesClosed) {

		$refusesClosed($data);

		$fw = new \FailWatch();

		$eBed = \map\BedLib::getById(POST('bed'))->validate('canWrite');


		\series\PlaceLib::emptyBed($eBed, $data->e['season']);

		$fw->validate();

		if($data->source === 'bed') {
			throw new \ReloadAction();
		}

		\series\PlaceLib::putPlanContext($data);

		throw new \ViewAction($data, ':doMove');

	})
	->post('doSwapBed', function($data) use($refusesClosed) {

		$refusesClosed($data);

		$fw = new \FailWatch();

		$eBed = \map\BedLib::getById(POST('bed'))->validate('canWrite');
		$eBedSwap = \map\BedLib::getById(POST('swap'))->validate('canWrite');


		\series\PlaceLib::swapBeds($eBed, $eBedSwap, $data->e['season']);

		$fw->validate();

		if($data->source === 'bed') {
			throw new \ReloadAction();
		}

		\series\PlaceLib::putPlanContext($data);

		throw new \ViewAction($data, ':doMove');

	})
	->post('doRemoveSeries', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();


		\series\PlaceLib::clearSeries($data->e);

		$fw->validate();

		\series\PlaceLib::putPlanContext($data);

		throw new \ViewAction($data, ':doMove');

	})
	->post('doUpdate', function($data) use($requiresSeries, $refusesClosed) {

		$requiresSeries($data);
		$refusesClosed($data);

		$fw = new \FailWatch();

		$cPlace = \series\PlaceLib::buildFromBeds(
			match($data->source) {
				'series', 'cultivation' => 'series',
				'task' => 'task',
			},
			$data->e,
			POST('beds', 'array'),
			POST('sizes', 'array')
		);

		if($fw->ok()) {

			match($data->source) {
				'series', 'cultivation' => \series\PlaceLib::replaceForSeries($data->e, $cPlace),
				'task' => \series\PlaceLib::replaceForTask($data->e, $cPlace),
			};
		}

		$fw->validate();

		if($data->source === 'cultivation') {

			\work\ActionLib::getMainByStructure($data->e['structure']);

			\series\SeriesLib::fillTimeline($data->e);

			$data->cZone = \map\ZoneLib::getByStructure($data->e['structure'], season: $data->e['season']);

			\map\PlotLib::putFromZoneWithSeries($data->e['structure'], $data->cZone, $data->e['season'], [$data->e['season'], $data->e['season'] - 1, $data->e['season'] + 1], onlySeries: TRUE);

			$data->ccCultivation = \series\CultivationLib::getForSelector($data->e['structure'], $data->e['season']);

		} else {
			$data->cPlace = \series\PlaceLib::getByElement($data->e);
		}

		throw new \ViewAction($data);

	});

/**
 * La fiche d'une série au jour d'aujourd'hui, celle du plan 3D (`SeriesLib::getSheet()`), sur sa propre saison : un bloc
 * d'une saison voisine l'ouvre aussi. Seule une série en place sur l'année en cours a des gestes.
 */
function placeSheetPrepare(\stdClass $data, \series\Series $eSeries): void {

	$season = $eSeries['season'];

	$data->eSeries = $eSeries;
	$data->date = \Clock::date();
	$data->sheet = \series\SeriesLib::getSheet($data->eStructure, $eSeries, $season);
	$data->inPlace = ($season === \Clock::year() and $eSeries->isInPlace($season, $data->date));

	// La pastille du titre est celle de la première culture, comme au plan 3D : une série sans culture n'en a pas
	$data->color = $data->sheet['series'][0]['cultivations'][0]['color'] ?? NULL;

	// « Récolter » propose les cultures de la série
	$data->cCultivation = $data->inPlace ? \series\CultivationLib::getBySeries($eSeries) : new Collection();
	$data->canHarvest = \planning\HarvestWeekLib::canHarvest($data->eStructure);
	$data->canTask = new \series\Task(['structure' => $data->eStructure])->assertCreate();

}

/**
 * La fiche d'une série sur le plan d'assolement et ses gestes, qui la redessinent. Cette page ne charge que la
 * structure : la branche `task` de la précédente exige `acceptSoil`, et sa branche `bed` une planche qu'on lit en
 * membre, quand la fiche s'ouvre à quiconque lit le plan (`Structure::canRead()`), conseiller compris, ses gestes
 * suivant chacun son droit.
 */
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		// Le panneau du planning d'où part la saisie, que la réponse ferme.
		$data->panel = NULL;

	})
	->get('sheet', function($data) {

		$eSeries = \series\SeriesLib::getById(GET('series'))
			->validate('canRead')
			->validateProperty('structure', $data->eStructure);

		placeSheetPrepare($data, $eSeries);

		throw new \ViewAction($data, ':sheet');

	})
	/**
	 * La saisie du panneau « Nouvelle récolte » ouvert depuis la fiche (`origin=assolement`), sous les contrôles de
	 * `planning/harvestWeek` : son constructeur (droit, saison, jours, semaine passée ou en cours) puis `doHarvest`.
	 */
	->post('doHarvest', function($data) {

		$data->eStructure->validate('canPlanningLimited');

		if(\planning\HarvestWeekLib::canHarvest($data->eStructure) === FALSE) {
			throw new NotAllowedAction();
		}

		$week = POST('week', fn($value) => Filter::check('week', $value), \Clock::week());
		$days = \planning\HarvestWeekLib::getWeekDays($data->eStructure, $week, POST('open', fn($value) => Filter::check('date', $value)));

		if(\planning\HarvestWeekLib::getCreateDate($days) === NULL) {
			throw new NotExpectedAction('No harvest in a future week');
		}

		$eCultivation = \series\CultivationLib::getById(POST('cultivation', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		$fw = new FailWatch();

		$eTaskChosen = \planning\HarvestWeekLib::getTaskChosen($data->eStructure, $eCultivation, $week, $_POST);

		$fw->validate();

		\planning\HarvestWeekLib::writeEntry($data->eStructure, $eCultivation, $days, $_POST, $eTaskChosen);

		$data->panel = 'panel-planning-harvest-create';

		placeSheetRespond($data, $eCultivation['series']);

	})
	/**
	 * La saisie du panneau « Nouvelle intervention » ouvert depuis la fiche (`planning/taskWeek:create`, `origin=assolement`),
	 * sous les contrôles de `planning/taskWeek:doCreate` : le droit, puis `TaskCreateLib::createCollection()`, qui
	 * refuse une semaine hors saison et un jour hors de la semaine. La réponse redessine la série d'où vient le panneau, même quand les cultures
	 * choisies ont changé : la fiche ne montre que les siennes.
	 */
	->post('doCreate', function($data) {

		$data->eStructure->validate('canPlanningLimited');

		if(new \series\Task(['structure' => $data->eStructure])->canCreate() === FALSE) {
			throw new NotAllowedAction();
		}

		$week = POST('week', fn($value) => Filter::check('week', $value), \Clock::week());

		$eSeries = \series\SeriesLib::getById(POST('series', 'int'))
			->validate('canRead')
			->validateProperty('structure', $data->eStructure);

		\planning\TaskCreateLib::createCollection($data->eStructure, $week, $_POST);

		$data->panel = 'panel-planning-task-create';

		placeSheetRespond($data, $eSeries);

	});

/**
 * La fiche relue après un geste.
 */
function placeSheetRespond(\stdClass $data, \series\Series $eSeries): void {

	placeSheetPrepare($data, \series\SeriesLib::getById($eSeries['id']));

	throw new \ViewAction($data, ':doSheet');

}

new Page()
	->get('tipAi', function($data) use($putSoilTip) {

		// Route de lecture pure : le module de cette page résout ses éléments avec
		// canWrite, ce qui réservait la bulle aux propriétaires. Le plan, lui, ne
		// demande que d'être membre — 9 membres actifs sur 18 recevaient un 403 à
		// chaque survol.
		$data->e = \series\SeriesLib::getById(GET('series'))->validate('canRead');

		\series\SeriesLib::fillTimeline($data->e);

		$putSoilTip($data->e);

		throw new \ViewAction($data, ':tipAi');

	});
?>
