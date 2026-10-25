<?php
new AdaptativeView('timeDay', function($data, StructureTemplate $t) {

	$t->title = s("Temps de travail de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\TimeDayUi::url($data->eStructure, $data->week, team: $data->team, weekly: $data->weekly);

	$uiTimeDay = new \planning\TimeDayUi();

	$t->mainTitle = $uiTimeDay->getCalendar($data->eStructure, $data->week, $data->eUserTime, $data->team);

	echo '<div id="planning-board">';
		if($data->team) {
			echo $uiTimeDay->getTeamSheet($data->eStructure, $data->week, $data->date, $data->timeWeek, $data->cUserPresent, $data->eMember, $data->weekly, $data->overtime, $data->complementary);
		} else {
			echo $uiTimeDay->get($data->eStructure, $data->week, $data->date, $data->cUser, $data->eUserTime, $data->timeDay, $data->entry, $data->eMember, $data->canWorking, $data->canTime, $data->canCreate, $data->zones, $data->weekly);
		}
	echo '</div>';

});

/**
 * L'onglet d'une ferme qui ne suit pas le temps : les jours, et dessous les absences de la semaine pour l'équipe.
 * Les siennes, la personne connectée les lit dans l'onglet Absences.
 */
new AdaptativeView('timeDayWithoutTime', function($data, StructureTemplate $t) {

	$t->title = s("Temps de travail de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\TimeDayUi::url($data->eStructure, $data->week, team: $data->team);

	$uiTimeDay = new \planning\TimeDayUi();

	$t->mainTitle = $uiTimeDay->getCalendar($data->eStructure, $data->week, $data->eUserTime, $data->team);

	$absences = $data->team ? new \hr\AbsenceUi()->getWeek($data->eStructure, $data->cAbsenceWeek) : '';

	echo '<div id="planning-board">';
		echo $uiTimeDay->getWithoutTime($data->eStructure, $data->week, $data->cUser, $data->eUserTime, $data->days, $data->eMember, $absences);
	echo '</div>';

});

/**
 * La journée de travail d'un jour de la fiche : son jour dans la rangée, que la semaine en champs remplace
 * pendant qu'on passe au suivant, et la somme de la semaine. Le jour choisi (`dateSelected`) garde sa pastille
 * pleine, et seul lui a, dessous, la journée que repère la barre des interventions (`getTasksDay()`) et, sous
 * horaires, sa carte, où la saisie reste ouverte après une heure, pour les minutes (`PlanningDuration.choose()`).
 * Le suivi RH seul n'a que la semaine.
 */
new JsonView('doWorkingTime', function($data, AjaxTemplate $t) {

	$uiTimeDay = new \planning\TimeDayUi();

	$selected = ($data->date === $data->dateSelected);

	$t->qs('#planning-time-day-'.$data->date)->outerHtml($uiTimeDay->getDay($data->eStructure, $data->week, $data->date, $selected, $data->eUserTime, $data->timeDay['days'][$data->date], $data->timeDay['eWorkingTime'], $data->entry));

	planningTimeDayWeekTotal($t, $uiTimeDay, $data->eStructure, $data->week, $data->eUserTime, $data->timeDay['total'], $data->entry, $data->dateSelected);

	if($data->eStructure->hasTime() and $selected) {
		planningTimeDayWorkingDay($t, $uiTimeDay, $data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->canWorking);
	}

});

/**
 * La somme de la semaine d'une personne : celle de la semaine en champs (`TimeDayUi::getEntryTotal()`), ou sous
 * horaires la phrase qui la dit (`getWeekSum()`).
 */
function planningTimeDayWeekTotal(AjaxTemplate $t, \planning\TimeDayUi $uiTimeDay, \structure\Structure $eStructure, string $week, \user\User $eUserTime, array $total, array $entry, ?string $dateSelected): void {

	if(\planning\TimeDayUi::hasEntry($eStructure)) {
		$html = $uiTimeDay->getEntryTotal($eStructure, $week, $eUserTime, $total, $entry, $dateSelected === NULL);
	} else {
		$html = $uiTimeDay->getWeekSum($eStructure, $eUserTime, $total, \planning\TimeDayUi::url($eStructure, $week, eUser: $eUserTime, weekly: TRUE));
	}

	$t->qs('#planning-time-week-total')->outerHtml($html);

}

/**
 * Sous le jour de la fiche, ce que sa journée change : sous horaires, sa carte (`TimeDayUi::getWorkingDay()`), et le
 * temps par intervention, dont la barre porte le repère de la journée.
 */
function planningTimeDayWorkingDay(AjaxTemplate $t, \planning\TimeDayUi $uiTimeDay, \structure\Structure $eStructure, string $week, string $date, \user\User $eUserTime, array $timeDay, bool $canWorking): void {

	if(\planning\TimeDayUi::hasEntry($eStructure) === FALSE) {
		$t->qs('#planning-time-working-day')->outerHtml($uiTimeDay->getWorkingDay($eStructure, $week, $date, $eUserTime, $timeDay['day'], $timeDay['eWorkingTime'], $canWorking));
	}

	$t->qs('#planning-time-tasks-day')->outerHtml($uiTimeDay->getTasksDay($eStructure, $timeDay['day'], $timeDay['actions'], $timeDay['cTaskSpent'], $timeDay['timeSpent']));

}

/**
 * La journée saisie sur une ligne de l'équipe (R158) : cette ligne, où la saisie se rouvre pour les minutes
 * (`PlanningDuration.choose()`), la pastille du jour, qui dit la journée de l'équipe, la somme de la semaine
 * et le jour de l'équipe. La somme de chacun n'est à l'écran que semaine choisie, où rien ne se saisit.
 * Les autres lignes ne bougent pas, l'échelle étant fixe (`TimeWeekLib::SCALE`).
 *
 * Une journée vidée sur la ligne d'un absent qui n'a pas de temps ce jour-là le fait sortir du tableau
 * (`TimeWeekLib::getPresents()`, R-Retours-1210, FH) : la carte du jour se redessine alors entière (`teamLeft`),
 * puisque le pli ouvert d'office dépend du nombre de lignes.
 */
new JsonView('doWorkingTimeTeam', function($data, AjaxTemplate $t) {

	$uiTimeDay = new \planning\TimeDayUi();

	if($data->teamLeft) {
		$t->qs('#planning-time-team-day')->outerHtml($uiTimeDay->getTeamDay($data->eStructure, $data->week, $data->date, $data->cUserPresent, $data->timeWeek['days'][$data->date]));
	} else {
		$t->qs('#planning-time-team-lane-'.$data->eUserTeam['id'])->outerHtml($uiTimeDay->getTeamLane($data->eStructure, $data->week, $data->date, $data->eUserTeam));
	}

	$t->qs('#planning-time-day-'.$data->date)->outerHtml($uiTimeDay->getDay($data->eStructure, $data->week, $data->date, TRUE, new \user\User(), $data->timeWeek['days'][$data->date]));
	$t->qs('#planning-time-week-total')->outerHtml($uiTimeDay->getWeekSum($data->eStructure, new \user\User(), $data->timeWeek['total'], \planning\TimeDayUi::url($data->eStructure, $data->week, team: TRUE, weekly: TRUE)));

	// La carte redessinée porte déjà le jour de l'équipe.
	if($data->teamLeft === FALSE) {
		$t->qs('#planning-time-team-total')->outerHtml($uiTimeDay->getTeamTotal($data->eStructure, $data->timeWeek['days'][$data->date]));
	}

});

/**
 * La journée saisie sur un jour du récapitulatif du mois (`\hr\RecapLib::ORIGIN`) : la semaine du jour, avec sa
 * durée, et les chiffres du mois.
 */
new JsonView('doWorkingTimeRecap', function($data, AjaxTemplate $t) {

	$uiRecap = new \hr\RecapUi();

	$t->qs('#'.\hr\RecapUi::getWeekId($data->week))->outerHtml($uiRecap->getWeek($data->eStructure, $data->eUserTime, $data->week, $data->weeks[$data->week]));
	$t->qs('#hr-recap-figures')->outerHtml($uiRecap->getFigures($data->eUserTime));

});

/**
 * La coche, la décoche et le temps d'une ligne : la ligne seule, à la place que l'écran lui donnait, ou la
 * liste entière, quand une ligne commentée arrive dans une table sans colonne des commentaires
 * (`TimeDayLib::getChange()`), dans la variante de la table affichée. Une ligne de zone reste dans sa zone
 * (`TimeDayLib::getChangeZone()`), sauf quand elle n'a plus de ligne à rendre : sa zone lâche alors les autres
 * lignes qu'elle retenait. La liste se redessine avec la rangée des outils quand la page le décide
 * (`listChanged`, `planningTimeDayRespond()`), comme pour « Nouvelle intervention ». Une zone qui change n'y touche
 * que ses nœuds (`zoneChanges`), après la liste : une ligne que la zone retenait y porte le même identifiant,
 * et doit être rendue dans la liste avant d'être retirée de la zone. L'écran reçoit ensuite ses blocs
 * dépliés (`data-expanded`), que le changement a pu replier, et les lignes qu'il garde dans une zone où la
 * base ne les compte plus (`data-retained`). Puis le jour de la fiche, dont le dépassement suit le temps des
 * interventions, et ce qui est sous le jour (`planningTimeDayWorkingDay()`), que le temps comme la coche
 * changent, avec la somme de la semaine qui accompagne toujours le jour.
 */
new JsonView('doLine', function($data, AjaxTemplate $t) {

	$uiTimeDay = new \planning\TimeDayUi();

	if($data->listChanged) {

		$canAlso = ($data->canCreate and $data->canTime);

		$t->qs('#planning-time-tools')->outerHtml($uiTimeDay->getTools($data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->eMember, $canAlso));
		$t->qs('#planning-time-list')->outerHtml($uiTimeDay->getList($data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->canTime, $canAlso));

	} else if($data->change === 'table') {
		$t->qs('#planning-time-table')->outerHtml($uiTimeDay->getTable($data->week, $data->date, $data->timeDay, $data->canTime));
	} else if($data->change === 'row') {
		$t->qs('#planning-time-task-'.$data->eTask['id'])->outerHtml($uiTimeDay->getRow($data->week, $data->date, $data->eTaskLine, $data->canTime, $data->commented));
	}

	if($data->zoneChanges !== []) {

		$expanded = $data->expanded;

		foreach($data->zoneChanges as $zoneChange) {
			planningTimeDayZoneChange($t, $uiTimeDay, $data->week, $data->date, $zoneChange, $data->canTime);
			$expanded[$zoneChange['zone']] = $zoneChange['expanded'];
		}

		$t->qs('#planning-time-zones')->setAttribute('data-expanded', $uiTimeDay->getExpanded($expanded));

	}

	if($data->retainedChanged) {
		$t->qs('#planning-time-zones')->setAttribute('data-retained', $uiTimeDay->getRetained($data->retained));
	}

	$t->qs('#planning-time-day-'.$data->date)->outerHtml($uiTimeDay->getDay($data->eStructure, $data->week, $data->date, TRUE, $data->eUserTime, $data->timeDay['days'][$data->date], $data->timeDay['eWorkingTime'], $data->entry));

	planningTimeDayWeekTotal($t, $uiTimeDay, $data->eStructure, $data->week, $data->eUserTime, $data->timeDay['total'], $data->entry, $data->dateSelected);
	planningTimeDayWorkingDay($t, $uiTimeDay, $data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->canWorking);

});

/**
 * Les étapes d'une zone qui change (`TimeDayLib::getZoneSteps()`), chacune sur son nœud. Une ligne se
 * retire dans la table de sa zone : la liste, redessinée avant, porte déjà la ligne que la zone retenait,
 * sous le même identifiant.
 */
function planningTimeDayZoneChange(AjaxTemplate $t, \planning\TimeDayUi $uiTimeDay, string $week, string $date, array $zoneChange, bool $canTime): void {

	$name = $zoneChange['zone'];

	foreach($zoneChange['steps'] as $step) {

		match($step['type']) {
			'zone' => $t->qs('#planning-time-zones')->insertAdjacentHtml(($name === \planning\TaskLateLib::LATE) ? 'afterbegin' : 'beforeend', $uiTimeDay->getZone($week, $date, $step['zone'], $canTime)),
			'removeZone' => $t->qs('#planning-time-'.$name)->remove(),
			'counter' => $t->qs('#planning-time-'.$name.' > .util-fold-head .util-counter')->innerHtml((string)$step['count']),
			'board' => $t->qs('#planning-time-'.$name.'-board')->outerHtml($uiTimeDay->getZoneBoard($week, $date, $step['zone'], $canTime)),
			'variant' => planningTimeDayZoneVariant($t, $uiTimeDay, $name),
			'title' => $t->qs('#'.$step['anchor'])->insertAdjacentHtml($step['position'], $uiTimeDay->getActionTitle($step['eAction'], 'planning-time-'.$name)),
			'removeTitle' => $t->qs('#planning-time-'.$name.'-action-'.$step['action'])->remove(),
			'insert' => $t->qs('#'.$step['after'])->insertAdjacentHtml('afterend', $uiTimeDay->getRow($week, $date, $step['eTask'], $canTime, $zoneChange['commented'])),
			'row' => $t->qs('#planning-time-task-'.$step['eTask']['id'])->outerHtml($uiTimeDay->getRow($week, $date, $step['eTask'], $canTime, $zoneChange['commented'])),
			'remove' => $t->qs('#planning-time-'.$name.'-board > #planning-time-task-'.$step['task'])->remove(),
			'more' => planningTimeDayZoneMore($t, $uiTimeDay, $step),
		};

	}

}

/**
 * La colonne des commentaires ouverte dans la table d'une zone, sans la redessiner : une cellule vide dans
 * chaque ligne, l'intitulé dans la tête, puis la variante de la table. Le nombre d'instructions ne dépend pas
 * du nombre de lignes.
 */
function planningTimeDayZoneVariant(AjaxTemplate $t, \planning\TimeDayUi $uiTimeDay, string $name): void {

	$board = '#planning-time-'.$name.'-board';

	$t->qsa($board.' > .planning-time-row > .planning-time-time')->insertAdjacentHtml('afterend', $uiTimeDay->getCommentsCell(''));
	$t->qs($board.' > .util-table-head')->insertAdjacentHtml('beforeend', $uiTimeDay->getCommentsHead());
	$t->qs($board)
		->removeClass('planning-time-table-wide')
		->addClass('planning-time-table-comments')
		->setAttribute('data-columns', 3);

}

/**
 * « Voir les N autres » d'un bloc, retiré, posé ou remplacé comme l'étape le dit (`TimeDayLib::getZoneSteps()`).
 */
function planningTimeDayZoneMore(AjaxTemplate $t, \planning\TimeDayUi $uiTimeDay, array $step): void {

	$more = $uiTimeDay->getZoneMore($step['zone'], $step['action'], $step['rest']);

	if($step['rest'] === 0) {
		$t->qs('#planning-time-'.$step['zone'].'-more-'.$step['action'])->remove();
	} else if($step['after'] !== NULL) {
		$t->qs('#'.$step['after'])->insertAdjacentHtml('afterend', $more);
	} else {
		$t->qs('#planning-time-'.$step['zone'].'-more-'.$step['action'])->outerHtml($more);
	}

}

/**
 * « Nouvelle intervention » ouvert depuis la fiche (`timeDay:doCreate`, retour 293) : la rangée des outils, où
 * « + Fait » paraît quand la ligne neuve est la première de la semaine (`TimeDayUi::getTools()`), la
 * liste, où les lignes neuves entrent, le jour où elles sont faites, dont la colonne prend le temps saisi et
 * qui n'est pas toujours le jour ouvert, la somme de la semaine et les chiffres du jour ouvert, puis le
 * panneau se ferme. À faire, aucun temps ne s'écrit et aucun jour ne change.
 */
new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-planning-task-create');

	if($data->success !== NULL) {
		$t->js()->success('planning', 'Task::createdOutside', $data->success);
		return;
	}

	$uiTimeDay = new \planning\TimeDayUi();

	$canAlso = ($data->canCreate and $data->canTime);

	$t->qs('#planning-time-tools')->outerHtml($uiTimeDay->getTools($data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->eMember, $canAlso));
	$t->qs('#planning-time-list')->outerHtml($uiTimeDay->getList($data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->canTime, $canAlso));

	if($data->dateDone !== NULL) {
		$t->qs('#planning-time-day-'.$data->dateDone)->outerHtml($uiTimeDay->getDay($data->eStructure, $data->week, $data->dateDone, $data->dateDone === $data->date, $data->eUserTime, $data->timeDay['days'][$data->dateDone], $data->timeDay['cWorkingTime'][$data->dateDone], $data->entry));
	}

	planningTimeDayWeekTotal($t, $uiTimeDay, $data->eStructure, $data->week, $data->eUserTime, $data->timeDay['total'], $data->entry, $data->dateSelected);
	planningTimeDayWorkingDay($t, $uiTimeDay, $data->eStructure, $data->week, $data->date, $data->eUserTime, $data->timeDay, $data->canWorking);

});

/**
 * « Non planifié » à sa première ouverture : sa table, et ce que les gestes réémettront désormais.
 */
new JsonView('unplanned', function($data, AjaxTemplate $t) {

	$t->qs('#planning-time-unplanned-board')->outerHtml(new \planning\TimeDayUi()->getZoneBoard($data->week, $data->date, $data->zones[\planning\TaskLateLib::UNPLANNED], $data->canTime));
	$t->qs('#planning-time-zones')->setAttribute('data-unplanned', '1');

});

/**
 * « Voir les N autres » : les lignes d'une action au-delà du plafond à la place du lien, après la colonne des
 * commentaires quand l'une d'elles l'ouvre (`timeDay:more`). L'écran retient l'action parmi les blocs dépliés,
 * que les gestes réémettent.
 */
new JsonView('more', function($data, AjaxTemplate $t) {

	$uiTimeDay = new \planning\TimeDayUi();

	if($data->more === 'variant') {
		planningTimeDayZoneVariant($t, $uiTimeDay, $data->zone);
	}

	// Une ligne par instruction : le client n'insère qu'un fragment à une seule racine (`renderAdjacentHTML()`).
	$more = $t->qs('#planning-time-'.$data->zone.'-more-'.$data->action);

	foreach($data->cTaskRest as $eTask) {
		$more->insertAdjacentHtml('beforebegin', $uiTimeDay->getRow($data->week, $data->date, $eTask, $data->canTime, $data->commented));
	}

	$more->remove();

	$t->qs('#planning-time-zones')->setAttribute('data-expanded', $uiTimeDay->getExpanded($data->expanded));

});
?>
