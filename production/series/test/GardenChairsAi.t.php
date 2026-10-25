<?php
namespace series;

/**
 * Les jardins identiques (`GardenChairsAiLib`).
 *
 * Un groupe réunit les jardins d'une même structure qui ont le même nombre de planches et les mêmes
 * dimensions, jamais un abri avec du plein champ ; un jardin dont une planche n'a pas de dimension
 * n'entre dans aucun. Déplacer le contenu d'un jardin sur un autre pose chaque série de la saison sur
 * la planche de même rang, avec sa longueur. Une série posée aussi ailleurs n'y déplace que ses
 * planches du jardin qui bouge. Restent où elles sont : les séries closes, vivaces ou d'une autre
 * saison (`PlaceLib::isLocked()`). Le serveur refuse ce qui n'est pas une permutation des jardins
 * du groupe.
 *
 * Les jardins du décor ont des planches de 17 m × 63 cm, une dimension qu'aucun jardin réel de la
 * structure hôte ne porte : son parcellaire est chargé avec celui du décor, et un groupe de la ferme
 * ne doit pas s'y mêler.
 *
 * Lancement : dev/test package=series file=GardenChairsAi
 */
class GardenChairsAiTest extends SeriesFixtureAiTest {

	const LENGTH = 17;
	const WIDTH = 63;

	public function testGardensOfTheSameFormatMakeAGroup(): void {

		$eZone = $this->createZone();

		$ePlotA = $this->createGarden($eZone);
		$ePlotB = $this->createGarden($eZone);
		$ePlotGreenhouse = $this->createGarden($eZone, \map\Plot::GREENHOUSE);
		$ePlotLonger = $this->createGarden($eZone, length: self::LENGTH + 1);
		$ePlotUnmeasured = $this->createGarden($eZone, width: NULL);

		$groups = GardenChairsAiLib::getByStructure($this->eStructure, $this->getSeason());

		$plots = GardenChairsAiLib::getGroupByPlot($groups, $ePlotA['id'])->getIds();
		sort($plots);

		$this->assertEquals([$ePlotA['id'], $ePlotB['id']], $plots, 'deux jardins de même format forment un groupe');
		$this->assertTrue(GardenChairsAiLib::getGroupByPlot($groups, $ePlotGreenhouse['id'])->empty(), 'un abri ne rejoint pas des jardins de plein champ');
		$this->assertTrue(GardenChairsAiLib::getGroupByPlot($groups, $ePlotLonger['id'])->empty(), 'une longueur différente fait un autre format');
		$this->assertTrue(GardenChairsAiLib::getGroupByPlot($groups, $ePlotUnmeasured['id'])->empty(), 'une planche sans dimension sort son jardin de la détection');

	}

	public function testGardensMustMatchBedByBed(): void {

		$eZone = $this->createZone();

		$ePlotShortFirst = $this->createGardenOf($eZone, [self::LENGTH, self::LENGTH + 2]);
		$ePlotShortFirstToo = $this->createGardenOf($eZone, [self::LENGTH, self::LENGTH + 2]);
		$ePlotLongFirst = $this->createGardenOf($eZone, [self::LENGTH + 2, self::LENGTH]);

		$groups = GardenChairsAiLib::getByStructure($this->eStructure, $this->getSeason());

		$plots = GardenChairsAiLib::getGroupByPlot($groups, $ePlotShortFirst['id'])->getIds();
		sort($plots);

		$this->assertEquals([$ePlotShortFirst['id'], $ePlotShortFirstToo['id']], $plots, 'deux jardins aux mêmes dimensions dans le même ordre forment un groupe');
		$this->assertTrue(GardenChairsAiLib::getGroupByPlot($groups, $ePlotLongFirst['id'])->empty(), 'les mêmes dimensions dans un autre ordre enverraient une série sur une planche d\'une autre taille');

	}

	public function testTheContentMovesToTheBedOfTheSameRank(): void {

		$eZone = $this->createZone();

		$ePlotA = $this->createGarden($eZone);
		$ePlotB = $this->createGarden($eZone);
		$ePlotC = $this->createGarden($eZone);
		$ePlotOutside = $this->createGarden($eZone, length: self::LENGTH + 1);

		[$eBedA1, $eBedA2] = $this->getBeds($ePlotA);
		[$eBedB1, $eBedB2] = $this->getBeds($ePlotB);
		[$eBedC1] = $this->getBeds($ePlotC);
		[$eBedOutside] = $this->getBeds($ePlotOutside);

		$eSeriesA = $this->createSeriesBed('Sur A');
		$this->createPlace($eSeriesA, $eBedA1, 10);
		$this->createPlace($eSeriesA, $eBedA2, 12);

		$eSeriesB = $this->createSeriesBed('Sur B');
		$this->createPlace($eSeriesB, $eBedB2, 7);

		$eSeriesClosed = $this->createSeriesBed('Close sur A');
		$this->createPlace($eSeriesClosed, $eBedA2, 5);
		$this->closeSeries($eSeriesClosed);

		$eSeriesPerennial = $this->createSeriesPerennial('Pérenne sur A');
		$this->createPlace($eSeriesPerennial, $eBedA1, 3);

		$eSeriesAcross = $this->createSeriesBed('À cheval sur C et hors du groupe');
		$this->createPlace($eSeriesAcross, $eBedC1, 4);
		$this->createPlace($eSeriesAcross, $eBedOutside, 4);

		$groups = GardenChairsAiLib::getByStructure($this->eStructure, $this->getSeason());
		$cPlot = GardenChairsAiLib::getGroupByPlot($groups, $ePlotA['id']);

		$this->assertEquals([$eSeriesA['id']], $cPlot[$ePlotA['id']]['seriesMoving'], 'la série close et la pérenne restent sur A');
		$this->assertEquals([$eSeriesPerennial['id'] => 'Pérenne sur A'], $cPlot[$ePlotA['id']]['seriesPerennial'], 'la pesée nomme la pérenne qui reste');
		$this->assertEquals([$eSeriesAcross['id']], $cPlot[$ePlotC['id']]['seriesMoving'], 'une série en partie hors du groupe suit son jardin');

		$fw = new \FailWatch();

		$moves = GardenChairsAiLib::move($this->eStructure, $cPlot, [
			$ePlotA['id'] => $ePlotB['id'],
			$ePlotB['id'] => $ePlotC['id'],
			$ePlotC['id'] => $ePlotA['id'],
		], $this->getSeason());

		$this->assertTrue($fw->ok());
		$plots = array_keys($moves);
		sort($plots);

		$this->assertEquals([$ePlotA['id'], $ePlotB['id'], $ePlotC['id']], $plots);

		$this->assertEquals($this->sortLengths([$eBedB1['id'] => 10, $eBedB2['id'] => 12]), $this->getLengths($eSeriesA), 'chaque planche sur celle de même rang, longueur gardée');
		$this->assertEquals([$this->getBeds($ePlotC)[1]['id'] => 7], $this->getLengths($eSeriesB));
		$this->assertEquals([$eBedA2['id'] => 5], $this->getLengths($eSeriesClosed), 'une série close ne bouge pas');
		$this->assertEquals([$eBedA1['id']], array_keys($this->getLengths($eSeriesPerennial)), 'une série pérenne ne bouge pas');
		$this->assertEquals($this->sortLengths([$eBedA1['id'] => 4, $eBedOutside['id'] => 4]), $this->getLengths($eSeriesAcross), 'seule la planche du jardin qui bouge se déplace');

	}

	/**
	 * La bande d'un jardin ne nomme que les familles de ses séries qui partent. B porte déjà un retour
	 * de la même famille sur sa seconde planche, par une série close qui y reste : ce retour existe
	 * que A arrive ou non, et ne compte pas.
	 *
	 * La rotation ne remonte pas avant la première saison de la structure
	 * (`Configuration::getRotationSeasons()`), et le décor n'en ouvre que deux : la borne s'abaisse sur
	 * la configuration mémoïsée par structure (`Structure::conf()`) pour que la saison précédente
	 * compte, et se rétablit en sortie quel que soit le résultat.
	 */
	public function testTheBandNamesOnlyTheFamiliesThatMove(): void {

		$eZone = $this->createZone();

		$ePlotA = $this->createGarden($eZone);
		$ePlotB = $this->createGarden($eZone);

		[$eBedA1] = $this->getBeds($ePlotA);
		[$eBedB1, $eBedB2] = $this->getBeds($ePlotB);

		$eFamily = \plant\Family::model()
			->select('id', 'name')
			->get();

		$ePlant = new \plant\Plant([
			'name' => 'Espèce de famille du décor '.uniqid(),
			'structure' => $this->eStructure,
			'cycle' => \plant\Plant::ANNUAL,
			'family' => $eFamily,
		]);

		\plant\PlantLib::create($ePlant);

		$season = $this->getSeason();

		$eConfiguration = $this->eStructure->conf(\map\Configuration::class);
		$seasonFirst = $eConfiguration['seasonFirst'];

		$eConfiguration['seasonFirst'] = min($seasonFirst, $season - 1);

		try {

			$eSeriesMoving = $this->createSeriesOf($ePlant, $season);
			$this->createPlace($eSeriesMoving, $eBedA1, 10);

			$this->createPlace($this->createSeriesOf($ePlant, $season - 1), $eBedB1, 10);

			$this->createPlace($this->createSeriesOf($ePlant, $season - 1), $eBedB2, 10);
			$eSeriesClosed = $this->createSeriesOf($ePlant, $season);
			$this->createPlace($eSeriesClosed, $eBedB2, 10);
			$this->closeSeries($eSeriesClosed);

			$groups = GardenChairsAiLib::getByStructure($this->eStructure, $season);
			$cPlot = GardenChairsAiLib::getGroupByPlot($groups, $ePlotA['id']);

			$ePlotFrom = $cPlot[$ePlotA['id']];

			$this->assertEquals(1, $ePlotFrom['watch'][$ePlotB['id']], 'seule la planche où arrive la famille qui part est à surveiller');
			$this->assertEquals([$eFamily['id'] => ['name' => $eFamily['name'], 'gap' => 1, 'beds' => 1]], $ePlotFrom['returns'][$ePlotB['id']]['families']);
			$this->assertEquals([0 => [$ePlant['id'] => 1]], $ePlotFrom['returns'][$ePlotB['id']]['plants'], 'la planche comptée porte l\'espèce qui revient');

			$this->assertEquals([], $cPlot[$ePlotB['id']]['seriesMoving']);
			$this->assertEquals(0, $cPlot[$ePlotB['id']]['watch'][$ePlotA['id']], 'un jardin dont rien ne part n\'a rien à surveiller');

		} finally {
			$eConfiguration['seasonFirst'] = $seasonFirst;
		}

	}

	public function testAMoveThatIsNotAPermutationIsRefused(): void {

		$eZone = $this->createZone();

		$ePlotA = $this->createGarden($eZone);
		$ePlotB = $this->createGarden($eZone);

		$eSeries = $this->createSeriesBed('Sur A');
		$this->createPlace($eSeries, $this->getBeds($ePlotA)[0], 10);

		$groups = GardenChairsAiLib::getByStructure($this->eStructure, $this->getSeason());
		$cPlot = GardenChairsAiLib::getGroupByPlot($groups, $ePlotA['id']);

		$fw = new \FailWatch();

		GardenChairsAiLib::move($this->eStructure, $cPlot, [
			$ePlotA['id'] => $ePlotB['id'],
			$ePlotB['id'] => $ePlotB['id'],
		], $this->getSeason());

		$this->assertEquals(['Place::plotsPermutation'], array_map(fn($fail) => $fail[1], $fw->get()));
		$this->assertEquals([$this->getBeds($ePlotA)[0]['id'] => 10], $this->getLengths($eSeries), 'rien n\'a bougé');

	}

	private function createGarden(\map\Zone $eZone, string $mode = \map\Plot::OPEN_FIELD, int $length = self::LENGTH, ?int $width = self::WIDTH): \map\Plot {

		$ePlot = $this->createPlot($eZone);

		if($mode !== \map\Plot::OPEN_FIELD) {
			$ePlot['mode'] = $mode;
			\map\PlotLib::update($ePlot, ['mode']);
		}

		$this->createBed($ePlot, $length, self::WIDTH);
		$eBed = $this->createBed($ePlot, $length, self::WIDTH);

		// Le décor pose directement l'état à éprouver, une planche que personne n'a mesurée.
		if($width === NULL) {
			\map\Bed::model()->update($eBed, ['width' => NULL]);
		}

		return $ePlot;

	}

	/**
	 * Un jardin de plein champ dont les planches, nommées P1, P2… pour fixer leur rang, ont les
	 * longueurs données dans cet ordre.
	 *
	 * @param int[] $lengths
	 */
	private function createGardenOf(\map\Zone $eZone, array $lengths): \map\Plot {

		$ePlot = $this->createPlot($eZone);

		foreach($lengths as $position => $length) {
			$eBed = $this->createBed($ePlot, $length, self::WIDTH);
			$eBed['name'] = 'P'.($position + 1);
			\map\BedLib::update($eBed, ['name']);
		}

		return $ePlot;

	}

	/** Une série en planches d'une saison donnée, d'une seule espèce : la culture prend la saison de sa série. */
	private function createSeriesOf(\plant\Plant $ePlant, int $season): Series {

		$eSeries = new Series([
			'structure' => $this->eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => 'Série de '.$season,
			'cycle' => Series::ANNUAL,
			'use' => Series::BED,
			'mode' => Series::OPEN_FIELD,
			'season' => $season,
			'plants' => 1,
			'areaTarget' => NULL,
			'lengthTarget' => 100,
			'bedWidth' => 80,
			'alleyWidth' => 40,
			'perennialFirst' => new Series(),
			'duplicateOf' => new Series(),
		]);

		SeriesLib::create($eSeries);

		$this->createCultivation($eSeries, $ePlant);

		return $eSeries;

	}

	private function getBeds(\map\Plot $ePlot): array {

		$cBed = \map\Bed::model()
			->select(['id', 'name'])
			->wherePlot($ePlot)
			->wherePlotFill(FALSE)
			->getCollection()
			->sort('name', natural: TRUE);

		$beds = [];

		foreach($cBed as $eBed) {
			$beds[] = $eBed;
		}

		return $beds;

	}

	private function getLengths(Series $eSeries): array {

		$lengths = [];

		foreach(Place::model()->select(['bed', 'length'])->whereSeries($eSeries)->getCollection() as $ePlace) {
			$lengths[$ePlace['bed']['id']] = $ePlace['length'];
		}

		return $this->sortLengths($lengths);

	}

	/**
	 * Les longueurs rangées par id de planche, des deux côtés de la comparaison : le rang d'une
	 * planche suit son nom, tiré au hasard par le décor, et non son id.
	 */
	private function sortLengths(array $lengths): array {

		ksort($lengths);

		return $lengths;

	}

}
?>
