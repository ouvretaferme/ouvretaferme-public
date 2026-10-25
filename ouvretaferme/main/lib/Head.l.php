<?php
namespace main;

/**
 * Le commit que porte le dépôt de cette installation, lu dans les fichiers de git.
 *
 * Aucune commande n'est lancée : la lecture sert une requête web, et un `git` exécuté depuis PHP
 * dépendrait du compte du serveur web, de son PATH et de la propriété du dépôt.
 */
class HeadLib {

	const BRANCH = 'refs/heads/main';
	const UPSTREAM = 'refs/remotes/origin/main';

	/**
	 * Le commit de travail et celui de l'amont tel qu'il a été tiré la dernière fois. Les deux
	 * diffèrent pendant un `git pull --rebase` et tant que l'installation porte un commit à elle :
	 * c'est l'amont qui dit alors ce qui a été tiré.
	 */
	public static function get(string $directory = LIME_DIRECTORY.'/.git'): array {

		return [
			'head' => self::getHead($directory),
			'upstream' => self::getReference($directory, self::UPSTREAM),
		];

	}

	private static function getHead(string $directory): ?string {

		if(is_file($directory.'/HEAD') === FALSE) {
			return NULL;
		}

		$head = trim(file_get_contents($directory.'/HEAD'));

		// Pendant un rebase, HEAD ne nomme plus une branche, il porte le commit lui-même.
		if(str_starts_with($head, 'ref: ') === FALSE) {
			return self::filter($head);
		}

		return self::getReference($directory, substr($head, 5));

	}

	/**
	 * Une référence vit dans son fichier, ou dans `packed-refs` une fois que git l'y a rangée.
	 */
	private static function getReference(string $directory, string $reference): ?string {

		if(is_file($directory.'/'.$reference)) {
			return self::filter(trim(file_get_contents($directory.'/'.$reference)));
		}

		if(is_file($directory.'/packed-refs') === FALSE) {
			return NULL;
		}

		foreach(file($directory.'/packed-refs', FILE_IGNORE_NEW_LINES) as $line) {

			if(str_ends_with($line, ' '.$reference)) {
				return self::filter(substr($line, 0, strpos($line, ' ')));
			}

		}

		return NULL;

	}

	private static function filter(string $commit): ?string {
		return (preg_match('/^[0-9a-f]{40}$/', $commit) === 1) ? $commit : NULL;
	}

}
?>
