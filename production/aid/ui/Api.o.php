<?php
namespace aid;

class ApiObserverUi {

	public static function getAdminDevLinks(): array {

		return [
			'aid' => ['/aid/admin/', s("Aides")],
		];

	}

}
?>
