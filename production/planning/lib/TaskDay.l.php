<?php
namespace planning;

/**
 * Le Jour de l'écran « Interventions » (L5c) : la semaine rangée en colonnes, « Sans jour » d'abord,
 * puis une colonne par jour, comme le planning actuel et la maquette navigable (`jourColonnes()`).
 *
 * Les interventions sont celles de la Semaine (`TaskWeekLib::get()`) : les cartes et les comptes disent
 * donc la même chose d'une période à l'autre. Ce Lib n'ajoute que ce qui range une intervention dans un
 * jour, lu en deux requêtes sur la semaine : le temps passé par jour et les récoltes par jour.
 *
 * La règle du jour est celle du planning actuel (`\series\TaskLib::getForDaily()`, appelée sans
 * personne) : une intervention va dans la colonne de son jour prévu, de son jour fait, et de chaque jour
 * où elle porte du temps ou une récolte. Elle peut donc avoir une carte dans plusieurs colonnes. Une
 * faite sans aucun de ces jours ne va dans aucune colonne, comme au planning actuel (Q2 de L5c,
 * tranchée par V-Planning) : 126 113 des 188 732 faites de 2026 n'ont pas de jour fait, dont environ
 * 86 000 sans temps (mesuré le 01/10).
 *
 * « Sans jour » porte les interventions à faire prévues dans la semaine sans jour.
 */
class TaskDayLib {

	/**
	 * La clé de la colonne « Sans jour », à côté des dates qui nomment les autres.
	 */
	const NONE = 'none';

	/**
	 * Les clés des colonnes de la semaine, dans l'ordre de l'écran : « Sans jour », puis les sept dates.
	 */
	public static function getColumnKeys(string $week): array {

		return array_merge([self::NONE], week_dates($week));

	}

	/**
	 * Le temps passé sur chaque intervention de la semaine, par jour et par personne, et sa récolte de chaque
	 * jour. Deux lectures sur la semaine, chacune servie par un index en tête `(structure, date)` ou
	 * `(structure, week)` (`series.yml`), sans relire les interventions.
	 *
	 * @return array `['times' => [tâche][date][personne] => heures, 'harvests' => [tâche][date] => quantité]`
	 */
	public static function getDates(\structure\Structure $eStructure, string $week): array {

		$times = [];

		$cTimesheet = \series\Timesheet::model()
			->select([
				'task', 'date', 'user',
				'total' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->whereDate('BETWEEN', new \Sql(\series\Timesheet::model()->format(week_date_starts($week)).' AND '.\series\Timesheet::model()->format(week_date_ends($week))))
			->group(['task', 'date', 'user'])
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['task']['id']][$eTimesheet['date']][$eTimesheet['user']['id']] = $eTimesheet['total'];
		}

		$harvests = [];

		// Le planning actuel ne date une récolte que si sa quantité ne s'annule pas (`TaskLib::fillHarvestDates()`).
		$cHarvest = \series\Harvest::model()
			->select([
				'task', 'date',
				'total' => new \Sql('SUM(quantity)', 'float')
			])
			->whereStructure($eStructure)
			->whereWeek($week)
			->group(['task', 'date'])
			->having('total != 0.0')
			->getCollection();

		foreach($cHarvest as $eHarvest) {
			$harvests[$eHarvest['task']['id']][$eHarvest['date']] = $eHarvest['total'];
		}

		return [
			'times' => $times,
			'harvests' => $harvests,
		];

	}

	/**
	 * Une intervention va dans « Sans jour » quand elle est à faire dans la semaine sans jour prévu : ses
	 * seuls champs le disent, sans temps ni récolte.
	 */
	public static function isNone(\series\Task $eTask, string $week): bool {

		return (
			$eTask->isTodo() and
			$eTask['plannedWeek'] === $week and
			$eTask['plannedDate'] === NULL
		);

	}

	/**
	 * Les colonnes où va une intervention, dans l'ordre de l'écran. Une seule règle pour la page et pour
	 * ce qu'un geste redessine (`getKeysForTask()`), sans quoi la réponse ciblée et le rechargement
	 * pourraient ranger la même carte ailleurs.
	 */
	public static function getKeys(\series\Task $eTask, string $week, array $dates): array {

		$keys = [];

		if(self::isNone($eTask, $week)) {
			$keys[] = self::NONE;
		}

		foreach(week_dates($week) as $date) {

			if(
				$eTask['plannedDate'] === $date or
				$eTask['doneDate'] === $date or
				isset($dates['times'][$eTask['id']][$date]) or
				isset($dates['harvests'][$eTask['id']][$date])
			) {
				$keys[] = $date;
			}

		}

		return $keys;

	}

	/**
	 * Le temps passé sur une intervention dans la semaine : toutes personnes confondues, celui que sa carte
	 * affiche à la Semaine (`TaskWeekLib::get()`, `timePeriod`), ou celui d'une personne, lu pour elle seule avant un geste.
	 */
	public static function getTimeForTask(\structure\Structure $eStructure, string $week, \series\Task $eTask, \user\User $eUser = new \user\User()): float {

		return (float)\series\Timesheet::model()
			->whereStructure($eStructure)
			->whereTask($eTask)
			->whereUser($eUser, if: $eUser->notEmpty())
			->whereDate('BETWEEN', new \Sql(\series\Timesheet::model()->format(week_date_starts($week)).' AND '.\series\Timesheet::model()->format(week_date_ends($week))))
			->getValue(new \Sql('SUM(time)', 'float'));

	}

	/**
	 * Les colonnes où se trouve une intervention avant un geste, lues pour elle seule : le geste
	 * redessine ces colonnes et celles où elle va ensuite (`getChange()`), sans relire la semaine deux fois.
	 */
	public static function getKeysForTask(\structure\Structure $eStructure, string $week, \series\Task $eTask): array {

		$times = \series\Timesheet::model()
			->whereStructure($eStructure)
			->whereTask($eTask)
			->whereDate('BETWEEN', new \Sql(\series\Timesheet::model()->format(week_date_starts($week)).' AND '.\series\Timesheet::model()->format(week_date_ends($week))))
			->group('date')
			->getColumn('date');

		$harvests = \series\Harvest::model()
			->select([
				'date',
				'total' => new \Sql('SUM(quantity)', 'float')
			])
			->whereStructure($eStructure)
			->whereTask($eTask)
			->whereWeek($week)
			->group('date')
			->having('total != 0.0')
			->getColumn('date');

		return self::getKeys($eTask, $week, [
			'times' => [$eTask['id'] => array_fill_keys($times, TRUE)],
			'harvests' => [$eTask['id'] => array_fill_keys($harvests, TRUE)],
		]);

	}

	/**
	 * La carte à faire qu'était une carte gardée avant sa coche : la personne qui a coché y retrouve le temps
	 * qu'elle avait alors. Seul l'élément est changé, rien n'est écrit.
	 */
	protected static function getTaskBefore(\series\Task $eTask, \user\User $eUser, float $timeBefore): \series\Task {

		$eTaskBefore = clone $eTask;
		$eTaskBefore['status'] = \series\Task::TODO;
		$eTaskBefore['kept'] = FALSE;

		$times = $eTask['times'];

		if($timeBefore > 0.0) {
			$times[$eUser['id']] = $timeBefore;
		} else {
			unset($times[$eUser['id']]);
		}

		$eTaskBefore['times'] = $times;
		$eTaskBefore['timePeriod'] = array_sum($times);

		return $eTaskBefore;

	}

	/**
	 * Les colonnes de l'écran, « Sans jour » puis les sept jours, chacune avec :
	 * - `cTask` : ses cartes, sous « Masquer ce qui est fait », dans l'ordre de la Semaine (l'action, puis
	 *   l'espèce et la série), que l'écran découpe par action ;
	 * - `todo` : ce qui y reste à faire, `done` : ce qui y est fait, et `time` : le temps passé ce jour-là
	 *   sur ses interventions, par tout le monde. Les trois ignorent le masque, comme les
	 *   comptes de la Semaine (`TaskWeekLib::countTodoByAction()`), pour ne pas changer d'une réponse ciblée
	 *   au rechargement. `todo` est le compte de la tête (Q1 du plan, gardé à la contre-recette) : le masque
	 *   ne cachant que ce qui est fait, il compte les cartes à faire visibles dans la colonne ;
	 * - `hidden` : le masque y cache au moins une carte, ce que dit l'état vide d'une colonne vidée.
	 *
	 * Une carte est une copie de l'intervention par colonne : chacune porte sa colonne (`column`) et le
	 * temps de la personne connectée ce jour-là (`timeDay`), que le champ d'une carte faite écrit sous son
	 * rond et son nom (`eUserTime`) (écart a de L5c). Seule la carte de sa première colonne (`main`) porte la
	 * case de sélection (L5d) : deux cases de la même intervention la compteraient deux fois dans la barre du
	 * lot. `$eTaskKept` est l'intervention qu'un geste vient de toucher, qui reste à l'écran même masquée,
	 * comme dans la Semaine (`TaskWeekLib::getBlocks()`).
	 *
	 * La copie d'une colonne de jour ne porte que ce jour (retour 360) : le temps de chaque personne
	 * (`times`), leur somme (`timePeriod`) et la quantité récoltée (`harvestPeriod`), que la carte lit comme
	 * à la Semaine. « Sans jour » et la carte gardée n'ont pas de jour et gardent la semaine (tranché par le
	 * maquetteur, DS). La case de sélection juge toujours la semaine (`harvestWeekTotal`) : le lot coché
	 * porte sur l'intervention entière, pas sur un jour.
	 *
	 * `$tasksKept` sont les interventions faites gardées dans « Sans jour » : celles qu'on vient d'y cocher, à
	 * l'unité ou en lot (`TaskBatchLib::getDayKept()`), et celles que l'écran y garde encore d'une coche
	 * précédente, qu'il réémet (`PlanningTaskDay.getBody()`, `emitKept()`).
	 * Chacune porte le temps qu'avait sur elle la personne connectée avant la coche, que
	 * `\series\TaskLib::updateDoneWithTime()` peut changer en y écrivant le prévu, et dont se déduit la carte à
	 * faire d'avant la coche (`eTaskBefore`). C'est ce temps qui voyage et non l'écart que la coche a écrit : la
	 * personne corrige souvent son temps ensuite, sur la copie du jour, et l'écart ne rendrait plus l'avant.
	 * La carte gardée empile son pied d'avant, invisible, sous son pied fait, et de même son groupe du haut
	 * (`TaskCardUi::get()`), et prend ainsi la place du plus grand des deux : aucune règle sur ce que la carte
	 * faite perd ou gagne ne tient aux deux largeurs, le prévu qui quitte le pied et la personne qui coche
	 * rejoignant le groupe du haut ne pesant pas pareil à 1199 px et à 360 px.
	 * « Sans jour » est la seule colonne qu'une coche fait quitter, et chacune y reste jusqu'au rechargement,
	 * cochée : la carte ne quitte pas la main qui vient de la cocher, et une seconde coche ne fait
	 * pas disparaître la première, ce qui remonterait tout le dessous (R-Jour-Fait). Un identifiant réémis
	 * n'ouvre rien : il ne garde qu'une intervention de la semaine relue, et faite. Dans la semaine courante, la coche la date
	 * d'aujourd'hui (`\series\TaskLib::updateDoneCollection()`) et la range donc aussi dans la colonne
	 * d'aujourd'hui ; dans une semaine passée, elle ne va plus dans aucune colonne (Q2 du plan) et sa coche
	 * ne se déferait plus sans la carte gardée. Celle-ci n'y compte pas, le rechargement ne l'y montrant
	 * pas, et ne porte la case de sélection que si elle est la seule. Elle est marquée (`kept`) pour garder
	 * la place qu'avait la carte à faire (`TaskCardUi::get()`). Une intervention rendue à faire n'est
	 * jamais gardée : tenue dans une colonne de jour, elle s'y lirait prévue ce jour-là.
	 */
	public static function getColumns(string $week, \Collection $cTask, array $dates, \user\User $eUser, bool $hide, \series\Task $eTaskKept = new \series\Task(), array $tasksKept = []): array {

		$columns = [];

		foreach(self::getColumnKeys($week) as $key) {
			$columns[$key] = [
				'date' => ($key === self::NONE) ? NULL : $key,
				'cTask' => new \Collection(),
				'todo' => 0,
				'done' => 0,
				'time' => 0.0,
				'hidden' => FALSE,
			];
		}

		foreach($cTask as $eTask) {

			$keys = self::getKeys($eTask, $week, $dates);

			if(
				array_key_exists($eTask['id'], $tasksKept) and
				$eTask->isDone()
			) {

				$eTaskColumn = clone $eTask;
				$eTaskColumn['column'] = self::NONE;
				$eTaskColumn['timeDay'] = 0.0;
				$eTaskColumn['eUserTime'] = $eUser;
				$eTaskColumn['main'] = ($keys === []);
				$eTaskColumn['kept'] = TRUE;
				$eTaskColumn['keptTime'] = $tasksKept[$eTask['id']];
				$eTaskColumn['eTaskBefore'] = self::getTaskBefore($eTaskColumn, $eUser, $tasksKept[$eTask['id']]);

				$columns[self::NONE]['cTask'][$eTask['id']] = $eTaskColumn;

			}

			foreach($keys as $key) {

				$times = ($key === self::NONE) ? [] : ($dates['times'][$eTask['id']][$key] ?? []);

				if($eTask->isTodo()) {
					$columns[$key]['todo']++;
				} else {
					$columns[$key]['done']++;
				}

				$columns[$key]['time'] += array_sum($times);

				if($hide and $eTask->isDone() and $eTask->is($eTaskKept) === FALSE) {
					$columns[$key]['hidden'] = TRUE;
					continue;
				}

				$eTaskColumn = clone $eTask;
				$eTaskColumn['column'] = $key;

				if($key !== self::NONE) {

					// Les personnes dans l'ordre de leur identifiant, comme à la Semaine (`TaskWeekLib::getTasksByWeeks()`).
					$timesDay = $times;
					ksort($timesDay);

					$eTaskColumn['times'] = $timesDay;
					$eTaskColumn['timePeriod'] = array_sum($timesDay);
					$eTaskColumn['harvestPeriod'] = $dates['harvests'][$eTask['id']][$key] ?? NULL;

				}

				$eTaskColumn['timeDay'] = $times[$eUser['id']] ?? 0.0;
				$eTaskColumn['eUserTime'] = $eUser;
				$eTaskColumn['main'] = ($key === $keys[0]);
				$eTaskColumn['kept'] = FALSE;

				$columns[$key]['cTask'][$eTask['id']] = $eTaskColumn;

			}

		}

		return $columns;

	}

	/**
	 * Les colonnes qu'un geste redessine : celles où l'intervention était avant lui, et celles où elle
	 * est après, dans l'ordre de l'écran. Une colonne est la plus petite unité qu'un geste change ici :
	 * sa tête compte ce qui y est fait et le temps du jour, et la carte peut y entrer ou en sortir.
	 */
	public static function getChange(array $columns, array $keysBefore, \series\Task $eTask): array {

		$keys = [];

		foreach($columns as $key => $column) {

			if(
				in_array($key, $keysBefore, TRUE) or
				$column['cTask']->offsetExists($eTask['id'])
			) {
				$keys[] = $key;
			}

		}

		return $keys;

	}

	/**
	 * Les colonnes que redessine le temps qu'une personne écrit un jour sur une carte faite (`doTime`), dans
	 * l'ordre de l'écran. Une colonne de jour ne montrant que son jour, les autres cartes de l'intervention ne
	 * changent pas, sauf :
	 * - la colonne du jour écrit, où la carte peut aussi entrer ou dont elle peut sortir ;
	 * - la première colonne avant et après le geste quand elles diffèrent, la case de sélection passant de
	 *   l'une à l'autre (`main`) ;
	 * - « Sans jour », où la carte gardée porte la semaine ;
	 * - toutes les colonnes de l'intervention quand la personne y entre ou en sort pour la semaine : la
	 *   recherche de chaque carte nomme les personnes qui ont du temps dans la semaine (`TaskSearchLib::getValues()`).
	 *
	 * @param \Collection $cTask Les interventions de la semaine relue après le geste.
	 * @param float $timeBefore Le temps de la personne sur l'intervention dans la semaine, lu avant le geste.
	 */
	public static function getChangeForTime(array $columns, \Collection $cTask, array $keysBefore, \series\Task $eTask, \user\User $eUser, float $timeBefore, string $date): array {

		$timeAfter = $cTask->offsetExists($eTask['id']) ? ($cTask[$eTask['id']]['times'][$eUser['id']] ?? 0.0) : 0.0;

		if(($timeBefore > 0.0) !== ($timeAfter > 0.0)) {
			return self::getChange($columns, $keysBefore, $eTask);
		}

		$keysAfter = [];

		foreach($columns as $key => $column) {

			if($key !== self::NONE and $column['cTask']->offsetExists($eTask['id'])) {
				$keysAfter[] = $key;
			}

		}

		$keys = [$date];

		if($keysBefore !== []) {
			$keys[] = $keysBefore[0];
		}

		if($keysAfter !== []) {
			$keys[] = $keysAfter[0];
		}

		if($columns[self::NONE]['cTask']->offsetExists($eTask['id'])) {
			$keys[] = self::NONE;
		}

		return array_values(array_filter(array_keys($columns), fn($key) => in_array($key, $keys, TRUE)));

	}

	/**
	 * Les interventions que « Nouvelle intervention » propose, récolte comprise (I4 de R-Retours-0410-soir) :
	 * les actions actives de la ferme, sous chacune de ses catégories qui en a, dans l'ordre de la ferme. Une
	 * action de deux catégories est proposée sous les deux. Chaque catégorie porte ses actions (`cAction`).
	 * Une récolte à faire ne demande pas de quantité, comme dans l'ancien planning : elle se saisit le jour
	 * où l'on récolte.
	 *
	 * @param string $name Ce que l'on tape dans le champ « Intervention » (`taskDay:queryAction`), vide pour toutes.
	 */
	public static function getCategoriesForCreate(\structure\Structure $eStructure, string $name = ''): \Collection {

		$cAction = \work\ActionLib::getByStructure($eStructure, search: new \Search(['name' => $name]));

		$cCategory = \work\CategoryLib::getByStructure($eStructure, index: 'id');

		foreach($cCategory as $eCategory) {
			$eCategory['cAction'] = $cAction->find(fn(\work\Action $eAction) => in_array($eCategory['id'], $eAction['categories']));
		}

		return $cCategory->find(fn(\work\Category $eCategory) => $eCategory['cAction']->notEmpty());

	}

	/**
	 * La section « Fréquentes » du champ « Intervention » (retour 257) : parmi les actions que `$cCategory`
	 * propose déjà, celles qui comptent le plus d'interventions sur les `TASK_ACTION_FREQUENT_WEEKS` dernières
	 * semaines et, l'année d'avant, sur autant de semaines à partir de la semaine en cours, pour annoncer ce que
	 * la saison ramène (Vincent, R-Retours-1210 V62), toutes catégories confondues, puis par nom à égalité.
	 * L'année d'avant se compte en 52 semaines et non par le numéro de la semaine, qu'une année de 53 semaines
	 * n'a pas toujours en face.
	 *
	 * Chaque action porte `eCategory`, la catégorie où elle compte le plus d'interventions parmi celles qui la
	 * proposent, la première de l'ordre de la structure à égalité : c'est celle qui part avec elle.
	 *
	 * @param \Collection $cCategory Les catégories et leurs actions (`getCategoriesForCreate()`).
	 * @param int $limit `TASK_ACTION_FREQUENT` pour la liste, `TASK_CREATE_SHORTCUTS` pour les pastilles du panneau.
	 */
	public static function getActionsFrequent(\structure\Structure $eStructure, \Collection $cCategory, int $limit): \Collection {

		$week = \Clock::week();
		$weekYearBefore = toWeek(strtotime($week.' - 52 WEEK'));
		$weeks = PlanningSetting::TASK_ACTION_FREQUENT_WEEKS - 1;

		$counts = \series\TaskLib::countByAction($eStructure, [
			[toWeek(strtotime($week.' - '.$weeks.' WEEK')), $week],
			[$weekYearBefore, toWeek(strtotime($weekYearBefore.' + '.$weeks.' WEEK'))],
		]);

		// Une action proposée sous deux catégories se lit deux fois : sa meilleure catégorie se garde sur son
		// clone, qui ne touche pas l'élément des listes de catégories.
		$cAction = new \Collection();

		foreach($cCategory as $eCategory) {

			foreach($eCategory['cAction'] as $eAction) {

				if(array_key_exists($eAction['id'], $counts) === FALSE) {
					continue;
				}

				$count = $counts[$eAction['id']][$eCategory['id']] ?? 0;

				if($cAction->offsetExists($eAction['id']) === FALSE) {

					$eActionFrequent = clone $eAction;
					$eActionFrequent['eCategory'] = $eCategory;
					$eActionFrequent['countCategory'] = $count;
					$eActionFrequent['count'] = array_sum($counts[$eAction['id']]);

					$cAction[$eAction['id']] = $eActionFrequent;

				} else {

					$eActionFrequent = $cAction[$eAction['id']];

					if($count > $eActionFrequent['countCategory']) {
						$eActionFrequent['eCategory'] = $eCategory;
						$eActionFrequent['countCategory'] = $count;
					}

				}

			}

		}

		$cAction->sort(fn(\work\Action $e1, \work\Action $e2) => ($e2['count'] <=> $e1['count']) ?: \L::getCollator()->compare($e1['name'], $e2['name']));

		return $cAction->find(limit: $limit);

	}

	/**
	 * La semaine qui suit, la seule hors de l'écran où le menu du jour d'une carte la reporte.
	 */
	public static function getNextWeek(string $week): string {

		return toWeek(strtotime($week.' + 1 WEEK'));

	}

}
?>
