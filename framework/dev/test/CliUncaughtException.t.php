<?php
namespace dev;

/**
 * Une exception qu'aucune page ne rattrape arrête un processus en ligne de commande avec le code 255,
 * pas 0 : un cron, un script de configure ou une passe de tests arrêtés ainsi ne passent plus pour
 * réussis auprès de ce qui ne lit que le code de sortie. Le statut 500 s'y écrit sans header(), qui
 * lèverait un Warning après l'encadré de l'exception.
 *
 * Le gestionnaire vit dans `Route::handleErrors()`, que `lime.php -r` ne pose pas : chaque cas lance
 * un autre processus par `-r`, y pose le gestionnaire réel puis lève, sans dépendre d'une page qui
 * n'existerait que pour ce test. Le processus ne consigne rien en base, `saveError(FALSE)` gardant
 * la seule sortie écrite.
 *
 * Lancement : dev/test package=dev file=CliUncaughtException
 */
class CliUncaughtExceptionTest extends \Test {

	const MESSAGE = 'exception left uncaught by CliUncaughtExceptionTest';
	const SHUTDOWN = 'shutdown function ran after the exception';

	public function testExitCodeIs255(): void {

		[$code, $output] = $this->runProcess('throw new \Exception("'.self::MESSAGE.'");');

		$this->assertEquals(255, $code, 'une exception non rattrapée doit arrêter le processus en code 255');
		$this->assertTrue(str_contains($output, self::MESSAGE), 'la sortie doit toujours nommer l\'exception : '.$output);
		$this->assertTrue(str_contains($output, '500 Internal Server Error'), 'la sortie doit toujours finir sur le statut 500 : '.$output);

	}

	public function testStatusSendsNoHeader(): void {

		[, $output] = $this->runProcess('throw new \Exception("'.self::MESSAGE.'");');

		$this->assertFalse(str_contains($output, 'headers already sent'), 'StatusAction ne doit pas appeler header() en ligne de commande, son Warning se consignerait en double de l\'exception : '.$output);

	}

	public function testShutdownFunctionsStillRun(): void {

		[, $output] = $this->runProcess('register_shutdown_function(function() { echo "'.self::SHUTDOWN.'"; }); throw new \Exception("'.self::MESSAGE.'");');

		$this->assertTrue(str_contains($output, self::SHUTDOWN), 'les fonctions d\'arrêt (consignation d\'une erreur fatale, action par défaut) doivent tourner après l\'exception : '.$output);

	}

	public function testExitCodeWithoutException(): void {

		[$code] = $this->runProcess('echo "done";');

		$this->assertEquals(0, $code, 'le même processus sans exception doit sortir en code 0, sinon 255 ne prouve rien');

	}

	/**
	 * @return array Le code de sortie et la sortie standard
	 */
	private function runProcess(string $body): array {

		// handleErrors() est protégée et la route ne s'instancie qu'en lisant la ligne de commande :
		// la réflexion pose le gestionnaire réel sans rejouer le constructeur.
		$code = '\dev\ErrorPhpLib::saveError(FALSE);'
			.'new \ReflectionMethod("Route", "handleErrors")->invoke(new \ReflectionClass("CliRoute")->newInstanceWithoutConstructor());'
			.$body;

		// Le tableau s'écrit en littéral pour que ShellCommandSentinel y lise la forme sans shell.
		$process = proc_open([PHP_BINARY, LIME_DIRECTORY.'/framework/lime.php', '-a', LIME_APP, '-e', LIME_ENV, '-r', $code], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($process), $output];

	}

}
?>
