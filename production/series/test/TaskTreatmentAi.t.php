<?php
namespace series;

/**
 * La dose et la bouillie d'une intervention de traitement (lot L4' de production-finir) : `build()`
 * ne les garde que sur une intervention de traitement, avec l'unité de la dose, et un champ vide
 * reprend la valeur du produit. Le produit a son propre champ (`products`) : un produit de la
 * structure, ou un produit du catalogue pris sous son AMM, qui devient un produit de la structure à
 * l'enregistrement avec la dose de l'intervention. Le jour proposé dans le carnet à un traitement
 * noté à la semaine vient de la première source qui en donne un dans la semaine faite.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=series file=TaskTreatmentAi
 */
class TaskTreatmentAiTest extends \Test {

	public function setUp(): void {
		Task::model()->beginTransaction();
	}

	public function tearDown(): void {
		Task::model()->rollBack();
	}

	private function build(string $fqn, array $treatment): array {

		$eTask = new Task([
			'action' => new \work\Action(['fqn' => $fqn])
		]);

		$fw = new \FailWatch();
		$eTask->build(['treatment'], ['treatment' => $treatment]);

		return [$eTask['treatment'] ?? NULL, array_map(fn($fail) => $fail[1], $fw->get())];

	}

	public function testBuild(): void {

		$this->assertEquals([['dose' => 2.5, 'unit' => 'L/ha', 'water' => 400], []], $this->build(ACTION_TRAITEMENT, ['dose' => '2.5', 'unit' => 'L/ha', 'water' => '400']));
		$this->assertEquals([['dose' => NULL, 'unit' => NULL, 'water' => 300], []], $this->build(ACTION_TRAITEMENT, ['dose' => '', 'unit' => 'kg/ha', 'water' => '300']), 'une dose vide reste vide, et son unité avec');
		$this->assertEquals([NULL, []], $this->build(ACTION_TRAITEMENT, ['dose' => '', 'unit' => 'kg/ha', 'water' => '']), 'deux champs vides ne font pas de traitement');
		$this->assertEquals([NULL, []], $this->build(ACTION_RECOLTE, ['dose' => '2.5', 'unit' => 'kg/ha', 'water' => '400']), 'une autre action ne garde rien');

		[, $fails] = $this->build(ACTION_TRAITEMENT, ['dose' => '0', 'unit' => 'kg/ha', 'water' => '400']);
		$this->assertEquals(['Task::treatment.dose'], $fails);

		[, $fails] = $this->build(ACTION_TRAITEMENT, ['dose' => '2', 'unit' => 'kg', 'water' => '400']);
		$this->assertEquals(['Task::treatment.dose'], $fails, 'une unité hors de celles des produits');

		[, $fails] = $this->build(ACTION_TRAITEMENT, ['dose' => '1', 'unit' => 'kg/ha', 'water' => '-3']);
		$this->assertEquals(['Task::treatment.water'], $fails);

	}

	/**
	 * Une structure qui porte l'action de traitement, un de ses produits de traitement et un de ses
	 * matériels ordinaires, et un produit du catalogue qu'elle n'a pas.
	 */
	private function getScene(): array {

		$eAction = \work\Action::model()
			->select(['id', 'fqn', 'structure'])
			->whereFqn(ACTION_TRAITEMENT)
			->where('m1.structure IS NOT NULL')
			->sort(['id' => SORT_ASC])
			->get();

		$eStructure = \structure\StructureLib::getById($eAction['structure']['id']);

		$eToolPhyto = \work\ToolLib::getNewTool($eStructure, 'phyto');
		$eToolPhyto->merge([
			'name' => 'Produit '.uniqid(),
			'routineValue' => ['amm' => NULL, 'dose' => 1.0, 'unit' => 'kg/ha', 'water' => NULL, 'functions' => NULL, 'formulation' => NULL, 'organic' => FALSE, 'readyToUse' => FALSE]
		]);
		\work\ToolLib::create($eToolPhyto);

		$eTool = new \work\Tool([
			'structure' => $eStructure,
			'name' => 'Pulvérisateur '.uniqid(),
			'action' => new \work\Action(),
			'routineName' => NULL,
			'routineValue' => []
		]);
		\work\ToolLib::create($eTool);

		$amms = \work\Tool::model()
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->getColumn(new \Sql('JSON_UNQUOTE(JSON_EXTRACT(routineValue, "$.amm"))', 'string'));

		$ePhytoProduct = \work\PhytoProduct::model()
			->select(\work\PhytoProduct::getSelection())
			->whereAmm('NOT IN', array_filter($amms) ?: ['-'])
			->sort(['amm' => SORT_ASC])
			->get();

		return [$eStructure, $eAction, $eToolPhyto, $eTool, $ePhytoProduct];

	}

	private function buildProducts(\structure\Structure $eStructure, \work\Action $eAction, array $input): Task {

		$eTask = new Task([
			'structure' => $eStructure,
			'action' => $eAction
		]);

		$eTask->build(['products', 'treatment', 'tools'], $input);

		return $eTask;

	}

	public function testProducts(): void {

		[$eStructure, $eAction, $eToolPhyto, $eTool, $ePhytoProduct] = $this->getScene();

		$fw = new \FailWatch();

		$eTask = $this->buildProducts($eStructure, $eAction, ['products' => $eToolPhyto['id'], 'tools' => [$eTool['id'], $eToolPhyto['id']]]);

		$this->assertEquals([$eToolPhyto['id']], $eTask['products'], 'le produit de la structure');
		$this->assertEquals([$eTool['id']], $eTask['tools'], 'le matériel ne garde pas les produits');
		$this->assertTrue($eTask['ePhytoProductNew']->empty());

		$eTask = $this->buildProducts($eStructure, $eAction, ['products' => $eTool['id']]);
		$this->assertEquals([], $eTask['products'], 'un matériel ordinaire n\'est pas un produit');

		$eTask = $this->buildProducts($eStructure, \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE), ['products' => $eToolPhyto['id']]);
		$this->assertEquals([], $eTask['products'], 'une récolte ne prend pas de produit');

		$this->assertTrue($fw->ok());

		// Un produit du catalogue : la dose est exigée, puisqu'elle devient celle du produit.
		$eTask = $this->buildProducts($eStructure, $eAction, ['products' => 'amm-'.$ePhytoProduct['amm'], 'treatment' => ['dose' => '', 'unit' => 'kg/ha', 'water' => '']]);

		$this->assertEquals([], $eTask['products']);
		$this->assertEquals($ePhytoProduct['amm'], $eTask['ePhytoProductNew']['amm']);
		$this->assertEquals(['Task::treatment.newProduct'], array_map(fn($fail) => $fail[1], $fw->get()));

		$fw = new \FailWatch();

		$eTask = $this->buildProducts($eStructure, $eAction, ['products' => 'amm-INCONNUE', 'treatment' => ['dose' => '2', 'unit' => 'kg/ha', 'water' => '']]);
		$this->assertEquals(['Task::products.action'], array_map(fn($fail) => $fail[1], $fw->get()), 'une AMM absente du catalogue');

		// L'enregistrement crée le produit de la structure, une seule fois.
		$treatment = ['dose' => 3.5, 'unit' => 'L/ha', 'water' => 250];

		$eToolCreated = \work\ToolLib::createPhytoFromCatalog($eStructure, $ePhytoProduct, $treatment);

		$this->assertEquals('phyto', $eToolCreated['routineName']);
		$this->assertEquals([$ePhytoProduct['amm'], 3.5, 'L/ha'], [$eToolCreated['routineValue']['amm'], $eToolCreated['routineValue']['dose'], $eToolCreated['routineValue']['unit']]);
		$this->assertEquals($eAction['id'], $eToolCreated['action']['id']);

		$this->assertEquals($eToolCreated['id'], \work\ToolLib::createPhytoFromCatalog($eStructure, $ePhytoProduct, $treatment)['id'], 'un second choix reprend le produit créé');

		$eTask = $this->buildProducts($eStructure, $eAction, ['products' => 'amm-'.$ePhytoProduct['amm']]);
		$this->assertEquals([$eToolCreated['id']], $eTask['products'], 'l\'AMM d\'un produit de la structure désigne ce produit');

	}

	/**
	 * Sans intervention choisie, seule l'action se refuse : le produit dépend d'elle et n'a pas à
	 * ajouter l'erreur du catalogue E-Phy (signalé par Alain B le 2026-10-04).
	 */
	public function testProductsWithoutAction(): void {

		[$eStructure] = $this->getScene();

		$eTask = new Task([
			'structure' => $eStructure,
			'category' => \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE)
		]);

		$fw = new \FailWatch();
		$eTask->build(['action', 'products', 'treatment'], ['action' => '', 'products' => '', 'treatment' => ['dose' => '', 'unit' => '', 'water' => '']]);

		$this->assertEquals(['Task::action.check'], array_map(fn($fail) => $fail[1], $fw->get()));

	}

	/**
	 * Un produit créé depuis la recherche (« Créer « cvbc » ») arrive sous la valeur `new`, avec son
	 * seul nom dans `productNew` : la ligne Traitement de l'intervention devient sa dose et sa bouillie
	 * par défaut, comme pour un produit du catalogue, et rien ne s'écrit avant l'enregistrement. Un
	 * texte saisi sans rien choisir ensuite (`productQuery`) est refusé, l'intervention partirait sans
	 * produit.
	 */
	public function testProductsNew(): void {

		[$eStructure, $eAction, $eToolPhyto] = $this->getScene();

		$name = 'Nouveau '.uniqid();
		$treatment = ['dose' => '1.5', 'unit' => 'L/ha', 'water' => '200'];

		$fails = function(array $input) use($eStructure, $eAction): array {
			$fw = new \FailWatch();
			$this->buildProducts($eStructure, $eAction, $input);
			return array_map(fn($fail) => $fail[1], $fw->get());
		};

		$fw = new \FailWatch();

		$eTask = $this->buildProducts($eStructure, $eAction, ['products' => 'new', 'productNew' => ['name' => $name], 'treatment' => $treatment]);

		$this->assertTrue($fw->ok());
		$this->assertEquals([], $eTask['products'], 'le produit n\'existe pas encore');
		$this->assertEquals($name, $eTask['eToolNew']['name']);
		$this->assertFalse(\work\Tool::model()->whereStructure($eStructure)->whereName($name)->exists(), 'rien ne s\'écrit à la construction');

		$this->assertEquals(['Task::treatment.newProduct'], $fails(['products' => 'new', 'productNew' => ['name' => $name], 'treatment' => ['dose' => '', 'unit' => 'L/ha', 'water' => '200']]), 'la dose est exigée sur la ligne Traitement, puisqu\'elle devient celle du produit');
		$this->assertEquals(['Task::treatment.newProduct'], $fails(['products' => 'new', 'productNew' => ['name' => $name]]), 'sans ligne Traitement');
		$this->assertEquals(['Tool::name.check'], $fails(['products' => 'new', 'productNew' => ['name' => ''], 'treatment' => $treatment]), 'sans nom');
		$this->assertEquals(['Tool::name.duplicate'], $fails(['products' => 'new', 'productNew' => ['name' => $eToolPhyto['name']], 'treatment' => $treatment]), 'le nom d\'un produit de la structure');
		$this->assertEquals(['Task::products.unknown'], $fails(['products' => '', 'productQuery' => 'cvbc']), 'un produit saisi sans être choisi');
		$this->assertEquals(['Task::products.unknown'], $fails(['products' => $eToolPhyto['id'], 'productQuery' => 'cvbc']), 'un texte saisi après un choix ne garde pas le choix');

		// L'enregistrement crée le produit avec la dose de l'intervention, une seule fois pour deux interventions construites ensemble.
		$eTaskOther = $this->buildProducts($eStructure, $eAction, ['products' => 'new', 'productNew' => ['name' => $name], 'treatment' => ['dose' => '4', 'unit' => 'kg/ha', 'water' => '']]);

		$eToolCreated = \work\ToolLib::createPhytoNew($eTask['eToolNew'], $eTask['treatment']);

		$this->assertEquals([$eAction['id'], 'phyto'], [$eToolCreated['action']['id'], $eToolCreated['routineName']]);
		$this->assertEquals([1.5, 'L/ha', 200, NULL], [$eToolCreated['routineValue']['dose'], $eToolCreated['routineValue']['unit'], $eToolCreated['routineValue']['water'], $eToolCreated['routineValue']['amm']]);

		$eToolAgain = \work\ToolLib::createPhytoNew($eTaskOther['eToolNew'], $eTaskOther['treatment']);

		$this->assertEquals($eToolCreated['id'], $eToolAgain['id']);
		$this->assertEquals(1.5, $eToolAgain['routineValue']['dose'], 'la seconde intervention ne réécrit pas la dose du produit');
		$this->assertEquals(1, \work\Tool::model()->whereStructure($eStructure)->whereName($name)->count());

	}

	/**
	 * Une intervention notée semaine 20 de 2026 (du lundi 11 au dimanche 17 mai), créée et modifiée
	 * hors de cette semaine, sans temps de travail : aucune source ne répond tant qu'un test n'en pose
	 * pas une.
	 */
	private static function getTaskWeek(array $properties = []): Task {

		return new Task($properties + [
			'doneWeek' => '2026-W20',
			'timesheetStart' => NULL,
			'timesheetStop' => NULL,
			'createdAt' => '2026-04-02 10:00:00',
			'updatedAt' => '2026-06-20 10:00:00'
		]);

	}

	public function testDoneProposedNone(): void {

		$this->assertEquals(NULL, TaskLib::getDoneProposed(self::getTaskWeek(), [], []));
		$this->assertEquals(NULL, TaskLib::getDoneProposed(self::getTaskWeek(), ['2026-05-12', '2026-05-14'], []), 'des traitements faits deux jours différents ne disent pas lequel');

	}

	public function testDoneProposedTimesheet(): void {

		$this->assertEquals(['date' => '2026-05-12', 'source' => TaskLib::PROPOSED_TIMESHEET], TaskLib::getDoneProposed(self::getTaskWeek(['timesheetStart' => '2026-05-12', 'timesheetStop' => '2026-05-12']), [], []));
		$this->assertEquals(NULL, TaskLib::getDoneProposed(self::getTaskWeek(['timesheetStart' => '2026-05-20', 'timesheetStop' => '2026-05-20']), [], []), 'du temps saisi une autre semaine ne date pas l\'intervention');

	}

	public function testDoneProposedTimesheets(): void {

		$this->assertEquals(['date' => '2026-05-12', 'source' => TaskLib::PROPOSED_TIMESHEETS], TaskLib::getDoneProposed(self::getTaskWeek(['timesheetStart' => '2026-05-12', 'timesheetStop' => '2026-05-14']), [], []), 'le premier jour');
		$this->assertEquals(['date' => '2026-05-11', 'source' => TaskLib::PROPOSED_TIMESHEETS], TaskLib::getDoneProposed(self::getTaskWeek(['timesheetStart' => '2026-05-08', 'timesheetStop' => '2026-05-12']), [], []), 'le premier jour de la semaine faite, quand le temps commence avant elle');
		$this->assertEquals(NULL, TaskLib::getDoneProposed(self::getTaskWeek(['timesheetStart' => '2026-05-18', 'timesheetStop' => '2026-05-20']), [], []), 'du temps saisi après la semaine faite');

	}

	public function testDoneProposedDone(): void {
		$this->assertEquals(['date' => '2026-05-13', 'source' => TaskLib::PROPOSED_DONE], TaskLib::getDoneProposed(self::getTaskWeek(), ['2026-05-13'], []));
	}

	public function testDoneProposedUpdated(): void {
		$this->assertEquals(['date' => '2026-05-15', 'source' => TaskLib::PROPOSED_UPDATED], TaskLib::getDoneProposed(self::getTaskWeek(['updatedAt' => '2026-05-15 18:30:00']), [], []));
	}

	public function testDoneProposedCreated(): void {
		$this->assertEquals(['date' => '2026-05-11', 'source' => TaskLib::PROPOSED_CREATED], TaskLib::getDoneProposed(self::getTaskWeek(['createdAt' => '2026-05-11 07:00:00']), [], []));
	}

	public function testDoneProposedComment(): void {
		$this->assertEquals(['date' => '2026-05-16', 'source' => TaskLib::PROPOSED_COMMENT], TaskLib::getDoneProposed(self::getTaskWeek(), [], ['2026-05-02', '2026-05-16', '2026-05-17']), 'le premier commentaire de la semaine faite');
	}

	/**
	 * Toutes les sources répondent, chacune par un jour différent : chaque retrait laisse passer la
	 * suivante, dans l'ordre arrêté par M-Prod le 27/09/2026.
	 */
	public function testDoneProposedOrder(): void {

		$properties = [
			'timesheetStart' => '2026-05-11',
			'timesheetStop' => '2026-05-11',
			'updatedAt' => '2026-05-13 10:00:00',
			'createdAt' => '2026-05-14 10:00:00'
		];
		$datesDone = ['2026-05-12'];
		$datesComment = ['2026-05-15'];

		$sources = [];

		$sources[] = TaskLib::getDoneProposed(self::getTaskWeek($properties), $datesDone, $datesComment);

		$properties['timesheetStop'] = '2026-05-15';
		$sources[] = TaskLib::getDoneProposed(self::getTaskWeek($properties), $datesDone, $datesComment);

		$properties['timesheetStart'] = NULL;
		$properties['timesheetStop'] = NULL;
		$sources[] = TaskLib::getDoneProposed(self::getTaskWeek($properties), $datesDone, $datesComment);

		$sources[] = TaskLib::getDoneProposed(self::getTaskWeek($properties), [], $datesComment);

		unset($properties['updatedAt']);
		$sources[] = TaskLib::getDoneProposed(self::getTaskWeek($properties), [], $datesComment);

		unset($properties['createdAt']);
		$sources[] = TaskLib::getDoneProposed(self::getTaskWeek($properties), [], $datesComment);

		$this->assertEquals([
			['date' => '2026-05-11', 'source' => TaskLib::PROPOSED_TIMESHEET],
			['date' => '2026-05-11', 'source' => TaskLib::PROPOSED_TIMESHEETS],
			['date' => '2026-05-12', 'source' => TaskLib::PROPOSED_DONE],
			['date' => '2026-05-13', 'source' => TaskLib::PROPOSED_UPDATED],
			['date' => '2026-05-14', 'source' => TaskLib::PROPOSED_CREATED],
			['date' => '2026-05-15', 'source' => TaskLib::PROPOSED_COMMENT],
		], $sources);

	}

	/**
	 * Le carnet lit la proposition sur ses propres interventions : elle se pose sur les éléments de la
	 * collection reçue, et une semaine où rien n'est daté laisse répondre l'intervention elle-même. Les
	 * ids et la semaine de 2031 ne portent ni commentaire ni intervention en base.
	 */
	public function testFillDoneProposed(): void {

		[$eStructure, $eAction] = $this->getScene();

		$cTask = new \Collection([
			2000000001 => self::getTaskWeek(['id' => 2000000001, 'doneWeek' => '2031-W10', 'doneDate' => NULL, 'updatedAt' => '2031-03-05 09:00:00']),
			2000000002 => self::getTaskWeek(['id' => 2000000002, 'doneWeek' => '2031-W10', 'doneDate' => '2031-03-04']),
		]);

		TaskLib::fillDoneProposed($eStructure, $eAction, $cTask);

		$this->assertEquals(['date' => '2031-03-05', 'source' => TaskLib::PROPOSED_UPDATED], $cTask[2000000001]['doneProposed']);
		$this->assertFalse($cTask[2000000002]->offsetExists('doneProposed'), 'une intervention datée n\'a rien à proposer');

	}

	/**
	 * Le planning crée les interventions des répétitions de la semaine affichée
	 * (`RepeatLib::createForWeek()`), et chacune passe par `prepareTreatment()`, qui lit l'action de la
	 * répétition : une sélection qui ne charge pas son fqn lève « Property action[fqn] is not set » à
	 * l'ouverture du planning. Rejoue l'appel de la page sur une répétition hebdomadaire ouverte, pour
	 * la semaine qui suit son dernier passage.
	 */
	public function testCreateForWeekReadsAction(): void {

		$cRepeat = Repeat::model()
			->select(['id', 'structure', 'current', 'stop'])
			->whereCompleted(FALSE)
			->whereFrequency(Repeat::W1)
			->where('current BETWEEN '.Repeat::model()->format((\Clock::year() - 1).'-01-01').' AND '.Repeat::model()->format(\Clock::year().'-11-30'))
			->sort(['id' => SORT_DESC])
			->getCollection();

		// La semaine visée doit rester avant la fin de la répétition, que le planning ne dépasse pas.
		$eRepeat = $cRepeat->find(fn(Repeat $e) => $e['stop'] === NULL or $e['stop'] >= toWeek(strtotime($e['current'].' + 7 DAY')), limit: 1, default: new Repeat());

		$this->assertTrue($eRepeat->notEmpty(), 'aucune répétition hebdomadaire ouverte en base');

		$week = toWeek(strtotime($eRepeat['current'].' + 7 DAY'));

		// Une intervention créée porte son auteur, que le planning prend à l'utilisateur connecté.
		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline(\user\User::model()->select('id')->sort(['id' => SORT_ASC])->get());

		RepeatLib::createForWeek($eRepeat['structure'], $week);

		\user\ConnectionLib::setOnline($eUserInitial);

		$this->assertEquals(1, Task::model()
			->whereRepeat($eRepeat)
			->wherePlannedWeek($week)
			->count(), 'l\'intervention de la semaine est créée');

	}

	/**
	 * L'assolement cherche aussi dans les produits des interventions, qu'il lit sur les tâches
	 * chargées par `PlotLib::putFromZoneWithSeries()`, pas par `getTasksForSearch()` : une sélection
	 * oubliée là lève « Undefined array key products » sur toutes les structures. Rejoue l'appel de
	 * la page (`series/page/index.p.php`) sur une série placée dont une intervention porte un
	 * produit, sans rien écrire.
	 */
	public function testSoilSearchReadsProducts(): void {

		$ePlace = Place::model()
			->select(['structure', 'season', 'series'])
			->where('m1.series IS NOT NULL')
			->where(new TaskModel()
				->where('s1.series', new \Sql('m1.series'))
				->where('s1.cultivation IS NOT NULL')
				->where('JSON_LENGTH(s1.products) > 0')
				->existsSql())
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($ePlace->notEmpty(), 'aucune série placée ne porte de produit en dev');

		$eTask = Task::model()
			->select(['products'])
			->whereSeries($ePlace['series'])
			->where('m1.cultivation IS NOT NULL')
			->where('JSON_LENGTH(m1.products) > 0')
			->get();

		$productNames = \work\Tool::model()
			->whereId('IN', $eTask['products'])
			->getColumn('name');

		$eStructure = \structure\StructureLib::getById($ePlace['structure']['id']);
		$cZone = \map\ZoneLib::getByStructure($eStructure, season: $ePlace['season']);

		\map\PlotLib::putFromZoneWithSeries($eStructure, $cZone, $ePlace['season'], [$ePlace['season']], onlySeries: TRUE);
		CultivationLib::putSearchVocabulary($eStructure, $cZone);

		$toolNames = [];

		foreach($cZone as $eZone) {
			foreach($eZone['cPlot'] as $ePlot) {
				foreach($ePlot['cBed'] as $eBed) {
					foreach($eBed['cPlace'] as $ePlaceBed) {
						if($ePlaceBed['series']->notEmpty() and $ePlaceBed['series']->is($ePlace['series'])) {
							foreach($ePlaceBed['series']['cCultivation'] as $eCultivation) {
								$toolNames = array_merge($toolNames, $eCultivation['toolNames'] ?? []);
							}
						}
					}
				}
			}
		}

		$this->assertEquals([], array_values(array_diff($productNames, $toolNames)), 'produits absents du vocabulaire de recherche de l\'assolement');

	}

	public function testTanks(): void {

		$this->assertEquals([15.0, 15.0, 6.0], \work\RoutineLib::getTanks(36.0, 15.0));
		$this->assertEquals([15.0], \work\RoutineLib::getTanks(15.04, 15.0), 'un reste sous 50 mL ne fait pas une cuve');
		$this->assertEquals([], \work\RoutineLib::getTanks(0.0, 15.0));

	}

}
?>
