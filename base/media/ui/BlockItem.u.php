<?php
namespace media;

/**
 * Sert l'adresse d'une image de bloc (`getUrlByHash()`) et la copie d'un fichier
 * (`MediaLib::duplicate()`).
 */
class BlockItemUi extends MediaUi {

	/**
	 * Le parent n'est pas appelé : il chargerait `block-item.js`, déduit du type, que rien ne fournit,
	 * aucun envoi ne passant par le script de `media` (`BlockItemLib::buildElement()`).
	 */
	public function __construct() {

		$this->type = 'block-item';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
