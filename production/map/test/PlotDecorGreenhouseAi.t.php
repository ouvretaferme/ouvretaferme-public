<?php
namespace map;

/**
 * Le modèle d'un abri (`Plot.decorGreenhouse`), par les chemins d'écriture du produit : la création
 * d'une parcelle et de son jardin, le formulaire d'un jardin ou d'un abri, la bascule couvrir /
 * découvrir, la duplication, l'API qui ne l'expose pas et la scène 3D qui le lit.
 *
 * Le décor est une parcelle posée par `ZoneLib::create()` dans la transaction de la méthode, sur la
 * structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=PlotDecorGreenhouseAi
 */
class PlotDecorGreenhouseAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createZone(): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle des abris '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		ZoneLib::create($eZone);

		return $eZone;

	}

	/** Le jardin tel que le formulaire de création le poste, construit et enregistré comme le fait `doCreate`. */
	private function createPlot(Zone $eZone, array $input, \FailWatch $fw): Plot {

		$ePlot = new Plot([
			'zone' => $eZone,
			'structure' => $this->eStructure,
			'mode' => $input['mode']
		]);

		$ePlot->build(PlotLib::getPropertiesCreate(), $input + [
			'name' => 'Jardin '.uniqid(),
			'area' => 300,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		], new \PropertyList('create'));

		if($fw->ok()) {
			PlotLib::create($ePlot);
		}

		return $ePlot;

	}

	private function getDecorGreenhouse(Plot $ePlot): ?string {

		return Plot::model()
			->select('decorGreenhouse')
			->whereId($ePlot['id'])
			->getValue('decorGreenhouse');

	}

	public function testZoneFillPlotHasNoType(): void {

		$eZone = $this->createZone();

		$ePlotFill = Plot::model()
			->select('id')
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();

		if($this->assertDecorPresent($ePlotFill->notEmpty(), 'ZoneLib::create() pose le jardin qui remplit la parcelle') === FALSE) {
			return;
		}

		$this->assertEquals(NULL, $this->getDecorGreenhouse($ePlotFill), 'createFromZone() prend le défaut du schéma');

	}

	public function testGreenhouseKeepsItsType(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => Plot::STRAIGHT,
			'length' => 30,
			'width' => 8
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(Plot::STRAIGHT, $this->getDecorGreenhouse($ePlot));

	}

	/** Le modèle est facultatif : un abri créé sans modèle n'en a pas, sans erreur. */
	public function testGreenhouseWithoutTypeHasNone(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => '',
			'length' => 30,
			'width' => 8
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(NULL, $this->getDecorGreenhouse($ePlot));

	}

	/** Une multichapelle de deux, trois ou quatre nefs s'enregistre et se relit telle quelle, ce que la colonne refuse tant que l'enum ne la porte pas. */
	public function testMultichapelKeepsItsType(): void {

		$eZone = $this->createZone();

		foreach([Plot::CHAPEL_2, Plot::CHAPEL_3, Plot::CHAPEL_4] as $decorGreenhouse) {

			$fw = new \FailWatch();

			$ePlot = $this->createPlot($eZone, [
				'mode' => Plot::GREENHOUSE,
				'decorGreenhouse' => $decorGreenhouse,
				'length' => 40,
				'width' => 9.6 * 3
			], $fw);

			$this->assertTrue($fw->ok(), $decorGreenhouse.' '.json_encode($fw->get()));
			$this->assertEquals($decorGreenhouse, $this->getDecorGreenhouse($ePlot));

		}

	}

	public function testUnknownTypeIsRefused(): void {

		$fw = new \FailWatch();

		$this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => 'dome',
			'length' => 30,
			'width' => 8
		], $fw);

		$this->assertTrue($fw->has('Plot::decorGreenhouse.check'), json_encode($fw->get()));

	}

	/** Le formulaire d'un jardin de plein champ n'affiche pas le modèle, et ne le poste donc pas. */
	public function testOpenFieldWithoutTypeHasNone(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::OPEN_FIELD
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(NULL, $this->getDecorGreenhouse($ePlot));

	}

	/** Sous abri, le formulaire poste toujours le modèle : vide, il retire celui qui était choisi. */
	public function testUpdateWithEmptyTypeClearsIt(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => Plot::GOTHIC,
			'length' => 30,
			'width' => 8
		], $fw);

		$ePlotUpdated = PlotLib::getById($ePlot['id']);

		$properties = ['name', 'length', 'width', 'area'];
		$ePlotUpdated->build(array_merge($properties, ['decorGreenhouse']), [
			'name' => 'Abri renommé',
			'decorGreenhouse' => '',
			'length' => 30,
			'width' => 8
		], new \PropertyList('update'));

		PlotLib::update($ePlotUpdated, array_merge($properties, ['decorGreenhouse']));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(NULL, $this->getDecorGreenhouse($ePlot));

	}

	/** Découvrir un abri puis le recouvrir, comme `doUpdateGreenhouse` : la bascule ne poste le modèle qu'en couvrant. */
	public function testUncoverThenCoverKeepsType(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => Plot::CHAPEL_2,
			'length' => 30,
			'width' => 8
		], $fw);

		$properties = ['mode', 'decorGreenhouse', 'length', 'width', 'area'];

		$ePlotUncovered = PlotLib::getById($ePlot['id']);
		$ePlotUncovered->build($properties, ['area' => 240], new \PropertyList('update'));
		PlotLib::update($ePlotUncovered, $properties);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(Plot::OPEN_FIELD, $ePlotUncovered['mode']);
		$this->assertEquals(Plot::CHAPEL_2, $this->getDecorGreenhouse($ePlot), 'le plein champ garde le modèle');

		$ePlotCovered = PlotLib::getById($ePlot['id']);
		$ePlotCovered->build($properties, ['decorGreenhouse' => Plot::CHAPEL_2, 'length' => 30, 'width' => 8], new \PropertyList('update'));
		PlotLib::update($ePlotCovered, $properties);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(Plot::GREENHOUSE, $ePlotCovered['mode']);
		$this->assertEquals(Plot::CHAPEL_2, $this->getDecorGreenhouse($ePlot), 'recouvert, le formulaire repropose son modèle');

	}

	public function testDuplicateCopiesType(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => Plot::ROUND,
			'length' => 30,
			'width' => 8
		], $fw);

		$ePlotDuplicated = PlotLib::duplicate(PlotLib::getById($ePlot['id']), new Plot(['name' => 'Abri copié']));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(Plot::ROUND, $this->getDecorGreenhouse($ePlotDuplicated));

	}

	/** Un décor de la scène 3D n'est exposé par l'API ni en lecture ni en écriture, et l'écriture d'un abri par l'API garde son modèle. */
	public function testApiIgnoresDecor(): void {

		$fw = new \FailWatch();

		$ePlot = $this->createPlot($this->createZone(), [
			'mode' => Plot::GREENHOUSE,
			'decorGreenhouse' => Plot::GOTHIC,
			'length' => 30,
			'width' => 8
		], $fw);

		$read = $this->callApi('GET', 'plot/{id}', get: ['id' => $ePlot['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertFalse(array_key_exists('decorGreenhouse', $read['body']), 'pas en lecture');

		$named = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['decorGreenhouse'],
			'decorGreenhouse' => Plot::ROUND
		], get: ['id' => $ePlot['id']]);

		$this->assertEquals(400, $named['status'], json_encode($named['body']));
		$this->assertEquals(Plot::GOTHIC, $this->getDecorGreenhouse($ePlot), 'pas en écriture');

		$partial = $this->callApi('PUT', 'plot/{id}', body: [
			'properties' => ['name'],
			'name' => 'Abri par l\'API'
		], get: ['id' => $ePlot['id']]);

		$this->assertEquals(200, $partial['status'], json_encode($partial['body']));
		$this->assertEquals(Plot::GOTHIC, $this->getDecorGreenhouse($ePlot), 'PUT partiel');

		$full = $this->callApi('PUT', 'plot/{id}', body: [
			'zone' => $read['body']['zone'],
			'name' => 'Abri par l\'API 2',
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		], get: ['id' => $ePlot['id']]);

		$this->assertEquals(200, $full['status'], json_encode($full['body']));
		$this->assertEquals(Plot::GOTHIC, $this->getDecorGreenhouse($ePlot), 'PUT complet sans le modèle');

	}

	public function testApiCreateHasNoType(): void {

		$create = $this->callApi('POST', 'plot', body: [
			'zone' => $this->createZone()['id'],
			'name' => 'Abri API',
			'mode' => Plot::GREENHOUSE,
			'length' => 30,
			'width' => 8,
			'area' => NULL,
			'coordinates' => NULL,
			'seasonFirst' => NULL
		]);

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertFalse(array_key_exists('decorGreenhouse', $create['body']));
		$this->assertEquals(NULL, $this->getDecorGreenhouse(new Plot(['id' => $create['body']['id']])));

	}

}
?>
