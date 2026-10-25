<?php
namespace series;

class Series extends SeriesElement {

	public function getBedStart(): ?string {

		if($this['bedStartCalculated'] !== NULL) {
			return $this->calculatedBed($this['bedStartCalculated'], 1);
		} else {
			return NULL;
		}

	}

	public function getBedStop(): ?string {

		if($this['bedStopCalculated'] !== NULL) {
			return $this->calculatedBed($this['bedStopCalculated'], 7);
		} else {
			return NULL;
		}

	}

	/**
	 * Le lundi où la série prend sa planche et le dimanche où elle la rend, ou deux `NULL` : une série
	 * dont un seul bout se date occuperait sa planche sans fin, ce qui se lirait comme une culture en
	 * place toute l'année. Deux `NULL` aussi quand le premier bout tombe après le second : une pérenne
	 * dont la fin calculée précède la saison, ou dont la plantation calculée la suit.
	 *
	 * Une série pérenne déjà installée tient sa planche dès le lundi de la semaine 1 de la saison, et
	 * jusqu'au dimanche de sa dernière semaine tant qu'elle n'a pas atteint sa durée de vie : sa
	 * semaine calculée ne compte que pour le bout que la saison ne borne pas. La dernière semaine est
	 * celle du 28 décembre, la 52 ou la 53 selon l'année, pour que deux saisons se touchent sans trou.
	 */
	public function getBedDates(int $season): array {

		$this->expects(['season', 'cycle', 'perennialSeason', 'perennialLifetime', 'bedStartCalculated', 'bedStopCalculated']);

		$perennial = ($this['cycle'] === Series::PERENNIAL);
		$installed = ($perennial and $this['perennialSeason'] > 1);
		$continued = ($perennial and ($this['perennialLifetime'] === NULL or $this['perennialSeason'] < $this['perennialLifetime']));

		if(
			($installed === FALSE and $this['bedStartCalculated'] === NULL) or
			($continued === FALSE and $this['bedStopCalculated'] === NULL)
		) {
			return [NULL, NULL];
		}

		$start = $installed ? week_date_day($season.'-W01', 1) : $this->getBedStart();
		$stop = $continued ? week_date_ends(toWeek($season.'-12-28')) : $this->getBedStop();

		if($start > $stop) {
			return [NULL, NULL];
		}

		return [$start, $stop];

	}

	/**
	 * La série tient-elle sa planche le jour `$date` de la saison `$season` (`getBedDates()`) ? Une série sans dates est
	 * en place, rien ne la disant finie ni à venir ; une série clôturée ne l'est plus.
	 */
	public function isInPlace(int $season, string $date): bool {

		$this->expects(['status']);

		if($this['status'] === Series::CLOSED) {
			return FALSE;
		}

		[$start, $stop] = $this->getBedDates($season);

		return (
			($start === NULL or $start <= $date) and
			($stop === NULL or $stop >= $date)
		);

	}

	private function calculatedBed(?int $value, int $day) {

		if($value === NULL) {
			return '';
		}

		// La semaine calculée vaut « semaine ISO + (année ISO - saison) × 100 » : les centaines
		// portent l'écart d'années, arrondi vers le bas pour qu'une semaine reste entre 1 et 53
		// quand l'écart est négatif (-157 est la semaine 43 de la saison moins deux).
		$year = (int)floor($value / 100);

		return week_date_day(($this['season'] + $year).'-W'.sprintf('%02d', $value - $year * 100), $day);

	}

	public function canRead(): bool {
		$this->expects(['structure']);
		return $this['structure']->canRead();
	}

	public function canWrite(): bool {
		$this->expects(['structure']);
		return $this['structure']->canManage();
	}

	public function isTargeted(): bool {

		$this->expects(['use', 'length', 'lengthTarget', 'area', 'areaTarget']);

		switch($this['use']) {

			case Series::BED :
				return ($this['length'] === NULL and $this['lengthTarget'] !== NULL);

			case Series::BLOCK :
				return ($this['area'] === NULL and $this['areaTarget'] !== NULL);

		}

	}

	public function isAnnual(): bool {

		$this->expects(['cycle']);

		return ($this['cycle'] === Series::ANNUAL);

	}

	public function isPerennial(): bool {

		$this->expects(['cycle']);

		return ($this['cycle'] === Series::PERENNIAL);

	}

	public function isOpen(): bool {

		$this->expects(['status']);

		return ($this['status'] === Series::OPEN);

	}

	public function isClosed(): bool {

		$this->expects(['status']);

		return ($this['status'] === Series::CLOSED);

	}

	public function acceptStatus(): bool {

		return $this->isAnnual();

	}

	public function acceptOpen(): bool {

		return $this->isClosed();

	}

	public function acceptClose(): bool {

		return (
			$this->acceptStatus() and
			$this->isOpen()
		);

	}

	public function acceptDuplicate(): bool {
		return $this->isAnnual();
	}

	public function acceptSeason(): bool {
		return ($this->isAnnual() and $this->isOpen());
	}

	// Une série clôturée ne se modifie plus : ses interventions sont terminées, la
	// rouvrir est le passage obligé. Le statut lui-même se change, sans quoi elle
	// serait enfermée (cf. doUpdateStatus, qui ne passe pas par ce test).
	public function acceptUpdate(): bool {
		return $this->isOpen();
	}

	public static function validateBatch(\Collection $cSeries, \structure\Structure $eStructure = new \structure\Structure()): void {

		if($cSeries->empty()) {
			throw new \FailAction('series\Series::series.check');
		} else {

			if($eStructure->empty()) {
				$eStructure = $cSeries->first()['structure'];
			}

			$season = $cSeries->first()['season'];

			foreach($cSeries as $eSeries) {

				$eSeries->validateProperty('structure', $eStructure);
				$eSeries->validateProperty('season', $season);

			}
		}

	}

	public static function properties(\PropertyList $l): void {

		$l->property('structure')
			->replaceCheck(function(\structure\Structure $eStructure): bool {

				return (
					$eStructure->empty() === FALSE and
					$eStructure->canManage()
				);

			});

		$l->property('season')
			->replaceCheck(function(int $season, \PropertyList $l): bool {

				if($l->for === 'update') {
					$this->expects(['season']);
					$this['oldSeason'] = $this['season'];
				}

				return $this['structure']->conf(\map\Configuration::class)->checkSeason($season);

			})
			->addAfterCheck('tasks', function(int $season, \PropertyList $l): bool {

				if($l->for !== 'update' or $season === $this['oldSeason']) {
					return TRUE;
				}

				$week = new \Sql('COALESCE(doneWeek, plannedWeek)');

				return Task::model()
					->whereSeries($this)
					->where($week.' IS NOT NULL')
					->where('ABS(CAST(SUBSTRING('.$week.', 1, 4) AS SIGNED) - '.$season.') > 1')
					->exists() === FALSE;

			});

		$l->property('status')
			->addAfterCheck('accept', function(): bool {

				return $this->acceptStatus();

			});

		$l->property('outcome')
			->addAfterCheck('closed', function(?string $outcome): bool {

				$this->expects(['status']);

				return ($outcome === NULL or $this['status'] === Series::CLOSED);

			});

		$l->property('use')
			->addAfterCast('old', function(mixed $value, \PropertyList $l): bool {

				if($l->for === 'update') {
					$this->expects(['use']);
					$this['oldUse'] = $this['use'];
				}

				return TRUE;

			});

		$l->property('cycle')
			->addAfterCast('sequence', function(?string &$cycle, \PropertyList $l): bool {

				$l->expectsBuilt('sequence');

				if($this['sequence']->notEmpty()) {
					$cycle = $this['sequence']['cycle'];
				}

				return TRUE;

			});

		$l->property('sequence')
			->replaceCheck(function(\sequence\Sequence $eSequence, \PropertyList $l): bool {

				if($l->for === 'update') {
					$this['oldSequence'] = $this['sequence'];
				}

				if($eSequence->empty()) {
					return TRUE;
				}

				if(\sequence\Sequence::model()
						->select('id', 'structure', 'cycle', 'name')
						->get($eSequence) === FALSE) {
					return FALSE;
				}

				return $eSequence['structure']->is($this['structure']);

			});

		$l->property('bedWidth')
			->addAfterCast('required', function(?int &$bedWidth, \PropertyList $l): bool {

				$l->skipIfNotBuilt('use');

				if($this['use'] === Series::BLOCK) {
					$bedWidth = NULL;
				}

				if($this['use'] === Series::BED and $bedWidth === NULL) {
					return FALSE;
				}

				return TRUE;

			});

		$l->property('alleyWidth')
			->addAfterCast('use', function(?int &$alleyWidth, \PropertyList $l): bool {

				$l->skipIfNotBuilt('use');

				if($this['use'] === Series::BLOCK) {
					$alleyWidth = NULL;
				}

				return TRUE;

			});

		$l->property('perennialLifetime')
			->addAfterCast('cycle', function(?int &$lifetime): bool {

				$this->expects(['id', 'cycle']);

				if($this['cycle'] === Series::ANNUAL) {
					$lifetime = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('last', function(?int $lifetime): bool {

				$this->expects(['id', 'cycle']);

				if($this['id'] !== NULL and $this['cycle'] === Series::PERENNIAL) {
					// Il n'est pas possible de modifier la durée de vie d'une série qui est continuée dans le futur
					return ($this['perennialStatus'] !== Series::CONTINUED);
				} else {
					return TRUE;
				}

			})
			->addAfterCheck('consistency', function(?int $lifetime): bool {

				$this->expects(['id', 'cycle']);

				if($this['id'] !== NULL and $this['cycle'] === Series::PERENNIAL) {

					$this->expects(['perennialSeason']);

					if($lifetime !== NULL) {
						return ($lifetime >= $this['perennialSeason']);
					} else {
						return TRUE;
					}

				} else {
					return TRUE;
				}

			});

		$l->property('taskInterval')
			->replaceCheck(function(mixed $value): bool {

				$value = (int)$value;

				['min' => $min, 'max' => $max] = SeriesSetting::DUPLICATE_INTERVAL;

				if($value > $min and $value < $max) {
					$this['taskInterval'] = $value;
					return TRUE;
				} else {
					return FALSE;
				}

			});

	}

}
?>
