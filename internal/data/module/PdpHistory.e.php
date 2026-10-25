<?php
namespace data;

class PdpHistory extends PdpHistoryElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\Structure::getSelection(),
			'doneBy' => \user\User::getSelection(),
			'relaunchedBy' => \user\User::getSelection(),
		];

	}

	public function canRead(): bool {

		return \user\ConnectionLib::getOnline()->isAdmin();

	}
}
?>
