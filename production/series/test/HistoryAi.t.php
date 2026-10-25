<?php
namespace series;

/**
 * L'historique des gestes d'une intervention (`HistoryLib`, lot L5f) : chaque geste écrit une ligne, le temps
 * et la récolte en écart signé, dans la transaction du geste.
 *
 * Les cas passent par les Libs que la page, l'API et la saisie appellent, jamais par le modèle : ce sont
 * leurs points d'écriture qui sont éprouvés. Les jours sont pris dans une semaine passée, pour que les
 * temps que le membre porte aujourd'hui sur la structure hôte ne pèsent pas sur les totaux, et le plafond
 * de 24 h se calcule depuis ce que le jour porte déjà.
 *
 * Le refus `time.negative`, qui vide la pile de transactions et le décor avec, est dans `HistoryNegativeAi`.
 *
 * Lancement : dev/test package=series file=HistoryAi
 */
class HistoryAiTest extends SeriesFixtureAiTest {

	const MONDAY = '2024-03-04';
	const WEDNESDAY = '2024-03-06';

	/** L'intervention relue entière, comme la page la lit avant d'y écrire du temps. */
	private function reload(Task $eTask): Task {

		return Task::model()
			->select(Task::getSelection())
			->whereId($eTask)
			->get();

	}

	/**
	 * L'historique en lignes lisibles dans un message d'assertion, du plus récent au plus ancien :
	 * `événement jour valeur`, la valeur étant l'écart signé.
	 */
	private function getLines(Task $eTask): array {

		$lines = [];

		foreach(HistoryLib::getByTask($eTask) as $eHistory) {

			$lines[] = match($eHistory['event']) {
				History::DONE, History::TODO => $eHistory['event'],
				History::TIME => History::TIME.' '.$eHistory['day'].' '.round($eHistory['time'], 2),
				History::HARVEST => History::HARVEST.' '.$eHistory['day'].' '.round($eHistory['harvest'], 2).' '.$eHistory['harvestUnit'],
			};

		}

		return $lines;

	}

	private function writeDay(Task $eTask, string $date, float $time): void {

		TimesheetLib::writeTimeForDay($this->reload($eTask), new Timesheet([
			'structure' => $this->eStructure,
			'user' => $this->getMember(),
			'date' => $date,
			'time' => $time
		]));

	}

	/**
	 * Fait puis défait : une ligne par geste, signée de la personne en ligne.
	 */
	public function testDoneThenTodo(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eTask = $this->reload($this->createTask());

		TaskLib::updateDoneCollection(new \Collection([$eTask['id'] => $eTask]), NULL);

		$eTask = $this->reload($eTask);

		TaskLib::updateTodoCollection(new \Collection([$eTask['id'] => $eTask]));

		$this->assertEquals([History::TODO, History::DONE], $this->getLines($eTask), 'fait puis défait n\'écrivent pas une ligne chacun');

		$cHistory = HistoryLib::getByTask($eTask);

		foreach($cHistory as $eHistory) {
			$this->assertEquals($this->getMember()['id'], $eHistory['user']['id'], 'une ligne n\'est pas signée de la personne en ligne');
		}

	}

	/**
	 * Une ligne de temps créée, corrigée puis supprimée : trois écarts, dont la somme est nulle.
	 */
	public function testTimesheetCreateUpdateDelete(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eTask = $this->createTask();
		$eTimesheet = $this->createTimesheet($eTask, 2.0, self::MONDAY);

		$eTimesheet['time'] = 3.5;
		TimesheetLib::update($eTimesheet, ['time']);

		TimesheetLib::delete($eTimesheet);

		$this->assertEquals([
			'time '.self::MONDAY.' -3.5',
			'time '.self::MONDAY.' 1.5',
			'time '.self::MONDAY.' 2',
		], $this->getLines($eTask), 'la création, la correction et la suppression d\'une ligne de temps n\'écrivent pas leur écart');

		$eHistory = HistoryLib::getByTask($eTask)->first();

		$this->assertEquals($this->getMember()['id'], $eHistory['worker']['id'], 'l\'écart n\'est pas attribué à la personne dont c\'est le temps');

	}

	/**
	 * Un jour refusé parce que la journée dépasserait 24 h n'écrit rien, pas même son historique.
	 */
	public function testTooLongWritesNothing(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eTask = $this->createTask();

		$free = round(24.0 - TimesheetLib::getTimeByUserDate($this->eStructure, $this->getMember(), self::WEDNESDAY), 2);

		$fw = new \FailWatch();

		$this->writeDay($eTask, self::WEDNESDAY, $free + 0.25);

		$this->assertTrue($fw->has('Timesheet::time.tooLong'), 'une journée portée au-delà de 24 h n\'est pas refusée par time.tooLong');
		$this->assertEquals([], $this->getLines($eTask), 'un jour refusé écrit une ligne d\'historique');

	}

	/**
	 * L'historique part avec son intervention, qu'elle soit supprimée seule ou avec d'autres.
	 */
	public function testDeleteTaskEmptiesHistory(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eTask = $this->createTask();
		$this->createTimesheet($eTask, 1.0, self::MONDAY);

		$eTaskOther = $this->createTask();
		$this->createTimesheet($eTaskOther, 1.0, self::MONDAY);

		$this->assertEquals(1, count($this->getLines($eTask)), 'le décor n\'a pas écrit son historique');

		TaskLib::delete($this->reload($eTask));

		$this->assertEquals(0, History::model()->whereTask($eTask)->count(), 'la suppression d\'une intervention laisse son historique');

		TaskLib::deleteCollection(new \Collection([$eTaskOther['id'] => $this->reload($eTaskOther)]));

		$this->assertEquals(0, History::model()->whereTask($eTaskOther)->count(), 'la suppression d\'un lot d\'interventions laisse leur historique');

	}

}
?>
