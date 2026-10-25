<?php
namespace planning;

/**
 * « Ajouter une personne » ne propose que les personnes du planning (retour 254) : celles qui sont présentes
 * dans la semaine, comme la fiche de temps, la personne connectée en tête, présente ou non, et celles qui ont
 * déjà du temps sur l'intervention que l'écran corrige. Le serveur refuse le temps de qui la liste ne propose
 * pas.
 *
 * Lancement : dev/test package=planning file=TimeTeam
 */
class TimeTeamTest extends HarvestFixtureTest {

	/**
	 * Bruno présent, un autre membre sans présence, la personne connectée sans présence : la liste de la
	 * semaine garde Bruno et la personne connectée, en tête au panneau « Nouvelle intervention », et écarte
	 * l'autre membre, que la liste de toute l'équipe porte pourtant.
	 */
	public function testWeekPresence(): void {

		[$eUserOnline, $eUserBruno, $eUserAbsent] = $this->getTeam();

		if($this->assertDecorPresent($eUserAbsent->notEmpty(), 'un membre sans présence dans la semaine dont le temps peut s\'écrire, autre que la personne connectée et Bruno') === FALSE) {
			return;
		}

		// La personne connectée du décor est présente toute l'année : le cas la retire de l'équipe dans sa
		// transaction, aucun autre compte n'ayant le planning complet qu'exige l'écran.
		foreach(\hr\PresenceLib::getByUser($this->eStructure, $eUserOnline) as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		if($this->assertDecorPresent($this->getPresent()->offsetExists($eUserOnline['id']) === FALSE, 'la personne connectée sans présence dans la semaine') === FALSE) {
			return;
		}

		$this->addPresence($eUserBruno);

		$users = TimeTeamLib::getUsersForWeek($this->eStructure, \Clock::week())->getIds();

		$this->assertTrue(in_array($eUserBruno['id'], $users), 'un membre présent dans la semaine n\'est pas proposé');
		$this->assertTrue(in_array($eUserOnline['id'], $users), 'la personne connectée sans présence n\'est pas proposée');
		$this->assertFalse(in_array($eUserAbsent['id'], $users), 'un membre sans présence dans la semaine est proposé');
		$this->assertTrue(TimeTeamLib::getUsers($this->eStructure)->offsetExists($eUserAbsent['id']), 'la liste de toute l\'équipe, que garde le carnet d\'entretien, n\'a plus le membre sans présence');

		[$data, , $action] = $this->callDay('create', ['date' => week_date_day(\Clock::week(), 2)], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Nouvelle intervention » ne s\'ouvre pas') === FALSE) {
			return;
		}

		$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		$this->assertEquals($eUserOnline['id'], $data->cUserTime->first()['id'] ?? NULL, 'la personne connectée sans présence ne vient pas en tête du panneau');
		$this->assertTrue($document->querySelector('[data-planning-harvest-add="'.$eUserBruno['id'].'"]') !== NULL, '« Ajouter une personne » ne propose pas le membre présent');
		$this->assertTrue($document->querySelector('[data-planning-harvest-add="'.$eUserAbsent['id'].'"]') === NULL, '« Ajouter une personne » propose un membre sans présence');

	}

	/**
	 * Une intervention faite refuse le temps d'un membre que le panneau ne propose pas, et écrit celui du
	 * membre présent.
	 */
	public function testDoneRefusesAbsent(): void {

		[$eUserOnline, $eUserBruno, $eUserAbsent] = $this->getTeam();

		if($this->assertDecorPresent($eUserAbsent->notEmpty(), 'un membre sans présence dans la semaine dont le temps peut s\'écrire, autre que la personne connectée et Bruno') === FALSE) {
			return;
		}

		$this->addPresence($eUserBruno);

		$input = [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'status' => \series\Task::DONE,
			'day' => week_date_day(\Clock::week(), 2),
			'description' => '',
		];

		[, , $action] = $this->callDay('doCreate', $input + ['time' => [$eUserAbsent['id'] => '1']]);

		$this->assertEquals(['Timesheet::user.check'], $this->getFails($action), 'le temps d\'un membre sans présence n\'est pas refusé');

		[$data, , $action] = $this->callDay('doCreate', $input + ['time' => [$eUserOnline['id'] => '0.5', $eUserBruno['id'] => '1']]);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps du membre présent est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(
			[$eUserOnline['id'] => 0.5, $eUserBruno['id'] => 1.0],
			$this->getTimeByUser($data->cTaskCreated->first()),
			'l\'intervention faite ne porte pas le temps de la personne connectée et du membre présent'
		);

	}

	/**
	 * Un membre sans présence qui a déjà du temps sur l'intervention de la récolte reste dans le tiroir de
	 * remplacement, montré avec son temps, et son temps se corrige. Sur une autre récolte, il n'est pas proposé.
	 */
	public function testHarvestKeepsWorked(): void {

		[, , $eUserAbsent] = $this->getTeam();

		if($this->assertDecorPresent($eUserAbsent->notEmpty(), 'un membre sans présence dans la semaine dont le temps peut s\'écrire, autre que la personne connectée et Bruno') === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		// Sans `build()`, qui refuserait le temps d'une personne absente : c'est le temps saisi avant que la
		// présence ne s'arrête, que le cas éprouve.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUserAbsent,
			'date' => \Clock::date(),
			'time' => 1.25,
		]));

		$this->assertTrue(HarvestWeekLib::getTimeTeam($this->eStructure, \Clock::week(), new \Collection([$eTask['id'] => $eTask]))->offsetExists($eUserAbsent['id']), 'le membre qui a du temps sur l\'intervention n\'est pas gardé');
		$this->assertFalse(HarvestWeekLib::getTimeTeam($this->eStructure, \Clock::week())->offsetExists($eUserAbsent['id']), 'le membre sans présence est proposé hors de l\'intervention où il a du temps');

		$cUser = HarvestWeekLib::getTimeUsers($this->eStructure, $eCultivation, \Clock::week(), HarvestWeekLib::getDays(\Clock::week()), 'replace', HarvestWeekLib::getTaskWeek($this->eStructure, $eCultivation, \Clock::week()));

		$this->assertEquals('shown', $cUser->offsetExists($eUserAbsent['id']) ? $cUser[$eUserAbsent['id']]['timeLine'] : NULL, 'le tiroir de remplacement ne montre pas le membre qui a du temps sur l\'intervention');

		[, , $action] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '2',
			'time' => [$eUserAbsent['id'] => '0.5'],
		], 'POST');

		$this->assertTrue($action instanceof \ViewAction, 'le temps du membre qui a du temps sur l\'intervention ne se corrige pas : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$eUserAbsent['id'] => 0.5], $this->getTimeByUser($eTask), 'le temps du membre n\'est pas remplacé');

	}

	/**
	 * Une personne hors de l'équipe n'est jamais proposée, même avec du temps sur l'intervention : le temps
	 * survit au départ d'une personne, et qui gère la structure peut écrire le temps de n'importe qui
	 * (`Timesheet::canWrite()`). Elle n'a plus de présence, qui part avec l'équipe (`TeammateLib::delete()`).
	 */
	public function testNonMemberRefused(): void {

		$eUserOutside = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('decor.sans.structure@example.org')
			->get();

		if($this->assertDecorPresent($eUserOutside->notEmpty() and \structure\MemberLib::getUsersByStructure($this->eStructure)->offsetExists($eUserOutside['id']) === FALSE, 'decor.sans.structure@example.org, hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation();
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		// Sans `build()`, qui refuserait le temps d'une personne hors de la structure : c'est le temps d'un membre
		// parti depuis, que le cas éprouve.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUserOutside,
			'date' => \Clock::date(),
			'time' => 1.0,
		]));

		$this->assertFalse(HarvestWeekLib::getTimeTeam($this->eStructure, \Clock::week(), new \Collection([$eTask['id'] => $eTask]))->offsetExists($eUserOutside['id']), 'une personne hors de la structure est proposée');

		[, , $action] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '2',
			'time' => [$eUserOutside['id'] => '3'],
		], 'POST');

		$this->assertEquals(['Timesheet::user.check'], $this->getFails($action), 'le temps d\'une personne hors de la structure n\'est pas refusé');
		$this->assertEquals([$eUserOutside['id'] => 1.0], $this->getTimeByUser($eTask), 'le temps d\'une personne hors de la structure a été remplacé');

	}

	/**
	 * Une personne sans compte de la structure (`ghost`) suit la règle des membres : proposée et son temps
	 * écrit quand elle est présente dans la semaine, absente de la liste sinon. Le planning la montre, et qui
	 * gère la structure écrit son temps (arbitrage V-Planning du 12/10).
	 */
	public function testGhostPresent(): void {

		$eUserGhost = $this->createGhost();

		$this->assertFalse(TimeTeamLib::getUsersForWeek($this->eStructure, \Clock::week())->offsetExists($eUserGhost['id']), 'une personne sans compte et sans présence dans la semaine est proposée');

		$this->addPresence($eUserGhost);

		$this->assertTrue(TimeTeamLib::getUsersForWeek($this->eStructure, \Clock::week())->offsetExists($eUserGhost['id']), 'une personne sans compte présente dans la semaine n\'est pas proposée');

		[$data, , $action] = $this->callDay('create', ['date' => week_date_day(\Clock::week(), 2)], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Nouvelle intervention » ne s\'ouvre pas') === FALSE) {
			return;
		}

		$panel = new TaskCreateUi()->create($data->eStructure, $data->page, $data->week, $data->when, $data->weeks, $data->eTask, $data->eCategoryAction, $data->cActionShortcut, $data->date, $data->status, $data->days, $data->cUserTime);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($document->querySelector('[data-planning-harvest-add="'.$eUserGhost['id'].'"]') !== NULL, '« Ajouter une personne » ne propose pas la personne sans compte présente');

		[$data, , $action] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'status' => \series\Task::DONE,
			'day' => week_date_day(\Clock::week(), 2),
			'description' => '',
			'time' => [$eUserGhost['id'] => '1'],
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps de la personne sans compte présente est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$eUserGhost['id'] => 1.0], $this->getTimeByUser($data->cTaskCreated->first()), 'l\'intervention faite ne porte pas le temps de la personne sans compte');

	}

	/** Une personne sans compte, posée comme la pose l'écran de l'équipe (`member.p.php`, `doCreateGhost`). */
	private function createGhost(): \user\User {

		$eUser = \user\UserLib::getNew()->merge([
			'invoiceCountry' => new \user\Country(),
			'email' => NULL,
			'type' => \user\User::PRIVATE,
			'visibility' => \user\User::PRIVATE,
			'roleProducer' => TRUE
		]);

		$fw = new \FailWatch();

		$eUser->build(['firstName', 'lastName'], ['firstName' => 'Fantôme', 'lastName' => 'Planning '.uniqid()]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('personne sans compte refusée : '.json_encode($fw->get())));

		\structure\MemberLib::createGhostUser($this->eStructure, $eUser);

		return $eUser;

	}

	/**
	 * La personne connectée, Bruno, et le premier autre membre dont le temps peut s'écrire sans présence dans
	 * la semaine, vide quand le décor n'en a pas : chaque cas pose les présences qu'il éprouve.
	 */
	private function getTeam(): array {

		$eUserOnline = $this->getMember();
		$eUserBruno = $this->getMemberPlanning();

		$cUserPresent = $this->getPresent();

		$cUserAbsent = TimeTeamLib::getUsers($this->eStructure)->find(fn(\user\User $eUser) => (
			$eUser->is($eUserOnline) === FALSE and
			$eUser->is($eUserBruno) === FALSE and
			$cUserPresent->offsetExists($eUser['id']) === FALSE
		));

		$eUserAbsent = $cUserAbsent->empty() ? new \user\User() : $cUserAbsent->first();

		// Seule la présence décide dans l'équipe : la personne y entre sans période si elle n'y est pas.
		// Sans `build()` : elle vient des membres de la structure, seul contrôle de l'étape `member`.
		if(
			$eUserAbsent->notEmpty() and
			\hr\TeammateLib::isTeammate($this->eStructure, $eUserAbsent) === FALSE
		) {
			\hr\TeammateLib::create(new \hr\Teammate([
				'structure' => $this->eStructure,
				'user' => $eUserAbsent
			]));
		}

		return [$eUserOnline, $eUserBruno, $eUserAbsent];

	}

	/** Les personnes présentes dans la semaine, par la règle de la fiche de temps. */
	private function getPresent(): \Collection {

		return \structure\MemberLib::getUsersByStructureForPeriod($this->eStructure, week_date_starts(\Clock::week()), week_date_ends(\Clock::week()));

	}

	/** Le temps de travail écrit sur l'intervention, par personne. */
	private function getTimeByUser(\series\Task $eTask): array {

		$time = [];

		foreach(\series\Timesheet::model()->select(['user', 'time'])->whereTask($eTask)->getCollection() as $eTimesheet) {
			$time[$eTimesheet['user']['id']] = ($time[$eTimesheet['user']['id']] ?? 0.0) + (float)$eTimesheet['time'];
		}

		ksort($time);

		return $time;

	}

}
?>
