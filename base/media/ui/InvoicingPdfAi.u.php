<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : le PDF d'une facture électronique se
 * lit par les routes du paquet `invoicing`, après contrôle des droits.
 */
class InvoicingPdfAiUi extends MediaUi {

	/** Même raison que `InvoicingPdfAiLib::__construct()`. */
	public function __construct() {

		$this->type = 'invoicing-pdf';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
