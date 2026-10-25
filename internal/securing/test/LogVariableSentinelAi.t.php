<?php
namespace securing;

/**
 * Une phrase de l'historique ne nomme-t-elle que des variables qu'elle fournit ?
 *
 * `LogUi::getComment()` rend une phrase par événement à partir d'une charge JSON sans schéma : rien
 * ne relie le `{register}` du texte au `'register' =>` de ses arguments, et le jour où l'un des deux
 * est renommé, rien ne le dit. `Language::applyVariables()` (`framework/core/Language.php:319`)
 * remplace une variable non fournie par **son propre nom, sans accolades** : « Clôture du cahier de
 * caisse registre au 31/07/2026 » part en production sans exception et sans trace. `LogAi.t.php`,
 * qui rend pourtant chaque événement, ne peut pas le voir — mesuré : cette faute injectée à la main
 * y donne 425 assertions passées, 0 échec.
 *
 * D'où cet inventaire, qui lit le fichier et non son exécution. Il compare dans les deux sens : une
 * variable nommée par le texte que les arguments ne portent pas, et un argument que plus aucun texte
 * ne nomme. Un appel dont il ne sait pas lire la forme échoue aussi, plutôt que de passer au vert
 * en silence.
 *
 * Trois règles du cadriciel qu'il applique, et qu'un appel ne montre pas : `p()` injecte toujours
 * `value`, qui vaut le nombre (`Language.php:192`) · `s()` dont l'argument n'est pas un tableau le
 * range sous `value` (`Language.php:162`) · les variables posées par `L::setVariables()` sont
 * disponibles partout (`siteName`, `ouvretaferme/Lime.s.php`).
 *
 * Seule la forme `{xxx}` est vérifiée. `applyVariables()` traite aussi `<xxx>`, mais les phrases de
 * l'historique portent du vrai HTML (`<b>`, `<br/>`) que `getVariable()` laisse passer tel quel :
 * la forme est ambiguë, la sentinelle ne la juge pas.
 *
 * La portée est ce seul fichier, et non tout le dépôt : ailleurs, un élément entier passe souvent
 * en arguments (`s("{width} cm", $eBed)`, `production/map/ui/Bed.u.php:157`), forme dont les clés ne
 * se lisent pas sans exécuter. 148 appels du dépôt sont dans ce cas, mesurés le 2026-09-16 ; aucun
 * ici, où chaque phrase écrit ses clés.
 *
 * Lancement : dev/test package=securing file=LogVariableSentinelAi
 */
class LogVariableSentinelAiTest extends \Test {

	/**
	 * Les méthodes d'aide de `LogUi` qui rendent un **jeu de clés** et non une chaîne : leurs clés
	 * entrent dans les arguments de la phrase qui les appelle, sans y être écrites. Toutes les
	 * autres rendent une chaîne, qui ne fournit rien par elle-même.
	 */
	const HELPERS = [
		'period' => ['from', 'to'],
	];

	private const FILE = 'internal/securing/ui/Log.u.php';

	/**
	 * La valeur d'une chaîne littérale, ou NULL si le jeton n'en est pas une.
	 */
	private function literal(array $token): ?string {

		if($token[0] !== T_CONSTANT_ENCAPSED_STRING) {
			return NULL;
		}

		$body = substr($token[1], 1, -1);

		if($token[1][0] === "'") {
			return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
		}

		return stripcslashes($body);

	}

	private function variables(string $text): array {

		preg_match_all('/\{([a-z0-9]+?)\}/si', $text, $matches);

		return array_values(array_unique($matches[1]));

	}

	/**
	 * Les arguments de premier niveau d'un appel, chacun rendu en liste de jetons.
	 */
	private function splitArguments(array $tokens): array {

		$arguments = [];
		$current = [];
		$depth = 0;

		foreach($tokens as $token) {

			if(in_array($token[0], ['(', '[', '{'], TRUE)) {
				$depth++;
			} else if(in_array($token[0], [')', ']', '}'], TRUE)) {
				$depth--;
			}

			if($token[0] === ',' and $depth === 0) {
				$arguments[] = $current;
				$current = [];
				continue;
			}

			$current[] = $token;

		}

		if($current !== []) {
			$arguments[] = $current;
		}

		return $arguments;

	}

	/**
	 * Les clés qu'un argument fournit à la phrase : un tableau littéral donne les siennes, une somme
	 * de premier niveau réunit ses deux membres, un appel d'aide déclaré rend son jeu de clés, et
	 * tout le reste est une valeur que `L::s()` range sous `value`.
	 *
	 * `$unreadable` passe à TRUE sur une forme dont les clés ne se lisent pas — une clé calculée :
	 * l'appel est alors signalé au lieu d'être jugé sur une lecture partielle.
	 */
	private function suppliedKeys(array $tokens, bool &$unreadable): array {

		if($tokens === []) {
			return [];
		}

		$depth = 0;

		foreach($tokens as $index => $token) {

			if(in_array($token[0], ['(', '[', '{'], TRUE)) {
				$depth++;
			} else if(in_array($token[0], [')', ']', '}'], TRUE)) {
				$depth--;
			} else if($token[0] === '+' and $depth === 0) {
				return array_merge(
					$this->suppliedKeys(array_slice($tokens, 0, $index), $unreadable),
					$this->suppliedKeys(array_slice($tokens, $index + 1), $unreadable)
				);
			}

		}

		if($tokens[0][0] === '[' and end($tokens)[0] === ']') {

			$keys = [];
			$depth = 0;

			foreach($tokens as $index => $token) {

				if(in_array($token[0], ['(', '[', '{'], TRUE)) {
					$depth++;
				} else if(in_array($token[0], [')', ']', '}'], TRUE)) {
					$depth--;
				} else if($token[0] === T_DOUBLE_ARROW and $depth === 1) {

					$key = $this->literal($tokens[$index - 1]);

					if($key === NULL) {
						$unreadable = TRUE;
					} else {
						$keys[] = $key;
					}

				}

			}

			return $keys;

		}

		foreach($tokens as $index => $token) {

			if(
				$token[0] === T_OBJECT_OPERATOR
				and array_key_exists($tokens[$index + 1][1] ?? '', self::HELPERS)
			) {
				return self::HELPERS[$tokens[$index + 1][1]];
			}

		}

		return ['value'];

	}

	/**
	 * Les écarts de chaque appel à `s()` / `p()` du code, un enregistrement par appel fautif :
	 * `missing` les variables nommées sans être fournies, `extra` les arguments que plus aucun texte
	 * ne nomme, `unreadable` la forme qui ne se lit pas.
	 */
	public function scan(string $code): array {

		$tokens = \dev\SentinelLib::getTokens($code);
		$found = [];

		foreach($tokens as $index => $token) {

			if(
				$token[0] !== T_STRING
				or in_array($token[1], ['s', 'p'], TRUE) === FALSE
				or ($tokens[$index + 1][0] ?? NULL) !== '('
			) {
				continue;
			}

			$previousType = $tokens[$index - 1][0] ?? NULL;

			if(in_array($previousType, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)) {
				continue;
			}

			$closing = \dev\SentinelLib::getClosing($tokens, $index + 1);
			$arguments = $this->splitArguments(array_slice($tokens, $index + 2, $closing - $index - 2));

			$unreadable = FALSE;

			// `p()` porte deux textes et injecte `value` lui-même ; `s()` n'en porte qu'un.
			if($token[1] === 'p') {

				$positions = [0, 1];
				$supplied = isset($arguments[3]) ? $this->suppliedKeys($arguments[3], $unreadable) : [];
				$automatic = ['value'];

			} else {

				$positions = [0];
				$supplied = isset($arguments[1]) ? $this->suppliedKeys($arguments[1], $unreadable) : [];
				$automatic = [];

			}

			$named = [];

			foreach($positions as $position) {

				$text = (count($arguments[$position] ?? []) === 1) ? $this->literal($arguments[$position][0]) : NULL;

				if($text === NULL) {
					$unreadable = TRUE;
				} else {
					$named = array_merge($named, $this->variables($text));
				}

			}

			$named = array_values(array_unique($named));
			$missing = [];

			foreach(array_diff($named, $supplied, $automatic) as $variable) {

				if(\L::getVariable($variable) === NULL) {
					$missing[] = $variable;
				}

			}

			$extra = array_values(array_diff($supplied, $named));

			if($missing !== [] or $extra !== [] or $unreadable) {
				$found[] = [
					'line' => $token[2],
					'missing' => array_values($missing),
					'extra' => $extra,
					'unreadable' => $unreadable,
				];
			}

		}

		return $found;

	}

	/**
	 * L'analyseur sait-il trouver ? Un échantillon qui porte les quatre cas, et rien qu'eux : les
	 * appels justes au-dessus ne doivent rien lever, sans quoi le vert du test réel ne dirait rien.
	 */
	public function testScannerFindsEachGap(): void {

		$code = <<<'CODE'
		<?php
		$a = s("Rien à signaler");
		$b = s("Clôture du cahier {register} au {date}", ['register' => $x, 'date' => $y]);
		$c = s("Exercice {value}", $this->name($z));
		$d = s("Période du {from} au {to}", $this->period($data));
		$e = s("Archive {id} du {from} au {to}", ['id' => $x] + $this->period($data));
		$f = p("{value} opération du cahier {register}", "{value} opérations du cahier {register}", $n, ['register' => $x]);
		$g = s("Bienvenue sur {siteName}");
		$h = s("Clôture du cahier {registre} au {date}", ['register' => $x, 'date' => $y]);
		$i = s("Suppression du cahier", ['register' => $x]);
		$j = s("Journal ".$name." {value}", $x);
		CODE;

		$found = $this->scan($code);

		$this->assertEquals([9, 10, 11], array_column($found, 'line'), 'trois appels fautifs trouvés, et seulement eux');
		$this->assertEquals(['registre'], $found[0]['missing'], 'la variable nommée et non fournie est nommée');
		$this->assertEquals(['register'], $found[0]['extra'], 'l\'argument que le texte ne nomme plus est nommé');
		$this->assertEquals(['register'], $found[1]['extra'], 'l\'argument seul, sans variable en face');
		$this->assertTrue($found[2]['unreadable'], 'le texte concaténé est signalé comme illisible');

	}

	/**
	 * Les aides déclarées existent-elles encore ? Une entrée de HELPERS devenue fausse ferait lire
	 * des clés que la méthode ne rend plus, ou masquerait une phrase réellement incomplète.
	 */
	public function testDeclaredHelpersStillExist(): void {

		$methods = [];

		foreach(new \ReflectionClass(LogUi::class)->getMethods() as $method) {
			$methods[] = $method->getName();
		}

		$unknown = array_values(array_diff(array_keys(self::HELPERS), $methods));

		$this->assertEquals([], $unknown, 'aides déclarées que LogUi ne porte plus : '.implode(', ', $unknown));

	}

	public function testEverySentenceSuppliesWhatItNames(): void {

		$found = $this->scan(file_get_contents(\dev\SentinelLib::getRoot().'/'.self::FILE));

		$messages = [];

		foreach($found as $gap) {

			$details = [];

			if($gap['missing'] !== []) {
				$details[] = 'nommées sans être fournies : '.implode(', ', $gap['missing']);
			}

			if($gap['extra'] !== []) {
				$details[] = 'fournies sans être nommées : '.implode(', ', $gap['extra']);
			}

			if($gap['unreadable']) {
				$details[] = 'forme que la sentinelle ne sait pas lire, à déclarer';
			}

			$messages[] = self::FILE.':'.$gap['line'].' ('.implode(' ; ', $details).')';

		}

		$this->assertEquals([], $messages, 'phrases de l\'historique à corriger : '.implode(' | ', $messages));

	}

}
?>
