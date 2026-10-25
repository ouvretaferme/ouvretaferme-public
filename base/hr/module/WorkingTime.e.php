<?php
namespace hr;

class WorkingTime extends WorkingTimeElement {

	/**
	 * Le planning complet lit la journée de chacun, arrivée, départ et pause compris (décidé le 17/10) :
	 * c'est ce qui organise le travail de l'équipe.
	 */
	public function canRead(): bool {

		$this->expects(['structure', 'user']);

		return (
			$this['structure']->canPlanning() or
			($this['structure']->canPlanningLimited() and $this['user']->isOnline())
		);

	}

	/**
	 * La journée est payée sur ce qu'elle dit : chacun n'écrit que la sienne, et seul le gérant écrit celle des
	 * autres, une personne sans compte comprise. C'est la règle du temps passé sur une intervention
	 * (`\series\Timesheet::canWrite()`).
	 */
	public function canWrite(): bool {

		$this->expects(['structure', 'user']);

		return (
			$this['structure']->canManage() or
			($this['structure']->canPlanningLimited() and $this['user']->isOnline())
		);

	}

	/**
	 * La plage de la journée, en heures, de l'arrivée au départ.
	 * Le schéma `time` admet HH:MM et HH:MM:SS : la seconde n'a pas de sens ici, la saisie est à la minute.
	 */
	public static function getRange(string $start, string $end): float {

		$minutes = fn(string $time): int => (int)substr($time, 0, 2) * 60 + (int)substr($time, 3, 2);

		return ($minutes($end) - $minutes($start)) / 60;

	}

	public static function properties(\PropertyList $l): void {

		// Sans temps de travail ni suivi RH, aucun écran ne montre la journée, et une journée postée quand même,
		// par l'API, ne s'écrit pas. La date est construite sous les deux formes, en durée comme en horaires.
		$l->property('date')
			->addAfterCheck('untracked', function(): bool {

				$this->expects(['structure']);

				return $this['structure']->hasWorkingTime();

			})
			// Hors de ses périodes de présence, une personne n'est pas payée par la structure : sa journée ne
			// s'écrit pas, et le récapitulatif ne compte pas celles déjà en base (`Presence::isDateWorkable()`).
			->addAfterCheck('outOfPresence', function(string $date, \PropertyList $l): bool {

				// Une personne refusée a déjà son erreur, et la date doit tout de même recevoir sa valeur, que
				// `skipIfInvalid()` abandonnerait avant le set.
				if($l->isInvalid('user')) {
					return TRUE;
				}

				$this->expects(['structure', 'user']);

				return PresenceLib::isWorkable($this['structure'], $this['user'], $date);

			});

		$l->property('user')
			->addAfterCheck('member', function(\user\User $eUser): bool {

				$this->expects(['structure']);

				// Plus large que hr\Presence, qui exige un membre : le planning liste ses utilisateurs
				// par leurs présences, et un ancien membre les conserve (MemberLib::delete() les clôt
				// sans les supprimer). Son temps de travail doit rester saisissable rétroactivement.
				return (
					\structure\MemberLib::isMember($eUser, $this['structure']) or
					Presence::model()
						->whereStructure($this['structure'])
						->whereUser($eUser)
						->exists()
				);

			});

		// Sous horaires, la durée est le calcul des horaires (WorkingTimeLib::prepareTime()) : sans
		// arrivée ni départ, il n'y a rien à calculer. En durée, les horaires ne sont pas construits.
		$l->property('start')
			->addAfterCheck('missing', function(?string $start): bool {

				$this->expects(['structure']);

				return (
					$start !== NULL or
					$this['structure']->hasTimeRange() === FALSE
				);

			});

		$l->property('end')
			->addAfterCheck('missing', function(?string $end): bool {

				$this->expects(['structure']);

				return (
					$end !== NULL or
					$this['structure']->hasTimeRange() === FALSE
				);

			})
			// Stricte : une journée vide n'en est pas une, et une journée qui passe minuit se refuse
			// plutôt que de se compter sur la mauvaise date.
			->addAfterCheck('afterStart', function(?string $end, \PropertyList $l): bool {

				// ⚠️ skipIfInvalid() d'abord : une arrivée refusée n'est pas construite, et expectsBuilt()
				// ajouterait sinon un refus au nom du départ à celui de l'arrivée.
				$l->skipIfInvalid('start');
				$l->expectsBuilt('start');

				if(
					$end === NULL or
					$this['start'] === NULL
				) {
					return TRUE;
				}

				return (self::getRange($this['start'], $end) > 0.0);

			});

		// Strictement plus courte que la plage : la durée calculée reste positive, et zéro est le
		// geste d'effacement d'une journée en durée.
		$l->property('pause')
			->addAfterCheck('tooLong', function(?float $pause, \PropertyList $l): bool {

				$l->skipIfInvalid(['start', 'end']);
				$l->expectsBuilt(['start', 'end']);

				if(
					$pause === NULL or
					$this['start'] === NULL or
					$this['end'] === NULL
				) {
					return TRUE;
				}

				return ($pause < self::getRange($this['start'], $this['end']));

			});

	}

}
?>