<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=QueryCount
 */
class QueryCountTest extends \Test {

	public function testCountsSelectAndExec(): void {

		$pdo = \user\User::model()->pdo();

		$queries = $this->countQueries(function() use($pdo) {
			$pdo->select('SELECT 1');
			$pdo->select('SELECT 2');
			$pdo->exec('DO 1');
		});

		$this->assertEquals(3, $queries, 'deux lectures et une écriture font trois requêtes');

	}

	public function testCountsWhatDebugPrints(): void {

		$pdo = \user\User::model()->pdo();
		$debug = \Database::getDebug();

		\Database::setDebug(TRUE);
		ob_start();

		try {
			$queries = $this->countQueries(fn() => \user\User::model()->select('id')->whereId('<', 10)->getCollection());
			$pdo->select('SELECT 1');
		} finally {
			$output = ob_get_clean();
			\Database::setDebug($debug);
		}

		$this->assertEquals($queries + 1, preg_match_all('/ : (.*) \(\S*?[\d.]+ s\.\S*\)$/m', $output), 'chaque requête comptée est une ligne du mode debug, et inversement');

	}

	public function testDuplicates(): void {

		$pdo = \user\User::model()->pdo();
		$marker = bin2hex(random_bytes(8));

		$duplicates = \Database::getQueryDuplicates();

		\Database::setServerTiming(TRUE);

		try {
			$pdo->select('SELECT \''.$marker.'\'');
			$pdo->select('SELECT \''.$marker.'\'');
			$pdo->select('SELECT \''.$marker.'\'');
			$pdo->select('SELECT \''.$marker.'-other\'');
		} finally {
			\Database::setServerTiming(FALSE);
		}

		$this->assertEquals(2, \Database::getQueryDuplicates() - $duplicates, 'une requête jouée trois fois compte deux doubles, une requête distincte aucun');

		$pdo->select('SELECT \''.$marker.'\'');

		$this->assertEquals(2, \Database::getQueryDuplicates() - $duplicates, 'éteint, le relevé des doubles ne compte plus');

	}

	public function testServerTiming(): void {

		\user\User::model()->pdo()->select('SELECT 1');

		$this->assertEquals(1, preg_match('/^sql;dur=([0-9]+\.[0-9]{3}), sql-count;desc=([0-9]+), sql-duplicate;desc=([0-9]+)$/', \Database::getServerTiming(), $matches), 'forme de l\'en-tête : '.\Database::getServerTiming());
		$this->assertEquals(\Database::getQueryCount(), (int)$matches[2], 'l\'en-tête porte le nombre de requêtes');
		$this->assertEquals(\Database::getQueryDuplicates(), (int)$matches[3], 'l\'en-tête porte le nombre de doubles');
		$this->assertTrue((float)$matches[1] > 0, 'le temps SQL est en millisecondes et non nul après une requête');

	}

}
?>
