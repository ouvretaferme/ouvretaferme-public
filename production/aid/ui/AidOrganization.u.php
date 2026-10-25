<?php
namespace aid;

class AidOrganizationUi {

	public static function p(string $property): \PropertyDescriber {

		$d = AidOrganization::model()->describer($property, [
			'organization' => s("Organisme"),
			'role' => s("Rôle"),
		]);

		switch($property) {

			case 'organization' :
				new OrganizationUi()->query($d);
				break;

			case 'role' :
				$d->field = 'select';
				$d->values = [
					AidOrganization::FUNDER => s("Accorde"),
					AidOrganization::COFUNDER => s("Cofinance"),
					AidOrganization::INSTRUCTOR => s("Instruit"),
					AidOrganization::PAYER => s("Paie"),
					AidOrganization::DESK => s("Reçoit le dépôt"),
					AidOrganization::RELAY => s("Relaie"),
				];
				$d->placeholder = s("Rôle");
				break;

		}

		return $d;

	}

}
?>
