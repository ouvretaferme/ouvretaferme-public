<?php
namespace map;

/**
 * Un jardin, un abri ou une parcelle créés en dessinant sur la vue Carte. `plot:doCreate` construit
 * PlotLib::getPropertiesCreate() sur ce que poste MapDrawing : le nom du bloc, la forme, sa surface, et
 * les dimensions d'un abri. `zone:doCreate` construit ZoneLib::getPropertiesCreate('map'), contour compris.
 * Ce que la carte propose d'office (MapLib::putPlotDefault()) se vérifie sur les mêmes parcelles.
 *
 * Le décor est posé par les Lib dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=PlotCreateMapAi
 */
class PlotCreateMapAiTest extends \dev\ApiTest {

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010]];
	const array RECTANGLE = [[3.1000, 45.6000], [3.1003, 45.6000], [3.1003, 45.6001], [3.1000, 45.6001]];

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createZone(?int $seasonFirst = NULL): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle où créer '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 40000,
			'coordinates' => self::SQUARE,
			'seasonFirst' => $seasonFirst,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui jugerait le nom, la surface et l'anneau : le décor est fixe, et les cas n'éprouvent que la création du jardin
		ZoneLib::create($eZone);

		return $eZone;

	}

	/** Ce que fait `plot:doCreate` : l'élément de getCreateElement(), puis la liste du web sur ce qu'a posté la carte. */
	private function createPlot(Zone $eZone, string $mode, array $input, \FailWatch $fw): Plot {

		$ePlot = new Plot([
			'zone' => $eZone,
			'structure' => $eZone['structure'],
			'mode' => $mode,
			'decorGreenhouse' => Plot::model()->getDefaultValue('decorGreenhouse')
		]);

		// Le bloc poste le mode avec le nom, comme le panneau
		$ePlot->build(PlotLib::getPropertiesCreate(), $input + ['mode' => $mode], new \PropertyList('create'));

		if($fw->ok()) {
			PlotLib::create($ePlot);
		}

		return $ePlot;

	}

	private function getStored(Plot $ePlot): Plot {

		return Plot::model()
			->select(['id', 'name', 'mode', 'area', 'length', 'width', 'coordinates', 'seasonFirst', 'seasonLast'])
			->whereId($ePlot['id'])
			->get();

	}

	/** Un jardin poste son nom, sa forme et sa surface : il est créé avec son contour, sans borne de saison. */
	public function testGardenIsCreatedWithItsShape(): void {

		$eZone = $this->createZone(2024);

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($eZone, Plot::OPEN_FIELD, [
			'name' => 'Jardin 3',
			'coordinates' => json_encode(self::RECTANGLE),
			'area' => '255'
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$ePlotStored = $this->getStored($ePlot);

		$this->assertEquals('Jardin 3', $ePlotStored['name']);
		$this->assertEquals(MapLib::getArea(self::RECTANGLE), $ePlotStored['area'], 'la surface est celle du contour, pas celle postée');
		$this->assertEquals(5, count($ePlotStored['coordinates']), 'l\'anneau est refermé à l\'écriture');
		$this->assertEquals(NULL, $ePlotStored['length']);
		$this->assertEquals(NULL, $ePlotStored['seasonFirst'], 'le jardin suit les saisons de sa parcelle');
		$this->assertEquals(NULL, $ePlotStored['seasonLast']);

	}

	/** Un abri tracé tire ses dimensions de son contour, quelles que soient celles postées, et elles font sa surface. */
	public function testGreenhouseAreaComesFromItsDimensions(): void {

		$eZone = $this->createZone();

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($eZone, Plot::GREENHOUSE, [
			'name' => 'Abri 1',
			'coordinates' => json_encode(self::RECTANGLE),
			'area' => '250',
			'length' => '30',
			'width' => '8'
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$ePlotStored = $this->getStored($ePlot);

		$dimensions = MapLib::getDimensions(self::RECTANGLE);

		$this->assertEquals(Plot::GREENHOUSE, $ePlotStored['mode']);
		$this->assertEquals($dimensions[0], $ePlotStored['length']);
		$this->assertEquals($dimensions[1], $ePlotStored['width']);
		$this->assertEquals((int)round($dimensions[0] * $dimensions[1]), $ePlotStored['area']);

	}

	/** Un nom vidé dans le bloc est refusé avant toute écriture, avec le message de Alert.u.php. */
	public function testEmptyNameIsRefused(): void {

		$eZone = $this->createZone();

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($eZone, Plot::OPEN_FIELD, [
			'name' => '',
			'coordinates' => json_encode(self::RECTANGLE),
			'area' => '255'
		], $fw);

		$this->assertTrue($fw->has('Plot::name.check'), json_encode($fw->get()));
		$this->assertTrue($ePlot->offsetExists('id') === FALSE, 'rien n\'est écrit');
		$this->assertTrue(AlertUi::getError('Plot::name.check') !== NULL, 'le refus a son message');

	}

	/** Un contour illisible est refusé comme sur la route du tracé. */
	public function testBrokenShapeIsRefused(): void {

		$eZone = $this->createZone();

		$fw = new \FailWatch();

		$this->createPlot($eZone, Plot::OPEN_FIELD, [
			'name' => 'Jardin 4',
			'coordinates' => json_encode(array_slice(self::RECTANGLE, 0, 2)),
			'area' => '255'
		], $fw);

		$this->assertTrue($fw->has('Plot::coordinates.ring') or $fw->has('Plot::coordinates.check'), json_encode($fw->get()));

	}

	/** Une parcelle dessinée sur la carte part avec son contour, sans saison de début : la Carte n'en propose pas. */
	public function testZoneIsCreatedWithItsShape(): void {

		$this->assertEquals(['name', 'coordinates', 'area'], ZoneLib::getPropertiesCreate('map'));

		$eZone = new Zone([
			'structure' => $this->eStructure
		]);

		$fw = new \FailWatch();

		$eZone->build(ZoneLib::getPropertiesCreate('map'), [
			'name' => 'Parcelle dessinée',
			'area' => '1600',
			'coordinates' => json_encode(self::SQUARE)
		], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		ZoneLib::create($eZone);

		$eZoneStored = Zone::model()
			->select(['area', 'coordinates', 'seasonFirst'])
			->whereId($eZone['id'])
			->get();

		$this->assertEquals(MapLib::getArea(self::SQUARE), $eZoneStored['area'], 'la surface est celle du contour, pas celle postée');
		$this->assertEquals(5, count($eZoneStored['coordinates']));
		$this->assertEquals(NULL, $eZoneStored['seasonFirst']);

	}

	/**
	 * Le nom proposé suit les jardins ou les abris de la parcelle, chacun compté dans la sienne.
	 */
	public function testTheProposedNameFollowsTheZone(): void {

		$eZone = $this->createZone();
		$eZoneOther = $this->createZone();

		$cZone = new \Collection([$eZone['id'] => $eZone, $eZoneOther['id'] => $eZoneOther]);

		PlotLib::putFromZone($cZone);
		MapLib::putPlotDefault($cZone);

		// La parcelle neuve n'a que le jardin qui la remplit, que la carte ne compte pas
		$this->assertEquals([Plot::OPEN_FIELD => 1, Plot::GREENHOUSE => 1], $eZone['plotDefault']);

		$fw = new \FailWatch();

		foreach([3.10015, 3.1003, 3.1006] as $position => $longitude) {
			$coordinates = [[3.1000, 45.6000], [$longitude, 45.6000], [$longitude, 45.6001], [3.1000, 45.6001]];
			$this->createPlot($eZone, Plot::OPEN_FIELD, ['name' => 'Jardin '.$position, 'coordinates' => json_encode($coordinates), 'area' => '0'], $fw);
		}

		$this->createPlot($eZoneOther, Plot::GREENHOUSE, ['name' => 'Abri', 'coordinates' => json_encode(self::RECTANGLE), 'area' => '0', 'length' => '24', 'width' => '8.2'], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$cZone = new \Collection([$eZone['id'] => $eZone, $eZoneOther['id'] => $eZoneOther]);

		PlotLib::putFromZone($cZone);
		MapLib::putPlotDefault($cZone);

		$this->assertEquals([Plot::OPEN_FIELD => 4, Plot::GREENHOUSE => 1], $eZone['plotDefault']);
		$this->assertEquals([Plot::OPEN_FIELD => 1, Plot::GREENHOUSE => 2], $eZoneOther['plotDefault']);

	}

}
?>
