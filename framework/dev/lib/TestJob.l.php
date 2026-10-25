<?php
namespace dev;

/**
 * Une passe de sentinelles répartie sur plusieurs processus (`dev/test jobs=N`).
 *
 * Chaque fichier se joue dans son propre `lime.php`, qui imprime le rapport du fichier et remet ses
 * compteurs par un fichier temporaire ; ce processus-ci les additionne et écrit seul la ligne Total,
 * qui reste ainsi celle de la passe série. Un processus par fichier plutôt qu'une part de la liste :
 * le rapport d'un fichier sort entier dès qu'il est fini, sans se mêler à celui d'un autre, et une
 * erreur fatale n'emporte que le fichier qui la lève.
 *
 * Réservé aux sentinelles, qui ne font que lire : deux tests qui écrivent dans la même base au même
 * moment verraient chacun les lignes de l'autre.
 *
 * `\Parallel` (framework/core/Parallel.php) ne convient pas : ses processus rendent leur résultat par
 * une file de messages, qu'un processus mort d'une erreur fatale n'alimente jamais et qu'on attend
 * alors sans fin, et leurs sorties se mêlent sur celle du parent.
 */
class TestJobLib {

	/**
	 * La seule sélection qui accepte jobs=N, parce qu'une sentinelle n'écrit pas en base
	 * (framework/dev/test/CLAUDE.md).
	 */
	const PATTERN = '*Sentinel*';

	/**
	 * Plafond d'espace d'adressage de chaque processus, en kilo-octets, posé par `ulimit -v`. Une
	 * fuite dans une sentinelle s'arrête alors en erreur fatale nommée au lieu de grossir jusqu'à ce
	 * que la machine tue d'autres programmes, ce que memory_limit ne garantit pas : il vaut -1 en
	 * ligne de commande sur une installation directe.
	 */
	const MEMORY = 1024 * 1024;

	/**
	 * Joue ces fichiers, au plus `$jobs` à la fois, dans l'ordre donné.
	 *
	 * @param array $files Des paires [\ReflectionPackage, nom du fichier sans `.t.php`]
	 * @param array $arguments Les arguments transmis à chaque processus, en plus de package, file et counters
	 * @return bool TRUE si un processus s'est arrêté sans rendre ses compteurs
	 */
	public static function run(array $files, int $jobs, array $arguments): bool {

		$stopped = FALSE;
		$running = [];

		while($files !== [] or $running !== []) {

			while($files !== [] and count($running) < $jobs) {

				[$package, $file] = array_shift($files);

				$job = self::start($package, $file, $arguments);
				$running[get_resource_id($job['output'])] = $job;

			}

			$read = array_column($running, 'output', 'id');
			$write = NULL;
			$except = NULL;

			// Un signal reçu interrompt l'attente sans qu'aucune sortie soit prête : on attend de nouveau.
			if(stream_select($read, $write, $except, NULL) === FALSE) {
				continue;
			}

			foreach(array_keys($read) as $id) {

				$chunk = fread($running[$id]['output'], 65536);

				if($chunk !== '' and $chunk !== FALSE) {
					$running[$id]['buffer'] .= $chunk;
					continue;
				}

				if(feof($running[$id]['output'])) {

					if(self::finish($running[$id]) === FALSE) {
						$stopped = TRUE;
					}

					unset($running[$id]);

				}

			}

		}

		return $stopped;

	}

	/**
	 * La commande qui lance `dev/test` dans un autre processus, avec l'application, l'environnement
	 * et les constantes de celui-ci.
	 */
	public static function getCommand(array $arguments): array {

		$command = [PHP_BINARY, LIME_DIRECTORY.'/framework/lime.php', '-a', LIME_APP, '-e', LIME_ENV];

		foreach($_SERVER['constants'] as $name => $value) {
			$command[] = '-c';
			$command[] = $name.'='.$value;
		}

		$command[] = 'dev/test';

		foreach($arguments as $name => $value) {
			$command[] = $name.'='.$value;
		}

		return $command;

	}

	/**
	 * La ligne qui nomme un fichier dont le processus s'est arrêté sans rien en dire, tué par un signal
	 * avant que PHP puisse écrire l'erreur fatale.
	 *
	 * @param array $status Ce que proc_get_status() rend du processus arrêté
	 */
	public static function getStopText(string $path, array $status): string {

		if($status['signaled']) {
			$end = 'killed by signal '.$status['termsig'];
		} else {
			$end = 'exited with code '.$status['exitcode'];
		}

		$stop = 'FATAL ERROR: the run stopped without a verdict, in '.$path.', whose process was '.$end.'.';

		if(TestLib::$summary === FALSE) {
			$stop = "\033[31m".$stop."\033[00m";
		}

		return $stop.PHP_EOL;

	}

	private static function start(\ReflectionPackage $package, string $file, array $arguments): array {

		$counters = tempnam(sys_get_temp_dir(), 'otf-dev-test-');

		$command = self::getCommand([
			'package' => $package->getPackageName(),
			'file' => $file,
			'counters' => $counters,
		] + $arguments);

		// La forme tableau n'ouvre pas de shell sur la commande : sh ne reçoit que le plafond, et
		// remet la commande telle quelle à exec par "$@".
		// La sortie d'erreur n'est pas déclarée, le processus hérite ainsi du descripteur 2 tel quel.
		// Passé en flux (`2 => STDERR`), PHP le recale sur la position qu'il tient pour STDERR, et
		// une passe envoyée dans un fichier par `> fichier 2>&1` s'y réécrivait depuis le début.
		$process = proc_open(
			['sh', '-c', 'ulimit -v '.self::MEMORY.' && exec "$@"', 'sh', ...$command],
			[0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w']],
			$pipes
		);

		stream_set_blocking($pipes[1], FALSE);

		return [
			'id' => get_resource_id($pipes[1]),
			'process' => $process,
			'output' => $pipes[1],
			'buffer' => '',
			'counters' => $counters,
			'path' => substr($package->getPath(), strlen(LIME_DIRECTORY) + 1).'/test/'.$file.'.t.php',
		];

	}

	/**
	 * Imprime le rapport du fichier et ajoute ses compteurs à ceux de la passe.
	 *
	 * @return bool FALSE si le processus s'est arrêté sans rendre ses compteurs
	 */
	private static function finish(array $job): bool {

		fclose($job['output']);

		// proc_close() ne rend qu'un code, où un signal ne se distingue pas d'une sortie : l'état se lit
		// avant, dès que le processus a fini. Sa sortie fermée dit seulement qu'il est en train de finir.
		while(($status = proc_get_status($job['process']))['running']) {
			usleep(10000);
		}

		proc_close($job['process']);

		$counters = file_get_contents($job['counters']);
		unlink($job['counters']);

		echo $job['buffer'];

		if($counters !== '') {
			TestLib::addCounters(unserialize($counters, ['allowed_classes' => FALSE]));
			return TRUE;
		}

		// Une erreur fatale de PHP a déjà nommé son test (TestLib::watchFatalError()), un signal n'a
		// rien laissé dire.
		if(str_contains($job['buffer'], 'FATAL ERROR: ') === FALSE) {
			echo self::getStopText($job['path'], $status);
		}

		return FALSE;

	}

}
?>
