<?php
namespace dev;

/**
 * Un observateur `.o.php` porte le nom du package qu'il écoute : `Dev.o.php` reçoit les événements
 * du package `dev` (`PackageLib::getObservers()`, `lcfirst()` du nom de fichier). Un nom qui ne
 * désigne aucun package n'est jamais appelé, sans erreur ni avertissement : c'est le sort d'un
 * `ShopAi.o.php`.
 *
 * Lancement : dev/test package=dev file=ObserverNameSentinel
 */
class ObserverNameSentinelTest extends \Test {

	/**
	 * Les écouteurs qui ne sont pas des packages, et que le cadriciel appelle quand même.
	 */
	const LISTENED = [
		// Les classes du cadriciel sans espace de noms (Route) émettent sous ce nom (Notifiable::notify()).
		'lime',
	];

	public function testObserverNamesAPackage(): void {

		$packages = array_keys(\Package::getList());
		$found = [];

		foreach(SentinelLib::getFiles('.o.php') as $file => $path) {

			$listened = lcfirst(strstr(basename($file), '.', TRUE));

			if(in_array($listened, $packages, TRUE) === FALSE and in_array($listened, self::LISTENED, TRUE) === FALSE) {
				$found[] = $file.' (écoute « '.$listened.' »)';
			}

		}

		$this->assertEquals([], $found, 'observateur dont le nom ne désigne aucun package, donc jamais appelé : '.implode(', ', $found));

	}

}
