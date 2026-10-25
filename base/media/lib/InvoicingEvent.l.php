<?php
namespace media;

class InvoicingEventLib extends MediaLib {

	// Les justificatifs sont référencés depuis les bases structure_<id>, que le nettoyage ne parcourt
	// pas : ils restent actifs. Rendre FALSE annulait le nettoyage de tous les autres types.
	public function clean(): bool {

		Media::model()
			->whereType($this->type)
			->update([
				'status' => Media::ACTIVE
			]);

		return TRUE;

	}

}
?>
