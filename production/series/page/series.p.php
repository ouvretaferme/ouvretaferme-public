<?php
new \series\CultivationPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canManage', 'acceptCultivation');

	})
	->create(function($data) {

		$data->cPlant = \plant\PlantLib::getByStructure($data->eStructure, properties: ['id', 'name']);

		throw new \ViewAction($data);

	})
	->post('doCreate', function($data) {

		$fw = new FailWatch();

		$prepare = \series\SeriesLib::prepareCreate($_POST);

		if($fw->ok()) {

			[$eSeries, $cCultivation] = $prepare;

			if($eSeries['cycle'] === \series\Series::PERENNIAL) {
				$season = 1;
			} else {
				$season = NULL;
			}

			$cFlow = new Collection();
			$referenceYear = NULL;

			if($eSeries['sequence']->notEmpty()) {

				$cFlow = \sequence\FlowLib::getBySequence($eSeries['sequence'], $season, ['plant' => ['name']]);

				if($eSeries['cycle'] === \series\Series::ANNUAL) {

					if($cFlow->notEmpty()) {

						$startYear = (int)POST('startYear', [0, -1], 'int');
						$startWeek = \sequence\Flow::POST('startWeek', 'weekStart', 1);

						\sequence\FlowLib::changeWeekStart($cFlow, $startWeek);

						$firstYear = ($cFlow->first()['yearOnly'] ?? $cFlow->first()['yearStart']);
						$selectedYear = $startYear + $eSeries['season'];
						$referenceYear = $selectedYear - $firstYear;

						if(
							$selectedYear < $eSeries['season'] and
							($cFlow->first()['weekOnly'] ?? $cFlow->first()['weekStart']) < \sequence\SequenceSetting::MIN_WEEK_MINUS_1
						) {
							\sequence\Flow::fail('weekTooSoonAnnualNeutral', ['season' => $eSeries['season']]);
							$fw->validate();
						}

					}

				} else {
					$referenceYear = $eSeries['season'];
				}

			}

			\series\SeriesLib::createWithCultivations($eSeries, $cCultivation, $cFlow, $referenceYear);

		}

		$fw->validate();

		// La série créée se voit dans le plan, à sa place : on y revient plutôt que
		// d'ouvrir sa fiche, et la ligne s'allume le temps qu'on la repère.
		$url = \structure\StructureUi::urlCultivationSeries($data->eStructure, \structure\Member::AREA, season: $eSeries['season']);

		if(Route::getRequestedWith() === 'ajax') {

			$t = new AjaxTemplate();
			$t->ajaxRedirect($url, TRUE);
			$t->js()->success('series', 'Series::created');
			$t->js()->eval('Series.highlightNext('.json_encode([$eSeries['id']]).', '.json_encode(s("Série créée")).')');

			throw new JsonAction($t->pushInstructions()->getOutput());

		}

		throw new RedirectAction($url.'&success=series\\Series::created');

	})
	->post('addPlant', function($data) {

		$data->season = POST('season', 'int');

		$data->eStructure->conf(\map\Configuration::class)->validateSeason($data->season);

		$data->ePlant = \plant\PlantLib::getById(POST('plant'))->validateProperty('structure', $data->eStructure);

		$use = \series\Series::POST('use', 'use', \series\Series::BED);

		$data->eSeries = new \series\Series([
			'structure' => $data->eStructure,
			'season' => $data->season,
			'cycle' => \series\Series::POST('cycle', 'cycle', function() {
				throw new NotExpectedAction('Missing cycle');
			}),
			'use' => $use,
			'area' => NULL,
			'areaTarget' => 0,
			'length' => NULL,
			'lengthTarget' => ($use === \series\Series::BED) ? 0 : NULL,
			'bedWidth' => $data->eStructure->conf(\map\Configuration::class)['defaultBedWidth'],
			'alleyWidth' => $data->eStructure->conf(\map\Configuration::class)['defaultAlleyWidth']
		]);

		$data->cAction = \work\ActionLib::getByStructure($data->eStructure, fqn: [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION], index: 'fqn');

		$data->eCultivation = \series\CultivationLib::getNew($data->eSeries, $data->ePlant);

		$data->nextIndex = POST('index', 'int', 0) + 1;

		throw new \ViewAction($data);

	});

(new Page(function($data) {

		$data->season = INPUT('season', 'int');

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))
			->validate('canManage');

		$data->eStructure->conf(\map\Configuration::class)->validateSeason($data->season);

	}))
	->get('createFrom', fn($data) => throw new ViewAction($data))
	->get('createFromPlant', function($data) {

		$data->eMember = $data->eStructure->getMember();
		$data->ePlant = \plant\PlantLib::getById(GET('plant'))->validate('notEmpty');
		$data->cAction = \work\ActionLib::getByStructure($data->eStructure, fqn: [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION], index: 'fqn');

		$data->eSeries = new \series\Series([
			'structure' => $data->eStructure,
			'name' => $data->ePlant['name'],
			'nameAuto' => TRUE,
			'nameDefault' => $data->ePlant['name'],
			'use' => \series\Series::BED,
			'area' => NULL,
			'areaTarget' => NULL,
			'length' => NULL,
			'lengthTarget' => NULL,
			'cycle' => $data->ePlant['cycle'],
			'season' => $data->season,
			'bedWidth' => $data->eStructure->conf(\map\Configuration::class)['defaultBedWidth'],
			'alleyWidth' => $data->eStructure->conf(\map\Configuration::class)['defaultAlleyWidth']
		]);

		$data->eCultivation = \series\CultivationLib::getNew($data->eSeries, $data->ePlant);

		throw new ViewAction($data);

	})
	->get('createFromSequence', function($data) {

		$data->eSequence = \sequence\SequenceLib::getById(GET('sequence'))->validate('canRead');

		if($data->eSequence['cycle'] === \sequence\Sequence::PERENNIAL) {
			$season = 1;
		} else {
			$season = NULL;
		}

		$data->cFlow = \sequence\FlowLib::getBySequence($data->eSequence, $season, ['plant' => ['name']]);
		$data->cCultivation = \series\CultivationLib::buildFromSequence($data->eSequence, $data->eStructure, $data->season);

		$cTray = \work\ToolLib::getTraysByStructure($data->eStructure);
		$data->cCultivation->setColumn('cTray', $cTray);

		$data->events = \sequence\FlowLib::reorder($data->eSequence, $data->cFlow);

		throw new ViewAction($data);

	})
	->post('getTasksFromSequence', function($data) {

		$data->eSequence = \sequence\SequenceLib::getById(POST('sequence'))->validate('canRead');

		$data->startYear = (int)POST('startYear', [0, -1], 'int');
		$data->startWeek = \sequence\Flow::POST('startWeek', 'weekStart', 1);

		if($data->eSequence['cycle'] === \sequence\Sequence::PERENNIAL) {
			$season = 1;
		} else {
			$season = NULL;
		}

		$data->cFlow = \sequence\FlowLib::getBySequence($data->eSequence, $season, ['plant' => ['name']]);
		\sequence\FlowLib::changeWeekStart($data->cFlow, $data->startWeek);

		$data->events = \sequence\FlowLib::reorder($data->eSequence, $data->cFlow);

		throw new ViewAction($data);

	});

new \series\SeriesPage()
	->read('/serie/{id}', function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']);

		$data->season = $data->e['season'];

		$data->cSeriesPerennial = \series\SeriesLib::getByPerennialFirst($data->e['perennialFirst']);

		$data->cCultivation = \series\CultivationLib::getBySeries($data->e);
		$data->cTask = \series\TaskLib::getBySeries($data->e);
		$data->cPhoto = \gallery\PhotoLib::getBySeries($data->e);
		$data->cPlace = \series\PlaceLib::getByElement($data->e);

		$formats = \series\PlaceLib::getBedFormats($data->e, $data->cPlace);

		foreach($data->cCultivation as $eCultivation) {
			$eCultivation['densityPlans'] = \series\CultivationLib::getDensityPlans($data->e, $eCultivation, $formats);
		}

		$data->ccTask = \series\TaskLib::getWorkingTimeBySeries($data->e);
		$data->ccTaskHarvested = \series\TaskLib::getHarvestedBySeries($data->e);

		$data->e['sequence'] = \sequence\SequenceLib::getById($data->e['sequence']);

		$data->cActionMain = \work\ActionLib::getMainByStructure($data->eStructure);

		// Une note arrive sur la série par son espèce, par l'action d'une de ses interventions ou par une
		// de ses planches.
		$data->cNoteLinked = \note\NoteLib::getByTargets($data->eStructure, [
			\plant\NoteTargetLib::getType() => array_values(array_unique($data->cCultivation->getColumnCollection('plant')->getIds())),
			\work\NoteTargetLib::getType() => array_values(array_unique($data->cTask->getColumnCollection('action')->getIds())),
			\map\NoteTargetLib::getType() => array_values(array_unique($data->cPlace->getColumnCollection('bed')->getIds()))
		]);

		throw new ViewAction($data);

	});

new \series\SeriesPage()
	->applyElement(function($data, \series\Series $e) {

		$e->validate('canWrite');

		$e['structure'] = \structure\StructureLib::getById($e['structure']);

		$data->eStructure = $e['structure'];
		$data->season = $e['season'];

	})
	->update(page: 'updateComment')
	->doUpdateProperties('doUpdateComment', ['comment'], fn() => throw new ReloadAction())
	->doUpdateProperties('doUpdateStatus', ['status', 'outcome'], fn() => throw new ReloadAction(), validate: ['canUpdate'])
	->quick(['name', 'areaTarget', 'lengthTarget'])
	->read('perennialContinued', function($data) {

		$data->e->validate('isPerennial');

		$fw = new FailWatch();

		\series\SeriesLib::updatePerennialContinued($data->e);

		$fw->validate();

		throw new ReloadAction();

	}, method: 'post')
	->read('perennialFinished', function($data) {

		$data->e->validate('isPerennial');

		\series\SeriesLib::updatePerennialFinished($data->e);

		throw new ReloadAction();

	}, method: 'post')
	->read('createSequence', function($data) {

		\work\ActionLib::getMainByStructure($data->eStructure);

		$data->cTaskMetadata = \series\TaskLib::getMetadataForSequence($data->e);
		$data->cCultivation = \series\CultivationLib::getBySeries($data->e);

		throw new ViewAction($data);

	}, validate: ['canWrite', 'acceptDuplicate'])
	->write('doUpdateTaskPosition', function($data) {

		$data->e->validate('canWrite');

		$fw = new FailWatch();

		$week = \series\Task::POST('week', 'plannedWeek') ?? throw new NotExpectedAction('Invalid week');

		\series\TaskLib::updatePosition($data->e, $week, POST('ids', 'array', []));

		$fw->validate();

		throw new ReloadAction();

	})
	->write('doCreateSequence', function($data) {

		$cAction = \work\ActionLib::getByStructure($data->eStructure, id: POST('actions', 'array'), index: 'id');

		$fw = new FailWatch();

		$eSequence = new \sequence\Sequence();
		$eSequence->build(['name'], $_POST);

		$fw->validate();

		\sequence\SequenceLib::createFromSeries($eSequence, $data->e, $cAction);

		$fw->validate();

		throw new RedirectAction(\sequence\SequenceUi::url($eSequence).'?success=sequence\\Sequence::createdFromSeries');

	}, validate: ['canWrite', 'acceptDuplicate'])
	->update(function($data) {

		$cCultivation = \series\CultivationLib::getBySeries($data->e);

		$data->e['cPlace'] = \series\PlaceLib::getByElement($data->e);
		$data->e['cCultivation'] = \series\CultivationLib::getBySeries($data->e);
		$data->e['cSequence'] = \sequence\SequenceLib::getForSeries($data->e, $cCultivation);
		$data->e['sequence'] = \sequence\SequenceLib::getById($data->e['sequence']);

		throw new ViewAction($data);

	})
	->doUpdate(fn() => throw new ReloadAction())
	->doDelete(function($data) {
		throw new RedirectAction(\structure\StructureUi::urlCultivationSeries($data->eStructure, \structure\Member::AREA, season: $data->season).'&success=series\\Series::deleted');
	});


new \series\SeriesPage()
	->applyCollection(function($data, Collection $c) {
		$c->validateProperty('structure', $c->first()['structure']);
	})
	->doUpdateCollectionProperties('doUpdateStatusCollection', ['status', 'outcome'], fn($data) => throw new ReloadAction(), validate: ['canUpdate', 'acceptOpen'])
	->doUpdateCollectionProperties('doUpdateSeasonCollection', ['season'], fn($data) => throw new ReloadAction('series', $data->c->count() === 1  ? 'Series::updatedSeason' : 'Series::updatedSeasonCollection'),
		validate: ['canUpdate', 'acceptSeason']
	);


new \structure\StructurePage()
	->remote('getCultivation', 'selling', function($data) {

		$data->season = \structure\MemberLib::getDynamicSeason($data->e, GET('season', 'int'));

		\work\ActionLib::getMainByStructure($data->e);

		$data->ccCultivation = \series\CultivationLib::getForArea($data->e, $data->season);


		throw new ViewAction($data);

	})
	->read('downloadCultivation', function($data) {

		$season = GET('season', 'int');

		$filename = 'Plan de culture '.$season.'.pdf';
		$content = \main\PdfLib::build('/series/series:getCultivation?id='.$data->e['id'].'&season='.$season, $filename);

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canWrite'])
	->remote('getSeedling', 'selling', function($data) {

		$data->season = \structure\MemberLib::getDynamicSeason($data->e, GET('season', 'int'));

		$data->search = new Search([
			'supplier' => \work\SupplierLib::getById(GET('supplier')),
			'seedling' => \series\Cultivation::GET('seedling', 'seedling'),
			'week' => \series\CultivationLib::getSeedlingWeek(),
			'groupWeek' => GET('groupWeek', 'bool')
		]);

		$data->sections = \series\CultivationLib::getForSeedlingBySupplier($data->e, $data->season, $data->search);

		throw new ViewAction($data);

	})
	->read('downloadSeedling', function($data) {

		$season = GET('season', 'int');
		$eSupplier = GET('supplier', 'work\Supplier');
		$seedling = \series\Cultivation::GET('seedling', 'seedling');

		$arguments = '';

		if($seedling) {
			$arguments .= '&seedling='.$seedling;
		}

		if($eSupplier->notEmpty()) {
			$arguments .= '&supplier='.$eSupplier['id'];
		}

		// La page de rendu est atteinte par une seconde requête, qui ne voit que cette
		// URL : un filtre non recopié ici disparaît du document.
		$week = \series\CultivationLib::getSeedlingWeek();

		if($week !== NULL) {
			$arguments .= '&week='.$week;
		}

		if(GET('groupWeek', 'bool')) {
			$arguments .= '&groupWeek=1';
		}

		$filename = \series\HeaderUi::getSeedlingPdfFilename($season);
		$content = \main\PdfLib::build(
			'/series/series:getSeedling?id='.$data->e['id'].'&season='.$season.$arguments,
			$filename,
			footer: \main\PdfUi::getPagingFooter()
		);

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canProduction'])
	->remote('getSoil', 'selling', function($data) {

		$eUser = \user\UserLib::getById(GET('user'));

		if($eUser->empty()) {
			throw new VoidAction();
		}

		\user\ConnectionLib::setOnline($eUser);

		$data->season = \structure\MemberLib::getDynamicSeason($data->e, GET('season', 'int'));

		$data->soilView = \structure\MemberLib::getSoilView($data->e, GET('view'));

		$data->cZone = \map\ZoneLib::getByStructure($data->e, season: $data->season);

		\work\ActionLib::getMainByStructure($data->e);


		$seasonsSeries = [$data->season + 1, $data->season, $data->season - 1];

		\map\PlotLib::putFromZoneWithSeries($data->e, $data->cZone, $data->season, $seasonsSeries);

		$data->ccCultivation = \series\CultivationLib::getForSelector($data->e, $data->season);

		throw new ViewAction($data);

	})
	->read('downloadSoil', function($data) {

		$season = GET('season', 'int');
		$soilView = \structure\MemberLib::getSoilView($data->e, GET('view'));

		$filename = \series\HeaderUi::getSoilPdfFilename($soilView, $season);
		$content = \main\PdfLib::build(
			'/series/series:getSoil?id='.$data->e['id'].'&season='.$season.'&view='.$soilView.'&user='.\user\ConnectionLib::getOnline()['id'],
			$filename,
			footer: \main\PdfUi::getPagingFooter()
		);

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canWrite']);

(new Page(function($data) {

		$data->c = \series\SeriesLib::getByIds(REQUEST('ids', 'array'), sort: ['name' => SORT_ASC]);

		\series\Series::validateBatch($data->c);

		$data->eStructure = \structure\StructureLib::getById($data->c->first()['structure']);


	}))
	->get('duplicate', function($data) {

		$data->c->validate('canWrite', 'acceptDuplicate');

		$data->cTaskMetadata = \series\TaskLib::getMetadataForDuplicate($data->c);
		$data->hasPlaces = \series\PlaceLib::existsBySeries($data->c);

		throw new ViewAction($data);

	})
	->post('doDuplicate', function($data) {

		$data->c->validate('canWrite', 'acceptDuplicate');

		$cAction = \work\ActionLib::getByStructure($data->eStructure, id: POST('copyActions', 'array'));

		$season = POST('season');
		$data->eStructure->conf(\map\Configuration::class)->validateSeason($season);

		$copies = POST('copies', 'int');

		if($copies < 1 or $copies > \series\SeriesSetting::DUPLICATE_LIMIT) {
			throw new NotExpectedAction('Invalid copies');
		}

		$copyTimesheet = POST('copyTimesheet', 'bool');
		$copyPlaces = POST('copyPlaces', 'bool');

		$fw = new FailWatch();

			$cSeriesNew = \series\SeriesLib::duplicateCollection(
				$data->c,
				$season,
				$cAction,
				$copies,
				$_POST,
				$copyTimesheet,
				$copyPlaces
			);

		$fw->validate();

		// Depuis le plan de culture, on y reste : il se recharge en ajax avec les
		// copies, plutôt que d'ouvrir la série créée ; les copies s'y allument le
		// temps qu'on les repère.
		if(POST('origin') === 'plan' and Route::getRequestedWith() === 'ajax') {

			// Même sortie que ReloadAction, plus l'instruction qui allume les copies.
			$t = new AjaxTemplate();
			$t->ajaxReload();
			$t->js()->success('series', ($cSeriesNew->count() === 1) ? 'Series::duplicated' : 'Series::duplicatedCollection');
			$t->js()->eval('Series.highlightNext('.json_encode($cSeriesNew->getIds()).', '.json_encode(s("Série dupliquée")).')');

			throw new JsonAction($t->pushInstructions()->getOutput());

		}

		if($cSeriesNew->count() === 1) {
			throw new RedirectAction(\series\SeriesUi::url($cSeriesNew->first()).'?success=series\\Series::duplicated');
		} else {
			throw new RedirectAction(\structure\StructureUi::urlCultivationSeries($data->eStructure, \structure\Member::AREA, season: $season).'&success=series\\Series::duplicatedCollection');
		}

	})
	->get('updateSeasonCollection', function($data) {

		$data->c->validate('canRead', 'acceptSeason');

		throw new ViewAction($data);

	})
	->get('closeCollection', function($data) {

		$data->c->validate('canRead', 'acceptClose');

		\work\ActionLib::getMainByStructure($data->eStructure);

		$cCultivation = \series\CultivationLib::getBySeries($data->c);

		foreach($data->c as $eSeries) {
			$eSeries['cCultivation'] = $cCultivation->find(fn($eCultivation) => $eCultivation['series']['id'] === $eSeries['id']);
		}

		throw new ViewAction($data);

	})
	->post('doCloseCollection', function($data) {

		$data->c->validate('canUpdate', 'acceptClose');

		$fw = new FailWatch();

		foreach($data->c as $eSeries) {
			$eSeries['status'] = \series\Series::CLOSED;
			$eSeries->buildIndex(['outcome', 'comment'], $_POST, $eSeries['id']);
		}

		$fw->validate();

		\series\SeriesLib::closeCollection($data->c);

		throw new ReloadAction('series', 'Series::closedCollection');

	})
	->get('numberCollection', function($data) {

		$data->c->validate('canRead');

		$data->numbering = \series\SeriesLib::getNumbering($data->c);

		throw new ViewAction($data);

	})
	->post('doNumberCollection', function($data) {

		$data->c->validate('canUpdate');

		$fw = new FailWatch();

		foreach($data->c as $eSeries) {
			$eSeries->buildIndex(['name'], $_POST, $eSeries['id']);
		}

		$fw->validate();

		\series\SeriesLib::updateNameCollection($data->c);

		throw new ReloadAction('series', 'Series::numberedCollection');

	})
	->post('doIncrementPlannedCollection', function($data) {

		$data->c->validate('canUpdate');

		$fw = new FailWatch();

		\series\TaskLib::incrementPlannedBySeriesCollection($data->c, POST('increment', 'int'));
		\series\CultivationLib::incrementHarvestPeriodExpectedBySeriesCollection($data->c, POST('increment', 'int'));

		$fw->validate();

		throw new ReloadAction();

	})
	->post('doDeleteCollection', function($data) {

		$data->c->validate('canDelete');

		\series\SeriesLib::deleteCollection($data->c);

		throw new ReloadAction('series', 'Series::deletedCollection');

	});
?>
