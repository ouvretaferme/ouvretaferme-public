<?php
namespace toolbox;

/**
 * Un relevé du compteur d'un matériel, en heures ou en kilomètres : ce qu'il affiche un jour donné. Un second relevé du
 * même jour remplace le premier (`EquipmentReadingLib::create()`).
 */
class EquipmentReading extends EquipmentReadingElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	// Relever un compteur est du même ordre que relever la pluie (`Rain::canWrite()`) : le droit du
	// planning.
	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	// Un relevé se corrige ou se supprime sur un matériel suivi, et se corrige dans l'unité de son
	// compteur. La création ne passe pas par là : le matériel n'y est pas encore construit, l'étape
	// `owned` le juge.
	public function acceptUpdate(): bool {

		$this->expects(['equipment' => ['status', 'meter']]);
		return ($this['equipment']['status'] === Equipment::ACTIVE and $this['equipment']->hasMeter());

	}

	public function acceptDelete(): bool {

		$this->expects(['equipment' => ['status']]);
		return ($this['equipment']['status'] === Equipment::ACTIVE);

	}

	/**
	 * Ce qu'un compteur accepte avant d'être converti : un entier, écrit en nombre ou en chiffres. Une
	 * valeur absente passe, la suite la juge (schéma, `required`) ; un nombre négatif aussi, que le
	 * schéma refuse sur sa borne.
	 */
	public static function isMeterValue(mixed $value): bool {

		return (
			$value === NULL or
			$value === '' or
			is_int($value) or
			(is_string($value) and preg_match('/^-?[0-9]+$/', $value) === 1)
		);

	}

	public static function properties(\PropertyList $l): void {

		// Un matériel qu'on ne suit plus, ou qui n'a pas de compteur, ne reçoit pas de relevé.
		$l->property('equipment')
			->addAfterCheck('owned', function(Equipment $eEquipment): bool {

				$this->expects(['structure']);

				return (
					Equipment::model()
						->select(['structure', 'status', 'meter'])
						->get($eEquipment) and
					$eEquipment['structure']->is($this['structure']) and
					$eEquipment['status'] === Equipment::ACTIVE and
					$eEquipment->hasMeter()
				);

			});

		// Un relevé dit ce que le compteur affiche : un jour qui n'est pas encore arrivé n'en a pas.
		$l->property('date')
			->addAfterCheck('future', fn(string $date): bool => $date <= \Clock::date());

		// Un compteur ne recule pas (arbitrage Q7 de la planche pf-t4-v2) : le relevé se place entre
		// celui d'avant et celui d'après. Le relevé du même jour ne compte pas, il est remplacé. Un relevé
		// hors de cet ordre passe quand la personne l'a confirmé (`EquipmentReadingLib::isConfirmed()`).
		//
		// À la création, sans matériel ni date construits, l'étape passe sans rien juger, et non par
		// `skipIfNotBuilt()`, qui abandonnerait la propriété entière : le premier relevé de « Suivre un
		// matériel » ne construit que `value`, le matériel n'existant pas encore, et `EquipmentLib::follow()`
		// le place une fois qu'il sait quel matériel le reçoit. Un relevé corrigé depuis l'historique
		// (`update`) porte déjà son matériel et son jour, et se juge toujours.
		//
		// Le cast du cadriciel rend 0 pour ce qui n'est pas un nombre, et 0 est un compteur : l'étape
		// `number` le refuse avant lui, sinon « abc » poserait un relevé à zéro.
		$l->property('value')
			->addBeforeCast('number', fn(mixed $value): bool => EquipmentReading::isMeterValue($value))
			->addAfterCheck('backwards', function(int $value, \PropertyList $l): bool {

				if(
					EquipmentReadingLib::isConfirmed($l->input) or
					($l->for !== 'update' and $l->isBuilt(['equipment', 'date']) === FALSE)
				) {
					return TRUE;
				}

				return EquipmentReadingLib::acceptBefore($this['equipment'], $this['date'], $value);

			})
			->addAfterCheck('ahead', function(int $value, \PropertyList $l): bool {

				if(
					EquipmentReadingLib::isConfirmed($l->input) or
					($l->for !== 'update' and $l->isBuilt(['equipment', 'date']) === FALSE)
				) {
					return TRUE;
				}

				return EquipmentReadingLib::acceptAfter($this['equipment'], $this['date'], $value);

			});

	}

}
?>
