<?php
namespace editor;

/**
 * Une propriété typée `editor` s'affiche par `new \editor\EditorUi()->value()`, jamais concaténée
 * telle quelle : la base porte le XML de l'éditeur, que seul `value()` traduit.
 *
 * Une lecture `$eXxx['propriete']` est fautive quand un `.`, un `.=`, un `echo` ou une interpolation
 * la touche directement ; passée en argument à une fonction, elle ne l'est pas.
 *
 * Ne sont lus que les fichiers que git suit : `explainUntracked()` nomme ceux que le motif attrape
 * hors suivi, et qui sont écartés.
 *
 * Lancement : dev/test package=editor file=EditorValueSentinel
 */
class EditorValueSentinelTest extends \Test {

	/**
	 * Les fichiers lus, dans chaque package de `\Package::getList()`, depuis le répertoire du package.
	 */
	const PATTERNS = ['ui/*.u.php', 'view/*.v.php'];

	/**
	 * Les propriétés de type editor, par nom court de classe.
	 * @return array<string, string[]>
	 */
	private function getEditorProperties(): array {

		$properties = [];

		foreach(\Package::getList() as $package => $app) {

			foreach(glob(\Package::getPath($package).'/module/*.m.php') as $file) {

				$name = basename($file, '.m.php');
				$class = '\\'.$package.'\\'.$name;

				if(class_exists($class) === FALSE or is_subclass_of($class, \Element::class) === FALSE) {
					continue;
				}

				$model = $class::model();

				foreach($model->getProperties() as $property) {
					if(str_starts_with((string)$model->getPropertyType($property), 'editor')) {
						$properties[$name][] = $property;
					}
				}

			}

		}

		return $properties;

	}

	/**
	 * Les fichiers de rendu du dépôt que le motif attrape, indexés par leur chemin depuis la racine,
	 * et, à part, ceux que git ne suit pas.
	 *
	 * Un fichier non suivi n'existe que dans l'arbre qui l'écrit, et deux postes alignés sur le même
	 * commit n'en portent pas les mêmes : 13 sur l'un, aucun sur l'autre le 2026-09-30. Le lire
	 * rendait un verdict qui dépend du poste (conseil des gardiens,
	 * `2026-09-29-artefact-derive-se-perime`, piste 2).
	 *
	 * @return array{tracked: array<string, string>, untracked: string[]}
	 */
	private function getFiles(): array {

		$files = [
			'tracked' => [],
			'untracked' => [],
		];

		$tracked = \dev\SentinelLib::getTracked();

		foreach(\Package::getList() as $package => $app) {

			$path = \Package::getPath($package);

			foreach(self::PATTERNS as $pattern) {

				foreach(glob($path.'/'.$pattern) as $file) {

					$relative = substr($file, strlen(\dev\SentinelLib::getRoot()) + 1);

					if($tracked === NULL or isset($tracked[$relative])) {
						$files['tracked'][$relative] = $file;
					} else {
						$files['untracked'][] = $relative;
					}

				}

			}

		}

		ksort($files['tracked']);
		sort($files['untracked']);

		return $files;

	}

	/**
	 * La fin du message d'échec, qui nomme les fichiers du motif écartés parce que git ne les suit pas :
	 * celui qui vient d'en écrire un doit savoir qu'il n'a pas été lu.
	 *
	 * @param string[] $untracked
	 */
	private function explainUntracked(array $untracked): string {

		if($untracked === []) {
			return '';
		}

		return ' [<package>/'.implode(' et <package>/', self::PATTERNS).', '.count($untracked).' non suivi(s) écarté(s) : '.implode(', ', $untracked).' ; un fichier neuf compte une fois ajouté à l\'index par `git add -N`]';

	}

	/**
	 * Les lectures d'une propriété editor concaténées telles quelles : ligne et `Classe::propriete`.
	 *
	 * @param array<string, string[]> $editor
	 * @return string[]
	 */
	private function scan(string $code, array $editor): array {

		$tokens = array_values(array_filter(
			token_get_all($code),
			fn($token) => (is_array($token) === FALSE or in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE) === FALSE)
		));

		$type = fn(int $index) => ($index < 0 or $index >= count($tokens)) ? NULL : (is_array($tokens[$index]) ? $tokens[$index][0] : $tokens[$index]);

		// Le plus long nom de classe d'abord, pour que `$eSaleLast` ne soit pas lu `Sale` + `Last` quand une classe `SaleLast` existe
		$classes = array_keys($editor);
		usort($classes, fn($a, $b) => strlen($b) <=> strlen($a));

		$found = [];

		foreach($tokens as $index => $token) {

			$variable = match($type($index)) {
				T_VARIABLE => substr($token[1], 1),
				T_STRING => ($type($index - 1) === T_OBJECT_OPERATOR and $type($index - 2) === T_VARIABLE) ? $token[1] : NULL,
				default => NULL
			};

			if(
				$variable === NULL or
				$type($index + 1) !== '[' or
				$type($index + 2) !== T_CONSTANT_ENCAPSED_STRING or
				$type($index + 3) !== ']'
			) {
				continue;
			}

			$property = substr($tokens[$index + 2][1], 1, -1);
			$first = ($type($index) === T_STRING) ? $index - 2 : $index;

			foreach($classes as $class) {

				if(
					preg_match('/^e'.$class.'([A-Z]|$)/', $variable) !== 1 or
					in_array($property, $editor[$class], TRUE) === FALSE
				) {
					continue;
				}

				if(
					in_array($type($first - 1), ['.', T_CONCAT_EQUAL, T_ECHO, T_PRINT, T_OPEN_TAG_WITH_ECHO, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE) or
					$type($index + 4) === '.'
				) {
					$found[] = $tokens[$index + 2][2].' '.$class.'::'.$property;
				}

				break;

			}

		}

		return $found;

	}

	/** Le scanner trouve chaque concaténation, et rien de ce qui passe par une fonction. */
	public function testScannerFindsRawValue(): void {

		$found = $this->scan(<<<'PHP'
			<?php
			class Fixture {
				public function a($eCommunity, $eShopLast, $eShop, $data) {
					$h = '<div>'.$eCommunity['rules'].'</div>';
					$h .= $this->eCommunity['quotationHelp'];
					$h = $data->eShop['terms'].'</div>';
					echo $eShopLast['description'];
					$h = "<p>{$eShop['terms']}</p>";
					$h = '<div>'.new \editor\EditorUi()->value($eCommunity['rules']).'</div>';
					$h = '<p>'.mb_strlen(strip_tags($eShop['terms'])).'</p>';
					$h = '<p>'.$eShop['name'].'</p>';
					$h = '<p>'.$eCommunityRules['rules'].'</p>';
					if($eCommunity['rules'] === NULL) {
					}
				}
			}
			PHP, ['Community' => ['rules', 'quotationHelp'], 'Shop' => ['description', 'terms']]);

		$this->assertEquals([
			'4 Community::rules',
			'5 Community::quotationHelp',
			'6 Shop::terms',
			'7 Shop::description',
			'8 Shop::terms',
			'12 Community::rules',
		], $found, 'les concaténations sont trouvées, les appels de fonction ignorés');

	}

	public function testNoRawEditorValue(): void {

		$editor = $this->getEditorProperties();

		$this->assertTrue(isset($editor['Community']) and in_array('rules', $editor['Community'], TRUE), 'les propriétés editor sont lues depuis les modèles');

		$files = $this->getFiles();
		$found = [];

		foreach($files['tracked'] as $relative => $path) {
			foreach($this->scan(file_get_contents($path), $editor) as $read) {
				$found[] = $relative.':'.$read;
			}
		}

		$this->assertEquals([], $found, 'propriété editor concaténée telle quelle, à afficher par new \\editor\\EditorUi()->value(), seul à traduire le XML que la base porte : '.implode(', ', $found).$this->explainUntracked($files['untracked']));

	}

}
