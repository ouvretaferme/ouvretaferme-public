<?php
namespace series;

class PlaceLib extends PlaceCrud {

	const HELPER_EDITING = 'placeEditing';

	public static function existsBySeries(\Collection $c): bool {

		return Place::model()
			->whereSeries('IN', $c)
			->count() > 0;

	}

	public static function getByElement(Series|Task $e): \Collection {

		$cPlace = Place::model()
			->select([
				'id',
				'zone' => ['name'],
				'plot' => ['name', 'mode'],
				'bed' => [
					'name', 'length', 'area', 'width', 'alleyWidth',
					'plotFill',
					'status'
				],
				'length', 'width', 'area'
			])
			->whereSeries($e, if: $e instanceof Series)
			->whereTask($e, if: $e instanceof Task)
			->getCollection(NULL, NULL, 'bed');

		$cPlace->sort([
			'zone' => ['name'],
			'plot' => ['name'],
			'bed' => ['name']
		], natural: TRUE);

		return $cPlace;

	}

	public static function getBedFormats(Series $eSeries, \Collection $cPlace): array {

		$eSeries->expects(['use', 'bedWidth', 'alleyWidth', 'length', 'lengthTarget']);

		if($eSeries['use'] !== Series::BED) {
			return [];
		}

		$formats = [];

		foreach($cPlace as $ePlace) {

			$eBed = $ePlace['bed'];

			if($eBed['plotFill'] or $eBed['width'] === NULL) {
				continue;
			}

			$alleyWidth = $eBed['alleyWidth'] ?? $eSeries['alleyWidth'] ?? 0;
			$key = $eBed['width'].'-'.$alleyWidth;

			$formats[$key] ??= [
				'bedWidth' => $eBed['width'],
				'alleyWidth' => $alleyWidth,
				'beds' => 0,
				'length' => 0
			];

			$formats[$key]['beds']++;
			$formats[$key]['length'] += $ePlace['length'] ?? $eBed['length'] ?? 0;

		}

		if($formats === []) {

			if($eSeries['bedWidth'] === NULL) {
				return [];
			}

			return [[
				'bedWidth' => $eSeries['bedWidth'],
				'alleyWidth' => $eSeries['alleyWidth'] ?? 0,
				'beds' => NULL,
				'length' => $eSeries['length'] ?? $eSeries['lengthTarget']
			]];

		}

		usort($formats, fn(array $format1, array $format2) => [$format2['bedWidth'], $format2['alleyWidth']] <=> [$format1['bedWidth'], $format1['alleyWidth']]);

		return $formats;

	}

	public static function getSearchMode(Series|Task $e): ?string {

		if($e instanceof Task or $e->empty()) {
			return NULL;
		}

		$e->expects(['mode']);

		$mode = $e['mode'];

		if(in_array($mode, [\map\Plot::GREENHOUSE, \map\Plot::OPEN_FIELD], TRUE) === FALSE) {
			return NULL;
		}

		$cPlace = Place::model()
			->select(['plot' => ['mode']])
			->whereSeries($e)
			->getCollection();

		if($cPlace->empty()) {
			return $mode;
		}

		foreach($cPlace as $ePlace) {

			if($ePlace['plot']['mode'] !== $mode) {
				return NULL;
			}

		}

		return $mode;

	}

	public static function delegateByTask(): PlaceModel {

		return new PlaceModel()
			->select([
				'task',
				'zone' => ['name'],
				'plot' => ['name', 'zoneFill'],
				'bed' => ['name', 'plotFill']
			])
			->sort(['plot' => SORT_ASC, 'bed' => SORT_ASC])
			->delegateCollection('task', ['zone', 'plot', NULL]);

	}

	public static function delegateBySeries(): PlaceModel {

		return new PlaceModel()
			->select([
				'series',
				'zone' => ['name'],
				'plot' => ['name', 'zoneFill'],
				'bed' => ['name', 'plotFill']
			])
			->sort(['plot' => SORT_ASC, 'bed' => SORT_ASC])
			->delegateCollection('series', ['zone', 'plot', NULL]);

	}

	public static function buildFromBeds(string $source, Series|Task $e, array $beds, array $sizes): \Collection {

		$e->expects(['structure', 'season', 'use']);

		if($beds === []) {
			return new \Collection();
		}

		$cBed = \map\Bed::model()
			->select([
				'id',
				'plot', 'plotFill', 'zone', 'zoneFill',
				'length', 'width', 'area'
			])
			->whereId('IN', $beds)
			->whereStructure($e['structure'])
			->whereStatus(\map\Bed::ACTIVE)
			->getCollection();

		if($cBed->count() !== count($beds)) {
			Place::fail('bedsCheck');
			return new \Collection();
		}

		$cPlace = new \Collection();

		foreach($cBed as $eBed) {

			$ePlace = new Place([
				'structure' => $e['structure'],
				'season' => $e['season'],
				$source => $e,
				'bed' => $eBed,
				'plot' => $eBed['plot'],
				'zone' => $eBed['zone'],
				'length' => NULL,
				'width' => NULL,
				'area' => NULL
			]);

			switch($source) {

				case 'series' :

					$size = (int)($sizes[$eBed['id']] ?? 0);

					if($size === 0) {
						continue 2;
					}

					switch($e['use']) {

						case Series::BED :

							if($size < 0) {
								Place::fail('bedsSize');
								return new \Collection();
							}

							if($eBed['length'] !== NULL and $size > $eBed['length']) {
								Place::fail('bedsExceeded');
								return new \Collection();
							}

							$ePlace['length'] = $size;
							$ePlace['width'] = $eBed['width'] /* Planche permanente */ ?? $e['bedWidth'] /* Planche temporaire */;

							$ePlace['area'] = $size * ($ePlace['width'] + ($e['alleyWidth'] ?? 0)) / 100;

							break;

						case Series::BLOCK :

							if($size <= 0 or $size > $eBed['area']) {
								Place::fail('bedsSize');
								return new \Collection();
							}

							$ePlace['area'] = $size;

							break;

					}

					break;

				case 'task' :

					$ePlace['length'] = NULL;
					$ePlace['width'] = NULL;
					$ePlace['area'] = NULL;

					break;

			}

			$cPlace[] = $ePlace;

		}

		return $cPlace;

	}

	public static function recalculateAreaBySeries(Series $eSeries): \Collection {

		$eSeries->expects(['id', 'alleyWidth']);

		$cPlace = self::getByElement($eSeries);

		$fullArea = NULL;
		$fullAreaPermanent = NULL;

		foreach($cPlace as $ePlace) {


			switch($eSeries['use']) {

				case Series::BED :

					$ePlace['area'] = $ePlace['length'] * ($ePlace['width'] + ($eSeries['alleyWidth'] ?? 0)) / 100;

					Place::model()
						->select('area')
						->update($ePlace);

					if($ePlace['bed']['width'] !== NULL) {
						$fullAreaPermanent ??= 0;
						$fullAreaPermanent += $ePlace['area'];
					}

					break;

			}

			$fullArea ??= 0;
			$fullArea += $ePlace['area'];

		}

		$eSeries['area'] = $fullArea;
		$eSeries['areaPermanent'] = $fullAreaPermanent;

		Series::model()
			->select(['area', 'areaPermanent'])
			->update($eSeries);

		Cultivation::model()
			->whereSeries($eSeries)
			->update([
				'area' => $eSeries['area'],
				'areaPermanent' => $eSeries['areaPermanent'],
			]);

		return $cPlace;

	}

	public static function replaceForSeries(Series $eSeries, \Collection $cPlace): void {

		Series::model()->beginTransaction();

		self::lock($eSeries);

		self::recalculateMetadata($eSeries, $cPlace);

		Place::model()
			->whereSeries($eSeries)
			->delete();

		self::updateMetadata($eSeries);

		try {

			if($cPlace->notEmpty()) {
				Place::model()->insert($cPlace);
			}

		} catch(\DuplicateException) {

			Place::fail('bedsDuplicate');

			Series::model()->rollBack();
			return;

		}

		Series::model()->commit();

	}

	public static function replaceForTask(Task $eTask, \Collection $cPlace): void {

		Series::model()->beginTransaction();

		self::lock($eTask);

		Place::model()
			->whereTask($eTask)
			->delete();

		try {
			Place::model()->insert($cPlace);
		} catch(\DuplicateException) {

			Place::fail('bedsDuplicate');

			Series::model()->rollBack();
			return;

		}

		Series::model()->commit();

	}

	/**
	 * Toute écriture des emplacements commence par verrouiller la ligne de la série ou de
	 * l'intervention, avant de lire ceux qui existent. Deux écritures simultanées sur la même série
	 * (deux onglets, deux membres de l'équipe) se croisaient sinon en interblocage : sur une série
	 * encore sans emplacement, le DELETE ne pose que des verrous d'intervalle, compatibles entre eux,
	 * et chacune attendait ensuite l'autre, à l'INSERT ou à l'UPDATE de la série. Le verrou les met
	 * en file, et la seconde relit ce que la première vient d'écrire au lieu de l'écraser.
	 */
	protected static function lock(Series|Task $e): void {

		if($e instanceof Task) {
			Task::model()
				->whereId($e)
				->getForUpdate();
		} else {
			Series::model()
				->whereId($e)
				->getForUpdate();
		}

	}

	// Les gestes qui lisent les emplacements existants avant de les remplacer : la lecture se fait
	// sous le verrou, sinon elle peut précéder l'écriture d'un geste simultané
	protected static function writeLocked(Series|Task $e, \Closure $write): void {

		Series::model()->beginTransaction();

		self::lock($e);

		$write();

		Series::model()->commit();

	}

	public static function recalculateMetadata(Series $eSeries, \Collection $cPlace): void {

		$fullArea = NULL;
		$fullAreaPermanent = NULL;

		$fullLength = NULL;
		$fullLengthPermanent = NULL;

		foreach($cPlace as $ePlace) {

			$eBed = $ePlace['bed'];

			switch($eSeries['use']) {

				case Series::BED :

					$fullLength ??= 0;
					$fullLength += $ePlace['length'];

					$fullArea ??= 0;
					$fullArea += $ePlace['area'];

					if($eBed['width'] !== NULL) {

						$fullLengthPermanent ??= 0;
						$fullLengthPermanent += $ePlace['length'];

						$fullAreaPermanent ??= 0;
						$fullAreaPermanent += $ePlace['area'];

					}

					break;

				case Series::BLOCK :

					$fullArea ??= 0;
					$fullArea += $ePlace['area'];

					break;

			}

		}

		$eSeries['area'] = $fullArea;
		$eSeries['areaPermanent'] = $fullAreaPermanent;

		$eSeries['length'] = $fullLength;
		$eSeries['lengthPermanent'] = $fullLengthPermanent;

	}

	public static function updateMetadata(Series $eSeries): void {

		Series::model()
			->select(['area', 'areaPermanent', 'length', 'lengthPermanent'])
			->update($eSeries);

		Cultivation::model()
			->whereSeries($eSeries)
			->update([
				'area' => $eSeries['area'],
				'areaPermanent' => $eSeries['areaPermanent'],
				'length' => $eSeries['length'],
				'lengthPermanent' => $eSeries['lengthPermanent']
			]);

	}

	public static function getForRotations(\structure\Structure $eStructure, \Collection $cBed, array $seasons) {

		$rotationExclude = $eStructure->conf(\map\Configuration::class)['rotationExclude'];

		return Place::model()
			->select([
				'season',
				'bed' => ['length', 'area']
			])
			->join(Cultivation::model()->select([
				'plant' => ['name', 'vignette', 'family']
			]), 'm1.series = m2.series', type: 'LEFT')
			->join(\plant\Plant::model()->select([
				'family' => ['name', 'fqn']
			]), 'm2.plant = m3.id', type: 'LEFT')
			->where('m1.season', 'IN', $seasons)
			->where('m1.bed', 'IN', $cBed)
			->where('m2.plant', 'NOT IN', $rotationExclude, if: $rotationExclude)
			->group(['m1.season', 'family', 'bed', 'plant'])
			->getCollection(index: ['season', 'family', 'bed', NULL]);


	}

	public static function getRotationsStats(\Collection $ccccPlace): array {

		$stats = [];

		foreach($ccccPlace as $season => $cccPlace) {

			foreach($cccPlace as $family => $ccPlace) {

				foreach($ccPlace as $bed => $cPlace) {

					$stats[$family]['season'][$season] ??= [];
					$stats[$family]['season'][$season][$bed] = [
						'bed' => $cPlace->first()['bed'],
						'plants' => $cPlace->getColumnCollection('plant')
					];

					$stats[$family]['bed'][$bed] ??= 0;
					$stats[$family]['bed'][$bed]++;

				}

			}

		}

		return $stats;


	}

	public static function filterRotationsByFamily(\structure\Structure $eStructure, \Collection $cZone, \Search $search): void {

		$eFamily = $search->get('family');

		if(
			$eFamily->empty(['cFamily']) and
			$search->get('bed') === FALSE
		) {
			return;
		}

		$seenSearch = $search->get('seen') ?? 1;
		$seasonSearch = NULL;

		if($seenSearch > 1000) {
			$seasonSearch = $seenSearch;
			$seenSearch = NULL;
		}

		$onlyBed = $search->get('bed');

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				$delete = [];

				foreach($ePlot['cBed'] as $bed => $eBed) {

					if(
						$onlyBed and
						($eBed['plotFill'] or $eBed['zoneFill'])
					) {
						$delete[] = $bed;
						continue;
					}

					if($eFamily->notEmpty()) {

						$seasons = [];

						foreach($eBed['cPlace'] as $ePlace) {

							$cCultivation = $ePlace['series']['cCultivation'];

							foreach($cCultivation as $eCultivation) {

								if(
									in_array($eCultivation['plant']['id'], $eStructure->conf(\map\Configuration::class)['rotationExclude']) === FALSE and
									$eCultivation['plant']['family']->notEmpty() and
									$eCultivation['plant']['family']['id'] === $eFamily['id']
								) {
									$seasons[] = $eCultivation['season'];
								}

							}

						}

						$seasons = array_unique($seasons);
						sort($seasons);

						// Famille pas trouvée
						if($seasons === []) {

							// Famille recherchée, on la supprime
							if($seenSearch > 0) {
								$delete[] = $bed;
							}

						}
						// Famille trouvée
						else {

							// Famille pas recherche, on la supprime
							if($seenSearch === 0 and $seasonSearch === NULL) {
								$delete[] = $bed;
							}
							// Famille cherchée N fois et pas trouvée N fois
							else if($seenSearch !== NULL and count($seasons) !== $seenSearch) {
								$delete[] = $bed;
							}
							// Famille cherchée en saison N et pas trouvée
							else if($seasonSearch !== NULL and max($seasons) >= $seasonSearch) {
								$delete[] = $bed;
							}

						}

					}

				}


				foreach($delete as $bed) {
					$ePlot['cBed']->offsetUnset($bed);
				}

			}

		}


	}

	public static function addBeds(Series|Task $e, array $beds, ?int $size = NULL): void {

		self::writeLocked($e, fn() => self::changeBeds($e, add: $beds, sizes: self::spreadSize($beds, $size)));

	}

	public static function removeBeds(Series|Task $e, array $beds): void {

		self::writeLocked($e, fn() => self::changeBeds($e, remove: $beds));

	}

	public static function moveBeds(Series $eSeries, array $from, array $to, ?int $size = NULL): void {

		self::writeLocked($eSeries, function() use($eSeries, $from, $to, $size) {

			if($size !== NULL) {
				self::changeBeds($eSeries, add: $to, remove: $from, sizes: self::spreadSize($to, $size));
				return;
			}

			$current = self::getSizes($eSeries);
			$sizes = [];

			foreach($to as $rank => $bedId) {

				$source = $from[$rank] ?? NULL;

				if($source !== NULL and isset($current[(int)$source])) {
					$sizes[(int)$bedId] = $current[(int)$source];
				}

			}

			self::changeBeds($eSeries, add: $to, remove: $from, sizes: $sizes);

		});

	}

	protected static function spreadSize(array $beds, ?int $size): array {

		if($size === NULL) {
			return [];
		}

		return array_fill_keys(array_map('intval', $beds), $size);

	}

	public static function clearSeries(Series $eSeries): void {

		self::deleteBySeries($eSeries);

		self::recalculateMetadata($eSeries, new \Collection());
		self::updateMetadata($eSeries);

	}

	public static function moveMany(\structure\Structure $eStructure, array $moves): void {

		foreach($moves as $move) {

			$eSeries = SeriesLib::getById($move['series'] ?? 0);

			if(
				$eSeries->empty() or
				$eSeries['structure']['id'] !== $eStructure['id']
			) {
				Place::fail('bedsCheck');
				return;
			}

			self::moveBeds($eSeries, $move['from'] ?? [], $move['to'] ?? []);

		}

	}

	protected static function changeBeds(Series|Task $eSeries, array $add = [], array $remove = [], array $sizes = []): void {

		$wanted = $sizes;

		$sizes = self::getSizes($eSeries);

		foreach($remove as $bedId) {
			unset($sizes[(int)$bedId]);
		}

		if($add !== []) {

			$cBed = \map\Bed::model()
				->select(['id', 'length', 'area'])
				->whereId('IN', $add)
				->whereStructure($eSeries['structure'])
				->getCollection(index: 'id');

			foreach($add as $bedId) {

				$bedId = (int)$bedId;

				if($cBed->offsetExists($bedId) === FALSE) {
					Place::fail('bedsCheck');
					return;
				}

				$size = $wanted[$bedId] ?? NULL;

				$sizes[$bedId] ??= match($eSeries['use']) {
					Series::BED => ($size !== NULL) ? min($size, $cBed[$bedId]['length'] ?? $size) : self::getFreeLength($eSeries, $cBed[$bedId]),
					Series::BLOCK => ($size !== NULL) ? min($size, $cBed[$bedId]['area'] ?? $size) : $cBed[$bedId]['area'],
				};

			}

		}

		// Vider une série est un geste légitime : on doit toujours pouvoir la
		// désassoler entièrement. Seul un ajout sans planche valable est un échec.
		if($sizes === []) {

			if($add !== []) {
				Place::fail('bedsCheck');
				return;
			}

			if($eSeries instanceof Task) {
				self::replaceForTask($eSeries, new \Collection());
			} else {
				self::replaceForSeries($eSeries, new \Collection());
			}

			return;

		}

		$source = ($eSeries instanceof Task) ? 'task' : 'series';

		$cPlaceChanged = self::buildFromBeds($source, $eSeries, array_keys($sizes), $sizes);

		if($cPlaceChanged->empty()) {
			return;
		}

		match($source) {
			'series' => self::replaceForSeries($eSeries, $cPlaceChanged),
			'task' => self::replaceForTask($eSeries, $cPlaceChanged),
		};

	}

	public static function getFreeLength(Series|Task $eSeries, \map\Bed $eBed): int {

		$length = (int)($eBed['length'] ?? 0);

		if(
			$length === 0 or
			$eSeries instanceof Task or
			$eSeries['bedStartCalculated'] === NULL or
			$eSeries['bedStopCalculated'] === NULL
		) {
			return $length;
		}

		$cPlace = Place::model()
			->select([
				'length',
				'series' => ['season', 'bedStartCalculated', 'bedStopCalculated']
			])
			->whereBed($eBed)
			->where('series IS NOT NULL')
			->getCollection();

		$occupied = 0;

		foreach($cPlace as $ePlace) {

			$eSeriesOther = $ePlace['series'];

			if(
				$eSeriesOther->empty() or
				$eSeriesOther->is($eSeries) or
				$eSeriesOther['bedStartCalculated'] === NULL or
				$eSeriesOther['bedStopCalculated'] === NULL
			) {
				continue;
			}

			$offset = ($eSeriesOther['season'] - $eSeries['season']) * 100;

			$overlap = min(
				$eSeriesOther['bedStopCalculated'] + $offset - $eSeries['bedStartCalculated'],
				$eSeries['bedStopCalculated'] - $eSeriesOther['bedStartCalculated'] - $offset
			);

			if($overlap > 2) {
				$occupied += (int)($ePlace['length'] ?? $length);
			}

		}

		$rest = $length - $occupied;

		return ($rest > 0) ? $rest : $length;

	}

	public static function setSize(Series $eSeries, \map\Bed $eBed, int $size): void {

		if($size <= 0) {
			Place::fail('bedsSize');
			return;
		}

		self::writeLocked($eSeries, function() use($eSeries, $eBed, $size) {

			$sizes = self::getSizes($eSeries);

			if(array_key_exists($eBed['id'], $sizes) === FALSE) {
				Place::fail('bedsCheck');
				return;
			}

			$sizes[$eBed['id']] = $size;

			$cPlace = self::buildFromBeds('series', $eSeries, array_keys($sizes), $sizes);

			if($cPlace->empty()) {
				return;
			}

			self::replaceForSeries($eSeries, $cPlace);

		});

	}

	public static function emptyBed(\map\Bed $eBed, int $season): \Collection {

		$cSeries = self::getSeriesByBed($eBed, $season);

		foreach($cSeries as $eSeriesBed) {

			if(self::isLocked($eSeriesBed, $season)) {
				continue;
			}

			self::removeBeds($eSeriesBed, [$eBed['id']]);

		}

		return $cSeries;

	}

	public static function isLocked(Series $eSeries, int $season): bool {

		return (
			$eSeries['status'] === Series::CLOSED or
			$eSeries['cycle'] === Series::PERENNIAL or
			$eSeries['season'] !== $season
		);

	}

	public static function getSeriesByBed(\map\Bed $eBed, int $season): \Collection {

		$cPlace = Place::model()
			->select([
				'length', 'area',
				'series' => Series::getSelection()
			])
			->whereBed($eBed)
			->whereSeason($season)
			->where('series IS NOT NULL')
			->getCollection();

		$cSeries = new \Collection();

		foreach($cPlace as $ePlace) {

			if($ePlace['series']->empty()) {
				continue;
			}

			$ePlace['series']['placeLength'] = $ePlace['length'];
			$ePlace['series']['placeArea'] = $ePlace['area'];

			$cSeries[$ePlace['series']['id']] = $ePlace['series'];

		}

		// Les séries sans début calculé sont à égalité : l'identifiant les départage, la lecture des places n'ayant pas d'ordre
		$cSeries->sort(['bedStartCalculated' => SORT_ASC, 'id' => SORT_ASC]);

		return $cSeries;

	}

	public static function getFreeGapByBed(\Collection $cSeries): ?array {

		$busy = array_fill(1, 52, FALSE);

		foreach($cSeries as $eSeries) {

			if(
				$eSeries['bedStartCalculated'] === NULL or
				$eSeries['bedStopCalculated'] === NULL
			) {

				if($eSeries['cycle'] === Series::PERENNIAL) {
					return NULL;
				}

				continue;

			}

			$start = max(1, $eSeries['bedStartCalculated']);
			$stop = min(52, $eSeries['bedStopCalculated']);

			for($week = $start; $week <= $stop; $week++) {
				$busy[$week] = TRUE;
			}

		}

		$best = NULL;
		$start = NULL;

		for($week = 1; $week <= 53; $week++) {

			if($week <= 52 and $busy[$week] === FALSE) {

				$start ??= $week;
				continue;

			}

			if($start !== NULL) {

				$length = $week - $start;

				if($best === NULL or $length > $best[2]) {
					$best = [$start, $week - 1, $length];
				}

				$start = NULL;

			}

		}

		if($best === NULL or $best[2] < 2) {
			return NULL;
		}

		return [$best[0], $best[1]];

	}

	public static function swapBeds(\map\Bed $eBed, \map\Bed $eBedSwap, int $season): void {

		if(
			$eBed['id'] === $eBedSwap['id'] or
			$eBed['structure']['id'] !== $eBedSwap['structure']['id'] or
			$eBed['plotFill'] or
			$eBedSwap['plotFill']
		) {
			Place::fail('bedsCheck');
			return;
		}

		\map\BedLib::swapSeries($season, $eBed, $eBedSwap);

	}

	public static function getRotationsByBed(\map\Bed $eBed, int $season, int $seasons = 3): array {

		$past = range($season - $seasons, $season - 1);

		$cPlace = Place::model()
			->select(['season', 'series'])
			->whereBed($eBed)
			->where('season', 'IN', $past)
			->where('series IS NOT NULL')
			->getCollection();

		$rotations = array_fill_keys($past, []);

		if($cPlace->empty()) {
			return array_reverse($rotations, TRUE);
		}

		$cCultivation = Cultivation::model()
			->select([
				'series',
				'plant' => ['family' => ['name']]
			])
			->where('series', 'IN', $cPlace->getColumnCollection('series'))
			->getCollection();

		$families = [];

		foreach($cCultivation as $eCultivation) {

			$eFamily = $eCultivation['plant']['family'];

			if($eFamily->notEmpty()) {
				$families[$eCultivation['series']['id']][$eFamily['id']] = $eFamily['name'];
			}

		}

		foreach($cPlace as $ePlace) {

			$seriesId = $ePlace['series']['id'];

			foreach(($families[$seriesId] ?? []) as $familyId => $name) {
				$rotations[$ePlace['season']][$familyId] = $name;
			}

		}

		return array_reverse($rotations, TRUE);

	}

	protected static function getSizes(Series|Task $eSeries): array {

		$sizes = [];

		foreach(self::getByElement($eSeries) as $ePlace) {

			$eBed = $ePlace['bed'];

			$sizes[$eBed['id']] = match($eSeries['use']) {
				Series::BED => ($eBed['length'] === NULL) ? $ePlace['length'] : min($ePlace['length'], $eBed['length']),
				Series::BLOCK => ($eBed['area'] === NULL) ? $ePlace['area'] : min($ePlace['area'], $eBed['area']),
			};

		}

		if($sizes === []) {
			return [];
		}

		$cBed = \map\Bed::model()
			->select(['id'])
			->whereId('IN', array_keys($sizes))
			->whereStructure($eSeries['structure'])
			->getCollection(index: 'id');

		return array_intersect_key($sizes, $cBed->getArrayCopy());

	}

	/**
	 * Les filtres de planches tels que le navigateur les tient. Ils voyagent avec
	 * chaque requête, comme le dégroupage : sans cela le moindre aller-retour —
	 * cliquer sur Assoler, élargir une planche — rendait une fiche aux filtres
	 * remis à zéro pendant que le plan, lui, restait filtré.
	 *
	 * @return array<string, mixed>
	 */
	public static function getFilterInput(): array {

		return [
			// Le client envoie toujours ce filtre, y compris à zéro, pour que « Toutes »
			// se distingue de l'absence.
			'width' => input_exists('width') ? (int)INPUT('width', 'int') : 0,
			'available' => (int)INPUT('free', 'int'),
			'rotation' => (int)INPUT('rotation', 'int'),
		];

	}

	public static function putPlanContext(\stdClass $data, bool $onlySeriesCards = FALSE): void {


		$eStructure = $data->e['structure'];
		$season = $data->e['season'];

		$eContext = $data->eContext ?? new Series();

		if($eContext->notEmpty()) {
			$eContext = SeriesLib::getById($eContext['id']);
			$data->eContext = $eContext;
		} else if($data->e instanceof Task) {
			// Le panneau d'une intervention n'a pas de bac d'où reprendre le contexte :
			// ce qu'on assole est l'élément lui-même. Sans ce report, le plan revenait
			// désarmé après chaque geste, sans un seul bouton Ajouter.
			$eContext = $data->e;
			$data->eContext = $eContext;
		}

		\work\ActionLib::getMainByStructure($eStructure);

		if($eContext->notEmpty()) {

			if($eContext instanceof Series) {
				SeriesLib::fillTimeline($eContext);
			}

			$eContext['cPlace'] = self::getByElement($eContext);

		}

		$data->cZone = \map\ZoneLib::getByStructure($eStructure, season: $season);

		\map\PlotLib::putFromZoneWithSeries($eStructure, $data->cZone, $season, [$season, $season - 1, $season + 1], onlySeries: ($data->source !== 'task'));

		CultivationLib::putSearchVocabulary($eStructure, $data->cZone);

		$data->search = new \Search([
			'canWidth' => \map\BedLib::countWidthsByStructure($eStructure, $season) > 1,
			'mode' => POST('mode', [NULL, \map\Plot::GREENHOUSE, \map\Plot::OPEN_FIELD]) ?? self::getSearchMode($eContext),
		] + self::getFilterInput());

		if($eContext->notEmpty() and $eContext instanceof Series) {
			\map\ZoneLib::test($data->cZone, $data->search, $eContext);
		}

		$eSeriesCards = ($onlySeriesCards and $data->e instanceof Series) ? $data->e : new Series();

		$data->ccCultivation = CultivationLib::getForSelector($eStructure, $season, $eSeriesCards);


	}

	public static function deleteBySeries(Series $e): void {

		Place::model()
			->whereSeries($e)
			->delete();

	}

}
?>