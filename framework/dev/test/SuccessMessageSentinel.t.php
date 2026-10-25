<?php
namespace dev;

/**
 * Un `ReloadAction('<package>', '<clé>')` affiche le message que rend `<package>\AlertUi::getSuccess()`
 * pour cette clé. Le package nommé est seul consulté : une clé qui n'y figure pas rend `NULL`, et le
 * bandeau de succès affiche « null », sans erreur ni avertissement. Cas vu le 2026-09-29 sur la
 * désactivation d'une intervention, dont le message vivait dans `work` depuis que le renommage
 * `farm` → `structure` avait fait nommer `structure` par les pages de `work`.
 *
 * Une clé composée à l'exécution (`'Action::updated'.ucfirst($status)`) n'a pas de valeur à lire
 * ici : on vérifie alors que le package porte au moins une clé qui commence par la partie écrite.
 *
 * Lancement : dev/test package=dev file=SuccessMessageSentinel
 */
class SuccessMessageSentinelTest extends \Test {

	/**
	 * Les messages de succès encore sans texte, que ce test n'a pas à écrire à la place de leur
	 * package.
	 */
	const DECLARED = [
	];

	public function testReloadActionNamesAnExistingSuccess(): void {

		$found = [];

		foreach(SentinelLib::getFiles('.php') as $file => $path) {

			if(str_starts_with($file, 'framework/') or str_ends_with($file, '.t.php')) {
				continue;
			}

			foreach(file($path) as $number => $line) {

				preg_match_all("/ReloadAction\(\s*'([a-zA-Z]+)'\s*,\s*'([A-Za-z]+::[A-Za-z.]+)'\s*([).])/", $line, $matches, PREG_SET_ORDER);

				foreach($matches as [, $package, $key, $next]) {

					$class = '\\'.$package.'\AlertUi';

					if(class_exists($class) === FALSE) {
						$exists = FALSE;
					} else if($next === ')') {
						$exists = ($class::getSuccess($key) !== NULL);
					} else {
						$exists = str_contains(file_get_contents(new \ReflectionClass($class)->getFileName()), "'".$key);
					}

					if($exists === FALSE) {
						$found[] = $file.':'.($number + 1);
					}

				}

			}

		}

		['new' => $new, 'stale' => $stale] = SentinelLib::compare($found, self::DECLARED);

		$this->assertEquals([], $new, 'ReloadAction dont le package ne porte pas le message de succès, donc un bandeau « null » : '.implode(', ', $new));
		$this->assertEquals([], $stale, 'déclaration périmée, le message existe désormais : '.implode(', ', $stale));

	}

}
?>
