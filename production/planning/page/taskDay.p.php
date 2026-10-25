<?php
/**
 * Le Jour de l'écran « Interventions » (L5c) : la semaine en colonnes, « Sans jour » puis un jour par
 * colonne (`TaskDayUi`).
 *
 * Les gestes de la carte sont ceux de la Semaine, la coche, la décoche et le temps, plus le jour : chacun
 * a sa route ici et non un `origin` sur celles de `taskWeek.p.php` (écart déclaré à la livraison), parce
 * que tout ce qui suit l'écriture diffère, la lecture, la bulle qui n'existe pas ici et la réponse.
 * L'écriture, elle, est la même, par les mêmes méthodes de `\series\TaskLib` et `\series\TimesheetLib`.
 *
 * Chaque geste répond par les colonnes qu'il change (`TaskDayLib::getChange()`) : celles où
 * l'intervention était, lues avant d'écrire pour elle seule (`TaskDayLib::getKeysForTask()`), et celles
 * où elle est après. « Masquer ce qui est fait » est la préférence du membre, relue à chaque requête
 * comme dans la Semaine.
 *
 * Le pense-bête de la Semaine se range ici par colonne (CC) : ses gestes ont aussi leurs routes, sur le
 * modèle de l'accueil (`dashboard.p.php`), pour la même raison que ceux de la carte, la réponse qui
 * redessine les rappels des colonnes touchées. L'écriture et ses droits sont ceux de la Semaine.
 */

/**
 * Les interventions faites que « Sans jour » garde jusqu'au rechargement (`TaskDayLib::getColumns()`), et
 * pour chacune le temps qu'y avait la personne connectée avant la coche : celles que l'écran y garde déjà,
 * qu'il réémet à chaque geste (`kept`), avec ce temps, et celle du geste si sa carte y a été cliquée (`column`). Sans la
 * réémission, la colonne redessinée par une seconde coche perdrait la carte de la première.
 *
 * @param float $timeBefore Le temps de la personne avant la coche du geste, que seule la coche connaît.
 */
function planningTaskDayKept(\series\Task $eTask, float $timeBefore = 0.0): array {

	$tasks = planningTaskDayKeptPosted();

	if(POST('column') === \planning\TaskDayLib::NONE) {
		$tasks[$eTask['id']] = $timeBefore;
	}

	return $tasks;

}

/**
 * Les interventions que « Sans jour » garde déjà, réémises par l'écran (`kept`) avec le temps d'avant leur
 * coche, que tout geste du Jour repasse à `TaskDayLib::getColumns()` : carte, lot, et panneau « Nouvelle
 * intervention », dont l'intervention neuve n'est jamais une carte gardée.
 */
function planningTaskDayKeptPosted(): array {

	$tasks = [];

	foreach(POST('kept', 'array', []) as $task => $value) {
		$tasks[(int)$task] = (float)$value;
	}

	return $tasks;

}

/**
 * L'écran entier quand la rangée des outils apparaît ou disparaît (R-Retours-0410, point 18), comme à la
 * Semaine (`planningTaskWeekScreen()`, `TaskWeekLib::isEmpty()`).
 */
function planningTaskDayScreen(\stdClass $data, \Collection $cTaskCreated): bool {

	$data->empty = \planning\TaskWeekLib::isEmpty($data->cTask);

	if(\planning\TaskWeekLib::isEmpty($data->cTask, cTaskCreated: $cTaskCreated) === FALSE) {
		return FALSE;
	}

	$data->weekNext = \planning\TaskDayLib::getNextWeek($data->week);
	$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);

	return TRUE;

}

/**
 * Le pense-bête de la semaine rangé par colonne (`ReminderLib::getByDay()`), quand le membre l'a allumé
 * à la Semaine, NULL sinon.
 */
function planningTaskDayReminders(\stdClass $data): void {

	$data->reminders = $data->eMember['viewPlanningReminders'] ?
		\planning\ReminderLib::getByDay($data->eStructure, $data->week) :
		NULL;

}

/**
 * La réponse d'un geste : la semaine relue avec l'intervention gardée même masquée, et les colonnes que le
 * geste change, ou l'écran entier (`planningTaskDayScreen()`).
 *
 * Une colonne redessinée porte son pense-bête, relu donc à chaque geste, pense-bête allumé : deux ou trois
 * requêtes de plus, chacune sur un index du rappel (`ReminderLib::getForWeek()`).
 *
 * @param array $keysBefore Les colonnes où l'intervention était avant le geste.
 * @param array $tasksKept Les interventions faites gardées dans « Sans jour » (`planningTaskDayKept()`).
 */
function planningTaskDayRespond(\stdClass $data, \series\Task $eTask, array $keysBefore, array $tasksKept = []): void {

	$data->eTask = $eTask;

	$data->cTask = \planning\TaskWeekLib::get($data->eStructure, $data->week);

	$data->columns = \planning\TaskDayLib::getColumns($data->week, $data->cTask, \planning\TaskDayLib::getDates($data->eStructure, $data->week), \user\ConnectionLib::getOnline(), $data->hide, $eTask, $tasksKept);
	$data->cUser = \hr\TeammateLib::getUsersForPlanning($data->eStructure);

	planningTaskDayReminders($data);

	if(planningTaskDayScreen($data, new \Collection())) {
		$data->change = 'screen';
		return;
	}

	$data->change = \planning\TaskDayLib::getChange($data->columns, $keysBefore, $eTask);

}

/**
 * La réponse d'un geste de lot (L5d) : la semaine relue, puis les colonnes que le lot change, celles de
 * chaque intervention avant et après le geste (`TaskBatchLib::getDayChange()`), ou l'écran entier
 * (`planningTaskDayScreen()`), dont « Nouvelle intervention » (`create`) est le seul geste qui crée le lot.
 *
 * Les cartes que « Sans jour » garde déjà y restent (R-Lot-Garde) : une carte gardée prise dans le lot
 * (« À faire », « Supprimer ») n'y reste pas, `TaskDayLib::getColumns()` ne gardant que ce qui est fait, et
 * « Sans jour » est alors redessinée pour qu'elle en parte.
 *
 * @param array $keysBefore Les colonnes des interventions du lot, lues avant le geste.
 * @param array $tasksKept Les interventions du lot que « Sans jour » garde après la coche (`TaskBatchLib::getDayKept()`).
 */
function planningTaskDayBatch(\stdClass $data, \Collection $cTask, array $keysBefore, string $gesture, array $tasksKept = []): void {

	$data->gesture = $gesture;

	$tasksPosted = planningTaskDayKeptPosted();

	// Une carte gardée prise dans le lot est à l'écran dans « Sans jour », qu'aucune colonne du lot ne nomme.
	if(array_intersect_key($tasksPosted, array_flip($cTask->getIds())) !== []) {
		$keysBefore[] = \planning\TaskDayLib::NONE;
	}

	$tasksKept += $tasksPosted;

	$dates = \planning\TaskDayLib::getDates($data->eStructure, $data->week);

	$data->cTask = \planning\TaskWeekLib::get($data->eStructure, $data->week);

	$data->columns = \planning\TaskDayLib::getColumns($data->week, $data->cTask, $dates, \user\ConnectionLib::getOnline(), $data->hide, tasksKept: $tasksKept);
	$data->cUser = \hr\TeammateLib::getUsersForPlanning($data->eStructure);

	planningTaskDayReminders($data);

	if(planningTaskDayScreen($data, $gesture === 'create' ? $cTask : new \Collection())) {
		$data->change = 'screen';
		throw new ViewAction($data, ':doTask');
	}

	$data->change = \planning\TaskBatchLib::getDayChange($data->columns, $keysBefore, $cTask, $data->cTask, $data->week, $dates);

	throw new ViewAction($data, ':doTask');

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
		$data->canCreate = new \series\Task(['structure' => $data->eStructure])->canCreate();

		// « + Fait » n'a rien à créer sur une semaine dont aucun jour ne peut porter Fait.
		$data->canCreateByStatus = [
			\series\Task::TODO => $data->canCreate,
			\series\Task::DONE => ($data->canCreate and \planning\TaskCreateLib::hasDone($data->week)),
		];

	})
	/**
	 * ⚠️ Une écriture dans un GET, déclarée comme dans la Semaine (`taskWeek.p.php`) : les interventions
	 * répétées de la semaine se matérialisent avant d'être lues.
	 */
	->get(['/ferme/{id}/planning-v2/jour', '/ferme/{id}/planning-v2/jour/{week@week}'], function($data) {

		\series\RepeatLib::createForWeek($data->eStructure, $data->week);

		$data->cTask = \planning\TaskWeekLib::get($data->eStructure, $data->week);

			$data->columns = \planning\TaskDayLib::getColumns($data->week, $data->cTask, \planning\TaskDayLib::getDates($data->eStructure, $data->week), \user\ConnectionLib::getOnline(), $data->hide);
		$data->cUser = \hr\TeammateLib::getUsersForPlanning($data->eStructure);
		$data->weekNext = \planning\TaskDayLib::getNextWeek($data->week);
		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->empty = \planning\TaskWeekLib::isEmpty($data->cTask);

		planningTaskDayReminders($data);

		throw new ViewAction($data, ':taskDay');

	})
	/**
	 * La coche : l'intervention passe en fait dans la semaine, comme dans la Semaine
	 * (`taskWeek.p.php`, `doDone`), le temps prévu posé à la première coche de la personne. Son jour fait
	 * est celui que `\series\TaskLib::updateDoneCollection()` en déduit : son jour prévu, le dernier jour
	 * où elle porte du temps, ou aujourd'hui dans la semaine courante. Une carte de « Sans jour » cochée
	 * cette semaine va donc aussi dans la colonne d'aujourd'hui, mais reste où on l'a cochée jusqu'au
	 * rechargement (`TaskDayLib::getColumns()`, R-Jour-Fait). Il n'y a pas de bulle : la carte faite porte
	 * son temps, que son champ modifie, ouvert à l'arrivée d'une carte cochée dans un jour
	 * (`PlanningTaskDay.openDone()`).
	 */
	->post('doDone', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$keysBefore = \planning\TaskDayLib::getKeysForTask($data->eStructure, $data->week, $eTask);
		$timeBefore = \planning\TaskDayLib::getTimeForTask($data->eStructure, $data->week, $eTask, \user\ConnectionLib::getOnline());

		if($data->eStructure->hasTime()) {

			$eTimesheet = new \series\Timesheet([
				'structure' => $data->eStructure,
				'user' => \user\ConnectionLib::getOnline(),
			]);

			$eTimesheet->validate('canWrite', 'acceptWrite');

			\series\TaskLib::updateDoneWithTime($eTask, $eTimesheet, $data->week);

		} else {
			\series\TaskLib::updateDoneCollection(new Collection([$eTask]), $data->week);
		}

		$data->gesture = 'done';

		planningTaskDayRespond($data, $eTask, $keysBefore, planningTaskDayKept($eTask, $timeBefore));

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * La décoche : l'intervention redevient à faire, le temps passé reste.
	 */
	->post('doTodo', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		$keysBefore = \planning\TaskDayLib::getKeysForTask($data->eStructure, $data->week, $eTask);

		\series\TaskLib::updateTodoCollection(new Collection([$eTask]));

		$data->gesture = 'todo';

		planningTaskDayRespond($data, $eTask, $keysBefore, planningTaskDayKept($eTask));

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * Le champ du temps d'une carte faite : le total tapé est le temps de la personne connectée le jour de
	 * la colonne (écart a de L5c), dont seul l'écart s'écrit (`\series\TimesheetLib::writeTimeForDay()`),
	 * comme la bulle de la Semaine. `Timesheet.date` refuse un jour à venir, et le jour doit être de la
	 * semaine affichée, la seule que la réponse redessine. La durée est exigée (`time.missing`, liste `day`) : absente, elle retirerait le temps du jour.
	 * La réponse ne redessine que les colonnes que ce temps change (`TaskDayLib::getChangeForTime()`).
	 */
	->post('doTime', function($data) {

		$data->eStructure->validate('hasTime');

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		$keysBefore = \planning\TaskDayLib::getKeysForTask($data->eStructure, $data->week, $eTask);
		$timeBefore = \planning\TaskDayLib::getTimeForTask($data->eStructure, $data->week, $eTask, \user\ConnectionLib::getOnline());

		$fw = new FailWatch();

		$eTimesheet = \series\TimesheetLib::prepareDay($data->eStructure, \user\ConnectionLib::getOnline(), $_POST);

		$fw->validate();

		if(in_array($eTimesheet['date'], week_dates($data->week), TRUE) === FALSE) {
			\series\Timesheet::fail('date.week');
		}

		$fw->validate();

		$eTimesheet->validate('canWrite', 'acceptWrite');

		\series\TimesheetLib::writeTimeForDay($eTask, $eTimesheet);

		$fw->validate();

		$data->gesture = 'time';

		planningTaskDayRespond($data, $eTask, $keysBefore, planningTaskDayKept($eTask));

		// planningTaskDayRespond() décide seul si l'écran entier repart ; sinon, un temps saisi change aussi les
		// colonnes qui le comptent, que le changement générique ne connaît pas, d'où le calcul propre au temps.
		if($data->change !== 'screen') {
			$data->change = \planning\TaskDayLib::getChangeForTime($data->columns, $data->cTask, $keysBefore, $eTask, \user\ConnectionLib::getOnline(), $timeBefore, $eTimesheet['date']);
		}

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * Le jour d'une intervention à faire, depuis le menu de sa carte ou le glisser : un jour de la semaine,
	 * « Sans jour » (`planned[week]` de la semaine affichée) ou la semaine suivante (`planned[week]`).
	 * `planned` se construit par `build()` (`Task.e.php`), qui en tire `plannedDate` et `plannedWeek` ;
	 * l'écriture est celle du planning actuel. Rien d'autre que ces deux semaines n'est offert.
	 */
	->post('doPlanned', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$keysBefore = \planning\TaskDayLib::getKeysForTask($data->eStructure, $data->week, $eTask);

		$fw = new FailWatch();

		$eTask->build(['planned'], $_POST);

		$fw->validate();

		if(in_array($eTask['plannedWeek'], [$data->week, \planning\TaskDayLib::getNextWeek($data->week)], TRUE) === FALSE) {
			throw new NotExpectedAction('Planned out of the week and the next one');
		}

		if($eTask['plannedDate'] !== NULL) {
			\series\TaskLib::updatePlannedDateCollection(new Collection([$eTask]), $eTask['plannedDate']);
		} else {
			\series\TaskLib::updatePlannedCollection(new Collection([$eTask]), $eTask['plannedWeek']);
		}

		$data->gesture = 'planned';

		planningTaskDayRespond($data, $eTask, $keysBefore, planningTaskDayKept($eTask));

		throw new ViewAction($data, ':doTask');

	})
	/**
	 * La recherche « Espèce ou série » de « Nouvelle intervention » : les séries de la
	 * saison choisie dans la rangée de la liste, les plus proches de la semaine d'abord
	 * (`HarvestWeekLib::getForTaskQuery()`), puis les espèces (`TaskCreateLib::getPlantsForQuery()`), chaque
	 * section avec le compte de ce que sa coupe laisse de côté, sauf celle que « Voir les N autres » a ouverte
	 * (`more`). Ce que le champ a déjà choisi n'y revient pas (`exclude`).
	 */
	->post('query', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$name = \planning\TaskCreateLib::readName(POST('query'));
		$excluded = \planning\TaskCreateLib::readExcluded(POST('exclude', 'array'));
		$more = \planning\TaskCreateLib::readMore(POST('more', 'array'));

		[$data->cCultivation, $data->cultivationOthers] = \planning\HarvestWeekLib::getForTaskQuery($data->eStructure, $data->week, $name, POST('season', '?int'), $excluded['cultivation'], $more['cultivation']);
		[$data->cPlant, $data->plantOthers] = \planning\TaskCreateLib::getPlantsForQuery($data->eStructure, $name, $excluded['plant'], $more['plant']);

		throw new ViewAction($data);

	})
	/**
	 * La recherche « Intervention » de « Nouvelle intervention » (retour 192) : les actions de la structure
	 * dont le nom porte ce qui est tapé, rangées sous leurs catégories, la récolte comprise (retour 293).
	 * Sans rien de tapé, les plus fréquentes passent en tête (retour 257).
	 */
	->post('queryAction', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$name = POST('query');

		$data->cCategory = \planning\TaskDayLib::getCategoriesForCreate($data->eStructure, $name);

		$data->cActionFrequent = ($name === '') ?
			\planning\TaskDayLib::getActionsFrequent($data->eStructure, $data->cCategory, \planning\PlanningSetting::TASK_ACTION_FREQUENT) :
			new \Collection();

		throw new ViewAction($data);

	})
	/**
	 * La récolte de « Nouvelle intervention » faite (retour 258), pour les choix d'« Espèce ou série »
	 * (`choices[]`, `TaskCreateLib::readChoice()`) : la quantité et « Ajouter à un stock » (DG-4), et pour plusieurs
	 * séries de la même espèce la clé et les parts de « Récolter » (DG-5), mesurées sur la variété choisie. Une
	 * série se saisit dans son unité, une espèce seule en kilos (BY-bis), chacune avec ses caisses, ou dans une
	 * autre unité (retour 319). Des espèces différentes ne se récoltent pas ensemble : rien ne se rend.
	 */
	->post('harvest', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$data->harvest = \planning\TaskCreateLib::getHarvestPanel($data->eStructure, POST('choices', 'array', []), POST('variety', '?int'));

		throw new ViewAction($data);

	})
	/**
	 * Variété, calibre et « Partagé » de « Nouvelle intervention » (retour 256) : les
	 * champs de l'ancien formulaire qui dépendent des choix d'« Espèce ou série » (`cultivation[]`) et de
	 * « Partagé » (`shared[]`), pour l'action choisie et sa catégorie (`TaskCreateLib::getNewForChoices()`).
	 */
	->post('getCultivationFields', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$action = POST('action', '?int');

		$eAction = ($action === NULL) ?
			new \work\Action() :
			\work\ActionLib::getByStructure($data->eStructure, id: $action);

		$category = POST('category', '?int');

		$eCategory = ($category === NULL) ?
			new \work\Category() :
			\work\CategoryLib::getByStructure($data->eStructure, id: $category);

		$fw = new FailWatch();

		$data->eTask = \planning\TaskCreateLib::getNewForChoices($data->eStructure, $eAction, $eCategory, POST('cultivation', 'array'), POST('shared', 'array'));

		$fw->validate();

		throw new ViewAction($data);

	})
	/**
	 * « Nouvelle intervention » (L5d, `TaskCreateUi`) : « + À faire » et « + Fait » de la rangée des outils, qui
	 * demandent leur état (`status`), le « + » d'un groupe d'action, qui pose l'action, celui d'un jour à venir,
	 * qui pose le jour, ou « J'ai aussi fait » d'un jour passé, qui pose le jour et demande Fait (retour 293).
	 */
	->get('create', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$data->page = 'taskDay';
		[$data->eAction, $data->eCategoryAction] = \planning\TaskCreateLib::getAction($data->eStructure, GET('action', '?int'));
		$data->eTask = \planning\TaskCreateLib::getNew($data->eStructure, $data->eAction, $data->eCategoryAction);
		$data->date = GET('date', fn($value) => in_array($value, week_dates($data->week), TRUE));
		$data->days = \planning\TaskCreateLib::getDays($data->week);
		$status = GET('status', [\series\Task::TODO, \series\Task::DONE], NULL);
		$data->date ??= \planning\TaskCreateLib::getDateDefault($data->days, $status);
		$data->status = \planning\TaskCreateLib::getStatus($data->days, $data->date, $status);
		$data->when = $data->week;
		$data->weeks = \planning\TaskCreateLib::getWeeks($data->eStructure, $data->status);
		$data->cActionShortcut = \planning\TaskCreateLib::getShortcuts($data->eStructure, $data->eAction);
		$data->post = [];
		$data->cUserTime = \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->week);

		throw new ViewAction($data);

	})
	/**
	 * « Quand » de « Nouvelle intervention » pour la semaine choisie (piste F de R-CreationRapide, `week`, que le
	 * constructeur borne aux saisons de la structure) ou pour l'état coché : ses jours et le jour pris d'office, et
	 * pour Fait les personnes du temps de cette semaine, la personne de la fiche de temps en tête (`user`). Une
	 * semaine à venir ne porte pas Fait : passée à Fait, elle devient la semaine en cours. Les cultures déjà
	 * choisies ne changent pas : la semaine ne fait que les nommer (`HarvestWeekLib::getForTaskSeries()`).
	 */
	->post('getWhen', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$data->status = POST('status', [\series\Task::TODO, \series\Task::DONE], NULL) ?? throw new NotExpectedAction('Invalid status');

		$user = POST('user', '?int');

		$data->when = match($data->status) {
			\series\Task::TODO => $data->week,
			\series\Task::DONE => min($data->week, \Clock::week()),
		};
		$data->days = \planning\TaskCreateLib::getDays($data->when);
		$data->date = \planning\TaskCreateLib::getDateDefault($data->days, $data->status);
		$data->weeks = \planning\TaskCreateLib::getWeeks($data->eStructure, $data->status);
		$data->cUserTime = match($data->status) {
			\series\Task::TODO => new \Collection(),
			\series\Task::DONE => \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->when, ($user === NULL) ? new \user\User() : new \user\User(['id' => $user])),
		};

		throw new ViewAction($data);

	})
	/**
	 * Les interventions neuves, faites ou à faire dans la semaine choisie (`when`), une par culture choisie
	 * (`TaskCreateLib::createCollection()`). La réponse redessine les colonnes où elles vont, comme
	 * celle d'un lot, et dit la semaine de celles qui n'y vont pas (`TaskCreateLib::getSuccessOutside()`).
	 */
	->post('doCreate', function($data) {

		$when = POST('when', fn($value) => Filter::check('week', $value)) ?? throw new NotExpectedAction('Invalid week');

		$data->cTaskCreated = \planning\TaskCreateLib::createCollection($data->eStructure, $when, $_POST);
		$data->success = \planning\TaskCreateLib::getSuccessOutside($data->cTaskCreated, $when, [$data->week]);

		planningTaskDayBatch($data, $data->cTaskCreated, [], 'create');

	})
	/**
	 * Les gestes de lot (L5d, `TaskBatchUi`), ceux de la Semaine (`taskWeek.p.php`) : la même validation et la
	 * même écriture, que suit la réponse du Jour.
	 */
	->post('doBatchDone', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);
		$tasksKept = \planning\TaskBatchLib::getDayKept($data->eStructure, $data->week, $cTask, \user\ConnectionLib::getOnline());

		\series\TaskLib::updateDoneCollection($cTask, $data->week);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch', $tasksKept);

	})
	->post('doBatchTodo', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isDone', 'canWrite');

		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);

		\series\TaskLib::updateTodoCollection($cTask);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch');

	})
	->post('doBatchPlannedDate', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$planned = \planning\TaskBatchLib::getPlanned('plannedDate', $_POST);
		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);

		\series\TaskLib::updatePlannedDateCollection($cTask, $planned);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch');

	})
	->post('doBatchPlannedWeek', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		$planned = \planning\TaskBatchLib::getPlanned('plannedWeek', $_POST);
		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);

		\series\TaskLib::updatePlannedCollection($cTask, $planned);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch');

	})
	->post('doBatchIncrement', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('acceptPostpone', 'canWrite');

		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);

		$fw = new FailWatch();

		\series\TaskLib::incrementPlannedCollection($cTask, POST('increment', 'int'));

		$fw->validate();

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch');

	})
	->post('doBatchUser', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('isTodo', 'canWrite');

		[$eUser, $action] = \planning\TaskBatchLib::getUser($data->eStructure, $_POST);
		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);

		\series\TaskLib::updateUser($cTask, $eUser, $action);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch');

	})
	->post('doBatchDelete', function($data) {

		$cTask = \planning\TaskBatchLib::getByIds($data->eStructure, POST('ids', 'array'));
		$cTask->validate('canDelete');

		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);

		\series\TaskLib::deleteCollection($cTask);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batch');

	})
	/**
	 * « Temps », comme dans la Semaine (`taskWeek.p.php`, `batchTime`), dont la vue rend le panneau.
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

		$data->page = 'taskDay';
		$data->post = [];

		throw new ViewAction($data, 'planning/taskWeek:batchTime');

	})
	/**
	 * Comme dans la Semaine (`doBatchTime`). Une intervention à faire que le temps passe faite reste dans « Sans
	 * jour » si elle y était, comme par « Fait ! » (`TaskBatchLib::getDayKept()`).
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

		$cTaskWritten = $cTask->find(fn($eTask) => $cTimesheet->offsetExists($eTask['id']));

		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTaskWritten);
		$tasksKept = \planning\TaskBatchLib::getDayKept($data->eStructure, $data->week, $cTaskWritten->find(fn($eTask) => $eTask->isTodo()), $eUser);

		\planning\TaskBatchLib::writeTimes($cTimesheet, $data->week);

		planningTaskDayBatch($data, $cTaskWritten, $keysBefore, 'batchTime', $tasksKept);

	})
	/**
	 * « Récolter », comme dans la Semaine (`taskWeek.p.php`, `batchHarvest`), dont la vue rend le panneau.
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

		$data->page = 'taskDay';
		$data->post = [];

		throw new ViewAction($data, 'planning/taskWeek:batchHarvest');

	})
	/**
	 * Comme dans la Semaine (`doBatchHarvest`). Une récolte à faire que la quantité passe faite reste dans « Sans
	 * jour » si elle y était, comme par « Fait ! » (`TaskBatchLib::getDayKept()`).
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

		$keysBefore = \planning\TaskBatchLib::getDayKeysBefore($data->eStructure, $data->week, $cTask);
		$tasksKept = \planning\TaskBatchLib::getDayKept($data->eStructure, $data->week, $cTask->find(fn($eTask) => $eTask->isTodo()), $eUser);

		\planning\TaskBatchLib::writeHarvests($harvest);

		planningTaskDayBatch($data, $cTask, $keysBefore, 'batchHarvest', $tasksKept);

	})
	/**
	 * La coche et la décoche d'un rappel, comme à la Semaine (`taskWeek:doReminderCheck`) : un rappel déjà
	 * dans l'état demandé ne s'écrit pas et ne lève rien.
	 */
	->post('doReminderCheck', function($data) {

		$eReminder = planningTaskDayReminderGet($data)->validate('canWrite');

		$keysBefore = [\planning\ReminderLib::getDayKey($eReminder, $data->week)];

		if($eReminder->acceptCheck()) {
			\planning\ReminderLib::check($eReminder);
		}

		planningTaskDayReminderRespond($data, $eReminder, $keysBefore);

	})
	->post('doReminderUncheck', function($data) {

		$eReminder = planningTaskDayReminderGet($data)->validate('canWrite');

		$keysBefore = [\planning\ReminderLib::getDayKey($eReminder, $data->week)];

		if($eReminder->acceptUncheck()) {
			\planning\ReminderLib::uncheck($eReminder);
		}

		planningTaskDayReminderRespond($data, $eReminder, $keysBefore);

	})
	/**
	 * Le texte et le jour corrigés sur place, comme à la Semaine (`taskWeek:doReminderUpdate`). La colonne
	 * se lit avant `build()`, qui pose le nouveau jour.
	 */
	->post('doReminderUpdate', function($data) {

		$eReminder = planningTaskDayReminderGet($data)
			->validate('canUpdate')
			->validate('acceptUpdate', onFail: fn() => throw new FailAction('planning\Reminder::updatePast'));

		$keysBefore = [\planning\ReminderLib::getDayKey($eReminder, $data->week)];

		$fw = new FailWatch();

		$eReminder->build(\planning\ReminderLib::getPropertiesUpdate(), $_POST, new \PropertyList('update'));

		$fw->validate();

		\planning\ReminderLib::update($eReminder, \planning\ReminderLib::getPropertiesUpdate());

		planningTaskDayReminderRespond($data, $eReminder, $keysBefore);

	})
	->post('doReminderDelete', function($data) {

		$eReminder = planningTaskDayReminderGet($data)
			->validate('canDelete')
			->validate('acceptDelete', onFail: fn() => throw new FailAction('planning\Reminder::deletePast'));

		\planning\ReminderLib::delete($eReminder);

		planningTaskDayReminderRespond($data, new \planning\Reminder(), [\planning\ReminderLib::getDayKey($eReminder, $data->week)]);

	});

/**
 * Le rappel que vise un geste, de la structure de l'écran : la réponse redessine le pense-bête de cet
 * écran-ci. Chaque geste valide ensuite son droit.
 */
function planningTaskDayReminderGet(\stdClass $data): \planning\Reminder {

	return \planning\ReminderLib::getById(POST('reminder', 'int'))
		->validateProperty('structure', $data->eStructure);

}

/**
 * La réponse d'un geste du pense-bête : les rappels relus, et les colonnes que le geste touche, celle où le
 * rappel était puis celle où il est, rien pour un rappel effacé. Le rappel s'éclaire quand il est encore à
 * l'écran ; un resté coché reste barré dans « Non défini ». Le pense-bête éteint ailleurs depuis l'ouverture de la
 * page, rien ne se redessine.
 *
 * @param \planning\Reminder $eReminder Le rappel après le geste, vide pour un effacement.
 * @param array $keysBefore La colonne où il était avant le geste.
 */
function planningTaskDayReminderRespond(\stdClass $data, \planning\Reminder $eReminder, array $keysBefore): void {

	$data->eReminder = $eReminder;

	planningTaskDayReminders($data);

	if($data->reminders === NULL) {
		$data->change = [];
		$data->highlight = FALSE;
	} else if($eReminder->empty()) {
		$data->change = $keysBefore;
		$data->highlight = FALSE;
	} else {

		$key = \planning\ReminderLib::getDayKey($eReminder, $data->week);

		$data->change = array_values(array_unique([...$keysBefore, $key]));
		$data->highlight = $data->reminders[$key]->offsetExists($eReminder['id']);

	}

	throw new ViewAction($data, ':doReminder');

}
?>
