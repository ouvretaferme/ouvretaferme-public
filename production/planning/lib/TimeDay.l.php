<?php
namespace planning;

/**
 * La fiche de temps (L6b) : la journée d'une personne, un jour de la semaine, avec son temps de travail
 * (`\hr\WorkingTime`), le temps qu'elle a passé sur les interventions ce jour-là (`\series\Timesheet`) et
 * les interventions de la semaine, celles du jour d'abord, le reste de la semaine dessous (reprise f de la
 * maquette, « la fiche de temps doit inclure toutes les interventions pour saisir son temps facilement »).
 *
 * Ce Lib ne lit rien lui-même (charte, point 10 a) : les interventions sont celles de la Semaine
 * (`TaskWeekLib::get()`, que la page lit une fois pour la fiche), le temps par intervention, jour et personne celui du Jour
 * (`TaskDayLib::getDates()`), la journée celle de `\hr\WorkingTimeLib::getByWeek()`. Les trois lectures
 * portent sur la semaine entière : la rangée des jours dit la journée de chaque jour, et passer d'un jour à
 * l'autre ne change que ce que l'on range. Une quatrième (`TimeWeekLib::getActionsByDate()`),
 * donne le temps du jour par action, la barre des interventions. Les deux zones sous la liste (CF) sont
 * celles des Interventions, lues par leur Lib (`TaskLateLib`).
 */
class TimeDayLib {

	/**
	 * Les personnes de la fiche, une par onglet : celles qui ont une présence dans la semaine, comme le
	 * planning actuel (`hr/page/workingTime.p.php`), et la personne connectée, qui a toujours sa fiche
	 * même sans présence. Seules restent celles dont on peut lire la journée (`WorkingTime::canRead()`) :
	 * un droit limité au planning n'a que son onglet (charte, point 5).
	 *
	 * Chacune porte ses absences dans la structure (`cAbsence`, `\hr\AbsenceLib::fillUsers()`), que la
	 * fiche lit jour par jour (`\hr\Absence::isDateAbsent()`), comme l'ancien planning (R-Retours-1210, FH).
	 * Quand la journée se saisit (`\structure\Structure::hasWorkingTime()`), chacune porte aussi ses périodes dans
	 * l'équipe (`cPresence`), qui disent les jours où sa journée manque (`TimeWeekLib::getDay()`, `unfilled`).
	 */
	public static function getUsers(\structure\Structure $eStructure, string $week): \Collection {

		$cUser = \structure\MemberLib::getUsersByStructureForPeriod($eStructure, week_date_starts($week), week_date_ends($week));

		$eUserOnline = \user\ConnectionLib::getOnline();

		if($cUser->offsetExists($eUserOnline['id']) === FALSE) {

			$cUser[$eUserOnline['id']] = $eUserOnline;

			$cUser->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

		}

		$cUserTime = $cUser->find(fn(\user\User $eUser) => new \hr\WorkingTime([
			'structure' => $eStructure,
			'user' => $eUser,
		])->canRead());

		// Ce que la fiche montre d'une absence, c'est qu'une personne est absente, jamais pourquoi : `fillUsers()` ne
		// lit pas le motif, et le reste de l'équipe voit les absents de chaque jour comme au planning.
		\hr\AbsenceLib::fillUsers($eStructure, $cUserTime, week_date_starts($week), week_date_ends($week));

		if($eStructure->hasWorkingTime()) {
			\hr\PresenceLib::fillUsers($eStructure, $cUserTime);
		}

		return $cUserTime;

	}

	/**
	 * Si l'on peut écrire la journée d'une personne un jour de la fiche, sur sa propre fiche comme sur une
	 * ligne de l'équipe. Un jour à venir n'a pas de journée (`WorkingTime::date.check`), ni un jour hors des périodes
	 * de présence de la personne (`WorkingTime::date.outOfPresence`) : sa tuile se lit sans s'ouvrir. `$eUser` porte
	 * toutes ses périodes (`cPresence`, `getUsers()`), que seul le temps de travail lit.
	 */
	public static function canWorking(\structure\Structure $eStructure, \user\User $eUser, string $date): bool {

		return (
			$eStructure->hasWorkingTime() and
			$date <= \Clock::date() and
			new \hr\WorkingTime(['structure' => $eStructure, 'user' => $eUser])->canWrite() and
			\hr\Presence::isDateWorkable($eUser['cPresence'], $date)
		);

	}

	/**
	 * Le jour ouvert par défaut : aujourd'hui dans la semaine courante, le lundi dans une autre, comme la
	 * maquette (`jourFicheDefaut()`).
	 */
	public static function getDefaultDate(string $week): string {

		return ($week === \Clock::week()) ? \Clock::date() : week_date_starts($week);

	}

	/**
	 * Une semaine entièrement passée s'ouvre sur la semaine choisie plutôt que sur son lundi (R-Retours-1210,
	 * FP, retour 420) : on y revient pour relire ce qui a été fait, non pour saisir un jour.
	 */
	public static function isWeeklyDefault(string $week): bool {

		return ($week < \Clock::week());

	}

	/**
	 * La fiche d'une personne un jour de la semaine :
	 * - `workingTimes` : sa journée, par date, `NULL` quand elle n'est pas saisie ;
	 * - `times` : son temps sur les interventions, par date ;
	 * - `days` : chaque jour de la semaine, sa journée, ses interventions et l'absence de la personne, comme
	 *   un jour de l'équipe (`TimeWeekLib::getDay()`), que la rangée des jours dessine (`TimeWeekUi::getDay()`).
	 *   `$eUser` est une personne de `getUsers()`, qui porte ses absences. Chacun dit en plus si sa journée
	 *   s'écrit (`canWorking`, `canWorking()`), qu'un jour du suivi RH seul lit pour s'ouvrir sous sa pastille ;
	 * - `cWorkingTime` : sa journée saisie, par date, un élément vide pour chaque jour à saisir, que la saisie
	 *   d'un jour du suivi RH seul ouvre sur ses horaires ;
	 * - `total` : la somme de ces sept jours (`TimeWeekLib::getTotal()`), lue comme eux hors masque et hors
	 *   filtre ;
	 * - `day` : le jour de la fiche, pris dans `days`, que sa réglette lit ;
	 * - `eWorkingTime` : la journée saisie du jour de la fiche, vide à saisir, dont une structure à horaires
	 *   saisit les horaires ;
	 * - `actions` : son temps du jour par action (`TimeWeekLib::getActionsByDate()`), la barre des interventions ;
	 * - `cTaskSpent` : les interventions où elle a du temps ce jour-là (`getTasksSpent()`), le détail de la carte ;
	 * - `timeSpent` : la somme des lignes de ce détail, son pied. Ce n'est pas le temps du jour de la barre, qui
	 *   compte aussi une intervention sans ligne (`getTasksSpent()`) : un pied se lit comme la somme de ce qu'il
	 *   termine, comme `total` dans `getTasksWeek()` ;
	 * - `tasksWeek` : les interventions où elle a du temps dans la semaine (`getTasksWeek()`), le tableau de la
	 *   semaine choisie ;
	 * - `ccTaskWeek` : les interventions de la semaine, par action, les jours dans l'ordre et sans jour à la
	 *   fin, celles du jour comprises, à leur place (R-Retours-0710, 164) ;
	 * - `hidden` : « Masquer ce qui est fait » ou « Uniquement assigné à moi » cache au moins une ligne, ce
	 *   que dit l'état vide ;
	 * - `commented` : une ligne de la fiche porte un commentaire, ce qui ouvre leur colonne. Les commentaires
	 *   viennent de la semaine (`cComment`, `\series\TaskLib::getForPlanning()`), sans autre lecture.
	 *
	 * Chaque ligne porte le temps de la personne ce jour-là (`timeDay`), qui est ce que son champ écrit, son
	 * jour dans la semaine (`dayWeek`), seul à la ranger dans son groupe, et `actionFirst` sur la première de
	 * son action.
	 *
	 * La fiche liste toutes les interventions de la semaine, quelle que soit la personne à qui elles sont
	 * prévues (retour 251) : on y saisit son temps sur une intervention prévue pour un autre. « Uniquement
	 * assigné à moi » (`$mine`) ne garde que les interventions qui reviennent à la personne de la fiche
	 * (`isAssigned()`). Il ne joue que sur la liste : le temps du jour, la barre et le détail disent ce que
	 * la personne a fait, quel que soit le filtre.
	 *
	 * Le masque retire ce qui est fait, comme la Semaine, que la personne y ait du temps ce jour-là ou non
	 * (retour 287) : ce temps reste compté dans la journée, la barre et le détail, que le masque ne touche
	 * pas. `$eTaskKept` est l'intervention qu'un geste vient de toucher : elle échappe au masque, et garde
	 * la place que l'écran lui donnait (`$dayWeekKept`), même quand le geste la sort de la fiche ou la
	 * ferait changer de jour, jusqu'au rechargement, comme une carte cochée de la Semaine (R-Place : un
	 * geste ne déplace pas la ligne sous le doigt).
	 *
	 * Une ligne gardée qui était dans une zone (`$zoneKept`, CF) y reste de même : le geste l'a fait entrer
	 * dans la semaine, que le temps ou la coche lui donnent, mais elle ne quitte pas la liste de la semaine
	 * pour sa zone avant le rechargement. Elle est rendue à part (`eTaskZoneKept`), avec `$actionFirstKept`,
	 * le rang qu'elle avait dans son action. Vide quand la semaine relue ne la porte pas : la page la lit
	 * alors à part (`TaskLateLib::getTaskKept()`).
	 *
	 * `$cTaskCreated` sont les interventions que « Nouvelle intervention » vient de créer depuis la fiche
	 * (`timeDay:doCreate`, retour 293) : elles échappent au masque et à « Uniquement assigné à moi », à leur
	 * propre jour, pour que l'on voie ce que l'on vient d'écrire.
	 */
	public static function get(\structure\Structure $eStructure, string $week, \Collection $cTask, string $date, \user\User $eUser, bool $hide, bool $mine, \series\Task $eTaskKept = new \series\Task(), ?string $dayWeekKept = NULL, ?string $zoneKept = NULL, bool $actionFirstKept = FALSE, \Collection $cTaskCreated = new \Collection()): array {

		// Le suivi RH ouvre la journée sans le temps des interventions : celui qu'un temps de travail éteint a laissé
		// en base ne se lit plus, et la page ne passe alors aucune intervention.
		$time = $eStructure->hasTime();

		$dates = $time ? TaskDayLib::getDates($eStructure, $week) : ['times' => []];

		$cWorkingTime = \hr\WorkingTimeLib::getByWeek($eStructure, $eUser, $week);

		$workingTimes = [];
		$times = [];

		foreach(week_dates($week) as $weekDate) {
			$workingTimes[$weekDate] = $cWorkingTime->offsetExists($weekDate) ? $cWorkingTime[$weekDate]['time'] : NULL;
			$times[$weekDate] = 0.0;
		}

		foreach($dates['times'] as $timesByDate) {

			foreach($timesByDate as $timeDate => $timesByUser) {
				$times[$timeDate] += $timesByUser[$eUser['id']] ?? 0.0;
			}

		}

		$tasksCreated = $cTaskCreated->getIds();

		$ccTaskWeek = new \Collection();
		$eTaskZoneKept = new \series\Task();
		$hidden = FALSE;
		$commented = FALSE;

		foreach($cTask as $eTask) {

			$timeDay = $dates['times'][$eTask['id']][$date][$eUser['id']] ?? 0.0;
			$kept = $eTask->is($eTaskKept);
			$created = in_array($eTask['id'], $tasksCreated, TRUE);

			if($kept and $zoneKept !== NULL) {
				$eTaskZoneKept = self::getZoneLine($eTask, $timeDay, $actionFirstKept);
				continue;
			}

			if($kept) {
				$dayWeek = $dayWeekKept;
			} else if($created or $mine === FALSE or self::isAssigned($eTask, $eUser)) {
				$dayWeek = self::getDayWeek($eTask, $week);
			} else {
				$hidden = TRUE;
				continue;
			}

			if($hide and $eTask->isDone() and $kept === FALSE and $created === FALSE) {
				$hidden = TRUE;
				continue;
			}

			if($eTask['cComment']->notEmpty()) {
				$commented = TRUE;
			}

			$eTaskLine = clone $eTask;
			$eTaskLine['timeDay'] = $timeDay;
			$eTaskLine['dayWeek'] = $dayWeek;
			$eTaskLine['actionFirst'] = FALSE;

			$action = $eTask['action']['id'];

			if($ccTaskWeek->offsetExists($action) === FALSE) {
				$ccTaskWeek[$action] = new \Collection();
			}

			$ccTaskWeek[$action][$eTask['id']] = $eTaskLine;

		}

		// Le tri est stable : deux lignes du même jour gardent l'ordre de la Semaine. La première ligne d'une
		// action porte son nom dans sa colonne (R-Retours-0810, 211).
		foreach($ccTaskWeek as $cTaskWeek) {
			$cTaskWeek->sort(fn(\series\Task $e1, \series\Task $e2): int => ($e1['dayWeek'] ?? '9999-99-99') <=> ($e2['dayWeek'] ?? '9999-99-99'));
			$cTaskWeek->first()['actionFirst'] = TRUE;
		}

		$days = [];
		$cWorkingTimeWeek = new \Collection();

		foreach(week_dates($week) as $weekDate) {
			$days[$weekDate] = TimeWeekLib::getDay($weekDate, $workingTimes[$weekDate], $times[$weekDate], \hr\Absence::isDateAbsent($eUser['cAbsence'], $weekDate), \hr\Presence::isDatePresent($eUser['cPresence'], $weekDate));
			$days[$weekDate]['canWorking'] = self::canWorking($eStructure, $eUser, $weekDate);
			$cWorkingTimeWeek[$weekDate] = $cWorkingTime->offsetExists($weekDate) ? $cWorkingTime[$weekDate] : new \hr\WorkingTime();
		}

		$cTaskSpent = self::getTasksSpent($cTask, $dates['times'], $date, $eUser);

		return [
			'workingTimes' => $workingTimes,
			'times' => $times,
			'days' => $days,
			'total' => TimeWeekLib::getTotal($days),
			'day' => $days[$date],
			'cWorkingTime' => $cWorkingTimeWeek,
			'eWorkingTime' => $cWorkingTimeWeek[$date],
			'actions' => $time ? TimeWeekLib::getActionsByDate($eStructure, $eUser, $week)[$date] : [],
			'cTaskSpent' => $cTaskSpent,
			'timeSpent' => $cTaskSpent->sum('timeDay'),
			'tasksWeek' => self::getTasksWeek($cTask, $dates['times'], $eUser),
			'ccTaskWeek' => $ccTaskWeek,
			'eTaskZoneKept' => $eTaskZoneKept,
			'hidden' => $hidden,
			'commented' => $commented,
		];

	}

	/**
	 * La semaine en champs d'une personne (planche rh-heures-pistes, piste B, 19/10), ce que la rangée de
	 * saisie et sa colonne de total lisent :
	 * - `warnings` : par date, si le jour est à saisir. C'est `unfilled` (`TimeWeekLib::getDay()`), borné aux jours
	 *   du contrat (`$contractDays`, `getContractDays()`) quand il en coche. Un jour hors contrat reste saisissable
	 *   mais n'est pas compté, comme dans le récapitulatif, pour la personne comme pour son gérant (19/10).
	 *   Sans jours du contrat, sans contrat, avec des jours variables ou pour un collègue qui ouvre la fiche, le
	 *   jour reste celui de `unfilled` : chaque jour passé sans journée, samedi et dimanche compris ;
	 * - `missing` : leur nombre ;
	 * - `hours` : l'horaire d'un contrat à la semaine, `NULL` sans contrat lu, sans horaire, ou annualisé, qui
	 *   compte sur sa période et n'a rien à comparer à une semaine ;
	 * - `done` et `todo` : les parts de la jauge sur cet horaire, en pourcentage, la semaine saisie puis les jours à
	 *   saisir, comptés chacun à l'horaire réparti sur les jours du contrat, du lundi au vendredi pour des jours
	 *   variables, comme le compteur d'un annualisé (`\hr\OvertimeLib`). Une semaine au-delà de l'horaire remplit
	 *   la jauge sans la dépasser.
	 *
	 * `$eContract` est vide quand la page ne peut pas le lire (`\hr\Contract::canRead()`) : l'horaire est une clause
	 * qui ne se montre pas hors de la gérance, les jours en sont tirés à part pour la personne.
	 *
	 * @param int[] $contractDays
	 */
	public static function getEntry(array $days, array $contractDays, \hr\Contract $eContract): array {

		$warnings = [];

		foreach($days as $date => $day) {
			// La règle de `\hr\Contract::isDayWorked()`, sur les seuls jours : le contrat entier ne descend pas jusqu'ici
			// pour qui ne le lit pas.
			$warnings[$date] = ($day['unfilled'] and ($contractDays === [] or in_array((int)date('N', strtotime($date)), $contractDays, TRUE)));
		}

		$missing = count(array_filter($warnings));

		$hours = (
			$eContract->notEmpty() and
			$eContract['hoursPeriod'] === \hr\Contract::WEEK and
			$eContract['weeklyHours'] > 0.0
		) ? $eContract['weeklyHours'] : NULL;

		if($hours === NULL) {

			$done = NULL;
			$todo = NULL;

		} else {

			$perDay = $hours / count($contractDays ?: self::getContractDays(\hr\ContractLib::getNew()));

			$done = min(100.0, round(100 * (TimeWeekLib::getTotal($days)['working'] ?? 0.0) / $hours, 2));
			$todo = min(100.0 - $done, round(100 * $missing * $perDay / $hours, 2));

		}

		return [
			'warnings' => $warnings,
			'missing' => $missing,
			'hours' => $hours,
			'done' => $done,
			'todo' => $todo,
		];

	}

	/**
	 * Les jours de la semaine (1 pour lundi à 7 pour dimanche) que coche le contrat, la seule part du contrat que
	 * lit la semaine en champs de qui n'a pas le contrat entier. Aucun sans contrat, et aucun pour des jours
	 * variables, qui n'en attendent aucun en particulier.
	 *
	 * @return int[]
	 */
	public static function getContractDays(\hr\Contract $eContract): array {

		if(
			$eContract->empty() or
			$eContract['hasVariableDays'] or
			$eContract['days'] === NULL
		) {
			return [];
		}

		return array_values(array_filter(range(1, 7), fn(int $weekday) => $eContract['days']->position($weekday - 1)));

	}

	/**
	 * Les deux zones sous la liste (CF, retour 292), celles des Interventions (`TaskLateLib`) : « En retard »,
	 * dépliée, puis « Non planifié », repliée et lue à sa première ouverture (`$unplanned`, que le client
	 * réémet), avec le même plafond. Sans catégorie : la fiche n'en filtre pas. « Uniquement assigné à moi »
	 * les filtre comme la liste ; « Masquer ce qui est fait » n'a rien à y retirer, une zone ne rangeant que
	 * de l'à faire. `$folded` est la liste des zones montrées que la personne a repliées, `$expanded` les
	 * actions que leur « Voir les N autres » a dépliées, par zone (`expandZone()`).
	 *
	 * Chaque zone porte en plus ses lignes, par action dans l'ordre des blocs (`ccTaskLine`), et si l'une
	 * d'elles est commentée (`commented`), la variante de sa table.
	 */
	public static function getZones(\structure\Structure $eStructure, string $week, \user\User $eUser, bool $mine, bool $unplanned, array $folded, array $expanded): array {

		return [
			TaskLateLib::LATE => self::getZone($eStructure, $week, $eUser, $mine, TaskLateLib::LATE, TRUE, $folded, $expanded[TaskLateLib::LATE]),
			TaskLateLib::UNPLANNED => self::getZone($eStructure, $week, $eUser, $mine, TaskLateLib::UNPLANNED, $unplanned, $folded, $expanded[TaskLateLib::UNPLANNED]),
		];

	}

	/**
	 * Une seule zone, pour une réponse qui ne rend qu'elle (« Non planifié » à sa première ouverture, « Voir
	 * les N autres ») : l'autre n'a pas à être relue ni hydratée, comme aux Interventions.
	 */
	public static function getZone(\structure\Structure $eStructure, string $week, \user\User $eUser, bool $mine, string $name, bool $loaded, array $folded, array $expanded): array {

		$zone = self::getZoneKeys($eStructure, $week, $eUser, $mine, $name, $loaded);

		self::fillZone($eStructure, $week, $zone, $folded, $expanded);

		return $zone;

	}

	/**
	 * Une zone dont on a lu les clés, prête à se rendre entière : ses blocs dépliés, ses cartes, ses lignes et
	 * son pli.
	 */
	public static function fillZone(\structure\Structure $eStructure, string $week, array &$zone, array $folded, array $expanded): void {

		self::expandZone($zone, $expanded);

		TaskLateLib::fill($eStructure, $week, $zone);

		$zone['open'] = ($zone['loaded'] and in_array($zone['zone'], $folded, TRUE) === FALSE);

		self::fillZoneLines($zone);

	}

	/**
	 * Les clés d'une zone, sans aucune ligne hydratée : « Voir les N autres » n'hydrate que son bloc
	 * (`TaskLateLib::fillBlock()`), comme aux Interventions.
	 */
	public static function getZoneKeys(\structure\Structure $eStructure, string $week, \user\User $eUser, bool $mine, string $name, bool $loaded): array {
		return TaskLateLib::getZone($eStructure, $name, $week, $loaded, $mine ? $eUser : new \user\User());
	}

	/**
	 * Montre en entier les blocs plafonnés des actions données, sans les hydrater : ceux que la personne a
	 * dépliés par leur « Voir les N autres ». Le serveur ne garde pas ce qu'elle a déplié, que l'écran réémet
	 * (`expanded`) pour qu'une ligne qui entre dans un bloc ou en sort s'y place comme l'écran le montre. Une
	 * action qui n'est plus plafonnée dans la zone, ou n'y est plus, n'est plus dépliée (`expanded`) : c'est ce
	 * que montre un écran ramené à la base, pas celui que « Voir les N autres » vient d'allonger (`timeDay:more`).
	 */
	public static function expandZone(array &$zone, array $actions): void {

		$zone['expanded'] = [];

		if($zone['loaded'] === FALSE) {
			return;
		}

		foreach($actions as $action) {

			if(
				array_key_exists($action, $zone['blocks']) and
				count($zone['blocks'][$action]['shown']) < count($zone['blocks'][$action]['ids'])
			) {
				$zone['blocks'][$action]['shown'] = $zone['blocks'][$action]['ids'];
				$zone['expanded'][] = $action;
			}

		}

	}

	/**
	 * Les lignes d'une zone, celles que ses blocs montrent, aucune pour une zone que l'écran ne montre pas.
	 * Le temps du jour est nul : une intervention qui a du temps dans la semaine est dans la semaine, jamais
	 * dans une zone (`TaskLateLib::getKeys()`).
	 */
	public static function fillZoneLines(array &$zone): void {

		$zone['ccTaskLine'] = new \Collection();
		$zone['commented'] = FALSE;

		if($zone['loaded'] === FALSE) {
			return;
		}

		foreach($zone['blocks'] as $action => $block) {

			$cTaskLine = new \Collection();

			foreach($block['shown'] as $position => $id) {

				$eTaskLine = self::getZoneLine($zone['cTask'][$id], 0.0, $position === 0);

				if($eTaskLine['cComment']->notEmpty()) {
					$zone['commented'] = TRUE;
				}

				$cTaskLine[$id] = $eTaskLine;

			}

			if($cTaskLine->notEmpty()) {
				$zone['ccTaskLine'][$action] = $cTaskLine;
			}

		}

	}

	/**
	 * Les lignes d'un bloc au-delà du plafond, que « Voir les N autres » ajoute sous celles qui se montraient
	 * (`TaskLateLib::fillBlock()`, qui les hydrate). Une ligne que l'écran retient dans le bloc (`$retained`,
	 * celles de la zone) y tient une des `LIMIT` places sans plus être du bloc : le reste commence d'autant
	 * plus tôt.
	 */
	public static function getZoneRest(array $zone, int $action, array $retained): \Collection {

		$ids = $zone['blocks'][$action]['ids'];

		$leaving = array_filter($retained, fn(array $line) => ($line['action'] === $action and in_array($line['task'], $ids, TRUE) === FALSE));

		$cTaskLine = new \Collection();

		foreach(array_slice($ids, TaskLateLib::LIMIT - count($leaving)) as $id) {
			$cTaskLine[$id] = self::getZoneLine($zone['cTask'][$id], 0.0, FALSE);
		}

		return $cTaskLine;

	}

	/**
	 * La ligne d'une intervention de zone : sans jour dans la semaine, la zone disant déjà ce qu'elle range.
	 */
	public static function getZoneLine(\series\Task $eTask, float $timeDay, bool $actionFirst): \series\Task {

		$eTaskLine = clone $eTask;
		$eTaskLine['timeDay'] = $timeDay;
		$eTaskLine['dayWeek'] = NULL;
		$eTaskLine['actionFirst'] = $actionFirst;

		return $eTaskLine;

	}

	/**
	 * Les interventions où une personne a du temps un jour donné, chacune avec ce temps (`timeDay`), dans l'ordre
	 * de la barre des interventions (`TimeWeekLib::getActionsByDate()`) : l'action au plus long temps d'abord,
	 * puis par nom, et dans une action la plus longue intervention d'abord. Le détail se lit ainsi de gauche à
	 * droite comme la barre au-dessus de lui. À durée égale, l'intervention créée la première passe devant : la
	 * fiche et l'équipe ne lisent pas les interventions dans le même ordre, et doivent ranger la même journée de
	 * la même façon.
	 *
	 * `$times` est indexé comme celui de `TaskDayLib::getDates()`. Une intervention à faire d'une série close
	 * n'est pas dans `$cTask` (`\series\TaskLib::applyOpenSeries()`) : son temps compte dans le chiffre et la
	 * barre du jour, mais elle n'a de ligne ni dans la liste ni dans le détail.
	 *
	 * Les actions se rangent sur le temps des seules interventions du détail. La barre compte en plus ce temps
	 * d'une série close et celui d'une intervention supprimée : quand l'un d'eux pèse, deux actions peuvent
	 * s'y suivre dans l'autre ordre. Le détail reste rangé sur ce qu'il montre : rangée sur un temps sans ligne,
	 * une action y paraîtrait hors de sa place.
	 */
	public static function getTasksSpent(\Collection $cTask, array $times, string $date, \user\User $eUser): \Collection {

		$cTaskSpent = new \Collection();
		$actions = [];

		foreach($cTask as $eTask) {

			$timeDay = $times[$eTask['id']][$date][$eUser['id']] ?? 0.0;

			if($timeDay <= 0.0) {
				continue;
			}

			$eTaskSpent = clone $eTask;
			$eTaskSpent['timeDay'] = $timeDay;

			$cTaskSpent[$eTask['id']] = $eTaskSpent;

			$action = $eTask['action']['id'];
			$actions[$action] = ($actions[$action] ?? 0.0) + $timeDay;

		}

		$cTaskSpent->sort(fn(\series\Task $e1, \series\Task $e2): int => [$actions[$e2['action']['id']], $e1['action']['name'], $e1['action']['id'], $e2['timeDay'], $e1['id']] <=> [$actions[$e1['action']['id']], $e2['action']['name'], $e2['action']['id'], $e1['timeDay'], $e2['id']]);

		return $cTaskSpent;

	}

	/**
	 * Les interventions où une personne a du temps dans la semaine, que sa fiche montre quand la semaine est
	 * choisie à la place d'un jour (R-Retours-1210, 326) : par action dans l'ordre de la liste du jour
	 * (`ccTaskLine`), chaque ligne avec la somme de ses jours (`timeWeek`) et `actionFirst` sur la première de son
	 * action, puis la somme des lignes (`total`). Ni le masque ni « Uniquement assigné à moi » n'y jouent : le
	 * tableau dit ce que la personne a fait.
	 *
	 * `$times` est indexé comme celui de `TaskDayLib::getDates()`, qui ne porte que les jours de la semaine. Comme
	 * dans le détail du jour (`getTasksSpent()`), une intervention absente de `$cTask` n'a pas de ligne : `total`
	 * est la somme de ce que le tableau montre, pas celle de la semaine (`TimeWeekLib::getTotal()`).
	 */
	public static function getTasksWeek(\Collection $cTask, array $times, \user\User $eUser): array {

		$ccTaskLine = new \Collection();
		$total = 0.0;

		foreach($cTask as $eTask) {

			$timeWeek = array_sum(array_column($times[$eTask['id']] ?? [], $eUser['id']));

			if($timeWeek <= 0.0) {
				continue;
			}

			$action = $eTask['action']['id'];

			$eTaskLine = clone $eTask;
			$eTaskLine['timeWeek'] = $timeWeek;
			// Sans jour : la ligne vaut pour la semaine, aucun jour n'est à redire sous son nom.
			$eTaskLine['dayWeek'] = NULL;
			$eTaskLine['actionFirst'] = ($ccTaskLine->offsetExists($action) === FALSE);

			if($eTaskLine['actionFirst']) {
				$ccTaskLine[$action] = new \Collection();
			}

			$ccTaskLine[$action][$eTask['id']] = $eTaskLine;

			$total += $timeWeek;

		}

		return [
			'ccTaskLine' => $ccTaskLine,
			'total' => $total,
		];

	}

	/**
	 * Si une intervention revient à une personne, ce que garde « Uniquement assigné à » : prévue pour elle,
	 * ou portant de son temps sur la semaine, quel que soit le jour (retour 251). Le libellé se lit au mot :
	 * le temps d'un autre ne retire pas une intervention à la personne pour qui elle est prévue, et une
	 * intervention prévue pour personne n'est assignée à personne.
	 */
	public static function isAssigned(\series\Task $eTask, \user\User $eUser): bool {

		$users = array_keys(array_filter($eTask['times'], fn(float $time) => $time > 0.0));

		return (
			in_array($eUser['id'], $eTask['plannedUsers']) or
			in_array($eUser['id'], $users)
		);

	}

	/**
	 * Le jour d'une ligne dans la semaine, qui la range dans son groupe : celui du fait d'une faite, celui
	 * prévu d'une à faire quand elle est prévue dans la semaine affichée, `NULL` sinon. Une seule règle pour
	 * la page et pour le jour d'avant un geste que l'écran n'a pas réémis.
	 */
	public static function getDayWeek(\series\Task $eTask, string $week): ?string {

		if($eTask->isDone()) {
			return $eTask['doneDate'];
		}

		return ($eTask['plannedWeek'] === $week) ? $eTask['plannedDate'] : NULL;

	}

	/**
	 * La ligne d'une intervention dans la fiche, ou une intervention vide quand la fiche ne la montre pas.
	 */
	public static function getLine(array $timeDay, \series\Task $eTask): \series\Task {

		$action = $eTask['action']['id'];

		if(
			$timeDay['ccTaskWeek']->offsetExists($action) and
			$timeDay['ccTaskWeek'][$action]->offsetExists($eTask['id'])
		) {
			return $timeDay['ccTaskWeek'][$action][$eTask['id']];
		}

		return new \series\Task();

	}

	/**
	 * Ce qu'un geste sur une ligne redessine, décidé ici et appliqué tel quel par la vue (règle « une
	 * action ne renvoie que ce qui change ») : la ligne seule (`row`), qui garde sa place (`get()`,
	 * `$dayWeekKept`), ou la liste entière (`table`), quand une ligne commentée arrive dans une table
	 * affichée sans la colonne des commentaires, ou quand la semaine relue ne porte plus l'intervention.
	 * `$commented` est la variante que la table affiche, que le geste réémet : la fiche relue ne peut pas
	 * la dire, une table reste à trois colonnes jusqu'au rechargement quand sa dernière ligne commentée la
	 * quitte.
	 */
	public static function getChange(array $timeDay, \series\Task $eTask, bool $commented): string {

		$eTaskLine = self::getLine($timeDay, $eTask);

		if(
			$eTaskLine->empty() or
			($commented === FALSE and $eTaskLine['cComment']->notEmpty())
		) {
			return 'table';
		}

		return 'row';

	}

	/**
	 * Ce qu'un geste sur une ligne de zone redessine : la ligne seule, qui reste dans sa zone jusqu'au
	 * rechargement (R-Place), la ligne et la colonne des commentaires de sa table, quand elle arrive commentée
	 * dans une table qui n'en a pas (`variant`), ou son départ de la zone, quand l'intervention n'a plus de
	 * ligne à rendre, une série close entre-temps (`remove`).
	 */
	public static function getChangeZone(\series\Task $eTaskLine, bool $commented): string {

		if($eTaskLine->empty()) {
			return 'remove';
		}

		if($commented === FALSE and $eTaskLine['cComment']->notEmpty()) {
			return 'variant';
		}

		return 'row';

	}

	/*
	 * Ce qu'un geste change dans une zone, décidé ici et appliqué tel quel par la vue (règle « une action ne
	 * renvoie que ce qui change ») : jamais la zone entière, dont le poids croît avec les blocs que la personne
	 * a dépliés, mais des étapes sur les nœuds touchés (`steps`), dans l'ordre où elles s'appliquent.
	 *
	 * Le serveur ne garde pas l'écran, il le reconstitue : la zone d'avant le geste, lue en base, plus ce que
	 * l'écran réémet. Les blocs dépliés (`expanded`) montrent toutes leurs lignes, les autres leurs `LIMIT`
	 * premières au-delà du seuil. La variante de la table (`commented`) dit si une ligne commentée qui paraît
	 * doit d'abord ouvrir la colonne des commentaires. Les lignes retenues (`retained`) sont celles qu'un
	 * geste a gardées à leur place dans la zone (R-Place) : la base ne les y compte plus, l'écran les y montre
	 * encore, et elles en sortent dès que la zone change, comme au rechargement.
	 */

	/**
	 * Les étapes qui mènent une zone de ce que l'écran en montre à ce qu'un rechargement en montrerait, à la
	 * colonne des commentaires près : `$retained` sont ses lignes retenues, chacune avec son action et si elle
	 * ouvrait son bloc, `$eTaskArrival` la ligne que le geste y fait entrer depuis la semaine, vide quand il
	 * n'y en a pas. Sans étape quand rien n'y change, ou que « Uniquement assigné à moi » écarte la ligne.
	 *
	 * Les étapes ouvrent la colonne des commentaires (`variant`) et ne la referment jamais : refermer une
	 * colonne parce que sa dernière ligne commentée sort déplacerait toutes les lignes de la table sous le
	 * doigt pour un geste fait sur une seule. C'est la tolérance de la liste (`getChange()`), que la zone
	 * reprend telle quelle, et que le rechargement lève.
	 *
	 * Deux cas seulement rendent plus que les nœuds touchés : une zone qui n'était pas à l'écran y entre
	 * entière, avec sa seule ligne, et une zone qui franchit le seuil du plafond (`TaskLateLib::$threshold`)
	 * redessine sa table, dont aucun bloc n'est alors déplié.
	 */
	public static function getZoneSteps(\structure\Structure $eStructure, string $week, \user\User $eUser, bool $mine, string $name, bool $loaded, array $folded, array $expanded, bool $commented, array $retained, \series\Task $eTaskArrival = new \series\Task()): array {

		$zone = self::getZoneKeys($eStructure, $week, $eUser, $mine, $name, $loaded);

		$change = [
			'zone' => $name,
			'commented' => $commented,
			'expanded' => [],
			'steps' => [],
		];

		// Par identifiant : la liste réémise n'a pas de borne, et chaque ligne s'y cherche une fois.
		$positions = array_flip(TaskLateLib::getIds($zone));

		$arrival = ($eTaskArrival->notEmpty() and array_key_exists($eTaskArrival['id'], $positions)) ? $eTaskArrival['id'] : NULL;

		// Une ligne retenue que la base compte de nouveau dans la zone y est comme les autres.
		$leaving = [];
		$leavingCount = 0;

		foreach($retained as $line) {

			if(array_key_exists($line['task'], $positions) === FALSE or $line['task'] === $arrival) {
				$leaving[$line['action']][$line['task']] = $line['first'];
				$leavingCount++;
			}

		}

		$countScreen = $zone['count'] - (($arrival === NULL) ? 0 : 1) + $leavingCount;

		if($zone['count'] === 0) {

			if($countScreen > 0) {
				$change['steps'][] = ['type' => 'removeZone'];
			}

			return $change;

		}

		if($countScreen === 0) {
			self::fillZone($eStructure, $week, $zone, $folded, []);
			$change['steps'][] = ['type' => 'zone', 'zone' => $zone];
			return $change;
		}

		if($arrival === NULL and $leavingCount === 0) {
			return $change;
		}

		$change['steps'][] = ['type' => 'counter', 'count' => $zone['count']];

		if($zone['loaded'] === FALSE) {
			return $change;
		}

		// Le plafond de l'écran est celui du compte qu'il montrait. D'un côté ou de l'autre du seuil, aucun bloc
		// de la table redessinée n'est déplié.
		if(($countScreen > TaskLateLib::$threshold) !== $zone['capped']) {
			self::fillZone($eStructure, $week, $zone, $folded, []);
			$change['steps'][] = ['type' => 'board', 'zone' => $zone];
			return $change;
		}

		self::expandZone($zone, $expanded);

		$actions = array_keys($leaving);

		if($arrival !== NULL) {
			$actions[] = $eTaskArrival['action']['id'];
		}

		$steps = [];

		foreach(array_unique($actions) as $action) {
			$steps = array_merge($steps, self::getBlockSteps($zone, $action, $leaving[$action] ?? [], $arrival, in_array($action, $expanded, TRUE)));
		}

		$steps = self::fillZoneSteps($eStructure, $week, $zone, $steps);

		// Une ligne commentée qui paraît dans une table sans colonne des commentaires l'ouvre d'abord.
		if($commented === FALSE) {

			foreach($steps as $step) {

				if($step['type'] === 'insert' and $step['eTask']['cComment']->notEmpty()) {
					$change['commented'] = TRUE;
					array_unshift($steps, ['type' => 'variant']);
					break;
				}

			}

		}

		$change['steps'] = array_merge($change['steps'], $steps);
		$change['expanded'] = $zone['expanded'];

		return $change;

	}

	/**
	 * Les étapes d'un bloc : ses lignes retenues sortent (`$leaving`, par intervention, `TRUE` pour celle qui
	 * ouvrait le bloc), puis l'écran passe des lignes qu'il montrait à celles que le bloc montre. `$arrival`
	 * est la ligne qui entre dans la zone, que l'écran ne montrait pas, `$expandedScreen` dit si l'écran montre
	 * le bloc en entier.
	 */
	private static function getBlockSteps(array $zone, int $action, array $leaving, ?int $arrival, bool $expandedScreen): array {

		$name = $zone['zone'];

		$steps = [];

		foreach(array_keys($leaving) as $task) {
			$steps[] = ['type' => 'remove', 'task' => $task];
		}

		if(array_key_exists($action, $zone['blocks']) === FALSE) {
			$steps[] = ['type' => 'removeTitle', 'action' => $action];
			return $steps;
		}

		$block = $zone['blocks'][$action];
		$shown = $block['shown'];

		$idsBefore = array_values(array_filter($block['ids'], fn(int $id) => $id !== $arrival));

		// Un bloc plafonné que son « Voir les N autres » n'a pas déplié montre `LIMIT` lignes, celles qui sortent
		// comprises : une ligne n'est retenue que par un geste, qui ne se fait que sur une ligne montrée.
		if($zone['capped'] and $expandedScreen === FALSE) {
			$screen = array_slice($idsBefore, 0, TaskLateLib::LIMIT - count($leaving));
			$restScreen = max(0, count($idsBefore) + count($leaving) - TaskLateLib::LIMIT);
		} else {
			$screen = $idsBefore;
			$restScreen = 0;
		}

		if($idsBefore === [] and $leaving === []) {
			$steps[] = self::getZoneTitle($zone, $action);
		}

		// La ligne qui ouvrait le bloc en sort : la première qui reste l'ouvre.
		if(in_array(TRUE, $leaving, TRUE) and in_array($shown[0], $screen, TRUE)) {
			$steps[] = ['type' => 'row', 'task' => $shown[0], 'first' => TRUE];
		}

		foreach($shown as $position => $id) {

			if(in_array($id, $screen, TRUE)) {
				continue;
			}

			$steps[] = [
				'type' => 'insert',
				'task' => $id,
				'first' => ($position === 0),
				'after' => ($position === 0) ? 'planning-time-'.$name.'-action-'.$action : 'planning-time-task-'.$shown[$position - 1]
			];

		}

		// La ligne qui ouvrait le bloc passe derrière celle qui vient d'y entrer en tête.
		if(
			$screen !== [] and
			in_array($shown[0], $screen, TRUE) === FALSE and
			in_array($screen[0], $shown, TRUE)
		) {
			$steps[] = ['type' => 'row', 'task' => $screen[0], 'first' => FALSE];
		}

		// Le bloc plafonné garde ses `LIMIT` premières lignes : les autres passent derrière « Voir les N autres ».
		foreach(array_diff($screen, $shown) as $id) {
			$steps[] = ['type' => 'remove', 'task' => $id];
		}

		$rest = count($block['ids']) - count($shown);

		// « Voir les N autres » est retiré quand il ne reste rien derrière, posé après la dernière ligne quand il
		// n'était pas à l'écran, remplacé sinon.
		if($rest !== $restScreen) {

			$steps[] = [
				'type' => 'more',
				'zone' => $name,
				'action' => $action,
				'rest' => $rest,
				'after' => ($restScreen === 0) ? 'planning-time-task-'.$shown[count($shown) - 1] : NULL
			];

		}

		return $steps;

	}

	/**
	 * Une ligne de zone qui arrive commentée dans une table sans colonne des commentaires : la colonne s'ouvre,
	 * puis la ligne se rend dans cette variante.
	 */
	public static function getZoneVariant(string $name, array $expanded, \series\Task $eTaskLine): array {

		return [
			'zone' => $name,
			'commented' => TRUE,
			'expanded' => $expanded[$name],
			'steps' => [
				['type' => 'variant'],
				['type' => 'row', 'eTask' => $eTaskLine],
			],
		];

	}

	/**
	 * L'intitulé d'un bloc qui paraît, avant celui du bloc qui le suit dans la zone, ou au bout de la table
	 * pour le dernier.
	 */
	private static function getZoneTitle(array $zone, int $action): array {

		$actions = array_keys($zone['blocks']);
		$index = array_search($action, $actions, TRUE);

		$step = [
			'type' => 'title',
			'eAction' => $zone['blocks'][$action]['eAction']
		];

		if($index === count($actions) - 1) {
			$step['anchor'] = 'planning-time-'.$zone['zone'].'-board';
			$step['position'] = 'beforeend';
		} else {
			$step['anchor'] = 'planning-time-'.$zone['zone'].'-action-'.$actions[$index + 1];
			$step['position'] = 'beforebegin';
		}

		return $step;

	}

	/**
	 * Les lignes que les étapes rendent, hydratées en une lecture.
	 */
	private static function fillZoneSteps(\structure\Structure $eStructure, string $week, array &$zone, array $steps): array {

		$ids = [];

		foreach($steps as $step) {
			if(in_array($step['type'], ['insert', 'row'], TRUE)) {
				$ids[] = $step['task'];
			}
		}

		$zone['cTask'] = TaskLateLib::getTasks($eStructure, $zone['zone'], $week, $ids);

		foreach($steps as $key => $step) {
			if(in_array($step['type'], ['insert', 'row'], TRUE)) {
				$steps[$key]['eTask'] = self::getZoneLine($zone['cTask'][$step['task']], 0.0, $step['first']);
			}
		}

		return $steps;

	}

}
?>
