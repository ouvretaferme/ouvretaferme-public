<?php
namespace entry;

/**
 * « Toutes les séries » de la coche (lot S4d) : prévues sur plusieurs séries, la plus proche de chacune se
 * coche d'un geste ; rien de prévu, une intervention faite se crée sur chaque série en phase. L'annulation
 * défait celles qui sont restées telles quelles, et la pastille les compte.
 *
 * Aucun refus éprouvé ici ne passe par un `rollBack()`, que la transaction du test emporterait
 * (`EntryAtomicity.t.php`).
 */
class DoneAllTest extends EntryFixtureTest {

	private function createTaskPlanned(\series\Cultivation $eCultivation, int $weeks = 0): \series\Task {
		return $this->createTask($eCultivation, ACTION_FERTILISATION, \series\Task::TODO, toWeek(\Clock::date().' '.(($weeks < 0) ? '- ' : '+ ').abs($weeks).' WEEKS'));
	}

	private function getDoneText(string $rest = ''): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '.$rest;
	}

	private function getStatus(\series\Task $eTask): string {

		return \series\Task::model()
			->select(['status'])
			->whereId($eTask['id'])
			->get()['status'];

	}

	/**
	 * Les ids triés : le test juge quelles séries, pas dans quel ordre la question les range.
	 */
	private function getSorted(array $ids): array {
		sort($ids);
		return $ids;
	}

	private function getTimeDay(\series\Task $eTask): float {
		return \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), \Clock::date());
	}

	/**
	 * Deux séries : la question finit par « Toutes », la plus proche de chacune. Trois passages sur une seule
	 * série n'en sont pas.
	 */
	public function testOffered(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série A');

		$eTaskNear = $this->createTaskPlanned($eCultivationA);
		$this->createTaskPlanned($eCultivationA, -2);
		$this->createTaskPlanned($eCultivationA, -4);

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('task', $reading['question']['name']);
		$this->assertTrue($reading['question']['cTaskAll']->empty(), 'une seule série');

		$eTaskB = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série B'), -3);

		$reading = $this->read($this->getDoneText());

		$this->assertEquals([$eTaskNear['id'], $eTaskB['id']], $reading['question']['cTaskAll']->getIds(), 'la plus proche de chaque série');

	}

	/**
	 * « Toutes » porte sur toute la fenêtre, au-delà des interventions que la question montre.
	 */
	public function testBeyondLimit(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série A');

		for($task = 0; $task < DoneIntentionLib::QUESTION_LIMIT; $task++) {
			$this->createTaskPlanned($eCultivationA);
		}

		$eTaskB = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série B'), -6);

		$reading = $this->read($this->getDoneText());

		$this->assertFalse($reading['question']['cTask']->offsetExists($eTaskB['id']), 'la question ne la montre pas');
		$this->assertTrue($reading['question']['cTaskAll']->offsetExists($eTaskB['id']), 'toutes la cochent');

	}

	/**
	 * Le geste coche les deux, le temps dit se partage, et l'annulation défait tout.
	 */
	public function testWriteAndCancel(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTaskA = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série A'));
		$eTaskB = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série B'), -1);

		$reading = $this->read('fait '.$this->getDoneText('2 h '), ['all' => TRUE]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(2, $reading['confirmation']['cTask']->count());

		EntryLib::write($this->eStructure, $reading);

		foreach([$eTaskA, $eTaskB] as $eTask) {
			$this->assertEquals(\series\Task::DONE, $this->getStatus($eTask));
			$this->assertEquals(1.0, $this->getTimeDay($eTask), 'la moitié du temps');
		}

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals(2, $cancellation['cTaskReverted']->count());
		$this->assertEquals(2.0, $cancellation['time']);

		foreach([$eTaskA, $eTaskB] as $eTask) {
			$this->assertEquals(\series\Task::TODO, $this->getStatus($eTask));
			$this->assertEquals(0.0, $this->getTimeDay($eTask));
		}

	}

	/**
	 * Une intervention retouchée depuis reste faite, avec son temps ; la pastille ne compte que l'autre.
	 */
	public function testCancelPartial(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTaskA = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série A'));
		$eTaskB = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série B'));

		$reading = $this->read('fait '.$this->getDoneText('2 h '), ['all' => TRUE]);
		EntryLib::write($this->eStructure, $reading);

		// Retouchée ailleurs après la coche, faite une autre semaine. Sans `TaskLib` ni `build()` : le décor pose
		// directement l'état qu'une autre voie aurait laissé, seule compte la condition de `updateTodoIfLeft()`.
		\series\Task::model()->update($eTaskA, ['doneWeek' => toWeek(\Clock::date().' - 1 WEEK')]);

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals([$eTaskB['id']], $cancellation['cTaskReverted']->getIds());

		$this->assertEquals(\series\Task::DONE, $this->getStatus($eTaskA));
		$this->assertEquals(1.0, $this->getTimeDay($eTaskA), 'son temps reste');
		$this->assertEquals(\series\Task::TODO, $this->getStatus($eTaskB));

	}

	/**
	 * Rien de prévu, deux séries en phase et une hors phase : « Toutes » crée une intervention faite sur chaque
	 * série en phase, et l'annulation les supprime.
	 */
	public function testCreate(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivationB = $this->createCultivationInPhase(TRUE, 'Série B');
		$this->createCultivationInPhase(FALSE, 'Série C');

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('series', $reading['question']['name']);
		$this->assertEquals($this->getSorted([$eCultivationA['id'], $eCultivationB['id']]), $this->getSorted($reading['question']['cCultivationAll']->getIds()), 'les séries en phase');

		$reading = $this->read($this->getDoneText(), ['all' => TRUE]);

		$this->assertTrue($reading['confirmation']['created']);
		$this->assertEquals(2, $reading['confirmation']['cTask']->count());

		EntryLib::write($this->eStructure, $reading);

		$cTask = $reading['confirmation']['cTask'];

		$this->assertEquals($this->getSorted([$eCultivationA['id'], $eCultivationB['id']]), $this->getSorted($cTask->getColumnCollection('cultivation')->getIds()), 'une sur chacune');

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals(2, $cancellation['cTaskReverted']->count());
		$this->assertEquals(0, \series\Task::model()->whereId('IN', $cTask->getIds())->count(), 'supprimées');

	}

	/**
	 * Une seule série en phase : pas de « Toutes », la réponse ne crée rien de plus qu'une question.
	 */
	public function testCreateOnePhase(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createCultivationInPhase(TRUE, 'Série A');
		$this->createCultivationInPhase(FALSE, 'Série B');

		$this->assertTrue($this->read($this->getDoneText())['question']['cCultivationAll']->empty());
		$this->assertEquals('series', $this->read($this->getDoneText(), ['all' => TRUE])['question']['name'], 'la réponse est ignorée');

	}

}
?>
