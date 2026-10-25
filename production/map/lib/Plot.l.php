<?php
namespace map;

class PlotLib extends PlotCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {

		$properties = [];

		// Côté web la parcelle vient du lien cliqué, l'API doit pouvoir la désigner. En premier :
		// seasonFirst se contrôle contre les saisons de la parcelle, qui doit donc être construite avant.
		if($origin === 'api') {
			$properties[] = 'zone';
		}

		$properties = array_merge($properties, ['name', 'mode']);

		// Un décor de la scène 3D n'est exposé par l'API ni en lecture ni en écriture (base/scene/CLAUDE.md)
		if($origin !== 'api') {
			$properties[] = 'decorGreenhouse';
		}

		// Le contour avant les dimensions et la surface : un abri tracé en tire les siennes, un jardin tracé la sienne
		return array_merge($properties, ['coordinates', 'length', 'width', 'area', 'seasonFirst']);

	}

	/**
	 * Pas de 'mode' : couvrir ou découvrir un jardin passe par doUpdateGreenhouse, qui le bascule
	 * lui-même. Une modification ordinaire ne peut donc pas le changer, ni depuis le web ni par l'API.
	 * Pas de 'zone' non plus : aucun écran ne déplace un jardin d'une parcelle à l'autre, et l'API ne fait pas plus
	 * que l'écran. La parcelle se pose à la création.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {

		$properties = ['name'];

		// Hors de l'API, comme à la création
		if($origin !== 'api') {
			$properties[] = 'decorGreenhouse';
		}

		// Comme à la création, le contour avant ce qui s'en déduit
		return array_merge($properties, ['coordinates', 'length', 'width', 'area', 'seasonFirst', 'seasonLast']);

	}

	/* Les dimensions qu'un jardin tracé aura sous abri, lues sur son contour comme à l'enregistrement (Plot::properties(), étape
	   `drawn`), pour que le panneau les montre telles qu'elles s'enregistreront. Sans contour, il garde celles qu'on saisit. Une
	   parcelle non tracée n'a pas de carte où tracer : le panneau n'envoie alors aucun contour, et l'abri s'enregistre sur les
	   dimensions saisies. Le panneau renvoie le contour tel quel, donc un abri trop étroit garde les dimensions enregistrées */
	public static function getDimensionsDrawn(Plot $e): ?array {

		$e->expects(['coordinates', 'length', 'width', 'zone' => ['coordinates']]);

		if($e['coordinates'] === NULL or $e['zone']['coordinates'] === NULL) {
			return NULL;
		}

		$dimensions = MapLib::getDimensions($e['coordinates']);

		if(self::isNarrow($dimensions) and $e['length'] !== NULL and $e['width'] !== NULL) {
			return [$e['length'], $e['width']];
		}

		return $dimensions;

	}

	// Plus étroit que ce qu'admet le schéma, un contour ne se mesure pas en abri
	public static function isNarrow(array $dimensions): bool {

		return (
			$dimensions[0] < Plot::model()->getPropertyRange('length')[0] or
			$dimensions[1] < Plot::model()->getPropertyRange('width')[0]
		);

	}

	// Sans borne de saison : dit si un jardin est de la structure, qu'une saison l'exploite ou non
	public static function existsByStructure(\structure\Structure $eStructure, int $id): bool {

		return Plot::model()
			->whereStructure($eStructure)
			->whereId($id)
			->exists();

	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, ?int $season = NULL): \Collection|Plot {

		if($id !== NULL) {
			Plot::model()->whereId($id);
		}

		SeasonLib::whereSeason(Plot::model(), $season);

		Plot::model()
			->select(Plot::getSelection())
			->whereStructure($eStructure);

		if($id !== NULL) {
			return Plot::model()->get();
		} else {
			return Plot::model()
				->getCollection()
				->sort('name', natural: TRUE);
		}

	}

	public static function createFromZone(Zone $eZone): void {

		$e = new Plot([
			'zone' => $eZone,
			'zoneFill' => TRUE,
			'mode' => Plot::OPEN_FIELD,
			'area' => $eZone['area']
		]);

		self::create($e);

	}

	public static function create(Plot $e): void {

		$e->add([
			'zoneFill' => FALSE
		]);

		$e->expects([
			'zone' => ['structure'],
			'area'
		]);

		Plot::model()->beginTransaction();

		$e['structure'] = $e['zone']['structure'];

		Plot::model()->insert($e);

		// On crée une planche inféodée au jardin
		$eBed = new Bed([
			'zone' => $e['zone'],
			'zoneFill' => $e['zoneFill'],
			'plot' => $e,
			'plotFill' => TRUE,
			'area' => $e['area']
		]);

		BedLib::create($eBed);

		Plot::model()->commit();

	}

	/**
	 * La copie d'un jardin, construite par plot:doDuplicate (son nom et, posée sur la carte, son contour et son `placement`), avec
	 * les planches actives de l'original. Posée sur la carte, elle emporte aussi leurs traits, de toutes les saisons.
	 */
	public static function duplicate(Plot $ePlotSource, Plot $ePlot): Plot {

		$ePlotSource->expects(['structure', 'zone', 'mode', 'decorGreenhouse', 'length', 'width', 'area', 'seasonFirst', 'seasonLast']);
		$ePlot->expects(['name']);

		// Une copie qui ne se pose pas sur la carte n'a ni contour ni placement
		$ePlot->add([
			'placement' => NULL
		]);

		$eZone = $ePlotSource['zone'];
		$eZone['structure'] = $ePlotSource['structure'];

		$ePlot->merge([
			'zone' => $eZone,
			'mode' => $ePlotSource['mode'],
			'decorGreenhouse' => $ePlotSource['decorGreenhouse'],
			'length' => $ePlotSource['length'],
			'width' => $ePlotSource['width'],
			'area' => $ePlotSource['area'],
			'seasonFirst' => $ePlotSource['seasonFirst'],
			'seasonLast' => $ePlotSource['seasonLast'],
		]);

		Plot::model()->beginTransaction();

		self::create($ePlot);

		$cBedSource = Bed::model()
			->select(['id', 'name', 'length', 'width', 'alleyWidth', 'seasonFirst', 'seasonLast'])
			->wherePlot($ePlotSource)
			->wherePlotFill(FALSE)
			->whereStatus(Bed::ACTIVE)
			->getCollection();

		// Chaque copie sous l'id de sa planche d'origine, où les traits recopiés la retrouvent
		$cBedBySource = new \Collection();

		foreach($cBedSource as $eBedSource) {

			$eBed = new Bed([
				'plot' => $ePlot,
				'name' => $eBedSource['name'],
				'length' => $eBedSource['length'],
				'width' => $eBedSource['width'],
				'alleyWidth' => $eBedSource['alleyWidth'],
				'seasonFirst' => $eBedSource['seasonFirst'],
				'seasonLast' => $eBedSource['seasonLast'],
			]);

			BedLib::create($eBed);

			$cBedBySource[$eBedSource['id']] = $eBed;

		}

		if($ePlot['placement'] !== NULL) {
			DrawLib::duplicateByPlot($ePlotSource, $ePlot, $cBedBySource);
		}

		Plot::model()->commit();

		return $ePlot;

	}

	/* Le contour de chaque jardin de la parcelle, de toutes ses saisons, suit le mouvement du sien (MapLib::getMotion()). Un jardin
	   qui couvre la parcelle n'a pas de contour à lui */
	public static function moveByZone(Zone $eZone, array $motion): void {

		$eZone->expects(['structure']);

		$cPlot = Plot::model()
			->select(['id', 'coordinates'])
			->whereStructure($eZone['structure'])
			->whereZone($eZone)
			->whereCoordinates('!=', NULL)
			// Chaque contour est réécrit depuis celui qu'on lit : un jardin retracé entre-temps serait remplacé par l'ancien, tourné
			->option('for-update')
			->getCollection();

		foreach($cPlot as $ePlot) {

			$ePlot['coordinates'] = MapLib::getTransformed($ePlot['coordinates'], $motion['origin'], $motion['shift'], $motion['angle']);
			$ePlot['updatedAt'] = new \Sql('NOW()');

			// Pas par update(), qui déplacerait les traits du jardin : la parcelle déplace déjà tous les siens (DrawLib::moveByZone())
			Plot::model()
				->select(['coordinates', 'updatedAt'])
				->update($ePlot);

		}

	}

	public static function update(Plot $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		// Changer de mode change aussi les dimensions et la surface, que Plot::build() a posées. Le contour d'un abri donne ses
		// dimensions (Plot::properties(), étape `drawn`), qui le suivent même quand il part seul (Plot_0_1beta, `addBuildOnly`)
		if(
			array_intersect(['mode', 'length', 'width'], $properties) !== [] or
			(in_array('coordinates', $properties) and $e['mode'] === Plot::GREENHOUSE)
		) {
			$properties = array_unique(array_merge($properties, ['mode', 'length', 'width', 'area']));
		}

		Plot::model()->beginTransaction();

		// Un contour tiré d'un bloc ou tourné entraîne les traits de ses planches, de toutes les saisons : le contour d'un jardin vaut
		// pour toutes, et ses planches restent dedans dans chacune. L'ancien contour se lit sous verrou, première lecture de la
		// transaction : un second mouvement simultané attend, et part du contour que le premier a écrit, donc de traits déjà
		// déplacés une fois. Un contour effacé n'entraîne rien
		if(in_array('coordinates', $properties) and $e['coordinates'] !== NULL) {

			$coordinatesBefore = Plot::model()
				->select('coordinates')
				->whereId($e)
				->getForUpdate()['coordinates'];

			$motion = MapLib::getMotion($coordinatesBefore, $e['coordinates']);

		} else {
			$motion = NULL;
		}

		Plot::model()
			->select($properties)
			->update($e);

		if($motion !== NULL) {
			DrawLib::moveByPlot($e, $motion);
		}

		if(in_array('area', $properties)) {

			Bed::model()
				->wherePlot($e)
				->wherePlotFill(TRUE)
				->whereStatus(Bed::ACTIVE)
				->update([
					'area' => $e['area']
				]);

		}

		if(in_array('seasonFirst', $properties)) {

			Bed::model()
				->wherePlot($e)
				->wherePlotFill(TRUE)
				->whereStatus(Bed::ACTIVE)
				->update([
					'seasonFirst' => $e['seasonFirst']
				]);

			if($e['seasonFirst'] !== NULL) {

				Bed::model()
					->wherePlot($e)
					->where('seasonFirst <'.$e['seasonFirst'])
					->whereStatus(Bed::ACTIVE)
					->update([
						'seasonFirst' => $e['seasonFirst']
					]);

			}

		}

		if(in_array('seasonLast', $properties)) {

			Bed::model()
				->wherePlot($e)
				->wherePlotFill(TRUE)
				->whereStatus(Bed::ACTIVE)
				->update([
					'seasonLast' => $e['seasonLast']
				]);

			if($e['seasonLast'] !== NULL) {

				Bed::model()
					->wherePlot($e)
					->where('seasonLast >'.$e['seasonLast'])
					->whereStatus(Bed::ACTIVE)
					->update([
						'seasonLast' => $e['seasonLast']
					]);

			}

		}

		Plot::model()->commit();

	}

	public static function putFromZone(\Collection|Zone $value, bool $withBeds = FALSE, bool $withDraw = FALSE, ?int $season = NULL, ?\Closure $newSelection = NULL): void {

		$selection = [
			'id',
			'structure', 'zone', 'zoneFill', 'name', 'mode',
			'length', 'width', 'area', 'coordinates', 'position',
			'seasonFirst', 'seasonLast',
		];

		if($withBeds) {

			SeasonLib::whereSeason(Bed::model(), $season);

			$selection['cBed'] = Bed::model()
				->select([
					'id', 'name', 'structure',
					'seasonFirst', 'seasonLast',
					'zoneFill', 'plotFill', 'length', 'width', 'alleyWidth', 'area'
				])
				->whereStatus(Bed::ACTIVE)
				->sort(new \Sql('plotFill DESC, name ASC'))
				->delegateCollection('plot', index: 'id', callback: fn(\Collection $cBed) => $cBed->sort('name', natural: TRUE));

		}

		if($withDraw) {

			$selection['cDraw'] = Draw::model()
				->select(Draw::getSelection())
				->whereSeason('<=', $season, if: $season !== NULL)
				// Dans une saison, les planches se lisent dans l'ordre où elles ont été posées : les deux dernières orientent le rang suivant
				->sort(['season' => SORT_DESC, 'id' => SORT_ASC])
				->delegateCollection('plot');

		}

		if(is_closure($newSelection)) {
			$newSelection($selection);
		}

		SeasonLib::whereSeason(Plot::model(), $season);

		if(Zone::model()
			->select([
				'cPlot' => Plot::model()
					->select($selection)
					->delegateCollection('zone')
			])
			->get($value)) {

			if($value instanceof Zone) {
				self::sortPlots($value['cPlot']);
			} else {
				foreach($value as $eZone) {
					self::sortPlots($eZone['cPlot']);
				}
			}

		}

		if($withBeds) {
			self::putFromZoneWithBedsStats($value);
		}

		if($withDraw) {
			self::putFromZoneWithDrawStats($value);
		}

	}

	public static function sortPlots(\Collection $cPlot): void {

		$cPlot->sort(function(Plot $e1, Plot $e2) {

			if($e1['position'] !== $e2['position']) {

				if($e1['position'] === NULL) {
					return 1;
				}

				if($e2['position'] === NULL) {
					return -1;
				}

				return $e1['position'] <=> $e2['position'];

			}

			if($e1['zoneFill'] !== $e2['zoneFill']) {
				return $e1['zoneFill'] ? -1 : 1;
			}

			return strnatcasecmp($e1['name'] ?? '', $e2['name'] ?? '');

		});

	}

	public static function updatePositions(Zone $eZone, array $ids): void {

		$cPlot = Plot::model()
			->select(['id'])
			->whereZone($eZone)
			->whereId('IN', $ids)
			->getCollection(index: 'id');

		if($cPlot->count() !== count($ids)) {
			Plot::fail('ids.check');
			return;
		}

		Plot::model()->beginTransaction();

		foreach(array_values($ids) as $position => $id) {

			$ePlot = $cPlot[$id];
			$ePlot['position'] = $position;

			self::update($ePlot, ['position']);

		}

		Plot::model()->commit();

	}

	private static function putFromZoneWithBedsStats(\Collection|Zone $value) {

		$calc = function(Zone $e) {

			$plots = 0;
			$plotsGreenhouse = 0;
			$zoneBeds = 0;
			$zoneBedsArea = 0;

			foreach($e['cPlot'] as $ePlot) {

				if($ePlot['zoneFill'] === FALSE) {

					// Un abri est un jardin, mais le producteur les compte séparément
					if($ePlot['mode'] === Plot::GREENHOUSE) {
						$plotsGreenhouse++;
					} else {
						$plots++;
					}

				}

				$plotBeds = 0;
				$plotBedsArea = 0;

				foreach($ePlot['cBed'] as $eBed) {

					if($eBed['plotFill']) {
						continue;
					}

					$plotBeds++;
					$plotBedsArea += $eBed['area'];

				}

				$ePlot['beds'] = $plotBeds;
				$ePlot['bedsArea'] = $plotBedsArea;

				$zoneBeds += $plotBeds;
				$zoneBedsArea += $plotBedsArea;

			}

			$e['plots'] = $plots;
			$e['plotsGreenhouse'] = $plotsGreenhouse;
			$e['beds'] = $zoneBeds;
			$e['bedsArea'] = $zoneBedsArea;

		};

		if($value instanceof \Collection) {
			$value->map($calc);
		} else {
			$calc($value);
		}

	}

	private static function putFromZoneWithDrawStats(\Collection|Zone $value) {

		$calc = function(Zone $e) {

			foreach($e['cPlot'] as $ePlot) {

				// La place d'une planche tracée, que la console écrit sur sa case pour qu'un geste rouvre la planche où elle est
				$ePlot['cBed']->setColumn('drawCorner', NULL);
				$ePlot['cBed']->setColumn('drawAngle', NULL);

				if($ePlot['cDraw']->empty()) {
					$ePlot['cBed']->setColumn('drawn', FALSE);
					$cDrawPlaced = new \Collection();
				} else {

					// La saison affichée est la plus récente du jardin, marque comprise : une saison effacée ne laisse pas reparaître la précédente
					$seasonSelected = $ePlot['cDraw']->first()['season'];
					$ePlot['cDraw'] = $ePlot['cDraw']->find(fn($eDraw) => $eDraw['season'] === $seasonSelected);

					$bedIdsDrawn = $ePlot['cDraw']->find(fn($eDraw) => $eDraw['bed']->notEmpty())->getColumnCollection('bed')->getIds();

					foreach($ePlot['cBed'] as $eBed) {
						$eBed['drawn'] = in_array($eBed['id'], $bedIdsDrawn);
					}

					$cDrawPlaced = self::getDrawsPlaced($ePlot);

					foreach($cDrawPlaced as $eDraw) {
						$eDraw['bed']['drawCorner'] = $eDraw['corner'];
						$eDraw['bed']['drawAngle'] = $eDraw['angle'];
					}

				}

				$ePlot['bedsDrawn'] = self::getBedsDrawn($cDrawPlaced);

				// Le trait d'où part le rang suivant, NULL sans planche tracée
				$ePlot['lineNext'] = DrawLib::getLineNext($cDrawPlaced);

			}

		};

		if($value instanceof \Collection) {
			$value->map($calc);
		} else {
			$calc($value);
		}

	}

	/**
	 * Les lignes des planches que la carte dessine, dans l'ordre où elles ont été posées, chacune portant sa planche entière : une
	 * planche active sans longueur ou sans largeur n'a pas de rectangle à montrer.
	 */
	private static function getDrawsPlaced(Plot $ePlot): \Collection {

		$cDrawPlaced = new \Collection();

		foreach($ePlot['cDraw'] as $eDraw) {

			if(
				$eDraw['bed']->empty() or
				$ePlot['cBed']->offsetExists($eDraw['bed']['id']) === FALSE
			) {
				continue;
			}

			$eBed = $ePlot['cBed'][$eDraw['bed']['id']];

			if($eBed['length'] === NULL or $eBed['width'] === NULL) {
				continue;
			}

			$eDrawPlaced = clone $eDraw;
			$eDrawPlaced['bed'] = $eBed;

			$cDrawPlaced[] = $eDrawPlaced;

		}

		return $cDrawPlaced;

	}

	/* Les planches tracées comme la carte les reçoit, à la page qui s'ouvre comme à la réponse d'un geste (`bedsDrawn`) */
	private static function getBedsDrawn(\Collection $cDrawPlaced): array {

		$bedsDrawn = [];

		foreach($cDrawPlaced as $eDraw) {
			$bedsDrawn[] = [
				'id' => $eDraw['id'],
				'bed' => $eDraw['bed']->extracts(['id', 'name', 'length', 'width']),
				'corner' => $eDraw['corner'],
				'angle' => $eDraw['angle']
			];
		}

		return $bedsDrawn;

	}

	/**
	 * Les planches tracées du jardin, réduites à celles qu'un geste vient de toucher : la carte ne repose qu'elles
	 */
	public static function getBedsDrawnByBeds(Plot $ePlot, \Collection $cBed): array {

		$ePlot->expects(['bedsDrawn']);

		$ids = $cBed->getIds();

		return array_values(array_filter($ePlot['bedsDrawn'], fn(array $bedDrawn) => in_array($bedDrawn['bed']['id'], $ids)));

	}

	public static function putFromZoneWithSeries(\structure\Structure $eStructure, \Collection|Zone $value, ?int $season = NULL, array $withSeries = [], bool $onlySeries = FALSE): void {

		self::putFromZone($value, withBeds: TRUE, season: $season, newSelection: function(&$selection) use($eStructure, $withSeries, $onlySeries) {

			$cAction = \work\ActionLib::getMainByStructure($eStructure);
			$cActionStart = $cAction->find(fn($eAction) => in_array($eAction['fqn'], [ACTION_SEMIS_DIRECT, ACTION_PLANTATION]));
			$cActionSoil = \work\ActionLib::getMainByStructure($eStructure)->find(fn($eAction) => $eAction['soil']);

			$selection['cBed']->select([
				'cPlace' => \series\Place::model()
					->select([
						'id',
						'area', 'length', 'season',
						'task' => ['id', 'plannedWeek', 'doneWeek', 'action' => ['name', 'color', 'fqn']],
						'series' => [
							'name', 'season', 'use', 'mode', 'cycle', 'perennialSeason', 'perennialLifetime', 'status',
							'bedStartCalculated', 'bedStopCalculated',
							'cCultivation' => \series\Cultivation::model()
								->select([
									'id',
									'season',
									'harvested',
									'harvestMonths', 'harvestMonthsExpected',
									'harvestWeeks', 'harvestWeeksExpected',
									'startWeek', 'startAction',
									'plant' => [
										'name', 'vignette', 'fqn', 'color',
										'family' => ['fqn', 'name', 'color']
									]
								])
								->sort(['startWeek' => SORT_ASC])
								->delegateCollection('series'),
							'cTask' => new \series\TaskModel()
								->select([
									'series', 'cultivation',
									'action' => \work\Action::getSelection(),
									'plannedWeek', 'doneWeek',
									'status', 'tools', 'products'
								])
								->delegateCollection('series', callback: function(\Collection $cTask, \series\Series $eSeries) use($cActionStart, $cActionSoil) {
									\series\TaskLib::spreadForSoil($eSeries, $cTask, $cActionStart, $cActionSoil);
									return $cTask;
								})

						]
					])
					->whereSeries('!=', NULL, if: $onlySeries)
					->whereSeason('IN', $withSeries)
					->delegateCollection('bed', callback: function($cPlace) {

						// Tri des séries par saison puis date de première tâche
						$cPlace->setColumn('firstTaskAt', function($ePlace) {

							if(
								$ePlace['series']->empty() or
								$ePlace['series']['cCultivation']->empty()
							) {
								return 0;
							} else {
								$week = $ePlace['series']['cCultivation']->first()['firstTaskWeek'];
								return $week ? strtotime($week) : NULL;
							}

						});

						$cPlace->sort(function($e1, $e2) {

							if($e1['season'] !== $e2['season']) {
								return ($e1['season'] > $e2['season']) ? 1 : -1;
							}

							return ($e1['firstTaskAt'] > $e2['firstTaskAt'] ? 1 : -1);

						});

						return $cPlace;

					})
			]);

		});

		self::putFromZoneWithSeriesStats($value);

	}

	public static function putFromZoneWithSeriesBounds(\Collection|Zone $value, ?int $season = NULL, array $withSeries = []): void {

		self::putFromZone($value, withBeds: TRUE, season: $season, newSelection: function(&$selection) use($withSeries) {

			$selection['cBed']->select([
				'cPlace' => \series\Place::model()
					->select([
						'id',
						'length', 'season',
						'task' => ['id', 'plannedWeek', 'doneWeek'],
						'series' => [
							'name', 'season', 'use', 'cycle', 'perennialSeason', 'perennialLifetime', 'status',
							'bedStartCalculated', 'bedStopCalculated',
						],
					])
					->whereSeries('!=', NULL)
					->whereSeason('IN', $withSeries)
					->delegateCollection('bed')
			]);

		});

	}

	private static function putFromZoneWithSeriesStats(\Collection|Zone $value) {

		$calc = function(Zone $e) {

			$zonePlaces = 0;

			foreach($e['cPlot'] as $ePlot) {

				$plotPlaces = 0;

				foreach($ePlot['cBed'] as $eBed) {
					$plotPlaces += $eBed['cPlace']->count();
				}

				$ePlot['places'] = $plotPlaces;

				$zonePlaces += $plotPlaces;

			}

			$e['places'] = $zonePlaces;

		};

		if($value instanceof \Collection) {
			$value->map($calc);
		} else {
			$calc($value);
		}

	}

	public static function delete(Plot $e): void {

		$e->expects(['id']);

		if(\series\Place::model()
				->wherePlot($e)
				->exists()) {
			Plot::fail('deleteUsed');
			return;
		}

		Plot::model()->beginTransaction();

		DecorLib::deleteByPlot($e);
		DrawLib::deleteByPlot($e);

		Bed::model()
			->wherePlot($e)
			->delete();

		Plot::model()->delete($e);

		Plot::model()->commit();

	}

}
?>
