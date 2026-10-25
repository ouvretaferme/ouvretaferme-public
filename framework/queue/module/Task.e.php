<?php
namespace queue;

class Task extends TaskElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>