<?php
namespace aid;

class EditionLib extends EditionCrud {

	/**
	 * Le semis reprend la date de relecture de l'édition (NULL). L'équipe ne la saisit pas (`'admin'`) :
	 * une édition n'a pas d'auteur de relecture, et une date sans auteur ne prouverait rien.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		$properties = ['name', 'startDate', 'endDate', 'state', 'rate', 'ceiling', 'url', 'checkedAt'];

		return match($origin) {
			NULL => $properties,
			'admin' => array_values(array_diff($properties, ['checkedAt'])),
		};

	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return self::getPropertiesCreate($origin);
	}

}
?>
