<?php
namespace structure;

/**
 * Test-sentinelle : un texte d'un écran de groupe nomme le groupe par `getText()` de son type.
 *
 * Le vocabulaire d'un groupe dépend de son type : « le groupe » pour une communauté ou une
 * formation, « le réseau », « le groupement de producteurs ». Un texte de `base/structure/ui` qui
 * écrit l'un de ces noms en dur le dit à tous les types qui passent par l'écran, et un réseau lit
 * alors « groupe ». `.claude/lexique/groupes.md` le constate : un mot refusé borné à un chemin ne
 * sait pas dire le type, donc la règle des mots refusés de `WritingSentinel` ne peut pas tenir ce
 * vocabulaire, et c'est cette sentinelle qui le tient.
 *
 * Les noms ne sont pas recopiés : ils se lisent dans la valeur `'the'` de `getText()` des quatre
 * types de groupe, article retiré, au singulier et au pluriel. Un type qui change son nom change ce
 * que la règle cherche, sans toucher ce fichier.
 *
 * Un texte est relevé quand il est un argument traduit de `s()` ou `p()` dans un `.u.php` de
 * `base/structure/ui`, hors des méthodes `getText()`, qui sont précisément l'endroit où le nom
 * s'écrit. L'existant est déclaré fichier par fichier, en nombre : il ne peut que baisser.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - les rôles (« administrateur », « membre », « formateur ») : leur nom dépend aussi du type, mais
 *   aucune méthode n'en porte la liste par type à laquelle comparer, et « administrateur » reste
 *   juste sur la page d'administration d'une ferme ;
 * - un texte qui nomme bien le groupe par `getText()` mais emploie un mot de groupe ailleurs dans
 *   la même phrase est relevé comme les autres : le nombre déclaré compte des chaînes, pas des
 *   fautes ;
 * - un nom de groupe écrit hors de `base/structure/ui` (un mail, un écran de `learning`).
 *
 * Lancement : dev/test package=structure file=StructureTypeWordingSentinelAi
 */
class StructureTypeWordingSentinelAiTest extends \dev\SentinelTest {

	/**
	 * Les types de groupe dont le nom se lit dans `getText()`.
	 */
	const TYPES = [
		'base/structure/ui/StructureCommunity.u.php',
		'base/structure/ui/StructureEducation.u.php',
		'base/structure/ui/StructureNetwork.u.php',
		'base/structure/ui/StructureShop.u.php',
	];

	/**
	 * Mesuré le 2026-10-01, identique sur origin/main, puis baissé le 2026-10-12 : la mise en route
	 * et la création d'un réseau disent « réseau » (lots MR3b et MR4 du chantier e-learning, le
	 * second pour le titre du bandeau de fin). Une partie est
	 * juste et y restera : un texte propre à un type (`StructureShop`), ou un écran de création où le
	 * type n'est pas encore choisi.
	 */
	const DECLARED = [
		'base/structure/ui/Alert.u.php' => 26,
		'base/structure/ui/Community.u.php' => 8,
		'base/structure/ui/CommunityStartAi.u.php' => 1,
		'base/structure/ui/Invite.u.php' => 4,
		'base/structure/ui/Member.u.php' => 4,
		'base/structure/ui/Notification.u.php' => 4,
		'base/structure/ui/Structure.u.php' => 11,
		'base/structure/ui/StructureCommunity.u.php' => 59,
		'base/structure/ui/StructureShop.u.php' => 3,
		'base/structure/ui/System.u.php' => 1,
	];

	private array $nouns = [];

	public function init() {

		$files = \dev\SentinelLib::getFiles('.u.php');

		foreach(self::TYPES as $file) {
			array_push($this->nouns, ...self::findNouns(\dev\SentinelLib::getTokens(file_get_contents($files[$file]))));
		}

		$this->nouns = array_values(array_unique($this->nouns));
		$patterns = array_map(self::getPattern(...), $this->nouns);

		foreach($files as $file => $path) {

			if(str_starts_with($file, 'base/structure/ui/') === FALSE) {
				continue;
			}

			$this->addFound('typeWording', $file, self::findHardcoded(\dev\SentinelLib::getTokens(file_get_contents($path)), $patterns));

		}

	}

	/**
	 * Le nom que porte la valeur `'the'` de `getText()`, sans son article.
	 *
	 * @return string[]
	 */
	private static function findNouns(array $tokens): array {

		$nouns = [];

		foreach($tokens as $i => $token) {

			if(
				$token[0] === T_CONSTANT_ENCAPSED_STRING and
				$token[1] === '\'the\'' and
				($tokens[$i + 1][0] ?? NULL) === T_DOUBLE_ARROW and
				in_array($tokens[$i + 2][1] ?? NULL, ['s', 'p'], TRUE) and
				($tokens[$i + 4][0] ?? NULL) === T_CONSTANT_ENCAPSED_STRING
			) {
				$nouns[] = preg_replace('/^(le |la |les |l\')/u', '', self::getString($tokens[$i + 4]));
			}

		}

		return $nouns;

	}

	/**
	 * Le premier mot prend la marque du pluriel : « réseaux », « groupements de producteurs ».
	 */
	private static function getPattern(string $noun): string {

		$words = explode(' ', preg_quote($noun, '/'), 2);
		$words[0] .= '[sx]?';

		return '/(?<!\p{L})'.implode(' ', $words).'(?!\p{L})/iu';

	}

	/**
	 * @return int[]
	 */
	private static function findHardcoded(array $tokens, array $patterns): array {

		$excluded = [];

		foreach($tokens as $i => $token) {

			if(
				$token[0] === T_FUNCTION and
				($tokens[$i + 1][1] ?? NULL) === 'getText'
			) {

				$j = $i;

				while(in_array($tokens[$j][0], ['{', ';'], TRUE) === FALSE) {
					$j++;
				}

				if($tokens[$j][0] === '{') {
					$excluded[] = [$tokens[$j][2], $tokens[\dev\SentinelLib::getClosing($tokens, $j)][2]];
				}

			}

		}

		$lines = [];

		foreach(self::findTexts($tokens) as [$line, $text]) {

			foreach($excluded as [$from, $to]) {

				if($line >= $from and $line <= $to) {
					continue 2;
				}

			}

			foreach($patterns as $pattern) {

				if(preg_match($pattern, $text) === 1) {
					$lines[] = $line;
					break;
				}

			}

		}

		return $lines;

	}

	public function testDetectors(): void {

		$this->assertEquals(['groupe', 'réseau', 'groupement de producteurs'], $this->nouns, 'noms lus dans getText()');

		$patterns = array_map(self::getPattern(...), ['réseau', 'groupement de producteurs']);

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			class X {
				public static function getText(Structure $e, string $text): string {
					return match($text) {
						'the' => s("le réseau"),
					};
				}
				public function a(Structure $e): string {
					// Le réseau ne paraît pas ici
					return s("Rejoindre {the}", ['the' => static::getText($e, 'the')]).s("Les réseaux de la région")
						.p("Un groupement de producteurs", "{value} groupements de producteurs", 2)
						.s("Le réseautage").'Le réseau';
				}
			}
			PHP);

		$this->assertEquals([10, 11, 11], self::findHardcoded($tokens, $patterns), 'findHardcoded');

	}

	public function testTypeWording(): void {
		$this->assertRatchet('typeWording', self::DECLARED, 'nom de groupe écrit en dur dans un texte de base/structure/ui : le dire par {the} et StructureUi::getText($eStructure, \'the\'), qui donne le nom propre au type');
	}

}
?>
