<?php
namespace security;

/**
 * La veille technique (`SoftwareWatchAiLib`) suit-elle tout ce qu'OTF fait tourner ?
 *
 * **De quoi c'est l'inventaire.** Les logiciels que le dépôt emploie sans les écrire, tels que le
 * code les nomme, comparés aux déclarations de `SoftwareAiLib` qui disent à la veille quoi suivre :
 *
 * 1. les binaires que le code du produit lance (`exec()`, `shell_exec()`, `proc_open()`…), contre
 *    `BINARIES` ;
 * 2. les fichiers de verrou de Composer et de npm, contre `LOCKS` ;
 * 3. les fichiers servis depuis un `static/lib/`, contre `ASSETS`, dont chaque motif doit lire une
 *    version dans le nom du fichier ;
 * 4. les fichiers qui chargent un script ou une feuille de style depuis un CDN (`\Asset::jsUrl()`,
 *    `\Asset::cssUrl()`), contre `CDN`, dont chaque motif doit lire une version dans le fichier.
 *    Une police (Google Fonts) n'est pas une bibliothèque et ne se suit pas.
 *
 * Chaque inventaire se compare dans les deux sens : un logiciel apparu sans déclaration ne serait
 * jamais suivi, et une déclaration qui ne désigne plus rien fait croire à un suivi qui n'existe pas.
 * Le code fait foi : c'est la déclaration qui se corrige.
 *
 * **Ce qu'il ne voit pas.** Un binaire lancé par une commande que le code construit dans une
 * variable : l'appel se déclare alors dans `UNREADABLE`, par fichier, avec les binaires qu'il lance.
 * Les tests et `framework/dev/` ne sont pas lus, rien n'y tourne en production. Les services tiers
 * appelés par HTTP ne sont pas inventoriés : leur hôte est une donnée de configuration, et leur
 * suivi vit dans l'observateur `Security.o.php` de leur package.
 *
 * Lecture seule.
 *
 * Lancement : dev/test package=security file=SoftwareSentinelAi
 */
class SoftwareSentinelAiTest extends \Test {

	const FUNCTIONS = ['exec', 'shell_exec', 'passthru', 'proc_open', 'system', 'popen'];

	/**
	 * Les appels dont la commande se construit hors de l'appel, par fichier, avec les binaires
	 * qu'ils lancent.
	 */
	const UNREADABLE = [
		'accounting/financialYear/lib/StatementReader.l.php' => ['gs'],
		'base/media/lib/Svg.l.php' => ['rsvg-convert'],
		'base/scene/lib/Upload.l.php' => ['bash', 'timeout', 'env', 'node'],
		'framework/language/lib/Prepare.l.php' => ['git', 'grep', 'wc', 'cut', 'xargs', 'xgettext', 'msgfmt'],
		'framework/storage/lib/Buffer.l.php' => ['jpeg-recompress'],
		'ouvretaferme/main/lib/Backup.l.php' => ['scp', 'bash'],
	];

	/**
	 * Les mots qui ouvrent un segment de commande sans être un binaire.
	 */
	const SHELL_WORDS = ['if', 'then', 'else', 'fi', 'do', 'done', 'while', 'for', '[', '!', '{', '}', 'command'];

	public function testBinaries(): void {

		$found = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(str_ends_with($file, '.t.php') or str_starts_with($file, 'framework/dev/')) {
				continue;
			}

			foreach(self::findCommands(\dev\SentinelLib::getTokens(file_get_contents($path))) as [$line, $binaries]) {

				if($binaries === NULL) {

					if(array_key_exists($file, self::UNREADABLE) === FALSE) {
						$found['?'][] = $file.':'.$line;
					}

					continue;

				}

				foreach($binaries as $binary) {
					$found[$binary][] = $file.':'.$line;
				}

			}

		}

		foreach(self::UNREADABLE as $file => $binaries) {
			foreach($binaries as $binary) {
				$found[$binary][] = $file;
			}
		}

		$unreadable = $found['?'] ?? [];
		unset($found['?']);

		$this->assertEquals([], $unreadable, 'commande construite hors de l\'appel, que la sentinelle ne lit pas : déclarer le fichier et les binaires qu\'il lance dans UNREADABLE de '.__FILE__.' : '.implode(', ', $unreadable));

		$new = array_diff_key($found, SoftwareAiLib::BINARIES);
		$stale = array_diff_key(SoftwareAiLib::BINARIES, $found);

		$this->assertEquals([], array_keys($new), 'binaire lancé par le code et que la veille ne suit pas : le déclarer dans SoftwareAiLib::BINARIES avec son paquet : '.implode(' · ', array_map(fn(string $binary) => $binary.' ('.implode(', ', $new[$binary]).')', array_keys($new))));
		$this->assertEquals([], array_keys($stale), 'binaire déclaré dans SoftwareAiLib::BINARIES qu\'aucun code ne lance plus, retirer la déclaration : '.implode(', ', array_keys($stale)));

	}

	public function testLocks(): void {

		// `SentinelLib::getFiles()` ne descend pas dans `nodejs/`, où vit un des verrous : la liste
		// vient de git.
		$tracked = \dev\SentinelLib::getTracked();

		$this->assertTrue($tracked !== NULL, 'git ne répond pas, les fichiers de verrou ne se relèvent pas');

		$found = [];

		foreach(array_keys($tracked ?? []) as $file) {
			// npm recopie son verrou dans node_modules, qui ne dit rien de plus que l'original.
			if(in_array(basename($file), ['composer.lock', 'package-lock.json'], TRUE) and str_contains($file, '/node_modules/') === FALSE) {
				$found[] = $file;
			}
		}

		$this->assertInventory($found, array_keys(SoftwareAiLib::LOCKS), 'SoftwareAiLib::LOCKS', 'fichier de verrou');

	}

	public function testAssets(): void {

		$found = [];

		foreach(\dev\SentinelLib::getFiles('', tracked: TRUE) as $file => $path) {
			if(preg_match('#(^|/)static/lib/[^/]+$#', $file) === 1) {
				$found[] = $file;
			}
		}

		$this->assertInventory($found, array_keys(SoftwareAiLib::ASSETS), 'SoftwareAiLib::ASSETS', 'bibliothèque servie au navigateur');

		foreach(SoftwareAiLib::ASSETS as $file => $asset) {
			if(is_array($asset)) {
				$this->assertEquals(1, preg_match($asset['pattern'], $file), 'le motif de '.$file.' dans SoftwareAiLib::ASSETS ne lit aucune version dans son nom');
			}
		}

	}

	public function testCdn(): void {

		$found = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(str_ends_with($file, '.t.php')) {
				continue;
			}

			$tokens = \dev\SentinelLib::getTokens(file_get_contents($path));

			foreach($tokens as $i => [$type, $text]) {
				if(
					$type === T_STRING and
					in_array($text, ['jsUrl', 'cssUrl'], TRUE) and
					($tokens[$i - 1][0] ?? NULL) === T_DOUBLE_COLON and
					($tokens[$i + 2][0] ?? NULL) === T_CONSTANT_ENCAPSED_STRING and
					preg_match('/^.https?:.*\.(js|css).$/', $tokens[$i + 2][1]) === 1
				) {
					$found[] = $file;
					break;
				}
			}

		}

		$this->assertInventory($found, array_keys(SoftwareAiLib::CDN), 'SoftwareAiLib::CDN', 'fichier qui charge une bibliothèque depuis un CDN');

		foreach(SoftwareAiLib::CDN as $file => $cdn) {
			$this->assertEquals(1, preg_match($cdn['pattern'], file_get_contents(LIME_DIRECTORY.'/'.$file)), 'le motif de '.$file.' dans SoftwareAiLib::CDN ne lit aucune version dans le fichier');
		}

	}

	/**
	 * Le détecteur des binaires, sur des appels écrits en ligne.
	 */
	public function testFindCommands(): void {

		$code = <<<'PHP'
		<?php
		exec('SOURCE_DATE_EPOCH=0 timeout 20 gs -q '.$file);
		shell_exec('cd '.$directory.'; git status -s | grep "^?" | xargs git add');
		exec('sudo /usr/sbin/service nginx reload');
		proc_open(['node', '-e', $script], $descriptors, $pipes);
		proc_open([PHP_BINARY, 'lime.php'], $descriptors, $pipes);
		exec($command);
		$curl->exec($url, []);
		CurlLib::exec($url);
		PHP;

		$commands = self::findCommands(\dev\SentinelLib::getTokens($code));

		$this->assertEquals([
			[2, ['timeout', 'gs']],
			[3, ['git', 'grep', 'xargs']],
			[4, ['sudo', 'service']],
			[5, ['node']],
			[6, ['php']],
			[7, NULL],
		], $commands);

	}

	/**
	 * Chaque appel à une fonction de `FUNCTIONS`, avec sa ligne et les binaires que lit sa
	 * commande, NULL quand la commande ne porte aucune chaîne littérale.
	 *
	 * @return array<int, array{0: int, 1: ?string[]}>
	 */
	public static function findCommands(array $tokens): array {

		$commands = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type !== T_STRING or
				in_array(strtolower($text), self::FUNCTIONS, TRUE) === FALSE or
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE) or
				($tokens[$i + 1][0] ?? NULL) !== '('
			) {
				continue;
			}

			// Le premier argument : une chaîne, une concaténation, ou un tableau pour proc_open().
			$depth = 1;
			$literals = [];
			$isArray = (($tokens[$i + 2][0] ?? NULL) === '[');

			for($j = $i + 2; isset($tokens[$j]); $j++) {

				$token = $tokens[$j][0];

				if($token === '(' or $token === '[') {
					$depth++;
				} else if($token === ')' or $token === ']') {
					$depth--;
				}

				if($depth === 0 or ($depth === 1 and $token === ',')) {
					break;
				}

				if($token === '[' or $token === ']') {
					continue;
				}

				if($token === T_CONSTANT_ENCAPSED_STRING) {
					$literals[] = substr($tokens[$j][1], 1, -1);
				} else if($token === T_STRING and $tokens[$j][1] === 'PHP_BINARY') {
					$literals[] = 'php';
				} else if($isArray === FALSE and in_array($token, ['.', T_WHITESPACE], TRUE) === FALSE) {
					$literals[] = ' X ';
				}

				// Dans un tableau, seul le premier élément est le binaire.
				if($isArray and $literals !== []) {
					break;
				}

			}

			$command = trim(implode('', $literals));

			if(preg_match('/[a-z]/i', str_replace('X', '', $command)) !== 1) {
				$commands[] = [$line, NULL];
				continue;
			}

			$commands[] = [$line, self::parseCommand($command)];

		}

		return $commands;

	}

	/**
	 * Les binaires d'une ligne de commande, un par segment (`;`, `&&`, `|`, accents graves), après
	 * les affectations de variables et les mots du shell. `timeout`, `sudo` et `xargs` lancent le
	 * mot qui les suit, qui compte aussi.
	 *
	 * @return string[]
	 */
	public static function parseCommand(string $command): array {

		$binaries = [];

		foreach(preg_split('/;|&&|\|\||\||`|\$\(/', $command) as $segment) {

			$words = preg_split('/\s+/', trim($segment), flags: PREG_SPLIT_NO_EMPTY);

			while($words !== []) {

				$word = array_shift($words);

				if(preg_match('/^[A-Z_]+=/', $word) === 1 or in_array($word, self::SHELL_WORDS, TRUE) or str_starts_with($word, '-') or is_numeric($word)) {
					continue;
				}

				// Le répertoire de `cd` n'est pas une commande, et ce qui suit sur le segment non plus.
				if($word === 'cd' or $word === 'X') {
					break;
				}

				$binary = basename($word);

				if(preg_match('/^[a-z][a-z0-9.+_-]*$/', $binary) !== 1) {
					break;
				}

				$binaries[] = $binary;

				if(in_array($binary, ['timeout', 'sudo', 'xargs'], TRUE) === FALSE) {
					break;
				}

			}

		}

		return array_values(array_unique($binaries));

	}

	private function assertInventory(array $found, array $declared, string $declaration, string $label): void {

		$new = array_diff($found, $declared);
		$stale = array_diff($declared, $found);

		$this->assertEquals([], array_values($new), $label.' que la veille ne suit pas : le déclarer dans '.$declaration.' : '.implode(', ', $new));
		$this->assertEquals([], array_values($stale), 'déclaré dans '.$declaration.' sans plus rien désigner, retirer la déclaration : '.implode(', ', $stale));

	}

}
?>
