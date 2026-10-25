<?php
namespace toolbox;

/**
 * Le réglage du carnet d'entretien d'une ferme : l'intervention que prend, au planning, la tâche
 * créée par un entretien fait (`MaintenanceTaskLib`), et le rythme du rappel des compteurs à relever
 * (`NotificationLib::READING`). Une ligne par ferme, absente tant que rien n'a été réglé : le
 * premier « Fait » la pose, le menu du carnet la change ensuite.
 */
class MaintenanceConfiguration extends MaintenanceConfigurationElement {

	// Les rythmes qu'on peut choisir, en mois. Chacun divise 6 : le rappel d'une ferme tombe alors
	// toujours sur les mêmes mois de l'année, janvier compris (`NotificationLib::isReadingMonth()`).
	const READING_EVERY_MONTHS = [1, 2, 3, 6];

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Le réglage décide de ce qu'un entretien fait écrit au planning : il s'ouvre à qui peut y écrire,
	// le droit de « Fait » (`MaintenanceDone::canWrite()`), sans quoi le premier « Fait » d'un
	// membre qui planifie resterait bloqué sur un champ qu'il ne peut pas enregistrer.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public static function properties(\PropertyList $l): void {

		// Le schéma ne sait pas à qui est l'intervention, ni sa catégorie.
		$l->property('action')
			->addAfterCheck('usable', function(\work\Action $eAction): bool {

				$this->expects(['structure']);

				return MaintenanceConfigurationLib::getActions($this['structure'])->offsetExists($eAction['id']);

			});

		// Une liste fermée dans les bornes du schéma : elle couvre ce qu'il vérifie, champ vide compris.
		$l->property('readingEveryMonths')
			->replaceCheck(fn(?int $readingEveryMonths): bool => in_array($readingEveryMonths, MaintenanceConfiguration::READING_EVERY_MONTHS, TRUE));

	}

}
?>
