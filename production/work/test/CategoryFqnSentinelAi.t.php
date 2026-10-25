<?php
namespace work;

/**
 * Inventaire des valeurs de `work\Category.fqn` que le code nomme.
 *
 * Une catégorie de travail n'est reconnue que par son fqn. Deux régimes coexistent :
 * - structurelle : series en dépend (culture, production), la catégorie ne se supprime pas, et la
 *   liste est WorkSetting::CATEGORIES_STRUCTURELLES, que lit CategoryLib::delete() ;
 * - affichage : la clé sert à regrouper (Commercialisation, Administration pour les tableaux de
 *   bord), la catégorie reste supprimable et le lecteur doit supporter qu'elle manque.
 * Une valeur nouvelle, dans une constante ou dans une comparaison, oblige à choisir son régime ici.
 * Une valeur déclarée que plus rien ne nomme est signalée comme périmée.
 *
 * Sources lues, par l'analyseur lexical, hors fichiers de test :
 * 1. Les constantes définies dont le nom commence par `CATEGORIE_` (tiret bas compris, pour ne pas
 *    prendre `CATEGORIES_STRUCTURELLES` ni `CATEGORIES_LIMIT`).
 * 2. Les comparaisons d'un sujet `$eCategory…['fqn']` ou `…['category']['fqn']` : opérande d'un
 *    `===`, `!==`, `==` ou `!=`, valeurs littérales d'un `in_array()` qui contient le sujet,
 *    étiquettes `case` d'un `switch` sur le sujet. Une valeur est un texte littéral ou une constante
 *    `CATEGORIE_*`. Un texte suivi de `=>` est une clé de tableau, pas une valeur comparée.
 * 3. Les arguments nommés `fqn:` de `work\CategoryLib::getByStructure()` et `category:` de
 *    `work\ActionLib::getByStructure()`. Le `fqn:` d'ActionLib est un fqn d'action, pas lu.
 *    `\product\CategoryLib` est écarté par le nom complet ou l'espace de noms du fichier.
 *
 * Angles morts, que cette sentinelle ne voit pas :
 * - un sujet sous un autre nom de variable (`$e['fqn']` dans CategoryLib, `$eTimesheet['fqn']`
 *   dans un tableau de bord qui a chargé la clé sous un alias) ;
 * - une valeur passée par variable (`fqn: $category`) ou lue dans une liste constante
 *   (`WorkSetting::CATEGORIES_STRUCTURELLES`, contrôlée à part ci-dessous) ;
 * - les bras d'un `match($eCategory['fqn'])`, que seul le `switch` est lu (aucun au 2026-09-16) ;
 * - une classe importée par `use`, sous son nom ou sous un autre ;
 * - un sujet entre parenthèses, `($eCategory['fqn'] ?? NULL) === 'x'`, la forme que CLAUDE.md
 *   impose pour une clé qui peut manquer : la comparaison suit la parenthèse, pas le sujet ;
 * - une liste de valeurs rendue par une méthode puis comparée (`in_array($row['fqn'], $liste)`),
 *   forme du seul lecteur de commercialisation et administration,
 *   series\VegetableDashboardAiLib::getCategories() ;
 * - un littéral passé à `Category::model()->whereFqn()` ;
 * - un attribut HTML qui transporte la clé vers le JS (`data-fqn` de ActionUi) ;
 * - le sens : une valeur trouvée dit que le code la nomme, pas qu'il en dépend.
 *
 * Aucune lecture ni écriture en base.
 *
 * Lancement : dev/test package=work file=CategoryFqnSentinelAi
 */
class CategoryFqnSentinelAiTest extends \Test {

	const STRUCTURELLE = 'structurelle';
	const AFFICHAGE = 'affichage';

	/**
	 * Valeur => [régime, le code la compare-t-il, raison].
	 *
	 * Les clés sont écrites en clair, et non par leur constante, pour qu'une constante dont la
	 * valeur change soit vue.
	 */
	const DECLARED = [
		'culture' => [self::STRUCTURELLE, TRUE, 'series distingue les interventions de culture (plante, variété, rythme de travail)'],
		'production' => [self::STRUCTURELLE, TRUE, 'series\Task::acceptSoil() la lit, avec culture, pour les interventions qui touchent le sol'],
		'commercialisation' => [self::AFFICHAGE, FALSE, 'regroupement des tableaux de bord, supprimable : le lecteur, series\\VegetableDashboardAiLib::getCategories(), invisible ici, affiche « non renseigné » si elle manque'],
		'administration' => [self::AFFICHAGE, FALSE, 'regroupement des tableaux de bord, supprimable : le lecteur, series\\VegetableDashboardAiLib::getCategories(), invisible ici, affiche « non renseigné » si elle manque'],
		'other' => [self::AFFICHAGE, TRUE, 'comparée par CategoryUi::text(), aucune catégorie ne la porte en base, aucune constante'],
	];

	public function testEveryNamedValueIsDeclared(): void {

		$constants = [];

		foreach(get_defined_constants(TRUE)['user'] as $name => $value) {

			if(str_starts_with($name, 'CATEGORIE_')) {
				$constants[$value][] = $name;
			}

		}

		$sites = $this->scan();

		$found = array_unique(array_merge(array_keys($constants), array_keys($sites)));
		sort($found);

		$undeclared = [];

		foreach(array_diff($found, array_keys(self::DECLARED)) as $value) {
			$undeclared[] = $value.' ('.implode(', ', array_merge($constants[$value] ?? [], $sites[$value] ?? [])).')';
		}

		$this->assertEquals([], $undeclared, 'valeur de fqn de catégorie nommée par le code et non déclarée, choisir son régime dans DECLARED : '.implode(' ; ', $undeclared));

		$stale = array_values(array_diff(array_keys(self::DECLARED), $found));

		$this->assertEquals([], $stale, 'valeur déclarée que le code ne nomme plus, à retirer de DECLARED : '.implode(', ', $stale));

		$compared = [];
		$uncompared = [];

		foreach(self::DECLARED as $value => [, $isCompared]) {

			if($isCompared and isset($sites[$value]) === FALSE) {
				$uncompared[] = $value;
			} else if($isCompared === FALSE and isset($sites[$value])) {
				$compared[] = $value.' ('.implode(', ', $sites[$value]).')';
			}

		}

		$this->assertEquals([], $compared, 'valeur déclarée comme non comparée que le code compare désormais, vérifier qu\'il supporte son absence puis corriger DECLARED : '.implode(' ; ', $compared));
		$this->assertEquals([], $uncompared, 'valeur déclarée comme comparée qu\'aucune comparaison ne nomme plus, corriger DECLARED : '.implode(', ', $uncompared));

	}

	public function testStructuralListMatchesDeclaration(): void {

		$declared = array_keys(array_filter(self::DECLARED, fn(array $entry) => $entry[0] === self::STRUCTURELLE));

		$missing = array_values(array_diff($declared, WorkSetting::CATEGORIES_STRUCTURELLES));
		$this->assertEquals([], $missing, 'catégorie déclarée structurelle absente de WorkSetting::CATEGORIES_STRUCTURELLES, donc supprimable : '.implode(', ', $missing));

		$extra = array_values(array_diff(WorkSetting::CATEGORIES_STRUCTURELLES, $declared));
		$this->assertEquals([], $extra, 'catégorie de WorkSetting::CATEGORIES_STRUCTURELLES non déclarée structurelle : '.implode(', ', $extra));

	}

	/**
	 * Les valeurs comparées ou passées en argument, avec leurs sites `chemin:ligne`.
	 *
	 * @return array<string, string[]>
	 */
	private function scan(): array {

		$sites = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $path => $file) {

			if(str_ends_with($path, '.t.php')) {
				continue;
			}

			$code = file_get_contents($file);

			if(str_contains($code, 'fqn') === FALSE and str_contains($code, 'getByStructure') === FALSE) {
				continue;
			}

			$tokens = \dev\SentinelLib::getTokens($code);

			$positions = array_merge(
				$this->scanSubjects($tokens),
				$this->scanArguments($tokens, \dev\SentinelLib::getNamespace($tokens))
			);

			foreach(array_unique($positions) as $position) {

				$value = $this->getValue($tokens, $position);
				$sites[$value][] = $path.':'.$tokens[$position][2];

			}

		}

		ksort($sites);

		return $sites;

	}

	/**
	 * Les positions des valeurs comparées à un sujet.
	 *
	 * @return int[]
	 */
	private function scanSubjects(array $tokens): array {

		$subjects = [];

		foreach($tokens as $i => [$type, $text]) {

			if(
				$type !== T_CONSTANT_ENCAPSED_STRING or
				trim($text, '\'"') !== 'fqn' or
				($tokens[$i - 1][0] ?? NULL) !== '[' or
				($tokens[$i + 1][0] ?? NULL) !== ']'
			) {
				continue;
			}

			$before = $tokens[$i - 2] ?? [NULL, ''];

			if($before[0] === T_VARIABLE and str_starts_with($before[1], '$eCategory')) {
				$subjects[] = [$i - 2, $i + 1];
			} else if(
				$before[0] === ']' and
				trim($tokens[$i - 3][1] ?? '', '\'"') === 'category' and
				($tokens[$i - 4][0] ?? NULL) === '['
			) {
				$subjects[] = [$i - 5, $i + 1];
			}

		}

		if($subjects === []) {
			return [];
		}

		$positions = [];
		$comparisons = [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL];

		foreach($subjects as [$start, $end]) {

			if(in_array($tokens[$end + 1][0] ?? NULL, $comparisons, TRUE) and $this->isValue($tokens, $end + 2)) {
				$positions[] = $end + 2;
			}

			if(in_array($tokens[$start - 1][0] ?? NULL, $comparisons, TRUE) and $this->isValue($tokens, $start - 2)) {
				$positions[] = $start - 2;
			}

		}

		$isInside = function(int $from, int $to) use($subjects): bool {

			foreach($subjects as [$start, $end]) {

				if($start > $from and $end < $to) {
					return TRUE;
				}

			}

			return FALSE;

		};

		foreach($tokens as $i => [$type, $text]) {

			if($type === T_STRING and $text === 'in_array' and ($tokens[$i + 1][0] ?? NULL) === '(') {

				$close = \dev\SentinelLib::getClosing($tokens, $i + 1);

				if($isInside($i + 1, $close)) {

					for($position = $i + 2; $position < $close; $position++) {

						if($this->isValue($tokens, $position) and $this->isArrayKey($tokens, $position) === FALSE) {
							$positions[] = $position;
						}

					}

				}

			} else if($type === T_SWITCH and ($tokens[$i + 1][0] ?? NULL) === '(') {

				$close = \dev\SentinelLib::getClosing($tokens, $i + 1);

				if($isInside($i + 1, $close) and ($tokens[$close + 1][0] ?? NULL) === '{') {

					$bodyClose = \dev\SentinelLib::getClosing($tokens, $close + 1);

					for($position = $close + 2; $position < $bodyClose; $position++) {

						if($tokens[$position][0] === T_CASE and $this->isValue($tokens, $position + 1)) {
							$positions[] = $position + 1;
						}

					}

				}

			}

		}

		return $positions;

	}

	/**
	 * Les positions des valeurs passées par `fqn:` à work\CategoryLib::getByStructure() et par
	 * `category:` à work\ActionLib::getByStructure().
	 *
	 * @return int[]
	 */
	private function scanArguments(array $tokens, string $namespace): array {

		$arguments = [
			'CategoryLib' => 'fqn',
			'ActionLib' => 'category',
		];

		$positions = [];

		foreach($tokens as $i => [$type, $text]) {

			$class = match($type) {
				T_NAME_FULLY_QUALIFIED => str_starts_with($text, '\\work\\') ? substr($text, 6) : NULL,
				T_STRING => ($namespace === 'work') ? $text : NULL,
				default => NULL
			};

			if(
				isset($arguments[$class]) === FALSE or
				($tokens[$i + 1][0] ?? NULL) !== T_DOUBLE_COLON or
				($tokens[$i + 2][1] ?? NULL) !== 'getByStructure' or
				($tokens[$i + 3][0] ?? NULL) !== '('
			) {
				continue;
			}

			$close = \dev\SentinelLib::getClosing($tokens, $i + 3);
			$depth = 0;
			$reading = FALSE;

			for($position = $i + 4; $position < $close; $position++) {

				$current = $tokens[$position][0];

				if($current === '(' or $current === '[') {
					$depth++;
				} else if($current === ')' or $current === ']') {
					$depth--;
				} else if($depth === 0 and $current === ',') {
					$reading = FALSE;
				} else if(
					$depth === 0 and
					$current === T_STRING and
					$tokens[$position][1] === $arguments[$class] and
					($tokens[$position + 1][0] ?? NULL) === ':'
				) {
					$reading = TRUE;
					$position++;
				} else if($reading and $this->isValue($tokens, $position) and $this->isArrayKey($tokens, $position) === FALSE) {
					$positions[] = $position;
				}

			}

		}

		return $positions;

	}

	private function isValue(array $tokens, int $position): bool {

		[$type, $text] = $tokens[$position] ?? [NULL, ''];

		if($type === T_CONSTANT_ENCAPSED_STRING) {
			return TRUE;
		}

		return (
			$type === T_STRING and
			str_starts_with($text, 'CATEGORIE_') and
			($tokens[$position - 1][0] ?? NULL) !== T_DOUBLE_COLON and
			($tokens[$position + 1][0] ?? NULL) !== T_DOUBLE_COLON
		);

	}

	/** Un texte qui indexe un tableau (`$x['clé']`) ou qui nomme une clé (`'clé' => …`) n'est pas une valeur de fqn. */
	private function isArrayKey(array $tokens, int $position): bool {

		if(($tokens[$position + 1][0] ?? NULL) === T_DOUBLE_ARROW) {
			return TRUE;
		}

		return (
			($tokens[$position - 1][0] ?? NULL) === '[' and
			in_array($tokens[$position - 2][0] ?? NULL, [T_VARIABLE, T_STRING, ']', ')'], TRUE)
		);

	}

	private function getValue(array $tokens, int $position): string {

		[$type, $text] = $tokens[$position];

		if($type === T_CONSTANT_ENCAPSED_STRING) {
			return trim($text, '\'"');
		}

		return defined($text) ? (string)constant($text) : $text.' (constante non définie)';

	}

}
?>
