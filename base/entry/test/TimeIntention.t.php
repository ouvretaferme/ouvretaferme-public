<?php
namespace entry;

/**
 * Le temps seul sur une intervention de la semaine (lot 2b) : « fertilisation <espèce> 2 h ». Ce qu'il
 * propose, ce qu'il demande, ce qu'il écrit, et ce que l'annulation défait.
 *
 * Aucun refus éprouvé ici ne passe par un `rollBack()`, que la transaction du test emporterait
 * (`EntryAtomicity.t.php`).
 */
class TimeIntentionTest extends EntryFixtureTest {

	/**
	 * Une intervention de fertilisation, ou de l'action `$action`, sur la culture, à faire à la semaine
	 * décalée de `$shift`, ou faite cette semaine-là.
	 */
	private function createTaskFertilisation(\series\Cultivation $eCultivation, string $shift = '+0 DAY', string $status = \series\Task::TODO, string $action = ACTION_FERTILISATION): \series\Task {

		$date = date('Y-m-d', \Clock::shift($shift));

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction($action),
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => $status,
			'plannedWeek' => toWeek($date),
			'plannedDate' => $date,
			'doneWeek' => ($status === \series\Task::DONE) ? toWeek($date) : NULL,
			'doneDate' => ($status === \series\Task::DONE) ? $date : NULL,
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * Une intervention de culture hors série et sans espèce, à faire aujourd'hui.
	 */
	private function createTaskWithoutPlant(string $action = ACTION_FERTILISATION): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $this->getAction($action),
			'category' => $this->getCategoryCulture(),
			'description' => NULL,
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => \Clock::date(),
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()` : le décor fixe telles quelles la semaine et le jour prévus, le statut et la catégorie,
		// que `build()` ne prend que d'un formulaire posté (`planned`, comme `TimeIntentionLib::buildTask()`). Il y
		// vérifierait l'action et l'espèce, qu'une intervention sans espèce satisfait hors récolte
		// (`Task::properties()`, `plant`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * La question d'un ajout au planning et l'espèce qu'elle porte, sans refus.
	 */
	private function getAddQuestion(array $reading): array {

		$this->assertEquals(NULL, $reading['refusal'], 'l\'ajout au planning ne refuse rien');

		return ($reading['question'] === NULL) ? [] : [$reading['question']['name'], $reading['question']['ePlant']['id'] ?? NULL];

	}

	private function getTimeText(string $rest = ''): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '.$rest;
	}

	private function getTime(\series\Task $eTask): float {
		return \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), \Clock::date());
	}

	private function getStatus(\series\Task $eTask): string {

		return \series\Task::model()
			->select(['status'])
			->whereId($eTask['id'])
			->get()['status'];

	}

	/**
	 * Sans verbe, l'action et l'espèce suffisent : une seule intervention s'écrit sans question, et reste
	 * à faire.
	 */
	public function testOneTaskWrites(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getTimeText('2 h'));

		$this->assertEquals('time', $reading['intention'], 'sans verbe, la phrase se lit comme un temps');
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eTask['id'], $reading['task']['id']);
		$this->assertEquals(120, $reading['confirmation']['minutes']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(2.0, $this->getTime($eTask), 'les deux heures sont posées sur l\'intervention');
		$this->assertEquals(\series\Task::TODO, $this->getStatus($eTask), 'l\'intervention reste à faire');
		$this->assertTrue($reading['eHarvest']->empty(), 'aucune récolte n\'est écrite');

		$this->assertEquals('time', $this->read('temps '.$this->getTimeText('1 h'))['intention'], 'le verbe « temps » reste admis');

	}

	/**
	 * Deux interventions de la même action sur la même espèce : une ligne par intervention, et la réponse
	 * désigne celle qui reçoit le temps.
	 */
	public function testSeveralAsk(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivationB = $this->createCultivationInPhase(TRUE, 'Série B');

		$eTaskA = $this->createTaskFertilisation($eCultivationA);
		$eTaskB = $this->createTaskFertilisation($eCultivationB, status: \series\Task::DONE);
		$eTaskLater = $this->createTaskFertilisation($eCultivationA, '+21 DAY');

		$reading = $this->read($this->getTimeText('30 min'));

		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals('task', $reading['question']['name']);
		$this->assertEquals([$eTaskA['id'], $eTaskB['id']], $reading['question']['cTask']->getIds());

		$reading = $this->read($this->getTimeText('30 min'), ['task' => $eTaskB['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eTaskB['id'], $reading['task']['id']);

		$reading = $this->read($this->getTimeText('30 min'), ['task' => $eTaskA['id'] + 1000000]);

		$this->assertEquals(EntryLib::ASK, $reading['outcome'], 'une réponse étrangère aux candidates repose la question');

		$reading = $this->read($this->getTimeText('30 min'), ['task' => $eTaskLater['id']]);

		$this->assertEquals(EntryLib::ASK, $reading['outcome'], 'une intervention de la structure hors de la semaine ne reçoit pas le temps');

	}

	/**
	 * Le jour dit : ni dans le futur, ni au-delà de `EntrySetting::PAST_DAYS` jours ; et une durée de plus de
	 * 24 heures.
	 */
	public function testBounds(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$days = EntrySetting::PAST_DAYS;

		$this->assertEquals(['reason' => 'future'], $this->read($this->getTimeText('1 h demain '))['refusal']);
		$this->assertEquals(['reason' => 'past', 'days' => $days], $this->read($this->getTimeText('1 h '.DateComplementUi::getToken(date('Y-m-d', \Clock::shift('-'.($days + 1).' DAYS'))).' '))['refusal']);
		$this->assertEquals(['reason' => 'duration'], $this->read($this->getTimeText('25 h '))['refusal']);

	}

	/**
	 * Une structure qui ne suit pas le temps refuse le temps seul dès la lecture.
	 */
	public function testUntracked(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$eConfiguration = $this->eStructure->conf();
		$time = $eConfiguration['time'];

		try {
			$eConfiguration['time'] = FALSE;
			$this->assertEquals(['reason' => 'untracked'], $this->read($this->getTimeText('1 h '))['refusal']);
		} finally {
			$eConfiguration['time'] = $time;
		}

	}

	/**
	 * Une action de plusieurs mots se lit entière, et sans verbe la quantité reste à la récolte.
	 */
	public function testNames(): void {

		if($this->requireAction(ACTION_SEMIS_DIRECT) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eTask = $this->createTaskFertilisation($eCultivation, action: ACTION_SEMIS_DIRECT);

		$reading = $this->read($this->getAction(ACTION_SEMIS_DIRECT)['name'].' '.$this->getPlant()['name'].' 45 min ');

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals($eTask['id'], $reading['task']['id']);
		$this->assertEquals(45, $reading['confirmation']['minutes']);

		$this->assertEquals('harvest', $this->read($this->getPlant()['name'].' 12 kg ')['intention'], 'l\'espèce et une quantité restent une récolte');

	}

	/**
	 * Une action sans intervention de la semaine pour ce qui la suit reste un temps, et non une récolte de
	 * l'espèce nommée. L'action seule, sans intervention de la semaine, est éprouvée par `ActionTypedTest`.
	 */
	public function testNone(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getTimeText('2 h '));

		$this->assertEquals('time', $reading['intention'], 'sans intervention, la phrase reste un temps');
		$this->assertEquals('series', $reading['question']['name'], 'l\'espèce sans intervention propose de l\'ajouter au planning');

		$this->assertEquals('harvest', $this->read($this->getPlant()['name'].' 2 h ')['intention'], 'l\'espèce seule reste une récolte');

	}

	/**
	 * Une action nommée seule porte toutes ses interventions de la semaine : « fertilisation 1 h » demande
	 * laquelle, et une espèce qu'aucune ne porte est refusée.
	 */
	public function testActionAlone(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTaskA = $this->createTaskFertilisation($this->createCultivationInPhase(TRUE, 'Série A'));
		$eTaskB = $this->createTaskFertilisation($this->createCultivationInPhase(TRUE, 'Série B'));

		$action = $this->getAction(ACTION_FERTILISATION)['name'];

		$reading = $this->read($action.' 1 h ');

		$this->assertEquals('task', $reading['question']['name'], 'l\'action seule demande l\'intervention');
		$this->assertEquals([$eTaskA['id'], $eTaskB['id']], $reading['question']['cTask']->getIds());

		$ePlant = $this->createPlant('Zzautre');

		$this->assertEquals(['series', $ePlant['id']], $this->getAddQuestion($this->read($action.' '.$ePlant['name'].' 1 h ')), 'une espèce qu\'aucune ne porte s\'ajoute au planning');

		$cTaskObject = TimeIntentionLib::getObjects($this->eStructure, EntryLib::getEmptyReading());

		$this->assertFalse(in_array($action, TimeIntentionLib::getListed($cTaskObject)->getColumn('name'), TRUE), 'l\'action seule n\'est pas proposée');

	}

	/**
	 * L'espèce encore en frappe après l'action n'est pas un mot de trop : la liste propose les interventions
	 * de l'action dont l'espèce commence ainsi, et sans aucune, le refus dit que la semaine n'en a pas.
	 * L'action seule, l'espèce entière et la durée en frappe ne refusent rien.
	 */
	public function testTypingPlant(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$action = $this->getAction(ACTION_FERTILISATION)['name'];
		$plant = $this->getPlant()['name'];

		$cElement = new \Collection();
		$reading = EntryLib::read($this->eStructure, $action.' '.mb_substr($plant, 0, -3), cElement: $cElement);

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals(NULL, $reading['refusal'], 'l\'espèce en frappe n\'est pas refusée');
		$this->assertTrue($reading['object']->empty(), 'l\'action seule ne se retient pas sur l\'espèce en frappe');
		$this->assertEquals([$action.' '.mb_strtolower($plant)], EntryLib::findObjects(TimeIntentionLib::getListed($cElement), $reading['pending'])->getColumn('name'));

		$reading = $this->read($action);

		$this->assertEquals(NULL, $reading['refusal'], 'l\'action seule n\'est pas refusée');

		$reading = $this->read($action.' '.$plant);

		$this->assertEquals(NULL, $reading['refusal'], 'l\'espèce entière n\'est pas refusée');
		$this->assertEquals($eTask['id'], $reading['task']['id']);

		$reading = $this->read($action.' '.$plant.' 1');

		$this->assertEquals(NULL, $reading['refusal'], 'la durée en frappe n\'est pas refusée');
		$this->assertEquals($eTask['id'], $reading['task']['id']);

		$ePlant = $this->createPlant('Zzautre');

		$this->assertEquals(['series', $ePlant['id']], $this->getAddQuestion($this->read($action.' '.mb_substr($ePlant['name'], 0, -3))), 'une espèce en frappe sans intervention cette semaine');

	}

	/**
	 * Après une action qui a aussi une intervention sans espèce, l'espèce en frappe, liaison comprise, se
	 * cherche comme le reste ; le début du nom de plusieurs espèces sans intervention attend la suite de
	 * la frappe, puis refuse une fois le mot fini.
	 */
	public function testTypingPlantAfterAction(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskWithoutPlant();
		$this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$action = $this->getAction(ACTION_FERTILISATION)['name'];
		$plant = $this->getPlant()['name'];

		foreach([$action.' ', $action.' de '] as $prefix) {

			$cElement = new \Collection();
			$reading = EntryLib::read($this->eStructure, $prefix.mb_substr($plant, 0, 3), cElement: $cElement);

			$this->assertEquals(NULL, $reading['refusal'], 'l\'espèce en frappe après « '.$prefix.'»');
			$this->assertEquals([$action.' '.mb_strtolower($plant)], EntryLib::findObjects(TimeIntentionLib::getListed($cElement), $reading['pending'])->getColumn('name'));

		}

		$ePlant = $this->createPlant('Zzautre');

		foreach([' ', ' de '] as $link) {
			$this->assertEquals(['series', $ePlant['id']], $this->getAddQuestion($this->read($action.$link.$ePlant['name'].' 1 h ')), 'une espèce sans intervention de l\'action, après « '.$link.'»');
		}

		$this->createPlant('Zzchou');
		$this->createPlant('Zzchou');

		$reading = $this->read($action.' zzchou');

		$this->assertEquals(NULL, $reading['refusal'], 'plusieurs espèces en frappe');
		$this->assertEquals(EntryLib::ASK, $reading['outcome']);

		$this->assertEquals(['reason' => 'none', 'name' => $action.' zzchou'], $this->read($action.' zzchou 1 h ')['refusal'], 'plusieurs espèces, le mot fini');

		// L'espèce après une liaison, l'action proposée seule ayant aussi une intervention sans espèce.
		$this->assertEquals(EntryLib::WRITE, $this->read($action.' de '.$plant.' 1 h ')['outcome'], 'l\'espèce après une liaison');
		$this->assertEquals(NULL, $this->read($action.' de '.$plant)['refusal'], 'l\'espèce après une liaison, en frappe');

		// Le début du nom de deux espèces qui ont chacune une intervention, le mot fini : la question.
		foreach([$this->createPlant('Zzbette'), $this->createPlant('Zzbette')] as $ePlantBette) {
			$this->createTaskFertilisation($this->createCultivation($this->createSeries('Série B'), $ePlantBette));
		}

		$reading = $this->read($action.' zzbette 1 h ');

		$this->assertEquals(NULL, $reading['refusal'], 'plusieurs espèces avec une intervention, le mot fini');
		$this->assertEquals(2, $reading['question']['cTask']->count());

		// Deux espèces dont seul le premier mot est partagé : la seconde, sans intervention, dément le début
		// que la première propose.
		$ePlantListed = $this->createPlant('Zzrave');
		$this->createTaskFertilisation($this->createCultivation($this->createSeries('Série R'), $ePlantListed));
		$ePlantOther = $this->createPlant('Zzrave');

		foreach(['', 'temps '] as $verb) {
			$this->assertEquals(['series', $ePlantOther['id']], $this->getAddQuestion($this->read($verb.$action.' '.$ePlantOther['name'].' 1 h ')), 'une espèce de deux mots démentie, « '.$verb.'»');
		}

		// Une espèce dont le nom porte une liaison.
		$ePlantLinked = $this->createPlant('Zzpomme de terre');

		$this->assertEquals(['series', $ePlantLinked['id']], $this->getAddQuestion($this->read($action.' zzpomme de t')), 'une espèce à liaison en frappe');
		$this->assertEquals(['series', $ePlantLinked['id']], $this->getAddQuestion($this->read('temps '.$action.' '.$ePlantLinked['name'].' 1 h ')), 'une espèce à liaison');

	}

	/**
	 * Le mot en frappe se complète quand toutes les interventions proposées le continuent de la même
	 * façon, accents compris, et pas quand elles divergent ou qu'il est déjà entier.
	 */
	public function testCompletion(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$ePlantAlpha = $this->createPlant('Zzalpha');
		$ePlantBeta = $this->createPlant('Zzbeta');

		$this->createTaskFertilisation($this->createCultivation($this->createSeries('Série Alpha'), $ePlantAlpha));
		$this->createTaskFertilisation($this->createCultivation($this->createSeries('Série Beta'), $ePlantBeta));

		$action = $this->getAction(ACTION_FERTILISATION)['name'];

		$getCompletion = function(string $text): ?array {

			$cElement = new \Collection();
			$reading = EntryLib::read($this->eStructure, $text, cElement: $cElement);

			return EntryLib::getCompletion($text, $reading, EntryLib::findObjects($cElement, $reading['pending']));

		};

		$getFirstValue = function(string $text): string {

			$cElement = new \Collection();
			$reading = EntryLib::read($this->eStructure, $text, cElement: $cElement);
			$cElement = EntryLib::findObjects($cElement, $reading['pending']);
			$completion = EntryLib::getCompletion($text, $reading, $cElement);

			// Comme la page, la liste ne montre que ce que l'intention en garde (`getShown()`).
			$cElement = EntryLib::getIntentions()[$reading['intention']]::getShown($this->eStructure, $reading, $cElement);

			return EntryUi::getResults($this->eStructure, $reading, $cElement, [], completion: $completion)[0]['value'];

		};

		$this->assertEquals(['word' => $action, 'suffix' => mb_strtolower(mb_substr($action, 4))], $getCompletion(mb_substr($action, 0, 4)), 'l\'action commune aux interventions');
		$this->assertEquals(['word' => 'zzalpha', 'suffix' => 'lpha'], $getCompletion($action.' zza'), 'l\'espèce d\'une seule intervention');
		$this->assertEquals(NULL, $getCompletion($action.' zz'), 'deux espèces divergent');
		$this->assertEquals(NULL, $getCompletion($action.' zzalpha'), 'le mot est entier');
		$this->assertEquals(NULL, $getCompletion(mb_substr($action, 0, 4).' '), 'aucun mot en frappe');
		$this->assertEquals(NULL, $getCompletion('temps zza'), 'un mot à l\'intérieur du nom');

		$this->assertEquals('completion', $getFirstValue(mb_substr($action, 0, 4)), 'la ligne de tête complète au doigt');
		$this->assertTrue($getFirstValue($action.' zza') !== 'completion', 'une suggestion seule n\'est pas doublée');

		$ePlantAccent = $this->createPlant('Zzépinard');
		$this->createTaskFertilisation($this->createCultivation($this->createSeries('Série Accent'), $ePlantAccent));

		$this->assertEquals(['word' => 'zzépinard', 'suffix' => 'pinard'], $getCompletion($action.' zze'), 'la fin garde ses accents');

	}

	/**
	 * Un mot de trop après la durée est refusé, avec ou sans verbe.
	 */
	public function testUnclaimed(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getTimeText('2 h blabla '));

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals('unclaimed', $reading['refusal']['reason']);
		$this->assertEquals('unclaimed', $this->read('temps '.$this->getTimeText('2 h blabla '))['refusal']['reason']);

	}

	/**
	 * Seules comptent les interventions de la semaine du jour dit : une intervention planifiée trois
	 * semaines plus loin n'est pas proposée, une récolte non plus.
	 */
	public function testWeekOnly(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->createTaskFertilisation($eCultivation, '+21 DAY');
		$this->createTaskTodo($eCultivation, '+0 DAY');

		$cTaskObject = TimeIntentionLib::getObjects($this->eStructure, EntryLib::getEmptyReading());

		$names = $cTaskObject->getColumn('name');

		$this->assertFalse(in_array($this->getAction(ACTION_FERTILISATION)['name'].' '.mb_strtolower($this->getPlant()['name']), $names, TRUE), 'la fertilisation d\'une autre semaine est écartée');
		$this->assertFalse(in_array($this->getAction(ACTION_RECOLTE)['name'].' '.mb_strtolower($this->getPlant()['name']), $names, TRUE), 'la récolte est écartée');

	}

	/**
	 * Annuler retire le temps posé et laisse l'intervention ; le ticket ne sert qu'une fois, à son auteur, dans sa
	 * structure.
	 */
	public function testCancel(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskFertilisation($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getTimeText('1 h 30'));

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(1.5, $this->getTime($eTask));

		$eTaskCancellable = TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket']);

		$this->assertEquals($eTask['id'], $eTaskCancellable['id'], 'le jeton désigne l\'intervention');

		$eStructureOther = new \structure\Structure(['id' => $this->eStructure['id'] + 1]);

		$this->assertTrue(TimeIntentionLib::getCancellable($eStructureOther, $reading['undo']['ticket'])->empty(), 'le jeton ne vaut que pour sa structure');

		$eUser = \user\ConnectionLib::getOnline();

		try {
			\user\ConnectionLib::setOnline(new \user\User(['id' => $eUser['id'] + 1]));
			$this->assertTrue(TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket'])->empty(), 'le jeton ne vaut que pour son auteur');
		} finally {
			\user\ConnectionLib::setOnline($eUser);
		}

		$cancellation = TimeIntentionLib::prepareCancel($this->eStructure, $eTaskCancellable);

		$this->assertFalse($cancellation['created']);
		$this->assertTrue(TimeIntentionLib::cancel($cancellation), 'la première annulation passe');
		$this->assertFalse(TimeIntentionLib::cancel($cancellation), 'la seconde ne fait rien, le ticket est pris');

		$this->assertEquals(0.0, $this->getTime($eTask), 'le temps est retiré');
		$this->assertEquals(\series\Task::TODO, $this->getStatus($eTask), 'l\'intervention reste');

		$this->assertTrue(TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket'])->empty(), 'le ticket pris ne désigne plus rien');
		$this->assertTrue(TimeIntentionLib::getCancellable($this->eStructure, 'zz')->empty(), 'un jeton mal formé ne désigne rien');

	}

	/**
	 * Un temps sur une intervention hors série sans espèce, comme un désherbage de toute la parcelle :
	 * l'écriture et son annulation disent aux écrans une intervention sans culture ni espèce.
	 */
	public function testTaskWithoutPlant(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskWithoutPlant(ACTION_FERTILISATION);

		$reading = $this->read($this->getAction(ACTION_FERTILISATION)['name'].' 1 h ');

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'l\'action seule désigne l\'intervention sans espèce');
		$this->assertEquals($eTask['id'], $reading['task']['id']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(1.0, $this->getTime($eTask));

		$cancellation = TimeIntentionLib::prepareCancel($this->eStructure, TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket']));

		$this->assertTrue(TimeIntentionLib::cancel($cancellation));

		// Ce que rendent les vues de l'écriture et de l'annulation, sous un gestionnaire qui change tout avertissement
		// en exception : une clé absente rend NULL, la valeur même qu'on attend, et ne se voit qu'ainsi.
		set_error_handler(fn(int $level, string $message, string $file, int $line) => throw new \ErrorException($message, 0, $level, $file, $line));

		try {
			$eventWrite = TimeIntentionUi::getWriteEvent($this->eStructure, $reading);
			$eventCancel = TimeIntentionUi::getCancelEvent($this->eStructure, $cancellation);
			$warning = NULL;
		} catch(\ErrorException $e) {
			$warning = $e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';
		} finally {
			restore_error_handler();
		}

		if($this->assertEquals(NULL, $warning, 'l\'écriture et l\'annulation se disent sans avertissement') === FALSE) {
			return;
		}

		$this->assertEquals($eTask['id'], $eventWrite['task']);
		$this->assertEquals(NULL, $eventWrite['plant'], 'l\'écriture dit une intervention sans espèce');
		$this->assertEquals(NULL, $eventWrite['cultivation']);
		$this->assertEquals(NULL, $eventCancel['plant'], 'l\'annulation aussi');

	}

	/**
	 * « <espèce> 30 min » sur la récolte : le temps seul, sans récolte, et Annuler emporte l'intervention
	 * que la saisie a créée.
	 */
	public function testHarvestTimeAlone(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getText('30 min '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getHarvest($eCultivation);

		$this->assertEquals(1, $cTask->count(), 'l\'intervention de récolte est créée');
		$this->assertEquals(0.5, $this->getTime($cTask->first()));
		$this->assertTrue($reading['eHarvest']->empty(), 'sans quantité, aucune ligne de récolte');

		$cancellation = TimeIntentionLib::prepareCancel($this->eStructure, TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket']));

		$this->assertTrue($cancellation['created'], 'l\'intervention est reconnue comme créée par la saisie');
		$this->assertTrue(TimeIntentionLib::cancel($cancellation));
		$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'l\'intervention créée part avec son temps');

	}

	/**
	 * Une espèce sans intervention de l'action cette semaine : la question propose ses cultures des saisons
	 * du jour, la saison du jour d'abord, sans celles d'une série close ni d'une autre espèce. La réponse
	 * construit l'intervention, à faire au jour dit, et l'écriture la crée avec le temps.
	 */
	public function testAddOnSeries(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eCultivationNext = $this->createCultivationNextSeasonInPhase('Série suivante');
		$eCultivationPrevious = $this->createCultivationPreviousSeason('Série précédente');

		$eCultivationClosed = $this->createCultivationPreviousSeason('Série close');
		// Sans `build()` : la clôture d'une série passe par son formulaire, que le décor n'a pas à rejouer.
		\series\Series::model()->update($eCultivationClosed['series'], ['status' => \series\Series::CLOSED]);

		$this->createCultivation($this->createSeries('Série d\'une autre espèce'), $this->createPlant('Zzautre'));

		$reading = $this->read($this->getTimeText('2 h '));

		$this->assertEquals('series', $reading['question']['name']);
		$this->assertEquals($this->getPlant()['id'], $reading['question']['ePlant']['id']);
		$this->assertEquals([$eCultivation['id'], $eCultivationNext['id'], $eCultivationPrevious['id']], $reading['question']['cCultivation']->getIds(), 'les cultures de l\'espèce, celle de la saison du jour d\'abord, puis la suivante, sans la série close');

		$this->assertFalse($reading['question']['cCultivation'][$eCultivation['id']]['nameWithSeason'], 'la série de la saison du jour ne dit pas sa saison');
		$this->assertTrue($reading['question']['cCultivation'][$eCultivationNext['id']]['nameWithSeason'], 'celle d\'une autre saison la dit');

		$this->assertEquals('series', $this->read($this->getTimeText('2 h '), ['addCultivation' => $eCultivationClosed['id']])['question']['name'], 'une culture hors de la question ne vaut pas réponse');

		$eCultivationForeign = \series\Cultivation::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->get();

		if($eCultivationForeign->notEmpty()) {
			$this->assertEquals('series', $this->read($this->getTimeText('2 h '), ['addCultivation' => $eCultivationForeign['id']])['question']['name'], 'la culture d\'une autre structure ne vaut pas réponse');
		}

		$reading = $this->read($this->getTimeText('2 h '), ['addCultivation' => $eCultivation['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertTrue($reading['confirmation']['created'], 'l\'intervention est à créer');
		$this->assertFalse($reading['task']->exists());
		$this->assertEquals($eCultivation['id'], $reading['task']['cultivation']['id']);
		$this->assertFalse($reading['task']['nameWithSeason'], 'l\'intervention sur la série du jour ne dit pas sa saison');

		$this->assertTrue($this->read($this->getTimeText('2 h '), ['addCultivation' => $eCultivationNext['id']])['task']['nameWithSeason'], 'celle sur une série d\'une autre saison la dit');

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		$this->assertTrue($eTask->exists(), 'l\'intervention est créée');
		$this->assertEquals($this->getAction(ACTION_FERTILISATION)['id'], $eTask['action']['id']);
		$this->assertEquals($eCultivation['series']['id'], $eTask['series']['id']);
		$this->assertEquals($eCultivation['id'], $eTask['cultivation']['id']);
		$this->assertEquals($this->getPlant()['id'], $eTask['plant']['id']);
		$this->assertEquals(\series\Task::TODO, $this->getStatus($eTask), 'elle est à faire');
		$this->assertEquals(\Clock::date(), $eTask['plannedDate'], 'planifiée au jour dit');
		$this->assertEquals(2.0, $this->getTime($eTask), 'avec le temps saisi');
		$this->assertFalse($eTask['nameWithSeason'], 'relue après écriture, elle le dit encore pour la pastille');

		$reading = $this->read($this->getTimeText('1 h '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la saisie suivante trouve l\'intervention ajoutée');
		$this->assertEquals($eTask['id'], $reading['task']['id']);

	}

	/**
	 * « Hors série » : seule réponse d'une espèce sans culture, l'intervention porte l'espèce sans série.
	 */
	public function testAddNoSeries(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant('Zzautre');
		$text = $this->getAction(ACTION_FERTILISATION)['name'].' '.$ePlant['name'].' 45 min ';

		$reading = $this->read($text);

		$this->assertEquals('series', $reading['question']['name']);
		$this->assertTrue($reading['question']['cCultivation']->empty(), 'une espèce sans culture n\'a que « Hors série »');

		$reading = $this->read($text, ['addNoSeries' => $ePlant['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		$ePlantOther = $this->createPlant('Zzautre');

		$this->assertEquals('series', $this->read($this->getAction(ACTION_FERTILISATION)['name'].' '.$ePlantOther['name'].' 45 min ', ['addNoSeries' => $ePlant['id']])['question']['name'], '« Hors série » ne vaut que pour l\'espèce à laquelle il répondait');

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		$this->assertTrue($eTask['series']->empty(), 'hors série');
		$this->assertFalse($eTask['nameWithSeason'], 'sans série, pas de saison à dire');
		$this->assertTrue($eTask['cultivation']->empty());
		$this->assertEquals($ePlant['id'], $eTask['plant']['id'], 'l\'espèce est celle tapée');
		$this->assertEquals(\series\Task::TODO, $this->getStatus($eTask));
		$this->assertEquals(0.75, $this->getTime($eTask));

	}

	/**
	 * Une action hors de la catégorie Culture ne porte pas d'espèce : l'espèce garde le refus, sans question.
	 */
	public function testAddOutsideCulture(): void {

		$eCategory = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_COMMERCIALISATION);

		if($this->assertTrue($eCategory->notEmpty(), 'la structure a une catégorie Commercialisation') === FALSE) {
			return;
		}

		$eAction = new \work\Action([
			'name' => 'Zzlivraison '.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10),
			'structure' => $this->eStructure,
			'categories' => [$eCategory['id']],
		]);

		// Sans `build()` : le décor fixe le nom et la catégorie, que le formulaire d'action vérifierait.
		\work\ActionLib::create($eAction);

		$ePlant = $this->createPlant('Zzautre');

		$reading = $this->read($eAction['name'].' '.$ePlant['name'].' 1 h ');

		$this->assertEquals(NULL, $reading['question']);
		$this->assertEquals(['reason' => 'none', 'name' => $eAction['name'].' '.mb_strtolower($ePlant['name'])], $reading['refusal']);

	}

	/**
	 * Annuler emporte l'intervention ajoutée avec son temps, sauf si elle a été retouchée depuis : seul
	 * le temps part alors.
	 */
	public function testAddCancel(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getTimeText('1 h '), ['addCultivation' => $eCultivation['id']]);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		$cancellation = TimeIntentionLib::prepareCancel($this->eStructure, TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket']));

		$this->assertTrue($cancellation['created'], 'l\'intervention est reconnue comme ajoutée par la saisie');
		$this->assertTrue(TimeIntentionLib::cancel($cancellation));
		$this->assertTrue(\series\Task::model()->whereId($eTask['id'])->exists() === FALSE, 'l\'intervention ajoutée part avec son temps');

		$reading = $this->read($this->getTimeText('1 h '), ['addCultivation' => $eCultivation['id']]);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['eTask'];

		// Sans `build()` : le décor retouche la description comme le ferait la fiche de l'intervention.
		\series\Task::model()->update($eTask, ['description' => 'Retouchée']);

		$cancellation = TimeIntentionLib::prepareCancel($this->eStructure, TimeIntentionLib::getCancellable($this->eStructure, $reading['undo']['ticket']));

		$this->assertFalse($cancellation['created'], 'retouchée, elle n\'est plus celle de la saisie');
		$this->assertTrue(TimeIntentionLib::cancel($cancellation));
		$this->assertTrue(\series\Task::model()->whereId($eTask['id'])->exists(), 'l\'intervention retouchée reste');
		$this->assertEquals(0.0, $this->getTime($eTask), 'son temps part');

	}

}
?>
