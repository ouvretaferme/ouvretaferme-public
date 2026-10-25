<?php
namespace data;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return NULL;

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'PdpHistory::redone' => s("Le traitement après la requête API a bien été relancé."),

			default => null

		};


	}

}
?>
