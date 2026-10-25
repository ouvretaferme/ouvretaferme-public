<?php
namespace toolbox;

/**
 * Le réglage de la consommation d'eau d'une structure : le rythme du rappel des compteurs d'eau à
 * relever (`NotificationLib::WATER_READING`), sur le modèle du carnet d'entretien (demande
 * du 03/10/2026, « exactement comme le carnet d'entretien du matériel »). Une ligne par structure,
 * absente tant que rien n'a été réglé.
 */
class WaterConfiguration extends WaterConfigurationElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Le droit de qui relève et pose un compteur (`WaterMeter::canWrite()`), comme le réglage du carnet.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public static function properties(\PropertyList $l): void {

		// Une liste fermée dans les bornes du schéma : elle couvre ce qu'il vérifie, champ vide compris.
		// C'est celle du carnet, parce que les deux rappels partent du même calendrier
		// (`NotificationLib::isReadingMonth()`).
		$l->property('readingEveryMonths')
			->replaceCheck(fn(?int $readingEveryMonths): bool => in_array($readingEveryMonths, MaintenanceConfiguration::READING_EVERY_MONTHS, TRUE));

	}

}
?>
