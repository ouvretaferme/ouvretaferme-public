<?php
namespace map;

class Configuration extends ConfigurationElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
			'calendarMonths' => new \Sql('IF(calendarMonthStart IS NULL, 0, 12 - calendarMonthStart + 1) + 12 + IF(calendarMonthStop IS NULL, 0, calendarMonthStop)', 'int'),
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function isSeasonValid(int $season): bool {

		$this->expects(['seasonFirst', 'seasonLast']);

		return (
			$season >= $this->getFirstValidSeason() and
			$season <= $this->getLastValidSeason()
		);

	}

	public function getFirstValidSeason(): int {

		$this->expects(['seasonFirst']);

		return $this['seasonFirst'] - \structure\StructureSetting::CALENDAR_LIMIT;

	}

	public function getLastValidSeason(): int {

		$this->expects(['seasonLast']);

		return $this['seasonLast'] + \structure\StructureSetting::CALENDAR_LIMIT;

	}

	public function validateSeason(mixed $season): self {

		if($this->checkSeason($season) === FALSE) {
			throw new \NotExpectedAction($this);
		}

		return $this;

	}

	public function getSeasons(): array {

		$this->expects(['seasonFirst', 'seasonLast']);

		$seasons = [];
		for($season = $this['seasonLast']; $season >= $this['seasonFirst']; $season--) {
			$seasons[] = $season;
		}
		return $seasons;

	}

	public function checkSeason(mixed $season): bool {

		$this->expects(['seasonFirst', 'seasonLast']);

		return ($season >= $this['seasonFirst'] and $season <= $this['seasonLast']);

	}

	public function getRotationSeasons(int $lastSeason): array {

		$this->expects(['rotationYears', 'seasonFirst']);

		$seasons = [];
		for($season = $lastSeason; $season >= $this['seasonFirst'] and count($seasons) < $this['rotationYears']; $season--) {
			$seasons[] = $season;
		}

		return $seasons;

	}

	public function getCalendarStartDay(int $season): string {
		return $this['calendarMonthStart'] ? ($season - 1).'-'.sprintf('%02d', $this['calendarMonthStart']).'-01' : $season.'-01-01';
	}

	public function getCalendarStopDay(int $season): string {

		if($this['calendarMonthStop']) {

			$date = new \DateTime(($season + 1).'-'.$this['calendarMonthStop'].'-01');
			$date->modify('last day of this month');

			return $date->format('Y-m-d');

		} else {
			return $season.'-12-31';
		}

	}

	public static function properties(\PropertyList $l): void {

		$l->property('rotationExclude')
			->addAfterCast('owned', function(mixed &$plants): bool {

				$this->expects(['structure']);

				$plants = (array)($plants ?? []);

				$plants = \plant\Plant::model()
					->select('id')
					->whereId('IN', $plants)
					->whereStructure($this['structure'])
					->getColumn('id');

				return TRUE;

			});

		$l->property('defaultBedWidth')
			->addAfterCheck('size', fn(?int $defaultBedWidth): bool => ($defaultBedWidth === NULL or $defaultBedWidth >= 5));

	}

}
?>
