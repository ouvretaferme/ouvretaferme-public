<?php
namespace learning;

class Progress extends ProgressElement {

	public function canRead(): bool {

		$this->expects(['user']);

		return $this['user']->isOnline();

	}

}
?>
