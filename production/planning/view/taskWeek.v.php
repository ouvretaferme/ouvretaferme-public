<?php
new AdaptativeView('taskWeek', function($data, StructureTemplate $t) {

	$t->title = s("Interventions de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\TaskWeekUi::url($data->eStructure, $data->week);

	$uiTaskWeek = new \planning\TaskWeekUi();

	$t->mainTitle = $uiTaskWeek->getCalendar($data->eStructure, $data->week);

	// L'écran rendu entier est une navigation ou un rechargement, dont celui qui suit l'écriture d'un panneau de
	// la barre de lot (Modifier, Commenter, Une autre semaine, Décaler davantage), jamais la réponse ciblée d'un
	// geste : la sélection s'y vide (retour 253). `parseRender()` passe ce nœud avant les cases qui la relisent.
	echo '<div id="planning-board" onrender="PlanningTaskSelection.reset()">';
		echo new \planning\TabUi()->get($data->eStructure, $data->week, 'semaine', $data->eMember);
		echo $uiTaskWeek->get($data->eStructure, $data->week, $data->weekNext, $data->cTask, $data->eMember, $data->ccTask, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->zones, $data->folded, $data->dues, $data->reminders, $data->canReminder, $data->canHarvest, $data->timeExpected, $data->view, $data->locked, $data->legend);
	echo '</div>';

});

new AdaptativeView('customize', function($data, StructureTemplate $t) {

	$t->title = s("Personnaliser le planning de {value}", $data->eStructure['name']);
	$t->canonical = \planning\TaskWeekUi::urlCustomize($data->eStructure, $data->week, $data->origin);
	$t->nav = 'planning-v2';

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Personnaliser le planning"),
		back: \planning\TabUi::getUrl($data->eStructure, $data->origin, $data->week)
	);

	echo '<div id="planning-board">';
		echo new \planning\TaskWeekUi()->getCustomize($data->eStructure, $data->week, $data->eMember);
	echo '</div>';

});

/**
 * La réponse des gestes sur une carte, la coche (`done`), la décoche (`todo`), la bulle (`time`) et la
 * saisie d'une récolte (`harvest`, retour 203), que la page distingue par `$data->gesture`.
 *
 * Avant le changement, la bulle : la coche ferme celle qui était ouverte, une seule l'étant à la fois ; la
 * décoche ferme celle de sa carte ; la bulle reste ouverte avec ce qu'on y a tapé. La récolte ferme la
 * feuille ou le panneau où elle s'est saisie.
 *
 * Le changement (`TaskWeekLib::getChange()`) : la carte remplacée sur place, ou retirée avec le compte de
 * son bloc, ou le bloc retiré quand le geste le vide, les autres gardant leur pile. L'écran des blocs est
 * rendu entier quand le geste vide le dernier bloc, ou quand l'intervention, arrivée d'une zone ou créée
 * par la récolte, ouvre un bloc (`TaskBatchLib::getCreateChange()`). Quand la rangée des outils apparaît ou disparaît, l'écran entier
 * et le calendrier (`screen`, `planningTaskWeekScreen()`).
 *
 * Après, la coche pose la bulle juste après la carte cochée, par-dessus les suivantes, qu'elle ne pousse
 * pas (`PlanningTask.anchor()`). Un temps (`time`) ne remplace que la carte, jamais la bulle : elle garde
 * sa place pendant toute la saisie.
 */
new JsonView('doTask', function($data, AjaxTemplate $t) {

	$uiTaskWeek = new \planning\TaskWeekUi();

	$eTask = $data->eTask;
	$action = $eTask['action']['id'];

	// Un `match` sans `default` : un geste que la page ne connaît pas lève au lieu de répondre sans rien fermer.
	match($data->gesture) {
		'done' => $t->qsa('#planning-week :is(.planning-task-bubble, #planning-task-sheet)')->remove(),
		'todo' => $t->qsa('#planning-task-bubble-'.$eTask['id'].', #planning-task-sheet[data-task="'.$eTask['id'].'"]')->remove(),
		'time', 'planned', 'unplanned' => NULL,
		'harvest' => $data->sheet ?
			$t->qs('#planning-harvest-sheet')->remove() :
			$t->js()->closePanel('#panel-planning-harvest-create'),
	};

	if($data->change === 'screen') {

		$t->qs('#tasks-calendar-top')->outerHtml($uiTaskWeek->getCalendar($data->eStructure, $data->week));
		$t->qs('#planning-week')->outerHtml($uiTaskWeek->get($data->eStructure, $data->week, $data->weekNext, $data->cTask, $data->eMember, $data->ccTask, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->zones, $data->folded, $data->dues, $data->reminders, $data->canReminder, $data->canHarvest, $data->timeExpected, $data->view, $data->locked, $data->legend));

	} else {

		planningTaskWeekZonesLeave($t, $data->grid, $data->changeZones);

		switch($data->change) {

			case NULL :
				break;

			case 'block' :
				planningTaskWeekBlock($t, $data->grid, $data->eStructure, $data->week, $data->ccTask[$action], $data->todo[$action], $data->cUser, $data->canCreate, $data->rank, $data->canHarvest);
				break;

			case 'card' :
				planningTaskWeekCard($t, $data->grid, $data->eStructure, $data->week, $data->ccTask[$eTask['action']['id']][$eTask['id']], $data->cUser, $data->canHarvest);
				$t->qs('#planning-week-counter-'.$action)->outerHtml($uiTaskWeek->getBlockCounter($action, $data->todo[$action]));
				break;

			case 'remove' :
				$t->qs(planningTaskWeekCardId($data->grid, $eTask))->remove();
				$t->qs('#planning-week-counter-'.$action)->outerHtml($uiTaskWeek->getBlockCounter($action, $data->todo[$action]));
				// Une carte retirée n'a pas de case qui se rende : la sélection et la case de tête de son bloc se recalculent ici.
				$t->js()->eval('PlanningTaskSelection.change()');
				break;

			case 'emptied' :
				planningTaskWeekBlockRemove($t, $data->grid, $action);
				planningTaskWeekRanks($t, $data->grid, $data->ccTask);
				$t->js()->eval('PlanningTaskSelection.change()');
				break;

			case 'board' :
				planningTaskWeekBoard($t, $data->grid, $data->eStructure, $data->week, $data->cTask, $data->ccTask, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->eMember, $data->reminders, $data->canReminder, $data->canHarvest);
				break;

			case 'columns' :
				planningTaskWeekColumns($t, $data->eStructure, $data->week, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->changeColumns, $data->cUser, $data->canCreateByStatus, $data->reminders, $data->canReminder, $data->idsArrived, $data->idsArrivedKept, $data->statusesArrived);
				break;

		}

		planningTaskWeekZonesEnter($t, $data->grid, $data->eStructure, $data->week, $data->zones, $data->changeZones, $data->cUser, $data->canHarvest);

		$t->qs('#planning-week-estimated')->outerHtml($uiTaskWeek->getEstimated($data->timeExpected));
		planningTaskWeekLocked($t, $data->grid, $data->locked);

	}

	// Une intervention en retard ou non planifiée que l'on coche entre dans les blocs de la semaine (`block`,
	// `board`) : la bulle se pose après sa carte, là où elle arrive.
	if($data->gesture === 'done' and $data->canTime and in_array($data->change, ['card', 'block', 'board', 'columns'], TRUE)) {
		if($data->timeSheet) {
			$t->qs('#planning-week')->insertAdjacentHtml('beforeend', new \planning\TaskCardUi()->getSheet($eTask, $data->time, $data->eUserTime));
		} else {
			$t->qs('#planning-task-'.$eTask['id'])->insertAdjacentHtml('afterend', new \planning\TaskCardUi()->getBubble($eTask, $data->time, $data->eUserTime));
		}
	}

});
/**
 * La réponse de « Nouvelle intervention » (L5d) : le panneau se ferme, puis le bloc de l'intervention
 * neuve rendu entier, ou les blocs entiers quand elle en ouvre un, ou rien quand l'écran ne la montre pas
 * (`TaskBatchLib::getCreateChange()`). « En retard » suit, où entre une intervention à faire d'une semaine
 * passée.
 */
new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$uiTaskWeek = new \planning\TaskWeekUi();

	$t->js()->closePanel('#panel-planning-task-create');

	if($data->success !== NULL) {
		$t->js()->success('planning', 'Task::createdOutside', $data->success);
	}

	if($data->change === 'screen') {
		$t->qs('#tasks-calendar-top')->outerHtml($uiTaskWeek->getCalendar($data->eStructure, $data->week));
		$t->qs('#planning-week')->outerHtml($uiTaskWeek->get($data->eStructure, $data->week, $data->weekNext, $data->cTask, $data->eMember, $data->ccTask, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->zones, $data->folded, $data->dues, $data->reminders, $data->canReminder, $data->canHarvest, $data->timeExpected, $data->view, $data->locked, $data->legend));
		return;
	}

	$action = $data->eTask['action']['id'];

	switch($data->change) {

		case 'block' :
			planningTaskWeekBlock($t, $data->grid, $data->eStructure, $data->week, $data->ccTask[$action], $data->todo[$action], $data->cUser, $data->canCreate, $data->rank, $data->canHarvest);
			break;

		case 'board' :
			planningTaskWeekBoard($t, $data->grid, $data->eStructure, $data->week, $data->cTask, $data->ccTask, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->eMember, $data->reminders, $data->canReminder, $data->canHarvest);
			break;

		case 'columns' :
			planningTaskWeekColumns($t, $data->eStructure, $data->week, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->changeColumns, $data->cUser, $data->canCreateByStatus, $data->reminders, $data->canReminder, $data->idsArrived, $data->idsArrivedKept, $data->statusesArrived);
			break;

	}

	planningTaskWeekZonesEnter($t, $data->grid, $data->eStructure, $data->week, $data->zones, $data->changeZones, $data->cUser, $data->canHarvest);

	$t->qs('#planning-week-estimated')->outerHtml($uiTaskWeek->getEstimated($data->timeExpected));

});

/**
 * « Temps » (R-Retours-0410, point 10), pour la Semaine comme pour le Jour, qui le rend par son adresse
 * (`planning/taskWeek:batchTime`).
 */
new AdaptativeView('batchTime', function($data, PanelTemplate $t) {

	return new \planning\TaskBatchUi()->getTime($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->eUserOnline, $data->days, $data->date, $data->distributions, $data->distribution);

});

/**
 * « Récolter » (DG-1), pour la Semaine comme pour le Jour, qui le rend par son adresse
 * (`planning/taskWeek:batchHarvest`).
 */
new AdaptativeView('batchHarvest', function($data, PanelTemplate $t) {

	return new \planning\TaskBatchUi()->getHarvest($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->days, $data->date, $data->distributions, $data->distribution, $data->ePlant, $data->unit, $data->tasksByUnit, $data->eTaskStock, $data->cUserTime, $data->eCultivationChoice);

});

/**
 * La réponse d'un geste de lot (L5d). La sélection se vide d'abord, avant que les cartes redessinées ne la
 * relisent, quel que soit le geste, « Affecter » compris (retour 253). Puis chaque carte du lot
 * remplacée ou retirée avec le compte de son bloc, et chaque bloc qu'il vide retiré seul. Les blocs sont
 * rendus entiers quand le lot vide le dernier, ou quand une intervention arrivée d'une zone entre dans la
 * semaine (`$idsArriving`, `TaskBatchLib::getWeekChange()`).
 */
new JsonView('doBatch', function($data, AjaxTemplate $t) {

	$uiTaskWeek = new \planning\TaskWeekUi();

	if($data->gesture === 'batch') {
		$t->js()->eval('PlanningTaskSelection.clear()');
	} else if($data->gesture === 'batchTime') {
		$t->js()->closePanel('#panel-planning-batch-time');
		$t->js()->eval('PlanningTaskSelection.clear()');
	} else if($data->gesture === 'batchHarvest') {
		$t->js()->closePanel('#panel-planning-batch-harvest');
		$t->js()->eval('PlanningTaskSelection.clear()');
	}

	if($data->change === 'screen') {
		$t->qs('#tasks-calendar-top')->outerHtml($uiTaskWeek->getCalendar($data->eStructure, $data->week));
		$t->qs('#planning-week')->outerHtml($uiTaskWeek->get($data->eStructure, $data->week, $data->weekNext, $data->cTask, $data->eMember, $data->ccTask, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->zones, $data->folded, $data->dues, $data->reminders, $data->canReminder, $data->canHarvest, $data->timeExpected, $data->view, $data->locked, $data->legend));
		return;
	}

	planningTaskWeekZonesLeave($t, $data->grid, $data->changeZones);

	if($data->change === 'board') {

		planningTaskWeekBoard($t, $data->grid, $data->eStructure, $data->week, $data->cTask, $data->ccTask, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->eMember, $data->reminders, $data->canReminder, $data->canHarvest);

	} else if($data->change === 'columns') {

		planningTaskWeekColumns($t, $data->eStructure, $data->week, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->changeColumns, $data->cUser, $data->canCreateByStatus, $data->reminders, $data->canReminder, $data->idsArrived, $data->idsArrivedKept, $data->statusesArrived);

	} else if(is_array($data->change)) {

		// Les Cartes disent ce qui change intervention par intervention ; les Colonnes, quand aucune ne bouge, rien.
		$actions = [];
		$actionsEmptied = [];

		foreach($data->cTaskBatch as $eTask) {

			$action = $eTask['action']['id'];

			// Une intervention restée dans les zones sous la semaine n'a ni carte ni bloc dans la semaine.
			if($data->change[$eTask['id']] === 'none') {
				continue;
			}

			// Son bloc s'est vidé : il sort seul, une fois pour toutes ses cartes (`TaskWeekLib::getChange()`).
			if($data->change[$eTask['id']] === 'emptied') {
				$actionsEmptied[$action] = TRUE;
				continue;
			}

			$actions[$action] = TRUE;

			if($data->change[$eTask['id']] === 'card') {
				planningTaskWeekCard($t, $data->grid, $data->eStructure, $data->week, $data->ccTask[$eTask['action']['id']][$eTask['id']], $data->cUser, $data->canHarvest);
			} else {
				$t->qs(planningTaskWeekCardId($data->grid, $eTask))->remove();
			}

		}

		foreach(array_keys($actionsEmptied) as $action) {
			planningTaskWeekBlockRemove($t, $data->grid, $action);
		}

		if($actionsEmptied !== []) {
			planningTaskWeekRanks($t, $data->grid, $data->ccTask);
		}

		foreach(array_keys($actions) as $action) {
			$t->qs('#planning-week-counter-'.$action)->outerHtml($uiTaskWeek->getBlockCounter($action, $data->todo[$action]));
		}

	}

	planningTaskWeekZonesEnter($t, $data->grid, $data->eStructure, $data->week, $data->zones, $data->changeZones, $data->cUser, $data->canHarvest);

	// Une carte retirée n'a pas de case qui se rende, et `clear()` est passé avant son retrait : les cases de
	// tête se recalculent une fois toutes les cartes posées.
	$t->js()->eval('PlanningTaskSelection.change()');

	$t->qs('#planning-week-estimated')->outerHtml($uiTaskWeek->getEstimated($data->timeExpected));
	planningTaskWeekLocked($t, $data->grid, $data->locked);

});

/**
 * Le temps prévu d'une ligne de la grille (DF) : la ligne garde sa tuile, que la fenêtre a écrite, et seul le
 * travail estimé de la semaine change.
 */
new JsonView('doTimeExpected', function($data, AjaxTemplate $t) {

	$t->qs('#planning-week-estimated')->outerHtml(new \planning\TaskWeekUi()->getEstimated($data->timeExpected));

});

/**
 * La première ouverture de « Non planifié » (L8) : ses blocs, puis l'état que les gestes réémettent
 * désormais (`unplanned`), sur l'écran pour les gestes des cartes et sur chaque entrée de la barre du lot.
 */
new JsonView('unplanned', function($data, AjaxTemplate $t) {

	$zone = $data->zones[\planning\TaskLateLib::UNPLANNED];

	$t->qs('#planning-week-unplanned-board')->outerHtml(new \planning\TaskWeekUi()->getZoneBoard($data->eStructure, $data->week, $zone, $data->cUser, $data->grid, $data->canHarvest));
	$t->qs('#planning-week')->setAttribute('data-unplanned', '1');
	$t->qsa('#planning-week [post-week]')->setAttribute('post-unplanned', '1');

});

/**
 * « Voir les N autres » (L8) : le bloc entier, à son rang dans les piles de sa zone, ou le groupe entier du
 * tableau « En retard » en grille (DZ).
 */
new JsonView('more', function($data, AjaxTemplate $t) {

	$zone = $data->zones[$data->zone];

	if(planningTaskWeekZoneGrid($data->grid, $data->zone)) {
		$html = new \planning\TaskWeekGridUi()->getZoneGroup($data->eStructure, $data->week, $zone, $data->action, $data->cUser, $data->canHarvest);
	} else {
		$html = new \planning\TaskWeekUi()->getZoneBlock($data->eStructure, $data->week, $zone, $data->action, $data->cUser, $data->rank);
	}

	$t->qs('#planning-week-'.$data->zone.'-block-'.$data->action)->outerHtml($html);

});

/**
 * Le tableau de l'équipe d'un jour de la grille, posé dans le menu qui vient de s'ouvrir, ou redessiné avec
 * la ligne qu'« Ajouter une personne » y ajoute, sous la ligne qui prévoit aujourd'hui (EH).
 */
new JsonView('time', function($data, AjaxTemplate $t) {

	$t->qs('#planning-grid-time-'.$data->eTask['id'].'-'.$data->dateTeam)->innerHtml(new \planning\TaskWeekGridUi()->getTimeList($data->eTask, $data->dateTeam, $data->cUserTeam, $data->daysTeam, $data->footTeam));

});

/**
 * La liste « Affecter à... » d'une ligne de la grille, posée dans le menu qui vient de s'ouvrir.
 */
new JsonView('users', function($data, AjaxTemplate $t) {

	$t->qs('#planning-grid-users-'.$data->eTask['id'])->innerHtml(new \planning\TaskWeekGridUi()->getUsersList($data->eTask, $data->cUser));

});

/**
 * Une personne affectée ou retirée : la classe et le contenu du déclencheur, puis l'entrée cliquée, et le
 * tableau du jour quand le geste en vient, jamais une liste ni le nœud d'un déclencheur, que `Lime.Dropdown`
 * fermerait en les voyant quitter la page. La case de la ligne aussi (DG-3) : ses jetons disent à « Affecter »
 * de la barre qui est déjà affecté. Et `data-search` sur le nom (EJ), seul attribut du nom que le geste change :
 * la recherche et la liste des personnes de l'omnibox le relisent.
 */
new JsonView('doUser', function($data, AjaxTemplate $t) {

	$uiTaskWeekGrid = new \planning\TaskWeekGridUi();
	$id = $data->eTask['id'];

	$t->qs('#planning-grid-toggle-'.$id)
		->setAttribute('class', \planning\TaskBatchUi::getUsersToggleClass($data->eTask['cUserPlanned']))
		->innerHtml(\planning\TaskBatchUi::getUsersToggle($data->eTask['cUserPlanned']));
	$t->qs('#planning-grid-user-'.$id.'-'.$data->eUser['id'])->outerHtml($uiTaskWeekGrid->getUser($data->eTask, $data->eUser));
	$t->qs('#planning-grid-task-'.$id.' > .planning-grid-name > .planning-select')->outerHtml(new \planning\TaskBatchUi()->getCheckbox($data->eTask));
	$t->qs('#planning-grid-task-'.$id.' > .planning-grid-name [data-search]')->setAttribute('data-search', \planning\TaskCardUi::getSearchValue($data->eTask));

	if($data->dateTeam !== NULL) {
		$t->qs('#planning-grid-time-'.$id.'-'.$data->dateTeam)->innerHtml($uiTaskWeekGrid->getTimeList($data->eTask, $data->dateTeam, $data->cUserTeam, $data->daysTeam, $data->footTeam));
	}

});

/**
 * L'« Enregistrer » commun du menu d'un jour, sur une ligne d'un bloc qui reste à sa place : le menu se ferme
 * à la réponse (`PlanningTaskDetail.saveTeam()`, FJ), et son contenu ne se redessine pas. La durée de son
 * déclencheur, qui s'éclaire, et la semaine de la ligne se redessinent, jamais le déclencheur lui-même, où
 * `PlanningTaskGrid.close()` retrouve le menu à fermer. Les autres jours de la
 * ligne quand la page les dit changés (`datesChanged`), `data-search` sur le nom, où la recherche lit les personnes
 * qui ont du temps (EJ), et ce que le temps change hors de la ligne : les zones,
 * qu'un temps ne fait pas changer sur une ligne qui reste à sa place mais que `doTask` suit de même, et la phrase
 * du verrou, qu'une intervention commencée allume (`TaskWeekLib::hasLocked()`). Les comptes de ce qui reste à
 * faire et le travail estimé ne lisent pas le temps passé.
 */
new JsonView('doTimeTeam', function($data, AjaxTemplate $t) {

	$uiTaskWeekGrid = new \planning\TaskWeekGridUi();

	$eTask = $data->eTaskRow;

	planningTaskWeekZonesLeave($t, $data->grid, $data->changeZones);

	$trigger = '#planning-grid-day-'.$eTask['id'].'-'.$data->dateTeam.' > [data-planning-grid-time]';

	$t->qs($trigger)->innerHtml($uiTaskWeekGrid->getDayTrigger($eTask, $data->dateTeam));
	$t->js()->highlight([$trigger]);

	foreach($data->datesChanged as $date) {
		$t->qs('#planning-grid-day-'.$eTask['id'].'-'.$date)->outerHtml($uiTaskWeekGrid->getDay($data->eStructure, $data->week, $eTask, $date));
	}

	$t->qs('#planning-grid-week-'.$eTask['id'])->outerHtml($uiTaskWeekGrid->getWeekSum($eTask));
	$t->qs('#planning-grid-task-'.$eTask['id'].' > .planning-grid-name [data-search]')->setAttribute('data-search', \planning\TaskCardUi::getSearchValue($eTask));

	planningTaskWeekZonesEnter($t, $data->grid, $data->eStructure, $data->week, $data->zones, $data->changeZones, $data->cUser, $data->canHarvest);
	planningTaskWeekLocked($t, $data->grid, $data->locked);

});

/**
 * Les interventions que les jetons retiennent dans les zones, par zone puis par bloc (`PlanningTaskSearch`).
 */
new JsonView('search', function($data, AjaxTemplate $t) {

	$t->push('zones', $data->matches);

});

/**
 * L'écran entier, redemandé par la page d'une intervention (`taskDetail:doTime`) quand une saisie y fait
 * changer l'intervention de zone.
 */
new JsonView('screen', function($data, AjaxTemplate $t) {

	$uiTaskWeek = new \planning\TaskWeekUi();

	$t->qs('#tasks-calendar-top')->outerHtml($uiTaskWeek->getCalendar($data->eStructure, $data->week));

	$t->qs('#planning-week')->outerHtml($uiTaskWeek->get($data->eStructure, $data->week, $data->weekNext, $data->cTask, $data->eMember, $data->ccTask, $data->ccTaskTodo, $data->ccTaskDone, $data->countByStatus, $data->countByAction, $data->todo, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->zones, $data->folded, $data->dues, $data->reminders, $data->canReminder, $data->canHarvest, $data->timeExpected, $data->view, $data->locked, $data->legend));

});

/**
 * La raison des jours verrouillés, sous la grille (DF) : une coche, une décoche, un temps ou une récolte change
 * ce qui est commencé, donc chaque réponse d'un geste sur une intervention la remplace.
 */
function planningTaskWeekLocked(AjaxTemplate $t, bool $grid, bool $locked): void {

	if($grid) {
		$t->qs('#planning-grid-locked')->outerHtml(new \planning\TaskWeekGridUi()->getLocked($locked));
	}

}

/**
 * Ce qu'un geste change dans les Colonnes (GQ), colonne par colonne (`planningTaskWeekColumnsChange()`). Une
 * intervention qui change d'état a la même carte des deux côtés : chaque sélecteur est donc borné par sa
 * colonne, et ce qui quitte une colonne s'y retire avant que l'autre ne soit rendue. Une carte gardée dans
 * « À faire » a sa copie dans « Fait » (`TaskWeekLib::getColumns()`), que la page désigne (`kept`,
 * `$idsArrivedKept`). Les comptes des onglets suivent, et celui d'une colonne qui reçoit une intervention
 * s'éclaire avec elle.
 */
function planningTaskWeekColumns(AjaxTemplate $t, \structure\Structure $eStructure, string $week, \Collection $ccTaskTodo, \Collection $ccTaskDone, array $countByStatus, array $countByAction, array $changeColumns, \Collection $cUser, array $canCreateByStatus, ?array $reminders, bool $canReminder, array $idsArrived, array $idsArrivedKept, array $statusesArrived): void {

	$uiTaskWeek = new \planning\TaskWeekUi();

	$columns = [
		\series\Task::TODO => $ccTaskTodo,
		\series\Task::DONE => $ccTaskDone,
	];

	foreach($columns as $status => $ccTaskStatus) {

		$change = $changeColumns[$status];

		if($change === 'column') {
			continue;
		}

		$column = '#planning-week-'.$status;

		foreach($change['card'] as $id => $action) {
			$t->qs($column.' #planning-task-'.$id.(in_array($id, $change['kept']) ? '-kept' : ''))->outerHtml(new \planning\TaskCardUi()->get($eStructure, $week, $ccTaskStatus[$action][$id], $cUser));
		}

		foreach($change['remove'] as $id) {
			$t->qs($column.' #planning-task-'.$id.(in_array($id, $change['kept']) ? '-kept' : ''))->remove();
		}

		foreach($change['emptied'] as $action) {
			$t->qs('#planning-week-'.$status.'-block-'.$action)->remove();
		}

		foreach($change['counter'] as $action) {
			$t->qs('#planning-week-'.$status.'-counter-'.$action)->outerHtml($uiTaskWeek->getBlockCounter($action, $countByAction[$status][$action], $status));
		}

	}

	foreach($columns as $status => $ccTaskStatus) {

		if($changeColumns[$status] === 'column') {
			$t->qs('#planning-week-'.$status)->outerHtml($uiTaskWeek->getColumn($eStructure, $week, $status, $ccTaskStatus, $countByAction[$status], $cUser, $canCreateByStatus[$status], $reminders, $canReminder));
		}

		$t->qs('#planning-week-'.$status.'-counter')->outerHtml($uiTaskWeek->getColumnCounter($status, $countByStatus[$status]));

	}

	$highlight = array_merge(
		array_map(fn(int $id) => '#planning-week-board #planning-task-'.$id.(in_array($id, $idsArrivedKept) ? '-kept' : ''), $idsArrived),
		array_map(fn(string $status) => '#planning-week-'.$status.'-counter', $statusesArrived)
	);

	if($highlight !== []) {
		$t->js()->highlight($highlight);
	}

	$t->js()->eval('PlanningTaskSelection.change()');

}

/**
 * Les nœuds de la semaine qu'une réponse ciblée remplace, en cartes ou en grille (CW) : la même valeur de
 * `TaskWeekLib::getChange()` ou de `TaskBatchLib::getCreateChange()` désigne la carte ou la ligne, le bloc
 * ou le groupe, les piles ou la grille, que la page a choisis (`$data->grid`).
 */
function planningTaskWeekBoard(AjaxTemplate $t, bool $grid, \structure\Structure $eStructure, string $week, \Collection $cTask, \Collection $ccTask, array $todo, \Collection $cUser, bool $canCreate, array $canCreateByStatus, bool $empty, \structure\Member $eMember, ?array $reminders, bool $canReminder, bool $canHarvest): void {

	if($grid) {
		$board = new \planning\TaskWeekGridUi()->getBoard($eStructure, $week, $cTask, $ccTask, $todo, $cUser, $canCreate, $canCreateByStatus, $empty, $reminders, $canReminder, $canHarvest);
	} else {
		$board = new \planning\TaskWeekUi()->getBoard($eStructure, $week, $cTask, $ccTask, $todo, $cUser, $canCreate, $canCreateByStatus, $empty, $eMember, $reminders, $canReminder);
	}

	$t->qs('#planning-week-board')->outerHtml($board);

}

function planningTaskWeekBlock(AjaxTemplate $t, bool $grid, \structure\Structure $eStructure, string $week, \Collection $cTaskAction, int $todo, \Collection $cUser, bool $canCreate, int $rank, bool $canHarvest): void {

	$action = $cTaskAction->first()['action']['id'];

	if($grid) {
		$t->qs('#planning-grid-block-'.$action)->outerHtml(new \planning\TaskWeekGridUi()->getGroup($eStructure, $week, $cTaskAction, $todo, $cUser, $canHarvest));
	} else {
		$t->qs('#planning-week-block-'.$action)->outerHtml(new \planning\TaskWeekUi()->getBlock($eStructure, $week, $cTaskAction, $todo, $cUser, $canCreate, $rank));
	}

}

function planningTaskWeekBlockRemove(AjaxTemplate $t, bool $grid, int $action): void {

	$t->qs(($grid ? '#planning-grid-block-' : '#planning-week-block-').$action)->remove();

}

function planningTaskWeekCard(AjaxTemplate $t, bool $grid, \structure\Structure $eStructure, string $week, \series\Task $eTaskWeek, \Collection $cUser, bool $canHarvest): void {

	if($grid) {
		$html = new \planning\TaskWeekGridUi()->getRow($eStructure, $week, $eTaskWeek, $cUser, $canHarvest);
	} else {
		$html = new \planning\TaskCardUi()->get($eStructure, $week, $eTaskWeek, $cUser);
	}

	$t->qs(planningTaskWeekCardId($grid, $eTaskWeek))->outerHtml($html);

}

function planningTaskWeekCardId(bool $grid, \series\Task $eTask): string {

	return ($grid ? '#planning-grid-task-' : '#planning-task-').$eTask['id'];

}

/**
 * Les rangs des blocs de la semaine après le retrait d'un bloc vidé (`emptied`, R-Retours-0810, 222) : ceux
 * qu'un rechargement leur donnerait. Le bloc retiré ne déplace rien entre les piles, mais un trou dans les
 * rangs ferait égaler le rang d'un bloc rendu seul ensuite (`block`) à celui d'un voisin, et
 * `MainPiles.stack()` ne saurait plus les ordonner au redimensionnement suivant.
 */
function planningTaskWeekRanks(AjaxTemplate $t, bool $grid, \Collection $ccTask): void {

	// La grille n'a pas de piles, donc pas de rang à recalculer.
	if($grid) {
		return;
	}

	foreach($ccTask->getKeys() as $rank => $action) {
		$t->qs('#planning-week-block-'.$action)->setAttribute('data-util-piles-rank', $rank);
	}

}

/**
 * En grille, « En retard » est un tableau de lignes et « Non planifié » reste en cartes (DZ).
 */
function planningTaskWeekZoneGrid(bool $grid, string $name): bool {
	return ($grid and $name === \planning\TaskLateLib::LATE);
}

/**
 * Ce qu'un geste retire des zones sous la semaine (L8), avant que les blocs de la semaine ne changent : une
 * carte qui passe d'une zone à la semaine a le même identifiant des deux côtés, et elle doit avoir quitté
 * sa zone avant d'arriver dans son bloc, sans quoi le retrait viserait la carte arrivée. Une ligne du tableau
 * « En retard » et celle de la grille de la semaine partagent de même leur identifiant.
 */
function planningTaskWeekZonesLeave(AjaxTemplate $t, bool $grid, string|array $changeZones): void {

	if($changeZones === 'zones') {
		return;
	}

	foreach($changeZones as $name => $change) {

		if($change !== 'board') {

			$rows = planningTaskWeekZoneGrid($grid, $name);

			foreach($change['remove'] as $id) {
				$t->qs(planningTaskWeekCardId($rows, new \series\Task(['id' => $id])))->remove();
			}

			foreach($change['emptied'] as $action) {
				$t->qs('#planning-week-'.$name.'-block-'.$action)->remove();
			}

		}

	}

}

/**
 * Ce qu'un geste change ensuite dans les zones (L8, `TaskLateLib::getChange()`) : les deux zones entières,
 * ou les blocs d'une zone, ou ses blocs touchés et ses cartes, puis le compte de chaque zone qui reste à
 * l'écran. En grille, les mêmes changements visent le tableau, les groupes et les lignes de « En retard », qui
 * n'a pas de piles, donc pas de rang à reposer.
 */
function planningTaskWeekZonesEnter(AjaxTemplate $t, bool $grid, \structure\Structure $eStructure, string $week, array $zones, string|array $changeZones, \Collection $cUser, bool $canHarvest): void {

	$uiTaskWeek = new \planning\TaskWeekUi();
	$uiTaskWeekGrid = new \planning\TaskWeekGridUi();
	$uiTaskCard = new \planning\TaskCardUi();

	if($changeZones === 'zones') {
		$t->qs('#planning-week-before')->outerHtml($uiTaskWeek->getZones($eStructure, $week, $zones, $cUser, $grid, $canHarvest));
		return;
	}

	foreach($changeZones as $name => $change) {

		$zone = $zones[$name];
		$rows = planningTaskWeekZoneGrid($grid, $name);

		if($zone['count'] === 0) {
			continue;
		}

		if($change === 'board') {

			$t->qs('#planning-week-'.$name.'-board')->outerHtml($uiTaskWeek->getZoneBoard($eStructure, $week, $zone, $cUser, $grid, $canHarvest));

		} else {

			foreach($change['block'] as $action) {

				if($rows) {
					$html = $uiTaskWeekGrid->getZoneGroup($eStructure, $week, $zone, $action, $cUser, $canHarvest);
				} else {
					$html = $uiTaskWeek->getZoneBlock($eStructure, $week, $zone, $action, $cUser, $zone['blocks'][$action]['rank']);
				}

				$t->qs('#planning-week-'.$name.'-block-'.$action)->outerHtml($html);

			}

			foreach($change['card'] as $id) {

				$eTask = $zone['cTask'][$id];
				$html = $rows ? $uiTaskWeekGrid->getRow($eStructure, $week, $eTask, $cUser, $canHarvest) : $uiTaskCard->get($eStructure, $week, $eTask, $cUser);

				$t->qs(planningTaskWeekCardId($rows, $eTask))->outerHtml($html);

			}

			foreach(array_diff($change['counter'], $change['block']) as $action) {
				$t->qs('#planning-week-'.$name.'-counter-'.$action)->outerHtml($uiTaskWeek->getZoneBlockCounter($name, $action, $zone['blocks'][$action]['count']));
			}

			// Le bloc vidé est sorti avant (`planningTaskWeekZonesLeave()`) : les suivants prennent leur rang d'après.
			if($change['emptied'] !== [] and $rows === FALSE) {
				foreach($zone['blocks'] as $action => $block) {
					$t->qs('#planning-week-'.$name.'-block-'.$action)->setAttribute('data-util-piles-rank', $block['rank']);
				}
			}

		}

		$t->qs('#planning-week-'.$name.'-counter')->outerHtml($uiTaskWeek->getZoneCounter($name, $zone['count']));

	}

}

/**
 * La réponse des deux réglages de la personnalisation : le bandeau des réglages, redessiné avec le choix
 * posé. La couleur bascule aussi la classe de la page, qui montre ou cache la pastille de chaque action.
 */
new JsonView('doDensity', function($data, AjaxTemplate $t) {

	$t->qs('#planning-customize-tools')->outerHtml(new \planning\TaskWeekUi()->getCustomizeTools($data->eMember));

});

new JsonView('doColor', function($data, AjaxTemplate $t) {

	if($data->eMember['viewPlanningColor']) {
		$t->qs('#planning-customize')->addClass('planning-color');
	} else {
		$t->qs('#planning-customize')->removeClass('planning-color');
	}

	$t->qs('#planning-customize-tools')->outerHtml(new \planning\TaskWeekUi()->getCustomizeTools($data->eMember));

});
new JsonView(['doReminders', 'doSalesDays'], function($data, AjaxTemplate $t) {

	$t->qs('#planning-customize-tools')->outerHtml(new \planning\TaskWeekUi()->getCustomizeTools($data->eMember));

});

new AdaptativeView('reminderCreate', function($data, PanelTemplate $t) {

	return new \planning\ReminderUi()->create($data->eReminder, $data->view);

});

/**
 * La réponse des gestes du pense-bête, à la granularité que la page a décidée
 * (`planningTaskWeekReminderRespond()`), sans message de succès : la ligne touchée s'éclaire, redessinée
 * à sa place, ou s'efface, dans la note ou dans le groupe de la grille selon l'affichage (`$data->grid`).
 * Le geste n'est permis que pense-bête allumé (le bloc porte le « + » et les cases) ; s'il a été éteint
 * depuis, dans un autre onglet, rien n'est redessiné.
 */
new JsonView('doReminder', function($data, AjaxTemplate $t) {

	if($data->gesture === 'create') {
		$t->js()->closePanel('#panel-planning-reminder-create');
	}

	if($data->reminders === NULL) {
		return;
	}

	$uiReminder = new \planning\ReminderUi();

	$head = fn() => $data->grid ?
		$uiReminder->getGridHead($data->eStructure, $data->week, $data->reminders['todo'], $data->canReminder) :
		$uiReminder->getHead($data->eStructure, $data->week, $data->reminders['todo'], $data->canReminder);

	switch($data->change) {

		case 'block' :
			$block = $data->grid ?
				$uiReminder->getGridGroup($data->eStructure, $data->week, $data->reminders, $data->canReminder) :
				$uiReminder->getBlock($data->eStructure, $data->week, $data->reminders, $data->canReminder);
			$t->qs('#planning-reminder-block')->outerHtml($block);
			if($data->gesture === 'create' or $data->gesture === 'update') {
				$t->js()->highlight(['#planning-reminder-'.$data->eReminder['id']]);
			}
			break;

		case 'line' :
			$line = $data->grid ?
				$uiReminder->getGridRow($data->eStructure, $data->week, $data->eReminder, $data->before) :
				$uiReminder->getReminder($data->eStructure, $data->week, $data->eReminder, $data->before);
			$t->qs('#planning-reminder-'.$data->eReminder['id'])->outerHtml($line);
			$t->qs('#planning-reminder-head')->outerHtml($head());
			$t->js()->highlight(['#planning-reminder-'.$data->eReminder['id']]);
			break;

		case 'restes' :
			$t->qs('#planning-reminder-restes')->remove(vanish: TRUE);
			$t->qs('#planning-reminder-'.$data->eReminder['id'])->remove(vanish: TRUE);
			$t->qs('#planning-reminder-head')->outerHtml($head());
			break;

		case 'vanish' :
			$t->qs('#planning-reminder-'.$data->eReminder['id'])->remove(vanish: TRUE);
			$t->qs('#planning-reminder-head')->outerHtml($head());
			break;

		case 'remove' :
			$t->qs('#planning-reminder-'.$data->eReminder['id'])->remove();
			$t->qs('#planning-reminder-head')->outerHtml($head());
			break;

	}

});
?>
