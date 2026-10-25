<?php
namespace product;

class StockBookmark extends StockBookmarkElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canSelling();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

}
?>