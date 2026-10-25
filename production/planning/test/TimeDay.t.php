<?php
namespace planning;

/**
 * La fiche de temps (L6b) : ce qu'elle montre pour une personne et un jour, et ce que ses gestes écrivent
 * ou refusent, la journée (`doWorkingTime`), le temps d'une ligne (`doTime`), la coche et le tampon
 * (`doDone`, `doTodo`) ; « Nouvelle intervention » ouvert depuis la fiche est dans `TimeDayCreateTest`. Chaque
 * cas passe par la vraie route, avec ses validations, dans la transaction du décor (`HarvestFixtureTest`),
 * et ne regarde que ce qu'il pose. Que la réponse ciblée soit l'écran d'un rechargement est éprouvé par
 * `FingerprintTest` (`testTime*`).
 *
 * Les états particuliers d'abord : le jour à venir, l'onglet d'une autre personne, la personne au droit
 * limité, la journée hors de la grille, la ligne masquée.
 *
 * Le jour du décor est le jeudi de la semaine 39. La structure 4 a quatre membres, dont un seul présent :
 * Alice, qui a le planning complet et gère la ferme. Bruno, au planning limité, devient présent dans les
 * cas qui ont besoin d'une seconde personne (`addBruno()`).
 *
 * Lancement : dev/test package=planning file=TimeDay
 */
class TimeDayTest extends HarvestFixtureTest {

	/**
	 * Un jour à venir ne propose pas « J'ai aussi fait », et sa journée se lit sans s'ouvrir. Un jour hors de
	 * la semaine affichée est refusé.
	 */
	public function testFutureDay(): void {

		$friday = week_date_day(\Clock::week(), 5);

		[$document, $data] = $this->renderTimeDay(['date' => $friday]);

		$this->assertTrue($document->querySelector('#planning-time a[href*="timeDay:create"]') === NULL, 'le jour à venir porte « J\'ai aussi fait »');
		$this->assertTrue($document->querySelector('#planning-time-day-'.$friday.' .planning-time-working.planning-duration, #planning-time-working-day .planning-time-working.planning-duration') === NULL, 'la journée d\'un jour à venir s\'ouvre');
		$this->assertFalse($data->canWorking, 'la journée d\'un jour à venir se saisit');
		$this->assertTrue($document->getElementById('planning-time-tasks-day') !== NULL, 'le jour à venir perd le nœud que les gestes remplacent');
		$this->assertTrue($document->querySelector('#planning-time [data-planning-time-rule]') === NULL, 'la réglette d\'un jour à venir se saisit');

		$action = $this->executePage($this->getTimeDayPath(), ['date' => date('Y-m-d', strtotime(week_date_day(\Clock::week(), 1).' - 1 DAY'))], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'un jour hors de la semaine affichée est accepté');

	}

	/**
	 * Avec Bruno présent, la fiche s'ouvre sur Alice ; sur l'onglet de Bruno, tout s'écrit au nom de Bruno : la
	 * journée, le temps d'une ligne, et le temps prévu posé par la coche (Q2).
	 */
	public function testOtherTab(): void {

		$eUserBruno = $this->addBruno();

		[, $data] = $this->renderTimeDay();
		$this->assertEquals($this->getMember()['id'], $data->eUserTime['id'], 'la fiche ne s\'ouvre pas sur la personne connectée');

		[, $data] = $this->renderTimeDay(['user' => $eUserBruno['id']]);

		$this->assertEquals($eUserBruno['id'], $data->eUserTime['id'], 'l\'onglet de Bruno ne montre pas Bruno');
		$this->assertTrue($data->canWorking and $data->canTime, 'Alice ne peut pas saisir pour Bruno');

		[, , $action] = $this->callTimeDay('doWorkingTime', ['user' => $eUserBruno['id'], 'date' => \Clock::date(), 'time' => '7.5']);

		$this->assertTrue($action instanceof \ViewAction, 'la journée de Bruno est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(7.5, $this->getWorkingTime($eUserBruno, \Clock::date()), 'la journée n\'est pas écrite au nom de Bruno');
		$this->assertEquals(NULL, $this->getWorkingTime($this->getMember(), \Clock::date()), 'la journée de Bruno est écrite au nom d\'Alice');

		$eTaskTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$data, , $action] = $this->callTimeDay('doTime', ['user' => $eUserBruno['id'], 'task' => $eTaskTime['id'], 'date' => \Clock::date(), 'time' => '1.25']);

		$this->assertTrue($action instanceof \ViewAction, 'le temps de Bruno est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$eUserBruno['id'] => 1.25], $this->getTimesByUser($eTaskTime), 'le temps n\'est pas écrit au nom de Bruno seul');
		$this->assertEquals(1.25, $data->timeDay['times'][\Clock::date()], 'les chiffres de Bruno ne portent pas son temps');

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskDone['timeExpected'] = 2.0;
		// Par le modèle et non par `TaskLib::update()` : le décor veut un temps prévu exact, que la coche
		// pose ensuite au nom de la personne de l'onglet, sans la synchronisation d'une mise à jour.
		\series\Task::model()->select(['timeExpected'])->update($eTaskDone);

		[, , $action] = $this->callTimeDay('doDone', ['user' => $eUserBruno['id'], 'task' => $eTaskDone['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'la coche sur l\'onglet de Bruno est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(\series\TaskLib::getById($eTaskDone['id'])->isDone(), 'la coche n\'est pas écrite');
		$this->assertEquals([$eUserBruno['id'] => 2.0], $this->getTimesByUser($eTaskDone), 'le temps prévu n\'est pas posé au nom de Bruno');

	}

	/**
	 * L'onglet « Équipe » (M-Temps, retour 139) : refusé à une fiche d'une seule personne ; avec Bruno présent,
	 * ses jours restent dans l'onglet, et la journée de chacun se saisit sur sa ligne, par la tuile et la
	 * réglette de sa fiche, qui partent de l'équipe (`origin=team`) au nom de la personne de la ligne. Rien ne
	 * s'écrit au nom de l'équipe.
	 */
	public function testTeam(): void {

		$action = $this->executePage($this->getTimeDayPath(), ['user' => TimeWeekLib::TEAM], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'l\'équipe d\'une seule personne est acceptée');

		$eUserBruno = $this->addBruno();

		$monday = week_date_day(\Clock::week(), 1);

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);
		$this->callTimeDay('doWorkingTime', ['user' => $eUserBruno['id'], 'date' => $monday, 'time' => '5']);

		[$document, $data] = $this->renderTimeDay(['user' => TimeWeekLib::TEAM, 'date' => $monday]);

		$this->assertTrue($data->team, 'l\'onglet « Équipe » ne montre pas l\'équipe');
		$this->assertFalse($data->canWorking or $data->canTime, 'la fiche de l\'équipe se saisit au nom de l\'équipe');
		$this->assertTrue(str_contains($document->getElementById('planning-time-day-'.$monday)?->getAttribute('href') ?? '', 'user='.TimeWeekLib::TEAM), 'les jours de l\'équipe quittent l\'onglet');

		foreach([$this->getMember()['id'], $eUserBruno['id']] as $user) {

			$lane = $document->getElementById('planning-time-team-lane-'.$user);

			if($this->assertTrue($lane !== NULL, 'la ligne de la personne '.$user.' n\'a pas son identifiant') === FALSE) {
				continue;
			}

			$tile = $lane->querySelector('.planning-week-time-lane-field .planning-time-working.planning-duration');

			if($this->assertTrue($tile !== NULL, 'la journée de la personne '.$user.' ne se saisit pas sur sa ligne')) {
				$this->assertEquals([(string)$user, TimeWeekLib::TEAM, $monday], [$tile->getAttribute('data-user'), $tile->getAttribute('data-origin'), $tile->getAttribute('data-date')], 'la tuile de la personne '.$user.' ne part pas de l\'équipe à son nom, pour lundi');
			}

			$this->assertTrue($lane->querySelector('[data-planning-time-rule]') !== NULL, 'la réglette de la personne '.$user.' ne se saisit pas');

		}

		foreach(['doWorkingTime' => ['date' => $monday, 'time' => '9', 'origin' => TimeWeekLib::TEAM], 'doTime' => ['task' => $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday)['id'], 'date' => $monday, 'time' => '1', 'columns' => '2']] as $route => $input) {

			[, , $action] = $this->callTimeDay($route, $input + ['user' => TimeWeekLib::TEAM]);
			$this->assertTrue($action instanceof \NotExpectedAction, $route.' est accepté au nom de l\'équipe');

		}

		$this->assertEquals(7.0, $this->getWorkingTime($this->getMember(), $monday), 'un geste au nom de l\'équipe écrit la journée d\'Alice');
		$this->assertEquals(5.0, $this->getWorkingTime($eUserBruno, $monday), 'un geste au nom de l\'équipe écrit la journée de Bruno');

	}

	/**
	 * Alice, qui a le planning complet, saisit la journée de Bruno sur sa ligne de l'équipe (R158, contrôle de
	 * V-Planning) : elle s'écrit au nom de Bruno, et la réponse ne redessine que sa ligne, la pastille du jour
	 * la somme de la semaine, celle de chacun et le jour de l'équipe, sans la jauge d'une personne, que la fiche
	 * de l'équipe n'a pas. Que l'écran
	 * obtenu soit celui d'un rechargement est éprouvé par `FingerprintTest::testTimeTeamWorking()`. Une
	 * origine inconnue reste refusée.
	 */
	public function testTeamWorking(): void {

		$eUserBruno = $this->addBruno();

		$monday = week_date_day(\Clock::week(), 1);

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);

		[$data, $instructions, $action] = $this->callTimeDayTeam(['user' => $eUserBruno['id'], 'date' => $monday, 'time' => '6']);

		if($this->assertTrue($action instanceof \ViewAction, 'la journée de Bruno saisie depuis l\'équipe est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(6.0, $this->getWorkingTime($eUserBruno, $monday), 'la journée de Bruno n\'est pas écrite depuis l\'équipe');
		$this->assertEquals(7.0, $this->getWorkingTime($this->getMember(), $monday), 'la saisie de l\'équipe touche la journée d\'Alice');

		$this->assertEquals(
			['#planning-time-team-lane-'.$eUserBruno['id'], '#planning-time-day-'.$monday, '#planning-time-week-total', '#planning-time-team-total'],
			array_map(fn(array $instruction) => $instruction[1], $instructions),
			'la réponse ne redessine pas seulement la ligne de Bruno, la pastille de lundi, la somme de la semaine et le jour de l\'équipe'
		);

		$json = json_encode($instructions, JSON_UNESCAPED_UNICODE);

		$this->assertTrue(str_contains($json, 'user='.TimeWeekLib::TEAM), 'la pastille redessinée quitte l\'onglet de l\'équipe');

		[, , $action] = $this->callTimeDay('doWorkingTime', ['user' => $eUserBruno['id'], 'date' => $monday, 'time' => '4', 'origin' => 'equipe']);
		$this->assertTrue($action instanceof \NotExpectedAction, 'une origine inconnue est acceptée');
		$this->assertEquals(6.0, $this->getWorkingTime($eUserBruno, $monday), 'une origine inconnue écrit la journée de Bruno');

	}

	/**
	 * Bruno, au planning limité, n'a pas d'équipe (une fiche d'une seule personne) et ne peut pas écrire la
	 * journée d'Alice en se donnant l'origine de l'équipe : la ligne d'Alice n'est jamais sur sa fiche. Sa
	 * propre journée, envoyée depuis l'équipe, est refusée avant d'être écrite.
	 */
	public function testTeamLimited(): void {

		$eUserBruno = $this->addBruno();

		$monday = week_date_day(\Clock::week(), 1);

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);

		\user\ConnectionLib::setOnline($eUserBruno);

		$action = $this->executePage($this->getTimeDayPath(), ['user' => TimeWeekLib::TEAM, 'date' => $monday], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'Bruno ouvre l\'équipe');

		[, , $action] = $this->callTimeDayTeam(['user' => $this->getMember()['id'], 'date' => $monday, 'time' => '3']);
		$this->assertTrue($action instanceof \NotExpectedAction, 'Bruno saisit la journée d\'Alice depuis l\'équipe');
		$this->assertEquals(7.0, $this->getWorkingTime($this->getMember(), $monday), 'la journée d\'Alice est écrite par Bruno');

		[, , $action] = $this->callTimeDayTeam(['date' => $monday, 'time' => '3']);
		$this->assertTrue($action instanceof \NotExpectedAction, 'Bruno, seul sur sa fiche, saisit sa journée depuis une équipe qu\'il n\'a pas');
		$this->assertEquals(NULL, $this->getWorkingTime($eUserBruno, $monday), 'la journée de Bruno est écrite avant que l\'équipe ne soit refusée');

	}

	/**
	 * Un jour à saisir qui porte des interventions les dessine (R-Barre-Interventions) : dans l'Équipe, la part
	 * de la barre à la largeur de ses interventions sur l'échelle de 12 h ; sur la fiche, la barre des
	 * interventions prend la même largeur (R2).
	 */
	public function testMissingDayTime(): void {

		$eUserBruno = $this->addBruno();

		[$monday, $tuesday] = array_slice(week_dates(\Clock::week()), 0, 2);

		$this->callTimeDay('doWorkingTime', ['user' => $eUserBruno['id'], 'date' => $monday, 'time' => '5']);
		$this->createTimesheet($this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday), 1.75, $monday);
		$this->createTimesheet($this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $tuesday), 7.0, $tuesday);

		[$document] = $this->renderTimeDay(['user' => TimeWeekLib::TEAM, 'date' => $monday]);

		$lane = $document->getElementById('planning-time-team-lane-'.$this->getMember()['id']);

		if($this->assertTrue($lane !== NULL, 'la ligne d\'Alice dans l\'équipe est absente')) {
			$this->assertEquals('width: 14.58%', $lane->querySelector('.planning-time-rule .planning-week-time-lane-done')?->getAttribute('style'), 'la part des 1h45 d\'interventions d\'Alice n\'est pas dessinée à sa largeur');
		}

		foreach([$monday => ['14.58', '1h45'], $tuesday => ['58.33', '7h00']] as $date => [$width, $time]) {

			[$document] = $this->renderTimeDay(['date' => $date]);
			$this->assertEquals(['width: '.$width.'%'], $this->getTaskWidths($document), 'la part des '.$time.' d\'interventions du '.$date.' n\'est pas dessinée à sa largeur sur la barre des interventions');

		}

	}

	/**
	 * Une saisie sans durée est refusée par `time.missing` et ne remet pas la ligne à zéro (R-Retours-0410,
	 * point 20b) : la fiche construit le temps sur la liste `day`, comme la page d'une intervention.
	 */
	public function testTimeMissing(): void {

		$monday = week_date_day(\Clock::week(), 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);
		$this->createTimesheet($eTask, 1.5, $monday);

		[, , $action] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => $monday]);

		$this->assertEquals(['Timesheet::time.missing'], $this->getFails($action), 'une saisie sans durée n\'est pas refusée par time.missing');

		$time = \series\Timesheet::model()
			->whereTask($eTask)
			->whereDate($monday)
			->getValue(new \Sql('SUM(time)', 'float'));

		$this->assertEquals(1.5, $time, 'une saisie sans durée change le temps de la ligne');

	}

	/**
	 * Le temps du jour par action, la barre des interventions : la plus longue d'abord, et la somme des actions
	 * égale au chiffre du jour, y compris pour une intervention à faire d'une série close, que la semaine écarte
	 * (`applyOpenSeries()`) mais dont le temps compte dans le jour. Le détail du jour ne la porte pas : il ne
	 * nomme que les interventions de la liste (R-Retours-0810, I). La barre des interventions les pose par
	 * action (R2), au-delà de la journée comme en deçà.
	 */
	public function testActions(): void {

		$eTaskPlantation = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskPlantation, 1.5, \Clock::date());

		$eTaskClosed = $this->createTask($this->getCultivation(), ACTION_FERTILISATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskClosed, 0.75, \Clock::date());

		$eSeries = $this->getCultivation()['series'];
		$eSeries['status'] = \series\Series::CLOSED;

		// Sans `build()`, qui vérifierait le statut posté : le décor clôt sa propre série, comme la clôture
		// des séries (`SeriesLib::closeCollection()`).
		\series\SeriesLib::update($eSeries, ['status']);

		[$document, $data] = $this->renderTimeDay();

		$this->assertFalse($data->cTask->offsetExists($eTaskClosed['id']), 'l\'intervention à faire d\'une série close est encore dans la semaine, le cas n\'éprouve plus rien');

		$actions = $data->timeDay['actions'];

		$this->assertEquals([ACTION_PLANTATION, ACTION_FERTILISATION], array_map(fn(array $action) => $action['eAction']['fqn'], $actions), 'les actions du jour ne sont pas rangées de la plus longue à la plus courte, ou l\'action de la série close manque');
		$this->assertEquals(round($data->timeDay['day']['time'], 2), round(array_sum(array_column($actions, 'time')), 2), 'la somme des actions n\'est pas le temps du jour (actions '.array_sum(array_column($actions, 'time')).', jour '.$data->timeDay['day']['time'].')');

		$this->assertEquals([$eTaskPlantation['id']], $data->timeDay['cTaskSpent']->getIds(), 'le détail du jour ne porte pas la seule plantation de la liste');
		$this->assertEquals(['width: 12.5%', 'width: 6.25%'], $this->getTaskWidths($document), 'la barre des interventions ne pose pas une part par action, la plus longue d\'abord');

		$this->callTimeDay('doWorkingTime', ['date' => \Clock::date(), 'time' => '2']);

		[$document, $data] = $this->renderTimeDay();

		$this->assertTrue($data->timeDay['day']['time'] > $data->timeDay['day']['working'], 'le jour de 2h15 pour 2h00 ne dépasse pas, le cas n\'éprouve plus rien');
		$this->assertEquals(['width: 12.5%', 'width: 6.25%'], $this->getTaskWidths($document), 'la barre des interventions coupe le temps d\'une action à la journée de 2h00');

		[, $data] = $this->renderTimeDay(['date' => week_date_day(\Clock::week(), 1)]);

		$this->assertEquals([], $data->timeDay['actions'], 'un jour sans temps a des actions');

	}

	/**
	 * Un temps dont l'intervention n'existe plus, reste de 2021 que `\series\TaskLib::delete()` ne laisse plus
	 * naître : la fiche s'affiche, le temps compte dans le chiffre du jour sans avoir d'action, et la plantation
	 * garde la sienne.
	 */
	public function testActionsOrphan(): void {

		$eTaskPlantation = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskPlantation, 1.5, \Clock::date());

		$eTaskDeleted = $this->createTask($this->getCultivation(), ACTION_FERTILISATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskDeleted, 0.5, \Clock::date());

		// Sans `TaskLib::delete()`, qui supprimerait aussi les temps de l'intervention : le décor reproduit les
		// lignes de 2021, dont l'intervention est partie seule.
		\series\Task::model()->delete($eTaskDeleted);

		[, $data] = $this->renderTimeDay();

		$this->assertEquals(2.0, round($data->timeDay['day']['time'], 2), 'le temps sans intervention ne compte plus dans le chiffre du jour');
		$this->assertEquals([ACTION_PLANTATION], array_map(fn(array $action) => $action['eAction']['fqn'], $data->timeDay['actions']), 'le temps sans intervention a une action, ou la plantation manque');

	}

	/**
	 * Le détail du jour dans la carte (R-Retours-0810, I, piste 2) : sous la barre, une ligne par intervention où
	 * la personne a du temps ce jour-là, dans l'ordre de la barre, l'action au plus long temps d'abord puis la plus
	 * longue intervention dans l'action. Chacune se nomme par son action et, en texte seul, par ce qui la nomme dans
	 * la liste : la série sans la quantité récoltée, la première ligne de la note sans sa case, la première méthode,
	 * ou rien. Une intervention qui n'a du temps qu'un autre jour n'y est pas. Le pied dit la somme des lignes :
	 * ni le lundi, ni une intervention à faire d'une série close, que le temps du jour compte sans lui donner de
	 * ligne.
	 */
	public function testTasksSpent(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$today = \Clock::date();

		$eTaskHarvest = $this->createTaskFlow(ACTION_RECOLTE, \series\Task::DONE, ['harvest' => NULL, 'harvestUnit' => \series\Task::KG]);
		$this->createHarvest($eTaskHarvest, 12.0, $today);
		$this->createTimesheet($eTaskHarvest, 2.0, $today);

		$eTaskPlantationShort = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $today);
		$this->createTimesheet($eTaskPlantationShort, 0.25, $today);

		$eTaskPlantationLong = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $today);
		$this->createTimesheet($eTaskPlantationLong, 1.0, $today);

		$eTaskNote = $this->createTaskBare($today, [], "o Hangar - 492\nPuis le tunnel");
		$this->createTimesheet($eTaskNote, 0.5, $today);

		$eTaskMethod = $this->createTaskBare($today, [$this->createMethod('Témoin AMAP')['id']]);
		$this->createTimesheet($eTaskMethod, 0.25, $today);

		$eTaskNothing = $this->createTaskBare($today);
		$this->createTimesheet($eTaskNothing, 0.1, $today);

		$eTaskMonday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);
		$this->createTimesheet($eTaskMonday, 3.0, $monday);

		$eTaskClosed = $this->createTask($this->getCultivation(), ACTION_FERTILISATION, \series\Task::TODO, \Clock::week(), $today);
		$this->createTimesheet($eTaskClosed, 0.5, $today);

		$eSeries = $this->getCultivation()['series'];
		$eSeries['status'] = \series\Series::CLOSED;

		// Sans `build()`, qui vérifierait le statut posté : le décor clôt sa propre série, comme la clôture
		// des séries (`SeriesLib::closeCollection()`).
		\series\SeriesLib::update($eSeries, ['status']);

		[$document, $data] = $this->renderTimeDay();

		$this->assertEquals(4.6, round($data->timeDay['day']['time'], 2), 'le temps du jour ne compte pas l\'intervention de la série close, et le cas ne sépare plus le pied du temps du jour');
		$this->assertEquals(4.1, round($data->timeDay['timeSpent'], 2), 'le pied du détail n\'est pas la somme de ses lignes');

		$this->assertEquals([$eTaskHarvest['id'], $eTaskPlantationLong['id'], $eTaskPlantationShort['id'], $eTaskNote['id'], $eTaskMethod['id'], $eTaskNothing['id']], $data->timeDay['cTaskSpent']->getIds(), 'le détail du jour n\'est pas dans l\'ordre de la barre, ou porte l\'intervention du lundi');

		$series = $this->getSeriesName();
		$traitement = $this->getAction(ACTION_TRAITEMENT)['name'];

		$this->assertEquals([
			$this->getAction(ACTION_RECOLTE)['name'].' · '.$series.' 2h00',
			$this->getAction(ACTION_PLANTATION)['name'].' · '.$series.' 1h00',
			$this->getAction(ACTION_PLANTATION)['name'].' · '.$series.' 0h15',
			$traitement.' · Hangar - 492 0h30',
			$traitement.' · Témoin AMAP 0h15',
			$traitement.' 0h06',
		], $this->getTasksSpentTexts($document->querySelector('#planning-time-tasks-day')), 'une ligne du détail ne dit pas son action, son nom en texte seul et son temps');

		$this->assertEquals('4h06', trim($document->querySelector('#planning-time-tasks-day .planning-time-legend > .planning-time-legend-total:last-child > b')?->textContent ?? ''), 'le pied du détail n\'écrit pas la somme de ses lignes');

	}

	/**
	 * Le détail de chacun sur la fiche Équipe (R-Retours-0810, I, piste 3) : sous la ligne de chaque personne qui
	 * a du temps ce jour-là, hors de la ligne que la saisie de la journée remplace, le même détail que dans sa
	 * carte, avec ses seules interventions.
	 */
	public function testTeamTasksSpent(): void {

		$eUserBruno = $this->addBruno();
		$eUserAlice = $this->getMember();
		$today = \Clock::date();

		$eTaskShared = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $today);
		$this->createTimesheet($eTaskShared, 1.0, $today);

		[, , $action] = $this->callTimeDay('doTime', ['user' => $eUserBruno['id'], 'task' => $eTaskShared['id'], 'date' => $today, 'time' => '0.5', 'columns' => '2']);

		if($this->assertDecorPresent($action instanceof \ViewAction, 'le temps de Bruno sur la plantation s\'écrit') === FALSE) {
			return;
		}

		// Deux interventions de même durée dans la même action. La fiche les reçoit rangées par espèce
		// (`TaskWeekLib::get()`), l'équipe par identifiant : la seconde créée, sur l'espèce qui vient d'abord
		// dans l'alphabet, arrive en tête sur la fiche et en second dans l'équipe. Seul le départage par
		// l'intervention la plus ancienne range les deux écrans pareil.
		$plants = [];

		foreach(['Radis', 'Ail'] as $name) {

			$ePlant = $this->createPlant();

			// Sans `build()`, qui vérifierait un nom saisi : le décor veut un nom choisi pour son rang dans
			// l'alphabet, rendu unique par `uniqid()`.
			$ePlant['name'] = $name.' '.uniqid();

			\plant\PlantLib::update($ePlant, ['name']);

			$this->createTimesheet($this->createTaskBare($today, ePlant: $ePlant), 0.75, $today);

			$plants[] = $ePlant['name'];

		}

		[$document] = $this->renderTimeDay(['user' => TimeWeekLib::TEAM]);

		$plantation = $this->getAction(ACTION_PLANTATION)['name'].' · '.$this->getSeriesName();
		$treatment = $this->getAction(ACTION_TRAITEMENT)['name'];

		foreach([$eUserAlice['id'] => [$treatment.' · '.$plants[0].' 0h45', $treatment.' · '.$plants[1].' 0h45', $plantation.' 1h00'], $eUserBruno['id'] => [$plantation.' 0h30']] as $user => $expected) {

			$legend = $document->querySelector('#planning-time-team-lane-'.$user.' + .util-fold-content > .planning-time-legend');

			if($this->assertTrue($legend !== NULL, 'le détail de la personne '.$user.' ne suit pas sa ligne, dans son pli') === FALSE) {
				continue;
			}

			$this->assertEquals($expected, $this->getTasksSpentTexts($legend), 'le détail de la personne '.$user.' ne porte pas ses seules interventions du jour, dans l\'ordre de la barre');
			$this->assertTrue($legend->querySelector('.planning-time-legend-total') === NULL, 'le détail de la personne '.$user.' porte un pied, que sa ligne dit déjà');

			[$documentUser] = $this->renderTimeDay(['user' => $user]);

			$this->assertEquals($expected, $this->getTasksSpentTexts($documentUser->querySelector('#planning-time-tasks-day .planning-time-legend')), 'la fiche de la personne '.$user.' ne range pas sa journée comme l\'équipe');

		}

	}

	/**
	 * La semaine choisie à la place d'un jour sur la fiche de l'équipe (R-Retours-1210, 309) : la somme de la
	 * semaine mène à la semaine choisie de l'équipe, la somme de chacun est celle de ses jours, et l'adresse ne
	 * porte pas un jour et la semaine à la fois. Le détail du jour de chacun est replié à l'arrivée (308), et
	 * ouvert d'office quand le tableau ne montre qu'une personne.
	 */
	public function testTeamFolds(): void {

		$eUserBruno = $this->addBruno();
		$eUserAlice = $this->getMember();

		$monday = week_date_day(\Clock::week(), 1);
		$today = \Clock::date();

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);
		$this->callTimeDayTeam(['user' => $eUserBruno['id'], 'date' => $monday, 'time' => '6']);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $today);
		$this->createTimesheet($eTask, 1.5, $today);

		[$document, $data] = $this->renderTimeDay(['user' => TimeWeekLib::TEAM]);

		$total = $document->querySelector('#planning-time-week-total > a');

		$this->assertTrue(str_ends_with($total?->getAttribute('href') ?? '', '?period='.TimeWeekLib::WEEK.'&user='.TimeWeekLib::TEAM), 'la somme ne mène pas à la semaine choisie de l\'équipe');

		foreach($data->timeWeek['cUserTime'] as $eUser) {
			$this->assertEquals(TimeWeekLib::getTotal($eUser['timeDays']), $eUser['timeTotal'], 'la somme d\'une personne n\'est pas celle de ses jours');
		}

		$this->assertTrue($document->querySelector('#planning-time .planning-time-team-fold.util-fold-open') === NULL, 'le détail du jour arrive déplié à plusieurs');

		// L'équipe ne se lit pas sous deux personnes (`TimeWeekLib::read()`) : le tableau d'une seule personne
		// se rend ici de la même lecture, bornée à Alice.
		$timeWeek = $data->timeWeek;
		$timeWeek['cUserTime'] = $timeWeek['cUserTime']->find(fn($eUser) => $eUser->is($eUserAlice));

		$documentAlone = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new TimeDayUi()->getTeamSheet($data->eStructure, $data->week, $data->date, $timeWeek, $timeWeek['cUserTime'], $data->eMember).'</body></html>', LIBXML_NOERROR);

		$this->assertEquals(1, $documentAlone->querySelectorAll('#planning-time .planning-time-team-fold.util-fold-open')->length, 'une personne seule dans le tableau n\'a pas son détail ouvert d\'office');

		foreach([
			'un jour et la semaine à la fois' => ['user' => TimeWeekLib::TEAM, 'period' => TimeWeekLib::WEEK, 'date' => $monday],
			'une période inconnue' => ['user' => TimeWeekLib::TEAM, 'period' => 'month'],
			'un jour et la semaine à la fois sur la fiche d\'une personne' => ['user' => $eUserAlice['id'], 'period' => TimeWeekLib::WEEK, 'date' => $monday],
			'une période inconnue sur la fiche d\'une personne' => ['user' => $eUserAlice['id'], 'period' => 'month'],
		] as $case => $input) {
			$this->assertTrue($this->executePage($this->getTimeDayPath(), $input, 'GET') instanceof \NotExpectedAction, 'l\'adresse accepte '.$case);
		}

	}

	/**
	 * La semaine choisie sur la fiche d'une personne (R-Retours-1210, 326) : la somme de la semaine en champs y
	 * mène, en gardant la personne de la fiche. Choisie, un tableau liste les interventions où la personne a du temps
	 * dans la semaine, chacune par le lien de sa fiche, puis leur somme. Le temps d'un autre n'y entre pas.
	 */
	public function testWeekTasks(): void {

		$eUserBruno = $this->addBruno();

		$week = \Clock::week();
		$monday = week_date_day($week, 1);
		$tuesday = week_date_day($week, 2);

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, $monday);
		$this->createTimesheet($eTaskDone, 3.0, $monday);
		$this->createTimesheet($eTaskDone, 1.0, $tuesday);

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $tuesday);
		$this->createTimesheet($eTaskTodo, 0.5, $tuesday);

		$eTaskOther = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $tuesday);
		$this->callTimeDay('doTime', ['user' => $eUserBruno['id'], 'task' => $eTaskOther['id'], 'date' => $tuesday, 'time' => '2', 'columns' => '2']);

		[$document] = $this->renderTimeDay(['date' => $monday]);

		// La somme de la semaine en champs s'affiche avant toute journée, à 0h00 : elle y mène dès lors.
		$this->assertTrue($document->querySelector('#planning-time-week-total > a.util-days-entry-value') !== NULL, 'la semaine en champs ne mène pas à la semaine choisie avant toute journée saisie');

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);
		$this->callTimeDayTeam(['user' => $eUserBruno['id'], 'date' => $monday, 'time' => '6']);

		[$document] = $this->renderTimeDay(['date' => $monday]);

		$total = $document->querySelector('#planning-time-week-total > a.util-days-entry-value');

		$this->assertTrue(str_ends_with($total?->getAttribute('href') ?? '', '?period='.TimeWeekLib::WEEK), 'la somme ne mène pas à la semaine choisie de la personne connectée');

		[$documentBruno] = $this->renderTimeDay(['user' => $eUserBruno['id']]);

		$this->assertTrue(str_ends_with($documentBruno->querySelector('#planning-time-week-total > a.util-days-entry-value')?->getAttribute('href') ?? '', '?period='.TimeWeekLib::WEEK.'&user='.$eUserBruno['id']), 'la somme de la fiche de Bruno ne garde pas Bruno');

		[$documentWeek, $data] = $this->renderTimeDay(['period' => TimeWeekLib::WEEK]);

		$rows = [];

		foreach($documentWeek->querySelectorAll('#planning-time .planning-time-sheet > .planning-time-week + .planning-time-title + #planning-time-week-tasks > .planning-time-row') as $row) {
			$rows[] = $row->querySelector('.planning-time-link')?->getAttribute('href');
		}

		$this->assertEquals([
			TaskDetailUi::url($eTaskDone, $week),
			TaskDetailUi::url($eTaskTodo, $week),
			NULL,
		], $rows, 'le tableau de la semaine ne porte pas les interventions d\'Alice, puis leur somme en pied');

		$this->assertEquals(4.5, $data->timeDay['tasksWeek']['total'], 'la somme du tableau n\'est pas celle de ses lignes');

	}

	/**
	 * Une semaine entièrement passée s'ouvre sur la semaine choisie (R-Retours-1210, FP), pour une personne
	 * comme pour l'équipe ; un jour posté l'emporte, et la semaine en cours s'ouvre sur aujourd'hui. Une
	 * semaine passée sans chiffre montre son lundi, faute de semaine à montrer.
	 */
	public function testPastWeekDefault(): void {

		$this->addBruno();

		$week = toWeek(\Clock::date().' - 7 DAYS');
		$monday = week_date_day($week, 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, $monday);
		$this->createTimesheet($eTask, 2.0, $monday);

		$data = $this->renderTimeDay([], $week)[1];

		$this->assertTrue($data->weekly, 'la semaine passée ne s\'ouvre pas sur la semaine choisie');
		$this->assertEquals($monday, $data->date, 'le jour retenu d\'une semaine passée n\'est pas son lundi');

		$this->assertTrue($this->renderTimeDay(['user' => TimeWeekLib::TEAM], $week)[1]->weekly, 'la semaine passée de l\'équipe ne s\'ouvre pas sur la semaine choisie');
		$this->assertFalse($this->renderTimeDay(['date' => $monday], $week)[1]->weekly, 'un jour posté d\'une semaine passée ouvre la semaine choisie');

		$data = $this->renderTimeDay([], \Clock::week())[1];

		$this->assertFalse($data->weekly, 'la semaine en cours s\'ouvre sur la semaine choisie');
		$this->assertEquals(\Clock::date(), $data->date, 'la semaine en cours ne s\'ouvre pas sur aujourd\'hui');

		$weekEmpty = toWeek(\Clock::date().' - 70 DAYS');
		$data = $this->renderTimeDay([], $weekEmpty)[1];

		if($this->assertDecorPresent(($data->timeDay['total']['working'] ?? 0.0) <= 0.0 and $data->timeDay['total']['time'] <= 0.0, 'une semaine passée sans temps pour la personne connectée') === FALSE) {
			return;
		}

		$this->assertFalse($data->weekly, 'une semaine passée sans chiffre s\'ouvre sur la semaine choisie');
		$this->assertEquals(week_date_day($weekEmpty, 1), $data->date, 'une semaine passée sans chiffre ne montre pas son lundi');

	}

	/**
	 * Les jours d'absence de la personne de la fiche (R-Retours-1210, FH), comme l'ancien planning : le jour
	 * absent le dit, la veille non, un jour à venir aussi, et une absence à cheval sur deux semaines le dit
	 * dans chacune.
	 */
	public function testAbsent(): void {

		[$monday, $tuesday] = array_slice(week_dates(\Clock::week()), 0, 2);
		$friday = week_date_day(\Clock::week(), 5);
		$sunday = week_date_day(\Clock::week(), 7);
		$weekNext = toWeek($sunday.' + 1 DAY');

		$this->addAbsence($this->getMember(), $tuesday, $tuesday);
		$this->addAbsence($this->getMember(), $friday, $friday);
		$this->addAbsence($this->getMember(), $sunday, week_date_day($weekNext, 2));

		$data = $this->renderTimeDay(['date' => $tuesday])[1];

		$this->assertTrue($data->timeDay['day']['absent'], 'le jour de la fiche ne dit pas l\'absence');
		$this->assertTrue($data->timeDay['days'][$tuesday]['absent'], 'mardi, jour d\'absence, ne la dit pas dans la rangée');
		$this->assertFalse($data->timeDay['days'][$monday]['absent'], 'lundi, la veille, se dit absent');
		$this->assertTrue($data->timeDay['days'][$friday]['absent'], 'vendredi, à venir, ne dit pas l\'absence');
		$this->assertTrue($data->timeDay['days'][$sunday]['absent'], 'dimanche, premier jour d\'une absence sur deux semaines, ne la dit pas');

		$data = $this->renderTimeDay([], $weekNext)[1];

		$this->assertTrue($data->timeDay['days'][week_date_day($weekNext, 1)]['absent'], 'le lundi suivant ne dit pas l\'absence commencée la semaine d\'avant');
		$this->assertTrue($data->timeDay['days'][week_date_day($weekNext, 2)]['absent'], 'le mardi suivant, dernier jour de l\'absence, ne la dit pas');
		$this->assertFalse($data->timeDay['days'][week_date_day($weekNext, 3)]['absent'], 'le mercredi suivant, après l\'absence, se dit absent');

	}

	/**
	 * Bruno, au planning limité, ne voit que sa propre fiche : l'onglet d'Alice lui est refusé, une ligne à
	 * faire n'y porte que le rond éteint, et ce qu'il écrit l'est à son nom.
	 */
	public function testLimited(): void {

		$eUserBruno = $this->addBruno();
		$eTaskToday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		\user\ConnectionLib::setOnline($eUserBruno);

		[$document, $data] = $this->renderTimeDay();

		$mark = $document->querySelector('#planning-time-task-'.$eTaskToday['id'].' span.planning-task-mark.planning-task-readonly:not(.planning-task-mark-done)');

		if($this->assertTrue($mark !== NULL, 'la ligne à faire de Bruno ne porte pas le rond éteint')) {
			$this->assertFalse($mark->hasAttribute('data-planning-task-done'), 'le rond éteint de Bruno coche encore');
		}

		$this->assertEquals([$eUserBruno['id']], $data->cUser->getIds(), 'la fiche de Bruno montre d\'autres personnes');
		$this->assertTrue($document->querySelector('#planning-time .planning-time-users') === NULL, 'la fiche de Bruno porte des onglets');

		$action = $this->executePage($this->getTimeDayPath(), ['user' => $this->getMember()['id']], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'Bruno ouvre la fiche d\'Alice');

		[, , $action] = $this->callTimeDay('doWorkingTime', ['date' => \Clock::date(), 'time' => '6']);

		$this->assertTrue($action instanceof \ViewAction, 'la journée de Bruno saisie par lui-même est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(6.0, $this->getWorkingTime($eUserBruno, \Clock::date()), 'la journée de Bruno n\'est pas écrite');

	}

	/**
	 * La journée : écrite au jour de la fiche, vidée par zéro ; refusée au-delà de 24 heures et un jour à
	 * venir, chacun par son code, et hors de la semaine avant toute écriture. Une journée hors des quarts
	 * d'heure se lit dans le champ de son jour, ses heures et ses minutes.
	 */
	public function testWorkingTime(): void {

		$monday = week_date_day(\Clock::week(), 1);

		[$data, $instructions, $action] = $this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7.4']);

		if($this->assertTrue($action instanceof \ViewAction, 'la journée de lundi est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(7.4, $this->getWorkingTime($this->getMember(), $monday), 'la journée de lundi n\'est pas écrite');
		$this->assertTrue(str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE), 'planning-time-day-'.$monday), 'la réponse ne redessine pas la pastille de lundi');
		$this->assertTrue(str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE), 'planning-time-tasks-day'), 'la réponse ne redessine pas les chiffres');

		[$document] = $this->renderTimeDay(['date' => $monday]);

		$this->assertEquals('7.4', $this->getEntrySent($document, $monday), 'la tuile de lundi ne porte pas sa journée de 7h24');

		$this->callTimeDay('doWorkingTime', ['date' => \Clock::date(), 'time' => '8']);

		[$document] = $this->renderTimeDay();
		$this->assertEquals('8', $this->getEntrySent($document, \Clock::date()), 'la tuile du jour ne porte pas sa journée de 8h');

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '0']);
		$this->assertEquals(NULL, $this->getWorkingTime($this->getMember(), $monday), 'zéro ne vide pas la journée');

		$fails = [
			'WorkingTime::time.tooLong' => ['date' => \Clock::date(), 'time' => '25'],
			'WorkingTime::date.check' => ['date' => date('Y-m-d', \Clock::shift('+1 day')), 'time' => '7'],
		];

		foreach($fails as $fail => $input) {

			[, , $action] = $this->callTimeDay('doWorkingTime', $input);

			$this->assertTrue(in_array($fail, $this->getFails($action), TRUE), 'la journée n\'est pas refusée par '.$fail.' : '.implode(', ', $this->getFails($action)));
			$this->assertTrue(\hr\AlertUi::getError($fail) !== NULL, 'le refus '.$fail.' n\'a pas de message');

		}

		[, , $action] = $this->callTimeDay('doWorkingTime', ['date' => date('Y-m-d', strtotime($monday.' - 1 DAY')), 'time' => '7']);
		$this->assertTrue($action instanceof \NotExpectedAction, 'une journée hors de la semaine est acceptée');

		$this->assertEquals(8.0, $this->getWorkingTime($this->getMember(), \Clock::date()), 'une journée refusée est écrite');

	}

	/**
	 * Une ligne du jour et les lignes du reste de la semaine sont sur la fiche ; une ligne à faire dit son jour,
	 * une ligne faite rien (retour 135). Le rond d'une ligne à faire la coche, celui d'une ligne faite la rend
	 * à faire.
	 */
	public function testRows(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$saturday = week_date_day(\Clock::week(), 6);

		$eTaskToday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskTime, 1.5, \Clock::date());
		$eTaskSaturday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $saturday);
		$eTaskMonday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);

		[$document, $data] = $this->renderTimeDay();

		foreach([$eTaskToday, $eTaskTime] as $eTask) {
			$this->assertTrue($this->isInSheet($data->timeDay, $eTask), 'l\'intervention '.$eTask['id'].' n\'est pas sur la fiche');
		}

		foreach([$eTaskSaturday, $eTaskMonday] as $eTask) {
			$this->assertTrue($data->timeDay['ccTaskWeek']->offsetExists($eTask['action']['id']) and $data->timeDay['ccTaskWeek'][$eTask['action']['id']]->offsetExists($eTask['id']), 'l\'intervention '.$eTask['id'].' n\'est pas dans le reste de la semaine');
		}

		$rowToday = '#planning-time-task-'.$eTaskToday['id'];
		$rowTime = '#planning-time-task-'.$eTaskTime['id'];

		$this->assertTrue($document->querySelector($rowToday.' .planning-task-mark:not(.planning-task-mark-done)[data-planning-task-done="'.$eTaskToday['id'].'"]') !== NULL, 'une ligne à faire ne porte pas son rond vide');
		$this->assertTrue($document->querySelector($rowTime.' .planning-task-mark-done[data-planning-task-todo="'.$eTaskTime['id'].'"]') !== NULL, 'une ligne faite ne porte pas son rond coché');

		$this->assertEquals('Samedi', trim($document->querySelector('#planning-time-task-'.$eTaskSaturday['id'].' .planning-time-subtitle')?->textContent ?? ''), 'une ligne posée samedi ne dit pas son jour');
		$this->assertTrue($document->querySelector('#planning-time-task-'.$eTaskMonday['id'].' .planning-time-subtitle') === NULL, 'une ligne faite dit encore quand elle l\'a été');

	}

	/**
	 * Plus de bloc du jour en tête de la liste (R-Retours-0710, 164) : la ligne du jour va dans le groupe de
	 * son action, à sa place dans l'ordre du groupe. Le nom d'une ligne ouvre le panneau de son intervention,
	 * comme sa carte (R-Retours-0710, 163), et la cellule d'une ligne sans nom l'ouvre aussi.
	 */
	public function testNoDayBlock(): void {

		$saturday = week_date_day(\Clock::week(), 6);

		$eTaskSaturday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $saturday);
		$eTaskToday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskBare = $this->createTaskBare($saturday);

		[$document, $data] = $this->renderTimeDay();

		$this->assertFalse(array_key_exists('cTaskDay', $data->timeDay), 'la fiche prépare encore un bloc du jour');

		$action = $eTaskToday['action']['id'];

		if($this->assertTrue($data->timeDay['ccTaskWeek']->offsetExists($action), 'le groupe de l\'action manque') === FALSE) {
			return;
		}

		$this->assertTrue($data->timeDay['ccTaskWeek'][$action]->offsetExists($eTaskToday['id']), 'la ligne du jour n\'est pas dans le groupe de son action');

		$ids = $data->timeDay['ccTaskWeek'][$action]->getIds();
		$this->assertTrue(array_search($eTaskToday['id'], $ids) < array_search($eTaskSaturday['id'], $ids), 'la ligne du jour n\'est pas à sa place de jour dans le groupe');

		$link = $document->querySelector('#planning-time-task-'.$eTaskToday['id'].' .planning-time-label > div:first-child > a.planning-time-link');
		$this->assertEquals(TaskDetailUi::url($eTaskToday, \Clock::week()), $link?->getAttribute('href'), 'le nom de la ligne n\'ouvre pas le panneau de l\'intervention (R-Retours-0710, 163)');

		$empty = $document->querySelector('#planning-time-task-'.$eTaskBare['id'].' .planning-time-label > a.planning-time-link-empty');
		$this->assertEquals(TaskDetailUi::url($eTaskBare, \Clock::week()), $empty?->getAttribute('href'), 'la cellule d\'une ligne sans nom n\'ouvre pas le panneau de l\'intervention (contre-recette de B, réserve 1)');

	}

	/**
	 * Chaque ligne porte le champ par pas (R-Retours-0410, point 22) : -1h, -15, -5, +5, +15, +1h, sur le temps
	 * de la personne ce jour-là, dans une fenêtre que sa durée ouvre, avec son « Enregistrer » (R-Retours-0610,
	 * points 2 et 3). La valeur non nulle porte la teinte du temps passé (point 12), zéro reste neutre. Sans
	 * droit d'écrire ce temps, la ligne n'a ni champ, ni fenêtre, ni crayon.
	 */
	public function testRowsStepped(): void {

		$eTaskTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskTime, 1.25, \Clock::date());
		$eTaskToday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		[$document, $data] = $this->renderTimeDay();

		foreach([[$eTaskTime, '1.25', TRUE], [$eTaskToday, '0', FALSE]] as [$eTask, $sent, $spent]) {

			$row = '#planning-time-task-'.$eTask['id'];

			$key = 'task-'.$eTask['id'].'-'.\Clock::date();
			$stepWindow = $document->querySelector($row.' > .planning-time-time > .planning-duration-step-tile + .planning-duration-step-window');

			if($this->assertTrue($stepWindow !== NULL, 'la ligne '.$eTask['id'].' ne loge pas sa fenêtre après sa durée (R-Retours-0610, points 2 et 3)')) {
				$this->assertTrue($stepWindow->querySelector('.planning-duration-step + .planning-duration-step-foot > [data-planning-duration-step-save]') !== NULL, 'la fenêtre de la ligne '.$eTask['id'].' n\'a pas « Enregistrer » sous son champ');
			}

			$edit = $document->querySelector($row.' > .planning-time-time > .planning-duration-step-tile');

			if($this->assertTrue($edit !== NULL, 'la ligne '.$eTask['id'].' ne porte pas la durée qui ouvre sa fenêtre')) {
				$this->assertEquals($key, $edit->getAttribute('data-planning-duration-step-open'), 'ce qui déplie le champ de la ligne '.$eTask['id'].' ne vise pas sa clé');
			}

			$field = $document->querySelector($row.' .planning-time-time > .planning-duration-step-window > .planning-duration-step');

			if($this->assertTrue($field !== NULL, 'la ligne '.$eTask['id'].' ne porte pas le champ par pas') === FALSE) {
				continue;
			}

			$this->assertEquals('task-'.$eTask['id'].'-'.\Clock::date(), $field->getAttribute('data-duration'), 'le champ de la ligne '.$eTask['id'].' ne porte pas sa clé');
			$this->assertEquals('time', $field->getAttribute('data-wrapper'), 'le refus du temps de la ligne '.$eTask['id'].' ne se rattache pas à son champ');

			$steps = array_map(fn($node) => $node->getAttribute('data-planning-duration-step'), iterator_to_array($field->querySelectorAll('[data-planning-duration-step]')));
			$this->assertEquals(['-60', '-15', '-5', '5', '15', '60'], $steps, 'les pas de la ligne '.$eTask['id'].' ne sont pas -1h, -15, -5, +5, +15, +1h');

			$this->assertEquals($sent, $field->getAttribute('data-sent'), 'le champ de la ligne '.$eTask['id'].' ne retient pas la durée écrite');
			$this->assertEquals($spent, $field->classList->contains('planning-duration-step-spent'), 'la ligne '.$eTask['id'].' '.($spent ? 'ne marque pas son temps passé' : 'marque un temps nul'));

		}

		if($this->assertTrue($this->isInSheet($data->timeDay, $eTaskTime), 'la ligne '.$eTaskTime['id'].' manque à la fiche') === FALSE) {
			return;
		}

		$eTaskLine = TimeDayLib::getLine($data->timeDay, $eTaskTime);
		$html = new TimeDayUi()->getRow(\Clock::week(), \Clock::date(), $eTaskLine, FALSE, FALSE);
		$readOnly = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($readOnly->querySelector('.planning-duration-step') === NULL, 'une ligne sans droit sur le temps porte le champ par pas');
		$this->assertTrue($readOnly->querySelector(':is(.planning-duration-step-window, .planning-duration-step-tile)') === NULL, 'une ligne sans droit sur le temps porte une fenêtre ou un crayon');

	}

	/**
	 * Une ligne qui porte une note et une méthode se nomme par sa note, et ne prend pas sa méthode pour nom.
	 */
	public function testRowNamedByNote(): void {

		$saturday = week_date_day(\Clock::week(), 6);

		$eTaskNoteMethod = $this->createTaskBare($saturday, [$this->createMethod('Témoin marché')['id']], 'Point du lundi');

		[$document] = $this->renderTimeDay();

		$titleNoteMethod = trim($document->querySelector('#planning-time-task-'.$eTaskNoteMethod['id'].' .planning-time-label > div:not(.planning-time-subtitle):not(.planning-task-place)')?->textContent ?? '');

		$this->assertEquals('Point du lundi', $titleNoteMethod, 'une ligne qui porte une note et une méthode ne se nomme plus par sa note');

	}

	/**
	 * Une ligne du reste de la semaine que rien d'autre ne nomme prend sa première méthode pour nom (arbitrage
	 * V-Planning du 02/10, portée du retour 137). La première du tableau est créée après la suivante : elle ne
	 * doit pas son rang à son id.
	 */
	public function testRowNamedByMethod(): void {

		$saturday = week_date_day(\Clock::week(), 6);

		$eMethodSecond = $this->createMethod('Témoin marché');
		$eMethodFirst = $this->createMethod('Témoin AMAP');

		$eTask = $this->createTaskBare($saturday, [$eMethodFirst['id'], $eMethodSecond['id']]);

		[$document] = $this->renderTimeDay();

		$row = '#planning-time-task-'.$eTask['id'];

		$this->assertEquals('Témoin AMAP', trim($document->querySelector($row.' .planning-time-label > div:not(.planning-time-subtitle):not(.planning-task-place)')?->textContent ?? ''), 'une ligne que rien d\'autre ne nomme ne prend pas sa première méthode pour nom');

	}

	/**
	 * Une fiche sans commentaire se dit à deux colonnes, que le script relit ; une ligne commentée ouvre la
	 * troisième, dont le commentaire ouvre l'intervention, ses commentaires suivant la carte.
	 */
	public function testComments(): void {

		$eTaskComment = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		[$document, $data] = $this->renderTimeDay();

		if($this->assertFalse($data->timeDay['commented'], 'le décor porte déjà une ligne commentée cette semaine') === FALSE) {
			return;
		}

		$table = $document->getElementById('planning-time-table');

		$this->assertEquals('2', $table?->getAttribute('data-columns'), 'une fiche sans commentaire ne se dit pas à deux colonnes');

		$this->createComment($eTaskComment, 'Filet posé', '09:00:00');

		[$document, $data] = $this->renderTimeDay();

		$this->assertTrue($data->timeDay['commented'], 'une ligne commentée n\'ouvre pas la colonne');
		$this->assertEquals('3', $document->getElementById('planning-time-table')?->getAttribute('data-columns'), 'une fiche commentée ne se dit pas à trois colonnes');

		$row = '#planning-time-task-'.$eTaskComment['id'];

		$this->assertEquals(TaskDetailUi::url($eTaskComment, \Clock::week()), $document->querySelector($row.' a.planning-time-comment')?->getAttribute('href'), 'le commentaire n\'ouvre pas l\'intervention');

	}

	/**
	 * Une ligne commentée que le masque retire n'ouvre pas la colonne : seules les lignes affichées comptent.
	 * Les commentaires viennent de la lecture de la semaine (`TaskWeekLib::get()`), en une requête pour toute
	 * la fiche, suivie d'une pour leurs auteurs : la semaine et la fiche relues font autant de lectures avec
	 * une ligne commentée qu'avec deux lignes et trois commentaires.
	 */
	public function testCommentsHidden(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $tuesday);
		$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $tuesday);
		$eTaskThird = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$this->setHideDone(TRUE);

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);
		$eUserAlice = $this->getUserSheet($this->getMember());

		$timeDay = TimeDayLib::get($eStructure, \Clock::week(), TaskWeekLib::get($eStructure, \Clock::week()), $tuesday, $eUserAlice, TRUE, FALSE);

		if($this->assertFalse($timeDay['commented'], 'le décor porte déjà une ligne commentée cette semaine') === FALSE) {
			return;
		}

		$this->createComment($eTask, 'Bâche relevée', '09:00:00');

		$cTask = TaskWeekLib::get($eStructure, \Clock::week());
		$timeDay = TimeDayLib::get($eStructure, \Clock::week(), $cTask, $tuesday, $eUserAlice, TRUE, FALSE);

		$this->assertFalse($this->isInSheet($timeDay, $eTask), 'le masque garde la ligne faite sans temps');
		$this->assertFalse($timeDay['commented'], 'une ligne masquée ouvre la colonne des commentaires');

		$timeDay = TimeDayLib::get($eStructure, \Clock::week(), $cTask, $tuesday, $eUserAlice, FALSE, FALSE);

		$this->assertTrue($timeDay['commented'], 'la même ligne, affichée, n\'ouvre pas la colonne');

		$this->createComment($eTaskSecond, 'Arrosé', '09:15:00');

		$selects = $this->countSelects();
		$cTask = TaskWeekLib::get($eStructure, \Clock::week());
		$timeDay = TimeDayLib::get($eStructure, \Clock::week(), $cTask, $tuesday, $eUserAlice, TRUE, FALSE);
		$selectsOne = $this->countSelects() - $selects;

		$this->assertTrue($timeDay['commented'], 'la ligne commentée affichée n\'ouvre pas la colonne');

		$this->createComment($eTaskThird, 'Filet posé', '09:30:00');
		$this->createComment($eTaskThird, 'Filet retendu', '09:45:00');

		$selects = $this->countSelects();
		$cTask = TaskWeekLib::get($eStructure, \Clock::week());
		TimeDayLib::get($eStructure, \Clock::week(), $cTask, $tuesday, $eUserAlice, TRUE, FALSE);
		$selectsThree = $this->countSelects() - $selects;

		$this->assertEquals($selectsOne, $selectsThree, 'chaque ligne commentée coûte une lecture de plus à la fiche');

	}

	/**
	 * « J'ai aussi fait » écrit le temps de la personne de l'onglet : sans droit d'écrire ce temps, il ne
	 * se propose pas, même à qui peut créer une intervention. Le décor n'a pas de planificateur sans la
	 * gestion de la ferme, seul cas où les deux droits divergent : la fiche se rend donc avec ces droits-là.
	 */
	public function testAlsoWithoutTime(): void {

		[, $data] = $this->renderTimeDay();

		$uiTimeDay = new TimeDayUi();

		$with = $uiTimeDay->get($data->eStructure, $data->week, $data->date, $data->cUser, $data->eUserTime, $data->timeDay, $data->entry, $data->eMember, $data->canWorking, TRUE, TRUE, $data->zones);
		$without = $uiTimeDay->get($data->eStructure, $data->week, $data->date, $data->cUser, $data->eUserTime, $data->timeDay, $data->entry, $data->eMember, $data->canWorking, FALSE, TRUE, $data->zones);

		$this->assertTrue(str_contains($with, 'timeDay:create'), 'la fiche où l\'on écrit le temps ne propose pas « J\'ai aussi fait »');
		$this->assertFalse(str_contains($without, 'timeDay:create'), 'la fiche où l\'on ne peut pas écrire le temps propose « J\'ai aussi fait »');

	}

	/**
	 * « J'ai aussi fait » devient le « + Intervention » des Interventions (retour 291), qui ouvre « Nouvelle
	 * intervention » avec Fait coché, pour la personne de l'onglet et le jour ouvert (retour 293).
	 */
	public function testAlsoButton(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		[$document, $data] = $this->renderTimeDay();

		if($this->assertDecorPresent($data->timeDay['ccTaskWeek']->notEmpty(), 'la semaine du décor a une intervention') === FALSE) {
			return;
		}

		$button = $document->querySelector('#planning-time .planning-tools-end > .planning-hide + .planning-tools-actions:last-child > a');

		if($this->assertTrue($button !== NULL, '« + Intervention » n\'est pas en haut à droite, après « Masquer ce qui est fait »') === FALSE) {
			return;
		}

		$this->assertEquals(TaskCreateUi::url($data->eStructure, 'timeDay', $data->week, date: $data->date, status: \series\Task::DONE, eUserTime: $data->eUserTime), $button->getAttribute('href'), '« + Intervention » n\'ouvre pas « Nouvelle intervention », Fait coché, pour la personne de l\'onglet et le jour ouvert');

	}

	/**
	 * La fiche liste toutes les interventions de la semaine (retour 251) : une intervention prévue pour Bruno
	 * est sur la fiche d'Alice, et le temps qu'Alice y saisit va à Alice. « Uniquement assigné à moi » est une
	 * préférence du membre, écrite par son interrupteur comme le masque : allumé, il garde ce qui est prévu
	 * pour la personne de la fiche ou porte de son temps, l'intervention de Bruno sans temps d'Alice et celle
	 * prévue pour personne sortent, et les chiffres du jour ne bougent pas. Sur la fiche de Bruno, le filtre
	 * et le libellé suivent Bruno : le temps d'Alice ne lui retire pas son intervention. Le membre n'écrit que
	 * la sienne (`canSelf`).
	 */
	public function testMine(): void {

		$eUserBruno = $this->addBruno();

		$eTaskBruno = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskBruno['plannedUsers'] = [$eUserBruno['id']];
		// Par le modèle et non par `TaskLib::update()` : le décor veut une intervention prévue pour Bruno seul,
		// sans la synchronisation des personnes d'une mise à jour.
		\series\Task::model()->select(['plannedUsers'])->update($eTaskBruno);

		$eTaskNobody = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$eTaskAlice = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskAlice, 0.5, \Clock::date());

		[$document, $data] = $this->renderTimeDay();

		$switch = $document->querySelector('#planning-time .planning-tools-end > .planning-mine .field-switch');

		if($this->assertTrue($switch !== NULL, 'la fiche ne porte pas « Uniquement assigné à moi » au-dessus de la liste') === FALSE) {
			return;
		}

		$this->assertEquals('Uniquement assigné à moi', $document->querySelector('#planning-time .planning-mine-label')?->textContent, 'sur sa propre fiche, l\'interrupteur ne dit pas « moi »');
		$this->assertEquals('/structure/member:doUpdatePlanningMine', $switch->getAttribute('data-ajax'), 'l\'interrupteur n\'écrit pas la préférence');
		$this->assertEquals('1', $switch->getAttribute('post-view-planning-mine'), 'l\'interrupteur éteint ne propose pas de s\'allumer');
		$this->assertFalse($data->mine, 'l\'interrupteur n\'est pas éteint par défaut');
		$this->assertTrue($this->isInSheet($data->timeDay, $eTaskBruno), 'éteint, la fiche d\'Alice ne porte pas l\'intervention prévue pour Bruno');
		$this->assertTrue($this->isInSheet($data->timeDay, $eTaskNobody), 'éteint, la fiche ne porte pas l\'intervention prévue pour personne');

		[$dataTime, , $action] = $this->callTimeDay('doTime', ['task' => $eTaskBruno['id'], 'date' => \Clock::date(), 'time' => '0.75']);

		$this->assertTrue($action instanceof \ViewAction, 'le temps d\'Alice sur l\'intervention de Bruno est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$this->getMember()['id'] => 0.75], $this->getTimesByUser($eTaskBruno), 'le temps saisi sur la ligne de Bruno ne va pas à Alice seule');
		$this->assertTrue($dataTime !== NULL and $dataTime->eTaskLine->notEmpty(), 'la ligne de Bruno sort de la fiche d\'Alice après la saisie');

		$eTaskBrunoOther = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskBrunoOther['plannedUsers'] = [$eUserBruno['id']];
		// Comme plus haut : une seconde intervention de Bruno, sans temps, que le filtre doit retirer.
		\series\Task::model()->select(['plannedUsers'])->update($eTaskBrunoOther);

		[, $dataOff] = $this->renderTimeDay();

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		$action = $this->executePage('structure/member:doUpdatePlanningMine', ['id' => $eMember['id'], 'viewPlanningMine' => '1'], 'POST');
		$this->assertTrue($action instanceof \ReloadAction, 'le membre n\'écrit pas sa préférence : '.implode(', ', $this->getFails($action)));

		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

		[, $dataOn] = $this->renderTimeDay();

		$this->assertTrue($dataOn->mine, 'la fiche rechargée ne relit pas la préférence');
		$this->assertFalse($this->isInSheet($dataOn->timeDay, $eTaskBrunoOther), 'allumé, la fiche d\'Alice garde l\'intervention prévue pour Bruno');
		$this->assertFalse($this->isInSheet($dataOn->timeDay, $eTaskNobody), 'allumé, la fiche garde l\'intervention prévue pour personne');
		$this->assertTrue($this->isInSheet($dataOn->timeDay, $eTaskBruno), 'allumé, la fiche retire l\'intervention de Bruno où Alice a du temps');
		$this->assertTrue($this->isInSheet($dataOn->timeDay, $eTaskAlice), 'allumé, la fiche retire l\'intervention où Alice a du temps');
		$this->assertEquals($dataOff->timeDay['times'], $dataOn->timeDay['times'], 'le filtre change le temps des jours');
		$actions = fn(array $timeDay): array => array_map(fn(array $action): array => [$action['eAction']['id'], $action['time']], $timeDay['actions']);
		$this->assertEquals($actions($dataOff->timeDay), $actions($dataOn->timeDay), 'le filtre change la barre des interventions');
		$this->assertEquals($dataOff->timeDay['cTaskSpent']->getIds(), $dataOn->timeDay['cTaskSpent']->getIds(), 'le filtre change le détail des interventions');

		// Sur la fiche de Bruno, la préférence reste celle d'Alice, le filtre et le libellé suivent la fiche.
		[$documentBruno, $dataBruno] = $this->renderTimeDay(['user' => $eUserBruno['id']]);

		$this->assertEquals('Uniquement assigné à Bruno', $documentBruno->querySelector('#planning-time .planning-mine-label')?->textContent, 'sur la fiche de Bruno, l\'interrupteur ne nomme pas Bruno');
		$this->assertTrue($this->isInSheet($dataBruno->timeDay, $eTaskBrunoOther), 'allumé, la fiche de Bruno retire son intervention');
		$this->assertTrue($this->isInSheet($dataBruno->timeDay, $eTaskBruno), 'allumé, la fiche de Bruno retire son intervention où Alice a du temps');
		$this->assertFalse($this->isInSheet($dataBruno->timeDay, $eTaskNobody), 'allumé, la fiche de Bruno garde l\'intervention prévue pour personne');
		$this->assertFalse($this->isInSheet($dataBruno->timeDay, $eTaskAlice), 'allumé, la fiche de Bruno garde l\'intervention d\'Alice');

		$eMemberOther = \structure\MemberLib::getByStructure($this->eStructure, index: NULL)->find(fn($eMemberStructure) => $eMemberStructure['user']->is($this->getMember()) === FALSE)->first();

		if($this->assertTrue($eMemberOther !== NULL, 'la structure '.static::STRUCTURE.' n\'a qu\'un membre, le refus d\'écrire pour un autre ne se monte pas') === FALSE) {
			return;
		}

		$mineOther = \structure\Member::model()->select('viewPlanningMine')->whereId($eMemberOther['id'])->getValue('viewPlanningMine');

		$action = $this->executePage('structure/member:doUpdatePlanningMine', ['id' => $eMemberOther['id'], 'viewPlanningMine' => $mineOther ? '0' : '1'], 'POST');

		$this->assertTrue(($action instanceof \ReloadAction) === FALSE, 'un membre écrit la préférence d\'un autre');
		$this->assertEquals($mineOther, \structure\Member::model()->select('viewPlanningMine')->whereId($eMemberOther['id'])->getValue('viewPlanningMine'), 'la préférence d\'un autre membre a changé en base');

	}

	/**
	 * Le masque retire toute ligne faite, qu'elle porte du temps de la personne ce jour-là ou non (retour 287).
	 * Il ne touche que la liste : la journée, sa barre et son détail comptent toujours ce temps, comme sans
	 * le masque.
	 */
	public function testHide(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		$eTaskEmpty = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $tuesday);
		$eTaskTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $tuesday);
		$this->createTimesheet($eTaskTime, 0.5, $tuesday);

		$this->setHideDone(FALSE);

		[, $dataShown] = $this->renderTimeDay(['date' => $tuesday]);

		$this->assertTrue($this->isInSheet($dataShown->timeDay, $eTaskEmpty), 'sans le masque, la fiche n\'a pas la ligne faite sans temps');
		$this->assertTrue($this->isInSheet($dataShown->timeDay, $eTaskTime), 'sans le masque, la fiche n\'a pas la ligne faite qui porte du temps');

		$this->setHideDone(TRUE);

		[, $data] = $this->renderTimeDay(['date' => $tuesday]);

		$this->assertFalse($this->isInSheet($data->timeDay, $eTaskEmpty), 'le masque garde une ligne faite sans temps');
		$this->assertFalse($this->isInSheet($data->timeDay, $eTaskTime), 'le masque garde une ligne faite qui porte du temps ce jour-là');
		$this->assertTrue($data->timeDay['hidden'], 'la fiche ne sait pas que le masque a retiré des lignes');

		$this->assertTrue($data->timeDay['cTaskSpent']->offsetExists($eTaskTime['id']), 'le temps de la ligne masquée quitte le détail de la journée');
		$this->assertEquals($dataShown->timeDay['day'], $data->timeDay['day'], 'le masque change la journée');
		$this->assertEquals($dataShown->timeDay['days'], $data->timeDay['days'], 'le masque change la rangée des jours');
		$this->assertEquals($this->getActionTimes($dataShown->timeDay), $this->getActionTimes($data->timeDay), 'le masque change la barre des interventions');

	}

	/**
	 * Le temps d'une ligne du jour redessine la ligne seule ; la coche d'une ligne sans jour la fait entrer
	 * dans le jour au rechargement, mais la ligne garde à l'écran sa place dans le reste de la semaine
	 * (R-Place). Un temps un jour à venir est refusé.
	 */
	public function testChange(): void {

		$eTaskToday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		[$data, $instructions, $action] = $this->callTimeDay('doTime', ['task' => $eTaskToday['id'], 'date' => \Clock::date(), 'time' => '0.5']);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps d\'une ligne est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('row', $data->change, 'le temps d\'une ligne du jour redessine plus que la ligne');
		$this->assertTrue(\series\TaskLib::getById($eTaskToday['id'])->isTodo(), 'le temps seul change l\'état de l\'intervention (Q1)');
		$this->assertTrue(str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE), 'planning-time-task-'.$eTaskToday['id']), 'la réponse ne redessine pas la ligne');

		$eTaskNone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$data, , $action] = $this->callTimeDay('doDone', ['task' => $eTaskNone['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'la coche d\'une ligne sans jour est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals('row', $data->change, 'la coche d\'une ligne sans jour redessine plus que la ligne');
		$this->assertEquals(\Clock::date(), \series\TaskLib::getById($eTaskNone['id'])['doneDate'], 'la ligne sans jour cochée ne prend pas aujourd\'hui (Q6)');
		$this->assertEquals(NULL, $data->eTaskLine['dayWeek'], 'la ligne cochée quitte sa place à l\'écran');
		$this->assertEquals(\Clock::date(), TimeDayLib::getLine($this->renderTimeDay()[1]->timeDay, $eTaskNone)['dayWeek'], 'la ligne cochée ne prend pas son jour au rechargement');

		[, , $action] = $this->callTimeDay('doTodo', ['task' => $eTaskNone['id']]);
		$this->assertTrue($action instanceof \ViewAction and \series\TaskLib::getById($eTaskNone['id'])->isTodo(), 'le tampon ne rend pas la ligne à faire');

		[, , $action] = $this->callTimeDay('doTime', ['task' => $eTaskToday['id'], 'date' => week_date_day(\Clock::week(), 5), 'time' => '1']);
		$this->assertTrue(in_array('Timesheet::date.future', $this->getFails($action), TRUE), 'un temps un jour à venir n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callTimeDay('doTime', ['task' => $eTaskToday['id'], 'date' => \Clock::date(), 'time' => '25']);
		$this->assertTrue($action instanceof \FailAction, 'un temps de plus de 24 heures est accepté');

	}

	/**
	 * Une absence de `$from` à `$to` inclus, dans la transaction du décor.
	 */
	private function addAbsence(\user\User $eUser, string $from, string $to): void {

		$this->enableHr();

		// Sans `build()`, qui vérifierait les dates d'un formulaire : le décor veut exactement ces jours.
		\hr\AbsenceLib::create(new \hr\Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from.' 00:00:00',
			'to' => $to.' 23:59:59',
			'duration' => 1.0,
			'type' => \hr\Absence::VACATION,
		]));

	}

	/**
	 * Une personne telle que la fiche la lit (`TimeDayLib::getUsers()`), avec ses absences, que
	 * `TimeDayLib::get()` attend.
	 */
	private function getUserSheet(\user\User $eUser): \user\User {

		return TimeDayLib::getUsers($this->eStructure, \Clock::week())[$eUser['id']];

	}

	private function countSelects(): int {

		return (int)\series\Task::model()->pdo()->query('SHOW SESSION STATUS LIKE "Com_select"')->fetch()['Value'];

	}

	/**
	 * Bruno présent depuis le début de la saison, dans la transaction du décor.
	 */
	private function addBruno(): \user\User {

		$eUserBruno = $this->getMemberPlanning();

		$this->addPresence($eUserBruno);

		return $eUserBruno;

	}

	/**
	 * La journée saisie sur une ligne de l'équipe (`origin=team`), rendue par sa vue (`doWorkingTimeTeam`) :
	 * ses données et ses instructions, ou l'action levée à la place d'une vue.
	 */
	private function callTimeDayTeam(array $input): array {

		$action = $this->executePage('planning/timeDay:doWorkingTime', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'origin' => TimeWeekLib::TEAM,
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doWorkingTimeTeam', $action, 'timeDay');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * La journée enregistrée que porte la tuile d'un jour de la semaine en champs (`data-sent`, que le sélecteur
	 * compare à la durée choisie avant de poster), NULL sans tuile.
	 */
	private function getEntrySent(\Dom\HTMLDocument $document, string $date): ?string {

		return $document->querySelector('#planning-time-day-'.$date.' .planning-time-working[data-date]')?->getAttribute('data-sent');

	}

	private function getWorkingTime(\user\User $eUser, string $date): ?float {

		$eWorkingTime = \hr\WorkingTime::model()
			->select(['time'])
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->whereDate($date)
			->get();

		return $eWorkingTime->empty() ? NULL : $eWorkingTime['time'];

	}

	private function getTimesByUser(\series\Task $eTask): array {

		$times = [];

		foreach(\series\Timesheet::model()->select(['user', 'time'])->whereTask($eTask)->getCollection() as $eTimesheet) {
			$times[$eTimesheet['user']['id']] = ($times[$eTimesheet['user']['id']] ?? 0.0) + $eTimesheet['time'];
		}

		return $times;

	}


	/**
	 * Une intervention sans série, sans espèce et sans note, posée un jour de la semaine : rien ne la nomme
	 * que son action.
	 */
	private function createMethod(string $name): \work\Method {

		$eMethod = new \work\Method([
			'structure' => $this->eStructure,
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'name' => $name,
		]);

		// Sans `build()`, qui vérifierait le nom et l'action saisis : le décor fixe les deux, et l'action est
		// celle des interventions de `createTaskBare()`, que la méthode doit pouvoir préciser.
		\work\MethodLib::create($eMethod);

		// `askCollection()` lit les méthodes dans un cache du processus, qu'une méthode créée après ne rejoint pas.
		new \ReflectionProperty(\work\MethodCrud::class, 'cache')->setValue(NULL, []);

		return $eMethod;

	}

	private function createTaskFlow(string $actionFqn, string $status, array $values): \series\Task {

		$eCultivation = $this->getCultivation();
		[$week, $date] = match($status) {
			\series\Task::DONE => ['doneWeek', 'doneDate'],
			\series\Task::TODO => ['plannedWeek', 'plannedDate'],
		};

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction($actionFqn),
			'category' => $this->getCategoryCulture(),
			'status' => $status,
			$week => \Clock::week(),
			$date => \Clock::date(),
			'repeatMaster' => new \series\Repeat(),
		] + $values);

		// Sans `build()`, qui vérifierait l'action, la semaine, l'unité et la fumure postées depuis le
		// formulaire d'intervention : le décor fixe ces valeurs, qui sont ce que la ligne doit afficher.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	private function createTaskBare(string $date, array $methods = [], ?string $description = NULL, \plant\Plant $ePlant = new \plant\Plant()): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => $ePlant,
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => $date,
			'repeatMaster' => new \series\Repeat(),
			'methods' => $methods,
			'description' => $description,
		]);

		// Sans `build()`, qui vérifierait l'action et la semaine saisies : le décor veut l'intervention que
		// le formulaire ne produit pas, sans série pour la nommer, puisque c'est le cas éprouvé (rien du
		// tout, une méthode, une note, les deux, ou une espèce seule).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * Le nom de la série de la culture du décor, que le détail du jour écrit sans rien après.
	 */
	private function getSeriesName(): string {
		return \series\Series::model()->select(['name'])->whereId($this->getCultivation()['series']['id'])->get()['name'];
	}

	/**
	 * Chaque ligne d'un détail du jour, son nom puis son temps.
	 */
	private function getTasksSpentTexts(?\Dom\Element $node): array {

		$texts = [];

		foreach($node?->querySelectorAll('.planning-time-legend > .util-legend-item:not(.planning-time-legend-total)') ?? [] as $item) {
			$texts[] = trim($item->querySelector('.action-circle + span')?->textContent ?? '').' '.trim($item->querySelector('b')?->textContent ?? '');
		}

		return $texts;

	}

	/**
	 * Les largeurs des parts de la barre des interventions de la jauge, dans l'ordre (R2).
	 */
	private function getTaskWidths(\Dom\HTMLDocument $document): array {

		$widths = [];

		foreach($document->querySelectorAll('#planning-time-tasks-day .planning-time-tasks > .util-progress-part') as $part) {
			$widths[] = preg_replace('/^.*(width: [0-9.]+%).*$/', '$1', $part->getAttribute('style'));
		}

		return $widths;

	}

	/**
	 * La barre des interventions d'une fiche, en temps par action : ce qu'elle dessine.
	 */
	private function getActionTimes(array $timeDay): array {

		return array_map(fn(array $action) => [$action['eAction']['id'], $action['time']], $timeDay['actions']);

	}

}
?>
