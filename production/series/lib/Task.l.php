<?php
namespace series;

class TaskLib extends TaskCrud {

	const PROPOSED_TIMESHEET = 'timesheet';
	const PROPOSED_TIMESHEETS = 'timesheets';
	const PROPOSED_DONE = 'done';
	const PROPOSED_UPDATED = 'updated';
	const PROPOSED_CREATED = 'created';
	const PROPOSED_COMMENT = 'comment';

	const COLUMNS_SCENE = ['id', 'series', 'cultivation', 'action', 'plannedWeek', 'doneWeek', 'doneDate', 'done', 'harvest', 'harvestUnit'];

	public static function getPropertiesCreate(?string $origin = NULL): \Closure {
		return function(Task $e) use($origin) {

			$e['category']->expects(['fqn']);

			$properties = TaskLib::getPropertiesWrite($e);
			$properties[] = 'harvestUnit';

			if($e['category']['fqn'] === CATEGORIE_CULTURE) {

				// Juste après l'action, que son étape lit, et avant le calibre, que l'étape de `harvestSize` cherche
				// dans l'espèce.
				if($e['series']->empty()) {
					array_splice($properties, array_search('action', $properties) + 1, 0, ['plant']);
				}

				$properties[] = 'variety';

			}

			if($origin !== 'api') {
				$properties[] = 'repeatMaster';
			}

			return $properties;

		};
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {

		return function(Task $e) {

			$e->expects([
				'status',
				'series',
				'action' => ['fqn']
			]);

			$properties = TaskLib::getPropertiesWrite($e);
			
			// On ne peut pas changer l'action si c'est une récolte
			if($e['action']['fqn'] === ACTION_RECOLTE) {
				array_delete($properties, 'action');
			} else {

				// On ne peut changer de culture que si ce n'est pas une récolte
				if($e['series']->notEmpty()) {
					$properties[] = 'cultivation';
				}

			}

			$properties[] = 'variety';

			return $properties;

		};

	}

	public static function spreadForSoil(Series $eSeries, \Collection $cTask, \Collection $cActionStart, \Collection $cActionSoil): void {

		$week = fn(Task $eTask) => ($eTask['status'] === Task::TODO) ? $eTask['plannedWeek'] : $eTask['doneWeek'];

		$actionsStart = $cActionStart->getIds();
		$actionsSoil = $cActionSoil->getIds();

		$cTaskSoil = new \Collection();

		foreach($cTask as $eTask) {

			if(in_array($eTask['action']['id'], $actionsSoil) === FALSE) {
				continue;
			}

			$taskWeek = $eTask['doneWeek'] ?? $eTask['plannedWeek'];

			if($taskWeek === NULL) {
				continue;
			}

			$actionId = $eTask['action']['id'];

			if($cTaskSoil->offsetExists($actionId)) {
				$cTaskSoil[$actionId]['minWeek'] = min($cTaskSoil[$actionId]['minWeek'], $taskWeek);
				$cTaskSoil[$actionId]['maxWeek'] = max($cTaskSoil[$actionId]['maxWeek'], $taskWeek);
			} else {
				$cTaskSoil[$actionId] = new Task([
					'series' => $eSeries,
					'action' => $eTask['action'],
					'minWeek' => $taskWeek,
					'maxWeek' => $taskWeek,
				]);
			}

		}

		$eSeries['cTaskSoil'] = $cTaskSoil;

		$ccTaskStart = [];

		foreach($cTask as $eTask) {

			if(
				$eTask['cultivation']->empty() or
				in_array($eTask['action']['id'], $actionsStart) === FALSE
			) {
				continue;
			}

			$ccTaskStart[$eTask['cultivation']['id']][] = $eTask;

		}

		foreach($eSeries['cCultivation'] as $eCultivation) {

			$cTaskStart = new \Collection($ccTaskStart[$eCultivation['id']] ?? []);
			$cTaskStart->sort(fn(Task $e1, Task $e2) => ($week($e1) ?? '') <=> ($week($e2) ?? ''));

			$eCultivation['cTask'] = $cTaskStart;
			$eCultivation['firstTaskWeek'] = $cTaskStart->empty() ? NULL : $week($cTaskStart->first());

		}

	}

	public static function getPropertiesWrite(Task $e): array {

		$e->expects(['status', 'series', 'category']);

		$properties = ['action', 'methods', 'description', 'harvestSize', 'fertilizer', 'products', 'treatment', 'tools'];

		switch($e['status']) {

			case Task::TODO :
				$properties[] = 'planned';
				$properties[] = 'timeExpected';
				break;

			case Task::DONE :
				$properties[] = 'done';
				break;

		}

		return $properties;

	}

	public static function getByStructure(\structure\Structure $eStructure, ?int $page = NULL, \Search $search = new \Search()): array {

		if($search->get('year')) {

			if($search->get('month')) {
				Task::model()->where('"'.$search->get('year').''.sprintf('%02d', $search->get('month')).'" BETWEEN EXTRACT(YEAR_MONTH FROM timesheetStart) AND EXTRACT(YEAR_MONTH FROM timesheetStop)');
			} else {
				Task::model()->where($search->get('year').' BETWEEN EXTRACT(YEAR FROM timesheetStart) AND EXTRACT(YEAR FROM timesheetStop)');
			}

		}

		if($search->get('week')) {

			$weekYear = week_year($search->get('week'));
			$weekNumber = week_number($search->get('week'));

			Task::model()
				->where($weekYear.' BETWEEN EXTRACT(YEAR FROM timesheetStart) AND EXTRACT(YEAR FROM timesheetStop)')
				->where('WEEK(timesheetStart, 1) = '.$weekNumber);

		}

		if($search->get('user')->notEmpty()) {

			Task::model()
				->where('m1.structure', $eStructure)
				->where('m2.user', $search->get('user'))
				->where('m1.time > 0', if: $search->get('time'))
				->join(Timesheet::model(), 'm2.task = m1.id');

			$index = ['id'];

		} else {

			Task::model()
				->whereStructure($eStructure)
				->where('time > 0', if: $search->get('time'));

			$index = NULL;

		}

		if($page === NULL) {
			$limit = NULL;
			$position = NULL;
		} else {
			$limit = 100;
			$position = $page * $limit;
		}

		$cTask = Task::model()
			->select(Task::getSelection())
			->select([
				'series' => [
					'cccPlace' => PlaceLib::delegateBySeries()
				],
				'cccPlace' => PlaceLib::delegateByTask(),
				'plant' => ['name'],
				'times' => TimesheetLib::delegateByTask()
			])
			->whereSeries($search->get('series'), if: $search->get('series'))
			->wherePlant($search->get('plant'), if: $search->get('plant'))
			->whereAction($search->get('action'), if: $search->get('action'))
			->whereCategory($search->get('category'), if: $search->get('category'))
			->whereStatus($search->get('status'), if: $search->get('status'))
			->option('count')
			->sort(new \Sql('IF(status = "'.Task::TODO.'", IF(plannedWeek IS NOT NULL, plannedWeek, "0000-00-00"), NULL) DESC, IF(timesheetStop IS NOT NULL, timesheetStop, updatedAt) DESC'))
			->getCollection($position, $limit, $index);

		return [$cTask, Task::model()->found()];

	}

	public static function getMetadataForDuplicate(\Collection $cSeries): \Collection {

		return Task::model()
			->select([
				'action' => ['name', 'fqn'],
				'time' => new \Sql('SUM(time)', 'float'),
			])
			->whereSeries('IN', $cSeries)
			->group('action')
			->getCollection();

	}

	public static function getMetadataForSequence(Series $eSeries): \Collection {

		return Task::model()
			->select([
				'action' => ['name', 'fqn'],
				'week' => new \Sql('MIN(IF(doneWeek IS NOT NULL, doneWeek, plannedWeek))')
			])
			->whereSeries($eSeries)
			->whereAction('NOT IN', \work\WorkSetting::$mainActions)
			->group('action')
			->getCollection(index: 'action');

	}

	public static function getBySeries(Series $eSeries, ?\Collection $cAction = NULL) {

		$eSeries->expects(['id', 'status']);

		return Task::model()
			->select([
				'id',
				'cultivation', 'series' => fn() => $eSeries, 'structure',
				'status', 'plannedWeek', 'doneWeek', 'plannedUsers', 'position',
				'display' => new \Sql('IF(doneWeek IS NULL, plannedWeek, doneWeek)', 'string'),
				'cComment' => CommentLib::delegateByTask(),
				'description', 'fertilizer', 'treatment', 'time', 'harvest', 'harvestUnit',
				'harvestSize' => ['name'],
				'action' => ['fqn', 'name', 'short', 'color'],
				'tools',
				'cTool?' => fn($e) => fn() =>\work\ToolLib::askCollection($e['tools'], $e['structure']),
				'products',
				'cProduct?' => fn($e) => fn() =>\work\ToolLib::askCollection($e['products'], $e['structure']),
				'methods',
				'cMethod?' => fn($e) => fn() =>\work\MethodLib::askCollection($e['methods'], $e['structure']),
				'plant',
				'variety' => ['name'],
				'createdAt', 'doneDate',
				'status',
				'times' => TimesheetLib::delegateByTask()
			])
			->whereSeries($eSeries)
			->whereAction('IN', $cAction, if: $cAction !== NULL)
			->sort(new \Sql('display ASC, position IS NULL, position ASC, id ASC'))
			->getCollection();

	}

	public static function calculateWorkingTimeForReport(\structure\Structure $eStructure, int $year, \plant\Plant $ePlant): float {

		return Task::model()
			->join(Timesheet::model(), 'm2.task = m1.id')
			->where('m1.structure', '=', $eStructure)
			->where('m1.plant', '=', $ePlant)
			->where('m1.series', '=', NULL)
			->where('m2.date', 'LIKE', $year.'-%')
			->getValue(new \Sql('SUM(m2.time)', 'float')) ?? 0.0;

	}

	// Les interventions d'implantation de la série : semis (direct ou pépinière) et
	// plantation. Elles se posent indifféremment sur la série ou sur l'une de ses
	// cultures, d'où la recherche par série.
	public static function getImplantationBySeries(Series $eSeries, \Collection $cAction): \Collection {

		// La série est déjà là : la reprendre telle quelle évite de la resélectionner
		// par tâche. Déplacer une intervention la recalcule en traversant
		// SequenceLib::doRecalculate(), d'où le cycle et la saison.
		$eSeries->expects(['id', 'status', 'cycle', 'season']);

		return Task::model()
			->select([
				'id', 'structure', 'cultivation',
				'series' => fn() => $eSeries,
				'action' => ['fqn', 'name', 'color', 'soil'],
				'status', 'plannedWeek', 'doneWeek'
			])
			->whereSeries($eSeries)
			->whereAction('IN', $cAction)
			->sort(['plannedWeek' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	// Une date d'implantation ne se ressaisit que si elle désigne une intervention
	// sans ambiguïté : celle-ci n'est pas faite, et elle est seule de son action.
	// La règle vaut action par action — un semis fait n'empêche pas de replacer la
	// plantation qui reste à faire.
	public static function acceptImplantationUpdate(\Collection $cTask, string $fqn): bool {

		$cTaskAction = $cTask->find(fn($eTask) => $eTask['action']['fqn'] === $fqn);

		if($cTaskAction->count() > 1) {
			return FALSE;
		}

		return ($cTaskAction->empty() or $cTaskAction->first()['status'] !== Task::DONE);

	}

	public static function getByYear(\structure\Structure $eStructure, int $year, \Search $search): \Collection {

		self::applySearch($search);
		self::applyOpenSeries();

		$ccTask = Task::model()
			->select(Task::getSelection())
			->select([
				'cccPlace' => PlaceLib::delegateByTask(),
				'series' => [
					'cccPlace' => PlaceLib::delegateBySeries()
				],
				'display' => new \Sql('IF(doneWeek IS NULL, plannedWeek, doneWeek)'),
				'plant' => ['name'],
				'cultivation' => ['seedlingSeeds', 'startWeek', 'startAction'],
				'times' => TimesheetLib::delegateByTask()
			])
			->whereStructure($eStructure)
			->wherePlannedWeek('LIKE', $year.'-%')
			->sort([
				'plannedWeek' => SORT_ASC,
				'createdAt' => SORT_ASC
			])
			->getCollection(NULL, NULL, ['display', NULL]);

		$delete = [];

		foreach($ccTask as $key => $cTask) {
			self::filterPlot($search, $cTask);
			if($cTask->empty()) {
				$delete[] = $key;
			}
		}

		foreach($delete as $key) {
			$ccTask->offsetUnset($key);
		}

		return $ccTask;

	}

	public static function applyOpenSeries(): void {

		Task::model()->or(
			fn() => $this->whereStatus(Task::DONE),
			fn() => $this->where('NOT '.new SeriesModel()
				->where('s1.id', new \Sql('m1.series'))
				->where('s1.status', Series::CLOSED)
				->existsSql())
		);

	}

	protected static function applySearch(\Search $search): void {

		Task::model()
			->whereAction($search->get('action'), if: $search->get('action') and $search->get('action')->notEmpty())
			->wherePlant($search->get('plant'), if: $search->get('plant') and $search->get('plant')->notEmpty())
			->where(fn() => 'JSON_CONTAINS(plannedUsers, \''.$search->get('user')['id'].'\')', if: $search->get('user') and $search->get('user')->notEmpty());

	}

	public static function getForDaily(\structure\Structure $eStructure, string $week, \user\User $eUser, \Collection $cAction, \Search $search = new \Search()): array {

		$eActionHarvest = $cAction->find(fn($eAction) => $eAction['fqn'] === ACTION_RECOLTE, limit: 1);
		$cActionSeedling = $cAction->find(fn($eAction) => in_array($eAction['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION]));

		$ccTimesheet = TimesheetLib::getTimesheetsByDaily($eStructure, $week, $eUser);

		$cccTaskByDate = new \Collection()->setDepth(3);

		foreach(week_dates($week) as $date) {
			$cccTaskByDate[$date] = new \Collection()->setDepth(2);
		}

		$firstDate = week_date_starts($week);
		$lastDate = week_date_ends($week);

		Task::model()
			->select([
				'cTimesheet' => Timesheet::model()
					->select([
						'date', 'user',
						'totalTime' => new \Sql('SUM(time)', 'float')
					])
					->whereStructure($eStructure)
					->whereUser($eUser, if: $eUser->notEmpty())
					->whereDate('BETWEEN', new \Sql(Task::model()->format($firstDate).' AND '.Task::model()->format($lastDate)))
					->group(['task', 'user', 'date'])
					->delegateCollection('task', ['date', 'user'])
			])
			->or(
				fn() => $this->whereId('IN', $ccTimesheet->getColumn('task')),
				fn() => $this
					->where(fn() => 'JSON_CONTAINS(plannedUsers, \''.$eUser['id'].'\')', if: $eUser->notEmpty())
					->or(
						fn() => $this->wherePlannedWeek($week),
						fn() => $this->whereDoneWeek($week)
					)
			);

		$ccTask = self::getForPlanning($eStructure, $week, $search, eUser: $eUser);

		foreach($ccTask as $cTask) {
			\series\TaskLib::fillHarvestDates($cTask);
		}


		if($ccTask->empty()) {
			return [
				$ccTimesheet,
				$cccTaskByDate
			];
		}

		// Remplissage de la récolte
		if($ccTask->offsetExists($eActionHarvest['id'])) {
			\series\TaskLib::fillHarvestDates($ccTask[$eActionHarvest['id']]);
		}

		foreach($cccTaskByDate as $date => $ccTaskByDate) {

			$tasksByDate = array_flip(($ccTimesheet[$date] ?? new \Collection())->getColumnCollection('task')->getColumn('id'));

			foreach($ccTask as $action => $cTask) {

				self::fillSeedling($ccTask, $cActionSeedling);

				foreach($cTask as $eTask) {

					$in = (
						$eTask['plannedDate'] === $date or
						$eTask['doneDate'] === $date
					);

					if(
						$in or
						array_key_exists($eTask['id'], $tasksByDate) or
						array_key_exists($date, $eTask['harvestDates'])
					) {


						$eTaskNew = clone $eTask;

						if(
							$eTask['harvest'] !== NULL and
							array_key_exists($date, $eTask['harvestDates'])
						) {
							$eTaskNew['harvestPeriod'] = $eTask['harvestDates'][$date];
						} else {
							$eTaskNew['harvestPeriod'] = NULL;
						}

						$eTaskNew['times'] = ($eTask['cTimesheet'][$date] ?? new \Collection())->makeArray(function(Timesheet $e, &$key) {

							$key = $e['user']['id'];
							return $e['totalTime'];

						});

						$isAffected = (
							$eUser->empty() or
							in_array($eUser['id'], $eTask['plannedUsers'])
						);

						if($isAffected or $eTaskNew['times']) {

							$cccTaskByDate[$date][$action] ??= new \Collection();
							$cccTaskByDate[$date][$action][] = $eTaskNew;

						}

					}

				}

			}

		}

		return [
			$ccTimesheet,
			$cccTaskByDate,
		];

	}

	public static function getForAssign(\structure\Structure $eStructure, string $week, \Collection $cAction): \Collection {

		$cActionSeedling = $cAction->find(fn($eAction) => in_array($eAction['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION]));

		Task::model()->wherePlannedDate(NULL);

		$cccTask = new \Collection([
			'todo' => self::getForWeekTodo($eStructure, $week),
			'delayed' => self::getForWeekDelayed($eStructure, $week),
			'unplanned' => self::getByUnplanned($eStructure, $week),
			'done' => new \Collection()
		]);

		self::fillSeedling($cccTask['todo'], $cActionSeedling);
		self::fillSeedling($cccTask['delayed'], $cActionSeedling);
		self::fillSeedling($cccTask['unplanned'], $cActionSeedling);

		$cccTask->setDepth(3);

		return $cccTask;

	}

	public static function getForWeek(\structure\Structure $eStructure, string $week, \Collection $cAction, \Search $search): \Collection {

		$eActionHarvest = $cAction->find(fn($eAction) => $eAction['fqn'] === ACTION_RECOLTE, limit: 1);
		$cActionSeedling = $cAction->find(fn($eAction) => in_array($eAction['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION]));

		$cccTask = new \Collection([
			'todo' => self::getForWeekTodo($eStructure, $week, $search),
			'delayed' => self::getForWeekDelayed($eStructure, $week, $search),
			'unplanned' => self::getByUnplanned($eStructure, $week, $search),
			'done' => self::getForWeekDone($eStructure, $week, $search),
		]);

		$cccTask->setDepth(3);

		foreach($cccTask as $ccTask) {
			self::fillForWeek($week, $ccTask, $eActionHarvest, $cActionSeedling);
		}

		return $cccTask;

	}

	public static function getForExport(\structure\Structure $eStructure, string $week, \Collection $cAction): \Collection {

		$eActionHarvest = $cAction->find(fn($eAction) => $eAction['fqn'] === ACTION_RECOLTE, limit: 1);
		$cActionSeedling = $cAction->find(fn($eAction) => in_array($eAction['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION]));

		$ccTask = self::getForWeekExport($eStructure, $week);
		self::fillForWeek($week, $ccTask, $eActionHarvest, $cActionSeedling);

		return $ccTask;

	}

	public static function getForExportDelayed(\structure\Structure $eStructure, string $week, \Collection $cAction): \Collection {

		$eActionHarvest = $cAction->find(fn($eAction) => $eAction['fqn'] === ACTION_RECOLTE, limit: 1);
		$cActionSeedling = $cAction->find(fn($eAction) => in_array($eAction['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION]));

		$ccTask = self::getForWeekDelayed($eStructure, $week);
		self::fillForWeek($week, $ccTask, $eActionHarvest, $cActionSeedling);

		return $ccTask;

	}

	private static function fillForWeek(string $week, \Collection $ccTask, \work\Action $eActionHarvest, \Collection $cActionSeedling): void {

		if($ccTask->offsetExists($eActionHarvest['id'])) {

			$cTask = $ccTask[$eActionHarvest['id']];

			// Mise à jour de la quantité récoltée par la quantité récoltée ce jour
			self::fillHarvestWeeks($cTask);
			self::fillHarvestByWeek($cTask, $week);

		}

		self::fillSeedling($ccTask, $cActionSeedling);
	}

	public static function fillSeedling(\Collection $ccTask, \Collection $cActionSeedling): void {

		foreach($cActionSeedling as $eActionSeedling) {

			if($ccTask->offsetExists($eActionSeedling['id'])) {

				foreach($ccTask[$eActionSeedling['id']] as $eTask) {

					if($eTask['cultivation']->empty()) {
						$eTask['cCultivation'] = new \Collection();
					} else {

						$eTask['cCultivation'] = new \Collection([$eTask['cultivation']->merge([
							'series' => $eTask['series'],
							'plant' => $eTask['plant']
						])]);

						foreach($eTask['cCultivation'] as $eCultivation) {
							\series\CultivationLib::fillSliceStats($eCultivation);
						}

					}

				}

			}

		}

	}

	public static function getByWeekAndAction(\structure\Structure $eStructure, string $week, \work\Action $eAction): \Collection {

		Task::model()
			->select([
				'cultivation' => [
					'cSlice' => SliceLib::delegateByCultivation(),
					'varietiesTool' => ['name', 'routineValue']
				]
			])
			->whereAction($eAction)
			->or(
				fn() => $this
					->whereStatus(Task::TODO)
					->wherePlannedWeek($week),
				fn() => $this
					->whereStatus(Task::DONE)
					->whereDoneWeek($week)
			);

		return self::getForPlanning($eStructure, $week, indexByAction: FALSE)
			->sort(function(Task $e1, Task $e2) {
				return \L::getCollator()->compare(
					$e1['series']->empty() ? '' : $e1['series']['name'],
					$e2['series']->empty() ? '' : $e2['series']['name']
				);
			});

	}

	public static function getForWeekTodo(\structure\Structure $eStructure, string $week, \Search $search = new \Search()): \Collection {

		Task::model()
			->wherePlannedWeek($week)
			->whereStatus(Task::TODO);

		return self::getForPlanning($eStructure, $week, $search);

	}

	public static function getByUnplanned(\structure\Structure $eStructure, string $referenceWeek, \Search $search = new \Search()): \Collection {

		Task::model()
			->wherePlannedWeek(NULL)
			->whereStatus(Task::TODO);

		return self::getForPlanning($eStructure, $referenceWeek, $search);

	}

	public static function getForWeekDone(\structure\Structure $eStructure, string $week, \Search $search = new \Search()): \Collection {

		$cTaskTimesheet = TimesheetLib::getTasksByWeek($eStructure, $week);
		$cTaskHarvest = HarvestLib::getTasksByWeek($eStructure, $week);

		Task::model()
			->option('index-force', ["structure", "status", "doneWeek"])
			->or(
				fn() => $this->whereId('IN', $cTaskTimesheet),
				fn() => $this->whereId('IN', $cTaskHarvest),
				fn() => $this
					->whereStatus(Task::DONE)
					->whereDoneWeek($week),
			);

		return self::getForPlanning($eStructure, $week, $search);

	}

	/**
	 * Fusion de Done et Todo
	 */
	public static function getForWeekExport(\structure\Structure $eStructure, string $week, \Search $search = new \Search()): \Collection {

		$cTaskTimesheet = TimesheetLib::getTasksByWeek($eStructure, $week);
		$cTaskHarvest = HarvestLib::getTasksByWeek($eStructure, $week);

		Task::model()
			->option('index-force', ["structure", "status", "doneWeek"])
			->or(
				fn() => $this->whereId('IN', $cTaskTimesheet),
				fn() => $this->whereId('IN', $cTaskHarvest),
				fn() => $this
					->wherePlannedWeek($week)
					->whereStatus(Task::TODO),
				fn() => $this
					->whereStatus(Task::DONE)
					->whereDoneWeek($week),
			);

		return self::getForPlanning($eStructure, $week, $search);

	}

	public static function getForWeekDelayed(\structure\Structure $eStructure, string $referenceWeek, \Search $search = new \Search()): \Collection {

		if(self::applyDelayed(Task::model(), $eStructure, $referenceWeek) === FALSE) {
			return new \Collection();
		}

		return self::getForPlanning($eStructure, $referenceWeek, $search);

	}

	/**
	 * Les interventions en retard pour une semaine, les plus anciennes d'abord. Une semaine à venir n'en
	 * a aucune : rien n'est posé sur le modèle et la méthode rend `FALSE`.
	 */
	public static function applyDelayed(TaskModel $mTask, \structure\Structure $eStructure, string $referenceWeek): bool {

		if(strcmp($referenceWeek, \Clock::week()) > 0) {
			return FALSE;
		}

		$mTask
			->wherePlannedWeek('>', toWeek(\Clock::shift('- '.$eStructure->conf(\map\Configuration::class)['planningDelayedMax'].' MONTH')))
			->wherePlannedWeek('<', $referenceWeek)
			->whereStatus(Task::TODO)
			->sort([
				'plannedWeek' => SORT_ASC,
				'createdAt' => SORT_ASC
			]);

		return TRUE;

	}

	public static function getHarvestedByCultivation(Cultivation $eCultivation): \Collection {

		$eCultivation->expects(['cSlice', 'structure']);

		$eAction = \work\ActionLib::getByStructure($eCultivation['structure'], fqn: ACTION_RECOLTE);

		$cTask = Task::model()
			->select([
				'variety' => ['name'],
				'totalHarvest' => new \Sql('SUM(harvest)', 'float'),
				'harvestUnit',
				'harvestSize' => ['name', 'yield']
			])
			->group(['variety', 'harvestSize', 'harvestUnit'])
			->whereAction($eAction)
			->whereCultivation($eCultivation)
			->sort(['variety' => SORT_ASC, 'harvestSize' => SORT_ASC, 'harvestUnit' => SORT_ASC])
			->having('totalHarvest > 0')
			->getCollection();

		return $cTask;

	}

	/**
	 * Ce que les lectures du planning ajoutent à `Task::getSelection()`, hors du temps et de la récolte de la
	 * semaine : `getForPlanning()` les délègue, `\planning\TaskWeekLib::getTasksByWeeks()` les répartit.
	 */
	public static function getPlanningSelection(): array {

		return [
			'cccPlace' => PlaceLib::delegateByTask(),
			'series' => [
				'cccPlace' => PlaceLib::delegateBySeries()
			],
			'cComment' => CommentLib::delegateByTask(),
			'plant' => ['name'],
			'cultivation' => [
				'cSlice' => SliceLib::delegateByCultivation(),
				'varietiesTool' => ['routineValue'],
				'startWeek', 'startAction', 'seedling', 'seedlingSeeds', 'distance', 'plantSpacing', 'rowSpacing', 'rows', 'stagger', 'density', 'varietiesUnit'
			],
		];

	}

	protected static function getForPlanning(\structure\Structure $eStructure, string $week, \Search $search = new \Search(), bool $indexByAction = TRUE, \user\User $eUser = new \user\User()): \Collection {

		$index = $indexByAction ? ['action', NULL] : NULL;

		self::applySearch($search);
		self::applyOpenSeries();

		$ccTask = Task::model()
			->select(Task::getSelection())
			->select([
				'harvestPeriod' => Harvest::model()
					->whereWeek($week)
					->group('task')
					->delegateProperty('task', new \Sql('SUM(quantity)', 'float')),
			] + self::getPlanningSelection())
			->select([
				'times' => TimesheetLib::delegateByTask($eUser, $week)
			])
			->whereStructure($eStructure)
			->sort([
				'harvestSize' => SORT_ASC,
				'plant' => SORT_ASC,
				'variety' => SORT_ASC,
				'id' => SORT_ASC
			])
			->getCollection(index: $index);

		if($indexByAction) {

			$ccTask->sort(function(\Collection $c1, \Collection $c2) {

				return \L::getCollator()->compare(
					$c1->first()['action']['name'],
					$c2->first()['action']['name']
				);

			});

			foreach($ccTask as $cTask) {

				$cTask->sort(function(Task $e1, Task $e2) {

					return \L::getCollator()->compare(
						$e1['plant']['name'] ?? '',
						$e2['plant']['name'] ?? ''
					);

				});

			}

			$delete = [];

			foreach($ccTask as $key => $cTask) {
				self::filterPlot($search, $cTask);
				if($cTask->empty()) {
					$delete[] = $key;
				}
			}

			foreach($delete as $key) {
				$ccTask->offsetUnset($key);
			}

		} else {
			self::filterPlot($search, $ccTask);
		}

		return $ccTask;

	}

	private static function filterPlot(\Search $search, \Collection $cTask): void {

		$delete = [];
		$ePlotSearch = $search->get('plot');

		if($ePlotSearch !== NULL and $ePlotSearch->notEmpty()) {

			foreach($cTask as $key => $eTask) {

				if($eTask['series']->notEmpty()) {
					$cccPlace = $eTask['series']['cccPlace'];
				} else {
					$cccPlace = $eTask['cccPlace'];
				}

				if(
					$cccPlace->empty() or
					$cccPlace->find(fn($ePlace) => (
						($ePlotSearch['zoneFill'] === FALSE and $ePlace['plot']->is($ePlotSearch)) or
						($ePlotSearch['zoneFill'] === TRUE and $ePlace['zone']->is($ePlotSearch['zone']))
					), depth: 3, limit: 1)->empty()
				) {
					$delete[] = $key;
				}

			}

		}

		foreach($delete as $key) {
			$cTask->offsetUnset($key);
		}

	}

	public static function getWorkingTimeBySeries(Series $eSeries): \Collection {

		return Task::model()
			->select([
				'cultivation',
				'action' => ['fqn', 'color', 'pace', 'name'],
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->whereSeries($eSeries)
			->group(['cultivation', 'action'])
			->having(new \Sql('totalTime IS NOT NULL'))
			->sort([
				'cultivation' => SORT_ASC,
				'totalTime' => SORT_DESC
			])
			->getCollection(index: ['cultivation', NULL]);

	}

	public static function getHarvestedBySeries(Series $eSeries): \Collection {

		$eSeries->expects(['structure']);

		$eAction = \work\ActionLib::getByStructure($eSeries['structure'], fqn: ACTION_RECOLTE);

		return Task::model()
			->select([
				'cultivation',
				'harvestUnit',
				'totalHarvested' => new \Sql('SUM(harvest)', 'float'),
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->join(\plant\Size::model(), 'm2.id = m1.harvestSize', type: 'LEFT')
			->whereSeries($eSeries)
			->whereAction($eAction)
			->or(
				fn() => $this->where('m2.yield', TRUE),
				fn() => $this->where('m2.yield', NULL)
			)
			->group(['cultivation', 'harvestUnit'])
			->sort([
				'harvestUnit' => SORT_ASC
			])
			->getCollection(index: ['cultivation', 'harvestUnit']);

	}

	public static function getHarvestedBySeriesCollection(\Collection $cSeries, \structure\Structure $eStructure): \Collection {

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		return Task::model()
			->select([
				'series',
				'cultivation',
				'harvestUnit',
				'totalHarvested' => new \Sql('SUM(harvest)', 'float'),
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->join(\plant\Size::model(), 'm2.id = m1.harvestSize', type: 'LEFT')
			->whereSeries('IN', $cSeries)
			->whereAction($eAction)
			->or(
				fn() => $this->where('m2.yield', TRUE),
				fn() => $this->where('m2.yield', NULL)
			)
			->group(['series', 'cultivation', 'harvestUnit'])
			->sort([
				'harvestUnit' => SORT_ASC
			])
			->getCollection(index: ['series', 'cultivation', 'harvestUnit']);

	}

	public static function fillDoneProposed(\structure\Structure $eStructure, \work\Action $eAction, \Collection $cTask): void {

		$cTaskWeek = $cTask->find(fn($eTask) => $eTask['doneDate'] === NULL, clone: FALSE);

		if($cTaskWeek->empty()) {
			return;
		}

		$datesByWeek = [];

		$cTaskDated = Task::model()
			->select(['doneWeek', 'doneDate'])
			->whereStructure($eStructure)
			->whereAction($eAction)
			->whereStatus(Task::DONE)
			->whereDoneWeek('IN', array_unique($cTaskWeek->getColumn('doneWeek')))
			->whereDoneDate('!=', NULL)
			->getCollection();

		foreach($cTaskDated as $eTaskDated) {
			$datesByWeek[$eTaskDated['doneWeek']][$eTaskDated['doneDate']] = TRUE;
		}

		$datesByTask = [];

		$cComment = Comment::model()
			->select(['task', 'createdAt'])
			->whereTask('IN', $cTaskWeek)
			->sort(['createdAt' => SORT_ASC])
			->getCollection();

		foreach($cComment as $eComment) {
			$datesByTask[$eComment['task']['id']][] = substr($eComment['createdAt'], 0, 10);
		}

		foreach($cTaskWeek as $eTask) {
			$eTask['doneProposed'] = self::getDoneProposed($eTask, array_keys($datesByWeek[$eTask['doneWeek']] ?? []), $datesByTask[$eTask['id']] ?? []);
		}

	}

	/**
	 * La modification passe avant la création : dans la semaine faite, c'est le plus souvent le passage à
	 * « fait ». Une source ne propose qu'un jour de cette semaine.
	 */
	public static function getDoneProposed(Task $eTask, array $datesDone, array $datesComment): ?array {

		$eTask->expects(['doneWeek', 'timesheetStart', 'timesheetStop', 'createdAt', 'updatedAt']);

		$monday = week_date_starts($eTask['doneWeek']);
		$sunday = week_date_ends($eTask['doneWeek']);

		$inWeek = fn(?string $date) => ($date !== NULL and substr($date, 0, 10) >= $monday and substr($date, 0, 10) <= $sunday);

		if($eTask['timesheetStart'] !== NULL) {

			if($eTask['timesheetStart'] === $eTask['timesheetStop']) {

				if($inWeek($eTask['timesheetStart'])) {
					return ['date' => $eTask['timesheetStart'], 'source' => self::PROPOSED_TIMESHEET];
				}

			} else {

				$first = max($eTask['timesheetStart'], $monday);

				if($first <= min($eTask['timesheetStop'], $sunday)) {
					return ['date' => $first, 'source' => self::PROPOSED_TIMESHEETS];
				}

			}

		}

		if(count($datesDone) === 1) {
			return ['date' => $datesDone[0], 'source' => self::PROPOSED_DONE];
		}

		if($inWeek($eTask['updatedAt'])) {
			return ['date' => substr($eTask['updatedAt'], 0, 10), 'source' => self::PROPOSED_UPDATED];
		}

		if($inWeek($eTask['createdAt'])) {
			return ['date' => substr($eTask['createdAt'], 0, 10), 'source' => self::PROPOSED_CREATED];
		}

		foreach($datesComment as $date) {
			if($inWeek($date)) {
				return ['date' => $date, 'source' => self::PROPOSED_COMMENT];
			}
		}

		return NULL;

	}

	/**
	 * Les interventions qui portent `$eTool` dans leur matériel, selon la place que `moveTool()` lui
	 * donnera avec sa routine : `product` le recevront en produit, `tool` le garderont en matériel.
	 */
	public static function countByTool(\work\Tool $eTool): array {

		$eTool->expects(['id', 'structure', 'routineName']);

		$cTask = Task::model()
			->select([
				'action' => ['fqn'],
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereStructure($eTool['structure'])
			->where('JSON_CONTAINS(tools, \''.$eTool['id'].'\')')
			->group('action')
			->getCollection();

		$count = [
			'product' => 0,
			'tool' => 0
		];

		foreach($cTask as $eTask) {

			if($eTool->isProductFor($eTask['action'])) {
				$count['product'] += $eTask['count'];
			} else {
				$count['tool'] += $eTask['count'];
			}

		}

		return $count;

	}

	/**
	 * Les interventions de la structure sur les périodes `$periods`, faites ou à faire, quelle que soit la
	 * personne : une intervention compte dans la semaine où elle a été faite, sinon dans celle où elle est
	 * prévue, et une intervention à faire sans semaine ne compte pas. Les périodes s'additionnent, une
	 * intervention ne comptant qu'une fois si elles se chevauchent.
	 *
	 * @param array $periods [[première semaine, dernière semaine], ...]
	 * @return array [action => [catégorie => nombre d'interventions]]
	 */
	public static function countByAction(\structure\Structure $eStructure, array $periods): array {

		$weeksBetween = array_map(fn(array $period) => new \Sql(Task::model()->format($period[0]).' AND '.Task::model()->format($period[1])), $periods);

		$cTask = Task::model()
			->select([
				'action',
				'category',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereStructure($eStructure)
			->or(
				fn() => $this
					->whereStatus(Task::TODO)
					->or(...array_map(fn(\Sql $weeks) => fn() => $this->wherePlannedWeek('BETWEEN', $weeks), $weeksBetween)),
				fn() => $this
					->whereStatus(Task::DONE)
					->or(...array_map(fn(\Sql $weeks) => fn() => $this->whereDoneWeek('BETWEEN', $weeks), $weeksBetween)),
			)
			->group(['action', 'category'])
			->getCollection();

		$counts = [];

		foreach($cTask as $eTask) {
			$counts[$eTask['action']['id']][$eTask['category']['id']] = $eTask['count'];
		}

		return $counts;

	}

	public static function synchronizeProduct(\work\Tool $eTool): void {

		$eTool->expects(['id', 'structure', 'routineName']);

		self::moveTool($eTool, $eTool);

	}

	/**
	 * Le matériel est le même objet qu'un produit déjà enregistré : ses interventions reçoivent ce produit.
	 */
	public static function mergeProduct(\work\Tool $eTool, \work\Tool $eToolProduct): void {

		$eTool->expects(['id', 'structure']);

		self::moveTool($eTool, $eToolProduct);

	}

	private static function moveTool(\work\Tool $eToolFrom, \work\Tool $eToolTo): void {

		$eToolTo->expects(['id', 'routineName', 'routineValue']);

		$cTask = Task::model()
			->select(['id', 'tools', 'products', 'treatment', 'action' => ['fqn']])
			->whereStructure($eToolFrom['structure'])
			->where('JSON_CONTAINS(tools, \''.$eToolFrom['id'].'\') OR JSON_CONTAINS(products, \''.$eToolFrom['id'].'\')')
			->getCollection();

		foreach($cTask as $eTask) {

			$eToolTo->applyMove($eTask, $eToolFrom);

			// Le produit donne sa dose aux traitements qui n'en portaient pas, comme à une intervention créée avec lui.
			if(
				$eToolTo['routineName'] === 'phyto' and
				$eTask['treatment'] === NULL and
				$eTask['action']['fqn'] === ACTION_TRAITEMENT
			) {
				$eTask['treatment'] = self::getTreatmentFromProducts(new \Collection([$eToolTo]));
			}

			Task::model()
				->select(['tools', 'products', 'treatment'])
				->update($eTask);

		}

	}

	public static function getTreatmentDefault(Task $eTask): ?array {

		$eTask->expects(['cProduct?']);

		return self::getTreatmentFromProducts($eTask['cProduct?']());

	}

	private static function getTreatmentFromProducts(\Collection $cTool): ?array {

		$eTool = $cTool->find(fn($eTool) => $eTool['routineName'] === 'phyto', limit: 1, default: new \work\Tool());

		if($eTool->empty()) {
			return NULL;
		}

		$product = $eTool['routineValue'];

		$dose = $product['dose'];
		$water = $product['readyToUse'] ? NULL : $product['water'];

		if($dose === NULL and $water === NULL) {
			return NULL;
		}

		return [
			'dose' => $dose,
			'unit' => ($dose === NULL) ? NULL : $product['unit'],
			'water' => $water,
		];

	}

	/**
	 * Une intervention de traitement écrite sans dose prend celle de son produit, et la garde : la
	 * dose du produit peut changer ensuite sans toucher aux interventions déjà écrites, ni au carnet
	 * qui en rend compte. Un traitement déjà posé, même partiel, est celui qu'a saisi l'utilisateur.
	 */
	private static function prepareTreatment(Task $e): void {

		$e->expects(['structure', 'products', 'action' => ['fqn']]);

		$e->add([
			'treatment' => NULL
		]);

		if(
			$e['treatment'] !== NULL or
			$e['action']['fqn'] !== ACTION_TRAITEMENT
		) {
			return;
		}

		$e['treatment'] = self::getTreatmentFromProducts(\work\ToolLib::askCollection($e['products'], $e['structure']));

	}

	/**
	 * Ce que montre le détail d'une intervention, sur `/tache/{id}` comme sur la page du planning, dont les
	 * intrants (`cProduct`) et le matériel (`cTool`), lus ici pour que la présentation les reçoive.
	 */
	public static function fillDetail(Task $eTask, \Collection $cPlace, ?int $sprayer): void {

		$eTask->expects(['cTool?', 'cProduct?']);

		$eTask['cultivation'] = CultivationLib::getById($eTask['cultivation']);
		$eTask['cTool'] = $eTask['cTool?']();
		$eTask['cProduct'] = $eTask['cProduct?']();

		self::fillHarvestDates($eTask);

		switch($eTask['action']['fqn']) {

			case ACTION_SEMIS_DIRECT :
			case ACTION_SEMIS_PEPINIERE :
			case ACTION_PLANTATION :
				if($eTask['cultivation']->empty()) {
					$eTask['cCultivation'] = CultivationLib::getBySeries($eTask['series']);
				} else {
					$eTask['cCultivation'] = new \Collection([$eTask['cultivation']]);
				}
				$formats = $eTask['series']->empty() ? [] : PlaceLib::getBedFormats($eTask['series'], $cPlace);

				foreach($eTask['cCultivation'] as $eCultivation) {
					CultivationLib::fillSliceStats($eCultivation);
					$eCultivation['densityPlans'] = $eTask['series']->empty() ? [] : CultivationLib::getDensityPlans($eTask['series'], $eCultivation, $formats);
				}
				break;

			case ACTION_TRAITEMENT :
				self::fillTreatment($eTask, $cPlace, $sprayer);
				break;

		}

	}

	public static function fillTreatment(Task $eTask, \Collection $cPlace, ?int $sprayer): void {

		$eTask->expects(['structure', 'treatment', 'cTool', 'cProduct']);

		$cTool = $eTask['cTool'];

		$eTask['eToolPhyto'] = $eTask['cProduct']->find(fn($eTool) => $eTool['routineName'] === 'phyto', limit: 1, default: new \work\Tool());
		$eTask['cToolSprayer'] = \work\ToolLib::getByStructure($eTask['structure'], routineName: 'sprayer');

		if($eTask['eToolPhyto']->empty()) {
			return;
		}

		$product = $eTask['eToolPhyto']['routineValue'];

		// Rien ne se lit dans le produit : sa dose change, et une intervention garde la sienne.
		$dose = $eTask['treatment']['dose'] ?? NULL;
		$unit = $eTask['treatment']['unit'] ?? $product['unit'];
		$water = $product['readyToUse'] ? NULL : ($eTask['treatment']['water'] ?? NULL);

		$eToolSprayerTask = $cTool->find(fn($eTool) => $eTool['routineName'] === 'sprayer', limit: 1, default: new \work\Tool());

		if($sprayer === NULL and $eToolSprayerTask->notEmpty()) {
			$sprayer = $eToolSprayerTask['id'];
		}

		// 0 est l'option « Sans pulvérisateur », NULL l'absence de choix.
		if($sprayer === 0) {
			$eToolSprayer = new \work\Tool();
		} else if($sprayer !== NULL and $eTask['cToolSprayer']->offsetExists($sprayer)) {
			$eToolSprayer = $eTask['cToolSprayer'][$sprayer];
		} else if($eTask['cToolSprayer']->notEmpty()) {
			$eToolSprayer = $eTask['cToolSprayer']->first();
		} else {
			$eToolSprayer = new \work\Tool();
		}

		$area = 0.0;

		foreach($cPlace as $ePlace) {
			$ePlace['treatmentQuantity'] = ($dose === NULL) ? NULL : $ePlace['area'] * $dose / 10000;
			$ePlace['treatmentWater'] = ($water === NULL) ? NULL : $ePlace['area'] * $water / 10000;
			$area += $ePlace['area'];
		}

		$eTask['treatmentShown'] = [
			'dose' => $dose,
			'unit' => $unit,
			'water' => $water,
			'spray' => ($product['readyToUse'] === FALSE),
			'area' => $area,
			'quantity' => ($dose === NULL) ? NULL : $area * $dose / 10000,
			'waterTotal' => ($water === NULL) ? NULL : $area * $water / 10000,
			'eToolSprayer' => $eToolSprayer,
			'tanks' => [],
		];

		if($dose !== NULL and $water !== NULL and $eToolSprayer->notEmpty()) {

			$perLiter = $dose / $water;

			$eTask['treatmentShown']['tanks'] = array_map(
				fn(float $volume) => ['water' => $volume, 'quantity' => $volume * $perLiter],
				\work\RoutineLib::getTanks($area * $water / 10000, $eToolSprayer['routineValue']['capacity'])
			);

		}

	}

	public static function fillHarvestDates(\Collection|Task $cTask): void {

		Task::model()
			->select([
				'harvestWorkingTime' => Timesheet::model()
					->select([
						'date',
						'total' => new \Sql('SUM(time)', 'float'),
					])
					->group($cTask instanceof \Collection ? ['task', 'date'] : 'date')
					->having('total > 0')
					->delegateCollection('task', 'date', callback: function(\Collection $cHarvest) {
						return $cHarvest->makeArray(function($eHarvest, &$key) {
							$key = $eHarvest['date'];
							return round($eHarvest['total'], 1);
						});
					}),
				'harvestDates' => Harvest::model()
					->select([
						'date',
						'total' => new \Sql('ROUND(SUM(quantity) * 10) / 10', 'float'),
					])
					->group($cTask instanceof \Collection ? ['task', 'date'] : 'date')
					->having('total != 0.0')
					->delegateCollection('task', 'date', callback: function(\Collection $cHarvest) {
						return $cHarvest->makeArray(function($eHarvest, &$key) {
							$key = $eHarvest['date'];
							return round($eHarvest['total'], 1);
						});
					})
			])
			->get($cTask);

	}

	public static function fillHarvestByDate(\Collection $cTask, string $date): void {

		$cTask->expects(['harvestDates']);

		foreach($cTask as $eTask) {
			$eTask['harvestByDate'] = $eTask['harvestDates'][$date] ?? 0.0;
		}

	}

	public static function fillHarvestWeeks(\Collection|Task $cTask): void {

		Task::model()
			->select([
				'harvestWeeks' => Harvest::model()
					->select([
						'week',
						'total' => new \Sql('ROUND(SUM(quantity) * 10) / 10', 'float'),
					])
					->group($cTask instanceof \Collection ? ['task', 'week'] : 'week')
					->having('total != 0.0')
					->delegateCollection('task', 'week', callback: function(\Collection $cHarvest) {
						return $cHarvest->makeArray(function($eHarvest, &$key) {
							$key = $eHarvest['week'];
							return round($eHarvest['total'], 1);
						});
					})
			])
			->get($cTask);

	}

	public static function fillHarvestByWeek(\Collection $cTask, string $week): void {

		$cTask->expects(['harvestWeeks']);

		foreach($cTask as $eTask) {
			$eTask['harvestByWeek'] = $eTask['harvestWeeks'][$week] ?? 0.0;
		}

	}

	public static function fillDistribution(\Collection $cTask): void {

		// Les tranches se lisent par culture et non par intervention : une intervention pas encore créée (« Récolter »
		// des Récoltes, `\planning\HarvestWeekLib::getTasksForBatch()`, « Nouvelle intervention » sur plusieurs
		// séries) n'a pas d'identifiant à lire, mais sa culture en a un.
		$cultivations = [];

		foreach($cTask as $eTask) {
			if($eTask['cultivation']->notEmpty()) {
				$cultivations[] = $eTask['cultivation']['id'];
			}
		}

		$ccSlice = ($cultivations === []) ?
			new \Collection() :
			Slice::model()
				->select([
					'cultivation', 'variety',
					'partPercent', 'partArea', 'partLength'
				])
				->whereCultivation('IN', array_values(array_unique($cultivations)))
				->getCollection(NULL, NULL, ['cultivation', 'variety']);

		foreach($cTask as $eTask) {

			$eTask['cSlice'] = ($eTask['cultivation']->notEmpty() and $ccSlice->offsetExists($eTask['cultivation']['id'])) ?
				$ccSlice[$eTask['cultivation']['id']] :
				new \Collection();

		}

		foreach($cTask as $eTask) {

			if(
				$eTask['series']->empty() or
				$eTask['series']['area'] === NULL
			) {
				$eTask['distributionArea'] = NULL;
				continue;
			}

			// Récupération du coefficient d'utilisation de la culture par la variété demandée et stockage dans une propriété distributionArea
			if($eTask['variety']->notEmpty()) {

				// La variété a pu être supprimée de la série entre temps, mais rester présente dans la tâche
				if($eTask['cSlice']->offsetExists($eTask['variety']['id'])) {

					$eSlice = $eTask['cSlice'][$eTask['variety']['id']];

					if($eSlice['partArea'] !== NULL) {
						$eTask['distributionArea'] = $eSlice['partArea'];
					} else if($eSlice['partPercent'] !== NULL) {
						$eTask['distributionArea'] = $eSlice['partPercent'] / 100 * $eTask['series']['area'];
					} else if($eSlice['partLength'] !== NULL) {
						$eTask['distributionArea'] = $eSlice['partLength'] * $eTask['series']['bedWidth'] / 100;
					} else {
						$eTask['distributionArea'] = $eTask['series']['area'];
					}

				} else {
					$eTask['distributionArea'] = 0;
				}

			} else {
				$eTask['distributionArea'] = $eTask['series']['area'];
			}

		}

	}

	public static function buildHarvests(\Collection $cTask, float $harvestAdd, string $harvestDate, string $harvestUnit, string $distribution, ?string $wrapper = NULL): void {

		if(\Filter::check('date', $harvestDate) === FALSE) {
			\Fail::log('Task::harvestDates.check', [], $wrapper);
			return;
		}

		$eTaskReference = $cTask->first();

		$fw = new \FailWatch();

		if($eTaskReference['harvestUnit'] === NULL) {
			$eTaskReference->buildProperty('harvestUnit', $harvestUnit);
		}

		if($fw->ko()) {
			return;
		}

		// La colonne est nullable pour les autres interventions, donc le schéma laisse passer une unité vide
		if($eTaskReference['harvestUnit'] === NULL) {
			\Fail::log('Task::harvestUnit.check', [], $wrapper);
			return;
		}

		foreach($cTask as $eTask) {

			$eTask->expects(['harvestDates']);

			if($eTask['action']['fqn'] !== ACTION_RECOLTE) {
				throw new \NotExpectedAction('Bad action');
			}

			// Même unité pour tout le monde
			if($eTask['harvestUnit'] === NULL) {
				$eTask['harvestUnit'] = $eTaskReference['harvestUnit'];
			} else if($eTask['harvestUnit'] !== $eTaskReference['harvestUnit']) {
				\Fail::log('Task::tasks.unit', [], $wrapper);
				return;
			}

		}

		if(self::checkDistribution($cTask, $distribution) === FALSE) {
			return;
		}

		if(self::distribute(
			$cTask,
			$distribution,
			$harvestAdd,
			1,
			fn(Task $eTask) => $eTask['harvest'] += $eTask['distributed']
		) === FALSE) {
			return;
		}

		// Vérification générale
		foreach($cTask as $eTask) {

			$harvested = $eTask['harvestDates'][$harvestDate] ?? 0.0;
			$newHarvested = $harvested + $eTask['distributed'];

			$eTask['harvest'] = round($eTask['harvest'], 1);

			if($newHarvested < 0) {
				\Fail::log('Task::harvestDates.negative', [], $wrapper);
				return;
			}

			if($eTask['harvest'] < 0) {
				\Fail::log('Task::harvestMore.negative', [], $wrapper);
				return;
			}

			$eTask['eHarvest'] = HarvestLib::getElementFromTask($eTask, $harvestDate, $eTask['distributed']);

		}

	}

	/**
	 * L'intervention de récolte que la saisie d'une semaine complète, par culture : celle à faire de la
	 * semaine, sinon celle faite dans la semaine, sinon la plus ancienne en retard que le planning montre
	 * dans « Retardé » (`applyDelayed()`). La faite passe devant le retard parce que le remplacement
	 * pose l'écart de toute la culture sur l'intervention choisie, dont `buildHarvests()` ne lit que la
	 * récolte du jour : la baisse d'un jour doit aller à l'intervention qui l'a récolté.
	 * Seules comptent les interventions du couple variété · calibre choisi, sans variété ni calibre par
	 * défaut : une saisie qui ne dit pas le couple n'envoie jamais ses kg à une ventilation que personne
	 * n'a choisie. Les interventions de la semaine ne comptent que dans l'unité de référence, ou sans unité
	 * encore (retour 319) : l'unité principale de la culture sans couple, la règle de
	 * `getHarvestReferenceUnits()` pour un couple ; une récolte en retard est prise telle qu'elle est.
	 *
	 * `$properties` réduit la sélection, `Task::getSelection()` par défaut, pour qui ne fait que lire quelle
	 * intervention serait retenue : la règle lit `cultivation`, `status` et `harvestUnit`, toujours ajoutés.
	 */
	public static function getHarvestForWeek(\structure\Structure $eStructure, \Collection $cCultivation, string $week, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), ?array $properties = NULL): \Collection {

		if($cCultivation->empty()) {
			return new \Collection();
		}

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		$properties = ($properties === NULL) ? Task::getSelection() : array_merge($properties, ['cultivation', 'status', 'harvestUnit']);

		$cTask = self::applyHarvestWeek(Task::model(), $eStructure, $cCultivation, $eAction, $week, $eVariety, $eSize)
			->select($properties)
			->getCollection();

		// L'intervention de la semaine est dans l'unité de référence, ou n'en a pas encore : une récolte de la
		// semaine dans une autre unité ne prend jamais sa place, même seule, et l'écran ne change pas d'unité
		// selon l'ordre des saisies de la semaine (retour 319). Le tri se fait ici et non dans la requête : une
		// culture qui a une récolte dans la semaine, faite ou à faire et quelle qu'en soit l'unité, n'est pas
		// en retard.
		$whole = ($eVariety->empty() and $eSize->empty());

		// Toutes les interventions de la semaine sont du même couple : la référence se range par culture.
		$references = $whole ? [] : self::getHarvestReferenceUnits($cTask, $cCultivation, fn(Task $eTask) => $eTask['cultivation']['id']);

		$cTaskByCultivation = new \Collection();
		$inWeek = [];

		foreach($cTask as $eTask) {

			$cultivation = $eTask['cultivation']['id'];

			$inWeek[$cultivation] = TRUE;

			$reference = $whole ?
				$cCultivation[$cultivation]['mainUnit'] :
				($references[$cultivation] ?? NULL);

			if(
				$eTask['harvestUnit'] !== NULL and
				$eTask['harvestUnit'] !== $reference
			) {
				continue;
			}

			if(
				$cTaskByCultivation->offsetExists($cultivation) === FALSE or
				$cTaskByCultivation[$cultivation]['status'] === Task::DONE
			) {
				$cTaskByCultivation[$cultivation] = $eTask;
			}

		}

		$cCultivationDelayed = $cCultivation->find(fn($eCultivation) => array_key_exists($eCultivation['id'], $inWeek) === FALSE);

		if(
			$cCultivationDelayed->notEmpty() and
			self::applyHarvestDelayed(Task::model(), $eStructure, $cCultivationDelayed, $eAction, $week, $eVariety, $eSize)
		) {

			$cTaskDelayed = Task::model()
				->select($properties)
				->getCollection();

			foreach($cTaskDelayed as $eTask) {

				$cultivation = $eTask['cultivation']['id'];

				if($cTaskByCultivation->offsetExists($cultivation) === FALSE) {
					$cTaskByCultivation[$cultivation] = $eTask;
				}

			}

		}

		return $cTaskByCultivation;

	}

	public static function applyHarvestWeek(TaskModel $mTask, \structure\Structure $eStructure, \Collection $cCultivation, \work\Action $eAction, string $week, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): TaskModel {

		return self::applyHarvestWeekAll($mTask, $eStructure, $cCultivation, $eAction, $week)
			->whereVariety($eVariety)
			->whereHarvestSize($eSize);

	}

	public static function applyHarvestWeekAll(TaskModel $mTask, \structure\Structure $eStructure, \Collection $cCultivation, \work\Action $eAction, string $week): TaskModel {

		return $mTask
			->whereStructure($eStructure)
			->whereCultivation('IN', $cCultivation)
			->whereAction($eAction)
			->where('(status = '.$mTask->format(Task::TODO).' AND plannedWeek = '.$mTask->format($week).') OR (status = '.$mTask->format(Task::DONE).' AND doneWeek = '.$mTask->format($week).')')
			->sort(['id' => SORT_ASC]);

	}

	/**
	 * Les récoltes à faire de la semaine qui tombent un jour à venir, culture par culture et jour par jour,
	 * `[cultivation => [date => nombre]]` : les Récoltes en marquent les jours qui ne sont pas arrivés
	 * (retour 410). Une récolte sans jour précis n'en a pas, ni une récolte prévue aujourd'hui ou un jour passé et
	 * pas faite, qui est en retard et non à venir. Une semaine finie au plus tard aujourd'hui n'a donc rien à lire.
	 */
	public static function getHarvestPlannedForWeek(\structure\Structure $eStructure, \Collection $cCultivation, string $week): array {

		$today = \Clock::date();

		if($cCultivation->empty() or week_date_ends($week) <= $today) {
			return [];
		}

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		$cTask = self::applyHarvestWeekAll(Task::model(), $eStructure, $cCultivation, $eAction, $week)
			->select(['cultivation', 'plannedDate'])
			->whereStatus(Task::TODO)
			->wherePlannedDate('>', $today)
			->getCollection();

		$planned = [];

		foreach($cTask as $eTask) {
			$cultivation = $eTask['cultivation']['id'];
			$planned[$cultivation][$eTask['plannedDate']] = ($planned[$cultivation][$eTask['plannedDate']] ?? 0) + 1;
		}

		return $planned;

	}

	public static function getHarvestUnitsForWeek(\structure\Structure $eStructure, \Collection $cCultivation, string $week): array {

		if($cCultivation->empty()) {
			return [];
		}

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		$cTask = self::applyHarvestWeekAll(Task::model(), $eStructure, $cCultivation, $eAction, $week)
			->select(['cultivation', 'variety', 'harvestSize', 'harvestUnit', 'status'])
			->where('variety IS NOT NULL OR harvestSize IS NOT NULL')
			->getCollection();

		$getReferenceKey = fn(Task $eTask) => $eTask['cultivation']['id'].'-'.CultivationLib::getHarvestPartKey($eTask['variety'], $eTask['harvestSize']);

		$references = self::getHarvestReferenceUnits($cTask, $cCultivation, $getReferenceKey);

		$units = [];
		$statuses = [];

		foreach($cTask as $eTask) {

			$cultivation = $eTask['cultivation']['id'];
			$key = CultivationLib::getHarvestPartKey($eTask['variety'], $eTask['harvestSize']);

			$reference = $references[$getReferenceKey($eTask)] ?? NULL;

			// La règle de `getHarvestForWeek()` : une intervention dans une autre unité que la référence du
			// couple n'est jamais la sienne.
			if(
				$eTask['harvestUnit'] !== NULL and
				$eTask['harvestUnit'] !== $reference
			) {
				continue;
			}

			if(($statuses[$cultivation][$key] ?? Task::DONE) === Task::DONE) {
				$units[$cultivation][$key] = $eTask['harvestUnit'];
				$statuses[$cultivation][$key] = $eTask['status'];
			}

		}

		// Un couple sans intervention dans la semaine écrit sur sa plus ancienne en retard, comme
		// getHarvestForWeek() : son unité vient de là.
		if(self::applyHarvestDelayedAll(Task::model(), $eStructure, $cCultivation, $eAction, $week)) {

			$cTaskDelayed = Task::model()
				->select(['cultivation', 'variety', 'harvestSize', 'harvestUnit'])
				->where('variety IS NOT NULL OR harvestSize IS NOT NULL')
				->getCollection();

			foreach($cTaskDelayed as $eTask) {

				$cultivation = $eTask['cultivation']['id'];
				$key = CultivationLib::getHarvestPartKey($eTask['variety'], $eTask['harvestSize']);

				if(isset($units[$cultivation][$key]) === FALSE) {
					$units[$cultivation][$key] = $eTask['harvestUnit'];
				}

			}

		}

		return $units;

	}

	/**
	 * L'unité de référence de chaque couple variété · calibre dans la semaine, par la clé que rend `$getKey` :
	 * l'unité principale de sa culture quand une de ses interventions de la semaine y est, sinon celle de sa
	 * première intervention qui en porte une, les interventions arrivant par identifiant croissant
	 * (`applyHarvestWeekAll()`). Une saisie dans une autre unité (HN-B2b, 21/10, « Autoriser ») ne
	 * fait donc jamais passer la ligne de sa part dans celle-ci, et la série donne la référence avant l'ordre
	 * des saisies (M-Planning, 21/10). Un couple dont aucune intervention n'a d'unité n'a pas de clé.
	 */
	private static function getHarvestReferenceUnits(\Collection $cTask, \Collection $cCultivation, \Closure $getKey): array {

		$references = [];

		foreach($cTask as $eTask) {

			$unit = $eTask['harvestUnit'];

			if($unit === NULL) {
				continue;
			}

			$key = $getKey($eTask);

			if(
				array_key_exists($key, $references) === FALSE or
				$unit === $cCultivation[$eTask['cultivation']['id']]['mainUnit']
			) {
				$references[$key] = $unit;
			}

		}

		return $references;

	}

	/**
	 * Rend `FALSE` sans rien poser quand `applyDelayed()` refuse la semaine : sur le modèle singleton, une
	 * condition posée survivrait jusqu'à la requête suivante.
	 */
	public static function applyHarvestDelayed(TaskModel $mTask, \structure\Structure $eStructure, \Collection $cCultivation, \work\Action $eAction, string $week, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): bool {

		if(self::applyHarvestDelayedAll($mTask, $eStructure, $cCultivation, $eAction, $week) === FALSE) {
			return FALSE;
		}

		$mTask
			->whereVariety($eVariety)
			->whereHarvestSize($eSize);

		return TRUE;

	}

	public static function applyHarvestDelayedAll(TaskModel $mTask, \structure\Structure $eStructure, \Collection $cCultivation, \work\Action $eAction, string $week): bool {

		if(self::applyDelayed($mTask, $eStructure, $week) === FALSE) {
			return FALSE;
		}

		$mTask
			->whereStructure($eStructure)
			->whereCultivation('IN', $cCultivation)
			->whereAction($eAction);

		return TRUE;

	}

	/**
	 * La récolte s'écrit sur `$date`, le jour de la saisie, et le temps sur le jour qu'il porte
	 * (`$eTimesheet['date']`), que le tiroir de l'écran des récoltes laisse choisir dans la semaine.
	 *
	 * Un ajout négatif retire du jour saisi, et pas plus que ce que ce jour a récolté (retour 213) : au-delà, il
	 * est refusé. Seule une quantité positive termine une intervention à faire : une soustraction, ajout négatif
	 * ou remplacement à la baisse, ne dit pas qu'une récolte est finie (arbitrage V-Planning du
	 * 2026-10-10), et un temps seul la laisse en l'état.
	 *
	 * Le temps de chaque personne (`$cTimesheet`, R-Retours-0810, 224) s'ajoute à celui de l'intervention, sauf
	 * en remplacement, qui le remplace : il devient alors le temps de la personne sur l'intervention ce jour-là,
	 * et 0 en retire les lignes (R-Retours-0810, 219). Un ajout ne porte que des durées positives
	 * (`HarvestWeekLib::buildTime()`).
	 *
	 * Une intervention qui n'a encore rien récolté n'a pas d'unité : elle prend `$harvestUnit` à sa première
	 * quantité, et l'unité principale de sa culture quand la saisie n'en choisit pas (retour 319).
	 */
	public static function writeHarvestForWeek(Task $eTask, \Collection $cTimesheet, string $date, string $mode, bool $part = FALSE, ?float $crateWeight = NULL, ?string $harvestUnit = NULL): float {

		return self::writePreparedHarvestForWeek(self::prepareHarvestForWeek($eTask, $cTimesheet, $date, $mode, $part, $crateWeight, $harvestUnit));

	}

	/**
	 * Tout ce que `writeHarvestForWeek()` lit et refuse, sans rien écrire : l'intervention relue, ses récoltes
	 * construites et la quantité signée. Un appelant qui écrit autre chose avec la récolte (caisses, stock) prépare
	 * d'abord, puis ouvre une seule transaction autour de `writePreparedHarvestForWeek()` et du reste : un refus
	 * levé dans une transaction ouverte la laisserait ouverte, `rollBack()` remettant à zéro toute la pile.
	 *
	 * `$wrapper` rattache ses refus à un champ autre que celui de la saisie : la rangée d'une autre unité du
	 * remplacement d'un jour (`harvestDay[<unité>]`, `HarvestWeekLib::prepareEntry()`).
	 *
	 * @return array ce que `writePreparedHarvestForWeek()` écrit, `write` à FALSE quand il n'y a rien à écrire
	 */
	public static function prepareHarvestForWeek(Task $eTask, \Collection $cTimesheet, string $date, string $mode, bool $part = FALSE, ?float $crateWeight = NULL, ?string $harvestUnit = NULL, ?string $wrapper = NULL): array {

		$eTask->expects(['structure', 'cultivation']);

		// Tout se vérifie avant la première écriture : rollBack() remet à zéro toute la pile de
		// transactions, celle de l'appelant comprise.
		if($eTask->exists()) {

			$eTaskWritten = Task::model()
				->select(Task::getSelection())
				->whereId($eTask['id'])
				->get();

			$cTask = new \Collection([$eTaskWritten]);

			self::fillHarvestDates($cTask);
			self::fillDistribution($cTask);

		} else {

			$eTaskWritten = $eTask;
			$eTaskWritten['harvestDates'] = [];

			$cTask = new \Collection([$eTaskWritten]);

		}

		$unit = $eTaskWritten['harvestUnit'] ?? $harvestUnit ?? $eTaskWritten['cultivation']['mainUnit'];

		if($crateWeight !== NULL) {

			$fw = new \FailWatch();

			if($unit !== Task::KG) {
				\Fail::log('Task::harvestUnit.crate');
			}

			$fw->validate();

			$property = match($mode) {
				'add' => 'harvestMore',
				'replace' => 'harvestDay',
			};

			$eTask[$property] = round($eTask[$property] * $crateWeight, 1);

		}

		$timeSpent = $cTimesheet->find(fn(Timesheet $eTimesheet) => $eTimesheet['time'] > 0.0)->notEmpty();

		if($mode === 'add' and $eTask['harvestMore'] < 0.0) {

			$fw = new \FailWatch();

			$harvested = self::getHarvestedForWrite($eTask, $eTaskWritten, $date, $unit, $part, 'Task::harvestMore.ventilated');

			if(round($harvested + $eTask['harvestMore'], 3) < 0.0) {
				\Fail::log('Task::harvestMore.day');
			}

			// Un retrait corrige une quantité mal saisie, ce n'est pas du travail fait : il n'ajoute pas de temps,
			// comme dans la case (`removeTime`, `HarvestIntentionLib`).
			if($timeSpent) {
				\Fail::log('Task::harvestMore.time', [], 'time');
			}

			$fw->validate();

		}

		$quantity = match($mode) {
			'add' => $eTask['harvestMore'],
			'replace' => round($eTask['harvestDay'] - self::getHarvestedForWrite($eTask, $eTaskWritten, $date, $unit, $part, 'Task::harvestDay.ventilated', $wrapper), 3),
		};

		// Une durée nulle ne remplace rien sur une intervention qui n'existe pas encore.
		if(
			$quantity === 0.0 and
			$eTaskWritten->exists() === FALSE and
			$timeSpent === FALSE
		) {
			$write = FALSE;
		} else {
			$write = TRUE;
		}

		if($write and $quantity !== 0.0) {

			$fw = new \FailWatch();

			self::buildHarvests($cTask, $quantity, $date, $unit, 'fair', $wrapper);

			$fw->validate();

		}

		return [
			'write' => $write,
			'eTaskWritten' => $eTaskWritten,
			'cTask' => $cTask,
			'cTimesheet' => $cTimesheet,
			'date' => $date,
			'mode' => $mode,
			'quantity' => $quantity,
		];

	}

	/**
	 * L'écriture d'une récolte préparée par `prepareHarvestForWeek()`, qui ne refuse plus rien.
	 */
	public static function writePreparedHarvestForWeek(array $harvest): float {

		if($harvest['write'] === FALSE) {
			return 0.0;
		}

		['eTaskWritten' => $eTaskWritten, 'cTask' => $cTask, 'cTimesheet' => $cTimesheet, 'date' => $date, 'mode' => $mode, 'quantity' => $quantity] = $harvest;

		Task::model()->beginTransaction();

			// La récolte construite porte l'intervention elle-même : créée ici, elle lui donne son identifiant.
			if($eTaskWritten->exists() === FALSE) {
				self::create($eTaskWritten);
			}

			if($quantity !== 0.0) {
				self::update($eTaskWritten, ['harvest', 'harvestUnit']);
			}

			// Sur une seule intervention en répartition « fair », writeByTasks() ne refuse rien : ses deux
			// refus portent sur la surface de la série et sur une répartition au récolté. Un remplacement n'en
			// écrit que l'écart, dont l'appelant a vérifié la journée de 24 heures (`HarvestWeekLib::writeEntry()`).
			foreach($cTimesheet as $eTimesheet) {

				$eTimesheet->expects(['structure', 'user', 'date', 'time']);

				if($mode === 'replace') {
					TimesheetLib::writeTimeForDay($eTaskWritten, $eTimesheet);
				} else {
					TimesheetLib::writeByTasks($cTask, $eTimesheet, $eTimesheet['time'], 'fair');
				}

			}

			if($quantity > 0.0 and $eTaskWritten['status'] === Task::TODO) {

				// `updateDoneCollection()` date l'intervention du plus tard de sa planification et de son
				// temps. Le report fait du jour de la récolte un plancher, sans quoi elle serait faite le jour
				// prévu ; un temps posé plus tard dans la semaine la date de ce jour, comme toute intervention.
				if($eTaskWritten['plannedDate'] !== $date) {
					self::updatePlannedDateCollection($cTask, $date);
				}

				self::updateDoneCollection($cTask, toWeek($date));

			}

		Task::model()->commit();

		return $quantity;

	}

	/**
	 * Le récolté du jour que remplace ou que diminue une saisie de la semaine : celui du couple pour une part,
	 * sinon celui du jour entier, refusé (`$ventilated`) quand une part du jour est répartie par variété ou par
	 * calibre, l'écart ne pouvant pas se poser sur une seule intervention.
	 */
	protected static function getHarvestedForWrite(Task $eTask, Task $eTaskWritten, string $date, string $unit, bool $part, string $ventilated, ?string $wrapper = NULL): float {

		if($part) {

			return self::applyHarvestedDayCouple(Harvest::model(), $eTask['structure'], $eTask['cultivation'], $date, $unit, $eTaskWritten['variety'], $eTaskWritten['harvestSize'])
				->select(['total' => new \Sql('IFNULL(SUM(quantity), 0)', 'float')])
				->get()['total'];

		}

		$fw = new \FailWatch();

		$harvestedVentilated = self::applyHarvestedDayVentilated(Harvest::model(), $eTask['structure'], $eTask['cultivation'], $date, $unit)
			->select(['total' => new \Sql('IFNULL(SUM(quantity), 0)', 'float')])
			->get()['total'];

		if(round($harvestedVentilated, 3) !== 0.0) {
			\Fail::log($ventilated, [], $wrapper);
		}

		$fw->validate();

		return self::applyHarvestedDay(Harvest::model(), $eTask['structure'], $eTask['cultivation'], $date, $unit)
			->select(['total' => new \Sql('IFNULL(SUM(quantity), 0)', 'float')])
			->get()['total'];

	}

	public static function applyHarvestedDay(HarvestModel $mHarvest, \structure\Structure $eStructure, Cultivation $eCultivation, string $date, string $unit): HarvestModel {

		return $mHarvest
			->whereStructure($eStructure)
			->whereDate($date)
			->whereCultivation($eCultivation)
			->whereUnit($unit);

	}

	public static function applyHarvestedDayVentilated(HarvestModel $mHarvest, \structure\Structure $eStructure, Cultivation $eCultivation, string $date, string $unit): HarvestModel {

		// Sans whereStructure(), que la culture porte déjà : les deux bornes font croiser à MySQL
		// (structure, action) et (cultivation, action) en index_merge au lieu de lire le second.
		$mTaskVentilated = Task::model()
			->select('id')
			->whereCultivation($eCultivation)
			->whereAction(\work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE))
			->where('variety IS NOT NULL OR harvestSize IS NOT NULL');

		return self::applyHarvestedDay($mHarvest, $eStructure, $eCultivation, $date, $unit)
			->whereTask('IN', $mTaskVentilated->getSql());

	}

	public static function applyHarvestedDayCouple(HarvestModel $mHarvest, \structure\Structure $eStructure, Cultivation $eCultivation, string $date, string $unit, \plant\Variety $eVariety, \plant\Size $eSize): HarvestModel {

		// Sans whereStructure(), pour la même raison que applyHarvestedDayVentilated().
		$mTaskCouple = Task::model()
			->select('id')
			->whereCultivation($eCultivation)
			->whereAction(\work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE))
			->whereVariety($eVariety)
			->whereHarvestSize($eSize);

		return self::applyHarvestedDay($mHarvest, $eStructure, $eCultivation, $date, $unit)
			->whereTask('IN', $mTaskCouple->getSql());

	}

	public static function checkDistribution(\Collection $cTask, string $distribution): bool {

		if($distribution === 'plant' or $distribution === 'area') {

			foreach($cTask as $eTask) {

				if($eTask['series']->empty()) {
					\Fail::log('Task::tasks.notSeries');
					return FALSE;
				}

				if($eTask['series']['area'] === NULL) {
					\Fail::log('Task::tasks.area');
					return FALSE;
				}

			}

		}

		if($distribution === 'harvest') {

			if($cTask->sum('harvestByDate') === 0.0) {
				\Fail::log('Task::distribution.harvestZero');
				return FALSE;
			}

		}

		return TRUE;

	}

	public static function distribute(\Collection $cTask, string $distribution, float $value, int $precision, \Closure $callback): bool {

		if($cTask->count() === 1) {
			$eTask = $cTask->first();
			$eTask['distributed'] = round($value, $precision);
			$callback($eTask);
			return TRUE;
		}

		$factor = pow(10, $precision);

		// On travaille avec une précision de 0.1 pour la répartition
		switch($distribution) {

			// Même valeur pour tout le monde
			case 'fair' :

				$totalTasks = $cTask->count();

				$part = floor($value * $factor / $totalTasks);
				$remain = $value * $factor - $part * $totalTasks;

				foreach($cTask as $eTask) {
					$eTask['distributed'] = $part / $factor;
					if($remain-- > 0) {
						$eTask['distributed'] += 1 / $factor;
					}
					$callback($eTask);
				}

				break;

			// Répartition en fonction de la récolte le jour de travail
			case 'harvest' :

				$cTask->expects(['harvestByDate']);

				$totalHarvest = $cTask->sum('harvestByDate');

				$remain = $value * $factor;

				foreach($cTask as $eTask) {

					$part = floor($eTask['harvestByDate'] / $totalHarvest * $value * $factor);

					$eTask['distributed'] = $part / $factor;
					$remain -= $part;

				}

				foreach($cTask as $eTask) {
					if($remain-- > 0) {
						$eTask['distributed'] += 1 / $factor;
					}
					$callback($eTask);
				}

				break;

			// Répartition en fonction de la surface
			case 'area' :

				$totalArea = $cTask->sum('distributionArea');

				$remain = $value * $factor;

				foreach($cTask as $eTask) {

					$part = floor($eTask['distributionArea'] / $totalArea * $value * $factor);

					$eTask['distributed'] = $part / $factor;
					$remain -= $part;

				}

				foreach($cTask as $eTask) {
					if($remain-- > 0) {
						$eTask['distributed'] += 1 / $factor;
					}
					$callback($eTask);
				}

				break;

			case 'plant' :

				$totalPlants = 0;

				foreach($cTask as $eTask) {

					if($eTask['cultivation']->empty() or $eTask['cultivation']['density'] === NULL) {
						\Fail::log('Task::tasks.density');
						return FALSE;
					}

					$totalPlants += $eTask['cultivation']['density'] * $eTask['distributionArea'];

				}

				$remain = $value * $factor;

				foreach($cTask as $eTask) {

					$part = floor($eTask['cultivation']['density'] * $eTask['distributionArea'] / $totalPlants * $value * $factor);

					$eTask['distributed'] = $part / $factor;
					$remain -= $part;

				}

				foreach($cTask as $eTask) {
					if($remain-- > 0) {
						$eTask['distributed'] += 1 / $factor;
					}
					$callback($eTask);
				}

				break;

		}

		return TRUE;

	}

	/**
	 * Construit une liste de Task à partir de Flow
	 */
	public static function buildFromFlow(\Collection $cFlow, Series $eSeries, \Collection $cCultivation, int $season, ?int $referenceYear = NULL): array {

		$eSeries->expects(['structure']);
		$cCultivation->expects(['crop']);

		$eCategory = \work\CategoryLib::getByStructure($eSeries['structure'], fqn: CATEGORIE_CULTURE);
		$cTask = new \Collection()->setDepth(2);
		$cRepeat = new \Collection();

		$referenceYear ??= $season;

		foreach($cFlow as $eFlow) {

			$eAction = $eFlow['action'];

			if($eAction['series'] === FALSE) {
				continue;
			}

			$e = ($eFlow['weekOnly'] === NULL) ? new Repeat() : new Task();

			$e->merge([
				'season' => $season,
				'structure' => $eSeries['structure'],
				'series' => $eSeries,
				'plant' => $eFlow['plant'],
				'action' => $eAction,
				'methods' => $eFlow['methods'],
				'tools' => $eFlow['tools'],
				'products' => $eFlow['products'],
				'category' => $eCategory,
				'description' => $eFlow['description'],
				'fertilizer' => $eFlow['fertilizer'],
				'crop' => $eFlow['crop'],
				'variety' => new \plant\Variety(),
				'status' => Task::TODO
			]);

			if($eFlow['crop']->notEmpty()) {
				$e['cultivation'] = $cCultivation->find(fn($eCultivation) => ($eCultivation['crop']->notEmpty() and $eCultivation['crop']['id'] === $eFlow['crop']['id']), limit: 1);
				$e['series'] = $e['cultivation']['series'];
			} else {
				$e['cultivation'] = new Cultivation();
			}

			if($eFlow['weekOnly'] !== NULL) {

				$plannedWeek = ($referenceYear + $eFlow['yearOnly']).'-W'.sprintf('%02d', $eFlow['weekOnly']);

				$e['plannedWeek'] = $plannedWeek;
				$e['position'] = $eFlow['positionOnly'];
				$e['display'] = $e['plannedWeek'];

				$cTask->append($e);

			} else {

				$weekStart = ($referenceYear + $eFlow['yearStart']).'-W'.sprintf('%02d', round($eFlow['weekStart']));
				$weekStop = ($referenceYear + $eFlow['yearStop']).'-W'.sprintf('%02d', round($eFlow['weekStop']));

				$e['frequency'] = $eFlow['frequency'];
				$e['start'] = week_date_day($weekStart, 3);
				$e['stop'] = $weekStop;

				$cRepeat->append($e);

			}

		}

		// Tri en fonction de la semaine et de la position
		$cTask->uasort(function($a, $b) {

			if($a['plannedWeek'] !== $b['plannedWeek']) {
				return strcmp($a['plannedWeek'], $b['plannedWeek']);
			}

			if($b['position'] === NULL) {
				return -1;
			} else if($a['position'] === NULL) {
				return 1;
			} else {
				return ($a['position'] < $b['position']) ? -1 : 1;
			}

		});

		return [$cTask, $cRepeat];

	}

	public static function createCollection(\Collection $c): void {

		foreach($c as $e) {
			self::create($e);
		}

	}

	public static function create(Task $e): void {

		$e->expects([
			'action',
			'series',
			'structure',
			'category',
			'status',
			'repeatMaster'
		]);

		if($e['category']['fqn'] === CATEGORIE_CULTURE) {
			$e->expects(['cultivation', 'plant']);
		} else {
			$e['cultivation'] = new Cultivation();
			$e['plant'] = new \plant\Plant();
		}

		if($e['series']->notEmpty()) {
			$e['series']->expects(['season']);
			$e['season'] = $e['series']['season'];
		}

		if($e['status'] === Task::DONE) {

			$e->expects(['doneWeek']);

			$e->add([
				'plannedWeek' => $e['doneWeek'],
				'plannedDate' => $e['doneDate'],
			]);

		}

		Task::model()->beginTransaction();

		self::prepareProducts($e);
		self::prepareTreatment($e);

		if($e['repeatMaster']->notEmpty()) {

			$eRepeat = RepeatLib::createFromTask($e);

			// Si c'est pour une série, on crée immédiatement toutes les tâches car il y a une finitude dans le temps
			if($e['series']->notEmpty()) {
				RepeatLib::createForSeries($eRepeat);
			}

		}

		parent::create($e);

		if($e['action']['fqn'] === ACTION_RECOLTE) {
			self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);
		}

		SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);

		Task::model()->commit();

	}

	private static function prepareProducts(Task $e): void {

		$e->add([
			'products' => [],
			'ePhytoProductNew' => new \work\PhytoProduct(),
			'eToolNew' => new \work\Tool()
		]);

		if($e['eToolNew']->notEmpty()) {
			$e['products'] = [\work\ToolLib::createPhytoNew($e['eToolNew'], $e['treatment'])['id']];
			return;
		}

		if($e['ePhytoProductNew']->empty()) {
			return;
		}

		$eTool = \work\ToolLib::createPhytoFromCatalog($e['structure'], $e['ePhytoProductNew'], $e['treatment']);

		$e['products'] = [$eTool['id']];

	}

	public static function createFromRepeat(Repeat $e, string $week): void {

		$eTask = new Task($e->extracts(RepeatLib::getTaskProperties()));
		$eTask['structure'] = $e['structure'];
		$eTask['repeat'] = $e;

		$day = match($e['precision']) {
			Repeat::DAY => (int)date('N', strtotime($e['start'])),
			Repeat::WEEK => 3,
		};

		switch($eTask['status']) {

			case Task::TODO :
				$eTask['plannedWeek'] = $week;
				if($e['precision'] === Repeat::DAY) {
					$eTask['plannedDate'] = week_date_day($week, $day);
				}
				break;

			case Task::DONE :
				$eTask['plannedWeek'] = $week;
				$eTask['doneWeek'] = $week;
				$eTask['doneDate'] = week_date_day($week, $day);
				break;

		}

		self::prepareTreatment($eTask);

		Task::model()->beginTransaction();

			parent::create($eTask);

			SeriesLib::recalculate($eTask['structure'], $eTask['series'], $eTask['action']);

		Task::model()->commit();

	}

	public static function updateCheck(Task $e, int $position, bool $checked): void {

		if(
			$e['description'] === NULL or
			substr_count($e['description'], "\n") < $position
		) {
			return;
		}

		$lines = explode("\n", $e['description']);

		if(str_starts_with($lines[$position], 'x ') or str_starts_with($lines[$position], 'o ')) {
			$lines[$position] = ($checked ? 'X' : 'O').' '.substr($lines[$position], 2);
		} else if(str_starts_with($lines[$position], 'X ') or str_starts_with($lines[$position], 'O ')) {
			$lines[$position] = ($checked ? 'X' : 'O').' '.substr($lines[$position], 2);
		} else {
			$lines[$position] = ($checked ? 'X' : 'O').' '.$lines[$position];
		}

		$e['description'] = implode("\n", $lines);

		Task::model()
			->select('description')
			->update($e);

	}

	public static function updateUser(\Collection $c, \user\User $eUser, string $action): void {

		foreach($c as $e) {

			switch($action) {

				case 'delete' :
					$newUsers = array_diff($e['plannedUsers'], [$eUser['id']]);

					if($newUsers !== $e['plannedUsers']) {
						Task::model()->update($e, [
							'plannedUsers' => $newUsers
						]);
					}

					break;

				case 'add' :
					if(in_array($eUser['id'], $e['plannedUsers']) === FALSE) {

						$newUsers = $e['plannedUsers'];
						$newUsers[] = $eUser['id'];

						Task::model()->update($e, [
							'plannedUsers' => $newUsers
						]);

					}
					break;

			}

		}

	}

	// « Ce qui suit » une intervention : les interventions à faire de la même
	// série dont la semaine est égale ou postérieure — les faites restent, c'est
	// le passé. Décaler un semis sans elles le fait passer après sa plantation.
	public static function getFollowing(Task $e): \Collection {

		$e->expects(['series', 'plannedWeek']);

		if($e['series']->empty() or $e['plannedWeek'] === NULL) {
			return new \Collection();
		}

		return Task::model()
			->select(['id', 'structure', 'plannedWeek', 'status', 'series' => Series::getSelection(), 'action' => \work\Action::getSelection(), 'cultivation' => ['plant' => ['name']]])
			->whereSeries($e['series'])
			->whereStatus(Task::TODO)
			->wherePlannedWeek('>=', $e['plannedWeek'])
			->whereId('!=', $e)
			->sort(['plannedWeek' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

	// L'ordre des interventions d'une semaine, à la main : les ids reçus dans
	// l'ordre voulu, renumérotés de 0 à n. Une intervention hors de la série ou
	// de la semaine est refusée en bloc — le POST se contourne.
	public static function updatePosition(Series $eSeries, string $week, array $ids): void {

		$cTask = Task::model()
			->select(['id', 'structure', 'series', 'plannedWeek', 'status'])
			->whereSeries($eSeries)
			->wherePlannedWeek($week)
			->whereStatus(Task::TODO)
			->getCollection(NULL, NULL, 'id');

		if(count($ids) !== $cTask->count()) {
			\Fail::log('Task::position.check');
			return;
		}

		foreach($ids as $id) {
			if($cTask->offsetExists((int)$id) === FALSE) {
				\Fail::log('Task::position.check');
				return;
			}
		}

		Task::model()->beginTransaction();

		foreach(array_values($ids) as $position => $id) {

			$eTask = $cTask[(int)$id];
			$eTask['position'] = $position;

			self::update($eTask, ['position']);

		}

		Task::model()->commit();

	}

	public static function incrementPlannedFollowing(Task $e, int $increment): void {

		$cTask = new \Collection([$e['id'] => $e]);
		$cTask->appendCollection(self::getFollowing($e));

		self::incrementPlannedCollection($cTask, $increment);

		CultivationLib::incrementHarvestPeriodExpectedBySeriesCollection(new \Collection([$e['series']]), $increment, fromWeek: $e['plannedWeek']);

	}

	public static function incrementPlannedCollection(\Collection $c, int $increment): void {

		// Refuser explicitement plutôt que d'ignorer : un décalage hors plage qui
		// ne fait rien puis annonce un succès laisse l'utilisateur devant une
		// intervention revenue à sa place sans explication.
		if($increment < -26 or $increment > 26) {
			\Fail::log('Task::planned.season');
			return;
		}

		// Traitement en PHP, impossible à faire en une seule requête
		foreach($c as $e) {
			self::incrementPlanned($e, $increment);
		}

	}

	/**
	 * Décale les interventions à faire d'un lot de séries.
	 *
	 * Une intervention déjà faite ne se déplace pas (Task::acceptPostpone), et une
	 * série entamée est le cas courant : sur la saison 2026, 13 % des séries mêlent
	 * du fait et du à faire. On ne refuse donc pas le lot, on décale ce qui reste et
	 * on rend le compte des deux pour que l'appelant puisse le dire.
	 */
	public static function incrementPlannedBySeriesCollection(\Collection $cSeries, int $increment): array {

		$cTask = Task::model()
			->select([
				'id', 'structure', 'plannedWeek', 'status',
				'series' => Series::getSelection(),
				'action' => \work\Action::getSelection()
			])
			->whereSeries('IN', $cSeries)
			->getCollection();

		$cTaskPostponable = $cTask->find(fn($eTask) => $eTask->acceptPostpone());

		self::incrementPlannedCollection($cTaskPostponable, $increment);

		return [$cTaskPostponable->count(), $cTask->count() - $cTaskPostponable->count()];

	}

	private static function incrementPlanned(Task $e, int $increment): void {

		if($increment < -26 or $increment > 26) {
			return;
		}

		$e->expects(['plannedWeek']);

		$newPlanned = toWeek($e['plannedWeek'].' '.sprintf('%+0d', $increment).' WEEK');

		Series::model()->beginTransaction();

		// Le jour prévu se décale avec la semaine : une intervention décalée puis ramenée retrouve son jour.
		// Un jour nul reste nul.
		Task::model()
			->wherePlannedWeek('!=', NULL)
			->update($e, [
				'plannedWeek' => $newPlanned,
				'plannedDate' => new \Sql('plannedDate + INTERVAL '.$increment.' WEEK'),
				'position' => NULL
			]);

		SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);

		Series::model()->commit();

	}

	public static function updateTodoCollection(\Collection $c): void {

		Series::model()->beginTransaction();

		// Traitement en PHP car traitements intermédiaires en PHP
		foreach($c as $e) {

			$e['status'] = Task::TODO;
			$e['doneWeek'] = NULL;
			$e['doneDate'] = NULL;

			self::update($e, ['status', 'doneWeek', 'doneDate']);

			HistoryLib::createForTask($e, History::TODO);

			SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);

		}

		Series::model()->commit();

	}

	/**
	 * Remet à faire une intervention qu'une écriture a terminée, à la semaine, au jour prévu, à la récolte et à
	 * l'unité que porte `$e`, à condition qu'elle soit encore dans l'état où cette écriture l'a laissée (`$left` : `status`,
	 * `plannedDate`, `doneWeek` et `harvest`). Les conditions vont dans la requête et non dans une lecture
	 * préalable : une écriture d'une autre voie, survenue entre les deux, ne serait pas vue.
	 *
	 * `updateTodoCollection()` garde la planification, que la récolte a reportée au jour récolté, et
	 * `updatePlannedDateCollection()` ne sait pas rendre un jour prévu nul.
	 *
	 * @return bool FALSE quand l'intervention a changé depuis : elle reste alors en l'état.
	 */
	public static function updateTodoIfLeft(Task $e, array $left): bool {

		$e->expects(['id', 'structure', 'series', 'cultivation', 'plant', 'action', 'plannedWeek', 'plannedDate', 'harvest', 'harvestUnit']);

		Series::model()->beginTransaction();

		$e['status'] = Task::TODO;
		$e['doneWeek'] = NULL;
		$e['doneDate'] = NULL;
		$e['updatedAt'] = new \Sql('NOW()');

		// `harvest` est un flottant simple en base : une égalité stricte avec la valeur relue en double
		// échouerait sur la plupart des quantités décimales. Une intervention qui ne se récolte pas n'en a pas.
		$affected = Task::model()
			->select(['status', 'plannedWeek', 'plannedDate', 'doneWeek', 'doneDate', 'harvest', 'harvestUnit', 'updatedAt'])
			->whereStatus($left['status'])
			->wherePlannedDate($left['plannedDate'])
			->whereDoneWeek($left['doneWeek'])
			->where(($left['harvest'] === NULL) ? 'harvest IS NULL' : 'ROUND(harvest, 2) = '.Task::model()->format(round($left['harvest'], 2)))
			->update($e);

		if($affected > 0) {

			HistoryLib::createForTask($e, History::TODO);

			self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);
			SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);

		}

		Series::model()->commit();

		return ($affected > 0);

	}

	/**
	 * `$doneDate` fait l'intervention ce jour-là, et dans sa semaine, quand la personne dit le jour : sans lui, la
	 * semaine et le jour se déduisent de ce que l'intervention porte (`getDoneSql()`).
	 */
	public static function updateDoneCollection(\Collection $c, ?string $newDone, ?string $doneDate = NULL): void {

		Series::model()->beginTransaction();

		[$doneWeekSql, $doneDateSql] = ($doneDate === NULL) ?
			self::getDoneSql($newDone) :
			[Task::model()->format(toWeek($doneDate)), Task::model()->format($doneDate)];

		// Traitement en PHP car traitements intermédiaires en PHP
		foreach($c as $e) {

			$e['doneWeek'] = new \Sql($doneWeekSql);
			$e['doneDate'] = new \Sql($doneDateSql);
			$e['status'] = Task::DONE;

			self::update($e, ['doneWeek', 'doneDate', 'status']);

			HistoryLib::createForTask($e, History::DONE);

			SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);

		}

		Series::model()->commit();

	}

	private static function getDoneSql(?string $newDone): array {

		$newDone ??= \Clock::week();

		$greatestWeek = 'GREATEST(
			COALESCE(plannedWeek, "0000-W00"),
			IF(timesheetStop IS NULL, "0000-W00", CONCAT(SUBSTRING(YEARWEEK(timesheetStop, 1), 1, 4), "-W", SUBSTRING(YEARWEEK(timesheetStop, 1), 5, 2)))
		)';

		$greatestDate = 'GREATEST(
			COALESCE(plannedDate, "0000-00-00"),
			COALESCE(timesheetStop, "0000-00-00")
		)';

		$doneWeek = 'IF('.$greatestWeek.' = "0000-W00", '.Task::model()->format($newDone).', '.$greatestWeek.')';
		$doneDate = 'IF('.$greatestDate.' = "0000-00-00", IF('.$doneWeek.' = '.Task::model()->format(\Clock::week()).', '.Task::model()->format(\Clock::date()).', NULL), '.$greatestDate.')';

		return [$doneWeek, $doneDate];

	}

	/**
	 * Le rond Fait d'une intervention hors du planning, sur l'accueil : elle est faite
	 * cette semaine, avec le temps de la personne connectée quand la structure suit le temps.
	 */
	public static function updateDoneByMark(\structure\Structure $eStructure, Task $eTask): void {

		if($eStructure->hasTime()) {

			$eTimesheet = new Timesheet([
				'structure' => $eStructure,
				'user' => \user\ConnectionLib::getOnline(),
			]);

			$eTimesheet->validate('canWrite', 'acceptWrite');

			self::updateDoneWithTime($eTask, $eTimesheet, \Clock::week());

		} else {
			self::updateDoneCollection(new \Collection([$eTask]), \Clock::week());
		}

	}

	public static function updateDoneWithTime(Task $eTask, Timesheet $eTimesheet, string $week): void {

		$eTask->expects(['id', 'structure', 'series', 'cultivation', 'plant', 'variety', 'timeExpected']);

		Series::model()->beginTransaction();

		self::updateDoneCollection(new \Collection([$eTask]), $week);

		// `updateDoneCollection()` pose des `Sql` sur l'élément, que seule une relecture en base résout.
		Task::model()
			->select('doneWeek', 'doneDate')
			->get($eTask);

		$eTimesheet['date'] = self::getDoneTimeDate($eTask);

		if(
			$eTask['timeExpected'] > 0.0 and
			$eTimesheet['date'] !== NULL and
			TimesheetLib::getByUserAndTasks($eTimesheet['user'], new \Collection([$eTask]))->empty()
		) {
			TimesheetLib::writeByTasks(new \Collection([$eTask]), $eTimesheet, $eTask['timeExpected'], 'fair');
		}

		Series::model()->commit();

	}

	public static function getDoneTimeDate(Task $eTask): ?string {

		$eTask->expects(['doneWeek', 'doneDate']);

		$today = \Clock::date();

		if($eTask['doneDate'] !== NULL) {
			$date = $eTask['doneDate'];
		} else {
			$date = week_date_ends($eTask['doneWeek']);
		}

		if($date <= $today) {
			return $date;
		} else if($eTask['doneWeek'] === \Clock::week()) {
			return $today;
		} else {
			return NULL;
		}

	}

	public static function updatePlannedCollection(\Collection $c, ?string $newPlanned): void {

		Series::model()->beginTransaction();

		Task::model()
			->whereId('IN', $c)
			->update([
				'plannedWeek' => $newPlanned,
				'plannedDate' => NULL
			]);

		foreach($c as $e) {
			SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);
		}

		Series::model()->commit();

	}

	public static function updatePlannedDateCollection(\Collection $c, ?string $newPlanned): void {

		Series::model()->beginTransaction();

		Task::model()
			->whereId('IN', $c)
			->update([
				'plannedWeek' => toWeek($newPlanned),
				'plannedDate' => $newPlanned
			]);

		foreach($c as $e) {
			SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);
		}

		Series::model()->commit();

	}

	public static function update(Task $e, array $properties = []): void {

		self::prepareUpdate($e, $properties);

		Task::model()->beginTransaction();

		if(in_array('products', $properties)) {
			self::prepareProducts($e);
		}

		if(array_delete($properties, 'planned')) {
			$properties[] = 'plannedWeek';
			$properties[] = 'plannedDate';
		}

		if(array_delete($properties, 'done')) {
			$properties[] = 'doneWeek';
			$properties[] = 'doneDate';
		}

		parent::update($e, $properties);

		if(
			in_array('harvest', $properties) or
			in_array('status', $properties)
		) {
			self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);
		}

		if(in_array('harvest', $properties)) {

			$e->expects(['eHarvest']);

			HarvestLib::create($e['eHarvest']);

		}

		$newTimesheet = [];

		if(in_array('cultivation', $properties)) {

			$e->expects(['action' => ['fqn']]);

			// Mise à jour des quantités récoltées
			if($e['action']['fqn'] === ACTION_RECOLTE) {

				$e->expects(['oldCultivation']);

				HarvestLib::synchronizeTask($e);

				self::recalculateHarvest($e['structure'], $e['oldCultivation'], $e['plant']);
				self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);

			}

			$newTimesheet['cultivation'] = $e['cultivation'];

		}

		if(in_array('plant', $properties)) {
			$newTimesheet['plant'] = $e['plant'];
		}

		if($newTimesheet) {

			Timesheet::model()
				->whereTask($e)
				->update($newTimesheet);

		}

		if(array_intersect(['plannedWeek', 'doneWeek', 'action'], $properties)) {

			SeriesLib::recalculate($e['structure'], $e['series']);

		}

		Task::model()->commit();

	}

	private static function prepareUpdate(\Element $e, array &$properties) {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		if(in_array('cultivation', $properties)) {

			$e->expects(['oldCultivation']);

			// La production peut avoir changé
			if($e['cultivation']->notEmpty()) {

				if(
					$e['oldCultivation']->empty() or
					$e['cultivation']['id'] !== $e['oldCultivation']['id']
				) {

					$e['cultivation']->expects(['series']);

					// Requis pour recalculate()
					Series::model()
						->select('cycle')
						->get($e['cultivation']['series']);

					$e['series'] = $e['cultivation']['series'];
					$e['plant'] = $e['cultivation']['plant'];

					$properties[] = 'series';

				}

			} else if($e['cultivation']->empty()) {
				$e['plant'] = new \plant\Plant();
			}

			$properties[] = 'plant';

		}

		if(in_array('status', $properties)) {

			switch($e['status']) {

				case Task::TODO :
					$e['doneWeek'] = NULL;
					$e['doneDate'] = NULL;
					$properties[] = 'doneWeek';
					$properties[] = 'doneDate';
					break;

			}

		}

		$key = array_search('harvestMore', $properties, TRUE);

		if($key !== FALSE) {
			$e['harvest'] = new \Sql('IF(harvest IS NULL, '.Task::model()->format($e['harvestMore']).', harvest + '.Task::model()->format($e['harvestMore']).')');
			$properties[$key] = 'harvest';
		}

	}

	public static function deleteCollection(\Collection $c, bool $recalculate = TRUE): void {

		Task::model()->beginTransaction();

		Timesheet::model()
			->whereTask('IN', $c)
			->delete();

		Harvest::model()
			->whereTask('IN', $c)
			->delete();

		Comment::model()
			->whereTask('IN', $c)
			->delete();

		HistoryLib::deleteByTasks($c);

		Task::model()->delete($c);

		if($recalculate) {

			foreach($c as $e) {
				self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);
			}

			foreach($c as $e) {
				SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);
			}

		}

		Task::model()->commit();

	}

	public static function deleteRepeat(Task $e): void {

		$e->expects([
			'repeat' => ['current']
		]);

		Task::model()->beginTransaction();

		$week = $e->isDone() ? $e['doneWeek'] : $e['plannedWeek'];

		// Désactivation de la répétition
		$eRepeat = $e['repeat'];
		$eRepeat['stop'] = $week;

		RepeatLib::calculateCompleted($eRepeat);

		Repeat::model()
			->select('stop', 'completed')
			->update($eRepeat);

		// Suppression des tâches
		$cTask = Task::model()
			->select(Task::getSelection())
			->whereRepeat($eRepeat)
			->where(new \Sql('IF(status = "'.Task::DONE.'", doneWeek, plannedWeek)'), '>', $week)
			->getCollection();

		$cTask[] = $e;

		self::deleteCollection($cTask);

		Task::model()->commit();

	}

	public static function delete(Task $e): void {

		$e->expects(['structure', 'series', 'cultivation', 'plant', 'action']);

		Task::model()->beginTransaction();

		Timesheet::model()
			->whereTask($e)
			->delete();

		Harvest::model()
			->whereTask($e)
			->delete();

		Comment::model()
			->whereTask($e)
			->delete();

		HistoryLib::deleteByTasks(new \Collection([$e]));

		parent::delete($e);

		self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);

		SeriesLib::recalculate($e['structure'], $e['series'], $e['action']);

		Task::model()->commit();

	}

	/**
	 * Supprime celles des interventions données qu'une récolte vidée laisse sans rien (`Task::isHarvestShell()`),
	 * relues en base après l'écriture qui les a vidées. Chacune passe par `delete()`, dans sa propre transaction.
	 */
	public static function deleteHarvestShells(\Collection $cTask): void {

		if($cTask->empty()) {
			return;
		}

		$cTaskEmptied = Task::model()
			->select(self::getHarvestShellSelection())
			->whereId('IN', $cTask)
			->getCollection();

		foreach($cTaskEmptied as $eTask) {

			if($eTask->isHarvestShell()) {

				$eTask->validate('canDelete', 'acceptDelete');

				self::delete($eTask);

			}

		}

	}

	/**
	 * Ce que lit `Task::isHarvestShell()`, sur la sélection d'une intervention entière, que `delete()` recalcule.
	 * Une quantité compte par jour et par unité, arrondie comme la saisie la lit : un retrait qui rend tout laisse
	 * sur le jour des lignes dont la somme flottante n'est pas exactement nulle.
	 */
	protected static function getHarvestShellSelection(): array {

		$select = Task::getSelection();

		$select['hasHarvest'] = new \Sql(new HarvestModel()
			->where('s1.task', new \Sql('m1.id'))
			->group(['date', 'unit'])
			->having('ROUND(SUM(s1.quantity), 3) <> 0')
			->existsSql(), 'bool');

		$select['hasTimesheet'] = new \Sql(new TimesheetModel()
			->where('s1.task', new \Sql('m1.id'))
			->where('s1.time', '>', 0)
			->existsSql(), 'bool');

		$select['hasComment'] = new \Sql(new CommentModel()->where('s1.task', new \Sql('m1.id'))->existsSql(), 'bool');
		$select['hasPhoto'] = new \Sql(new \gallery\PhotoModel()->where('s1.task', new \Sql('m1.id'))->existsSql(), 'bool');
		$select['hasPlace'] = new \Sql(new PlaceModel()->where('s1.task', new \Sql('m1.id'))->existsSql(), 'bool');

		return $select;

	}

	public static function deleteHarvest(Task $e, Harvest $eHarvest): bool {

		$e->expects(['structure', 'cultivation', 'plant']);
		$eHarvest->expects(['id', 'quantity']);

		Task::model()->beginTransaction();

		HarvestLib::delete($eHarvest);

		// Sans ligne supprimée, rien n'est écrit : la transaction se referme sans `rollBack()`, qui
		// viderait aussi celles de l'appelant.
		if(HarvestLib::getAffected() === 0) {
			Task::model()->commit();
			return FALSE;
		}

		$e['harvest'] = new \Sql('harvest - '.Task::model()->format($eHarvest['quantity']));
		$e['updatedAt'] = new \Sql('NOW()');

		Task::model()
			->select('harvest', 'updatedAt')
			->update($e);

		self::recalculateHarvest($e['structure'], $e['cultivation'], $e['plant']);

		Task::model()->commit();

		return TRUE;

	}

	public static function recalculateTime(Task $eTask): void {

		$eTask->expects(['id', 'structure']);

		$eTimesheet = Timesheet::model()
			->select([
				'sum' => new \Sql('SUM(time)', 'float'),
				'start' => new \Sql('MIN(date)'),
				'stop' => new \Sql('MAX(date)')
			])
			->whereStructure($eTask['structure'])
			->whereTask($eTask)
			->get();

		if($eTimesheet->empty()) {

			$eTask['time'] = NULL;
			$eTask['timesheetStart'] = NULL;
			$eTask['timesheetStop'] = NULL;

		} else {

			$eTask['time'] = $eTimesheet['sum'];
			$eTask['timesheetStart'] = $eTimesheet['start'];
			$eTask['timesheetStop'] = $eTimesheet['stop'];

		}

		$eTask['updatedAt'] = new \Sql('NOW()');

		Task::model()
			->select('time', 'timesheetStart', 'timesheetStop', 'updatedAt')
			->update($eTask);

	}

	public static function recalculateHarvest(\structure\Structure $eStructure, Cultivation $eCultivation, \plant\Plant $ePlant): void {

		if($eCultivation->empty()) {
			return;
		}

		$eCultivation->expects(['mainUnit']);

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		// Total récolté
		$cSize = \plant\SizeLib::getForYield($eStructure, $ePlant);

		if($cSize->notEmpty()) {
			Task::model()->where('harvestSize IS NULL OR harvestSize IN ('.implode(', ', $cSize->getIds()).')');
		} else {
			Task::model()->whereHarvestSize(NULL);
		}

		$cTask = Task::model()
			->select('harvest', 'harvestUnit', 'harvestSize', 'doneWeek', 'doneDate')
			->whereAction($eAction)
			->whereCultivation($eCultivation)
			->whereStatus(Task::DONE)
			->getCollection();

		if($cTask->notEmpty()) {

			$harvested = 0;
			$harvestedByUnit = [];
			$months = [];
			$weeks = [];

			foreach($cTask as $eTask) {

				// Pas encore d'unité de récolte pour cette tâche
				if($eTask['harvestUnit'] !== NULL) {

					// Récolte totale
					if($eTask['harvestUnit'] === $eCultivation['mainUnit']) {
						$harvested += $eTask['harvest'];
					}

					// Récolte par unité
					if(empty($harvestedByUnit[$eTask['harvestUnit']])) {
						$harvestedByUnit[$eTask['harvestUnit']] = 0;
					}
					$harvestedByUnit[$eTask['harvestUnit']] += $eTask['harvest'];

				}

				// Gestion des mois de récolte
				$months[] = date('Y-m', strtotime($eTask['doneWeek'].' + 3 DAYS'));

				$weeks[] = $eTask['doneWeek'];

			}

			$months = array_unique($months);
			sort($months);

			$weeks = array_unique($weeks);
			sort($weeks);

		} else {
			$harvested = NULL;
			$harvestedByUnit = NULL;
			$months = NULL;
			$weeks = NULL;
		}

		$eCultivation['harvested'] = $harvested;
		$eCultivation['harvestedByUnit'] = $harvestedByUnit;
		$eCultivation['harvestMonths'] = $months;
		$eCultivation['harvestWeeks'] = $weeks;

		$eCultivation->calculateHarvestedNormalized();

		Cultivation::model()
			->select('harvested', 'harvestedNormalized', 'harvestedByUnit', 'harvestMonths', 'harvestWeeks')
			->update($eCultivation);

	}

	/**
	 * Les tâches des séries placées, pour une scène (`\map\SceneLib`, dont la règle de version vaut pour ce
	 * format), en tuples de `COLUMNS_SCENE`, et les actions qu'elles citent.
	 */
	public static function getForScene(\structure\Structure $eStructure, array $seriesIds): array {

		$cTask = Task::model()
			->select(['id', 'series', 'cultivation', 'action', 'plannedWeek', 'doneWeek', 'doneDate', 'status', 'harvest', 'harvestUnit'])
			->whereStructure($eStructure)
			->whereSeries('IN', $seriesIds)
			->sort(['id' => SORT_ASC])
			->getCollection();

		$tasks = [];
		$actionIds = [];

		foreach($cTask as $eTask) {

			$tasks[] = [
				$eTask['id'],
				$eTask['series']['id'],
				$eTask['cultivation']->empty() ? NULL : $eTask['cultivation']['id'],
				$eTask['action']['id'],
				$eTask['plannedWeek'],
				$eTask['doneWeek'],
				$eTask['doneDate'],
				($eTask['status'] === Task::DONE) ? 1 : 0,
				$eTask['harvest'],
				$eTask['harvestUnit'],
			];

			$actionIds[$eTask['action']['id']] = $eTask['action']['id'];

		}

		$cAction = \work\Action::model()
			->select(['id', 'name', 'fqn', 'color'])
			->whereStructure($eStructure)
			->whereId('IN', array_values($actionIds))
			->getCollection();

		$actions = [];

		foreach($cAction as $eAction) {
			$actions[] = [
				'id' => $eAction['id'],
				'name' => $eAction['name'],
				'fqn' => $eAction['fqn'],
				'color' => $eAction['color'],
			];
		}

		return ['tasks' => $tasks, 'actions' => $actions];

	}

	/**
	 * Les interventions prévues pour une personne sans compte le sont pour le compte qui la remplace
	 * (`structure\MemberLib::transferGhost()`), qui n'y figure qu'une fois s'il y était déjà.
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Écritures de masse, en deux temps parce qu'une colonne `json` ne se réécrit pas en une
		// expression : le compte s'ajoute là où il manque, puis la personne sans compte se retire partout.
		Task::model()
			->whereStructure($eStructure)
			->where('JSON_CONTAINS('.Task::model()->field('plannedUsers').', \''.$eUserFrom['id'].'\')')
			->where('JSON_CONTAINS('.Task::model()->field('plannedUsers').', \''.$eUserTo['id'].'\') = 0')
			->update([
				'plannedUsers' => new \Sql(Task::model()->pdo()->api->jsonAdd('plannedUsers', $eUserTo['id']))
			]);

		Task::model()
			->whereStructure($eStructure)
			->where('JSON_CONTAINS('.Task::model()->field('plannedUsers').', \''.$eUserFrom['id'].'\')')
			->update([
				'plannedUsers' => new \Sql(Task::model()->pdo()->api->jsonRemove('plannedUsers', $eUserFrom['id']))
			]);

	}

}
?>
