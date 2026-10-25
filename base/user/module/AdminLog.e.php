<?php
namespace user;

class AdminLog extends AdminLogElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>
