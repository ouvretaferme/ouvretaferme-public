<?php
namespace planning;

/**
 * L'ordre des blocs de la Semaine, par personne (L5e) : le calcul de l'ordre après un bloc lâché
 * (`TaskWeekLib::applyMove()`), le rangement des blocs qui en découle (`sortBlocks()`), et la liste
 * des actions du mode Personnalisation qui le valide (`TaskWeekLib::getActions()`, R-Personnalisation),
 * les actives de la structure seulement.
 *
 * Les deux calculs sont purs : leurs cas ne lisent pas la base. La lecture des actions passe par la
 * transaction du décor (`HarvestFixtureTest`), qui défait le statut posé. La route `taskWeek:doOrder`
 * s'éprouve par le routeur : l'écriture et sa relecture, le bloc gardé sous le masque, une action hors
 * de la semaine lâchée devant une autre, le refus d'une action inactive ou d'une autre structure, et les
 * refus de `build()`.
 *
 * Lancement : dev/test package=planning file=TaskOrder
 */
class TaskOrderTest extends HarvestFixtureTest {

	/**
	 * La structure dont une action ne doit jamais valider l'ordre d'un membre de `STRUCTURE`.
	 */
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	/**
	 * Le bloc lâché devant un autre passe juste avant lui, et les autres gardent leur suite.
	 */
	public function testMoveBefore(): void {

		$this->assertEquals([3, 1, 2, 4], TaskWeekLib::applyMove([1, 2, 3, 4], 3, 1));
		$this->assertEquals([2, 1, 3, 4], TaskWeekLib::applyMove([1, 2, 3, 4], 1, 3));
		$this->assertEquals([5, 1, 2, 3, 4], TaskWeekLib::applyMove([1, 2, 3, 4, 5], 5, 1));

	}

	/**
	 * Sans voisin, le bloc ferme la liste ; un voisin que l'ordre ne porte pas vaut aussi la fin.
	 */
	public function testMoveToEnd(): void {

		$this->assertEquals([2, 3, 4, 1], TaskWeekLib::applyMove([1, 2, 3, 4], 1, NULL));
		$this->assertEquals([2, 3, 1], TaskWeekLib::applyMove([1, 2, 3], 1, 99));

	}

	/**
	 * Lâché devant son propre suivant, le bloc ne change rien, et aucun déplacement ne fait apparaître de
	 * doublon, le défaut vu à la contre-recette de la reprise g.
	 */
	public function testMoveNeverDuplicates(): void {

		$this->assertEquals([1, 2, 3], TaskWeekLib::applyMove([1, 2, 3], 2, 3));

		$order = [1, 2, 3, 4, 5];

		foreach([[5, 1], [1, NULL], [3, 2], [5, NULL], [2, 4]] as [$action, $before]) {

			$order = TaskWeekLib::applyMove($order, $action, $before);

			$this->assertEquals(count(array_unique($order)), count($order), 'l\'ordre porte un doublon : '.implode(', ', $order));
			$this->assertEquals([1, 2, 3, 4, 5], $this->sorted($order), 'l\'ordre a perdu ou gagné un bloc : '.implode(', ', $order));

		}

	}

	/**
	 * Les blocs de l'ordre d'abord, dans son ordre, puis les autres dans leur ordre d'arrivée. Une action
	 * de l'ordre absente de la semaine ne réserve aucune place.
	 */
	public function testSortBlocks(): void {

		$ccTask = new \Collection();

		foreach([10, 20, 30, 40] as $action) {
			$ccTask[$action] = new \Collection();
		}

		$this->assertEquals([10, 20, 30, 40], TaskWeekLib::sortBlocks($ccTask, [])->getKeys());
		$this->assertEquals([30, 10, 20, 40], TaskWeekLib::sortBlocks($ccTask, [30, 99, 10])->getKeys());
		$this->assertEquals([40, 30, 20, 10], TaskWeekLib::sortBlocks($ccTask, [40, 30, 20, 10])->getKeys());

	}

	/**
	 * La liste du mode Personnalisation : l'ordre posé d'abord, dans son ordre, puis les autres actions
	 * dans l'ordre alphabétique. Ni une action inactive de la structure, ni celle d'une autre structure,
	 * même posées dans l'ordre.
	 */
	public function testGetActions(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		// Relue et non prise au décor, que `once()` garde d'un cas à l'autre : le statut posé ici ne doit
		// pas survivre à la transaction.
		$eActionInactive = \work\ActionLib::getById($this->getAction(ACTION_SEMIS_DIRECT)['id']);
		$eActionOther = \work\ActionLib::getByStructure(\structure\StructureLib::getById(self::STRUCTURE_OTHER), fqn: ACTION_PLANTATION);

		$this->setStatus($eActionInactive, \work\Action::INACTIVE);

		$eAction = $this->getAction(ACTION_PLANTATION);

		$actions = TaskWeekLib::getActions($this->eStructure, [$eActionOther['id'], $eActionInactive['id'], $eAction['id']])->getIds();

		$this->assertEquals($eAction['id'], $actions[0], 'l\'action posée dans l\'ordre ne vient pas en tête : '.json_encode($actions));
		$this->assertFalse(in_array($eActionInactive['id'], $actions, TRUE), 'l\'action inactive est dans la liste');
		$this->assertFalse(in_array($eActionOther['id'], $actions, TRUE), 'l\'action d\'une autre structure est dans la liste');

		$names = TaskWeekLib::getActions($this->eStructure, [$eAction['id']])->find(fn($eActionListed) => $eActionListed['id'] !== $eAction['id'])->getColumn('name');
		$sorted = $names;

		usort($sorted, fn($name1, $name2) => \L::getCollator()->compare($name1, $name2));

		$this->assertEquals($sorted, $names, 'les actions hors de l\'ordre ne suivent pas l\'ordre alphabétique');

	}

	/**
	 * Le bloc lâché en tête y reste au rechargement : `doOrder` écrit l'ordre du membre, la liste du mode
	 * entière, que la Semaine relit. La réponse est vide, la ligne étant déjà à sa place dans la liste.
	 * Lâché sans voisin, comme le client le poste en fin de liste (`before` vide), il revient à la fin.
	 */
	public function testOrderWritesAndRereads(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, \Clock::week());

		[, $dataBefore] = $this->renderTasks();

		$visible = $dataBefore->ccTask->getKeys();
		$last = $visible[count($visible) - 1];

		$actions = TaskWeekLib::getActions($this->eStructure, [])->getIds();

		$action = $this->callOrder($last, $visible[0]);

		$this->assertTrue($action instanceof \VoidAction, 'le lâcher d\'un bloc est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(TaskWeekLib::applyMove($actions, $last, $visible[0]), $this->getOrderStored(), 'l\'ordre écrit en base n\'est pas celui de la liste');

		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

		[, $dataAfter] = $this->renderTasks();

		$this->assertEquals(array_merge([$last], array_slice($visible, 0, -1)), $dataAfter->ccTask->getKeys(), 'la Semaine rechargée ne relit pas l\'ordre');

		$this->callOrder($last, NULL);

		$order = $this->getOrderStored();

		$this->assertEquals($last, $order[count($order) - 1], 'le bloc lâché sans voisin ne revient pas à la fin');

	}

	/**
	 * L'action d'une autre structure ne se déplace pas, ni ne sert de voisin, et l'ordre reste tel quel.
	 */
	public function testOrderRefusesOtherStructure(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$eActionOther = \work\ActionLib::getByStructure(\structure\StructureLib::getById(self::STRUCTURE_OTHER), fqn: ACTION_PLANTATION);

		$this->assertTrue($this->callOrder($eActionOther['id'], NULL) instanceof \NotExpectedAction, 'l\'action d\'une autre structure se déplace');
		$this->assertTrue($this->callOrder($this->getAction(ACTION_PLANTATION)['id'], $eActionOther['id']) instanceof \NotExpectedAction, 'l\'action d\'une autre structure sert de voisin');

		$eActionInactive = $this->createAction();

		$this->setStatus($eActionInactive, \work\Action::INACTIVE);

		// Une action inactive n'excuse pas son voisin : le refus nommé ne vaut que si tout ce qui manque à la liste est inactif.
		$this->assertTrue($this->callOrder($eActionInactive['id'], $eActionOther['id']) instanceof \NotExpectedAction, 'une action inactive a pour voisin l\'action d\'une autre structure');
		$this->assertTrue($this->callOrder($eActionInactive['id'], 2147483647) instanceof \NotExpectedAction, 'une action inactive a pour voisin une action qui n\'existe pas');
		$this->assertEquals(NULL, $this->getOrderStored(), 'un lâcher refusé a écrit l\'ordre');

	}

	/**
	 * La liste du mode montre toutes les actions actives, celles qui n'ont pas de bloc cette semaine
	 * comprises : une action hors de la semaine, lâchée devant une autre qui n'y est pas non plus, est
	 * enregistrée juste devant elle, et la liste la rend ainsi au rechargement. Une action inactive ne se
	 * déplace pas, ni ne sert de voisin, et l'ordre reste tel quel.
	 */
	public function testOrderOutsideWeek(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		// Le voisin est créé d'abord : son nom passe alors devant au collator, et seul l'ordre enregistré
		// peut rendre l'action lâchée devant lui.
		$eActionBefore = $this->createAction();
		$eActionMoved = $this->createAction();

		$action = $this->callOrder($eActionMoved['id'], $eActionBefore['id']);

		$this->assertTrue($action instanceof \VoidAction, 'le lâcher d\'une action hors de la semaine est refusé : '.implode(', ', $this->getFails($action)));

		$order = $this->getOrderStored() ?? [];
		$position = array_search($eActionBefore['id'], $order, TRUE);

		$this->assertTrue($position !== FALSE and $position > 0 and $order[$position - 1] === $eActionMoved['id'], 'l\'action n\'est pas enregistrée juste devant son voisin : '.json_encode($order));

		$actions = TaskWeekLib::getActions($this->eStructure, $order)->getIds();
		$position = array_search($eActionBefore['id'], $actions, TRUE);

		$this->assertTrue($position > 0 and $actions[$position - 1] === $eActionMoved['id'], 'la liste rechargée ne rend pas l\'action devant son voisin : '.json_encode($actions));

		$eActionInactive = $this->createAction();

		$this->setStatus($eActionInactive, \work\Action::INACTIVE);

		$cases = [
			'une action inactive se déplace' => [$eActionInactive['id'], NULL],
			'une action inactive sert de voisin' => [$eActionMoved['id'], $eActionInactive['id']],
		];

		foreach($cases as $case => [$action, $before]) {
			$this->assertEquals(['Member::viewPlanningOrder.inactive'], $this->getFails($this->callOrder($action, $before)), $case);
		}

		$this->assertTrue(\structure\AlertUi::getError('Member::viewPlanningOrder.inactive', []) !== NULL, 'le refus d\'une action inactive n\'a pas de message');
		$this->assertEquals($order, $this->getOrderStored(), 'un lâcher refusé a écrit l\'ordre');

	}

	/**
	 * Une action désactivée garde son rang dans l'ordre enregistré, que la liste ne montre plus : réactivée,
	 * elle le reprend au lieu de passer en fin (`reactivation.md`). Un lâcher en fin de liste se pose derrière
	 * la dernière action active, et une action supprimée sort de l'ordre.
	 */
	public function testOrderKeepsInactive(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$eActionFirst = $this->createAction();
		$eActionX = $this->createAction();
		$eActionMoved = $this->createAction();
		$eActionDeleted = $this->createAction();

		$this->callOrder($eActionX['id'], $this->getActionsShown()[0]);
		$this->callOrder($eActionFirst['id'], $eActionX['id']);
		$this->callOrder($eActionDeleted['id'], $eActionFirst['id']);

		\work\ActionLib::delete($eActionDeleted);

		$this->setStatus($eActionX, \work\Action::INACTIVE);

		$actionsShown = $this->getActionsShown();

		$this->assertTrue($this->callOrder($eActionMoved['id'], $actionsShown[count($actionsShown) - 1]) instanceof \VoidAction, 'le lâcher devant une action active est refusé');
		$this->assertEquals(1, array_search($eActionX['id'], $this->getOrderStored(), TRUE), 'l\'ordre enregistré perd le rang de l\'action inactive : '.json_encode($this->getOrderStored()));
		$this->assertFalse(in_array($eActionDeleted['id'], $this->getOrderStored(), TRUE), 'l\'action supprimée reste dans l\'ordre');

		$this->assertTrue($this->callOrder($eActionMoved['id'], NULL) instanceof \VoidAction, 'le lâcher en fin de liste est refusé');

		$order = $this->getOrderStored();

		$this->assertEquals(1, array_search($eActionX['id'], $order, TRUE), 'un lâcher en fin de liste déplace l\'action inactive : '.json_encode($order));
		$this->assertEquals($eActionMoved['id'], $order[count($order) - 1], 'l\'action lâchée en fin de liste ne ferme pas l\'ordre : '.json_encode($order));

		$this->setStatus($eActionX, \work\Action::ACTIVE);

		$this->assertEquals([$eActionFirst['id'], $eActionX['id']], array_slice($this->getActionsShown(), 0, 2), 'l\'action réactivée ne reprend pas son rang');

	}

	/**
	 * Au milieu de la liste, l'action lâchée se pose devant le bloc qu'elle précède à l'écran, donc derrière
	 * les inactives qui le précèdent dans l'ordre (remarque 65 du relecteur, acceptée par V-Planning).
	 */
	public function testOrderInactiveInMiddle(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$eActionA = $this->createAction();
		$eActionX = $this->createAction();
		$eActionB = $this->createAction();
		$eActionC = $this->createAction();

		$this->callOrder($eActionC['id'], $this->getActionsShown()[0]);
		$this->callOrder($eActionB['id'], $eActionC['id']);
		$this->callOrder($eActionX['id'], $eActionB['id']);
		$this->callOrder($eActionA['id'], $eActionX['id']);

		$this->setStatus($eActionX, \work\Action::INACTIVE);

		$this->assertTrue($this->callOrder($eActionA['id'], $eActionC['id']) instanceof \VoidAction, 'le lâcher devant une action active est refusé');

		$order = $this->getOrderStored();

		$this->assertEquals([$eActionX['id'], $eActionB['id'], $eActionA['id'], $eActionC['id']], array_slice($order, 0, 4), 'l\'action lâchée ne se pose pas devant son voisin, derrière l\'inactive : '.json_encode($order));

	}

	/**
	 * Les actions inactives qui fermaient la liste la ferment encore, dans leur suite : un lâcher en fin de
	 * liste se pose devant elles, à la fin de ce que l'écran montrait (`reactivation.md`).
	 */
	public function testOrderKeepsInactiveLast(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$eActionFirst = $this->createAction();
		$eActionSecond = $this->createAction();
		$eActionMoved = $this->createAction();

		$this->callOrder($eActionFirst['id'], NULL);
		$this->callOrder($eActionSecond['id'], NULL);

		$this->setStatus($eActionFirst, \work\Action::INACTIVE);
		$this->setStatus($eActionSecond, \work\Action::INACTIVE);

		$this->assertTrue($this->callOrder($eActionMoved['id'], NULL) instanceof \VoidAction, 'le lâcher en fin de liste est refusé');

		$order = $this->getOrderStored();

		$this->assertEquals([$eActionMoved['id'], $eActionFirst['id'], $eActionSecond['id']], array_slice($order, -3), 'les actions inactives ne ferment plus l\'ordre : '.json_encode($order));

		$this->setStatus($eActionFirst, \work\Action::ACTIVE);
		$this->setStatus($eActionSecond, \work\Action::ACTIVE);

		$this->assertEquals([$eActionMoved['id'], $eActionFirst['id'], $eActionSecond['id']], array_slice($this->getActionsShown(), -3), 'les actions réactivées ne ferment plus la liste');

	}

	/**
	 * Sous « Masquer ce qui est fait », un bloc dont on vient de cocher la dernière carte reste à l'écran
	 * jusqu'au rechargement (charte, point 10 e), et deux peuvent l'être à la fois. Le client les compte
	 * parmi ses blocs, le serveur ne les voit plus : le bloc lâché passe devant son voisin de l'écran, et
	 * non à un rang que seul le client sait compter (V-Planning et architecte, 02/10).
	 */
	public function testOrderKeptBlockUnderHide(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, \Clock::week());

		$eActionKept = $this->createAction();
		$eTaskKept = $this->createTaskOn($eActionKept);

		$eActionMoved = $this->createAction();
		$eTaskMoved = $this->createTaskOn($eActionMoved);

		$this->setHideDone(TRUE);

		foreach([$eTaskKept, $eTaskMoved] as $eTask) {

			[, , $actionDone] = $this->callTask('doDone', $eTask);

			$this->assertTrue($actionDone instanceof \ViewAction, 'la dernière carte d\'un bloc ne se coche pas : '.implode(', ', $this->getFails($actionDone)));

		}

		$actionBefore = $this->getAction(ACTION_SEMIS_DIRECT)['id'];

		$action = $this->callOrder($eActionMoved['id'], $actionBefore);

		$this->assertTrue($action instanceof \VoidAction, 'le bloc gardé à l\'écran sous le masque ne se déplace pas : '.implode(', ', $this->getFails($action)));

		$order = $this->getOrderStored();

		$this->assertEquals([$eActionMoved['id'], $actionBefore], array_slice($order, (int)array_search($eActionMoved['id'], $order, TRUE), 2), 'le bloc gardé n\'est pas juste devant son voisin de l\'écran : '.json_encode($order));
		$this->assertTrue(in_array($eActionKept['id'], $order, TRUE), 'l\'autre bloc gardé est sorti de l\'ordre : '.json_encode($order));

	}

	/**
	 * Posté sur une structure dont l'utilisateur n'est pas membre, le lâcher est refusé par
	 * `canPlanningLimited`, validé par le constructeur de la page, avant toute lecture de l'ordre.
	 */
	public function testOrderOtherStructure(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$action = $this->executePage('planning/taskWeek:doOrder', [
			'id' => self::STRUCTURE_OTHER,
			'week' => \Clock::week(),
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'before' => '',
		], 'POST');

		$this->assertTrue($action instanceof \NotAllowedAction, 'le lâcher sur une autre structure n\'est pas refusé par `canPlanningLimited`');

	}

	/**
	 * `build()` refuse tout autre chemin d'écriture d'un ordre faux : un doublon, l'action d'une autre
	 * structure, une valeur non entière. Le refus a son message.
	 */
	public function testOrderRefusedByBuild(): void {

		if($this->requireActions() === FALSE) {
			return;
		}

		$eAction = $this->getAction(ACTION_PLANTATION);
		$eActionOther = \work\ActionLib::getByStructure(\structure\StructureLib::getById(self::STRUCTURE_OTHER), fqn: ACTION_PLANTATION);

		$eMember = new \structure\Member([
			'cAction' => TaskWeekLib::getActions($this->eStructure, []),
		]);

		$cases = [
			'un doublon' => [$eAction['id'], $eAction['id']],
			'l\'action d\'une autre structure' => [$eAction['id'], $eActionOther['id']],
			'une valeur non entière' => [(string)$eAction['id']],
		];

		foreach($cases as $case => $order) {

			$fw = new \FailWatch();

			$eMember->build(['viewPlanningOrder'], ['viewPlanningOrder' => $order]);

			$this->assertTrue($fw->has('Member::viewPlanningOrder.actions'), $case.' passe build() : '.implode(', ', array_column($fw->get(), 1)));

		}

		$fw = new \FailWatch();

		$eMember->build(['viewPlanningOrder'], ['viewPlanningOrder' => [$eAction['id']]]);

		$this->assertTrue($fw->ok(), 'un ordre juste est refusé : '.implode(', ', array_column($fw->get(), 1)));
		$this->assertTrue(\structure\AlertUi::getError('Member::viewPlanningOrder.actions', []) !== NULL, 'le refus n\'a pas de message');

	}

	/**
	 * Deux actions de la structure, pour deux blocs, et la même action dans la structure voisine.
	 */
	private function requireActions(): bool {

		return (
			$this->requireAction(ACTION_PLANTATION) and
			$this->requireAction(ACTION_SEMIS_DIRECT) and
			$this->assertTrue(\work\ActionLib::getByStructure(\structure\StructureLib::getById(self::STRUCTURE_OTHER), fqn: ACTION_PLANTATION)->notEmpty(), 'la structure '.self::STRUCTURE_OTHER.' a une action « '.ACTION_PLANTATION.' » active')
		);

	}

	/**
	 * L'ordre du membre connecté, lu en base et non sur le membre en cache, que `build()` a déjà touché.
	 */
	private function getOrderStored(): ?array {

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		return \structure\Member::model()->select(['viewPlanningOrder'])->whereId($eMember['id'])->get()['viewPlanningOrder'];

	}

	/** La liste du mode Personnalisation telle que la personne la voit, sur l'ordre enregistré. */
	private function getActionsShown(): array {

		return TaskWeekLib::getActions($this->eStructure, $this->getOrderStored() ?? [])->getIds();

	}

	private function setStatus(\work\Action $eAction, string $status): void {

		// Sans `build()`, qui n'aurait vérifié que l'appartenance à l'enum : la valeur est la constante du
		// module, et le test ne poste rien.
		$eAction['status'] = $status;
		\work\ActionLib::update($eAction, ['status']);

	}

	/** Une action neuve de la structure, dans la catégorie Culture, pour un bloc qui n'a que les cartes du test. */
	private function createAction(): \work\Action {

		$eAction = new \work\Action([
			'structure' => $this->eStructure,
		]);

		$fw = new \FailWatch();

		$eAction->build(\work\ActionLib::getPropertiesCreate(), [
			'name' => 'Action du décor '.uniqid(),
			'categories' => [$this->getCategoryCulture()['id']],
			'color' => '#112233',
		]);

		$fw->validate();

		\work\ActionLib::create($eAction);

		return $eAction;

	}

	private function createTaskOn(\work\Action $eAction): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $this->getCultivation()['series'],
			'cultivation' => $this->getCultivation(),
			'plant' => $this->getCultivation()['plant'],
			'action' => $eAction,
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, qui n'a rien à vérifier ici : le décor pose une intervention à faire, prévue cette
		// semaine, sur une action qu'il vient de créer (forme de `FingerprintTest::testTaskBatchEmptiesBlock()`).
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * Le lâcher tel que le client le poste : `before` vide quand le bloc ferme la liste.
	 */
	private function callOrder(int $action, ?int $before): ?\Action {

		return $this->executePage('planning/taskWeek:doOrder', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'action' => $action,
			'before' => ($before === NULL) ? '' : $before,
		], 'POST');

	}

	private function sorted(array $order): array {

		sort($order);

		return $order;

	}

}
?>
