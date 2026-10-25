<?php
namespace map;

class BedLib extends BedCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['plot', 'name', 'length', 'width', 'alleyWidth'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['plot', 'name', 'length', 'width', 'alleyWidth', 'seasonFirst', 'seasonLast'];
	}

	/**
	 * Les dimensions que « Modifier des planches » change : celles que le formulaire déclare modifiées dans `changed`. Un champ
	 * laissé tel qu'il s'est ouvert garde la valeur de chaque planche, ce qui permet de changer la seule longueur de planches de
	 * largeurs différentes. Vider le passe-pied le retire, puisqu'une planche peut ne pas en avoir ; une longueur ou une largeur
	 * vidée ne change rien, une planche ne pouvant pas s'en passer. Rien de modifié, rien à enregistrer.
	 */
	public static function getPropertiesUpdateSize(array $input): array {

		$properties = array_values(array_filter(['length', 'width', 'alleyWidth'], function(string $property) use($input) {

			if(($input['changed'][$property] ?? NULL) !== '1') {
				return FALSE;
			}

			return ($property === 'alleyWidth' or ($input[$property] ?? '') !== '');

		}));

		if($properties === []) {
			Bed::fail('size.empty');
		}

		return $properties;

	}

	/**
	 * Les dimensions communes aux planches, qui préremplissent « Modifier des planches » : une dimension qui diffère d'une planche à
	 * l'autre reste vide et se nomme dans `propertiesMixed`, et la surface n'est commune que si la longueur et la largeur le sont.
	 */
	public static function getSizeCommon(\Collection $cBed): Bed {

		$eBed = new Bed([
			'propertiesMixed' => []
		]);

		foreach(['length', 'width', 'alleyWidth'] as $property) {

			// getColumn() saute les valeurs nulles, alors qu'une dimension vide sur une seule planche les distingue des autres
			$values = [];

			foreach($cBed as $eBedSelected) {
				$values[] = $eBedSelected[$property];
			}

			$values = array_unique($values, SORT_REGULAR);

			if(count($values) === 1) {
				$eBed[$property] = $values[array_key_first($values)];
			} else {
				$eBed[$property] = NULL;
				$eBed['propertiesMixed'][] = $property;
			}

		}

		$eBed['area'] = ($eBed['length'] !== NULL and $eBed['width'] !== NULL) ? $eBed['length'] * $eBed['width'] / 100 : 0;

		return $eBed;

	}


	/**
	 * $properties : ce que chaque planche lit de son index. Le formulaire des planches ne donne que leurs noms, leurs dimensions
	 * étant communes, alors que les planches d'un jardin neuf ont chacune la longueur que sa forme leur laisse.
	 */
	public static function buildFromInput(Bed $eBedSource, array $input, array $properties = ['name']): \Collection {

		$eBedSource->expects([
			'plot' => ['structure']
		]);

		$cBed = new \Collection();

		$fw = new \FailWatch();

		$names = $input['name'] ?? [];

		if(is_array($names) === FALSE) {
			Bed::fail('names.check');
			return $cBed;
		}

		// Le champ du nombre ne borne que le navigateur, et chaque planche s'insère seule dans la transaction de l'envoi
		if(count($names) > MapSetting::$onboardingBedsMax) {
			Bed::fail('number.check', ['value' => MapSetting::$onboardingBedsMax], wrapper: 'names');
			return $cBed;
		}

		foreach(array_keys($names) as $index) {

			$eBed = clone $eBedSource;
			$eBed->buildIndex($properties, $input, $index, new \PropertyList('create'));

			$cBed[$index] = $eBed;

		}

		if($cBed->empty()) {
			Bed::fail('names.check');
			return $cBed;
		}

		// Seule règle que build() ne peut pas porter : il ne voit qu'une planche à la fois
		if($fw->ok()) {

			$names = $cBed->getColumn('name');

			if(count(array_unique($names)) < count($names)) {
				Bed::fail('names.different');
			}

		}

		return $cBed;

	}

	/**
	 * Le rang de planches à créer dessiné sur la carte d'un jardin qui a sa forme. Les planches n'ont pas encore d'id : la place de
	 * chacune, son coin et son angle, part sous sa position dans le rang, dans un seul champ en JSON dont `$wrapper` est le nom et
	 * reçoit les refus. Rend le rang et la place de chaque planche, vides quand le jardin ne reçoit pas de rang ou que le JSON ne se lit pas.
	 */
	public static function buildDraw(Plot $ePlot, int $season, \Collection $cBed, string $json, string $wrapper): array {

		if($ePlot->canBedLine() === FALSE) {
			Bed::fail('canNotDraw', wrapper: $wrapper);
			return [new Draw(), new \Collection()];
		}

		$input = json_decode($json, TRUE);

		if(is_array($input) === FALSE) {
			Bed::fail('draw.invalid', wrapper: $wrapper);
			return [new Draw(), new \Collection()];
		}

		$eDraw = new Draw([
			'structure' => $ePlot['structure'],
			'plot' => $ePlot
		]);

		$eDraw->build(['season'], [
			'season' => $season
		]);

		$cDraw = new \Collection();

		// Les refus d'une planche se posent sur le champ du rang, qui est le seul que l'écran montre
		foreach($cBed as $position => $eBed) {

			$eDrawBed = new Draw([
				'bed' => $eBed,
				'plot' => $ePlot
			]);

			$l = new \PropertyList()->setWrapper(fn() => $wrapper);

			$eDrawBed->build(['corner', 'angle'], [
				'corner' => $input['corner'][$position] ?? NULL,
				'angle' => $input['angle'][$position] ?? NULL
			], $l);

			$cDraw[$position] = $eDrawBed;

			// Les planches suivantes sortent du même rang : un message suffit
			if($l->isInvalid('angle')) {
				break;
			}

		}

		return [$eDraw, $cDraw];

	}

	public static function buildIds(Plot $ePlot, array $ids): \Collection {

		$ePlot->expects('id');

		if($ids === []) {
			Bed::fail('ids.check');
			return new \Collection();
		}

		$cBed = new \Collection();

		foreach($ids as $id) {

			Bed::model()->cast('id', $id);

			if(Bed::model()->check('id', $id)) {

				$eBed = new Bed([
					'id' => $id
				]);

				$cBed[] = $eBed;

			} else {
				Bed::fail('ids.check');
				return new \Collection();
			}

		}

		if($cBed->empty()) {
			Bed::fail('ids.check');
		}

		// On vérifie que toutes les planches appartiennent bien au jardin, et qu'aucune n'est la planche de remplissage d'un jardin ou
		// d'une parcelle : elle n'a pas de dimensions, et aucune liste ne la propose
		Bed::model()
			->select(['plot', 'name', 'length', 'width', 'alleyWidth'])
			->whereStatus(Bed::ACTIVE)
			->whereZoneFill(FALSE)
			->wherePlotFill(FALSE)
			->get($cBed);

		if(Bed::model()->found() !== $cBed->count()) {
			Bed::fail('ids.check');
		} else {

			foreach($cBed as $eBed) {

				if($eBed['plot']['id'] !== $ePlot['id']) {
					Bed::fail('ids.check');
				}

			}

		}

		return $cBed;

	}

	public static function getByStructure(\structure\Structure $eStructure, int $season): \Collection {

		SeasonLib::whereSeason(Bed::model(), $season);

		$cPlot = PlotLib::getByStructure($eStructure, season: $season);

		return Bed::model()
			->select(Bed::getSelection())
			->whereStructure($eStructure)
			->where('plot', 'IN', $cPlot)
			->where('plotFill', FALSE)
			->where('zoneFill', FALSE)
			->whereStatus(Bed::ACTIVE)
			->getCollection(index: 'id')
			->sort('name');

	}

	public static function getByPlot(Plot $ePlot, int $season): \Collection {

		SeasonLib::whereSeason(Bed::model(), $season);

		return Bed::model()
			->select(['id', 'name'])
			->wherePlot($ePlot)
			->wherePlotFill(FALSE)
			->whereStatus(Bed::ACTIVE)
			->getCollection(index: 'id')
			->sort('name', natural: TRUE);

	}

	/**
	 * Devine la suite de la numérotation à partir des planches en place : le motif
	 * préfixe + nombre + suffixe le plus fréquent, et le nombre qui suit le plus grand.
	 * Sans planche ou sans motif reconnaissable, on repart de 1.
	 */
	public static function guessNumbering(\Collection $cBed): array {

		$patterns = [];

		foreach($cBed as $eBed) {

			if(preg_match('/^(.*?)(\d+)([^\d]*)$/', $eBed['name'], $matches) === 1) {

				$key = $matches[1].'|'.$matches[3];

				$patterns[$key] ??= ['prefix' => $matches[1], 'suffix' => $matches[3], 'max' => 0, 'count' => 0, 'pad' => 0];
				$patterns[$key]['count']++;
				$patterns[$key]['max'] = max($patterns[$key]['max'], (int)$matches[2]);
				$padded = (strlen($matches[2]) > 1 and $matches[2][0] === '0');
				$patterns[$key]['pad'] = max($patterns[$key]['pad'], $padded ? strlen($matches[2]) : 0);

			}

		}

		if($patterns === []) {
			return ['prefix' => '', 'start' => 1, 'suffix' => '', 'pad' => 0];
		}

		uasort($patterns, fn($a, $b) => $b['count'] <=> $a['count']);
		$pattern = first($patterns);

		return ['prefix' => $pattern['prefix'], 'start' => $pattern['max'] + 1, 'suffix' => $pattern['suffix'], 'pad' => $pattern['pad']];

	}

	public static function countWidthsByStructure(\structure\Structure $eStructure, int $season): int {

		SeasonLib::whereSeason(Bed::model(), $season);

		return Bed::model()
			->whereStructure($eStructure)
			->wherePlotFill(FALSE)
			->whereZoneFill(FALSE)
			->getValue(new \Sql('COUNT(DISTINCT width)', 'int'));

	}

	// Une planche à la fois : seule une insertion unitaire rend son id à l'élément, que le trait d'un rang créé avec elles cite
	public static function createCollection(\Collection $c): void {

		Bed::model()->beginTransaction();

		foreach($c as $e) {
			self::create($e);
		}

		Bed::model()->commit();

	}

	// Des planches créées en dessinant leur rang : elles et leur place, ou rien. Une place cite sa planche par son id, connu après sa création
	public static function createCollectionDrawn(\Collection $c, Draw $eDraw, \Collection $cDraw): void {

		Bed::model()->beginTransaction();

		self::createCollection($c);

		DrawLib::createByBeds($eDraw, $cDraw);

		Bed::model()->commit();

	}

	public static function create(Bed $e): void {

		self::prepareCreate($e);

		Bed::model()->insert($e);

	}

	private static function prepareCreate(Bed $e): void {

		$e->expects([
			'plot' => ['structure', 'zone'],
		]);

		$e['zone'] = $e['plot']['zone'];
		$e['structure'] = $e['plot']['structure'];

	}

	public static function updatePlotCollection(\Collection $c, Bed $e): void {

		$e->expects(['plot']);

		$names = array_filter($c->getColumn('name'), fn($name) => $name !== NULL);

		if(
			$names !== [] and
			Bed::model()
				->wherePlot($e['plot'])
				->whereName('IN', $names)
				->whereStatus(Bed::ACTIVE)
				->whereId('NOT IN', $c)
				->exists()
		) {
			Bed::fail('plot.duplicate');
			return;
		}

		self::updateCollection($c, $e, ['plot']);

	}

	public static function updateCollection(\Collection $c, \Element $e, array $properties): void {

		self::prepareUpdate($e, $properties);

		Bed::model()->beginTransaction();

		parent::updateCollection($c, $e, $properties);

		if(in_array('plot', $properties)) {

			\series\Place::model()
				->whereBed('IN', $c)
				->update([
					'plot' => $e['plot'],
					'zone' => $e['zone']
				]);

			DrawLib::deleteByBedsOutsidePlot($c, $e['plot']);

		}

		Bed::model()->commit();

	}

	public static function update(Bed $e, array $properties = []): void {

		self::prepareUpdate($e, $properties);

		Bed::model()->beginTransaction();

		parent::update($e, $properties);

		if(in_array('plot', $properties)) {

			\series\Place::model()
				->whereBed($e)
				->update([
					'plot' => $e['plot'],
					'zone' => $e['zone']
				]);

			DrawLib::deleteByBedsOutsidePlot(new \Collection([$e]), $e['plot']);

		}

		Bed::model()->commit();

	}

	private static function prepareUpdate(\Element $e, array &$properties) {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		// La surface se calcule sur chaque ligne, avec ses propres dimensions : une collection dont on ne change que la longueur garde
		// les largeurs de chaque planche. MySQL écrit les colonnes de gauche à droite, et la surface vient après les dimensions
		if(in_array('length', $properties) or in_array('width', $properties)) {

			$e['area'] = new \Sql('length * width / 100');
			$properties[] = 'area';

		}

		if(in_array('plot', $properties)) {

			$e->expects(['plot' => ['zone']]);

			$e['zone'] = $e['plot']['zone'];
			$properties[] = 'zone';

		}

	}

	public static function deleteCollection(\Collection $c): void {

		foreach($c as $e) {
			self::delete($e);
		}

	}

	public static function delete(Bed $e): void {

		$e->expects(['id']);

		if(\series\Place::model()
				->whereBed($e)
				->exists()) {

			Bed::model()->update($e, [
				'status' => Bed::DELETED
			]);

		} else {

			Bed::model()->beginTransaction();

			DrawLib::deleteByBed($e);

			Bed::model()
				->wherePlotFill(FALSE)
				->delete($e);

			Bed::model()->commit();

		}

	}

	public static function putFamilyReturns(\Collection $cZone, int $season, int $years): void {

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				foreach($ePlot['cBed'] as $eBed) {
					$eBed['familyReturns'] = self::getFamilyReturns($eBed['cPlace'], $season, $years);
				}

			}

		}

	}

	public static function getFamiliesBySeason(\Collection $cPlace): array {

		$families = [];

		foreach($cPlace as $ePlace) {

			foreach($ePlace['series']['cCultivation'] as $eCultivation) {

				if($eCultivation['plant']['family']->empty()) {
					continue;
				}

				$families[$eCultivation['season']][$eCultivation['plant']['family']['id']] = TRUE;

			}

		}

		return $families;

	}

	public static function getFamilyReturns(\Collection $cPlace, int $season, int $years): array {

		return self::getReturnsFromFamilies(self::getFamiliesBySeason($cPlace), $season, $years);

	}

	/**
	 * Les retours de famille trop rapprochés, saison par saison. Chaque saison
	 * concernée rend son écart le plus court et la liste des familles qui le
	 * provoquent : sans elles, l'alerte désigne la ligne sans dire laquelle des
	 * trois vignettes est en cause.
	 *
	 * @return array<int, array{gap: int, families: array<int, int>}>
	 */
	public static function getReturnsFromFamilies(array $families, int $season, int $years): array {

		$returns = [];

		for($i = $season; $i > $season - $years; $i--) {

			foreach(($families[$i] ?? []) as $familyId => $ignored) {

				for($gap = 1; $gap < $years; $gap++) {

					if(isset($families[$i - $gap][$familyId])) {

						$returns[$i] ??= ['gap' => $gap, 'families' => []];
						$returns[$i]['gap'] = min($returns[$i]['gap'], $gap);
						$returns[$i]['families'][$familyId] = $gap;

						break;

					}

				}

			}

		}

		return $returns;

	}

	public static function swapSeries(int $season, Bed $e1, Bed $e2): void {

		$tmp = 999999999 + $e1['id'];

		\series\Place::model()->beginTransaction();

		$affectedTmp = \series\Place::model()
			->whereSeason($season)
			->whereBed($e1)
			->update([
				'bed' => $tmp
			]);

		\series\Place::model()
			->whereSeason($season)
			->whereBed($e2)
			->update([
				'bed' => $e1,
				'plot' => $e1['plot'],
				'zone' => $e1['zone']
			]);

		if($affectedTmp > 0) {

			\series\Place::model()
				->whereSeason($season)
				->whereBed($tmp)
				->update([
					'bed' => $e2,
					'plot' => $e2['plot'],
					'zone' => $e2['zone']
				]);

		}

		\series\Place::model()->commit();

	}

}
?>
