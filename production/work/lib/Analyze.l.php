<?php
namespace work;

class AnalyzeLib {

	public static function getActionMonths(Action $eAction, Category $eCategory, int $year): array {
		return self::getActionPeriod('month', $eAction, $eCategory, $year);
	}

	public static function getActionWeeks(Action $eAction, Category $eCategory, int $year): array {
		return self::getActionPeriod('week', $eAction, $eCategory, $year);
	}

	protected static function getActionPeriod(string $period, Action $eAction, Category $eCategory, int $year): array {

		$ccTimesheet = \series\Timesheet::model()
			->select([
				$period => match($period) {
					'week' => new \Sql('WEEK(date, 1)', 'int'),
					'month' => new \Sql('EXTRACT(MONTH FROM date)', 'int')
				},
				'timeTotal' => new \Sql('SUM(m1.time)'),
				'user' => ['firstName', 'lastName', 'vignette']
			])
			->join(\series\Task::model(), 'm2.id = m1.task')
			->where('m2.structure', $eCategory['structure'])
			->where('m2.action', $eAction, if: $eAction->notEmpty())
			->where('m2.category', $eCategory)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), $year)
			->group(new \Sql('m1_'.$period.', m1_user'))
			->sort(new \Sql('m1_timeTotal DESC'))
			->getCollection(NULL, NULL, [$period, 'user']);

		$cTimesheetPeriod = new \Collection();
		$cTimesheetUser = new \Collection();

		foreach($ccTimesheet as $value => $cTimesheet) {

			$cTimesheetPeriod[$value] = new \series\Timesheet([
				$period => $value,
				'timeTotal' => $cTimesheet->sum('timeTotal'),
				'cTimesheetUser' => $cTimesheet
			]);

			foreach($cTimesheet as $user => $eTimesheet) {

				if($cTimesheetUser->offsetExists($user) === FALSE) {
					$cTimesheetUser[$user] = new \series\Timesheet([
						'user' => $eTimesheet['user'],
						'timeTotal' => 0,
					]);
				}

				$cTimesheetUser[$user]['timeTotal'] += $eTimesheet['timeTotal'];

			}

		}

		$cTimesheetUser->sort(new \Sql('timeTotal DESC'));

		return [$cTimesheetPeriod, $cTimesheetUser];

	}

	public static function getActionTimesheet(Action $eAction, Category $eCategory, int $year): \Collection {

		return \series\Timesheet::model()
			->select([
				'year' => new \Sql('EXTRACT(YEAR FROM date)', 'int'),
				'timeTotal' => new \Sql('SUM(m1.time)')
			])
			->join(\series\Task::model(), 'm2.id = m1.task')
			->where('m2.structure', $eCategory['structure'])
			->where('m2.action', $eAction, if: $eAction->notEmpty())
			->where('m2.category', $eCategory)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), 'BETWEEN', new \Sql(($year - 2).' AND '.($year + 2)))
			->group(new \Sql('m1_year'))
			->sort(new \Sql('m1_year DESC'))
			->getCollection();

	}

	public static function getActionMethods(Action $eAction, Category $eCategory, int $year): \Collection {

		$cTimesheet = \series\Timesheet::model()
			->select([
				'methods' => new \Sql('m2.methods'),
				'timeTotal' => new \Sql('SUM(m1.time)')
			])
			->join(\series\Task::model(), 'm2.id = m1.task')
			->where('m2.structure', $eCategory['structure'])
			->where('m2.action', $eAction, if: $eAction->notEmpty())
			->where('m2.category', $eCategory)
			->where(new \Sql('EXTRACT(YEAR FROM date)'), $year)
			->group(new \Sql('m2.methods'))
			->getCollection();

		$times = [];
		$timeWithoutMethod = 0;

		foreach($cTimesheet as $eTimesheet) {

			$methods = json_decode($eTimesheet['methods'], TRUE) ?: [];

			if($methods === []) {
				$timeWithoutMethod += $eTimesheet['timeTotal'];
				continue;
			}

			foreach($methods as $method) {
				$times[$method] = ($times[$method] ?? 0) + $eTimesheet['timeTotal'];
			}

		}

		if($times === []) {
			return new \Collection();
		}

		$cMethod = Method::model()
			->select(['id', 'name'])
			->whereStructure($eCategory['structure'])
			->whereId('IN', array_keys($times))
			->getCollection(index: 'id');

		foreach($cMethod as $eMethod) {
			$eMethod['timeTotal'] = $times[$eMethod['id']];
		}

		$cMethod->sort(['timeTotal' => SORT_DESC]);

		if($timeWithoutMethod > 0) {
			$cMethod[] = new Method([
				'timeTotal' => $timeWithoutMethod,
			]);
		}

		return $cMethod;

	}

}
?>
