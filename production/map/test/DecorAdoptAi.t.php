<?php
namespace map;

/**
 * Le décor par défaut qu'enregistre `doAdopt` au premier geste de la pose : `DecorLib::buildDefault()` sur les éléments postés,
 * puis `DecorLib::adopt()`, tout ou rien, seulement sur une structure qui n'a pas encore son propre décor, même vidé depuis. Puis
 * `DecorLib::unadopt()`, que `doUnadopt` appelle quand Annuler défait l'adoption.
 *
 * Le décor du cas est une parcelle tracée posée par `ZoneLib::create()` dans la transaction de la méthode, sur la structure
 * `FixtureAiLib::F1`, qui n'a ni décor enregistré ni adoption : la méthode le prouve avant de s'en servir.
 *
 * Lancement : dev/test package=map file=DecorAdoptAi
 */
class DecorAdoptAiTest extends \dev\ApiTest {

	private \Closure $irrigationEnabled;

	protected function getTransactionModel(): \ModuleModel {
		return new DecorModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un décor portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

		$this->irrigationEnabled = MapSetting::$irrigationEnabled;

	}

	public function tearDown(): void {

		MapSetting::$irrigationEnabled = $this->irrigationEnabled;

		parent::tearDown();

	}

	/** Une parcelle carrée de 100 m de côté autour de [3.1, 45.6], sur une structure sans décor. */
	private function createZone(): bool {

		if($this->assertDecorPresent(Decor::model()->whereStructure($this->eStructure)->exists() === FALSE, 'la structure F1 sans aucune ligne de décor') === FALSE) {
			return FALSE;
		}

		if($this->assertDecorPresent(ConfigurationLib::readByStructure($this->eStructure)['decorAdopted'] === FALSE, 'la structure F1 sans décor adopté') === FALSE) {
			return FALSE;
		}

		$eZone = new Zone([
			'name' => 'Parcelle du décor par défaut '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => [[3.0994, 45.5996], [3.1006, 45.5996], [3.1006, 45.6004], [3.0994, 45.6004], [3.0994, 45.5996]],
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ce carré
		// fermé l'est, et le cas ne lit que ses sommets
		ZoneLib::create($eZone);

		return TRUE;

	}

	private function getSource(): Decor {

		return new Decor([
			'structure' => $this->eStructure,
			'center' => DecorLib::getCenter($this->eStructure)
		]);

	}

	/**
	 * Les éléments tels que la scène les poste, un tableau par propriété indexé par rang : un point à tant de mètres à l'est du
	 * centre, la graine de son tirage.
	 */
	private function getInput(array $kits): array {

		$center = DecorLib::getCenter($this->eStructure);

		$input = ['kit' => [], 'coordinates' => [], 'angle' => [], 'size' => [], 'seed' => []];

		foreach(array_values($kits) as $index => $kit) {
			$input['kit'][] = $kit;
			$input['coordinates'][] = json_encode([$center[0] + (10 * $index) / (111320 * cos(deg2rad($center[1]))), $center[1]]);
			$input['angle'][] = 0.5;
			$input['size'][] = 1.6;
			$input['seed'][] = 31000 + $index;
		}

		return $input;

	}

	private function countSeeded(): int {

		return Decor::model()
			->whereStructure($this->eStructure)
			->whereSeed('>=', 31000)
			->count();

	}

	public function testAdopt(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'massif', 'arbre']));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::adopt($this->eStructure, $cDecor);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals([0, 1, 2], $cDecor->getKeys());

		$cDecorStored = Decor::model()
			->select(['kit', 'angle', 'size', 'seed', 'layer', 'zone', 'plot', 'trace'])
			->whereId('IN', $cDecor->getColumn('id'))
			->sort(['id' => SORT_ASC])
			->getCollection();

		$this->assertEquals(['verger', 'massif', 'arbre'], array_values($cDecorStored->getColumn('kit')));
		$this->assertEquals([31000, 31001, 31002], array_values($cDecorStored->getColumn('seed')));
		$this->assertEquals(1.6, $cDecorStored->first()['size']);
		$this->assertTrue($cDecorStored->first()['layer']->empty());
		$this->assertTrue($cDecorStored->first()['zone']->empty());
		$this->assertEquals(NULL, $cDecorStored->first()['trace']);

	}

	/** La scène reçoit la graine de chaque élément, et sait que la structure a adopté son décor. */
	public function testScene(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$sceneBefore = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$this->assertFalse($sceneBefore['decorAdopted']);
		$this->assertEquals(['center' => DecorLib::getCenter($this->eStructure), 'distance' => MapSetting::DECOR_NEAR], $sceneBefore['decorReach']);

		$fw = new \FailWatch();

		DecorLib::adopt($this->eStructure, DecorLib::buildDefault($this->getSource(), $this->getInput(['verger'])));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$this->assertTrue($scene['decorAdopted']);
		$this->assertEquals(NULL, $scene['decorReach']);
		$this->assertEquals([31000], array_column($scene['decor'], 'seed'));

	}

	/**
	 * Une ligne que la scène ne montre pas compte : un élément d'irrigation, hors du drapeau de l'irrigation, ne sort pas du plan
	 * mais sa pose a donné son décor à la structure, et l'adoption se refuse.
	 */
	public function testRefusedWhenStoredHidden(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		MapSetting::$irrigationEnabled = fn(\structure\Structure $eStructure): bool => TRUE;

		$fw = new \FailWatch();

		$eDecor = $this->getSource();
		$eDecor->build(DecorLib::getPropertiesCreate(), [
			'kit' => 'pompe',
			'coordinates' => json_encode($eDecor['center']),
			'angle' => 0,
			'size' => 1
		], new \PropertyList('create'));

		DecorLib::create($eDecor);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		MapSetting::$irrigationEnabled = fn(\structure\Structure $eStructure): bool => FALSE;

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$this->assertEquals([], $scene['decor'], 'la pompe hors drapeau ne sort pas du plan');
		$this->assertTrue($scene['decorAdopted'], 'la pompe hors drapeau est le décor de la structure');

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'arbre']));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::adopt($this->eStructure, $cDecor);

		$this->assertTrue($fw->has('Decor::structure.adopted'));
		$this->assertEquals(0, $this->countSeeded());

	}

	/** Un élément adopté, retiré puis rétabli dans une séance suivante, repasse par `doCreate` avec sa graine, et garde sa forme. */
	public function testCreateKeepsSeed(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$eDecor = $this->getSource();
		$eDecor->build(DecorLib::getPropertiesCreate(), [
			'kit' => 'arbre',
			'coordinates' => json_encode($eDecor['center']),
			'angle' => 0,
			'size' => 1,
			'seed' => 31000
		], new \PropertyList('create'));

		DecorLib::create($eDecor);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(1, $this->countSeeded());

	}

	/** Une pose, hors de toute adoption, donne son décor à la structure : le défaut ne revient plus. */
	public function testCreateAdopts(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$eDecor = $this->getSource();
		$eDecor->build(DecorLib::getPropertiesCreate(), [
			'kit' => 'hangar',
			'coordinates' => json_encode($eDecor['center']),
			'angle' => 0,
			'size' => 1
		], new \PropertyList('create'));

		DecorLib::create($eDecor);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertTrue(ConfigurationLib::readByStructure($this->eStructure)['decorAdopted']);
		$this->assertEquals(['tender', 'loam'], [ConfigurationLib::readByStructure($this->eStructure)['decorGrass'], ConfigurationLib::readByStructure($this->eStructure)['decorSoil']], 'le réglage créé par l\'adoption garde le terrain par défaut');

	}

	/**
	 * Une ligne vaut adoption, drapeau posé ou non : c'est l'état d'une structure qui a un décor tant que la reprise
	 * de `migration/2026-10-12-map-adoption-du-decor.md` ne l'a pas marquée. Le défaut ne se tire pas, et l'adoption se refuse.
	 */
	public function testStoredWithoutFlag(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'arbre']));
		DecorLib::adopt($this->eStructure, $cDecor);

		ConfigurationLib::unadoptDecor($this->eStructure);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertFalse(ConfigurationLib::readByStructure($this->eStructure)['decorAdopted']);

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$this->assertTrue($scene['decorAdopted']);
		$this->assertEquals(NULL, $scene['decorReach']);

		DecorLib::adopt($this->eStructure, DecorLib::buildDefault($this->getSource(), $this->getInput(['verger'])));

		$this->assertTrue($fw->has('Decor::structure.adopted'));
		$this->assertEquals(2, $this->countSeeded());

	}

	/** Un plan adopté puis vidé reste vide : la scène ne tire plus le défaut, et une nouvelle adoption se refuse. */
	public function testEmptiedStaysEmpty(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'arbre']));
		DecorLib::adopt($this->eStructure, $cDecor);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		foreach($cDecor as $eDecor) {
			DecorLib::delete($eDecor);
		}

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$this->assertEquals([], $scene['decor']);
		$this->assertTrue($scene['decorAdopted']);
		$this->assertEquals(NULL, $scene['decorReach']);

		DecorLib::adopt($this->eStructure, DecorLib::buildDefault($this->getSource(), $this->getInput(['verger'])));

		$this->assertTrue($fw->has('Decor::structure.adopted'));
		$this->assertEquals(0, $this->countSeeded());

	}

	/** Annuler défait l'adoption : les lignes s'en vont, le défaut revient, et une nouvelle adoption passe. */
	public function testUnadopt(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'massif', 'arbre']));
		DecorLib::adopt($this->eStructure, $cDecor);

		// Un élément retiré pendant la séance a déjà perdu sa ligne : son id reste posté, sans effet
		DecorLib::delete($cDecor[1]);

		DecorLib::unadopt($this->eStructure, $cDecor->getIds());

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(0, Decor::model()->whereStructure($this->eStructure)->count());

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$this->assertFalse($scene['decorAdopted']);
		$this->assertEquals(['center' => DecorLib::getCenter($this->eStructure), 'distance' => MapSetting::DECOR_NEAR], $scene['decorReach']);

		DecorLib::adopt($this->eStructure, DecorLib::buildDefault($this->getSource(), $this->getInput(['verger'])));

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(1, $this->countSeeded());

	}

	/**
	 * Un plan adopté puis vidé reste vide : sans id, l'annulation se refuse. La séance qui a vidé le plan l'annule encore, avec
	 * les ids des éléments qu'elle a retirés, et une structure qui n'a rien adopté n'a rien à annuler, quels que soient les ids.
	 */
	public function testUnadoptEmptied(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		// Un id posté ne rend pas l'annulation possible sur une structure qui n'a rien adopté
		$fw = new \FailWatch();

		DecorLib::unadopt($this->eStructure, [1]);

		$this->assertTrue($fw->has('Decor::structure.unadopted'));

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'arbre']));
		DecorLib::adopt($this->eStructure, $cDecor);

		foreach($cDecor as $eDecor) {
			DecorLib::delete($eDecor);
		}

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::unadopt($this->eStructure, []);

		$this->assertTrue($fw->has('Decor::structure.unadopted'));
		$this->assertTrue(ConfigurationLib::readByStructure($this->eStructure)['decorAdopted']);
		$this->assertTrue(SceneLib::getByStructure($this->eStructure, \Clock::year())['decorAdopted']);

		$fw = new \FailWatch();

		DecorLib::unadopt($this->eStructure, $cDecor->getIds());

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertFalse(ConfigurationLib::readByStructure($this->eStructure)['decorAdopted']);

	}

	/** Un élément posé depuis une autre fenêtre, absent des ids postés, refuse l'annulation : rien ne s'efface. */
	public function testUnadoptRefused(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', 'arbre']));
		DecorLib::adopt($this->eStructure, $cDecor);

		$eDecor = $this->getSource();
		$eDecor->build(DecorLib::getPropertiesCreate(), [
			'kit' => 'hangar',
			'coordinates' => json_encode($eDecor['center']),
			'angle' => 0,
			'size' => 1
		], new \PropertyList('create'));

		DecorLib::create($eDecor);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::unadopt($this->eStructure, $cDecor->getIds());

		$this->assertTrue($fw->has('Decor::structure.unadopted'));
		$this->assertTrue(AlertUi::getError('Decor::structure.unadopted') !== NULL);
		$this->assertEquals(3, Decor::model()->whereStructure($this->eStructure)->count());
		$this->assertTrue(ConfigurationLib::readByStructure($this->eStructure)['decorAdopted']);

	}

	public function testTooMany(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(array_fill(0, MapSetting::DECOR_DEFAULT_MAX + 1, 'arbre')));

		$this->assertTrue($fw->has('Decor::default.check'));
		$this->assertEquals(1, $fw->count(), 'refusé avant toute construction');
		$this->assertTrue($cDecor->empty());

	}

	public function testIndexNotInteger(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$input = $this->getInput(['arbre']);

		foreach($input as $property => $values) {
			$input[$property] = ['a' => $values[0]];
		}

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $input);

		$this->assertTrue($fw->has('Decor::default.check'));
		$this->assertTrue($cDecor->empty());

	}

	/** Un kit posté en valeur seule, sans rang, n'est pas l'envoi de la scène. */
	public function testKitNotArray(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$input = $this->getInput(['arbre']);
		$input['kit'] = 'arbre';

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), $input);

		$this->assertTrue($fw->has('Decor::default.check'));
		$this->assertTrue($cDecor->empty());

	}

	/** Une graine que le schéma refuse se dit sur l'élément, avec son message. */
	public function testSeedRefused(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$input = $this->getInput(['arbre']);
		$input['seed'][0] = -1;

		$fw = new \FailWatch();

		DecorLib::buildDefault($this->getSource(), $input);

		$this->assertTrue($fw->has('Decor::seed.check'));
		$this->assertTrue(AlertUi::getError('Decor::seed.check') !== NULL);

	}

	public function testEmpty(): void {

		$fw = new \FailWatch();

		$cDecor = DecorLib::buildDefault($this->getSource(), []);

		$this->assertTrue($fw->has('Decor::default.check'));
		$this->assertTrue($cDecor->empty());

	}

	/**
	 * Un kit hors du décor par défaut refuse l'envoi entier, un kit d'irrigation aussi quand l'irrigation est ouverte. Le hangar
	 * en est : il se pose à la main, jamais par le défaut.
	 */
	public function testKitOutsideDefault(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		MapSetting::$irrigationEnabled = fn(\structure\Structure $eStructure): bool => TRUE;

		foreach(['hangar', 'cabane', 'pompe', 'haie'] as $kit) {

			$fw = new \FailWatch();

			$cDecor = DecorLib::buildDefault($this->getSource(), $this->getInput(['verger', $kit]));

			$this->assertTrue($fw->has('Decor::kit.default'), $kit);
			$this->assertTrue($cDecor->empty(), $kit);

		}

	}

	/** Une place que le schéma refuse se dit sur l'élément, à son rang. */
	public function testPositionRefused(): void {

		if($this->createZone() === FALSE) {
			return;
		}

		$input = $this->getInput(['verger', 'arbre']);
		$input['size'][1] = 50;

		$fw = new \FailWatch();

		DecorLib::buildDefault($this->getSource(), $input);

		$this->assertTrue($fw->has('Decor::size.check'));
		$this->assertEquals(1, $fw->count());

	}

	/** Un membre qui ne gère pas la structure n'enregistre pas son décor, ce que `doAdopt` valide avant toute construction. */
	public function testNotManager(): void {

		\user\ConnectionLib::setOnline(\user\User::model()
			->select('id')
			->whereEmail('decor.f1.planning@example.org')
			->get());

		$eDecor = $this->getSource();

		$this->assertTrue($eDecor->canRead());
		$this->assertFalse($eDecor->canCreate());

	}

}
?>
