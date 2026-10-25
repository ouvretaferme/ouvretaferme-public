<?php
namespace entry;

/**
 * Le temps sur une intervention restée à faire d'une semaine passée (HN-B7) : la saisie la propose avec celles
 * de la semaine, rangée après elles, et la retient avant d'ajouter une intervention au planning. Le passé
 * s'arrête à la semaine du jour le plus ancien qu'un temps admet (`IntentionLib::getPastLimit()`).
 */
class TimeLateTest extends EntryFixtureTest {

	/**
	 * Une intervention de fertilisation sur la culture, prévue au jour décalé de `$shift`, à faire ou faite ce
	 * jour-là.
	 */
	private function createTaskFertilisation(\series\Cultivation $eCultivation, string $shift, string $status = \series\Task::TODO): \series\Task {

		$date = date('Y-m-d', \Clock::shift($shift));

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_FERTILISATION),
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => $status,
			'plannedWeek' => toWeek($date),
			'plannedDate' => $date,
			'doneWeek' => ($status === \series\Task::DONE) ? toWeek($date) : NULL,
			'doneDate' => ($status === \series\Task::DONE) ? $date : NULL,
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()` : le décor fixe telles quelles la semaine et le jour prévus, le statut et la catégorie,
		// que `build()` ne prend que d'un formulaire posté (`planned`, comme `TimeIntentionLib::buildTask()`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	private function getTimeText(string $rest = ''): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '.$rest;
	}

	private function getObjectName(): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.mb_strtolower($this->getPlant()['name']);
	}

	/**
	 * L'objet de la fertilisation de l'espèce du décor, vide quand la saisie ne la propose pas.
	 */
	private function getObject(): \Element {

		// Une lecture neuve : les objets ne valent que pour la lecture en cours (`EntryLib::getRead()`), et le décor
		// change entre deux appels.
		$this->read('');

		$cTaskObject = TimeIntentionLib::getObjects($this->eStructure, EntryLib::getEmptyReading());

		return $cTaskObject->find(fn(\series\Task $eTaskObject) => $eTaskObject['name'] === $this->getObjectName(), limit: 1);

	}

	/**
	 * Restée à faire deux semaines plus tôt, l'intervention se propose en retard, avec sa semaine, et reçoit le
	 * temps du jour dit sans question ni ajout au planning. Elle reste à faire, à sa semaine.
	 */
	public function testLateWrites(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockInSeason();

		$eTask = $this->createTaskFertilisation($this->createCultivationInPhase(TRUE), '-14 DAY');

		$eTaskObject = $this->getObject();

		$this->assertTrue($eTaskObject->notEmpty(), 'l\'intervention en retard est proposée');
		$this->assertTrue($eTaskObject['listed'], 'dans la liste, sans frappe');
		$this->assertEquals($eTask['plannedWeek'], $eTaskObject['plannedWeekLate'], 'avec la semaine où elle était prévue');

		$reading = $this->read($this->getTimeText('1 h'));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eTask['id'], $reading['task']['id'], 'le temps va sur elle, pas sur une intervention ajoutée');
		$this->assertFalse($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertTrue($reading['confirmation']['eTask']['late'], 'relue pour l\'écriture, elle reste en retard pour la pastille');
		$this->assertEquals(1.0, \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), \Clock::date()), 'le temps est posé au jour dit');

		$eTaskWritten = \series\Task::model()
			->select(['status', 'plannedWeek'])
			->whereId($eTask['id'])
			->get();

		$this->assertEquals(\series\Task::TODO, $eTaskWritten['status'], 'elle reste à faire');
		$this->assertEquals($eTask['plannedWeek'], $eTaskWritten['plannedWeek'], 'à la semaine où elle était prévue');

	}

	/**
	 * Sans semaine prévue, une intervention à faire n'est pas en retard ; faite, non plus.
	 */
	public function testLateRule(): void {

		$this->setClockInSeason();

		$lastWeek = date('Y-m-d', \Clock::shift('-7 DAY'));

		$this->assertTrue(TimeIntentionLib::isLate(new \series\Task(['status' => \series\Task::TODO, 'plannedWeek' => toWeek($lastWeek)]), \Clock::date()));
		$this->assertFalse(TimeIntentionLib::isLate(new \series\Task(['status' => \series\Task::TODO, 'plannedWeek' => NULL]), \Clock::date()), 'jamais prévue');
		$this->assertFalse(TimeIntentionLib::isLate(new \series\Task(['status' => \series\Task::DONE, 'plannedWeek' => toWeek($lastWeek)]), \Clock::date()), 'faite');
		$this->assertFalse(TimeIntentionLib::isLate(new \series\Task(['status' => \series\Task::TODO, 'plannedWeek' => \Clock::week()]), \Clock::date()), 'de la semaine');

		$this->assertFalse(TimeIntentionLib::isProposed(new \series\Task(['status' => \series\Task::TODO, 'plannedWeek' => NULL]), \Clock::date()), 'la coche ne propose pas de durée sur une intervention jamais prévue');

	}

	/**
	 * Le passé s'arrête à la semaine du jour le plus ancien qu'un temps admet ; une intervention faite ne se
	 * propose pas en retard.
	 */
	public function testBounds(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->createTaskFertilisation($eCultivation, '-42 DAY');
		$this->createTaskFertilisation($eCultivation, '-14 DAY', \series\Task::DONE);

		$this->assertTrue($this->getObject()->empty(), 'ni au-delà de la borne, ni faite');

		$eTaskLimit = $this->createTaskFertilisation($eCultivation, '- '.EntrySetting::PAST_DAYS.' DAY');

		$this->assertEquals([$eTaskLimit['id']], $this->getObject()['cTask']->getIds(), 'la semaine du jour le plus ancien en est');

	}

	/**
	 * Rangées après celles de la semaine. Une même action sur la même espèce, cette semaine et en retard, ne fait
	 * qu'un objet de la semaine, et la question dit laquelle est en retard.
	 */
	public function testWithWeek(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE or $this->requireAction(ACTION_TRAITEMENT) === FALSE) {
			return;
		}

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTaskLate = $this->createTaskFertilisation($eCultivation, '-14 DAY');

		$eTaskTreatment = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => \Clock::date(),
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, comme `createTaskFertilisation()`.
		\series\TaskLib::create($eTaskTreatment);

		$this->read('');

		$names = TimeIntentionLib::getListed(TimeIntentionLib::getObjects($this->eStructure, EntryLib::getEmptyReading()))->getColumn('name');

		$this->assertTrue(
			array_search($this->getObjectName(), $names, TRUE) > array_search($this->getAction(ACTION_TRAITEMENT)['name'].' '.mb_strtolower($this->getPlant()['name']), $names, TRUE),
			'l\'intervention en retard vient après celle de la semaine, même si son nom la précède'
		);

		$eTaskWeek = $this->createTaskFertilisation($eCultivation, '+0 DAY');

		$this->assertEquals(NULL, $this->getObject()['plannedWeekLate'], 'avec une intervention de la semaine, l\'objet est de la semaine');

		$reading = $this->read($this->getTimeText('30 min'));

		$this->assertEquals('task', $reading['question']['name']);

		$late = $reading['question']['cTask']->makeArray(function(\series\Task $eTask, &$key) {
			$key = $eTask['id'];
			return $eTask['late'];
		});

		$this->assertEquals([$eTaskWeek['id'], $eTaskLate['id']], array_keys($late), 'celle de la semaine d\'abord, celle en retard ensuite');
		$this->assertEquals([FALSE, TRUE], array_values($late), 'la seconde est dite en retard');

	}

}
?>
