<?php
new AdaptativeView('taskYear', function($data, StructureTemplate $t) {

	$t->title = s("Interventions de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\TaskYearUi::url($data->eStructure, $data->week);

	$uiTaskYear = new \planning\TaskYearUi();

	$t->mainTitle = $uiTaskYear->getCalendar($data->eStructure, $data->year, $data->calendar);

	echo '<div id="planning-board">';
		echo new \planning\TabUi()->get($data->eStructure, $data->week, 'annee', $data->eMember);
		echo $uiTaskYear->get($data->eStructure, $data->week, $data->eMember, $data->columns, $data->calendar['months'], $data->canCreate, $data->canCreateByStatus);
	echo '</div>';

});

/**
 * « Nouvelle intervention » sur une semaine de l'Année, le panneau de la Semaine et du Jour.
 */
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \planning\TaskCreateUi()->create($data->eStructure, 'taskYear', $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime, $data->current);

});

/**
 * Les semaines chargées à l'approche du cadre (`PlanningTaskYear.load()`), qui remplacent leur tête seule.
 *
 * Chacune ne vise que la colonne qui porte encore le jeton du chargement : la réponse a été lue avant tout
 * geste parti pendant le trajet, et le geste a retiré le jeton des semaines qu'il change (vue `doTask`).
 * Une réponse périmée ne trouve donc rien, et le navigateur recharge la semaine restée vide.
 */
new JsonView('weeks', function($data, AjaxTemplate $t) {

	$uiTaskYear = new \planning\TaskYearUi();

	foreach($data->change as $week) {
		$t->qs('#planning-year-'.$week.'[data-load="'.$data->load.'"]')->outerHtml($uiTaskYear->getColumn($data->eStructure, $week, $data->week, $data->columns[$week], $data->canCreate));
	}

});

/**
 * La réponse des gestes de l'Année : dans chaque semaine que le geste change, la carte et la tête, ou la
 * colonne entière (`TaskYearLib::getGranularity()`).
 * « Nouvelle intervention » ferme son panneau.
 *
 * La tête ne se remplace que dans une colonne chargée : celle qui ne l'est pas encore se charge entière
 * plus tard, et un compteur sur une colonne sans cartes dirait ce qu'elle ne montre pas.
 */
new JsonView('doTask', function($data, AjaxTemplate $t) {

	$uiTaskYear = new \planning\TaskYearUi();

	// Avant tout remplacement : une colonne non chargée garde son nœud, et un chargement parti avant le geste
	// la viserait encore (vue `weeks`).
	foreach($data->change as $week) {
		$t->qs('#planning-year-'.$week)->removeAttribute('data-load');
	}

	foreach($data->change as $week) {

		$column = $data->columns[$week];

		if($data->granularity === \planning\TaskYearLib::CARD) {
			$t->qs('#planning-year-'.$week.'[data-loaded] .planning-day-title')->outerHtml($uiTaskYear->getTitle($week, $column));
			$t->qs('#'.\planning\TaskCardUi::getId($data->eTask, $week))->outerHtml($uiTaskYear->getCard($data->eStructure, $week, $column['cTask'][$data->eTask['id']]));
		} else {
			$t->qs('#planning-year-'.$week)->outerHtml($uiTaskYear->getColumn($data->eStructure, $week, $data->current, $column, $data->canCreate));
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
 * Les suggestions de la recherche, au format de l'omnibox (`OmniboxUi::get()`, `suggestionsUrl`).
 */
new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', new \planning\TabUi()->getSearchSuggestions($data->suggestions, $data->cUser, $data->cPlant));

});

/**
 * Les interventions que les jetons retiennent, par semaine (`PlanningTaskSearch`).
 */
new JsonView('search', function($data, AjaxTemplate $t) {

	$t->push('weeks', $data->matches);

});
?>
