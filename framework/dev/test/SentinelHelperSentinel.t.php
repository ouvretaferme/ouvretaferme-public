<?php
namespace dev;

/**
 * Une sentinelle qui redéclare un helper de la base n'hérite d'aucune correction de la base.
 *
 * Rien ne le signale, et le nom identique donne l'impression du contraire : `getFiles()` écrit
 * dans une sentinelle a la même signature et le même usage que celui de `SentinelLib`, il ne
 * l'appelle pas. Le filtre que la base a gagné le 2026-10-01, qui écarte des deux côtés du cliquet
 * ce que git ne suit pas, n'atteint donc aucune des douze sentinelles qui ont leur propre parcours.
 * Mesuré ce jour-là : aucune des douze n'appelle `SentinelLib::compare()` ni `assertRatchet()`,
 * donc aucune n'en reçoit même la moitié (relevé par E-Dashboard, sur `WeekMonthSentinelAi`).
 *
 * Redéclarer reste légitime, et c'est pourquoi l'inventaire se déclare au lieu d'être refusé : la
 * plupart bornent leur balayage aux paquets par `\Package::getList()`, ce que la base ne fait pas.
 * Ce qui ne l'est pas, c'est de le faire sans savoir qu'on part de zéro.
 */
class SentinelHelperSentinelTest extends SentinelTest {

	/**
	 * Les sentinelles qui portent leur propre copie d'un helper de la base, et ce que chacune
	 * balaie. Une entrée dit pourquoi le parcours est propre, pas qu'il est en faute.
	 *
	 * Chacune doit écarter elle-même ce que git ne suit pas, par `SentinelLib::getTracked()`,
	 * sinon son inventaire compte des fichiers qu'aucune déclaration poussée ne peut couvrir.
	 */
	private const HELPERS = [
		// Bornent leur balayage aux paquets par \Package::getList(), ce que la base ne fait pas.
		'base/structure/test/CrossDatabaseJoinSentinelAi.t.php' => 1,
		'base/structure/test/HasVatBooleanSentinelAi.t.php' => 1,
		'base/structure/test/InstalledSentinelAi.t.php' => 1,
		'base/structure/test/StructureSweepSentinelAi.t.php' => 1,
		'production/series/test/WeekMonthSentinelAi.t.php' => 1,
		// Idem, et redéclarent aussi getNamespace() pour lire l'espace de noms de leurs modules.
		'commercialisation/quotation/test/DirectWriteSentinelAi.t.php' => 2,
		'commercialisation/shop/test/ShopRegimeSentinelAi.t.php' => 2,
		// Balaient des suffixes que la base sait déjà rendre : elles pourraient l'appeler.
		'accounting/financialYear/test/AccrualGateSentinelAi.t.php' => 1,
		'framework/editor/test/EditorValueSentinel.t.php' => 1,
		// Balaie les feuilles de style et les UI des paquets, que la base ne rend pas ensemble.
		'ouvretaferme/design/test/DesignSystemSentinelAi.t.php' => 1,
		// Redéclare addFound() de SentinelTest, donc ne passe pas par assertRatchet().
		'framework/session/test/SessionHijackSentinel.t.php' => 1,
	];

	/**
	 * Les fichiers qui définissent les helpers : ils les portent par construction.
	 */
	private const SOURCES = [
		'framework/dev/lib/Sentinel.l.php',
		'framework/dev/test/Sentinel.t.php',
	];

	public function init(): void {

		$names = self::getHelperNames();

		foreach(SentinelLib::getFiles('.t.php') as $file => $path) {

			if(fnmatch('*Sentinel*.t.php', basename($file)) === FALSE) {
				continue;
			}

			if(in_array($file, self::SOURCES, TRUE)) {
				continue;
			}

			$tokens = SentinelLib::getTokens(file_get_contents($path));

			foreach($tokens as $position => $token) {

				if($token[0] !== T_FUNCTION) {
					continue;
				}

				$next = self::getNextName($tokens, $position);

				if($next !== NULL and in_array($tokens[$next][1], $names, TRUE)) {
					$this->addFound('helper', $file, [$tokens[$next][2]]);
				}

			}

		}

	}

	public function testEveryRedeclaredHelperIsDeclared(): void {

		$this->assertRatchet(
			'helper',
			self::HELPERS,
			'sentinelle qui redéclare un helper de la base, donc qui n\'hérite d\'aucune de ses corrections, filtre du non suivi compris. Deux gestes, qui ne valent qu\'ensemble : d\'abord écarter du parcours ce que git ne suit pas, par \dev\SentinelLib::getTracked(), ensuite, du côté déclaré, sauter une entrée dont le chemin est présent sur le disque sans être suivi. Le premier seul échange un rouge contre un autre, le contrôle en sens inverse du cliquet rendant alors la déclaration périmée. Un chemin que ni le disque ni git ne portent reste nommé. Puis déclarer l\'entrée ici avec ce qu\'elle balaie'
		);

	}

	/**
	 * Le détecteur voit une redéclaration et laisse passer un appel au helper de la base.
	 */
	public function testDetectorFindsOnlyADeclaration(): void {

		$names = self::getHelperNames();

		$tokens = SentinelLib::getTokens('<?php
			class T {
				private function getFiles(string $suffix): array { return []; }
				public function other(): array { return \dev\SentinelLib::getFiles(\'.php\'); }
			}
		');

		$found = [];

		foreach($tokens as $position => $token) {

			if($token[0] !== T_FUNCTION) {
				continue;
			}

			$next = self::getNextName($tokens, $position);

			if($next !== NULL and in_array($tokens[$next][1], $names, TRUE)) {
				$found[] = $tokens[$next][1];
			}

		}

		$this->assertEquals(['getFiles'], $found, 'le détecteur doit voir la seule redéclaration : '.implode(', ', $found));

	}

	public function testHelperNamesComeFromTheCode(): void {

		$names = self::getHelperNames();

		$this->assertTrue(in_array('getFiles', $names, TRUE), 'la liste des helpers se dérive des deux classes de la base, elle ne se retape pas : '.implode(', ', $names));
		$this->assertTrue(in_array('getTracked', $names, TRUE), 'getTracked() manque à la liste dérivée : '.implode(', ', $names));

	}

	/**
	 * Les noms des helpers, dérivés de `SentinelLib` et de `SentinelTest`, jamais recopiés : un
	 * helper ajouté à la base fait apparaître d'office les sentinelles qui portaient déjà ce nom.
	 */
	private static function getHelperNames(): array {

		$names = [];

		foreach([SentinelLib::class, SentinelTest::class] as $class) {

			foreach(new \ReflectionClass($class)->getMethods() as $method) {

				if($method->getDeclaringClass()->getName() === $class and $method->getName() !== '__construct') {
					$names[] = $method->getName();
				}

			}

		}

		return array_unique($names);

	}

	/**
	 * La position du nom qui suit un `function`, en sautant les espaces. Rend NULL sur une closure,
	 * dont le `function` est suivi d'une parenthèse.
	 */
	private static function getNextName(array $tokens, int $position): ?int {

		for($i = $position + 1; $i < count($tokens); $i++) {

			if($tokens[$i][0] === T_WHITESPACE) {
				continue;
			}

			return ($tokens[$i][0] === T_STRING) ? $i : NULL;

		}

		return NULL;

	}

}
?>
