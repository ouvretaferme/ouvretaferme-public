<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=TransactionCheck
 */
class TransactionCheckTest extends \Test {

	private int $warnings = 0;
	private string $logPath = '';
	private array $get = [];

	public function setUp(): void {

		$this->get = $_GET;
		unset($_GET['strict']);

		$this->warnings = TestLib::$warnings;
		$this->logPath = tempnam(sys_get_temp_dir(), 'lime-test-transaction-check-');

	}

	public function tearDown(): void {

		TestLib::$warnings = $this->warnings;
		$_GET = $this->get;

		\user\User::model()->rollBack();

		unlink($this->logPath);

	}

	public function testOpenedServer(): void {

		$closed = ['stack' => 0, 'connection' => FALSE];
		$opened = ['stack' => 1, 'connection' => TRUE];

		$this->assertEquals('a:3306', TransactionTest::getOpenedServer(['a:3306' => $closed], ['a:3306' => $opened]));
		$this->assertEquals('b:3306', TransactionTest::getOpenedServer(['a:3306' => $closed], ['a:3306' => $closed, 'b:3306' => $opened]), 'un serveur connecté par l\'ouverture');
		$this->assertEquals('b:3306', TransactionTest::getOpenedServer(['a:3306' => $opened, 'b:3306' => $closed], ['a:3306' => $opened, 'b:3306' => $opened]));
		$this->assertEquals('', TransactionTest::getOpenedServer(['a:3306' => $opened], ['a:3306' => $opened]));

	}

	public function testRefusals(): void {

		$closed = ['stack' => 0, 'connection' => FALSE];
		$opened = ['stack' => 1, 'connection' => TRUE];

		$this->assertEquals([], TransactionTest::getRefusals(['a:3306' => $opened, 'b:3306' => $closed], 'a:3306'));

		$this->assertEquals(
			[['a:3306', TransactionTest::CLOSED]],
			TransactionTest::getRefusals(['a:3306' => $closed], 'a:3306')
		);
		$this->assertEquals(
			[['a:3306', TransactionTest::NESTED]],
			TransactionTest::getRefusals(['a:3306' => ['stack' => 2, 'connection' => TRUE]], 'a:3306')
		);
		$this->assertEquals(
			[['a:3306', TransactionTest::IMPLICIT_COMMIT]],
			TransactionTest::getRefusals(['a:3306' => ['stack' => 1, 'connection' => FALSE]], 'a:3306')
		);
		$this->assertEquals(
			[['b:3306', TransactionTest::FOREIGN]],
			TransactionTest::getRefusals(['a:3306' => $opened, 'b:3306' => $opened], 'a:3306')
		);
		$this->assertEquals(
			[['b:3306', TransactionTest::FOREIGN]],
			TransactionTest::getRefusals(['a:3306' => $opened, 'b:3306' => ['stack' => 0, 'connection' => TRUE]], 'a:3306'),
			'une transaction ouverte sur la connexion sans passer par le modèle'
		);
		$this->assertEquals(
			[['b:3306', TransactionTest::FOREIGN]],
			TransactionTest::getRefusals(['a:3306' => $opened, 'b:3306' => ['stack' => 1, 'connection' => FALSE]], 'a:3306'),
			'une pile montée sur une connexion qui ne porte plus de transaction'
		);
		$this->assertEquals(
			[['a:3306', TransactionTest::CLOSED], ['b:3306', TransactionTest::FOREIGN]],
			TransactionTest::getRefusals(['a:3306' => $closed, 'b:3306' => $opened], 'a:3306')
		);

	}

	/**
	 * ModuleModel::dbClean() vide les connexions et laisse la pile. Les connexions sont retirées
	 * puis rendues par réflexion, dbClean() déconnectant tout le processus.
	 */
	public function testStatesWithoutConnection(): void {

		\user\User::model()->pdo();

		$property = new \ReflectionProperty(\ModuleModel::class, 'db');
		$connections = $property->getValue();

		$property->setValue(NULL, []);

		try {
			$states = \ModuleModel::getTransactionStates();
		} finally {
			$property->setValue(NULL, $connections);
		}

		$this->assertEquals([], $states);
		$this->assertEquals(['stack' => 0, 'connection' => FALSE], $this->getState(), 'les connexions sont rendues');

	}

	public function testUntouchedTransaction(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		$probe->setUp();

		$this->assertEquals(['stack' => 1, 'connection' => TRUE], $this->getState(), 'prémisse : setUp() ouvre la transaction');

		$output = $this->finish($probe);

		$this->assertEquals('', $output);
		$this->assertEquals($this->warnings, TestLib::$warnings);
		$this->assertEquals('', file_get_contents($this->logPath));
		$this->assertEquals(0, $probe->getKo());
		$this->assertEquals(['stack' => 0, 'connection' => FALSE], $this->getState(), 'tearDown() annule la transaction');

	}

	public function testClosedTransaction(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		$probe->setUp();
		\user\User::model()->rollBack();

		$this->assertWarning(
			'dev\TransactionCheckProbe::testProbe() on '.$this->getServer().', the transaction opened by setUp() is closed, nothing written after that point is rolled back',
			$this->finish($probe)
		);
		$this->assertEquals(0, $probe->getKo(), 'un signalement n\'est pas un échec');

	}

	public function testNestedTransaction(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		$probe->setUp();
		\user\User::model()->beginTransaction();

		$this->assertWarning(
			'dev\TransactionCheckProbe::testProbe() on '.$this->getServer().', a transaction opened by the test is still open',
			$this->finish($probe)
		);
		$this->assertEquals(['stack' => 0, 'connection' => FALSE], $this->getState());

	}

	/**
	 * La connexion est fermée sans passer par le modèle, ce qui laisse la pile à 1 comme le fait une
	 * instruction DDL, sans en jouer une.
	 */
	public function testImplicitCommit(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		$probe->setUp();
		\user\User::model()->pdo()->rollBack();

		$this->assertEquals(['stack' => 1, 'connection' => FALSE], $this->getState(), 'prémisse : la pile et la connexion divergent');

		$this->assertWarning(
			'dev\TransactionCheckProbe::testProbe() on '.$this->getServer().', the connection holds no transaction, an implicit commit has written what came before it',
			$this->finish($probe)
		);

		$this->assertEquals(['stack' => 0, 'connection' => FALSE], $this->getState(), 'la pile est remise à zéro');

		$probe->setUp();

		$this->assertEquals(['stack' => 1, 'connection' => TRUE], $this->getState(), 'la méthode suivante ouvre sa transaction');
		$this->assertEquals('', $this->finish($probe));

	}

	public function testImplicitCommitStrict(): void {

		$probe = $this->getProbe(TransactionCheckStrictProbe::class);

		$probe->setUp();
		\user\User::model()->pdo()->rollBack();

		$probe->tearDown();

		$this->assertEquals(1, $probe->getKo(), 'le refus nommé est compté, tearDown() ne lève pas');
		$this->assertEquals(['stack' => 0, 'connection' => FALSE], $this->getState());

	}

	/**
	 * Une annulation qui lève pour une autre raison qu'un commit implicite n'est pas étouffée.
	 */
	public function testClosedConnectionStillThrows(): void {

		$probe = $this->getProbe(TransactionCheckNestingProbe::class);

		$probe->setUp();
		\user\User::model()->pdo()->rollBack();

		try {
			$this->finish($probe);
			$this->assertFail('une pile à 2 sur une connexion sans transaction est un refus nested, l\'annulation lève');
		} catch(\PDOException $e) {
			$this->assertException($e, 'There is no active transaction');
		}

		TestLib::$warnings = $this->warnings;

	}

	public function testStrict(): void {

		$probe = $this->getProbe(TransactionCheckStrictProbe::class);

		$probe->setUp();
		\user\User::model()->rollBack();

		$output = $this->finish($probe);

		$this->assertEquals('', $output);
		$this->assertEquals($this->warnings, TestLib::$warnings);
		$this->assertEquals('', file_get_contents($this->logPath));
		$this->assertEquals(1, $probe->getKo());

	}

	public function testStrictArgument(): void {

		foreach(['1' => 1, '0' => 0] as $argument => $ko) {

			$_GET['strict'] = (string)$argument;

			$probe = $this->getProbe(TransactionCheckProbe::class);

			$probe->setUp();
			\user\User::model()->rollBack();

			$this->finish($probe);

			$this->assertEquals($ko, $probe->getKo(), 'strict='.$argument);

		}

	}

	public function testLogKeepsEveryWarning(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		foreach([1, 2] as $lines) {

			$probe->setUp();
			\user\User::model()->rollBack();

			$this->finish($probe);

			$this->assertEquals($lines, substr_count(file_get_contents($this->logPath), PHP_EOL));

		}

	}

	public function testClosedByTest(): void {

		$probe = $this->getProbe(TransactionCheckClosingProbe::class);

		$probe->setUp();
		\user\User::model()->rollBack();

		$this->assertEquals('', $this->finish($probe));
		$this->assertEquals($this->warnings, TestLib::$warnings);

		$probe->setUp();
		\user\User::model()->beginTransaction();

		$this->assertWarning(
			'dev\TransactionCheckClosingProbe::testProbe() on '.$this->getServer().', a transaction opened by the test is still open',
			$this->finish($probe)
		);

	}

	public function testTearDownWithoutSetUp(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		\user\User::model()->beginTransaction();

		$this->assertEquals('', $this->finish($probe), 'une transaction que setUp() n\'a pas ouverte n\'est pas jugée');
		$this->assertEquals($this->warnings, TestLib::$warnings);

	}

	public function testTearDownTwice(): void {

		$probe = $this->getProbe(TransactionCheckProbe::class);

		$probe->setUp();

		$this->assertEquals('', $this->finish($probe));
		$this->assertEquals('', $this->finish($probe), 'le serveur retenu par setUp() est oublié après le contrôle');
		$this->assertEquals($this->warnings, TestLib::$warnings);

	}

	private function getProbe(string $class): TransactionCheckProbe {

		$probe = new $class();
		$probe->logPath = $this->logPath;
		$probe->setTestMethod('testProbe');

		// Connecte le serveur avant le premier relevé.
		\user\User::model()->pdo();

		return $probe;

	}

	private function getServer(): string {
		return array_key_first(\ModuleModel::getTransactionStates());
	}

	private function getState(): array {
		return \ModuleModel::getTransactionStates()[$this->getServer()];
	}

	private function finish(TransactionCheckProbe $probe): string {

		ob_start();

		try {
			$probe->tearDown();
		} finally {
			$output = ob_get_clean();
		}

		return $output;

	}

	private function assertWarning(string $message, string $output): void {

		$this->assertEquals("\033[33mWarning: ".$message."\033[00m".PHP_EOL, $output);
		$this->assertEquals($this->warnings + 1, TestLib::$warnings);

		$log = file_get_contents($this->logPath);

		$this->assertTrue(str_ends_with($log, ' '.$message.PHP_EOL), 'le signalement est ajouté au journal');
		$this->assertEquals(1, substr_count($log, PHP_EOL));

		TestLib::$warnings = $this->warnings;
		file_put_contents($this->logPath, '');

	}

}

class TransactionCheckProbe extends TransactionTest {

	public string $logPath = '';

	protected function getTransactionModel(): \ModuleModel {
		return \user\User::model();
	}

	protected function getLogPath(): string {
		return $this->logPath;
	}

}

class TransactionCheckStrictProbe extends TransactionCheckProbe {

	const STRICT = TRUE;

}

class TransactionCheckNestingProbe extends TransactionCheckProbe {

	public function setUp(): void {

		parent::setUp();

		$this->getTransactionModel()->beginTransaction();

	}

}

class TransactionCheckClosingProbe extends TransactionCheckProbe {

	const CLOSED_BY_TEST = TRUE;

}
?>
