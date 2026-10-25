<?php
new AdaptativeView('taskDetail', function($data, PanelTemplate $t) {
	return new \planning\TaskDetailUi()->get($data->eTask, $data->week, $data->cPlace, $data->cPhoto, $data->cUser, $data->cComment, $data->history, $data->days, $data->foot, $data->navigation, $data->timesByDay);
});

/**
 * Une autre semaine dans le « Temps de travail » : la tête, qui la nomme et porte le calendrier, et le tableau.
 * Le jour saisi qui l'a demandée amène le tableau à l'écran et éclaire sa colonne : le dépli du total est
 * au-dessus, et le tableau souvent hors de vue.
 */
new JsonView('week', function($data, AjaxTemplate $t) {

	$ui = new \planning\TaskDetailUi();
	$context = \planning\TaskDetailUi::getGridDetail($data->eTask, $data->week, $data->weekShown);

	$t->qs('#planning-task-detail-nav')->outerHtml($ui->getNav($data->eTask, $data->week, $data->navigation));
	$t->qs('#planning-task-detail-grid')->outerHtml($ui->getGrid($data->eTask, $data->cUser, $data->days, $data->foot, $context));

	if($data->day !== NULL) {
		$t->qs('#planning-task-detail-grid')->scrollTo('center', 'smooth');
		$t->js()->highlight(['#planning-task-detail-grid [data-date="'.$data->day.'"]']);
	}

});

/**
 * La ligne d'une personne ajoutée : le tableau d'un seul nœud, qui porte la liste des lignes ajoutées.
 */
new JsonView('user', function($data, AjaxTemplate $t) {

	$t->qs('#planning-task-detail-grid')->outerHtml(new \planning\TaskDetailUi()->getGrid($data->eTask, $data->cUser, $data->days, $data->foot, \planning\TaskDetailUi::getGridDetail($data->eTask, $data->week, $data->weekShown)));

});

/**
 * La réponse d'une saisie de temps : le total du bloc et les jours saisis qu'il déplie, puis la case écrite, la
 * somme de sa ligne et le pied, ou le tableau entier quand une ligne entre ou sort
 * (`TaskDetailLib::getChange()`), l'historique, où la saisie vient d'écrire son écart, puis la carte de
 * l'écran de la semaine resté derrière le panneau, quand la page en vient et que l'intervention y est. Le
 * champ dont un envoi est encore en route, et la fenêtre ouverte entre-temps, se retrouvent par leur clé
 * (`PlanningDurationStep.resume()`). La page ouverte seule (rechargée, nouvel onglet, lien) n'a pas de
 * semaine derrière elle : pas de carte, seulement le bloc.
 *
 * La fenêtre de la case s'est fermée à l'envoi (FJ, retour 411) : la case redessinée s'éclaire pour dire que
 * la durée est écrite.
 *
 * Un jour écrit hors de la semaine que l'écran montre, quand la semaine a changé pendant que l'envoi
 * attendait dans la file, n'a pas de case à l'écran (`dateShown`) : le total le dit seul.
 */
new JsonView('doTime', function($data, AjaxTemplate $t) {

	$ui = new \planning\TaskDetailUi();
	$context = \planning\TaskDetailUi::getGridDetail($data->eTask, $data->week, $data->weekShown);

	$t->qs('#planning-task-detail-total')->outerHtml($ui->getTotal($data->eTask));
	$t->qs('#planning-task-detail-worked')->outerHtml($ui->getWorked($data->eTask, $data->week, $data->cUser, $data->timesByDay));

	switch($data->change) {

		case 'user' :
			if($data->dateShown) {
				$t->qs('#planning-task-detail-cell-'.$data->eUserTime['id'].'-'.$data->date)->outerHtml($ui->getGridCell($data->eTask, $data->eUserTime, $data->date, $data->days[$data->date], $context), highlight: TRUE);
				$t->qs('#planning-task-detail-sum-'.$data->eUserTime['id'])->outerHtml($ui->getGridSum($data->eUserTime, $context));
				$t->qs('#planning-task-detail-foot')->outerHtml($ui->getGridFoot($data->days, $data->foot, $context));
			}
			break;

		case 'grid' :
			$t->qs('#planning-task-detail-grid')->outerHtml($ui->getGrid($data->eTask, $data->cUser, $data->days, $data->foot, $context));
			break;

	}

	$t->qs('#planning-detail-history')->outerHtml($ui->getHistory($data->eTask, $data->history));

	planningTaskDetailCard($t, $data->eStructure, $data->week, $data->eTask, $data->screen, $data->eTaskCard, $data->cUserCard);

});

/**
 * La réponse d'une affectation : le nom de la personne, qui porte son état, ou le tableau entier quand sa
 * ligne entre, sort ou change de rang, puis la carte, qui montre les personnes affectées.
 */
new JsonView('doUser', function($data, AjaxTemplate $t) {

	$ui = new \planning\TaskDetailUi();
	$context = \planning\TaskDetailUi::getGridDetail($data->eTask, $data->week, $data->weekShown);

	switch($data->change) {

		case 'user' :
			$t->qs('#planning-task-detail-user-'.$data->eUserAssigned['id'])->outerHtml($ui->getGridUser($data->eUserAssigned, $context));
			break;

		case 'grid' :
			$t->qs('#planning-task-detail-grid')->outerHtml($ui->getGrid($data->eTask, $data->cUser, $data->days, $data->foot, $context));
			break;

	}

	planningTaskDetailCard($t, $data->eStructure, $data->week, $data->eTask, $data->screen, $data->eTaskCard, $data->cUserCard);

});

/**
 * La réponse d'une récolte : la récolte de la fiche, l'historique et la carte, comme une saisie de temps.
 */
new JsonView('doHarvest', function($data, AjaxTemplate $t) {

	$ui = new \planning\TaskDetailUi();

	$t->qs('#planning-detail-harvest')->outerHtml($ui->getHarvest($data->eTask, $data->week));
	$t->qs('#planning-detail-history')->outerHtml($ui->getHistory($data->eTask, $data->history));

	planningTaskDetailCard($t, $data->eStructure, $data->week, $data->eTask, $data->screen, $data->eTaskCard, $data->cUserCard);

});

/**
 * La carte de l'écran de la semaine resté derrière le panneau, après une écriture.
 */
function planningTaskDetailCard(AjaxTemplate $t, \structure\Structure $eStructure, ?string $week, \series\Task $eTask, bool $screen, \series\Task $eTaskCard, \Collection $cUserCard): void {

	if($screen) {
		$t->js()->eval('PlanningTaskZone.screen()');
	} else if($eTaskCard->notEmpty()) {
		$t->qs('#planning-task-'.$eTask['id'])->outerHtml(new \planning\TaskCardUi()->get($eStructure, $week, $eTaskCard, $cUserCard));
	}

}
?>
