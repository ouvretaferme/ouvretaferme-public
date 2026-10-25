<?php
namespace toolbox;

/**
 * Le temps d'une équipe dans « Entretien fait » (planche production-finir-temps-bloc,
 * `planning\TimeTeamLib`) :
 *
 * - les personnes qui ont fait l'entretien et la durée de chacune, au jour de l'entretien, sur la tâche
 *   que « Fait » crée ;
 * - le bloc propose les personnes dont on peut écrire le temps, comme le planning : toute l'équipe à
 *   qui gère la structure, sa propre personne sinon, et alors sans vignette ;
 * - le temps est facultatif : une personne sans durée n'en reçoit aucun, et sans aucune durée rien
 *   n'est écrit ; une personne hors du bloc ou une journée de plus de 24 heures sont refusées ;
 * - sans suivi du temps, dans « Consigner un entretien » et à la modification d'une ligne, pas de bloc.
 *
 * Le décor est celui de `MaintenanceTaskFixtureTest`, sur F1, qui suit le temps et compte quatre membres.
 *
 * Lancement : dev/test package=toolbox file=MaintenanceTime
 */
class MaintenanceTimeTest extends MaintenanceTaskFixtureTest {

	/**
	 * Le gestionnaire choisit deux personnes, une heure et demie pour l'une, trois quarts d'heure pour
	 * l'autre : chacune reçoit la sienne au jour de l'entretien, et la tâche en porte deux heures et quart.
	 */
	public function testEachUserReceivesTheirTime(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		if($this->assertTrue($eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$eUserAdmin = $this->getUser('decor.f1.admin@example.org');
		$eUserPlanning = $this->getUser('decor.f1.planning@example.org');

		$this->assertEquals(
			\structure\Member::model()->whereStructure($eStructure)->whereStatus(\structure\Member::IN)->count(),
			$this->getPanel($eMaintenance)['cUserTime']->count(),
			'le gestionnaire se voit proposer toute l\'équipe'
		);

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, [
			'date' => '2026-06-12',
			'time' => [
				$eUserAdmin['id'] => '1.5',
				$eUserPlanning['id'] => '0.75'
			]
		]);

		if($this->assertTrue($fw->ok(), 'l\'entretien fait se construit : '.$fw) === FALSE) {
			return;
		}

		MaintenanceDoneLib::create($eMaintenanceDone);

		$eTask = $this->getTasks($eMaintenance)->first();

		$times = $this->getTimes($eTask);
		sort($times);

		$expected = [
			[$eUserAdmin['id'], '2026-06-12', 1.5],
			[$eUserPlanning['id'], '2026-06-12', 0.75]
		];
		sort($expected);

		$this->assertEquals($expected, $times, 'chaque personne reçoit sa durée, au jour de l\'entretien');
		$this->assertEquals(2.25, \series\Task::model()->whereId($eTask['id'])->getValue('time'), 'la tâche porte la somme');

	}

	/**
	 * Le temps est facultatif (04/10) : une personne choisie à zéro, à côté d'une autre qui a sa
	 * durée, n'est pas refusée et ne reçoit rien.
	 */
	public function testZeroIsSkipped(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		$eUserPlanning = $this->getUser('decor.f1.planning@example.org');

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, [
			'date' => '2026-06-12',
			'time' => [
				$this->getUser('decor.f1.admin@example.org')['id'] => '0',
				$eUserPlanning['id'] => '2.25'
			]
		]);

		if($this->assertTrue($fw->ok(), 'une personne à zéro n\'est pas refusée : '.$fw) === FALSE) {
			return;
		}

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals(
			[[$eUserPlanning['id'], '2026-06-12', 2.25]],
			$this->getTimes($this->getTasks($eMaintenance)->first()),
			'seule la personne qui a une durée la reçoit'
		);

	}

	/**
	 * Noter un entretien n'oblige pas à saisir un temps : sans personne choisie, ou avec des personnes
	 * choisies toutes à zéro, la tâche se crée sans temps.
	 */
	public function testNoTimeWritesNothing(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);

		$cases = [
			'sans personne choisie' => [],
			'toutes à zéro' => [
				$this->getUser('decor.f1.admin@example.org')['id'] => '0',
				$this->getUser('decor.f1.planning@example.org')['id'] => '0'
			]
		];

		foreach($cases as $case => $times) {

			$eMaintenance = $this->createMaintenance($eStructure);

			[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, [
				'date' => '2026-06-12',
				'time' => $times
			]);

			$this->assertTrue($fw->ok(), $case.', l\'entretien fait se construit : '.$fw);

			MaintenanceDoneLib::create($eMaintenanceDone);

			$this->assertEquals([], $this->getTimes($this->getTasks($eMaintenance)->first()), $case.', aucun temps n\'est écrit');

		}

	}

	/**
	 * Les refus, et rien ne s'écrit : une personne que le bloc ne propose pas, sur le bloc ; une journée
	 * qui dépasserait 24 heures, sous la personne.
	 */
	public function testRefusals(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		$eUserAdmin = $this->getUser('decor.f1.admin@example.org');

		$eUserOther = \user\User::model()
			->select(['id'])
			->whereId('NOT IN', \structure\Member::model()->select('user')->whereStructure($eStructure)->getColumn('user'))
			->get();

		[, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['date' => '2026-06-12', 'time' => [$eUserOther['id'] => '1.5']]);
		$this->assertTrue($fw->has('Timesheet::user.check'), 'une personne hors de l\'équipe est refusée : '.$fw);

		// Sans `build()` : le temps déjà passé ce jour-là est le décor, posé sur une tâche du même jour.
		$eTaskDay = $this->createTaskDone($eStructure, '2026-06-12');
		\series\TimesheetLib::writeTimeForDay($eTaskDay, new \series\Timesheet([
			'structure' => $eStructure,
			'user' => $eUserAdmin,
			'date' => '2026-06-12',
			'time' => 23.0
		]));

		[, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['date' => '2026-06-12', 'time' => [$eUserAdmin['id'] => '1.5']]);
		$this->assertTrue($fw->has('Timesheet::time.tooLong'), 'une journée de plus de 24 heures est refusée : '.$fw);

		$this->assertEquals(0, $this->getTasks($eMaintenance)->count(), 'rien n\'est écrit au planning');

	}

	/**
	 * Le bloc du gestionnaire porte une vignette et une durée par personne, la sienne choisie d'office ;
	 * celui d'une personne qui n'écrit que son temps n'en porte aucune, la durée seule. Une durée nulle
	 * n'est pas postée.
	 */
	public function testPanel(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		$eUserAdmin = $this->getUser('decor.f1.admin@example.org');
		$eUserPlanning = $this->getUser('decor.f1.planning@example.org');

		$eMaintenanceDone = $this->getPanel($eMaintenance);
		$html = $this->getPanelHtml($eMaintenanceDone);

		$this->assertEquals($eMaintenanceDone['cUserTime']->count(), preg_match_all('/<input disabled="1" id="[^"]+" name="time\[\d+\]"/', $html), 'les durées nulles ne partent pas');
		$this->assertTrue(str_contains($html, 'data-wrapper="time['.$eUserPlanning['id'].']"'), 'chaque personne porte la place de ses refus');

		\user\ConnectionLib::setOnline($eUserPlanning);

		$eMaintenanceDone = $this->getPanel($eMaintenance);
		$html = $this->getPanelHtml($eMaintenanceDone);

		$this->assertEquals([$eUserPlanning['id']], $eMaintenanceDone['cUserTime']->getIds(), 'qui planifie sans gérer n\'écrit que son temps');
		$this->assertEquals(1, preg_match('/<input disabled="1" id="[^"]+" name="time\['.$eUserPlanning['id'].'\]"/', $html), 'la durée seule, éteinte tant qu\'elle est nulle');
		$this->assertTrue(str_contains($html, 'data-wrapper="timeTeam time['.$eUserPlanning['id'].']"'), 'ses refus tombent sur le bloc');

		\user\ConnectionLib::setOnline($eUserAdmin);

	}

	/**
	 * Pas de bloc sans suivi du temps, dans « Consigner un entretien », ni à la modification d'une ligne.
	 */
	public function testNoBlock(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		// Le réglage se lit dans la mémoire de `Structure::conf()`, qui survit à la transaction : il y est
		// coupé le temps du cas, puis rendu.
		$eStructure->conf()['time'] = FALSE;

		$eMaintenanceDone = new MaintenanceDone(['structure' => $eStructure, 'equipment' => $eMaintenance['equipment'], 'maintenance' => $eMaintenance]);
		MaintenanceTaskLib::fillPanel($eMaintenanceDone);

		$eStructure->conf()['time'] = TRUE;

		$this->assertTrue($eMaintenanceDone['cUserTime']->empty(), 'pas de bloc sans suivi du temps');

	}

	protected function getUser(string $email): \user\User {

		return \user\User::model()
			->select(['id', 'firstName', 'lastName', 'vignette', 'visibility'])
			->whereEmail($email)
			->get();

	}

	/**
	 * Le panneau de « Fait », préparé comme la page le prépare (`page/maintenanceDone.p.php`).
	 */
	protected function getPanel(Maintenance $eMaintenance, Equipment $eEquipment = new Equipment()): MaintenanceDone {

		$eEquipment = $eMaintenance->empty() ? $eEquipment : $eMaintenance['equipment'];

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $eEquipment['structure'],
			'equipment' => EquipmentLib::getById($eEquipment['id'], ['id', 'structure', 'meter', 'status', 'tool' => ['name']]),
			'maintenance' => $eMaintenance,
			'name' => NULL,
			'date' => \Clock::date(),
			'value' => NULL,
			'photo' => NULL,
			'meterUpdate' => FALSE
		]);

		EquipmentLib::fillReadingLast($eMaintenanceDone['equipment']);
		$eMaintenanceDone['valueRequired'] = FALSE;
		MaintenanceTaskLib::fillPanel($eMaintenanceDone);

		return $eMaintenanceDone;

	}

	protected function getPanelHtml(MaintenanceDone $eMaintenanceDone): string {
		return new MaintenanceDoneUi()->create($eMaintenanceDone, NULL)->body;
	}

	/**
	 * Une tâche faite hors série, au jour donné, sur laquelle poser du temps.
	 */
	protected function createTaskDone(\structure\Structure $eStructure, string $date): \series\Task {

		$eTask = new \series\Task([
			'structure' => $eStructure,
			'category' => MaintenanceConfigurationLib::getCategory($eStructure),
			'series' => new \series\Series(),
			'season' => NULL,
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'status' => \series\Task::DONE,
		]);

		$eTask->build(\series\TaskLib::getPropertiesCreate()($eTask), [
			'action' => MaintenanceConfigurationLib::getDefaultAction(MaintenanceConfigurationLib::getActions($eStructure))['id'],
			'description' => 'Journée chargée',
			'done' => ['date' => $date],
		]);

		\series\TaskLib::create($eTask);

		return $eTask;

	}

}
?>
