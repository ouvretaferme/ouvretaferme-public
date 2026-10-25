<?php
namespace session;

/**
 * La session d'un onglet n'existe que sur un poste de dev qui l'a ouverte par son `secret.c.php`, et
 * ne s'atteint que par `SessionLib::getTabSid()`.
 *
 * Un identifiant de session lisible en JavaScript annule `HttpOnly` : en production, ce mécanisme
 * serait une porte. Rien ne le rappelle à qui touchera `Session.l.php` dans six mois, d'où huit
 * inventaires :
 *
 * - les noms du mécanisme (l'en-tête et le champ de formulaire qui porte son nom, la clé de
 *   `sessionStorage`, le cookie qui marque le navigateur) n'apparaissent que dans les fichiers
 *   déclarés ;
 * - l'en-tête ne se lit, côté serveur, que dans `getTabSid()` ;
 * - le champ ne se lit, côté serveur, que dans `getTabSidPosted()` ;
 * - aucun code ne lit les en-têtes en bloc (`getallheaders()`, `apache_request_headers()`), qui
 *   rendraient l'en-tête sans le nommer ;
 * - le mode onglet ne se pose qu'une fois, depuis `getTabSid()` ;
 * - `getTabSid()`, `getTabSidPosted()` et `needsTabShell()` commencent par
 *   `if(self::isTabEnabled() === FALSE)` ;
 * - `isTabEnabled()` exige à la fois dev et l'activation, fermée par défaut et posée par le seul
 *   `setTabEnabled()` ;
 * - hors des tests déclarés, aucun fichier suivi n'appelle `setTabEnabled()` ni
 *   `TabLib::setWorkstationUser()`, que le secret d'un poste appelle : le second connecte sans mot
 *   de passe, et le compte qu'il nomme n'est pas le même d'un poste à l'autre. Un appel par nom en
 *   chaîne (`call_user_func()`) échappe à cet inventaire.
 *
 * Lancement : dev/test package=session file=SessionTabSentinel
 */
class SessionTabSentinelTest extends \dev\SentinelTest {

	const SESSION = 'framework/session/lib/Session.l.php';

	const PATTERN = '/x-session-tab|HTTP_X_SESSION_TAB|session-tab|tabidentity/';

	/**
	 * Les fichiers qui nomment le mécanisme, par chemin explicite : un fichier renommé fait échouer
	 * la sentinelle des deux côtés, au lieu de sortir en silence d'un motif.
	 *
	 * - `Session.l.php` : la lecture de l'en-tête et celle du champ, le renvoi de l'en-tête, le cookie
	 *   lu par `needsTabShell()`, et le shell qui relit `sessionStorage` et rejoue la requête.
	 * - `ajax.js` : `Ajax.Tab`, qui pose l'en-tête sur toute requête, le champ sur un formulaire posté
	 *   hors ajax, et relit la réponse.
	 * - `base/user/lib/Tab.l.php` : la pose du cookie qui marque le navigateur.
	 * - `SessionTab.t.php` : le décor de `$_SERVER`, `$_POST` et `$_COOKIE`.
	 * - `ErrorSecret.t.php` : le champ est masqué dans le journal des erreurs, qui garde tout `$_POST`.
	 *
	 * @var array<string, int>
	 */
	const NAMED = [
		'base/user/lib/Tab.l.php' => 1,
		'framework/dev/test/ErrorSecret.t.php' => 1,
		'framework/session/lib/Session.l.php' => 7,
		'framework/session/test/SessionTab.t.php' => 23,
		'framework/util/static/js/ajax.js' => 5,
	];

	/**
	 * Où `$_SERVER['HTTP_X_SESSION_TAB']` se lit hors de `getTabSid()`.
	 *
	 * @var array<string, int>
	 */
	const HEADER_READ = [
		'framework/session/test/SessionTab.t.php' => 12,
	];

	/**
	 * Où le champ `x-session-tab` se cite en chaîne hors de `getTabSidPosted()`.
	 *
	 * - `SessionTab.t.php` : le décor de `$_POST`.
	 * - `ErrorSecret.t.php` : le nom passé à `ErrorPhpLib::hideSecret()`, qui ne lit rien.
	 *
	 * @var array<string, int>
	 */
	const FIELD_READ = [
		'framework/dev/test/ErrorSecret.t.php' => 1,
		'framework/session/test/SessionTab.t.php' => 8,
	];

	/**
	 * Les lectures en bloc des en-têtes.
	 *
	 * - `framework/api/lib/Log.l.php` : le journal d'un appel d'API, qui retire `Authorization` et
	 *   `Cookie` et ne tire aucune identité de ce qu'il garde.
	 *
	 * @var array<string, int>
	 */
	const HEADERS_READ = [
		'framework/api/lib/Log.l.php' => 1,
	];

	/**
	 * Les appels à `setTabEnabled()` et à `setWorkstationUser()` dans les fichiers suivis.
	 *
	 * - `SessionTab.t.php` : l'ouvre pour éprouver le mécanisme, le ferme pour éprouver la garde,
	 *   et rend la valeur du poste en fin de test.
	 * - `base/user/test/Tab.t.php` : déclare un compte du décor comme compte du poste, et rend
	 *   celui du poste en fin de test.
	 *
	 * @var array<string, int>
	 */
	const ACTIVATION = [
		'base/user/test/Tab.t.php' => 2,
		'framework/session/test/SessionTab.t.php' => 3,
	];

	const ACTIVATION_CALLS = ['setTabEnabled', 'setWorkstationUser'];

	const SECRET = 'ouvretaferme/secret.c.php';

	const GUARDED = ['getTabSid', 'getTabSidPosted', 'needsTabShell'];

	const GUARD = 'if(self::isTabEnabled()===FALSE){return';

	/** L'accolade fermante est celle de la méthode : le corps entier tient dans cette seule instruction. */
	const ACTIVATION_BODY = 'return(LIME_ENV===\'dev\'andself::$tabEnabled);}';

	const ACTIVATION_DEFAULT = 'privatestaticbool$tabEnabled=FALSE;';

	public function init() {

		foreach(\dev\SentinelLib::getFiles('.php') + \dev\SentinelLib::getFiles('.js') as $file => $path) {

			if(fnmatch('*Sentinel*.t.php', basename($file))) {
				continue;
			}

			$code = file_get_contents($path);

			$this->addFound('named', $file, self::scanNamed($code));

			if(str_ends_with($file, '.php')) {

				$tokens = \dev\SentinelLib::getTokens($code);

				$this->addFound('headerRead', $file, self::scanRead($tokens, 'HTTP_X_SESSION_TAB', 'getTabSid', $file === self::SESSION));
				$this->addFound('fieldRead', $file, self::scanRead($tokens, 'x-session-tab', 'getTabSidPosted', $file === self::SESSION));
				$this->addFound('headersRead', $file, self::scanHeadersRead($tokens));
				// git ne suit pas le secret, mais le repli sur le système de fichiers le lit, et c'est le seul endroit où l'appel a sa place
				if($file !== self::SECRET) {
					$this->addFound('activation', $file, self::scanActivation($tokens));
				}

			}

		}

	}

	/**
	 * Une ligne par occurrence d'un des noms.
	 *
	 * @return int[]
	 */
	private static function scanNamed(string $code): array {

		$lines = [];

		preg_match_all(self::PATTERN, $code, $matches, PREG_OFFSET_CAPTURE);

		foreach($matches[0] as [, $offset]) {
			$lines[] = substr_count($code, "\n", 0, $offset) + 1;
		}

		return $lines;

	}

	/**
	 * Les lignes qui citent la chaîne `$name`, hors du corps de `$method` quand `$inSession`.
	 *
	 * @return int[]
	 */
	private static function scanRead(array $tokens, string $name, string $method, bool $inSession): array {

		[$start, $end] = $inSession ? self::getBody($tokens, $method) : [-1, -1];

		$lines = [];

		foreach($tokens as $position => $token) {

			if(
				$token[0] === T_CONSTANT_ENCAPSED_STRING and
				substr($token[1], 1, -1) === $name and
				($position > $start and $position < $end) === FALSE
			) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * Les appels à `getallheaders()` et `apache_request_headers()`, sans leur déclaration.
	 *
	 * @return int[]
	 */
	private static function scanHeadersRead(array $tokens): array {

		$lines = [];

		foreach($tokens as $position => $token) {

			if(
				$token[0] === T_STRING and
				in_array(strtolower($token[1]), ['getallheaders', 'apache_request_headers'], TRUE) and
				($tokens[$position + 1][0] ?? NULL) === '(' and
				in_array($tokens[$position - 1][0] ?? NULL, [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], TRUE) === FALSE
			) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * Les appels à `setTabEnabled()` et à `setWorkstationUser()`, sans leur déclaration.
	 *
	 * @return int[]
	 */
	private static function scanActivation(array $tokens): array {

		$lines = [];

		foreach($tokens as $position => $token) {

			if(
				$token[0] === T_STRING and
				in_array($token[1], self::ACTIVATION_CALLS, TRUE) and
				($tokens[$position + 1][0] ?? NULL) === '(' and
				($tokens[$position - 1][0] ?? NULL) !== T_FUNCTION
			) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * Les écritures de `self::$tabEnabled`, chacune sous la forme de son instruction.
	 *
	 * @return string[]
	 */
	private static function getActivationAssignments(array $tokens): array {

		$assignments = [];

		foreach($tokens as $position => $token) {

			if(
				$token[0] === T_VARIABLE and
				$token[1] === '$tabEnabled' and
				($tokens[$position - 1][0] ?? NULL) === T_DOUBLE_COLON and
				($tokens[$position + 1][0] ?? NULL) === '='
			) {

				for($end = $position; $tokens[$end][0] !== ';'; $end++) {
				}

				$assignments[] = self::getText($tokens, $position - 2, $end);

			}

		}

		return $assignments;

	}

	/**
	 * Les positions de l'accolade ouvrante et fermante du corps d'une méthode, `[-1, -1]` si elle
	 * n'existe pas.
	 *
	 * @return array{0: int, 1: int}
	 */
	private static function getBody(array $tokens, string $method): array {

		foreach($tokens as $position => $token) {

			if(
				$token[0] === T_STRING and
				$token[1] === $method and
				($tokens[$position - 1][0] ?? NULL) === T_FUNCTION
			) {

				for($open = $position; $tokens[$open][0] !== '{'; $open++) {
				}

				return [$open, \dev\SentinelLib::getClosing($tokens, $open)];

			}

		}

		return [-1, -1];

	}

	/**
	 * Le texte des jetons de `$start` à `$end` inclus, sans espace.
	 */
	private static function getText(array $tokens, int $start, int $end): string {

		$text = '';

		for($position = $start; $position <= $end; $position++) {
			$text .= $tokens[$position][1];
		}

		return $text;

	}

	/**
	 * Les instructions qui affectent `self::$tab`, chacune précédée de la sienne.
	 *
	 * @return array<int, array{0: string, 1: string}> l'instruction précédente et l'affectation, par ligne
	 */
	private static function getTabAssignments(array $tokens): array {

		$assignments = [];

		foreach($tokens as $position => $token) {

			if(
				$token[0] === T_VARIABLE and
				$token[1] === '$tab' and
				($tokens[$position - 1][0] ?? NULL) === T_DOUBLE_COLON and
				($tokens[$position + 1][0] ?? NULL) === '='
			) {

				$start = $position - 2;

				for($end = $position; $tokens[$end][0] !== ';'; $end++) {
				}

				for($previous = $start - 2; $previous > 0 and in_array($tokens[$previous - 1][0], [';', '{', '}'], TRUE) === FALSE; $previous--) {
				}

				$assignments[$token[2]] = [
					self::getText($tokens, $previous, $start - 1),
					self::getText($tokens, $start, $end),
				];

			}

		}

		return $assignments;

	}

	/**
	 * Le texte des premières instructions d'une méthode, jusqu'à la fin du premier bloc.
	 */
	private static function getFirstStatement(array $tokens, string $method): ?string {

		[$open, $close] = self::getBody($tokens, $method);

		if($open === -1) {
			return NULL;
		}

		for($end = $open + 1; $end < $close and $tokens[$end][0] !== '}'; $end++) {
		}

		return self::getText($tokens, $open + 1, $end);

	}

	/** Les détecteurs trouvent la forme fautive et laissent passer ses voisines. */
	public function testScannersFind(): void {

		$this->assertEquals([2, 3, 3, 4], self::scanNamed("<?php\n'x-session-tab';\n'HTTP_X_SESSION_TAB' . 'session-tab';\n\$_COOKIE['tabidentity'];\n'session' . 'tab';"), 'chaque nom compte une fois par occurrence, x-session-tab compris');

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			$a = SERVER('HTTP_X_SESSION_TAB');
			class A {
				public static function getTabSid() {
					return SERVER('HTTP_X_SESSION_TAB');
				}
			}
			PHP);

		$this->assertEquals([2], self::scanRead($tokens, 'HTTP_X_SESSION_TAB', 'getTabSid', TRUE), 'seule la lecture hors de getTabSid() est trouvée');
		$this->assertEquals([2, 5], self::scanRead($tokens, 'HTTP_X_SESSION_TAB', 'getTabSid', FALSE), 'hors de Session.l.php, toute lecture est trouvée');

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			$a = $_POST["x-session-tab"];
			class A {
				private static function getTabSidPosted() {
					return POST('x-session-tab', '?string');
				}
				public static function getTabSid() {
					return POST('x-session-tab');
				}
			}
			header('x-session-tab: 1');
			PHP);

		$this->assertEquals([2, 8], self::scanRead($tokens, 'x-session-tab', 'getTabSidPosted', TRUE), 'seule la lecture du champ hors de getTabSidPosted() est trouvée, quels que soient les guillemets, et l\'en-tête renvoyé ne compte pas');

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			$headers = getallheaders();
			$headers = apache_request_headers();
			function getallheaders() {}
			$this->getallheaders();
			$exists = function_exists('getallheaders');
			PHP);

		$this->assertEquals([2, 3], self::scanHeadersRead($tokens), 'seuls les appels sont trouvés');

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			\session\SessionLib::setTabEnabled(TRUE);
			class A {
				public static function setTabEnabled(bool $tabEnabled) {}
			}
			SessionLib::setTabEnabled(FALSE);
			$name = 'setTabEnabled';
			\user\TabLib::setWorkstationUser(new \user\User(['id' => 1]));
			PHP);

		$this->assertEquals([2, 6, 8], self::scanActivation($tokens), 'seuls les appels sont trouvés, la déclaration et le nom cité ne comptent pas');

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			class A {
				public static function init() {
					$tabSid = self::getTabSid();
					self::$tab = ($tabSid !== NULL);
					self::$tab = TRUE;
				}
				public static function isTabEnabled() {
					return (LIME_ENV === 'dev' and self::$tabEnabled);
				}
				public static function getTabSid() {
					if(self::isTabEnabled() === FALSE) {
						return NULL;
					}
				}
				private static function needsTabShell() {
					$a = 1;
					if(self::isTabEnabled() === FALSE) {
						return FALSE;
					}
				}
			}
			PHP);

		$this->assertEquals([
			5 => ['$tabSid=self::getTabSid();', 'self::$tab=($tabSid!==NULL);'],
			6 => ['self::$tab=($tabSid!==NULL);', 'self::$tab=TRUE;'],
		], self::getTabAssignments($tokens), 'chaque affectation est lue avec l\'instruction qui la précède');

		$this->assertEquals('if(self::isTabEnabled()===FALSE){returnNULL;}', self::getFirstStatement($tokens, 'getTabSid'), 'la garde en tête est lue');
		$this->assertFalse(str_starts_with(self::getFirstStatement($tokens, 'needsTabShell'), self::GUARD), 'une garde déplacée n\'est plus la première instruction');
		$this->assertEquals(self::ACTIVATION_BODY, self::getFirstStatement($tokens, 'isTabEnabled'), 'le corps de isTabEnabled() est lu');

	}

	public function testNamesStayInDeclaredFiles(): void {

		$this->assertRatchet(
			'named',
			self::NAMED,
			'nom de la session d\'onglet hors des fichiers déclarés dans SessionTabSentinel::NAMED : le mécanisme n\'existe qu\'en dev, et ne s\'atteint que par SessionLib::getTabSid()'
		);

	}

	public function testHeaderIsReadOnlyInTabSid(): void {

		$this->assertRatchet(
			'headerRead',
			self::HEADER_READ,
			'en-tête x-session-tab lu hors de SessionLib::getTabSid(), qui seul refuse tout environnement autre que dev'
		);

	}

	public function testFieldIsReadOnlyInTabSidPosted(): void {

		$this->assertRatchet(
			'fieldRead',
			self::FIELD_READ,
			'champ x-session-tab lu hors de SessionLib::getTabSidPosted(), qui seul refuse tout environnement autre que dev'
		);

	}

	public function testHeadersAreNotReadInBulk(): void {

		$this->assertRatchet(
			'headersRead',
			self::HEADERS_READ,
			'en-têtes lus en bloc, ce qui rendrait x-session-tab sans le nommer : lire l\'en-tête voulu par SERVER(\'HTTP_…\')'
		);

	}

	public function testTabModeIsSetOnceFromTabSid(): void {

		$tokens = \dev\SentinelLib::getTokens(file_get_contents(\dev\SentinelLib::getRoot().'/'.self::SESSION));

		$this->assertEquals(
			[['$tabSid=self::getTabSid();', 'self::$tab=($tabSid!==NULLand$tabSid!==self::TAB_COOKIE);']],
			array_values(self::getTabAssignments($tokens)),
			'SessionLib::$tab ne se pose qu\'une fois, depuis getTabSid(), juste après sa lecture'
		);

	}

	public function testGuardIsFirstStatement(): void {

		$tokens = \dev\SentinelLib::getTokens(file_get_contents(\dev\SentinelLib::getRoot().'/'.self::SESSION));

		foreach(self::GUARDED as $method) {

			$statement = self::getFirstStatement($tokens, $method);

			$this->assertTrue(
				$statement !== NULL and str_starts_with($statement, self::GUARD),
				'SessionLib::'.$method.'() ne commence plus par la garde if(self::isTabEnabled() === FALSE) { return … } : la session d\'onglet s\'ouvrirait sans activation, ou hors de dev'
			);

		}

	}

	public function testActivationRequiresDevAndSecret(): void {

		$tokens = \dev\SentinelLib::getTokens(file_get_contents(\dev\SentinelLib::getRoot().'/'.self::SESSION));

		$this->assertEquals(
			self::ACTIVATION_BODY,
			self::getFirstStatement($tokens, 'isTabEnabled'),
			'SessionLib::isTabEnabled() ne rend plus (LIME_ENV === \'dev\' and self::$tabEnabled) : la session d\'onglet s\'ouvrirait sans activation, ou hors de dev'
		);

	}

	public function testActivationClosedByDefault(): void {

		$tokens = \dev\SentinelLib::getTokens(file_get_contents(\dev\SentinelLib::getRoot().'/'.self::SESSION));

		$this->assertTrue(
			str_contains(self::getText($tokens, 0, count($tokens) - 1), self::ACTIVATION_DEFAULT),
			'SessionLib::$tabEnabled ne se déclare plus private static bool $tabEnabled = FALSE : l\'identité par onglet s\'ouvrirait sur tous les postes de dev sans rien dans le secret'
		);

		$this->assertEquals(
			['self::$tabEnabled=$tabEnabled;'],
			self::getActivationAssignments($tokens),
			'SessionLib::$tabEnabled s\'écrit ailleurs que dans setTabEnabled() : l\'identité par onglet s\'ouvrirait sans rien dans le secret'
		);

	}

	public function testActivationOnlyFromSecret(): void {

		$this->assertRatchet(
			'activation',
			self::ACTIVATION,
			'SessionLib::setTabEnabled() ou TabLib::setWorkstationUser() appelé par un fichier suivi : l\'identité par onglet et le compte du poste ne se déclarent que par le secret.c.php d\'un poste. Un test qui doit les poser se déclare dans ACTIVATION, avec sa raison'
		);

	}

}
?>
