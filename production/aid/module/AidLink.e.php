<?php
namespace aid;

class AidLink extends AidLinkElement {

	// Lu comme l'aide qu'il décrit, à qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	public static function properties(\PropertyList $l): void {

		$l->property('target')
			->addAfterCheck('self', function(Aid $eTarget): bool {

				$this->expects(['aid']);

				return $eTarget['id'] !== $this['aid']['id'];

			});

	}

}
?>
