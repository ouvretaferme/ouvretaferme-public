<?php
namespace queue;

/**
 * Pushing, reserving, retrying, postponing and purging, on eight fake actions the file declares
 * itself.
 *
 * Run: dev/test package=queue file=Queue
 */
class QueueTest extends \Test {

	/** A plain integer: `reference` carries no foreign key. */
	const REFERENCE = 999999999;

	const ACTION_COUNT = 'queue.test.count';
	const ACTION_DELAY = 'queue.test.delay';
	const ACTION_FAIL = 'queue.test.fail';
	const ACTION_ONCE = 'queue.test.once';
	const ACTION_OPEN = 'queue.test.open';
	const ACTION_POSTPONE = 'queue.test.postpone';
	const ACTION_REFUSE = 'queue.test.refuse';
	const ACTION_TRANSACTION = 'queue.test.transaction';

	const ACTIONS = [self::ACTION_COUNT, self::ACTION_DELAY, self::ACTION_FAIL, self::ACTION_ONCE, self::ACTION_OPEN, self::ACTION_POSTPONE, self::ACTION_REFUSE, self::ACTION_TRANSACTION];

	/** Out of ACTIONS on purpose. The cleanup sweeps it all the same: the day the guard in
	 * `push()` fell, the case using it would leave a row behind. */
	const ACTION_UNKNOWN = 'queue.test.inconnue';

	protected array $calls = [];

	protected array $order = [];

	protected bool $postponing = TRUE;

	public function init(): void {

		QueueLib::autoload(fn() => [
			// It returns FALSE on purpose: what a consumer returns is read nowhere.
			self::ACTION_COUNT => function(Task $eTask) {
				$this->trace(self::ACTION_COUNT);
				return FALSE;
			},
			self::ACTION_DELAY => function(Task $eTask) {
				$this->trace(self::ACTION_DELAY);
				throw new TaskPostponed('not possible for five minutes', 5);
			},
			self::ACTION_FAIL => function(Task $eTask) {
				$this->trace(self::ACTION_FAIL);
				throw new \Exception('deliberate failure');
			},
			self::ACTION_ONCE => function(Task $eTask) {
				$this->trace(self::ACTION_ONCE);
				if($this->calls[self::ACTION_ONCE] === 1) {
					throw new \Exception('failure on the first pass');
				}
			},
			// It returns without raising, leaving a transaction open: nothing tells the runner.
			self::ACTION_OPEN => function(Task $eTask) {
				$this->trace(self::ACTION_OPEN);
				Task::model()->beginTransaction();
			},
			self::ACTION_POSTPONE => function(Task $eTask) {
				$this->trace(self::ACTION_POSTPONE);
				if($this->postponing) {
					throw new TaskPostponed('not possible yet');
				}
			},
			// A bare return: to the queue, that is a success.
			self::ACTION_REFUSE => function(Task $eTask) {
				$this->trace(self::ACTION_REFUSE);
				return;
			},
			// It raises leaving a transaction open: unwinding it is the runner's job.
			self::ACTION_TRANSACTION => function(Task $eTask) {
				$this->trace(self::ACTION_TRANSACTION);
				Task::model()->beginTransaction();
				throw new \Exception('breakdown inside an open transaction');
			}
		]);

	}

	public function setUp(): void {

		$this->calls = array_fill_keys(self::ACTIONS, 0);
		$this->order = [];
		$this->postponing = TRUE;

		$this->clean();

	}

	public function finalize(): void {

		$this->clean();

	}

	protected function trace(string $action): void {

		$this->calls[$action]++;
		$this->order[] = $action;

	}

	/**
	 * Tasks live in the shared database, which no transaction covers here: every case empties
	 * the queue itself, otherwise the next one would read the previous one's rows.
	 */
	protected function clean(): void {

		$cTask = Task::model()
			->select(['id'])
			->whereAction('IN', [...self::ACTIONS, self::ACTION_UNKNOWN])
			->getCollection();

		foreach($cTask as $eTask) {
			TaskLib::delete($eTask);
		}

	}

	protected function getTask(string $action, int $element): Task {

		return Task::model()
			->select(['id', 'status', 'tries', 'runAt', 'payload', 'element'])
			->whereAction($action)
			->whereElement($element)
			->get();

	}

	/** Brings the due time back to now, as the real wait between two cron passes would. */
	protected function awake(Task $eTask): void {

		Task::model()->update($eTask, ['runAt' => new \Sql('NOW()')]);

	}

	/**
	 * `runAt` carries both the due time of a `PENDING` task and the reservation date of a
	 * `RUNNING` one, so the same gesture ages either of them.
	 */
	protected function stale(Task $eTask, string $status, int $hours): void {

		Task::model()->update($eTask, [
			'status' => $status,
			'runAt' => new \Sql('NOW() - INTERVAL '.$hours.' HOUR')
		]);

	}

	/**
	 * ⚠️ The page is reached through the closure the framework registered while including the
	 * file, and not through `Page::run()`: this cron is a `permanent`, whose process never comes
	 * back.
	 *
	 * NULL writes no parameter at all, which is the crontab line that forgot it.
	 */
	protected function cron(?string $actions = NULL): void {

		$path = \Package::getPath('queue').'/page/cron/run.p.php';

		\Page::includeFile($path);

		unset($_GET['action']);

		if($actions !== NULL) {
			$_GET['action'] = $actions;
		}

		try {

			foreach(\Page::all()[$path] as $page) {
				$page['create'](new \stdClass());
			}

		} finally {
			unset($_GET['action']);
		}

	}

	/**
	 * ⚠️ `Task::model()` is a singleton and a query survives on it until something consumes it:
	 * the stray condition is inherited by whichever read comes first, which fails on an unknown
	 * column. That is a breakdown of the queue itself, coming out of `release()`, `getPending()`
	 * or `reserve()` and never out of a consumer. It hits one read only, the failed query
	 * leaving the model clean behind it.
	 */
	protected function breakQueue(): void {

		Task::model()->where('m1.colonneInexistante IS NULL');

	}

	public function testRunTreatsAndClears() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 101);
		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 102);

		$this->assertEquals(2, QueueLib::run(self::ACTION_COUNT), 'les deux tâches sont prises');
		$this->assertEquals(2, $this->calls[self::ACTION_COUNT], 'le consommateur est appelé une fois par tâche');

		$this->assertTrue($this->getTask(self::ACTION_COUNT, 101)->empty(), 'la tâche traitée est effacée');
		$this->assertEquals(0, QueueLib::run(self::ACTION_COUNT), 'un second passage ne trouve plus rien');

	}

	public function testPushKeepsOneTask() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 103, ['tour' => 1]);

		$id = $this->getTask(self::ACTION_COUNT, 103)['id'];

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 103, ['tour' => 2]);

		$eTask = $this->getTask(self::ACTION_COUNT, 103);

		$this->assertEquals($id, $eTask['id'], 'la tâche est reprise sur place');
		$this->assertEquals(['tour' => 2], $eTask['payload'], 'la charge de la dernière demande');
		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT), 'une seule tâche déposée');

	}

	public function testPushLeavesRunningTask() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 109, ['tour' => 1]);

		$eTask = $this->getTask(self::ACTION_COUNT, 109);
		$id = $eTask['id'];

		TaskLib::reserve($eTask);

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 109, ['tour' => 2]);

		$eTaskRunning = $this->getTask(self::ACTION_COUNT, 109);

		$this->assertEquals($id, $eTaskRunning['id'], 'la tâche en cours garde son id');
		$this->assertEquals(Task::RUNNING, $eTaskRunning['status'], 'elle reste en cours');
		$this->assertEquals(['tour' => 1], $eTaskRunning['payload'], 'sa charge n\'est pas réécrite');

		// `complete()` and not the framework `delete()`: the case states the runner's gesture, and
		// would otherwise stay green the day its status bound disappeared.
		TaskLib::complete($eTaskRunning);

		$this->assertTrue($this->getTask(self::ACTION_COUNT, 109)->empty(), 'le runner efface bien sa tâche');
		$this->assertEquals(0, QueueLib::run(self::ACTION_COUNT), 'aucun second traitement');

	}

	public function testPushRevivesFailedTask() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 110);

		$eTask = $this->getTask(self::ACTION_COUNT, 110);

		Task::model()->update($eTask, ['status' => Task::FAILED, 'tries' => QueueLib::TRIES]);

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 110);

		$eTaskRevived = $this->getTask(self::ACTION_COUNT, 110);

		$this->assertEquals(Task::PENDING, $eTaskRevived['status'], 'la tâche perdue repart en attente');
		$this->assertEquals(0, $eTaskRevived['tries'], 'ses essais sont remis à zéro');
		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT), 'et elle est reprise');

	}

	/**
	 * ⚠️ MySQL holds two NULLs as distinct in a unique index: a request without a target object
	 * deduplicates nothing.
	 */
	public function testPushWithoutElement() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, NULL, ['tour' => 1]);
		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, NULL, ['tour' => 2]);

		$this->assertEquals(2, QueueLib::run(self::ACTION_COUNT), 'deux tâches déposées');
		$this->assertEquals(2, $this->calls[self::ACTION_COUNT], 'et deux traitements');

	}

	/**
	 * ⚠️ On an already reserved task MySQL counts no changed row, so the reservation would give
	 * back an empty element even without its `PENDING` bound; what the bound saves is the lost
	 * task, which would go round again.
	 */
	public function testReserveOnlyPending() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 104);

		$eTask = $this->getTask(self::ACTION_COUNT, 104);

		$this->assertTrue(TaskLib::reserve($eTask)->notEmpty(), 'le premier passage prend la tâche');
		$this->assertTrue(TaskLib::reserve($eTask)->empty(), 'le second ne la prend pas');

		Task::model()->update($eTask, ['status' => Task::FAILED]);

		$this->assertTrue(TaskLib::reserve($eTask)->empty(), 'une tâche perdue n\'est pas reprise');

	}

	public function testRetryUntilFailed() {

		QueueLib::push(self::REFERENCE, self::ACTION_FAIL, 105);

		$this->assertEquals(1, QueueLib::run(self::ACTION_FAIL), 'la tâche est prise');

		$eTask = $this->getTask(self::ACTION_FAIL, 105);

		$this->assertEquals(Task::PENDING, $eTask['status'], 'un échec ne perd pas la tâche');
		$this->assertEquals(1, $eTask['tries'], 'un essai consommé');
		$this->assertTrue($eTask['runAt'] > \Clock::datetime(), 'l\'échéance est repoussée');

		$this->assertEquals(0, QueueLib::run(self::ACTION_FAIL), 'une tâche reportée n\'est pas reprise avant son heure');

		for($try = 2; $try < QueueLib::TRIES; $try++) {

			$this->awake($this->getTask(self::ACTION_FAIL, 105));
			QueueLib::run(self::ACTION_FAIL);

			$this->assertEquals($try, $this->getTask(self::ACTION_FAIL, 105)['tries'], 'essai '.$try);

		}

		$this->awake($this->getTask(self::ACTION_FAIL, 105));

		$this->assertError(fn() => QueueLib::run(self::ACTION_FAIL), FALSE, 'Task '.self::ACTION_FAIL.' #'.$this->getTask(self::ACTION_FAIL, 105)['id'].' lost after '.QueueLib::TRIES.' tries: deliberate failure');

		$eTask = $this->getTask(self::ACTION_FAIL, 105);

		$this->assertEquals(Task::FAILED, $eTask['status'], 'la tâche est perdue au dernier essai');
		$this->assertEquals(QueueLib::TRIES, $eTask['tries'], 'tous les essais sont consommés');
		$this->assertEquals(QueueLib::TRIES, $this->calls[self::ACTION_FAIL], 'le consommateur a été appelé à chaque essai');

	}

	public function testRetrySucceeds() {

		QueueLib::push(self::REFERENCE, self::ACTION_ONCE, 106);

		QueueLib::run(self::ACTION_ONCE);

		$this->assertEquals(1, $this->getTask(self::ACTION_ONCE, 106)['tries'], 'le premier passage a échoué');

		$this->awake($this->getTask(self::ACTION_ONCE, 106));

		$this->assertEquals(1, QueueLib::run(self::ACTION_ONCE), 'la tâche est reprise');
		$this->assertEquals(2, $this->calls[self::ACTION_ONCE], 'le consommateur a été rappelé');
		$this->assertTrue($this->getTask(self::ACTION_ONCE, 106)->empty(), 'la tâche réussie est effacée');

	}

	/**
	 * ⚠️ The case writes into the global state of the process, which `QueueLib` exposes no way
	 * to undo: the declarations are read before and put back after, otherwise every later case
	 * would raise on the declaration this one adds.
	 */
	public function testActionDeclaredTwice() {

		$consumers = new \ReflectionProperty(QueueLib::class, 'consumers');
		$actions = new \ReflectionProperty(QueueLib::class, 'actions');

		$declarations = $consumers->getValue();

		try {

			QueueLib::autoload(fn() => [self::ACTION_COUNT => fn(Task $eTask) => NULL]);

			try {
				QueueLib::getConsumer(self::ACTION_COUNT);
				$this->assertFail('une action déclarée deux fois doit lever');
			} catch(\Exception $e) {
				$this->assertEquals('Action '.self::ACTION_COUNT.' is declared by two queue consumers', $e->getMessage());
			}

			$consumers->setValue(NULL, $declarations);
			$actions->setValue(NULL, NULL);

			[, $length] = Task::model()->getPropertyRange('action');

			$tooLong = str_pad('queue.test.', $length + 1, 'z');

			QueueLib::autoload(fn() => [$tooLong => fn(Task $eTask) => NULL]);

			try {
				QueueLib::getConsumer(self::ACTION_COUNT);
				$this->assertFail('une action plus longue que la clé qui déduplique doit lever');
			} catch(\Exception $e) {
				$this->assertEquals('Action '.$tooLong.' exceeds '.$length.' characters, the length on which the queue deduplicates requests', $e->getMessage());
			}

		} finally {

			$consumers->setValue(NULL, $declarations);
			$actions->setValue(NULL, NULL);

		}

		$this->assertTrue(QueueLib::getConsumer(self::ACTION_COUNT) !== NULL, 'les déclarations du fichier sont reposées');

	}

	/**
	 * ⚠️ The transaction stack is a per-server counter: left at 1, everything the runner writes
	 * next joins a transaction nobody will commit, and the whole pass vanishes when the process
	 * dies.
	 */
	public function testConsumerLeavingATransaction() {

		QueueLib::push(self::REFERENCE, self::ACTION_TRANSACTION, 138);

		QueueLib::run(self::ACTION_TRANSACTION);

		$this->assertFalse(Task::model()->inTransaction(), 'aucune transaction ne reste ouverte après le passage');

		// The runner's write was committed: a rollback does not take it back.
		Task::model()->rollBack();

		$eTask = $this->getTask(self::ACTION_TRANSACTION, 138);

		$this->assertEquals(Task::PENDING, $eTask['status'], 'la tâche est repartie en attente');
		$this->assertEquals(1, $eTask['tries'], 'avec son essai compté');

	}

	public function testConsumerLeavingTransactionOpen() {

		QueueLib::push(self::REFERENCE, self::ACTION_OPEN, 139);
		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 140);

		$this->assertError(fn() => QueueLib::run(self::ACTION_OPEN), FALSE, 'Task '.self::ACTION_OPEN.' #'.$this->getTask(self::ACTION_OPEN, 139)['id'].' left a transaction open');

		$this->assertFalse(Task::model()->inTransaction(), 'aucune transaction ne reste ouverte après le passage');

		QueueLib::run(self::ACTION_COUNT);

		// Only acts if the runner left the transaction open: everything since would then vanish.
		Task::model()->rollBack();

		$eTask = $this->getTask(self::ACTION_OPEN, 139);

		$this->assertEquals(Task::PENDING, $eTask['status'], 'la tâche n\'est pas effacée');
		$this->assertEquals(1, $eTask['tries'], 'et son essai est compté');
		$this->assertTrue($this->getTask(self::ACTION_COUNT, 140)->empty(), 'la tâche servie ensuite est effacée pour de bon');

	}

	public function testRunWithoutConsumer() {

		try {
			QueueLib::run(self::ACTION_UNKNOWN);
			$this->assertFail('une action sans consommateur doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('No consumer declared for action '.self::ACTION_UNKNOWN, $e->getMessage());
		}

		// The push refuses on its side too: nothing ages a pending task, so the row would have no
		// way out and would hold its triplet forever.
		try {
			QueueLib::push(self::REFERENCE, self::ACTION_UNKNOWN, 133);
			$this->assertFail('une action sans consommateur ne se dépose pas');
		} catch(\Exception $e) {
			$this->assertEquals('No consumer declared for action '.self::ACTION_UNKNOWN, $e->getMessage());
		}

		$this->assertTrue($this->getTask(self::ACTION_UNKNOWN, 133)->empty(), 'et rien n\'est resté en file');

	}

	public function testCleanFailedOnly() {

		QueueLib::push(self::REFERENCE, self::ACTION_FAIL, 107);
		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 108);

		$this->awake($this->getTask(self::ACTION_FAIL, 107));

		for($try = 1; $try < QueueLib::TRIES; $try++) {
			$this->awake($this->getTask(self::ACTION_FAIL, 107));
			QueueLib::run(self::ACTION_FAIL);
		}

		$this->awake($this->getTask(self::ACTION_FAIL, 107));

		// ⚠️ `assertError()` only asserts when given the expected message, and does nothing
		// otherwise: without this frame the warning would surface in the report.
		$this->assertError(fn() => QueueLib::run(self::ACTION_FAIL), FALSE, 'Task '.self::ACTION_FAIL.' #'.$this->getTask(self::ACTION_FAIL, 107)['id'].' lost after '.QueueLib::TRIES.' tries: deliberate failure');

		$eTask = $this->getTask(self::ACTION_FAIL, 107);

		$this->assertEquals(Task::FAILED, $eTask['status'], 'la tâche est perdue');

		$this->assertEquals(0, QueueLib::clean(), 'une tâche perdue du jour reste, pour qu\'on puisse la voir');

		Task::model()->update($eTask, ['createdAt' => new \Sql('NOW() - INTERVAL '.(QueueLib::DAYS + 1).' DAY')]);

		$this->assertEquals(1, QueueLib::clean(), 'une seule tâche purgée');
		$this->assertTrue($this->getTask(self::ACTION_FAIL, 107)->empty(), 'la tâche perdue est purgée');
		$this->assertFalse($this->getTask(self::ACTION_COUNT, 108)->empty(), 'la tâche en attente reste');

	}

	/**
	 * Without this hand-back the row is a black hole: `getPending()` does not take it, the purge
	 * only sees lost tasks, and `upsert()` silently swallows every later request on the same
	 * triplet.
	 */
	public function testReleaseExpiredTask() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 111);
		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 112);

		$eTaskLost = $this->getTask(self::ACTION_COUNT, 111);

		TaskLib::reserve($eTaskLost);
		TaskLib::reserve($this->getTask(self::ACTION_COUNT, 112));

		Task::model()->update($eTaskLost, ['runAt' => new \Sql('NOW() - INTERVAL '.(QueueLib::EXPIRE + 60).' SECOND')]);

		$this->assertEquals(0, QueueLib::run(self::ACTION_COUNT), 'le passage qui la rend ne la traite pas dans la foulée');

		$eTaskReleased = $this->getTask(self::ACTION_COUNT, 111);

		$this->assertEquals(Task::PENDING, $eTaskReleased['status'], 'la réservation abandonnée revient en attente');
		$this->assertEquals(1, $eTaskReleased['tries'], 'l\'essai commencé par le runner disparu est compté');

		$this->assertEquals(Task::RUNNING, $this->getTask(self::ACTION_COUNT, 112)['status'], 'la tâche réservée à l\'instant n\'est pas touchée');

		$this->awake($eTaskReleased);

		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT), 'et elle est reprise une fois son heure venue');
		$this->assertTrue($this->getTask(self::ACTION_COUNT, 111)->empty(), 'puis traitée et effacée');

	}

	public function testRunServesOnlyItsReferences() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 171);
		QueueLib::push(self::REFERENCE - 1, self::ACTION_COUNT, 172);

		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT, [self::REFERENCE]), 'seule la tâche de la référence demandée est prise');
		$this->assertTrue($this->getTask(self::ACTION_COUNT, 171)->empty(), 'elle est traitée et effacée');
		$this->assertEquals(Task::PENDING, $this->getTask(self::ACTION_COUNT, 172)['status'], 'la tâche d\'une autre référence reste en attente');

		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT), 'sans référence, le passage sert toute la file');

	}

	public function testReleaseOnlyItsReferences() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 173);
		QueueLib::push(self::REFERENCE - 1, self::ACTION_COUNT, 174);

		foreach([173, 174] as $element) {
			$eTask = $this->getTask(self::ACTION_COUNT, $element);
			TaskLib::reserve($eTask);
			Task::model()->update($eTask, ['runAt' => new \Sql('NOW() - INTERVAL '.(QueueLib::EXPIRE + 60).' SECOND')]);
		}

		QueueLib::run(self::ACTION_COUNT, [self::REFERENCE]);

		$this->assertEquals(Task::PENDING, $this->getTask(self::ACTION_COUNT, 173)['status'], 'la réservation abandonnée de la référence demandée revient en attente');
		$this->assertEquals(Task::RUNNING, $this->getTask(self::ACTION_COUNT, 174)['status'], 'celle d\'une autre référence n\'est pas relâchée');

	}

	public function testRunRefusesAnEmptyListOfReferences() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 175);

		try {
			QueueLib::run(self::ACTION_COUNT, []);
			$this->assertFail('une liste de références vide doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('An empty list of references would serve the whole queue of '.self::ACTION_COUNT, $e->getMessage());
		}

		$this->assertEquals(0, $this->calls[self::ACTION_COUNT], 'et rien n\'a été servi');

	}

	/**
	 * ⚠️ `getPending()` lists a hundred tasks at once and the runner reserves them one after the
	 * other: the hundredth is taken long after being read, and a gesture may have rewritten the
	 * request in between.
	 */
	public function testReserveReadsTheRowAgain() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 136, ['tour' => 1]);

		$eTaskListed = $this->getTask(self::ACTION_COUNT, 136);

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 136, ['tour' => 2]);

		$eTaskReserved = TaskLib::reserve($eTaskListed);

		$this->assertTrue($eTaskReserved->notEmpty(), 'la tâche est prise');
		$this->assertEquals(['tour' => 2], $eTaskReserved['payload'], 'et c\'est la dernière demande qui part au consommateur');

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 137);

		$eTaskPostponedListed = $this->getTask(self::ACTION_COUNT, 137);

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 137, [], date('Y-m-d H:i:s', strtotime('+1 hour')));

		$this->assertTrue(TaskLib::reserve($eTaskPostponedListed)->empty(), 'une demande repoussée après la liste n\'est pas prise');
		$this->assertEquals(Task::PENDING, $this->getTask(self::ACTION_COUNT, 137)['status'], 'elle attend son heure');

	}

	/**
	 * The window past `EXPIRE` is assumed: the same object may be served twice. What must not
	 * happen is the request vanishing, hence the `RUNNING` bound on the three writes of a
	 * runner.
	 */
	public function testWriteOnlyWhatWeHold() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 131);

		$eTaskSlow = $this->getTask(self::ACTION_COUNT, 131);

		TaskLib::reserve($eTaskSlow);

		Task::model()->update($eTaskSlow, ['runAt' => new \Sql('NOW() - INTERVAL '.(QueueLib::EXPIRE + 60).' SECOND')]);

		QueueLib::run(self::ACTION_COUNT);

		$eTaskReleased = $this->getTask(self::ACTION_COUNT, 131);

		$this->assertEquals(Task::PENDING, $eTaskReleased['status'], 'un second runner a rendu la tâche à la file');

		$this->assertFalse(TaskLib::complete($eTaskSlow), 'le retardataire n\'efface pas la tâche qu\'il ne détient plus');
		$this->assertFalse($this->getTask(self::ACTION_COUNT, 131)->empty(), 'et la demande est toujours là');

		// It does not declare it lost either, and that is what the caller reads to decide whether
		// to warn.
		$eTaskSlow['tries'] = QueueLib::TRIES - 1;

		$this->assertFalse(TaskLib::retry($eTaskSlow, QueueLib::TRIES, QueueLib::DELAYS), 'il ne déclare pas perdue une demande qu\'un autre a reprise');
		$this->assertFalse(TaskLib::postpone($eTaskSlow, QueueLib::POSTPONE, QueueLib::DAYS), 'ni abandonnée au bout de la rétention');

		$eTaskAfter = $this->getTask(self::ACTION_COUNT, 131);

		$this->assertEquals(Task::PENDING, $eTaskAfter['status'], 'ni son échec ni son report ne se reposent dessus');
		$this->assertEquals($eTaskReleased['tries'], $eTaskAfter['tries'], 'et son essai n\'est pas compté deux fois');

		$this->awake($eTaskAfter);

		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT), 'la demande finit servie');

	}

	public function testPostponeKeepsTries() {

		QueueLib::push(self::REFERENCE, self::ACTION_POSTPONE, 113);

		$this->assertEquals(1, QueueLib::run(self::ACTION_POSTPONE), 'la tâche est prise');

		$eTask = $this->getTask(self::ACTION_POSTPONE, 113);

		$this->assertEquals(Task::PENDING, $eTask['status'], 'un report ne perd pas la tâche');
		$this->assertEquals(0, $eTask['tries'], 'et ne lui compte aucun essai');
		$this->assertTrue(Task::model()
			->whereId($eTask['id'])
			->where('runAt BETWEEN NOW() + INTERVAL '.(QueueLib::POSTPONE - 1).' MINUTE AND NOW() + INTERVAL '.(QueueLib::POSTPONE + 1).' MINUTE')
			->exists(), 'l\'échéance est repoussée du délai par défaut');

		for($pass = 0; $pass < QueueLib::TRIES; $pass++) {
			$this->awake($this->getTask(self::ACTION_POSTPONE, 113));
			QueueLib::run(self::ACTION_POSTPONE);
		}

		$eTaskPostponed = $this->getTask(self::ACTION_POSTPONE, 113);

		$this->assertEquals(0, $eTaskPostponed['tries'], 'toujours aucun essai consommé');
		$this->assertEquals(Task::PENDING, $eTaskPostponed['status'], 'et toujours en attente');
		$this->assertEquals(QueueLib::TRIES + 1, $this->calls[self::ACTION_POSTPONE], 'le consommateur a été rappelé à chaque passage');

		$this->postponing = FALSE;

		$this->awake($eTaskPostponed);

		$this->assertEquals(1, QueueLib::run(self::ACTION_POSTPONE), 'la tâche part dès que le consommateur peut la servir');
		$this->assertTrue($this->getTask(self::ACTION_POSTPONE, 113)->empty(), 'et elle est effacée');

	}

	public function testPostponeWithDelay() {

		QueueLib::push(self::REFERENCE, self::ACTION_DELAY, 141);

		QueueLib::run(self::ACTION_DELAY);

		$eTask = Task::model()
			->select([
				'status', 'tries',
				'inDelay' => new \Sql('runAt BETWEEN NOW() + INTERVAL 4 MINUTE AND NOW() + INTERVAL 6 MINUTE', 'bool')
			])
			->whereAction(self::ACTION_DELAY)
			->whereElement(141)
			->get();

		$this->assertEquals(Task::PENDING, $eTask['status'], 'la tâche reportée reste en attente');
		$this->assertEquals(0, $eTask['tries'], 'sans essai compté');
		$this->assertTrue($eTask['inDelay'], 'et revient après le délai que le consommateur a choisi');

	}

	public function testPostponeRefusesZero() {

		try {
			new TaskPostponed('not possible yet', 0);
			$this->assertFail('un report de zéro minute doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('A task is postponed for at least one minute, 0 given', $e->getMessage());
		}

	}

	public function testPostponeUntilRetention() {

		QueueLib::push(self::REFERENCE, self::ACTION_POSTPONE, 114);

		QueueLib::run(self::ACTION_POSTPONE);

		$eTask = $this->getTask(self::ACTION_POSTPONE, 114);

		Task::model()->update($eTask, ['createdAt' => new \Sql('NOW() - INTERVAL '.(QueueLib::DAYS + 1).' DAY')]);

		$this->awake($eTask);

		$this->assertError(fn() => QueueLib::run(self::ACTION_POSTPONE), FALSE, 'Task '.self::ACTION_POSTPONE.' #'.$eTask['id'].' abandoned after '.QueueLib::DAYS.' days of postponement: not possible yet');

		$eTaskAbandoned = $this->getTask(self::ACTION_POSTPONE, 114);

		$this->assertEquals(Task::FAILED, $eTaskAbandoned['status'], 'la tâche abandonnée prend le statut que la purge voit');
		$this->assertEquals(0, $eTaskAbandoned['tries'], 'sans qu\'aucun essai ait été consommé');
		$this->assertEquals(1, QueueLib::clean(), 'et la purge l\'emporte, la ligne étant déjà vieille');

	}

	/**
	 * The retention of `postpone()` and the purge of `cleanFailed()` both read `createdAt`: on a
	 * revived row it must date the last request, not the first.
	 */
	public function testUpsertRenewsTheDemand() {

		QueueLib::push(self::REFERENCE, self::ACTION_POSTPONE, 115);

		QueueLib::run(self::ACTION_POSTPONE);

		$eTaskOld = $this->getTask(self::ACTION_POSTPONE, 115);

		Task::model()->update($eTaskOld, ['createdAt' => new \Sql('NOW() - INTERVAL '.(QueueLib::DAYS + 1).' DAY')]);

		QueueLib::push(self::REFERENCE, self::ACTION_POSTPONE, 115);

		$eTaskRenewed = $this->getTask(self::ACTION_POSTPONE, 115);

		$this->assertEquals($eTaskOld['id'], $eTaskRenewed['id'], 'la demande a bien été reprise sur place');

		$fresh = Task::model()
			->whereId($eTaskRenewed)
			->whereCreatedAt('>', new \Sql('NOW() - INTERVAL 1 MINUTE'))
			->count();

		$this->assertEquals(1, $fresh, 'la reprise a rafraîchi l\'âge de la demande');

		QueueLib::run(self::ACTION_POSTPONE);

		$eTaskPostponed = $this->getTask(self::ACTION_POSTPONE, 115);

		$this->assertEquals(Task::PENDING, $eTaskPostponed['status'], 'et elle est reportée, non abandonnée sur l\'âge de la précédente');

	}

	/**
	 * Meaning travels by an exception, never by a value: a `return FALSE`, an early exit or an
	 * error swallowed inside are three successes in the eyes of the queue.
	 */
	public function testConsumerReturnIsNotRead() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 115);
		QueueLib::push(self::REFERENCE, self::ACTION_REFUSE, 116);

		$this->assertEquals(1, QueueLib::run(self::ACTION_COUNT), 'la tâche du consommateur qui rend FALSE est prise');
		$this->assertEquals(1, QueueLib::run(self::ACTION_REFUSE), 'celle du consommateur qui sort tôt aussi');

		$this->assertTrue($this->getTask(self::ACTION_COUNT, 115)->empty(), 'un FALSE rendu n\'empêche pas l\'effacement');
		$this->assertTrue($this->getTask(self::ACTION_REFUSE, 116)->empty(), 'une sortie anticipée non plus');

	}

	/**
	 * The check runs once a night through `dev\IntegrityLib::calculate()`, and is the only
	 * signal of an abandoned action: nothing ages a pending task and no error surfaces.
	 *
	 * Both statuses say the same breakdown by two routes: a `RUNNING` row a day old would have
	 * been handed back by `release()` after `EXPIRE` if a runner were passing.
	 */
	public function testStuckNamesTheAction() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 140);
		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 141);
		QueueLib::push(self::REFERENCE, self::ACTION_FAIL, 142);

		$this->stale($this->getTask(self::ACTION_COUNT, 140), Task::PENDING, 25);
		$this->stale($this->getTask(self::ACTION_COUNT, 141), Task::PENDING, 30);
		$this->stale($this->getTask(self::ACTION_FAIL, 142), Task::RUNNING, 25);

		$cTaskStuck = QueueLib::getStuck();

		$this->assertTrue($cTaskStuck->offsetExists(self::ACTION_COUNT), 'l\'action en attente est nommée');
		$this->assertTrue($cTaskStuck->offsetExists(self::ACTION_FAIL), 'celle dont la tâche est restée réservée aussi');

		$this->assertEquals(2, $cTaskStuck[self::ACTION_COUNT]['pending'], 'les deux tâches en attente sont comptées');
		$this->assertEquals(0, $cTaskStuck[self::ACTION_COUNT]['running'], 'et aucune n\'est comptée réservée');
		$this->assertEquals(1, $cTaskStuck[self::ACTION_FAIL]['running'], 'la tâche réservée est comptée sur son action');

		// The older of the two, and not the last one read: that is what dates the breakdown.
		$this->assertTrue(
			strtotime($cTaskStuck[self::ACTION_COUNT]['oldest']) < strtotime('-29 hour'),
			'la date rendue est celle de la plus ancienne'
		);

		$integrity = DevObserverLib::getStuck();

		$this->assertTrue($integrity !== NULL, 'le contrôle d\'intégrité parle');
		$this->assertTrue(str_contains($integrity[0], (string)(QueueLib::STUCK / 3600)), 'et son message porte le seuil');
		$this->assertTrue(array_key_exists(self::ACTION_COUNT, $integrity[1]), 'ce qu\'il remonte nomme l\'action à réparer');

	}

	public function testStuckHoldsItsThreshold() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 143);

		$this->stale($this->getTask(self::ACTION_COUNT, 143), Task::PENDING, 23);

		$this->assertFalse(QueueLib::getStuck()->offsetExists(self::ACTION_COUNT), 'une tâche de 23 h attend encore son heure');

		// ⚠️ The equivalence, and not a bare NULL: `queueTask` lives in the shared database, and a
		// stuck task from another project would make that NULL false without the check being wrong.
		$this->assertEquals(
			QueueLib::getStuck()->empty(),
			DevObserverLib::getStuck() === NULL,
			'l\'observateur ne parle que s\'il a trouvé quelque chose'
		);

		$this->stale($this->getTask(self::ACTION_COUNT, 143), Task::PENDING, 25);

		$this->assertTrue(QueueLib::getStuck()->offsetExists(self::ACTION_COUNT), 'la même, deux heures plus tard, est bloquée');

	}

	/**
	 * `QueueLib::warn()` already reported it once, when it was lost. Counting it again would
	 * repeat the same fact every night for the `DAYS` days of the retention.
	 */
	public function testStuckIgnoresLostTasks() {

		QueueLib::push(self::REFERENCE, self::ACTION_FAIL, 144);

		$this->stale($this->getTask(self::ACTION_FAIL, 144), Task::FAILED, 48);

		$this->assertFalse(QueueLib::getStuck()->offsetExists(self::ACTION_FAIL), 'une tâche perdue n\'est pas bloquée');

	}

	/**
	 * ⚠️ The subscription lives in `package.c.php`, generated from the `*.o.php` files: renaming
	 * the file or the method would unsubscribe the package silently, without a single behaviour
	 * test moving.
	 */
	public function testStuckIsObserved() {

		$observers = \Package::getObservers();

		$this->assertTrue(
			in_array('queue', $observers['lib']['dev']['integrity'] ?? [], TRUE),
			'le package est abonné au contrôle d\'intégrité de la nuit'
		);

	}

	/**
	 * ⚠️ `BATCH` bounds the volume of a pass, not its duration: the families of a line get their
	 * turn in turn, but a family whose tasks are slow makes all the others wait at every round.
	 * Grouping is for fast families only.
	 */
	public function testCronServesEveryActionOfTheList() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 150);
		QueueLib::push(self::REFERENCE, self::ACTION_REFUSE, 151);

		$this->cron(self::ACTION_COUNT.','.self::ACTION_REFUSE);

		$this->assertEquals(1, $this->calls[self::ACTION_COUNT], 'la première action de la liste est servie');
		$this->assertEquals(1, $this->calls[self::ACTION_REFUSE], 'la seconde aussi');
		$this->assertEquals([self::ACTION_COUNT, self::ACTION_REFUSE], $this->order, 'dans l\'ordre où la liste les écrit');

	}

	public function testCronKeepsTheOrderOfTheList() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 152);
		QueueLib::push(self::REFERENCE, self::ACTION_REFUSE, 153);

		$this->cron(self::ACTION_REFUSE.','.self::ACTION_COUNT);

		$this->assertEquals([self::ACTION_REFUSE, self::ACTION_COUNT], $this->order, 'la liste écrite à l\'envers sert à l\'envers');

	}

	public function testCronRefusesTheWholeListOnAnUnknownName() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 154);

		try {
			$this->cron(self::ACTION_COUNT.','.self::ACTION_UNKNOWN);
			$this->assertFail('un nom inconnu dans la liste doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('queue/cron/run: no consumer declared for action '.self::ACTION_UNKNOWN, $e->getMessage(), 'le message nomme l\'action fautive');
		}

		$this->assertEquals(0, $this->calls[self::ACTION_COUNT], 'l\'action valide qui précède n\'est pas servie');
		$this->assertTrue($this->getTask(self::ACTION_COUNT, 154)->notEmpty(), 'et sa tâche est toujours en file');

	}

	public function testCronRefusesAMalformedList() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 157);

		try {
			$this->cron(self::ACTION_COUNT.','.self::ACTION_COUNT);
			$this->assertFail('un doublon doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('queue/cron/run: the action list carries a duplicate', $e->getMessage());
		}

		try {
			$this->cron(self::ACTION_COUNT.',');
			$this->assertFail('un élément vide doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('queue/cron/run: the action list carries an empty item', $e->getMessage());
		}

		$this->assertEquals(0, $this->calls[self::ACTION_COUNT], 'et ni l\'un ni l\'autre n\'a servi quoi que ce soit');

	}

	/**
	 * ⚠️ An absent parameter reads back as an empty string: without its own refusal, a crontab
	 * line with no `action=` at all would be reported as a list carrying an empty item.
	 */
	public function testCronRefusesAMissingParameter() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 158);

		try {
			$this->cron();
			$this->assertFail('un paramètre absent doit lever');
		} catch(\Exception $e) {
			$this->assertEquals('queue/cron/run: action= parameter is required', $e->getMessage());
		}

		$this->assertEquals(0, $this->calls[self::ACTION_COUNT], 'et rien n\'a été servi');

	}

	public function testCronToleratesSpaces() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 155);
		QueueLib::push(self::REFERENCE, self::ACTION_REFUSE, 156);

		$this->cron(' '.self::ACTION_COUNT.' , '.self::ACTION_REFUSE.' ');

		$this->assertEquals(1, $this->calls[self::ACTION_COUNT], 'la première action est servie malgré les espaces');
		$this->assertEquals(1, $this->calls[self::ACTION_REFUSE], 'la seconde aussi');

	}

	/**
	 * What is isolated is what breaks the **queue**, never what breaks a task: a consumer that
	 * raises is already turned into a retry or a postponement inside `run()`, and its family
	 * stays in the round.
	 */
	public function testCronIsolatesABrokenQueue() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 160);
		QueueLib::push(self::REFERENCE, self::ACTION_REFUSE, 161);

		$this->breakQueue();

		try {
			$this->cron(self::ACTION_COUNT.','.self::ACTION_REFUSE);
			$this->assertFail('une file en panne doit lever une fois le tour fini');
		} catch(\Exception $e) {
			$this->assertTrue(str_contains($e->getMessage(), self::ACTION_COUNT), 'le message nomme la file en panne : '.$e->getMessage());
			$this->assertFalse(str_contains($e->getMessage(), self::ACTION_REFUSE), 'et pas celle qui a été servie');
			$this->assertTrue(str_contains($e->getPrevious()?->getMessage() ?? '', 'colonneInexistante'), 'et la panne d\'origine est chaînée, sa trace disant où la file a réellement cassé');
		}

		$this->assertEquals(0, $this->calls[self::ACTION_COUNT], 'la file en panne n\'a rien servi');
		$this->assertEquals(1, $this->calls[self::ACTION_REFUSE], 'la suivante a servi ses tâches malgré la panne de la première');
		$this->assertTrue($this->getTask(self::ACTION_REFUSE, 161)->empty(), 'et sa tâche est effacée');
		$this->assertTrue($this->getTask(self::ACTION_COUNT, 160)->notEmpty(), 'la demande de la file en panne est toujours là');

	}

	public function testCronIsolatesWhicheverFamilyComesFirst() {

		QueueLib::push(self::REFERENCE, self::ACTION_COUNT, 162);
		QueueLib::push(self::REFERENCE, self::ACTION_REFUSE, 163);

		$this->breakQueue();

		try {
			$this->cron(self::ACTION_REFUSE.','.self::ACTION_COUNT);
			$this->assertFail('une file en panne doit lever une fois le tour fini');
		} catch(\Exception $e) {
			$this->assertTrue(str_contains($e->getMessage(), self::ACTION_REFUSE), 'le message nomme la file en panne : '.$e->getMessage());
			$this->assertFalse(str_contains($e->getMessage(), self::ACTION_COUNT), 'et pas celle qui a été servie');
		}

		$this->assertEquals(0, $this->calls[self::ACTION_REFUSE], 'la file en panne n\'a rien servi');
		$this->assertEquals(1, $this->calls[self::ACTION_COUNT], 'la suivante a servi ses tâches');
		$this->assertTrue($this->getTask(self::ACTION_COUNT, 162)->empty(), 'et sa tâche est effacée');

	}

}
?>
