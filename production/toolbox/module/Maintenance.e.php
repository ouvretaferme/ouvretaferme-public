<?php
namespace toolbox;

/**
 * Un entretien planifié sur un matériel, dû à « Prochaine fois » (`plannedDate`, `plannedValue`). Il
 * revient régulièrement, toutes les N heures ou N kilomètres, tous les N mois, ou au premier des deux
 * (arbitrage Q6 A de la planche pf-t4-v2), ou ne se fait qu'une seule fois (arbitrage Q2 de la planche
 * pf-t4-v4) : fait, il quitte alors le bloc du matériel (`Maintenance::DONE`).
 *
 * « Une seule fois » ou « Régulièrement » n'est pas une colonne : c'est l'absence ou la présence d'une
 * fréquence, que le formulaire pose par `frequency` (`isRegular()`).
 *
 * À l'écran, le matériel n'est pas construit : la page le pose depuis le lien qui ouvre le panneau,
 * après en avoir vérifié le droit (`page/maintenance.p.php`). L'API le construit, à la création
 * seulement (`MaintenanceLib::getPropertiesCreate()`, étape `owned`).
 */
class Maintenance extends MaintenanceElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public function acceptUpdate(): bool {

		$this->expects(['status']);
		return ($this['status'] === Maintenance::ACTIVE);

	}

	// Seul le retard d'un entretien régulier s'ignore : l'alerte d'un entretien bientôt dû n'est pas
	// rouge, et un entretien « Une seule fois » n'a pas de fréquence pour dire quand l'alerte reviendrait.
	// `due` est posé par `MaintenanceLib::fill()`.
	public function acceptIgnore(): bool {

		$this->expects(['status', 'due']);
		return ($this->acceptUpdate() and $this->isRegular() and $this['due'] === MaintenanceLib::OVERDUE);

	}

	// La croix du planning (demande du 03/10) passe l'échéance d'un entretien en retard ou
	// bientôt dû, ceux que le planning montre avec une marque. Elle se refuse, comme « Ignorer ce
	// retard », sur un entretien « Une seule fois » : sans fréquence, il n'aurait plus d'échéance et ne
	// reviendrait jamais (tranché par M-Prod sous mandat le 03/10).
	public function acceptSkip(): bool {

		$this->expects(['status', 'due']);
		return ($this->acceptUpdate() and $this->isRegular() and in_array($this['due'], [MaintenanceLib::OVERDUE, MaintenanceLib::SOON], TRUE));

	}

	public function assertSkip(): bool {
		return $this->acceptSkip() and $this->canUpdate();
	}

	public function isRegular(): bool {

		$this->expects(['everyValue', 'everyMonths']);
		return ($this['everyValue'] !== NULL or $this['everyMonths'] !== NULL);

	}

	public function assertIgnore(): bool {
		return $this->acceptIgnore() and $this->canUpdate();
	}

	public static function properties(\PropertyList $l): void {

		// Un entretien se planifie sur un matériel suivi de la structure, comme un relevé se pose
		// (`EquipmentReading::properties()`). Le compteur est chargé ici pour les étapes `meter`.
		$l->property('equipment')
			->addAfterCheck('owned', function(Equipment $eEquipment): bool {

				$this->expects(['structure']);

				return (
					Equipment::model()
						->select(['structure', 'status', 'meter'])
						->get($eEquipment) and
					$eEquipment['structure']->is($this['structure']) and
					$eEquipment['status'] === Equipment::ACTIVE
				);

			});

		// « Une seule fois » efface la fréquence, et ce qu'un POST porterait est oublié ; « Régulièrement »
		// en demande une (étape `frequency`). Un appel qui ne dit rien garde la règle d'avant le choix :
		// régulier. L'API, qui n'a pas ce choix, le déduit des deux fréquences
		// (`Maintenance_0_1betaApi::convertProperties()`).
		$l->property('everyValue')
			->addBeforeCast('once', function(mixed &$value, \PropertyList $l): bool {

				$this['frequency'] = (($l->input['frequency'] ?? 'regular') === 'once') ? 'once' : 'regular';

				if($this['frequency'] === 'once') {
					$value = NULL;
				}

				return TRUE;

			})
			// Sans compteur, un entretien ne revient qu'à la date : le panneau ne propose pas le compteur,
			// et un POST qui le porterait est refusé. Un matériel refusé porte déjà l'erreur, et n'a pas
			// de compteur à lire.
			->addAfterCheck('meter', function(?int $value, \PropertyList $l): bool {

				if($l->isInvalid('equipment')) {
					return TRUE;
				}

				$this->expects(['equipment' => ['meter']]);

				return ($value === NULL or $this['equipment']->hasMeter());

			});

		$l->property('everyMonths')
			->addBeforeCast('once', function(mixed &$value, \PropertyList $l): bool {

				if(($l->input['frequency'] ?? 'regular') === 'once') {
					$value = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('frequency', function(?int $months, \PropertyList $l): bool {

				// Une valeur de compteur refusée porte déjà l'erreur.
				if($this['frequency'] === 'once' or $l->isInvalid('everyValue')) {
					return TRUE;
				}

				return ($months !== NULL or $this['everyValue'] !== NULL);

			});

		$l->property('plannedValue')
			->addAfterCheck('meter', function(?int $value, \PropertyList $l): bool {

				if($l->isInvalid('equipment')) {
					return TRUE;
				}

				$this->expects(['equipment' => ['meter']]);

				return ($value === NULL or $this['equipment']->hasMeter());

			});

		// Un entretien qui n'est dû ni à une date ni à un compteur n'a pas d'échéance. La date peut être
		// passée : l'entretien arrive alors en retard, ce que la personne sait déjà.
		$l->property('plannedDate')
			->addAfterCheck('planned', function(?string $date, \PropertyList $l): bool {

				if($l->isInvalid('plannedValue')) {
					return TRUE;
				}

				return ($date !== NULL or $this['plannedValue'] !== NULL);

			});

		// Le seul changement de statut qu'on poste est la suppression (`acceptUpdate()`) : DONE ne se pose
		// qu'avec l'entretien fait (`MaintenanceDoneLib::create()`).
		$l->property('status')
			->addAfterCheck('deleted', fn(string $status): bool => $status === Maintenance::DELETED);

	}

}
?>
