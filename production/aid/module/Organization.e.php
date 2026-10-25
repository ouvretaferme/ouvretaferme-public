<?php
namespace aid;

class Organization extends OrganizationElement {

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
