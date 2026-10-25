<?php
namespace planning;

/**
 * « Masquer ce qui est fait » ne cache que ce qui est fait (R-Retours-1210-discord, AY). Une récolte à faire
 * qui porte déjà une quantité, ou une intervention à faire qui porte déjà du temps, paraît dans la semaine
 * comme commencée, mais il reste à la faire : le masque la laisse à l'écran, en Semaine, en Jour, en Année,
 * dans la recherche de l'Année et sur la fiche de temps. Une intervention faite disparaît partout.
 *
 * Le jour du décor est le jeudi de la semaine 39 ; l'Année se lit sur la semaine 3, qu'aucune intervention
 * du décor ne touche.
 *
 * Lancement : dev/test package=planning file=TaskHide
 */
class TaskHideTest extends HarvestFixtureTest {

	public function testWeek(): void {

		[$eTaskHarvest, $eTaskTime, $eTaskDone] = $this->createStarted(\Clock::week(), \Clock::date());

		if($this->requireStarted($eTaskHarvest, $eTaskTime) === FALSE) {
			return;
		}

		$this->setHideDone(TRUE);

		[$document] = $this->renderTasks();

		$this->assertTrue($document->getElementById('planning-task-'.$eTaskHarvest['id']) !== NULL, 'le masque retire une récolte à faire qui porte une quantité');
		$this->assertTrue($document->getElementById('planning-task-'.$eTaskTime['id']) !== NULL, 'le masque retire une intervention à faire qui porte du temps');
		$this->assertTrue($document->getElementById('planning-task-'.$eTaskDone['id']) === NULL, 'le masque garde une intervention faite');

	}

	public function testDay(): void {

		[$eTaskHarvest, $eTaskTime, $eTaskDone] = $this->createStarted(\Clock::week(), \Clock::date());

		if($this->requireStarted($eTaskHarvest, $eTaskTime) === FALSE) {
			return;
		}

		$this->setHideDone(TRUE);

		[, $data] = $this->renderTaskDay();

		$this->assertTrue($this->isInColumns($data->columns, $eTaskHarvest), 'le masque retire du Jour une récolte à faire qui porte une quantité');
		$this->assertTrue($this->isInColumns($data->columns, $eTaskTime), 'le masque retire du Jour une intervention à faire qui porte du temps');
		$this->assertFalse($this->isInColumns($data->columns, $eTaskDone), 'le masque garde au Jour une intervention faite');

	}

	public function testYear(): void {

		$week = $this->getSeason().'-W03';

		[$eTaskHarvest, $eTaskTime, $eTaskDone] = $this->createStarted($week, week_date_day($week, 2));

		if($this->requireStarted($eTaskHarvest, $eTaskTime) === FALSE) {
			return;
		}

		$this->setHideDone(TRUE);

		[$data] = $this->callYear('weeks', ['weeks' => [$week]], 'GET');

		$cTask = $data->columns[$week]['cTask'];

		$this->assertTrue($cTask->offsetExists($eTaskHarvest['id']), 'le masque retire de l\'Année une récolte à faire qui porte une quantité');
		$this->assertTrue($cTask->offsetExists($eTaskTime['id']), 'le masque retire de l\'Année une intervention à faire qui porte du temps');
		$this->assertFalse($cTask->offsetExists($eTaskDone['id']), 'le masque garde à l\'Année une intervention faite');

		$cards = TaskSearchLib::getYear($this->eStructure, \Clock::week(), TRUE, [])[$week] ?? [];

		$this->assertTrue(array_key_exists($eTaskHarvest['id'], $cards), 'la recherche de l\'Année ne voit plus, sous le masque, une récolte à faire qui porte une quantité');
		$this->assertTrue(array_key_exists($eTaskTime['id'], $cards), 'la recherche de l\'Année ne voit plus, sous le masque, une intervention à faire qui porte du temps');
		$this->assertFalse(array_key_exists($eTaskDone['id'], $cards), 'la recherche de l\'Année voit, sous le masque, une intervention faite');

	}

	/**
	 * La fiche de temps du mardi : la récolte et l'intervention commencées un autre jour n'y portent pas de
	 * temps de la personne, et c'est le cas que le masque retirait.
	 */
	public function testTimeDay(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		[$eTaskHarvest, $eTaskTime, $eTaskDone] = $this->createStarted(\Clock::week(), \Clock::date());

		if($this->requireStarted($eTaskHarvest, $eTaskTime) === FALSE) {
			return;
		}

		$this->setHideDone(TRUE);

		[, $data] = $this->renderTimeDay(['date' => $tuesday]);

		$this->assertTrue($this->isInSheet($data->timeDay, $eTaskHarvest), 'le masque retire de la fiche une récolte à faire qui porte une quantité');
		$this->assertTrue($this->isInSheet($data->timeDay, $eTaskTime), 'le masque retire de la fiche une intervention à faire qui porte du temps');
		$this->assertFalse($this->isInSheet($data->timeDay, $eTaskDone), 'le masque garde sur la fiche une intervention faite sans temps ce jour-là');

	}

	/**
	 * Trois interventions prévues ou faites la semaine `$week` : une récolte à faire qui porte une quantité
	 * le jour `$date`, une intervention à faire qui y porte du temps, une intervention faite ce jour-là.
	 */
	private function createStarted(string $week, string $date): array {

		$eTaskHarvest = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $this->getCultivation()['series'],
			'cultivation' => $this->getCultivation(),
			'plant' => $this->getCultivation()['plant'],
			'action' => $this->getAction(ACTION_RECOLTE),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => $week,
			'plannedDate' => NULL,
			'harvest' => NULL,
			'harvestUnit' => $this->getCultivation()['mainUnit'],
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans build(), qui vérifierait l'action, la catégorie, la semaine et l'unité postées depuis le
		// formulaire d'intervention : le décor veut une récolte à faire dans l'unité de la culture, et le
		// test n'éprouve que ce que le masque garde à l'écran.
		\series\TaskLib::create($eTaskHarvest);
		$this->createHarvest($eTaskHarvest, 2.0, $date);

		$eTaskTime = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
		$this->createTimesheet($eTaskTime, 0.5, $date);

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, $week, $date);

		return [$eTaskHarvest, $eTaskTime, $eTaskDone];

	}

	/**
	 * La quantité et le temps saisis laissent les deux interventions à faire : c'est ce qui les fait paraître
	 * commencées sans être faites.
	 */
	private function requireStarted(\series\Task ...$tasks): bool {

		foreach($tasks as $eTask) {

			if($this->assertDecorPresent(\series\TaskLib::getById($eTask['id'])->isTodo(), 'l\'intervention '.$eTask['id'].' reste à faire après la saisie de sa quantité ou de son temps') === FALSE) {
				return FALSE;
			}

		}

		return TRUE;

	}

	private function isInColumns(array $columns, \series\Task $eTask): bool {

		foreach($columns as $column) {

			if($column['cTask']->offsetExists($eTask['id'])) {
				return TRUE;
			}

		}

		return FALSE;

	}

}
?>
