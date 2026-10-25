<?php
namespace series;

class Cultivation extends CultivationElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'series' => SeriesElement::getSelection(),
			'structure' => ['name'],
			'plant' => \plant\PlantElement::getSelection(),
			'varietiesTool' => ['name', 'routineValue'],
			'cSlice' => SliceLib::delegateByCultivation(),
			'sequence' => ['name', 'mode'],
			'harvestedByUnit' => function(Cultivation $e): ?array {

				if($e['harvestedByUnit'] === NULL) {
					return NULL;
				}
				$e->sortUnits('harvestedByUnit');

				return $e['harvestedByUnit'];

			},
			'yieldByUnit' => function(Cultivation $e): ?array {

				if($e['harvestedByUnit'] === NULL) {
					return NULL;
				}

				$yield = $e['harvestedByUnit'];

				if($e['mainUnit'] === Cultivation::KG) {

					$yield[Cultivation::KG] ??= 0;

					if(isset($yield[Cultivation::BUNCH]) and $e['bunchWeight']) {
						$yield[Cultivation::KG] += $yield[Cultivation::BUNCH] * $e['bunchWeight'];
						unset($yield[Cultivation::BUNCH]);
					}

					if(isset($yield[Cultivation::UNIT]) and $e['unitWeight']) {
						$yield[Cultivation::KG] += $yield[Cultivation::UNIT] * $e['unitWeight'];
						unset($yield[Cultivation::UNIT]);
					}

				}

				return $yield;

			},
			'harvestExpected' => function(Cultivation $e): ?float {

				if($e['yieldExpected'] === NULL) {
					return NULL;
				}

				if($e['area'] !== NULL) {
					return round($e['yieldExpected'] * $e['area']);
				} else {
					return NULL;
				}

			},
			'harvestExpectedTarget' => function(Cultivation $e): ?float {

				if($e['yieldExpected'] === NULL) {
					return NULL;
				}

				return $e['series']['areaTarget'] ? round($e['yieldExpected'] * $e['series']['areaTarget']) : NULL;

			},
			'yield' => new \Sql('IF(area IS NOT NULL AND harvested IS NOT NULL, ROUND(harvested / area * 10) / 10, NULL)', 'float'),
		];

	}

	public function getStartWeek(int $year): string {

		$week = sprintf('%02d', ($this['startWeek'] + 100) % 100);

		if($this['startWeek'] < 0) {
			return ($year - 1).'-W'.$week;
		} else if($this['startWeek'] > 100) {
			return ($year - 1).'-W'.$week;
		} else {
			return $year.'-W'.$week;
		}

	}

	public function getYoungPlants(Slice $eSlice = new Slice(),  ?bool &$targeted = NULL, ?int $safetyMargin = NULL, ?string &$error = NULL): ?int {

		$this->expects([
			'series' => ['use', 'area', 'areaTarget', 'length', 'lengthTarget'],
			'distance',
		]);

		$safetyMarginMultiplier = (1 + ($safetyMargin ?? 0) / 100);

		if($eSlice->empty()) {
			$slicePercent = 100;
			$varietiesUnit = Cultivation::PERCENT;
		} else {

			switch($this['varietiesUnit']) {

				case Cultivation::PLANT :
					return round($eSlice['partPlant'] * $safetyMarginMultiplier);

				case Cultivation::TRAY :
					$this->expects([
						'varietiesTool' => ['routineValue']
					]);
					return round($eSlice['partTray'] * $this['varietiesTool']['routineValue']['value'] * $safetyMarginMultiplier);

			}

			$slicePercent = $eSlice['partPercent'];
			$varietiesUnit = $this['varietiesUnit'];

		}

		if($this['density'] === NULL) {
			$error = 'density';
			return NULL;
		}

		if(
			($this['series']['use'] === Series::BLOCK) or
			($this['series']['use'] === Series::BED and $this['distance'] === Cultivation::DENSITY)
		) {

			if($this['series']['area'] !== NULL) {
				$area = $this['series']['area'];
				$targeted = FALSE;
			} else if($this['series']['areaTarget'] !== NULL) {
				$area = $this['series']['areaTarget'];
				$targeted = TRUE;
			} else {
				$error = 'area';
				return NULL;
			}

			switch($varietiesUnit) {

				case Cultivation::PERCENT :
					return round($area * $this['density'] * $slicePercent / 100 * $safetyMarginMultiplier);

				case Cultivation::AREA :
					return round($eSlice['partArea'] * $this['density'] * $safetyMarginMultiplier);

				case Cultivation::LENGTH :
					// `series.area` est bâtie place par place, avec la largeur de chaque planche (Place.l.php:171) :
					// une longueur ne s'y convertit que par sa part de la longueur totale, comme dans getArea().
					$length = $this['series']['length'] ?? $this['series']['lengthTarget'];

					if($length === NULL) {
						$error = 'length';
						return NULL;
					}

					return round($eSlice['partLength'] / $length * $area * $this['density'] * $safetyMarginMultiplier);

			};

		} else {

			if($this['series']['length'] !== NULL) {
				$length = $this['series']['length'];
				$targeted = FALSE;
			} else if($this['series']['lengthTarget'] !== NULL) {
				$length = $this['series']['lengthTarget'];
				$targeted = TRUE;
			} else {
				$error = 'length';
				return NULL;
			}

			$plantsByMeter = (100 / $this['plantSpacing']) * $this['rows'];

			switch($varietiesUnit) {

				case Cultivation::PERCENT :
					return round($length * $plantsByMeter * $slicePercent / 100 * $safetyMarginMultiplier);

				case Cultivation::LENGTH :
					return round($eSlice['partLength'] * $plantsByMeter * $safetyMarginMultiplier);

			}

		}

	}

	public function getSeeds(Slice $eSlice = new Slice(),  ?bool &$targeted = NULL, ?int $safetyMargin = NULL, ?string &$error = NULL): ?int {

		$youngPlants = self::getYoungPlants($eSlice, $targeted, $safetyMargin, $error);

		if($youngPlants !== NULL) {

			switch($this['seedling']) {

				case Cultivation::SOWING :
				case Cultivation::YOUNG_PLANT :
					return $youngPlants * $this['seedlingSeeds'];

				case Cultivation::YOUNG_PLANT_BOUGHT :
					return NULL;

			}

		}

		return NULL;

	}

	public function getHarvestExpectedOrTarget(): ?float {

		$this->expects([
			'series' => ['area', 'areaTarget'],
			'yieldExpected',
		]);

		$area = $this['series']['area'] ?? $this['series']['areaTarget'];

		if($area === NULL or $this['yieldExpected'] === NULL) {
			return NULL;
		}

		return $this['yieldExpected'] * $area;

	}

	public function calculateHarvestedNormalized() {

		$this->expects(['mainUnit', 'harvestedByUnit', 'bunchWeight', 'unitWeight']);

		if($this['harvestedByUnit'] === NULL) {
			$this['harvestedNormalized'] = NULL;
			return;
		}

		if($this['mainUnit'] === Cultivation::KG) {

			$result = $this['harvestedByUnit'][Cultivation::KG] ?? 0;

			if(isset($this['harvestedByUnit'][Cultivation::BUNCH]) and $this['bunchWeight']) {
				$result += $this['harvestedByUnit'][Cultivation::BUNCH] * $this['bunchWeight'];
			}

			if(isset($this['harvestedByUnit'][Cultivation::UNIT]) and $this['unitWeight']) {
				$result += $this['harvestedByUnit'][Cultivation::UNIT] * $this['unitWeight'];
			}

			$this['harvestedNormalized'] = ($result > 0) ? $result : NULL;
		} else {
			$this['harvestedNormalized'] = $this['harvested'];
		}

	}

	// ⚠️ `canPlanning()` et non `canWrite()`, dont le parent répond « est membre » : sans droit de
	// rôle, toute adhésion lisait et écrivait le plan de culture.
	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canPlanning();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->canPlanning();

	}

	// Une culture ne se modifie plus quand sa série est clôturée : ses
	// interventions sont terminées, rouvrir la série est le passage obligé.
	public function acceptWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->acceptWrite();

	}

	public function acceptUpdate(): bool {

		$this->expects(['series']);

		return (
			$this->acceptWrite() and
			$this['series']->isOpen()
		);

	}

	public function sortUnits(string $property): void {

		$this->expects(['harvestedByUnit']);

		if($this['harvestedByUnit'] === NULL) {
			throw new \Exception('harvestedByUnit must not be NULL');
		}

		uksort($this['harvestedByUnit'], function($key1, $key2) {

			if($key1 === $this['mainUnit']) {
				return -1;
			}

			if($key2 === $this['mainUnit']) {
				return 1;
			}

			return strcmp($key1, $key2);

		});

	}

	public function getHarvestBounds(): array {

		$this->expects(['harvestWeeks', 'harvestWeeksExpected']);

		$min = NULL;
		$max = NULL;

		if($this['harvestWeeksExpected'] !== NULL) {

			$min = min($this['harvestWeeksExpected']);
			$max = max($this['harvestWeeksExpected']);

		}

		if($this['harvestWeeks'] !== NULL) {

			$min = ($min === NULL) ? min($this['harvestWeeks']) : min($min, min($this['harvestWeeks']));
			$max = ($max === NULL) ? max($this['harvestWeeks']) : max($max, max($this['harvestWeeks']));

		}

		return [$min, $max];

	}

	public static function validateBatch(\Collection $cCultivation, \structure\Structure $eStructure): \plant\Plant {

		$eStructure->expects(['id']);

		if($cCultivation->empty()) {
			return new \plant\Plant();
		}

		$season = $cCultivation->first()['season'];
		$ePlant = $cCultivation->first()['plant'];

		foreach($cCultivation as $eCultivation) {

			$eCultivation->validate('canWrite');

			if($eCultivation['structure']['id'] !== $eStructure['id']) {
				throw new \NotExpectedAction('Wrong structure');
			}

			if($eCultivation['season'] !== $season) {
				throw new \NotExpectedAction('Different seasons');
			}

			if(
				$ePlant->notEmpty() and
				$eCultivation['plant']['id'] !== $ePlant['id']
			) {
				$ePlant = new \plant\Plant();
			}

		}

		return $ePlant;

	}

	public function format(string $property, array $options = []): ?string {

		switch($property) {

			case 'yield' :
			case 'yieldExpected' :
			case 'harvested' :
			case 'harvestExpected' :
			case 'harvestExpectedTarget' :
				return \sequence\CropUi::getYield($this, $property, 'mainUnit', $options);

			default :
				return parent::format($property, $options);

		}

	}

	public static function properties(\PropertyList $l): void {

		$spacing = function(float|int|null &$value): bool {

			$this->expects(['distance']);

			if($this['distance'] !== Cultivation::SPACING) {
				$value = NULL;
			}

			return TRUE;

		};

		$l->property('series')
			->addAfterCheck('open', function(Series $eSeries): bool {

				$this->expects(['structure']);

				return Series::model()
					->whereStructure($this['structure'])
					->whereStatus(Series::OPEN)
					->exists($eSeries);

			});

		$l->property('crop')
			->replaceCheck(function(\sequence\Crop $eCrop): bool {

				return (
					$eCrop->empty() or
					\sequence\Crop::model()
						->select([
							'id'
						])
						->get($eCrop)
				);

			});

		$l->property('harvest')
			->addAfterCast('array', function(&$harvest): bool {

				if($harvest === []) {
					$harvest = NULL;
				}

				return (
					$harvest === NULL or
					is_array($harvest)
				);

			})
			->replaceCheck(function(?array &$harvest): bool {

				if($harvest === NULL) {
					return TRUE;
				}

				foreach($harvest as $month) {
					if(\Filter::check('month', $month) === FALSE) {
						return FALSE;
					}
				}

				return TRUE;

			});

		$l->property('plant')
			->addAfterCast('owned', function(\plant\Plant $ePlant, \PropertyList $l): bool {

				if($l->for === 'update') {

					if($ePlant->empty()) {
						$ePlant->merge($this['plant']);
						return TRUE;
					}

				}

				if(
					$ePlant->empty() === FALSE and
					\plant\Plant::model()
						->select('structure', 'name', 'cycle')
						->get($ePlant) and
					$ePlant['structure']->is($this['structure'])
				) {
					return TRUE;
				} else {
					throw new \BuildElementError();
				}

			})
			->addAfterCheck('unused', function(\plant\Plant $ePlant, \PropertyList $l): bool {

				if($l->for === 'update') {

					if($ePlant['id'] === $this['plant']['id']) {
						return TRUE;
					}

					$this->expects('series');

					return Cultivation::model()
						->whereSeries($this['series'])
						->wherePlant($ePlant)
						->whereId('!=', $this['plant'])
						->exists() === FALSE;

				} else {
					return TRUE;
				}

			});

		$l->property('varietiesTool')
			->replaceCheck(function(\work\Tool $eTool, \PropertyList $l) {

				$l->expectsBuilt('varietiesUnit');

				if($this['varietiesUnit'] === Cultivation::TRAY) {

					return \work\Tool::model()
						->whereStructure($this['structure'])
						->whereRoutineName('tray')
						->exists($eTool);

				} else {
					return $eTool->empty();
				}

			});

		$l->property('varieties')
			->replaceCheck(function(?array $varieties, \PropertyList $l, string $wrapper) {

				$l->expectsBuilt(['varietiesUnit', 'varietiesTool']);

				if($varieties === NULL) {
					return FALSE;
				}

				$this['cSlice'] = \sequence\SliceLib::prepare($this, $varieties, $wrapper);

				if($this['cSlice']->empty()) {
					$this['varietiesUnit'] = Cultivation::PERCENT;
					$this['varietiesTool'] = new \work\Tool();
				}

				return TRUE;

			});

		$l->property('seedlingSeeds')
			->addAfterCast('seedling', function(?int &$seeds): bool {

				$this->expects(['seedling']);

				if(in_array($this['seedling'], [Cultivation::SOWING, Cultivation::YOUNG_PLANT]) === FALSE) {
					$seeds = NULL;
				} else {
					$seeds = (int)$seeds;
				}

				return TRUE;

			});

		$l->property('rowSpacing')
			->addAfterCast('spacing', $spacing);

		$l->property('rows')
			->addAfterCast('spacing', $spacing);

		$l->property('plantSpacing')
			->addAfterCast('spacing', $spacing);

		$l->property('stagger')
			->addAfterCast('spacing', function(bool &$stagger): bool {

				$this->expects(['distance']);

				if($this['distance'] !== Cultivation::SPACING) {
					$stagger = FALSE;
				}

				return TRUE;

			});

		$l->property('harvestMonthsExpected')
			->addAfterCheck('months', function(?array $months): bool {

				$this->expects(['harvestPeriodExpected']);

				if($this['harvestPeriodExpected'] !== Cultivation::MONTH) {
					return TRUE;
				}

				return ($months === NULL or array_filter($months, fn($month) => \Filter::check('month', $month) === FALSE) === []);

			})
			->replaceSet(function(?array $months): bool {

				$this->expects(['harvestPeriodExpected']);

				if($this['harvestPeriodExpected'] !== Cultivation::MONTH) {
					return TRUE;
				}

				$this['harvestMonthsExpected'] = $months;

				if($months === NULL) {
					$this['harvestWeeksExpected'] = NULL;
				} else {
					$this['harvestWeeksExpected'] = \util\DateLib::convertMonthsToWeeks($months);
				}

				return TRUE;

			});

		$l->property('harvestWeeksExpected')
			->addAfterCheck('weeks', function(?array $weeks): bool {

				$this->expects(['harvestPeriodExpected']);

				if($this['harvestPeriodExpected'] !== Cultivation::WEEK) {
					return TRUE;
				}

				return ($weeks === NULL or array_filter($weeks, fn($week) => \Filter::check('week', $week) === FALSE) === []);

			})
			->replaceSet(function(?array $weeks): bool {

				$this->expects(['harvestPeriodExpected']);

				if($this['harvestPeriodExpected'] !== Cultivation::WEEK) {
					return TRUE;
				}

				$this['harvestWeeksExpected'] = $weeks;

				if($weeks === NULL) {
					$this['harvestMonthsExpected'] = NULL;
				} else {
					$this['harvestMonthsExpected'] = \util\DateLib::convertWeeksToMonths($weeks);
				}

				return TRUE;

			});

		$l->property('actions')
			->replaceSet(function(?array $actions): bool {

				$this->expects(['seedling']);

				$this['actions'] = [];

				switch($this['seedling']) {

					case Cultivation::SOWING :
						if(\Filter::check('week', $actions[ACTION_SEMIS_DIRECT] ?? NULL)) {
							$this['actions'][ACTION_SEMIS_DIRECT] = $actions[ACTION_SEMIS_DIRECT];
						}
						break;

					case Cultivation::YOUNG_PLANT :
						if(\Filter::check('week', $actions[ACTION_SEMIS_PEPINIERE] ?? NULL)) {
							$this['actions'][ACTION_SEMIS_PEPINIERE] = $actions[ACTION_SEMIS_PEPINIERE];
						}
						if(\Filter::check('week', $actions[ACTION_PLANTATION] ?? NULL)) {
							$this['actions'][ACTION_PLANTATION] = $actions[ACTION_PLANTATION];
						}
						break;

					case Cultivation::YOUNG_PLANT_BOUGHT :
						if(\Filter::check('week', $actions[ACTION_PLANTATION] ?? NULL)) {
							$this['actions'][ACTION_PLANTATION] = $actions[ACTION_PLANTATION];
						}
						break;

				}

				return TRUE;

			});

	}

	public function build(array $properties, array $input, \PropertyList $l = new \PropertyList()): void {

		$this->expects([
			'series' => ['use']
		]);

		parent::build($properties, $input, $l);

	}

}
?>
