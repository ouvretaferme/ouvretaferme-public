<?php
namespace dev;

/**
 * Piège du CLAUDE.md : une `Collection` n'a qu'un curseur, celui de l'`ArrayIterator` qu'elle étend.
 *
 * Un `foreach($cPlant)` posé dans un `foreach($cPlant)` le partage avec la boucle extérieure, qui
 * s'arrête à la fin de l'intérieure : 3 tours au lieu de 9 sur trois éléments. Les méthodes de
 * `Collection` qui parcourent `$this` font de même depuis le corps de la boucle : `getIds()`,
 * `getColumn()`, `map()`, `sum()` ou `last()` l'arrêtent après un tour, et `first()`, qui rembobine,
 * la fait tourner sans fin (mesuré le 2026-09-24). Un tableau n'est pas concerné, `foreach` en
 * parcourt une copie.
 *
 * Ce que la sentinelle ne voit pas : la collection passée à une fonction qui la parcourt, depuis le
 * corps de la boucle. Seule l'expression écrite deux fois à l'identique se lit dans le code.
 *
 * Lancement : dev/test package=dev file=CollectionCursorSentinel
 */
class CollectionCursorSentinelTest extends SentinelTest {

	const RULE = 'cursor';

	/**
	 * Les parcours imbriqués légitimes, par fichier.
	 */
	const DECLARED = [
		// La boucle intérieure est suivie d'un return : l'extérieure ne reprend jamais.
		'base/structure/ui/ProfileAi.u.php' => 1,
		// next() lit la vente qui suit la vente courante, et la boucle s'achève par un return.
		'commercialisation/selling/lib/Preparation.l.php' => 1,
	];

	/**
	 * Les méthodes de `ArrayIterator` qui déplacent le curseur, auxquelles s'ajoutent celles de
	 * `Collection` qui les appellent.
	 */
	const ITERATOR = ['rewind', 'next', 'seek'];

	public function init(): void {

		$methods = self::getMovingMethods();

		foreach($this->getPhpTokens() as $file => $tokens) {
			$this->addFound(self::RULE, $file, $this->scan($tokens, $methods));
		}

	}

	/**
	 * Les méthodes de `Collection` dont le corps parcourt `$this`, ou appelle une méthode qui le fait.
	 *
	 * @return string[]
	 */
	public static function getMovingMethods(): array {

		$reflection = new \ReflectionClass(\Collection::class);
		$lines = file($reflection->getFileName());
		$bodies = [];

		foreach($reflection->getMethods() as $method) {

			if($method->getDeclaringClass()->getName() === \Collection::class) {
				$bodies[$method->getName()] = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
			}

		}

		$moving = self::ITERATOR;

		foreach($bodies as $name => $body) {

			if(preg_match('/foreach\(\$this\b/', $body)) {
				$moving[] = $name;
			}

		}

		do {

			$added = FALSE;

			foreach($bodies as $name => $body) {

				if(in_array($name, $moving, TRUE)) {
					continue;
				}

				if(preg_match('/\$this->('.implode('|', $moving).')\(/', $body)) {
					$moving[] = $name;
					$added = TRUE;
				}

			}

		} while($added);

		sort($moving);

		return $moving;

	}

	/**
	 * Les lignes où une collection déjà parcourue est reparcourue, ou reçoit un appel qui déplace
	 * son curseur.
	 *
	 * Une collection se reconnaît à son nom (`$cPlant`, `$eSeries['cCultivation']`,
	 * `$this->cPlant`) : un tableau parcouru deux fois ne partage rien.
	 *
	 * @param array<int, array{0: int|string, 1: string, 2: int}> $tokens
	 * @param string[] $methods
	 * @return int[]
	 */
	public function scan(array $tokens, array $methods): array {

		$lines = [];
		$loops = [];
		$depth = 0;
		$count = count($tokens);

		for($i = 0; $i < $count; $i++) {

			[$type, $text, $line] = $tokens[$i];

			if($text === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$depth++;
				continue;
			}

			if($text === '}') {

				while($loops and end($loops)['depth'] === $depth) {
					array_pop($loops);
				}

				$depth--;
				continue;

			}

			if($type === T_ENDFOREACH) {
				array_pop($loops);
				continue;
			}

			if($type === T_FOREACH) {

				[$expression, $i] = self::readForeach($tokens, $i);

				if(self::isCollection($expression) and in_array($expression, array_column($loops, 'expression'), TRUE)) {
					$lines[] = $line;
				}

				// Une boucle sans accolades ni deux-points ne porte qu'une instruction : aucun appel n'y est relevé
				$loops[] = match($tokens[$i + 1][1] ?? NULL) {
					'{' => ['expression' => $expression, 'depth' => $depth + 1],
					':' => ['expression' => $expression, 'depth' => NULL],
					default => ['expression' => '', 'depth' => $depth + 1],
				};

				continue;

			}

			if(
				$type === T_OBJECT_OPERATOR and
				$loops and
				($tokens[$i + 1][0] ?? NULL) === T_STRING and
				($tokens[$i + 2][1] ?? NULL) === '(' and
				in_array($tokens[$i + 1][1], $methods, TRUE)
			) {

				$expression = self::readBefore($tokens, $i);

				if(self::isCollection($expression) and in_array($expression, array_column($loops, 'expression'), TRUE)) {
					$lines[] = $line;
				}

			}

		}

		return $lines;

	}

	/**
	 * L'expression parcourue par le `foreach` en position `$i`, et la position de sa parenthèse
	 * fermante.
	 *
	 * @return array{0: string, 1: int}
	 */
	private static function readForeach(array $tokens, int $i): array {

		$expression = '';
		$parenthesis = 0;
		$reading = TRUE;

		for($j = $i + 1; $j < count($tokens); $j++) {

			$text = $tokens[$j][1];

			if($text === '(') {
				$parenthesis++;
				if($parenthesis === 1) {
					continue;
				}
			} else if($text === ')') {
				$parenthesis--;
				if($parenthesis === 0) {
					break;
				}
			}

			if($tokens[$j][0] === T_AS and $parenthesis === 1) {
				$reading = FALSE;
			}

			if($reading) {
				$expression .= $text;
			}

		}

		return [$expression, $j];

	}

	/**
	 * L'expression qui se termine juste avant la position `$i` : une variable suivie de ses index
	 * et de ses propriétés.
	 */
	private static function readBefore(array $tokens, int $i): string {

		$expression = '';

		for($j = $i - 1; $j >= 0; $j--) {

			[$type, $text] = $tokens[$j];

			if($text === ']') {

				$chunk = '';
				$brackets = 0;

				for(; $j >= 0; $j--) {

					$chunk = $tokens[$j][1].$chunk;

					if($tokens[$j][1] === ']') {
						$brackets++;
					} else if($tokens[$j][1] === '[') {
						$brackets--;
						if($brackets === 0) {
							break;
						}
					}

				}

				$expression = $chunk.$expression;

			} else if($type === T_STRING and ($tokens[$j - 1][0] ?? NULL) === T_OBJECT_OPERATOR) {

				$expression = '->'.$text.$expression;
				$j--;

			} else if($type === T_VARIABLE) {

				return $text.$expression;

			} else {

				return '';

			}

		}

		return '';

	}

	private static function isCollection(string $expression): bool {
		return preg_match('/(^\$|\[\'|->)c[A-Z]\w*\'?\]?$/', $expression) === 1;
	}

	public function testMovingMethods(): void {

		$methods = self::getMovingMethods();

		foreach(['getIds', 'getColumn', 'first', 'last', 'sum', 'rewind'] as $method) {
			$this->assertTrue(in_array($method, $methods, TRUE), $method.' déplace le curseur et manque à la liste');
		}

		foreach(['count', 'find', 'offsetExists', 'notEmpty'] as $method) {
			$this->assertFalse(in_array($method, $methods, TRUE), $method.' ne déplace pas le curseur et figure dans la liste');
		}

	}

	public function testScannerFindsCursor(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			foreach($cPlant as $ePlant) {
				$ids = $cPlant->getIds();
				foreach($eSeries['cCultivation'] as $eCultivation) {
					$eSeries['cCultivation']->first();
					foreach($eSeries['cCultivation'] as $eOther):
					endforeach;
					$cOther->getIds();
					$cPlant->count();
				}
				foreach($names as $name) {
					foreach($names as $other) {
					}
				}
			}
			$cPlant->getIds();
			foreach($this->cSeries as $eSeries) {
				$this->cSeries->sum('area');
			}
			PHP);

		$this->assertEquals([3, 5, 6, 18], $this->scan($tokens, self::getMovingMethods()));

	}

	public function testNoSharedCursor(): void {
		$this->assertRatchet(self::RULE, self::DECLARED, 'collection reparcourue dans sa propre boucle, qui partage son curseur, lire avant la boucle ce qu\'elle consulte (getIds(), getColumn(), first()…), ou parcourir un tableau');
	}

}
