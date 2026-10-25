<?php
namespace dev;

/**
 * Run tests
 */
class TestLib {

	const FILE = 'file';
	const METHOD = 'method';

	public static int $files = 0;
	public static int $classes = 0;
	public static int $missingFixtures = 0;
	public static int $notApplicable = 0;
	public static int $assertions = 0;
	public static int $warnings = 0;

	public static int $warningsInFailedTests = 0;

	public static array $warningsBySource = [];
	public static array $warningsInFailedTestsBySource = [];
	public static array $warningsWithoutSource = [];
	public static array $missingFixtureClasses = [];
	public static array $notApplicableClasses = [];

	public static bool $summary = FALSE;

	/**
	 * Nombre total d'échecs et d'exceptions de la campagne, pour que le lanceur sorte en code non nul.
	 */
	public static int $failures = 0;

	/**
	 * Le test en cours, que la passe nomme si PHP l'arrête sur une erreur fatale.
	 */
	public static ?string $running = NULL;

	private static bool $countingErrors = FALSE;

	private static array $resets = [
		self::FILE => [],
		self::METHOD => [],
	];

	public static function addReset(\Closure $reset, string $scope = self::FILE): void {

		if(array_key_exists($scope, self::$resets) === FALSE) {
			throw new \Exception('Unknown reset scope \''.$scope.'\'');
		}

		self::$resets[$scope][] = $reset;

	}

	public static function addWarning(string $message, ?string $source = NULL): void {

		if($source === NULL) {

			echo "\033[33mWarning: ".$message."\033[00m".PHP_EOL;

			self::$warningsWithoutSource[$message] = (self::$warningsWithoutSource[$message] ?? 0) + 1;

		} else {

			echo "\033[33mWarning: ".$message." (".$source.")\033[00m".PHP_EOL;

			$key = $source.' '.$message;
			self::$warningsBySource[$key] = (self::$warningsBySource[$key] ?? 0) + 1;

		}

		self::$warnings++;

	}

	public static function reset(string $scope): void {

		foreach(self::$resets[$scope] as $reset) {
			$reset();
		}

	}

	/**
	 * Run tests
	 *
	 * @param \ReflectionPackage $package
	 * @param string $selectedFile Selected file, a shell pattern (* for all files, *Sentinel* for
	 *                             sentinels), or a comma separated list of either
	 */
	public static function run(\ReflectionPackage $package, string $selectedFile) {

		$patterns = explode(',', $selectedFile);

		// Un run() lancé depuis un test (TestRunnerTest) trouve le gestionnaire déjà posé : en poser
		// un second compterait chaque notice deux fois.
		$countingErrors = (self::$countingErrors === FALSE);

		if($countingErrors) {

			self::$countingErrors = TRUE;

			// Le gestionnaire global imprime une notice sans la compter : on la compte, puis on lui
			// rend la main pour garder sa pile d'appels. assertError() empile le sien par-dessus.
			$previous = set_error_handler(function(int $code, string $message, string $file, int $line) use(&$previous) {

				if(error_reporting() & $code) {
					self::addWarning($message, substr($file, strlen(LIME_DIRECTORY) + 1).':'.$line);
				}

				if($previous === NULL) {
					return FALSE;
				}

				return $previous($code, $message, $file, $line);

			});

		}

		$running = self::$running;

		try {
			self::browse($package, $patterns);
		} finally {

			self::$running = $running;

			if($countingErrors) {
				restore_error_handler();
				self::$countingErrors = FALSE;
			}

		}

	}

	/**
	 * Les fichiers que run() jouerait dans ce package, dans son ordre, sans ceux qui ne portent
	 * qu'une classe abstraite : run() les compte sans rien y jouer, et un processus lancé pour l'un
	 * d'eux ne rendrait aucune classe.
	 *
	 * Un fichier dont la classe n'a pas le nom attendu reste dans la liste, pour que run() le dise en
	 * CRITICAL ERROR comme dans une passe série.
	 *
	 * @return string[] Les noms des fichiers, sans `.t.php`
	 */
	public static function getFiles(\ReflectionPackage $package, string $selectedFile): array {

		$patterns = explode(',', $selectedFile);
		$files = [];

		$package->browse('test/', '.t.php', function($file) use($package, $patterns, &$files) {

			$basename = substr($file->getBasename(), 0, -6);

			if(self::isSelected($basename, $patterns) === FALSE) {
				return;
			}

			require_once $file->getPathname();

			$class = $package->getPackageName().'\\'.$basename.'Test';

			if(class_exists($class, FALSE) and new \ReflectionClass($class)->isAbstract()) {
				return;
			}

			$files[] = $basename;

		});

		return $files;

	}

	private static function isSelected(string $basename, array $patterns): bool {

		foreach($patterns as $pattern) {

			if(fnmatch($pattern, $basename)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	private static function browse(\ReflectionPackage $package, array $patterns): void {

		$package->browse('test/', '.t.php', function($file) use($package, $patterns) {

			$basename = substr($file->getBasename(), 0, -6);

			if(self::isSelected($basename, $patterns) === FALSE) {
				return;
			}

			self::$files++;

			$class = $package->getPackageName().'\\'.$basename.'Test';
			$running = substr($file->getPathname(), strlen(LIME_DIRECTORY) + 1).', '.$class;
			$report = '';

			self::$running = $running;

			require_once $file->getPathname();

			ob_start();

			try {

				$reflection = new \ReflectionClass($class);

				if($reflection->isAbstract()) {
					return;
				}

				$methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

				$instance = $reflection->newInstance();

				self::reset(self::FILE);

				self::$running = $running.'::init()';

				try {
					$instance->init();
				} catch(NotApplicableException $e) {
					$report = self::addNotApplicable($reflection->getName(), $e);
					return;
				} catch(\Throwable $e) {
					echo "\033[31mMissing fixture: ".$reflection->getName().", ".$e->getMessage()."\033[00m".PHP_EOL;
					self::$missingFixtures++;
					self::$missingFixtureClasses[] = $reflection->getName();
					$report = 'Missing fixture: '.$reflection->getName().', '.$e->getMessage().PHP_EOL.PHP_EOL;
					return;
				}

				self::$classes++;

				$notApplicable = NULL;

				foreach($methods as $method) {

					$methodName = $method->getName();

					if(substr($methodName, 0, 4) === 'test') {

						$instance->setTestMethod($methodName);

						self::$running = $running.'::'.$methodName.'()';

						$koBefore = $instance->getKo();
						$warningsBefore = self::$warnings;
						$warningsBySourceBefore = self::$warningsBySource;

						try {

							self::reset(self::METHOD);

							$instance->setUp();
							$instance->$methodName();

						} catch(NotApplicableException $e) {
							$notApplicable = $e;
						} catch(\Throwable $e) {
							// \Throwable et non \Exception : une Error (TypeError, appel sur NULL…) est aussi
							// un échec du test, pas un arrêt de la campagne. Elle est comptée comme tel.
							$instance->reportException($methodName, $e);
						} finally {
							// tearDown TOUJOURS, sinon la transaction du test n'est pas annulée et les
							// données fuient dans la base partagée.
							$instance->tearDown();
							\Clock::reset();
							$instance->setTestMethod('');
						}

						if($instance->getKo() > $koBefore) {
							self::addWarningsInFailedTest($warningsBefore, $warningsBySourceBefore);
						}

						// La ressource qui manque à une méthode manque aussi aux suivantes.
						if($notApplicable !== NULL) {
							break;
						}

					}
				}

				self::$running = $running;

				$instance->finalize();

				echo $instance->show();

				if($instance->getKo() > 0) {
					$report = $instance->showSummary().PHP_EOL;
				}

				// Commencée, la classe reste comptée avec ses assertions passées, et se nomme à part
				// pour ce qu'elle n'a pas joué.
				if($notApplicable !== NULL) {
					$report .= self::addNotApplicable($reflection->getName(), $notApplicable);
				}

				self::$assertions += $instance->getOk() + $instance->getKo();
				self::$failures += $instance->getKo();

			} catch(\Throwable $e) {

				echo "\033[31mCRITICAL ERROR: ".$class.", ".$e->getMessage()."\033[00m".PHP_EOL;

				$report = 'CRITICAL ERROR: '.$class.', '.$e->getMessage().PHP_EOL.PHP_EOL;
				self::$failures++;

			} finally {

				self::$running = NULL;

				$output = ob_get_clean();

				echo self::$summary ? $report : $output;

			}

		});

	}

	/**
	 * PHP sort en 255 d'une erreur fatale sans que la ligne Total soit écrite : la passe dit donc
	 * qu'elle n'a pas de verdict, sur quel test elle s'est arrêtée, et l'état du fichier en cause
	 * quand l'arbre le porte modifié ou non suivi.
	 */
	public static function watchFatalError(): void {

		// Route consigne l'erreur avant que la passe la dise, et charge pour cela des classes que la
		// ligne de commande compile sans opcache : la réserve couvre les deux.
		\Route::reserveMemory(2 * 1024 * 1024);

		register_shutdown_function(function() {

			$error = error_get_last();
			$text = self::getFatalErrorText($error);

			if($text !== NULL) {

				// L'arrêt est écrit avant que git soit lu : un git qui ne répondrait pas ne doit pas le taire.
				fwrite(STDOUT, $text);

				$path = substr($error['file'], strlen(LIME_DIRECTORY) + 1);

				// Sans `-uall`, un fichier d'un répertoire non suivi se rend sous le nom du répertoire, et la
				// ligne ne nommerait plus le fichier.
				fwrite(STDOUT, self::getChangedFileText($path, SentinelLib::getGitPaths('status', '--porcelain', '--untracked-files=all', '--', $path)));

			}

		});

	}

	/**
	 * PHP tient les en-têtes pour envoyés dès la première sortie, même en ligne de commande, et
	 * Action::setStatusCode() les pose ensuite dans ApiTest::callApi(). Ce qu'un tampon retient se perd
	 * quand la passe meurt d'une mémoire épuisée ou qu'on la tue : il écrit donc chaque sortie dès
	 * qu'elle arrive, sans rien rendre à PHP.
	 *
	 * @param resource $stream
	 */
	public static function startOutput($stream = STDOUT): void {

		ob_start(function(string $output) use($stream): string {
			fwrite($stream, $output);
			return '';
		}, 1);

	}

	public static function getFatalErrorText(?array $error): ?string {

		if($error === NULL or ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) === 0) {
			return NULL;
		}

		$stop = 'FATAL ERROR: the run stopped without a verdict, '.(self::$running === NULL ? 'outside any test' : 'in '.self::$running).'.';

		if(self::$summary === FALSE) {
			$stop = "\033[31m".$stop."\033[00m";
		}

		$cause = $error['message'].' in '.substr($error['file'], strlen(LIME_DIRECTORY) + 1).':'.$error['line'];

		if(str_starts_with($error['message'], 'Allowed memory size')) {
			$cause .= ' (memory_limit '.ini_get('memory_limit').')';
		}

		return $stop.PHP_EOL.$cause.PHP_EOL;

	}

	/**
	 * Une erreur fatale levée par un fichier que l'arbre porte modifié ou non suivi vient souvent d'une
	 * session voisine qui l'écrit encore, un `.e.php` posé avant son `canRead()`. La passe nomme le
	 * fichier, son état et l'heure de sa dernière écriture, et dit quand relancer, sans conclure que le
	 * fichier est hors du lot de qui l'a lancée : elle ne connaît pas ce lot.
	 *
	 * @param string[]|null $status Les lignes de `git status --porcelain` sur ce seul fichier, NULL quand git n'a pas répondu.
	 */
	public static function getChangedFileText(string $path, ?array $status): string {

		if($status === NULL or $status === []) {
			return '';
		}

		$code = substr($status[0], 0, 2);

		return $path.' is '.($code === '??' ? 'untracked' : 'changed').' in the working tree (git status "'.$code.'"), last written on '.date('Y-m-d H:i:s', filemtime(LIME_DIRECTORY.'/'.$path)).'. If another session is still writing it, run the tests again.'.PHP_EOL;

	}

	private static function addNotApplicable(string $class, NotApplicableException $e): string {

		echo "\033[33mNot applicable: ".$class.", ".$e->getMessage()."\033[00m".PHP_EOL;

		self::$notApplicable++;
		self::$notApplicableClasses[] = $class.' ('.$e->getMessage().')';

		return 'Not applicable: '.$class.', '.$e->getMessage().PHP_EOL.PHP_EOL;

	}

	public static function finish(float $start): int {

		$total = 'Total: '.implode(', ', array_filter([
			self::getCountText(self::$classes, 'class', 'classes'),
			self::getCountText(self::$missingFixtures, 'missing fixture', 'missing fixtures'),
			// Absent quand tout s'applique, pour que la ligne Total d'une passe ordinaire ne change pas.
			self::$notApplicable > 0 ? self::$notApplicable.' not applicable' : NULL,
			self::getCountText(self::$assertions, 'assertion', 'assertions'),
			self::getCountText(self::$warnings, 'warning', 'warnings').self::getFailedTestsText(self::$warningsInFailedTests),
			self::getCountText(self::$failures, 'failure', 'failures'),
			round(microtime(TRUE) - $start).' s',
		])).PHP_EOL;

		arsort(self::$warningsBySource);

		$warnings = [];

		foreach(self::$warningsBySource as $source => $count) {
			$warnings[] = $count." × ".$source.self::getFailedTestsText(self::$warningsInFailedTestsBySource[$source] ?? 0);
		}

		if(self::$summary) {

			arsort(self::$warningsWithoutSource);

			foreach(self::$warningsWithoutSource as $message => $count) {
				$warnings[] = $count.' × '.$message;
			}

			if($warnings !== []) {
				echo implode(PHP_EOL, $warnings).PHP_EOL.PHP_EOL;
			}

			echo $total;

			if(self::$notApplicableClasses !== []) {
				echo 'Not run, not applicable here: '.implode(', ', self::$notApplicableClasses).PHP_EOL;
			}

		} else {

			echo PHP_EOL.$total;

			if(self::$missingFixtureClasses !== []) {
				echo "\033[31mNot run, missing fixture: ".implode(', ', self::$missingFixtureClasses)."\033[00m".PHP_EOL;
			}

			if(self::$notApplicableClasses !== []) {
				echo "\033[33mNot run, not applicable here: ".implode(', ', self::$notApplicableClasses)."\033[00m".PHP_EOL;
			}

			foreach($warnings as $warning) {
				echo "\033[33m".$warning."\033[00m".PHP_EOL;
			}

		}

		if(self::$failures > 0 or self::$missingFixtures > 0) {
			return 1;
		}

		if(self::$classes === 0) {
			return 2;
		}

		return 0;

	}

	/**
	 * Ce que finish() lit pour écrire la ligne Total et le code de sortie, pour qu'un processus le
	 * remette à celui qui l'a lancé (TestJobLib).
	 */
	public static function getCounters(): array {

		return [
			'files' => self::$files,
			'classes' => self::$classes,
			'missingFixtures' => self::$missingFixtures,
			'notApplicable' => self::$notApplicable,
			'assertions' => self::$assertions,
			'warnings' => self::$warnings,
			'warningsInFailedTests' => self::$warningsInFailedTests,
			'failures' => self::$failures,
			'warningsBySource' => self::$warningsBySource,
			'warningsInFailedTestsBySource' => self::$warningsInFailedTestsBySource,
			'warningsWithoutSource' => self::$warningsWithoutSource,
			'missingFixtureClasses' => self::$missingFixtureClasses,
			'notApplicableClasses' => self::$notApplicableClasses,
		];

	}

	/**
	 * Ajoute les compteurs d'un autre processus à ceux de la passe, comme si ses fichiers avaient été
	 * joués ici : les avertissements d'une même source s'additionnent en une seule ligne.
	 */
	public static function addCounters(array $counters): void {

		self::$files += $counters['files'];
		self::$classes += $counters['classes'];
		self::$missingFixtures += $counters['missingFixtures'];
		self::$notApplicable += $counters['notApplicable'];
		self::$assertions += $counters['assertions'];
		self::$warnings += $counters['warnings'];
		self::$warningsInFailedTests += $counters['warningsInFailedTests'];
		self::$failures += $counters['failures'];

		foreach($counters['warningsBySource'] as $source => $count) {
			self::$warningsBySource[$source] = (self::$warningsBySource[$source] ?? 0) + $count;
		}

		foreach($counters['warningsInFailedTestsBySource'] as $source => $count) {
			self::$warningsInFailedTestsBySource[$source] = (self::$warningsInFailedTestsBySource[$source] ?? 0) + $count;
		}

		foreach($counters['warningsWithoutSource'] as $message => $count) {
			self::$warningsWithoutSource[$message] = (self::$warningsWithoutSource[$message] ?? 0) + $count;
		}

		self::$missingFixtureClasses = array_merge(self::$missingFixtureClasses, $counters['missingFixtureClasses']);
		self::$notApplicableClasses = array_merge(self::$notApplicableClasses, $counters['notApplicableClasses']);

	}

	private static function addWarningsInFailedTest(int $warningsBefore, array $warningsBySourceBefore): void {

		self::$warningsInFailedTests += self::$warnings - $warningsBefore;

		foreach(self::$warningsBySource as $source => $count) {

			$raised = $count - ($warningsBySourceBefore[$source] ?? 0);

			if($raised > 0) {
				self::$warningsInFailedTestsBySource[$source] = (self::$warningsInFailedTestsBySource[$source] ?? 0) + $raised;
			}

		}

	}

	private static function getFailedTestsText(int $count): string {
		return $count > 0 ? ' ('.$count.' in failed tests)' : '';
	}

	private static function getCountText(int $count, string $singular, string $plural): string {
		return $count.' '.($count === 1 ? $singular : $plural);
	}

}

/**
 * Levée par `SentinelTest::notApplicable()` : la ressource qu'un test lit hors du code manque ici.
 */
class NotApplicableException extends \Exception {
}
?>
