<?php
/**
 * Test
 */
abstract class Test {

	private $ok = 0;
	private $ko = 0;
	private $trace = [];
	private $errorException = NULL;
	private string $method = '';

	/**
	 * Les vues déclarées par chaque fichier de vues, rangées par fichier (`getView()`).
	 *
	 * @var array<string, array<string, \View>>
	 */
	private static array $viewsByFile = [];

	public function getOk(): int {
		return $this->ok;
	}

	public function getKo(): int {
		return $this->ko;
	}

	public function setTestMethod(string $method): void {
		$this->method = $method;
	}

	public function getTestMethod(): string {
		return $this->method;
	}

	/**
	 * Une exception non prévue par le test : elle compte comme un échec, trace à l'appui, au lieu
	 * d'arrêter la campagne. (Auparavant elle remontait jusqu'à une page 500.)
	 */
	public function reportException(string $methodName, \Throwable $e): void {

		$this->ko++;

		$this->trace[] = [
			NULL, NULL, TRUE, NULL,
			'Exception non attrapée : '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().')',
			$e->getTrace(),
			$this->method,
			$this->getTestLine([['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()])
		];

	}

	protected function setClock(string $datetime): void {

		if($this->method === '') {
			throw new Exception('Clock can only be set inside a test method');
		}

		Clock::set($datetime);

	}

	protected function countQueries(\Closure $run): int {

		$count = \Database::getQueryCount();

		$run();

		return \Database::getQueryCount() - $count;

	}

	/**
	 * La vue `$name` du fichier de vues de `$request`, telle qu'une requête du produit la trouve.
	 *
	 * ⚠️ Le registre des vues (`View::$views`) est global au processus et lève sur un nom pris deux
	 * fois. Une requête du produit ne charge qu'un fichier de vues, et un nom court n'y est unique que
	 * dans son fichier (`updateProduct` est déclaré par `amap/view/contract.v.php` comme par
	 * `amap/view/subscription.v.php`) ; une passe de tests en charge beaucoup dans le même processus.
	 * Chaque fichier se charge donc une fois, sur un registre vide, et ses vues se gardent à part :
	 * le registre global reste tel quel.
	 *
	 * ⚠️ `require` et non `require_once`, pour que les vues d'un fichier déjà chargé par un autre chemin
	 * soient déclarées dans le registre vide. Un fichier de vues qui déclare aussi une fonction ou une
	 * classe au premier niveau ne se rejoue pas (« Cannot redeclare », erreur fatale) : celui-là ne
	 * doit être chargé que par ce helper dans tout le processus.
	 */
	protected function getView(string $request, string $name): \View {

		$file = realpath(\Package::getFileFromUri($request, 'view'));

		if(array_key_exists($file, self::$viewsByFile) === FALSE) {

			$registry = new \ReflectionProperty(\View::class, 'views');
			$registryInitial = $registry->getValue();

			$registry->setValue(NULL, []);

			try {
				require $file;
				self::$viewsByFile[$file] = $registry->getValue();
			} finally {
				$registry->setValue(NULL, $registryInitial);
			}

		}

		if(array_key_exists($name, self::$viewsByFile[$file]) === FALSE) {
			throw new \Exception('View '.$name.' does not exist in '.$file);
		}

		return self::$viewsByFile[$file][$name];

	}

	/**
	 * Test starts...
	 */
	public function init() {

	}

	/**
	 * Everytime a method starts...
	 */
	public function setUp() {

	}

	/**
	 * Everytime a method ends...
	 */
	public function tearDown() {

	}

	/**
	 * Test ends...
	 */
	public function finalize() {

	}

	/**
	 * Test if a value equals TRUE
	 *
	 * @param mixed $value
	 * @param string $message Message describing this test case
	 * @return bool
	 */
	protected function assertTrue($value, ?string $message = NULL): bool {
		return $this->assert($value === TRUE, TRUE, TRUE, $value, $message);
	}

	/**
	 * Test if a value equals FALSE
	 *
	 * @param mixed $value
	 * @param string $message Message describing this test case
	 * @return bool
	 */
	protected function assertFalse($value, ?string $message = NULL): bool {
		return $this->assert($value === FALSE, FALSE, TRUE, $value, $message);
	}

	/**
	 * This test always passes.
	 * It can be useful if you want to test that no exception has been thrown
	 *
	 * @return bool Always TRUE
	 */
	protected function assertPass(): bool {
		return $this->assert(TRUE, TRUE, TRUE, TRUE, NULL);
	}

	/**
	 * Is the setting this case relies on there? If not, the case fails naming it instead of passing.
	 *
	 * @param bool $present Whether the setting is there
	 * @param string $expected What the case expects to find
	 * @return bool $present
	 */
	protected function assertDecorPresent(bool $present, string $expected): bool {
		return $this->assert($present, TRUE, TRUE, $present, 'décor attendu : '.$expected);
	}

	/**
	 * This test always fails
	 *
	 * @param string $message Message describing this test case
	 * @return bool Always FALSE
	 */
	protected function assertFail(?string $message = NULL): bool {
		return $this->assert(FALSE, TRUE, TRUE, FALSE, $message);
	}

	/**
	 * Test if a value equals a specific other value
	 *
	 * @param mixed $expected
	 * @param mixed $value
	 * @param string $message Message describing this test case
	 * @return bool
	 */
	protected function assertEquals($expected, $value, ?string $message = NULL): bool {
		return $this->assert($value === $expected, $expected, TRUE, $value, $message);
	}

	/**
	 * Test if a value differs a specific other value
	 *
	 * @param mixed $unexpected
	 * @param mixed $value
	 * @param string $message Message describing this test case
	 * @return bool
	 */
	protected function assertDiffer($unexpected, $value, ?string $message = NULL): bool {
		return $this->assert($value !== $unexpected, $unexpected, FALSE, $value, $message);
	}

	/**
	 * Test an exception
	 *
	 * @param Exception $e An exception
	 * @param string $message The expected exception message (FALSE to ignore this argument)
	 * @param int $code The expected exception message (FALSE to ignore this argument)
	 * @return TRUE if the test passed, FALSE otherwise
	 */
	protected function assertException($e, $message = '', $code = FALSE): bool {

		if($e instanceof Exception) {

			if($message !== '') {
				$flag = ($e->getMessage() === $message);
				$this->assert($flag, TRUE, TRUE, FALSE, "Exception message did not match");
				return $flag;
			}

			if($code !== FALSE) {
				$flag = ($e->getCode() === $code);
				$this->assert($flag, TRUE, TRUE, FALSE, "Exception message did not match");
				return $flag;
			}

		} else {
			$this->assertFail("Expected an exception, '".gettype($e)."' found");
		}

		return FALSE;

	}

	/**
	 * Test a method for an expected PHP error message.
	 *
	 * @param callable $callback A function callback
	 * @param array $args An array of arguments to give (FALSE if there is no arguments)
	 * @param string $message The expected error message (FALSE to ignore this argument)
	 * @param int $code The expected PHP error code (FALSE to ignore this argument)
	 */
	protected function assertError(callable $callback, $args = FALSE, $message = FALSE, $code = FALSE): bool {

		set_error_handler([$this, 'handleError']);

		try {

			if($args === FALSE) {
				call_user_func($callback);
			} else {
				call_user_func_array($callback, $args);
			}

			$errorException = $this->errorException;

		} catch(\Exception $e) {
			$errorException = $e;
		}

		restore_error_handler();
		$this->errorException = NULL;

		if($errorException instanceof Exception) {

			if($message !== FALSE) {
				return $this->assertEquals($errorException->getMessage(), $message, "Error message did not match");
			}

			if($code !== FALSE) {
				return $this->assertEquals($e->getCode(), $code, "Error type did not match");
			}

		} else {
			return $this->assertFail("Expected an error, none occurred");
		}

	}

	/**
	 * Test if an array has the same keys and values than an other one
	 *
	 * @param mixed $expected
	 * @param mixed $value
	 * @return bool
	 */
	protected function assertArraySimilar($expected, $compareTo): bool {

		if(!is_array($compareTo)) {
			return $this->assertFail("Invalid compare type : ".gettype($compareTo));
		}
		return $this->recursiveAssertArraySimilar($expected, $compareTo);
	}

	private function recursiveAssertArraySimilar($expected, $compareTo, $parentsKeys = []): bool {

		$valid = TRUE;

		foreach($expected as $key => $value) {
			$includePath = array_merge($parentsKeys, [$key]);
			if(!array_key_exists($key, $compareTo)) {
				$valid = ($this->assertFail("Could not find key ".implode(' => ', $includePath)) and $valid);
			} else if(is_array($compareTo[$key]) and is_array($value)) {
				$valid = ($this->recursiveAssertArraySimilar($value, $compareTo[$key], $includePath) and $valid);
			} else {
				$valid = ($this->assertEquals($value, $compareTo[$key], "Values for key ".implode(' => ', $includePath)." does not match") and $valid);
			}
		}

		foreach($compareTo as $key => $value) {
			if(!array_key_exists($key, $expected)) {
				$valid = ($this->assertFail("Unexpected key ".implode(' => ', array_merge($parentsKeys, [$key]))) and $valid);
			}
		}

		return $valid;

	}

	/**
	 * Test a value
	 *
	 * @param bool $valid
	 * @param mixed $compareTo Expected or Unexpected value
	 * @param bool $equality If the test was "===" or "!=="
	 * @param mixed $value Obtained value
	 * @param string $message Test message
	 */
	protected function assert(bool $valid, $compareTo, bool $equality, $value, ?string $message): bool {

		if($valid) {
			$this->ok++;
		} else {
			$this->ko++;

			// Le rouge d'une sentinelle peut tenir à un fichier que cet arbre seul porte : il le dit,
			// pour que la déclaration ne se corrige pas sur un verdict local.
			if($message !== NULL and fnmatch(\dev\TestJobLib::PATTERN, static::class)) {
				$message .= \dev\SentinelLib::explainGitState($message, substr(new \ReflectionClass($this)->getFileName(), strlen(\dev\SentinelLib::getRoot()) + 1));
			}

			$backtrace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1, -8);

			$this->trace[$this->ko] = [
				$valid,
				$compareTo,
				$equality,
				$value,
				$message,
				$backtrace,
				$this->method,
				$this->getTestLine($backtrace),
			];

		}

		return $valid;

	}

	/**
	 * Show tests results
	 *
	 */
	public function show(): string {

		$className = get_class($this);

		$text = "";
		$text .= "+-------- ".bold($className)." -------+\n";
		$text .= "| Passed: ".bold($this->ok, "32")."".str_repeat(' ', strlen($className) - strlen($this->ok) + 8)."|\n";
		$text .= "| Failed: ".bold($this->ko, "31")."".str_repeat(' ', strlen($className) - strlen($this->ko) + 8)."|\n";
		$text .= "+--------".str_repeat('-', strlen($className) + 2)."-------+\n";

		foreach($this->trace as $test => $trace) {

			list($valid, $compareTo, $equality, $value, $message, $backtrace) = $trace;

			$in = $backtrace[0];

			$text .= "\n";
			$text .= "Test #".$test.": ".$in['function']."()\n";

			if($valid !== NULL) {
				if($equality === TRUE) {
					$text .= " * Expected: ".bold(var_export($compareTo, TRUE))."\n";
					$text .= " * Received: ".bold(var_export($value, TRUE))."\n";
				} else {
					$text .= " * Unexpected: ".bold(var_export($compareTo, TRUE))."\n";
				}
			}

			if($message !== NULL) {
				$text .= " * Message: ".bold($message)."\n";
			}

			if($backtrace !== []) {

				$text .= " * Stack trace:\n";
				$text .= \dev\TraceLib::getCli($backtrace, TRUE);

			}

		}

		return $text;
	}

	/**
	 * Show the failed tests only, without colours
	 *
	 */
	public function showSummary(): string {

		$text = get_class($this).', '.$this->ko.' '.($this->ko === 1 ? 'failure' : 'failures')."\n";

		foreach($this->trace as [$valid, $compareTo, $equality, $value, $message, , $method, $line]) {

			$text .= ' '.($method === '' ? 'outside a test method' : $method.'()').' in '.$line."\n";

			if($valid !== NULL) {
				if($equality === TRUE) {
					$text .= "  * Expected: ".var_export($compareTo, TRUE)."\n";
					$text .= "  * Received: ".var_export($value, TRUE)."\n";
				} else {
					$text .= "  * Unexpected: ".var_export($compareTo, TRUE)."\n";
				}
			}

			if($message !== NULL) {
				$text .= "  * Message: ".$message."\n";
			}

		}

		return $text;

	}

	/**
	 * The first line of a backtrace that lies in a test file, as path:line
	 */
	private function getTestLine(array $backtrace): string {

		foreach($backtrace as $entry) {

			if(str_ends_with($entry['file'] ?? '', '.t.php')) {
				return substr($entry['file'], strlen(LIME_DIRECTORY) + 1).':'.$entry['line'];
			}

		}

		return '?';

	}

	/**
	 * Convert PHP errors into an Exception.
	 *
	 * @param int $code
	 * @param string $message
	 * @param string $file
	 * @param string $line
	 * @throws Exception If the error is of type E_ERROR or E_ERROR, the exception is thrown.
	 */
	public function handleError(int $code, string $message, string $file, string $line) {

		switch($code) {
			case E_ERROR :
			case E_USER_ERROR :
				throw new \Exception($message);
			default :
				$this->errorException = new \Exception($message);
		}

	}

}
?>
