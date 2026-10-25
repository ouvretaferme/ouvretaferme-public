<?php
new Page()
	/**
	 * Build a DIA environment in the specified package
	 *
	 */
	->cli('index', function($data) {

		$flags = strtoupper(GET('flags'));
		$selectedModule = GET('module');
		$selectedPackage = GET('package');

		// Les refus de cette page sortent en code 2, comme ceux de lime.php : un code 0 les ferait passer
		// pour réussis auprès de qui ne lit que le code de sortie.
		if($flags === '') {
			echo "Error: Argument flags=? is missing (type ".LIME_REQUEST_PATH.":help)\n";
			exit(2);
		}

		if(str_contains($flags, 'B') or str_contains($flags, 'T') or str_contains($flags, 'D')) {

			if(
				$selectedPackage === '' or
				(ctype_alnum($selectedPackage) === FALSE and $selectedPackage !== '*')
			) {
				echo "Error: Argument package=? is required with flags B, T, D (type ".LIME_REQUEST_PATH.":help)\n";
				exit(2);
			}

			if(
				$selectedModule === '' or
				(ctype_alnum($selectedModule) === FALSE and $selectedModule !== '*')
			) {
				echo "Error: Argument module=? is required with flags B, T, D (type ".LIME_REQUEST_PATH.":help)\n";
				exit(2);
			}

		} else if($flags === 'E') {

			if($selectedModule === '') {
				$selectedModule = '*';
			}

			if($selectedPackage === '') {
				$selectedPackage = '*';
			}

		}

		if(str_contains($flags, 'B') or str_contains($flags, 'T') or str_contains($flags, 'D')) {
			\dev\TestLockLib::take(\dev\TestLockLib::SCHEMA, 'package='.$selectedPackage.' module='.$selectedModule.' flags='.$flags);
		}

		$libModule = new dev\ModuleLib();
		$libModule->load();

		$classes = $libModule->getClasses();

		if($classes === []) {
			echo "Error: No valid class selected\n";
			exit(2);
		}

		$found = 0;
		$failed = 0;

		if(strpos($flags, 'E') !== FALSE) {

			// La borne package=/module= vaut pour tout mélange de drapeaux, flags=EB compris : sur un arbre
			// partagé, régénérer hors de la borne écrase les modèles des autres sessions. Elle borne au
			// module, pas à la colonne : buildModule() écrit l'entité entière telle que le .yml la porte,
			// donc une colonne qu'une autre session y a posée ou retirée part dans le .m.php, puis dans la
			// table avec B.
			$retained = [];

			foreach($classes as $class) {

				list($package, $module) = explode('\\', $class);

				if($selectedPackage !== '*' and $selectedPackage !== $package) {
					continue;
				}

				if($selectedModule !== '*' and $selectedModule !== $module) {
					continue;
				}

				$retained[] = $class;

			}

			echo 'Régénération de '.count($retained).' modèle(s).'."\n";

			$found += count($retained);
			$failed += $libModule->buildModules($retained);

		}

		$actions = [
			'D' => 'finalize',
			'T' => 'init',
			'B' => 'rebuild'
		];

		foreach($actions as $flag => $action) {

			if(strpos($flags, $flag) !== FALSE) {

				foreach($classes as $class) {

					list($package, $module) = explode('\\', $class);

					if($selectedPackage !== '*' and $selectedPackage !== $package) {
						continue;
					}

					if($selectedModule !== '*' and $selectedModule !== $module) {
						continue;
					}

					$output = \dev\SystemLib::command(LIME_APP, '-e '.LIME_ENV.' dev/module:'.$action.' package='.$package.' module='.$module, $code);

					echo implode("\n", $output)."\n";
					$found++;

					if($code !== 0) {
						$failed++;
					}

				}

			}
		}

		if($found === 0) {
			echo "Error: No class found\n";
			exit(2);
		}

		// Chaque module est tenté, et l'échec de l'un ne retient pas les suivants : le code ne se pose qu'à la fin.
		if($failed > 0) {
			exit(1);
		}

	})
	/**
	 * Display help
	 */
	->cli('help', function($data) {

		echo "Usage: dev/module package=[package name] module=[module name] ...\n";
		echo "\n";
		echo "DESCRIPTION\n";
		echo "	Build modules from a Yaml file.\n";
		echo "\n";
		echo "OPTIONS\n";
		echo "	package=[package name]\n";
		echo "		The name of the package (use * for all packages).\n";
		echo "		This option is MANDATORY for DTB flags.\n";
		echo "	module=[module name]\n";
		echo "		Limit the build to the specified module (use * for all modules).\n";
		echo "		This option is MANDATORY for DTB flags.\n";
		echo "	flags=MDTB\n";
		echo "		E : build element / query files (automatically build files for all packages and all modules).\n";
		echo "		D : drop module table.\n";
		echo "		T : create module table.\n";
		echo "		B : rebuild module table.\n";

	})
	/**
	 * Init, finalize et rebuild tournent chacun dans le processus que l'index lance par SystemLib::command() :
	 * un échec y sort en code 1, que l'index lit pour sortir lui-même en non nul, sans quoi une migration
	 * de schéma qui n'a rien joué passerait pour jouée.
	 */
	->cli('init', function($data) {

		$package = GET('package');
		$module = GET('module');

		try {

			(new ModuleAdministration($package.'\\'.$module))->init();

		} catch(Exception $e) {
			echo "Error: Processing error for '".$module."' (".str_replace("\n", " ", $e->getMessage()).")\n";
			exit(1);
		}

		throw new LineAction('Init '.$module.': OK');

	})
	->cli('finalize', function($data) {

		$package = GET('package');
		$module = GET('module');

		try {

			(new ModuleAdministration($package.'\\'.$module))->finalize();

		} catch(Exception $e) {
			echo "Error: Processing error for '".$module."' (".str_replace("\n", " ", $e->getMessage()).")\n";
			exit(1);
		}

		throw new LineAction('Finalize '.$module.': OK');

	})
	->cli('rebuild', function($data) {

		$package = GET('package');
		$module = GET('module');
		$default = GET('default', 'array');

		try {

			(new ModuleAdministration($package.'\\'.$module))->rebuild($default);

		} catch(Exception $e) {
			echo "Error: Processing error for '".$module."' (".str_replace("\n", " ", $e->getMessage()).")\n";
			exit(1);
		}

		throw new LineAction('Rebuild '.$module.': OK');

	});
?>