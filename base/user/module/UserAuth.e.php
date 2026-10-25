<?php
namespace user;

class UserAuth extends UserAuthElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>