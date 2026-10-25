<?php
namespace series;

class TimesheetLib extends TimesheetCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['date', 'user', 'time'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['time'];
	}

	public static function getByUserAndTasks(\user\User $eUser, \Collection $cTask): \Collection {

		return Timesheet::model()
			->select([
				'id',
				'time',
				'date'
			])
			->whereTask('IN', $cTask)
			->whereUser($eUser)
			->sort(['date' => SORT_ASC])
			->getCollection(index: ['date', NULL]);

	}

	public static function fillTimesByDate(\structure\Structure $eStructure, \Collection $cUser, string $week): void {

		$dates = week_dates($week);
		$firstDate = first($dates);
		$lastDate = last($dates);

		$cTimesheet = Timesheet::model()
			->select([
				'user', 'date',
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->whereDate('BETWEEN', new \Sql(Timesheet::model()->format($firstDate).' AND '.Timesheet::model()->format($lastDate)))
			->group(['user', 'date'])
			->getCollection(NULL, NULL, ['user', 'date']);

		foreach($cUser as $eUser) {

			$eUser['timesheetTime'] = [];

			foreach($dates as $date) {
				$eUser['timesheetTime'][$date] = $cTimesheet[$eUser['id']][$date]['totalTime'] ?? NULL;
			}

		}

	}

	public static function fillTimesByTask(\Collection $cUser, Task $eTask): void {
		self::fillTimesByTasks($cUser, new \Collection([$eTask]));
	}

	public static function fillTimesByTasks(\Collection $cUser, \Collection $cTask): void {

		$cTimesheet = Timesheet::model()
			->select([
				'user',
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->whereTask('IN', $cTask)
			->sort(['totalTime' => SORT_DESC])
			->group('user')
			->getCollection(NULL, NULL, 'user');

		foreach($cUser as $eUser) {

			$eUser['time'] = $cTimesheet[$eUser['id']]['totalTime'] ?? NULL;

		}

	}

	public static function getTimesheetsByDaily(\structure\Structure $eStructure, string $week, \user\User $eUser = new \user\User()): \Collection {

		if($eUser->notEmpty()) {
			Timesheet::model()->whereUser($eUser);
		}

		$firstDate = week_date_starts($week);
		$lastDate = week_date_ends($week);

		return Timesheet::model()
			->select('task', 'date')
			->whereStructure($eStructure)
			->whereDate('BETWEEN', new \Sql(Timesheet::model()->format($firstDate).' AND '.Timesheet::model()->format($lastDate)))
			->getCollection(index: ['date', NULL]);

	}

	public static function getTasksByWeek(\structure\Structure $eStructure, string $week, ?\user\User $eUser = NULL): \Collection {

		if($eUser !== NULL) {
			Timesheet::model()->whereUser($eUser);
		}

		$firstDate = week_date_starts($week);
		$lastDate = week_date_ends($week);

		return Timesheet::model()
			->whereStructure($eStructure)
			->whereDate('BETWEEN', new \Sql(Timesheet::model()->format($firstDate).' AND '.Timesheet::model()->format($lastDate)))
			->group('task')
			->getColumn('task');

	}

	public static function getTimesByWeek(\structure\Structure $eStructure, \user\User $eUser, string $week): array {

		$firstDate = week_date_starts($week);
		$lastDate = week_date_ends($week);

		return Timesheet::model()
			->select([
				'date',
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->whereUser($eUser)
			->where('date BETWEEN '.Timesheet::model()->format($firstDate).' AND '.Timesheet::model()->format($lastDate))
			->group('date')
			->getCollection()
			->makeArray(function(Timesheet $eTimesheet, &$key) {
				$key = $eTimesheet['date'];
				return round($eTimesheet['totalTime'], 2);
			});

	}

	public static function delegateByTask(\user\User $eUser = new \user\User(), ?string $timesheetWeek = NULL) {

		if($timesheetWeek !== NULL) {

			$firstDate = date('Y-m-d', strtotime($timesheetWeek));
			$lastDate = date('Y-m-d', strtotime($timesheetWeek.' + 6 DAY'));

			Timesheet::model()->whereDate('BETWEEN', new \Sql(Timesheet::model()->format($firstDate).' AND '.Timesheet::model()->format($lastDate)));

		}

		if($eUser->notEmpty()) {
			Timesheet::model()->whereUser($eUser);
		}

		return Timesheet::model()
			->select([
				'user',
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->group(['task', 'user'])
			->sort(['user' =>  SORT_ASC])
			->delegateCollection('task', 'user', function($c) {
				return $c->makeArray(function(Timesheet $e, &$key) {

					$key = $e['user']['id'];
					return $e['totalTime'];

				});
			});

	}

	public static function create(Timesheet $e): void {

		$e->expects(['time']);

		try {

			parent::create($e);

			HistoryLib::createForTimesheet($e, $e['time']);

			if($e['time'] > 0) {
				TaskLib::recalculateTime($e['task']);
			}

		} catch(\DuplicateException $e) {

			Timesheet::fail('duplicate');

		}


	}


	public static function writeByTasks(\Collection $cTask, Timesheet $eTimesheetBase, float $timeAdd, string $distribution): void {

		if($distribution === 'harvest') {
			\series\TaskLib::fillHarvestDates($cTask);
			\series\TaskLib::fillHarvestByDate($cTask, $eTimesheetBase['date']);
		}

		\series\TaskLib::fillDistribution($cTask);

		// On récupére les parts de chaque variété
		if(TaskLib::checkDistribution($cTask, $distribution) === FALSE) {
			return;
		}

		$cTimesheet = new \Collection();

		$precision = 6;

		if(TaskLib::distribute(
			$cTask,
			$distribution,
			$timeAdd,
			$precision,
			function(Task $eTask) use($cTimesheet, $eTimesheetBase, $precision) {

				$eTimesheet = (clone $eTimesheetBase)->merge([
					'task' => $eTask
				]);

				$cTimesheet[] = $eTimesheet->merge([
					'time' => $eTask['distributed']
				]);

			}
		) === FALSE) {
			return;
		}

		Timesheet::model()->beginTransaction();

		foreach($cTimesheet as $eTimesheetNew) {

			$eTimesheetNew['time'] = round($eTimesheetNew['time'], $precision);

			// L'écart que ce geste écrit sur la ligne, avant qu'il s'ajoute au temps qu'elle porte déjà.
			$time = $eTimesheetNew['time'];

			$eTimesheetNew->merge([
				'series' => $eTimesheetNew['task']['series'],
				'cultivation' => $eTimesheetNew['task']['cultivation'],
				'plant' => $eTimesheetNew['task']['plant'],
				'week' => toWeek($eTimesheetNew['date'])
			]);

			$eTimesheetCurrent = Timesheet::model()
				->select('id', 'time')
				->whereTask($eTimesheetNew['task'])
				->whereUser($eTimesheetNew['user'])
				->whereDate($eTimesheetNew['date'])
				->get();

			if($eTimesheetCurrent->notEmpty()) {

				if($eTimesheetNew['time'] === 0.0) {
					continue;
				}

				$eTimesheetNew['id'] = $eTimesheetCurrent['id'];
				$eTimesheetNew['time'] += $eTimesheetCurrent['time'];

				// Workaround sur les arrondis
				if($eTimesheetNew['time'] < 0.0001 and $eTimesheetNew['time'] > -0.0001) {
					$eTimesheetNew['time'] = 0;
				}

				if($eTimesheetNew['time'] >= 0) {

					Timesheet::model()
						->select('time')
						->update($eTimesheetNew);

					HistoryLib::createForTimesheet($eTimesheetNew, $time);

				} else {

					Timesheet::model()->rollBack();
					Timesheet::fail('time.negative');

					return;

				}

			} else {

				if($eTimesheetNew['time'] < 0) {

					Timesheet::model()->rollBack();
					Timesheet::fail('time.negative');

					return;

				}

				Timesheet::model()->insert($eTimesheetNew);

				HistoryLib::createForTimesheet($eTimesheetNew, $time);

			}

			TaskLib::recalculateTime($eTimesheetNew['task']);

		}

		Timesheet::model()->commit();

	}

	public static function getTimeByDate(Task $eTask, \user\User $eUser, string $date): float {

		return Timesheet::model()
			->select(['total' => new \Sql('IFNULL(SUM(time), 0)', 'float')])
			->whereTask($eTask)
			->whereUser($eUser)
			->whereDate($date)
			->get()['total'];

	}

	/**
	 * Le temps d'une personne sur une intervention, par jour, sur les jours donnés : 0 un jour sans temps.
	 */
	public static function getTimeByTaskUser(Task $eTask, \user\User $eUser, array $dates): array {

		$times = array_fill_keys($dates, 0.0);

		$cTimesheet = Timesheet::model()
			->select([
				'date',
				'total' => new \Sql('SUM(time)', 'float')
			])
			->whereTask($eTask)
			->whereUser($eUser)
			->whereDate('IN', $dates)
			->group(['date'])
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['date']] = $eTimesheet['total'];
		}

		return $times;

	}

	public static function getTimeByUserDate(\structure\Structure $eStructure, \user\User $eUser, string $date): float {

		return Timesheet::model()
			->select(['total' => new \Sql('IFNULL(SUM(time), 0)', 'float')])
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereDate($date)
			->get()['total'];

	}

	public static function getDaysByTask(Task $eTask): \Collection {

		return Timesheet::model()
			->select([
				'user' => \user\User::getSelection(),
				'date',
				'total' => new \Sql('SUM(time)', 'float')
			])
			->whereTask($eTask)
			->group(['user', 'date'])
			->sort(['date' => SORT_DESC])
			->getCollection(NULL, NULL, ['user', 'date']);

	}

	/**
	 * Le temps d'une personne sur un jour, tel que le saisissent la fiche, le Jour et la grille de la Semaine.
	 * La validation reste à l'appelant : une route borne la date à sa semaine entre la construction et
	 * l'écriture (`date.week`), que `writeTimeForDay()` fait ensuite pour toutes.
	 */
	public static function prepareDay(\structure\Structure $eStructure, \user\User $eUser, array $input): Timesheet {

		$eTimesheet = new Timesheet([
			'structure' => $eStructure,
			'user' => $eUser
		]);

		$eTimesheet->build(['time', 'date'], $input, new \PropertyList('day'));

		return $eTimesheet;

	}

	/**
	 * Le temps `$eTimesheet` remplace celui que la personne a passé sur l'intervention ce jour-là : la journée
	 * qui en résulte dépasse-t-elle 24 heures ? Seul un écart positif peut la faire dépasser. Une baisse n'est
	 * jamais refusée, même sur une journée déjà au-delà, écrite avant la borne : elle l'en rapproche.
	 */
	public static function exceedsDay(Task $eTask, Timesheet $eTimesheet): bool {

		$time = self::getDayChange($eTask, $eTimesheet);

		return (
			$time > 0.0 and
			round(self::getTimeByUserDate($eTimesheet['structure'], $eTimesheet['user'], $eTimesheet['date']) + $time, 2) > 24.0
		);

	}

	/**
	 * L'écart entre le temps `$eTimesheet` et celui que la personne a déjà sur l'intervention ce jour-là.
	 */
	protected static function getDayChange(Task $eTask, Timesheet $eTimesheet): float {
		return round($eTimesheet['time'] - self::getTimeByDate($eTask, $eTimesheet['user'], $eTimesheet['date']), 2);
	}

	public static function writeTimeForDay(Task $eTask, Timesheet $eTimesheet): void {

		$eTimesheet->expects(['structure', 'user', 'date', 'time']);

		if($eTimesheet['time'] > 0.0) {

			if(self::exceedsDay($eTask, $eTimesheet)) {
				Timesheet::fail('time.tooLong');
				return;
			}

			$time = self::getDayChange($eTask, $eTimesheet);

			// Sans transaction autour : sur un refus, `writeByTasks()` annule toute la pile de transactions.
			if($time !== 0.0) {
				self::writeByTasks(new \Collection([$eTask]), $eTimesheet, $time, 'fair');
			}

		} else {

			$cTimesheet = Timesheet::model()
				->select('id', 'task', 'structure')
				->whereTask($eTask)
				->whereUser($eTimesheet['user'])
				->whereDate($eTimesheet['date'])
				->getCollection();

			Timesheet::model()->beginTransaction();

			self::deleteCollection($cTimesheet);

			Timesheet::model()->commit();

		}

	}

	public static function update(Timesheet $e, array $properties): void {

		$e->expects(['id', 'task', 'structure']);

		Timesheet::model()->beginTransaction();

		$eTimesheetOld = self::getHistoryBase($e);

		parent::update($e, $properties);

		if(in_array('time', $properties)) {

			if(self::getAffected() > 0) {
				HistoryLib::createForTimesheet($eTimesheetOld, round($e['time'] - $eTimesheetOld['time'], 6));
			}

			$e['task']['structure'] = $e['structure'];

			TaskLib::recalculateTime($e['task']);

		}

		Timesheet::model()->commit();

	}

	public static function delete(Timesheet $e): void {

		$e->expects(['id', 'task', 'structure']);

		Timesheet::model()->beginTransaction();

		$eTimesheetOld = self::getHistoryBase($e);

		parent::delete($e);

		if(self::getAffected() > 0) {
			HistoryLib::createForTimesheet($eTimesheetOld, -$eTimesheetOld['time']);
		}

		$e['task']['structure'] = $e['structure'];

		TaskLib::recalculateTime($e['task']);

		Timesheet::model()->commit();

	}

	/**
	 * La ligne avant le geste, lue en base : dans `update()`, l'élément porte déjà la durée neuve, et
	 * l'écart d'historique se compte depuis l'ancienne.
	 */
	private static function getHistoryBase(Timesheet $e): Timesheet {

		$eTimesheet = Timesheet::model()
			->select('time', 'user', 'date')
			->whereId($e['id'])
			->get();

		return $eTimesheet->merge([
			'structure' => $e['structure'],
			'task' => $e['task']
		]);

	}

	public static function deleteCollection(\Collection $c): void {

		$c->expects(['task', 'structure']);

		// Suppression un à un car beaucoup traitements personnalisés à chaque fois
		foreach($c as $e) {
			self::delete($e);
		}

	}

	/**
	 * Le temps passé sur les interventions par une personne sans compte passe au compte qui la remplace (`structure\MemberLib::transferGhost()`).
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Écriture de masse : toutes les lignes de la personne changent de titulaire, sans rien d'autre à recalculer.
		Timesheet::model()
			->whereStructure($eStructure)
			->whereUser($eUserFrom)
			->update([
				'user' => $eUserTo
			]);

	}

}
?>
