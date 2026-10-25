<?php
new AdaptativeView('taskDay', function($data, StructureTemplate $t) {

	$t->title = s("Interventions de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\TaskDayUi::url($data->eStructure, $data->week);

	$uiTaskDay = new \planning\TaskDayUi();

	$t->mainTitle = $uiTaskDay->getCalendar($data->eStructure, $data->week);

	// Comme à la Semaine (`taskWeek.v.php`) : rendu entier, l'écran vide la sélection avant que ses cases ne la relisent.
	echo '<div id="planning-board" onrender="PlanningTaskSelection.reset()">';
		echo new \planning\TabUi()->get($data->eStructure, $data->week, 'jour', $data->eMember);
		echo $uiTaskDay->get($data->eStructure, $data->week, $data->weekNext, $data->columns, $data->eMember, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->dues, $data->reminders);
	echo '</div>';

});

/**
 * « Nouvelle intervention » (L5d), pour le Jour comme pour la Semaine et le Temps de travail, qui le
 * rendent par son adresse (`planning/taskDay:create`).
 */
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \planning\TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime, post: $data->post);

});

/**
 * « Quand » redessiné pour la semaine choisie ou l'état coché (`PlanningTaskCreateWhen`) : la rangée des semaines et
 * des jours, la semaine retenue, la saison où « Espèce ou série » cherche, et pour Fait le temps de chaque personne.
 */
new JsonView('getWhen', function($data, AjaxTemplate $t) {

	$uiTaskCreate = new \planning\TaskCreateUi();

	$t->push('when', $uiTaskCreate->getWhen($data->eStructure, $data->weeks, $data->when, $data->days, $data->date, $data->status));
	$t->push('week', $data->when);

	$head = new \util\FormUi()->getAutocompleteHead(\planning\TaskCreateUi::getSeasonHead($data->when));

	$t->push('season', [
		'head' => $head['data-autocomplete-head'],
		'selected' => $head['data-autocomplete-head-selected'],
	]);

	if($data->status === \series\Task::DONE) {
		$t->push('time', $uiTaskCreate->getTime(new \util\FormUi(), $data->cUserTime));
	}

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \planning\TaskDayUi::getAutocompleteResults($data->cCultivation, $data->cultivationOthers, $data->cPlant, $data->plantOthers));

});

new JsonView('harvest', function($data, AjaxTemplate $t) {

	if($data->harvest === []) {
		$t->push('harvest', '');
		return;
	}

	$t->push('harvest', new \planning\TaskBatchUi()->getHarvestBlock(
		new \util\FormUi(),
		$data->harvest['cTask'],
		$data->harvest['distributions'],
		$data->harvest['distribution'],
		$data->harvest['ePlant'],
		$data->harvest['unit'],
		$data->harvest['tasksByUnit'],
		$data->harvest['eTaskStock'],
		stockId: 'planning-task-create-stock',
		timeShare: $data->eStructure->hasTime()
	));

});

new JsonView('getCultivationFields', function($data, AjaxTemplate $t) {

	$form = new \util\FormUi();

	$t->push('variety', new \series\TaskUi()->getVarietyGroup($form, $data->eTask, $data->eTask['cVariety'], $data->eTask['varietiesIntersect']));
	$t->push('size', \planning\TaskCreateUi::getSize($form, $data->eTask));
	$t->push('shared', \planning\TaskCreateUi::getSharedFields($form, $data->eTask));

});

new JsonView('queryAction', function($data, AjaxTemplate $t) {

	$t->push('results', \planning\TaskCreateUi::getActionResults($data->cCategory, $data->cActionFrequent));

});

/**
 * La réponse des gestes du Jour : les colonnes que le geste change (`TaskDayLib::getChange()`), redessinées
 * entières, ou l'écran entier quand la rangée des outils apparaît ou disparaît (`planningTaskDayScreen()`).
 * « Nouvelle intervention » ferme son panneau. Un geste de lot vide d'abord la sélection, avant que les cartes redessinées ne la relisent,
 * quel que soit le geste, « Affecter » compris (retour 253).
 */
new JsonView('doTask', function($data, AjaxTemplate $t) {

	$uiTaskDay = new \planning\TaskDayUi();

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
		$t->qs('#planning-day')->outerHtml($uiTaskDay->get($data->eStructure, $data->week, $data->weekNext, $data->columns, $data->eMember, $data->cUser, $data->canCreate, $data->canCreateByStatus, $data->empty, $data->dues, $data->reminders));
	} else {

		foreach($data->change as $key) {
			$t->qs('#planning-day-'.$key)->outerHtml($uiTaskDay->getColumn($data->eStructure, $data->week, $key, $data->columns[$key], $data->cUser, $data->canCreate, $data->empty, $data->reminders === NULL ? NULL : $data->reminders[$key]));
		}
	}

	if($data->gesture === 'create') {

		$t->js()->closePanel('#panel-planning-task-create');

		if($data->success !== NULL) {
			$t->js()->success('planning', 'Task::createdOutside', $data->success);
		}

	}

});

/**
 * Un geste du pense-bête : les rappels des colonnes touchées, décidées par la page
 * (`planningTaskDayReminderRespond()`), puis le rappel éclairé s'il est encore à l'écran.
 */
new JsonView('doReminder', function($data, AjaxTemplate $t) {

	$uiTaskDay = new \planning\TaskDayUi();

	foreach($data->change as $key) {
		$t->qs('#planning-day-reminders-'.$key)->outerHtml($uiTaskDay->getReminders($data->eStructure, $data->week, $key, $data->reminders[$key]));
	}

	if($data->highlight) {
		$t->js()->highlight(['#planning-reminder-'.$data->eReminder['id']]);
	}

});
?>
