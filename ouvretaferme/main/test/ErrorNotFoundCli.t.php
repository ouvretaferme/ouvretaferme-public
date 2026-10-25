<?php
namespace main;

/**
 * La page 404 de l'application (`main/page/error.p.php`), atteinte en ligne de commande, lève une action
 * qui porte le code de sortie 2, et ne sort pas elle-même.
 *
 * Un test qui exécute une page dans son processus (`HarvestFixture::executePage()`) attrape l'action
 * levée : une page qui sortait par `exit()` au moment de lever arrêtait toute la passe, sans bilan. La
 * sortie en 2 d'une vraie ligne de commande, qui exécute l'action, est éprouvée par `dev/CliRefusal`.
 *
 * Lancement : dev/test package=main file=ErrorNotFoundCli
 */
class ErrorNotFoundCliTest extends \Test {

	private function executeNotFound(string $request, \stdClass $data): ?\Action {

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $request), $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		}

	}

	public function testPageNotFoundIsThrown(): void {

		$action = $this->executeNotFound('zzz/nope', new \stdClass());

		if($this->assertTrue($action instanceof \DataAction, 'une page introuvable en ligne de commande doit lever une DataAction')) {
			$this->assertEquals(2, $action->getExitCode(), 'l\'action d\'une page introuvable doit porter le code de sortie 2');
			$this->assertTrue(str_contains($action->get(), '404 Not Found'), 'l\'action doit toujours porter l\'encadré 404');
		}

	}

	public function testDataNotFoundIsThrown(): void {

		$data = new \stdClass();
		$data->error = 'Data does not exist: sonde';

		$action = $this->executeNotFound('error:404', $data);

		if($this->assertTrue($action instanceof \DataAction, 'une donnée introuvable en ligne de commande doit lever une DataAction')) {
			$this->assertEquals(2, $action->getExitCode(), 'l\'action d\'une donnée introuvable doit porter le code de sortie 2');
			$this->assertEquals('Data does not exist: sonde', $action->get(), 'l\'action doit porter le refus tel que NotExistsAction le nomme');
		}

	}

}
?>
