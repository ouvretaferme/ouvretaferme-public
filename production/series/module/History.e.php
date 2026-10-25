<?php
namespace series;

class History extends HistoryElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

}
?>
