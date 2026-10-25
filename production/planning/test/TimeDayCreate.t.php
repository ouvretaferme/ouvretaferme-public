<?php
namespace planning;

/**
 * « Nouvelle intervention » ouvert depuis le Temps de travail et depuis un jour passé du Jour (retour 293) :
 * « J'ai aussi fait » et « + Intervention » n'ont plus de panneau à eux, ils ouvrent celui des Interventions
 * avec Fait coché et le jour regardé choisi. Ce que le panneau écrit est éprouvé par `TaskCreateTest` ; ce
 * fichier tient ce que la fiche y ajoute : la personne de l'onglet, dont le temps se montre, les droits de sa
 * fiche, et la réponse, qui dessine la fiche que l'on regarde. Que cette réponse soit l'écran d'un
 * rechargement est éprouvé par `FingerprintTest` (`testTimeAlso*`).
 *
 * Le jour du décor est le jeudi de la semaine 39. Alice a le planning complet, Bruno le planning limité.
 *
 * Lancement : dev/test package=planning file=TimeDayCreate
 */
class TimeDayCreateTest extends HarvestFixtureTest {

	/**
	 * Les trois entrées portent l'adresse du panneau des Interventions, Fait demandé : le bouton du bloc vide
	 * et « + Intervention » de la fiche, avec la personne de l'onglet et le jour ouvert, et le « + » d'un jour
	 * passé du Jour, avec ce jour.
	 */
	public function testEntries(): void {

		$today = \Clock::date();
		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno);

		[$document] = $this->renderTimeDay(['user' => $eUserBruno['id']]);

		$this->assertEquals(
			TaskCreateUi::url($this->eStructure, 'timeDay', \Clock::week(), date: $today, status: \series\Task::DONE, eUserTime: $eUserBruno),
			$document->querySelector('#planning-time-none a.btn-primary')?->getAttribute('href'),
			'le bouton du bloc vide n\'ouvre pas « Nouvelle intervention », Fait demandé, pour la personne de l\'onglet et le jour ouvert'
		);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $today);

		$tuesday = week_date_day(\Clock::week(), 2);

		[$document] = $this->renderTimeDay(['user' => $eUserBruno['id'], 'date' => $tuesday]);

		$this->assertEquals(
			TaskCreateUi::url($this->eStructure, 'timeDay', \Clock::week(), date: $tuesday, status: \series\Task::DONE, eUserTime: $eUserBruno),
			$document->querySelector('#planning-time-tools .planning-tools-actions > a')?->getAttribute('href'),
			'« + Intervention » n\'ouvre pas « Nouvelle intervention », Fait demandé, pour la personne de l\'onglet et le jour ouvert'
		);

		[$document] = $this->renderTaskDay();

		$this->assertEquals(
			TaskCreateUi::url($this->eStructure, 'taskDay', \Clock::week(), date: $tuesday, status: \series\Task::DONE),
			$document->querySelector('#planning-day-'.$tuesday.' a[href*="taskDay:create"]')?->getAttribute('href'),
			'le « + » d\'un jour passé du Jour n\'ouvre pas « Nouvelle intervention », Fait demandé, sur ce jour'
		);

	}

	/**
	 * Ouvert par « J'ai aussi fait », le panneau s'ouvre sur Fait et choisit le jour regardé, aujourd'hui compris,
	 * où il serait à faire de lui-même. Le panneau réémet la personne de l'onglet et le jour ouvert, et s'envoie
	 * à la fiche.
	 */
	public function testPanelDone(): void {

		$today = \Clock::date();

		[$data, $document, $panel] = $this->openPanel(['status' => \series\Task::DONE]);

		if($data === NULL) {
			return;
		}

		$this->assertTrue(str_contains($panel->dialogOpen, 'data-ajax-form="/planning/timeDay:doCreate"'), 'le panneau ouvert depuis la fiche ne s\'envoie pas à la fiche');

		$this->assertEquals(\series\Task::DONE, $data->status, '« J\'ai aussi fait » ne s\'ouvre pas sur Fait aujourd\'hui');
		$this->assertEquals($today, $data->date, 'le jour ouvert n\'est pas choisi');

		$this->assertEquals((string)$this->getMember()['id'], $document->querySelector('input[type="hidden"][name="user"]')?->getAttribute('value'), 'le panneau ne réémet pas la personne de l\'onglet');
		$this->assertEquals($today, $document->querySelector('input[type="hidden"][name="date"]')?->getAttribute('value'), 'le panneau ne réémet pas le jour ouvert');
		$this->assertTrue($document->querySelector('[data-autocomplete-url="/planning/taskDay:queryAction"]') !== NULL, 'le champ « Intervention » ne cherche pas parmi toutes les actions');

		// Sans statut demandé, le panneau garde la règle du jour : aujourd'hui propose À faire, et rien n'est touché.
		[$data, $document] = $this->openPanel([]);

		if($data === NULL) {
			return;
		}

		$this->assertEquals(\series\Task::TODO, $data->status, 'sans statut demandé, aujourd\'hui n\'est pas à faire');

	}

	/**
	 * Un jour à venir ne peut pas être fait : l'adresse forcée sur vendredi ouvre À faire à ce jour, et l'envoi
	 * d'une intervention faite vendredi est refusé sans rien créer.
	 */
	public function testFutureDay(): void {

		$friday = week_date_day(\Clock::week(), 5);

		[$data, $document] = $this->openPanel(['status' => \series\Task::DONE, 'date' => $friday]);

		if($data === NULL) {
			return;
		}

		$this->assertEquals(\series\Task::TODO, $data->status, 'un jour à venir s\'ouvre sur Fait');
		$this->assertEquals($friday, $data->date, 'le jour à venir demandé n\'est pas choisi');

		$count = $this->countTasks();

		[, , $action] = $this->callCreate($this->getInput(['day' => $friday, 'date' => $friday]));

		$this->assertEquals(['Task::done.future'], $this->getFails($action), 'une intervention faite un jour à venir n\'est pas refusée');
		$this->assertEquals($count, $this->countTasks(), 'le refus a créé une intervention');

	}

	/**
	 * Depuis l'onglet de Bruno, c'est le temps de Bruno qui se montre, en tête, et Alice, connectée, passe
	 * derrière « Ajouter une personne » : on écrit le temps de la fiche que l'on regarde.
	 */
	public function testPanelUser(): void {

		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno);

		[$data, $document] = $this->openPanel(['status' => \series\Task::DONE, 'user' => $eUserBruno['id']]);

		if($data === NULL) {
			return;
		}

		$eUserAlice = $this->getMember();

		$this->assertEquals($eUserBruno['id'], $data->cUserTime->first()['id'], 'la personne de l\'onglet n\'est pas en tête du temps');
		$this->assertTrue($document->querySelector('[data-planning-harvest-person="'.$eUserBruno['id'].'"][data-planning-harvest-online]:not(.hide) input[name="time['.$eUserBruno['id'].']"]') !== NULL, 'le temps de Bruno n\'est pas montré dans le panneau ouvert depuis sa fiche');
		$this->assertTrue($document->querySelector('[data-planning-harvest-person="'.$eUserAlice['id'].'"].hide:not([data-planning-harvest-online])') !== NULL, 'Alice, connectée, n\'est pas derrière « Ajouter une personne »');
		$this->assertTrue($document->querySelector('[data-planning-harvest-add="'.$eUserAlice['id'].'"]:not(.hide)') !== NULL, '« Ajouter une personne » ne propose pas Alice');
		$this->assertEquals((string)$eUserBruno['id'], $document->querySelector('input[type="hidden"][name="user"]')?->getAttribute('value'), 'le panneau ne réémet pas l\'onglet de Bruno');

	}

	/**
	 * Une récolte se saisit depuis la fiche (retour 293) : « réc » la propose, et faite mardi depuis l'onglet de
	 * Bruno, ouvert sur jeudi, elle prend sa série, sa quantité et le temps de Bruno. La réponse redessine la
	 * case de mardi, où elle est faite, et la ligne entre dans la fiche de Bruno.
	 */
	public function testHarvest(): void {

		$eActionHarvest = $this->getAction(ACTION_RECOLTE);

		[$data, , $action] = $this->callDay('queryAction', ['query' => 'réc']);

		if($this->assertTrue($action instanceof \ViewAction, 'la recherche d\'une intervention est refusée') === FALSE) {
			return;
		}

		$this->assertTrue(
			in_array($eActionHarvest['id'], array_column(TaskCreateUi::getActionResults($data->cCategory, $data->cActionFrequent), 'value')),
			'« réc » ne propose pas la récolte'
		);

		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno);

		$tuesday = week_date_day(\Clock::week(), 2);
		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());

		[$data, $instructions, $action] = $this->callCreate([
			'user' => $eUserBruno['id'],
			'category' => $this->getCategoryCulture()['id'],
			'action' => $eActionHarvest['id'],
			'cultivation' => [$eCultivation['id']],
			'status' => \series\Task::DONE,
			'day' => $tuesday,
			'harvestMore' => '12,5',
			'time' => [$eUserBruno['id'] => '1.5'],
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte faite depuis la fiche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals(
			[$eActionHarvest['id'], $eCultivation['id'], \series\Task::DONE, $tuesday, 12.5],
			$this->readTask($eTask, ['action', 'cultivation', 'status', 'doneDate', 'harvest']),
			'la récolte ne porte pas sa série, mardi et ses 12,5 kg'
		);

		$this->assertEquals(1.5, $this->getTimeWritten($eTask, $eUserBruno, $tuesday), 'le temps de Bruno n\'est pas écrit mardi');
		$this->assertEquals(0.0, $this->getTimeWritten($eTask, $this->getMember(), $tuesday), 'la récolte saisie sur la fiche de Bruno écrit du temps à Alice');

		$this->assertEquals($tuesday, $data->dateDone, 'la réponse ne connaît pas le jour où la récolte est faite');
		$this->assertEquals(
			['#planning-time-tools', '#planning-time-list', '#planning-time-day-'.$tuesday, '#planning-time-week-total', '#planning-time-tasks-day'],
			$this->getSelectors($instructions),
			'la réponse ne redessine pas les outils, la liste, la case de mardi, la somme de la semaine et les chiffres'
		);
		$this->assertTrue($this->isInSheet($data->timeDay, $eTask), 'la récolte n\'entre pas dans la fiche de Bruno');

		// Mardi n'est pas le jour ouvert : sa case se redessine avec ses chiffres à lui, sans devenir le jour choisi.
		// Son champ de durée porte un identifiant tiré à chaque rendu (`FormUi`), écarté de la comparaison.
		$this->assertEquals(
			preg_replace('/field-[0-9a-f]+/', 'field-', new TimeDayUi()->getDay($data->eStructure, $data->week, $tuesday, FALSE, $data->eUserTime, $data->timeDay['days'][$tuesday], $data->timeDay['cWorkingTime'][$tuesday], $data->entry)),
			preg_replace('/field-[0-9a-f]+/', 'field-', $this->getHtml($instructions, '#planning-time-day-'.$tuesday)),
			'la case de mardi ne porte pas le temps de mardi, ou se rend choisie alors que jeudi est ouvert'
		);
		$this->assertTrue($data->timeDay['days'][$tuesday] !== $data->timeDay['days'][$data->date], 'mardi et le jour ouvert ont les mêmes chiffres, la case rendue ne prouve rien');
		$this->assertTrue(in_array(['closePanel', ['#panel-planning-task-create']], array_merge(...array_column(array_filter($instructions, fn(array $instruction) => $instruction[0] === 'js'), 1))), 'la réponse ne ferme pas « Nouvelle intervention »');

	}

	/**
	 * Plusieurs séries donnent plusieurs lignes, ce que l'ancien panneau ne savait pas : chacune entre dans la
	 * fiche, et le temps saisi se répartit entre elles sans se multiplier.
	 */
	public function testSeveral(): void {

		$today = \Clock::date();
		$eUser = $this->getMember();

		$cultivations = [$this->getCultivation()['id'], $this->createCultivation($this->createSeries('Seconde série du décor'))['id']];

		[$data, , $action] = $this->callCreate($this->getInput([
			'cultivation' => $cultivations,
			'time' => [$eUser['id'] => '0.5'],
		]));

		if($this->assertTrue($action instanceof \ViewAction, 'deux séries faites depuis la fiche sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(2, $data->cTaskCreated->count(), 'deux séries ne créent pas deux interventions');

		$time = 0.0;

		foreach($data->cTaskCreated as $eTask) {

			$this->assertTrue($this->isInSheet($data->timeDay, $eTask), 'une des deux lignes neuves n\'entre pas dans la fiche');
			$time += $this->getTimeWritten($eTask, $eUser, $today);

		}

		$this->assertEquals(0.5, round($time, 4), '0h30 saisi sur deux séries ne donne pas 0h30 au total');

	}

	/**
	 * À faire depuis la fiche : la ligne entre dans la liste de la semaine, sans temps, et la réponse ne
	 * redessine aucune case de jour.
	 */
	public function testTodo(): void {

		[$data, $instructions, $action] = $this->callCreate($this->getInput(['status' => \series\Task::TODO, 'day' => '']));

		if($this->assertTrue($action instanceof \ViewAction, 'une intervention à faire depuis la fiche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals([\series\Task::TODO, \Clock::week(), NULL, NULL], $this->readTask($eTask, ['status', 'plannedWeek', 'plannedDate', 'doneDate']), 'l\'intervention à faire n\'est pas posée sans jour dans la semaine');
		$this->assertEquals(NULL, $data->dateDone, 'une intervention à faire a un jour fait');
		$this->assertEquals(
			['#planning-time-tools', '#planning-time-list', '#planning-time-week-total', '#planning-time-tasks-day'],
			$this->getSelectors($instructions),
			'une intervention à faire redessine une case de jour'
		);
		$this->assertTrue($this->isInSheet($data->timeDay, $eTask), 'l\'intervention à faire n\'entre pas dans la liste de la semaine');

	}

	/**
	 * « J'ai aussi fait » sur la semaine précédente, par « Quand » (piste F de R-CreationRapide) : l'intervention
	 * est faite le jour choisi, aucune case de la semaine affichée ne se redessine pour elle, et un message dit
	 * sa semaine.
	 */
	public function testCreatedOnPreviousWeek(): void {

		$weekPrevious = toWeek(week_date_starts(\Clock::week()).' - 7 days');
		$wednesdayPrevious = week_date_day($weekPrevious, 3);

		[$data, $instructions, $action] = $this->callCreate($this->getInput(['when' => $weekPrevious, 'day' => $wednesdayPrevious]));

		if($this->assertTrue($action instanceof \ViewAction, 'une intervention faite la semaine précédente est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $data->cTaskCreated->first();

		$this->assertEquals([\series\Task::DONE, $wednesdayPrevious], $this->readTask($eTask, ['status', 'doneDate']), 'l\'intervention n\'est pas faite le mercredi de la semaine précédente');
		$this->assertEquals([], $this->getSelectors($instructions), 'la réponse redessine la fiche pour une intervention d\'une autre semaine, qui n\'y change rien');
		$this->assertEquals(['week' => week_number($weekPrevious), 'year' => (week_year($weekPrevious) === week_year(\Clock::week())) ? NULL : week_year($weekPrevious), 'count' => 1], $data->success, 'le message ne dit pas la semaine de l\'intervention');

		[, $data] = $this->renderTimeDay();

		$this->assertFalse($this->isInSheet($data->timeDay, $eTask), 'l\'intervention de la semaine précédente entre dans la fiche de la semaine affichée');

	}

	/**
	 * Sous « Masquer ce qui est fait » et « Uniquement assigné à moi », la ligne neuve, faite et sans personne
	 * assignée, reste sous les doigts dans la réponse, et sort de la fiche au rechargement (R-Place).
	 */
	public function testHiddenMine(): void {

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		$this->setHideDone(TRUE);
		\structure\MemberLib::setView('viewPlanningMine', $eStructure, TRUE);

		[$data, , $action] = $this->callCreate($this->getInput());

		if($this->assertTrue($action instanceof \ViewAction, 'une intervention faite sous les deux filtres est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTask = $data->cTaskCreated->first();

		$this->assertTrue($this->isInSheet($data->timeDay, $eTask), 'la ligne neuve ne reste pas dans la réponse sous les deux filtres');

		[, $data] = $this->renderTimeDay();

		$this->assertFalse($this->isInSheet($data->timeDay, $eTask), 'la ligne neuve, faite, reste dans la fiche rechargée sous les deux filtres');

	}

	/**
	 * Le panneau ne s'offre qu'à qui écrit le temps de la fiche : Bruno, au planning limité, n'ouvre ni
	 * n'envoie rien sur l'onglet d'Alice, et sur le sien le panneau s'ouvre exactement quand sa fiche le
	 * propose. L'équipe n'est personne : ni panneau ni envoi.
	 */
	public function testRights(): void {

		$eUserAlice = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno);

		foreach(['GET' => 'create', 'POST' => 'doCreate'] as $method => $route) {

			$action = $this->executePage('planning/timeDay:'.$route, $this->getInput([
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'user' => TimeWeekLib::TEAM,
			]), $method);

			$this->assertTrue($action instanceof \NotExpectedAction, $route.' : « Nouvelle intervention » s\'ouvre ou s\'envoie au nom de l\'équipe');

		}

		\user\ConnectionLib::setOnline($eUserBruno);

		$count = $this->countTasks();

		foreach(['GET' => 'create', 'POST' => 'doCreate'] as $method => $route) {

			$action = $this->executePage('planning/timeDay:'.$route, $this->getInput([
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'user' => $eUserAlice['id'],
				'time' => [$eUserAlice['id'] => '1'],
			]), $method);

			$this->assertFalse($action instanceof \ViewAction, $route.' : Bruno ouvre ou envoie « Nouvelle intervention » sur l\'onglet d\'Alice');

		}

		$this->assertEquals($count, $this->countTasks(), 'Bruno a créé une intervention depuis l\'onglet d\'Alice');

		[$document] = $this->renderTimeDay();

		$action = $this->executePage('planning/timeDay:create', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'status' => \series\Task::DONE,
		], 'GET');

		$this->assertEquals(
			$document->querySelector('#planning-time a[href*="timeDay:create"]') !== NULL,
			$action instanceof \ViewAction,
			'la fiche de Bruno propose « J\'ai aussi fait » sans que le panneau s\'ouvre, ou l\'inverse'
		);

	}

	/**
	 * Le planning complet sans la gestion de la structure n'écrit pas le temps d'un autre
	 * (`Timesheet::canWrite()`) : depuis l'onglet de Bruno, Alice ainsi réduite n'ouvre pas le panneau et
	 * n'envoie rien, même sans temps posté, que `TimeTeamLib::prepare()` n'aurait donc pas à refuser.
	 *
	 * Le rôle est retouché en mémoire sur le membre que les pages relisent, et rendu en fin de méthode
	 * (même raison que `HarvestSalesTest::testDoSalesWithoutSelling()`).
	 */
	public function testRightsFullPlanning(): void {

		$eUserBruno = $this->getMemberPlanning();
		$this->addPresence($eUserBruno);

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);
		$eRole = $eStructure->getMember()['role'];
		$roleBefore = $eRole->extracts(['admin', 'planning']);

		$eRole['admin'] = FALSE;
		$eRole['planning'] = \structure\Role::FULL;

		try {

			if($this->assertDecorPresent(($eStructure->canManage() === FALSE and new \series\Task(['structure' => $eStructure])->canCreate()), 'Alice crée des interventions sans gérer la structure, une fois son rôle retouché') === FALSE) {
				return;
			}

			$count = $this->countTasks();

			foreach(['GET' => 'create', 'POST' => 'doCreate'] as $method => $route) {

				$action = $this->executePage('planning/timeDay:'.$route, $this->getInput([
					'id' => $this->eStructure['id'],
					'week' => \Clock::week(),
					'user' => $eUserBruno['id'],
				]), $method);

				$this->assertTrue($action instanceof \NotAllowedAction, $route.' : le planning complet sans la gestion ouvre ou envoie « Nouvelle intervention » sur l\'onglet de Bruno');

			}

			$this->assertEquals($count, $this->countTasks(), 'une intervention est créée depuis l\'onglet d\'une personne dont on n\'écrit pas le temps');

		} finally {

			foreach($roleBefore as $property => $value) {
				$eRole[$property] = $value;
			}

		}

	}

	/**
	 * L'ancien panneau n'a plus d'adresse : `taskDay:also` et `taskDay:doAlso` ne rendent plus rien.
	 */
	public function testFormerRoutes(): void {

		foreach(['GET' => 'also', 'POST' => 'doAlso'] as $method => $route) {

			$action = $this->executePage('planning/taskDay:'.$route, [
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
			], $method);

			$this->assertFalse($action instanceof \ViewAction, 'l\'ancienne adresse taskDay:'.$route.' répond encore');

		}

	}

	/**
	 * Le panneau ouvert depuis la fiche (`timeDay:create`, rendu par la vue du Jour) : ses données, son corps
	 * lu en document et le panneau, ou trois `NULL` quand il est refusé.
	 */
	private function openPanel(array $input): array {

		$action = $this->executePage('planning/timeDay:create', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, '« Nouvelle intervention » ne s\'ouvre pas depuis la fiche') === FALSE) {
			return [NULL, NULL, NULL];
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime, post: $data->post);

		return [
			$data,
			\Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR),
			$panel
		];

	}

	/**
	 * `timeDay:doCreate` rendu par sa vue : ses données et ses instructions, ou l'action levée à la place
	 * d'une vue.
	 */
	private function callCreate(array $input): array {

		// « Quand » s'ouvre sur la semaine de l'écran : sans `when`, l'intervention part pour elle, comme du panneau tel qu'il s'ouvre.
		$input += ['when' => $input['week'] ?? \Clock::week()];

		$action = $this->executePage('planning/timeDay:doCreate', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doCreate', $action, 'timeDay');

		return [$data, $template->formatInstructions(), $action];

	}

	/** Une plantation faite aujourd'hui sur la série du décor, depuis l'onglet d'Alice ouvert sur aujourd'hui. */
	private function getInput(array $input = []): array {

		return $input + [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => [$this->getCultivation()['id']],
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
		];

	}

	/** Les nœuds qu'une réponse redessine, dans l'ordre. */
	private function getSelectors(array $instructions): array {

		return array_values(array_column(array_filter($instructions, fn(array $instruction) => $instruction[0] === 'qs'), 1));

	}

	/** Le fragment qu'une réponse pose sur un nœud. */
	private function getHtml(array $instructions, string $selector): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {
				return $instruction[2][0][1][0];
			}

		}

		return NULL;

	}

	/** Les valeurs en base d'une intervention, dans l'ordre demandé, une propriété déléguée par son id. */
	private function readTask(\series\Task $eTask, array $properties): array {

		$eTaskRead = \series\Task::model()
			->select($properties)
			->whereId($eTask['id'])
			->get();

		return array_map(fn(string $property) => ($eTaskRead[$property] instanceof \Element) ? $eTaskRead[$property]['id'] : $eTaskRead[$property], $properties);

	}

	private function getTimeWritten(\series\Task $eTask, \user\User $eUser, string $date): float {

		$cTimesheet = \series\Timesheet::model()
			->select('time')
			->whereTask($eTask)
			->whereUser($eUser)
			->whereDate($date)
			->getCollection();

		return round($cTimesheet->sum('time'), 4);

	}

	private function countTasks(): int {

		return \series\Task::model()
			->whereStructure($this->eStructure)
			->count();

	}

}
?>
