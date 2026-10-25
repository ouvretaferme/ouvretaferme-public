<?php
namespace user;

class UserAvatarRequest extends UserAvatarRequestElement {

	public function canRead(): bool {

		$this->expects(['user']);
		return $this['user']->is(ConnectionLib::getOnline());

	}

}
?>
