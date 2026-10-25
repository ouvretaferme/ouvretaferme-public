<?php
namespace dev;

/**
 * Une ligne de commande que la route refuse, qui demande une page ou une donnée introuvable, ou dont la
 * page refuse ses arguments, sort en code 2, pas 0 : ce qui ne lit que le code de sortie la prendrait
 * pour réussie.
 *
 * Chaque cas lance un autre processus, la route ne lisant ses options qu'au démarrage.
 *
 * Lancement : dev/test package=dev file=CliRefusal
 */
class CliRefusalTest extends \Test {

	public function testAppMissing(): void {

		[$code, $output] = $this->runProcess(['-e', LIME_ENV, 'dev/test']);

		$this->assertEquals(2, $code, 'une ligne de commande sans -a doit sortir en code 2');
		$this->assertEquals("Error: App is missing\n", $output, 'la sortie doit toujours nommer ce qui manque');

	}

	public function testEnvMissing(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, 'dev/test']);

		$this->assertEquals(2, $code, 'une ligne de commande sans -e doit sortir en code 2');
		$this->assertEquals("Error: Env is missing\n", $output, 'la sortie doit toujours nommer ce qui manque');

	}

	public function testArgumentSyntax(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, 'dev/test:lock', 'zzz']);

		$this->assertEquals(2, $code, 'un argument de page sans name=value doit sortir en code 2');
		$this->assertTrue(str_contains($output, 'Error: Page arguments must have the following syntax'), 'la sortie doit toujours nommer le refus : '.$output);

	}

	public function testArgumentName(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, 'dev/test:lock', 'zzz[a=1']);

		$this->assertEquals(2, $code, 'un nom d\'argument invalide doit sortir en code 2');
		$this->assertTrue(str_contains($output, 'Error: Invalid argument name'), 'la sortie doit toujours nommer le refus : '.$output);

	}

	public function testConstantSyntax(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, '-c', 'zzz', 'dev/test:lock']);

		$this->assertEquals(2, $code, 'une constante -c sans NAME=VALUE doit sortir en code 2, après l\'aide');
		$this->assertTrue(str_contains($output, 'Usage: php lime.php'), 'la sortie doit toujours donner l\'aide : '.$output);

	}

	public function testPageNotFound(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, 'dev/cliRefusalNowhere']);

		$this->assertEquals(2, $code, 'une page introuvable doit sortir en code 2');
		$this->assertTrue(str_contains($output, '404 Not Found'), 'la sortie doit toujours dire la page introuvable : '.$output);

	}

	public function testDataNotFound(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, '-r', 'new \NotExistsAction("cliRefusal")->run();']);

		$this->assertEquals(2, $code, 'une donnée introuvable doit sortir en code 2');
		$this->assertTrue(str_contains($output, 'Data does not exist'), 'la sortie doit toujours dire la donnée introuvable : '.$output);

	}

	public function testPageRefusal(): void {

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, 'dev/module']);

		$this->assertEquals(2, $code, 'une page qui refuse un argument manquant doit sortir en code 2');
		$this->assertEquals("Error: Argument flags=? is missing (type /dev/module:help)\n", $output, 'la sortie doit toujours nommer l\'argument manquant');

		[$code, $output] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, 'storage/configure/addSize', 'type=cliRefusalNowhere']);

		$this->assertEquals(2, $code, 'une page qui refuse une valeur d\'argument doit sortir en code 2');
		$this->assertEquals("Error: Invalid type=cliRefusalNowhere\n", $output, 'la sortie doit toujours nommer la valeur refusée');

	}

	public function testPageFound(): void {

		[$code] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, 'dev/test:lock']);

		$this->assertEquals(0, $code, 'la même page trouvée, sans argument fautif, doit sortir en code 0, sinon 2 ne prouve rien');

	}

	public function testComplete(): void {

		[$code] = $this->runProcess(['-a', LIME_APP, '-e', LIME_ENV, '-r', 'echo "done";']);

		$this->assertEquals(0, $code, 'la même ligne de commande complète doit sortir en code 0, sinon 2 ne prouve rien');

	}

	/**
	 * @return array Le code de sortie et la sortie standard
	 */
	private function runProcess(array $options): array {

		// Le tableau s'écrit en littéral pour que ShellCommandSentinel y lise la forme sans shell.
		$process = proc_open([PHP_BINARY, LIME_DIRECTORY.'/framework/lime.php', ...$options], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($process), $output];

	}

}
?>
