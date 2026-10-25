<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : un relevé se télécharge par la route
 * du paquet `bank`, jamais par l'adresse publique du stockage.
 */
class BankStatementAiUi extends MediaUi {

	/**
	 * Même raison que `BankStatementAiLib::__construct()` : le type ne se déduit pas d'un nom de classe
	 * suffixé.
	 */
	public function __construct() {

		$this->type = 'bank-statement';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
