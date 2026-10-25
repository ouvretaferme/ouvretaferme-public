<?php
namespace aid;

class AidOrganizationLib extends AidOrganizationCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['organization', 'role'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['role'];
	}

	public static function create(AidOrganization $e): void {

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			AidOrganization::fail('organization.duplicate');
		}

	}

}
?>
