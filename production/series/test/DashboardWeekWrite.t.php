<?php
namespace series;

/**
 * La coche et la décoche d'une intervention depuis le bloc `series-week` de l'accueil, passées par les
 * routes de `series/dashboard` comme le navigateur les poste : ce qui s'écrit, et la réponse, bornée à la
 * ligne de l'intervention, à la tête de son action et au chiffre du bloc.
 *
 * Les gestes forgés s'éprouvent ici (intervention d'une autre structure, planning limité).
 *
 * Le décor et ses outils sont ceux des gestes du pense-bête (`\planning\HarvestFixtureTest`), qui posent
 * l'heure un jeudi et savent exécuter une page puis sa vue. Les lectures se bornent aux interventions que
 * la méthode pose : la semaine du décor peut en porter d'autres.
 *
 * Lancement : dev/test package=series file=DashboardWeekWrite
 */
class DashboardWeekWriteTest extends \planning\HarvestFixtureTest {

	/** Le choix du membre en ligne avant le cas, que `tearDown()` lui rend : l'élément gardé pour la requête survit d'un test à l'autre. */
	private mixed $followed = NULL;

	public function setUp(): void {

		parent::setUp();

		$this->followed = $this->eStructure->getView('viewDashboardActions');

	}

	public function tearDown(): void {

		if($this->eStructure->getMember()->notEmpty()) {
			$this->eStructure->getMember()['viewDashboardActions'] = $this->followed;
		}

		parent::tearDown();

	}

	/**
	 * Cochée, l'intervention passe faite dans la semaine et sa ligne se redessine à sa place, avec la tête
	 * de son action et le chiffre du bloc, rien d'autre ; décochée, elle redevient à faire de la même façon.
	 */
	public function testCheckAndUncheckRedrawTheLine(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, \Clock::week());

		[$data, $instructions, $action] = $this->callDashboard('doDone', $eTask);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTaskStored = TaskLib::getById($eTask['id']);

		$this->assertEquals([Task::DONE, \Clock::week()], [$eTaskStored['status'], $eTaskStored['doneWeek']], 'l\'intervention cochée n\'est pas faite cette semaine');
		$this->assertEquals('line', $data->change, 'cochée, l\'intervention reste dans la semaine');
		$this->assertEquals(Task::DONE, $data->task['status'], 'la ligne redessinée n\'est pas celle d\'une faite');
		$this->assertEquals($this->getSelectorsExpected($eTask), $this->getSelectors($instructions), 'la coche touche autre chose que la ligne, la tête de son action et le chiffre');

		[$data, $instructions, $action] = $this->callDashboard('doTodo', $eTask);

		$this->assertTrue($action instanceof \ViewAction, 'la décoche est refusée');
		$this->assertEquals(Task::TODO, TaskLib::getById($eTask['id'])['status'], 'l\'intervention décochée n\'est pas à faire');
		$this->assertEquals('line', $data->change, 'décochée, l\'intervention prévue cette semaine y reste');
		$this->assertEquals(Task::TODO, $data->task['status'], 'la ligne redessinée n\'est pas celle d\'une à faire');
		$this->assertEquals($this->getSelectorsExpected($eTask), $this->getSelectors($instructions), 'la décoche touche autre chose que la ligne, la tête de son action et le chiffre');

	}

	/**
	 * Prévue la semaine passée et faite cette semaine, l'intervention décochée retourne à sa semaine prévue
	 * et quitte le bloc : sa ligne s'efface, ou le pli de son action quand elle n'a plus rien cette semaine.
	 */
	public function testUncheckOutOfTheWeekRemovesTheLine(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, toWeek(week_date_starts(\Clock::week()).' - 7 days'));

		TaskLib::updateDoneCollection(new \Collection([$eTask]), \Clock::week());

		// Sans `TaskLib`, qui ne pose la semaine faite qu'au plus tôt à la semaine prévue : le décor veut une
		// intervention prévue une semaine et faite la suivante, ce que fait un temps pointé après coup.
		Task::model()->update($eTask, ['doneWeek' => \Clock::week()]);

		$remaining = $this->findAction(DashboardLib::load('series-week', $this->eStructure), $this->getAction(ACTION_PLANTATION)['id']);
		$expected = ($remaining['done'] + $remaining['todo'] > 1) ? 'remove' : 'empty';

		[$data, $instructions, $action] = $this->callDashboard('doTodo', $eTask);

		if($this->assertTrue($action instanceof \ViewAction, 'la décoche est refusée') === FALSE) {
			return;
		}

		$this->assertEquals($expected, $data->change, 'l\'intervention retournée à sa semaine prévue reste dans le bloc');
		$this->assertTrue($data->task === NULL, 'l\'intervention qui a quitté la semaine est encore lue dans le bloc');

		$line = '#'.DashboardUi::getWeekTaskId($eTask['id']);
		$fold = '#'.DashboardUi::getWeekActionId($eTask['action']['id']);

		$this->assertTrue($this->getCall($instructions, ($expected === 'remove') ? $line : $fold, 'remove') !== NULL, 'la ligne, ou le pli de son action, n\'est pas retiré');

	}

	/** Une intervention d'une autre structure que celle de l'accueil : refus, sans rien écrire. */
	public function testForeignStructureRefused(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eTaskForeign = $this->createTaskForeign();

		[, , $action] = $this->callDashboard('doDone', $eTaskForeign);

		$this->assertFalse($action instanceof \ViewAction, 'la coche d\'une intervention d\'une autre structure est acceptée');
		$this->assertEquals(Task::TODO, TaskLib::getById($eTaskForeign['id'])['status'], 'l\'intervention d\'une autre structure a été écrite');

	}

	/**
	 * Le planning limité lit le bloc et ses interventions sans geste : aucune route ne lui écrit, sa
	 * marque ne porte pas l'attribut que le clic écoute. Le nom ouvre la page de l'intervention comme au
	 * planning, `Task::canRead()` la donnant à tout membre : le lien suit un droit, d'où sa recherche dans
	 * le rendu.
	 */
	public function testPlanningLimited(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, \Clock::week());

		$url = \planning\TaskDetailUi::url(new Task(['id' => $eTask['id'], 'structure' => $this->eStructure]), \Clock::week());
		$html = new DashboardUi()->render('series-week', $this->eStructure, DashboardLib::load('series-week', $this->eStructure));

		$this->assertTrue(str_contains($html, 'href="'.$url.'"'), 'le nom de l\'intervention n\'ouvre pas sa page avec le droit d\'écrire');

		$this->reconnect($this->getMemberPlanning());

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		if($this->assertDecorPresent($eStructure->canPlanningLimited() and $eStructure->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		foreach(['doDone', 'doTodo'] as $route) {

			[, , $action] = $this->callDashboard($route, $eTask);

			$this->assertFalse($action instanceof \ViewAction, 'le planning limité passe '.$route);

		}

		$this->assertEquals(Task::TODO, TaskLib::getById($eTask['id'])['status'], 'le planning limité a coché une intervention');

		$eStructure->getMember()['viewDashboardActions'] = [$this->getAction(ACTION_PLANTATION)['id']];

		$week = DashboardLib::load('series-week', $eStructure);
		$html = new DashboardUi()->render('series-week', $eStructure, $week);

		$this->assertTrue(in_array($eTask['id'], array_column($this->findAction($week, $this->getAction(ACTION_PLANTATION)['id'])['tasks'], 'id'), TRUE), 'le planning limité ne lit pas l\'intervention');
		$this->assertFalse(str_contains($html, 'data-planning-task-done') or str_contains($html, 'data-planning-task-todo'), 'la marque du planning limité porte le geste');
		$this->assertTrue(str_contains($html, 'href="'.$url.'"'), 'le planning limité n\'ouvre pas la page de l\'intervention, que le planning lui ouvre');

	}

	private function requireDecor(): bool {

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE) {
			return FALSE;
		}

		$this->eStructure->getMember()['viewDashboardActions'] = [$this->getAction(ACTION_PLANTATION)['id']];

		return TRUE;

	}

	private function findAction(array $week, int $action): ?array {

		foreach($week['actions'] as $values) {
			if($values['id'] === $action) {
				return $values;
			}
		}

		return NULL;

	}

	private function getSelectorsExpected(Task $eTask): array {

		return [
			'#'.DashboardUi::getWeekValueId(),
			'#'.DashboardUi::getWeekTaskId($eTask['id']),
			'#'.DashboardUi::getWeekSummaryId($eTask['action']['id'])
		];

	}

	/** Une route du bloc posée sur la structure du décor, puis rendue par sa vue. */
	private function callDashboard(string $route, Task $eTask): array {

		$action = $this->executePage('series/dashboard:'.$route, ['id' => $this->eStructure['id'], 'task' => $eTask['id']], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doTask', $action, 'dashboard', 'series');

		return [$data, $template->formatInstructions(), $action];

	}

	/** Les sélecteurs que les instructions de la réponse touchent, hors appels JavaScript. */
	private function getSelectors(array $instructions): array {

		return array_values(array_unique(array_column(array_filter($instructions, fn(array $instruction) => in_array($instruction[0], ['qs', 'qsa'], TRUE)), 1)));

	}

	/** Les arguments du premier appel `$method` posé sur `$selector`, ou NULL. */
	private function getCall(array $instructions, string $selector, string $method): ?array {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$methodCalled, $argumentsCalled]) {

					if($methodCalled === $method) {
						return $argumentsCalled;
					}

				}

			}

		}

		return NULL;

	}

	private function reconnect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
