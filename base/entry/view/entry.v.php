<?php
new JsonView('read', function($data, AjaxTemplate $t) {

	$t->push('results', \entry\EntryUi::getResults($data->eStructure, $data->reading, $data->cElement, $data->intentions, $data->cAction, $data->completion));
	$t->push('entry', \entry\EntryUi::getEntry($data->reading, $data->completion));

});

/**
 * Les instructions du planning, communes à l'écriture et à l'annulation : `PlanningLib::getChangeCollection()`
 * a décidé en PHP du niveau et de l'ancrage, elles s'appliquent telles quelles. Tout sélecteur est
 * borné par `#planning-weekly-done`, le même `#task-item-<id>` pouvant être rendu dans À faire.
 *
 * Une tête d'espèce n'a pas d'id : elle se désigne par la ligne qui la suit
 * (`.tasks-planning-plant:has(+ #task-item-<id>)`), et se retire donc avant elle.
 */
$writePlanning = function(AjaxTemplate $t, \structure\Structure $eStructure, array $context, \Collection $cccTask, array $change, \user\User $eUserTime): void {

	$uiPlanning = new \series\PlanningUi();

	// Comme `workingTime:getByUser` le rend, pour l'utilisateur dont le temps vient de changer.
	if($eUserTime->notEmpty()) {
		$t->qs('#planning-weekly-time')->innerHtml($uiPlanning->getWeekTime($eStructure, $context['week'], $cccTask, $eUserTime, $context['cUserStructure']));
	}

	$week = $context['week'];
	$eTask = $change['eTask'];
	$anchor = $change['anchor'];

	$done = '#planning-weekly-done';
	$item = $done.' #task-item-'.$change['id'];

	switch($change['level']) {

		case \entry\PlanningLib::LINE :
			$t->qs($item)->outerHtml($uiPlanning->getWeekTask($eStructure, $week, $eTask, $context['cUserStructure']));
			$t->qs($item)->addClass('tasks-planning-item-arrived', ['lifetime' => 1200]);
			break;

		case \entry\PlanningLib::INSERT :
			$t->qs($done.' #task-item-'.$anchor['task'])->insertAdjacentHtml($anchor['position'], $uiPlanning->getWeekTask($eStructure, $week, $eTask, $context['cUserStructure']));
			$t->qs($item)->addClass('tasks-planning-item-appeared', ['lifetime' => 1200]);
			break;

		case \entry\PlanningLib::INSERT_PLANT :

			// La ligne, puis la tête de l'espèce devant elle : le client n'insère qu'une racine par instruction.
			$html = $uiPlanning->getWeekTask($eStructure, $week, $eTask, $context['cUserStructure']);

			if(isset($anchor['task'])) {
				$t->qs($done.' #task-item-'.$anchor['task'])->insertAdjacentHtml($anchor['position'], $html);
			} else {
				$t->qs($done.' .tasks-planning-items[data-filter-action="'.$anchor['action'].'"] > .tasks-planning-action')->insertAdjacentHtml(\entry\PlanningLib::AFTER, $html);
			}

			$t->qs($item)->insertAdjacentHtml(\entry\PlanningLib::BEFORE, $uiPlanning->getWeekPlant($eTask['plant']));

			$t->qs($done.' .tasks-planning-plant:has(+ #task-item-'.$eTask['id'].')')->addClass('tasks-planning-item-appeared', ['lifetime' => 1200]);
			$t->qs($item)->addClass('tasks-planning-item-appeared', ['lifetime' => 1200]);
			break;

		case \entry\PlanningLib::INSERT_ACTION :

			$html = $uiPlanning->getWeekAction($eStructure, $week, $change['cTask'], $context['cUserStructure'], $context['eActionSelected'], $context['ePlantSelected']);

			if(isset($anchor['header'])) {
				$t->qs($done.' > .planning-week-header')->insertAdjacentHtml(\entry\PlanningLib::AFTER, $html);
			} else {
				$t->qs($done.' .tasks-planning-items[data-filter-action="'.$anchor['action'].'"]')->insertAdjacentHtml(\entry\PlanningLib::AFTER, $html);
			}

			$t->qs($done.' .tasks-planning-items[data-filter-action="'.$change['action']['id'].'"]')->addClass('tasks-planning-item-appeared', ['lifetime' => 1200]);
			break;

		// Plusieurs lignes du même bloc ont changé : le bloc se redessine, et chacune se signale comme une ligne seule.
		case \entry\PlanningLib::ACTION :

			$t->qs($done.' .tasks-planning-items[data-filter-action="'.$change['action']['id'].'"]')->outerHtml($uiPlanning->getWeekAction($eStructure, $week, $change['cTask'], $context['cUserStructure'], $context['eActionSelected'], $context['ePlantSelected']));

			foreach($change['cTaskChanged'] as $eTaskChanged) {
				$t->qs($done.' #task-item-'.$eTaskChanged['id'])->addClass('tasks-planning-item-arrived', ['lifetime' => 1200]);
			}

			break;

		case \entry\PlanningLib::REMOVE :
			$t->qs($item)->remove();
			break;

		case \entry\PlanningLib::REMOVE_PLANT :
			$t->qs($done.' .tasks-planning-plant:has(+ #task-item-'.$change['id'].')')->remove();
			$t->qs($item)->remove();
			break;

		case \entry\PlanningLib::REMOVE_ACTION :
			$t->qs($done.' .tasks-planning-items[data-filter-action="'.$change['action']['id'].'"]')->remove();
			break;

	}

	if($change['todo']) {
		$t->qs('#planning-weekly-todo-list')->outerHtml($uiPlanning->getWeekTodo($eStructure, $week, $cccTask, $context['cUserStructure'], $context['eActionSelected'], $context['ePlantSelected'], new \planning\DueUi()->getWeek($eStructure, $week, $context['dues'])));
	}

	// La barre de filtres disparaît sur une semaine vide à un seul utilisateur (`getFilters()` rend '') :
	// elle se retire puis se repose après le temps de travail, qui la précède toujours.
	if($change['filters']) {

		$t->qs('#planning-weekly-wrapper > .planning-filters')->remove();

		$url = \structure\StructureUi::urlPlanningWeekly($eStructure, $week);
		$ePlot = $context['search']->get('plot');

		if($ePlot->notEmpty()) {
			$url = \util\HttpUi::setArgument($url, 'plot', $ePlot['id']);
		}

		$filters = $uiPlanning->getFilters($eStructure, $url, $cccTask, $context['cUserStructure'], $context['eUserSelected'], $context['cAction'], $context['eActionSelected'], NULL, $context['ePlantSelected']);

		if($filters !== '') {
			$t->qs('#planning-weekly-time')->insertAdjacentHtml(\entry\PlanningLib::AFTER, $filters);
		}

	}

};

/**
 * Les instructions de l'accueil, communes à l'écriture et à l'annulation faites depuis son champ :
 * `HomeLib::getChange()` a décidé en PHP de ce qui se redessine, elles s'appliquent telles quelles. Les
 * colonnes rendues entières se reposent en fin d'accueil, où elles sont toujours, et s'y posent aussi
 * quand l'accueil n'en avait pas encore. La file n'a qu'une tuile, qui se trouve par sa classe.
 */
$writeHome = function(AjaxTemplate $t, \structure\Structure $eStructure, array $change): void {

	$uiDashboard = new \dashboard\DashboardUi();

	if($change['columns'] !== NULL) {

		$t->qs('#dashboard > .dashboard-columns')->remove();
		$t->qs('#dashboard')->insertAdjacentHtml('beforeend', $uiDashboard->getColumns($eStructure, $change['columns']['blocks'], $change['columns']['contributions']));

		return;

	}

	if($change['queue'] !== NULL) {
		$t->qs('#dashboard .dashboard-queue')->outerHtml($uiDashboard->getQueue($eStructure, $change['queue']));
	}

	foreach($change['replace'] as $block => $entry) {
		$t->qs('#'.\dashboard\DashboardUi::getBlockId($block))->outerHtml($uiDashboard->getBlock($eStructure, $block, $entry, column: $entry['declaration']['column']));
	}

	foreach($change['remove'] as $block) {
		$t->qs('#'.\dashboard\DashboardUi::getBlockId($block))->remove();
	}

};

/**
 * L'écriture pose sa pastille, puis rend les nœuds du planning qui changent (`PlanningLib::getChange()`).
 * La pastille vient en tête : elle part de la ligne de confirmation, qu'elle mesure à son arrivée, et
 * les instructions s'appliquent dans l'ordre. `written` dit au champ que l'écriture a eu lieu :
 * `Ajax.Query` résout aussi une réponse en erreur, le champ ne se vide que sur celle-ci.
 *
 * La pastille et l'événement sont ceux de l'intention écrite (`IntentionUi`), le planning n'est rendu que
 * pour une intention qui l'écrit (`$data->context`).
 */
new JsonView('doWrite', function($data, AjaxTemplate $t) use($writePlanning, $writeHome) {

	$reading = $data->reading;
	$ui = \entry\EntryUi::getIntentions()[$reading['intention']];

	$t->js()->toast(...$ui::getWriteToast($reading), undo: \entry\EntryUi::getUndo($data->eStructure, $reading['undo']), from: \entry\EntryUi::TOAST_FROM);

	if($data->context !== NULL) {
		$writePlanning($t, $data->eStructure, $data->context, $data->cccTask, $data->change, $data->eUserTime);
	}

	if($data->homeChange !== NULL) {
		$writeHome($t, $data->eStructure, $data->homeChange);
	}

	$t->push('written', TRUE);
	$t->push('event', $ui::getWriteEvent($data->eStructure, $reading));

	// Le jour dit reste en jeton pour la saisie suivante : une série de récoltes de la veille ne le
	// redit pas à chaque ligne.
	$t->push('date', ($data->date === NULL) ? NULL : ['value' => $data->date, 'label' => \entry\DateComplementUi::getToken($data->date)]);

});

new JsonView('doCancel', function($data, AjaxTemplate $t) use($writePlanning, $writeHome) {

	if($data->context !== NULL) {
		$writePlanning($t, $data->eStructure, $data->context, $data->cccTask, $data->change, $data->eUserTime);
	}

	if($data->homeChange !== NULL) {
		$writeHome($t, $data->eStructure, $data->homeChange);
	}

	$ui = \entry\EntryUi::getIntentions()[$data->intention];

	$t->push('cancelled', $ui::getCancelEvent($data->eStructure, $data->cancellation));

	$t->js()->toast(...$ui::getCancelToast($data->cancellation));

});
?>
