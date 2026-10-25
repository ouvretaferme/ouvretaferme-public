<?php
new \series\TaskPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate();

		return new \series\Task([
			'structure' => $data->eStructure,
			'series' => new \series\Series(),
			'season' => NULL,
			'cultivation' => new \series\Cultivation(),
			'status' => \series\Task::INPUT('status', 'status', \series\Task::TODO),
			'category' => \work\CategoryLib::getByStructure($data->eStructure, id: INPUT('category')),
		]);

	})
	->create(function($data) {

		$plannedWeek = \series\Task::GET('plannedWeek', 'plannedWeek');
		$plannedDate = \series\Task::GET('plannedDate', 'plannedDate');
		$doneWeek = \series\Task::GET('doneWeek', 'doneWeek', \Clock::week());
		$doneDate = \series\Task::GET('doneDate', 'doneDate') ?? ($doneWeek === \Clock::week() ? \Clock::date() : NULL);

		$data->e->merge([
			'plannedWeek' => $plannedWeek,
			'plannedDate' => $plannedDate ?? ($plannedWeek === \Clock::week() ? \Clock::date() : NULL),
			'plannedSelection' => ($plannedDate !== NULL) ? 'date' : 'week',
			'doneWeek' => $doneWeek,
			'doneDate' => $doneDate,
			'doneSelection' => ($doneDate !== NULL) ? 'date' : 'week',
			'action' => \work\ActionLib::getByStructure($data->eStructure, id: GET('action'))
		]);

		if($data->e['category']->empty()) {
			throw new NotExpectedAction('Invalid value for \'category\'');
		}

		$data->cAction = \work\ActionLib::getByStructure($data->eStructure, category: $data->e['category']);
		$data->cCategory = \work\CategoryLib::getByStructure($data->eStructure);

		$ePlant = \plant\PlantLib::getById(GET('plant'));

		if($ePlant->notEmpty()) {

			$ePlant->validateProperty('structure', $data->eStructure);

			$data->e['cSize'] = \plant\SizeLib::getByStructureAndPlant($data->e['structure'], $ePlant);

		} else {
			$data->e['cSize'] = new Collection();
			$data->cAction->map(fn($eAction) => $eAction['disabled'] = ($eAction['fqn'] === ACTION_RECOLTE));
		}

		$data->e->add([
			'cultivation' => new \series\Cultivation(),
			'plant' => $ePlant,
			'cTool?' => fn() => new Collection(),
			'hasTools' => \work\ToolLib::getForWork($data->e['structure'], $data->e['action']),
			'cProduct?' => fn() => new Collection(),
			'hasProducts' => \work\ToolLib::getProductsForWork($data->e['structure'], $data->e['action']),
			'cMethod?' => fn() => new Collection(),
			'hasMethods' => \work\MethodLib::getForWork($data->e['structure'], $data->e['action'])
		]);

		throw new ViewAction($data);


	}, propertiesCreate: [], page: 'createFromScratch')
	->create(function($data) {

		$cAction = \work\ActionLib::getByStructure($data->eStructure, category: CATEGORIE_CULTURE, index: 'id');
		$cAction->setColumn('disabled', FALSE);

		$ePlant = new \plant\Plant();

		// Récupération de la série appelée en paramètre de la page
		if(get_exists('series')) {

			$selection = \series\Series::getSelection();
			$selection['cCultivation'] = \series\CultivationLib::delegateBySeries();

			$data->eSeries = \series\SeriesLib::getById(GET('series'), $selection)->validateProperty('structure', $data->eStructure);

			if($data->eSeries['cCultivation']->count() === 1) {
				$data->eCultivation = $data->eSeries['cCultivation']->first();
			} else if(get_exists('cultivation')) {
				$data->eCultivation = $data->eSeries['cCultivation'][GET('cultivation', 'int')] ?? new \series\Cultivation();
			} else {
				$data->eCultivation = new \series\Cultivation();
			}

			$data->cSeries = new Collection();

			$data->season = $data->eSeries['season'];

			if($data->eCultivation->empty()) {
				$cAction->map(fn($eAction) => $eAction['disabled'] = ($eAction['fqn'] === ACTION_RECOLTE));
			} else {
				$ePlant = $data->eCultivation['plant'];
			}

		} else {

			$data->season = GET('season');
			$data->eStructure->conf(\map\Configuration::class)->validateSeason($data->season);

			$data->eSeries = new \series\Series();
			$data->eCultivation = new \series\Cultivation();

			$search = new Search([
				'status' => \series\Series::OPEN
			]);

			if(get_exists('plant')) {

				$ePlant = \plant\PlantLib::getById(GET('plant'))->validateProperty('structure', $data->eStructure);
				$search->set('plant', $ePlant);

			}

			$data->cSeries = \series\SeriesLib::getByStructure($data->eStructure, $data->season, selectCultivation: TRUE, selectPlaces: TRUE, search: $search);

			// Si sélection par plante, on trie correctement les séries par ordre chronologique
			if($ePlant->notEmpty()) {

				$data->cSeries->sort(function($eSeries1, $eSeries2) use($ePlant) {

					$eCultivation1 = $eSeries1['cCultivation']->find(fn($eCultivation) => $eCultivation['plant']['id'] === $ePlant['id'], limit: 1, clone: FALSE);
					$eCultivation2 = $eSeries2['cCultivation']->find(fn($eCultivation) => $eCultivation['plant']['id'] === $ePlant['id'], limit: 1, clone: FALSE);

					if($eCultivation1['startWeek'] === NULL and $eCultivation2['startWeek'] === NULL) {
						return \L::getCollator()->compare($eCultivation1['series']['name'] ?? '', $eCultivation2['series']['name'] ?? '');
					}

					if($eCultivation1['startWeek'] === NULL) {
						return 1;
					}

					if($eCultivation2['startWeek'] === NULL) {
						return -1;
					}

					return ($eCultivation1['startWeek'] > $eCultivation2['startWeek'] ? 1 : -1);

				});

			} else {
				$data->cSeries->sort(['name' => SORT_ASC], natural: TRUE);
			}

			if($data->cSeries->notEmpty()) {

				\series\Series::validateBatch($data->cSeries, $data->eStructure);

				$data->season = $data->cSeries->first()['season'];

			}

			$cAction->map(fn($eAction) => $eAction['disabled'] = TRUE);

			\work\ActionLib::getMainByStructure($data->eStructure);

		}

		$eAction = GET('action') ? ($cAction[GET('action')] ?? throw new NotExpectedAction('Action mismatch')) : new \work\Action();

		$doneWeek = ($data->e['status'] === \series\Task::DONE) ? \series\Task::GET('doneWeek', 'doneWeek', \Clock::week()) : NULL;

		// Initialisation de l'objet de la tâche
		$data->e->merge([
			'season' => $data->season,
			'series' => $data->eSeries,
			'cultivation' => $data->eCultivation,
			'plannedWeek' => ($data->e['status'] === \series\Task::TODO) ? \series\Task::GET('plannedWeek', 'plannedWeek', $data->season) : NULL,
			'plannedDate' => ($data->e['status'] === \series\Task::TODO) ? \series\Task::GET('plannedDate', 'plannedDate') : NULL,
			'doneWeek' => $doneWeek,
			'doneDate' => ($data->e['status'] === \series\Task::DONE) ? (\series\Task::GET('doneDate', 'doneDate') ?? ($doneWeek === \Clock::week() ? \Clock::date() : NULL)) : NULL,
			'action' => $eAction,
			'plant' => $ePlant,
			'cTool?' => fn() => new Collection(),
			'hasTools' => \work\ToolLib::getForWork($data->eStructure, $eAction),
			'cProduct?' => fn() => new Collection(),
			'hasProducts' => \work\ToolLib::getProductsForWork($data->eStructure, $eAction),
			'cMethod?' => fn() => new Collection(),
			'hasMethods' => \work\MethodLib::getForWork($data->eStructure, $eAction),
			'cAction' => $cAction,
			'cVariety' => new Collection(),
			'varietiesIntersect' => []
		]);

		if($data->eCultivation->notEmpty()) {
			$data->e->merge(\series\SliceLib::getVarietiesByCultivations(new Collection([$data->eCultivation])));
		}

		if($ePlant->empty()) {
			$data->e['cSize'] = new Collection();
		} else {
			$data->e['cSize'] = \plant\SizeLib::getByStructureAndPlant($data->eStructure, $ePlant);
		}

		throw new ViewAction($data);

	}, propertiesCreate: [], page: 'createFromSeries')
	->doCreate(fn($data) => throw new ViewAction($data));

new Page()
	->post('doCreateFromSeriesCollection', function($data) {

		$data->status = \series\Task::INPUT('status', 'status', \series\Task::TODO);
		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		$data->e = new \series\Task([
			'status' => $data->status,
			'structure' => $data->eStructure
		]);

		$data->e->validate('canCreate');

		$fw = new \FailWatch();

		$cSeries = \series\SeriesLib::getByIds(POST('series', 'array'));
		\series\Series::validateBatch($cSeries, $data->eStructure);

		$cCultivation = \series\CultivationLib::getByIds(POST('cultivation', 'array'), index: 'series');

		$eCategory = \work\CategoryLib::getByStructure($data->eStructure, fqn: CATEGORIE_CULTURE);

		$data->c = new Collection();

		foreach($cSeries as $eSeries) {

			$eCultivation = $cCultivation[$eSeries['id']] ?? new \series\Cultivation();

			if(
				($eCultivation->empty() and $eSeries['plants'] === 1) or // Une seule production dans la série mais pas de production en paramètre
				($eCultivation->notEmpty() and $eCultivation['series']['id'] !== $eSeries['id']) // Series mismatch
			) {
				Fail::log('series\Task::cultivation.check');
				continue;
			}

			$ePlant = $eCultivation->notEmpty() ? $eCultivation['plant'] : new \plant\Plant();

			$e = new \series\Task([
				'structure' => $eSeries['structure'],
				'category' => $eCategory,
				'series' => $eSeries,
				'season' => $eSeries['season'],
				'cultivation' => $eCultivation,
				'plant' => $ePlant,
				'status' => $data->status
			]);

			$e->build(\series\TaskLib::getPropertiesCreate()($e), $_POST, new \PropertyList('create'));

			$data->c[] = $e;

		}

		$fw->validate();

		// Vérification des contraintes de cohérence (espèce, variété, unité, calibre) pour les récoltes
		if($data->c->first()['action']['fqn'] === ACTION_RECOLTE) {
			\series\Task::validateSameHarvest($data->c);
		}

		\series\TaskLib::createCollection($data->c);

		$fw->validate();

		throw new ViewAction($data);

	});


new Page()
	->post('getCreateCollectionFields', function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure'));

		$data->eTask = new \series\Task([
			'action' => new \work\Action(),
			'structure' => $data->eStructure
		]);

		$data->eTask->validate('canWrite');

		$data->cCultivation = \series\CultivationLib::getByIds(POST('cultivations', 'array'));

		if($data->cCultivation->notEmpty()) {

			$ePlant = \series\Cultivation::validateBatch($data->cCultivation, $data->eStructure);

			[
				'varietiesIntersect' => $data->varietiesIntersect,
				'cVariety' => $data->cVariety
			] = \series\SliceLib::getVarietiesByCultivations($data->cCultivation);

		} else {
			$data->cVariety = new Collection();
			$data->varietiesIntersect = [];
			$ePlant = new \plant\Plant();
		}

		if($ePlant->empty()) {
			$data->eTask['cSize'] = new Collection();
		} else {
			$data->eTask['cSize'] = \plant\SizeLib::getByStructureAndPlant($data->eStructure, $ePlant);
		}

		throw new ViewAction($data);

	})
	->post('getFields', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'));
		$eAction = \work\ActionLib::getById(POST('action'))->validateProperty('structure', $eStructure);

		// Le formulaire « Modifier » poste l'intervention : revenir à son action retrouve ce qu'elle porte
		// en base, que les champs redessinés pour une autre action n'ont pas gardé.
		$eTaskSaved = \series\TaskLib::getById(POST('id'));

		if($eTaskSaved->notEmpty()) {
			$eTaskSaved
				->validate('canWrite')
				->validateProperty('structure', $eStructure);
		}

		if($eTaskSaved->notEmpty() and $eTaskSaved['action']->is($eAction)) {
			$values = $eTaskSaved->extracts(['tools', 'products', 'methods']);
		} else {
			$values = [
				'tools' => [],
				'products' => [],
				'methods' => []
			];
		}

		$data->eTask = new \series\Task([
			'structure' => $eStructure,
			'action' => $eAction
		] + $values);

		$data->eTask->validate('canWrite');

		$data->eTask['hasTools'] = \work\ToolLib::getForWork($eStructure, $eAction, $values['tools']);
		$data->eTask['hasProducts'] = \work\ToolLib::getProductsForWork($eStructure, $eAction, $values['products']);
		$data->eTask['hasMethods'] = \work\MethodLib::getForWork($eStructure, $eAction);

		$data->eTask['cTool?'] = fn() => \work\ToolLib::askCollection($values['tools'], $eStructure);
		$data->eTask['cProduct?'] = fn() => \work\ToolLib::askCollection($values['products'], $eStructure);
		$data->eTask['cMethod?'] = fn() => \work\MethodLib::askCollection($values['methods'], $eStructure);

		throw new \ViewAction($data);

	});

/**
 * Où revenir après une suppression depuis la page d'une intervention du nouveau planning
 * (`\planning\TaskDetailUi::ORIGIN`) : sa semaine, celle de sa réalisation avant celle prévue, ou la semaine
 * en cours quand elle n'en a pas. Seul le client sait si la page est seule ou ouverte au-dessus d'un écran, qu'il
 * recharge alors comme ailleurs (`PlanningTaskDetail.deleted()`). Les autres lieux n'ont pas d'adresse de retour.
 */
$getUrlBack = function(\series\Task $eTask): ?string {

	if(POST('origin') !== \planning\TaskDetailUi::ORIGIN) {
		return NULL;
	}

	return \planning\TaskWeekUi::url($eTask['structure'], $eTask['doneWeek'] ?? $eTask['plannedWeek']);

};

new \series\TaskPage()
	->read('/tache/{id}', function($data) {

		$data->eStructure = $data->e['structure'];


		if($data->e['series']->empty()) {
			$data->cPlace = \series\PlaceLib::getByElement($data->e);
		} else {
			$data->cPlace = \series\PlaceLib::getByElement($data->e['series']);
		}

		\series\TaskLib::fillDetail($data->e, $data->cPlace, GET('sprayer', '?int'));

		$data->cPhoto = \gallery\PhotoLib::getByTask($data->e);

		$data->cUser = \hr\TeammateLib::getUsersForPlanning($data->e['structure'], withPresenceAbsence: TRUE);
		\series\TimesheetLib::fillTimesByTask($data->cUser, $data->e);

		$data->cComment = \series\CommentLib::getByTask($data->e);

		// Une note arrive sur l'intervention par son action, par son espèce ou par une de ses planches. La
		// structure se relit entière, l'ouverture des notes lisant son type, comme sur le menu de planche.
		$data->cNoteLinked = \note\NoteLib::getByTargets(\structure\StructureLib::getById($data->e['structure']['id']), [
			\work\NoteTargetLib::getType() => [$data->e['action']['id']],
			\plant\NoteTargetLib::getType() => $data->e['plant']->empty() ? [] : [$data->e['plant']['id']],
			\map\NoteTargetLib::getType() => array_values(array_unique($data->cPlace->getColumnCollection('bed')->getIds()))
		]);

		throw new ViewAction($data);

	})
	->read('following', function($data) {

		$data->cTaskFollowing = \series\TaskLib::getFollowing($data->e);
		$data->cCultivationFollowing = ($data->e['series']->notEmpty() and $data->e['plannedWeek'] !== NULL) ?
			\series\CultivationLib::getFollowingHarvest(new Collection([$data->e['series']]), $data->e['plannedWeek']) :
			new Collection();

		throw new ViewAction($data);

	})
	->write('doIncrementPlanned', function($data) {

		$data->e->validate('acceptPostpone');

		$fw = new FailWatch();

		$increment = POST('increment', 'int');

		if(POST('mode', ['only', 'following'], 'following') === 'only') {
			\series\TaskLib::incrementPlannedCollection(new Collection([$data->e]), $increment);
		} else {
			\series\TaskLib::incrementPlannedFollowing($data->e, $increment);
		}

		$fw->validate();

		throw new ReloadAction();

	})
	->read('getVarietiesField', function($data) {

		$data->eCultivation = \series\CultivationLib::getById(POST('cultivation'))->validateProperty('series', $data->e['series']);

		if($data->eCultivation->notEmpty()) {
			$data->eCultivation['cSlice'] = \series\SliceLib::getByCultivation($data->eCultivation);
		}

		throw new \ViewAction($data);

	}, method: 'post')
	->update(function($data) {

		if($data->e['series']->empty()) {

			$data->cZone = \map\ZoneLib::getByStructure($data->e['structure']);
			\map\PlotLib::putFromZone($data->cZone);

		} else {

			$data->e['series']['cCultivation'] = \series\CultivationLib::getBySeries($data->e['series']);

			if($data->e['cultivation']->notEmpty()) {
				$data->e['cultivation']['cSlice'] = \series\SliceLib::getByCultivation($data->e['cultivation']);
			}

			$data->cZone = new Collection();

		}

		if($data->e['plant']->empty()) {
			$data->e['cSize'] = new Collection();
		} else {
			$data->e['cSize'] = \plant\SizeLib::getByStructureAndPlant($data->e['structure'], $data->e['plant']);
		}

		$data->e['hasMethods'] = \work\MethodLib::getForWork($data->e['structure'], $data->e['action']);
		$data->e['hasTools'] = \work\ToolLib::getForWork($data->e['structure'], $data->e['action'], $data->e['tools']);
		$data->e['hasProducts'] = \work\ToolLib::getProductsForWork($data->e['structure'], $data->e['action'], $data->e['products']);

		if($data->e['treatment'] === NULL) {
			$data->e['treatment'] = \series\TaskLib::getTreatmentDefault($data->e);
		}

		$data->cAction = \work\ActionLib::getByStructure($data->e['structure'], category: $data->e['category']);
		$data->cAction->filter(fn($eAction) => $eAction['fqn'] !== ACTION_RECOLTE); // On ne peut pas changer l'action pour une récolte

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new BackAction(highlight: [
		'#task-'.$data->e['id'].'-presentation',
		'#planning-task-detail-intervention[data-task="'.$data->e['id'].'"]',
		'.flow-timeline-row[data-task="'.$data->e['id'].'"] > .flow-timeline-card',
		'.planning-task[data-task="'.$data->e['id'].'"]',
		'#analyze-task-'.$data->e['id'],
		'#treatment-task-'.$data->e['id'],
	]))
	->quick(['description', 'doneDate' => ['done']])
	->update(function($data) {

		if($data->e['series']->empty()) {
			throw new NotExpectedAction('No series');
		}

		$data->cCultivation = \series\CultivationLib::getBySamePlants($data->e['structure'], $data->e['season'], $data->e['plant']);

		throw new ViewAction($data);

	}, page: 'updateCultivation')
	->doUpdateProperties('doUpdateCultivation', ['cultivation'], fn($data) => throw new ViewAction($data))
	->doUpdateProperties('doUpdateTreatment', ['treatment'], fn($data) => throw new ReloadAction('series', 'Task::treatmentUpdated'))
	->write('doCheck', function($data) {

		\series\TaskLib::updateCheck($data->e, POST('position', 'int'), POST('check', 'bool'));

		throw new ViewAction($data);

	})
	->doDelete(function($data) use($getUrlBack) {

		$data->urlBack = $getUrlBack($data->e);

		throw new ViewAction($data);

	})
	->write('doDeleteRepeat', function($data) use($getUrlBack) {

		\series\TaskLib::deleteRepeat($data->e);

		$data->urlBack = $getUrlBack($data->e);

		throw new ViewAction($data, ':doDelete');

	}, validate: ['canDelete']);

(new Page(function($data) {

		$data->c = \series\TaskLib::getByIds(REQUEST('ids', 'array'), properties: \series\Task::getSelection() + [
			'cccPlace' => \series\PlaceLib::delegateByTask()
		]);

		\series\Task::validateBatch($data->c);

		$data->eStructure = $data->c->first()['structure'];

	}))
	->get('updateHarvestCollection', function($data) {

		$data->c->validate('canWrite');
		$data->c->setColumn('harvestDate', GET('date', default: \Clock::date()));

		\series\Task::validateSameAction($data->c, \work\ActionLib::getByStructure($data->eStructure, fqn: ACTION_RECOLTE));
		\series\Task::validateSameHarvest($data->c);

		\series\TaskLib::fillDistribution($data->c);

		$data->cProductStock = \product\StockLib::getCompatibleProducts($data->c->first());

		$data->eProductBookmark = \product\StockLib::getBookmark($data->c->first());

		\work\ActionLib::getMainByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	->post('doUpdateHarvestCollection', function($data) {

		$data->c->validate('canWrite');

		$eAction = \work\ActionLib::getByStructure($data->eStructure, fqn: ACTION_RECOLTE);

		\series\Task::validateSameAction($data->c, $eAction);
		\series\Task::validateSameHarvest($data->c);

		\series\TaskLib::fillHarvestDates($data->c);
		\series\TaskLib::fillDistribution($data->c);

		$data->harvestDate = POST('harvestDate');
		$data->harvestMore = POST('harvestMore', 'float', 0.0);

		$fw = new FailWatch();

		\series\TaskLib::buildHarvests(
			$data->c,
			$data->harvestMore,
			$data->harvestDate,
			POST('harvestUnit'),
			POST('distribution', ['area', 'plant', 'fair'], 'fair')
		);

		$fw->validate();

		foreach($data->c as $e) {
			\series\TaskLib::update($e, ['harvest', 'harvestUnit']);
		}

		if(post_exists('stock')) {

			$productStock = POST('stock', '?int');

			if($productStock !== NULL) {

				$eProductStock = \product\ProductLib::getById($productStock)
					->validate('acceptStock', onFail: fn() => \series\Task::fail('stock.check'))
					->validateProperty('structure', $data->eStructure);

			} else {
				$eProductStock = new \product\Product();
			}

			$fw->validate();

			// Mise à jour du stock
			if($data->harvestMore !== 0.0) {

				if($eProductStock->notEmpty()) {

					\product\StockLib::increment($eProductStock, $data->harvestMore, new \product\Stock([
						'comment' => $eAction['name']
					]), zeroIfNegative: TRUE);

				}

			}

			// Mise à jour du marquage ou non en favori du stock
			if(POST('stockRemember', 'bool')) {
				\product\StockLib::remember($data->c->first(), $eProductStock);
			} else {
				\product\StockLib::forget($data->c->first());
			}

		}

		throw new ViewAction($data);

	})
	->post('doUpdateTodoCollection', function($data) {

		$data->c->validate('isDone', 'canWrite');

		\series\TaskLib::updateTodoCollection($data->c);

		throw new ReloadAction();

	})
	->post('doUpdateDoneCollection', function($data) {

		$data->c->validate('isTodo', 'canWrite');

		$newDone = \series\Task::POST('doneWeek', 'doneWeek', fn() => throw new NotExpectedAction('Invalid week'));

		\series\TaskLib::updateDoneCollection($data->c, $newDone);

		throw new ReloadAction();

	})
	->get('updatePlannedCollection', function($data) {

		$data->c->validate('isTodo', 'canWrite');

		throw new ViewAction($data);

	})
	->post('doUpdatePlannedDateCollection', function($data) {

		$data->c->validate('isTodo', 'canWrite');

		$fw = new FailWatch();

		$e = new \series\Task();
		$e->build(['plannedDate'], $_POST);

		$fw->validate();

		\series\TaskLib::updatePlannedDateCollection($data->c, $e['plannedDate']);

		throw new ReloadAction();

	})
	->post('doUpdatePlannedCollection', function($data) {

		$data->c->validate('isTodo', 'canWrite');

		$fw = new FailWatch();

		$e = new \series\Task();
		$e->build(['plannedWeek'], $_POST);

		$fw->validate();

		\series\TaskLib::updatePlannedCollection($data->c, $e['plannedWeek']);

		throw new ReloadAction();

	})
	->get('incrementPlannedCollection', function($data) {

		$data->c->validate('acceptPostpone', 'canWrite');

		throw new ViewAction($data);

	})
	->post('doIncrementPlannedCollection', function($data) {

		$data->c->validate('acceptPostpone', 'canWrite');

		$fw = new FailWatch();

		\series\TaskLib::incrementPlannedCollection($data->c, POST('increment', 'int'));

		$fw->validate();

		throw new ReloadAction();

	})
	->post('doUpdateUserCollection', function($data) {

		$data->c->validate('isTodo', 'canWrite');

		$eUser = \user\UserLib::getById(POST('user'));

		if(\structure\MemberLib::isMember($eUser, $data->eStructure) === FALSE) {
			throw new NotExpectedAction('Invalid user');
		}

		$action = POST('action', ['add', 'delete'], fn() => throw new NotExpectedAction('Invalid action'));

		\series\TaskLib::updateUser($data->c, $eUser, $action);

		throw new ViewAction($data);

	})
	->post('doDeleteCollection', function($data) {

		$data->c->validate('canDelete');

		\series\TaskLib::deleteCollection($data->c);

		throw new ReloadAction();

	})
	->post('doDeleteCollection', function($data) {

		$data->c->validate('canDelete');

		\series\TaskLib::deleteCollection($data->c);

		throw new ReloadAction();

	});

new \series\TaskPage()
	->read('getStockField', function($data) {

		$data->e['harvestUnit'] = \series\Task::POST('harvestUnit', 'harvestUnit', fn() => throw new NotExpectedAction('Invalid unit'));

			$data->cProductStock = \product\StockLib::getCompatibleProducts($data->e);
			$data->eProductBookmark = \product\StockLib::getBookmark($data->e);

		throw new \ViewAction($data);

	}, method: 'post', validate: ['canWrite'])
?>
