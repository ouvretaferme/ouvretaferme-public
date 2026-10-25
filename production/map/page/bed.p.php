<?php
new Page(function($data) {

		$data->ePlot = \map\PlotLib::getById(INPUT('plot'))->validate('canWrite');

	})
	->get('create', function($data) {

		\map\MapLib::loadOrigin($data);

		// Sur un jardin qui a sa forme, le rang se pose sur la carte de la saison regardée, parmi les jardins et les traits de sa parcelle
		\map\MapLib::loadDrawing($data, $data->ePlot, \map\SeasonLib::getOnline(GET('season')));

		// Le lien ne se pose que sur un jardin de la saison affichée
		if($data->ePlot->empty()) {
			throw new NotExpectedAction('Plot not in season');
		}

		// Les planches neuves suivent la numérotation de celles du jardin, dont la planche qui le remplit n'a pas de nom
		$data->ePlot['numbering'] = \map\BedLib::guessNumbering($data->ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE));

		throw new ViewAction($data);

	})
	->post('doCreate', function($data) {

		// Seule la vue Carte poste une origine, et y rend le troisième niveau du jardin
		$data->origin = (POST('origin') === \map\PlanUi::VIEW_MAP) ? \map\PlanUi::VIEW_MAP : NULL;
		$data->season = \map\SeasonLib::getOnline(POST('season'));

		$fw = new \FailWatch();

		$e = new \map\Bed([
			'plot' => $data->ePlot
		]);

		$e->build(['length', 'width', 'alleyWidth'], $_POST);

		$data->c = \map\BedLib::buildFromInput($e, $_POST);

		// Le rang dessiné sur la carte du panneau, sur un jardin qui a sa forme. Sans lui, les planches restent à dessiner
		$eDraw = new \map\Draw();
		$cDraw = new \Collection();

		// La saison du rang est celle que la réponse rend, lue une fois par getOnline(). Le rang se juge sur les dimensions de ses
		// planches, donc après elles
		if($fw->ok() and post_exists('draw')) {
			[$eDraw, $cDraw] = \map\BedLib::buildDraw($data->ePlot, $data->season, $data->c, POST('draw'), 'draw');
		}

		$fw->validate();

		if($eDraw->empty()) {
			\map\BedLib::createCollection($data->c);
		} else {
			\map\BedLib::createCollectionDrawn($data->c, $eDraw, $cDraw);
		}

		if($data->origin === \map\PlanUi::VIEW_MAP) {

			$ePlot = $data->ePlot;

			\map\MapLib::loadDrawing($data, $ePlot, $data->season);

			// Un jardin absent de la saison postée n'a pas de troisième niveau à rendre : la carte de cette saison le dit
			if($data->ePlot->empty()) {
				throw new RedirectAction(\map\PlanUi::url($ePlot['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?zone='.$ePlot['zone']['id'].'&success=map\\Bed::createdCollection');
			}

			\map\MapLib::putDrawing($data->ePlot);

			throw new ViewAction($data, ':doCreateMap');

		}

		throw new ViewAction($data);

	});

new \map\BedPage()
	->applyElement(function($data, \map\Bed $e) {

		$e->validate('canWrite');

	})
	->update(function($data) {

		$data->season = \map\SeasonLib::getOnline(GET('season'));

		$data->cZone = \map\ZoneLib::getByStructure($data->e['structure'], season: $data->season);
		\map\PlotLib::putFromZone($data->cZone, season: $data->season);

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new ViewAction($data))
	->quick(['name'], action: fn($data) => throw new ViewAction($data, ':doUpdateQuick'))
	->doDelete(fn($data) => throw new ViewAction($data))
	->read('swapSeries', function($data) {

		if($data->e['plotFill']) {
			throw new NotExpectedAction('Invalid plot');
		}

		$data->season = GET('season', 'int');

		$data->e['structure']->conf(\map\Configuration::class)->validateSeason($data->season);

		$data->cZone = \map\ZoneLib::getByStructure($data->e['structure'], season: $data->season);
		\map\PlotLib::putFromZone($data->cZone, withBeds: TRUE, season: $data->season);

		throw new ViewAction($data);

	})
	->write('doSwapSeries', function($data) {

		if($data->e['plotFill']) {
			throw new NotExpectedAction('Invalid plot');
		}

		$data->season = POST('season', 'int');

		$data->e['structure']->conf(\map\Configuration::class)->validateSeason($data->season);

		$eBed2 = \map\BedLib::getById(POST('swapId'))->validate('canWrite');

		if(
			$data->e['id'] !== $eBed2['id'] and
			$data->e['structure']['id'] === $eBed2['structure']['id']
		) {
			\map\BedLib::swapSeries($data->season, $data->e, $eBed2);
		}

		throw new ReloadAction();

	});

// Seule la vue Carte pose une origine (`origin=map`), que le panneau reposte pour choisir la réponse de l'enregistrement
$updateCollection = function($data, ?Closure $callback = NULL) {

	$fw = new \FailWatch();

	$data->cBed = \map\BedLib::buildIds($data->ePlot, GET('ids', 'array'));

	$data->season = GET('season', 'int');
	\map\MapLib::loadOrigin($data);

	$fw->validate();

	if($callback) {
		$callback($data);
	}

	throw new ViewAction($data);

};

// La vue Carte rend le troisième niveau tel que l'écriture le laisse, avec les comptes des deux niveaux du dessus, et les planches
// du geste qui restent dans le jardin restent cochées
$loadDrawingMap = function($data, string $success, \Collection $cBed) {

	// Le panneau de l'onglet Plan ne poste pas d'origine : seule la vue Carte en pose une, et tout le reste reçoit la réponse du panneau
	$data->origin = (POST('origin') === \map\PlanUi::VIEW_MAP) ? \map\PlanUi::VIEW_MAP : NULL;

	if($data->origin === \map\PlanUi::VIEW_MAP) {

		$ePlot = $data->ePlot;

		\map\MapLib::loadDrawing($data, $ePlot, $data->season);

		// Un jardin absent de la saison postée n'a pas de troisième niveau à rendre : la carte de cette saison le dit
		if($data->ePlot->empty()) {
			throw new RedirectAction(\map\PlanUi::url($ePlot['structure'], $data->season, \map\PlanUi::VIEW_MAP).'?zone='.$ePlot['zone']['id'].'&success=map\\'.$success);
		}

		\map\MapLib::putDrawing($data->ePlot, $cBed->getIds());

	}

};

// Un geste sur des planches cochées ne renvoie à la carte que leurs tracés, ceux des autres n'ayant pas bougé
$putBedsTouched = function($data, \Collection $cBed) {

	$data->beds = $cBed->getIds();
	$data->bedsDrawn = \map\PlotLib::getBedsDrawnByBeds($data->ePlot, $cBed);

};

(new Page(function($data) {

		$data->ePlot = \map\PlotLib::getById(INPUT('plot'))->validate('canWrite');

	}))
	->post('doUpdateBedLineCollection', function($data) use($putBedsTouched) {

		if($data->ePlot->canBedLine() === FALSE) {
			throw new \FailAction('map\Bed::canNotDraw');
		}

		$fw = new \FailWatch();

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$eDraw = new \map\Draw([
			'structure' => $data->ePlot['structure'],
			'plot' => $data->ePlot
		]);

		$eDraw->build(['season'], $_POST);

		$fw->validate();

		// Chaque planche arrive à sa place, son coin et son angle postés sous son id : déplacer une planche ne réécrit qu'elle
		$cDraw = new \Collection();

		foreach($c as $eBed) {

			$eDrawBed = new \map\Draw([
				'bed' => $eBed,
				'plot' => $data->ePlot
			]);

			$eDrawBed->buildIndex(['corner', 'angle'], $_POST, $eBed['id']);

			$cDraw[$eBed['id']] = $eDrawBed;

		}

		$fw->validate();

		\map\DrawLib::createByBeds($eDraw, $cDraw);

		$fw->validate();

		// Le troisième niveau repart de ce que le geste vient d'écrire, avec les comptes des deux niveaux du dessus. Les planches
		// posées y restent cochées pour enchaîner un autre déplacement ou une rotation
		\map\MapLib::loadDrawing($data, $data->ePlot, $eDraw['season']);
		\map\MapLib::putDrawing($data->ePlot, $c->getIds());

		$putBedsTouched($data, $c);

		throw new ViewAction($data);

	})
	->post('doUpdateBedLineSpacing', function($data) use($putBedsTouched) {

		if($data->ePlot->canBedLine() === FALSE) {
			throw new \FailAction('map\Bed::canNotDraw');
		}

		$fw = new \FailWatch();

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$eDraw = new \map\Draw([
			'structure' => $data->ePlot['structure'],
			'plot' => $data->ePlot
		]);

		$eDraw->build(['season'], $_POST);

		$fw->validate();

		\map\DrawLib::respaceByBeds($eDraw, $c);

		$fw->validate();

		// Le troisième niveau repart de ce que le geste vient d'écrire, avec les comptes des deux niveaux du dessus. Les planches
		// replacées, les seules dessinées parmi celles postées, y restent cochées pour enchaîner sur Déplacer ou Rotation
		\map\MapLib::loadDrawing($data, $data->ePlot, $eDraw['season']);

		$ids = $c->getIds();

		\map\MapLib::putDrawing($data->ePlot, $data->ePlot['cBed']->find(fn($eBed) => $eBed['drawn'] and in_array($eBed['id'], $ids, TRUE))->getIds());

		$putBedsTouched($data, $c);

		throw new ViewAction($data);

	})
	->post('doDeleteBedLineCollection', function($data) use($putBedsTouched) {

		// Effacer d'un tracé hérité le recopie d'abord dans la saison : le geste écrit des traits, au même titre que le tracé
		if($data->ePlot->canBedLine() === FALSE) {
			throw new \FailAction('map\Bed::canNotDraw');
		}

		$fw = new \FailWatch();

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$eDraw = new \map\Draw([
			'structure' => $data->ePlot['structure'],
			'plot' => $data->ePlot
		]);

		$eDraw->build(['season'], $_POST);

		// L'onglet Plan relit sa parcelle entière, la vue Carte reçoit le troisième niveau et les traits du jardin.
		// Les deux formulaires qui postent ici posent toujours l'origine : une autre valeur est un envoi forgé, qui échoue avant d'effacer quoi que ce soit
		$data->origin = match(POST('origin')) {
			\map\PlanUi::VIEW_PLAN => \map\PlanUi::VIEW_PLAN,
			\map\PlanUi::VIEW_MAP => \map\PlanUi::VIEW_MAP
		};

		$fw->validate();

		\map\DrawLib::deleteByBeds($eDraw, $c);

		if($data->origin === \map\PlanUi::VIEW_MAP) {
			\map\MapLib::loadDrawing($data, $data->ePlot, $eDraw['season']);
			// Les planches effacées restent cochées : elles passent dans « à tracer », prêtes à être redessinées
			\map\MapLib::putDrawing($data->ePlot, $c->getIds());
			$putBedsTouched($data, $c);
		}

		throw new ViewAction($data);

	})
	->get('updateSizeCollection', fn($data) => $updateCollection($data, function($data) {
		$data->eBedSize = \map\BedLib::getSizeCommon($data->cBed);
	}))
	->post('doUpdateSizeCollection', function($data) use($loadDrawingMap) {

		// La saison que la réponse rend se lit avant d'écrire : une saison refusée ne laisse rien derrière elle
		$data->season = \map\SeasonLib::getOnline(POST('season'));

		$fw = new \FailWatch();

		$e = new \map\Bed([
			'plot' => $data->ePlot
		]);

		$properties = \map\BedLib::getPropertiesUpdateSize($_POST);

		$e->build($properties, $_POST);

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$fw->validate();

		\map\BedLib::updateCollection($c, $e, $properties);

		$fw->validate();

		$loadDrawingMap($data, 'Bed::updatedCollection', $c);

		throw new ViewAction($data);

	})
	->get('updatePlotCollection', fn($data) => $updateCollection($data, function($data) {

		$data->cZone = \map\ZoneLib::getByStructure($data->ePlot['structure'], season: $data->season);
		\map\PlotLib::putFromZone($data->cZone, season: $data->season);

	}))
	->get('updateSeasonCollection', fn($data) => $updateCollection($data))
	->post('doUpdateSeasonCollection', function($data) use($loadDrawingMap) {

		// La saison que la réponse rend se lit avant d'écrire, comme pour les dimensions
		$data->season = \map\SeasonLib::getOnline(POST('season'));

		$fw = new \FailWatch();

		$e = new \map\Bed([
			'plot' => $data->ePlot
		]);

		$e->build(['seasonFirst', 'seasonLast'], $_POST);

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$fw->validate();

		\map\BedLib::updateCollection($c, $e, ['seasonFirst', 'seasonLast']);

		$fw->validate();

		$loadDrawingMap($data, 'Bed::updatedCollection', $c);

		throw new ViewAction($data);

	})
	->post('doDeleteCollection', function($data) use($loadDrawingMap, $putBedsTouched) {

		// La saison que la réponse rend se lit avant d'écrire, comme pour les dimensions
		$data->season = \map\SeasonLib::getOnline(POST('season'));

		$fw = new \FailWatch();

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$fw->validate();

		\map\BedLib::deleteCollection($c);

		$fw->validate();

		$loadDrawingMap($data, 'Bed::deletedCollection', $c);

		// Les planches supprimées n'ont plus de tracé : la carte les retire et ne repose rien
		if($data->origin === \map\PlanUi::VIEW_MAP) {
			$putBedsTouched($data, $c);
		}

		throw new ViewAction($data);

	});

new Page(function($data) {

		$data->ePlot = \map\PlotLib::getById(POST('plotOrigin'))->validate('canWrite');

	})
	->post('doUpdatePlotCollection', function($data) {

		$fw = new \FailWatch();

		$e = new \map\Bed([
			'structure' => $data->ePlot['structure']
		]);

		$e->build(['plot'], $_POST);

		$c = \map\BedLib::buildIds($data->ePlot, POST('ids', 'array'));

		$fw->validate();

		\map\BedLib::updatePlotCollection($c, $e);

		$fw->validate();

		$data->e = $e;

		throw new ViewAction($data);

	});
?>
