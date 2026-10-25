<?php
namespace user;

class Ban extends BanElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>