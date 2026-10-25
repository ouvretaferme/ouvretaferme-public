<?php
namespace map;

/**
 * Le dessin des planches d'un jardin, troisième niveau de la vue Carte : ouvert par l'adresse de la Carte
 * (`/ferme/{id}/cartographie/{season}?plot=`) ou au clic par `map/plot:drawing`, et sous l'ancienne adresse
 * `/ferme/{id}/planches/{season}`, qui y renvoie. Qui l'ouvre, sur quel jardin, et ce que la page pose pour la
 * liste (les planches à tracer, les voisins), puis ce que les gestes y rendent.
 *
 * Le jardin de l'adresse se cherche parmi ceux de la structure de l'adresse : le cas croisé connecte un
 * membre qui administre deux structures et demande à l'une le jardin de l'autre.
 *
 * Le décor est monté par les Lib dans la transaction de la méthode, sur `FixtureAiLib::F1` et, pour le cas
 * croisé, `FixtureAiLib::F2` : les tables du plan vivent dans la base commune.
 *
 * Lancement : dev/test package=map file=DrawConsole
 */
class DrawConsoleTest extends \dev\ApiTest {

	const int SEASON = 2026;

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	// Un identifiant d'utilisateur par membre fictif : MemberLib::$cMemberOnline est indexé par utilisateur
	private int $userId = 910000;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	// Les membres fictifs vivent dans des caches de processus, que la transaction n'annule pas
	public function tearDown(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);
		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');
	}

	private function createZone(\structure\Structure $eStructure, bool $drawn = TRUE, ?int $seasonLast = NULL): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle de console '.uniqid(),
			'structure' => $eStructure,
			'area' => 10000,
			'coordinates' => $drawn ? self::SQUARE : NULL,
			'seasonFirst' => NULL,
			'seasonLast' => $seasonLast,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : le carré est fixe, et le cas ne lit que sa présence
		ZoneLib::create($eZone);

		return $eZone;

	}

	private function createPlot(Zone $eZone, string $name, bool $drawn = TRUE, ?int $seasonLast = NULL): Plot {

		$ePlot = new Plot([
			'name' => $name,
			'zone' => $eZone,
			'mode' => Plot::OPEN_FIELD,
			'area' => 100,
			'coordinates' => $drawn ? self::SQUARE : NULL,
			'seasonFirst' => NULL,
			'seasonLast' => $seasonLast,
		]);

		// Sans build(), qui vérifierait le polygone posté et sa place dans la parcelle : le carré est fixe, et le cas ne lit que sa présence
		PlotLib::create($ePlot);

		return $ePlot;

	}

	private function createBeds(Plot $ePlot, int $number): \Collection {

		$cBed = new \Collection();

		for($position = 1; $position <= $number; $position++) {

			$eBed = new Bed([
				'name' => 'P'.$position,
				'plot' => $ePlot,
				'zoneFill' => $ePlot['zoneFill'],
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

	private function draw(Plot $ePlot, \Collection $cBed, int $season, array $start = self::ROW_START, float $across = 90): void {

		$eDraw = new Draw([
			'plot' => $ePlot,
			'season' => $season,
		]);

		// Sans build(), qui vérifierait la saison postée : elle est celle que le cas veut, antérieure comprise
		DrawLib::createByBeds($eDraw, $this->getPlaces($cBed, $start, $across));

	}

	private const array ROW_START = [3.0995, 45.5995];

	/**
	 * La place de chaque planche de `$cBed`, sous sa clé, dans un rang qui part de `$start` vers le cap `$across`, les planches
	 * côte à côte dans l'ordre de la collection, séparées par un passe-pied de 60 cm et longues vers le nord.
	 */
	private function getPlaces(\Collection $cBed, array $start, float $across): \Collection {

		$cDraw = new \Collection();
		$position = 0;

		foreach($cBed as $key => $eBed) {

			$corner = MapLib::getDestination($start, $position, $across);

			// La longueur part à cap + 90° : un rang large vers l'est part du bout est de chaque planche, vers l'ouest
			$place = (fmod(360 - $across, 360) === 90.0)
				? DrawLib::getCanonical($corner, $across, $eBed)
				: DrawLib::getCanonical(MapLib::getDestination($corner, $eBed['width'] / 100, $across), $across + 180, $eBed);

			$cDraw[$key] = new Draw([
				'bed' => $eBed
			] + $place);

			$position += $eBed['width'] / 100 + 0.6;

		}

		return $cDraw;

	}

	/**
	 * Connecte un membre fictif, administrateur ou simple lecteur de la production, des structures données.
	 * L'injection passe par le cache de MemberLib, comme `structure\AccountingRightsAiTest::connectAs()`.
	 */
	private function connectAs(array $structures, bool $admin): void {

		$eUser = new \user\User(['id' => $this->userId++]);
		\user\ConnectionLib::setOnline($eUser);

		$cMember = new \Collection();

		foreach($structures as $structure) {

			$cMember[$structure] = new \structure\Member([
				'structure' => $structure,
				// Les vues que les pages lisent sur le membre. La saison est celle de l'adresse et l'onglet celui que le cas ouvre : les pages n'ont alors rien à mémoriser sur un membre sans ligne en base
				'viewSeason' => self::SEASON,
				'viewMap' => \structure\Member::MAP,
				'viewMapBasemap' => \structure\Member::SATELLITE,
				'role' => new \structure\Role([
					'admin' => $admin,
					'accounting' => \structure\Role::DENIED,
					'selling' => FALSE,
					'planning' => \structure\Role::DENIED,
					'productionAnalyze' => FALSE,
					'productionObserve' => TRUE,
					'commercialisationAnalyze' => FALSE,
				]),
			]);

		}

		$property = self::getMemberCache();
		$property->setValue(NULL, $property->getValue() + [$eUser['id'] => $cMember]);

	}

	/**
	 * Joue la route comme le ferait le navigateur, sous l'utilisateur connecté, et rend la classe de ce qu'elle
	 * lève (forme de `journal\SearchEmptyAiTest::executePage()`) ; ce que la page pose est rendu dans `$data`.
	 * L'ancienne adresse se joue par la même voie (`$tab`), et `$redirect` reçoit l'adresse d'un renvoi.
	 */
	private function executePage(int $structure, array $input, ?\stdClass &$data, string $tab = 'cartographie', ?string &$redirect = NULL): string {

		// HttpRoute pose LIME_URL, pas le lanceur de tests
		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		$request = array_map(fn($value) => is_array($value) ? array_map(strval(...), $value) : (string)$value, $input) + ['id' => (string)$structure, 'season' => (string)self::SEASON];

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$request, [], $request];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'ferme/'.$structure.'/'.$tab.'/'.self::SEASON), $data);
			return 'aucune action';
		} catch(\RedirectAction $action) {
			$redirect = new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action);
			return \RedirectAction::class;
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Joue le clic qui ouvre un jardin sur la vue Carte (`MapPlan.openDrawing()`), et rend la classe de l'action
	 * levée ou le nom du refus.
	 */
	private function executeDrawing(Plot $ePlot, int $season, ?\stdClass &$data): string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		$input = ['id' => (string)$ePlot['id'], 'season' => (string)$season];

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$input, [], $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'map/plot:drawing'), $data);
			return 'aucune action';
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : $fail->format()[0][0];
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	public function testAdminOpensThePlotOnItsBedsWhenAsked(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin de console');
		$cBed = $this->createBeds($ePlot, 5);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P3'), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id']], $data), 'un administrateur ouvre la Carte sur son jardin');
		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'le jardin de l\'adresse est désigné');
		$this->assertTrue($data->ePlotDrawing->empty(), 'sans ses planches, que l\'adresse ne demande pas');

		$this->assertEquals(\ViewAction::class, $this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data), 'l\'adresse de « Planches » ouvre la Carte');

		$this->assertEquals($ePlot['id'], $data->ePlotDrawing['id'], 'sur les planches du jardin');
		$this->assertEquals($eZone['id'], $data->eZone['id'], 'dans sa parcelle');
		$this->assertEquals(['P3'], $data->ePlotDrawing['cBedUndrawn']->getColumn('name'), 'une planche manquante parmi cinq reste seule à tracer');
		$this->assertEquals(4, $data->ePlotDrawing['cBed']->find(fn($eBed) => $eBed['drawn'])->count(), 'les quatre autres sont déjà tracées');

		$this->assertEquals(\ViewAction::class, $this->executeDrawing($ePlot, self::SEASON, $data), 'le clic sur le jardin rend le même niveau');
		$this->assertEquals(['P3'], $data->ePlot['cBedUndrawn']->getColumn('name'), 'avec la même planche à tracer');
		$this->assertTrue($data->ePlot['cBedSelected']->empty(), 'et rien de coché : aucune adresse ne désigne de planche, et une case ne se coche que d\'un geste');

	}

	public function testFormerAddressLeadsToTheMap(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin de l\'ancienne adresse');
		$cBed = $this->createBeds($ePlot, 2);
		$bed = $cBed->first()['id'];

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\RedirectAction::class, $this->executePage($this->eStructure['id'], ['plot' => $ePlot['id'], 'ids' => [$bed]], $data, 'planches', $redirect), 'l\'ancienne adresse du dessin renvoie');
		$this->assertTrue(str_ends_with($redirect, '/cartographie/'.self::SEASON.'?zone='.$eZone['id'].'&plot='.$ePlot['id'].'&beds=1&ids[]='.$bed), 'vers la Carte, sur le jardin et les planches qu\'elle désignait');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1, 'ids' => [$bed]], $data);
		$this->assertEquals($ePlot['id'], $data->ePlotDrawing['id'], 'où ses planches s\'ouvrent');
		$this->assertEquals([$bed], $data->ePlotDrawing['cBedSelected']->getIds(), 'avec celle qu\'elle désignait');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'ids' => [$bed]], $data);
		$this->assertEquals($ePlot['id'], $data->ePlotDrawing['id'], 'une planche désignée suffit à les ouvrir, comme une demande de dessin');

		$this->assertEquals(\RedirectAction::class, $this->executePage($this->eStructure['id'], ['plot' => 0], $data, 'planches', $redirect), 'un jardin inconnu renvoie aussi');
		$this->assertTrue(str_ends_with($redirect, '/cartographie/'.self::SEASON), 'vers la Carte de la saison, sans jardin');

	}

	public function testReaderHasNoConsole(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin de console');
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: FALSE);

		$this->assertEquals(\ViewAction::class, $this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data), 'un lecteur de la production ouvre la Carte sur le jardin');
		$this->assertTrue($data->ePlotDrawing->empty(), 'qui reste parmi ceux de sa parcelle, même quand l\'adresse demande ses planches');
		$this->assertEquals(\NotAllowedAction::class, $this->executeDrawing($ePlot, self::SEASON, $data), 'le clic qui les ouvre lui est refusé');

	}

	public function testStrangerHasNoConsole(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin de console');
		$this->createBeds($ePlot, 2);

		$this->connectAs([], admin: FALSE);

		$this->assertEquals(\NotAllowedAction::class, $this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id']], $data), 'un utilisateur étranger à la structure n\'ouvre pas sa Carte');
		$this->assertEquals(\NotAllowedAction::class, $this->executeDrawing($ePlot, self::SEASON, $data), 'ni les planches d\'un de ses jardins');

	}

	public function testPlotOfAnotherStructureIsNotFound(): void {

		$eStructureOther = \structure\StructureLib::getById(\structure\FixtureAiLib::F2);

		$eZoneOther = $this->createZone($eStructureOther);
		$ePlotOther = $this->createPlot($eZoneOther, 'Jardin de l\'autre structure');
		$this->createBeds($ePlotOther, 2);

		$this->connectAs([$this->eStructure['id'], $eStructureOther['id']], admin: TRUE);

		$this->executePage($eStructureOther['id'], ['zone' => $eZoneOther['id'], 'plot' => $ePlotOther['id'], 'beds' => 1], $data);
		$this->assertEquals($ePlotOther['id'], $data->ePlotDrawing['id'], 'le membre des deux structures ouvre le jardin sous sa structure');

		$this->executePage($this->eStructure['id'], ['zone' => $eZoneOther['id'], 'plot' => $ePlotOther['id'], 'beds' => 1], $data);
		$this->assertTrue($data->ePlotDrawing->empty(), 'le même jardin ne s\'ouvre pas sous l\'adresse de l\'autre structure, qu\'il administre aussi');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->executeDrawing($ePlotOther, self::SEASON, $data), 'un administrateur de la seule première structure n\'ouvre pas les planches d\'un jardin de l\'autre');

	}

	public function testPlotWithoutShapeListsItsBedsWithoutDrawingThem(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans forme', drawn: FALSE);
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data);
		$this->assertEquals($ePlot['id'], $data->ePlotDrawing['id'], 'un jardin sans contour ouvre aussi ses planches');
		$this->assertFalse($data->ePlotDrawing['drawable'], 'sans forme où les dessiner');

		$this->assertEquals(\ViewAction::class, $this->executeDrawing($ePlot, self::SEASON, $data), 'comme au clic sur « Planches »');
		$this->assertEquals(2, $data->ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE)->count(), 'qui les liste');

	}

	public function testBedsLaidOnTheZoneOpenOnItsShape(): void {

		$eZone = $this->createZone($this->eStructure);
		// Le jardin que la parcelle reçoit à sa création, pour les planches posées à même elle
		$ePlot = Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data), 'des planches posées à même une parcelle tracée s\'ouvrent sur la Carte');
		$this->assertTrue($data->ePlotDrawing['zoneFill'], 'le jardin ouvert est celui de la parcelle');

	}

	/**
	 * Des planches posées à même la parcelle se listent comme un jardin sans en être un : sur la carte, leur rangée porte le
	 * nom de la parcelle, et les compteurs de jardins les taisent. Le clic qui les ouvre est dans cartography.js (bed-fill
	 * de bindClicks()), vu dans Chrome.
	 */
	public function testBedsLaidOnTheZoneCarryItsNameAndAreNotCounted(): void {

		$eZone = $this->createZone($this->eStructure);
		$this->createPlot($eZone, 'Parcours poules');
		$ePlotFill = Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();
		$this->createBeds($ePlotFill, 3);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlotFill['id'], 'beds' => 1], $data), 'des planches posées à même la parcelle s\'ouvrent sur la Carte');

		$eZoneLoaded = $data->eZone;
		$ePlotLoaded = $data->ePlotDrawing;

		$head = new \ReflectionMethod(DrawUi::class, 'getPlotHead')->invoke(new DrawUi(), $eZoneLoaded, $ePlotLoaded, $ePlotLoaded['cBed']);
		$this->assertTrue(str_contains($head, encode($eZone['name'])), 'la rangée porte le nom de la parcelle');

		$level = new \ReflectionMethod(PlanUi::class, 'getMapZoneLevel')->invoke(new PlanUi(), $this->eStructure, $eZoneLoaded, TRUE, new Plot());
		$this->assertTrue(str_contains($level, 'data-plot="'.$ePlotFill['id'].'"'), 'la liste de la parcelle propose toujours ses planches');
		// Le parcours est tracé, les planches de la parcelle restent à tracer : chacun a sa section
		$this->assertEquals(['1'], preg_match_all('/>([0-9]+)<\/span>/', $level, $matches) ? $matches[1] : [], 'seul le parcours est compté ; les planches posées à même la parcelle n\'ajoutent aucun compteur à ce qui reste à dessiner');

	}

	public function testBedsLaidOnAZoneWithoutShapeCannotBeDrawn(): void {

		$eZone = $this->createZone($this->eStructure, drawn: FALSE);
		$ePlot = Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data);
		$this->assertFalse($data->ePlotDrawing['drawable'], 'sans forme de parcelle, ses planches n\'ont pas où se tracer');

	}

	public function testUndrawnZoneIsOnlyDesignatedByTheGestureThatDrawsIt(): void {

		$eZone = $this->createZone($this->eStructure, drawn: FALSE);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		if($this->assertDecorPresent(\map\Zone::model()->whereStructure($this->eStructure)->count() === 1, 'la structure du test n\'a que la parcelle qu\'il crée') === FALSE) {
			return;
		}

		$this->executePage($this->eStructure['id'], [], $data);
		$this->assertTrue($data->eZone->empty(), 'seule, une parcelle sans contour n\'est pas désignée d\'office : la carte s\'ouvre sur la liste');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id']], $data);
		$this->assertTrue($data->eZone->empty(), 'une adresse qui revient à une parcelle sans contour s\'ouvre aussi sur la liste, sans lancer son tracé');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'draw' => 1], $data);
		$this->assertEquals($eZone['id'], $data->eZone['id'], '« Dessiner la parcelle » la désigne, ce qui lance son tracé');

		$ePlot = $this->createPlot($eZone, 'Jardin tracé d\'une parcelle sans contour');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id']], $data);
		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'l\'adresse d\'un jardin garde sa parcelle, même sans contour, et mène à lui');

		$eZoneDrawn = $this->createZone($this->eStructure);

		$this->executePage($this->eStructure['id'], ['zone' => $eZoneDrawn['id']], $data);
		$this->assertEquals($eZoneDrawn['id'], $data->eZone['id'], 'une parcelle tracée se désigne par son adresse seule');

	}

	public function testPlotWithoutBedOpensOnAnEmptyList(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans planche');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeDrawing($ePlot, self::SEASON, $data), '« Planches » ouvre un jardin sans planche');
		$this->assertTrue($data->ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE)->empty(), 'sur une liste vide, d\'où l\'on ajoute ses premières planches');

	}

	public function testUntouchedPlotHasEveryBedToDraw(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin vierge');
		$this->createBeds($ePlot, 3);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);
		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id']], $data);

		$this->assertEquals(['P1', 'P2', 'P3'], $data->ePlot['cBedUndrawn']->getColumn('name'), 'un jardin sans tracé a toutes ses planches à tracer');
		$this->assertEquals(0, $data->ePlot['cBed']->find(fn($eBed) => $eBed['drawn'])->count(), 'aucune n\'est déjà tracée');

	}

	public function testEarlierSeasonDrawingIsInherited(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin hérité');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P4'), self::SEASON - 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);
		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id']], $data);

		$this->assertEquals(['P4'], $data->ePlot['cBedUndrawn']->getColumn('name'), 'le tracé de la saison précédente vaut pour la saison montrée, seule la planche qu\'il ne porte pas reste à tracer');
		$this->assertEquals(self::SEASON - 1, $data->ePlot['cDraw']->first()['season'], 'la carte reçoit les traits de la saison précédente');

	}

	public function testAddressChecksItsBedsOfThePlotAndIgnoresTheOthers(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin de l\'adresse');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P4'), self::SEASON);

		$ePlotOther = $this->createPlot($eZone, 'Jardin voisin');
		$cBedOther = $this->createBeds($ePlotOther, 1);

		$ids = $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2']))->getIds();

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'ids' => array_merge($ids, $cBedOther->getIds(), ['0', 'P3'])], $data);
		$this->assertEquals(['P1', 'P2'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'deux planches tracées de l\'adresse se cochent seules, sans le reste de leur trait, ni la planche d\'un autre jardin ni une valeur qui n\'est pas une planche');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'ids' => $cBed->find(fn($eBed) => $eBed['name'] !== 'P4')->getIds()], $data);
		$this->assertEquals(['P1', 'P2', 'P3'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'toutes les planches d\'un trait');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'ids' => array_merge($ids, $cBed->find(fn($eBed) => $eBed['name'] === 'P4')->getIds())], $data);
		$this->assertEquals(['P1', 'P2', 'P4'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'une planche à tracer se coche avec des planches tracées');

		$this->assertTrue($data->ePlot['cBedSelected']->offsetExists($cBed->find(fn($eBed) => $eBed['name'] === 'P4')->first()['id']), 'la sélection se lit par l\'id de la planche, d\'où la liste coche ses cases');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'ids' => $cBedOther->getIds()], $data);
		$this->assertTrue($data->ePlot['cBedSelected']->empty(), 'une adresse qui ne désigne aucune planche du jardin n\'en coche aucune');

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data);
		$this->assertTrue($data->ePlot['cBedSelected']->empty(), 'comme une adresse sans planche, même quand il en reste à tracer');

	}

	public function testSeasonWithoutThePlotSendsBackToItsTab(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin arrêté', seasonLast: self::SEASON - 1);
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->executePage($this->eStructure['id'], ['zone' => $eZone['id'], 'plot' => $ePlot['id'], 'beds' => 1], $data);
		$this->assertTrue($data->ePlotDrawing->empty(), 'un jardin absent de la saison ne s\'ouvre pas sur la Carte de cette saison');
		$this->assertEquals('map\\Draw::season.exploited', $this->executeDrawing($ePlot, self::SEASON, $data), 'et le clic qui l\'ouvrirait, resté dans une page d\'une autre saison, est refusé');

	}

	/**
	 * Joue « Terminer » : le POST du geste, avec ce que `MapDrawingLine.getBody()` envoie. Rend la classe de
	 * l'action levée, et dans `$data` ce que la réponse ciblée reçoit.
	 */
	private function executeGesture(Plot $ePlot, array $ids, int $season, ?\stdClass &$data, string $action = 'doUpdateBedLineCollection', array $more = []): string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		// Chaque planche à sa place, comme MapDrawingLine.getBody() les poste : côte à côte vers l'est, la largeur plein nord
		$input = $more + [
			'plot' => (string)$ePlot['id'],
			'season' => (string)$season,
			'ids' => array_map(strval(...), $ids),
			'corner' => array_combine($ids, array_map(fn(int $position) => json_encode([3.0995 + $position * 0.00003, 45.5995]), array_keys($ids))),
			'angle' => array_fill_keys($ids, '0')
		];

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'map/bed:'.$action), $data);
			return 'aucune action';
		} catch(\FailAction $action) {
			// Un refus se nomme : la classe seule laisserait passer un autre refus que celui que le cas attend
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : $fail->format()[0][0];
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	private function executeShape(Plot|Zone $e, ?array $coordinates, ?\stdClass &$data, array $more = []): string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		$input = [
			'id' => (string)$e['id'],
			'season' => (string)self::SEASON,
			'coordinates' => json_encode($coordinates),
			'area' => '100'
		] + $more;

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, ($e instanceof Zone) ? 'map/zone:doUpdateShape' : 'map/plot:doUpdateShape'), $data);
			return 'aucune action';
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : $fail->format()[0][0];
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	public function testPlotShapeLeavesThePlotDesignated(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans forme', drawn: FALSE);
		$this->createBeds($ePlot, 3);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlot, self::SQUARE, $data), 'un administrateur trace le contour d\'un jardin sur la carte');

		$this->assertEquals(self::SQUARE, PlotLib::getById($ePlot['id'])['coordinates'], 'le contour est enregistré');
		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'la réponse rend le jardin qui a gagné sa forme, désigné sans ses planches, que « Planches » ouvre');
		$this->assertEquals(3, $data->ePlot['bedsUndrawn'], 'toutes restent à tracer');

	}

	public function testPlotShapeDrawnFromItsBedsKeepsThemOpen(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin tiré de ses planches', drawn: FALSE);
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlot, self::SQUARE, $data, ['beds' => '1']), 'un contour tiré depuis les planches ouvertes s\'enregistre');
		$this->assertTrue($data->bedsShown, 'la réponse rend les planches ouvertes');
		$this->assertEquals(2, $data->ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE)->count(), 'le troisième niveau porte les planches du jardin');
		$this->assertTrue($data->ePlot->offsetExists('cBedSelected'), 'et ce que la console vise, aucune planche cochée');

		$this->executeShape($ePlot, self::SQUARE, $data);
		$this->assertFalse($data->bedsShown, 'tiré depuis le jardin désigné, il reste désigné');

	}

	private function executeDuplicate(Plot $ePlot, string $name, ?array $placement, ?\stdClass &$data): string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		$input = [
			'id' => (string)$ePlot['id'],
			'season' => (string)self::SEASON,
			'name' => $name
		];

		if($placement !== NULL) {
			$input['placement'] = json_encode($placement);
		}

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'map/plot:doDuplicate'), $data);
			return 'aucune action';
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : $fail->format()[0][0];
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	private function round(array $points): array {
		return array_map(fn(array $point) => [round($point[0], 7), round($point[1], 7)], $points);
	}

	public function testShapeTurnedInTheLocalPlaneKeepsItsSides(): void {

		// Un rectangle de 20 m d'est en ouest et 10 m du sud au nord, à la latitude de la France
		$origin = [3.1, 45.6];
		$scale = \map\MapSetting::PLANE_METERS * cos(deg2rad($origin[1]));
		$rectangle = [[3.1, 45.6], [3.1 + 20 / $scale, 45.6], [3.1 + 20 / $scale, 45.6 + 10 / \map\MapSetting::PLANE_METERS], [3.1, 45.6 + 10 / \map\MapSetting::PLANE_METERS]];

		$turned = MapLib::getTransformed($rectangle, MapLib::getOrigin($rectangle), [0, 0], 90);

		$side = fn(array $from, array $to) => hypot(($to[0] - $from[0]) * $scale, ($to[1] - $from[1]) * \map\MapSetting::PLANE_METERS);

		$this->assertTrue(abs($side($turned[0], $turned[1]) - 20) <= 0.01, 'le grand côté garde ses 20 m');
		$this->assertTrue(abs($side($turned[1], $turned[2]) - 10) <= 0.01, 'le petit côté garde ses 10 m');
		$this->assertTrue(abs(($turned[1][0] - $turned[0][0]) * $scale) <= 0.01, 'tourné d\'un quart de tour, le grand côté va du nord au sud');

		$moved = MapLib::getTransformed($rectangle, MapLib::getOrigin($rectangle), [4, -2], 0);

		$this->assertTrue(abs(($moved[0][0] - $rectangle[0][0]) * $scale - 4) <= 0.001, 'le décalage se compte en mètres vers l\'est');
		$this->assertTrue(abs(($moved[0][1] - $rectangle[0][1]) * \map\MapSetting::PLANE_METERS + 2) <= 0.001, 'et vers le nord');

	}

	public function testPlotDuplicatedOnTheMapTakesItsDrawsAlong(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin copié');
		$cBed = $this->createBeds($ePlot, 3);
		[$eBed1, $eBed2, $eBed3] = array_values($cBed->makeArray(fn(Bed $eBed) => $eBed));

		// L'an passé, deux traits ; cette saison, effacée, n'en garde qu'une marque vide
		$this->draw($ePlot, new \Collection([$eBed1['id'] => $eBed1, $eBed2['id'] => $eBed2]), self::SEASON - 1);
		$this->draw($ePlot, new \Collection([$eBed3['id'] => $eBed3]), self::SEASON - 1);
		// Sans build() : la saison effacée est le décor du cas, aucun formulaire ne la poste
		DrawLib::deleteByBeds(new Draw(['plot' => $ePlot, 'season' => self::SEASON]), $cBed);

		// La planche du second trait n'est plus : son trait ne marquait rien et n'a pas à être recopié
		BedLib::delete($eBed3);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$placement = ['shift' => [30, -5], 'angle' => 15];

		$this->assertEquals(\ViewAction::class, $this->executeDuplicate(PlotLib::getById($ePlot['id']), 'Jardin copié bis', $placement, $data), 'un administrateur pose la copie d\'un jardin dessiné à côté de lui');

		$ePlotCopy = PlotLib::getById($data->ePlotDuplicated['id']);
		$origin = MapLib::getOrigin(self::SQUARE);

		$this->assertEquals($this->round(MapLib::getTransformed(self::SQUARE, $origin, $placement['shift'], $placement['angle'])), $this->round($ePlotCopy['coordinates']), 'la copie prend le contour de l\'original, tourné puis décalé');

		$cBedCopy = Bed::model()
			->select(['id', 'name'])
			->wherePlot($ePlotCopy)
			->wherePlotFill(FALSE)
			->getCollection(index: 'name');

		$this->assertEquals(['P1', 'P2'], $cBedCopy->getKeys(), 'les planches actives sont copiées');

		$rowsCopy = Draw::model()
			->select(['season', 'bed'])
			->wherePlot($ePlotCopy)
			->sort(['season' => SORT_ASC, 'bed' => SORT_ASC])
			->getCollection()
			->makeArray(fn(Draw $eDraw) => [$eDraw['season'], $eDraw['bed']->empty() ? NULL : $eDraw['bed']['id']]);

		// La place des copies, tournée et décalée, est éprouvée par DrawSeason (testDuplicatedPlotTurnsCornersAndAngles)
		$this->assertEquals([
			[self::SEASON - 1, $cBedCopy['P1']['id']],
			[self::SEASON - 1, $cBedCopy['P2']['id']],
			[self::SEASON, NULL]
		], $rowsCopy, 'une ligne par copie de planche dans la saison de l\'original, et la saison effacée garde sa marque');

	}

	public function testPlotDuplicatedWithoutPlacementStaysOffTheMap(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin copié sans carte');
		$this->draw($ePlot, $this->createBeds($ePlot, 2), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeDuplicate(PlotLib::getById($ePlot['id']), 'Copie hors carte', NULL, $data), 'une copie sans placement s\'enregistre');

		$this->assertEquals(NULL, PlotLib::getById($data->ePlotDuplicated['id'])['coordinates'], 'elle n\'a pas de contour');
		$this->assertFalse(Draw::model()->wherePlot($data->ePlotDuplicated)->exists(), 'ni de traits');

	}

	public function testPlotDuplicatedRefusesAPlacementItCannotTake(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlotUndrawn = $this->createPlot($eZone, 'Jardin sans contour à copier', drawn: FALSE);
		$ePlot = $this->createPlot($eZone, 'Jardin à copier trop loin');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Plot::placement.shape', $this->executeDuplicate(PlotLib::getById($ePlotUndrawn['id']), 'Copie', ['shift' => [4, 0], 'angle' => 0], $data), 'un jardin sans contour ne se pose pas en copie');
		$this->assertEquals('Plot::placement.check', $this->executeDuplicate(PlotLib::getById($ePlot['id']), 'Copie', ['shift' => [\map\MapSetting::COPY_SHIFT_MAX + 1, 0], 'angle' => 0], $data), 'ni à plus d\'un kilomètre');
		$this->assertEquals('Plot::placement.check', $this->executeDuplicate(PlotLib::getById($ePlot['id']), 'Copie', ['shift' => [4], 'angle' => 0], $data), 'ni sur un décalage incomplet');

	}

	// Les coins des planches du jardin, de toutes ses saisons, arrondis au dix-millionième de degré, où s'arrêtent les flottants qu'on y ajoute
	private function getDrawCorners(Plot $ePlot): array {

		return Draw::model()
			->select(['corner'])
			->wherePlot($ePlot)
			->whereBed('!=', NULL)
			->sort('id')
			->getCollection()
			->makeArray(fn(Draw $eDraw) => [round($eDraw['corner'][0], 7), round($eDraw['corner'][1], 7)]);

	}

	// Les quatre coins de chaque planche tracée du jardin, quelle que soit celle d'où part son écriture
	private function getDrawRectangles(Plot $ePlot): array {

		return Draw::model()
			->select([
				'corner', 'angle',
				'bed' => ['length', 'width']
			])
			->wherePlot($ePlot)
			->whereBed('!=', NULL)
			->sort('id')
			->getCollection()
			->makeArray(function(Draw $eDraw) {
				[$corner, $across, $along] = DrawLib::getLine($eDraw, $eDraw['bed']);
				return [$corner, $across, $along, [$corner[0] + $along[0] - $across[0], $corner[1] + $along[1] - $across[1]]];
			});

	}

	private function getMoved(array $rectangles, array $origin, array $shift, float $angle): array {
		return array_map(fn(array $rectangle) => MapLib::getTransformed($rectangle, $origin, $shift, $angle), $rectangles);
	}

	// Chaque coin attendu retrouve un coin de la même planche au centimètre, dans un ordre qui dépend du coin d'où part l'écriture
	private function isNearRectangles(array $expected, array $rectangles): bool {

		if(array_keys($expected) !== array_keys($rectangles)) {
			return FALSE;
		}

		foreach($expected as $id => $points) {
			foreach($points as $point) {

				$distances = array_map(fn(array $corner) => MapLib::getDistance($point, $corner), $rectangles[$id]);

				if(min($distances) > 0.01) {
					return FALSE;
				}

			}
		}

		return TRUE;

	}

	private function shift(array $points, array $shift): array {
		return array_map(fn(array $point) => [round($point[0] + $shift[0], 7), round($point[1] + $shift[1], 7)], $points);
	}

	public function testPlotShapeMovedTakesItsDrawsAlong(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin déplacé');
		$ePlotOther = $this->createPlot($eZone, 'Jardin resté');

		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed, self::SEASON - 1);
		$this->draw($ePlot, $cBed, self::SEASON);
		$this->draw($ePlotOther, $this->createBeds($ePlotOther, 1), self::SEASON);

		$before = $this->getDrawCorners($ePlot);
		$beforeOther = $this->getDrawCorners($ePlotOther);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$shift = [0.0001, -0.00005];

		// Le navigateur poste l'anneau sans le refermer, la base le garde refermé
		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlot, array_slice($this->shift(self::SQUARE, $shift), 0, -1), $data), 'un jardin tiré d\'un bloc s\'enregistre');

		$this->assertEquals(4, count($before), 'le jardin porte une ligne par planche et par saison');
		$this->assertEquals($this->shift($before, $shift), $this->getDrawCorners($ePlot), 'ses planches de chaque saison le suivent du même décalage');
		$this->assertEquals($beforeOther, $this->getDrawCorners($ePlotOther), 'celles d\'un autre jardin restent');

	}

	// Le panneau et l'API écrivent par PlotLib::update(), comme la carte : un contour tiré d'un bloc y entraîne aussi les traits
	public function testPlotMovedOutsideTheMapTakesItsDrawsAlong(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin déplacé par son panneau');

		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed, self::SEASON - 1);
		$this->draw($ePlot, $cBed, self::SEASON);

		$before = $this->getDrawCorners($ePlot);
		$shift = [-0.00005, 0.0001];

		$ePlot['coordinates'] = $this->shift(self::SQUARE, $shift);
		PlotLib::update($ePlot, ['coordinates']);

		$this->assertEquals($this->shift($before, $shift), $this->getDrawCorners($ePlot), 'ses planches de chaque saison le suivent du même décalage');

		$moved = $this->getDrawCorners($ePlot);

		$ePlot['coordinates'][2] = [$ePlot['coordinates'][2][0] + 0.0002, $ePlot['coordinates'][2][1]];
		PlotLib::update($ePlot, ['coordinates']);

		$this->assertEquals($moved, $this->getDrawCorners($ePlot), 'un sommet tiré seul laisse les planches où elles sont');

		$ePlot['name'] = 'Jardin renommé';
		PlotLib::update($ePlot, ['name']);

		$this->assertEquals($moved, $this->getDrawCorners($ePlot), 'une écriture qui ne touche pas au contour aussi');

	}

	public function testPlotShapeTurnedTakesItsDrawsAlong(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin tourné');
		$ePlotOther = $this->createPlot($eZone, 'Jardin resté');

		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed, self::SEASON - 1);
		$this->draw($ePlot, $cBed, self::SEASON);
		$this->draw($ePlotOther, $this->createBeds($ePlotOther, 1), self::SEASON);

		$before = $this->getDrawRectangles($ePlot);
		$beforeOther = $this->getDrawCorners($ePlotOther);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$vertices = array_slice(self::SQUARE, 0, -1);
		$origin = MapLib::getOrigin($vertices);

		// Tourné de 200°, ses planches passent par l'angle où leur écriture repart du coin opposé (DrawLib::getCanonical())
		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlot, MapLib::getTransformed($vertices, $origin, [0, 0], 200), $data), 'un jardin tourné s\'enregistre');

		$this->assertTrue($this->isNearRectangles($this->getMoved($before, $origin, [0, 0], 200), $this->getDrawRectangles($ePlot)), 'ses planches de chaque saison tournent avec lui');
		$this->assertEquals($beforeOther, $this->getDrawCorners($ePlotOther), 'celles d\'un autre jardin restent');

		// Par le panneau et l'API, tourné et décalé à la fois
		$moved = $this->getDrawRectangles($ePlot);
		$coordinates = MapLib::getVertices($data->e['coordinates']);
		$ePlot = PlotLib::getById($ePlot['id']);
		$ePlot['coordinates'] = MapLib::getTransformed($coordinates, MapLib::getOrigin($coordinates), [12, -5], -37);
		PlotLib::update($ePlot, ['coordinates']);

		$this->assertTrue($this->isNearRectangles($this->getMoved($moved, MapLib::getOrigin($coordinates), [12, -5], -37), $this->getDrawRectangles($ePlot)), 'ses planches suivent la rotation et le décalage');

	}

	public function testPlotFillingItsZoneTakesNoShapeOfItsOwn(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlotFill = Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();
		$ePlot = $this->createPlot($eZone, 'Jardin tracé');

		$coordinates = json_encode(array_slice($this->shift(self::SQUARE, [0.0001, 0.0001]), 0, -1));

		$fw = new \FailWatch();
		$ePlotFill->build(['coordinates'], ['coordinates' => $coordinates], new \PropertyList('update'));
		$this->assertTrue($fw->has('Plot::coordinates.filled'), 'des planches posées à même la parcelle refusent un contour dès la construction, le panneau et l\'API étant fermés avant par Plot::acceptUpdate()');

		$fw = new \FailWatch();
		$ePlotFill->build(['coordinates'], ['coordinates' => NULL], new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'elles gardent l\'absence de contour');

		$fw = new \FailWatch();
		$ePlot->build(['coordinates'], ['coordinates' => $coordinates], new \PropertyList('update'));
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

	}

	public function testZoneShapeMovedTakesItsPlotsAlong(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlotDrawn = $this->createPlot($eZone, 'Jardin tracé');
		$ePlotUndrawn = $this->createPlot($eZone, 'Jardin sans contour', drawn: FALSE);
		$this->draw($ePlotDrawn, $this->createBeds($ePlotDrawn, 2), self::SEASON);
		$this->draw($ePlotUndrawn, $this->createBeds($ePlotUndrawn, 1), self::SEASON - 1);

		$eZoneOther = $this->createZone($this->eStructure);
		$ePlotOther = $this->createPlot($eZoneOther, 'Jardin d\'une autre parcelle');
		$this->draw($ePlotOther, $this->createBeds($ePlotOther, 1), self::SEASON);

		$before = [$this->getDrawCorners($ePlotDrawn), $this->getDrawCorners($ePlotUndrawn)];
		$beforeOther = $this->getDrawCorners($ePlotOther);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$shift = [0.0001, -0.00005];

		// Le navigateur poste l'anneau sans le refermer, la base le garde refermé
		$this->assertEquals(\ViewAction::class, $this->executeShape($eZone, array_slice($this->shift(self::SQUARE, $shift), 0, -1), $data), 'une parcelle tirée d\'un bloc s\'enregistre');

		$round = fn(?array $points) => ($points === NULL) ? NULL : array_map(fn(array $point) => [round($point[0], 7), round($point[1], 7)], $points);

		$this->assertEquals($this->shift(self::SQUARE, $shift), $round(PlotLib::getById($ePlotDrawn['id'])['coordinates']), 'son jardin tracé la suit du même décalage');
		$this->assertEquals(NULL, PlotLib::getById($ePlotUndrawn['id'])['coordinates'], 'un jardin sans contour n\'en gagne pas');
		$this->assertEquals([
			$this->shift($before[0], $shift),
			$this->shift($before[1], $shift)
		], [$this->getDrawCorners($ePlotDrawn), $this->getDrawCorners($ePlotUndrawn)], 'les planches de tous ses jardins, de toutes les saisons, aussi');
		$this->assertEquals(self::SQUARE, $round(PlotLib::getById($ePlotOther['id'])['coordinates']), 'le jardin d\'une autre parcelle reste');
		$this->assertEquals($beforeOther, $this->getDrawCorners($ePlotOther), 'et ses planches');

		// Retracée sommet par sommet, elle laisse ses jardins où ils sont
		$square = $this->shift(self::SQUARE, $shift);
		$square[2] = [$square[2][0] + 0.0002, $square[2][1]];
		$this->assertEquals(\ViewAction::class, $this->executeShape($eZone, $square, $data), 'une parcelle retracée s\'enregistre');
		$this->assertEquals($this->shift(self::SQUARE, $shift), $round(PlotLib::getById($ePlotDrawn['id'])['coordinates']), 'sans décalage, ses jardins restent où ils sont');

	}

	public function testZoneShapeTurnedTakesItsPlotsAlong(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin tracé');
		$this->draw($ePlot, $this->createBeds($ePlot, 2), self::SEASON);

		$eZoneOther = $this->createZone($this->eStructure);
		$ePlotOther = $this->createPlot($eZoneOther, 'Jardin d\'une autre parcelle');
		$this->draw($ePlotOther, $this->createBeds($ePlotOther, 1), self::SEASON);

		$before = $this->getDrawRectangles($ePlot);
		$beforeOther = $this->getDrawCorners($ePlotOther);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$vertices = array_slice(self::SQUARE, 0, -1);
		$origin = MapLib::getOrigin($vertices);
		$turned = MapLib::getTransformed($vertices, $origin, [3, 4], 30);

		$this->assertEquals(\ViewAction::class, $this->executeShape($eZone, $turned, $data), 'une parcelle tournée s\'enregistre');

		$this->assertTrue($this->isNear(MapLib::getVertices(PlotLib::getById($ePlot['id'])['coordinates']), $turned), 'son jardin tourne avec elle');
		$this->assertTrue($this->isNearRectangles($this->getMoved($before, $origin, [3, 4], 30), $this->getDrawRectangles($ePlot)), 'et ses planches');
		$this->assertEquals($beforeOther, $this->getDrawCorners($ePlotOther), 'les planches d\'une autre parcelle restent');

	}

	public function testPlotShapeRedrawnLeavesItsDraws(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin retracé');
		$this->draw($ePlot, $this->createBeds($ePlot, 2), self::SEASON);

		$before = $this->getDrawCorners($ePlot);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$square = self::SQUARE;
		$square[2] = [3.1012, 45.6012];

		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlot, $square, $data), 'un contour retracé sommet par sommet s\'enregistre');
		$this->assertEquals($before, $this->getDrawCorners($ePlot), 'sans décalage, ses planches restent où elles sont');

	}

	public function testMotionReadsVerticesAsTheBrowserDoes(): void {

		$vertices = array_slice(self::SQUARE, 0, -1);
		$origin = MapLib::getOrigin($vertices);

		$after = array_slice($this->shift(self::SQUARE, [0.0001, -0.00005]), 0, -1);

		// Un contour ancien, refermé deux fois et qui répète un sommet
		$before = self::SQUARE;
		array_splice($before, 2, 0, [self::SQUARE[1]]);
		$before[] = self::SQUARE[0];

		$this->assertTrue($this->isMotion(self::SQUARE, $after), 'un anneau refermé tiré d\'un bloc a un mouvement');
		$this->assertEquals(0.0, round(MapLib::getMotion(self::SQUARE, $after)['angle'] ?? 1, 6), 'sans angle');
		$this->assertTrue($this->isMotion($before, $after), 'un anneau refermé deux fois, à sommet répété, aussi');

		// Un glisser dont les flottants rendaient -1,1e-13° : ajouté à une planche posée à 0, il l'aurait fait réécrire depuis le coin opposé
		$dragged = [[0.062459849315909644, 48.89244469157068], [0.06417499004284623, 48.89244469157068], [0.06417499004284623, 48.8934813569276], [0.062459849315909644, 48.8934813569276]];
		$this->assertEquals(0.0, MapLib::getMotion($dragged, $this->shift($dragged, [2.6905348304149368E-5, -8.247170671004417E-5]))['angle'] ?? NULL, 'un glisser n\'a aucun angle, pas même de l\'ordre du flottant');

		$turned = MapLib::getTransformed($vertices, $origin, [0, 0], 37);
		$this->assertTrue($this->isMotion(self::SQUARE, $turned), 'un contour tourné a un mouvement');
		$this->assertEquals(37.0, round(MapLib::getMotion(self::SQUARE, $turned)['angle'] ?? 0, 6), 'de son angle, dans le sens horaire');

		$turned = MapLib::getTransformed($vertices, $origin, [12, -5], -150);
		$this->assertTrue($this->isMotion(self::SQUARE, $turned), 'tourné et décalé aussi');
		$this->assertEquals([12.0, -5.0, -150.0], array_map(fn($value) => round($value, 6), [...MapLib::getMotion(self::SQUARE, $turned)['shift'] ?? [0, 0], MapLib::getMotion(self::SQUARE, $turned)['angle'] ?? 0]), 'décalé en mètres, l\'angle ramené entre -180° et 180°');

		// Un jardin d'un kilomètre de côté, où une approximation de l'angle écarterait ses coins de plusieurs centimètres
		$large = [[3.0900, 45.5900], [3.1028, 45.5900], [3.1028, 45.5990], [3.0900, 45.5990]];
		$this->assertTrue($this->isMotion($large, MapLib::getTransformed($large, MapLib::getOrigin($large), [0, 300], 37)), 'un grand contour tourné et décalé a un mouvement');

		$this->assertEquals(NULL, MapLib::getMotion(self::SQUARE, $vertices), 'un contour resté en place n\'en a pas');

		$pulled = $after;
		$pulled[2] = [$pulled[2][0] + 0.0001, $pulled[2][1]];
		$this->assertEquals(NULL, MapLib::getMotion(self::SQUARE, $pulled), 'un sommet tiré seul non plus');
		$this->assertEquals(NULL, MapLib::getMotion(self::SQUARE, array_slice($after, 0, -1)), 'ni un sommet retiré');
		$this->assertEquals(NULL, MapLib::getMotion(NULL, $after), 'ni un jardin qui n\'avait pas de contour');

	}

	// Le mouvement rendu porte chaque sommet d'avant sur celui d'après
	private function isMotion(array $before, array $after): bool {

		$motion = MapLib::getMotion($before, $after);

		if($motion === NULL) {
			return FALSE;
		}

		$moved = MapLib::getTransformed(MapLib::getVertices($before), $motion['origin'], $motion['shift'], $motion['angle']);

		return $this->isNear($moved, MapLib::getVertices($after));

	}

	// Deux listes de points qui se superposent point à point, au centimètre
	private function isNear(array $points, array $expected): bool {

		if(count($points) !== count($expected)) {
			return FALSE;
		}

		foreach($points as $index => $point) {
			if(MapLib::getDistance($point, $expected[$index]) > 0.01) {
				return FALSE;
			}
		}

		return TRUE;

	}

	public function testPlotShapeWithoutBedStaysAmongItsZone(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin vide', drawn: FALSE);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlot, self::SQUARE, $data), 'un jardin sans planche reçoit aussi son contour');
		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'et reste désigné parmi ceux de sa parcelle');

	}

	public function testPlotShapeIsRequired(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin tracé');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Plot::coordinates.ring', $this->executeShape($ePlot, NULL, $data), 'la route du contour refuse un contour absent');
		$this->assertEquals(self::SQUARE, PlotLib::getById($ePlot['id'])['coordinates'], 'qui laisse le contour d\'avant');

	}

	public function testGreenhouseShapeGivesItsDimensions(): void {

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ePlotGreenhouse = $this->createPlot($this->createZone($this->eStructure), 'Abri', drawn: FALSE);
		$ePlotGreenhouse['mode'] = Plot::GREENHOUSE;
		$ePlotGreenhouse['length'] = 12;
		$ePlotGreenhouse['width'] = 10;
		$ePlotGreenhouse['area'] = 120;

		// Sans build(), qui exigerait le formulaire entier d'un abri : le cas ne part que d'un abri sans contour
		PlotLib::update($ePlotGreenhouse, ['mode', 'length', 'width', 'area']);

		$this->assertEquals(\ViewAction::class, $this->executeShape($ePlotGreenhouse, self::SQUARE, $data), 'un abri se trace sur la carte');

		$ePlotStored = PlotLib::getById($ePlotGreenhouse['id']);

		$this->assertEquals(self::SQUARE, $ePlotStored['coordinates'], 'le contour est enregistré');
		$dimensions = MapLib::getDimensions(self::SQUARE);

		$this->assertEquals($dimensions, [$ePlotStored['length'], $ePlotStored['width']], 'le tracé donne ses dimensions à l\'abri');
		$this->assertEquals((int)round($dimensions[0] * $dimensions[1]), $ePlotStored['area'], 'qui donnent sa surface');

	}

	public function testPlotShapeIsRefusedWhereItHasNoMeaning(): void {

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$eZone = $this->createZone($this->eStructure);

		$ePlotFill = Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();

		$this->assertEquals(\NotExpectedAction::class, $this->executeShape($ePlotFill, self::SQUARE, $data), 'des planches posées à même la parcelle n\'ont pas d\'autre contour qu\'elle');

		$eZoneUndrawn = $this->createZone($this->eStructure, drawn: FALSE);
		$ePlotUndrawn = $this->createPlot($eZoneUndrawn, 'Jardin sans parcelle tracée', drawn: FALSE);

		$this->assertEquals(\NotExpectedAction::class, $this->executeShape($ePlotUndrawn, self::SQUARE, $data), 'hors d\'une parcelle tracée, un jardin n\'a pas où se poser');
		$this->assertEquals(NULL, PlotLib::getById($ePlotUndrawn['id'])['coordinates'], 'et rien n\'est écrit');

	}

	public function testReaderCannotPostThePlotShape(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin d\'un autre', drawn: FALSE);

		$this->connectAs([$this->eStructure['id']], admin: FALSE);

		$this->assertEquals(\NotAllowedAction::class, $this->executeShape($ePlot, self::SQUARE, $data), 'un lecteur ne trace pas le contour d\'un jardin');
		$this->assertEquals(NULL, PlotLib::getById($ePlot['id'])['coordinates'], 'rien n\'est écrit');

	}

	private function getBedsDrawnNames(Plot $ePlot): array {
		return array_map(fn(array $bedDrawn) => $bedDrawn['bed']['name'], $ePlot['bedsDrawn']);
	}

	public function testGestureAnswersWithWhatThePlotBecomes(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin du geste');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P4'), self::SEASON - 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $cBed->find(fn($eBed) => $eBed['name'] === 'P4')->getIds(), self::SEASON, $data), 'un administrateur trace la planche qui manque à un jardin hérité');

		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'la réponse porte le jardin du geste');
		$this->assertTrue($data->ePlot['cBedUndrawn']->empty(), 'où plus rien ne reste à tracer : la saison a reçu le tracé hérité et le trait neuf');
		$this->assertEquals(['P4'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'seule la planche tracée y reste cochée');
		$this->assertEquals(['P1', 'P2', 'P3', 'P4'], $this->getBedsDrawnNames($data->ePlot), 'la carte reçoit les planches du tracé hérité et celle du trait neuf');

	}

	public function testRedrawingPartOfALineLeavesTheRestInPlace(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin retracé en partie');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ids = $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2', 'P3']))->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data), 'un administrateur retrace trois planches d\'un trait de quatre');
		$this->assertEquals(['P4', 'P1', 'P2', 'P3'], $this->getBedsDrawnNames($data->ePlot), 'la planche décochée garde sa place, et les trois autres viennent après elle dans l\'ordre du rang neuf');

	}

	// La carte retire les planches de `beds` et repose celles de `bedsDrawn` : une planche décochée n'a pas bougé, elle ne voyage pas
	public function testAnswerCarriesOnlyTheCheckedBeds(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin aux réponses ciblées');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$getNames = fn(array $bedsDrawn) => array_map(fn(array $bedDrawn) => $bedDrawn['bed']['name'], $bedsDrawn);
		$getIds = fn(array $names) => $cBed->find(fn($eBed) => in_array($eBed['name'], $names))->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $getIds(['P2', 'P3']), self::SEASON, $data), 'un administrateur retrace deux planches');
		$this->assertEquals($getIds(['P2', 'P3']), $data->beds, 'la carte retire les deux planches retracées');
		$this->assertEquals(['P2', 'P3'], $getNames($data->bedsDrawn), 'et ne repose qu\'elles');

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $getIds(['P1', 'P2', 'P3']), self::SEASON, $data, 'doUpdateBedLineSpacing'), 'un administrateur recommence le dessin de trois planches');
		$this->assertEquals(['P1', 'P2', 'P3'], $getNames($data->bedsDrawn), 'la quatrième reste où elle est');

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $getIds(['P4']), self::SEASON, $data, 'doDeleteBedLineCollection', ['origin' => PlanUi::VIEW_MAP]), 'un administrateur efface une planche depuis la Carte');
		$this->assertEquals($getIds(['P4']), $data->beds, 'la carte retire la planche effacée');
		$this->assertEquals([], $data->bedsDrawn, 'et n\'a rien à reposer');

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $getIds(['P1']), self::SEASON, $data, 'doDeleteCollection', ['origin' => PlanUi::VIEW_MAP]), 'un administrateur supprime une planche depuis la Carte');
		$this->assertEquals($getIds(['P1']), $data->beds, 'la carte retire la planche supprimée');
		$this->assertEquals([], $data->bedsDrawn, 'sans rien reposer');

	}

	public function testMovedBedTakesItsPlaceAndLeavesTheOthers(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à la planche déplacée');
		$cBed = $this->createBeds($ePlot, 3);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$eBedMoved = $cBed->find(fn($eBed) => $eBed['name'] === 'P2')->first();

		$before = Draw::model()
			->select(['bed', 'corner', 'angle'])
			->wherePlot($ePlot)
			->whereBed('!=', $eBedMoved)
			->getCollection()
			->makeArray(fn(Draw $eDraw) => [$eDraw['bed']['id'], round($eDraw['corner'][0], 7), round($eDraw['corner'][1], 7), $eDraw['angle']]);

		$more = [
			'corner' => [$eBedMoved['id'] => '[3.1002, 45.6001]'],
			'angle' => [$eBedMoved['id'] => '1.2']
		];

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, [$eBedMoved['id']], self::SEASON, $data, more: $more), 'un administrateur déplace et tourne une planche d\'un rang de trois');

		$eDrawMoved = Draw::model()
			->select(['corner', 'angle'])
			->wherePlot($ePlot)
			->whereBed($eBedMoved)
			->get();

		$this->assertEquals([3.1002, 45.6001], [round($eDrawMoved['corner'][0], 7), round($eDrawMoved['corner'][1], 7)], 'la planche déplacée prend le coin posté');
		$this->assertTrue(abs($eDrawMoved['angle'] - 1.2) < 0.0001, 'et l\'angle posté');

		$after = Draw::model()
			->select(['bed', 'corner', 'angle'])
			->wherePlot($ePlot)
			->whereBed('!=', $eBedMoved)
			->getCollection()
			->makeArray(fn(Draw $eDraw) => [$eDraw['bed']['id'], round($eDraw['corner'][0], 7), round($eDraw['corner'][1], 7), $eDraw['angle']]);

		$this->assertEquals($before, $after, 'les deux autres planches ne bougent pas');

		$this->assertEquals([$eBedMoved['id']], $data->ePlot['cBedSelected']->getIds(), 'la planche déplacée reste cochée, prête pour un autre déplacement ou une rotation, seule');

	}

	/**
	 * « Recommencer le dessin » repose les planches dessinées cochées en rang droit (DrawLib::getRespaced()), dans la saison regardée :
	 * celle qui hérite d'un tracé reçoit ses propres lignes, la saison d'origine garde les siennes, et une planche cochée sans dessin
	 * reste sans dessin.
	 */
	public function testRespacingRedrawsTheDrawnBedsInARow(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à recommencer');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] !== 'P4'), self::SEASON - 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$corners = function(int $season) use($ePlot): array {

			$cDraw = Draw::model()
				->select(['bed' => ['name'], 'corner', 'angle'])
				->wherePlot($ePlot)
				->whereSeason($season)
				->getCollection();

			$corners = [];

			foreach($cDraw as $eDraw) {
				$corners[$eDraw['bed']['name']] = [round($eDraw['corner'][0], 7), round($eDraw['corner'][1], 7), round($eDraw['angle'], 4)];
			}

			ksort($corners);

			return $corners;

		};

		$before = $corners(self::SEASON - 1);

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, 'doUpdateBedLineSpacing'), 'un administrateur recommence le dessin de quatre planches cochées');

		$after = $corners(self::SEASON);

		$this->assertEquals(['P1', 'P2', 'P3'], array_keys($after), 'la saison regardée reçoit ses lignes, sans la planche qui n\'était pas dessinée');
		$this->assertEquals($before['P1'], $after['P1'], 'la première planche garde son coin et son angle');
		$this->assertEquals([$after['P1'][2]], array_values(array_unique(array_column($after, 2))), 'les suivantes prennent son angle');
		$this->assertEquals($before, $corners(self::SEASON - 1), 'la saison d\'origine garde ses lignes');

		$selected = $data->ePlot['cBedSelected']->getIds();
		$respaced = $cBed->find(fn($eBed) => $eBed['name'] !== 'P4')->getIds();

		sort($selected);
		sort($respaced);

		$this->assertEquals($respaced, $selected, 'les trois planches replacées restent cochées, prêtes pour Déplacer, sans celle qui n\'était pas dessinée');

	}

	public function testRespacingNeedsTwoDrawnBeds(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin d\'une planche dessinée');
		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed->find(fn($eBed) => $eBed['name'] === 'P1'), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Draw::bed.spaced', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, 'doUpdateBedLineSpacing'), 'une planche dessinée ne fait pas un rang');

	}

	public function testReaderCannotRespace(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin du lecteur');
		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: FALSE);

		$this->assertEquals(\NotAllowedAction::class, $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, 'doUpdateBedLineSpacing'), 'un lecteur ne recommence pas le dessin');

	}

	public function testRespacingStaysInItsStructure(): void {

		$eStructureOther = \structure\StructureLib::getById(\structure\FixtureAiLib::F2);

		$ePlotOther = $this->createPlot($this->createZone($eStructureOther), 'Jardin de l\'autre structure');
		$cBedOther = $this->createBeds($ePlotOther, 2);
		$this->draw($ePlotOther, $cBedOther, self::SEASON);

		$ePlot = $this->createPlot($this->createZone($this->eStructure), 'Jardin de la structure');
		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$drawsOther = fn(): array => Draw::model()->select(['corner', 'angle'])->wherePlot($ePlotOther)->getCollection()->getArrayCopy();
		$before = $drawsOther();

		$this->assertEquals(\NotAllowedAction::class, $this->executeGesture($ePlotOther, $cBedOther->getIds(), self::SEASON, $data, 'doUpdateBedLineSpacing'), 'un administrateur de la seule première structure ne recommence pas le dessin d\'un jardin de l\'autre');
		$this->assertEquals('Bed::ids.check', $this->executeGesture($ePlot, $cBedOther->getIds(), self::SEASON, $data, 'doUpdateBedLineSpacing'), 'ni celui de planches de l\'autre structure postées sous un de ses jardins');
		$this->assertEquals($before, $drawsOther(), 'les planches de l\'autre structure gardent leur dessin');

	}

	public function testBedPostedWithoutItsPlaceWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à la planche sans place');
		$cBed = $this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		[$id1, $id2] = $cBed->getIds();

		$more = [
			'corner' => [$id1 => '[3.0995, 45.5995]'],
			'angle' => [$id1 => '0', $id2 => '0']
		];

		$this->assertEquals('Draw::corner.placed', $this->executeGesture($ePlot, [$id1, $id2], self::SEASON, $data, more: $more), 'une planche cochée postée sans coin est refusée');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'et rien n\'est écrit, pas même la planche qui avait le sien');

	}

	public function testAngleBeyondHalfATurnWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à l\'angle refusé');
		$cBed = $this->createBeds($ePlot, 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$more = [
			'angle' => [$cBed->first()['id'] => '4']
		];

		$this->assertEquals('Draw::angle.check', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, more: $more), 'un angle au-delà d\'un demi-tour n\'est pas la forme canonique, que le navigateur doit poster');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'et rien n\'est écrit');

	}

	public function testCornerOffTheEarthWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin au coin hors de la Terre');
		$cBed = $this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		foreach([[3.1, 91.0], [181.0, 45.6], [-181.0, 45.6], [1e9, 1e9]] as $corner) {

			$more = [
				'corner' => array_combine($cBed->getIds(), [json_encode($corner), json_encode([3.0995, 45.5995])])
			];

			$this->assertEquals('Draw::corner.bounded', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, more: $more), 'un coin posté à '.json_encode($corner).' n\'a pas de place sur la carte');

		}

		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'et rien n\'est écrit, pas même la planche voisine bien placée');

		$more = [
			'corner' => array_combine($cBed->getIds(), [json_encode([-180.0, -90.0]), json_encode([180.0, 90.0])])
		];

		// Sur la carte, les bornes elles-mêmes passent corner.bounded : le refus qui reste est celui du jardin, dont elles sortent
		$this->assertEquals('Draw::angle.inside', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, more: $more), 'les bornes elles-mêmes sont sur la carte, hors du jardin');

	}

	public function testRedrawingBedsOfTwoLinesMakesOneRow(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin aux deux traits');
		$cBed = $this->createBeds($ePlot, 5);
		$this->draw($ePlot, $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2'])), self::SEASON);
		$this->draw($ePlot, $cBed->find(fn($eBed) => in_array($eBed['name'], ['P3', 'P4'])), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ids = $cBed->find(fn($eBed) => in_array($eBed['name'], ['P2', 'P3', 'P5']))->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data), 'un administrateur retrace une planche de chaque trait et une planche à tracer');
		$this->assertEquals(['P1', 'P4', 'P2', 'P3', 'P5'], $this->getBedsDrawnNames($data->ePlot), 'chaque planche décochée garde sa place, et les trois cochées forment un seul rang');

	}

	public function testRedrawingAWholeLineEmptiesIt(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin au trait vidé');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2'])), self::SEASON);
		$this->draw($ePlot, $cBed->find(fn($eBed) => in_array($eBed['name'], ['P3', 'P4'])), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ids = $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2', 'P3']))->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data), 'un administrateur retrace un trait entier et une planche d\'un autre');
		$this->assertEquals(['P4', 'P1', 'P2', 'P3'], $this->getBedsDrawnNames($data->ePlot), 'chaque planche garde une seule place');
		$this->assertEquals(4, \map\Draw::model()->wherePlot($ePlot)->whereSeason(self::SEASON)->count(), 'en base aussi');

	}

	public function testDeleteFromTheMapAnswersWithWhatThePlotBecomes(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin aux planches supprimées');
		$cBed = $this->createBeds($ePlot, 3);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ids = $cBed->find(fn($eBed) => $eBed['name'] === 'P2')->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data, 'doDeleteCollection', ['origin' => PlanUi::VIEW_MAP]), 'un administrateur supprime une planche depuis la Carte');
		$this->assertEquals(PlanUi::VIEW_MAP, $data->origin, 'la réponse est celle de la Carte');
		$this->assertEquals(['P1', 'P3'], $this->getBedsDrawnNames($data->ePlot), 'la carte perd la planche supprimée');

	}

	public function testGestureLeavesOnlyItsBedsChecked(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à tracer en partie');
		$cBed = $this->createBeds($ePlot, 4);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $cBed->find(fn($eBed) => $eBed['name'] === 'P3')->getIds(), self::SEASON, $data), 'un administrateur trace une seule des quatre planches');
		$this->assertEquals(['P1', 'P2', 'P4'], array_values($data->ePlot['cBedUndrawn']->getColumn('name')), 'les trois autres restent à tracer');
		$this->assertEquals(['P3'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'seule la planche tracée reste cochée, les trois autres ne le sont pas');

	}

	public function testFinishedPlotStaysOpen(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin fini par le geste');
		$cBed = $this->createBeds($ePlot, 2);
		$this->createBeds($this->createPlot($eZone, 'Jardin suivant'), 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data), 'un administrateur trace les dernières planches d\'un jardin');
		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'le troisième niveau reste sur le jardin du geste, même quand un autre attend ses planches');
		$this->assertTrue($data->ePlot['cBedUndrawn']->empty(), 'où plus rien ne reste à tracer');

	}

	public function testEraseFromTheConsoleAnswersWithWhatThePlotBecomes(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à effacer');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2'])), self::SEASON);
		$this->draw($ePlot, $cBed->find(fn($eBed) => in_array($eBed['name'], ['P3', 'P4'])), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ids = $cBed->find(fn($eBed) => in_array($eBed['name'], ['P1', 'P2']))->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data, 'doDeleteBedLineCollection', ['origin' => PlanUi::VIEW_MAP]), 'un administrateur efface un trait depuis la Carte');

		$this->assertEquals(PlanUi::VIEW_MAP, $data->origin, 'la réponse est celle de la Carte');
		$this->assertEquals(['P1', 'P2'], array_values($data->ePlot['cBedUndrawn']->getColumn('name')), 'où les planches du trait effacé restent à tracer');
		$this->assertEquals(['P3', 'P4'], $this->getBedsDrawnNames($data->ePlot), 'et la carte reçoit les planches qui restent au jardin');
		$this->assertEquals(['P1', 'P2'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'les planches effacées restent cochées');

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $cBed->find(fn($eBed) => $eBed['name'] === 'P3')->getIds(), self::SEASON, $data, 'doDeleteBedLineCollection', ['origin' => PlanUi::VIEW_PLAN]), 'le même effacement posté depuis l\'onglet Plan');
		$this->assertEquals(PlanUi::VIEW_PLAN, $data->origin, 'y répond sans rien relire de la Carte');

	}

	public function testCollectionFromTheMapKeepsItsBedsChecked(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à régler');
		$cBed = $this->createBeds($ePlot, 4);
		$this->draw($ePlot, $cBed, self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$ids = $cBed->find(fn($eBed) => in_array($eBed['name'], ['P2', 'P3']))->getIds();

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data, 'doUpdateSizeCollection', ['origin' => PlanUi::VIEW_MAP, 'length' => '15', 'width' => '80', 'alleyWidth' => '50', 'changed' => ['length' => '1', 'width' => '1', 'alleyWidth' => '1']]), 'un administrateur change les dimensions de deux planches depuis la Carte');
		$this->assertEquals(['P2', 'P3'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'qui restent cochées');

		$this->assertEquals(\ViewAction::class, $this->executeGesture($ePlot, $ids, self::SEASON, $data, 'doUpdateSeasonCollection', ['origin' => PlanUi::VIEW_MAP, 'seasonFirst' => (string)(self::SEASON - 1), 'seasonLast' => '']), 'puis leurs saisons');
		$this->assertEquals(['P2', 'P3'], array_values($data->ePlot['cBedSelected']->getColumn('name')), 'qui restent cochées aussi');

	}

	public function testGestureInASeasonWithoutThePlotWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin arrêté', seasonLast: self::SEASON - 1);
		$cBed = $this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Draw::season.exploited', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data), 'un geste posté dans une saison où le jardin n\'est plus exploité est refusé par la validation de la saison');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'et n\'écrit aucun trait');

	}

	public function testGestureInASeasonWithoutTheZoneWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure, seasonLast: self::SEASON - 1);
		$ePlot = $this->createPlot($eZone, 'Jardin d\'une parcelle arrêtée');
		$cBed = $this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Draw::season.exploited', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data), 'la saison où la parcelle n\'est plus exploitée est refusée, même si le jardin ne porte pas de borne');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'et aucun trait n\'est écrit');

	}

	public function testEraseInASeasonWithoutThePlotCopiesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin arrêté et tracé', seasonLast: self::SEASON - 1);
		$cBed = $this->createBeds($ePlot, 2);
		$this->draw($ePlot, $cBed, self::SEASON - 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Draw::season.exploited', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, 'doDeleteBedLineCollection', ['origin' => PlanUi::VIEW_MAP]), 'effacer dans une saison où le jardin n\'est plus exploité est refusé comme le tracé');
		$this->assertEquals([self::SEASON - 1], array_values(array_unique(Draw::model()->wherePlot($ePlot)->getColumn('season'))), 'et le tracé hérité n\'est pas recopié dans cette saison');

	}

	public function testEraseOnAPlotWithoutShapeIsRefused(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans contour à effacer', drawn: FALSE);
		$cBed = $this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('map\\Bed::canNotDraw', $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data, 'doDeleteBedLineCollection'), 'un jardin qui ne se trace pas ne s\'efface pas non plus');

	}

	public function testReaderCannotPostTheGesture(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin du lecteur');
		$cBed = $this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: FALSE);

		$this->assertEquals(\NotAllowedAction::class, $this->executeGesture($ePlot, $cBed->getIds(), self::SEASON, $data), 'un lecteur ne trace pas');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'et aucun trait n\'est écrit');

	}

	/**
	 * Joue l'envoi du panneau « Ajouter des planches » (`map/bed:doCreate`), ouvert depuis la vue Carte ou, sans `$origin`, depuis le
	 * Plan, avec la place de chaque planche quand `$cDraw` est posé, sous sa position dans le rang, et rend la classe de l'action levée
	 * ou le nom du refus.
	 */
	private function executeCreate(Plot $ePlot, array|string $names, int $season, ?\stdClass &$data, \Collection $cDraw = new \Collection(), ?string $origin = PlanUi::VIEW_MAP): string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		$input = [
			'plot' => (string)$ePlot['id'],
			'season' => (string)$season,
			'origin' => (string)$origin,
			'name' => $names,
			'length' => '20',
			'width' => '110',
			'alleyWidth' => '60'
		];

		// Le champ du panneau, tel que MapDrawingLine.getBody() l'écrit
		if($cDraw->notEmpty()) {
			$input['draw'] = json_encode([
				'corner' => $cDraw->makeArray(fn(Draw $eDraw) => json_encode($eDraw['corner'])),
				'angle' => $cDraw->makeArray(fn(Draw $eDraw) => $eDraw['angle'])
			]);
		}

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'map/bed:doCreate'), $data);
			return 'aucune action';
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : $fail->format()[0][0];
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Le rang que le navigateur pose pour `$number` planches à créer, aux dimensions que executeCreate() poste, une place par
	 * position.
	 */
	private function getRow(int $number): \Collection {

		$cBed = new \Collection();

		for($position = 0; $position < $number; $position++) {
			$cBed[$position] = new Bed([
				'length' => 20,
				'width' => 110
			]);
		}

		return $this->getPlaces($cBed, self::ROW_START, 90);

	}

	public function testCreateWithItsRowDrawsTheBeds(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin à garnir');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreate($ePlot, ['P1', 'P2', 'P3'], self::SEASON, $data, $this->getRow(3)), 'un administrateur crée trois planches en dessinant leur rang');

		$cBed = Bed::model()->select(['id', 'name'])->wherePlot($ePlot)->wherePlotFill(FALSE)->sort('name')->getCollection(index: 'id');

		$this->assertEquals(['P1', 'P2', 'P3'], array_values($cBed->getColumn('name')), 'les planches sont créées avec les noms postés');
		$this->assertDrawnAlongTheRow($ePlot, $cBed, self::SEASON, 'les planches créées');
		$this->assertEquals($ePlot['id'], $data->ePlot['id'], 'la réponse reste sur le jardin du geste');
		$this->assertTrue($data->ePlot['cBedUndrawn']->empty(), 'où plus rien ne reste à tracer');

	}

	public function testCreateFromThePlanDrawsTheBedsToo(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin garni depuis le Plan');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreate($ePlot, ['P1', 'P2'], self::SEASON, $data, $this->getRow(2), origin: NULL), 'le panneau ouvert depuis le Plan crée les planches avec leur rang');

		$cBed = Bed::model()->select(['id', 'name'])->wherePlot($ePlot)->wherePlotFill(FALSE)->sort('name')->getCollection(index: 'id');

		$this->assertDrawnAlongTheRow($ePlot, $cBed, self::SEASON, 'les deux planches créées');
		$this->assertFalse(isset($data->eZone), 'la réponse du Plan recharge la parcelle sans rendre de troisième niveau');

	}

	/**
	 * Chaque planche de `$cBed`, rangée par nom, a sa ligne dans la saison, à la place postée sous sa position (getRow()), et le
	 * jardin n'en a pas d'autre.
	 */
	private function assertDrawnAlongTheRow(Plot $ePlot, \Collection $cBed, int $season, string $beds): void {

		$cDraw = Draw::model()
			->select(['bed', 'season', 'corner', 'angle'])
			->wherePlot($ePlot)
			->sort('bed')
			->getCollection();

		$ids = $cBed->getIds();
		sort($ids);

		$this->assertEquals($ids, $cDraw->makeArray(fn(Draw $eDraw) => $eDraw['bed']['id']), 'une ligne pour chacune des '.$beds);
		$this->assertEquals([$season], array_values(array_unique($cDraw->getColumn('season'))), 'dans la saison postée');

		$positions = array_flip($cBed->getIds());
		$cDrawRow = $this->getRow($cBed->count());

		foreach($cDraw as $eDraw) {
			$eDrawRow = $cDrawRow[$positions[$eDraw['bed']['id']]];
			$this->assertTrue(MapLib::getDistance($eDraw['corner'], $eDrawRow['corner']) < 0.001 and abs($eDraw['angle'] - $eDrawRow['angle']) < 0.0001, 'la planche #'.$eDraw['bed']['id'].' prend la place postée sous sa position dans le rang');
		}

	}

	/**
	 * Ouvre le panneau « Ajouter des planches » (`map/bed:create`) et rend la classe de l'action levée.
	 */
	private function executeCreatePanel(Plot $ePlot, int $season, ?\stdClass &$data): string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		$input = ['plot' => (string)$ePlot['id'], 'season' => (string)$season];

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$input, [], $input];

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'map/bed:create'), $data);
			return 'aucune action';
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	public function testCreatePanelPlacesTheRowAfterTheLastLine(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin au rang suivant');
		$this->draw($ePlot, $this->createBeds($ePlot, 3), self::SEASON);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreatePanel($ePlot, self::SEASON, $data), 'un administrateur ouvre le panneau');
		$this->assertTrue($data->ePlot->canBedLine(), 'sur un jardin qui a sa forme, où le rang se dessine');
		$eDrawLast = \map\Draw::model()
			->select(['corner', 'angle', 'bed' => ['length', 'width']])
			->wherePlot($ePlot)
			->sort(['id' => SORT_DESC])
			->get();
		$this->assertEquals(\map\DrawLib::getLine($eDrawLast, $eDrawLast['bed']), $data->ePlot['lineNext'], 'à la suite de la dernière planche posée, dans le sens où le rang avance');
		$this->assertEquals(['prefix' => 'P', 'start' => 4, 'suffix' => '', 'pad' => 0], $data->ePlot['numbering'], 'et les planches neuves suivent la numérotation des trois premières');

	}

	public function testCreatePanelFollowsARowLaidWestward(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin au rang vers l\'ouest');
		$this->draw($ePlot, $this->createBeds($ePlot, 3), self::SEASON, [3.1005, 45.5995], 270);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreatePanel($ePlot, self::SEASON, $data), 'un administrateur ouvre le panneau');

		$eDrawLast = \map\Draw::model()
			->select(['corner', 'angle', 'bed' => ['length', 'width']])
			->wherePlot($ePlot)
			->sort(['id' => SORT_DESC])
			->get();
		$line = \map\DrawLib::getLine($eDrawLast, $eDrawLast['bed']);

		$this->assertEquals([$line[1], $line[0]], array_slice($data->ePlot['lineNext'], 0, 2), 'le rang suivant part du bord ouest de la dernière planche, le cap canonique pointant vers l\'est');

	}

	public function testCreatePanelOnAPlotWithoutShapeHasNoRow(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans contour du panneau', drawn: FALSE);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreatePanel($ePlot, self::SEASON, $data), 'le panneau s\'ouvre sur un jardin sans contour');
		$this->assertFalse($data->ePlot->canBedLine(), 'sans rang à dessiner');

	}

	public function testCreatePanelOutOfSeasonIsNotOffered(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin arrêté du panneau', seasonLast: self::SEASON - 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\NotExpectedAction::class, $this->executeCreatePanel($ePlot, self::SEASON, $data), 'un jardin hors de la saison regardée n\'a pas de panneau');

	}

	public function testCreateWithoutRowLeavesTheBedsToDraw(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin garni sans rang');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreate($ePlot, ['P1', 'P2'], self::SEASON, $data), 'les planches se créent aussi sans rang');
		$this->assertEquals(2, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'les deux planches sont créées');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'sans trait');
		$this->assertEquals(2, $data->ePlot['bedsUndrawn'], 'elles restent à tracer');

	}

	public function testCreateOnAPlotWithoutShapeListsThemUndrawable(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans contour à garnir', drawn: FALSE);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->executeCreate($ePlot, ['P1', 'P2'], self::SEASON, $data), 'un jardin sans contour reçoit ses planches');
		$this->assertEquals(2, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'qui sont créées');
		$this->assertFalse($data->ePlot['drawable'], 'et la réponse les liste sans case à cocher, faute de forme où les dessiner');

	}

	public function testCreateWithARowOnAPlotWithoutShapeWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin sans contour au rang forgé', drawn: FALSE);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Bed::canNotDraw', $this->executeCreate($ePlot, ['P1'], self::SEASON, $data, $this->getRow(1)), 'un rang posté pour un jardin sans contour est refusé');
		$this->assertEquals(0, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et aucune planche n\'est créée');

	}

	public function testCreateWithAnInvalidRowWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin au rang trop court');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Draw::corner.placed', $this->executeCreate($ePlot, ['P1', 'P2'], self::SEASON, $data, $this->getRow(1)), 'un rang qui ne place qu\'une des deux planches est refusé');
		$this->assertEquals(0, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et les planches ne sont pas créées sans lui');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'ni aucun trait');

	}

	public function testCreateWithARowInASeasonWithoutThePlotWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin arrêté à garnir', seasonLast: self::SEASON - 1);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Draw::season.exploited', $this->executeCreate($ePlot, ['P1'], self::SEASON, $data, $this->getRow(1)), 'un rang posté dans une saison où le jardin n\'est plus exploité est refusé');
		$this->assertEquals(0, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et la planche n\'est pas créée');

	}

	public function testCreateWithATakenNameWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin déjà garni');
		$this->createBeds($ePlot, 2);

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Bed::name.duplicate', $this->executeCreate($ePlot, ['P2', 'P3'], self::SEASON, $data, $this->getRow(2)), 'un nom que porte déjà une planche du jardin est refusé');
		$this->assertEquals(2, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et aucune planche n\'est ajoutée');
		$this->assertEquals(0, Draw::model()->wherePlot($ePlot)->count(), 'ni aucun trait');

	}

	public function testCreateAboveTheCapWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin trop garni');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$names = array_map(fn($position) => 'P'.$position, range(1, MapSetting::$onboardingBedsMax + 1));

		$this->assertEquals('Bed::number.check', $this->executeCreate($ePlot, $names, self::SEASON, $data), 'un envoi qui dépasse le plafond du champ est refusé par le serveur');
		$this->assertEquals(0, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et aucune planche n\'est créée');

	}

	public function testCreateWithNamesNotListedWritesNothing(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin au nom forgé');

		$this->connectAs([$this->eStructure['id']], admin: TRUE);

		$this->assertEquals('Bed::names.check', $this->executeCreate($ePlot, 'P1', self::SEASON, $data), 'un nom posté hors d\'une liste est refusé');
		$this->assertEquals(0, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et aucune planche n\'est créée');

	}

	public function testReaderCannotCreate(): void {

		$eZone = $this->createZone($this->eStructure);
		$ePlot = $this->createPlot($eZone, 'Jardin du lecteur à garnir');

		$this->connectAs([$this->eStructure['id']], admin: FALSE);

		$this->assertEquals(\NotAllowedAction::class, $this->executeCreate($ePlot, ['P1'], self::SEASON, $data, $this->getRow(1)), 'un lecteur ne crée pas de planche');
		$this->assertEquals(0, Bed::model()->wherePlot($ePlot)->wherePlotFill(FALSE)->count(), 'et rien n\'est écrit');

	}

	/**
	 * Un panneau rechargé en page entière garde `origin=map` dans son adresse sans avoir de carte où rendre la réponse de
	 * l'enregistrement : il ne repostera pas d'origine, et l'enregistrement répondra comme au panneau de l'onglet Plan. Le
	 * panneau se lit ici sur `loadOrigin()` seul, que ses quatre pages appellent : en ajax comme en page entière, son rendu
	 * demande une session que le lanceur de tests n'ouvre pas.
	 */
	public function testPanelOpenedFullPageForgetsTheMap(): void {

		$requestedWith = new \ReflectionProperty(\Route::class, 'requestedWith');
		$requestedWithInitial = $requestedWith->getValue();

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];
		[$_GET, $_REQUEST] = [['origin' => PlanUi::VIEW_MAP], ['origin' => PlanUi::VIEW_MAP]];

		try {

			$data = new \stdClass();

			$requestedWith->setValue(NULL, 'http');
			MapLib::loadOrigin($data);
			$this->assertEquals(NULL, $data->origin, 'une page entière n\'a pas de carte où répondre');

			$requestedWith->setValue(NULL, 'ajax');
			MapLib::loadOrigin($data);
			$this->assertEquals(PlanUi::VIEW_MAP, $data->origin, 'un panneau par-dessus la Carte y répond');

		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestedWith->setValue(NULL, $requestedWithInitial);
		}

	}

}
?>
