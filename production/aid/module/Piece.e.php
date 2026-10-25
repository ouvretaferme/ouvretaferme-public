<?php
namespace aid;

class Piece extends PieceElement {

	// Lu, comme les aides qui la demandent, par qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

}
?>
