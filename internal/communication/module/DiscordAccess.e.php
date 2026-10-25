<?php
namespace communication;

class DiscordAccess extends DiscordAccessElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function acceptRevoke(): bool {

		return $this->notEmpty() and
			$this['status'] === DiscordAccess::JOINED;

	}
}
?>
