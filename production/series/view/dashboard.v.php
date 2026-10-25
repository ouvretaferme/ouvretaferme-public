<?php
new JsonView('doUpdateActions', function($data, AjaxTemplate $t) {

	$t->js()->ajax('GET', \dashboard\DashboardUi::urlBlock($data->eStructure, 'series-week'));

});

/**
 * La réponse d'une coche ou d'une décoche du bloc de la semaine, à la granularité que la page a décidée
 * (`series/dashboard`), sans message de succès : la marque se redessine cochée ou vide, comme au planning.
 */
new JsonView('doTask', function($data, AjaxTemplate $t) {

	$ui = new \series\DashboardUi();

	$line = '#'.\series\DashboardUi::getWeekTaskId($data->eTask['id']);
	$action = $data->eTask['action']['id'];

	$t->qs('#'.\series\DashboardUi::getWeekValueId())->outerHtml($ui->getWeekValue($data->week['todo'], $data->week['late']));

	switch($data->change) {

		case 'line' :
			$t->qs($line)->outerHtml($ui->getWeekTask($data->eStructure, $data->week['week'], $data->task));
			$t->qs('#'.\series\DashboardUi::getWeekSummaryId($action))->outerHtml($ui->getWeekSummary($data->action));
			break;

		case 'remove' :
			$t->qs($line)->remove(vanish: TRUE);
			$t->qs('#'.\series\DashboardUi::getWeekSummaryId($action))->outerHtml($ui->getWeekSummary($data->action));
			break;

		// La ligne des actions sans rien se repose en fin de liste, qu'elle y soit déjà ou non.
		case 'empty' :
			$t->qs('#'.\series\DashboardUi::getWeekActionId($action))->remove(vanish: TRUE);
			$t->qs('#'.\series\DashboardUi::getWeekIdleId())->remove();
			$t->qs('#'.\series\DashboardUi::getWeekId())->insertAdjacentHtml('beforeend', $ui->getWeekIdle($data->week['actions']));
			break;

	}

});
?>
