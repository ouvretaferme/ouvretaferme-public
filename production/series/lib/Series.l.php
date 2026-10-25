<?php
namespace series;

class SeriesLib extends SeriesCrud {

	use \Notifiable;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['sequence', 'name', 'cycle', 'use', 'mode', 'season', 'areaTarget', 'lengthTarget', 'bedWidth', 'alleyWidth'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {

		return function(Series $e) {

			$e->expects(['cycle', 'perennialStatus']);
			$properties = ['name', 'use', 'mode', 'sequence', 'areaTarget', 'lengthTarget', 'bedWidth', 'alleyWidth'];

			if($e->acceptSeason()) {
				$properties[] = 'season';
			}

			if($e['cycle'] === Series::PERENNIAL and $e['perennialStatus'] !== Series::CONTINUED) {
				$properties[] = 'perennialLifetime';
			}

			return $properties;

		};

	}

	public static function getByStructure(\structure\Structure $eStructure, ?int $season = NULL, bool $selectCultivation = FALSE, bool $selectPlaces = FALSE, \Search $search = new \Search()): \Collection {

		if($season !== NULL) {
			Series::model()->whereSeason($season);
		}

		$selection = Series::getSelection();

		if($selectCultivation) {
			$selection['cCultivation'] = CultivationLib::delegateBySeries(Cultivation::getSelection() + [
				'firstTaskWeek' => CultivationLib::delegateFirstTaskWeek($eStructure)
			]);
		}

		if($selectPlaces) {
			$selection['cccPlace'] = PlaceLib::delegateBySeries();
		}

		if($search->get('plant')) {

			if($season !== NULL) {
				Cultivation::model()->whereSeason($season);
			}

			$cSeriesFilter = Cultivation::model()
				->select('series')
				->whereStructure($eStructure)
				->wherePlant($search->get('plant'))
				->getColumn('series');

			Series::model()->whereId('IN', $cSeriesFilter);

		}

		if($search->get('status')) {
			Series::model()->whereStatus($search->get('status'));
		}

		return Series::model()
			->select($selection)
			->whereStructure($eStructure)
			->sort([
				'name' => SORT_ASC
			])
			->getCollection();

	}

	public static function countByStructure(\structure\Structure $eStructure, ?int $season = NULL): int {

		return Series::model()
			->whereStructure($eStructure)
			->whereSeason($season, if: $season !== NULL)
			->count();

	}

	/**
	 * Les séries qui portent une culture de l'une de ces espèces.
	 */
	public static function applyByPlant(SeriesModel $mSeries, \structure\Structure $eStructure, \Collection $cPlant): SeriesModel {

		$mCultivation = new CultivationModel()
			->select('series')
			->whereStructure($eStructure)
			->wherePlant('IN', $cPlant);

		return $mSeries
			->whereStructure($eStructure)
			->whereId('IN', $mCultivation->getSql());

	}

	public static function fillTimeline(Series $e): void {

		$e->expects(['structure']);

		$cAction = \work\ActionLib::getByStructure($e['structure'], fqn: [ACTION_SEMIS_DIRECT, ACTION_PLANTATION]);

		\series\Series::model()
			->select([
				'cCultivation' => \series\Cultivation::model()
					->select([
						'id',
						'season',
						'harvested',
						'plant' => ['family' => ['color'], 'fqn', 'vignette', 'name', 'color'],
						'harvestMonths', 'harvestMonthsExpected',
						'harvestWeeks', 'harvestWeeksExpected',
						'startWeek', 'startAction',
						'firstTaskWeek' => \series\CultivationLib::delegateFirstTaskWeek($e['structure']),
						'cTask' => \series\Task::model()
							->select([
								'cultivation',
								'action',
								'plannedWeek', 'doneWeek',
								'status'
							])
							->whereAction('IN', $cAction)
							->whereSeries($e)
							->delegateCollection('cultivation')
					])
					->sort(['startWeek' => SORT_ASC])
					->delegateCollection('series')
			])
			->get($e);

	}

	public static function getBySequence(\sequence\Sequence $eSequence): \Collection {

		return Series::model()
			->select(Series::getSelection())
			->select([
				'ccCultivation' => Cultivation::model()
					->select(Cultivation::getSelection())
					->whereCrop('!=', NULL)
					->sort(['crop' => SORT_ASC])
					->delegateCollection('series', index: ['plant', NULL]),
			])
			->whereStructure($eSequence['structure']) // Pour l'index
			->whereSequence($eSequence)
			->sort([
				'season' => SORT_DESC,
				'name' => SORT_ASC
			])
			->getCollection(NULL, NULL, ['season', NULL]);

	}

	public static function getByPerennialFirst(Series $eSeries): \Collection {

		if($eSeries->empty()) {
			return new \Collection();
		}

		return Series::model()
			->select([
				'id',
				'perennialSeason', 'perennialStatus'
			])
			->wherePerennialFirst($eSeries)
			->sort([
				'perennialSeason' => SORT_ASC
			])
			->getCollection(NULL, NULL, 'perennialSeason');

	}

	public static function getImportPerennial(\structure\Structure $eStructure, int $season): \Collection {

		return Series::model()
			->select(Series::getSelection())
			->select([
				'sequence' => ['name', 'mode', 'status'],
				'cCultivation' => (clone Cultivation::model())
					->select([
						'id',
						'plant' => ['name', 'fqn', 'vignette']
					])
					->delegateCollection('series'),
			])
			->whereStructure($eStructure)
			->whereSeason($season - 1)
			->whereCycle(Series::PERENNIAL)
			->wherePerennialStatus(Series::GROWING)
			->sort([
				'name' => SORT_ASC
			])
			->getCollection();

	}

	public static function getLastSeasons(\Collection $cStructure): array {

		if($cStructure->empty()) {
			return [];
		}

		$cSeries = Series::model()
			->select(['structure', 'season' => new \Sql('MAX(season)', 'int')])
			->whereStructure('IN', $cStructure)
			->group('structure')
			->getCollection(index: 'structure');

		$seasons = [];

		foreach($cSeries as $structure => $eSeries) {
			$seasons[$structure] = $eSeries['season'];
		}

		return $seasons;

	}

	public static function getSeasonsAround(\structure\Structure $eStructure, int $year): array {

		return Series::model()
			->whereStructure($eStructure)
			->where('season BETWEEN '.($year - 1).' AND '.($year + 1))
			->sort(new \Sql('_ DESC'))
			->getColumn(new \Sql('DISTINCT season', 'int'));

	}

	public static function getCreateElement(): Series {
		return new Series([
			'id' => NULL,
			'cycle' => Series::ANNUAL
		]);
	}

	public static function buildDefaultName(\Collection $cCultivation): string {

		return implode(' + ', $cCultivation->getColumnCollection('plant')
			->sort('name')
			->getColumn('name'));

	}

	/**
	 * La structure se passe en argument quand l'appelant l'a déjà et l'a déjà cadrée : l'API n'a pas de
	 * membre connecté à qui demander canManage(), elle travaille sous un utilisateur technique membre
	 * d'aucune structure, et la validation refuserait toute création.
	 */
	public static function prepareCreate(array $input, \structure\Structure $eStructure = new \structure\Structure()): array {

		if($eStructure->empty()) {
			$eStructure = \structure\StructureLib::getById($input['structure'] ?? NULL)->validate('canManage');
		}

		$eSeries = SeriesLib::getNewElement(['id' => NULL]);
		$eSeries['structure'] = $eStructure;

		$fw = new \FailWatch();

		$eSeries->build(self::getPropertiesCreate(), $input, new \PropertyList('create'));

		if($fw->ko()) {
			return [];
		}

		$cCultivation = new \Collection();

		$eCultivationReference = new Cultivation([
			'structure' => $eStructure,
			'series' => $eSeries,
			'season' => $eSeries['season'],
			'sequence' => $eSeries['sequence'],
			'distance' => Cultivation::SPACING,
			'seedling' => NULL,
			'harvestPeriodExpected' => Cultivation::MONTH
		]);

		$plants = var_filter($input['plant'] ?? [], 'array');
		$crops = var_filter($input['crop'] ?? [], 'array');

		if($plants === []) {
			Series::fail('plantsCheck');
			return [];
		}

		if(array_unique($plants) !== $plants) {
			Series::fail('plantsDuplicate');
			return [];
		}

		foreach($plants as $index => $plant) {

			$eCultivation = clone $eCultivationReference;
			$eCultivation['index'] = $index;

			if($eSeries['sequence']->notEmpty()) {

				$crop = $crops[$index] ?? NULL;

				if($crop === NULL) {
					Series::fail('cropsCheck');
					return [];
				}

				$cCultivation->offsetSet($crop, $eCultivation);

			} else {
				$cCultivation->append($eCultivation);
			}

		}

		// Construction de chaque culture
		foreach($cCultivation as $eCultivation) {

			$index = $eCultivation['index'];

			$fw = new \FailWatch();

			// Recherche du 'crop' attaché au 'cultivation'
			if($eSeries['sequence']->notEmpty()) {
				$eCultivation->buildIndex(['crop'], $input, $index);
			} else {
				$eCultivation['crop'] = new \sequence\Crop();
			}

			$eCultivation->buildIndex(['plant', 'varietiesUnit', 'varietiesTool', 'varieties'], $input, $index);

			$properties = ['distance', 'density', 'rows', 'plantSpacing', 'rowSpacing', 'stagger', 'seedling', 'seedlingSeeds', 'yieldExpected', 'mainUnit', 'actions'];

			if($eSeries['sequence']->empty()) {
				$properties = array_merge($properties, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);
			}

			$eCultivation->buildIndex($properties, $input, $index);

		}

		$eSeries['plants'] = $cCultivation->count();

		// Le cycle est connu, on vérifie la durée de vie de la culture si c'est une pérenne
		$eSeries->build(['perennialLifetime'], $input);

		if($fw->ko()) {
			return [];
		}

		return [$eSeries, $cCultivation];

	}

	public static function createWithCultivations(Series $e, \Collection $cCultivation, \Collection $cFlow = new \Collection(), ?int $referenceYear = NULL): void {

		$cCultivation->expects([
			'cSlice',
			'plant' => ['name']
		]);

		Series::model()->beginTransaction();

		// Créer une série
		$e->add([
			'name' => self::buildDefaultName($cCultivation),
			'cultivations' => $cCultivation->count()
		]);

		self::create($e);

		$fw = new \FailWatch();

		// Créer les nouvelles variétés
		foreach($cCultivation as $eCultivation) {

			\sequence\SliceLib::createVariety($eCultivation['cSlice']);

			$eCultivation['series'] = $e;

			\sequence\CropLib::calculateDistance($eCultivation, $e);

		}

		// Ajout des estimations de récolte
		if($cFlow->notEmpty()) {
			CultivationLib::buildHarvestsFromSequence($cCultivation, $cFlow, $referenceYear);
		}

		// Ajout des cultures sans parent une par une pour récupérer les IDs
		self::createCultivations($cCultivation);

		// Create tasks and add into the database
		if($cFlow->notEmpty()) {

			[$cTask, $cRepeat] = \series\TaskLib::buildFromFlow($cFlow, $e, $cCultivation, $e['season'], $referenceYear);

			self::createTasks($cTask);
			self::createRepeats($cRepeat);

		}

		self::recalculate($e['structure'], $e);

		CultivationLib::updateDensityBySeries($e);

		if($fw->ok()) {
			Series::model()->commit();
		} else {
			Series::model()->rollBack();
		}

	}

	private static function createTasks(\Collection $cTask): void {

		Task::model()->insert($cTask);

	}

	private static function createRepeats(\Collection $cRepeat): void {

		foreach($cRepeat as $eRepeat) {

			Repeat::model()->insert($eRepeat);

			RepeatLib::createForSeries($eRepeat);

		}

	}

	public static function createCultivations(\Collection $cCultivation): void {

		$cCultivation->expects(['cSlice', 'actions']);

		// Ajout des cultures sans parent une par une pour récupérer les IDs

		foreach($cCultivation as $eCultivation) {

			Cultivation::model()->insert($eCultivation); // Pour mettre l'ID

			CultivationLib::createTasks($eCultivation);

			$eCultivation['cSlice']->setColumn('id', NULL);
			$eCultivation['cSlice']->setColumn('cultivation', $eCultivation);
			$eCultivation['cSlice']->setColumn('series', $eCultivation['series']);

			// Ajout de la répartition des variétés
			SliceLib::createCollection($eCultivation['cSlice']);

		}

	}

	public static function create(Series $e): void {

		$e->expects([
			'structure',
			'name', 'cycle'
		]);

		if($e['cycle'] === Series::PERENNIAL) {

			$e->expects(['perennialLifetime']);

			$e['perennialSeason'] = 1;
			$e['perennialStatus'] = Series::GROWING;

		}

		if($e['use'] === Series::BED and $e['lengthTarget'] !== NULL) {
			$e['areaTarget'] = round($e['lengthTarget'] * ($e['bedWidth'] + $e['alleyWidth'] ?? 0) / 100);
		}

		parent::create($e);

		if($e['cycle'] === Series::PERENNIAL) {

			Series::model()->update($e, [
				'perennialFirst' => $e
			]);

		}

		if($e['structure']->conf(\structure\Feature::class)['hasCultivations'] === FALSE) {

			\structure\FeatureLib::updateFeature($e['structure'], 'hasCultivations', TRUE);

		}
	}

	/**
	 * Dupliquer plusieurs séries
	 */
	public static function duplicateCollection(\Collection $cSeries, int $season, \Collection $cAction, int $copies, array $input, bool $copyTimesheet, bool $copyPlaces): \Collection {

		$properties = ['name', 'structure', 'season', 'mode', 'use', 'plants', 'area', 'areaTarget', 'length', 'lengthTarget', 'bedWidth', 'alleyWidth', 'sequence', 'cycle'];

		$cSeriesNew = new \Collection();

		$fw = new \FailWatch();

		foreach($cSeries as $eSeries) {

			$eSeries->expects($properties);

			if($eSeries['cycle'] !== \series\Series::ANNUAL) {
				throw new \Exception('Can duplicate only annual series');
			}

			for($index = 0; $index < $copies; $index++) {

				// Créer une nouvelle série
				$eSeriesNew = new Series($eSeries->extracts($properties));
				$eSeriesNew['duplicateOf'] = $eSeries;
				$eSeriesNew['season'] = $season;
				$eSeriesNew['base'] = $eSeries;

				$newValues = [
					'name' => $input['name'][$eSeries['id']][$index] ?? NULL,
					'taskInterval' => cast($input['taskInterval'][$eSeries['id']][$index] ?? 0, 'int'),
				];

				$eSeriesNew->build(['name', 'taskInterval'], $newValues, new \PropertyList()->setWrapper(fn(string $property) => 'series-'.$eSeries['id'].'-'.$index));

				$cSeriesNew[] = $eSeriesNew;

			}

		};

		if($fw->ko()) {
			return new \Collection();
		}

		Series::model()->beginTransaction();

		foreach($cSeriesNew as $eSeriesNew) {

			\series\SeriesLib::duplicateOne(
				$eSeriesNew['base'],
				$eSeriesNew,
				$cAction,
				$copyTimesheet,
				$copyPlaces
			);

		}

		Series::model()->commit();

		return $cSeriesNew;

	}

	private static function shiftWeeks(array $weeks, int $seasonInterval, int $taskInterval): array {

		return array_map(
			fn(string $week) => toWeek(strtotime($week.' '.($seasonInterval < 0 ? '' : '+').$seasonInterval.' YEAR '.($taskInterval < 0 ? '' : '+').$taskInterval.' WEEK')),
			$weeks
		);

	}

	/**
	 * Dupliquer une série
	 */
	protected static function duplicateOne(Series $eSeriesBase, Series $eSeriesNew, \Collection $cAction, bool $copyTimesheet, bool $copyPlaces): Series {

		$eSeriesNew->expects(['taskInterval']);

		Series::model()->beginTransaction();

		// Créer une nouvelle série
		Series::model()->insert($eSeriesNew);

		// Dupliquer les cultures et les variétés
		$cCultivation = self::getDuplicateCultivations($eSeriesBase);

		$taskInterval = $eSeriesNew['taskInterval'];
		$seasonInterval = ($eSeriesNew['season'] - $eSeriesBase['season']);

		foreach($cCultivation as $eCultivation) {

			// Mise à jour de la série
			$eCultivation['series'] = $eSeriesNew;
			$eCultivation['season'] = $eSeriesNew['season'];

			switch($eCultivation['harvestPeriodExpected']) {

				case Cultivation::WEEK :

					if(
						($seasonInterval !== 0 or $taskInterval !== 0) and
						$eCultivation['harvestWeeksExpected']
					) {

						$eCultivation['harvestWeeksExpected'] = self::shiftWeeks($eCultivation['harvestWeeksExpected'], $seasonInterval, $taskInterval);
						$eCultivation['harvestMonthsExpected'] = \util\DateLib::convertWeeksToMonths($eCultivation['harvestWeeksExpected']);

					}

					break;

				case Cultivation::MONTH:

					if($eCultivation['harvestMonthsExpected']) {

						if($taskInterval !== 0) {

							// Un décalage en semaines ne tombe pas sur des mois entiers : la récolte attendue de la copie
							// se compte à la semaine, comme ses interventions, au lieu d'être arrondie au mois.
							$eCultivation['harvestPeriodExpected'] = Cultivation::WEEK;
							$eCultivation['harvestWeeksExpected'] = self::shiftWeeks(\util\DateLib::convertMonthsToWeeks($eCultivation['harvestMonthsExpected']), $seasonInterval, $taskInterval);
							$eCultivation['harvestMonthsExpected'] = \util\DateLib::convertWeeksToMonths($eCultivation['harvestWeeksExpected']);

						} else if($seasonInterval !== 0) {

							foreach($eCultivation['harvestMonthsExpected'] as $key => $value) {
								$eCultivation['harvestMonthsExpected'][$key] = date('Y-m', strtotime($value.'-15 '.($seasonInterval < 0 ? '' : '+').$seasonInterval.' YEAR'));
							}

							$eCultivation['harvestWeeksExpected'] = \util\DateLib::convertMonthsToWeeks($eCultivation['harvestMonthsExpected']);

						}

					}

					break;


			}

		}

		self::createCultivations($cCultivation);

		// Dupliquer les tâches
		if($cAction->notEmpty()) {
			self::duplicateTasks($eSeriesBase, $eSeriesNew, $cCultivation, $cAction, $copyTimesheet, $eSeriesNew['taskInterval']);
		}

		// Dupliquer les emplacements
		if($copyPlaces) {
			self::duplicatePlaces($eSeriesBase, $eSeriesNew);
		} else {

			$eSeriesNew['area'] = NULL;
			$eSeriesNew['areaPermanent'] = NULL;
			$eSeriesNew['length'] = NULL;
			$eSeriesNew['lengthPermanent'] = NULL;

			\series\PlaceLib::updateMetadata($eSeriesNew);

		}

		foreach($cCultivation as $eCultivation) {
			TaskLib::recalculateHarvest($eSeriesNew['structure'], $eCultivation, $eCultivation['plant']);
		}

		SeriesLib::recalculate($eSeriesNew['structure'], $eSeriesNew);

		Series::model()->commit();

		return $eSeriesNew;

	}

	private static function duplicatePerennialWithCultivations(Series $eSeries): void {

		$eSeries->expects(['id']);

		// Créer une série
		$eSeriesNew = self::getDuplicatePerennialSeries($eSeries);

		Series::model()->insert($eSeriesNew);

		// Créer les nouvelles cultures
		$cCultivation = self::getDuplicateCultivations($eSeries);

		// Obtenir les Crop équivalents
		$cCrop = new \Collection();

		foreach($cCultivation as $eCultivation) {

			if($eCultivation['crop']->empty() === FALSE) {
				$cCrop[] = $eCultivation['crop'];
			}

		}

		// Obtenir le flow
		if($cCrop->empty()) {
			$cFlow = new \Collection();
		} else {
			$cFlow = \sequence\FlowLib::getByCrops($cCrop, $eSeriesNew['perennialSeason']);
		}

		// Ajustements les cultures
		self::buildDuplicateCultivations($eSeriesNew, $cCultivation, $cFlow);

		self::createCultivations($cCultivation);

		// Créer les nouvelles tâches
		[$cTask, $cRepeat] = \series\TaskLib::buildFromFlow($cFlow, $eSeriesNew, $cCultivation, $eSeriesNew['season']);

		self::createTasks($cTask);
		self::createRepeats($cRepeat);

		// Réaffecter les emplacements
		self::duplicatePlaces($eSeries, $eSeriesNew);

		SeriesLib::recalculate($eSeriesNew['structure'], $eSeriesNew);

	}

	private static function getDuplicatePerennialSeries(Series $e): Series {

		$properties = ['name', 'structure', 'season', 'use', 'plants', 'area', 'length', 'bedWidth', 'alleyWidth', 'sequence', 'cycle', 'perennialLifetime', 'perennialFirst', 'perennialSeason'];

		$e->expects($properties);

		$eSeries = new Series($e->extracts($properties));
		$eSeries['season']++;
		$eSeries['perennialSeason']++;
		$eSeries['perennialStatus'] = ($eSeries['perennialSeason'] ===  $eSeries['perennialLifetime'] ? Series::FINISHED : Series::GROWING);

		return $eSeries;

	}

	private static function getDuplicateCultivations(Series $eSeries): \Collection {

		$cCultivation = Cultivation::model()
			->select([
				'id',
				'structure', 'season', 'sequence',
				'crop' => ['yieldExpected'],
				'cSlice' => SliceLib::delegateByCultivation(),
				'plant',
				'startWeek', 'startAction',
				'distance', 'rows', 'rowSpacing', 'plantSpacing', 'stagger', 'density', 'varietiesUnit', 'varietiesTool',
				'area', 'areaPermanent', 'length', 'lengthPermanent', 'seedling', 'seedlingSeeds',
				'mainUnit', 'yieldExpected', 'unitWeight', 'bunchWeight',
				'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected'
			])
			->whereSeries($eSeries)
			->getCollection(NULL, NULL, 'id');

		$cCultivation->setColumn('id', NULL);
		$cCultivation->setColumn('actions', []);

		return $cCultivation;

	}

	private static function buildDuplicateCultivations(Series $eSeriesNew, \Collection $cCultivation, \Collection $cFlow) {

		$harvests = \sequence\CropLib::getHarvestsFromFlow($cFlow, $eSeriesNew['season']);

		foreach($cCultivation as $eCultivation) {

			$eCultivation['series'] = $eSeriesNew;
			$eCultivation['season'] = $eSeriesNew['season'];

			if($eCultivation['crop']->empty() === FALSE) {

				$harvestWeeksExpected = $harvests[$eCultivation['plant']['id']] ?? [];

				if($harvestWeeksExpected) {
					$eCultivation['yieldExpected'] = $eCultivation['crop']['yieldExpected'];
					$eCultivation['harvestPeriodExpected'] = Cultivation::WEEK;
					$eCultivation['harvestMonthsExpected'] = \util\DateLib::convertWeeksToMonths($harvestWeeksExpected);
					$eCultivation['harvestWeeksExpected'] = $harvestWeeksExpected;
				} else {
					$eCultivation['yieldExpected'] = NULL;
					$eCultivation['harvestPeriodExpected'] = Cultivation::MONTH;
					$eCultivation['harvestMonthsExpected'] = NULL;
					$eCultivation['harvestWeeksExpected'] = NULL;
				}

			}

		}

	}

	private static function duplicateTasks(Series $eSeries, Series $eSeriesNew, \Collection $cCultivation, \Collection $cAction, bool $copyTimesheet, int $taskInterval): void {

		$cTask = Task::model()
			->select(Task::model()->getProperties() + [
				'cHarvest' => Harvest::model()
					->select([
						'structure', 'quantity', 'unit', 'date', 'week'
					])
					->delegateCollection('task')
			])
			->whereSeries($eSeries)
			->whereAction('IN', $cAction)
			->getCollection(NULL, NULL, 'id');

		if($cTask->empty()) {
			return;
		}

		if($copyTimesheet) {

			// Mise en réserve des temps de travail avec les anciennes tâches
			$cTimesheet = Timesheet::model()
				->select(array_diff(Timesheet::model()->getProperties(), ['id']))
				->whereTask('IN', $cTask)
				->getCollection();

		}

		$seasonInterval = ($eSeriesNew['season'] - $eSeries['season']);

		// Copie des tâches
		foreach($cTask as $eTask) {

			$eTask['id'] = NULL;
			$eTask['series'] = $eSeriesNew;
			$eTask['season'] = $eSeriesNew['season'];

			if($eTask['cultivation']->notEmpty()) {
				$eTask['cultivation'] = $cCultivation[$eTask['cultivation']['id']];
			}

			if($copyTimesheet === FALSE) {
				$eTask['time'] = NULL;
				$eTask['timesheetStart'] = NULL;
				$eTask['timesheetStop'] = NULL;
			}

			if(
				$seasonInterval !== 0 or
				$taskInterval !== 0
			) {

				if($eTask['plannedWeek'] !== NULL) {

					$week = toWeek(strtotime($eTask['plannedWeek'].' '.($seasonInterval < 0 ? '' : '+').$seasonInterval.' YEAR '.($taskInterval < 0 ? '' : '+').$taskInterval.' WEEK'));
					$eTask['plannedWeek'] = $week;

				}

				if($eTask['plannedDate'] !== NULL) {

					$week = date('Y-m-d', strtotime($eTask['plannedDate'].' '.($seasonInterval < 0 ? '' : '+').$seasonInterval.' YEAR '.($taskInterval < 0 ? '' : '+').$taskInterval.' WEEK'));
					$eTask['plannedDate'] = $week;

				}

				$eTask['doneWeek'] = NULL;
				$eTask['doneDate'] = NULL;
				$eTask['status'] = Task::TODO;

				if($eTask['harvest'] !== NULL) {
					$eTask['harvest'] = NULL;
					$eTask['harvestUnit'] = NULL;
					$eTask['harvestSize'] = NULL;
				}

			}

			Task::model()->insert($eTask);

			if($seasonInterval === 0) {

				foreach($eTask['cHarvest'] as $eHarvest) {

					$eHarvest['id'] = NULL;
					$eHarvest['series'] = $eTask['series'];
					$eHarvest['cultivation'] = $eTask['cultivation'];
					$eHarvest['task'] = $eTask;

				}

				Harvest::model()->insert($eTask['cHarvest']);

			}

		}

		if($copyTimesheet) {

			// Copie des temps de travail
			$cTimesheet->map(function(Timesheet $eTimesheet) use($cTask) {

				$eTask = $cTask[$eTimesheet['task']['id']];

				$eTimesheet['task'] = $eTask;
				$eTimesheet['series'] = $eTask['series'];
				$eTimesheet['cultivation'] = $eTask['cultivation'];
				$eTimesheet['plant'] = $eTask['plant'];

			});

			Timesheet::model()->insert($cTimesheet);

		}

	}

	private static function duplicatePlaces(Series $eSeries, Series $eSeriesNew): void {

		$cPlace =  Place::model()
			->select(['structure', 'season', 'zone', 'plot', 'bed', 'length', 'width', 'area'])
			->whereSeries($eSeries)
			->getCollection();

		if($cPlace->empty()) {
			return;
		}

		foreach($cPlace as $ePlace) {
			$ePlace['series'] = $eSeriesNew;
			$ePlace['season'] = $eSeriesNew['season'];
		}

		if(
			\map\Bed::model()
				->whereId('IN', $cPlace->getColumn('bed'))
				->where('seasonFirst > '.$eSeriesNew['season'].' OR seasonLast < '.$eSeriesNew['season'])
				->count() > 0 or
			\map\Plot::model()
				->whereId('IN', $cPlace->getColumn('plot'))
				->where('seasonFirst > '.$eSeriesNew['season'].' OR seasonLast < '.$eSeriesNew['season'])
				->count() > 0 or
			\map\Zone::model()
				->whereId('IN', $cPlace->getColumn('zone'))
				->where('seasonFirst > '.$eSeriesNew['season'].' OR seasonLast < '.$eSeriesNew['season'])
				->count() > 0
		) {
			Series::fail('duplicatePlaceConsistency');
		}

		if($cPlace->notEmpty()) {
			Place::model()->insert($cPlace);
		}

	}

	public static function updatePerennialContinued(Series $e): void {

		Series::model()->beginTransaction();

		$e['perennialStatus'] = Series::CONTINUED;

		$affected = Series::model()
			->select('perennialStatus')
			->wherePerennialStatus(Series::GROWING)
			->update($e);

		$fw = new \FailWatch();

		if($affected > 0) {
			self::duplicatePerennialWithCultivations($e);
		}

		if($fw->ok()) {
			Series::model()->commit();
		} else {
			Series::model()->rollBack();
		}

	}

	public static function updatePerennialFinished(Series $e): void {

		$e->expects(['perennialSeason']);

		$e['perennialStatus'] = Series::FINISHED;
		$e['perennialLifetime'] = $e['perennialSeason'];

		Series::model()
			->select('perennialStatus', 'perennialLifetime')
			->wherePerennialStatus(Series::GROWING)
			->update($e);

	}

	public static function closeCollection(\Collection $cSeries): void {

		Series::model()->beginTransaction();

		foreach($cSeries as $eSeries) {
			self::update($eSeries, ['status', 'outcome', 'comment']);
		}

		Series::model()->commit();

	}

	public static function getNumbering(\Collection $cSeries): array {

		$cCultivation = CultivationLib::getBySeries($cSeries);

		$groups = [];

		foreach($cSeries as $eSeries) {

			$eSeries['eCultivationFirst'] = $cCultivation
				->find(fn($eCultivation) => $eCultivation['series']['id'] === $eSeries['id'] and $eCultivation['startWeek'] !== NULL)
				->sort(['startWeek' => SORT_ASC])
				->first() ?? new Cultivation();

			$eSeries['bareName'] = self::getBareName($eSeries['name']);

			$groups[$eSeries['bareName']] ??= new \Collection();
			$groups[$eSeries['bareName']][$eSeries['id']] = $eSeries;

		}

		uksort($groups, fn($name1, $name2) => \L::getCollator()->compare($name1, $name2));

		foreach($groups as $cSeriesGroup) {
			$cSeriesGroup->sort(fn(Series $e1, Series $e2) => self::getNumberingRank($e1) <=> self::getNumberingRank($e2));
		}

		return $groups;

	}

	private static function getNumberingRank(Series $eSeries): array {

		if($eSeries['eCultivationFirst']->empty()) {
			return [1, 0, $eSeries['id']];
		} else {
			return [0, $eSeries['eCultivationFirst']['startWeek'], $eSeries['id']];
		}

	}

	public static function getBareName(string $name): string {
		return preg_replace('/\s+(#|n°\s*)?\d+$/iu', '', $name);
	}

	public static function updateNameCollection(\Collection $cSeries): void {

		Series::model()->beginTransaction();

		foreach($cSeries as $eSeries) {
			self::update($eSeries, ['name']);
		}

		Series::model()->commit();

	}

	public static function update(Series $e, array $properties = []): void {

		Series::model()->beginTransaction();

		$updateCultivation = [];
		$updatePlace = [];
		$updateTask = [];

		if(in_array('perennialLifetime', $properties)) {

			$e->expects(['cycle', 'perennialSeason']);

			$properties[] = 'perennialStatus';

			if($e['perennialLifetime'] === NULL or $e['perennialLifetime'] > $e['perennialSeason']) {
				$e['perennialStatus'] = Series::GROWING;
			} else {
				$e['perennialStatus'] = Series::FINISHED;
			}

		}

		$updateUse = FALSE;

		if(in_array('use', $properties)) {

			$e->expects(['use']);

			if($e['use'] !== $e['oldUse']) {

				$updateUse = TRUE;

				switch($e['use']) {

					case Series::BED :

						$e['areaTarget'] = NULL;
						$properties[] = 'areaTarget';

						$updateCultivation['rowSpacing'] = NULL;
						break;

					case Series::BLOCK :

						$e['lengthTarget'] = NULL;
						$properties[] = 'lengthTarget';

						$e['bedWidth'] = NULL;
						$properties[] = 'bedWidth';

						$e['alleyWidth'] = NULL;
						$properties[] = 'alleyWidth';

						$updateCultivation['rows'] = NULL;
						break;

				}

			}

		}

		if(
			$e['use'] === Series::BED and
			(in_array('lengthTarget', $properties) or in_array('bedWidth', $properties))
		) {
			if($e['lengthTarget'] !== NULL) {
				$e['areaTarget'] = round($e['lengthTarget'] * ($e['bedWidth'] + $e['alleyWidth'] ?? 0) / 100);
			} else {
				$e['areaTarget'] = NULL;
			}
			$properties[] = 'areaTarget';
		}

		parent::update($e, $properties);

		if(
			in_array('season', $properties) and
			$e['oldSeason'] !== $e['season']
		) {

			$updateCultivation['season'] = $e['season'];
			$updateTask['season'] = $e['season'];
			$updatePlace['season'] = $e['season'];

			self::recalculate($e['structure'], $e);

		}

		if(
			in_array('sequence', $properties) and
			$e['oldSequence']->is($e['sequence']) === FALSE
		) {

			if($e['sequence']->notEmpty()) {

				$cCrop = \sequence\CropLib::getBySequence($e['sequence']);

				foreach($cCrop as $eCrop) {

					Cultivation::model()
						->whereSeries($e)
						->wherePlant($eCrop['plant'])
						->update([
							'crop' => $eCrop['id']
						]);

				}

			} else {
				$updateCultivation['crop'] = new \sequence\Crop();
			}

		}


		if($updateCultivation) {

			Cultivation::model()
				->whereSeries($e)
				->update($updateCultivation);

		}

		if($updateTask) {

			Task::model()
				->whereSeries($e)
				->update($updateTask);

		}

		if($updatePlace) {

			Place::model()
				->whereSeries($e)
				->update($updatePlace);

		}

		if(
			(array_intersect(['bedWidth', 'alleyWidth'], $properties) and $e['use'] === Series::BED) or
			$updateUse
		) {
			CultivationLib::updateDensityBySeries($e);
		}

		if($updateUse) {
			PlaceLib::deleteBySeries($e);
			PlaceLib::recalculateMetadata($e, new \Collection());
			PlaceLib::updateMetadata($e);
		} else {

			if(
				in_array('alleyWidth', $properties) and
				$e['use'] === Series::BED
			) {
				PlaceLib::recalculateAreaBySeries($e);
			}

		}

		Series::model()->commit();

	}

	public static function delete(Series $e): void {

		$e->expects(['id']);

		Series::model()->beginTransaction();

		// Supprime les cultures
		Cultivation::model()
			->whereSeries($e)
			->delete();

		Slice::model()
			->whereSeries($e)
			->delete();

		// Supprime les tâches
		$cTask = Task::model()
			->select('id')
			->whereSeries($e)
			->getCollection();

		TaskLib::deleteCollection($cTask, recalculate: FALSE);

		// Supprime les emplacements
		Place::model()
			->whereSeries($e)
			->delete();

		// Supprime les rapports, que tient le package analyze, retiré de l'export public
		if(\structure\StructureSetting::hasAnalyzePackage()) {
			self::notify('deleteSeries', $e);
		}
		
		Timesheet::model()
			->whereSeries($e)
			->delete();

		Series::model()->delete($e);

		Series::model()->commit();

	}

	public static function deleteCollection(\Collection $cSeries): void {

		foreach($cSeries as $eSeries) {
			self::delete($eSeries);
		}

	}

	public static function recalculate(\structure\Structure $eStructure, Series $e, \work\Action $eAction = new \work\Action()): void {

		if($e->empty()) {
			return;
		}

		$e->expects([
			'season'
		]);

		if($eAction->notEmpty()) {

			$eAction->expects(['fqn']);

			if(
				in_array($eAction['fqn'], [ACTION_SEMIS_DIRECT, ACTION_PLANTATION, ACTION_RECOLTE]) === FALSE and
				$eAction['soil'] === FALSE
			) {
				return;
			}

		}

		// On récupère les données de la série à jour
		Series::model()
			->select(['bedStartCalculated', 'bedStopCalculated'])
			->get($e);

		// Recalculer les données internes à chaque culture
		$min = new \Sql('MIN(IF(doneWeek IS NOT NULL, CAST(SUBSTRING(doneWeek, 7, 2) AS SIGNED) + (CAST(SUBSTRING(doneWeek, 1, 4) AS SIGNED) - '.$e['season'].') * 100, CAST(SUBSTRING(plannedWeek, 7, 2) AS SIGNED) + (CAST(SUBSTRING(plannedWeek, 1, 4) AS SIGNED) - '.$e['season'].') * 100))', 'int');
		$max = new \Sql('MAX(IF(doneWeek IS NOT NULL, CAST(SUBSTRING(doneWeek, 7, 2) AS SIGNED) + (CAST(SUBSTRING(doneWeek, 1, 4) AS SIGNED) - '.$e['season'].') * 100, CAST(SUBSTRING(plannedWeek, 7, 2) AS SIGNED) + (CAST(SUBSTRING(plannedWeek, 1, 4) AS SIGNED) - '.$e['season'].') * 100))', 'int');

		$cCrop = \sequence\SequenceLib::doRecalculate(
			Cultivation::model(),
			Task::model(),
			'series',
			'cultivation',
			$min,
			$max,
			$eStructure,
			$e,
			['id', 'plant', 'harvestWeeks', 'harvestWeeksExpected']
		);

		// On cherche les récoltes attendues
		$stopHarvest = NULL;

		foreach($cCrop as $eCrop) {

			[, $week] = $eCrop->getHarvestBounds();

			if($week === NULL) {
				continue;
			}

			$currentStopHarvest = week_number($week) + (week_year($week) - $e['season']) * 100;
			$stopHarvest = ($stopHarvest === NULL) ? $currentStopHarvest : max($stopHarvest, $currentStopHarvest);

		}

		if($e['bedStopCalculated'] === NULL or $e['bedStopCalculated'] < $stopHarvest) {
			$e['bedStopCalculated'] = $stopHarvest;
		}

		Series::model()
			->select(['bedStartCalculated', 'bedStopCalculated'])
			->update($e);

	}

	/**
	 * Les places des planches `$bedIds` et les séries qui les occupent pendant l'année de la saison, avec leurs cultures,
	 * à plat, telles que les lit une scène (`\map\SceneLib`, dont la règle de version vaut pour ce format) et la fiche
	 * d'une série (`getSheet()`). Les séries sont celles de la saison et celles des saisons voisines qui tiennent déjà ou
	 * encore leur planche cette année-là (`getSceneSeriesModel()`). La place d'une série sur sa planche est celle de
	 * `Series::getBedDates()`, sur sa propre saison.
	 */
	public static function getForScene(\structure\Structure $eStructure, int $season, array $bedIds): array {

		$cPlace = Place::model()
			->select(['id', 'bed', 'series', 'length', 'area'])
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->whereSeries('IN', self::getSceneSeriesModel($eStructure, $season)->getSql())
			->whereBed('IN', $bedIds)
			->getCollection();

		return [
			'places' => self::getPlacesForScene($cPlace),
			'series' => self::getSeriesForScene($eStructure, array_values(array_unique($cPlace->getColumnCollection('series')->getIds())))
		];

	}

	/**
	 * Les séries qu'une scène de la saison `$season` pose : celles de la saison, celles de la saison suivante qui prennent
	 * leur planche avant la fin de l'année, et celles de la saison précédente qui la rendent après son début. Une voisine
	 * n'y est qu'avec ses deux bouts, ceux sans lesquels `Series::getBedDates()` ne la date pas.
	 *
	 * La semaine calculée d'une série vaut « semaine ISO + (année ISO - saison) × 100 » (`Series::calculatedBed()`) : pour
	 * la saison suivante, l'année de la saison montrée s'écrit sous 1, et pour la précédente, à partir de 101. Une pérenne
	 * installée prend sa planche en semaine 1 de sa saison, une pérenne reconduite la rend en fin de la sienne, quelle que
	 * soit sa semaine calculée (`Series::getBedDates()`) : ni l'une ni l'autre ne déborde sur l'année voisine, la série qui
	 * la suit ou la précède dans la saison montrée y tient sa place.
	 *
	 * La requête est citée par celle des places, d'où l'alias `s1` des conditions écrites à la main.
	 */
	private static function getSceneSeriesModel(\structure\Structure $eStructure, int $season): SeriesModel {

		$perennial = Series::model()->format(Series::PERENNIAL);
		$installed = 's1.cycle = '.$perennial.' AND s1.perennialSeason > 1';
		$continued = 's1.cycle = '.$perennial.' AND (s1.perennialLifetime IS NULL OR s1.perennialSeason < s1.perennialLifetime)';

		return new SeriesModel()
			->select('id')
			->whereStructure($eStructure)
			->whereSeason('IN', [$season - 1, $season, $season + 1])
			->where(
				's1.season = '.$season.' OR '.
				'(s1.season = '.($season + 1).' AND s1.bedStartCalculated <= 0 AND NOT ('.$installed.') AND (s1.bedStopCalculated IS NOT NULL OR ('.$continued.'))) OR '.
				'(s1.season = '.($season - 1).' AND s1.bedStopCalculated >= 101 AND NOT ('.$continued.') AND (s1.bedStartCalculated IS NOT NULL OR ('.$installed.')))'
			);

	}

	/**
	 * Les places `$cPlace`, chargées avec `id`, `bed`, `series`, `length` et `area`, au format de `getForScene()`. La surface, en m²,
	 * est ce qu'un jardin sans planche dessinée donne à la place dans la scène : elle se pose aussi sans longueur.
	 */
	private static function getPlacesForScene(\Collection $cPlace): array {

		return $cPlace->makeArray(fn(Place $ePlace) => [
			'id' => $ePlace['id'],
			'bed' => $ePlace['bed']['id'],
			'series' => $ePlace['series']['id'],
			'length' => $ePlace['length'],
			'area' => $ePlace['area'],
		]);

	}

	/**
	 * Les séries `$seriesIds` avec leurs cultures, au format de `getForScene()`. `weights` porte, par unité, le poids en kg
	 * d'une botte ou d'une pièce d'une culture vendue au kg : c'est la conversion de `yieldByUnit` (`Cultivation.e.php`),
	 * que le script applique aux récoltes du jour montré sans en reprendre la règle.
	 */
	private static function getSeriesForScene(\structure\Structure $eStructure, array $seriesIds): array {

		$cSeries = Series::model()
			->select(['id', 'name', 'season', 'area', 'cycle', 'status', 'bedStartCalculated', 'bedStopCalculated', 'perennialSeason', 'perennialLifetime'])
			->whereStructure($eStructure)
			->whereId('IN', $seriesIds)
			->getCollection();

		$cCultivation = Cultivation::model()
			->select([
				'id', 'series', 'startWeek', 'startAction', 'harvested', 'mainUnit', 'bunchWeight', 'unitWeight',
				'harvestWeeks', 'harvestWeeksExpected',
				'plant' => ['name', 'fqn', 'color']
			])
			->whereStructure($eStructure)
			->whereSeries('IN', $seriesIds)
			->sort(['startWeek' => SORT_ASC])
			->getCollection();

		$cSlice = Slice::model()
			->select([
				'cultivation',
				'variety' => ['name']
			])
			->whereStructure($eStructure)
			->whereSeries('IN', $seriesIds)
			->getCollection();

		$varieties = [];

		foreach($cSlice as $eSlice) {
			$varieties[$eSlice['cultivation']['id']][$eSlice['variety']['name']] = $eSlice['variety']['name'];
		}

		$cultivations = [];

		foreach($cCultivation as $eCultivation) {

			$weights = [];

			if($eCultivation['mainUnit'] === Cultivation::KG) {

				if($eCultivation['bunchWeight']) {
					$weights[Cultivation::BUNCH] = $eCultivation['bunchWeight'];
				}

				if($eCultivation['unitWeight']) {
					$weights[Cultivation::UNIT] = $eCultivation['unitWeight'];
				}

			}

			$cultivations[$eCultivation['series']['id']][] = [
				'id' => $eCultivation['id'],
				'fqn' => $eCultivation['plant']['fqn'],
				'plant' => $eCultivation['plant']['name'],
				'color' => $eCultivation['plant']['color'],
				'varieties' => array_values($varieties[$eCultivation['id']] ?? []),
				'startAction' => $eCultivation['startAction'],
				'harvested' => $eCultivation['harvested'],
				'unit' => $eCultivation['mainUnit'],
				'weights' => $weights,
				'harvestWeeks' => $eCultivation['harvestWeeks'] ?? $eCultivation['harvestWeeksExpected'],
				'harvestWeeksExpected' => $eCultivation['harvestWeeksExpected'],
				'harvestDone' => ($eCultivation['harvestWeeks'] !== NULL),
			];

		}

		$series = [];

		foreach($cSeries as $eSeries) {

			[$start, $stop] = $eSeries->getBedDates($eSeries['season']);

			$series[] = [
				'id' => $eSeries['id'],
				'name' => $eSeries['name'],
				'season' => $eSeries['season'],
				'area' => $eSeries['area'],
				'cycle' => $eSeries['cycle'],
				'status' => $eSeries['status'],
				'start' => $start,
				'stop' => $stop,
				'cultivations' => $cultivations[$eSeries['id']] ?? [],
			];

		}

		return $series;

	}

	/**
	 * La fiche d'une série au format d'une scène (`getForScene()`), bornée à elle : ses places sur la saison, ses
	 * planches avec leur jardin et leur parcelle, ses interventions et ses récoltes. La fiche du plan d'assolement
	 * la rend par le même script que celle du plan 3D (`series/static/js/seriesSheet.js`), qui ne lit que ces clés.
	 * Les planches sont celles que la scène pose : actives sur la saison.
	 */
	public static function getSheet(\structure\Structure $eStructure, Series $eSeries, int $season): array {

		$cPlace = Place::model()
			->select(['id', 'bed', 'series', 'length', 'area'])
			->whereStructure($eStructure)
			->whereSeason($season)
			->whereSeries($eSeries)
			->getCollection();

		$cBed = \map\SeasonLib::whereSeason(\map\Bed::model(), $season)
			->select(['id', 'name', 'plot' => ['name', 'zone' => ['name']]])
			->whereStructure($eStructure)
			->whereStatus(\map\Bed::ACTIVE)
			->whereId('IN', $cPlace->getColumnCollection('bed')->getIds())
			->getCollection(index: 'id');

		// Une place sur une planche que la scène ne pose pas, inactive ou hors saison, n'a rien à montrer
		$cPlace = $cPlace->find(fn(Place $ePlace) => $cBed->offsetExists($ePlace['bed']['id']));

		$tasks = TaskLib::getForScene($eStructure, [$eSeries['id']]);

		$beds = [];
		$plots = [];
		$zones = [];

		foreach($cBed as $eBed) {

			$ePlot = $eBed['plot'];

			$beds[] = ['id' => $eBed['id'], 'name' => $eBed['name'], 'plot' => $ePlot['id']];
			$plots[$ePlot['id']] = ['id' => $ePlot['id'], 'name' => $ePlot['name'], 'zone' => $ePlot['zone']['id']];
			$zones[$ePlot['zone']['id']] = ['id' => $ePlot['zone']['id'], 'name' => $ePlot['zone']['name']];

		}

		return [
			'season' => $season,
			'zones' => array_values($zones),
			'plots' => array_values($plots),
			'beds' => $beds,
			'places' => self::getPlacesForScene($cPlace),
			'series' => self::getSeriesForScene($eStructure, [$eSeries['id']]),
			'actions' => $tasks['actions'],
			'columns' => [
				'tasks' => TaskLib::COLUMNS_SCENE,
				'harvests' => HarvestLib::COLUMNS_SCENE,
			],
			'tasks' => $tasks['tasks'],
			'harvests' => HarvestLib::getForScene($eStructure, $season, [$eSeries['id']]),
		];

	}

}
?>
