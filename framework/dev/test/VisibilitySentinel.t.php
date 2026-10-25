<?php
namespace dev;

/**
 * Une classe ne restreint jamais la visibilité d'une méthode que sa parente déclare.
 *
 * PHP en fait une erreur fatale au chargement de la classe, pas une exception : « Access level to
 * X::f() must be protected (as in class Y) or weaker ». Le fichier ne se charge plus, et rien
 * n'attrape ça, puisqu'il n'y a plus de programme pour l'attraper.
 *
 * ⚠️ **Ce qui rend le contrôle nécessaire, c'est où le défaut se cache.** Dans le code du produit,
 * une classe qui ne charge plus se voit au premier écran ouvert. Dans un fichier de test, elle
 * n'apparaît que le jour où quelqu'un lance les tests de ce paquet, et les sentinelles ne la voient
 * pas : l'erreur est à la compilation d'un fichier qu'elles ne chargent pas. Mesuré le 2026-09-29,
 * `commercialisation/shop/test/SaleSharedCancelAi.t.php` déclarait `private function createOffer()`
 * quand sa grand-mère `ShopPageFixtureAi.t.php:63` déclarait `protected function createOffer()`, avec
 * des signatures sans rapport : le paquet `shop` rendait 4 classes puis une erreur fatale, au lieu de
 * 39 classes et 2 118 assertions, et le défaut vivait dans `origin/main` depuis `cf1afb656`.
 *
 * La bonne correction est un renommage chez l'enfant, jamais un alignement de visibilité : deux
 * méthodes qui n'ont ni le même rôle ni la même signature ne sont pas une surcharge, et les aligner
 * déplace l'erreur sur l'incompatibilité de signature ou déclare une surcharge que personne n'a
 * voulue.
 *
 * Lancement : dev/test package=dev file=VisibilitySentinel
 */
class VisibilitySentinelTest extends \dev\SentinelTest {

	private const RANKS = ['public' => 0, 'protected' => 1, 'private' => 2];

	/**
	 * Les classes du dépôt, par nom pleinement qualifié.
	 *
	 * @return array<string, array{parent: ?string, file: string, methods: array<string, array{0: string, 1: int}>}>
	 */
	private function getClasses(): array {

		$classes = [];

		foreach(SentinelLib::getFiles('.php') as $file => $path) {

			$tokens = SentinelLib::getTokens(file_get_contents($path));
			$namespace = SentinelLib::getNamespace($tokens);
			$aliases = $this->getAliases($tokens);

			foreach($tokens as $i => [$type, $text, $line]) {

				// `Foo::class` et `new class` portent aussi T_CLASS : seul un nom qui suit en fait une
				// déclaration, et une classe anonyme ne peut être la parente de personne ici.
				if($type !== T_CLASS or ($tokens[$i + 1][0] ?? NULL) !== T_STRING) {
					continue;
				}

				$name = ($namespace === '' ? '' : $namespace.'\\').$tokens[$i + 1][1];
				$parent = NULL;
				$body = NULL;

				for($j = $i + 2; $j < count($tokens); $j++) {

					if($tokens[$j][0] === T_EXTENDS) {
						$parent = $this->resolve($tokens[$j + 1][1], $namespace, $aliases);
					}

					if($tokens[$j][0] === '{') {
						$body = $j;
						break;
					}

				}

				if($body === NULL) {
					continue;
				}

				$classes[$name] = [
					'parent' => $parent,
					'file' => $file,
					'methods' => $this->getMethods($tokens, $body, SentinelLib::getClosing($tokens, $body)),
				];

			}

		}

		return $classes;

	}

	/**
	 * Les alias d'importation du fichier, pour résoudre un `extends` écrit sans son chemin.
	 *
	 * @return array<string, string>
	 */
	private function getAliases(array $tokens): array {

		$aliases = [];

		foreach($tokens as $i => [$type, $text]) {

			if($type !== T_USE) {
				continue;
			}

			// Un `use` de trait ou de closure vit dans un corps : seul celui du fichier suit un `;`,
			// un `}` ou l'ouverture, et porte un nom qualifié.
			$previous = $tokens[$i - 1][0] ?? NULL;

			if(in_array($previous, [';', '}', T_OPEN_TAG, T_NAMESPACE], TRUE) === FALSE and $previous !== NULL) {
				continue;
			}

			$parts = [];

			for($j = $i + 1; $j < count($tokens); $j++) {

				if($tokens[$j][0] === ';' or $tokens[$j][0] === '{') {
					break;
				}

				$parts[] = $tokens[$j];

			}

			$names = array_values(array_filter($parts, fn($t) => $t[0] === T_STRING or $t[0] === T_NAME_QUALIFIED or $t[0] === T_NAME_FULLY_QUALIFIED));

			if($names === []) {
				continue;
			}

			$full = ltrim($names[0][1], '\\');
			$alias = count($names) > 1 ? $names[count($names) - 1][1] : substr($full, (int)strrpos('\\'.$full, '\\'));

			$aliases[ltrim($alias, '\\')] = $full;

		}

		return $aliases;

	}

	private function resolve(string $name, string $namespace, array $aliases): string {

		if(str_starts_with($name, '\\')) {
			return ltrim($name, '\\');
		}

		$first = explode('\\', $name)[0];

		if(isset($aliases[$first])) {
			return $aliases[$first].substr($name, strlen($first));
		}

		return ($namespace === '' ? '' : $namespace.'\\').$name;

	}

	/**
	 * Les méthodes déclarées dans le corps d'une classe, avec leur visibilité et leur ligne.
	 *
	 * @return array<string, array{0: string, 1: int}>
	 */
	private function getMethods(array $tokens, int $start, int $end): array {

		$methods = [];
		$depth = 0;

		for($i = $start; $i <= $end; $i++) {

			$type = $tokens[$i][0];

			if($type === '{' or $type === T_CURLY_OPEN or $type === T_DOLLAR_OPEN_CURLY_BRACES) {
				$depth++;
				continue;
			}

			if($type === '}') {
				$depth--;
				continue;
			}

			// Profondeur 1 : le corps de la classe. Plus bas, on est dans une méthode, donc dans une
			// closure ou une fonction imbriquée, qui n'est pas une méthode.
			if($depth !== 1 or $type !== T_FUNCTION or ($tokens[$i + 1][0] ?? NULL) !== T_STRING) {
				continue;
			}

			$visibility = 'public';

			for($j = $i - 1; $j >= $start; $j--) {

				$modifier = $tokens[$j][0];

				if(in_array($modifier, [T_PRIVATE, T_PROTECTED, T_PUBLIC], TRUE)) {
					$visibility = strtolower($tokens[$j][1]);
					break;
				}

				if(in_array($modifier, [T_STATIC, T_FINAL, T_ABSTRACT], TRUE) === FALSE) {
					break;
				}

			}

			$methods[$tokens[$i + 1][1]] = [$visibility, $tokens[$i + 1][2]];

		}

		return $methods;

	}

	public function testNoChildNarrowsAParentMethod(): void {

		$classes = $this->getClasses();
		$found = [];

		foreach($classes as $name => $class) {

			foreach($class['methods'] as $method => [$visibility, $line]) {

				$parent = $class['parent'];
				$seen = [];

				while($parent !== NULL and isset($classes[$parent]) and isset($seen[$parent]) === FALSE) {

					$seen[$parent] = TRUE;
					$inherited = $classes[$parent]['methods'][$method] ?? NULL;

					if($inherited !== NULL) {

						if(self::RANKS[$visibility] > self::RANKS[$inherited[0]]) {
							$found[] = $class['file'].':'.$line.' '.$method.'() en '.$visibility.', quand '.$classes[$parent]['file'].':'.$inherited[1].' la déclare '.$inherited[0];
						}

						break;

					}

					$parent = $classes[$parent]['parent'];

				}

			}

		}

		sort($found);

		$this->assertEquals([], $found, 'une classe restreint la visibilité d\'une méthode de sa parente, ce qui est une erreur fatale au chargement ; la correction est un renommage chez l\'enfant quand les deux méthodes n\'ont rien à voir, jamais un alignement de visibilité : '.implode(' · ', $found));

	}

}
?>
