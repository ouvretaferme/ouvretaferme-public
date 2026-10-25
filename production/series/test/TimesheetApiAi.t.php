<?php
namespace series;

/**
 * Test bout-en-bout de la ressource series\Timesheet (production/series/api/Timesheet_0_1beta.a.php).
 *
 * L'intervention, la mise en culture et le temps déjà saisi sont ceux du décor (`SeriesFixtureAiTest`),
 * posés dans la transaction de la méthode : les cas ne lisent plus la ligne de temps la plus récente de
 * la structure, dont le jour et le cumul changeaient avec la base. Chaque écriture vérifie en plus le
 * cumul de la tâche (Task.time), que TimesheetLib refait par TaskLib::recalculateTime() : c'est ce
 * cumul, et non la ligne écrite, que le reste du produit affiche.
 *
 * Lancement : dev/test package=series file=TimesheetApiAi
 */
class TimesheetApiAiTest extends SeriesFixtureAiTest {

	/** Le temps déjà saisi sur l'intervention du décor, avant tout geste du cas. */
	const TIME_EXISTING = 0.75;

	/** Ce que tout cas qui écrit exige de la structure : une équipe, et l'action de l'intervention du décor. */
	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_PLANTATION));

	}

	/** Une ligne de temps du membre sur l'intervention du décor, datée du jour. */
	private function getTimesheet(): Timesheet {

		return $this->once('timesheet', fn() => $this->createTimesheet($this->getTask(), self::TIME_EXISTING));

	}

	/** Le cumul de l'intervention tel que la base le porte, l'élément du décor ne suivant pas les écritures. */
	private function getTaskTime(Task $eTask): float {

		return round((float)Task::model()->whereId($eTask)->getValue('time'), 4);

	}

	/**
	 * Un utilisateur hors de l'équipe, n'importe lequel : seule son absence de l'équipe compte. La table
	 * des utilisateurs est un décor de la base, le cas se déclare si elle n'en porte aucun.
	 */
	private function getUserForeign(): \user\User {

		return $this->once('userForeign', function() {

			$members = new \structure\MemberModel()
				->whereStructure($this->eStructure)
				->getColumn('user')
				->getIds();

			return \user\User::model()
				->select(['id'])
				->whereId('NOT IN', $members)
				->sort(['id' => SORT_ASC])
				->get();

		});

	}

	private function requireUserForeign(): bool {

		return $this->assertTrue($this->getUserForeign()->notEmpty(), 'la base porte un utilisateur hors de l\'équipe de la structure '.static::STRUCTURE);

	}

	public function testList(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTimesheet = $this->getTimesheet();

		$response = $this->callApi('GET', 'timesheetList', get: [
			'dateFrom' => $eTimesheet['date'],
			'dateTo' => $eTimesheet['date'],
			'limit' => 20
		]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0);
		$this->assertTrue(in_array($eTimesheet['id'], array_column($response['body']['list'], 'id')), 'la ligne du décor est dans la liste de son jour');

	}

	/** La période cadre la requête : sans elle, la liste balaierait la table. */
	public function testListRequiresPeriod(): void {

		$missingTo = $this->callApi('GET', 'timesheetList', get: ['dateFrom' => \Clock::date()]);
		$missingFrom = $this->callApi('GET', 'timesheetList', get: ['dateTo' => \Clock::date()]);

		foreach([$missingTo, $missingFrom] as $response) {
			$this->assertEquals(400, $response['status']);
		}

	}

	public function testListByUser(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTimesheet = $this->getTimesheet();

		$response = $this->callApi('GET', 'timesheetList', get: [
			'dateFrom' => $eTimesheet['date'],
			'dateTo' => $eTimesheet['date'],
			'user' => $eTimesheet['user']['id']
		]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0);

		foreach($response['body']['list'] as $timesheet) {
			$this->assertEquals($eTimesheet['user']['id'], $timesheet['user']);
		}

	}

	public function testRead(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTimesheet = $this->getTimesheet();

		$response = $this->callApi('GET', 'timesheet/{id}', get: ['id' => $eTimesheet['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eTimesheet['id'], $response['body']['id'] ?? NULL);
		$this->assertEquals(self::TIME_EXISTING, $response['body']['time'] ?? NULL);
		$this->assertEquals($eTimesheet['task']['id'], $response['body']['task'] ?? NULL);
		$this->assertFalse(array_key_exists('createdAt', $response['body']));
		$this->assertFalse(array_key_exists('createdBy', $response['body']));

	}

	/**
	 * La tâche donne à la ligne sa série, sa mise en culture et son espèce, la date sa semaine, et le
	 * cumul de la tâche augmente d'autant.
	 */
	public function testCreate(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTask();
		$eUser = $this->getMember();

		$this->getTimesheet();

		$timeBefore = $this->getTaskTime($eTask);

		$this->assertEquals(self::TIME_EXISTING, $timeBefore, 'le cumul de l\'intervention porte le temps déjà saisi');

		$create = $this->callApi('POST', 'timesheet', body: [
			'task' => $eTask['id'],
			'user' => $eUser['id'],
			'date' => \Clock::date(),
			'time' => 1.5
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(1.5, $create['body']['time'] ?? NULL);
		$this->assertEquals($eTask['id'], $create['body']['task'] ?? NULL);
		$this->assertEquals($eUser['id'], $create['body']['user'] ?? NULL);
		$this->assertEquals(toWeek(\Clock::date()), $create['body']['week'] ?? NULL, 'la semaine se dérive de la date');
		$this->assertEquals($eTask['series']['id'], $create['body']['series'] ?? NULL);
		$this->assertEquals($eTask['cultivation']['id'], $create['body']['cultivation'] ?? NULL);

		$this->assertEquals(round($timeBefore + 1.5, 4), $this->getTaskTime($eTask), 'le cumul de la tâche a suivi');

	}

	/** Une tâche d'une autre structure ne se désigne pas. */
	public function testCreateRejectsForeignTask(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eTaskForeign = $this->createTaskForeign();

		$response = $this->callApi('POST', 'timesheet', body: [
			'task' => $eTaskForeign['id'],
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => 1.0
		]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_task', $response['body'][0]['code'] ?? NULL);

	}

	/** Le temps d'une personne qui n'est pas de l'équipe ne se saisit pas. */
	public function testCreateRejectsForeignUser(): void {

		if($this->requireDecor() === FALSE or $this->requireUserForeign() === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'timesheet', body: [
			'task' => $this->getTask()['id'],
			'user' => $this->getUserForeign()['id'],
			'date' => \Clock::date(),
			'time' => 1.0
		]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_user', $response['body'][0]['code'] ?? NULL);

	}

	/** Un temps ne se saisit pas d'avance : Timesheet::build() borne la date à aujourd'hui. */
	public function testCreateRejectsFutureDate(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'timesheet', body: [
			'task' => $this->getTask()['id'],
			'user' => $this->getMember()['id'],
			'date' => date('Y-m-d', strtotime('+1 day')),
			'time' => 1.0
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/** Seule la durée se modifie, et le cumul de la tâche suit. */
	public function testUpdate(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTask();

		$create = $this->callApi('POST', 'timesheet', body: [
			'task' => $eTask['id'],
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => 1.0
		]);

		$this->assertEquals(201, $create['status']);

		$timeBefore = $this->getTaskTime($eTask);

		$update = $this->callApi('PUT', 'timesheet/{id}', body: ['time' => 3.0], get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(3.0, $update['body']['time'] ?? NULL);

		$this->assertEquals(round($timeBefore + 2.0, 4), $this->getTaskTime($eTask), 'le cumul de la tâche a suivi la modification');

	}

	public function testDelete(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTask();

		$create = $this->callApi('POST', 'timesheet', body: [
			'task' => $eTask['id'],
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => 2.0
		]);

		$this->assertEquals(201, $create['status']);

		$timeBefore = $this->getTaskTime($eTask);

		$delete = $this->callApi('DELETE', 'timesheet/{id}', get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $delete['status']);

		$this->assertEquals(round($timeBefore - 2.0, 4), $this->getTaskTime($eTask), 'le cumul de la tâche a suivi la suppression');

	}

	/**
	 * La répartition écrit ce que l'écran écrit : chaque intervention reçoit sa part, la somme fait le
	 * temps demandé, et une ligne déjà là pour cette personne et ce jour est augmentée, pas doublée.
	 *
	 * Les trois interventions sont posées sur la même mise en culture, sans aucun temps du jour : la
	 * somme des parts ne peut valoir que le temps demandé. Lue sur des interventions réelles, elle
	 * portait aussi le temps que la structure y avait saisi ce jour-là.
	 */
	public function testDistribution(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eUser = $this->getMember();

		$cTask = new \Collection();

		for($i = 0; $i < 3; $i++) {
			$cTask[] = $this->createTask($this->getCultivation());
		}

		// La forme que la réécriture du serveur transmet : le nom de la route arrive comme un identifiant.
		$response = $this->callApi('POST', 'timesheet/{id}', body: [
			'tasks' => $cTask->getIds(),
			'user' => $eUser['id'],
			'date' => \Clock::date(),
			'time' => 3.0,
			'distribution' => 'fair'
		], get: ['id' => 'distribution']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals($cTask->count(), count($response['body']['list']), 'une ligne par intervention');

		$total = array_sum(array_column($response['body']['list'], 'time'));

		$this->assertEquals(3.0, round($total, 4), 'la somme des parts fait le temps demandé');

		foreach($response['body']['list'] as $timesheet) {
			$this->assertEquals(1.0, round($timesheet['time'], 4), 'à parts égales, chacune reçoit un tiers');
		}

		// Le même geste rejoué augmente les lignes existantes plutôt que d'en créer d'autres
		$again = $this->callApi('POST', 'timesheet/distribution', body: [
			'tasks' => $cTask->getIds(),
			'user' => $eUser['id'],
			'date' => \Clock::date(),
			'time' => 3.0,
			'distribution' => 'fair'
		]);

		$this->assertEquals($cTask->count(), count($again['body']['list']), 'toujours une ligne par intervention');
		$this->assertEquals(6.0, round(array_sum(array_column($again['body']['list'], 'time')), 4), 'les temps se sont ajoutés');

		foreach($cTask as $eTask) {
			$this->assertEquals(2.0, $this->getTaskTime($eTask), 'le cumul de chaque intervention a suivi les deux gestes');
		}

	}

	/** Une intervention d'une autre structure ne se répartit pas. */
	public function testDistributionRejectsForeignTask(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eTaskForeign = $this->createTaskForeign();

		$response = $this->callApi('POST', 'timesheet/distribution', body: [
			'tasks' => [$this->getTask()['id'], $eTaskForeign['id']],
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => 1.0
		]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_task', $response['body'][0]['code'] ?? NULL);

	}

	/** Une clé de répartition que TaskLib::distribute() ne connaît pas est refusée avant toute écriture. */
	public function testDistributionRejectsUnknownKey(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'timesheet/distribution', body: [
			'tasks' => [$this->getTask()['id']],
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => 1.0,
			'distribution' => 'sonde'
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('unknown_distribution', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Une structure qui ne suit pas le temps de travail n'en reçoit pas par la répartition. Le réglage se
	 * pose sur la configuration mémorisée (`Structure::conf()`, que `hasTime()` lit pour la structure de la
	 * clé), puis se remet : la ligne de la structure n'est pas touchée.
	 */
	public function testDistributionRefusesUntracked(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eConfiguration = \structure\StructureLib::getById($this->eStructure['id'])->conf();
		$timeBefore = $eConfiguration['time'];

		$cTask = new \Collection([$this->createTask($this->getCultivation())]);

		try {

			$eConfiguration['time'] = FALSE;

			$response = $this->callApi('POST', 'timesheet/distribution', body: [
				'tasks' => $cTask->getIds(),
				'user' => $this->getMember()['id'],
				'date' => \Clock::date(),
				'time' => 1.0
			]);

		} finally {
			$eConfiguration['time'] = $timeBefore;
		}

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['Timesheet::time.untracked'], array_column($response['body'], 'code'), 'la répartition sans suivi du temps est refusée par la durée');
		$this->assertEquals(0, Timesheet::model()->whereTask('IN', $cTask)->count(), 'aucune ligne de temps n\'est écrite sans suivi du temps');
		$this->assertEquals(0.0, $this->getTaskTime($cTask->first()), 'le cumul de l\'intervention n\'a pas bougé');

	}

	/** La répartition ne fait qu'ajouter : une durée négative est refusée, et retirer passe par PUT timesheet/{id}. */
	public function testDistributionRejectsNegativeTime(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$cTask = new \Collection([$this->createTask($this->getCultivation())]);

		$response = $this->callApi('POST', 'timesheet/distribution', body: [
			'tasks' => $cTask->getIds(),
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => -1.0
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['Timesheet::time.check'], array_column($response['body'], 'code'), 'une durée négative est refusée par le schéma');
		$this->assertEquals(0, Timesheet::model()->whereTask('IN', $cTask)->count(), 'aucune ligne de temps n\'est écrite');

	}

	/** Une durée nulle n'a rien à répartir : elle vaut absence, et rien ne s'écrit. */
	public function testDistributionRejectsNullTime(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$cTask = new \Collection([$this->createTask($this->getCultivation())]);

		$response = $this->callApi('POST', 'timesheet/distribution', body: [
			'tasks' => $cTask->getIds(),
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'time' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('missing_time', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(0, Timesheet::model()->whereTask('IN', $cTask)->count(), 'aucune ligne de temps n\'est écrite');

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'timesheet/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
