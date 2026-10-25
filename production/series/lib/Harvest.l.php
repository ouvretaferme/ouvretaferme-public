<?php
namespace series;

class HarvestLib extends HarvestCrud {

	const COLUMNS_SCENE = ['date', 'series', 'cultivation', 'quantity', 'unit'];

	public static function getElementFromTask(Task $eTask, string $date, float $quantity): \Element {

		$e = new Harvest([
			'structure' => $eTask['structure'],
			'task' => $eTask,
			'series' => $eTask['series'],
			'cultivation' => $eTask['cultivation'],
			'unit' => $eTask['harvestUnit'],
			'quantity' => $quantity,
			'date' => $date,
			'week' => toWeek($date),
		]);

		return $e;

	}

	public static function create(Harvest $e): void {

		parent::create($e);

		HistoryLib::createForHarvest($e, $e['quantity']);

	}

	public static function delete(Harvest $e): void {

		$e->expects(['structure', 'task', 'quantity', 'unit', 'date']);

		parent::delete($e);

		if(self::getAffected() > 0) {
			HistoryLib::createForHarvest($e, -$e['quantity']);
		}

	}

	public static function synchronizeTask(Task $eTask): void {

		$eTask->expects(['series', 'cultivation']);

		// Toutes les lignes de l'intervention à la fois : la forme tableau, puisque update($e) n'écrit que la ligne de $e
		Harvest::model()
			->whereTask($eTask)
			->update([
				'series' => $eTask['series'],
				'cultivation' => $eTask['cultivation']
			]);

	}

	public static function getTasksByWeek(\structure\Structure $eStructure, string $week): \Collection {

		$firstDate = week_date_starts($week);
		$lastDate = week_date_ends($week);

		return Harvest::model()
			->whereStructure($eStructure)
			->whereDate('BETWEEN', new \Sql(Harvest::model()->format($firstDate).' AND '.Harvest::model()->format($lastDate)))
			->group('task')
			->getColumn('task');

	}

	/**
	 * Le récolté d'une semaine, par culture et par part (`CultivationLib::getHarvestPartKey()`), unité par
	 * unité : une seule lecture sert la culture et ses parts, la culture étant la somme de ses parts. Une part
	 * porte sa variété et son calibre, nommés, pour qu'une part récoltée cette semaine-là seulement puisse
	 * avoir sa ligne (`CultivationLib::getForHarvestWeek()`).
	 *
	 * @return array [[cultivation => [unit => quantity]], [cultivation => [part => ['eVariety', 'eSize', 'quantities' => [unit => quantity]]]]]
	 */
	public static function getTotalInWeek(\structure\Structure $eStructure, \Collection $cCultivation, string $week): array {

		if($cCultivation->empty()) {
			return [[], []];
		}

		$cHarvest = self::applyTotalInWeek(Harvest::model(), $eStructure, $cCultivation, $week)->getCollection();

		$total = [];
		$parts = [];

		foreach($cHarvest as $eHarvest) {

			$cultivation = $eHarvest['cultivation']['id'];
			$unit = $eHarvest['unit'];
			$key = CultivationLib::getHarvestPartKey($eHarvest['variety'], $eHarvest['harvestSize']);

			$total[$cultivation][$unit] = round(($total[$cultivation][$unit] ?? 0.0) + $eHarvest['total'], 3);

			if($eHarvest['total'] !== 0.0) {
				$parts[$cultivation][$key] ??= [
					'eVariety' => $eHarvest['variety'],
					'eSize' => $eHarvest['harvestSize'],
					'quantities' => []
				];
				$parts[$cultivation][$key]['quantities'][$unit] = $eHarvest['total'];
			}

		}

		// Une correction négative peut ramener une part, ou la culture, à zéro : rien ne s'y montre.
		$total = array_map(fn($units) => array_filter($units, fn($quantity) => $quantity !== 0.0), $total);
		$total = array_filter($total, fn($units) => $units !== []);

		return [$total, $parts];

	}

	public static function applyTotalInWeek(HarvestModel $mHarvest, \structure\Structure $eStructure, \Collection $cCultivation, string $week): HarvestModel {

		// Le couple variété · calibre est porté par l'intervention, comme dans `CultivationLib::applyHarvestedInWeek()`.
		return $mHarvest
			->select([
				'cultivation', 'unit',
				'total' => new \Sql('ROUND(SUM(m1.quantity), 3)', 'float')
			])
			->join(Task::model()
				->select([
					'variety' => ['name'],
					'harvestSize' => ['name'],
				]), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.week', $week)
			->where('m1.cultivation', 'IN', $cCultivation)
			->group([new \Sql('m1.cultivation'), new \Sql('m1.unit'), new \Sql('m2.variety'), new \Sql('m2.harvestSize')])
			->sort(['m1.unit' => SORT_ASC]);

	}

	/**
	 * Les récoltes des séries placées, pour une scène (`\map\SceneLib`, dont la règle de version vaut pour ce
	 * format), en tuples de `COLUMNS_SCENE`. Une série d'hiver se récolte de part et d'autre de son année, du 1er juillet
	 * d'avant sa saison au 31 mars d'après, et la scène pose aussi les séries des saisons voisines
	 * (`SeriesLib::getForScene()`) : la fenêtre court du 1er juillet d'avant la saison précédente au 31 mars d'après la
	 * suivante.
	 */
	public static function getForScene(\structure\Structure $eStructure, int $season, array $seriesIds): array {

		$cHarvest = Harvest::model()
			->select(['series', 'cultivation', 'quantity', 'unit', 'date'])
			->whereStructure($eStructure)
			->whereSeries('IN', $seriesIds)
			->whereDate('BETWEEN', new \Sql(Harvest::model()->format(($season - 2).'-07-01').' AND '.Harvest::model()->format(($season + 2).'-03-31')))
			->sort(['date' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

		$harvests = [];

		foreach($cHarvest as $eHarvest) {
			$harvests[] = [
				$eHarvest['date'],
				$eHarvest['series']['id'],
				$eHarvest['cultivation']->empty() ? NULL : $eHarvest['cultivation']['id'],
				round($eHarvest['quantity'], 2),
				$eHarvest['unit'],
			];
		}

		return $harvests;

	}

}
?>
