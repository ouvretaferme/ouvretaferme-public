<?php
namespace aid;

class Tag extends TagElement {

	// Le tag qui range les aides de conversion, que le filtre bio garde pour une structure qui n'est pas en bio.
	const CONVERSION = 'conversion-bio';

	// Lu, comme le catalogue qu'il range, par qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

}
?>
