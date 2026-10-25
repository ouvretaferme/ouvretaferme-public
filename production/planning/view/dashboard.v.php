<?php
/**
 * La réponse des gestes du pense-bête de l'accueil, à la granularité que la page a décidée
 * (`planning/dashboard`), sans message de succès : la ligne touchée s'éclaire à sa place ou s'efface.
 * La ligne d'ajout se redessine après un ajout, pour se refermer vidée.
 */
new JsonView('doReminder', function($data, AjaxTemplate $t) {

	$ui = new \planning\DashboardUi();

	$line = '#'.\planning\DashboardUi::getReminderId($data->eReminder['id']);

	// Un rappel se pose devant celui qui le suit dans l'ordre du bloc, et le dernier devant la ligne d'ajout.
	$getNext = fn() => '#'.(($data->next === NULL) ? \planning\DashboardUi::getReminderAddId() : \planning\DashboardUi::getReminderId($data->next));

	switch($data->change) {

		case 'insert' :
			$t->qs($getNext())->insertAdjacentHtml('beforebegin', $ui->getReminder($data->eStructure, $data->reminder));
			$t->qs('#'.\planning\DashboardUi::getReminderAddId())->outerHtml($ui->getReminderAdd($data->eStructure));
			$t->js()->highlight([$line]);
			break;

		case 'line' :
			$t->qs($line)->outerHtml($ui->getReminder($data->eStructure, $data->reminder));
			$t->js()->highlight([$line]);
			break;

		case 'move' :
			$t->qs('#'.\planning\DashboardUi::getReminderRowsId())->outerHtml($ui->getReminderRows($data->eStructure, $data->reminders));
			$t->js()->highlight([$line]);
			break;

		case 'remove' :
			$t->qs($line)->remove(vanish: TRUE);
			break;

	}

});
?>
