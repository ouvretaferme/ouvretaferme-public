<?php
namespace hr;

class Absence extends AbsenceElement {

	/**
	 * Le gérant lit toutes les absences de la structure, motif compris. Une personne au planning ne lit que les
	 * siennes : le motif d'une absence peut être une donnée de santé (`SICKNESS`, `WORK_ACCIDENT`, `PARENTAL`).
	 * Ce que voit le reste de l'équipe, « Absent » sans motif, ne passe pas par ici (`AbsenceLib::fillUsers()`).
	 */
	public function canRead(): bool {

		$this->expects(['structure', 'user']);

		return (
			$this['structure']->canManage() or
			$this->isOwn()
		);

	}

	/**
	 * Le gérant saisit pour toute l'équipe. Une personne au planning demande pour elle-même, tant qu'une de ses
	 * périodes court aujourd'hui ou commence plus tard : une personne partie reste dans l'équipe
	 * (`TeammateLib::isTeammate()`), c'est sa période qui dit qu'elle n'y travaille plus.
	 */
	public function canCreate(): bool {

		$this->expects(['structure', 'user']);

		return (
			$this['structure']->canManage() or
			(
				$this->isOwn() and
				PresenceLib::isPresentBetween($this['structure'], $this['user'], \Clock::date(), NULL)
			)
		);

	}

	/**
	 * Une personne au planning ne retouche que sa demande tant qu'elle attend une réponse : c'est la part qui
	 * lui revient, une absence validée ou refusée étant celle du gérant.
	 */
	public function canWrite(): bool {

		$this->expects(['structure', 'user', 'status']);

		return (
			$this['structure']->canManage() or
			(
				$this->isOwn() and
				$this['status'] === Absence::PENDING
			)
		);

	}

	/**
	 * RH éteinte, une absence ne se lit ni ne s'écrit plus, même par son id : les `accept*` d'écriture en héritent
	 * (`framework/core/Data.php`), et ModulePage interroge le droit avant l'état, si bien que l'état d'une autre
	 * structure ne se lit jamais.
	 */
	public function acceptRead(): bool {

		$this->expects(['structure' => ['type']]);

		return $this['structure']->acceptHr();

	}

	public function canValidate(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

	public function acceptValidate(): bool {

		$this->expects(['status']);

		return (
			$this->acceptWrite() and
			$this['status'] === Absence::PENDING
		);

	}

	public function assertValidate(): bool {
		return $this->acceptValidate() and $this->canValidate();
	}

	/**
	 * Toute personne au planning, gérant compris, lit l'onglet Absences du planning : celles de l'équipe, puis
	 * les siennes (décidé le 18/10).
	 */
	public function canPlanning(): bool {

		$this->expects(['structure']);

		return $this['structure']->canPlanningLimited();

	}

	public function acceptPlanning(): bool {
		return $this->acceptRead();
	}

	public function assertPlanning(): bool {
		return $this->acceptPlanning() and $this->canPlanning();
	}

	protected function isOwn(): bool {

		return (
			$this['structure']->canPlanningLimited() and
			$this['user']->isOnline()
		);

	}

	const int MAX_YEARS = 3;

	/**
	 * Une absence se saisit pour une personne dont une période court encore, ou a fini il y a au plus trois mois
	 * (décidé le 19/10) : assez pour régulariser un départ récent, sans proposer toute l'équipe passée.
	 */
	const int PRESENCE_MONTHS = 3;

	public static function getPresenceSince(): string {
		return date('Y-m-d', \Clock::shift('-'.self::PRESENCE_MONTHS.' months'));
	}

	public function isPending(): bool {
		return ($this['status'] === Absence::PENDING);
	}

	public static function getSelection(): array {

		return parent::getSelection() + [
			// `acceptRead()` lit le type de la structure par `acceptHr()`.
			'structure' => ['type'],
			'user' => [
				'firstName', 'lastName', 'vignette'
			],
		];

	}

	public static function isDateAbsent(\Collection $cAbsence, string $date): Absence {

		foreach($cAbsence as $eAbsence) {

			if($date >= substr($eAbsence['from'], 0, 10) and $date <= substr($eAbsence['to'], 0, 10)) {
				return $eAbsence;
			}

		}

		return new Absence();

	}

	public static function isWeekAbsent(\Collection $cAbsence, string|array $weeks): Absence {

		$intervals = [];

		foreach((array)$weeks as $week) {
			$intervals[] = [week_date_starts($week), week_date_ends($week)];
		}

		foreach($cAbsence as $eAbsence) {

			foreach($intervals as [$start, $stop]) {
				if($stop >= substr($eAbsence['from'], 0, 10) and $start <= substr($eAbsence['to'], 0, 10)) {
					return $eAbsence;
				}
			}

		}

		return new Absence();

	}

	/**
	 * La part du jour `$date` que couvre l'absence, ou NULL quand elle le couvre en entier. Une absence à la
	 * journée ne lit une demi-journée que d'une borne à midi (`hr.yml`) : une autre heure vient d'une saisie
	 * d'avant les demi-journées, qui valait la journée. Une absence à l'heure rend ses deux heures.
	 *
	 * @return array{from: ?string, to: ?string}|null `from` NULL pour le début du jour, `to` NULL pour sa fin
	 */
	public function getPartOn(string $date): ?array {

		$this->expects(['from', 'to', 'unit']);

		$first = (substr($this['from'], 0, 10) === $date);
		$last = (substr($this['to'], 0, 10) === $date);

		if($this['unit'] === Absence::HOUR) {
			return [
				'from' => substr($this['from'], 11, 5),
				'to' => substr($this['to'], 11, 5)
			];
		}

		$from = ($first and substr($this['from'], 11) === AbsenceLib::NOON) ? substr(AbsenceLib::NOON, 0, 5) : NULL;
		$to = ($last and substr($this['to'], 11) === AbsenceLib::NOON) ? substr(AbsenceLib::NOON, 0, 5) : NULL;

		if($from === NULL and $to === NULL) {
			return NULL;
		}

		return [
			'from' => $from,
			'to' => $to
		];

	}

	/**
	 * Le formulaire poste chaque borne en morceaux, que `from` et `to` réunissent en une date et une heure avant
	 * la conversion : la date et la demi-journée pour une absence à la journée, la date et l'heure pour une
	 * absence à l'heure, dont la fin reprend le jour du début. Une borne déjà écrite en entier, comme l'API et
	 * les décors la postent, passe telle quelle. L'unité se lit sur l'élément et non sur l'entrée : l'API ne la
	 * construit pas, et une borne qu'elle posterait en morceaux se compose à l'unité qui sera enregistrée.
	 */
	public static function properties(\PropertyList $l): void {

		$l->property('user')
			->addAfterCheck('present', function(\user\User $eUser): bool {

				$this->expects(['structure']);

				// Le panneau du gérant ne propose que cette population (`TeammateLib::getUsersForAbsence()`) : une
				// personne postée hors de la liste est refusée ici, l'API passant par la même construction.
				return PresenceLib::isPresentBetween($this['structure'], $eUser, self::getPresenceSince(), NULL);

			});

		$l->property('from')
			->addBeforeCast('compose', function(mixed &$value, \PropertyList $l): bool {

				if(is_array($value)) {
					$value = AbsenceLib::compose($this['unit'], $value['date'] ?? NULL, $value['half'] ?? NULL, $value['time'] ?? NULL, TRUE);
				}

				return TRUE;

			});

		$l->property('to')
			->addBeforeCast('compose', function(mixed &$value, \PropertyList $l): bool {

				if(is_array($value)) {

					$date = ($this['unit'] === Absence::HOUR) ? ($l->input['from']['date'] ?? NULL) : ($value['date'] ?? NULL);

					$value = AbsenceLib::compose($this['unit'], $date, $value['half'] ?? NULL, $value['time'] ?? NULL, FALSE);

				}

				return TRUE;

			})
			->addAfterCheck('consistency', function(string $to): bool {

				$this->expects([
					'from'
				]);

				return ($to > $this['from']);

			})
			->addAfterCheck('tooLong', function(string $to): bool {

				$this->expects([
					'from'
				]);

				// Trois ans au plus (décidé le 17/10). La comparaison porte sur des horodatages : au-delà
				// de l'an 9999, une date écrite se range mal.
				return (strtotime($to) <= strtotime($this['from'].' + '.self::MAX_YEARS.' YEAR'));

			})
			->addAfterCheck('oneDay', function(string $to, \PropertyList $l): bool {

				$this->expects([
					'from'
				]);

				// `unit` ne se construit que par la page, qui la place avant les bornes ; une absence construite sans
				// elle, comme les décors de scène, reçoit la journée de `AbsenceLib::create()`, qui ne passe qu'après
				// la construction.
				return (
					($this['unit'] ?? Absence::DAY) !== Absence::HOUR or
					substr($to, 0, 10) === substr($this['from'], 0, 10)
				);

			})
			// Une absence ne se pose que sur des jours où la personne est au planning : hors de ses périodes, il n'y a
			// pas de travail dont s'absenter, ni de paie qui la compterait (`RecapLib::fillByMonth()`).
			->addAfterCheck('outOfPresence', function(string $to, \PropertyList $l): bool {

				// Une personne ou un début refusés ont déjà leur erreur, et `to` doit tout de même recevoir sa valeur :
				// l'étape `tooLong` de `duration` la lit. `skipIfInvalid()` l'abandonnerait avant le set.
				if($l->isInvalid('user') or $l->isInvalid('from')) {
					return TRUE;
				}

				$this->expects([
					'structure', 'user',
					'from'
				]);

				return PresenceLib::isPresentThroughout($this['structure'], $this['user'], substr($this['from'], 0, 10), substr($to, 0, 10));

			});

		$l->property('duration')
			// Une durée de 0 est refusée (décidé le 19/10). La borne du schéma est inclusive et ne dit pas
			// « strictement positif », d'où l'étape. Les absences déjà à 0 restent telles quelles, sans reprise : leur
			// durée se corrige à leur prochaine modification.
			->addAfterCheck('positive', fn(float $duration): bool => ($duration > 0))
			->addAfterCheck('tooLong', function(float $duration, \PropertyList $l): bool {

				// Des bornes refusées laissent l'élément sans période à comparer, et l'erreur est déjà remontée sur
				// elles.
				$l->skipIfInvalid('from');
				$l->skipIfInvalid('to');

				$this->expects([
					'from', 'to'
				]);

				// La durée se saisit (décidé le 19/10) et ne se compare qu'à ce que la période contient
				// au plus : les heures écoulées pour une absence à l'heure, les jours du calendrier sinon. Ce n'est
				// pas un calcul de la durée, qui dépend du temps partiel ; c'est le plafond qu'aucun contrat ne
				// dépasse. `unit` manque aux absences construites sans elle, qui reçoivent la journée.
				if(($this['unit'] ?? Absence::DAY) === Absence::HOUR) {
					return ($duration <= (strtotime($this['to']) - strtotime($this['from'])) / 3600);
				}

				// Arrondi : un changement d'heure fait d'un jour 23 ou 25 heures.
				$days = round((strtotime(substr($this['to'], 0, 10)) - strtotime(substr($this['from'], 0, 10))) / 86400) + 1;

				return ($duration <= $days);

			});

	}

}
?>
