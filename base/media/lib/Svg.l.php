<?php
namespace media;

/**
 * Rastérisation des SVG déposés à l'upload
 *
 * Un SVG n'est jamais stocké : il est rendu en PNG à l'entrée puis suit le pipeline
 * habituel, comme le WebP.
 *
 * Le rendu passe par librsvg et surtout pas par Imagick, qui suit les ressources externes
 * d'un SVG (`<image xlink:href>`, en http comme en file) et les incruste dans l'image
 * produite — mesuré.
 */
class SvgLib {

	const BINARY = '/usr/bin/rsvg-convert';

	// Un SVG est du texte : au-delà, on ne rend pas
	const MAX_SIZE = 2 * 1024 * 1024;

	// Plafond de rastérisation, indépendant de l'imageMaxLength du type : synthétiser un
	// PNG de 5000 px à partir d'un vecteur (cas de 'editor') est disproportionné
	const MAX_RASTER = 2000;

	// Longueur examinée pour la détection, bornée pour ne pas relire tout le fichier
	const SNIFF_LENGTH = 4096;

	/**
	 * Détection seule, sans plafond de taille : un SVG trop gros doit être refusé par
	 * rasterize(), surtout pas requalifié en « pas un SVG », ce qui le confierait à Imagick
	 */
	public static function isSvg(string $file): bool {

		if(is_file($file) === FALSE) {
			return FALSE;
		}

		$prefix = file_get_contents($file, length: self::SNIFF_LENGTH);

		if($prefix === FALSE) {
			return FALSE;
		}

		// Un binaire ne peut pas être du SVG : l'écarter avant de chercher la balise racine
		if(preg_match('/[\x00-\x08\x0E-\x1F]/', $prefix) === 1) {
			return FALSE;
		}

		return (preg_match('/<svg[\s>]/i', $prefix) === 1);

	}

	/**
	 * Rend le SVG en PNG et renvoie le chemin du fichier produit, NULL si le rendu échoue
	 *
	 * Le PNG est temporaire : c'est à l'appelant de le supprimer.
	 */
	public static function rasterize(string $file, int $max): ?string {

		if(filesize($file) > self::MAX_SIZE) {
			return NULL;
		}

		$max = min($max, self::MAX_RASTER);

		// librsvg refuse le réseau, mais lit les fichiers du dossier du SVG et de ses
		// sous-dossiers (mesuré). On l'isole seul dans un dossier vide : il ne reste alors
		// plus rien à lire, y compris les uploads concurrents du dossier temporaire.
		$directory = sys_get_temp_dir().'/svg-source-'.bin2hex(random_bytes(8));

		if(mkdir($directory, 0700) === FALSE) {
			return NULL;
		}

		$source = $directory.'/source.svg';
		$destination = tempnam(sys_get_temp_dir(), 'svg-raster-');

		copy($file, $source);

		exec(
			self::BINARY
				.' --width '.(int)$max
				.' --height '.(int)$max
				.' --keep-aspect-ratio'
				.' --output '.escapeshellarg($destination)
				.' '.escapeshellarg($source)
				.' 2>&1',
			$output,
			$code
		);

		unlink($source);
		rmdir($directory);

		if($code !== 0) {

			unlink($destination);

			return NULL;

		}

		return $destination;

	}

}
?>
