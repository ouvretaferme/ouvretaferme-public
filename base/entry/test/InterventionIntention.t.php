<?php
namespace entry;

/**
 * Une intervention créée depuis le champ (HN-B16) : l'action, l'espèce et la série, puis faite au jour dit avec
 * son temps passé, ou à faire à la semaine dite avec son temps prévu. L'état se choisit en puce, et sans elle se
 * déduit de la phrase. L'annulation retire l'intervention.
 *
 * Aucun refus éprouvé ici ne passe par un `rollBack()`, que la transaction du test emporterait
 * (`EntryAtomicity.t.php`).
 */
class InterventionIntentionTest extends EntryFixtureTest {

	/**
	 * Une fertilisation de l'espèce de la culture, sur sa série choisie en réponse.
	 */
	private function readIntervention(\series\Cultivation $eCultivation, string $rest = '', array $answers = []): array {

		$text = 'intervention '.$this->getAction(ACTION_FERTILISATION)['name'].' '.$eCultivation['plant']['name'].' '.$rest;

		return $this->read($text, $answers + ['addCultivation' => $eCultivation['id']]);

	}

	private function getTaskRead(\series\Task $eTask): \series\Task {

		return \series\Task::model()
			->select(['status', 'action', 'cultivation', 'plannedWeek', 'plannedDate', 'doneDate', 'timeExpected'])
			->whereId($eTask['id'])
			->get();

	}

	/**
	 * Une culture en phase, à la mi-juillet, vide quand le décor n'a pas la fertilisation.
	 */
	private function prepare(): \series\Cultivation {

		$this->setClockInSeason();

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return new \series\Cultivation();
		}

		return $this->createCultivationInPhase(TRUE);

	}

	/**
	 * Les actions de la catégorie Culture, sans espèce, hors récolte, qui a son verbe.
	 */
	public function testObjects(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cTaskObject = InterventionIntentionLib::getObjects($this->eStructure, EntryLib::getEmptyReading());

		$this->assertTrue($cTaskObject->offsetExists($this->getAction(ACTION_FERTILISATION)['id']));
		$this->assertFalse($cTaskObject->offsetExists($this->getAction(ACTION_RECOLTE)['id']), 'la récolte a son verbe');
		$this->assertEquals([], $cTaskObject->find(fn(\series\Task $eTask) => $eTask['plant']->notEmpty())->getIds(), 'l\'espèce se demande ensuite');

	}

	/**
	 * L'action seule demande l'espèce, l'espèce demande la série.
	 */
	public function testQuestions(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$reading = $this->read('intervention '.$this->getAction(ACTION_FERTILISATION)['name'].' ');

		$this->assertEquals('intervention', $reading['intention']);
		$this->assertEquals('plant', $reading['question']['name'] ?? NULL, 'l\'action seule demande l\'espèce');
		$this->assertTrue($reading['question']['cPlant']->offsetExists($eCultivation['plant']['id']));

		$reading = $this->read('intervention '.$this->getAction(ACTION_FERTILISATION)['name'].' '.$eCultivation['plant']['name'].' ');

		$this->assertEquals('series', $reading['question']['name'] ?? NULL, 'l\'espèce demande la série');
		$this->assertTrue($reading['question']['cCultivation']->offsetExists($eCultivation['id']));

	}

	/**
	 * Sans jour ni état, l'intervention est faite aujourd'hui, sans temps.
	 */
	public function testDoneToday(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$reading = $this->readIntervention($eCultivation);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::DONE, $reading['confirmation']['status']);
		$this->assertEquals('today', $reading['confirmation']['day']);
		$this->assertEquals([], $reading['confirmation']['dates'], 'aucun temps');

		EntryLib::write($this->eStructure, $reading);

		$eTaskRead = $this->getTaskRead($reading['confirmation']['eTask']);

		$this->assertEquals(\series\Task::DONE, $eTaskRead['status']);
		$this->assertEquals(\Clock::date(), $eTaskRead['doneDate']);
		$this->assertEquals($this->getAction(ACTION_FERTILISATION)['id'], $eTaskRead['action']['id']);
		$this->assertEquals($eCultivation['id'], $eTaskRead['cultivation']['id']);

	}

	/**
	 * Faite hier en une heure : le temps s'écrit hier sur la fiche de qui tape, et l'annulation retire
	 * l'intervention avec lui.
	 */
	public function testDoneYesterdayTimed(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty() or $this->assertDecorPresent($this->eStructure->hasTime(), 'la structure du décor suit le temps de travail') === FALSE) {
			return;
		}

		$yesterday = date('Y-m-d', \Clock::shift('-1 DAY'));

		$reading = $this->readIntervention($eCultivation, 'hier 1 h ');

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::DONE, $reading['confirmation']['status']);
		$this->assertEquals('yesterday', $reading['confirmation']['day']);
		$this->assertEquals(60, $reading['confirmation']['minutes']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		$this->assertEquals($yesterday, $this->getTaskRead($eTask)['doneDate']);
		$this->assertEquals(1.0, \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), $yesterday));

		$this->cancelUndo($reading['undo']);

		$this->assertTrue($this->getTaskRead($eTask)->empty(), 'l\'annulation retire l\'intervention');
		$this->assertEquals(0.0, \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), $yesterday), 'et son temps');

	}

	/**
	 * La semaine prochaine dite, l'intervention est à faire cette semaine-là, sans jour, et la durée devient le temps
	 * prévu, sans rien écrire sur la fiche de temps. L'annulation la retire et redessine le début de sa semaine.
	 */
	public function testTodoWeek(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$week = toWeek(\Clock::shift('+7 DAYS'));

		$reading = $this->readIntervention($eCultivation, 'semaine prochaine 30 min ');

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::TODO, $reading['confirmation']['status']);
		$this->assertEquals('next', $reading['confirmation']['weekChoice']);
		$this->assertEquals(30, $reading['confirmation']['expected']);
		$this->assertEquals(0, $reading['confirmation']['minutes']);
		$this->assertEquals([], $reading['confirmation']['dates']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];
		$eTaskRead = $this->getTaskRead($eTask);

		$this->assertEquals(\series\Task::TODO, $eTaskRead['status']);
		$this->assertEquals($week, $eTaskRead['plannedWeek']);
		$this->assertEquals(NULL, $eTaskRead['plannedDate']);
		$this->assertEquals(0.5, $eTaskRead['timeExpected']);

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals(week_date_starts($week), $cancellation['date']);
		$this->assertTrue($this->getTaskRead($eTask)->empty());

	}

	/**
	 * Un jour à venir rend l'intervention à faire ce jour-là.
	 */
	public function testTodoDay(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$tomorrow = date('Y-m-d', \Clock::shift('+1 DAY'));

		$reading = $this->readIntervention($eCultivation, 'demain ');

		$this->assertEquals(\series\Task::TODO, $reading['confirmation']['status'] ?? NULL);
		$this->assertEquals($tomorrow, $reading['confirmation']['eTask']['plannedDate']);
		$this->assertEquals(toWeek($tomorrow), $reading['confirmation']['eTask']['plannedWeek']);

	}

	/**
	 * La puce d'état l'emporte sur ce que la phrase laisse deviner : à faire sans semaine, c'est cette semaine ; faite,
	 * une semaine ou un jour à venir sont refusés, l'intervention restant posée sur la lecture.
	 */
	public function testStatusAnswer(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$reading = $this->readIntervention($eCultivation, '', ['status' => \series\Task::TODO]);

		$this->assertEquals(\series\Task::TODO, $reading['confirmation']['status']);
		$this->assertEquals(\Clock::week(), $reading['confirmation']['eTask']['plannedWeek']);
		$this->assertEquals('this', $reading['confirmation']['weekChoice']);

		$reading = $this->readIntervention($eCultivation, 'semaine prochaine ', ['status' => \series\Task::DONE]);

		$this->assertEquals('doneWeek', $reading['refusal']['reason'] ?? NULL);
		$this->assertTrue($reading['task']->notEmpty(), 'la série reste choisie');

		$this->assertEquals('future', $this->readIntervention($eCultivation, 'demain ', ['status' => \series\Task::DONE])['refusal']['reason'] ?? NULL);

		$this->assertEquals(\series\Task::TODO, $this->readIntervention($eCultivation, 'cette semaine ')['confirmation']['status'] ?? NULL, 'la puce Cette semaine garde l\'intervention à faire');

	}

	/**
	 * Un jour passé ne se planifie pas : à faire, « hier » est refusé, la semaine restant le moyen de dire un mois
	 * qui vient.
	 */
	public function testTodoPastDay(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$reading = $this->readIntervention($eCultivation, 'hier ', ['status' => \series\Task::TODO]);

		$this->assertEquals('plannedPast', $reading['refusal']['reason'] ?? NULL);
		$this->assertTrue($reading['task']->notEmpty(), 'la série reste choisie');

	}

	/**
	 * Un numéro de semaine que l'année n'a pas est refusé.
	 */
	public function testWeekInvalid(): void {

		$eCultivation = $this->prepare();

		if($eCultivation->empty()) {
			return;
		}

		$this->assertEquals('week', $this->readIntervention($eCultivation, 'semaine 60 ')['refusal']['reason'] ?? NULL);

	}

}
?>
