<?php
namespace dev;

/**
 * Règle du « Style PHP » du CLAUDE.md : une condition qui garde une instruction s'écrit `if`,
 * jamais `$condition and $action;` ni `$condition or $action;`.
 *
 * Une instruction dont un `and` ou un `or` est hors de toute parenthèse a cet opérateur pour
 * opérateur de tête, quel que soit ce qui l'entoure. Restent légitimes, parce que l'opérateur y
 * appartient à la valeur : `return`, `echo`, `print`, `yield`, une constante, et le corps d'une
 * fonction fléchée.
 *
 * Lancement : dev/test package=dev file=GuardStatementSentinel
 */
class GuardStatementSentinelTest extends \Test {

	/**
	 * Les lignes des instructions dont l'opérateur de tête est `and` ou `or`.
	 *
	 * Chaque accolade ouvre un contexte d'instructions, ce qui fait lire aussi le corps d'une closure
	 * passée en argument. Dans un contexte, seules comptent les parenthèses et les crochets : un
	 * opérateur à l'intérieur appartient à une sous-expression.
	 *
	 * @return int[]
	 */
	private function scan(string $code): array {

		$lines = [];
		$contexts = [];

		$reset = function(array &$context): void {
			$context['first'] = NULL;
			$context['arrow'] = FALSE;
			$context['line'] = NULL;
		};

		$open = function() use (&$contexts, $reset): void {
			$context = ['depth' => 0];
			$reset($context);
			$contexts[] = $context;
		};

		// Les instructions dont l'accolade ferme un bloc, et non une valeur comme une closure ou un match
		$blocks = [
			T_IF, T_ELSE, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_TRY, T_CATCH, T_FINALLY, T_DECLARE,
			T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_NAMESPACE,
			T_ABSTRACT, T_FINAL, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_READONLY
		];

		$open();

		foreach(token_get_all($code) as $token) {

			$type = is_array($token) ? $token[0] : $token;

			if(in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_INLINE_HTML], TRUE)) {
				continue;
			}

			$context = &$contexts[array_key_last($contexts)];

			if($type === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$open();
				unset($context);
				continue;
			}

			if($type === '}') {

				unset($context);
				array_pop($contexts);

				$parent = &$contexts[array_key_last($contexts)];

				if(in_array($parent['first'], $blocks, TRUE)) {
					$reset($parent);
				}

				unset($parent);
				continue;

			}

			$context['first'] ??= $type;

			if($type === '(' or $type === '[' or $type === T_ATTRIBUTE) {
				$context['depth']++;
			} else if($type === ')' or $type === ']') {
				$context['depth']--;
			} else if($context['depth'] === 0) {

				if($type === ';' or $type === T_CLOSE_TAG) {

					if(
						$context['line'] !== NULL and
						in_array($context['first'], [T_RETURN, T_ECHO, T_PRINT, T_YIELD, T_CONST], TRUE) === FALSE
					) {
						$lines[] = $context['line'];
					}

					$reset($context);

				} else if($type === ':' and in_array($context['first'], [T_CASE, T_DEFAULT], TRUE)) {
					$reset($context);
				} else if($type === T_FN) {
					$context['arrow'] = TRUE;
				} else if(
					($type === T_LOGICAL_AND or $type === T_LOGICAL_OR) and
					$context['arrow'] === FALSE
				) {
					$context['line'] ??= $token[2];
				}

			}

			unset($context);

		}

		return $lines;

	}

	/** Le scanner trouve chaque forme de l'idiome, et rien de ce qui est légitime. */
	public function testScannerFindsGuard(): void {

		$lines = $this->scan(<<<'PHP'
			<?php
			class Fixture {
				public function a($a, $b, $h) {
					$a === '' or $h .= '<p>'.$a.'</p>';
					$hide = $a === FALSE or $b === TRUE;
					$a and throw new \Exception();
					array_map(function($c) { $c or $d = 1; }, []);
					if($a):
						$b and $h = 2;
					endif;
					$h = ($a and $b);
					if($a and $b) {
						$h = "{$a['x']}".($a or $b);
					}
					for($i = 0; $i < 1 and $b; $i++) {
					}
					$f = fn($c) => $c !== NULL and $c < 10;
					$g = match(TRUE) {
						$a and $b => 1,
						default => 2,
					};
					switch($a) {
						case 1:
							$b or $h = 3;
							break;
					}
					#[\Deprecated]
					$b and $h = 4;
					echo $a and $b;
					return $a and $b;
				}
				const X = TRUE and FALSE;
			}
			PHP);

		$this->assertEquals([4, 5, 6, 7, 9, 24, 28], $lines, 'les sept gardes sont trouvées, rien d\'autre');

	}

	public function testNoGuardStatement(): void {

		$found = [];

		foreach(SentinelLib::getFiles('.php') as $relative => $path) {
			foreach($this->scan(file_get_contents($path)) as $line) {
				$found[] = $relative.':'.$line;
			}
		}

		$this->assertEquals([], $found, 'condition qui garde une instruction, à écrire en if : '.implode(', ', $found));

	}

}
