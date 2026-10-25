<?php
namespace planning;

/**
 * Le temps d'une semaine : la journée de travail de chaque jour et ce qui en est passé sur les
 * interventions, pour l'équipe, que la fiche de temps de l'équipe lit (`timeDay.p.php`). La fiche d'une
 * personne lit ses jours elle-même (`TimeDayLib::get()`), par `getDay()` et `getActionsByDate()`.
 *
 * Les personnes sont celles de la fiche de temps (`TimeDayLib::getUsers()`), avec la même règle de droit
 * (charte, point 5) : un planning limité ne voit que sa propre journée, et l'équipe ne se montre qu'à
 * partir de deux personnes. Les deux lectures de la semaine sont celles du planning actuel, une requête chacune
 * pour toute l'équipe et toute la semaine (`\hr\WorkingTimeLib::fillByWeekFromUsers()`,
 * `\series\TimesheetLib::fillTimesByDate()`, écart a du plan). Le détail du jour (`fillTasksSpent()`) et la
 * barre de la fiche (`getActionsByDate()`) lisent directement les fiches de temps et les interventions : ils ne
 * portent que sur un jour ou une personne, et une lecture de la semaine pour toute l'équipe y paierait tout ce
 * qu'ils n'affichent pas.
 */
class TimeWeekLib {

	/**
	 * La personne de la fiche de temps (`user`) et l'origine d'une journée saisie (`origin`) qui désignent
	 * l'équipe plutôt qu'une personne.
	 */
	const TEAM = 'team';

	/**
	 * La semaine choisie à la place d'un jour sur la fiche de l'équipe (`period`, R-Retours-1210, 309).
	 */
	const WEEK = 'week';

	/**
	 * L'échelle de toutes les barres de la fiche, en heures : la même position veut dire la
	 * même durée partout, d'un jour, d'une semaine et d'une personne à l'autre.
	 */
	const SCALE = 12.0;

	/**
	 * Les bornes de la réglette d'une journée saisie en horaires, en heures de l'horloge : la plage d'une journée
	 * de travail tient de 6 h à 20 h (planche rh-heures, Q4 A).
	 */
	const CLOCK_START = 6;
	const CLOCK_STOP = 20;

	/**
	 * Le temps de l'équipe sur une semaine :
	 * - `cUserTime` : les personnes, chacune avec ses jours (`timeDays`) et leur somme (`timeTotal`, au format de
	 *   `getTotal()`), que la fiche de l'équipe montre quand la semaine est choisie (R-Retours-1210, 308 et 309) ;
	 * - `days` : par date, la somme de l'équipe, au format de `getDay()`, et ses absents (`cUserAbsent`, `\hr\Absence`,
	 *   posées par `TimeDayLib::getUsers()`), que la rangée de la semaine montre sous la pastille de chaque jour
	 *   (R-Retours-1210, FS), et son `overflow`, vrai quand une personne au moins dépasse sa journée ce jour-là, comme
	 *   son `unfilled` quand la journée d'une personne au moins manque ;
	 * - `total` : la somme de la semaine (`getTotal()`).
	 *
	 * Les barres se calent toutes sur `SCALE`, de 0 à 12 h, validé sur la planche M-Temps le
	 * 02/10/2026, quand la barre d'un jour est devenue le champ de sa journée : l'échelle de la plus longue
	 * journée (retour 36) faisait changer la durée d'une même position à chaque saisie.
	 *
	 * Sous deux personnes, il n'y a pas d'équipe : la lecture est refusée, comme l'onglet qui y mène.
	 */
	public static function read(\structure\Structure $eStructure, string $week): array {

		$cUser = TimeDayLib::getUsers($eStructure, $week);

		if($cUser->count() < 2) {
			throw new \NotExpectedAction('No team under two users');
		}

		\hr\WorkingTimeLib::fillByWeekFromUsers($eStructure, $cUser, $week);

		$dates = week_dates($week);

		// Le suivi RH ouvre la journée sans le temps des interventions : celui qu'un temps de travail éteint a laissé
		// en base ne se lit plus.
		if($eStructure->hasTime()) {
			\series\TimesheetLib::fillTimesByDate($eStructure, $cUser, $week);
		} else {
			foreach($cUser as $eUser) {
				$eUser['timesheetTime'] = array_fill_keys($dates, NULL);
			}
		}

		$workings = array_fill_keys($dates, NULL);
		$times = array_fill_keys($dates, 0.0);
		$absents = [];
		$overflows = array_fill_keys($dates, FALSE);
		$unfilleds = array_fill_keys($dates, FALSE);

		foreach($dates as $date) {
			$absents[$date] = new \Collection();
		}

		foreach($cUser as $eUser) {

			$eUser['timeDays'] = [];

			foreach($dates as $date) {

				// `fillTimesByDate()` pose `NULL` un jour sans temps sur les interventions, que la fiche lit zéro.
				$day = self::getDay($date, $eUser['workingTime'][$date], $eUser['timesheetTime'][$date] ?? 0.0, \hr\Absence::isDateAbsent($eUser['cAbsence'], $date), \hr\Presence::isDatePresent($eUser['cPresence'], $date));

				$eUser['timeDays'][$date] = $day;

				if($day['absent']) {
					$absents[$date][$eUser['id']] = $eUser;
				}

				if($day['overflow']) {
					$overflows[$date] = TRUE;
				}

				if($day['unfilled']) {
					$unfilleds[$date] = TRUE;
				}

				if($day['working'] !== NULL) {
					$workings[$date] = ($workings[$date] ?? 0.0) + $day['working'];
				}

				$times[$date] += $day['time'];

			}

			$eUser['timeTotal'] = self::getTotal($eUser['timeDays']);

		}

		// Un jour où personne n'a saisi sa journée reste « à saisir », pas « 0h00 ».
		$days = [];

		foreach($dates as $date) {
			$days[$date] = self::getDay($date, $workings[$date], $times[$date]);
			$days[$date]['cUserAbsent'] = $absents[$date];
			// La somme ne dit pas qui dépasse : les interventions d'une personne sans journée s'y ajoutent sans journée
			// en face, et l'excès de l'une se fond dans la marge des autres.
			$days[$date]['overflow'] = $overflows[$date];
			$days[$date]['unfilled'] = $unfilleds[$date];
		}

		return [
			'cUserTime' => $cUser,
			'days' => $days,
			'total' => self::getTotal($days),
		];

	}

	/**
	 * Les jours d'une semaine dans une ferme qui ne suit pas le temps (décidé le 17/10) : ni journée
	 * ni temps, seulement les absences. Chaque personne reçoit ses jours (`timeDays`), au format de `getDay()`, et
	 * chaque jour de l'équipe ses absents (`cUserAbsent`), comme dans `read()`, sans aucune lecture du temps.
	 */
	public static function readAbsences(\Collection $cUser, string $week): array {

		$days = [];

		foreach(week_dates($week) as $date) {
			$days[$date] = self::getDay($date, NULL, 0.0);
			$days[$date]['cUserAbsent'] = new \Collection();
		}

		foreach($cUser as $eUser) {

			$eUser['timeDays'] = [];

			foreach(week_dates($week) as $date) {

				$day = self::getDay($date, NULL, 0.0, \hr\Absence::isDateAbsent($eUser['cAbsence'], $date));

				$eUser['timeDays'][$date] = $day;

				if($day['absent']) {
					$days[$date]['cUserAbsent'][$eUser['id']] = $eUser;
				}

			}

		}

		return $days;

	}

	/**
	 * Les personnes du tableau d'un jour de l'équipe : toutes, sauf celles qui sont absentes ce jour-là sans
	 * journée ni temps sur les interventions, que la rangée de la semaine montre seule, sous la pastille du jour
	 * (R-Retours-1210, FH et FS).
	 * Un absent qui a une journée ou du temps garde sa ligne, parce que le jour de l'équipe additionne ce
	 * temps et que son total doit être la somme des lignes affichées. `$cUser` porte `timeDays` (`read()`).
	 *
	 * Les personnes rendues sont celles de `$cUser`, sans copie : la page complète chacune ensuite
	 * (`canWorking`, `cTaskSpent`), et le tableau doit lire ce qu'elle y pose.
	 */
	public static function getPresents(\Collection $cUser, string $date): \Collection {

		return $cUser->find(function(\user\User $eUser) use($date): bool {

			$day = $eUser['timeDays'][$date];

			return (
				$day['absent'] === FALSE or
				$day['working'] !== NULL or
				$day['time'] > 0.0
			);

		}, clone: FALSE);

	}

	/**
	 * Le détail du jour de chacun sur la fiche de l'équipe : pose `cTaskSpent` sur chaque personne
	 * (`TimeDayLib::getTasksSpent()`). La lecture se borne au jour et aux seules colonnes du détail : la fiche de
	 * l'équipe n'a pas de liste d'interventions, et la lecture de la semaine (`\series\TaskLib::getForWeekDone()`)
	 * charge tout ce que cette liste affiche. Les interventions retenues sont celles de la fiche d'une personne
	 * (`\series\TaskLib::applyOpenSeries()`), pour que les deux écrans disent la même journée.
	 */
	public static function fillTasksSpent(\structure\Structure $eStructure, \Collection $cUser, string $date): void {

		// Sans temps de travail, aucune intervention n'a de temps à détailler (`read()`).
		if($eStructure->hasTime() === FALSE) {

			foreach($cUser as $eUser) {
				$eUser['cTaskSpent'] = new \Collection();
			}

			return;

		}

		$times = [];

		$cTimesheet = \series\Timesheet::model()
			->select([
				'task', 'user',
				'total' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->whereDate($date)
			->whereUser('IN', $cUser)
			->group(['task', 'user'])
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['task']['id']][$date][$eTimesheet['user']['id']] = $eTimesheet['total'];
		}

		if($times === []) {
			$cTask = new \Collection();
		} else {

			\series\TaskLib::applyOpenSeries();

			$cTask = \series\Task::model()
				->select([
					'id', 'structure', 'description', 'methods',
					'cMethod?' => fn($e) => fn() => \work\MethodLib::askCollection($e['methods'], $e['structure']),
					'series' => ['name'],
					'plant' => ['name'],
					'action' => ['name', 'color'],
				])
				->whereStructure($eStructure)
				->whereId('IN', array_keys($times))
				->getCollection(NULL, NULL, 'id');

		}

		foreach($cUser as $eUser) {
			$eUser['cTaskSpent'] = TimeDayLib::getTasksSpent($cTask, $times, $date, $eUser);
		}

	}

	/**
	 * Le temps d'une personne sur les interventions, par jour et par action, sur toute la semaine : la barre des
	 * interventions de la fiche (`TimeDayLib::get()`). Une seule requête, les fiches de temps jointes à leur
	 * intervention, qui vit dans la même base : une intervention d'une série close compte comme les autres, et
	 * un temps dont l'intervention n'existe plus n'a pas d'action, donc pas d'entrée, comme dans la barre du
	 * jour qui le compte sans le ranger.
	 *
	 * @return array Par date de la semaine, une entrée par action, `['eAction', 'time']`, la plus longue
	 *   d'abord, puis par nom.
	 */
	public static function getActionsByDate(\structure\Structure $eStructure, \user\User $eUser, string $week): array {

		$dates = week_dates($week);

		$cTimesheet = \series\Timesheet::model()
			->select([
				'date',
				'timeAction' => new \Sql('SUM(m1.time)', 'float'),
			])
			->join(\series\Task::model()
				->select([
					'action' => \work\ActionElement::getSelection(),
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.user', $eUser)
			->where('m1.date BETWEEN '.\series\Timesheet::model()->format(first($dates)).' AND '.\series\Timesheet::model()->format(last($dates)))
			->where('m1.time > 0')
			->group([new \Sql('m1.date'), new \Sql('m2.action')])
			->getCollection();

		$actionsByDate = array_fill_keys($dates, []);

		foreach($cTimesheet as $eTimesheet) {
			$actionsByDate[$eTimesheet['date']][] = ['eAction' => $eTimesheet['action'], 'time' => $eTimesheet['timeAction']];
		}

		foreach($dates as $date) {
			usort($actionsByDate[$date], fn(array $action1, array $action2): int => [$action2['time'], $action1['eAction']['name'], $action1['eAction']['id']] <=> [$action1['time'], $action2['eAction']['name'], $action2['eAction']['id']]);
		}

		return $actionsByDate;

	}

	/**
	 * Un jour d'une personne ou de l'équipe : sa journée (`working`, `NULL` à saisir), ses interventions
	 * (`time`), s'il est à venir (`future`) ou aujourd'hui (`today`), et si la personne est absente toute la
	 * journée (`absent`, R-Retours-1210, FH) ou une part seulement (`absentPart`, `\hr\Absence::getPartOn()`), ce
	 * que le jour de l'équipe ne dit pas : `read()` y pose à la place ses absents (`cUserAbsent`). La fiche le lit
	 * aussi (`TimeDayLib::get()`).
	 *
	 * Une absence d'une part du jour laisse le jour travaillé : il garde sa journée et sa ligne dans l'équipe.
	 *
	 * `overflow` dit que les interventions dépassent une journée saisie, l'alerte de l'ancien planning
	 * (`\series\PlanningUi::getDayTimesheetTime()`, R-Retours-1210, GJ). L'arrondi au centième écarte l'erreur des
	 * temps en `float32` additionnés : trois interventions de 0h20 ne dépassent pas une journée de 1h00.
	 *
	 * `unfilled` dit qu'une journée manque (18/10) : le jour est passé, la personne était dans l'équipe
	 * ce jour-là (`$ePresence`, `\hr\Presence::isDatePresent()`), sans absence validée, ni journée saisie, et ce
	 * n'est pas un jour férié (`holiday`, `\hr\HolidayLib`). Les week-ends en sont, et la journée en cours n'en est pas,
	 * puisqu'elle n'est pas finie. Sans présence, un jour n'a jamais de journée qui manque : c'est le cas de la
	 * personne connectée qui n'est pas dans l'équipe, et du jour de l'équipe, que `read()` complète.
	 */
	public static function getDay(string $date, ?float $working, float $time, \hr\Absence $eAbsence = new \hr\Absence(), \hr\Presence $ePresence = new \hr\Presence()): array {

		$absentPart = $eAbsence->empty() ? NULL : $eAbsence->getPartOn($date);
		$holiday = \hr\HolidayLib::isHoliday($date);

		return [
			'holiday' => $holiday,
			'unfilled' => (
				$ePresence->notEmpty() and
				$date < \Clock::date() and
				$working === NULL and
				$eAbsence->empty() and
				$holiday === FALSE
			),
			'working' => $working,
			'time' => $time,
			'future' => ($date > \Clock::date()),
			'today' => ($date === \Clock::date()),
			'absent' => ($eAbsence->notEmpty() and $absentPart === NULL),
			'absentPart' => $absentPart,
			'overflow' => ($working > 0.0 and round($time, 2) > round($working, 2)),
		];

	}

	/**
	 * La somme d'une semaine, à partir de ses jours au format de `getDay()`. Comme pour un jour, `working`
	 * reste `NULL` quand aucune journée n'est saisie : une semaine à saisir n'est pas une semaine à « 0h00 ».
	 */
	public static function getTotal(array $days): array {

		$working = NULL;
		$time = 0.0;

		foreach($days as $day) {

			if($day['working'] !== NULL) {
				$working = ($working ?? 0.0) + $day['working'];
			}

			$time += $day['time'];

		}

		return [
			'working' => $working,
			'time' => $time,
		];

	}

}
?>
