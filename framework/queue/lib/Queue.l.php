<?php
namespace queue;

/**
 * A generic queue: a request is pushed while the user waits, a cron serves it later.
 *
 * Each package declares its consumers by observing the queue, in `lib/Queue.o.php`:
 * `QueueObserverLib::getConsumers()` returns [action => closure(Task)].
 */
class QueueLib {

	use \Notifiable;

	const TRIES = 5;

	/** One delay per attempt already spent, in minutes, hence TRIES - 1 entries. */
	const DELAYS = [1, 5, 15, 60];

	/** In days. It also bounds how long a consumer may postpone a task. */
	const DAYS = 30;

	/** In minutes. */
	const POSTPONE = 60;

	/**
	 * How long a reserved task may stay so before the queue takes it back, in seconds.
	 *
	 * ⚠️ **No task may exceed this delay**, or it is handed back and served twice at once. Measured
	 * against the lifetime of a runner and not the crontab interval, a task picked up just before
	 * the end of the loop outliving it.
	 */
	const EXPIRE = 2 * \dev\DevSetting::CRON_PERMANENT_LIFETIME;

	/**
	 * How long a task may sit still before it means a breakdown, in seconds.
	 *
	 * ⚠️ Nothing ages a `PENDING` task: it is never purged, never lost and never reported, so an
	 * action that stops being served piles requests up with no visible error. Read once a night, so
	 * the threshold stays well above `EXPIRE` rather than tracking it.
	 */
	const STUCK = 24 * 3600;

	const BATCH = 100;

	/**
	 * @var \Closure[] Declarations from outside any package, such as a test's, each returning [action => closure(Task)].
	 */
	private static array $consumers = [];

	/** @var ?array The declarations, flattened into [action => closure(Task)], or NULL until they are. */
	private static ?array $actions = NULL;

	/** When set, push() keeps nothing, for a process whose requests must not be served. */
	private static bool $discard = FALSE;

	public static function setDiscard(bool $discard): void {
		self::$discard = $discard;
	}

	public static function autoload(\Closure $consumers): void {

		self::$consumers[] = $consumers;
		self::$actions = NULL;

	}

	/**
	 * ⚠️ **A name must fit within the indexed length.** The schema gives it (`text8(1, 50)`) but the
	 * column, a `varchar(255)`, does not enforce it: `upsert()` inserts through the model, without
	 * `build()`, and the `UNIQUE` only covers that prefix, so two actions matching up to it would
	 * share a single request with no error.
	 *
	 * Flattened on the first read: the declarations load the classes of every consuming package,
	 * which a request that never touches the queue has no reason to pay for.
	 */
	private static function getConsumers(): array {

		if(self::$actions !== NULL) {
			return self::$actions;
		}

		[, $length] = Task::model()->getPropertyRange('action');

		// Published only once complete: a refusal would otherwise leave a partial map behind, which
		// later reads would take for the whole.
		$actions = [];

		$declarations = self::notify('getConsumers');

		foreach(self::$consumers as $consumers) {
			$declarations[] = $consumers();
		}

		foreach($declarations as $declaration) {

			foreach($declaration as $action => $consumer) {

				if(array_key_exists($action, $actions)) {
					throw new \Exception('Action '.$action.' is declared by two queue consumers');
				}

				if(mb_strlen($action) > $length) {
					throw new \Exception('Action '.$action.' exceeds '.$length.' characters, the length on which the queue deduplicates requests');
				}

				$actions[$action] = $consumer;

			}

		}

		self::$actions = $actions;

		return self::$actions;

	}

	public static function getActions(): array {

		return array_keys(self::getConsumers());

	}

	public static function getConsumer(string $action): ?\Closure {

		return self::getConsumers()[$action] ?? NULL;

	}

	/**
	 * Pushes a request. This is all the user's gesture pays for.
	 *
	 * ⚠️ An action nobody serves is refused here: nothing ages a pending task, so the row would have
	 * no way out at all and would hold its triplet forever.
	 *
	 * @param int $reference The id of the structure the request belongs to. No element type:
	 * structure is unknown to framework/queue.
	 * @param ?int $element The target object, which makes the request unique with (reference, action).
	 * @param ?string $runAt The earliest time the task may be served; NULL for the next pass.
	 */
	public static function push(int $reference, string $action, ?int $element = NULL, array $payload = [], ?string $runAt = NULL): void {

		if(self::getConsumer($action) === NULL) {
			throw new \Exception('No consumer declared for action '.$action);
		}

		if(self::$discard) {
			return;
		}

		// Posted whole: reviving a request already queued writes these properties through an update,
		// which does not get the defaults of an insert.
		TaskLib::upsert(new Task([
			'reference' => $reference,
			'action' => $action,
			'element' => $element,
			'payload' => $payload,
			'status' => Task::PENDING,
			'tries' => 0,
			'runAt' => $runAt ?? Task::model()->getDefaultValue('runAt')
		]));

	}

	/**
	 * Serves one family of tasks, and returns how many were actually taken.
	 *
	 * ⚠️ **Must not be called from within an open transaction.** It rolls one back on the failure
	 * paths below, and the stack is a per-server counter: it would roll back its caller's
	 * transaction along with the consumer's, silently, the following `commit()` returning `TRUE` on
	 * an empty stack (`framework/core/Module.php`). A caller that needs both chains them.
	 *
	 * @param array|null $references Serves only the tasks of these references, NULL serving them all.
	 * An empty list is refused rather than read as « all », so that a filter computed empty never
	 * hands the whole queue to its caller.
	 */
	public static function run(string $action, ?array $references = NULL): int {

		if($references === []) {
			throw new \Exception('An empty list of references would serve the whole queue of '.$action);
		}

		$consumer = self::getConsumer($action);

		if($consumer === NULL) {
			throw new \Exception('No consumer declared for action '.$action);
		}

		self::release($action, $references);

		$treated = 0;

		foreach(TaskLib::getPending($action, self::BATCH, $references) as $eTaskPending) {

			$eTask = TaskLib::reserve($eTaskPending);

			if($eTask->empty()) {
				continue;
			}

			$treated++;

			try {

				$consumer($eTask);

			} catch(TaskPostponed $e) {

				// Both failure paths first roll back whatever the consumer left open: without it,
				// their own write would join that transaction, and the rest of the pass with it.
				TaskLib::rollBackTransaction();

				if(TaskLib::postpone($eTask, $e->minutes, self::DAYS)) {
					self::warn($action, $eTask, 'abandoned after '.self::DAYS.' days of postponement: '.$e->getMessage());
				}

				continue;

			} catch(\Throwable $e) {

				TaskLib::rollBackTransaction();

				if(TaskLib::retry($eTask, self::TRIES, self::DELAYS)) {
					self::warn($action, $eTask, 'lost after '.self::TRIES.' tries: '.$e->getMessage());
				}

				continue;

			}

			// A consumer returning inside an open transaction raises nothing, yet the delete below
			// and every write of the pass would join it and vanish with the process: its work is
			// rolled back and counted as a failed attempt. Reported on every attempt, unlike an
			// exception: it is always a defect of the consumer, never a transient breakdown.
			if(Task::model()->inTransaction()) {

				TaskLib::rollBackTransaction();

				if(TaskLib::retry($eTask, self::TRIES, self::DELAYS)) {
					self::warn($action, $eTask, 'lost after '.self::TRIES.' tries, the last one leaving a transaction open');
				} else {
					self::warn($action, $eTask, 'left a transaction open');
				}

				continue;

			}

			TaskLib::complete($eTask);

		}

		return $treated;

	}

	/**
	 * Hands back the tasks a vanished runner left reserved, before taking new ones.
	 *
	 * The attempt already started is counted, there being no telling what the runner did before it
	 * vanished: a task that kills its runner at the same point would otherwise loop until the purge.
	 */
	private static function release(string $action, ?array $references): void {

		foreach(TaskLib::getExpired($action, self::EXPIRE, self::BATCH, $references) as $eTask) {

			if(TaskLib::retry($eTask, self::TRIES, self::DELAYS)) {
				self::warn($action, $eTask, 'lost after '.self::TRIES.' tries, the last one abandoned by its runner');
			}

		}

	}

	private static function warn(string $action, Task $eTask, string $reason): void {

		trigger_error('Task '.$action.' #'.$eTask['id'].' '.$reason, E_USER_WARNING);

	}

	public static function clean(): int {

		return TaskLib::cleanFailed(self::DAYS);

	}

	/**
	 * The threshold lives here and not in the query: `TaskLib` says how the table is read,
	 * `QueueLib` says what the queue tolerates.
	 */
	public static function getStuck(): \Collection {

		return TaskLib::getStuck(self::STUCK);

	}

}

/**
 * Raised by a consumer that cannot serve the request yet: the task comes back after the given delay
 * without an attempt being counted.
 */
class TaskPostponed extends \Exception {

	/**
	 * @param int $minutes The delay before the task is served again. At least one minute: the
	 * runner loops every two seconds, and a delay of zero would serve the task on every turn.
	 */
	public function __construct(string $message = '', public readonly int $minutes = QueueLib::POSTPONE) {

		if($minutes < 1) {
			throw new \Exception('A task is postponed for at least one minute, '.$minutes.' given');
		}

		parent::__construct($message);

	}

}
?>
