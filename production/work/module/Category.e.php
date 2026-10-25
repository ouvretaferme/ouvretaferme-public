<?php
namespace work;

class Category extends CategoryElement {

	public function canRead(): bool {
		return $this->canWrite();
	}

	public function canWrite(): bool {
		$this->expects(['structure']);
		return $this['structure']->canManage();
	}
}
?>