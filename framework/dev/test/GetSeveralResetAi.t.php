<?php
namespace dev;

/**
 * Une lecture sur une collection sans identifiant consomme sa requête comme les autres.
 *
 * `Xxx::model()` est un singleton : `->select(…)->get($c)` sur une collection vide, ou dont aucun élément
 * n'existe encore en base, n'a rien à lire, mais la sélection et les conditions posées ne doivent pas
 * survivre pour autant. Sinon elles s'ajoutent à la requête suivante du même module. Aux Récoltes, la
 * délégation `harvestDates` restée sur `Task::model()` se résolvait ainsi sur l'intervention jointe par
 * `CultivationLib::applyHarvestedInWeek()`, qui ne porte pas d'`id` (avertissement de production du
 * 15/10, lot planning PROD-harvest-id).
 *
 * Chaque cas part d'un modèle neuf, pour ne rien laisser au singleton des autres tests, et lit ce qu'il
 * reste de la requête par `getSql()`, qui rend le SQL de la prochaine lecture.
 *
 * Lancement : dev/test package=dev file=GetSeveralResetAi
 */
class GetSeveralResetAiTest extends \Test {

	private function assertConsumed(\series\TaskModel $mTask, string $case): void {

		$sql = (string)$mTask
			->select('id')
			->getSql();

		$this->assertFalse(str_contains($sql, 'description'), $case." : la sélection a survécu à la lecture : ".$sql);
		$this->assertFalse(str_contains($sql, 'WHERE'), $case." : la condition a survécu à la lecture : ".$sql);

	}

	public function testEmptyCollection(): void {

		$mTask = new \series\TaskModel();
		$cTask = new \Collection();

		$mTask
			->select(['description'])
			->whereStatus(\series\Task::DONE)
			->get($cTask);

		$this->assertConsumed($mTask, 'collection vide');

	}

	public function testCollectionWithoutId(): void {

		$mTask = new \series\TaskModel();
		$cTask = new \Collection([new \series\Task()]);

		$mTask
			->select(['description'])
			->whereStatus(\series\Task::DONE)
			->get($cTask);

		$this->assertConsumed($mTask, 'élément sans identifiant');

	}

	/**
	 * Le cas de production : une délégation laissée sur l'intervention se résout ensuite sur l'intervention
	 * jointe, dont la sélection ne porte pas `id`.
	 */
	public function testDelegationOnJoin(): void {

		$mTask = new \series\TaskModel();
		$cTask = new \Collection();

		$mTask
			->select([
				'harvestDates' => \series\Harvest::model()
					->select(['date'])
					->delegateCollection('task', 'date')
			])
			->get($cTask);

		$cHarvest = new \series\HarvestModel()
			->select(['date'])
			->join($mTask->select(['variety' => ['name']]), 'm2.id = m1.task')
			->getCollection(0, 1);

		if($this->assertDecorPresent($cHarvest->notEmpty(), 'au moins une récolte en base, à joindre à son intervention') === FALSE) {
			return;
		}

		$this->assertFalse($cHarvest->first()->offsetExists('harvestDates'), "la délégation restée sur l'intervention s'est résolue sur l'intervention jointe");

	}

}
?>
