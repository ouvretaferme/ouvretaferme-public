<?php
namespace aggregate;

class Price extends PriceElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>