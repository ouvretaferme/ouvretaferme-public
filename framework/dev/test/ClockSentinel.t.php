<?php
namespace dev;

/**
 * Le code du produit lit l'heure par `\Clock`, qu'un test fige. Une lecture directe
 * (`date('Y-m-d')`, `time()`, `new DateTime()`, `strtotime('-1 year')`) rend l'heure du serveur sous
 * une horloge réglée : le test daté lit alors deux jours différents dans la même méthode.
 *
 * Les fonctions `currentTimestamp()`, `currentDate()` et leurs voisines sont retirées : aucun appel,
 * tests compris, ni aucune définition ne les fait revenir.
 *
 * `\Clock::machine()` lit l'heure du serveur, qu'aucun test ne fige. Ses appels sont déclarés par
 * fichier avec leur raison. `self::machine()` dans `Clock.php` échappe à la règle : c'est la lecture
 * que `timestamp()` emprunte quand aucun test n'a réglé l'horloge.
 *
 * `date()` et ses cousines ne comptent que sans horodatage. `strtotime()` sans horodatage de départ,
 * `new DateTime()` et `date_create()` ne comptent que sur un littéral sans année ou sur un décalage
 * assemblé (`'- '.$days.' day'`, `$days.' days ago'`, `($date ?? 'now').' +1 day'`) : avec une date
 * en argument, elles formatent ou décalent une valeur reçue. `microtime()` et `hrtime()` mesurent
 * une durée et restent à l'heure réelle.
 *
 * En SQL, `NOW()`, `CURDATE()`, `CURTIME()` et `UNIX_TIMESTAMP()` suivent la variable de session
 * que `Clock` pose sur chaque connexion. `SYSDATE()` ne la suit pas.
 *
 * Ce que la sentinelle ne voit pas : une fonction ou une classe appelée par son nom en chaîne, une
 * heure lue par le système de fichiers, un décalage assemblé qui ne commence pas par un signe et ne
 * porte aucun de `now`, `ago`, `today`, `tomorrow`, `yesterday` (`$days.' days'`), un décalage écrit
 * entre guillemets doubles avec une variable ou par `sprintf()`, et `DateTime::createFromFormat()` sans `!`,
 * qui prend à l'heure courante les champs que le format ne porte pas.
 *
 * Ce qu'elle relève à tort : un argument dont un littéral porte un de ces cinq mots sans être un
 * décalage (`strtotime($e['today'])`, `$date.' tomorrow'`).
 *
 * Lancement : dev/test package=dev file=ClockSentinel
 */
class ClockSentinelTest extends SentinelTest {

	const RULE_DIRECT = 'direct';
	const RULE_SYSDATE = 'sysdate';
	const RULE_SET = 'set';
	const RULE_TIMESTAMP = 'timestamp';
	const RULE_SET_CLOCK = 'setClock';
	const RULE_HELPER = 'helper';
	const RULE_HELPER_DEFINITION = 'helperDefinition';
	const RULE_MACHINE = 'machine';

	const WITHOUT_ARGUMENT = ['time', 'getdate', 'localtime'];
	const WITHOUT_TIMESTAMP = ['date', 'gmdate', 'idate'];
	const WITHOUT_EVERY_FIELD = ['mktime', 'gmmktime'];
	const CLASSES = ['datetime', 'datetimeimmutable'];
	const CREATORS = ['date_create', 'date_create_immutable'];
	const HELPERS = ['currenttimestamp', 'currentdatetime', 'currentdate', 'currenttime', 'currentweek', 'currentmonth', 'currentyear', 'nextyear'];

	const DIRECT = [
		// La lecture que toutes les autres empruntent
		'framework/core/Clock.php' => 1,
	];

	const SET = [
		'framework/core/Test.php' => 1,
		// Un décor de dev qui fait vieillir une AMAP : le temps y passe pour de bon, et l'exception vaut pour ce seul fichier (Vincent, 2026-10-16)
		'commercialisation/amap/page/configure/decorVieilli.p.php' => 1,
	];

	const TIMESTAMP = [
		'framework/core/Clock.php' => 2,
	];

	const MACHINE = [
		// L'expiration d'un cookie est jugée par le navigateur
		'base/user/lib/ChallengeAi.l.php' => 1,
		'base/user/lib/Connection.l.php' => 2,
		'base/user/lib/Tab.l.php' => 1,
		// L'expiration d'une session de paiement est jugée par Stripe
		'commercialisation/shop/lib/Sale.l.php' => 1,
		'ouvretaferme/association/lib/Membership.l.php' => 1,
		'ouvretaferme/subscription/lib/Subscription.l.php' => 1,
		// L'horodatage d'un webhook est posé par Stripe à l'heure réelle
		'commercialisation/payment/lib/Stripe.l.php' => 1,
		// Le jeton est validé par la bibliothèque OAuth, qui lit l'heure de la machine
		'framework/api/lib/PersonalAccessToken.l.php' => 1,
		// Une échéance de cache ou de session survit à la requête qui l'a posée
		'framework/core/Cache.php' => 2,
		'framework/session/lib/Session.l.php' => 1,
		// getId() garantit l'unicité, qu'une heure figée ne garantit plus
		'framework/core/Function.php' => 1,
		// Une tâche boucle jusqu'à une heure : figée, elle ne s'arrête pas
		'framework/core/Page.php' => 2,
		'framework/storage/lib/Buffer.l.php' => 1,
		// Une version d'image doit changer à chaque rotation, sinon le navigateur garde l'ancienne
		'base/media/view/index.v.php' => 2,
		'framework/editor/ui/Editor.u.php' => 1,
		// L'âge d'un panier est jugé par le navigateur, à sa propre horloge
		'commercialisation/shop/ui/Basket.u.php' => 1,
		// En dev, une heure figée laisse la clé du verrou de nettoyage inchangée, et le nettoyage ne se rejoue plus
		'base/media/lib/Clean.l.php' => 1,
		// La signature d'un appel entre serveurs est comparée à l'heure de l'autre serveur
		'framework/lime.php' => 2,
	];

	public function init(): void {

		foreach($this->getPhpTokens() as $file => $tokens) {

			$this->addFound(self::RULE_SET, $file, self::scanCall($tokens, 'Clock', 'set'));
			$this->addFound(self::RULE_TIMESTAMP, $file, self::scanCall($tokens, 'ModuleModel', 'setTimestamp'));
			$this->addFound(self::RULE_HELPER, $file, self::scanHelper($tokens));
			$this->addFound(self::RULE_HELPER_DEFINITION, $file, self::scanHelperDefinition($tokens));

			if(self::isTest($file)) {
				continue;
			}

			$this->addFound(self::RULE_DIRECT, $file, self::scan($tokens));
			$this->addFound(self::RULE_SYSDATE, $file, self::scanSysdate($tokens));
			$this->addFound(self::RULE_SET_CLOCK, $file, self::scanMethod($tokens, 'setClock'));
			$this->addFound(self::RULE_MACHINE, $file, self::scanCall($tokens, 'Clock', 'machine'));

		}

	}

	public static function isTest(string $file): bool {
		return (str_ends_with($file, '.t.php') or str_contains('/'.$file, '/test/'));
	}

	/**
	 * @param array<int, array{0: int|string, 1: string, 2: int}> $tokens
	 * @return int[]
	 */
	public static function scan(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			$next = $tokens[$i + 1][1] ?? NULL;

			if($type === T_NEW) {

				if(
					in_array(self::getName($tokens[$i + 1] ?? NULL), self::CLASSES, TRUE) and
					(($tokens[$i + 2][1] ?? NULL) !== '(' or self::countArguments($tokens, $i + 2) === 0 or self::isRelative($tokens, $i + 2))
				) {
					$lines[] = $line;
				}

				continue;

			}

			$name = self::getName($tokens[$i]);

			if($name === NULL or $next !== '(') {
				continue;
			}

			if(in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)) {
				continue;
			}

			$arguments = self::countArguments($tokens, $i + 1);

			if(in_array($name, self::WITHOUT_ARGUMENT, TRUE)) {
				$direct = ($arguments === 0);
			} else if(in_array($name, self::WITHOUT_TIMESTAMP, TRUE)) {
				$direct = ($arguments <= 1);
			} else if(in_array($name, self::WITHOUT_EVERY_FIELD, TRUE)) {
				$direct = ($arguments < 6);
			} else if(in_array($name, self::CREATORS, TRUE)) {
				$direct = ($arguments === 0 or self::isRelative($tokens, $i + 1));
			} else if($name === 'strtotime') {
				$direct = ($arguments === 1 and self::isRelative($tokens, $i + 1));
			} else {
				$direct = FALSE;
			}

			if($direct) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * @return int[]
	 */
	public static function scanHelper(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				in_array(self::getName($tokens[$i]), self::HELPERS, TRUE) and
				($tokens[$i + 1][1] ?? NULL) === '(' and
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE) === FALSE
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * @return int[]
	 */
	public static function scanHelperDefinition(array $tokens): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if($type === T_FUNCTION and in_array(self::getName($tokens[$i + 1] ?? NULL), self::HELPERS, TRUE)) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * @return int[]
	 */
	public static function scanSysdate(array $tokens): array {

		$lines = [];

		foreach($tokens as $token) {

			$string = self::getString($token);

			if($string !== NULL and preg_match('/\bSYSDATE\s*\(/i', $string) === 1) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * @return int[]
	 */
	public static function scanCall(array $tokens, string $class, string $method): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				$type === T_DOUBLE_COLON and
				ltrim($tokens[$i - 1][1] ?? '', '\\') === $class and
				($tokens[$i + 1][1] ?? NULL) === $method and
				($tokens[$i + 2][1] ?? NULL) === '('
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	/**
	 * @return int[]
	 */
	public static function scanMethod(array $tokens, string $method): array {

		$lines = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(
				($type === T_OBJECT_OPERATOR or $type === T_DOUBLE_COLON) and
				($tokens[$i + 1][1] ?? NULL) === $method and
				($tokens[$i + 2][1] ?? NULL) === '('
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	private static function getName(?array $token): ?string {

		if($token === NULL or in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], TRUE) === FALSE) {
			return NULL;
		}

		return strtolower(ltrim($token[1], '\\'));

	}

	private static function countArguments(array $tokens, int $open): int {

		$close = SentinelLib::getClosing($tokens, $open);

		if($close === $open + 1) {
			return 0;
		}

		$count = 1;
		$depth = 0;

		for($i = $open + 1; $i < $close; $i++) {

			$text = $tokens[$i][1];

			if($text === '(' or $text === '[' or $text === '{') {
				$depth++;
			} else if($text === ')' or $text === ']' or $text === '}') {
				$depth--;
			} else if($text === ',' and $depth === 0) {
				$count++;
			}

		}

		return $count;

	}

	private static function isRelative(array $tokens, int $open): bool {

		$first = $tokens[$open + 1];
		$next = $tokens[$open + 2][1] ?? NULL;

		if($first[0] === T_CONSTANT_ENCAPSED_STRING and in_array($next, [')', ','], TRUE)) {

			$literal = substr($first[1], 1, -1);

			return (
				str_starts_with($literal, '@') === FALSE and
				preg_match('/(^|[^0-9])[0-9]{4}([^0-9]|$)/', $literal) === 0
			);

		}

		if($first[0] === T_CONSTANT_ENCAPSED_STRING and preg_match('/^.\s*[+-]/', $first[1]) === 1) {
			return TRUE;
		}

		$close = SentinelLib::getClosing($tokens, $open);
		$depth = 0;

		for($i = $open + 1; $i < $close; $i++) {

			[$type, $text] = $tokens[$i];

			if($text === '(' or $text === '[') {
				$depth++;
			} else if($text === ')' or $text === ']') {
				$depth--;
			} else if($text === ',' and $depth === 0) {
				break;
			} else if($type === T_CONSTANT_ENCAPSED_STRING and preg_match('/\b(now|ago|today|tomorrow|yesterday)\b/i', $text) === 1) {
				return TRUE;
			}

		}

		return FALSE;

	}

	public function testScannerFindsDirectRead(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			$today = date('Y-m-d');
			$formatted = date('Y-m-d', $timestamp);
			$now = time();
			$e->time();
			$lastYear = strtotime('-1 year');
			$fixed = strtotime('2026-06-15');
			$shifted = strtotime('-1 year', currentTimestamp());
			$parsed = strtotime($date);
			$date = new DateTime();
			$date = new \DateTimeImmutable('now');
			$date = new DateTime('2026-06-15');
			$date = new DateTime($value);
			$date = new DateTime;
			$date = new DateTime("now", $timezone);
			$start = microtime(TRUE);
			$midnight = mktime(0, 0, 0);
			$day = mktime(0, 0, 0, 6, 15, 2026);
			$today = currentDate();
			$year = \date('Y');
			function time() {}
			Clock::set('2026-06-15');
			$this->setClock('2026-06-15');
			$sql = 'SELECT SYSDATE(), NOW()';
			$limit = strtotime('- '.$days.' day');
			$limit = strtotime('NOW - '.Setting::DELAY.' DAYS');
			$last = strtotime('last day of '.$month);
			$far = strtotime('+10000 seconds');
			$date = new DateTime('-'.$days.' days');
			$date = new DateTimeImmutable('@'.$timestamp);
			$date = new DateTime('@0');
			$date = date_create('2026-06-15');
			$date = date_create();
			$date = date_create('now', $timezone);
			ModuleModel::setTimestamp(NULL);
			$limit = strtotime(Setting::DELAY.' days ago');
			$next = strtotime(($date ?? 'now').' +1 day');
			$next = strtotime($date.' +1 day');
			PHP);

		$this->assertEquals([2, 4, 6, 10, 11, 14, 15, 17, 20, 25, 26, 28, 29, 33, 34, 36, 37], self::scan($tokens));
		$this->assertEquals([35], self::scanCall($tokens, 'ModuleModel', 'setTimestamp'));
		$this->assertEquals([24], self::scanSysdate($tokens));
		$this->assertEquals([22], self::scanCall($tokens, 'Clock', 'set'));
		$this->assertEquals([23], self::scanMethod($tokens, 'setClock'));

	}

	public function testScannerFindsHelper(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			$today = currentDate();
			$year = \nextYear() + currentYear();
			$now = CurrentTimestamp();
			$week = $e->currentWeek();
			$month = Setting::currentMonth();
			$time = $e?->currentTime();
			function currentDatetime(): string {}
			$today = \Clock::date();
			$name = 'currentDate()';
			$callback = currentDate(...);
			PHP);

		$this->assertEquals([2, 3, 3, 4, 11], self::scanHelper($tokens));
		$this->assertEquals([8], self::scanHelperDefinition($tokens));

	}

	public function testTestFilesAreLeftOut(): void {

		$this->assertTrue(self::isTest('accounting/journal/test/OperationAi.t.php'));
		$this->assertTrue(self::isTest('framework/dev/test/GetAllHeadersPolyfill.php'));
		$this->assertFalse(self::isTest('accounting/financialYear/lib/FinancialYear.l.php'));
		$this->assertFalse(self::isTest('base/contest/lib/Contest.l.php'));

	}

	public function testNoDirectRead(): void {
		$this->assertRatchet(self::RULE_DIRECT, self::DIRECT, 'heure lue sans passer par \\Clock, qu\'une horloge réglée par un test ne couvre pas, lire \\Clock::timestamp(), \\Clock::date() ou leurs voisines, ou \\Clock::machine() déclaré dans MACHINE avec sa raison pour une échéance jugée hors du processus');
	}

	public function testNoNewHelperCall(): void {

		$this->assertRatchet(self::RULE_HELPER, [], 'relais currentDate() ou voisin, retiré : lire l\'heure par \\Clock::date() et ses voisines');
		$this->assertRatchet(self::RULE_HELPER_DEFINITION, [], 'relais currentDate() ou voisin redéfini : lire l\'heure par \\Clock::date() et ses voisines');

	}

	public function testMachineIsDeclared(): void {
		$this->assertRatchet(self::RULE_MACHINE, self::MACHINE, 'Clock::machine() lit l\'heure qu\'aucun test ne fige : réservé à une boucle bornée par l\'heure, une échéance ou un horodatage vérifié hors du processus, déclaré avec sa raison, sinon lire \\Clock::timestamp()');
	}

	public function testNoSysdate(): void {
		$this->assertRatchet(self::RULE_SYSDATE, [], 'SYSDATE() ne suit pas l\'horloge posée sur la connexion, écrire NOW()');
	}

	public function testClockIsSetByTestOnly(): void {

		$this->assertRatchet(self::RULE_SET, self::SET, 'Clock::set() appelé ailleurs que par Test::setClock(), qui refuse le réglage hors d\'une méthode de test');
		$this->assertRatchet(self::RULE_SET_CLOCK, [], 'setClock() appelé hors d\'un fichier de test, à retirer, seul un test fige l\'heure, le produit la lit par \\Clock');
		$this->assertRatchet(self::RULE_TIMESTAMP, self::TIMESTAMP, 'ModuleModel::setTimestamp() appelé ailleurs que par Clock, dont set() porte le verrou');

	}

}
?>
