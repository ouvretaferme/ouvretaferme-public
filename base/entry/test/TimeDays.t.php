<?php
namespace entry;

/**
 * Un temps sur plusieurs jours de la semaine (HN-B15) : les jours que la ligne qui écrit propose en puces, ceux
 * que ses jetons choisissent, ce qui s'écrit sur chacun, et l'annulation qui les défait d'un geste. Sans jour
 * tapé, aujourd'hui n'est qu'un choix par défaut, que la première puce remplace ; un jour tapé reste (HN-B17).
 *
 * L'horloge est un jeudi de la saison du décor : les jours passés de sa semaine sont lundi, mardi et mercredi,
 * et le vendredi est à venir.
 */
class TimeDaysTest extends EntryFixtureTest {

	private string $monday;
	private string $tuesday;
	private string $wednesday;
	private string $thursday;
	private string $friday;

	private function setClockThursday(): void {

		$thursday = date('Y-m-d', strtotime('thursday this week', strtotime($this->getSeason().'-07-15')));

		$this->setClock($thursday.' 10:00:00');

		[$this->monday, $this->tuesday, $this->wednesday, $this->thursday, $this->friday] = array_slice(week_dates(toWeek($thursday)), 0, 5);

	}

	/**
	 * Une intervention de fertilisation à faire cette semaine, sur une culture en phase.
	 */
	private function createTaskFertilisation(): \series\Task {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_FERTILISATION),
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => \Clock::date(),
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()` : le décor fixe telles quelles la semaine et le jour prévus, le statut et la catégorie,
		// que `build()` ne prend que d'un formulaire posté (`planned`, comme `TimeIntentionLib::buildTask()`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	private function getTimeText(string $rest): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '.$rest;
	}

	private function getTime(\series\Task $eTask, string $date): float {
		return \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), $date);
	}

	/**
	 * Les puces sont les jours de la semaine qu'un temps admet, jusqu'à aujourd'hui, et seul le jour dit est
	 * choisi. Le vendredi, à venir, n'en est pas.
	 */
	public function testDaysOffered(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$this->createTaskFertilisation();

		$reading = $this->read($this->getTimeText('1 h'));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals([$this->thursday], $reading['confirmation']['dates'], 'sans jeton, le temps s\'écrit le jour dit');
		$this->assertEquals([
			['date' => $this->monday, 'selected' => FALSE],
			['date' => $this->tuesday, 'selected' => FALSE],
			['date' => $this->wednesday, 'selected' => FALSE],
			['date' => $this->thursday, 'selected' => TRUE],
		], $reading['days'], 'les jours passés de la semaine, sans le vendredi à venir');

		$reading = $this->read($this->getTimeText('1 h hier'));

		$this->assertEquals([$this->wednesday], $reading['confirmation']['dates'], 'le jour dit par la phrase est celui qui s\'écrit');
		$this->assertEquals([FALSE, FALSE, TRUE, FALSE], array_column($reading['days'], 'selected'), 'sa puce est la choisie');

	}

	/**
	 * Un lundi, la semaine n'a qu'un jour à proposer : aucune puce.
	 */
	public function testMondayAlone(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$this->setClock($this->monday.' 10:00:00');
		$this->createTaskFertilisation();

		$reading = $this->read($this->getTimeText('1 h'));

		$this->assertEquals([$this->monday], $reading['confirmation']['dates']);
		$this->assertEquals([], $reading['days'], 'un seul jour n\'a pas de puce');

	}

	/**
	 * Sans jour tapé, les jetons de jour remplacent aujourd'hui, et le même temps s'écrit sur chacun. Un jour hors
	 * de la semaine du jour dit, ou à venir, ne s'écrit pas, même posté.
	 */
	public function testDaysWritten(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$eTask = $this->createTaskFertilisation();

		$lastWeek = date('Y-m-d', strtotime($this->monday.' -7 DAY'));

		$reading = $this->read($this->getTimeText('1 h 30'), ['days' => [$this->tuesday, $this->monday, $lastWeek, $this->friday]]);

		$this->assertEquals([$this->monday, $this->tuesday], $reading['confirmation']['dates'], 'les jours de la semaine, dans leur ordre, sans aujourd\'hui');
		$this->assertEquals([TRUE, TRUE, FALSE, FALSE], array_column($reading['days'], 'selected'));
		$this->assertEquals($reading['confirmation']['dates'], EntryUi::getEntry($reading)['days'], 'le champ apprend les jours retenus, et retire les jetons des autres');

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(1.5, $this->getTime($eTask, $this->monday));
		$this->assertEquals(1.5, $this->getTime($eTask, $this->tuesday));
		$this->assertEquals(0.0, $this->getTime($eTask, $this->wednesday), 'le jour non choisi ne reçoit rien');
		$this->assertEquals(0.0, $this->getTime($eTask, $this->thursday), 'aujourd\'hui, remplacé par les jetons, ne reçoit rien');
		$this->assertEquals(0.0, $this->getTime($eTask, $lastWeek), 'le jour hors de la semaine ne reçoit rien');

	}

	/**
	 * Un seul jeton remplace aujourd'hui : « Mar » puis 1 h 30 s'écrit le mardi seulement. Un jour tapé, même
	 * aujourd'hui, reste, et les jetons s'y ajoutent. Les jetons retirés, aujourd'hui revient.
	 */
	public function testDefaultReplaced(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$this->createTaskFertilisation();

		$this->assertEquals([$this->tuesday], $this->read($this->getTimeText('1 h 30'), ['days' => [$this->tuesday]])['confirmation']['dates'], 'le premier jeton remplace aujourd\'hui');
		$this->assertEquals([$this->monday, $this->wednesday], $this->read($this->getTimeText('hier 1 h 30'), ['days' => [$this->monday]])['confirmation']['dates'], 'le jour tapé reste, le jeton s\'y ajoute');
		$this->assertEquals([$this->monday, $this->thursday], $this->read($this->getTimeText('aujourd\'hui 1 h 30'), ['days' => [$this->monday]])['confirmation']['dates'], 'aujourd\'hui tapé reste');
		$this->assertEquals([$this->thursday], $this->read($this->getTimeText('1 h 30'), ['days' => []])['confirmation']['dates'], 'sans jeton, aujourd\'hui');

		// La coche écrit le jour dit, seul : elle ne se propose que quand le temps va sur lui seul.
		$hasDone = fn(array $reading) => in_array('timeDone', array_map(fn(array $result) => explode(':', $result['value'] ?? '')[0], EntryUi::getResults($this->eStructure, $reading, new \Collection(), [])), TRUE);

		$this->assertTrue($hasDone($this->read($this->getTimeText('1 h 30'))), 'le temps d\'aujourd\'hui propose la coche');
		$this->assertFalse($hasDone($this->read($this->getTimeText('1 h 30'), ['days' => [$this->tuesday]])), 'pas sur un jour qui remplace aujourd\'hui');

	}

	/**
	 * L'annulation défait d'un geste le temps de chaque jour, et seulement lui : un temps posé avant reste.
	 */
	public function testDaysCancelled(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$eTask = $this->createTaskFertilisation();

		$readingBefore = $this->read($this->getTimeText('1 h'), ['days' => [$this->monday]]);

		EntryLib::write($this->eStructure, $readingBefore);

		$reading = $this->read($this->getTimeText('2 h'), ['days' => [$this->monday, $this->wednesday]]);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(3.0, $this->getTime($eTask, $this->monday));

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals([$this->monday, $this->wednesday], $cancellation['dates']);
		$this->assertEquals(4.0, $cancellation['time'], 'deux heures sur chacun des deux jours');
		$this->assertFalse($cancellation['created']);

		$this->assertEquals(1.0, $this->getTime($eTask, $this->monday), 'le temps posé avant reste');
		$this->assertEquals(0.0, $this->getTime($eTask, $this->wednesday));
		$this->assertEquals(0.0, $this->getTime($eTask, $this->thursday), 'aujourd\'hui, remplacé, n\'a rien reçu');

	}

	/**
	 * L'intervention que la saisie ajoute au planning est prévue au premier jour où le temps s'écrit, pas
	 * aujourd'hui quand une puce l'a remplacé (24/10).
	 */
	public function testAddedPlannedFirstDay(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$eCultivation = $this->createCultivationInPhase(TRUE);

		$getPlanned = fn(array $answers, string $rest = '1 h') => $this->read($this->getTimeText($rest), ['addCultivation' => $eCultivation['id']] + $answers)['confirmation']['eTask']['plannedDate'];

		$this->assertEquals($this->thursday, $getPlanned([]), 'sans puce, le jour dit');
		$this->assertEquals($this->tuesday, $getPlanned(['days' => [$this->wednesday, $this->tuesday]]), 'le premier des jours choisis');
		$this->assertEquals($this->monday, $getPlanned(['days' => [$this->monday]], 'hier 1 h'), 'une puce plus tôt que le jour tapé');
		$this->assertEquals($this->wednesday, $getPlanned(['days' => [$this->friday]], 'hier 1 h'), 'le jour tapé, la puce à venir ne s\'écrivant pas');

		$reading = $this->read($this->getTimeText('1 h'), ['addCultivation' => $eCultivation['id'], 'days' => [$this->tuesday]]);

		EntryLib::write($this->eStructure, $reading);

		$eTask = \series\Task::model()
			->select(['plannedDate', 'plannedWeek'])
			->whereId($reading['confirmation']['eTask']['id'])
			->get();

		$this->assertEquals($this->tuesday, $eTask['plannedDate'], 'écrite au jour de la puce');
		$this->assertEquals(toWeek($this->tuesday), $eTask['plannedWeek']);

	}

	/**
	 * Une intervention ajoutée par la saisie sur plusieurs jours part avec eux à l'annulation.
	 */
	public function testAddedCancelled(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->setClockThursday();
		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getTimeText('1 h '), ['addCultivation' => $eCultivation['id'], 'days' => [$this->tuesday, $this->wednesday]]);

		$this->assertEquals([$this->tuesday, $this->wednesday], $reading['confirmation']['dates']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		$this->assertEquals(1.0, $this->getTime($eTask, $this->tuesday));
		$this->assertEquals(1.0, $this->getTime($eTask, $this->wednesday));

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertTrue($cancellation['created'], 'l\'intervention est reconnue comme ajoutée par la saisie');
		$this->assertTrue(\series\Task::model()->whereId($eTask['id'])->exists() === FALSE, 'l\'intervention ajoutée part avec ses deux jours');

		$reading = $this->read($this->getTimeText('1 h '), ['addCultivation' => $eCultivation['id'], 'days' => [$this->tuesday, $this->wednesday]]);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		// Sans `TimesheetLib` : le décor met à zéro le temps du mardi, comme « Retirer » le laisse sur la fiche de
		// l'intervention, sans repasser par le recalcul du temps de l'intervention que l'annulation ne lit pas.
		\series\Timesheet::model()
			->whereTask($eTask)
			->whereDate($this->tuesday)
			->update(['time' => 0.0]);

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertTrue($cancellation['created'], 'un jour retiré depuis ne la fait pas passer pour retouchée');
		$this->assertTrue(\series\Task::model()->whereId($eTask['id'])->exists() === FALSE, 'elle part avec le jour qui lui reste');

	}

}
?>
