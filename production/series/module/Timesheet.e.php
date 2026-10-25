<?php
namespace series;

class Timesheet extends TimesheetElement {

	public function canRead(): bool {
		return $this->canWrite();
	}

	public function canWrite(): bool {

		$this->expects(['structure', 'user']);

		return (
			$this['structure']->canManage() or
			(
				$this['structure']->canPlanningLimited() and
				$this['user']->isOnline()
			)
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('date')
			->addAfterCheck('future', function(string $date) {
				return \util\DateLib::compare($date, \Clock::date()) <= 0;
			});

		// Une saisie par jour (`new \PropertyList('day')`) écrit la durée envoyée telle quelle : absente, elle
		// passerait le schéma à zéro et effacerait le jour.
		$l->property('time')
			->addBeforeCast('missing', function($value, \PropertyList $l): bool {
				return ($l->for !== 'day' or $value !== NULL);
			})
			// Une structure qui ne suit pas le temps de travail n'en a aucun écran, et un temps qu'on lui poste
			// quand même ne s'écrit pas.
			->addAfterCheck('untracked', function(): bool {
				return $this['structure']->hasTime();
			});

	}

}
?>