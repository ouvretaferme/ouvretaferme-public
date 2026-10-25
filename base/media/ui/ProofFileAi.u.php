<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : une page de justificatif se lit par
 * les routes du paquet `proof`, jamais par l'adresse publique du stockage.
 */
class ProofFileAiUi extends MediaUi {

	/**
	 * Même raison que `ProofFileAiLib::__construct()` : le type ne se déduit pas d'un nom de classe
	 * suffixé. Aucun script d'envoi de `media` n'est chargé, le dépôt passant par le justificatif.
	 */
	public function __construct() {

		$this->type = 'proof-file';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
