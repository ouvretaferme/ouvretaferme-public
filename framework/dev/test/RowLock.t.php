<?php
namespace dev;

/**
 * `ModuleModel::getForUpdate()` : le refus hors transaction, et le verrou réellement pris par le
 * moteur.
 *
 * Le second cas est le seul qui tombe le jour où la clause disparaît du SQL rendu : une assertion sur
 * la chaîne resterait verte sur un verrou que MySQL ne prend pas. Il se joue donc sur une **seconde
 * connexion**, à laquelle on donne une seconde d'attente au lieu des cinquante du serveur.
 *
 * Lancement : dev/test package=dev file=RowLock
 */
class RowLockTest extends \Test {

	/** Un entier nu : `reference` ne porte aucune clé étrangère. */
	const REFERENCE = 999999998;

	const ACTION = 'dev.test.rowLock';

	/** Le code MySQL de « Lock wait timeout exceeded », que `Database::handleFailure()` ne nomme pas. */
	const LOCK_WAIT_TIMEOUT = 1205;

	public function setUp(): void {
		$this->clean();
	}

	public function finalize(): void {
		$this->clean();
	}

	/**
	 * La file vit dans la base commune, qu'aucune transaction ne couvre ici : le verrou du cas
	 * principal exige justement que la transaction reste ouverte pendant qu'une autre connexion lit.
	 */
	protected function clean(): void {

		foreach(
			\queue\Task::model()
				->select(['id'])
				->whereAction(self::ACTION)
				->getCollection()
			as $eTask
		) {
			\queue\TaskLib::delete($eTask);
		}

	}

	protected function push(): \queue\Task {

		\queue\TaskLib::upsert(new \queue\Task([
			'reference' => self::REFERENCE,
			'action' => self::ACTION,
			'element' => NULL,
			'payload' => [],
			'status' => \queue\Task::PENDING,
			'tries' => 0,
			'runAt' => \queue\Task::model()->getDefaultValue('runAt')
		]));

		return \queue\Task::model()
			->select(['id'])
			->whereAction(self::ACTION)
			->get();

	}

	public function testOutsideTransaction(): void {

		$eTask = $this->push();

		try {
			\queue\Task::model()->select(['tries'])->whereId($eTask)->getForUpdate();
			$this->assertFail('Un verrou hors transaction doit lever.');
		} catch(\Exception $e) {
			$this->assertTrue(str_contains($e->getMessage(), 'getForUpdate() called outside a transaction'), $e->getMessage());
		}

	}

	public function testMissingRow(): void {

		\queue\Task::model()->beginTransaction();

			$eTask = \queue\Task::model()->select(['tries'])->whereId(-1)->getForUpdate();

		\queue\Task::model()->rollBack();

		$this->assertTrue($eTask->empty());

	}

	public function testRowLocked(): void {

		$eTask = $this->push();

		\queue\Task::model()->beginTransaction();

			$eTaskLocked = \queue\Task::model()->select(['tries'])->whereId($eTask)->getForUpdate();
			$this->assertTrue($eTaskLocked->notEmpty());

			$this->assertEquals(self::LOCK_WAIT_TIMEOUT, $this->readConcurrently($eTask), "Le verrou n'a pas été pris : une autre connexion a relu la ligne.");

		\queue\Task::model()->rollBack();

		// Le verrou relâché, la même lecture passe : la mesure précédente vient bien du verrou et non
		// d'un défaut de la sonde.
		$this->assertEquals(NULL, $this->readConcurrently($eTask), "La ligne reste inaccessible une fois la transaction annulée.");

	}

	/**
	 * Rend le code d'erreur MySQL rencontré par une connexion neuve qui demande la même ligne, ou NULL
	 * si elle l'obtient.
	 */
	protected function readConcurrently(\queue\Task $eTask): ?int {

		$mTask = \queue\Task::model();

		$database = new \Database('queue');
		$database->exec('SET SESSION innodb_lock_wait_timeout = 1');
		$database->beginTransaction();

		try {

			$rs = $database->select('SELECT id FROM `'.$mTask->getDatabase().'`.`'.$mTask->getTable().'` WHERE id = '.(int)$eTask['id'].' FOR UPDATE');
			$rs->closeCursor();

			return NULL;

		// `Database::select()` ne rattrape pas PDO, contrairement à `exec()` : le code du moteur se lit
		// donc dans `errorInfo`, `getCode()` ne rendant que le SQLSTATE générique `HY000`.
		} catch(\PDOException $e) {

			return (int)($e->errorInfo[1] ?? 0);

		} finally {

			$database->rollBack();

		}

	}

}
