<?php
namespace map;

/**
 * « Modifier des planches » change les dimensions de plusieurs planches à la fois. Le formulaire s'ouvre sur les dimensions communes
 * (BedLib::getSizeCommon()), un champ laissé tel qu'il s'est ouvert garde la valeur de chaque planche, un passe-pied vidé se retire
 * (BedLib::getPropertiesUpdateSize()), et la surface de chaque planche se recalcule avec ses propres dimensions : changer la seule longueur de planches de largeurs différentes
 * ne leur donne pas la même surface.
 *
 * Le décor est monté par les Lib dans la transaction de la méthode, sur `FixtureAiLib::F1` : les tables du plan vivent dans la base
 * commune.
 *
 * Lancement : dev/test package=map file=BedSizeCollectionAi
 */
class BedSizeCollectionAiTest extends \dev\ApiTest {

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	public function testOnlyTheFilledDimensionChanges(): void {

		$cBed = $this->createBeds([80, 120]);

		$input = ['length' => '30', 'width' => '', 'alleyWidth' => '', 'changed' => ['length' => '1', 'width' => '0', 'alleyWidth' => '0']];

		$fw = new \FailWatch();

		$properties = BedLib::getPropertiesUpdateSize($input);

		$eBed = new Bed();
		$eBed->build($properties, $input);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(['length'], $properties);

		BedLib::updateCollection($cBed, $eBed, $properties);

		$cBedStored = Bed::model()
			->select(['length', 'width', 'area'])
			->whereId('IN', $cBed)
			->sort(['width' => SORT_ASC])
			->getCollection();

		$this->assertEquals([30, 30], array_values($cBedStored->getColumn('length')), 'la longueur remplie change sur chaque planche');
		$this->assertEquals([80, 120], array_values($cBedStored->getColumn('width')), 'la largeur laissée vide garde la valeur de chaque planche');
		$this->assertEquals([24.0, 36.0], array_values($cBedStored->getColumn('area')), 'la surface se calcule avec les dimensions de chaque planche, après le changement');

	}

	public function testNothingFilledIsRefused(): void {

		$fw = new \FailWatch();

		$properties = BedLib::getPropertiesUpdateSize(['length' => '20', 'width' => '80', 'alleyWidth' => '', 'changed' => ['length' => '0', 'width' => '0', 'alleyWidth' => '0']]);

		$this->assertEquals([], $properties);
		$this->assertTrue($fw->has('Bed::size.empty'), 'aucune dimension modifiée, rien à enregistrer');

	}

	public function testAnEmptiedLengthChangesNothing(): void {

		$fw = new \FailWatch();

		$properties = BedLib::getPropertiesUpdateSize(['length' => '', 'width' => '', 'alleyWidth' => '', 'changed' => ['length' => '1', 'width' => '1', 'alleyWidth' => '0']]);

		$this->assertEquals([], $properties, 'une planche ne se passe ni de longueur ni de largeur');
		$this->assertTrue($fw->has('Bed::size.empty'));

	}

	public function testAnEmptiedAlleyIsRemoved(): void {

		$cBed = $this->createBeds([80, 120], [40, 40]);

		$this->updateSize($cBed, ['length' => '20', 'width' => '', 'alleyWidth' => '', 'changed' => ['length' => '0', 'width' => '0', 'alleyWidth' => '1']], ['alleyWidth']);

		$this->assertEquals([NULL, NULL], $this->getStored($cBed, 'alleyWidth'), 'le passe-pied commun vidé se retire de chaque planche');
		$this->assertEquals([80, 120], $this->getStored($cBed, 'width'));

	}

	public function testAMixedAlleyLeftEmptyIsKept(): void {

		$cBed = $this->createBeds([80, 80], [NULL, 40]);

		$this->updateSize($cBed, ['length' => '25', 'width' => '80', 'alleyWidth' => '', 'changed' => ['length' => '1', 'width' => '0', 'alleyWidth' => '0']], ['length']);

		$this->assertEquals([NULL, 40], $this->getStored($cBed, 'alleyWidth'), 'un passe-pied « Plusieurs valeurs » laissé vide garde la valeur de chaque planche');
		$this->assertEquals([25, 25], $this->getStored($cBed, 'length'));

	}

	private function updateSize(\Collection $cBed, array $input, array $propertiesExpected): void {

		$fw = new \FailWatch();

		$properties = BedLib::getPropertiesUpdateSize($input);

		$eBed = new Bed();
		$eBed->build($properties, $input);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals($propertiesExpected, $properties);

		BedLib::updateCollection($cBed, $eBed, $properties);

	}

	private function getStored(\Collection $cBed, string $property): array {

		$cBedStored = Bed::model()
			->select([$property])
			->whereId('IN', $cBed)
			->sort(['id' => SORT_ASC])
			->getCollection();

		// getColumn() saute les valeurs nulles, qui sont ici ce que le cas éprouve
		$values = [];

		foreach($cBedStored as $eBedStored) {
			$values[] = $eBedStored[$property];
		}

		return $values;

	}

	public function testCommonDimensionsPrefillTheForm(): void {

		$eBedSize = BedLib::getSizeCommon($this->createBeds([80, 80]));

		$this->assertEquals([20, 80, NULL], [$eBedSize['length'], $eBedSize['width'], $eBedSize['alleyWidth']]);
		$this->assertEquals([], $eBedSize['propertiesMixed']);
		$this->assertEquals(16, $eBedSize['area'], 'la surface commune vient de la longueur et de la largeur communes');

	}

	public function testMixedDimensionsStayEmpty(): void {

		$eBedSize = BedLib::getSizeCommon($this->createBeds([80, 120]));

		$this->assertEquals(20, $eBedSize['length']);
		$this->assertEquals(NULL, $eBedSize['width']);
		$this->assertEquals(['width'], $eBedSize['propertiesMixed'], 'seule la largeur diffère, le passe-pied vide est commun');
		$this->assertEquals(0, $eBedSize['area'], 'sans largeur commune, pas de surface commune');

	}

	public function testAnEmptyDimensionOnOneBedIsMixed(): void {

		$eBedSize = BedLib::getSizeCommon($this->createBeds([80, 80], [NULL, 40]));

		$this->assertEquals(['alleyWidth'], $eBedSize['propertiesMixed'], 'un passe-pied vide sur une planche et rempli sur l\'autre n\'est pas commun');
		$this->assertEquals(NULL, $eBedSize['alleyWidth']);

	}

	public function testAFillBedIsRefused(): void {

		$cBed = $this->createBeds([80]);
		$eBed = $cBed->first();

		$eBedFill = Bed::model()
			->select(['id'])
			->wherePlot($eBed['plot'])
			->wherePlotFill(TRUE)
			->get();

		if($this->assertDecorPresent($eBedFill->notEmpty(), 'le jardin créé porte sa planche de remplissage') === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		BedLib::buildIds($eBed['plot'], [$eBed['id'], $eBedFill['id']]);

		$this->assertTrue($fw->has('Bed::ids.check'), 'la planche de remplissage, sans dimensions, ne se modifie pas avec les autres');

	}

	public function testAZoneFillBedIsRefused(): void {

		$eBed = $this->createBeds([80])->first();

		$ePlot = Plot::model()
			->select(['id', 'structure', 'zone'])
			->whereId($eBed['plot']['id'])
			->get();

		$eBedFill = new Bed([
			'name' => 'zone-fill',
			'plot' => $ePlot,
			'zoneFill' => TRUE,
			'plotFill' => FALSE,
			'length' => 20,
			'width' => 80,
			'alleyWidth' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui ne laisse pas poser zoneFill : la base porte dix planches qui remplissent leur parcelle sans remplir leur
		// jardin, et c'est ce cas que plotFill seul laisserait passer
		BedLib::create($eBedFill);

		$fw = new \FailWatch();

		BedLib::buildIds($eBed['plot'], [$eBed['id'], $eBedFill['id']]);

		$this->assertTrue($fw->has('Bed::ids.check'), 'la planche qui remplit la parcelle, sans dimensions, ne se modifie pas');

	}

	private function createBeds(array $widths, array $alleyWidths = []): \Collection {

		$eZone = new Zone([
			'name' => 'Parcelle des dimensions '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => self::SQUARE,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : le carré est fixe, et le cas ne lit que sa présence
		ZoneLib::create($eZone);

		$ePlot = new Plot([
			'name' => 'Jardin des dimensions',
			'zone' => $eZone,
			'mode' => Plot::OPEN_FIELD,
			'area' => 100,
			'coordinates' => self::SQUARE,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait le polygone posté et sa place dans la parcelle : le carré est fixe, et le cas ne lit que sa présence
		PlotLib::create($ePlot);

		$cBed = new \Collection();

		foreach($widths as $position => $width) {

			$eBed = new Bed([
				'name' => 'd'.($position + 1),
				'plot' => $ePlot,
				'zoneFill' => FALSE,
				'plotFill' => FALSE,
				'length' => 20,
				'width' => $width,
				'alleyWidth' => $alleyWidths[$position] ?? NULL,
				'seasonFirst' => NULL,
				'seasonLast' => NULL,
			]);

			// Sans build(), qui vérifierait les bornes des dimensions postées : le cas veut deux largeurs fixes
			BedLib::create($eBed);

			$cBed[$eBed['id']] = $eBed;

		}

		return BedLib::buildIds($ePlot, $cBed->getIds());

	}

}
?>
