<?php
namespace dev;

/**
 * Sujet de `TestRunnerTest`, qui le fait jouer par `TestLib::run()` pour éprouver le lanceur sur une
 * vraie classe : l'ordre de ses appels, le décor absent, la somme des assertions.
 *
 * Lancé dans une campagne, sans que `TestRunnerTest` ait posé `$withheld`, `$failing`, `$noticing` ni
 * `$noticingSecond`, il est
 * vert et compte pour une classe de trois assertions.
 *
 * `TestRunnerTest` le fait aussi jouer dans un autre processus, par `TestJobLib`, où ses propriétés
 * statiques ne portent pas : l'arrêt qu'il y demande passe par la variable d'environnement que
 * nomme `STOP`.
 *
 * Lancement : dev/test package=dev file=TestRunnerSubject
 */
class TestRunnerSubjectTest extends \Test {

	public static bool $withheld = FALSE;
	public static bool $inapplicable = FALSE;
	public static bool $inapplicableMethod = FALSE;
	public static bool $failing = FALSE;
	public static bool $noticing = FALSE;
	public static bool $noticingSecond = FALSE;

	public static array $calls = [];

	/**
	 * `memory` arrête le processus sur une erreur fatale, que PHP laisse nommer ; `kill` le tue sans
	 * qu'il puisse rien dire.
	 */
	const STOP = 'OTF_TEST_RUNNER_STOP';

	public function init(): void {

		self::$calls[] = __FUNCTION__;

		if(self::$withheld) {
			throw new \Exception('fixture withheld by TestRunnerTest');
		}

		if(self::$inapplicable) {
			throw new \dev\NotApplicableException('resource withheld by TestRunnerTest');
		}

	}

	public function setUp(): void {
		self::$calls[] = __FUNCTION__;
	}

	public function tearDown(): void {
		self::$calls[] = __FUNCTION__;
	}

	public function finalize(): void {
		self::$calls[] = __FUNCTION__;
	}

	public function testFirst(): void {

		self::$calls[] = __FUNCTION__;

		if(getenv(self::STOP) === 'memory') {
			// Le processus hérite de la sortie d'erreur de la passe, où PHP consignerait l'erreur
			// voulue en plus de la dire sur la sortie standard, que TestJobLib lit.
			ini_set('log_errors', '0');
			// Le plafond se pose au-dessus de ce que le processus occupe déjà, que PHP refuserait.
			ini_set('memory_limit', (string)(memory_get_usage() + 8 * 1024 * 1024));
			$exhausted = str_repeat('x', 16 * 1024 * 1024);
		}

		if(getenv(self::STOP) === 'kill') {
			// 9 est SIGKILL, dont la constante n'existe qu'avec l'extension pcntl.
			posix_kill(getmypid(), 9);
		}

		if(self::$inapplicableMethod) {
			throw new \dev\NotApplicableException('resource withheld in testFirst');
		}

		if(self::$noticing) {
			foreach([1, 2] as $ignored) {
				$read = $absent;
			}
		}

		$this->assertEquals(__FUNCTION__, $this->getTestMethod());

	}

	public function testSecond(): void {

		self::$calls[] = __FUNCTION__;

		if(self::$noticingSecond) {
			$read = $missing;
		}

		$this->assertEquals(__FUNCTION__, $this->getTestMethod());
		$this->assertFalse(self::$failing, 'échec demandé par TestRunnerTest');

	}

}
?>
