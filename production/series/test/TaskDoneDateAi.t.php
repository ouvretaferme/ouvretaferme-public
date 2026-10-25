<?php
namespace series;

/**
 * Le jour d'une intervention qui passe en fait (décision du 28/09) : `TaskLib::updateDoneCollection()`
 * prend le plus tard du jour planifié et de la fin du temps de travail, et sans l'un ni l'autre, le jour
 * même quand la semaine faite est la semaine courante. Une intervention cochée après coup reste notée à
 * la semaine : son jour ne se connaît pas, le carnet de traitement le fait préciser.
 *
 * Les autres chemins qui passent une intervention en fait n'ont pas cette règle, et pour une raison :
 * le formulaire fait choisir la semaine ou le jour, et propose le jour même sur la semaine courante
 * (`task.p.php`, `createFromScratch` et `createFromSeries`) ; la répétition pose toujours un jour
 * (`TaskLib::createFromRepeat()`) ; l'API ne fait que lire.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=series file=TaskDoneDateAi
 */
class TaskDoneDateAiTest extends \Test {

	public function setUp(): void {
		Task::model()->beginTransaction();
	}

	public function tearDown(): void {
		Task::model()->rollBack();
	}

	/**
	 * Une intervention à faire, sans jour planifié ni temps de travail, planifiée à la semaine donnée.
	 */
	private function getTodo(string $plannedWeek): Task {

		$eTask = Task::model()
			->select(Task::getSelection())
			->whereStatus(Task::TODO)
			->whereTimesheetStart(NULL)
			->whereTimesheetStop(NULL)
			->where('series IS NOT NULL')
			->sort(['id' => SORT_DESC])
			->get();

		Task::model()->update($eTask, ['plannedWeek' => $plannedWeek, 'plannedDate' => NULL]);

		return $eTask;

	}

	private function done(Task $eTask, ?string $newDone = NULL): Task {

		TaskLib::updateDoneCollection(new \Collection([$eTask['id'] => $eTask]), $newDone);

		return Task::model()
			->select(['status', 'doneWeek', 'doneDate'])
			->whereId($eTask['id'])
			->get();

	}

	public function testThisWeek(): void {

		$eTask = $this->done($this->getTodo(\Clock::week()));

		$this->assertEquals([Task::DONE, \Clock::week(), \Clock::date()], [$eTask['status'], $eTask['doneWeek'], $eTask['doneDate']], 'faite cette semaine, sans autre jour connu : aujourd\'hui');

	}

	public function testThisWeekWithoutPlannedWeek(): void {

		$eTask = $this->getTodo(\Clock::week());
		Task::model()->update($eTask, ['plannedWeek' => NULL]);

		$eTask = $this->done($eTask, \Clock::week());

		$this->assertEquals([\Clock::week(), \Clock::date()], [$eTask['doneWeek'], $eTask['doneDate']], 'la semaine choisie au cochage est la semaine courante');

	}

	public function testEarlierWeek(): void {

		$week = toWeek(\Clock::shift('-14 days'));

		$eTask = $this->done($this->getTodo($week));

		$this->assertEquals([$week, NULL], [$eTask['doneWeek'], $eTask['doneDate']], 'cochée après coup, elle reste à la semaine');

	}

	public function testPlannedDate(): void {

		$date = date('Y-m-d', \Clock::shift('-14 days'));

		$eTask = $this->getTodo(toWeek($date));
		Task::model()->update($eTask, ['plannedDate' => $date]);

		$eTask = $this->done($eTask);

		$this->assertEquals($date, $eTask['doneDate'], 'un jour planifié reste le jour fait');

	}

}
?>
