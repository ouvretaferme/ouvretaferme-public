<?php
namespace planning;

/**
 * « Nouvelle intervention » de la Semaine et du Jour (L5d). Ce que « J'ai aussi fait » y change, Fait coché
 * à l'ouverture, est dans `TimeDayCreateTest`.
 *
 * Lancement : dev/test package=planning file=TaskCreate
 */
class TaskCreateTest extends HarvestFixtureTest {

	/**
	 * Une intervention à faire, sans série, sans espèce ni description, se crée (R-Retours-0810 AH, qui lève
	 * la règle de L5d).
	 */
	public function testWeekAcceptedOutOfSeries(): void {

		$before = $this->countWeekTasks();

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput());

		if($this->assertTrue($action instanceof \ViewAction, 'la Semaine refuse une intervention hors série sans description : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals($before + 1, $this->countWeekTasks(), 'l\'intervention hors série sans description n\'est pas écrite');

		$eTask = $data->eTask;

		$this->assertEquals(\series\Task::TODO, $eTask['status'], 'l\'intervention neuve n\'est pas à faire');
		$this->assertEquals(\Clock::week(), $eTask['plannedWeek'], 'l\'intervention neuve n\'est pas prévue dans la semaine affichée');
		$this->assertEquals(NULL, $eTask['plannedDate'], 'l\'intervention neuve sans jour porte un jour');
		$this->assertEquals([TRUE, TRUE, NULL], [$eTask['series']->empty(), $eTask['plant']->empty(), $eTask['description']], 'l\'intervention neuve n\'est pas hors série, sans espèce ni description');

	}

	public function testDayAcceptedOutOfSeries(): void {

		$wednesday = week_date_day(\Clock::week(), 3);

		$before = $this->countWeekTasks();

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput(['day' => $wednesday]));

		if($this->assertTrue($action instanceof \ViewAction, 'le Jour refuse une intervention hors série sans description : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals($before + 1, $this->countWeekTasks(), 'l\'intervention hors série sans description n\'est pas écrite');

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals($wednesday, $eTask['plannedDate'], 'l\'intervention neuve n\'est pas posée sur le jour choisi');
		$this->assertEquals([TRUE, TRUE, NULL], [$eTask['series']->empty(), $eTask['plant']->empty(), $eTask['description']], 'l\'intervention neuve n\'est pas hors série, sans espèce ni description');

	}

	public function testDayOutOfWeekRejected(): void {

		[, , $action] = $this->callCreate('taskDay', $this->getInput(['day' => week_date_day(TaskDayLib::getNextWeek(\Clock::week()), 1)]));

		$this->assertTrue($action instanceof \NotExpectedAction, 'un jour hors de la semaine affichée est accepté');

	}

	/**
	 * Une intervention prévue par « Quand » sur une autre semaine que l'écran (piste F de R-CreationRapide) :
	 * elle part pour la semaine choisie, l'écran ne redessine rien qui la porte, et un message dit sa semaine.
	 * Sur la semaine de l'écran, aucun message.
	 */
	public function testCreatedOnAnotherWeek(): void {

		$weekNext = TaskDayLib::getNextWeek(\Clock::week());
		$wednesdayNext = week_date_day($weekNext, 3);

		$cases = [
			'la Semaine' => ['taskWeek', $this->getInput(['when' => $weekNext])],
			'le Jour' => ['taskDay', $this->getInput(['when' => $weekNext, 'day' => $wednesdayNext])],
		];

		foreach($cases as $case => [$page, $input]) {

			[$data, $instructions, $action] = $this->callCreate($page, $input);

			if($this->assertTrue($action instanceof \ViewAction, $case.' refuse une intervention de la semaine suivante : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$eTask = $data->cTaskCreated->first();

			$this->assertEquals($weekNext, $eTask['plannedWeek'], $case.', l\'intervention ne part pas pour la semaine choisie');
			$this->assertEquals(['week' => week_number($weekNext), 'year' => NULL, 'count' => 1], $data->success, $case.', le message ne dit pas la semaine de l\'intervention');
			$this->assertFalse(str_contains(json_encode($instructions), 'planning-task-'.$eTask['id']), $case.', la réponse redessine l\'intervention d\'une autre semaine');

		}

		[$data, $instructions, $action] = $this->callCreate('taskWeek', $this->getInput());

		if($this->assertTrue($action instanceof \ViewAction, 'la Semaine refuse une intervention de sa semaine : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals(NULL, $data->success, 'une intervention de la semaine affichée reçoit un message');
			$this->assertTrue(str_contains(json_encode($instructions), 'planning-task-'.$data->eTask['id']), 'la réponse ne redessine pas l\'intervention de la semaine affichée');
		}

	}

	/**
	 * Une puce de « Quand » (`taskDay:getWhen`, piste F de R-CreationRapide) : les jours de la semaine choisie et
	 * le jour pris d'office, et en Fait les personnes du temps de cette semaine, la personne de la fiche en tête.
	 * Passée à Fait, une semaine à venir devient la semaine en cours. Une semaine hors des saisons et une autre
	 * structure sont refusées.
	 */
	public function testGetWhen(): void {

		$weekNext = TaskDayLib::getNextWeek(\Clock::week());
		$weekPrevious = toWeek(week_date_starts(\Clock::week()).' - 7 days');

		[$data, , $action] = $this->callDay('getWhen', ['week' => $weekNext, 'status' => \series\Task::TODO]);

		if($this->assertTrue($action instanceof \ViewAction, 'la semaine suivante est refusée : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals(week_dates($weekNext), array_keys($data->days), 'les jours ne sont pas ceux de la semaine choisie');
			$this->assertEquals([NULL, 0], [$data->date, $data->cUserTime->count()], 'À faire prend un jour ou des personnes du temps');
		}

		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno, week_date_starts($weekPrevious));

		[$data, , $action] = $this->callDay('getWhen', ['week' => $weekPrevious, 'status' => \series\Task::DONE, 'user' => $eUserBruno['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la semaine précédente est refusée : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals(week_date_day($weekPrevious, 7), $data->date, 'Fait sur une semaine passée ne prend pas son dimanche');
			$this->assertEquals($eUserBruno['id'], $data->cUserTime->first()['id'] ?? NULL, 'la personne de la fiche n\'est pas en tête du temps');
		}

		[$data, , $action] = $this->callDay('getWhen', ['week' => $weekNext, 'status' => \series\Task::DONE]);

		if($this->assertTrue($action instanceof \ViewAction, 'Fait sur la semaine suivante est refusé : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals([\Clock::week(), \Clock::date()], [$data->when, $data->date], 'Fait reste sur une semaine à venir');
		}

		[, , $action] = $this->callDay('getWhen', ['week' => '1990-W10', 'status' => \series\Task::TODO]);
		$this->assertTrue($action instanceof \NotExpectedAction, 'une semaine hors des saisons est acceptée');

		[, , $action] = $this->callDay('getWhen', ['week' => $weekNext, 'status' => 'other']);
		$this->assertTrue($action instanceof \NotExpectedAction, 'un état inconnu est accepté');

		if($this->requireStructureForeign()) {
			[, , $action] = $this->callDay('getWhen', ['id' => $this->getStructureForeign()['id'], 'week' => $weekNext, 'status' => \series\Task::TODO]);
			$this->assertFalse($action instanceof \ViewAction, '« Quand » se lit sur une autre structure');
		}

	}

	/**
	 * La récolte se prévoit par « Nouvelle intervention » (I4 de R-Retours-0410-soir) : le panneau la propose,
	 * le « + » d'un bloc de récolte l'ouvre sur elle, et elle se crée à faire sans quantité ni unité, comme
	 * dans l'ancien planning, la quantité venant le jour où l'on récolte.
	 */
	public function testHarvestCreated(): void {

		$eAction = $this->getAction(ACTION_RECOLTE);

		$panel = $this->executePage('planning/taskWeek:create', ['id' => $this->eStructure['id'], 'week' => \Clock::week()], 'GET');

		if($this->assertTrue($panel instanceof \ViewAction, 'le panneau « Nouvelle intervention » ne s\'ouvre pas') === FALSE) {
			return;
		}

		// Les actions du panneau viennent de sa recherche « Intervention », qui demande la récolte (retour 192).
		[$data] = $this->callDay('queryAction', ['query' => '', 'harvest' => 1]);

		$cCategoryOffered = $data->cCategory->find(fn(\work\Category $eCategory) => $eCategory['cAction']->find(fn(\work\Action $eActionListed) => $eActionListed['id'] === $eAction['id'])->notEmpty());

		$this->assertTrue($cCategoryOffered->notEmpty(), 'le panneau « Nouvelle intervention » ne propose pas la récolte');

		$panel = $this->executePage('planning/taskWeek:create', ['id' => $this->eStructure['id'], 'week' => \Clock::week(), 'action' => $eAction['id']], 'GET');

		$this->assertTrue($panel instanceof \ViewAction, 'le « + » d\'un bloc de récolte n\'ouvre pas le panneau');

		$this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderTasks();

		$this->assertTrue($document->querySelector('#planning-week-block-'.$eAction['id'].' a[href*="action='.$eAction['id'].'"]') !== NULL, 'le bloc de récolte de la Semaine n\'a pas son « + »');

		[$document] = $this->renderTaskDay();

		$this->assertTrue($document->querySelectorAll('.planning-day-action-head a[href*="action='.$eAction['id'].'"]')->length > 0, 'le groupe de récolte du Jour n\'a pas son « + »');

		$before = $this->countWeekTasks();

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'action' => $eAction['id'],
			'cultivation' => $this->getCultivation()['id'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte est refusée par « Nouvelle intervention » : '.($action instanceof \Exception ? get_class($action) : implode(', ', $this->getFails($action)))) === FALSE) {
			return;
		}

		$this->assertEquals($before + 1, $this->countWeekTasks(), 'la récolte n\'est pas créée');

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals([$eAction['id'], \series\Task::TODO, NULL, NULL], [$eTask['action']['id'], $eTask['status'], $eTask['harvest'], $eTask['harvestUnit']], 'la récolte neuve n\'est pas une récolte à faire sans quantité ni unité');

	}

	/**
	 * Plusieurs cultures choisies (R-Retours-0310, point 2) : une intervention par culture, toutes de la même
	 * action, de la même semaine et de la même description, chacune avec la série et l'espèce de sa culture.
	 */
	public function testSeveralSeries(): void {

		$eCultivation = $this->getCultivation();
		$eCultivationOther = $this->createCultivation($this->createSeries('Seconde série du décor'));

		$before = $this->countWeekTasks();

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$eCultivation['id'], $eCultivationOther['id']],
			'description' => 'Pailler',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'deux séries choisies sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals($before + 2, $this->countWeekTasks(), 'deux séries choisies ne créent pas deux interventions');
		$this->assertEquals([$eCultivation['id'], $eCultivationOther['id']], $this->getCreated($data, 'cultivation'), 'les interventions neuves ne portent pas chacune sa culture');
		$this->assertEquals([$eCultivation['series']['id'], $eCultivationOther['series']['id']], $this->getCreated($data, 'series'), 'les interventions neuves ne portent pas chacune sa série');
		$this->assertEquals([$this->getAction(ACTION_PLANTATION)['id']], array_values(array_unique($this->getCreated($data, 'action'))), 'les interventions neuves n\'ont pas toutes l\'action choisie');
		$this->assertEquals(['Pailler'], array_values(array_unique($this->getCreated($data, 'description'))), 'les interventions neuves n\'ont pas toutes la description saisie');
		$this->assertEquals([\Clock::week()], array_values(array_unique($this->getCreated($data, 'plannedWeek'))), 'les interventions neuves ne sont pas toutes prévues dans la semaine affichée');

	}

	/**
	 * Deux espèces d'une même série sont deux cultures, donc deux interventions, une par espèce.
	 */
	public function testSeveralPlantsOfSeries(): void {

		$eCultivation = $this->getCultivation();
		$eCultivationOther = $this->createCultivation($this->getSeries(), $this->createPlant());

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$eCultivation['id'], $eCultivationOther['id']],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'deux espèces d\'une série sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$eCultivation['plant']['id'], $eCultivationOther['plant']['id']], $this->getCreated($data, 'plant'), 'deux espèces d\'une série ne donnent pas une intervention par espèce');
		$this->assertEquals([$this->getSeries()['id']], array_values(array_unique($this->getCreated($data, 'series'))), 'les interventions de deux espèces d\'une série ne portent pas cette série');

	}

	/**
	 * Une culture postée deux fois ne donne qu'une intervention.
	 */
	public function testDuplicatePosted(): void {

		$before = $this->countWeekTasks();

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$this->getCultivation()['id'], $this->getCultivation()['id']],
		]));

		$this->assertTrue($action instanceof \ViewAction, 'une culture postée deux fois est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals($before + 1, $this->countWeekTasks(), 'une culture postée deux fois ne donne pas une seule intervention');

	}

	/**
	 * Tout ou rien : une culture d'une autre structure, postée après une culture de la structure, n'en laisse
	 * créer aucune. La culture étrangère vient en second, pour qu'une écriture faite culture par culture
	 * laisse la première en base.
	 */
	public function testForeignCultivationCreatesNone(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eCultivationForeign = $this->createCultivationForeign();

		$before = $this->countWeekTasks();

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$this->getCultivation()['id'], $eCultivationForeign['id']],
		]));

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une culture d\'une autre structure est acceptée');
		$this->assertEquals($before, $this->countWeekTasks(), 'une culture refusée laisse créer l\'intervention de la culture acceptée');

	}

	/**
	 * Une culture postée qui ne désigne rien, série supprimée pendant que le panneau était ouvert ou valeur
	 * forgée, est refusée sur le champ, et n'en laisse créer aucune : sans ce refus, elle deviendrait une
	 * intervention hors série.
	 */
	public function testUnknownCultivationCreatesNone(): void {

		foreach([2147483647, 'abc', '-3'] as $unknown) {

			$before = $this->countWeekTasks();

			[, , $action] = $this->callCreate('taskWeek', $this->getInput([
				'cultivation' => [$this->getCultivation()['id'], $unknown],
			]));

			$this->assertEquals(['Task::cultivation.unknown'], $this->getFails($action), 'une culture postée « '.$unknown.' » n\'est pas refusée sur le champ : '.implode(', ', $this->getFails($action)));
			$this->assertEquals($before, $this->countWeekTasks(), 'une culture postée « '.$unknown.' » laisse créer des interventions');

		}

		$this->assertTrue(\series\AlertUi::getError('Task::cultivation.unknown') !== NULL, 'le refus d\'une culture inconnue n\'a pas de message');

	}

	/**
	 * Une répétition par intervention : chacune porte la sienne, que les occurrences de sa série suivent.
	 */
	public function testRepeatPerTask(): void {

		$eCultivation = $this->getCultivation();
		$eCultivationOther = $this->createCultivation($this->createSeries('Seconde série du décor'));

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$eCultivation['id'], $eCultivationOther['id']],
			'frequency' => \series\Repeat::W1,
			'stop' => $this->getSeason().'-W45',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'deux séries répétées sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$repeats = $this->getCreated($data, 'repeat');

		$this->assertEquals(2, count(array_unique(array_filter($repeats))), 'deux interventions répétées ne portent pas chacune sa répétition : '.implode(', ', array_map('strval', $repeats)));

		foreach($data->cTaskCreated as $eTask) {

			$occurrences = \series\Task::model()
				->whereStructure($this->eStructure)
				->whereRepeat($eTask['repeat'])
				->count();

			$this->assertTrue($occurrences > 1, 'la répétition de la culture '.$eTask['cultivation']['id'].' n\'a pas créé ses occurrences ('.$occurrences.')');

		}

	}

	/**
	 * Le plafond (`\series\SeriesSetting::TASK_CREATE_LIMIT`) : une culture de plus est refusée avant toute
	 * lecture, avec son message.
	 */
	public function testTooMany(): void {

		$before = $this->countWeekTasks();

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => range(1, \series\SeriesSetting::TASK_CREATE_LIMIT + 1),
		]));

		$this->assertTrue(in_array('Task::cultivation.tooMany', $this->getFails($action), TRUE), 'une culture au-delà du plafond n\'est pas refusée : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(\series\AlertUi::getError('Task::cultivation.tooMany') !== NULL, 'le refus du plafond n\'a pas de message');
		$this->assertEquals($before, $this->countWeekTasks(), 'le refus du plafond a écrit');

	}

	/**
	 * Le Jour crée de même une intervention par culture, toutes posées sur le jour choisi.
	 */
	public function testDaySeveralSeries(): void {

		$wednesday = week_date_day(\Clock::week(), 3);

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput([
			'day' => $wednesday,
			'cultivation' => [$this->getCultivation()['id'], $this->createCultivation($this->createSeries('Seconde série du décor'))['id']],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'deux séries choisies sur le Jour sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$wednesday, $wednesday], $this->getCreated($data, 'plannedDate'), 'les interventions neuves du Jour ne sont pas toutes posées sur le jour choisi');

	}

	/**
	 * Fait sur un jour passé (retour 244) : l'intervention se crée faite ce jour-là, par le chemin de « J'ai aussi
	 * fait » (`TaskCreateLib::prepare()`), avec le temps de la personne (retour 245), sans temps prévu ni
	 * répétition, même postés.
	 */
	public function testDoneOnPastDay(): void {

		$tuesday = week_date_day(\Clock::week(), 2);
		$eUser = $this->getMember();

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'cultivation' => [$this->getCultivation()['id']],
			'time' => [$eUser['id'] => '1.5'],
			'timeExpected' => '2',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'une intervention faite un jour passé est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals([\series\Task::DONE, $tuesday, \Clock::week()], [$eTask['status'], $eTask['doneDate'], $eTask['doneWeek']], 'l\'intervention neuve n\'est pas faite le jour choisi');
		$timeExpected = \series\Task::model()
			->select('timeExpected')
			->whereId($eTask['id'])
			->get()['timeExpected'];

		$this->assertEquals(NULL, $timeExpected, 'l\'intervention faite prend le temps prévu posté');
		$this->assertEquals(1.5, $this->getTimeWritten($data->cTaskCreated, $eUser, $tuesday), 'le temps de la personne n\'est pas écrit sur l\'intervention faite');

	}

	/**
	 * À faire, un temps posté par personne ne s'écrit pas : seul Fait prend le temps de chacun.
	 */
	public function testTodoWritesNoTime(): void {

		$wednesday = week_date_day(\Clock::week(), 3);
		$eUser = $this->getMember();

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput([
			'status' => \series\Task::TODO,
			'day' => $wednesday,
			'cultivation' => [$this->getCultivation()['id']],
			'time' => [$eUser['id'] => '1'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'une intervention à faire est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(\series\Task::TODO, $data->cTaskCreated->first()['status'], 'l\'intervention neuve n\'est pas à faire');
		$this->assertEquals(0.0, $this->getTimeWritten($data->cTaskCreated, $eUser, $wednesday), 'une intervention à faire écrit le temps posté');

	}

	/**
	 * Le temps saisi sur plusieurs séries est celui de la personne, compté une fois et partagé entre les
	 * interventions (`TaskLib::distribute()` en `fair`, arbitrage V-Planning du 11/10) : 1 h sur trois séries
	 * donne 1 h dans sa journée, et non 3 h.
	 */
	public function testDoneTimeDistributed(): void {

		$tuesday = week_date_day(\Clock::week(), 2);
		$eUser = $this->getMember();

		$dayBefore = \series\TimesheetLib::getTimeByUserDate($this->eStructure, $eUser, $tuesday);

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'cultivation' => [
				$this->getCultivation()['id'],
				$this->createCultivation($this->createSeries('Deuxième série du décor'))['id'],
				$this->createCultivation($this->createSeries('Troisième série du décor'))['id'],
			],
			'time' => [$eUser['id'] => '1'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'trois séries faites sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(3, $data->cTaskCreated->count(), 'trois séries faites ne créent pas trois interventions');
		$this->assertEquals(1.0, $this->getTimeWritten($data->cTaskCreated, $eUser, $tuesday), '1 h saisie sur trois séries ne donne pas 1 h au total sur les interventions');
		$this->assertEquals(1.0, round(\series\TimesheetLib::getTimeByUserDate($this->eStructure, $eUser, $tuesday) - $dayBefore, 4), '1 h saisie sur trois séries ne donne pas 1 h dans la journée de la personne');

		foreach($data->cTaskCreated as $eTask) {

			$time = $this->getTimeWritten(new \Collection([$eTask]), $eUser, $tuesday);

			$this->assertTrue(abs($time - 1 / 3) < 0.0001, 'l\'intervention de la culture '.$eTask['cultivation']['id'].' n\'a pas le tiers du temps ('.$time.')');

		}

	}

	/**
	 * Fait demande un jour qui n'est pas à venir : sans jour, ou un jour à venir, rien ne se crée et le refus a
	 * son message, sous le jour.
	 */
	public function testDoneRefused(): void {

		foreach(['' => 'Task::done.day', week_date_day(\Clock::week(), 5) => 'Task::done.future'] as $day => $fail) {

			$before = $this->countWeekTasks();

			[, , $action] = $this->callCreate('taskDay', $this->getInput([
				'status' => \series\Task::DONE,
				'day' => $day,
			]));

			$this->assertEquals([$fail], $this->getFails($action), 'Fait le jour « '.$day.' » n\'est pas refusé par '.$fail);
			$this->assertTrue(\series\AlertUi::getError($fail) !== NULL, 'le refus '.$fail.' n\'a pas de message');
			$this->assertEquals($before, $this->countWeekTasks(), 'Fait le jour « '.$day.' » crée une intervention');

		}

		[, , $action] = $this->callCreate('taskDay', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
		]));

		$this->assertTrue($action instanceof \ViewAction, 'Fait aujourd\'hui est refusé : '.implode(', ', $this->getFails($action)));

		// La même personne sous deux clés, 20 h chacune : chaque durée seule passe sous les 24 heures.
		$eUser = $this->getMember();
		$before = $this->countWeekTasks();

		[, , $action] = $this->callCreate('taskDay', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
			'time' => [$eUser['id'] => '20', '0'.$eUser['id'] => '20'],
		]));

		$this->assertEquals(['Timesheet::user.check'], $this->getFails($action), 'une personne postée deux fois n\'est pas refusée');
		$this->assertEquals($before, $this->countWeekTasks(), 'une personne postée deux fois crée une intervention');

	}

	/**
	 * Une récolte se crée faite depuis « Nouvelle intervention » (retour 248), sans quantité : hypothèse de
	 * V-Planning, la quantité se saisit dans Récoltes.
	 */
	public function testDoneHarvest(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$this->getCultivation()['id']],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte faite est refusée : '.($action instanceof \Exception ? get_class($action) : implode(', ', $this->getFails($action)))) === FALSE) {
			return;
		}

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals([\series\Task::DONE, $tuesday, NULL], [$eTask['status'], $eTask['doneDate'], $eTask['harvest']], 'la récolte neuve n\'est pas faite le jour choisi, sans quantité');

	}

	/**
	 * Faite sur une seule série, la récolte prend sa quantité (retour 258) : elle s'écrit le jour choisi, en
	 * kilos, et la culture la compte.
	 */
	public function testDoneHarvestQuantity(): void {

		$tuesday = week_date_day(\Clock::week(), 2);
		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'harvestMore' => '12,5',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte faite avec sa quantité est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$tuesday => ['kg' => 12.5]], $this->getHarvested($eCultivation), '12,5 kg récoltés mardi ne s\'écrivent pas mardi');
		$this->assertEquals(12.5, $this->readHarvest($data->cTaskCreated->first()), 'l\'intervention ne porte pas les 12,5 kg');

	}

	/**
	 * La quantité se saisit aussi en caisses, comme dans Récoltes (`HarvestWeekLib::buildCrate()`) : 3 caisses de
	 * 6 kg écrivent 18 kg, et l'espèce garde le poids. En caisses, une culture qui ne se compte pas en kilos est
	 * refusée, et rien ne se crée, de même qu'une intervention postée dans une autre unité que le kilo.
	 */
	public function testDoneHarvestCrate(): void {

		$tuesday = week_date_day(\Clock::week(), 2);
		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'harvestMore' => '3',
			'crate' => '1',
			'crateWeight' => '6',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte faite en caisses est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$tuesday => ['kg' => 18.0]], $this->getHarvested($eCultivation), '3 caisses de 6 kg n\'écrivent pas 18 kg');
		$this->assertEquals(6.0, \plant\PlantLib::getById($ePlant['id'])['crateWeight'], 'le poids de la caisse n\'est pas posé sur l\'espèce');

		$eCultivationUnit = $this->createCultivation(ePlant: $this->createPlant());

		// Sans `build()` ni `CultivationLib::update()` : l'unité se change dans le formulaire de la culture, qui la
		// vérifie avec ses densités et recalcule le prévu ; le décor ne veut qu'une culture comptée à l'unité.
		\series\Cultivation::model()->update($eCultivationUnit, ['mainUnit' => \series\Cultivation::UNIT]);

		$before = $this->countWeekTasks();

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivationUnit['id']],
			'harvestMore' => '3',
			'crate' => '1',
			'crateWeight' => '6',
		]));

		$this->assertEquals(['Task::harvestUnit.crate'], $this->getFails($action), 'des caisses sur une culture à l\'unité ne sont pas refusées');
		$this->assertEquals($before, $this->countWeekTasks(), 'des caisses refusées créent une intervention');

		// Les caisses ne pèsent que des kilos : choisies avec une autre unité, que le panneau ne laisse pas poster
		// ensemble, elles se refusent comme sur une culture à l'unité.
		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'harvestUnit' => \series\Task::BUNCH,
			'harvestMore' => '3',
			'crate' => '1',
			'crateWeight' => '6',
		]));

		$this->assertEquals(['Task::harvestUnit.crate'], $this->getFails($action), 'des caisses en bottes sur une culture en kilos ne sont pas refusées');
		$this->assertEquals($before, $this->countWeekTasks(), 'des caisses en bottes refusées créent une intervention');

	}

	/**
	 * La quantité se saisit aussi dans les autres unités de la culture (retour 319) : 5 pièces récoltées mardi
	 * s'écrivent en pièces, et l'intervention neuve se compte en pièces. Une quantité nulle n'écrit rien, et
	 * l'intervention garde alors l'unité de la culture. Une variété choisie garde l'unité de la culture, comme au
	 * tiroir : une autre unité est refusée et rien ne se crée, l'unité de la culture s'écrit sur la variété.
	 */
	public function testDoneHarvestUnitOther(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		$cases = [
			'5 pièces' => ['5', [$tuesday => [\series\Task::UNIT => 5.0]], \series\Task::UNIT, 5.0],
			'0 pièce' => ['0', [], NULL, NULL],
		];

		foreach($cases as $case => [$quantity, $harvested, $unit, $harvest]) {

			$eCultivation = $this->createCultivation(ePlant: $this->createPlant());

			[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
				'status' => \series\Task::DONE,
				'day' => $tuesday,
				'action' => $this->getAction(ACTION_RECOLTE)['id'],
				'cultivation' => [$eCultivation['id']],
				'harvestUnit' => \series\Task::UNIT,
				'harvestMore' => $quantity,
			]));

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$eTask = $this->readTask($data->cTaskCreated->first(), ['harvest', 'harvestUnit']);

			$this->assertEquals($harvested, $this->getHarvested($eCultivation), $case.' : le récolté de la culture');
			$this->assertEquals([$unit, $harvest], [$eTask['harvestUnit'], $eTask['harvest']], $case.' : l\'unité et la quantité de l\'intervention');

		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		// Une autre unité sur une variété (21/10, « Autoriser », HN-B2c) : l'intervention de la variété
		// dans cette unité.
		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'variety' => $eVariety['id'],
			'harvestUnit' => \series\Task::BUNCH,
			'harvestMore' => '2',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'des bottes sur une variété sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $this->readTask($data->cTaskCreated->first(), ['harvest', 'harvestUnit', 'variety']);

		$this->assertEquals([2.0, \series\Task::BUNCH, $eVariety['id']], [$eTask['harvest'], $eTask['harvestUnit'], $eTask['variety']['id']], 'les 2 bottes de la variété');

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'variety' => $eVariety['id'],
			'harvestUnit' => \series\Task::KG,
			'harvestMore' => '2',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'l\'unité de la culture postée avec une variété est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $this->readTask($data->cTaskCreated->first(), ['harvest', 'variety']);

		$this->assertEquals([2.0, $eVariety['id']], [$eTask['harvest'], $eTask['variety']['id']], 'les 2 kg de la variété');

	}

	/**
	 * La quantité ne vaut que pour une récolte faite d'une seule espèce : à faire, pour une autre action ou sur
	 * deux espèces, elle ne s'écrit pas, son unité non plus, et l'intervention se crée quand même. Négative, elle
	 * est refusée. Plusieurs séries de la même espèce se partagent la quantité (`TaskCreateHarvestTest`).
	 */
	public function testHarvestQuantityIgnored(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		$cases = [
			'à faire' => [\series\Task::TODO, ACTION_RECOLTE, 1],
			'une plantation' => [\series\Task::DONE, ACTION_PLANTATION, 1],
			'deux espèces' => [\series\Task::DONE, ACTION_RECOLTE, 2],
		];

		foreach($cases as $case => [$status, $action, $series]) {

			$cCultivation = new \Collection();

			for($i = 0; $i < $series; $i++) {
				$cCultivation[] = $this->createCultivation($this->createSeries('Série '.$case.' '.$i), ePlant: $this->createPlant());
			}

			[$data, , $result] = $this->callCreate('taskWeek', $this->getInput([
				'status' => $status,
				'day' => $tuesday,
				'action' => $this->getAction($action)['id'],
				'cultivation' => $cCultivation->getIds(),
				'harvestUnit' => \series\Task::BUNCH,
				'harvestMore' => '7',
			]));

			if($this->assertTrue($result instanceof \ViewAction, $case.' : l\'intervention est refusée : '.implode(', ', $this->getFails($result))) === FALSE) {
				continue;
			}

			$this->assertEquals($series, $data->cTaskCreated->count(), $case.' : les interventions ne sont pas créées');

			foreach($cCultivation as $eCultivation) {
				$this->assertEquals([], $this->getHarvested($eCultivation), $case.' : la quantité postée s\'écrit');
			}

			foreach($data->cTaskCreated as $eTask) {
				$this->assertEquals(NULL, $this->readTask($eTask, ['harvestUnit'])['harvestUnit'], $case.' : l\'unité postée s\'écrit');
			}

		}

		$before = $this->countWeekTasks();

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$this->getCultivation()['id']],
			'harvestMore' => '-2',
		]));

		$this->assertEquals(['Task::harvestMore.negative'], $this->getFails($action), 'une quantité négative n\'est pas refusée');
		$this->assertEquals($before, $this->countWeekTasks(), 'une quantité négative crée une intervention');

	}

	/**
	 * La route rend le champ de quantité de la culture choisie, en kilos et en caisses comme dans Récoltes, que
	 * `PlanningTaskCreateHarvest` pose dans le panneau.
	 */
	public function testHarvestField(): void {

		$action = $this->executePage('planning/taskDay:harvest', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'choices' => [(string)$this->getCultivation()['id']],
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'le champ de quantité est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('harvest', $action, 'taskDay');

		$document = $this->parse($template->export()['harvest']);

		$this->assertTrue($document->querySelector('input[name="harvestMore"]') !== NULL, 'la route ne rend pas le champ de quantité');
		$this->assertTrue($document->querySelector('[name="crate"]') !== NULL, 'la route ne rend pas la saisie en caisses');
		$this->assertEquals(1, $document->querySelectorAll('[data-wrapper~="harvestMore"][data-wrapper~="harvestUnit"]')->length, 'les refus de la quantité ne sont pas portés une fois, par le groupe « Quantité récoltée »');

		$others = array_map(fn(\Dom\Element $option) => $option->getAttribute('data-planning-harvest-other'), iterator_to_array($document->querySelectorAll('[name="crate"] option[data-planning-harvest-other]:not([data-planning-harvest-couple])')));
		$this->assertEquals([\series\Task::UNIT, \series\Task::BUNCH], $others, 'le champ ne propose pas les pièces et les bottes');

		$field = $document->querySelector('input[type="hidden"][name="harvestUnit"]');
		$this->assertTrue(($field !== NULL and $field->hasAttribute('disabled')), 'le champ caché « harvestUnit » ne s\'ouvre pas éteint : le kilo posterait une unité');

		[, , $action] = $this->callDay('harvest', ['choices' => [(string)$this->createCultivationForeign()['id']]]);

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'la culture d\'une autre structure rend son champ de quantité');

	}

	/**
	 * Une espèce seule (`plant:<id>`, BY-bis) se saisit en kilos, puis en caisses quand elle en a, puis dans les
	 * autres unités, dans l'ordre d'une série. Une espèce du référentiel partagé ou d'une autre structure ne rend
	 * rien.
	 */
	public function testHarvestFieldPlant(): void {

		$ePlant = $this->createPlant();

		$action = $this->executePage('planning/taskDay:harvest', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'choices' => ['plant:'.$ePlant['id']],
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'le champ de quantité d\'une espèce seule est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('harvest', $action, 'taskDay');

		$document = $this->parse($template->export()['harvest']);

		$choices = array_map(fn(\Dom\Element $option) => [$option->getAttribute('value'), $option->getAttribute('data-planning-harvest-other')], iterator_to_array($document->querySelectorAll('[name="crate"] option:not([data-planning-harvest-couple])')));
		$this->assertEquals([['0', NULL], ['1', NULL], ['0', \series\Task::UNIT], ['0', \series\Task::BUNCH]], $choices, 'l\'espèce seule ne propose pas le kilo, les caisses, puis les pièces et les bottes');

		$ePlantShared = \plant\Plant::model()
			->select('id')
			->whereStructure(NULL)
			->get();

		if($this->assertDecorPresent($ePlantShared->notEmpty(), 'une espèce du référentiel partagé') === FALSE) {
			return;
		}

		[, , $action] = $this->callDay('harvest', ['choices' => ['plant:'.$ePlantShared['id']]]);
		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une espèce du référentiel partagé rend son champ de quantité');

		$ePlantForeign = new \plant\Plant([
			'name' => 'Espèce d\'une autre structure '.uniqid(),
			'structure' => $this->getStructureForeign(),
			'cycle' => \plant\Plant::ANNUAL,
		]);

		// Sans `build()` : le décor veut une espèce d'une autre structure, que le formulaire de cette structure-ci refuserait.
		\plant\PlantLib::create($ePlantForeign);

		[, , $action] = $this->callDay('harvest', ['choices' => ['plant:'.$ePlantForeign['id']]]);
		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'l\'espèce d\'une autre structure rend son champ de quantité');

	}

	/**
	 * Faite sur une espèce seule, la récolte prend aussi sa quantité (BY-bis) : en kilos par défaut, dans une autre
	 * unité, ou en caisses, dont le poids se pose sur l'espèce. À 0, rien ne s'écrit et l'intervention n'a pas
	 * d'unité.
	 *
	 * Un calibre de l'espèce s'écrit avec ses kilos (DB). Des bottes sur ce calibre sont refusées par
	 * `harvestUnit.couple`, et un calibre d'une autre espèce par `harvestSize.action`, sans rien écrire. Une
	 * variété ne vaut que pour les planches d'une culture (`Task::properties()`, `variety`).
	 */
	public function testDoneHarvestPlant(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		$cases = [
			'12,5 kg' => [['harvestMore' => '12,5'], [$tuesday => [\series\Task::KG => 12.5]], \series\Task::KG, 12.5, NULL],
			'4 bottes' => [['harvestMore' => '4', 'harvestUnit' => \series\Task::BUNCH], [$tuesday => [\series\Task::BUNCH => 4.0]], \series\Task::BUNCH, 4.0, NULL],
			'3 caisses de 6 kg' => [['harvestMore' => '3', 'crate' => '1', 'crateWeight' => '6'], [$tuesday => [\series\Task::KG => 18.0]], \series\Task::KG, 18.0, 6.0],
			'0 kg' => [['harvestMore' => '0'], [], NULL, NULL, NULL],
		];

		foreach($cases as $case => [$input, $harvested, $unit, $harvest, $crateWeight]) {

			$ePlant = $this->createPlant();

			[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
				'status' => \series\Task::DONE,
				'day' => $tuesday,
				'action' => $this->getAction(ACTION_RECOLTE)['id'],
				'cultivation' => ['plant:'.$ePlant['id']],
			] + $input));

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$eTask = $this->readTask($data->cTaskCreated->first(), ['plant', 'cultivation', 'harvest', 'harvestUnit']);

			$this->assertEquals([$ePlant['id'], TRUE], [$eTask['plant']['id'], $eTask['cultivation']->empty()], $case.' : l\'intervention n\'est pas sur l\'espèce seule');
			$this->assertEquals($harvested, $this->getHarvestedByTask($data->cTaskCreated->first()), $case.' : le récolté de l\'intervention');
			$this->assertEquals([$unit, $harvest], [$eTask['harvestUnit'], $eTask['harvest']], $case.' : l\'unité et la quantité de l\'intervention');
			$this->assertEquals($crateWeight, \plant\PlantLib::getById($ePlant['id'])['crateWeight'], $case.' : le poids de la caisse de l\'espèce');

		}

		$ePlant = $this->createPlant();
		$eSize = $this->createSize($ePlant);
		$eSizeOther = $this->createSize($this->createPlant());

		$input = fn(\plant\Size $eSize, array $more) => $this->getInput([
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => ['plant:'.$ePlant['id']],
			'harvestSize' => $eSize['id'],
			'harvestMore' => '4',
		] + $more);

		[, , $action] = $this->callCreate('taskWeek', $input($eSizeOther, []));

		$this->assertEquals(['Task::harvestSize.action'], $this->getFails($action), 'le calibre d\'une autre espèce n\'est pas refusé');
		$this->assertEquals(0, \series\Task::model()->wherePlant($ePlant)->count(), 'une récolte refusée a créé une intervention');

		[$data, , $action] = $this->callCreate('taskWeek', $input($eSize, []));

		if($this->assertTrue($action instanceof \ViewAction, '4 kg sur un calibre de l\'espèce seule sont refusés : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $this->readTask($data->cTaskCreated->first(), ['plant', 'harvestSize', 'harvest', 'harvestUnit']);

		$this->assertEquals([$ePlant['id'], $eSize['id'], \series\Task::KG, 4.0], [$eTask['plant']['id'], $eTask['harvestSize']['id'], $eTask['harvestUnit'], $eTask['harvest']], 'la récolte d\'une espèce seule ne porte pas son calibre et ses kilos');

		// Une autre unité sur un calibre (HN-B2c) : l'intervention du calibre dans cette unité.
		[$data, , $action] = $this->callCreate('taskWeek', $input($eSize, ['harvestUnit' => \series\Task::BUNCH]));

		if($this->assertTrue($action instanceof \ViewAction, 'des bottes sur un calibre de l\'espèce seule sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $this->readTask($data->cTaskCreated->first(), ['harvestSize', 'harvest', 'harvestUnit']);

		$this->assertEquals([$eSize['id'], \series\Task::BUNCH, 4.0], [$eTask['harvestSize']['id'], $eTask['harvestUnit'], $eTask['harvest']], 'les 4 bottes du calibre de l\'espèce seule');

	}

	/**
	 * Le panneau de récolte (retour 258) porte l'espèce, le statut, le jour, la récolte et le temps.
	 */
	public function testPanelOrder(): void {

		$document = $this->renderCreate(['action' => $this->getAction(ACTION_RECOLTE)['id'], 'date' => week_date_day(\Clock::week(), 2)]);

		if($this->assertDecorPresent($document !== NULL, 'le panneau de récolte') === FALSE) {
			return;
		}

		$html = $document->body->innerHTML;
		$positions = [
			'espèce' => strpos($html, 'data-wrapper="cultivation'),
			'statut' => strpos($html, 'name="status"'),
			'jour' => strpos($html, 'data-wrapper="when day'),
			'récolte' => strpos($html, 'data-planning-task-create-harvest="1"'),
			'temps' => strpos($html, 'name="time['),
		];

		$this->assertTrue(in_array(FALSE, $positions, TRUE) === FALSE, 'un champ manque au panneau : '.json_encode($positions));

	}

	/**
	 * « Espèce ou série » garde le texte tapé après un choix (retour 345), pour revenir sur les autres séries de la même
	 * recherche ; c'est le seul champ du panneau.
	 */
	public function testPanelKeepQuery(): void {

		$document = $this->renderCreate(['action' => $this->getAction(ACTION_RECOLTE)['id'], 'date' => week_date_day(\Clock::week(), 2)]);

		if($this->assertDecorPresent($document !== NULL, 'le panneau de récolte') === FALSE) {
			return;
		}

		$fields = $document->querySelectorAll('[data-autocomplete-keep-query]');

		$this->assertEquals(1, $fields->length, 'le texte tapé n\'est pas gardé sur un seul champ du panneau');
		$this->assertTrue($fields->item(0)?->closest('[data-wrapper~="cultivation"]') !== NULL, 'le champ qui garde le texte tapé n\'est pas « Espèce ou série »');

	}

	/**
	 * Le jour pris à l'ouverture selon le bouton (piste F de R-CreationRapide) : « + Fait » prend aujourd'hui sur la
	 * semaine en cours et le dernier jour d'une semaine passée, aucun sur une semaine à venir, qui ne peut pas
	 * être faite ; « + À faire » s'ouvre toujours sur « Non défini ».
	 */
	public function testPanelDateDefault(): void {

		$weekPrevious = toWeek(week_date_day(\Clock::week(), 1).' - 7 days');
		$weekNext = toWeek(week_date_day(\Clock::week(), 1).' + 7 days');

		$cases = [
			'Fait, semaine en cours' => [\Clock::week(), \series\Task::DONE, \Clock::date(), \series\Task::DONE],
			'Fait, semaine passée' => [$weekPrevious, \series\Task::DONE, week_date_day($weekPrevious, 7), \series\Task::DONE],
			'Fait, semaine à venir' => [$weekNext, \series\Task::DONE, NULL, \series\Task::TODO],
			'À faire, semaine en cours' => [\Clock::week(), \series\Task::TODO, NULL, \series\Task::TODO],
			'À faire, semaine passée' => [$weekPrevious, \series\Task::TODO, NULL, \series\Task::TODO],
		];

		foreach($cases as $case => [$week, $statusAsked, $date, $status]) {

			[$data, , $action] = $this->callDay('create', ['week' => $week, 'status' => $statusAsked], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : le panneau est refusé') === FALSE) {
				continue;
			}

			$this->assertEquals($date, $data->date, $case.' : le panneau ne prend pas le bon jour');
			$this->assertEquals($status, $data->status, $case.' : le panneau ne prend pas le bon état');

		}

	}

	/**
	 * Sans état demandé, le jour posé par l'adresse le fixe (retour 244) : un jour passé est fait, aujourd'hui,
	 * un jour à venir et « Non défini » sont à faire. L'état est coché, et seuls les champs du sien partent avec le
	 * panneau, ceux de l'autre restant désactivés pour la bascule : le temps de la personne connectée pour une
	 * intervention faite, le temps prévu et la répétition pour une intervention à faire.
	 */
	public function testPanelStatus(): void {

		// Jeudi : la semaine a un jour passé et un jour à venir.
		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$cases = [
			'mardi passé' => [week_date_day(\Clock::week(), 2), \series\Task::DONE],
			'aujourd\'hui' => [\Clock::date(), \series\Task::TODO],
			'vendredi à venir' => [week_date_day(\Clock::week(), 5), \series\Task::TODO],
			'Non défini' => [NULL, \series\Task::TODO],
		];

		foreach($cases as $case => [$date, $status]) {

			[$data, , $action] = $this->callDay('create', array_filter(['date' => $date]), 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : le panneau est refusé') === FALSE) {
				continue;
			}

			$this->assertEquals($status, $data->status, $case.' : le panneau ne prend pas l\'état du jour');

			$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime);
			$document = $this->parse($panel->body);

			$this->assertEquals($status, $document->querySelector('input[name="status"][checked]')?->getAttribute('value'), $case.' : l\'état n\'est pas coché');

			$done = ($status === \series\Task::DONE);

			foreach([
				'le temps de la personne connectée' => ['input[name="time['.\user\ConnectionLib::getOnline()['id'].']"]', $done],
				'le temps prévu' => ['input[name="timeExpected"]', $done === FALSE],
				'la répétition' => ['select[name="frequency"]', $done === FALSE],
			] as $field => [$selector, $posted]) {

				$node = $document->querySelector($selector);

				if($this->assertTrue($node !== NULL, $case.' : '.$field.' n\'est pas rendu pour la bascule')) {
					$this->assertEquals($posted, $node->closest('fieldset[disabled]') === NULL, $case.' : '.$field.' ne suit pas l\'état');
				}

			}

		}

	}

	/**
	 * « Nouvelle intervention » de la Semaine sans état demandé, le « + » d'un bloc (retour 392) : sur la semaine
	 * en cours, le panneau s'ouvre sur Fait, aujourd'hui ; sur une semaine passée ou à venir, À faire sans jour. Le lundi et le dimanche bornent la semaine en cours.
	 */
	public function testWeekCreateCurrentDone(): void {

		$week = \Clock::week();

		foreach([1 => 'lundi', 4 => 'jeudi', 7 => 'dimanche'] as $position => $case) {

			$today = week_date_day($week, $position);

			$this->setClock($today.' 10:00:00');

			$cases = [
				'semaine en cours, '.$case => [$week, $today, \series\Task::DONE],
				'semaine passée, '.$case => [toWeek($today.' - 7 days'), NULL, \series\Task::TODO],
				'semaine à venir, '.$case => [toWeek($today.' + 7 days'), NULL, \series\Task::TODO],
			];

			foreach($cases as $label => [$weekCase, $date, $status]) {

				$data = $this->callWeekCreate($weekCase);

				if($data === NULL) {
					continue;
				}

				$this->assertEquals($date, $data->date, $label.' : le panneau ne prend pas le bon jour');
				$this->assertEquals($status, $data->status, $label.' : le panneau ne coche pas le bon statut');

			}

		}

	}

	/**
	 * « Méthodes de travail » et « Matériel nécessaire » (retour 255) : ceux de l'action posée par le « + » ;
	 * leurs conteneurs, que `PlanningTaskAction.fields()`
	 * remplit quand l'action change, sont rendus vides sans action ou pour une action sans méthode.
	 */
	public function testPanelMethodsTools(): void {

		[$eAction, $eMethod, $eTool] = $this->createActionMethodTool();

		$document = $this->renderCreate(['action' => $eAction['id']]);

		if($this->assertDecorPresent($document !== NULL, 'le panneau de l\'action du décor') === FALSE) {
			return;
		}

		$this->assertTrue($document->querySelector('[data-ref="methods"] [data-wrapper~="methods"]') !== NULL, 'le panneau d\'une action à méthode ne porte pas « Méthodes de travail »');
		$this->assertTrue($document->querySelector('[data-ref="tools"] [data-wrapper~="tools"]') !== NULL, 'le panneau d\'une action à matériel ne porte pas « Matériel nécessaire »');

		$document = $this->renderCreate([]);

		$this->assertEquals(['', ''], [
			trim($document->querySelector('[data-ref="methods"]')?->innerHTML ?? 'absent'),
			trim($document->querySelector('[data-ref="tools"]')?->innerHTML ?? 'absent'),
		], 'sans action, les conteneurs de méthodes et de matériel ne sont pas là, vides');

		$eActionBare = $this->createActionIn($this->getCategoryCulture());
		$document = $this->renderCreate(['action' => $eActionBare['id']]);

		$this->assertEquals('', trim($document->querySelector('[data-ref="methods"]')?->innerHTML ?? 'absent'), 'une action sans méthode montre « Méthodes de travail »');

	}

	/**
	 * La méthode et le matériel postés s'écrivent sur chaque intervention créée et sur chaque occurrence de sa
	 * répétition, à faire comme faite ; le matériel d'une autre action est écarté (`Task::tools`).
	 */
	public function testMethodsToolsWritten(): void {

		[$eAction, $eMethod, $eTool, $eToolOther] = $this->createActionMethodTool();

		$eCultivationOther = $this->createCultivation($this->createSeries('Seconde série du décor'));

		$posted = [
			'action' => $eAction['id'],
			'methods' => [$eMethod['id']],
			'tools' => [$eTool['id'], $eToolOther['id']],
		];

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput($posted + [
			'cultivation' => [$this->getCultivation()['id'], $eCultivationOther['id']],
			'frequency' => \series\Repeat::W1,
			'stop' => $this->getSeason().'-W45',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'à faire répétée, l\'intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTask = \series\Task::model()
			->select('id', 'methods', 'tools')
			->whereStructure($this->eStructure)
			->whereRepeat('IN', $data->cTaskCreated->getColumnCollection('repeat'))
			->getCollection();

		$this->assertTrue($cTask->count() > $data->cTaskCreated->count(), 'la répétition n\'a pas créé d\'occurrences');

		foreach($cTask as $eTask) {
			$this->assertEquals([[$eMethod['id']], [$eTool['id']]], [$eTask['methods'], $eTask['tools']], 'l\'intervention à faire '.$eTask['id'].' ne porte pas la méthode et le seul matériel de son action');
		}

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput($posted + [
			'status' => \series\Task::DONE,
			'day' => week_date_day(\Clock::week(), 2),
			'cultivation' => [$this->getCultivation()['id']],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'faite, l\'intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([[$eMethod['id']], [$eTool['id']]], $this->readMethodsTools($data->cTaskCreated->first()), 'l\'intervention faite ne porte pas la méthode et le seul matériel de son action');

	}

	/**
	 * Les champs de l'ancien formulaire (retour 256) : « Partagé », variété et calibre, vides jusqu'au premier
	 * choix, puis le produit, la fertilisation et le traitement, qui ne valent que pour une action de culture :
	 * hors culture ou sans action, leur `fieldset` est désactivé et ne poste rien.
	 */
	public function testPanelCultureFields(): void {

		if($this->requireAction(ACTION_TRAITEMENT) === FALSE) {
			return;
		}

		$document = $this->renderCreate(['action' => $this->getAction(ACTION_TRAITEMENT)['id']]);

		if($this->assertDecorPresent($document !== NULL, 'le panneau du traitement') === FALSE) {
			return;
		}

		$culture = $document->querySelector('fieldset[data-planning-task-create-culture]');

		$this->assertTrue(($culture !== NULL and $culture->hasAttribute('disabled') === FALSE), 'le traitement n\'a pas les champs de culture actifs');
		$this->assertTrue($document->querySelector('[data-ref="products"] [data-wrapper~="products"]') !== NULL, 'le traitement ne propose pas de produit');
		$this->assertTrue($document->querySelector('[data-ref="product-new"][disabled]') !== NULL, 'le traitement n\'a pas « Nouveau produit », désactivé tant qu\'il n\'est pas choisi');

		$positions = [];

		foreach(['data-wrapper="cultivation', 'data-ref="shared"', 'data-ref="variety"', 'data-ref="size"', 'data-ref="products"', 'data-wrapper="fertilizer"', 'data-wrapper="treatment"', 'data-ref="methods"', 'data-wrapper="when day'] as $needle) {
			$positions[$needle] = strpos($document->body->innerHTML, $needle);
		}

		$this->assertTrue(in_array(FALSE, $positions, TRUE) === FALSE, 'un champ manque au panneau : '.json_encode($positions));

		$this->assertEquals(['', '', ''], [
			trim($document->querySelector('[data-ref="shared"]')?->innerHTML ?? 'absent'),
			trim($document->querySelector('[data-ref="variety"]')?->innerHTML ?? 'absent'),
			trim($document->querySelector('[data-ref="size"]')?->innerHTML ?? 'absent'),
		], 'sans choix, « Partagé », la variété et le calibre ne sont pas là, vides');

		$document = $this->renderCreate([]);

		$this->assertTrue($document->querySelector('fieldset[data-planning-task-create-culture][disabled]') !== NULL, 'sans action, les champs de culture sont actifs');
		$this->assertTrue($document->querySelector('fieldset[data-planning-task-create-variety]:not([disabled])') !== NULL, 'sans action, la variété est désactivée alors que rien ne refuse encore la culture');

		$eCategory = $this->createCategory();

		if($this->assertDecorPresent($eCategory->notEmpty(), 'une catégorie hors culture') === FALSE) {
			return;
		}

		$document = $this->renderCreate(['action' => $this->createActionIn($eCategory)['id']]);

		$this->assertTrue($document->querySelector('fieldset[data-planning-task-create-culture][disabled]') !== NULL, 'une action hors culture a les champs de culture actifs');
		$this->assertTrue($document->querySelector('fieldset[data-planning-task-create-variety][disabled]') !== NULL, 'une action hors culture propose la variété d\'une culture qu\'elle refuse');

	}

	/**
	 * Les variétés des cultures choisies, celles qu'elles n'ont pas toutes désactivées, et aucune avec une espèce hors
	 * série ; le calibre d'une seule espèce ; « Partagé » des cultures d'une série qui en compte plusieurs, caché pour
	 * une récolte et pour une action hors culture. Les cultures de deux saisons se choisissent ensemble. Une culture d'une autre structure est refusée.
	 */
	public function testCultivationFields(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->getCultivation();
		$eVariety = $this->createVariety();
		$eVarietyOther = $this->createVariety();
		$this->createSlice($eCultivation, $eVariety, 50.0);
		$this->createSlice($eCultivation, $eVarietyOther, 50.0);

		$eCultivationNext = $this->createCultivation($this->createSeries('Série de la saison suivante', $this->getSeason() + 1));
		$this->createSlice($eCultivationNext, $eVariety);

		$eSize = $this->createSize($eCultivation['plant']);

		$document = $this->renderCultivationFields([$eCultivation['id']]);

		if($this->assertDecorPresent($document !== NULL, 'les champs d\'une culture') === FALSE) {
			return;
		}

		$this->assertEquals([$eVariety['id'], $eVarietyOther['id']], $this->readVarieties($document, FALSE), 'une culture de deux variétés ne les propose pas');
		$this->assertTrue($document['size']->querySelector('[name="harvestSize"] option[value="'.$eSize['id'].'"]') !== NULL, 'le calibre de l\'espèce n\'est pas proposé');
		$this->assertEquals('', trim($document['shared']->body->innerHTML), 'une série d\'une seule culture propose « Partagé »');

		$document = $this->renderCultivationFields([$eCultivation['id'], $eCultivationNext['id']]);

		if($this->assertDecorPresent($document !== NULL, 'les champs de deux saisons') === FALSE) {
			return;
		}

		$this->assertEquals([$eVariety['id']], $this->readVarieties($document, TRUE), 'la variété que deux cultures n\'ont pas toutes deux n\'est pas désactivée');

		$document = $this->renderCultivationFields([$eCultivation['id'], 'plant:'.$this->createPlant()['id']]);

		$this->assertEquals([[], ''], [
			$this->readVarieties($document, FALSE),
			trim($document['size']->body->innerHTML),
		], 'une espèce hors série choisie avec une culture laisse la variété ou le calibre de deux espèces');

		$document = $this->renderCultivationFields(['plant:'.$eCultivation['plant']['id']]);

		$this->assertTrue($document['size']->querySelector('[name="harvestSize"]') !== NULL, 'une espèce hors série ne propose pas ses calibres');

		$eCultivationShared = $this->createCultivation($this->getSeries(), $this->createPlant());

		$document = $this->renderCultivationFields([$eCultivationShared['id']]);

		$this->assertTrue($document['shared']->querySelector('fieldset[data-planning-task-create-shared]:not([disabled]) input[name="shared['.$eCultivationShared['id'].']"][value="0"][checked]') !== NULL, 'une culture d\'une série de deux cultures ne propose pas « Partagé », sa culture choisie d\'office');

		$document = $this->renderCultivationFields([$eCultivationShared['id']], $this->getAction(ACTION_RECOLTE));

		$this->assertTrue($document['shared']->querySelector('fieldset[data-planning-task-create-shared][disabled]') !== NULL, 'une récolte propose « Partagé »');

		$eCategory = $this->createCategory();

		if($this->assertDecorPresent($eCategory->notEmpty(), 'une catégorie hors culture') === FALSE) {
			return;
		}

		$document = $this->renderCultivationFields([$eCultivationShared['id']], $this->createActionIn($eCategory), eCategory: $eCategory);

		$this->assertTrue($document['shared']->querySelector('fieldset[data-planning-task-create-shared][disabled]') !== NULL, 'une action hors culture propose « Partagé » pour une culture qu\'elle refuse');

		$this->createSlice($eCultivationShared, $this->createVariety($eCultivationShared['plant']), 50.0);
		$this->createSlice($eCultivationShared, $this->createVariety($eCultivationShared['plant']), 50.0);

		$document = $this->renderCultivationFields([$eCultivationShared['id']], shared: [$eCultivationShared['id'] => '1']);

		$this->assertEquals([], $this->readVarieties($document, FALSE), 'une culture dite « Partagé », seule choisie, propose une variété, que son intervention de série ne peut pas porter');

		$document = $this->renderCultivationFields([$eCultivation['id'], $eCultivationShared['id']], shared: [$eCultivationShared['id'] => '1']);

		$this->assertEquals([$eVariety['id'], $eVarietyOther['id']], $this->readVarieties($document, TRUE), 'une culture dite « Partagé » restreint les variétés de l\'autre culture choisie à celles qu\'elles ont en commun');

		[, , $action] = $this->callDay('getCultivationFields', ['cultivation' => [$this->createCultivationForeign()['id']]]);

		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'la culture d\'une autre structure rend ses champs');

	}

	/**
	 * Le produit, avec « Nouveau produit », et sa dose, la fertilisation, le calibre et la variété s'écrivent sur
	 * chaque intervention créée et sur chaque occurrence de sa répétition, à faire comme faite.
	 */
	public function testCultureFieldsWritten(): void {

		if($this->requireAction(ACTION_TRAITEMENT) === FALSE or $this->requireAction(ACTION_FERTILISATION) === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->getCultivation();
		$eCultivationOther = $this->createCultivation($this->createSeries('Seconde série du décor'));
		$name = 'Produit du décor '.uniqid();

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'action' => $this->getAction(ACTION_TRAITEMENT)['id'],
			'cultivation' => [$eCultivation['id'], $eCultivationOther['id']],
			'products' => ['new'],
			'productQuery' => '',
			'productNew' => ['name' => $name],
			'treatment' => ['dose' => '2.5', 'unit' => \work\RoutineLib::PHYTO_UNITS[0], 'water' => '300'],
			'fertilizer' => ['n' => '12'],
			'frequency' => \series\Repeat::W1,
			'stop' => $this->getSeason().'-W45',
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'le traitement répété est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTask = \series\Task::model()
			->select('id', 'products', 'treatment', 'fertilizer')
			->whereStructure($this->eStructure)
			->whereRepeat('IN', $data->cTaskCreated->getColumnCollection('repeat'))
			->getCollection();

		$eTool = \work\Tool::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereName($name)
			->get();

		$this->assertTrue($eTool->notEmpty(), '« Nouveau produit » n\'est pas créé');
		$this->assertTrue($cTask->count() > $data->cTaskCreated->count(), 'la répétition n\'a pas créé d\'occurrences');

		foreach($cTask as $eTask) {
			$this->assertEquals([[$eTool['id']], 2.5, 300, NULL], [$eTask['products'], $eTask['treatment']['dose'] ?? NULL, $eTask['treatment']['water'] ?? NULL, $eTask['fertilizer']], 'le traitement '.$eTask['id'].' ne porte pas le produit et sa dose, ou porte la fertilisation');
		}

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput([
			'action' => $this->getAction(ACTION_FERTILISATION)['id'],
			'status' => \series\Task::DONE,
			'day' => week_date_day(\Clock::week(), 2),
			'cultivation' => [$eCultivation['id']],
			'fertilizer' => ['n' => '12', 'k' => '3'],
			'treatment' => ['dose' => '2.5', 'unit' => \work\RoutineLib::PHYTO_UNITS[0]],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'la fertilisation faite est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $this->readTask($data->cTaskCreated->first(), ['fertilizer', 'treatment']);

		$this->assertEquals([12.0, NULL, 3.0, NULL, NULL], [$eTask['fertilizer']['n'] ?? NULL, $eTask['fertilizer']['p'] ?? NULL, $eTask['fertilizer']['k'] ?? NULL, $eTask['fertilizer']['mgo'] ?? NULL, $eTask['treatment']], 'la fertilisation faite ne porte pas ses apports, ou porte un traitement');

		$eVariety = $this->createVariety();
		$this->createSlice($eCultivation, $eVariety, 50.0);
		$this->createSlice($eCultivation, $this->createVariety(), 50.0);
		$eSize = $this->createSize($eCultivation['plant']);

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'variety' => $eVariety['id'],
			'harvestSize' => $eSize['id'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte d\'une variété et d\'un calibre est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $this->readTask($data->cTaskCreated->first(), ['variety', 'harvestSize']);

		$this->assertEquals([$eVariety['id'], $eSize['id']], [$eTask['variety']['id'] ?? NULL, $eTask['harvestSize']['id'] ?? NULL], 'la récolte ne porte pas la variété et le calibre choisis');

	}

	/**
	 * « Partagé » (retour 256) : une culture d'une série de plusieurs cultures, dite partagée, donne l'intervention
	 * de la série, sans culture ni espèce, à faire comme faite, et une seule pour deux cultures de la même série.
	 * Une série d'une seule culture et une récolte sont refusées, et rien ne s'écrit.
	 */
	public function testSharedWritten(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->getCultivation();
		$eCultivationOther = $this->createCultivation($this->getSeries(), $this->createPlant());
		$eVariety = $this->createVariety();
		$this->createSlice($eCultivation, $eVariety);

		[$data, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$eCultivation['id'], $eCultivationOther['id']],
			'shared' => [$eCultivation['id'] => '1', $eCultivationOther['id'] => '1'],
			'variety' => $eVariety['id'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'l\'intervention partagée est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([[$this->getSeries()['id']], [NULL], [NULL]], [
			$this->getCreated($data, 'series'),
			$this->getCreated($data, 'cultivation'),
			$this->getCreated($data, 'plant'),
		], 'deux cultures partagées de la série ne donnent pas une seule intervention de la série, sans culture ni espèce');

		$eTask = $this->readTask($data->cTaskCreated->first(), ['series', 'cultivation', 'plant', 'variety']);

		$this->assertEquals([$this->getSeries()['id'], TRUE, TRUE, TRUE], [$eTask['series']['id'], $eTask['cultivation']->empty(), $eTask['plant']->empty(), $eTask['variety']->empty()], 'l\'intervention partagée n\'est pas écrite sur la série seule');

		[$data, , $action] = $this->callCreate('taskDay', $this->getInput([
			'status' => \series\Task::DONE,
			'day' => week_date_day(\Clock::week(), 2),
			'cultivation' => [$eCultivation['id'], $eCultivationOther['id']],
			'shared' => [$eCultivation['id'] => '1', $eCultivationOther['id'] => '0'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'faite, l\'intervention partagée est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([[NULL, $eCultivationOther['id']], [\series\Task::DONE]], [
			$this->getCreated($data, 'cultivation'),
			array_values(array_unique($this->getCreated($data, 'status'))),
		], 'faite, une culture partagée et une qui ne l\'est pas ne donnent pas l\'intervention de la série et celle de la culture');

		$before = $this->countWeekTasks();

		$eCultivationAlone = $this->createCultivation($this->createSeries('Série d\'une seule culture'));

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'cultivation' => [$eCultivationOther['id'], $eCultivationAlone['id']],
			'shared' => [$eCultivationAlone['id'] => '1'],
		]));

		$this->assertTrue(in_array('Task::cultivation.check', $this->getFails($action), TRUE), 'une série d\'une seule culture accepte une intervention partagée');

		[, , $action] = $this->callCreate('taskWeek', $this->getInput([
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'cultivation' => [$eCultivation['id']],
			'shared' => [$eCultivation['id'] => '1'],
		]));

		$this->assertTrue(in_array('Task::harvestConsistency.plant', $this->getFails($action), TRUE), 'une récolte partagée est acceptée');
		$this->assertEquals($before, $this->countWeekTasks(), 'une intervention partagée refusée en laisse créer d\'autres');

	}

	/**
	 * Les fragments de `taskDay:getCultivationFields` pour ces choix, chacun en document, ou NULL quand la route les
	 * refuse.
	 *
	 * @return ?array ['variety' => \Dom\HTMLDocument, 'size' => …, 'shared' => …]
	 */
	private function renderCultivationFields(array $choices, \work\Action $eAction = new \work\Action(), array $shared = [], \work\Category $eCategory = new \work\Category()): ?array {

		$action = $this->executePage('planning/taskDay:getCultivationFields', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $choices,
			'shared' => $shared,
		] + ($eAction->empty() ? [] : ['action' => $eAction['id']]) + ($eCategory->empty() ? [] : ['category' => $eCategory['id']]), 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'les champs des choix sont refusés : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		[, $template] = $this->runView('getCultivationFields', $action, 'taskDay');

		$fragments = $template->export();

		return [
			'variety' => $this->parse($fragments['variety']),
			'size' => $this->parse($fragments['size']),
			'shared' => $this->parse($fragments['shared']),
		];

	}

	/**
	 * Les variétés proposées, toutes ou les seules désactivées, sans « Toutes ».
	 */
	private function readVarieties(array $document, bool $enabled): array {

		$varieties = [];

		foreach($document['variety']->querySelectorAll('[name="variety"]'.($enabled ? ':not([disabled])' : '')) as $node) {

			$value = $node->getAttribute('value');

			if($value !== '' and $value !== NULL and $node->getAttribute('type') !== 'hidden') {
				$varieties[] = (int)$value;
			}

		}

		sort($varieties);

		return $varieties;

	}

	private function readTask(\series\Task $eTask, array $properties): \series\Task {

		return \series\Task::model()
			->select($properties)
			->whereId($eTask['id'])
			->get();

	}

	/**
	 * Une action de culture du décor avec sa méthode et son matériel, et un matériel d'une autre action, que
	 * l'intervention doit écarter.
	 *
	 * @return array [\work\Action, \work\Method, \work\Tool, \work\Tool]
	 */
	private function createActionMethodTool(): array {

		$eAction = $this->createActionIn($this->getCategoryCulture());

		$fw = new \FailWatch();

		$eMethod = new \work\Method(['structure' => $this->eStructure]);
		$eMethod->build(\work\MethodLib::getPropertiesCreate(), ['name' => 'Méthode du décor', 'action' => $eAction['id']]);

		$eTool = new \work\Tool(['structure' => $this->eStructure]);
		$eTool->build(\work\ToolLib::getPropertiesCreate(), ['name' => 'Outil '.uniqid(), 'action' => $eAction['id']]);

		$eToolOther = new \work\Tool(['structure' => $this->eStructure]);
		$eToolOther->build(\work\ToolLib::getPropertiesCreate(), ['name' => 'Autre '.uniqid(), 'action' => $this->getAction(ACTION_RECOLTE)['id']]);

		$fw->validate();

		\work\MethodLib::create($eMethod);
		\work\ToolLib::create($eTool);
		\work\ToolLib::create($eToolOther);

		// `askCollection()` lit méthodes et matériel dans un cache du processus, que ceux créés ici ne rejoignent pas.
		new \ReflectionProperty(\work\MethodCrud::class, 'cache')->setValue(NULL, []);
		new \ReflectionProperty(\work\ToolCrud::class, 'cache')->setValue(NULL, []);

		return [$eAction, $eMethod, $eTool, $eToolOther];

	}

	/**
	 * Le panneau de « Nouvelle intervention » du Jour, ou NULL quand la route le refuse.
	 */
	private function renderCreate(array $input): ?\Dom\HTMLDocument {

		[$data, , $action] = $this->callDay('create', $input, 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		return $this->parse(new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime)->body);

	}

	private function readMethodsTools(\series\Task $eTask): array {

		$eTaskRead = \series\Task::model()
			->select('methods', 'tools')
			->whereId($eTask['id'])
			->get();

		return [$eTaskRead['methods'], $eTaskRead['tools']];

	}

	private function getInput(array $input = []): array {

		return $input + [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'day' => '',
			'description' => '',
		];

	}

	/**
	 * Le panneau « Nouvelle intervention » de la Semaine sur une semaine donnée : les données que la page
	 * prépare, NULL quand elle le refuse.
	 */
	private function callWeekCreate(string $week): ?\stdClass {

		$action = $this->executePage('planning/taskWeek:create', [
			'id' => $this->eStructure['id'],
			'week' => $week,
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'semaine '.$week.' : le panneau est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

	}

	/**
	 * `doCreate` de la Semaine ou du Jour, rendu par sa vue : ses données et ses instructions, ou l'action
	 * levée à la place d'une vue.
	 */
	private function callCreate(string $page, array $input): array {

		// « Quand » s'ouvre sur la semaine de l'écran : sans `when`, l'intervention part pour elle, comme du panneau tel qu'il s'ouvre.
		$input += ['when' => $input['week'] ?? \Clock::week()];

		if($page === 'taskDay') {
			return $this->callDay('doCreate', $input);
		}

		$action = $this->executePage('planning/taskWeek:doCreate', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doCreate', $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Une propriété des interventions créées par la route, dans l'ordre de création ; l'identifiant pour
	 * un élément, que chaque cas pose.
	 */
	private function getCreated(\stdClass $data, string $property): array {

		$values = [];

		foreach($data->cTaskCreated as $eTask) {
			// Une intervention partagée (retour 256) n'a ni culture ni espèce : `NULL`, comme une propriété vide.
			if($eTask[$property] instanceof \Element) {
				$values[] = $eTask[$property]->empty() ? NULL : $eTask[$property]['id'];
			} else {
				$values[] = $eTask[$property];
			}
		}

		return $values;

	}

	/**
	 * Une culture d'une autre structure, sur une espèce neuve, que la route doit refuser.
	 */
	private function createCultivationForeign(): \series\Cultivation {

		$this->stopOnClosedTransaction('avant createCultivationForeign()');

		$eCultivation = new \series\Cultivation([
			'structure' => $this->getStructureForeign(),
			'season' => $this->getSeason(),
			'series' => $this->getSeries(),
			'sequence' => new \sequence\Sequence(),
			'crop' => new \sequence\Crop(),
			'plant' => $this->createPlant(),
			'distance' => \series\Cultivation::SPACING,
			'varietiesUnit' => \series\Cultivation::PERCENT,
			'varietiesTool' => new \work\Tool(),
			'mainUnit' => \series\Cultivation::KG,
			'harvestPeriodExpected' => \series\Cultivation::MONTH,
		]);

		// Sans `build()` ni `CultivationLib::create()` : le build borne la série et l'espèce à la structure, et
		// le Lib recalcule la série de la structure connectée. Le cas veut justement une ligne qu'aucun des deux
		// ne laisserait passer, une culture d'une autre structure, lue seulement par sa structure.
		\series\Cultivation::model()->insert($eCultivation);

		return $eCultivation;

	}

	/**
	 * Le temps écrit pour une personne un jour sur des interventions, arrondi au dix-millième : la répartition
	 * écrit des parts à six décimales.
	 */
	private function getTimeWritten(\Collection $cTask, \user\User $eUser, string $date): float {

		$cTimesheet = \series\Timesheet::model()
			->select('time')
			->whereTask('IN', $cTask)
			->whereUser($eUser)
			->whereDate($date)
			->getCollection();

		return round($cTimesheet->sum('time'), 4);

	}

	/** Le récolté de la culture, par jour puis par unité. */
	private function getHarvested(\series\Cultivation $eCultivation): array {

		$cHarvest = \series\Harvest::model()
			->select(['date', 'unit', 'quantity'])
			->whereCultivation($eCultivation)
			->getCollection();

		$harvested = [];

		foreach($cHarvest as $eHarvest) {
			$harvested[$eHarvest['date']][$eHarvest['unit']] = ($harvested[$eHarvest['date']][$eHarvest['unit']] ?? 0.0) + $eHarvest['quantity'];
		}

		return $harvested;

	}

	private function getHarvestedByTask(\series\Task $eTask): array {

		$cHarvest = \series\Harvest::model()
			->select(['date', 'unit', 'quantity'])
			->whereTask($eTask)
			->getCollection();

		$harvested = [];

		foreach($cHarvest as $eHarvest) {
			$harvested[$eHarvest['date']][$eHarvest['unit']] = ($harvested[$eHarvest['date']][$eHarvest['unit']] ?? 0.0) + $eHarvest['quantity'];
		}

		return $harvested;

	}

	private function readHarvest(\series\Task $eTask): ?float {

		return \series\Task::model()
			->select('harvest')
			->whereId($eTask['id'])
			->get()['harvest'];

	}

	private function parse(string $html): \Dom\HTMLDocument {

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	private function countWeekTasks(): int {

		return \series\Task::model()
			->whereStructure($this->eStructure)
			->wherePlannedWeek(\Clock::week())
			->count();

	}

}
?>
