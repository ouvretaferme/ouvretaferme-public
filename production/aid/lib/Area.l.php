<?php
namespace aid;

class AreaLib extends AreaCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['department', 'epci', 'commune', 'note'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['epci', 'commune', 'note'];
	}

	public static function create(Area $e): void {

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Area::fail('department.duplicate');
		}

	}

}
?>
