<?php
namespace plant;

class Forecast extends ForecastElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'plant' => ['fqn', 'name', 'vignette']
		];

	}

	/**
	 * Une prévision n'accepte d'être écrite que si sa ferme l'accepte.
	 *
	 * ⚠️ Sans cette délégation, la prévision d'une espèce d'une ferme école s'écrit depuis n'importe quel compte
	 * connecté : `Structure::canAnalyzeProduction()` ouvre la ferme école, et le droit de lire passe par la même porte
	 * que celui d'écrire. Mesuré le 2026-09-05.
	 *
	 * La règle est posée en `accept*` et non en `can*` parce qu'elle décrit l'état de la ferme et
	 * non le rôle du lecteur : `ModuleApi` n'appelle que les `accept*` (`framework/core/Api.php:446`,
	 * `572`, `640`). Même forme que `selling\Sale::acceptWrite()`.
	 */
	public function acceptWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->acceptWrite();

	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canAnalyzeProduction();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

	/**
	 * Le chiffre d'affaires d'un volume, à la répartition et aux prix du prévisionnel. Le prix aux
	 * particuliers est celui qu'ils paient, TVA comprise : `$privateVatRate` l'en retire pour qui a besoin
	 * d'un montant hors taxes (le prévisionnel financier d'une structure redevable).
	 */
	public function getSales(float $harvest, float $privateVatRate = 0.0): float {

		$this->expects(['privatePrice', 'privatePart', 'proPrice', 'proPart']);

		return ($harvest * $this['proPrice'] * $this['proPart'] / 100) +
			($harvest * $this['privatePrice'] * $this['privatePart'] / 100 / (1 + $privateVatRate / 100));

	}


	public static function properties(\PropertyList $l): void {

		$l->property('season')
			->replaceCheck(function(int $season): bool {

				$this->expects(['structure']);

				return $this['structure']->conf(\map\Configuration::class)->checkSeason($season);

			});

		$l->property('plant')
			->addAfterCheck('owned', function(\plant\Plant $ePlant): bool {

				$this->expects(['structure']);

				return \plant\Plant::model()
					->whereStructure($this['structure'])
					->exists($ePlant);

			});

		$l->property('proPart')
			->addAfterCheck('consistency', function(int $part, \PropertyList $l): bool {

				if(
					$l->hasProperty('privatePart') and
					$l->hasProperty('proPart')
				) {
					return ($this['privatePart'] + $part === 100);
				} else {
					return TRUE;
				}

			});

	}

}
?>
