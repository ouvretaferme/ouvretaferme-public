<?php
namespace series;

/**
 * Chaque ligne s'écrit dans la transaction du geste qui l'a causée : le `rollBack()` d'un refus
 * (`time.negative`) l'emporte avec lui.
 */
class HistoryLib extends HistoryCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return [];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return [];
	}

	/**
	 * Du plus récent au plus ancien. `id` et non `date` : deux gestes de la même seconde gardent leur ordre, et
	 * le tri se fait sur l'index `(task)`, qui porte la clé primaire.
	 */
	public static function getByTask(Task $eTask): \Collection {

		return History::model()
			->select([
				'id', 'event', 'day', 'time', 'harvest', 'harvestUnit', 'date',
				'worker' => \user\User::getSelection(),
				'user' => \user\User::getSelection()
			])
			->whereTask($eTask)
			->sort(['id' => SORT_DESC])
			->getCollection();

	}

	public static function createForTask(Task $eTask, string $event): void {

		$eTask->expects(['id', 'structure']);

		self::create(new History([
			'structure' => $eTask['structure'],
			'task' => $eTask,
			'event' => $event
		]));

	}

	/**
	 * `$time` est l'écart que le geste a écrit sur la ligne de temps, pas le temps de la ligne.
	 */
	public static function createForTimesheet(Timesheet $eTimesheet, float $time): void {

		$eTimesheet->expects(['structure', 'task', 'user', 'date']);

		if($time === 0.0) {
			return;
		}

		self::create(new History([
			'structure' => $eTimesheet['structure'],
			'task' => $eTimesheet['task'],
			'event' => History::TIME,
			'worker' => $eTimesheet['user'],
			'day' => $eTimesheet['date'],
			'time' => $time
		]));

	}

	public static function createForHarvest(Harvest $eHarvest, float $quantity): void {

		$eHarvest->expects(['structure', 'task', 'unit', 'date']);

		if($quantity === 0.0) {
			return;
		}

		self::create(new History([
			'structure' => $eHarvest['structure'],
			'task' => $eHarvest['task'],
			'event' => History::HARVEST,
			'day' => $eHarvest['date'],
			'harvest' => $quantity,
			'harvestUnit' => $eHarvest['unit']
		]));

	}

	/**
	 * L'historique part avec ses interventions. Écriture de masse, comme celles des temps et des récoltes à
	 * côté dans `TaskLib::delete()` et `deleteCollection()` : aucune ligne n'a de traitement propre à sa
	 * suppression.
	 */
	public static function deleteByTasks(\Collection $cTask): void {

		History::model()
			->whereTask('IN', $cTask)
			->delete();

	}

	/**
	 * L'historique des interventions faites par une personne sans compte passe au compte qui la remplace (`structure\MemberLib::transferGhost()`). Sans index sur `worker`, un balayage de la table que justifie un geste rare.
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Écriture de masse : toutes les lignes de la personne changent de titulaire.
		History::model()
			->whereStructure($eStructure)
			->whereWorker($eUserFrom)
			->update([
				'worker' => $eUserTo
			]);

	}

}
?>
