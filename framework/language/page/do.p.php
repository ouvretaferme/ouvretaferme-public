<?php
/**
 * Import messages
 * php lime.php -a [package] language/import package=main langs=ru_RU
 *
 * @return Action
 */
(new Page(function($data) {

	$app = GET('app', 'string', LIME_APP);
	$package = GET('package');

	$package = new ReflectionPackage($package, $app);

	// Les refus de cette page sortent en code 2, comme ceux de lime.php, et un import ou un export
	// interrompu par une exception en code 1, comme un échec de dev/module : un code 0 les ferait passer
	// pour réussis auprès de qui ne lit que le code de sortie.
	if($package->exists() === FALSE) {
		echo "Error: Package '".$package->getPackageName()."' does not exist.\n";
		exit(2);
	}

	$data->package = $package;

	$langs = GET('langs');
	$langSource = GET('langSource', 'string', L::getDefaultLang());

	if($langs === '') {
		echo "Error: Missing selected languages langs=?\n";
		exit(2);
	} else if($langs === '*') {
		$langs = $data->package->getLangs();
	} else {

		$langs = explode(',', $langs);

		array_map(function($lang) {

			if(!preg_match('/^[a-z]{2}\\_[A-Z]{2}$/', $lang)) {
				echo "Error: Language '".$lang."' is invalid.\n";
				exit(2);
			}

		}, $langs);

	}

	if(!preg_match('/^[a-z]{2}\\_[A-Z]{2}$/', $langSource)) {
		echo "Error: Source language '".$langSource."' is invalid.\n";
		exit(2);
	}

	$data->langSource = $langSource;
	$data->langs = array_diff($langs, [$langSource]);

}))
	->cli('import', function($data) {

		// Create a new instance of the import and csv packages
		$libImport = new language\ImportLib($data->package);
		$libCsv = new language\CsvLib($data->package);

		$stats = [];
		$errors = [];
		$warnings = [];

		foreach($data->langs as $lang) {

			try {

				$messagesByPath = $libCsv->load($lang);

				$libImport->import($data->langSource, $lang, $messagesByPath);

			}
			catch(Exception $e) {

				$action = new JsonAction([
					'exception' => $e->getMessage(),
				]);
				$action->setExitCode(1);

				throw $action;

			}

			$stats[$lang] = $libImport->getStats();
			$errors[$lang] = $libImport->getErrors();
			$warnings[$lang] = $libImport->getWarnings();

		}

		throw new JsonAction([
			 'warnings' => $warnings,
			 'errors' => $errors,
			 'stats' => $stats
		]);

	})
	->cli('export', function($data) {

		// Create a new instance of the export and csv packages
		$libExport = new language\ExportLib($data->package);
		$libCsv = new language\CsvLib($data->package);

		$stats = [];

		foreach($data->langs as $lang) {

			try {

				$export = $libExport->export($data->langSource, $lang);

			}
			catch(Exception $e) {

				$action = new JsonAction([
					'exception' => $e->getMessage(),
				]);
				$action->setExitCode(1);

				throw $action;

			}

			$csv = $libCsv->create($lang, $export);
			$libCsv->save($lang, $csv);

			$stats[$lang] = $libExport->getStats();

		}

		throw new JsonAction($stats);

	});
?>
