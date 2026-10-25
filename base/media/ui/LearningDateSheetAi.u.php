<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : le téléchargement passe par la
 * date, jamais par l'adresse publique du stockage, une feuille portant des signatures.
 */
class LearningDateSheetAiUi extends MediaUi {

	/**
	 * Même raison que `LearningDateSheetAiLib::__construct()` : le type ne se déduit pas d'un nom de
	 * classe suffixé.
	 */
	public function __construct() {

		$this->type = 'learning-date-sheet';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
