<?php
namespace aggregate;

class Sale extends SaleElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>
