<?php
namespace series;

/**
 * Le bloc `series-week` de l'accueil : les interventions que le membre suit, faites sur prévues, et la
 * liste qu'il peut choisir.
 *
 * L'heure est figée sur un lundi par `setClock()` : `load()` lit `\Clock::week()`. Les nombres se
 * lisent en écart et jamais en absolu : ce que le test mesure est ce que ses propres interventions
 * ajoutent, quelle que soit la semaine que la structure porte déjà.
 *
 * Le choix du membre se pose sur le membre en ligne et non en base : `Structure::getMember()` rend
 * l'élément que `MemberLib::getOnline()` garde pour la requête, et c'est lui que `load()` relit.
 *
 * Isolation : la transaction de `SeriesFixtureAiTest`, annulée en tearDown().
 *
 * Lancement : dev/test package=series file=DashboardWeek
 */
class DashboardWeekTest extends SeriesFixtureAiTest {

	const string MONDAY = '2026-06-15 10:00:00';

	/** Le choix du membre en ligne avant le cas, que `tearDown()` lui rend : l'élément gardé pour la requête survit d'un test à l'autre. */
	private mixed $followed = NULL;

	public function setUp(): void {

		parent::setUp();

		$this->followed = $this->eStructure->getView('viewDashboardActions');

	}

	public function tearDown(): void {

		if($this->eStructure->getMember()->notEmpty()) {
			$this->follow($this->followed);
		}

		parent::tearDown();

	}

	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_PLANTATION));

	}

	private function follow(?array $actions): void {

		$this->eStructure->getMember()['viewDashboardActions'] = $actions;

	}

	private function findAction(array $week, int $action): ?array {

		foreach($week['actions'] as $values) {
			if($values['id'] === $action) {
				return $values;
			}
		}

		return NULL;

	}

	/**
	 * Une intervention à faire compte à sa semaine prévue, une faite à sa semaine faite, même sans avoir
	 * été prévue : ce sont les deux moitiés de l'écran de la semaine du planning.
	 */
	public function testDoneIsCountedOverPlanned(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eAction = $this->getAction(ACTION_PLANTATION);

		$this->follow([$eAction['id']]);

		$before = $this->findAction(DashboardLib::load('series-week', $this->eStructure), $eAction['id']);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::DONE, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, toWeek(\Clock::date().' + 7 days'));

		$after = $this->findAction(DashboardLib::load('series-week', $this->eStructure), $eAction['id']);

		$this->assertEquals([$before['done'] + 1, $before['todo'] + 1], [$after['done'], $after['todo']], 'une faite et une à faire de plus, rien de la semaine suivante');
		$this->assertTrue(in_array($this->getCultivation()['plant']['name'], $after['plants'], TRUE), "l'espèce de la culture est nommée");

	}

	/**
	 * Une intervention à faire d'une semaine passée compte en retard sur son action (L67), tant qu'elle
	 * reste dans la profondeur `planningDelayedMax` du planning : la semaine courante n'en gagne rien, et
	 * une intervention plus ancienne que cette profondeur ou déjà faite ne compte pas.
	 */
	public function testLateIsCountedByAction(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eAction = $this->getAction(ACTION_PLANTATION);

		$this->follow([$eAction['id']]);

		$before = $this->findAction(DashboardLib::load('series-week', $this->eStructure), $eAction['id']);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, toWeek(\Clock::date().' - 7 days'));
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, toWeek(\Clock::date().' - 14 days'));
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::DONE, toWeek(\Clock::date().' - 7 days'));
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, toWeek(\Clock::shift('- '.($this->eStructure->conf(\map\Configuration::class)['planningDelayedMax'] + 1).' MONTH')));

		$week = DashboardLib::load('series-week', $this->eStructure);
		$after = $this->findAction($week, $eAction['id']);

		$this->assertEquals($before['late'] + 2, $after['late'], 'les deux à faire des semaines passées, ni la faite ni celle d\'au-delà de la profondeur');
		$this->assertEquals($before['todo'], $after['todo'], 'la semaine courante n\'en gagne rien');
		$this->assertEquals($after['late'], $week['late'], 'le chiffre du bloc les compte, une seule action étant suivie');
		$this->assertEquals(\structure\StructureUi::urlPlanningWeekly($this->eStructure), $week['lateUrl'], 'la ligne mène à la semaine du planning, où « Retardé » les montre');

	}

	/**
	 * L'action porte ses interventions de la semaine, chacune avec son état et ce qui la nomme, rangées par
	 * leur nom et jamais par leur état : cochée, une intervention garde sa place, celle où la réponse du
	 * geste la redessine. Une intervention de la semaine suivante n'y est pas.
	 */
	public function testTasksAreListedByName(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eAction = $this->getAction(ACTION_PLANTATION);

		$this->follow([$eAction['id']]);

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, \Clock::week());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::DONE, \Clock::week());
		$eTaskNext = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, toWeek(\Clock::date().' + 7 days'));

		$getTasks = fn() => array_column($this->findAction(DashboardLib::load('series-week', $this->eStructure), $eAction['id'])['tasks'], NULL, 'id');

		$tasks = $getTasks();

		$this->assertTrue(array_key_exists($eTaskTodo['id'], $tasks) and array_key_exists($eTaskDone['id'], $tasks), 'les deux interventions de la semaine sont listées');
		$this->assertFalse(array_key_exists($eTaskNext['id'], $tasks), 'une intervention de la semaine suivante est listée');
		$this->assertEquals([Task::TODO, Task::DONE], [$tasks[$eTaskTodo['id']]['status'], $tasks[$eTaskDone['id']]['status']], 'l\'état de chaque intervention');
		$this->assertEquals($this->getCultivation()['series']['id'], $tasks[$eTaskTodo['id']]['series']['id'], 'l\'intervention est nommée par sa série');

		$before = array_keys($tasks);

		TaskLib::updateDoneCollection(new \Collection([$eTaskTodo]), \Clock::week());
		TaskLib::updateTodoCollection(new \Collection([$eTaskDone]));

		$this->assertEquals($before, array_keys($getTasks()), 'cocher et décocher ne déplacent aucune intervention');

	}

	/**
	 * Le chiffre du bloc est ce qui reste à faire sur toutes les actions suivies : une intervention à
	 * faire de plus le fait monter d'une, une faite ne le change pas.
	 */
	public function testTheTotalIsWhatRemainsOnAllActions(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE) {
			return;
		}

		// Toutes les actions qui se choisissent, pour que la somme porte sur plusieurs lignes.
		$this->follow(DashboardLib::getActions($this->eStructure)->getIds());

		$before = DashboardLib::load('series-week', $this->eStructure);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::DONE, \Clock::week());

		$after = DashboardLib::load('series-week', $this->eStructure);

		$this->assertEquals(array_sum(array_column($after['actions'], 'todo')), $after['todo'], 'la somme des actions suivies');
		$this->assertEquals($before['todo'] + 1, $after['todo'], 'une à faire de plus, la faite ne compte pas');

	}

	/** Une action suivie sans rien cette semaine garde sa ligne, à zéro. */
	public function testAFollowedActionStaysWithNothing(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eAction = $this->getAction(ACTION_PLANTATION);

		$this->follow([$eAction['id']]);

		$week = DashboardLib::load('series-week', $this->eStructure);

		$this->assertEquals([$eAction['id']], array_column($week['actions'], 'id'), 'la seule action suivie, quel que soit ce que la semaine porte');

		$this->follow([]);

		$this->assertEquals(['week' => \Clock::week(), 'actions' => [], 'todo' => 0, 'late' => 0, 'lateUrl' => \structure\StructureUi::urlPlanningWeekly($this->eStructure)], DashboardLib::load('series-week', $this->eStructure), 'rien de suivi, aucune ligne et pas de NULL : le bloc garde son « Choisir »');

	}

	/** `NULL` suit les favorites de la structure, la récolte jamais : sa cellule la porte. */
	public function testNullFollowsTheFavorites(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE) {
			return;
		}

		$this->follow(NULL);

		$favorites = \work\ActionLib::getByStructure($this->eStructure, index: 'id')
			->find(fn(\work\Action $eAction) => ($eAction['favorite'] and $eAction['fqn'] !== ACTION_RECOLTE))
			->getIds();

		$week = DashboardLib::load('series-week', $this->eStructure);

		$this->assertEquals($favorites, array_column($week['actions'], 'id'), 'les favorites, dans leur ordre');
		$this->assertFalse(DashboardLib::getActions($this->eStructure)->offsetExists($this->getAction(ACTION_RECOLTE)['id']), 'la récolte ne se choisit pas');

	}

	/** Un identifiant qui n'est plus une action de la structure est ignoré à la lecture, l'ordre du membre est gardé. */
	public function testAnUnknownActionIsIgnored(): void {

		$this->setClock(self::MONDAY);

		if($this->requireDecor() === FALSE or $this->requireAction(ACTION_SEMIS_DIRECT) === FALSE) {
			return;
		}

		$plantation = $this->getAction(ACTION_PLANTATION)['id'];
		$sowing = $this->getAction(ACTION_SEMIS_DIRECT)['id'];

		$this->follow([$sowing, 0, $plantation]);

		$this->assertEquals([$sowing, $plantation], array_column(DashboardLib::load('series-week', $this->eStructure)['actions'], 'id'));

		$choice = DashboardLib::getChoice('series-week', $this->eStructure);

		$this->assertEquals([$sowing, $plantation], array_slice(array_column($choice['actions'], 'id'), 0, 2), 'le panneau ouvre sur les suivies, dans leur ordre');
		$this->assertEquals([TRUE, TRUE, FALSE], array_slice(array_column($choice['actions'], 'followed'), 0, 3), 'puis les autres, éteintes');

	}

	/**
	 * Le choix ne s'écrit qu'avec des actions de la structure, sans doublon : le POST est contournable,
	 * et la règle est celle de l'ordre du planning, posée sur les deux colonnes.
	 */
	public function testTheChoiceIsBoundToTheStructure(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$plantation = $this->getAction(ACTION_PLANTATION)['id'];
		$foreign = \work\ActionLib::getByStructure($this->getStructureForeign(), index: 'id')->first();

		$build = function(array $actions): \FailWatch {

			$eMember = new \structure\Member([
				'cAction' => DashboardLib::getActions($this->eStructure)
			]);

			$fw = new \FailWatch();

			$eMember->build(['viewDashboardActions'], ['viewDashboardActions' => $actions]);

			return $fw;

		};

		$this->assertFalse($build([$plantation])->ko(), 'une action de la structure');
		$this->assertFalse($build([])->ko(), 'rien de suivi');
		$this->assertTrue($build([$plantation, $plantation])->has('Member::viewDashboardActions.actions'), 'un doublon');
		$this->assertTrue($build([$foreign['id']])->has('Member::viewDashboardActions.actions'), "une action d'une autre structure");
		$this->assertTrue($build([$this->getAction(ACTION_RECOLTE)['id']])->has('Member::viewDashboardActions.actions'), 'la récolte');

	}

}
?>
