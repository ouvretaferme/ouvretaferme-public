<?php
namespace aid;

class AidOrganization extends AidOrganizationElement {

	// Lu comme l'aide qu'il décrit, à qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

}
?>
