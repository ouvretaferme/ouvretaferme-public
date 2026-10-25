<?php
namespace media;

class UtilLib {

	/**
	 * Cleans old media that needs to be deleted
	 * either reference === 0
	 * either status === deleted
	 */
	public static function clean() {

		$cMedia = Media::model()
			->select('id', 'status', 'updatedAt', 'type', 'hash')
			->whereStatus(Media::DELETED)
			->getCollection();

		foreach($cMedia as $eMedia) {

			$url = $eMedia['type'].'/'.$eMedia['hash']; // TODO OVH : il ne manque pas l'extension ?

			MediaSetting::$mediaDriver->delete($url);
			Media::model()->delete($eMedia);

		}

	}

	/**
	 * Generates a 19-characters hash
	 *
	 * @param string $end Optional end (from 0 to 6 characters)
	 * @return string
	 */
	public static function generateHash(string $end = ''): string {

		$endLength = strlen($end);

		if($endLength > 6) {
			throw new \Exception('Parameter \'end\' is too long (max length expected: 6, received: '.$end.')');
		}

		// CSPRNG : le hash sert de nom de fichier public dans le storage, il ne doit pas être énumérable.
		// uniqid() est un microtime et mt_rand() n'est pas cryptographique.
		$hash = substr(bin2hex(random_bytes(10)), 0, 19 - $endLength);

		return $hash.$end; /* 19 caracters */
	}

	/**
	 * Détecte le vrai type d'un contenu binaire par signature.
	 * Retourne une constante IMAGETYPE_* pour les images, 'pdf' pour un PDF,
	 * ou NULL si le contenu ne correspond à aucun type reconnu.
	 */
	public static function detectFileType(string $content): int|string|null {

		if(str_starts_with($content, "\xFF\xD8\xFF")) {
			return IMAGETYPE_JPEG;
		}

		if(str_starts_with($content, "\x89PNG\r\n\x1a\n")) {
			return IMAGETYPE_PNG;
		}

		if(str_starts_with($content, '%PDF-')) {
			return 'pdf';
		}

		return NULL;

	}

}

?>
