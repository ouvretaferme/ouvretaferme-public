<?php
namespace map;

/**
 * Un jardin ou une parcelle modifiés dans leur panneau, ouvert depuis l'onglet Plan comme depuis la vue Carte : `plot:doUpdate`
 * et `zone:doUpdate` y construisent PlotLib::getPropertiesUpdate() et ZoneLib::getPropertiesUpdate(NULL, $e). Le panneau d'un
 * jardin poste son contour et ses dimensions, celui d'une parcelle n'a pas de carte : la surface d'une parcelle dessinée vient
 * de son contour.
 *
 * Le décor est posé par les Lib dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=PlotUpdateMapAi
 */
class PlotUpdateMapAiTest extends \dev\ApiTest {

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

	private function createZone(?array $coordinates): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle à modifier '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 40000,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui jugerait le nom, la surface et l'anneau : le décor est fixe, et les cas n'éprouvent que la modification
		ZoneLib::create($eZone);

		return $eZone;

	}

	private function createPlot(Zone $eZone, string $mode, ?array $coordinates): Plot {

		$ePlot = new Plot([
			'zone' => $eZone,
			'structure' => $eZone['structure'],
			'name' => 'Jardin à modifier',
			'mode' => $mode,
			'area' => 30,
			'length' => ($mode === Plot::GREENHOUSE) ? 6.0 : NULL,
			'width' => ($mode === Plot::GREENHOUSE) ? 5.0 : NULL,
			'coordinates' => $coordinates,
			'decorGreenhouse' => Plot::model()->getDefaultValue('decorGreenhouse'),
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), pour la même raison que la parcelle
		PlotLib::create($ePlot);

		return $ePlot;

	}

	/** Ce que fait `plot:doUpdate` : l'élément relu, puis la liste du panneau sur ce qu'il a posté. */
	private function updatePlot(Plot $ePlot, array $input): Plot {

		$ePlotUpdate = Plot::model()
			->select(Plot::getSelection())
			->whereId($ePlot['id'])
			->get();

		$ePlotUpdate->build(PlotLib::getPropertiesUpdate(), $input, new \PropertyList('update'));

		return $ePlotUpdate;

	}

	private function getStored(Plot $ePlot): Plot {

		return Plot::model()
			->select(['name', 'mode', 'area', 'length', 'width', 'coordinates', 'seasonFirst', 'seasonLast'])
			->whereId($ePlot['id'])
			->get();

	}

	/** Le panneau d'un abri tracé reposte son contour : renommé, il tire ses dimensions du contour quelles que soient celles postées, et sa surface les suit. */
	public function testGreenhouseKeepsItsShapeAndDimensions(): void {

		$ePlot = $this->createPlot($this->createZone(self::SQUARE), Plot::GREENHOUSE, self::RECTANGLE);

		$fw = new \FailWatch();

		$ePlotUpdate = $this->updatePlot($ePlot, [
			'name' => 'Abri renommé',
			'length' => '6',
			'width' => '5',
			'coordinates' => json_encode(self::RECTANGLE),
			'decorGreenhouse' => Plot::model()->getDefaultValue('decorGreenhouse'),
			'seasonFirst' => '',
			'seasonLast' => ''
		]);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		PlotLib::update($ePlotUpdate, PlotLib::getPropertiesUpdate());

		$ePlotStored = $this->getStored($ePlot);

		$this->assertEquals('Abri renommé', $ePlotStored['name']);
		$dimensions = MapLib::getDimensions(self::RECTANGLE);

		$this->assertEquals($dimensions[0], $ePlotStored['length'], 'les dimensions postées sont remplacées par celles du contour');
		$this->assertEquals($dimensions[1], $ePlotStored['width']);
		$this->assertEquals((int)round($dimensions[0] * $dimensions[1]), $ePlotStored['area'], 'la surface d\'un abri vaut ses dimensions');
		$this->assertEquals(5, count($ePlotStored['coordinates']), 'le contour reste celui de la base');

	}

	/** Un abri retracé dans le panneau prend les dimensions de son nouveau contour, quelles que soient celles postées. */
	public function testRedrawnGreenhouseTakesTheDimensionsOfItsNewShape(): void {

		$ePlot = $this->createPlot($this->createZone(self::SQUARE), Plot::GREENHOUSE, self::RECTANGLE);

		// Le même abri tourné d'un quart de tour, refermé comme la séance l'écrit
		$redrawn = [[3.1000, 45.6000], [3.1001, 45.6000], [3.1001, 45.6003], [3.1000, 45.6003], [3.1000, 45.6000]];

		$fw = new \FailWatch();

		$ePlotUpdate = $this->updatePlot($ePlot, [
			'name' => 'Abri retracé',
			'length' => '50',
			'width' => '2',
			'coordinates' => json_encode($redrawn),
			'decorGreenhouse' => Plot::model()->getDefaultValue('decorGreenhouse'),
			'seasonFirst' => '',
			'seasonLast' => ''
		]);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		PlotLib::update($ePlotUpdate, PlotLib::getPropertiesUpdate());

		$ePlotStored = $this->getStored($ePlot);
		$dimensions = MapLib::getDimensions($redrawn);

		$this->assertEquals($redrawn, $ePlotStored['coordinates'], 'le nouveau contour est enregistré');
		$this->assertEquals($dimensions, [$ePlotStored['length'], $ePlotStored['width']], 'avec ses dimensions, et non celles postées');
		$this->assertEquals((int)round($dimensions[0] * $dimensions[1]), $ePlotStored['area'], 'que sa surface suit');

	}

	/** La page ouvre en rectangle un abri dont le contour en est un, sur ses quatre sommets, et tel qu'il est tout autre contour. */
	public function testOnlyARectangleRingOpensAsARectangle(): void {

		$ring = [...self::RECTANGLE, self::RECTANGLE[0]];

		$this->assertTrue(MapLib::isRectangleRing($ring), 'un rectangle refermé');
		$this->assertEquals(self::RECTANGLE, MapLib::getVertices($ring), 's\'ouvre sur ses quatre sommets');
		$this->assertTrue(MapLib::isRectangleRing(self::RECTANGLE), 'un rectangle laissé ouvert');

		// Le rectangle de 30 m sur 10 tourné de 30° autour de son centre, sur le plan en mètres de MapLib
		$turn = M_PI / 6;
		$scale = MapSetting::PLANE_METERS * cos(deg2rad(45.6));
		$turned = array_map(fn(array $corner) => [
			3.1 + ($corner[0] * 15 * cos($turn) - $corner[1] * 5 * sin($turn)) / $scale,
			45.6 + ($corner[0] * 15 * sin($turn) + $corner[1] * 5 * cos($turn)) / MapSetting::PLANE_METERS
		], [[-1, -1], [1, -1], [1, 1], [-1, 1], [-1, -1]]);

		$this->assertTrue(MapLib::isRectangleRing($turned), 'un rectangle tourné');

		$parallelogram = [[3.1000, 45.6000], [3.1003, 45.6000], [3.1004, 45.6001], [3.1001, 45.6001], [3.1000, 45.6000]];

		$this->assertFalse(MapLib::isRectangleRing($parallelogram), 'un parallélogramme garde sa forme');

	}

	/** Un jardin sans contour se donne une surface dans le panneau, que rien d'autre ne lui donnerait. */
	public function testGardenWithoutShapeTakesItsArea(): void {

		$ePlot = $this->createPlot($this->createZone(NULL), Plot::OPEN_FIELD, NULL);

		$fw = new \FailWatch();

		$ePlotUpdate = $this->updatePlot($ePlot, ['name' => 'Jardin sans contour', 'area' => '120', 'seasonFirst' => '', 'seasonLast' => '']);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		PlotLib::update($ePlotUpdate, PlotLib::getPropertiesUpdate());

		$this->assertEquals(120, $this->getStored($ePlot)['area']);

	}

	/** Un nom vidé et des saisons à l'envers sont refusés, avec les messages de Alert.u.php. */
	public function testInconsistentInputIsRefused(): void {

		$ePlot = $this->createPlot($this->createZone(self::SQUARE), Plot::OPEN_FIELD, self::RECTANGLE);

		$fw = new \FailWatch();

		$this->updatePlot($ePlot, ['name' => '', 'seasonFirst' => '2027', 'seasonLast' => '2025']);

		$this->assertTrue($fw->has('Plot::name.check'), json_encode($fw->get()));
		$this->assertTrue($fw->has('Plot::seasonLast.consistency'), json_encode($fw->get()));
		$this->assertTrue(AlertUi::getError('Plot::name.check') !== NULL);
		$this->assertTrue(AlertUi::getError('Plot::seasonLast.consistency') !== NULL);

	}

	/** Une parcelle tracée ne se donne pas de surface dans le panneau, son contour la porte. */
	public function testZoneListFollowsItsShape(): void {

		$eZoneDrawn = $this->createZone(self::SQUARE);
		$eZoneUndrawn = $this->createZone(NULL);

		$this->assertEquals(['name', 'seasonFirst', 'seasonLast'], ZoneLib::getPropertiesUpdate(NULL, $eZoneDrawn));
		$this->assertEquals(['name', 'area', 'seasonFirst', 'seasonLast'], ZoneLib::getPropertiesUpdate(NULL, $eZoneUndrawn));

		$fw = new \FailWatch();

		$eZoneDrawn->build(ZoneLib::getPropertiesUpdate(NULL, $eZoneDrawn), ['name' => 'Parcelle renommée', 'area' => '12', 'seasonFirst' => '', 'seasonLast' => ''], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		ZoneLib::update($eZoneDrawn, ZoneLib::getPropertiesUpdate(NULL, $eZoneDrawn));

		$eZoneStored = Zone::model()
			->select(['name', 'area', 'coordinates'])
			->whereId($eZoneDrawn['id'])
			->get();

		$this->assertEquals('Parcelle renommée', $eZoneStored['name']);
		$this->assertEquals(40000, $eZoneStored['area'], 'une surface postée à côté du contour ne le contredit pas');
		$this->assertEquals(5, count($eZoneStored['coordinates']));

	}

}
?>
