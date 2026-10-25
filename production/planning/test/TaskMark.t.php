<?php
namespace planning;

/**
 * La marque d'une carte (lot BI, planche planning-a-faire-cliquable) : un rond qui coche l'intervention à faire
 * et remet à faire celle qui est faite, sur chaque écran qui porte une carte, la Semaine, le Jour, l'Année et la
 * fiche de temps. « Nouvelle intervention » ne le porte plus : Fait et À faire y sont des radios du cadriciel (BX).
 *
 * Le dessin du rond, sa coche, la flèche du retour (retours 266 et 267) et que le clic ne déplace rien
 * se regardent dans Chrome.
 *
 * Lancement : dev/test package=planning file=TaskMark
 */
class TaskMarkTest extends HarvestFixtureTest {

	/**
	 * Sur chaque écran, le rond de la carte à faire la coche, et celui de la carte faite la remet à faire.
	 */
	public function testEveryScreen(): void {

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		// La fiche de temps ne porte une intervention faite que si du temps y est passé ce jour-là.
		$this->createTimesheet($eTaskDone, 0.5, \Clock::date());

		$screens = [
			'Semaine' => [$this->renderTasks()[0], fn(\series\Task $eTask) => '#planning-task-'.$eTask['id']],
			'Jour' => [$this->renderTaskDay()[0], fn(\series\Task $eTask) => '#planning-day [data-task="'.$eTask['id'].'"]'],
			'Année' => [$this->renderTaskYear()[0], fn(\series\Task $eTask) => '#planning-year [data-task="'.$eTask['id'].'"]'],
			'Temps' => [$this->renderTimeDay()[0], fn(\series\Task $eTask) => '#planning-time-task-'.$eTask['id']],
		];

		foreach($screens as $screen => [$document, $scope]) {

			$this->checkMark($document, $scope($eTaskTodo), $eTaskTodo, FALSE, TRUE, $screen);
			$this->checkMark($document, $scope($eTaskDone), $eTaskDone, TRUE, TRUE, $screen);
		}

	}

	/**
	 * Sans droit d'écrire le planning (Bruno, au planning limité), le rond ne porte plus aucun geste.
	 */
	public function testLimited(): void {

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderTasks();

		$this->checkMark($document, '#planning-task-'.$eTaskTodo['id'], $eTaskTodo, FALSE, FALSE, 'Semaine de Bruno');
		$this->checkMark($document, '#planning-task-'.$eTaskDone['id'], $eTaskDone, TRUE, FALSE, 'Semaine de Bruno');

	}

	/**
	 * La marque d'une carte : son geste, ou son absence en droit limité.
	 */
	private function checkMark(\Dom\HTMLDocument $document, string $scope, \series\Task $eTask, bool $done, bool $write, string $screen): void {

		$case = $screen.', '.($done ? 'carte faite' : 'carte à faire');

		$marks = $document->querySelectorAll($scope.' .planning-task-mark');

		if($this->assertTrue($marks->length > 0, $case.' : la carte ne porte pas de marque') === FALSE) {
			return;
		}

		$mark = $marks->item(0);

		$gesture = $done ? 'data-planning-task-todo' : 'data-planning-task-done';

		if($write) {
			$this->assertEquals((string)$eTask['id'], $mark->getAttribute($gesture), $case.' : le rond ne porte pas le geste qui '.($done ? 'la remet à faire' : 'la coche'));
		} else {
			$this->assertFalse($mark->hasAttribute($gesture), $case.' : le rond porte un geste en droit limité');
		}

	}

}
