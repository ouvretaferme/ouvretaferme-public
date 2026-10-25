<?php
/**
 * This class is a factory for database connections.>
 */

class Database extends PDO {

	/**
	 * List of registered servers
	 *
	 * @var array
	 */
	protected static $servers = [];

	/**
	 * List of registered databases
	 *
	 * @var array
	 */
	protected static $bases = [];

	/**
	 * List of registered packages and their base
	 *
	 * @var array
	 */
	protected static $packages = [];

	/**
	 * Connection parameters
	 *
	 * @var array
	 */
	protected static $connections = [];

	private $instances = [];

	/**
	 * Current parameters
	 *
	 * @var string
	 */
	protected $params;

	private static int $count = 0;
	private static float $time = 0.0;

	/**
	 * Class configuration
	 */
	private static $debug = FALSE; // Display each query
	private static bool $serverTiming = FALSE;

	private static int $duplicates = 0;
	private static array $hashes = [];
	private static array $statements = [];
	private static bool $journal = FALSE;

	/**
	 * Option to reconnect in case of failure.
	 * Set as string so in order to avoid overriding some PDO constants.
	 * This affects $this->maxReconnections and $this->reconnections
	 */
	const MAX_RECONNECTIONS = "Max number of reconnections";

	private $maxReconnections;
	private $reconnections = 0;

	/**
	 * L'erreur MySQL qui a défait la transaction de cette connexion, NULL tant qu'elle tient.
	 */
	private ?string $transactionLost = NULL;

	private string $package;
	private array $options;

	/**
	 * DatabaseFactory constructor
	 *
	 * @param string $package A package name
	 * @param array $options Options for the connection
	 */
	public function __construct(string $package, array $options = []) {

		$this->package = $package;
		$this->options = $options;

		if(strpos($package, '@') !== FALSE) {
			list($package, $at) = explode('@', $package, 2);
		} else {
			$at = NULL;
		}

		// Check is the package is registered
		if(isset(self::$packages[$package]) === FALSE) {
			throw new ConnectionDatabaseException("Package '".$package."' is not registered");
		}

		// Check is the base is registered
		if(isset(self::$bases[self::$packages[$package]]) === FALSE) {
			throw new ConnectionDatabaseException("Base '".self::$packages[$package]."' is not registered");
		}

		$this->params = self::$servers[self::$bases[self::$packages[$package]]];

		if($at !== NULL) {

			if(isset($this->params['@'.$at])) {
				$this->params = $this->params['@'.$at] + $this->params;
			} else {
				throw new ConnectionDatabaseException("Access '".$package."@".$at."' is not registered");
			}

		}

		$login = $this->params['login'];
		$password = $this->params['password'];

		$options += $this->params['options'] ?? [];
		$options += [
			PDO::ATTR_PERSISTENT => FALSE,
		];

		if($this->params['type'] === 'MySQL') {
			$options += [
				\Pdo\Mysql::ATTR_DIRECT_QUERY => TRUE,
				\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY => TRUE,
				\Pdo\Mysql::ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
			];
		}

		$this->maxReconnections = $options[self::MAX_RECONNECTIONS] ?? 3; // 3 is default.

		try {

			$dsn = $this->dsn($this->params);

			parent::__construct(
				$dsn,
				$login, $password,
				$options
			);

		}
		catch(PDOException $e) {

			$message = $e->getMessage();

			if(preg_match("/SQLSTATE\[([0-9]+)\]\s*\[([0-9]+)\]/si", $e->getMessage(), $result)) {

				$sqlSpecificCode = (int)$result[2];

				$code = $sqlSpecificCode;

			} else {
				$code = $e->getCode();
			}

			throw new ConnectionDatabaseException($message, 9990000 + $code);

		}

		if(Clock::isSet()) {
			$this->exec('SET timestamp = '.Clock::timestamp());
		}

	}

	private function dsn(array $params): string {

		switch($params['type']) {
			case 'MySQL' :
				return "mysql:host=".$params['host'];
			default :
				throw new Exception($params['type'].' is not supported');
		}

	}

	/**
	 * Add a new server
	 *
	 * [
	 *		'type' => 'MySQL',
	 *		'host' => '127.0.0.1',
	 *		'port' => 3306,
	 *		'login' => 'root',
	 *		'password' => '???',
	 *		'bases' => ['dev', 'session'],
	 *		'@replication' => [
	 *			'host' => '127.0.0.1',
	 *		]
	 *	]
	 *
	 * @param array $server
	 */
	public static function addServer(array $server) {

		array_expects($server, ['name', 'type', 'host', 'port', 'login', 'password', 'bases']);

		$name = $server['name'];

		self::$servers[$name] = $server;

		foreach($server['bases'] as $base) {
			self::$bases[$base] = $name;
		}

	}

	public static function addBase(string $base, string $server) {
		self::$bases[$base] = $server;
	}

	public static function getServerByBase(string $base): ?string {
		return self::$bases[$base] ?? NULL;
	}

	/**
	 * Reset server configuration
	 */
	public static function resetServers() {
		self::$servers = [];
		self::$bases = [];
	}

	/**
	 * Link packages to bases
	 *
	 * [
	 *		'Cache' => 'cache',
	 *	]
	 *
	 */
	public static function addPackages(array $packages): void {
		self::$packages += $packages;
	}

	/**
	 * Link package to base
	 */
	public static function setPackage(string $package, string $base): void {
		self::$packages[$package] = $base;
	}

	/**
	 * Link packages to bases
	 *
	 * [
	 *		'Cache' => 'cache',
	 *	]
	 *
	 */
	public static function setPackages(array $packages): void {
		self::$packages = $packages;
	}

	/**
	 * Return registered packages
	 *
	 * @return array
	 */
	public static function getPackages(): array {
		return self::$packages;
	}

	/**
	 * Reset package configuration
	 */
	public static function resetPackages() {
		self::$packages = [];
	}

	/**
	 * Check if a connection exists
	 */
	public static function hasConnection(string $connection): bool {
		return isset(self::$connections[$connection]);
	}

	/**
	 * Get Database files
	 */
	public function __get(string $property) {

		if(isset($this->instances[$property]) === FALSE) {

			$class = $this->params['type'].ucfirst($property);
			$file = LIME_DIRECTORY.'/framework/core/Database/drivers/'.$this->params['type'].'/'.$class.'.php';

			require_once $file;

			$this->instances[$property] = new $class($this);

		}

		return $this->instances[$property];


	}

	/**
	 * Get server parameters for a package
	 *
	 * @param string $package
	 *
	 * @return array
	 */
	public static function SERVER(string $package): array {

		if(strpos($package, '@') !== FALSE) {
			list($package, $at) = explode('@', $package, 2);
		} else {
			$at = NULL;
		}

		$base = self::getBase($package);

		if($base === NULL) {
			throw new Exception('Could not find package \''.$package.'\'');
		}

		$serverId = self::$bases[$base] ?? NULL;

		if($serverId === NULL) {
			throw new Exception('Could not find server for database \''.$base.'\'');
		}

		$server = self::$servers[$serverId] ?? NULL;

		if($server === NULL) {
			throw new Exception('Could not find server for package \''.$package.'\'');
		}

		if($at !== NULL) {
			return $server['@'.$at] + $server;
		} else {
			return $server;
		}

	}

	/**
	 * Get base for a package
	 *
	 * @param string $package
	 *
	 * @return string / null
	 */
	public static function getBase(string $package): string {
		if(isset(self::$packages[$package]) === FALSE) {
			throw new \Exception('Package \''.$package.'\' is not registered');
		}
		return self::$packages[$package];
	}

	/**
	 * Get a param of the current connection
	 *
	 * @param string $name Param name
	 *
	 * @return string / null Param value
	 */
	public function getParam(string $name) {
		return $this->params[$name] ?? NULL;
	}

	/**
	 * Execute a SQL query
	 *
	 * $hasRows must be set to TRUE for queries that return rows, such as OPTIMIZE, ALTER...
	 */
	public function exec($statement, bool $hasRows = FALSE): int|false {

		$this->checkTransaction();

		$trace = $this->trace($statement);

		try {

			if($hasRows) {
				$result = $this->query($statement);
				if($result !== FALSE) {
					$result->closeCursor();
				}
			} else {
				$result = parent::exec($statement);
			}

			$this->untrace($trace, $statement, __FUNCTION__);

		} catch(PDOException $e) {
			$this->untrace($trace, $statement, __FUNCTION__);
			$this->handleFailure($statement, $e->errorInfo);
		}


		try {
			$return = $this->handleResult($statement, $result);
			$this->reconnections = 0;
			return $return;

		}
		catch(LostServerException $e) {

			if($this->reconnections < $this->maxReconnections) {
				$this->reconnections++;
				return $this->exec($statement); // replay.
			}
			throw $e;
		}
		catch(DeadLockException $e) {

			return $this->exec($statement);

		}

	}

	/**
	 * Perform a select SQL query
	 *
	 */
	public function select($statement, ...$options) {

		$this->checkTransaction();

		$trace = $this->trace($statement);

		if($options === []) {
			$options = [PDO::FETCH_ASSOC];
		}

		// Une lecture reçoit l'exception de PDO, que ses appelants lisent par `errorInfo` : elle ressort
		// telle quelle, une fois noté ce qui a défait la transaction (une lecture verrouillante
		// interbloquée, une connexion perdue).
		try {
			$result = $this->query($statement, ...$options);
		} catch(PDOException $e) {

			if($this->losesTransaction((int)($e->errorInfo[1] ?? 0))) {
				$this->transactionLost = $e->getMessage();
			}

			throw $e;

		}

		$this->untrace($trace, $statement, __FUNCTION__);

		try {
			$return = $this->handleResult($statement, $result);
			$this->reconnections = 0;
			return $return;

		}
		catch(LostServerException $e) {

			if($this->reconnections < $this->maxReconnections) {
				$this->reconnections++;
				return $this->select($statement); // replay.
			}
			throw $e;
		}

	}

	/**
	 * Execute a SQL query and returns a unique result
	 *
	 * @param string $sql The SQL query
	 * @return mixed The first field of the first result, or false if there is no result
	 */
	public function selectUnique($statement) {

		$rs = $this->select($statement, PDO::FETCH_NUM);
		$row = $rs->fetch();
		$rs->closeCursor();
		return $row ? current($row) : NULL;

	}

	/**
	 * Handle SQL errors
	 *
	 */
	protected function handleResult($statement, $result) {

		if($result !== FALSE) {
			return $result;
		} else {
			$this->handleFailure($statement, $this->errorInfo());
		}

	}

	protected function handleFailure(string $statement, array $errorInfo): void {

		list($sqlCode, $sqlSpecificCode, $sqlError) = $errorInfo;

		$sqlSpecificCode = (int)$sqlSpecificCode;

		// Lu avant la reconnexion, qui remplace la connexion par une autre sans transaction ouverte.
		$losesTransaction = $this->losesTransaction($sqlSpecificCode);

		// 2006: "MySQL Server has gone away". 2013: "Lost connection to MySQL server during query"
		if($sqlSpecificCode === 2006 or $sqlSpecificCode === 2013) {

			$this->__construct($this->package, $this->options); // reconnect

			// Noté après la reconnexion, dont le `SET timestamp` d'une horloge réglée serait refusé.
			if($losesTransaction) {
				$this->transactionLost = $sqlError;
			}

			throw new LostServerException($sqlError);

		}

		// 1213: "Deadlock found when trying to get lock; try restarting transaction"
		if($sqlSpecificCode === 1213) {

			if($losesTransaction) {
				$this->transactionLost = $sqlError;
			}

			throw new DeadLockException($sqlError);

		}

		throw new QueryDatabaseException(
			$sqlError." - Full SQL query: ".$statement, (int)$sqlSpecificCode
		);

	}

	/**
	 * Un interblocage (1213) ou une connexion perdue (2006, 2013) défait la transaction entière côté
	 * MySQL, sans que PDO ni la pile de `ModuleModel` le sachent. Ce qui s'écrirait ensuite partirait
	 * en autocommit, et le geste serait validé à moitié : la connexion refuse donc toute requête
	 * jusqu'au `rollBack()` de qui tient la transaction, y compris quand un `catch` a avalé l'erreur
	 * d'origine. Une attente de verrou dépassée (1205) n'est pas du nombre : tant que
	 * `innodb_rollback_on_timeout` vaut 0, sa valeur par défaut, MySQL ne défait que l'instruction, et
	 * la transaction reste valable.
	 */
	private function losesTransaction(int $sqlSpecificCode): bool {

		return (
			in_array($sqlSpecificCode, [1213, 2006, 2013], TRUE) and
			parent::inTransaction()
		);

	}

	private function checkTransaction(): void {

		if($this->transactionLost !== NULL) {
			throw new TransactionAbortedException('Transaction rolled back by MySQL, rollBack() expected before any query: '.$this->transactionLost);
		}

	}

	public function beginTransaction(): bool {

		$begun = parent::beginTransaction();

		$this->transactionLost = NULL;

		return $begun;

	}

	public function commit(): bool {

		if($this->transactionLost !== NULL) {

			$sqlError = $this->transactionLost;

			$this->rollBack();

			throw new TransactionAbortedException('Transaction rolled back by MySQL, nothing to commit: '.$sqlError);

		}

		return parent::commit();

	}

	public function rollBack(): bool {

		$transactionLost = $this->transactionLost;

		$this->transactionLost = NULL;

		// Une transaction défaite par MySQL n'est plus ouverte, et une reconnexion a remplacé la
		// connexion par une autre qui n'en a jamais ouvert : PDO lèverait sur ce qui est le geste attendu.
		// Hors de ce cas, l'annulation d'une transaction absente lève toujours.
		if(
			$transactionLost !== NULL and
			parent::inTransaction() === FALSE
		) {
			return TRUE;
		}

		return parent::rollBack();

	}


	public function trace(&$statement) {
		return microtime(TRUE);
	}

	public function untrace($trace, $sql, $function) {

		$time = microtime(TRUE) - $trace;

		self::$count++;
		self::$time += $time;

		if(self::$serverTiming) {

			$hash = hash('xxh128', $sql);

			if(isset(self::$hashes[$hash])) {

				self::$duplicates++;

				self::$statements[$hash] ??= [
					'statement' => $sql,
					'executions' => 1,
					'time' => self::$hashes[$hash],
					'timeMax' => self::$hashes[$hash]
				];

				self::$statements[$hash]['executions']++;
				self::$statements[$hash]['time'] += $time;
				self::$statements[$hash]['timeMax'] = max(self::$statements[$hash]['timeMax'], $time);

			} else {

				self::$hashes[$hash] = $time;

				if($time * 1000 > \dev\DevSetting::QUERY_SLOW_MS) {
					self::$statements[$hash] = [
						'statement' => $sql,
						'executions' => 1,
						'time' => $time,
						'timeMax' => $time
					];
				}

			}

		}

		if(self::$debug) {

			$backtrace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0);

			foreach($backtrace as $key => $value) {

				if(
					strpos($value['file'], LIME_DIRECTORY.'/framework/core/Database/') === 0 or
					$value['file'] === LIME_DIRECTORY.'/framework/core/Module.php'
				) {
					unset($backtrace[$key]);
					continue;
				} else {
					break;
				}

			}

			// Print debug information
			$sql = trim(preg_replace("/[ \n\r\t]+/s", " ", $sql));


			switch(Route::getRequestedWith()) {

				case 'cli' :
					echo ''.$function.'() (\033[01m'.self::$count.'\033[00m : ';
					echo $sql.' (\033[31m'.sprintf("%.5f", $time).' s.\033[00m)'."\n";
					break;

				default :

					$h = '';

					if(Route::getRequestedWith() === 'http') {
						$h .= '<div class="dev-sql">';
					}

					$h .= '<div class="dev-block">';
					$h .= '<div class="dev-extends" onclick="Dev.extends(this)"><span class="btn btn-primary btn-xs">'.self::$count.'</span> '.$this->api->color($sql).' (<span class="sql-query-time">'.sprintf("%.5f", $time).' s.'.'</span>)</div>';
					$h .= '<div class="dev-trace">';
					$h .= \dev\TraceLib::getHttp($backtrace);
					$h .= '</div>';
					$h .= '</div>';

					if(Route::getRequestedWith() === 'http') {
						$h .= '</div>';
					}

					register_shutdown_function(function() use($h) {
						echo $h;
					});

					break;
			}

		}

	}

	/**
	 * Change 'debug' parameter
	 *
	 * @param bool $trace
	 */
	public static function setDebug($debug) {
		self::$debug = (bool)$debug;
	}

	/**
	 * Get actual value of 'debug' parameter
	 *
	 * @return bool
	 */
	public static function getDebug() {
		return self::$debug;
	}

	public static function setServerTiming(bool $serverTiming): void {

		self::$serverTiming = $serverTiming;

		// PHP ne garde qu'un rappel par requête : un second header_register_callback() remplace celui-ci
		header_register_callback(function() {

			if(self::$serverTiming) {
				header('Server-Timing: '.self::getServerTiming());
			}

		});

		if($serverTiming and self::$journal === FALSE) {

			self::$journal = TRUE;

			// Une fonction inscrite pendant l'arrêt passe en fin de file : le journal compte aussi les
			// requêtes des fonctions d'arrêt inscrites après celle-ci (Route, session)
			register_shutdown_function(fn() => register_shutdown_function(fn() => self::journal()));

		}

	}

	public static function journal(): void {

		if(self::$serverTiming === FALSE) {
			return;
		}

		$count = self::$count;
		$time = self::$time;
		$duplicates = self::$duplicates;

		// Une exception levée à l'arrêt s'imprime à la suite de la réponse déjà rendue et casse un JSON ajax
		try {
			\dev\QueryLib::record();
		} catch(\Throwable) {
		}

		// L'en-tête part au premier envoi de la sortie, qui peut suivre les fonctions d'arrêt : sans
		// cette remise, il compterait l'écriture du journal
		self::$count = $count;
		self::$time = $time;
		self::$duplicates = $duplicates;

	}

	public static function getServerTiming(): string {
		return 'sql;dur='.sprintf('%.3F', self::$time * 1000).', sql-count;desc='.self::$count.', sql-duplicate;desc='.self::$duplicates;
	}

	public static function getQueryCount(): int {
		return self::$count;
	}

	public static function getQueryTime(): float {
		return self::$time;
	}

	public static function getStatements(): array {
		return self::$statements;
	}

	public static function getQueryDuplicates(): int {
		return self::$duplicates;
	}

}

/*
 * Generic object for Database libraries
 *
 */
class DatabaseObject {

	/**
	 * Database object
	 *
	 * @var Database
	 */
	protected $db;

	/**
	 * DatabaseFactory constructor
	 *
	 * @param string $db A connection name or a Database object
	 */
	public function __construct($db) {

		if($db instanceof Database) {
			$this->db = $db;
		} else {
			$this->db = new Database($db);
		}

	}

}

/**
 * Exception thrown if a SQL query fails
 */
class QueryDatabaseException extends Exception {

	const ERROR_SYNTAX = 1149;
	const ERROR_DUPLICATE_ENTRY = 1062;
	const ERROR_CRASH = 1194;

}

/**
 * Exception thrown if a server connection is lost. This can happen when
 * several processes try to access the same resource in parallel, or when
 * a network or server timeout occurs. In that case, the query is re-run.
 */
class LostServerException extends Exception {}

/**
 * Exception thrown if a dead lock occurs.
 * In that case, the query is re-run
 */
class DeadLockException extends Exception {}

/**
 * Exception thrown by any query on a connection whose transaction MySQL has rolled back, until rollBack()
 */
class TransactionAbortedException extends Exception {}


/**
 * Exception thrown if the connection to the database fails
 */
class ConnectionDatabaseException extends Exception {

	const ERROR_DENIED = 1044;

	public function handle() {
		$this->write();
	}

}
?>
