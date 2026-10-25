<?php
namespace toolbox;

/**
 * La cascade des prix d'une structure (`CompositionLib::getPrices()`) tourne une fois par source de
 * plan, dans `CompositionLib::getSourceByPlan()`, qui la range sous `pricesPlan`. Chaque lecteur la
 * reprend de là : l'écran, le menu d'une espèce, chaque geste qui compose, recompose ou chiffre une
 * ligne (chantier Performances, P2 et P2b). Relancée à côté d'une source déjà lue, elle refait ses
 * requêtes (les sources de la structure, huit requêtes, P17) pour rendre la même chose,
 * rien ne s'écrivant entre les deux.
 *
 * La sentinelle lit chaque appel du code de production du package (`lib`, `page`, `ui`, `view`,
 * `module`) avec l'analyseur lexical de PHP et le compare à la déclaration, dans les deux sens. Les
 * tests en sont exclus : ceux de la cascade l'appellent pour elle-même.
 *
 * Un appel nouveau ne se déclare que si une écriture passe entre la source et les prix, qui
 * changeraient alors : la déclaration dit laquelle. Sinon, lire `$source['pricesPlan']`.
 *
 * Ce qu'elle ne voit pas : un appel par une chaîne (`call_user_func`) ou par un alias de classe.
 *
 * Lancement : dev/test package=toolbox file=PricesOnceSentinel
 */
class PricesOnceSentinelTest extends \Test {

	/**
	 * `[fichier depuis le package => [méthode englobante => nombre d'appels]]`, une fermeture de page
	 * sous `{closure}`.
	 */
	private const CALLERS = [
		// La seule lecture : la source du plan la range sous `pricesPlan`.
		'lib/Composition.l.php' => ['getSourceByPlan' => 1],
	];

	public function testCallers(): void {

		$found = [];

		foreach(['lib', 'page', 'ui', 'view', 'module'] as $directory) {

			foreach(glob(__DIR__.'/../'.$directory.'/*.php') as $path) {

				$file = $directory.'/'.basename($path);

				foreach($this->getCallers(file_get_contents($path), $file) as $function) {
					$found[$file][$function] = ($found[$file][$function] ?? 0) + 1;
				}

			}

		}

		$appeared = [];
		$gone = [];

		foreach($found as $file => $functions) {
			foreach($functions as $function => $count) {
				if($count !== (self::CALLERS[$file][$function] ?? 0)) {
					$appeared[] = $file.' '.$function.' ('.$count.' appel(s), '.(self::CALLERS[$file][$function] ?? 0).' déclaré(s))';
				}
			}
		}

		foreach(self::CALLERS as $file => $functions) {
			foreach($functions as $function => $count) {
				if(isset($found[$file][$function]) === FALSE) {
					$gone[] = $file.' '.$function;
				}
			}
		}

		$this->assertEquals([], $appeared, 'la cascade des prix est relancée hors de getSourceByPlan() ; c\'est le code qui se corrige, en lisant $source[\'pricesPlan\'], sauf si une écriture passe entre la source et les prix, à dire dans la déclaration : '.implode(', ', $appeared));
		$this->assertEquals([], $gone, 'la déclaration nomme un appel qui n\'existe plus, elle se corrige : '.implode(', ', $gone));

	}

	/**
	 * Les méthodes qui appellent `CompositionLib::getPrices()` dans `$code`, une entrée par appel :
	 * `CompositionLib::getPrices(`, `\toolbox\CompositionLib::getPrices(`, et `self::getPrices(`
	 * dans `CompositionLib` même. `CompositionUi::getPrices()`, qui écrit des prix, n'en est pas.
	 */
	private function getCallers(string $code, string $file): array {

		$tokens = array_values(array_filter(\PhpToken::tokenize($code), fn(\PhpToken $token) => $token->isIgnorable() === FALSE));

		$callers = [];
		$function = '{closure}';

		foreach($tokens as $position => $token) {

			if($token->is(T_FUNCTION)) {
				$function = $tokens[$position + 1]->is(T_STRING) ? $tokens[$position + 1]->text : $function;
				continue;
			}

			if(
				$token->text !== 'getPrices' or
				($tokens[$position + 1]->text ?? NULL) !== '(' or
				($tokens[$position - 1]->text ?? NULL) !== '::'
			) {
				continue;
			}

			$class = $tokens[$position - 2]->text;

			if(
				in_array($class, ['CompositionLib', '\\toolbox\\CompositionLib', 'toolbox\\CompositionLib'], TRUE) or
				($class === 'self' and $file === 'lib/Composition.l.php')
			) {
				$callers[] = $function;
			}

		}

		return $callers;

	}

}
?>
