<?php
namespace dev;

/**
 * Un interblocage défait la transaction entière côté MySQL. Le code qui l'avale dans un `catch` et
 * continue ne doit rien pouvoir écrire de plus : sans le refus de `Database`, l'écriture suivante
 * partait en autocommit, et le geste était validé à moitié.
 *
 * L'interblocage est réel et non simulé par un déclencheur `SIGNAL 1213`, qui lève le même code sans
 * que MySQL défasse quoi que ce soit : seul un vrai fait apparaître l'autocommit. Il demande une
 * seconde connexion qui attend un verrou pendant que la première continue, donc une requête
 * asynchrone, que PDO ne sait pas faire : d'où `mysqli`. Elle modifie plus de lignes que la
 * connexion du cadriciel, pour que MySQL choisisse celle-ci comme victime.
 *
 * Lancement : dev/test package=dev file=TransactionAborted
 */
class TransactionAbortedTest extends \Test {

	/** Des entiers nus : `reference` ne porte aucune clé étrangère. */
	const REFERENCES = [999999993, 999999994, 999999995];

	const ACTION = 'dev.test.transactionAborted';

	public function setUp(): void {
		$this->clean();
	}

	public function finalize(): void {
		$this->clean();
	}

	/**
	 * La file vit dans la base commune, qu'aucune transaction ne couvre ici : les verrous du cas
	 * principal exigent des lignes validées, visibles de la seconde connexion.
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

	/**
	 * @return int[] Les ids des tâches, dans l'ordre de `REFERENCES`.
	 */
	protected function push(): array {

		foreach(self::REFERENCES as $reference) {

			\queue\TaskLib::upsert(new \queue\Task([
				'reference' => $reference,
				'action' => self::ACTION,
				'element' => NULL,
				'payload' => [],
				'status' => \queue\Task::PENDING,
				'tries' => 0,
				'runAt' => \queue\Task::model()->getDefaultValue('runAt')
			]));

		}

		return \queue\Task::model()
			->whereAction(self::ACTION)
			->sort(['reference' => SORT_ASC])
			->getColumn('id');

	}

	public function testSwallowedDeadlockRefusesWhatFollows(): void {

		[$first, $second, $third] = $this->push();

		\queue\Task::model()->beginTransaction();

			$this->writeTries($first, 1);

			$caught = $this->deadlock($first, $second, $third, fn() => $this->writeTries($second, 1));
			$this->assertTrue($caught instanceof \DeadLockException, $caught === NULL ? 'aucun interblocage' : get_class($caught));

			// Le geste continue comme si un catch avait avalé l'interblocage.
			$this->assertTrue($this->tryWriteTries($third, 9) instanceof \TransactionAbortedException, 'l\'écriture qui suit l\'interblocage passe');

			$caught = NULL;

			try {
				\queue\Task::model()->commit();
			} catch(\Throwable $e) {
				$caught = $e;
			}

			$this->assertTrue($caught instanceof \TransactionAbortedException, $caught === NULL ? 'commit() rend la main sur une transaction défaite' : get_class($caught));

		$this->assertFalse(\queue\Task::model()->inTransaction(), 'la pile reste ouverte');
		$this->assertEquals([0, 0, 0], $this->readTries(), 'une écriture du geste a été validée');

	}

	/** `rollBack()` ôte le refus : c'est le geste attendu de qui rattrape un interblocage pour rejouer. */
	public function testRollBackAllowsARetry(): void {

		[$first, $second, $third] = $this->push();

		\queue\Task::model()->beginTransaction();

			$this->writeTries($first, 1);
			$this->assertTrue($this->deadlock($first, $second, $third, fn() => $this->writeTries($second, 1)) instanceof \DeadLockException, 'aucun interblocage');

		\queue\Task::model()->rollBack();

		\queue\Task::model()->beginTransaction();

			$this->writeTries($first, 1);
			$this->writeTries($third, 1);

		\queue\Task::model()->commit();

		$this->assertEquals([1, 0, 1], $this->readTries());

	}

	/** Une lecture verrouillante interbloquée reçoit l'exception de PDO, et le refus suit de même. */
	public function testSwallowedLockingReadRefusesWhatFollows(): void {

		[$first, $second, $third] = $this->push();

		\queue\Task::model()->beginTransaction();

			$this->writeTries($first, 1);

			$caught = $this->deadlock($first, $second, $third, fn() => \queue\Task::model()->select(['tries'])->whereId($second)->getForUpdate());
			$this->assertTrue($caught instanceof \PDOException and ($caught->errorInfo[1] ?? NULL) === 1213, $caught === NULL ? 'aucun interblocage' : get_class($caught));

			$this->assertTrue($this->tryWriteTries($third, 9) instanceof \TransactionAbortedException, 'l\'écriture qui suit l\'interblocage passe');

		\queue\Task::model()->rollBack();

		$this->assertEquals([0, 0, 0], $this->readTries(), 'une écriture du geste a été validée');

	}

	/**
	 * Une connexion perdue est remplacée par une neuve, qui n'a pas la transaction : le refus vaut
	 * pour elle aussi. L'horloge réglée vérifie que la reconnexion la reprend.
	 */
	public function testLostConnectionRefusesWhatFollows(): void {

		$this->setClock('2026-01-15 12:00:00');

		[$first, , $third] = $this->push();

		$database = \queue\Task::model()->pdo();
		$connection = (int)$database->selectUnique('SELECT CONNECTION_ID()');

		\queue\Task::model()->beginTransaction();

			$this->writeTries($first, 1);

			$mysqli = $this->connect();
			$mysqli->query('KILL '.$connection);
			$mysqli->close();

			$caught = $this->tryWriteTries($third, 9);
			$this->assertTrue($caught instanceof \LostServerException, $caught === NULL ? 'la connexion tuée écrit encore' : get_class($caught));

			$this->assertTrue($this->tryWriteTries($third, 9) instanceof \TransactionAbortedException, 'la connexion neuve écrit hors de la transaction');

		\queue\Task::model()->rollBack();

		$this->assertEquals(\Clock::timestamp(), (int)$database->selectUnique('SELECT UNIX_TIMESTAMP()'), 'la connexion neuve a perdu l\'horloge');
		$this->assertEquals([0, 0, 0], $this->readTries(), 'une écriture du geste a été validée');

	}

	/**
	 * La seconde connexion verrouille `$second` et `$third`, puis attend `$first`, que tient la
	 * connexion du cadriciel ; celle-ci demande alors `$second` par `$victim`, et MySQL la défait.
	 *
	 * @return ?\Throwable Ce que la connexion du cadriciel a reçu.
	 */
	protected function deadlock(int $first, int $second, int $third, \Closure $victim): ?\Throwable {

		$mysqli = $this->connect();

		$mysqli->query('START TRANSACTION');
		$mysqli->query('UPDATE '.$this->getTable().' SET tries = 5 WHERE id IN ('.$second.', '.$third.')');
		$mysqli->query('UPDATE '.$this->getTable().' SET tries = 5 WHERE id = '.$first, MYSQLI_ASYNC);

		// Le temps que la requête asynchrone arrive au serveur et se mette en attente du verrou.
		usleep(300000);

		$caught = NULL;

		try {
			$victim();
		} catch(\Throwable $e) {
			$caught = $e;
		}

		$mysqli->reap_async_query();
		$mysqli->query('ROLLBACK');
		$mysqli->close();

		return $caught;

	}

	protected function connect(): \mysqli {

		$mTask = \queue\Task::model();
		$database = $mTask->pdo();

		return new \mysqli($database->getParam('host'), $database->getParam('login'), $database->getParam('password'), $mTask->getDatabase(), (int)$database->getParam('port'));

	}

	protected function writeTries(int $id, int $tries): void {
		// Sans TaskLib ni build() : seul compte le verrou de ligne que prend l'UPDATE, et `tries` n'a
		// aucune règle que build() vérifierait.
		\queue\Task::model()->pdo()->exec('UPDATE '.$this->getTable().' SET tries = '.$tries.' WHERE id = '.$id);
	}

	protected function tryWriteTries(int $id, int $tries): ?\Throwable {

		try {
			$this->writeTries($id, $tries);
			return NULL;
		} catch(\Throwable $e) {
			return $e;
		}

	}

	/** Relu d'une connexion neuve : seul ce qui est validé compte. */
	protected function readTries(): array {

		$database = new \Database('queue');

		$rs = $database->select('SELECT tries FROM '.$this->getTable().' WHERE action = '.$database->quote(self::ACTION).' ORDER BY reference');
		$tries = array_map('intval', $rs->fetchAll(\PDO::FETCH_COLUMN));
		$rs->closeCursor();

		return $tries;

	}

	protected function getTable(): string {

		$mTask = \queue\Task::model();

		return '`'.$mTask->getDatabase().'`.`'.$mTask->getTable().'`';

	}

}
?>
