<?php

/**
 * Run every exposed API module's end-to-end test in one go (dev\ApiTest subclasses), discovered
 * the same way as dev/apiDoc : ApiRoute::expose(...) calls in ouvretaferme/Lime.s.php.
 *
 * Convention assumed: for a module 'package\Module', the test lives in
 * <package>/test/<Module>Api.t.php (class package\<Module>ApiTest extends \dev\ApiTest), ou dans
 * <Module>ApiAi.t.php, le nom des tests écrits tant que CLAUDE.md suffixait les fichiers d'une IA.
 *
 * Usage: php framework/lime.php -a ouvretaferme -e dev dev/apiTest
 */
new Page()
	->cli('index', function($data) {

		$start = microtime(TRUE);

		\dev\TestLib::addReset(fn() => \Clock::reset(), \dev\TestLib::FILE);
		\dev\TestLib::addReset(fn() => \Clock::reset(), \dev\TestLib::METHOD);
		\dev\TestLib::watchFatalError();

		$modules = \dev\ApiDocLib::getExposedModules();

		// Ce refus sort en code 2, comme ceux de lime.php : un code 0 le ferait passer pour réussi auprès
		// de qui ne lit que le code de sortie.
		if($modules === []) {
			echo "Error: No exposed module found in ouvretaferme/Lime.s.php\n";
			exit(2);
		}

		\dev\TestLib::startOutput();

		$found = 0;

		foreach($modules as $class) {

			[$packageName, $baseName] = explode('\\', $class, 2);

			// Un module exposé sans son package est un échec de la passe. Package::getApp() rend NULL
			// pour un package inconnu, et ReflectionPackage lèverait une exception, qui arrêterait la
			// passe sans jouer les modules suivants.
			if(Package::exists($packageName) === FALSE) {
				echo $class.': package \''.$packageName.'\' not found'."\n";
				\dev\TestLib::$failures++;
				continue;
			}

			$package = new ReflectionPackage($packageName, Package::getApp($packageName));

			if($package->exists() === FALSE) {
				echo $class.': package \''.$packageName.'\' not found'."\n";
				\dev\TestLib::$failures++;
				continue;
			}

			$files = array_values(array_filter(
				[$baseName.'Api', $baseName.'ApiAi'],
				fn($file) => is_file($package->getPath().'/test/'.$file.'.t.php')
			));

			if($files === []) {
				echo $class.': no test file ('.$packageName.'/test/'.$baseName.'Api.t.php not found)'."\n";
				continue;
			}

			foreach($files as $file) {
				\dev\TestLib::run($package, $file);
			}

			$found++;

		}

		echo "\n".$found.'/'.count($modules)." module(s) tested.\n";

		$code = \dev\TestLib::finish($start);

		ob_end_flush();

		if($code !== 0) {
			exit($code);
		}

	})
	->cli('help', function($data) {

		echo "Usage: dev/apiTest\n";
		echo "\n";
		echo "DESCRIPTION\n";
		echo "	Run every exposed API module's end-to-end test (<package>/test/<Module>Api.t.php),\n";
		echo "	discovered from ApiRoute::expose(...) in ouvretaferme/Lime.s.php.\n";

	});
?>
