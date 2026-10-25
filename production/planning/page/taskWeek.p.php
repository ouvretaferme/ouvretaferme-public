<?php
/**
 * Les interventions d'une semaine, premier écran du nouveau planning (L5a) : un bloc par action, une
 * carte par intervention, et deux gestes sur la carte, la coche et la décoche.
 * Cocher ouvre sous la carte la bulle du temps passé, qui n'écrit que le temps de la personne connectée.
 *
 * « Masquer ce qui est fait » est une préférence du membre
 * (`viewPlanningHideDone`, retour 108, qui lève pour elle le point 10 f de la charte), écrite
 * par `structure/member:doUpdatePlanningHideDone` et relue ici à chaque requête, page comme geste.
 * L'ordre des blocs (`viewPlanningOrder`, L5e) l'est aussi, écrit par `doOrder` et relu de même.
 *
 * Chaque geste répond par ce qu'il change (`TaskWeekLib::getChange()`) : la carte et le compte de son bloc,
 * ou le bloc qu'il vide. L'écran des blocs (`#planning-week-board`) n'est rendu entier que quand le dernier
 * bloc se vide ou qu'une intervention arrivée d'une zone ou créée ouvre un bloc, et la page entière que
 * quand l'écran se vide ou cesse de l'être (`planningTaskWeekScreen()`).
 */

/**
 * La lecture de l'écran, commune à la page et aux réponses des gestes : les interventions de la semaine,
 * ses blocs et leurs comptes, les membres dont les cartes montrent le temps, et si l'écran n'a aucune
 * intervention (`TaskWeekLib::isEmpty()`), qui retire la rangée des outils. `$eTaskKept` est l'intervention que le geste vient de
 * toucher, qui reste à l'écran même masquée (`TaskWeekLib::getBlocks()`), et `$tasksKept` les interventions
 * que les Colonnes gardent dans « À faire » une fois cochées (`TaskWeekLib::getColumns()`), que seuls les
 * gestes réémettent (`planningTaskWeekKept()`). La semaine suivante nomme ce que
 * le glisser à droite d'une carte promet (`TaskWeekUi::getSwipe()`, retour 176), et le geste « Récolter »
 * n'existe que là où l'écran des récoltes accepte une saisie (retour 203).
 */
function planningTaskWeekRead(\stdClass $data, \series\Task $eTaskKept = new \series\Task(), array $tasksKept = []): void {

	$data->cTask = \planning\TaskWeekLib::get($data->eStructure, $data->week);

	$data->weekNext = \planning\TaskDayLib::getNextWeek($data->week);

	// En Colonnes, ce qui est fait a sa colonne : le masque du membre n'y retire rien, sans que son choix change.
	if($data->columns) {
		$data->ccTask = new \Collection();
		$columns = \planning\TaskWeekLib::getColumns($data->cTask, $tasksKept, $data->order);
		$data->ccTaskTodo = $columns['ccTaskTodo'];
		$data->ccTaskDone = $columns['ccTaskDone'];
		$data->countByStatus = $columns['countByStatus'];
		$data->countByAction = $columns['countByAction'];
	} else {
		$data->ccTask = \planning\TaskWeekLib::getBlocks($data->cTask, $data->hide, $eTaskKept, $data->order);
	}

	// « + Fait » n'a rien à créer sur une semaine dont aucun jour ne peut porter Fait.
	$data->canCreateByStatus = [
		\series\Task::TODO => $data->canCreate,
		\series\Task::DONE => ($data->canCreate and \planning\TaskCreateLib::hasDone($data->week)),
	];

	$data->todo = \planning\TaskWeekLib::countTodoByAction($data->cTask);
	$data->timeExpected = $data->eStructure->hasTime() ? \planning\TaskWeekLib::getTimeExpected($data->cTask, $data->week) : 0.0;
	$data->cUser = \hr\TeammateLib::getUsersForPlanning($data->eStructure);

	if($data->grid) {
		planningTaskWeekGrid($data);
	}

	$data->canHarvest = (
		\planning\HarvestWeekLib::canHarvest($data->eStructure) and
		\planning\HarvestWeekLib::getCreateDate(\planning\HarvestWeekLib::getDays($data->week)) !== NULL
	);

	$data->zones = planningTaskWeekZones($data);
	$data->empty = \planning\TaskWeekLib::isEmpty($data->cTask, $data->zones);

	if($data->grid) {
		$data->locked = \planning\TaskWeekLib::hasLocked($data->eStructure, $data->cTask, $data->zones[\planning\TaskLateLib::LATE]['started'], $data->week);
	}

}

/**
 * Le temps de chaque jour de la semaine sur chaque intervention, toutes personnes confondues, que la grille
 * écrit dans ses cases (`TaskWeekGridUi`), et les membres affectés de son déclencheur d'« Affecter à... ».
 * Les cartes ne lisent ni l'un ni l'autre.
 */
function planningTaskWeekGrid(\stdClass $data): void {

	$times = \planning\TaskDayLib::getDates($data->eStructure, $data->week)['times'];

	$days = array_fill_keys(week_dates($data->week), 0.0);

	foreach($data->cTask as $eTask) {
		$eTask['timesByDate'] = array_map(fn(array $timesByUser) => array_sum($timesByUser), $times[$eTask['id']] ?? []) + $days;
		$eTask['cUserPlanned'] = \planning\TaskWeekLib::getUsersPlanned($eTask, $data->cUser);
	}

}

/**
 * Ce que la grille lit en plus sur les lignes hydratées du tableau « En retard » (DZ), comme sur celles de la
 * semaine (`planningTaskWeekGrid()`). Aucune n'a de temps dans la semaine affichée, sans quoi elle serait dans la
 * semaine (`TaskLateLib::getKeys()`) : chaque jour vaut zéro. Les lignes déjà complétées le sont de nouveau, à
 * l'identique, quand une réponse en hydrate d'autres (`fillChange()`, `fillBlock()`).
 */
function planningTaskWeekGridZone(\stdClass $data): void {

	if($data->grid === FALSE) {
		return;
	}

	$days = array_fill_keys(week_dates($data->week), 0.0);

	foreach($data->zones[\planning\TaskLateLib::LATE]['cTask'] as $eTask) {
		$eTask['timesByDate'] = $days;
		$eTask['cUserPlanned'] = \planning\TaskWeekLib::getUsersPlanned($eTask, $data->cUser);
	}

}

/**
 * Le tableau de l'équipe du menu d'un jour de la grille, celui du bloc « Équipe » de la fiche réduit à ce jour
 * (`TaskDetailUi::getGridWeek()`) : les personnes de la semaine affichée avec les lignes ajoutées à la main
 * (`added`, que le menu réémet), le jour et le pied.
 */
function planningTaskWeekTeam(\stdClass $data, \series\Task $eTask, string $date, array $userIdsAdded): void {

	$data->dateTeam = $date;
	$data->cUserTeam = \planning\TaskDetailLib::getUsers($eTask, $data->week, $userIdsAdded);
	$data->daysTeam = array_intersect_key(\planning\TaskDetailLib::getDays($data->week, \planning\TaskDetailLib::getWorked($data->cUserTeam)), [$date => TRUE]);
	$data->footTeam = \planning\TaskDetailLib::getFoot($data->cUserTeam, $data->daysTeam);

}

/**
 * Les lignes ajoutées à la main dans le tableau d'un menu. `TaskDetailLib::getUsers()` écarte celles qui
 * n'ont pas à y être.
 */
function planningTaskWeekAdded(): array {
	return array_filter(array_map('intval', explode(',', INPUT('added'))));
}

/**
 * Les deux zones sous les blocs (L8, `TaskLateLib`), lues par leurs clés, sans carte hydratée. « Non
 * planifié » ne se montre qu'une fois ouverte, ce que le client réémet (`unplanned`). Une zone montrée
 * est dépliée sauf si la personne l'a repliée (`folded`, réémis aussi) : une réponse qui redessine les
 * zones ou l'écran garde ainsi le pli de chacune.
 */
function planningTaskWeekZones(\stdClass $data): array {

	$zones = [
		\planning\TaskLateLib::LATE => \planning\TaskLateLib::getZone($data->eStructure, \planning\TaskLateLib::LATE, $data->week, TRUE),
		\planning\TaskLateLib::UNPLANNED => \planning\TaskLateLib::getZone($data->eStructure, \planning\TaskLateLib::UNPLANNED, $data->week, $data->unplanned),
	];

	foreach($zones as $name => $zone) {
		$zones[$name]['open'] = ($zone['loaded'] and in_array($name, $data->folded, TRUE) === FALSE);
	}

	return $zones;

}

/**
 * Les interventions que les Colonnes gardent dans « À faire » une fois cochées (`TaskWeekLib::getColumns()`),
 * réémises par l'écran à chaque geste (`kept`), avec celle que la coche emporte quand sa carte part de
 * « À faire ». Sans la réémission, la colonne « Fait » rendue entière par une seconde coche redonnerait à la
 * première l'identifiant de sa carte gardée. Le Jour garde de même ses cartes de « Sans jour »
 * (`planningTaskDayKeptPosted()`).
 */
function planningTaskWeekKept(): array {

	return array_map(fn($task) => (int)$task, array_keys(POST('kept', 'array', [])));

}

/**
 * Ce que l'écran montrait avant un geste : les zones (`planningTaskWeekZones()`), l'état de chaque
 * intervention que le geste touche, qui dit en Colonnes de quelle colonne part sa carte, et les cartes que
 * les Colonnes gardaient (`planningTaskWeekKept()`). Il se lit avant l'écriture, qui change l'état sur
 * l'élément même (`\series\TaskLib::updateDoneCollection()`).
 */
function planningTaskWeekBefore(\stdClass $data, \Collection $cTask): array {

	$statuses = [];

	foreach($cTask as $eTask) {
		$statuses[$eTask['id']] = $eTask['status'];
	}

	return [
		'zones' => planningTaskWeekZones($data),
		'statuses' => $statuses,
		'kept' => planningTaskWeekKept(),
	];

}

/**
 * Ce qu'un geste change dans les Colonnes (GQ), colonne par colonne :
 * - `column` : la colonne rendue entière, quand une intervention y arrive, d'une autre colonne, d'une zone ou
 *   neuve, puisque sans `MainPiles` sa place est celle du document, ou quand le geste la vide ;
 * - sinon, pour chaque intervention dont la carte y était, `TaskWeekLib::getChange()` sur ses blocs : la carte
 *   remplacée (`card`, avec son action), retirée (`remove`), ou son bloc retiré (`emptied`), puis les comptes
 *   des blocs qui restent (`counter`).
 * Une intervention que la saisie d'une récolte écrit à côté de la carte d'où elle part n'a pas d'état d'avant :
 * sa carte peut être dans l'une ou l'autre colonne, qui se rendent alors entières.
 *
 * Une intervention que les Colonnes gardaient (`$tasksKept`, faite avant le geste) avait une carte dans
 * chaque colonne, et celle de « Fait » était sa copie : `kept` nomme, par colonne, les interventions dont la
 * réponse vise la copie (`planning-task-<id>-kept`). La gardée l'est encore après le geste si elle reste
 * faite, puisque l'écran la réémet : la copie qu'on remplace a donc le même identifiant avant et après.
 *
 * `$idsArrived` garde les interventions arrivées dans une colonne, que la réponse éclaire, `$idsArrivedKept`
 * celles dont la carte arrivée est la copie d'une carte gardée, et les colonnes qu'elles rejoignent, dont le
 * compte d'onglet monte. `$data->change` vaut `columns`, ou `NULL` quand aucune
 * colonne ne bouge, comme aux Cartes.
 */
function planningTaskWeekColumnsChange(\stdClass $data, \Collection $cTask, array $statuses, array $idsArriving, array $tasksKept = []): void {

	$data->changeColumns = [];
	$data->idsArrived = [];
	$data->idsArrivedKept = [];
	$data->statusesArrived = [];

	foreach([\series\Task::TODO => $data->ccTaskTodo, \series\Task::DONE => $data->ccTaskDone] as $status => $ccTaskStatus) {

		$change = ['card' => [], 'remove' => [], 'emptied' => [], 'counter' => [], 'kept' => []];

		foreach($cTask as $eTask) {

			$id = $eTask['id'];
			$action = $eTask['action']['id'];
			$arriving = in_array($id, $idsArriving);

			if($arriving === FALSE and array_key_exists($id, $statuses) === FALSE) {
				$change = 'column';
				break;
			}

			$changeTask = \planning\TaskWeekLib::getChange($ccTaskStatus, $eTask);

			$kept = ($arriving === FALSE and $statuses[$id] === \series\Task::DONE and in_array($id, $tasksKept));
			$here = ($arriving === FALSE and ($statuses[$id] === $status or $kept));

			// Sans carte ici avant le geste, seule son arrivée compte : ce qui reste ailleurs n'a rien à retirer.
			if($here === FALSE) {

				if($changeTask === 'card') {

					$data->idsArrived[] = $id;
					$data->statusesArrived[] = $status;

					if($ccTaskStatus[$action][$id]['keptCopy']) {
						$data->idsArrivedKept[] = $id;
					}

					$change = 'column';

				}

				continue;

			}

			if($change === 'column') {
				continue;
			}

			switch($changeTask) {

				// La colonne se rend entière : une copie gardée n'y a plus de carte à viser.
				case 'board' :
					$change = 'column';
					continue 2;

				case 'emptied' :
					$change['emptied'][] = $action;
					break;

				case 'card' :
					$change['card'][$id] = $action;
					$change['counter'][] = $action;
					break;

				case 'remove' :
					$change['remove'][] = $id;
					$change['counter'][] = $action;
					break;

			}

			if($kept and $status === \series\Task::DONE) {
				$change['kept'][] = $id;
			}

		}

		if(is_array($change)) {
			$change['emptied'] = array_values(array_unique($change['emptied']));
			$change['counter'] = array_values(array_diff(array_unique($change['counter']), $change['emptied']));
		}

		$data->changeColumns[$status] = $change;

	}

	$data->statusesArrived = array_values(array_unique($data->statusesArrived));

	// Un geste qui ne laisse rien dans les colonnes, comme une intervention en retard cochée dans sa propre
	// semaine, ne répond rien ici, comme aux Cartes : la bulle n'aurait pas de carte après laquelle se poser.
	$unchanged = ['card' => [], 'remove' => [], 'emptied' => [], 'counter' => [], 'kept' => []];

	$data->change = ($data->changeColumns[\series\Task::TODO] === $unchanged and $data->changeColumns[\series\Task::DONE] === $unchanged) ? NULL : 'columns';

	// Le pense-bête ouvre « À faire » : il se relit quand la colonne se rend entière.
	if($data->changeColumns[\series\Task::TODO] === 'column') {
		planningTaskWeekReminders($data);
	}

}

/**
 * Toutes les cartes des deux zones, pour un écran rendu entier.
 */
function planningTaskWeekFill(\stdClass $data): void {

	\planning\TaskLateLib::fill($data->eStructure, $data->week, $data->zones[\planning\TaskLateLib::LATE]);
	\planning\TaskLateLib::fill($data->eStructure, $data->week, $data->zones[\planning\TaskLateLib::UNPLANNED]);

	planningTaskWeekGridZone($data);

}

/**
 * Ce qu'un geste change dans les deux zones (`TaskLateLib::getChange()`), avec les seules cartes que la
 * réponse redessine. Une zone qui apparaît ou disparaît rend les deux zones entières (`zones`).
 */
function planningTaskWeekZonesChange(\stdClass $data, array $zonesBefore, array $ids): void {

	$data->changeZones = [];

	foreach($data->zones as $zone => $zoneAfter) {

		$change = \planning\TaskLateLib::getChange($zonesBefore[$zone], $zoneAfter, $ids);

		if($change === 'zones') {
			$data->changeZones = 'zones';
			planningTaskWeekFill($data);
			return;
		}

		$data->changeZones[$zone] = $change;

	}

	foreach($data->changeZones as $zone => $change) {
		\planning\TaskLateLib::fillChange($data->eStructure, $data->week, $data->zones[$zone], $change);
	}

	planningTaskWeekGridZone($data);

}

/**
 * La réponse d'un geste sur une carte : l'écran relu avec l'intervention gardée et ce que le geste y change
 * (`TaskWeekLib::getChange()`).
 *
 * Seule une récolte peut créer l'intervention qu'elle écrit (`$created`, `taskWeek:doHarvest`) : elle
 * entre alors dans son bloc comme une intervention neuve.
 */
function planningTaskWeekRespond(\stdClass $data, \series\Task $eTask, array $before, bool $created = FALSE): void {

	$zonesBefore = $before['zones'];

	$data->eTask = $eTask;

	planningTaskWeekRead($data, $eTask, $before['kept']);

	if(planningTaskWeekScreen($data, $created ? new \Collection([$eTask]) : new \Collection())) {
		$data->change = 'screen';
		return;
	}

	// Une intervention qui venait d'une zone n'a pas encore de carte dans les blocs : elle y entre comme une
	// intervention neuve (L8).
	$arriving = (
		$created or
		in_array($eTask['id'], \planning\TaskLateLib::getIds($zonesBefore[\planning\TaskLateLib::LATE])) or
		in_array($eTask['id'], \planning\TaskLateLib::getIds($zonesBefore[\planning\TaskLateLib::UNPLANNED]))
	);

	if($data->columns) {

		planningTaskWeekColumnsChange($data, new Collection([$eTask]), $before['statuses'], $arriving ? [$eTask['id']] : [], $before['kept']);

	} else if($arriving) {

		$data->change = \planning\TaskBatchLib::getCreateChange($data->ccTask, $eTask);

		if($data->change === 'block') {
			$data->rank = array_search($eTask['action']['id'], $data->ccTask->getKeys());
		}

	} else {
		$data->change = \planning\TaskWeekLib::getChange($data->ccTask, $eTask);
	}

	if($data->change === 'board') {
		planningTaskWeekReminders($data);
	}

	planningTaskWeekZonesChange($data, $zonesBefore, [$eTask['id']]);

}

/**
 * Si l'écran doit se rendre entier après un geste, parce que la rangée des outils apparaît ou disparaît
 * (R-Retours-0410, point 18, `TaskWeekLib::isEmpty()`).
 */
function planningTaskWeekScreen(\stdClass $data, \Collection $cTaskCreated = new \Collection()): bool {

	if(\planning\TaskWeekLib::isEmpty($data->cTask, $data->zones, $cTaskCreated)) {
		planningTaskWeekFill($data);
		planningTaskWeekReminders($data);
		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		return TRUE;
	}

	return FALSE;

}

/**
 * La réponse d'un geste de lot (L5d) : l'écran relu, puis ce que le lot y change
 * (`TaskBatchLib::getWeekChange()`), carte par carte, bloc vidé par bloc vidé, ou les blocs entiers quand
 * le dernier se vide ou qu'une intervention arrivée d'une zone entre dans la semaine. Rien n'échappe au
 * masque : seule la coche d'une carte garde l'intervention qu'elle touche.
 */
function planningTaskWeekBatch(\stdClass $data, \Collection $cTask, string $gesture, array $before): void {

	$zonesBefore = $before['zones'];

	$data->gesture = $gesture;
	$data->cTaskBatch = $cTask;

	planningTaskWeekRead($data, tasksKept: $before['kept']);

	if(planningTaskWeekScreen($data)) {
		$data->change = 'screen';
		throw new ViewAction($data, ':doBatch');
	}

	$idsArriving = array_merge(
		\planning\TaskLateLib::getIds($zonesBefore[\planning\TaskLateLib::LATE]),
		\planning\TaskLateLib::getIds($zonesBefore[\planning\TaskLateLib::UNPLANNED])
	);

	if($data->columns) {
		planningTaskWeekColumnsChange($data, $cTask, $before['statuses'], $idsArriving, $before['kept']);
	} else {
		$data->change = \planning\TaskBatchLib::getWeekChange($data->ccTask, $cTask, $idsArriving);
	}

	if($data->change === 'board') {
		planningTaskWeekReminders($data);
	}

	planningTaskWeekZonesChange($data, $zonesBefore, $cTask->getIds());

	throw new ViewAction($data, ':doBatch');

}

/**
 * Le pense-bête (R-PenseBete), quand le membre l'a allumé dans « Personnaliser le planning » : ce que la
 * semaine montre (`ReminderLib::getForWeek()`), ou rien (`NULL`) et pas de bloc.
 *
 * Lu seulement là où le bloc se dessine : la page, l'écran entier (`screen`, `planningTaskWeekScreen()`),
 * les blocs entiers (`board`), dont le pense-bête est la première carte, et les gestes du pense-bête. Les
 * gestes sur une carte d'intervention qui ne redessinent qu'elle ne le lisent pas : deux ou trois requêtes
 * de plus à chaque coche, pour un bloc que la réponse ne touche pas.
 */
function planningTaskWeekReminders(\stdClass $data): void {

	if($data->eMember['viewPlanningReminders']) {
		$data->reminders = \planning\ReminderLib::getForWeek($data->eStructure, $data->week);
	} else {
		$data->reminders = NULL;
	}

}

/**
 * Le rappel que vise un geste, de la structure de l'écran. Chaque geste valide ensuite son droit.
 */
function planningTaskWeekReminderGet(\stdClass $data): \planning\Reminder {

	return \planning\ReminderLib::getById(POST('reminder', 'int'))
		->validateProperty('structure', $data->eStructure);

}

/**
 * La réponse d'un geste du pense-bête, relue puis rendue par `doReminder` à la granularité décidée ici
 * (`$data->change`) :
 * - `line` : la coche ou la décoche d'un rappel, de la semaine ou resté, la ligne redessinée à sa place et
 *   la tête qui porte le compte ; l'ordre ne dépend pas de l'état fait (`ReminderLib::getForWeek()`), et un
 *   resté coché reste barré dans la semaine de sa coche ;
 * - `vanish` : un effacement, qui quitte la liste ; la ligne s'efface et la tête suit ;
 * - `restes` : le même départ pour le dernier resté, qui emmène aussi le titre de sa section ;
 * - `remove` : un départ dans la grille, sans copie animée ni titre de section. La copie que laisse
 *   `remove(vanish)` sort la ligne de `#planning-grid` et perd les colonnes qu'elle tient de lui
 *   (`subgrid`), et un resté s'y reconnaît à sa semaine, sans titre à emmener ;
 * - `block` : le bloc entier, parce que l'ordre peut bouger quand un rappel naît ou change de jour, ou
 *   parce qu'un départ l'a vidé et qu'il passe à l'état vide.
 * Un départ garde le bloc vivant hors du cas vide : la copie que laisse `remove(vanish)` se pose dans
 * l'ancêtre positionné le plus proche, ici le bloc, et disparaîtrait avec lui s'il était remplacé.
 */
function planningTaskWeekReminderRespond(\stdClass $data, string $gesture, \planning\Reminder $eReminder): void {

	$data->gesture = $gesture;
	$data->eReminder = $eReminder;

	planningTaskWeekReminders($data);

	$leaves = ($gesture === 'delete');

	if($data->reminders === NULL) {
		$data->change = 'block';
	} else if(
		($gesture === 'check' or $gesture === 'uncheck') and
		$data->reminders['cReminder']->offsetExists($eReminder['id'])
	) {
		$data->change = 'line';
		$data->eReminder = $data->reminders['cReminder'][$eReminder['id']];
		$data->before = FALSE;
	} else if(
		($gesture === 'check' or $gesture === 'uncheck') and
		$data->reminders['cReminderBefore']->offsetExists($eReminder['id'])
	) {
		$data->change = 'line';
		$data->eReminder = $data->reminders['cReminderBefore'][$eReminder['id']];
		$data->before = TRUE;
	} else if(
		$leaves === FALSE or
		($data->reminders['cReminder']->empty() and $data->reminders['cReminderBefore']->empty())
	) {
		$data->change = 'block';
	} else if($data->grid) {
		$data->change = 'remove';
	} else if($eReminder['week'] < $data->week and $data->reminders['cReminderBefore']->empty()) {
		$data->change = 'restes';
	} else {
		$data->change = 'vanish';
	}

	throw new ViewAction($data, ':doReminder');

}

/**
 * Un réglage de la page « Personnaliser le planning », une préférence du membre comme l'ordre, que la
 * Semaine relit à son ouverture. `$written` joue après l'écriture, pour ce qui gardait l'ancienne valeur.
 */
function planningTaskWeekCustomize(\stdClass $data, string $property, string $view, ?\Closure $written = NULL): void {

	if(OTF_DEMO) {
		throw new \FailAction('user\User::demo.write');
	}

	// Le membre n'est jamais vide ici : `canPlanningLimited`, validé par le constructeur de la page,
	// le demande non vide (`Member::isRole()`).
	$data->eMember->validate('canSelf');

	$fw = new FailWatch();

	$data->eMember->build([$property], $_POST);

	$fw->validate();

	\structure\MemberLib::update($data->eMember, [$property]);

	if($written !== NULL) {
		$written();
	}

	throw new ViewAction($data, $view);

}

new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canPlanningLimited');

		$data->week = INPUT('week', fn($value) => Filter::check('week', $value), \Clock::week());

		if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($data->week)) === FALSE) {
			throw new NotExpectedAction('Invalid week');
		}

		$data->eMember = $data->eStructure->getMember();
		$data->hide = $data->eStructure->getView('viewPlanningHideDone');
		$data->order = $data->eStructure->getView('viewPlanningOrder') ?? [];
		$data->unplanned = INPUT('unplanned', 'bool');
		$data->folded = array_values(array_intersect(explode(',', INPUT('folded', 'string', '')), [\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED, ...\planning\DueLib::getSources()]));
		$data->changeZones = [];
		$data->canCreate = new \series\Task(['structure' => $data->eStructure])->canCreate();
		// Une semaine passée ne prend plus de rappel : il y serait aussitôt reporté (`Reminder::week.notPast`).
		$data->canReminder = (
			new \planning\Reminder(['structure' => $data->eStructure])->canCreate() and
			$data->week >= \Clock::week()
		);
		$data->reminders = NULL;
		$data->locked = FALSE;
		// La légende du point du jour prévu vaut tant que la semaine a un jour à prévoir ; elle ne dépend d'aucun geste, et
		// aucune réponse ne la rejoue (DY).
		$data->legend = (week_dates($data->week)[6] >= \Clock::date());

		// L'affichage demandé, à défaut celui que le membre a choisi en dernier
		$data->viewRequested = \structure\Member::INPUT('view', 'viewPlanningWeek', $data->eStructure->getView('viewPlanningWeek'));
		$data->grid = ($data->viewRequested === \structure\Member::GRID);
		$data->columns = ($data->viewRequested === \structure\Member::COLUMNS);
		$data->ccTaskTodo = new Collection();
		$data->ccTaskDone = new Collection();
		$data->countByStatus = [];
		$data->countByAction = [];
		$data->canCreateByStatus = [];

		// L'affichage montré, que les panneaux ouverts d'ici réémettent pour que leur réponse vise cet écran.
		$data->view = $data->viewRequested;

	})
	/**
	 * ⚠️ Une écriture dans un GET, déclarée : `\series\RepeatLib::createForWeek()` matérialise les
	 * interventions répétées de la semaine avant de la lire, comme le planning actuel le fait à chaque
	 * ouverture (`series/page/index.p.php`). Sans elle, les répétitions manquent (charte, point 10 a).
	 * L'appel est idempotent : il ne crée que ce que l'ouverture de la semaine aurait créé de toute façon.
	 * La seconde écriture est l'affichage que l'interrupteur demande (`view`), retenu sur le membre connecté
	 * comme ses autres préférences, et qui ne change que son propre écran.
	 */
	->get(['/ferme/{id}/planning-v2/semaine', '/ferme/{id}/planning-v2/semaine/{week@week}'], function($data) {

		// L'affichage choisi par l'interrupteur se garde sur le membre
		if(get_exists('view')) {
			\structure\MemberLib::setView('viewPlanningWeek', $data->eStructure, $data->viewRequested);
		}

		\series\RepeatLib::createForWeek($data->eStructure, $data->week);

		planningTaskWeekRead($data);
		planningTaskWeekFill($data);
		planningTaskWeekReminders($data);

		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);

		throw new ViewAction($data, ':taskWeek');

	})
	->get(['/ferme/{id}/planning-v2/personnaliser', '/ferme/{id}/planning-v2/personnaliser/{week@week}'], function($data) {

		$data->eMember['cAction'] = \planning\TaskWeekLib::getActions($data->eStructure, $data->order);
		$data->origin = GET('origin', \planning\TabUi::SCREENS, 'semaine');

		throw new ViewAction($data, ':customize');

	})
	/**
	 * Les adresses de Répartir, retiré au profit de la grille (DF) : la Semaine de la même semaine, dans
	 * l'affichage que le membre a retenu.
	 */
	->get(['/ferme/{id}/planning-v2/repartir', '/ferme/{id}/planning-v2/repartir/{week@week}'], function($data) {
		throw new PermanentRedirectAction(\planning\TaskWeekUi::url($data->eStructure, $data->week));
	})
	/**
	 * « Non planifié », à sa première ouverture (L8) : ses blocs, plafonnés au-delà du seuil comme au
	 * rechargement. La réponse dit aussi aux gestes de réémettre que la zone est à l'écran.
	 */
	->get('unplanned', function($data) {

		$data->unplanned = TRUE;

		// Les zones sont en cartes dans les deux affichages : la réponse ne rend rien de la grille.
		$data->grid = FALSE;

		planningTaskWeekRead($data);

		\planning\TaskLateLib::fill($data->eStructure, $data->week, $data->zones[\planning\TaskLateLib::UNPLANNED]);

		throw new ViewAction($data, ':unplanned');

	})
	/**
	 * « Voir les N autres » d'un bloc plafonné (L8) : le bloc entier, toutes ses cartes.
	 */
	->get('more', function($data) {

		$data->zone = GET('zone');

		if(in_array($data->zone, [\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED], TRUE) === FALSE) {
			throw new NotExpectedAction('Invalid zone');
		}

		$data->action = GET('action', 'int');

		// « Non planifié » est en cartes dans les deux affichages, « En retard » en lignes dans la grille (DZ).
		if($data->zone === \planning\TaskLateLib::UNPLANNED) {
			$data->unplanned = TRUE;
			$data->grid = FALSE;
		}

		planningTaskWeekRead($data);

		if(array_key_exists($data->action, $data->zones[$data->zone]['blocks']) === FALSE) {
			throw new NotExpectedAction('Invalid action');
		}

		\planning\TaskLateLib::fillBlock($data->eStructure, $data->week, $data->zones[$data->zone], $data->action);

		planningTaskWeekGridZone($data);

		$data->rank = $data->zones[$data->zone]['blocks'][$data->action]['rank'];

		throw new ViewAction($data, ':more');

	})
	/**
	 * Ce que les jetons de la recherche retiennent dans les deux zones (R-Recherche-2), entières : « Non
	 * planifié » avant sa première ouverture et les cartes d'un bloc au-delà de son plafond, que l'écran ne
	 * porte pas.
	 */
	->get('search', function($data) {

		$criteria = GET('criteria', 'array', []);

		if(\planning\TaskSearchLib::isCriteria($criteria) === FALSE) {
			throw new NotExpectedAction('Invalid criteria');
		}

		$zones = [
			\planning\TaskLateLib::LATE => \planning\TaskSearchLib::getZone($data->eStructure, \planning\TaskLateLib::LATE, $data->week),
			\planning\TaskLateLib::UNPLANNED => \planning\TaskSearchLib::getZone($data->eStructure, \planning\TaskLateLib::UNPLANNED, $data->week),
		];

		$data->matches = \planning\TaskSearchLib::getMatchesZones($zones, $criteria);

		throw new ViewAction($data, ':search');

	})
	/**
	 * L'écran entier, que la page d'une intervention redemande quand une saisie de temps y fait passer une
	 * intervention d'une zone à la semaine, ou l'inverse (`taskDetail:doTime`). Le client réémet
	 * `unplanned` de l'écran resté derrière le panneau, qui n'en sait rien.
	 */
	->get('screen', function($data) {

		planningTaskWeekRead($data);
		planningTaskWeekFill($data);
		planningTaskWeekReminders($data);

		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);

		throw new ViewAction($data, ':screen');

	})
	/**
	 * La coche : l'intervention passe en fait dans la semaine affichée. Si la personne n'a encore aucun
	 * temps sur l'intervention, tous jours confondus, le temps prévu lui est posé au jour du fait
	 * (`\series\TaskLib::updateDoneWithTime()`, écart h de L5a, tranché par V-Planning), une seule fois
	 * donc, même recochée un autre jour. La bulle s'ouvre sur son temps de ce jour, à zéro si elle avait
	 * déjà du temps ailleurs. Une ferme qui ne suit pas le temps coche sans rien écrire d'autre et sans bulle.
	 */
	->post('doDone', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$before = planningTaskWeekBefore($data, new Collection([$eTask]));

		if($data->eStructure->hasTime()) {

			$eTimesheet = new \series\Timesheet([
				'structure' => $data->eStructure,
				'user' => \user\ConnectionLib::getOnline(),
			]);

			$eTimesheet->validate('canWrite', 'acceptWrite');

			\series\TaskLib::updateDoneWithTime($eTask, $eTimesheet, $data->week);

			$date = \series\TaskLib::getDoneTimeDate($eTask);

			// Faite dans une semaine à venir, l'intervention n'a pas encore de jour où poser du temps : la
			// bulle ne s'ouvre pas, elle proposerait un geste que `doTime` refuse. La grille n'a pas de bulle
			// (tranché par M-Planning le 14/10) : sa ligne porte déjà le temps de chaque jour.
			$data->canTime = ($date !== NULL and $data->grid === FALSE);

			// En Colonnes au téléphone, la carte cochée part dans l'onglet caché : la saisie monte dans une feuille
			// (`TaskCardUi::getSheet()`). Le client seul connaît sa largeur, et la poste avec la coche.
			$data->timeSheet = ($data->columns and POST('narrow', 'bool'));

			if($data->canTime) {
				$data->time = \series\TimesheetLib::getTimeByDate($eTask, $eTimesheet['user'], $date);
				$data->eUserTime = $eTimesheet['user'];
			}

		} else {
			$data->canTime = FALSE;
			\series\TaskLib::updateDoneCollection(new Collection([$eTask]), $data->week);
		}

		$data->gesture = 'done';

		planningTaskWeekRespond($data, $eTask, $before);

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * La décoche : l'intervention redevient à faire, et le temps passé reste, comme dans le
	 * planning actuel.
	 */
	->post('doTodo', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		$before = planningTaskWeekBefore($data, new Collection([$eTask]));

		\series\TaskLib::updateTodoCollection(new Collection([$eTask]));

		$data->gesture = 'todo';

		planningTaskWeekRespond($data, $eTask, $before);

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * Le jour d'une intervention à faire, depuis la case d'un jour à venir de la grille (CW) : l'écriture de
	 * `taskDay:doPlanned`, bornée à un jour de la semaine affichée, la seule que la case propose. En grille,
	 * une intervention commencée garde son jour (`Task::acceptPlanned()`, DF) ; le glisser des Cartes, qui
	 * poste ici aussi, n'est pas borné par ce verrou.
	 */
	->post('doPlanned', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'), \series\Task::getSelection())
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$before = planningTaskWeekBefore($data, new Collection([$eTask]));

		$fw = new FailWatch();

		if($data->grid and $eTask->acceptPlanned() === FALSE) {
			\series\Task::fail('planned.started');
		}

		$eTask->build(['planned'], $_POST);

		$fw->validate();

		if(in_array($eTask['plannedDate'], week_dates($data->week), TRUE) === FALSE) {
			throw new NotExpectedAction('Planned out of the week');
		}

		\series\TaskLib::updatePlannedDateCollection(new Collection([$eTask]), $eTask['plannedDate']);

		$data->gesture = 'planned';

		planningTaskWeekRespond($data, $eTask, $before);

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * Le jour retiré depuis son point dans la grille (DF, DN, DY) : l'intervention reste prévue dans la semaine,
	 * sans jour, et sa ligne garde sa place. Rien ne se poste que l'intervention : `build()` part d'une entrée
	 * écrite ici, la branche `week` de `planned` qui vide le jour, et un `planned` forgé ne peut pas reposer un
	 * jour. Comme pour `doPlanned`, l'intervention est de la semaine affichée, la seule dont la grille porte le
	 * point du jour et dont la réponse redessine les lignes.
	 */
	->post('doUnplanned', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'), \series\Task::getSelection())
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		if($eTask['plannedWeek'] !== $data->week) {
			throw new NotExpectedAction('Unplanned out of the week');
		}

		$before = planningTaskWeekBefore($data, new Collection([$eTask]));

		$fw = new FailWatch();

		if($eTask->acceptPlanned() === FALSE) {
			\series\Task::fail('planned.started');
		}

		$eTask->build(['planned'], ['planned' => ['week' => $data->week]]);

		$fw->validate();

		\series\TaskLib::updatePlannedCollection(new Collection([$eTask]), $eTask['plannedWeek']);

		$data->gesture = 'unplanned';

		planningTaskWeekRespond($data, $eTask, $before);

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * Le temps prévu, depuis la cellule « Prévu » de la grille (DF). La ligne ne change pas : la réponse ne
	 * porte que le travail estimé de la semaine, qui l'additionne. Une intervention faite garde le temps
	 * prévu qu'elle avait, son temps passé étant celui qui compte.
	 */
	->post('doTimeExpected', function($data) {

		$data->eStructure->validate('hasTime');

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$fw = new FailWatch();

		$eTask->build(['timeExpected'], $_POST);

		$fw->validate();

		\series\TaskLib::update($eTask, ['timeExpected']);

		$data->timeExpected = \planning\TaskWeekLib::getTimeExpected(\planning\TaskWeekLib::get($data->eStructure, $data->week), $data->week);

		throw new ViewAction($data, ':doTimeExpected');

	})
	/**
	 * La bulle : le total tapé est le temps de la personne connectée au jour du fait
	 * (`\series\TaskLib::getDoneTimeDate()`). Seul l'écart avec ce qu'elle a déjà ce jour-là s'écrit
	 * (`\series\TimesheetLib::writeTimeForDay()`, écart g de L5a tranché par V-Planning) : ses autres jours
	 * et les temps des autres restent tels quels. Zéro retire ses lignes de ce jour, c'est la flèche
	 * « Recommencer à zéro ». Une baisse ne peut rendre le jour négatif que sur un jour de deux lignes,
	 * qu'une concurrence ou l'API laissent, et se refuse alors (`Timesheet::time.negative`). Une
	 * intervention faite dans une semaine à venir n'a pas de jour où poser du temps. La durée est exigée
	 * (`time.missing`, liste `day`) : absente, elle vaudrait zéro et retirerait le jour.
	 */
	->post('doTime', function($data) {

		$data->eStructure->validate('hasTime');

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		$fw = new FailWatch();

		$eTimesheet = new \series\Timesheet([
			'structure' => $data->eStructure,
			'user' => \user\ConnectionLib::getOnline(),
			'date' => \series\TaskLib::getDoneTimeDate($eTask),
		]);

		$eTimesheet->build(['time'], $_POST, new \PropertyList('day'));

		if($eTimesheet['date'] === NULL) {
			\series\Timesheet::fail('date.future');
		}

		$fw->validate();

		$eTimesheet->validate('canWrite', 'acceptWrite');

		$before = planningTaskWeekBefore($data, new Collection([$eTask]));

		\series\TimesheetLib::writeTimeForDay($eTask, $eTimesheet);

		$fw->validate();

		$data->gesture = 'time';

		planningTaskWeekRespond($data, $eTask, $before);

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * Le tableau de l'équipe d'un jour de la grille (CW, DC), à l'ouverture de son menu
	 * (`planningTaskWeekTeam()`), pour un jour passé ou aujourd'hui de la semaine affichée, sur une
	 * intervention que la personne connectée peut écrire, les seuls jours dont la case ouvre ce menu. Avec
	 * `user`, la ligne d'une personne choisie dans « Ajouter une personne », comme `taskDetail:user` : rien ne
	 * s'écrit, et la liste ne propose que les personnes où un geste est possible (`addable`). Toutes les
	 * saisies du menu sont déjà ouvertes (ET), et aucune ne prend le focus (retour 403).
	 */
	->get('time', function($data) {

		$data->eStructure->validate('hasTime');

		$data->eTask = \series\TaskLib::getById(GET('task', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		$date = GET('date');

		if(in_array($date, week_dates($data->week), TRUE) === FALSE or $date > \Clock::date()) {
			throw new NotExpectedAction('Date not spendable');
		}

		$userIdsAdded = planningTaskWeekAdded();
		$user = GET('user', '?int');

		if($user !== NULL) {

			$cUserBefore = \planning\TaskDetailLib::getUsers($data->eTask, $data->week, $userIdsAdded);

			if(
				$cUserBefore->offsetExists($user) === FALSE or
				$cUserBefore[$user]['addable'] === FALSE
			) {
				throw new NotExpectedAction('User can not be added');
			}

			$userIdsAdded[] = $user;

		}

		planningTaskWeekTeam($data, $data->eTask, $date, $userIdsAdded);

		throw new ViewAction($data, ':time');

	})
	/**
	 * Le temps de toutes les personnes d'un jour, depuis l'« Enregistrer » commun du menu de ce jour dans la
	 * grille (ET, retour 398) : `time[<user>]` pour les seules durées changées, une requête pour toutes. Les
	 * gardes sont celles de la saisie d'une personne : le suivi du temps, une intervention que la personne
	 * connectée peut écrire, un jour de la semaine affichée (`date.week`), jamais à venir (étape `future` de
	 * `Timesheet`). Les personnes sont celles du menu (`TaskDetailLib::getUsers()`), et seule une ligne qui peut
	 * écrire le temps (`canTime`) a une saisie : `TimeTeamLib::prepareDay()` refuse les autres et vérifie tout,
	 * avant que `writeDay()` n'écrive toutes les durées ou aucune.
	 *
	 * Le menu se ferme une fois la réponse acceptée (FJ, retour 411, « Fermer après Enregistrer » partout) : sur
	 * une ligne d'un bloc qui reste à sa place (`card`), la réponse ne redessine que les jours de la ligne et sa
	 * semaine (`:doTimeTeam`), jamais le contenu du menu, qui se relit à sa prochaine ouverture (`time`). Le jour
	 * du menu (`dateTeam`) suffit à retrouver son déclencheur. Ailleurs, la ligne change de place, ou entre dans
	 * les blocs depuis une zone, et la réponse est celle de tout geste sur une ligne (`:doTask`).
	 */
	->post('doTimeTeam', function($data) {

		$data->eStructure->validate('hasTime');

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		$date = POST('date');

		$fw = new FailWatch();

		if(in_array($date, week_dates($data->week), TRUE) === FALSE) {
			\series\Timesheet::fail('date.week');
		}

		$fw->validate();

		$cUser = \planning\TaskDetailLib::getUsers($eTask, $data->week, planningTaskWeekAdded());

		$cTimesheet = \planning\TimeTeamLib::prepareDay($data->eStructure, $eTask, $cUser, $date, $_POST);

		$fw->validate();

		$before = planningTaskWeekBefore($data, new Collection([$eTask]));

		// Avant l'écriture, qui recalcule le temps sur l'élément qu'elle reçoit (`TaskLib::recalculateTime()`).
		$acceptPlannedBefore = $eTask->acceptPlanned();

		\planning\TimeTeamLib::writeDay($eTask, $cTimesheet);

		$data->gesture = 'time';

		planningTaskWeekRespond($data, $eTask, $before);

		if($data->change === 'card') {

			$data->eTaskRow = $data->ccTask[$eTask['action']['id']][$eTask['id']];

			// Les autres jours de la ligne ne montrent pas le temps de ce jour : ils ne changent que si l'intervention
			// cesse ou recommence d'accepter un jour prévu, en recevant son premier temps ou en perdant le dernier.
			$data->datesChanged = ($acceptPlannedBefore === $data->eTaskRow->acceptPlanned()) ?
				[] :
				array_values(array_diff(week_dates($data->week), [$date]));

			$data->dateTeam = $date;

			throw new ViewAction($data, ':doTimeTeam');

		}

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * La liste « Affecter à... » d'une ligne de la grille (CW), à l'ouverture de son menu : les personnes que
	 * la personne connectée peut affecter (`canAssign`, `TaskDetailLib::getUsers()`). Une intervention faite
	 * n'a pas de menu, et la route la refuse.
	 */
	->get('users', function($data) {

		$data->eTask = \series\TaskLib::getById(GET('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$data->cUser = \planning\TaskDetailLib::getUsers($data->eTask, $data->week);

		throw new ViewAction($data, ':users');

	})
	/**
	 * Une personne affectée ou retirée depuis la liste d'une ligne de la grille (CW), ou depuis le tableau du
	 * menu d'un jour (DC), qui poste son jour (`date`) et ses lignes ajoutées : l'écriture et les gardes de
	 * `taskDetail:doUser` (`\series\TaskLib::updateUser()`). La réponse ne redessine que le déclencheur et
	 * l'entrée cliquée, plus le tableau du jour quand le geste en vient, le menu restant ouvert. Elle repose
	 * aussi ce que la recherche lit sur la ligne (`search`), qui nomme les personnes affectées, et d'où la
	 * liste des personnes de l'omnibox se relève (EJ).
	 */
	->post('doUser', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$date = post_exists('date') ? POST('date') : NULL;

		if($date !== NULL and (in_array($date, week_dates($data->week), TRUE) === FALSE or $date > \Clock::date())) {
			throw new NotExpectedAction('Date not spendable');
		}

		$cUserMember = \hr\TeammateLib::getUsersForPlanning($data->eStructure);
		$user = POST('user', 'int');

		if($cUserMember->offsetExists($user) === FALSE) {
			throw new NotExpectedAction('User not in structure');
		}

		$action = POST('action', ['add', 'delete'], fn() => throw new NotExpectedAction('Invalid action'));

		$cUserBefore = \planning\TaskDetailLib::getUsers($eTask, $data->week);

		if($cUserBefore[$user]['canAssign'] === FALSE) {
			throw new NotExpectedAction('User can not be assigned');
		}

		\series\TaskLib::updateUser(new Collection([$eTask]), $cUserMember[$user], $action);

		// L'écriture passe par `Module::model()->update()`, qui travaille sur un clone : l'élément posté garde
		// ses anciens affectés, la ligne se relit.
		$data->eTask = \series\TaskLib::getById($eTask['id']);
		$data->eTask['cUserPlanned'] = \planning\TaskWeekLib::getUsersPlanned($data->eTask, $cUserMember);
		\planning\TaskWeekLib::fillHarvestWeek($data->eTask, $data->week);
		$data->eTask['search'] = \planning\TaskSearchLib::getTask($data->eStructure, $data->week, $data->eTask, $cUserMember, \planning\TaskSearchLib::getCategories($data->eStructure));
		$data->eUser = $cUserMember[$user];
		$data->cUser = $cUserMember;

		$data->dateTeam = NULL;

		if($date !== NULL) {
			planningTaskWeekTeam($data, $data->eTask, $date, planningTaskWeekAdded());
		}

		throw new ViewAction($data, ':doUser');

	})
	/**
	 * « Récolter » sur une carte (retour 203) : la saisie du panneau ou de la feuille de l'écran des
	 * récoltes, qui écrit comme lui (`HarvestWeekLib::writeEntry()`) dans la semaine affichée, et répond par
	 * la carte de l'intervention écrite. Une saisie qui la crée la fait entrer dans son bloc.
	 *
	 * Le formulaire poste sous `view` l'affichage des Récoltes, jour ou semaine, dont le nom est celui de
	 * l'affichage de la Semaine : ses valeurs ne sont pas celles de `viewPlanningWeek`, que le constructeur
	 * refuse donc, et la réponse suit la grille ou les cartes que le membre a choisies.
	 */
	->post('doHarvest', function($data) {

		if(\planning\HarvestWeekLib::canHarvest($data->eStructure) === FALSE) {
			throw new NotAllowedAction();
		}

		// La culture n'est que le parent de l'écriture, comme dans `harvestWeek:doHarvest`.
		$eCultivation = \series\CultivationLib::getById(POST('cultivation', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		// La carte d'où part la saisie, que l'écriture préfère, se refuse comme à l'ouverture quand elle n'est
		// pas une récolte de cette culture.
		$eTaskCard = \planning\HarvestWeekLib::getTaskCard($data->eStructure, $eCultivation, POST('task', 'int'));

		if($eTaskCard->empty()) {
			throw new NotExpectedAction('Not a harvest of this cultivation');
		}

		$before = planningTaskWeekBefore($data, new Collection([$eTaskCard]));

		$entry = \planning\HarvestWeekLib::writeEntry($data->eStructure, $eCultivation, \planning\HarvestWeekLib::getDays($data->week), $_POST, $eTaskCard);

		$data->gesture = 'harvest';
		$data->sheet = POST('sheet', 'bool');

		planningTaskWeekRespond($data, $entry['eTask'], $before, $entry['created']);

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * « Nouvelle intervention » (L5d, `TaskCreateUi`) : « + À faire » et « + Fait » de la rangée des outils ou
	 * d'une colonne des Colonnes, ou le « + » d'un bloc, qui pose son action. Fait s'ouvre aujourd'hui sur la semaine en cours, sur son dernier jour sur une semaine
	 * passée, et À faire sur « Non défini » (`TaskCreateLib::getDateDefault()`). La semaine de l'intervention
	 * (`when`) est d'abord celle de l'écran.
	 * Ouvert depuis la fiche d'une série du plan d'assolement (`origin`, GM), il part rempli de ses cultures et
	 * poste sur la fiche, qui se redessine.
	 */
	->get('create', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$sheet = (GET('origin', ['assolement'], '') === 'assolement');

		$data->page = $sheet ? 'placeSheet' : 'taskWeek';
		[$data->eAction, $data->eCategoryAction] = \planning\TaskCreateLib::getAction($data->eStructure, GET('action', '?int'));
		$data->eTask = \planning\TaskCreateLib::getNew($data->eStructure, $data->eAction, $data->eCategoryAction);

		if($sheet) {

			$eSeries = \series\SeriesLib::getById(GET('series', 'int'))
				->validate('canRead')
				->validateProperty('structure', $data->eStructure);

			$data->eTask['cCultivationChosen'] = \planning\HarvestWeekLib::getForTaskSeries($data->eStructure, $eSeries, $data->week);

		}

		// « + À faire », « + Fait » et le « + » d'un bloc des Colonnes demandent leur état (GQ), le « + » d'un bloc
		// des Cartes ouvre sur Fait.
		$status = GET('status', [\series\Task::TODO, \series\Task::DONE], NULL);

		$data->days = \planning\TaskCreateLib::getDays($data->week);
		$data->date = \planning\TaskCreateLib::getDateDefault($data->days, $status);
		$data->status = \planning\TaskCreateLib::getStatus($data->days, $data->date, $status ?? \series\Task::DONE);
		$data->when = $data->week;
		$data->weeks = \planning\TaskCreateLib::getWeeks($data->eStructure, $data->status);
		$data->cActionShortcut = \planning\TaskCreateLib::getShortcuts($data->eStructure, $data->eAction);
		$data->post = [
			'view' => $data->view,
		];

		// La fiche redessine la série d'où vient le panneau.
		if($sheet) {
			$data->post['series'] = $eSeries['id'];
		}

		$data->cUserTime = \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->week);

		throw new ViewAction($data, 'planning/taskDay:create');

	})
	/**
	 * Les interventions neuves, faites ou à faire dans la semaine choisie (`when`), une par culture choisie. Elles
	 * partagent action et semaine, donc un bloc : la réponse le rend, ou les blocs entiers quand elles l'ouvrent
	 * (`TaskBatchLib::getCreateChange()`), rien quand l'écran ne les montre pas, et un message quand elles sont
	 * d'une autre semaine (`TaskCreateLib::getSuccessOutside()`). À faire sur une semaine passée, elles entrent
	 * dans « En retard », que la réponse redessine comme après tout geste (`planningTaskWeekZonesChange()`).
	 */
	->post('doCreate', function($data) {

		$when = POST('when', fn($value) => Filter::check('week', $value)) ?? throw new NotExpectedAction('Invalid week');

		$zonesBefore = planningTaskWeekZones($data);

		$data->cTaskCreated = \planning\TaskCreateLib::createCollection($data->eStructure, $when, $_POST);
		$data->eTask = $data->cTaskCreated->first();
		$data->success = \planning\TaskCreateLib::getSuccessOutside($data->cTaskCreated, $when, [$data->week]);

		// L'intervention neuve n'est jamais une carte gardée : seules celles que l'écran garde déjà le restent.
		planningTaskWeekRead($data, tasksKept: planningTaskWeekKept());

		if(planningTaskWeekScreen($data, $data->cTaskCreated)) {
			$data->change = 'screen';
			throw new ViewAction($data, ':doCreate');
		}

		if($data->columns) {
			planningTaskWeekColumnsChange($data, $data->cTaskCreated, [], $data->cTaskCreated->getIds());
		} else {
			$data->change = \planning\TaskBatchLib::getCreateChange($data->ccTask, $data->eTask, $data->cTaskCreated->count());
		}

		if($data->change === 'block') {
			$data->rank = array_search($data->eTask['action']['id'], $data->ccTask->getKeys());
		} else if($data->change === 'board') {
			planningTaskWeekReminders($data);
		}

		planningTaskWeekZonesChange($data, $zonesBefore, $data->cTaskCreated->getIds());

		throw new ViewAction($data, ':doCreate');

	})
	/**
	 * Les gestes de lot (L5d, `TaskBatchUi`), une route par geste comme l'ancien planning
	 * (`series/page/task.p.php`), dont ils reprennent la validation et l'écriture.
	 */
	->post('doBatchDone', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$before = planningTaskWeekBefore($data, $cTask);

		\series\TaskLib::updateDoneCollection($cTask, $data->week);

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	->post('doBatchTodo', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isDone', 'canWrite');

		$before = planningTaskWeekBefore($data, $cTask);

		\series\TaskLib::updateTodoCollection($cTask);

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	->post('doBatchPlannedDate', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$before = planningTaskWeekBefore($data, $cTask);

		\series\TaskLib::updatePlannedDateCollection($cTask, \planning\TaskBatchLib::getPlanned('plannedDate', $_POST));

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	->post('doBatchPlannedWeek', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$before = planningTaskWeekBefore($data, $cTask);

		\series\TaskLib::updatePlannedCollection($cTask, \planning\TaskBatchLib::getPlanned('plannedWeek', $_POST));

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	->post('doBatchIncrement', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('acceptPostpone', 'canWrite');

		$before = planningTaskWeekBefore($data, $cTask);

		$fw = new FailWatch();

		\series\TaskLib::incrementPlannedCollection($cTask, POST('increment', 'int'));

		$fw->validate();

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	->post('doBatchUser', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$before = planningTaskWeekBefore($data, $cTask);

		[$eUser, $action] = \planning\TaskBatchLib::getUser($data->eStructure, $_POST);

		\series\TaskLib::updateUser($cTask, $eUser, $action);

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	->post('doBatchDelete', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('canDelete');

		$before = planningTaskWeekBefore($data, $cTask);

		\series\TaskLib::deleteCollection($cTask);

		planningTaskWeekBatch($data, $cTask, 'batch', $before);

	})
	/**
	 * « Temps » (R-Retours-0410, point 10, refait au lot BG) : le temps de la personne connectée, un total pour
	 * un jour partagé entre les interventions cochées, quelle que soit leur action. Le Jour ouvre le même
	 * panneau (`taskDay.p.php`).
	 *
	 * Le droit est celui du temps et non celui de l'intervention (décision de V-Planning du 04/10, voie ii) : le
	 * rôle planning limité saisit son propre temps (`\series\Timesheet::canWrite()`), comme il le faisait par
	 * « Temps de travail », que ce geste remplace. Une intervention à faire lui reste en lecture
	 * (`TaskBatchLib::fillTimes()`, `timeLocked`), puisque saisir son temps la passerait faite.
	 */
	->get('batchTime', function($data) {

		$data->eStructure->validate('hasTime');

		$data->cTaskBatch = \planning\TaskBatchLib::getByIds($data->eStructure, GET('ids', 'array'));
		$data->cTaskBatch->validate('canRead');

		$eUser = \user\ConnectionLib::getOnline();

		new \series\Timesheet([
			'structure' => $data->eStructure,
			'user' => $eUser,
		])->validate('canWrite', 'acceptWrite');

		\planning\TaskBatchLib::fillTimes($data->cTaskBatch, $data->week);

		[$data->days, $data->date] = \planning\TaskBatchLib::getTimeDays($data->week);
		[$data->distributions, $data->distribution] = \planning\TaskBatchLib::getDistributions($data->cTaskBatch);

		$data->page = 'taskWeek';
		$data->post = [
			'unplanned' => $data->unplanned ? '1' : '',
			'folded' => implode(',', $data->folded),
			'view' => $data->view,
		];

		throw new ViewAction($data, 'planning/taskWeek:batchTime');

	})
	/**
	 * Le temps total s'ajoute à celui de la personne connectée le jour choisi, partagé selon la clé, et une
	 * intervention à faire qui reçoit une part passe faite (`TaskBatchLib::writeTimes()`, lot BG). Seules les
	 * interventions écrites se redessinent.
	 */
	->post('doBatchTime', function($data) {

		$data->eStructure->validate('hasTime');

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('canRead');

		$eUser = \user\ConnectionLib::getOnline();

		new \series\Timesheet([
			'structure' => $data->eStructure,
			'user' => $eUser,
		])->validate('canWrite', 'acceptWrite');

		$fw = new FailWatch();

		\planning\TaskBatchLib::fillTimes($cTask, $data->week);
		$cTimesheet = \planning\TaskBatchLib::prepareTimes($data->eStructure, $cTask, $eUser, $data->week, $_POST);

		$fw->validate();

		$before = planningTaskWeekBefore($data, $cTask);

		\planning\TaskBatchLib::writeTimes($cTimesheet, $data->week);

		planningTaskWeekBatch($data, $cTask->find(fn($eTask) => $cTimesheet->offsetExists($eTask['id'])), 'batchTime', $before);

	})
	/**
	 * « Récolter » (DG-1, planche planning-dg-plusieurs-series, cadre r) : une quantité pour un jour, partagée
	 * entre des récoltes de la même espèce, variété et calibre (`TaskBatchLib::validateHarvests()`), avec son
	 * stock et son temps, partagés par la même clé. Le Jour ouvre le même panneau (`taskDay.p.php`).
	 *
	 * Récolter écrit l'intervention : le rôle planning limité ne récolte pas (`\series\Task::canWrite()`), comme
	 * dans Récoltes (`HarvestWeekLib::canHarvest()`). Une semaine à venir n'a aucun jour où récolter.
	 */
	->get('batchHarvest', function($data) {

		$data->cTaskBatch = \planning\TaskBatchLib::getByIds($data->eStructure, GET('ids', 'array'));
		$data->cTaskBatch->validate('canWrite');

		\planning\TaskBatchLib::validateHarvests($data->cTaskBatch);

		if($data->week > \Clock::week()) {
			throw new NotExpectedAction('Future week');
		}

		[
			'days' => $data->days,
			'date' => $data->date,
			'distributions' => $data->distributions,
			'distribution' => $data->distribution,
			'unit' => $data->unit,
			'ePlant' => $data->ePlant,
			'tasksByUnit' => $data->tasksByUnit,
			'eTaskStock' => $data->eTaskStock,
		] = \planning\TaskBatchLib::getHarvestPanel($data->cTaskBatch, $data->week);

		$data->cUserTime = \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->week);
		// Le couple ne se choisit qu'aux Récoltes, qui cochent des parts (HM2).
		$data->eCultivationChoice = new \series\Cultivation();

		$data->page = 'taskWeek';
		$data->post = [
			'unplanned' => $data->unplanned ? '1' : '',
			'folded' => implode(',', $data->folded),
			'view' => $data->view,
		];

		throw new ViewAction($data, 'planning/taskWeek:batchHarvest');

	})
	/**
	 * La récolte s'ajoute à chaque série le jour choisi, puis le stock et le temps (`TaskBatchLib::writeHarvests()`).
	 * Une récolte à faire qui reçoit une quantité passe faite, comme dans Récoltes. Le temps est celui de chaque
	 * personne de l'équipe (`time[<user>]`), que le droit du temps borne (`TimeTeamLib::getUsersForWeek()`).
	 */
	->post('doBatchHarvest', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('canWrite');

		\planning\TaskBatchLib::validateHarvests($cTask);

		if($data->week > \Clock::week()) {
			throw new NotExpectedAction('Future week');
		}

		$eUser = \user\ConnectionLib::getOnline();

		if($data->eStructure->hasTime()) {
			new \series\Timesheet([
				'structure' => $data->eStructure,
				'user' => $eUser,
			])->validate('canWrite', 'acceptWrite');
		}

		\planning\TaskBatchLib::fillTimes($cTask, $data->week);

		$harvest = \planning\TaskBatchLib::prepareHarvests($data->eStructure, $cTask, $data->week, $_POST);

		$before = planningTaskWeekBefore($data, $cTask);

		\planning\TaskBatchLib::writeHarvests($harvest);

		planningTaskWeekBatch($data, $cTask, 'batchHarvest', $before);

	})
	/**
	 * Le lâcher d'une ligne de la liste de « Personnaliser le planning » (L5e, R-Retours-0410-soir) : le client
	 * envoie l'intention, l'action et la ligne qui la suit (`before`, vide en fin de liste), jamais l'ordre
	 * entier, et le serveur la rejoue sur l'ordre enregistré, complété de la liste (`TaskWeekLib::getActions()`),
	 * puis `applyMove()`. Une action hors de la semaine et jamais placée se range donc là où on l'a lâchée,
	 * et une action inactive garde son rang. La liste porte toutes les actions actives : une action
	 * désactivée depuis l'ouverture de la page est refusée avec un message, que l'écran ne peut pas prévenir,
	 * et une action d'une autre structure ne se rencontre pas sans forger la requête.
	 *
	 * La réponse est vide : la ligne est déjà à sa place à l'écran.
	 */
	->post('doOrder', function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

		// Le membre n'est jamais vide ici : `canPlanningLimited`, validé par le constructeur de la page,
		// le demande non vide (`Member::isRole()`).
		$data->eMember->validate('canSelf');

		$action = POST('action', 'int');
		$before = POST('before', '?int');

		$cAction = \planning\TaskWeekLib::getActions($data->eStructure, $data->order);
		$cActionInactive = \work\ActionLib::getByStructure($data->eStructure, index: 'id', search: new Search(['status' => \work\Action::INACTIVE]));

		$actionsOutside = array_filter([$action, $before], fn(?int $id) => ($id !== NULL and $cAction->offsetExists($id) === FALSE));

		$fw = new FailWatch();

		if($actionsOutside !== []) {

			if(array_diff($actionsOutside, $cActionInactive->getIds()) !== []) {
				throw new NotExpectedAction('Action not in structure');
			}

			Fail::log('structure\Member::viewPlanningOrder.inactive');

		}

		$fw->validate();

		$base = array_values(array_unique(array_merge(
			array_filter($data->order, fn(int $id) => ($cAction->offsetExists($id) or $cActionInactive->offsetExists($id))),
			$cAction->getIds()
		)));

		// La fin de la liste est derrière la dernière action active : celles qui la suivent, toutes inactives,
		// gardent leur rang derrière l'action lâchée.
		if($before === NULL) {

			$tail = $base;

			while($tail !== [] and $cActionInactive->offsetExists(end($tail))) {
				$before = array_pop($tail);
			}

		}

		$order = \planning\TaskWeekLib::applyMove($base, $action, $before);

		$data->eMember['cAction'] = $cAction->appendCollection($cActionInactive);
		$data->eMember->build(['viewPlanningOrder'], ['viewPlanningOrder' => $order]);

		$fw->validate();

		\structure\MemberLib::update($data->eMember, ['viewPlanningOrder']);

		throw new VoidAction();

	})
	->post('doDensity', fn($data) => planningTaskWeekCustomize($data, 'viewPlanningDensity', ':doDensity'))
	->post('doColor', fn($data) => planningTaskWeekCustomize($data, 'viewPlanningColor', ':doColor'))
	->post('doReminders', fn($data) => planningTaskWeekCustomize($data, 'viewPlanningReminders', ':doReminders'))
	->post('doSalesDays', fn($data) => planningTaskWeekCustomize($data, 'viewPlanningSalesDays', ':doSalesDays', fn() => \planning\DashboardLib::forgetSales($data->eStructure, \user\ConnectionLib::getOnline())))
	/**
	 * « Nouveau rappel » (R-PenseBete), le « + » du pense-bête.
	 */
	->get('reminderCreate', function($data) {

		if($data->canReminder === FALSE) {
			throw new NotAllowedAction();
		}

		$data->eReminder = new \planning\Reminder([
			'structure' => $data->eStructure,
			'week' => $data->week,
			'date' => NULL,
			'text' => NULL,
			'days' => \planning\ReminderLib::getDays($data->week),
		]);

		throw new ViewAction($data, ':reminderCreate');

	})
	/**
	 * Le rappel neuf, dans la semaine de l'écran : `$data->week`, validée par le constructeur (format et
	 * saison), passe avant ce que le formulaire poste.
	 */
	->post('doReminderCreate', function($data) {

		$eReminder = new \planning\Reminder(['structure' => $data->eStructure]);
		$eReminder->validate('canCreate');

		$fw = new FailWatch();

		$eReminder->build(\planning\ReminderLib::getPropertiesCreate(), ['week' => $data->week] + $_POST, new \PropertyList('create'));

		$fw->validate();

		\planning\ReminderLib::create($eReminder);

		planningTaskWeekReminderRespond($data, 'create', $eReminder);

	})
	/**
	 * La coche et la décoche d'un rappel, partagé par l'équipe. Un rappel d'une autre structure que celle
	 * de l'écran est refusé, même à qui écrit dans les deux : la réponse redessine le pense-bête de cet
	 * écran-ci. Un rappel déjà dans l'état demandé (une autre personne l'a coché depuis l'ouverture de la
	 * page) ne s'écrit pas et ne lève rien : l'intention est satisfaite, et le bloc redessiné montre qui
	 * l'a fait.
	 */
	->post('doReminderCheck', function($data) {

		$eReminder = planningTaskWeekReminderGet($data)->validate('canWrite');

		if($eReminder->acceptCheck()) {
			\planning\ReminderLib::check($eReminder);
		}

		planningTaskWeekReminderRespond($data, 'check', $eReminder);

	})
	->post('doReminderUncheck', function($data) {

		$eReminder = planningTaskWeekReminderGet($data)->validate('canWrite');

		if($eReminder->acceptUncheck()) {
			\planning\ReminderLib::uncheck($eReminder);
		}

		planningTaskWeekReminderRespond($data, 'uncheck', $eReminder);

	})
	/**
	 * Le texte et le jour corrigés sur place. La semaine ne se poste pas : le jour se borne à celle du
	 * rappel, qui reste la sienne même vu depuis une semaine suivante. Les cases de jour de la grille
	 * postent `origin=date`, qui ne construit que le jour : le texte d'un autre membre n'est jamais
	 * réécrit par un clic.
	 */
	->post('doReminderUpdate', function($data) {

		$eReminder = planningTaskWeekReminderGet($data)
			->validate('canUpdate')
			->validate('acceptUpdate', onFail: fn() => throw new FailAction('planning\Reminder::updatePast'));

		$fw = new FailWatch();

		$origin = POST('origin', ['date'], NULL);
		$properties = \planning\ReminderLib::getPropertiesUpdate($origin);

		$eReminder->build($properties, $_POST, new \PropertyList('update', $origin));

		$fw->validate();

		\planning\ReminderLib::update($eReminder, $properties);

		planningTaskWeekReminderRespond($data, 'update', $eReminder);

	})
	->post('doReminderDelete', function($data) {

		$eReminder = planningTaskWeekReminderGet($data)
			->validate('canDelete')
			->validate('acceptDelete', onFail: fn() => throw new FailAction('planning\Reminder::deletePast'));

		\planning\ReminderLib::delete($eReminder);

		planningTaskWeekReminderRespond($data, 'delete', $eReminder);

	});

?>
