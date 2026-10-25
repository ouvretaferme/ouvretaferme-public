<?php
namespace media;

/**
 * Le PDF d'une facture électronique (`invoicing\PdfContent`).
 *
 * Le type a longtemps été `pdf-content`, partagé avec `selling\PdfContent`. Le nettoyage de nuit ne
 * cherchait les hashes que dans la table de la base commune : ceux des bases `structure_<id>` passaient
 * en DELETED, et leur fichier quittait le disque dix jours plus tard (bug relevé le 07/10/2026, 208 PDF
 * de factures électroniques marqués sur la copie de production du 03/10).
 */
class InvoicingPdfAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai donnerait
	 * `invoicing-pdf-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'invoicing-pdf';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Les hashes sont cités depuis les bases `structure_<id>`, que le nettoyage ne parcourt pas : tout
	 * ce que la passe a mis en SEARCHING repasse en ACTIVE. Rien n'efface ces PDF aujourd'hui, et un
	 * effacement futur passerait par `deleteByHash()`, dont le DELETED reste en place.
	 */
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
