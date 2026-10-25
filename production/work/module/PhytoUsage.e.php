<?php
namespace work;

class PhytoUsage extends PhytoUsageElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return FALSE;
	}

}
?>
