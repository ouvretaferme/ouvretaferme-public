<?php
namespace dev;

/**
 * Une commande passée au shell ne se compose que de littéraux, de constantes et de valeurs
 * échappées par `escapeshellarg()`.
 *
 * Le contrat vaut pour celui qui n'a aucune raison de lire ce fichier : il écrira un `exec()` dans
 * son package, des mois plus tard, en recopiant celui d'à côté. Cinq générateurs de PDF portaient
 * ainsi le même bloc, où un paramètre de l'URL arrivait dans `exec()` entre guillemets doubles. Ils
 * ne protègent que des espaces : `$(…)` et les accents graves y sont interpolés par le shell, donc
 * `?accountLabel=$(commande)` exécutait la commande sous l'utilisateur du serveur web.
 *
 * Une variable est suivie jusqu'à ses affectations, dans tout le fichier : c'est la forme du dépôt,
 * qui assemble ses arguments dans un `$args` avant de le concaténer. Une variable qu'aucune
 * affectation ne construit — un paramètre, une valeur de `foreach`, une propriété, une sortie par
 * référence — est tenue pour non sûre : la sentinelle ne lit qu'un fichier et ne sait pas d'où elle
 * vient. Une interpolation est refusée même quand ce qu'elle insère est sûr, parce qu'elle place la
 * valeur là où l'on ne voit plus si elle a été échappée.
 *
 * **La forme tableau de `proc_open()` est hors contrat** : PHP exécute alors le binaire sans shell et
 * remet chaque élément au programme tel quel, donc une variable y est sûre et `escapeshellarg()` y
 * serait faux, ses guillemets arrivant dans la valeur. Refuser cette forme a coûté deux réécritures
 * de code juste, dans `base/dashboard/test/Landing.t.php` et `ouvretaferme/learning/test/DateEnrollment.t.php`,
 * dont le commentaire porte encore la contrainte qu'elle croyait devoir tenir (cas-vus, « Une
 * sentinelle qui refuse la forme sans shell »).
 *
 * ⚠️ Ce suivi se fait **par nom et sur tout le fichier**, donc une commande irréprochable échoue
 * quand une autre méthode affecte le même nom sans échapper : `$root = escapeshellarg(…)` a été
 * refusé pour un `$root = self::getRoot()` situé cent lignes plus haut. Le nom est ce qui les lie,
 * l'ordre et la portée n'y font rien, et la réponse est d'échapper sur place ou de nommer autrement,
 * jamais de déclarer la dette.
 *
 * Lancement : dev/test package=dev file=ShellCommandSentinel
 */
class ShellCommandSentinelTest extends SentinelTest {

	/** Les fonctions qui remettent une chaîne au shell. Les accents graves sont lus à part. */
	const FUNCTIONS = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'];

	/**
	 * Les jetons qui composent une commande sûre : du texte écrit dans le fichier, des nombres, des
	 * noms de constantes et la ponctuation qui les assemble.
	 */
	const SAFE = [
		T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_LNUMBER, T_DNUMBER,
		T_DOUBLE_COLON, T_NS_SEPARATOR, T_DIR, T_FILE,
		'.', '(', ')', '[', ']', ',',
	];

	/** Un cast numérique rend une valeur qui ne peut porter aucun métacaractère, quelle que soit la sienne. */
	const CASTS = [T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST];

	/** Les jetons qui portent un nom : constante seule, ou fonction quand une parenthèse suit. */
	const NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

	/**
	 * La dette, relevée le 2026-09-21. Aucune de ces commandes ne compose une entrée du web : ce
	 * sont des outils de développement, des scripts d'administration lancés en ligne de commande, ou
	 * des chemins que le code construit lui-même.
	 *
	 * Les deux `page/configure/` reçoivent leurs valeurs des arguments de la commande
	 * (`GET('password')`) ; `migrateFarm` a servi au renommage `farm` → `structure` et n'a plus
	 * d'objet.
	 */
	const UNESCAPED = [
		'accounting/company/page/configure/backup.p.php' => 3,
		'accounting/company/page/configure/migrateFarm.p.php' => 2,
		// Le point d'entrée générique des commandes de dev : son argument porte déjà plusieurs
		// arguments, il ne peut pas être échappé d'un bloc
		'framework/dev/lib/System.l.php' => 1,
		'framework/dev/lib/Cron.l.php' => 1,
		'framework/dev/lib/Minify.l.php' => 1,
		// Extraction et réécriture des fichiers de langue, par git
		'framework/language/lib/Csv.l.php' => 1,
		'framework/language/lib/Prepare.l.php' => 5,
		// Chemins portés par une propriété de l'objet, que la sentinelle ne suit pas
		'framework/core/Cache.php' => 1,
		'framework/core/Reflection/ReflectionPackage.php' => 1,
		'framework/storage/lib/Buffer.l.php' => 1,
		'ouvretaferme/main/lib/Backup.l.php' => 1,
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {
			$this->addFound('unescaped', $file, self::findUnescaped($tokens));
		}

	}

	public function testUnescapedShellCommand(): void {

		$this->assertRatchet('unescaped', self::UNESCAPED, 'commande shell composée sans escapeshellarg()');

	}

	/** Le scanner trouve chaque forme non échappée, et laisse passer ses voisines légitimes. */
	public function testScannerFindsUnescaped(): void {

		$lines = self::findUnescaped(SentinelLib::getTokens(<<<'PHP'
			<?php
			class Fixture {
				public function a(string $url, int $max, array $list) {
					exec('node pdf.js "--url='.$url.'"');
					exec('node pdf.js '.escapeshellarg('--url='.$url));
					$args = escapeshellarg('--url='.$url);
					$args .= ' '.escapeshellarg('--title='.rawurlencode($url));
					exec('node '.LIME_DIRECTORY.'/pdf.js '.$args.' 2>&1', $out, $code);
					$command = 'gs -q '.escapeshellarg($url).' 2>/dev/null';
					shell_exec($command);
					$dirty = 'rm '.$url;
					shell_exec($dirty);
					system("ls {$url}");
					passthru('ls '.\main\BackupLib::LOCAL_BACKUP_DIR);
					exec(self::BINARY.' --width '.(int)$max.' '.escapeshellarg($url));
					$text = `ls`;
					$text = `ls $url`;
					$this->exec('rm '.$url);
					\util\CurlLib::exec($url);
					foreach($list as $item) {
						exec('rm '.$item);
					}
					proc_open([PHP_BINARY, $url], [], $pipes);
					proc_open('php '.$url, [], $pipes);
				}
			}
			PHP));

		$this->assertEquals([4, 12, 13, 17, 21, 24], $lines, 'les six commandes composées sans échappement sont trouvées, rien d\'autre, la forme tableau de proc_open() étant hors shell');

	}

	/**
	 * Les lignes des commandes shell dont la composition n'est pas sûre.
	 *
	 * @return int[]
	 */
	private static function findUnescaped(array $tokens): array {

		$assignments = self::getAssignments($tokens);
		$lines = [];
		$count = count($tokens);

		for($i = 0; $i < $count; $i++) {

			[$type, $text, $line] = $tokens[$i];

			if($type === '`') {

				$close = $i + 1;

				while($close < $count and $tokens[$close][0] !== '`') {
					$close++;
				}

				if(self::isSafe($tokens, $i + 1, $close - 1, $assignments, []) === FALSE) {
					$lines[] = $line;
				}

				$i = $close;
				continue;

			}

			if(
				in_array($type, self::NAMES, TRUE) === FALSE or
				in_array(strtolower(ltrim($text, '\\')), self::FUNCTIONS, TRUE) === FALSE or
				($tokens[$i + 1][0] ?? NULL) !== '('
			) {
				continue;
			}

			// Une méthode qui porte l'un de ces noms n'est pas la fonction : `$curl->exec()`,
			// `$database->exec()` et `Module::model()->exec()` peuplent le dépôt
			if(in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)) {
				continue;
			}

			$close = SentinelLib::getClosing($tokens, $i + 1);

			if(
				self::isDirect($tokens, $text, $i + 2) === FALSE and
				self::isSafe($tokens, $i + 2, self::getFirstArgumentEnd($tokens, $i + 2, $close), $assignments, []) === FALSE
			) {
				$lines[] = $line;
			}

			$i = $close;

		}

		return $lines;

	}

	/**
	 * Un `proc_open()` dont la commande est un **tableau** n'ouvre aucun shell : PHP exécute le
	 * binaire directement et remet chaque élément au programme comme un argument, sans qu'aucun
	 * interpréteur ne lise ses métacaractères. `escapeshellarg()` y serait non seulement inutile
	 * mais faux, ses guillemets arrivant dans la valeur. C'est la seule forme de ce genre : aucune
	 * des autres fonctions de `FUNCTIONS` n'accepte un tableau.
	 */
	private static function isDirect(array $tokens, string $name, int $argument): bool {

		if(strtolower(ltrim($name, '\\')) !== 'proc_open') {
			return FALSE;
		}

		return ($tokens[$argument][0] ?? NULL) === '[' or ($tokens[$argument][0] ?? NULL) === T_ARRAY;

	}

	/**
	 * La dernière position du premier argument : seule la commande est en cause, les suivants
	 * recueillent la sortie et le code de retour.
	 */
	private static function getFirstArgumentEnd(array $tokens, int $from, int $close): int {

		$depth = 0;

		for($i = $from; $i < $close; $i++) {

			$type = $tokens[$i][0];

			if($type === '(' or $type === '[' or $type === '{') {
				$depth++;
			} else if($type === ')' or $type === ']' or $type === '}') {
				$depth--;
			} else if($type === ',' and $depth === 0) {
				return $i - 1;
			}

		}

		return $close - 1;

	}

	/**
	 * Les expressions qui construisent chaque variable du fichier, par son nom.
	 *
	 * @return array<string, array<int, array{0: int, 1: int}>>
	 */
	private static function getAssignments(array $tokens): array {

		$assignments = [];
		$count = count($tokens);

		for($i = 0; $i < $count; $i++) {

			if($tokens[$i][0] !== T_VARIABLE) {
				continue;
			}

			$operator = $tokens[$i + 1][0] ?? NULL;

			if($operator !== '=' and $operator !== T_CONCAT_EQUAL) {
				continue;
			}

			$start = $i + 2;
			$end = $start;
			$depth = 0;

			for($j = $start; $j < $count; $j++) {

				$type = $tokens[$j][0];

				if($type === '(' or $type === '[' or $type === '{') {
					$depth++;
				} else if($type === ')' or $type === ']' or $type === '}') {

					if($depth === 0) {
						break;
					}

					$depth--;

				} else if(($type === ';' or $type === ',') and $depth === 0) {
					break;
				}

				$end = $j;

			}

			$assignments[$tokens[$i][1]][] = [$start, $end];

		}

		return $assignments;

	}

	/**
	 * @param array<string, array<int, array{0: int, 1: int}>> $assignments
	 * @param array<string, bool> $seen Les variables déjà ouvertes, contre une affectation circulaire
	 */
	private static function isSafe(array $tokens, int $from, int $to, array $assignments, array $seen): bool {

		for($i = $from; $i <= $to; $i++) {

			[$type, $text] = $tokens[$i];

			if(in_array($type, self::CASTS, TRUE)) {

				$i = (($tokens[$i + 1][0] ?? NULL) === '(') ? SentinelLib::getClosing($tokens, $i + 1) : $i + 1;
				continue;

			}

			if(in_array($type, self::NAMES, TRUE)) {

				if(($tokens[$i + 1][0] ?? NULL) !== '(') {
					continue;
				}

				if(strtolower(ltrim($text, '\\')) !== 'escapeshellarg') {
					return FALSE;
				}

				$i = SentinelLib::getClosing($tokens, $i + 1);
				continue;

			}

			if($type === T_VARIABLE) {

				if(array_key_exists($text, $seen)) {
					continue;
				}

				if(array_key_exists($text, $assignments) === FALSE) {
					return FALSE;
				}

				$seen[$text] = TRUE;

				foreach($assignments[$text] as [$start, $end]) {

					if(self::isSafe($tokens, $start, $end, $assignments, $seen) === FALSE) {
						return FALSE;
					}

				}

				continue;

			}

			if(in_array($type, self::SAFE, TRUE) === FALSE) {
				return FALSE;
			}

		}

		return TRUE;

	}

}
?>
