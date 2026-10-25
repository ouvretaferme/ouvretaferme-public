<?php
namespace aid;

class AidLinkLib extends AidLinkCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['target', 'kind'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['kind'];
	}

	public static function create(AidLink $e): void {

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			AidLink::fail('target.duplicate');
		}

	}

}
?>
