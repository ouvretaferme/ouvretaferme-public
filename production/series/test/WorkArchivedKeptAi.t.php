<?php
namespace series;

/**
 * « Modifier » une intervention ou une intervention d'itinéraire, puis enregistrer sans rien toucher,
 * garde les intrants, le produit et le matériel qu'elle porte, même quand la structure a archivé ou
 * supprimé tout ce que ces champs proposeraient (lot FG du chantier planning, R-Retours-1210).
 *
 * Le formulaire ne rend le champ Intrants ou Produit que si `hasProducts` a au moins une ligne
 * (`TaskUi::getProductsField()`, `FlowUi::getProductsField()`), et le champ Matériel que si `hasTools`
 * en a une (`getToolsGroup()`). Un champ absent ne poste rien, et `build()` vide alors la valeur
 * portée. Chaque cas rejoue donc le geste comme la page : il prépare `hasProducts` et `hasTools`
 * comme l'`update()` de `task.p.php` ou de `flow.p.php`, poste ce que les champs rendus posteraient,
 * construit, écrit par le Lib, puis relit la ligne en base.
 *
 * Isolation : la transaction du décor (`SeriesFixtureAiTest`), annulée en tearDown(), emporte aussi
 * les matériels archivés ou supprimés par les cas.
 *
 * Lancement : dev/test package=series file=WorkArchivedKeptAi
 */
class WorkArchivedKeptAiTest extends SeriesFixtureAiTest {

	const string FERTILIZER = 'fertilizer';
	const string PHYTO = 'phyto';

	/**
	 * Un engrais encore actif : le témoin, qui passe avant comme après le correctif.
	 */
	public function testFertilizerActive(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTool = $this->createTool(self::FERTILIZER);
		$eTask = $this->createTaskWith(ACTION_FERTILISATION, products: [$eTool['id']]);

		$this->assertEquals([$eTool['id']], $this->replayTask($eTask)['products']);

	}

	public function testFertilizerInactive(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTool = $this->createTool(self::FERTILIZER);
		$eTask = $this->createTaskWith(ACTION_FERTILISATION, products: [$eTool['id']]);

		$this->archive(\work\ToolLib::getProductsForWork($this->eStructure, $this->getAction(ACTION_FERTILISATION)), \work\Tool::INACTIVE);

		$this->assertEquals([$eTool['id']], $this->replayTask($eTask)['products'], 'un engrais archivé reste sur la fertilisation');

	}

	/**
	 * L'état réel des engrais portés qu'on retire : `ToolLib::delete()` passe en DELETED un matériel
	 * qu'une intervention cite.
	 */
	public function testFertilizerDeleted(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTool = $this->createTool(self::FERTILIZER);
		$eTask = $this->createTaskWith(ACTION_FERTILISATION, products: [$eTool['id']]);

		$this->archive(\work\ToolLib::getProductsForWork($this->eStructure, $this->getAction(ACTION_FERTILISATION)), \work\Tool::DELETED);

		$this->assertEquals(\work\Tool::DELETED, \work\Tool::model()->select('status')->whereId($eTool)->getValue('status'), 'le décor : l\'engrais porté est passé en DELETED');
		$this->assertEquals([$eTool['id']], $this->replayTask($eTask)['products'], 'un engrais supprimé reste sur la fertilisation');

	}

	public function testToolsDeleted(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTool = $this->createTool(NULL);
		$eTask = $this->createTaskWith(ACTION_FERTILISATION, tools: [$eTool['id']]);

		$this->archive(\work\ToolLib::getForWork($this->eStructure, $this->getAction(ACTION_FERTILISATION)), \work\Tool::DELETED);

		$this->assertEquals([$eTool['id']], $this->replayTask($eTask)['tools'], 'un matériel supprimé reste sur l\'intervention');

	}

	/**
	 * Le Produit d'une intervention d'itinéraire de traitement, quand plus aucun produit de
	 * traitement n'est actif.
	 */
	public function testFlowPhytoDeleted(): void {

		if($this->requireAction(ACTION_TRAITEMENT) === FALSE) {
			return;
		}

		$eTool = $this->createTool(self::PHYTO);

		$eSequence = $this->getSequence();
		$eFlow = $this->createFlow($eSequence, $this->getCrops($eSequence)->first(), ACTION_TRAITEMENT);

		$eFlow['products'] = [$eTool['id']];

		// Sans build(), qui garderait le même produit : le décor pose ce que le formulaire aurait posté.
		\sequence\FlowLib::update($eFlow, ['products']);

		$this->archive(\work\ToolLib::getProductsForWork($this->eStructure, $this->getAction(ACTION_TRAITEMENT)), \work\Tool::DELETED);

		$this->assertEquals([$eTool['id']], $this->replayFlow($eFlow)['products'], 'un produit supprimé reste sur l\'intervention d\'itinéraire');

	}

	/**
	 * Un matériel de la structure : un intrant ou un produit de traitement selon sa routine, un
	 * matériel ordinaire sans routine.
	 */
	private function createTool(?string $routineName): \work\Tool {

		$eTool = \work\ToolLib::getNewTool($this->eStructure, $routineName);

		// getNewTool() ne pose l'action que d'un produit, qui la tient de sa routine.
		$eTool->add([
			'action' => new \work\Action()
		]);

		$eTool->merge([
			'name' => 'Matériel '.uniqid(),
			'routineValue' => match($routineName) {
				self::FERTILIZER => ['k' => 1, 'n' => 1, 'p' => 1, 'mgo' => NULL],
				self::PHYTO => ['amm' => NULL, 'dose' => 1.0, 'unit' => 'kg/ha', 'water' => NULL, 'functions' => NULL, 'formulation' => NULL, 'organic' => FALSE, 'readyToUse' => FALSE],
				NULL => NULL
			}
		]);

		\work\ToolLib::create($eTool);

		return $eTool;

	}

	private function createTaskWith(string $actionFqn, array $products = [], array $tools = []): Task {

		$eTask = $this->createTask(actionFqn: $actionFqn);

		$eTask['products'] = $products;
		$eTask['tools'] = $tools;

		// Sans build(), qui garderait les mêmes valeurs tant que le matériel est actif : le décor pose ce
		// que le formulaire aurait posté.
		TaskLib::update($eTask, ['products', 'tools']);

		return $eTask;

	}

	/**
	 * Retire tout ce qu'un champ proposerait, pour que la page n'ait plus rien d'actif à y mettre.
	 */
	private function archive(\Collection $cTool, string $status): void {

		$cTool = \work\Tool::model()
			->select(\work\Tool::getSelection())
			->whereId('IN', $cTool->getIds())
			->getCollection();

		foreach($cTool as $eTool) {

			if($status === \work\Tool::DELETED) {
				\work\ToolLib::delete($eTool);
			} else {
				$eTool['status'] = $status;
				\work\ToolLib::update($eTool, ['status']);
			}

		}

	}

	/**
	 * Ce que posteraient les champs Intrants ou Produit et Matériel : leur valeur s'ils sont rendus,
	 * rien sinon.
	 */
	private function getInput(\sequence\Flow|Task $e): array {

		$this->forgetTools();

		$input = ['action' => $e['action']['id']];

		if($e['hasProducts']->notEmpty()) {
			$input['products'] = $e['cProduct?']()->getIds();
		}

		if($e['hasTools']->notEmpty()) {
			$input['tools'] = $e['cTool?']()->getIds();
		}

		return $input;

	}

	/**
	 * `cProduct?` et `cTool?` lisent le matériel de la structure par `ToolLib::deferred()`, mémoïsé pour
	 * le processus : une requête du produit le remplit après que le matériel existe, le test l'a rempli
	 * avant de poser le sien.
	 */
	private function forgetTools(): void {
		new \ReflectionProperty(\work\ToolCrud::class, 'cache')->setValue(NULL, []);
	}

	private function replayTask(Task $eTask): Task {

		$e = Task::model()
			->select(Task::getSelection())
			->whereId($eTask)
			->get();

		$e['hasTools'] = \work\ToolLib::getForWork($e['structure'], $e['action'], $e['tools']);
		$e['hasProducts'] = \work\ToolLib::getProductsForWork($e['structure'], $e['action'], $e['products']);

		$fw = new \FailWatch();

		$e->build(['action', 'products', 'tools'], $this->getInput($e));

		$this->assertTrue($fw->ok(), 'l\'enregistrement est accepté');

		TaskLib::update($e, ['action', 'products', 'tools']);

		return Task::model()
			->select(['products', 'tools'])
			->whereId($eTask)
			->get();

	}

	private function replayFlow(\sequence\Flow $eFlow): \sequence\Flow {

		$e = \sequence\Flow::model()
			->select(\sequence\Flow::getSelection())
			->whereId($eFlow)
			->get();

		$e['sequence'] = \sequence\SequenceLib::getById($e['sequence']);

		$e['hasTools'] = \work\ToolLib::getForWork($e['sequence']['structure'], $e['action'], $e['tools']);
		$e['hasProducts'] = \work\ToolLib::getProductsForWork($e['sequence']['structure'], $e['action'], $e['products']);

		$fw = new \FailWatch();

		$e->build(['action', 'products', 'tools'], $this->getInput($e));

		$this->assertTrue($fw->ok(), 'l\'enregistrement est accepté');

		\sequence\FlowLib::update($e, ['action', 'products', 'tools']);

		return \sequence\Flow::model()
			->select(['products', 'tools'])
			->whereId($eFlow)
			->get();

	}

}
?>
