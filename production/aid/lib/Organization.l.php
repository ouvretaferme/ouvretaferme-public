<?php
namespace aid;

class OrganizationLib extends OrganizationCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'type', 'level', 'url', 'grants'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'type', 'level', 'url', 'grants'];
	}

	public static function getByName(string $name): Organization {

		$eOrganization = new Organization();

		Organization::model()
			->select(Organization::getSelection())
			->whereName($name)
			->get($eOrganization);

		return $eOrganization;

	}

	public static function getByQuery(string $query): \Collection {

		return Organization::model()
			->select(['id', 'name'])
			->whereName('LIKE', '%'.$query.'%', if: $query !== '')
			->sort(['name' => SORT_ASC])
			->getCollection(0, 20);

	}

}
?>
