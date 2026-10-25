<?php
namespace media;

/**
 * Sert à retrouver le fichier d'un hash (`getBasenameByHash()`) : un document PDF d'exercice se lit
 * par l'écran de l'exercice ou par la route financialYear/{id}/document de l'API, jamais par son
 * adresse sur media.ouvretaferme.org, que Nginx refuse.
 */
class FinancialYearPdfAiUi extends MediaUi {

	/** Même raison que `FinancialYearPdfAiLib::__construct()`. */
	public function __construct() {

		$this->type = 'financial-year-pdf';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

}
?>
