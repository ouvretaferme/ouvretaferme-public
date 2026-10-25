<?php
namespace dev;

class Error extends ErrorElement {

	public function canRead(): bool {
		return TRUE;
	}

	public static function getSelection(): array {

		return parent::getSelection() + [
			'cTrace' => ErrorTrace::model()
				->select('id', 'file', 'line', 'class', 'function', 'arguments')
				->delegateCollection('error'),
			'cParameter' => ErrorParameter::model()
				->select('id', 'type', 'name', 'value')
				->delegateCollection('error'),
		];

	}

}
?>