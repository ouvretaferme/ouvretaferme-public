<?php
namespace planning;

/**
 * « Récolter » sur les séries cochées des Récoltes (DG-2) : la case de chaque série désigne sa culture, et une
 * série qui n'a pas encore d'intervention de récolte dans la semaine en reçoit une, comme la saisie d'une case
 * (`HarvestWeekLib::getTasksForBatch()`). Le panneau et l'écriture sont ceux de la Semaine (DG-1,
 * `TaskBatchHarvestTest`) : ici, ce qui tient aux Récoltes, les cultures, l'intervention neuve, l'unité des lignes
 * et les cases.
 *
 * Lancement : dev/test package=planning file=HarvestBatch
 */
class HarvestBatchTest extends HarvestFixtureTest {

	/**
	 * Deux séries de 30 et 60 m², la première avec sa récolte de la semaine (2 kg), la seconde sans : 9 kg à la
	 * surface donnent 3 et 6 kg, et la seconde reçoit une intervention faite aujourd'hui, dans l'unité de sa ligne.
	 */
	public function testNewAndExisting(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();

		$eCultivationHarvested = $this->createCultivationArea($ePlant, 30);
		$eTaskHarvested = $this->createTaskHarvest($eCultivationHarvested, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskHarvested, 2.0, \Clock::date());

		$eCultivationNew = $this->createCultivationArea($ePlant, 60);

		$data = $this->getPanel([$this->getValue($eCultivationHarvested), $this->getValue($eCultivationNew)]);

		if($data !== NULL) {
			$this->assertEquals(['area', 'fair'], $data->distributions, 'deux séries mesurées n\'offrent pas la surface et les parts égales');
			$this->assertEquals(\series\Task::KG, $data->unit, 'les lignes en kilos ne se récoltent pas en kilos');
			$this->assertEquals([], $data->tasksByUnit, 'l\'unité des Récoltes se choisit');
			$this->assertFalse($data->cTaskBatch[$this->getValue($eCultivationNew)]->exists(), 'la série sans récolte de la semaine en a une avant l\'écriture');
			$this->assertEquals(\Clock::date(), $data->date, 'le panneau ne propose pas le jour ouvert');
			$this->assertEquals(2.0, $data->cTaskBatch[$this->getValue($eCultivationHarvested)]['harvestWeek'][\Clock::date()], 'la part de la série récoltée ne lit pas sa récolte du jour');
			$this->assertEquals(30, $data->cTaskBatch[$this->getValue($eCultivationHarvested)]['distributionArea'], 'la série récoltée n\'a pas sa surface');
			$this->assertEquals(60, $data->cTaskBatch[$this->getValue($eCultivationNew)]['distributionArea'], 'la série sans récolte n\'a pas sa surface');

			// Rendu comme la vue `planning/harvestWeek:batchHarvest` : la part de la série neuve n'a pas
			// d'intervention écrite dont elle porterait l'identifiant, et se lit à sa place dans l'ordre des `ids[]`.
			$panel = new TaskBatchUi()->getHarvest($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->days, $data->date, $data->distributions, $data->distribution, $data->ePlant, $data->unit, $data->tasksByUnit, $data->eTaskStock, $data->cUserTime, $data->eCultivationChoice);

			$this->assertEquals(2, substr_count($panel->body, 'data-planning-batch-harvest-share'), 'le panneau d\'une série récoltée et d\'une série neuve n\'a pas une part par série');
			$this->assertEquals(2, substr_count($panel->body, 'name="ids[]"'), 'le panneau d\'une série récoltée et d\'une série neuve ne poste pas ses deux cultures');
			$this->assertTrue(str_contains($panel->body, 'value="'.$this->getValue($eCultivationNew).'"'), 'le panneau ne poste pas la culture de la série neuve');
		}

		[$data, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivationHarvested), $this->getValue($eCultivationNew)], $this->getInput('9', ['distribution' => 'area', 'time' => [\user\ConnectionLib::getOnline()['id'] => '1.5']]));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 5.0], $this->getHarvested($eTaskHarvested), 'la série de 30 m² ne reçoit pas le tiers de la récolte, en plus des 2 kg déjà récoltés');

		$cTaskNew = $this->getHarvestTasks($eCultivationNew);

		if($this->assertEquals(1, $cTaskNew->count(), 'la série sans récolte de la semaine ne reçoit pas une intervention') === FALSE) {
			return;
		}

		$eTaskNew = $cTaskNew->first();

		$this->assertEquals([\Clock::date() => 6.0], $this->getHarvested($eTaskNew), 'la série de 60 m² ne reçoit pas les deux tiers de la récolte');
		$this->assertEquals(\series\Task::DONE, $eTaskNew['status'], 'l\'intervention créée n\'est pas faite');
		$this->assertEquals(\series\Task::KG, $eTaskNew['harvestUnit'], 'l\'intervention créée n\'est pas dans l\'unité de sa ligne');
		$this->assertEquals(\Clock::date(), $eTaskNew['doneDate'], 'l\'intervention créée n\'est pas faite le jour récolté');
		$this->assertEquals([\Clock::date() => 1.0], $this->getTimes($eTaskNew), 'l\'intervention créée ne reçoit pas les deux tiers du temps');

		$this->assertFalse($data->change['list'], '« Récolter » rend la liste entière');
		$this->assertTrue(in_array($eCultivationNew['id'], $data->change['updated'], TRUE), 'la ligne de la série neuve n\'est pas redessinée');

	}

	/**
	 * Une seule série, sans récolte dans la semaine : l'intervention se crée, et la récolte entière y va.
	 */
	public function testNewOnly(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationArea($this->createPlant(), 30);

		$data = $this->getPanel([$this->getValue($eCultivation)]);

		if($data !== NULL) {

			$this->assertFalse($data->eTaskStock->exists(), 'la récolte de référence d\'une série sans intervention est déjà écrite');

			// Rendu comme la vue `planning/taskWeek:batchHarvest`.
			$panel = new TaskBatchUi()->getHarvest($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->days, $data->date, $data->distributions, $data->distribution, $data->ePlant, $data->unit, $data->tasksByUnit, $data->eTaskStock, $data->cUserTime, $data->eCultivationChoice);

			$this->assertTrue(str_contains($panel->body, 'data-unit="'.$data->unit.'"'), 'le panneau d\'une série sans intervention ne porte pas son unité');

		}

		// Le panneau d'une seule série pose la clé choisie d'office en champ caché (`TaskBatchUi::getHarvest()`).
		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation)], $this->getInput('4.5', ['distribution' => 'area']));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » sur une série neuve est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cTask = $this->getHarvestTasks($eCultivation);

		if($this->assertEquals(1, $cTask->count(), 'la série ne reçoit pas une intervention') === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 4.5], $this->getHarvested($cTask->first()), 'l\'intervention créée ne reçoit pas la récolte');

	}

	/**
	 * Deux espèces, ou deux lignes dans deux unités, ne se récoltent pas ensemble : le panneau ne s'ouvre pas, et
	 * l'écriture n'écrit rien.
	 */
	public function testMixed(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();

		$eCultivation = $this->createCultivationArea($ePlant, 30);
		$eCultivationOther = $this->createCultivationArea($this->createPlant(), 30);

		$eCultivationBunch = $this->createCultivationArea($ePlant, 30);
		$eCultivationBunch['mainUnit'] = \series\Cultivation::BUNCH;
		\series\CultivationLib::update($eCultivationBunch, ['mainUnit']);

		foreach([
			'deux espèces' => [$eCultivation, $eCultivationOther],
			'deux unités' => [$eCultivation, $eCultivationBunch],
		] as $case => $cultivations) {

			$ids = array_map(fn($eCultivationChecked) => $this->getValue($eCultivationChecked), $cultivations);

			$action = $this->executePage('planning/harvestWeek:batchHarvest', $this->getRoute($ids), 'GET');

			$this->assertEquals('series\Task::harvestConsistency.check', $this->getFailName($action), $case.' : le panneau s\'ouvre');

			[, , $action] = $this->callBatch('doBatchHarvest', $ids, $this->getInput('3'));

			$this->assertEquals('series\Task::harvestConsistency.check', $this->getFailName($action), $case.' : l\'écriture n\'est pas refusée');

			foreach($cultivations as $eCultivationChecked) {
				$this->assertEquals(0, $this->getHarvestTasks($eCultivationChecked)->count(), $case.' : une intervention est créée');
			}

		}

	}

	/**
	 * Une semaine à venir n'a aucun jour où récolter, une culture que la structure n'a pas ne se coche pas, et le
	 * planning limité ne récolte pas : rien ne s'ouvre ni ne s'écrit.
	 */
	public function testRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationArea($this->createPlant(), 30);

		$action = $this->executePage('planning/harvestWeek:doBatchHarvest', ['week' => $this->getSeason().'-W40'] + $this->getRoute([$this->getValue($eCultivation)]) + $this->getInput('3'), 'POST');
		$this->assertTrue($action instanceof \NotExpectedAction, 'une semaine à venir se récolte');

		// Une culture d'une autre structure, empruntée à la base : la monter demanderait la série, l'espèce et le droit
		// d'une seconde structure, quand le cas ne lit que son identifiant.
		$eCultivationForeign = \series\Cultivation::model()
			->select('id')
			->whereStructure('!=', $this->eStructure)
			->get();

		if($this->assertDecorPresent($eCultivationForeign->notEmpty(), 'une culture d\'une autre structure') === FALSE) {
			return;
		}

		// La liste de la semaine est celle de la structure : une culture d'une autre structure n'y figure pas.
		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation), $this->getValue($eCultivationForeign)], $this->getInput('3'));
		$this->assertEquals('series\Cultivation::cultivations.listed', $this->getFailName($action), 'une culture d\'une autre structure se récolte');
		$this->assertEquals(0, $this->getHarvestTasks($eCultivationForeign)->count() + $this->getHarvestTasks($eCultivation)->count(), 'une culture d\'une autre structure laisse écrire');

		// Seule, elle n'apporte aucune espèce à la lecture bornée (EX-2), qui sans espèce lirait toute la semaine :
		// la garde refuse avant toute lecture de la semaine.
		$this->assertTrue(HarvestWeekLib::getPlantsAround($this->eStructure, [$eCultivationForeign['id']])->empty(), 'une culture d\'une autre structure apporte une espèce');

		$action = $this->executePage('planning/harvestWeek:batchHarvest', $this->getRoute([$this->getValue($eCultivationForeign)]), 'GET');
		$this->assertEquals('series\Cultivation::cultivations.listed', $this->getFailName($action), 'le panneau « Récolter » s\'ouvre sur une seule culture d\'une autre structure');

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivationForeign)], $this->getInput('3'));
		$this->assertEquals('series\Cultivation::cultivations.listed', $this->getFailName($action), 'une seule culture d\'une autre structure se récolte');
		$this->assertEquals(0, $this->getHarvestTasks($eCultivationForeign)->count(), 'une seule culture d\'une autre structure laisse écrire');

		// Une série de la structure, sans récolte attendue ni faite : elle n'est pas dans la liste de la semaine.
		$eCultivationUnlisted = $this->createCultivation($this->createSeries('Série hors semaine '.uniqid()), $this->createPlant());

		if($this->assertFalse(\planning\HarvestWeekLib::get($this->eStructure, \Clock::week())->offsetExists($eCultivationUnlisted['id']), 'la série hors semaine est dans les récoltes de la semaine')) {

			$action = $this->executePage('planning/harvestWeek:batchHarvest', $this->getRoute([$this->getValue($eCultivationUnlisted)]), 'GET');
			$this->assertEquals('series\Cultivation::cultivations.listed', $this->getFailName($action), 'le panneau « Récolter » s\'ouvre sur une série absente de la semaine');

			[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation), $this->getValue($eCultivationUnlisted)], $this->getInput('3'));
			$this->assertEquals('series\Cultivation::cultivations.listed', $this->getFailName($action), 'une série absente de la semaine se récolte');
			$this->assertEquals(0, $this->getHarvestTasks($eCultivationUnlisted)->count() + $this->getHarvestTasks($eCultivation)->count(), 'une série absente de la semaine laisse écrire');

		}

		[, , $action] = $this->callBatch('doBatchHarvest', [], $this->getInput('3'));
		$this->assertTrue($action instanceof \NotExpectedAction, 'une sélection vide se récolte');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		$action = $this->executePage('planning/harvestWeek:batchHarvest', $this->getRoute([$this->getValue($eCultivation)]), 'GET');
		$this->assertTrue($action instanceof \NotAllowedAction, 'le planning limité ouvre « Récolter »');

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation)], $this->getInput('3'));
		$this->assertTrue($action instanceof \NotAllowedAction, 'le planning limité récolte');

		$this->assertEquals(0, $this->getHarvestTasks($eCultivation)->count(), 'un refus crée une intervention');

	}

	/**
	 * Deux séries d'espèces différentes, toutes deux de la semaine, postées ensemble (l'écran ne coche qu'une
	 * espèce) : la lecture bornée prend les deux espèces, et le partage refuse comme sur la semaine entière.
	 */
	public function testTwoPlants(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationArea($this->createPlant(), 30);
		$eCultivationOther = $this->createCultivationArea($this->createPlant(), 60);

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation), $this->getValue($eCultivationOther)], $this->getInput('3', ['distribution' => 'area']));
		$this->assertEquals('series\Task::harvestConsistency.check', $this->getFailName($action), 'deux espèces se récoltent ensemble');
		$this->assertEquals(0, $this->getHarvestTasks($eCultivation)->count() + $this->getHarvestTasks($eCultivationOther)->count(), 'deux espèces laissent écrire');

	}

	/**
	 * Une série à deux variétés de 10 et 30 m², chacune récoltée dans la semaine, et donc chacune sur sa part
	 * (FD, retour 405). La variété seule cochée reçoit toute la récolte ; les deux cochées se la partagent par la
	 * surface de leur tranche (arbitrage du 16/10), dans l'unité que montre leur ligne, et le stock se
	 * lit sans variété.
	 */
	public function testCouples(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivation, $eVarietyFirst, $eVarietySecond, $eTaskFirst, $eTaskSecond] = $this->createCultivationVarieties();

		$valueFirst = $this->getValue($eCultivation, $eVarietyFirst);
		$valueSecond = $this->getValue($eCultivation, $eVarietySecond);

		$valuesExpected = [$valueFirst, $valueSecond];
		sort($valuesExpected);

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			[$document] = $this->render($quick);

			$values = [];

			foreach($document->querySelectorAll('#planning-harvest-list .planning-harvest-row-sub input[name="batch[]"][data-cultivation="'.$eCultivation['id'].'"]') as $node) {
				$values[] = $node->getAttribute('value');
			}

			// L'écran range les variétés par leur nom, que le décor tire au hasard.
			sort($values);

			$this->assertEquals($valuesExpected, $values, $case.', chaque variété n\'a pas sa case');
			$this->assertTrue($document->querySelector('#planning-harvest-list [data-planning-harvest-series="'.$eCultivation['id'].'"]:not([name])') !== NULL, $case.', la série n\'a pas de case qui coche ses variétés sans rien poster');

		}

		// Le panneau d'une seule ligne pré-remplit sa variété (`HarvestWeekLib::getBatchChoices()`), et la reposte.
		[, , $action] = $this->callBatch('doBatchHarvest', [$valueSecond], $this->getInput('2', ['variety' => $eVarietySecond['id']]));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » sur une variété est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 5.0], $this->getHarvested($eTaskSecond), 'la variété cochée ne reçoit pas la récolte');
		$this->assertEquals([\Clock::date() => 1.0], $this->getHarvested($eTaskFirst), 'la variété non cochée reçoit une part');
		$this->assertEquals(2, $this->getHarvestTasks($eCultivation)->count(), 'une intervention de la série entière est créée');

		$data = $this->getPanel([$valueFirst, $valueSecond]);

		if($data !== NULL) {

			$this->assertEquals(10.0, $data->cTaskBatch[$valueFirst]['distributionArea'], 'la première variété n\'a pas la surface de sa tranche');
			$this->assertEquals(30.0, $data->cTaskBatch[$valueSecond]['distributionArea'], 'la seconde variété n\'a pas la surface de sa tranche');
			$this->assertTrue($data->eTaskStock['variety']->empty(), 'le stock de deux variétés se lit sur l\'une d\'elles');

			$cCultivationListed = HarvestWeekLib::get($this->eStructure, \Clock::week());
			$part = $cCultivationListed[$eCultivation['id']]['harvestedParts'][\series\CultivationLib::getHarvestPartKey($eVarietyFirst, new \plant\Size())];
			$this->assertEquals($part['unit'], $data->unit, 'l\'unité de « Récolter » n\'est pas celle que montre la ligne de la variété');

			// Rendu comme la vue `planning/taskWeek:batchHarvest` : le panneau reposte les couples cochés.
			$panel = new TaskBatchUi()->getHarvest($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->days, $data->date, $data->distributions, $data->distribution, $data->ePlant, $data->unit, $data->tasksByUnit, $data->eTaskStock, $data->cUserTime, $data->eCultivationChoice);

			$this->assertTrue(str_contains($panel->body, 'value="'.$valueFirst.'"') and str_contains($panel->body, 'value="'.$valueSecond.'"'), 'le panneau ne reposte pas les deux couples');

		}

		[, , $action] = $this->callBatch('doBatchHarvest', [$valueFirst, $valueSecond], $this->getInput('8', ['distribution' => 'area']));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » sur deux variétés est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date() => 3.0], $this->getHarvested($eTaskFirst), 'la variété de 10 m² ne reçoit pas le quart de la récolte');
		$this->assertEquals([\Clock::date() => 11.0], $this->getHarvested($eTaskSecond), 'la variété de 30 m² ne reçoit pas les trois quarts de la récolte');

	}

	/**
	 * Deux calibres d'une même variété se partagent sa surface, au lieu de la compter chacun : face à une autre
	 * série de même surface, la variété reçoit la moitié de la récolte, pas les deux tiers.
	 */
	public function testCouplesSizes(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivation, $eVariety] = $this->createCultivationVarieties();

		$cSize = new \Collection([$this->createSize($eCultivation['plant']), $this->createSize($eCultivation['plant'])]);
		$values = [];

		foreach($cSize as $eSize) {

			$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
			$this->ventilate($eTask, ['variety' => $eVariety, 'harvestSize' => $eSize]);
			$this->createHarvest($eTask, 1.0, \Clock::date());

			$values[] = $this->getValue($eCultivation, $eVariety, $eSize);

		}

		$data = $this->getPanel($values);

		if($data !== NULL) {
			$this->assertEquals(5.0, $data->cTaskBatch[$values[0]]['distributionArea'], 'le premier calibre ne prend pas la moitié de la surface de la variété');
			$this->assertEquals(5.0, $data->cTaskBatch[$values[1]]['distributionArea'], 'le second calibre ne prend pas la moitié de la surface de la variété');
		}

	}

	/**
	 * La part d'une variété récoltée un autre jour de la semaine a sa ligne, et « Récolter » la vise comme
	 * celles d'aujourd'hui.
	 */
	public function testOtherDayPart(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		[$eCultivation, $eVarietyFirst] = $this->createCultivationVarieties();

		$eVarietyMonday = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVarietyMonday, 10.0);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), week_date_day(\Clock::week(), 1));
		$this->ventilate($eTask, ['variety' => $eVarietyMonday]);
		$this->createHarvest($eTask, 2.0, week_date_day(\Clock::week(), 1));

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation, $eVarietyMonday)], $this->getInput('3'));
		$this->assertTrue($action instanceof \ViewAction, 'la part du lundi ne se récolte pas : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation, $eVarietyFirst)], $this->getInput('3'));
		$this->assertTrue($action instanceof \ViewAction, 'la part d\'aujourd\'hui ne se récolte pas : '.implode(', ', $this->getFails($action)));

	}

	/**
	 * Ce que l'écran ne coche pas ne s'écrit pas : « Non spécifiée » avec une variété de la même série, une variété
	 * que la série ne récolte pas dans la semaine, la série entière quand ses parts la remplacent, une valeur qui
	 * n'est pas un couple. La même ligne postée sous deux écritures ne s'écrit qu'une fois.
	 */
	public function testCouplesRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivation, $eVarietyFirst, $eVarietySecond, $eTaskFirst, $eTaskSecond] = $this->createCultivationVarieties();

		// La série entière n'est pas une ligne de l'écran tant qu'elle n'a pas de part « Non spécifiée ».
		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation)], $this->getInput('3'));
		$this->assertEquals('series\Cultivation::cultivations.part', $this->getFailName($action), 'la série entière se récolte quand ses parts la remplacent');

		// Un calibre d'une autre espèce n'est aucune part de la série : le refus vient avant `build()`.
		$eSizeOther = $this->createSize($this->createPlant());

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation, $eVarietySecond, $eSizeOther)], $this->getInput('3'));
		$this->assertEquals('series\\Cultivation::cultivations.part', $this->getFailName($action), 'un calibre d\'une autre espèce se récolte');

		$eVarietyOther = $this->createVariety($eCultivation['plant']);

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation, $eVarietyOther)], $this->getInput('3'));
		$this->assertEquals('series\Cultivation::cultivations.part', $this->getFailName($action), 'une variété hors de la série se récolte');

		foreach(['abc', '1-2', '-1-0-0', $eCultivation['id'].'-0-0-0'] as $value) {
			[, , $action] = $this->callBatch('doBatchHarvest', [$value], $this->getInput('3'));
			$this->assertTrue($action instanceof \NotExpectedAction, 'la valeur '.$value.' se récolte');
		}

		$eTaskWhole = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTaskWhole, 1.0, \Clock::date());

		// La case de la série et la vignette de l'espèce laissent « Non spécifiée » à part (`getFieldsGrouped()`).
		[$document] = $this->render();

		$this->assertTrue($document->querySelector('#planning-harvest-list input[name="batch[]"][value="'.$this->getValue($eCultivation).'"][data-unspecified]') !== NULL, '« Non spécifiée » n\'est pas marquée à part');
		$this->assertEquals(NULL, $document->querySelector('#planning-harvest-list input[name="batch[]"][value="'.$this->getValue($eCultivation, $eVarietySecond).'"][data-unspecified]'), 'une variété est marquée comme « Non spécifiée »');

		[, , $action] = $this->callBatch('doBatchHarvest', [$this->getValue($eCultivation), $this->getValue($eCultivation, $eVarietySecond)], $this->getInput('3'));
		$this->assertEquals('series\Task::harvestConsistency.unspecified', $this->getFailName($action), '« Non spécifiée » se récolte avec une variété de sa série');

		$this->assertEquals([\Clock::date() => 1.0], $this->getHarvested($eTaskFirst), 'un refus écrit une récolte sur la première variété');
		$this->assertEquals([\Clock::date() => 3.0], $this->getHarvested($eTaskSecond), 'un refus écrit une récolte sur la seconde variété');
		$this->assertEquals([\Clock::date() => 1.0], $this->getHarvested($eTaskWhole), 'un refus écrit une récolte sur la série entière');

		$value = $this->getValue($eCultivation, $eVarietySecond);
		$valueZero = '0'.$eCultivation['id'].'-0'.$eVarietySecond['id'].'-00';

		[, , $action] = $this->callBatch('doBatchHarvest', [$value, $valueZero], $this->getInput('2', ['variety' => $eVarietySecond['id']]));

		if($this->assertTrue($action instanceof \ViewAction, 'la même ligne postée deux fois est refusée : '.implode(', ', $this->getFails($action)))) {
			$this->assertEquals([\Clock::date() => 5.0], $this->getHarvested($eTaskSecond), 'la même ligne postée deux fois reçoit deux parts');
		}

	}

	/**
	 * Le droit se repose sur chaque couple (`HarvestWeekLib::getTasksForBatch()`), après celui de la page : une
	 * intervention existante se refuse par `canWrite`, une neuve par `canCreate`. La page refuse le planning
	 * limité avant d'arriver là (`testRefused`) : le cas appelle donc le Lib sur des couples sondés par un membre
	 * qui a le droit, puis lus par un membre qui ne l'a pas.
	 */
	public function testCouplesRights(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivation, $eVariety] = $this->createCultivationVarieties();
		$eCultivationNew = $this->createCultivationArea($eCultivation['plant'], 30);

		$cCultivationListed = HarvestWeekLib::get($this->eStructure, \Clock::week());

		$cCultivation = new \Collection([
			$eCultivation['id'] => $cCultivationListed[$eCultivation['id']],
			$eCultivationNew['id'] => $cCultivationListed[$eCultivationNew['id']],
		]);

		$cases = [
			'existante' => $this->getValue($eCultivation, $eVariety),
			'neuve' => $this->getValue($eCultivationNew),
		];

		$cTaskCouple = [];

		foreach($cases as $case => $value) {
			$cTaskCouple[$case] = HarvestWeekLib::getBatchProbes($this->eStructure, $cCultivation, HarvestWeekLib::getBatchCouples([$value]));
		}

		$this->assertTrue(HarvestWeekLib::getTasksForBatch($this->eStructure, $cTaskCouple['existante'], \Clock::week(), \Clock::date())->first()->exists(), 'l\'intervention de la variété n\'existe pas');
		$this->assertFalse(HarvestWeekLib::getTasksForBatch($this->eStructure, $cTaskCouple['neuve'], \Clock::week(), \Clock::date())->first()->exists(), 'la série sans récolte a déjà son intervention');

		$eUserOnline = \user\ConnectionLib::getOnline();

		\user\ConnectionLib::setOnline($this->getMemberPlanning());
		\structure\MemberLib::clearCache();

		foreach($cases as $case => $value) {

			try {
				HarvestWeekLib::getTasksForBatch($this->eStructure, $cTaskCouple[$case], \Clock::week(), \Clock::date());
				$action = NULL;
			} catch(\Action $action) {
			}

			$this->assertTrue($action instanceof \NotAllowedAction, 'le planning limité écrit une intervention '.$case);

		}

		\user\ConnectionLib::setOnline($eUserOnline);
		\structure\MemberLib::clearCache();

	}

	/**
	 * Une case par série et la vignette de chaque espèce qui les coche, avec et sans la saisie rapide, avec la barre ; ni
	 * case ni barre sur une semaine à venir ou au planning limité.
	 */
	public function testCheckboxes(): void {

		$ePlant = $this->createPlant();

		$this->createCultivationArea($ePlant, 30);
		$this->createCultivationArea($ePlant, 60);

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			[$document, $data] = $this->render($quick);

			$rows = $document->querySelectorAll('#planning-harvest-list .util-table-row:not(.planning-harvest-section)')->length;

			$this->assertTrue($rows >= 2, $case.', les séries du décor ne sont pas listées');
			$this->assertEquals($rows, $document->querySelectorAll('#planning-harvest-list .util-table-row > .planning-harvest-crop > .planning-harvest-select input[name="batch[]"]')->length, $case.', une série n\'a pas sa case');
			$this->assertEquals(0, $document->querySelectorAll('#planning-harvest-list .planning-harvest-row-sub .planning-harvest-select')->length, $case.', une part a une case');
			$this->assertTrue($document->querySelector('#planning-harvest-plant-'.$ePlant['id'].' [data-planning-harvest-select-plant="'.$ePlant['id'].'"]') !== NULL, $case.', la vignette de l\'espèce ne coche pas ses séries');
			$this->assertTrue($document->querySelector('#planning-board > #planning-harvest-batch [data-ajax-submit="/planning/harvestWeek:batchHarvest"]') !== NULL, $case.', la barre n\'a pas « Récolter »');
			$this->assertEquals('#planning-harvest-batch', $document->getElementById('planning-harvest-list')->getAttribute('data-batch'), $case.', la liste ne nourrit pas la barre');

		}

		[$document] = $this->render(week: $this->getSeason().'-W40');

		$this->assertEquals(NULL, $document->querySelector('.planning-harvest-select, [data-planning-harvest-select-plant], #planning-harvest-batch'), 'une semaine à venir a des cases ou la barre');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->render();

		$this->assertEquals(NULL, $document->querySelector('.planning-harvest-select, [data-planning-harvest-select-plant], #planning-harvest-batch'), 'le planning limité a des cases ou la barre');

	}

	/**
	 * Sur une semaine passée lue en Semaine sans jour ouvert (R-Retours-1210, HD), « Récolter » propose le dimanche,
	 * comme « Nouvelle récolte », et ne renvoie aucun jour ouvert ; un jour ouvert à l'écran reste celui qu'il propose.
	 */
	public function testPanelPastWeekWithoutOpenDay(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$week = $this->getSeason().'-W38';

		$eCultivation = $this->createCultivationArea($this->createPlant(), 30);

		// Prévue la semaine passée, pour que l'écran de cette semaine la liste.
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => [$week]]);
		\series\CultivationLib::update($eCultivation, ['harvestMonthsExpected', 'harvestWeeksExpected']);

		$data = $this->getPanel([$this->getValue($eCultivation)], ['week' => $week]);

		if($data !== NULL) {
			$this->assertEquals(week_date_day($week, 7), $data->date, '« Récolter » sans jour ouvert ne propose pas le dimanche');
			$this->assertEquals(NULL, $data->post['open'], '« Récolter » sans jour ouvert en renvoie un');
		}

		$data = $this->getPanel([$this->getValue($eCultivation)], ['week' => $week, 'open' => week_date_day($week, 3)]);

		if($data !== NULL) {
			$this->assertEquals(week_date_day($week, 3), $data->date, '« Récolter » ne propose pas le jour ouvert à l\'écran');
		}

	}

	/**
	 * Deux séries entières de la même espèce, de 20 et 60 m², cochées sur le même couple : le panneau offre leurs
	 * variétés communes et les calibres de l'espèce, pré-remplis du couple des lignes (HM2). Des couples
	 * différents, ou aucune variété commune sur une espèce sans calibre, n'offrent rien.
	 */
	public function testChoice(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivationSmall, $eCultivationLarge, $eVarietyCommon, , $eSize] = $this->createCultivationsChoice();

		$data = $this->getPanel([$this->getValue($eCultivationSmall), $this->getValue($eCultivationLarge)]);

		if($data !== NULL) {
			$this->assertEquals([$eVarietyCommon['id']], $data->eCultivationChoice['cVarietyChoice']->getIds(), 'le panneau n\'offre pas la seule variété commune aux deux séries');
			$this->assertEquals([$eSize['id']], $data->eCultivationChoice['cSizeChoice']->getIds(), 'le panneau n\'offre pas le calibre de l\'espèce');
			$this->assertTrue($data->eCultivationChoice['eVariety']->empty() and $data->eCultivationChoice['eSize']->empty(), 'le panneau ne se pré-remplit pas du couple des lignes');
		}

		[$eCultivation, $eVarietyFirst, $eVarietySecond] = $this->createCultivationVarieties();

		$data = $this->getPanel([$this->getValue($eCultivation, $eVarietyFirst), $this->getValue($eCultivation, $eVarietySecond)]);

		if($data !== NULL) {
			$this->assertTrue($data->eCultivationChoice->empty(), 'des couples différents offrent un couple à choisir');
		}

		$data = $this->getPanel([$this->getValue($eCultivation, $eVarietySecond)]);

		if($data !== NULL) {
			$this->assertEquals($eVarietySecond['id'], $data->eCultivationChoice['eVariety']['id'], 'une variété cochée seule ne pré-remplit pas le champ');
		}

		$ePlant = $this->createPlant();
		$eCultivationFirst = $this->createCultivationArea($ePlant, 20);
		$eCultivationSecond = $this->createCultivationArea($ePlant, 20);
		$this->createSlice($eCultivationFirst, $this->createVariety($ePlant));
		$this->createSlice($eCultivationSecond, $this->createVariety($ePlant));

		$data = $this->getPanel([$this->getValue($eCultivationFirst), $this->getValue($eCultivationSecond)]);

		if($data !== NULL) {
			$this->assertTrue($data->eCultivationChoice->empty(), 'des séries sans variété commune ni calibre offrent un couple à choisir');
		}

	}

	/**
	 * Le couple choisi redessine les parts sur la surface de la variété (5 et 45 m²) sans toucher à l'unité ni aux
	 * clés, puis l'écriture le vise : chaque série reçoit une intervention de la variété et du calibre, les séries
	 * entières n'en reçoivent rien.
	 */
	public function testChoiceWrite(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivationSmall, $eCultivationLarge, $eVarietyCommon, , $eSize] = $this->createCultivationsChoice();

		$values = [$this->getValue($eCultivationSmall), $this->getValue($eCultivationLarge)];
		$couple = ['variety' => $eVarietyCommon['id'], 'harvestSize' => $eSize['id'], 'distribution' => 'area'];

		$action = $this->executePage('planning/harvestWeek:batchHarvestChoice', $couple + $this->getRoute($values), 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le couple choisi est refusé : '.implode(', ', $this->getFails($action)))) {

			$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

			$this->assertEquals(5.0, $data->cTaskBatch[$values[0]]['distributionArea'], 'la petite série ne se partage pas sur la surface de la variété');
			$this->assertEquals(45.0, $data->cTaskBatch[$values[1]]['distributionArea'], 'la grande série ne se partage pas sur la surface de la variété');
			$this->assertTrue($data->cTaskBatch->contains(fn($eTask) => $eTask['variety']->is($eVarietyCommon) === FALSE or $eTask['harvestSize']->is($eSize) === FALSE) === FALSE, 'une part ne porte pas le couple choisi');
			$this->assertEquals('area', $data->distribution, 'la clé choisie n\'est pas gardée');
			$this->assertFalse($data->quantityChanged, 'la quantité se redessine sans que l\'unité change');

			// La vue se joue : elle redessine les nœuds que les drapeaux désignent, rendus comme le panneau
			// (`testChoiceFragments()`).
			$this->runView('batchHarvestChoice', $action);

		}

		$eTaskWhole = $this->getHarvestTasks($eCultivationSmall)->first();

		[, , $action] = $this->callBatch('doBatchHarvest', $values, $this->getInput('10', $couple));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » sur le couple choisi est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		foreach([[$eCultivationSmall, 1.0], [$eCultivationLarge, 9.0]] as [$eCultivation, $quantity]) {

			$eTask = HarvestWeekLib::getTaskWeek($this->eStructure, $eCultivation, \Clock::week(), $eVarietyCommon, $eSize);

			if($this->assertTrue($eTask->notEmpty(), 'la série de '.$eCultivation['series']['area'].' m² n\'a pas d\'intervention du couple choisi')) {
				$this->assertEquals([\Clock::date() => $quantity], $this->getHarvested($eTask), 'la série de '.$eCultivation['series']['area'].' m² ne reçoit pas sa part du couple choisi');
			}

		}

		$this->assertEquals([\Clock::date() => 1.0], $this->getHarvested($eTaskWhole), 'la série entière reçoit une récolte');

	}

	/**
	 * Le schéma garde le couple posté : une variété que la série ne cultive pas et un calibre d'une autre espèce
	 * sont refusés et rien ne s'écrit. Un couple qui fait passer les séries à des unités différentes est refusé
	 * au choix comme à l'écriture.
	 */
	public function testChoiceRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivationSmall, $eCultivationLarge, $eVarietyCommon, $eVarietyOwn] = $this->createCultivationsChoice();

		$values = [$this->getValue($eCultivationSmall), $this->getValue($eCultivationLarge)];
		$tasks = $this->getHarvestTasks($eCultivationSmall)->count() + $this->getHarvestTasks($eCultivationLarge)->count();

		[, , $action] = $this->callBatch('doBatchHarvest', $values, $this->getInput('10', ['variety' => $eVarietyOwn['id']]));
		$this->assertTrue(in_array('Task::variety.check', $this->getFails($action), TRUE), 'une variété qu\'une série ne cultive pas se récolte');

		[, , $action] = $this->callBatch('doBatchHarvest', $values, $this->getInput('10', ['harvestSize' => $this->createSize($this->createPlant())['id']]));
		$this->assertTrue(in_array('Task::harvestSize.action', $this->getFails($action), TRUE), 'un calibre d\'une autre espèce se récolte');

		$this->assertEquals($tasks, $this->getHarvestTasks($eCultivationSmall)->count() + $this->getHarvestTasks($eCultivationLarge)->count(), 'un couple refusé écrit une intervention');

		// L'intervention entière de la grande série est en kilos, qu'elle garde, mais une intervention neuve prend
		// son unité principale (`HarvestWeekLib::getEntryUnit()`).
		$eCultivationLarge['mainUnit'] = \series\Cultivation::UNIT;
		\series\CultivationLib::update($eCultivationLarge, ['mainUnit']);

		$couple = ['variety' => $eVarietyCommon['id']];

		$action = $this->executePage('planning/harvestWeek:batchHarvestChoice', $couple + $this->getRoute($values), 'GET');
		$this->assertEquals('series\Task::harvestConsistency.check', $this->getFailName($action), 'un couple aux unités différentes se choisit');

		[, , $action] = $this->callBatch('doBatchHarvest', $values, $this->getInput('10', $couple));
		$this->assertEquals('series\Task::harvestConsistency.check', $this->getFailName($action), 'un couple aux unités différentes se récolte');

	}

	/**
	 * Le garde-fou de la réponse ciblée (CLAUDE.md, « Une action ne renvoie que ce qui change ») : chaque nœud que
	 * `batchHarvestChoice` redessine est celui du panneau entier, attributs compris, puisque le refus d'une
	 * propriété se lit sous le `data-wrapper` qui la nomme. La saisie n'y entre pas : elle reste dans la page.
	 */
	public function testChoiceFragments(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		[$eCultivationSmall, $eCultivationLarge] = $this->createCultivationsChoice();

		$data = $this->getPanel([$this->getValue($eCultivationSmall), $this->getValue($eCultivationLarge)]);

		if($data === NULL) {
			return;
		}

		$uiTaskBatch = new TaskBatchUi();

		$panel = $this->parse($uiTaskBatch->getHarvest($data->eStructure, $data->page, $data->week, $data->post, $data->cTaskBatch, $data->days, $data->date, $data->distributions, $data->distribution, $data->ePlant, $data->unit, $data->tasksByUnit, $data->eTaskStock, $data->cUserTime, $data->eCultivationChoice)->body);

		$fragments = [
			'.planning-batch-time-lines' => $uiTaskBatch->getHarvestLines(new \util\FormUi(), $data->cTaskBatch, $data->distributions, $data->distribution, $data->unit, $data->eStructure->hasTime(), FALSE),
			'[data-planning-batch-harvest-distribution]' => $uiTaskBatch->getHarvestDistribution(new \util\FormUi(), FALSE, $data->distributions, $data->distribution),
			'[data-wrapper="'.HarvestEntryUi::getQuantityWrapper('harvestMore').'"]' => $uiTaskBatch->getHarvestQuantity(new \util\FormUi(), $data->ePlant, $data->unit, $data->tasksByUnit),
			'[data-planning-harvest-stock="planning-batch-harvest-stock"]' => new HarvestEntryUi()->getStock(new \util\FormUi(), 'planning-batch-harvest-stock', $data->eTaskStock),
		];

		foreach($fragments as $selector => $html) {

			$node = $panel->querySelector($selector);

			if($this->assertTrue($node !== NULL, 'le panneau n\'a pas de nœud '.$selector)) {
				$this->assertEquals($this->getOuter($node), $this->getOuter($this->parse($html)->querySelector($selector)), 'la réponse ciblée ne redessine pas '.$selector.' comme le panneau');
			}

		}

	}

	private function parse(string $html): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
	}

	/**
	 * Le nœud tel qu'il s'écrit, sans les identifiants de champ, tirés à chaque rendu (`FormUi`).
	 */
	private function getOuter(?\Dom\Element $node): string {
		return ($node === NULL) ? '' : preg_replace('/field-[0-9a-f]+/', 'field', $node->ownerDocument->saveHtml($node));
	}

	/**
	 * Deux séries entières de la même espèce, de 20 et 60 m², chacune récoltée aujourd'hui (1 kg) : une variété
	 * commune qui couvre le quart de la petite et les trois quarts de la grande (5 et 45 m²), une variété propre à
	 * la grande, et un calibre de l'espèce.
	 *
	 * @return array [la petite série, la grande, la variété commune, la variété propre, le calibre]
	 */
	private function createCultivationsChoice(): array {

		$ePlant = $this->createPlant();

		$eCultivationSmall = $this->createCultivationArea($ePlant, 20);
		$eCultivationLarge = $this->createCultivationArea($ePlant, 60);

		$eVarietyCommon = $this->createVariety($ePlant);
		$eVarietyOwn = $this->createVariety($ePlant);

		$this->createSlice($eCultivationSmall, $eVarietyCommon, 25.0);
		$this->createSlice($eCultivationLarge, $eVarietyCommon, 75.0);
		$this->createSlice($eCultivationLarge, $eVarietyOwn, 25.0);

		foreach([$eCultivationSmall, $eCultivationLarge] as $eCultivation) {
			$this->createHarvest($this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date()), 1.0, \Clock::date());
		}

		return [$eCultivationSmall, $eCultivationLarge, $eVarietyCommon, $eVarietyOwn, $this->createSize($ePlant)];

	}

	/**
	 * Une culture de l'espèce, prévue en récolte cette semaine pour que l'écran la liste, sur une série de la
	 * surface donnée.
	 */
	private function createCultivationArea(\plant\Plant $ePlant, int $area): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries('Série de '.$area.' m² '.uniqid()), $ePlant);

		// La surface d'une série se calcule de ses emplacements, que le décor ne pose pas : elle s'écrit ici telle
		// que le calcul l'aurait laissée, seule base que lit le partage.
		$eCultivation['series']['area'] = $area;
		\series\SeriesLib::update($eCultivation['series'], ['area']);

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => [\Clock::week()]]);
		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

		return $eCultivation;

	}

	/**
	 * Une série de 40 m² à deux variétés, de 10 et 30 m², chacune récoltée aujourd'hui (1 kg, puis 3 kg) sur son
	 * intervention : la liste de la semaine range ces récoltes en deux parts.
	 *
	 * @return array [la culture, les deux variétés, leurs interventions]
	 */
	private function createCultivationVarieties(): array {

		$eCultivation = $this->createCultivationArea($this->createPlant(), 40);

		$eVarietyFirst = $this->createVariety($eCultivation['plant']);
		$eVarietySecond = $this->createVariety($eCultivation['plant']);

		$this->createSlice($eCultivation, $eVarietyFirst, 25.0);
		$this->createSlice($eCultivation, $eVarietySecond, 75.0);

		$tasks = [];

		foreach([[$eVarietyFirst, 1.0], [$eVarietySecond, 3.0]] as [$eVariety, $quantity]) {

			$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
			$this->ventilate($eTask, ['variety' => $eVariety]);
			$this->createHarvest($eTask, $quantity, \Clock::date());

			$tasks[] = $eTask;

		}

		return [$eCultivation, $eVarietyFirst, $eVarietySecond, ...$tasks];

	}

	/**
	 * La valeur de la case d'une ligne, telle que l'écran la poste.
	 */
	private function getValue(\series\Cultivation $eCultivation, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): string {
		return HarvestWeekLib::getBatchValue($eCultivation, $eVariety, $eSize);
	}

	private function getRoute(array $ids): array {

		return [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'ids' => $ids,
		];

	}

	private function getInput(string $quantity, array $input = []): array {

		return $input + [
			'date' => \Clock::date(),
			'harvestMore' => $quantity,
		];

	}

	/**
	 * Les données du panneau « Récolter » des Récoltes.
	 */
	private function getPanel(array $ids, array $input = []): ?\stdClass {

		$action = $this->executePage('planning/harvestWeek:batchHarvest', $input + $this->getRoute($ids), 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Récolter » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

	}

	/**
	 * « Récolter » des Récoltes, rendu par sa vue.
	 */
	private function callBatch(string $route, array $ids, array $input = []): array {

		$action = $this->executePage('planning/harvestWeek:'.$route, $input + $this->getRoute($ids), 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView($route, $action);

		return [$data, $template->formatInstructions(), $action];

	}

	private function getHarvestTasks(\series\Cultivation $eCultivation): \Collection {

		return \series\Task::model()
			->select(\series\Task::getSelection())
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->getCollection();

	}

	/**
	 * Le récolté d'une intervention par jour, sans les jours revenus à zéro.
	 */
	private function getHarvested(\series\Task $eTask): array {

		$harvested = [];

		foreach(\series\Harvest::model()
			->select(['date', 'quantity'])
			->whereTask($eTask)
			->getCollection() as $eHarvest) {
			$harvested[$eHarvest['date']] = round(($harvested[$eHarvest['date']] ?? 0.0) + $eHarvest['quantity'], 3);
		}

		return array_filter($harvested);

	}

	/**
	 * Le temps de la personne connectée sur une intervention, par jour.
	 */
	private function getTimes(\series\Task $eTask): array {

		$times = [];

		foreach(\series\Timesheet::model()
			->select(['date', 'time'])
			->whereTask($eTask)
			->whereUser(\user\ConnectionLib::getOnline())
			->getCollection() as $eTimesheet) {
			$times[$eTimesheet['date']] = ($times[$eTimesheet['date']] ?? 0.0) + $eTimesheet['time'];
		}

		return $times;

	}

	/**
	 * Le refus levé d'un nom seul, sans `FailWatch` : `throw new \FailAction('<nom>')`, que `getFails()` ne lit pas.
	 */
	private function getFailName(?\Action $action): ?string {

		if(($action instanceof \FailAction) === FALSE) {
			return NULL;
		}

		$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);

		return is_string($fail) ? $fail : NULL;

	}

}
?>
