<?php
namespace dev;

/**
 * Une page qui refuse en ligne de commande sort en code 2, pas en 0 : ce qui ne lit que le code de
 * sortie, un `&&`, un cron, un script de migration, prendrait le refus pour une réussite (décidé par
 * le 2026-10-11, lots 1-i et 1-j du chantier tech).
 *
 * Deux écritures sortent en 0 en affichant un message, et se reconnaissent dans le code d'un `.p.php` :
 * `die("…")` ou `exit("…")`, dont PHP imprime l'argument avant de sortir en 0, et
 * `throw new LineAction("Error…")`, que la route imprime avant de terminer normalement. Un refus
 * s'écrit `echo "…";` puis `exit(2);`, la forme de `Route.php`, et un échec d'exécution (réseau,
 * base illisible) `exit(1)`.
 *
 * La troisième écriture, `echo "…";` puis `return;`, n'est pas refusée : la même forme porte un
 * refus et ce qui sort en 0 par décision, une simulation sans `write=1`, un « déjà semé », un « rien
 * à faire ». Seuls les refus connus qui la gardent sont nommés, dans `REFUSAL_IN_ZERO`, pour que
 * leur correction ne se perde pas.
 *
 * Ce qu'elle ne voit pas : un message qui n'est pas une chaîne écrite au premier niveau de
 * l'argument (`die($message)`, `die(sprintf(…))`, `die(s(…))`), et un `LineAction` dont le refus ne
 * commence pas par « Error », à la casse près (« Erreur », « error »).
 *
 * Lancement : dev/test package=dev file=CliRefusalSentinel
 */
class CliRefusalSentinelTest extends SentinelTest {

	/**
	 * Les `die("…")` et `exit("…")` qui restent, `'<fichier>:<message>' => '<raison>'`, le message
	 * écrit avec ses seuls morceaux littéraux, une expression valant « … ». Un même message répété
	 * dans un fichier prend « #2 », « #3 » à partir de sa deuxième occurrence, pour qu'un cas neuf ne
	 * se fonde pas dans un cas déclaré. L'ordre suit le fichier : un doublon écrit au-dessus du cas
	 * déclaré prend sa clé, et l'échec nomme alors la ligne du cas déclaré.
	 */
	const DIE_MESSAGE = [];

	/**
	 * Les refus écrits `echo "…";` puis `return;`, donc en 0, que les lots 1-i et 1-j n'ont pas
	 * corrigés parce qu'une autre session écrivait la page, `'<fichier>:<message>' => '<raison>'`.
	 *
	 * Une entrée ne se vérifie que sur un fichier que git suit : un fichier hors du suivi n'est pas
	 * chez ceux qui tirent, où l'entrée serait sinon périmée.
	 */
	const REFUSAL_IN_ZERO = [
		'accounting/preaccounting/page/configure/ongletsRecetteAi.p.php:Compte introuvable : user=<id>' => 'compte de recette introuvable, refus à faire sortir en 2',
		'commercialisation/amap/page/configure/distributionsRecette.p.php:compte … introuvable : passer user=<id du compte qui recette>' => 'compte de recette introuvable, refus à faire sortir en 2',
		'ouvretaferme/learning/page/configure/designRecette.p.php:Compte introuvable : user=<id>' => 'compte de recette introuvable, refus à faire sortir en 2',
		'ouvretaferme/learning/page/configure/partageQuizRecette.p.php:Compte introuvable : user=<id>' => 'compte de recette introuvable, refus à faire sortir en 2',
	];

	/**
	 * Les refus en 0 relevés dans les fichiers de `REFUSAL_IN_ZERO`, par clé.
	 *
	 * @var array<string, int>
	 */
	private array $refusalInZero = [];

	public function init() {

		$files = array_unique(array_map(fn(string $key) => strtok($key, ':'), array_keys(self::REFUSAL_IN_ZERO)));

		foreach(SentinelLib::getFiles('.p.php') as $file => $path) {

			$tokens = SentinelLib::getTokens(file_get_contents($path));

			foreach(self::findDieMessage($tokens) as $message => $line) {
				$this->addFoundKey('dieMessage', $file, $message, $line);
			}

			foreach(self::findLineActionError($tokens) as $message => $line) {
				$this->addFoundKey('lineActionError', $file, $message, $line);
			}

			if(in_array($file, $files, TRUE)) {

				foreach(self::findEchoReturn($tokens) as $message => $line) {
					$this->refusalInZero[$file.':'.$message] = $line;
				}

			}

		}

	}

	public function testNoDieMessage(): void {

		$this->assertDeclared('dieMessage', self::DIE_MESSAGE, 'die() ou exit() avec un message, qui sort en 0 : écrire le même texte par echo, puis exit(2) pour un refus, exit(1) pour un échec, ou return pour une réponse ; ne déclarer dans DIE_MESSAGE, avec sa raison, que ce qui ne peut pas encore prendre l\'une de ces formes');

	}

	public function testNoLineActionError(): void {

		$this->assertDeclared('lineActionError', [], 'throw new LineAction("Error…"), qui sort en 0 : écrire le même texte par echo, suivi d\'un saut de ligne, puis exit(2)');

	}

	/**
	 * Une entrée qui ne désigne plus rien est un refus corrigé, ou un message réécrit : dans les
	 * deux cas, c'est la déclaration qui se corrige.
	 */
	public function testRefusalInZeroIsStillThere(): void {

		$tracked = SentinelLib::getTracked();
		$stale = [];

		foreach(self::REFUSAL_IN_ZERO as $key => $reason) {

			$path = strtok($key, ':');

			if($tracked !== NULL and isset($tracked[$path]) === FALSE) {
				continue;
			}

			if(array_key_exists($key, $this->refusalInZero) === FALSE) {
				$stale[] = $key;
			}

		}

		$this->assertEquals([], $stale, 'refus déclaré dans REFUSAL_IN_ZERO qui ne s\'écrit plus echo puis return : s\'il sort désormais en 2, retirer sa ligne ; si son message a changé, reporter le nouveau dans la clé : '.implode(' · ', $stale));
		$this->assertEquals([], array_keys(self::REFUSAL_IN_ZERO, '', TRUE), 'refus déclaré dans REFUSAL_IN_ZERO sans raison : '.implode(' · ', array_keys(self::REFUSAL_IN_ZERO, '', TRUE)));

	}

	/**
	 * Les `die(…)` et `exit(…)` dont l'argument contient une chaîne, par message.
	 *
	 * @return array<string, int>
	 */
	public static function findDieMessage(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, , $line]) {

			if($type !== T_EXIT or ($tokens[$i + 1][0] ?? NULL) !== '(') {
				continue;
			}

			$closing = SentinelLib::getClosing($tokens, $i + 1);
			$message = self::getMessage($tokens, $i + 2, $closing);

			if($message !== NULL) {
				self::addMessage($found, $message, $line);
			}

		}

		return $found;

	}

	/**
	 * Les `new LineAction(…)` dont le message commence par « Error », par message.
	 *
	 * @return array<string, int>
	 */
	public static function findLineActionError(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, , $line]) {

			if(
				$type !== T_NEW or
				ltrim($tokens[$i + 1][1] ?? '', '\\') !== 'LineAction' or
				($tokens[$i + 2][0] ?? NULL) !== '('
			) {
				continue;
			}

			$closing = SentinelLib::getClosing($tokens, $i + 2);
			$message = self::getMessage($tokens, $i + 3, $closing);

			if($message !== NULL and str_starts_with($message, 'Error')) {
				self::addMessage($found, $message, $line);
			}

		}

		return $found;

	}

	/**
	 * Les blocs réduits à `echo …;` puis `return;`, par message.
	 *
	 * @return array<string, int>
	 */
	public static function findEchoReturn(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, , $line]) {

			if($type !== T_ECHO or ($tokens[$i - 1][0] ?? NULL) !== '{') {
				continue;
			}

			$end = $i + 1;

			while(isset($tokens[$end]) and $tokens[$end][0] !== ';') {
				$end++;
			}

			if(
				($tokens[$end + 1][0] ?? NULL) !== T_RETURN or
				($tokens[$end + 2][0] ?? NULL) !== ';' or
				($tokens[$end + 3][0] ?? NULL) !== '}'
			) {
				continue;
			}

			$message = self::getMessage($tokens, $i + 1, $end);

			if($message !== NULL) {
				self::addMessage($found, $message, $line);
			}

		}

		return $found;

	}

	/**
	 * @param array<string, int> $found
	 */
	private static function addMessage(array &$found, string $message, int $line): void {

		$key = $message;
		$occurrence = 1;

		while(array_key_exists($key, $found)) {
			$occurrence++;
			$key = $message.' #'.$occurrence;
		}

		$found[$key] = $line;

	}

	/**
	 * Le message porté par les jetons `[$from, $to[`, ou NULL s'ils ne contiennent aucune chaîne.
	 *
	 * Seuls les morceaux littéraux de la concaténation comptent, une expression ou une variable
	 * interpolée valant « … » : la clé reste celle qu'on lit dans le source, et ne bouge pas quand une
	 * ligne est insérée au-dessus. Les sauts de ligne et les espaces de bord ne la changent pas.
	 */
	private static function getMessage(array $tokens, int $from, int $to): ?string {

		$message = '';
		$literal = FALSE;
		$depth = 0;

		for($i = $from; $i < $to; $i++) {

			[$type, $text] = $tokens[$i];

			if(in_array($type, ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE)) {
				$depth++;
			} else if(in_array($type, [')', ']', '}'], TRUE)) {
				$depth--;
			}

			if($depth === 0 and $type === T_CONSTANT_ENCAPSED_STRING) {
				$message .= match($text[0]) {
					'\'' => str_replace('\\\'', '\'', substr($text, 1, -1)),
					'"' => str_replace('\\"', '"', substr($text, 1, -1)),
				};
				$literal = TRUE;
			} else if($depth === 0 and $type === T_ENCAPSED_AND_WHITESPACE) {
				$message .= $text;
				$literal = TRUE;
			} else if(($depth === 0 and in_array($type, ['.', '"', T_START_HEREDOC, T_END_HEREDOC], TRUE)) === FALSE and str_ends_with($message, '…') === FALSE) {
				$message .= '…';
			}

		}

		if($literal === FALSE) {
			return NULL;
		}

		return trim(preg_replace('/\s+/u', ' ', str_replace('\n', ' ', $message)));

	}

	/** Chaque détecteur trouve sa forme, avec sa clé, et laisse passer ses voisines légitimes. */
	public function testDetectors(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			new Page()
				->cli('index', function($data) {
					if(GET('a') === '') {
						die("Usage : a=<id>\n");
					}
					if(GET('b') === '') {
						exit('Structure #'.GET('b').' introuvable');
					}
					if(GET('c') === '') {
						throw new \LineAction("Error: Argument c=$c is missing");
					}
					if(GET('d') === '') {
						throw new LineAction('Error: d');
					}
					if(GET('e') === '') {
						echo "Compte introuvable : user=<id>\n";
						return;
					}
					if(GET('f') === '') {
						echo "Refus\n";
						exit(2);
					}
					if(GET('g') === '') {
						echo "Simulation, passer write=1\n";
						echo "Rien d'autre\n";
						return;
					}
					if(GET('h') === '') {
						die("Usage : a=<id>\n");
					}
					exit(<<<TXT
						Usage : heredoc
						TXT);
					throw new LineAction('Init '.$module.': OK');
					throw new \LineAction('');
					exit($code);
					exit(1);
					die;
					exit();
				});
			PHP);

		$this->assertEquals(['Usage : a=<id>' => 5, 'Structure #… introuvable' => 8, 'Usage : a=<id> #2' => 30, 'Usage : heredoc' => 32], self::findDieMessage($tokens), 'die() et exit() avec message, et eux seuls, sont trouvés, un message répété sous une clé à lui');
		$this->assertEquals(['Error: Argument c=… is missing' => 11, 'Error: d' => 14], self::findLineActionError($tokens), 'LineAction("Error…"), qualifié ou non, et lui seul, est trouvé');
		$this->assertEquals(['Compte introuvable : user=<id>' => 17], self::findEchoReturn($tokens), 'seul un bloc réduit à echo puis return est trouvé');

	}

}
?>
