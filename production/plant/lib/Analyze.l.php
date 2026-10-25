<?php
namespace plant;

class AnalyzeLib {

	/**
	 * Répartition plein champ / sous abri des planches d'une saison.
	 *
	 * Volontairement hors de getArea() qui, lui, couvre tout l'historique : un jardin porte son
	 * abri dans map\Plot.mode, qui n'est pas saisonné. La répartition ne décrit donc que l'état
	 * courant et la présenter saison par saison laisserait croire à une évolution qu'on ne sait
	 * pas reconstituer.
	 */
	public static function getAreaByGreenhouse(\structure\Structure $eStructure, int $season): array {

		$area = \map\Bed::model()
			->select([
				'area' => new \Sql('SUM(m1.area)', 'int'),
				'areaGreenhouse' => new \Sql('SUM(IF(m2.mode = "'.\map\Plot::GREENHOUSE.'", m1.area, 0))', 'int')
			])
			->join(\map\Plot::model(), 'm1.plot = m2.id')
			->or(
				fn() => $this->where('m1.seasonFirst', NULL),
				fn() => $this->where('m1.seasonFirst', '<=', $season),
			)
			->or(
				fn() => $this->where('m1.seasonLast', NULL),
				fn() => $this->where('m1.seasonLast', '>=', $season),
			)
			->or(
				fn() => $this->where('m2.seasonFirst', NULL),
				fn() => $this->where('m2.seasonFirst', '<=', $season),
			)
			->or(
				fn() => $this->where('m2.seasonLast', NULL),
				fn() => $this->where('m2.seasonLast', '>=', $season),
			)
			->where('m1.structure', $eStructure)
			->where('m1.zoneFill', FALSE)
			->where('m1.plotFill', FALSE)
			->where('m1.status', \map\Bed::ACTIVE)
			->get();

		$total = $area['area'] ?? 0;
		$greenhouse = $area['areaGreenhouse'] ?? 0;

		return [
			'area' => $total,
			'areaGreenhouse' => $greenhouse,
			'areaField' => $total - $greenhouse
		];

	}

	public static function getArea(\structure\Structure $eStructure, array $seasons): array {

		$summary = [];

		$defaultBeds = [
			'beds' => 0,
			'bedsAnnual' => 0,
			'bedsPerennial' => 0,
			'bedsUnused' => 0,
			'bedsList' => '',
			'bedsArea' => 0,
			'bedsAreaAnnual' => 0,
			'bedsAreaPerennial' => 0,
			'bedsAreaDevelopedAnnual' => 0,
			'bedsAreaDevelopedPerennial' => 0,
			'bedsAreaRateAnnual' => 0,
			'bedsAreaRatePerennial' => 0,
			'bedsPlants' => 0,
		];

		foreach($seasons as $season) {

			$summary[$season] = [];

			$cPlot = \map\Plot::model()
				->select('id')
				->or(
					fn() => $this->whereSeasonFirst(NULL),
					fn() => $this->whereSeasonFirst('<=', $season),
				)
				->or(
					fn() => $this->whereSeasonLast(NULL),
					fn() => $this->whereSeasonLast('>=', $season),
				)
				->whereStructure($eStructure)
				->getCollection();

			if($cPlot->empty()) {
				$summary[$season] = $defaultBeds;
			} else {

				\map\Bed::model()->pdo()->exec('SET SESSION group_concat_max_len = 1000000;');

				$beds = \map\Bed::model()
					->select([
						'beds' => new \Sql('COUNT(*)', 'int'),
						'bedsList' => new \Sql('GROUP_CONCAT("\"", m1.id,"\":", m1.area, "")'),
						'bedsArea' => new \Sql('SUM(m1.area)', 'int')
					])
					->or(
						fn() => $this->where('m1.seasonFirst', NULL),
						fn() => $this->where('m1.seasonFirst', '<=', $season),
					)
					->or(
						fn() => $this->where('m1.seasonLast', NULL),
						fn() => $this->where('m1.seasonLast', '>=', $season),
					)
					->where('m1.plot', 'IN', $cPlot)
					->where('m1.structure', $eStructure)
					->where('m1.zoneFill', FALSE)
					->wherePlotFill(FALSE)
					->whereStatus(\map\Bed::ACTIVE)
					->get()
					->getArrayCopy();

				$summary[$season] += ($beds['beds'] === 0 ? [] : $beds) + $defaultBeds;

				foreach(\series\Place::model()
					->select([
						'beds' => new \Sql('COUNT(*)'),
						'plants' => new \Sql('COUNT(DISTINCT m3.plant)', 'int'),
						'bedsList' => new \Sql('GROUP_CONCAT(DISTINCT m1.bed)'),
						'area' => new \Sql('SUM(m1.area)', 'int')
					])
					->join(\series\Series::model()
						->select('cycle'), 'm1.series = m2.id')
					->join(\series\Cultivation::model(), 'm2.id = m3.series')
					->join(\map\Bed::model(), 'm4.id = m1.bed')
					->where('m1.structure', $eStructure)
					->where('m1.season', $season)
					->where('m4.plotFill', FALSE)
					->where('m4.zoneFill', FALSE)
					->where('m4.status', \map\Bed::ACTIVE)
					->group('m2.cycle')
					->getCollection() as $value) {

					$cycle = ucfirst($value['cycle']);
					$beds = explode(',', $value['bedsList']);
					array_walk($beds, fn(&$value) => $value = (int)$value);

					$summary[$season]['beds'.$cycle] = $value['beds'];
					$summary[$season]['bedsArea'.$cycle] = \map\Bed::model()
						->whereId('IN', $beds)
						->getValue(new \Sql('SUM(area)'));
					$summary[$season]['bedsPlants'] += $value['plants'];

					$summary[$season]['bedsAreaDeveloped'.$cycle] = $value['area'];

					$summary[$season]['bedsAreaRate'.$cycle] = ($summary[$season]['bedsArea'.$cycle] > 0) ? round($summary[$season]['bedsAreaDeveloped'.$cycle] / $summary[$season]['bedsArea'.$cycle], 1) : NULL;

				}

			}

		}

		$defaultBlocks = [
			'blocks' => 0,
			'blockAreaDevelopedAnnual' => 0,
			'blockAreaDevelopedPerennial' => 0,
			'blockPlants' => 0,
		];

		foreach($seasons as $season) {

			$blocks = \series\Place::model()
				->select([
					'blocks' => new \Sql('COUNT(*)', 'int'),
					'blockPlants' => new \Sql('COUNT(DISTINCT m3.plant)', 'int'),
					'blockAreaDevelopedAnnual' => new \Sql('SUM(IF(m2.cycle="'.\series\Series::ANNUAL.'", m1.area, 0))', 'int'),
					'blockAreaDevelopedPerennial' => new \Sql('SUM(IF(m2.cycle="'.\series\Series::PERENNIAL.'", m1.area, 0))', 'int'),
				])
				->join(\series\Series::model(), 'm1.series = m2.id')
				->join(\series\Cultivation::model(), 'm2.id = m3.series')
				->join(\map\Bed::model(), 'm4.id = m1.bed')
				->where('m1.structure', $eStructure)
				->where('m1.season', $season)
				->or(
					fn() => $this->where('m4.plotFill', TRUE),
					fn() => $this->where('m4.zoneFill', TRUE)
				)
				->get()
				->getArrayCopy() ?: [
					'blockAreaDevelopedAnnual' => 0,
					'blockAreaDevelopedPerennial' => 0,
					'blockPlants' => 0,
				];

			$summary[$season] += ($blocks['blocks'] === 0 ? [] : $blocks) + $defaultBlocks;

		}

		return $summary;

	}

	public static function getPlants(\structure\Structure $eStructure, int $season, \Search $search): \Collection {

		$ccCultivation = \series\Cultivation::model()
			->select([
				'season',
				'plant' => ['name', 'fqn', 'vignette'],
				'area' => new \Sql('SUM(m1.area)', 'int'),
				'areaPermanent' => new \Sql('SUM(m1.areaPermanent)', 'int'),
				'harvested' => new \Sql('CONCAT("[", GROUP_CONCAT(m1.harvestedByUnit SEPARATOR ","), "]")'),
			])
			->join(\series\Series::model(), 'm1.series = m2.id', 'LEFT')
			->where('m1.season', 'IN', [$season, $season - 1])
			->where('m1.area', '!=', NULL)
			->where('m1.structure', $eStructure)
			->where('m2.cycle', $search->get('cycle'), if: $search->get('cycle'))
			->where('m2.use', $search->get('use'), if: $search->get('use'))
			->group(new \Sql('m1.season, m1.plant'))
			->sort(new \Sql('m1_area DESC'))
			->getCollection(NULL, NULL, ['season', 'plant']);

		$ccCultivation->map(function($eCultivation) {

			if($eCultivation['harvested'] === NULL) {
				return;
			}

			$harvested = [];

			foreach(json_decode($eCultivation['harvested'], TRUE) as $harvestedOne) {

				foreach($harvestedOne as $unit => $value) {

					if($value <= 0) {
						continue;
					}

					if(array_key_exists($unit, $harvested) === FALSE) {
						$harvested[$unit] = 0;
					}

					$harvested[$unit] += $value;

				}

			}


			array_walk($harvested, function(&$value) {
				$value = round($value, 2);
			});

			ksort($harvested);

			$eCultivation['harvested'] = $harvested;

		}, 2);

		return $ccCultivation;

	}

	public static function getFamilies(\structure\Structure $eStructure, int $season, \Search $search): \Collection {

		return \series\Cultivation::model()
			->select([
				'season',
				'area' => new \Sql('SUM(m1.area)', 'int'),
				'areaPermanent' => new \Sql('SUM(m1.areaPermanent)', 'int')
			])
			->join(\series\Series::model(), 'm1.series = m2.id', 'LEFT')
			->join(Plant::model()
				->select([
					'family' => ['name', 'fqn']
				]), 'm1.plant = m3.id', 'LEFT')
			->where('m1.season', 'IN', $search->get('seasons') ? $search->get('seasons') : [$season, $season - 1])
			->where('m1.area', '!=', NULL)
			->where('m1.structure', $eStructure)
			->where('m2.cycle', $search->get('cycle'), if: $search->get('cycle'))
			->where('m2.use', $search->get('use'), if: $search->get('use'))
			->group(new \Sql('m1.season, m3.family'))
			->sort(new \Sql('m1_area DESC'))
			->getCollection(NULL, NULL, ['season', 'family']);

	}

}
?>
