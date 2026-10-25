<?php
namespace toolbox;

/**
 * Un pluviomètre : posé sur une zone de la structure, ou sur la ferme entière quand la zone est
 * vide (retour du 23/09). Il porte les relevés (`Rain`), et existe avant le premier : on
 * le pose, puis on y note la pluie (plan Gauge validé par M-Prod le 23/09).
 */
class Gauge extends GaugeElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Poser ou retirer un pluviomètre est du même ordre que relever la pluie (`Rain::canWrite()`) :
	// le droit du planning, jamais celui d'un observateur.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	// Importer un fichier de relevés est un import de données : il suit la règle des imports, réservés
	// à qui administre la structure (décision du 14/09), et non celle de la saisie (`canWrite()`).
	public function canManage(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	// Un pluviomètre supprimé ne revient pas : le rendre actif poserait un second pluviomètre au même
	// endroit, sans passer par l'étape `placed`. Seule la modification est bornée, un élément neuf n'a
	// pas encore de statut.
	public function acceptUpdate(): bool {

		$this->expects(['status']);
		return ($this['status'] === Gauge::ACTIVE);

	}

	public static function properties(\PropertyList $l): void {

		// « La ferme » poste une chaîne vide ; un lieu laissé sur l'invite ne poste rien, et le cast
		// ferait des deux le même élément vide.
		$l->property('zone')
			->addBeforeCast('chosen', fn(mixed $zone): bool => $zone !== NULL)
			->addAfterCheck('owned', function(\map\Zone $eZone): bool {

				// La ferme n'a pas de zone à vérifier.
				if($eZone->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				return (
					\map\Zone::model()
						->select('structure')
						->get($eZone) and
					$eZone['structure']->is($this['structure'])
				);

			})
			// Un lieu ne porte qu'un pluviomètre actif : un second ferait deux cartes du même nom. Un
			// pluviomètre qui change de lieu ne se compte pas lui-même.
			->addAfterCheck('placed', function(\map\Zone $eZone, \PropertyList $l): bool {

				$this->expects(['structure']);

				return Gauge::model()
					->whereStructure($this['structure'])
					->whereId('!=', $this, if: $l->for === 'update')
					->whereZone($eZone->empty() ? NULL : $eZone)
					->whereStatus(Gauge::ACTIVE)
					->count() === 0;

			});

		// Le seul changement de statut est la suppression (`acceptUpdate()`).
		$l->property('status')
			->addAfterCheck('deleted', fn(string $status): bool => $status === Gauge::INACTIVE);

	}

}
?>
