<?php
namespace series;

class SliceLib extends SliceCrud {

	public static function delegateByCultivation(): SliceModel {

		return Slice::model()
			->select([
				'variety' => \plant\Variety::getSelection(),
				'partPercent', 'partArea', 'partLength', 'partPlant', 'partTray'
			])
			->delegateCollection('cultivation', callback: fn($c) => $c->sort(['variety' => ['name']], natural: TRUE));

	}

	public static function getByCultivation(Cultivation $eCultivation): \Collection {

		return Slice::model()
			->select(SliceElement::getSelection() + [
				'variety' => ['name']
			])
			->whereCultivation($eCultivation)
			->getCollection();

	}

	/**
	 * Les tranches de plusieurs cultures, avec le nom de leur variété.
	 */
	public static function getByCultivations(\Collection $cCultivation): \Collection {

		return Slice::model()
			->select([
				'cultivation',
				'variety' => ['name']
			])
			->whereCultivation('IN', $cCultivation)
			->getCollection();

	}

	/**
	 * Les variétés du champ Variété pour des cultures choisies ensemble : celles de leurs tranches, toutes cultures
	 * confondues (`cVariety`), et parmi elles celles que chaque culture cultive (`varietiesIntersect`), seules
	 * choisissables puisque `Task::variety` refuse une variété sans tranche sur la culture de l'intervention.
	 */
	public static function getVarietiesByCultivations(\Collection $cCultivation): array {

		$ccSlice = Slice::model()
			->select([
				'cultivation',
				'variety' => ['name']
			])
			->whereCultivation('IN', $cCultivation)
			->getCollection(NULL, NULL, ['cultivation', NULL]);

		if($ccSlice->empty()) {
			return [
				'varietiesIntersect' => [],
				'cVariety' => new \Collection(),
			];
		}

		// Une culture sans tranche ne cultive aucune variété : elle compte dans l'intersection et la vide.
		$varietiesIntersect = array_intersect(...$cCultivation->makeArray(fn(Cultivation $eCultivation) => $ccSlice->offsetExists($eCultivation['id']) ?
			$ccSlice[$eCultivation['id']]->getColumnCollection('variety')->getIds() :
			[]
		));

		$cVariety = new \Collection();

		foreach($ccSlice as $cSlice) {
			foreach($cSlice as $eSlice) {
				$eVariety = $eSlice['variety'];
				$cVariety[$eVariety['id']] = $eVariety;
			}
		}

		return [
			'varietiesIntersect' => $varietiesIntersect,
			'cVariety' => $cVariety,
		];

	}

	public static function createCollection(\Collection $c): void {

		$c->map(fn($e) => self::prepareCreate($e));

		try {
			Slice::model()->insert($c);
		} catch(\DuplicateException) {
			Cultivation::fail('variety.duplicate');
		}

	}

	public static function create(Slice $e): void {

		self::prepareCreate($e);

		parent::create($e);

	}

	private static function prepareCreate(Slice $e): void {

		$e->expects([
			'cultivation' => ['structure', 'plant', 'series']
		]);

		// Transfert de propriétés
		$e['structure'] = $e['cultivation']['structure'];
		$e['plant'] = $e['cultivation']['plant'];
		$e['series'] = $e['cultivation']['series'];

	}

	public static function deleteByCultivation(Cultivation $eCultivation): void {

		Slice::model()
			->whereCultivation($eCultivation)
			->delete();

	}

}
?>
