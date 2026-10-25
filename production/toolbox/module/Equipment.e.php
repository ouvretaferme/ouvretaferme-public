<?php
namespace toolbox;

/**
 * Un matériel suivi par le carnet du matériel (planche pf-t4-v2) : un élément de « Mon matériel »
 * (`work\Tool`), avec un compteur d'heures, de kilomètres, ou sans compteur. Il porte les relevés du compteur
 * (`EquipmentReading`) et les entretiens qui reviennent (`Maintenance`).
 */
class Equipment extends EquipmentElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Suivre un matériel est du même ordre que poser un pluviomètre (`Gauge::canWrite()`) : le droit
	// du planning, jamais celui d'un observateur (production/toolbox/CLAUDE.md, « Qui écrit dans la
	// Boîte »).
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	// Un matériel qu'on ne suit plus ne reçoit plus rien : le suivre de nouveau passe par
	// `EquipmentLib::follow()`, qui le réveille. Seule la modification est bornée, un élément neuf n'a
	// pas encore de statut.
	public function acceptUpdate(): bool {

		$this->expects(['status']);
		return ($this['status'] === Equipment::ACTIVE);

	}

	// Un carnet sans entretien planifié ni historique ne rend qu'une page de zéros : il ne se
	// télécharge pas tant qu'il n'a rien à montrer (relecture de carnet-export du 01/10). L'écran et
	// l'action lisent la même réponse, sur un matériel livré par `EquipmentLib::getByStructure()`.
	public function acceptDownload(): bool {

		$this->expects(['cMaintenanceScheduled', 'history']);
		return ($this['cMaintenanceScheduled']->notEmpty() or $this['history'] !== []);

	}

	// Le CSV ne porte que l'historique : sans lui, il se réduirait à sa ligne d'en-tête.
	public function acceptExport(): bool {

		$this->expects(['history']);
		return ($this['history'] !== []);

	}

	// La croix du planning ne passe que le relevé d'un compteur qu'on suit encore.
	public function acceptSkipReading(): bool {
		return ($this->acceptUpdate() and $this->hasMeter());
	}

	// Le décalage du planning ne vise, comme la croix, qu'un compteur qu'on suit encore. Les deux gestes
	// gardent chacun leur règle : une condition propre à la croix ne doit pas fermer le décalage.
	public function acceptPostponeReading(): bool {
		return ($this->acceptUpdate() and $this->hasMeter());
	}

	public function hasMeter(): bool {

		$this->expects(['meter']);
		return ($this['meter'] !== NULL);

	}

	/**
	 * Le formulaire a été envoyé sans qu'un matériel soit choisi dans la liste.
	 */
	protected function isToolUnselected(mixed $value, \PropertyList $l): bool {
		return ($l->origin !== 'api' and in_array($value, [NULL, ''], TRUE));
	}

	public static function properties(\PropertyList $l): void {

		// L'autocomplétion poste l'id d'un matériel de la structure, ou « new » quand la personne a
		// validé un nom qui n'y est pas (arbitrage Q5 A, « comme sur les variétés »). Le matériel à
		// créer attend dans `eToolNew`, que `EquipmentLib::follow()` enregistre dans la même
		// transaction : il n'existe pas si le suivi est refusé.
		$l->property('tool')
			// L'API crée un matériel par sa propre ressource, `POST tool` : « new » n'y est pas une valeur,
			// et le refus le dit avant que l'étape suivante ne cherche un gestionnaire connecté, que
			// l'API n'a jamais.
			->addBeforeCast('newApi', fn(mixed $value, \PropertyList $l): bool => ($value !== 'new' or $l->origin !== 'api'))
			// Un nom tapé sans être choisi dans la liste ne poste aucun matériel. Le formulaire dit alors
			// les deux gestes possibles, et « Valider » un nom neuf ne se propose qu'à qui peut créer le
			// matériel (`page/equipment.p.php`, `query`) : le refus ne nomme que ce que la liste offre.
			// L'API n'a pas de liste, son refus reste celui du contrôle.
			->addBeforeCast('unvalidated', fn(mixed $value, \PropertyList $l): bool => ($this->isToolUnselected($value, $l) === FALSE or $this['structure']->canManage() === FALSE))
			->addBeforeCast('unselected', fn(mixed $value, \PropertyList $l): bool => ($this->isToolUnselected($value, $l) === FALSE))
			->addBeforeCast('new', function(mixed &$value, \PropertyList $l): bool {

				$this['eToolNew'] = new \work\Tool();

				if($value !== 'new') {
					return TRUE;
				}

				$this->expects(['structure']);

				// Créer un matériel change « Mon matériel », que seul un gestionnaire règle
				// (`work\Tool::canWrite()`) : le planning suit un matériel qui existe.
				if($this['structure']->canManage() === FALSE) {
					return FALSE;
				}

				$this['eToolNew'] = \work\ToolLib::getNewTool($this['structure'], NULL);
				$this['eToolNew']->build(['name'], ['name' => $l->input['toolName'] ?? '']);

				if(
					$this['eToolNew']['name'] !== NULL and
					\work\Tool::model()
						->whereStructure($this['structure'])
						->whereName($this['eToolNew']['name'])
						->whereStatus(\work\Tool::ACTIVE)
						->exists()
				) {
					\work\Tool::fail('name.duplicate');
				}

				$value = NULL;

				return TRUE;

			})
			// Couvre le contrôle du schéma : un matériel à créer n'a pas encore d'id, et un matériel
			// choisi doit exister, appartenir à la structure et ne pas être un produit (un produit de
			// traitement ou de fertilisation porte une routine).
			->replaceCheck(function(\work\Tool $eTool): bool {

				if($this['eToolNew']->notEmpty()) {
					return TRUE;
				}

				if($eTool->empty()) {
					return FALSE;
				}

				$this->expects(['structure']);

				return (
					\work\Tool::model()
						->select(['structure', 'status', 'routineName'])
						->get($eTool) and
					$eTool['structure']->is($this['structure']) and
					$eTool['status'] === \work\Tool::ACTIVE and
					$eTool['routineName'] === NULL
				);

			})
			// Un matériel ne se suit qu'une fois.
			->addAfterCheck('followed', function(\work\Tool $eTool): bool {

				if($this['eToolNew']->notEmpty()) {
					return TRUE;
				}

				return Equipment::model()
					->whereTool($eTool)
					->whereStatus(Equipment::ACTIVE)
					->exists() === FALSE;

			});

		// Un matériel qui porte des valeurs de compteur (relevés, entretiens faits, « Prochaine fois » d'un
		// entretien) ne passe plus des heures aux kilomètres, ni l'inverse : elles n'auraient plus de sens
		// (décidé sous mandat sur la planche pf-t4-v3, Q3). Couper le compteur reste ouvert, et le
		// compteur ne revient qu'à l'unité de ces valeurs. Un matériel neuf n'en porte pas, celui qu'on
		// suit de nouveau se juge dans `EquipmentLib::follow()`, qui sait seul lequel c'est.
		$l->property('meter')
			->addAfterCheck('locked', function(?string $meter): bool {

				if($this->exists() === FALSE or $meter === NULL) {
					return TRUE;
				}

				$locked = EquipmentLib::getLockedMeter($this);

				return ($locked === NULL or $locked === $meter);

			});

		// Une immatriculation se lit en capitales, quelle que soit la casse de la saisie.
		$l->property('registration')
			->addAfterCast('upper', function(?string &$value): bool {

				if($value !== NULL) {
					$value = mb_strtoupper($value);
				}

				return TRUE;

			});

		// Le seul changement de statut est « Ne plus suivre » (`acceptUpdate()`).
		$l->property('status')
			->addAfterCheck('deleted', fn(string $status): bool => $status === Equipment::INACTIVE);

	}

}
?>
