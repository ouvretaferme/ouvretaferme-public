<?php
namespace toolbox;

/**
 * Un entretien fait, qui entre à l'historique du matériel et fait repartir l'échéance de son
 * entretien (`MaintenanceLib::fill()`). Sans entretien, c'est une intervention (« Ajouter une
 * intervention », retour du 30/09) : elle n'entre qu'à l'historique, sous son propre libellé. Sur un matériel à compteur, son compteur est lu dans les
 * relevés, ou saisi quand la personne choisit de mettre le compteur à jour (`MaintenanceDoneLib::create()`).
 *
 * À l'écran, le matériel et l'entretien ne sont pas construits : la page les pose depuis le lien qui
 * ouvre le panneau, après en avoir vérifié l'état (`page/maintenanceDone.p.php`). L'API, qui n'a pas
 * de lien, les reçoit dans le corps et les construit (`MaintenanceDoneLib::getPropertiesCreate()`) :
 * les étapes `owned` y posent les mêmes questions que la page.
 */
class MaintenanceDone extends MaintenanceDoneElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Noter un entretien fait consigne ce qui s'est passé, comme un relevé : le droit du planning.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	// Un matériel qu'on ne suit plus ne reçoit plus rien (`Equipment::acceptUpdate()`) : son historique
	// reste tel qu'il était, comme ses relevés (`EquipmentReading::acceptUpdate()`). La création n'est
	// pas bornée ici, un élément neuf n'a pas encore de matériel : la page et l'étape `owned` le jugent.
	public function acceptUpdate(): bool {

		$this->expects(['equipment' => ['status']]);
		return ($this['equipment']['status'] === Equipment::ACTIVE);

	}

	public function acceptDelete(): bool {
		return $this->acceptUpdate();
	}

	/**
	 * Ce que dit la ligne de l'historique : l'entretien, ou le libellé de l'intervention.
	 */
	public function getName(): string {

		$this->expects(['name', 'maintenance']);

		return $this['maintenance']->empty() ? $this['name'] : $this['maintenance']['name'];

	}

	/**
	 * Une photo se dépose par `/media/:put` sans élément : le hash revient au navigateur, qui le poste
	 * avec l'entretien. Un hash posté est donc celui d'un média que le compte a lui-même déposé sous ce
	 * type, la même règle que `/media/:put` applique à un remplacement (`base/media/page/index.p.php`).
	 * Sans elle, on poserait sur son entretien la photo d'une autre structure en recopiant son hash.
	 */
	public static function isPhotoOwned(string $hash): bool {

		return \media\Media::model()
			->whereType('maintenance-done')
			->whereHash($hash)
			->whereUser(\user\ConnectionLib::getOnline())
			->whereStatus('!=', \media\Media::DELETED)
			->exists();

	}

	public static function properties(\PropertyList $l): void {

		// Un entretien se note sur un matériel suivi de la structure, comme un relevé se pose
		// (`EquipmentReading::properties()`). Le compteur est chargé ici pour les étapes de `value`.
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

		// Sans entretien, la ligne est une intervention. Avec, il est un entretien encore actif du
		// matériel donné : un entretien « Une seule fois » déjà fait, ou retiré, ne se note plus, ce que
		// l'écran dit en ne montrant plus « Fait ». Un matériel refusé porte déjà l'erreur.
		$l->property('maintenance')
			->addAfterCheck('owned', function(Maintenance $eMaintenance, \PropertyList $l): bool {

				if($eMaintenance->empty() or $l->isInvalid('equipment')) {
					return TRUE;
				}

				return (
					Maintenance::model()
						->select(['structure', 'equipment', 'status', 'everyValue', 'everyMonths'])
						->get($eMaintenance) and
					$eMaintenance['structure']->is($this['structure']) and
					$eMaintenance['equipment']->is($this['equipment']) and
					$eMaintenance->acceptUpdate()
				);

			});

		// Le libellé est celui de l'entretien quand il y en a un : seule une intervention porte le sien.
		// Un entretien refusé porte déjà l'erreur, et ne dit pas si un libellé est attendu.
		$l->property('name')
			->addBeforeCast('maintenance', function(mixed &$value, \PropertyList $l): bool {

				if($l->isInvalid('maintenance')) {
					return TRUE;
				}

				$this->expects(['maintenance']);

				if($this['maintenance']->notEmpty()) {
					$value = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('required', fn(?string $name, \PropertyList $l): bool => ($l->isInvalid('maintenance') or $this['maintenance']->notEmpty() or $name !== NULL));

		$l->property('date')
			->addAfterCheck('future', fn(string $date): bool => $date <= \Clock::date());

		// La tâche que « Fait » écrit au planning (`MaintenanceTaskLib`) n'est pas une colonne : elle se
		// construit par les `build()` de ses propres modules, dont les refus portent déjà leur message et
		// leur champ. Une date refusée ne dit pas quel jour la poser, et porte déjà l'erreur.
		$l->property('task')
			->replaceCheck(function(mixed $value, \PropertyList $l): bool {

				$this['eTask'] = new \series\Task();
				$this['eMaintenanceConfiguration'] = new MaintenanceConfiguration();

				if(MaintenanceTaskLib::isExpected($this) === FALSE or $l->isInvalid('date')) {
					return TRUE;
				}

				return MaintenanceTaskLib::prepare($this, $l->input);

			});

		// Le contrôle ne tire que sur un hash qui change : une ligne consignée par un autre membre garde
		// une photo que le compte connecté n'a pas déposée, et doit pouvoir se modifier sans la perdre.
		// La valeur de comparaison est celle que la page a relue avant `build()`.
		$l->property('photo')
			->addAfterCheck('owned', fn(?string $hash): bool => (
				$hash === NULL or
				($this->exists() and $this['photo'] === $hash) or
				MaintenanceDone::isPhotoOwned($hash)
			));

		// Une date refusée ne dit pas où placer le relevé : les deux dernières étapes ne jugent alors rien,
		// la date porte déjà l'erreur.
		//
		// Le compteur ne se saisit que si la personne a choisi « Mettre à jour le compteur »
		// (`meterUpdate`, retour du 30/09). Sinon il est lu dans les relevés
		// (`MaintenanceDoneLib::prepareValue()`), et ce qu'un POST porterait est oublié. Saisi, il devient
		// le relevé du jour de l'entretien : il obéit aux mêmes règles qu'un relevé
		// (`EquipmentReading::properties()`), confirmation comprise.
		$l->property('value')
			->addBeforeCast('update', function(mixed &$value, \PropertyList $l): bool {

				$this['meterUpdate'] = (($l->input['meterUpdate'] ?? '0') === '1');

				if($this['meterUpdate'] === FALSE) {
					$value = NULL;
				}

				return TRUE;

			})
			// Le cast du cadriciel rend 0 pour ce qui n'est pas un nombre, et 0 est un compteur
			// (`EquipmentReading::isMeterValue()`).
			->addBeforeCast('number', fn(mixed $value): bool => EquipmentReading::isMeterValue($value))
			// Un matériel refusé porte déjà l'erreur, et n'a ni compteur ni relevés à lire : les trois
			// étapes qui le lisent ne jugent alors rien.
			->addAfterCheck('meter', function(?int $value, \PropertyList $l): bool {

				if($l->isInvalid('equipment')) {
					return TRUE;
				}

				$this->expects(['equipment' => ['meter']]);

				return ($value === NULL or $this['equipment']->hasMeter());

			})
			// Deux raisons de l'exiger : la personne a choisi de le mettre à jour, ou l'entretien ne peut
			// repartir que de lui (`MaintenanceDoneLib::isValueRequired()`). Un matériel ou un entretien
			// refusés portent déjà l'erreur.
			->addAfterCheck('required', function(?int $value, \PropertyList $l): bool {

				if($value !== NULL) {
					return TRUE;
				}

				if($this['meterUpdate']) {
					return FALSE;
				}

				if($l->isInvalid('equipment') or $l->isInvalid('maintenance')) {
					return TRUE;
				}

				return (MaintenanceDoneLib::isValueRequired($this) === FALSE);

			})
			->addAfterCheck('backwards', function(?int $value, \PropertyList $l): bool {

				if($value === NULL or $l->isInvalid('equipment') or $l->isInvalid('date') or EquipmentReadingLib::isConfirmed($l->input)) {
					return TRUE;
				}

				return EquipmentReadingLib::acceptBefore($this['equipment'], $this['date'], $value);

			})
			->addAfterCheck('ahead', function(?int $value, \PropertyList $l): bool {

				if($value === NULL or $l->isInvalid('equipment') or $l->isInvalid('date') or EquipmentReadingLib::isConfirmed($l->input)) {
					return TRUE;
				}

				return EquipmentReadingLib::acceptAfter($this['equipment'], $this['date'], $value);

			});

	}

}
?>
