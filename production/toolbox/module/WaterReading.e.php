<?php
namespace toolbox;

/**
 * Un relevé de compteur d'eau : ce que le compteur (`WaterMeter`) affiche un jour donné, en m³. Un
 * second relevé du même jour remplace le premier (`WaterReadingLib::create()`). Le relevé du jour où
 * le compteur a été remplacé (`changed`) porte en plus ce que l'ancien affichait à la dépose
 * (`WaterReadingLib::createChange()`).
 */
class WaterReading extends WaterReadingElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Relever un compteur d'eau est du même ordre que relever la pluie (`Rain::canWrite()`) : le droit
	// du planning.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	// Un relevé se corrige ou se supprime sur un compteur qui existe encore. La création ne passe pas
	// par là : le compteur n'y est pas encore construit, l'étape `owned` le juge.
	public function acceptUpdate(): bool {

		$this->expects(['meter' => ['status']]);
		return ($this['meter']['status'] === WaterMeter::ACTIVE);

	}

	public function acceptDelete(): bool {
		return $this->acceptUpdate();
	}

	public static function properties(\PropertyList $l): void {

		// Un compteur supprimé garde ses relevés, mais n'en reçoit plus.
		$l->property('meter')
			->addAfterCheck('owned', function(WaterMeter $eWaterMeter): bool {

				$this->expects(['structure']);

				return (
					WaterMeter::model()
						->select(['structure', 'status', 'digits'])
						->get($eWaterMeter) and
					$eWaterMeter['structure']->is($this['structure']) and
					$eWaterMeter['status'] === WaterMeter::ACTIVE
				);

			});

		// Un relevé dit ce que le compteur affiche : un jour qui n'est pas encore arrivé n'en a pas.
		$l->property('date')
			->addAfterCheck('future', fn(string $date): bool => $date <= \Clock::date());

		// Le cast du cadriciel rend 0 pour ce qui n'est pas un nombre, et 0 est un relevé : l'étape
		// `number` le refuse avant lui, sinon « abc » poserait un compteur à zéro.
		//
		// Un relevé plus bas que le précédent n'est pas refusé (arbitrage Q2 C de la planche de l'eau) :
		// c'est un compteur qui a fait le tour, enregistré puis signalé. Seul le nombre de chiffres du
		// compteur le borne, celui du compteur en place ce jour-là (`WaterReadingLib::getDigits()`). Le
		// relevé d'un changement est ce que le nouveau compteur affiche à la pose : il se borne par les
		// chiffres que le formulaire vient de poser sur le compteur (origine `change`).
		$l->property('value')
			->addBeforeCast('number', fn(mixed $value): bool => ($value === NULL or $value === '' or is_numeric($value)))
			->addAfterCheck('digits', function(float $value, \PropertyList $l): bool {

				if($l->origin === 'change') {
					return ($value < $this['meter']->getCapacity());
				}

				if($l->for !== 'update') {
					$l->skipIfNotBuilt('meter');
					$l->skipIfNotBuilt('date');
				}

				return ($value < 10 ** WaterReadingLib::getDigits($this['meter'], $this['date']));

			});

		// Ce que l'ancien compteur affichait à la dépose, borné par ses propres chiffres : ceux que le
		// relevé garde déjà quand il se corrige, ceux du compteur en place ce jour-là quand le
		// changement se déclare. Le laisser vide dit qu'on ne l'a pas relevé.
		$l->property('changedValue')
			->addBeforeCast('number', fn(mixed $value): bool => ($value === NULL or $value === '' or is_numeric($value)))
			->addAfterCheck('digits', function(?float $value, \PropertyList $l): bool {

				if($value === NULL) {
					return TRUE;
				}

				if($l->for === 'update') {
					return ($value < 10 ** $this['changedDigits']);
				}

				$l->skipIfNotBuilt('date');

				return ($value < 10 ** WaterReadingLib::getDigitsReplaced($this['meter'], $this['date']));

			});

	}

}
?>
