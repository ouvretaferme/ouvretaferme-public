<?php
namespace planning;

/**
 * Les zones « En retard » et « Non planifié » sous la fiche de temps (CF, retour 292) : la population, les
 * titres, le repli et les comptes des Interventions (`TaskLateLib`), « Uniquement assigné à moi », les
 * lignes de la fiche, la saisie sur une ligne de zone, « Non planifié » lu à sa première ouverture,
 * « Voir les N autres », le bloc vide de la semaine qui ne compte que la liste, et l'absence de zones un
 * jour à venir et sur l'onglet Équipe. Que la réponse ciblée soit l'écran d'un rechargement est éprouvé par
 * `FingerprintTest` (`testTimeZone*`).
 *
 * Le décor est posé dans la transaction du test (`HarvestFixtureTest`), le jeudi de la semaine 39.
 *
 * Lancement : dev/test package=planning file=TimeDayZone
 */
class TimeDayZoneTest extends HarvestFixtureTest {

	/**
	 * Les deux zones se rendent sous la liste, « En retard » dépliée puis « Non planifié » repliée et non lue,
	 * avec les titres et les comptes des Interventions. Une intervention en retard qui porte du temps cette
	 * semaine est dans la liste, pas dans la zone. Une ligne de zone a la forme d'une ligne de la fiche : son
	 * champ de temps et son rond.
	 */
	public function testSameAsInterventions(): void {

		$week = \Clock::week();

		$eTaskLate = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$eTaskLateTimed = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$this->createTimesheet($eTaskLateTimed, 0.5, \Clock::date());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		[$document, $data] = $this->renderTimeDay();

		$zones = $document->querySelectorAll('#planning-time-list + #planning-time-zones > .util-fold');

		if($this->assertEquals(2, $zones->length, 'la fiche ne porte pas les deux zones sous la liste') === FALSE) {
			return;
		}

		$this->assertEquals(['planning-time-late', 'planning-time-unplanned'], [$zones->item(0)->id, $zones->item(1)->id], 'les zones ne sont pas « En retard » puis « Non planifié »');
		$this->assertTrue($zones->item(0)->classList->contains('util-fold-open'), '« En retard » n\'est pas dépliée');
		$this->assertFalse($zones->item(1)->classList->contains('util-fold-open'), '« Non planifié » n\'est pas repliée');
		$this->assertEquals('', $document->getElementById('planning-time-unplanned-board')?->innerHTML, '« Non planifié » est lue avant sa première ouverture');

		foreach([TaskLateLib::LATE => 'En retard', TaskLateLib::UNPLANNED => 'Non planifié'] as $name => $title) {

			$count = TaskLateLib::getZone($this->eStructure, $name, $week, TRUE)['count'];

			$this->assertEquals($title.' '.$count, trim($document->querySelector('#planning-time-'.$name.' .util-fold-head h3')?->textContent ?? ''), $name.' : le titre ou le compte n\'est pas celui des Interventions');

		}

		$row = $document->querySelector('#planning-time-late-board #planning-time-task-'.$eTaskLate['id']);

		if($this->assertTrue($row !== NULL, 'l\'intervention en retard n\'a pas de ligne dans sa zone') === FALSE) {
			return;
		}

		$this->assertTrue($row->querySelector('.planning-time-time .planning-duration-step-window') !== NULL, 'la ligne de zone n\'a pas le champ de temps de la fiche');
		$this->assertTrue($row->querySelector('.planning-time-validate [data-planning-task-done]') !== NULL, 'la ligne de zone n\'a pas le rond de la fiche');
		$this->assertEquals('', $row->querySelector('.planning-time-subtitle')?->textContent ?? '', 'la ligne de zone porte un jour');

		$this->assertTrue($document->querySelector('#planning-time-zones #planning-time-task-'.$eTaskLateTimed['id']) === NULL, 'l\'intervention en retard qui porte du temps cette semaine est dans une zone');
		$this->assertTrue($this->isInSheet($data->timeDay, $eTaskLateTimed), 'l\'intervention en retard qui porte du temps cette semaine n\'est pas dans la liste');
		$this->assertEquals(1, $document->querySelectorAll('#planning-time-task-'.$eTaskLateTimed['id'])->length, 'l\'intervention qui porte du temps n\'est pas une seule fois à l\'écran');

	}

	/**
	 * « Non planifié » se lit à sa première ouverture, avec ses lignes, et dit aux gestes de le réémettre.
	 */
	public function testUnplannedOpen(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		[, $instructions] = $this->callTimeDayGet('unplanned', []);

		$html = $this->getInstruction($instructions, '#planning-time-unplanned-board');

		$this->assertTrue(str_contains($html, 'id="planning-time-task-'.$eTask['id'].'"'), 'la première ouverture de « Non planifié » ne rend pas sa ligne');
		$this->assertTrue(str_contains($html, 'id="planning-time-unplanned-board"'), 'la première ouverture ne remplace pas la table de la zone');

		$action = $this->executePage($this->getTimeDayPath(), ['unplanned' => '1'], 'GET');
		[, , $page] = $this->runView('timeDay', $action, 'timeDay');

		$this->assertTrue(str_contains($page, 'data-unplanned="1"'), 'l\'écran ne dit pas que « Non planifié » est lu');

	}

	/**
	 * « Uniquement assigné à moi » ne garde dans les zones que les interventions prévues pour la personne de
	 * l'onglet, et le compte suit.
	 */
	public function testMine(): void {

		$eTaskMine = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$eTaskOther = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		$eTaskMine['plannedUsers'] = [];
		\series\TaskLib::updateUser(new \Collection([$eTaskMine]), $this->getMember(), 'add');

		\structure\MemberLib::setView('viewPlanningMine', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		[$document, $data] = $this->renderTimeDay();

		$this->assertTrue($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTaskMine['id']) !== NULL, 'le filtre retire l\'intervention prévue pour la personne');
		$this->assertTrue($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTaskOther['id']) === NULL, 'le filtre garde une intervention prévue pour personne');

		$count = TaskLateLib::getZone($this->eStructure, TaskLateLib::LATE, \Clock::week(), TRUE, $this->getMember())['count'];

		$this->assertEquals($count, $data->zones[TaskLateLib::LATE]['count'], 'le compte ne suit pas le filtre');
		$this->assertEquals((string)$count, $document->querySelector('#planning-time-late .util-fold-head .util-counter')?->textContent, 'le compte affiché ne suit pas le filtre');

	}

	/**
	 * Une semaine passée sans rien dans la liste garde son bloc vide, et les zones se rendent dessous : le
	 * bloc vide ne compte que la liste de la semaine (BS).
	 */
	public function testEmptyWeek(): void {

		$week = toWeek(\Clock::date().' - 28 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 35 DAYS'));

		$action = $this->executePage($this->getTimeDayPath($week), [], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la fiche d\'une semaine passée ne se rend pas') === FALSE) {
			return;
		}

		[$data, , $html] = $this->runView('timeDay', $action, 'timeDay');

		if($this->assertDecorPresent($data->timeDay['ccTaskWeek']->empty(), 'une semaine passée sans intervention dans la liste') === FALSE) {
			return;
		}

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($document->querySelector('#planning-time-list > #planning-time-none') !== NULL, 'la semaine vide n\'a pas son bloc vide');
		$this->assertTrue($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTask['id']) !== NULL, 'le bloc vide cache les zones');

	}

	/**
	 * Un jour à venir et l'onglet Équipe n'ont pas de zones, comme ils n'ont pas de liste.
	 */
	public function testNoZones(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		[$document] = $this->renderTimeDay(['date' => week_date_day(\Clock::week(), 5)]);
		$this->assertTrue($document->getElementById('planning-time-zones') === NULL, 'un jour à venir porte les zones');

		$action = $this->executePage('planning/timeDay:unplanned', ['id' => $this->eStructure['id'], 'week' => \Clock::week(), 'date' => week_date_day(\Clock::week(), 5)], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, '« Non planifié » se lit un jour à venir');

		$action = $this->executePage('planning/timeDay:unplanned', ['id' => $this->eStructure['id'], 'week' => \Clock::week(), 'user' => TimeWeekLib::TEAM], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, '« Non planifié » se lit pour l\'équipe');

	}

	/**
	 * Le temps saisi sur une ligne en retard s'écrit pour la personne de l'onglet, le jour ouvert, et compte
	 * dans la journée. La ligne reste dans sa zone, au rang qu'elle avait (R-Place) ; au rechargement, elle
	 * est dans la liste, à la fin de son action, et plus dans la zone.
	 */
	public function testTime(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		[$document, $dataBefore] = $this->renderTimeDay();

		$row = $document->getElementById('planning-time-task-'.$eTask['id']);

		if($this->assertDecorPresent($row !== NULL, 'la ligne en retard est dans sa zone') === FALSE) {
			return;
		}

		[$data] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'time' => '1.5', 'date' => \Clock::date(), 'zone' => TaskLateLib::LATE, 'actionFirst' => $row->getAttribute('data-action-first'), 'columns' => $row->closest('.planning-time-table')->getAttribute('data-columns')] + $this->getTimePosition($document, $eTask));

		if($this->assertTrue($data !== NULL, 'le temps sur une ligne en retard est refusé') === FALSE) {
			return;
		}

		$this->assertEquals('row', $data->change, 'le temps sur une ligne en retard ne rend pas la ligne seule');
		$this->assertEquals([], $data->zoneChanges, 'le temps sur une ligne en retard change sa zone');
		$this->assertEquals(1.5, $data->eTaskLine['timeDay'], 'la ligne rendue ne porte pas le temps saisi');
		$this->assertEquals($row->getAttribute('data-action-first') === '1', $data->eTaskLine['actionFirst'], 'la ligne rendue change de rang dans son action');
		$this->assertFalse($this->isInSheet($data->timeDay, $eTask), 'la ligne gardée dans sa zone est aussi dans la liste');

		$timesheet = \series\Timesheet::model()
			->select(['total' => new \Sql('SUM(time)', 'float')])
			->whereTask($eTask)
			->whereUser($this->getMember())
			->whereDate(\Clock::date())
			->get();

		$this->assertEquals(1.5, $timesheet['total'], 'le temps n\'est pas écrit pour la personne de l\'onglet, le jour ouvert');
		$this->assertEquals($dataBefore->timeDay['times'][\Clock::date()] + 1.5, $data->timeDay['times'][\Clock::date()], 'le temps ne compte pas dans la journée');

		[$documentReloaded, $dataReloaded] = $this->renderTimeDay();

		$this->assertTrue($documentReloaded->querySelector('#planning-time-zones #planning-time-task-'.$eTask['id']) === NULL, 'au rechargement, la ligne reste dans sa zone');

		$cTaskAction = $dataReloaded->timeDay['ccTaskWeek'][$eTask['action']['id']] ?? new \Collection();
		$this->assertTrue($cTaskAction->notEmpty() and array_key_last($cTaskAction->getArrayCopy()) === $eTask['id'], 'au rechargement, la ligne n\'est pas à la fin de son action');

	}

	/**
	 * La coche d'une ligne en retard la laisse dans sa zone, cochée, puis la décoche l'y laisse encore. Cochée,
	 * elle est faite dans sa semaine prévue, comme dans les Interventions (`testTaskLateDone` de
	 * `FingerprintTest`) : au rechargement, elle n'est ni dans la liste ni dans la zone.
	 */
	public function testDoneTodo(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		[$document] = $this->renderTimeDay();

		$position = ['zone' => TaskLateLib::LATE, 'actionFirst' => $document->getElementById('planning-time-task-'.$eTask['id'])?->getAttribute('data-action-first')] + $this->getTimePosition($document, $eTask);

		[$data] = $this->callTimeDay('doDone', ['task' => $eTask['id']] + $position);

		if($this->assertTrue($data !== NULL, 'la coche d\'une ligne en retard est refusée') === FALSE) {
			return;
		}

		$this->assertEquals('row', $data->change, 'la coche d\'une ligne en retard ne rend pas la ligne seule');
		$this->assertTrue($data->eTaskLine->isDone(), 'la ligne rendue n\'est pas cochée');

		[$documentReloaded] = $this->renderTimeDay();
		$this->assertTrue($documentReloaded->getElementById('planning-time-task-'.$eTask['id']) === NULL, 'au rechargement, la ligne en retard cochée est encore sur la fiche');

		[$data] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $position);

		$this->assertEquals('row', $data?->change, 'la décoche d\'une ligne gardée dans sa zone ne rend pas la ligne seule');
		$this->assertTrue($data?->eTaskLine->notEmpty() and $data->eTaskLine->isTodo(), 'la ligne décochée n\'est pas rendue à faire');

	}

	/**
	 * Une zone forgée est refusée, avant toute écriture.
	 */
	public function testForgedZone(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		[, , $action] = $this->callTimeDay('doDone', ['task' => $eTask['id'], 'zone' => 'week', 'columns' => '2']);

		$this->assertTrue($action instanceof \NotExpectedAction, 'une zone forgée est acceptée');
		$this->assertTrue(\series\TaskLib::getById($eTask['id'])->isTodo(), 'la zone forgée n\'a pas arrêté l\'écriture');

	}

	/**
	 * Au-delà du seuil, chaque action d'une zone montre ses `LIMIT` plus récentes puis « Voir les N autres »,
	 * qui rend les autres lignes à sa place. Une action hors de la zone est refusée.
	 */
	public function testMore(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$tasks = [];

			for($i = 0; $i <= TaskLateLib::LIMIT; $i++) {
				$tasks[] = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
			}

			[$document, $data] = $this->renderTimeDay();

			$action = $tasks[0]['action']['id'];
			$block = $data->zones[TaskLateLib::LATE]['blocks'][$action];
			$rest = $block['count'] - TaskLateLib::LIMIT;

			$more = $document->getElementById('planning-time-late-more-'.$action);

			if($this->assertTrue($more !== NULL, 'l\'action plafonnée n\'a pas « Voir les N autres »') === FALSE) {
				return;
			}

			$this->assertEquals(TaskLateLib::LIMIT, $document->querySelectorAll('#planning-time-late-board .planning-time-row[data-task]')->length - $this->countOtherRows($document, $action), 'l\'action plafonnée ne montre pas ses '.TaskLateLib::LIMIT.' plus récentes');

			[$dataMore, $instructions] = $this->callTimeDayGet('more', ['zone' => TaskLateLib::LATE, 'action' => $action, 'columns' => 2]);

			$this->assertEquals('rows', $dataMore->more, '« Voir les N autres » ne rend pas les seules lignes');
			$this->assertEquals($rest, $dataMore->cTaskRest->count(), '« Voir les N autres » ne rend pas les lignes qui ne se montraient pas');
			$this->assertTrue($this->getInstruction($instructions, '#planning-time-late-more-'.$action) !== NULL, 'les lignes ne remplacent pas le lien');

			[, , $refused] = $this->callTimeDayGet('more', ['zone' => TaskLateLib::LATE, 'action' => 0]);
			$this->assertTrue($refused instanceof \NotExpectedAction, 'une action hors de la zone est acceptée');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * « Voir les N autres » sur une table sans colonne de commentaires, quand une ligne du reste en porte :
	 * la table ouvre sa colonne sans se redessiner, et les lignes du reste arrivent dans cette variante. Les
	 * lignes des autres actions ne sont ni lues ni rendues.
	 */
	public function testMoreCommented(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$tasks = [];

			for($i = 0; $i <= TaskLateLib::LIMIT; $i++) {
				$tasks[] = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
			}

			$eTaskOther = $this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, $this->getWeekBefore());

			[, $data] = $this->renderTimeDay();

			$action = $tasks[0]['action']['id'];
			$restId = $data->zones[TaskLateLib::LATE]['blocks'][$action]['ids'][TaskLateLib::LIMIT];

			$this->createComment(\series\TaskLib::getById($restId), 'Recette CF reste', '10:05:00');

			[$dataMore, $instructions] = $this->callTimeDayGet('more', ['zone' => TaskLateLib::LATE, 'action' => $action, 'columns' => 2]);

			$this->assertEquals('variant', $dataMore->more, 'une ligne commentée du reste n\'ouvre pas la colonne des commentaires');
			$this->assertEquals('3', $this->getAttribute($instructions, '#planning-time-late-board', 'data-columns'), 'la table ne passe pas à trois colonnes');

			$json = json_encode($instructions);

			$this->assertTrue(str_contains($json, 'planning-time-task-'.$restId), 'la ligne commentée du reste manque');
			$this->assertFalse(str_contains($json, 'planning-time-task-'.$eTaskOther['id']), 'les lignes des autres actions sont rendues');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * Deux blocs plafonnés : le premier déplié par son « Voir les N autres », puis le second, dont une ligne du
	 * reste ouvre les commentaires. La réponse du second ne rend aucune ligne du premier, qui reste déplié à
	 * l'écran, et les deux sont retenus pour les gestes suivants (`expanded`).
	 */
	public function testMoreTwoBlocks(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$tasksFirst = [];
			$tasksSecond = [];

			for($i = 0; $i <= TaskLateLib::LIMIT; $i++) {
				$tasksFirst[] = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
				$tasksSecond[] = $this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, $this->getWeekBefore());
			}

			[, $data] = $this->renderTimeDay();

			$actionFirst = $tasksFirst[0]['action']['id'];
			$actionSecond = $tasksSecond[0]['action']['id'];

			$restFirst = $data->zones[TaskLateLib::LATE]['blocks'][$actionFirst]['ids'][TaskLateLib::LIMIT];
			$restSecond = $data->zones[TaskLateLib::LATE]['blocks'][$actionSecond]['ids'][TaskLateLib::LIMIT];

			[$dataFirst, $instructionsFirst] = $this->callTimeDayGet('more', ['zone' => TaskLateLib::LATE, 'action' => $actionFirst, 'columns' => 2]);

			$this->assertEquals('rows', $dataFirst->more, 'le premier bloc ne se déplie pas sur place');
			$this->assertEquals(TaskLateLib::LATE.'-'.$actionFirst, $this->getAttribute($instructionsFirst, '#planning-time-zones', 'data-expanded'), 'le bloc déplié n\'est pas retenu pour les gestes suivants');

			$this->createComment(\series\TaskLib::getById($restSecond), 'Recette CF second', '10:05:00');

			[$dataSecond, $instructionsSecond] = $this->callTimeDayGet('more', ['zone' => TaskLateLib::LATE, 'action' => $actionSecond, 'columns' => 2, 'expanded' => TaskLateLib::LATE.'-'.$actionFirst]);

			$this->assertEquals('variant', $dataSecond->more, 'la ligne commentée du second bloc n\'ouvre pas la colonne des commentaires');

			$json = json_encode($instructionsSecond);

			$this->assertTrue(str_contains($json, 'planning-time-task-'.$restSecond), 'le reste du second bloc manque');
			$this->assertFalse(str_contains($json, 'planning-time-task-'.$restFirst), 'la réponse du second bloc rend le premier, déplié');

			$expanded = explode(',', $this->getAttribute($instructionsSecond, '#planning-time-zones', 'data-expanded') ?? '');
			sort($expanded);
			$this->assertEquals([TaskLateLib::LATE.'-'.$actionFirst, TaskLateLib::LATE.'-'.$actionSecond], $expanded, 'les deux blocs dépliés ne sont pas retenus');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * La valeur qu'une instruction pose sur un attribut d'un sélecteur, `NULL` sans instruction pour lui.
	 */
	private function getAttribute(array $instructions, string $selector, string $attribute): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] !== 'js' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$method, $arguments]) {
					if($method === 'setAttribute' and $arguments[0] === $attribute) {
						return $arguments[1];
					}
				}

			}

		}

		return NULL;

	}

	/**
	 * Les lignes de la zone qui ne sont pas de l'action donnée.
	 */
	private function countOtherRows(\Dom\HTMLDocument $document, int $action): int {

		$count = 0;

		foreach($document->querySelectorAll('#planning-time-late-board .planning-time-row[data-task]') as $row) {

			$eTask = \series\TaskLib::getById((int)$row->getAttribute('data-task'));

			if($eTask['action']['id'] !== $action) {
				$count++;
			}

		}

		return $count;

	}

	/**
	 * Une route en GET de la fiche, rendue par sa vue : ses données et ses instructions, ou l'action levée.
	 */
	private function callTimeDayGet(string $route, array $input): array {

		$action = $this->executePage('planning/timeDay:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'GET');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView($route, $action, 'timeDay');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Le HTML qu'une instruction pose sur un sélecteur, `NULL` sans instruction pour lui.
	 */
	private function getInstruction(array $instructions, string $selector): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] !== 'js' and $instruction[1] === $selector) {
				return $instruction[2][0][1][0] ?? '';
			}

		}

		return NULL;

	}

	private function getWeekBefore(): string {
		return toWeek(\Clock::date().' - 7 DAYS');
	}

}
?>
