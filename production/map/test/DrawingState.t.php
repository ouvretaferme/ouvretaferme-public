<?php
namespace map;

/**
 * Ce qui reste à tracer dans une parcelle (`MapLib::putDrawingState()`) et le jardin par lequel son
 * pas à pas continue sur l'onglet Carte : une parcelle sans forme, un jardin sans forme, des planches
 * tracées en partie, et le tracé d'une saison antérieure resté incomplet.
 *
 * Le décor est monté par les Lib dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 * Les saisons sont fixes : le cas ne lit que l'écart entre la saison vue et celle du tracé.
 *
 * Lancement : dev/test package=map file=DrawingState
 */
class DrawingStateTest extends \dev\ApiTest {

	const int SEASON = 2026;

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createZone(bool $drawn): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle à tracer '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => $drawn ? self::SQUARE : NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : le carré est fixe, et le cas ne lit que sa présence
		ZoneLib::create($eZone);

		return $eZone;

	}

	private function createPlot(Zone $eZone, bool $drawn, string $mode = Plot::OPEN_FIELD): Plot {

		$ePlot = new Plot([
			'name' => 'Jardin à tracer '.uniqid(),
			'zone' => $eZone,
			'mode' => $mode,
			'area' => 100,
			'coordinates' => $drawn ? self::SQUARE : NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone tient dans sa parcelle : le cas ne lit que sa présence
		PlotLib::create($ePlot);

		return $ePlot;

	}

	private function getPlotFill(Zone $eZone): Plot {

		$ePlot = Plot::model()
			->select(['id', 'structure'])
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();

		$ePlot['zone'] = $eZone;

		return $ePlot;

	}

	private function createBeds(Plot $ePlot, int $number): \Collection {

		$cBed = new \Collection();

		for($position = 1; $position <= $number; $position++) {

			$eBed = new Bed([
				'name' => 'P'.$position,
				'plot' => $ePlot,
				'zoneFill' => FALSE,
				'plotFill' => FALSE,
				'length' => 20,
				'width' => 80,
				'alleyWidth' => NULL,
				'seasonFirst' => NULL,
				'seasonLast' => NULL,
			]);

			// Sans build(), qui vérifierait les bornes des dimensions postées : elles sont fixes et le cas ne les lit pas
			BedLib::create($eBed);

			$cBed[$eBed['id']] = $eBed;

		}

		return $cBed;

	}

	private function draw(Plot $ePlot, \Collection $cBed, int $season): void {

		$eDraw = new Draw([
			'plot' => $ePlot,
			'season' => $season,
		]);

		// Sans build(), qui vérifierait la saison postée : elle est celle que le cas veut, antérieure comprise
		DrawLib::createByBeds($eDraw, $this->getPlaces($cBed, [3.0995, 45.5995]));

	}

	/**
	 * La place de chaque planche de `$cBed`, sous son id, dans un rang qui part de `$start` vers l'est : les planches côte à côte
	 * dans l'ordre de la collection, séparées par un passe-pied de 60 cm et longues vers le nord.
	 */
	private function getPlaces(\Collection $cBed, array $start): \Collection {

		$cDraw = new \Collection();
		$position = 0;

		foreach($cBed as $eBed) {

			$position += $eBed['width'] / 100;

			// Large vers l'ouest depuis son bout est, une planche s'allonge vers le nord, à cap + 90°
			$cDraw[$eBed['id']] = new Draw([
				'bed' => $eBed
			] + DrawLib::getCanonical(MapLib::getDestination($start, $position, 90), 270, $eBed));

			$position += 0.6;

		}

		return $cDraw;

	}

	/** La parcelle telle que la page de la carte la charge, son état de tracé posé. */
	private function getState(Zone $eZone): Zone {

		$eZoneLoaded = ZoneLib::getByStructure($this->eStructure, $eZone['id'], season: self::SEASON);

		$cZone = new \Collection([$eZoneLoaded]);

		PlotLib::putFromZone($cZone, withBeds: TRUE, withDraw: TRUE, season: self::SEASON);
		MapLib::putDrawingState($cZone);

		return $eZoneLoaded;

	}

	private function findPlot(Zone $eZoneLoaded, Plot $ePlot): Plot {

		return $eZoneLoaded['cPlot']->find(fn($ePlotLoaded) => $ePlotLoaded['id'] === $ePlot['id'], limit: 1, clone: FALSE);

	}

	public function testZoneWithoutShape(): void {

		$eZone = $this->createZone(drawn: FALSE);
		$ePlot = $this->createPlot($eZone, drawn: FALSE);
		$this->createBeds($ePlot, 2);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(2, $eZoneLoaded['remaining'], 'la forme de la parcelle se compte avec celle de son jardin');
		$this->assertTrue($eZoneLoaded['ePlotNext']->empty(), 'rien ne se trace dans une parcelle sans forme, le pas à pas commence par elle');

	}

	public function testZoneWithoutPlot(): void {

		$eZoneLoaded = $this->getState($this->createZone(drawn: TRUE));

		$this->assertEquals(0, $eZoneLoaded['remaining'], 'une parcelle tracée sans jardin ni planche n\'a plus rien à tracer');
		$this->assertTrue($eZoneLoaded['ePlotNext']->empty());

	}

	public function testPlotWithoutShape(): void {

		$eZone = $this->createZone(drawn: TRUE);
		$ePlot = $this->createPlot($eZone, drawn: FALSE);
		$this->createBeds($ePlot, 3);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(1, $eZoneLoaded['remaining'], 'le jardin sans forme compte pour un, pas pour ses planches');
		$this->assertEquals($ePlot['id'], $eZoneLoaded['ePlotNext']['id']);

	}

	public function testPlotWithoutBed(): void {

		$eZone = $this->createZone(drawn: TRUE);
		$ePlot = $this->createPlot($eZone, drawn: TRUE);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(0, $this->findPlot($eZoneLoaded, $ePlot)['bedsUndrawn'], 'la planche qui remplit le jardin ne se trace pas');
		$this->assertEquals(0, $eZoneLoaded['remaining'], 'un jardin tracé sans planche n\'a plus rien à tracer');
		$this->assertTrue($eZoneLoaded['ePlotNext']->empty());

	}

	public function testBedsPartlyDrawn(): void {

		$eZone = $this->createZone(drawn: TRUE);
		$ePlot = $this->createPlot($eZone, drawn: TRUE);
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P3'), self::SEASON);

		$eZoneLoaded = $this->getState($eZone);
		$ePlotLoaded = $this->findPlot($eZoneLoaded, $ePlot);

		$this->assertEquals(1, $ePlotLoaded['bedsUndrawn'], 'une planche sur trois reste à tracer');
		$this->assertEquals(3, $ePlotLoaded['beds'], 'le geste retrace toutes les planches du jardin, même s\'il n\'en nomme qu\'une');
		$this->assertEquals(1, $eZoneLoaded['remaining']);
		$this->assertEquals($ePlot['id'], $eZoneLoaded['ePlotNext']['id'], 'un jardin tracé en partie reste à l\'étape des planches');

	}

	public function testPlotsUndrawnByMode(): void {

		$eZone = $this->createZone(drawn: TRUE);

		$this->createPlot($eZone, drawn: FALSE);
		$this->createPlot($eZone, drawn: FALSE);
		$this->createPlot($eZone, drawn: FALSE, mode: Plot::GREENHOUSE);
		$this->createPlot($eZone, drawn: TRUE, mode: Plot::GREENHOUSE);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals([Plot::OPEN_FIELD => 2, Plot::GREENHOUSE => 1], $eZoneLoaded['plotsUndrawn'], 'les formes à tracer se comptent par mode, un abri tracé n\'y entre pas');
		$this->assertEquals(3, $eZoneLoaded['remaining']);

	}

	public function testBedsUndrawnAreSummedOverDrawablePlots(): void {

		$eZone = $this->createZone(drawn: TRUE);

		$this->createBeds($this->getPlotFill($eZone), 1);

		$ePlotDrawn = $this->createPlot($eZone, drawn: TRUE);
		$cBed = $this->createBeds($ePlotDrawn, 3);
		$this->draw($ePlotDrawn, $cBed->find(fn($eBed) => $eBed['name'] === 'P1'), self::SEASON);

		// Ses planches attendent sa forme : elles ne s'ajoutent pas à ce qui peut se tracer maintenant
		$this->createBeds($this->createPlot($eZone, drawn: FALSE), 5);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(3, $eZoneLoaded['bedsUndrawn'], 'une planche à même la parcelle et deux du jardin tracé');
		$this->assertEquals([Plot::OPEN_FIELD => 1, Plot::GREENHOUSE => 0], $eZoneLoaded['plotsUndrawn']);

	}

	public function testBedsUndrawnAreListedOnEachPlot(): void {

		$eZone = $this->createZone(drawn: TRUE);

		$ePlotOne = $this->createPlot($eZone, drawn: TRUE);
		$cBedOne = $this->createBeds($ePlotOne, 3);
		$this->draw($ePlotOne, $cBedOne->find(fn($eBed) => $eBed['name'] !== 'P2'), self::SEASON);

		$ePlotThree = $this->createPlot($eZone, drawn: TRUE);
		$this->createBeds($ePlotThree, 3);

		$ePlotNone = $this->createPlot($eZone, drawn: TRUE);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(['P2'], $this->findPlot($eZoneLoaded, $ePlotOne)['cBedUndrawn']->getColumn('name'), 'la seule planche sans tracé, que le bouton nomme');
		$this->assertEquals(['P1', 'P2', 'P3'], $this->findPlot($eZoneLoaded, $ePlotThree)['cBedUndrawn']->getColumn('name'), 'dans l\'ordre de la liste');
		$this->assertEquals(0, $this->findPlot($eZoneLoaded, $ePlotNone)['cBedUndrawn']->count(), 'un jardin sans planche porte une collection vide, lue sans garde');
		$this->assertEquals(4, $eZoneLoaded['bedsUndrawn']);

	}

	public function testZoneBeds(): void {

		$eZone = $this->createZone(drawn: TRUE);
		$ePlotFill = $this->getPlotFill($eZone);
		$this->createBeds($ePlotFill, 2);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(1, $eZoneLoaded['remaining'], 'les planches posées à même la parcelle comptent pour un, comme celles d\'un jardin');
		$this->assertEquals($ePlotFill['id'], $eZoneLoaded['ePlotNext']['id'], 'elles n\'attendent la forme d\'aucun jardin');

	}

	public function testShapesComeBeforeBeds(): void {

		$eZone = $this->createZone(drawn: TRUE);

		// Les planches à même la parcelle ouvrent la liste, devant tous les jardins
		$this->createBeds($this->getPlotFill($eZone), 1);

		$ePlotBeds = $this->createPlot($eZone, drawn: TRUE);
		$this->createBeds($ePlotBeds, 2);

		$this->createPlot($eZone, drawn: FALSE);
		$this->createPlot($eZone, drawn: FALSE);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(4, $eZoneLoaded['remaining']);
		$this->assertTrue($eZoneLoaded['cPlot']->first()['zoneFill'], 'les planches à même la parcelle sont en tête de liste');
		$this->assertEquals($eZoneLoaded['cPlot']->find(fn($ePlot) => $ePlot['zoneFill'] === FALSE and $ePlot['coordinates'] === NULL, limit: 1, clone: FALSE)['id'], $eZoneLoaded['ePlotNext']['id'], 'toutes les formes se tracent avant les premières planches, dans l\'ordre de la liste');

	}

	public function testBedsFollowTheList(): void {

		$eZone = $this->createZone(drawn: TRUE);

		$ePlotFirst = $this->createPlot($eZone, drawn: TRUE);
		$this->createBeds($ePlotFirst, 2);

		$ePlotSecond = $this->createPlot($eZone, drawn: TRUE);
		$this->createBeds($ePlotSecond, 2);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(2, $eZoneLoaded['remaining']);
		$this->assertEquals($eZoneLoaded['cPlot']->find(fn($ePlot) => $ePlot['bedsUndrawn'] > 0, limit: 1, clone: FALSE)['id'], $eZoneLoaded['ePlotNext']['id'], 'à étape égale, le premier jardin de la liste');

	}

	public function testEarlierSeasonIncompleteIsRedrawnWhole(): void {

		$eZone = $this->createZone(drawn: TRUE);
		$ePlot = $this->createPlot($eZone, drawn: TRUE);
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P3'), self::SEASON - 1);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(1, $this->findPlot($eZoneLoaded, $ePlot)['bedsUndrawn'], 'le tracé de la saison précédente vaut encore, il lui manque une planche');

		// Le geste de la liste : toutes les planches du jardin, tracées dans la saison vue
		$this->draw($ePlot, $cBed, self::SEASON);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(0, $this->findPlot($eZoneLoaded, $ePlot)['bedsUndrawn'], 'aucune planche ne reste à tracer');
		$this->assertEquals(0, $eZoneLoaded['remaining']);
		$this->assertTrue($eZoneLoaded['ePlotNext']->empty(), 'la parcelle finie n\'ouvre plus de pas à pas');

	}

	public function testEarlierSeasonCompletedByMissingBedsKeepsTheOthers(): void {

		$eZone = $this->createZone(drawn: TRUE);
		$ePlot = $this->createPlot($eZone, drawn: TRUE);
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P3'), self::SEASON - 1);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] === 'P3'), self::SEASON);

		$eZoneLoaded = $this->getState($eZone);

		$this->assertEquals(0, $this->findPlot($eZoneLoaded, $ePlot)['bedsUndrawn'], 'tracer la seule planche manquante recopie d\'abord le tracé hérité dans la saison : les deux autres restent tracées');

	}

	public function testEachPlotOfTheMapListSaysWhatItStillNeeds(): void {

		$eZone = $this->createZone(drawn: TRUE);

		$ePlotOne = $this->createPlot($eZone, drawn: TRUE);
		$cBedOne = $this->createBeds($ePlotOne, 3);
		$this->draw($ePlotOne, $cBedOne->find(fn($eBed) => $eBed['name'] !== 'P2'), self::SEASON);

		$ePlotThree = $this->createPlot($eZone, drawn: TRUE);
		$this->createBeds($ePlotThree, 3);

		$ePlotDone = $this->createPlot($eZone, drawn: TRUE);
		$cBedDone = $this->createBeds($ePlotDone, 2);
		$this->draw($ePlotDone, $cBedDone, self::SEASON);

		$ePlotShapeless = $this->createPlot($eZone, drawn: FALSE);
		$this->createBeds($ePlotShapeless, 2);

		// Les planches posées à même la parcelle n'ont pas de forme à elles : elles ont celle de la parcelle, qui est tracée
		$ePlotZone = Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();
		$this->createBeds($ePlotZone, 2);

		$eZoneLoaded = $this->getState($eZone);

		$getRemaining = new \ReflectionMethod(PlanUi::class, 'getMapPlotRemaining');
		$read = fn(Plot $ePlot) => strip_tags($getRemaining->invoke(new PlanUi(), $this->findPlot($eZoneLoaded, $ePlot)));

		$this->assertEquals('1 planche à dessiner', $read($ePlotOne), 'la planche qui reste au jardin');
		$this->assertEquals('3 planches à dessiner', $read($ePlotThree));
		$this->assertEquals('', $read($ePlotDone), 'un jardin fini ne dit rien');
		$this->assertEquals('à dessiner', $read($ePlotShapeless), 'un jardin sans forme se dessine avant ses planches, qui ne sont pas comptées');
		$this->assertEquals('2 planches à dessiner', $read($ePlotZone), 'les planches posées à même une parcelle tracée se comptent, sans forme à dessiner avant elles');

		$this->assertEquals(6, $eZoneLoaded['bedsUndrawn'], 'les comptes des jardins font celui que le badge de la parcelle annonce');

	}

}
?>
