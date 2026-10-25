<?php
namespace map;

class ZoneLib extends ZoneCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {

		// Le contour donne sa surface et se construit donc avant elle. Une parcelle créée sur la carte l'est en dessinant son contour ;
		// celle du panneau peut se dessiner plus tard, son contour ne part pas et reste vide
		return match($origin) {
			'map' => ['name', 'coordinates', 'area'],
			default => ['name', 'coordinates', 'area', 'seasonFirst']
		};

	}

	/**
	 * En modification, le contour ne se construit que par l'API : build() pose NULL sur une propriété absente de l'entrée, et le
	 * panneau, qui ne le porte pas, l'effacerait à chaque enregistrement. Le web le redessine sur la carte (zone:doUpdateShape).
	 *
	 * Dans le panneau, la surface se saisit tant que la parcelle n'a pas de contour pour la donner : le panneau n'a pas de carte
	 * où le redessiner. La liste d'une page qui ne désigne aucune parcelle (ZonePage) porte la surface.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL, Zone $e = new Zone()): array {

		if($origin === 'api') {
			return ['name', 'coordinates', 'area', 'seasonFirst', 'seasonLast'];
		}

		if($e->notEmpty()) {

			$e->expects(['coordinates']);

			if($e['coordinates'] !== NULL) {
				return ['name', 'seasonFirst', 'seasonLast'];
			}

		}

		return ['name', 'area', 'seasonFirst', 'seasonLast'];

	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, ?int $season = NULL): \Collection|Zone {

		if($id !== NULL) {
			Zone::model()->whereId($id);
		}

		SeasonLib::whereSeason(Zone::model(), $season);

		Zone::model()
			->select(Zone::getSelection())
			->whereStructure($eStructure);

		if($id !== NULL) {
			return Zone::model()->get();
		} else {
			return Zone::model()
				->getCollection()
				->sort('name', natural: TRUE);
		}

	}

	public static function test(\Collection $cZone, \Search $search, \series\Series $eSeries): void {

		$eSeries->expects([
			'use',
			'bedStartCalculated', 'bedStopCalculated'
		]);

		if($eSeries['use'] !== \series\Series::BED) {
			return;
		}

		$rotations = [];
		
		$cFamily = $eSeries['cCultivation']
			->getColumnCollection('plant')
			->getColumnCollection('family');

		if($cFamily->notEmpty()) {

			$rotationLimit = 5;
			$rotationExclude = $eSeries['structure']->conf(Configuration::class)['rotationExclude'];

			$cCultivationRotation = \series\Cultivation::model()
				->select([
					'series',
					'rotation' => new \Sql($eSeries['season'].' - season', 'int')
				])
				->join(\plant\Plant::model(), 'm1.plant = m2.id')
				->where('m1.series', '!=', $eSeries)
				->where('m1.structure', $eSeries['structure'])
				->whereSeason('BETWEEN', new \Sql(($eSeries['season'] - $rotationLimit).' AND '.$eSeries['season']))
				->where('m2.family', 'IN', $cFamily)
				->where('m1.plant', 'NOT IN', $rotationExclude, if: $rotationExclude)
				->getCollection(index: 'series');

			foreach(\series\Place::model()
				->select(['bed', 'series'])
				->whereSeries('IN', $cCultivationRotation->getColumn('series'))
				->getCollection() as $ePlace) {

				$rotations[$ePlace['bed']['id']] = $cCultivationRotation[$ePlace['series']['id']]['rotation'];

			}

		}

		$filterMode = $search->get('mode');

		foreach($cZone as ['cPlot' => $cPlot]) {

			foreach($cPlot as $ePlot) {

				$hasGreenhouse = ($ePlot['mode'] === Plot::GREENHOUSE);

				foreach($ePlot['cBed'] as $eBed) {

					// La surface libre n'a ni largeur, ni rotation, ni occupation à comparer : seul le mode de son jardin la concerne
					if($eBed['plotFill'] or $eBed['zoneFill']) {
						$eBed['test'] = [
							'hasGreenhouse' => $hasGreenhouse
						];
						continue;
					}

					$eBed['test'] = [
						'sameWidth' => ($eSeries['bedWidth'] === $eBed['width']),
						'hasGreenhouse' => $hasGreenhouse,
						'rotation' => $rotations[$eBed['id']] ?? NULL,
						'free' => 0,
						'reason' => NULL,
						'rest' => NULL,
						'blockName' => NULL,
						'blockStopWeek' => NULL,
						'hide' => FALSE,
						'mismatch' => (
							in_array($filterMode, [Plot::GREENHOUSE, Plot::OPEN_FIELD]) and
							$filterMode !== $ePlot['mode']
						)
					];

					if(
						$eSeries['bedStartCalculated'] !== NULL and
						$eSeries['bedStopCalculated'] !== NULL
					) {

						$occupiedLength = 0;
						$blockStopPosition = NULL;
						$blockName = NULL;
						$perennialName = NULL;

						foreach($eBed['cPlace'] as $ePlace) {

							if(
								$ePlace['series']->empty() or
								$ePlace['series']['id'] === $eSeries['id']
							) {
								continue;
							}

							if(
								$ePlace['series']['bedStartCalculated'] === NULL or
								$ePlace['series']['bedStopCalculated'] === NULL
							) {

								if($ePlace['series']['cycle'] === \series\Series::PERENNIAL) {

									$eBed['test']['free'] = NULL;
									$perennialName = $ePlace['series']['name'];

								}

								continue;

							}

							$placeStart = $ePlace['series']['bedStartCalculated'] + ($ePlace['series']['season'] - $eSeries['season']) * 100;
							$placeStop = $ePlace['series']['bedStopCalculated'] + ($ePlace['series']['season'] - $eSeries['season']) * 100;

							$free = min($placeStop - $eSeries['bedStartCalculated'], $eSeries['bedStopCalculated'] - $placeStart);

							if($free <= 2) {

								if($eBed['test']['free'] !== NULL) {
									$eBed['test']['free'] = max($eBed['test']['free'], max($free, 0));
								}

							} else {

								$eBed['test']['free'] = NULL;

								$occupiedLength += ($ePlace['length'] ?? $eBed['length']);

								if(
									$blockStopPosition === NULL or
									$placeStop > $blockStopPosition
								) {
									$blockStopPosition = $placeStop;
									$blockName = $ePlace['series']['name'];
								}

							}

						}

						if($perennialName !== NULL) {

							$eBed['test']['reason'] = 'perennial';
							$eBed['test']['blockName'] = $perennialName;

						} else if($eBed['test']['free'] === NULL) {

							$rest = max(0, ($eBed['length'] ?? 0) - $occupiedLength);

							if($rest > 0) {

								$eBed['test']['reason'] = 'rest';
								$eBed['test']['rest'] = $rest;

							} else if(
								$blockStopPosition !== NULL and
								$blockStopPosition < $eSeries['bedStopCalculated']
							) {

								$eBed['test']['reason'] = 'freeAfter';
								$eBed['test']['blockName'] = $blockName;
								$eBed['test']['blockStopWeek'] = (($blockStopPosition % 100) + 100) % 100;

							} else {
								$eBed['test']['reason'] = 'occupied';
							}

						} else if($eBed['test']['free'] > 0) {
							$eBed['test']['reason'] = 'almost';
						} else {
							$eBed['test']['reason'] = 'free';
						}

					}

				}

			}

		}

	}

	public static function create(Zone $e): void {

		Zone::model()->beginTransaction();

		Zone::model()->insert($e);

		// On crée le jardin inféodé à la parcelle
		PlotLib::createFromZone($e);

		Zone::model()->commit();

	}

	public static function updateShape(Zone $e): void {

		Zone::model()->beginTransaction();

		// L'ancien contour se lit sous verrou, première lecture de la transaction : un second mouvement simultané attend, et part du
		// contour que le premier a écrit, donc de jardins et de traits déjà déplacés une fois (PlotLib::update() fait de même)
		$coordinatesBefore = Zone::model()
			->select('coordinates')
			->whereId($e)
			->getForUpdate()['coordinates'];

		$motion = MapLib::getMotion($coordinatesBefore, $e['coordinates']);

		self::update($e, ['area', 'coordinates']);

		if($motion !== NULL) {
			PlotLib::moveByZone($e, $motion);
			DrawLib::moveByZone($e, $motion);
		}

		Zone::model()->commit();

	}

	public static function update(Zone $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		Zone::model()->beginTransaction();

		Zone::model()
			->select($properties)
			->update($e);

		if(in_array('area', $properties)) {

			Plot::model()
				->whereZoneFill(TRUE)
				->whereZone($e)
				->update([
					'area' => $e['area']
				]);

			Bed::model()
				->whereZoneFill(TRUE)
				->whereZone($e)
				->update([
					'area' => $e['area']
				]);

		}

		if(in_array('seasonFirst', $properties)) {

			Plot::model()
				->whereZone($e)
				->whereZoneFill(TRUE)
				->update([
					'seasonFirst' => $e['seasonFirst']
				]);

			Bed::model()
				->whereZone($e)
				->whereZoneFill(TRUE)
				->update([
					'seasonFirst' => $e['seasonFirst']
				]);

			if($e['seasonFirst'] !== NULL) {

				Plot::model()
					->whereZone($e)
					->where('seasonFirst <'.$e['seasonFirst'])
					->update([
						'seasonFirst' => $e['seasonFirst']
					]);

				Bed::model()
					->whereZone($e)
					->where('seasonFirst <'.$e['seasonFirst'])
					->whereStatus(Bed::ACTIVE)
					->update([
						'seasonFirst' => $e['seasonFirst']
					]);

			}

		}

		if(in_array('seasonLast', $properties)) {

			Plot::model()
				->whereZone($e)
				->whereZoneFill(TRUE)
				->update([
					'seasonLast' => $e['seasonLast']
				]);

			Bed::model()
				->whereZone($e)
				->whereZoneFill(TRUE)
				->update([
					'seasonLast' => $e['seasonLast']
				]);

			if($e['seasonLast'] !== NULL) {

				Plot::model()
					->whereZone($e)
					->where('seasonLast >'.$e['seasonLast'])
					->update([
						'seasonLast' => $e['seasonLast']
					]);

				Bed::model()
					->whereZone($e)
					->where('seasonLast >'.$e['seasonLast'])
					->whereStatus(Bed::ACTIVE)
					->update([
						'seasonLast' => $e['seasonLast']
					]);

			}

		}

		Zone::model()->commit();

	}

	public static function delete(Zone $e): void {

		$e->expects(['id']);

		if(\series\Place::model()
				->whereZone($e)
				->exists()) {
			Zone::fail('deleteUsed');
			return;
		}

		Zone::model()->beginTransaction();

		DecorLib::deleteByZone($e);
		DrawLib::deleteByZone($e);

		Plot::model()
			->whereZone($e)
			->delete();

		Bed::model()
			->whereZone($e)
			->delete();

		Zone::model()->delete($e);

		Zone::model()->commit();

	}

}
?>
