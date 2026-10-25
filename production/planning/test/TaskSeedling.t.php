<?php
namespace planning;

/**
 * Ce que la Semaine prépare pour les variétés d'un semis ou d'une plantation (FF, retour 407), que la carte et la
 * ligne de la grille affichent (`TaskCardUi::getFacts()`) : `TaskWeekLib::fillSeedling()`, appelé par les
 * deux lectures des cartes, `TaskWeekLib::get()` pour la semaine et `TaskLateLib::getTasks()` pour les zones.
 *
 * Le décor est monté dans la transaction de la méthode : des séries en planches, des cultures dont l'implantation
 * est posée par le cas, deux variétés à 60 et 40 %, et une pépinière qui porte une plaque de 104 trous et un
 * engrais rangé dans son matériel, dont la valeur de routine ne doit pas compter pour une plaque.
 *
 * Une série en planches de 100 m d'objectif, 2 rangs à 25 cm, porte 800 plants : 480 et 320 pour les deux
 * variétés, l'espèce du décor n'ayant pas de marge de sécurité.
 *
 * Lancement : dev/test package=planning file=TaskSeedling
 */
class TaskSeedlingTest extends HarvestFixtureTest {

	/**
	 * La lecture de la Semaine charge le matériel de la structure dans un cache du processus, que la transaction
	 * annulée ne vide pas : un test suivant y chercherait en vain l'outil qu'il crée.
	 */
	public function tearDown(): void {

		$this->resetToolCache();

		parent::tearDown();

	}

	/**
	 * En pépinière, chaque variété porte ses plants, ses graines et les plateaux de la plaque, à sa propre valeur :
	 * les plaques viennent d'un cache d'outils partagé, une valeur posée sur la plaque y serait écrasée par la
	 * variété suivante. L'engrais rangé dans le matériel n'est pas une plaque. La plantation de la même culture,
	 * lue en premier, partage ses tranches avec la pépinière et ne doit pas lui retirer ses plateaux.
	 */
	public function testNursery(): void {

		if($this->requireAction(ACTION_SEMIS_PEPINIERE) === FALSE or $this->requireAction(ACTION_FERTILISATION) === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		[$eToolTray, $eToolFertilizer] = $this->createTools();

		$eCultivation = $this->createCultivationSeedling(\series\Cultivation::YOUNG_PLANT);
		[$eVarietyMain, $eVarietyOther] = $this->createSlices($eCultivation);

		$eTaskPlantation = $this->createTaskSeedling($eCultivation, ACTION_PLANTATION);
		$eTask = $this->createTaskSeedling($eCultivation, ACTION_SEMIS_PEPINIERE, [$eToolTray['id'], $eToolFertilizer['id']]);

		$cTask = TaskWeekLib::get($this->eStructure, \Clock::week());
		$eTaskWeek = $cTask[$eTask['id']];

		if($this->assertEquals([$eCultivation['id']], $eTaskWeek['cCultivation']->getIds(), 'la pépinière ne garde pas sa culture en plants autoproduits') === FALSE) {
			return;
		}

		$cSlice = $eTaskWeek['cCultivation'][$eCultivation['id']]['cSlice'];

		$this->assertEquals([
			$eVarietyMain['id'] => ['youngPlants' => 480, 'seeds' => 960, 'trays' => [$eToolTray['id'] => 4.7], 'targeted' => TRUE],
			$eVarietyOther['id'] => ['youngPlants' => 320, 'seeds' => 640, 'trays' => [$eToolTray['id'] => 3.1], 'targeted' => TRUE],
		], $this->readSlices($cSlice), 'les plants, graines et plateaux de chaque variété en pépinière');

		$this->assertTrue($eTaskWeek['cCultivation'][$eCultivation['id']]['hasTrays'], 'une pépinière avec une plaque ne demande pas de colonne de plateaux');
		$this->assertFalse($cTask[$eTaskPlantation['id']]['cCultivation'][$eCultivation['id']]['hasTrays'], 'une plantation demande une colonne de plateaux');

		$this->assertEquals([[], []], array_column($this->readSlices($cTask[$eTaskPlantation['id']]['cCultivation'][$eCultivation['id']]['cSlice']), 'trays'), 'la plantation reçoit les plateaux de la pépinière de sa culture');

	}

	/**
	 * Une pépinière sans plaque n'a pas de plateaux, et la carte ne leur donne pas de colonne : l'engrais rangé dans
	 * son matériel porte une valeur de routine, mais n'est pas une plaque.
	 */
	public function testNurseryWithoutTray(): void {

		if($this->requireAction(ACTION_SEMIS_PEPINIERE) === FALSE or $this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		[, $eToolFertilizer] = $this->createTools();

		$eCultivation = $this->createCultivationSeedling(\series\Cultivation::YOUNG_PLANT);
		[$eVarietyMain, $eVarietyOther] = $this->createSlices($eCultivation);

		$eTask = $this->createTaskSeedling($eCultivation, ACTION_SEMIS_PEPINIERE, [$eToolFertilizer['id']]);

		$eCultivationWeek = TaskWeekLib::get($this->eStructure, \Clock::week())[$eTask['id']]['cCultivation'][$eCultivation['id']];

		$this->assertFalse($eCultivationWeek['hasTrays'], 'une pépinière sans plaque demande une colonne de plateaux');

		$this->assertEquals([
			$eVarietyMain['id'] => ['youngPlants' => 480, 'seeds' => 960, 'trays' => [], 'targeted' => TRUE],
			$eVarietyOther['id'] => ['youngPlants' => 320, 'seeds' => 640, 'trays' => [], 'targeted' => TRUE],
		], $this->readSlices($eCultivationWeek['cSlice']), 'les variétés d\'une pépinière sans plaque');

	}

	/**
	 * Une plantation n'a pas de plateaux, même quand l'intervention porte une plaque, et une culture semée en
	 * direct n'est pas plantée : la plantation qui la vise ne garde aucune culture.
	 */
	public function testPlantation(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE or $this->requireAction(ACTION_SEMIS_PEPINIERE) === FALSE) {
			return;
		}

		[$eToolTray] = $this->createTools();

		$eCultivation = $this->createCultivationSeedling(\series\Cultivation::YOUNG_PLANT_BOUGHT);
		[$eVarietyMain, $eVarietyOther] = $this->createSlices($eCultivation);

		$eTask = $this->createTaskSeedling($eCultivation, ACTION_PLANTATION, [$eToolTray['id']]);
		$eTaskSowing = $this->createTaskSeedling($this->createCultivationSeedling(\series\Cultivation::SOWING), ACTION_PLANTATION);

		$cTask = TaskWeekLib::get($this->eStructure, \Clock::week());

		$this->assertEquals([
			$eVarietyMain['id'] => ['youngPlants' => 480, 'seeds' => NULL, 'trays' => [], 'targeted' => TRUE],
			$eVarietyOther['id'] => ['youngPlants' => 320, 'seeds' => NULL, 'trays' => [], 'targeted' => TRUE],
		], $this->readSlices($cTask[$eTask['id']]['cCultivation'][$eCultivation['id']]['cSlice']), 'les plants de chaque variété d\'une plantation de plants achetés');

		$this->assertEquals(0, $cTask[$eTaskSowing['id']]['cCultivation']->count(), 'la plantation d\'une culture semée en direct garde sa culture');

	}

	/**
	 * Un semis direct conduit à la densité, sur une série en surface : la culture sans variété en reçoit une vide,
	 * qui porte la quantité entière, et sa surface n'est qu'un objectif. Une intervention d'une autre action ne
	 * reçoit rien.
	 */
	public function testSowingDensity(): void {

		if($this->requireAction(ACTION_SEMIS_DIRECT) === FALSE or $this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation($this->createSeries('Série semée '.uniqid()), $this->createPlant());

		// Sans `build()`, qui lirait l'implantation dans le formulaire de la culture : le décor la pose telle que le
		// cas la veut, une densité de 10 plants au m² sur les 100 m² d'objectif de la série.
		\series\Cultivation::model()->update($eCultivation, [
			'seedling' => \series\Cultivation::SOWING,
			'seedlingSeeds' => 1,
			'distance' => \series\Cultivation::DENSITY,
			'density' => 10.0,
		]);

		$eTask = $this->createTaskSeedling($eCultivation, ACTION_SEMIS_DIRECT);
		$eTaskOther = $this->createTaskSeedling($eCultivation, ACTION_FERTILISATION);

		$cTask = TaskWeekLib::get($this->eStructure, \Clock::week());
		$cSlice = $cTask[$eTask['id']]['cCultivation'][$eCultivation['id']]['cSlice'];

		$this->assertEquals([
			'' => ['youngPlants' => 1000, 'seeds' => 1000, 'trays' => [], 'targeted' => TRUE],
		], $this->readSlices($cSlice), 'le semis direct sans variété, conduit à la densité');

		$this->assertFalse($cTask[$eTaskOther['id']]->offsetExists('cCultivation'), 'une fertilisation reçoit les cultures d\'un semis');

	}

	/**
	 * Les zones de la Semaine lisent leurs cartes ailleurs (`TaskLateLib::getTasks()`) et reçoivent les mêmes
	 * variétés : une pépinière en retard porte ses plateaux comme dans sa semaine.
	 */
	public function testLate(): void {

		if($this->requireAction(ACTION_SEMIS_PEPINIERE) === FALSE or $this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		[$eToolTray] = $this->createTools();

		$eCultivation = $this->createCultivationSeedling(\series\Cultivation::YOUNG_PLANT);
		[$eVarietyMain, $eVarietyOther] = $this->createSlices($eCultivation);

		$eTask = $this->createTaskSeedling($eCultivation, ACTION_SEMIS_PEPINIERE, [$eToolTray['id']], toWeek(\Clock::week().' - 2 WEEK'));

		$cTask = TaskLateLib::getTasks($this->eStructure, TaskLateLib::LATE, \Clock::week(), [$eTask['id']]);

		if($this->assertTrue($cTask->offsetExists($eTask['id']), 'la pépinière en retard n\'est pas dans sa zone') === FALSE) {
			return;
		}

		$this->assertEquals([
			$eVarietyMain['id'] => ['youngPlants' => 480, 'seeds' => 960, 'trays' => [$eToolTray['id'] => 4.7], 'targeted' => TRUE],
			$eVarietyOther['id'] => ['youngPlants' => 320, 'seeds' => 640, 'trays' => [$eToolTray['id'] => 3.1], 'targeted' => TRUE],
		], $this->readSlices($cTask[$eTask['id']]['cCultivation'][$eCultivation['id']]['cSlice']), 'les variétés d\'une pépinière en retard');

	}

	/**
	 * Une plaque de 104 trous et un engrais, créés dans la structure du décor.
	 *
	 * @return array [\work\Tool plaque, \work\Tool engrais]
	 */
	private function createTools(): array {

		$fw = new \FailWatch();

		$eToolTray = new \work\Tool(['structure' => $this->eStructure]);
		$eToolTray->build(\work\ToolLib::getPropertiesCreate(), [
			'name' => 'Plaque '.uniqid(),
			'action' => $this->getAction(ACTION_SEMIS_PEPINIERE)['id'],
			'routineName' => 'tray',
			'routineValue' => ['value' => 104],
		]);

		$eToolFertilizer = new \work\Tool(['structure' => $this->eStructure]);
		$eToolFertilizer->build(\work\ToolLib::getPropertiesCreate(), [
			'name' => 'Engrais '.uniqid(),
			'action' => $this->getAction(ACTION_FERTILISATION)['id'],
			'routineName' => 'fertilizer',
			'routineValue' => ['n' => 6],
		]);

		$fw->validate();

		\work\ToolLib::create($eToolTray);
		\work\ToolLib::create($eToolFertilizer);

		// `askCollection()` lit le matériel dans un cache du processus, que les outils créés ici ne rejoignent pas.
		$this->resetToolCache();

		return [$eToolTray, $eToolFertilizer];

	}

	/**
	 * Une culture en planches de son espèce et de sa série, 2 rangs à 25 cm et 2 graines par plant.
	 */
	private function createCultivationSeedling(string $seedling): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeriesBed('Série en planches '.uniqid()), $this->createPlant());

		// Sans `build()`, qui lirait l'implantation dans le formulaire de la culture et en déduirait la densité :
		// le décor la pose telle que les quantités du cas l'attendent, 2 rangs de 4 plants au mètre sur une planche
		// de 80 cm, soit 10 plants au m².
		\series\Cultivation::model()->update($eCultivation, [
			'seedling' => $seedling,
			'seedlingSeeds' => match($seedling) {
				\series\Cultivation::YOUNG_PLANT_BOUGHT => NULL,
				\series\Cultivation::SOWING, \series\Cultivation::YOUNG_PLANT => 2
			},
			'rows' => 2,
			'plantSpacing' => 25.0,
			'rowSpacing' => 30.0,
			'density' => 10.0,
		]);

		return $eCultivation;

	}

	/**
	 * @return array [\plant\Variety à 60 %, \plant\Variety à 40 %]
	 */
	private function createSlices(\series\Cultivation $eCultivation): array {

		$eVarietyMain = $this->createVariety($eCultivation['plant']);
		$eVarietyOther = $this->createVariety($eCultivation['plant']);

		$this->createSlice($eCultivation, $eVarietyMain, 60.0);
		$this->createSlice($eCultivation, $eVarietyOther, 40.0);

		return [$eVarietyMain, $eVarietyOther];

	}

	private function createTaskSeedling(\series\Cultivation $eCultivation, string $actionFqn, array $tools = [], ?string $week = NULL): \series\Task {

		$eTask = $this->createTask($eCultivation, $actionFqn, \series\Task::TODO, $week ?? \Clock::week());

		if($tools !== []) {

			// Sans `build()`, qui vérifierait que le matériel sert l'action : le cas range un engrais dans le
			// matériel d'une pépinière, ce que le formulaire n'offre pas, pour prouver qu'il n'y compte pas.
			\series\Task::model()->update($eTask, ['tools' => $tools]);

		}

		return $eTask;

	}

	private function readSlices(\Collection $cSlice): array {

		$slices = [];

		foreach($cSlice as $eSlice) {
			// La culture conduite sans variété reçoit une tranche à variété vide, rangée sous la clé vide.
			$slices[$eSlice['variety']->empty() ? '' : $eSlice['variety']['id']] = [
				'youngPlants' => $eSlice['youngPlants'],
				'seeds' => $eSlice['seeds'],
				'trays' => $eSlice['trays'],
				'targeted' => $eSlice['targeted'],
			];
		}

		// Les tranches viennent dans l'ordre des noms de variétés, tirés au hasard par le décor.
		ksort($slices);

		return $slices;

	}

}
?>
