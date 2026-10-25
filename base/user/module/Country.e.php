<?php
namespace user;

class Country extends CountryElement {

	public function canRead(): bool {
		return TRUE;
	}

	public static function form(): mixed {
		return \user\CountryLib::getForForm();
	}

	public static function ask(Country $e): mixed {
		return \user\CountryLib::ask($e);
	}

	public function isFrance(): bool {

		return (
			$this->exists() and
			in_array($this['id'], \user\UserSetting::COUNTRY_FRANCE)
		);

	}

	public function isBelgium(): bool {

		return (
			$this->exists() and
			in_array($this['id'], \user\UserSetting::COUNTRY_BELGIUM)
		);

	}

}
?>
