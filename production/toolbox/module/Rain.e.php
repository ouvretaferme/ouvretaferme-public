<?php
namespace toolbox;

/**
 * Un relevé de pluviomètre : le total de pluie d'un jour sur un pluviomètre (`Gauge`), en mm.
 */
class Rain extends RainElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Relever la pluie est du même ordre que noter une intervention : le droit est celui du planning
	// (`series\Task::canWrite()`), et non `Structure::canWrite()`, qui répond « est membre » et laissait
	// écrire un observateur (retour du 29/09 sur la 368).
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public static function properties(\PropertyList $l): void {

		// Un pluviomètre supprimé garde ses relevés, mais n'en reçoit plus.
		$l->property('gauge')
			->addAfterCheck('owned', function(Gauge $eGauge): bool {

				$this->expects(['structure']);

				return (
					Gauge::model()
						->select(['structure', 'status'])
						->get($eGauge) and
					$eGauge['structure']->is($this['structure']) and
					$eGauge['status'] === Gauge::ACTIVE
				);

			});

		// Un relevé dit la pluie tombée : un jour qui n'est pas encore arrivé n'en a pas.
		$l->property('date')
			->addAfterCheck('future', function(string $date): bool {

				return ($date <= \Clock::date());

			});

		// Un relevé ajouté au jour peut être négatif, pour retirer des mm saisis en trop (retour de
		// du 23/09) : le schéma juge alors sa valeur absolue. Le total d'un jour, lui, reste
		// positif, et c'est la modification qui le porte.
		$l->property('rain')
			->replaceCheck(function(?float $rain): bool {

				if($this->exists()) {
					return Rain::model()->check('rain', $rain);
				} else {
					return ($rain !== NULL and Rain::model()->check('rain', abs($rain)));
				}

			})
			->addAfterCheck('exceeds', function(float $rain, \PropertyList $l): bool {

				if($this->exists() or $rain > 0) {
					return TRUE;
				}

				$l->skipIfNotBuilt('gauge');
				$l->skipIfNotBuilt('date');

				return (-$rain <= RainLib::getDay($this['gauge'], $this['date']));

			});

	}

}
?>
