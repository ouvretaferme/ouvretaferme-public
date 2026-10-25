<?php
namespace design;

/**
 * Test-sentinelle : un booléen de formulaire se rend en oui / non, jamais en case à cocher seule.
 *
 * Décidé le 2026-10-01 : « remplacer de manière générale les cases à cocher par
 * yesNo ». La convention est dans le skill `ui`, sa démonstration sur la page du design system
 * (`#convention-yes-no`).
 *
 * `FormUi::createDynamicField()` rend une propriété `bool` en case à cocher dès que le describer
 * ne lui pose pas de `field` (`framework/util/ui/Form.u.php`, `if($type === 'bool')`). La
 * sentinelle croise donc deux lectures :
 * - à l'exécution, les propriétés `bool` de chaque module dont le describer `p()` laisse `field`
 *   à son défaut ;
 * - dans le code, les appels `dynamicGroup()`, `dynamicGroups()` et `dynamicField()` des `.u.php`
 *   et `.v.php` du même package qui nomment une de ces propriétés.
 *
 * Le describer seul ne suffit pas : mesuré sur `origin/main` à `244e1ff1f` le 2026-10-01, 123
 * propriétés `bool` gardent le rendu par défaut, et la plupart ne passent jamais par un champ
 * dynamique (une colonne lue, un interrupteur, un libellé repris à l'affichage). L'appel dit le
 * module par sa variable, que le dépôt nomme d'après lui (`$eStaff`, `$eStaffNew`), et sinon par
 * son fichier (`Staff.u.php`).
 *
 * L'existant est déclaré par fichier, en nombre, et ne peut que baisser. Il est pris sur
 * `origin/main`, jamais sur l'arbre de travail : 3 emplois dans 2 fichiers à `244e1ff1f` le
 * 2026-10-01, dont il reste la case « Fin de mois » de l'échéance des factures. Les deux autres
 * étaient les cases de la fiche d'un encadrant, passées en oui / non avec la décision et sorties
 * de la déclaration dans le même commit.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - une case posée à la main, `$form->checkbox('nom')` ou `$form->inputCheckbox('nom')` (22 et 7
 *   emplois à nom littéral sur `origin/main`) : aucune forme n'y sépare un booléen du schéma
 *   d'un consentement, d'un filtre de recherche ou d'une sélection de lignes ;
 * - une liste de propriétés passée par une variable (`dynamicGroups($e, $properties)`) ;
 * - un appel dont la variable ne dit pas le module, dans un fichier qui porte un autre nom ;
 * - un appel dont la closure pose elle-même un `field` : il est écarté, le rendu se décidant là.
 *
 * Lancement : dev/test package=design file=BooleanFieldSentinelAi
 */
class BooleanFieldSentinelAiTest extends \dev\SentinelTest {

	const RULE = 'booleanField';

	const CALLS = ['dynamicGroup', 'dynamicGroups', 'dynamicField'];

	const DECLARED = [
		'commercialisation/selling/ui/Configuration.u.php' => 1,
	];

	/**
	 * Le nombre de propriétés `bool` au rendu par défaut lues à l'exécution, pour prouver que la
	 * lecture a eu lieu : à zéro, la règle serait verte sans avoir rien regardé.
	 */
	private int $bare = 0;

	public function init() {

		$bare = $this->getBareBooleans();

		foreach(\dev\SentinelLib::getFiles('.u.php') + \dev\SentinelLib::getFiles('.v.php') as $file => $path) {

			if(preg_match('#^(.+)/(?:ui|view)/([A-Za-z0-9]+)\.[uv]\.php$#', $file, $matches) !== 1 or isset($bare[$matches[1]]) === FALSE) {
				continue;
			}

			[, $directory, $basename] = $matches;

			$lines = [];

			foreach(self::findDynamicCalls(\dev\SentinelLib::getTokens(file_get_contents($path))) as [$variable, $properties]) {

				$module = self::getModule($variable, $basename, $bare[$directory]['modules']);

				foreach($properties as $property => $line) {

					if($module !== NULL and isset($bare[$directory]['properties'][$module][$property])) {
						$lines[] = $line;
					}

				}

			}

			$this->addFound(self::RULE, $file, $lines);

		}

	}

	/**
	 * Par répertoire de package : les modules qu'il porte, et pour chacun ses propriétés `bool`
	 * dont le describer laisse `field` à son défaut.
	 *
	 * @return array<string, array{modules: string[], properties: array<string, array<string, bool>>}>
	 */
	private function getBareBooleans(): array {

		$bare = [];

		foreach(array_keys(\dev\SentinelLib::getFiles('.m.php')) as $file) {

			if(preg_match('#^((?:.+/)?([a-zA-Z0-9]+))/module/([A-Za-z0-9]+)\.m\.php$#', $file, $matches) !== 1) {
				continue;
			}

			[, $directory, $package, $module] = $matches;

			$class = '\\'.$package.'\\'.$module;
			$ui = $class.'Ui';

			if(class_exists($class) === FALSE or method_exists($class, 'model') === FALSE) {
				continue;
			}

			$bare[$directory]['modules'][] = $module;
			$bare[$directory]['properties'] ??= [];

			if(class_exists($ui) === FALSE or method_exists($ui, 'p') === FALSE) {
				continue;
			}

			$model = $class::model();

			foreach($model->getProperties() as $property) {

				if($model->getPropertyType($property) === 'bool' and self::isDefaultField($ui::p($property)->field)) {
					$bare[$directory]['properties'][$module][$property] = TRUE;
					$this->bare++;
				}

			}

		}

		return $bare;

	}

	/**
	 * Un `field` que `createDynamicField()` laisse au type de la propriété : absent, ou `default`.
	 */
	public static function isDefaultField(mixed $field): bool {
		return ($field === NULL or $field === 'default');
	}

	/**
	 * Les appels à un champ dynamique : la variable de leur premier argument, et les propriétés
	 * qu'ils nomment en clair avec leur ligne, sans l'astérisque ni l'index qui les suivent. Une
	 * chaîne qui indexe un tableau (`$eShop['shared']`) est une lecture, pas une propriété nommée.
	 * Un appel dont un argument écrit `->field` est écarté : sa closure décide du rendu.
	 *
	 * @return array<int, array{0: ?string, 1: array<string, int>}>
	 */
	public static function findDynamicCalls(array $tokens): array {

		$calls = [];

		foreach($tokens as $position => [$type, $text]) {

			if(
				$type !== T_STRING or
				in_array($text, self::CALLS, TRUE) === FALSE or
				($tokens[$position - 1][0] ?? NULL) !== T_OBJECT_OPERATOR or
				($tokens[$position + 1][0] ?? NULL) !== '('
			) {
				continue;
			}

			$closing = \dev\SentinelLib::getClosing($tokens, $position + 1);
			$variable = ($tokens[$position + 2][0] === T_VARIABLE) ? $tokens[$position + 2][1] : NULL;

			$properties = [];
			$decided = FALSE;

			for($i = $position + 2; $i < $closing; $i++) {

				$isIndex = ($tokens[$i - 1][0] === '[' and $tokens[$i + 1][0] === ']' and in_array($tokens[$i - 2][0], [T_VARIABLE, ']', ')'], TRUE));

				if($isIndex === FALSE and $tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING and preg_match('/^[\'"]([a-zA-Z][a-zA-Z0-9]*)(?:\[[^\]]*\])?\*?[\'"]$/', $tokens[$i][1], $matches) === 1) {
					$properties[$matches[1]] ??= $tokens[$i][2];
				} else if($tokens[$i][0] === T_STRING and $tokens[$i][1] === 'field' and $tokens[$i - 1][0] === T_OBJECT_OPERATOR) {
					$decided = TRUE;
				}

			}

			if($decided === FALSE) {
				$calls[] = [$variable, $properties];
			}

		}

		return $calls;

	}

	/**
	 * Le module qu'un appel désigne : le plus long nom de module du package qui ouvre le nom de la
	 * variable après `$e` (`$eStaffNew` est un `Staff`), sinon celui du fichier quand il en porte un.
	 *
	 * @param string[] $modules
	 */
	public static function getModule(?string $variable, string $basename, array $modules): ?string {

		$found = NULL;

		if($variable !== NULL and str_starts_with($variable, '$e')) {

			foreach($modules as $module) {

				if(str_starts_with(substr($variable, 2), $module) and strlen($module) > strlen($found ?? '')) {
					$found = $module;
				}

			}

		}

		if($found === NULL and in_array($basename, $modules, TRUE)) {
			$found = $basename;
		}

		return $found;

	}

	public function testDetectors(): void {

		$code = <<<'PHP'
			<?php
			$h .= $form->dynamicGroups($eStaff, ['name*', 'contact',
				'handicap']);
			$h .= $form->dynamicGroup($eCourseDate, 'open[12]*'.($eCourseDate['shared'] ? '*' : ''));
			$h .= $form->dynamicGroups($eStaff, ['contact']);
			$h .= $form->dynamicGroup($e, 'external', function(\PropertyDescriber $d) {
				$d->field = 'yesNo';
			});
			$h .= $form->dynamicField($this->getElement(), 'active');
			$h .= $form->group(s("Contact"), $form->checkbox('contact'));
			$h .= self::dynamicGroup($eStaff, 'contact');
			PHP;

		$this->assertEquals([
			['$eStaff', ['name' => 2, 'contact' => 2, 'handicap' => 3]],
			['$eCourseDate', ['open' => 4]],
			['$eStaff', ['contact' => 5]],
			['$this', ['active' => 9]],
		], self::findDynamicCalls(\dev\SentinelLib::getTokens(str_replace("\t", '', $code))));

		$modules = ['Course', 'CourseDate', 'Staff'];

		$this->assertEquals('Staff', self::getModule('$eStaff', 'Course', $modules));
		$this->assertEquals('Staff', self::getModule('$eStaffNew', 'Course', $modules));
		$this->assertEquals('CourseDate', self::getModule('$eCourseDate', 'Staff', $modules));
		$this->assertEquals('Course', self::getModule('$e', 'Course', $modules));
		$this->assertEquals('Course', self::getModule('$this', 'Course', $modules));
		$this->assertEquals('Course', self::getModule(NULL, 'Course', $modules));
		$this->assertEquals('Staff', self::getModule('$element', 'Staff', $modules));
		$this->assertEquals(NULL, self::getModule('$e', 'Design', $modules));

		$this->assertTrue(self::isDefaultField(NULL));
		$this->assertTrue(self::isDefaultField('default'));
		$this->assertTrue(self::isDefaultField('yesNo') === FALSE);
		$this->assertTrue(self::isDefaultField('switch') === FALSE);
		$this->assertTrue(self::isDefaultField(fn() => '') === FALSE);

	}

	public function testBooleanField(): void {

		$this->assertTrue($this->bare >= 50, 'les describers ne se lisent plus ('.$this->bare.' propriétés bool au rendu par défaut lues, 123 le 2026-10-01) : aucune case à cocher ne paraîtrait');

		$this->assertRatchet(self::RULE, self::DECLARED, 'propriété bool rendue en case à cocher par un champ dynamique (le code fait foi : poser $d->field = \'yesNo\' dans le describer p() de la propriété, et non dans l\'appel ; un fichier neuf ne se déclare pas, et un fichier déjà déclaré ne monte pas)');

	}

}
?>
