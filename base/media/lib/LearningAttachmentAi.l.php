<?php
namespace media;

/**
 * Les fichiers à télécharger d'une leçon (`learning\Attachment`).
 *
 * Le type n'écrit dans aucun champ d'élément (`field` à NULL) : la ligne `learningAttachment` est
 * créée après l'envoi, avec le hash qu'il rend, et plusieurs lignes peuvent porter le même hash
 * quand une formation est copiée. Le média ne se supprime donc jamais depuis la ligne : le
 * nettoyage de nuit (`CleanLib`) le laisse partir quand plus aucune ligne ne le porte.
 */
class LearningAttachmentAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai des fichiers
	 * écrits par IA donnerait `learning-attachment-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'learning-attachment';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Le fichier est écrit tel qu'il a été déposé, image comprise : le lecteur télécharge ce que
	 * l'auteur a envoyé. La branche image de `MediaLib::send()` ne peut pas servir ici : elle confie à
	 * Imagick un fichier temporaire déjà fermé, qu'il lit vide (« Can not process empty Imagick
	 * object », mesuré le 21/09/2026 sur une image PNG).
	 */
	public function send(\Element $eElement, &$hash, string $binary, int|string|null $fileType = NULL): bool {

		$this->addMedia($eElement, $hash, $fileType);

		MediaSetting::$mediaDriver->sendBinary($binary, $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

		return TRUE;

	}

	/**
	 * Sans cette surcharge, `MediaLib::clean()` remettrait ACTIVE tout le type, `field` étant NULL :
	 * aucun fichier retiré d'une leçon ne quitterait le stockage.
	 */
	public function clean(): bool {

		$hashes = \learning\Attachment::model()
			->getColumn('hash');

		foreach(array_chunk(array_unique($hashes), 100) as $hashesChunk) {
			$this->activeMedias($hashesChunk);
		}

		return TRUE;

	}

}
?>
