<?php
namespace series;

/**
 * Un matériel fusionné dans un produit, ou qui change de routine, se range à sa place dans chaque
 * intervention, répétition et intervention d'itinéraire qui le portait : en produit s'il en est un
 * pour l'action, en matériel sinon. « Modifier » garde ensuite ce rangement, et revenir à l'action
 * d'origine dans le formulaire retrouve ce qui est enregistré (lot FM du chantier planning,
 * R-Retours-1210, décidé le 16/10).
 *
 * Isolation : la transaction du décor (`SeriesFixtureAiTest`), annulée en tearDown(), emporte les
 * matériels, interventions, répétitions et itinéraires posés par les cas.
 *
 * Lancement : dev/test package=series file=WorkMoveToolAi
 */
class WorkMoveToolAiTest extends SeriesFixtureAiTest {

	const string FERTILIZER = 'fertilizer';
	const string PHYTO = 'phyto';

	/**
	 * Le matériel enregistré sous l'AMM d'un produit existant s'y fusionne : un produit sur le
	 * traitement, du matériel sur la plantation, et l'ancien matériel n'est plus cité nulle part.
	 */
	public function testMergeIntoProduct(): void {

		if($this->requireActions([ACTION_TRAITEMENT, ACTION_PLANTATION]) === FALSE) {
			return;
		}

		$amm = 'FM'.uniqid();

		$eToolProduct = $this->createTool(self::PHYTO, $amm);
		$eTool = $this->createTool(NULL);

		$eTaskTreatment = $this->createTaskWith(ACTION_TRAITEMENT, tools: [$eTool['id']]);
		$eTaskPlantation = $this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);
		$eRepeat = $this->createRepeatWith(ACTION_PLANTATION, tools: [$eTool['id']]);

		$eSequence = $this->getSequence();
		$eFlowTreatment = $this->createFlowWith($eSequence, ACTION_TRAITEMENT, tools: [$eTool['id']]);
		$eFlowPlantation = $this->createFlowWith($eSequence, ACTION_PLANTATION, tools: [$eTool['id']]);

		$this->changeRoutine($eTool, self::PHYTO, $amm);

		$this->assertEquals([[], [$eToolProduct['id']]], $this->read(Task::model(), $eTaskTreatment), 'le traitement reçoit le produit');
		$this->assertEquals([[$eToolProduct['id']], []], $this->read(Task::model(), $eTaskPlantation), 'la plantation le garde en matériel');
		$this->assertEquals([[$eToolProduct['id']], []], $this->read(Repeat::model(), $eRepeat), 'la répétition qui le portait seule le garde en matériel');
		$this->assertEquals([[], [$eToolProduct['id']]], $this->read(\sequence\Flow::model(), $eFlowTreatment), 'l\'itinéraire de traitement reçoit le produit');
		$this->assertEquals([[$eToolProduct['id']], []], $this->read(\sequence\Flow::model(), $eFlowPlantation), 'l\'itinéraire de plantation le garde en matériel');

		$this->assertEquals(0, $this->countCiting($eTool), 'l\'ancien matériel n\'est plus cité');

	}

	/**
	 * La reprise d'un matériel en produit de traitement annonce ce que la fusion fera : seul le
	 * traitement le reçoit, les quatre autres interventions le gardent en matériel. Avant FM, le
	 * compte de toutes les interventions qui le portaient valait 5.
	 */
	public function testCountByToolFollowsMove(): void {

		if($this->requireActions([ACTION_TRAITEMENT, ACTION_PLANTATION, ACTION_FERTILISATION]) === FALSE) {
			return;
		}

		$eTool = $this->createTool(NULL);

		$this->createTaskWith(ACTION_TRAITEMENT, tools: [$eTool['id']]);
		$this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);
		$this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);
		$this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);
		$this->createTaskWith(ACTION_FERTILISATION, tools: [$eTool['id']]);

		$eToolForm = \work\ToolLib::getById($eTool['id']);
		\work\ToolLib::loadPhytoReprise($eToolForm, NULL);

		$this->assertEquals(1, $eToolForm['phytoTasks'], 'le traitement reçoit le produit');
		$this->assertEquals(4, $eToolForm['phytoTasksKept'], 'les autres interventions le gardent en matériel');

	}

	/**
	 * `getProductByAction()` rend NULL pour une action sans produit, comme `routineName` d'un matériel
	 * ordinaire : sans la garde, la plantation le rangerait dans ses produits.
	 */
	public function testOrdinaryToolStaysTool(): void {

		if($this->requireActions([ACTION_PLANTATION]) === FALSE) {
			return;
		}

		$eTool = $this->createTool(NULL);
		$eTask = $this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);

		$this->assertFalse($eTool->isProductFor($this->getAction(ACTION_PLANTATION)), 'un matériel ordinaire n\'est le produit d\'aucune action');

		// Le geste qui range : enregistrer le matériel en gardant sa routine, qui n'en est pas une.
		\work\ToolLib::update($eTool, ['routineName', 'routineValue']);

		$this->assertEquals([[$eTool['id']], []], $this->read(Task::model(), $eTask));

	}

	public function testFertilizerOutsideFertilisationStaysTool(): void {

		if($this->requireActions([ACTION_PLANTATION, ACTION_FERTILISATION]) === FALSE) {
			return;
		}

		$eTool = $this->createTool(NULL);
		$eTask = $this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);

		$this->changeRoutine($eTool, self::FERTILIZER);

		$this->assertEquals([[$eTool['id']], []], $this->read(Task::model(), $eTask), 'un intrant reste du matériel hors fertilisation');

	}

	public function testPhytoBecomingFertilizerOnTreatment(): void {

		if($this->requireActions([ACTION_TRAITEMENT, ACTION_FERTILISATION]) === FALSE) {
			return;
		}

		$eTool = $this->createTool(self::PHYTO);
		$eTask = $this->createTaskWith(ACTION_TRAITEMENT, products: [$eTool['id']]);

		$this->changeRoutine($eTool, self::FERTILIZER);

		$this->assertEquals([[$eTool['id']], []], $this->read(Task::model(), $eTask), 'un intrant n\'est pas le produit d\'un traitement');

	}

	/**
	 * Après la fusion, « Modifier » propose sur la plantation le produit rangé en matériel, et
	 * l'enregistrement le garde.
	 */
	public function testUpdateKeepsMergedTool(): void {

		if($this->requireActions([ACTION_TRAITEMENT, ACTION_PLANTATION]) === FALSE) {
			return;
		}

		$amm = 'FM'.uniqid();

		$eToolProduct = $this->createTool(self::PHYTO, $amm);
		$eTool = $this->createTool(NULL);

		$eTask = $this->createTaskWith(ACTION_PLANTATION, tools: [$eTool['id']]);

		$eSequence = $this->getSequence();
		$eFlow = $this->createFlowWith($eSequence, ACTION_PLANTATION, tools: [$eTool['id']]);

		$this->changeRoutine($eTool, self::PHYTO, $amm);

		$this->assertEquals([[$eToolProduct['id']], []], $this->replayTask($eTask), 'l\'intervention garde le produit en matériel');
		$this->assertEquals([[$eToolProduct['id']], []], $this->replayFlow($eFlow), 'l\'itinéraire garde le produit en matériel');

	}

	/**
	 * Ce que l'élément porte en base n'ouvre rien à une création : seul le matériel de l'action y entre.
	 */
	public function testCreateFiltersTools(): void {

		if($this->requireActions([ACTION_TRAITEMENT, ACTION_PLANTATION]) === FALSE) {
			return;
		}

		$eToolProduct = $this->createTool(self::PHYTO);
		$eTool = $this->createTool(NULL);

		$eTask = new Task([
			'structure' => $this->eStructure,
			'action' => $this->getAction(ACTION_PLANTATION)
		]);

		$fw = new \FailWatch();

		$eTask->build(['tools'], ['tools' => [$eToolProduct['id'], $eTool['id']]]);

		$this->assertTrue($fw->ok());
		$this->assertEquals([$eTool['id']], $eTask['tools'], 'un produit n\'entre pas en matériel à la création');

	}

	/**
	 * Le formulaire « Modifier » passe d'une fertilisation à une plantation puis revient : la page
	 * retrouve en base ce que l'intervention porte. Une autre action, ou une création, part vide.
	 */
	public function testGetFieldsTask(): void {

		if($this->requireActions([ACTION_FERTILISATION, ACTION_PLANTATION]) === FALSE) {
			return;
		}

		$eToolFertilizer = $this->createTool(self::FERTILIZER);
		$eTool = $this->createTool(NULL);

		$eTask = $this->createTaskWith(ACTION_FERTILISATION, products: [$eToolFertilizer['id']], tools: [$eTool['id']]);

		$this->assertEquals(
			[[$eTool['id']], [$eToolFertilizer['id']]],
			$this->getFields('series/task:getFields', 'eTask', $eTask['id'], ACTION_FERTILISATION),
			'retour à l\'action d\'origine'
		);

		$this->assertEquals([[], []], $this->getFields('series/task:getFields', 'eTask', $eTask['id'], ACTION_PLANTATION), 'une autre action');
		$this->assertEquals([[], []], $this->getFields('series/task:getFields', 'eTask', NULL, ACTION_FERTILISATION), 'une création');

	}

	public function testGetFieldsFlow(): void {

		if($this->requireActions([ACTION_FERTILISATION, ACTION_PLANTATION]) === FALSE) {
			return;
		}

		$eToolFertilizer = $this->createTool(self::FERTILIZER);
		$eTool = $this->createTool(NULL);

		$eFlow = $this->createFlowWith($this->getSequence(), ACTION_FERTILISATION, products: [$eToolFertilizer['id']], tools: [$eTool['id']]);

		$this->assertEquals(
			[[$eTool['id']], [$eToolFertilizer['id']]],
			$this->getFields('sequence/flow:getFields', 'eFlow', $eFlow['id'], ACTION_FERTILISATION),
			'retour à l\'action d\'origine'
		);

		$this->assertEquals([[], []], $this->getFields('sequence/flow:getFields', 'eFlow', $eFlow['id'], ACTION_PLANTATION), 'une autre action');
		$this->assertEquals([[], []], $this->getFields('sequence/flow:getFields', 'eFlow', NULL, ACTION_FERTILISATION), 'une création');

	}

	private function requireActions(array $actions): bool {

		foreach($actions as $fqn) {
			if($this->requireAction($fqn) === FALSE) {
				return FALSE;
			}
		}

		return TRUE;

	}

	private function createTool(?string $routineName, ?string $amm = NULL): \work\Tool {

		$eTool = \work\ToolLib::getNewTool($this->eStructure, $routineName);

		// getNewTool() ne pose l'action que d'un produit, qui la tient de sa routine.
		$eTool->add([
			'action' => new \work\Action()
		]);

		$eTool->merge([
			'name' => 'Matériel '.uniqid(),
			'routineValue' => $this->getRoutineValue($routineName, $amm)
		]);

		\work\ToolLib::create($eTool);

		return $eTool;

	}

	private function getRoutineValue(?string $routineName, ?string $amm = NULL): ?array {

		return match($routineName) {
			self::FERTILIZER => ['k' => 1, 'n' => 1, 'p' => 1, 'mgo' => NULL],
			self::PHYTO => ['amm' => $amm, 'dose' => 1.0, 'unit' => 'kg/ha', 'water' => NULL, 'functions' => NULL, 'formulation' => NULL, 'organic' => FALSE, 'readyToUse' => FALSE],
			NULL => NULL
		};

	}

	/**
	 * Le geste de la page du matériel, qui passe par `ToolLib::update()` : la fusion quand l'AMM est
	 * celle d'un produit enregistré, le changement de routine sinon.
	 */
	private function changeRoutine(\work\Tool $eTool, string $routineName, ?string $amm = NULL): void {

		// Sans build(), qui exigerait l'action de la routine : le geste porté est le rangement qui suit.
		$eTool['routineName'] = $routineName;
		$eTool['routineValue'] = $this->getRoutineValue($routineName, $amm);

		\work\ToolLib::update($eTool, ['routineName', 'routineValue']);

	}

	private function createTaskWith(string $actionFqn, array $products = [], array $tools = []): Task {

		$eTask = $this->createTask(actionFqn: $actionFqn);

		$eTask['products'] = $products;
		$eTask['tools'] = $tools;

		// Sans build(), qui écarterait le matériel d'une autre action : le décor pose l'état qu'une fusion
		// ou un changement de routine trouve en base.
		TaskLib::update($eTask, ['products', 'tools']);

		return $eTask;

	}

	/**
	 * Une répétition hors série, sans intervention qui porte le matériel : c'est le cas que
	 * `ToolLib::delete()` ne regarde pas.
	 */
	private function createRepeatWith(string $actionFqn, array $tools): Repeat {

		$eTask = new Task([
			'structure' => $this->eStructure,
			'season' => $this->getSeason(),
			'series' => new Series(),
			'cultivation' => new Cultivation(),
			'plant' => $this->getPlant(),
			'variety' => new \plant\Variety(),
			'action' => $this->getAction($actionFqn),
			'category' => $this->getCategoryCulture(),
			'methods' => [],
			'tools' => $tools,
			'products' => [],
			'description' => NULL,
			'timeExpected' => NULL,
			'harvestSize' => new \plant\Size(),
			'fertilizer' => NULL,
			'status' => Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'repeatMaster' => new Repeat([
				'frequency' => Repeat::W1,
				'stop' => NULL
			])
		]);

		return RepeatLib::createFromTask($eTask);

	}

	private function createFlowWith(\sequence\Sequence $eSequence, string $actionFqn, array $products = [], array $tools = []): \sequence\Flow {

		$eFlow = $this->createFlow($eSequence, $this->getCrops($eSequence)->first(), $actionFqn);

		$eFlow['products'] = $products;
		$eFlow['tools'] = $tools;

		// Sans build(), qui écarterait le matériel d'une autre action : le décor pose l'état qu'une fusion
		// ou un changement de routine trouve en base.
		\sequence\FlowLib::update($eFlow, ['products', 'tools']);

		return $eFlow;

	}

	private function read(\ModuleModel $mElement, \Element $e): array {

		return array_values(
			$mElement
				->select(['tools', 'products'])
				->whereId($e)
				->get()
				->extracts(['tools', 'products'])
		);

	}

	private function countCiting(\work\Tool $eTool): int {

		$where = 'JSON_CONTAINS(tools, \''.$eTool['id'].'\') OR JSON_CONTAINS(products, \''.$eTool['id'].'\')';

		return (
			Task::model()->whereStructure($this->eStructure)->where($where)->count() +
			Repeat::model()->whereStructure($this->eStructure)->where($where)->count() +
			\sequence\Flow::model()->whereStructure($this->eStructure)->where($where)->count()
		);

	}

	/**
	 * `cTool?` lit le matériel de la structure par `ToolLib::deferred()`, mémoïsé pour le processus :
	 * le test l'a rempli avant de poser le sien.
	 */
	private function forgetTools(): void {
		new \ReflectionProperty(\work\ToolCrud::class, 'cache')->setValue(NULL, []);
		new \ReflectionProperty(\work\MethodCrud::class, 'cache')->setValue(NULL, []);
	}

	/**
	 * Rejoue « Modifier » puis « Enregistrer » sans rien toucher, comme `update()` de `task.p.php`.
	 */
	private function replayTask(Task $eTask): array {

		$e = Task::model()
			->select(Task::getSelection())
			->whereId($eTask)
			->get();

		$e['hasTools'] = \work\ToolLib::getForWork($e['structure'], $e['action'], $e['tools']);

		$this->assertEquals($e['tools'], $e['hasTools']->getIds(), 'le champ Matériel montre ce que l\'intervention porte');

		$this->forgetTools();

		$fw = new \FailWatch();

		$e->build(['action', 'products', 'tools'], ['action' => $e['action']['id'], 'tools' => $e['cTool?']()->getIds()]);

		$this->assertTrue($fw->ok(), 'l\'enregistrement est accepté');

		TaskLib::update($e, ['action', 'products', 'tools']);

		return $this->read(Task::model(), $eTask);

	}

	private function replayFlow(\sequence\Flow $eFlow): array {

		$e = \sequence\Flow::model()
			->select(\sequence\Flow::getSelection())
			->whereId($eFlow)
			->get();

		$e['sequence'] = \sequence\SequenceLib::getById($e['sequence']);

		$e['hasTools'] = \work\ToolLib::getForWork($e['structure'], $e['action'], $e['tools']);

		$this->assertEquals($e['tools'], $e['hasTools']->getIds(), 'le champ Matériel montre ce que l\'itinéraire porte');

		$this->forgetTools();

		$fw = new \FailWatch();

		$e->build(['action', 'products', 'tools'], ['action' => $e['action']['id'], 'tools' => $e['cTool?']()->getIds()]);

		$this->assertTrue($fw->ok(), 'l\'enregistrement est accepté');

		\sequence\FlowLib::update($e, ['action', 'products', 'tools']);

		return $this->read(\sequence\Flow::model(), $eFlow);

	}

	/**
	 * Exécute la vraie page et rend le matériel et les produits que ses champs montreraient. Forme
	 * reprise de `dashboard/test/Preference.t.php`.
	 */
	private function getFields(string $page, string $key, ?int $id, string $actionFqn): array {

		$this->forgetTools();

		$post = [
			'structure' => $this->eStructure['id'],
			'action' => $this->getAction($actionFqn)['id']
		];

		if($id !== NULL) {
			$post['id'] = $id;
		}

		$action = $this->runPage($page, $post);

		if($this->assertTrue($action instanceof \ViewAction, 'la page rend ses champs : '.get_class($action)) === FALSE) {
			return [];
		}

		$e = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->$key;

		return [$e['cTool?']()->getIds(), $e['cProduct?']()->getIds()];

	}

	private function runPage(string $page, array $post): \Throwable {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'POST');

		$getPages = new \ReflectionMethod(\Page::class, 'getPages');

		$_POST = $post;
		$_REQUEST = $_POST;

		try {
			\Page::execute($getPages->invoke(NULL, $page), new \stdClass());
		} catch(\Throwable $e) {
			return $e;
		} finally {
			$_POST = [];
			$_REQUEST = [];
			$method->setValue(NULL, $methodBefore);
		}

		throw new \Exception('La page n\'a rien levé');

	}

}
?>
