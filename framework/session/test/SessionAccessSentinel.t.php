<?php
namespace session;

/**
 * `$_SESSION` ne se lit et ne s'écrit que dans `SessionLib`, qui range la valeur sous `data`, là
 * où ses lectures la cherchent, et borne la taille du contenu à `MAX_LENGTH`.
 *
 * Lancement : dev/test package=session file=SessionAccessSentinel
 */
class SessionAccessSentinelTest extends \Test {

	/**
	 * Accès légitimes hors de `SessionLib`, par fichier depuis la racine du dépôt.
	 *
	 * - `ouvretaferme/Lime.s.php` : `isset($_SESSION)` demande si une session HTTP est ouverte avant
	 *   de lire le membre connecté. `SessionLib::isInitialized()` ne répond pas à la même question,
	 *   il rend TRUE en CLI, où `ConnectionLib::getOnline()` tenterait une connexion automatique.
	 */
	const ALLOWED = [
		'framework/session/lib/Session.l.php' => NULL,
		'ouvretaferme/Lime.s.php' => 1,
	];

	/**
	 * Les lignes où `$_SESSION` apparaît en code, commentaires et chaînes exclus.
	 * @return int[]
	 */
	private function scan(string $code): array {

		$lines = [];

		foreach(token_get_all($code) as $token) {

			if(is_array($token) and $token[0] === T_VARIABLE and $token[1] === '$_SESSION') {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/** Le scanner trouve le code, et ignore les commentaires et les chaînes. */
	public function testScannerFindsSession(): void {

		$lines = $this->scan(<<<'PHP'
			<?php
			$_SESSION['user'] = 1;
			// $_SESSION['user'] = 2;
			$a = '$_SESSION';
			$b = isset($_SESSION['data']);
			PHP);

		$this->assertEquals([2, 5], $lines, 'l\'écriture et la lecture sont trouvées, le commentaire et la chaîne ignorés');

	}

	public function testSessionOnlyInLib(): void {

		$found = [];
		$counts = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $relative => $path) {

			$lines = $this->scan(file_get_contents($path));

			if($lines === []) {
				continue;
			}

			$counts[$relative] = count($lines);

			if(array_key_exists($relative, self::ALLOWED) === FALSE) {
				foreach($lines as $line) {
					$found[] = $relative.':'.$line;
				}
			}

		}

		$this->assertEquals([], $found, '$_SESSION hors de SessionLib, à passer par SessionLib::set()/get()/exists()/delete() : '.implode(', ', $found));

		$stale = [];

		foreach(self::ALLOWED as $relative => $expected) {

			$count = $counts[$relative] ?? 0;

			if(($expected === NULL and $count === 0) or ($expected !== NULL and $count !== $expected)) {
				$stale[] = $relative.' (déclaré '.($expected ?? 'au moins 1').', trouvé '.$count.')';
			}

		}

		$this->assertEquals([], $stale, 'déclarations de ALLOWED qui ne correspondent plus au code : '.implode(', ', $stale));

	}

}
