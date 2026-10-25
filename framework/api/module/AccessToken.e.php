<?php
namespace api;

class AccessToken extends AccessTokenElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function canRevoke(): bool {

		$eStructure = \structure\Structure::getConnected();
		$eUserOnline = \user\ConnectionLib::getOnline();

		return (
			$eStructure->notEmpty() and
			$eUserOnline->notEmpty() and
			$this['user']->is($eUserOnline) and
			$this['reference'] === $eStructure['id']
		);

	}

	public function acceptRevoke(): bool {

		return ($this['revoked'] === FALSE);

	}

	public function assertRevoke(): bool {

		return ($this->canRevoke() and $this->acceptRevoke());

	}
}
?>
