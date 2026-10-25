<?php
namespace map;

/**
 * L'herbe, le sol et le fond du pré propres à une parcelle (`Zone.decorGrass`, `Zone.decorSoil`, `Zone.decorMeadow`), par les chemins d'écriture du produit :
 * le choix dans la fiche du décor (`zone:doUpdateDecor`, qui poste les trois à chaque choix), le panneau d'une parcelle qui ne
 * les porte pas, l'API qui ne les expose pas et la scène 3D qui les lit.
 *
 * Le décor est une parcelle posée par `ZoneLib::create()` dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=ZoneDecorTerrainAi
 */
class ZoneDecorTerrainAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle porte son auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createZone(): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle du terrain '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		ZoneLib::create($eZone);

		return $eZone;

	}

	/** Le choix tel que la fiche du décor le poste, construit et enregistré comme le fait `doUpdateDecor`. */
	private function chooseTerrain(Zone $eZone, array $input, \FailWatch $fw): void {

		$properties = ['decorGrass', 'decorSoil', 'decorMeadow'];

		$eZoneChosen = ZoneLib::getById($eZone['id']);
		$eZoneChosen->build($properties, $input, new \PropertyList('update'));

		if($fw->ok()) {
			ZoneLib::update($eZoneChosen, $properties);
		}

	}

	private function getTerrain(Zone $eZone): array {

		$eZoneRead = Zone::model()
			->select(['decorGrass', 'decorSoil', 'decorMeadow'])
			->whereId($eZone['id'])
			->get();

		return [$eZoneRead['decorGrass'], $eZoneRead['decorSoil'], $eZoneRead['decorMeadow']];

	}

	public function testNewZoneFollowsTheFarm(): void {

		$this->assertEquals([NULL, NULL, NULL], $this->getTerrain($this->createZone()));

	}

	public function testChoiceIsKept(): void {

		$eZone = $this->createZone();
		$fw = new \FailWatch();

		$this->chooseTerrain($eZone, ['decorGrass' => Zone::DRY, 'decorSoil' => Zone::SAND, 'decorMeadow' => Zone::FLOWERY], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals([Zone::DRY, Zone::SAND, Zone::FLOWERY], $this->getTerrain($eZone));

	}

	/** « Comme la ferme » poste une chaîne vide, que build() enregistre à NULL sur une colonne nullable. */
	public function testEmptyChoiceFollowsTheFarm(): void {

		$eZone = $this->createZone();
		$fw = new \FailWatch();

		$this->chooseTerrain($eZone, ['decorGrass' => Zone::LUSH, 'decorSoil' => Zone::CLAY, 'decorMeadow' => Zone::TUFTED], $fw);
		$this->chooseTerrain($eZone, ['decorGrass' => '', 'decorSoil' => Zone::CLAY, 'decorMeadow' => ''], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals([NULL, Zone::CLAY, NULL], $this->getTerrain($eZone));

	}

	public function testUnknownValuesAreRefused(): void {

		$eZone = $this->createZone();
		$fw = new \FailWatch();

		$this->chooseTerrain($eZone, ['decorGrass' => 'snow', 'decorSoil' => 'lava', 'decorMeadow' => 'moor'], $fw);

		$this->assertTrue($fw->has('Zone::decorGrass.check'), json_encode($fw->get()));
		$this->assertTrue($fw->has('Zone::decorSoil.check'), json_encode($fw->get()));
		$this->assertTrue($fw->has('Zone::decorMeadow.check'), json_encode($fw->get()));
		$this->assertEquals([NULL, NULL, NULL], $this->getTerrain($eZone));

	}

	/** Le panneau d'une parcelle ne porte pas le terrain : l'enregistrer ne l'efface pas. */
	public function testPanelKeepsTheTerrain(): void {

		$eZone = $this->createZone();
		$fw = new \FailWatch();

		$this->chooseTerrain($eZone, ['decorGrass' => Zone::GRAZED, 'decorSoil' => Zone::CHALK, 'decorMeadow' => Zone::MIXED], $fw);

		$eZoneUpdated = ZoneLib::getById($eZone['id']);
		$properties = ZoneLib::getPropertiesUpdate(NULL, $eZoneUpdated);

		$eZoneUpdated->build($properties, [
			'name' => 'Parcelle renommée',
			'area' => 9000,
			'seasonFirst' => '',
			'seasonLast' => ''
		], new \PropertyList('update'));

		ZoneLib::update($eZoneUpdated, $properties);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals([Zone::GRAZED, Zone::CHALK, Zone::MIXED], $this->getTerrain($eZone));

	}

	/** Un décor de la scène 3D n'est exposé par l'API ni en lecture ni en écriture, et l'écriture d'une parcelle par l'API garde son terrain. */
	public function testApiIgnoresDecor(): void {

		$eZone = $this->createZone();
		$fw = new \FailWatch();

		$this->chooseTerrain($eZone, ['decorGrass' => Zone::DRY, 'decorSoil' => Zone::SAND, 'decorMeadow' => Zone::FLOWERY], $fw);

		$read = $this->callApi('GET', 'zone/{id}', get: ['id' => $eZone['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertFalse(array_key_exists('decorGrass', $read['body']), 'herbe pas en lecture');
		$this->assertFalse(array_key_exists('decorSoil', $read['body']), 'sol pas en lecture');
		$this->assertFalse(array_key_exists('decorMeadow', $read['body']), 'pré pas en lecture');

		$named = $this->callApi('PUT', 'zone/{id}', body: [
			'properties' => ['decorGrass', 'decorSoil', 'decorMeadow'],
			'decorGrass' => Zone::LUSH,
			'decorSoil' => Zone::CLAY,
			'decorMeadow' => Zone::TUFTED
		], get: ['id' => $eZone['id']]);

		$this->assertEquals(400, $named['status'], json_encode($named['body']));
		$this->assertEquals([Zone::DRY, Zone::SAND, Zone::FLOWERY], $this->getTerrain($eZone), 'pas en écriture');

		$full = $this->callApi('PUT', 'zone/{id}', body: [
			'name' => 'Parcelle par l\'API',
			'coordinates' => NULL,
			'area' => 8000,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
			'decorGrass' => Zone::LUSH,
			'decorSoil' => Zone::CLAY,
			'decorMeadow' => Zone::TUFTED
		], get: ['id' => $eZone['id']]);

		$this->assertEquals(200, $full['status'], json_encode($full['body']));
		$this->assertEquals([Zone::DRY, Zone::SAND, Zone::FLOWERY], $this->getTerrain($eZone), 'PUT complet qui les nomme sans les lister');

	}

	public function testSceneReadsTheTerrain(): void {

		$eZone = $this->createZone();
		$fw = new \FailWatch();

		$this->chooseTerrain($eZone, ['decorGrass' => Zone::DRY, 'decorSoil' => '', 'decorMeadow' => Zone::TUFTED], $fw);

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());
		$zones = array_column($scene['zones'], NULL, 'id');

		$this->assertTrue(array_key_exists($eZone['id'], $zones), 'la parcelle sans saison est dans la scène de l\'année');
		$this->assertEquals(Zone::DRY, $zones[$eZone['id']]['decorGrass']);
		$this->assertEquals(NULL, $zones[$eZone['id']]['decorSoil'], 'NULL arrive tel quel, la scène prend le sol de la ferme');
		$this->assertEquals(Zone::TUFTED, $zones[$eZone['id']]['decorMeadow']);

	}

}
?>
