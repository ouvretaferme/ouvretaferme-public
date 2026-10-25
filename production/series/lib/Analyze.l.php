<?php
namespace series;

class AnalyzeLib {

	public static function getStructureMonths(\structure\Structure $eStructure, int $year): \Collection {

		$cWorkingTimeMonth = \hr\WorkingTime::model()
			->select([
				'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int'),
				'timeTotal' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), $year)
			->group(new \Sql('month'))
			->sort(new \Sql('month DESC'))
			->getCollection(NULL, NULL, ['month']);

		return $cWorkingTimeMonth;

	}

	public static function getStructureWeeks(\structure\Structure $eStructure, int $year): \Collection {

		$cWorkingTimeWeek = \hr\WorkingTime::model()
			->select([
				'week' => new \Sql('WEEK(date, 1)', 'int'),
				'timeTotal' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), $year)
			->group(new \Sql('week'))
			->sort(new \Sql('week DESC'))
			->getCollection(NULL, NULL, ['week']);

		return $cWorkingTimeWeek;

	}

	public static function getGlobalWorkingTime(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): ?float {

		$workingTime = \hr\WorkingTime::model()
			->whereStructure($eStructure)
			->where('EXTRACT(YEAR from date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->getValue(new \Sql('SUM(time)', 'float'));

		if($workingTime !== NULL) {
			return round($workingTime, 2);
		} else {
			return NULL;
		}

	}

	public static function getMonthlyWorkingTime(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		$eUserOnline = \user\ConnectionLib::getOnline();

		return \hr\WorkingTime::model()
			->select([
				'user' => ['firstName', 'lastName', 'vignette'],
				'month' => new \Sql('SUBSTRING(date, 1, 7)'),
				'timeTotal' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->where('EXTRACT(YEAR from date) = '.\hr\WorkingTime::model()->format($year))
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['user', 'month'])
			->sort(new \Sql('IF(user = '.$eUserOnline['id'].', 0, user) ASC'))
			->getCollection(NULL, NULL, ['user', 'month']);

	}

	public static function getWeeklyWorkingTime(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): array {

		$start = \Clock::now()->setISODate($year, 1)->format('Y-m-d');
		$stop = \Clock::now()->setISODate($year, 52, 7)->format('Y-m-d');
		$currentWeek = min(52, (int)\Clock::format('W'));

		$ccPresence =\hr\PresenceLib::getBetween($eStructure, $start, $stop);

		$distribution = [];

		foreach($ccPresence as $user => $cPresence) {

			$dates = [];
			$weekStart = 1;
			$weekStop = $currentWeek;

			foreach($cPresence as $ePresence) {

				if($ePresence['to'] === NULL) {

					$dates[] = fn() => $this->whereDate('>=', $ePresence['from']);

					if(date_year($ePresence['from']) === $year) {
						$weekStart = week_number(toWeek($ePresence['from']));
					}

				} else {

					if(date_year($ePresence['from']) === $year) {
						$weekStart = week_number(toWeek($ePresence['from']));
					}

					if(date_year($ePresence['to']) === $year) {
						$weekStop = min($weekStop, week_number(toWeek($ePresence['to'])));
					}

				}

			}

			$distribution[$user] = \hr\WorkingTime::model()
				->select([
					'user',
					'week' => new \Sql('WEEK(date, 1)', 'int'),
					'timeTotal' => new \Sql('IF(SUM(time) < 11, 10, IF(SUM(time) < 40, 40, IF(SUM(time) < 50, 50, 51)))', 'int')
				])
				->whereStructure($eStructure)
				->whereUser($user)
				->or(...$dates)
				->where('EXTRACT(YEAR from date) = '.\hr\WorkingTime::model()->format($year))
				->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
				->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
				->group(['week'])
				->getCollection()
				->reduce(function($eWorkingTime, $list) {
					$list[$eWorkingTime['timeTotal']] = ($list[$eWorkingTime['timeTotal']] ?? 0) + 1;
					return $list;
				}, []);

			$distribution[$user][0] = max(0, ($weekStop - $weekStart) - array_sum($distribution[$user]));

		}

		return $distribution;

	}

	public static function getActionTimesheetByUser(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		return Timesheet::model()
			->select([
				'user',
				'timeTotal' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(Task::model()
				->select([
					'action' => ['name', 'color'],
					'category' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.time > 0')
			->where('m1.structure', $eStructure)
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['user', 'action', 'category'])
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection(index: ['user', NULL]);

	}

	public static function getCategoryTimesheetByUser(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		return Timesheet::model()
			->select([
				'user',
				'timeTotal' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(Task::model()
				->select([
					'category' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.time > 0')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['user', 'category'])
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection(index: ['user', NULL]);

	}

	public static function getCategoryTimesheet(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		return Timesheet::model()
			->select([
				'timeTotal' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(Task::model()
				->select([
					'category' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.time > 0')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['category'])
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection();

	}

	public static function getCategoryMonthly(\structure\Structure $eStructure, int $year): \Collection {

		return Timesheet::model()
			->select([
				'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int'),
				'timeTotal' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(Task::model()
				->select([
					'category' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.time > 0')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->group([new \Sql('m1_month'), 'category'])
			->getCollection(index: ['category', 'month']);

	}

	public static function getPlantCultivations(\plant\Plant $ePlant, int $year): \Collection {

		// On récupère les séries potentiellement concernées par l'année
		$cCultivation = Cultivation::model()
			->select(['id', 'series'])
			->wherePlant($ePlant)
			->whereSeason('IN', [$year - 1, $year, $year + 1])
			->getCollection();

		return $cCultivation;

	}

	public static function getPlantMonths(\plant\Plant $ePlant, \Collection $cCultivation, int $year): \Collection {
		return self::getPlantPeriod('month', $ePlant, $cCultivation, $year);
	}

	public static function getPlantWeeks(\plant\Plant $ePlant, \Collection $cCultivation, int $year): \Collection {
		return self::getPlantPeriod('week', $ePlant, $cCultivation, $year);
	}

	public static function getPlantPeriod(string $period, \plant\Plant $ePlant, \Collection $cCultivation, int $year): \Collection {

		if($cCultivation->empty()) {
			return new \Collection();
		}

		$cSeries = $cCultivation->getColumnCollection('series');

		return Timesheet::model()
			->select([
				$period => match($period) {
					'week' => new \Sql('WEEK(date, 1)', 'int'),
					'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int')
				},
				'timeTotal' => new \Sql('SUM(IF(m1.series IS NOT NULL AND m1.cultivation IS NULL, m1.time / m2.plants, m1.time))')
			])
			->join(Series::model(), 'm2.id = m1.series', 'LEFT')
			->or(
				fn() => $this->where('m1.series', 'IN', $cSeries),
				fn() => $this->where('m1.plant', $ePlant),
			)
			->or(
				fn() => $this->where('m1.cultivation', NULL),
				fn() => $this->where('m1.cultivation', 'IN', $cCultivation),
			)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), $year)
			->group(new \Sql('m1_'.$period))
			->getCollection(NULL, NULL, $period);

	}

	public static function getPlantsTimesheet(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		return Timesheet::model()
			->select([
				'plant' => ['vignette', 'fqn', 'name'],
				'timeTotal' => function($e) {
					return round($e['timePlant'] + $e['timeShared'], 2);
				},
				'timePlant' => new \Sql('SUM(time)', 'float'),
				'timeNoSeries' => new \Sql('SUM(IF(series IS NULL, time, 0))', 'float'),
				'timeShared' => new TimesheetModel()
					->select([
						'propertySource' => new \Sql('m3.plant')
					])
					->join(Task::model(), 'm2.id = m1.task')
					->join(Cultivation::model(), 'm3.series = m2.series')
					->join(Series::model(), 'm4.id = m2.series')
					->where('m1.structure', $eStructure)
					->where('m2.cultivation IS NULL')
					->where('EXTRACT(YEAR FROM date) = '.$year)
					->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
					->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
					->group(new \Sql('m3.plant'))
					->delegateProperty('propertySource', new \Sql('SUM(m1.time / m4.plants)', 'float'), fn($value) => $value ?? 0, 'plant')
			])
			->where('structure', $eStructure)
			->where('time > 0')
			->where('plant IS NOT NULL')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['plant'])
			->sort(new \Sql('timePlant DESC'))
			->getCollection();

	}

	public static function getMonthlyPlantsTimesheet(\structure\Structure $eStructure, int $year): \Collection {

		return Timesheet::model()
			->select([
				'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int'),
				'plant' => ['vignette', 'fqn', 'name'],
				'propertyDelegate' => new \Sql('CONCAT(plant, EXTRACT(MONTH FROM date))', 'int'),
				'timeTotal' => function($e) {
					return round($e['timePlant'] + $e['timeShared'] + $e['timeShared'], 2);
				},
				'timePlant' => new \Sql('SUM(time)', 'float'),
				'timeNoSeries' => new \Sql('SUM(IF(series IS NULL, time, 0))', 'float'),
				'timeShared' => new TimesheetModel()
					->select([
						'propertySource' => new \Sql('CONCAT(m3.plant, "-", EXTRACT(MONTH FROM date))')
					])
					->join(Task::model(), 'm2.id = m1.task')
					->join(Cultivation::model(), 'm3.series = m2.series')
					->join(Series::model(), 'm4.id = m2.series')
					->where('m1.structure', $eStructure)
					->where('m2.cultivation IS NULL')
					->where('EXTRACT(YEAR FROM date) = '.$year)
					->group([new \Sql('CONCAT(m3.plant, "-", EXTRACT(MONTH FROM date))')])
					->delegateProperty('propertySource', new \Sql('SUM(m1.time / m4.plants)', 'float'), fn($value) => $value ?? 0, 'propertyDelegate')
			])
			->where('structure', $eStructure)
			->where('plant IS NOT NULL')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->group([new \Sql('CONCAT(plant, EXTRACT(MONTH FROM date)), EXTRACT(MONTH FROM date), plant')])
			->getCollection(index: ['plant', 'month']);

	}

	public static function getPlantTimesheet(\plant\Plant $ePlant, int $year): \Collection {

		// On récupère les séries potentiellement concernées par l'année
		$cCultivation = Cultivation::model()
			->select(['id', 'series'])
			->wherePlant($ePlant)
			->whereSeason('BETWEEN', new \Sql(($year - 3).' AND '.($year + 3)))
			->getCollection();

		if($cCultivation->empty()) {
			return new \Collection();
		}

		$cSeries = $cCultivation->getColumnCollection('series');

		return Timesheet::model()
			->select([
				'year' => new \Sql('EXTRACT(YEAR FROM date)', 'int'),
				'timeTotal' => new \Sql('SUM(IF(m1.series IS NOT NULL AND m1.cultivation IS NULL, m1.time / m2.plants, m1.time))')
			])
			->join(Series::model(), 'm2.id = m1.series', 'LEFT')
			->or(
				fn() => $this->where('m1.series', 'IN', $cSeries),
				fn() => $this->where('m1.plant', $ePlant),
			)
			->or(
				fn() => $this->where('m1.cultivation', NULL),
				fn() => $this->where('m1.cultivation', 'IN', $cCultivation),
			)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), 'BETWEEN', new \Sql(($year - 2).' AND '.($year + 2)))
			->group(new \Sql('m1_year'))
			->sort(new \Sql('m1_year DESC'))
			->getCollection();

	}

	public static function getActionTimesheetByPlant(\plant\Plant $ePlant, int $year): array {

		// On récupère les séries potentiellement concernées par l'année
		$cCultivation = Cultivation::model()
			->select(['id', 'series'])
			->wherePlant($ePlant)
			->whereSeason('IN', [$year - 1, $year, $year + 1])
			->getCollection();

		if($cCultivation->empty()) {
			return [new \Collection(), new \Collection()];
		}

		$cSeries = $cCultivation->getColumnCollection('series');

		$ccTimesheet = Timesheet::model()
			->select([
				'timeTotal' => new \Sql('SUM(IF(m1.series IS NOT NULL AND m1.cultivation IS NULL, m1.time / m2.plants, m1.time))'),
				'user' => ['firstName', 'lastName', 'vignette']
			])
			->join(Series::model(), 'm2.id = m1.series', 'LEFT')
			->join(Task::model()
				->select([
					'action' => ['name', 'color'],
				]), 'm3.id = m1.task')
			->or(
				fn() => $this->where('m1.series', 'IN', $cSeries),
				fn() => $this->where('m1.plant', $ePlant),
			)
			->or(
				fn() => $this->where('m1.cultivation', NULL),
				fn() => $this->where('m1.cultivation', 'IN', $cCultivation),
			)
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->group(['m3_action', 'm1_user'])
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection(index: ['action', 'user']);

		$cTimesheetAction = new \Collection();
		$cTimesheetUser = new \Collection();

		foreach($ccTimesheet as $action => $cTimesheet) {

			$cTimesheetAction[$action] = new Timesheet([
				'action' => $cTimesheet->first()['action'],
				'timeTotal' => $cTimesheet->sum('timeTotal'),
				'cTimesheetUser' => $cTimesheet
			]);

			foreach($cTimesheet as $user => $eTimesheet) {

				if($cTimesheetUser->offsetExists($user) === FALSE) {
					$cTimesheetUser[$user] = new Timesheet([
						'user' => $eTimesheet['user'],
						'timeTotal' => 0,
					]);
				}

				$cTimesheetUser[$user]['timeTotal'] += $eTimesheet['timeTotal'];

			}

		}

		$cTimesheetUser->sort(new \Sql('timeTotal DESC'));

		return [$cTimesheetAction, $cTimesheetUser];

	}

	public static function getSeriesTimesheet(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		return Timesheet::model()
			->select([
				'series' => ['name', 'mode'],
				'timeTotal' => new \Sql('SUM(time)', 'float'),
			])
			->whereStructure($eStructure)
			->where('time > 0')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['series'])
			->sort(new \Sql('timeTotal DESC'))
			->getCollection(index: 'series');

	}

	public static function getSeriesMonthly(\structure\Structure $eStructure, int $year): \Collection {

		return Timesheet::model()
			->select([
				'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int'),
				'hasSeries' => new \Sql('series IS NOT NULL', 'bool'),
				'timeTotal' => new \Sql('SUM(time)', 'float'),
			])
			->whereStructure($eStructure)
			->where('time > 0')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->group([new \Sql('month'), 'hasSeries'])
			->sort('hasSeries')
			->getCollection(index: ['hasSeries', 'month']);

	}

	public static function getActionTimesheet(\structure\Structure $eStructure, int $year, ?int $month, ?string $week): \Collection {

		return Timesheet::model()
			->select([
				'timeTotal' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(Task::model()
				->select([
					'action' => ['name', 'color'],
					'category' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.time > 0')
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where(fn() => 'EXTRACT(MONTH FROM date) = '.$month, if: $month)
			->where(fn() => 'WEEK(date, 1) = '.week_number($week), if: is_week($week))
			->group(['action', 'category'])
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection();


	}

	public static function getActionMonthly(\structure\Structure $eStructure, int $year): \Collection {

		return Timesheet::model()
			->select([
				'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int'),
				'timeTotal' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(Task::model()
				->select([
					'action',
					'category',
				]), 'm2.id = m1.task')
			->where('m1.time > 0')
			->where('m1.structure', $eStructure)
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->group([new \Sql('m1_month'), 'action', 'category'])
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection(index: ['action', 'category', 'month']);


	}

	public static function getYears(\structure\Structure $eStructure): array {

		// MIN(date) se lit dans l'index (structure, date) en une lecture, une fonction sur la colonne fait parcourir toute la structure.
		$firstDate = Timesheet::model()
			->whereStructure($eStructure)
			->getValue(new \Sql('MIN(date)'));

		$firstYear = ($firstDate === NULL) ? \Clock::year() : (int)substr($firstDate, 0, 4);

		return range(\Clock::year(), min($firstYear, \Clock::year()));

	}

}
?>
