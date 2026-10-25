<?php
namespace security;

class AccessEvent extends AccessEventElement {

	public function canRead(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

}
?>
