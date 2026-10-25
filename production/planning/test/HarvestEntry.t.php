<?php
namespace planning;

/**
 * La saisie d'une récolte depuis l'écran des récoltes : ce que `doHarvest` écrit, par
 * `\series\TaskLib::writeHarvestForWeek()`, et ce qu'il refuse. Chaque cas passe par la vraie route,
 * avec ses `build()` et ses validations, dans la transaction du décor (`HarvestFixtureTest`).
 *
 * Le jour du décor est le jeudi de la semaine 39 : lundi est un jour passé de la semaine, vendredi
 * un jour à venir.
 *
 * Lancement : dev/test package=planning file=HarvestEntry
 */
class HarvestEntryTest extends HarvestFixtureTest {

	/**
	 * L'intervention à faire de la semaine reçoit la récolte et se termine ; aucune autre ne naît, et
	 * le temps va au membre connecté.
	 */
	public function testCompletesTodoTask(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3', 'time' => $this->getTimeInput('0.5')]);

		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTask['id']], $cTask->getIds(), 'la saisie crée une intervention au lieu de compléter celle de la semaine');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTask['id']]['status'], 'l\'intervention de la semaine reste à faire');
		$this->assertEquals(3.0, $cTask[$eTask['id']]['harvest'], 'l\'intervention ne porte pas la récolte');
		$this->assertEquals([\Clock::date() => [\series\Cultivation::KG => 3.0]], $this->getHarvested($eCultivation), 'la récolte n\'est pas datée d\'aujourd\'hui dans l\'unité de la culture');

		$cTimesheet = \series\Timesheet::model()
			->select(['user', 'task', 'time', 'date'])
			->whereCultivation($eCultivation)
			->getCollection();

		$this->assertEquals(1, $cTimesheet->count(), 'le temps n\'est pas écrit une fois');
		$this->assertEquals($this->getMember()['id'], $cTimesheet->first()['user']['id'], 'le temps ne va pas au membre connecté');
		$this->assertEquals($eTask['id'], $cTimesheet->first()['task']['id'], 'le temps ne va pas à l\'intervention de la semaine');
		$this->assertEquals(0.5, (float)$cTimesheet->first()['time'], 'le temps écrit n\'est pas la durée choisie');

	}

	/**
	 * Sans intervention cette semaine, la saisie en crée une, faite au jour saisi, dans l'unité de la
	 * culture. L'intervention naît à faire, planifiée ce jour-là, et la quantité la termine : un jour
	 * passé de la semaine prouve que la date de fin est le jour planifié, et non aujourd'hui.
	 */
	public function testCreatesDoneTask(): void {

		$eCultivation = $this->createCultivation();

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2.5']);

		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		if($this->assertEquals(1, $cTask->count(), 'la saisie ne crée pas une intervention') === FALSE) {
			return;
		}

		$eTask = $cTask->first();

		$this->assertEquals(\series\Task::DONE, $eTask['status'], 'l\'intervention créée n\'est pas faite');
		$this->assertEquals(\Clock::date(), $eTask['doneDate'], 'l\'intervention créée n\'est pas datée d\'aujourd\'hui');
		$this->assertEquals(\Clock::week(), $eTask['doneWeek'], 'l\'intervention créée n\'est pas de la semaine');
		$this->assertEquals(\series\Cultivation::KG, $eTask['harvestUnit'], 'l\'intervention créée n\'est pas dans l\'unité de la culture');
		$this->assertEquals([\Clock::date() => [\series\Cultivation::KG => 2.5]], $this->getHarvested($eCultivation), 'la récolte n\'est pas écrite');

		$this->assertEquals(0, \series\Timesheet::model()->whereCultivation($eCultivation)->count(), 'une saisie sans durée écrit un temps');

		$monday = week_date_day(\Clock::week(), 1);
		$eCultivationMonday = $this->createCultivation($this->createSeries('Série du lundi'));

		$action = $this->post($eCultivationMonday, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '1']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du lundi est refusée : '.implode(', ', $this->getFails($action)));

		$cTaskMonday = $this->getTasks($eCultivationMonday);

		if($this->assertEquals(1, $cTaskMonday->count(), 'la saisie du lundi ne crée pas une intervention') === FALSE) {
			return;
		}

		$eTaskMonday = $cTaskMonday->first();

		$this->assertEquals(\series\Task::DONE, $eTaskMonday['status'], 'l\'intervention créée le lundi n\'est pas faite');
		$this->assertEquals($monday, $eTaskMonday['doneDate'], 'l\'intervention créée le lundi n\'est pas datée du lundi');
		$this->assertEquals(\Clock::week(), $eTaskMonday['doneWeek'], 'l\'intervention créée le lundi n\'est pas de la semaine');

	}

	/**
	 * Remplacer la quantité d'un jour écrit la différence : le jour vaut ensuite la quantité saisie,
	 * en hausse comme en baisse, et les autres jours ne bougent pas.
	 */
	public function testReplace(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), $monday);
		$this->createHarvest($eTask, 2.0, $monday);
		$this->createHarvest($eTask, 1.0, $tuesday);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '5']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en hausse est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$monday => ['kg' => 5.0], $tuesday => ['kg' => 1.0]], $this->getHarvested($eCultivation), 'lundi ne vaut pas la quantité saisie en hausse');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '0.5']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en baisse est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$monday => ['kg' => 0.5], $tuesday => ['kg' => 1.0]], $this->getHarvested($eCultivation), 'lundi ne vaut pas la quantité saisie en baisse');
		$this->assertEquals([$eTask['id']], $this->getTasks($eCultivation)->getIds(), 'le remplacement crée une intervention');
		$this->assertEquals(1.5, $this->getTasks($eCultivation)->first()['harvest'], 'le total de l\'intervention ne suit pas');

		// Un jour remis à zéro redevient un jour sans récolte à l'écran, et non « 0 kg ».
		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $tuesday, 'harvestDay' => '0']);
		$this->assertTrue($action instanceof \ViewAction, 'la remise à zéro est refusée : '.implode(', ', $this->getFails($action)));

		$harvestedDays = HarvestWeekLib::get($this->eStructure, \Clock::week())[$eCultivation['id']]['harvestedDays'];
		$this->assertEquals([$monday => ['kg' => 0.5]], $harvestedDays, 'un jour remis à zéro reste affiché');

	}

	/**
	 * Une case du jour, qui ne porte aucune marque « terminée », termine l'intervention à faire de la semaine
	 * dès sa première quantité, comme la maquette. Faite, l'intervention continue d'accepter les jours
	 * suivants de la même semaine : « faite » dit qu'on a récolté, pas qu'on ne récoltera plus
	 * (arbitrage du 2026-09-30, « L3a, comportement »).
	 */
	public function testDayDoneThenNextDay(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du lundi est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(\series\Task::DONE, $this->getTasks($eCultivation)[$eTask['id']]['status'], 'la saisie du lundi ne termine pas l\'intervention de la semaine');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $tuesday, 'harvestDay' => '3']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du mardi sur l\'intervention faite est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTask['id']], $cTask->getIds(), 'la saisie du mardi crée une seconde intervention au lieu de compléter la faite');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTask['id']]['status'], 'la saisie du mardi rouvre l\'intervention');
		$this->assertEquals(5.0, $cTask[$eTask['id']]['harvest'], 'l\'intervention ne porte pas les deux jours');
		$this->assertEquals([$monday => ['kg' => 2.0], $tuesday => ['kg' => 3.0]], $this->getHarvested($eCultivation), 'les deux jours ne sont pas écrits');

	}

	/**
	 * Une intervention en unités ne reçoit que la saisie qui nomme son unité (`harvestUnit`, retour 319), et
	 * le remplacement ne compte alors que le récolté de cette unité. La saisie qui ne nomme rien reste dans
	 * l'unité de la culture, que la semaine porte ou non une récolte en unités. `buildHarvests()` refuse de
	 * mêler deux unités sur une intervention (`Task::tasks.unit`) ; la saisie ne lui en présente jamais une autre.
	 */
	public function testUnitOfTask(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);

		// Un kilo lundi, sur une intervention de la semaine passée : il compte au jour, pas à l'unité.
		$eTaskKg = $this->createTaskHarvest($eCultivation, toWeek(date('Y-m-d', strtotime($monday.' -7 days'))), date('Y-m-d', strtotime($monday.' -7 days')));
		$this->createHarvest($eTaskKg, 1.0, $monday);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $monday);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '12', 'harvestUnit' => \series\Task::UNIT]);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie en unités est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$monday => ['kg' => 1.0, 'unit' => 12.0]], $this->getHarvested($eCultivation), 'la saisie qui nomme les unités ne va pas dans l\'intervention en unités de la semaine, ou touche les kilos');
		$this->assertEquals(12.0, $this->getTasks($eCultivation)[$eTaskUnit['id']]['harvest'], 'l\'intervention en unités ne porte pas la récolte');

		// Le lundi ne porte en kilos que ceux d'une intervention d'une autre semaine, et la semaine n'a pas
		// d'intervention en kilos : « Supprimer » du tiroir se refuse par la chaîne d'erreur, sans rien écrire.
		[, $action] = $this->delete($eCultivation, ['date' => $monday]);

		$this->assertEquals(['Task::harvestDates.negative'], $this->getFails($action), 'la suppression des kilos d\'une intervention d\'une autre semaine n\'est pas refusée par son message');
		$this->assertEquals([$monday => ['kg' => 1.0, 'unit' => 12.0]], $this->getHarvested($eCultivation), 'la suppression refusée a écrit');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie sans unité est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$monday => ['kg' => 3.0, 'unit' => 12.0]], $this->getHarvested($eCultivation), 'la saisie qui ne nomme pas d\'unité ne reste pas dans celle de la culture');
		$this->assertEquals(12.0, $this->getTasks($eCultivation)[$eTaskUnit['id']]['harvest'], 'la saisie en kilos écrit sur l\'intervention en unités');

	}

	/**
	 * « Nouvelle récolte » dans une autre unité que celle de la culture (retour 319) : la première saisie crée
	 * l'intervention de l'unité, la suivante la retrouve, et la saisie qui ne nomme rien, ou qui nomme l'unité
	 * de la culture, reste en kilos sur sa propre intervention.
	 */
	public function testOtherUnit(): void {

		$eCultivation = $this->createCultivation();

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '5', 'crate' => '0', 'panel' => 1, 'harvestUnit' => \series\Task::UNIT]);

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte en pièces d\'une culture en kilos est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([[\series\Task::UNIT, 5.0, \series\Task::DONE]], array_values($cTask->makeArray(fn($eTask) => [$eTask['harvestUnit'], $eTask['harvest'], $eTask['status']])), 'la première récolte en pièces ne crée pas une intervention faite, en pièces');
		$this->assertEquals([\Clock::date() => ['unit' => 5.0]], $this->getHarvested($eCultivation), 'la récolte en pièces ne s\'écrit pas en pièces, sans conversion');

		$eTaskUnit = $cTask->first();

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3', 'crate' => '0', 'panel' => 1, 'harvestUnit' => \series\Task::UNIT]);
		$this->assertTrue($action instanceof \ViewAction, 'la seconde récolte en pièces est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTaskUnit['id']], $cTask->getIds(), 'la seconde récolte en pièces crée une intervention au lieu de retrouver celle de l\'unité');
		$this->assertEquals(8.0, $cTask[$eTaskUnit['id']]['harvest'], 'l\'intervention en pièces ne porte pas les deux saisies');

		foreach([
			'sans unité' => [],
			'dans l\'unité de la culture' => ['harvestUnit' => \series\Task::KG],
		] as $case => $input) {

			$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'crate' => '0', 'panel' => 1] + $input);
			$this->assertTrue($action instanceof \ViewAction, $case.' : la récolte est refusée : '.implode(', ', $this->getFails($action)));

		}

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(2, $cTask->count(), 'les deux saisies en kilos ne partagent pas une seule intervention, à côté de celle en pièces');
		$this->assertEquals(8.0, $cTask[$eTaskUnit['id']]['harvest'], 'une saisie en kilos écrit sur l\'intervention en pièces');
		$this->assertEquals([\Clock::date() => ['unit' => 8.0, 'kg' => 4.0]], $this->getHarvested($eCultivation), 'les saisies en kilos ne restent pas en kilos');

	}

	/**
	 * Une intervention de la semaine qui n'a encore rien récolté n'a pas d'unité : la récolte dans une autre
	 * unité la prend au lieu d'en créer une seconde, et lui donne la sienne.
	 */
	public function testOtherUnitTakesTaskWithoutUnit(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '4', 'crate' => '0', 'panel' => 1, 'harvestUnit' => \series\Task::BUNCH]);
		$this->assertTrue($action instanceof \ViewAction, 'la récolte en bottes est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTask['id']], $cTask->getIds(), 'la récolte en bottes crée une intervention à côté de celle à faire de la semaine');
		$this->assertEquals([\series\Task::BUNCH, 4.0], [$cTask[$eTask['id']]['harvestUnit'], $cTask[$eTask['id']]['harvest']], 'l\'intervention à faire ne prend pas l\'unité et la quantité de la saisie');
		$this->assertEquals([\Clock::date() => ['bunch' => 4.0]], $this->getHarvested($eCultivation), 'la récolte en bottes ne s\'écrit pas en bottes');

	}

	/**
	 * La semaine d'une culture reste dans son unité (retour 319, lecture stricte) : une récolte en pièces,
	 * même seule dans la semaine, n'en devient pas l'intervention. La grille annonce des kilos, la case du jour
	 * en pièces désigne son intervention, et la saisie d'un jour vide crée une intervention en kilos, sans
	 * aller chercher une récolte à faire en retard, que la semaine déjà récoltée ne rattrape plus.
	 */
	public function testOtherUnitNeverTheWeek(): void {

		// Un jeudi, pour que le lundi et le mardi du décor ne soient jamais des jours à venir.
		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eTaskLate = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(date('Y-m-d', strtotime($monday.' - 7 days'))));

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $monday);
		$this->createHarvest($eTaskUnit, 10.0, $monday);

		[$document, $data] = $this->render();

		if($this->assertDecorPresent($data->cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée en pièces est dans la liste de la semaine') === FALSE) {
			return;
		}

		$this->assertEquals(\series\Task::KG, $data->cCultivation[$eCultivation['id']]['harvestUnitWeek'], 'la semaine d\'une culture en kilos passe en pièces sur une seule récolte en pièces');
		$this->assertEquals((string)$eTaskUnit['id'], $this->getOpenTask($document, $eCultivation, $monday, 'replace'), 'la case du jour en pièces ne désigne pas l\'intervention en pièces');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $tuesday, 'harvestDay' => '6']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du jour vide est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$monday => ['unit' => 10.0], $tuesday => ['kg' => 6.0]], $this->getHarvested($eCultivation), 'la saisie du jour vide ne s\'écrit pas en kilos, ou touche aux pièces');
		$this->assertEquals(3, $cTask->count(), 'la saisie du jour vide ne crée pas son intervention en kilos');
		$this->assertEquals([\series\Task::TODO, NULL], [$cTask[$eTaskLate['id']]['status'], $cTask[$eTaskLate['id']]['harvest']], 'la saisie du jour vide va dans la récolte à faire en retard');
		$this->assertEquals(10.0, $cTask[$eTaskUnit['id']]['harvest'], 'la saisie du jour vide écrit sur l\'intervention en pièces');

		// Le jour en pièces se corrige et se vide par sa case, qui désigne son intervention.
		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '7', 'task' => $eTaskUnit['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement du jour en pièces est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(['unit' => 7.0], $this->getHarvested($eCultivation)[$monday] ?? NULL, 'le remplacement du jour en pièces n\'écrit pas 7 pièces');

		[, $action] = $this->delete($eCultivation, ['date' => $monday, 'task' => $eTaskUnit['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'la suppression du jour en pièces est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$tuesday => ['kg' => 6.0]], array_filter($this->getHarvested($eCultivation)), 'la suppression du jour en pièces laisse des pièces, ou touche aux kilos');

	}

	/**
	 * Ce qu'une autre unité ne prend pas : une intervention désignée, qui a la sienne, des caisses, une unité
	 * que le schéma ne connaît pas, et une intervention neuve sans quantité, que rien ne montrerait. Rien ne
	 * s'écrit.
	 */
	public function testOtherUnitRefused(): void {

		[$eCultivation] = $this->createCultivationPart();

		$eTaskBunch = $this->createTaskHarvestInUnit($eCultivation, \series\Task::BUNCH, \Clock::date());

		$harvested = $this->getHarvested($eCultivation);
		$count = $this->getTasks($eCultivation)->count();

		foreach([
			'avec une intervention désignée' => [['harvestMore' => '2', 'harvestUnit' => \series\Task::UNIT, 'task' => $eTaskBunch['id']], 'Task::harvestUnit.couple'],
			'en caisses' => [['harvestMore' => '2', 'harvestUnit' => \series\Task::UNIT, 'crate' => 1, 'crateWeight' => '6'], 'Task::harvestUnit.crate'],
			'inconnue' => [['harvestMore' => '2', 'harvestUnit' => 'tonne'], 'Task::harvestUnit.check'],
			'sans quantité, avec du temps' => [['harvestMore' => '', 'harvestUnit' => \series\Task::UNIT, 'time' => $this->getTimeInput('0.5')], 'Task::harvestUnit.quantity'],
		] as $case => [$input, $fail]) {

			$action = $this->post($eCultivation, $input + ['mode' => 'add', 'panel' => 1]);

			$this->assertEquals([$fail], $this->getFails($action), 'une autre unité '.$case.' n\'est pas refusée par '.$fail);
			$this->assertDiffer(NULL, \series\AlertUi::getError($fail), 'le refus d\'une autre unité '.$case.' n\'a pas de message');

		}

		$this->assertEquals($harvested, $this->getHarvested($eCultivation), 'une saisie refusée a écrit une récolte');
		$this->assertEquals($count, $this->getTasks($eCultivation)->count(), 'une saisie refusée a créé une intervention');
		$this->assertEquals([], $this->getTimeByTask($eCultivation), 'une saisie refusée a écrit du temps');

	}

	/**
	 * L'unité de la saisie, postée par le panneau, ne vaut pas demande d'une autre unité : un temps seul, sans
	 * quantité, crée l'intervention de la semaine comme si aucune unité n'était postée, au lieu d'être refusé
	 * comme une autre unité qui ne porterait rien (`harvestUnit.quantity`).
	 */
	public function testEntryUnitTimeOnly(): void {

		foreach([
			'avec l\'unité de la saisie' => ['harvestUnit' => \series\Task::KG],
			'sans unité' => [],
		] as $case => $input) {

			$eCultivation = $this->createCultivation($this->createSeries('Série '.$case));

			$action = $this->post($eCultivation, $input + ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('0.5'), 'panel' => 1]);

			$this->assertTrue($action instanceof \ViewAction, 'un temps seul '.$case.' est refusé : '.implode(', ', $this->getFails($action)));

			$cTask = $this->getTasks($eCultivation);

			if($this->assertEquals(1, $cTask->count(), 'un temps seul '.$case.' ne crée pas une intervention') === FALSE) {
				continue;
			}

			$eTask = $cTask->first();

			$this->assertEquals([\series\Task::TODO, \series\Task::KG], [$eTask['status'], $eTask['harvestUnit']], 'l\'intervention d\'un temps seul '.$case.' n\'est pas à faire en kilos');
			$this->assertEquals([$eTask['id'] => 0.5], $this->getTimeByTask($eCultivation), 'l\'intervention d\'un temps seul '.$case.' ne porte pas le temps');
			$this->assertEquals([], $this->getHarvested($eCultivation), 'un temps seul '.$case.' écrit une récolte');

		}

	}

	/**
	 * Le panneau d'une culture à variétés poste ses listes même laissées sur « Toutes » et « Non spécifié » :
	 * rien n'est choisi, et l'autre unité s'écrit pour la culture entière.
	 */
	public function testOtherUnitWithCoupleUnchosen(): void {

		[$eCultivation] = $this->createCultivationPart();

		$harvested = $this->getHarvested($eCultivation);

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '5', 'crate' => '0', 'panel' => 1, 'harvestUnit' => \series\Task::UNIT, 'variety' => '', 'harvestSize' => '']);
		$this->assertTrue($action instanceof \ViewAction, 'une autre unité sans variété ni calibre choisis est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => $harvested[\Clock::date()] + ['unit' => 5.0]], $this->getHarvested($eCultivation), 'les pièces ne s\'écrivent pas à côté des kilos du jour');

	}

	/**
	 * Une variété se récolte aussi dans une autre unité (HN-B2b, 21/10, « Autoriser ») : la saisie
	 * construit puis retrouve l'intervention de la variété dans cette unité, jamais celle de la culture entière.
	 * La variété garde l'unité principale de la culture si elle y a une intervention dans la semaine, sinon celle
	 * de sa première : ses pièces s'ajoutent à sa ligne sans la faire passer en pièces, et des kilos saisis
	 * ensuite restent dans son unité, même saisis après des pièces (M-Planning, 21/10).
	 */
	public function testOtherUnitCouple(): void {

		[$eCultivation, $eVariety] = $this->createCultivationPart();

		$today = \Clock::date();
		$eSize = new \plant\Size();
		$key = \series\CultivationLib::getHarvestPartKey($eVariety, $eSize);

		$eTaskKg = \series\Task::model()
			->select(['id'])
			->whereCultivation($eCultivation)
			->whereVariety($eVariety)
			->get();

		$eTaskWholeUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->createHarvest($eTaskWholeUnit, 10.0, $today);

		$this->assertEquals([\series\Task::UNIT, NULL], [
			\planning\HarvestWeekLib::getOtherUnit($this->eStructure, $eCultivation, $today, \series\Task::UNIT, $eVariety, $eSize),
			\planning\HarvestWeekLib::getOtherUnit($this->eStructure, $eCultivation, $today, \series\Task::KG, $eVariety, $eSize),
		], 'les pièces ne sont pas une autre unité pour la variété en kilos, ou les kilos le sont');

		$eTaskBuilt = \planning\HarvestWeekLib::getTaskForEntry($this->eStructure, $eCultivation, $today, \series\Task::UNIT, $eVariety, $eSize);

		$this->assertEquals([FALSE, \series\Task::UNIT, $eVariety['id']], [$eTaskBuilt->offsetExists('id'), $eTaskBuilt['harvestUnit'], $eTaskBuilt['variety']['id']], 'la variété en pièces ne construit pas une intervention neuve de la variété, en pièces, et prend celle de la culture entière en pièces');

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->ventilate($eTaskUnit, ['variety' => $eVariety]);
		$this->createHarvest($eTaskUnit, 40.0, $today);

		$this->assertEquals($eTaskUnit['id'], \planning\HarvestWeekLib::getTaskForEntry($this->eStructure, $eCultivation, $today, \series\Task::UNIT, $eVariety, $eSize)['id'], 'la variété en pièces ne retrouve pas son intervention en pièces');

		$this->assertEquals($eTaskKg['id'], \planning\HarvestWeekLib::getTaskWeek($this->eStructure, $eCultivation, \Clock::week(), $eVariety, $eSize)['id'], 'la variété passe en pièces à sa dernière récolte');
		$this->assertEquals(\series\Task::KG, \series\TaskLib::getHarvestUnitsForWeek($this->eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), \Clock::week())[$eCultivation['id']][$key], 'la ligne de la variété passe en pièces');
		$this->assertEquals(NULL, \planning\HarvestWeekLib::getOtherUnit($this->eStructure, $eCultivation, $today, \series\Task::KG, $eVariety, $eSize), 'les kilos deviennent une autre unité pour la variété après ses pièces');

		[, $data] = $this->render(TRUE);

		$this->assertEquals(['kg' => 4.0, 'unit' => 40.0], $data->cCultivation[$eCultivation['id']]['harvestedParts'][$key]['days'][$today], 'la ligne de la variété n\'empile pas ses kilos et ses pièces du jour');
		$this->assertEquals(['kg', 'unit'], array_keys($data->cCultivation[$eCultivation['id']]['harvestedParts'][$key]['days'][$today]), 'la ligne de la variété en kilos n\'empile pas ses kilos en tête');

		// Une seconde variété récoltée en pièces avant de l'être en kilos : l'unité de la culture la commande.
		$eVarietyLater = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVarietyLater);
		$keyLater = \series\CultivationLib::getHarvestPartKey($eVarietyLater, $eSize);

		$eTaskLaterUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->ventilate($eTaskLaterUnit, ['variety' => $eVarietyLater]);
		$this->createHarvest($eTaskLaterUnit, 6.0, $today);

		$eTaskLaterKg = $this->createTaskHarvest($eCultivation, \Clock::week(), $today);
		$this->ventilate($eTaskLaterKg, ['variety' => $eVarietyLater]);
		$this->createHarvest($eTaskLaterKg, 2.0, $today);

		$this->assertEquals($eTaskLaterKg['id'], \planning\HarvestWeekLib::getTaskWeek($this->eStructure, $eCultivation, \Clock::week(), $eVarietyLater, $eSize)['id'], 'la variété récoltée d\'abord en pièces ne prend pas son intervention dans l\'unité de la culture');
		$this->assertEquals(\series\Task::KG, \series\TaskLib::getHarvestUnitsForWeek($this->eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), \Clock::week())[$eCultivation['id']][$keyLater], 'la ligne de la variété récoltée d\'abord en pièces ne passe pas dans l\'unité de la culture');

	}

	/**
	 * Une case empile ses unités celle de sa ligne en tête, puis les autres dans l'ordre de l'énumération
	 * (M-Planning, 21/10) : sur une culture en pièces, « 40 p. » avant « 4 kg », pour la culture comme pour
	 * sa variété, quel que soit l'ordre que rend la base.
	 */
	public function testOtherUnitCoupleOrder(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série en pièces '.uniqid()));

		$eCultivation['mainUnit'] = \series\Cultivation::UNIT;
		// Sans build(), qui vérifierait l'unité postée depuis le formulaire de culture : le décor veut une
		// culture en pièces, et l'unité qu'il pose est une constante de `Cultivation`.
		\series\CultivationLib::update($eCultivation, ['mainUnit']);

		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$today = \Clock::date();

		// Les kilos d'abord : la base rend alors les kilos en tête, que la case doit remettre après les pièces.
		foreach([\series\Task::KG => 4.0, \series\Task::UNIT => 40.0] as $unit => $quantity) {
			$eTask = $this->createTaskHarvestInUnit($eCultivation, $unit, $today);
			$this->ventilate($eTask, ['variety' => $eVariety]);
			$this->createHarvest($eTask, $quantity, $today);
		}

		[, $data] = $this->render(TRUE);

		if($this->assertDecorPresent($data->cCultivation->offsetExists($eCultivation['id']), 'la culture en pièces est dans la liste de la semaine') === FALSE) {
			return;
		}

		$eCultivationShown = $data->cCultivation[$eCultivation['id']];
		$key = \series\CultivationLib::getHarvestPartKey($eVariety, new \plant\Size());

		$this->assertEquals(['unit', 'kg'], array_keys($eCultivationShown['harvestedParts'][$key]['days'][$today]), 'la ligne de la variété en pièces n\'empile pas ses pièces en tête');
		$this->assertEquals(['unit', 'kg'], array_keys($eCultivationShown['harvestedParts'][$key]['week']), 'la semaine de la variété en pièces n\'empile pas ses pièces en tête');
		$this->assertEquals(['unit', 'kg'], array_keys($eCultivationShown['harvestedDays'][$today]), 'la ligne de la culture en pièces n\'empile pas ses pièces en tête');

	}

	/**
	 * Une variété se saisit aussi dans une autre unité au tiroir de la culture (HN-B2c) : la liste poste
	 * l'unité sans intervention, et l'intervention de la culture entière qu'un écran posterait encore avec
	 * elle ne désigne rien, n'étant pas celle de la variété.
	 */
	public function testOtherUnitCoupleEntry(): void {

		[$eCultivation, $eVariety] = $this->createCultivationPart();

		$eTaskWhole = $this->getTasks($eCultivation)->find(fn($eTask) => $eTask['variety']->empty())->first();

		foreach([
			'sans intervention' => [[], 3.0],
			'avec l\'intervention de la culture entière' => [['task' => $eTaskWhole['id']], 6.0],
		] as $case => [$input, $harvested]) {

			$action = $this->post($eCultivation, $input + ['mode' => 'add', 'harvestMore' => '3', 'harvestUnit' => \series\Task::UNIT, 'variety' => $eVariety['id'], 'harvestSize' => '']);

			if($this->assertTrue($action instanceof \ViewAction, $case.', des pièces sur une variété sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$cTaskUnit = $this->getTasks($eCultivation)->find(fn($eTask) => $eTask['harvestUnit'] === \series\Task::UNIT);

			$this->assertEquals([1, $eVariety['id'], $harvested], [$cTaskUnit->count(), $cTaskUnit->first()['variety']['id'], $cTaskUnit->first()['harvest']], $case.', les pièces ne vont pas sur la seule intervention de la variété en pièces');

		}

		$this->assertEquals([\Clock::date() => ['kg' => 94.0, 'unit' => 6.0]], $this->getHarvested($eCultivation), 'les kilos du jour ont bougé');

	}

	/**
	 * Un jour récolté dans deux unités se remplace unité par unité (M-Planning, 21/10, HN-B2c) : la tête garde
	 * l'unité de la ligne, chaque autre unité a sa rangée (`getOthers()`), postée en `harvestOther[<unité>]` et
	 * écrite sur l'intervention de son unité. Une rangée à 0 vide son unité sans refus du schéma.
	 */
	public function testReplaceOthers(): void {

		$eCultivation = $this->createCultivation();
		$today = \Clock::date();

		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), $today);
		$this->createHarvest($eTaskKg, 6.0, $today);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->createHarvest($eTaskUnit, 10.0, $today);

		[, $data] = $this->render();

		if($this->assertDecorPresent($data->cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée en deux unités est dans la liste de la semaine') === FALSE) {
			return;
		}

		$this->assertEquals([\series\Task::UNIT => 10.0], \planning\HarvestWeekLib::getOthers($data->cCultivation[$eCultivation['id']], [], $today, \series\Task::KG), 'les pièces ne sont pas la seule autre unité du jour');
		$this->assertEquals([], \planning\HarvestWeekLib::getOthers($data->cCultivation[$eCultivation['id']], [], week_date_day(\Clock::week(), 1) === $today ? week_date_day(\Clock::week(), 2) : week_date_day(\Clock::week(), 1), \series\Task::KG), 'un jour sans récolte a d\'autres unités');

		[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $today, 'mode' => 'replace'], 'GET');

		$this->assertEquals([\series\Task::UNIT => 10.0], $data->others ?? NULL, 'le tiroir de remplacement ne reçoit pas les pièces du jour');

		[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $today, 'mode' => 'add'], 'GET');

		$this->assertEquals([], $data->others ?? NULL, 'le tiroir d\'ajout reçoit des rangées');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'harvestOther' => [\series\Task::UNIT => '3']]);

		if($this->assertTrue($action instanceof \ViewAction, 'le remplacement en deux unités est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$today => ['kg' => 2.0, 'unit' => 3.0]], $this->getHarvested($eCultivation), 'le jour ne lit pas 2 kg et 3 pièces');
		$this->assertEquals([[$eTaskKg['id'], $eTaskUnit['id']], 2.0, 3.0], [$cTask->getIds(), $cTask[$eTaskKg['id']]['harvest'], $cTask[$eTaskUnit['id']]['harvest']], 'chaque quantité n\'est pas sur l\'intervention de son unité');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'harvestOther' => [\series\Task::UNIT => '0']]);

		$this->assertTrue($action instanceof \ViewAction, 'une rangée à 0 est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$today => ['kg' => 2.0]], $this->getHarvested($eCultivation), 'une rangée à 0 ne vide pas les pièces, et elles seules');

	}

	/**
	 * Ce qu'une rangée ne prend pas : une unité que le jour ne porte pas, celle de la tête, un ajout, une carte,
	 * une rangée vide, et une rangée qui retomberait sur l'intervention de la tête. Les refus d'une rangée se
	 * rattachent à elle (`harvestDay[<unité>]`). Rien ne s'écrit.
	 */
	public function testReplaceOthersRefused(): void {

		$eCultivation = $this->createCultivation();
		$today = \Clock::date();

		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), $today);
		$this->createHarvest($eTaskKg, 6.0, $today);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->createHarvest($eTaskUnit, 10.0, $today);

		$harvested = $this->getHarvested($eCultivation);

		foreach([
			'dans une unité que le jour ne porte pas' => ['mode' => 'replace', 'harvestOther' => [\series\Task::BUNCH => '1']],
			'dans l\'unité de la tête' => ['mode' => 'replace', 'harvestOther' => [\series\Task::KG => '1']],
			'en ajout' => ['mode' => 'add', 'harvestMore' => '1', 'harvestOther' => [\series\Task::UNIT => '1']],
			'en liste' => ['mode' => 'replace', 'harvestOther' => '1'],
		] as $case => $input) {

			$action = $this->post($eCultivation, $input + ['harvestDay' => '2']);

			$this->assertTrue($action instanceof \NotExpectedAction, 'une rangée '.$case.' n\'est pas refusée');

		}

		$action = $this->executePage('planning/taskWeek:doHarvest', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'task' => $eTaskKg['id'],
			'date' => $today,
			'mode' => 'replace',
			'harvestDay' => '2',
			'harvestOther' => [\series\Task::UNIT => '1'],
		], 'POST');

		$this->assertTrue($action instanceof \NotExpectedAction, 'une rangée depuis une carte n\'est pas refusée');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'harvestOther' => [\series\Task::UNIT => '']]);

		// Le même refus qu'une tête vide, rattaché à la rangée.
		$this->assertEquals(['Task::harvestDay.negative' => 'harvestDay['.\series\Task::UNIT.']'], $this->getFailWrappers($action), 'une rangée vide n\'est pas refusée sous elle');
		$this->assertEquals($harvested, $this->getHarvested($eCultivation), 'une rangée refusée a écrit une récolte');

	}

	/**
	 * Un refus de la préparation d'une rangée se rattache à elle, et non à la tête : des pièces ventilées ce
	 * jour-là refusent de remplacer les pièces de la culture entière (`Task::harvestDay.ventilated`).
	 */
	public function testReplaceOthersWrapper(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$today = \Clock::date();

		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), $today);
		$this->createHarvest($eTaskKg, 6.0, $today);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->createHarvest($eTaskUnit, 10.0, $today);

		$eTaskUnitVentilated = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->ventilate($eTaskUnitVentilated, ['variety' => $eVariety]);
		$this->createHarvest($eTaskUnitVentilated, 5.0, $today);

		$harvested = $this->getHarvested($eCultivation);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'harvestOther' => [\series\Task::UNIT => '3']]);

		$this->assertEquals(['Task::harvestDay.ventilated' => 'harvestDay['.\series\Task::UNIT.']'], $this->getFailWrappers($action), 'le refus des pièces ventilées ne se rattache pas à leur rangée');
		$this->assertEquals($harvested, $this->getHarvested($eCultivation), 'une rangée refusée a écrit une récolte, la tête comprise');

	}

	/**
	 * Une rangée qui retomberait sur l'intervention de la tête se refuse (HN-B2c, architecte, second tour) :
	 * des pièces du jour sur une intervention d'une autre semaine laissent la semaine sans intervention en
	 * pièces, et la saisie en pièces prendrait alors l'intervention de la semaine encore sans unité, celle de
	 * la tête. L'écran n'y mène pas.
	 */
	public function testReplaceOthersOnHead(): void {

		$eCultivation = $this->createCultivation();
		$today = \Clock::date();

		$this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$eTaskUnitBefore = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, date('Y-m-d', strtotime($today.' - 7 days')));
		$this->createHarvest($eTaskUnitBefore, 10.0, $today);

		$harvested = $this->getHarvested($eCultivation);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'harvestOther' => [\series\Task::UNIT => '3']]);

		$this->assertTrue($action instanceof \NotExpectedAction, 'une rangée sur l\'intervention de la tête n\'est pas refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals($harvested, $this->getHarvested($eCultivation), 'une rangée refusée a écrit une récolte');

	}

	/**
	 * « Supprimer » vide le jour dans toutes ses unités (M-Planning, 21/10, HN-B2c). Le stock, qui ne rend que
	 * l'unité de la tête, est éprouvé par `HarvestStockTest::testDeleteOthers()`.
	 */
	public function testDeleteOthers(): void {

		$eCultivation = $this->createCultivation();
		$today = \Clock::date();

		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), $today);
		$this->createHarvest($eTaskKg, 6.0, $today);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $today);
		$this->createHarvest($eTaskUnit, 10.0, $today);

		[, $action] = $this->delete($eCultivation);

		$this->assertTrue($action instanceof \ViewAction, 'la suppression du jour en deux unités est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([], $this->getHarvested($eCultivation)[$today] ?? [], 'la suppression ne vide pas le jour dans ses deux unités');

	}

	/**
	 * Une récolte à faire planifiée dans une autre unité que celle de la culture suit la même règle qu'une
	 * faite (retour 319, lecture stricte) : elle n'est pas l'intervention de la semaine, la saisie d'un jour
	 * crée la sienne en kilos, et « Nouvelle récolte » la désigne par son unité.
	 */
	public function testOtherUnitPlanned(): void {

		$eCultivation = $this->createCultivation();

		$eTaskUnit = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$eTaskUnit['harvestUnit'] = \series\Task::UNIT;

		// Sans build(), qui vérifierait l'unité postée depuis le formulaire d'intervention : le décor veut une
		// récolte à faire en pièces sur une culture en kilos, que la fixture pose sans unité.
		\series\TaskLib::update($eTaskUnit, ['harvestUnit']);

		[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

		$this->assertEquals(\series\Task::KG, $data->unit, '« Nouvelle récolte » s\'ouvre dans l\'unité de la récolte à faire, pas dans celle de la culture');
		$this->assertEquals($eTaskUnit['id'], $data->tasksByUnit[\series\Task::UNIT]['id'] ?? NULL, '« Nouvelle récolte » ne désigne pas la récolte à faire par son unité');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '6']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du jour est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([\Clock::date() => ['kg' => 6.0]], $this->getHarvested($eCultivation), 'la saisie du jour ne s\'écrit pas en kilos');
		$this->assertEquals([\series\Task::TODO, NULL], [$cTask[$eTaskUnit['id']]['status'], $cTask[$eTaskUnit['id']]['harvest']], 'la saisie en kilos va dans la récolte à faire en pièces');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '4', 'crate' => '0', 'panel' => 1, 'task' => $eTaskUnit['id']]);
		$this->assertTrue($action instanceof \ViewAction, '« Nouvelle récolte » en pièces est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals(4.0, $this->getTasks($eCultivation)[$eTaskUnit['id']]['harvest'], 'la récolte à faire en pièces ne reçoit pas la saisie qui la désigne');

	}

	/**
	 * L'unité suit la case qu'on ouvre (R-Retours-0810, T, piste A). La règle de la semaine retient les kilos,
	 * et le mardi n'a que des bottes : sa case désigne l'intervention en bottes, en remplacement comme en
	 * ajout, et son tiroir poste l'intervention en bottes. Le lundi, qui a des bottes et des kilos, garde la
	 * règle, et son tiroir ne désigne aucune intervention.
	 */
	public function testUnitFollowsCell(): void {

		['eCultivation' => $eCultivation, 'eTaskBunch' => $eTaskBunch, 'monday' => $monday, 'tuesday' => $tuesday] = $this->createBunchThenKg();

		[$document] = $this->render();

		$this->assertEquals((string)$eTaskBunch['id'], $this->getOpenTask($document, $eCultivation, $tuesday, 'replace'), 'la quantité d\'un jour en bottes ne désigne pas l\'intervention en bottes');
		$this->assertEquals((string)$eTaskBunch['id'], $this->getOpenTask($document, $eCultivation, $tuesday, 'add'), 'le « + » d\'un jour en bottes ne désigne pas l\'intervention en bottes');
		$this->assertEquals(NULL, $this->getOpenTask($document, $eCultivation, $monday, 'replace'), 'un jour à deux unités ne garde pas la règle de la semaine');

		$drawer = $this->openDrawerOn($eCultivation, $tuesday, 'replace', $eTaskBunch);

		if($this->assertTrue($drawer !== NULL, 'le tiroir du jour en bottes ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals('1', $drawer->querySelector('[data-wrapper~="harvestDay"] input[name="harvestDay"]')?->getAttribute('value'), 'le champ du tiroir en bottes ne part pas de la quantité de la case');
		$this->assertEquals((string)$eTaskBunch['id'], $drawer->querySelector('input[name="task"]')?->getAttribute('value'), 'le tiroir du jour en bottes ne poste pas son intervention');

		$drawer = $this->openDrawerOn($eCultivation, $monday, 'replace');

		if($this->assertTrue($drawer !== NULL, 'le tiroir du jour à deux unités ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals('1', $drawer->querySelector('[data-wrapper~="harvestDay"] input[name="harvestDay"]')?->getAttribute('value'), 'le champ du tiroir du jour à deux unités ne part pas des kilos de la case');
		// Le tiroir d'une culture entière propose les autres unités (lot GN) : le champ existe, éteint, et rien ne se poste.
		$field = $drawer->querySelector('input[name="task"]');
		$this->assertTrue(($field !== NULL and $field->hasAttribute('disabled') and ($field->getAttribute('value') ?? '') === ''), 'le tiroir du jour à deux unités désigne une intervention');

	}

	/**
	 * L'intervention désignée reçoit la saisie dans son unité, sans conversion, et l'autre ne bouge pas : le
	 * remplacement du mardi en bottes, puis « Nouvelle récolte » en bottes.
	 */
	public function testWriteToChosenTask(): void {

		['eCultivation' => $eCultivation, 'eTaskBunch' => $eTaskBunch, 'eTaskKg' => $eTaskKg, 'monday' => $monday, 'tuesday' => $tuesday] = $this->createBunchThenKg();

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $tuesday, 'harvestDay' => '2', 'task' => $eTaskBunch['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en bottes est refusé : '.implode(', ', $this->getFails($action)));

		$harvested = $this->getHarvested($eCultivation);

		$this->assertEquals(['bunch' => 2.0], $harvested[$tuesday] ?? NULL, 'le remplacement du jour en bottes n\'écrit pas 2 bottes');
		$this->assertEquals(1.0, $harvested[$monday]['kg'] ?? NULL, 'le remplacement en bottes touche aux kilos');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3', 'crate' => '0', 'panel' => 1, 'task' => $eTaskBunch['id']]);
		$this->assertTrue($action instanceof \ViewAction, '« Nouvelle récolte » en bottes est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(15.0, $cTask[$eTaskBunch['id']]['harvest'], '« Nouvelle récolte » en bottes ne va pas à l\'intervention en bottes, ou se convertit');
		$this->assertEquals(1.0, $cTask[$eTaskKg['id']]['harvest'], '« Nouvelle récolte » en bottes touche à l\'intervention en kilos');
		$this->assertEquals(2, $cTask->count(), 'une saisie désignée crée une intervention');

	}

	/**
	 * Une intervention postée à la main qui n'est pas une récolte de la culture entière dans la semaine de
	 * l'écran se refuse par la chaîne d'erreur, avec son message, et rien ne s'écrit : à l'ouverture comme à
	 * l'écriture.
	 */
	public function testChosenTaskForeign(): void {

		$eCultivation = $this->createCultivation();
		$eCultivationOther = $this->createCultivation($this->createSeries('Série étrangère '.uniqid()));

		$monday = week_date_day(\Clock::week(), 1);

		// Une récolte lundi, pour que la culture soit dans la liste de la semaine, où son tiroir s'ouvre.
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), $monday), 1.0, $monday);

		$harvested = $this->getHarvested($eCultivation);

		// Une récolte d'une part ne se désigne pas non plus : une case ou une unité de « Nouvelle récolte »
		// désigne une récolte de la culture entière, dans la semaine de l'écran.
		$eTaskPart = $this->createTaskHarvestInUnit($eCultivation, \series\Task::BUNCH, $monday);
		$this->ventilate($eTaskPart, ['variety' => $this->createVariety($eCultivation['plant'])]);

		$tasksForeign = [
			'récolte d\'une autre culture' => $this->createTaskHarvestInUnit($eCultivationOther, \series\Task::BUNCH, $monday),
			'intervention qui n\'est pas une récolte' => $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday),
			'récolte d\'une autre semaine' => $this->createTaskHarvestInUnit($eCultivation, \series\Task::BUNCH, date('Y-m-d', strtotime($monday.' - 7 days'))),
			'récolte d\'une part' => $eTaskPart,
		];

		foreach($tasksForeign as $case => $eTaskForeign) {

			$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'task' => $eTaskForeign['id']]);
			$this->assertTrue(in_array('Task::cultivation.harvest', $this->getFails($action), TRUE), 'une '.$case.' postée n\'est pas refusée par Task::cultivation.harvest : '.implode(', ', $this->getFails($action)));
			$this->assertDiffer(NULL, \series\AlertUi::getError('Task::cultivation.harvest'), 'le refus d\'une '.$case.' n\'a pas de message');

			[, , $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'mode' => 'add', 'task' => $eTaskForeign['id']], 'GET');
			$this->assertTrue(in_array('Task::cultivation.harvest', $this->getFails($action), TRUE), 'le tiroir s\'ouvre sur une '.$case);

		}

		$this->assertEquals($harvested, $this->getHarvested($eCultivation), 'une intervention refusée a reçu une récolte');
		$this->assertEquals([], $this->getHarvested($eCultivationOther), 'la récolte d\'une autre culture a reçu la saisie');

	}

	/**
	 * « Nouvelle récolte » range les autres unités de la semaine avec kg et caisses, dans le même choix : chacune
	 * désigne son intervention, que le panneau poste dans un champ caché, éteint tant que l'unité est celle de
	 * la règle.
	 */
	public function testPanelUnits(): void {

		['eCultivation' => $eCultivation, 'eTaskBunch' => $eTaskBunch] = $this->createBunchThenKg();

		[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

		$panel = new HarvestEntryUi()->create($this->eStructure, \Clock::week(), $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, taskWeek: $data->taskWeek, tasksByUnit: $data->tasksByUnit);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		$tasks = [];

		foreach($document->querySelectorAll('select[name="crate"] option[data-planning-harvest-task]') as $option) {
			$tasks[] = $option->getAttribute('data-planning-harvest-task');
		}

		$this->assertEquals([(string)$eTaskBunch['id']], $tasks, 'les bottes ne désignent pas leur intervention, ou les kilos de la règle en désignent une');

		$hidden = $document->querySelector('input[name="task"]');

		$this->assertTrue($hidden !== NULL and $hidden->hasAttribute('disabled'), 'le panneau ne porte pas d\'intervention éteinte à poster');

	}

	/**
	 * Un remplacement qui ferait passer sous zéro la part d'une intervention sur un jour est refusé par
	 * `buildHarvests()`, avec son code, et rien n'est écrit : deux interventions faites cette semaine
	 * portent chacune une part d'aujourd'hui, et la saisie ne retire que sur la dernière.
	 */
	public function testNegativeDay(): void {

		$eCultivation = $this->createCultivation();

		$eTaskFirst = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskFirst, 3.0, \Clock::date());

		$eTaskLast = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskLast, 1.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '0']);

		$this->assertTrue(in_array('Task::harvestDates.negative', $this->getFails($action), TRUE), 'le jour négatif n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(3.0, $cTask[$eTaskFirst['id']]['harvest'], 'le refus a écrit sur la première intervention');
		$this->assertEquals(1.0, $cTask[$eTaskLast['id']]['harvest'], 'le refus a écrit sur la dernière intervention');
		$this->assertEquals([\Clock::date() => ['kg' => 4.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');

	}

	/**
	 * Un remplacement négatif se refuse au `build()`, par l'étape de `harvestDay`. Un ajout négatif sur un jour
	 * sans récolte retire plus que le jour : il se refuse par le plancher du jour (`harvestMore.day`, retour 213),
	 * et non par le total de l'intervention, que la saisie n'a pas à connaître.
	 */
	public function testNegativeQuantity(): void {

		$eCultivation = $this->createCultivation();

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '-1']);
		$this->assertTrue(in_array('Task::harvestDay.negative', $this->getFails($action), TRUE), 'un remplacement négatif n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '-1']);
		$this->assertEquals(['Task::harvestMore.day'], $this->getFails($action), 'un ajout négatif sur un jour vide n\'est pas refusé par le plancher du jour');

		$this->assertEquals([], $this->getHarvested($eCultivation), 'un refus a écrit une récolte');
		$this->assertEquals([], $this->getTasks($eCultivation)->getIds(), 'un refus a créé une intervention');

	}

	/**
	 * Une quantité négative retire du jour saisi, jusqu'à sa récolte et pas au-delà (retour 213). Le plancher est
	 * le récolté du jour de la culture dans l'unité : 3 kg lundi, -1 laisse 2 kg, puis -5 se refuse par
	 * `harvestMore.day` sans rien écrire, et -2 ramène le jour à zéro. Une soustraction ne dit pas qu'une
	 * récolte est finie : l'intervention à faire le reste (arbitrage V-Planning du 2026-10-10, point c).
	 */
	public function testNegativeFromDay(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week(), $tuesday);

		// Sans build(), qui n'a rien à vérifier sur une unité que le décor pose : c'est celle qu'une première
		// saisie aurait écrite, et `createHarvest()` la lit pour ranger la récolte.
		$eTask['harvestUnit'] = \series\Task::KG;
		\series\TaskLib::update($eTask, ['harvestUnit']);

		$this->createHarvest($eTask, 3.0, $monday);

		if($this->assertDecorPresent($this->getHarvested($eCultivation) === [$monday => [\series\Task::KG => 3.0]], '3 kg récoltés lundi sur une intervention à faire') === FALSE) {
			return;
		}

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '-1']);
		$this->assertTrue($action instanceof \ViewAction, '-1 sur un jour de 3 est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => [\series\Task::KG => 2.0]], $this->getHarvested($eCultivation), '-1 sur un jour de 3 ne laisse pas 2');
		$this->assertEquals(\series\Task::TODO, $this->getTasks($eCultivation)[$eTask['id']]['status'], 'une quantité négative termine l\'intervention à faire');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '-5']);
		$this->assertEquals(['Task::harvestMore.day'], $this->getFails($action), '-5 sur un jour de 2 n\'est pas refusé par le plancher du jour');
		$this->assertEquals([$monday => [\series\Task::KG => 2.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '-2']);
		$this->assertTrue($action instanceof \ViewAction, '-2 sur un jour de 2 est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => []], $this->getHarvested($eCultivation), '-2 sur un jour de 2 ne le ramène pas à zéro');
		$this->assertEquals(0.0, $this->getTasks($eCultivation)[$eTask['id']]['harvest'], 'l\'intervention ne suit pas le retrait');

	}

	/**
	 * Le plancher se lit dans le jour de la culture, pas dans celui d'une autre : 3 kg lundi ne permettent pas
	 * de retirer 1 kg mardi, et le refus passe par le même code.
	 */
	public function testNegativeOtherDay(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), $monday), 3.0, $monday);

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $tuesday, 'harvestMore' => '-1']);
		$this->assertEquals(['Task::harvestMore.day'], $this->getFails($action), 'un retrait sur un jour sans récolte n\'est pas refusé par le plancher du jour');
		$this->assertEquals([$monday => [\series\Task::KG => 3.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');

	}

	/**
	 * Le champ de l'ajout, au tiroir comme au panneau, laisse taper une quantité négative : le refus vient du
	 * serveur, sous le champ, jamais de l'infobulle du navigateur (retour 213).
	 */
	public function testNegativeField(): void {

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 3.0, \Clock::date());

		[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

		$panel = new HarvestEntryUi()->create($this->eStructure, \Clock::week(), $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		$field = $document->querySelector('input[name="harvestMore"]');

		if($this->assertTrue($field !== NULL, 'Nouvelle récolte ne porte pas le champ de la quantité') === FALSE) {
			return;
		}

		$this->assertFalse($field->hasAttribute('min'), 'Nouvelle récolte borne la quantité à zéro dans le navigateur');

		// Un refus ne s'affiche que sous son champ, jamais une seconde fois sous le libellé du groupe, qui
		// hériterait sinon du wrapper du dernier champ rendu : la quantité en bottes, le poids de caisse en
		// kilos quand l'espèce se pèse à la caisse, le temps dans les deux unités.
		if($this->assertDecorPresent($data->eCultivation['plant']['crateWrite'], 'l\'espèce de la fixture laisse saisir le poids de sa caisse') === FALSE) {
			return;
		}

		foreach([$data->unit, \series\Cultivation::BUNCH] as $unit) {

			$panelUnit = new HarvestEntryUi()->create($this->eStructure, \Clock::week(), $data->eCultivation, $unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline);
			$documentUnit = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panelUnit->body.'</body></html>', LIBXML_NOERROR);

			$this->assertEquals(1, $documentUnit->querySelectorAll('[data-wrapper~="harvestMore"]')->length, 'Nouvelle récolte en '.$unit.', le refus de la quantité s\'affiche ailleurs que sous le champ');
			$this->assertEquals(1, $documentUnit->querySelectorAll('[data-wrapper~="time"]')->length, 'Nouvelle récolte en '.$unit.', le refus du temps s\'affiche ailleurs que sous la tuile');
			$this->assertEquals(match($unit) { \series\Cultivation::KG => 1, \series\Cultivation::BUNCH => 0 }, $documentUnit->querySelectorAll('[data-wrapper~="crateWeight"]')->length, 'Nouvelle récolte en '.$unit.', le refus du poids de caisse s\'affiche ailleurs que sous son champ');

		}

		$drawer = $this->openDrawer($eCultivation, \Clock::date());

		if($this->assertTrue($drawer !== NULL, 'le tiroir ne s\'ouvre pas') === FALSE) {
			return;
		}

		$fieldDrawer = $drawer->querySelector('input[name="harvestMore"]');

		if($this->assertTrue($fieldDrawer !== NULL, 'le tiroir ne porte pas le champ de l\'ajout') === FALSE) {
			return;
		}

		$this->assertFalse($fieldDrawer->hasAttribute('min'), 'le tiroir borne l\'ajout à zéro dans le navigateur');

	}

	/**
	 * Un ajout vide ou à zéro, sans temps, se refuse par son code et ne termine pas l'intervention de
	 * la semaine. Une case de la grille mise à zéro sur un jour sans récolte ni intervention ne crée rien.
	 */
	public function testEmptyQuantity(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		foreach(['', '0'] as $quantity) {

			$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => $quantity]);
			$this->assertTrue(in_array('Task::harvestMore.empty', $this->getFails($action), TRUE), 'un ajout « '.$quantity.' » sans temps n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		}

		$this->assertEquals(\series\Task::TODO, $this->getTasks($eCultivation)[$eTask['id']]['status'], 'un ajout vide termine l\'intervention de la semaine');

		$eCultivationEmpty = $this->createCultivation($this->createSeries('Série sans intervention'));

		$action = $this->post($eCultivationEmpty, ['mode' => 'replace', 'harvestDay' => '0']);
		$this->assertTrue($action instanceof \ViewAction, 'un zéro sur un jour vide est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([], $this->getTasks($eCultivationEmpty)->getIds(), 'un zéro sur un jour vide crée une intervention');

	}

	/**
	 * Un temps ajouté sans quantité, depuis le tiroir qui ne poste pas « terminée », laisse l'intervention
	 * dans l'état où il la trouve : à faire, elle reste à faire, faite, elle reste faite. Le temps est
	 * écrit sur elle, et aucune récolte (arbitrage V-Planning du 2026-09-30).
	 */
	public function testTimeOnly(): void {

		$eCultivationTodo = $this->createCultivation();
		$eTaskTodo = $this->createTask($eCultivationTodo, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$action = $this->post($eCultivationTodo, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('0.5')]);
		$this->assertTrue($action instanceof \ViewAction, 'un temps sans quantité est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$eTaskTodo['id']], $this->getTasks($eCultivationTodo)->getIds(), 'un temps sans quantité crée une intervention au lieu de compléter celle de la semaine');
		$this->assertEquals(\series\Task::TODO, $this->getTasks($eCultivationTodo)[$eTaskTodo['id']]['status'], 'un temps sans quantité termine l\'intervention à faire');
		$this->assertEquals([$eTaskTodo['id'] => 0.5], $this->getTimeByTask($eCultivationTodo), 'le temps n\'est pas écrit sur l\'intervention à faire');
		$this->assertEquals([], $this->getHarvested($eCultivationTodo), 'un temps sans quantité écrit une récolte');

		$eCultivationDone = $this->createCultivation($this->createSeries('Série faite'));
		$eTaskDone = $this->createTaskHarvest($eCultivationDone, \Clock::week(), \Clock::date());

		$action = $this->post($eCultivationDone, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('0.5')]);
		$this->assertTrue($action instanceof \ViewAction, 'un temps sans quantité est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals(\series\Task::DONE, $this->getTasks($eCultivationDone)[$eTaskDone['id']]['status'], 'un temps sans quantité rouvre l\'intervention faite');
		$this->assertEquals([$eTaskDone['id'] => 0.5], $this->getTimeByTask($eCultivationDone), 'le temps n\'est pas écrit sur l\'intervention faite');

	}

	/**
	 * Sur une semaine sans intervention, un temps seul crée une intervention à faire, planifiée au jour
	 * saisi, que la saisie suivante de la semaine retrouve : la quantité du mardi la termine au lieu d'en
	 * créer une seconde, et l'intervention porte le temps du lundi et la récolte du mardi (arbitrage
	 * V-Planning du 2026-09-30, option A).
	 */
	public function testTimeOnlyCreatesTodoTask(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '', 'time' => $this->getTimeInput('0.5')]);
		$this->assertTrue($action instanceof \ViewAction, 'le temps seul du lundi est refusé : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		if($this->assertEquals(1, $cTask->count(), 'le temps seul du lundi ne crée pas une intervention') === FALSE) {
			return;
		}

		$eTask = $cTask->first();

		$this->assertEquals(\series\Task::TODO, $eTask['status'], 'l\'intervention créée par un temps seul est faite');
		$this->assertEquals($monday, $eTask['plannedDate'], 'l\'intervention créée par un temps seul n\'est pas planifiée au jour saisi');
		$this->assertEquals(\Clock::week(), $eTask['plannedWeek'], 'l\'intervention créée par un temps seul n\'est pas planifiée dans la semaine');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $tuesday, 'harvestMore' => '3']);
		$this->assertTrue($action instanceof \ViewAction, 'la quantité du mardi est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTask['id']], $cTask->getIds(), 'la quantité du mardi crée une seconde intervention au lieu de compléter celle du lundi');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTask['id']]['status'], 'la quantité du mardi ne termine pas l\'intervention');
		$this->assertEquals(\Clock::week(), $cTask[$eTask['id']]['doneWeek'], 'l\'intervention n\'est pas faite dans la semaine');

		// L'intervention à faire est replanifiée au jour de la récolte avant d'être terminée, et
		// `updateDoneCollection()` la date du plus tard de ce jour et du temps du lundi (V-Planning,
		// 2026-10-06, R-Retours-0610 point 8 : le lundi retenu le 2026-09-30 séparait Récoltes et Temps).
		$this->assertEquals($tuesday, $cTask[$eTask['id']]['doneDate'], 'l\'intervention n\'est pas faite au jour de la récolte');

		$this->assertEquals(3.0, $cTask[$eTask['id']]['harvest'], 'l\'intervention ne porte pas la quantité du mardi');
		$this->assertEquals([$tuesday => [\series\Cultivation::KG => 3.0]], $this->getHarvested($eCultivation), 'la récolte n\'est pas datée du mardi');
		$this->assertEquals([$tuesday => [\series\Cultivation::KG => 3.0]], HarvestWeekLib::get($this->eStructure, \Clock::week())[$eCultivation['id']]['harvestedDays'], 'la grille ne range pas la quantité du mardi sous le mardi');
		$this->assertEquals([$eTask['id'] => 0.5], $this->getTimeByTask($eCultivation), 'l\'intervention ne porte pas le temps du lundi');

	}

	/**
	 * Récoltes et Temps de travail rangent une récolte le même jour (R-Retours-0610, point 8) : l'intervention
	 * à faire que la saisie termine est replanifiée au jour de la récolte, plus tôt comme plus tard que son jour
	 * prévu, puis `updateDoneCollection()` la date du plus tard de ce jour et de son temps. Une intervention
	 * déjà faite garde son jour de fin (V-Planning, 2026-10-06).
	 *
	 * Le premier cas est celui de la capture : planifiée le mardi, récoltée le lundi. L'ancienne règle ne
	 * replanifiait qu'une intervention d'une semaine passée, et la datait du mardi.
	 */
	public function testDoneOnHarvestDay(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);
		$wednesday = week_date_day(\Clock::week(), 3);

		$cases = [
			'planifiée le mardi, récoltée le lundi' => [\series\Task::TODO, $tuesday, NULL, $monday, $monday, [$monday]],
			'planifiée le lundi, récoltée le mardi' => [\series\Task::TODO, $monday, NULL, $tuesday, $tuesday, [$tuesday]],
			'planifiée sans jour, récoltée le lundi' => [\series\Task::TODO, NULL, NULL, $monday, $monday, [$monday]],
			'planifiée le mardi, temps du mercredi, récoltée le lundi' => [\series\Task::TODO, $tuesday, $wednesday, $monday, $wednesday, [$monday, $wednesday]],
			'faite le mardi, récoltée le lundi' => [\series\Task::DONE, $tuesday, NULL, $monday, $tuesday, [$monday, $tuesday]],
		];

		foreach($cases as $case => [$status, $date, $dateTime, $dateHarvest, $doneDate, $keys]) {

			$eCultivation = $this->createCultivation($this->createSeries($case));
			$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, $status, \Clock::week(), $date);

			if($dateTime !== NULL) {
				$this->createTimesheet($eTask, 0.5, $dateTime);
			}

			$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $dateHarvest, 'harvestMore' => '5']);

			if($this->assertTrue($action instanceof \ViewAction, $case.', la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$cTask = $this->getTasks($eCultivation);

			if($this->assertEquals([$eTask['id']], $cTask->getIds(), $case.', la saisie ne complète pas l\'intervention du décor') === FALSE) {
				continue;
			}

			$eTaskDone = $cTask[$eTask['id']];

			$this->assertEquals($doneDate, $eTaskDone['doneDate'], $case.', l\'intervention n\'est pas faite au jour attendu');
			$this->assertEquals($keys, TaskDayLib::getKeysForTask($this->eStructure, \Clock::week(), $eTaskDone), $case.', Temps de travail ne range pas l\'intervention aux jours attendus');
			$this->assertEquals([$dateHarvest => [\series\Cultivation::KG => 5.0]], HarvestWeekLib::get($this->eStructure, \Clock::week())[$eCultivation['id']]['harvestedDays'], $case.', Récoltes ne range pas la quantité au jour saisi');

		}

	}

	/**
	 * Une intervention en retard d'une semaine, celle que le planning montre dans « Retardé », reçoit la
	 * récolte au lieu d'une intervention neuve, et se termine au jour saisi, dans la semaine de la saisie :
	 * la saisie suivante de la semaine la retrouve. Un temps seul la laisse à faire, à sa semaine
	 * d'origine (décision de l'architecte du 2026-09-30).
	 *
	 * Le contrôle de tête prouve que le cas sort de l'ancienne règle, qui ne lisait que l'intervention à
	 * faire ou faite de la semaine (`TaskLib::applyHarvestWeek()`) : elle créait une seconde
	 * intervention, et la première assertion tombait.
	 */
	public function testCompletesDelayedTask(): void {

		$weekDelayed = toWeek(\Clock::shift('- 1 WEEK'));
		$tuesday = week_date_day(\Clock::week(), 2);

		$eCultivation = $this->createCultivation();
		$eTaskDelayed = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, $weekDelayed);

		// applyHarvestWeek() pose un tri, que count() reporterait dans le SELECT COUNT(*) : on compte la collection.
		$this->assertEquals(0, \series\TaskLib::applyHarvestWeek(new \series\TaskModel(), $this->eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $this->getAction(ACTION_RECOLTE), \Clock::week())
			->select(['id'])
			->getCollection()
			->count(), 'le décor porte une intervention de la semaine, que l\'ancienne règle trouvait déjà');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $tuesday, 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		if($this->assertEquals([$eTaskDelayed['id']], $cTask->getIds(), 'la saisie crée une intervention au lieu de compléter celle en retard') === FALSE) {
			return;
		}

		$eTask = $cTask[$eTaskDelayed['id']];

		$this->assertEquals(\series\Task::DONE, $eTask['status'], 'l\'intervention en retard reste à faire');
		$this->assertEquals($tuesday, $eTask['doneDate'], 'l\'intervention en retard n\'est pas faite au jour saisi');
		$this->assertEquals(\Clock::week(), $eTask['doneWeek'], 'l\'intervention en retard est faite dans sa semaine d\'origine, où la saisie suivante ne la retrouve plus');
		$this->assertEquals(\Clock::week(), $eTask['plannedWeek'], 'l\'intervention en retard n\'est pas replanifiée dans la semaine de la saisie');
		$this->assertEquals(2.0, $eTask['harvest'], 'l\'intervention en retard ne porte pas la récolte');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '1']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie suivante est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$eTaskDelayed['id']], $this->getTasks($eCultivation)->getIds(), 'la saisie suivante de la semaine ne retrouve pas l\'intervention complétée');

		$eCultivationTime = $this->createCultivation($this->createSeries('Série au temps seul'));
		$eTaskTime = $this->createTask($eCultivationTime, ACTION_RECOLTE, \series\Task::TODO, $weekDelayed);

		$action = $this->post($eCultivationTime, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('0.5')]);
		$this->assertTrue($action instanceof \ViewAction, 'le temps seul est refusé : '.implode(', ', $this->getFails($action)));

		$cTaskTime = $this->getTasks($eCultivationTime);

		$this->assertEquals([$eTaskTime['id']], $cTaskTime->getIds(), 'le temps seul crée une intervention au lieu de compléter celle en retard');
		$this->assertEquals(\series\Task::TODO, $cTaskTime[$eTaskTime['id']]['status'], 'le temps seul termine l\'intervention en retard');
		$this->assertEquals($weekDelayed, $cTaskTime[$eTaskTime['id']]['plannedWeek'], 'le temps seul déplace l\'intervention en retard');
		$this->assertEquals([$eTaskTime['id'] => 0.5], $this->getTimeByTask($eCultivationTime), 'le temps n\'est pas écrit sur l\'intervention en retard');

	}

	/**
	 * Une intervention planifiée avant la borne du planning (`planningDelayedMax` mois) n'est plus en
	 * retard pour lui, ni pour la saisie : elle reste à faire, et la saisie crée une intervention neuve.
	 */
	public function testDelayedBeyondBound(): void {

		$weekOld = toWeek(\Clock::shift('- '.($this->eStructure->conf(\map\Configuration::class)['planningDelayedMax'] + 1).' MONTH'));

		$eCultivation = $this->createCultivation();
		$eTaskOld = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, $weekOld);

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		if($this->assertEquals(2, $cTask->count(), 'la saisie complète une intervention au-delà de la borne du retard') === FALSE) {
			return;
		}

		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskOld['id']]['status'], 'l\'intervention au-delà de la borne est terminée');
		$this->assertEquals($weekOld, $cTask[$eTaskOld['id']]['plannedWeek'], 'l\'intervention au-delà de la borne est déplacée');
		$this->assertEquals(\series\Task::DONE, $cTask->find(fn($eTask) => $eTask['id'] !== $eTaskOld['id'])->first()['status'], 'l\'intervention neuve n\'est pas faite');

	}

	/**
	 * Entre deux interventions en retard, la plus ancienne reçoit la récolte. Elle est créée après
	 * l'autre : l'ordre suivi est celui de la planification, pas celui des identifiants.
	 */
	public function testOldestDelayed(): void {

		$eCultivation = $this->createCultivation();
		$eTaskRecent = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 1 WEEK')));
		$eTaskOldest = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 2 WEEK')));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTaskRecent['id'], $eTaskOldest['id']], $cTask->getIds(), 'la saisie crée une intervention malgré deux interventions en retard');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTaskOldest['id']]['status'], 'la plus ancienne intervention en retard reste à faire');
		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskRecent['id']]['status'], 'la plus récente intervention en retard est terminée');

	}

	/**
	 * L'intervention à faire de la semaine passe devant une intervention en retard, qui reste à faire.
	 */
	public function testWeekBeforeDelayed(): void {

		$eCultivation = $this->createCultivation();
		$eTaskDelayed = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 1 WEEK')));
		$eTaskWeek = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTaskDelayed['id'], $eTaskWeek['id']], $cTask->getIds(), 'la saisie crée une intervention malgré celle de la semaine');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTaskWeek['id']]['status'], 'l\'intervention de la semaine reste à faire');
		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskDelayed['id']]['status'], 'l\'intervention en retard est terminée à la place de celle de la semaine');

	}

	/**
	 * Une fois l'intervention de la semaine faite, la baisse d'un jour déjà récolté lui revient, et non
	 * à l'intervention en retard : `buildHarvests()` ne lit que la récolte du jour de l'intervention
	 * choisie, et le retard, qui n'en porte aucune, refuserait la baisse (B1 du relecteur, ordre retenu
	 * par l'architecte le 2026-09-30). L'ordre précédent, le retard devant la faite, tombait sur le refus.
	 */
	public function testLowerWithDelayed(): void {

		$eCultivation = $this->createCultivation();
		$eTaskDelayed = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 1 WEEK')));
		$eTaskWeek = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '1']);
		$this->assertTrue($action instanceof \ViewAction, 'la baisse du jour est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => [\series\Cultivation::KG => 1.0]], $this->getHarvested($eCultivation), 'le jour ne vaut pas la quantité baissée');

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(1.0, $cTask[$eTaskWeek['id']]['harvest'], 'la baisse ne va pas à l\'intervention faite de la semaine');
		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskDelayed['id']]['status'], 'la baisse termine l\'intervention en retard');

	}

	/**
	 * Une intervention faite dans la semaine reçoit la saisie de la semaine, et l'intervention en retard
	 * reste à faire. La semaine suivante, la première saisie la complète, faite au jour saisi dans cette
	 * semaine-là : la saisie termine au plus un retard par culture et par semaine.
	 */
	public function testDoneWeekThenDelayed(): void {

		$eCultivation = $this->createCultivation();
		$eTaskDelayed = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 1 WEEK')));
		$eTaskDone = $this->createTaskHarvest($eCultivation, \Clock::week(), week_date_day(\Clock::week(), 1));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie de la semaine est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTaskDelayed['id'], $eTaskDone['id']], $cTask->getIds(), 'la saisie de la semaine crée une intervention');
		$this->assertEquals(2.0, $cTask[$eTaskDone['id']]['harvest'], 'la saisie de la semaine ne va pas à l\'intervention faite');
		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskDelayed['id']]['status'], 'la saisie de la semaine termine l\'intervention en retard');

		$this->setClock(week_date_day(toWeek(\Clock::shift('+ 1 WEEK')), 4).' 10:00:00');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie de la semaine suivante est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTaskDelayed['id'], $eTaskDone['id']], $cTask->getIds(), 'la saisie de la semaine suivante crée une intervention au lieu de compléter celle en retard');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTaskDelayed['id']]['status'], 'la saisie de la semaine suivante ne termine pas l\'intervention en retard');
		$this->assertEquals(\Clock::date(), $cTask[$eTaskDelayed['id']]['doneDate'], 'l\'intervention en retard n\'est pas faite au jour saisi');
		$this->assertEquals(\Clock::week(), $cTask[$eTaskDelayed['id']]['doneWeek'], 'l\'intervention en retard n\'est pas faite dans la semaine de la saisie');
		$this->assertEquals(3.0, $cTask[$eTaskDelayed['id']]['harvest'], 'l\'intervention en retard ne porte pas la récolte');

	}

	/**
	 * Le jour et la durée passent par le `build()` de `\series\Timesheet` : un jour à venir se refuse,
	 * et une durée postée vide aussi. L'écran ne la poste jamais ainsi, son champ caché restant désactivé
	 * tant que rien n'est cumulé (`HarvestEntryUi::getTimeWindow()`), mais le POST se contourne.
	 */
	public function testDateAndTime(): void {

		$eCultivation = $this->createCultivation();

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => week_date_day(\Clock::week(), 5), 'harvestMore' => '1']);
		$this->assertTrue(in_array('Timesheet::date.future', $this->getFails($action), TRUE), 'un jour à venir n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '1', 'time' => $this->getTimeInput('')]);
		$this->assertTrue(in_array('Timesheet::time.check', $this->getFails($action), TRUE), 'une durée vide n\'est pas refusée par son code : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([], $this->getHarvested($eCultivation), 'un refus a écrit une récolte');

	}

	/**
	 * La saisie appartient à la semaine affichée (arbitrage V-Planning du 2026-09-30) : un jour passé
	 * hors de cette semaine se refuse par son code, en ajout comme en remplacement de la grille, et rien
	 * n'est écrit.
	 */
	public function testDateOutsideWeek(): void {

		$eCultivation = $this->createCultivation();
		$weekPrevious = toWeek(week_date_day(\Clock::week(), 1).' - 7 days');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => week_date_day($weekPrevious, 3), 'harvestMore' => '1']);
		$this->assertTrue(in_array('Timesheet::date.week', $this->getFails($action), TRUE), 'un jour de la semaine précédente n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'replace', 'week' => $weekPrevious, 'harvestDay' => '1']);
		$this->assertTrue(in_array('Timesheet::date.week', $this->getFails($action), TRUE), 'aujourd\'hui posté depuis la semaine précédente n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([], $this->getHarvested($eCultivation), 'un refus a écrit une récolte');

	}

	/**
	 * « Nouvelle récolte » propose aujourd'hui dans la semaine courante et le dernier jour d'une
	 * semaine passée, que `doHarvest` accepte. Une semaine à venir n'a ni bouton ni panneau.
	 */
	public function testCreateDate(): void {

		$eCultivation = $this->createCultivation();

		[$data, , $action] = $this->call('create', [], 'GET');
		$this->assertTrue($action instanceof \ViewAction, 'le panneau ne s\'ouvre pas sur la semaine courante');
		$this->assertEquals(\Clock::date(), $data->date, 'le panneau ne propose pas aujourd\'hui sur la semaine courante');

		$weekPrevious = toWeek(week_date_day(\Clock::week(), 1).' - 7 days');

		[$data, , $action] = $this->call('create', ['week' => $weekPrevious], 'GET');
		$this->assertTrue($action instanceof \ViewAction, 'le panneau ne s\'ouvre pas sur une semaine passée');
		$this->assertEquals(week_date_day($weekPrevious, 7), $data->date, 'le panneau ne propose pas le dernier jour d\'une semaine passée');
		$this->assertEquals(week_date_day($weekPrevious, 1), $data->dateMin, 'le jour du panneau ne commence pas au lundi de la semaine passée');

		$action = $this->post($eCultivation, ['mode' => 'add', 'week' => $weekPrevious, 'date' => $data->date, 'harvestMore' => '2', 'panel' => 1]);
		$this->assertTrue($action instanceof \ViewAction, 'le dernier jour d\'une semaine passée est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([week_date_day($weekPrevious, 7) => [\series\Cultivation::KG => 2.0]], $this->getHarvested($eCultivation), 'la récolte n\'est pas datée du dernier jour de la semaine passée');

		$weekNext = toWeek(week_date_day(\Clock::week(), 1).' + 7 days');

		[, , $action] = $this->call('create', ['week' => $weekNext], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'le panneau s\'ouvre sur une semaine à venir');

		$urlCreate = 'harvestWeek:create';

		[$document] = $this->render();
		$this->assertTrue(str_contains($document->saveHtml(), $urlCreate), 'la semaine courante n\'a pas de « Nouvelle récolte », le témoin ne prouve rien');

		[$document] = $this->render(TRUE, $weekNext);
		$this->assertFalse(str_contains($document->saveHtml(), $urlCreate), 'une semaine à venir porte « Nouvelle récolte »');

	}

	/**
	 * « Nouvelle récolte » s'ouvre sans curseur, comme « Nouvelle intervention » (retour 260) : ni dans « Série »,
	 * dont la liste s'ouvre au clic dans le champ, ni dans « Quantité récoltée » quand la série est posée,
	 * depuis l'écran des récoltes comme depuis une carte de récolte (12/10 : « retire aussi le curseur
	 * de nouvelle récolte »). Au doigt, le clavier couvrirait le panneau. Échap, liste ouverte, ne ferme que la
	 * liste, comme dans les panneaux du Jour (`TaskActionTest::testEscape()`).
	 */
	public function testCreateCursor(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		foreach([
			'sans série' => [],
			'avec une série' => ['cultivation' => $eCultivation['id']],
			'depuis une carte' => ['origin' => 'semaine', 'task' => $eTask['id'], 'cultivation' => $eCultivation['id'], 'unplanned' => '', 'folded' => ''],
		] as $case => $input) {

			[$data, , $action] = $this->call('create', $input, 'GET');

			if($this->assertTrue($action instanceof \ViewAction, 'le panneau ne s\'ouvre pas, '.$case) === FALSE) {
				continue;
			}

			$panel = new \planning\HarvestEntryUi()->create($data->eStructure, $data->week, $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, $data->open, $data->taskWeek);

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

			if($this->assertTrue($data->eCultivation->empty() or $document->querySelector('input[name="harvestMore"]') !== NULL, '« Quantité récoltée » manque au panneau, '.$case) === FALSE) {
				continue;
			}

			$focused = [];

			foreach($document->querySelectorAll('[onrender*="focus()"], [autofocus]') as $element) {
				$focused[] = $element->getAttribute('name');
			}

			$this->assertEquals([], $focused, 'le panneau pose le curseur dans un champ à son ouverture, '.$case);

			// Depuis une carte, la série est en vignette et ne se change pas.
			if($data->taskWeek === []) {

				$field = $document->querySelector('#planning-harvest-create-cultivation input[data-autocomplete-field]');

				if($this->assertTrue($field !== NULL, '« Série » n\'est pas une autocomplétion, '.$case) === FALSE) {
					continue;
				}

				$this->assertEquals('PlanningAutocomplete.start(this);', $field->getAttribute('onrender'), '« Série » prend le curseur à l\'ouverture (retour 260), ou ne démarre pas comme les autres panneaux, '.$case);
				$this->assertTrue($field->hasAttribute('data-planning-task-escape'), 'Échap, liste ouverte, ferme le panneau « Nouvelle récolte » au lieu de la liste, '.$case);

			}

		}

	}

	/**
	 * Le panneau n'a plus de case « Récolte de la semaine terminée » (retour 214) : la règle commune décide
	 * seule, et un `done` encore posté n'y change rien. Une quantité termine l'intervention à faire.
	 */
	public function testPanelWithoutDone(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

		$panel = new HarvestEntryUi()->create($this->eStructure, \Clock::week(), $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		if($this->assertDecorPresent($data->eTask->notEmpty(), 'le panneau ouvert sur une intervention à faire de la semaine') === FALSE) {
			return;
		}

		$this->assertEquals(0, $document->querySelectorAll('input[name="done"]')->length, 'Nouvelle récolte porte encore la case « Récolte de la semaine terminée »');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '1', 'done' => 0, 'panel' => 1]);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals(\series\Task::DONE, $this->getTasks($eCultivation)[$eTask['id']]['status'], 'un `done` posté garde la main sur la règle commune');

	}

	/**
	 * « Nouvelle récolte » s'ouvre aussi depuis le menu « Saisir » de la barre du téléphone, sur un écran
	 * où la liste des récoltes n'est pas : la saisie le dit alors par un message, sans recharger. Depuis
	 * l'écran des récoltes, la ligne qui change suffit.
	 */
	public function testPanelFromMenuSaysItIsSaved(): void {

		$eCultivation = $this->createCultivation();

		[, $instructions, $action] = $this->call('doHarvest', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '1', 'panel' => 1, 'origin' => 'menu'], 'POST');
		$this->assertTrue($action instanceof \ViewAction, 'la saisie depuis le menu est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE), 'La récolte est enregistrée.'), 'la saisie depuis le menu ne dit pas qu\'elle est enregistrée');

		// Le menu ne sait rien de l'écran derrière lui : la réponse ne vise que la liste de sa semaine et de la
		// forme de la saisie rapide, et ne redessine donc pas celle d'une autre semaine ou de l'autre forme.
		$list = '#planning-harvest-list[data-week="'.\Clock::week().'"][data-quick="0"][data-sales="0"]';
		$selectors = array_column(array_filter($instructions, fn($instruction) => $instruction[0] !== 'js' and str_contains($instruction[1], 'planning-harvest-') and str_contains($instruction[1], 'panel') === FALSE), 1);

		$this->assertTrue($selectors !== [], 'la saisie depuis le menu ne redessine rien');
		$this->assertEquals([], array_values(array_filter($selectors, fn($selector) => str_starts_with($selector, $list) === FALSE)), 'la saisie depuis le menu vise une liste qui n\'est ni de sa semaine ni de sa forme');

		[, $instructions] = $this->call('doHarvest', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '1', 'panel' => 1], 'POST');
		$this->assertFalse(str_contains(json_encode($instructions, JSON_UNESCAPED_UNICODE), 'La récolte est enregistrée.'), 'la saisie depuis l\'écran des récoltes porte le message du menu');

	}

	/**
	 * « Nouvelle récolte » ajoute, comme le tiroir en ajout : l'addon de tête de son champ (R-Retours-0810, 235)
	 * ne se marque pas comme l'unité, que les autres unités de son choix annoncent une intervention neuve ou
	 * désignent celle de la semaine.
	 */
	public function testPanelSigned(): void {

		$eCultivation = $this->createCultivation();

		foreach(['une seule unité récoltée', 'plusieurs unités récoltées'] as $case) {

			if($case === 'plusieurs unités récoltées') {
				$this->createTaskHarvestInUnit($eCultivation, \series\Task::BUNCH, week_date_day(\Clock::week(), 2));
				$this->createTaskHarvestInUnit($eCultivation, \series\Task::KG, week_date_day(\Clock::week(), 1));
			}

			[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

			$panel = new HarvestEntryUi()->create($this->eStructure, \Clock::week(), $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, taskWeek: $data->taskWeek, tasksByUnit: $data->tasksByUnit);
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

			$tasksWeek = array_filter($data->tasksByUnit, fn(\series\Task $eTask) => $eTask->notEmpty());

			if($this->assertDecorPresent(($case === 'plusieurs unités récoltées') === ($tasksWeek !== []), $case.' : le panneau désigne autant d\'interventions que le cas en pose') === FALSE) {
				return;
			}

			$sign = $document->querySelector('.planning-harvest-entry-quantity .input-group > .input-group-addon:first-child');

			// Le choix d'une variété réécrit l'addon marqué de l'unité (`PlanningHarvestChoice`) : le « + » ne l'est pas.
			$this->assertFalse($sign?->hasAttribute('data-planning-harvest-unit') ?? TRUE, $case.' : le « + » se marque comme l\'unité, le choix d\'une variété l\'écraserait');

			// L'unité suit le champ, soit en choix des caisses, soit en addon marqué que le choix d'une variété réécrit.
			$unit = $document->querySelector('.planning-harvest-entry-quantity .input-group > :last-child');

			$this->assertTrue(
				$unit?->hasAttribute('data-planning-harvest-crate') or $unit?->hasAttribute('data-planning-harvest-unit'),
				$case.' : l\'unité qui suit le champ n\'est ni le choix des caisses ni l\'addon marqué'
			);

		}

	}

	/**
	 * Une culture récoltée en bottes, sans autre unité dans la semaine, propose quand même les deux autres
	 * (retour 319), qui se désignent par leur unité faute d'intervention, et ne propose pas de caisses. Le champ
	 * caché `harvestUnit` s'ouvre éteint à côté de `task` : l'unité de la culture ne poste ni l'un ni l'autre.
	 */
	public function testPanelUnitOther(): void {

		$eCultivation = $this->createCultivation();
		$eCultivation['mainUnit'] = \series\Cultivation::BUNCH;
		$eCultivation['bunchWeight'] = NULL;

		// Sans build(), qui vérifierait l'unité postée depuis le formulaire de la culture : le décor veut une
		// culture en bottes, que la fixture de série ne pose pas.
		\series\CultivationLib::update($eCultivation, ['mainUnit', 'bunchWeight']);

		[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

		if($this->assertDecorPresent($data->unit === \series\Task::BUNCH, 'la culture du décor se récolte en bottes') === FALSE) {
			return;
		}

		$this->assertEquals([\series\Task::KG, \series\Task::UNIT], array_keys($data->tasksByUnit), 'le panneau ne reçoit pas les deux autres unités d\'une récolte');

		$panel = new HarvestEntryUi()->create($this->eStructure, \Clock::week(), $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, taskWeek: $data->taskWeek, tasksByUnit: $data->tasksByUnit);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		$options = [];

		foreach($document->querySelectorAll('.planning-harvest-entry-quantity .input-group > select[name="crate"]:last-child option:not([data-planning-harvest-couple])') as $option) {
			$options[] = [$option->getAttribute('value'), $option->hasAttribute('selected'), $option->getAttribute('data-planning-harvest-other'), $option->hasAttribute('data-planning-harvest-task')];
		}

		$this->assertEquals([
			['0', TRUE, NULL, FALSE],
			['0', FALSE, \series\Task::KG, FALSE],
			['0', FALSE, \series\Task::UNIT, FALSE],
		], $options, 'le choix qui suit le champ ne range pas les bottes puis les deux autres unités, désignées par leur unité et sans caisses');

		foreach(['task', 'harvestUnit'] as $name) {

			$field = $document->querySelector('input[type="hidden"][name="'.$name.'"]');

			$this->assertTrue(($field !== NULL and $field->hasAttribute('disabled') and ($field->getAttribute('value') ?? '') === ''), 'le champ caché « '.$name.' » ne s\'ouvre pas éteint et vide');

		}

	}

	/**
	 * Une culture d'une autre structure ne se récolte pas depuis la page de celle-ci, et rien n'est
	 * écrit chez elle.
	 */
	public function testForeignStructure(): void {

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

		$harvested = $this->getHarvested($eCultivationForeign);

		$action = $this->post($eCultivationForeign, ['mode' => 'add', 'harvestMore' => '1']);

		$this->assertFalse($action instanceof \ViewAction, 'une culture d\'une autre structure se récolte');
		$this->assertEquals($harvested, $this->getHarvested($eCultivationForeign), 'une récolte est écrite chez une autre structure');

	}

	/**
	 * La recherche de « Nouvelle récolte » trouve une culture par le début d'un mot de son espèce ou
	 * de sa série, et écarte les séries closes.
	 */
	public function testQuery(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série cherchable '.uniqid()));

		$eSeriesClosed = $this->createSeries('Série cherchable close');
		$eCultivationClosed = $this->createCultivation($eSeriesClosed);
		$this->closeSeries($eSeriesClosed);

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, \Clock::week(), 'cherchab');
		$this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la recherche ne trouve pas la série par un début de mot');
		$this->assertFalse($cCultivation->offsetExists($eCultivationClosed['id']), 'la recherche trouve une série close');

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, \Clock::week(), 'décor série');
		$this->assertTrue($cCultivation->offsetExists($eCultivation['id']), 'la recherche ne trouve pas la culture par le nom de son espèce');

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, \Clock::week(), 'erchable');
		$this->assertFalse($cCultivation->offsetExists($eCultivation['id']), 'la recherche trouve un milieu de mot');

	}

	/**
	 * La feuille du tactile (L3b) : `open` avec `sheet=1`, que `planning.js` pose au tactile, la pose sur le
	 * `body` au lieu du tiroir. Elle renvoie le jour ouvert de l'écran, et non la date saisie, porte la propriété de son mode, et son temps se
	 * cumule au champ par pas, en lecture seule (le clavier sert au pavé), le total partant en heures par
	 * un champ caché désactivé tant qu'il vaut zéro, sauf en remplacement, où il remplace le temps du jour et
	 * part toujours. Le tiroir porte le même champ, qui se tape.
	 */
	public function testSheet(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);

		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), $monday), 2.0, $monday);

		$cases = [
			'ajout' => [['mode' => 'add', 'sheet' => 1], 'harvestMore'],
			'remplacement' => [['mode' => 'replace', 'sheet' => 1, 'date' => $monday, 'open' => $monday], 'harvestDay'],
		];

		foreach($cases as $case => [$input, $property]) {

			$input += ['cultivation' => $eCultivation['id'], 'date' => \Clock::date()];

			[$data, $instructions, $action] = $this->call('open', $input, 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la feuille ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$this->assertTrue($data->sheet, $case.' : le tiroir s\'ouvre à la place de la feuille');

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR);
			$sheet = $document->getElementById('planning-harvest-sheet');

			if($this->assertTrue($sheet !== NULL, $case.' : la réponse ne pose pas la feuille sur le body') === FALSE) {
				continue;
			}

			// Sans jour posté, la semaine courante s'ouvre sur aujourd'hui.
			$this->assertEquals($input['open'] ?? \Clock::date(), $sheet->querySelector('input[name="open"]')?->getAttribute('value'), $case.' : la feuille ne renvoie pas le jour ouvert');
			$this->assertEquals('1', $sheet->querySelector('input[name="sheet"]')?->getAttribute('value'), $case.' : la feuille ne se nomme pas dans ce qu\'elle poste');
			$this->assertEquals(['decimal'], array_map(fn($field) => $field->getAttribute('inputmode'), iterator_to_array($sheet->querySelectorAll('input[name="'.$property.'"]'))), $case.' : la feuille ne poste pas '.$property.' en un seul champ, au clavier décimal du téléphone');
			// Un remplacement remplace aussi le temps (R-Retours-0810, 219) : sa durée part toujours.
			if($input['mode'] === 'replace') {
				$this->assertTrue($sheet->querySelector('input[type="hidden"][name="time['.$data->eUserOnline['id'].']"]:not([disabled])') !== NULL, $case.' : le temps de la feuille ne part pas, qu\'il remplace');
			} else {
				$this->assertTrue($sheet->querySelector('input[type="hidden"][name="time['.$data->eUserOnline['id'].']"][disabled]') !== NULL, $case.' : le temps de la feuille ne part pas par un champ caché désactivé');
			}
			$this->assertSheetPeople($sheet, $data->eUserOnline, $case, menu: ($data->cUserTime->count() > 1));

		}

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add'], 'GET');

		$drawer = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']).'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($drawer->querySelector('.planning-harvest-time > .planning-duration-step-window.planning-duration-step-inline.is-open > .planning-duration-step') !== NULL, 'le temps du tiroir n\'a pas ses pas ouverts d\'office (R-Retours-0810, 208, lot BW)');
		$this->assertTrue($drawer->querySelector('.planning-harvest-time :is(.planning-duration-tiled, [data-planning-time-hour], [data-planning-time-minute])') === NULL, 'le temps du tiroir garde les pastilles d\'heures et de minutes');
		$this->assertTrue($drawer->querySelector('input[type="hidden"][name="time['.$this->getMember()['id'].']"][disabled]') !== NULL, 'le temps du tiroir ne part pas par un champ caché désactivé');
		$this->assertTrue($drawer->querySelector('input[type="radio"][name="time"]') === NULL, 'le tiroir garde les durées au choix');

		[$data, , $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add'], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'le tiroir ne s\'ouvre pas');
		$this->assertFalse($data->sheet, 'une ouverture sans feuille demandée ouvre la feuille');

	}

	/**
	 * L'ancienne adresse du Jour (R143) mène à la semaine de la date, ce jour ouvert, y compris sur une
	 * autre semaine que la courante ; sans date, à la semaine courante, sans jour imposé. La redirection
	 * est temporaire : sans date, la cible change chaque semaine.
	 */
	public function testDayRedirect(): void {

		$weekPrevious = toWeek(new \DateTime(\Clock::date())->modify('-7 days')->format('Y-m-d'));
		$tuesdayPrevious = week_date_day($weekPrevious, 2);

		$cases = [
			'un jour de la semaine' => [week_date_day(\Clock::week(), 1), HarvestWeekUi::url($this->eStructure).'?open='.week_date_day(\Clock::week(), 1)],
			'un jour d\'une semaine passée' => [$tuesdayPrevious, HarvestWeekUi::url($this->eStructure, $weekPrevious).'?open='.$tuesdayPrevious],
			'sans date' => [NULL, HarvestWeekUi::url($this->eStructure)],
		];

		foreach($cases as $case => [$date, $expected]) {

			$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/recoltes/jour'.($date === NULL ? '' : '/'.$date), [], 'GET');

			if($this->assertTrue($action instanceof \RedirectAction, $case.' : l\'adresse du jour ne redirige pas') === FALSE) {
				continue;
			}

			$this->assertEquals($expected, new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action), $case.' : la redirection ne mène pas à la semaine de la date');
			$this->assertEquals('HTTP/1.1 302 Found', new \ReflectionProperty(\RedirectAction::class, 'httpStatus')->getValue($action), $case.' : la redirection se mémoriserait');

		}

		// Le lundi est le jour ouvert en arrivant sur une semaine passée : le mardi prouve que `open` est lu.
		[, $data] = $this->render(week: $weekPrevious, open: $tuesdayPrevious);

		$this->assertEquals($tuesdayPrevious, array_find_key($data->days, fn($day) => $day['open']), 'la semaine n\'ouvre pas le jour de l\'ancienne adresse');

	}

	/**
	 * Le défaut que la garde de L3a corrige : une intervention de la semaine ventilée par variété
	 * recevait la saisie du planning, et ses kg allaient à cette variété sans que personne l'ait choisie.
	 * La saisie ne vise plus qu'une intervention sans variété ni calibre, et en crée une à défaut ; la
	 * ventilée reste à faire, vide.
	 *
	 * Sur la règle d'avant la garde, `getHarvestForWeek()` retenait la ventilée, première à faire de la
	 * semaine : aucune intervention neuve ne naissait, et la première assertion tombait.
	 */
	public function testVentilatedNotTaken(): void {

		$eCultivation = $this->createCultivation();

		$eTaskVentilated = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->ventilate($eTaskVentilated, ['variety' => $this->createVariety($eCultivation['plant'])]);

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);
		$cTaskNew = $cTask->find(fn($eTask) => $eTask['id'] !== $eTaskVentilated['id']);

		if($this->assertEquals(1, $cTaskNew->count(), 'la saisie ne crée pas d\'intervention et complète la ventilée') === FALSE) {
			return;
		}

		$this->assertEquals(NULL, $cTask[$eTaskVentilated['id']]['harvest'], 'les kg vont à la variété de l\'intervention ventilée');
		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskVentilated['id']]['status'], 'l\'intervention ventilée se termine');
		$this->assertTrue($cTaskNew->first()['variety']->empty(), 'l\'intervention créée porte une variété');
		$this->assertTrue($cTaskNew->first()['harvestSize']->empty(), 'l\'intervention créée porte un calibre');
		$this->assertEquals(2.0, $cTaskNew->first()['harvest'], 'l\'intervention créée ne porte pas la récolte');

	}

	/**
	 * Un remplacement calcule son écart sur tout le récolté du jour de la culture et le pose sur une
	 * seule intervention : quand une part du jour est ventilée, il retirerait ou ajouterait à la
	 * mauvaise. Il est refusé, par son code, et rien n'est écrit. Refusé et non borné : borner écrirait
	 * un chiffre que personne n'a tapé (V-Planning, 2026-09-30).
	 */
	public function testReplaceRefusedWhenVentilated(): void {

		$eCultivation = $this->createCultivation();

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $this->createVariety($eCultivation['plant'])]);
		$this->createHarvest($eTaskVentilated, 3.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '1']);

		$this->assertTrue(in_array('Task::harvestDay.ventilated', $this->getFails($action), TRUE), 'le remplacement sur un jour ventilé n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => ['kg' => 3.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');
		$this->assertEquals([$eTaskVentilated['id']], $this->getTasks($eCultivation)->getIds(), 'le refus a créé une intervention');

	}

	/**
	 * Une quantité négative sans couple, sur un jour dont une part est ventilée, se refuse comme le
	 * remplacement : la baisse ne peut pas se poser sur une seule intervention.
	 */
	public function testNegativeRefusedWhenVentilated(): void {

		$eCultivation = $this->createCultivation();

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $this->createVariety($eCultivation['plant'])]);
		$this->createHarvest($eTaskVentilated, 3.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '-1']);

		$this->assertEquals(['Task::harvestMore.ventilated'], $this->getFails($action), 'une quantité négative sur un jour ventilé n\'est pas refusée par son code');
		$this->assertEquals([\Clock::date() => ['kg' => 3.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');

	}

	/**
	 * Le refus porte sur ce que la part ventilée pèse, pas sur son existence : ramenée à zéro dans la
	 * journée, elle ne fausse plus l'écart du remplacement, qui passe sur une intervention sans variété.
	 */
	public function testReplaceAllowedWhenVentilatedIsZero(): void {

		$eCultivation = $this->createCultivation();

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $this->createVariety($eCultivation['plant'])]);
		$this->createHarvest($eTaskVentilated, 3.0, \Clock::date());
		$this->createHarvest($eTaskVentilated, -3.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement est refusé sur une part ventilée revenue à zéro : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['kg' => 2.0]], $this->getHarvested($eCultivation), 'le remplacement n\'écrit pas la quantité saisie');
		$this->assertEquals(0.0, $this->getTasks($eCultivation)[$eTaskVentilated['id']]['harvest'], 'le remplacement touche à l\'intervention ventilée');

	}

	/**
	 * Le refus se borne à l'unité saisie, où se calcule l'écart du remplacement (arbitrage de
	 * l'architecte, 2026-09-30) : une part ventilée comptée en pièces ne fausse pas un remplacement
	 * en kg, qui passe sur une intervention sans variété.
	 */
	public function testReplaceAllowedWhenVentilatedInOtherUnit(): void {

		$eCultivation = $this->createCultivation();

		$eTaskVentilated = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $this->createVariety($eCultivation['plant'])]);
		$this->createHarvest($eTaskVentilated, 10.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en kg est refusé à cause d\'une part ventilée en pièces : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['unit' => 10.0, 'kg' => 2.0]], $this->getHarvested($eCultivation), 'le remplacement en kg n\'écrit pas la quantité saisie, ou touche aux pièces');

	}

	/**
	 * Quand la culture n'a que du ventilé, la faite de la semaine par calibre comme la plus ancienne en
	 * retard par variété, la saisie crée une intervention sans variété ni calibre et laisse les deux
	 * autres telles qu'elles sont.
	 */
	public function testCreatesWhenOnlyVentilated(): void {

		$eCultivation = $this->createCultivation();

		$eTaskSize = $this->createTaskHarvest($eCultivation, \Clock::week(), week_date_day(\Clock::week(), 1));
		$this->ventilate($eTaskSize, ['harvestSize' => $this->createSize($eCultivation['plant'])]);

		$eTaskDelayed = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 1 WEEK')));
		$this->ventilate($eTaskDelayed, ['variety' => $this->createVariety($eCultivation['plant'])]);

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '4']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);
		$cTaskNew = $cTask->find(fn($eTask) => in_array($eTask['id'], [$eTaskSize['id'], $eTaskDelayed['id']], TRUE) === FALSE);

		if($this->assertEquals(1, $cTaskNew->count(), 'la saisie ne crée pas d\'intervention sans variété ni calibre') === FALSE) {
			return;
		}

		$this->assertTrue($cTaskNew->first()['variety']->empty() and $cTaskNew->first()['harvestSize']->empty(), 'l\'intervention créée est ventilée');
		$this->assertEquals(4.0, $cTaskNew->first()['harvest'], 'l\'intervention créée ne porte pas la récolte');
		$this->assertEquals(NULL, $cTask[$eTaskSize['id']]['harvest'], 'la saisie va à l\'intervention ventilée par calibre');
		$this->assertEquals(\series\Task::TODO, $cTask[$eTaskDelayed['id']]['status'], 'la saisie termine l\'intervention en retard ventilée par variété');

	}

	/**
	 * Remplacer une part ne touche qu'elle (L3c) : la part « Non spécifiée » remplacée par 80 kg passe de
	 * 90 à 80, la part de la variété garde ses 4 kg, et le jour vaut 84. Le couple posté vide désigne la
	 * part sans variété ni calibre.
	 *
	 * Sur la garde de L3a, qui ne connaît pas le couple, le jour porte du ventilé et le remplacement est
	 * refusé par `Task::harvestDay.ventilated` : le test tombe sur sa première assertion.
	 */
	public function testReplaceBoundedToCouple(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 90.0, \Clock::date());

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $eVariety]);
		$this->createHarvest($eTaskVentilated, 4.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '80', 'variety' => '', 'harvestSize' => '']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement de la part « Non spécifiée » est refusé : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([\Clock::date() => ['kg' => 84.0]], $this->getHarvested($eCultivation), 'le jour ne vaut pas la part remplacée plus la part de la variété');
		$this->assertEquals(80.0, $cTask[$eTask['id']]['harvest'], 'la part « Non spécifiée » ne vaut pas la quantité saisie');
		$this->assertEquals(4.0, $cTask[$eTaskVentilated['id']]['harvest'], 'le remplacement touche à la part de la variété');
		$this->assertEquals([$eTask['id'], $eTaskVentilated['id']], $cTask->getIds(), 'le remplacement crée une intervention');

	}

	/**
	 * La première saisie d'un couple crée son intervention, faite, avec la variété et le calibre choisis ;
	 * la seconde la complète au lieu d'en créer une autre.
	 */
	public function testAddToCouple(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);
		$eSize = $this->createSize($eCultivation['plant']);

		$couple = ['variety' => $eVariety['id'], 'harvestSize' => $eSize['id']];

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2'] + $couple);
		$this->assertTrue($action instanceof \ViewAction, 'la première saisie du couple est refusée : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3'] + $couple);
		$this->assertTrue($action instanceof \ViewAction, 'la seconde saisie du couple est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		if($this->assertEquals(1, $cTask->count(), 'la seconde saisie ne complète pas l\'intervention du couple') === FALSE) {
			return;
		}

		$eTask = $cTask->first();

		$this->assertEquals($eVariety['id'], $eTask['variety']['id'], 'l\'intervention créée ne porte pas la variété choisie');
		$this->assertEquals($eSize['id'], $eTask['harvestSize']['id'], 'l\'intervention créée ne porte pas le calibre choisi');
		$this->assertEquals(5.0, $eTask['harvest'], 'l\'intervention du couple ne porte pas les deux saisies');
		$this->assertEquals(\series\Task::DONE, $eTask['status'], 'l\'intervention du couple n\'est pas faite');

	}

	/**
	 * Une intervention ventilée à faire de la semaine se termine par une saisie de son couple, comme une
	 * intervention sans variété se termine par la saisie de la culture : la garde de L3a ne la laissait
	 * jamais recevoir de récolte.
	 */
	public function testCompletesVentilatedTodo(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTaskVentilated = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->ventilate($eTaskVentilated, ['variety' => $eVariety]);

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'variety' => $eVariety['id'], 'harvestSize' => '']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du couple est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([$eTaskVentilated['id']], $cTask->getIds(), 'la saisie du couple crée une intervention à côté de celle à faire');
		$this->assertEquals(2.0, $cTask[$eTaskVentilated['id']]['harvest'], 'l\'intervention à faire du couple ne reçoit pas la récolte');
		$this->assertEquals(\series\Task::DONE, $cTask[$eTaskVentilated['id']]['status'], 'l\'intervention à faire du couple ne se termine pas');

	}

	/**
	 * Le couple passe par le `build()` de l'intervention : une variété hors de la culture et un calibre
	 * d'une autre espèce sont refusés par leur code, et rien n'est écrit.
	 */
	public function testCoupleForeign(): void {

		$eCultivation = $this->createCultivation();

		$cases = [
			'Task::variety.check' => ['variety' => $this->createVariety($eCultivation['plant'])['id'], 'harvestSize' => ''],
			'Task::harvestSize.action' => ['variety' => '', 'harvestSize' => $this->createSize($this->createPlant())['id']],
		];

		foreach($cases as $fail => $couple) {

			$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2'] + $couple);

			$this->assertTrue(in_array($fail, $this->getFails($action), TRUE), 'le couple étranger n\'est pas refusé par '.$fail.' : '.implode(', ', $this->getFails($action)));

		}

		$this->assertEquals([], $this->getHarvested($eCultivation), 'un couple refusé a écrit une récolte');
		$this->assertEquals(0, $this->getTasks($eCultivation)->count(), 'un couple refusé a créé une intervention');

	}

	/**
	 * L'unité d'un couple vient de l'intervention où la saisie l'écrira : celle de la semaine, ou à défaut
	 * la plus ancienne en retard, comptée ici en pièces quand la culture l'est en kg.
	 */
	public function testCoupleUnitFromDelayed(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTaskDelayed = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(\Clock::shift('- 1 WEEK')));
		$this->ventilate($eTaskDelayed, ['variety' => $eVariety, 'harvestUnit' => \series\Task::UNIT]);

		$units = \series\TaskLib::getHarvestUnitsForWeek($this->eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), \Clock::week());
		$key = \series\CultivationLib::getHarvestPartKey($eVariety, new \plant\Size());

		$this->assertEquals(\series\Task::UNIT, $units[$eCultivation['id']][$key] ?? NULL, 'l\'unité du couple n\'est pas celle de son intervention en retard');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3', 'variety' => $eVariety['id'], 'harvestSize' => '']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie du couple est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['unit' => 3.0]], $this->getHarvested($eCultivation), 'la saisie du couple ne va pas à son intervention en retard, dans son unité');

	}

	/**
	 * Remplacer la part d'une variété ne touche qu'elle, la part « Non spécifiée » gardant ses 90 kg.
	 */
	public function testReplacePart(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 90.0, \Clock::date());

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $eVariety]);
		$this->createHarvest($eTaskVentilated, 4.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '6', 'variety' => $eVariety['id'], 'harvestSize' => '']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement de la part de la variété est refusé : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals([\Clock::date() => ['kg' => 96.0]], $this->getHarvested($eCultivation), 'le jour ne vaut pas la part remplacée plus la part « Non spécifiée »');
		$this->assertEquals(6.0, $cTask[$eTaskVentilated['id']]['harvest'], 'la part de la variété ne vaut pas la quantité saisie');
		$this->assertEquals(90.0, $cTask[$eTask['id']]['harvest'], 'le remplacement touche à la part « Non spécifiée »');

	}

	/**
	 * Un remplacement sans couple porte sur le jour entier : sur 90 kg sans variété et 4 kg d'une variété,
	 * remplacer par 98 ajouterait 4 kg à la part sans variété, un chiffre que personne n'a tapé. Il reste
	 * refusé tant que le jour porte du ventilé, et rien n'est écrit.
	 */
	public function testReplaceHeadRefused(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 90.0, \Clock::date());

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $eVariety]);
		$this->createHarvest($eTaskVentilated, 4.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '98']);

		$this->assertTrue(in_array('Task::harvestDay.ventilated', $this->getFails($action), TRUE), 'le remplacement du jour entier n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => ['kg' => 94.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');

	}

	/**
	 * La case qu'une saisie change s'éclaire (retour 300) : la réponse nomme la quantité du jour écrit, sur la
	 * ligne de sa culture, et la ligne qu'elle rend ne porte cette case qu'une fois. La feuille du tactile et
	 * le panneau « Nouvelle récolte » éclairent la même case.
	 */
	public function testWrittenAdd(): void {

		$eCultivation = $this->createCultivation();

		$cases = [
			'tiroir' => [],
			'feuille du tactile' => ['sheet' => 1],
			'panneau' => ['panel' => 1],
			'jour passé ouvert' => ['date' => week_date_day(\Clock::week(), 1), 'open' => week_date_day(\Clock::week(), 1)],
		];

		foreach($cases as $case => $input) {

			[, $instructions, $action] = $this->call('doHarvest', $input + [
				'cultivation' => $eCultivation['id'],
				'date' => \Clock::date(),
				'mode' => 'add',
				'harvestMore' => '12',
			], 'POST');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$date = $input['date'] ?? \Clock::date();
			$nodes = $this->getHighlighted($instructions);

			$this->assertEquals(1, count($nodes), $case.' : la réponse n\'éclaire pas une case, et une seule');
			$this->assertEquals([['planning-harvest-'.$eCultivation['id'], $date]], array_map(fn($node) => [$node->closest('[id^="planning-harvest-"]')?->getAttribute('id'), $node->getAttribute('data-date')], $nodes), $case.' : la case éclairée n\'est pas la quantité du jour écrit, sur la ligne de la culture');

		}

	}

	/**
	 * Rien ne s'éclaire quand la case montre après la saisie ce qu'elle montrait avant : un remplacement par la
	 * même quantité, et un écart que l'arrondi au dixième annule. Un temps qui change éclaire la case, qui le dit
	 * sous sa quantité (`HarvestTimeGridTest::testWriteLightsCase()`).
	 */
	public function testWrittenNone(): void {

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 2.0, \Clock::date());

		$cases = [
			'remplacement par la même quantité' => ['mode' => 'replace', 'harvestDay' => '2'],
			'écart que l\'arrondi annule' => ['mode' => 'replace', 'harvestDay' => '2.02'],
		];

		foreach($cases as $case => $input) {

			[, $instructions, $action] = $this->call('doHarvest', $input + [
				'cultivation' => $eCultivation['id'],
				'date' => \Clock::date(),
			], 'POST');

			$this->assertTrue($action instanceof \ViewAction, $case.' : la saisie est refusée : '.implode(', ', $this->getFails($action)));
			$this->assertEquals([], $this->getJs($instructions, 'highlight'), $case.' : la réponse éclaire une case qui n\'a pas changé');

		}

	}

	/**
	 * En saisie rapide, la case éclairée est celle du jour écrit, qui porte sa quantité et, à côté, le « + » qui
	 * ouvre le tiroir en ajout sur la récolte du jour, comme sans saisie rapide (V52).
	 */
	public function testWrittenQuick(): void {

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 1.0, \Clock::date());

		$this->setQuick(TRUE);

		[, $instructions, $action] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '4',
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'le remplacement en saisie rapide est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$nodes = $this->getHighlighted($instructions);

		if($this->assertEquals(1, count($nodes), 'la réponse en saisie rapide n\'éclaire pas une case, et une seule') === FALSE) {
			return;
		}

		$this->assertEquals(['planning-harvest-day-quantities', \Clock::date()], [$nodes[0]->className, $nodes[0]->getAttribute('data-date')], 'en saisie rapide, la case éclairée n\'est pas la quantité du jour écrit');

		parse_str(parse_url($nodes[0]->nextElementSibling?->getAttribute('data-planning-harvest-open') ?? '', PHP_URL_QUERY) ?? '', $arguments);

		$this->assertEquals(['add', (string)$eCultivation['id'], \Clock::date()], [$arguments['mode'] ?? NULL, $arguments['cultivation'] ?? NULL, $arguments['date'] ?? NULL], 'en saisie rapide, la quantité du jour n\'a pas à côté d\'elle le « + » qui ajoute à sa récolte');

	}

	/**
	 * Une saisie faite dans la case d'une culture (`cell`, HN-B) ne redessine que la ligne de cette culture et
	 * l'intertitre de son espèce, chacun tel que l'écran entier le rend. Une culture qui ne sort pas cette
	 * semaine retombe sur la relecture des espèces voisines, comme une saisie du menu.
	 */
	public function testEntryCell(): void {

		$ePlant = $this->createPlant();

		$eCultivation = $this->createCultivation($this->createSeries('HN-B case '.uniqid()), $ePlant);
		$eCultivationOther = $this->createCultivation($this->createSeries('HN-B voisine '.uniqid()), $ePlant);

		foreach([$eCultivation, $eCultivationOther] as $eCultivationHarvested) {
			$this->createHarvest($this->createTaskHarvest($eCultivationHarvested, \Clock::week(), \Clock::date()), 1.0, \Clock::date());
		}

		$this->setQuick(TRUE);

		$input = [
			'cultivation' => $eCultivation['id'],
			'cell' => 1,
		];

		[$data, $instructions, $action] = $this->call('entry', $input, 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la relecture d\'une case est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$list = '#planning-harvest-list[data-week="'.\Clock::week().'"][data-quick="1"][data-sales="0"]';
		$row = $list.' #planning-harvest-'.$eCultivation['id'];
		$section = $list.' #planning-harvest-plant-'.$ePlant['id'];

		$this->assertEquals([$section, $row], array_keys($this->getOuterHtml($instructions)), 'la case ne redessine pas l\'intertitre de son espèce et sa seule ligne');

		[$document] = $this->render(TRUE);

		foreach($this->getOuterHtml($instructions) as $selector => $html) {

			$selectorNode = substr($selector, strpos($selector, ' ') + 1);
			$node = $document->querySelector($selectorNode);

			if($this->assertTrue($node !== NULL, 'l\'écran entier n\'a pas de nœud '.$selectorNode)) {
				$fragment = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR)->querySelector($selectorNode);
				$this->assertEquals($this->getOuter($node), $this->getOuter($fragment), 'la réponse ciblée ne redessine pas '.$selectorNode.' comme l\'écran entier');
			}

		}

		// Sans `cell`, la saisie vient du menu et l'espèce entière se repose.
		[$data] = $this->call('entry', array_diff_key($input, ['cell' => TRUE]), 'GET');
		$this->assertEquals([$ePlant['id']], $data->change['sections']['removed'], 'une saisie du menu ne repose pas l\'espèce entière');

		$eCultivationAway = $this->createCultivation($this->createSeries('HN-B absente '.uniqid()), $ePlant);

		if($this->assertDecorPresent(\planning\HarvestWeekLib::get($this->eStructure, \Clock::week())->offsetExists($eCultivationAway['id']) === FALSE, 'une culture qui ne sort pas cette semaine') === FALSE) {
			return;
		}

		[$data, , $action] = $this->call('entry', ['cultivation' => $eCultivationAway['id']] + $input, 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'une case d\'une culture absente de la semaine est refusée : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals([$ePlant['id']], $data->change['sections']['removed'], 'une case d\'une culture absente de la semaine ne relit pas les espèces voisines');
		}

	}

	/**
	 * La saisie d'un couple éclaire sa part, que la culture relue porte ; une culture sans part s'éclaire sur
	 * sa ligne, même quand la saisie a posté un couple vide. Les parts sont rendues dans la ligne de leur
	 * culture : la saisie de l'une n'éclaire jamais le même jour des autres.
	 */
	public function testWrittenPart(): void {

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$key = \series\CultivationLib::getHarvestPartKey($eVariety, new \plant\Size());

		$cases = [
			'couple vide sur une culture sans part' => [['variety' => '', 'harvestSize' => ''], 'planning-harvest-'.$eCultivation['id']],
			'couple d\'une variété' => [['variety' => $eVariety['id'], 'harvestSize' => ''], 'planning-harvest-'.$eCultivation['id'].'-'.$key],
		];

		foreach($cases as $case => [$input, $id]) {

			[, $instructions, $action] = $this->call('doHarvest', $input + [
				'cultivation' => $eCultivation['id'],
				'date' => \Clock::date(),
				'mode' => 'add',
				'harvestMore' => '3',
			], 'POST');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la saisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$this->assertEquals([$id], array_map(fn($node) => $node->closest('[id^="planning-harvest-"]')?->getAttribute('id'), $this->getHighlighted($instructions)), $case.' : la case éclairée n\'est pas celle de la ligne écrite, et elle seule');

		}

	}

	/**
	 * « Supprimer » du tiroir (retour 301) n'existe qu'en remplacement, avec sa confirmation. Il poste ce qui
	 * désigne la case, l'intervention que la case désigne et le couple d'une part, sous les noms que lit
	 * `Element.prototype.post()`, et rien du formulaire. Le tiroir se referme, en ajout comme en remplacement.
	 */
	public function testDrawerDelete(): void {

		['eCultivation' => $eCultivation, 'eTaskBunch' => $eTaskBunch, 'eTaskKg' => $eTaskKg, 'monday' => $monday, 'tuesday' => $tuesday] = $this->createBunchThenKg();

		$selector = 'a[data-ajax="/planning/harvestWeek:doHarvestDelete"][data-confirm]';

		$drawerAdd = $this->openDrawerOn($eCultivation, $monday, 'add');
		$drawerReplace = $this->openDrawerOn($eCultivation, $monday, 'replace', $eTaskBunch);

		if($this->assertTrue($drawerAdd !== NULL and $drawerReplace !== NULL, 'le tiroir ne s\'ouvre pas') === FALSE) {
			return;
		}

		foreach(['ajout' => $drawerAdd, 'remplacement' => $drawerReplace] as $case => $drawer) {
			$this->assertTrue($drawer->querySelector('[data-planning-harvest-close]') !== NULL, $case.' : le tiroir ne se referme pas');
		}

		$this->assertTrue($drawerAdd->querySelector('[data-ajax="/planning/harvestWeek:doHarvestDelete"]') === NULL, 'le tiroir d\'un ajout propose de supprimer');

		// Mardi n'est récolté qu'en bottes : le tiroir de l'intervention au kilo n'y a ni quantité ni temps à
		// lui, et « Supprimer » n'aurait rien à écrire.
		$drawerBare = $this->openDrawerOn($eCultivation, $tuesday, 'replace', $eTaskKg);

		if($this->assertTrue($drawerBare !== NULL, 'le tiroir d\'un jour sans saisie au kilo ne s\'ouvre pas en remplacement') === FALSE) {
			return;
		}

		$this->assertTrue($drawerBare->querySelector('[data-ajax="/planning/harvestWeek:doHarvestDelete"]') === NULL, 'le tiroir d\'un jour sans quantité ni temps propose de supprimer');

		// Le temps compte dans les deux sens : celui de l'intervention en bottes n'est pas au tiroir du kilo,
		// celui de l'intervention au kilo suffit à lui seul, sans quantité.
		$this->writeTime($eTaskBunch, $this->getMember(), $tuesday, 0.5);

		$drawerTimeOther = $this->openDrawerOn($eCultivation, $tuesday, 'replace', $eTaskKg);

		$this->writeTime($eTaskKg, $this->getMember(), $tuesday, 0.75);

		$drawerTimeOwn = $this->openDrawerOn($eCultivation, $tuesday, 'replace', $eTaskKg);

		if($this->assertTrue($drawerTimeOther !== NULL and $drawerTimeOwn !== NULL, 'le tiroir d\'un jour qui ne porte que du temps ne s\'ouvre pas en remplacement') === FALSE) {
			return;
		}

		$this->assertTrue($drawerTimeOther->querySelector('[data-ajax="/planning/harvestWeek:doHarvestDelete"]') === NULL, 'le tiroir propose de supprimer pour le temps d\'une autre intervention');
		$this->assertTrue($drawerTimeOwn->querySelector('[data-ajax="/planning/harvestWeek:doHarvestDelete"]') !== NULL, 'le tiroir ne propose pas de supprimer le temps de son intervention, seul ce jour-là');

		$link =$drawerReplace->querySelector($selector);

		if($this->assertTrue($link !== NULL, 'le tiroir d\'un remplacement n\'a pas « Supprimer », avec sa confirmation') === FALSE) {
			return;
		}

		$posted = [];

		foreach($link->attributes as $attribute) {
			if(str_starts_with($attribute->name, 'post-')) {
				$posted[$attribute->name] = $attribute->value;
			}
		}

		$this->assertEquals([
			'post-id' => (string)$this->eStructure['id'],
			'post-week' => \Clock::week(),
			'post-cultivation' => (string)$eCultivation['id'],
			'post-date' => $monday,
			'post-open' => $monday,
			'post-task' => (string)$eTaskBunch['id'],
		], $posted, '« Supprimer » ne poste pas la case, le jour ouvert et son intervention, et eux seuls');

		[$eCultivationPart, $eVariety] = $this->createCultivationPart();

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivationPart['id'], 'date' => \Clock::date(), 'mode' => 'replace', 'variety' => $eVariety['id'], 'harvestSize' => ''], 'GET');

		$link = $this->getDocument($instructions)->querySelector($selector);

		if($this->assertTrue($link !== NULL, 'le tiroir d\'une part n\'a pas « Supprimer »') === FALSE) {
			return;
		}

		$this->assertEquals([(string)$eVariety['id'], ''], [$link->getAttribute('post-variety'), $link->getAttribute('post-harvest-size')], '« Supprimer » d\'une part ne poste pas son couple');

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'mode' => 'replace', 'sheet' => 1], 'GET');

		$this->assertTrue($this->getDocument($instructions)->querySelector('[data-ajax="/planning/harvestWeek:doHarvestDelete"]') === NULL, 'la feuille du tactile propose de supprimer');

	}

	/**
	 * « Supprimer » vide le jour : la quantité tombe à 0, le temps de chaque personne que le tiroir montre
	 * ce jour-là aussi, et l'intervention, qui ne porte plus rien, part (GR). La réponse touche la ligne,
	 * qu'elle rend sans tiroir ou qu'elle retire quand la culture ne figurait dans la semaine que par cette
	 * récolte, et n'éclaire rien.
	 */
	public function testDelete(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 6.0, \Clock::date());

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 0.5);
		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 1.0);

		[$instructions, $action] = $this->delete($eCultivation);

		if($this->assertTrue($action instanceof \ViewAction, 'la suppression est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([], $this->getHarvested($eCultivation)[\Clock::date()] ?? [], 'la récolte du jour n\'est pas supprimée');
		$this->assertEquals([], array_filter($this->getTimeByUser($eTask)), 'le temps du jour que le tiroir montre n\'est pas supprimé');
		$this->assertEquals([], $this->getTasks($eCultivation)->getIds(), 'l\'intervention vidée reste au planning');

		$selectors = array_column(array_filter($instructions, fn($instruction) => $instruction[0] !== 'js'), 1);

		$list = '#planning-harvest-list[data-week="'.\Clock::week().'"][data-quick="0"][data-sales="0"]';

		$this->assertTrue(array_intersect([$list.' #planning-harvest-'.$eCultivation['id'], $list], $selectors) !== [], 'la réponse ne touche pas la ligne : '.implode(', ', $selectors));
		$this->assertTrue($this->getDocument($instructions)->querySelector('.planning-harvest-drawer') === NULL, 'la réponse garde le tiroir ouvert');
		$this->assertEquals([], $this->getJs($instructions, 'highlight'), 'la suppression éclaire une case vidée');

	}

	/**
	 * Le temps d'un autre jour reste : la suppression ne porte que sur le jour du tiroir.
	 */
	public function testDeleteOtherDay(): void {

		$monday = week_date_day(\Clock::week(), 1);

		if($this->assertDecorPresent($monday !== \Clock::date(), 'un jour passé dans la semaine, que le lundi n\'a pas') === FALSE) {
			return;
		}

		$eUserOnline = $this->getMember();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

		$this->createHarvest($eTask, 3.0, $monday);
		$this->createHarvest($eTask, 6.0, \Clock::date());

		$this->writeTime($eTask, $eUserOnline, $monday, 2.0);
		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 0.5);

		[, $action] = $this->delete($eCultivation);

		$this->assertTrue($action instanceof \ViewAction, 'la suppression est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => ['kg' => 3.0]], array_filter($this->getHarvested($eCultivation)), 'la suppression ne laisse pas la récolte du lundi, et elle seule');
		$this->assertEquals([$monday => 2.0], array_filter($this->getTimeByDate($eCultivation)), 'la suppression ne laisse pas le temps du lundi, et lui seul');

	}

	/**
	 * Le temps que le tiroir ne montre pas reste : un salarié n'écrit que le sien, et sa suppression laisse
	 * celui d'un collègue sur la même intervention.
	 */
	public function testDeleteTimeKept(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 6.0, \Clock::date());

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 0.5);
		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 1.0);

		$this->withRoleEmployee($eUserBruno, function() use($eCultivation, $eTask, $eUserOnline) {

			[, $action] = $this->delete($eCultivation);

			$this->assertTrue($action instanceof \ViewAction, 'la suppression d\'un salarié est refusée : '.implode(', ', $this->getFails($action)));
			$this->assertEquals([], $this->getHarvested($eCultivation)[\Clock::date()] ?? [], 'la récolte du jour n\'est pas supprimée');
			$this->assertEquals([$eUserOnline['id'] => 0.5], array_filter($this->getTimeByUser($eTask)), 'la suppression ne laisse pas le temps du collègue, et lui seul');

		});

	}

	/**
	 * La suppression d'une part ne vide que son couple, et celle du jour entier reste refusée tant que le jour
	 * porte du ventilé, comme son retrait.
	 */
	public function testDeletePart(): void {

		[$eCultivation, $eVariety] = $this->createCultivationPart();

		[, $action] = $this->delete($eCultivation);

		$this->assertTrue(in_array('Task::harvestMore.ventilated', $this->getFails($action), TRUE), 'la suppression du jour entier n\'est pas refusée par son code : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => ['kg' => 94.0]], $this->getHarvested($eCultivation), 'le refus a écrit une récolte');

		[, $action] = $this->delete($eCultivation, ['variety' => $eVariety['id'], 'harvestSize' => '']);

		$this->assertTrue($action instanceof \ViewAction, 'la suppression d\'une part est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => ['kg' => 90.0]], $this->getHarvested($eCultivation), 'la suppression d\'une part ne laisse pas le reste du jour, et lui seul');

	}

	/**
	 * Un jour récolté dans deux unités ne perd que celle de l'intervention que la case désigne.
	 */
	public function testDeleteUnit(): void {

		['eCultivation' => $eCultivation, 'eTaskBunch' => $eTaskBunch, 'monday' => $monday, 'tuesday' => $tuesday] = $this->createBunchThenKg();

		[, $action] = $this->delete($eCultivation, ['date' => $monday, 'task' => $eTaskBunch['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'la suppression des bottes du lundi est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => ['kg' => 1.0], $tuesday => ['bunch' => 1.0]], array_filter($this->getHarvested($eCultivation)), 'la suppression ne laisse pas les kilos du lundi et les bottes du mardi');

	}

	/**
	 * Un jour récolté sur deux interventions de la culture se vide sur les deux : le tiroir montre leur total,
	 * qu'un remplacement par 0 refuserait de poser sur une seule (`Task::harvestDates.negative`). Le temps que
	 * le tiroir ne montre pas, celui de l'autre intervention, reste, et elle avec lui ; la première, vidée, part.
	 */
	public function testDeleteTasks(): void {

		$eUserOnline = $this->getMember();

		$eCultivation = $this->createCultivation();
		$eTaskFirst = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$eTaskSecond = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

		$this->createHarvest($eTaskFirst, 2.0, \Clock::date());
		$this->createHarvest($eTaskSecond, 6.0, \Clock::date());

		$this->writeTime($eTaskFirst, $eUserOnline, \Clock::date(), 0.5);
		$this->writeTime($eTaskSecond, $eUserOnline, \Clock::date(), 1.0);

		[, $action] = $this->delete($eCultivation, ['task' => $eTaskFirst['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la suppression d\'un jour récolté sur deux interventions est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([], $this->getHarvested($eCultivation)[\Clock::date()] ?? [], 'la quantité du jour reste sur une des deux interventions');
		$this->assertEquals([], array_filter($this->getTimeByUser($eTaskFirst)), 'le temps que le tiroir montre n\'est pas supprimé');
		$this->assertEquals([$eUserOnline['id'] => 1.0], array_filter($this->getTimeByUser($eTaskSecond)), 'le temps de l\'autre intervention, que le tiroir ne montre pas, est parti');
		$this->assertEquals([$eTaskSecond['id']], $this->getTasks($eCultivation)->getIds(), 'la suppression n\'a pas gardé l\'intervention qui porte du temps, et elle seule');

	}

	/**
	 * Ce que la suppression refuse sans rien écrire : une culture d'une autre structure, une intervention
	 * qui n'est pas une récolte de la culture, un jour hors de la semaine, et une case sans intervention, où
	 * un remplacement par 0 en créerait une.
	 */
	public function testDeleteRefused(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 6.0, \Clock::date());

		$eCultivationOther = $this->createCultivation($this->createSeries('Série voisine'));
		$eTaskOther = $this->createTaskHarvest($eCultivationOther, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskOther, 5.0, \Clock::date());

		$eCultivationEmpty = $this->createCultivation($this->createSeries('Série sans récolte'));

		[, $action] = $this->delete($eCultivation, ['task' => $eTaskOther['id']]);
		$this->assertEquals(['Task::cultivation.harvest'], $this->getFails($action), 'la récolte d\'une autre culture n\'est pas refusée par son code');

		[, $action] = $this->delete($eCultivation, ['date' => date('Y-m-d', strtotime(\Clock::date().' - 14 DAY'))]);
		$this->assertEquals(['Timesheet::date.week'], $this->getFails($action), 'un jour hors de la semaine n\'est pas refusé par son code');

		[, $action] = $this->delete($eCultivationEmpty);
		$this->assertTrue($action instanceof \NotExpectedAction, 'une case sans intervention se supprime');
		$this->assertEquals(0, $this->getTasks($eCultivationEmpty)->count(), 'la suppression d\'une case sans intervention en a créé une');

		$this->assertEquals([\Clock::date() => ['kg' => 6.0]], $this->getHarvested($eCultivation), 'un refus a écrit sur la culture');
		$this->assertEquals([\Clock::date() => ['kg' => 5.0]], $this->getHarvested($eCultivationOther), 'un refus a écrit sur la culture voisine');

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eCultivationForeign = \series\Cultivation::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

		if($this->assertDecorPresent($eCultivationForeign->notEmpty(), 'une culture d\'une autre structure') === FALSE) {
			return;
		}

		$harvested = $this->getHarvested($eCultivationForeign);

		[, $action] = $this->delete($eCultivationForeign);

		$this->assertFalse($action instanceof \ViewAction, 'la récolte d\'une culture d\'une autre structure se supprime');
		$this->assertEquals($harvested, $this->getHarvested($eCultivationForeign), 'la suppression a écrit chez une autre structure');

	}

	/**
	 * Un ajout négatif soustrait du récolté du jour, seul retrait depuis que la feuille ne propose plus « Retirer »
	 * (21/10) : 21 − 5 laisse 16, et 21 − 21 vide le jour. Une soustraction ne dit pas qu'une récolte est
	 * finie : l'intervention à faire le reste, à son jour prévu, qu'elle garde de la récolte ou non
	 * (arbitrage V-Planning du 2026-10-10, point c). Temps de travail la range à ce jour, et au lundi tant
	 * qu'il garde de sa récolte.
	 */
	public function testRemoveByNegativeAdd(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$cases = [
			'21 − 5' => ['-5', [$monday => [\series\Task::KG => 16.0]], \series\Task::TODO, $tuesday, [$monday, $tuesday]],
			'21 − 21' => ['-21', [$monday => []], \series\Task::TODO, $tuesday, [$tuesday]],
		];

		foreach($cases as $case => [$quantity, $harvested, $status, $plannedDate, $keys]) {

			$eCultivation = $this->createCultivation($this->createSeries('Retrait '.$case));
			$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week(), $tuesday);

			// Sans build(), qui n'a rien à vérifier sur une unité que le décor pose : c'est celle qu'une première
			// saisie aurait écrite, et `createHarvest()` la lit pour ranger la récolte.
			$eTask['harvestUnit'] = \series\Task::KG;
			\series\TaskLib::update($eTask, ['harvestUnit']);

			$this->createHarvest($eTask, 21.0, $monday);

			if($this->assertEquals([$monday => [\series\Task::KG => 21.0]], $this->getHarvested($eCultivation), $case.', le décor ne porte pas 21 kg lundi') === FALSE) {
				continue;
			}

			$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => $quantity]);

			if($this->assertTrue($action instanceof \ViewAction, $case.', le retrait est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$eTaskWritten = $this->getTasks($eCultivation)[$eTask['id']];

			$this->assertEquals($harvested, $this->getHarvested($eCultivation), $case.', le jour ne vaut pas le récolté moins le retrait');
			$this->assertEquals($status, $eTaskWritten['status'], $case.', l\'intervention n\'a pas le statut d\'un remplacement à la baisse');
			$this->assertEquals($plannedDate, $eTaskWritten['plannedDate'], $case.', l\'intervention n\'est pas prévue au jour attendu');
			$this->assertEquals($keys, TaskDayLib::getKeysForTask($this->eStructure, \Clock::week(), $eTaskWritten), $case.', Temps de travail ne range pas l\'intervention aux jours attendus');

		}

	}

	/**
	 * Un ajout négatif qui porte du temps est refusé sous le temps : un retrait corrige une quantité mal saisie, ce
	 * n'est pas du travail fait (comme `removeTime` dans la case), et rien ne s'écrit.
	 */
	public function testRemoveWithTimeRefused(): void {

		$eUserOnline = $this->getMember();

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 21.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '-2', 'crate' => '0', 'time' => [$eUserOnline['id'] => '0.25']]);

		$this->assertEquals(['Task::harvestMore.time'], $this->getFails($action), 'un retrait avec du temps n\'est pas refusé');
		$this->assertDiffer(NULL, \series\AlertUi::getError('Task::harvestMore.time'), 'le refus d\'un retrait avec du temps n\'a pas de message');
		$this->assertEquals([\Clock::date() => [\series\Task::KG => 21.0]], $this->getHarvested($eCultivation), 'un retrait avec du temps refusé a écrit');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '-2', 'crate' => '0', 'time' => [$eUserOnline['id'] => '0']]);

		$this->assertTrue($action instanceof \ViewAction, 'un retrait sans temps est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => [\series\Task::KG => 19.0]], $this->getHarvested($eCultivation), 'un retrait sans temps n\'a pas retiré');

	}

	/**
	 * Le stock proposé suit l'unité et la variété de la saisie (lot GV). L'option de la règle, sans unité de
	 * stock, lit le couple de « Nouvelle récolte », à défaut l'unité que la liste porte depuis son rendu, jamais
	 * le cadre, que chaque réponse redessine dans l'unité qu'elle a lue. Une seule requête vole à la fois, la
	 * suivante partant à la réponse sur la dernière saisie, et une réponse qui ne redessine pas le cadre ne
	 * relance rien.
	 */
	public function testStockReload(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvestStock {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvestStock') === FALSE) {
			return;
		}

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvestStock = PlanningHarvestStock;", context);

			// Les requêtes partent dans une liste, et ne répondent qu\'à la demande. Un cadre verrouillé refuse une
			// seconde requête, comme `Ajax.Query.lock()`.
			const requests = [];
			const locked = new Set();

			context.Ajax = {Query: class {
				constructor(field) { this.field = field; }
				url() { return this; }
				body(body) { this.sent = body; return this; }
				fetch() {
					if(locked.has(this.field)) {
						throw "LOCK_FAILED";
					}
					locked.add(this.field);
					return new Promise(resolve => requests.push({field: this.field, unit: this.sent.harvestUnit, answer: resolve}));
				}
			}};
			context.FormData = class {
				constructor(form) { this.values = form.values; }
				has(name) { return this.values[name] !== undefined; }
				get(name) { return this.values[name] ?? null; }
			};
			context.PlanningHarvestChoice = {getValue: () => ""};

			const makeField = unit => ({isConnected: true, dataset: {planningHarvestStock: "stock", variety: "", unit: unit, cultivation: "1", plant: "1", titled: "0"}});

			const makeForm = (stockUnit, units = null) => {
				const form = {isConnected: true, values: {}, elements: {id: {value: "1"}}, field: makeField(stockUnit)};
				form.option = {dataset: {}};
				form.select = {selectedOptions: [form.option], dataset: {planningHarvestStockUnit: stockUnit}};
				form.choice = (units === null) ? null : {dataset: {planningHarvestUnits: JSON.stringify(units)}};
				form.qs = selector => selector.startsWith("select") ? form.select : form.choice;
				form.qsa = (selector, callback) => callback(form.field);
				return form;
			};

			// La réponse redessine le cadre dans l\'unité qu\'elle a lue, ou le laisse tel quel.
			const answer = async (form, request, replace = true) => {
				locked.delete(request.field);
				if(replace) {
					request.field.isConnected = false;
					form.field = makeField(request.unit);
				}
				request.answer();
				await new Promise(resolve => setImmediate(resolve));
			};

			const choose = (form, stockUnit) => {
				form.option = {dataset: (stockUnit === null) ? {} : {planningHarvestStockUnit: stockUnit}};
				form.select.selectedOptions = [form.option];
				context.PlanningHarvestStock.reload(form);
			};

			(async () => {

				const result = {};

				const formCouple = makeForm("kg", {"*": {value: "bunch"}});
				result.couple = context.PlanningHarvestStock.getUnit(formCouple, formCouple.field);

				const formBack = makeForm("kg");
				choose(formBack, "unit");
				await answer(formBack, requests.at(-1));
				choose(formBack, null);
				await answer(formBack, requests.at(-1));
				result.back = [requests.map(request => request.unit), formBack.field.dataset.unit];

				requests.length = 0;

				const formFast = makeForm("kg");
				choose(formFast, "unit");
				choose(formFast, "bunch");
				choose(formFast, "unit");
				choose(formFast, "bunch");
				const sentBefore = requests.length;
				await answer(formFast, requests[0]);
				await answer(formFast, requests.at(-1));
				result.fast = [sentBefore, requests.map(request => request.unit), formFast.field.dataset.unit, formFast.field.dataset.pending ?? null];

				requests.length = 0;

				const formKept = makeForm("kg");
				choose(formKept, "unit");
				await answer(formKept, requests[0], false);
				const pending = formKept.field.dataset.pending ?? null;
				const sentAfter = requests.length;
				context.PlanningHarvestStock.reload(formKept);
				result.kept = [pending, sentAfter, requests.length];

				process.stdout.write(JSON.stringify(result));

			})().catch(error => process.stdout.write(JSON.stringify({error: String(error)})));
		';

		[$result, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($result) and isset($result['error']) === FALSE, 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		$this->assertEquals('bunch', $result['couple'], 'l\'option de la règle ne lit pas l\'unité du couple');
		$this->assertEquals([['unit', 'kg'], 'kg'], $result['back'], 'le retour aux kilos après les pièces ne relit pas le stock en kilos');
		$this->assertEquals([1, ['unit', 'bunch'], 'bunch', NULL], $result['fast'], 'quatre changements avant la première réponse ne finissent pas sur la dernière unité, en une requête de plus');
		$this->assertEquals([NULL, 1, 2], $result['kept'], 'une réponse qui ne redessine pas le cadre le bloque, ou relance d\'elle-même');

	}

	/**
	 * Tiroir ouvert, un clic hors de lui le referme, et dans la grille il ne fait rien d'autre : le jour
	 * ouvert reste, aucune autre saisie ne s'ouvre (retour 194). Hors de la grille, un onglet ou le menu
	 * agissent aussi. `outside()` tourne sur un arbre simulé comme le rend `HarvestWeekUi::get()` : la liste
	 * porte les onglets puis la grille, qui porte l'en-tête de mardi, la ligne du tiroir ouvert jeudi et une
	 * autre ligne ; le menu est hors de la liste.
	 */
	public function testOutsideClick(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvest {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvest') === FALSE) {
			return;
		}

		// [tiroir ouvert, nœud de l'appui, nœud du clic, `detail` du clic, touche Ctrl ou Cmd] => [tiroir fermé, clic arrêté]
		$cases = [
			'un autre jour' => [[TRUE, 'tuesday', 'tuesday', 1, NULL], [TRUE, TRUE]],
			'l\'en-tête d\'un autre jour' => [[TRUE, 'tuesdayHead', 'tuesdayHead', 1, NULL], [TRUE, TRUE]],
			'un autre « + Récolter »' => [[TRUE, 'harvestOther', 'harvestOther', 1, NULL], [TRUE, TRUE]],
			'une autre ligne' => [[TRUE, 'rowOther', 'rowOther', 1, NULL], [TRUE, TRUE]],
			'le « + Récolter » du tiroir' => [[TRUE, 'harvest', 'harvest', 1, NULL], [TRUE, TRUE]],
			'un onglet' => [[TRUE, 'tab', 'tab', 1, NULL], [TRUE, FALSE]],
			'le menu' => [[TRUE, 'menu', 'menu', 1, NULL], [TRUE, FALSE]],
			'dans le tiroir' => [[TRUE, 'field', 'field', 1, NULL], [FALSE, FALSE]],
			'dans le tiroir, sans appui retenu' => [[TRUE, NULL, 'field', 1, NULL], [FALSE, FALSE]],
			'une sélection commencée dans le tiroir' => [[TRUE, 'field', 'row', 1, NULL], [FALSE, FALSE]],
			'au clavier' => [[TRUE, NULL, 'tuesday', 0, NULL], [FALSE, FALSE]],
			'un Ctrl+clic sur le nom d\'une autre série' => [[TRUE, 'name', 'name', 1, 'ctrl'], [FALSE, FALSE]],
			'un Cmd+clic sur le nom d\'une autre série' => [[TRUE, 'name', 'name', 1, 'meta'], [FALSE, FALSE]],
			'un Ctrl+clic sur un autre jour' => [[TRUE, 'tuesday', 'tuesday', 1, 'ctrl'], [TRUE, TRUE]],
			'sans tiroir' => [[FALSE, 'tuesday', 'tuesday', 1, NULL], [FALSE, FALSE]],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvest = PlanningHarvest;", context);
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

			const body = node(null);
			const list = node(body, ["#planning-harvest-list"]);
			const grid = node(list, ["#planning-harvest-list .planning-harvest-grid"]);
			const head = node(grid, [".util-table-head"]);
			const row = node(grid, [".util-table-row"]);
			const drawer = node(row, [".planning-harvest-drawer"]);
			const rowOther = node(grid, [".util-table-row"]);

			const nodes = {
				menu: node(body),
				tab: node(list),
				tuesdayHead: node(head),
				harvest: node(row),
				field: node(drawer),
				row: row,
				rowOther: rowOther,
				tuesday: node(rowOther),
				name: node(rowOther, ["a[href]"]),
				harvestOther: node(rowOther)
			};

			const results = cases.map(([open, pressed, target, detail, modified]) => {

				const closed = [];
				const e = {target: nodes[target], detail: detail, ctrlKey: modified === "ctrl", metaKey: modified === "meta", stopped: false, prevented: false, stopPropagation() { this.stopped = true; }, preventDefault() { this.prevented = true; }};

				context.qs = selector => (open && selector === "#planning-harvest-list .planning-harvest-drawer") ? drawer : null;
				context.closed = closed;

				vm.runInContext("PlanningHarvest.close = row => closed.push(row)", context);

				context.PlanningHarvest.pressed = (pressed === null) ? null : nodes[pressed];
				context.PlanningHarvest.outside(e);

				return [
					closed.length === 1 && closed[0] === row,
					e.stopped && e.prevented,
					e.stopped || e.prevented,
					context.PlanningHarvest.pressed === null
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

			$this->assertEquals($cases[$case][1][0], $closed, $case.', le tiroir '.($cases[$case][1][0] ? 'reste ouvert' : 'se ferme'));
			$this->assertEquals($cases[$case][1][1], $stopped, $case.', le clic '.($cases[$case][1][1] ? 'traverse' : 'est arrêté'));
			$this->assertEquals($stopped, $touched, $case.', le clic n\'est arrêté qu\'à moitié');
			$this->assertTrue($forgotten, $case.', l\'appui reste retenu pour le clic suivant');

		}

	}

	/**
	 * La saisie ne connaît plus de retrait (21/10, « ne garde que Ajouter et Remplacer ») : un
	 * `mode=remove` posté retombe sur l'ajout, et sa quantité, qui ne porte pas le nom de l'ajout, n'est pas lue.
	 * L'ajout vide se refuse et rien n'est écrit. Un remplacement par 0 sans temps reste un geste légitime, qui
	 * vide le jour.
	 */
	public function testRemoveModeRefused(): void {

		$eCultivation = $this->createCultivation();
		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 2.0, \Clock::date());

		$action = $this->post($eCultivation, ['mode' => 'remove', 'harvestLess' => '1']);

		$this->assertEquals(['Task::harvestMore.empty'], $this->getFails($action), 'un retrait posté n\'est pas refusé comme un ajout vide');
		$this->assertEquals([\Clock::date() => [\series\Task::KG => 2.0]], $this->getHarvested($eCultivation), 'un retrait posté a écrit');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '0']);

		$this->assertTrue($action instanceof \ViewAction, 'un remplacement par 0 sans temps est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => []], $this->getHarvested($eCultivation), 'le remplacement par 0 ne vide pas le jour');

	}

	/**
	 * `open` ne connaît que l'ajout et le remplacement, et un `mode=remove` retombe sur l'ajout. La liste des
	 * gestes de la feuille, ajouter ou remplacer, ne vient que sur un jour qui porte une récolte ; la quantité n'a
	 * qu'un champ, qui porte le nom du geste ouvert.
	 */
	public function testSheetModes(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);

		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), $monday), 2.0, $monday);

		[$data] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'mode' => 'remove', 'sheet' => 1], 'GET');

		$this->assertEquals('add', $data->mode, 'un `mode=remove` ne retombe pas sur l\'ajout');

		$cases = [
			'jour récolté' => [$monday, TRUE],
			'jour sans récolte' => [\Clock::date(), FALSE],
		];

		foreach($cases as $case => [$date, $modes]) {

			[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $date, 'mode' => 'add', 'sheet' => 1], 'GET');

			$sheet = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

			if($this->assertTrue($sheet !== NULL, $case.' : la feuille ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$values = [];

			foreach($sheet->querySelectorAll('input[type="radio"][name="mode"]') as $radio) {
				$values[] = $radio->getAttribute('value');
			}

			$this->assertEquals($modes ? ['add', 'replace'] : [], $values, $case.' : la liste des gestes');

			$this->assertEquals($modes === FALSE, $sheet->querySelector('input[type="hidden"][name="mode"]') !== NULL, $case.' : le geste part par un champ caché quand il n\'y a pas de liste');

			$names = [];

			foreach($sheet->querySelectorAll(':is(input[name="harvestMore"], input[name="harvestDay"])') as $field) {
				$names[] = $field->getAttribute('name');
			}

			$this->assertEquals(['harvestMore'], $names, $case.' : la quantité ne part pas par un seul champ, au nom du geste ouvert');

			// Le groupe « Temps de travail » ne reprend pas le refus de la dernière durée construite.
			foreach($sheet->querySelectorAll('input[name^="time["]') as $field) {
				$this->assertEquals(1, $sheet->querySelectorAll('[data-wrapper~="'.$field->getAttribute('name').'"]')->length, $case.' : le refus de '.$field->getAttribute('name').' s\'affiche ailleurs que sous sa durée');
			}

		}

	}

	/**
	 * Ouverte en remplacement, la feuille part de la quantité du jour, comme le tiroir (R-Retours-0810, AA,
	 * point 4) : le champ posté la porte. En ajout, elle part de rien.
	 */
	public function testSheetReplaceFromDay(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);

		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), $monday), 2.5, $monday);

		$cases = [
			'replace' => ['harvestDay', '2.5'],
			'add' => ['harvestMore', NULL],
		];

		foreach($cases as $mode => [$property, $posted]) {

			[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'mode' => $mode, 'sheet' => 1], 'GET');

			$sheet = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

			if($this->assertTrue($sheet !== NULL, $mode.' : la feuille ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$field = $sheet->querySelector('input[name="'.$property.'"]');

			if($this->assertTrue($field !== NULL and $field->hasAttribute('disabled') === FALSE, $mode.' : la quantité ne part pas par '.$property) === FALSE) {
				continue;
			}

			$this->assertEquals($posted, $field->getAttribute('value'), $mode.' : la quantité ne part pas de la bonne valeur');

		}

	}

	/**
	 * Une quantité tapée en caisses compte des caisses : quitter l'ajout ramène la saisie aux kilos sans la
	 * garder, sans quoi « 3 » caisses partait comme 3 kg (R-Retours-0810, AA, point 5). Le remplacement en kilos
	 * part de la quantité du jour quand rien n'est tapé, comme ouvert directement, et la rend en quittant le
	 * remplacement tant qu'elle n'a pas été touchée. Une quantité tapée reste. `mode()` tourne sur une feuille
	 * simulée, en kilos ou en caisses, avec 2,5 kg récoltés ce jour, le champ tel que le navigateur le lit.
	 */
	public function testSheetLeaveCrate(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvestSheet {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvestSheet') === FALSE) {
			return;
		}

		// Le geste d'où l'on part, la frappe, la pré-saisie, les caisses choisies ou non, le geste choisi, puis la
		// frappe, la pré-saisie et les caisses attendues.
		$cases = [
			'3 caisses, puis Remplacer' => ['add', '3', '', TRUE, 'replace', ['2.5', '2.5', FALSE]],
			'rien en caisses, puis Remplacer' => ['add', '', '', TRUE, 'replace', ['2.5', '2.5', FALSE]],
			'4 kg, puis Remplacer' => ['add', '4', '', FALSE, 'replace', ['4', '', FALSE]],
			'rien en kg, puis Remplacer' => ['add', '', '', FALSE, 'replace', ['2.5', '2.5', FALSE]],
			'remplacement intact, puis Ajouter' => ['replace', '2.5', '2.5', FALSE, 'add', ['', '', FALSE]],
			'remplacement tapé, puis Ajouter' => ['replace', '3', '2.5', FALSE, 'add', ['3', '', FALSE]],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvestSheet = PlanningHarvestSheet;", context);
			const cases = '.json_encode(array_values($cases)).';

			// Seule la frappe est éprouvée : le temps, le couple et le bouton ne répondent rien. Quitter un ajout
			// ramène le choix aux kilos, unité de la saisie.
			const state = {crate: false};
			const kilos = {set selected(value) { state.crate = (value === false); }};
			const crates = {};
			const select = {get selectedOptions() { return [state.crate ? crates : kilos]; }};
			context.PlanningHarvestCrate = {isOn: () => state.crate, own: () => kilos, allow: () => {}};
			context.PlanningHarvestTime = {team: () => new Set(), reset: () => {}};
			context.PlanningHarvestChoice = {change: () => {}};
			vm.runInContext("[\"update\", \"people\"].forEach(name => PlanningHarvestSheet[name] = () => {})", context);

			const read = cases.map(([from, typed, prefilled, crate, mode]) => {

				state.crate = crate;

				const field = {name: "", value: typed, min: "0", removeAttribute(name) { delete this[name]; }};

				const sheet = {
					dataset: {mode: from, prefilled: prefilled, currentDay: "2.5"},
					qs: (selector, callback) => {
						const found = selector.includes("harvestMore") ? field : ({"select[data-planning-harvest-crate]": select, "form": {}}[selector] ?? null);
						return (callback === undefined || found === null) ? found : callback(found);
					},
					qsa: () => {}
				};

				vm.runInContext("PlanningHarvestSheet.mode(globalThis.sheet, globalThis.target)", Object.assign(context, {sheet: sheet, target: mode}));

				return [field.value, sheet.dataset.prefilled, state.crate, field.name, field.min ?? null];

			});

			process.stdout.write(JSON.stringify(read));
		';

		[$read, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($read), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		$names = ['add' => 'harvestMore', 'replace' => 'harvestDay'];

		// Un ajout négatif retire du jour : seul le remplacement garde le plancher que le navigateur oppose à l'envoi.
		$mins = ['add' => NULL, 'replace' => '0'];

		foreach(array_keys($cases) as $position => $case) {
			$this->assertEquals([...$cases[$case][5], $names[$cases[$case][4]], $mins[$cases[$case][4]]], $read[$position], $case.' : la quantité ne part pas de la bonne valeur, dans la bonne unité, sous le nom et avec le plancher du geste');
		}

	}

	/**
	 * Au tactile, la liste de l'unité est le choix de « Nouvelle récolte » (retour 319), quel que soit le geste
	 * d'ouverture : l'unité de la culture choisie, ses caisses, puis les deux autres, qui désignent l'intervention
	 * de la semaine dans leur unité ou se désignent par leur unité. Les champs cachés `task` et `harvestUnit`
	 * s'ouvrent éteints et vides, un seul de chaque. Ouverte depuis une part, la feuille garde l'unité du couple
	 * seule, avec ses caisses, et n'a pas ces champs.
	 */
	public function testSheetUnitOther(): void {

		$this->addPresenceTeam();

		$eCultivation = $this->createCultivation();
		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, week_date_day(\Clock::week(), 1));
		$this->createHarvest($eTaskUnit, 3.0, week_date_day(\Clock::week(), 1));

		foreach(['add', 'replace'] as $mode) {

			$sheet = ($mode === 'add') ? $this->openSheet($eCultivation) : $this->openSheetOn($eCultivation, \Clock::date(), $mode);

			if($this->assertTrue($sheet !== NULL, $mode.' : la feuille ne s\'ouvre pas') === FALSE) {
				return;
			}

			$this->assertEquals([
				['0', TRUE, NULL, NULL],
				['0', FALSE, (string)$eTaskUnit['id'], NULL],
				['0', FALSE, NULL, \series\Task::BUNCH],
			], $this->getUnitOptions($sheet), $mode.' : la liste ne range pas les kilos choisis, les pièces qui désignent leur intervention et les bottes qui se désignent par leur unité');

			foreach(['task', 'harvestUnit'] as $name) {

				$field = $sheet->querySelector('input[type="hidden"][name="'.$name.'"]');

				$this->assertTrue(($field !== NULL and $field->hasAttribute('disabled') and ($field->getAttribute('value') ?? '') === ''), $mode.' : le champ caché « '.$name.' » ne s\'ouvre pas éteint et vide');
				$this->assertEquals(1, $sheet->querySelectorAll('[name="'.$name.'"]')->length, $mode.' : la feuille porte deux champs « '.$name.' »');

			}

		}

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'sheet' => 1, 'variety' => '', 'harvestSize' => ''], 'GET');
		$sheet = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

		if($this->assertTrue($sheet !== NULL, 'part : la feuille ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals([], array_filter($this->getUnitOptions($sheet), fn($option) => $option[2] !== NULL or $option[3] !== NULL), 'part : la feuille d\'une part propose d\'autres unités que l\'unité du couple');
		$this->assertTrue($sheet->querySelector('[name="harvestUnit"]') === NULL, 'part : la feuille d\'une part porte le champ « harvestUnit »');

	}

	/**
	 * Les unités en kilos ou en pièces de la liste de la quantité (`HarvestEntryUi::getUnitChoices()`), sans les
	 * caisses ni l'unité de la saisie réservée à un couple : la valeur postée, l'option choisie, l'intervention
	 * qu'elle désigne, l'unité qu'elle annonce.
	 */
	private function getUnitOptions(\Dom\Element $sheet): array {

		$options = [];

		foreach($sheet->querySelectorAll('select[name="crate"] > option[value="0"]:not([data-planning-harvest-couple])') as $option) {
			$options[] = [
				$option->getAttribute('value'),
				$option->hasAttribute('selected'),
				$option->getAttribute('data-planning-harvest-task'),
				$option->getAttribute('data-planning-harvest-other'),
			];
		}

		return $options;

	}

	/**
	 * Le tiroir de la Semaine propose les autres unités comme la feuille (lot GN), en ajout comme en
	 * remplacement, où `PlanningHarvestCrate.allow()` les éteint : chacune désigne son intervention ou annonce
	 * son unité, les champs cachés qui les portent s'ouvrent éteints, et une part garde l'unité de son couple.
	 */
	public function testDrawerUnitOther(): void {

		$eCultivation = $this->createCultivation();
		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, week_date_day(\Clock::week(), 1));
		$this->createHarvest($eTaskUnit, 3.0, week_date_day(\Clock::week(), 1));

		foreach(['add', 'replace'] as $mode) {

			$drawer = $this->openDrawerOn($eCultivation, \Clock::date(), $mode);

			if($this->assertTrue($drawer !== NULL, $mode.' : le tiroir ne s\'ouvre pas') === FALSE) {
				return;
			}

			$this->assertEquals([
				['0', TRUE, NULL, NULL],
				['0', FALSE, (string)$eTaskUnit['id'], NULL],
				['0', FALSE, NULL, \series\Task::BUNCH],
			], $this->getUnitOptions($drawer), $mode.' : la liste du tiroir ne range pas les kilos choisis, les pièces qui désignent leur intervention et les bottes qui se désignent par leur unité');

			// L'unité de la saisie qu'un couple choisi rend à la liste (HN-B2c), que `PlanningHarvestCrate.task()`
			// poste par son unité de stock.
			$couple = $drawer->querySelector('select[name="crate"] > option[data-planning-harvest-couple]');
			$this->assertEquals([\series\Task::KG, TRUE, TRUE], [$couple?->getAttribute('data-planning-harvest-stock-unit'), $couple?->hasAttribute('disabled'), $couple?->hasAttribute('hidden')], $mode.' : la liste ne garde pas, éteints, les kilos qu\'un couple choisi reprend');

			foreach(['task', 'harvestUnit'] as $name) {

				$field = $drawer->querySelector('input[type="hidden"][name="'.$name.'"]');

				$this->assertTrue(($field !== NULL and $field->hasAttribute('disabled') and ($field->getAttribute('value') ?? '') === ''), $mode.' : le champ caché « '.$name.' » du tiroir ne s\'ouvre pas éteint et vide');
				$this->assertEquals(1, $drawer->querySelectorAll('[name="'.$name.'"]')->length, $mode.' : le tiroir porte deux champs « '.$name.' »');

			}

		}

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'variety' => '', 'harvestSize' => ''], 'GET');
		$drawer = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']).'</body></html>', LIBXML_NOERROR)->querySelector('.planning-harvest-drawer');

		if($this->assertTrue($drawer !== NULL, 'part : le tiroir ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals([], array_filter($this->getUnitOptions($drawer), fn($option) => $option[2] !== NULL or $option[3] !== NULL), 'part : le tiroir d\'une part propose d\'autres unités que l\'unité du couple');
		$this->assertTrue($drawer->querySelector('[name="harvestUnit"]') === NULL, 'part : le tiroir d\'une part porte le champ « harvestUnit »');

		// Une carte écrit dans son intervention (`taskWeek:doHarvest`) : son tiroir ne propose pas d'autre unité.
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$data, , $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'mode' => 'add', 'origin' => 'semaine', 'task' => $eTask['id'], 'unplanned' => '', 'folded' => ''], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'carte : le tiroir ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals([], $data->tasksByUnit, 'carte : le tiroir d\'une carte propose d\'autres unités');

	}

	/**
	 * Un jour qui ne porte que du temps s'ouvre en remplacement sur le « 0 » de l'unité de la semaine, et se
	 * remplace par lui : seule une autre unité rouverte part d'un champ vide (lot GO).
	 */
	public function testDrawerTimeOnly(): void {

		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$eUserOnline = $this->getMember();

		// La culture n'est listée dans la semaine qu'avec une récolte : celle du lundi, le jeudi n'ayant que du temps.
		$eCultivation = $this->createCultivation();
		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskKg, 2.0, week_date_day(\Clock::week(), 1));
		$this->writeTime($eTaskKg, $eUserOnline, \Clock::date(), 0.25);

		[, $instructions, $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'replace'], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le tiroir d\'un jour à temps seul ne s\'ouvre pas : '.get_class($action).' '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$drawer = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']).'</body></html>', LIBXML_NOERROR)->querySelector('.planning-harvest-drawer');

		$this->assertEquals('0', $drawer?->querySelector('input[name="harvestDay"]')?->getAttribute('value'), 'le tiroir d\'un jour à temps seul ne part pas de son « 0 »');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '0', 'crate' => '0', 'time' => [$eUserOnline['id'] => '0.5']]);
		$this->assertTrue($action instanceof \ViewAction, 'le jour à temps seul ne se remplace pas par son « 0 » : '.implode(', ', $this->getFails($action)));

	}

	/**
	 * Une autre unité choisie en remplacement rouvre le tiroir sur elle (lot GO) : la liste porte l'adresse qui
	 * le rouvre. Rouvert sur l'intervention d'une autre unité, il en
	 * montre la quantité et les durées, et « Supprimer » la vise ; rouvert sur une unité que la semaine ne porte
	 * pas, il part de zéro, le temps du jour reste entier, il ne propose pas « Supprimer », et l'unité se poste
	 * par l'option choisie. Une part ou une intervention désignée refusent une autre unité, comme à l'écriture.
	 */
	public function testDrawerReopen(): void {

		$eUserOnline = $this->getMember();

		$eCultivation = $this->createCultivation();
		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskKg, 6.0, \Clock::date());
		$this->writeTime($eTaskKg, $eUserOnline, \Clock::date(), 1.0);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, \Clock::date());
		$this->createHarvest($eTaskUnit, 3.0, \Clock::date());
		$this->writeTime($eTaskUnit, $eUserOnline, \Clock::date(), 0.5);

		$open = function(array $input) use($eCultivation): array {

			[$data, $instructions, $action] = $this->call('open', $input + ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'replace'], 'GET');

			$drawer = ($action instanceof \ViewAction) ?
				\Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']).'</body></html>', LIBXML_NOERROR)->querySelector('.planning-harvest-drawer') :
				NULL;

			return [$data, $drawer, $action];

		};

		[, $drawer] = $open([]);

		if($this->assertTrue($drawer !== NULL, 'le tiroir de la semaine ne s\'ouvre pas') === FALSE) {
			return;
		}

		// La liste porte l'unité que lisent ses options sans unité de stock, que le stock relit au retour sur elles
		// (`PlanningHarvestStock.getUnit()`), et que la réponse du stock ne redessine pas.
		$this->assertEquals(\series\Task::KG, $drawer->querySelector('select[name="crate"]')?->getAttribute('data-planning-harvest-stock-unit'), 'la liste ne dit pas au stock l\'unité de la saisie');

		parse_str((string)parse_url($drawer->querySelector('select[name="crate"]')?->getAttribute('data-planning-harvest-reopen') ?? '', PHP_URL_QUERY), $reopen);

		$this->assertEquals(
			['cultivation' => (string)$eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'replace', 'open' => \Clock::date()],
			array_intersect_key($reopen, ['cultivation' => NULL, 'date' => NULL, 'mode' => NULL, 'open' => NULL, 'task' => NULL, 'harvestUnit' => NULL]),
			'la liste ne porte pas l\'adresse qui rouvre le tiroir en remplacement, sur son jour, sans unité'
		);

		[$data, $drawer] = $open(['task' => $eTaskUnit['id']]);

		if($this->assertTrue($drawer !== NULL, 'le tiroir rouvert sur les pièces ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals(\series\Task::UNIT, $data->eCultivation['harvestUnitWeek'], 'le tiroir rouvert sur les pièces n\'est pas en pièces');
		$this->assertEquals('3', $drawer->querySelector('input[name="harvestDay"]')?->getAttribute('value'), 'le tiroir rouvert sur les pièces ne montre pas les 3 pièces du jour');
		$this->assertEquals(0.5, $data->cUserTime[$eUserOnline['id']]['spent'][\Clock::date()], 'le tiroir rouvert sur les pièces ne montre pas le temps passé sur elles');
		$this->assertEquals(1.0, $data->timeKept, 'le tiroir rouvert sur les pièces ne laisse pas le temps des kilos hors de ses durées');
		$this->assertTrue($data->deletable, 'le tiroir rouvert sur les pièces ne propose pas « Supprimer »');
		$this->assertEquals([
			['0', FALSE, (string)$eTaskKg['id'], NULL],
			['0', TRUE, (string)$eTaskUnit['id'], NULL],
			['0', FALSE, NULL, \series\Task::BUNCH],
		], $this->getUnitOptions($drawer), 'rouvert sur les pièces, la liste ne garde pas l\'ordre de l\'ouverture, les kilos qui désignent leur intervention, les pièces choisies, puis les bottes');
		$this->assertEquals((string)$eTaskUnit['id'], $drawer->querySelector('select[name="crate"] > option[data-planning-harvest-own]')?->getAttribute('data-planning-harvest-task'), 'rouvert sur les pièces, les pièces ne se marquent pas unité de la saisie');
		$this->assertEquals(\series\Task::UNIT, $drawer->querySelector('select[name="crate"]')?->getAttribute('data-planning-harvest-stock-unit'), 'rouvert sur les pièces, la liste ne dit pas au stock les pièces');

		[$data, $drawer] = $open(['harvestUnit' => \series\Task::BUNCH]);

		if($this->assertTrue($drawer !== NULL, 'le tiroir rouvert sur les bottes ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals(\series\Task::BUNCH, $data->eCultivation['harvestUnitWeek'], 'le tiroir rouvert sur les bottes n\'est pas en bottes');
		$this->assertEquals('', $drawer->querySelector('input[name="harvestDay"]')?->getAttribute('value') ?? '', 'le tiroir rouvert sur les bottes ne part pas d\'un champ vide');
		$this->assertEquals(0.0, array_sum($data->cUserTime[$eUserOnline['id']]['spent']), 'le tiroir rouvert sur les bottes montre le temps d\'une autre intervention');
		$this->assertEquals(1.5, $data->timeKept, 'le tiroir rouvert sur les bottes ne laisse pas tout le temps du jour hors de ses durées');
		$this->assertFalse($data->deletable, 'le tiroir rouvert sur les bottes propose « Supprimer »');
		$this->assertEquals([
			['0', FALSE, (string)$eTaskKg['id'], NULL],
			['0', FALSE, (string)$eTaskUnit['id'], NULL],
			['0', TRUE, NULL, \series\Task::BUNCH],
		], $this->getUnitOptions($drawer), 'rouvert sur les bottes, la liste ne garde pas l\'ordre de l\'ouverture, l\'option choisie ne poste pas les bottes, ou les autres unités ne désignent pas leur intervention');
		$this->assertEquals(\series\Task::BUNCH, $drawer->querySelector('select[name="crate"]')?->getAttribute('data-planning-harvest-stock-unit'), 'rouvert sur les bottes, la liste ne dit pas au stock les bottes');

		if($this->assertDecorPresent($data->eCultivation['plant']['crate'], 'l\'espèce de la culture se pèse à la caisse') === FALSE) {
			return;
		}

		$crates = $drawer->querySelector('select[name="crate"] > option[value="1"]');
		$this->assertTrue(($crates !== NULL and $crates->hasAttribute('data-planning-harvest-own') === FALSE and $crates->getAttribute('data-planning-harvest-task') === (string)$eTaskKg['id']), 'rouvert sur les bottes, les caisses ne suivent pas les kilos en désignant leur intervention');

		[, $drawer] = $open(['task' => $eTaskKg['id'], 'crate' => 1]);

		$crates = $drawer?->querySelector('select[name="crate"] > option[value="1"]');
		$this->assertTrue(($crates !== NULL and $crates->hasAttribute('selected') and $crates->hasAttribute('data-planning-harvest-own')), 'rouvert sur les caisses, les caisses des kilos ne sont pas choisies');
		$this->assertEquals('6', $drawer?->querySelector('input[name="harvestDay"]')?->getAttribute('value'), 'rouvert sur les caisses, le champ ne garde pas les kilos du jour, que le retour aux kilos remet');
		$this->assertFalse($drawer?->querySelector('[data-planning-crate]')?->classList->contains('hide') ?? TRUE, 'rouvert sur les caisses, le poids d\'une caisse reste caché');

		[, , $action] = $open(['task' => $eTaskUnit['id'], 'harvestUnit' => \series\Task::BUNCH]);
		$this->assertEquals(['Task::harvestUnit.couple'], $this->getFails($action), 'une autre unité avec une intervention désignée n\'est pas refusée à l\'ouverture');

		[$eCultivationPart, $eVariety] = $this->createCultivationPart();

		[, , $action] = $this->call('open', ['cultivation' => $eCultivationPart['id'], 'date' => \Clock::date(), 'mode' => 'replace', 'variety' => $eVariety['id'], 'harvestUnit' => \series\Task::BUNCH], 'GET');
		$this->assertTrue($action instanceof \ViewAction, 'une autre unité avec une variété est refusée à l\'ouverture : '.implode(', ', $this->getFails($action)));

	}

	/**
	 * Écrire après une réouverture va sur l'intervention que le tiroir montrait (lot GO) : le temps remplacé
	 * dans une autre unité est le sien, et celui de l'unité de la case ne bouge pas.
	 */
	public function testWriteAfterReopen(): void {

		$eUserOnline = $this->getMember();

		$eCultivation = $this->createCultivation();
		$eTaskKg = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskKg, 6.0, \Clock::date());
		$this->writeTime($eTaskKg, $eUserOnline, \Clock::date(), 1.0);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, \Clock::date());
		$this->createHarvest($eTaskUnit, 3.0, \Clock::date());
		$this->writeTime($eTaskUnit, $eUserOnline, \Clock::date(), 0.5);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '4', 'crate' => '0', 'task' => $eTaskUnit['id'], 'time' => [$eUserOnline['id'] => '0.75']]);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en pièces est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$eUserOnline['id'] => 0.75], $this->getTimeByUser($eTaskUnit), 'le temps remplacé en pièces ne va pas sur les pièces');
		$this->assertEquals([$eUserOnline['id'] => 1.0], $this->getTimeByUser($eTaskKg), 'le remplacement en pièces touche au temps des kilos');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'crate' => '0', 'harvestUnit' => \series\Task::BUNCH, 'time' => [$eUserOnline['id'] => '0.25']]);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en bottes est refusé : '.implode(', ', $this->getFails($action)));

		$cTaskBunch = $this->getTasks($eCultivation)->find(fn($eTask) => $eTask['harvestUnit'] === \series\Task::BUNCH);

		if($this->assertEquals(1, $cTaskBunch->count(), 'le remplacement en bottes ne crée pas son intervention') === FALSE) {
			return;
		}

		$this->assertEquals([$eUserOnline['id'] => 0.25], $this->getTimeByUser($cTaskBunch->first()), 'le temps du remplacement en bottes ne va pas sur les bottes');
		$this->assertEquals([$eUserOnline['id'] => 1.0], $this->getTimeByUser($eTaskKg), 'le remplacement en bottes touche au temps des kilos');
		$this->assertEquals(['kg' => 6.0, 'unit' => 4.0, 'bunch' => 2.0], $this->getHarvested($eCultivation)[\Clock::date()] ?? NULL, 'les remplacements dans deux autres unités touchent aux kilos');

	}

	/**
	 * Le remplacement d'une culture entière vaut le jour dans l'unité choisie (lot GN). Une autre unité qui
	 * désigne son intervention y remplace le jour, une unité que la semaine ne porte pas crée la sienne, et des
	 * caisses, proposées seulement à une espèce qui en a le poids, remplacent les kilos du jour par leur poids.
	 * Chaque écriture laisse les autres unités intactes.
	 */
	public function testDayUnitOther(): void {

		// Un jeudi, pour que le lundi du décor ne soit jamais un jour à venir.
		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $monday);
		$this->createHarvest($eTaskUnit, 3.0, $monday);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '10']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie en kilos du lundi est refusée : '.implode(', ', $this->getFails($action)));

		[, $data] = $this->render(TRUE);

		if($this->assertDecorPresent($data->cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée lundi est dans la liste de la semaine') === FALSE) {
			return;
		}

		$this->assertFalse($data->cCultivation[$eCultivation['id']]['plant']['crate'], 'la saisie propose des caisses à une espèce qui n\'en a pas le poids');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '5', 'crate' => '0', 'task' => $eTaskUnit['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en pièces est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(['unit' => 5.0, 'kg' => 10.0], $this->getHarvested($eCultivation)[$monday] ?? NULL, 'le remplacement en pièces n\'écrit pas 5 pièces, ou touche aux kilos');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '2', 'crate' => '0', 'harvestUnit' => \series\Task::BUNCH]);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie en bottes est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(['unit' => 5.0, 'kg' => 10.0, 'bunch' => 2.0], $this->getHarvested($eCultivation)[$monday] ?? NULL, 'la saisie en bottes ne crée pas ses 2 bottes, ou touche aux autres unités');

		// Le poids des caisses se règle par le tiroir.
		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '1', 'crate' => '1', 'crateWeight' => '6']);
		$this->assertTrue($action instanceof \ViewAction, 'la récolte en caisses qui règle leur poids est refusée : '.implode(', ', $this->getFails($action)));

		[, $data] = $this->render(TRUE);
		$this->assertTrue($data->cCultivation[$eCultivation['id']]['plant']['crate'], 'la saisie ne propose pas de caisses à une espèce qui en a le poids');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $monday, 'harvestDay' => '3', 'crate' => '1']);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement en caisses est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(['unit' => 5.0, 'kg' => 18.0, 'bunch' => 2.0], $this->getHarvested($eCultivation)[$monday] ?? NULL, 'trois caisses de 6 kg ne remplacent pas les kilos du lundi par 18 kg');

	}

	/**
	 * Le champ d'une case en saisie rapide (HN-B) rappelle à chaque lecture le couple de sa ligne. Chaque part
	 * récoltée ce jour-là montre sa quantité : le seul champ du jour est celui de la série, sans couple ni
	 * `part`, où la phrase peut dire une variété ou un calibre.
	 * C'est ce que `base/entry` lit, pas ce que l'écran montre.
	 */
	public function testCellCouple(): void {

		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$eCultivation = $this->createCultivation();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety, 50.0);

		$eTaskVariety = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVariety, ['variety' => $eVariety]);
		$this->createHarvest($eTaskVariety, 2.0, \Clock::date());

		$eTaskUnspecified = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskUnspecified, 1.0, \Clock::date());

		[$document] = $this->render(TRUE);

		$bodies = [];

		foreach($document->querySelectorAll('.planning-harvest-cell[data-date="'.\Clock::date().'"] [data-suggestions-body]') as $node) {

			$body = json_decode($node->getAttribute('data-suggestions-body'), TRUE);

			if((int)$body['cell[cultivation]'] === $eCultivation['id']) {
				$bodies[] = [$body['cell[variety]'], $body['cell[harvestSize]'], $body['cell[part]'] ?? NULL];
			}

		}

		$this->assertEquals([['', '', NULL]], $bodies, 'le champ du jour n\'est pas celui de la série seule, sans couple');

	}

	/**
	 * Un jour récolté se rouvre dans l'unité qui le porte (GY) : la première dans l'ordre, celle de la semaine
	 * d'abord, puis l'enum. Son écriture désigne l'intervention de cette unité, même quand le jour en porte deux
	 * autres que celle de la semaine, et rien dans celle de la semaine.
	 */
	public function testDayUnitShown(): void {

		// Un jeudi, pour que le lundi et le mardi du décor ne soient jamais des jours à venir.
		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $monday);
		$this->createHarvest($eTaskUnit, 3.0, $monday);

		$eTaskBunch = $this->createTaskHarvestInUnit($eCultivation, \series\Task::BUNCH, $monday);
		$this->createHarvest($eTaskBunch, 2.0, $monday);

		$this->createHarvest($eTaskUnit, 4.0, $tuesday);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'date' => $tuesday, 'harvestDay' => '10']);
		$this->assertTrue($action instanceof \ViewAction, 'la saisie en kilos du mardi est refusée : '.implode(', ', $this->getFails($action)));

		[, $data] = $this->render(TRUE);

		if($this->assertDecorPresent($data->cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée lundi et mardi est dans la liste de la semaine') === FALSE) {
			return;
		}

		$eCultivationListed = $data->cCultivation[$eCultivation['id']];

		$this->assertEquals(\series\Task::KG, $eCultivationListed['harvestUnitWeek'], 'la semaine de la culture n\'est plus en kilos');
		$this->assertEquals(
			[$monday => $eTaskUnit['id'], $tuesday => NULL],
			array_map(fn($eTask) => $eTask->empty() ? NULL : $eTask['id'], $eCultivationListed['harvestTaskDays']),
			'la case du lundi ne désigne pas l\'intervention en pièces qu\'elle montre, ou celle du mardi en désigne une autre que celle des kilos'
		);

	}

	/**
	 * Depuis la feuille, une puce d'une autre unité écrit dans l'intervention qu'elle désigne, et celle qui
	 * se nomme crée l'intervention de son unité, comme « Nouvelle récolte ».
	 */
	public function testSheetUnitOtherWrites(): void {

		$eCultivation = $this->createCultivation();
		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, week_date_day(\Clock::week(), 1));
		$this->createHarvest($eTaskUnit, 3.0, week_date_day(\Clock::week(), 1));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'crate' => '0', 'sheet' => 1, 'task' => $eTaskUnit['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'la puce des pièces est refusée : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '4', 'crate' => '0', 'sheet' => 1, 'harvestUnit' => \series\Task::BUNCH]);
		$this->assertTrue($action instanceof \ViewAction, 'la puce des bottes est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(5.0, $cTask[$eTaskUnit['id']]['harvest'], 'les pièces ne s\'ajoutent pas à l\'intervention que leur puce désigne');
		$this->assertEquals([4.0], array_values(array_map(fn($eTask) => $eTask['harvest'], $cTask->find(fn($eTask) => $eTask['harvestUnit'] === \series\Task::BUNCH)->getArrayCopy())), 'les bottes ne créent pas l\'intervention de leur unité');

	}

	/**
	 * Depuis une case qui désigne son intervention, la feuille propose aussi les autres unités de la semaine
	 * (lot EL, Q1 de M-Planning) : l'unité de la case et ses caisses désignent l'intervention de la case, que le
	 * champ caché `task` poste dès l'ouverture, et une autre unité la remplace par la sienne. Chacune écrit là
	 * où elle le dit, `doHarvest` recevant ce que `PlanningHarvestCrate.task()` laisse actif.
	 */
	public function testSheetUnitOtherFromTask(): void {

		$eCultivation = $this->createCultivation();
		$monday = week_date_day(\Clock::week(), 1);
		$eTaskUnit = $this->createTaskHarvestInUnit($eCultivation, \series\Task::UNIT, $monday);
		$this->createHarvest($eTaskUnit, 3.0, $monday);

		[$data, $instructions, $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'mode' => 'replace', 'sheet' => 1, 'task' => $eTaskUnit['id']], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la feuille d\'une case désignée ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals($eTaskUnit['id'], $data->eTaskChosen['id'], 'la feuille ne reçoit pas l\'intervention de la case');
		$this->assertEquals([\series\Task::KG, \series\Task::BUNCH], array_keys($data->tasksByUnit), 'la feuille d\'une case désignée ne propose pas les autres unités de la semaine');

		$sheet = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

		if($this->assertTrue($sheet !== NULL, 'la réponse ne pose pas la feuille sur le body') === FALSE) {
			return;
		}

		$task = $sheet->querySelector('input[type="hidden"][name="task"]');

		$this->assertTrue(($task !== NULL and $task->hasAttribute('disabled') === FALSE and $task->getAttribute('value') === (string)$eTaskUnit['id']), 'le champ caché « task » ne part pas avec l\'intervention de la case');
		$this->assertEquals((string)$eTaskUnit['id'], $sheet->querySelector('select[name="crate"] > option[data-planning-harvest-own]')?->getAttribute('data-planning-harvest-task'), 'l\'unité de la case ne désigne pas son intervention, qu\'un retour à elle rendrait à « task »');

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '2', 'crate' => '0', 'sheet' => 1, 'task' => $eTaskUnit['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'l\'unité de la case est refusée : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '4', 'crate' => '0', 'sheet' => 1, 'harvestUnit' => \series\Task::BUNCH]);
		$this->assertTrue($action instanceof \ViewAction, 'une autre unité choisie depuis une case désignée est refusée : '.implode(', ', $this->getFails($action)));

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(5.0, $cTask[$eTaskUnit['id']]['harvest'], 'l\'unité de la case n\'écrit pas dans son intervention');
		$this->assertEquals([4.0], array_values(array_map(fn($eTask) => $eTask['harvest'], $cTask->find(fn($eTask) => $eTask['harvestUnit'] === \series\Task::BUNCH)->getArrayCopy())), 'une autre unité n\'écrit pas dans l\'intervention de son unité');

	}

	/**
	 * L'espèce se change à la feuille entre les cultures de la série que la semaine liste (lot EL, Q2 de
	 * M-Planning), préparées par la page : en changer rouvre la feuille par `open` sur l'autre culture, et c'est
	 * là que la récolte s'écrit. Une culture que la semaine ne liste pas ne se rouvre pas, et une carte de la
	 * Semaine, qui n'écrit que dans la sienne, n'a pas de liste.
	 */
	public function testSheetSpecies(): void {

		$eCultivation = $this->createCultivationThisWeek($this->getSeries(), $this->getPlant());
		$eCultivationOther = $this->createCultivationThisWeek($this->getSeries(), $this->createPlant());
		$this->createCultivationThisWeek($this->createSeries('Autre série du décor'), $this->createPlant());

		[$data, , $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'sheet' => 1], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la feuille ne s\'ouvre pas') === FALSE) {
			return;
		}

		$ids = $data->cCultivationSeries->getIds();
		sort($ids);

		$expected = [$eCultivation['id'], $eCultivationOther['id']];
		sort($expected);

		$this->assertEquals($expected, $ids, 'la feuille ne propose pas les espèces de sa série que la semaine liste, et seulement elles');

		[$data, , $action] = $this->call('open', ['cultivation' => $eCultivationOther['id'], 'date' => \Clock::date(), 'mode' => 'add', 'sheet' => 1], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'autre espèce ne se rouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals($eCultivationOther['id'], $data->eCultivation['id'], 'la feuille ne se rouvre pas sur l\'autre espèce');

		$action = $this->post($eCultivationOther, ['mode' => 'add', 'harvestMore' => '3', 'sheet' => 1]);
		$this->assertTrue($action instanceof \ViewAction, 'la récolte de l\'autre espèce est refusée : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => [\series\Task::KG => 3.0]], $this->getHarvested($eCultivationOther), 'la récolte ne s\'écrit pas dans l\'autre espèce');
		$this->assertEquals([], $this->getHarvested($eCultivation), 'la récolte de l\'autre espèce s\'écrit dans la culture d\'où l\'on vient');

		$eSeriesClosed = $this->createSeries('Série close du décor');
		$eCultivationClosed = $this->createCultivationThisWeek($eSeriesClosed, $this->createPlant());
		$this->closeSeries($eSeriesClosed);

		if($this->assertDecorPresent(HarvestWeekLib::get($this->eStructure, \Clock::week())->offsetExists($eCultivationClosed['id']) === FALSE, 'la semaine ne liste pas la culture d\'une série close')) {

			[, , $action] = $this->call('open', ['cultivation' => $eCultivationClosed['id'], 'date' => \Clock::date(), 'mode' => 'add', 'sheet' => 1], 'GET');

			$this->assertTrue($action instanceof \NotExpectedAction, 'la feuille se rouvre sur une culture que la semaine ne liste pas');

		}

		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$data, , $action] = $this->call('open', ['cultivation' => $eCultivation['id'], 'mode' => 'add', 'sheet' => 1, 'origin' => 'semaine', 'task' => $eTask['id'], 'unplanned' => '', 'folded' => ''], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la feuille d\'une carte ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals(0, $data->cCultivationSeries->count(), 'la feuille d\'une carte propose une autre espèce, où la carte n\'écrit pas');

	}

	/**
	 * Au tiroir comme à la feuille, le bouton s'éteint sur une saisie sans quantité ni temps (`.empty`, lot GO, R3-7) : un
	 * remplacement vaut dès qu'une quantité est tapée, zéro compris, un ajout dès qu'il porte une quantité ou un
	 * temps. `PlanningHarvestDrawer.check()` tourne sur un formulaire simulé.
	 */
	public function testDrawerCheck(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvestDrawer {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvestDrawer') === FALSE) {
			return;
		}

		$script = '
			const vm = require("vm");
			const context = vm.createContext({
				ResizeObserver: class { observe() {} },
				PlanningHarvestCrate: {read: value => (value === "") ? NaN : parseFloat(value)},
				PlanningHarvestTime: {get: wrapper => wrapper.time}
			});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvestDrawer = PlanningHarvestDrawer;", context);

			const submit = {disabled: false};
			const times = [];
			const form = {
				elements: {mode: {value: "replace"}, harvestDay: {value: ""}, harvestMore: {value: ""}},
				qs: (selector, callback) => callback(submit),
				qsa: (selector, callback) => times.forEach(callback)
			};

			const steps = [];

			for(const [mode, value, time] of [["replace", "", 0], ["replace", "0", 0], ["replace", "3", 0], ["add", "", 0], ["add", "0", 0], ["add", "", 0.25], ["add", "2", 0]]) {
				form.elements.mode.value = mode;
				form.elements.harvestDay.value = value;
				form.elements.harvestMore.value = value;
				times.splice(0, times.length, {time: time});
				vm.runInContext("PlanningHarvestDrawer.check(globalThis.form)", Object.assign(context, {form: form}));
				steps.push(submit.disabled);
			}

			process.stdout.write(JSON.stringify(steps));
		';

		[$read, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($read), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		$this->assertEquals([TRUE, FALSE, FALSE], array_slice($read, 0, 3), 'Remplacer sans quantité n\'éteint pas le bouton, ou une quantité tapée, zéro compris, ne le rallume pas');
		$this->assertEquals([TRUE, TRUE, FALSE, FALSE], array_slice($read, 3), 'Ajouter sans quantité ni temps n\'éteint pas le bouton, ou un temps seul, ou une quantité, ne le rallume pas');

	}

	/**
	 * Une autre unité ne vaut qu'en ajout (remarque 4 de l'architecte), ou en remplacement là où son choix rouvre
	 * la saisie sur elle (`data-planning-harvest-reopen`, lot GO) : passer à Remplacer hors de ce cas rend le
	 * choix à l'unité de la saisie et éteint les autres, ce qui rend `task` et `harvestUnit` (`task()`), et le
	 * retour à l'ajout les rallume sans rien rechoisir. Un couple choisi éteint aussi les
	 * autres unités, pas les caisses, et les caisses d'une autre unité, rouverte, ne valent qu'en remplacement.
	 * `allow()` et `unit()` tournent sur une liste simulée, kilos, caisses, pièces et bottes, les pièces choisies.
	 */
	public function testSheetUnitOtherMode(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvestCrate {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvestCrate') === FALSE) {
			return;
		}

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvestCrate = PlanningHarvestCrate;", context);

			// Seules la liste et ce qu\'elle désigne sont éprouvées.
			const chosen = [];
			vm.runInContext("PlanningHarvestCrate.task = select => globalThis.chosen.push(select.selectedOptions[0].label)", Object.assign(context, {chosen: chosen}));

			const select = {
				selectedIndex: 2,
				dataset: {},
				qs: () => select.options[0],
				get selectedOptions() { return [this.options[this.selectedIndex]]; }
			};
			const options = ["kg", "caisses", "pièces", "bottes"].map((label, index) => ({
				label: label,
				index: index,
				value: (label === "caisses") ? "1" : "0",
				dataset: (index < 2) ? {planningHarvestOwn: "on"} : {},
				disabled: false,
				textContent: label,
				set selected(value) { select.selectedIndex = index; }
			}));
			select.options = options;

			const read = () => [select.selectedOptions[0].label, options.map(option => option.disabled), chosen.at(-1)];

			const steps = [];

			vm.runInContext("PlanningHarvestCrate.allow(globalThis.select, \"replace\")", Object.assign(context, {select: select}));
			steps.push(read());

			vm.runInContext("PlanningHarvestCrate.allow(globalThis.select, \"add\")", context);
			steps.push(read());

			select.selectedIndex = 2;
			vm.runInContext("PlanningHarvestCrate.unit(globalThis.select, {label: \"kg\", crate: true}, false, \"add\")", context);
			steps.push(read());

			vm.runInContext("PlanningHarvestCrate.unit(globalThis.select, {label: \"kg\", crate: true}, true, \"replace\")", context);
			steps.push(read());

			select.dataset.planningHarvestReopen = "/planning/harvestWeek:open?mode=replace";
			select.selectedIndex = 2;
			vm.runInContext("PlanningHarvestCrate.unit(globalThis.select, {label: \"kg\", crate: true}, true, \"replace\")", context);
			steps.push(read());

			vm.runInContext("PlanningHarvestCrate.unit(globalThis.select, {label: \"kg\", crate: true}, false, \"replace\")", context);
			steps.push(read());

			// Rouverte sur les bottes, la liste garde les kilos et leurs caisses en tête, qui désignent la tâche des
			// kilos : en ajout, ces caisses sont éteintes, faute du poids des caisses dans la saisie des bottes.
			const foreign = ["kg", "caisses", "pièces", "bottes"].map((label, index) => ({
				label: label,
				index: index,
				value: (label === "caisses") ? "1" : "0",
				dataset: (label === "bottes") ? {planningHarvestOwn: "on"} : {},
				disabled: false,
				set selected(value) { select.selectedIndex = index; }
			}));
			select.options = foreign;
			select.qs = () => foreign[3];
			select.dataset = {crate: "1", planningHarvestReopen: "/planning/harvestWeek:open?mode=replace"};
			select.selectedIndex = 3;

			const readForeign = () => [select.selectedOptions[0].label, foreign.map(option => option.disabled)];

			vm.runInContext("PlanningHarvestCrate.allow(globalThis.select, \"add\")", context);
			steps.push(readForeign());

			vm.runInContext("PlanningHarvestCrate.allow(globalThis.select, \"replace\")", context);
			steps.push(readForeign());

			select.selectedIndex = 1;
			vm.runInContext("PlanningHarvestCrate.allow(globalThis.select, \"add\")", context);
			steps.push(readForeign());

			process.stdout.write(JSON.stringify(steps));
		';

		[$read, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($read), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		$this->assertEquals(['kg', [FALSE, FALSE, TRUE, TRUE], 'kg'], $read[0], 'Remplacer après les pièces : le choix ne revient pas aux kilos, ou les autres unités ne s\'éteignent pas');
		$this->assertEquals(['kg', [FALSE, FALSE, FALSE, FALSE], 'kg'], $read[1], 'de retour à l\'ajout : les caisses et les autres unités ne se rallument pas, ou la saisie désigne autre chose que les kilos');
		$this->assertEquals(['kg', [FALSE, FALSE, TRUE, TRUE], 'kg'], $read[2], 'un couple choisi : les autres unités ne s\'éteignent pas, ou restent choisies');
		$this->assertEquals(['kg', [FALSE, FALSE, TRUE, TRUE], 'kg'], $read[3], 'Remplacer : les autres unités ne s\'éteignent pas, ou les caisses s\'éteignent');
		$this->assertEquals(['pièces', [FALSE, FALSE, FALSE, FALSE], 'pièces'], $read[4], 'Remplacer en Semaine, où le choix rouvre la saisie : les autres unités s\'éteignent, ou le choix revient aux kilos');
		$this->assertEquals(['kg', [FALSE, FALSE, TRUE, TRUE], 'kg'], $read[5], 'Remplacer en Semaine pour un couple : les autres unités ne s\'éteignent pas');
		$this->assertEquals(['bottes', [FALSE, TRUE, FALSE, FALSE]], $read[6] ?? NULL, 'Ajouter sur une saisie rouverte en bottes : les caisses des kilos ne s\'éteignent pas');
		$this->assertEquals(['bottes', [FALSE, FALSE, FALSE, FALSE]], $read[7] ?? NULL, 'Remplacer sur une saisie rouverte en bottes : les caisses des kilos s\'éteignent, alors que leur choix rouvre la saisie');
		$this->assertEquals(['bottes', [FALSE, TRUE, FALSE, FALSE]], $read[8] ?? NULL, 'passé à l\'ajout, caisses des kilos choisies : le choix ne revient pas aux bottes');

	}

	/**
	 * Le choix d'une autre unité rouvre la saisie (lot GO) en remplacement seulement, sur l'adresse de la liste
	 * et l'intervention ou l'unité de l'option : l'unité de la saisie, ses caisses, l'ajout et une saisie sans
	 * adresse restent sur place, comme « Récolter » sur des séries cochées, qui n'a pas de geste (GT). `reopen()` tourne sur une liste simulée, kilos, caisses, pièces qui
	 * désignent leur intervention et bottes qui se nomment, et `Ajax.Query` relève l'adresse demandée.
	 */
	public function testReopen(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvestCrate {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvestCrate') === FALSE) {
			return;
		}

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvestCrate = PlanningHarvestCrate;", context);

			const asked = [];
			vm.runInContext("String.prototype.setArgument = function(name, value) { return this + \"&\" + name + \"=\" + value; }; globalThis.Ajax = {Query: class { method() { return this; } url(url) { globalThis.asked.push(url); return this; } fetch() {} }};", Object.assign(context, {asked: asked}));

			// Les kilos et leurs caisses, unité de la saisie, puis rouverte sur les bottes, dans l\'ordre de l\'ouverture :
			// les kilos et leurs caisses désignent alors leur intervention.
			const options = [{planningHarvestOwn: "on"}, {planningHarvestOwn: "on"}, {planningHarvestTask: "12"}, {planningHarvestOther: "bunch"}].map((dataset, index) => ({index: index, value: (index === 1) ? "1" : "0", dataset: dataset}));
			const optionsReopened = [{planningHarvestTask: "11"}, {planningHarvestTask: "11"}, {planningHarvestTask: "12"}, {planningHarvestOther: "bunch", planningHarvestOwn: "on"}].map((dataset, index) => ({index: index, value: (index === 1) ? "1" : "0", dataset: dataset}));

			const run = (index, mode, url, list = options) => {
				const select = {
					selectedOptions: [list[index]],
					dataset: (url === null) ? {} : {planningHarvestReopen: url},
					closest: () => null,
					form: {elements: (mode === null) ? {} : {mode: {value: mode}}}
				};
				const count = asked.length;
				const reopened = vm.runInContext("PlanningHarvestCrate.reopen(globalThis.select)", Object.assign(context, {select: select}));
				return [reopened, (asked.length > count) ? asked.at(-1) : null];
			};

			const url = "/planning/harvestWeek:open?mode=replace";

			process.stdout.write(JSON.stringify([
				run(2, "replace", url),
				run(3, "replace", url),
				run(0, "replace", url),
				run(1, "replace", url),
				run(2, "add", url),
				run(2, "replace", null),
				run(0, "replace", url, optionsReopened),
				run(1, "replace", url, optionsReopened),
				run(3, "replace", url, optionsReopened),
				run(3, null, null)
			]));
		';

		[$read, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($read), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		$this->assertEquals([TRUE, '/planning/harvestWeek:open?mode=replace&task=12'], $read[0], 'les pièces ne rouvrent pas la saisie sur leur intervention');
		$this->assertEquals([TRUE, '/planning/harvestWeek:open?mode=replace&harvestUnit=bunch'], $read[1], 'les bottes ne rouvrent pas la saisie sur leur unité');
		$this->assertEquals([[FALSE, NULL], [FALSE, NULL]], [$read[2], $read[3]], 'l\'unité de la saisie ou ses caisses rouvrent la saisie');
		$this->assertEquals([FALSE, NULL], $read[4], 'une autre unité rouvre la saisie en ajout');
		$this->assertEquals([FALSE, NULL], $read[5], 'une autre unité rouvre une saisie sans adresse');
		$this->assertEquals([TRUE, '/planning/harvestWeek:open?mode=replace&task=11'], $read[6], 'rouverte sur les bottes, les kilos en tête ne rouvrent pas la saisie sur leur intervention');
		$this->assertEquals([TRUE, '/planning/harvestWeek:open?mode=replace&task=11&crate=1'], $read[7], 'rouverte sur les bottes, les caisses ne rouvrent pas la saisie sur les kilos, caisses choisies');
		$this->assertEquals([FALSE, NULL], $read[8], 'rouverte sur les bottes, les bottes rouvrent la saisie');
		$this->assertEquals([FALSE, NULL], $read[9], 'une autre unité lève dans un formulaire sans geste, celui de « Récolter » sur des séries cochées');

	}

	/**
	 * Le volet grandi sur un écran bas (844×390, contre-recette de HL par M-Planning) : le corps de la feuille
	 * défile juste assez pour le montrer entier, jusqu'à son haut s'il est plus grand que le corps, remonte
	 * jusqu'à une cible passée au-dessus de ce qu'il montre, et ne bouge pas quand elle tient. `reveal()` tourne sur
	 * des nœuds simulés, aux bornes mesurées dans Chrome.
	 */
	public function testSheetReveal(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningHarvestSheet {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningHarvestSheet') === FALSE) {
			return;
		}

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningHarvestSheet = PlanningHarvestSheet;", context);

			const scrolls = [];

			for(const [top, bottom] of [[120, 300], [150, 333], [100, 600], [40, 192]]) {

				let scrolled = 0;

				const body = {
					getBoundingClientRect: () => ({top: 85, bottom: 314}),
					scrollBy: (x, y) => scrolled += y
				};
				const pane = {getBoundingClientRect: () => ({top: top, bottom: bottom})};

				const sheet = {
					qs: selector => (selector === ".util-sheet-body") ? body : pane
				};

				vm.runInContext("PlanningHarvestSheet.reveal(globalThis.sheet)", Object.assign(context, {sheet: sheet}));
				scrolls.push(scrolled);

			}

			process.stdout.write(JSON.stringify(scrolls));
		';

		[$scrolls, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($scrolls), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		$this->assertEquals(0, $scrolls[0], 'le corps défile alors que le volet tient');
		$this->assertEquals(19, $scrolls[1], 'le volet ne se montre pas entier, ou le corps défile trop');
		$this->assertEquals(15, $scrolls[2], 'le volet plus grand que le corps ne s\'arrête pas sur son haut');
		$this->assertEquals(-45, $scrolls[3], 'une personne au-dessus de ce que montre le corps ne se montre pas');

	}

	/**
	 * Le temps d'une récolte dit le jour où il s'écrit, avec son quantième (R-Retours-0610, point 7), dans le
	 * tiroir et « Nouvelle récolte », qui porte le libellé de chaque jour qu'on peut choisir. Le
	 * premier du mois s'écrit « 1er », comme dans le panneau d'un jour de Temps de travail.
	 */
	public function testTimeDayLabel(): void {

		$ui = new HarvestEntryUi();

		$this->assertEquals('Temps passé lundi 1er', $ui->getTimeDayLabel('2026-06-01', ['today' => FALSE]), 'le premier du mois ne s\'écrit pas « 1er »');
		$this->assertEquals('Temps passé jeudi 24', $ui->getTimeDayLabel('2026-09-24', ['today' => FALSE]), 'le libellé ne porte pas le quantième');
		$this->assertEquals('Temps passé aujourd\'hui', $ui->getTimeDayLabel('2026-09-24', ['today' => TRUE]), 'aujourd\'hui ne se dit pas aujourd\'hui');

		$eCultivation = $this->createCultivation();

		[$data] = $this->call('create', ['cultivation' => $eCultivation['id']], 'GET');

		$panel = $ui->create($this->eStructure, \Clock::week(), $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);
		$group = $document->querySelector('[data-planning-harvest-time-labels]');

		if($this->assertTrue($group !== NULL, 'Nouvelle récolte ne porte pas les libellés des jours') === FALSE) {
			return;
		}

		$expected = [];

		foreach(HarvestWeekLib::getDays(\Clock::week()) as $date => $day) {
			if($day['future'] === FALSE) {
				$expected[$date] = $ui->getTimeDayLabel($date, $day);
			}
		}

		$this->assertEquals($expected, json_decode($group->getAttribute('data-planning-harvest-time-labels'), TRUE), 'Nouvelle récolte ne porte pas le libellé de chaque jour passé de la semaine');

	}

	/**
	 * Le choix du jour du temps propose les jours de la semaine où il peut s'écrire, sans ceux à venir, chacun
	 * avec le titre qu'il donne ; le jour du tiroir est posté dans `timeDate`.
	 */
	public function testDrawerTimeDays(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);
		$days = HarvestWeekLib::getDays(\Clock::week());

		$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), $monday), 2.0, $monday);

		$drawer = $this->openDrawer($eCultivation, $monday);

		if($this->assertTrue($drawer !== NULL, 'le tiroir ne s\'ouvre pas') === FALSE) {
			return;
		}

		$ui = new HarvestEntryUi();

		$labels = [];

		foreach($drawer->querySelectorAll('.planning-harvest-entry-time > .dropdown-list [data-planning-harvest-time-date]') as $item) {
			$labels[$item->getAttribute('data-planning-harvest-time-date')] = $item->getAttribute('data-planning-harvest-time-label');
		}

		$expected = [];

		foreach($days as $date => $day) {
			if($day['future'] === FALSE) {
				$expected[$date] = $ui->getTimeDayLabel($date, $day);
			}
		}

		$this->assertEquals($expected, $labels, 'la liste ne propose pas chaque jour passé de la semaine avec son titre');
		$this->assertEquals($monday, $drawer->querySelector('input[name="timeDate"]')?->getAttribute('value'), 'le jour du temps n\'est pas posté');

	}

	/**
	 * Le temps s'écrit sur son jour (`timeDate`) et la récolte sur celui du tiroir (`date`). Un jour du
	 * temps à venir ou hors de la semaine est refusé, et rien n'est écrit.
	 */
	public function testTimeDate(): void {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'time' => $this->getTimeInput('0.5'), 'timeDate' => $monday]);
		$this->assertTrue($action instanceof \ViewAction, 'un temps posé lundi est refusé : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([\Clock::date() => ['kg' => 2.0]], $this->getHarvested($eCultivation), 'la récolte ne reste pas sur le jour du tiroir');
		$this->assertEquals([$monday => 0.5], $this->getTimeByDate($eCultivation), 'le temps ne part pas sur son jour');

		$eCultivationRefused = $this->createCultivation($this->createSeries('Série refusée'));
		$weekPrevious = toWeek($monday.' - 7 days');

		foreach([
			'Timesheet::date.future' => week_date_day(\Clock::week(), 5),
			'Timesheet::date.week' => week_date_day($weekPrevious, 3),
		] as $fail => $timeDate) {

			$action = $this->post($eCultivationRefused, ['mode' => 'add', 'harvestMore' => '2', 'time' => $this->getTimeInput('0.5'), 'timeDate' => $timeDate]);
			$this->assertTrue(in_array($fail, $this->getFails($action), TRUE), $timeDate.' n\'est pas refusé par '.$fail.' : '.implode(', ', $this->getFails($action)));

		}

		$this->assertEquals([], $this->getHarvested($eCultivationRefused), 'un jour du temps refusé a laissé écrire la récolte');
		$this->assertEquals([], $this->getTimeByDate($eCultivationRefused), 'un jour du temps refusé a écrit du temps');

	}

	/**
	 * La fenêtre des pas ne refuse aucune durée (R-Retours-0810, 210) : la borne de 24 heures se juge au
	 * serveur, sur la somme de la journée, et revient sous la tuile du temps.
	 */
	public function testTimeTooLong(): void {

		$eCultivation = $this->createCultivation();

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'time' => $this->getTimeInput('87')]);
		$this->assertTrue(in_array('Timesheet::time.tooLong', $this->getFails($action), TRUE), '87 heures ne sont pas refusées : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([], $this->getHarvested($eCultivation), '87 heures refusées ont laissé écrire la récolte');
		$this->assertEquals([], $this->getTimeByDate($eCultivation), '87 heures refusées ont été écrites');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('20')]);
		$this->assertTrue($action instanceof \ViewAction, '20 heures sont refusées : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('5')]);
		$this->assertTrue(in_array('Timesheet::time.tooLong', $this->getFails($action), TRUE), '5 heures de plus sur 20 ne sont pas refusées : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([\Clock::date() => 20.0], $this->getTimeByDate($eCultivation), 'le temps refusé a été ajouté à la journée');

		// Le tiroir ne s'ouvre que sur une culture qui a une récolte dans la semaine.
		$eCultivationDrawer = $this->createCultivation($this->createSeries('Série du tiroir'));
		$this->createHarvest($this->createTaskHarvest($eCultivationDrawer, \Clock::week(), week_date_day(\Clock::week(), 1)), 2.0, week_date_day(\Clock::week(), 1));

		$drawer = $this->openDrawer($eCultivationDrawer, \Clock::date());
		$this->assertTrue($drawer?->querySelector('[data-wrapper="timeDate time"]') !== NULL, 'le refus du temps n\'a pas de place dans le tiroir');
		$this->assertEquals(1, count($drawer?->querySelectorAll('[data-wrapper~="time"]') ?? []), 'le refus du temps s\'écrirait deux fois dans le tiroir');

	}

	/**
	 * En remplacement, la durée réglée remplace le temps que la personne a déjà saisi ce jour-là sur
	 * l'intervention que la case écrit (R-Retours-0810, 219, piste A) : en hausse, en baisse, et à zéro la
	 * ligne de temps disparaît. Sans durée postée, le temps reste. En ajout, il s'ajoute toujours.
	 */
	public function testReplaceTime(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		foreach([
			'ajout de 0h30' => [['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('0.5')], 0.5],
			'remplacement par 0h15' => [['mode' => 'replace', 'harvestDay' => '2', 'time' => $this->getTimeInput('0.25')], 0.25],
			'remplacement en hausse' => [['mode' => 'replace', 'harvestDay' => '2', 'time' => $this->getTimeInput('1.5')], 1.5],
			'remplacement sans durée' => [['mode' => 'replace', 'harvestDay' => '3'], 1.5],
			'ajout sur un temps saisi' => [['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('0.5')], 2.0],
		] as $case => [$input, $time]) {

			$action = $this->post($eCultivation, $input);
			$this->assertTrue($action instanceof \ViewAction, $case.' est refusé : '.implode(', ', $this->getFails($action)));
			$this->assertEquals([$eTask['id'] => $time], $this->getTimeByTask($eCultivation), $case.' ne laisse pas le temps attendu');

		}

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '3', 'time' => $this->getTimeInput('0')]);
		$this->assertTrue($action instanceof \ViewAction, 'le remplacement par 0h00 est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(0, \series\Timesheet::model()->whereTask($eTask)->count(), 'le remplacement par 0h00 laisse une ligne de temps');
		$this->assertEquals([\Clock::date() => ['kg' => 3.0]], $this->getHarvested($eCultivation), 'le remplacement par 0h00 touche à la récolte');

	}

	/**
	 * La borne de 24 heures d'un remplacement compte l'écart avec le temps saisi, et non le temps saisi deux
	 * fois.
	 */
	public function testReplaceTimeTooLong(): void {

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$eCultivationOther = $this->createCultivation($this->createSeries('Série voisine'));

		$this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('20')]);
		$this->post($eCultivationOther, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('3')]);

		if($this->assertDecorPresent($this->getTimeByTask($eCultivation) === [$eTask['id'] => 20.0], '20 heures sur la culture et 3 sur sa voisine') === FALSE) {
			return;
		}

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'time' => $this->getTimeInput('21')]);
		$this->assertTrue($action instanceof \ViewAction, '21 heures au lieu de 20, pour 24 dans la journée, sont refusées : '.implode(', ', $this->getFails($action)));

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'time' => $this->getTimeInput('21.5')]);
		$this->assertTrue(in_array('Timesheet::time.tooLong', $this->getFails($action), TRUE), '21h30 au lieu de 21, pour 24h30 dans la journée, ne sont pas refusées : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$eTask['id'] => 21.0], $this->getTimeByTask($eCultivation), 'le remplacement refusé a changé le temps');

	}

	/**
	 * Les pas du tiroir de remplacement partent du temps que la personne a déjà saisi sur l'intervention que la
	 * case écrit, chaque jour passé de la semaine (`data-planning-harvest-spent`), la case en bottes et la case
	 * en kilos chacune du sien ; la durée part avec le formulaire même à zéro. Le tiroir d'ajout part de zéro.
	 */
	public function testReplaceTimeSteps(): void {

		['eCultivation' => $eCultivation, 'eTaskBunch' => $eTaskBunch, 'eTaskKg' => $eTaskKg, 'monday' => $monday, 'tuesday' => $tuesday] = $this->createBunchThenKg();

		$this->post($eCultivation, ['mode' => 'add', 'date' => $tuesday, 'harvestMore' => '', 'time' => $this->getTimeInput('0.5'), 'task' => $eTaskBunch['id']]);
		$this->post($eCultivation, ['mode' => 'add', 'date' => $monday, 'harvestMore' => '', 'time' => $this->getTimeInput('0.75')]);

		if($this->assertDecorPresent($this->getTimeByTask($eCultivation) === [$eTaskBunch['id'] => 0.5, $eTaskKg['id'] => 0.75], '0h30 en bottes mardi, 0h45 en kilos lundi') === FALSE) {
			return;
		}

		$days = array_fill_keys(array_keys(HarvestWeekLib::getDays(\Clock::week())), 0.0);

		foreach([
			'mardi en bottes' => [$tuesday, $eTaskBunch, array_replace($days, [$tuesday => 0.5])],
			'lundi en kilos' => [$monday, new \series\Task(), array_replace($days, [$monday => 0.75])],
		] as $case => [$date, $eTaskOpen, $spent]) {

			$drawer = $this->openDrawerOn($eCultivation, $date, 'replace', $eTaskOpen);

			if($this->assertTrue($drawer !== NULL, 'le tiroir '.$case.' ne s\'ouvre pas') === FALSE) {
				continue;
			}

			$time = $drawer->querySelector('.planning-harvest-time');
			$hidden = $time?->querySelector('input[name="time['.$this->getMember()['id'].']"]');

			$this->assertEquals($spent, array_map(floatval(...), json_decode($time?->getAttribute('data-planning-harvest-spent') ?? '[]', TRUE)), 'le tiroir '.$case.' ne porte pas le temps saisi de son intervention');
			$this->assertEquals($spent[$date], (float)$time?->querySelector('.planning-duration-step')?->getAttribute('data-sent'), 'les pas '.$case.' ne partent pas du temps saisi');
			$this->assertTrue($hidden !== NULL and $hidden->hasAttribute('disabled') === FALSE and (float)$hidden->getAttribute('value') === $spent[$date], 'la durée du tiroir '.$case.' ne part pas avec le formulaire');

		}

		$drawer = $this->openDrawerOn($eCultivation, $tuesday, 'add', $eTaskBunch);
		$time = $drawer?->querySelector('.planning-harvest-time');

		$this->assertTrue($time !== NULL and $time->hasAttribute('data-planning-harvest-spent') === FALSE, 'le tiroir d\'ajout porte le temps saisi');
		$this->assertEquals(0.0, (float)$time?->querySelector('.planning-duration-step')?->getAttribute('data-sent'), 'les pas du tiroir d\'ajout ne partent pas de zéro');

	}

	/**
	 * Le tiroir d'un remplacement montre le temps de l'équipe sur l'intervention de la case (R-Retours-0810,
	 * 224, sur le modèle de la page d'une intervention) : la personne connectée, et chaque personne
	 * qui a du temps sur l'intervention dans la semaine, chacune sous `time[<user>]`, sa tuile partie de son
	 * temps du jour. Les autres membres dont le temps peut s'écrire attendent cachés, champ désactivé, et
	 * « Ajouter une personne » les propose. En ajout, seule la personne connectée est montrée, sa tuile part
	 * de zéro, et les autres restent à ajouter.
	 */
	public function testTeamDrawer(): void {

		$this->addPresenceTeam();

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 1.25);

		$others = array_values(array_diff(\planning\TimeTeamLib::getUsers($this->eStructure)->getIds(), [$eUserOnline['id'], $eUserBruno['id']]));

		if($this->assertDecorPresent($others !== [], 'un troisième membre dont le temps peut s\'écrire') === FALSE) {
			return;
		}

		$drawer = $this->openDrawerOn($eCultivation, \Clock::date(), 'replace');

		if($this->assertTrue($drawer !== NULL, 'le tiroir de remplacement ne s\'ouvre pas') === FALSE) {
			return;
		}

		$people = $this->getPeople($drawer);

		$this->assertEquals(['shown' => TRUE, 'time' => 0.0, 'posted' => TRUE], $people[$eUserOnline['id']] ?? NULL, 'la personne connectée n\'a pas sa tuile à 0h00, postée');
		$this->assertEquals(['shown' => TRUE, 'time' => 1.25, 'posted' => TRUE], $people[$eUserBruno['id']] ?? NULL, 'la personne qui a du temps sur l\'intervention n\'a pas sa tuile à 1h15, postée');

		foreach($others as $user) {
			$this->assertEquals(['shown' => FALSE, 'time' => 0.0, 'posted' => FALSE], $people[$user] ?? NULL, 'un membre sans temps sur l\'intervention n\'attend pas caché, son champ désactivé');
		}

		$this->assertEquals($others, $this->getPeopleAdd($drawer), '« Ajouter une personne » ne propose pas les membres cachés');

		$drawer = $this->openDrawerOn($eCultivation, \Clock::date(), 'add');
		$people = $this->getPeople($drawer);

		$this->assertEquals([$eUserOnline['id']], array_keys(array_filter($people, fn($person) => $person['shown'])), 'le tiroir d\'ajout montre une autre personne que soi');
		$this->assertEquals(array_merge([$eUserBruno['id']], $others), $this->getPeopleAdd($drawer), 'le tiroir d\'ajout ne propose pas les autres membres');
		$this->assertEquals(NULL, $drawer?->querySelector('[data-planning-harvest-spent]'), 'le tiroir d\'ajout porte le temps saisi');

	}

	/**
	 * Un remplacement remplace le temps de chaque personne postée, selon la règle de 219 : en baisse, à zéro,
	 * et pour une personne ajoutée. Un ajout ajoute le temps de chacune, et une durée nulle n'y écrit rien.
	 */
	public function testTeamWrite(): void {

		$this->addPresenceTeam();

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();
		$eUserClaire = $this->getMemberAccounting();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 1.25);

		foreach([
			'Bruno ramené à 0h45' => [['mode' => 'replace', 'harvestDay' => '2', 'time' => [$eUserOnline['id'] => '0', $eUserBruno['id'] => '0.75']], [$eUserBruno['id'] => 0.75]],
			'Claire ajoutée' => [['mode' => 'replace', 'harvestDay' => '2', 'time' => [$eUserOnline['id'] => '0', $eUserBruno['id'] => '0.75', $eUserClaire['id'] => '0.5']], [$eUserBruno['id'] => 0.75, $eUserClaire['id'] => 0.5]],
			'chacun ramené à zéro' => [['mode' => 'replace', 'harvestDay' => '2', 'time' => [$eUserOnline['id'] => '0', $eUserBruno['id'] => '0', $eUserClaire['id'] => '0']], []],
			'ajout pour deux' => [['mode' => 'add', 'harvestMore' => '', 'time' => [$eUserOnline['id'] => '0.5', $eUserBruno['id'] => '0.25', $eUserClaire['id'] => '0']], [$eUserOnline['id'] => 0.5, $eUserBruno['id'] => 0.25]],
		] as $case => [$input, $time]) {

			$action = $this->post($eCultivation, $input);
			$this->assertTrue($action instanceof \ViewAction, $case.' est refusé : '.implode(', ', $this->getFails($action)));
			$this->assertEquals($time, $this->getTimeByUser($eTask), $case.' ne laisse pas le temps attendu à chacun');

		}

		$this->assertEquals([\Clock::date() => ['kg' => 2.0]], $this->getHarvested($eCultivation), 'le temps de l\'équipe touche à la récolte');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => [$eUserBruno['id'] => '0']]);
		$this->assertTrue(in_array('Task::harvestMore.empty', $this->getFails($action), TRUE), 'un ajout sans quantité ni temps n\'est pas refusé : '.implode(', ', $this->getFails($action)));

		// Gérer la structure n'ouvre que son équipe : Émile est membre de F2, pas de F1.
		$eUserStranger = \user\User::model()
			->select('id')
			->whereEmail('decor.f2.admin@example.org')
			->get();

		if($this->assertDecorPresent($eUserStranger->notEmpty(), 'le décor porte le compte decor.f2.admin@example.org') === FALSE) {
			return;
		}

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => [$eUserOnline['id'] => '0', $eUserStranger['id'] => '0.5']]);

		$this->assertEquals(['Timesheet::user.check' => 'time'], $this->getFailWrappers($action), 'le temps d\'un membre d\'une autre structure posté par qui gère n\'est pas refusé par `user.check`');
		$this->assertEquals([$eUserOnline['id'] => 0.5, $eUserBruno['id'] => 0.25], $this->getTimeByUser($eTask), 'le refus a laissé écrire un temps');

		$action = $this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => [$eUserOnline['id'] => '0', 'abc' => '0.5']]);

		$this->assertEquals(['Timesheet::user.check' => 'time'], $this->getFailWrappers($action), 'une personne postée sous un index qui n\'est pas un id n\'est pas refusée sous le temps');

	}

	/**
	 * Les 24 heures d'une journée se comptent par personne, sur l'écart avec son temps sur l'intervention, et
	 * le refus se lit sous sa ligne (`time[<user>]`) : rien ne s'écrit, pour personne.
	 */
	public function testTeamTooLong(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$eCultivationOther = $this->createCultivation($this->createSeries('Série voisine'));
		$eTaskOther = $this->createTaskHarvest($eCultivationOther, \Clock::week(), \Clock::date());

		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 1.0);
		$this->writeTime($eTaskOther, $eUserBruno, \Clock::date(), 22.0);

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'time' => [$eUserOnline['id'] => '0.5', $eUserBruno['id'] => '3']]);

		$this->assertEquals(['Timesheet::time.tooLong' => 'time['.$eUserBruno['id'].']'], $this->getFailWrappers($action), '3 heures au lieu d\'une, qui portent la journée de Bruno à 25, ne sont pas refusées sous sa ligne');
		$this->assertEquals([$eUserBruno['id'] => 1.0], $this->getTimeByUser($eTask), 'le refus de Bruno a laissé écrire un temps');

		$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'time' => [$eUserOnline['id'] => '0.5', $eUserBruno['id'] => '2']]);
		$this->assertTrue($action instanceof \ViewAction, '2 heures au lieu d\'une, qui portent la journée de Bruno à 24 tout juste, sont refusées : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$eUserOnline['id'] => 0.5, $eUserBruno['id'] => 2.0], $this->getTimeByUser($eTask), 'le remplacement de chacun ne s\'est pas écrit');

	}

	/**
	 * Sans le droit d'écrire le temps des autres, le tiroir et la feuille ne montrent que soi, sans « Ajouter une
	 * personne », et le temps d'un autre posté à la main est
	 * refusé par `user.check`. Le cas est celui d'un salarié, qui saisit les récoltes (`Task::canWrite()`, le
	 * planning entier) sans gérer la structure (`Timesheet::canWrite()`) : le décor de F1 n'en a pas, Bruno en
	 * prend le rôle le temps du test (`withRoleEmployee()`).
	 */
	public function testTeamWithoutRight(): void {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$this->writeTime($eTask, $eUserOnline, \Clock::date(), 1.0);

		$this->withRoleEmployee($eUserBruno, function() use($eCultivation, $eTask, $eUserOnline, $eUserBruno) {

			$drawer = $this->openDrawerOn($eCultivation, \Clock::date(), 'replace');

			if($this->assertTrue($drawer !== NULL, 'le tiroir du salarié ne s\'ouvre pas') === FALSE) {
				return;
			}

			$this->assertEquals([$eUserBruno['id']], array_keys($this->getPeople($drawer)), 'le tiroir du salarié montre le temps d\'un autre');
			$this->assertEquals([], $this->getPeopleAdd($drawer), 'le tiroir du salarié propose d\'ajouter une personne');

			$sheet = $this->openSheetOn($eCultivation, \Clock::date(), 'replace');

			$this->assertEquals([$eUserBruno['id']], array_keys($this->getPeople($sheet)), 'la feuille du salarié montre le temps d\'un autre');
			$this->assertSheetPeople($sheet, $eUserBruno, 'la feuille du salarié', menu: FALSE);

			$action = $this->post($eCultivation, ['mode' => 'replace', 'harvestDay' => '2', 'time' => [$eUserBruno['id'] => '0.5', $eUserOnline['id'] => '0']]);

			$this->assertEquals(['Timesheet::user.check' => 'time'], $this->getFailWrappers($action), 'le temps d\'un autre posté par le salarié n\'est pas refusé par `user.check`');
			$this->assertEquals([$eUserOnline['id'] => 1.0], $this->getTimeByUser($eTask), 'le refus a laissé écrire un temps');

		});

	}

	/**
	 * La feuille du tactile montre le temps de l'équipe déroulé (V28d) : une ligne par personne montrée, ses pas
	 * ouverts, le refus de chaque durée sous sa personne et « Ajouter une personne » sous elles. Ouverte en
	 * ajout, elle ne montre que soi, et chaque personne qui a du temps sur l'intervention le dit
	 * (`data-planning-harvest-worked`), pour que le passage au remplacement la montre.
	 */
	public function testTeamSheet(): void {

		$this->addPresenceTeam();

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		$this->writeTime($eTask, $eUserBruno, \Clock::date(), 1.25);

		$sheet = $this->openSheetOn($eCultivation, \Clock::date(), 'replace');

		if($this->assertTrue($sheet !== NULL, 'la feuille de remplacement ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertEquals([$eUserOnline['id'], $eUserBruno['id']], array_keys(array_filter($this->getPeople($sheet), fn($person) => $person['shown'])), 'la feuille de remplacement ne montre pas les deux personnes qui ont du temps');
		$this->assertSheetPeople($sheet, $eUserOnline, 'la feuille de remplacement à deux personnes montrées', menu: TRUE);
		$this->assertTrue($sheet->querySelector('[data-planning-sheet-pane="time"] .planning-harvest-entry-refusal[data-wrapper="time['.$eUserBruno['id'].']"]') !== NULL, 'le refus de la durée de Bruno ne se lit pas sous sa ligne');
		$this->assertEquals(1, count($sheet->querySelectorAll('[data-wrapper~="time['.$eUserBruno['id'].']"]')), 'le refus de la durée de Bruno s\'écrirait deux fois');
		$this->assertTrue($sheet->querySelector('[data-planning-sheet-pane="time"] + .planning-harvest-sheet-refusal[data-wrapper="time"]') !== NULL, 'le refus d\'une durée sans personne ne se lit pas sous le volet');
		$this->assertTrue($sheet->querySelector('[data-planning-sheet-pane="time"] [data-planning-harvest-add]') !== NULL, 'le volet des personnes ne propose pas d\'en ajouter une');
		$this->assertEquals(1.25, $this->getPeople($sheet)[$eUserBruno['id']]['time'] ?? NULL, 'le temps de Bruno ne part pas de ce qu\'il a passé');

		$sheet = $this->openSheetOn($eCultivation, \Clock::date(), 'add');
		$people = $this->getPeople($sheet);

		$this->assertEquals([$eUserOnline['id']], array_keys(array_filter($people, fn($person) => $person['shown'])), 'la feuille ouverte en ajout montre une autre personne que soi');
		$this->assertTrue($sheet?->querySelector('[data-planning-harvest-person="'.$eUserBruno['id'].'"][data-planning-harvest-worked]') !== NULL, 'la feuille ouverte en ajout ne sait pas que Bruno a du temps sur l\'intervention');
		$this->assertSheetPeople($sheet, $eUserOnline, 'la feuille ouverte en ajout', menu: TRUE);

		// Personne d'autre n'a de temps sur cette intervention-ci : le remplacement ne montre que soi.
		$eCultivationAlone = $this->createCultivation($this->createSeries('Série seule'));
		$eTaskAlone = $this->createTaskHarvest($eCultivationAlone, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskAlone, 2.0, \Clock::date());

		$sheet = $this->openSheetOn($eCultivationAlone, \Clock::date(), 'replace');

		$this->assertEquals([$eUserOnline['id']], array_keys(array_filter($this->getPeople($sheet), fn($person) => $person['shown'])), 'la feuille de remplacement montre une personne sans temps sur l\'intervention');
		$this->assertSheetPeople($sheet, $eUserOnline, 'la feuille de remplacement où personne d\'autre n\'a de temps', menu: TRUE);

	}

	/**
	 * Le temps de la feuille se lit déroulé dès l'ouverture (V28d) : les pas de la personne connectée dans le
	 * volet, qui n'attend derrière aucune ligne, et « Ajouter une personne » sous les personnes pour qui peut
	 * écrire le temps des autres (`$menu`).
	 */
	private function assertSheetPeople(?\Dom\Element $sheet, \user\User $eUserOnline, string $case, bool $menu): void {

		$shown = fn(string $selector) => ($sheet?->querySelector($selector) !== NULL and $sheet->querySelector($selector)->closest('.hide') === NULL);

		$this->assertTrue($shown('[data-planning-sheet-pane="time"] [data-planning-harvest-online] .planning-harvest-time > .planning-duration-step-inline ~ input[name="time['.$eUserOnline['id'].']"]'), $case.' ne déroule pas les pas de la personne connectée');
		$this->assertEquals($menu, $shown('[data-planning-sheet-pane="time"] .planning-harvest-time-add'), $case.($menu ? ' ne propose pas' : ' propose').' d\'ajouter une personne');

	}

	/**
	 * Une culture de `$eSeries` sur `$ePlant`, que la semaine liste parce qu'elle y attend une récolte. Le
	 * décor pose la semaine attendue par `build()`, sans l'écran de la culture qui la choisit.
	 */
	private function createCultivationThisWeek(\series\Series $eSeries, \plant\Plant $ePlant): \series\Cultivation {

		$eCultivation = $this->createCultivation($eSeries, $ePlant);

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => [\Clock::week()]]);

		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestWeeksExpected']);

		return $eCultivation;

	}

	/**
	 * Le tiroir d'un ajout sur `$date`, comme le pose `open` à l'ordinateur, NULL quand il ne s'ouvre pas.
	 */
	private function openDrawer(\series\Cultivation $eCultivation, string $date): ?\Dom\Element {

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $date, 'mode' => 'add'], 'GET');

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']).'</body></html>', LIBXML_NOERROR)->querySelector('.planning-harvest-drawer');

	}

	/** Le temps de travail écrit sur la culture, par jour. */
	private function getTimeByDate(\series\Cultivation $eCultivation): array {

		$time = [];

		foreach(\series\Timesheet::model()->select(['date', 'time'])->whereCultivation($eCultivation)->getCollection() as $eTimesheet) {
			$time[$eTimesheet['date']] = ($time[$eTimesheet['date']] ?? 0.0) + (float)$eTimesheet['time'];
		}

		return $time;

	}

	/**
	 * La feuille d'un ajout sur aujourd'hui, comme la pose `open` au tactile, NULL quand elle ne s'ouvre pas.
	 */
	private function openSheet(\series\Cultivation $eCultivation): ?\Dom\Element {

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'sheet' => 1], 'GET');

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

	}

	/** Les arguments de chaque appel d'une méthode `js` de la réponse. */
	private function getJs(array $instructions, string $method): array {

		$calls = [];

		foreach($instructions as $instruction) {

			if($instruction[0] !== 'js') {
				continue;
			}

			foreach($instruction[1] as [$methodCalled, $argumentsCalled]) {

				if($methodCalled === $method) {
					$calls[] = $argumentsCalled[0];
				}

			}

		}

		return $calls;

	}

	/** Tout le HTML qu'une réponse pose, en un document où chercher ce qu'elle rend. */
	private function getDocument(array $instructions): \Dom\HTMLDocument {

		$html = '';

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js') {
				continue;
			}

			foreach($instruction[2] as $call) {
				$html .= implode('', array_filter($call[1] ?? [], 'is_string'));
			}

		}

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * Les nœuds que la réponse redessine en place (`outerHtml`), par sélecteur, dans l'ordre de la réponse.
	 */
	private function getOuterHtml(array $instructions): array {

		$nodes = [];

		foreach($instructions as $instruction) {

			if($instruction[0] !== 'qs') {
				continue;
			}

			foreach($instruction[2] as [$method, $arguments]) {

				if($method === 'outerHtml') {
					$nodes[$instruction[1]] = $arguments[0];
				}

			}

		}

		return $nodes;

	}

	/**
	 * Le nœud tel qu'il s'écrit, sans les identifiants de champ, tirés à chaque rendu (`FormUi`).
	 */
	private function getOuter(?\Dom\Element $node): string {
		return ($node === NULL) ? '' : preg_replace('/field-[0-9a-f]+/', 'field', $node->ownerDocument->saveHtml($node));
	}

	/**
	 * Les nœuds que la réponse éclaire (`highlight`), cherchés dans les lignes qu'elle rend. Chaque sélecteur
	 * part de la liste de la semaine, que ces lignes n'ont pas autour d'elles : il se lit ici sans elle.
	 */
	private function getHighlighted(array $instructions): array {

		$document = $this->getDocument($instructions);
		$nodes = [];

		$list = '#planning-harvest-list[data-week="'.\Clock::week().'"]';

		foreach($this->getJs($instructions, 'highlight') as $selectors) {
			foreach($selectors as $selector) {

				if($this->assertTrue(str_starts_with($selector, $list), 'la réponse éclaire hors de la liste de sa semaine : '.$selector) === FALSE) {
					continue;
				}

				array_push($nodes, ...iterator_to_array($document->querySelectorAll(substr($selector, strpos($selector, ' ') + 1))));

			}
		}

		return $nodes;

	}

	/**
	 * `doHarvestDelete` sur la culture, aujourd'hui sauf `date` donnée, rendu par la vue de `doHarvest` à
	 * laquelle la route répond.
	 */
	private function delete(\series\Cultivation $eCultivation, array $input = []): array {

		$action = $this->executePage('planning/harvestWeek:doHarvestDelete', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [[], $action];
		}

		[, $template] = $this->runView('doHarvest', $action);

		return [$template->formatInstructions(), $action];

	}

	/**
	 * Une culture dont le jour porte 90 kg sans variété et 4 kg d'une variété, sur deux interventions.
	 */
	private function createCultivationPart(): array {

		$eCultivation = $this->createCultivation($this->createSeries('Série à parts '.uniqid()));
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 90.0, \Clock::date());

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $eVariety]);
		$this->createHarvest($eTaskVentilated, 4.0, \Clock::date());

		return [$eCultivation, $eVariety];

	}

	/** Le HTML que la réponse insère dans le nœud nommé. */
	private function getInserted(array $instructions, string $selector): string {

		$html = '';

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js' or $instruction[1] !== $selector) {
				continue;
			}

			foreach($instruction[2] as $call) {

				[$method, $arguments] = $call + [1 => []];

				if($method === 'insertAdjacentHtml') {
					$html .= $arguments[1];
				}

			}

		}

		return $html;

	}

	/**
	 * `doHarvest` sur la culture, aujourd'hui sauf `date` donnée.
	 */
	private function post(\series\Cultivation $eCultivation, array $input): ?\Action {

		[, , $action] = $this->call('doHarvest', $input + [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
		], 'POST');

		return $action;

	}

	/** Le temps de travail écrit sur la culture, par intervention. */
	private function getTimeByTask(\series\Cultivation $eCultivation): array {

		$time = [];

		foreach(\series\Timesheet::model()->select(['task', 'time'])->whereCultivation($eCultivation)->getCollection() as $eTimesheet) {
			$time[$eTimesheet['task']['id']] = ($time[$eTimesheet['task']['id']] ?? 0.0) + (float)$eTimesheet['time'];
		}

		return $time;

	}

	/** Les interventions de récolte de la culture, par identifiant. */
	private function getTasks(\series\Cultivation $eCultivation): \Collection {

		return \series\Task::model()
			->select(['id', 'status', 'harvest', 'harvestUnit', 'plannedDate', 'plannedWeek', 'doneDate', 'doneWeek', 'variety', 'harvestSize'])
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/** Le récolté de la culture, par jour puis par unité, sans les jours revenus à zéro. */
	private function getHarvested(\series\Cultivation $eCultivation): array {

		$cHarvest = \series\Harvest::model()
			->select(['date', 'unit', 'quantity'])
			->whereCultivation($eCultivation)
			->getCollection();

		$harvested = [];

		foreach($cHarvest as $eHarvest) {
			$harvested[$eHarvest['date']][$eHarvest['unit']] = round(($harvested[$eHarvest['date']][$eHarvest['unit']] ?? 0.0) + $eHarvest['quantity'], 3);
		}

		ksort($harvested);

		return array_map(fn($units) => array_filter($units, fn($quantity) => $quantity !== 0.0), $harvested);

	}

	/**
	 * Deux récoltes faites cette semaine, en bottes puis en kilos, comme zzN1 Bottes sur 6861 : la règle de la
	 * semaine retient la dernière faite, en kilos. Le lundi porte 10 bottes et 1 kg, le mardi 1 botte.
	 */
	private function createBunchThenKg(): array {

		$eCultivation = $this->createCultivation();

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);

		$eTaskBunch = $this->createTaskHarvestInUnit($eCultivation, \series\Task::BUNCH, $tuesday);
		$eTaskKg = $this->createTaskHarvestInUnit($eCultivation, \series\Task::KG, $monday);

		$this->createHarvest($eTaskBunch, 10.0, $monday);
		$this->createHarvest($eTaskKg, 1.0, $monday);
		$this->createHarvest($eTaskBunch, 1.0, $tuesday);

		return [
			'eCultivation' => $eCultivation,
			'eTaskBunch' => $eTaskBunch,
			'eTaskKg' => $eTaskKg,
			'monday' => $monday,
			'tuesday' => $tuesday,
		];

	}

	/**
	 * L'intervention que désigne la case d'un jour de la ligne, en remplacement ou en ajout, NULL quand elle
	 * n'en désigne aucune.
	 */
	private function getOpenTask(\Dom\HTMLDocument $document, \series\Cultivation $eCultivation, string $date, string $mode): ?string {

		foreach($document->querySelectorAll('#planning-harvest-'.$eCultivation['id'].' .util-bellows-panel [data-planning-harvest-open]') as $node) {

			parse_str((string)parse_url($node->getAttribute('data-planning-harvest-open'), PHP_URL_QUERY), $arguments);

			if($arguments['date'] === $date and $arguments['mode'] === $mode) {
				return $arguments['task'] ?? NULL;
			}

		}

		return NULL;

	}

	/**
	 * Le tiroir d'un jour, comme l'ouvre sa case à l'ordinateur, NULL quand il ne s'ouvre pas.
	 */
	private function openDrawerOn(\series\Cultivation $eCultivation, string $date, string $mode, \series\Task $eTask = new \series\Task()): ?\Dom\Element {

		$input = ['cultivation' => $eCultivation['id'], 'date' => $date, 'mode' => $mode];

		if($eTask->notEmpty()) {
			$input['task'] = $eTask['id'];
		}

		[, $instructions] = $this->call('open', $input, 'GET');

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, '#planning-harvest-'.$eCultivation['id']).'</body></html>', LIBXML_NOERROR)->querySelector('.planning-harvest-drawer');

	}

	/** Le texte d'un nœud, espaces insécables rendus ordinaires et blancs resserrés. */
	private function getText(\Dom\Element $node): string {
		return preg_replace('/\s+/u', ' ', str_replace(["\u{a0}", "\u{202f}"], ' ', $node->textContent));
	}

	/**
	 * Une intervention de récolte faite de la semaine, dans une unité choisie, sans quantité encore.
	 */
	private function createTaskHarvestInUnit(\series\Cultivation $eCultivation, string $unit, string $date): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_RECOLTE),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::DONE,
			'doneWeek' => toWeek($date),
			'doneDate' => $date,
			'harvest' => NULL,
			'harvestUnit' => $unit,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans build(), qui vérifierait l'action, la catégorie, la semaine et l'unité postées depuis le
		// formulaire d'intervention : le décor veut une intervention faite dans une unité autre que
		// celle de la culture, que la saisie de l'écran ne pose jamais elle-même.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * Claire, consultante sur F1 : elle n'écrit pas le planning, mais son temps s'écrit par qui gère la
	 * structure. Son compte se lit par son courriel, comme celui de Bruno (`getMemberPlanning()`).
	 */
	private function getMemberAccounting(): \user\User {

		return $this->once('memberAccounting', function() {

			$eUser = \user\User::model()
				->select(\user\User::getSelection())
				->whereEmail('decor.f1.comptable@example.org')
				->get();

			if($eUser->empty()) {
				throw new \Exception('Compte decor.f1.comptable@example.org introuvable : monte le décor par dev/test package=structure file=FixtureAi');
			}

			return $eUser;

		});

	}

	/**
	 * Du temps d'une personne sur une intervention, un jour.
	 */
	private function writeTime(\series\Task $eTask, \user\User $eUser, string $date, float $time): void {

		// Sans `build()`, qui vérifierait la durée d'un formulaire : le décor veut exactement ce temps, pour une
		// personne que la personne connectée n'a pas toujours le droit d'écrire.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'date' => $date,
			'time' => $time,
		]));

	}

	/** Le temps de travail écrit sur l'intervention, par personne. */
	private function getTimeByUser(\series\Task $eTask): array {

		$time = [];

		foreach(\series\Timesheet::model()->select(['user', 'time'])->whereTask($eTask)->getCollection() as $eTimesheet) {
			$time[$eTimesheet['user']['id']] = ($time[$eTimesheet['user']['id']] ?? 0.0) + (float)$eTimesheet['time'];
		}

		ksort($time);

		return $time;

	}

	/**
	 * Les personnes d'un tiroir ou d'une feuille, dans l'ordre où elles se lisent : montrée ou cachée, la durée
	 * de son champ et s'il part avec le formulaire.
	 */
	private function getPeople(?\Dom\Element $root): array {

		$people = [];

		foreach($root?->querySelectorAll('[data-planning-harvest-person]') ?? [] as $node) {

			$user = (int)$node->getAttribute('data-planning-harvest-person');
			$hidden = $node->querySelector('input[name="time['.$user.']"]');

			$people[$user] = [
				'shown' => ($node->classList->contains('hide') === FALSE),
				'time' => (float)$hidden?->getAttribute('value'),
				'posted' => ($hidden !== NULL and $hidden->hasAttribute('disabled') === FALSE),
			];

		}

		return $people;

	}

	/**
	 * Les personnes que propose « Ajouter une personne », dans son ordre. Celles qui sont déjà montrées y
	 * attendent cachées, pour un changement de geste de la feuille.
	 */
	private function getPeopleAdd(?\Dom\Element $root): array {

		$users = [];

		foreach($root?->querySelectorAll('[data-planning-harvest-add]:not(.hide)') ?? [] as $node) {
			$users[] = (int)$node->getAttribute('data-planning-harvest-add');
		}

		return $users;

	}

	/**
	 * La feuille d'une saisie sur `$date`, dans le geste donné, comme la pose `open` au tactile.
	 */
	private function openSheetOn(\series\Cultivation $eCultivation, string $date, string $mode): ?\Dom\Element {

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $date, 'mode' => $mode, 'sheet' => 1], 'GET');

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getInserted($instructions, 'body').'</body></html>', LIBXML_NOERROR)->getElementById('planning-harvest-sheet');

	}

	/**
	 * `$callback` joué par `$eUser` connecté, avec le rôle de salarié de F1 (le planning entier, sans
	 * administration), puis son rôle et la personne connectée rendus, même sur un échec.
	 */
	private function withRoleEmployee(\user\User $eUser, \Closure $callback): void {

		$eRole = \structure\Role::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereAdmin(FALSE)
			->wherePlanning(\structure\Role::FULL)
			->whereFqn(NULL)
			->get();

		$eMember = \structure\Member::model()
			->select(['id', 'role'])
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->get();

		if(
			$this->assertDecorPresent($eRole->notEmpty(), 'un rôle de salarié sur F1') === FALSE or
			$this->assertDecorPresent($eMember->notEmpty(), 'l\'adhésion de '.$eUser['email'].' à F1') === FALSE
		) {
			return;
		}

		$eRoleBefore = $eMember['role'];

		$eMember['role'] = $eRole;
		\structure\MemberLib::update($eMember, ['role']);

		$this->connect($eUser);

		try {
			$callback();
		} finally {

			$eMember['role'] = $eRoleBefore;
			\structure\MemberLib::update($eMember, ['role']);

			$this->connect($this->getMember());

		}

	}

	/** La personne en ligne, comme la pose une connexion : le membre en cache est celui de la précédente. */
	private function connect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
