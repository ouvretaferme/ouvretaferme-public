<?php
namespace toolbox;

/**
 * Le réglage de la consommation d'eau, un seul par structure : le rythme du rappel des compteurs
 * d'eau à relever, comme celui du carnet d'entretien (`MaintenanceConfigurationLib`).
 */
class WaterConfigurationLib extends WaterConfigurationCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['readingEveryMonths'];
	}

	/**
	 * Le rythme d'une structure qui n'a rien réglé : le défaut du schéma.
	 */
	public static function getReadingEveryMonthsDefault(): int {
		return WaterConfiguration::model()->getDefaultValue('readingEveryMonths');
	}

	public static function getReadingEveryMonths(\structure\Structure $eStructure): int {

		return WaterConfiguration::model()
			->whereStructure($eStructure)
			->getValue('readingEveryMonths', self::getReadingEveryMonthsDefault());

	}

	/**
	 * Le rythme de chaque structure qui a un réglage, par identifiant de structure, pour le cron qui
	 * balaie le parc (`NotificationLib::emitWaterReadings()`). Une structure absente du tableau suit
	 * le défaut.
	 */
	public static function getReadingEveryMonthsByStructure(): array {

		$cWaterConfiguration = WaterConfiguration::model()
			->select(['structure', 'readingEveryMonths'])
			->getCollection();

		$readingEveryMonths = [];

		foreach($cWaterConfiguration as $eWaterConfiguration) {
			$readingEveryMonths[$eWaterConfiguration['structure']['id']] = $eWaterConfiguration['readingEveryMonths'];
		}

		return $readingEveryMonths;

	}

	/**
	 * Le panneau s'ouvre sur le rythme de la structure, le défaut tant qu'elle n'a rien réglé.
	 */
	public static function fillPanel(WaterConfiguration $e): void {

		$e->expects(['structure']);

		$e['readingEveryMonths'] = self::getReadingEveryMonths($e['structure']);

	}

	/**
	 * Une structure n'a qu'un réglage : le poser une seconde fois le remplace.
	 */
	public static function create(WaterConfiguration $e): void {

		$e->expects(['structure', 'readingEveryMonths']);

		$affected = WaterConfiguration::model()
			->option('add-ignore')
			->insert($e);

		if($affected === 0) {

			// La ligne de la structure n'est pas `$e`, qui n'a pas d'id : l'écriture se désigne par la
			// structure, et garde donc un tableau de valeurs.
			WaterConfiguration::model()
				->whereStructure($e['structure'])
				->update($e->extracts(['readingEveryMonths']));

		}

		// Le rythme dit le jour où chaque compteur entre au planning.
		\planning\DueLib::refresh($e['structure'], 'toolbox');

	}

}
?>
