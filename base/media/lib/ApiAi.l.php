<?php
namespace media;

/**
 * Le dépôt d'une image par l'API : le fichier arrive en base64 dans un corps JSON, l'API n'ayant pas
 * d'autre transport (\Api::load() lit php://input et refuse tout ce qui n'est pas du JSON).
 *
 * Rien du pipeline d'images n'est réécrit ici : ce Lib décode, borne et confie le fichier à
 * MediaLib::putFile(), qui fait déjà le reste (contrôle du type réel, Imagick, conversion vers le
 * format autorisé par le type, rastérisation d'un SVG, enregistrement du média et du champ).
 */
class ApiAiLib {

	/**
	 * Pose une image sur un élément, en remplaçant celle qui s'y trouve.
	 *
	 * Le format est établi en décodant le fichier. Ni un nom, ni une extension, ni un en-tête
	 * annoncé par l'appelant n'entrent dans la décision : putFile() rend FALSE quand le contenu n'est
	 * ni un SVG ni une image d'un format admis, ce qui écarte un PDF ou un exécutable présenté comme une image.
	 */
	public static function put(string $type, \Element $eElement, string $content): void {

		$file = self::decode($content);

		try {
			self::putFile($type, $eElement, $file);
		} finally {
			unlink($file);
		}

	}

	/**
	 * Pose une image depuis un fichier local, quelle que soit la façon dont il est arrivé : c'est ce que
	 * `put()` fait du contenu décodé, et ce que fait une route qui reçoit son image autrement. Le
	 * fichier reste à l'appelant, qui l'a créé et le retire.
	 */
	public static function putFile(string $type, \Element $eElement, string $file): void {

		$lib = self::getLib($type);

		// Le hash existant est réutilisé pour que le remplacement écrase l'image en place plutôt que
		// d'en créer une seconde et de laisser la première au ramasse-miettes (CleanLib, 5 jours).
		$hashCurrent = $eElement[$lib->getField()];
		$hash = $hashCurrent !== NULL ? substr($hashCurrent, 0, 20) : NULL;

		if($lib->putFile($eElement, $hash, $file, []) === FALSE) {
			throw new \ApiException('Unsupported image content', 'media_invalid_format', 400);
		}

	}

	/** Retire l'image d'un élément. Sans image, l'appel est sans effet. */
	public static function delete(string $type, \Element $eElement): void {

		self::getLib($type)->delete($eElement);

	}

	/**
	 * Écrit le contenu décodé dans un fichier temporaire et rend son chemin.
	 *
	 * La taille est mesurée sur la chaîne, avant décodage : quatre caractères de base64 portent
	 * trois octets. Décoder pour mesurer ensuite reviendrait à allouer ce qu'on s'apprête à refuser.
	 * Le fichier revient à l'appelant, qui le retire.
	 */
	public static function decode(string $content): string {

		if((int)(strlen($content) * 3 / 4) > MediaSetting::MAX_API_IMAGE_SIZE * 1024 * 1024) {
			throw new \ApiException('Media is larger than '.MediaSetting::MAX_API_IMAGE_SIZE.' Mo', 'media_too_large', 413);
		}

		// Mode strict : un caractère hors alphabet fait échouer le décodage, là où le mode par défaut
		// l'ignore en silence et rend un binaire tronqué qu'Imagick refuserait plus loin, sans dire
		// pourquoi.
		$binary = base64_decode($content, TRUE);

		if($binary === FALSE or $binary === '') {
			throw new \ApiException('Content is not valid base64', 'media_invalid_encoding', 400);
		}

		$file = tempnam(sys_get_temp_dir(), 'otf-media-');

		if($file === FALSE or file_put_contents($file, $binary) === FALSE) {
			throw new \ApiException('Can not store media', 'media_storage_error', 500);
		}

		return $file;

	}

	/** Le type vient toujours du code appelant, jamais du corps de la requête. */
	private static function getLib(string $type): MediaLib {

		if(in_array($type, MediaSetting::$images, TRUE) === FALSE) {
			throw new \ApiException('Unknown media type \''.$type.'\'', 'media_unknown_type', 400);
		}

		return MediaLib::getInstance($type) ?? throw new \ApiException('Unknown media type \''.$type.'\'', 'media_unknown_type', 400);

	}

}
?>
