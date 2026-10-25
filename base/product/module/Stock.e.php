<?php
namespace product;

class Stock extends StockElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>