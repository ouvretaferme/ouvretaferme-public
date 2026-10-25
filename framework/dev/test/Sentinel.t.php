<?php
namespace dev;

/**
 * Base des tests-sentinelles qui portent les règles du `CLAUDE.md` racine.
 *
 * Une sentinelle relève ses occurrences une fois, dans `init()`, par règle, puis chaque méthode
 * `test*` confronte une règle à sa déclaration par `assertRatchet()`. Une règle que le dépôt
 * respecte déjà se déclare vide : toute occurrence la fait échouer. Une règle qu'il enfreint
 * encore déclare sa dette, fichier par fichier : elle ne peut que baisser.
 *
 * Chaque détecteur a son test sur un code en ligne, qui prouve qu'il trouve la forme fautive et
 * laisse passer ses voisines légitimes : un détecteur qui ne trouve rien rendrait la règle verte
 * pour de mauvaises raisons.
 *
 * Abstraite, donc ignorée par `TestLib::run()`.
 */
abstract class SentinelTest extends \Test {

	/**
	 * Les occurrences relevées, par règle, sous la forme `chemin:ligne`.
	 * @var array<string, string[]>
	 */
	private array $found = [];

	/**
	 * Les cas relevés une à une, par règle, sous la forme `chemin:clé => ligne`, pour les règles dont
	 * chaque cas porte sa propre raison (une route, une méthode).
	 * @var array<string, array<string, int>>
	 */
	private array $foundByKey = [];

	/**
	 * @param int[] $lines
	 */
	protected function addFound(string $rule, string $file, array $lines): void {

		foreach($lines as $line) {
			$this->found[$rule][] = $file.':'.$line;
		}

	}

	/**
	 * @param array<string, int> $declared
	 */
	protected function assertRatchet(string $rule, array $declared, string $message): void {

		$result = SentinelLib::compare($this->found[$rule] ?? [], $declared);

		$counts = SentinelLib::getNewCounts($this->found[$rule] ?? [], $declared);

		$this->assertEquals([], $result['new'], $message.' ('.$this->getDeclarationName($declared).') : '.implode(' · ', SentinelLib::explainNew($result['new'], $this->getDeclarationFile(), $counts, $declared, $this->getDeclarationConstant($declared))));
		$this->assertEquals([], $result['stale'], 'dette résorbée, baisser la déclaration de la règle « '.$message.' » : '.implode(' · ', SentinelLib::explainStale($result['stale'])));

	}

	/**
	 * La constante qui porte une déclaration, et le fichier qui la déclare, pour que le message d'un
	 * cliquet nomme où écrire sans qu'on ouvre le test. Une déclaration passée par une expression et
	 * non par une constante se nomme d'après le fichier seul.
	 */
	private function getDeclarationName(array $declared): string {
		return ($this->getDeclarationConstant($declared) ?? 'déclaration').' de '.$this->getDeclarationFile();
	}

	private function getDeclarationConstant(array $declared): ?string {

		$class = new \ReflectionClass(static::class);
		// Deux constantes de même valeur, dont toutes les déclarations vides, ne se départagent pas.
		$names = array_keys($class->getConstants(), $declared, TRUE);

		return ($declared !== [] and count($names) === 1) ? $names[0] : NULL;

	}

	private function getDeclarationFile(): string {
		return substr(new \ReflectionClass(static::class)->getFileName(), strlen(SentinelLib::getRoot()) + 1);
	}

	protected function addFoundKey(string $rule, string $file, string $key, int $line): void {
		$this->foundByKey[$rule][$file.':'.$key] = $line;
	}

	/**
	 * Confronte les cas relevés à une déclaration `chemin:clé => raison`, dans les deux sens.
	 *
	 * Un compte par fichier ne dit pas quel cas est excusé, et laisse passer un échange : un cas
	 * corrigé et un cas neuf du même fichier gardent le même nombre. Une raison vide est refusée.
	 * Comme pour `SentinelLib::compare()`, une déclaration qui désigne un fichier présent sur le
	 * disque mais que git ne suit pas dort, au lieu d'être périmée chez son auteur.
	 *
	 * @param array<string, string> $declared
	 */
	protected function assertDeclared(string $rule, array $declared, string $message): void {

		$found = $this->foundByKey[$rule] ?? [];
		ksort($found);

		$new = [];
		$counts = [];

		foreach($found as $key => $line) {

			if(array_key_exists($key, $declared) === FALSE) {
				$new[] = $key.' (ligne '.$line.')';
				$counts[$key] = 1;
			}

		}

		$stale = [];

		foreach(array_diff_key($declared, $found) as $key => $reason) {

			$path = strtok($key, ':');

			if(SentinelLib::getTracked() !== NULL and is_file(SentinelLib::getRoot().'/'.$path) and isset(SentinelLib::getTracked()[$path]) === FALSE) {
				continue;
			}

			$stale[] = $key;

		}

		$this->assertEquals([], $new, $message.' ('.$this->getDeclarationName($declared).') : '.implode(' · ', SentinelLib::explainNew($new, $this->getDeclarationFile(), $counts, $declared, $this->getDeclarationConstant($declared))));
		$this->assertEquals([], $stale, 'déclaré pour la règle « '.$message.' » sans plus rien désigner, retirer la déclaration : '.implode(' · ', $stale));
		$this->assertEquals([], array_keys($declared, '', TRUE), 'déclaration sans raison pour la règle « '.$message.' », écrire ce qui justifie le cas : '.implode(' · ', array_keys($declared, '', TRUE)));

	}

	/**
	 * Les jetons de chaque fichier PHP du dépôt, par son chemin depuis la racine.
	 *
	 * Les sentinelles elles-mêmes sont écartées : leurs fixtures portent volontairement la forme
	 * fautive, dans des chaînes que les règles sur le texte liraient.
	 */
	protected function getPhpTokens(): \Generator {

		foreach(SentinelLib::getFiles('.php') as $file => $path) {

			if(fnmatch('*Sentinel*.t.php', basename($file))) {
				continue;
			}

			yield $file => SentinelLib::getTokens(file_get_contents($path));

		}

	}

	protected static function isPresentation(string $file): bool {
		return (str_ends_with($file, '.u.php') or str_ends_with($file, '.v.php'));
	}

	/**
	 * Le texte à traduire de chaque appel à `s()` et `p()` : le premier argument, et le second pour
	 * `p()`, morceaux concaténés recousus.
	 *
	 * @return array<int, array{0: int, 1: string}>
	 */
	protected static function findTexts(array $tokens): array {

		$texts = [];

		foreach($tokens as $i => [$type, $text]) {

			if(
				$type !== T_STRING or
				($text !== 's' and $text !== 'p') or
				($tokens[$i + 1][0] ?? NULL) !== '(' or
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)
			) {
				continue;
			}

			$closing = SentinelLib::getClosing($tokens, $i + 1);
			$arguments = ($text === 's') ? 1 : 2;

			$argument = 0;
			$depth = 0;
			$pieces = [];

			for($j = $i + 2; $j < $closing and $argument < $arguments; $j++) {

				$token = $tokens[$j];

				if(in_array($token[0], ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE)) {
					$depth++;
				} else if(in_array($token[0], [')', ']', '}'], TRUE)) {
					$depth--;
				} else if($token[0] === ',' and $depth === 0) {
					$argument++;
					continue;
				}

				$string = self::getString($token);

				if($string === NULL) {
					continue;
				}

				$pieces[$argument] ??= [$token[2], ''];
				$pieces[$argument][1] .= match($token[0] === T_CONSTANT_ENCAPSED_STRING ? $token[1][0] : NULL) {
					'\'' => str_replace('\\\'', '\'', $string),
					'"' => str_replace('\\"', '"', $string),
					NULL => $string,
				};

			}

			array_push($texts, ...array_values($pieces));

		}

		return $texts;

	}

	/**
	 * Le texte d'un jeton de chaîne, sans ses guillemets. Les chaînes interpolées arrivent en
	 * morceaux (`T_ENCAPSED_AND_WHITESPACE`), sans guillemets.
	 */
	protected static function getString(array $token): ?string {

		return match($token[0]) {
			T_CONSTANT_ENCAPSED_STRING => substr($token[1], 1, -1),
			T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML => $token[1],
			default => NULL,
		};

	}

	/**
	 * Arrête la sentinelle quand la ressource hors du code qu'elle lit manque ici : git, une
	 * commande, une base injoignable. Ses déclarations ne sont alors ni vraies ni fausses, et un
	 * échec ferait chercher dans le code un défaut qui n'y est pas. Dans `init()`, aucun test ne
	 * part ; dans une méthode, elle s'arrête et les suivantes ne partent pas.
	 *
	 * Une base joignable mais en retard n'est pas une ressource absente : la sentinelle le dit par
	 * un échec qui nomme ce qu'il faut jouer. La passe imprime la sentinelle à part, avec la raison,
	 * et son code de sortie reste 0 : seule la ligne Total dit qu'une sentinelle n'a rien vérifié.
	 */
	protected function notApplicable(string $reason): never {
		throw new NotApplicableException($reason);
	}

}
?>
