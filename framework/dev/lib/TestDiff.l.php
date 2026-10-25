<?php
namespace dev;

/**
 * Une passe de sentinelles bornée à un diff (`dev/test file='*Sentinel*' diff=<…>`).
 *
 * Une sentinelle se joue quand elle peut lire un fichier du diff : un suffixe de ses `getFiles()` le
 * désigne, elle vit dans le package qui le porte, ou elle cite ce package par son espace de noms ou
 * par son chemin. Les deux dernières règles couvrent celles qui lisent par `file_get_contents()`,
 * `glob()` ou la réflexion, qu'aucun suffixe ne désigne : 87 des 160 sentinelles n'appellent pas
 * `getFiles()` (mesuré le 2026-10-25). Un diff qui touche le socle des tests les joue toutes, et
 * `AnchorSentinel` se joue toujours, seule sentinelle mesurée qui ait rougi sur un fichier absent du
 * diff (c6096124a).
 *
 * La borne ne voit ni la citation morte qu'un renommage laisse hors du diff, ni une sentinelle qui
 * lit un package sans le nommer : la passe complète reste celle de la porte de push.
 */
class TestDiffLib {

	const ALWAYS = ['framework/dev/test/AnchorSentinel.t.php'];

	/**
	 * Ce dont dépend le verdict de toutes les sentinelles : leur classe de base, leurs outils et le
	 * lanceur.
	 */
	const BASE = ['framework/core/Test.php', 'framework/dev/lib/', 'framework/dev/page/test.p.php', 'framework/dev/test/Sentinel.t.php'];

	/**
	 * Les chemins que désigne `diff=`, depuis la racine du dépôt, ou NULL quand une entrée ne désigne
	 * rien. Chaque entrée, séparée par une virgule, est un commit, un patch, un répertoire dont chaque
	 * patch est lu, ou un fichier du dépôt.
	 *
	 * Un commit plutôt que l'arbre : l'arbre est partagé, et ses fichiers modifiés sont ceux de toutes
	 * les fenêtres, si bien qu'une borne tirée de `git diff HEAD` les jouerait presque toutes.
	 *
	 * @return string[]|null
	 */
	public static function getPaths(string $diff): ?array {

		$paths = [];

		foreach(explode(',', $diff) as $entry) {

			$absolute = str_starts_with($entry, '/') ? $entry : LIME_DIRECTORY.'/'.$entry;

			if(is_dir($absolute)) {

				foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)) as $file) {

					if(in_array($file->getExtension(), ['patch', 'diff'], TRUE)) {
						$paths = array_merge($paths, self::readPatch(file_get_contents($file->getPathname())));
					}

				}

			} else if(is_file($absolute) and in_array(pathinfo($absolute, PATHINFO_EXTENSION), ['patch', 'diff'], TRUE)) {
				$paths = array_merge($paths, self::readPatch(file_get_contents($absolute)));
			} else if(is_file($absolute) or isset(SentinelLib::getTracked()[$entry])) {
				$paths[] = $entry;
			} else {

				$commit = SentinelLib::getGitPaths('show', '--name-only', '--format=', $entry.'^{commit}');

				if($commit === NULL) {
					return NULL;
				}

				$paths = array_merge($paths, $commit);

			}

		}

		$paths = array_values(array_unique($paths));
		sort($paths);

		return $paths;

	}

	/**
	 * Les chemins d'un patch unifié, des deux côtés, pour qu'un fichier renommé ou supprimé désigne
	 * aussi les sentinelles de son ancien emplacement.
	 *
	 * @return string[]
	 */
	public static function readPatch(string $patch): array {

		preg_match_all('#^(?:---|\+\+\+) [ab]/(\S+)#m', $patch, $matches);

		return $matches[1];

	}

	/**
	 * Les sentinelles que ces chemins concernent, chacune avec la règle qui la retient.
	 *
	 * @param string[] $paths
	 * @return array<string, string>
	 */
	public static function getSentinels(array $paths): array {

		$base = array_any($paths, fn(string $path) => array_any(self::BASE, fn(string $prefix) => str_starts_with($path, $prefix)));

		$packages = [];

		foreach($paths as $path) {

			$segments = explode('/', $path);

			if(count($segments) > 2) {
				$packages[$segments[0].'/'.$segments[1]] = $segments[1];
			}

		}

		$sentinels = [];

		// Un fichier non suivi se joue comme les autres, `dev/test` lisant le disque.
		foreach(SentinelLib::getFiles('.t.php', FALSE) as $file => $absolute) {

			if(fnmatch('*Sentinel*.t.php', basename($file)) === FALSE) {
				continue;
			}

			$reason = self::getReason($file, file_get_contents($absolute), $paths, $packages, $base);

			if($reason !== NULL) {
				$sentinels[$file] = $reason;
			}

		}

		return $sentinels;

	}

	/**
	 * @param array<string, string> $packages Le nom de chaque package touché, par son répertoire
	 */
	private static function getReason(string $file, string $code, array $paths, array $packages, bool $base): ?string {

		if(in_array($file, $paths, TRUE)) {
			return 'modifiée';
		}

		if($base) {
			return 'socle des tests';
		}

		if(in_array($file, self::ALWAYS, TRUE)) {
			return 'toujours jouée';
		}

		preg_match_all("#getFiles\('([^']*)'#", $code, $matches);

		foreach(array_unique($matches[1]) as $suffix) {

			if(array_any($paths, fn(string $path) => str_ends_with($path, $suffix))) {
				return 'lit '.($suffix === '' ? 'tout fichier' : $suffix);
			}

		}

		foreach($packages as $directory => $name) {

			if(str_starts_with($file, $directory.'/')) {
				return 'dans '.$directory;
			}

			if(str_contains($code, '\\'.$name.'\\') or str_contains($code, $directory.'/')) {
				return 'cite '.$directory;
			}

		}

		return NULL;

	}

}
?>
