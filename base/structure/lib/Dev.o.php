<?php
namespace structure;

class DevObserverLib {

	public static function integrity(): \Generator {

		yield self::getVersion();

	}

	/**
	 * On vérifie que toutes les structures sont au même numéro de version
	 */
	public static function getVersion(): ?array {

		$cStructureByVersion = Structure::model()
			->select(['version', 'count' => new \Sql('COUNT(*)', 'int')])
			->group('version')
			->getCollection(index: 'version');

		if($cStructureByVersion->count() > 1) {
			return [
				'Des fermes n\'ont pas le même numéro de version que les autres',
				$cStructureByVersion->getArrayCopy()
			];
		} else {
			return NULL;
		}
	}
}
?>
