<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=QueryRecord
 */
class QueryRecordTest extends TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return new QueryModel();
	}

	public function testRecordsResponseAndStatements(): void {

		$pdo = Query::model()->pdo();
		$marker = bin2hex(random_bytes(4));
		$eQueryBefore = $this->getLastQuery();

		\Database::setServerTiming(TRUE);

		try {

			$pdo->select('SELECT \'a\' AS repeated_'.$marker);
			$pdo->select('SELECT \'a\' AS repeated_'.$marker);
			$pdo->select('SELECT \'a\' AS repeated_'.$marker);
			$pdo->select('SELECT SLEEP('.((DevSetting::QUERY_SLOW_MS + 10) / 1000).') AS slow_'.$marker);
			$pdo->select('SELECT \'a\' AS single_'.$marker);

			$queries = \Database::getQueryCount();
			$duplicates = \Database::getQueryDuplicates();
			$time = \Database::getQueryTime();

			\Database::journal();

		} finally {
			\Database::setServerTiming(FALSE);
		}

		$this->assertEquals([$queries, $duplicates, $time], [\Database::getQueryCount(), \Database::getQueryDuplicates(), \Database::getQueryTime()], 'l\'écriture du journal ne compte pas dans l\'en-tête');

		$eQuery = $this->getLastQuery();

		if($this->assertDecorPresent($this->isNew($eQuery, $eQueryBefore), 'allumé, une ligne écrite') === FALSE) {
			return;
		}

		$this->assertEquals($queries, $eQuery['queries'], 'la ligne porte le nombre de requêtes de l\'en-tête');
		$this->assertEquals($duplicates, $eQuery['duplicates'], 'la ligne porte le nombre de doubles de l\'en-tête');
		$this->assertEquals(\Route::getRequestedWith(), $eQuery['mode'], 'la ligne porte le mode');

		$cQueryStatement = $this->getStatements($eQuery);

		$repeated = 'SELECT ? AS repeated_'.$marker;
		$slow = 'SELECT SLEEP(?) AS slow_'.$marker;

		$this->assertFalse($cQueryStatement->offsetExists('SELECT ? AS single_'.$marker), 'une requête rapide jouée une fois n\'est pas gardée');
		$this->assertTrue($cQueryStatement->offsetExists($repeated), 'la requête jouée trois fois est gardée');
		$this->assertTrue($cQueryStatement->offsetExists($slow), 'la requête lente jouée une fois est gardée');

		if($cQueryStatement->offsetExists($repeated)) {
			$this->assertEquals(3, $cQueryStatement[$repeated]['executions'], 'trois exécutions');
			$this->assertEquals(hash('xxh128', $repeated), $cQueryStatement[$repeated]['hash'], 'le hash est celui de la forme normalisée');
		}

		if($cQueryStatement->offsetExists($slow)) {
			$this->assertEquals(1, $cQueryStatement[$slow]['executions'], 'une exécution');
			$this->assertTrue($cQueryStatement[$slow]['timeMax'] > DevSetting::QUERY_SLOW_MS, 'son temps est en millisecondes');
		}

	}

	public function testNothingWhenOff(): void {

		$pdo = Query::model()->pdo();
		$marker = bin2hex(random_bytes(4));
		$eQueryBefore = $this->getLastQuery();

		\Database::setServerTiming(TRUE);

		$pdo->select('SELECT \'a\' AS off_'.$marker);
		$pdo->select('SELECT \'a\' AS off_'.$marker);

		\Database::setServerTiming(FALSE);

		\Database::journal();

		$this->assertFalse($this->isNew($this->getLastQuery(), $eQueryBefore), 'éteint, aucune ligne');

	}

	public function testKeepsTheCostliest(): void {

		$pdo = Query::model()->pdo();
		$marker = bin2hex(random_bytes(4));
		$eQueryBefore = $this->getLastQuery();

		\Database::setServerTiming(TRUE);

		try {

			foreach(range(1, DevSetting::QUERY_SAVE_MAX + 5) as $position) {
				$pdo->select('SELECT \'a\' AS capped_'.$position.'_'.$marker);
				$pdo->select('SELECT \'a\' AS capped_'.$position.'_'.$marker);
			}

			\Database::journal();

		} finally {
			\Database::setServerTiming(FALSE);
		}

		$eQuery = $this->getLastQuery();

		if($this->assertDecorPresent($this->isNew($eQuery, $eQueryBefore), 'allumé, une ligne écrite') === FALSE) {
			return;
		}

		$this->assertEquals(DevSetting::QUERY_SAVE_MAX, $this->getStatements($eQuery)->count(), 'au plus '.DevSetting::QUERY_SAVE_MAX.' requêtes par réponse');

	}

	public function testNormalize(): void {

		$this->assertEquals('SELECT m1.id FROM `dev_structure_?`.`series` m1 WHERE m1.name = ? AND m1.id IN (?) LIMIT ?, ?', QueryStatementLib::normalize("SELECT m1.id FROM `dev_structure_7`.`series` m1\n WHERE m1.name = 'l\\'été' AND m1.id IN (12, 13,14) LIMIT 0, 20"), 'chaînes, nombres, liste et base de structure');
		$this->assertEquals('SELECT ? FROM t WHERE a > -? AND b = ?', QueryStatementLib::normalize('SELECT 1.5 FROM t WHERE a > -3 AND b = "x"'), 'décimal, négatif, guillemets doubles');
		$this->assertEquals('SELECT x FROM t WHERE c = ?', QueryStatementLib::normalize("SELECT x FROM t WHERE c = 'it''s'"), 'apostrophe doublée');
		$this->assertEquals('SELECT ? AS x', QueryStatementLib::normalize("SELECT '".str_repeat('a', 100000)."' AS x"), 'littéral de 100 Ko');
		$this->assertEquals('SELECT * FROM t WHERE id IN (?)', QueryStatementLib::normalize('SELECT * FROM t WHERE id IN ('.implode(', ', range(1, 50000)).')'), 'liste de 50 000 valeurs');

	}

	public function testClean(): void {

		// Une horloge antérieure à toute réponse du journal : seules les deux lignes du test peuvent y être anciennes
		$this->setClock('2026-01-15 10:00:00');

		// La ligne ancienne écrite après la récente : une purge par id emporterait la récente
		$eQueryRecent = $this->createQuery('2026-01-14 10:00:00');
		$eQueryOld = $this->createQuery('2025-12-26 10:00:00');

		QueryLib::clean();

		$this->assertTrue(QueryLib::getById($eQueryOld['id'], ['id'])->empty(), 'la ligne de vingt jours');
		$this->assertTrue(QueryStatement::model()->whereQuery($eQueryOld)->count() === 0, 'les requêtes de la ligne de vingt jours');
		$this->assertTrue(QueryLib::getById($eQueryRecent['id'], ['id'])->notEmpty(), 'la ligne d\'un jour');
		$this->assertEquals(1, QueryStatement::model()->whereQuery($eQueryRecent)->count(), 'les requêtes de la ligne d\'un jour');

	}

	/**
	 * La transaction du test fige sa lecture à la première : une ligne écrite ensuite par une autre
	 * réponse de la dev n'y apparait pas, la dernière ligne lue est donc celle du test.
	 */
	private function getLastQuery(): Query {

		return Query::model()
			->select(Query::getSelection())
			->sort(['id' => SORT_DESC])
			->get();

	}

	private function getStatements(Query $eQuery): \Collection {

		return QueryStatement::model()
			->select(QueryStatement::getSelection())
			->whereQuery($eQuery)
			->getCollection(NULL, NULL, 'statement');

	}

	private function isNew(Query $eQuery, Query $eQueryBefore): bool {
		return $eQuery->notEmpty() and ($eQueryBefore->empty() or $eQuery['id'] > $eQueryBefore['id']);
	}

	private function createQuery(string $createdAt): Query {

		// Sans build() : la date de création est le cas éprouvé, et aucun formulaire ne la saisit
		$eQuery = new Query([
			'method' => 'GET',
			'request' => 'test/queryRecord',
			'page' => NULL,
			'name' => NULL,
			'mode' => 'cli',
			'user' => new \user\User(),
			'time' => 1.0,
			'queries' => 1,
			'duplicates' => 0,
			'duration' => 1.0,
			'createdAt' => $createdAt
		]);

		QueryLib::create($eQuery);

		QueryStatementLib::createByQuery($eQuery, [
			['statement' => 'SELECT 1', 'executions' => 2, 'time' => 0.001, 'timeMax' => 0.001]
		]);

		return $eQuery;

	}

}
?>
