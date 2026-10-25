<?php
namespace planning;

/**
 * Un rappel du pense-bête de la semaine, partagé par toute l'équipe de la structure : une ligne de
 * texte rattachée à une semaine, avec un jour en option, qu'on coche une fois faite.
 *
 * Il remplace les interventions « 📌 » qu'une équipe créait pour ne rien oublier : il se lit donc
 * comme le planning (`canPlanningLimited()`), et s'écrit et se coche avec le droit d'écrire une
 * intervention (`canPlanning()`, comme `series\Task::canWrite()`).
 *
 * `status`, `doneAt` et `doneBy` ne se postent jamais : `ReminderLib::check()` et `uncheck()` les
 * posent ensemble, pour qu'un rappel coché dise toujours qui l'a coché et quand.
 */
class Reminder extends ReminderElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'createdBy' => ['firstName', 'lastName', 'vignette', 'visibility'],
			'doneBy' => ['firstName', 'lastName', 'vignette', 'visibility'],
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanningLimited();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public function acceptCheck(): bool {

		$this->expects(['status']);
		return $this['status'] === Reminder::TODO;

	}

	public function acceptUncheck(): bool {

		$this->expects(['status']);
		return $this['status'] === Reminder::DONE;

	}

	public function acceptUpdate(): bool {
		return $this->isShownFromNow();
	}

	public function acceptDelete(): bool {
		return $this->isShownFromNow();
	}

	/**
	 * Un rappel se corrige et s'efface tant qu'une semaine en cours ou à venir le montre : à faire, il est
	 * reporté jusqu'à la semaine en cours ; coché, il ne vit plus que dans la plus tardive de sa semaine
	 * et de celle de sa coche (`ReminderLib::fillDone()`), et une semaine passée ne s'écrit pas. La règle
	 * ne monte pas dans `acceptWrite()` : `acceptCreate()` en hérite, et un rappel neuf n'a pas d'état.
	 */
	private function isShownFromNow(): bool {

		$this->expects(['status', 'week', 'doneAt']);

		return (
			$this['status'] === Reminder::TODO or
			max($this['week'], toWeek($this['doneAt'])) >= \Clock::week()
		);

	}

	public function assertCheck(): bool {
		return $this->acceptCheck() and $this->canWrite();
	}

	public function assertUncheck(): bool {
		return $this->acceptUncheck() and $this->canWrite();
	}

	public static function properties(\PropertyList $l): void {

		// Une semaine passée ne prend plus de rappel : il y serait aussitôt reporté dans la semaine en cours.
		$l->property('week')
			->addAfterCheck('notPast', fn(string $week): bool => $week >= \Clock::week());

		// Le jour, quand il est donné, tombe dans la semaine du rappel : c'est elle qui range le rappel
		// à l'écran, et un jour d'une autre semaine le ferait lire au mauvais endroit.
		//
		// La semaine est construite avant le jour à la création (`ReminderLib::getPropertiesCreate()`).
		// Refusée, elle porte déjà son propre message, que celui-ci doublerait. À la mise à jour, elle
		// n'est pas postée et se lit sur l'élément.
		$l->property('date')
			->addAfterCheck('dateInWeek', function(?string $date, \PropertyList $l): bool {

				if($date === NULL) {
					return TRUE;
				}

				if($l->hasProperty('week') and $l->isBuilt('week') === FALSE) {
					return TRUE;
				}

				$this->expects(['week']);

				return (
					$date >= week_date_starts($this['week']) and
					$date <= week_date_ends($this['week'])
				);

			});

	}

}
?>
