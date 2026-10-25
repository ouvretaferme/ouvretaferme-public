<?php
namespace toolbox;

/**
 * Un compteur d'eau : il porte un nom, le nombre de chiffres qu'il affiche, et ses relevés
 * (`WaterReading`). Il existe avant le premier : on l'ajoute, puis on le relève.
 */
class WaterMeter extends WaterMeterElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Ajouter, régler ou retirer un compteur est du même ordre que le relever (`WaterReading::canWrite()`) :
	// le droit du planning, jamais celui d'un observateur.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	// Un compteur supprimé ne revient pas, comme un pluviomètre (`Gauge::acceptUpdate()`). Seule la
	// modification est bornée, un élément neuf n'a pas encore de statut.
	public function acceptUpdate(): bool {

		$this->expects(['status']);
		return ($this['status'] === WaterMeter::ACTIVE);

	}

	/**
	 * Ce que le compteur affiche au plus, à une unité près : le relevé d'un compteur à 5 chiffres reste
	 * sous 100 000, et c'est aussi ce qu'il a compté quand il repasse par zéro.
	 */
	public function getCapacity(): int {

		$this->expects(['digits']);
		return 10 ** $this['digits'];

	}

	public static function properties(\PropertyList $l): void {

		// Deux compteurs actifs du même nom feraient deux onglets qu'on ne distingue pas. Un compteur
		// qui garde son nom ne se compte pas lui-même.
		$l->property('name')
			->addAfterCheck('duplicate', function(string $name, \PropertyList $l): bool {

				$this->expects(['structure']);

				return WaterMeter::model()
					->whereStructure($this['structure'])
					->whereId('!=', $this, if: $l->for === 'update')
					->whereName($name)
					->whereStatus(WaterMeter::ACTIVE)
					->count() === 0;

			});

		// Un compteur ne perd pas de chiffres sous ses relevés : celui qui a affiché 12 482 en a au
		// moins cinq. Un compteur qu'on pose à la place d'un autre (origine `change`) n'a pas encore de
		// relevé, et ceux de l'ancien ne le bornent pas.
		$l->property('digits')
			->addAfterCheck('readings', function(int $digits, \PropertyList $l): bool {

				if($l->for !== 'update' or $l->origin === 'change') {
					return TRUE;
				}

				return (WaterReadingLib::getHighest($this) < 10 ** $digits);

			});

		// Le seul changement de statut est la suppression (`acceptUpdate()`).
		$l->property('status')
			->addAfterCheck('deleted', fn(string $status): bool => $status === WaterMeter::INACTIVE);

	}

}
?>
