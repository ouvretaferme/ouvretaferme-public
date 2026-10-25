<?php
namespace series;

class CsvLib {

	public static function getExportTimesheet(\structure\Structure $eStructure, int $year): array {

		$ccTimesheet = Timesheet::model()
			->select([
				'user' => ['firstName', 'lastName', 'visibility'],
				'time',
				'date'
			])
			->join(Task::model()
				->select([
					'task' => new \Sql('m2.id'),
					'action' => ['name'],
					'category' => ['name'],
					'series' => ['name', 'mode'],
					'plant' => ['name'],
					'variety' => ['name'],
					'description',
					'harvestUser' => new \Sql('IF(harvest IS NOT NULL, IF(m2.time > 0, FLOOR(harvest * m1.time / m2.time * 100) / 100, 0), NULL)', 'float'),
					'harvest',
					'harvestUnit',
					'harvestSize' => ['name']
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->getCollection(index: ['task', NULL]);

		$output = [];

		foreach($ccTimesheet as $cTimesheet) {

			$eTimesheetFirst = $cTimesheet->first();

			if($eTimesheetFirst['harvest'] !== NULL) {

				$harvestUser = $cTimesheet->sum('harvestUser');
				$harvestTotal = $eTimesheetFirst['harvest'];

				if($harvestUser < $harvestTotal) {
					$eTimesheetFirst['harvestUser'] += round($harvestTotal - $harvestUser, 2);
				}

			}

			foreach($cTimesheet as $eTimesheet) {

				$output[] = [
					\util\DateUi::numeric($eTimesheet['date']),
					($eTimesheet['user']['firstName'] === NULL) ? $eTimesheet['user']['lastName'] : $eTimesheet['user']['firstName'].' '.$eTimesheet['user']['lastName'],
					$eTimesheet['category']['name'],
					$eTimesheet['action']['name'],
					\util\TextUi::csvNumber($eTimesheet['time']),
					$eTimesheet['series']->empty() ? '' : $eTimesheet['series']['id'],
					$eTimesheet['series']->empty() ? '' : $eTimesheet['series']['name'],
					$eTimesheet['plant']->empty() ? '' : $eTimesheet['plant']['name'],
					$eTimesheet['variety']->empty() ? '' : $eTimesheet['variety']['name'],
					$eTimesheet['harvestUser'] ? \util\TextUi::csvNumber($eTimesheet['harvestUser']) : '',
					($eTimesheet['harvestUser'] and $eTimesheet['harvestUnit']) ? \product\UnitUi::getSingular($eTimesheet['harvestUnit']) : '',
					$eTimesheet['harvestSize']->empty() ? '' : $eTimesheet['harvestSize']['name'],
				];

			}

		}

		return $output;

	}

	public static function getExportTasks(\structure\Structure $eStructure, int $year): array {

		$cTask = Task::model()
				->select([
					'id',
					'plannedWeek', 'doneWeek',
					'time', 'timeExpected',
					'action' => ['name'],
					'category' => ['name'],
					'series' => ['name', 'mode'],
					'plant' => ['name'],
					'variety' => ['name'],
					'description',
					'tools',
					'products',
					'methods',
					'cTool?' => fn($e) => fn() =>\work\ToolLib::askCollection($e['tools'], $eStructure),
					'cProduct?' => fn($e) => fn() =>\work\ToolLib::askCollection($e['products'], $eStructure),
					'cMethod?' => fn($e) => fn() =>\work\MethodLib::askCollection($e['methods'], $eStructure),
					'harvest',
					'harvestUnit',
					'harvestSize' => ['name']
				])
			->whereStructure($eStructure)
			->where(new \Sql('IF(doneWeek IS NULL, plannedWeek, doneWeek) LIKE "'.$year.'%"'))
			->sort(new \Sql('IF(doneWeek IS NULL, plannedWeek, doneWeek), id'))
			->getCollection();

		$output = [];

		foreach($cTask as $eTask) {

			$output[] = [
				$eTask['plannedWeek'],
				$eTask['doneWeek'],
				$eTask['category']['name'],
				$eTask['action']['name'],
				\util\TextUi::csvNumber($eTask['time']),
				\util\TextUi::csvNumber($eTask['timeExpected']),
				$eTask['series']->empty() ? '' : $eTask['series']['id'],
				$eTask['series']->empty() ? '' : $eTask['series']['name'],
				$eTask['plant']->empty() ? '' : $eTask['plant']['name'],
				$eTask['variety']->empty() ? '' : $eTask['variety']['name'],
				implode(', ', array_merge($eTask['cProduct?']()->getColumn('name'), $eTask['cTool?']()->getColumn('name'))),
				implode(', ', $eTask['cMethod?']()->getColumn('name')),
				$eTask['description'],
				\util\TextUi::csvNumber($eTask['harvest']),
				($eTask['harvest'] and $eTask['harvestUnit']) ? \product\UnitUi::getSingular($eTask['harvestUnit']) : '',
				$eTask['harvestSize']->empty() ? '' : $eTask['harvestSize']['name'],
			];

		}

		return $output;

	}

	public static function getExportHarvests(\structure\Structure $eStructure, int $year): array {

		$cHarvest = Harvest::model()
			->select([
				'date',
				'task' => [
					'series' => ['name'],
					'plant' => ['name'],
					'variety' => ['name'],
					'harvestSize' => ['name'],
				],
				'quantity' => new \Sql('SUM(quantity)', 'float'),
				'unit'
			])
			->whereStructure($eStructure)
			->where('EXTRACT(YEAR FROM date) = '.$year)
			->where('quantity IS NOT NULL')
			->group(['date', 'unit', 'task'])
			->sort('date')
			->getCollection();

		$output = [];

		foreach($cHarvest as $eHarvest) {

			$eTask = $eHarvest['task'];

			if(round($eHarvest['quantity'], 2) === 0.0) {
				continue;
			}

			$output[] = [
				\util\DateUi::numeric($eHarvest['date']),
				$eTask['series']->empty() ? '' : $eTask['series']['id'],
				$eTask['series']->empty() ? '' : $eTask['series']['name'],
				$eTask['plant']->empty() ? '' : $eTask['plant']['name'],
				$eTask['variety']->empty() ? '' : $eTask['variety']['name'],
				\util\TextUi::csvNumber($eHarvest['quantity']),
				\product\UnitUi::getSingular($eHarvest['unit']),
				$eTask['harvestSize']->empty() ? '' : $eTask['harvestSize']['name']
			];

		}

		return $output;

	}

	// Les quantités et les filtres viennent déjà de getForSeedling() : une ligne
	// de l'export est une ligne de la grille.
	// Le tableur reçoit des grammes bruts et non « 1.2 kg » : une masse mise en forme
	// ne s'additionne pas, et l'unité change d'une ligne à l'autre.
	protected static function getSeedsGrams(\plant\Variety $eVariety, ?int $seeds): ?float {

		if($eVariety->empty() or $eVariety['weightSeed1000'] === NULL or $seeds === NULL) {
			return NULL;
		}

		return $seeds / 1000 * $eVariety['weightSeed1000'];

	}

	public static function getExportSeedling(array $items): array {

		$output = [];

		foreach($items as ['plant' => $ePlant, 'seeds' => $seeds]) {

			foreach($seeds as $seedsVariety) {

				$eVariety = $seedsVariety['variety'];

				$seedlings = [];
				$seriesNames = [];

				foreach($seedsVariety['cultivations'] as $cultivation) {

					$seedling = $cultivation['cultivation']['seedling'];

					if($seedling !== NULL) {
						$seedlings[$seedling] = TRUE;
					}

					$seriesNames[] = $cultivation['series']['name'];

				}

				$stock = $eVariety->empty() ? NULL : $eVariety['stock'];
				$toBuy = max($seedsVariety['seeds'] - (int)$stock, 0);

				$output[] = [
					$ePlant['name'],
					$eVariety->empty() ? '' : $eVariety['name'],
					implode(' ', array_keys($seedlings)),
					($eVariety->notEmpty() and $eVariety['supplierSeed']->notEmpty()) ? $eVariety['supplierSeed']['name'] : '',
					$eVariety->empty() ? NULL : \util\TextUi::csvNumber($eVariety['weightSeed1000']),
					\util\TextUi::csvNumber($seedsVariety['seeds']),
					\util\TextUi::csvNumber($stock),
					\util\TextUi::csvNumber(self::getSeedsGrams($eVariety, $stock)),
					\util\TextUi::csvNumber($toBuy),
					\util\TextUi::csvNumber(self::getSeedsGrams($eVariety, $toBuy)),
					\util\TextUi::csvNumber($seedsVariety['youngPlantsProduced']),
					($eVariety->notEmpty() and $eVariety['supplierPlant']->notEmpty()) ? $eVariety['supplierPlant']['name'] : '',
					\util\TextUi::csvNumber($seedsVariety['youngPlantsBought']),
					count($seriesNames),
					implode(' / ', $seriesNames)
				];

			}

		}

		return $output;

	}

	public static function getExportCultivations(\structure\Structure $eStructure, int $year, &$maxVarieties, \Collection $cSeries = new \Collection()): array {

		$cAction = \work\Action::model()
			->select('id', 'fqn')
			->whereStructure($eStructure)
			->whereFqn('IN', [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION])
			->getCollection(index: 'fqn');

		$cTool = \work\ToolLib::getTraysByStructure($eStructure);

		$eActionSemisDirect = $cAction[ACTION_SEMIS_DIRECT];
		$eActionSemisPepiniere = $cAction[ACTION_SEMIS_PEPINIERE];
		$eActionPlantation = $cAction[ACTION_PLANTATION];

		$cCultivation = Cultivation::model()
			->select(Cultivation::getSelection() + [
				'cTask' => Task::model()
					->select([
						'action',
						'min' => new \Sql('MIN(IF(doneWeek IS NOT NULL, doneWeek, plannedWeek))'),
						'max' => new \Sql('MAX(IF(doneWeek IS NOT NULL, doneWeek, plannedWeek))'),
						'toolList' => new \Sql('CONCAT("{", GROUP_CONCAT(CONCAT("\\"", IF(doneWeek IS NOT NULL, doneWeek, plannedWeek), "\\":", tools) SEPARATOR ","), "}")'),
						'toolName' => function($e) use($cTool) {

							$tools = json_decode($e['toolList'], TRUE);

							if(empty($tools[$e['min']]) === FALSE) {

								foreach($tools[$e['min']] as $tool) {
									if($cTool->offsetExists($tool)) {
										return $cTool[$tool]['name'];
									}
								}

							}

							return '';

						}
					])
					->whereAction('IN', $cAction)
					->group(['cultivation', 'action'])
					->delegateCollection('cultivation', index: 'action')
			])
			->whereStructure($eStructure)
			->whereSeason($year)
			->whereSeries('IN', $cSeries, if: $cSeries->notEmpty())
			->sort([
				'series' => SORT_ASC,
				'id' => SORT_ASC
			])
			->getCollection();

		$maxVarieties = 0;
		$output = [];

		foreach($cCultivation as $eCultivation) {

			$maxVarieties = max($maxVarieties, $eCultivation['cSlice']->count());
			$eSeries = $eCultivation['series'];

			// Pas de cultures pérennes
			if($eSeries['cycle'] === Series::PERENNIAL) {
				continue;
			}

			$sowingWeek = match($eCultivation['seedling']) {
				Cultivation::SOWING => $eCultivation['cTask'][$eActionSemisDirect['id']]['min'] ?? NULL,
				Cultivation::YOUNG_PLANT => $eCultivation['cTask'][$eActionSemisPepiniere['id']]['min'] ?? NULL,
				default => NULL
			};
			$sowingDate = $sowingWeek ? week_date_starts($sowingWeek) : NULL;

			$plantingWeek = match($eCultivation['seedling']) {
				Cultivation::YOUNG_PLANT, Cultivation::YOUNG_PLANT_BOUGHT => $eCultivation['cTask'][$eActionPlantation['id']]['min'] ?? NULL,
				default => NULL,
			};
			$plantingDate = $plantingWeek ? week_date_starts($plantingWeek) : NULL;

			if($eCultivation['seedling'] === Cultivation::YOUNG_PLANT) {
				$youngPlantTray = $eCultivation['cTask'][$eActionSemisPepiniere['id']]['toolName'] ?? NULL;
			} else {
				$youngPlantTray = NULL;
			}

			$harvestDates = $eCultivation['harvestWeeks'] ?? $eCultivation['harvestWeeksExpected'] ?? [];

			$minHarvestDate = $harvestDates ? week_date_starts(min($harvestDates)) : NULL;
			$maxHarvestDate = $harvestDates ? week_date_ends(max($harvestDates)) : NULL;

			$line = [
				$eSeries['season'],
				$eSeries['id'],
				$eSeries['name'],
				$eSeries['mode'],
				$eSeries['use'],
				$eCultivation['plant']['name'],
				($eCultivation['seedling'] !== NULL) ? $eCultivation['seedling'] : NULL,
				in_array($eCultivation['seedling'], [Cultivation::SOWING, Cultivation::YOUNG_PLANT]) ? $eCultivation['seedlingSeeds'] : NULL,
				$youngPlantTray,
				$sowingDate,
				$plantingDate,
				$minHarvestDate,
				$maxHarvestDate,
				($eSeries['use'] === Series::BLOCK) ? $eSeries['area'] ?? $eSeries['areaTarget'] ?? NULL : NULL,
				($eSeries['use'] === Series::BLOCK and $eCultivation['distance'] === Cultivation::DENSITY) ? $eCultivation['density'] : NULL,
				($eSeries['use'] === Series::BLOCK and $eCultivation['distance'] === Cultivation::SPACING) ? $eCultivation['rowSpacing'] : NULL,
				($eSeries['use'] === Series::BLOCK and $eCultivation['distance'] === Cultivation::SPACING) ? $eCultivation['plantSpacing'] : NULL,
				($eSeries['use'] === Series::BED) ? $eSeries['length'] ?? $eSeries['lengthTarget'] ?? NULL : NULL,
				($eSeries['use'] === Series::BED and $eCultivation['distance'] === Cultivation::DENSITY) ? $eCultivation['density'] : NULL,
				($eSeries['use'] === Series::BED and $eCultivation['distance'] === Cultivation::SPACING) ? $eCultivation['rows'] : NULL,
				($eSeries['use'] === Series::BED and $eCultivation['distance'] === Cultivation::SPACING) ? $eCultivation['plantSpacing'] : NULL,
				$eSeries['status'] === Series::CLOSED ? 'yes' : 'no',
				$eCultivation['mainUnit'],
				\util\TextUi::csvNumber($eCultivation['yieldExpected']),
				\util\TextUi::csvNumber(($eCultivation['harvestedNormalized'] !== NULL and $eSeries['area'] !== NULL) ? round($eCultivation['harvestedNormalized'] / $eSeries['area'], 1) : NULL),
			];

			if($eCultivation['cSlice']->notEmpty()) {

				$slices = [];

				$limit = match($eCultivation['varietiesUnit']) {
					Cultivation::PERCENT => 100,
					Cultivation::LENGTH => ($eSeries['use'] === Series::BED) ? ($eSeries['length'] ?? $eSeries['lengthTarget']) : NULL,
					Cultivation::AREA => ($eSeries['use'] === Series::BLOCK) ? ($eSeries['area'] ?? $eSeries['areaTarget']) : NULL,
					Cultivation::PLANT => $eCultivation['cSlice']->sum('partPlant'),
					Cultivation::TRAY => $eCultivation['cSlice']->sum('partTray'),
				};

				foreach($eCultivation['cSlice'] as $eSlice) {

					$slices[] = [
						'variety' => $eSlice['variety']['name'],
						'part' => $limit ? (int)($eSlice['part'.ucfirst($eCultivation['varietiesUnit'])] / $limit * 100) : NULL
					];

				}


				if($eCultivation['varietiesUnit'] !== Cultivation::PERCENT) {

					$required = match($eCultivation['varietiesUnit']) {
						Cultivation::LENGTH => $limit ? (int)($eCultivation['cSlice']->sum('partLength') / $limit) : NULL,
						Cultivation::AREA => $limit ? (int)($eCultivation['cSlice']->sum('partArea') / $limit) : NULL,
						Cultivation::PLANT => 100,
						Cultivation::TRAY => 100,
					};

					$rest = $required - array_sum(array_column($slices, 'part'));

					for($i = 0; $i < $rest; $i++) {
						$slices[$i % count($slices)]['part']++;
					}

				}

				foreach($slices as $slice) {
					$line[] = $slice['variety'];
					$line[] = $slice['part'];
				}

			}

			$output[] = $line;

		}

		return $output;

	}

	public static function getExportSoil(\structure\Structure $eStructure, int $season, &$maxSpecies): array {

		$cPlace = Place::model()
			->select([
				'zone' => ['name'],
				'plot' => ['name', 'zoneFill'],
				'bed' => ['name', 'plotFill'],
				'length',
				'area',
				'series' => [
					'season',
					'name', 'bedStartCalculated', 'bedStopCalculated',
					'cCultivation' => Cultivation::model()
						->select([
							'series',
							'plant' => ['name']
						])
						->delegateCollection('series')
				]
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->whereSeries('!=', NULL)
			->getCollection()
			->sort([
				'zone' => ['name'],
				'plot' => ['name'],
				'bed' => ['name'],
			], natural: TRUE);

		$maxSpecies = 0;

		$output = [];

		foreach($cPlace as $ePlace) {

			$maxSpecies = max($maxSpecies, $ePlace['series']['cCultivation']->count());

			$line = [
				$ePlace['zone']['name'],
				$ePlace['plot']['zoneFill'] ? '' : $ePlace['plot']['name'],
				$ePlace['bed']['plotFill'] ? '' : $ePlace['bed']['name'],
				$ePlace['series']['id'],
				$ePlace['series']['name'],
				\util\TextUi::csvNumber($ePlace['length']),
				\util\TextUi::csvNumber($ePlace['area']),
				$ePlace['series']->getBedStart(),
				$ePlace['series']->getBedStop(),
			];

			foreach($ePlace['series']['cCultivation'] as $eCultivation) {
				$line[] = $eCultivation['plant']['name'];
			}

			$output[] = $line;

		}

		return $output;

	}

	public static function uploadCultivations(\structure\Structure $eStructure): bool {

		return \main\CsvLib::upload('import-cultivations-'.$eStructure['id'], function($csv) {

			$header = $csv[0];

			if(count(array_intersect($header, ['crop', 'in_greenhouse', 'planting_type', 'unit', 'first_harvest_date'])) === 5) {

				$csv = self::convertFromBrinjel($csv);

			} else if(count(array_intersect($header, ['crop', 'in_greenhouse', 'planting_type', 'unit', 'beg_harvest_date'])) === 5) {

				$csv = self::convertFromQrop($csv);

			} else if(count(array_intersect($header, ['series_name', 'season', 'mode', 'species', 'use', 'planting_type', 'harvest_unit'])) === 7) {
				$csv = self::convertFromOtf($csv);
				if($csv === NULL) {
					return NULL;
				}
			} else {
				\Fail::log('main\csvSource');
				return NULL;
			}

			return $csv;

		});

	}

	public static function convertFromQrop(array $cultivations): array {

		$head = $cultivations[0];

		$key = array_find_key($head, fn($value) => $value === 'beg_harvest_date');
		if($key !== NULL) {
			$cultivations[0][$key] = 'first_harvest_date';
		}

		$key = array_find_key($head, fn($value) => $value === 'end_harvest_date');
		if($key !== NULL) {
			$cultivations[0][$key] = 'last_harvest_date';
		}

		return self::convertFromBrinjel($cultivations);

	}

	public static function convertFromBrinjel(array $cultivations): array {

		$import = [];

		$head = array_shift($cultivations);

		foreach($cultivations as $cultivation) {

			if(count($cultivation) < count($head)) {
				$cultivation = array_merge($cultivation, array_fill(0, count($head) - count($cultivation), ''));
			} else if(count($head) < count($cultivation)) {
				$cultivation = array_slice($cultivation, 0, count($head));
			}

			$line = array_combine($head, $cultivation) + [
				'crop' => '',
				'sowing_date' => '',
				'planting_date' => '',
				'first_harvest_date' => '',
				'last_harvest_date' => '',
				'length' => '',
				'rows' => '',
				'planting_type' => '',
				'variety' => '',
				'provider' => '',
				'finished' => '',
				'in_greenhouse' => '',
				'price_per_unit' => '',
				'spacing_plants' => '',
				'unit' => '',
				'yield_per_bed_meter' => '',
				'estimated_greenhouse_loss' => '',
				'seeds_per_gram' => '',
				'seeds_per_hole_seedling' => '',
				'seeds_per_hole_direct' => '',
				'seeds_extra_percentage' => '',
				'container_name' => '',
				'container_size' => ''
			];

			$sowing = \main\CsvLib::formatDateField($line['sowing_date'] ?: NULL);
			$planting = \main\CsvLib::formatDateField($line['planting_date'] ?: NULL);

			// planting_type
			$plantingType = match($line['planting_type'] ?? NULL) {
				// Brinjel, Qrop
				'direct_seeded', '1' => Cultivation::SOWING,
				'transplant_raised', '2' => Cultivation::YOUNG_PLANT,
				'transplant_bought', '3' => Cultivation::YOUNG_PLANT_BOUGHT,
				default => NULL
			};

			if($plantingType === NULL) {

				if($sowing !== NULL and $planting === NULL) {
					$plantingType = Cultivation::SOWING;
				} else if($sowing !== NULL and $planting !== NULL) {
					$plantingType = Cultivation::YOUNG_PLANT_BOUGHT;
				} else if($sowing === NULL and $planting !== NULL) {
					$plantingType = Cultivation::YOUNG_PLANT;
				}

			}

			$firstHarvestDate = \main\CsvLib::formatDateField($line['first_harvest_date'] ?: NULL);
			$lastHarvestDate = \main\CsvLib::formatDateField($line['last_harvest_date'] ?: NULL);

			$season = (int)substr($firstHarvestDate ?? $planting ?? $sowing ?? \Clock::date(), 0, 4);
			$mode = (($line['in_greenhouse'] ?? 'false') === 'true') ? Series::GREENHOUSE : Series::OPEN_FIELD;

			$harvestUnit = CsvUi::convertUnit($line['unit']);
			$crop = $line['crop'] ?? NULL;

			$rows = (int)$line['rows'] ?: NULL;
			$spacingPlants = round((float)$line['spacing_plants'], 2) ?: NULL;

			$hash = md5($season.'-'.$mode.'-'.$crop.'-'.$plantingType.'-'.$rows.'-'.$spacingPlants.'-'.$sowing.'-'.$planting.'-'.$harvestUnit.'-'.$firstHarvestDate.'-'.$lastHarvestDate);

			$import[$hash] ??= [
				'series' => [
					'season' => $season,
					'name' => $crop,
					'mode' => $mode,
					'use' => Series::BED,
					'bed_length' => 0,
					'block_area' => NULL,
					'finished' => (($line['finished'] ?? 'false') === 'true'),
				],
				'cultivations' => [
					[
						'species' => $crop,
						'planting_type' => $plantingType,
						'seeds_per_hole' => match($plantingType) {
							Cultivation::SOWING => $line['seeds_per_hole_direct'] ? (int)$line['seeds_per_hole_direct'] : NULL,
							default => $line['seeds_per_hole_seedling'] ? (int)$line['seeds_per_hole_seedling'] : NULL
						},
						'young_plants_tray' => $line['container_name'] ?: NULL,
						'young_plants_tray_size' => $line['container_size'] ?: NULL,
						'sowing_date' => $sowing,
						'planting_date' => $planting,
						'first_harvest_date' => $firstHarvestDate,
						'last_harvest_date' => $lastHarvestDate,
						'block_density' => NULL,
						'block_spacing_rows' => NULL,
						'block_spacing_plants' => NULL,
						'bed_density' => NULL,
						'bed_rows' => $rows,
						'bed_spacing_plants' => $spacingPlants,
						'harvest_unit' => $harvestUnit,
						'yield_expected_area' => NULL,
						'yield_expected_length' => (float)$line['yield_per_bed_meter'] ? \main\CsvLib::formatFloat($line['yield_per_bed_meter']) : NULL,
						'varieties_unit' => Cultivation::LENGTH,
						'varieties_list' => [],
						'varieties' => []
					]
				]
			];

			// Une planche sans longueur compte un mètre, et une longueur de moins d'un mètre aussi :
			// chaque variété garde ainsi une part, et la longueur de la série n'est jamais nulle
			$length = max(1, (int)round(\main\CsvLib::formatFloat($line['length'])));

			$import[$hash]['series']['bed_length'] += $length;

			if($line['variety'] !== '') {

				$key = array_search($line['variety'], $import[$hash]['cultivations'][0]['varieties_list'], TRUE);

				if($key === FALSE) {

					$import[$hash]['cultivations'][0]['varieties_list'][] = $line['variety'];
					$import[$hash]['cultivations'][0]['varieties'][] = [
						'variety' => $line['variety'],
						'eVariety' => new \plant\Variety(),
						'part' => $length
					];

				} else {
					$import[$hash]['cultivations'][0]['varieties'][$key]['part'] += $length;
				}

			}

		}

		// Retraitement des variétés pour passer de LENGTH à PERCENT
		foreach($import as $hash => ['cultivations' => $cultivations]) {

			$lengths = array_column($cultivations[0]['varieties'], 'part');

			if($lengths === []) {
				continue;
			}

			$total = array_sum($lengths);

			// Une variété de quelques mètres dans une longue série tomberait à 0 %, part que la
			// création des cultures refuse (sequence\Crop::variety.partZero) pour toute la série
			$parts = array_map(fn(int $length) => max(1, (int)floor($length / $total * 100)), $lengths);
			$parts[array_search(max($parts), $parts, TRUE)] += 100 - array_sum($parts);

			foreach($parts as $key => $part) {
				$import[$hash]['cultivations'][0]['varieties'][$key]['part'] = $part;
			}

			$import[$hash]['cultivations'][0]['varieties_unit'] = Cultivation::PERCENT;

		}

		return $import;

	}

	public static function convertFromOtf(array $cultivations): ?array {

		$import = [];

		$head = array_shift($cultivations);

		// Recherche des colonnes liées aux variétés
		$varietiesIndex = [];

		foreach($head as $index => $column) {

			if($column !== 'variety_name') {
				continue;
			}

			if(
				array_key_exists($index + 1, $head) === FALSE or
				$head[$index + 1] !== 'variety_part'
			) {
				\Fail::log('csvVariety');
				return NULL;
			}

			$varietiesIndex[] = $index;

		}

		$seriesIndex = [];

		foreach($cultivations as $cultivation) {

			if(count($cultivation) < count($head)) {
				$cultivation = array_merge($cultivation, array_fill(0, count($head) - count($cultivation), ''));
			} else if(count($head) < count($cultivation)) {
				$cultivation = array_slice($cultivation, 0, count($head));
			}

			$line = array_combine($head, $cultivation) + [
				'season' => '',
				'series_id' => '',
				'series_name' => '',
				'mode' => '',
				'use' => '',
				'species' => '',
				'planting_type' => '',
				'seeds_per_hole' => '',
				'young_plants_tray' => '',
				'sowing_date' => '',
				'planting_date' => '',
				'first_harvest_date' => '',
				'last_harvest_date' => '',
				'block_area' => '',
				'block_density' => '',
				'block_spacing_rows' => '',
				'block_spacing_plants' => '',
				'bed_length' => '',
				'bed_density' => '',
				'bed_rows' => '',
				'bed_spacing_plants' => '',
				'finished' => '',
				'harvest_unit' => '',
				'yield_expected_area' => '',
			];

			$varieties = [];

			foreach($varietiesIndex as $varietyIndex) {

				if($cultivation[$varietyIndex] !== '') {

					$varieties[] = [
						'variety' => $cultivation[$varietyIndex],
						'part' => (int)$cultivation[$varietyIndex + 1],
						'eVariety' => new \plant\Variety()
					];

				}

			}

			$season = (int)($line['season'] ?: \Clock::year());
			$seriesName = $line['series_name'] ?: NULL;
			$seriesId = $line['series_id'] ?: NULL;

			if($seriesId) {

				if(array_key_exists($seriesId, $seriesIndex)) {
					$index = $seriesIndex[$seriesId];
				} else {

					$index = count($import);
					$seriesIndex[$seriesId] = $index;

					$import[$index] = [
						'series' => [
							'season' => $season,
							'name' => $seriesName,
							'mode' => $line['mode'] ?: NULL,
							'use' => $line['use'] ?: NULL,
							'bed_length' => (int)round((float)$line['bed_length']) ?: NULL,
							'block_area' => (int)round((float)$line['block_area']) ?: NULL,
							'finished' => (($line['finished'] ?? 'no') === 'yes'),
						],
						'cultivations' => []
					];

				}

			} else {

				$index = count($import);

				$import[$index] = [
					'series' => [
						'season' => $season,
						'name' => $seriesName,
						'mode' => $line['mode'] ?: NULL,
						'use' => $line['use'] ?: NULL,
						'bed_length' => (int)round((float)$line['bed_length']) ?: NULL,
						'block_area' => (int)round((float)$line['block_area']) ?: NULL,
						'finished' => (($line['finished'] ?? 'no') === 'yes'),
					],
					'cultivations' => []
				];

			}

			$import[$index]['cultivations'][] = [
				'species' => $line['species'] ?? NULL,
				'planting_type' => $line['planting_type'] ?: NULL,
				'seeds_per_hole' => $line['seeds_per_hole'] ? (int)$line['seeds_per_hole'] : NULL,
				'young_plants_tray' => $line['young_plants_tray'] ?: NULL,
				'young_plants_tray_size' => NULL,
				'sowing_date' => $line['sowing_date'] ?: NULL,
				'planting_date' => $line['planting_date'] ?: NULL,
				'first_harvest_date' => $line['first_harvest_date'] ?: NULL,
				'last_harvest_date' => $line['last_harvest_date'] ?: NULL,
				'block_density' => (float)$line['block_density'] ? \main\CsvLib::formatFloat($line['block_density']) : NULL,
				'block_spacing_rows' => (int)round((float)$line['block_spacing_rows']) ?: NULL,
				'block_spacing_plants' => $line['block_spacing_plants'] ? \main\CsvLib::formatFloat($line['block_spacing_plants']) : NULL,
				'bed_density' => $line['bed_density'] ? \main\CsvLib::formatFloat($line['bed_density']) : NULL,
				'bed_rows' => (int)round((float)$line['bed_rows']) ?: NULL,
				'bed_spacing_plants' => $line['bed_spacing_plants'] ? \main\CsvLib::formatFloat($line['bed_spacing_plants']) : NULL,
				'harvest_unit' => $line['harvest_unit'] ?: NULL,
				'yield_expected_area' => (float)$line['yield_expected_area'] ? \main\CsvLib::formatFloat($line['yield_expected_area']) : NULL,
				'yield_expected_length' => NULL,
				'varieties_unit' => Cultivation::PERCENT,
				'varieties' => $varieties
			];

		}

		// Calcul des noms
		foreach($import as $key => ['series' => $series, 'cultivations' => $cultivations]) {

			if($series['name'] === NULL) {
				$import[$key]['series']['name'] = implode(' + ', array_column($cultivations, 'species'));
			}

		}

		return $import;

	}

	public static function reset(\structure\Structure $eStructure): bool {

		return \Cache::redis()->delete('import-cultivations-'.$eStructure['id']);

	}

	/**
	 * ⚠️ `$reset` dit qu'il y a un cache de téléversement à purger, et cette purge **garde**
	 * l'écriture : elle rend FAUX si quelqu'un a déjà importé ce dépôt, ce qui interdit un second
	 * envoi. Une copie n'a jamais rien déposé dans ce cache — laissée à VRAI, elle préparait les
	 * 85 séries puis n'en écrivait aucune, et la page redirigeait en annonçant le succès.
	 */
	public static function importCultivations(\structure\Structure $eStructure, array $list, bool $reset = TRUE): bool {

		$fw = new \FailWatch();
		$prepared = [];

		$eVarietyEmpty = \plant\VarietyLib::getByFqn('unknown');

		self::prepareSpecies($eStructure, $list);

		foreach($list as ['series' => $series, 'cultivations' => $cultivations]) {

			$input = [
				'structure' => $eStructure,
				'name' => $series['name'],
				'use' => $series['use'],
				'cycle' => Series::ANNUAL,
				'mode' => $series['mode'],
				'season' => $series['season'],
				'sequence' => new \sequence\Sequence(),
				'areaTarget' => ($series['block_area'] <= 0) ? NULL : $series['block_area'],
				'lengthTarget' => ($series['bed_length'] <= 0) ? NULL : $series['bed_length'],
				'bedWidth' => $series['use'] === Series::BED ? $eStructure->conf(\map\Configuration::class)['defaultBedWidth'] : NULL,
				'alleyWidth' => $series['use'] === Series::BED ? $eStructure->conf(\map\Configuration::class)['defaultAlleyWidth'] : NULL,
				'plant' => []
			];

			$position = 0;

			foreach($cultivations as $cultivation) {

				$input['plant'][$position] = $cultivation['ePlant']['id'];
				$input['varietiesUnit'][$position] = $cultivation['varieties_unit'];
				$input['seedling'][$position] = $cultivation['planting_type'];
				$input['seedlingSeeds'][$position] = ($cultivation['seeds_per_hole'] ?: 1);
				$input['mainUnit'][$position] = $cultivation['harvest_unit'];
				$input['varieties'][$position] = [
					'variety' => [],
					'varietyCreate' => [],
					'varietyPartPercent' => []
				];

				switch($input['seedling'][$position]) {

					case Cultivation::SOWING :
						$input['actions'][$position] = [
							ACTION_SEMIS_DIRECT => $cultivation['sowing_date'] ? toWeek($cultivation['sowing_date']) : NULL
						];
						break;

					case Cultivation::YOUNG_PLANT :
						$input['actions'][$position] = [
							ACTION_SEMIS_PEPINIERE => $cultivation['sowing_date'] ? toWeek($cultivation['sowing_date']) : NULL,
							ACTION_PLANTATION => $cultivation['planting_date'] ? toWeek($cultivation['planting_date']) : NULL,
						];
						break;

					case Cultivation::YOUNG_PLANT_BOUGHT :
						$input['actions'][$position] = [
							ACTION_PLANTATION => $cultivation['planting_date'] ? toWeek($cultivation['planting_date']) : NULL,
						];
						break;

				}

				switch($series['use']) {

					case Series::BED :
						$input['distance'][$position] = $cultivation['bed_density'] ? Cultivation::DENSITY : Cultivation::SPACING;
						$input['density'][$position] = $cultivation['bed_density'];
						$input['rows'][$position] = $cultivation['bed_rows'];
						$input['plantSpacing'][$position] = $cultivation['bed_spacing_plants'];
						$input['rowSpacing'][$position] = NULL;

						if($cultivation['yield_expected_area'] !== NULL) {
							$input['yieldExpected'][$position] = $cultivation['yield_expected_area'];
						} else if($cultivation['yield_expected_length'] !== NULL) {
							$input['yieldExpected'][$position] = round($cultivation['yield_expected_length'] * ($input['bedWidth'] + $input['alleyWidth'] ?? 0) / 100, 1);
						} else {
							$input['yieldExpected'][$position] = NULL;
						}
						break;

					case Series::BLOCK :
						$input['distance'][$position] = $cultivation['block_density'] ? Cultivation::DENSITY : Cultivation::SPACING;
						$input['density'][$position] = $cultivation['block_density'];
						$input['rows'][$position] = NULL;
						$input['plantSpacing'][$position] = $cultivation['block_spacing_plants'];
						$input['rowSpacing'][$position] = $cultivation['block_spacing_rows'];
						$input['yieldExpected'][$position] = $cultivation['yield_expected_area'];
						break;

				}

				// variety
				foreach($cultivation['varieties'] as ['variety' => $variety, 'eVariety' => $eVariety, 'part' => $part]) {

					$input['varieties'][$position]['variety'][] = $eVariety->empty() ? 'new' : $eVariety['id'];
					$input['varieties'][$position]['varietyCreate'][] = $eVariety->empty() ? $variety : NULL;
					$input['varieties'][$position][match($cultivation['varieties_unit']) {
						Cultivation::LENGTH => 'varietyPartLength',
						Cultivation::PERCENT => 'varietyPartPercent'
					}][] = $part;

				}

				if($input['varieties'][$position]['variety'] === []) {

					$input['varietiesUnit'][$position] = Cultivation::PERCENT;

					// L'identifiant, et non l'élément : `SliceLib::prepare()` reçoit ici ce que
					// poste le formulaire, une valeur de champ. Un élément y traversait la
					// conversion en chaîne de `Element::__toString()`, et la variété sans nom
					// n'était donc jamais retrouvée (`sequence\Crop::variety.notExists`).
					$input['varieties'][$position]['variety'][] = $eVarietyEmpty['id'];
					$input['varieties'][$position]['varietyCreate'][] = NULL;
					$input['varieties'][$position]['varietyPartPercent'][] = 100;

				}

				// harvest
				if($cultivation['first_harvest_date']) {

					$weeks = [];

					$firstWeek = toWeek($cultivation['first_harvest_date']);
					$lastWeek = toWeek($cultivation['last_harvest_date']);

					for($week = $firstWeek; $week <= $lastWeek; $week = toWeek(strtotime($week.' + 1 WEEK'))) {
						$weeks[] = $week;
					}

					$input['harvestPeriodExpected'][$position] = Cultivation::WEEK;
					$input['harvestWeeksExpected'][$position] = $weeks;
					$input['harvestMonthsExpected'][$position] = NULL;


				} else {

					$input['harvestPeriodExpected'][$position] = Cultivation::WEEK;
					$input['harvestWeeksExpected'][$position] = NULL;
					$input['harvestMonthsExpected'][$position] = NULL;

				}

				$position++;

			}

			$prepare = \series\SeriesLib::prepareCreate($input);

			if($fw->ko()) {
				return FALSE;
			}

			$prepare[0]['status'] = ($series['finished'] === TRUE) ? Series::CLOSED : Series::OPEN;

			$prepared[] = $prepare;

		}

		if($reset and self::reset($eStructure) === FALSE) {
			return FALSE;
		}

		foreach($prepared as $prepare) {
			\series\SeriesLib::createWithCultivations(...$prepare);
		}

		return TRUE;

	}

	/**
	 * Ce qui reste d'un import une fois écartées les cultures que l'analyse refuse.
	 *
	 * ⚠️ C'est la troisième issue d'un écran de conflits, et elle s'appelle « copier sans ces
	 * données » et non « corriger plus tard » : rien ne rappellera de corriger, et les séries
	 * arriveront amputées. Le bouton qui l'ouvre doit donc nommer ce qu'on perd.
	 *
	 * ⚠️ Elle ne peut rien contre les erreurs **globales** — une largeur de planche absente de la
	 * ferme d'arrivée, un outil inconnu — qui ne tiennent à aucune ligne et qu'aucun retrait de
	 * ligne ne répare. L'appelant les pèse avant de proposer cette issue.
	 *
	 * Une série vidée de toutes ses cultures disparaît : une série sans culture n'est pas une
	 * série amputée, c'est une série vide.
	 */
	public static function applyImportWithoutErrors(array $import): array {

		$kept = [];

		foreach($import as $entry) {

			$cultivations = array_values(array_filter(
				$entry['cultivations'],
				fn(array $cultivation) => ($cultivation['errors'] ?? []) === []
			));

			if($cultivations === []) {
				continue;
			}

			$entry['cultivations'] = $cultivations;

			$kept[] = $entry;

		}

		return $kept;

	}

	/**
	 * Les espèces que l'écriture va servir, réactivées ou créées selon le cas.
	 *
	 * ⚠️ Appelée avant l'écriture et non pendant : une série qui pointerait une espèce désactivée
	 * s'écrirait sans erreur et n'apparaîtrait nulle part ensuite, l'écran de production ne
	 * servant que les espèces actives. Et une culture dont l'espèce est absente n'a aucun `ePlant`
	 * à poser — la création échouerait sur une valeur nulle, loin de sa cause.
	 *
	 * Ne fait rien sur un import de fichier : l'analyse y a bloqué toute espèce inconnue, donc il
	 * n'en reste aucune à créer et aucune désactivée à réveiller.
	 *
	 * ⚠️ La création reprend `reference` de l'espèce du référentiel partagé quand il y en a une :
	 * sans elle, la copie naîtrait orpheline et perdrait ce que le référentiel lui apporte, là où
	 * `PlantLib::duplicateForStructure()` la relie à sa mère pour toutes les autres.
	 */
	private static function prepareSpecies(\structure\Structure $eStructure, array &$list): void {

		// ⚠️ Les espèces créées ici se mémorisent, sinon deux cultures d'une même espèce inconnue
		// la créent deux fois : la seconde `create()` bute sur l'unicité, `PlantLib` la transforme
		// en `Plant::fail('name.duplicate')`, et `importCultivations()` sort sur `$fw->ko()` —
		// l'espèce est écrite, aucune série ne l'est, et le geste échoue en silence. Mesuré : la
		// ferme 17 porte huit espèces sans référence à deux cultures ou plus. La clé est le `fqn`
		// et non le nom, comme `$cachePlants` dans l'analyse : c'est lui que la recherche tolérante
		// compare, et deux saisies différentes du même nom doivent donner la même espèce.
		$cachePlants = [];

		foreach($list as $key1 => ['cultivations' => $cultivations]) {

			foreach($cultivations as $key2 => $cultivation) {

				$ePlant = $cultivation['ePlant'] ?? new \plant\Plant();

				if($ePlant->notEmpty()) {

					if(($ePlant['status'] ?? \plant\Plant::ACTIVE) !== \plant\Plant::ACTIVE) {

						$ePlant['status'] = \plant\Plant::ACTIVE;

						\plant\PlantLib::update($ePlant, ['status']);

					}

					continue;

				}

				if($cultivation['species'] === NULL) {
					continue;
				}

				$species = $cultivation['species'];
				$speciesFqn = toFqn($species, ' ');

				if(array_key_exists($speciesFqn, $cachePlants)) {

					$list[$key1]['cultivations'][$key2]['ePlant'] = $cachePlants[$speciesFqn];

					continue;

				}

				// La même recherche tolérante que l'analyse, et pas une égalité stricte : le
				// référentiel écrit « Chou pë-tsaï » là où une ferme a pu saisir « chou pe tsai ».
				// Une égalité stricte créait une espèce orpheline à côté de sa mère.
				$eReference = \plant\Plant::model()
					->select(['id', 'name', 'fqn', 'cycle', 'family', 'vignette', 'color'])
					->whereStructure(NULL)
					->or(
						fn() => $this->whereName($species),
						fn() => $this->where('REGEXP_REPLACE(REPLACE(name, "-", " "), " +", " ") = '.\plant\Plant::model()->format($speciesFqn))
					)
					->get();

				// ⚠️ Annuelle par défaut, et la colonne n'accepte pas le vide. C'est le seul cas
				// possible ici : une espèce pérenne fait sauter sa série de la copie (`ignore`),
				// donc aucune culture qui arrive jusqu'ici n'en porte une.
				$ePlantNew = new \plant\Plant([
					'structure' => $eStructure,
					'name' => $species,
					'cycle' => \plant\Plant::ANNUAL,
					'status' => \plant\Plant::ACTIVE
				]);

				if($eReference->notEmpty()) {

					$ePlantNew['reference'] = $eReference;
					$ePlantNew['fqn'] = $eReference['fqn'];
					$ePlantNew['cycle'] = $eReference['cycle'];
					$ePlantNew['family'] = $eReference['family'];
					$ePlantNew['vignette'] = $eReference['vignette'];
					$ePlantNew['color'] = $eReference['color'];

				}

				\plant\PlantLib::create($ePlantNew);

				$cachePlants[$speciesFqn] = $ePlantNew;

				$list[$key1]['cultivations'][$key2]['ePlant'] = $ePlantNew;

			}

		}

	}

	/**
	 * Ce qu'il reste d'une copie qui ne passe pas entièrement, et ce qu'on y perd.
	 *
	 * ⚠️ La liste réduite est **réanalysée**, elle n'est pas déduite du premier rapport. Les
	 * erreurs globales — une largeur de planche que la ferme d'arrivée n'a pas, un outil qu'elle
	 * ne connaît pas — ne tiennent à aucune ligne et aucun retrait de ligne ne les répare :
	 * mesuré, une copie partielle qui les ignorait était refusée par `Series::bedWidth.prepare`
	 * après avoir promis à l'écran qu'elle passerait. La seconde analyse est le seul moyen de
	 * savoir si ce qui reste tient debout, et elle coûte le prix d'une lecture sans écriture.
	 *
	 * `list` vide veut dire qu'il n'y a rien à proposer : ni bouton, ni promesse.
	 */
	public static function getPartialImport(\structure\Structure $eStructure, array $import, ?int $season = NULL, bool $fromStructure = FALSE): array {

		$skipped = 0;

		foreach($import as $entry) {
			foreach($entry['cultivations'] as $cultivation) {
				if(($cultivation['errors'] ?? []) !== []) {
					$skipped++;
				}
			}
		}

		$list = self::applyImportWithoutErrors($import);

		if($list === []) {
			return ['list' => [], 'series' => 0, 'skipped' => $skipped];
		}

		$second = self::getCultivationsFromImport($eStructure, $list, $season, $fromStructure);

		if($second['errorsCount'] > 0) {
			return ['list' => [], 'series' => 0, 'skipped' => $skipped];
		}

		return ['list' => $second['import'], 'series' => count($second['import']), 'skipped' => $skipped];

	}

	/**
	 * Le plan d'une saison d'une ferme, dans la forme normalisée que l'analyse et l'écriture attendent.
	 *
	 * ⚠️ Le plan repasse par le format CSV de Ouvretaferme et par son convertisseur plutôt que
	 * d'être recopié depuis les séries : c'est le même chemin que l'import d'un fichier, donc les
	 * mêmes conflits, les mêmes rattachements d'espèces et de variétés, et un seul code à corriger
	 * le jour où l'un d'eux change. Une copie qui prendrait un raccourci finirait par diverger de
	 * l'import sur des cas que personne ne rejoue.
	 *
	 * L'en-tête est remis en tête avant la conversion : `getExportCultivations()` ne rend que les
	 * lignes, et `convertFromOtf()` lit la première comme l'en-tête. L'oublier fait disparaître la
	 * première série, sans erreur.
	 */
	public static function getImportFromStructure(\structure\Structure $eStructureSource, int $season, \Collection $cSeries = new \Collection()): ?array {

		$maxVarieties = NULL;

		$export = self::getExportCultivations($eStructureSource, $season, $maxVarieties, $cSeries);

		if($export === []) {
			return [];
		}

		array_unshift($export, new CsvUi()->getExportCultivationsHeader($maxVarieties));

		return self::convertFromOtf($export);

	}

	/**
	 * L'analyse d'un import en attente, déposé par un téléversement.
	 */
	public static function getCultivations(\structure\Structure $eStructure): ?array {

		$import = \Cache::redis()->get('import-cultivations-'.$eStructure['id']);

		if($import === FALSE) {
			return NULL;
		}

		return self::getCultivationsFromImport($eStructure, $import);

	}

	/**
	 * L'analyse d'une liste normalisée contre la ferme qui la recevra.
	 *
	 * ⚠️ La provenance ne l'intéresse pas : un fichier téléversé et le plan d'une autre ferme
	 * arrivent sous la même forme, et les cinq familles de conflits se posent à l'identique.
	 * Séparer cette analyse de la lecture du cache est ce qui permet à la copie de réutiliser
	 * l'écran de conflits sans le réécrire.
	 *
	 * `$season` est la saison **d'arrivée**, quand l'appelant la demande. Elle remplace celle que
	 * la source portait, et fait disparaître une famille entière de conflits : une saison absente
	 * de la ferme d'arrivée était une erreur bloquante que rien ne savait réparer. On ne signale
	 * plus le conflit, on demande où atterrir.
	 *
	 * ⚠️ `$fromStructure` dit que la source est **le produit lui-même** — le plan d'une autre
	 * ferme — et non un fichier déposé. Le même symptôme n'a alors pas la même cause, donc pas le
	 * même remède : une espèce inconnue dans un fichier est le plus souvent une faute de frappe,
	 * et bloquer protège ; venue d'une autre ferme, elle existe forcément quelque part. Mesuré
	 * entre deux fermes de dev : sur 37 espèces que l'arrivée ne servait pas, 33 y étaient déjà,
	 * désactivées — `PlantLib::duplicateForStructure()` copie tout le référentiel sur chaque ferme
	 * à sa création. Demander d'« ajouter Chou fleur » à une ferme qui l'a déjà n'a pas de sens.
	 */
	public static function getCultivationsFromImport(\structure\Structure $eStructure, array $import, ?int $season = NULL, bool $fromStructure = FALSE): array {

		$errorsCount = 0;
		$errorsGlobal = [
			'beds' => FALSE,
			'harvestUnit' => [],
			'species' => [],
			'tools' => [],
			'seasons' => []
		];
		$infoGlobal = [
			'varieties' => [],
			'speciesActivate' => [],
			'speciesCreate' => [],
			'speciesPerennial' => [],
			'toolsMissing' => [],
			'beds' => FALSE,
		];

		$cachePlants = [];
		$cacheVarieties = [];
		$cacheTools = [];

		$cVarietyShared = \plant\Variety::model()
			->select(['id', 'name'])
			->whereId('IN', [\plant\PlantSetting::VARIETY_MIX, \plant\PlantSetting::VARIETY_UNKNOWN])
			->getCollection();

		$sharedVarieties = [];

		foreach($cVarietyShared as $eVarietyShared) {
			$sharedVarieties[toFqn($eVarietyShared['name'], ' ')] = $eVarietyShared;
		}

		foreach($import as $key1 => ['series' => $series, 'cultivations' => $cultivations]) {

			$errorsCommon = [];

			if($season !== NULL) {

				$series['season'] = $season;
				$import[$key1]['series']['season'] = $season;

			}

			if(in_array($series['mode'], Series::model()->getPropertyEnum('mode')) === FALSE) {
				$errorsCommon[] = 'modeInvalid';
			}

			if(in_array($series['use'], Series::model()->getPropertyEnum('use')) === FALSE) {
				$errorsCommon[] = 'useInvalid';
			}

			if($series['use'] === Series::BED) {
				$infoGlobal['beds'] = TRUE;
				if($eStructure->conf(\map\Configuration::class)['defaultBedWidth'] === NULL) {
					$errorsGlobal['beds'] = TRUE;
				}
			}

			if(
				$series['season'] < $eStructure->conf(\map\Configuration::class)['seasonFirst'] or
				$series['season'] > $eStructure->conf(\map\Configuration::class)['seasonLast']
			) {
				$errorsGlobal['seasons'][] = $series['season'];
			}

			$species = array_count_values(array_column($cultivations, 'species'));

			foreach($cultivations as $key2 => $cultivation) {

				$ignore = FALSE;
				$errors = $errorsCommon;

				if($cultivation['species'] === NULL) {
					$errors[] = 'speciesEmpty';
					$ePlant = new \plant\Plant();
				} else {

					if($species[$cultivation['species']] >= 2) {
						$errors[] = 'speciesDuplicate';
					}

					// crop
					$plantFqn = toFqn($cultivation['species'], ' ');

					if(empty($cachePlants[$plantFqn])) {

						$cachePlants[$plantFqn] = \plant\Plant::model()
							// `structure` et `status` sont là pour la réactivation d'une espèce
							// désactivée : `PlantLib::update()` exige la première, et sans la
							// seconde on ne saurait pas qu'il y a quelque chose à réactiver.
							->select(['id', 'vignette', 'fqn', 'name', 'cycle', 'status', 'structure'])
							->whereStructure($eStructure)
							->whereStatus(\plant\Plant::ACTIVE, if: $fromStructure === FALSE)
							->or(
			                fn() => $this->whereName($cultivation['species']),
			                fn() => $this->where('REGEXP_REPLACE(REPLACE(name, "-", " "), " +", " ") = '.\plant\Plant::model()->format($plantFqn))
							)
							->get();

					}

					$ePlant = $cachePlants[$plantFqn];

					if($cachePlants[$plantFqn]->empty()) {

						// Venue d'une autre ferme, l'espèce existe forcément quelque part : elle
						// se crée comme les variétés se créent déjà. Venue d'un fichier, elle
						// bloque — c'est le seul endroit où la provenance change la règle.
						if($fromStructure) {
							$infoGlobal['speciesCreate'][] = $cultivation['species'];
						} else {
							$errorsGlobal['species'][] = $cultivation['species'];
						}

					} else if($cachePlants[$plantFqn]['status'] !== \plant\Plant::ACTIVE) {

						// Désactivée et non absente : la copie la réactive et le dit. Ce n'est pas
						// un conflit, c'est une conséquence.
						$infoGlobal['speciesActivate'][] = $cultivation['species'];

					} else if($cachePlants[$plantFqn]['cycle'] === \plant\Plant::PERENNIAL) {
						$infoGlobal['speciesPerennial'][] = $cultivation['species'];
						$ignore = TRUE;
					}

					// varieties
					if($ignore === FALSE) {

						$varietyTotal = 0;

						foreach($cultivation['varieties'] as $key3 => ['variety' => $variety, 'part' => $part]) {

							$varietyFqn = toFqn($variety, ' ');
							$varietyTotal += (int)$part;

							$eVariety = new \plant\Variety();

							if(isset($sharedVarieties[$varietyFqn])) {
								$eVariety = $sharedVarieties[$varietyFqn];
							} else if($ePlant->notEmpty()) {

								$cacheKey = $ePlant['id'].'-'.$varietyFqn;

								if(empty($cacheVarieties[$cacheKey])) {

									$cacheVarieties[$cacheKey] = \plant\Variety::model()
										->select(['id', 'name'])
										->whereStructure($eStructure)
										->wherePlant($ePlant)
										->or(
						                fn() => $this->whereName($variety),
						                fn() => $this->where('REGEXP_REPLACE(REPLACE(name, "-", " "), " +", " ") = '.\plant\Variety::model()->format($varietyFqn))
										)
										->get();

								}

								$eVariety = $cacheVarieties[$cacheKey];

							}

							$import[$key1]['cultivations'][$key2]['varieties'][$key3]['eVariety'] = $eVariety;

							if((int)$part <= 0) {
								$errors[] = 'varietyPartZero';
							}

							if($eVariety->empty()) {

								if(\plant\Variety::model()->check('name', $variety)) {
									$infoGlobal['varieties'][] = $variety;
								} else {
									$errors[] = 'varietyName';
								}

							}

						}

					}

					// trays
					if($cultivation['young_plants_tray']) {

						$tool = $cultivation['young_plants_tray'];
						$toolFqn = toFqn($cultivation['young_plants_tray'], ' ');

						if(empty($cacheTools[$toolFqn])) {

							$cacheTools[$toolFqn] = \work\Tool::model()
								->select(['id', 'name'])
								->whereStructure($eStructure)
								->or(
				                fn() => $this->whereName($tool),
				                fn() => $this->where('REGEXP_REPLACE(REPLACE(name, "-", " "), " +", " ") = '.\work\Tool::model()->format($toolFqn))
								)
								->get();

						}

						$eTool = $cacheTools[$toolFqn];

						if($eTool->empty()) {

							// ⚠️ Venue d'une autre ferme, l'absence d'un plateau n'écarte pas la
							// culture : `Cultivation.varietiesTool` est nullable, donc une culture
							// sans plateau existe très bien. Perdre l'espèce, les dates, les
							// densités et les rendements pour un accessoire de conduite reviendrait
							// à ne copier que la moitié du plan — mesuré, 105 cultures sur 190.
							// La copie passe sans le plateau et le dit.
							//
							// Sur un fichier, le même symptôme a une autre cause : un plateau
							// inconnu y est le plus souvent une faute de frappe, et bloquer protège.
							// La copie, elle, tient le plateau d'une ferme où il existe vraiment.
							if($fromStructure) {
								$infoGlobal['toolsMissing'][] = $cultivation['young_plants_tray'];
							} else {
								$errorsGlobal['tools'][] = $cultivation['young_plants_tray'];
							}

						}

					} else {
						$eTool = new \work\Tool();
					}

					$import[$key1]['cultivations'][$key2]['eTool'] = $eTool;

					if($cultivation['planting_type'] !== NULL and in_array($cultivation['planting_type'], Cultivation::model()->getPropertyEnum('seedling')) === FALSE) {
						$errors[] = 'seedlingInvalid';
					}

					$errors[] = \main\CsvLib::checkDateField($cultivation['sowing_date'], 'sowingDateFormat');
					$errors[] = \main\CsvLib::checkDateField($cultivation['planting_date'], 'plantingDateFormat');
					$errors[] = \main\CsvLib::checkDateField($cultivation['first_harvest_date'], 'firstHarvestDateFormat');
					$errors[] = \main\CsvLib::checkDateField($cultivation['last_harvest_date'], 'lastHarvestDateFormat');

					if(
						$cultivation['first_harvest_date'] === NULL xor
						$cultivation['last_harvest_date'] === NULL
					) {
						$errors[] = 'harvestDateNull';
					} else if(
						$cultivation['first_harvest_date'] !== NULL and
						$cultivation['first_harvest_date'] > $cultivation['last_harvest_date']
					) {
						$errors[] = 'harvestDateConsistency';
					}

					if($cultivation['harvest_unit'] === NULL) {
						$errors[] = 'harvestUnitEmpty';
					} else if(in_array($cultivation['harvest_unit'], Cultivation::model()->getPropertyEnum('mainUnit')) === FALSE) {
						$errorsGlobal['harvestUnit'][] = $cultivation['harvest_unit'];
					}

					if($cultivation['harvest_unit'] === NULL) {
						$errors[] = 'harvestUnitEmpty';
					} else if(in_array($cultivation['harvest_unit'], Cultivation::model()->getPropertyEnum('mainUnit')) === FALSE) {
						$errorsGlobal['harvestUnit'][] = $cultivation['harvest_unit'];
					}

					if(
						$ignore === FALSE and
						$cultivation['varieties'] and
						$cultivation['varieties_unit'] === Cultivation::PERCENT and
						$varietyTotal !== 100
					) {
						$errors[] = 'varietyParts';
					}

					switch($series['use']) {

						case Series::BED :
							if(
								$cultivation['bed_density'] !== NULL and
								($cultivation['bed_rows'] !== NULL or $cultivation['bed_spacing_plants'] !== NULL)
							) {
								$errors[] = 'bedSpacing';
							}
							break;

						case Series::BLOCK :
							if(
								$cultivation['block_density'] !== NULL and
								($cultivation['block_spacing_rows'] !== NULL or $cultivation['block_spacing_rows'] !== NULL)
							) {
								$errors[] = 'blockSpacing';
							}
							break;

					}

				}

				$import[$key1]['cultivations'][$key2]['ePlant'] = $ePlant;

				$errors = array_filter($errors);
				$errors = array_unique($errors);

				$errorsCount += count($errors);

				$import[$key1]['cultivations'][$key2]['errors'] = $errors;
				$import[$key1]['cultivations'][$key2]['ignore'] = $ignore;

			}

		}

		$errorsGlobal['harvestUnit'] = array_unique($errorsGlobal['harvestUnit']);
		$errorsGlobal['species'] = array_unique($errorsGlobal['species']);
		$errorsGlobal['tools'] = array_unique($errorsGlobal['tools']);
		$errorsGlobal['seasons'] = array_unique($errorsGlobal['seasons']);

		$infoGlobal['varieties'] = array_unique($infoGlobal['varieties']);
		$infoGlobal['speciesActivate'] = array_unique($infoGlobal['speciesActivate']);
		$infoGlobal['speciesCreate'] = array_unique($infoGlobal['speciesCreate']);
		$infoGlobal['speciesPerennial'] = array_unique($infoGlobal['speciesPerennial']);
		$infoGlobal['toolsMissing'] = array_unique($infoGlobal['toolsMissing']);

		return [
			'import' => $import,
			'errorsCount' => $errorsCount + (int)$errorsGlobal['beds'] + (int)$errorsGlobal['tools'] + count($errorsGlobal['harvestUnit']) + count($errorsGlobal['species']) + count($errorsGlobal['seasons']),
			'errorsGlobal' => $errorsGlobal,
			'infoGlobal' => $infoGlobal
		];

	}

}
?>
