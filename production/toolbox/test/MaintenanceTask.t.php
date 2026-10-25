<?php
namespace toolbox;

/**
 * Du carnet d'entretien au planning (planche production-finir-carnet-planning).
 *
 * - « Fait » sur un entretien planifié crée au planning une intervention déjà faite, à la date de
 *   l'entretien, sur l'intervention réglée pour la ferme, avec le matériel, et le temps de l'équipe
 *   quand le bloc en porte un (`MaintenanceTaskLib`, `MaintenanceTimeTest`).
 * - L'intervention se règle une fois par ferme (`MaintenanceConfiguration`) : le premier « Fait »
 *   la demande et la pose, les suivants ne la lisent plus dans ce qui est posté.
 * - Le même réglage porte le rythme du rappel des compteurs à relever, tous les 3 mois tant que la
 *   ferme n'a rien choisi, et que le premier « Fait » ne touche pas.
 * - Ni « Consigner un entretien », ni l'API ne créent de tâche.
 * - Le planning liste en tête de la semaine les entretiens en retard et ceux datés de la semaine, lus
 *   dans ce que le carnet y dit dû (`toolbox\PlanningObserverLib`, `planning\DueLib::getForWeek()`).
 *
 * Le décor est celui de `MaintenanceTaskFixtureTest`.
 *
 * Lancement : dev/test package=toolbox file=MaintenanceTask
 */
class MaintenanceTaskTest extends MaintenanceTaskFixtureTest {

	/**
	 * « Fait » note l'entretien et crée la tâche faite, à la date de l'entretien, avec le matériel. Sans
	 * durée choisie dans le bloc du temps (`MaintenanceTimeTest`), un temps posté sous le nom que le champ
	 * tapé d'une durée du planning emploie n'est pas lu : la tâche n'en porte pas, sur une ferme qui suit
	 * le temps de travail.
	 */
	public function testDoneCreatesTheTaskDone(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eAction = $this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		if($this->assertTrue($eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['date' => '2026-06-12', 'timeAdd' => '1.5']);
		$this->assertTrue($fw->ok(), 'un entretien fait se construit : '.$fw);

		MaintenanceDoneLib::create($eMaintenanceDone);

		$cTask = $this->getTasks($eMaintenance);

		if($this->assertEquals(1, $cTask->count(), 'une tâche, et une seule, au planning') === FALSE) {
			return;
		}

		$eTask = $cTask->first();

		$this->assertEquals(
			[\series\Task::DONE, '2026-06-12', toWeek('2026-06-12'), $eAction['id'], MaintenanceConfigurationLib::getCategory($eStructure)['id']],
			[$eTask['status'], $eTask['doneDate'], $eTask['doneWeek'], $eTask['action']['id'], $eTask['category']['id']],
			'la tâche est faite, à la date de l\'entretien, sur l\'intervention de la ferme'
		);
		$this->assertTrue($eTask['series']->empty(), 'elle n\'appartient à aucune série');
		$this->assertEquals([$eMaintenance['equipment']['tool']['id']], $eTask['tools'], 'elle porte le matériel');
		$this->assertEquals([], $this->getTimes($eTask), 'aucun temps n\'est écrit, même posté');
		$this->assertEquals(NULL, $eTask['time'], 'et la tâche n\'en porte pas');

		$this->assertEquals(1, MaintenanceDone::model()->whereMaintenance($eMaintenance)->count(), 'l\'entretien est noté');

	}

	/**
	 * Tant que la ferme n'a rien réglé, le panneau demande l'intervention, préremplie « Matériel », et
	 * le premier « Fait » la pose. Ensuite elle n'est plus demandée, et ce qui est posté n'est pas lu.
	 */
	public function testFirstDoneSetsTheAction(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure);

		$cAction = MaintenanceConfigurationLib::getActions($eStructure);
		$eActionDefault = MaintenanceConfigurationLib::getDefaultAction($cAction);
		$eActionOther = $cAction->find(fn(\work\Action $eAction) => $eAction['id'] !== $eActionDefault['id'], limit: 1, default: new \work\Action());

		if($this->assertTrue($eActionDefault->notEmpty() and $eActionOther->notEmpty(), 'la structure '.static::STRUCTURE.' a « Matériel » et une autre intervention sur l\'outil de production') === FALSE) {
			return;
		}

		$getPanel = function() use($eStructure, $eMaintenance): MaintenanceDone {

			$eMaintenanceDone = new MaintenanceDone(['structure' => $eStructure, 'equipment' => $eMaintenance['equipment'], 'maintenance' => $eMaintenance]);
			MaintenanceTaskLib::fillPanel($eMaintenanceDone);

			return $eMaintenanceDone;

		};

		// Le panneau du réglage, ouvert depuis le menu du carnet, montre le même champ.
		$getConfigurationPanel = function() use($eStructure): MaintenanceConfiguration {

			$eMaintenanceConfiguration = new MaintenanceConfiguration(['structure' => $eStructure]);
			MaintenanceConfigurationLib::fillPanel($eMaintenanceConfiguration);

			return $eMaintenanceConfiguration;

		};

		$this->assertEquals($eActionDefault['id'], $getPanel()['eMaintenanceConfiguration']['action']['id'], 'le panneau demande l\'intervention, préremplie « Matériel »');
		$this->assertEquals($eActionDefault['id'], $getConfigurationPanel()['action']['id'], 'et le panneau du réglage s\'ouvre sur elle avant tout premier « Fait »');
		$this->assertEquals(3, $getConfigurationPanel()['readingEveryMonths'], 'avec le rappel des compteurs sur tous les 3 mois : régler le seul rythme reste un geste à un champ');

		[, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, []);
		$this->assertTrue($fw->has('MaintenanceConfiguration::action.check'), 'sans intervention, le premier « Fait » est refusé sur son champ : '.$fw);

		$eActionForeign = \work\Action::model()
			->select(['id'])
			->whereStructure('!=', $eStructure)
			->get();

		[, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['action' => $eActionForeign['id']]);
		$this->assertTrue($fw->has('MaintenanceConfiguration::action.usable'), 'l\'intervention d\'une autre structure est refusée : '.$fw);

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['action' => $eActionOther['id']]);
		$this->assertTrue($fw->ok(), 'avec une intervention de la ferme, il passe : '.$fw);
		$this->assertTrue(MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)->empty(), 'rien n\'est réglé tant que l\'entretien n\'est pas noté');

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals($eActionOther['id'], MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)['action']['id'], 'le premier « Fait » règle l\'intervention de la ferme');
		$this->assertEquals($eActionOther['id'], $this->getTasks($eMaintenance)->first()['action']['id'], 'et la tâche la prend');
		$this->assertTrue($getPanel()['eMaintenanceConfiguration']->empty(), 'le panneau ne la demande plus');
		$this->assertEquals($eActionOther['id'], $getConfigurationPanel()['action']['id'], 'et le panneau du réglage montre celle de la ferme');

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['action' => $eActionDefault['id']]);
		$this->assertTrue($fw->ok());

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals($eActionOther['id'], MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)['action']['id'], 'une intervention postée ensuite ne change pas le réglage');
		$this->assertEquals(
			[$eActionOther['id'], $eActionOther['id']],
			$this->getTasks($eMaintenance)->makeArray(fn(\series\Task $eTask) => $eTask['action']['id']),
			'ni l\'intervention de la tâche'
		);

	}

	/**
	 * Une intervention réglée puis désactivée ne vaut plus : le réglage est tenu pour absent, le
	 * panneau la redemande, et la poser de nouveau remplace la ligne de la ferme.
	 */
	public function testUnusableActionIsAskedAgain(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eAction = $this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		// Sans `build()` ni Lib : le test veut l'état « désactivée », pas le geste qui y mène.
		\work\Action::model()->update($eAction, ['status' => \work\Action::INACTIVE]);

		$cAction = MaintenanceConfigurationLib::getActions($eStructure);

		$this->assertTrue(MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)->empty(), 'le réglage est tenu pour absent');

		$eMaintenanceDonePanel = new MaintenanceDone(['structure' => $eStructure, 'equipment' => $eMaintenance['equipment'], 'maintenance' => $eMaintenance]);
		MaintenanceTaskLib::fillPanel($eMaintenanceDonePanel);

		$this->assertTrue($eMaintenanceDonePanel['eMaintenanceConfiguration']->notEmpty(), 'le panneau redemande l\'intervention');
		$this->assertTrue($eMaintenanceDonePanel['eMaintenanceConfiguration']['action']->empty(), 'sans la préremplir de celle qui ne vaut plus');

		// Le panneau du réglage s'ouvre de même : ni l'ancienne valeur, ni « Matériel » désactivée.
		$eMaintenanceConfigurationPanel = new MaintenanceConfiguration(['structure' => $eStructure]);
		MaintenanceConfigurationLib::fillPanel($eMaintenanceConfigurationPanel);

		$this->assertEquals([FALSE, TRUE], [$eMaintenanceConfigurationPanel['isConfigured'], $eMaintenanceConfigurationPanel['action']->empty()], 'le panneau du réglage montre un champ à choisir');

		[, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['action' => $eAction['id']]);
		$this->assertTrue($fw->has('MaintenanceConfiguration::action.usable'), 'l\'intervention désactivée est refusée : '.$fw);

		$eActionOther = $cAction->first();

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['action' => $eActionOther['id']]);
		$this->assertTrue($fw->ok(), 'une autre intervention passe : '.$fw);

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals(1, MaintenanceConfiguration::model()->whereStructure($eStructure)->count(), 'la ferme garde un seul réglage');
		$this->assertEquals($eActionOther['id'], MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)['action']['id'], 'qui porte la nouvelle intervention');

	}

	/**
	 * « Consigner un entretien » n'entre qu'à l'historique : ni tâche, ni intervention demandée.
	 */
	public function testInterventionCreatesNoTask(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure);

		$count = \series\Task::model()->whereStructure($eStructure)->count();

		$eMaintenanceDonePanel = new MaintenanceDone(['structure' => $eStructure, 'equipment' => $eMaintenance['equipment'], 'maintenance' => new Maintenance()]);
		MaintenanceTaskLib::fillPanel($eMaintenanceDonePanel);

		$this->assertEquals(
			[FALSE, TRUE],
			[$eMaintenanceDonePanel['hasTask'], $eMaintenanceDonePanel['eMaintenanceConfiguration']->empty()],
			'le panneau ne demande pas d\'intervention'
		);

		// La ferme n'a rien réglé : l'intervention consignée ne s'en soucie pas.
		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], new Maintenance(), []);
		$this->assertTrue($fw->ok(), 'une intervention consignée se construit sans réglage : '.$fw);

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals($count, \series\Task::model()->whereStructure($eStructure)->count(), 'aucune tâche n\'est créée');
		$this->assertEquals(0, MaintenanceConfiguration::model()->whereStructure($eStructure)->count(), 'aucun réglage n\'est posé');

	}

	/**
	 * L'API note l'entretien sans créer de tâche : elle ne construit pas `task`.
	 */
	public function testApiCreatesNoTask(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		$this->assertFalse(in_array('task', MaintenanceDoneLib::getPropertiesCreate('api'), TRUE), 'l\'API ne construit pas la tâche');

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eMaintenance['equipment'],
			'maintenance' => $eMaintenance,
		]);

		$fw = new \FailWatch();
		$eMaintenanceDone->build(['date', 'cost', 'comment'], ['date' => \Clock::date(), 'cost' => '', 'comment' => '']);
		$this->assertTrue($fw->ok());

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals(1, MaintenanceDone::model()->whereMaintenance($eMaintenance)->count(), 'l\'entretien est noté');
		$this->assertEquals(0, $this->getTasks($eMaintenance)->count(), 'sans tâche au planning');

	}

	/**
	 * Le planning ne prend pas une tâche à plus de cinq ans : la date de l'entretien est refusée sur
	 * son champ, et seulement quand une tâche doit être créée.
	 */
	public function testDateTooFarIsRefusedOnTheDate(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eMaintenance = $this->createMaintenance($eStructure);

		$date = date('Y-m-d', time() - 86400 * 365 * 6);

		[, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['date' => $date]);
		$this->assertTrue($fw->has('Task::done.interval'), '« Fait » à plus de cinq ans est refusé avec le message du planning : '.$fw);

		// Un refus enregistré porte son champ en quatrième position (`Fail::log()`).
		$wrappers = array_column(array_filter($fw->get(), fn(array $fail) => $fail[1] === 'Task::done.interval'), 3);
		$this->assertEquals(['date'], $wrappers, 'le refus se pose sur le champ de la date');

		[, $fw] = $this->build($eMaintenance['equipment'], new Maintenance(), ['date' => $date]);
		$this->assertTrue($fw->ok(), 'une intervention consignée à la même date passe : '.$fw);

	}

	/**
	 * Le réglage par le menu du carnet : une intervention de la catégorie, posée une fois par ferme
	 * et remplacée ensuite.
	 */
	public function testConfigurationIsOnePerStructure(): void {

		$eStructure = $this->getStructure();
		$cAction = MaintenanceConfigurationLib::getActions($eStructure);

		$set = function(string $action) use($eStructure): \FailWatch {

			$eMaintenanceConfiguration = new MaintenanceConfiguration(['structure' => $eStructure]);

			$fw = new \FailWatch();
			$eMaintenanceConfiguration->build(MaintenanceConfigurationLib::getPropertiesCreate(), ['action' => $action, 'readingEveryMonths' => '3']);

			if($fw->ok()) {
				MaintenanceConfigurationLib::create($eMaintenanceConfiguration);
			}

			return $fw;

		};

		$this->assertTrue($set('')->has('MaintenanceConfiguration::action.check'), 'un réglage sans intervention est refusé');

		$eActionCulture = \work\Action::model()
			->select(['id'])
			->whereStructure($eStructure)
			->whereStatus(\work\Action::ACTIVE)
			->whereId('NOT IN', $cAction)
			->get();

		$this->assertTrue($set((string)$eActionCulture['id'])->has('MaintenanceConfiguration::action.usable'), 'une intervention hors de « Sur l\'outil de production » est refusée');

		[$eActionFirst, $eActionSecond] = array_values($cAction->getArrayCopy());

		$this->assertTrue($set((string)$eActionFirst['id'])->ok());
		$this->assertTrue($set((string)$eActionSecond['id'])->ok());

		$this->assertEquals(1, MaintenanceConfiguration::model()->whereStructure($eStructure)->count(), 'la ferme n\'a qu\'un réglage');
		$this->assertEquals($eActionSecond['id'], MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)['action']['id'], 'le second remplace le premier');

	}

	/**
	 * Le rythme du rappel des compteurs : quatre valeurs et elles seules, tous les 3 mois tant que
	 * rien n'est réglé. Il survit à une intervention qui ne vaut plus, et le premier « Fait », qui ne
	 * demande que l'intervention, le laisse tel quel.
	 */
	public function testReadingEveryMonths(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure);

		$cAction = MaintenanceConfigurationLib::getActions($eStructure);
		$eAction = MaintenanceConfigurationLib::getDefaultAction($cAction);
		$eActionOther = $cAction->find(fn(\work\Action $eActionFound) => $eActionFound['id'] !== $eAction['id'], limit: 1, default: new \work\Action());

		if($this->assertTrue($eAction->notEmpty() and $eActionOther->notEmpty(), 'la structure '.static::STRUCTURE.' a « Matériel » et une autre intervention sur l\'outil de production') === FALSE) {
			return;
		}

		$set = function(array $input) use($eStructure, $eAction): \FailWatch {

			$eMaintenanceConfiguration = new MaintenanceConfiguration(['structure' => $eStructure]);

			$fw = new \FailWatch();
			$eMaintenanceConfiguration->build(MaintenanceConfigurationLib::getPropertiesCreate(), ['action' => (string)$eAction['id']] + $input);

			if($fw->ok()) {
				MaintenanceConfigurationLib::create($eMaintenanceConfiguration);
			}

			return $fw;

		};

		$getPanel = function() use($eStructure): MaintenanceConfiguration {

			$eMaintenanceConfiguration = new MaintenanceConfiguration(['structure' => $eStructure]);
			MaintenanceConfigurationLib::fillPanel($eMaintenanceConfiguration);

			return $eMaintenanceConfiguration;

		};

		$this->assertEquals(3, MaintenanceConfigurationLib::getReadingEveryMonths($eStructure), 'sans réglage, le rappel part tous les 3 mois');
		$this->assertEquals([1, 2, 3, 6], MaintenanceConfiguration::READING_EVERY_MONTHS, 'quatre rythmes, et eux seuls');

		// Un champ non posté, vide, hors de la liste dans les bornes du schéma, hors des bornes, et
		// une valeur qui n'est pas un entier.
		foreach([[], ['readingEveryMonths' => ''], ['readingEveryMonths' => '4'], ['readingEveryMonths' => '5'], ['readingEveryMonths' => '0'], ['readingEveryMonths' => '12'], ['readingEveryMonths' => 'trimestre']] as $input) {
			$this->assertTrue($set($input)->has('MaintenanceConfiguration::readingEveryMonths.check'), 'un rythme hors des quatre est refusé sur son champ : '.json_encode($input));
		}

		$this->assertEquals(0, MaintenanceConfiguration::model()->whereStructure($eStructure)->count(), 'et rien n\'est réglé');

		foreach(MaintenanceConfiguration::READING_EVERY_MONTHS as $readingEveryMonths) {

			$fw = $set(['readingEveryMonths' => (string)$readingEveryMonths]);

			$this->assertTrue($fw->ok(), 'tous les '.$readingEveryMonths.' mois se règle : '.$fw);
			$this->assertEquals($readingEveryMonths, MaintenanceConfigurationLib::getReadingEveryMonths($eStructure), 'et se relit');
			$this->assertEquals([TRUE, $eAction['id'], $readingEveryMonths], [$getPanel()['isConfigured'], $getPanel()['action']['id'], $getPanel()['readingEveryMonths']], 'le panneau du réglage s\'ouvre sur ce qui est réglé');

		}

		$this->assertEquals(1, MaintenanceConfiguration::model()->whereStructure($eStructure)->count(), 'la ferme n\'a qu\'un réglage');
		$this->assertEquals(6, MaintenanceConfigurationLib::getReadingEveryMonthsByStructure()[$eStructure['id']] ?? NULL, 'le balayage du parc rend le rythme de la ferme');

		// Sans `build()` ni Lib : le test veut l'état « désactivée », pas le geste qui y mène.
		\work\Action::model()->update($eAction, ['status' => \work\Action::INACTIVE]);

		$cAction = MaintenanceConfigurationLib::getActions($eStructure);

		$this->assertTrue(MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)->empty(), 'l\'intervention désactivée, le réglage est tenu pour absent');
		$this->assertEquals(6, MaintenanceConfigurationLib::getReadingEveryMonths($eStructure), 'le rythme, lui, vaut toujours');
		$this->assertEquals([FALSE, 6], [$getPanel()['isConfigured'], $getPanel()['readingEveryMonths']], 'et le panneau le montre à côté de l\'intervention à choisir');

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, ['action' => $eActionOther['id'], 'readingEveryMonths' => '1']);
		$this->assertTrue($fw->ok(), 'le premier « Fait » passe avec une autre intervention : '.$fw);

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals($eActionOther['id'], MaintenanceConfigurationLib::getByStructure($eStructure, $cAction)['action']['id'], 'il règle l\'intervention');
		$this->assertEquals(6, MaintenanceConfigurationLib::getReadingEveryMonths($eStructure), 'sans toucher au rythme, même posté avec lui');

	}

	/**
	 * Le bloc du planning : sur la semaine en cours, les retards puis les dates de la semaine ; sur une
	 * semaine à venir, les seules dates de cette semaine ; sur une semaine passée, rien.
	 */
	public function testPlanningListsByWeek(): void {

		// Un lundi : la semaine en cours va du 15 au 21 juin 2026.
		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();

		$eMaintenanceOverdue = $this->createMaintenance($eStructure, '2026-05-20', 'Graissage');
		$eMaintenanceFriday = $this->createMaintenance($eStructure, '2026-06-19', 'Vidange moteur');
		$eMaintenanceWednesday = $this->createMaintenance($eStructure, '2026-06-17', 'Filtre à air');
		$eMaintenanceNext = $this->createMaintenance($eStructure, '2026-06-24', 'Pression des pneus');
		$eMaintenanceFar = $this->createMaintenance($eStructure, '2026-09-01', 'Courroie');

		$ids = [$eMaintenanceOverdue['id'], $eMaintenanceFriday['id'], $eMaintenanceWednesday['id'], $eMaintenanceNext['id'], $eMaintenanceFar['id']];

		// La structure du décor peut porter d'autres entretiens : seuls ceux du cas sont comparés.
		$getForWeek = fn(string $week): array => array_values(array_intersect(
			$this->getMaintenanceIds($eStructure, $week),
			$ids
		));

		$this->assertEquals(
			[$eMaintenanceOverdue['id'], $eMaintenanceWednesday['id'], $eMaintenanceFriday['id']],
			$getForWeek('2026-W25'),
			'la semaine en cours liste le retard, puis les dates de la semaine dans l\'ordre'
		);
		$this->assertEquals([$eMaintenanceNext['id']], $getForWeek('2026-W26'), 'la semaine suivante liste sa date, sans le retard');
		$this->assertEquals([], $getForWeek('2026-W27'), 'une semaine sans date ne liste rien');
		$this->assertEquals([], $getForWeek('2026-W21'), 'une semaine passée ne liste rien, même celle du retard');

		$this->setClock('2026-06-18 10:00:00');

		$this->assertEquals(
			[$eMaintenanceOverdue['id'], $eMaintenanceWednesday['id'], $eMaintenanceFriday['id']],
			$getForWeek('2026-W25'),
			'une date de la semaine déjà passée reste listée une fois, comme retard'
		);

	}

	/**
	 * Le bloc du planning reprend les rangées du carnet, nomme le matériel, et ne s'affiche pas quand
	 * il n'y a rien à lister.
	 */
	public function testPlanningBlock(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure, '2026-06-17');

		$cDue = \planning\DueLib::getForWeek($eStructure, '2026-W25')['toolbox']
			->find(fn(\planning\Due $eDue) => $eDue['type'] === PlanningObserverLib::MAINTENANCE and $eDue['reference'] === $eMaintenance['id']);

		$html = new MaintenanceUi()->getPlanning($eStructure, '2026-W25', $cDue, \planning\DueLib::ORIGIN_BLOCK);

		$this->assertTrue(str_contains($html, '/toolbox/maintenanceDone:create?maintenance='.$eMaintenance['id'].'&origin=planning&week=2026-W25'), 'avec son « Fait », qui emporte la semaine');

	}

	/**
	 * Les entretiens que le planning lit pour la semaine, dans l'ordre de l'écran.
	 */
	protected function getMaintenances(\structure\Structure $eStructure, string $week): \Collection {

		$cMaintenance = new \Collection();

		foreach(\planning\DueLib::getForWeek($eStructure, $week)['toolbox'] ?? [] as $eDue) {

			if($eDue['type'] === PlanningObserverLib::MAINTENANCE) {
				$cMaintenance[] = $eDue['eMaintenance'];
			}

		}

		return $cMaintenance;

	}

	protected function getMaintenanceIds(\structure\Structure $eStructure, string $week): array {
		return $this->getMaintenances($eStructure, $week)->getIds();
	}

}
?>
