<?php
namespace map;

/**
 * Le contour d'une parcelle tel que le web l'écrit depuis la carte : `zone:doUpdateShape` construit
 * `area` et `coordinates` avec `for: 'shape'`, puis `ZoneLib::update()`. Le panneau « Modifier une
 * parcelle », lui, ne construit plus le contour et ne doit donc plus l'effacer. « Ajouter une parcelle » le construit, dessiné
 * ou remis à plus tard.
 *
 * Le décor est une parcelle posée par `ZoneLib::create()` dans la transaction de la méthode, sur la
 * structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=ZoneShapeAi
 */
class ZoneShapeAiTest extends \dev\ApiTest {

	const array TRIANGLE = [[3.128768, 45.664010], [3.128903, 45.663964], [3.129082, 45.663926]];
	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle porte son auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createZone(?array $coordinates): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle à tracer '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => $coordinates,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui jugerait le nom, la surface et l'anneau : le décor est fixe, et les cas n'éprouvent que ce qui s'écrit ensuite
		ZoneLib::create($eZone);

		return $eZone;

	}

	/** Ce que poste « Terminer », construit et enregistré comme le fait `doUpdateShape`. */
	private function updateShape(Zone $eZone, array $input, \FailWatch $fw): void {

		$eZone->build(['coordinates', 'area'], $input, new \PropertyList('shape'));

		if($fw->ok()) {
			ZoneLib::update($eZone, ['area', 'coordinates']);
		}

	}

	private function getStored(Zone $eZone): Zone {

		return Zone::model()
			->select(['id', 'area', 'coordinates'])
			->whereId($eZone['id'])
			->get();

	}

	public function testShapeIsWritten(): void {

		$eZone = $this->createZone(NULL);

		$fw = new \FailWatch();

		$this->updateShape($eZone, ['area' => '1234', 'coordinates' => json_encode(self::TRIANGLE)], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eZoneStored = $this->getStored($eZone);

		$this->assertEquals(MapLib::getArea(self::TRIANGLE), $eZoneStored['area'], 'la surface est celle du contour, pas celle postée');
		$this->assertEquals(4, count($eZoneStored['coordinates']), 'l\'anneau est refermé à l\'écriture');

	}

	/** Un anneau fermé par un point écrit en chaînes n'est pas refermé une seconde fois par la base. */
	public function testShapeClosedByStrings(): void {

		$eZone = $this->createZone(NULL);

		$points = self::TRIANGLE;
		$points[] = [(string)self::TRIANGLE[0][0], (string)self::TRIANGLE[0][1]];

		$fw = new \FailWatch();

		$this->updateShape($eZone, ['area' => '1234', 'coordinates' => json_encode($points)], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(4, count($this->getStored($eZone)['coordinates']));

	}

	/** La route du tracé n'existe que pour poser un contour : sans lui, ou avec un contour illisible, elle n'efface rien. */
	public function testShapeIsRequired(): void {

		$refused = [
			'champ absent' => ['area' => '1234'],
			'champ vide' => ['area' => '1234', 'coordinates' => ''],
			'JSON illisible' => ['area' => '1234', 'coordinates' => '[[3.1, 45.6], [3.2'],
			'deux sommets' => ['area' => '1234', 'coordinates' => json_encode(array_slice(self::TRIANGLE, 0, 2))],
			'liste vide' => ['area' => '1234', 'coordinates' => '[]']
		];

		foreach($refused as $case => $input) {

			$eZone = $this->createZone(self::SQUARE);

			$fw = new \FailWatch();

			$this->updateShape($eZone, $input, $fw);

			$this->assertTrue($fw->has('Zone::coordinates.ring') or $fw->has('Zone::coordinates.check'), $case.' '.json_encode($fw->get()));
			$this->assertEquals(5, count($this->getStored($eZone)['coordinates'] ?? []), $case.', le contour enregistré est intact');

		}

	}

	/**
	 * Le panneau « Ajouter une parcelle » la dessine, ou la remet à plus tard : son contour ne part pas, et la parcelle garde la
	 * surface saisie.
	 */
	public function testPanelCreatesWithOrWithoutShape(): void {

		$this->assertEquals(['name', 'coordinates', 'area', 'seasonFirst'], ZoneLib::getPropertiesCreate());

		$cases = [
			'dessinée' => ['coordinates' => json_encode(self::SQUARE), 'area' => '1600'],
			'à dessiner plus tard' => ['area' => '1600']
		];

		$stored = [];

		foreach($cases as $case => $input) {

			$eZone = new Zone([
				'structure' => $this->eStructure
			]);

			$fw = new \FailWatch();

			$eZone->build(ZoneLib::getPropertiesCreate(), ['name' => 'Parcelle '.$case, 'seasonFirst' => ''] + $input, new \PropertyList('create'));

			$this->assertTrue($fw->ok(), $case.' '.json_encode($fw->get()));

			ZoneLib::create($eZone);

			$stored[$case] = $this->getStored($eZone);

		}

		$this->assertEquals(MapLib::getArea(self::SQUARE), $stored['dessinée']['area'], 'dessinée, sa surface est celle du contour, pas celle postée');
		$this->assertEquals(5, count($stored['dessinée']['coordinates'] ?? []), 'et le contour est enregistré');
		$this->assertEquals(1600, $stored['à dessiner plus tard']['area'], 'à dessiner plus tard, elle garde la surface saisie');
		$this->assertEquals(NULL, $stored['à dessiner plus tard']['coordinates'], 'sans contour');

	}

	/** Le panneau de modification ne poste pas le contour : l'enregistrer ne doit pas l'effacer. */
	public function testPanelKeepsShape(): void {

		$this->assertTrue(in_array('coordinates', ZoneLib::getPropertiesUpdate()) === FALSE, 'le panneau de modification ne construit pas le contour');

		$eZone = $this->createZone(self::SQUARE);

		$fw = new \FailWatch();

		$eZone->build(ZoneLib::getPropertiesUpdate(), [
			'name' => 'Parcelle renommée',
			'area' => '2000',
			'seasonFirst' => '',
			'seasonLast' => ''
		], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		ZoneLib::update($eZone, ZoneLib::getPropertiesUpdate());

		$eZoneStored = $this->getStored($eZone);

		$this->assertEquals(2000, $eZoneStored['area']);
		$this->assertEquals(5, count($eZoneStored['coordinates'] ?? []));

	}

	/** Les listes de l'API construisent le contour avant la surface, qui se lit sur lui (Zone::properties()). */
	public function testApiListsBuildTheContourFirst(): void {

		$this->assertEquals(['name', 'coordinates', 'area', 'seasonFirst'], ZoneLib::getPropertiesCreate('api'));
		$this->assertEquals(['name', 'coordinates', 'area', 'seasonFirst', 'seasonLast'], ZoneLib::getPropertiesUpdate('api'));

	}

}
?>
