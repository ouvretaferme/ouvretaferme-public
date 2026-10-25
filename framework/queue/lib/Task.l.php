<?php
namespace queue;

/**
 * ⚠️ **Every write below carries its status in the query, never in the code.** Two runners read the
 * same listing, and the write is what settles which of them holds the row: a reservation is bounded
 * by `PENDING`, a delete, a retry and a postponement by `RUNNING`. Without that bound a late runner
 * lays its result on a row another one has taken over, and the request is either served twice or
 * deleted unserved.
 */
class TaskLib extends TaskCrud {

	/**
	 * Pushes a task, or revives the one already holding the same (reference, action, element).
	 *
	 * A row being served is left alone: replacing it would change its id under the runner, whose
	 * delete and postpone would then miss, and the object would be served twice.
	 *
	 * ⚠️ `element` set to NULL deduplicates nothing: MySQL holds two NULLs as distinct in a unique
	 * index. A request without a target object is therefore always one more task.
	 *
	 * ⚠️ `createdAt` is refreshed on revival, and it is not cosmetic: it is the column the retention
	 * of `postpone()` and the purge of `cleanFailed()` read. Without the refresh, a queue long
	 * hammered on the same object would see fresh requests abandoned because of the age of a request
	 * served long ago.
	 */
	public static function upsert(Task $eTask): void {

		// Both inserts go through the model rather than `self::create()`: the second one needs the
		// `add-ignore` option, which `create()` does not carry, and that option is what absorbs the
		// collision between two simultaneous pushes on the same object.
		if($eTask['element'] === NULL) {

			Task::model()->insert($eTask);

			return;

		}

		$eTaskExisting = Task::model()
			->select(['id', 'status'])
			->whereReference($eTask['reference'])
			->whereAction($eTask['action'])
			->whereElement($eTask['element'])
			->get();

		if($eTaskExisting->empty()) {

			Task::model()->option('add-ignore')->insert($eTask);

			return;

		}

		Task::model()->whereStatus('IN', [Task::PENDING, Task::FAILED])->update($eTaskExisting, [
			'payload' => $eTask['payload'],
			'status' => Task::PENDING,
			'tries' => 0,
			'runAt' => $eTask['runAt'],
			'createdAt' => new \Sql('NOW()')
		]);

	}

	/**
	 * The tasks still held for these objects, indexed by element. A task served is deleted, so an
	 * object missing from the result has either been served or been purged after failing.
	 */
	public static function getByElements(int $reference, string $action, array $elements): \Collection {

		return Task::model()
			->select(Task::getSelection())
			->whereReference($reference)
			->whereAction($action)
			->whereElement('IN', $elements)
			->getCollection(index: 'element');

	}

	/**
	 * Ordered by due time, so that a task postponed five times never falls behind a request that has
	 * just arrived.
	 */
	public static function getPending(string $action, int $limit, ?array $references = NULL): \Collection {

		return Task::model()
			->select(Task::getSelection())
			->whereAction($action)
			->whereReference('IN', $references, if: $references !== NULL)
			->whereStatus(Task::PENDING)
			->whereRunAt('<=', new \Sql('NOW()'))
			->sort(['runAt' => SORT_ASC, 'id' => SORT_ASC])
			->limit($limit)
			->getCollection();

	}

	/**
	 * Takes the task for this pass, and returns the row read back — empty if another runner won it.
	 *
	 * ⚠️ **The row is read back after the reservation, and that is the one served**, never the one
	 * the caller held: the gap between the listing of a hundred tasks and the reservation of the
	 * hundredth is long, and `upsert()` writes there.
	 *
	 * `runAt` is brought back to now along with the status, which dates the reservation and lets
	 * `getExpired()` recognise one a vanished runner never returned, without one more column.
	 */
	public static function reserve(Task $eTask): Task {

		$reserved = Task::model()
			->whereStatus(Task::PENDING)
			->whereRunAt('<=', new \Sql('NOW()'))
			->update($eTask, [
				'status' => Task::RUNNING,
				'runAt' => new \Sql('NOW()')
			]) > 0;

		if($reserved === FALSE) {
			return new Task();
		}

		return Task::model()
			->select(Task::getSelection())
			->whereId($eTask)
			->get();

	}

	/** Returns FALSE if the task was no longer ours. */
	public static function complete(Task $eTask): bool {

		return Task::model()
			->whereStatus(Task::RUNNING)
			->delete($eTask) > 0;

	}

	/**
	 * ⚠️ The stack (`ModuleModel::$dbTransactionStack`) is a **per-server** counter, not one per
	 * database or per package: a consumer raising inside an open transaction leaves it at 1, and
	 * everything the runner writes next waits for a `COMMIT` that will never come. `rollBack()`
	 * resets the counter, where `rollBackEverything()` rolls back without resetting it, which would
	 * make the next transaction look like a nested one.
	 */
	public static function rollBackTransaction(): void {

		Task::model()->rollBack();

	}

	/**
	 * The tasks reserved for longer than a runner lives: whoever took them never came back.
	 *
	 * The runner is a `permanent` with no execution time limit, so a redeployment, a `kill` or a
	 * lost connection between the reservation and the delete leaves the row `RUNNING`. Without this,
	 * it would hold its triplet forever, `upsert()` leaving a running task alone.
	 *
	 * @param int $delay How long a reservation may last before it is held abandoned, in seconds.
	 */
	public static function getExpired(string $action, int $delay, int $limit, ?array $references = NULL): \Collection {

		return Task::model()
			->select(['id', 'tries'])
			->whereAction($action)
			->whereReference('IN', $references, if: $references !== NULL)
			->whereStatus(Task::RUNNING)
			->whereRunAt('<', new \Sql('NOW() - INTERVAL '.$delay.' SECOND'))
			->sort(['runAt' => SORT_ASC, 'id' => SORT_ASC])
			->limit($limit)
			->getCollection();

	}

	/**
	 * Postpones the task after a failure, or declares it lost on the last attempt.
	 *
	 * @param array $delays The successive delays, in minutes, one per attempt already spent.
	 * @return bool TRUE if the loss was actually written. The value comes from the write and not
	 * from the threshold alone, otherwise a late runner would announce as lost a request another
	 * runner has taken over and will serve.
	 */
	public static function retry(Task $eTask, int $tries, array $delays): bool {

		$try = $eTask['tries'] + 1;

		if($try >= $tries) {

			return Task::model()
				->whereStatus(Task::RUNNING)
				->update($eTask, ['status' => Task::FAILED, 'tries' => $try]) > 0;

		}

		Task::model()->whereStatus(Task::RUNNING)->update($eTask, [
			'status' => Task::PENDING,
			'tries' => $try,
			'runAt' => new \Sql('NOW() + INTERVAL '.$delays[$try - 1].' MINUTE')
		]);

		return FALSE;

	}

	public static function getFailedAt(Task $eTask, array $delays): string {

		$eTask->expects(['tries', 'runAt']);

		return date('Y-m-d H:i:s', strtotime($eTask['runAt'].' - '.$delays[$eTask['tries'] - 1].' minutes'));

	}

	/**
	 * Postpones the task without counting an attempt, or gives it up at the end of the retention.
	 *
	 * A consumer that cannot serve the request *yet* is not broken: counting attempts would lose it
	 * in five hours over a wait that may last days, so what bounds it is its age. The bound is read
	 * in the database on `createdAt`, the clock that writes the column being the one to judge it.
	 *
	 * @param int $days The retention, counted from the push of the request the row carries —
	 * `upsert()` refreshes `createdAt` when it revives one.
	 * @return bool TRUE if the task is given up.
	 */
	public static function postpone(Task $eTask, int $minutes, int $days): bool {

		$abandoned = Task::model()
			->whereStatus(Task::RUNNING)
			->whereCreatedAt('<', new \Sql('NOW() - INTERVAL '.$days.' DAY'))
			->update($eTask, ['status' => Task::FAILED]) > 0;

		if($abandoned) {
			return TRUE;
		}

		Task::model()->whereStatus(Task::RUNNING)->update($eTask, [
			'status' => Task::PENDING,
			'runAt' => new \Sql('NOW() + INTERVAL '.$minutes.' MINUTE')
		]);

		return FALSE;

	}

	public static function cleanFailed(int $days): int {

		$cTask = Task::model()
			->select(['id'])
			->whereStatus(Task::FAILED)
			->whereCreatedAt('<', new \Sql('NOW() - INTERVAL '.$days.' DAY'))
			->getCollection();

		foreach($cTask as $eTask) {
			self::delete($eTask);
		}

		return $cTask->count();

	}

	/**
	 * The families of tasks that have not moved for the given delay, counted by action.
	 *
	 * Grouped by action rather than over the whole queue, the diagnosis being "this crontab line is
	 * missing": a queue may run for five actions and be dead for the sixth. FAILED rows are out of
	 * scope, being lost rather than stuck.
	 *
	 * ⚠️ `runAt` carries both ages at once, which is what allows a single query: on a `PENDING` row
	 * it means "not before", on a `RUNNING` one it dates the reservation.
	 *
	 * @return \Collection One row per stuck action, indexed by action, carrying the count of each
	 * status and the date of the oldest one.
	 */
	public static function getStuck(int $delay): \Collection {

		return Task::model()
			->select([
				'action',
				'pending' => new \Sql('SUM(IF(status = "'.Task::PENDING.'", 1, 0))', 'int'),
				'running' => new \Sql('SUM(IF(status = "'.Task::RUNNING.'", 1, 0))', 'int'),
				'oldest' => new \Sql('MIN(runAt)')
			])
			->whereStatus('IN', [Task::PENDING, Task::RUNNING])
			->whereRunAt('<', new \Sql('NOW() - INTERVAL '.$delay.' SECOND'))
			->group('action')
			->sort(['action' => SORT_ASC])
			->getCollection(index: 'action');

	}

}
?>
