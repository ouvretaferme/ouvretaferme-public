<?php
namespace entry;

/**
 * Le temps et la coche d'un même geste (lot S4c) : sans verbe, une durée garde la phrase au temps, qui propose
 * la coche de la même phrase en seconde ligne ; avec le verbe, la coche écrit la durée sur l'intervention
 * qu'elle coche, ou qu'elle crée, et l'annulation défait les deux.
 *
 * Aucun refus éprouvé ici ne passe par un `rollBack()`, que la transaction du test emporterait
 * (`EntryAtomicity.t.php`).
 */
class DoneTimeTest extends EntryFixtureTest {

	/**
	 * Une fertilisation à faire sur la culture, prévue à la semaine décalée de `$weeks` du jour dit.
	 */
	private function createTaskPlanned(\series\Cultivation $eCultivation, int $weeks = 0): \series\Task {
		return $this->createTask($eCultivation, ACTION_FERTILISATION, \series\Task::TODO, toWeek(\Clock::date().' '.(($weeks < 0) ? '- ' : '+ ').abs($weeks).' WEEKS'));
	}

	private function getDoneText(string $rest = ''): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '.$rest;
	}

	private function getTaskRead(\series\Task $eTask): \series\Task {

		return \series\Task::model()
			->select(['status', 'doneDate', 'plannedWeek', 'time'])
			->whereId($eTask['id'])
			->get();

	}

	/**
	 * Le temps de la personne connectée sur l'intervention, ce jour-là.
	 */
	private function getTimeDay(\series\Task $eTask, string $date): float {
		return \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), $date);
	}

	/**
	 * Le jeton que pose le geste est un mot que la coche lit.
	 */
	public function testVerb(): void {

		$this->assertEquals(DoneIntentionLib::getWords()[0], DoneIntentionUi::getVerb());

	}

	/**
	 * Sans verbe, la durée garde la phrase au temps, et la coche de la même intervention est gardée pour l'écran.
	 */
	public function testOffered(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getDoneText('2 h '));

		$this->assertEquals('time', $reading['intention'], 'la durée garde le temps');
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		$readingDone = $reading['discarded']['done'];

		$this->assertTrue($readingDone !== NULL, 'la coche est gardée');
		$this->assertEquals(EntryLib::WRITE, $readingDone['outcome']);
		$this->assertEquals($eTask['id'], $readingDone['confirmation']['cTask']->first()['id']);
		$this->assertEquals(120, $readingDone['confirmation']['minutes']);

		$this->assertEquals(NULL, $this->read($this->getDoneText())['discarded']['done'], 'sans durée, la coche est la lecture');

	}

	/**
	 * Une intervention déjà faite n'attend plus rien : la coche n'est pas proposée sous le temps.
	 */
	public function testNotOfferedWhenDone(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTask($this->createCultivationInPhase(TRUE), ACTION_FERTILISATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		$this->assertEquals(NULL, $this->read($this->getDoneText('2 h '))['discarded']['done']);

	}

	/**
	 * La prévue six semaines plus tôt est au-delà de ce que le temps propose, de la semaine ou en retard (HN-B7) :
	 * il demande où ajouter la sienne (2c). La coche, elle, la vise, et le verbe y écrit la durée.
	 */
	public function testBeyondLate(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE), -6);

		$reading = $this->read($this->getDoneText('2 h '));

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals('series', $reading['question']['name'], 'le temps demande où l\'ajouter');
		$this->assertEquals($eTask['id'], $reading['discarded']['done']['confirmation']['cTask']->first()['id'], 'la coche vise la prévue');

		$reading = $this->read('fait '.$this->getDoneText('2 h '));

		$this->assertEquals('done', $reading['intention']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		EntryLib::write($this->eStructure, $reading);

		$eTaskRead = $this->getTaskRead($eTask);

		$this->assertEquals(\series\Task::DONE, $eTaskRead['status']);
		$this->assertEquals(\Clock::date(), $eTaskRead['doneDate']);
		$this->assertEquals(2.0, $this->getTimeDay($eTask, \Clock::date()), 'le temps est sur la prévue');

	}

	/**
	 * Un lundi, « hier » tombe la semaine d'avant, où le temps ne voit pas la prévue de la semaine : la coche,
	 * elle, la vise encore, et reste proposée quoi que le temps réponde (refus ou série où ajouter la sienne).
	 */
	public function testYesterdayOnMonday(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClock(date('Y-m-d', strtotime('monday this week', strtotime($this->getSeason().'-07-15'))).' 10:00:00');

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getDoneText('hier 2 h '));

		$this->assertEquals('time', $reading['intention']);
		$this->assertFalse($reading['outcome'] === EntryLib::WRITE, 'le temps ne trouve rien la semaine d\'avant');
		$this->assertEquals($eTask['id'], $reading['discarded']['done']['confirmation']['cTask']->first()['id'], 'la coche vise la prévue');

	}

	/**
	 * Le verbe et la durée : la coche et le temps, au jour dit.
	 */
	public function testWrite(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$date = date('Y-m-d', \Clock::shift('-1 DAY'));

		$reading = $this->read('fait '.$this->getDoneText('hier 45 min '));

		$this->assertEquals(45, $reading['confirmation']['minutes']);
		$this->assertEquals(NULL, $reading['question']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals($date, $this->getTaskRead($eTask)['doneDate']);
		$this->assertEquals(0.75, $this->getTimeDay($eTask, $date));

	}

	/**
	 * Rien de prévu : la coche crée l'intervention faite sur la série choisie, avec son temps, et l'annulation
	 * l'emporte avec lui.
	 */
	public function testCreate(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getDoneText('1 h '));

		$this->assertEquals('series', $reading['discarded']['done']['question']['name'], 'la coche demanderait aussi la série');

		$reading = $this->read('fait '.$this->getDoneText('1 h '), ['addCultivation' => $eCultivation['id']]);

		$this->assertEquals('done', $reading['intention']);
		$this->assertTrue($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['cTask']->first();

		$this->assertEquals(\series\Task::DONE, $this->getTaskRead($eTask)['status']);
		$this->assertEquals(1.0, $this->getTimeDay($eTask, \Clock::date()));

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertTrue($cancellation['created']);
		$this->assertTrue($this->getTaskRead($eTask)->empty(), 'supprimée avec son temps');

	}

	/**
	 * Annuler remet à faire puis retire le temps ; une intervention retouchée depuis garde coche et temps.
	 */
	public function testCancel(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTask = $this->createTaskPlanned($eCultivation);

		$reading = $this->read('fait '.$this->getDoneText('1 h '));
		EntryLib::write($this->eStructure, $reading);

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals(1.0, $cancellation['time']);
		$this->assertEquals(\series\Task::TODO, $this->getTaskRead($eTask)['status'], 'remise à faire');
		$this->assertEquals(0.0, $this->getTimeDay($eTask, \Clock::date()), 'sans son temps');

		$reading = $this->read('fait '.$this->getDoneText('1 h '));
		EntryLib::write($this->eStructure, $reading);

		// Retouchée ailleurs après la coche, faite une autre semaine. Sans `TaskLib` ni `build()` : le décor pose
		// directement l'état qu'une autre voie aurait laissé, seule compte la condition de `updateTodoIfLeft()`.
		\series\Task::model()->update($eTask, ['doneWeek' => toWeek(\Clock::date().' - 1 WEEK')]);

		try {
			$this->cancelUndo($reading['undo']);
			$this->assertTrue(FALSE, 'une intervention retouchée ne s\'annule pas');
		} catch(\FailAction) {
		}

		$this->assertEquals(\series\Task::DONE, $this->getTaskRead($eTask)['status']);
		$this->assertEquals(1.0, $this->getTimeDay($eTask, \Clock::date()), 'le temps reste');

	}

	/**
	 * Une structure qui ne suit pas le temps refuse la durée, et coche sans elle.
	 */
	public function testUntracked(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$eConfiguration = $this->eStructure->conf();
		$time = $eConfiguration['time'];

		try {

			$eConfiguration['time'] = FALSE;

			$this->assertEquals('untracked', $this->read('fait '.$this->getDoneText('1 h '))['refusal']['reason']);
			$this->assertEquals(EntryLib::WRITE, $this->read('fait '.$this->getDoneText())['outcome']);

		} finally {
			$eConfiguration['time'] = $time;
		}

	}

}
?>
