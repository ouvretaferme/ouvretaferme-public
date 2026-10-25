<?php
namespace map;

/**
 * Le format que `SceneLib::getByStructure()` rend à une scène 3D : ce qui en sort, ce qui n'en sort
 * pas, et la date des deux bouts d'une série.
 *
 * Le décor est celui de `series\SeriesFixtureAiTest`, monté par les Lib dans la transaction de la
 * méthode sur la structure `FixtureAiLib::F1`. Les cas qui comptent des lignes de la structure
 * entière (tracés, tâches, récoltes, équipe) déclarent d'abord qu'elle n'a ni parcellaire, ni série
 * à elle, par `requirePlanEmpty()`, et celui de l'équipe retire les présences du décor avant de poser
 * les siennes.
 *
 * Les semaines de planche d'une série ne se posent pas à la main : ce sont les interventions de
 * plantation du décor qui les font calculer par `SeriesLib`, comme dans le produit.
 *
 * Lancement : dev/test package=map file=SceneAi
 */
class SceneAiTest extends \series\SeriesFixtureAiTest {

	/**
	 * Le plan se lit en entier dans la base commune : la structure du décor se lit sans que sa base
	 * `structure_<id>` soit ouverte ni migrée, ce que ferait l'`init()` hérité.
	 */
	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(static::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.static::STRUCTURE.' introuvable : sème le décor par structure/configure/fixtureAi write=1');
		}

	}

	/** La saison qu'un cas impose au décor quand le calendrier de l'année courante ne lui suffit pas. */
	private ?int $season = NULL;

	public function setUp(): void {

		parent::setUp();

		$this->season = NULL;

		$this->setClock($this->getSeason().'-06-15 10:00:00');

	}

	protected function getSeason(): int {

		return $this->season ?? parent::getSeason();

	}

	/** Le dimanche qui clôt une saison : la veille du lundi de la semaine 1 de la suivante. */
	private function getSeasonLastDay(int $season): string {

		return date('Y-m-d', strtotime(week_date_starts(($season + 1).'-W01').' - 1 DAY'));

	}

	private function getScene(): array {

		return SceneLib::getByStructure($this->eStructure, $this->getSeason());

	}

	/** La ligne d'un bloc du format par son identifiant, ou un tableau vide quand elle n'en sort pas. */
	private function findById(array $rows, int $id): array {

		foreach($rows as $row) {
			if($row['id'] === $id) {
				return $row;
			}
		}

		return [];

	}

	/** La planche tracée par son identifiant de planche, ou un tableau vide quand elle ne sort pas. */
	private function findByBed(array $bedsDrawn, int $bedId): array {

		foreach($bedsDrawn as $bedDrawn) {
			if($bedDrawn['bed'] === $bedId) {
				return $bedDrawn;
			}
		}

		return [];

	}

	private function requirePlanEmpty(): bool {

		return $this->assertDecorPresent(
			(
				Zone::model()->whereStructure($this->eStructure)->count() === 0 and
				Draw::model()->whereStructure($this->eStructure)->count() === 0 and
				\series\Series::model()->whereStructure($this->eStructure)->count() === 0
			),
			'la structure '.static::STRUCTURE.' sans parcelle, tracé ni série à elle, pour compter ceux du cas'
		);

	}

	/** Une série en planches du décor, posée sur une planche neuve du jardin du décor. */
	private function createSeriesPlaced(string $name): \series\Series {

		$eSeries = $this->createSeriesBed($name);

		$this->createPlace($eSeries, $this->createBed(), 10);

		return $eSeries;

	}

	/** Une plantation prévue une semaine donnée sur une culture neuve de la série : `SeriesLib` en tire les semaines de planche. */
	private function plant(\series\Series $eSeries, string ...$weeks): \series\Cultivation {

		$eCultivation = $this->createCultivation($eSeries, $this->createPlant());

		foreach($weeks as $week) {
			$this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, $week);
		}

		return $eCultivation;

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

	/** Les planches posées en rang dans la saison, une ligne par planche, indexées par planche. */
	private function createDraws(Plot $ePlot, \Collection $cBed, int $season): \Collection {

		$this->stopOnClosedTransaction('avant createDraws()');

		$eDraw = new Draw([
			'plot' => $ePlot,
			'season' => $season,
		]);

		$cDraw = $this->getPlaces($cBed, [3.1, 45.6]);

		// Sans build(), qui vérifierait la saison postée : elle est celle que le cas veut, passée ou future comprise
		DrawLib::createByBeds($eDraw, $cDraw);

		return $cDraw;

	}

	public function testFormatKeysAndColumns(): void {

		if($this->requirePlanEmpty() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE or $this->requireMember() === FALSE) {
			return;
		}

		$eSeries = $this->createSeriesPlaced('Série du format');
		$eCultivation = $this->plant($eSeries, $this->getSeason().'-W20');

		$eTaskHarvest = $this->createTaskHarvest($eCultivation, $this->getSeason().'-W24', $this->getSeason().'-06-10');
		$this->createHarvest($eTaskHarvest, 12.5, $this->getSeason().'-06-10');

		$scene = $this->getScene();

		$this->assertEquals(
			['version', 'structure', 'name', 'season', 'center', 'zones', 'plots', 'beds', 'bedsDrawn', 'layers', 'decor', 'buildings', 'decorAdopted', 'decorReach', 'terrain', 'places', 'series', 'actions', 'columns', 'tasks', 'harvests', 'crew', 'team'],
			array_keys($scene)
		);

		$this->assertEquals(MapSetting::SCENE_VERSION, $scene['version']);
		$this->assertEquals(static::STRUCTURE, $scene['structure']);
		$this->assertEquals($this->eStructure['name'], $scene['name']);
		$this->assertEquals($this->getSeason(), $scene['season']);

		$this->assertEquals(['tasks', 'harvests', 'crew'], array_keys($scene['columns']));
		$this->assertEquals(['date', 'members', 'team'], $scene['columns']['crew']);

		$this->assertEquals(2, count($scene['tasks']), 'la plantation et la récolte de la série placée');
		$this->assertEquals(1, count($scene['harvests']), 'la récolte datée de la série placée');

		foreach($scene['tasks'] as $task) {
			$this->assertEquals(count($scene['columns']['tasks']), count($task), 'un tuple de tâche a la largeur de columns.tasks');
		}

		foreach($scene['harvests'] as $harvest) {
			$this->assertEquals(count($scene['columns']['harvests']), count($harvest), 'un tuple de récolte a la largeur de columns.harvests');
		}

		$task = array_combine($scene['columns']['tasks'], $scene['tasks'][1]);

		$this->assertEquals($eTaskHarvest['id'], $task['id']);
		$this->assertEquals($eSeries['id'], $task['series']);
		$this->assertEquals($eCultivation['id'], $task['cultivation']);
		$this->assertEquals($this->getAction(ACTION_RECOLTE)['id'], $task['action']);
		$this->assertEquals($this->getSeason().'-W24', $task['doneWeek']);
		$this->assertEquals($this->getSeason().'-06-10', $task['doneDate']);
		$this->assertEquals(1, $task['done']);
		$this->assertEquals(12.5, $task['harvest']);
		$this->assertEquals(\series\Task::KG, $task['harvestUnit']);

		$taskPlanned = array_combine($scene['columns']['tasks'], $scene['tasks'][0]);

		$this->assertEquals($this->getSeason().'-W20', $taskPlanned['plannedWeek']);
		$this->assertEquals(0, $taskPlanned['done']);

		$this->assertEquals(
			['date' => $this->getSeason().'-06-10', 'series' => $eSeries['id'], 'cultivation' => $eCultivation['id'], 'quantity' => 12.5, 'unit' => \series\Harvest::KG],
			array_combine($scene['columns']['harvests'], $scene['harvests'][0])
		);

		// Le fqn de l'action d'une tâche se lit par actions[], depuis la colonne action du tuple
		$action = $this->findById($scene['actions'], $task['action']);

		$this->assertEquals(['id', 'name', 'fqn', 'color'], array_keys($action));
		$this->assertEquals(ACTION_RECOLTE, $action['fqn']);
		$this->assertEquals(2, count($scene['actions']), 'une ligne par action citée, pas une par tâche');

	}

	public function testGroundAndPlace(): void {

		$eSeries = $this->createSeriesBed('Série du parcellaire');
		$eBed = $this->createBed();
		$ePlace = $this->createPlace($eSeries, $eBed, 10);

		$scene = $this->getScene();

		$zone = $this->findById($scene['zones'], $this->getZone()['id']);
		$plot = $this->findById($scene['plots'], $this->getPlot()['id']);
		$bed = $this->findById($scene['beds'], $eBed['id']);

		$this->assertEquals(['id', 'name', 'area', 'coordinates'], array_keys($zone));
		$this->assertEquals(['id', 'name', 'zone', 'zoneFill', 'mode', 'decorGreenhouse', 'length', 'width', 'area', 'coordinates'], array_keys($plot));
		$this->assertEquals(['id', 'name', 'plot', 'zoneFill', 'plotFill', 'length', 'width', 'alleyWidth'], array_keys($bed));

		$this->assertEquals($this->getZone()['id'], $plot['zone']);
		$this->assertEquals(Plot::OPEN_FIELD, $plot['mode'], 'le mode du jardin sort, la scène comptable le lit');
		$this->assertEquals(NULL, $plot['decorGreenhouse'], 'le modèle d\'abri sort tel qu\'en base, vide compris : la scène replie un abri sans modèle sur les pieds droits');
		$this->assertEquals($this->getPlot()['id'], $bed['plot']);
		$this->assertEquals(20, $bed['length']);
		$this->assertEquals(80, $bed['width']);

		$this->assertEquals(
			['id' => $ePlace['id'], 'bed' => $eBed['id'], 'series' => $eSeries['id'], 'length' => 10],
			$this->findById($scene['places'], $ePlace['id'])
		);

		$series = $this->findById($scene['series'], $eSeries['id']);

		$this->assertEquals(['id', 'name', 'season', 'area', 'cycle', 'status', 'start', 'stop', 'cultivations'], array_keys($series));
		$this->assertEquals($this->getSeason(), $series['season']);
		$this->assertEquals(\series\Series::ANNUAL, $series['cycle']);
		$this->assertEquals(\series\Series::OPEN, $series['status']);
		$this->assertEquals([], $series['cultivations']);

	}

	/** Une planche retirée du plan ne sort pas, ni la série qui n'est posée que sur elle. */
	public function testBedOutOfSeason(): void {

		$eSeries = $this->createSeriesBed('Série sur une planche retirée');
		$eBed = $this->createBed();
		$this->createPlace($eSeries, $eBed, 10);

		$eBed['seasonLast'] = $this->getSeason() - 1;

		// Sans build(), qui vérifierait la dernière saison postée contre la première et contre celle de
		// ce qui porte la planche : rien dans le décor n'a de borne de saison, toute valeur convient.
		BedLib::update($eBed, ['seasonLast']);

		$scene = $this->getScene();

		$this->assertEquals([], $this->findById($scene['beds'], $eBed['id']));
		$this->assertEquals([], $this->findById($scene['series'], $eSeries['id']));

	}

	public function testPlantFqn(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eSeries = $this->createSeriesPlaced('Série des espèces');

		$ePlantNamed = new \plant\Plant([
			'name' => 'Espèce à modèle '.uniqid(),
			'fqn' => 'carotte',
			'color' => '#ff8800',
			'structure' => $this->eStructure,
			'cycle' => \plant\Plant::ANNUAL,
		]);

		// Sans build(), qui ne laisse pas un formulaire poser le fqn d'une espèce : le cas veut une
		// espèce de la structure qui porte celui d'un modèle, comme la copie d'une espèce du référentiel.
		\plant\PlantLib::create($ePlantNamed);

		$eCultivationNamed = $this->createCultivation($eSeries, $ePlantNamed);
		$eCultivationUnnamed = $this->createCultivation($eSeries, $this->createPlant());

		// Sans build(), qui demande le formulaire entier de la culture : le cas ne veut que le poids d'une botte
		$eCultivationUnnamed['bunchWeight'] = 0.4;
		\series\CultivationLib::update($eCultivationUnnamed, ['bunchWeight']);

		$eVariety = $this->createVariety($ePlantNamed);
		$this->createSlice($eCultivationNamed, $eVariety);

		$cultivations = $this->findById($this->getScene()['series'], $eSeries['id'])['cultivations'];

		$this->assertEquals(2, count($cultivations));

		$named = $this->findById($cultivations, $eCultivationNamed['id']);
		$unnamed = $this->findById($cultivations, $eCultivationUnnamed['id']);

		$this->assertEquals(
			['id', 'fqn', 'plant', 'color', 'varieties', 'startAction', 'harvested', 'unit', 'weights', 'harvestWeeks', 'harvestWeeksExpected', 'harvestDone'],
			array_keys($named)
		);

		$this->assertEquals('carotte', $named['fqn']);
		$this->assertEquals($ePlantNamed['name'], $named['plant']);
		$this->assertEquals('#ff8800', $named['color']);
		$this->assertEquals([$eVariety['name']], $named['varieties']);
		$this->assertEquals(\series\Cultivation::KG, $named['unit']);
		$this->assertEquals(FALSE, $named['harvestDone']);
		$this->assertEquals([], $named['weights'], 'une culture sans poids saisi ne convertit rien');

		$this->assertEquals(NULL, $unnamed['fqn'], 'une espèce sans fqn sort avec son nom seul');
		$this->assertEquals([], $unnamed['varieties']);
		$this->assertEquals([\series\Cultivation::BUNCH => 0.4], $unnamed['weights'], 'le poids d\'une botte sert à convertir les bottes récoltées en kg');

	}

	public function testDrawOfLatestSeason(): void {

		$ePlot = $this->getPlot();
		$eBed = $this->createBed();
		$cBed = new \Collection([$eBed['id'] => $eBed]);

		$this->createDraws($ePlot, $cBed, $this->getSeason() - 2);
		$eDrawKept = $this->createDraws($ePlot, $cBed, $this->getSeason() - 1)[$eBed['id']];
		$this->createDraws($ePlot, $cBed, $this->getSeason() + 1);

		// Relue, parce que la base arrondit le coin et l'angle qu'on lui donne
		$eDrawKept = Draw::model()
			->select(['id', 'corner', 'angle'])
			->whereId($eDrawKept['id'])
			->get();

		$scene = $this->getScene();

		$bedsDrawn = array_values(array_filter($scene['bedsDrawn'], fn($bedDrawn) => $bedDrawn['plot'] === $ePlot['id']));

		$this->assertEquals([[
			'bed' => $eBed['id'],
			'plot' => $ePlot['id'],
			'season' => $this->getSeason() - 1,
			'corner' => $eDrawKept['corner'],
			'angle' => $eDrawKept['angle']
		]], $bedsDrawn);

	}

	/** Un tracé survit au jardin qu'il dessine : il ne sort pas quand son jardin n'est plus de la saison. */
	public function testDrawOfPlotOutOfSeason(): void {

		$ePlot = $this->createPlot();
		$eDraw = $this->createDraws($ePlot, new \Collection([$this->createBed($ePlot)]), $this->getSeason() - 1)->first();

		$this->assertDiffer([], $this->findByBed($this->getScene()['bedsDrawn'], $eDraw['bed']['id']));

		$ePlot['seasonLast'] = $this->getSeason() - 1;

		// Sans build(), qui vérifierait la dernière saison postée contre la première et contre celle de
		// la parcelle : rien dans le décor n'a de borne de saison, toute valeur convient.
		PlotLib::update($ePlot, ['seasonLast']);

		$scene = $this->getScene();

		$this->assertEquals([], $this->findById($scene['plots'], $ePlot['id']));
		$this->assertEquals([], $this->findByBed($scene['bedsDrawn'], $eDraw['bed']['id']));

	}

	/** Un décor posé, et une clôture qui ne sort qu'autour d'un espace rendu avec son contour. */
	public function testDecor(): void {

		$this->stopOnClosedTransaction('avant le décor');

		$eZoneOutlined = new Zone([
			'name' => 'Parcelle tracée '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => [[3.1, 45.6], [3.101, 45.6], [3.101, 45.601], [3.1, 45.6]],
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ce triangle
		// fermé l'est, et le cas ne lit que la présence d'un contour
		ZoneLib::create($eZoneOutlined);

		$eZoneBare = $this->createZone();

		$eZonePast = $this->createZone();
		$eZonePast['seasonLast'] = $this->getSeason() - 1;
		$eZonePast['coordinates'] = $eZoneOutlined['coordinates'];

		// Sans build(), qui vérifierait la saison et le polygone postés : le cas veut une parcelle
		// tracée qui n'est plus de la saison
		ZoneLib::update($eZonePast, ['seasonLast', 'coordinates']);

		$decor = [];

		foreach([
			'posed' => [],
			'outlined' => ['zone' => $eZoneOutlined, 'model' => Decor::SHEEP],
			'bare' => ['zone' => $eZoneBare, 'model' => Decor::SHEEP],
			'past' => ['zone' => $eZonePast, 'hedge' => TRUE],
		] as $name => $values) {

			$eDecor = new Decor($values + [
				'structure' => $this->eStructure,
				'layer' => new DecorLayer(),
				'kit' => 'cabane',
				'coordinates' => [3.1005, 45.6005],
				'angle' => 0.5,
				'size' => 1.0,
				'width' => NULL,
				'model' => NULL,
				'trace' => NULL,
				'diameter' => NULL,
				'laying' => NULL,
				'zone' => new Zone(),
				'plot' => new Plot(),
				'building' => new DecorBuilding(),
				'hedge' => FALSE,
				'gate' => NULL,
			]);

			// Sans build(), qui vérifierait que l'espace clôturé a un contour : c'est justement la
			// clôture d'un espace qui l'a perdu, ou qui a quitté la saison, que le cas éprouve
			DecorLib::create($eDecor);

			$decor[$name] = $eDecor['id'];

		}

		$scene = $this->getScene();

		$this->assertEquals(
			['id' => $decor['posed'], 'layer' => NULL, 'kit' => 'cabane', 'building' => NULL, 'coordinates' => [3.1005, 45.6005], 'angle' => 0.5, 'size' => 1.0, 'seed' => NULL, 'width' => NULL, 'model' => NULL, 'trace' => NULL, 'diameter' => NULL, 'laying' => NULL, 'zone' => NULL, 'plot' => NULL, 'hedge' => FALSE, 'gate' => NULL],
			$this->findById($scene['decor'], $decor['posed'])
		);

		$this->assertEquals($eZoneOutlined['id'], $this->findById($scene['decor'], $decor['outlined'])['zone'] ?? NULL);
		$this->assertEquals([], $this->findById($scene['decor'], $decor['bare']), 'une parcelle sans contour ne se clôture pas');
		$this->assertEquals([], $this->findById($scene['decor'], $decor['past']), 'une parcelle hors saison ne sort pas, sa clôture non plus');

	}

	public function testWithoutDraw(): void {

		if($this->requirePlanEmpty() === FALSE) {
			return;
		}

		$eSeries = $this->createSeriesPlaced('Série sans tracé');

		$scene = $this->getScene();

		$this->assertEquals([], $scene['bedsDrawn']);
		$this->assertEquals(NULL, $scene['center'], 'aucune parcelle tracée, aucune origine');

		$this->assertEquals(1, count($scene['zones']));
		$this->assertEquals(2, count($scene['plots']), 'le jardin du décor et celui qui remplit la parcelle');
		$this->assertEquals(3, count($scene['beds']), 'la planche du décor et celles qui remplissent les deux jardins');
		$this->assertEquals(1, count($scene['places']));
		$this->assertEquals([$eSeries['id']], array_column($scene['series'], 'id'));

	}

	public function testCenter(): void {

		if($this->requirePlanEmpty() === FALSE) {
			return;
		}

		$this->stopOnClosedTransaction('avant les parcelles tracées');

		foreach([
			[[3.0, 45.0], [3.2, 45.0], [3.2, 45.2], [3.0, 45.0]],
			[[4.0, 46.0], [4.4, 46.0], [4.4, 46.4], [4.0, 46.0]],
		] as $coordinates) {

			$eZone = new Zone([
				'name' => 'Parcelle tracée '.uniqid(),
				'structure' => $this->eStructure,
				'area' => 10000,
				'coordinates' => $coordinates,
				'seasonFirst' => NULL,
				'seasonLast' => NULL,
			]);

			// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ces deux
			// triangles fermés le sont, et le cas ne lit que la moyenne de leurs sommets.
			ZoneLib::create($eZone);

		}

		// Une parcelle sans tracé ne pèse pas dans la moyenne
		$this->createZone();

		$this->assertEquals([3.65, 45.575], $this->getScene()['center']);

	}

	public function testSeriesNotPlaced(): void {

		$eSeriesPlaced = $this->createSeriesPlaced('Série placée');
		$eSeriesNotPlaced = $this->createSeriesBed('Série sans planche');

		$scene = $this->getScene();

		$this->assertDiffer([], $this->findById($scene['series'], $eSeriesPlaced['id']));
		$this->assertEquals([], $this->findById($scene['series'], $eSeriesNotPlaced['id']));

	}

	public function testSeriesOfAnotherStructure(): void {

		if($this->requireStructureForeign() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$this->stopOnClosedTransaction('avant les témoins d\'une autre structure');

		$eBed = $this->createBed();

		$eSeriesForeign = new \series\Series([
			'name' => 'Série témoin',
			'structure' => $this->getStructureForeign(),
			'season' => $this->getSeason(),
			'use' => \series\Series::BED,
			'plants' => 1,
			'cycle' => \series\Series::ANNUAL,
			'createdBy' => $this->getMember(),
		]);

		// Par le modèle, comme les témoins du décor hérité : le témoin vit dans une structure réelle de la
		// base, où il ne doit poser que sa ligne, sans rien de ce que SeriesLib::create() y ajoute.
		\series\Series::model()->insert($eSeriesForeign);

		// Un emplacement de la structure qui cite la série d'une autre : ni lui ni la série qu'il cite ne sortent, les
		// emplacements se lisant par les séries de la structure. Par le modèle : PlaceLib::addBeds() refuse une planche qui n'est
		// pas de la structure de la série, c'est l'état que le produit ne fabrique pas.
		$ePlaceCrossed = new \series\Place([
			'structure' => $this->eStructure,
			'season' => $this->getSeason(),
			'series' => $eSeriesForeign,
			'task' => new \series\Task(),
			'zone' => $eBed['zone'],
			'plot' => $eBed['plot'],
			'bed' => $eBed,
			'length' => 5,
			'width' => 80,
			'createdBy' => $this->getMember(),
		]);

		\series\Place::model()->insert($ePlaceCrossed);

		// Un emplacement d'une autre structure sur une planche de celle-ci : il ne sort pas. Par le
		// modèle : PlaceLib::addBeds() donne à l'emplacement la structure de sa série, jamais une autre.
		$ePlaceForeign = new \series\Place([
			'structure' => $this->getStructureForeign(),
			'season' => $this->getSeason(),
			'series' => $this->createSeriesBed('Série citée par un emplacement témoin'),
			'task' => new \series\Task(),
			'zone' => $eBed['zone'],
			'plot' => $eBed['plot'],
			'bed' => $eBed,
			'length' => 5,
			'width' => 80,
			'createdBy' => $this->getMember(),
		]);

		\series\Place::model()->insert($ePlaceForeign);

		$scene = $this->getScene();

		$this->assertEquals([], $this->findById($scene['places'], $ePlaceCrossed['id']), 'l\'emplacement qui cite la série d\'une autre structure ne sort pas');
		$this->assertEquals([], $this->findById($scene['series'], $eSeriesForeign['id']), 'la série d\'une autre structure ne sort pas');
		$this->assertEquals([], $this->findById($scene['places'], $ePlaceForeign['id']), 'l\'emplacement d\'une autre structure ne sort pas');
		$this->assertEquals([], $this->findById($scene['series'], $ePlaceForeign['series']['id']));

	}

	public function testBedDates(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$season = $this->getSeason();

		// La semaine 35 de la saison d'avant se calcule -65, la semaine 13 de la saison d'après 113
		$eSeries = $this->createSeriesPlaced('Série à cheval sur trois saisons');
		$this->plant($eSeries, ($season - 1).'-W35', ($season + 1).'-W13');

		// La semaine 43 de deux saisons avant se calcule -157 : l'écart d'années n'a pas de borne
		$eSeriesOut = $this->createSeriesPlaced('Série plantée deux saisons avant');
		$this->plant($eSeriesOut, ($season - 2).'-W43', $season.'-W20');

		$eSeriesUndated = $this->createSeriesPlaced('Série sans intervention');

		$eSeries = \series\Series::model()->select(['id', 'bedStartCalculated', 'bedStopCalculated'])->whereId($eSeries)->get();
		$eSeriesOut = \series\Series::model()->select(['id', 'bedStartCalculated', 'bedStopCalculated'])->whereId($eSeriesOut)->get();

		if($this->assertDecorPresent(
			(
				$eSeries['bedStartCalculated'] === -65 and $eSeries['bedStopCalculated'] === 113 and
				$eSeriesOut['bedStartCalculated'] === -157 and $eSeriesOut['bedStopCalculated'] === 20
			),
			'des semaines de planche calculées -65 et 113 puis -157 et 20 par les plantations du décor, lues '.$eSeries['bedStartCalculated'].' et '.$eSeries['bedStopCalculated'].' puis '.$eSeriesOut['bedStartCalculated'].' et '.$eSeriesOut['bedStopCalculated']
		) === FALSE) {
			return;
		}

		$scene = $this->getScene();

		$series = $this->findById($scene['series'], $eSeries['id']);

		$this->assertEquals(week_date_starts(($season - 1).'-W35'), $series['start']);
		$this->assertEquals(week_date_ends(($season + 1).'-W13'), $series['stop']);
		$this->assertEquals('1', date('N', strtotime($series['start'])), 'start est un lundi');
		$this->assertEquals('7', date('N', strtotime($series['stop'])), 'stop est un dimanche');

		$seriesOut = $this->findById($scene['series'], $eSeriesOut['id']);

		$this->assertEquals(week_date_starts(($season - 2).'-W43'), $seriesOut['start'], 'une plantation de deux saisons avant se date');
		$this->assertEquals(week_date_ends($season.'-W20'), $seriesOut['stop']);

		$seriesUndated = $this->findById($scene['series'], $eSeriesUndated['id']);

		$this->assertEquals(NULL, $seriesUndated['start']);
		$this->assertEquals(NULL, $seriesUndated['stop']);

	}

	public function testSeriesPerennial(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$season = $this->getSeason();

		// Première saison d'une pérenne de cinq ans : elle prend sa planche à sa plantation et la garde
		$eSeriesFirst = $this->createSeriesPerennial('Pérenne en première saison');
		$this->createPlace($eSeriesFirst, $this->createBed(), 100);
		$this->plant($eSeriesFirst, $season.'-W12');

		// Installée et pas encore à terme : la saison entière, sans aucune semaine calculée
		$eSeriesRunning = $this->createSeriesPerennial('Pérenne installée');
		$this->createPlace($eSeriesRunning, $this->createBed(), 100);

		// Dernière saison : elle rend sa planche à sa dernière intervention
		$eSeriesLast = $this->createSeriesPerennial('Pérenne en dernière saison');
		$this->createPlace($eSeriesLast, $this->createBed(), 100);
		$this->plant($eSeriesLast, $season.'-W30');

		// Dernière saison close la saison d'avant : son lundi de semaine 1 tombe après son dernier dimanche
		$eSeriesClosedBefore = $this->createSeriesPerennial('Pérenne close avant la saison');
		$this->createPlace($eSeriesClosedBefore, $this->createBed(), 100);
		$this->plant($eSeriesClosedBefore, ($season - 1).'-W44');

		// Première saison plantée la saison d'après : sa plantation tombe après le dernier dimanche de la saison
		$eSeriesPlantedAfter = $this->createSeriesPerennial('Pérenne plantée après la saison');
		$this->createPlace($eSeriesPlantedAfter, $this->createBed(), 100);
		$this->plant($eSeriesPlantedAfter, ($season + 1).'-W11');

		$eSeriesRunning['perennialSeason'] = 2;
		$eSeriesLast['perennialSeason'] = 5;
		$eSeriesClosedBefore['perennialSeason'] = 5;

		// Par le modèle : seul SeriesLib::duplicate fait avancer perennialSeason, en créant la série de la
		// saison suivante, et le cas veut plusieurs âges de pérenne dans la même saison.
		\series\Series::model()->select(['perennialSeason'])->update($eSeriesRunning);
		\series\Series::model()->select(['perennialSeason'])->update($eSeriesLast);
		\series\Series::model()->select(['perennialSeason'])->update($eSeriesClosedBefore);

		$scene = $this->getScene();

		$first = $this->findById($scene['series'], $eSeriesFirst['id']);

		$this->assertEquals(\series\Series::PERENNIAL, $first['cycle']);
		$this->assertEquals(week_date_starts($season.'-W12'), $first['start']);
		$this->assertEquals($this->getSeasonLastDay($season), $first['stop']);

		$running = $this->findById($scene['series'], $eSeriesRunning['id']);

		$this->assertEquals(week_date_starts($season.'-W01'), $running['start']);
		$this->assertEquals($this->getSeasonLastDay($season), $running['stop']);

		$last = $this->findById($scene['series'], $eSeriesLast['id']);

		$this->assertEquals(week_date_starts($season.'-W01'), $last['start']);
		$this->assertEquals(week_date_ends($season.'-W30'), $last['stop']);

		foreach([$eSeriesClosedBefore, $eSeriesPlantedAfter] as $eSeriesReversed) {

			$reversed = $this->findById($scene['series'], $eSeriesReversed['id']);

			$this->assertEquals(NULL, $reversed['start'], 'un début après la fin retire les deux bouts');
			$this->assertEquals(NULL, $reversed['stop']);

		}

	}

	/** Une saison à 53 semaines : la pérenne installée garde sa planche jusqu'à la veille de la saison suivante. */
	public function testSeriesPerennialSeasonOf53Weeks(): void {

		$this->season = 2032;

		$eSeries = $this->createSeriesPerennial('Pérenne installée une année à 53 semaines');
		$this->createPlace($eSeries, $this->createBed(), 100);

		$eSeries['perennialSeason'] = 2;

		// Par le modèle : seul SeriesLib::duplicate fait avancer perennialSeason, en créant la série de la
		// saison suivante.
		\series\Series::model()->select(['perennialSeason'])->update($eSeries);

		$series = $this->findById($this->getScene()['series'], $eSeries['id']);

		$this->assertEquals('2031-12-29', $series['start']);
		$this->assertEquals('2033-01-02', $series['stop'], 'le dimanche de la semaine 53, veille de la saison suivante, pas celui de la 52');

	}

	/** Une série en planches d'une autre saison que celle montrée, posée sur une planche neuve du jardin du décor. */
	private function createSeriesPlacedOn(int $season, string $name): \series\Series {

		// Le décor crée ses séries sur la saison du cas : elle se déplace le temps de la création, et la scène se lit ensuite
		// sur celle d'origine
		$this->season = $season;

		try {
			return $this->createSeriesPlaced($name);
		} finally {
			$this->season = NULL;
		}

	}

	/** Une pérenne de cinq ans d'une autre saison que celle montrée, posée sur une planche neuve, à sa première saison. */
	private function createSeriesPerennialPlacedOn(int $season, string $name): \series\Series {

		// Même déplacement de saison que createSeriesPlacedOn()
		$this->season = $season;

		try {
			$eSeries = $this->createSeriesPerennial($name);
		} finally {
			$this->season = NULL;
		}

		$this->createPlace($eSeries, $this->createBed(), 100);

		return $eSeries;

	}

	/**
	 * La scène d'une saison pose aussi les séries des saisons voisines qui tiennent leur planche pendant son année, et
	 * celles-là seulement : une voisine qui reste dans la sienne, une pérenne reconduite, une pérenne de la saison suivante
	 * déjà installée, une voisine à un seul bout et celle d'une autre structure n'en sortent pas.
	 */
	public function testSeriesOfNeighborSeasons(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$season = $this->getSeason();

		$eSeriesNext = $this->createSeriesPlacedOn($season + 1, 'Série de la saison suivante plantée en décembre');
		$this->plant($eSeriesNext, $season.'-W50', ($season + 1).'-W20');

		$eSeriesPrevious = $this->createSeriesPlacedOn($season - 1, 'Série de la saison précédente encore en place en janvier');
		$this->plant($eSeriesPrevious, ($season - 1).'-W30', $season.'-W10');

		$eSeriesNextInside = $this->createSeriesPlacedOn($season + 1, 'Série de la saison suivante dans son année');
		$this->plant($eSeriesNextInside, ($season + 1).'-W10', ($season + 1).'-W30');

		$eSeriesPreviousInside = $this->createSeriesPlacedOn($season - 1, 'Série de la saison précédente dans son année');
		$this->plant($eSeriesPreviousInside, ($season - 1).'-W10', ($season - 1).'-W40');

		$eSeriesOneEnd = $this->createSeriesPlacedOn($season - 1, 'Série de la saison précédente sans début');
		$this->plant($eSeriesOneEnd, ($season - 1).'-W30', $season.'-W10');

		$eSeriesContinued = $this->createSeriesPerennialPlacedOn($season - 1, 'Pérenne de la saison précédente reconduite');
		$this->plant($eSeriesContinued, ($season - 1).'-W12', $season.'-W05');

		$eSeriesLast = $this->createSeriesPerennialPlacedOn($season - 1, 'Pérenne de la saison précédente en dernière saison');
		$this->plant($eSeriesLast, $season.'-W05');

		$eSeriesNextFirst = $this->createSeriesPerennialPlacedOn($season + 1, 'Pérenne de la saison suivante plantée en décembre');
		$this->plant($eSeriesNextFirst, $season.'-W50');

		$eSeriesNextInstalled = $this->createSeriesPerennialPlacedOn($season + 1, 'Pérenne de la saison suivante déjà installée');
		$this->plant($eSeriesNextInstalled, $season.'-W50');

		// Par le modèle : seul SeriesLib::duplicate fait avancer perennialSeason, en créant la série de la saison suivante
		$eSeriesLast['perennialSeason'] = 5;
		$eSeriesNextInstalled['perennialSeason'] = 2;

		\series\Series::model()->select(['perennialSeason'])->update($eSeriesLast);
		\series\Series::model()->select(['perennialSeason'])->update($eSeriesNextInstalled);

		// Par le modèle : la plantation du décor date toujours les deux bouts, alors qu'une série du produit peut n'en porter
		// qu'un, et chacun de ces cas veut celle-là. Une pérenne installée ou reconduite tient sa planche sans le bout qui
		// lui manque, une annuelle non.
		$eSeriesOneEnd['bedStartCalculated'] = NULL;
		$eSeriesLast['bedStartCalculated'] = NULL;
		$eSeriesNextFirst['bedStopCalculated'] = NULL;

		\series\Series::model()->select(['bedStartCalculated'])->update($eSeriesOneEnd);
		\series\Series::model()->select(['bedStartCalculated'])->update($eSeriesLast);
		\series\Series::model()->select(['bedStopCalculated'])->update($eSeriesNextFirst);

		$this->stopOnClosedTransaction('avant le témoin d\'une autre structure');

		$eBed = $this->createBed();

		$eSeriesForeign = new \series\Series([
			'name' => 'Série témoin de la saison suivante',
			'structure' => $this->getStructureForeign(),
			'season' => $season + 1,
			'use' => \series\Series::BED,
			'plants' => 1,
			'cycle' => \series\Series::ANNUAL,
			'bedStartCalculated' => -50,
			'bedStopCalculated' => 20,
			'createdBy' => $this->getMember(),
		]);

		// Par le modèle, comme le témoin de testSeriesOfAnotherStructure() : il vit dans une structure réelle, où il ne doit
		// poser que sa ligne, avec des semaines de planche qui le feraient poser s'il était de la structure
		\series\Series::model()->insert($eSeriesForeign);

		// Par le modèle : PlaceLib::addBeds() refuse une planche qui n'est pas de la structure de la série, c'est l'état que
		// le produit ne fabrique pas
		$ePlaceCrossed = new \series\Place([
			'structure' => $this->eStructure,
			'season' => $season + 1,
			'series' => $eSeriesForeign,
			'task' => new \series\Task(),
			'zone' => $eBed['zone'],
			'plot' => $eBed['plot'],
			'bed' => $eBed,
			'length' => 5,
			'width' => 80,
			'createdBy' => $this->getMember(),
		]);

		\series\Place::model()->insert($ePlaceCrossed);

		$cSeries = \series\Series::model()
			->select(['id', 'bedStartCalculated', 'bedStopCalculated'])
			->whereId('IN', [$eSeriesNext['id'], $eSeriesPrevious['id'], $eSeriesContinued['id'], $eSeriesNextFirst['id'], $eSeriesNextInstalled['id']])
			->getCollection(index: 'id');

		if($this->assertDecorPresent(
			(
				$cSeries[$eSeriesNext['id']]['bedStartCalculated'] === -50 and $cSeries[$eSeriesNext['id']]['bedStopCalculated'] === 20 and
				$cSeries[$eSeriesPrevious['id']]['bedStartCalculated'] === 30 and $cSeries[$eSeriesPrevious['id']]['bedStopCalculated'] === 110 and
				$cSeries[$eSeriesContinued['id']]['bedStopCalculated'] === 105 and
				$cSeries[$eSeriesNextFirst['id']]['bedStartCalculated'] === -50 and
				$cSeries[$eSeriesNextInstalled['id']]['bedStartCalculated'] === -50
			),
			'des semaines de planche calculées -50 et 20, 30 et 110, puis une fin à 105 et deux débuts à -50 pour les pérennes, par les plantations du décor'
		) === FALSE) {
			return;
		}

		$scene = $this->getScene();

		$next = $this->findById($scene['series'], $eSeriesNext['id']);

		$this->assertEquals($season + 1, $next['season']);
		$this->assertEquals(week_date_starts($season.'-W50'), $next['start']);
		$this->assertEquals(week_date_ends(($season + 1).'-W20'), $next['stop']);

		$previous = $this->findById($scene['series'], $eSeriesPrevious['id']);

		$this->assertEquals($season - 1, $previous['season']);
		$this->assertEquals(week_date_starts(($season - 1).'-W30'), $previous['start']);
		$this->assertEquals(week_date_ends($season.'-W10'), $previous['stop']);

		// Installée, elle prend sa planche en semaine 1 de sa propre saison, et non de celle montrée
		$last = $this->findById($scene['series'], $eSeriesLast['id']);

		$this->assertEquals(week_date_starts(($season - 1).'-W01'), $last['start'], 'une pérenne voisine se date sur sa propre saison');
		$this->assertEquals(week_date_ends($season.'-W05'), $last['stop']);

		// Première saison d'une pérenne : elle garde sa planche jusqu'à la fin de sa saison, sans fin calculée
		$nextFirst = $this->findById($scene['series'], $eSeriesNextFirst['id']);

		$this->assertEquals(week_date_starts($season.'-W50'), $nextFirst['start'], 'une pérenne de la saison suivante plantée en décembre sort');
		$this->assertEquals($this->getSeasonLastDay($season + 1), $nextFirst['stop']);

		$placesBySeries = array_count_values(array_column($scene['places'], 'series'));

		$this->assertEquals(1, $placesBySeries[$eSeriesNext['id']] ?? 0, 'la place de la série de la saison suivante sort, une fois');
		$this->assertEquals(1, $placesBySeries[$eSeriesPrevious['id']] ?? 0, 'la place de la série de la saison précédente sort, une fois');

		foreach([
			[$eSeriesNextInside, 'une série de la saison suivante qui ne prend sa planche qu\'après l\'année ne sort pas'],
			[$eSeriesPreviousInside, 'une série de la saison précédente qui rend sa planche avant l\'année ne sort pas'],
			[$eSeriesOneEnd, 'une voisine sans ses deux bouts ne sort pas'],
			[$eSeriesContinued, 'une pérenne reconduite ne sort pas, la série qui la suit tient sa place'],
			[$eSeriesNextInstalled, 'une pérenne installée de la saison suivante ne prend sa planche qu\'en semaine 1 de sa saison'],
			[$eSeriesForeign, 'une voisine d\'une autre structure ne sort pas'],
		] as [$eSeriesOut, $message]) {
			$this->assertEquals([], $this->findById($scene['series'], $eSeriesOut['id']), $message);
		}

		$this->assertEquals([], $this->findById($scene['places'], $ePlaceCrossed['id']), 'ni la place qui la cite');

	}

	/** Seules sortent les planches rendues : la ligne d'une planche hors saison reste en base sans sortir. */
	public function testDrawOfBedOutOfSeason(): void {

		$ePlot = $this->createPlot();
		$eBedKept = $this->createBed($ePlot);
		$eBedRetired = $this->createBed($ePlot);

		$this->createDraws($ePlot, new \Collection([$eBedRetired['id'] => $eBedRetired, $eBedKept['id'] => $eBedKept]), $this->getSeason() - 1);

		$ePlotRetired = $this->createPlot();
		$eBedAlone = $this->createBed($ePlotRetired);

		// Une ligne plus ancienne du même jardin, sur une planche gardée : elle ne revient pas à la place
		// de la planche retirée, le jardin n'a que les lignes de sa dernière saison dessinée
		$eBedOlder = $this->createBed($ePlotRetired);
		$this->createDraws($ePlotRetired, new \Collection([$eBedOlder]), $this->getSeason() - 2);
		$this->createDraws($ePlotRetired, new \Collection([$eBedAlone]), $this->getSeason() - 1);

		$scene = $this->getScene();

		$this->assertDiffer([], $this->findByBed($scene['bedsDrawn'], $eBedRetired['id']));
		$this->assertDiffer([], $this->findByBed($scene['bedsDrawn'], $eBedKept['id']));

		foreach([$eBedRetired, $eBedAlone] as $eBed) {

			$eBed['seasonLast'] = $this->getSeason() - 1;

			// Sans build(), qui vérifierait la dernière saison postée contre la première et contre celle de
			// ce qui porte la planche : rien dans le décor n'a de borne de saison, toute valeur convient.
			BedLib::update($eBed, ['seasonLast']);

		}

		$scene = $this->getScene();

		$this->assertEquals([], $this->findById($scene['beds'], $eBedRetired['id']));
		$this->assertEquals([$eBedKept['id']], array_column(array_filter($scene['bedsDrawn'], fn($bedDrawn) => $bedDrawn['plot'] === $ePlot['id']), 'bed'), 'la planche hors saison ne sort pas, sa voisine reste');

		$this->assertDiffer([], $this->findById($scene['plots'], $ePlotRetired['id']), 'le jardin reste de la saison');
		$this->assertEquals([], $this->findByBed($scene['bedsDrawn'], $eBedAlone['id']), 'la ligne d\'une planche qui n\'est plus rendue ne sort pas');
		$this->assertEquals($this->getSeason() - 1, $this->findByBed($scene['bedsDrawn'], $eBedOlder['id'])['season'], 'et ne rend pas la main à une saison plus ancienne : la planche gardée sort de la dernière saison dessinée, qui l\'a recopiée');

	}

	public function testHarvestWindow(): void {

		if($this->requirePlanEmpty() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE or $this->requireAction(ACTION_RECOLTE) === FALSE or $this->requireMember() === FALSE) {
			return;
		}

		$season = $this->getSeason();

		$eSeries = $this->createSeriesPlaced('Série récoltée sur cinq années');
		$eCultivation = $this->plant($eSeries, $season.'-W20');

		foreach([
			($season - 2).'-06-30', ($season - 2).'-07-01',
			($season + 2).'-03-31', ($season + 2).'-04-01'
		] as $date) {
			$this->createHarvest($this->createTaskHarvest($eCultivation, toWeek($date), $date), 1.0, $date);
		}

		$this->assertEquals(
			[($season - 2).'-07-01', ($season + 2).'-03-31'],
			array_column($this->getScene()['harvests'], 0)
		);

	}

	public function testCrew(): void {

		if($this->requirePlanEmpty() === FALSE) {
			return;
		}

		$cUser = \structure\MemberLib::getUsersByStructure($this->eStructure);

		if($this->assertDecorPresent($cUser->count() >= 2, 'deux membres dans l\'équipe de la structure '.static::STRUCTURE.', pour compter des membres distincts') === FALSE) {
			return;
		}

		$season = $this->getSeason();

		// L'équipe du décor compterait chaque jour : le cas pose seul les présences et les absences qu'il compte
		$cPresence = \hr\Presence::model()
			->select(['id', 'structure'])
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		$cAbsence = \hr\Absence::model()
			->select(['id', 'structure'])
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cAbsence as $eAbsence) {
			\hr\AbsenceLib::delete($eAbsence);
		}

		$eUserFirst = $cUser->first();
		$eUserSecond = $cUser->find(fn($eUser) => $eUser['id'] !== $eUserFirst['id'], limit: 1, clone: FALSE);

		// Une présence n'est posée qu'à une personne de l'équipe (`Presence::user.teammate`). Sans `build()` :
		// les deux viennent des membres de la structure, seul contrôle de l'étape `member`.
		foreach([$eUserFirst, $eUserSecond] as $eUser) {
			if(\hr\TeammateLib::isTeammate($this->eStructure, $eUser) === FALSE) {
				\hr\TeammateLib::create(new \hr\Teammate([
					'structure' => $this->eStructure,
					'user' => $eUser
				]));
			}
		}

		// Le premier : du 10 au 12 mars, absent le 11, et en décembre de l'année d'avant, hors de la saison.
		// Le second : à partir du 11 mars, sans fin.
		foreach([
			[$eUserFirst, $season.'-03-10', $season.'-03-12'],
			[$eUserFirst, ($season - 1).'-12-01', ($season - 1).'-12-31'],
			[$eUserSecond, $season.'-03-11', NULL],
		] as [$eUser, $from, $to]) {

			$this->stopOnClosedTransaction('avant une présence du '.$from);

			$fw = new \FailWatch();

			$ePresence = new \hr\Presence(['structure' => $this->eStructure]);
			$ePresence->build(\hr\PresenceLib::getPropertiesCreate(), ['user' => $eUser['id'], 'from' => $from, 'to' => $to]);

			$this->assertTrue($fw->ok(), json_encode($fw->get()));

			\hr\PresenceLib::create($ePresence);

		}

		$this->enableHr();

		$fw = new \FailWatch();

		$eAbsence = new \hr\Absence(['structure' => $this->eStructure, 'user' => $eUserFirst]);
		$eAbsence->build(\hr\AbsenceLib::getPropertiesCreate(), ['from' => $season.'-03-11 08:00:00', 'to' => $season.'-03-11 18:00:00', 'duration' => 1.0, 'type' => \hr\Absence::VACATION]);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		\hr\AbsenceLib::create($eAbsence);

		$crew = $this->getScene()['crew'];

		$this->assertEquals(
			[[$season.'-03-10', 1, NULL], [$season.'-03-11', 1, NULL], [$season.'-03-12', 2, NULL], [$season.'-03-13', 1, NULL]],
			array_slice($crew, 0, 4)
		);

		$this->assertEquals([$season.'-12-31', 1, NULL], end($crew));

		$this->assertEquals(
			(int)((strtotime($season.'-12-31') - strtotime($season.'-03-10')) / 86400) + 1,
			count($crew)
		);

	}

}
?>
