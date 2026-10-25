<?php
namespace planning;

/**
 * Le Jour de l'écran « Interventions » (L5c) : où une intervention se range, ce que chaque colonne
 * affiche, et ce que ses gestes écrivent ou refusent, le jour (`doPlanned`) et le temps d'un jour
 * (`doTime`). Chaque cas passe par la vraie route, avec ses
 * validations, dans la transaction du décor (`HarvestFixtureTest`), et ne regarde que les interventions
 * qu'il pose. Que la réponse ciblée soit l'écran d'un rechargement est éprouvé par `FingerprintTest`
 * (`testDay*`).
 *
 * Les états particuliers d'abord : l'intervention rangée dans plusieurs colonnes, la colonne vidée par
 * le masque, le jour à venir sans « + » ni champ de temps.
 *
 * Le jour du décor est le jeudi de la semaine 39.
 *
 * Lancement : dev/test package=planning file=TaskDay
 */
class TaskDayTest extends HarvestFixtureTest {

	/**
	 * Une intervention faite lundi, qui porte aussi du temps mardi, a une carte dans les deux colonnes ;
	 * une à faire sans jour va dans « Sans jour » seule ; une à faire posée samedi, dans samedi seul.
	 */
	public function testKeys(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);
		$saturday = week_date_day(\Clock::week(), 6);

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);
		$this->createTimesheet($eTaskDone, 1.0, $tuesday);

		$eTaskNone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskSaturday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $saturday);

		$this->assertEquals([$monday, $tuesday], TaskDayLib::getKeysForTask($this->eStructure, \Clock::week(), \series\TaskLib::getById($eTaskDone['id'])), 'l\'intervention faite lundi avec du temps mardi ne va pas dans les deux colonnes');
		$this->assertEquals([TaskDayLib::NONE], TaskDayLib::getKeysForTask($this->eStructure, \Clock::week(), \series\TaskLib::getById($eTaskNone['id'])), 'l\'intervention à faire sans jour ne va pas dans « Sans jour » seule');
		$this->assertEquals([$saturday], TaskDayLib::getKeysForTask($this->eStructure, \Clock::week(), \series\TaskLib::getById($eTaskSaturday['id'])), 'l\'intervention posée samedi ne va pas dans samedi seul');

		[$document, $data] = $this->renderTaskDay();

		$this->assertEquals(array_merge([TaskDayLib::NONE], week_dates(\Clock::week())), array_keys($data->columns), 'les colonnes ne sont pas « Sans jour » puis les sept jours');

		foreach([$monday, $tuesday] as $date) {
			$this->assertTrue($document->getElementById('planning-task-'.$eTaskDone['id'].'-'.$date) !== NULL, 'la carte de l\'intervention faite manque le '.$date);
		}

		$this->assertTrue($document->getElementById('planning-task-'.$eTaskNone['id'].'-'.TaskDayLib::NONE) !== NULL, 'la carte sans jour manque à « Sans jour »');

	}

	/**
	 * Le temps saisi s'écrit en heures et minutes sur la carte, là où un temps absent s'écrit « ? »
	 * (retour 107, `testColumnStates()`).
	 */
	public function testTimeWritten(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le temps de la carte') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTask, 7.5, \Clock::date());

		[$document] = $this->renderTaskDay();

		$this->assertEquals('7h30', trim($document->querySelector('#planning-task-'.$eTask['id'].'-'.\Clock::date().' [data-planning-task-time-open]')?->textContent ?? ''), 'la carte faite aujourd\'hui ne dit pas le temps saisi');

	}

	/**
	 * Le menu du jour d'une carte sans jour choisit « Sans jour » ; une carte faite aujourd'hui ouvre le
	 * champ du temps, une carte faite un jour à venir non. Un jour passé et aujourd'hui portent « J'ai aussi
	 * fait », un jour à venir non.
	 */
	public function testColumnStates(): void {

		$friday = week_date_day(\Clock::week(), 5);

		$eTaskNone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskToday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$eTaskFriday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $friday);

		[$document] = $this->renderTaskDay();

		$cardNone = 'planning-task-'.$eTaskNone['id'].'-'.TaskDayLib::NONE;

		$selected = $document->querySelector('[data-dropdown-id="'.$cardNone.'-day-list"] .selected');
		$this->assertEquals(TaskDayLib::NONE, $selected?->getAttribute('data-planning-task-plan'), 'le menu d\'une carte sans jour ne choisit pas « Sans jour »');

		if($this->eStructure->hasTime()) {

			$this->assertTrue($document->querySelector('#planning-task-'.$eTaskToday['id'].'-'.\Clock::date().' [data-planning-task-time-open]') !== NULL, 'la carte faite aujourd\'hui n\'ouvre pas le champ du temps');

		}

		$this->assertTrue($document->querySelector('#planning-task-'.$eTaskFriday['id'].'-'.$friday.' [data-planning-task-time-open]') === NULL, 'le temps de la carte faite un jour à venir ouvre un champ');

		$this->assertTrue($document->querySelector('#planning-day-'.\Clock::date().' a[href*="taskDay:create"][href*="status=done"]') !== NULL, 'aujourd\'hui ne porte pas « J\'ai aussi fait »');
		$this->assertTrue($document->querySelector('#planning-day-'.$friday.' a[href*="taskDay:create"][href*="status=done"]') === NULL, 'un jour à venir porte « J\'ai aussi fait »');
		$this->assertTrue($document->querySelector('#planning-day-'.TaskDayLib::NONE.' a[href*="taskDay:create"][href*="status=done"]') === NULL, '« Sans jour » porte « J\'ai aussi fait »');

	}

	/**
	 * Le temps d'une carte faite est celui de l'intervention ce jour-là (retour 360) : le champ de la carte
	 * porte 1h30 lundi et 0h45 mardi, le temps de la personne connectée ce jour-là.
	 */
	public function testDoneTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);
		$this->createTimesheet($eTask, 1.5, $monday);
		$this->createTimesheet($eTask, 0.75, $tuesday);

		[$document] = $this->renderTaskDay();

		foreach([$monday => [1.5, '1h30'], $tuesday => [0.75, '0h45']] as $date => [$time, $readout]) {

			$card = '#planning-task-'.$eTask['id'].'-'.$date;
			$duration = $document->querySelector($card.' .planning-task-time-field .planning-duration-step');

			if($this->assertTrue($duration !== NULL, 'la carte du '.$date.' ne porte pas de saisie du temps') === FALSE) {
				continue;
			}

			$this->assertEquals((string)$time, $duration->getAttribute('data-sent'), 'la saisie de la carte du '.$date.' ne porte pas le temps de la personne connectée ce jour-là');
			$this->assertEquals($readout, $this->readStepped($duration), 'la durée lue de la carte du '.$date.' ne dit pas son temps');

		}

	}

	/**
	 * Au Jour, une colonne ne montre que son jour (retour 360) : le temps, les personnes et la quantité
	 * récoltée de la carte sont ceux du jour de la colonne. Une récolte faite mercredi, avec 1h lundi et
	 * 3 récoltés mardi et 5 mercredi, porte 1h et sa personne lundi sans quantité, 3 mardi et 5 mercredi
	 * sans temps ni personne. « Sans jour » n'a pas de jour et garde la semaine (tranché par le maquetteur, DS) :
	 * une intervention à faire sans jour, avec 0h30 lundi et 0h15 mardi, y porte 0h45.
	 */
	public function testDayMarks(): void {

		if(
			$this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE or
			$this->requireAction(ACTION_RECOLTE) === FALSE
		) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);
		$wednesday = week_date_day(\Clock::week(), 3);

		$eTaskHarvest = $this->createTaskHarvest($this->getCultivation(), \Clock::week(), $wednesday);
		$this->createTimesheet($eTaskHarvest, 1.0, $monday);
		$this->createHarvest($eTaskHarvest, 3.0, $tuesday);
		$this->createHarvest($eTaskHarvest, 5.0, $wednesday);

		$eTaskNone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTaskNone, 0.5, $monday);
		$this->createTimesheet($eTaskNone, 0.25, $tuesday);

		[, $data] = $this->renderTaskDay();

		$member = $this->getMember()['id'];

		$expected = [
			$monday => [[$member => 1.0], 1.0, NULL],
			$tuesday => [[], 0, 3.0],
			$wednesday => [[], 0, 5.0],
		];

		foreach($expected as $date => [$times, $timePeriod, $harvestPeriod]) {

			$eTaskColumn = $data->columns[$date]['cTask'][$eTaskHarvest['id']] ?? new \series\Task();

			if($this->assertTrue($eTaskColumn->notEmpty(), 'la récolte n\'a pas de carte le '.$date) === FALSE) {
				continue;
			}

			$this->assertEquals([$times, $timePeriod, $harvestPeriod, 8.0], [$eTaskColumn['times'], $eTaskColumn['timePeriod'], $eTaskColumn['harvestPeriod'], $eTaskColumn['harvestWeekTotal']], 'la carte du '.$date.' ne porte pas le temps et la récolte de son jour');

		}

		$eTaskNoneColumn = $data->columns[TaskDayLib::NONE]['cTask'][$eTaskNone['id']] ?? new \series\Task();

		if($this->assertTrue($eTaskNoneColumn->notEmpty(), 'l\'intervention sans jour n\'est pas dans « Sans jour »') === FALSE) {
			return;
		}

		$this->assertEquals(0.75, $eTaskNoneColumn['timePeriod'], '« Sans jour » ne porte pas le temps de la semaine');
		$this->assertEquals(0.5, $data->columns[$monday]['cTask'][$eTaskNone['id']]['timePeriod'] ?? NULL, 'la carte de lundi de l\'intervention sans jour ne porte pas le temps de lundi');

	}

	/**
	 * Une carte de « Sans jour » cochée dans une semaine passée n'a aucun jour, et ne va donc plus dans
	 * aucune colonne : la réponse la garde dans « Sans jour », faite, où sa décoche la défait. Le
	 * rechargement ne la montre plus (Q2 du plan).
	 */
	public function testDoneKeptInPastWeek(): void {

		$week = toWeek(\Clock::date().' - 14 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		[$data, $instructions, $action] = $this->callDay('doDone', ['task' => $eTask['id'], 'week' => $week, 'column' => TaskDayLib::NONE]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche dans une semaine passée est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(NULL, \series\TaskLib::getById($eTask['id'])['doneDate'], 'l\'intervention cochée dans une semaine passée a un jour fait, le cas ne se monte pas');
		$this->assertEquals([TaskDayLib::NONE], $data->change, 'la coche ne redessine pas « Sans jour » seule');
		if($this->assertTrue($data->columns[TaskDayLib::NONE]['cTask']->offsetExists($eTask['id']), 'la carte cochée quitte « Sans jour »') === FALSE) {
			return;
		}

		$this->assertTrue($data->columns[TaskDayLib::NONE]['cTask'][$eTask['id']]->isDone(), 'la carte gardée dans « Sans jour » n\'est pas faite');
		$this->assertEquals(0, $data->columns[TaskDayLib::NONE]['todo'], 'la carte gardée compte encore à faire');

		[$document] = $this->renderTaskDay(week: $week);

		$this->assertTrue($document->getElementById('planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE) === NULL, 'le rechargement montre encore la carte cochée sans jour');

		[$data, , $action] = $this->callDay('doTodo', ['task' => $eTask['id'], 'week' => $week, 'column' => TaskDayLib::NONE]);

		$this->assertTrue($action instanceof \ViewAction, 'la décoche de la carte gardée est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertTrue($data->columns[TaskDayLib::NONE]['cTask'][$eTask['id']]?->isTodo() ?? FALSE, 'la décoche ne rend pas la carte à faire dans « Sans jour »');

		[$data] = $this->callDay('doDone', ['task' => $eTask['id'], 'week' => $week, 'column' => 'ailleurs']);

		$this->assertTrue($data->columns[TaskDayLib::NONE]['cTask']->offsetExists($eTask['id']) === FALSE, 'une colonne inconnue garde la carte');

	}

	/**
	 * Une carte de « Sans jour » cochée dans la semaine courante est datée d'aujourd'hui en base, et va
	 * donc dans la colonne d'aujourd'hui ; la réponse la garde aussi dans « Sans jour », sous la main qui l'a
	 * cochée, sans case ni compte, jusqu'au rechargement (R-Jour-Fait). Une carte rendue à faire n'est
	 * jamais gardée : tenue dans la colonne d'aujourd'hui, elle s'y lirait prévue ce jour-là.
	 */
	public function testDoneKeptThisWeek(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$data, , $action] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche de « Sans jour » est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(\Clock::date(), \series\TaskLib::getById($eTask['id'])['doneDate'], 'la coche ne date plus l\'intervention d\'aujourd\'hui en base');

		$columnNone = $data->columns[TaskDayLib::NONE];
		$columnToday = $data->columns[\Clock::date()];

		if(
			$this->assertTrue($columnNone['cTask']->offsetExists($eTask['id']), 'la carte cochée quitte « Sans jour »') === FALSE or
			$this->assertTrue($columnToday['cTask']->offsetExists($eTask['id']), 'la carte cochée manque à la colonne d\'aujourd\'hui') === FALSE
		) {
			return;
		}

		$this->assertTrue($columnNone['cTask'][$eTask['id']]->isDone(), 'la carte gardée dans « Sans jour » n\'est pas faite');
		$this->assertEquals([FALSE, TRUE], [$columnNone['cTask'][$eTask['id']]['main'], $columnToday['cTask'][$eTask['id']]['main']], 'la case de sélection n\'est pas sur la seule carte d\'aujourd\'hui');
		$this->assertEquals([0, 0], [$columnNone['todo'], $columnNone['done']], 'la carte gardée compte dans la tête de « Sans jour »');

		[$document] = $this->renderTaskDay();

		$this->assertTrue($document->getElementById('planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE) === NULL, 'le rechargement montre encore la carte cochée dans « Sans jour »');
		$this->assertTrue($document->getElementById('planning-task-'.$eTask['id'].'-'.\Clock::date()) !== NULL, 'le rechargement ne montre pas la carte cochée aujourd\'hui');

		[$data, , $action] = $this->callDay('doTodo', ['task' => $eTask['id'], 'column' => \Clock::date()]);

		if($this->assertTrue($action instanceof \ViewAction, 'la décoche de la carte d\'aujourd\'hui est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue($data->columns[TaskDayLib::NONE]['cTask'][$eTask['id']]?->isTodo() ?? FALSE, 'la décoche de la carte d\'aujourd\'hui ne la rend pas à faire dans « Sans jour »');
		$this->assertTrue($data->columns[\Clock::date()]['cTask']->offsetExists($eTask['id']) === FALSE, 'la décoche garde la carte à faire dans la colonne d\'aujourd\'hui');

		$monday = week_date_day(\Clock::week(), 1);

		[$data] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => $monday]);

		$this->assertTrue($data->columns[$monday]['cTask']->offsetExists($eTask['id']) === FALSE, 'une colonne de jour postée où la carte n\'était pas la garde');

	}

	/**
	 * « Fait ! » en lot garde dans « Sans jour » les seules cartes qui y étaient avant la coche, chacune avec
	 * le temps qu'y avait la personne dans la semaine (R-Lot-Garde). Une carte gardée prise dans le lot d'un
	 * autre geste suit ce geste, comme au rechargement.
	 */
	public function testBatchDoneKept(): void {

		$saturday = week_date_day(\Clock::week(), 6);

		$eTaskNone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskNoneTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskSaturday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $saturday);
		$eTaskNext = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, TaskDayLib::getNextWeek(\Clock::week()));
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 1));

		$this->createTimesheet($eTaskNoneTime, 0.5, week_date_day(\Clock::week(), 1));

		foreach([
			'à faire sans jour' => [$eTaskNone, TRUE],
			'à faire posée samedi' => [$eTaskSaturday, FALSE],
			'à faire la semaine suivante' => [$eTaskNext, FALSE],
			'faite' => [$eTaskDone, FALSE],
		] as $case => [$eTask, $none]) {
			$this->assertEquals($none, TaskDayLib::isNone(\series\TaskLib::getById($eTask['id']), \Clock::week()), $case.' : isNone() se trompe');
		}

		$cTask = TaskBatchLib::getByIds($this->eStructure, [$eTaskNone['id'], $eTaskNoneTime['id'], $eTaskSaturday['id']]);

		$this->assertEquals(
			[$eTaskNone['id'] => 0.0, $eTaskNoneTime['id'] => 0.5],
			TaskBatchLib::getDayKept($this->eStructure, \Clock::week(), $cTask, $this->getMember()),
			'le lot ne garde pas les seules cartes de « Sans jour », avec le temps de la personne'
		);
		$this->assertEquals([], TaskBatchLib::getDayKept($this->eStructure, \Clock::week(), TaskBatchLib::getByIds($this->eStructure, [$eTaskSaturday['id']]), $this->getMember()), 'un lot sans carte de « Sans jour » en garde une');

		[$data, , $action] = $this->callDay('doBatchDone', ['ids' => [$eTaskNone['id'], $eTaskNoneTime['id'], $eTaskSaturday['id']]]);

		if($this->assertTrue($action instanceof \ViewAction, '« Fait ! » en lot est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTaskNone = $data->columns[TaskDayLib::NONE]['cTask'];

		$this->assertEquals([$eTaskNone['id'], $eTaskNoneTime['id']], array_values(array_filter($cTaskNone->getIds(), fn(int $task) => $cTaskNone[$task]['kept'])), 'le lot ne garde pas dans « Sans jour » les seules cartes qui y étaient');
		$this->assertEquals(0.5, $cTaskNone->offsetExists($eTaskNoneTime['id']) ? $cTaskNone[$eTaskNoneTime['id']]['keptTime'] : NULL, 'la carte gardée par le lot perd le temps d\'avant la coche');
		$this->assertEquals([0, 0], [$data->columns[TaskDayLib::NONE]['todo'], $data->columns[TaskDayLib::NONE]['done']], 'les cartes gardées par le lot comptent dans la tête de « Sans jour »');
		$this->assertTrue($cTaskNone->offsetExists($eTaskSaturday['id']) === FALSE, 'la carte de samedi faite paraît dans « Sans jour »');
		$this->assertTrue($data->columns[$saturday]['cTask']->offsetExists($eTaskSaturday['id']) and $data->columns[$saturday]['cTask'][$eTaskSaturday['id']]->isDone(), 'la carte de samedi faite n\'est pas dans samedi, faite, comme au rechargement');

		$kept = [$eTaskNone['id'] => '0', $eTaskNoneTime['id'] => '0.5'];

		[$data] = $this->callDay('doBatchTodo', ['ids' => [$eTaskNone['id']], 'kept' => $kept]);

		$cTaskNone = $data->columns[TaskDayLib::NONE]['cTask'];

		$this->assertTrue($cTaskNone->offsetExists($eTaskNone['id']) and $cTaskNone[$eTaskNone['id']]->isTodo(), 'la carte gardée remise à faire en lot ne suit pas le geste');
		$this->assertTrue($cTaskNone->offsetExists($eTaskNoneTime['id']) and $cTaskNone[$eTaskNoneTime['id']]['kept'], 'un autre geste de lot fait tomber une carte gardée');

	}

	/**
	 * La carte à faire du Jour porte le rond qui la coche, la carte cochée celui qui la remet à faire (lot BI).
	 */
	public function testMarkThenChecked(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$card = '#planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE;

		[$document] = $this->renderTaskDay();
		$this->assertTrue($document->querySelector($card.' [data-planning-task-done="'.$eTask['id'].'"]') !== NULL, 'la carte à faire de « Sans jour » ne porte pas le rond qui la coche');

		$this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);

		[$document] = $this->renderTaskDay();
		$this->assertTrue($document->querySelector('[data-task="'.$eTask['id'].'"] [data-planning-task-todo="'.$eTask['id'].'"]') !== NULL, 'la carte cochée ne porte pas le rond qui la remet à faire');

	}

	/**
	 * Sans droit d'écrire le planning (Bruno, au planning limité), la carte à faire de « Sans jour » ne
	 * porte pas de rond qui coche, et la coche postée quand même est refusée sans rien écrire ni rien redessiner :
	 * la carte reste celle d'un rechargement.
	 */
	public function testLimited(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$card = 'planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE;

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderTaskDay();

		if($this->assertTrue($document->getElementById($card) !== NULL, 'la carte à faire de Bruno manque')) {
			$this->assertTrue($document->querySelector('#'.$card.' [data-planning-task-done]') === NULL, 'le rond de Bruno coche encore');
		}

		[, $instructions, $action] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);

		$this->assertFalse($action instanceof \ViewAction, 'la coche de Bruno, au planning limité, est acceptée');
		$this->assertEquals([], $instructions, 'la coche refusée de Bruno redessine l\'écran');
		$this->assertFalse(\series\TaskLib::getById($eTask['id'])->isDone(), 'la coche refusée de Bruno est écrite');

		// La case de sélection porte un identifiant `field-<aléa>` tiré à chaque rendu.
		$getCard = fn(\Dom\HTMLDocument $document) => preg_replace('/field-[0-9a-f]+/', 'field-', $document->saveHtml($document->getElementById($card)));

		$this->assertEquals($getCard($document), $getCard($this->renderTaskDay()[0]), 'la carte de Bruno après la coche refusée diffère du rechargement');

	}

	/**
	 * Sous le masque, une colonne dont tout est fait perd ses cartes et garde ses comptes.
	 * Le dimanche à venir du décor ne porte rien : une intervention faite ce jour-là y suffit.
	 */
	public function testHiddenColumn(): void {

		$sunday = week_date_day(\Clock::week(), 7);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $sunday);

		$this->setHideDone(TRUE);

		[, $data] = $this->renderTaskDay();

		if($this->assertEquals(0, $data->columns[$sunday]['todo'], 'le dimanche du décor porte des interventions à faire, le cas ne se monte pas') === FALSE) {
			return;
		}

		$this->assertTrue($data->columns[$sunday]['cTask']->empty(), 'le masque laisse une carte faite');
		$this->assertEquals(1, $data->columns[$sunday]['done'], 'la colonne vidée par le masque perd son compte des faites');

	}

	/**
	 * Le menu du jour n'offre que la semaine affichée et la suivante : toute autre semaine est refusée,
	 * sans rien écrire.
	 */
	public function testPlannedOutOfRange(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[, , $action] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['week' => toWeek(\Clock::date().' + 14 DAYS')]]);

		$this->assertTrue($action instanceof \NotExpectedAction, 'un report à deux semaines est accepté');
		$this->assertEquals(\Clock::week(), \series\TaskLib::getById($eTask['id'])['plannedWeek'], 'le report refusé est écrit');

		[, , $action] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['date' => week_date_day(\Clock::week(), 6)]]);

		$this->assertTrue($action instanceof \ViewAction, 'un jour de la semaine est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(week_date_day(\Clock::week(), 6), \series\TaskLib::getById($eTask['id'])['plannedDate'], 'le jour posé n\'est pas écrit');

	}

	/**
	 * Reportée à la semaine suivante depuis le menu du jour, une intervention qui porte du temps lundi garde sa
	 * carte dans la colonne du lundi, et la carte redessinée dit sa semaine prévue, comme à la Semaine (FZ,
	 * `TaskCardUi::hasOtherWeek()`).
	 */
	public function testPlannedNextStarted(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$next = TaskDayLib::getNextWeek(\Clock::week());

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $monday);
		$this->createTimesheet($eTask, 0.5, $monday);

		[$data, , $action] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['week' => $next]]);

		if($this->assertTrue($action instanceof \ViewAction, 'le report à la semaine suivante est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals($next, \series\TaskLib::getById($eTask['id'])['plannedWeek'], 'le report à la semaine suivante n\'est pas écrit');
		$this->assertTrue(in_array($monday, $data->change, TRUE), 'la colonne du lundi n\'est pas redessinée');

		if($this->assertTrue($data->columns[$monday]['cTask']->offsetExists($eTask['id']), 'la carte a quitté la colonne du lundi, où elle porte du temps') === FALSE) {
			return;
		}

		$this->assertTrue(TaskCardUi::hasOtherWeek($data->columns[$monday]['cTask'][$eTask['id']], \Clock::week()), 'la carte redessinée du Jour ne dit pas sa semaine prévue');

	}

	/**
	 * Le temps d'un jour : écrit pour la personne connectée au jour posté, refusé un jour à venir et hors
	 * de la semaine affichée.
	 */
	public function testTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);

		[$data, , $action] = $this->callDay('doTime', ['task' => $eTask['id'], 'date' => $monday, 'time' => '1.5']);

		$this->assertTrue($action instanceof \ViewAction, 'le temps d\'un jour passé est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => 1.5], $this->getTimesByDate($eTask), 'le temps du lundi n\'est pas écrit au lundi');
		$this->assertEquals(1.5, $data->columns[$monday]['cTask'][$eTask['id']]['timeDay'], 'la carte ne porte pas le temps écrit');

		[, , $action] = $this->callDay('doTime', ['task' => $eTask['id'], 'date' => week_date_day(\Clock::week(), 5), 'time' => '1']);
		$this->assertTrue(in_array('Timesheet::date.future', $this->getFails($action), TRUE), 'un temps un jour à venir n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callDay('doTime', ['task' => $eTask['id'], 'date' => date('Y-m-d', strtotime($monday.' - 1 DAY')), 'time' => '1']);
		$this->assertTrue(in_array('Timesheet::date.week', $this->getFails($action), TRUE), 'un temps hors de la semaine n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$monday => 1.5], $this->getTimesByDate($eTask), 'un temps refusé est écrit');

	}

	/**
	 * Une saisie sans durée est refusée par `time.missing` et ne retire pas le temps du jour (R-Retours-0410,
	 * point 20b) : la carte construit le temps sur la liste `day`.
	 */
	public function testTimeMissing(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);
		$this->callDay('doTime', ['task' => $eTask['id'], 'date' => $monday, 'time' => '1.5']);

		[, , $action] = $this->callDay('doTime', ['task' => $eTask['id'], 'date' => $monday]);

		$this->assertEquals(['Timesheet::time.missing'], $this->getFails($action), 'une saisie sans durée n\'est pas refusée par time.missing');
		$this->assertEquals([$monday => 1.5], $this->getTimesByDate($eTask), 'une saisie sans durée change le temps du jour');

	}

	/**
	 * Au Jour, chaque carte d'une même intervention porte ce que la recherche lit (R-Recherche) : masquée ou
	 * montrée, elle l'est dans toutes ses colonnes à la fois. La rangée porte l'omnibox. La rangée porte l'omnibox.
	 */
	public function testSearch(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);
		$this->createTimesheet($eTask, 1.0, $tuesday);

		[$document] = $this->renderTaskDay();

		$searches = [];

		foreach([$monday, $tuesday] as $date) {
			$node = $document->querySelector('#planning-task-'.$eTask['id'].'-'.$date.' .planning-task-main');
			$searches[$date] = ($node === NULL) ? NULL : json_decode($node->getAttribute('data-search'), TRUE);
		}

		$this->assertEquals($this->getPlant()['name'], $searches[$monday]['plant'][0] ?? NULL, 'la carte du lundi ne porte pas l\'espèce à chercher');
		$this->assertEquals($searches[$monday], $searches[$tuesday], 'les deux cartes d\'une même intervention ne portent pas la même recherche');
		$this->assertTrue($document->querySelector('#planning-day #planning-search') !== NULL, 'la rangée du Jour ne porte pas l\'omnibox de la recherche');
		$this->assertTrue($document->querySelector('#planning-day #planning-search') !== NULL, 'la rangée du Jour ne porte pas l\'omnibox de la recherche');

	}

	/**
	 * La catégorie se cherche dans l'omnibox (V58) : une ancienne adresse qui porte encore `category` montre
	 * tout le Jour, les interventions de la catégorie comme celles des autres.
	 */
	public function testCategoryIgnored(): void {

		$eCategory = $this->createCategory();

		if($this->assertDecorPresent($eCategory->notEmpty(), 'une catégorie créée pour le décor') === FALSE) {
			return;
		}

		$eTaskCulture = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskOther = $this->createTaskIn($this->createActionIn($eCategory), $eCategory);

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/jour/'.\Clock::week(), ['category' => $eCategory['id']], 'GET');
		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$cTaskNone = $data->columns[TaskDayLib::NONE]['cTask'];

		$this->assertTrue($cTaskNone->offsetExists($eTaskOther['id']), 'une adresse qui porte une catégorie retire une intervention qui en est');
		$this->assertTrue($cTaskNone->offsetExists($eTaskCulture['id']), 'une adresse qui porte une catégorie retire une intervention d\'une autre catégorie');

	}

	/**
	 * Chaque carte du Jour porte sa clé de rang (`data-sort`), dont le glisser déduit la place d'une carte dans
	 * la colonne d'arrivée (R-Retours-0810, 231). Les clés croissent dans l'ordre des cartes de chaque colonne,
	 * et comparées octet par octet, elles rangent les actions comme le collator range leurs noms : accents,
	 * capitales, un nom qui en prolonge un autre, deux actions du même nom séparées par leur identifiant.
	 */
	public function testSortKey(): void {

		$tasks = [];

		foreach(['Zèbre', 'zebre', 'Zébu', 'Zeb', 'Zèbre'] as $name) {
			$tasks[] = $this->createTaskIn($this->createActionIn($this->getCategoryCulture(), NULL, $name), $this->getCategoryCulture());
		}

		[$document] = $this->renderTaskDay();

		foreach($document->querySelectorAll('#planning-day .planning-day-column') as $column) {

			$keys = [];

			foreach($column->querySelectorAll('.planning-task') as $card) {
				$keys[] = $card->getAttribute('data-sort') ?? '';
			}

			$this->assertTrue(in_array('', $keys, TRUE) === FALSE, 'une carte de la colonne '.$column->getAttribute('data-column').' n\'a pas de clé de rang');

			$sorted = $keys;
			sort($sorted, SORT_STRING);

			$this->assertEquals($sorted, $keys, 'les clés de rang de la colonne '.$column->getAttribute('data-column').' ne suivent pas l\'ordre des cartes');

		}

		$collator = \L::getCollator();

		$expected = $tasks;
		usort($expected, fn(\series\Task $e1, \series\Task $e2) => $collator->compare($e1['action']['name'], $e2['action']['name']) ?: ($e1['action']['id'] <=> $e2['action']['id']));

		$keys = [];

		foreach($tasks as $eTask) {
			$keys[$eTask['id']] = $document->getElementById(TaskCardUi::getId($eTask, TaskDayLib::NONE))?->getAttribute('data-sort') ?? '';
		}

		$actual = $tasks;
		usort($actual, fn(\series\Task $e1, \series\Task $e2) => strcmp($keys[$e1['id']], $keys[$e2['id']]));

		$this->assertEquals(array_map(fn($e) => $e['id'], $expected), array_map(fn($e) => $e['id'], $actual), 'les clés de rang ne rangent pas les actions comme le collator');

	}

	private function countTasks(): int {

		return \series\Task::model()
			->whereStructure($this->eStructure)
			->count();

	}

	private function getTimesByDate(\series\Task $eTask): array {

		$times = [];

		$cTimesheet = \series\Timesheet::model()
			->select(['date', 'total' => new \Sql('SUM(time)', 'float')])
			->whereTask($eTask)
			->whereUser($this->getMember())
			->group('date')
			->sort(['date' => SORT_ASC])
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['date']] = round($eTimesheet['total'], 2);
		}

		return $times;

	}

}
?>
