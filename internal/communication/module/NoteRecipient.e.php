<?php
namespace communication;

class NoteRecipient extends NoteRecipientElement {

	public function canRead(): bool {

		$this->expects(['user']);

		return $this['user']->is(\user\ConnectionLib::getOnline());

	}

}
?>
