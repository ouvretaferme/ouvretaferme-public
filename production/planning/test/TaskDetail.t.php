<?php
namespace planning;

/**
 * La page d'une intervention (L5b) : ce que son « Temps de travail » affiche, et ce qu'y écrit une
 * saisie (`doTime`), le temps d'une personne un jour (R-Retours-0410, point 20).
 *
 * Le bloc montre une semaine à la fois, choisie par sa tête ou par un jour saisi (`taskDetail:week`), dans
 * un tableau d'une ligne par personne et d'une colonne par jour (lot CT, retour 311) : chaque case est le
 * champ par pas de la personne ce jour-là, et le nom porte son affectation (`doUser`). Une personne sans
 * ligne s'ajoute par « Ajouter une personne » (`taskDetail:user`). Une saisie écrit le
 * jour posté et lui seul (`TimesheetLib::writeTimeForDay()`) : un jour à venir est refusé par `date.future`,
 * une journée de plus de 24 heures, tâches confondues, par `time.tooLong`. Le décor du cas type : 2h le lundi, 1h le mercredi,
 * l'intervention faite le mercredi.
 *
 * Chaque cas passe par la vraie route, avec ses validations, dans la transaction du décor
 * (`HarvestFixtureTest`). Le jour du décor est le jeudi de la semaine 39 : la page d'une intervention de
 * cette semaine s'ouvre sur elle, vendredi, samedi et dimanche sont à venir.
 *
 * Lancement : dev/test package=planning file=TaskDetail
 */
class TaskDetailTest extends HarvestFixtureTest {

	/**
	 * Une saisie écrit le jour posté, et lui seul : 2h30 hier remplacent l'heure du mercredi, le lundi ne
	 * bouge pas, et la durée de l'intervention suit.
	 */
	public function testWriteDay(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$action = $this->callTime($eTask, $this->getMember(), '2.5', $this->getWednesday());

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie d\'hier est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$this->getMonday() => 2.0, $this->getWednesday() => 2.5], $this->getTimesByDate($eTask, $this->getMember()), 'la saisie d\'hier n\'écrit pas le seul mercredi');
		$this->assertEquals(4.5, $this->getTaskTime($eTask), 'la durée de l\'intervention ne suit pas la saisie');

		$action = $this->callTime($eTask, $this->getMember(), '1.25', $this->getThursday());

		$this->assertTrue($action instanceof \ViewAction, 'la saisie d\'aujourd\'hui est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$this->getMonday() => 2.0, $this->getWednesday() => 2.5, $this->getThursday() => 1.25], $this->getTimesByDate($eTask, $this->getMember()), 'la saisie d\'aujourd\'hui n\'ajoute pas le seul jeudi');

	}

	/**
	 * Un jour d'avant hier s'écrit de même, et zéro n'efface que lui.
	 */
	public function testWriteOldDay(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$this->callTime($eTask, $this->getMember(), '0.5', $this->getMonday());

		$this->assertEquals([$this->getMonday() => 0.5, $this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'la saisie du lundi n\'écrit pas le seul lundi');

		$action = $this->callTime($eTask, $this->getMember(), '0', $this->getMonday());

		$this->assertTrue($action instanceof \ViewAction, 'la remise à zéro du lundi est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'la remise à zéro du lundi touche un autre jour');
		$this->assertEquals(1.0, $this->getTaskTime($eTask), 'la durée de l\'intervention ne suit pas la remise à zéro');

	}

	/**
	 * La poubelle d'une case (FJ, retour 412) poste une durée nulle par la route de la saisie : le jour n'a plus
	 * aucune ligne de temps, pas même une ligne à zéro, la durée de l'intervention suit, et la réponse redessine
	 * la case quand la personne garde sa ligne (`user`). Effacer le seul temps d'une personne retire sa ligne,
	 * et la réponse redessine le tableau (`grid`).
	 */
	public function testErase(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$action = $this->callTime($eTask, $this->getMember(), '0', $this->getWednesday());

		if($this->assertTrue($action instanceof \ViewAction, 'l\'effacement du mercredi est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(0, $this->countTimesheets($eTask, $this->getMember(), $this->getWednesday()), 'l\'effacement laisse une ligne de temps au mercredi');
		$this->assertEquals([$this->getMonday() => 2.0], $this->getTimesByDate($eTask, $this->getMember()), 'l\'effacement du mercredi touche un autre jour');
		$this->assertEquals(2.0, $this->getTaskTime($eTask), 'la durée de l\'intervention ne suit pas l\'effacement');
		$this->assertEquals('user', $data->change, 'une personne qui garde du temps dans la semaine perd sa ligne');

		$eUserOther = $this->getMemberOther();

		if($this->assertDecorPresent($eUserOther->notEmpty() and \structure\StructureLib::getById($this->eStructure['id'])->canManage(), 'un second membre sur la structure '.static::STRUCTURE.', dont la personne connectée écrit le temps') === FALSE) {
			return;
		}

		$this->createTimesheetFor($eTask, $eUserOther, 0.75, $this->getWednesday());

		$action = $this->callTime($eTask, $eUserOther, '0', $this->getWednesday());

		if($this->assertTrue($action instanceof \ViewAction, 'l\'effacement du temps d\'un autre membre est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(0, $this->countTimesheets($eTask, $eUserOther, $this->getWednesday()), 'l\'effacement laisse une ligne de temps à l\'autre membre');
		$this->assertEquals('grid', $data->change, 'la personne dont le seul temps est effacé garde sa ligne');
		$this->assertFalse($data->cUser[$eUserOther['id']]['line'], 'le tableau redessiné montre encore la ligne de la personne effacée');

	}

	/**
	 * Un jour à venir est refusé par `date.future`, un jour illisible ou absent par le schéma, sans rien
	 * écrire.
	 */
	public function testDateRefused(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$tomorrow = date('Y-m-d', \Clock::shift('+ 1 DAY'));

		$action = $this->callTime($eTask, $this->getMember(), '1', $tomorrow);

		$this->assertEquals(['Timesheet::date.future'], $this->getFails($action), 'demain n\'est pas refusé par date.future');

		$action = $this->callTime($eTask, $this->getMember(), '1', 'hier');

		$this->assertEquals(['Timesheet::date.check'], $this->getFails($action), 'un jour illisible n\'est pas refusé par le schéma');

		$action = $this->callTime($eTask, $this->getMember(), '1', NULL);

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une saisie sans jour est acceptée');
		$this->assertEquals([$this->getMonday() => 2.0, $this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'un jour refusé change les temps');

	}

	/**
	 * Le temps d'un autre membre sur l'intervention ne bouge pas quand on écrit le sien, même remis à zéro.
	 */
	public function testOtherMemberKept(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre, dont le temps doit rester') === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();
		$this->createTimesheetFor($eTask, $eUserOther, 0.75, $this->getWednesday());

		$this->callTime($eTask, $this->getMember(), '0', $this->getWednesday());

		$this->assertEquals([$this->getWednesday() => 0.75], $this->getTimesByDate($eTask, $eUserOther), 'la remise à zéro d\'une personne touche le temps d\'une autre');
		$this->assertEquals(2.75, $this->getTaskTime($eTask), 'la durée de l\'intervention ne suit pas les temps restants');

	}

	/**
	 * Le temps d'un autre membre s'écrit selon le droit du temps (`Timesheet::canWrite()`) : qui gère la
	 * structure l'écrit, les autres non.
	 */
	public function testOtherMemberRight(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $this->getWednesday());

		$canManage = \structure\StructureLib::getById($this->eStructure['id'])->canManage();

		$action = $this->callTime($eTask, $eUserOther, '1', $this->getWednesday());

		if($canManage) {
			$this->assertEquals([$this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $eUserOther), 'qui gère la structure n\'écrit pas le temps d\'un autre membre');
		} else {
			$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'qui ne gère pas la structure écrit le temps d\'un autre membre');
			$this->assertEquals([], $this->getTimesByDate($eTask, $eUserOther), 'le temps d\'un autre membre s\'écrit sans le droit');
		}

	}

	/**
	 * Bruno, au planning limité, écrit son propre temps et pas celui d'un autre, quoi que poste le
	 * formulaire : le droit se vérifie côté serveur.
	 */
	public function testLimitedOther(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $this->getWednesday());

		$eUserLimited = $this->getMemberPlanning();

		\user\ConnectionLib::setOnline($eUserLimited);

		$action = $this->callTime($eTask, $this->getMember(), '1', $this->getWednesday());

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'Bruno, au planning limité, écrit le temps d\'un autre membre');
		$this->assertEquals([], $this->getTimesByDate($eTask, $this->getMember()), 'le temps d\'un autre membre s\'écrit au nom de Bruno');

		$action = $this->callTime($eTask, $eUserLimited, '1', $this->getWednesday());

		$this->assertTrue($action instanceof \ViewAction, 'Bruno n\'écrit pas son propre temps : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $eUserLimited), 'le temps de Bruno ne s\'écrit pas');

	}

	/**
	 * Une personne qui n'est pas membre de la structure ne reçoit pas de temps.
	 */
	public function testNotMember(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$eUserFormer = $this->createUserFormer();

		$action = $this->callTime($eTask, $eUserFormer, '1', $this->getWednesday());

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'le temps d\'une personne hors de la structure est accepté');
		$this->assertEquals([], $this->getTimesByDate($eTask, $eUserFormer), 'le temps d\'une personne hors de la structure s\'écrit');

	}

	/**
	 * Une durée négative est refusée par le schéma, sans rien écrire.
	 */
	public function testRefused(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$action = $this->callTime($eTask, $this->getMember(), '-1', $this->getWednesday());

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une durée négative est acceptée');
		$this->assertEquals([$this->getMonday() => 2.0, $this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'une durée refusée change les temps');

	}

	/**
	 * Une durée illisible est refusée par le schéma, une saisie sans durée par `time.missing` : elle ne
	 * remet pas le jour à zéro. Les deux sans rien écrire. Prend la place de `testNotANumber`, parti avec
	 * l'ancienne saisie par total (relecteur du point 20, n° 7).
	 */
	public function testTimeMissing(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$action = $this->callTime($eTask, $this->getMember(), 'abc', $this->getWednesday());

		$this->assertEquals(['Timesheet::time.check'], $this->getFails($action), 'une durée illisible n\'est pas refusée par le schéma');

		$action = $this->callTime($eTask, $this->getMember(), NULL, $this->getWednesday());

		$this->assertEquals(['Timesheet::time.missing'], $this->getFails($action), 'une saisie sans durée n\'est pas refusée par time.missing');
		$this->assertEquals([$this->getMonday() => 2.0, $this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'une durée absente ou illisible change les temps');

	}

	/**
	 * Une journée ne dépasse pas 24 h de travail, toutes interventions comprises : la saisie qui y mène
	 * s'écrit, celle qui la dépasse est refusée par `time.tooLong` sans rien écrire. Le mercredi peut déjà
	 * porter du temps réel de la structure hôte, d'où des durées calculées depuis ce qu'il porte.
	 */
	public function testDayTooLong(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$free = round(24.0 - \series\TimesheetLib::getTimeByUserDate($this->eStructure, $this->getMember(), $this->getWednesday()), 2);

		$action = $this->callTime($eTask, $this->getMember(), (string)round(1.0 + $free + 0.25, 2), $this->getWednesday());

		$this->assertEquals(['Timesheet::time.tooLong'], $this->getFails($action), 'une journée portée au-delà de 24 h n\'est pas refusée par time.tooLong');
		$this->assertEquals([$this->getMonday() => 2.0, $this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'une journée refusée change les temps');

		$action = $this->callTime($eTask, $this->getMember(), (string)round(1.0 + $free, 2), $this->getWednesday());

		$this->assertTrue($action instanceof \ViewAction, 'une journée portée à 24 h tout juste est refusée : '.implode(', ', $this->getFails($action)));

	}

	/**
	 * Le temps par personne et par jour (`TaskDetailLib::getUsers()`) : la durée de chaque jour qui en a, le
	 * total et le temps de la semaine montrée. La personne connectée vient en tête.
	 */
	public function testUsers(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();
		$this->createTimesheet($eTask, 0.5, $this->getThursday());
		$this->createTimesheet($eTask, 0.25, $this->getTuesday());
		$this->createTimesheetFor($eTask, $eUserOther, 0.75, $this->getTuesday());

		$cUser = $this->getWeekUsers($eTask, \Clock::week());

		$eUser = $cUser[$this->getMember()['id']];

		$times = $eUser['times'];
		ksort($times);

		$this->assertEquals([$this->getMonday() => 2.0, $this->getTuesday() => 0.25, $this->getWednesday() => 1.0, $this->getThursday() => 0.5], $times, 'les jours de la personne connectée');
		$this->assertEquals(3.75, $eUser['time'], 'le total de la personne');
		$this->assertEquals(3.75, $eUser['timeWeek'], 'le temps de la personne sur la semaine montrée');
		$this->assertTrue($eUser['line'], 'la personne connectée, qui a du temps cette semaine, n\'a pas sa ligne');

		$eUserOtherLine = $cUser[$eUserOther['id']];

		$this->assertEquals([$this->getTuesday() => 0.75], $eUserOtherLine['times'], 'les jours de l\'autre membre');
		$this->assertTrue($eUserOtherLine['line'], 'l\'autre membre, qui a du temps cette semaine, n\'a pas sa ligne');

		$this->assertEquals($this->getMember()['id'], $cUser->first()['id'], 'la personne connectée ne vient pas en tête');

	}

	/**
	 * La semaine que le bloc montre à l'ouverture : la semaine en cours si celle de l'intervention l'est ou est
	 * à venir, ses jours refusant tous la saisie, sinon celle de l'intervention si elle porte du temps ou si
	 * l'intervention n'en a aucun, sinon celle du dernier jour saisi.
	 */
	public function testOpeningWeek(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$weekBefore = toWeek(\Clock::date().' - 14 DAYS');
		$weekAfter = toWeek(\Clock::date().' + 14 DAYS');

		$eTaskWithTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weekBefore, week_date_day($weekBefore, 2));
		$this->createTimesheet($eTaskWithTime, 1.0, week_date_day($weekBefore, 2));
		$this->createTimesheetFor($eTaskWithTime, $eUserOther, 0.5, week_date_day($weekBefore, 4));
		// Du temps hors de la semaine de l'intervention ne la fait pas ouvrir ailleurs que dans sa semaine.
		$this->createTimesheet($eTaskWithTime, 0.25, $this->getMonday());

		// Du temps seulement hors de la semaine de l'intervention, avant elle et après elle : le plus récent l'emporte.
		$weekBeforeBefore = toWeek(\Clock::date().' - 28 DAYS');

		$eTaskOutside = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weekBefore, week_date_day($weekBefore, 2));
		$this->createTimesheet($eTaskOutside, 1.0, week_date_day($weekBeforeBefore, 3));
		$this->createTimesheetFor($eTaskOutside, $eUserOther, 0.5, $this->getTuesday());

		$cases = [
			'semaine en cours' => [$this->createTaskMondayWednesday(), \Clock::week()],
			'temps hors de sa semaine seulement' => [$eTaskOutside, \Clock::week()],
			'semaine passée avec du temps' => [$eTaskWithTime, $weekBefore],
			'semaine passée sans temps' => [$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore), $weekBefore],
			'semaine à venir' => [$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekAfter), \Clock::week()],
		];

		foreach($cases as $case => [$eTask, $expected]) {

			$eTask = \series\TaskLib::getById($eTask['id']);

			$this->assertEquals($expected, TaskDetailLib::getOpeningWeek($eTask), $case.' : la semaine d\'ouverture');

			$document = $this->renderDetail($eTask);

			$this->assertEquals($expected, $this->getWeekShown($document->getElementById('planning-task-detail-grid')), $case.' : le tableau n\'est pas celui de la semaine d\'ouverture');

		}

	}

	/**
	 * Un jour ne compte que s'il affiche au moins une minute : un jour à zéro ou à moins d'une demi-minute ne
	 * compte ni dans la somme de la semaine ni dans le pied. Le total d'une personne compte pourtant tout son temps.
	 */
	public function testZeroDay(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$weekBefore = toWeek(\Clock::date().' - 14 DAYS');

		// 0,008 h vaut 0,48 minute : affiché 0h00, entre le centième et la minute.
		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weekBefore, week_date_day($weekBefore, 2));
		$this->createTimesheet($eTask, 1.0, week_date_day($weekBefore, 2));
		$this->createTimesheet($eTask, 0.0, week_date_day($weekBefore, 4));
		$this->createTimesheet($eTask, 0.008, week_date_day($weekBefore, 5));

		$eUserFormer = $this->createUserFormer();
		$this->createTimesheetFor($eTask, $eUserFormer, 1.0, week_date_day($weekBefore, 2));
		$this->createTimesheetFor($eTask, $eUserFormer, 0.008, week_date_day($weekBefore, 5));

		$cUser = $this->getWeekUsers($eTask, $weekBefore);

		$this->assertTrue(abs($cUser[$this->getMember()['id']]['time'] - 1.008) < 0.000001, 'le total d\'une personne n\'est pas la somme brute de son temps');
		$this->assertEquals(1.0, $cUser[$this->getMember()['id']]['timeWeek'], 'la somme de la semaine compte un jour sans minute');

		$document = $this->renderDetail($eTask);


		$foot = $document->querySelectorAll('#planning-task-detail-foot > div');

		// Le pied commence par son libellé : le jour de rang N est sa case N.
		foreach([4, 5] as $position) {
			$this->assertEquals('', trim($foot->item($position)?->textContent ?? '-'), 'le jour '.$position.', sans minute, a une valeur au pied');
		}

	}

	/**
	 * Une personne a sa ligne quand un de ses jours de la semaine affiche au moins une minute. Des jours qui
	 * n'en affichent aucune ne lui en donnent pas, même quand leur somme en affiche une : le tableau n'aurait
	 * aucune case à lui remplir.
	 */
	public function testMinutePerson(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$weekBefore = toWeek(\Clock::date().' - 14 DAYS');

		// 0,008 h et 0,006 h valent 0,48 et 0,36 minute : chaque jour affiché 0h00, leur somme 0h01.
		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weekBefore, week_date_day($weekBefore, 2));
		$this->createTimesheet($eTask, 1.0, week_date_day($weekBefore, 2));
		$this->createTimesheetFor($eTask, $eUserOther, 0.008, week_date_day($weekBefore, 3));
		$this->createTimesheetFor($eTask, $eUserOther, 0.006, week_date_day($weekBefore, 4));

		$cUser = $this->getWeekUsers($eTask, $weekBefore);

		$this->assertEquals([], $cUser[$eUserOther['id']]['times'], 'un jour sous la minute compte dans les jours de la personne');
		$this->assertFalse($cUser[$eUserOther['id']]['line'], 'une personne dont aucun jour n\'affiche une minute a sa ligne');
		$this->assertTrue(abs($cUser[$eUserOther['id']]['time'] - 0.014) < 0.000001, 'le total de la personne ne compte pas son temps sous la minute');

		$this->createTimesheetFor($eTask, $eUserOther, 0.0084, week_date_day($weekBefore, 5));

		$this->assertTrue($this->getWeekUsers($eTask, $weekBefore)[$eUserOther['id']]['line'], 'une personne dont un jour affiche une minute n\'a pas sa ligne');

	}

	/**
	 * Un jour qui affiche une minute compte : 0,0084 h vaut 0,504 minute, affichée 0h01 par sa case ;
	 * 0,0917 h vaut 5,502 minutes, affichées 0h06, qu'un arrondi au centième lirait 0h05.
	 */
	public function testMinuteDay(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$weekBefore = toWeek(\Clock::date().' - 14 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weekBefore, week_date_day($weekBefore, 3));
		$this->createTimesheet($eTask, 0.0917, week_date_day($weekBefore, 2));
		$this->createTimesheet($eTask, 0.0084, week_date_day($weekBefore, 3));

		$document = $this->renderDetail(\series\TaskLib::getById($eTask['id']));

		$cell = '#planning-task-detail-cell-'.$this->getMember()['id'].'-';

		$this->assertEquals('0h01', $this->readStepped($document->querySelector($cell.week_date_day($weekBefore, 3).' .planning-duration-step')), 'la case ne dit pas la minute de son jour');
		$this->assertEquals('0h06', $this->readStepped($document->querySelector($cell.week_date_day($weekBefore, 2).' .planning-duration-step')), 'la case n\'arrondit pas à la minute');

	}

	/**
	 * Une personne a sa ligne quand elle est affectée à l'intervention ou qu'elle y a du temps la semaine
	 * montrée, et la personne connectée toujours (`online`). Les autres se proposent dans « Ajouter une
	 * personne ». La personne connectée vient en tête, puis les personnes affectées, puis les autres.
	 */
	public function testLine(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheetFor($eTask, $eUserOther, 0.75, $this->getWednesday());

		$cUser = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertTrue($cUser[$eUserOther['id']]['line'], 'une personne qui a du temps cette semaine n\'a pas sa ligne');
		$this->assertEquals([TRUE, FALSE], [$cUser[$this->getMember()['id']]['line'], $cUser[$this->getMember()['id']]['addable']], 'la personne connectée, sans temps ni affectation, n\'a pas sa ligne à côté du temps d\'un autre, ou se propose à l\'ajout');
		$this->assertEquals([$this->getMember()['id'], $eUserOther['id']], $cUser->find(fn(\user\User $eUser) => $eUser['line'])->getIds(), 'la personne connectée ne vient pas avant celle qui a du temps');

		$cUser = $this->getWeekUsers($eTask, toWeek(\Clock::date().' - 7 DAYS'));

		$this->assertFalse($cUser[$eUserOther['id']]['line'], 'du temps saisi une autre semaine donne une ligne');

		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTask['id'])]), $this->getMember(), 'add');

		$cUser = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([TRUE, FALSE], [$cUser[$this->getMember()['id']]['line'], $cUser[$this->getMember()['id']]['addable']], 'une personne affectée n\'a pas sa ligne, ou se propose encore à l\'ajout');

		// Connecté en membre qui n'écrit pas de temps, personne n'a de ligne d'office : l'ordre est celui des
		// affectations.
		\user\ConnectionLib::setOnline($this->getMemberObserver());

		$cUser = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([$this->getMember()['id'], $eUserOther['id']], $cUser->find(fn(\user\User $eUser) => $eUser['line'])->getIds(), 'la personne affectée ne vient pas avant celle qui a seulement du temps');

	}

	/**
	 * La personne connectée a sa ligne d'office (`online`, retour 384, puis V23), sans que rien ne s'écrive :
	 * sur une intervention vide, à côté du temps d'un autre membre et à côté d'une personne affectée, toujours
	 * en tête ; qui ne peut pas écrire son temps, ou n'est pas présent la semaine montrée, n'en a pas. Sa
	 * première durée garde la ligne en place (`user`), le temps d'un autre redessine le tableau, qui gagne sa
	 * ligne. Le pied ne paraît que sous deux lignes qui ont du temps.
	 */
	public function testLineOnline(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$member = $this->getMember()['id'];
		$other = $eUserOther['id'];

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$cUserBefore = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([TRUE, TRUE, FALSE], [$cUserBefore[$member]['online'], $cUserBefore[$member]['line'], $cUserBefore[$member]['added']], 'la personne connectée n\'a pas sa ligne d\'office sur une intervention vide');
		$this->assertEquals([FALSE, FALSE], [$cUserBefore[$other]['online'], $cUserBefore[$other]['line']], 'un autre membre a sa ligne d\'office');
		$this->assertEquals([], $this->getTimesByDate($eTask, $this->getMember()), 'la ligne d\'office écrit du temps');

		$this->createTimesheet($eTask, 1.0, $this->getMonday());

		$cUserAfter = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([TRUE, TRUE], [$cUserAfter[$member]['online'], $cUserAfter[$member]['line']], 'la personne connectée perd sa ligne d\'office à sa première durée');
		$this->assertEquals('user', TaskDetailLib::getChange($cUserBefore, $cUserAfter), 'la première durée redessine le tableau');
		$this->assertEquals('user', TaskDetailLib::getChange($cUserAfter, $cUserBefore), 'le retrait de la dernière durée redessine le tableau');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheetFor($eTask, $eUserOther, 0.75, $this->getMonday());

		$cUserAfter = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([TRUE, TRUE], [$cUserAfter[$member]['online'], $cUserAfter[$member]['line']], 'la personne connectée perd sa ligne d\'office à côté du temps d\'un autre');
		$this->assertEquals([$member, $other], $cUserAfter->find(fn(\user\User $eUser) => $eUser['line'])->getIds(), 'la personne connectée ne vient pas en tête, devant celle qui a du temps');
		$this->assertEquals('grid', TaskDetailLib::getChange($cUserBefore, $cUserAfter), 'le temps d\'un autre ne redessine pas le tableau');
		$this->assertFalse(TaskDetailLib::getFoot($cUserAfter, [])['shown'], 'le pied paraît sous une seule ligne qui a du temps');

		$this->createTimesheet($eTask, 0.5, $this->getMonday());

		$this->assertTrue(TaskDetailLib::getFoot($this->getWeekUsers($eTask, \Clock::week()), [])['shown'], 'le pied ne paraît pas sous deux lignes qui ont du temps');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTask['id'])]), $eUserOther, 'add');

		$cUserAfter = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([TRUE, TRUE], [$cUserAfter[$member]['online'], $cUserAfter[$member]['line']], 'la personne connectée perd sa ligne d\'office à côté d\'une personne affectée');
		$this->assertEquals([$member, $other], $cUserAfter->find(fn(\user\User $eUser) => $eUser['line'])->getIds(), 'la personne connectée ne vient pas en tête, devant la personne affectée');

		// La semaine qui précède sa première présence.
		$from = min($cUserAfter[$member]['cPresence']->getColumn('from'));
		$weekAbsent = toWeek($from.' - 7 DAYS');
		$cUserAbsent = $this->getWeekUsers($eTask, $weekAbsent);

		if($this->assertDecorPresent($cUserAbsent[$member]['isPresent'] === FALSE, 'la personne connectée absente la semaine '.$weekAbsent) !== FALSE) {
			$this->assertEquals([FALSE, FALSE], [$cUserAbsent[$member]['online'], $cUserAbsent[$member]['line']], 'la personne connectée a sa ligne d\'office une semaine où elle est absente');
		}

		$eUserObserver = $this->getMemberObserver();

		\user\ConnectionLib::setOnline($eUserObserver);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$cUser = $this->getWeekUsers($eTask, \Clock::week());

		if($this->assertDecorPresent($cUser->offsetExists($eUserObserver['id']) and $cUser[$eUserObserver['id']]['canTime'] === FALSE, 'un membre de la structure '.static::STRUCTURE.' qui ne peut pas écrire son temps') === FALSE) {
			return;
		}

		$this->assertEquals([], $cUser->find(fn(\user\User $eUser) => $eUser['line'])->getIds(), 'qui ne peut pas écrire son temps a une ligne d\'office');

	}

	/**
	 * Une personne qui a du temps sur l'intervention sans être membre de la structure garde sa ligne la semaine
	 * de ce temps, sans champ ni affectation, et le total compte son temps. Une semaine où elle n'a rien, elle
	 * n'a pas de ligne.
	 */
	public function testFormerMember(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserFormer = $this->createUserFormer();

		$eTask = $this->createTaskMondayWednesday();
		$this->createTimesheetFor($eTask, $eUserFormer, 2.25, $this->getMonday());

		$document = $this->renderDetail($eTask);

		$person = $document->getElementById('planning-task-detail-user-'.$eUserFormer['id']);

		if($this->assertTrue($person !== NULL, 'l\'ancien membre n\'a pas sa ligne la semaine de son temps') === FALSE) {
			return;
		}

		$this->assertTrue($person->querySelector('[data-planning-detail-assign]') === NULL, 'l\'ancien membre porte un état d\'affectation');
		$this->assertTrue($person->parentElement->querySelector('.planning-duration-step-window, input') === NULL, 'la ligne de l\'ancien membre a une saisie');
		$this->assertEquals('2h15', trim($document->getElementById('planning-task-detail-cell-'.$eUserFormer['id'].'-'.$this->getMonday())?->textContent ?? ''), 'la case de l\'ancien membre ne dit pas son temps du jour');
		$this->assertEquals(5.25, $this->getTaskTime($eTask), 'la durée de l\'intervention ne compte pas le temps de l\'ancien membre');

		$document = $this->renderDetail($eTask, ['weekShown' => toWeek(\Clock::date().' - 7 DAYS')]);

		$this->assertTrue($document->getElementById('planning-task-detail-user-'.$eUserFormer['id']) === NULL, 'l\'ancien membre a une ligne une semaine où il n\'a pas de temps');

	}

	/**
	 * Le bloc pose le total, que la première saisie redessine, et le tableau porte sa semaine sans ligne
	 * ajoutée.
	 */
	public function testBlock(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTaskEmpty = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$document = $this->renderDetail($eTaskEmpty);

		$this->assertTrue($document->getElementById('planning-task-detail-total') !== NULL, 'le total du temps n\'est pas posé, la première saisie n\'aurait rien à redessiner');

		$grid = $document->getElementById('planning-task-detail-grid');

		$this->assertEquals([\Clock::week(), ''], [$this->getWeekShown($grid), $grid?->getAttribute('data-added')], 'le tableau ne porte pas sa semaine, ou porte une ligne ajoutée que personne n\'a demandée');

	}

	/**
	 * Le tableau de la semaine : une case par personne et par jour qui porte son temps, les jours à venir sans
	 * geste.
	 */
	public function testGrid(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$this->createTimesheetFor($eTask, $eUserOther, 0.5, $this->getMonday());

		$document = $this->renderDetail($eTask);

		$member = $this->getMember()['id'];
		$values = [];
		$dates = [];

		foreach($document->querySelectorAll('.planning-detail-time-field[data-user="'.$member.'"]') as $cell) {
			$dates[] = $cell->getAttribute('data-date');
			$values[] = $cell->classList->contains('planning-detail-team-grid-future') ? $cell->childNodes->length : $this->readStepped($cell->querySelector('.planning-duration-step'));
		}

		$this->assertEquals(week_dates(\Clock::week()), $dates, 'la personne n\'a pas une case par jour de la semaine');
		$this->assertEquals(['2h00', '0h00', '1h00', '0h00', 0, 0, 0], $values, 'les cases ne portent pas le temps de leur jour, ou un jour à venir porte un geste');

	}

	/**
	 * Une personne dont la personne connectée ne peut pas écrire le temps garde sa ligne la semaine où elle en
	 * a, en texte : sa case dit son temps du jour, ou rien le jour où elle n'en a pas. Bruno, au planning
	 * limité, a sa ligne d'office et y saisit.
	 */
	public function testReadOnlyPerson(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$eUserLimited = $this->getMemberPlanning();

		$this->addPresence($eUserLimited);

		\user\ConnectionLib::setOnline($eUserLimited);

		$document = $this->renderDetail($eTask);

		$cell = '#planning-task-detail-cell-'.$this->getMember()['id'].'-';

		if($this->assertTrue($document->getElementById('planning-task-detail-user-'.$this->getMember()['id']) !== NULL, 'une personne en lecture seule qui a du temps cette semaine n\'a pas sa ligne') === FALSE) {
			return;
		}

		$this->assertTrue($document->querySelector('.planning-detail-time-field[data-user="'.$this->getMember()['id'].'"] input, .planning-detail-time-field[data-user="'.$this->getMember()['id'].'"] .planning-duration-step-window') === NULL, 'la ligne d\'une personne en lecture seule porte un champ');
		$this->assertEquals('2h00', trim($document->querySelector($cell.$this->getMonday())?->textContent ?? ''), 'la case d\'une personne en lecture seule ne dit pas son temps du jour');

		$this->assertTrue($document->getElementById('planning-task-detail-user-'.$eUserLimited['id']) !== NULL, 'Bruno, sans temps ni affectation, n\'a pas sa ligne d\'office');
		$this->assertTrue($document->querySelector('.planning-detail-time-add-list > [data-planning-detail-add="'.$eUserLimited['id'].'"]') === NULL, 'Bruno se propose à l\'ajout alors qu\'il a sa ligne');
		$this->assertTrue($document->querySelector('.planning-detail-time-add-list > [data-planning-detail-add="'.$this->getMember()['id'].'"]') === NULL, 'Bruno peut ajouter une personne qui a déjà sa ligne');
		$this->assertEquals(4, $document->querySelectorAll('.planning-detail-time-field[data-user="'.$eUserLimited['id'].'"] > .planning-duration-step-window')->length, 'la ligne de Bruno n\'a pas une saisie par jour passé de la semaine');

	}

	/**
	 * Vue par qui n'écrit que son propre temps, la somme des cases d'un jour vaut le pied de ce jour, chaque
	 * jour de la semaine : un membre à 2h le lundi et 1h le mercredi, un ancien membre à 2h15 le dimanche. La
	 * semaine est la précédente : aucun de ses jours n'est à venir.
	 */
	public function testReadOnlyTotal(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, week_date_day($week, 3));
		$this->createTimesheet($eTask, 2.0, week_date_day($week, 1));
		$this->createTimesheet($eTask, 1.0, week_date_day($week, 3));
		$this->createTimesheetFor($eTask, $this->createUserFormer(), 2.25, week_date_day($week, 7));

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		$this->assertWeekMatchesLines($eTask, $week, [1 => 120, 3 => 60, 7 => 135]);

	}

	/**
	 * Vue par qui gère la structure, de même : la personne connectée à 2h le lundi et 1h le mercredi, un autre
	 * membre à 1h30 le mardi seulement, un ancien membre à 2h15 le dimanche. Une case se lit sur son champ, ou
	 * sur sa valeur en lecture seule.
	 */
	public function testAdminTotal(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, week_date_day($week, 3));
		$this->createTimesheet($eTask, 2.0, week_date_day($week, 1));
		$this->createTimesheet($eTask, 1.0, week_date_day($week, 3));
		$this->createTimesheetFor($eTask, $eUserOther, 1.5, week_date_day($week, 2));
		$this->createTimesheetFor($eTask, $this->createUserFormer(), 2.25, week_date_day($week, 7));

		$document = $this->renderDetail($eTask);

		if($this->assertTrue($document->querySelector('#planning-task-detail-cell-'.$eUserOther['id'].'-'.week_date_day($week, 2).' input[name="timeAddHour"]') !== NULL, 'la vue n\'est pas celle de qui gère la structure : l\'autre membre n\'a pas de champ') === FALSE) {
			return;
		}

		$this->assertWeekMatchesLines($eTask, $week, [1 => 120, 2 => 90, 3 => 60, 7 => 135]);

	}

	/**
	 * Le tableau de `$week` : chaque jour, la somme des cases vaut le pied du jour, en minutes (`$expected`,
	 * par rang du jour, zéro ailleurs), et la somme des lignes vaut celle du pied.
	 */
	private function assertWeekMatchesLines(\series\Task $eTask, string $week, array $expected): void {

		$document = $this->renderDetail($eTask, ['weekShown' => $week]);

		$this->assertEquals($week, $this->getWeekShown($document->getElementById('planning-task-detail-grid')), 'le tableau ne montre pas la semaine demandée');

		$foot = $document->querySelectorAll('#planning-task-detail-foot > div');

		foreach(week_dates($week) as $position => $date) {

			$lines = 0;

			foreach($document->querySelectorAll('.planning-detail-time-field[data-date="'.$date.'"]') as $cell) {
				$lines += $this->toMinutes($this->readStepped($cell->querySelector('.planning-duration-step')) ?? trim($cell->textContent));
			}

			$day = $this->toMinutes(trim($foot->item($position + 1)?->textContent ?? ''));

			$this->assertEquals($expected[$position + 1] ?? 0, $day, 'le '.$date.', le pied ne dit pas le temps du jour');
			$this->assertEquals($day, $lines, 'le '.$date.', la somme des cases ne vaut pas le pied du jour');

		}

		$sums = 0;

		foreach($document->querySelectorAll('.util-table-row:not(.planning-detail-team-grid-foot) > .planning-detail-team-grid-sum') as $sum) {
			$sums += $this->toMinutes(trim($sum->textContent));
		}

		$this->assertEquals(array_sum($expected), $sums, 'la somme des lignes ne vaut pas le temps de la semaine');
		$this->assertEquals($sums, $this->toMinutes(trim($foot->item(8)?->textContent ?? '')), 'le pied ne dit pas la somme des lignes');

	}

	/** « 2h15 » en minutes, une valeur vide en zéro. */
	private function toMinutes(string $value): int {

		if($value === '') {
			return 0;
		}

		[$hours, $minutes] = explode('h', $value);

		return (int)$hours * 60 + (int)$minutes;

	}

	/**
	 * « Ajouter une personne » ouvre une entrée par personne sans ligne où un geste est possible, et part quand
	 * personne ne reste à ajouter. Le tableau porte les lignes ajoutées à la main (`data-added`).
	 */
	public function testAdd(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTask = \series\TaskLib::getById($eTask['id']);

		$days = TaskDetailLib::getDays(\Clock::week(), []);
		$foot = ['shown' => FALSE, 'time' => 0.0];

		$cUser = TaskDetailLib::getUsers($eTask, \Clock::week());

		$userIdsAddable = $cUser->find(fn(\user\User $eUser) => $eUser['addable'])->getIds();

		if($this->assertDecorPresent(count($userIdsAddable) >= 2, 'deux personnes au moins à ajouter sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$document = $this->parseHtml(new TaskDetailUi()->getGrid($eTask, $cUser, $days, $foot, TaskDetailUi::getGridDetail($eTask, NULL, \Clock::week())));

		if($this->assertTrue($document->querySelector('#planning-task-detail-grid > a[data-dropdown]') !== NULL, '« Ajouter une personne » n\'ouvre pas de menu') === FALSE) {
			return;
		}

		$userIdsListed = [];

		foreach($document->querySelectorAll('.planning-detail-time-add-list > .dropdown-item[data-planning-detail-add]') as $item) {
			$userIdsListed[] = (int)$item->getAttribute('data-planning-detail-add');
		}

		$this->assertEquals($userIdsAddable, $userIdsListed, 'la liste d\'« Ajouter une personne » ne porte pas une entrée par personne à ajouter');

		$cUser = TaskDetailLib::getUsers($eTask, \Clock::week(), [$userIdsAddable[0]]);

		$document = $this->parseHtml(new TaskDetailUi()->getGrid($eTask, $cUser, $days, $foot, TaskDetailUi::getGridDetail($eTask, NULL, \Clock::week())));

		$this->assertEquals((string)$userIdsAddable[0], $document->getElementById('planning-task-detail-grid')?->getAttribute('data-added'), 'le tableau ne porte pas la ligne ajoutée à la main');
		$this->assertTrue($document->querySelector('.planning-detail-time-add-list > [data-planning-detail-add="'.$userIdsAddable[0].'"]') === NULL, 'une personne ajoutée se propose encore');

		$cUser = TaskDetailLib::getUsers($eTask, \Clock::week(), $userIdsAddable);

		$document = $this->parseHtml(new TaskDetailUi()->getGrid($eTask, $cUser, $days, $foot, TaskDetailUi::getGridDetail($eTask, NULL, \Clock::week())));

		$this->assertTrue($document->querySelector('[data-planning-detail-add]') === NULL, 'sans personne à ajouter, « Ajouter une personne » reste');

	}

	/**
	 * La tête de la semaine, celle du planning : les flèches vers les semaines voisines, la semaine, qui ouvre
	 * le calendrier des semaines (`FormUi::weekSelector()`), et « Aujourd'hui » hors de la semaine en cours, qui y ramène.
	 * Chaque geste passe par `taskDetail:week` avec `weekShown`, sans changer l'adresse de la page, et rien ne
	 * mène au-delà de la semaine en cours. Tout le monde l'a, Bruno au planning limité compris : elle ne fait
	 * que montrer une semaine.
	 */
	public function testNav(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$week = \Clock::week();
		$weekBefore = toWeek(\Clock::date().' - 7 DAYS');

		$document = $this->renderDetail($eTask);
		$nav = $document->getElementById('planning-task-detail-nav');

		if($this->assertTrue($nav !== NULL, 'la tête de la semaine n\'est pas posée') === FALSE) {
			return;
		}

		$this->assertEquals(TaskDetailUi::urlWeek($eTask, $week, $weekBefore), $nav->querySelector('.btn:first-child')?->getAttribute('data-ajax'), 'la flèche précédente ne mène pas à la semaine d\'avant');
		$this->assertTrue($nav->querySelector('[data-ajax="'.TaskDetailUi::urlWeek($eTask, $week, toWeek(\Clock::date().' + 7 DAYS')).'"]') === NULL, 'la flèche suivante mène au-delà de la semaine en cours');
		$this->assertTrue(str_contains($nav->querySelector('.field-week-selector-bubble[data-week="'.$weekBefore.'"]')?->getAttribute('onclick') ?? '', 'PlanningTaskDetail.week(\''.$weekBefore.'\')'), 'le calendrier ne montre pas la semaine choisie par `PlanningTaskDetail.week()`');

		$nav = $this->renderDetail($eTask, ['weekShown' => $weekBefore])->getElementById('planning-task-detail-nav');

		$this->assertEquals(TaskDetailUi::urlWeek($eTask, $week, $week), $nav?->querySelector('.planning-detail-time-nav-today')?->getAttribute('data-ajax'), '« Aujourd\'hui » ne ramène pas à la semaine en cours');
		$this->assertTrue($nav?->querySelector('.btn[data-ajax="'.TaskDetailUi::urlWeek($eTask, $week, $week).'"]:not(.planning-detail-time-nav-today)') !== NULL, 'la flèche suivante ne mène pas à la semaine d\'après');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		$this->assertTrue($this->renderDetail($eTask)->getElementById('planning-task-detail-nav') !== NULL, 'Bruno, au planning limité, n\'a pas la tête de la semaine');

	}

	/**
	 * `taskDetail:week` montre une autre semaine sans rien écrire : la réponse ne redessine que la tête et le
	 * tableau, qui porte la semaine et les cases de ses jours. Une semaine à venir, que le calendrier propose,
	 * montre la semaine en cours ; une semaine illisible, vide, absente, que son année n'a pas ou d'avant la
	 * première saison du calendrier de la ferme est refusée, et la page ouverte sur elle montre sa semaine
	 * d'ouverture. Les lignes ajoutées à la main ne suivent pas.
	 */
	public function testWeekRoute(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTaskMondayWednesday();

		$weekWorked = toWeek(\Clock::date().' - 14 DAYS');
		$this->createTimesheet($eTask, 0.5, week_date_day($weekWorked, 2));
		$this->createTimesheet($eTask, 0.25, week_date_day($weekWorked, 5));

		$weekEmpty = toWeek(\Clock::date().' - 7 DAYS');

		foreach([
			'semaine en cours' => [\Clock::week(), \Clock::week()],
			'semaine saisie' => [$weekWorked, $weekWorked],
			'semaine vide' => [$weekEmpty, $weekEmpty],
			'semaine à venir' => [toWeek(\Clock::date().' + 7 DAYS'), \Clock::week()],
		] as $case => [$weekShown, $weekExpected]) {

			$action = $this->callDetailWeek($eTask, $weekShown, ['added' => (string)$eUserOther['id']]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la semaine est refusée') === FALSE) {
				continue;
			}

			[, $template] = $this->runView('week', $action, 'taskDetail');

			$html = [];

			foreach($template->formatInstructions() as $instruction) {
				$html[$instruction[1]] = $instruction[2][0][1][0];
			}

			$this->assertEquals(['#planning-task-detail-nav', '#planning-task-detail-grid'], array_keys($html), $case.' : la réponse ne redessine pas la tête et le tableau, et eux seuls');

			$document = $this->parseHtml(implode('', $html));
			$grid = $document->getElementById('planning-task-detail-grid');

			$this->assertEquals([$weekExpected, ''], [$this->getWeekShown($grid), $grid?->getAttribute('data-added')], $case.' : le tableau ne porte pas la semaine, ou garde une ligne ajoutée à la main');
			// La ligne de la personne connectée tient même sur une semaine sans temps : ses cases sont toujours là.
			$this->assertEquals(week_dates($weekExpected), $this->getFieldDates($document), $case.' : les cases ne sont pas celles des jours de la semaine');

		}

		[, $template] = $this->runView('week', $this->callDetailWeek($eTask, $weekWorked), 'taskDetail');

		$grid = $this->parseHtml($template->formatInstructions()[1][2][0][1][0]);

		$this->assertEquals('0h15', $this->readStepped($grid->querySelector('#planning-task-detail-cell-'.$this->getMember()['id'].'-'.week_date_day($weekWorked, 5).' .planning-duration-step')), 'la case ne porte pas le temps de son jour dans la semaine montrée');

		foreach([
			'semaine illisible' => '2026-W99',
			'semaine 53 d\'une année de 52' => '2025-W53',
			'semaine d\'avant la première saison' => ($this->eStructure->conf(\map\Configuration::class)->getFirstValidSeason() - 1).'-W20',
			'semaine vide' => '',
			'sans semaine' => NULL,
		] as $case => $weekShown) {

			$this->assertTrue($this->callDetailWeek($eTask, $weekShown) instanceof \NotExpectedAction, $case.' : la semaine est acceptée');

			$this->assertEquals(
				$this->getWeekShown($this->renderDetail($eTask)->getElementById('planning-task-detail-grid')),
				$this->getWeekShown($this->renderDetail($eTask, ['weekShown' => $weekShown])->getElementById('planning-task-detail-grid')),
				$case.' : la page ne s\'ouvre pas sur sa semaine d\'ouverture'
			);

		}

	}

	/**
	 * `taskDetail:user` ajoute la ligne d'une personne sans rien écrire : la réponse redessine le tableau, qui
	 * porte la ligne et la retient (`data-added`), et un rechargement l'oublie. Une personne qui a déjà sa
	 * ligne, ajoutée ou non, un ancien membre ou un inconnu sont refusés. La saisie d'une autre personne garde
	 * la ligne ajoutée, que l'écran réémet ; le premier temps de la personne ajoutée retient sa ligne, qui
	 * quitte alors la liste, et le tableau se redessine pour le dire.
	 */
	public function testUserRoute(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTask, 2.0, $this->getMonday());

		$action = $this->callDetailUser($eTask, $eUserOther);

		if($this->assertTrue($action instanceof \ViewAction, 'l\'ajout d\'une personne est refusé') === FALSE) {
			return;
		}

		[, $template] = $this->runView('user', $action, 'taskDetail');

		$instructions = $template->formatInstructions();

		$this->assertEquals(['#planning-task-detail-grid'], array_map(fn(array $instruction) => $instruction[1], $instructions), 'la réponse ne redessine pas le tableau, et lui seul');

		$document = $this->parseHtml($instructions[0][2][0][1][0]);

		$this->assertTrue($document->getElementById('planning-task-detail-user-'.$eUserOther['id']) !== NULL, 'la personne ajoutée n\'a pas sa ligne');
		$this->assertEquals((string)$eUserOther['id'], $document->getElementById('planning-task-detail-grid')?->getAttribute('data-added'), 'le tableau ne retient pas la ligne ajoutée');
		$this->assertEquals([[], FALSE], [$this->getTimesByDate($eTask, $eUserOther), in_array($eUserOther['id'], \series\TaskLib::getById($eTask['id'])['plannedUsers'])], 'l\'ajout d\'une ligne écrit un temps ou une affectation');
		$this->assertTrue($this->renderDetail($eTask)->getElementById('planning-task-detail-user-'.$eUserOther['id']) === NULL, 'un rechargement garde une ligne que rien n\'a écrite');

		foreach([
			'personne déjà ajoutée' => [$eUserOther, [$eUserOther['id']]],
			'personne qui a du temps' => [$this->getMember(), []],
			'ancien membre' => [$this->createUserFormer(), []],
			'inconnu' => [new \user\User(['id' => 0]), []],
		] as $case => [$eUser, $userIdsAdded]) {
			$this->assertTrue($this->callDetailUser($eTask, $eUser, $userIdsAdded) instanceof \NotExpectedAction, $case.' : l\'ajout est accepté');
		}

		$action = $this->callTime($eTask, $this->getMember(), '1', $this->getTuesday(), TRUE, ['added' => (string)$eUserOther['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doTime', $action, 'taskDetail');

		$selectors = array_map(fn(array $instruction) => $instruction[1], $template->formatInstructions());

		$this->assertTrue(in_array('#planning-task-detail-cell-'.$this->getMember()['id'].'-'.$this->getTuesday(), $selectors) and in_array('#planning-task-detail-grid', $selectors) === FALSE, 'la saisie d\'une autre personne redessine le tableau, donc la ligne ajoutée : '.implode(', ', $selectors));

		$action = $this->callTime($eTask, $eUserOther, '0.5', $this->getTuesday(), TRUE, ['added' => (string)$eUserOther['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie de la personne ajoutée est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doTime', $action, 'taskDetail');

		$html = [];

		foreach($template->formatInstructions() as $instruction) {
			if($instruction[0] === 'qs') {
				$html[$instruction[1]] = $instruction[2][0][1][0];
			}
		}

		$grid = $this->parseHtml($html['#planning-task-detail-grid'] ?? '')->getElementById('planning-task-detail-grid');

		$this->assertEquals('', $grid?->getAttribute('data-added') ?? '-', 'une ligne que son premier temps retient reste dans la liste des lignes ajoutées');
		$this->assertTrue($grid?->querySelector('#planning-task-detail-user-'.$eUserOther['id']) !== NULL, 'la personne ajoutée perd sa ligne à son premier temps');

	}

	/**
	 * `taskDetail:doUser` affecte une personne ou lui retire l'intervention, par l'écriture du planning actuel
	 * (`TaskLib::updateUser()`). La réponse redessine le nom de la personne, qui porte son état, tant que les
	 * lignes ne bougent pas, sinon le tableau : l'affectation donne sa ligne à qui n'en avait pas, la retirer
	 * la lui reprend. Une intervention faite, une action inconnue, une personne hors
	 * de la structure, une personne que le tableau ne propose pas d'affecter (absente la semaine planifiée) ou
	 * une semaine absente sont refusées sans rien écrire.
	 */
	public function testAssign(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTask, 2.0, $this->getMonday());

		$member = $this->getMember()['id'];
		$other = $eUserOther['id'];

		foreach([
			'affecter qui a sa ligne' => [$this->getMember(), 'add', [$member], '#planning-task-detail-user-'.$member, [$member]],
			'affecter qui n\'a pas de ligne' => [$eUserOther, 'add', [$member, $other], '#planning-task-detail-grid', [$member, $other]],
			'retirer qui n\'a pas de temps' => [$eUserOther, 'delete', [$member], '#planning-task-detail-grid', [$member]],
			'retirer qui a du temps' => [$this->getMember(), 'delete', [], '#planning-task-detail-user-'.$member, [$member]],
		] as $case => [$eUser, $assign, $planned, $selector, $lines]) {

			$action = $this->callDetailAssign($eTask, $eUser, $assign);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : l\'affectation est refusée') === FALSE) {
				return;
			}

			$userIdsPlanned = \series\TaskLib::getById($eTask['id'])['plannedUsers'];
			sort($userIdsPlanned);
			sort($planned);

			$this->assertEquals($planned, $userIdsPlanned, $case.' : les personnes affectées');

			[, $template] = $this->runView('doUser', $action, 'taskDetail');

			$html = [];

			foreach($template->formatInstructions() as $instruction) {
				if($instruction[0] === 'qs' and str_starts_with($instruction[1], '#planning-task-detail-')) {
					$html[$instruction[1]] = $instruction[2][0][1][0];
				}
			}

			$this->assertEquals([$selector], array_keys($html), $case.' : ce que la réponse redessine dans le bloc');

			$document = $this->parseHtml(implode('', $html));

			$state = $document->querySelector('#planning-task-detail-user-'.$eUser['id'].' [data-planning-detail-assign]');

			if($selector === '#planning-task-detail-grid') {

				$userIdsLines = [];

				foreach($document->querySelectorAll('.planning-detail-time-user') as $person) {
					$userIdsLines[] = (int)$person->getAttribute('data-user');
				}

				sort($userIdsLines);
				sort($lines);

				$this->assertEquals($lines, $userIdsLines, $case.' : les lignes du tableau');

			}

			if(in_array($eUser['id'], $lines)) {
				$this->assertEquals(($assign === 'add') ? 'delete' : 'add', $state?->getAttribute('data-planning-detail-assign'), $case.' : le nom ne propose pas le geste inverse');

				// Ouvert, le menu de la vignette vit sous `body` : le geste ne retrouve sa personne et son tableau que par lui.
				$menu = $document->querySelector('#planning-task-detail-user-'.$eUser['id'].' .planning-detail-team-menu');

				$this->assertEquals(
					[(string)$eUser['id'], 'planning-task-detail-grid', ($assign === 'add') ? 'delete' : 'add'],
					[$menu?->getAttribute('data-user'), $menu?->getAttribute('data-grid'), $menu?->querySelector('[data-planning-detail-assign]')?->getAttribute('data-planning-detail-assign')],
					$case.' : le menu de la vignette ne nomme pas sa personne, son tableau et le geste inverse'
				);

			}

		}

		foreach([
			'action inconnue' => ['action' => 'toggle'],
			'sans semaine' => ['weekShown' => NULL],
		] as $case => $input) {
			$this->assertTrue($this->callDetailAssign($eTask, $eUserOther, 'add', $input) instanceof \NotExpectedAction, $case.' : l\'affectation est acceptée');
		}

		$this->assertTrue($this->callDetailAssign($eTask, $this->createUserFormer(), 'add') instanceof \NotExpectedAction, 'une personne hors de la structure est affectée');

		// Le menu d'un jour est lui-même un menu, que `Lime.Dropdown` ne sait pas empiler : ses personnes n'ouvrent
		// pas celui de leur vignette.
		$action = $this->executePage('planning/taskWeek:time', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'task' => $eTask['id'],
			'date' => $this->getMonday(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le menu du jour ne s\'ouvre pas : '.implode(', ', $this->getFails($action)))) {

			[, $template] = $this->runView('time', $action, 'taskWeek');

			$html = '';
			$instructions = $template->formatInstructions();

			array_walk_recursive($instructions, function($value) use (&$html) {
				if(is_string($value)) {
					$html .= $value;
				}
			});

			$document = $this->parseHtml($html);

			$this->assertTrue($document->querySelector('.planning-detail-time-user') !== NULL, 'le menu du jour ne montre pas ses personnes');
			$this->assertTrue($document->querySelector('.planning-detail-team-menu') === NULL, 'une personne du menu du jour ouvre le menu de sa vignette');

		}

		$eTaskDone = $this->createTaskMondayWednesday();

		$this->assertTrue(($this->callDetailAssign($eTaskDone, $eUserOther, 'add') instanceof \ViewAction) === FALSE, 'une intervention faite s\'affecte');

		// Planifiée avant le début de la présence que le décor a posée : personne n'y est affectable.
		$eTaskBefore = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, ($this->getSeason() - 1).'-W20');

		if($this->assertDecorPresent($this->getWeekUsers($eTaskBefore, \Clock::week())[$eUserOther['id']]['canAssign'] === FALSE, 'un membre absent la semaine planifiée') !== FALSE) {
			$this->assertTrue($this->callDetailAssign($eTaskBefore, $eUserOther, 'add') instanceof \NotExpectedAction, 'une personne absente la semaine planifiée est affectée');
		}

		$this->assertEquals([[], [], []], [\series\TaskLib::getById($eTask['id'])['plannedUsers'], \series\TaskLib::getById($eTaskDone['id'])['plannedUsers'], \series\TaskLib::getById($eTaskBefore['id'])['plannedUsers']], 'une affectation refusée écrit');

	}

	/**
	 * Le total de la récolte est la somme des jours de sa table (R-Retours-0710, 156).
	 */
	public function testOrder(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		// Sans `build()` : le décor pose une récolte dont il veut exactement la quantité et l'unité, que le
		// formulaire du planning actuel calculerait à partir de sa saisie. Elle passe par `TaskLib::update()`,
		// comme ce formulaire, qui écrit le jour et porte le total sur l'intervention (`Task::$harvest`). Un
		// second jour fait du total une somme, et le compteur s'en écarte d'un dixième (15.6 pour 15.5) : il est
		// en `float32` et s'incrémente à chaque saisie, donc il dérive, et le chiffre doit suivre les jours.
		$other = (week_date_day(\Clock::week(), 1) === \Clock::date()) ? week_date_day(\Clock::week(), 2) : week_date_day(\Clock::week(), 1);

		foreach([[\Clock::date(), 12.0, 12.0], [$other, 3.5, 15.6]] as [$date, $quantity, $total]) {

			$eTask['harvest'] = $total;
			$eTask['harvestUnit'] = \series\Task::KG;
			$eTask['eHarvest'] = \series\HarvestLib::getElementFromTask($eTask, $date, $quantity);
			$eTask['eHarvest']['unit'] = \series\Harvest::KG;

			\series\TaskLib::update($eTask, ['harvest', 'harvestUnit']);

		}

		$data = $this->getPanelData(\series\TaskLib::getById($eTask['id']));

		if($data === NULL) {
			return;
		}

		$this->assertEquals(15.5, round($data->eTask['harvestTotal'], 1), 'le total de la récolte n\'est pas la somme des deux jours de la table');

	}

	/**
	 * La réponse ciblée d'une saisie, appliquée à la page, rend le total d'un rechargement.
	 */
	public function testTotalMatchesReload(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$document = $this->renderDetail($eTask);

		$action = $this->callTime($eTask, $this->getMember(), '1.5', $this->getWednesday());

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doTime', $action, 'taskDetail');

		$instructions = $template->formatInstructions();
		$html = NULL;

		foreach($instructions as $instruction) {
			if($instruction[0] === 'qs' and $instruction[1] === '#planning-task-detail-total') {
				$html = $instruction[2][0][1][0];
			}
		}

		if($this->assertTrue($html !== NULL, 'la réponse ne redessine pas le total') === FALSE) {
			return;
		}

		$reload = $this->renderDetail($eTask);

		$response = $this->parseHtml($html);

		$this->assertEquals($reload->saveHtml($reload->getElementById('planning-task-detail-total')), $response->saveHtml($response->getElementById('planning-task-detail-total')), 'le total de la réponse diffère de celui d\'un rechargement');

	}

	/**
	 * Dans la case d'une personne qui peut écrire son temps, la durée qui ouvre la fenêtre de son champ par pas,
	 * fermée, avec « Enregistrer » ; une personne en lecture seule n'en a pas.
	 */
	public function testStepWindow(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserFormer = $this->createUserFormer();

		$eTask = $this->createTaskMondayWednesday();
		$this->createTimesheetFor($eTask, $eUserFormer, 2.25, $this->getMonday());

		$document = $this->renderDetail($eTask);

		$window = $document->querySelector('#planning-task-detail-cell-'.$this->getMember()['id'].'-'.$this->getMonday().' .planning-duration-step-window');

		if($this->assertTrue($window !== NULL, 'la case de la personne connectée n\'a pas la fenêtre que sa durée ouvre') === FALSE) {
			return;
		}

		$this->assertTrue($window->querySelector('.planning-duration-step') !== NULL and $window->querySelector('[data-planning-duration-step-save]') !== NULL, 'la fenêtre n\'a pas le champ par pas et « Enregistrer »');
		$this->assertFalse($window->classList->contains('is-open'), 'la fenêtre est ouverte avant qu\'on touche la durée');
		$this->assertTrue($document->querySelector('.planning-detail-time-field[data-user="'.$eUserFormer['id'].'"] .planning-duration-step-window') === NULL, 'la ligne en lecture seule a une fenêtre');

	}

	/**
	 * La fenêtre reste ouverte après « Enregistrer » (retour 396) : la réponse redessine la case de la personne,
	 * et le client y rouvre la fenêtre par la clé de son champ (`PlanningDurationStep.resume()`). La case
	 * redessinée porte donc le champ sous la clé que la page lui donnait, avec la durée écrite comme dernière
	 * durée envoyée, et la réponse ne retire aucune fenêtre.
	 */
	public function testStepWindowKey(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();
		$cell = '#planning-task-detail-cell-'.$this->getMember()['id'].'-'.$this->getWednesday();

		$document = $this->renderDetail($eTask);
		$key = $document->querySelector($cell.' .planning-duration-step')?->getAttribute('data-duration');

		if($this->assertDecorPresent($key !== NULL, 'la case du mercredi de la personne connectée porte un champ par pas') === FALSE) {
			return;
		}

		$action = $this->callTime($eTask, $this->getMember(), '1.5', $this->getWednesday());

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doTime', $action, 'taskDetail');

		$html = NULL;
		$removed = [];

		foreach($template->formatInstructions() as $instruction) {

			if($instruction[0] !== 'qs' and $instruction[0] !== 'qsa') {
				continue;
			}

			foreach($instruction[2] as [$method, $arguments]) {

				if($method === 'outerHtml' and $instruction[1] === $cell) {
					$html = $arguments[0];
				} else if($method === 'remove') {
					$removed[] = $instruction[1];
				}

			}

		}

		if($this->assertTrue($html !== NULL, 'la réponse ne redessine pas la case du jour écrit') === FALSE) {
			return;
		}

		$field = $this->parseHtml($html)->querySelector('.planning-duration-step');

		$this->assertEquals($key, $field?->getAttribute('data-duration'), 'la case redessinée ne porte pas le champ sous la clé de la page : la fenêtre ne s\'y rouvre pas');
		$this->assertEquals('1.5', $field?->getAttribute('data-sent'), 'la case redessinée ne retient pas la durée écrite');
		$this->assertEquals([], array_filter($removed, fn(string $selector) => str_contains($selector, 'duration')), 'la réponse retire une fenêtre des pas');

	}

	/**
	 * La page ouverte seule (rechargée, nouvel onglet, lien) ne porte pas de semaine : la saisie écrit et la
	 * réponse redessine le total, les jours saisis, la case du jour écrit, la somme de la personne et le pied,
	 * sans le reste du tableau, que la saisie ne change pas, ni carte ni bandeau, qu'aucun écran ne montre derrière elle. Sans la semaine, la vue passait `NULL` au
	 * bandeau et plantait après l'écriture (contre-recette R-Saisie).
	 * Le navigateur poste `week=""` et non une requête sans la clé : les deux finissent à `NULL`, mais l'absence
	 * est acceptée telle quelle par `?week`, quand `""` est refusé par `Filter::check()` puis remplacé par le défaut.
	 */
	public function testTimeWithoutWeek(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$cases = [
			'sans semaine' => [],
			'semaine vide' => ['week' => ''],
		];

		foreach($cases as $case => $input) {

			$eTask = $this->createTaskMondayWednesday();

			$action = $this->callTime($eTask, $this->getMember(), '1.75', $this->getWednesday(), FALSE, $input);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$this->assertEquals(3.75, $this->getTaskTime($eTask), $case.' : la saisie n\'écrit pas le jour');

			[, $template] = $this->runView('doTime', $action, 'taskDetail');

			$selectors = [];

			foreach($template->formatInstructions() as $instruction) {
				if($instruction[0] === 'qs') {
					$selectors[] = $instruction[1];
				}
			}

			$this->assertTrue(in_array('#planning-task-detail-total', $selectors), $case.' : la réponse ne redessine pas le total : '.implode(', ', $selectors));
			$this->assertTrue(in_array('#planning-detail-history', $selectors), $case.' : la réponse ne redessine pas l\'historique : '.implode(', ', $selectors));

			$this->assertEquals([
				'#planning-task-detail-total',
				'#planning-task-detail-worked',
				'#planning-task-detail-cell-'.$this->getMember()['id'].'-'.$this->getWednesday(),
				'#planning-task-detail-sum-'.$this->getMember()['id'],
				'#planning-task-detail-foot',
				'#planning-detail-history',
			], $selectors, $case.' : la réponse ne redessine pas le total, les jours saisis, la case écrite, la somme de la personne, le pied et l\'historique, et eux seuls');

		}

	}

	/**
	 * Une saisie hors de la semaine montrée, que l'écran ne propose pas mais qu'un POST peut porter, écrit son
	 * jour sans rien redessiner du tableau, qui n'a pas de case pour lui : les jours saisis le gagnent, le plus
	 * ancien en dernier, avec son lien vers sa semaine.
	 */
	public function testTimeOutsideWeek(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		$dateFirst = week_date_day(toWeek(\Clock::date().' - 7 DAYS'), 3);

		$action = $this->callTime($eTask, $this->getMember(), '0.5', $dateFirst, TRUE, ['weekShown' => \Clock::week()]);

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$dateFirst => 0.5, $this->getMonday() => 2.0, $this->getWednesday() => 1.0], $this->getTimesByDate($eTask, $this->getMember()), 'la saisie n\'écrit pas son jour, et lui seul');

		[, $template] = $this->runView('doTime', $action, 'taskDetail');

		$html = [];

		foreach($template->formatInstructions() as $instruction) {
			if($instruction[0] === 'qs') {
				$html[$instruction[1]] = $instruction[2][0][1][0];
			}
		}

		$this->assertEquals([], array_values(array_filter(array_keys($html), fn(string $selector) => str_contains($selector, '-cell-') or str_contains($selector, '-sum-') or in_array($selector, ['#planning-task-detail-grid', '#planning-task-detail-foot']))), 'un jour hors de la semaine montrée redessine le tableau');

		$worked = $this->parseHtml($html['#planning-task-detail-worked'] ?? '');

		$rows = $worked->querySelectorAll('#planning-task-detail-worked > .util-table > .util-table-row');

		$this->assertEquals(TaskDetailUi::urlWeek($eTask, \Clock::week(), toWeek($dateFirst), $dateFirst), $rows->item($rows->length - 1)?->querySelector('a[data-ajax]')?->getAttribute('data-ajax'), 'le jour écrit, le plus ancien, ne ferme pas les jours saisis, ou ne mène pas à sa semaine');

	}

	/**
	 * Un jour saisi du dépli nomme son jour à `taskDetail:week` : la réponse amène le tableau à l'écran et
	 * éclaire la colonne de ce jour, même quand sa semaine est déjà celle du tableau. Un jour hors de la semaine
	 * montrée n'éclaire rien.
	 */
	public function testWeekDay(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();

		foreach([
			'jour de la semaine montrée' => [$this->getWednesday(), TRUE],
			'jour d\'une autre semaine' => [week_date_day(toWeek(\Clock::date().' - 14 DAYS'), 2), FALSE],
			'jour illisible' => ['2026-13-45', FALSE],
		] as $case => [$day, $shown]) {

			[, $template] = $this->runView('week', $this->callDetailWeek($eTask, \Clock::week(), ['day' => $day]), 'taskDetail');

			$this->assertEquals($shown ? [
				['qs', '#planning-task-detail-grid', [['scrollTo', ['center', 'smooth']]]],
				['js', [['highlight', [['#planning-task-detail-grid [data-date="'.$day.'"]']]]]]
			] : [], array_slice($template->formatInstructions(), 2), $case.' : le tableau n\'est pas amené à l\'écran avec la colonne du jour éclairée, ou l\'est à tort');

		}

	}

	/**
	 * Le total du temps se clique et déplie les jours saisis (R-Retours-0710, 157), du plus récent au plus
	 * ancien, chacun avec la durée du jour quand au moins deux personnes y ont travaillé, lus sur les personnes que la page a déjà chargées (`TaskDetailLib::getTimesByDay()`) : la
	 * date mène à la semaine de son jour dans le bloc (`taskDetail:week`), et y montre ce jour.
	 */
	public function testWorked(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$eTask = $this->createTaskMondayWednesday();
		$this->createTimesheetFor($eTask, $eUserOther, 0.5, $this->getMonday());

		$cUser = $this->getWeekUsers($eTask, \Clock::week());

		$this->assertEquals([
			$this->getWednesday() => [$this->getMember()['id'] => 1.0],
			$this->getMonday() => [$this->getMember()['id'] => 2.0, $eUserOther['id'] => 0.5],
		], TaskDetailLib::getTimesByDay($cUser), 'les jours saisis ne vont pas du plus récent au plus ancien, avec le temps de chaque personne');

		$document = $this->renderDetail($eTask);

		$block = $document->getElementById('planning-task-detail-time');
		$figure = $block?->querySelector('a#planning-task-detail-total.util-fold-head');

		if($this->assertTrue($figure !== NULL, 'le total du temps n\'est pas un lien') === FALSE) {
			return;
		}

		$this->assertTrue($block->classList->contains('util-fold') and $block->classList->contains('util-fold-open') === FALSE, 'le bloc ne se replie pas, ou s\'ouvre déplié');
		$this->assertTrue(str_contains($figure->getAttribute('onclick') ?? '', 'util-fold-open'), 'le total ne déplie pas les jours saisis');

		$rows = $document->querySelectorAll('#planning-task-detail-worked > .util-table > .util-table-row');

		if($this->assertTrue($rows->item(1) !== NULL, 'les jours saisis n\'ont pas leurs deux jours') === FALSE) {
			return;
		}

		$this->assertEquals(TaskDetailUi::urlWeek($eTask, \Clock::week(), \Clock::week(), $this->getWednesday()), $rows->item(0)->querySelector('a[data-ajax-method="get"]')?->getAttribute('data-ajax'), 'le jour le plus récent ne mène pas à sa semaine, ou ne s\'y montre pas');
		$this->assertEquals('2h30', trim($rows->item(1)->querySelector('.badge-time-spent')?->textContent ?? ''), 'lundi ne dit pas son temps, toutes personnes');

	}

	/**
	 * L'Historique que prépare la page (`TaskDetailLib::getHistory()`) : la création, chaque écart de temps et
	 * de récolte avec sa quantité (R-Retours-0710, 160) et chaque commentaire, la création en dernier.
	 */
	public function testHistory(): void {

		if($this->requireTime() === FALSE) {
			return;
		}

		$eTask = $this->createTaskHarvest(week: \Clock::week(), date: \Clock::date());
		$this->createHarvest($eTask, 12.0, \Clock::date());

		$action = $this->callTime($eTask, $this->getMember(), '1.25', \Clock::date());

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie du décor est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		// Sans `build()` : seule l'existence du commentaire compte ici, pas le contrôle de son texte.
		\series\CommentLib::create(new \series\Comment(['task' => $eTask, 'text' => 'Commentaire du décor']));

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/intervention/'.$eTask['id'], ['week' => \Clock::week()], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la page de l\'intervention ne se rend pas') === FALSE) {
			return;
		}

		$history = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->history;

		$events = array_column($history, 'event');

		$this->assertEquals(TaskDetailLib::HISTORY_CREATED, end($events), 'la création n\'est pas la plus ancienne ligne de l\'Historique');

		$quantities = [];

		foreach($history as $event) {
			$quantities[$event['event']] = match($event['event']) {
				\series\History::TIME => [$event['element']['worker']['id'], $event['element']['day'], round($event['element']['time'], 2)],
				\series\History::HARVEST => [$event['element']['day'], round($event['element']['harvest'], 1), $event['element']['harvestUnit']],
				TaskDetailLib::HISTORY_CREATED, TaskDetailLib::HISTORY_COMMENT, \series\History::DONE, \series\History::TODO => NULL,
			};
		}

		ksort($quantities);

		$this->assertEquals([
			TaskDetailLib::HISTORY_COMMENT => NULL,
			TaskDetailLib::HISTORY_CREATED => NULL,
			\series\History::HARVEST => [\Clock::date(), 12.0, \series\Task::KG],
			\series\History::TIME => [$this->getMember()['id'], \Clock::date(), 1.25],
		], $quantities, 'l\'Historique ne porte pas la création, le temps, la récolte avec leur quantité et le commentaire');

	}

	/**
	 * La récolte s'ajoute par une ligne de sa table (R-Retours-0710, 155), qui s'ouvre en ligne de saisie :
	 * le jour, la quantité, et l'unité tant que l'intervention n'en a pas. `doHarvest` écrit comme le
	 * formulaire du planning actuel (`TaskLib::buildHarvests()`), sans le stock, et redessine le bloc, l'Historique
	 * et la carte de la semaine.
	 */
	public function testHarvestAdd(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$document = $this->renderDetail($eTask);
		$harvest = $document->getElementById('planning-detail-harvest');

		if($this->assertTrue($harvest !== NULL, 'la récolte de la fiche n\'a pas son identifiant, qu\'une saisie ne pourrait pas redessiner') === FALSE) {
			return;
		}

		$this->assertTrue($harvest->querySelector('.util-procedure-step-open [data-planning-detail-harvest]') !== NULL, 'la frise ne finit pas sur l\'ajout');
		$this->assertTrue($harvest->querySelector('form[data-ajax-form="/planning/taskDetail:doHarvest"]') !== NULL, 'la ligne de saisie n\'est pas là');
		$this->assertTrue($harvest->querySelector('form[data-ajax-form="/planning/taskDetail:doHarvest"] select[name="harvestUnit"]') !== NULL, 'sans unité, la ligne de saisie ne la demande pas');

		$action = $this->callHarvest($eTask, '2.5', \Clock::date(), \series\Task::KG);

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 2.5], $this->getHarvestDates($eTask), 'la récolte ne s\'écrit pas sur son jour');

		[, $template] = $this->runView('doHarvest', $action, 'taskDetail');

		$targets = array_column(array_filter($template->formatInstructions(), fn(array $instruction) => $instruction[0] === 'qs'), 1);

		$this->assertEquals(['#planning-detail-harvest', '#planning-detail-history', '#planning-task-'.$eTask['id']], $targets, 'la réponse ne redessine pas le bloc, l\'Historique et la carte de la semaine, et eux seuls');

		$action = $this->callHarvest($eTask, '1.5', \Clock::date());

		$this->assertTrue($action instanceof \ViewAction, 'une seconde récolte, sans unité postée, est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => 4.0], $this->getHarvestDates($eTask), 'la seconde récolte ne s\'ajoute pas à la première');

		$entry = $this->renderDetail($eTask)->querySelector('.util-procedure-step-open form[data-ajax-form="/planning/taskDetail:doHarvest"]');

		$this->assertTrue($entry?->querySelector('select[name="harvestUnit"]') === NULL, 'avec une unité, la ligne de saisie la redemande');

	}

	/**
	 * Une quantité absente ou nulle est refusée par `harvestMore.missing`, une baisse qui rendrait la récolte négative
	 * par l'étape du schéma, une intervention qui n'est pas une récolte ou d'une autre structure sans
	 * explication : l'écran n'en propose aucune. Rien ne s'écrit.
	 */
	public function testHarvestRefused(): void {

		$eTask = $this->createTaskHarvest(week: \Clock::week());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$this->assertEquals(['Task::harvestMore.missing'], $this->getFails($this->callHarvest($eTask, '', \Clock::date())), 'une quantité absente n\'est pas refusée par harvestMore.missing');
		$this->assertEquals(['Task::harvestMore.missing'], $this->getFails($this->callHarvest($eTask, '0', \Clock::date())), 'une quantité nulle n\'est pas refusée par harvestMore.missing');
		$this->assertEquals(['Task::harvestMore.missing'], $this->getFails($this->callHarvest($eTask, '0,0', \Clock::date())), 'un zéro écrit avec une virgule n\'est pas refusé par harvestMore.missing');
		$this->assertEquals(['Task::harvestMore.negative'], $this->getFails($this->callHarvest($eTask, '-3', \Clock::date())), 'une baisse sous zéro n\'est pas refusée par le schéma');
		$this->assertEquals([\Clock::date() => 2.0], $this->getHarvestDates($eTask), 'une quantité refusée change la récolte');

		$eTaskPlantation = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$this->assertTrue(($this->callHarvest($eTaskPlantation, '1', \Clock::date(), \series\Task::KG) instanceof \ViewAction) === FALSE, 'une intervention qui n\'est pas une récolte reçoit une récolte');

		$eTaskForeign = $this->createTaskForeign();

		$this->assertTrue(($this->callHarvest($eTaskForeign, '1', \Clock::date(), \series\Task::KG) instanceof \ViewAction) === FALSE, 'une intervention d\'une autre structure reçoit une récolte');

	}

	/**
	 * La quantité d'un jour de la frise ouvre sa modification dans son étape (R-Retours-0810, 207), qui poste
	 * son jour et la remplace. La suppression est dans la modification, plus dans la ligne. Les jours se suivent
	 * du plus ancien au plus récent (HK).
	 */
	public function testHarvestRows(): void {

		$eTask = $this->createTaskHarvest(week: \Clock::week());
		$this->createHarvest($eTask, 2.0, $this->getMonday());
		$this->createHarvest($eTask, 3.5, $this->getWednesday());

		$document = $this->renderDetail($eTask);
		$days = $document->querySelector('#planning-detail-harvest .planning-detail-harvest-days');

		if($this->assertTrue($days !== NULL, 'la récolte de la fiche n\'a pas sa frise') === FALSE) {
			return;
		}

		$this->assertTrue($days->querySelector('[href*="doUpdateHarvestCollection"], [data-ajax*="doUpdateHarvestCollection"]') === NULL, 'une ligne de récolte a encore sa corbeille');

		$steps = $document->querySelectorAll('#planning-detail-harvest .planning-detail-harvest-days > .util-procedure-step:not(.util-procedure-step-open)');

		if($this->assertTrue($steps->item(1) !== NULL, 'la frise n\'a pas ses deux jours') === FALSE) {
			return;
		}

		foreach([[$steps->item(0), $this->getMonday(), '2'], [$steps->item(1), $this->getWednesday(), '3.5']] as [$step, $date, $value]) {

			$quick = $step->querySelector('.planning-detail-harvest-line [data-planning-detail-harvest]');

			if($this->assertTrue($quick !== NULL, 'la quantité du '.$date.' n\'ouvre pas sa modification') === FALSE) {
				continue;
			}

			$this->assertTrue(str_starts_with(trim($quick->textContent), $value), 'la quantité du '.$date.' n\'est pas la sienne');

			$entry = $step->querySelector('form[data-ajax-form="/planning/taskDetail:doHarvest"]');

			if($this->assertTrue($entry !== NULL, 'la modification du '.$date.' n\'est pas dans son étape') === FALSE) {
				continue;
			}

			$this->assertEquals('replace', $entry->querySelector('input[type="hidden"][name="mode"]')?->getAttribute('value'), 'la modification du '.$date.' ne remplace pas');
			$this->assertEquals($date, $entry->querySelector('input[type="hidden"][name="harvestDate"]')?->getAttribute('value'), 'la modification du '.$date.' ne poste pas son jour');
			$this->assertTrue(str_starts_with($entry->querySelector('input[name="harvestDay"]')?->getAttribute('value') ?? '', $value), 'la modification du '.$date.' n\'est pas pré-remplie de sa quantité');

			$delete = $entry->querySelector('.util-table-add-actions [data-ajax="/planning/taskDetail:doHarvest"][data-confirm]');

			if($this->assertTrue($delete !== NULL, 'la modification du '.$date.' n\'a pas sa suppression confirmée') === FALSE) {
				continue;
			}

			$this->assertEquals(['replace', $date, '0'], [$delete->getAttribute('post-mode'), $delete->getAttribute('post-harvest-date'), $delete->getAttribute('post-harvest-day')], 'la suppression du '.$date.' ne remet pas son jour à zéro');

		}

	}

	/**
	 * La modification remplace la quantité d'un jour (`harvestDay`) : la page écrit l'écart avec ce que le jour
	 * porte, par la même écriture que l'ajout. La même quantité n'écrit rien, zéro vide le jour, une quantité
	 * négative est refusée par le schéma.
	 */
	public function testHarvestReplace(): void {

		$eTask = $this->createTaskHarvest(week: \Clock::week());
		$this->createHarvest($eTask, 2.0, $this->getMonday());
		$this->createHarvest($eTask, 3.5, $this->getWednesday());

		$action = $this->callHarvestDay($eTask, '5', $this->getMonday());

		if($this->assertTrue($action instanceof \ViewAction, 'le remplacement est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$this->getMonday() => 5.0, $this->getWednesday() => 3.5], $this->getHarvestDates($eTask), 'le remplacement n\'écrit pas le seul lundi');
		$this->assertEquals(8.5, \series\TaskLib::getById($eTask['id'])['harvest'], 'le total de l\'intervention ne suit pas le remplacement');

		[, $template] = $this->runView('doHarvest', $action, 'taskDetail');

		$targets = array_column(array_filter($template->formatInstructions(), fn(array $instruction) => $instruction[0] === 'qs'), 1);

		$this->assertEquals(['#planning-detail-harvest', '#planning-detail-history', '#planning-task-'.$eTask['id']], $targets, 'la réponse du remplacement ne redessine pas le bloc, l\'Historique et la carte, et eux seuls');

		$this->assertTrue($this->callHarvestDay($eTask, '5', $this->getMonday()) instanceof \ViewAction, 'la même quantité est refusée');
		$this->assertEquals([$this->getMonday() => 5.0, $this->getWednesday() => 3.5], $this->getHarvestDates($eTask), 'la même quantité change la récolte');

		$this->assertEquals(['Task::harvestDay.negative'], $this->getFails($this->callHarvestDay($eTask, '-1', $this->getMonday())), 'une quantité négative n\'est pas refusée par harvestDay.negative');
		$this->assertEquals(['Task::harvestDay.negative'], $this->getFails($this->callHarvestDay($eTask, '', $this->getMonday())), 'une quantité absente n\'est pas refusée');
		$this->assertEquals([$this->getMonday() => 5.0, $this->getWednesday() => 3.5], $this->getHarvestDates($eTask), 'une quantité refusée change la récolte');

		foreach(['2026-13-45', $this->getTuesday()] as $date) {
			$this->assertEquals(['Task::harvestDates.check'], $this->getFails($this->callHarvestDay($eTask, '0', $date)), 'le '.$date.', sans récolte, n\'est pas refusé par harvestDates.check');
		}

		$this->assertTrue($this->callHarvestDay($eTask, '0', $this->getMonday()) instanceof \ViewAction, 'la suppression est refusée');
		$this->assertEquals(0.0, $this->getHarvestDates($eTask)[$this->getMonday()] ?? 0.0, 'la suppression ne vide pas le lundi');
		$this->assertEquals(3.5, \series\TaskLib::getById($eTask['id'])['harvest'], 'le total de l\'intervention ne suit pas la suppression');

	}

	/** L'intervention du cas type, faite le mercredi : 2h le lundi et 1h le mercredi pour la personne connectée. */
	private function createTaskMondayWednesday(): \series\Task {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $this->getWednesday());

		$this->createTimesheet($eTask, 2.0, $this->getMonday());
		$this->createTimesheet($eTask, 1.0, $this->getWednesday());

		return \series\TaskLib::getById($eTask['id']);

	}

	private function getMonday(): string {
		return week_date_day(\Clock::week(), 1);
	}

	private function getTuesday(): string {
		return week_date_day(\Clock::week(), 2);
	}

	private function getWednesday(): string {
		return week_date_day(\Clock::week(), 3);
	}

	private function getThursday(): string {
		return week_date_day(\Clock::week(), 4);
	}

	private function requireTime(): bool {

		return $this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le bloc');

	}

	/** Le membre du décor qui ne voit pas le planning, et n'écrit donc aucun temps. */
	private function getMemberObserver(): \user\User {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('decor.f1.observateur@example.org')
			->get();

	}

	private function getMemberOther(): \user\User {

		$cUser = \structure\MemberLib::getUsersByStructure($this->eStructure)->find(fn($eUser) => $eUser->is($this->getMember()) === FALSE);

		return $cUser->empty() ? new \user\User() : $cUser->first();

	}

	private function createTimesheetFor(\series\Task $eTask, \user\User $eUser, float $time, string $date): void {

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
		// le temps d'un autre membre, qu'aucun formulaire ne lui fait saisir ici.
		\series\TimesheetLib::create($eTimesheet);

	}

	/** La saisie d'un jour dans le bloc, avec la semaine d'où la page s'ouvre, ou sans quand la page est ouverte seule. Sans jour ou sans durée (`NULL`), la saisie n'en poste pas. */
	private function callTime(\series\Task $eTask, \user\User $eUser, ?string $time, ?string $date, bool $fromWeek = TRUE, array $input = []): ?\Action {

		$input += [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'user' => $eUser['id'],
		];

		if($time !== NULL) {
			$input += ['time' => $time];
		}

		if($date !== NULL) {
			$input += ['date' => $date];
		}

		if($fromWeek) {
			$input += ['week' => \Clock::week()];
		}

		// La semaine que le tableau montre, que l'écran réémet à chaque geste : celle du jour saisi par défaut.
		$input += ['weekShown' => ($date !== NULL and preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) ? min(toWeek($date), \Clock::week()) : \Clock::week()];

		return $this->executePage('planning/taskDetail:doTime', $input, 'POST');

	}

	/**
	 * Une personne posée par le décor et jamais cherchée en base : le test ne dépend pas des comptes présents
	 * dans la base de test. Créée sans lien de membre, elle est dans l'état d'un ancien membre. Le ROLLBACK
	 * du décor la retire.
	 */
	private function createUserFormer(): \user\User {

		$eUserFormer = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Ancien membre',
			'email' => 'zztest.planning.ancien-membre@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE,
		]);

		$fw = new \FailWatch();

		// Sans `build()`, qui vérifierait le nom, l'adresse et le mot de passe saisis à l'inscription : le décor
		// fabrique un compte jetable dont il fixe chaque valeur, qu'aucun formulaire ne lui fait saisir.
		\user\UserLib::create($eUserFormer);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('le compte de l\'ancien membre est refusé : '.json_encode($fw->get())));

		return $eUserFormer;

	}

	/**
	 * La série ouvre les informations de la fiche, au-dessus de « Planifiée » (retour du 20/10). Une
	 * intervention hors série n'a pas de ligne.
	 */
	public function testSeriesInRecord(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eSeries = \series\SeriesLib::getById($eTask['series']['id']);

		$data = $this->getPanelData($eTask);

		if($data === NULL) {
			return;
		}

		$items = new \series\TaskUi()->getDetailPanelItems($data->eTask, $data->cUser, FALSE);

		$this->assertEquals(['series', 'plannedWeek'], array_keys($items), 'la série n\'ouvre pas les informations de la fiche');
		$this->assertTrue($this->parseHtml($items['series']['value'])->querySelector('a[href="'.\series\SeriesUi::url($eSeries).'"]') !== NULL, 'la ligne de la série ne mène pas à la série');

		// Sur l'élément de la page, sans écrire : une intervention hors série n'a que ce qu'elle porte.
		$data->eTask['series'] = new \series\Series();

		$this->assertEquals(['plannedWeek'], array_keys(new \series\TaskUi()->getDetailPanelItems($data->eTask, $data->cUser, FALSE)), 'une intervention hors série porte une ligne de série');

	}

	/**
	 * La fiche (HF) porte les informations de l'intervention qui ont une valeur, dans l'ordre de la page : la
	 * série, les dates, l'affectation quand le tableau de l'équipe ne la porte pas, la répétition, les intrants, le
	 * matériel, puis la description.
	 */
	public function testRecordItems(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$data = $this->getPanelData($eTask);

		if($data === NULL) {
			return;
		}

		$uiTask = new \series\TaskUi();

		$this->assertEquals(['series', 'plannedWeek'], array_keys($uiTask->getDetailPanelItems($data->eTask, $data->cUser, FALSE)), 'une intervention planifiée sans rien d\'autre ne porte pas que sa date');

		// Sur l'élément de la page, sans écrire : la fiche lit la description telle que l'élément la porte.
		$data->eTask['description'] = 'Pailler avant la pluie';

		$this->assertEquals(['series', 'plannedWeek', 'description'], array_keys($uiTask->getDetailPanelItems($data->eTask, $data->cUser, FALSE)), 'la description n\'est pas portée');

		$data->eTask['plannedUsers'] = $data->cUser->getIds();

		$this->assertEquals(['series', 'plannedWeek', 'description'], array_keys($uiTask->getDetailPanelItems($data->eTask, $data->cUser, FALSE)), 'la fiche porte l\'affectation alors que le tableau de l\'équipe la porte');

		if($this->assertDecorPresent($data->cUser->count() > 1, 'la structure a plusieurs membres, sans quoi l\'affectation ne se montre pas') === FALSE) {
			return;
		}

		$this->assertEquals(['series', 'plannedWeek', 'plannedUsers', 'description'], array_keys($uiTask->getDetailPanelItems($data->eTask, $data->cUser, TRUE)), 'une ferme sans suivi du temps ne voit pas l\'affectation dans la fiche');

		// Sur l'élément de la page, sans écrire : la fiche ne lit de la répétition et du matériel que ce que
		// leur rendu affiche.
		$data->eTask['repeat'] = new \series\Repeat(['id' => 1, 'frequency' => \series\Repeat::W1, 'stop' => NULL]);
		$eTool = new \work\Tool(['id' => 1, 'name' => 'Grelinette', 'vignette' => NULL, 'routineName' => NULL]);
		$data->eTask['cProduct'] = new \Collection([1 => $eTool]);
		$data->eTask['cTool'] = new \Collection([1 => $eTool]);

		$this->assertEquals(['series', 'plannedWeek', 'repeat', 'cProduct', 'cTool', 'description'], array_keys($uiTask->getDetailPanelItems($data->eTask, $data->cUser, FALSE)), 'la description ne vient pas après la répétition, les intrants et le matériel');

	}

	/**
	 * La fenêtre du temps d'une ligne s'ouvre sous elle, et au-dessus seulement si le dessous manque et que le
	 * dessus suffit (retour 196). Un panneau couvre la page et ses barres : son écran va du bas de son en-tête
	 * au haut de son pied quand il porte quelque chose, sinon à son propre bas. Posée par-dessus, la fenêtre
	 * passait sous l'en-tête, qu'aucun `z-index` du corps ne peut franchir. Sans place ni dessous ni dessus, la
	 * fenêtre reste dessous, et ce qui la porte défile jusqu'à la montrer, sans faire passer son haut sous
	 * l'en-tête (contre-recette M1, panneau de 300 de haut) : elle s'ouvrait entièrement hors de l'écran.
	 * Toutes les mesures précèdent les écritures.
	 *
	 * `PlanningDurationStep.place()` tourne sur une géométrie simulée : une page dont la barre collante finit à
	 * 100 et l'écran à 800, une ligne de 40 et une fenêtre posée sous elle.
	 */
	public function testStepPlace(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$classes = '';

		foreach(['PlanningDuration', 'PlanningDurationStep'] as $class) {

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe '.$class) === FALSE) {
				return;
			}

			$classes .= substr($source, $start, $end - $start + 2);

		}

		// [panneau, bas de l'en-tête, haut du pied ou NULL quand il est vide, bas du panneau, haut de la ligne, bas de la table, hauteur de la fenêtre] => [ouverte au-dessus, défilement]
		$cases = [
			'panneau, crayon en haut, dernière ligne' => [[TRUE, 120, NULL, 800, 300, 340, 200], [FALSE, 0]],
			'panneau, en-tête au-dessus de la barre de la page' => [[TRUE, 60, NULL, 500, 280, 320, 200], [TRUE, 0]],
			'panneau, crayon en bas, sous le pied' => [[TRUE, 120, 650, 800, 500, 900, 200], [TRUE, 0]],
			'panneau, crayon en bas, pied vide' => [[TRUE, 120, NULL, 800, 500, 900, 200], [FALSE, 0]],
			'panneau, crayon en haut, place dessous' => [[TRUE, 120, 650, 800, 200, 900, 200], [FALSE, 0]],
			'panneau de 300, ligne en bas, place ni dessous ni dessus' => [[TRUE, 113, NULL, 300, 200, 260, 120], [FALSE, 60]],
			'panneau de 300, la fenêtre plus haute que la place' => [[TRUE, 113, NULL, 300, 140, 260, 250], [FALSE, 67]],
			'page, crayon en haut, dernière ligne' => [[FALSE, NULL, NULL, NULL, 360, 400, 200], [FALSE, 0]],
			'page, sous la barre du haut' => [[FALSE, NULL, NULL, NULL, 260, 300, 200], [FALSE, 0]],
			'page, crayon en bas' => [[FALSE, NULL, NULL, NULL, 700, 900, 200], [TRUE, 0]],
			'page, place ni dessous ni dessus' => [[FALSE, NULL, NULL, NULL, 160, 1000, 700], [FALSE, 100]],
		];

		[$shown, $output] = $this->runNode('
			const vm = require("vm");

			const style = {"--mainSticky": "2.5", "--nav-main-sticked": "3.75", "--space-xs": "0.5rem"};
			const scrolled = [];

			// De côté, la fenêtre tient dans l\'écran : seul le placement vertical est éprouvé ici (`DurationPlaceTest`).
			const context = vm.createContext({
				window: {innerHeight: 800, scrollBy: (x, y) => scrolled.push(["page", y])},
				document: {documentElement: {clientWidth: 1000}},
				getComputedStyle: element => ({position: element?.position, overflowY: "visible", getPropertyValue: name => style[name]}),
				qs: () => ({getBoundingClientRect: () => ({left: 0, right: 1000})}),
				// Hors d\'un panneau, la barre du haut de la page est fixe et finit à 100, comme `--mainSticky` et
				// `--nav-main-sticked` la font finir dans un panneau.
				qsa: (selector, each) => [{position: "fixed", getBoundingClientRect: () => ({top: 0, bottom: 100, width: 1000, height: 100})}].forEach(each),
				rem: () => 16
			});

			vm.runInContext('.json_encode($classes).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';

			const shown = cases.map(([inPanel, headerBottom, footerTop, panelBottom, rowTop, tableBottom, height]) => {

				const log = [];
				scrolled.length = 0;

				const measured = (name, bounds) => ({getBoundingClientRect() { log.push(name); return bounds; }});
				const header = measured("header", {bottom: headerBottom});
				const footer = (footerTop === null) ? null : measured("footer", {top: footerTop});
				const panel = inPanel ? Object.assign(measured("panel", {left: 0, right: 1000, bottom: panelBottom}), {
					qs: selector => ({".panel-header": header, ".panel-footer:not(:empty)": footer})[selector],
					scrollBy: (x, y) => { log.push("write"); scrolled.push(["panel", y]); }
				}) : null;
				const row = measured("row", {top: rowTop, bottom: rowTop + 40});
				const table = measured("table", {top: 0, bottom: tableBottom});
				const ancestors = {
					".panel": panel,
					".planning-harvest-drawer": null,
					".planning-time-row, .planning-detail-time-field, .planning-harvest-time, .planning-time-crew-duration, .planning-grid-row": row,
					".planning-time-table, .planning-detail-time-table, #planning-grid, #planning-grid-late, form": table
				};

				const classes = [];
				const stepWindow = Object.assign(measured("window", {left: 100, right: 464, top: rowTop + 40, bottom: rowTop + 40 + height, height: height}), {
					style: {getPropertyValue: () => "", setProperty() { log.push("write"); }, removeProperty() { log.push("write"); }},
					classList: {add(name) { log.push("write"); classes.push(name); }, toggle(name, force) { log.push("write"); if(force) { classes.push(name); } }, contains: () => false},
					closest: selector => ancestors[selector]
				});

				context.PlanningDurationStep.place(stepWindow);

				const writes = log.filter(name => name === "write").length;
				const scroll = scrolled.reduce((sum, [scroller, y]) => sum + y, 0);
				const scroller = (scrolled.length === 0) ? null : scrolled[0][0];

				return [
					classes.includes("is-up"),
					scroll,
					writes === 0 || log.indexOf("write") === log.length - writes,
					scroller === null || scroller === (inPanel ? "panel" : "page")
				];

			});

			process.stdout.write(JSON.stringify(shown));
		');

		if($this->assertTrue(is_array($shown), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$up, $scroll] = $cases[$case][1];

			$this->assertEquals($up, $shown[$position][0], $case.' : la fenêtre '.($up ? 'ne s\'ouvre pas au-dessus de la ligne' : 's\'ouvre au-dessus de la ligne'));
			$this->assertEquals($scroll, $shown[$position][1], $case.' : le défilement qui montre la fenêtre');
			$this->assertTrue($shown[$position][2], $case.' : une mesure suit une écriture');
			$this->assertTrue($shown[$position][3], $case.' : ce n\'est pas '.($cases[$case][0][0] ? 'le panneau' : 'la page').' qui défile');

		}

	}

	/**
	 * Rien ne part de la fenêtre des pas avant « Enregistrer » ou Entrée (planche R-Retours-0810, Enregistrer) :
	 * les pas et la frappe ne font qu'écrire la durée affichée. « Enregistrer » envoie une fois et ferme la
	 * fenêtre (FJ, retour 411), une durée inchangée ne part pas et la ferme aussi. La poubelle envoie une durée
	 * nulle et ferme. Le client ne refuse rien (R-Retours-0810, 210) : des minutes
	 * au-delà de 59 se lisent comme sur la fiche de temps (`timesheet.js`), et plus d'une journée part au
	 * serveur, dont le refus rouvre la fenêtre.
	 * Échap, un appui dehors ou une tabulation qui sort de la fenêtre la ferment sur la dernière durée écrite,
	 * sans rien envoyer. Un refus la rouvre sur la durée refusée, où son message se lit, et la dernière durée
	 * écrite reste celle d'avant. Un second « Enregistrer », depuis la fenêtre rouverte avant la réponse du
	 * premier, envoie son propre total, compté depuis la durée du premier.
	 *
	 * `PlanningDurationStep` tourne sur des nœuds simulés, sans placement : `place()` a son propre cas.
	 */
	public function testStepSave(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$classes = '';

		foreach(['PlanningDuration', 'PlanningDurationStep'] as $class) {

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe '.$class) === FALSE) {
				return;
			}

			$classes .= substr($source, $start, $end - $start + 2);

		}

		// [gestes] => [durées envoyées, fenêtre ouverte, cases affichées, dernière durée écrite], à partir d'une heure
		$cases = [
			'un pas' => [[['step', 15]], [[], TRUE, ['1', '15'], '1']],
			'des pas puis Enregistrer' => [[['step', 15], ['step', 60], ['save']], [[2.25], FALSE, ['2', '15'], '2.25']],
			'Enregistrer sans rien changer' => [[['save']], [[], FALSE, ['1', '00'], '1']],
			'une durée tapée puis Entrée' => [[['type', '3', '30'], ['save']], [[3.5], FALSE, ['3', '30'], '3.5']],
			'des minutes au-delà de 59 puis Entrée' => [[['type', '3', '75'], ['save']], [[4.25], FALSE, ['4', '15'], '4.25']],
			'plus d\'une journée, que le serveur refuse' => [[['type', '87', '00'], ['refuse'], ['save']], [[87], TRUE, ['87', '00'], '1']],
			'des pas puis Échap, un appui dehors ou une tabulation' => [[['step', 60], ['close']], [[], FALSE, ['1', '00'], '1']],
			'une durée tapée puis Échap' => [[['type', '3', '30'], ['close']], [[], FALSE, ['1', '00'], '1']],
			'un refus' => [[['step', 60], ['refuse'], ['save']], [[2], TRUE, ['2', '00'], '1']],
			'un refus, puis Échap' => [[['step', 60], ['refuse'], ['save'], ['close']], [[2], FALSE, ['1', '00'], '1']],
			'deux Enregistrer sans attendre la réponse' => [[['hold'], ['step', 5], ['save'], ['open'], ['step', 5], ['save']], [[1.08, 1.17], FALSE, ['1', '10'], '1.17']],
			'la poubelle' => [[['erase']], [[0], FALSE, ['0', '00'], '0']],
			'la poubelle après des pas' => [[['step', 15], ['erase']], [[0], FALSE, ['0', '00'], '0']],
			'la poubelle, que le serveur refuse' => [[['refuse'], ['erase']], [[0], TRUE, ['0', '00'], '1']],
		];

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			const classList = () => {
				const names = new Set();
				return {
					add: (...added) => added.forEach(name => names.add(name)),
					remove: (...removed) => removed.forEach(name => names.delete(name)),
					contains: name => names.has(name),
					toggle(name, on = (names.has(name) === false)) {
						if(on) {
							names.add(name);
						} else {
							names.delete(name);
						}
						return on;
					}
				};
			};

			// `dataset` écrit ses valeurs en texte, comme `DOMStringMap`.
			const dataset = values => new Proxy(values, {set(target, key, value) { target[key] = String(value); return true; }});

			let current = null;

			const context = vm.createContext({
				// Les envois rendent une promesse du script de test : la classe la reconnaît par `instanceof Promise`,
				// qui ne vaut que dans le même royaume.
				Promise: Promise,
				PlanningTask: {MAX_TIME: 24, round: time => Math.round(time * 100) / 100, format: time => String(time)},
				requestAnimationFrame: () => {},
				// L\'envoi relève la case qui a le focus (`PlanningDurationStep.send()`) : aucune ici.
				document: {activeElement: null, body: {}},
				qs: selector => (current !== null && selector.includes(".planning-duration-step-window.is-open") && current.stepWindow.classList.contains("is-open")) ? current.stepWindow : null,
				qsa: (selector, each) => {
					if(current !== null && selector === ".planning-duration-step-window.is-open" && current.stepWindow.classList.contains("is-open")) {
						each(current.stepWindow);
					}
				}
			});

			vm.runInContext('.json_encode($classes).' + ";globalThis.PlanningDuration = PlanningDuration; globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const PlanningDurationStep = context.PlanningDurationStep;
			PlanningDurationStep.place = () => {};

			let sent = [];
			let refuse = false;
			let hold = false;
			const held = [];

			PlanningDurationStep.register({
				selector: ".planning-duration-step",
				bounded: true,
				change: (field, time) => {
					sent.push(time);
					const json = refuse ? {__instructions: [["js", [["errors", {}]]]]} : {__instructions: []};
					refuse = false;
					return hold ? new Promise(resolve => held.push(() => resolve(json))) : Promise.resolve(json);
				}
			});

			const make = () => {

				// `value` écrit en texte, comme un `<input>`.
				const input = value => {
					let text = value;
					return {get value() { return text; }, set value(written) { text = String(written); }, classList: classList(), getAttribute: () => null};
				};
				const hour = input("1");
				const minute = input("00");
				const group = {classList: classList()};
				const stepWindow = {classList: classList()};

				const field = {
					dataset: dataset({duration: "task-1-2026-10-05", sent: "1"}),
					classList: classList(),
					isConnected: true,
					matches: selector => selector === ".planning-duration-step",
					qs: selector => ({"input[name=\"timeAddHour\"]": hour, "input[name=\"timeAddMinute\"]": minute, ".timesheet-time-field-group": group})[selector] ?? null,
					closest: selector => (selector === ".planning-duration-step-window") ? stepWindow : null,
					contains: () => false
				};

				stepWindow.qs = selector => (selector === ".planning-duration-step") ? field : null;

				// La tuile qui précède la fenêtre, qu\'un refus remet à la dernière durée écrite (`refuse()`).
				const value = {textContent: "", classList: classList()};
				stepWindow.previousElementSibling = {qs: (selector, callback) => (selector === ".planning-duration-tile-value") ? callback(value) : null};

				return {field, stepWindow, hour, minute};

			};

			(async () => {

				const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';
				const results = [];

				for(const gestures of cases) {

					current = make();
					sent = [];
					refuse = false;
					hold = false;
					held.length = 0;

					const field = current.field;

					PlanningDurationStep.open(field);

					for(const [gesture, ...values] of gestures) {

						switch(gesture) {

							case "step" :
								PlanningDurationStep.add(field, values[0]);
								break;

							case "type" :
								current.hour.value = values[0];
								current.minute.value = values[1];
								break;

							case "save" :
								PlanningDurationStep.save(field);
								break;

							case "erase" :
								PlanningDurationStep.erase(field);
								break;

							case "open" :
								PlanningDurationStep.open(field);
								break;

							case "close" :
								PlanningDurationStep.close(field);
								break;

							case "refuse" :
								refuse = true;
								break;

							case "hold" :
								hold = true;
								break;

						}

						await new Promise(resolve => setTimeout(resolve));

					}

					held.forEach(resolve => resolve());
					await new Promise(resolve => setTimeout(resolve));

					results.push([sent, current.stepWindow.classList.contains("is-open"), [current.hour.value, current.minute.value], field.dataset.sent]);

				}

				process.stdout.write(JSON.stringify(results));

			})();
		');

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$sent, $open, $shown, $last] = $cases[$case][1];

			$this->assertEquals($sent, $results[$position][0], $case.' : les durées envoyées');
			$this->assertEquals($open, $results[$position][1], $case.' : la fenêtre '.($open ? 'est fermée' : 'reste ouverte'));
			$this->assertEquals($shown, $results[$position][2], $case.' : les cases affichées');
			$this->assertEquals($last, $results[$position][3], $case.' : la dernière durée écrite');

		}

	}

	/**
	 * Dans le menu d'un jour de la grille, la poubelle d'une personne n'envoie que sa durée nulle (FJ, point 14
	 * du relecteur, tranché le 16/10) : une durée tapée pour une autre et pas enregistrée ne part qu'avec
	 * « Enregistrer ». Dans les deux cas, la réponse acceptée ferme le menu.
	 *
	 * `PlanningTaskDetail` tourne sur des nœuds simulés, la requête et la file étant remplacées.
	 */
	public function testEraseTeam(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTaskDetail {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTaskDetail') === FALSE) {
			return;
		}

		$class = substr($source, $start, $end - $start + 2);

		// [gestes] => [durées envoyées par personne, menu fermé]
		$cases = [
			'la poubelle de Bruno' => [[['erase', 'bruno']], [[['bruno' => 0]], TRUE]],
			'une durée tapée pour Alice, puis la poubelle de Bruno' => [[['type', 'alice', 1.08], ['erase', 'bruno']], [[['bruno' => 0]], TRUE]],
			'une durée tapée pour Alice, puis Enregistrer' => [[['type', 'alice', 1.08], ['save']], [[['alice' => 1.08]], TRUE]],
		];

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			let sent = [];
			let closed = false;

			const context = vm.createContext({
				Promise: Promise,
				PlanningTask: {enqueue: callback => callback()},
				PlanningTaskGrid: {close: () => { closed = true; }},
				PlanningDuration: {refused: () => false},
				PlanningDurationStep: {write: () => {}},
				Ajax: {
					Query: class {
						url() { return this; }
						body(body) { this.sent = body.time; return this; }
						fetch() { sent.push(this.sent); return Promise.resolve({__instructions: []}); }
					}
				},
				document: {getElementById: () => grid}
			});

			vm.runInContext('.json_encode($class).' + ";globalThis.PlanningTaskDetail = PlanningTaskDetail;", context);

			const PlanningTaskDetail = context.PlanningTaskDetail;

			const list = {};
			const grid = {
				id: "planning-detail-team-grid-1",
				dataset: {body: "{}", screen: "day", added: "", urlTime: "/time"},
				closest: selector => (selector === ".planning-grid-time-list") ? list : null
			};

			const fields = {};

			["alice", "bruno"].forEach(user => {
				const row = {dataset: {user: user}};
				fields[user] = {
					dataset: {duration: "task-1-2026-10-05-" + user},
					closest: selector => ({"[data-user]": row, ".planning-detail-team-grid": grid})[selector] ?? null
				};
				PlanningTaskDetail.served.set(fields[user].dataset.duration, 1);
			});

			// `qsa()` rend la liste sans fonction, et la parcourt avec, comme celui de `lime.js`.
			grid.qsa = (selector, each) => (each === undefined) ? Object.values(fields) : Object.values(fields).forEach(each);

			(async () => {

				const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';
				const results = [];

				for(const gestures of cases) {

					sent = [];
					closed = false;
					PlanningTaskDetail.typed.clear();

					for(const [gesture, ...values] of gestures) {

						switch(gesture) {

							case "type" :
								PlanningTaskDetail.type(fields[values[0]], values[1]);
								break;

							case "erase" :
								PlanningTaskDetail.erase(fields[values[0]]);
								break;

							case "save" :
								PlanningTaskDetail.saveTeam(grid);
								break;

						}

						await new Promise(resolve => setTimeout(resolve));

					}

					results.push([sent, closed]);

				}

				process.stdout.write(JSON.stringify(results));

			})();
		');

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$sent, $closed] = $cases[$case][1];

			$this->assertEquals($sent, $results[$position][0], $case.' : les durées envoyées');
			$this->assertEquals($closed, $results[$position][1], $case.' : le menu '.($closed ? 'reste ouvert' : 'est fermé'));

		}

	}

	/**
	 * Le panneau d'une intervention, rendu par l'UI de sa vue avec les données que la page a préparées.
	 */
	private function renderPanel(\series\Task $eTask): ?\Panel {

		$data = $this->getPanelData($eTask);

		if($data === NULL) {
			return NULL;
		}

		return new TaskDetailUi()->get($data->eTask, $data->week, $data->cPlace, $data->cPhoto, $data->cUser, $data->cComment, $data->history, $data->days, $data->foot, $data->navigation, $data->timesByDay);

	}

	/**
	 * Ce que la page de l'intervention prépare pour sa vue.
	 */
	private function getPanelData(\series\Task $eTask): ?\stdClass {

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/intervention/'.$eTask['id'], ['week' => \Clock::week()], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la page de l\'intervention ne se rend pas') === FALSE) {
			return NULL;
		}

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

	}

	/** La semaine que montre le tableau, que son corps réémet à chaque geste. */
	private function getWeekShown(?\Dom\Element $grid): ?string {

		return json_decode($grid?->getAttribute('data-body') ?? '[]', TRUE)['weekShown'] ?? NULL;

	}

	private function parseHtml(string $html): \Dom\HTMLDocument {

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * La page, rendue par l'UI de sa vue avec les données qu'elle a préparées : la vue rend un panneau, dont
	 * le corps est la page.
	 */
	private function renderDetail(\series\Task $eTask, array $input = []): \Dom\HTMLDocument {

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/intervention/'.$eTask['id'], $input + ['week' => \Clock::week()], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'la page de l\'intervention ne se rend pas');

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$panel = new TaskDetailUi()->get($data->eTask, $data->week, $data->cPlace, $data->cPhoto, $data->cUser, $data->cComment, $data->history, $data->days, $data->foot, $data->navigation, $data->timesByDay);

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

	}

	/** Les jours que portent les cases du tableau, toutes lignes confondues. */
	private function getFieldDates(\Dom\HTMLDocument $document): array {

		$dates = [];

		foreach($document->querySelectorAll('#planning-task-detail-grid .planning-detail-time-field[data-date]') as $field) {
			$dates[] = $field->getAttribute('data-date');
		}

		return array_values(array_unique($dates));

	}

	/** Une autre semaine dans le bloc, depuis la semaine d'où la page s'ouvre. Sans semaine (`NULL`), l'appel n'en porte pas. */
	private function callDetailWeek(\series\Task $eTask, ?string $weekShown, array $input = []): ?\Action {

		$input += [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
		];

		if($weekShown !== NULL) {
			$input += ['weekShown' => $weekShown];
		}

		return $this->executePage('planning/taskDetail:week', $input, 'GET');

	}

	/** La ligne d'une personne demandée par « Ajouter une personne », dans la semaine en cours, les lignes déjà ajoutées réémises. */
	private function callDetailUser(\series\Task $eTask, \user\User $eUser, array $userIdsAdded = []): ?\Action {

		return $this->executePage('planning/taskDetail:user', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'weekShown' => \Clock::week(),
			'user' => $eUser['id'],
			'added' => implode(',', $userIdsAdded),
		], 'GET');

	}

	/** Une personne affectée (`add`) ou retirée (`delete`) depuis son nom, dans la semaine en cours. Une valeur `NULL` de `$input` retire le paramètre. */
	private function callDetailAssign(\series\Task $eTask, \user\User $eUser, string $action, array $input = []): ?\Action {

		$input += [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'weekShown' => \Clock::week(),
			'user' => $eUser['id'],
			'action' => $action,
		];

		return $this->executePage('planning/taskDetail:doUser', array_filter($input, fn($value) => $value !== NULL), 'POST');

	}

	/** Les personnes de l'intervention, relue en base, pour la semaine montrée. */
	private function getWeekUsers(\series\Task $eTask, string $weekShown): \Collection {

		return TaskDetailLib::getUsers(\series\TaskLib::getById($eTask['id']), $weekShown);

	}

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

	/** Une récolte ajoutée par la ligne du bloc, depuis la semaine. Sans unité (`NULL`), la saisie n'en poste pas. */
	private function callHarvest(\series\Task $eTask, string $quantity, string $date, ?string $unit = NULL): ?\Action {

		$input = [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'harvestMore' => $quantity,
			'harvestDate' => $date,
		];

		if($unit !== NULL) {
			$input['harvestUnit'] = $unit;
		}

		return $this->executePage('planning/taskDetail:doHarvest', $input, 'POST');

	}

	/** La quantité d'un jour remplacée par la modification de sa ligne, depuis la semaine. */
	private function callHarvestDay(\series\Task $eTask, string $quantity, string $date): ?\Action {

		return $this->executePage('planning/taskDetail:doHarvest', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'mode' => 'replace',
			'harvestDay' => $quantity,
			'harvestDate' => $date,
		], 'POST');

	}

	private function getHarvestDates(\series\Task $eTask): array {

		$eTask = \series\TaskLib::getById($eTask['id']);

		\series\TaskLib::fillHarvestDates($eTask);

		return $eTask['harvestDates'];

	}

	/** Les lignes de temps d'une personne un jour, sans les sommer : une ligne à zéro compte. */
	private function countTimesheets(\series\Task $eTask, \user\User $eUser, string $date): int {

		return \series\Timesheet::model()
			->whereTask($eTask)
			->whereUser($eUser)
			->whereDate($date)
			->count();

	}

	private function getTaskTime(\series\Task $eTask): float {

		return round((float)\series\Task::model()
			->select('time')
			->whereId($eTask['id'])
			->get()['time'], 2);

	}

}
?>
