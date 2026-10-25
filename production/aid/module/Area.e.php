<?php
namespace aid;

class Area extends AreaElement {

	// Lu comme l'aide qu'il décrit, à qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	public static function properties(\PropertyList $l): void {

		// La liste officielle couvre tout ce que le schéma vérifie, une longueur de 2 ou 3.
		$l->property('department')
			->replaceCheck(fn(?string $department): bool => (
				$department !== NULL and
				array_key_exists($department, AidSetting::DEPARTMENTS)
			));

	}

}
?>
