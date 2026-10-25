<?php
namespace planning;

/**
 * Les deux zones sous la Semaine (L8), « En retard » et « Non planifié », contre la règle de l'ancien
 * planning qu'elles appellent (`\series\TaskLib::getForWeekDelayed()`, `getByUnplanned()`) : mêmes
 * interventions, moins celles qui portent du temps ou une récolte dans la semaine et sont donc déjà dans
 * la Semaine. Puis les bornes de la règle (la profondeur `planningDelayedMax`, rien pour une semaine à
 * venir, les séries clôturées écartées) et les refus de « Voir les N autres ». La recherche y lit les zones entières, et ses suggestions portent leur vignette
 * (R-Recherche-2, `testSearch*`).
 *
 * Le décor est posé dans la transaction du test, sur la structure du décor (`HarvestFixtureTest`), à
 * l'horloge figée de la saison.
 *
 * Lancement : dev/test package=planning file=TaskLate
 */
class TaskLateTest extends HarvestFixtureTest {

	/**
	 * Les mêmes interventions que l'ancien planning, moins celles de la semaine : une en retard et une
	 * sans semaine qui portent du temps cette semaine sont absentes des deux zones et présentes une seule
	 * fois dans la Semaine.
	 */
	public function testSameAsOldPlanning(): void {

		$week = \Clock::week();

		$eTaskLate = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$eTaskLateTimed = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$eTaskUnplanned = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);
		$eTaskUnplannedTimed = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		$this->createTimesheet($eTaskLateTimed, 0.5, \Clock::date());
		$this->createTimesheet($eTaskUnplannedTimed, 0.5, \Clock::date());

		$inWeek = array_merge(
			\series\TimesheetLib::getTasksByWeek($this->eStructure, $week)->getIds(),
			\series\HarvestLib::getTasksByWeek($this->eStructure, $week)->getIds()
		);

		$expected = [
			TaskLateLib::LATE => array_diff($this->getOldIds(\series\TaskLib::getForWeekDelayed($this->eStructure, $week)), $inWeek),
			TaskLateLib::UNPLANNED => array_diff($this->getOldIds(\series\TaskLib::getByUnplanned($this->eStructure, $week)), $inWeek),
		];

		foreach($expected as $zone => $ids) {

			$keys = TaskLateLib::getKeys($this->eStructure, $zone, $week)->getIds();

			sort($ids);
			sort($keys);

			$this->assertEquals(array_values($ids), $keys, $zone.' : les clés ne sont pas les interventions de l\'ancien planning moins celles de la semaine');

		}

		$late = TaskLateLib::getKeys($this->eStructure, TaskLateLib::LATE, $week);
		$unplanned = TaskLateLib::getKeys($this->eStructure, TaskLateLib::UNPLANNED, $week);

		$this->assertTrue($late->offsetExists($eTaskLate['id']), 'l\'intervention en retard n\'est pas dans « En retard »');
		$this->assertTrue($unplanned->offsetExists($eTaskUnplanned['id']), 'l\'intervention sans semaine n\'est pas dans « Non planifié »');

		foreach([$eTaskLateTimed, $eTaskUnplannedTimed] as $eTask) {
			$this->assertFalse($late->offsetExists($eTask['id']) or $unplanned->offsetExists($eTask['id']), 'une intervention qui porte du temps cette semaine est encore dans une zone ('.$eTask['id'].')');
		}

		[$document] = $this->renderTasks(unplanned: TRUE);

		foreach([$eTaskLateTimed, $eTaskUnplannedTimed, $eTaskLate, $eTaskUnplanned] as $eTask) {
			$this->assertEquals(1, $document->querySelectorAll('#planning-task-'.$eTask['id'])->length, 'la carte '.$eTask['id'].' n\'est pas une seule fois à l\'écran');
		}

	}

	/**
	 * La profondeur du retard est celle de la structure, et une semaine à venir n'a aucun retard.
	 */
	public function testBounds(): void {

		$months = $this->eStructure->conf(\map\Configuration::class)['planningDelayedMax'];

		$eTaskDeep = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - '.($months + 1).' MONTHS'));
		$eTaskLate = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		$late = TaskLateLib::getKeys($this->eStructure, TaskLateLib::LATE, \Clock::week());

		$this->assertTrue($late->offsetExists($eTaskLate['id']), 'l\'intervention de la semaine d\'avant n\'est pas en retard');
		$this->assertFalse($late->offsetExists($eTaskDeep['id']), 'une intervention au-delà de '.$months.' mois est en retard');

		$future = toWeek(\Clock::date().' + 14 DAYS');

		$this->assertEquals(0, TaskLateLib::getKeys($this->eStructure, TaskLateLib::LATE, $future)->count(), 'une semaine à venir a du retard');

	}

	/**
	 * Une intervention d'une série clôturée n'est dans aucune zone, comme dans l'ancien planning.
	 */
	public function testClosedSeries(): void {

		$eTaskLate = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$eTaskUnplanned = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		$eSeries = $this->getCultivation()['series'];
		$eSeries['status'] = \series\Series::CLOSED;

		// Sans `build()`, qui vérifierait le statut posté : le décor clôt sa propre série, comme la clôture
		// des séries (`SeriesLib::closeCollection()`).
		\series\SeriesLib::update($eSeries, ['status']);

		foreach([TaskLateLib::LATE => $eTaskLate, TaskLateLib::UNPLANNED => $eTaskUnplanned] as $zone => $eTask) {
			$this->assertFalse(TaskLateLib::getKeys($this->eStructure, $zone, \Clock::week())->offsetExists($eTask['id']), $zone.' : une intervention d\'une série clôturée est dans la zone');
		}

	}

	/**
	 * Les clés et les cartes : la page hydrate une carte par clé, et les comptes des blocs et de la zone sont
	 * ceux des cartes.
	 */
	public function testKeysAreCards(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);
		$this->createTasksLate($this->createActionLate(), 1);

		[, $data] = $this->renderTasks(unplanned: TRUE);

		foreach($data->zones as $name => $zone) {

			$keys = TaskLateLib::getIds($zone);
			$cards = $zone['cTask']->getIds();

			sort($keys);
			sort($cards);

			$this->assertEquals($keys, $cards, $name.' : les cartes ne sont pas les clés de la zone');
			$this->assertEquals(count($cards), $zone['count'], $name.' : le compte de la zone n\'est pas celui des cartes');

			foreach($zone['blocks'] as $action => $block) {
				$this->assertEquals($block['count'], $zone['cTask']->find(fn(\series\Task $eTask) => $eTask['action']['id'] === $action)->count(), $name.' : le bloc '.$action.' n\'a pas toutes ses cartes sous le seuil');
			}

		}

	}

	/**
	 * Au-delà du seuil, un bloc ne montre que ses `LIMIT` premières clés et « Voir les N autres » s'ouvre sur
	 * le bloc ; une zone ou une action inconnues sont refusées.
	 */
	public function testCapped(): void {

		$eAction = $this->createActionLate();

		$this->createTasksLate($eAction, TaskLateLib::LIMIT + 2);

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			[, $data] = $this->renderTasks();

			$block = $data->zones[TaskLateLib::LATE]['blocks'][$eAction['id']] ?? NULL;

			if($this->assertTrue($block !== NULL, 'le bloc de l\'action du décor n\'est pas dans « En retard »')) {
				$this->assertEquals([TaskLateLib::LIMIT + 2, array_slice($block['ids'], 0, TaskLateLib::LIMIT)], [$block['count'], $block['shown']], 'le bloc plafonné ne montre pas ses '.TaskLateLib::LIMIT.' premières clés, sur toutes celles qu\'il compte');
			}

			$action = $this->executePage('planning/taskWeek:more', [
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'zone' => TaskLateLib::LATE,
				'action' => $eAction['id'],
			], 'GET');

			$this->assertTrue($action instanceof \ViewAction, '« Voir les autres » est refusé');

			foreach([['zone' => 'other', 'action' => $eAction['id']], ['zone' => TaskLateLib::LATE, 'action' => 0]] as $input) {

				$action = $this->executePage('planning/taskWeek:more', $input + [
					'id' => $this->eStructure['id'],
					'week' => \Clock::week(),
				], 'GET');

				$this->assertTrue($action instanceof \NotExpectedAction, '« Voir les autres » accepte '.json_encode($input));

			}

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * R-Recherche-2 · Ce que la recherche de la Semaine lit dans une zone sans la charger
	 * (`TaskSearchLib::getZone()`) est ce que porteraient ses cartes chargées entières, sans plafond : les mêmes
	 * interventions, les mêmes valeurs, et l'action de chacune, qui nomme son bloc. « Non planifié » se lit avant
	 * sa première ouverture.
	 */
	public function testSearchZone(): void {

		$eTaskUnplanned = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);
		// Affectée par le geste du produit, sans temps : la personne ne vient que de l'affectation.
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskUnplanned['id'])]), $this->getMemberPlanning(), 'add');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $this->getWeekBefore());

		foreach([TaskLateLib::LATE, TaskLateLib::UNPLANNED] as $name) {

			$zone = TaskLateLib::getZone($this->eStructure, $name, \Clock::week(), TRUE);

			foreach($zone['blocks'] as $action => $block) {
				TaskLateLib::fillBlock($this->eStructure, \Clock::week(), $zone, $action);
			}

			$cards = [];
			$actions = [];

			foreach($zone['blocks'] as $action => $block) {

				foreach($block['ids'] as $id) {
					$cards[$id] = $zone['cTask'][$id]['search'];
					$actions[$id] = $action;
				}

			}

			$search = TaskSearchLib::getZone($this->eStructure, $name, \Clock::week());

			ksort($cards);
			ksort($actions);
			ksort($search['cards']);
			ksort($search['actions']);

			$this->assertEquals($cards, $search['cards'], 'la recherche ne lit pas dans « '.$name.' » ce que porteraient ses cartes entières');
			$this->assertEquals($actions, $search['actions'], 'la recherche ne range pas les cartes de « '.$name.' » dans leur bloc');

		}

		$search = TaskSearchLib::getZone($this->eStructure, TaskLateLib::UNPLANNED, \Clock::week());

		$this->assertEquals([$this->getMemberPlanning()->getName()], $search['cards'][$eTaskUnplanned['id']]['user'] ?? NULL, '« Non planifié » ne porte pas la personne affectée sans temps');
		$this->assertEquals([$this->getCategoryCulture()['name']], $search['cards'][$eTaskUnplanned['id']]['category'] ?? NULL, '« Non planifié » ne porte pas la catégorie de l\'intervention');

	}

	/**
	 * R-Recherche-2 · Sous un jeton, « Non planifié » suit le filtre avant sa première ouverture
	 * (contre-recette de R-Recherche du 2026-10-03, la zone qui annonçait 10 interventions et s'effaçait à
	 * l'ouverture) : le serveur dit ce qu'il y retient, par bloc, rien quand aucune ne correspond, et chaque
	 * intervention retenue a sa carte quand la zone s'ouvre.
	 */
	public function testSearchUnplanned(): void {

		$eTaskUnplanned = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskUnplanned['id'])]), $this->getMemberPlanning(), 'add');

		$matches = $this->callSearch(['user' => [$this->getMemberPlanning()->getName()]]);

		if($this->assertTrue($matches !== NULL, 'la recherche des zones est refusée') === FALSE) {
			return;
		}

		$ids = array_merge(...array_values($matches[TaskLateLib::UNPLANNED]));

		$this->assertTrue(in_array($eTaskUnplanned['id'], $matches[TaskLateLib::UNPLANNED][$this->getAction(ACTION_PLANTATION)['id']] ?? [], TRUE), 'la recherche ne retrouve pas dans son bloc l\'intervention de « Non planifié » affectée');

		[$document] = $this->renderTasks(unplanned: TRUE);

		foreach($ids as $id) {
			$this->assertTrue($document->getElementById('planning-task-'.$id) !== NULL, 'l\'intervention '.$id.' retenue dans « Non planifié » n\'a pas de carte à l\'ouverture de la zone');
		}

		$matches = $this->callSearch(['plant' => ['Espèce absente du décor '.uniqid()]]);

		$this->assertEquals([TaskLateLib::LATE => [], TaskLateLib::UNPLANNED => []], $matches, 'une recherche sans résultat retient encore des interventions dans les zones');

	}

	/**
	 * R-Recherche-2 · Sous un jeton, une carte retenue au-delà du plafond d'un bloc est connue du serveur dans
	 * son bloc, et « Voir les N autres », que l'écran ouvre alors de lui-même, la montre : un compte n'annonce
	 * jamais une carte que l'écran ne peut pas montrer (écart 3 refusé par V-Planning).
	 */
	public function testSearchCapped(): void {

		$eAction = $this->createActionLate();

		$this->createTasksLate($eAction, TaskLateLib::LIMIT + 2);

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$block = TaskLateLib::getZone($this->eStructure, TaskLateLib::LATE, \Clock::week(), TRUE)['blocks'][$eAction['id']];
			$hidden = array_values(array_diff($block['ids'], $block['shown']));

			if($this->assertEquals(2, count($hidden), 'le bloc du décor ne cache pas deux interventions au-delà du plafond') === FALSE) {
				return;
			}

			\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($hidden[0])]), $this->getMemberPlanning(), 'add');

			$matches = $this->callSearch(['action' => [$eAction['name']], 'user' => [$this->getMemberPlanning()->getName()]]);

			$this->assertEquals([$hidden[0]], $matches[TaskLateLib::LATE][$eAction['id']] ?? NULL, 'la recherche ne retrouve pas dans son bloc la carte cachée au-delà du plafond');

			[$document] = $this->renderTasks();

			$this->assertTrue($document->getElementById('planning-task-'.$hidden[0]) === NULL, 'la carte retenue est déjà à l\'écran, le cas ne se monte pas');
			$this->assertTrue($document->querySelector('#planning-week-late-block-'.$eAction['id'].' .planning-week-more a[data-ajax]') !== NULL, 'le bloc qui cache la carte retenue n\'offre pas « Voir les N autres »');

			$action = $this->executePage('planning/taskWeek:more', [
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'zone' => TaskLateLib::LATE,
				'action' => $eAction['id'],
			], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, '« Voir les autres » est refusé') === FALSE) {
				return;
			}

			[, $template] = $this->runView('more', $action, 'taskWeek');

			$more = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$template->formatInstructions()[0][2][0][1][0].'</body></html>', LIBXML_NOERROR);
			$node = $more->querySelector('#planning-task-'.$hidden[0].' [data-search]');

			if($this->assertTrue($node !== NULL, '« Voir les autres » ne montre pas la carte retenue') === FALSE) {
				return;
			}

			$this->assertTrue(in_array($this->getMemberPlanning()->getName(), json_decode($node->getAttribute('data-search'), TRUE)['user'] ?? [], TRUE), 'la carte montrée ne porte pas ce que la recherche a retenu');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * R-Recherche-2 · Des jetons forgés sont refusés par la recherche des zones.
	 */
	public function testSearchRefused(): void {

		foreach([
			['variety' => ['Planche 4']],
			['action' => [['x']]],
			['action' => 'Plantation'],
			['action' => []],
		] as $criteria) {

			$action = $this->executePage('planning/taskWeek:search', [
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'criteria' => $criteria,
			], 'GET');

			$this->assertTrue($action instanceof \NotExpectedAction, 'la recherche des zones accepte des jetons forgés : '.json_encode($criteria));

		}

	}

	/**
	 * R-Recherche-2 (retours 14 et 15 du 03/10) · Chaque personne et chaque espèce que la recherche peut
	 * suggérer porte sa vignette, à la Semaine, au Jour et à l'Année : l'avatar du membre sur l'enveloppe de
	 * la recherche, et l'espèce sur sa carte, que chaque réponse redessine, le symbole du référentiel ou la
	 * photo ou les initiales d'une espèce propre à la structure. L'Année, qui ne charge pas toutes ses cartes,
	 * rend les vignettes avec ses suggestions.
	 */
	public function testSearchVignettes(): void {

		$name = 'Espèce propre '.uniqid();

		// Sans build() : le décor veut une espèce sans `fqn`, propre à la structure, que le formulaire de
		// l'espèce créerait aussi, mais depuis un POST qu'un test n'a pas à fabriquer.
		$ePlantOwn = new \plant\Plant(['structure' => $this->eStructure, 'name' => $name, 'cycle' => \plant\Plant::ANNUAL, 'family' => new \plant\Family(), 'color' => '#373737']);
		\plant\PlantLib::create($ePlantOwn);

		$eCultivationOwn = $this->createCultivation($this->getSeries(), $ePlantOwn);

		$eTaskOwn = $this->createTask($eCultivationOwn, ACTION_PLANTATION, \series\Task::TODO, NULL);
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskOwn['id'])]), $this->getMemberPlanning(), 'add');

		// Sans build() non plus, pour la même raison : une espèce du référentiel se reconnaît à son `fqn`, que seul
		// un import pose. Le symbole qu'elle désigne n'a pas à exister, la carte n'en porte que l'adresse.
		$ePlantReference = new \plant\Plant(['structure' => $this->eStructure, 'fqn' => 'vignette-'.uniqid(), 'name' => 'Espèce du référentiel '.uniqid(), 'cycle' => \plant\Plant::ANNUAL, 'family' => new \plant\Family(), 'color' => '#373737']);
		\plant\PlantLib::create($ePlantReference);

		$eTaskReference = $this->createTask($this->createCultivation($this->getSeries(), $ePlantReference), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$member = $this->getMemberPlanning()->getName();

		[$document] = $this->renderTasks(unplanned: TRUE);

		$vignettes = $this->getSearchVignettes($document);

		$this->assertTrue(str_contains($vignettes['user'][$member]['vignette'] ?? '', 'media-circle-view'), 'la Semaine ne porte pas l\'avatar de la personne');

		$plant = $this->getSearchPlant($document, $eTaskOwn);

		$this->assertTrue(str_contains($plant['vignette'] ?? '', 'media-circle-view'), 'la carte d\'une espèce propre à la structure ne porte pas sa vignette');
		$this->assertFalse(isset($plant['icon']), 'une espèce propre à la structure reçoit un symbole du référentiel');

		$this->assertEquals(['icon' => \plant\PlantUi::getVignetteHref($ePlantReference['fqn'])], $this->getSearchPlant($document, $eTaskReference), 'la carte d\'une espèce du référentiel ne porte pas son symbole');

		[$document] = $this->renderTaskDay();

		$vignettes = $this->getSearchVignettes($document);

		$this->assertTrue(str_contains($vignettes['user'][$member]['vignette'] ?? '', 'media-circle-view'), 'le Jour ne porte pas l\'avatar de la personne');
		$this->assertTrue($this->getSearchPlant($document, $eTaskReference) !== [], 'au Jour, la carte ne porte pas la vignette de son espèce');

		// Les suggestions de l'Année ne viennent que des interventions de ses semaines : l'espèce propre et la
		// personne y sont suggérées par une intervention prévue cette semaine.
		$eTaskOwnPlanned = $this->createTask($eCultivationOwn, ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		\series\TaskLib::updateUser(new \Collection([\series\TaskLib::getById($eTaskOwnPlanned['id'])]), $this->getMemberPlanning(), 'add');

		[$data] = $this->callYear('query', ['query' => $name]);

		$suggestions = new TabUi()->getSearchSuggestions($data->suggestions, $data->cUser, $data->cPlant);
		$suggestion = array_find($suggestions, fn(array $suggestion) => $suggestion['label'] === $name);

		$this->assertTrue(str_contains($suggestion['vignette'] ?? '', 'media-circle-view'), 'à l\'Année, l\'espèce propre à la structure est suggérée sans sa vignette');

		[$data] = $this->callYear('query', ['query' => $member]);

		$suggestions = new TabUi()->getSearchSuggestions($data->suggestions, $data->cUser, $data->cPlant);
		$suggestion = array_find($suggestions, fn(array $suggestion) => $suggestion['label'] === $member);

		$this->assertTrue(str_contains($suggestion['vignette'] ?? '', 'media-circle-view'), 'à l\'Année, la personne est suggérée sans son avatar');

	}

	/**
	 * La tête d'une zone, sur des zones écrites à la main : elle ouvre et ferme la zone (retours
	 * du 05/10, lot E). Non chargée, la zone ne lit aucune carte.
	 */
	public function testZoneHead(): void {

		$uiTaskWeek = new TaskWeekUi();

		foreach([TaskLateLib::LATE => [5, TRUE], TaskLateLib::UNPLANNED => [9, FALSE]] as $name => [$count, $open]) {

			$zone = ['zone' => $name, 'count' => $count, 'open' => $open, 'loaded' => FALSE, 'blocks' => []];

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$uiTaskWeek->getZone($this->eStructure, \Clock::week(), $zone, new \Collection(), FALSE, FALSE).'</body></html>', LIBXML_NOERROR);
			$fold = $document->getElementById('planning-week-'.$name);

			if($this->assertTrue($fold !== NULL and $fold->classList->contains('util-fold'), $name.' : la zone n\'est pas un util-fold') === FALSE) {
				continue;
			}

			$this->assertEquals($open, $fold->classList->contains('util-fold-open'), $name.' : la zone ne s\'ouvre pas selon `open`');
			$this->assertEquals($name, $document->querySelector('#planning-week-'.$name.' > a.util-fold-head')?->getAttribute('data-planning-zone'), $name.' : la tête n\'ouvre pas la zone');

		}

	}

	/**
	 * Ce que la recherche des zones retient pour des jetons, par zone puis par bloc, `NULL` si elle est refusée.
	 */
	private function callSearch(array $criteria): ?array {

		$action = $this->executePage('planning/taskWeek:search', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'criteria' => $criteria,
		], 'GET');

		if(($action instanceof \ViewAction) === FALSE) {
			return NULL;
		}

		[$data] = $this->runView('search', $action, 'taskWeek');

		return $data->matches;

	}

	// Les vignettes que l'enveloppe de la recherche donne à ses suggestions (`TabUi::getSearchVignettes()`).
	private function getSearchVignettes(\Dom\HTMLDocument $document): array {
		return json_decode($document->querySelector('.planning-search')?->getAttribute('data-vignettes') ?? '{}', TRUE);
	}

	// La vignette de l'espèce que porte la carte d'une intervention (`TaskCardUi::getSearchPlant()`), vide sans carte.
	private function getSearchPlant(\Dom\HTMLDocument $document, \series\Task $eTask): array {

		$node = $document->querySelector('[data-task="'.$eTask['id'].'"] [data-search-plant]');

		return ($node === NULL) ? [] : json_decode($node->getAttribute('data-search-plant'), TRUE);

	}

	private function getWeekBefore(): string {
		return toWeek(\Clock::date().' - 7 DAYS');
	}

	private function getOldIds(\Collection $ccTask): array {

		$ids = [];

		foreach($ccTask as $cTask) {
			$ids = array_merge($ids, $cTask->getIds());
		}

		return $ids;

	}

	/** Une action de culture créée pour le cas, que le retard de la structure ne peut donc pas porter. */
	private function createActionLate(): \work\Action {

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

	private function createTasksLate(\work\Action $eAction, int $number): void {

		for($i = 0; $i < $number; $i++) {

			$eTask = new \series\Task([
				'structure' => $this->eStructure,
				'series' => $this->getCultivation()['series'],
				'cultivation' => $this->getCultivation(),
				'plant' => $this->getCultivation()['plant'],
				'action' => $eAction,
				'category' => $this->getCategoryCulture(),
				'status' => \series\Task::TODO,
				'plannedWeek' => $this->getWeekBefore(),
				'plannedDate' => NULL,
				'repeatMaster' => new \series\Repeat(),
			]);

			// Sans `build()`, qui n'a rien à vérifier ici : le décor pose des interventions à faire, prévues la
			// semaine d'avant, sur une action qu'il vient de créer.
			\series\TaskLib::create($eTask);

		}

	}

}
?>
