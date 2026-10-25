<?php
namespace series;

class CultivationLib extends CultivationCrud {

	use \Notifiable;

	public static function getPropertiesCreate(?string $origin = NULL): array {

		$properties = [];

		if($origin === 'api') {
			$properties[] = 'series';
		}

		$properties = array_merge($properties, ['plant', 'distance', 'density', 'rows', 'plantSpacing', 'rowSpacing', 'seedling', 'seedlingSeeds', 'yieldExpected', 'mainUnit', 'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected', 'varietiesUnit', 'varietiesTool', 'varieties']);

		if($origin !== 'api') {
			$properties[] = 'stagger';
			$properties[] = 'actions';
		}

		return $properties;
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {

		$properties = ['yieldExpected', 'mainUnit', 'unitWeight', 'bunchWeight', 'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected', 'plant', 'distance', 'density', 'rows', 'plantSpacing', 'rowSpacing', 'seedling', 'seedlingSeeds', 'varietiesUnit', 'varietiesTool', 'varieties'];

		if($origin !== 'api') {
			$properties[] = 'stagger';
			$properties[] = 'actions';
		}

		return $properties;

	}

	public static function getNew(Series $eSeries, \plant\Plant $ePlant): Cultivation {

		$eSeries->expects(['structure', 'season', 'area', 'length']);
		
		return new Cultivation([
			'structure' => $eSeries['structure'],
			'plant' => $ePlant,
			'cTray' => \work\ToolLib::getTraysByStructure($eSeries['structure']),
			'cSlice' => new \Collection(),
			'series' => $eSeries,
			'season' => $eSeries['season'],
			'sequence' => new \sequence\Sequence(),
			'varietiesUnit' => Cultivation::PERCENT,
			'varietiesTool' => new \work\Tool(),
			'seedling' => NULL,
			'seedlingSeeds' => NULL,
			'distance' => Cultivation::SPACING,
			'density' => NULL,
			'rows' => NULL,
			'rowSpacing' => NULL,
			'plantSpacing' => NULL,
			'area' => $eSeries['area'],
			'length' => $eSeries['length'],
			'harvestPeriodExpected' => Cultivation::MONTH
		]);

	}

	public static function getBySeries(Series|\Collection $eSeries): \Collection {

		return Cultivation::model()
			->select(Cultivation::getSelection())
			->whereSeries('IN', $eSeries, if: $eSeries instanceof \Collection)
			->whereSeries($eSeries, if: $eSeries instanceof Series)
			->getCollection(NULL, NULL, 'id')
			->sort(function(Cultivation $e1, Cultivation $e2) {
				return \L::getCollator()->compare($e1['plant']['name'], $e2['plant']['name']);
			});

	}

	public static function getDensityPlans(Series $eSeries, Cultivation $eCultivation, array $formats): array {

		$eSeries->expects(['use', 'area', 'areaTarget']);

		if($eCultivation['distance'] !== Cultivation::SPACING) {
			return [];
		}

		$build = fn(?int $bedWidth, ?int $alleyWidth) => \sequence\DensityPlanAiLib::get($eSeries['use'], $bedWidth, $alleyWidth, $eCultivation['rows'], $eCultivation['plantSpacing'], $eCultivation['rowSpacing'], $eCultivation['stagger']);

		if($eSeries['use'] === Series::BLOCK) {

			$plan = $build(NULL, NULL);

			if($plan === []) {
				return [];
			}

			return [$plan + ['area' => $eSeries['area'] ?? $eSeries['areaTarget']]];

		}

		$plans = [];

		foreach($formats as $format) {

			$plan = $build($format['bedWidth'], $format['alleyWidth']);

			if($plan !== []) {
				$plans[] = $plan + ['beds' => $format['beds'], 'length' => $format['length']];
			}

		}

		return $plans;

	}

	public static function getBySamePlants(\structure\Structure $eStructure, int $season, \plant\Plant $ePlant): \Collection {

		if($ePlant->empty()) {
			return new \Collection();
		}
		
		$cCultivation = Cultivation::model()
			->select([
				'id',
				'mainUnit', 'density',
				'startWeek', 'startAction',
				'cSlice' => SliceLib::delegateByCultivation(),
				'series' => [
					'name', 'area', 'use', 'mode', 'status',
					'cccPlace' => PlaceLib::delegateBySeries()
				],
				'harvestWeeks', 'harvestWeeksExpected',
				'firstTaskWeek' => self::delegateFirstTaskWeek($eStructure),
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->wherePlant($ePlant)
			->sort(['startWeek' => SORT_ASC])
			->getCollection()
			->filter(fn($eCultivation) => $eCultivation['series']['status'] === Series::OPEN);

		return $cCultivation;

	}

	public static function getForReport(\structure\Structure $eStructure, int $season, \plant\Plant $ePlant): \Collection {

		if($ePlant->empty()) {
			return new \Collection();
		}

		return Cultivation::model()
			->select([
				'id',
				'series' => ['name', 'mode', 'area'],
				'workingTimePlant' => Task::model()
					->group('cultivation')
					->delegateProperty('cultivation', new \Sql('SUM(time)', 'float')),
				'workingTimeShared' => new TimesheetModel()
					->select([
						'propertySource' => new \Sql('m3.id')
					])
					->join(Task::model(), 'm2.id = m1.task')
					->join(Series::model(), 'm3.id = m2.series')
					->where('m2.cultivation', NULL)
					->group(new \Sql('m3.id'))
					->delegateProperty('propertySource', new \Sql('SUM(m1.time / m3.plants)', 'float'), fn($value) => $value ?? 0, 'series'),
				'workingTime' => fn($e) => $e['workingTimePlant'] + $e['workingTimeShared'],
				'firstTaskWeek' => CultivationLib::delegateFirstTaskWeek($eStructure),
				'harvestedByUnit', 'harvestWeeks', 'harvestWeeksExpected'
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->wherePlant($ePlant)
			->sort(['startWeek' => SORT_ASC])
			->getCollection();

	}

	public static function getPlantsBySeason(\structure\Structure $eStructure, int $season): \Collection {

		return Cultivation::model()
			->select([
				'plant' => ['name']
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->group('plant')
			->getColumn('plant');

	}

	public static function getForArea(\structure\Structure $eStructure, int $season): \Collection {

		$ccCultivation = Cultivation::model()
			->select(Cultivation::getSelection())
			->select([
				'series' => [
					'cccPlace' => PlaceLib::delegateBySeries(),
					// Déplacer une série entière n'a de sens que si rien n'est encore
					// fait : une intervention faite ne bouge pas, le geste étirerait
					// la série au lieu de la déplacer.
					'tasksDone' => new TaskModel()
						->whereStatus(Task::DONE)
						->group('series')
						->delegateProperty('series', new \Sql('COUNT(*)', 'int'), fn($value) => $value ?? 0)
				],
				'cTask' => Task::model()
					->select([
						'id',
						'cultivation', 'tools',
						'action' => \work\Action::getSelection(),
						'plannedWeek', 'doneWeek',
						'status'
					])
					->whereAction('IN', \work\ActionLib::getByStructure($eStructure, fqn: [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION]))
					->sort(new \Sql('IF(status="'.\series\Task::TODO.'", plannedWeek, doneWeek), id'))
					->delegateCollection('series', callback: fn(\Collection $cTask, Cultivation $eCultivation) => $cTask->find(fn($eTask) => $eTask['cultivation']->empty() or $eTask['cultivation']->is($eCultivation)), propertyParent: 'series'),
				'firstTaskWeek' => function($e) {

					if($e['cTask']->empty()) {
						return NULL;
					} else {
						$eTask = $e['cTask']->first();
						if($eTask['status'] === Task::TODO) {
							return $eTask['plannedWeek'];
						} else {
							return $eTask['doneWeek'];
						}
					}

				}
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->sort(['startWeek' => SORT_ASC, 'mainUnit' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, ['plant', NULL]);

		self::orderByPlant($ccCultivation);

		self::addSearchTools($eStructure, self::getTasksForSearch($eStructure, $ccCultivation), $ccCultivation);
		self::addSearchIdle($eStructure, $ccCultivation);

		return $ccCultivation;

	}

	// Le filtre de la page cherche aussi dans le matériel, qui ne s'affiche nulle
	// part sur la ligne : les outils de toutes les interventions de la série sont
	// rapportés sur ses cultures.
	protected static function getTasksForSearch(\structure\Structure $eStructure, \Collection $ccCultivation): \Collection {

		return Task::model()
			->select(['cultivation', 'series', 'tools', 'products'])
			->whereStructure($eStructure)
			->whereCultivation('IN', $ccCultivation)
			->getCollection();

	}

	protected static function addSearchTools(\structure\Structure $eStructure, \Collection $cTask, \Collection $ccCultivation): void {

		$cTool = \work\ToolLib::getByStructure($eStructure);

		foreach(\work\RoutineLib::getProducts() as $routineName) {
			$cTool->mergeCollection(\work\ToolLib::getByStructure($eStructure, routineName: $routineName), reindex: FALSE);
		}

		$toolsByCultivation = [];
		$toolsBySeries = [];

		foreach($cTask as $eTask) {

			foreach(array_merge($eTask['products'], $eTask['tools']) as $toolId) {

				if($cTool->offsetExists($toolId) === FALSE) {
					continue;
				}

				$name = $cTool[$toolId]['name'];

				if($eTask['cultivation']->notEmpty()) {
					$toolsByCultivation[$eTask['cultivation']['id']][$name] = TRUE;
				} else if($eTask['series']->notEmpty()) {
					$toolsBySeries[$eTask['series']['id']][$name] = TRUE;
				}

			}

		}

		foreach($ccCultivation as $cCultivation) {
			foreach($cCultivation as $eCultivation) {
				$eCultivation['toolNames'] = array_keys(
					($toolsByCultivation[$eCultivation['id']] ?? []) +
					($toolsBySeries[$eCultivation['series']['id']] ?? [])
				);
			}
		}

	}

	protected static function addSearchIdle(\structure\Structure $eStructure, \Collection $ccCultivation): void {

		$seriesIds = [];

		foreach($ccCultivation as $cCultivation) {
			foreach($cCultivation as $eCultivation) {
				$seriesIds[] = $eCultivation['series']['id'];
			}
		}

		$seriesTodo = Task::model()
			->select('series')
			->whereStructure($eStructure)
			->whereSeries('IN', array_unique($seriesIds))
			->whereStatus(Task::TODO)
			->group('series')
			->getColumn('series')
			->getIds();

		foreach($ccCultivation as $cCultivation) {
			foreach($cCultivation as $eCultivation) {
				$eCultivation['idle'] = (
					$eCultivation['series']->acceptClose() and
					in_array($eCultivation['series']['id'], $seriesTodo) === FALSE
				);
			}
		}

	}

	public static function putSearchVocabulary(\structure\Structure $eStructure, \Collection $cZone): void {

		$cCultivation = new \Collection();
		$cTask = new \Collection();
		$seriesSeen = new \Collection();

		foreach($cZone as $eZone) {
			foreach($eZone['cPlot'] as $ePlot) {
				foreach($ePlot['cBed'] as $eBed) {
					foreach($eBed['cPlace'] as $ePlace) {

						if($ePlace['series']->empty()) {
							continue;
						}

						foreach(($ePlace['series']['cCultivation'] ?? new \Collection()) as $eCultivation) {
							$cCultivation[$eCultivation['id']] = $eCultivation;
						}

						if($seriesSeen->offsetExists($ePlace['series']['id']) === FALSE) {
							$seriesSeen[$ePlace['series']['id']] = $ePlace['series'];
							$cTask->mergeCollection($ePlace['series']['cTask'] ?? new \Collection());
						}

					}
				}
			}
		}

		if($cCultivation->empty()) {
			return;
		}

		$cSlice = Slice::model()
			->select(['cultivation', 'variety' => ['name']])
			->whereCultivation('IN', $cCultivation)
			->getCollection();

		$varieties = [];

		foreach($cSlice as $eSlice) {

			if($eSlice['variety']->empty()) {
				continue;
			}

			$varieties[$eSlice['cultivation']['id']][$eSlice['variety']['name']] = TRUE;

		}

		foreach($cCultivation as $eCultivation) {
			$eCultivation['varietyNames'] = array_keys($varieties[$eCultivation['id']] ?? []);
		}

		self::addSearchTools($eStructure, $cTask, new \Collection([$cCultivation])->setDepth(2));

	}

	public static function getForSelector(\structure\Structure $eStructure, int $season, Series $eSeries = new Series()): \Collection {

		$select = Cultivation::getSelection();
		$select['plant'] = \plant\Plant::getSelection();
		$select['plant']['family'] = ['name', 'fqn', 'color'];

		// Une série clôturée n'a plus rien à faire dans le bac : on ne l'assole plus,
		// et l'y laisser proposait un geste que les routes refusent. Le filtre est
		// dans la requête et non après : le regroupement par espèce attache à la
		// collection ce qu'un tri ultérieur relit, et la reconstruire le perdait.
		$closed = Series::model()
			->select('id')
			->whereStructure($eStructure)
			->whereSeason($season)
			->whereStatus(Series::CLOSED)
			->whereId('!=', $eSeries, if: $eSeries->notEmpty())
			->getCollection()
			->getIds();

		$ccCultivation = Cultivation::model()
			->select($select)
			->whereStructure($eStructure)
			->whereSeason($season)
			->whereSeries('NOT IN', $closed, if: $closed !== [])
			->whereSeries($eSeries, if: $eSeries->notEmpty())
			->sort(['startWeek' => SORT_ASC, 'mainUnit' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, ['plant', NULL]);

		self::orderByPlant($ccCultivation);

		self::putStartColor($eStructure, $ccCultivation);

		return $ccCultivation;

	}

	protected static function putStartColor(\structure\Structure $eStructure, \Collection $ccCultivation): void {

		// Les actions principales plutôt que ces deux-là seules : elles portent les mêmes
		// couleurs, et l'appel garnit `WorkSetting::$mainActions`, dont le bac lit la pastille
		// de semaine comme le reste du produit.
		$cAction = \work\ActionLib::getMainByStructure($eStructure);

		foreach($ccCultivation as $cCultivation) {

			foreach($cCultivation as $eCultivation) {

				$fqn = match($eCultivation['startAction']) {
					Cultivation::SOWING => ACTION_SEMIS_DIRECT,
					Cultivation::PLANTING => ACTION_PLANTATION,
					default => NULL,
				};

				$eCultivation['startColor'] = ($fqn === NULL) ? NULL : ($cAction[$fqn]['color'] ?? NULL);

			}

		}

	}

	public static function getForForecast(\structure\Structure $eStructure, int $season): \Collection {

		$cccCultivation = Cultivation::model()
			->select(Cultivation::getSelection())
			->whereStructure($eStructure)
			->whereSeason($season)
			->sort(['startWeek' => SORT_ASC, 'mainUnit' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, ['plant', 'mainUnit', NULL]);

		self::orderByPlant($cccCultivation);

		return $cccCultivation;

	}

	// La semaine qui date un besoin de semences ou de plants est celle de la
	// première intervention d'implantation, pas celle de la mise en terre : un
	// plant autoproduit se sème en pépinière plusieurs semaines avant d'être
	// planté, et c'est à cette date que la semence doit être là.
	// « Semences à acheter » ne nomme pas un mode d'implantation mais un besoin : un
	// plant qu'on produit soi-même se sème en pépinière, et cette semence s'achète
	// comme celle d'un semis direct.
	protected static function getSeedlingTypes(\Search $search): array {

		return match($search->get('seedling')) {
			Cultivation::SOWING => [Cultivation::SOWING, Cultivation::YOUNG_PLANT],
			default => [$search->get('seedling')]
		};

	}

	// Le filtre choisit les sections autant que les cultures, et les mêmes qu'à
	// l'écran, où le CSS masque déjà les colonnes qu'il exclut.
	protected static function getSeedlingSections(\Search $search): array {

		return match($search->get('seedling')) {
			Cultivation::SOWING => ['seeds'],
			Cultivation::YOUNG_PLANT => ['seeds', 'youngPlantsProduced'],
			Cultivation::YOUNG_PLANT_BOUGHT => ['youngPlantsBought'],
			default => ['seeds', 'youngPlantsProduced', 'youngPlantsBought']
		};

	}

	public static function getSeedlingWeek(): ?string {

		$week = GET('week');

		return \Filter::check('week', $week) ? $week : NULL;

	}

	public static function getForSeedlingByWeek(\structure\Structure $eStructure, int $season, \Search $search): array {

		$select = Cultivation::getSelection();
		$select['firstTaskWeek'] = self::delegateFirstTaskWeek($eStructure);

		$cCultivation = Cultivation::model()
			->select($select)
			->whereSeedling('IN', self::getSeedlingTypes($search), if: $search->get('seedling'))
			->whereStructure($eStructure)
			->whereSeason($season)
			->getCollection();

		$weekSearch = $search->get('week');
		$cccCultivation = [];

		foreach($cCultivation as $eCultivation) {

			if($weekSearch !== NULL and $eCultivation['firstTaskWeek'] !== $weekSearch) {
				continue;
			}

			// PHP range la clé NULL sous la chaîne vide, où les cultures que
			// nulle intervention ne date se retrouvent donc groupées.
			$week = (string)$eCultivation['firstTaskWeek'];

			$cccCultivation[$week] ??= new \Collection()->setDepth(2);
			$cccCultivation[$week]->push([$eCultivation['plant']['id'], NULL], $eCultivation);

		}

		// Les semaines sont des chaînes ISO, dont l'ordre alphabétique est
		// l'ordre chronologique ; la chaîne vide des cultures non datées passe
		// donc en tête, là où elles se voient.
		ksort($cccCultivation);

		$values = [];

		foreach($cccCultivation as $week => $ccCultivation) {
			$value = self::formatSeedling($ccCultivation, $search);
			if($value) {
				$values[$week] = $value;
			}
		}

		return $values;

	}

	public static function getForSeedling(\structure\Structure $eStructure, int $season, \Search $search): array {

		$select = Cultivation::getSelection();
		$select['firstTaskWeek'] = self::delegateFirstTaskWeek($eStructure);

		$cCultivation = Cultivation::model()
			->select($select)
			->whereSeedling('IN', self::getSeedlingTypes($search), if: $search->get('seedling'))
			->whereStructure($eStructure)
			->whereSeason($season)
			->getCollection();

		// Le regroupement se fait après le filtre plutôt que dans la requête : une
		// espèce dont il ne reste aucune culture ne doit pas laisser de groupe vide,
		// orderByPlant lisant l'espèce sur les cultures du groupe.
		$weekSearch = $search->get('week');
		$ccCultivation = new \Collection()->setDepth(2);

		foreach($cCultivation as $eCultivation) {

			if($weekSearch !== NULL and $eCultivation['firstTaskWeek'] !== $weekSearch) {
				continue;
			}

			$ccCultivation->push([$eCultivation['plant']['id'], NULL], $eCultivation);

		}

		return self::formatSeedling($ccCultivation, $search);

	}

	protected static function formatSeedling(\Collection $ccCultivation, \Search $search): array {

		self::orderByPlant($ccCultivation);

		$eSupplier = $search->get('supplier');

		$values = [];

		foreach($ccCultivation as $cCultivation) {

			$seedsVariety = [];

			// On vérifie si c'est la saison du semis pour la culture
			$cCultivation->filter(function($eCultivation) {

				$eSeries = $eCultivation['series'];

					return (
						$eSeries['cycle'] === Series::ANNUAL or
						$eSeries['perennialSeason'] === 1
					);

			});

			if($cCultivation->empty()) {
				continue;
			}

			foreach($cCultivation as $eCultivation) {

				if($eCultivation['cSlice']->empty()) {

					if($eSupplier->empty()) {

						$eSlice = new Slice([
							'variety' => new \plant\Variety(),
							'partPercent' => 100,
							'partArea' => $eCultivation['series']['area'],
							'partLength' => $eCultivation['series']['length']
						]);

						self::calculateSeeds($eCultivation, $eSlice, $seedsVariety, FALSE);

					}

				} else {

					foreach($eCultivation['cSlice'] as $eSlice) {

						if(
							$eSupplier->empty() or
							(
								in_array($eCultivation['seedling'], [Cultivation::SOWING, Cultivation::YOUNG_PLANT]) and
								$eSlice['variety']['supplierSeed']->notEmpty() and $eSupplier['id'] === $eSlice['variety']['supplierSeed']['id']
							) or
							(
								$eCultivation['seedling'] === Cultivation::YOUNG_PLANT_BOUGHT and
								$eSlice['variety']['supplierPlant']->notEmpty() and $eSupplier['id'] === $eSlice['variety']['supplierPlant']['id']
							)
						) {
							self::calculateSeeds($eCultivation, $eSlice, $seedsVariety, $eSupplier->notEmpty());
						}

					}

				}

			}

			if($seedsVariety) {

				$values[] = [
					'plant' => $eCultivation['plant'],
					'seeds' => $seedsVariety
				];

			}

		}

		return $values;

	}

	// Le bon de commande se lit fournisseur par fournisseur, quand l'écran se lit
	// espèce par espèce : les lignes sont reventilées, et celles qui n'ont rien à
	// commander sortent du document. Le regroupement par semaine demandé à l'écran
	// s'impose au document, où il remplace celui par fournisseur.
	public static function getForSeedlingBySupplier(\structure\Structure $eStructure, int $season, \Search $search): array {

		// L'ordre est celui du filtre « Affichage » et des colonnes de l'écran, pour
		// qu'une même nomenclature se lise partout dans le même sens.
		$sections = [
			'seeds' => [],
			'youngPlantsProduced' => [],
			'youngPlantsBought' => []
		];

		if($search->get('groupWeek')) {

			foreach(self::getForSeedlingByWeek($eStructure, $season, $search) as $week => $items) {
				self::fillSeedlingSections($sections, $items, $week === '' ? NULL : $week);
			}

		} else {

			self::fillSeedlingSections($sections, self::getForSeedling($eStructure, $season, $search), NULL);

			foreach($sections as &$groups) {

				// Le fournisseur non renseigné ferme la liste : c'est ce qui reste à
				// attribuer, pas une commande à passer.
				uasort($groups, function(array $groupA, array $groupB) {

					if($groupA['supplier']->empty() or $groupB['supplier']->empty()) {
						return ($groupA['supplier']->empty() <=> $groupB['supplier']->empty());
					}

					return strcasecmp($groupA['supplier']['name'], $groupB['supplier']['name']);

				});

			}

			unset($groups);

		}

		foreach(array_diff(array_keys($sections), self::getSeedlingSections($search)) as $section) {
			$sections[$section] = [];
		}

		return $sections;

	}

	protected static function fillSeedlingSections(array &$sections, array $items, ?string $week): void {

		foreach($items as ['plant' => $ePlant, 'seeds' => $seeds]) {

			foreach($seeds as $seedsVariety) {

				$eVariety = $seedsVariety['variety'];

				$eSupplierSeed = $eVariety->empty() ? new \work\Supplier() : $eVariety['supplierSeed'];
				$eSupplierPlant = $eVariety->empty() ? new \work\Supplier() : $eVariety['supplierPlant'];
				$stock = $eVariety->empty() ? NULL : $eVariety['stock'];

				self::pushSeedlingLine($sections['seeds'], $ePlant, $seedsVariety, $eSupplierSeed, $week, [
					'needed' => $seedsVariety['seeds'],
					'stock' => $stock,
					'quantity' => max($seedsVariety['seeds'] - (int)$stock, 0)
				]);

				self::pushSeedlingLine($sections['youngPlantsBought'], $ePlant, $seedsVariety, $eSupplierPlant, $week, [
					'quantity' => $seedsVariety['youngPlantsBought']
				]);

				// Un plant autoproduit ne s'achète pas : la feuille le range à part, sans
				// fournisseur, et les semences qu'il consomme figurent dans la section
				// précédente.
				self::pushSeedlingLine($sections['youngPlantsProduced'], $ePlant, $seedsVariety, new \work\Supplier(), $week, [
					'quantity' => $seedsVariety['youngPlantsProduced']
				]);

			}

		}

	}

	// $values porte les colonnes chiffrées de la section : une seule pour les plants,
	// trois pour les semences, dont la dernière est toujours ce qu'il reste à commander.
	protected static function pushSeedlingLine(array &$groups, \plant\Plant $ePlant, array $seedsVariety, \work\Supplier $eSupplier, ?string $week, array $values): void {

		// C'est la première colonne qui dit si la ligne a lieu d'être, pas la dernière :
		// un besoin de semences justifie la variété même quand le stock le couvre en
		// entier, et il ne reste alors rien à acheter.
		if(reset($values) === 0) {
			return;
		}

		// Regroupé par semaine, un même fournisseur se retrouve dans plusieurs groupes :
		// c'est alors la ligne qui le porte, et non plus l'en-tête.
		$key = $week ?? ($eSupplier->empty() ? 0 : $eSupplier['id']);

		$groups[$key] ??= ['supplier' => $eSupplier, 'week' => $week, 'lines' => []];
		$groups[$key]['lines'][] = [
			'plant' => $ePlant,
			'variety' => $seedsVariety['variety'],
			'supplier' => $eSupplier,
			'targeted' => $seedsVariety['targeted']
		] + $values;

	}

	protected static function calculateSeeds(Cultivation $eCultivation, Slice $eSlice, array &$seedsVariety, bool $removeIfEmpty) {

		$eCultivation->expects([
			'plant' => ['plantsSafetyMargin', 'seedsSafetyMargin']
		]);

		$eVariety = $eSlice['variety'];
		$varietyId = $eVariety->empty() ? '' : $eVariety['id'];

		$seeds = NULL;
		$youngPlantsProduced = NULL;
		$youngPlantsBought = NULL;
		$targeted = NULL;
		$error = NULL;

		switch($eCultivation['seedling']) {

			case Cultivation::SOWING :
				$seeds = $eCultivation->getSeeds($eSlice, $targeted, $eCultivation['plant']['seedsSafetyMargin'], error: $error);
				break;

			case Cultivation::YOUNG_PLANT :

				$seeds = $eCultivation->getSeeds($eSlice, $targeted, $eCultivation['plant']['plantsSafetyMargin']);
				$youngPlantsProduced = $eCultivation->getYoungPlants($eSlice, $targeted, $eCultivation['plant']['plantsSafetyMargin'], error: $error);
				break;

			case Cultivation::YOUNG_PLANT_BOUGHT :
				$youngPlantsBought = $eCultivation->getYoungPlants($eSlice, $targeted, error: $error);
				break;

			default :
				$error = 'seedling';

		}

		if($removeIfEmpty) {

			switch($eCultivation['seedling']) {

				case Cultivation::SOWING :
				case Cultivation::YOUNG_PLANT :
					if($seeds === 0) {
						return;
					}
					break;

				case Cultivation::YOUNG_PLANT_BOUGHT :
					if($youngPlantsBought === 0) {
						return;
					}
					break;

			}

		}

		if(empty($seedsVariety[$varietyId])) {

			$seedsVariety[$varietyId] = [
				'variety' => $eVariety,
				'error' => FALSE,
				'targeted' => FALSE,
				'seeds' => 0,
				'youngPlantsProduced' => 0,
				'youngPlantsBought' => 0,
				'cultivations' => []
			];

		}

		if($error) {
			$seedsVariety[$varietyId]['error'] = TRUE;
		}

		$seedsVariety[$varietyId]['cultivations'][] = [
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'slice' => $eSlice,
			'seeds' => $seeds,
			'error' => $error,
			'targeted' => $targeted,
			'youngPlantsProduced' => $youngPlantsProduced,
			'youngPlantsBought' => $youngPlantsBought
		];

		$seedsVariety[$varietyId]['seeds'] += $seeds;
		$seedsVariety[$varietyId]['youngPlantsProduced'] += $youngPlantsProduced;
		$seedsVariety[$varietyId]['youngPlantsBought'] += $youngPlantsBought;
		$seedsVariety[$varietyId]['targeted'] = ($targeted or $seedsVariety[$varietyId]['targeted']);

	}

	public static function fillSliceStats(Cultivation $eCultivation): void {

		if($eCultivation->empty()) {
			return;
		}

		$eCultivation->expects([
			'series',
			'cSlice',
			'plant' => ['seedsSafetyMargin', 'plantsSafetyMargin']
		]);

		if($eCultivation['cSlice']->empty()) {

			$eCultivation['cSlice']->append(new Slice([
				'variety' => new \plant\Variety(),
				'partPercent' => 100,
				'partArea' => $eCultivation['series']['area'],
				'partLength' => $eCultivation['series']['length']
			]));

		}

		$error = NULL;

		foreach($eCultivation['cSlice'] as $eSlice) {

			$eSlice['targeted'] = $eCultivation['series']->isTargeted();

			$eSlice['youngPlants'] = $eCultivation->getYoungPlants($eSlice, safetyMargin: match($eCultivation['seedling']) {
				Cultivation::YOUNG_PLANT => $eCultivation['plant']['plantsSafetyMargin'],
				default => NULL
			}, error: $error);

			$eSlice['seeds'] = $eCultivation->getSeeds($eSlice, safetyMargin: match($eCultivation['seedling']) {
				Cultivation::SOWING => $eCultivation['plant']['seedsSafetyMargin'],
				Cultivation::YOUNG_PLANT => $eCultivation['plant']['plantsSafetyMargin'],
				default => NULL
			});

			$eSlice['area'] = self::getArea($eCultivation['series'], $eCultivation, $eSlice);

		}

		$eCultivation['sliceError'] = $error;

	}

	public static function getArea(Series $eSeries, Cultivation $eCultivation, Slice $eSlice): ?float {

		$area = $eSeries['area'] ?? $eSeries['areaTarget'];

		if($area === NULL) {
			return NULL;
		}

		switch($eCultivation['varietiesUnit']) {

			case Cultivation::PLANT :
				if($eCultivation['density'] === NULL or $eCultivation['density'] === 0.0) {
					return NULL;
				} else {
					return round($eSlice['partPlant'] / $eCultivation['density'], 1);
				}

			case Cultivation::TRAY :
				if($eCultivation['density'] === NULL or $eCultivation['density'] === 0.0) {
					return NULL;
				} else {
					return round($eSlice['partTray'] * $eCultivation['varietiesTool']['routineValue']['value'] / $eCultivation['density'], 1);
				}

		}

		return match($eCultivation['varietiesUnit']) {
			Cultivation::PERCENT => round($eSlice['partPercent'] / 100 * $area, 1),
			Cultivation::LENGTH => ($eSeries['use'] === Series::BED) ? round($eSlice['partLength'] / ($eSeries['length'] ?? $eSeries['lengthTarget']) * $area, 1) : NULL,
			Cultivation::AREA => ($eSeries['use'] === Series::BLOCK) ? round($eSlice['partArea'], 1) : NULL,
		};

	}

	public static function getForHarvesting(\structure\Structure $eStructure, int $season): \Collection {

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		$ccCultivation = Cultivation::model()
			->select(Cultivation::getSelection())
			->select([
				'series' => [
					'cccPlace' => PlaceLib::delegateBySeries()
				],
				'cTask' => Task::model()
					->select([
						'action' => ['fqn'],
						'minDoneWeek' => new \Sql('MIN(doneWeek)'),
						'maxDoneWeek' => new \Sql('MAX(doneWeek)'),
						'count' => new \Sql('COUNT(*)', 'int')
					])
					->whereAction($eAction)
					->whereStatus(Task::DONE)
					->group(['cultivation', 'action'])
					->delegateCollection('cultivation'),
				'firstTaskWeek' => self::delegateFirstTaskWeek($eStructure),
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->sort(['startWeek' => SORT_ASC, 'mainUnit' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, ['plant', NULL]);

		self::orderByPlant($ccCultivation);

		// La recherche de l'écran cherche aussi dans le matériel : sans cet appel, la
		// famille existerait dans le champ et ne proposerait rien.
		self::addSearchTools($eStructure, self::getTasksForSearch($eStructure, $ccCultivation), $ccCultivation);
		self::addSearchIdle($eStructure, $ccCultivation);

		foreach($ccCultivation as $cCultivation) {

			foreach($cCultivation as $eCultivation) {

				$harvesting = [
					'nHarvest' => 0,
					'firstHarvest' => NULL,
					'lastHarvest' => NULL
				];

				foreach($eCultivation['cTask'] as $eTask) {

					$harvesting['nHarvest'] = round((strtotime($eTask['maxDoneWeek']) - strtotime($eTask['minDoneWeek'])) / 86400 / 7) + 1;
					$harvesting['firstHarvest'] = strtotime($eTask['minDoneWeek']);
					$harvesting['lastHarvest'] = strtotime($eTask['maxDoneWeek']);

				}

				$eCultivation['harvesting'] = $harvesting;

			}

		}

		return $ccCultivation;

	}

	/**
	 * Une sélection par requête : un délégué est un modèle, que la requête qui le lit consomme.
	 *
	 * `structure`, `density`, `bunchWeight` et `unitWeight` servent « Récolter » sur les séries cochées, qui crée
	 * l'intervention d'une série depuis sa culture (`\planning\HarvestWeekLib::getTasksForBatch()`) : le droit
	 * d'écrire se juge sur la structure, le partage au nombre de plants lit la densité, et la quantité en bottes
	 * ou en pièces se convertit par les poids de la culture, comme sur l'intervention (`Task::getSelection()`).
	 */
	public static function getHarvestWeekSelection(): array {

		return [
			'id', 'structure', 'season', 'startWeek', 'startAction', 'harvestWeeksExpected', 'mainUnit', 'density', 'bunchWeight', 'unitWeight',
			'series' => [
				'name', 'season', 'status', 'use', 'cycle', 'perennialSeason',
				'cccPlace' => PlaceLib::delegateBySeries()
			],
			'plant' => ['name', 'fqn', 'vignette', 'crateWeight'],
			'cSlice' => SliceLib::delegateByCultivation()
		];

	}

	/**
	 * Les cultures de l'écran des récoltes de la semaine. `$cPlant` borne la liste aux cultures de ces espèces,
	 * vide pour toutes : la saisie d'une récolte ne relit que les espèces qu'elle peut changer
	 * (`\planning\HarvestWeekLib::getPlantsAround()`), selon la même règle d'entrée que l'écran entier.
	 * `$properties` remplace la sélection de l'écran quand l'appelant ne lit que le rang des lignes ; sans
	 * délégué, elle sert aux deux requêtes, quand celle de l'écran se reconstruit pour chacune
	 * (`getHarvestWeekSelection()`).
	 *
	 * Les parts posées ici sont les candidates de l'écran : les couples variété · calibre qui portent dans la
	 * semaine une quantité, ou du temps sur une intervention de récolte de la culture (`getHarvestTimed()`).
	 * Le temps qui compte est celui que l'écran somme, connu après lui : c'est
	 * `\planning\HarvestWeekLib::keepParts()` qui garde celles qui ont une ligne.
	 *
	 * `$previous` lit aussi le récolté de la semaine d'avant, que seule la vue Semaine montre : la culture le
	 * porte en `harvestedWeekPrevious`, chaque part en `weekPrevious`, et une part récoltée la semaine d'avant
	 * est candidate, pour que ce récolté garde une ligne où se lire (M-Planning, 21/10).
	 */
	public static function getForHarvestWeek(\structure\Structure $eStructure, string $week, \Collection $cPlant = new \Collection(), ?array $properties = NULL, bool $previous = FALSE): \Collection {

		[$harvested, $parts] = self::getHarvestedByCultivation(self::applyHarvestedInWeek(Harvest::model(), $eStructure, $week, $cPlant)->getCollection());

		$cCultivation = self::applyExpectedInWeek(Cultivation::model(), $eStructure, $week, $cPlant)
			->select($properties ?? self::getHarvestWeekSelection())
			->getCollection(index: 'id');

		$cultivationsOther = array_diff(
			array_unique([
				...array_keys($harvested),
				...self::getStartedOrJustEnded($eStructure, $week, $cPlant),
				...self::applyHarvestedInWeekPrevious(Harvest::model(), $eStructure, $week, $cPlant)->getColumn('cultivation')->getIds()
			]),
			$cCultivation->getIds()
		);

		if($cultivationsOther !== []) {

			$cCultivationOther = self::applyByIds(Cultivation::model(), $eStructure, $cultivationsOther)
				->select($properties ?? self::getHarvestWeekSelection())
				->getCollection(index: 'id');

			$cCultivation->mergeCollection($cCultivationOther, reindex: FALSE);

		}

		$timed = self::getHarvestTimed($eStructure, $week, $cCultivation->getIds());

		[$harvestedPrevious, $partsPrevious] = $previous ?
			HarvestLib::getTotalInWeek($eStructure, $cCultivation, toWeek(week_date_starts($week).' - 7 days')) :
			[[], []];

		foreach($cCultivation as $eCultivation) {

			$eCultivation['harvestedDays'] = $harvested[$eCultivation['id']] ?? [];
			$eCultivation['harvestedWeekPrevious'] = $harvestedPrevious[$eCultivation['id']] ?? [];

			$cultivationParts = [];

			foreach(($timed[$eCultivation['id']] ?? []) + ($partsPrevious[$eCultivation['id']] ?? []) as $key => $part) {
				$parts[$eCultivation['id']][$key] ??= [
					'key' => $key,
					'eVariety' => $part['eVariety'],
					'eSize' => $part['eSize'],
					'days' => []
				];
			}

			foreach($parts[$eCultivation['id']] ?? [] as $key => $part) {

				$part['days'] = self::filterHarvestedDays($part['days']);
				$part['weekPrevious'] = $partsPrevious[$eCultivation['id']][$key]['quantities'] ?? [];

				if($part['days'] !== [] or $part['weekPrevious'] !== [] or array_key_exists($key, $timed[$eCultivation['id']] ?? [])) {
					$cultivationParts[$key] = $part;
				}

			}

			$eCultivation['harvestedParts'] = self::filterVentilated($cultivationParts);

		}

		return $cCultivation->find(fn($eCultivation) => ($eCultivation['series']['status'] === Series::OPEN or $eCultivation['harvestedDays'] !== []));

	}

	// Les semaines `YYYY-Www` se comparent comme du texte.
	private static function getStartedOrJustEnded(\structure\Structure $eStructure, string $week, \Collection $cPlant): array {

		$weekPrevious = toWeek(week_date_starts($week).' - 7 days');

		$cCultivation = self::applyHarvestWindow(Cultivation::model(), $eStructure, $week, $cPlant)
			->select(['id', 'harvestWeeks', 'harvestWeeksExpected'])
			->getCollection();

		$cultivations = [];

		foreach($cCultivation as $eCultivation) {

			$expectedLast = max($eCultivation['harvestWeeksExpected']);
			$started = array_any($eCultivation['harvestWeeks'] ?? [], fn($harvestWeek) => $harvestWeek < $week);

			if(($started and $week <= $expectedLast) or $expectedLast === $weekPrevious) {
				$cultivations[] = $eCultivation['id'];
			}

		}

		return $cultivations;

	}

	public static function applyHarvestWindow(CultivationModel $mCultivation, \structure\Structure $eStructure, string $week, \Collection $cPlant = new \Collection()): CultivationModel {

		$season = week_year($week);

		$mSeriesOpen = new SeriesModel();
		$mSeriesOpen
			->select('id')
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->whereStatus(Series::OPEN);

		return $mCultivation
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->whereSeries('IN', $mSeriesOpen->getSql())
			->wherePlant('IN', $cPlant, if: $cPlant->notEmpty())
			->where('JSON_LENGTH('.$mCultivation->field('harvestWeeksExpected').') > 0');

	}

	// Une série close en sort par le filtre final de `getForHarvestWeek()`, sauf récolte dans la semaine.
	public static function applyHarvestedInWeekPrevious(HarvestModel $mHarvest, \structure\Structure $eStructure, string $week, \Collection $cPlant = new \Collection()): HarvestModel {

		return self::applyHarvestPlants($mHarvest, $eStructure, $cPlant)
			->select(['cultivation'])
			->whereStructure($eStructure)
			->whereWeek(toWeek(week_date_starts($week).' - 7 days'))
			->whereCultivation('!=', NULL)
			->group('cultivation');

	}

	/**
	 * Le récolté des cultures des espèces `$cPlant`, toutes quand la liste est vide. La récolte ne porte pas
	 * l'espèce : elle se lit par la culture, dans la même base.
	 */
	public static function applyHarvestPlants(HarvestModel $mHarvest, \structure\Structure $eStructure, \Collection $cPlant): HarvestModel {

		if($cPlant->empty()) {
			return $mHarvest;
		}

		$mCultivation = new CultivationModel()
			->select('id')
			->whereStructure($eStructure)
			->wherePlant('IN', $cPlant);

		// Colonne qualifiée : `applyHarvestedInWeek()` joint `Task`, qui porte aussi `cultivation`.
		return $mHarvest->where('m1.cultivation', 'IN', $mCultivation->getSql());

	}

	public static function getHarvestedInWeek(\structure\Structure $eStructure, string $week): array {

		[$harvested] = self::getHarvestedByCultivation(self::applyHarvestedInWeek(Harvest::model(), $eStructure, $week)->getCollection());

		return array_keys($harvested);

	}

	private static function getHarvestedByCultivation(\Collection $cHarvest): array {

		$harvested = [];
		$parts = [];

		foreach($cHarvest as $eHarvest) {

			$cultivation = $eHarvest['cultivation']['id'];
			$date = $eHarvest['date'];
			$unit = $eHarvest['unit'];

			$harvested[$cultivation][$date][$unit] = ($harvested[$cultivation][$date][$unit] ?? 0.0) + $eHarvest['total'];

			$key = self::getHarvestPartKey($eHarvest['variety'], $eHarvest['harvestSize']);

			$parts[$cultivation][$key] ??= [
				'key' => $key,
				'eVariety' => $eHarvest['variety'],
				'eSize' => $eHarvest['harvestSize'],
				'days' => []
			];

			$parts[$cultivation][$key]['days'][$date][$unit] = $eHarvest['total'];

		}

		$harvested = array_map(self::filterHarvestedDays(...), $harvested);
		$harvested = array_filter($harvested, fn($days) => $days !== []);

		return [$harvested, $parts];

	}

	/**
	 * Les cultures que « Nouvelle intervention » et « Nouvelle récolte » peuvent proposer, sans la sélection
	 * des Récoltes : ce qui sert à les ranger et à les retenir (`HarvestWeekLib::rank()`). Une saison donnée
	 * remplace les trois saisons autour de la semaine, et un nom vide n'écarte rien. Les cultures écartées le sont
	 * avant que l'appelant ne coupe la liste, qui garde ainsi sa pleine mesure.
	 */
	public static function getForTaskQuery(\structure\Structure $eStructure, string $week, ?int $season, string $name, array $cultivationsExcluded = []): \Collection {

		return self::applyTaskQuery(Cultivation::model(), $eStructure, $week, $season, $name)
			->select([
				'id', 'season', 'harvestWeeks', 'harvestWeeksExpected',
				'series' => ['name'],
				'plant' => ['name']
			])
			->whereId('NOT IN', $cultivationsExcluded, if: $cultivationsExcluded !== [])
			->getCollection(index: 'id');

	}

	public static function applyTaskQuery(CultivationModel $mCultivation, \structure\Structure $eStructure, string $week, ?int $season, string $name): CultivationModel {

		$seasons = ($season === NULL) ? [week_year($week) - 1, week_year($week), week_year($week) + 1] : [$season];

		$mSeriesOpen = new SeriesModel();
		$mSeriesOpen
			->select('id')
			->whereStructure($eStructure)
			->whereSeason('IN', $seasons)
			->whereStatus(Series::OPEN);

		if($name !== '') {
			self::whereName($mCultivation, $eStructure, $name);
		}

		return $mCultivation
			->whereStructure($eStructure)
			->whereSeason('IN', $seasons)
			->whereSeries('IN', $mSeriesOpen->getSql());

	}

	public static function getForHarvestSuggestion(\structure\Structure $eStructure, string $week, array $cultivationsExcluded): \Collection {

		$season = week_year($week);

		$mSeriesOpen = new SeriesModel();
		$mSeriesOpen
			->select('id')
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->whereStatus(Series::OPEN);

		return Cultivation::model()
			->select([
				'id', 'season', 'harvestWeeksExpected',
				'series' => ['name'],
				'plant' => ['name']
			])
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->whereSeries('IN', $mSeriesOpen->getSql())
			->whereId('NOT IN', $cultivationsExcluded, if: $cultivationsExcluded !== [])
			->where('JSON_LENGTH('.Cultivation::model()->field('harvestWeeksExpected').') > 0')
			->getCollection(index: 'id');

	}

	/**
	 * Les cultures d'une série dans la sélection de l'écran des récoltes, rangées par espèce comme `getBySeries()`.
	 */
	public static function getBySeriesForHarvestWeek(\structure\Structure $eStructure, Series $eSeries): \Collection {

		return Cultivation::model()
			->select(self::getHarvestWeekSelection())
			->whereStructure($eStructure)
			->whereSeries($eSeries)
			->getCollection(index: 'id')
			->sort(function(Cultivation $e1, Cultivation $e2) {
				return \L::getCollator()->compare($e1['plant']['name'], $e2['plant']['name']);
			});

	}

	public static function getByIdsForHarvestWeek(\structure\Structure $eStructure, array $cultivations): \Collection {

		if($cultivations === []) {
			return new \Collection();
		}

		return self::applyByIds(Cultivation::model(), $eStructure, $cultivations)
			->select(self::getHarvestWeekSelection())
			->getCollection(index: 'id');

	}

	public static function applyExpectedInWeek(CultivationModel $mCultivation, \structure\Structure $eStructure, string $week, \Collection $cPlant = new \Collection()): CultivationModel {

		$season = week_year($week);

		return $mCultivation
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->wherePlant('IN', $cPlant, if: $cPlant->notEmpty())
			->where('JSON_CONTAINS('.$mCultivation->field('harvestWeeksExpected').', JSON_QUOTE('.$mCultivation->format($week).'))');

	}

	public static function applyHarvestedInWeek(HarvestModel $mHarvest, \structure\Structure $eStructure, string $week, \Collection $cPlant = new \Collection()): HarvestModel {

		return self::applyHarvestPlants($mHarvest, $eStructure, $cPlant)
			->select([
				'cultivation', 'date', 'unit',
				'total' => new \Sql('ROUND(SUM(m1.quantity), 3)', 'float')
			])
			->join(Task::model()
				->select([
					'variety' => ['name'],
					'harvestSize' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.week', $week)
			->where('m1.cultivation IS NOT NULL')
			->group([new \Sql('m1.cultivation'), new \Sql('m1.date'), new \Sql('m1.unit'), new \Sql('m2.variety'), new \Sql('m2.harvestSize')]);

	}

	/**
	 * Les parts d'une culture ventilée, aucune quand aucune ne nomme de variété ni de calibre : « Non spécifiée »
	 * seule est la culture entière.
	 */
	public static function filterVentilated(array $parts): array {

		$ventilated = array_filter($parts, fn($part) => $part['eVariety']->notEmpty() or $part['eSize']->notEmpty());

		return ($ventilated === []) ? [] : $parts;

	}

	/**
	 * Les couples variété · calibre qui portent du temps dans la semaine sur une intervention de récolte de ces
	 * cultures, d'une personne nommée. C'est plus large que ce que l'écran somme (`\planning\HarvestWeekLib::prepareTime()`),
	 * qui en décide ensuite. Une structure qui ne suit pas le temps n'en lit aucun.
	 */
	private static function getHarvestTimed(\structure\Structure $eStructure, string $week, array $cultivations): array {

		if($eStructure->hasTime() === FALSE or $cultivations === []) {
			return [];
		}

		$cTimesheet = self::applyHarvestTimedInWeek(Timesheet::model(), $eStructure, $week, $cultivations)->getCollection();

		$timed = [];

		foreach($cTimesheet as $eTimesheet) {

			$key = self::getHarvestPartKey($eTimesheet['variety'], $eTimesheet['harvestSize']);

			$timed[$eTimesheet['cultivation']['id']][$key] ??= [
				'eVariety' => $eTimesheet['variety'],
				'eSize' => $eTimesheet['harvestSize']
			];

		}

		return $timed;

	}

	/**
	 * La requête de `getHarvestTimed()`, une ligne par culture et par couple variété · calibre, posée sur le
	 * modèle reçu pour que `HarvestExplainAiTest` mesure celle du produit et non une copie.
	 */
	public static function applyHarvestTimedInWeek(TimesheetModel $mTimesheet, \structure\Structure $eStructure, string $week, array $cultivations): TimesheetModel {

		return $mTimesheet
			->select(['cultivation'])
			->join(Task::model()
				->select([
					'variety' => ['name'],
					'harvestSize' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.date', '>=', week_date_starts($week))
			->where('m1.date', '<=', week_date_ends($week))
			->where('m1.cultivation', 'IN', $cultivations)
			->where('m1.user IS NOT NULL')
			->where('m1.time > 0')
			->where('m2.action', \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE))
			->group([new \Sql('m1.cultivation'), new \Sql('m2.variety'), new \Sql('m2.harvestSize')]);

	}

	public static function getHarvestPartKey(\plant\Variety $eVariety, \plant\Size $eSize): string {

		return ($eVariety->empty() ? '0' : $eVariety['id']).'-'.($eSize->empty() ? '0' : $eSize['id']);

	}

	public static function filterHarvestedDays(array $days): array {

		foreach($days as $date => $units) {

			$units = array_map(fn($quantity) => round($quantity, 3), $units);
			$units = array_filter($units, fn($quantity) => $quantity !== 0.0);

			if($units === []) {
				unset($days[$date]);
			} else {
				$days[$date] = $units;
			}

		}

		return $days;

	}

	public static function applyByIds(CultivationModel $mCultivation, \structure\Structure $eStructure, array $cultivations): CultivationModel {

		return $mCultivation
			->whereStructure($eStructure)
			->whereId('IN', $cultivations);

	}

	/**
	 * Les cultures des séries qui portent l'un de ces noms.
	 */
	public static function applyBySeriesName(CultivationModel $mCultivation, \structure\Structure $eStructure, array $names): CultivationModel {

		$mSeries = new SeriesModel()
			->select('id')
			->whereStructure($eStructure)
			->whereName('IN', $names);

		return $mCultivation
			->whereStructure($eStructure)
			->whereSeries('IN', $mSeries->getSql());

	}

	/**
	 * Par début de mot, sur le nom de la série ou celui de l'espèce.
	 */
	private static function whereName(CultivationModel $mCultivation, \structure\Structure $eStructure, string $query): void {

		$like = fn(\ModuleModel $m, string $property) => '('.$m->field($property).' LIKE '.$m->format($query.'%').' OR '.$m->field($property).' LIKE '.$m->format('% '.$query.'%').')';

		$mSeriesNamed = new SeriesModel();
		$mSeriesNamed
			->select('id')
			->whereStructure($eStructure)
			->where($like($mSeriesNamed, 'name'));

		$mPlant = new \plant\PlantModel();
		$mPlant
			->select('id')
			->where($like($mPlant, 'name'));

		$mCultivation->where('series IN '.$mSeriesNamed->getSql().' OR plant IN '.$mPlant->getSql());

	}

	public static function getPaceByStructure(\structure\Structure $eStructure, int $season, \Collection $cAction): \Collection {

		$ccCultivation = \series\CultivationLib::getWorkingTimeByStructure($eStructure, $season, new \Search([
			'cAction' => $cAction
		]));

		$cPlant = new \Collection();

		foreach($ccCultivation as $cCultivation) {

			$ePlant = $cCultivation->first()['plant'];

			$cTask = new \Collection();
			$cTaskHarvested = new \Collection();

			$area = 0;

			foreach($cCultivation as $eCultivation) {

				$eSeries = $eCultivation['series'];

				$area += $eSeries['area'];

				foreach($eCultivation['cTask'] as $eTask) {

					if($eTask['totalTime'] === NULL) {
						continue;
					}

					$eAction = $eTask['action'];

					if($cTask->offsetExists($eAction['id']) === FALSE) {
						$cTask[$eAction['id']] = $eTask;
						$cTask[$eAction['id']]['area'] = 0;
						$cTask[$eAction['id']]['plants'] = 0;
					} else {
						$cTask[$eAction['id']]['totalTime'] += $eTask['totalTime'];
					}

					$cTask[$eAction['id']]['area'] += $eSeries['area'];
					$cTask[$eAction['id']]['plants'] += $eCultivation['density'] * $eSeries['area'];

				}

				foreach($eCultivation['cTaskHarvested'] as $eTaskHarvested) {

					$unit = $eTaskHarvested['harvestUnit'];

					if($unit === NULL) {
						continue;
					}

					if($cTaskHarvested->offsetExists($unit) === FALSE) {
						$cTaskHarvested[$unit] = new Task([
							'harvestUnit' => $unit,
							'totalHarvested' => 0,
							'totalTime' => 0,
						]);
					}

					$cTaskHarvested[$unit]['totalHarvested'] += $eTaskHarvested['totalHarvested'];
					$cTaskHarvested[$unit]['totalTime'] += $eTaskHarvested['totalTime'];

				}

			}

			if($area === 0) {
				continue;
			}

			$ePlant['area'] = $area;
			$ePlant['cTask'] = $cTask;
			$ePlant['cTaskHarvested'] = $cTaskHarvested;

			$cPlant[$ePlant['id']] = $ePlant;

		}

		return $cPlant;

	}

	public static function getWorkingTimeByStructure(\structure\Structure $eStructure, int $season, \Search $search = new \Search()): \Collection {

		$ccCultivation = Cultivation::model()
			->select(Cultivation::getSelection())
			->select([
				'series' => [
					'cccPlace' => PlaceLib::delegateBySeries()
				],
				'firstTaskWeek' => self::delegateFirstTaskWeek($eStructure),
			])
			->whereStructure($eStructure)
			->whereSeason($season)
			->sort(['startWeek' => SORT_ASC, 'mainUnit' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, ['plant', NULL]);

		self::orderByPlant($ccCultivation);

		// Même raison que sur les récoltes : la famille Matériel doit avoir de quoi
		// proposer, sinon elle s'affiche vide.
		self::addSearchTools($eStructure, self::getTasksForSearch($eStructure, $ccCultivation), $ccCultivation);
		self::addSearchIdle($eStructure, $ccCultivation);

		$cSeries = $ccCultivation->getColumnCollection('series');

		if($search->has('cAction')) {
			Task::model()->whereAction('IN', $search->get('cAction'));
		}

		$cccTask = Task::model()
			->select([
				'series',
				'cultivation',
				'action' => \work\Action::getSelection(),
				'totalTime' => new \Sql('SUM(time)', 'float'),
				'firstWeek' => new \Sql('MIN(COALESCE(doneWeek, plannedWeek))'),
				'lastWeek' => new \Sql('MAX(COALESCE(doneWeek, plannedWeek))')
			])
			->group(['series', 'cultivation', 'action'])
			->whereSeries('IN', $cSeries)
			->sort(new \Sql('totalTime DESC'))
			->getCollection(index: ['series', 'cultivation', NULL]);

		$cccTaskHarvested = TaskLib::getHarvestedBySeriesCollection($cSeries, $eStructure);

		foreach($ccCultivation as $cCultivation) {

			foreach($cCultivation as $eCultivation) {

				$eSeries = $eCultivation['series'];
				$ccTask = $cccTask[$eSeries['id']] ?? new \Collection();
				$cTaskHarvested = $cccTaskHarvested[$eSeries['id']][$eCultivation['id']] ?? new \Collection();

				$cTaskPlant = $ccTask[$eCultivation['id']] ?? new \Collection();
				$cTaskSoil = $ccTask[NULL] ?? new \Collection();

				$eCultivation['totalTimePlant'] = $cTaskPlant->sum('totalTime');
				$eCultivation['totalTimeSoil'] = round($cTaskSoil->sum('totalTime') / $eSeries['plants'], 2);

				$cTask = new \Collection();

				foreach($cTaskPlant as $eTask) {
					$cTask[] = new Task([
						'action' => $eTask['action'],
						'totalTime' => $eTask['totalTime'],
						'firstWeek' => $eTask['firstWeek'],
						'lastWeek' => $eTask['lastWeek']
					]);
				}

				foreach($cTaskSoil as $eTask) {
					$cTask[] = new Task([
						'action' => $eTask['action'],
						'totalTime' => round($eTask['totalTime'] / $eSeries['plants'], 2),
						'firstWeek' => $eTask['firstWeek'],
						'lastWeek' => $eTask['lastWeek']
					]);
				}

				$cTask->sort(['totalTime' => SORT_DESC]);

				$eCultivation['cTask'] = $cTask;
				$eCultivation['cTaskHarvested'] = $cTaskHarvested;

			}

		}

		return $ccCultivation;

	}

	// Décaler une série, c'est décaler tout ce qu'elle prévoit : les interventions
	// à faire, et la campagne de récolte attendue, du même nombre de semaines.
	// Les cultures dont la campagne attendue commence à une semaine ou après :
	// c'est « ce qui suit » côté récolte, quand on décale une intervention.
	public static function getFollowingHarvest(\Collection $cSeries, string $fromWeek): \Collection {

		return self::getWithHarvestExpected($cSeries)->find(function($eCultivation) use($fromWeek) {
			$weeks = $eCultivation['harvestWeeksExpected'];
			sort($weeks);
			return $weeks[0] >= $fromWeek;
		});

	}

	protected static function getWithHarvestExpected(\Collection $cSeries): \Collection {

		// update() recalcule la série derrière : il lui faut structure et series.
		return Cultivation::model()
			->select(['id', 'structure', 'harvestWeeks', 'harvestWeeksExpected', 'series' => Series::getSelection(), 'plant' => ['name']])
			->whereSeries('IN', $cSeries)
			->whereHarvestWeeksExpected('!=', NULL)
			->getCollection()
			->find(fn($eCultivation) => $eCultivation['harvestWeeksExpected'] !== []);

	}

	public static function incrementHarvestPeriodExpectedBySeriesCollection(\Collection $cSeries, int $increment, ?string $fromWeek = NULL): void {

		// Même borne que les interventions (TaskLib::incrementPlannedCollection),
		// qui a déjà posé le refus : ici on s'abstient seulement.
		if($increment === 0 or abs($increment) > 26) {
			return;
		}

		$cCultivation = ($fromWeek === NULL) ? self::getWithHarvestExpected($cSeries) : self::getFollowingHarvest($cSeries, $fromWeek);

		foreach($cCultivation as $eCultivation) {

			$weeks = $eCultivation['harvestWeeksExpected'];

			sort($weeks);

			$firstTs = strtotime($increment.' week', strtotime(week_date_starts($weeks[0])));
			$lastTs = strtotime($increment.' week', strtotime(week_date_starts(end($weeks))));

			self::updateHarvestPeriodExpected($eCultivation, $firstTs, $lastTs);

		}

	}

	public static function updateHarvestPeriodExpected(Cultivation $e, int $firstTs, int $lastTs): void {

		if($lastTs < $firstTs) {
			[$firstTs, $lastTs] = [$lastTs, $firstTs];
		}

		// Deux ans de campagne, c'est déjà une erreur de saisie : la base porte des
		// périodes de 1 293 semaines qu'aucun écran n'a jamais voulues.
		if(
			$firstTs < strtotime('2000-01-01') or
			$lastTs < $firstTs or
			$lastTs - $firstTs > 104 * 7 * 86400
		) {
			\Fail::log('Cultivation::harvestPeriod.check');
			return;
		}

		// Les bornes arrivent en secondes et un décalage de N × 604 800 s traverse
		// les changements d'heure : fin octobre, un lundi minuit devient un dimanche
		// 23 h, qui appartient à la semaine d'avant. On recale chaque borne sur son
		// lundi et on avance de semaine en semaine par le calendrier, pas par les
		// secondes — sinon la campagne perd une semaine à chaque passage.
		$firstTs = strtotime('monday this week', $firstTs + 12 * 3600);
		$lastTs = strtotime('monday this week', $lastTs + 12 * 3600);

		$weeks = [];

		for($ts = $firstTs; $ts <= $lastTs; $ts = strtotime('+1 week', $ts)) {
			$weeks[] = date('o-\WW', $ts);
		}

		$e['harvestWeeksExpected'] = array_values(array_unique($weeks));
		$e['harvestMonthsExpected'] = \util\DateLib::convertWeeksToMonths($e['harvestWeeksExpected']);

		self::update($e, ['harvestWeeksExpected', 'harvestMonthsExpected']);

	}

	public static function orderByPlant(\Collection $cxCultivationInput, \Collection $cPlantPriority = new \Collection()): void {

		$priorities = [];

		if($cPlantPriority->notEmpty()) {
			$position = 0;
			foreach($cPlantPriority as $ePlantPriority) {
				$priorities[$ePlantPriority['id']] = $position++;
			}
		}

		$cPlant = $cxCultivationInput->getColumn('plant', 'plant');

		$cxCultivationInput->uksort(function($a, $b) use($cPlant, $priorities) {

			if($priorities !== []) {

				$positionA = $priorities[$cPlant[$a]['id']] ?? NULL;
				$positionB = $priorities[$cPlant[$b]['id']] ?? NULL;

				if($positionA === NULL and $positionB === NULL) {

				} else if($positionA === NULL) {
					return 1;
				} else if($positionB === NULL) {
					return -1;
				} else {
					return ($positionA < $positionB) ? -1 : 1;
				}

			}

			return \L::getCollator()->compare($cPlant[$a]['name'], $cPlant[$b]['name']);

		});

	}

	public static function delegateFirstTaskWeek(\structure\Structure $eStructure): TaskModel {

		return Task::model()
			->whereAction('IN', \work\ActionLib::getByStructure($eStructure, fqn: [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION]))
			->group('cultivation')
			->delegateProperty('cultivation', new \Sql('MIN(IF(status="'.\series\Task::TODO.'", plannedWeek, doneWeek))'));

	}

	public static function delegateBySeries(?array $select = NULL): CultivationModel {

		return (clone Cultivation::model())
			->select($select ?? Cultivation::getSelection())
			->delegateCollection('series', 'id');

	}

	public static function canDelete(Cultivation $eCultivation): bool {

		$eCultivation->expects('series');

		return Cultivation::model()
			->whereSeries($eCultivation['series'])
			->count() > 1;

	}

	public static function create(Cultivation $e): void {

		$e->expects([
			'structure',
			'series' => ['season', 'area', 'length'],
			'cSlice'
		]);

		Cultivation::model()->beginTransaction();

		$e['season'] = $e['series']['season'];
		$e['area'] = $e['series']['area'];
		$e['length'] = $e['series']['length'];

		\sequence\SliceLib::createVariety($e['cSlice']);

		\sequence\CropLib::calculateDistance($e, $e['series']);

		try {

			parent::create($e);

			if($e->offsetExists('actions')) {
				self::createTasks($e);
			}

			$fw = new \FailWatch();

			// Ajout de la répartition des variétés
			SliceLib::createCollection($e['cSlice']);

			if($fw->ok()) {

				Series::model()->update($e['series'], [
					'plants' => new \Sql('plants + 1')
				]);

				SeriesLib::recalculate($e['structure'], $e['series']);

				Cultivation::model()->commit();

			} else {
				Cultivation::model()->rollBack();
			}

		} catch(\DuplicateException) {

			Cultivation::fail('plant.duplicate');

			Cultivation::model()->rollBack();

		}

	}

	public static function createTasks(Cultivation $e, ?array $actions = NULL): void {

		$e->expects(['actions']);

		$actions ??= $e['actions'];

		if($actions === []) {
			return;
		}

		$cAction = \work\ActionLib::getByStructure($e['structure'], fqn: array_keys($actions), index: 'fqn');
		$eCategory = \work\CategoryLib::getByStructure($e['structure'], fqn: CATEGORIE_CULTURE);

		foreach($actions as $action => $week) {

			$eAction = $cAction[$action];

			$tools = [];

			if(
				$action === ACTION_SEMIS_PEPINIERE and
				$e['varietiesTool']->notEmpty()
			) {
				$tools[] = $e['varietiesTool']['id'];
			}

			$eTask = new \series\Task([
				'structure' => $e['structure'],
				'action' => $eAction,
				'category' => $eCategory,
				'series' => $e['series'],
				'cultivation' => $e,
				'plant' => $e['plant'],
				'plannedWeek' => $week,
				'status' => Task::TODO,
				'repeatMaster' => new Repeat(),
				'tools' => $tools
			]);

			TaskLib::create($eTask);

		}

	}

	// En modification, une semaine d'implantation déplace l'intervention qui la
	// porte : la recréer en poserait une deuxième. Le panneau n'ouvre la saisie
	// que s'il n'y en a qu'une par action (TaskLib::acceptImplantationUpdate), le
	// déplacement vise donc toujours la bonne.
	public static function writeTasks(Cultivation $e): void {

		$e->expects(['actions', 'series', 'structure']);

		if($e['actions'] === []) {
			return;
		}

		$cAction = \work\ActionLib::getByStructure($e['structure'], fqn: array_keys($e['actions']), index: 'fqn');
		$cTask = TaskLib::getImplantationBySeries($e['series'], $cAction);

		$cTaskByAction = [];

		foreach($cTask as $eTask) {
			$cTaskByAction[$eTask['action']['fqn']] ??= $eTask;
		}

		$create = [];

		foreach($e['actions'] as $action => $week) {

			// Le formulaire n'ouvre la saisie que sur une action sans ambiguïté ; un
			// POST forgé ne doit pas déplacer une intervention faite pour autant.
			if(TaskLib::acceptImplantationUpdate($cTask, $action) === FALSE) {
				continue;
			}

			$eTask = $cTaskByAction[$action] ?? NULL;

			if($eTask === NULL) {
				$create[$action] = $week;
			} else if($eTask['plannedWeek'] !== $week) {
				TaskLib::updatePlannedCollection(new \Collection([$eTask]), $week);
			}

		}

		self::createTasks($e, $create);

	}

	public static function buildFromSequence(\sequence\Sequence $eSequence, \structure\Structure $eStructure, int $season): \Collection {

		$eSequence->expects([
			'id', 'cycle', 'cCrop'
		]);

		// Série minimale
		$eSeries = new Series([
			'structure' => $eStructure,
			'season' => $season,
			'cycle' => $eSequence['cycle'],
			'use' => $eSequence['use'],
			'length' => NULL,
			'lengthTarget' => NULL,
			'area' => NULL,
			'areaTarget' => NULL,
			'bedWidth' => $eSequence['bedWidth'] ?? $eStructure->conf(\map\Configuration::class)['defaultBedWidth'],
			'alleyWidth' => $eSequence['alleyWidth'] ?? $eStructure->conf(\map\Configuration::class)['defaultAlleyWidth'],
		]);

		// Construction des cultivations
		$cCultivation = new \Collection();

		foreach($eSequence['cCrop'] as $eCrop) {

			$eCultivation = new Cultivation([
				'structure' => $eStructure,
				'sequence' => $eSequence,
				'series' => $eSeries,
				'season' => $season,
				'crop' => $eCrop,
				'plant' => $eCrop['plant'],
				'varietiesUnit' => Cultivation::PERCENT,
				'varietiesTool' => new \work\Tool(),
				'distance' => $eCrop['distance'],
				'density' => $eCrop['density'],
				'rows' => $eCrop['rows'],
				'rowSpacing' => $eCrop['rowSpacing'],
				'plantSpacing' => $eCrop['plantSpacing'],
				'seedling' => $eCrop['seedling'],
				'seedlingSeeds' => $eCrop['seedlingSeeds'],
				'yieldExpected' => $eCrop['yieldExpected'],
				'mainUnit' => $eCrop['mainUnit'],
				'cSlice' => $eCrop['cSlice']
			]);

			$cCultivation[$eCrop['id']] = $eCultivation;

		}

		return $cCultivation;

	}

	public static function buildHarvestsFromSequence(\Collection $cCultivation, \Collection $cFlow, int $referenceYear): \Collection {

		$harvests = \sequence\CropLib::getHarvestsFromFlow($cFlow, $referenceYear);

		foreach($cCultivation as $eCultivation) {

			$harvestWeeksExpected = $harvests[$eCultivation['plant']['id']] ?? [];

			if($harvestWeeksExpected) {
				$eCultivation['harvestPeriodExpected'] = Cultivation::WEEK;
				$eCultivation['harvestMonthsExpected'] = \util\DateLib::convertWeeksToMonths($harvestWeeksExpected);
				$eCultivation['harvestWeeksExpected'] = $harvestWeeksExpected;
			} else {
				$eCultivation['harvestPeriodExpected'] = Cultivation::MONTH;
				$eCultivation['harvestMonthsExpected'] = NULL;
				$eCultivation['harvestWeeksExpected'] = NULL;
			}

		}

		return $cCultivation;

	}

	public static function update(Cultivation $e, array $properties): void {

		Cultivation::model()->beginTransaction();

		$key = array_search('varieties', $properties);

		if($key !== FALSE) {

			\sequence\SliceLib::createVariety($e['cSlice']);

			SliceLib::deleteByCultivation($e);
			SliceLib::createCollection($e['cSlice']);

			unset($properties[$key]);

		}

		$harvestedNormalizedUpdate = array_intersect(['mainUnit', 'bunchWeight', 'unitWeight'], $properties);

		if($harvestedNormalizedUpdate) {
			$e->calculateHarvestedNormalized();
			$properties[] = 'harvestedNormalized';
		}

		$distanceUpdate = array_intersect(['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing'], $properties);

		if(count($distanceUpdate) === 5) {
			\sequence\CropLib::calculateDistance($e, $e['series']);
		} else if(count($distanceUpdate) > 0) {
			throw new \Exception('Properties must be updated together');
		}

		if(array_delete($properties, 'actions')) {
			self::writeTasks($e);
		}

		parent::update($e, $properties);

		if(in_array('harvestWeeksExpected', $properties)) {
			SeriesLib::recalculate($e['structure'], $e['series']);
		}

		// Mise à jour de l'espèce -> mettre à jour également les tâches
		if(in_array('plant', $properties)) {

			Task::model()
				->whereCultivation($e)
				->update([
					'plant' => $e['plant']
				]);

			Timesheet::model()
				->whereSeries($e['series'])
				->whereCultivation($e)
				->update([
					'plant' => $e['plant']
				]);

		}

		Cultivation::model()->commit();

	}

	public static function updateDensityBySeries(Series $eSeries): void {

		$eSeries->expects(['bedWidth', 'alleyWidth']);

		Cultivation::model()
			->whereSeries($eSeries)
			->whereDistance(Cultivation::SPACING)
			->update([
				'density' => match($eSeries['use']) {
					Series::BLOCK => new \Sql('IF(rowSpacing IS NOT NULL AND plantSpacing IS NOT NULL, 100 / rowSpacing * 100 / plantSpacing, NULL)'),
					Series::BED => new \Sql('IF('.Cultivation::model()->field('rows').' IS NOT NULL AND plantSpacing IS NOT NULL, '.Cultivation::model()->field('rows').' / ('.($eSeries['bedWidth'] + ($eSeries['alleyWidth'] ?? 0)).' / 100) * 100 / plantSpacing, NULL)')
				}
			]);

	}

	public static function delete(Cultivation $e): void {

		$e->expects(['series']);

		Cultivation::model()->beginTransaction();

		if(self::canDelete($e) === FALSE) {
			Cultivation::fail('canNotDelete');
			return;
		}

		$cTask = Task::model()
			->select(Task::getSelection())
			->whereCultivation($e)
			->getCollection();

		TaskLib::deleteCollection($cTask);

		Slice::model()
			->whereCultivation($e)
			->delete();

		// Supprime les rapports, que tient le package analyze, retiré de l'export public
		if(\structure\StructureSetting::hasAnalyzePackage()) {
			self::notify('deleteCultivation', $e);
		}

		Cultivation::model()->delete($e);

		Series::model()->update($e['series'], [
			'plants' => new \Sql('plants - 1')
		]);

		// Mets à jour le compteur de productions
		$cCultivation = self::getBySeries($e['series']);

		$e['series']['plants'] = $cCultivation->count();

		Series::model()
			->select('plants')
			->update($e['series']);

		// on passe les actions générales sur la culture restante
		if($e['series']['plants'] === 1) {

			$eCultivationRemaining = $cCultivation->first();

			Task::model()
				->whereSeries($e['series'])
				->whereCultivation(NULL)
				->update([
					'cultivation' => $eCultivationRemaining,
					'plant' => $eCultivationRemaining['plant']
				]);

			Timesheet::model()
				->whereSeries($e['series'])
				->whereCultivation(NULL)
				->update([
					'cultivation' => $eCultivationRemaining,
					'plant' => $eCultivationRemaining['plant']
				]);

		}

		Cultivation::model()->commit();

	}

}
?>
