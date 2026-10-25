<?php
namespace planning;

/**
 * L'écran « Interventions » d'une semaine : ce qu'il y a à faire, ce qui est fait ou commencé, rangé en
 * un bloc par action de la ferme.
 *
 * Les interventions s'y lisent sur les conditions des deux lectures du planning actuel
 * (`\series\TaskLib::getForWeekTodo()` et `getForWeekDone()`), pour plusieurs semaines à la fois
 * (`getByWeeks()`) ; le temps vient de `\series\TimesheetLib`
 * (charte, point 10 a).
 */
class TaskWeekLib {

	/**
	 * Les interventions de la semaine, à faire et faites, en une seule collection indexée par identifiant.
	 *
	 * À faire, celles qui sont prévues dans la semaine. Faites ou commencées, celles qui y sont faites et
	 * celles, faites ou non, qui y portent du temps ou une récolte, comme `\series\TaskLib::getForWeekDone()`
	 * pour le planning actuel. Une intervention à faire mais commencée dans sa semaine prévue n'y paraît
	 * qu'une fois.
	 *
	 * `timePeriod` est le temps de la période de la carte, ici la semaine (le Jour le réduit au jour de sa
	 * colonne, `TaskDayLib::getColumns()`), que la carte affiche : converti tel quel, sans arrondi préalable,
	 * comme le reste du produit (`series\TaskUi::convertTime()`), pour que la carte et la page de
	 * l'intervention disent la même durée.
	 *
	 * L'ordre ne dépend jamais de l'état : l'action, puis les interventions sans espèce, puis l'espèce, la
	 * série et l'identifiant. Cocher une carte ne la déplace donc pas, et la réponse ciblée d'un geste
	 * peut la remplacer sur place (`getChange()`). Il est porté par la clé `sort` de chaque intervention
	 * (`getSort()`).
	 *
	 * `search` est ce que la recherche lit sur la carte (`TaskSearchLib::fill()`).
	 */
	public static function get(\structure\Structure $eStructure, string $week): \Collection {

		return self::getByWeeks($eStructure, [$week])[$week];

	}

	/**
	 * Les interventions de chaque semaine, chacune comme `get()` la rend, en une lecture pour toutes :
	 * l'Année en ouvre huit d'un coup.
	 *
	 * @return array Par semaine, dans l'ordre de `$weeks`.
	 */
	public static function getByWeeks(\structure\Structure $eStructure, array $weeks): array {

		$cTaskByWeek = self::getTasksByWeeks($eStructure, $weeks);

		$collator = \L::getCollator();

		foreach($cTaskByWeek as $cTask) {

			foreach($cTask as $eTask) {
				$eTask['timePeriod'] = array_sum($eTask['times']);
				$eTask['sort'] = self::getSort($collator, $eTask);
				$eTask['keptCopy'] = FALSE;
			}

			$cTask->sort(fn(\series\Task $e1, \series\Task $e2): int => strcmp($e1['sort'], $e2['sort']));

		}

		TaskSearchLib::fill($eStructure, ...array_values($cTaskByWeek));

		return $cTaskByWeek;

	}

	/**
	 * Les interventions de chaque semaine, sans ordre. Une intervention paraît dans chacune de
	 * ses semaines par une copie (`clone`, comme `\series\TaskLib::getForDaily()` le fait par jour), qui porte
	 * ce que les délégués de `\series\TaskLib::getForPlanning()` y bornent à la semaine : le temps (`times`)
	 * par sa date, la récolte (`harvestPeriod`) par sa colonne `week`.
	 */
	private static function getTasksByWeeks(\structure\Structure $eStructure, array $weeks): array {

		$cTaskByWeek = [];

		foreach($weeks as $week) {
			$cTaskByWeek[$week] = new \Collection();
		}

		if($weeks === []) {
			return $cTaskByWeek;
		}

		$times = TaskSearchLib::getTimes($eStructure, $weeks);
		$harvests = TaskSearchLib::getHarvests($eStructure, $weeks);

		$cHarvest = \series\Harvest::model()
			->select([
				'task',
				'week',
				'total' => new \Sql('SUM(quantity)', 'float')
			])
			->whereStructure($eStructure)
			->whereWeek('IN', $weeks)
			->group(['task', 'week'])
			->getCollection();

		$harvestPeriods = [];

		foreach($cHarvest as $eHarvest) {
			$harvestPeriods[$eHarvest['task']['id']][$eHarvest['week']] = $eHarvest['total'];
		}

		// Le temps et les récoltes n'entrent pas dans cette condition : un `id IN` joint par OR ne laisse à
		// MySQL que l'index de la structure, qu'il parcourt en entier. Ils rejoignent la lecture suivante.
		$tasks = \series\Task::model()
			->whereStructure($eStructure)
			->or(
				fn() => $this
					->whereStatus(\series\Task::TODO)
					->wherePlannedWeek('IN', $weeks),
				fn() => $this
					->whereStatus(\series\Task::DONE)
					->whereDoneWeek('IN', $weeks),
			)
			->getColumn('id');

		\series\TaskLib::applyOpenSeries();

		$cTask = \series\Task::model()
			->select(\series\Task::getSelection())
			->select(\series\TaskLib::getPlanningSelection())
			->whereStructure($eStructure)
			->whereId('IN', array_merge($tasks, array_keys($times), array_keys($harvests)))
			->getCollection();

		foreach($cTask as $eTask) {

			// Avant les copies par semaine, qui partagent la culture : une fois par intervention, pas une fois par semaine.
			self::fillSeedling($eTask);

			foreach($weeks as $week) {

				$todo = ($eTask->isTodo() and $eTask['plannedWeek'] === $week);
				$started = (
					($eTask->isDone() and $eTask['doneWeek'] === $week) or
					isset($times[$eTask['id']][$week]) or
					in_array($week, $harvests[$eTask['id']] ?? [], TRUE)
				);

				if($todo === FALSE and $started === FALSE) {
					continue;
				}

				// Les personnes dans l'ordre de leur identifiant, comme le délégué du temps
				// (`\series\TimesheetLib::delegateByTask()`).
				$timesWeek = $times[$eTask['id']][$week] ?? [];
				ksort($timesWeek);

				$eTaskWeek = clone $eTask;
				$eTaskWeek['times'] = $timesWeek;
				$eTaskWeek['harvestPeriod'] = $harvestPeriods[$eTask['id']][$week] ?? NULL;
				$eTaskWeek['harvestWeekTotal'] = self::getHarvestWeekTotal($eTaskWeek);
				// Ni « En retard » ni « Non planifié » : la semaine, ses blocs et son Jour (`TaskCardUi::hasOtherWeek()`).
				$eTaskWeek['zone'] = NULL;

				$cTaskByWeek[$week][$eTask['id']] = $eTaskWeek;

			}

		}

		return $cTaskByWeek;

	}

	/**
	 * La quantité récoltée sur une intervention dans la semaine, que la case de sélection lit
	 * (`TaskBatchUi::getCheckbox()`) : `harvestPeriod` est celle de la période que montre la carte, que le Jour
	 * borne à sa colonne (`TaskDayLib::getColumns()`), alors que la case juge la semaine. `NULL` hors récolte.
	 */
	public static function getHarvestWeekTotal(\series\Task $eTask): ?float {

		return ($eTask['action']['fqn'] === ACTION_RECOLTE) ? $eTask['harvestPeriod'] : NULL;

	}

	/**
	 * La récolte d'une seule intervention dans la semaine, lue comme `getTasksByWeeks()` la lit pour toutes, pour
	 * une réponse qui redessine sa case de sélection (`TaskBatchUi::getCheckbox()`) sans relire la semaine.
	 */
	public static function fillHarvestWeek(\series\Task $eTask, string $week): void {

		$eTask['harvestPeriod'] = \series\Harvest::model()
			->whereTask($eTask)
			->whereWeek($week)
			->getValue(new \Sql('SUM(quantity)', 'float'));

		$eTask['harvestWeekTotal'] = self::getHarvestWeekTotal($eTask);

	}

	/**
	 * Ce que la carte et la ligne de la grille disent d'un semis ou d'une plantation, variété par variété
	 * (`TaskCardUi::getFacts()`) : `cCultivation` ne garde que la culture de l'intervention dont l'implantation
	 * correspond à son action, avec par variété les plants, les graines et la surface
	 * (`\series\CultivationLib::fillSliceStats()`) et les plateaux de chaque plaque (`trays`), que la culture
	 * annonce par `hasTrays` pour que la carte sache s'il leur faut une colonne. Une autre action ne reçoit rien.
	 *
	 * L'ancien planning a son propre remplissage, `\series\TaskLib::fillSeedling()`, qui garde la culture quelle
	 * que soit son implantation : `\series\TaskUi::getListByTool()` y somme les plants de chaque plaque.
	 *
	 * Les plateaux sont rangés par identifiant de plaque sur la variété, et non posés sur la plaque : les outils de
	 * `cTool?` viennent d'un cache partagé par toutes les interventions de la structure (`\work\ToolLib::deferred()`),
	 * où la valeur d'une variété écraserait celle de la précédente.
	 */
	public static function fillSeedling(\series\Task $eTask): void {

		$seedlings = match($eTask['action']['fqn']) {
			ACTION_SEMIS_DIRECT => [\series\Cultivation::SOWING],
			ACTION_SEMIS_PEPINIERE => [\series\Cultivation::YOUNG_PLANT],
			ACTION_PLANTATION => [\series\Cultivation::YOUNG_PLANT, \series\Cultivation::YOUNG_PLANT_BOUGHT],
			default => []
		};

		if($seedlings === []) {
			return;
		}

		$eTask['cCultivation'] = new \Collection();

		if(
			$eTask['cultivation']->empty() or
			in_array($eTask['cultivation']['seedling'], $seedlings, TRUE) === FALSE
		) {
			return;
		}

		// Le cadriciel rattache une seule collection de tranches à toutes les interventions d'une même culture, et les
		// plateaux dépendent du matériel de chacune : la pépinière et la plantation d'un même poireau s'écraseraient.
		$cSlice = new \Collection();

		foreach($eTask['cultivation']['cSlice'] as $key => $eSlice) {
			$cSlice[$key] = new \series\Slice($eSlice->getArrayCopy());
		}

		$eCultivation = $eTask['cultivation']->merge([
			'series' => $eTask['series'],
			'plant' => $eTask['plant'],
			'cSlice' => $cSlice
		]);

		\series\CultivationLib::fillSliceStats($eCultivation);

		$cToolTray = ($eTask['action']['fqn'] === ACTION_SEMIS_PEPINIERE) ?
			$eTask['cTool?']()->find(fn(\work\Tool $eTool) => $eTool['routineName'] === 'tray') :
			new \Collection();

		$eCultivation['hasTrays'] = $cToolTray->notEmpty();

		foreach($eCultivation['cSlice'] as $eSlice) {

			$trays = [];

			foreach($cToolTray as $eTool) {
				$trays[$eTool['id']] = ($eSlice['youngPlants'] === NULL) ? NULL : ceil($eSlice['youngPlants'] / $eTool['routineValue']['value'] * 10) / 10;
			}

			$eSlice['trays'] = $trays;

		}

		$eTask['cCultivation'][$eCultivation['id']] = $eCultivation;

	}

	/**
	 * La clé de rang d'une intervention dans sa semaine, que l'ordre de `get()` compare octet par octet et que
	 * le Jour pose sur ses cartes (`data-sort`) : le glisser d'une carte y montre la place où la réponse la
	 * rangera (`PlanningTaskDay.placeGhost()`, R-Retours-0810, 231) sans rien recalculer.
	 *
	 * Chaque partie est une clé de tri du collator, en hexadécimal : comparer deux clés rend le même ordre que
	 * `Collator::compare()` sur les chaînes. Deux clés égales jusqu'à une partie y entrent au même rang, et s'y
	 * lisent par paires de chiffres alignées : là où la plus courte des deux parties s'arrête, son séparateur
	 * `00` fait face à un octet de l'autre, qu'ICU n'écrit jamais nul, donc un nom qui en prolonge un autre passe
	 * après lui, comme au collator. Les identifiants sont complétés à dix chiffres pour se comparer comme des
	 * nombres. La clé ne dépend que de l'intervention, pour qu'une colonne qu'une réponse ciblée ne redessine
	 * pas garde les mêmes clés qu'au rechargement.
	 */
	private static function getSort(\Collator $collator, \series\Task $eTask): string {

		$parts = [
			bin2hex($collator->getSortKey($eTask['action']['name'])),
			sprintf('%010d', $eTask['action']['id']),
			$eTask['plant']->empty() ? '0' : '1',
			bin2hex($collator->getSortKey(self::getPlantName($eTask))),
			bin2hex($collator->getSortKey(self::getSeriesName($eTask))),
			sprintf('%010d', $eTask['id'])
		];

		return implode('00', $parts);

	}

	private static function getPlantName(\series\Task $eTask): string {
		return $eTask['plant']->empty() ? '' : $eTask['plant']['name'];
	}

	private static function getSeriesName(\series\Task $eTask): string {
		return $eTask['series']->empty() ? '' : $eTask['series']['name'];
	}

	/**
	 * Les blocs de l'écran, un par action, sous « Masquer ce qui est fait ».
	 *
	 * Une intervention cochée pendant la visite reste à l'écran même quand on masque ce qui est fait :
	 * elle ne disparaît qu'au rechargement (charte, point 10 e). `$eTaskKept` est celle que le geste
	 * vient de toucher, et elle seule échappe au masque.
	 *
	 * Les blocs suivent l'ordre que la personne a posé en mode Personnalisation (`$order`, les
	 * identifiants d'action de `Member::viewPlanningOrder`, L5e), puis viennent ceux qu'elle n'a jamais
	 * placés, dans l'ordre alphabétique de `get()` : une action apparue depuis son dernier déplacement
	 * se range donc après les autres (reprise g de la maquette navigable).
	 */
	public static function getBlocks(\Collection $cTask, bool $hide, \series\Task $eTaskKept = new \series\Task(), array $order = []): \Collection {

		$ccTask = new \Collection();

		foreach($cTask as $eTask) {

			if($hide and $eTask->isDone() and $eTask->is($eTaskKept) === FALSE) {
				continue;
			}

			$action = $eTask['action']['id'];

			if($ccTask->offsetExists($action) === FALSE) {
				$ccTask[$action] = new \Collection();
			}

			$ccTask[$action][$eTask['id']] = $eTask;

		}

		return self::sortBlocks($ccTask, $order);

	}

	/**
	 * Les deux colonnes des Colonnes (GQ), « À faire » et « Fait », avec leurs comptes.
	 *
	 * Une intervention cochée depuis « À faire » y reste, faite, jusqu'au rechargement (R-Retours-1210 V40) :
	 * la carte ne quitte pas la main qui vient de la cocher, comme dans « Sans jour » du Jour
	 * (`TaskDayLib::getColumns()`). `$tasksKept` sont les identifiants que l'écran garde ainsi ; un identifiant
	 * réémis n'ouvre rien, il ne garde qu'une intervention de `$cTask`, et faite. « Fait » en porte une copie
	 * (`clone`), marquée `keptCopy`, que `TaskCardUi::get()` distingue de la carte gardée, qui garde son
	 * identifiant. La carte gardée compte dans « Fait » et non dans « À faire », comme au rechargement.
	 *
	 * @return array `ccTaskTodo` et `ccTaskDone`, les blocs de chaque colonne, `countByStatus`, le compte de
	 * chaque colonne, et `countByAction`, celui de chaque bloc, par colonne puis par action.
	 */
	public static function getColumns(\Collection $cTask, array $tasksKept, array $order): array {

		$cTaskTodo = new \Collection();
		$cTaskDone = new \Collection();

		foreach($cTask as $eTask) {

			if($eTask->isTodo()) {
				$cTaskTodo[$eTask['id']] = $eTask;
				continue;
			}

			if(in_array($eTask['id'], $tasksKept)) {

				$cTaskTodo[$eTask['id']] = $eTask;

				$eTaskCopy = clone $eTask;
				$eTaskCopy['keptCopy'] = TRUE;

				$cTaskDone[$eTask['id']] = $eTaskCopy;

			} else {
				$cTaskDone[$eTask['id']] = $eTask;
			}

		}

		$ccTaskTodo = self::getBlocks($cTaskTodo, FALSE, order: $order);
		$ccTaskDone = self::getBlocks($cTaskDone, FALSE, order: $order);

		$countByAction = [
			\series\Task::TODO => [],
			\series\Task::DONE => [],
		];

		foreach($ccTaskTodo as $action => $cTaskAction) {
			$countByAction[\series\Task::TODO][$action] = $cTaskAction->find(fn($eTask) => $eTask->isTodo())->count();
		}

		foreach($ccTaskDone as $action => $cTaskAction) {
			$countByAction[\series\Task::DONE][$action] = $cTaskAction->count();
		}

		return [
			'ccTaskTodo' => $ccTaskTodo,
			'ccTaskDone' => $ccTaskDone,
			'countByStatus' => [
				\series\Task::TODO => array_sum($countByAction[\series\Task::TODO]),
				\series\Task::DONE => array_sum($countByAction[\series\Task::DONE]),
			],
			'countByAction' => $countByAction,
		];

	}

	/**
	 * Les blocs dans l'ordre de la personne : d'abord les actions de `$order`, dans son ordre, puis les
	 * autres dans l'ordre où elles arrivent. Une action de `$order` absente de la semaine ne réserve
	 * aucune place.
	 */
	public static function sortBlocks(\Collection $ccTask, array $order): \Collection {

		if($order === []) {
			return $ccTask;
		}

		$positions = array_flip($order);
		$actions = $ccTask->getKeys();

		$placed = array_values(array_filter($actions, fn(int $action) => array_key_exists($action, $positions)));
		$others = array_values(array_filter($actions, fn(int $action) => array_key_exists($action, $positions) === FALSE));

		usort($placed, fn(int $action1, int $action2) => $positions[$action1] <=> $positions[$action2]);

		$ccTaskSorted = new \Collection();

		foreach(array_merge($placed, $others) as $action) {
			$ccTaskSorted[$action] = $ccTask[$action];
		}

		return $ccTaskSorted;

	}

	/**
	 * L'ordre de la personne après qu'elle a lâché le bloc `$action` devant le bloc `$before`, le voisin
	 * qui le suit à l'écran, ou à la fin quand il ferme la liste (`NULL`). C'est l'intention que le client
	 * envoie, jamais l'ordre entier ni un rang : un rang se compte parmi les blocs que le client montre,
	 * que le serveur ne connaît pas (un bloc gardé à l'écran sous « Masquer ce qui est fait » par une
	 * carte cochée pendant la visite y compte, et plusieurs peuvent l'être à la fois).
	 *
	 * `$order` arrive complet, toutes les actions de la liste du mode Personnalisation (`getActions()`) et les
	 * actions inactives à leur rang : les autres gardent leur suite, et chaque action n'y figure qu'une fois,
	 * par construction.
	 */
	public static function applyMove(array $order, int $action, ?int $before): array {

		$order = array_values(array_filter($order, fn(int $actionOrdered) => $actionOrdered !== $action));

		$place = ($before === NULL) ? FALSE : array_search($before, $order, TRUE);

		array_splice($order, ($place === FALSE) ? count($order) : $place, 0, [$action]);

		return $order;

	}

	/**
	 * Les actions actives de la structure, dans l'ordre de la liste du mode Personnalisation
	 * (R-Personnalisation) : celles de `$order`, dans son ordre, puis les autres dans l'ordre alphabétique
	 * des blocs (`get()`). Une action de `$order` qui n'est plus active ni de la structure n'y prend aucune
	 * place.
	 *
	 * `taskWeek:doOrder` n'accepte pas d'autre action que celles-ci, et rejoue le geste sur l'ordre enregistré,
	 * où une action inactive garde son rang.
	 */
	public static function getActions(\structure\Structure $eStructure, array $order): \Collection {

		$cAction = \work\ActionLib::getByStructure($eStructure, index: 'id');

		$collator = \L::getCollator();

		$cAction->sort(function(\work\Action $e1, \work\Action $e2) use($collator): int {

			$compare = $collator->compare($e1['name'], $e2['name']);

			return ($compare !== 0) ? $compare : ($e1['id'] <=> $e2['id']);

		});

		$cActionSorted = new \Collection();

		foreach($order as $action) {

			if($cAction->offsetExists($action)) {
				$cActionSorted[$action] = $cAction[$action];
			}

		}

		foreach($cAction as $action => $eAction) {

			if($cActionSorted->offsetExists($action) === FALSE) {
				$cActionSorted[$action] = $eAction;
			}

		}

		return $cActionSorted;

	}

	/**
	 * Ce qui reste à faire dans chaque bloc. « Masquer ce qui est fait » retire aussi les interventions
	 * commencées et pas finies : le compte les garde, pour ne pas changer d'une réponse ciblée au
	 * rechargement.
	 *
	 * @return array Le compte, par identifiant d'action.
	 */
	public static function countTodoByAction(\Collection $cTask): array {

		$todo = [];

		foreach($cTask as $eTask) {

			$action = $eTask['action']['id'];

			$todo[$action] ??= 0;

			if($eTask->isTodo()) {
				$todo[$action]++;
			}

		}

		return $todo;

	}

	/**
	 * Les personnes affectées à l'intervention, parmi les membres `$cUser`, dans l'ordre de l'affectation :
	 * celles qui ont quitté la structure ne s'affichent pas, comme sur la carte. La grille les rend dans le
	 * déclencheur d'« Affecter à... » (`TaskWeekGridUi::getUsers()`).
	 */
	public static function getUsersPlanned(\series\Task $eTask, \Collection $cUser): \Collection {

		$cUserPlanned = new \Collection();

		foreach($eTask['plannedUsers'] as $user) {
			if($cUser->offsetExists($user)) {
				$cUserPlanned[$user] = $cUser[$user];
			}
		}

		return $cUserPlanned;

	}

	/**
	 * Le travail estimé de la semaine (R-Retours-1210, 312), tous filtres ôtés : le temps prévu des
	 * interventions planifiées cette semaine et de celles qui y ont été réalisées. Une intervention que
	 * seuls un temps ou une récolte amènent dans la semaine n'y entre pas, son temps prévu appartenant à sa
	 * propre semaine : l'ancien planning (`\series\PlanningUi::getWeekTime()`) l'y comptait.
	 */
	public static function getTimeExpected(\Collection $cTask, string $week): float {

		return round($cTask
			->find(fn(\series\Task $eTask) => (
				($eTask->isTodo() and $eTask['plannedWeek'] === $week) or
				($eTask->isDone() and $eTask['doneWeek'] === $week)
			))
			->sum('timeExpected'), 2);

	}

	/**
	 * Une intervention de la grille que la personne connectée pourrait déplacer, sans le verrou, sur un jour
	 * encore à venir de la semaine : à faire, commencée par un temps ou une récolte (`Task::acceptPlanned()`), et
	 * qu'elle peut écrire. La grille de la semaine les lit sur ses interventions, le tableau « En retard » sur les
	 * clés de sa zone (`$lateStarted`, `TaskLateLib::getZone()`), toutes à faire, qui s'écrivent avec le droit sur
	 * le planning de la structure (`Task::canWrite()`). Le retard est compté sur ses clés entières, plafond
	 * ignoré : la phrase dit qu'un verrou existe dans la zone, que la ligne verrouillée
	 * soit montrée ou attende derrière « Voir les N autres ».
	 */
	public static function hasLocked(\structure\Structure $eStructure, \Collection $cTask, bool $lateStarted, string $week): bool {

		if(week_dates($week)[6] <= \Clock::date()) {
			return FALSE;
		}

		if($lateStarted and $eStructure->canPlanning()) {
			return TRUE;
		}

		return $cTask->find(fn(\series\Task $eTask) => (
			$eTask->isTodo() and
			$eTask->acceptPlanned() === FALSE and
			$eTask->canWrite()
		))->notEmpty();

	}

	/**
	 * Ce qu'un geste sur une carte change à l'écran, lu dans les blocs d'après le geste :
	 * - `card` : la carte est toujours là, elle se remplace sur place, l'ordre ne dépendant pas de l'état ;
	 * - `remove` : elle a quitté la semaine, et son bloc garde d'autres cartes ;
	 * - `emptied` : son bloc s'est vidé, et il sort seul. Les autres blocs gardent leur pile et leur rang, et
	 *   seuls ceux qui étaient sous lui dans sa pile remontent. La répartition entre les piles
	 *   (`MainPiles.stack()`) ne se refait qu'au rechargement, pour ne pas déplacer sous les yeux ce qu'on
	 *   vient de lire (R-Retours-0810, 222) ;
	 * - `board` : son bloc était le dernier, et l'écran des blocs se rend entier, qui dit alors que la semaine
	 *   n'a plus rien à montrer.
	 */
	public static function getChange(\Collection $ccTask, \series\Task $eTask): string {

		$action = $eTask['action']['id'];

		if($ccTask->offsetExists($action) === FALSE) {
			return $ccTask->empty() ? 'board' : 'emptied';
		}

		if($ccTask[$action]->offsetExists($eTask['id'])) {
			return 'card';
		}

		return 'remove';

	}

	/**
	 * Un écran sans aucune intervention (R-Retours-0410, point 18) : ni dans la semaine, ni dans les zones
	 * qu'il montre sous ses blocs, « En retard » et « Non planifié », comptées même repliées ou pas encore
	 * ouvertes (`TaskLateLib::getZone()`). Le Jour n'a pas ces zones et n'en passe aucune. « Rappels »
	 * (`DueLib`) ne porte pas d'intervention et n'y entre pas.
	 *
	 * « Masquer ce qui est fait » ne retire rien de `$cTask`, et la recherche filtre dans le navigateur : un
	 * écran que l'un ou l'autre vide n'est donc pas vide ici, et garde la rangée des outils qui permet de
	 * revenir en arrière.
	 *
	 * Après un geste, l'écran se juge privé de ce que le geste vient de créer (`$cTaskCreated`) : vide, c'est
	 * qu'il l'est après le geste, ou qu'il ne l'était qu'avant et que seule la création le remplit. Dans les
	 * deux cas la rangée des outils apparaît ou disparaît, et la réponse rend l'écran entier. Un geste sur une
	 * carte ne crée rien et la carte était à l'écran : il ne le vide qu'en emportant la dernière.
	 */
	public static function isEmpty(\Collection $cTask, array $zones = [], \Collection $cTaskCreated = new \Collection()): bool {

		$idsCreated = $cTaskCreated->getIds();

		if($cTask->find(fn($eTask) => in_array($eTask['id'], $idsCreated) === FALSE)->notEmpty()) {
			return FALSE;
		}

		foreach($zones as $zone) {

			if($zone['count'] > 0) {
				return FALSE;
			}

		}

		return TRUE;

	}

}
?>
