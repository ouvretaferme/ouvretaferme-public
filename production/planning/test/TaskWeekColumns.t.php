<?php
namespace planning;

/**
 * Les Colonnes de la Semaine (GQ, `GQ/planche.md`) : « À faire » et « Fait » côte à côte, chacune avec les
 * blocs des Cartes réduits aux interventions de son état. Le cas éprouve la donnée que la page prépare
 * (`$data->ccTaskTodo`, `ccTaskDone`, `changeColumns`) et les nœuds que chaque réponse ciblée vise, colonne
 * par colonne, qui sont le contrat avec l'écran : une carte qui change d'état a le même identifiant des deux
 * côtés. Chaque cas passe par la vraie route, dans la transaction du décor (`HarvestFixtureTest`), et ne
 * regarde que les interventions qu'il pose.
 *
 * Lancement : dev/test package=planning file=TaskWeekColumns
 */
class TaskWeekColumnsTest extends HarvestFixtureTest {

	/**
	 * `?view=columns` partage les interventions de la semaine par état et retient le choix sur le membre. Le
	 * masque de ce qui est fait n'y retire rien, sans que le réglage du membre change.
	 */
	public function testSplit(): void {

		$this->setHideDone(TRUE);

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document, $data] = $this->renderColumns();

		$action = $eTaskTodo['action']['id'];

		$this->assertTrue($data->columns, '?view=columns ne rend pas les Colonnes');
		$this->assertEquals(\structure\Member::COLUMNS, \structure\StructureLib::getById($this->eStructure['id'])->getView('viewPlanningWeek'), 'le choix des Colonnes n\'est pas retenu sur le membre');
		$this->assertTrue($this->eStructure->getView('viewPlanningHideDone'), 'les Colonnes ont changé le masque du membre');

		$this->assertTrue($data->ccTaskTodo->offsetExists($action) and $data->ccTaskTodo[$action]->offsetExists($eTaskTodo['id']), 'l\'intervention à faire n\'est pas dans « À faire »');
		$this->assertFalse($data->ccTaskTodo->offsetExists($action) and $data->ccTaskTodo[$action]->offsetExists($eTaskDone['id']), 'l\'intervention faite est dans « À faire »');
		$this->assertTrue($data->ccTaskDone->offsetExists($action) and $data->ccTaskDone[$action]->offsetExists($eTaskDone['id']), 'sous le masque, l\'intervention faite n\'est pas dans « Fait »');
		$this->assertEquals($data->ccTaskTodo->countByDepth(2), $data->countByStatus[\series\Task::TODO], 'le compte de l\'onglet « À faire » n\'est pas celui de sa colonne');
		$this->assertEquals($data->ccTaskDone->countByDepth(2), $data->countByStatus[\series\Task::DONE], 'le compte de l\'onglet « Fait » n\'est pas celui de sa colonne');

		$this->assertTrue($document->querySelector('#planning-week-todo #planning-week-todo-block-'.$action.' #planning-task-'.$eTaskTodo['id']) !== NULL, 'la carte à faire n\'est pas dans le bloc de sa colonne');
		$this->assertTrue($document->querySelector('#planning-week-done #planning-week-done-block-'.$action.' #planning-task-'.$eTaskDone['id']) !== NULL, 'la carte faite n\'est pas dans le bloc de sa colonne');

	}

	/**
	 * Le « + » d'un bloc crée dans l'état de sa colonne, et le panneau qu'il ouvre le lit : sans `status`, la
	 * Semaine en cours ouvre toujours sur Fait.
	 */
	public function testCreateStatus(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderColumns();

		$action = $eTask['action']['id'];

		foreach([\series\Task::TODO, \series\Task::DONE] as $status) {

			$href = $document->querySelector('#planning-week-'.$status.'-block-'.$action.' a[href*="taskWeek:create"]')?->getAttribute('href') ?? '';

			$this->assertTrue(str_contains($href, 'status='.$status), 'le « + » de « '.$status.' » ne demande pas son état');
			$this->assertTrue(str_contains($href, 'view='.\structure\Member::COLUMNS), 'le « + » de « '.$status.' » ne réémet pas les Colonnes');

		}

		$this->assertEquals(\series\Task::TODO, $this->callCreatePanel(['status' => \series\Task::TODO])?->status, 'le « + » de « À faire » ouvre sur Fait');
		$this->assertEquals(\series\Task::DONE, $this->callCreatePanel([])?->status, 'sans état demandé, le panneau de la Semaine en cours n\'ouvre plus sur Fait');

	}

	/**
	 * Hors de la semaine en cours, le « + » de « Fait » ouvre une semaine passée sur Fait à son dernier jour, et
	 * n'existe pas sur une semaine à venir, qu'aucun jour ne peut porter Fait. Sans état demandé, une semaine
	 * passée ouvre toujours sur « À faire », jour non défini, comme aux Cartes.
	 */
	public function testCreateOtherWeeks(): void {

		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');
		$weekAfter = toWeek(week_date_starts(\Clock::week()).' +7 days');

		$data = $this->callCreatePanel(['status' => \series\Task::DONE, 'week' => $weekBefore]);
		$this->assertEquals(\series\Task::DONE, $data?->status, 'le « + » de « Fait » d\'une semaine passée n\'ouvre pas sur Fait');
		$this->assertEquals(week_dates($weekBefore)[6], $data?->date, 'le « + » de « Fait » d\'une semaine passée n\'ouvre pas sur son dernier jour');

		$data = $this->callCreatePanel(['week' => $weekBefore]);
		$this->assertEquals(\series\Task::TODO, $data?->status, 'sans état demandé, une semaine passée n\'ouvre plus sur « À faire »');
		$this->assertEquals(NULL, $data?->date, 'sans état demandé, une semaine passée pose un jour');

		[, $data] = $this->renderColumns($weekAfter);
		$this->assertFalse($data->canCreateByStatus[\series\Task::DONE], 'une semaine à venir peut créer dans « Fait »');

		[, $data] = $this->renderColumns();
		$this->assertTrue($data->canCreateByStatus[\series\Task::DONE], 'la semaine en cours ne peut plus créer dans « Fait »');

	}

	/**
	 * La coche fait passer la carte de « À faire » à « Fait » : elle se retire de sa colonne de départ, par un
	 * sélecteur borné à cette colonne, et la colonne d'arrivée se rend entière, avec la carte éclairée. Les
	 * comptes des onglets suivent. La décoche fait le chemin inverse.
	 */
	public function testDoneAndTodo(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		// Une carte reste de chaque côté : sans elle, la colonne de départ se viderait et se rendrait entière.
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		foreach([
			['doDone', \series\Task::TODO, \series\Task::DONE],
			['doTodo', \series\Task::DONE, \series\Task::TODO],
		] as [$route, $from, $to]) {

			[$data, $instructions, $action] = $this->callTask($route, $eTask, ['view' => \structure\Member::COLUMNS]);

			if($this->assertTrue($action instanceof \ViewAction, $route.' est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
				return;
			}

			$targets = $this->getTargets($instructions);

			$this->assertEquals('columns', $data->change, $route.' ne répond pas en Colonnes');
			$this->assertEquals('column', $data->changeColumns[$to], $route.' ne rend pas la colonne d\'arrivée entière');
			$this->assertEquals([$eTask['id']], $data->changeColumns[$from]['remove'], $route.' ne retire pas la carte de sa colonne de départ');
			$this->assertEquals([$eTask['id']], $data->idsArrived, $route.' n\'éclaire pas la carte arrivée');
			$this->assertEquals([$to], $data->statusesArrived, $route.' n\'éclaire pas l\'onglet qui monte');

			$this->assertTrue(in_array('remove #planning-week-'.$from.' #planning-task-'.$eTask['id'], $targets), $route.' ne borne pas le retrait à la colonne de départ');
			$this->assertTrue(in_array('outerHtml #planning-week-'.$to, $targets), $route.' ne rend pas la colonne d\'arrivée');
			$this->assertTrue(in_array('outerHtml #planning-week-'.$from.'-counter-'.$eTask['action']['id'], $targets), $route.' ne recompte pas le bloc de départ');
			$this->assertTrue(in_array('outerHtml #planning-week-todo-counter', $targets) and in_array('outerHtml #planning-week-done-counter', $targets), $route.' ne recompte pas les onglets');

			// Le retrait vise la carte avant que la colonne d'arrivée ne pose sa jumelle.
			$this->assertTrue(array_search('remove #planning-week-'.$from.' #planning-task-'.$eTask['id'], $targets) < array_search('outerHtml #planning-week-'.$to, $targets), $route.' rend la colonne d\'arrivée avant de retirer la carte de départ');

			$this->assertFalse(in_array('outerHtml #planning-week-board', $targets), $route.' rend le tableau entier');

		}

	}

	/**
	 * Une carte cochée depuis « À faire » y reste, faite, et sa copie entre dans « Fait » (R-Retours-1210 V40) :
	 * « À faire » remplace la carte sur place et ne la compte plus, « Fait » se rend entière avec la copie
	 * éclairée. Décochée depuis sa copie, la carte se remplace dans « À faire » et la copie se retire, chacune par
	 * son identifiant.
	 */
	public function testKept(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		$id = $eTask['id'];
		$action = $eTask['action']['id'];
		$input = ['view' => \structure\Member::COLUMNS, 'kept' => [$id => 1]];

		[$data, $instructions, $route] = $this->callTask('doDone', $eTask, $input);

		if($this->assertTrue($route instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($route))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertEquals([$id => $action], $data->changeColumns[\series\Task::TODO]['card'], 'la carte cochée ne reste pas dans « À faire »');
		$this->assertEquals('column', $data->changeColumns[\series\Task::DONE], 'la copie n\'entre pas dans « Fait »');
		$this->assertTrue($data->ccTaskDone[$action][$id]['keptCopy'], 'la carte de « Fait » n\'est pas la copie de la carte gardée');
		$this->assertFalse($data->ccTaskTodo[$action][$id]['keptCopy'], 'la carte gardée est marquée comme copie');
		$this->assertEquals(1, $data->countByAction[\series\Task::TODO][$action], 'le bloc de « À faire » compte la carte gardée');
		$this->assertEquals($data->ccTaskDone->countByDepth(2), $data->countByStatus[\series\Task::DONE], 'l\'onglet « Fait » ne compte pas la copie');
		$this->assertEquals([$id], $data->idsArrivedKept, 'la copie arrivée n\'est pas désignée');
		$this->assertTrue(in_array('outerHtml #planning-week-todo #planning-task-'.$id, $targets), 'la carte gardée n\'est pas remplacée dans « À faire »');

		[$data, $instructions, $route] = $this->callTask('doTodo', $eTask, $input);

		if($this->assertTrue($route instanceof \ViewAction, 'la décoche est refusée : '.implode(', ', $this->getFails($route))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertEquals([$id => $action], $data->changeColumns[\series\Task::TODO]['card'], 'la décoche ne remplace pas la carte gardée');
		$this->assertEquals([$id], $data->changeColumns[\series\Task::DONE]['kept'], 'la décoche ne vise pas la copie');
		$this->assertTrue(in_array('remove #planning-week-done #planning-task-'.$id.'-kept', $targets), 'la copie ne se retire pas de « Fait »');

	}

	/**
	 * Décochée depuis sa copie quand elle était la seule intervention faite de la semaine, la carte vide
	 * « Fait », qui se rend entière : aucune copie n'y reste à viser.
	 */
	public function testKeptLastDone(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderColumns();

		if($this->assertDecorPresent($document->querySelector('#planning-week-done .planning-week-block') === NULL, 'aucune intervention faite cette semaine') === FALSE) {
			return;
		}

		$input = ['view' => \structure\Member::COLUMNS, 'kept' => [$eTask['id'] => 1]];

		$this->callTask('doDone', $eTask, $input);

		[$data, , $route] = $this->callTask('doTodo', $eTask, $input);

		if($this->assertTrue($route instanceof \ViewAction, 'la décoche est refusée : '.implode(', ', $this->getFails($route))) === FALSE) {
			return;
		}

		$this->assertEquals('column', $data->changeColumns[\series\Task::DONE], 'la colonne vidée par la décoche ne se rend pas entière');
		$this->assertEquals(0, $data->countByStatus[\series\Task::DONE], 'l\'onglet « Fait » compte encore la copie');

	}

	/**
	 * Le serveur ne garde que ce que l'écran peut garder : une intervention faite de la semaine. Un identifiant
	 * à faire ou hors de la semaine, réémis par erreur ou forgé, n'ouvre aucune copie.
	 */
	public function testKeptBounded(): void {

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');
		$eTaskOther = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $weekBefore, week_date_day($weekBefore, 1));

		[, $data] = $this->renderColumns();

		$columns = TaskWeekLib::getColumns($data->cTask, [$eTaskTodo['id'], $eTaskOther['id']], $data->order);

		foreach($columns['ccTaskDone'] as $cTaskAction) {
			$this->assertEquals(0, $cTaskAction->find(fn($eTask) => $eTask['keptCopy'])->count(), 'un identifiant réémis hors des interventions faites de la semaine ouvre une copie');
		}

		$this->assertEquals($data->countByStatus, $columns['countByStatus'], 'un identifiant réémis hors des interventions faites de la semaine change les comptes');

	}

	/**
	 * La dernière carte d'une colonne qui part vide cette colonne : elle se rend entière, et dit qu'elle est
	 * vide.
	 */
	public function testColumnEmptied(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderColumns();

		if($this->assertTrue($document->querySelector('#planning-week-done .planning-week-block') === NULL, 'le décor a déjà une intervention faite cette semaine') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTask('doDone', $eTask, ['view' => \structure\Member::COLUMNS]);

		$this->assertEquals('column', $data?->changeColumns[\series\Task::TODO], 'la colonne vidée ne se rend pas entière');
		$this->assertEquals('column', $data?->changeColumns[\series\Task::DONE], 'la colonne d\'arrivée ne se rend pas entière');
		$this->assertEquals(0, $data?->countByStatus[\series\Task::TODO], 'l\'onglet « À faire » compte encore la carte partie');

	}

	/**
	 * Un geste qui ne change pas l'état garde la réponse des Cartes, dans la colonne de la carte : la carte
	 * remplacée sur place, aucune colonne rendue.
	 */
	public function testTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$data, $instructions, $action] = $this->callTask('doTime', $eTask, ['view' => \structure\Member::COLUMNS, 'time' => 0.5]);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertEquals([$eTask['id'] => $eTask['action']['id']], $data->changeColumns[\series\Task::DONE]['card'], 'le temps ne remplace pas la carte dans « Fait »');
		$this->assertTrue(in_array('outerHtml #planning-week-done #planning-task-'.$eTask['id'], $targets), 'la carte n\'est pas visée dans sa colonne');
		$this->assertFalse(in_array('outerHtml #planning-week-done', $targets) or in_array('outerHtml #planning-week-todo', $targets), 'un geste de temps rend une colonne entière');
		$this->assertEquals([], $data->idsArrived, 'un geste de temps éclaire la carte comme arrivée');

	}

	/**
	 * Une intervention non planifiée cochée entre dans « Fait » : la colonne se rend entière, et rien ne se
	 * retire de « À faire », où elle n'avait pas de carte.
	 */
	public function testUnplannedArrives(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		[$data, $instructions, $action] = $this->callTask('doDone', $eTask, ['view' => \structure\Member::COLUMNS, 'unplanned' => '1']);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		if($data->change === 'screen') {
			$this->assertTrue(FALSE, 'le décor rend l\'écran entier : la semaine était vide');
			return;
		}

		$this->assertEquals('column', $data->changeColumns[\series\Task::DONE], 'l\'intervention arrivée ne rend pas « Fait » entière');
		$this->assertFalse(in_array('remove #planning-week-todo #planning-task-'.$eTask['id'], $targets), 'l\'arrivée retire une carte de « À faire »');
		$this->assertEquals([$eTask['id']], $data->idsArrived, 'l\'intervention arrivée n\'est pas éclairée');

	}

	/**
	 * Une intervention en retard se coche dans sa propre semaine (`TaskWeek.t.php`, `testTimeExpectedWeek()`) : elle
	 * quitte « En retard » sans entrer dans « Fait ». Comme aux Cartes, les colonnes ne répondent rien, et la
	 * bulle du temps, qui n'aurait pas de carte après laquelle se poser, n'est pas rendue.
	 */
	public function testLateLeaves(): void {

		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);

		// « En retard » reste affiché après la coche, sans quoi l'écran se rendrait entier.
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$data, $instructions, $action] = $this->callTask('doDone', $eTask, ['view' => \structure\Member::COLUMNS]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertEquals(NULL, $data->change, 'une intervention en retard cochée dans sa semaine fait répondre les colonnes');
		$this->assertTrue(in_array('remove #planning-task-'.$eTask['id'], $targets), 'la carte ne quitte pas « En retard »');
		$this->assertFalse(in_array('insertAdjacentHtml #planning-task-'.$eTask['id'], $targets), 'la bulle vise une carte retirée');
		$this->assertEquals([], array_filter($targets, fn(string $target) => str_contains($target, '#planning-week-todo') or str_contains($target, '#planning-week-done')), 'les colonnes reçoivent une instruction');

	}

	/**
	 * Affecter une personne à un lot ne change pas d'état : chaque carte se remplace dans sa colonne, sans
	 * colonne rendue.
	 */
	public function testBatchUser(): void {

		$cTask = new \Collection([
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week()),
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week()),
		]);
		$cTask->setDepth(1);

		[$data, $instructions, $action] = $this->callBatch('doBatchUser', $cTask, ['view' => \structure\Member::COLUMNS, 'user' => $this->getMember()['id'], 'action' => 'add']);

		if($this->assertTrue($action instanceof \ViewAction, 'l\'affectation est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertTrue(is_array($data->changeColumns[\series\Task::TODO]), 'l\'affectation rend « À faire » entière');

		foreach($cTask as $eTask) {
			$this->assertTrue(in_array('outerHtml #planning-week-todo #planning-task-'.$eTask['id'], $targets), 'la carte '.$eTask['id'].' n\'est pas remplacée dans « À faire »');
		}

		$this->assertEquals([], $data->idsArrived, 'l\'affectation éclaire des cartes comme arrivées');

	}

	/**
	 * Un lot fait des seules cartes « En retard » ne bouge aucune colonne : la réponse se rend, les colonnes ne
	 * reçoivent rien, et la carte se remplace dans sa zone.
	 */
	public function testBatchZoneOnly(): void {

		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$cTask = new \Collection([$eTask]);
		$cTask->setDepth(1);

		[$data, $instructions, $action] = $this->callBatch('doBatchUser', $cTask, ['view' => \structure\Member::COLUMNS, 'user' => $this->getMember()['id'], 'action' => 'add']);

		if($this->assertTrue($action instanceof \ViewAction, 'l\'affectation est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertEquals(NULL, $data->change, 'un lot de cartes de zone fait répondre les colonnes');
		$this->assertEquals([], array_filter($targets, fn(string $target) => str_contains($target, '#planning-week-todo') or str_contains($target, '#planning-week-done')), 'les colonnes reçoivent une instruction');
		$this->assertTrue(in_array('outerHtml #planning-task-'.$eTask['id'], $targets), 'la carte n\'est pas remplacée dans « En retard »');
		$this->assertEquals([], array_filter($targets, fn(string $target) => str_starts_with($target, 'remove ')), 'la réponse retire une carte qu\'aucune colonne ne porte');

	}

	/**
	 * Reporter à une autre semaine fait sortir la carte de sa colonne, qui se recompte sans se rendre entière.
	 */
	public function testBatchPlannedWeek(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$cTask = new \Collection([$eTask]);
		$cTask->setDepth(1);

		[$data, $instructions, $action] = $this->callBatch('doBatchPlannedWeek', $cTask, ['view' => \structure\Member::COLUMNS, 'plannedWeek' => toWeek(week_date_starts(\Clock::week()).' +7 days')]);

		if($this->assertTrue($action instanceof \ViewAction, 'le report est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertTrue(is_array($data->changeColumns[\series\Task::TODO]), 'le report rend « À faire » entière');
		$this->assertTrue(in_array('remove #planning-week-todo #planning-task-'.$eTask['id'], $targets), 'la carte reportée ne sort pas de « À faire »');
		$this->assertTrue(in_array('outerHtml #planning-week-todo-counter-'.$eTask['action']['id'], $targets), 'le bloc quitté ne se recompte pas');

	}

	/**
	 * « Récolter » sur une carte écrit l'intervention que la saisie désigne, qui peut être une autre que la
	 * carte : sans état d'avant pour elle, les deux colonnes se rendent entières.
	 */
	public function testHarvest(): void {

		if($this->assertTrue(\planning\HarvestWeekLib::canHarvest($this->eStructure), 'la structure '.static::STRUCTURE.' récolte depuis la Semaine') === FALSE) {
			return;
		}

		$eCultivation = $this->getCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$action = $this->executePage('planning/taskWeek:doHarvest', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'task' => $eTask['id'],
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
			'done' => '1',
			'panel' => 1,
			'view' => \structure\Member::COLUMNS,
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$data] = $this->runView('doTask', $action, 'taskWeek');

		$this->assertEquals('columns', $data->change, 'la récolte ne répond pas en Colonnes');

		foreach([\series\Task::TODO, \series\Task::DONE] as $status) {
			$this->assertEquals('column', $data->changeColumns[$status], 'la récolte ne rend pas « '.$status.' » entière');
		}

	}

	/**
	 * Une coche de lot fait passer chaque carte, et chacune se retire de sa colonne de départ.
	 */
	public function testBatchDone(): void {

		$cTask = new \Collection([
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week()),
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week()),
		]);
		$cTask->setDepth(1);

		$eTaskStaying = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$data, $instructions, $action] = $this->callBatch('doBatchDone', $cTask, ['view' => \structure\Member::COLUMNS]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche de lot est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$targets = $this->getTargets($instructions);

		$this->assertEquals('column', $data->changeColumns[\series\Task::DONE], 'le lot ne rend pas « Fait » entière');

		foreach($cTask as $eTask) {
			$this->assertTrue(in_array('remove #planning-week-todo #planning-task-'.$eTask['id'], $targets), 'la carte '.$eTask['id'].' ne se retire pas de « À faire »');
		}

		$this->assertFalse(in_array('remove #planning-week-todo #planning-task-'.$eTaskStaying['id'], $targets), 'le lot retire une carte qu\'il ne touche pas');

	}

	/**
	 * « Nouvelle intervention » en Colonnes rend la colonne de l'état créé, sans toucher l'autre.
	 */
	public function testCreate(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$action = $this->executePage('planning/taskWeek:doCreate', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'when' => \Clock::week(),
			'view' => \structure\Member::COLUMNS,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'status' => \series\Task::TODO,
			'day' => '',
			'description' => '',
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la création est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$data, $template] = $this->runView('doCreate', $action, 'taskWeek');

		$this->assertEquals('columns', $data->change, 'la création ne répond pas en Colonnes');
		$this->assertEquals('column', $data->changeColumns[\series\Task::TODO], 'la création ne rend pas « À faire »');
		$this->assertTrue(is_array($data->changeColumns[\series\Task::DONE]), 'la création rend « Fait », qu\'elle ne touche pas');
		$this->assertEquals($data->cTaskCreated->getIds(), $data->idsArrived, 'l\'intervention créée n\'est pas éclairée');

	}

	/**
	 * Au téléphone, en Colonnes, la coche ouvre la feuille du temps et pas la bulle, puisque la carte part dans
	 * l'onglet caché. Au bureau, et dans les Cartes quelle que soit la largeur, la bulle reste.
	 */
	public function testNarrowSheet(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		foreach([
			'Colonnes, téléphone' => [\structure\Member::COLUMNS, 1, TRUE],
			'Colonnes, bureau' => [\structure\Member::COLUMNS, 0, FALSE],
			'Cartes, téléphone' => [\structure\Member::CARDS, 1, FALSE],
		] as $case => [$view, $narrow, $sheet]) {

			$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

			[$data, $instructions, $action] = $this->callTask('doDone', $eTask, ['view' => $view, 'narrow' => $narrow]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la coche est refusée') === FALSE) {
				continue;
			}

			$encoded = json_encode($instructions);

			$this->assertEquals($sheet, $data->timeSheet, $case.' : la page ne choisit pas la bonne saisie');
			$this->assertEquals($sheet, str_contains($encoded, 'PlanningTaskSheet.start'), $case.' : la feuille du temps');
			$this->assertEquals($sheet === FALSE, str_contains($encoded, 'planning-task-bubble-'.$eTask['id']), $case.' : la bulle du temps');

		}

	}

	private function renderColumns(string $week = ''): array {

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/semaine/'.($week ?: \Clock::week()), ['view' => \structure\Member::COLUMNS], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'l\'écran des interventions ne se rend pas');

		[$data, , $html] = $this->runView('taskWeek', $action, 'taskWeek');

		return [
			\Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR),
			$data
		];

	}

	private function callCreatePanel(array $input): ?\stdClass {

		$action = $this->executePage('planning/taskWeek:create', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'view' => \structure\Member::COLUMNS,
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

	}

	private function callBatch(string $route, \Collection $cTask, array $input = []): array {

		$action = $this->executePage('planning/taskWeek:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'ids' => $cTask->getIds(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doBatch', $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Les nœuds qu'une réponse vise, dans l'ordre, sous la forme « <méthode> <sélecteur> ».
	 */
	private function getTargets(array $instructions): array {

		$targets = [];

		foreach($instructions as $instruction) {
			foreach($instruction[2] ?? [] as $call) {
				$targets[] = $call[0].' '.$instruction[1];
			}
		}

		return $targets;

	}

}
?>
