<?php
namespace sequence;

class Slice extends SliceElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function formatPart(Crop $eCrop): string {
		return s("{partPercent} %", $this);
	}

}
?>
