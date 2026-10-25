<?php
namespace planning;

/**
 * L'Année de l'écran « Interventions » (R-Annee, piste A de la planche M-Annee retenue) : une
 * colonne par semaine ISO de l'année, cartes rangées par action, comme le Jour range une semaine par jour.
 *
 * Une colonne montre exactement la Semaine du même numéro (`TaskWeekLib::get()`), sous « Masquer ce qui
 * est fait » : les cartes et les comptes disent la même chose d'une période à
 * l'autre. Lire les 52 ou 53 semaines coûte environ 2,3 s sur la structure 7 (planche M-Annee) : la page ne
 * rend le contenu que des semaines autour de celle qu'elle ouvre (`getAround()`), les autres n'ont que leur
 * tête et se chargent par paquets quand elles approchent du cadre (`taskYear:weeks`).
 *
 * L'année est l'année ISO de la semaine : ses semaines vont de la 1 à la 52 ou 53, et un mois rassemble
 * celles dont le jeudi y tombe (`getMonth()`), comme la numérotation ISO.
 */
class TaskYearLib {

	/**
	 * Le nombre de semaines qu'une requête de chargement accepte : celles que le cadre peut montrer d'un coup
	 * à 1599 px et au-delà, avec la marge d'une largeur de cadre de chaque côté (`PlanningTaskYear`).
	 */
	const LOAD_MAX = 8;

	/**
	 * Les semaines dont la page rend le contenu : celle qu'on ouvre, la précédente, et les six suivantes.
	 */
	const AROUND_BEFORE = 1;
	const AROUND_AFTER = 6;

	/**
	 * La granularité de la réponse d'un geste (`getGranularity()`) : la carte et la tête de sa colonne, ou les
	 * colonnes entières.
	 */
	const CARD = 'card';
	const COLUMN = 'column';

	/**
	 * Les semaines ISO de l'année, de la 1 à la dernière : le 28 décembre tombe toujours dans la dernière.
	 */
	public static function getWeeks(int $year): array {

		$count = (int)date('W', strtotime($year.'-12-28'));

		$weeks = [];

		for($number = 1; $number <= $count; $number++) {
			$weeks[] = $year.'-W'.sprintf('%02d', $number);
		}

		return $weeks;

	}

	/**
	 * Les semaines de l'année dont la page rend le contenu, autour de celle qu'elle ouvre. Au bord de l'année,
	 * la fenêtre ne déborde pas : janvier n'a pas de semaine avant la 1, décembre bute sur la dernière.
	 */
	public static function getAround(string $week): array {

		$weeks = self::getWeeks(week_year($week));
		$position = array_search($week, $weeks, TRUE);

		return array_slice($weeks, max(0, $position - self::AROUND_BEFORE), self::AROUND_BEFORE + 1 + self::AROUND_AFTER);

	}

	/**
	 * Le mois d'une semaine, celui de son jeudi, par la règle unique du dépôt
	 * (`\util\DateLib::convertWeeksToMonths()`, gardée par `WeekMonthSentinelAi`), qui rend `Y-m`.
	 */
	public static function getMonth(string $week): int {

		return (int)substr(\util\DateLib::convertWeeksToMonths([$week])[0], 5, 2);

	}

	/**
	 * La première semaine de chaque mois de l'année, par numéro de mois.
	 */
	public static function getMonthWeeks(int $year): array {

		$months = [];

		foreach(self::getWeeks($year) as $week) {
			$months[self::getMonth($week)] ??= $week;
		}

		return $months;

	}

	/**
	 * Les premières semaines des mois que l'écran de l'année nomme : celles de ses douze mois (`months`), pour
	 * la rangée des mois, puis décembre de l'année d'avant (`before`) et janvier de l'année d'après (`after`),
	 * où mènent les flèches de l'en-tête.
	 */
	public static function getCalendar(int $year): array {

		return [
			'months' => self::getMonthWeeks($year),
			'before' => self::getMonthWeeks($year - 1)[12],
			'after' => self::getMonthWeeks($year + 1)[1],
		];

	}

	/**
	 * Les colonnes de l'année, une par semaine, dans l'ordre. Celles de `$weeksLoaded` portent leur contenu
	 * (`loaded`), les autres ne sont qu'une tête, que le navigateur remplit en approchant (`taskYear:weeks`).
	 * Toutes portent leur mois (`month`), que la rangée des mois suit au défilement.
	 *
	 * Une colonne chargée porte, comme une colonne du Jour (`TaskDayLib::getColumns()`) :
	 * - `cTask` : ses cartes, sous le masque, dans l'ordre de la Semaine, que l'écran découpe
	 *   par action ;
	 * - `todo` et `done` : ce qui y reste à faire et ce qui y est fait, le masque ignoré,
	 *   comme les comptes de la Semaine, pour ne pas changer d'une réponse ciblée au rechargement ;
	 * - `hidden` : le masque y cache au moins une carte.
	 *
	 * Une intervention paraît dans chaque semaine où elle est prévue, faite, ou porte du temps ou une récolte :
	 * sa carte prend donc la semaine de sa colonne dans son identifiant (`TaskCardUi::getId()`). Les cartes
	 * de l'Année ne portent ni case de sélection (`main`), ni rien de ce que « Sans jour » garde (`kept`).
	 *
	 * `$tasksShown` sont les interventions faites que l'écran montre encore sous le masque, parce qu'on vient
	 * de les cocher : chaque geste les réémet, sans quoi la colonne qu'il redessine ferait disparaître la
	 * coche précédente sous la main, ce que la Semaine évite en ne remplaçant que la carte. Elles
	 * disparaissent au rechargement, comme dans la Semaine.
	 */
	public static function getColumns(\structure\Structure $eStructure, array $weeks, array $weeksLoaded, bool $hide, array $tasksShown = []): array {

		$cTaskByWeek = TaskWeekLib::getByWeeks($eStructure, array_values(array_intersect($weeks, $weeksLoaded)));

		$columns = [];

		foreach($weeks as $week) {

			$columns[$week] = array_key_exists($week, $cTaskByWeek) ?
				self::getColumn($cTaskByWeek[$week], $hide, $tasksShown) :
				['loaded' => FALSE];

			$columns[$week]['month'] = self::getMonth($week);

		}

		return $columns;

	}

	protected static function getColumn(\Collection $cTask, bool $hide, array $tasksShown): array {

		$column = [
			'loaded' => TRUE,
			'cTask' => new \Collection(),
			'todo' => 0,
			'done' => 0,
			'hidden' => FALSE,
		];

		foreach($cTask as $eTask) {

			if($eTask->isTodo()) {
				$column['todo']++;
			} else {
				$column['done']++;
			}

			if($hide and $eTask->isDone() and in_array($eTask['id'], $tasksShown, TRUE) === FALSE) {
				$column['hidden'] = TRUE;
				continue;
			}

			$eTask['main'] = FALSE;
			$eTask['kept'] = FALSE;

			$column['cTask'][$eTask['id']] = $eTask;

		}

		return $column;

	}

	/**
	 * Les semaines de l'année où une intervention paraît, lues pour elle seule, sur les conditions des deux
	 * lectures de la Semaine (`\series\TaskLib::getForWeekTodo()` et `getForWeekDone()`) : sa semaine prévue
	 * si elle est à faire, sa semaine faite si elle est faite, et chaque semaine où elle porte du temps ou une
	 * récolte. Un geste redessine les semaines d'avant et d'après lui : une semaine de trop se redessine à
	 * l'identique, une semaine oubliée garderait une carte fausse.
	 */
	public static function getWeeksForTask(\structure\Structure $eStructure, \series\Task $eTask): array {

		$weeks = [];

		if($eTask->isTodo() and $eTask['plannedWeek'] !== NULL) {
			$weeks[] = $eTask['plannedWeek'];
		}

		if($eTask->isDone() and $eTask['doneWeek'] !== NULL) {
			$weeks[] = $eTask['doneWeek'];
		}

		$dates = array_merge(
			\series\Timesheet::model()
				->whereStructure($eStructure)
				->whereTask($eTask)
				->group('date')
				->getColumn('date'),
			\series\Harvest::model()
				->whereStructure($eStructure)
				->whereTask($eTask)
				->group('date')
				->getColumn('date')
		);

		foreach($dates as $date) {
			$weeks[] = toWeek($date);
		}

		$weeks = array_values(array_unique($weeks));
		sort($weeks);

		return $weeks;

	}

	/**
	 * Les semaines qu'un geste redessine : celles où l'intervention paraissait avant lui et celles où elle
	 * paraît après, dans l'année affichée et dans l'ordre de l'écran.
	 */
	public static function getChange(int $year, array $weeksBefore, array $weeksAfter): array {

		return array_values(array_intersect(self::getWeeks($year), array_merge($weeksBefore, $weeksAfter)));

	}

	/**
	 * Ce qu'un geste renvoie (arbitrage de V-Planning du 2026-10-03, « Une action ne renvoie que ce qui change »
	 * de CLAUDE.md). Quand l'intervention paraît dans les mêmes semaines avant et après lui (coche dans sa
	 * semaine, décoche), seules sa carte et la tête de chaque colonne changent : l'ordre des cartes ne dépend
	 * pas de leur état (`TaskWeekLib::get()`), donc la carte se remplace en place. Une colonne de printemps
	 * pèse 180 Ko, sa carte 1 Ko. Quand l'ensemble des semaines change, une carte paraît ou disparaît, et les
	 * colonnes se redessinent entières.
	 *
	 * La carte doit aussi être dans chaque colonne relue : une intervention que le masque retire n'y est pas, et
	 * seule la colonne entière dit alors la vérité.
	 *
	 * @param array $columns Les colonnes relues après le geste (`getColumns()`), toutes chargées.
	 */
	public static function getGranularity(\series\Task $eTask, array $weeksBefore, array $weeksAfter, array $columns): string {

		if($weeksBefore !== $weeksAfter) {
			return self::COLUMN;
		}

		foreach($columns as $column) {

			if($column['cTask']->offsetExists($eTask['id']) === FALSE) {
				return self::COLUMN;
			}

		}

		return self::CARD;

	}

	/**
	 * Les jours de `$weekFirst` à `$weekLast`, bornes d'un `BETWEEN` sur une date de temps ou de récolte.
	 */
	public static function getPeriodSql(string $weekFirst, string $weekLast): \Sql {

		return new \Sql(\series\Timesheet::model()->format(week_date_starts($weekFirst)).' AND '.\series\Timesheet::model()->format(week_date_ends($weekLast)));

	}

	/**
	 * Les semaines de `$weeks` regroupées en plages de semaines qui se suivent, chacune `[première, dernière]`,
	 * dans l'ordre : huit semaines contiguës font une plage, deux semaines éloignées en font deux, et ce qui
	 * les sépare reste hors des bornes.
	 */
	public static function getPeriods(array $weeks): array {

		$weeks = array_unique($weeks);
		sort($weeks);

		$periods = [];

		foreach($weeks as $week) {

			$last = array_key_last($periods);

			if($last !== NULL and toWeek($periods[$last][1].' + 1 WEEK') === $week) {
				$periods[$last][1] = $week;
			} else {
				$periods[] = [$week, $week];
			}

		}

		return $periods;

	}

	/**
	 * Les interventions de l'année sur le modèle : à faire et prévues dans l'année, ou faites dans l'année,
	 * ou portant du temps ou une récolte dans l'année, sans les interventions à faire des séries clôturées,
	 * que l'écran ne montre pas (`\series\TaskLib::applyOpenSeries()`). `$todo` ne garde que les à faire.
	 * La recherche y lit aussi les cartes de l'année (`TaskSearchLib::getYear()`).
	 */
	public static function applyYear(\structure\Structure $eStructure, string $weekFirst, string $weekLast, bool $todo): void {

		$dates = self::getPeriodSql($weekFirst, $weekLast);

		$sqlTimesheet = \series\Timesheet::model()
			->select('task')
			->whereStructure($eStructure)
			->whereDate('BETWEEN', $dates)
			->getSql();

		$sqlHarvest = \series\Harvest::model()
			->select('task')
			->whereStructure($eStructure)
			->whereDate('BETWEEN', $dates)
			->getSql();

		\series\TaskLib::applyOpenSeries();

		\series\Task::model()
			->whereStructure($eStructure)
			->whereStatus(\series\Task::TODO, if: $todo)
			->or(
				fn() => $this
					->whereStatus(\series\Task::TODO)
					->wherePlannedWeek('BETWEEN', new \Sql(\series\Task::model()->format($weekFirst).' AND '.\series\Task::model()->format($weekLast))),
				fn() => $this
					->whereStatus(\series\Task::DONE)
					->whereDoneWeek('BETWEEN', new \Sql(\series\Task::model()->format($weekFirst).' AND '.\series\Task::model()->format($weekLast))),
				fn() => $this->whereId('IN', $sqlTimesheet),
				fn() => $this->whereId('IN', $sqlHarvest),
			);

	}

}
?>
