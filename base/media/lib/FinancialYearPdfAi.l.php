<?php
namespace media;

/**
 * Les documents PDF d'un exercice (`financialYear\PdfContent`) : bilans, balances, grand livre.
 *
 * Même histoire que `InvoicingPdfAiLib` : sous le type partagé `pdf-content`, le nettoyage de nuit les
 * croyait orphelins (373 documents marqués sur la copie de production du 03/10/2026).
 */
class FinancialYearPdfAiLib extends MediaLib {

	/** Même raison que `InvoicingPdfAiLib::__construct()`. */
	public function __construct() {

		$this->type = 'financial-year-pdf';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/** Même raison que `InvoicingPdfAiLib::clean()`. */
	public function clean(): bool {

		Media::model()
			->whereType($this->type)
			->whereStatus(Media::SEARCHING)
			->update([
				'status' => Media::ACTIVE
			]);

		return TRUE;

	}

}
?>
