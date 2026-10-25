<?php
namespace planning;

/**
 * « Récolter » sur un lot de la Semaine et du Jour (DG-1, `TaskBatchLib::prepareHarvests()`) : la quantité et
 * le temps de chaque personne (HM) se partagent par la même clé, chaque part s'écrit par `\series\TaskLib::writeHarvestForWeek()`,
 * puis le poids des caisses, le stock et son étoile. Tout se vérifie avant la première écriture : un lot refusé
 * n'écrit rien.
 *
 * Lancement : dev/test package=planning file=TaskBatchHarvest
 */
class TaskBatchHarvestTest extends HarvestFixtureTest {

	private bool $timeBefore;

	public function setUp(): void {

		parent::setUp();

		$this->timeBefore = $this->getConfiguration()['time'];

	}

	public function tearDown(): void {

		$this->getConfiguration()['time'] = $this->timeBefore;

		parent::tearDown();

	}

	/**
	 * Deux séries de 30 et 60 m² : 9 kg à la surface donnent 3 et 6 kg, et 1h30 donne 30 minutes et 1 heure. Le
	 * panneau propose les trois clés, et le geste ferme son panneau.
	 */
	public function testArea(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$cTask = $this->createHarvestsByArea($ePlant, [30, 60]);

		$data = $this->getPanel($cTask);

		if($data !== NULL) {
			$this->assertEquals(['area', 'fair'], $data->distributions, 'deux séries mesurées n\'offrent pas la surface et les parts égales');
			$this->assertEquals(\series\Task::KG, $data->unit, 'des récoltes en kilos ne se saisissent pas en kilos');
		}

		[$data, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'area', 'time' => [\user\ConnectionLib::getOnline()['id'] => '1.5']]));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('batchHarvest', $data->gesture, '« Récolter » ne ferme pas son panneau');

		$eTaskSmall = $cTask->first();
		$eTaskLarge = $cTask->last();

		$this->assertEquals([\Clock::date() => 3.0], $this->getHarvested($eTaskSmall), 'la série de 30 m² ne reçoit pas le tiers de la récolte');
		$this->assertEquals([\Clock::date() => 6.0], $this->getHarvested($eTaskLarge), 'la série de 60 m² ne reçoit pas les deux tiers de la récolte');
		$this->assertEquals([\Clock::date() => 0.5], $this->getTimes($eTaskSmall), 'la série de 30 m² ne reçoit pas le tiers du temps');
		$this->assertEquals([\Clock::date() => 1.0], $this->getTimes($eTaskLarge), 'la série de 60 m² ne reçoit pas les deux tiers du temps');

	}

	/**
	 * Deux personnes : chacune se partage par la clé, à la minute, sur ses propres lignes de temps. 1h30 et 45
	 * minutes sur 30 et 60 m² donnent 30 minutes et 1 heure, puis 15 et 30 minutes.
	 */
	public function testTeam(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eUserOnline = \user\ConnectionLib::getOnline();
		$eUserBruno = $this->getMemberPlanning();

		$cTask = $this->createHarvestsByArea($this->createPlant(), [30, 60]);

		$data = $this->getPanel($cTask);

		if($data !== NULL) {
			$this->assertTrue($data->cUserTime->notEmpty(), 'le panneau ne montre aucune personne');
			$this->assertEquals($eUserOnline['id'], $data->cUserTime->first()['id'], 'le panneau ne montre pas la personne connectée en tête');
			$this->assertTrue($data->cUserTime->offsetExists($eUserBruno['id']), 'le panneau ne propose pas une personne présente dans la semaine');
		}

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'area', 'time' => [$eUserOnline['id'] => '1.5', $eUserBruno['id'] => '0.75']]));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » pour deux est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$eUserOnline['id'] => 0.5, $eUserBruno['id'] => 0.25], $this->getTimeByUser($cTask->first()), 'la série de 30 m² ne reçoit pas le tiers du temps de chacun');
		$this->assertEquals([$eUserOnline['id'] => 1.0, $eUserBruno['id'] => 0.5], $this->getTimeByUser($cTask->last()), 'la série de 60 m² ne reçoit pas les deux tiers du temps de chacun');

	}

	/**
	 * Plus de 24 heures dans la journée d'une personne se refusent sous sa ligne, et une personne d'une autre
	 * structure sous le temps de l'équipe : rien ne s'écrit, ni la récolte ni le temps de l'autre personne.
	 */
	public function testTeamRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eUserOnline = \user\ConnectionLib::getOnline();
		$eUserBruno = $this->getMemberPlanning();

		$cTask = $this->createHarvestsByArea($this->createPlant(), [30, 60]);

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('3', ['distribution' => 'area', 'time' => [$eUserBruno['id'] => '5']]));

		if($this->assertTrue($action instanceof \ViewAction, 'les 5 heures de Bruno sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'area', 'time' => [$eUserOnline['id'] => '1', $eUserBruno['id'] => '20']]));

		$this->assertEquals(['Timesheet::time.tooLong' => 'time['.$eUserBruno['id'].']'], $this->getFailWrappers($action), '25 heures pour Bruno ne sont pas refusées sous sa ligne');

		$eUserStranger = \user\User::model()
			->select('id')
			->whereEmail('decor.f2.admin@example.org')
			->get();

		if($this->assertDecorPresent($eUserStranger->notEmpty(), 'le décor porte le compte decor.f2.admin@example.org')) {

			[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'area', 'time' => [$eUserOnline['id'] => '1', $eUserStranger['id'] => '0.5']]));

			$this->assertEquals(['Timesheet::user.check' => 'timeTeam'], $this->getFailWrappers($action), 'le temps d\'un membre d\'une autre structure n\'est pas refusé');

		}

		$this->assertEquals([\Clock::date() => 1.0], $this->getHarvested($cTask->first()), 'un refus a laissé écrire la récolte');
		$this->assertEquals([$eUserBruno['id'] => round(100 / 60, 4)], $this->getTimeByUser($cTask->first()), 'un refus a laissé écrire un temps');

	}

	/**
	 * Le stock choisi reçoit la quantité entière et l'étoile le mémorise pour l'espèce et l'unité. Un produit
	 * d'une autre espèce, que le champ ne propose pas, est refusé sans rien écrire.
	 */
	public function testStock(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$cTask = $this->createHarvestsByArea($ePlant, [30, 60]);

		$eProduct = $this->createProduct('Stock du lot', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);
		$eProductOther = $this->createProduct('Stock d\'une autre espèce', $this->getUnit(\series\Task::KG), 10.0, ePlant: $this->createPlant());

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'fair', 'stock' => $eProductOther['id']]));

		$this->assertEquals(['Task::stock.check'], $this->getFails($action), 'un stock d\'une autre espèce n\'est pas refusé');

		foreach($cTask as $eTask) {
			$this->assertEquals([], $this->getHarvested($eTask), 'le lot refusé pour son stock a écrit une récolte');
		}

		$this->assertEquals(10.0, $this->getStock($eProductOther), 'le stock refusé a bougé');

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'fair', 'stock' => $eProduct['id'], 'stockRemember' => '1']));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » avec un stock est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(19.0, $this->getStock($eProduct), 'le stock ne reçoit pas les 9 kg récoltés');

		$eTaskReference = \series\TaskLib::getById($cTask->first()['id']);

		$this->assertEquals($eProduct['id'], \product\StockLib::getBookmark($eTaskReference)['id'] ?? NULL, 'l\'étoile ne mémorise pas le stock choisi');

	}

	/**
	 * Un produit d'une autre structure qui porte l'espèce du lot est refusé sans rien écrire : les stocks
	 * compatibles se lisent par l'espèce, et l'espèce d'une structure s'emprunte depuis une autre dont on est membre.
	 */
	public function testStockForeign(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$cTask = $this->createHarvestsByArea($ePlant, [30, 60]);

		$eProductForeign = $this->createProduct('Stock d\'une autre structure', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		// Posé par le modèle, sans `build()` : un produit naît dans la structure connectée, aucun champ n'en choisit
		// une autre. Le cas est celui d'un membre des deux structures qui crée le produit dans l'autre, que le décor n'a pas.
		\product\Product::model()->update($eProductForeign, ['structure' => $this->getStructureForeign()]);

		[$cProductStock] = HarvestStockLib::getByReference(HarvestStockLib::getReference($cTask->first(), \series\Task::KG));

		if($this->assertDecorPresent($cProductStock->findById($eProductForeign['id'], default: new \product\Product())->notEmpty(), 'le produit de l\'autre structure parmi les stocks compatibles') === FALSE) {
			return;
		}

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'fair', 'stock' => $eProductForeign['id']]));

		$this->assertEquals(['Task::stock.check'], $this->getFails($action), 'un stock d\'une autre structure n\'est pas refusé');

		foreach($cTask as $eTask) {
			$this->assertEquals([], $this->getHarvested($eTask), 'le lot refusé pour son stock a écrit une récolte');
		}

		$this->assertEquals(10.0, $this->getStock($eProductForeign), 'le stock de l\'autre structure a bougé');

	}

	/**
	 * Une sélection qui n'est pas faite de récoltes de la même espèce dans la même unité est refusée par le
	 * serveur, même si la barre ne propose pas « Récolter » : une plantation, une autre espèce, une récolte en
	 * bottes à côté d'une récolte en kilos.
	 */
	public function testMixedRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());
		$eTaskHarvest = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

		foreach([
			'une plantation' => [$this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date()), 'series\\Task::actions.check'],
			'une autre espèce' => [$this->createTaskHarvest($this->createCultivation(ePlant: $this->createPlant()), \Clock::week(), \Clock::date()), 'series\\Task::harvestConsistency.check'],
			'une autre unité' => [$this->createTaskHarvestBunch($eCultivation), 'series\\Task::harvestConsistency.check'],
		] as $case => [$eTaskOther, $fail]) {

			$cTask = new \Collection([$eTaskHarvest['id'] => $eTaskHarvest, $eTaskOther['id'] => $eTaskOther]);

			[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('4', ['distribution' => 'fair']));

			$this->assertEquals($fail, $this->getFailName($action), 'une sélection avec '.$case.' n\'est pas refusée');

		}

		$this->assertEquals([], $this->getHarvested($eTaskHarvest), 'une sélection refusée a écrit une récolte');

	}

	/**
	 * Le planning limité ne saisit pas de récolte (`Task::canWrite()`), comme dans Récoltes : refusé, le lot
	 * n'écrit rien.
	 */
	public function testLimitedRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cTask = $this->createHarvestsByArea($this->createPlant(), [30, 60]);

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'fair']));

		$this->assertFalse($action instanceof \ViewAction, '« Récolter » est accepté au planning limité');

		foreach($cTask as $eTask) {
			$this->assertEquals([], $this->getHarvested($eTask), 'le planning limité a écrit une récolte');
		}

	}

	/**
	 * Un jour d'une autre semaine, un jour à venir de la semaine et une quantité vide sont refusés sans rien
	 * écrire. L'horloge est un mercredi, pour qu'un jour à venir existe dans la semaine.
	 */
	public function testDayRefused(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->setClock(week_date_day(\Clock::week(), 3).' 10:00:00');

		$cTask = $this->createHarvestsByArea($this->createPlant(), [30, 60]);

		foreach([
			'un jour d\'une autre semaine' => [['date' => toDate(\Clock::date().' - 7 DAYS')], 'Timesheet::date.week'],
			'un jour à venir' => [['date' => toDate(\Clock::date().' + 1 DAY')], 'Timesheet::date.future'],
			'une quantité vide' => [['harvestMore' => ''], 'Task::harvestMore.missing'],
			'une quantité négative' => [['harvestMore' => '-2'], 'Task::harvestMore.missing'],
		] as $case => [$input, $fail]) {

			[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $input + $this->getInput('9', ['distribution' => 'fair']));

			$this->assertTrue(in_array($fail, $this->getFails($action), TRUE), $case.' n\'est pas refusé par '.$fail.' : '.implode(', ', $this->getFails($action)));

		}

		foreach($cTask as $eTask) {
			$this->assertEquals([], $this->getHarvested($eTask), 'un lot refusé a écrit une récolte');
		}

	}

	/**
	 * Une semaine à venir n'a aucun jour où récolter : la barre ne propose pas « Récolter », et la route refuse.
	 */
	public function testFutureWeek(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$week = toWeek(\Clock::date().' + 7 DAYS');

		$eTask = $this->createTaskHarvest($this->createCultivation(ePlant: $this->createPlant()), $week);
		$cTask = new \Collection([$eTask['id'] => $eTask]);

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, ['week' => $week] + $this->getInput('2'));

		$this->assertTrue($action instanceof \NotExpectedAction, 'une semaine à venir accepte « Récolter »');
		$this->assertEquals([], $this->getHarvested($eTask), 'une semaine à venir a écrit une récolte');

		foreach([
			'la semaine en cours' => [\Clock::week(), TRUE],
			'une semaine à venir' => [$week, FALSE],
		] as $case => [$weekCase, $expected]) {

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.new TaskBatchUi()->get($this->eStructure, 'taskWeek', $weekCase, [], new \Collection()).'</body>', LIBXML_NOERROR);

			$this->assertEquals($expected, $document->querySelector('.planning-batch-harvest') !== NULL, $case.', « Récolter » '.($expected ? 'manque à' : 'est proposé dans').' la barre');

		}

	}

	/**
	 * Une récolte à faire qui n'a encore rien reçu n'a pas d'unité : à côté d'une récolte en kilos, elle prend les
	 * kilos et sa part, et passe faite sans qu'aucun temps ne soit saisi, comme dans Récoltes.
	 */
	public function testUnitEmpty(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());

		$eTaskDone = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$eTaskTodo = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		if($this->assertDecorPresent(\series\TaskLib::getById($eTaskTodo['id'])['harvestUnit'] === NULL, 'une récolte à faire sans quantité n\'a pas d\'unité') === FALSE) {
			return;
		}

		$cTask = new \Collection([$eTaskDone['id'] => $eTaskDone, $eTaskTodo['id'] => $eTaskTodo]);

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('4', ['distribution' => 'fair']));

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte sans unité à côté d\'une récolte en kilos est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTaskTodo = \series\TaskLib::getById($eTaskTodo['id']);

		$this->assertEquals([\Clock::date() => 2.0], $this->getHarvested($eTaskTodo), 'la récolte sans unité ne reçoit pas sa part');
		$this->assertEquals(\series\Task::KG, $eTaskTodo['harvestUnit'], 'la récolte sans unité ne prend pas les kilos des autres');
		$this->assertEquals(\series\Task::DONE, $eTaskTodo['status'], 'la récolte à faire qui reçoit une quantité ne passe pas faite');
		$this->assertEquals([], $this->getTimes($eTaskTodo), 'la récolte reçoit un temps qui n\'a pas été saisi');

	}

	/**
	 * Quand aucune récolte n'a d'unité, elle se choisit : une saisie en bottes s'écrit en bottes.
	 */
	public function testUnitChosen(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$cTask = new \Collection([$eTask['id'] => $eTask]);

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('5', ['harvestUnit' => \series\Task::BUNCH]));

		if($this->assertTrue($action instanceof \ViewAction, 'une unité choisie est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(\series\Task::BUNCH, \series\TaskLib::getById($eTask['id'])['harvestUnit'], 'l\'unité choisie n\'est pas écrite');
		$this->assertEquals([\Clock::date() => 5.0], $this->getHarvested($eTask), 'la quantité n\'est pas écrite dans l\'unité choisie');

	}

	/**
	 * 2 caisses de 6 kg écrivent 12 kg, partagés entre les séries, et l'espèce garde le poids de la caisse. En
	 * bottes, les caisses sont refusées.
	 */
	public function testCrates(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$cTask = $this->createHarvestsByArea($ePlant, [30, 60]);

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('2', ['distribution' => 'area', 'crate' => '1', 'crateWeight' => '6']));

		if($this->assertTrue($action instanceof \ViewAction, 'une récolte en caisses est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eTaskSmall = $cTask->first();
		$eTaskLarge = $cTask->last();

		$this->assertEquals([\Clock::date() => 4.0], $this->getHarvested($eTaskSmall), '2 caisses de 6 kg ne donnent pas 4 kg à la série de 30 m²');
		$this->assertEquals([\Clock::date() => 8.0], $this->getHarvested($eTaskLarge), '2 caisses de 6 kg ne donnent pas 8 kg à la série de 60 m²');
		$this->assertEquals(6.0, \plant\PlantLib::getById($ePlant['id'])['crateWeight'], 'le poids de la caisse n\'est pas posé sur l\'espèce');

		$eTask = $this->createTask($this->createCultivation(ePlant: $this->createPlant()), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[, , $action] = $this->callBatch('doBatchHarvest', new \Collection([$eTask['id'] => $eTask]), $this->getInput('2', ['harvestUnit' => \series\Task::BUNCH, 'crate' => '1', 'crateWeight' => '6']));

		$this->assertEquals(['Task::harvestUnit.crate'], $this->getFails($action), 'des caisses en bottes ne sont pas refusées');

	}

	/**
	 * Sans suivi du temps, la récolte s'écrit et le temps posté ne se lit pas.
	 */
	public function testUntracked(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$this->getConfiguration()['time'] = FALSE;

		$cTask = $this->createHarvestsByArea($this->createPlant(), [30, 60]);

		[, , $action] = $this->callBatch('doBatchHarvest', $cTask, $this->getInput('9', ['distribution' => 'area', 'time' => [\user\ConnectionLib::getOnline()['id'] => '1.5']]));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » sans suivi du temps est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		foreach($cTask as $eTask) {
			$this->assertTrue($this->getHarvested($eTask) !== [], 'une série ne reçoit pas sa récolte sans suivi du temps');
			$this->assertEquals([], $this->getTimes($eTask), 'un temps est écrit sans suivi du temps');
		}

	}

	/**
	 * Le Jour : la même route, rendue par la vue de ses gestes.
	 */
	public function testDay(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cTask = $this->createHarvestsByArea($this->createPlant(), [30, 60]);

		[$data, , $action] = $this->callDay('doBatchHarvest', ['ids' => $cTask->getIds()] + $this->getInput('6', ['distribution' => 'fair']));

		if($this->assertTrue($action instanceof \ViewAction, '« Récolter » du Jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('batchHarvest', $data->gesture, '« Récolter » du Jour ne ferme pas son panneau');

		foreach($cTask as $eTask) {
			$this->assertEquals([\Clock::date() => 3.0], $this->getHarvested($eTask), '« Récolter » du Jour ne partage pas la récolte');
		}

	}

	/**
	 * Des récoltes faites aujourd'hui en kilos, une par série de la surface donnée, sur la même espèce.
	 */
	private function createHarvestsByArea(\plant\Plant $ePlant, array $areas): \Collection {

		$cTask = new \Collection();

		foreach($areas as $area) {

			$eCultivation = $this->createCultivation($this->createSeries('Série de '.$area.' m²'), $ePlant);

			// La surface d'une série se calcule de ses emplacements, que le décor ne pose pas : elle s'écrit
			// ici telle que le calcul l'aurait laissée, seule base que lit le partage.
			$eCultivation['series']['area'] = $area;
			\series\SeriesLib::update($eCultivation['series'], ['area']);

			$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
			$cTask[$eTask['id']] = $eTask;

		}

		return $cTask;

	}

	/**
	 * Une récolte faite aujourd'hui en bottes, sur la culture donnée.
	 */
	private function createTaskHarvestBunch(\series\Cultivation $eCultivation): \series\Task {

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

		$eTask['harvestUnit'] = \series\Task::BUNCH;
		\series\TaskLib::update($eTask, ['harvestUnit']);

		return $eTask;

	}

	private function getInput(string $quantity, array $input = []): array {

		return $input + [
			'date' => \Clock::date(),
			'harvestMore' => $quantity,
		];

	}

	/**
	 * Les données du panneau « Récolter » de la Semaine.
	 */
	private function getPanel(\Collection $cTask): ?\stdClass {

		$action = $this->executePage('planning/taskWeek:batchHarvest', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'ids' => $cTask->getIds(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Récolter » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

	}

	/**
	 * Un geste de lot de la Semaine, rendu par sa vue `doBatch`.
	 */
	private function callBatch(string $route, \Collection $cTask, array $input = []): array {

		$action = $this->executePage('planning/taskWeek:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'ids' => $cTask->getIds(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doBatch', $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

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
	 * Le temps de chaque personne sur une intervention, toutes dates confondues.
	 */
	private function getTimeByUser(\series\Task $eTask): array {

		$times = [];

		foreach(\series\Timesheet::model()
			->select(['user', 'time'])
			->whereTask($eTask)
			->getCollection() as $eTimesheet) {
			$times[$eTimesheet['user']['id']] = round(($times[$eTimesheet['user']['id']] ?? 0.0) + $eTimesheet['time'], 4);
		}

		return $times;

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

	private function getStock(\product\Product $eProduct): ?float {

		return \product\ProductLib::getById($eProduct['id'])['stock'];

	}

	/**
	 * La configuration que lit `Structure::hasTime()`, mémorisée par structure (`Structure::conf()`) : le réglage
	 * s'y pose seulement, et `tearDown()` le remet, comme dans `HarvestTimeUntrackedTest`.
	 */
	private function getConfiguration(): \structure\Configuration {

		return \structure\StructureLib::getById($this->eStructure['id'])->conf();

	}

}
?>
