<?php
namespace design;

class DesignLib {

	/**
	 * Liste les identifiants des <symbol> d'un sprite SVG, triés par ordre alphabétique
	 */
	public static function getSvgSymbols(string $package, string $file): array {

		$path = \Package::getPath($package).'/static/image/'.$file;

		if(is_file($path) === FALSE) {
			return [];
		}

		preg_match_all('/<symbol[^>]+id="([^"]+)"/', file_get_contents($path), $matches);

		$symbols = array_unique($matches[1]);

		sort($symbols);

		return $symbols;

	}

	/**
	 * Liste les fichiers SVG d'un dossier d'images, triés par ordre alphabétique
	 */
	public static function getSvgFiles(string $package, string $folder): array {

		$path = \Package::getPath($package).'/static/image/'.$folder;

		if(is_dir($path) === FALSE) {
			return [];
		}

		$files = array_map(fn($file) => basename($file, '.svg'), glob($path.'/*.svg'));

		sort($files);

		return $files;

	}

}
?>
