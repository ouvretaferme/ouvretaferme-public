<?php
namespace planning;

/**
 * L'écran des interventions d'une semaine (L5a) : ce que ses cartes affichent, ce que ses filtres
 * gardent, et ce que ses trois gestes écrivent, la coche (`doDone`), la décoche (`doTodo`) et la bulle
 * du temps passé (`doTime`). Chaque cas passe par la vraie route, avec ses validations, dans la
 * transaction du décor (`HarvestFixtureTest`), et ne regarde que les interventions qu'il pose : la
 * structure du décor porte les siennes dans la même semaine.
 *
 * Les états particuliers d'abord : la carte sans série, la carte que seul un temps nomme, la semaine
 * à venir où la bulle n'a pas de jour, le prévu posé une seule fois, le temps des autres gardé.
 *
 * Le jour du décor est le jeudi de la semaine 39.
 *
 * Lancement : dev/test package=planning file=TaskWeek
 */
class TaskWeekTest extends HarvestFixtureTest {

	/**
	 * Hors de toute série, la carte prend le nom de l'espèce ; sans série ni espèce ni note, quand quelqu'un y
	 * a passé du temps, elle n'a pas de titre, jamais le nom d'une personne à la place du titre.
	 */
	public function testTitleWithoutSeries(): void {

		if($this->requireAction(ACTION_TRAITEMENT) === FALSE) {
			return;
		}

		$eTaskPlant = $this->createTaskOutOfSeries();

		$eTaskBare = $this->createTaskBare();
		$this->createTimesheet($eTaskBare, 0.5, \Clock::date());

		[$document] = $this->renderTasks();

		$this->assertEquals($this->getPlant()['name'], $this->getCardTitle($document, $eTaskPlant), 'la carte hors série ne prend pas le nom de l\'espèce');

		if($this->requireTime() === FALSE) {
			return;
		}

		$this->assertTrue($document->querySelector('#planning-task-'.$eTaskBare['id'].' .planning-task-name') === NULL, 'la carte sans série, espèce ni note, avec du temps, a encore un titre');

	}

	/**
	 * Décaler une intervention puis la ramener lui rend son jour prévu (R-Retours-0810, AA, point 1) : le jour
	 * suit la semaine, et une intervention sans jour reste sans jour.
	 */
	public function testIncrementKeepsDay(): void {

		$week = \Clock::week();
		$wednesday = week_date_day($week, 3);

		$eTaskDay = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $wednesday);
		$eTaskWeek = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$read = fn(\series\Task $eTask) => \series\Task::model()
			->select(['plannedWeek', 'plannedDate'])
			->whereId($eTask)
			->get()
			->extracts(['plannedWeek', 'plannedDate']);

		\series\TaskLib::incrementPlannedCollection(new \Collection([$eTaskDay['id'] => $eTaskDay, $eTaskWeek['id'] => $eTaskWeek]), 2);

		$weekLater = toWeek($week.' +2 WEEK');

		$this->assertEquals(['plannedWeek' => $weekLater, 'plannedDate' => week_date_day($weekLater, 3)], $read($eTaskDay), 'décalée de deux semaines, l\'intervention ne garde pas son mercredi');
		$this->assertEquals(['plannedWeek' => $weekLater, 'plannedDate' => NULL], $read($eTaskWeek), 'décalée, l\'intervention sans jour en prend un');

		$eTaskDay['plannedWeek'] = $weekLater;
		$eTaskWeek['plannedWeek'] = $weekLater;

		\series\TaskLib::incrementPlannedCollection(new \Collection([$eTaskDay['id'] => $eTaskDay, $eTaskWeek['id'] => $eTaskWeek]), -2);

		$this->assertEquals(['plannedWeek' => $week, 'plannedDate' => $wednesday], $read($eTaskDay), 'ramenée, l\'intervention n\'a pas retrouvé son jour prévu');
		$this->assertEquals(['plannedWeek' => $week, 'plannedDate' => NULL], $read($eTaskWeek), 'ramenée, l\'intervention sans jour en prend un');

	}

	/**
	 * Sans rien qui la nomme, pas même un temps, la carte garde un titre, celui de l'action.
	 */
	public function testTitleNeverEmpty(): void {

		if($this->requireAction(ACTION_TRAITEMENT) === FALSE) {
			return;
		}

		$eTask = $this->createTaskBare();

		[$document] = $this->renderTasks();

		$this->assertTrue($this->getCardTitle($document, $eTask) !== '', 'une carte sans série, espèce, note ni temps reste sans titre');

	}

	/**
	 * La coche dans une semaine à venir : l'intervention passe en fait sans temps, aucun jour n'étant
	 * encore passé, et la bulle refuse d'en poser un.
	 */
	public function testFutureWeekWritesNoTime(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$week = toWeek(\Clock::week().' + 14 DAYS');

		$eTask = $this->createTaskExpected(1.5, $week);

		[$data, $instructions, $action] = $this->callTask('doDone', $eTask, ['week' => $week]);

		$this->assertTrue($action instanceof \ViewAction, 'la coche d\'une semaine à venir est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(0, $this->getTimes($eTask)->count(), 'la coche d\'une semaine à venir écrit un temps');
		$this->assertFalse(str_contains(json_encode($instructions), 'planning-task-bubble-'.$eTask['id']), 'la coche d\'une semaine à venir ouvre une bulle dont tout temps serait refusé');

		[, , $action] = $this->callTask('doTime', $eTask, ['week' => $week, 'time' => '1']);

		$this->assertTrue(in_array('Timesheet::date.future', $this->getFails($action), TRUE), 'un temps dans une semaine à venir n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(0, $this->getTimes($eTask)->count(), 'le temps refusé est écrit');

	}

	/**
	 * Sans aucun temps sur l'intervention, la coche pose le prévu, entier, à la personne qui coche, au
	 * jour du fait, et la bulle s'ouvre dessus.
	 */
	public function testDoneWritesExpected(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskExpected(1.5, \Clock::week());

		[$data, $instructions, $action] = $this->callTask('doDone', $eTask);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTimesheet = $this->getTimes($eTask);

		$this->assertEquals(1, $cTimesheet->count(), 'le prévu n\'est pas écrit en une ligne');
		$this->assertEquals($this->getMember()['id'], $cTimesheet->first()['user']['id'], 'le prévu ne va pas à la personne qui coche');
		$this->assertEquals(1.5, (float)$cTimesheet->first()['time'], 'le prévu n\'est pas écrit entier');
		$this->assertEquals(\Clock::date(), $cTimesheet->first()['date'], 'le prévu d\'une intervention cochée aujourd\'hui n\'est pas daté d\'aujourd\'hui');
		$this->assertEquals(1.5, $data->time, 'la bulle ne s\'ouvre pas sur le prévu');
		$this->assertTrue(str_contains(json_encode($instructions), 'planning-task-bubble-'.$eTask['id']), 'la coche n\'ouvre pas la bulle');
		$this->assertEquals($this->getMember()['id'], $data->eUserTime['id'], 'la bulle ne nomme pas la personne qui coche');
		$this->assertEquals('1.5', $this->getBubbleTime($instructions, $eTask), 'la saisie de la bulle ne s\'ouvre pas sur le prévu');

	}

	/**
	 * Cochée, décochée, puis recochée le lendemain, l'intervention ne reçoit le prévu qu'une fois
	 * (V-Planning, 2026-09-30, sur la règle de l'architecte). Le cas est une non-régression et ne
	 * distingue pas la règle de l'intervention entière de celle du jour : la recoche retombe sur le jour
	 * du fait, que `updateDoneCollection()` pose au dernier jour de temps, celui de la première coche.
	 * Ce qui les distingue est éprouvé par `testTimeDayRiseThenFall`, où du temps d'une autre semaine
	 * empêche le prévu.
	 */
	public function testDoneWritesExpectedOnce(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskExpected(1.5, \Clock::week());

		$this->callTask('doDone', $eTask);
		$this->callTask('doTodo', $eTask);

		$this->setClock(week_date_day(\Clock::week(), 5).' 10:00:00');

		$this->callTask('doDone', $eTask);

		$this->assertEquals(1.5, $this->sumTimes($this->getTimes($eTask, $this->getMember())), 'recochée un autre jour, l\'intervention reçoit le prévu une seconde fois');

	}

	/**
	 * La bulle porte le temps de la personne au jour du fait, et le serveur n'écrit que l'écart avec ce
	 * qu'elle y a déjà (écart g de L5a, tranché par V-Planning) : une hausse puis une baisse changent ce
	 * jour seul. Son temps d'une autre semaine et celui d'un autre membre le même jour ne bougent pas.
	 */
	public function testTimeDayRiseThenFall(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre, dont le temps doit rester') === FALSE) {
			return;
		}

		$today = \Clock::date();
		$weekBefore = $this->getDateBefore(14);

		$eTask = $this->createTaskExpected(1.0, \Clock::week());

		$this->createTimesheet($eTask, 0.5, $weekBefore);
		$this->createTimesheetOther($eTask, $eUserOther, 0.75, $today);

		[$data] = $this->callTask('doDone', $eTask);

		$this->assertEquals(0.0, $data->time, 'la bulle ne s\'ouvre pas à zéro, la personne ayant déjà du temps sur l\'intervention');

		[$dataTime, , $action] = $this->callTask('doTime', $eTask, ['time' => '2.5']);

		if($this->assertTrue($action instanceof \ViewAction, 'la hausse est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('card', $dataTime->change, 'un temps rend plus que la carte : la bulle ouverte, ancrée à son ouverture, serait remplacée');

		$this->assertEquals([$weekBefore => 0.5, $today => 2.5], $this->getTimesByDate($eTask, $this->getMember()), 'la hausse n\'écrit pas le total tapé au jour du fait, ou touche une autre semaine');

		[, , $action] = $this->callTask('doTime', $eTask, ['time' => '0.5']);

		if($this->assertTrue($action instanceof \ViewAction, 'la baisse est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$weekBefore => 0.5, $today => 0.5], $this->getTimesByDate($eTask, $this->getMember()), 'la baisse n\'écrit pas le total tapé au jour du fait, ou touche une autre semaine');
		$this->assertEquals([$today => 0.75], $this->getTimesByDate($eTask, $eUserOther), 'le temps d\'un autre membre le même jour change');
		$this->assertEquals(1.75, $this->getTaskTime($eTask), 'la durée de l\'intervention ne suit pas les temps écrits');

	}

	/**
	 * Le jour du fait est celui du planning actuel, que le tampon affiche (V-Planning, 2026-09-30, voie
	 * a) : sans jour prévu, `updateDoneCollection()` le pose au dernier jour de temps. Avec du temps la
	 * veille et une autre semaine, la coche d'aujourd'hui ne pose pas le prévu, le jour du fait est la
	 * veille et la bulle s'ouvre sur son temps. La flèche remet la veille à zéro en retirant ses lignes,
	 * sans toucher à l'autre semaine.
	 */
	public function testTimeArrowKeepsOtherDays(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$yesterday = $this->getDateBefore(1);
		$weekBefore = $this->getDateBefore(14);

		$eTask = $this->createTaskExpected(1.0, \Clock::week());

		$this->createTimesheet($eTask, 0.5, $yesterday);
		$this->createTimesheet($eTask, 0.25, $weekBefore);

		[$data] = $this->callTask('doDone', $eTask);

		$this->assertEquals($yesterday, \series\TaskLib::getDoneTimeDate(\series\TaskLib::getById($eTask['id'])), 'sans jour prévu, le jour du fait n\'est pas le dernier jour de temps');
		$this->assertEquals([$weekBefore => 0.25, $yesterday => 0.5], $this->getTimesByDate($eTask, $this->getMember()), 'la coche pose le prévu alors que la personne a déjà du temps');
		$this->assertEquals(0.5, $data->time, 'la bulle ne s\'ouvre pas sur le temps du jour du fait');

		[, , $action] = $this->callTask('doTime', $eTask, ['time' => '0']);

		if($this->assertTrue($action instanceof \ViewAction, 'la flèche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$weekBefore => 0.25], $this->getTimesByDate($eTask, $this->getMember()), 'la flèche laisse une ligne au jour du fait, ou touche l\'autre semaine');
		$this->assertEquals(0.25, $this->getTaskTime($eTask), 'la durée de l\'intervention ne suit pas la flèche');

	}

	/**
	 * Un total illisible est refusé par le schéma, sans rien écrire.
	 */
	public function testTimeRefused(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskExpected(1.0, \Clock::week());

		$this->callTask('doDone', $eTask);

		[, , $action] = $this->callTask('doTime', $eTask, ['time' => '-1']);

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'un temps négatif est accepté');
		$this->assertEquals(1.0, $this->sumTimes($this->getTimes($eTask)), 'un temps refusé change le temps écrit');

	}

	/**
	 * Une saisie sans durée est refusée par `time.missing` et ne retire pas le temps du jour du fait
	 * (R-Retours-0410, point 20b) : la bulle construit le temps sur la liste `day`.
	 */
	public function testTimeMissing(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskExpected(1.0, \Clock::week());

		$this->callTask('doDone', $eTask);

		[, , $action] = $this->callTask('doTime', $eTask, []);

		$this->assertEquals(['Timesheet::time.missing'], $this->getFails($action), 'une saisie sans durée n\'est pas refusée par time.missing');
		$this->assertEquals(1.0, $this->sumTimes($this->getTimes($eTask)), 'une saisie sans durée change le temps écrit');

	}

	/**
	 * Une intervention à faire ne reçoit pas de temps par la bulle, ni une faite une seconde coche.
	 */
	public function testGesturesFollowStatus(): void {

		$eTask = $this->createTaskExpected(1.0, \Clock::week());

		[, , $action] = $this->callTask('doTodo', $eTask);
		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une intervention à faire se remet à faire');

		if($this->eStructure->hasTime()) {
			[, , $action] = $this->callTask('doTime', $eTask, ['time' => '1']);
			$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une intervention à faire reçoit du temps par la bulle');
		}

		$this->callTask('doDone', $eTask);

		[, , $action] = $this->callTask('doDone', $eTask);
		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une intervention faite se coche une seconde fois');

	}

	/**
	 * Masquer ce qui est fait retire les faites au rendu, mais garde celle qu'on vient de cocher jusqu'au
	 * rechargement ; les comptes ne dépendent pas du masque.
	 */
	public function testHideKeepsTicked(): void {

		$eTaskTodo = $this->createTaskExpected(0.0, \Clock::week());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document, $dataShown] = $this->renderTasks();

		$this->setHideDone(TRUE);

		[$documentHidden, $dataHidden] = $this->renderTasks();

		$this->assertTrue($document->getElementById('planning-task-'.$eTaskDone['id']) !== NULL, 'une intervention faite n\'est pas rendue');
		$this->assertTrue($documentHidden->getElementById('planning-task-'.$eTaskDone['id']) === NULL, 'le masque garde une intervention faite');
		$this->assertTrue($documentHidden->getElementById('planning-task-'.$eTaskTodo['id']) !== NULL, 'le masque retire une intervention à faire');
		$this->assertEquals($dataShown->todo, $dataHidden->todo, 'les comptes des blocs changent avec le masque');

		[$data] = $this->callTask('doDone', $eTaskTodo);

		$this->assertEquals('card', $data->change, 'l\'intervention cochée sous le masque ne reste pas à l\'écran');

		[$dataTime, , $action] = $this->callTask('doTime', $eTaskTodo, ['time' => '0.25']);

		if($this->assertTrue($action instanceof \ViewAction, 'un temps sous le masque est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('card', $dataTime->change, 'un temps sous le masque rend plus que la carte : la bulle ouverte serait remplacée');

		[$documentHidden] = $this->renderTasks();

		$this->assertTrue($documentHidden->getElementById('planning-task-'.$eTaskTodo['id']) === NULL, 'au rechargement, le masque garde l\'intervention cochée');

	}

	/**
	 * « Masquer ce qui est fait » est une préférence du membre (retour 108) : l'interrupteur l'écrit, et la
	 * page suivante la relit sans rien dans l'adresse, la Semaine comme le Jour et le Temps de travail.
	 * Le membre n'écrit que la sienne (`canSelf`).
	 */
	public function testHideIsPreference(): void {

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderTasks();

		$switch = $document->querySelector('#planning-week .planning-hide .field-switch');

		if($this->assertTrue($switch !== NULL, 'la Semaine ne porte pas l\'interrupteur du masque') === FALSE) {
			return;
		}

		$this->assertEquals('/structure/member:doUpdatePlanningHideDone', $switch->getAttribute('data-ajax'), 'l\'interrupteur n\'écrit pas la préférence');
		$this->assertEquals('1', $switch->getAttribute('post-view-planning-hide-done'), 'l\'interrupteur éteint ne propose pas d\'allumer le masque');

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		$action = $this->executePage('structure/member:doUpdatePlanningHideDone', ['id' => $eMember['id'], 'viewPlanningHideDone' => '1'], 'POST');
		$this->assertTrue($action instanceof \ReloadAction, 'le membre n\'écrit pas sa préférence : '.implode(', ', $this->getFails($action)));

		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

		[$documentHidden] = $this->renderTasks();
		[$documentDay] = $this->renderTaskDay();
		[, $dataTime] = $this->renderTimeDay();

		$this->assertTrue($documentHidden->getElementById('planning-task-'.$eTaskDone['id']) === NULL, 'la Semaine rechargée ne relit pas le masque');
		$this->assertTrue($documentDay->getElementById('planning-task-'.$eTaskDone['id'].'-'.\Clock::date()) === NULL, 'le Jour ne relit pas le masque de la Semaine');
		$this->assertTrue($dataTime->hide, 'le Temps de travail ne relit pas le masque de la Semaine');

		$eMemberOther = \structure\MemberLib::getByStructure($this->eStructure, index: NULL)->find(fn($eMemberStructure) => $eMemberStructure['user']->is($this->getMember()) === FALSE)->first();

		if($this->assertTrue($eMemberOther !== NULL, 'la structure '.static::STRUCTURE.' n\'a qu\'un membre, le refus d\'écrire pour un autre ne se monte pas') === FALSE) {
			return;
		}

		$hideOther = \structure\Member::model()->select('viewPlanningHideDone')->whereId($eMemberOther['id'])->getValue('viewPlanningHideDone');

		$action = $this->executePage('structure/member:doUpdatePlanningHideDone', ['id' => $eMemberOther['id'], 'viewPlanningHideDone' => $hideOther ? '0' : '1'], 'POST');

		$this->assertTrue(($action instanceof \ReloadAction) === FALSE, 'un membre écrit la préférence d\'un autre');
		$this->assertEquals($hideOther, \structure\Member::model()->select('viewPlanningHideDone')->whereId($eMemberOther['id'])->getValue('viewPlanningHideDone'), 'la préférence d\'un autre membre a changé en base');

	}

	/**
	 * Une carte à faire porte le rond qui la coche ; cochée, le rond qui la remet à faire ; remise à faire,
	 * de nouveau celui qui la coche (lot BI).
	 */
	public function testMarkThenChecked(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$empty = '#planning-task-'.$eTask['id'].' [data-planning-task-done="'.$eTask['id'].'"]';
		$checked = '#planning-task-'.$eTask['id'].' [data-planning-task-todo="'.$eTask['id'].'"]';

		[$document] = $this->renderTasks();
		$this->assertTrue($document->querySelector($empty) !== NULL, 'la carte à faire ne porte pas le rond qui la coche');

		$this->callTask('doDone', $eTask);

		[$document] = $this->renderTasks();
		$this->assertTrue($document->querySelector($checked) !== NULL, 'la carte cochée ne porte pas le rond qui la remet à faire');

		$this->callTask('doTodo', $eTask);

		[$document] = $this->renderTasks();
		$this->assertTrue($document->querySelector($empty) !== NULL, 'la carte remise à faire ne porte pas de nouveau le rond qui la coche');

	}

	/**
	 * Sans droit d'écrire le planning (Bruno, au planning limité), la carte à faire ne porte pas de rond qui
	 * coche, et la coche postée quand même est refusée sans rien écrire ni rien redessiner : la carte
	 * reste celle d'un rechargement.
	 */
	public function testLimited(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$card = 'planning-task-'.$eTask['id'];

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderTasks();

		if($this->assertTrue($document->getElementById($card) !== NULL, 'la carte à faire de Bruno manque')) {
			$this->assertTrue($document->querySelector('#'.$card.' [data-planning-task-done]') === NULL, 'le rond de Bruno coche encore');
		}

		[, $instructions, $action] = $this->callTask('doDone', $eTask);

		$this->assertFalse($action instanceof \ViewAction, 'la coche de Bruno, au planning limité, est acceptée');
		$this->assertEquals([], $instructions, 'la coche refusée de Bruno redessine l\'écran');
		$this->assertFalse(\series\TaskLib::getById($eTask['id'])->isDone(), 'la coche refusée de Bruno est écrite');

		// La case de sélection porte un identifiant `field-<aléa>` tiré à chaque rendu.
		$getCard = fn(\Dom\HTMLDocument $document) => preg_replace('/field-[0-9a-f]+/', 'field-', $document->saveHtml($document->getElementById($card)));

		$this->assertEquals($getCard($document), $getCard($this->renderTasks()[0]), 'la carte de Bruno après la coche refusée diffère du rechargement');

	}

	/**
	 * Le glisser d'une carte (retour 176) découvre la semaine suivante et la suppression, que le gabarit
	 * `#planning-week-swipe` porte. La semaine se calcule, le passage d'une année compris, et la suppression
	 * demande sa confirmation au socle. Aucun dessous n'est dans la page hors d'un geste. Sans droit d'écrire le
	 * planning (Bruno), il n'y a pas de gabarit, donc pas de geste.
	 */
	public function testSwipe(): void {

		foreach([\Clock::week() => 'Semaine 40', '2025-W52' => 'Semaine 1'] as $week => $label) {

			$document = $this->renderTasks(week: $week)[0];
			$swipe = $document->getElementById('planning-week-swipe');

			if($this->assertTrue($swipe !== NULL, 'la semaine '.$week.' ne porte pas le gabarit du glisser') === FALSE) {
				continue;
			}

			// Un dessous n'existe que le temps d'un geste : le gabarit reste un `<template>`, que le navigateur ne
			// peint pas.
			$this->assertEquals('TEMPLATE', $swipe->tagName, 'le gabarit du glisser de la semaine '.$week.' se peint dans la page');

			// `querySelector()` ne descend pas dans le contenu d'un `<template>` : il se relit en document.
			$content = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$swipe->innerHTML.'</body></html>', LIBXML_NOERROR);

			$next = $content->querySelector('.planning-task-swipe-next');
			$delete = $content->querySelector('.planning-task-swipe-delete');

			$this->assertEquals($label, trim($next?->textContent ?? ''), 'le glisser à droite de la semaine '.$week.' ne nomme pas la semaine suivante');
			$this->assertEquals('1', $next?->getAttribute('data-increment'), 'le glisser à droite ne reporte pas d\'une semaine');
			$this->assertEquals('Supprimer cette intervention ?', $delete?->getAttribute('data-confirm'), 'le glisser à gauche ne demande pas de confirmation');

		}

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		$this->assertTrue($this->renderTasks()[0]->getElementById('planning-week-swipe') === NULL, 'Bruno, au planning limité, a le glisser');

	}

	/**
	 * Récolter depuis une carte (retour 203) : seule une récolte qui a une série porte sa culture
	 * (`data-cultivation`), que le geste ouvre. Le gabarit du glisser porte « Récolter » pour qui récolte,
	 * sur une semaine où l'on peut récolter, passée comme courante, jamais sur une semaine à venir.
	 */
	public function testHarvestSwipe(): void {

		$eCultivation = $this->createCultivation();

		$cases = [
			'une récolte d\'une série' => [$this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), (string)$eCultivation['id']],
			'une plantation' => [$this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, \Clock::week()), NULL],
			'une récolte sans série' => [$this->createTaskOutOfSeries(ACTION_RECOLTE), NULL],
		];

		$document = $this->renderTasks()[0];

		foreach($cases as $case => [$eTask, $expected]) {

			$card = $document->querySelector('#planning-week-board #planning-task-'.$eTask['id']);

			if($this->assertTrue($card !== NULL, $case.' n\'a pas de carte dans les blocs') === FALSE) {
				continue;
			}

			$this->assertEquals($expected, $card->getAttribute('data-cultivation'), $case.' ne porte pas la culture que la récolte attend');

		}

		$weekPrevious = toWeek(week_date_day(\Clock::week(), 1).' - 7 days');
		$weekNext = toWeek(week_date_day(\Clock::week(), 1).' + 7 days');

		foreach([\Clock::week() => TRUE, $weekPrevious => TRUE, $weekNext => FALSE] as $week => $expected) {

			$swipe = $this->renderTasks(week: $week)[0]->getElementById('planning-week-swipe');

			if($this->assertTrue($swipe !== NULL, 'la semaine '.$week.' ne porte pas le gabarit du glisser') === FALSE) {
				continue;
			}

			$content = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$swipe->innerHTML.'</body></html>', LIBXML_NOERROR);
			$harvest = $content->querySelector('.planning-task-swipe-harvest');

			if($expected) {
				$this->assertTrue($harvest !== NULL, 'le glisser de la semaine '.$week.' ne découvre pas « Récolter »');
			} else {
				$this->assertTrue($harvest === NULL, 'la semaine à venir '.$week.' découvre « Récolter »');
			}

		}

	}

	/**
	 * La saisie ouverte depuis une carte (`origin=semaine`) de la semaine en cours : la série posée, aujourd'hui
	 * proposé et pour maximum, même pour une carte faite lundi (V29). Panneau comme feuille postent sur
	 * `taskWeek:doHarvest`, et réémettent ce qui dessine l'écran que l'on regarde. Une intervention qui n'est
	 * pas une récolte de cette culture n'ouvre rien.
	 */
	public function testHarvestOpen(): void {

		// Un jeudi, pour qu'aujourd'hui ne soit pas le lundi de la carte.
		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$eCultivation = $this->createCultivation();
		$monday = week_date_day(\Clock::week(), 1);
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), $monday);

		// La feuille ne s'ouvre que sur une culture de l'écran des récoltes, où une récolte la fait entrer.
		$this->createHarvest($eTask, 2.0, $monday);

		$context = ['origin' => 'semaine', 'task' => $eTask['id'], 'cultivation' => $eCultivation['id'], 'unplanned' => '1', 'folded' => 'late'];

		[$data, , $action] = $this->call('create', $context, 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau ne s\'ouvre pas depuis une carte : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(\Clock::date(), $data->date, 'le panneau d\'une carte faite lundi ne propose pas aujourd\'hui');
		$this->assertEquals(\Clock::date(), $data->dateMax, 'le panneau ne s\'arrête pas au jour de « Nouvelle récolte »');

		$panel = new HarvestEntryUi()->create($data->eStructure, $data->week, $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, $data->open, $data->taskWeek);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->dialogOpen.$panel->body.$panel->dialogClose.'</body></html>', LIBXML_NOERROR);

		$this->assertEquals('/planning/taskWeek:doHarvest', $document->querySelector('form')?->getAttribute('data-ajax-form'), 'le panneau ouvert depuis une carte ne poste pas sur la Semaine');
		$this->assertEquals('1', $document->querySelector('input[name="unplanned"]')?->getAttribute('value'), 'le panneau ne réémet pas « Non planifié » ouvert');
		$this->assertEquals('late', $document->querySelector('input[name="folded"]')?->getAttribute('value'), 'le panneau ne réémet pas les zones repliées');
		$this->assertEquals((string)$eTask['id'], $document->querySelector('input[name="task"]')?->getAttribute('value'), 'le panneau ne poste pas la carte d\'où part la saisie');
		$this->assertEquals(\Clock::date(), $document->querySelector('input[name="date"]')?->getAttribute('value'), 'le jour du panneau n\'est pas aujourd\'hui');
		$this->assertEquals(\Clock::date(), $document->querySelector('input[name="date"]')?->getAttribute('max'), 'le jour du panneau va au-delà d\'aujourd\'hui');

		[, $instructions, $action] = $this->call('open', $context + ['sheet' => 1], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la feuille ne s\'ouvre pas depuis une carte : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$html = '';

		foreach($instructions as $instruction) {
			foreach($instruction[0] === 'js' ? [] : $instruction[2] as $call) {
				if($call[0] === 'insertAdjacentHtml') {
					$html .= $call[1][1];
				}
			}
		}

		$sheet = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

		if($this->assertTrue($sheet !== NULL, 'la feuille ouverte depuis une carte ne se pose pas') === FALSE) {
			return;
		}

		$this->assertEquals('/planning/taskWeek:doHarvest', $sheet->querySelector('form')?->getAttribute('data-ajax-form'), 'la feuille ouverte depuis une carte ne poste pas sur la Semaine');
		$this->assertEquals(\Clock::date(), $sheet->querySelector('input[name="date"]')?->getAttribute('value'), 'la feuille d\'une carte faite lundi ne saisit pas aujourd\'hui');
		$this->assertEquals('late', $sheet->querySelector('input[name="folded"]')?->getAttribute('value'), 'la feuille ne réémet pas les zones repliées');
		$this->assertEquals((string)$eTask['id'], $sheet->querySelector('input[name="task"]')?->getAttribute('value'), 'la feuille ne poste pas la carte d\'où part la saisie');

		[, , $action] = $this->call('create', ['task' => $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, \Clock::week())['id']] + $context, 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'une plantation ouvre la saisie d\'une récolte');

		[, , $action] = $this->call('create', ['cultivation' => $this->createCultivation($this->createSeries('Série '.uniqid()))['id']] + $context, 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'la récolte d\'une autre culture ouvre la saisie de celle-ci');

	}

	/**
	 * Le jour que propose une carte (`HarvestWeekLib::getTaskDate()`) : aujourd'hui dans la semaine en cours,
	 * même pour une carte planifiée un autre jour (V29). Dans une semaine passée, le jour où la carte est faite,
	 * à défaut celui où elle est planifiée, à défaut le dernier jour de la semaine (retour 203).
	 */
	public function testHarvestOpenDate(): void {

		// Un jeudi, pour qu'aujourd'hui ne soit pas le lundi de la carte de la semaine en cours.
		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');

		$eCultivation = $this->createCultivation();

		foreach([
			'une carte à faire, planifiée lundi de la semaine en cours' => [\series\Task::TODO, \Clock::week(), week_date_day(\Clock::week(), 1), \Clock::date()],
			'une carte faite mardi de la semaine passée' => [\series\Task::DONE, $weekBefore, week_date_day($weekBefore, 2), week_date_day($weekBefore, 2)],
			'une carte à faire, planifiée mercredi de la semaine passée' => [\series\Task::TODO, $weekBefore, week_date_day($weekBefore, 3), week_date_day($weekBefore, 3)],
			'une carte à faire de la semaine passée, sans jour' => [\series\Task::TODO, $weekBefore, NULL, week_date_day($weekBefore, 7)],
		] as $case => [$status, $week, $date, $expected]) {

			$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, $status, $week, $date);

			[$data, , $action] = $this->call('create', ['week' => $week, 'origin' => 'semaine', 'task' => $eTask['id'], 'cultivation' => $eCultivation['id'], 'unplanned' => '', 'folded' => ''], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : le panneau ne s\'ouvre pas, '.implode(', ', $this->getFails($action)))) {
				$this->assertEquals($expected, $data->date, $case.' : le panneau ne propose pas le bon jour');
			}

		}

	}

	/**
	 * `taskWeek:doHarvest` écrit comme l'écran des récoltes, avec ses refus, et répond par la carte écrite et
	 * le compte de son bloc, jamais par l'écran entier. Une intervention que la saisie crée entre dans
	 * son bloc.
	 */
	public function testHarvestWrite(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$data, $instructions, $action] = $this->callTask('doHarvest', $eTask, ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '3', 'panel' => 1]);

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte depuis une carte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTaskWritten = \series\TaskLib::getById($eTask['id']);

		$this->assertEquals(3.0, $eTaskWritten['harvest'], 'la récolte ne s\'écrit pas sur l\'intervention de la carte');
		$this->assertTrue($eTaskWritten->isDone(), 'la quantité saisie depuis la carte ne termine pas l\'intervention à faire (retour 214)');
		$this->assertEquals('card', $data->change, 'la récolte ne remplace pas la carte sur place');

		$selectors = array_map(fn($instruction) => $instruction[1], array_filter($instructions, fn($instruction) => $instruction[0] !== 'js'));

		$this->assertTrue(in_array('#planning-task-'.$eTask['id'], $selectors, TRUE), 'la réponse ne remplace pas la carte');
		$this->assertTrue(in_array('#planning-week-counter-'.$eTask['action']['id'], $selectors, TRUE), 'la réponse ne remplace pas le compte du bloc');
		$this->assertFalse(in_array('#planning-week', $selectors, TRUE), 'la réponse rend l\'écran entier');
		$this->assertTrue(str_contains(json_encode($instructions), 'panel-planning-harvest-create'), 'la réponse ne ferme pas le panneau');

		foreach([
			'un jour hors de la semaine' => [['date' => week_date_day(toWeek(week_date_day(\Clock::week(), 1).' - 7 days'), 1), 'harvestMore' => '1'], 'Timesheet::date.week'],
			'une saisie vide' => [['harvestMore' => '0'], 'Task::harvestMore.empty'],
		] as $case => [$input, $fail]) {

			[, , $action] = $this->callTask('doHarvest', $eTask, $input + ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'panel' => 1]);
			$this->assertTrue(in_array($fail, $this->getFails($action), TRUE), $case.' n\'est pas refusé par '.$fail.' : '.implode(', ', $this->getFails($action)));

		}

		// Une carte d'une variété, saisie sans variété : la semaine n'a pas de récolte de ce couple.
		$eCultivationNew = $this->createCultivation($this->createSeries('Série '.uniqid()));
		$eTaskVariety = $this->createTask($eCultivationNew, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->ventilate($eTaskVariety, ['variety' => $this->createVariety($eCultivationNew['plant'])]);

		[$data, , $action] = $this->callTask('doHarvest', $eTaskVariety, ['cultivation' => $eCultivationNew['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '2', 'panel' => 1]);

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte qui crée son intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue(in_array($data->change, ['block', 'board'], TRUE), 'l\'intervention que la récolte crée n\'entre pas dans son bloc');

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eCultivationForeign = \series\Cultivation::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

		if($this->assertTrue($eCultivationForeign->notEmpty(), 'la base porte une culture d\'une autre structure, pour éprouver le refus') === FALSE) {
			return;
		}

		[, , $action] = $this->callTask('doHarvest', $eTask, ['cultivation' => $eCultivationForeign['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '1', 'panel' => 1]);
		$this->assertFalse($action instanceof \ViewAction, 'une culture d\'une autre structure se récolte depuis la Semaine');

	}

	/**
	 * Point 1 du relecteur, tranché par V-Planning : deux récoltes de la même culture dans la semaine, du même
	 * couple, ne se distinguent que par la carte d'où part la saisie. La carte postée (`task`) passe avant la
	 * règle de la semaine quand elle est une récolte de cette culture, dans la semaine du jour saisi, du
	 * couple saisi ; sinon la règle vaut. L'écran des récoltes désigne de même l'intervention d'une case
	 * (R-Retours-0810, T, piste A). Une carte qui n'est pas une récolte de la culture est refusée, jamais
	 * ignorée.
	 */
	public function testHarvestWriteCard(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série '.uniqid()));
		$monday = week_date_day(\Clock::week(), 1);

		// Le cas du relecteur sur la 6861 : 578487099, faite et récoltée, puis 578506387, à faire, que la
		// règle de la semaine préfère. La saisie partie de la première s'écrivait sur la seconde.
		$eTaskDone = $this->createTaskHarvest($eCultivation, \Clock::week(), $monday);
		$this->createHarvest($eTaskDone, 12.0, $monday);
		$eTaskTodo = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$harvest = fn(\series\Task $eTask) => \series\TaskLib::getById($eTask['id'])['harvest'];
		$harvestTodo = $harvest($eTaskTodo);

		$input = ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '1', 'panel' => 1];

		[, , $action] = $this->callTask('doHarvest', $eTaskDone, $input);

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie depuis une carte faite est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(13.0, $harvest($eTaskDone), 'la saisie partie d\'une carte ne s\'écrit pas sur elle');
		$this->assertEquals($harvestTodo, $harvest($eTaskTodo), 'la saisie partie d\'une carte s\'écrit sur une autre récolte de la semaine');

		// L'écran des récoltes poste aussi l'intervention qu'une case désigne, et l'écriture la préfère de même.
		$action = $this->executePage('planning/harvestWeek:doHarvest', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'task' => $eTaskDone['id'],
		], 'POST');

		$this->assertTrue($action instanceof \ViewAction, 'la saisie de l\'écran des récoltes est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(14.0, $harvest($eTaskDone), 'l\'écran des récoltes n\'écrit pas sur l\'intervention postée');
		$this->assertEquals($harvestTodo, $harvest($eTaskTodo), 'l\'écran des récoltes écrit sur une autre récolte de la semaine que celle postée');

		// Une carte d'un autre couple, ou d'une semaine passée, laisse la saisie à la règle de la semaine.
		$eTaskVariety = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->ventilate($eTaskVariety, ['variety' => $this->createVariety($eCultivation['plant'])]);

		$eTaskLate = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(week_date_day(\Clock::week(), 1).' - 7 days'));

		foreach([
			'une carte d\'une variété, saisie sans variété' => [$eTaskVariety, 1.0],
			'une carte en retard' => [$eTaskLate, 2.0],
		] as $case => [$eTaskCard, $expected]) {

			$harvestCard = $harvest($eTaskCard);

			[, , $action] = $this->callTask('doHarvest', $eTaskCard, $input);

			if($this->assertTrue($action instanceof \ViewAction, $case.' est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$this->assertEquals($expected, $harvest($eTaskTodo), $case.' n\'écrit pas sur la récolte de la semaine et du couple saisis');
			$this->assertEquals($harvestCard, $harvest($eTaskCard), $case.' écrit sur la carte');

		}

		foreach([
			'une plantation de la culture' => $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, \Clock::week()),
			'une récolte d\'une autre culture' => $this->createTask($this->createCultivation($this->createSeries('Série '.uniqid())), ACTION_RECOLTE, \series\Task::TODO, \Clock::week()),
		] as $case => $eTaskCard) {

			[, , $action] = $this->callTask('doHarvest', $eTaskCard, $input);
			$this->assertTrue($action instanceof \NotExpectedAction, $case.' n\'est pas refusée');

		}

		$this->assertEquals(2.0, $harvest($eTaskTodo), 'une carte refusée a écrit quand même');

	}

	/**
	 * Repasse du point 1 (R2 et R6) : la saisie ouverte depuis une carte annonce l'unité de l'intervention
	 * qu'elle écrira. Le panneau comme la feuille partent de la carte quand l'écriture la préfère, et de la
	 * règle de la semaine sinon.
	 */
	public function testHarvestOpenUnit(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série '.uniqid()));
		$monday = week_date_day(\Clock::week(), 1);

		$eTaskBunch = $this->createTaskHarvest($eCultivation, \Clock::week(), $monday);
		$this->ventilate($eTaskBunch, ['harvestUnit' => \series\Task::BUNCH]);
		$this->createHarvest($eTaskBunch, 12.0, $monday);

		$eTaskTodo = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		if($this->assertDecorPresent($eCultivation['mainUnit'] !== \series\Task::BUNCH, 'la culture du décor ne se récolte pas en bottes') === FALSE) {
			return;
		}

		foreach([
			'la carte en bottes' => [$eTaskBunch, \series\Task::BUNCH],
			'la carte à faire' => [$eTaskTodo, $eCultivation['mainUnit']],
		] as $case => [$eTaskCard, $expected]) {

			$context = ['origin' => 'semaine', 'task' => $eTaskCard['id'], 'cultivation' => $eCultivation['id'], 'unplanned' => '', 'folded' => ''];

			[$data, , $action] = $this->call('create', $context, 'GET');

			if($this->assertTrue($action instanceof \ViewAction, 'le panneau ne s\'ouvre pas depuis '.$case.' : '.implode(', ', $this->getFails($action)))) {
				$this->assertEquals($expected, $data->unit, 'le panneau ouvert depuis '.$case.' n\'annonce pas l\'unité qu\'il écrira');
			}

			[$data, , $action] = $this->call('open', $context + ['sheet' => 1], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, 'la feuille ne s\'ouvre pas depuis '.$case.' : '.implode(', ', $this->getFails($action)))) {
				$this->assertEquals($expected, $data->eCultivation['harvestUnitWeek'], 'la feuille ouverte depuis '.$case.' n\'annonce pas l\'unité qu\'elle écrira');
			}

		}

	}

	/**
	 * Le geste (`PlanningTaskSwipe`, retour 203, v2) : au doigt, une récolte balayée à droite découvre
	 * « Récolter » jusqu'aux deux tiers, puis le report si elle se reporte ; une récolte faite n'a que
	 * « Récolter ». À la souris, le report reste à droite et « Récolter » se tire vers le haut. Le geste
	 * vertical du doigt reste au défilement, et le doigt ne choisit pas d'axe avant 100 ms (retour 259). Le
	 * lâcher agit au-delà de 40 % de la carte dans son axe.
	 */
	public function testHarvestGesture(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTaskSwipe {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTaskSwipe') === FALSE) {
			return;
		}

		// [doigt ou souris, récolte, se reporte, axe, déplacement en part de la carte] => [dessous, geste au lâcher]
		$sides = [
			'au doigt, une récolte à faire à 0,5' => [['touch', TRUE, TRUE, 'x', 0.5], ['harvest', 'harvest']],
			'au doigt, une récolte à faire à 0,3' => [['touch', TRUE, TRUE, 'x', 0.3], ['harvest', NULL]],
			'au doigt, une récolte à faire à 0,7' => [['touch', TRUE, TRUE, 'x', 0.7], ['next', 'next']],
			'au doigt, une récolte faite à 0,9' => [['touch', TRUE, FALSE, 'x', 0.9], ['harvest', 'harvest']],
			'au doigt, une récolte à gauche' => [['touch', TRUE, TRUE, 'x', -0.5], ['delete', 'delete']],
			'au doigt, une autre action à 0,5' => [['touch', FALSE, TRUE, 'x', 0.5], ['next', 'next']],
			'au doigt, une autre action faite à 0,5' => [['touch', FALSE, FALSE, 'x', 0.5], [NULL, NULL]],
			'à la souris, une récolte à faire à 0,5' => [['mouse', TRUE, TRUE, 'x', 0.5], ['next', 'next']],
			'à la souris, une récolte faite à 0,5' => [['mouse', TRUE, FALSE, 'x', 0.5], [NULL, NULL]],
			'à la souris, une récolte tirée vers le haut à 0,45' => [['mouse', TRUE, FALSE, 'y', -0.45], ['harvest', 'harvest']],
			'à la souris, une récolte tirée vers le haut à 0,3' => [['mouse', TRUE, TRUE, 'y', -0.3], ['harvest', NULL]],
			'à la souris, une récolte tirée vers le bas' => [['mouse', TRUE, TRUE, 'y', 0.3], [NULL, NULL]],
		];

		// Vers le haut, le lâcher attend que le libellé du dessous soit découvert en entier, rembourrage compris
		// (seuil vertical, arbitrage V-Planning du 2026-10-09) : une carte d'une ligne de 31 px ne le découvre
		// pas à 40 %, une carte de 60 px, si.
		// [hauteur de la carte, hauteur du libellé, levée en px] => geste au lâcher
		$reaches = [
			'une carte de 31 px levée de 13 px (0,42)' => [[31, 25, -13], NULL],
			'une carte de 31 px levée de 24 px' => [[31, 25, -24], NULL],
			'une carte de 31 px levée de 25 px' => [[31, 25, -25], 'harvest'],
			'une carte de 60 px levée de 24 px (0,4)' => [[60, 20, -24], 'harvest'],
			'une carte de 60 px levée de 23 px' => [[60, 20, -23], NULL],
		];

		// Vers le haut, la carte s'arrête au seuil, quoi que fasse la souris, et le lâcher à la butée vaut le geste
		// (retour 215). De côté, rien ne la retient.
		// [axe, hauteur de la carte, hauteur du libellé, déplacement en px] => [déplacement retenu, geste au lâcher]
		$bounds = [
			'une carte de 31 px tirée de 200 px vers le haut' => [['y', 31, 25, -200], [-25, 'harvest']],
			'une carte de 60 px tirée de 200 px vers le haut' => [['y', 60, 20, -200], [-24, 'harvest']],
			'une carte de 60 px tirée de 10 px vers le haut' => [['y', 60, 20, -10], [-10, NULL]],
			'une carte de 60 px tirée vers le bas' => [['y', 60, 20, 30], [30, NULL]],
			'une carte tirée de 200 px de côté' => [['x', 60, 20, 200], [200, 'next']],
		];

		// [doigt ou souris, récolte, déplacement x, déplacement y, millisecondes depuis l'appui] => axe
		$axes = [
			'au doigt, vers le haut sur une récolte' => [['touch', TRUE, 2, -20, 120], 'none'],
			'au doigt, de côté sur une récolte' => [['touch', TRUE, 20, 2, 120], 'x'],
			'au doigt, de côté à 50 ms' => [['touch', FALSE, 20, 2, 50], NULL],
			'au doigt, vers le haut à 50 ms' => [['touch', FALSE, 2, -20, 50], NULL],
			'au doigt, de côté à 120 ms' => [['touch', FALSE, 20, 2, 120], 'x'],
			'à la souris, de côté à 0 ms' => [['mouse', FALSE, 20, 2, 0], 'x'],
			'à la souris, vers le haut sur une récolte' => [['mouse', TRUE, 2, -20, 0], 'y'],
			'à la souris, vers le bas sur une récolte' => [['mouse', TRUE, 2, 20, 0], 'none'],
			'à la souris, vers le haut sur une autre action' => [['mouse', FALSE, 2, -20, 0], 'none'],
			'à la souris, sous le seuil' => [['mouse', TRUE, 3, -3, 0], NULL],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({PlanningPointer: {THRESHOLD: 8}});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningTaskSwipe = PlanningTaskSwipe;", context);

			const sides = '.json_encode(array_values(array_map(fn($case) => $case[0], $sides))).'.map(([pointer, harvest, postpone, axis, part]) => {
				const swipe = {axis: axis, mouse: pointer === "mouse", harvest: harvest, postpone: postpone, width: 300, height: 60, reach: 20};
				const offset = part * (axis === "x" ? swipe.width : swipe.height);
				return [context.PlanningTaskSwipe.getSide(swipe, offset), context.PlanningTaskSwipe.getGesture(swipe, offset)];
			});

			const axes = '.json_encode(array_values(array_map(fn($case) => $case[0], $axes))).'.map(([pointer, harvest, dx, dy, elapsed]) => context.PlanningTaskSwipe.getAxis({mouse: pointer === "mouse", harvest: harvest}, dx, dy, elapsed));

			const reaches = '.json_encode(array_values(array_map(fn($case) => $case[0], $reaches))).'.map(([height, reach, offset]) => context.PlanningTaskSwipe.getGesture({axis: "y", mouse: true, harvest: true, postpone: true, width: 300, height: height, reach: reach}, offset));

			const bounds = '.json_encode(array_values(array_map(fn($case) => $case[0], $bounds))).'.map(([axis, height, reach, offset]) => {
				const swipe = {axis: axis, mouse: true, harvest: true, postpone: true, width: 300, height: height, reach: reach};
				const bounded = context.PlanningTaskSwipe.bound(swipe, offset);
				return [bounded, context.PlanningTaskSwipe.getGesture(swipe, bounded)];
			});

			process.stdout.write(JSON.stringify([sides, axes, reaches, bounds]));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		foreach(array_keys($sides) as $position => $case) {
			$this->assertEquals($sides[$case][1], $results[0][$position], $case.', le dessous ou le geste au lâcher');
		}

		foreach(array_keys($axes) as $position => $case) {
			$this->assertEquals($axes[$case][1], $results[1][$position], $case.', l\'axe du geste');
		}

		foreach(array_keys($reaches) as $position => $case) {
			$this->assertEquals($reaches[$case][1], $results[2][$position], 'à la souris, '.$case.', le geste au lâcher');
		}

		foreach(array_keys($bounds) as $position => $case) {
			$this->assertEquals($bounds[$case][1], $results[3][$position], 'à la souris, '.$case.', le déplacement retenu et le geste au lâcher');
		}

	}

	/**
	 * La catégorie se cherche dans l'omnibox (V58) : une ancienne adresse qui porte encore `category`, sous
	 * une autre catégorie que celle de l'intervention, montre toute la semaine.
	 */
	public function testCategoryIgnored(): void {

		$eTask = $this->createTaskExpected(0.0, \Clock::week());
		$eCategoryOther = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_ADMINISTRATION);

		if($this->assertDecorPresent($eCategoryOther->notEmpty(), 'la structure '.static::STRUCTURE.' a une catégorie d\'administration') === FALSE) {
			return;
		}

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/semaine/'.\Clock::week(), ['category' => $eCategoryOther['id']], 'GET');
		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertTrue(
			$data->ccTask->offsetExists($eTask['action']['id']) and $data->ccTask[$eTask['action']['id']]->offsetExists($eTask['id']),
			'une adresse qui porte une autre catégorie retire l\'intervention de son bloc'
		);

	}

	/**
	 * Cocher ne déplace aucune carte : l'ordre des blocs et des cartes ne dépend pas de l'état.
	 */
	public function testOrderIndependentOfStatus(): void {

		$eTaskFirst = $this->createTaskExpected(0.0, \Clock::week());
		$eTaskSecond = $this->createTaskExpected(0.0, \Clock::week());

		[, $dataBefore] = $this->renderTasks();

		$this->callTask('doDone', $eTaskFirst);

		[, $dataAfter] = $this->renderTasks();

		$this->assertEquals($this->getOrder($dataBefore->ccTask), $this->getOrder($dataAfter->ccTask), 'cocher change l\'ordre des cartes');

		$action = $eTaskFirst['action']['id'];
		$ids = $dataAfter->ccTask[$action]->getIds();

		$this->assertTrue(array_search($eTaskFirst['id'], $ids) < array_search($eTaskSecond['id'], $ids), 'la carte cochée passe après sa voisine');

	}

	/**
	 * Ce que la recherche lit sur une carte (R-Recherche, `TaskCardUi::getSearch()`) : l'action, l'espèce, et
	 * la personne affectée sans temps comme celle qui a du temps sans être affectée ; un identifiant affecté
	 * qui n'est pas un membre n'y paraît pas, celle qui est les deux n'y paraît qu'une fois. Sans série ni
	 * espèce, la première ligne de la note, que la carte prend pour titre, rejoint l'action sans sa case, et
	 * l'espèce reste vide.
	 */
	public function testSearch(): void {

		if($this->requireAction(ACTION_TRAITEMENT) === FALSE or $this->requireTime() === FALSE) {
			return;
		}

		$eUserPlanning = $this->getMemberPlanning();

		$eTaskPlant = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		// Affectées par le geste du produit, qui accepte un identifiant hors des membres.
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskPlant['id'])]), $eUserPlanning, 'add');
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskPlant['id'])]), new \user\User(['id' => 999999999]), 'add');
		$this->createTimesheet($eTaskPlant, 0.5, \Clock::date());

		$eTaskBare = $this->createTaskBare();
		$eTaskBare->build(['description'], ['description' => "o Suivi des rongeurs et taupes\nCampagnols sur la planche 4"]);
		\series\TaskLib::update($eTaskBare, ['description']);
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskBare['id'])]), $this->getMember(), 'add');
		$this->createTimesheet($eTaskBare, 0.5, \Clock::date());

		[$document] = $this->renderTasks();

		$this->assertEquals([
			'category' => [$this->getCategoryCulture()['name']],
			'action' => [$this->getAction(ACTION_PLANTATION)['name']],
			'user' => [$this->getMember()->getName(), $eUserPlanning->getName()],
			'plant' => [$this->getPlant()['name']],
			'place' => [],
		], $this->getCardSearch($document, $eTaskPlant), 'la carte d\'une culture ne porte pas son action, son espèce, la personne affectée et celle qui a du temps, ou porte un affecté hors des membres');

		$this->assertEquals([
			'category' => [$this->getCategoryCulture()['name']],
			'action' => [$this->getAction(ACTION_TRAITEMENT)['name'], 'Suivi des rongeurs et taupes'],
			'user' => [$this->getMember()->getName()],
			'plant' => [],
			'place' => [],
		], $this->getCardSearch($document, $eTaskBare), 'la carte titrée par sa note ne porte pas l\'action et la première ligne de la note sans sa case, ou porte deux fois la personne affectée qui a du temps');

		$this->assertTrue($document->querySelector('#planning-week #planning-search') !== NULL, 'la rangée ne porte pas l\'omnibox de la recherche');
		$this->assertTrue($document->querySelector('#planning-week .planning-search-empty.hide') !== NULL, 'l\'écran vide de la recherche n\'est pas posé masqué');

	}

	/**
	 * L'écran lit ses semaines sur les conditions des deux lectures du planning actuel
	 * (`\series\TaskLib::getForWeekTodo()` et `getForWeekDone()`) : pour chaque semaine lue d'un coup, les
	 * mêmes interventions, chacune avec le même temps et la même récolte. Le décor
	 * pose les bords : l'intervention à faire, celle faite avec du temps la semaine d'avant, la récolte
	 * datée d'une autre semaine que celle où l'intervention est faite, l'intervention à faire déjà
	 * commencée, et des semaines sans rien du décor. Des semaines qui ne se suivent pas se lisent ensuite
	 * comme lues avec leurs voisines.
	 */
	public function testWeeksReadLikePlanning(): void {

		$weeksSeason = TaskYearLib::getWeeks($this->getSeason());
		$weeks = array_slice($weeksSeason, array_search(\Clock::week(), $weeksSeason, TRUE) - 7, 8);

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weeks[1]);

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weeks[3], week_date_day($weeks[3], 2));
		$this->createTimesheet($eTaskDone, 1.5, week_date_day($weeks[2], 4));

		$eTaskHarvest = $this->createTaskHarvest($this->getCultivation(), $weeks[5], week_date_day($weeks[5], 1));
		$this->createHarvest($eTaskHarvest, 3.0, week_date_day($weeks[4], 3));

		$eTaskStarted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weeks[6]);
		$this->createTimesheet($eTaskStarted, 0.5, week_date_day($weeks[6], 1));

		$cTaskByWeek = TaskWeekLib::getByWeeks($this->eStructure, $weeks);

		$this->assertEquals($weeks, array_keys($cTaskByWeek), 'les semaines ne sont pas rendues dans l\'ordre demandé');

		foreach($weeks as $week) {

			$expected = [];

			foreach([\series\TaskLib::getForWeekTodo($this->eStructure, $week), \series\TaskLib::getForWeekDone($this->eStructure, $week)] as $ccTask) {

				foreach($ccTask as $cTaskAction) {

					foreach($cTaskAction as $eTask) {
						$expected[$eTask['id']] = [$eTask['times'], $eTask['harvestPeriod']];
					}

				}

			}

			$read = [];

			foreach($cTaskByWeek[$week] as $eTask) {
				$read[$eTask['id']] = [$eTask['times'], $eTask['harvestPeriod']];
			}

			ksort($expected);
			ksort($read);

			$this->assertEquals($expected, $read, 'la semaine '.$week.' ne lit pas ce que lisent les deux lectures du planning actuel');

		}

		foreach([[$eTaskTodo, $weeks[1]], [$eTaskDone, $weeks[2]], [$eTaskDone, $weeks[3]], [$eTaskHarvest, $weeks[4]], [$eTaskHarvest, $weeks[5]], [$eTaskStarted, $weeks[6]]] as [$eTask, $week]) {
			$this->assertTrue($cTaskByWeek[$week]->offsetExists($eTask['id']), 'l\'intervention '.$eTask['id'].' manque à la semaine '.$week);
		}

		$this->assertEquals([], $cTaskByWeek[$weeks[3]][$eTaskDone['id']]['times'], 'le temps de la semaine d\'avant est porté par la semaine où l\'intervention est faite');
		$this->assertEquals(1.5, array_sum($cTaskByWeek[$weeks[2]][$eTaskDone['id']]['times']), 'le temps n\'est pas porté par sa semaine');
		$this->assertEquals(3.0, $cTaskByWeek[$weeks[4]][$eTaskHarvest['id']]['harvestPeriod'], 'la récolte n\'est pas portée par sa semaine');
		$this->assertFalse($cTaskByWeek[$weeks[0]]->offsetExists($eTaskDone['id']), 'l\'intervention faite paraît dans une semaine où elle n\'a rien');

		// Une semaine sur deux : le temps (semaine 2) et la récolte (semaine 4) tombent dans des semaines lues
		// chacune par sa propre plage, entre des semaines que la lecture saute.
		$weeksApart = [$weeks[0], $weeks[2], $weeks[4], $weeks[6]];
		$cTaskByWeekApart = TaskWeekLib::getByWeeks($this->eStructure, $weeksApart);

		$read = fn(\series\Task $eTask) => [$eTask['times'], $eTask['harvestPeriod']];

		foreach($weeksApart as $week) {
			$this->assertEquals($cTaskByWeek[$week]->makeArray($read, TRUE), $cTaskByWeekApart[$week]->makeArray($read, TRUE), 'la semaine '.$week.', lue entre des semaines sautées, ne rend pas ce qu\'elle rend lue avec ses voisines');
		}

		$this->assertTrue($cTaskByWeekApart[$weeks[2]]->offsetExists($eTaskDone['id']), 'le temps d\'une semaine lue entre deux semaines sautées est perdu');
		$this->assertTrue($cTaskByWeekApart[$weeks[4]]->offsetExists($eTaskHarvest['id']), 'la récolte d\'une semaine lue entre deux semaines sautées est perdue');

	}

	/**
	 * Bulle ouverte, un clic hors d'elle la referme, et sur les blocs il ne fait rien d'autre : aucune carte ne
	 * se coche ni ne s'ouvre, c'est le clic suivant qui agit (retour 194, comme le tiroir des récoltes). Hors
	 * des blocs, un onglet, la recherche ou le menu agissent aussi. Seul le rond coché de la carte que la bulle
	 * suit la décoche du premier clic (contre-recette de BI). `outside()` tourne sur un arbre simulé
	 * comme le rend `TaskWeekUi::get()` : l'écran porte les onglets, la recherche, les zones « En retard »
	 * et les blocs, où la bulle suit la carte cochée ; le menu est hors de l'écran.
	 */
	public function testBubbleOutsideClick(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTask {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTask') === FALSE) {
			return;
		}

		// [bulle ouverte, nœud de l'appui, nœud du clic, `detail` du clic, touche Ctrl ou Cmd, sélection en cours, refus affiché dans la bulle] => [bulle fermée, clic arrêté]
		$cases = [
			'une autre carte' => [[TRUE, 'cardOther', 'cardOther', 1, NULL, FALSE, FALSE], [TRUE, TRUE]],
			'la coche d\'une autre carte' => [[TRUE, 'tick', 'tick', 1, NULL, FALSE, FALSE], [TRUE, TRUE]],
			'une carte en retard' => [[TRUE, 'late', 'late', 1, NULL, FALSE, FALSE], [TRUE, TRUE]],
			'la carte de la bulle' => [[TRUE, 'card', 'card', 1, NULL, FALSE, FALSE], [TRUE, TRUE]],
			'le rond coché de la carte de la bulle' => [[TRUE, 'mark', 'mark', 1, NULL, FALSE, FALSE], [FALSE, FALSE]],
			'un onglet' => [[TRUE, 'tab', 'tab', 1, NULL, FALSE, FALSE], [TRUE, FALSE]],
			'la recherche' => [[TRUE, 'search', 'search', 1, NULL, FALSE, FALSE], [TRUE, FALSE]],
			'le menu' => [[TRUE, 'menu', 'menu', 1, NULL, FALSE, FALSE], [TRUE, FALSE]],
			'dans la bulle' => [[TRUE, 'field', 'field', 1, NULL, FALSE, FALSE], [FALSE, FALSE]],
			'dans la bulle, sans appui retenu' => [[TRUE, NULL, 'field', 1, NULL, FALSE, FALSE], [FALSE, FALSE]],
			'une sélection commencée dans la bulle' => [[TRUE, 'field', 'block', 1, NULL, FALSE, FALSE], [FALSE, FALSE]],
			'au clavier, ou au bout d\'un glisser au doigt' => [[TRUE, NULL, 'cardOther', 0, NULL, FALSE, FALSE], [FALSE, FALSE]],
			'un Ctrl+clic sur le nom d\'une autre carte' => [[TRUE, 'nameOther', 'nameOther', 1, 'ctrl', FALSE, FALSE], [FALSE, FALSE]],
			'un Cmd+clic sur le nom d\'une autre carte' => [[TRUE, 'nameOther', 'nameOther', 1, 'meta', FALSE, FALSE], [FALSE, FALSE]],
			'un Ctrl+clic sur une autre carte' => [[TRUE, 'cardOther', 'cardOther', 1, 'ctrl', FALSE, FALSE], [TRUE, TRUE]],
			'une autre carte, sélection en cours' => [[TRUE, 'cardOther', 'cardOther', 1, NULL, TRUE, FALSE], [TRUE, FALSE]],
			'une autre carte, refus affiché dans la bulle' => [[TRUE, 'cardOther', 'cardOther', 1, NULL, FALSE, TRUE], [FALSE, FALSE]],
			'sans bulle' => [[FALSE, 'cardOther', 'cardOther', 1, NULL, FALSE, FALSE], [FALSE, FALSE]],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningTask = PlanningTask;", context);
			const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';

			const node = (parent, selectors = []) => ({
				parent: parent,
				selectors: selectors,
				contains(other) {
					for(let current = other; current !== null; current = current.parent) {
						if(current === this) {
							return true;
						}
					}
					return false;
				},
				closest(selector) {
					for(let current = this; current !== null; current = current.parent) {
						if(current.selectors.includes(selector)) {
							return current;
						}
					}
					return null;
				}
			});

			const grid = "#planning-week-before, #planning-week-board";

			const body = node(null);
			const week = node(body, ["#planning-week"]);
			const before = node(week, [grid]);
			const board = node(week, [grid]);
			const block = node(board);
			const card = node(block);
			const bubble = node(block, [".planning-task-bubble"]);
			bubble.dataset = {task: "1"};
			const cardOther = node(block);

			const nodes = {
				menu: node(body),
				tab: node(week),
				search: node(week),
				late: node(before),
				block: block,
				card: card,
				mark: node(card, [\'[data-planning-task-todo="1"]\']),
				field: node(bubble),
				cardOther: cardOther,
				tick: node(cardOther, [\'[data-planning-task-todo="2"]\']),
				nameOther: node(cardOther, ["a[href]"])
			};

			const results = cases.map(([open, pressed, target, detail, modified, selecting, refused]) => {

				const closed = [];
				const e = {target: nodes[target], detail: detail, ctrlKey: modified === "ctrl", metaKey: modified === "meta", stopped: false, prevented: false, stopPropagation() { this.stopped = true; }, preventDefault() { this.prevented = true; }};

				context.qs = selector => (open && selector === "#planning-week .planning-task-bubble") ? bubble : null;
				context.PlanningTaskSelection = {selecting: selecting};
				bubble.qs = selector => (refused && selector === ".form-error-message") ? {} : null;
				context.closed = closed;

				vm.runInContext("PlanningTask.close = bubble => closed.push(bubble)", context);

				context.PlanningTask.pressed = (pressed === null) ? null : nodes[pressed];
				context.PlanningTask.outside(e);

				return [
					closed.length === 1 && closed[0] === bubble,
					e.stopped && e.prevented,
					e.stopped || e.prevented,
					context.PlanningTask.pressed === null
				];

			});

			process.stdout.write(JSON.stringify(results));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$closed, $stopped, $touched, $forgotten] = $results[$position];

			$this->assertEquals($cases[$case][1][0], $closed, $case.', la bulle '.($cases[$case][1][0] ? 'reste ouverte' : 'se ferme'));
			$this->assertEquals($cases[$case][1][1], $stopped, $case.', le clic '.($cases[$case][1][1] ? 'traverse' : 'est arrêté'));
			$this->assertEquals($stopped, $touched, $case.', le clic n\'est arrêté qu\'à moitié');
			$this->assertTrue($forgotten, $case.', l\'appui reste retenu pour le clic suivant');

		}

	}

	/**
	 * La bulle du temps passé se centre sous le rond de sa carte, la pointe au milieu du rond (retour 271,
	 * lot BP). Près du bord de la Semaine ou de l'écran, elle se décale juste assez pour tenir et la
	 * pointe reste sous le rond. `PlanningTask.anchor()` lit toute la géométrie avant d'écrire.
	 *
	 * Le décor, en pixels de l'écran : la Semaine de 0 à 1000, l'écran de 1000, une bulle de 300 bordée d'un pixel,
	 * dont la pointe se compte depuis l'intérieur de la bordure, une carte
	 * à 120 de l'écran et à 100 de son bloc, 40 sous le haut du bloc et haute de 60.
	 */
	public function testBubbleAnchor(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTask {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTask') === FALSE) {
			return;
		}

		// Centre du rond, en pixels de l'écran => [gauche de la bulle dans son bloc, pointe dans la bulle]
		$cases = [
			'une carte du milieu' => [500, [330, 149]],
			'une carte contre le bord droit' => [950, [680, 249]],
			'une carte contre le bord gauche' => [40, [-20, 39]],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({document: {documentElement: {clientWidth: 1000}}});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningTask = PlanningTask;", context);

			const results = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).'.map(center => {

				let written = false;
				let readAfterWrite = false;

				const read = value => {
					if(written) {
						readAfterWrite = true;
					}
					return value;
				};

				const style = {
					properties: {},
					setProperty(name, value) { written = true; this.properties[name] = value; },
					set top(value) { written = true; this.properties.top = value; },
					set left(value) { written = true; this.properties.left = value; }
				};

				const mark = {getBoundingClientRect: () => read({left: center - 11, width: 22})};

				const card = {
					get offsetTop() { return read(40); },
					get offsetHeight() { return read(60); },
					get offsetLeft() { return read(100); },
					getBoundingClientRect: () => read({left: 120}),
					querySelector: selector => (selector === ".planning-task-mark-ring") ? mark : null
				};

				const bubble = {
					isConnected: true,
					previousElementSibling: card,
					get offsetWidth() { return read(300); },
					get clientLeft() { return read(1); },
					style: style,
					closest: selector => (selector === "#planning-week") ? {getBoundingClientRect: () => read({left: 0, right: 1000})} : null
				};

				context.PlanningTask.anchor(bubble);

				return [style.properties.top, style.properties.left, style.properties["--planning-task-bubble-tip"], readAfterWrite];

			});

			process.stdout.write(JSON.stringify(results));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$top, $left, $tip, $readAfterWrite] = $results[$position];
			[$leftExpected, $tipExpected] = $cases[$case][1];

			$this->assertEquals('100px', $top, $case.', la bulle ne se pose pas sous la carte');
			$this->assertEquals($leftExpected.'px', $left, $case.', la bulle ne se centre pas sous le rond, ou ne tient pas dans la Semaine');
			$this->assertEquals($tipExpected.'px', $tip, $case.', la pointe n\'est pas au milieu du rond');
			$this->assertFalse($readAfterWrite, $case.', la géométrie est lue après une écriture');

		}

	}

	/**
	 * Le travail estimé de la semaine (R-Retours-1210, 312) : le temps prévu des interventions planifiées cette
	 * semaine et de celles qui y sont réalisées, tous filtres ôtés. Une intervention d'une autre semaine n'y
	 * entre pas, même cochée d'ici, et la réponse d'un geste redit la ligne. Une semaine sans temps prévu
	 * garde le nœud.
	 */
	public function testTimeExpectedWeek(): void {

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure du décor suit le temps de travail') === FALSE) {
			return;
		}

		$weekPrevious = toWeek(week_date_starts(\Clock::week()).' - 7 days');
		$weekNext = TaskDayLib::getNextWeek(\Clock::week());

		[$document] = $this->renderTasks(week: $weekNext);

		$estimated = $document->querySelector('#planning-week > #planning-week-estimated');

		$this->assertTrue($estimated !== NULL, 'une semaine sans temps prévu ne garde pas le nœud du travail estimé');

		$eTask = $this->createTaskExpected(1.5, \Clock::week());
		$this->createTaskExpected(0.75, \Clock::week());
		$this->createTaskExpected(0.0, \Clock::week());
		$this->createTaskExpected(4.0, $weekNext);
		$eTaskLate = $this->createTaskExpected(1.0, $weekPrevious);

		$text = fn(float $time): string => s("Travail estimé à {value} sur la semaine", \series\TaskUi::convertTime($time));

		[, $data] = $this->renderTasks();

		$this->assertEquals(2.25, $data->timeExpected, 'le travail estimé ne somme pas le prévu des seules interventions planifiées cette semaine');

		// Une intervention cochée cette semaine y reste comptée, et la réponse du geste redit la ligne.
		[$data, $instructions, $action] = $this->callTask('doDone', $eTask);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(2.25, $data->timeExpected, 'une intervention réalisée cette semaine sort du travail estimé');
		$this->assertTrue(str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), trim(json_encode($text(2.25), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '"')), 'la réponse de la coche ne redit pas le travail estimé');

		// Une intervention en retard se coche dans sa propre semaine : son temps prévu reste là-bas.
		[$data] = $this->callTask('doDone', $eTaskLate);

		$this->assertEquals(2.25, $data?->timeExpected, 'une intervention en retard, réalisée dans sa semaine, entre dans le travail estimé de celle-ci');

	}

	private function getCardSearch(\Dom\HTMLDocument $document, \series\Task $eTask): array {

		$node = $document->querySelector('#planning-task-'.$eTask['id'].' .planning-task-main');

		return ($node === NULL) ? [] : json_decode($node->getAttribute('data-search'), TRUE);

	}

	private function requireTime(): bool {

		return $this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour la bulle');

	}

	private function getCardTitle(\Dom\HTMLDocument $document, \series\Task $eTask): string {

		$node = $document->querySelector('#planning-task-'.$eTask['id'].' .planning-task-name');

		return ($node === NULL) ? '' : trim($node->textContent);

	}

	private function getOrder(\Collection $ccTask): array {

		$order = [];

		foreach($ccTask as $action => $cTask) {
			$order[$action] = $cTask->getIds();
		}

		return $order;

	}

	/**
	 * Une intervention de culture à faire dans la semaine donnée, avec son temps prévu.
	 */
	private function createTaskExpected(float $timeExpected, string $week): \series\Task {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		if($timeExpected > 0.0) {
			$eTask->build(['timeExpected'], ['timeExpected' => (string)$timeExpected]);
			\series\TaskLib::update($eTask, ['timeExpected']);
		}

		return \series\TaskLib::getById($eTask['id']);

	}

	/**
	 * Une intervention sans série, sans espèce et sans note : la carte n'a rien pour la nommer.
	 */
	private function createTaskBare(): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, qui vérifierait l'action et la semaine saisies : le décor veut l'intervention que
		// le formulaire ne produit pas, sans rien pour la nommer, puisque c'est le cas éprouvé.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * La dernière durée écrite que porte le champ de la bulle (`data-sent`).
	 */
	private function getBubbleTime(array $instructions, \series\Task $eTask): string {
		return $this->getBubble($instructions, $eTask)?->querySelector('.planning-duration-step')?->getAttribute('data-sent') ?? '';
	}

	private function getBubble(array $instructions, \series\Task $eTask): ?\Dom\Element {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js' or $instruction[1] !== '#planning-task-'.$eTask['id']) {
				continue;
			}

			foreach($instruction[2] as [$method, $arguments]) {

				if($method === 'insertAdjacentHtml') {
					$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$arguments[1].'</body></html>', LIBXML_NOERROR);
					return $document->querySelector('#planning-task-bubble-'.$eTask['id']);
				}

			}

		}

		return NULL;

	}

	private function getMemberOther(): \user\User {

		$cUser = \structure\MemberLib::getUsersByStructure($this->eStructure)->find(fn($eUser) => $eUser->is($this->getMember()) === FALSE);

		return $cUser->empty() ? new \user\User() : $cUser->first();

	}

	private function createTimesheetOther(\series\Task $eTask, \user\User $eUser, float $time, string $date): void {

		$eTimesheet = new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'series' => $eTask['series'],
			'cultivation' => $eTask['cultivation'],
			'plant' => $eTask['plant'],
			'task' => $eTask,
			'time' => $time,
			'date' => $date,
			'week' => toWeek($date),
		]);

		// Sans `build()`, qui vérifierait le temps et le jour saisis pour la personne connectée : le décor pose
		// le temps d'un autre membre, qu'aucun formulaire ne lui fait saisir, et c'est ce que la bulle ne doit pas toucher.
		\series\TimesheetLib::create($eTimesheet);

	}

	private function getTimes(\series\Task $eTask, \user\User $eUser = new \user\User()): \Collection {

		return \series\Timesheet::model()
			->select(['user', 'time', 'date'])
			->whereTask($eTask)
			->whereUser($eUser, if: $eUser->notEmpty())
			->getCollection();

	}

	/**
	 * Les temps d'une personne sur l'intervention, sommés par jour, dans l'ordre des jours.
	 */
	private function getTimesByDate(\series\Task $eTask, \user\User $eUser): array {

		$times = [];

		$cTimesheet = \series\Timesheet::model()
			->select(['date', 'total' => new \Sql('SUM(time)', 'float')])
			->whereTask($eTask)
			->whereUser($eUser)
			->group('date')
			->sort(['date' => SORT_ASC])
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['date']] = round($eTimesheet['total'], 2);
		}

		return $times;

	}

	/**
	 * La durée de l'intervention, celle du pied de sa carte : tout le monde, tous les jours.
	 */
	private function getTaskTime(\series\Task $eTask): float {

		return round((float)\series\Task::model()
			->select('time')
			->whereId($eTask['id'])
			->get()['time'], 2);

	}

	private function getDateBefore(int $days): string {

		return date('Y-m-d', strtotime(\Clock::date().' - '.$days.' DAYS'));

	}

	private function sumTimes(\Collection $cTimesheet): float {

		return round($cTimesheet->sum('time'), 2);

	}

}
?>
