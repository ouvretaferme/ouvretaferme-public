<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : le téléchargement passe par la
 * leçon, jamais par l'adresse publique du stockage.
 */
class LearningAttachmentAiUi extends MediaUi {

	/**
	 * Même raison que `LearningAttachmentAiLib::__construct()` : le type ne se déduit pas d'un nom de
	 * classe suffixé. Aucun script d'envoi de `media` n'est chargé, le dépôt passant par la leçon.
	 */
	public function __construct() {

		$this->type = 'learning-attachment';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
