<?php
namespace toolbox;

/**
 * Le rendu au planning de ce que la Boite à outils y dit dû (`PlanningObserverLib`). Chaque méthode
 * publique de cette classe est un événement du registre des observateurs.
 */
class PlanningObserverUi {

	/**
	 * Le bloc « Rappels » de la semaine `$week` (alertes lot 2, Q1 B) : les entretiens dus,
	 * les compteurs du carnet et ceux de l'eau à relever, avec leurs gestes, sous le titre que le
	 * planning pose (`getDueTitle()`). `$cDue` sort de `PlanningObserverLib::describeDue()`, rangée dans
	 * l'ordre de l'écran. `$origin` est la forme du planning d'où partent les gestes
	 * (`planning\DueLib::isOrigin()`).
	 */
	public static function getDue(\structure\Structure $eStructure, string $week, \Collection $cDue, string $origin): string {
		return new MaintenanceUi()->getPlanning($eStructure, $week, $cDue, $origin);
	}

	public static function getDueTitle(): string {
		return s("Rappels");
	}

}
?>
