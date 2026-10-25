<?php
namespace structure;

/**
 * Un cas de test qui s'arrête faute de décor échoue en nommant ce qui lui manque, il ne passe pas.
 *
 * La garde commune est `\Test::assertDecorPresent($present, $expected)` : vraie, elle compte une
 * assertion ; fausse, elle échoue avec « décor attendu : <expected> ». Un `return` anticipé dans une
 * méthode `test*` est admis quand il nomme son cas :
 * - `nomme` : la condition ou le bloc du `if` appellent une assertion, directe ou par une méthode
 *   qui asserte, résolue dans la classe et ses parents (`requireUser()`, `connect()` qui asserte) ;
 * - `precede` : une des assertions qui précèdent le `if` sans interruption porte sur une variable
 *   de sa condition (`assertTrue($x !== NULL, '…'); if($x === NULL) { return; }`).
 *
 * Refusés, par cliquet dans `SILENT` :
 * - `passe` : le bloc appelle `assertPass()` puis rend, et le cas compte comme réussi ;
 * - `muet` : aucune assertion, le cas ne compte rien ;
 * - `precede-large` : des assertions précèdent le `if`, aucune ne porte sur ce qu'il teste.
 *
 * Ne voit pas :
 * - un retour anticipé dans une méthode privée appelée par un `test*` ;
 * - une méthode qui asserte sur un chemin et rend FALSE sans assertion sur un autre : elle compte
 *   comme une garde qui asserte ;
 * - un appel statique à un Lib qui lève, une garde portée par un trait ;
 * - une instruction précédente qui porte elle-même des accolades (une fonction anonyme en argument)
 *   coupe la remontée des assertions : le retour tombe alors en `precede-large`.
 *
 * Un `return` qui appartient à une fonction anonyme logée dans le cas (une recherche dans une
 * boucle) n'est pas un retour du cas : il est écarté.
 *
 * Lancement : dev/test package=structure file=DecorGuardSentinelAi
 */
class DecorGuardSentinelAiTest extends \dev\SentinelTest {

	const RULE = 'decor';

	/**
	 * Retours anticipés qui ne nomment pas leur cas, par fichier : [nombre, raison].
	 */
	const SILENT = [
		'accounting/overview/test/BalanceSheet.t.php' => [1, '`testValeursConnues` attend des valeurs `EXPECTED_ACTIF` jamais renseignées : le cas ne vérifie rien depuis qu\'il existe, le redresser demande de choisir les valeurs (V8)'],
		'accounting/preaccounting/test/CashInvoicingAi.t.php' => [1, 'la seconde moitié de `testCreditNoteRefundRunsTheOtherWay` ne s\'applique pas le mois où l\'exercice commence : la condition porte sur l\'horloge, pas sur le décor, et se lève en figeant le jour par `setClock()` (6.4)'],
		'commercialisation/payment/test/MarketAggregationAi.t.php' => [1, 'fichier modifié dans l\'arbre par une autre session au moment du lot 6.3 (04/10/2026)'],
		'production/toolbox/test/EquipmentApi.t.php' => [1, 'toolbox, chantier en cours au moment du lot 6.3 (04/10/2026)'],
	];

	/**
	 * Les retours relevés : [classe, chemin, ligne, méthode, condition].
	 *
	 * @var array[]
	 */
	private array $rows = [];

	public function init(): void {

		// Chaque fichier est réduit dès sa lecture à ce que le classement relit, et ses jetons sont
		// jetés : garder ceux de tous les tests à la fois dépasse 512 Mo.
		$classes = [];

		foreach(\dev\SentinelLib::getFiles('.t.php') as $file => $path) {

			// Les sentinelles portent en fixture les formes qu'elles cherchent.
			if(fnmatch('*Sentinel*.t.php', basename($file))) {
				continue;
			}

			$classes += self::readClasses($file, \dev\SentinelLib::getTokens(file_get_contents($path)));

		}

		$classes += self::readClasses('framework/core/Test.php', \dev\SentinelLib::getTokens(file_get_contents(\dev\SentinelLib::getRoot().'/framework/core/Test.php')));

		$this->rows = self::classifyClasses($classes);

		foreach($this->rows as [$kind, $file, $line]) {

			if(in_array($kind, ['passe', 'muet', 'precede-large'], TRUE)) {
				$this->addFound(self::RULE, $file, [$line]);
			}

		}

	}

	public function testEarlyReturnsNameTheirCase(): void {

		$this->assertRatchet(self::RULE, array_map(fn(array $entry) => $entry[0], self::SILENT), 'retour anticipé d\'un cas de test qui ne nomme pas ce qui lui manque (le code fait foi : garder le retour par `if($this->assertDecorPresent(<ce qui doit être vrai>, \'<ce que le cas attend>\') === FALSE) { return; }`, jamais par `assertPass()` ni par un `return` nu ; ne déclarer dans SILENT qu\'un retour qui ne garde pas un décor, avec sa raison)');

		$unexplained = array_keys(array_filter(self::SILENT, fn(array $entry) => trim($entry[1]) === ''));
		$this->assertEquals([], $unexplained, 'fichier déclaré dans SILENT sans sa raison : '.implode(', ', $unexplained));

	}

	public function testScanReadsTheRepository(): void {

		$kinds = array_count_values(array_column($this->rows, 0));

		$this->assertTrue(($kinds['nomme'] ?? 0) > 0 and ($kinds['precede'] ?? 0) > 0, 'le balayage ne trouve aucun retour gardé ('.json_encode($kinds).') : il ne lit plus le dépôt');

	}

	public function testScanKnowsHowToFind(): void {

		$base = "<?php\nabstract class Test {\nprotected function assertPass(): bool {\nreturn \$this->assert(TRUE);\n}\nprotected function assertDecorPresent(bool \$p, string \$e): bool {\nreturn \$this->assert(\$p);\n}\n}\n";

		$code = "<?php\nnamespace a;\nclass ParentTest extends \\Test {\nprotected function requireX(): bool {\nreturn \$this->assertDecorPresent(FALSE, 'x');\n}\n}\n"
			."class ATest extends ParentTest {\n"
			."public function testA(): void {\n"
			."if(\$this->requireX() === FALSE) {\nreturn;\n}\n"
			."\$this->assertTrue(\$e !== NULL, 'e');\nif(\$e === NULL) {\nreturn;\n}\n"
			."\$this->assertTrue(\$f, 'f');\nif(\$g === NULL) {\nreturn;\n}\n"
			."if(\$h->empty()) {\n\$this->assertPass();\nreturn;\n}\n"
			."if(\$i->empty()) {\nreturn;\n}\n"
			."\$find = function() use (\$k) {\nif(\$k) {\nreturn 1;\n}\n};\n"
			."\$m = X::model()->whereY(1, if: \$z)->get();\n"
			."}\n"
			."public function helper(): void {\nif(\$n) {\nreturn;\n}\n}\n"
			."}\n";

		$rows = self::classify([
			'framework/core/Test.php' => \dev\SentinelLib::getTokens($base),
			'a/test/A.t.php' => \dev\SentinelLib::getTokens($code),
		]);

		$expected = [
			'nomme a/test/A.t.php:10 testA',
			'precede a/test/A.t.php:14 testA',
			'precede-large a/test/A.t.php:18 testA',
			'passe a/test/A.t.php:21 testA',
			'muet a/test/A.t.php:25 testA',
		];
		$found = array_map(fn(array $row) => $row[0].' '.$row[1].':'.$row[2].' '.$row[3], $rows);

		$this->assertEquals([], array_values(array_diff($expected, $found)), 'retours que le scan aurait dû trouver sur la fixture : '.implode(', ', array_diff($expected, $found)));
		$this->assertEquals([], array_values(array_diff($found, $expected)), 'retours que le scan trouve à tort sur la fixture : '.implode(', ', array_diff($found, $expected)));

	}

	/**
	 * Chaque retour anticipé des méthodes `test*` des sources données, classé.
	 *
	 * @param array<string, array> $sources chemin => jetons, `framework/core/Test.php` compris
	 * @return array[] [classe, chemin, ligne, méthode, condition], triés par chemin et ligne
	 */
	public static function classify(array $sources): array {

		$classes = [];

		foreach($sources as $file => $tokens) {
			$classes += self::readClasses($file, $tokens);
		}

		return self::classifyClasses($classes);

	}

	/**
	 * Le classement, sur les classes déjà réduites par `readClasses()`. Seul ce qui dépend de
	 * l'héritage (les méthodes qui assertent) se résout ici : le reste a été lu fichier par fichier.
	 *
	 * @param array<string, array> $classes nom complet => classe réduite, `\Test` comprise
	 * @return array[] [classe, chemin, ligne, méthode, condition], triés par chemin et ligne
	 */
	private static function classifyClasses(array $classes): array {

		$rows = [];

		foreach($classes as $name => $class) {

			if($class['file'] === 'framework/core/Test.php') {
				continue;
			}

			$asserting = self::getAsserting($name, $classes);

			foreach($class['methods'] as $method => ['returns' => $returns]) {

				foreach($returns as $return) {
					$rows[] = [self::classifyReturn($return, $asserting), $class['file'], $return['line'], $method, $return['condition']];
				}

			}

		}

		usort($rows, fn(array $a, array $b) => [$a[1], $a[2]] <=> [$b[1], $b[2]]);

		return $rows;

	}

	/**
	 * Les classes d'un fichier, réduites à ce que le classement relit : nom complet => parent
	 * complet, chemin et méthodes (nom => méthodes appelées, et pour une méthode `test*` ses
	 * retours anticipés lus par `readReturns()`). Aucun jeton n'y survit, pour que l'appelant puisse
	 * jeter ceux du fichier.
	 */
	private static function readClasses(string $file, array $tokens): array {

		$namespace = \dev\SentinelLib::getNamespace($tokens);
		$classes = [];
		$count = count($tokens);

		for($i = 0; $i < $count; $i++) {

			if(
				$tokens[$i][0] !== T_CLASS or
				($tokens[$i - 1][0] ?? NULL) === T_DOUBLE_COLON or
				($tokens[$i + 1][0] ?? NULL) !== T_STRING
			) {
				continue;
			}

			$name = self::qualify($namespace, $tokens[$i + 1][1]);
			$parent = NULL;
			$open = $i + 2;

			if($tokens[$open][0] === T_EXTENDS) {
				$parent = self::qualify($namespace, $tokens[$open + 1][1]);
			}

			while($tokens[$open][1] !== '{') {
				$open++;
			}

			$close = \dev\SentinelLib::getClosing($tokens, $open);
			$methods = [];

			for($j = $open + 1; $j < $close; $j++) {

				if($tokens[$j][0] !== T_FUNCTION or ($tokens[$j + 1][0] ?? NULL) !== T_STRING) {
					continue;
				}

				$body = \dev\SentinelLib::getClosing($tokens, $j + 2) + 1;

				while($tokens[$body][1] !== '{' and $tokens[$body][1] !== ';') {
					$body++;
				}

				if($tokens[$body][1] === ';') {
					$j = $body;
					continue;
				}

				$method = $tokens[$j + 1][1];
				$end = \dev\SentinelLib::getClosing($tokens, $body);

				$methods[$method] = [
					'calls' => self::readCalls($tokens, $body, $end),
					'returns' => str_starts_with($method, 'test') ? self::readReturns($tokens, $body, $end) : [],
				];

				$j = $end;

			}

			$classes[$name] = ['parent' => $parent, 'file' => $file, 'methods' => $methods];
			$i = $close;

		}

		return $classes;

	}

	private static function qualify(string $namespace, string $name): string {

		if(str_starts_with($name, '\\')) {
			return substr($name, 1);
		}

		return ($namespace === '') ? $name : $namespace.'\\'.$name;

	}

	/**
	 * Les méthodes qui assertent, dans la classe et ses parents : celles qui appellent une
	 * assertion autre que `assertPass()`, ou une méthode qui asserte. `assertPass()` n'en est pas
	 * une : elle réussit toujours, c'est ce que la règle refuse.
	 *
	 * @return array<string, bool>
	 */
	private static function getAsserting(string $name, array $classes): array {

		$chain = [];

		while($name !== NULL and isset($classes[$name]) and in_array($name, $chain, TRUE) === FALSE) {
			$chain[] = $name;
			$name = $classes[$name]['parent'];
		}

		$calls = [];

		foreach(array_reverse($chain) as $class) {
			foreach($classes[$class]['methods'] as $method => ['calls' => $called]) {
				$calls[$method] = $called;
			}
		}

		$asserting = [];

		do {

			$changed = FALSE;

			foreach($calls as $method => $called) {

				if(isset($asserting[$method]) or $method === 'assertPass') {
					continue;
				}

				foreach($called as $call) {

					if(self::isAssertion($call, $asserting)) {
						$asserting[$method] = TRUE;
						$changed = TRUE;
						break;
					}

				}

			}

		} while($changed);

		return $asserting;

	}

	private static function isAssertion(string $call, array $asserting): bool {
		return ($call !== 'assertPass' and (str_starts_with($call, 'assert') or isset($asserting[$call])));
	}

	/**
	 * Les méthodes appelées sur l'objet ou la classe (`$this->x(`, `self::x(`, `static::x(`,
	 * `parent::x(`) entre deux positions.
	 *
	 * @return string[]
	 */
	private static function readCalls(array $tokens, int $from, int $to): array {

		$calls = [];

		for($i = $from; $i < $to; $i++) {

			$onObject = ($tokens[$i][1] === '$this' and ($tokens[$i + 1][0] ?? NULL) === T_OBJECT_OPERATOR);
			$onClass = (in_array(strtolower($tokens[$i][1]), ['self', 'static', 'parent'], TRUE) and ($tokens[$i + 1][0] ?? NULL) === T_DOUBLE_COLON);

			if(($onObject or $onClass) and ($tokens[$i + 2][0] ?? NULL) === T_STRING and ($tokens[$i + 3][1] ?? NULL) === '(') {
				$calls[] = $tokens[$i + 2][1];
			}

		}

		return $calls;

	}

	/**
	 * Les variables lues entre deux positions, `$this->propriete` comprise, `$this` seul exclu.
	 *
	 * @return string[]
	 */
	private static function readVariables(array $tokens, int $from, int $to): array {

		$variables = [];

		for($i = $from; $i < $to; $i++) {

			if($tokens[$i][0] !== T_VARIABLE) {
				continue;
			}

			if($tokens[$i][1] !== '$this') {
				$variables[] = $tokens[$i][1];
			} else if(($tokens[$i + 1][0] ?? NULL) === T_OBJECT_OPERATOR and ($tokens[$i + 2][0] ?? NULL) === T_STRING and ($tokens[$i + 3][1] ?? NULL) !== '(') {
				$variables[] = '$this->'.$tokens[$i + 2][1];
			}

		}

		return array_values(array_unique($variables));

	}

	/**
	 * Les `if(…) { … return …; }` d'un corps de méthode, hors fonctions anonymes, chacun réduit à ce
	 * que `classifyReturn()` lit. Le classement attend l'héritage, que ce fichier seul ne connaît pas.
	 *
	 * @return array[] ligne, condition, méthodes appelées par la condition et le bloc, variables de
	 *   la condition, instructions qui précèdent le `if` (`readPreceding()`)
	 */
	private static function readReturns(array $tokens, int $start, int $end): array {

		$returns = [];

		// La pile des accolades ouvertes dans le corps : TRUE pour celle d'une fonction anonyme.
		$braces = [];
		$pending = FALSE;

		for($i = $start + 1; $i < $end; $i++) {

			[$type, $text] = $tokens[$i];

			if($type === T_FUNCTION) {
				$pending = TRUE;
				continue;
			}

			if($text === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$braces[] = $pending;
				$pending = FALSE;
				continue;
			}

			if($text === '}') {
				array_pop($braces);
				continue;
			}

			// `if:` est aussi l'argument nommé des méthodes du modèle (`->whereX(…, if: …)`).
			if($type !== T_IF or ($tokens[$i + 1][1] ?? NULL) !== '(' or in_array(TRUE, $braces, TRUE)) {
				continue;
			}

			$conditionEnd = \dev\SentinelLib::getClosing($tokens, $i + 1);

			if(($tokens[$conditionEnd + 1][1] ?? NULL) !== '{') {
				continue;
			}

			$blockStart = $conditionEnd + 1;
			$blockEnd = \dev\SentinelLib::getClosing($tokens, $blockStart);

			if(self::hasOwnReturn($tokens, $blockStart, $blockEnd) === FALSE) {
				continue;
			}

			$returns[] = [
				'line' => $tokens[$i][2],
				'condition' => implode(' ', array_map(fn(array $token) => $token[1], array_slice($tokens, $i + 2, $conditionEnd - $i - 2))),
				'calls' => array_merge(self::readCalls($tokens, $i + 1, $conditionEnd + 1), self::readCalls($tokens, $blockStart, $blockEnd + 1)),
				'variables' => self::readVariables($tokens, $i + 1, $conditionEnd),
				'preceding' => self::readPreceding($tokens, $start, $i),
			];

		}

		return $returns;

	}

	/**
	 * Le classement d'un retour lu par `readReturns()`, une fois connues les méthodes qui assertent
	 * dans la classe et ses parents.
	 */
	private static function classifyReturn(array $return, array $asserting): string {

		foreach($return['calls'] as $call) {
			if(self::isAssertion($call, $asserting)) {
				return 'nomme';
			}
		}

		$asserted = FALSE;

		// Remontée tant que les instructions sont des assertions : `precede` si l'une porte sur une
		// variable de la condition, `precede-large` sinon.
		foreach($return['preceding'] as [$call, $variables]) {

			if(self::isAssertion($call, $asserting) === FALSE) {
				break;
			}

			$asserted = TRUE;

			if(array_intersect($return['variables'], $variables) !== []) {
				return 'precede';
			}

		}

		if($asserted) {
			return 'precede-large';
		}

		return in_array('assertPass', $return['calls'], TRUE) ? 'passe' : 'muet';

	}

	/**
	 * Un `return` du bloc lui-même, pas d'une fonction anonyme qu'il porte.
	 */
	private static function hasOwnReturn(array $tokens, int $blockStart, int $blockEnd): bool {

		$braces = [];
		$pending = FALSE;

		for($i = $blockStart + 1; $i < $blockEnd; $i++) {

			[$type, $text] = $tokens[$i];

			if($type === T_FUNCTION or $type === T_FN) {
				$pending = TRUE;
			} else if($text === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$braces[] = $pending;
				$pending = FALSE;
			} else if($text === '}') {
				array_pop($braces);
			} else if($text === ';') {
				$pending = FALSE;
			} else if($type === T_RETURN and $pending === FALSE and in_array(TRUE, $braces, TRUE) === FALSE) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les instructions qui précèdent le `if`, de la plus proche à la plus lointaine, remontées tant
	 * qu'elles appellent une méthode : [première méthode appelée, variables lues]. Savoir si cette
	 * méthode asserte attend l'héritage, et revient à `classifyReturn()`, qui arrête la remontée à
	 * la première instruction qui n'asserte pas.
	 *
	 * @return array[]
	 */
	private static function readPreceding(array $tokens, int $start, int $position): array {

		$end = $position - 1;
		$preceding = [];

		while($end > $start and $tokens[$end][1] === ';') {

			$begin = $end - 1;

			while($begin > $start and in_array($tokens[$begin][1], [';', '{', '}'], TRUE) === FALSE) {
				$begin--;
			}

			$calls = self::readCalls($tokens, $begin + 1, $end + 1);

			if($calls === []) {
				break;
			}

			$preceding[] = [$calls[0], self::readVariables($tokens, $begin + 1, $end)];

			$end = $begin;

		}

		return $preceding;

	}

}
?>
