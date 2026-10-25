<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : l'application lit le fichier par
 * la route `external/file:content` et ne publie jamais son adresse dans le stockage.
 */
class ExternalFileAiUi extends MediaUi {

	/**
	 * Même raison que `ExternalFileAiLib::__construct()` : le type ne se déduit pas d'un nom de classe
	 * suffixé. Aucun script d'envoi de `media` n'est chargé, le dépôt passant par le document.
	 */
	public function __construct() {

		$this->type = 'external-file';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
