<?php
namespace aid;

class OrganizationUi {

	public function query(\PropertyDescriber $d): void {

		$d->prepend = \Asset::icon('bank');
		$d->field = 'autocomplete';

		$d->placeholder ??= s("Tapez le nom d'un organisme");

		$d->autocompleteUrl = '/aid/admin/organization:query';
		$d->autocompleteResults = fn(Organization $e) => self::getAutocomplete($e);

	}

	public static function getAutocomplete(Organization $eOrganization): array {

		return [
			'value' => $eOrganization['id'],
			'itemHtml' => encode($eOrganization['name']),
			'itemText' => $eOrganization['name'],
		];

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Organization::model()->describer($property, [
			'name' => s("Nom"),
		]);

		return $d;

	}

}
?>
