<?php
namespace entry;

/**
 * La coche d'une intervention de culture prévue (lot S4a) : « fertilisation <espèce> » puis Entrée. Ce qu'elle
 * retient, ce qu'elle demande, ce qu'elle écrit, et ce que l'annulation défait. Avec une durée, la phrase reste
 * le temps du lot 2b. Rien de prévu, elle crée l'intervention faite sur la série demandée (lot S4b).
 *
 * Aucun refus éprouvé ici ne passe par un `rollBack()`, que la transaction du test emporterait
 * (`EntryAtomicity.t.php`).
 */
class DoneIntentionTest extends EntryFixtureTest {

	/**
	 * Une fertilisation à faire sur la culture, prévue à la semaine décalée de `$weeks` du jour dit.
	 */
	private function createTaskPlanned(\series\Cultivation $eCultivation, int $weeks = 0, string $action = ACTION_FERTILISATION): \series\Task {
		return $this->createTask($eCultivation, $action, \series\Task::TODO, toWeek(\Clock::date().' '.(($weeks < 0) ? '- ' : '+ ').abs($weeks).' WEEKS'));
	}

	private function getDoneText(string $rest = ''): string {
		return $this->getAction(ACTION_FERTILISATION)['name'].' '.$this->getPlant()['name'].' '.$rest;
	}

	private function getTaskRead(\series\Task $eTask): \series\Task {

		return \series\Task::model()
			->select(['status', 'doneWeek', 'doneDate', 'plannedWeek'])
			->whereId($eTask['id'])
			->get();

	}

	/**
	 * Sans verbe ni durée, une seule intervention prévue se coche sans question, faite aujourd'hui, sans temps.
	 */
	public function testOneTaskWrites(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE), 2);

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('done', $reading['intention'], 'sans durée, la phrase coche');
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eTask['id'], $reading['task']['id']);
		$this->assertEquals(NULL, $reading['confirmation']['minutes'], 'la coche n\'écrit aucun temps');

		EntryLib::write($this->eStructure, $reading);

		$eTaskRead = $this->getTaskRead($eTask);

		$this->assertEquals(\series\Task::DONE, $eTaskRead['status']);
		$this->assertEquals(\Clock::date(), $eTaskRead['doneDate'], 'faite le jour dit, pas à la semaine prévue');
		$this->assertEquals(\Clock::week(), $eTaskRead['doneWeek']);
		$this->assertEquals(0.0, \series\TimesheetLib::getTimeByDate($eTask, \user\ConnectionLib::getOnline(), \Clock::date()), 'aucun temps n\'est posé');
		$this->assertEquals('done', $reading['undo']['intention']);

		$this->assertEquals('done', $this->read('fait '.$this->getDoneText())['intention'], 'le verbe « fait » est admis');

	}

	/**
	 * Avec une durée, la phrase reste le temps de 2b, et le temps de 2c quand rien n'est à la semaine.
	 */
	public function testDurationIsTime(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getDoneText('1 h '));

		$this->assertEquals('time', $reading['intention'], 'une durée fait du temps');
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eTask['id'], $reading['task']['id']);
		$this->assertEquals(60, $reading['confirmation']['minutes']);

		// La durée que pose une ligne de la liste part dans le préfixe en minutes (`entry.js`).
		$this->assertEquals($reading['confirmation']['minutes'], $this->read($this->getDoneText('60 min'))['confirmation']['minutes'], 'le jeton de durée se lit comme la durée tapée');

	}

	/**
	 * Une durée ne se propose que sur une intervention que le temps propose, de la semaine ou en retard :
	 * ailleurs, le temps ne la trouverait pas et la phrase changerait d'intervention.
	 */
	public function testDurationOfferedWhereTimeIs(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTask = $this->createTaskPlanned($eCultivation);

		$reading = $this->read($this->getDoneText());
		$this->assertEquals($eTask['id'], $reading['task']['id']);
		$this->assertTrue($reading['task']['timed'], 'prévue cette semaine');

		$this->assertEquals('time', $this->read($this->getDoneText('1 h '))['intention']);

		\series\TaskLib::delete($eTask);

		$eTaskLate = $this->createTaskPlanned($eCultivation, -2);

		$reading = $this->read($this->getDoneText());
		$this->assertEquals($eTaskLate['id'], $reading['task']['id']);
		$this->assertTrue($reading['task']['timed'], 'prévue il y a deux semaines, en retard, que le temps propose (HN-B7)');

		$readingTime = $this->read($this->getDoneText('1 h '));
		$this->assertTrue($readingTime['task']->is($eTaskLate), 'le temps la voit');

		\series\TaskLib::delete($eTaskLate);

		$eTaskOld = $this->createTaskPlanned($eCultivation, -6);

		$reading = $this->read($this->getDoneText());
		$this->assertEquals($eTaskOld['id'], $reading['task']['id']);
		$this->assertFalse($reading['task']['timed'], 'prévue il y a six semaines, au-delà de ce que le temps propose');

		$readingTime = $this->read($this->getDoneText('1 h '));
		$this->assertFalse($readingTime['task']->is($eTaskOld), 'le temps ne la voit pas');

	}

	/**
	 * Plusieurs prévues : le champ demande laquelle, la plus proche du jour dit en tête, et la réponse
	 * désigne celle qui se coche.
	 */
	public function testSeveralAsk(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTaskFar = $this->createTaskPlanned($eCultivation, -5);
		$eTaskNear = $this->createTaskPlanned($eCultivation, -1);
		$eTaskAfter = $this->createTaskPlanned($eCultivation, 2);

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('done', $reading['intention']);
		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals('task', $reading['question']['name']);
		$this->assertEquals([$eTaskNear['id'], $eTaskAfter['id'], $eTaskFar['id']], $reading['question']['cTask']->getIds(), 'la plus proche en tête');

		$reading = $this->read($this->getDoneText(), ['task' => $eTaskFar['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eTaskFar['id'], $reading['task']['id']);

		$reading = $this->read($this->getDoneText(), ['task' => $eTaskFar['id'] + 1000000]);

		$this->assertEquals(EntryLib::ASK, $reading['outcome'], 'une réponse étrangère aux candidates repose la question');

	}

	/**
	 * La question ne propose que les `QUESTION_LIMIT` plus proches du jour dit.
	 */
	public function testQuestionLimit(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTaskFar = $this->createTaskPlanned($eCultivation, -DoneIntentionLib::WEEKS_BEFORE);

		for($task = 0; $task < DoneIntentionLib::QUESTION_LIMIT; $task++) {
			$this->createTaskPlanned($eCultivation, -1);
		}

		$cTask = $this->read($this->getDoneText())['question']['cTask'];

		$this->assertEquals(DoneIntentionLib::QUESTION_LIMIT, $cTask->count());
		$this->assertFalse($cTask->offsetExists($eTaskFar['id']), 'la plus lointaine sort de la question');

	}

	/**
	 * La fenêtre va de `WEEKS_BEFORE` semaines avant à `WEEKS_AFTER` semaines après le jour dit, bornes
	 * comprises. Au-delà, la coche n'a rien à cocher et demande où créer l'intervention faite.
	 */
	public function testWindow(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->createTaskPlanned($eCultivation, -DoneIntentionLib::WEEKS_BEFORE - 1);
		$this->createTaskPlanned($eCultivation, DoneIntentionLib::WEEKS_AFTER + 1);

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('done', $reading['intention'], 'hors de la fenêtre, la coche demande où la créer');
		$this->assertEquals('series', $reading['question']['name']);

		$this->assertEquals('series', $this->read('fait '.$this->getDoneText())['question']['name'], 'avec le verbe aussi');

		$eTaskBefore = $this->createTaskPlanned($eCultivation, -DoneIntentionLib::WEEKS_BEFORE);
		$eTaskAfter = $this->createTaskPlanned($eCultivation, DoneIntentionLib::WEEKS_AFTER);

		$this->assertEquals([$eTaskAfter['id'], $eTaskBefore['id']], $this->read($this->getDoneText())['question']['cTask']->getIds(), 'les bornes sont comprises');

	}

	/**
	 * Une série close, une intervention faite, une autre espèce ou une autre action ne se cochent pas.
	 */
	public function testExcluded(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE or $this->requireAction(ACTION_SEMIS_DIRECT) === FALSE) {
			return;
		}

		$eCultivationClosed = $this->createCultivation($this->createSeries('Série close'), $this->getPlant());
		$this->createTaskPlanned($eCultivationClosed);
		$this->closeSeries($eCultivationClosed['series']);

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createTaskPlanned($eCultivation, 0, ACTION_SEMIS_DIRECT);
		$this->createTask($eCultivation, ACTION_FERTILISATION, \series\Task::DONE, \Clock::week(), date('Y-m-d', \Clock::shift('-1 DAY')));

		$reading = $this->read('fait '.$this->getDoneText());

		$this->assertEquals('series', $reading['question']['name'], 'ni série close, ni autre action, ni faite un autre jour : rien à cocher');
		$this->assertFalse($reading['question']['cCultivation']->offsetExists($eCultivationClosed['id']), 'la série close ne se propose pas');

	}

	/**
	 * Une récolte prévue ne se coche pas : « récolte » est le verbe de la récolte, et le temps n'en lit pas l'action.
	 */
	public function testHarvestExcluded(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE), 0, ACTION_RECOLTE);

		$reading = $this->read('fait '.$this->getAction(ACTION_RECOLTE)['name'].' '.$this->getPlant()['name']);

		$this->assertDiffer(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::TODO, $this->getTaskRead($eTask)['status']);

	}

	/**
	 * Le jour dit date la coche, dans sa semaine, et le futur est refusé.
	 */
	public function testDate(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE), -3);

		$yesterday = date('Y-m-d', \Clock::shift('-1 DAY'));

		$reading = $this->read($this->getDoneText('hier '));

		$this->assertEquals('done', $reading['intention']);
		$this->assertEquals($yesterday, $reading['confirmation']['date']);

		EntryLib::write($this->eStructure, $reading);

		$eTaskRead = $this->getTaskRead($eTask);

		$this->assertEquals($yesterday, $eTaskRead['doneDate']);
		$this->assertEquals(toWeek($yesterday), $eTaskRead['doneWeek']);

		$this->assertEquals(['reason' => 'future'], $this->read('fait '.$this->getDoneText('demain '))['refusal']);

		// L'intervention de la semaine, nommée par son objet, se coche encore à un jour d'une autre semaine.
		$eTaskWeek = $this->createTaskPlanned($this->createCultivationInPhase(TRUE, 'Série de la semaine'));

		$reading = $this->read($this->getDoneText(DateComplementUi::getToken(date('Y-m-d', \Clock::shift('-14 DAYS'))).' '));

		$this->assertEquals(NULL, $reading['refusal'], 'un jour d\'une autre semaine ne refuse pas l\'objet');
		$this->assertEquals($eTaskWeek['id'], $reading['task']['id']);

	}

	/**
	 * Déjà faite le jour dit : la coche la montre sans rien écrire, son id reposté en réponse ne la recoche pas,
	 * et une durée pose du temps dessus.
	 */
	public function testAlreadyDone(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eTask = $this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$reading = $this->read($this->getDoneText());
		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('done', $reading['intention'], 'la déjà faite se montre');
		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals(NULL, $reading['question']);
		$this->assertEquals($eTask['id'], $reading['task']['id']);

		$this->assertEquals(EntryLib::ASK, $this->read($this->getDoneText(), ['task' => $eTask['id']])['outcome'], 'son id reposté ne la recoche pas');

		$reading = $this->read($this->getDoneText('30 min '), ['task' => $eTask['id']]);

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'le temps se pose sur la faite');
		$this->assertEquals($eTask['id'], $reading['task']['id']);

		// Sous le verbe, la durée dite reste à la coche : ses puces s'y ajoutent (HN-B20, 25/10).
		$reading = $this->read('fait '.$this->getDoneText('1 h '));

		$this->assertEquals('done', $reading['intention'], 'le verbe garde la coche');
		$this->assertTrue($reading['task']['timed'], 'la faite propose encore du temps');
		$this->assertEquals([15 => 75, 30 => 90, 60 => 120], $reading['task']['additions'], 'chaque puce ajoute à l\'heure dite');

	}

	/**
	 * Une structure qui ne suit pas le temps coche aussi.
	 */
	public function testUntracked(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createTaskPlanned($this->createCultivationInPhase(TRUE));

		$eConfiguration = $this->eStructure->conf();
		$time = $eConfiguration['time'];

		try {
			$eConfiguration['time'] = FALSE;
			$this->assertEquals(EntryLib::WRITE, $this->read($this->getDoneText())['outcome']);
		} finally {
			$eConfiguration['time'] = $time;
		}

	}

	/**
	 * Annuler remet à faire ; une intervention qui a bougé depuis la coche reste faite, et l'annulation se
	 * refuse.
	 */
	public function testCancel(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$eTask = $this->createTaskPlanned($eCultivation, 1);

		$reading = $this->read($this->getDoneText());
		EntryLib::write($this->eStructure, $reading);

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertEquals(\series\Task::DONE, $cancellation['cTask']->first()['status']);

		$eTaskRead = $this->getTaskRead($eTask);

		$this->assertEquals(\series\Task::TODO, $eTaskRead['status'], 'remise à faire');
		$this->assertEquals(NULL, $eTaskRead['doneDate']);
		$this->assertEquals(toWeek(\Clock::date().' + 1 WEEK'), $eTaskRead['plannedWeek'], 'à sa semaine prévue');

		$this->assertTrue($this->getCancelFail($reading['undo']), 'le ticket ne se rejoue pas');

		$reading = $this->read($this->getDoneText());
		EntryLib::write($this->eStructure, $reading);

		// Retouchée ailleurs après la coche, faite une autre semaine. Sans `TaskLib` ni `build()` : le décor pose
		// directement l'état qu'une autre voie aurait laissé, seule compte la condition de `updateTodoIfLeft()`.
		\series\Task::model()->update($eTask, ['doneWeek' => toWeek(\Clock::date().' - 1 WEEK')]);

		$this->assertTrue($this->getCancelFail($reading['undo']), 'une intervention retouchée ne se remet pas à faire');
		$this->assertEquals(\series\Task::DONE, $this->getTaskRead($eTask)['status']);

	}

	private function getCancelFail(array $undo): bool {

		try {
			$this->cancelUndo($undo);
			return FALSE;
		} catch(\FailAction $e) {
			return TRUE;
		}

	}

	/**
	 * Rien de prévu : la question est posée même pour une seule culture, « Hors série » en dernier, les cultures
	 * en phase d'abord puis la plus récente. La réponse crée l'intervention faite au jour dit, dans sa semaine.
	 */
	public function testCreate(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivationOld = $this->createCultivationInPhase(FALSE, 'Série hors phase');
		$eCultivationPhase = $this->createCultivationInPhase(TRUE, 'Série en phase');
		$eCultivationRecent = $this->createCultivationInPhase(FALSE, 'Série récente');

		$reading = $this->read($this->getDoneText());

		$this->assertEquals('series', $reading['question']['name']);
		$this->assertEquals([$eCultivationPhase['id'], $eCultivationRecent['id'], $eCultivationOld['id']], array_values(array_intersect(
			$reading['question']['cCultivation']->getIds(),
			[$eCultivationOld['id'], $eCultivationPhase['id'], $eCultivationRecent['id']]
		)), 'en phase d\'abord, puis la plus récente');

		$date = date('Y-m-d', \Clock::shift('-1 DAY'));

		$reading = $this->read($this->getDoneText('hier '), ['addCultivation' => $eCultivationRecent['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertTrue($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['cTask']->first();

		$eTaskRead = $this->getTaskRead($eTask);

		$this->assertEquals(\series\Task::DONE, $eTaskRead['status']);
		$this->assertEquals($date, $eTaskRead['doneDate']);
		$this->assertEquals(toWeek($date), $eTaskRead['doneWeek']);
		$this->assertEquals(toWeek($date), $eTaskRead['plannedWeek'], 'prévue dans la semaine où elle a été faite');
		$this->assertEquals($eCultivationRecent['id'], $eTask['cultivation']['id']);
		$this->assertEquals(0.0, $this->getTimeOf($eTask), 'la coche n\'écrit aucun temps');

		$this->assertEquals('done', $this->read($this->getDoneText('hier '))['intention'], 'un second Entrée retrouve celle qui est faite');
		$this->assertEquals(NULL, $this->read($this->getDoneText('hier '))['question'], 'sans en créer une autre');

	}

	/**
	 * Un semis direct ou une plantation créés recalculent leur série (`\series\SeriesLib::recalculate()`), qui
	 * lit son cycle : l'écriture ne doit pas tomber sur une série que la question a chargée.
	 */
	public function testCreateSowing(): void {

		if($this->requireAction(ACTION_SEMIS_DIRECT) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE, 'Série semée');

		$text = $this->getAction(ACTION_SEMIS_DIRECT)['name'].' '.$this->getPlant()['name'];

		$reading = $this->read($text, ['addCultivation' => $eCultivation['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertTrue($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(\series\Task::DONE, $this->getTaskRead($reading['confirmation']['cTask']->first())['status']);

	}

	/**
	 * Un semis ou une plantation est en phase autour du départ de la culture, à `PHASE_START_WEEKS` semaines
	 * près ; toute autre action, dans la fenêtre de récolte. La culture au départ, créée la première, passe
	 * devant la plus récente pour un semis, derrière elle pour une fertilisation.
	 */
	public function testSortByPhaseStart(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE or $this->requireAction(ACTION_SEMIS_DIRECT) === FALSE) {
			return;
		}

		$week = week_number(\Clock::week());

		// Le départ dix semaines plus tôt tomberait sinon la saison d'avant.
		if($this->assertDecorPresent($week > 12, 'une semaine au-delà de la douzième') === FALSE) {
			return;
		}

		$season = $this->getSeason();

		$eCultivationStart = $this->createCultivation($this->createSeries('Série au départ'), $this->getPlant());
		$this->setStart($eCultivationStart, $week, [$season.'-W'.sprintf('%02d', max(1, $week - 30))]);

		$eCultivationHarvest = $this->createCultivation($this->createSeries('Série en récolte'), $this->getPlant());
		$this->setStart($eCultivationHarvest, $week - 10, [\Clock::week()]);

		$cCultivation = CultivationResolveLib::getByPlant($this->eStructure, $this->getPlant(), \Clock::date());
		$ids = [$eCultivationStart['id'], $eCultivationHarvest['id']];

		$order = fn(string $fqn) => array_values(array_intersect(CultivationResolveLib::sortByPhase(clone $cCultivation, $this->getAction($fqn), \Clock::week())->getIds(), $ids));

		$this->assertEquals([$eCultivationStart['id'], $eCultivationHarvest['id']], $order(ACTION_SEMIS_DIRECT), 'un semis, au départ d\'abord');
		$this->assertEquals([$eCultivationHarvest['id'], $eCultivationStart['id']], $order(ACTION_FERTILISATION), 'une fertilisation, en récolte d\'abord');

	}

	/**
	 * Sans `build()` : le décor pose le départ et la récolte attendue, que le formulaire de culture vérifierait
	 * avec le reste de l'itinéraire, et seule compte la phase que le champ en lit.
	 */
	private function setStart(\series\Cultivation $eCultivation, int $startWeek, array $harvestWeeksExpected): void {

		$eCultivation['startWeek'] = $startWeek;
		$eCultivation['startAction'] = \series\Cultivation::SOWING;
		$eCultivation['harvestWeeksExpected'] = $harvestWeeksExpected;

		\series\Cultivation::model()
			->select(['startWeek', 'startAction', 'harvestWeeksExpected'])
			->update($eCultivation);

	}

	/**
	 * « Hors série » crée l'intervention sur l'espèce seule, et ne vaut que pour l'espèce à laquelle il répondait.
	 */
	public function testCreateNoSeries(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getDoneText(), ['addNoSeries' => $this->getPlant()['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['cTask']->first();

		$this->assertTrue($eTask['series']->empty());
		$this->assertEquals($this->getPlant()['id'], $eTask['plant']['id']);
		$this->assertEquals(\series\Task::DONE, $eTask['status']);

		$ePlantOther = $this->createPlant('Zzautre');

		$this->assertEquals('series', $this->read($this->getAction(ACTION_FERTILISATION)['name'].' '.$ePlantOther['name'], ['addNoSeries' => $this->getPlant()['id']])['question']['name']);

	}

	/**
	 * La série choisie se pose en jeton et la phrase se relit : une durée tapée ensuite garde l'ajout au
	 * planning du temps (2c), à faire, et la même réponse sans durée crée l'intervention faite.
	 */
	public function testCreateThenDuration(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getDoneText('1 h '), ['addCultivation' => $eCultivation['id']]);

		$this->assertEquals('time', $reading['intention']);
		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(\series\Task::TODO, $reading['confirmation']['eTask']['status']);

		$reading = $this->read($this->getDoneText(), ['addCultivation' => $eCultivation['id']]);

		$this->assertEquals('done', $reading['intention']);
		$this->assertTrue($reading['confirmation']['created']);
		$this->assertEquals(\series\Task::DONE, $reading['confirmation']['cTask']->first()['status']);

	}

	/**
	 * Une action hors de la catégorie Culture suivie d'une espèce ne se crée pas faite : avec le verbe, le refus dit
	 * la fenêtre. Seule, elle passe à l'intervention (`ActionOutsideCultureTest::testDoneCreates()`).
	 */
	public function testCreateOutsideCulture(): void {

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

		$reading = $this->read('fait '.$eAction['name'].' '.$this->getPlant()['name']);

		$this->assertEquals(NULL, $reading['question']);
		$this->assertEquals('unplanned', $reading['refusal']['reason']);
		$this->assertEquals(DoneIntentionLib::getWeekFrom(\Clock::date()), $reading['refusal']['from']);

	}

	/**
	 * Annuler supprime l'intervention créée ; celle qui a bougé depuis reste, faite, et l'annulation se refuse.
	 */
	public function testCreateCancel(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->read($this->getDoneText(), ['addCultivation' => $eCultivation['id']]);
		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['cTask']->first();

		$cancellation = $this->cancelUndo($reading['undo']);

		$this->assertTrue($cancellation['created']);
		$this->assertTrue($this->getTaskRead($eTask)->empty(), 'supprimée');

		$reading = $this->read($this->getDoneText(), ['addCultivation' => $eCultivation['id']]);
		EntryLib::write($this->eStructure, $reading);

		$eTask = $reading['confirmation']['cTask']->first();

		// Retouchée ailleurs après la coche. Sans `TaskLib` ni `build()` : le décor pose directement l'état
		// qu'une autre voie aurait laissé, seule compte l'empreinte que `CancelLib::isCreatedBy()` compare.
		\series\Task::model()->update($eTask, ['description' => 'Retouchée']);

		$this->assertTrue($this->getCancelFail($reading['undo']), 'une intervention retouchée ne se supprime pas');
		$this->assertEquals(\series\Task::DONE, $this->getTaskRead($eTask)['status'], 'ni ne se remet à faire');

	}

	private function getTimeOf(\series\Task $eTask): float {

		return (float)\series\Task::model()
			->whereId($eTask['id'])
			->getValue('time');

	}

	/**
	 * La précédence de la coche ne départage que des lectures du même objet : une espèce dont le nom commence
	 * par celui d'une action laisse le verbe se demander en frappe, comme avant la coche.
	 */
	public function testPrecedenceSameObjectOnly(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant($this->getAction(ACTION_FERTILISATION)['name'].'zz');
		$this->createTaskPlanned($this->createCultivation($this->createSeries('Série F'), $ePlant));

		$this->assertEquals(NULL, $this->read(mb_substr($this->getAction(ACTION_FERTILISATION)['name'], 0, 4))['intention'], 'la récolte et le temps lisent aussi bien deux objets différents');
		$this->assertEquals('harvest', $this->read($ePlant['name'].' 2 kg ')['intention'], 'l\'espèce et une quantité restent une récolte');

	}

}
?>
