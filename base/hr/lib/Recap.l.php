<?php
namespace hr;

/**
 * Le récapitulatif d'un mois, qui prépare la paie : pour chaque personne, ses journées, ses heures, ses jours
 * fériés travaillés, ses heures supplémentaires et complémentaires, ses absences par motif et ses jours non saisis ;
 * pour une personne, chacun de ses jours. Le mois se lit sur sa période (`getStart()`, `getStop()`), qui est le
 * mois civil sauf si la structure a choisi un autre premier jour.
 */
class RecapLib {

	/**
	 * L'écran d'où part la saisie d'une journée, que `planning/timeDay:doWorkingTime` lit pour répondre par le jour
	 * du récapitulatif.
	 */
	const string ORIGIN = 'recap';

	/**
	 * Le premier jour de la période d'un mois. Elle part du premier jour choisi par la structure
	 * (`Configuration.timePeriodDay`) et porte le nom du mois où elle finit : avec le 20, octobre va du 20 septembre
	 * au 19 octobre. Avec le 1er, c'est le mois civil.
	 */
	public static function getStart(\structure\Structure $eStructure, string $month): string {

		$day = $eStructure->conf()['timePeriodDay'];

		if($day === 1) {
			return $month.'-01';
		}

		return date('Y-m', strtotime($month.'-01 - 1 MONTH')).'-'.sprintf('%02d', $day);

	}

	/**
	 * Le dernier jour de la période d'un mois, la veille du premier jour de la période suivante.
	 */
	public static function getStop(\structure\Structure $eStructure, string $month): string {

		$day = $eStructure->conf()['timePeriodDay'];

		if($day === 1) {
			return date('Y-m-t', strtotime($month.'-01'));
		}

		return $month.'-'.sprintf('%02d', $day - 1);

	}

	/**
	 * Le mois dont la période contient `$date` : avec le 20, le 25 octobre est dans la période de novembre.
	 */
	public static function getMonth(\structure\Structure $eStructure, string $date): string {

		$day = $eStructure->conf()['timePeriodDay'];
		$month = substr($date, 0, 7);

		if(
			$day > 1 and
			(int)substr($date, 8, 2) >= $day
		) {
			return date('Y-m', strtotime($month.'-01 + 1 MONTH'));
		}

		return $month;

	}

	/**
	 * Les semaines qui touchent la période, du lundi qui précède son premier jour au dimanche qui suit son dernier.
	 *
	 * @return string[]
	 */
	public static function getWeeks(\structure\Structure $eStructure, string $month): array {

		$weeks = [];

		$week = toWeek(self::getStart($eStructure, $month));
		$weekLast = toWeek(self::getStop($eStructure, $month));

		while(TRUE) {

			$weeks[] = $week;

			if($week === $weekLast) {
				return $weeks;
			}

			$week = toWeek(week_date_starts($week).' + 7 DAYS');

		}

	}

	/**
	 * Les semaines dont la période compte les heures supplémentaires : celles qui y finissent. Une semaine à cheval
	 * sur deux périodes les compte dans la période de son dimanche, comme elle ne se compte qu'une fois : la loi
	 * compte ces heures à la semaine civile, jamais à la période de paie.
	 *
	 * @return string[]
	 */
	public static function getWeeksOvertime(\structure\Structure $eStructure, string $month): array {

		$start = self::getStart($eStructure, $month);
		$stop = self::getStop($eStructure, $month);

		return array_values(array_filter(
			self::getWeeks($eStructure, $month),
			fn(string $week) => (week_date_ends($week) >= $start and week_date_ends($week) <= $stop)
		));

	}

	/**
	 * @return string[] les jours fériés de la période, en `Y-m-d`
	 */
	public static function getHolidays(\structure\Structure $eStructure, string $month): array {

		$start = self::getStart($eStructure, $month);
		$stop = self::getStop($eStructure, $month);

		return array_values(array_filter(
			self::getHolidaysBetween($start, $stop),
			fn(string $date) => ($date >= $start and $date <= $stop)
		));

	}

	/**
	 * Les jours fériés des années que touchent `$start` et `$stop`, une période pouvant partir en décembre.
	 *
	 * @return string[]
	 */
	protected static function getHolidaysBetween(string $start, string $stop): array {

		return array_unique(array_merge(
			HolidayLib::getByYear((int)substr($start, 0, 4)),
			HolidayLib::getByYear((int)substr($stop, 0, 4))
		));

	}

	/**
	 * Les personnes du récapitulatif d'un mois : celles dont une période de présence touche sa période, et celles
	 * qui y ont une journée sans avoir aucune période, dont la journée compte (`Presence::isDateWorkable()`). Un
	 * compte fermé depuis y figure : il a travaillé ce mois-là.
	 */
	public static function getUsers(\structure\Structure $eStructure, string $month): \Collection {

		$start = self::getStart($eStructure, $month);
		$stop = self::getStop($eStructure, $month);

		$ids = PresenceLib::getBetween($eStructure, $start, $stop, withClosed: TRUE)->getKeys();

		$cWorkingTime = WorkingTime::model()
			->select(['user'])
			->whereStructure($eStructure)
			->whereDate('BETWEEN', new \Sql(WorkingTime::model()->format($start).' AND '.WorkingTime::model()->format($stop)))
			->group('user')
			->getCollection();

		$idsWorking = array_diff($cWorkingTime->getColumnCollection('user')->getIds(), $ids);

		// Une personne qui a des périodes, aucune dans le mois, n'a rien qui compte : ses journées en sont hors.
		if($idsWorking !== []) {

			$idsPresent = Presence::model()
				->select(['user'])
				->whereStructure($eStructure)
				->whereUser('IN', $idsWorking)
				->group('user')
				->getCollection()
				->getColumnCollection('user')
				->getIds();

			$ids = array_merge($ids, array_diff($idsWorking, $idsPresent));

		}

		return \user\UserLib::getByIds($ids, \user\User::getSelection(), [
			'firstName' => SORT_ASC,
			'lastName' => SORT_ASC
		], 'id');

	}

	/**
	 * Pose sur chaque personne de `$cUser` toutes ses périodes de présence (`cPresence`), et son mois (`recap`), dont
	 * ne compte que ce qui tombe dans ces périodes (`Presence::isDateWorkable()`) : une journée ou une absence restée
	 * en base hors présence, avant l'arrivée ou après un départ, n'est pas payée.
	 * - `days`, `time` : ses journées saisies et leur durée ;
	 * - `holidays` : ses journées un jour férié ;
	 * - `extra`, `extraHigh`, `complementary` : la somme de ses heures sur les semaines qui finissent dans la
	 *   période (`getWeeksOvertime()`), NULL quand aucune n'avait de contrat à la semaine qui les compte ;
	 * - `year` : le solde d'un contrat annualisé à la fin de la période, ou aujourd'hui si elle n'est pas finie,
	 *   NULL sans contrat annualisé (`OvertimeLib::getYearBalances()`) ;
	 * - `absences` : par motif, ses jours d'absence (`days`) et ses heures d'absence (`hours`) dans la période, et
	 *   `absent`, la somme de ses jours d'absence.
	 *
	 * Avec `$withMissing`, pour le tableau de l'équipe, chaque personne porte aussi ses jours non saisis
	 * (`missing`, `fillMissing()`), comptés sur les journées et les absences déjà lues.
	 */
	public static function fillByMonth(\structure\Structure $eStructure, \Collection $cUser, string $month, bool $withMissing = FALSE): void {

		$start = self::getStart($eStructure, $month);
		$stop = self::getStop($eStructure, $month);

		$holidays = self::getHolidays($eStructure, $month);

		foreach($cUser as $eUser) {

			$eUser['recap'] = [
				'days' => 0,
				'time' => 0.0,
				'holidays' => 0,
				'extra' => NULL,
				'extraHigh' => NULL,
				'complementary' => NULL,
				'year' => NULL,
				'absences' => [],
				'absent' => 0.0
			];

		}

		if($cUser->empty()) {
			return;
		}

		PresenceLib::fillUsers($eStructure, $cUser);

		$cWorkingTime = WorkingTime::model()
			->select(['user', 'date', 'time'])
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->whereDate('BETWEEN', new \Sql(WorkingTime::model()->format($start).' AND '.WorkingTime::model()->format($stop)))
			->getCollection();

		$worked = [];

		foreach($cWorkingTime as $eWorkingTime) {

			$eUser = $cUser[$eWorkingTime['user']['id']];

			if(Presence::isDateWorkable($eUser['cPresence'], $eWorkingTime['date']) === FALSE) {
				continue;
			}

			$worked[$eWorkingTime['user']['id']][$eWorkingTime['date']] = TRUE;

			$recap = $eUser['recap'];
			$recap['days']++;
			$recap['time'] += $eWorkingTime['time'];
			$recap['holidays'] += in_array($eWorkingTime['date'], $holidays, TRUE) ? 1 : 0;

			$eUser['recap'] = $recap;

		}

		foreach(self::getWeeksOvertime($eStructure, $month) as $week) {

			OvertimeLib::fillByWeek($eStructure, $cUser, $week, withBalance: FALSE);

			foreach($cUser as $eUser) {

				$recap = $eUser['recap'];

				// NULL tant qu'aucune semaine n'a de contrat qui les compte, zéro dès qu'une les compte sans en avoir.
				foreach(['extra', 'extraHigh', 'complementary'] as $key) {

					if($eUser['overtime'][$key] !== NULL) {
						$recap[$key] = ($recap[$key] ?? 0.0) + $eUser['overtime'][$key];
					}

				}

				$eUser['recap'] = $recap;

			}

		}

		foreach(OvertimeLib::getYearBalances($eStructure, $cUser, min($stop, \Clock::date())) as $userId => $year) {

			$recap = $cUser[$userId]['recap'];
			$recap['year'] = $year;

			$cUser[$userId]['recap'] = $recap;

		}

		$cAbsence = AbsenceLib::getValidatedBetween($eStructure, $start, $stop, withType: TRUE);

		foreach($cAbsence as $eAbsence) {

			if($cUser->offsetExists($eAbsence['user']['id']) === FALSE) {
				continue;
			}

			$eUser = $cUser[$eAbsence['user']['id']];

			if($eAbsence['unit'] === Absence::HOUR) {
				$hours = Presence::isDatePresent($eUser['cPresence'], substr($eAbsence['from'], 0, 10))->notEmpty() ? $eAbsence['duration'] : 0.0;
				$days = 0.0;
			} else {
				$hours = 0.0;
				$days = self::getAbsenceDays($eAbsence, $eUser['cPresence'], $start, $stop);
			}

			// Une absence toute hors présence ne compte rien, et son motif ne prend ni ligne ni colonne d'export.
			if($hours === 0.0 and $days === 0.0) {
				continue;
			}

			$recap = $eUser['recap'];
			$recap['absences'][$eAbsence['type']] ??= ['days' => 0.0, 'hours' => 0.0];
			$recap['absences'][$eAbsence['type']]['hours'] += $hours;
			$recap['absences'][$eAbsence['type']]['days'] += $days;
			$recap['absent'] += $days;

			$eUser['recap'] = $recap;

		}

		if($withMissing) {
			self::fillMissing($eStructure, $cUser, $start, $stop, $holidays, $worked, $cAbsence);
		}

	}

	/**
	 * La part de la période d'une absence à la journée : sa durée saisie quand elle tient dans la période et dans la
	 * présence de la personne (toutes ses périodes, `$cPresence`), sinon ses jours comptés entre les bornes de la
	 * période, dans sa présence seule (`AbsenceLib::getDaysBetween()`).
	 */
	protected static function getAbsenceDays(Absence $eAbsence, \Collection $cPresence, string $start, string $stop): float {

		$from = substr($eAbsence['from'], 0, 10);
		$to = substr($eAbsence['to'], 0, 10);

		if(
			$from >= $start and
			$to <= $stop and
			Presence::isRangePresent($cPresence, $from, $to)
		) {
			return $eAbsence['duration'];
		}

		return AbsenceLib::getDaysBetween($eAbsence, max($from, $start), min($to, $stop));

	}

	/**
	 * Pose sur chaque personne de `$cUser` le nombre de jours de la période qui manquent (`missing`), ceux que
	 * `getByUser()` signale un à un, sur ses journées saisies (`$worked`, par personne et par date) et les absences
	 * validées de la période (`$cAbsence`).
	 *
	 * @param string[] $holidays
	 * @param array<int, array<string, bool>> $worked
	 */
	protected static function fillMissing(\structure\Structure $eStructure, \Collection $cUser, string $start, string $stop, array $holidays, array $worked, \Collection $cAbsence): void {

		$ccPresence = PresenceLib::getByUsersBetween($eStructure, $cUser, $start, $stop);

		// Un compte de jours, comme `AbsenceLib::getDaysBetween()`, et non une comparaison de dates écrites.
		$span = (int)round((strtotime($stop) - strtotime($start)) / 86400);

		foreach($cUser as $eUser) {

			$cPresence = $ccPresence->offsetExists($eUser['id']) ? $ccPresence[$eUser['id']] : new \Collection();
			$cAbsenceUser = $cAbsence->find(fn(Absence $eAbsence) => $eAbsence['user']['id'] === $eUser['id']);

			$missing = 0;

			for($index = 0; $index <= $span; $index++) {

				$date = date('Y-m-d', strtotime($start.' + '.$index.' DAY'));

				if(Presence::isDateMissing(
					$cPresence,
					$date,
					isset($worked[$eUser['id']][$date]),
					Absence::isDateAbsent($cAbsenceUser, $date)->notEmpty(),
					in_array($date, $holidays, TRUE)
				)) {
					$missing++;
				}

			}

			$eUser['missing'] = $missing;

		}

	}

	/**
	 * Le mois de l'équipe, la somme du mois de chacun et de ses jours non saisis (`fillByMonth()`, `$withMissing`).
	 * Le solde d'un annualisé ne s'additionne pas d'une personne à l'autre.
	 */
	public static function getTotal(\Collection $cUser): array {

		$total = [
			'days' => 0,
			'time' => 0.0,
			'holidays' => 0,
			'extra' => NULL,
			'extraHigh' => NULL,
			'complementary' => NULL,
			'year' => NULL,
			'absences' => [],
			'missing' => 0
		];

		foreach($cUser as $eUser) {

			$total['missing'] += $eUser['missing'];

			foreach(['days', 'time', 'holidays'] as $key) {
				$total[$key] += $eUser['recap'][$key];
			}

			foreach(['extra', 'extraHigh', 'complementary'] as $key) {

				if($eUser['recap'][$key] !== NULL) {
					$total[$key] = ($total[$key] ?? 0.0) + $eUser['recap'][$key];
				}

			}

			foreach($eUser['recap']['absences'] as $type => $absence) {

				$total['absences'][$type] ??= ['days' => 0.0, 'hours' => 0.0];
				$total['absences'][$type]['days'] += $absence['days'];
				$total['absences'][$type]['hours'] += $absence['hours'];

			}

		}

		return $total;

	}

	/**
	 * Les colonnes du mois : les heures supplémentaires et complémentaires, si une personne en compte, et les motifs
	 * d'absence posés. Le solde d'un annualisé se lit dans la colonne des supplémentaires, qui porte son nom
	 * (`balance`) quand aucun contrat à la semaine n'y compte d'heures.
	 *
	 * @return array{extra: bool, balance: bool, complementary: bool, types: array<string, bool>}
	 */
	public static function getColumns(\Collection $cUser): array {

		$extra = self::hasExtra($cUser);
		$year = self::hasYear($cUser);

		return [
			'extra' => ($extra or $year),
			'balance' => ($extra === FALSE and $year),
			'complementary' => self::hasComplementary($cUser),
			'types' => self::getAbsenceTypes($cUser)
		];

	}

	/**
	 * Les motifs d'absence du mois, dans l'ordre du schéma : une colonne de l'export chacun, et une seconde pour
	 * un motif posé à l'heure.
	 *
	 * @return array<string, bool> par motif, s'il compte des heures
	 */
	protected static function getAbsenceTypes(\Collection $cUser): array {

		$types = [];

		foreach($cUser as $eUser) {

			foreach($eUser['recap']['absences'] as $type => $absence) {
				$types[$type] ??= FALSE;
				$types[$type] = ($types[$type] or $absence['hours'] > 0.0);
			}

		}

		$sorted = [];

		foreach(Absence::model()->getPropertyEnum('type') as $type) {

			if(array_key_exists($type, $types)) {
				$sorted[$type] = $types[$type];
			}

		}

		return $sorted;

	}

	/**
	 * Si un contrat à la semaine compte des heures supplémentaires dans le mois.
	 */
	protected static function hasExtra(\Collection $cUser): bool {

		foreach($cUser as $eUser) {

			if($eUser['recap']['extra'] !== NULL) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Si un contrat annualisé a un solde dans le mois.
	 */
	protected static function hasYear(\Collection $cUser): bool {

		foreach($cUser as $eUser) {

			if($eUser['recap']['year'] !== NULL) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Le mois d'avant et le mois d'après, `NULL` hors des bornes de `isMonthValid()`, et les douze derniers mois que
	 * la structure peut afficher, du plus récent au plus ancien.
	 *
	 * Elle porte aussi les bornes de la période du mois (`start`, `stop`), et `civil` quand c'est le mois civil.
	 *
	 * @return array{previous: ?string, next: ?string, months: string[], start: string, stop: string, civil: bool}
	 */
	public static function getNavigation(\structure\Structure $eStructure, string $month): array {

		$previous = date('Y-m', strtotime($month.'-01 - 1 MONTH'));
		$next = date('Y-m', strtotime($month.'-01 + 1 MONTH'));

		$monthCurrent = self::getMonth($eStructure, \Clock::date());

		$months = [];

		for($index = 0; $index < 12; $index++) {

			$monthOption = date('Y-m', strtotime($monthCurrent.'-01 - '.$index.' MONTH'));

			if(self::isMonthValid($eStructure, $monthOption)) {
				$months[] = $monthOption;
			}

		}

		return [
			'previous' => self::isMonthValid($eStructure, $previous) ? $previous : NULL,
			'next' => self::isMonthValid($eStructure, $next) ? $next : NULL,
			'months' => $months,
			'start' => self::getStart($eStructure, $month),
			'stop' => self::getStop($eStructure, $month),
			'civil' => ($eStructure->conf()['timePeriodDay'] === 1)
		];

	}

	/**
	 * Si un contrat sous la durée légale compte des heures complémentaires dans le mois : elles ont leur colonne.
	 */
	protected static function hasComplementary(\Collection $cUser): bool {

		foreach($cUser as $eUser) {

			if($eUser['recap']['complementary'] !== NULL) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Le mois d'une personne, semaine par semaine (`getWeeks()`) : chaque jour, s'il est dans la période (`inPeriod`),
	 * sa journée (`eWorkingTime`, vide quand rien n'est saisi), son absence (`absent`), s'il est férié (`holiday`),
	 * s'il est à venir (`future`), s'il manque (`missing`, `Presence::isDateMissing()`, sur les périodes de présence
	 * `$cPresence`), et si sa journée se saisit d'ici (`canWorking`). Un jour hors des périodes de présence de la personne
	 * (`inPresence`, sur `cPresence`, que pose `fillByMonth()`) ne montre ni journée ni absence, comme il ne compte
	 * pas au mois, et ne s'ouvre pas. La saisie passe par la fiche de temps, qui refuse une personne
	 * hors de son équipe de la semaine (`planning\TimeDayLib::getUsers()`) : un compte fermé se lit sans s'ouvrir.
	 * Chaque semaine porte aussi la durée de ses journées de la période (`time`), et si un de ses jours a une
	 * journée ou une absence à écrire (`values`).
	 *
	 * @return array<string, array{time: float, values: bool, days: array<string, array>}> par semaine
	 */
	public static function getByUser(\structure\Structure $eStructure, \user\User $eUser, string $month, \Collection $cPresence): array {

		$eUser->expects(['cPresence']);

		$weeks = self::getWeeks($eStructure, $month);

		$periodStart = self::getStart($eStructure, $month);
		$periodStop = self::getStop($eStructure, $month);

		$start = week_date_starts(first($weeks));
		$stop = week_date_ends(last($weeks));

		$cWorkingTime = WorkingTime::model()
			->select(WorkingTime::getSelection())
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereDate('BETWEEN', new \Sql(WorkingTime::model()->format($periodStart).' AND '.WorkingTime::model()->format($periodStop)))
			->getCollection(NULL, NULL, 'date');

		$cAbsence = AbsenceLib::getValidatedBetween($eStructure, $start, $stop, withType: FALSE)
			->find(fn(Absence $eAbsence) => $eAbsence['user']['id'] === $eUser['id']);

		$holidays = self::getHolidaysBetween($start, $stop);

		$result = [];

		foreach($weeks as $week) {

			// Un jour s'ouvre dans le récapitulatif quand il s'ouvrirait dans la fiche de la semaine : la règle se lit
			// de la fiche elle-même, semaine par semaine, pour que les deux écrans ne puissent pas diverger.
			$team = \planning\TimeDayLib::getUsers($eStructure, $week)->offsetExists($eUser['id']);

			$days = [];
			$time = 0.0;
			$values = FALSE;

			foreach(week_dates($week) as $date) {

				$inPeriod = ($date >= $periodStart and $date <= $periodStop);
				$inPresence = Presence::isDateWorkable($eUser['cPresence'], $date);
				$eWorkingTime = ($inPeriod and $inPresence and $cWorkingTime->offsetExists($date)) ? $cWorkingTime[$date] : new WorkingTime();

				if($eWorkingTime->notEmpty()) {
					$time += $eWorkingTime['time'];
				}

				$absent = ($inPeriod and $inPresence and Absence::isDateAbsent($cAbsence, $date)->notEmpty());
				$holiday = in_array($date, $holidays, TRUE);

				$values = ($values or $eWorkingTime->notEmpty() or $absent);

				$days[$date] = [
					'inPeriod' => $inPeriod,
					'inPresence' => $inPresence,
					'eWorkingTime' => $eWorkingTime,
					'absent' => $absent,
					'holiday' => $holiday,
					'future' => ($date > \Clock::date()),
					'missing' => (
						$inPeriod and
						Presence::isDateMissing($cPresence, $date, $eWorkingTime->notEmpty(), $absent, $holiday)
					),
					'canWorking' => (
						$inPeriod and
						$team and
						\planning\TimeDayLib::canWorking($eStructure, $eUser, $date)
					)
				];

			}

			$result[$week] = [
				'time' => $time,
				'values' => $values,
				'days' => $days
			];

		}

		return $result;

	}

	/**
	 * Le mois, si le récapitulatif peut l'afficher : pas après celui dont la période contient aujourd'hui, ni hors
	 * des saisons de la structure, comme la semaine de la fiche de temps.
	 */
	public static function isMonthValid(\structure\Structure $eStructure, string $month): bool {

		return (
			\Filter::check('month', $month) and
			$month <= self::getMonth($eStructure, \Clock::date()) and
			$eStructure->conf(\map\Configuration::class)->isSeasonValid((int)substr($month, 0, 4))
		);

	}

}
?>
