<?php
namespace design;

/**
 * Test-sentinelle : un bouton qui n'a qu'une icône porte `btn-only-icon`, et lui seul.
 *
 * Un `.btn` à libellé prend une marge latérale de 0,75em (`component.css`, règle `.btn`), qui
 * élargirait une icône seule en ovale : `btn-only-icon` lui rend la marge carrée. La classe
 * s'oublie dans les deux sens, une icône seule qui devient ovale, un libellé qui se serre sur une
 * marge d'icône, et rien à l'écran ne le signale ailleurs que sur le bouton fautif.
 *
 * Le relevé lit les jetons des fichiers PHP suivis par git. Un bouton est une balise `<a` ou
 * `<button` ouverte dans une chaîne, dont l'attribut `class` porte le mot `btn`. Il n'a qu'une
 * icône quand rien d'autre qu'un seul appel `Asset::icon(…)` ne sépare la balise de sa fermeture,
 * hormis les `.`, les `;` et les `$h .=` d'une écriture en plusieurs instructions. Un bouton de menu
 * (`dropdown-toggle`) porte une flèche après son icône : il n'est pas une icône seule.
 *
 * Deux règles :
 * - `iconOnlyUnmarked` : une icône seule sans `btn-only-icon`. Les nombres déclarés sont ceux des
 *   fichiers qu'une autre session tenait au moment de la pose, relevés sur `origin/main` par
 *   `git show origin/main:<chemin>` (31f9e3a9b), et ne peuvent que baisser.
 * - `iconOnlyMisplaced` : `btn-only-icon` dans un attribut de classe que la lecture ne reconnaît
 *   pas comme une icône seule. Un bouton rendu par `FormUi::button()` ou `submit()` reçoit sa
 *   classe dans un tableau d'attributs que la lecture ne suit pas : il se déclare par cas, sous le
 *   nom de son icône, avec sa raison. De même un `span` qui prend la forme d'un bouton dans un
 *   lien plus large que lui, la lecture ne lisant que `<a` et `<button`.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture : un bouton écrit en JavaScript, une icône
 * seule rendue par `FormUi::button()` ou `submit()` sans la classe, un contenu passé par une
 * variable ou choisi par un ternaire, une balise écrite entre guillemets doubles, une classe
 * recomposée par concaténation avant d'être posée.
 *
 * Lancement : dev/test package=design file=IconOnlyButtonSentinelAi
 */
class IconOnlyButtonSentinelAiTest extends \dev\SentinelTest {

	const CLASS_NAME = 'btn-only-icon';

	/**
	 * Les icônes seules que la pose n'a pas marquées, dans des fichiers qu'une autre session tenait.
	 */
	const UNMARKED = [
		'commercialisation/selling/ui/Customer.u.php' => 1,
		'commercialisation/selling/ui/Merchant.u.php' => 1,
		'production/planning/ui/TaskWeek.u.php' => 1,
		'production/series/ui/Planning.u.php' => 3,
	];

	/**
	 * Les poses que la lecture ne suit pas, par `chemin:icône`, avec leur raison.
	 */
	const MISPLACED = [
		'commercialisation/selling/ui/Invoice.u.php:search' => 'FormUi::button(), la classe passe par le tableau d\'attributs',
		'framework/dev/ui/ErrorMonitoring.u.php:check' => 'FormUi::submit(), la classe passe par le tableau d\'attributs',
		'ouvretaferme/design/ui/Design.u.php:plus-lg' => 'un span, le « + » d\'un jour sans journée, le jour entier étant le lien qui l\'ouvre (TimeWeekUi::getUnfilled())',
		'ouvretaferme/main/ui/QuickInlineAi.u.php:check-lg' => 'FormUi::submit(), la classe passe par le tableau d\'attributs',
		'production/planning/ui/TimeWeek.u.php:plus-lg' => 'un span, le « + » d\'un jour sans journée, le jour entier étant le lien qui l\'ouvre',
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			// Un test monte le markup qu'il éprouve, il ne le rend à personne.
			if(str_ends_with($file, '.t.php')) {
				continue;
			}

			$this->addFound('iconOnlyUnmarked', $file, self::findUnmarked($tokens));

			foreach(self::findMisplaced($tokens) as [$key, $line]) {
				$this->addFoundKey('iconOnlyMisplaced', $file, $key, $line);
			}

		}

	}

	/**
	 * Les lignes des boutons à icône seule, hors menu, qui ne portent pas la classe.
	 *
	 * @return int[]
	 */
	private static function findUnmarked(array $tokens): array {

		$lines = [];

		foreach(self::findButtons($tokens) as $button) {

			if($button['iconOnly'] and $button['menu'] === FALSE and $button['marked'] === FALSE) {
				$lines[] = $button['line'];
			}

		}

		return $lines;

	}

	/**
	 * Les attributs de classe qui portent `btn-only-icon` hors d'une icône seule, sous la forme
	 * `[icône, ligne]`, l'icône étant celle de l'appel `Asset::icon()` le plus proche dans
	 * l'instruction.
	 *
	 * @return array<int, array{string, int}>
	 */
	private static function findMisplaced(array $tokens): array {

		$legitimate = [];

		foreach(self::findButtons($tokens) as $button) {

			if($button['iconOnly'] and $button['menu'] === FALSE and $button['marked']) {
				$legitimate[$button['classToken']] = TRUE;
			}

		}

		$found = [];

		foreach($tokens as $position => [$type, $text, $line]) {

			if(
				$type !== T_CONSTANT_ENCAPSED_STRING or
				self::hasClassWord(self::unquote($text), self::CLASS_NAME) === FALSE or
				isset($legitimate[$position]) or
				self::isClassValue($tokens, $position) === FALSE
			) {
				continue;
			}

			$found[] = [self::findNearestIcon($tokens, $position), $line];

		}

		return $found;

	}

	/**
	 * Une chaîne est une valeur de classe quand elle ouvre un attribut `class="…"` ou qu'elle suit
	 * la clé `'class' =>` d'un tableau d'attributs.
	 */
	private static function isClassValue(array $tokens, int $position): bool {

		if(preg_match('/class="[^"]*\b'.self::CLASS_NAME.'\b/', self::unquote($tokens[$position][1])) === 1) {
			return TRUE;
		}

		return (
			($tokens[$position - 1][0] ?? NULL) === T_DOUBLE_ARROW and
			($tokens[$position - 2][0] ?? NULL) === T_CONSTANT_ENCAPSED_STRING and
			self::unquote($tokens[$position - 2][1]) === 'class'
		);

	}

	/**
	 * Le nom de l'icône de l'appel `Asset::icon()` le plus proche dans la même instruction.
	 */
	private static function findNearestIcon(array $tokens, int $position): string {

		$start = $position;

		while($start > 0 and $tokens[$start - 1][0] !== ';') {
			$start--;
		}

		$count = count($tokens);
		$end = $position;

		while($end < $count - 1 and $tokens[$end + 1][0] !== ';') {
			$end++;
		}

		$nearest = NULL;

		for($i = $start; $i <= $end; $i++) {

			if(
				self::isIconCall($tokens, $i) and
				($tokens[$i + 4][0] ?? NULL) === T_CONSTANT_ENCAPSED_STRING and
				($nearest === NULL or abs($i - $position) < abs($nearest - $position))
			) {
				$nearest = $i;
			}

		}

		return ($nearest === NULL) ? 'sans-icône' : self::unquote($tokens[$nearest + 4][1]);

	}

	/**
	 * Chaque bouton ouvert dans une chaîne : la ligne de son attribut `class`, la position du jeton
	 * qui le porte, et ce que la lecture en dit.
	 *
	 * @return array<int, array{line: int, classToken: int, iconOnly: bool, menu: bool, marked: bool}>
	 */
	private static function findButtons(array $tokens): array {

		$count = count($tokens);
		$buttons = [];

		foreach($tokens as $position => [$type, $text]) {

			if($type !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}

			$string = self::unquote($text);

			if(preg_match_all('/<(a|button)(?=[\s>])/', $string, $matches, PREG_OFFSET_CAPTURE) === 0) {
				continue;
			}

			$tag = end($matches[1])[0];
			$opening = substr($string, end($matches[0])[1]);

			// La balise se ferme au premier `>` d'une chaîne de la concaténation, les arguments
			// d'un appel (attr(), une URL) étant lus comme une valeur dynamique.
			$classToken = str_contains($opening, 'class="') ? $position : NULL;
			$end = $position;
			$after = NULL;

			if(str_contains($opening, '>')) {
				$after = substr($opening, strpos($opening, '>') + 1);
				$opening = substr($opening, 0, strpos($opening, '>'));
			} else {

				$depth = 0;

				for($i = $position + 1; $i < $count; $i++) {

					[$typeNext, $textNext] = $tokens[$i];

					if($typeNext === '(' or $typeNext === '[') {
						$depth++;
					} else if($typeNext === ')' or $typeNext === ']') {
						$depth--;
					} else if($typeNext === ';' and $depth === 0) {
						break;
					} else if($typeNext === T_CONSTANT_ENCAPSED_STRING and $depth === 0) {

						$stringNext = self::unquote($textNext);

						if($classToken === NULL and str_contains($opening.$stringNext, 'class="')) {
							$classToken = $i;
						}

						if(str_contains($stringNext, '>')) {
							$opening .= substr($stringNext, 0, strpos($stringNext, '>'));
							$after = substr($stringNext, strpos($stringNext, '>') + 1);
							$end = $i;
							break;
						}

						$opening .= $stringNext;
						continue;

					}

					if($depth === 0 and $typeNext !== '.') {
						$opening .= '§';
					}

				}

			}

			if(
				$after === NULL or
				$classToken === NULL or
				preg_match('/class="([^"]*)"?/', $opening, $classMatch) === 0 or
				self::hasClassWord($classMatch[1], 'btn') === FALSE
			) {
				continue;
			}

			$buttons[] = [
				'line' => $tokens[$classToken][2],
				'classToken' => $classToken,
				'iconOnly' => ($after === '' and self::hasOnlyIcon($tokens, $end + 1, $tag)),
				'menu' => self::hasClassWord($classMatch[1], 'dropdown-toggle'),
				'marked' => self::hasClassWord($classMatch[1], self::CLASS_NAME),
			];

		}

		return $buttons;

	}

	/**
	 * Entre la balise ouverte et sa fermeture, un seul appel `Asset::icon()`, et sinon que des
	 * `.`, des `;` et des `$h .=`.
	 */
	private static function hasOnlyIcon(array $tokens, int $start, string $tag): bool {

		$count = count($tokens);
		$icons = 0;

		for($i = $start; $i < $count; $i++) {

			[$type, $text] = $tokens[$i];

			if($type === T_CONSTANT_ENCAPSED_STRING) {

				$string = self::unquote($text);

				if($string === '') {
					continue;
				}

				return ($icons === 1 and str_starts_with($string, '</'.$tag.'>'));

			}

			if(self::isIconCall($tokens, $i)) {
				$icons++;
				$i = \dev\SentinelLib::getClosing($tokens, $i + 3);
				continue;
			}

			if($type === '.' or $type === ';' or $type === T_CONCAT_EQUAL) {
				continue;
			}

			if($type === T_VARIABLE and ($tokens[$i + 1][0] ?? NULL) === T_CONCAT_EQUAL) {
				continue;
			}

			return FALSE;

		}

		return FALSE;

	}

	private static function isIconCall(array $tokens, int $i): bool {

		return (
			in_array($tokens[$i][0], [T_NAME_FULLY_QUALIFIED, T_STRING], TRUE) and
			ltrim($tokens[$i][1], '\\') === 'Asset' and
			($tokens[$i + 1][0] ?? NULL) === T_DOUBLE_COLON and
			($tokens[$i + 2][1] ?? NULL) === 'icon' and
			($tokens[$i + 3][0] ?? NULL) === '('
		);

	}

	private static function hasClassWord(string $classes, string $word): bool {
		return preg_match('/(^|[\s"§])'.preg_quote($word, '/').'([\s"§]|$)/', $classes) === 1;
	}

	private static function unquote(string $text): string {
		return substr($text, 1, -1);
	}

	public function testDetectors(): void {

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			$h .= '<a href="'.$url.'" class="btn btn-outline-primary">'.\Asset::icon('x-lg').'</a>';
			$h .= '<a class="btn btn-only-icon btn-sm">'.Asset::icon('pencil').'</a>';
			$h .= '<a class="btn btn-sm" data-ajax="'.conf()['id'].'">';
				$h .= \Asset::icon('trash');
			$h .= '</a>';
			$h .= '<a class="btn btn-outline-primary dropdown-toggle">'.\Asset::icon('gear').'</a>';
			$h .= '<a class="btn btn-primary">'.\Asset::icon('plus').' '.s("Ajouter").'</a>';
			$h .= '<a class="btn btn-only-icon btn-primary">'.s("Ajouter").'</a>';
			$h .= $form->submit(\Asset::icon('check'), ['class' => 'btn btn-only-icon btn-primary']);
			$h .= '<a class="btn">'.\Asset::icon('a').\Asset::icon('b').'</a>';
			$h .= '<a class="util-link">'.\Asset::icon('x').'</a>';
			PHP);

		$this->assertEquals([2, 4], self::findUnmarked($tokens));
		$this->assertEquals([['sans-icône', 9], ['check', 10]], self::findMisplaced($tokens));

	}

	public function testIconOnlyButton(): void {

		$this->assertRatchet('iconOnlyUnmarked', self::UNMARKED, 'bouton à icône seule sans btn-only-icon (le code fait foi : ajouter btn-only-icon après le mot btn de sa classe)');
		$this->assertDeclared('iconOnlyMisplaced', self::MISPLACED, 'btn-only-icon sur un bouton que la lecture ne reconnaît pas comme une icône seule (le code fait foi : retirer la classe d\'un bouton à libellé, ou déclarer dans MISPLACED, avec sa raison, un bouton à icône seule dont la classe passe par un tableau d\'attributs)');

	}

}
?>
