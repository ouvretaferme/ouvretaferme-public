<?php
namespace toolbox;

/**
 * Le carnet de traitement (lot L4' de production-finir, planche pf-l4-v3) : chaque traitement est
 * rangé par année civile et par mois avec la quantité que sa dose donne sur la surface de sa série,
 * dans l'unité de sa dose, et le matériel noté sur les traitements sans produit est proposé à la reprise tant que
 * la question n'a pas reçu « Non ». Le matériel qui devient un produit passe dans les produits des
 * interventions qui le portaient.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(). Les matériels se créent sur une
 * structure qui porte l'action de traitement.
 *
 * Lancement : dev/test package=toolbox file=Treatment
 */
class TreatmentTest extends \Test {

	public function setUp(): void {
		\work\Tool::model()->beginTransaction();
	}

	public function tearDown(): void {
		\work\Tool::model()->rollBack();
	}

	private static function getProduct(int $id, float $dose): \work\Tool {

		return new \work\Tool([
			'id' => $id,
			'name' => 'Produit '.$id,
			'routineName' => 'phyto',
			'routineValue' => ['amm' => NULL, 'dose' => $dose, 'unit' => 'kg/ha', 'water' => NULL, 'functions' => [], 'formulation' => NULL, 'organic' => FALSE, 'readyToUse' => FALSE]
		]);

	}

	private static function getTask(int $id, array $products, ?int $area, ?array $treatment = NULL, ?string $doneDate = '2026-05-12', array $tools = [], ?int $areaTarget = NULL): \series\Task {

		return new \series\Task([
			'id' => $id,
			'tools' => $tools,
			'products' => $products,
			'treatment' => $treatment,
			'doneDate' => $doneDate,
			'doneWeek' => '2026-W20',
			'series' => ($area === NULL and $areaTarget === NULL) ? new \series\Series() : new \series\Series(['id' => $id, 'area' => $area, 'areaTarget' => $areaTarget])
		]);

	}

	public function testFill(): void {

		$cToolPhyto = new \Collection([
			10 => self::getProduct(10, 2.5),
			11 => self::getProduct(11, 1.0),
			12 => self::getProduct(12, 4.0),
		]);

		$cTask = new \Collection([
			1 => self::getTask(1, [10], 2000, tools: [99]),
			2 => self::getTask(2, [10], 400, ['dose' => 5.0, 'unit' => 'kg/ha', 'water' => NULL], doneDate: NULL),
			3 => self::getTask(3, [11], NULL),
			4 => self::getTask(4, [], 1000, doneDate: NULL, tools: [99]),
			5 => self::getTask(5, [10], 1000, ['dose' => 3.0, 'unit' => 'L/ha', 'water' => NULL]),
			6 => self::getTask(6, [10], NULL, ['dose' => 5.0, 'unit' => 'kg/ha', 'water' => NULL], areaTarget: 300),
		]);

		$fill = TreatmentLib::fill($cTask, $cToolPhyto);

		$this->assertEquals(10, $cTask[1]['eToolPhyto']['id']);
		$this->assertEquals([NULL, 'kg/ha'], [$cTask[1]['quantity'], $cTask[1]['unit']], 'sans dose, pas de quantité : celle du produit ne s\'emprunte pas');
		$this->assertEquals(0.2, $cTask[2]['quantity'], 'surface × dose de l\'intervention');
		$this->assertEquals([0.3, 'L/ha'], [$cTask[5]['quantity'], $cTask[5]['unit']], 'dans l\'unité de l\'intervention');
		$this->assertEquals(NULL, $cTask[3]['quantity'], 'hors série, pas de surface donc pas de quantité');
		$this->assertEquals(NULL, $cTask[3]['area']);
		$this->assertEquals([300, TRUE, 0.15], [$cTask[6]['area'], $cTask[6]['areaPlanned'], $cTask[6]['quantity']], 'série pas encore placée : sa surface prévue, pour un ordre de grandeur');
		$this->assertFalse($cTask[2]['areaPlanned']);

		$this->assertEquals([4], $fill['cTaskWithout']->getIds(), 'du matériel seul ne fait pas un produit');

	}

	/**
	 * Le filtre « Produit à renseigner » : les mois ne rangent que les traitements sans produit, et
	 * les deux comptes des chiffres restent ceux de l'année.
	 */
	public function testFillWithout(): void {

		$cToolPhyto = new \Collection([
			10 => self::getProduct(10, 2.5),
		]);

		$cTask = new \Collection([
			1 => self::getTask(1, [10], 1000, ['dose' => 2.5, 'unit' => 'kg/ha', 'water' => NULL]),
			2 => self::getTask(2, [], 1000, doneDate: '2026-04-02'),
			3 => self::getTask(3, [], NULL),
		]);

		$fill = TreatmentLib::fill($cTask, $cToolPhyto, without: TRUE);

		$this->assertEquals(['2026-05', '2026-04'], array_keys($fill['tasksByMonth']));
		$this->assertEquals([3], $fill['tasksByMonth']['2026-05']->getIds(), 'le traitement avec produit du même mois ne se range pas');
		$this->assertEquals([2, 3], $fill['cTaskWithout']->getIds());
		$this->assertEquals(0.25, $cTask[1]['quantity'], 'un traitement écarté des mois garde sa quantité');

		$fill = TreatmentLib::fill($cTask, $cToolPhyto);

		$this->assertEquals([1, 3], $fill['tasksByMonth']['2026-05']->getIds(), 'sans le filtre, tous les traitements');

	}

	public function testFillByMonth(): void {

		$eTaskWeek = self::getTask(2, [], NULL, doneDate: NULL);
		$eTaskWeek['doneWeek'] = '2026-W01';

		$eTaskDay = self::getTask(1, [], NULL, doneDate: '2025-12-30');
		$eTaskDay['doneWeek'] = '2026-W01';

		$cTask = new \Collection([
			3 => self::getTask(3, [], NULL),
			2 => $eTaskWeek,
			1 => $eTaskDay,
		]);

		$tasksByMonth = TreatmentLib::fill($cTask, new \Collection())['tasksByMonth'];

		$this->assertEquals(['2026-05', '2026-01', '2025-12'], array_keys($tasksByMonth), 'du plus récent au plus ancien');
		$this->assertEquals([1], $tasksByMonth['2025-12']->getIds(), 'un jour exact dans son mois');
		$this->assertEquals([2], $tasksByMonth['2026-01']->getIds(), 'une semaine dans le mois de son jeudi, le 1er janvier, et non de son lundi, le 29 décembre, qui est de l\'année d\'avant');

	}

	/**
	 * Le registre en PDF : du plus ancien au plus récent, comme il se lit. `getTasks()` rend le carnet
	 * dans l'ordre inverse, par semaine puis par jour, un traitement sans jour après les jours de sa
	 * semaine : le registre le porte donc avant eux, dans le mois de son jeudi (2026-W20, le 14 mai).
	 */
	public function testRegister(): void {

		$cTask = new \Collection([
			3 => self::getTask(3, [], NULL, doneDate: '2026-09-02'),
			1 => self::getTask(1, [], NULL, doneDate: '2026-05-12'),
			4 => self::getTask(4, [], NULL, doneDate: '2026-05-11'),
			2 => self::getTask(2, [], NULL, doneDate: NULL),
		]);

		$tasksByMonth = TreatmentLib::getRegister($cTask);

		$this->assertEquals(['2026-05', '2026-09'], array_keys($tasksByMonth), 'du plus ancien au plus récent');
		$this->assertEquals([2, 4, 1], $tasksByMonth['2026-05']->getIds(), 'un traitement noté à la semaine entre au registre, avant les jours de sa semaine');
		$this->assertEquals([3], $tasksByMonth['2026-09']->getIds());

	}

	/**
	 * Le CSV porte tous les traitements, avec ou sans jour. La surface et la quantité d'une série pas
	 * encore placée restent vides : un tableur ne montrerait pas qu'elles ne sont qu'un ordre de grandeur.
	 */
	public function testExport(): void {

		$eToolPhyto = self::getProduct(10, 2.0);
		$eToolPhyto['routineValue'] = ['amm' => '2100030', 'water' => 200] + $eToolPhyto['routineValue'];

		$cTask = new \Collection([
			2 => self::getTask(2, [10], NULL, ['dose' => 2.0, 'unit' => 'kg/ha', 'water' => 300], doneDate: NULL, areaTarget: 500),
			1 => self::getTask(1, [10], 1000, ['dose' => 2.0, 'unit' => 'kg/ha', 'water' => 300]),
			3 => self::getTask(3, [], NULL),
		]);

		foreach($cTask as $eTask) {
			$eTask['plant'] = new \plant\Plant();
			$eTask['description'] = NULL;
			$eTask['cTool'] = new \Collection();
			if($eTask['series']->notEmpty()) {
				$eTask['series']['name'] = 'Série '.$eTask['id'];
				$eTask['series']['use'] = \series\Series::BED;
				$eTask['series']['cccPlace'] = new \Collection();
			}
		}

		$cTask[1]['description'] = 'Contre le mildiou';
		$cTask[1]['cTool'] = new \Collection([new \work\Tool(['id' => 99, 'name' => 'Pulvérisateur'])]);

		TreatmentLib::fill($cTask, new \Collection([10 => $eToolPhyto]));

		$export = TreatmentLib::getExport($cTask);

		$this->assertEquals(['', 'Série 1', 'Série 2'], array_column($export, 2), 'tous les traitements, avec ou sans jour, dans l\'ordre inverse du carnet');
		$this->assertEquals(['2026-05-12', '2026-W20', 'Série 1', '', '1000', 'Produit 10', '2100030', '2', 'kg/ha', '300', '0,2', 'Pulvérisateur', 'Contre le mildiou'], $export[1]);
		$this->assertEquals(['', 'Série 2'], [$export[2][0], $export[2][2]], 'un traitement noté à la semaine reste dans le CSV');
		$this->assertEquals([NULL, NULL], [$export[2][4], $export[2][10]], 'surface prévue : ni surface ni quantité');
		$this->assertEquals(['', '', '', NULL, '', NULL], [$export[0][2], $export[0][5], $export[0][6], $export[0][7], $export[0][8], $export[0][9]], 'hors série et sans produit : ni culture, ni dose, ni bouillie');

	}

	public function testDone(): void {

		$this->assertEquals('12 mai', TreatmentUi::getDone(new \series\Task(['doneDate' => '2026-05-12', 'doneWeek' => '2026-W20'])));
		$this->assertEquals('23 décembre', TreatmentUi::getDone(new \series\Task(['doneDate' => '2025-12-23', 'doneWeek' => '2025-W52'])), 'l\'onglet dit l\'année');
		$this->assertEquals('semaine 20', TreatmentUi::getDone(new \series\Task(['doneDate' => NULL, 'doneWeek' => '2026-W20'])));

	}

	public function testSynchronizeProduct(): void {

		$eTask = \series\Task::model()
			->select(['id', 'structure', 'tools', 'products'])
			->whereStatus(\series\Task::DONE)
			->whereAction('IN', \work\Action::model()->select('id')->whereFqn(ACTION_TRAITEMENT)->getSql())
			->where('JSON_LENGTH(tools) = 0')
			->sort(['id' => SORT_DESC])
			->get();

		if($this->assertDecorPresent($eTask->notEmpty(), 'un traitement fait, sans matériel') === FALSE) {
			return;
		}

		$eAction = \work\ActionLib::getByStructure($eTask['structure'], fqn: ACTION_TRAITEMENT);

		$eTool = $this->createTool($eTask['structure'], $eAction, 'Devient un produit');

		\series\Task::model()->update($eTask, ['tools' => [$eTool['id']], 'products' => []]);

		$eTool['routineName'] = 'phyto';
		\series\TaskLib::synchronizeProduct($eTool);

		$this->assertEquals([[], [$eTool['id']]], $this->getColumns($eTask), 'du matériel aux produits');

		\series\TaskLib::synchronizeProduct($eTool);

		$this->assertEquals([[], [$eTool['id']]], $this->getColumns($eTask), 'un second passage ne change rien');

		$eTool['routineName'] = NULL;
		\series\TaskLib::synchronizeProduct($eTool);

		$this->assertEquals([[$eTool['id']], []], $this->getColumns($eTask), 'et retour');

	}

	/**
	 * Un produit de traitement n'a de place dans les produits que sur un traitement : ailleurs, il
	 * reste du matériel.
	 */
	public function testSynchronizeProductOutsideTreatment(): void {

		$eTask = \series\Task::model()
			->select(['id', 'structure', 'tools', 'products'])
			->whereStatus(\series\Task::DONE)
			->whereAction('NOT IN', \work\Action::model()->select('id')->whereFqn(ACTION_TRAITEMENT)->getSql())
			->where('JSON_LENGTH(tools) = 0')
			->where('JSON_LENGTH(products) = 0')
			->sort(['id' => SORT_DESC])
			->get();

		if($this->assertDecorPresent($eTask->notEmpty(), 'une intervention faite hors traitement, sans matériel ni produit') === FALSE) {
			return;
		}

		$eAction = \work\ActionLib::getByStructure($eTask['structure'], fqn: ACTION_TRAITEMENT);

		$eTool = $this->createTool($eTask['structure'], $eAction, 'Reste du matériel');

		// Sans build() : le matériel de traitement posé sur une autre intervention est l'état que la
		// fusion et l'ancien rangement laissent, et que le formulaire de cette action refuserait.
		\series\Task::model()->update($eTask, ['tools' => [$eTool['id']], 'products' => []]);

		$eTool['routineName'] = 'phyto';
		\series\TaskLib::synchronizeProduct($eTool);

		$this->assertEquals([[$eTool['id']], []], $this->getColumns($eTask), 'hors traitement, le produit reste du matériel');

	}

	public function testProductChangeKeepsTask(): void {

		$eTask = \series\Task::model()
			->select(['id', 'structure', 'action'])
			->whereStatus(\series\Task::DONE)
			->whereAction('IN', \work\Action::model()->select('id')->whereFqn(ACTION_TRAITEMENT)->getSql())
			->where('JSON_LENGTH(products) = 0')
			->where('series IS NOT NULL')
			->sort(['id' => SORT_DESC])
			->get();

		$eTool = $this->createTool($eTask['structure'], $eTask['action'], 'Change de dose');

		\series\Task::model()->update($eTask, ['tools' => [$eTool['id']], 'products' => [], 'treatment' => NULL]);

		$eTool['routineName'] = 'phyto';
		$eTool['routineValue'] = ['amm' => NULL, 'dose' => 2.0, 'unit' => 'kg/ha', 'water' => 300, 'functions' => [], 'formulation' => NULL, 'organic' => FALSE, 'readyToUse' => FALSE];
		\work\ToolLib::update($eTool, ['routineName', 'routineValue']);

		$treatment = ['dose' => 2.0, 'unit' => 'kg/ha', 'water' => 300];

		$this->assertEquals($treatment, $this->getTreatment($eTask), 'le matériel devenu produit donne sa dose au traitement qui n\'en portait pas');

		$eTool['routineValue'] = ['dose' => 5.0, 'unit' => 'L/ha', 'water' => 800] + $eTool['routineValue'];
		\work\ToolLib::update($eTool, ['routineValue']);

		$this->assertEquals($treatment, $this->getTreatment($eTask), 'la dose du produit change, pas celle de l\'intervention');

		$cTask = \series\Task::model()
			->select(['id', 'products', 'treatment', 'doneDate', 'doneWeek', 'series' => ['area', 'areaTarget']])
			->whereId($eTask['id'])
			->getCollection();

		TreatmentLib::fill($cTask, new \Collection([$eTool['id'] => $eTool]));

		$eTaskFilled = $cTask->first();

		$this->assertEquals('kg/ha', $eTaskFilled['unit'], 'le carnet garde l\'unité de l\'intervention');
		$this->assertEquals($eTaskFilled['series']['area'] * 2.0 / 10000, $eTaskFilled['quantity'], 'et sa dose');

	}

	private function getTreatment(\series\Task $eTask): ?array {

		return \series\Task::model()
			->select(['treatment'])
			->whereId($eTask['id'])
			->get()['treatment'];

	}

	private function getColumns(\series\Task $eTask): array {

		$eTaskRead = \series\Task::model()
			->select(['tools', 'products'])
			->whereId($eTask['id'])
			->get();

		return [$eTaskRead['tools'], $eTaskRead['products']];

	}

	public function testToolReprise(): void {

		$eAction = \work\Action::model()
			->select(['id', 'structure'])
			->whereFqn(ACTION_TRAITEMENT)
			->where('m1.structure IS NOT NULL')
			->sort(['id' => SORT_ASC])
			->get();

		$eStructure = \structure\StructureLib::getById($eAction['structure']['id']);

		$eToolOnce = $this->createTool($eStructure, $eAction, 'Reprise une fois');
		$eToolTwice = $this->createTool($eStructure, $eAction, 'Reprise deux fois');
		$eToolOther = $this->createTool($eStructure, new \work\Action(), 'Reprise sans action');

		$cTaskWithout = new \Collection([
			1 => self::getTask(1, [], 100, tools: [$eToolOnce['id'], $eToolTwice['id']]),
			2 => self::getTask(2, [], 100, tools: [$eToolTwice['id'], $eToolOther['id']]),
			3 => self::getTask(3, [], 100, tools: [$eToolOther['id']]),
		]);

		$eToolReprise = TreatmentLib::getToolReprise($eStructure, $eAction, $cTaskWithout);

		$this->assertEquals($eToolTwice['id'], $eToolReprise['id'], 'le matériel le plus noté, et seulement s\'il est lié au traitement');
		$this->assertEquals(2, $eToolReprise['tasks']);

		// « Non » répondu : la question passe au suivant.
		$eToolTwice['phytoRefused'] = TRUE;
		\work\ToolLib::update($eToolTwice, ['phytoRefused']);

		$this->assertEquals($eToolOnce['id'], TreatmentLib::getToolReprise($eStructure, $eAction, $cTaskWithout)['id']);

		$eToolOnce['phytoRefused'] = TRUE;
		\work\ToolLib::update($eToolOnce, ['phytoRefused']);

		$this->assertTrue(TreatmentLib::getToolReprise($eStructure, $eAction, $cTaskWithout)->empty());
		$this->assertTrue(TreatmentLib::getToolReprise($eStructure, $eAction, new \Collection())->empty());

	}

	/**
	 * Un traitement fait sur une série, pris dans la base : sa structure porte l'action de traitement.
	 */
	private static function getDoneTreatment(): \series\Task {

		return \series\Task::model()
			->select(['id', 'structure', 'action', 'season', 'plant', 'doneWeek', 'doneDate'])
			->whereStatus(\series\Task::DONE)
			->whereAction('IN', \work\Action::model()->select('id')->whereFqn(ACTION_TRAITEMENT)->getSql())
			->where('series IS NOT NULL')
			->where('plant IS NOT NULL')
			->sort(['id' => SORT_DESC])
			->get();

	}

	public function testGetTasksSearch(): void {

		$eTask = self::getDoneTreatment();
		$eStructure = \structure\StructureLib::getById($eTask['structure']['id']);
		$eAction = TreatmentLib::getAction($eStructure);

		// Le carnet se découpe par année civile : une série de la saison 2027 se traite dès l'automne 2026.
		$year = (int)substr($eTask['doneDate'] ?? $eTask['doneWeek'], 0, 4);

		$eTool = $this->createTool($eStructure, $eAction, 'Filtre');
		\series\Task::model()->update($eTask, ['products' => [$eTool['id']]]);

		$cTask = TreatmentLib::getTasks($eStructure, $eAction, $year, new \Search(['tool' => $eTool]));
		$this->assertEquals([$eTask['id']], $cTask->getIds(), 'le filtre produit ne garde que ses traitements');

		$cTask = TreatmentLib::getTasks($eStructure, $eAction, $year, new \Search(['plant' => $eTask['plant']]));
		$this->assertTrue($cTask->offsetExists($eTask['id']));
		$this->assertEquals([$eTask['plant']['id']], array_values(array_unique($cTask->getColumnCollection('plant')->getIds())), 'le filtre espèce ne garde que son espèce');

		$this->assertTrue(TreatmentLib::getTasks($eStructure, $eAction, $year)->count() >= $cTask->count(), 'sans filtre, rien ne manque');

	}

	/**
	 * Le carnet se découpe par année civile, jamais par la saison de la série (retour du
	 * 27/09) : un jour exact se range par son année, une semaine par son année ISO, avec ou sans série.
	 */
	public function testGetTasksByYear(): void {

		$eTask = self::getDoneTreatment();
		$eStructure = \structure\StructureLib::getById($eTask['structure']['id']);
		$eAction = TreatmentLib::getAction($eStructure);
		$season = $eTask['season'];

		\series\Task::model()->update($eTask, ['doneDate' => ($season - 1).'-12-30', 'doneWeek' => $season.'-W01']);

		$this->assertTrue(TreatmentLib::getTasks($eStructure, $eAction, $season - 1)->offsetExists($eTask['id']), 'un jour de décembre dans son année, pas dans la saison de sa série');
		$this->assertFalse(TreatmentLib::getTasks($eStructure, $eAction, $season)->offsetExists($eTask['id']));
		$this->assertTrue(in_array($season - 1, TreatmentLib::getYears($eStructure, $eAction), TRUE), 'son année a un onglet');

		\series\Task::model()->update($eTask, ['doneDate' => NULL]);

		$this->assertTrue(TreatmentLib::getTasks($eStructure, $eAction, $season)->offsetExists($eTask['id']), 'noté à la semaine, dans l\'année ISO de sa semaine');
		$this->assertFalse(TreatmentLib::getTasks($eStructure, $eAction, $season - 1)->offsetExists($eTask['id']));

		\series\Task::model()->update($eTask, ['series' => NULL, 'cultivation' => NULL, 'season' => NULL, 'doneWeek' => ($season - 1).'-W10']);

		$this->assertTrue(TreatmentLib::getTasks($eStructure, $eAction, $season - 1)->offsetExists($eTask['id']), 'hors série, rangé par l\'année de sa semaine');
		$this->assertFalse(TreatmentLib::getTasks($eStructure, $eAction, $season)->offsetExists($eTask['id']));

	}

	public function testGetYears(): void {

		$eTask = self::getDoneTreatment();
		$eStructure = \structure\StructureLib::getById($eTask['structure']['id']);
		$eAction = TreatmentLib::getAction($eStructure);

		$years = TreatmentLib::getYears($eStructure, $eAction);

		$this->assertTrue(in_array(\Clock::year(), $years, TRUE), 'l\'année en cours, même sans traitement');
		$this->assertEquals($years, array_values(array_unique($years)));
		$this->assertEquals([\Clock::year()], TreatmentLib::getYears($eStructure, new \work\Action()), 'sans action, l\'année en cours seule');

		$sorted = $years;
		rsort($sorted);
		$this->assertEquals($sorted, $years, 'la plus récente en tête');

	}

	public function testGetProductTask(): void {

		$eTask = self::getDoneTreatment();
		$eStructure = \structure\StructureLib::getById($eTask['structure']['id']);
		$eAction = TreatmentLib::getAction($eStructure);

		$cTaskOne = TreatmentLib::getTasksByIds($eStructure, $eAction, [$eTask['id']]);
		$eTaskOne = TreatmentLib::getProductTask($eStructure, $eAction, $cTaskOne);

		$this->assertEquals($eTask['id'], $eTaskOne['id'], 'un seul traitement : le panneau le reprend tel qu\'il est');

		$eTaskOther = \series\Task::model()
			->select(['id'])
			->whereStructure($eStructure)
			->whereAction($eAction)
			->whereId('!=', $eTask['id'])
			->get();

		$cTaskMany = TreatmentLib::getTasksByIds($eStructure, $eAction, [$eTask['id'], $eTaskOther['id']]);
		$eTaskMany = TreatmentLib::getProductTask($eStructure, $eAction, $cTaskMany);

		$this->assertEquals(2, $cTaskMany->count());
		$this->assertTrue($eTaskMany->offsetExists('id') === FALSE, 'plusieurs traitements : un panneau vierge');
		$this->assertEquals([], $eTaskMany['products']);

		$this->assertTrue(TreatmentLib::getTasksByIds($eStructure, new \work\Action(), [$eTask['id']])->empty());
		$this->assertTrue(TreatmentLib::getTasksByIds($eStructure, $eAction, [])->empty());

	}

	public function testCatalogProductOnce(): void {

		$eTask = self::getDoneTreatment();
		$eStructure = \structure\StructureLib::getById($eTask['structure']['id']);
		$eAction = TreatmentLib::getAction($eStructure);

		// Un produit du catalogue que la structure n'a pas encore.
		$amms = \work\Tool::model()
			->select(['routineValue'])
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->getCollection()
			->makeArray(fn($eTool) => $eTool['routineValue']['amm'] ?? NULL);

		$amms = array_values(array_filter($amms));

		$ePhytoProduct = \work\PhytoProduct::model()
			->select(['amm'])
			->whereAmm('NOT IN', $amms, if: $amms !== [])
			->sort(['amm' => SORT_ASC])
			->get();

		$eTaskOther = \series\Task::model()
			->select(['id'])
			->whereStructure($eStructure)
			->whereAction($eAction)
			->whereId('!=', $eTask['id'])
			->get();

		$cTask = TreatmentLib::getTasksByIds($eStructure, $eAction, [$eTask['id'], $eTaskOther['id']]);

		$input = [
			'products' => ['amm-'.$ePhytoProduct['amm']],
			'treatment' => ['dose' => '2', 'unit' => 'kg/ha', 'water' => '300'],
		];

		// La boucle de `doUpdateProduct`.
		foreach($cTask as $eTaskSelected) {

			$fw = new \FailWatch();

			$eTaskSelected['structure'] = $eStructure;
			$eTaskSelected->build(['products', 'treatment'], $input);

			$this->assertTrue($fw->ok());

			\series\TaskLib::update($eTaskSelected, ['products', 'treatment']);

		}

		$eTool = \work\ToolLib::getPhytoByAmm($eStructure, $ePhytoProduct['amm']);
		$this->assertTrue($eTool->notEmpty());

		$count = \work\Tool::model()
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->where('JSON_UNQUOTE(JSON_EXTRACT(routineValue, "$.amm")) = '.\work\Tool::model()->format($ePhytoProduct['amm']))
			->count();

		$this->assertEquals(1, $count, 'un seul produit créé pour tous les traitements cochés');
		$this->assertEquals([$eTool['id']], $this->getColumns($eTask)[1]);
		$this->assertEquals([$eTool['id']], $this->getColumns($eTaskOther)[1]);

	}

	/**
	 * « Oui, c'est un produit » sur un matériel dont le produit choisi est déjà enregistré sous la
	 * même AMM : rien n'est créé ni renommé, les traitements reçoivent ce produit et sa dose, et le
	 * matériel qui ne sert plus s'efface.
	 */
	public function testRepriseSameProduct(): void {

		[$eStructure, $eAction, $eTask, $ePhytoProduct] = $this->getReprise();

		$eToolProduct = \work\ToolLib::createPhytoFromCatalog($eStructure, $ePhytoProduct, ['dose' => 7.0, 'unit' => 'kg/ha', 'water' => NULL]);
		$eToolMaterial = $this->createTool($eStructure, $eAction, 'Matériel en double');

		\series\Task::model()->update($eTask, ['tools' => [$eToolMaterial['id']], 'products' => [], 'treatment' => NULL]);

		$eToolForm = \work\ToolLib::getById($eToolMaterial['id']);
		\work\ToolLib::loadPhytoReprise($eToolForm, $ePhytoProduct['amm']);

		$this->assertEquals($eToolProduct['id'], $eToolForm['eToolPhytoSame']['id'], 'le formulaire annonce le produit déjà enregistré');
		$this->assertEquals(1, $eToolForm['phytoTasks']);

		$this->updateReprise($eToolMaterial, $eAction, $ePhytoProduct, $eToolProduct['routineValue']);

		$this->assertEquals([[], [$eToolProduct['id']]], $this->getColumns($eTask), 'le traitement reçoit le produit déjà enregistré');
		$this->assertEquals(['dose' => 7.0, 'unit' => 'kg/ha', 'water' => NULL], $this->getTreatment($eTask), 'avec sa dose');
		$this->assertFalse(\work\Tool::model()->whereId($eToolMaterial['id'])->exists(), 'le matériel qui ne sert plus est supprimé');
		$this->assertEquals($eToolProduct['name'], \work\ToolLib::getById($eToolProduct['id'])['name'], 'le produit n\'est pas renommé');

	}

	/**
	 * Sans produit enregistré sous la même AMM, le matériel devient le produit.
	 */
	public function testRepriseNewProduct(): void {

		[$eStructure, $eAction, $eTask, $ePhytoProduct] = $this->getReprise();

		$eToolMaterial = $this->createTool($eStructure, $eAction, 'Matériel seul');

		\series\Task::model()->update($eTask, ['tools' => [$eToolMaterial['id']], 'products' => [], 'treatment' => NULL]);

		$eToolForm = \work\ToolLib::getById($eToolMaterial['id']);
		\work\ToolLib::loadPhytoReprise($eToolForm, $ePhytoProduct['amm']);

		$this->assertTrue($eToolForm['eToolPhytoSame']->empty());

		$this->updateReprise($eToolMaterial, $eAction, $ePhytoProduct, ['dose' => 3.0, 'unit' => 'L/ha', 'water' => 200]);

		$this->assertEquals([[], [$eToolMaterial['id']]], $this->getColumns($eTask), 'le matériel devient le produit du traitement');
		$this->assertEquals(['dose' => 3.0, 'unit' => 'L/ha', 'water' => $ePhytoProduct->isReadyToUse() ? NULL : 200], $this->getTreatment($eTask));
		$this->assertEquals('phyto', \work\ToolLib::getById($eToolMaterial['id'])['routineName']);

	}

	/**
	 * Un traitement fait, sa structure, et un produit du catalogue que la structure n'a pas encore.
	 */
	private function getReprise(): array {

		$eTask = self::getDoneTreatment();
		$eStructure = \structure\StructureLib::getById($eTask['structure']['id']);
		$eAction = TreatmentLib::getAction($eStructure);

		$amms = \work\Tool::model()
			->select(['routineValue'])
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->getCollection()
			->makeArray(fn($eTool) => $eTool['routineValue']['amm'] ?? NULL);

		$amms = array_values(array_filter($amms));

		$ePhytoProduct = \work\PhytoProduct::model()
			->select(\work\PhytoProduct::getSelection())
			->whereAmm('NOT IN', $amms, if: $amms !== [])
			->sort(['amm' => SORT_ASC])
			->get();

		return [$eStructure, $eAction, $eTask, $ePhytoProduct];

	}

	/**
	 * Ce que poste le formulaire de la carte de reprise, construit et écrit comme `doUpdate`.
	 */
	private function updateReprise(\work\Tool $eToolMaterial, \work\Action $eAction, \work\PhytoProduct $ePhytoProduct, array $treatment): void {

		$eTool = \work\ToolLib::getById($eToolMaterial['id']);

		$properties = \work\ToolLib::getPropertiesUpdate()($eTool);

		$fw = new \FailWatch();

		$eTool->build($properties, [
			'name' => $eTool['name'],
			'action' => $eAction['id'],
			'routineName' => 'phyto',
			'routineValue' => ['amm' => $ePhytoProduct['amm']] + $treatment,
		]);

		$this->assertTrue($fw->ok(), 'le formulaire se construit');

		\work\ToolLib::update($eTool, $properties);

		$this->assertTrue($fw->ok(), 'et s\'écrit sans erreur');

	}

	private function createTool(\structure\Structure $eStructure, \work\Action $eAction, string $name): \work\Tool {

		$eTool = new \work\Tool([
			'structure' => $eStructure,
			'name' => $name.' '.uniqid(),
			'action' => $eAction,
			'routineName' => NULL,
			'routineValue' => [],
		]);

		\work\ToolLib::create($eTool);

		return $eTool;

	}

	/**
	 * Une structure qui n'a plus d'action de traitement (16 en production le 28/09, dont la 266) ouvre
	 * son registre sans rien à saisir ni produit à chercher : les deux se construisent sur l'action.
	 */
	public function testWithoutActionOpens(): void {

		$eStructure = \structure\StructureLib::getById(
			\structure\Structure::model()
				->where('NOT '.new \work\ActionModel()->where('s1.structure', new \Sql('m1.id'))->whereFqn(ACTION_TRAITEMENT)->existsSql())
				->sort(['id' => SORT_ASC])
				->getValue('id')
		);

		$this->assertTrue($eStructure->notEmpty(), 'aucune structure sans action de traitement, le cas ne s\'éprouve pas');

		$eAction = TreatmentLib::getAction($eStructure);
		$this->assertTrue($eAction->empty());

		$search = new \Search(['plant' => new \plant\Plant(), 'tool' => new \work\Tool(), 'without' => FALSE]);

		$uiTreatment = new TreatmentUi();
		$htmlYears = $uiTreatment->getYears($eStructure, $eAction, [\Clock::year()], [\Clock::year()], \Clock::year(), $search);

		$this->assertFalse(str_contains($htmlYears, 'createFromScratch'), 'sans action, le registre propose de saisir un traitement');

	}

}
?>
