<?php
namespace toolbox;

class CompositionWeek extends CompositionWeekElement {

	public function canRead(): bool {

		$this->expects(['formula']);

		return $this['formula']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['formula']);

		return $this['formula']->canWrite();

	}

	public function acceptWrite(): bool {

		$this->expects(['formula']);

		return $this['formula']->acceptWrite();

	}

}
?>
