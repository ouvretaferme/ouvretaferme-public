<?php
namespace learning;

/**
 * Une séance d'une date (lot date-mixte) : un créneau d'un seul jour, en présentiel avec son lieu
 * ou en visio. Une date en porte une ou plusieurs, et c'est sur elles que se lisent les jours, les
 * horaires, le lieu, le besoin d'un lien et la marque de la formation (`CourseLib::synchronizeType()`).
 * Elle ne s'écrit qu'avec sa date, dont le formulaire poste toutes les séances à la fois
 * (`Date::properties()`, propriété `sequences`) : ses droits sont ceux de sa date.
 */
class DateSequence extends DateSequenceElement {

	/**
	 * Une formation de réseau se tient sur quelques jours, au plus deux séances par jour : au-delà,
	 * ce n'est plus une date mais un cycle, que le réseau publie en plusieurs dates.
	 */
	const MAX_BY_DATE = 20;

	public function canRead(): bool {

		$this->expects(['date']);

		return $this['date']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['date']);

		return $this['date']->canWrite();

	}

	public function getDay(): string {

		$this->expects(['startAt']);

		return substr($this['startAt'], 0, 10);

	}

	/**
	 * La durée de la séance, comptée sur une journée fictive : le passage à l'heure d'hiver ne doit
	 * pas ajouter une heure à une séance qui le traverse.
	 */
	public function getMinutes(): int {

		$this->expects(['startAt', 'endAt']);

		$minutes = (int)((strtotime('2000-01-01 '.substr($this['endAt'], 11)) - strtotime('2000-01-01 '.substr($this['startAt'], 11))) / 60);

		return max(0, $minutes);

	}

	public static function properties(\PropertyList $l): void {

		/**
		 * Le formulaire poste le jour et l'heure de début, `startAt[i][date]` et `startAt[i][time]`,
		 * et l'heure de fin seule, `endAt[i][time]`, une séance tenant dans une journée : la fin prend
		 * le jour du début, lu dans l'entrée de la ligne (`$l->input`), avant que le début soit
		 * construit. Une chaîne passe telle quelle, pour les tests et une écriture par l'API. Le
		 * navigateur poste l'heure sans secondes, que le contrôle du schéma exige.
		 */
		$assemble = function(mixed &$value, ?string $date): void {

			$time = $value['time'] ?? '';

			if(
				is_string($date) === FALSE or
				is_string($time) === FALSE or
				$date === '' or
				$time === ''
			) {
				$value = NULL;
				return;
			}

			$value = $date.' '.$time.(strlen($time) === 5 ? ':00' : '');

		};

		$l->property('startAt')
			->addBeforeCast('assembled', function(mixed &$value) use($assemble): bool {

				if(is_array($value)) {
					$assemble($value, $value['date'] ?? '');
				}

				return TRUE;

			});

		// La fin se juge sur le début construit juste avant (`DateSequenceLib` range `startAt`
		// devant) ; un début refusé ne laisse ni jour à prendre ni rien à comparer, et la fin ne
		// dit pas un second refus. Une séance ne passe pas minuit.
		$l->property('endAt')
			->addBeforeCast('assembled', function(mixed &$value, \PropertyList $l) use($assemble): bool {

				$l->skipIfInvalid('startAt');

				if(is_array($value)) {
					$startAt = $l->input['startAt'] ?? NULL;
					$assemble($value, is_array($startAt) ? ($startAt['date'] ?? '') : '');
				}

				return TRUE;

			})
			->addAfterCheck('consistent', function(string $endAt, \PropertyList $l): bool {

				$l->skipIfInvalid('startAt');

				return (
					$endAt > $this['startAt'] and
					substr($endAt, 0, 10) === substr($this['startAt'], 0, 10)
				);

			});

		// Le lieu d'une séance en présentiel est exigé ; celui d'une séance en visio est effacé à
		// l'écriture (`DateSequenceLib::create()`), le champ restant posté quand la ligne change de mode.
		$l->property('place')
			->addAfterCheck('required', function(?string $place, \PropertyList $l): bool {

				$l->skipIfInvalid('mode');

				return (
					$place !== NULL or
					$this['mode'] !== DateSequence::ONSITE
				);

			});

	}

}
?>
