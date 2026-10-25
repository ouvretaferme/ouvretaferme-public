<?php
namespace dev;

/**
 * Deux commandes de développement qui échouent sortent en code non nul, pas 0 : une migration de
 * schéma ou une passe de tests qui n'a rien joué passeraient pour réussies auprès de ce qui ne lit
 * que le code de sortie.
 *
 * - `dev/module:init`, `:finalize` et `:rebuild` sortent en 1 sur un « Processing error », que l'index
 *   de `dev/module` lit pour sortir en 1 à son tour.
 * - La régénération d'un modèle (`flags=E`) compte ses échecs, qui font sortir l'index en 1.
 * - L'attente du verrou de `TestLockLib` sort en 2 au-delà de son délai, et s'inscrit au journal.
 * - `dev/test:schema` sort en 2 sans libellé, sans commandes ou au-delà de son délai, et sinon avec le
 *   code des commandes jouées.
 * - `dev/test:schema` ne rend pas son jeton actif ni ne joue rien tant qu'un autre processus tient le verrou de
 *   `TestLockLib::take()`, sans quoi une passe lancée au même instant passerait son attente avec lui.
 * - Un changement de schéma qui attend retient une passe neuve, mais pas un processus d'une passe jobs=N
 *   déjà lancée, qui ferait sinon attendre le schéma, son parent et lui-même les uns derrière les autres.
 * - L'export de `language/do` sort en 1 quand une exception l'interrompt, avec le même JSON.
 *
 * Chaque cas lance un autre processus, sauf la régénération, éprouvée dans la passe sur un modèle qui
 * n'existe pas. Le module demandé n'existe pas, ou sa table existe déjà et la création échoue, donc
 * rien n'est écrit en base ; le verrou attendu est le jeton `test` que la passe en cours pose pour
 * elle-même ; l'export échoue sur le premier fichier qu'il lit, avant d'écrire.
 *
 * Lancement : dev/test package=dev file=CliFailure
 */
class CliFailureTest extends \Test {

	const MODULE = 'cliFailureNowhere';

	public function testModuleProcessingError(): void {

		foreach(['init', 'finalize', 'rebuild'] as $action) {

			[$code, $output] = $this->runProcess(['dev/module:'.$action, 'package='.self::MODULE, 'module='.self::MODULE]);

			$this->assertEquals(1, $code, 'dev/module:'.$action.' doit sortir en code 1 sur un échec');
			$this->assertTrue(str_contains($output, 'Error: Processing error for \''.self::MODULE.'\''), 'la sortie doit toujours nommer le module en échec : '.$output);

		}

	}

	/**
	 * L'index attend que plus aucune passe de tests ne tienne de jeton avant de toucher au schéma, et la
	 * passe en cours en tient un : il lit donc ses jetons dans un répertoire temporaire à lui, vide. La
	 * table de `dev\Error` existe déjà, si bien que `flags=T` échoue sur sa création sans rien écrire.
	 */
	public function testModuleIndexFailure(): void {

		$mError = \dev\Error::model();

		if($this->assertDecorPresent($mError->pdo()->manager->hasTable($mError->getDatabase(), $mError->getTable()), 'la table de dev\Error existe, sans quoi flags=T la créerait') === FALSE) {
			return;
		}

		$directory = sys_get_temp_dir().'/otf-cli-failure-'.getmypid();
		mkdir($directory);

		[$code, $output] = $this->runProcess(['dev/module', 'flags=T', 'package=dev', 'module=Error'], ['TMPDIR' => $directory]);

		array_map(unlink(...), glob($directory.'/otf-dev-test/*') ?: []);
		rmdir($directory.'/otf-dev-test');
		rmdir($directory);

		$this->assertEquals(1, $code, 'dev/module doit sortir en code 1 quand l\'un des modules a échoué');
		$this->assertTrue(str_contains($output, 'Error: Processing error for \'Error\''), 'la sortie doit toujours nommer le module en échec : '.$output);

	}

	public function testModuleBuildError(): void {

		$saveError = ErrorPhpLib::$saveError;
		ErrorPhpLib::saveError(FALSE);

		ob_start();
		$failed = new ModuleLib()->buildModules(['dev\\'.self::MODULE]);
		$output = ob_get_clean();

		ErrorPhpLib::saveError($saveError);

		$this->assertEquals(1, $failed, 'un modèle qui ne se régénère pas doit compter pour un échec');
		$this->assertTrue(str_contains($output, 'dev\\'.self::MODULE.': '), 'la sortie doit toujours nommer le modèle en échec : '.$output);

	}

	public function testModuleHelp(): void {

		[$code] = $this->runProcess(['dev/module:help']);

		$this->assertEquals(0, $code, 'la même page sans échec doit sortir en code 0, sinon 1 ne prouve rien');

	}

	/**
	 * Les attentes provoquées ici s'inscriraient au journal du poste comme de vraies attentes : elles lisent
	 * leurs jetons dans un répertoire temporaire à elles, où seul celui de la passe en cours est recopié.
	 */
	public function testLockTimeout(): void {

		$directory = $this->createLockDirectory();

		if($this->assertDecorPresent($directory !== NULL, 'la passe en cours tient un jeton test') === FALSE) {
			return;
		}

		[$code, $output] = $this->runProcess(['-r', '\dev\TestLockLib::wait(\dev\TestLockLib::TEST, 0);'], ['TMPDIR' => $directory]);
		$journal = $this->readJournal($directory);

		$this->removeLockDirectory($directory);

		$this->assertEquals(2, $code, 'une attente du verrou au-delà de son délai doit sortir en code 2');
		$this->assertTrue(str_contains($output, 'Error: still waiting after 0 s for test'), 'la sortie doit toujours nommer ce qui est attendu : '.$output);

		$this->assertEquals(1, count($journal), 'une attente abandonnée doit s\'inscrire au journal, une fois');
		$this->assertTrue($journal[0]['kind'] === TestLockLib::TEST and $journal[0]['timeout'] and str_contains(implode(' ', $journal[0]['for']), 'pid '.getmypid().','), 'la ligne doit nommer la passe qui retenait l\'attente : '.json_encode($journal));

	}

	public function testSchemaRefusal(): void {

		[$code, $output] = $this->runProcess(['dev/test:schema'], input: 'true');
		$this->assertEquals(2, $code, 'dev/test:schema sans libellé doit sortir en code 2 : '.$output);

		[$code, $output] = $this->runProcess(['dev/test:schema', 'label='.self::MODULE]);
		$this->assertEquals(2, $code, 'dev/test:schema sans commandes doit sortir en code 2 : '.$output);

		$directory = $this->createLockDirectory();

		if($this->assertDecorPresent($directory !== NULL, 'la passe en cours tient un jeton test') === FALSE) {
			return;
		}

		[$code, $output] = $this->runProcess(['dev/test:schema', 'label='.self::MODULE, 'timeout=0'], ['TMPDIR' => $directory], 'echo '.self::MODULE.'Played');

		$this->removeLockDirectory($directory);

		$this->assertEquals(2, $code, 'dev/test:schema au-delà de son délai doit sortir en code 2');
		$this->assertFalse(str_contains($output, self::MODULE.'Played'), 'une attente abandonnée ne joue rien : '.$output);

	}

	/**
	 * Le test tient lui-même le verrou dans un répertoire temporaire sans jeton : rien n'est attendu, et
	 * seul le verrou peut retenir dev/test:schema, qui sans lui jouerait et sortirait dans la seconde.
	 */
	public function testTakeUnderMutex(): void {

		$directory = $this->createEmptyLockDirectory();

		$mutex = fopen($directory.'/otf-dev-test/'.TestLockLib::MUTEX, 'c');
		flock($mutex, LOCK_EX);

		// Le tableau s'écrit en littéral pour que ShellCommandSentinel y lise la forme sans shell.
		$process = proc_open([PHP_BINARY, LIME_DIRECTORY.'/framework/lime.php', '-a', LIME_APP, '-e', LIME_ENV, 'dev/test:schema', 'label='.self::MODULE], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['TMPDIR' => $directory] + getenv());

		fwrite($pipes[0], 'echo '.self::MODULE.'Played');
		fclose($pipes[0]);

		sleep(1);

		$running = proc_get_status($process)['running'];
		$tokens = array_filter(glob($directory.'/otf-dev-test/*.json') ?: [], fn($file) => json_decode(file_get_contents($file), TRUE)['waiting'] === FALSE);

		flock($mutex, LOCK_UN);
		fclose($mutex);

		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		$code = proc_close($process);

		$this->removeLockDirectory($directory);

		$this->assertTrue($running, 'dev/test:schema doit rester en attente tant qu\'un autre processus tient le verrou');
		$this->assertEquals([], $tokens, 'aucun jeton actif ne doit être posé tant qu\'un autre processus tient le verrou');
		$this->assertEquals(0, $code, 'le verrou relâché, dev/test:schema doit jouer et sortir en 0 : '.$output);
		$this->assertTrue(str_contains($output, self::MODULE.'Played'), 'le verrou relâché, les commandes doivent être jouées : '.$output);

	}

	/**
	 * Le jeton en attente est posé au nom de la passe en cours, vivante, et le processus lancé n'en a pas
	 * d'autre : il est une passe neuve.
	 */
	public function testReservationHoldsNewPass(): void {

		$directory = $this->createEmptyLockDirectory();
		$this->writeToken($directory, getmypid(), TestLockLib::SCHEMA, TRUE);

		[$code, $output] = $this->runProcess(['-r', '\dev\TestLockLib::take(\dev\TestLockLib::TEST, "'.self::MODULE.'", 0);'], ['TMPDIR' => $directory]);

		$this->removeLockDirectory($directory);

		$this->assertEquals(2, $code, 'une passe neuve doit attendre derrière un changement de schéma en attente : '.$output);
		$this->assertTrue(str_contains($output, 'for schema en attente'), 'la sortie doit dire que le schéma attend : '.$output);

	}

	/**
	 * Le processus lancé a pour parent la passe en cours, qui tient ici un jeton `test` : il est l'un de
	 * ses processus. Le jeton en attente est posé au nom du parent de la passe, vivant lui aussi.
	 */
	public function testReservationAdmitsRunningPass(): void {

		$directory = $this->createEmptyLockDirectory();
		$this->writeToken($directory, getmypid(), TestLockLib::TEST, FALSE);
		$this->writeToken($directory, posix_getppid(), TestLockLib::SCHEMA, TRUE);

		[$code, $output] = $this->runProcess(['-r', '\dev\TestLockLib::take(\dev\TestLockLib::TEST, "'.self::MODULE.'", 0);'], ['TMPDIR' => $directory]);

		$this->removeLockDirectory($directory);

		$this->assertEquals(0, $code, 'un processus d\'une passe déjà lancée ne doit pas attendre un changement de schéma en attente : '.$output);

	}

	public function testPlay(): void {

		$this->assertEquals(0, TestLockLib::play('true'), 'des commandes qui réussissent rendent 0');
		$this->assertEquals(3, TestLockLib::play('exit 3'), 'le code de sortie des commandes est rendu tel quel');
		$this->assertEquals(1, TestLockLib::play("false\nexit 0"), 'une commande en échec arrête la suite, comme sous bash -e');
		$this->assertEquals(4, TestLockLib::play("cat > /dev/null\nexit 4"), 'une commande qui lit son entrée n\'avale pas la suite du script');
		$this->assertEquals(0, TestLockLib::play("[ \"a  b\" = 'a  b' ]"), 'les guillemets du script arrivent à bash tels qu\'ils sont écrits');

	}

	public function testLockFree(): void {

		[$code] = $this->runProcess(['-r', '\dev\TestLockLib::wait("cliFailureNowhere", 0);']);

		$this->assertEquals(0, $code, 'une attente sans jeton à attendre doit sortir en code 0, sinon 2 ne prouve rien');

	}

	/**
	 * L'export lit `lang/lastCustom` du paquet avant d'écrire quoi que ce soit : sans ce fichier, que pose
	 * `language/prepare`, il lève, et la page rend l'exception en JSON.
	 */
	public function testLanguageExportException(): void {

		$file = new \ReflectionPackage('main', LIME_APP)->getPath().'/lang/lastCustom';

		if($this->assertDecorPresent(is_file($file) === FALSE, 'le paquet main n\'a pas de lang/lastCustom, sans quoi l\'export écrirait ses fichiers') === FALSE) {
			return;
		}

		[$code, $output] = $this->runProcess(['language/do:export', 'package=main', 'langs=en_US']);

		$this->assertEquals(1, $code, 'language/do:export doit sortir en code 1 quand une exception l\'interrompt');
		$this->assertEquals(['exception' => 'File \''.$file.'\' can not be found. Please run \'language/prepare\' first'], json_decode($output, TRUE), 'la sortie doit rester le JSON de l\'exception : '.$output);

	}

	/**
	 * @return array Le code de sortie et la sortie standard
	 */
	private function runProcess(array $options, array $environment = [], ?string $input = NULL): array {

		// Le tableau s'écrit en littéral pour que ShellCommandSentinel y lise la forme sans shell.
		$process = proc_open([PHP_BINARY, LIME_DIRECTORY.'/framework/lime.php', '-a', LIME_APP, '-e', LIME_ENV, ...$options], [0 => $input === NULL ? ['file', '/dev/null', 'r'] : ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, $environment + getenv());

		if($input !== NULL) {
			fwrite($pipes[0], $input);
			fclose($pipes[0]);
		}

		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($process), $output];

	}

	/**
	 * Un répertoire temporaire où le jeton de la passe en cours est seul, ou NULL si elle n'en tient pas.
	 * Son pid restant vivant, le jeton recopié retient toute attente de la sorte `test`.
	 */
	private function createLockDirectory(): ?string {

		$token = TestLockLib::getDirectory().'/'.getmypid().'.json';

		if(is_file($token) === FALSE) {
			return NULL;
		}

		$directory = sys_get_temp_dir().'/otf-cli-failure-'.getmypid();
		mkdir($directory.'/otf-dev-test', 0777, TRUE);
		copy($token, $directory.'/otf-dev-test/'.getmypid().'.json');

		return $directory;

	}

	private function createEmptyLockDirectory(): string {

		$directory = sys_get_temp_dir().'/otf-cli-failure-'.getmypid();
		mkdir($directory.'/otf-dev-test', 0777, TRUE);

		return $directory;

	}

	private function writeToken(string $directory, int $pid, string $kind, bool $waiting): void {

		file_put_contents($directory.'/otf-dev-test/'.$pid.'.json', json_encode([
			'pid' => $pid,
			'kind' => $kind,
			'label' => self::MODULE,
			'waiting' => $waiting,
			'start' => \Clock::datetime(),
			'cwd' => getcwd()
		]));

	}

	private function removeLockDirectory(string $directory): void {

		array_map(unlink(...), glob($directory.'/otf-dev-test/*') ?: []);
		rmdir($directory.'/otf-dev-test');
		rmdir($directory);

	}

	/**
	 * Les attentes inscrites dans le répertoire temporaire du test, sans choisir le jour : la passe peut franchir minuit.
	 */
	private function readJournal(string $directory): array {

		$waits = [];

		foreach(glob($directory.'/otf-dev-test/waits-*.log') ?: [] as $file) {
			foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
				$waits[] = json_decode($line, TRUE);
			}
		}

		return $waits;

	}

}
?>
