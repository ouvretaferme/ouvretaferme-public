<?php

/**
 * Run test pages
 *
 */
new Page()
	->cli('index', function($data) {

		\dev\TestLib::addReset(fn() => \Clock::reset(), \dev\TestLib::FILE);
		\dev\TestLib::addReset(fn() => \Clock::reset(), \dev\TestLib::METHOD);

		$selectedFile = GET('file', 'string', '*');
		$selectedPackage = GET('package', 'string', '*');
		$jobs = GET('jobs', 'int', 1);
		$counters = GET('counters');

		\dev\TestLib::$summary = GET('summary', 'bool', stream_isatty(STDOUT) === FALSE);

		// Un refus sort en code 2, aucun test n'ayant tourné, et jamais par une exception : elle sortirait
		// en code 255, celui d'une passe arrêtée sans verdict, et non d'une passe qui n'a pas commencé.
		if($jobs < 1) {
			echo "Error: jobs must be a positive number\n";
			exit(2);
		}

		if($jobs > 1 and $selectedFile !== \dev\TestJobLib::PATTERN) {
			echo "Error: jobs only runs with file='".\dev\TestJobLib::PATTERN."', the other tests write in the database\n";
			exit(2);
		}

		if($selectedPackage === '*') {

			$packages = [];

			foreach(Package::getList() as $package => $app) {
				$packages[] = new ReflectionPackage($package, $app);
			}

		} else if(Package::exists($selectedPackage)) {
			$packages = [new ReflectionPackage($selectedPackage, Package::getApp($selectedPackage))];
		} else {
			echo "Error: Package '".$selectedPackage."' does not exist\n";
			exit(2);
		}

		foreach($packages as $package) {

			if($package->exists() === FALSE) {
				echo "Error: Package '".$package->getPackageName()."' does not exist\n";
				exit(2);
			}

		}

		// Une passe répartie se reconnait dans dev/test:lock, ses processus nommant le sien.
		$label = 'package='.$selectedPackage.' file='.$selectedFile;

		if($jobs > 1) {
			$label .= ' jobs='.$jobs;
		}

		if($counters !== '') {
			$label .= ', in the run of pid '.posix_getppid();
		}

		\dev\TestLockLib::take(\dev\TestLockLib::TEST, $label);
		\dev\TestLib::watchFatalError();

		$start = microtime(TRUE);

		\dev\TestLib::startOutput();

		if($jobs > 1) {

			$files = [];

			foreach($packages as $package) {

				foreach(\dev\TestLib::getFiles($package, $selectedFile) as $file) {
					$files[] = [$package, $file];
				}

			}

			$arguments = array_diff_key($_GET, array_flip(['package', 'file', 'jobs']));
			$arguments['summary'] = (int)\dev\TestLib::$summary;

			// Un processus arrêté est nommé, par lui-même ou par TestJobLib, mais ses compteurs manquent :
			// la passe n'a pas de verdict, comme une passe série qu'une erreur fatale interrompt.
			if(\dev\TestJobLib::run($files, $jobs, $arguments)) {
				ob_end_flush();
				exit(255);
			}

		} else {

			foreach($packages as $package) {
				\dev\TestLib::run($package, $selectedFile);
			}

		}

		// Un processus lancé par jobs=N rend ses compteurs à celui qui l'a lancé, qui écrit seul la
		// ligne Total de la passe.
		if($counters !== '') {
			file_put_contents($counters, serialize(\dev\TestLib::getCounters()));
			ob_end_flush();
			return;
		}

		$code = \dev\TestLib::finish($start);

		// Une passe de sentinelles dit ce qu'elle n'a pas lu, à côté de son verdict (\dev\SentinelLib::getUntracked()).
		if($selectedFile === \dev\TestJobLib::PATTERN) {

			$untracked = \dev\SentinelLib::getUntracked();

			if($untracked === NULL) {
				echo 'Non lus : git ne répond pas, la liste des fichiers neufs écartés est inconnue.'."\n";
			} else if($untracked !== []) {

				echo 'Non lus : '.array_sum($untracked).' fichier(s) neuf(s) que git ne suit pas, écartés de l\'inventaire des sentinelles. `git add -N <chemin>` les y fait entrer, sans rien pousser :'."\n";

				foreach($untracked as $package => $count) {
					echo '  '.$package.' '.$count."\n";
				}

			}

		}

		ob_end_flush();

		if($code !== 0) {
			exit($code);
		}

	})
	/**
	 * List the test passes and schema changes running on this machine
	 */
	->cli('lock', function($data) {

		$live = \dev\TestLockLib::getLive();

		if($live === []) {
			echo "Aucune passe ni changement de schéma en cours.\n";
		}

		foreach($live as $token) {
			echo \dev\TestLockLib::describe($token)."\n";
		}

	})
	/**
	 * Wait for the running test passes, before a schema change made outside dev/module
	 */
	->cli('wait', function($data) {

		\dev\TestLockLib::wait(\dev\TestLockLib::TEST);

		echo "Aucune passe en cours.\n";

	})
	/**
	 * Play a schema change written by hand, read from the standard input, once the running test passes are over
	 */
	->cli('schema', function($data) {

		$label = GET('label');
		$script = stream_isatty(STDIN) ? '' : stream_get_contents(STDIN);

		if($label === '') {
			echo "Error: label is required, the name of the migration file whose block is played\n";
			exit(2);
		}

		if($script === '') {
			echo "Error: the commands to play are read from the standard input, which is empty\n";
			exit(2);
		}

		\dev\TestLockLib::take(\dev\TestLockLib::SCHEMA, $label, GET('timeout', 'int', \dev\TestLockLib::TIMEOUT));

		exit(\dev\TestLockLib::play($script));

	})
	/**
	 * Display help
	 */
	->cli('help', function($data) {

		echo "Usage: dev/test package=[package name] file=[file name] ...\n";
		echo "\n";
		echo "DESCRIPTION\n";
		echo "	Run test pages.\n";
		echo "\n";
		echo "OPTIONS\n";
		echo "	package=[package name]\n";
		echo "		The name of the package (use * for all packages).\n";
		echo "	file=[file name]\n";
		echo "		Limit the test to the specified file, or to the files matching a shell pattern.\n";
		echo "		file=*Sentinel* runs the sentinel tests only.\n";
		echo "		Several files or patterns can be separated by commas: file=DisposalAi,Resume.\n";
		echo "	jobs=[number]\n";
		echo "		Runs the files in that many processes at once, one process per file, with the same Total line\n";
		echo "		and exit status as a run in a single process. Only accepted with file='*Sentinel*', since the\n";
		echo "		other tests write in the database. Each process is limited to ".(\dev\TestJobLib::MEMORY / 1024)." MB of address space.\n";
		echo "	strict=1\n";
		echo "		A transaction left in an unexpected state fails the test instead of raising a warning.\n";
		echo "	summary=0|1\n";
		echo "		1 prints only the failed classes, the missing fixtures and the grouped warnings, without colours,\n";
		echo "		then the Total line. 0 prints every class. Defaults to 1 when the output is not a terminal.\n";
		echo "\n";
		echo "EXIT STATUS\n";
		echo "	0 if every test passed, 1 if a test failed or could not start, 2 if no test ran, including\n";
		echo "	an unknown package or a refused jobs argument.\n";
		echo "	255 if a fatal error such as an exhausted memory_limit stopped the run: no Total line,\n";
		echo "	two FATAL ERROR lines name the test that was running and the error.\n";
		echo "\n";
		echo "SCHEMA CHANGE\n";
		echo "	dev/test:schema label=[migration file] <<'JOUER' … JOUER\n";
		echo "		Waits for the running test passes, holds the schema token so that no pass starts meanwhile,\n";
		echo "		then plays the shell commands read from the standard input with bash -e.\n";
		echo "		Exits with their status, or 2 when the label or the commands are missing or the wait timed out.\n";
		echo "		timeout=[seconds] shortens the wait, ".\dev\TestLockLib::TIMEOUT." s by default.\n";
		echo "	dev/test:lock lists the passes and schema changes running on this machine.\n";

	});
?>
