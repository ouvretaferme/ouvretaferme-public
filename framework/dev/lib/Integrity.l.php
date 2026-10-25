<?php
namespace dev;

class IntegrityLib {

	use \Notifiable;

	public static function calculate(): void {

		foreach(self::notify('integrity') as $generator) {

			foreach($generator as $value) {

				if($value === NULL) {
					continue;
				}

				ErrorPhpLib::handleIntegrity(...$value);

			}

		}

	}

}
