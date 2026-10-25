<?php
namespace planning;

/**
 * Les gestes de lot de la Semaine et du Jour (L5d, `TaskBatchUi`) : chacun écrit ce qu'écrit le geste
 * de l'ancien planning, par la même méthode de `\series\TaskLib`, et refuse ce qu'il refuse. Ce que la
 * réponse redessine se vérifie dans `FingerprintTest`.
 *
 * Lancement : dev/test package=planning file=TaskBatch
 */
class TaskBatchTest extends HarvestFixtureTest {

	public function testDone(): void {

		$cTask = $this->createTasks();

		[$data, , $action] = $this->callBatch('doBatchDone', $cTask);

		if($this->assertTrue($action instanceof \ViewAction, 'le lot « Fait » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('batch', $data->gesture, 'le lot « Fait » ne vide pas la sélection');

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals(\series\Task::DONE, $eTask['status'], 'une intervention du lot « Fait » n\'est pas faite');
		}

	}

	public function testTodo(): void {

		$cTask = $this->createTasks();
		$this->callBatch('doBatchDone', $cTask);

		[, , $action] = $this->callBatch('doBatchTodo', $cTask);

		$this->assertTrue($action instanceof \ViewAction, 'le lot « À faire » est refusé : '.implode(', ', $this->getFails($action)));

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals(\series\Task::TODO, $eTask['status'], 'une intervention du lot « À faire » n\'est pas à faire');
		}

	}

	public function testDoneRefusedOnDone(): void {

		$cTask = $this->createTasks();
		$this->callBatch('doBatchDone', $cTask);

		[, , $action] = $this->callBatch('doBatchDone', $cTask);

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'le lot « Fait » accepte des interventions déjà faites');

	}

	public function testPlannedDate(): void {

		$cTask = $this->createTasks();
		$thursday = week_date_day(\Clock::week(), 4);

		[, , $action] = $this->callBatch('doBatchPlannedDate', $cTask, ['plannedDate' => $thursday]);

		$this->assertTrue($action instanceof \ViewAction, 'le lot « Planifier » sur un jour est refusé : '.implode(', ', $this->getFails($action)));

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals($thursday, $eTask['plannedDate'], 'une intervention du lot n\'est pas posée sur le jour choisi');
		}

	}

	public function testPlannedWeek(): void {

		$cTask = $this->createTasks();
		$next = TaskDayLib::getNextWeek(\Clock::week());

		// Une intervention faite que le lot ne touche pas : sans elle, le lot viderait l'écran, qui se rendrait
		// entier pour retirer la rangée des outils (R-Retours-0410, point 18).
		$this->createTask($this->getCultivation(), ACTION_FERTILISATION, \series\Task::DONE, \Clock::week());

		[$data, , $action] = $this->callBatch('doBatchPlannedWeek', $cTask, ['plannedWeek' => $next]);

		if($this->assertTrue($action instanceof \ViewAction, 'le lot « Planifier » sur une semaine est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals($next, $eTask['plannedWeek'], 'une intervention du lot n\'est pas reportée à la semaine choisie');
		}

		// Une intervention qui vide son bloc le retire seul (`emptied`, R-Retours-0810, 222), les autres retirent leur carte.
		$this->assertTrue(
			$data->change === 'board' or (
				is_array($data->change) and
				array_keys($data->change) === $cTask->getIds() and
				array_diff($data->change, ['remove', 'emptied']) === []
			),
			'les interventions parties de la semaine ne se retirent pas de l\'écran'
		);

	}

	public function testIncrement(): void {

		$cTask = $this->createTasks();

		[, , $action] = $this->callBatch('doBatchIncrement', $cTask, ['increment' => 1]);

		$this->assertTrue($action instanceof \ViewAction, 'le lot « Décaler » est refusé : '.implode(', ', $this->getFails($action)));

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals(TaskDayLib::getNextWeek(\Clock::week()), $eTask['plannedWeek'], 'une intervention du lot n\'est pas décalée d\'une semaine');
		}

	}

	/**
	 * Décalée à la semaine suivante, une intervention où l'on a saisi du temps reste dans la semaine, et sa
	 * carte redessinée dit sa semaine prévue (FZ, `TaskCardUi::hasOtherWeek()`). Sans temps, elle part. La
	 * voisine restée dans le bloc fait de chaque réponse la seule attendue : seule, l'intervention viderait son
	 * bloc (`emptied`) ou l'écran (`board`).
	 */
	public function testIncrementStarted(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$eTaskStarted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTaskStarted, 0.5);

		$eTaskIdle = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$next = TaskDayLib::getNextWeek(\Clock::week());

		foreach([
			[$eTaskStarted, 'card', 'commencée dans la semaine'],
			[$eTaskIdle, 'remove', 'sans temps dans la semaine'],
		] as [$eTask, $change, $label]) {

			[$data, , $action] = $this->callBatch('doBatchIncrement', new \Collection([$eTask['id'] => $eTask]), ['increment' => 1]);

			if($this->assertTrue($action instanceof \ViewAction, 'le lot « Décaler » est refusé sur l\'intervention '.$label.' : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$this->assertEquals($next, $this->reload(new \Collection([$eTask]))->first()['plannedWeek'], 'l\'intervention '.$label.' n\'est pas décalée d\'une semaine');
			$this->assertEquals([$eTask['id'] => $change], $data->change, 'l\'intervention '.$label.' n\'a pas la réponse attendue');

			if($change === 'card') {
				$this->assertTrue(TaskCardUi::hasOtherWeek($data->ccTask[$eTask['action']['id']][$eTask['id']], \Clock::week()), 'la carte redessinée de l\'intervention décalée ne dit pas sa semaine prévue');
			}

		}

	}

	/**
	 * Une intervention faite ne se décale pas (`Task::acceptPostpone()`), et le lot qui la porte est
	 * refusé entier, comme dans l'ancien planning.
	 */
	public function testIncrementRefusedOnDone(): void {

		$cTask = $this->createTasks();
		$this->callBatch('doBatchDone', new \Collection([$cTask->first()['id'] => $cTask->first()]));

		[, , $action] = $this->callBatch('doBatchIncrement', $cTask, ['increment' => 1]);

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'le lot « Décaler » accepte une intervention faite');

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals(\Clock::week(), $eTask['plannedWeek'], 'le lot refusé a décalé une intervention');
		}

	}

	public function testUser(): void {

		$cTask = $this->createTasks();
		$eUser = $this->getMember();

		[, $instructions, $action] = $this->callBatch('doBatchUser', $cTask, ['user' => $eUser['id'], 'action' => 'add']);

		if($this->assertTrue($action instanceof \ViewAction, 'le lot « Affecter » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue($this->clearsSelection($instructions), 'le lot « Affecter » de la Semaine garde la sélection, que toute action de la barre vide (retour 253)');

		foreach($this->reload($cTask) as $eTask) {
			$this->assertTrue(in_array($eUser['id'], $eTask['plannedUsers'] ?? [], TRUE), 'une intervention du lot n\'est pas affectée');
		}

		$this->callBatch('doBatchUser', $cTask, ['user' => $eUser['id'], 'action' => 'delete']);

		foreach($this->reload($cTask) as $eTask) {
			$this->assertFalse(in_array($eUser['id'], $eTask['plannedUsers'] ?? [], TRUE), 'une intervention du lot reste affectée après le retrait');
		}

	}

	public function testDayUser(): void {

		$cTask = $this->createTasks();
		$eUser = $this->getMember();

		[, $instructions, $action] = $this->callDay('doBatchUser', ['ids' => $cTask->getIds(), 'user' => $eUser['id'], 'action' => 'add']);

		if($this->assertTrue($action instanceof \ViewAction, 'le lot « Affecter » du Jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue($this->clearsSelection($instructions), 'le lot « Affecter » du Jour garde la sélection, que toute action de la barre vide (retour 253)');

	}

	public function testUserRefusedOutsideStructure(): void {

		$cTask = $this->createTasks();

		// Un compte du décor qui n'appartient à aucune structure (`FixtureAiLib::USERS`).
		$eUser = \user\User::model()
			->select('id')
			->whereEmail('decor.sans.structure@example.org')
			->get();

		if($this->assertTrue($eUser->notEmpty() and \structure\MemberLib::isMember($eUser, $this->eStructure) === FALSE, 'le décor n\'a plus de compte hors de toute structure') === FALSE) {
			return;
		}

		[, , $action] = $this->callBatch('doBatchUser', $cTask, ['user' => $eUser['id'], 'action' => 'add']);

		$this->assertTrue($action instanceof \NotExpectedAction, 'le lot « Affecter » accepte une personne hors de la structure');

	}

	public function testDelete(): void {

		$cTask = $this->createTasks();

		[$data, , $action] = $this->callBatch('doBatchDelete', $cTask);

		if($this->assertTrue($action instanceof \ViewAction, 'le lot « Supprimer » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(0, $this->reload($cTask)->count(), 'une intervention du lot « Supprimer » est restée');

	}

	/**
	 * Une intervention d'une autre structure, postée sur l'adresse de la nôtre : le lot est refusé avant
	 * toute écriture.
	 */
	public function testForeignRefused(): void {

		$eTaskForeign = $this->createTaskForeign();

		[, , $action] = $this->callBatch('doBatchDone', new \Collection([$eTaskForeign['id'] => $eTaskForeign]));

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'le lot accepte une intervention d\'une autre structure');
		$this->assertEquals(\series\Task::TODO, \series\Task::model()->select('status')->whereId($eTaskForeign['id'])->getValue('status'), 'le lot refusé a écrit sur l\'intervention d\'une autre structure');

	}

	/**
	 * Le Jour : les mêmes routes, rendues par la vue de ses gestes.
	 */
	public function testDayDone(): void {

		$cTask = $this->createTasks();

		[$data, , $action] = $this->callDay('doBatchDone', ['ids' => $cTask->getIds()]);

		if($this->assertTrue($action instanceof \ViewAction, 'le lot « Fait » du Jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([TaskDayLib::NONE, \Clock::date()], $data->change, 'le lot « Fait » du Jour ne redessine pas « Sans jour » et aujourd\'hui');

	}

	/**
	 * « Temps » sur un lot (R-Retours-1210, lot BG) : un total pour la journée, partagé entre les interventions
	 * cochées. Deux plantations d'une série sans surface n'ont que le partage égalitaire : 1h30 donne 45 minutes
	 * à chacune, qui s'ajoutent au temps déjà saisi, comme « Compléter pour » d'avant. Celle à faire passe faite.
	 */
	public function testTime(): void {

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		$this->createTimesheet($eTaskDone, 0.5);

		$cTask = new \Collection([$eTaskTodo['id'] => $eTaskTodo, $eTaskDone['id'] => $eTaskDone]);

		[$data, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5']);

		if($this->assertTrue($action instanceof \ViewAction, '« Temps » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('batchTime', $data->gesture, '« Temps » ne ferme pas son panneau');

		$this->assertEquals([\Clock::date() => 0.75], $this->getTimes($eTaskTodo), 'l\'intervention à faire ne reçoit pas la moitié du total');
		$this->assertEquals([\Clock::date() => 1.25], $this->getTimes($eTaskDone), 'la part de l\'intervention faite ne s\'ajoute pas à son temps du jour');

		$this->assertEquals(\series\Task::DONE, \series\Task::model()->select('status')->whereId($eTaskTodo['id'])->getValue('status'), 'l\'intervention à faire qui a reçu du temps n\'est pas passée faite');

	}

	/**
	 * Des récoltes de la même unité se partagent le total à la quantité récoltée le jour choisi (arbitrage de
	 * du 12/10), sans clé postée : 1, 2 et 3 kg pour 1h30 donnent 15, 30 et 45 minutes, et une récolte
	 * cochée sans quantité ce jour ne reçoit rien. Celle-ci est à faire et n'a encore rien reçu, donc pas d'unité,
	 * le cas le plus courant : elle ne fait pas perdre la quantité aux autres. Les parts relues en base font le
	 * total.
	 */
	public function testTimeHarvest(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cTask = new \Collection();

		foreach([1.0, 2.0, 3.0] as $quantity) {
			$eTask = $this->createTaskHarvest($this->getCultivation(), \Clock::week(), \Clock::date());
			$this->createHarvest($eTask, $quantity, \Clock::date());
			$cTask[$eTask['id']] = $eTask;
		}

		$eTaskEmpty = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		if($this->assertDecorPresent(\series\TaskLib::getById($eTaskEmpty['id'])['harvestUnit'] === NULL, 'une récolte à faire sans quantité n\'a pas d\'unité') === FALSE) {
			return;
		}

		$cTask[$eTaskEmpty['id']] = $eTaskEmpty;

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5', 'distribution' => 'fair']);

		if($this->assertTrue($action instanceof \ViewAction, '« Temps » sur des récoltes est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$times = [];

		foreach($cTask as $eTask) {
			$times[] = $this->getTimes($eTask)[\Clock::date()] ?? 0.0;
		}

		$this->assertEquals([0.25, 0.5, 0.75, 0.0], $times, 'les récoltes ne se partagent pas le total à la quantité du jour');

		$total = \series\Timesheet::model()
			->whereTask('IN', $cTask->getIds())
			->whereUser(\user\ConnectionLib::getOnline())
			->getValue(new \Sql('SUM(time)', 'float'));

		$this->assertEquals(1.5, round($total, 2), 'les parts écrites ne font pas le total');

	}

	/**
	 * Des récoltes cochées dont aucune n'a de quantité le jour choisi n'ont rien pour départager le total : le
	 * lot est refusé sans rien écrire.
	 */
	public function testTimeHarvestless(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eTask = $this->createTaskHarvest($this->getCultivation(), \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, week_date_day(\Clock::week(), 1));

		$cTask = new \Collection([$eTask['id'] => $eTask]);

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1']);

		$this->assertEquals(['Timesheet::time.harvestless'], $this->getFails($action), 'des récoltes sans quantité ce jour ne refusent pas le total');
		$this->assertEquals([], $this->getTimes($eTask), 'le lot refusé a écrit du temps');

	}

	/**
	 * Des plantations de séries qui ont une surface, sur des cultures qui ont une densité, offrent la surface, le
	 * nombre de plants et les parts égales, plants d'office. Le partage suit la clé postée, et une clé que la
	 * sélection n'offre pas est refusée.
	 */
	public function testTimeDistribution(): void {

		$cTask = new \Collection();

		foreach([[30, 4.0], [60, 1.0]] as [$area, $density]) {

			$eCultivation = $this->createCultivation($this->createSeries('Série de '.$area.' m²'));

			// La surface d'une série se calcule de ses emplacements, que le décor ne pose pas : elle s'écrit
			// ici telle que le calcul l'aurait laissée, seule base que lit le partage.
			$eCultivation['series']['area'] = $area;
			\series\SeriesLib::update($eCultivation['series'], ['area']);

			$eCultivation['series'] = \series\SeriesLib::getById($eCultivation['series']['id']);
			$eCultivation['distance'] = \series\Cultivation::DENSITY;
			$eCultivation['density'] = $density;
			\series\CultivationLib::update($eCultivation, ['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing']);

			$eTask = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
			$cTask[$eTask['id']] = $eTask;

		}

		$action = $this->executePage('planning/taskWeek:batchTime', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'ids' => $cTask->getIds(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Temps » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(['area', 'plant', 'fair'], $data->distributions, 'des plantations sur des séries mesurées n\'offrent pas les trois clés');
		$this->assertEquals('plant', $data->distribution, 'des plantations ne se partagent pas d\'office au nombre de plants');

		$eTaskSmall = $cTask->first();
		$eTaskLarge = $cTask->last();

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5', 'distribution' => 'area']);

		$this->assertTrue($action instanceof \ViewAction, '« Temps » à la surface est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => 0.5], $this->getTimes($eTaskSmall), 'la série de 30 m² ne reçoit pas le tiers du total');
		$this->assertEquals([\Clock::date() => 1.0], $this->getTimes($eTaskLarge), 'la série de 60 m² ne reçoit pas les deux tiers du total');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1', 'distribution' => 'plant']);

		$this->assertTrue($action instanceof \ViewAction, '« Temps » au nombre de plants est refusé : '.implode(', ', $this->getFails($action)));
		// Le temps s'écrit en `float32` : il se compare à la minute.
		$this->assertEquals(30 + 40, (int)round($this->getTimes($eTaskSmall)[\Clock::date()] * 60), '120 plants sur 180 ne reçoivent pas les deux tiers de l\'heure');
		$this->assertEquals(60 + 20, (int)round($this->getTimes($eTaskLarge)[\Clock::date()] * 60), '60 plants sur 180 ne reçoivent pas le tiers de l\'heure');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1', 'distribution' => 'harvest']);

		$this->assertTrue($action instanceof \NotExpectedAction, 'une clé que la sélection n\'offre pas est acceptée');

	}

	/**
	 * Une intervention hors culture dans le lot : le partage égalitaire seul, comme l'ancien formulaire, quelle
	 * que soit la clé postée.
	 */
	public function testTimeOutside(): void {

		$eCategoryOther = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_ADMINISTRATION);

		if($this->assertDecorPresent($eCategoryOther->notEmpty(), 'la structure '.static::STRUCTURE.' a une catégorie d\'administration') === FALSE) {
			return;
		}

		$eTaskOutside = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $this->getAction(ACTION_PLANTATION),
			'category' => $eCategoryOther,
			'status' => \series\Task::DONE,
			'doneWeek' => \Clock::week(),
			'doneDate' => \Clock::date(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans build(), qui refuserait l'action de culture dans la catégorie d'administration : le partage ne lit
		// que la catégorie, et le décor évite de chercher une action propre à cette catégorie.
		\series\TaskLib::create($eTaskOutside);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		$cTask = new \Collection([$eTask['id'] => $eTask, $eTaskOutside['id'] => $eTaskOutside]);

		$document = $this->getTimePanel($cTask);

		if($document === NULL) {
			return;
		}

		$this->assertEquals(NULL, $document->querySelector('input[type="radio"][name="distribution"]'), 'une sélection hors culture offre un choix de clé');
		$this->assertEquals('fair', $document->querySelector('input[type="hidden"][name="distribution"]')?->getAttribute('value'), 'une sélection hors culture ne poste pas les parts égales');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1', 'distribution' => 'area']);

		$this->assertTrue($action instanceof \ViewAction, '« Temps » hors culture est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => 0.5], $this->getTimes($eTaskOutside), 'l\'intervention hors culture ne reçoit pas la moitié');
		$this->assertEquals([\Clock::date() => 0.5], $this->getTimes($eTask), 'l\'intervention de culture ne reçoit pas la moitié');

	}

	/**
	 * Le panneau : les jours passés et aujourd'hui à choisir, aujourd'hui choisi, le total à 0h00, et aucune
	 * ligne fondée sur la quantité récoltée hors d'une récolte.
	 */
	public function testTimePanel(): void {

		$cTask = $this->createTasks();

		$document = $this->getTimePanel($cTask);

		if($document === NULL) {
			return;
		}

		$this->assertEquals(0, $document->querySelectorAll('[data-basis^="harvest-"]')->length, 'une ligne qui n\'est pas une récolte se fonde sur la quantité récoltée');

		$this->assertEquals(\Clock::date(), $document->querySelector('input[type="hidden"][name="date"]')?->getAttribute('value'), 'le panneau ne choisit pas aujourd\'hui');
		$this->assertEquals(4, $document->querySelectorAll('[data-planning-batch-time-day]')->length, 'les jours passés et aujourd\'hui ne se choisissent pas tous');

		$this->assertEquals('0', $document->querySelector('input[type="hidden"][name="time"]')?->getAttribute('value'), 'le total ne s\'ouvre pas à 0h00');

	}

	/**
	 * Une seule intervention cochée (retour 295) : le panneau n'a rien à répartir. Une plantation d'une série
	 * qui a une surface et des plants, où deux lignes auraient à choisir leur clé, ne montre pas de boutons
	 * radios, la clé part cachée, et le total va entier à l'intervention, qui passe faite.
	 */
	public function testTimePanelOne(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série de 30 m²'));

		// Même écriture que `testTimeDistribution()` : la surface telle que le calcul l'aurait laissée.
		$eCultivation['series']['area'] = 30;
		\series\SeriesLib::update($eCultivation['series'], ['area']);

		$eCultivation['series'] = \series\SeriesLib::getById($eCultivation['series']['id']);
		$eCultivation['distance'] = \series\Cultivation::DENSITY;
		$eCultivation['density'] = 4.0;
		\series\CultivationLib::update($eCultivation, ['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing']);

		$eTask = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$cTask = new \Collection([$eTask['id'] => $eTask]);

		$document = $this->getTimePanel($cTask);

		if($document === NULL) {
			return;
		}

		$this->assertEquals(0, $document->querySelectorAll('[data-wrapper~="distribution"], input[type="radio"][name="distribution"]')->length, 'une seule intervention a une répartition à choisir');
		$this->assertTrue(in_array($document->querySelector('input[type="hidden"][name="distribution"]')?->getAttribute('value'), ['area', 'plant'], TRUE), 'la clé d\'une seule intervention ne part pas cachée');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5', 'distribution' => $document->querySelector('input[type="hidden"][name="distribution"]')?->getAttribute('value')]);

		if($this->assertTrue($action instanceof \ViewAction, '« Temps » sur une seule intervention est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 1.5], $this->getTimes($eTask), 'une seule intervention ne reçoit pas le total entier');
		$this->assertTrue(\series\TaskLib::getById($eTask['id'])->isDone(), 'l\'intervention seule qui a reçu du temps n\'est pas passée faite');

	}

	/**
	 * La barre selon ce qui est coché (retour 295), `PlanningTaskSelection.updateMenu()` joué dans node sur la
	 * barre que chaque rôle reçoit : « Temps » se montre à une carte comme à deux, « Fait ! » à deux seulement.
	 * Bruno, au planning limité, ne donne pas de temps à une intervention à faire : « Temps » se cache tant
	 * qu'aucune carte faite n'est cochée, une comme plusieurs. Une récolte sans quantité dans la semaine, cochée
	 * seule, n'a aucun jour où recevoir du temps : « Temps » se cache, et revient dès qu'une autre carte la rejoint.
	 */
	public function testMenuTime(): void {

		$this->createTasks();

		$cases = [
			'une à faire' => [['todo']],
			'une faite' => [['done']],
			'deux à faire' => [['todo'], ['todo']],
			'une à faire et une faite' => [['todo'], ['done']],
			'une récolte sans quantité' => [['todo', 'not-time']],
			'une récolte sans quantité et une à faire' => [['todo', 'not-time'], ['todo']],
		];

		foreach($this->renderMenuScreens() as $name => $document) {

			$hidden = $this->playMenu($document, $cases);

			if($hidden !== NULL) {

				$this->assertEquals(
					['une à faire' => FALSE, 'une faite' => FALSE, 'deux à faire' => FALSE, 'une à faire et une faite' => FALSE, 'une récolte sans quantité' => TRUE, 'une récolte sans quantité et une à faire' => FALSE],
					array_map(fn(array $case) => $case['time'], $hidden),
					'au planning complet, « Temps » ne se cache pas pour la seule récolte sans quantité cochée seule, dans '.$name
				);
				$this->assertEquals(
					['une à faire' => TRUE, 'une faite' => TRUE, 'deux à faire' => FALSE, 'une à faire et une faite' => TRUE, 'une récolte sans quantité' => TRUE, 'une récolte sans quantité et une à faire' => FALSE],
					array_map(fn(array $case) => $case['done'], $hidden),
					'« Fait ! » ne se montre pas à plusieurs cartes à faire seulement, dans '.$name
				);

			}

		}

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		foreach($this->renderMenuScreens() as $name => $document) {

			$hidden = $this->playMenu($document, $cases);

			if($hidden !== NULL) {

				$this->assertEquals(
					['une à faire' => TRUE, 'une faite' => FALSE, 'deux à faire' => TRUE, 'une à faire et une faite' => FALSE, 'une récolte sans quantité' => TRUE, 'une récolte sans quantité et une à faire' => TRUE],
					array_map(fn(array $case) => $case['time'], $hidden),
					'au planning limité, « Temps » ne suit pas la présence d\'une carte faite, dans '.$name
				);

			}

		}

	}

	/**
	 * La marque que le menu lit (`not-time`) : la case d'une récolte la porte tant que la semaine ne lui connaît
	 * aucune quantité, à la Semaine comme au Jour, et jamais celle d'une autre intervention. Au Jour, la case
	 * d'une récolte est sur la carte de sa première colonne, qui peut n'avoir rien récolté ce jour-là : elle
	 * juge la semaine (`harvestWeekTotal`), pas la quantité du jour que la carte montre (retour 360).
	 */
	public function testCheckboxTime(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eTaskPlanted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskEmpty = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$eTaskHarvested = $this->createTaskHarvest($this->getCultivation(), \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskHarvested, 2.0, \Clock::date());
		$eTaskHarvestedLater = $this->createTaskHarvest($this->getCultivation(), \Clock::week(), week_date_day(\Clock::week(), 2));
		$this->createTimesheet($eTaskHarvestedLater, 0.5, week_date_day(\Clock::week(), 1));
		$this->createHarvest($eTaskHarvestedLater, 1.0, week_date_day(\Clock::week(), 2));

		foreach($this->renderMenuScreens() as $name => $document) {

			$marked = [];

			foreach([$eTaskPlanted, $eTaskEmpty, $eTaskHarvested, $eTaskHarvestedLater] as $eTask) {

				$checkbox = $document->querySelector('input[name="batch[]"][value="'.$eTask['id'].'"]');

				if($this->assertTrue($checkbox !== NULL, 'l\'intervention '.$eTask['id'].' n\'a pas de case dans '.$name) === FALSE) {
					continue 2;
				}

				$marked[] = in_array('not-time', explode(' ', $checkbox->getAttribute('data-batch')), TRUE);

			}

			$this->assertEquals([FALSE, TRUE, FALSE, FALSE], $marked, 'la plantation, la récolte sans quantité, la récolte de 2 kg et celle récoltée mardi, dont la case est lundi, ne portent pas la marque attendue dans '.$name);

		}

	}

	/**
	 * Les deux écrans qui portent la barre, rendus pour la personne connectée.
	 */
	private function renderMenuScreens(): array {

		[$documentWeek] = $this->renderTasks();
		[$documentDay] = $this->renderTaskDay();

		return ['la Semaine' => $documentWeek, 'le Jour' => $documentDay];

	}

	/**
	 * Pour chaque sélection, si « Temps » et « Fait ! » sortent cachés d'`updateMenu()`. L'entrée « Temps » porte
	 * la marque que la barre rendue lui donne ; `NULL` quand la barre n'a pas d'entrée « Temps ».
	 */
	private function playMenu(\Dom\HTMLDocument $document, array $cases): ?array {

		$time = $document->querySelector('#planning-batch .planning-batch-time');

		if($this->assertTrue($time !== NULL, 'la barre ne rend pas « Temps »') === FALSE) {
			return NULL;
		}

		$class = $this->getSelectionClass();

		if($class === NULL) {
			return NULL;
		}

		$script = '
			const vm = require("vm");

			const hidden = {};
			const entry = (name, marked) => ({
				classList: {toggle: (className, on) => { hidden[name] = on; }},
				hasAttribute: attribute => marked && attribute === "data-planning-batch-time-done"
			});
			const entries = {
				".planning-batch-time": entry("time", '.json_encode($time->hasAttribute('data-planning-batch-time-done')).'),
				".planning-batch-done": entry("done", false)
			};

			const context = vm.createContext({
				qsa: (selector, each) => { if(entries[selector]) { each(entries[selector]); } }
			});

			vm.runInContext('.json_encode($class).' + ";globalThis.PlanningTaskSelection = PlanningTaskSelection;", context);

			const results = {};

			for(const [name, cards] of Object.entries('.json_encode($cases).')) {

				const selection = cards.map((batch, index) => ({value: String(index + 1), batch: batch}));
				selection.filter = selector => Array.prototype.filter.call(selection, card => card.batch.includes(/~="([^"]+)"/.exec(selector)[1]));

				context.PlanningTaskSelection.updateMenu(selection);
				results[name] = {time: hidden.time, done: hidden.done};

			}

			process.stdout.write(JSON.stringify(results));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results) and count($results) === count($cases), 'node ne rend pas son résultat : '.$trace) === FALSE) {
			return NULL;
		}

		return $results;

	}

	/**
	 * Bruno, au planning limité, saisit son propre temps (décision de V-Planning du 04/10, voie ii). Une
	 * intervention à faire lui reste en lecture, puisque recevoir du temps la passerait faite : sa ligne dit
	 * « À faire » et le total va aux seules interventions faites.
	 */
	public function testTimeLimited(): void {

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		$cTask = new \Collection([$eTaskDone['id'] => $eTaskDone, $eTaskTodo['id'] => $eTaskTodo]);

		$document = $this->getTimePanel($cTask);

		if($document === NULL) {
			return;
		}

		$this->assertEquals(NULL, $document->querySelector('[data-planning-batch-time-task="'.$eTaskTodo['id'].'"]'), 'la ligne à faire de Bruno reçoit une part');
		$this->assertTrue($document->querySelector('[data-planning-batch-time-task="'.$eTaskDone['id'].'"]') !== NULL, 'la ligne faite de Bruno ne reçoit pas de part');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5']);

		$this->assertTrue($action instanceof \ViewAction, 'le temps de Bruno est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => 1.5], $this->getTimes($eTaskDone), 'le temps de Bruno ne va pas entier à l\'intervention faite');
		$this->assertEquals([], $this->getTimes($eTaskTodo), 'le temps de Bruno est écrit sur l\'intervention à faire');
		$this->assertFalse(\series\TaskLib::getById($eTaskTodo['id'])->isDone(), 'le temps de Bruno passe l\'intervention faite');

	}

	/**
	 * Au planning limité, une faite et une à faire cochées ne laissent qu'une ligne recevoir une part : comme à une
	 * seule intervention, le panneau ne propose pas de répartition, et la clé part cachée. La série a sa surface
	 * et sa densité, pour que deux lignes ouvertes aient eu le choix.
	 */
	public function testTimeLimitedOne(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série de 30 m²'));

		// Même écriture que `testTimeDistribution()` : la surface telle que le calcul l'aurait laissée.
		$eCultivation['series']['area'] = 30;
		\series\SeriesLib::update($eCultivation['series'], ['area']);

		$eCultivation['series'] = \series\SeriesLib::getById($eCultivation['series']['id']);
		$eCultivation['distance'] = \series\Cultivation::DENSITY;
		$eCultivation['density'] = 4.0;
		\series\CultivationLib::update($eCultivation, ['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing']);

		$eTaskDone = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$eTaskTodo = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$cTask = new \Collection([$eTaskDone['id'] => $eTaskDone, $eTaskTodo['id'] => $eTaskTodo]);

		$document = $this->getTimePanel($cTask);

		if($document !== NULL) {
			$this->assertTrue($document->querySelectorAll('input[type="radio"][name="distribution"]')->length > 1, 'au planning complet, deux lignes ouvertes d\'une série mesurée n\'ont pas de répartition à choisir');
		}

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		$document = $this->getTimePanel($cTask);

		if($document === NULL) {
			return;
		}

		$this->assertEquals(0, $document->querySelectorAll('[data-wrapper~="distribution"], input[type="radio"][name="distribution"]')->length, 'une seule ligne ouverte a une répartition à choisir');

		$distribution = $document->querySelector('input[type="hidden"][name="distribution"]')?->getAttribute('value');

		$this->assertTrue(in_array($distribution, ['area', 'plant'], TRUE), 'la clé d\'une seule ligne ouverte ne part pas cachée');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5', 'distribution' => $distribution]);

		$this->assertTrue($action instanceof \ViewAction, 'le temps de Bruno est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => 1.5], $this->getTimes($eTaskDone), 'le temps de Bruno ne va pas entier à l\'intervention faite');
		$this->assertEquals([], $this->getTimes($eTaskTodo), 'le temps de Bruno est écrit sur l\'intervention à faire');

	}

	/**
	 * Au rôle planning limité, une intervention à faire qui n'est pas une récolte reste verrouillée, et ne compte pas
	 * dans le choix de la clé : les récoltes faites se partagent le total à la quantité du jour, 1 et 2 kg pour
	 * 1h30 donnant 30 minutes et 1 heure.
	 */
	public function testTimeLimitedHarvest(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cTask = new \Collection();

		foreach([1.0, 2.0] as $quantity) {
			$eTask = $this->createTaskHarvest($this->getCultivation(), \Clock::week(), \Clock::date());
			$this->createHarvest($eTask, $quantity, \Clock::date());
			$cTask[$eTask['id']] = $eTask;
		}

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$cTask[$eTaskTodo['id']] = $eTaskTodo;

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '1.5']);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps de Bruno sur ses récoltes est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$times = [];

		foreach($cTask as $eTask) {
			$times[] = $this->getTimes($eTask)[\Clock::date()] ?? 0.0;
		}

		$this->assertEquals([0.5, 1.0, 0.0], $times, 'une ligne verrouillée qui n\'est pas une récolte prive les récoltes de leur quantité');

	}

	/**
	 * Le plafond de 24 heures se juge sur le jour entier, total compris : refusé, le lot n'écrit rien et ne
	 * coche rien. Un total nul et un jour hors de la semaine affichée sont refusés de même.
	 */
	public function testTimeRefused(): void {

		$cTask = $this->createTasks();

		$this->createTimesheet($this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date()), 20.0);

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '5']);
		$this->assertEquals(['Timesheet::time.tooLong'], $this->getFails($action), 'un total qui passe 24 heures dans la journée n\'est pas refusé');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => \Clock::date(), 'time' => '0']);
		$this->assertEquals(['Timesheet::time.missing'], $this->getFails($action), 'un total nul n\'est pas refusé');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => week_date_day(\Clock::week(), 1).' - 1 DAY', 'time' => '1']);
		$this->assertTrue($this->getFails($action) !== [], 'un jour invalide n\'est pas refusé');

		[, , $action] = $this->callBatch('doBatchTime', $cTask, ['date' => toDate(\Clock::date().' - 7 DAYS'), 'time' => '1']);
		$this->assertEquals(['Timesheet::date.week'], $this->getFails($action), 'un jour d\'une autre semaine n\'est pas refusé');

		foreach($cTask as $eTask) {
			$this->assertEquals([], $this->getTimes($eTask), 'un lot refusé a écrit du temps');
		}

		foreach($this->reload($cTask) as $eTask) {
			$this->assertEquals(\series\Task::TODO, $eTask['status'], 'un lot refusé a passé une intervention faite');
		}

	}

	/**
	 * Le Jour : la même route, rendue par la vue de ses gestes.
	 */
	public function testDayTime(): void {

		$cTask = $this->createTasks();

		[$data, , $action] = $this->callDay('doBatchTime', ['ids' => $cTask->getIds(), 'date' => \Clock::date(), 'time' => '0.5']);

		if($this->assertTrue($action instanceof \ViewAction, '« Temps » du Jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('batchTime', $data->gesture, '« Temps » du Jour ne ferme pas son panneau');

		foreach($cTask as $eTask) {
			$this->assertEquals([\Clock::date() => 0.25], $this->getTimes($eTask), '« Temps » du Jour ne partage pas le total');
		}

	}

	/**
	 * Une semaine à venir n'a aucun jour où poser du temps : la barre ne propose pas « Temps », qui ouvrirait
	 * un panneau dont l'envoi ne pourrait qu'être refusé. La semaine en cours et une semaine passée le proposent.
	 */
	public function testTimeFutureWeek(): void {

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure suit le temps de travail') === FALSE) {
			return;
		}

		foreach([
			'la semaine en cours' => [\Clock::week(), TRUE],
			'une semaine passée' => [toWeek(\Clock::date().' - 7 DAYS'), TRUE],
			'une semaine à venir' => [toWeek(\Clock::date().' + 7 DAYS'), FALSE],
		] as $case => [$week, $expected]) {

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.new TaskBatchUi()->get($this->eStructure, 'taskWeek', $week, [], new \Collection()).'</body>', LIBXML_NOERROR);

			$this->assertEquals($expected, $document->querySelector('.planning-batch-time') !== NULL, $case.', « Temps » '.($expected ? 'manque à' : 'est proposé dans').' la barre');

		}

	}

	/**
	 * Le panneau « Temps » de la Semaine pour un lot, rendu comme sa vue le rend.
	 */
	private function getTimePanel(\Collection $cTask): ?\Dom\HTMLDocument {

		$action = $this->executePage('planning/taskWeek:batchTime', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'ids' => $cTask->getIds(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Temps » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
		$panel = new TaskBatchUi()->getTime($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->eUserOnline, $data->days, $data->date, $data->distributions, $data->distribution);

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$panel->dialogOpen.$panel->body.$panel->dialogClose.'</body>', LIBXML_NOERROR);

	}

	/**
	 * Le temps de la personne connectée sur une intervention, par jour.
	 */
	private function getTimes(\series\Task $eTask): array {

		$times = [];

		foreach(\series\Timesheet::model()
			->select(['date', 'time'])
			->whereTask($eTask)
			->whereUser(\user\ConnectionLib::getOnline())
			->getCollection() as $eTimesheet) {
			$times[$eTimesheet['date']] = ($times[$eTimesheet['date']] ?? 0.0) + $eTimesheet['time'];
		}

		return $times;

	}

	/**
	 * Le mode de sélection (R-Selection) vit dans le navigateur : le serveur rend « Sélectionner » et
	 * « Terminé » ensemble, avec « + Intervention » (R-Retours-0410-soir, I2), et jamais le mode ouvert ni une carte choisie. Chaque
	 * écran et chaque colonne du Jour le reposent en arrivant, pour qu'une réponse qui les redessine sans
	 * case le retrouve.
	 */
	public function testSelectMode(): void {

		$this->createTasks();

		[$document] = $this->renderTasks();
		$this->assertSelectMode($document, '#planning-week', 'la Semaine');

		[$document] = $this->renderTaskDay();
		$this->assertSelectMode($document, '#planning-day', 'le Jour');

		$columns = $document->querySelectorAll('#planning-day .planning-day-column');
		$this->assertTrue($columns->length > 0, 'le Jour ne rend aucune colonne');

		foreach($columns as $column) {
			$this->assertTrue(str_contains($column->getAttribute('onrender') ?? '', 'PlanningTaskSelection.apply()'), 'la colonne '.$column->getAttribute('data-column').' du Jour ne repose pas le mode en arrivant');
		}

		$none = $document->querySelector('#planning-day-'.TaskDayLib::NONE);

		if($none === NULL) {
			$this->assertFail('le Jour ne rend pas la colonne « Sans jour »');
			return;
		}

		$this->assertTrue(str_contains($none->getAttribute('onrender'), 'PlanningTaskDay.renderNone(this)'), '« Sans jour » perd son rendu de rail');

	}

	/**
	 * Modifier, Commenter, Une autre semaine et Décaler davantage écrivent par les routes de l'ancien planning,
	 * qui rechargent l'écran entier (retour 253) : la Semaine et le Jour rendus entiers vident la sélection par
	 * le nœud qui porte toutes leurs cases, que `parseRender()` passe avant elles. La réponse ciblée d'un geste
	 * ne rend jamais ce nœud, et garde ainsi la sélection qu'elle doit garder.
	 */
	public function testScreenClearsSelection(): void {

		$this->createTasks();

		[$document] = $this->renderTasks();
		$this->assertScreenClearsSelection($document, '#planning-week', 'la Semaine');

		[$document] = $this->renderTaskDay();
		$this->assertScreenClearsSelection($document, '#planning-day', 'le Jour');

	}

	private function assertScreenClearsSelection(\Dom\HTMLDocument $document, string $screen, string $name): void {

		$board = $document->querySelector('#planning-board');

		if($this->assertTrue($board !== NULL, $name.' ne rend pas #planning-board') === FALSE) {
			return;
		}

		$this->assertEquals('PlanningTaskSelection.reset()', $board->getAttribute('onrender'), 'après un rechargement, '.$name.' revient avec les cases cochées avant lui');

		$fields = $document->querySelectorAll($screen.' .planning-task [name="batch[]"]')->length;

		$this->assertTrue($fields > 0, $name.' ne rend aucune case à choisir');
		$this->assertEquals($fields, $board->querySelectorAll($screen.' .planning-task [name="batch[]"]')->length, $name.' rend des cases hors du nœud qui vide la sélection');

	}

	/**
	 * Un menu de la barre s'ouvre vers le haut, là où le titre de la barre porte le × : sa liste se pose
	 * au-dessus du × et non sur lui, faute de quoi le clic qui annule tombe sur une entrée qui écrit (lot CG).
	 * Le haut du × est 2.7rem au-dessus du haut du bouton, mesuré dans Chrome à la Semaine et au Jour : un
	 * décalage plus court laisse la liste sur lui.
	 */
	public function testMenuClearsHide(): void {

		$this->createTasks();

		[$document] = $this->renderTasks();
		$this->assertMenuClearsHide($document, 'la Semaine');

		[$document] = $this->renderTaskDay();
		$this->assertMenuClearsHide($document, 'le Jour');

	}

	private function assertMenuClearsHide(\Dom\HTMLDocument $document, string $name): void {

		$buttons = $document->querySelectorAll('#planning-batch [data-dropdown]');

		if($this->assertTrue($buttons->length > 0, $name.' ne rend aucun menu dans la barre') === FALSE) {
			return;
		}

		foreach($buttons as $button) {

			$label = trim($button->textContent);

			$this->assertEquals('top-start', $button->getAttribute('data-dropdown'), 'le menu « '.$label.' » de '.$name.' ne s\'ouvre plus vers le haut');
			$this->assertTrue($button->hasAttribute('data-dropdown-offset-y') and (float)$button->getAttribute('data-dropdown-offset-y') <= -2.7, 'la liste de « '.$label.' », dans '.$name.', se pose sur le × de la barre');

		}

	}

	/**
	 * Le × de la barre vide la sélection, ferme un menu ouvert depuis la barre et ferme le mode Sélectionner,
	 * dont les cases repartent (13/10, qui défait le retour 288) : il fait comme « Terminé ». Le × se lit
	 * sur la barre rendue par chaque écran et se joue dans node, `PlanningTaskSelection` extraite de
	 * `planning.js`.
	 */
	public function testHideLeavesSelectMode(): void {

		$this->createTasks();

		[$document] = $this->renderTasks();
		$this->assertHideLeavesSelectMode($document, 'la Semaine');

		[$document] = $this->renderTaskDay();
		$this->assertHideLeavesSelectMode($document, 'le Jour');

	}

	private function assertHideLeavesSelectMode(\Dom\HTMLDocument $document, string $name): void {

		$hide = $document->querySelector('#planning-batch .batch-hide');

		if($this->assertTrue($hide !== NULL, $name.' ne rend pas le × de la barre') === FALSE) {
			return;
		}

		$class = $this->getSelectionClass();

		if($class === NULL) {
			return;
		}

		$script = '
			const vm = require("vm");

			let purged = 0;
			const context = vm.createContext({
				Lime: {Dropdown: {purge: () => purged++}},
				qsa: (selector, each) => []
			});

			vm.runInContext('.json_encode($class).' + ";globalThis.PlanningTaskSelection = PlanningTaskSelection;", context);

			const selection = context.PlanningTaskSelection;
			selection.change = () => {};

			const play = code => {
				selection.selection = new Set(["1", "2"]);
				selection.selecting = true;
				purged = 0;
				vm.runInContext(code, context);
				return {selection: Array.from(selection.selection), selecting: selection.selecting, purged: purged};
			};

			process.stdout.write(JSON.stringify({hide: play('.json_encode($hide->getAttribute('onclick')).'), clear: play("PlanningTaskSelection.clear()")}));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results) and isset($results['hide'], $results['clear']), 'node ne rend pas son résultat : '.$trace) === FALSE) {
			return;
		}

		$this->assertEquals([], $results['hide']['selection'], 'le × de la barre ne vide pas la sélection dans '.$name);
		$this->assertFalse($results['hide']['selecting'], 'le × de la barre laisse ouvert le mode Sélectionner dans '.$name);
		$this->assertEquals(1, $results['hide']['purged'], 'le × de la barre laisse ouvert le menu de la barre dans '.$name);
		$this->assertFalse($results['clear']['selecting'], '« Terminé » ne ferme plus le mode Sélectionner dans '.$name);

	}

	/**
	 * La source de la classe `PlanningTaskSelection`, que node joue hors du navigateur ; `NULL`, après un
	 * échec nommé, quand `planning.js` ne la déclare plus.
	 */
	private function getSelectionClass(): ?string {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTaskSelection {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertTrue($start !== FALSE and $end !== FALSE, 'planning.js ne déclare plus la classe PlanningTaskSelection') === FALSE) {
			return NULL;
		}

		return substr($source, $start, $end - $start + 2);

	}

	private function clearsSelection(array $instructions): bool {

		return str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'PlanningTaskSelection.clear()');

	}

	private function assertSelectMode(\Dom\HTMLDocument $document, string $screen, string $name): void {

		$this->assertEquals('PlanningTaskSelection.apply()', $document->querySelector($screen)->getAttribute('onrender'), $name.' ne repose pas le mode en arrivant');

		$open = $document->querySelectorAll($screen.' > .planning-tools .planning-tools-mode > .planning-tools-actions > .planning-select-open[data-planning-select-mode]');
		$close = $document->querySelectorAll($screen.' > .planning-tools .planning-tools-mode > .planning-tools-actions > .planning-select-close[data-planning-select-mode]');

		if($this->assertTrue($open->length === 1 and $close->length === 1, $name.' ne rend pas « Sélectionner » et « Terminé » une fois chacun dans la rangée des outils') === FALSE) {
			return;
		}

		$this->assertTrue($document->querySelector($screen.' > .planning-tools a[href*=":create"]') !== NULL, $name.' ne pose pas « + Intervention » dans la rangée des outils');
		$this->assertFalse($open->item(0)->classList->contains('hide') or $close->item(0)->classList->contains('hide'), $name.' masque un des deux libellés par `hide`, que le JS devrait lever');

		$this->assertEquals(0, $document->querySelectorAll('.planning-selecting, .planning-task-selected, '.$screen.' [name="batch[]"]:checked')->length, $name.' est rendu avec le mode ouvert ou une carte choisie');
		$this->assertTrue($document->querySelectorAll($screen.' .planning-task [name="batch[]"]')->length > 0, $name.' ne rend aucune case à choisir');

		foreach(['.planning-batch-todo', '.batch-item-danger'] as $selector) {

			$node = $document->querySelector('#planning-batch '.$selector);

			if($this->assertTrue($node !== NULL, $name.' ne rend pas '.$selector.' dans la barre') === FALSE) {
				continue;
			}

			$this->assertTrue($node->getAttribute('data-confirm-one') !== NULL and $node->getAttribute('data-confirm-many') !== NULL, $name.' ne rend pas les deux questions de '.$selector.', au singulier et au pluriel');

		}

	}

	private function createTasks(): \Collection {

		$cTask = new \Collection();

		foreach([ACTION_PLANTATION, ACTION_PLANTATION] as $fqn) {
			$eTask = $this->createTask($this->getCultivation(), $fqn, \series\Task::TODO, \Clock::week());
			$cTask[$eTask['id']] = $eTask;
		}

		return $cTask;

	}

	private function reload(\Collection $cTask): \Collection {

		return \series\Task::model()
			->select(['id', 'status', 'plannedWeek', 'plannedDate', 'plannedUsers'])
			->whereId('IN', $cTask->getIds())
			->getCollection();

	}

	/**
	 * Un geste de lot de la Semaine, rendu par sa vue `doBatch`.
	 */
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

}
?>
