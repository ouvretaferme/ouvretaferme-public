<?php
namespace securing;

class Log extends LogElement {

	public function canRead(): bool {
		return TRUE;
	}

	public static function getSelection(): array {

		return Log::model()->getProperties() + [
			'doneBy' => ['id', 'firstName', 'lastName'],
		];

	}

}
?>
