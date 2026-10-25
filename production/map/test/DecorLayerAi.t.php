<?php
namespace map;

/**
 * Les calques du décor (`map\DecorLayer`) et ce que le décor en porte (`layer`, et pour un tuyau
 * `diameter` et `laying`), par le chemin d'écriture du produit : `build()` sur les listes de la page,
 * puis le Lib, comme `page/decorLayer.p.php` et `page/decor.p.php`.
 *
 * Le décor du cas, une parcelle tracée, se pose par `ZoneLib::create()` dans la transaction de la
 * méthode, sur la structure `FixtureAiLib::F1` ; une seconde structure, `FixtureAiLib::F2`, porte
 * le calque qu'un décor de la première ne peut pas viser.
 *
 * Le drapeau de l'irrigation (`MapSetting::$irrigationEnabled`), ouvert en dev, est remis dans `tearDown()` après
 * `testIrrigationFlag`, qui lui donne la liste de production.
 *
 * Lancement : dev/test package=map file=DecorLayerAi
 */
class DecorLayerAiTest extends \dev\ApiTest {

	private \Closure $irrigationEnabled;

	protected function getTransactionModel(): \ModuleModel {
		return new DecorLayerModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Un calque, une parcelle et un décor portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

		$this->irrigationEnabled = MapSetting::$irrigationEnabled;

	}

	public function tearDown(): void {

		MapSetting::$irrigationEnabled = $this->irrigationEnabled;

		parent::tearDown();

	}

	/** Une parcelle carrée de 100 m de côté autour de [3.1, 45.6]. */
	private function createZone(): void {

		$eZone = new Zone([
			'name' => 'Parcelle des calques '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => [[3.0994, 45.5996], [3.1006, 45.5996], [3.1006, 45.6004], [3.0994, 45.6004], [3.0994, 45.5996]],
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ce carré
		// fermé l'est, et le cas ne lit que ses sommets
		ZoneLib::create($eZone);

	}

	/** Le calque tel que le client le poste, construit et enregistré comme le fait `doCreate`. */
	private function createLayer(array $input, \FailWatch $fw, \structure\Structure $eStructure = new \structure\Structure()): DecorLayer {

		$eDecorLayer = new DecorLayer([
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure
		]);

		$this->assertTrue($eDecorLayer->acceptCreate());

		$eDecorLayer->build(DecorLayerLib::getPropertiesCreate(), $input + [
			'type' => DecorLayer::IRRIGATION,
			'name' => 'Arrosage',
		], new \PropertyList('create'));

		if($fw->ok()) {
			DecorLayerLib::create($eDecorLayer);
		}

		return $eDecorLayer;

	}

	/**
	 * Le décor tel que le client le poste, construit et enregistré comme le fait `doCreate` : le tracé
	 * arrive en chaîne JSON, que la conversion du schéma décode.
	 */
	private function createDecor(array $input, \FailWatch $fw): Decor {

		$eDecor = new Decor([
			'structure' => $this->eStructure,
			'center' => DecorLib::getCenter($this->eStructure)
		]);

		if(isset($input['trace'])) {
			$input['trace'] = json_encode($input['trace']);
		}

		$eDecor->build(DecorLib::getPropertiesCreate(), $input + [
			'kit' => 'pompe',
			'angle' => 0,
			'size' => 1,
			'coordinates' => json_encode($eDecor['center'])
		], new \PropertyList('create'));

		if($fw->ok()) {
			DecorLib::create($eDecor);
		}

		return $eDecor;

	}

	/** Un tracé de `$length` mètres vers l'est depuis le centre, par la projection de la scène. */
	private function getTrace(int $length = 30): array {

		$center = DecorLib::getCenter($this->eStructure);

		return [$center, [$center[0] + $length / (111320 * cos(deg2rad($center[1]))), $center[1]]];

	}

	public function testCreate(): void {

		$fw = new \FailWatch();

		$eDecorLayerFirst = $this->createLayer(['name' => 'Arrosage des jardins'], $fw);
		$eDecorLayerSecond = $this->createLayer(['name' => 'Arrosage du verger'], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$cDecorLayer = DecorLayerLib::getByStructure($this->eStructure);
		$positions = $cDecorLayer->getColumn('position', 'id');

		$this->assertEquals(1, $positions[$eDecorLayerSecond['id']] - $positions[$eDecorLayerFirst['id']], 'un calque neuf prend la place qui suit le dernier');
		$this->assertEquals(DecorLayer::IRRIGATION, $cDecorLayer[$eDecorLayerFirst['id']]['type']);
		$this->assertEquals('Arrosage des jardins', $cDecorLayer[$eDecorLayerFirst['id']]['name']);

	}

	public function testName(): void {

		$fw = new \FailWatch();
		$this->createLayer(['name' => ''], $fw);
		$this->assertTrue($fw->has('DecorLayer::name.check'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createLayer(['name' => str_repeat('a', DecorLayerUi::p('name')->range[1] + 1)], $fw);
		$this->assertTrue($fw->has('DecorLayer::name.check'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createLayer(['type' => 'electricity'], $fw);
		$this->assertTrue($fw->has('DecorLayer::type.check'), json_encode($fw->get()));

	}

	public function testRename(): void {

		$fw = new \FailWatch();
		$eDecorLayer = $this->createLayer([], $fw);

		$eDecorLayer = DecorLayerLib::getById($eDecorLayer['id']);
		$eDecorLayer->build(['name'], ['name' => 'Arrosage du potager'], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLayerLib::update($eDecorLayer, ['name']);

		$this->assertEquals('Arrosage du potager', DecorLayerLib::getById($eDecorLayer['id'])['name']);

	}

	public function testLimit(): void {

		$fw = new \FailWatch();

		$count = DecorLayer::model()->whereStructure($this->eStructure)->count();

		for($i = $count; $i < MapSetting::DECOR_LAYER_MAX; $i++) {
			$this->createLayer([], $fw);
		}

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->createLayer([], $fw);
		$this->assertTrue($fw->has('DecorLayer::structure.limit'), json_encode($fw->get()));

	}

	public function testDecorInLayer(): void {

		$this->createZone();

		$fw = new \FailWatch();

		$eDecorLayer = $this->createLayer([], $fw);

		$eDecor = $this->createDecor([
			'layer' => $eDecorLayer['id'],
			'kit' => 'tuyau',
			'trace' => $this->getTrace(),
			'diameter' => 63,
			'laying' => Decor::BURIED,
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorStored = Decor::model()
			->select(['layer', 'diameter', 'laying'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals($eDecorLayer['id'], $eDecorStored['layer']['id']);
		$this->assertEquals(63, $eDecorStored['diameter']);
		$this->assertEquals(Decor::BURIED, $eDecorStored['laying']);

	}

	/** L'électrovanne est une clé du catalogue comme la vanne manuelle : elle se pose dans un calque, sans diamètre ni pose. */
	public function testValveInLayer(): void {

		$this->createZone();

		$fw = new \FailWatch();

		$eDecorLayer = $this->createLayer([], $fw);

		$eDecor = $this->createDecor([
			'layer' => $eDecorLayer['id'],
			'kit' => 'electrovanne',
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorStored = Decor::model()
			->select(['kit', 'layer', 'diameter', 'laying'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals('electrovanne', $eDecorStored['kit']);
		$this->assertEquals($eDecorLayer['id'], $eDecorStored['layer']['id']);
		$this->assertEquals(NULL, $eDecorStored['diameter']);
		$this->assertEquals(NULL, $eDecorStored['laying']);

		$fw = new \FailWatch();
		$this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'electrovanne', 'diameter' => 32], $fw);
		$this->assertTrue($fw->has('Decor::diameter.pipe'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'electrovanne', 'laying' => Decor::BURIED], $fw);
		$this->assertTrue($fw->has('Decor::laying.pipe'), json_encode($fw->get()));

	}

	public function testLayerOfAnotherStructure(): void {

		$this->createZone();

		$eStructureOther = \structure\Structure::model()
			->select('id')
			->whereId(\structure\FixtureAiLib::F2)
			->get();

		if($this->assertDecorPresent($eStructureOther->notEmpty(), 'la structure FixtureAiLib::F2') === FALSE) {
			return;
		}

		$fw = new \FailWatch();
		$eDecorLayerOther = $this->createLayer([], $fw, $eStructureOther);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->createDecor(['layer' => $eDecorLayerOther['id']], $fw);
		$this->assertTrue($fw->has('Decor::layer.owned'), json_encode($fw->get()));

	}

	public function testPipeWithoutTrace(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['diameter' => 50], $fw);
		$this->assertTrue($fw->has('Decor::diameter.pipe'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['laying' => Decor::SURFACE], $fw);
		$this->assertTrue($fw->has('Decor::laying.pipe'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'tuyau', 'trace' => $this->getTrace(), 'diameter' => 401], $fw);
		$this->assertTrue($fw->has('Decor::diameter.check'), json_encode($fw->get()));

	}

	/** Comme `doUpdatePipe` : seuls le diamètre et la pose se construisent, sur le tuyau relu en base. */
	public function testUpdatePipe(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'tuyau', 'trace' => $this->getTrace(), 'diameter' => 50, 'laying' => Decor::SURFACE], $fw);
		$eDecorPump = $this->createDecor([], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecor = DecorLib::getById($eDecor['id']);
		$eDecor->build(['diameter', 'laying'], ['diameter' => 37, 'laying' => Decor::BURIED], new \PropertyList('update'));
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::update($eDecor, ['diameter', 'laying']);

		$eDecorStored = DecorLib::getById($eDecor['id']);
		$this->assertEquals(37, $eDecorStored['diameter']);
		$this->assertEquals(Decor::BURIED, $eDecorStored['laying']);

		// Une pompe n'a pas de tracé, donc ni diamètre ni pose
		$eDecorPump = DecorLib::getById($eDecorPump['id']);
		$eDecorPump->build(['diameter'], ['diameter' => 37], new \PropertyList('update'));
		$this->assertTrue($fw->has('Decor::diameter.pipe'), json_encode($fw->get()));

	}

	public function testDelete(): void {

		$this->createZone();

		$fw = new \FailWatch();

		$eDecorLayerDeleted = $this->createLayer([], $fw);
		$eDecorLayerKept = $this->createLayer([], $fw);

		$eDecorDeletedPump = $this->createDecor(['layer' => $eDecorLayerDeleted['id']], $fw);
		$eDecorDeletedPipe = $this->createDecor(['layer' => $eDecorLayerDeleted['id'], 'kit' => 'tuyau', 'trace' => $this->getTrace()], $fw);
		$eDecorKept = $this->createDecor(['layer' => $eDecorLayerKept['id']], $fw);
		$eDecorBase = $this->createDecor([], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->assertEquals(2, DecorLib::countByLayer($eDecorLayerDeleted), 'la confirmation annonce les éléments du calque, comptés en base');

		DecorLayerLib::delete(DecorLayerLib::getById($eDecorLayerDeleted['id']));

		$this->assertTrue(DecorLayerLib::getById($eDecorLayerDeleted['id'])->empty());
		$this->assertEquals(0, DecorLayerLib::getById($eDecorLayerKept['id'])['position'], 'le calque qui suivait le supprimé remonte d\'une place');

		$this->assertEquals(
			[$eDecorKept['id'], $eDecorBase['id']],
			Decor::model()
				->whereId('IN', [$eDecorDeletedPump['id'], $eDecorDeletedPipe['id'], $eDecorKept['id'], $eDecorBase['id']])
				->sort(['id' => SORT_ASC])
				->getColumn('id'),
			'un calque s\'en va avec ses éléments, et seulement les siens'
		);

	}

	/**
	 * Un tuyau d'un calque d'irrigation ne pèse pas dans `DECOR_TRACE_LENGTH`, un tracé du calque de base
	 * de la même longueur si.
	 */
	public function testPipeOutsideTraceLength(): void {

		$this->createZone();

		$fw = new \FailWatch();

		$eDecorLayer = $this->createLayer([], $fw);
		$this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'tuyau', 'trace' => $this->getTrace(MapSetting::DECOR_TRACE_LENGTH + 100)], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->createDecor(['kit' => 'cloture', 'trace' => $this->getTrace(MapSetting::DECOR_TRACE_LENGTH + 100)], $fw);

		$this->assertTrue($fw->has('Decor::trace.limit'), json_encode($fw->get()));

	}

	/** Le format de la scène porte les calques, et chaque décor son calque, son diamètre et sa pose. */
	public function testScene(): void {

		$this->createZone();

		$fw = new \FailWatch();

		$eDecorLayer = $this->createLayer(['name' => 'Arrosage <b>du</b> potager'], $fw);
		$eDecor = $this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'tuyau', 'trace' => $this->getTrace(), 'diameter' => 32, 'laying' => Decor::SURFACE], $fw);
		$eDecorBase = $this->createDecor([], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$scene = SceneLib::getByStructure(\structure\StructureLib::getById($this->eStructure['id']), \Clock::year());

		$layers = array_column($scene['layers'], NULL, 'id');
		$decor = array_column($scene['decor'], NULL, 'id');

		$this->assertEquals(['id' => $eDecorLayer['id'], 'name' => 'Arrosage <b>du</b> potager', 'type' => DecorLayer::IRRIGATION], $layers[$eDecorLayer['id']] ?? NULL, 'le nom sort tel que saisi : c\'est la scène qui l\'échappe');
		$this->assertEquals([$eDecorLayer['id'], 32, Decor::SURFACE], [$decor[$eDecor['id']]['layer'], $decor[$eDecor['id']]['diameter'], $decor[$eDecor['id']]['laying']]);
		$this->assertEquals(NULL, $decor[$eDecorBase['id']]['layer'], 'un décor sans calque appartient au calque de base');

	}

	/**
	 * Le drapeau de l'irrigation sur la liste de production, où la structure du cas n'est pas : ni calque
	 * d'irrigation ni élément d'irrigation ne se crée, et ceux déjà posés ne sortent pas dans la scène.
	 */
	public function testIrrigationFlag(): void {

		if($this->assertDecorPresent(in_array($this->eStructure['id'], [7, 17], TRUE) === FALSE, 'une structure de test hors de la liste de production de l\'irrigation') === FALSE) {
			return;
		}

		$this->createZone();

		// Posés pendant que le drapeau est ouvert, comme en dev
		$fw = new \FailWatch();

		$eDecorLayer = $this->createLayer([], $fw);
		$eDecorPipe = $this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'tuyau', 'trace' => $this->getTrace(), 'diameter' => 32, 'laying' => Decor::SURFACE], $fw);
		$eDecorTank = $this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'cuve'], $fw);
		$eDecorPump = $this->createDecor([], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		// La règle réelle, sur la liste de production
		MapSetting::$irrigationEnabled = fn(\structure\Structure $eStructure): bool => MapSetting::accepts($eStructure, [7, 17]);

		$this->assertFalse(MapSetting::isIrrigationEnabled($this->eStructure));

		$fw = new \FailWatch();
		$this->createLayer([], $fw);
		$this->assertTrue($fw->has('DecorLayer::type.irrigation'), 'un calque d\'irrigation ne se crée pas hors drapeau : '.json_encode($fw->get()));

		foreach(MapSetting::DECOR_IRRIGATION_KITS as $kit) {

			$fw = new \FailWatch();
			$this->createDecor(['kit' => $kit], $fw);
			$this->assertTrue($fw->has('Decor::kit.irrigation'), $kit.' ne se pose pas hors drapeau : '.json_encode($fw->get()));

		}

		$fw = new \FailWatch();
		$this->createDecor(['layer' => $eDecorLayer['id'], 'kit' => 'cuve'], $fw);
		$this->assertTrue($fw->has('Decor::layer.irrigation'), 'rien ne se pose dans un calque d\'irrigation hors drapeau : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$eDecorBase = $this->createDecor(['kit' => 'cuve'], $fw);
		$this->assertTrue($fw->ok(), 'le reste du décor se pose toujours : '.json_encode($fw->get()));

		$scene = SceneLib::getByStructure(\structure\StructureLib::getById($this->eStructure['id']), \Clock::year());

		$this->assertEquals([], array_column($scene['layers'], 'id'), 'aucun calque d\'irrigation dans la scène hors drapeau');
		$this->assertEquals([$eDecorBase['id']], array_values(array_intersect(array_column($scene['decor'], 'id'), [$eDecorPipe['id'], $eDecorTank['id'], $eDecorPump['id'], $eDecorBase['id']])), 'ni élément d\'irrigation ni élément d\'un calque d\'irrigation dans la scène hors drapeau');

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);
		$kits = new \ReflectionMethod(\map\SceneUi::class, 'getDecorKits');

		$this->assertTrue($eStructure->canManage(), 'le cas lit la carte de qui gère la structure');
		$this->assertFalse(str_contains(new DecorLayerUi()->getCard($eStructure, $this->getAdmin(), FALSE), 'data-scene-layers-new'), 'hors drapeau, aucun type de calque ne reste à créer');
		$this->assertEquals([], array_intersect(array_keys($kits->invoke(NULL, $eStructure)), MapSetting::DECOR_IRRIGATION_KITS), 'le catalogue ne propose aucune vignette d\'irrigation hors drapeau');

		// Le contrôle sait distinguer : la même structure mise dans la liste retrouve son irrigation
		MapSetting::$irrigationEnabled = fn(\structure\Structure $eStructure): bool => MapSetting::accepts($eStructure, [7, 17, $this->eStructure['id']]);

		$this->assertTrue(str_contains(new DecorLayerUi()->getCard($eStructure, $this->getAdmin(), FALSE), 'data-scene-layers-new'));
		$this->assertEquals(MapSetting::DECOR_IRRIGATION_KITS, array_values(array_intersect(array_keys($kits->invoke(NULL, $eStructure)), MapSetting::DECOR_IRRIGATION_KITS)));

		$scene = SceneLib::getByStructure(\structure\StructureLib::getById($this->eStructure['id']), \Clock::year());

		$this->assertTrue(in_array($eDecorLayer['id'], array_column($scene['layers'], 'id'), TRUE), 'le calque d\'irrigation sort sous le drapeau');
		$this->assertEquals([$eDecorPipe['id'], $eDecorTank['id'], $eDecorPump['id'], $eDecorBase['id']], array_values(array_intersect(array_column($scene['decor'], 'id'), [$eDecorPipe['id'], $eDecorTank['id'], $eDecorPump['id'], $eDecorBase['id']])));

		$fw = new \FailWatch();
		$this->createLayer([], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

	}

}
?>
