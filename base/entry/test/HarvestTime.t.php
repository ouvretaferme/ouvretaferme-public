<?php
namespace entry;

/**
 * Le temps dit avec la récolte, « courgette 12 kg 30 min » (lot 2a) : il s'écrit pour l'utilisateur
 * connecté, sur l'intervention de la récolte, comme Consigner l'écrit (`HarvestWeekLib::writeEntry()`),
 * et Annuler le retire avec elle.
 */
class HarvestTimeTest extends EntryFixtureTest {

	private function write(string $rest): array {

		$reading = $this->read($this->getText($rest.' '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la phrase « '.$rest.' » s\'écrit');

		EntryLib::write($this->eStructure, $reading);

		return $reading;

	}

	private function cancel(array $reading): array {

		return $this->cancelUndo($reading['undo']);

	}

	/**
	 * Les lignes de temps d'une intervention : la personne, le jour, et le temps en minutes, que le
	 * flottant simple de la base arrondit.
	 */
	private function getTimes(\series\Task $eTask): array {

		return \series\Timesheet::model()
			->select(['user', 'date', 'time'])
			->whereTask($eTask)
			->getCollection()
			->makeArray(fn(\series\Timesheet $eTimesheet) => [$eTimesheet['user']['id'], $eTimesheet['date'], (int)round($eTimesheet['time'] * 60)]);

	}

	private function getTaskState(\series\Task $eTask): array {

		$eTaskRead = \series\Task::model()
			->select(['status', 'harvest', 'time'])
			->whereId($eTask)
			->get();

		return [
			$eTaskRead['status'],
			($eTaskRead['harvest'] === NULL) ? NULL : round((float)$eTaskRead['harvest'], 2),
			($eTaskRead['time'] === NULL) ? NULL : (int)round($eTaskRead['time'] * 60),
		];

	}

	/**
	 * La saisie et Consigner, la même récolte et le même temps sur deux séries : les mêmes lignes de temps,
	 * et l'intervention dans le même état.
	 */
	public function testSameAsConsigner(): void {

		$eCultivationEntry = $this->createCultivationInPhase(TRUE, 'Série saisie');
		$eTaskEntry = $this->createTask($eCultivationEntry, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$reading = $this->read($this->getText('12 kg 30 min '), ['cultivation' => $eCultivationEntry['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la phrase s\'écrit');
		$this->assertEquals(30, $reading['confirmation']['minutes']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals($eTaskEntry['id'], $reading['confirmation']['eTask']['id'], 'le temps va sur l\'intervention de la récolte');

		$eCultivationConsigner = $this->createCultivationInPhase(TRUE, 'Série Consigner');
		$eTaskConsigner = $this->createTask($eCultivationConsigner, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		\planning\HarvestWeekLib::writeEntry($this->eStructure, \series\CultivationLib::getById($eCultivationConsigner['id']), [\Clock::date() => TRUE], [
			'date' => \Clock::date(),
			'harvestMore' => '12',
			'time' => [$this->getMember()['id'] => '0.5'],
		], stock: FALSE);

		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 30]], $this->getTimes($eTaskEntry), 'la saisie écrit 30 min de la personne connectée, ce jour');
		$this->assertEquals($this->getTimes($eTaskConsigner), $this->getTimes($eTaskEntry), 'les mêmes lignes de temps que Consigner');
		$this->assertEquals($this->getTaskState($eTaskConsigner), $this->getTaskState($eTaskEntry), 'l\'intervention dans le même état que par Consigner');

	}

	/**
	 * Le temps s'ajoute au temps du jour de la personne sur l'intervention, comme dans Consigner, et une
	 * intervention neuve le porte dès sa création.
	 */
	public function testAddedToDay(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->write('3 kg 1h30');

		$eTask = $reading['confirmation']['eTask'];

		$this->assertTrue($reading['confirmation']['created'], 'la saisie crée l\'intervention');
		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 90]], $this->getTimes($eTask));

		$this->write('2 kg 15 min');

		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 105]], $this->getTimes($eTask), 'le second temps s\'ajoute au premier');
		$this->assertEquals([\series\Task::DONE, 5.0, 105], $this->getTaskState($eTask));

	}

	/**
	 * La journée de la personne ne dépasse pas 24 heures, temps déjà posé ailleurs compris : la saisie est
	 * refusée avant toute écriture, comme dans Consigner.
	 */
	public function testDayTooLong(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTaskOther = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::DONE, \Clock::week());

		$this->createTimesheet($eTaskOther, 23.75);

		$reading = $this->read($this->getText('12 kg 30 min '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la lecture ne connaît pas la journée');

		try {
			EntryLib::write($this->eStructure, $reading);
			$this->assertFail('l\'écriture lève');
		} catch(\FailAction $action) {

			$fails = array_map(fn(array $failed) => $failed[1], new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action)->get());

			$this->assertEquals(['Timesheet::time.tooLong'], $fails, 'le refus est celui de la journée');

		}

		$this->assertTrue(\series\Task::model()->inTransaction(), 'le refus n\'a défait aucune transaction');
		$this->assertTrue(\series\Harvest::model()->whereCultivation($eCultivation)->exists() === FALSE, 'aucune récolte écrite');

	}

	/**
	 * Une structure qui ne suit pas le temps refuse la phrase dès la lecture.
	 */
	public function testUntracked(): void {

		$this->createCultivationInPhase(TRUE);

		$eConfiguration = $this->eStructure->conf();
		$time = $eConfiguration['time'];

		try {

			$eConfiguration['time'] = FALSE;

			$reading = $this->read($this->getText('12 kg 30 min '));

			$this->assertEquals(['reason' => 'untracked'], $reading['refusal']);
			$this->assertEquals(EntryLib::WRITE, $this->read($this->getText('12 kg '))['outcome'], 'sans durée, la récolte s\'écrit');

		} finally {
			$eConfiguration['time'] = $time;
		}

	}

	/**
	 * En cours de frappe, le dernier mot peut commencer plusieurs choses : le « h » d'une durée en heures
	 * commence aussi « hier ». Après un nombre, il dit la durée, et la récolte reste du jour.
	 */
	public function testHoursWhileTyping(): void {

		$this->createCultivationInPhase(TRUE);

		foreach(['12 kg 1 h' => 60, '12 kg 1h' => 60, '12 kg 3 h' => 180, '12 kg 1 h 30' => 90, '12 kg 30 mi' => 30] as $text => $minutes) {

			$reading = $this->read($this->getText($text));

			$this->assertEquals(EntryLib::WRITE, $reading['outcome'], $text);
			$this->assertEquals($minutes, $reading['confirmation']['minutes'], $text);
			$this->assertEquals(\Clock::date(), $reading['date'], $text);

		}

		$reading = $this->read($this->getText('1 h'));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'une durée seule s\'écrit seule, sans écrire hier');
		$this->assertEquals(\Clock::date(), $reading['date']);
		$this->assertEquals(60, $reading['complements']['duration']);

		$this->assertEquals(date('Y-m-d', \Clock::shift('-1 DAY')), $this->read($this->getText('12 kg hi'))['date'], 'un début de « hier » après la quantité reste une date');
		$this->assertTrue($this->read($this->getText('3 kg 12 m'))['outcome'] !== EntryLib::WRITE, 'un « m » seul peut commencer « mai » : rien ne s\'écrit avant la lettre suivante');

	}

	/**
	 * Le jeton d'une durée choisie dans la liste part en minutes dans le préfixe (`entry.js`), et la quantité
	 * tapée ensuite reste une quantité : « 60 min » suivi de « 5 » n'est pas une heure et cinq minutes.
	 */
	public function testDurationTokenThenQuantity(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('60 min 5'));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(60, $reading['confirmation']['minutes']);
		$this->assertEquals(5.0, (float)$reading['confirmation']['quantity']);

	}

	/**
	 * Sans quantité, la durée seule s'écrit sur la récolte (lot 2b), sans quantité à confirmer.
	 */
	public function testTimeAloneWrites(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('30 min '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(30, $reading['confirmation']['minutes']);
		$this->assertEquals(NULL, $reading['confirmation']['quantity']);
		$this->assertEquals(NULL, $reading['refusal']);

		$this->assertEquals(EntryLib::ASK, $this->read($this->getText())['outcome'], 'sans durée ni quantité, la récolte attend');

	}

	/**
	 * Annuler une récolte qui avait créé son intervention l'emporte avec son temps.
	 */
	public function testCancelCreated(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->write('12 kg 30 min');

		$eTask = $reading['confirmation']['eTask'];

		$cancellation = $this->cancel($reading);

		$this->assertTrue($cancellation['created'], 'le temps de la saisie n\'empêche pas l\'intervention de partir avec elle');
		$this->assertEquals(30, $cancellation['minutes']);
		$this->assertTrue(\series\Task::model()->whereId($eTask)->exists() === FALSE, 'l\'intervention est supprimée');
		$this->assertEquals([], $this->getTimes($eTask), 'son temps aussi');

	}

	/**
	 * Annuler une récolte faite sur une intervention à faire la remet à faire et retire le temps posé :
	 * la ligne qu'il avait ouverte disparaît, celle qui portait déjà du temps revient à son temps d'avant.
	 */
	public function testCancelTodo(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$reading = $this->write('12 kg 30 min');

		$cancellation = $this->cancel($reading);

		$this->assertFalse($cancellation['created']);
		$this->assertEquals([], $this->getTimes($eTask), 'la ligne de temps ouverte par la saisie disparaît');
		$this->assertEquals([\series\Task::TODO, NULL, NULL], $this->getTaskState($eTask), 'l\'intervention revient à faire, sans récolte ni temps');

		$this->createTimesheet($eTask, 1.0);

		$reading = $this->write('12 kg 30 min');

		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 90]], $this->getTimes($eTask));

		$this->cancel($reading);

		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 60]], $this->getTimes($eTask), 'le temps d\'avant la saisie reste');

	}

	/**
	 * Le temps posé par la saisie, retouché ailleurs dans la minute, ne fait plus partir l'intervention créée :
	 * elle garde ce qu'on y a ajouté, seuls la récolte et le temps de la saisie s'annulent.
	 */
	public function testCancelTouchedTime(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->write('12 kg 30 min');

		$eTask = $reading['confirmation']['eTask'];

		\series\TimesheetLib::writeByTasks(new \Collection([\series\TaskLib::getById($eTask['id'])]), new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $this->getMember(),
			'date' => \Clock::date(),
		]), 0.25, 'fair');

		$cancellation = $this->cancel($reading);

		$this->assertFalse($cancellation['created'], 'l\'intervention retouchée reste');
		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 15]], $this->getTimes($eTask), 'le temps ajouté ailleurs reste');

	}

	/**
	 * Une journée portée par deux lignes, comme l'API en crée (`TimesheetLib::create()`), dont celle de la
	 * saisie est passée sous son temps : Annuler retire le temps de la saisie sur la journée entière, sans
	 * qu'aucune ligne passe sous zéro ni que la transaction se défasse.
	 */
	public function testCancelSplitDay(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$reading = $this->write('12 kg 30 min');

		// Le décor veut une ligne sous le temps de la saisie, ce qu'aucun formulaire ne pose seul : écrite sans `build()`.
		\series\Timesheet::model()
			->whereTask($eTask)
			->update(['time' => 0.25]);

		$this->createTimesheet(\series\TaskLib::getById($eTask['id']), 1.0);

		$this->cancel($reading);

		$this->assertTrue(\series\Task::model()->inTransaction(), 'aucune transaction défaite');
		$this->assertTrue(\series\Harvest::model()->whereId($reading['confirmation']['eTask']['eHarvest']['id'])->exists() === FALSE, 'la récolte est annulée');
		$this->assertEquals([[$this->getMember()['id'], \Clock::date(), 45]], $this->getTimes($eTask), 'la journée garde 1 h 15 moins les 30 min de la saisie');

	}

}
?>
