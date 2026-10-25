<?php
namespace planning;

/**
 * L'Année de l'écran « Interventions » (R-Annee) : une colonne par semaine de l'année, dont la page ne
 * charge que celles qui entourent la semaine ouverte, le reste arrivant par `weeks`, huit au plus.
 *
 * Le contrat qui compte : une colonne montre exactement la Semaine du même numéro (`TaskWeekLib::get()`),
 * sous le filtre de catégorie et le masque, et le filtre des catégories de l'année compte ce que l'union
 * des semaines compterait, sans les lire toutes. Les gestes (coche, tampon, « Nouvelle intervention »)
 * passent par la vraie route, avec ses validations, dans la transaction du décor (`HarvestFixtureTest`).
 * Que la réponse ciblée soit l'écran d'un rechargement est éprouvé par `FingerprintTest` (`testYear*`).
 *
 * Les états particuliers d'abord : l'intervention présente dans plusieurs semaines, la semaine non chargée,
 * la colonne vidée par le masque, le droit limité, l'autre structure.
 *
 * « Non planifié » de la Semaine n'a pas de place à l'Année (`testUnplannedAbsent`).
 *
 * La recherche (R-Recherche-2) lit toute l'année sans la charger (`TaskSearchLib::getYear()`) : ce qu'elle
 * lit est comparé à ce que porteraient les cartes de l'année chargée entière (`testSearch*`).
 *
 * Le jour du décor est le jeudi de la semaine 39.
 *
 * Lancement : dev/test package=planning file=TaskYear
 */
class TaskYearTest extends HarvestFixtureTest {

	/**
	 * Toutes les semaines de l'année ont leur colonne, et seules celles qui entourent la semaine ouverte
	 * sont chargées. Une colonne chargée porte les interventions de la Semaine du même numéro, dans le
	 * même ordre, et ses comptes. Une intervention faite cette semaine avec du temps la semaine d'avant a
	 * une carte dans les deux colonnes ; une semaine non chargée n'a que sa tête.
	 */
	public function testColumns(): void {

		$weekBefore = $this->getWeek(-1);
		$weekAfter = $this->getWeek(2);

		$eTaskBefore = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);
		$eTaskAfter = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekAfter);
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskDone, 1.0, week_date_day($weekBefore, 2));

		[$document, $data] = $this->renderTaskYear();

		$this->assertEquals(TaskYearLib::getWeeks($this->getSeason()), array_keys($data->columns), 'les colonnes ne sont pas les semaines de l\'année');

		$weeksLoaded = array_keys(array_filter($data->columns, fn(array $column) => $column['loaded']));
		$this->assertEquals(TaskYearLib::getAround(\Clock::week()), $weeksLoaded, 'les semaines chargées ne sont pas celles qui entourent la semaine ouverte');

		foreach($weeksLoaded as $week) {

			$cTaskWeek = TaskWeekLib::get($this->eStructure, $week);

			$this->assertEquals($cTaskWeek->getIds(), $data->columns[$week]['cTask']->getIds(), 'la colonne '.$week.' ne porte pas les interventions de la Semaine');
			$this->assertEquals($cTaskWeek->find(fn($eTask) => $eTask->isTodo())->count(), $data->columns[$week]['todo'], 'la colonne '.$week.' ne compte pas ce qui reste à faire dans la Semaine');

		}

		foreach([[$eTaskBefore, $weekBefore], [$eTaskAfter, $weekAfter], [$eTaskDone, \Clock::week()], [$eTaskDone, $weekBefore]] as [$eTask, $week]) {
			$this->assertTrue($document->getElementById(TaskCardUi::getId($eTask, $week)) !== NULL, 'la carte de l\'intervention '.$eTask['id'].' manque à la semaine '.$week);
		}

		$weekUnloaded = $this->getSeason().'-W10';
		$section = $document->getElementById('planning-year-'.$weekUnloaded);

		if($this->assertTrue($section !== NULL, 'la semaine non chargée n\'a pas de colonne')) {
			$this->assertFalse($section->hasAttribute('data-loaded'), 'la semaine non chargée se dit chargée');
			$this->assertEquals(0, $section->querySelectorAll('[data-task]')->length, 'la semaine non chargée porte des cartes');
		}

	}

	/**
	 * Les mois en liens, chacun vers la première semaine dont le jeudi y tombe.
	 */
	public function testMonths(): void {

		[$document] = $this->renderTaskYear();

		foreach(TaskYearLib::getMonthWeeks($this->getSeason()) as $month => $week) {
			$this->assertTrue($document->querySelector('.planning-year-months a[href="#planning-year-'.$week.'"][data-planning-year-month="'.$month.'"]') !== NULL, 'le mois '.$month.' ne mène pas à sa première semaine '.$week);
		}

	}

	/**
	 * Les semaines lues ensemble se bornent par plages de semaines qui se suivent (`getPeriods()`) : celles qui
	 * les séparent n'en font jamais partie, dans le désordre, en double, et d'une année à la suivante.
	 */
	public function testPeriods(): void {

		$cases = [
			'aucune semaine' => [[], []],
			'une semaine' => [['2026-W10'], [['2026-W10', '2026-W10']]],
			'désordre et doublon' => [['2026-W40', '2026-W03', '2026-W04', '2026-W03'], [['2026-W03', '2026-W04'], ['2026-W40', '2026-W40']]],
			'une semaine sur deux' => [['2026-W30', '2026-W32', '2026-W34'], [['2026-W30', '2026-W30'], ['2026-W32', '2026-W32'], ['2026-W34', '2026-W34']]],
			'semaine 53 puis l\'année suivante' => [['2026-W52', '2026-W53', '2027-W01'], [['2026-W52', '2027-W01']]],
			'année de 52 semaines puis la suivante' => [['2025-W52', '2026-W01'], [['2025-W52', '2026-W01']]],
			'semaine 53 sautée' => [['2026-W52', '2027-W01'], [['2026-W52', '2026-W52'], ['2027-W01', '2027-W01']]],
			'année entière' => [TaskYearLib::getWeeks(2026), [['2026-W01', '2026-W53']]],
		];

		foreach($cases as $case => [$weeks, $periods]) {
			$this->assertEquals($periods, TaskYearLib::getPeriods($weeks), 'les plages ne sont pas celles des semaines demandées, cas '.$case);
		}

	}

	/**
	 * Le chargement d'une semaine rend la colonne entière, ses cartes comprises ; il refuse une liste vide,
	 * plus de huit semaines, une semaine d'une autre année, une valeur qui n'est pas une semaine et un tableau
	 * forgé à la place d'une semaine.
	 */
	public function testWeeks(): void {

		$weeks = [$this->getSeason().'-W10', $this->getSeason().'-W11'];

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weeks[1]);

		[$data, $instructions, $action] = $this->callYear('weeks', ['weeks' => $weeks], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le chargement de deux semaines est refusé') === FALSE) {
			return;
		}

		$this->assertEquals($weeks, $data->change, 'le chargement ne change pas les semaines demandées');

		$replaced = $this->getReplaced($instructions);

		$this->assertEquals(array_map(fn($week) => '#planning-year-'.$week.'[data-load="1"]', $weeks), array_keys($replaced), 'le chargement ne remplace pas les colonnes demandées, et elles seules, sous le jeton du chargement');
		$this->assertTrue(str_contains($replaced['#planning-year-'.$weeks[1].'[data-load="1"]'] ?? '', 'id="'.TaskCardUi::getId($eTask, $weeks[1]).'"'), 'la colonne chargée ne porte pas sa carte');
		$this->assertTrue(str_contains($replaced['#planning-year-'.$weeks[0].'[data-load="1"]'] ?? '', 'data-loaded="1"'), 'la colonne chargée ne se dit pas chargée');

		$refused = [
			'vide' => [],
			'neuf semaines' => array_slice(TaskYearLib::getWeeks($this->getSeason()), 0, TaskYearLib::LOAD_MAX + 1),
			'autre année' => [($this->getSeason() + 1).'-W01'],
			'pas une semaine' => ['abc'],
			'tableau forgé' => [['abc']],
		];

		foreach($refused as $case => $weeksRefused) {
			[, , $action] = $this->callYear('weeks', ['weeks' => $weeksRefused], 'GET');
			$this->assertTrue($action instanceof \NotExpectedAction, 'le chargement est accepté, cas '.$case);
		}

		$loadsRefused = [
			'absent' => NULL,
			'vide' => '',
			'texte' => 'abc',
			'négatif' => '-1',
			'décimal' => '1.5',
			'tableau forgé' => ['1'],
		];

		foreach($loadsRefused as $case => $load) {
			[, , $action] = $this->callYear('weeks', ['weeks' => $weeks, 'load' => $load], 'GET');
			$this->assertTrue($action instanceof \NotExpectedAction, 'le jeton de chargement est accepté, cas '.$case);
		}

	}

	/**
	 * Sous le masque, une colonne dont tout est fait perd ses cartes et garde son compte des faites. La semaine 3 de l'année, loin du jour du décor, n'est pas chargée par la page : elle se
	 * lit par `weeks`, comme le navigateur la lirait.
	 */
	public function testHidden(): void {

		$week = $this->getSeason().'-W03';

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, week_date_day($week, 2));

		$this->setHideDone(TRUE);

		[$data] = $this->callYear('weeks', ['weeks' => [$week]], 'GET');

		if($this->assertEquals(0, $data->columns[$week]['todo'] ?? NULL, 'la semaine 3 de la structure porte des interventions à faire, le cas ne se monte pas') === FALSE) {
			return;
		}

		$this->assertTrue($data->columns[$week]['cTask']->empty(), 'le masque laisse une carte faite');
		$this->assertTrue($data->columns[$week]['hidden'], 'la colonne vidée par le masque ne le sait pas');
		$this->assertTrue($data->columns[$week]['done'] >= 1, 'la colonne vidée par le masque perd son compte des faites');

	}

	/**
	 * La catégorie se cherche dans l'omnibox (V58) : une ancienne adresse qui porte encore `category` montre
	 * toute la semaine, les interventions de la catégorie comme celles des autres, et ses comptes aussi.
	 */
	public function testCategoryIgnored(): void {

		$eTaskCulture = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskOther = $this->createTaskOther(\Clock::week());

		$eCategory = $this->getCategoryCulture();

		[, $data] = $this->renderTaskYear(input: ['category' => $eCategory['id']]);

		$cTaskWeek = TaskWeekLib::get($this->eStructure, \Clock::week());
		$column = $data->columns[\Clock::week()];

		$this->assertTrue($column['cTask']->offsetExists($eTaskCulture['id']) and $column['cTask']->offsetExists($eTaskOther['id']), 'une adresse qui porte une catégorie retire des interventions de la semaine');
		$this->assertEquals($cTaskWeek->getIds(), $column['cTask']->getIds(), 'la colonne ne porte pas toutes les interventions de la semaine');
		$this->assertEquals($cTaskWeek->find(fn($eTask) => $eTask->isTodo())->count(), $column['todo'], 'la colonne ne compte pas toutes les catégories');

	}

	/**
	 * La coche d'une carte d'une autre semaine que celle de l'écran : l'intervention est faite dans la
	 * semaine de sa carte, la réponse redessine cette semaine et le compte de l'onglet, et la carte y porte
	 * le tampon.
	 */
	public function testDone(): void {

		$week = $this->getWeek(2);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		[$data, $instructions, $action] = $this->callYear('doDone', ['task' => $eTask['id'], 'week' => $week]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTaskAfter = \series\TaskLib::getById($eTask['id']);

		$this->assertTrue($eTaskAfter->isDone(), 'la coche n\'écrit pas l\'intervention faite');
		$this->assertEquals($week, $eTaskAfter['doneWeek'], 'la coche ne date pas l\'intervention de la semaine de sa carte');
		$this->assertTrue(in_array($week, $data->change, TRUE), 'la coche ne redessine pas la semaine de sa carte');

		$replaced = $this->getReplaced($instructions);

		$this->assertEquals(\planning\TaskYearLib::CARD, $data->granularity, 'la coche dans sa semaine ne répond pas à la carte');

		foreach($data->change as $weekChanged) {
			$this->assertTrue(in_array(['qs', '#planning-year-'.$weekChanged, [['removeAttribute', ['data-load']]]], $instructions, TRUE), 'la coche ne retire pas le jeton de chargement de la semaine '.$weekChanged);
		}
		$this->assertTrue(str_contains($replaced['#'.TaskCardUi::getId($eTask, $week)] ?? '', 'data-planning-task-todo="'.$eTask['id'].'"'), 'la carte cochée ne porte pas le tampon');
		$this->assertTrue(array_key_exists('#planning-year-'.$week.'[data-loaded] .planning-day-title', $replaced), 'la coche ne redessine pas la tête de la colonne');

	}

	/**
	 * Sous le masque, la carte qu'on vient de cocher reste à l'écran, et celle cochée avant elle aussi
	 * quand l'écran la réémet (`shown`) : la colonne redessinée ne les fait pas disparaître sous la main.
	 */
	public function testDoneShownUnderMask(): void {

		$week = $this->getWeek(1);

		$eTaskFirst = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
		$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$this->setHideDone(TRUE);

		$this->callYear('doDone', ['task' => $eTaskFirst['id'], 'week' => $week]);
		[$data] = $this->callYear('doDone', ['task' => $eTaskSecond['id'], 'week' => $week, 'shown' => [$eTaskFirst['id']]]);

		$cTask = $data->columns[$week]['cTask'] ?? new \Collection();

		$this->assertTrue($cTask->offsetExists($eTaskSecond['id']), 'la carte qu\'on vient de cocher disparaît sous le masque');
		$this->assertTrue($cTask->offsetExists($eTaskFirst['id']), 'la carte cochée avant, réémise par l\'écran, disparaît sous le masque');

		[$dataReload] = $this->callYear('weeks', ['weeks' => [$week]], 'GET');
		$this->assertFalse($dataReload->columns[$week]['cTask']->offsetExists($eTaskFirst['id']), 'le rechargement montre une carte faite sous le masque');

	}

	/**
	 * Le tampon : l'intervention redevient à faire, et la réponse redessine la semaine où elle était faite.
	 */
	public function testTodo(): void {

		$week = $this->getWeek(-1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, week_date_day($week, 2));

		[$data, $instructions, $action] = $this->callYear('doTodo', ['task' => $eTask['id'], 'week' => $week]);

		if($this->assertTrue($action instanceof \ViewAction, 'le tampon est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue(\series\TaskLib::getById($eTask['id'])->isTodo(), 'le tampon n\'écrit pas l\'intervention à faire');
		$this->assertTrue(in_array($week, $data->change, TRUE), 'le tampon ne redessine pas la semaine où l\'intervention était faite');
		$this->assertTrue(str_contains(implode('', $this->getReplaced($instructions)), 'data-planning-task-done="'.$eTask['id'].'"'), 'la carte remise à faire ne porte pas l\'empreinte');

	}

	/**
	 * « Nouvelle intervention » sur une semaine : l'intervention est à faire dans cette semaine, et la
	 * réponse redessine cette semaine seule.
	 */
	public function testCreate(): void {

		$week = $this->getWeek(3);

		[$data, $instructions, $action] = $this->callYear('doCreate', [
			'week' => $week,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
			'description' => '',
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'la création est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = \series\Task::model()
			->select(['id', 'status', 'plannedWeek'])
			->whereStructure($this->eStructure)
			->whereCultivation($this->getCultivation())
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertEquals(\series\Task::TODO, $eTask['status'], 'l\'intervention neuve n\'est pas à faire');
		$this->assertEquals($week, $eTask['plannedWeek'], 'l\'intervention neuve n\'est pas prévue dans la semaine de sa colonne');
		$this->assertEquals([$week], $data->change, 'la création ne redessine pas la seule semaine de sa colonne');
		$this->assertTrue(str_contains($this->getReplaced($instructions)['#planning-year-'.$week] ?? '', 'id="'.TaskCardUi::getId($eTask, $week).'"'), 'la colonne redessinée ne porte pas l\'intervention neuve');

	}

	/**
	 * « Quand » déplace l'intervention hors de la colonne où le panneau s'est ouvert (piste F de
	 * R-CreationRapide) : la réponse redessine la semaine choisie, sans message, tant qu'elle est de l'année
	 * affichée ; sur l'année suivante, elle ne redessine rien et un message dit la semaine et son année.
	 */
	public function testCreateOtherWeek(): void {

		$week = $this->getWeek(3);
		$when = $this->getWeek(5);

		[$data, , $action] = $this->callYear('doCreate', [
			'week' => $week,
			'when' => $when,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
			'description' => '',
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'la création sur une autre semaine de l\'année est refusée : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals($when, $data->cTaskCreated->first()['plannedWeek'], 'l\'intervention ne part pas pour la semaine choisie');
			$this->assertEquals([$when], $data->change, 'la création ne redessine pas la semaine choisie');
			$this->assertEquals(NULL, $data->success, 'une intervention de l\'année affichée reçoit un message');
		}

		$whenNext = toWeek(week_date_starts($when).' + 1 year');

		[$data, $instructions, $action] = $this->callYear('doCreate', [
			'week' => $week,
			'when' => $whenNext,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'day' => '',
			'description' => '',
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'la création sur l\'année suivante est refusée : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals([], $data->change, 'la création sur l\'année suivante redessine une semaine de l\'année affichée');
			$this->assertEquals([], array_keys($this->getReplaced($instructions)), 'la réponse redessine une colonne pour une intervention de l\'année suivante');
			$this->assertEquals(['week' => week_number($whenNext), 'year' => week_year($whenNext), 'count' => 1], $data->success, 'le message ne dit pas la semaine et l\'année de l\'intervention');
		}

	}

	/**
	 * Le panneau de l'Année ouvre l'état demandé par « + À faire » et « + Fait » de la rangée des outils (piste F
	 * de R-CreationRapide), et à faire depuis le « + » d'une semaine, qui n'en demande pas : Fait, sur la semaine
	 * en cours, prend aujourd'hui.
	 */
	public function testCreateStatus(): void {

		foreach([
			'sans état' => [[], \series\Task::TODO, NULL],
			'à faire' => [['status' => \series\Task::TODO], \series\Task::TODO, NULL],
			'fait' => [['status' => \series\Task::DONE], \series\Task::DONE, \Clock::date()],
		] as $case => [$input, $status, $date]) {

			[$data, , $action] = $this->callYear('create', $input + ['current' => \Clock::week()], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, 'le panneau '.$case.' est refusé') === FALSE) {
				continue;
			}

			$this->assertEquals([$status, $date], [$data->status, $data->date], 'le panneau '.$case.' n\'ouvre pas son état sur son jour');

		}

	}

	/**
	 * Un geste dont la semaine de l'écran n'est pas de l'année de la carte est refusé sans rien écrire.
	 */
	public function testCurrentOutOfYear(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[, , $action] = $this->callYear('doDone', ['task' => $eTask['id'], 'current' => ($this->getSeason() + 1).'-W01']);

		$this->assertTrue($action instanceof \NotExpectedAction, 'une semaine d\'écran d\'une autre année est acceptée');
		$this->assertTrue(\series\TaskLib::getById($eTask['id'])->isTodo(), 'le geste refusé est écrit');

	}

	/**
	 * Bruno, au planning limité, voit l'Année ; sa coche postée quand même est refusée sans rien écrire.
	 */
	public function testLimited(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderTaskYear();

		$this->assertTrue($document->getElementById('planning-year') !== NULL, 'Bruno, au planning limité, ne voit pas l\'Année');

		[, $instructions, $action] = $this->callYear('doDone', ['task' => $eTask['id']]);

		$this->assertFalse($action instanceof \ViewAction, 'la coche de Bruno, au planning limité, est acceptée');
		$this->assertEquals([], $instructions, 'la coche refusée de Bruno redessine l\'écran');
		$this->assertTrue(\series\TaskLib::getById($eTask['id'])->isTodo(), 'la coche refusée de Bruno est écrite');

	}

	/**
	 * L'intervention d'une autre structure ne se coche pas depuis l'Année de celle-ci.
	 */
	public function testForeign(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eTaskForeign = $this->createTaskForeign();

		[, , $action] = $this->callYear('doDone', ['task' => $eTaskForeign['id']]);

		$this->assertFalse($action instanceof \ViewAction, 'la coche de l\'intervention d\'une autre structure est acceptée');
		$this->assertEquals(\series\Task::TODO, \series\Task::model()->select('status')->whereId($eTaskForeign['id'])->getValue('status'), 'la coche refusée a écrit sur l\'intervention d\'une autre structure');

	}

	/**
	 * Une saison que la structure n'accepte pas n'a pas d'Année.
	 */
	public function testSeasonRefused(): void {

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/annee/1990-W10', [], 'GET');

		$this->assertTrue($action instanceof \NotExpectedAction, 'l\'Année de 1990 s\'ouvre');

	}

	/**
	 * L'Année ne montre pas « Non planifié » de la Semaine : une intervention à faire sans semaine prévue ne
	 * paraît dans aucune colonne, et les routes de l'ancienne zone ne répondent plus.
	 */
	public function testUnplannedAbsent(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);
		$eTaskOther = $this->createTaskOther(NULL);

		$keys = TaskLateLib::getKeys($this->eStructure, TaskLateLib::UNPLANNED, \Clock::week())->getIds();

		if($this->assertDecorPresent(in_array($eTask['id'], $keys, TRUE) and in_array($eTaskOther['id'], $keys, TRUE), 'les deux interventions sans semaine sont dans « Non planifié » de la Semaine') === FALSE) {
			return;
		}

		$weeks = TaskYearLib::getWeeks($this->getSeason());
		$found = [];

		foreach(TaskYearLib::getColumns($this->eStructure, $weeks, $weeks, FALSE) as $week => $column) {
			foreach([$eTask, $eTaskOther] as $eTaskUnplanned) {
				if($column['cTask']->offsetExists($eTaskUnplanned['id'])) {
					$found[] = $eTaskUnplanned['id'].' en '.$week;
				}
			}
		}

		$this->assertEquals([], $found, 'une intervention sans semaine prévue paraît dans une colonne de l\'Année');

		foreach(['unplanned', 'more'] as $route) {
			[, , $action] = $this->callYear($route, [], 'GET');
			$this->assertFalse($action instanceof \ViewAction, 'la route taskYear:'.$route.' de l\'ancienne zone répond encore');
		}

	}

	/**
	 * Une intervention sans série, emplacement ni espèce, que nomme sa méthode, la prend pour titre dans sa
	 * semaine.
	 */
	public function testCardMethod(): void {

		$eAction = $this->getAction(ACTION_TRAITEMENT);

		$eMethod = new \work\Method([
			'structure' => $this->eStructure,
			'action' => $eAction,
			'name' => 'Binage',
		]);

		// Sans `build()`, qui vérifierait le nom et l'action saisis : le décor fixe les deux, l'action étant
		// celle de l'intervention que la méthode doit nommer.
		\work\MethodLib::create($eMethod);

		// `askCollection()` lit les méthodes dans un cache du processus, qu'une méthode créée après ne rejoint pas.
		new \ReflectionProperty(\work\MethodCrud::class, 'cache')->setValue(NULL, []);

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $eAction,
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
			'methods' => [$eMethod['id']],
		]);

		// Sans `build()`, qui vérifierait une saisie : le décor veut une intervention que seule sa méthode
		// nomme, sans série, emplacement ni espèce, ce que le formulaire ne produit pas.
		\series\TaskLib::create($eTask);

		[$document] = $this->renderTaskYear();

		$node = $document->querySelector('#'.TaskCardUi::getId($eTask, \Clock::week()).' .planning-task-name');

		if($this->assertTrue($node !== NULL, 'l\'intervention nommée par sa méthode n\'a pas de titre dans sa semaine') === FALSE) {
			return;
		}

		$title = trim(preg_replace('/\s+/u', ' ', $node->textContent));

		$this->assertTrue(str_contains($title, 'Binage'), 'la carte ne dit plus sa méthode : '.$title);

	}

	/**
	 * Sans série, emplacement, espèce ni complément, la carte prend la première ligne de sa description,
	 * sans sa marque de liste, et l'action seulement sans description.
	 */
	public function testCardDescription(): void {

		$week = \Clock::week();

		$eTaskList = $this->createTaskDescribed($week, "o lundi : ouvrir la boutique\no jeudi : livraison");
		$eTaskText = $this->createTaskDescribed($week, 'Récupérer la commande');
		$eTaskEmpty = $this->createTaskDescribed($week, NULL);

		[$document] = $this->renderTaskYear();

		$title = function(\series\Task $eTask, string $week) use($document): ?string {
			$node = $document->querySelector('#'.TaskCardUi::getId($eTask, $week).' .planning-task-name');
			return ($node === NULL) ? NULL : trim(preg_replace('/\s+/u', ' ', $node->textContent));
		};

		$action = $eTaskList['action']['name'];

		$this->assertEquals('lundi : ouvrir la boutique', $title($eTaskList, $week), 'la carte d\'une liste ne prend pas sa première ligne, sans marque');
		$this->assertEquals('Récupérer la commande', $title($eTaskText, $week), 'la carte ne prend pas le début de sa description');
		$this->assertEquals($action, $title($eTaskEmpty, $week), 'la carte sans description ne prend plus son action');

	}

	/**
	 * Le titre que la carte prend de sa description est la valeur que la recherche en tire
	 * (`TaskSearchLib::getValues()`), comme le détail du jour (`\series\Task::getDescriptionStart()`) : la
	 * première ligne écrite, une ligne vide en tête sautée, sans la marque de liste « o » ou « x » ni le tiret
	 * d'une liste, que suit une espace : un tiret collé est un signe, qui reste.
	 */
	public function testCardDescriptionSearch(): void {

		$week = \Clock::week();

		$titles = [
			'Ouvrir la boutique' => $this->createTaskDescribed($week, "o Ouvrir la boutique\no Livrer"),
			'Commande récupérée' => $this->createTaskDescribed($week, "x Commande récupérée\nFacture à classer"),
			'Vérifier la serre' => $this->createTaskDescribed($week, "\n  \nVérifier la serre\nPuis l'arroser"),
			'Arroser les semis' => $this->createTaskDescribed($week, "- Arroser les semis\n- Fermer la serre"),
			'-5 °C cette nuit' => $this->createTaskDescribed($week, "-5 °C cette nuit\nRentrer les plants"),
		];

		[$document, $data] = $this->renderTaskYear();

		foreach($titles as $title => $eTask) {

			$node = $document->querySelector('#'.TaskCardUi::getId($eTask, $week).' .planning-task-name');
			$eTaskCard = $data->columns[$week]['cTask'][$eTask['id']] ?? new \series\Task();

			if($this->assertDecorPresent($node !== NULL and $eTaskCard->notEmpty(), 'la carte de l\'intervention '.$eTask['id'].' dans la semaine '.$week) === FALSE) {
				return;
			}

			$this->assertEquals($title, trim($node->textContent), 'la carte de l\'intervention '.$eTask['id'].' ne prend pas la première ligne écrite de sa description');
			$this->assertEquals($title, TaskSearchLib::getValues($eTaskCard, new \Collection(), TaskSearchLib::getCategories($this->eStructure))['action'][1] ?? NULL, 'la recherche ne tire pas de l\'intervention '.$eTask['id'].' le titre de sa carte');

		}

	}

	private function createTaskDescribed(?string $week, ?string $description): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => $week,
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
			'description' => $description,
		]);

		// Sans `build()`, qui exigerait une description à une intervention sans série ni espèce : le décor
		// veut aussi celle qui n'en a pas, que l'ancien planning a laissé créer.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * R-Recherche-2 · Ce que la recherche de l'Année lit sans charger l'année (`TaskSearchLib::getYear()`)
	 * est ce que porteraient les cartes de toutes ses colonnes chargées : les mêmes semaines, les mêmes
	 * interventions, les mêmes valeurs. Le décor pose les cas où une lecture allégée diverge :
	 * l'intervention affectée sans temps dans sa semaine et commencée par un autre membre dans une autre,
	 * la faite avec du temps la semaine d'avant, la titrée par sa note.
	 */
	public function testSearchYear(): void {

		[$eTaskAffected, $eTaskDone, $eTaskOther] = $this->createSearchDecor();

		$year = TaskSearchLib::getYear($this->eStructure, \Clock::week(), FALSE, []);

		$this->assertEquals($this->getSearchOracle(FALSE, []), $this->sortSearch($year), 'la recherche de l\'Année ne lit pas ce que porteraient les cartes de l\'année chargée entière');

		$this->assertEquals([$this->getMemberPlanning()->getName()], $year[$this->getWeek(1)][$eTaskAffected['id']]['user'] ?? NULL, 'dans sa semaine prévue, l\'intervention affectée sans temps ne porte pas la seule personne affectée');
		$this->assertTrue(in_array($this->getMember()->getName(), $year[$this->getWeek(-1)][$eTaskAffected['id']]['user'] ?? [], TRUE), 'dans la semaine de son temps, l\'intervention ne porte pas la personne qui a du temps');
		$this->assertTrue(isset($year[$this->getWeek(-2)][$eTaskDone['id']]) and isset($year[\Clock::week()][$eTaskDone['id']]), 'l\'intervention faite avec du temps la semaine d\'avant n\'est pas dans ses deux semaines');
		$this->assertTrue(in_array('Témoin hors des cultures', $year[$this->getWeek(1)][$eTaskOther['id']]['action'] ?? [], TRUE), 'l\'intervention titrée par sa note ne porte pas la note');
		$this->assertEquals([$this->getCategoryCulture()['name']], $year[$this->getWeek(-2)][$eTaskDone['id']]['category'] ?? NULL, 'l\'intervention ne porte pas le nom de sa catégorie');

	}

	/**
	 * R-Recherche-2 · Le jeton d'une catégorie (V58) écarte les autres interventions de toutes les semaines,
	 * et sous le masque la recherche lit encore ce que les cartes porteraient : il écarte ce qui est commencé
	 * sauf la carte que l'écran réémet (`shown`).
	 */
	public function testSearchYearFiltered(): void {

		[, $eTaskDone, $eTaskOther, $eTaskOtherPlanned, $eTaskHidden] = $this->createSearchDecor();

		$eCategory = $this->getCategoryCulture();

		$matches = TaskSearchLib::getMatches(TaskSearchLib::getYear($this->eStructure, \Clock::week(), FALSE, []), ['category' => [$eCategory['name']]]);

		$this->assertTrue(in_array($eTaskDone['id'], $matches[\Clock::week()] ?? [], TRUE), 'le jeton de la catégorie ne retient pas une intervention de la catégorie');
		$this->assertFalse(in_array($eTaskOther['id'], $matches[$this->getWeek(1)] ?? [], TRUE), 'le jeton de la catégorie retient une intervention d\'une autre catégorie dans la semaine d\'une intervention de la catégorie');
		$this->assertFalse(isset($matches[$this->getWeek(3)]) and in_array($eTaskOtherPlanned['id'], $matches[$this->getWeek(3)], TRUE), 'le jeton de la catégorie retient une intervention d\'une autre catégorie dans sa semaine');

		$year = TaskSearchLib::getYear($this->eStructure, \Clock::week(), TRUE, [$eTaskDone['id']]);

		$this->assertEquals($this->getSearchOracle(TRUE, [$eTaskDone['id']]), $this->sortSearch($year), 'sous le masque, la recherche ne lit pas ce que porteraient les cartes');
		$this->assertFalse(isset($year[$this->getWeek(2)][$eTaskHidden['id']]), 'sous le masque, la recherche garde une intervention faite');
		$this->assertTrue(isset($year[\Clock::week()][$eTaskDone['id']]), 'sous le masque, la recherche écarte la carte faite que l\'écran réémet');

	}

	/**
	 * R-Recherche-2 · Les suggestions de l'Année viennent du serveur, par groupe, filtrées par ce qui est
	 * tapé sans tenir compte de la casse ni des accents, comme l'omnibox filtre les siennes. La réponse est
	 * au format de l'omnibox, un libellé par groupe.
	 */
	public function testSearchQuery(): void {

		$this->createSearchDecor();

		[$data, , $action] = $this->callYear('query', ['query' => 'TEMOIN']);

		if($this->assertTrue($action instanceof \ViewAction, 'les suggestions de l\'Année sont refusées') === FALSE) {
			return;
		}

		$this->assertTrue(in_array('Témoin hors des cultures', $data->suggestions['action'], TRUE), 'les suggestions ne retrouvent pas la note sans sa casse ni son accent');

		foreach(TaskSearchLib::GROUPS as $group) {

			$values = array_filter($data->suggestions[$group], fn(string $value) => str_contains(TaskSearchLib::normalize($value), 'temoin') === FALSE);

			$this->assertEquals([], array_values($values), 'les suggestions du groupe '.$group.' gardent des valeurs qui ne correspondent pas à la saisie');

		}

		$this->assertTrue(in_array(['group' => new TabUi()->getSearchGroups()['action'], 'label' => 'Témoin hors des cultures'], new TabUi()->getSearchSuggestions($data->suggestions, $data->cUser, $data->cPlant), TRUE), 'la réponse n\'est pas au format de l\'omnibox');

		[$data] = $this->callYear('query', ['query' => '']);

		$this->assertTrue(in_array($this->getPlant()['name'], $data->suggestions['plant'] ?? [], TRUE), 'sans saisie, les suggestions ne proposent pas l\'espèce du décor');
		$this->assertTrue(in_array($this->getMemberPlanning()->getName(), $data->suggestions['user'] ?? [], TRUE), 'sans saisie, les suggestions ne proposent pas la personne affectée sans temps');

		[$data] = $this->callYear('query', ['query' => $this->getCategoryCulture()['name']]);

		$this->assertTrue(in_array($this->getCategoryCulture()['name'], $data->suggestions['category'] ?? [], TRUE), 'les suggestions ne proposent pas la catégorie tapée');

	}

	/**
	 * R-Recherche-2 · Les interventions que les jetons retiennent dans toute l'année : par semaine, sans
	 * les semaines vides. Deux jetons de groupes différents se cumulent.
	 */
	public function testSearchMatches(): void {

		[$eTaskAffected, , $eTaskOther] = $this->createSearchDecor();

		[$data, $instructions, $action] = $this->callYear('search', ['criteria' => ['action' => ['témoin hors des cultures']]], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la recherche de l\'Année est refusée') === FALSE) {
			return;
		}

		$this->assertTrue(in_array($eTaskOther['id'], $data->matches[$this->getWeek(1)] ?? [], TRUE), 'la recherche ne retrouve pas l\'intervention titrée par sa note');
		$this->assertFalse(in_array([], $data->matches, TRUE), 'la recherche rend une semaine sans résultat');

		$criteria = [
			'user' => [$this->getMemberPlanning()->getName()],
			'plant' => [$this->getPlant()['name']],
		];

		[$data] = $this->callYear('search', ['criteria' => $criteria], 'GET');

		$this->assertTrue(in_array($eTaskAffected['id'], $data->matches[$this->getWeek(1)] ?? [], TRUE), 'la recherche ne retrouve pas l\'intervention affectée dans sa semaine');
		$this->assertFalse(in_array($eTaskOther['id'], $data->matches[$this->getWeek(1)] ?? [], TRUE), 'deux jetons ne se cumulent pas');

	}

	/**
	 * R-Recherche-2 · Une recherche forgée est refusée : un groupe inconnu, une valeur qui n'est pas un
	 * libellé, l'Année d'une autre structure.
	 */
	public function testSearchRefused(): void {

		foreach([
			['variety' => ['Planche 4']],
			['action' => [['x']]],
			['action' => 'Plantation'],
			['action' => []],
		] as $criteria) {

			[, , $action] = $this->callYear('search', ['criteria' => $criteria], 'GET');
			$this->assertTrue($action instanceof \NotExpectedAction, 'la recherche accepte des jetons forgés : '.json_encode($criteria));

		}

		if($this->requireStructureForeign()) {

			[, , $action] = $this->callYear('query', ['query' => '', 'id' => $this->getStructureForeign()['id']]);
			$this->assertFalse($action instanceof \ViewAction, 'les suggestions lisent l\'Année d\'une autre structure');

		}

	}

	/**
	 * R-Recherche-2 · La carte de l'Année porte ce que la recherche lit sur l'élément, pour une intervention
	 * de culture comme pour une intervention titrée par sa note : la valeur vient d'une seule dérivation
	 * (`TaskSearchLib::fill()`).
	 */
	public function testSearchCard(): void {

		[$eTaskAffected, , $eTaskOther] = $this->createSearchDecor();

		[$document, $data] = $this->renderTaskYear();

		$week = $this->getWeek(1);
		$node = $document->querySelector('#'.TaskCardUi::getId($eTaskAffected, $week).' .planning-task-main');

		if($this->assertTrue($node !== NULL, 'la carte de l\'intervention affectée n\'est pas dans sa colonne') === FALSE) {
			return;
		}

		$this->assertEquals($data->columns[$week]['cTask'][$eTaskAffected['id']]['search'], json_decode($node->getAttribute('data-search'), TRUE), 'la carte de l\'Année ne porte pas ce que la recherche lit sur l\'élément');

		$node = $document->querySelector('#'.TaskCardUi::getId($eTaskOther, $week).' .planning-task-main');

		if($this->assertTrue($node !== NULL, 'la carte de l\'intervention titrée par sa note n\'est pas dans sa colonne') === FALSE) {
			return;
		}

		$this->assertEquals($data->columns[$week]['cTask'][$eTaskOther['id']]['search'], json_decode($node->getAttribute('data-search'), TRUE), 'la carte titrée par sa note ne porte pas ce que la recherche lit sur l\'élément');

		$this->assertTrue($document->querySelector('#planning-year #planning-search[data-suggestions-url]') !== NULL, 'la rangée de l\'Année ne porte pas l\'omnibox à suggestions du serveur');
		$this->assertTrue($document->querySelector('#planning-year .planning-search-empty.hide') !== NULL, 'l\'écran vide de la recherche n\'est pas posé masqué');

	}

	/**
	 * EB · Le groupe Emplacement porte ce que la carte écrit (`TaskCardUi::getPlace()`) : la parcelle et
	 * chaque planche d'une série en planches, « parcelle › planche » ; la zone pour une parcelle qui la
	 * remplit, et la parcelle seule pour une planche qui la remplit ; la parcelle seule d'une série en blocs ;
	 * l'emplacement propre d'une intervention sans série ; rien pour une intervention sans série ni
	 * emplacement. Les noms sont ceux que l'assolement écrit (`CultivationUi::displayPlaces()`).
	 */
	public function testSearchPlaceValues(): void {

		$decor = $this->createPlaceDecor();
		[$ePlot, $eBedA, $eBedB, $ePlotFilled, $eZoneFilled] = $decor['places'];

		$week = $this->getWeek(1);
		$year = TaskSearchLib::getYear($this->eStructure, \Clock::week(), FALSE, []);

		$expected = [
			$ePlot['name'],
			$ePlot['name'].' › '.$eBedA['name'],
			$ePlot['name'].' › '.$eBedB['name'],
			$ePlotFilled['name'],
			$eZoneFilled['name'],
		];

		$values = $year[$week][$decor['taskBed']['id']]['place'] ?? NULL;

		if($this->assertDecorPresent($values !== NULL, 'l\'intervention de la série en planches paraît dans sa semaine') === FALSE) {
			return;
		}

		sort($expected);
		sort($values);

		$this->assertEquals($expected, $values, 'la recherche ne propose pas la parcelle et chaque planche de la série en planches');

		$eSeries = \series\Series::model()
			->select(['id', 'use', 'cccPlace' => \series\PlaceLib::delegateBySeries()])
			->whereId($decor['taskBed']['series'])
			->get();

		$text = html_entity_decode(strip_tags(new \series\CultivationUi()->displayPlaces($eSeries['use'], $eSeries['cccPlace'])));

		foreach([$ePlot['name'], $eBedA['name'], $eBedB['name'], $ePlotFilled['name'], $eZoneFilled['name']] as $name) {
			$this->assertTrue(str_contains($text, $name), 'la recherche propose « '.$name.' », que l\'assolement n\'écrit pas');
		}

		$this->assertEquals([$ePlot['name']], $year[$week][$decor['taskBlock']['id']]['place'] ?? NULL, 'une série en blocs propose autre chose que sa parcelle');
		$this->assertEquals([$ePlot['name'], $ePlot['name'].' › '.$eBedA['name']], $year[$this->getWeek(3)][$decor['taskOwn']['id']]['place'] ?? NULL, 'une intervention sans série ne propose pas son propre emplacement');
		$this->assertEquals([], $year[$week][$decor['taskNone']['id']]['place'] ?? NULL, 'une intervention sans série ni emplacement propose un emplacement');

	}

	/**
	 * EB · Un jeton de parcelle retient toutes les interventions de ses planches, un jeton de planche celles
	 * de cette planche seulement, et l'un comme l'autre écarte une intervention sans emplacement. Les
	 * suggestions de l'Année proposent la planche tapée sous son libellé « parcelle › planche ».
	 */
	public function testSearchPlaceMatches(): void {

		$decor = $this->createPlaceDecor();
		[$ePlot, $eBedA, $eBedB] = $decor['places'];

		$taskBed = $decor['taskBed']['id'];
		$taskBedB = $decor['taskBedB']['id'];
		$week = $this->getWeek(1);

		[$data, , $action] = $this->callYear('search', ['criteria' => ['place' => [$ePlot['name']]]], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la recherche par emplacement est refusée') === FALSE) {
			return;
		}

		$this->assertTrue(in_array($taskBed, $data->matches[$week] ?? [], TRUE), 'le jeton de la parcelle écarte l\'intervention sur deux de ses planches');
		$this->assertTrue(in_array($taskBedB, $data->matches[$week] ?? [], TRUE), 'le jeton de la parcelle écarte l\'intervention sur une seule de ses planches');
		$this->assertFalse(in_array($decor['taskNone']['id'], $data->matches[$week] ?? [], TRUE), 'le jeton de la parcelle garde une intervention sans emplacement');

		// Sans casse, comme l'omnibox compare ses jetons (`TaskSearchLib::normalize()`).
		[$data] = $this->callYear('search', ['criteria' => ['place' => [mb_strtoupper($ePlot['name'].' › '.$eBedA['name'])]]], 'GET');

		$this->assertTrue(in_array($taskBed, $data->matches[$week] ?? [], TRUE), 'le jeton de la planche écarte l\'intervention qui y est');
		$this->assertFalse(in_array($taskBedB, $data->matches[$week] ?? [], TRUE), 'le jeton de la planche garde l\'intervention d\'une autre planche de la parcelle');
		$this->assertFalse(in_array($decor['taskNone']['id'], $data->matches[$week] ?? [], TRUE), 'le jeton de la planche garde une intervention sans emplacement');

		$this->assertTrue(in_array($decor['taskOwn']['id'], $data->matches[$this->getWeek(3)] ?? [], TRUE), 'le jeton de la planche écarte l\'intervention sans série qui y est assolée');

		[$data] = $this->callYear('search', ['criteria' => ['place' => [$ePlot['name'].' › '.$eBedA['name']], 'plant' => [$this->getPlant()['name']]]], 'GET');

		$this->assertTrue(in_array($taskBed, $data->matches[$week] ?? [], TRUE), 'les jetons de la planche et de l\'espèce écartent l\'intervention qui porte les deux');
		$this->assertFalse(in_array($decor['taskOwn']['id'], $data->matches[$this->getWeek(3)] ?? [], TRUE), 'le jeton de l\'espèce ne s\'ajoute pas à celui de la planche');

		[$data] = $this->callYear('query', ['query' => $eBedA['name']]);

		$this->assertEquals([$ePlot['name'].' › '.$eBedA['name']], $data->suggestions['place'] ?? NULL, 'les suggestions ne proposent pas la planche tapée sous son libellé « parcelle › planche »');

		$suggestions = new TabUi()->getSearchSuggestions($data->suggestions, $data->cUser, $data->cPlant);

		$this->assertTrue(in_array(['group' => 'Emplacement', 'label' => $ePlot['name'].' › '.$eBedA['name']], $suggestions, TRUE), 'la suggestion de la planche n\'est pas au format de l\'omnibox, ou porte une vignette');

	}

	public function testSearchNormalize(): void {

		$this->assertEquals('suivante ?', TaskSearchLib::normalize("Suivante\u{00A0}?"), 'l\'espace insécable sépare encore ce qu\'une espace tapée rapproche');
		$this->assertEquals('reponse !', TaskSearchLib::normalize("Réponse\u{202F}!"), 'l\'espace insécable fine sépare encore ce qu\'une espace tapée rapproche');
		$this->assertEquals('suivante', TaskSearchLib::normalize("Suivante\u{00A0}"), 'une espace insécable au bord reste dans la forme comparée');

	}

	/**
	 * Le décor du groupe Emplacement, dans une parcelle et une zone créées pour lui :
	 * - une série en planches sur les planches A et B d'une parcelle, sur une parcelle remplie par sa planche, et
	 *   sur une zone remplie par sa parcelle, avec une intervention la semaine suivante ;
	 * - une série en planches sur la planche B seule, son intervention la même semaine ;
	 * - une série en blocs sur la parcelle, son intervention la même semaine ;
	 * - une intervention sans série assolée sur la planche A, trois semaines plus tard ;
	 * - une intervention sans série ni emplacement, la semaine des séries.
	 */
	private function createPlaceDecor(): array {

		$ePlot = $this->createPlot();
		$eBedA = $this->createBed($ePlot);
		$eBedB = $this->createBed($ePlot);

		$ePlotFilled = $this->createPlot();
		$eZoneFilled = $this->createZone();

		$eBedPlotFill = \map\Bed::model()
			->select(['id'])
			->wherePlot($ePlotFilled)
			->wherePlotFill(TRUE)
			->get();

		$eBedZoneFill = \map\Bed::model()
			->select(['id'])
			->whereZone($eZoneFilled)
			->whereZoneFill(TRUE)
			->get();

		$eSeriesBed = $this->createSeriesBed('Série EB planches A et B');

		foreach([$eBedA, $eBedB, $eBedPlotFill, $eBedZoneFill] as $eBed) {
			$this->createPlace($eSeriesBed, $eBed, 10);
		}

		$eSeriesBedB = $this->createSeriesBed('Série EB planche B');
		$this->createPlace($eSeriesBedB, $eBedB, 10);

		$eSeriesBlock = $this->createSeries('Série EB en blocs');
		$this->createPlace($eSeriesBlock, $eBedB, 10);

		// L'assolement d'une intervention sans série, comme la page de l'assolement le prépare et l'écrit
		// (`series/page/place.p.php`).
		$eTaskOwn = $this->createTaskOther($this->getWeek(3));
		$eTaskOwn['season'] = $this->getSeason();
		$eTaskOwn['use'] = \series\Series::BED;
		$eTaskOwn['bedWidth'] = NULL;
		\series\PlaceLib::replaceForTask($eTaskOwn, \series\PlaceLib::buildFromBeds('task', $eTaskOwn, [$eBedA['id']], [$eBedA['id'] => 10]));

		return [
			'places' => [$ePlot, $eBedA, $eBedB, $ePlotFilled, $eZoneFilled],
			'taskBed' => $this->createTask($this->createCultivation($eSeriesBed), ACTION_PLANTATION, \series\Task::TODO, $this->getWeek(1)),
			'taskBedB' => $this->createTask($this->createCultivation($eSeriesBedB), ACTION_PLANTATION, \series\Task::TODO, $this->getWeek(1)),
			'taskBlock' => $this->createTask($this->createCultivation($eSeriesBlock), ACTION_PLANTATION, \series\Task::TODO, $this->getWeek(1)),
			'taskOwn' => $eTaskOwn,
			'taskNone' => $this->createTaskOther($this->getWeek(1)),
		];

	}

	/**
	 * Le décor de la recherche de l'Année : une intervention à faire la semaine suivante, affectée sans
	 * temps et commencée deux semaines plus tôt par un autre membre ; une faite cette semaine avec du temps
	 * deux semaines avant ; une hors culture, titrée par sa note, la semaine de l'affectée, et sa jumelle
	 * deux semaines plus tard ; une faite qui ne paraît que sous le masque levé.
	 */
	private function createSearchDecor(): array {

		$eTaskAffected = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeek(1));
		// Affectée par le geste du produit.
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskAffected['id'])]), $this->getMemberPlanning(), 'add');
		$this->createTimesheet($eTaskAffected, 0.5, week_date_day($this->getWeek(-1), 2));

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheet($eTaskDone, 1.0, week_date_day($this->getWeek(-2), 2));

		$eTaskOther = $this->createTaskOther($this->getWeek(1));
		$eTaskOtherPlanned = $this->createTaskOther($this->getWeek(3));

		$eTaskHidden = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $this->getWeek(2), week_date_day($this->getWeek(2), 2));

		return [$eTaskAffected, $eTaskDone, $eTaskOther, $eTaskOtherPlanned, $eTaskHidden];

	}

	/**
	 * L'oracle : ce que porteraient les cartes de toutes les colonnes chargées de l'année, par les lectures
	 * de l'écran.
	 */
	private function getSearchOracle(bool $hide, array $tasksShown): array {

		$weeks = TaskYearLib::getWeeks($this->getSeason());

		$cards = [];

		foreach(TaskYearLib::getColumns($this->eStructure, $weeks, $weeks, $hide, $tasksShown) as $week => $column) {
			foreach($column['cTask'] as $eTask) {
				$cards[$week][$eTask['id']] = $eTask['search'];
			}
		}

		return $this->sortSearch($cards);

	}

	/**
	 * L'ordre des cartes d'une semaine est celui de l'écran, que la recherche ne lit pas : on compare les
	 * ensembles.
	 */
	private function sortSearch(array $year): array {

		ksort($year);

		foreach($year as &$cards) {
			ksort($cards);
		}

		return $year;

	}

	/**
	 * La semaine de l'année à `$offset` semaines du jour du décor, qui reste dans l'année : la semaine 39 a
	 * de la marge des deux côtés.
	 */
	private function getWeek(int $offset): string {

		$weeks = TaskYearLib::getWeeks($this->getSeason());

		return $weeks[array_search(\Clock::week(), $weeks, TRUE) + $offset];

	}

	/**
	 * Une intervention à faire hors culture, d'une autre catégorie que celle du décor et d'une action de
	 * cette catégorie, pour éprouver le filtre. Sans semaine, elle n'est prévue nulle part.
	 */
	private function createTaskOther(?string $week): \series\Task {

		[$eCategory, $eAction] = $this->once('categoryOther', function() {

			foreach(\work\CategoryLib::getByStructure($this->eStructure) as $eCategory) {

				if($eCategory->is($this->getCategoryCulture())) {
					continue;
				}

				$cAction = \work\ActionLib::getByStructure($this->eStructure, category: $eCategory);

				if($cAction->notEmpty()) {
					return [$eCategory, $cAction->first()];
				}

			}

			$this->assertFail('la structure '.static::STRUCTURE.' n\'a aucune action hors de la catégorie des cultures');

			return [new \work\Category(), new \work\Action()];

		});

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $eAction,
			'category' => $eCategory,
			'description' => 'Témoin hors des cultures',
			'status' => \series\Task::TODO,
			'plannedWeek' => $week,
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, qui vérifierait une saisie : le décor pose une intervention dont il veut exactement
		// la catégorie, l'action et la semaine, que le formulaire ne choisirait pas autrement.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * Le HTML que chaque instruction `outerHtml` pose, par sélecteur, dans l'ordre de la réponse.
	 */
	private function getReplaced(array $instructions): array {

		$replaced = [];

		foreach($instructions as $instruction) {

			// Une instruction `js` n'a que deux membres, sans sélecteur.
			if($instruction[0] === 'js') {
				continue;
			}

			foreach($instruction[2] as [$method, $arguments]) {
				if($method === 'outerHtml') {
					$replaced[$instruction[1]] = $arguments[0];
				}
			}

		}

		return $replaced;

	}

}
?>
