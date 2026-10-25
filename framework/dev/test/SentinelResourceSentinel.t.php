<?php
namespace dev;

/**
 * Les sentinelles qui lisent autre chose que le code : la base, ou une commande du système.
 *
 * Une sentinelle se lit comme un jugement sur le code, et son rouge accuse sa déclaration. Quand la
 * ressource qu'elle interroge manque, une base injoignable ou en retard, un `git` ou un `node` absent,
 * le même rouge accuse à tort un code juste, et un vert peut ne rien avoir vérifié. Chaque sentinelle
 * qui ouvre la base ou lance une commande se déclare donc ici, avec ce qu'elle rend quand cette
 * ressource manque. La valeur dit où elle en est : `non applicable` quand elle appelle
 * `SentinelTest::notApplicable()`, `repli : <ce qu'elle lit à la place>` quand elle répond encore
 * juste sans la ressource, ou `à convertir : <ce qu'elle rend>` tant qu'elle compte des échecs. La conversion se fait sentinelle par sentinelle, chacune dans le lot de son package,
 * et la valeur suit : un appel ajouté ou retiré sans elle échoue.
 *
 * Deux règles :
 * - `resource`, par fichier et par ressource (`<chemin>:base`, `<chemin>:commande`) :
 *   - commande : un appel à `exec`, `shell_exec`, `proc_open`, `popen`, `passthru` ou `system`, un
 *     accent grave, ou `SentinelLib::getTracked()`, `getGitPaths()` et `getGitOutput()`, les
 *     lecteurs de git du socle ;
 *   - base : `connectDatabase(`, `->pdo(`, `new \PDO(`, ou une requête du modèle (`Xxx::model()` ou
 *     `new XxxModel()` dont la chaîne appelle une lecture ou une écriture) ;
 * - `state`, la valeur de chaque déclaration, qui doit dire ce que fait le fichier.
 *
 * Ce qu'elle ne voit pas : une sentinelle qui lit la base par un Lib ou par un modèle tenu dans une
 * variable, et une commande lancée par une fonction d'un autre fichier. Elle prend pour une requête
 * un `->count(` posé dans une closure en argument de la chaîne d'un modèle.
 * `SentinelLib::getFiles()`, qui lit git pour toutes, retombe sur le disque quand git manque.
 *
 * Lancement : dev/test package=dev file=SentinelResourceSentinel
 */
class SentinelResourceSentinelTest extends SentinelTest {

	const COMMANDS = ['exec', 'shell_exec', 'proc_open', 'popen', 'passthru', 'system'];

	// `getSql()` n'en fait pas partie : il rend le SQL d'une requête sans la lancer.
	const QUERIES = ['get', 'getCollection', 'getValue', 'getColumn', 'getArray', 'count', 'exists', 'sum', 'insert', 'update', 'delete'];

	const NOT_APPLICABLE = 'non applicable';

	const FALLBACK = 'repli : ';

	const CONVERT = 'à convertir : ';

	/**
	 * Ce que rend chaque sentinelle quand sa ressource manque, mesuré le 2026-10-16.
	 */
	const RESOURCE = [
		'accounting/town/test/PlaceSentinel.t.php:commande' => 'à convertir : node absent, échec nommé, puis exception sur $scene[\'places\']',
		'base/mail/test/MailCustomizeSentinelAi.t.php:base' => 'à convertir : base injoignable, exception ; table vide, vert sans rien vérifier',
		'framework/dev/test/AnchorSentinel.t.php:commande' => 'non applicable',
		'framework/dev/test/CliRefusalSentinel.t.php:commande' => 'repli : sans git, chaque refus déclaré se cherche dans tous les fichiers du disque',
		'framework/dev/test/Sentinel.t.php:commande' => 'repli : sans git, une déclaration périmée est nommée même pour un fichier hors suivi',
		'framework/dev/test/SentinelRatchet.t.php:commande' => 'repli : le témoin non suivi est attendu dans l\'inventaire quand git manque',
		'framework/editor/test/EditorValueSentinel.t.php:commande' => 'repli : sans git, les fichiers non suivis entrent dans l\'inventaire',
		'commercialisation/quotation/test/SectorSentinelAi.t.php:base' => 'à convertir : base injoignable, exception ; base qui ne descend pas de la production, échec qui le dit',
		'internal/data/test/MigrationVerifySentinelAi.t.php:commande' => 'repli : sans git, un script cité se cherche sur le disque',
		'internal/data/test/MigrationSchemaSentinelAi.t.php:base' => 'à convertir : base injoignable, exception ; base en retard, nomme les fichiers de migration à jouer',
		'internal/data/test/MigrationSchemaSentinelAi.t.php:commande' => 'à convertir : git absent, « DIRECTION INDÉTERMINÉE », et la marque NON COMMITÉ disparait sans le dire',
		'internal/security/test/NginxScanSentinelAi.t.php:base' => 'à convertir : base injoignable, exception ; base vide, vert sans rien vérifier',
		'internal/security/test/SoftwareSentinelAi.t.php:commande' => 'à convertir : git absent, échec nommé « git ne répond pas » sur les fichiers de verrou',
		'ouvretaferme/design/test/SkillDocumentedSentinelAi.t.php:commande' => 'repli : sans git, aucun skill ne se dit non suivi, le verdict ne change pas',
		'ouvretaferme/main/test/ClosedBoundarySentinel.t.php:commande' => 'repli : sans git, les fichiers non suivis entrent dans l\'inventaire',
		'ouvretaferme/main/test/PublicDataSentinelAi.t.php:commande' => 'à convertir : git absent, exception « git est indisponible » dans chaque méthode qui lit les fichiers publiés',
		'ouvretaferme/subscription/test/SummarySentinel.t.php:commande' => 'à convertir : grep absent, accuse DECLARED, et testNoWrite passe au vert sans rien vérifier',
		'ouvretaferme/website/test/TemplateCatalogSentinelAi.t.php:base' => 'à convertir : base injoignable, exception ; base en retard, nomme les gabarits et la migration',
		'production/map/test/DecorWeightSentinel.t.php:commande' => 'à convertir : node absent, échec nommé « node ne se lance pas ici »',
		'production/work/test/PhytoCropSentinelAi.t.php:base' => 'à convertir : base injoignable, exception ; catalogue vide, accuse sa déclaration et CROPS',
	];

	/**
	 * @var array<string, bool> par fichier, s'il appelle `notApplicable()`
	 */
	private array $notApplicable = [];

	public function init() {

		foreach(SentinelLib::getFiles('.php') as $file => $path) {

			if(fnmatch('*Sentinel*.t.php', basename($file)) === FALSE) {
				continue;
			}

			$code = file_get_contents($path);

			foreach(self::scan(SentinelLib::getTokens($code)) as $resource => $line) {
				$this->addFoundKey('resource', $file, $resource, $line);
			}

			$this->notApplicable[$file] = str_contains($code, '->notApplicable(');

		}

	}

	public function testResource(): void {
		$this->assertDeclared('resource', self::RESOURCE, 'sentinelle qui lit la base ou lance une commande : déclarer dans RESOURCE « à convertir : <ce qu\'elle rend quand cette ressource manque> », « repli : <ce qu\'elle lit à la place> », ou « non applicable » si elle appelle notApplicable()');
	}

	public function testState(): void {

		$wrong = [];

		foreach(self::RESOURCE as $key => $state) {

			$file = substr($key, 0, strrpos($key, ':'));
			$calls = ($this->notApplicable[$file] ?? FALSE);

			if($state === self::NOT_APPLICABLE) {

				if($calls === FALSE) {
					$wrong[] = $key.' (déclarée non applicable, n\'appelle pas notApplicable())';
				}

			} else if(self::isStated($state, self::CONVERT) or self::isStated($state, self::FALLBACK)) {

				if($calls) {
					$wrong[] = $key.' (appelle notApplicable(), déclarer « non applicable »)';
				}

			} else {
				$wrong[] = $key.' (ni « non applicable », ni « repli : … », ni « à convertir : … »)';
			}

		}

		$this->assertEquals([], $wrong, 'état mal dit dans RESOURCE, il suit le fichier : '.implode(' · ', $wrong));

	}

	private static function isStated(string $state, string $prefix): bool {
		return str_starts_with($state, $prefix) and strlen($state) > strlen($prefix);
	}

	/**
	 * La première ligne de chaque ressource qu'un fichier lit.
	 *
	 * @return array<string, int>
	 */
	private static function scan(array $tokens): array {

		$resources = [];
		$count = count($tokens);

		foreach($tokens as $i => [$type, $text, $line]) {

			$previous = $tokens[$i - 1][0] ?? NULL;
			$call = (($tokens[$i + 1][0] ?? NULL) === '(');

			$name = ltrim($text, '\\');

			if(in_array($type, [T_STRING, T_NAME_FULLY_QUALIFIED], TRUE) and $call and in_array($name, self::COMMANDS, TRUE) and in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], TRUE) === FALSE) {
				$resources['commande'] ??= $line;
			} else if($type === '`' or ($type === T_STRING and $call and in_array($text, ['getTracked', 'getGitPaths', 'getGitOutput'], TRUE) and $previous === T_DOUBLE_COLON)) {
				$resources['commande'] ??= $line;
			} else if(($type === T_STRING and $call and ($text === 'connectDatabase' or ($text === 'pdo' and $previous === T_OBJECT_OPERATOR))) or ($type === T_NEW and in_array($tokens[$i + 1][1] ?? '', ['\\PDO', 'PDO'], TRUE))) {
				$resources['base'] ??= $line;
			} else if(($type === T_STRING and $text === 'model' and $previous === T_DOUBLE_COLON) or ($type === T_NEW and str_ends_with($tokens[$i + 1][1] ?? '', 'Model'))) {

				for($j = $i + 1; $j < $count and $tokens[$j][0] !== ';'; $j++) {

					if($tokens[$j][0] === T_OBJECT_OPERATOR and in_array($tokens[$j + 1][1] ?? '', self::QUERIES, TRUE) and ($tokens[$j + 2][0] ?? NULL) === '(') {
						$resources['base'] ??= $line;
						break;
					}

				}

			}

		}

		return $resources;

	}

	public function testDetector(): void {

		$code = <<<'PHP'
<?php
$this->exec('x');
function system() {}
\dev\Thing::popen();
exec('git status', $output);
$value = \data\Thing::model()
	->whereId(1)
	->get();
\data\Thing::model()->select('id');
PHP;

		$this->assertEquals(['commande' => 5, 'base' => 6], self::scan(SentinelLib::getTokens($code)), 'une commande, une requête sur deux lignes ; ni méthode homonyme ni modèle sans requête');

		$this->assertEquals(['base' => 2], self::scan(SentinelLib::getTokens("<?php\nnew \\data\\ThingModel()->whereId(1)->count();\n\\structure\\StructureLib::connectDatabase(\$e);")), 'new XxxModel() et connectDatabase');

		$code = <<<'PHP'
<?php
$sql = \data\Thing::model()->whereId(1)->getSql();
$files = SentinelLib::getTracked();
$pdo = new \PDO('mysql:');
PHP;

		$this->assertEquals(['commande' => 3, 'base' => 4], self::scan(SentinelLib::getTokens($code)), 'getSql() ne lit rien, getTracked() lit git, new \\PDO ouvre une base');
		$this->assertEquals(['commande' => 2], self::scan(SentinelLib::getTokens("<?php\n\$paths = SentinelLib::getGitPaths('ls-files');")), 'getGitPaths() lit git');
		$this->assertEquals(['commande' => 2], self::scan(SentinelLib::getTokens("<?php\n\\exec('ls');")), 'commande en nom pleinement qualifié');
		$this->assertEquals(['commande' => 2], self::scan(SentinelLib::getTokens("<?php\n\$x = `ls`;")), 'accent grave');

	}

}
?>
