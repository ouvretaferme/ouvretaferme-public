<?php
namespace hr;

class Presence extends PresenceElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Une période sans jour de départ n'est jamais finie, et le jour de départ en fait encore partie.
	 */
	public function isOpenOn(string $date): bool {

		$this->expects(['to']);

		return ($this['to'] === NULL or $this['to'] >= $date);

	}

	public function isComing(): bool {

		$this->expects(['from']);

		return ($this['from'] > \Clock::date());

	}

	public static function getSelection(): array {

		return parent::getSelection() + [
			'user' => [
				'vignette',
				'firstName', 'lastName',
				'status'
			],
		];

	}

	public static function isDatePresent(\Collection $cPresence, string $date): Presence {

		foreach($cPresence as $ePresence) {

			if($ePresence['to'] === NULL) {
				if($date >= $ePresence['from']) {
					return $ePresence;
				}
			} else {
				if($date >= $ePresence['from'] and $date <= $ePresence['to']) {
					return $ePresence;
				}
			}

		}

		return new Presence();

	}

	/**
	 * Si la journée de `$date` se saisit et compte, d'après toutes les périodes de la personne (`$cPresence`) : dans
	 * l'une d'elles, ou à toute date pour qui n'en a aucune, comme un gérant qui saisit son temps sans être au
	 * planning. La même règle que `PresenceLib::isWorkable()`, sur des périodes déjà lues.
	 */
	public static function isDateWorkable(\Collection $cPresence, string $date): bool {

		return (
			$cPresence->empty() or
			self::isDatePresent($cPresence, $date)->notEmpty()
		);

	}

	/**
	 * Un jour passé où la personne était présente et que son contrat attend (`$cPresence`, chaque période avec son
	 * `eContract`), sans journée saisie (`$worked`), ni absence posée (`$absent`), ni férié (`$holiday`) : un risque
	 * d'erreur sur la paie, à vérifier. Le jour en cours n'est pas encore fini. Des jours variables n'attendent aucun
	 * jour en particulier, quand l'absence et le solde d'un annualisé retombent sur le lundi au vendredi : seul ce que
	 * le contrat écrit se signale.
	 */
	public static function isDateMissing(\Collection $cPresence, string $date, bool $worked, bool $absent, bool $holiday): bool {

		if(
			$worked or
			$absent or
			$holiday or
			$date >= \Clock::date()
		) {
			return FALSE;
		}

		$ePresence = self::isDatePresent($cPresence, $date);

		if($ePresence->empty()) {
			return FALSE;
		}

		$eContract = $ePresence['eContract'];

		if(
			$eContract->empty() or
			$eContract['hasVariableDays'] or
			$eContract['days'] === NULL
		) {
			return FALSE;
		}

		return $eContract->isDayWorked($date);

	}

	/**
	 * Si chaque jour de `$start` à `$stop` est dans une des périodes `$cPresence`, deux périodes qui se suivent sans
	 * jour entre elles valant une seule, comme un contrat qui change au premier du mois.
	 */
	public static function isRangePresent(\Collection $cPresence, string $start, string $stop): bool {

		$cPresenceSorted = clone $cPresence;
		$cPresenceSorted->sort(['from' => SORT_ASC]);

		$coveredUntil = $start;

		foreach($cPresenceSorted as $ePresence) {

			if($ePresence['from'] > $coveredUntil) {

				// Une période qui finit avant le jour attendu ne crée pas de trou : elle est seulement antérieure.
				if($ePresence['to'] !== NULL and $ePresence['to'] < $coveredUntil) {
					continue;
				}

				return FALSE;

			}

			if($ePresence['to'] === NULL) {
				return TRUE;
			}

			$coveredUntil = max($coveredUntil, date('Y-m-d', strtotime($ePresence['to'].' + 1 DAY')));

			if($coveredUntil > $stop) {
				return TRUE;
			}

		}

		return FALSE;

	}

	public static function isWeekPresent(\Collection $cPresence, string|array $weeks): Presence {

		$intervals = [];

		foreach((array)$weeks as $week) {
			$intervals[] = [week_date_starts($week), week_date_ends($week)];
		}

		foreach($cPresence as $ePresence) {

			if($ePresence['to'] === NULL) {
				foreach($intervals as [, $stop]) {
					if($stop >= $ePresence['from']) {
						return $ePresence;
					}
				}
			} else {
				foreach($intervals as [$start, $stop]) {
					if($stop >= $ePresence['from'] and $start <= $ePresence['to']) {
						return $ePresence;
					}
				}
			}

		}

		return new Presence();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('user')
			->addAfterCheck('teammate', function(\user\User $eUser): bool {

				$this->expects(['structure']);

				return TeammateLib::isTeammate($this['structure'], $eUser);

			});

		$l->property('to')
			->addAfterCheck('consistency', function(?string $to, \PropertyList $l): bool {

				// Hors création, une arrivée qui ne se poste pas (départ, jour de départ seul par l'API) est celle
				// de la période enregistrée, contre laquelle le départ se contrôle. À la création, elle a déjà été
				// refusée.
				if(
					$l->isBuilt('from') === FALSE and
					$l->for === 'create'
				) {
					return TRUE;
				}

				$this->expects([
					'from'
				]);

				return (
					$to === NULL or
					$to >= $this['from']
				);

			})
			->addAfterCheck('present', function(?string $to, \PropertyList $l): bool {

				// Hors création, une arrivée qui ne se poste pas est celle de la période enregistrée, et un jour
				// de départ posté seul peut la prolonger jusqu'à la suivante : le chevauchement se contrôle aussi.
				// À la création, elle a déjà été refusée.
				if(
					$l->isBuilt('from') === FALSE and
					$this->exists() === FALSE
				) {
					return TRUE;
				}

				// Un user refusé laisse l'élément sans utilisateur : il n'y a plus de présence à
				// comparer, et l'erreur est déjà remontée sur cette propriété.
				$l->skipIfInvalid('user');

				$this->expects([
					'structure', 'user',
					'from'
				]);

				// Une personne sans compte que le même geste crée (`TeammateLib::create()`) n'a pas encore
				// d'identifiant, donc aucune période à chevaucher.
				if($this['user']->exists() === FALSE) {
					return TRUE;
				}

				if($this->exists()) {
					Presence::model()->whereId('!=', $this['id']);
				}

				return PresenceLib::isPresentBetween($this['structure'], $this['user'], $this['from'], $to) === FALSE;

			});

	}

}
?>