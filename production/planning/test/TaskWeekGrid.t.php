<?php
namespace planning;

/**
 * La grille de la Semaine (CW, cadre `#b` de la planche planning-cw-vue-grille) : l'interrupteur
 * « Cartes | Grille » et le choix qu'il retient sur le membre, les colonnes de la grille, ce qu'une ligne
 * montre d'une intervention (temps par jour, jour prévu, commentaires, récolté, cachet « Fait »), et le
 * téléphone, qui garde les cartes. Chaque cas passe par la vraie route, dans la transaction du décor
 * (`HarvestFixtureTest`), et ne regarde que les interventions qu'il pose.
 *
 * Le jour du décor est le jeudi de la semaine 39 : lundi, mardi et mercredi sont passés, vendredi, samedi
 * et dimanche à venir.
 *
 * Lancement : dev/test package=planning file=TaskWeekGrid
 */
class TaskWeekGridTest extends HarvestFixtureTest {

	/**
	 * `?view=grid` rend la grille et retient le choix sur le membre : l'écran relu sans paramètre reste en
	 * grille, et `?view=cards` revient aux cartes.
	 */
	public function testViewRemembered(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderView('grid');

		$this->assertTrue($document->querySelector('#planning-week-board #planning-grid #planning-grid-task-'.$eTask['id']) !== NULL, '?view=grid ne rend pas la ligne de l\'intervention dans la grille');
		$this->assertEquals(0, $document->querySelectorAll('#planning-task-'.$eTask['id'])->length, 'la grille rend encore la carte de l\'intervention');

		$this->assertEquals(\structure\Member::GRID, \structure\StructureLib::getById($this->eStructure['id'])->getView('viewPlanningWeek'), 'le choix de la grille n\'est pas retenu sur le membre');

		[$document] = $this->renderView(NULL);

		$this->assertTrue($document->querySelector('#planning-grid-task-'.$eTask['id']) !== NULL, 'l\'écran relu sans paramètre ne reste pas en grille');

		[$document] = $this->renderView('cards');

		$this->assertTrue($document->querySelector('#planning-task-'.$eTask['id']) !== NULL, '?view=cards ne rend pas la carte de l\'intervention');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid')->length, '?view=cards rend encore la grille');

	}

	/**
	 * Un geste répond dans l'affichage que l'écran réémet (`data-view`), pas dans le dernier choix du membre :
	 * un second onglet resté en cartes reçoit sa carte après que le premier a choisi la grille. L'écran porte
	 * l'affichage montré, et le panneau de création le réémet aussi, depuis la rangée des outils comme depuis le
	 * « + » d'un bloc.
	 */
	public function testGestureKeepsShownView(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderView('grid');

		$this->assertEquals(\structure\Member::GRID, $document->getElementById('planning-week')?->getAttribute('data-view'), 'l\'écran en grille ne le dit pas aux gestes');
		$this->assertTrue(str_contains($document->querySelector('#planning-week .planning-tools a[href*="taskWeek:create"]')?->getAttribute('href') ?? '', 'view='.\structure\Member::GRID), '« Nouvelle intervention » ne réémet pas la grille');

		[$data, $instructions] = $this->callTask('doDone', $eTask, ['view' => \structure\Member::CARDS]);

		$this->assertTrue($data !== NULL and $data->grid === FALSE, 'un geste réémis depuis les cartes répond en grille');
		$this->assertTrue(str_contains(json_encode($instructions), 'planning-task-'.$eTask['id']), 'un geste réémis depuis les cartes ne redessine pas la carte');

		[$data] = $this->callTask('doTodo', $eTask, ['view' => \structure\Member::GRID]);

		$this->assertTrue($data !== NULL and $data->grid, 'un geste réémis depuis la grille répond en cartes');

		[$document] = $this->renderView('cards');

		$this->assertTrue(str_contains($document->querySelector('#planning-week-block-'.$eTask['action']['id'].' a[href*="taskWeek:create"]')?->getAttribute('href') ?? '', 'view='.\structure\Member::CARDS), 'le « + » d\'un bloc ne réémet pas les cartes');

	}

	/**
	 * « Sélectionner » coche des cartes, que la grille n'a pas : il n'est rendu qu'en cartes.
	 */
	public function testSelectOnlyWithCards(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderView('grid');
		$this->assertEquals(0, $document->querySelectorAll('[data-planning-select-mode]')->length, 'la grille porte « Sélectionner »');

		[$document] = $this->renderView('cards');
		$this->assertTrue($document->querySelectorAll('[data-planning-select-mode]')->length > 0, 'les cartes ont perdu « Sélectionner »');

	}

	/**
	 * La grille coche ses lignes sans mode « Sélectionner » (DG-3) : chaque ligne porte la case
	 * d'une carte, avec ses jetons, ligne faite comprise, et chaque intertitre d'action la case de son groupe.
	 * Deux récoltes de la même espèce portent le même jeton de « Récolter ». Le serveur ne rend aucune case cochée.
	 */
	public function testSelect(): void {

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$eTaskHarvest = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$eTaskHarvestOther = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderView('grid');
		[$documentCards] = $this->renderView('cards');

		foreach([$eTaskTodo, $eTaskDone, $eTaskHarvest, $eTaskHarvestOther] as $eTask) {

			$field = $document->querySelector('#planning-grid-task-'.$eTask['id'].' input[name="batch[]"][value="'.$eTask['id'].'"]');
			$fieldCard = $documentCards->querySelector('#planning-task-'.$eTask['id'].' .planning-select > input[name="batch[]"]');

			if($this->assertTrue($field !== NULL, 'la ligne '.$eTask['id'].' n\'a pas sa case') === FALSE) {
				continue;
			}

			$this->assertEquals($fieldCard?->getAttribute('data-batch'), $field->getAttribute('data-batch'), 'la case de la ligne '.$eTask['id'].' n\'a pas les jetons de la carte');
			$this->assertFalse($field->hasAttribute('checked'), 'la case de la ligne '.$eTask['id'].' est rendue cochée');

		}

		$this->assertTrue(str_contains($document->querySelector('#planning-grid-task-'.$eTaskDone['id'].' [name="batch[]"]')?->getAttribute('data-batch') ?? '', 'done'), 'la case de la ligne faite ne le dit pas au menu');

		$token = fn(\series\Task $eTask) => array_values(array_filter(explode(' ', $document->querySelector('#planning-grid-task-'.$eTask['id'].' [name="batch[]"]')?->getAttribute('data-batch') ?? ''), fn(string $token) => str_starts_with($token, 'harvest-') and str_starts_with($token, 'harvest-unit-') === FALSE));

		$this->assertTrue($token($eTaskHarvest) !== [] and $token($eTaskHarvest) === $token($eTaskHarvestOther), 'deux récoltes de la même espèce n\'ont pas le même jeton de « Récolter »');

		foreach([$eTaskTodo, $eTaskHarvest] as $eTask) {
			$this->assertTrue($document->querySelector('#planning-grid-block-'.$eTask['action']['id'].' input[data-planning-select-group]') !== NULL, 'l\'intertitre de l\'action '.$eTask['action']['id'].' n\'a pas sa case de groupe');
		}

	}

	/**
	 * Une autre semaine que la courante garde sa semaine dans les liens des trois affichages et dans celui de la
	 * roue : sans elle, changer d'affichage ou personnaliser ramènerait à la semaine courante. Les adresses
	 * attendues s'écrivent en clair, et non par `TaskWeekUi::url()`, qui est ce que le cas éprouve.
	 */
	public function testLinksKeepWeek(): void {

		$week = toWeek(week_date_starts(\Clock::week()).' +7 days');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		[$document] = $this->renderView('grid', $week);

		$links = array_map(fn(\Dom\Element $link) => $link->getAttribute('href'), iterator_to_array($document->querySelectorAll('.planning-period-view-list > .dropdown-item')));
		$base = '/ferme/'.$this->eStructure['id'].'/planning-v2/semaine/'.$week.'?view=';

		$this->assertEquals([$base.\structure\Member::CARDS, $base.\structure\Member::COLUMNS, $base.\structure\Member::GRID], array_map(fn(string $link) => strstr($link, '/ferme/'), $links), 'les liens des trois affichages ne gardent pas la semaine affichée');
		$this->assertTrue(str_ends_with($document->querySelector('a.planning-views-menu')?->getAttribute('href') ?? '', '/ferme/'.$this->eStructure['id'].'/planning-v2/personnaliser/'.$week), 'la roue ne garde pas la semaine affichée');

	}

	/**
	 * La case du temps de la semaine porte la somme des temps passés, toutes personnes ensemble. Le jour prévu
	 * est marqué s'il est à venir et que l'intervention reste à faire, jamais sur un jour passé ni sur une
	 * intervention faite (retour 335).
	 */
	public function testDays(): void {

		$monday = week_date_day(\Clock::week(), 1);
		$friday = week_date_day(\Clock::week(), 5);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $friday);
		$this->createTimesheet($eTask, 0.5, $monday);
		$this->createTimesheet($eTask, 0.25, \Clock::date());

		[$document] = $this->renderView('grid');

		$row = $document->querySelector('#planning-grid-task-'.$eTask['id']);

		if($this->assertTrue($row !== NULL, 'la ligne de l\'intervention manque') === FALSE) {
			return;
		}

		$days = $row->querySelectorAll('.planning-grid-day');

		$this->assertTrue($days->item(4)?->querySelector('.planning-grid-planned') !== NULL, 'le jour prévu n\'est pas marqué');
		$this->assertEquals(DurationUi::formatDuration(0.75), trim($row->querySelector('.planning-grid-end:nth-last-child(2)')?->textContent ?? ''), 'le temps de la semaine n\'est pas la somme des jours');

		$eTaskPast = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $monday);
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $friday);

		[$document] = $this->renderView('grid');

		$this->assertTrue($document->getElementById('planning-grid-task-'.$eTaskPast['id']) !== NULL and $document->getElementById('planning-grid-task-'.$eTaskDone['id']) !== NULL, 'l\'intervention prévue un jour passé ou la faite n\'a pas sa ligne');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskPast['id'].' .planning-grid-planned')->length, 'un jour prévu passé est marqué autour de son temps');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskDone['id'].' .planning-grid-planned')->length, 'une intervention faite garde la marque de son jour prévu');

	}

	/**
	 * Seul aujourd'hui se distingue sur sa colonne, de minuit à minuit, et plus les jours passés (retour 388). On
	 * juge quel jour la grille tient pour aujourd'hui, pas le nom de sa classe.
	 */
	public function testToday(): void {

		foreach(['2026-10-15 00:00:00', '2026-10-15 23:59:59'] as $datetime) {

			$this->setClock($datetime);

			$this->assertTrue(TaskWeekGridUi::getTodayClass('2026-10-15') !== '', 'aujourd\'hui ne se distingue pas à '.$datetime);
			$this->assertEquals('', TaskWeekGridUi::getTodayClass('2026-10-14'), 'la veille se distingue à '.$datetime);
			$this->assertEquals('', TaskWeekGridUi::getTodayClass('2026-10-12'), 'un jour passé de la semaine se distingue à '.$datetime);
			$this->assertEquals('', TaskWeekGridUi::getTodayClass('2026-10-16'), 'le lendemain se distingue à '.$datetime);

		}

	}

	/**
	 * Le titre d'une ligne suit la règle de la carte (`TaskCardUi::getContent()`), sans jamais prendre le nom de
	 * l'action, que porte l'intertitre (retour 376) : la série, sinon la note, sinon le complément. Ce que le titre
	 * prend ne se répète pas dessous, et les méthodes et le matériel restent sur la ligne (retour 374). Le titre se
	 * trouve par son lien vers la page de l'intervention.
	 */
	public function testTitle(): void {

		[$eMethod, $eTool] = $this->createMethodTool();

		$eTaskSeries = $this->createTaskTitled(TRUE, NULL, [$eMethod['id']], [$eTool['id']]);
		$eTaskNote = $this->createTaskTitled(FALSE, 'Note du décor', [$eMethod['id']]);
		$eTaskMethod = $this->createTaskTitled(FALSE, NULL, [$eMethod['id']]);
		$eTaskBare = $this->createTaskTitled(FALSE, NULL);

		[$document] = $this->renderView('grid');

		$action = $this->getAction(ACTION_TRAITEMENT)['name'];
		$row = fn(\series\Task $eTask) => $document->getElementById('planning-grid-task-'.$eTask['id']);
		$title = fn(\series\Task $eTask) => $row($eTask)?->querySelector('a[href="'.TaskDetailUi::url($eTask, \Clock::week()).'"]');

		if($this->assertDecorPresent($title($eTaskSeries) !== NULL, 'la ligne de l\'intervention de série') === FALSE) {
			return;
		}

		$this->assertTrue(str_contains($title($eTaskSeries)->textContent, $this->getCultivation()['series']['name']), 'une intervention de série n\'a pas sa série pour titre');
		$this->assertTrue(str_contains($row($eTaskSeries)->textContent, $eMethod['name']) and str_contains($row($eTaskSeries)->textContent, $eTool['name']), 'la méthode ou le matériel quittent la ligne de l\'intervention de série');

		$this->assertTrue(str_contains($title($eTaskNote)?->textContent ?? '', 'Note du décor'), 'sans série, la note n\'est pas le titre');
		$this->assertEquals(1, substr_count($row($eTaskNote)?->textContent ?? '', 'Note du décor'), 'la note devenue titre se répète dessous');

		$this->assertTrue(str_contains($title($eTaskMethod)?->textContent ?? '', $eMethod['name']), 'sans série ni note, le complément n\'est pas le titre');
		$this->assertEquals(1, substr_count($row($eTaskMethod)?->textContent ?? '', $eMethod['name']), 'le complément devenu titre se répète dessous');

		$this->assertTrue($title($eTaskBare) !== NULL, 'une ligne sans rien ne mène pas à la page de l\'intervention');

		foreach([$eTaskSeries, $eTaskNote, $eTaskMethod, $eTaskBare] as $eTask) {
			$this->assertFalse(str_contains($title($eTask)?->textContent ?? '', $action), 'le titre de la ligne '.$eTask['id'].' prend le nom de son action');
		}

	}

	/**
	 * Le récolté d'une récolte est la quantité de la semaine, dans son unité ; une autre intervention laisse
	 * la case vide.
	 */
	public function testHarvested(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eTaskHarvest = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		// Sans build(), qui vérifierait l'unité postée depuis le formulaire d'intervention : le décor veut une
		// récolte dans l'unité de la culture, ce que `createTask()` ne pose pas.
		$eTaskHarvest['harvestUnit'] = $this->getCultivation()['mainUnit'];
		\series\TaskLib::update($eTaskHarvest, ['harvestUnit']);
		$eTaskOther = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$this->createHarvest($eTaskHarvest, 12.5);

		[$document] = $this->renderView('grid');

		$harvested = fn(\series\Task $eTask) => trim($document->querySelector('#planning-grid-task-'.$eTask['id'].' > .planning-grid-end:not(.planning-grid-expected)')?->textContent ?? '');

		$this->assertTrue(preg_match('/^12[.,]5\b/', $harvested($eTaskHarvest)) === 1, 'le récolté de la semaine ne se lit pas : « '.$harvested($eTaskHarvest).' »');
		$this->assertEquals('', $harvested($eTaskOther), 'une intervention sans récolte montre un récolté');

		$link = $document->querySelector('#planning-grid-task-'.$eTaskHarvest['id'].' [data-planning-grid-harvest]');
		$this->assertTrue($link !== NULL, 'le récolté d\'une récolte de série n\'ouvre pas la saisie');
		$this->assertEquals((string)$this->getCultivation()['id'], $document->getElementById('planning-grid-task-'.$eTaskHarvest['id'])?->getAttribute('data-cultivation'), 'la ligne ne porte pas la culture que la saisie écrit');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskOther['id'].' [data-planning-grid-harvest]')->length, 'une intervention qui n\'est pas une récolte ouvre la saisie');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderView('grid');
		$this->assertEquals(0, $document->querySelectorAll('[data-planning-grid-harvest]')->length, 'un planning limité ouvre la saisie de récolte');

	}

	/**
	 * Un jour à venir d'une intervention à faire la prévoit ce jour-là ; le jour déjà prévu, les jours passés, une
	 * intervention faite et un planning limité n'en ont pas. Le geste écrit le jour et répond par la ligne ; un
	 * jour hors de la semaine affichée se refuse.
	 */
	public function testPlan(): void {

		$friday = week_date_day(\Clock::week(), 5);
		$saturday = week_date_day(\Clock::week(), 6);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $friday);
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderView('grid');

		$plans = array_map(fn(\Dom\Element $link) => $link->getAttribute('data-planning-grid-plan'), iterator_to_array($document->querySelectorAll('#planning-grid-task-'.$eTask['id'].' [data-planning-grid-plan]')));

		$this->assertEquals([$saturday, week_date_day(\Clock::week(), 7)], $plans, 'les jours qui prévoient ne sont pas les jours à venir hors du jour prévu');
		$this->assertTrue($document->querySelectorAll('#planning-grid-task-'.$eTask['id'].' .planning-grid-day')->item(4)?->querySelector('[data-planning-grid-unplan]') !== NULL, 'le jour prévu ne se retire pas depuis sa case');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskDone['id'].' [data-planning-grid-plan]')->length, 'une intervention faite se prévoit');

		[$data, $instructions] = $this->callTask('doPlanned', $eTask, ['planned' => ['date' => $saturday], 'view' => \structure\Member::GRID]);

		$this->assertEquals($saturday, \series\TaskLib::getById($eTask['id'])['plannedDate'], 'le jour choisi n\'est pas écrit');
		$this->assertTrue($data !== NULL and $data->grid, 'le geste ne répond pas en grille');
		$this->assertTrue(str_contains(json_encode($instructions), 'planning-grid-task-'.$eTask['id']), 'le geste ne redessine pas la ligne');

		[, , $action] = $this->callTask('doPlanned', $eTask, ['planned' => ['date' => week_date_day(TaskDayLib::getNextWeek(\Clock::week()), 1)], 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \NotExpectedAction, 'un jour hors de la semaine est accepté');
		$this->assertEquals($saturday, \series\TaskLib::getById($eTask['id'])['plannedDate'], 'un jour refusé a été écrit');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderView('grid');
		$this->assertEquals(0, $document->querySelectorAll('[data-planning-grid-plan]')->length, 'un planning limité prévoit un jour');

	}

	/**
	 * Le jour prévu se retire depuis sa case (DF, DN, DY) : l'intervention reste prévue dans la semaine, sans jour,
	 * et sa ligne redessinée garde sa place avec un geste sur chacun de ses jours à venir. Un `planned` posté avec
	 * le geste n'est pas lu.
	 */
	public function testUnplan(): void {

		$friday = week_date_day(\Clock::week(), 5);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $friday);

		[$data, $instructions, $action] = $this->callTask('doUnplanned', $eTask, ['planned' => ['date' => week_date_day(\Clock::week(), 6)], 'view' => \structure\Member::GRID]);

		$eTaskAfter = \series\TaskLib::getById($eTask['id']);

		$this->assertTrue($action instanceof \ViewAction, 'le retrait du jour est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertTrue($eTaskAfter['plannedWeek'] === \Clock::week() and $eTaskAfter['plannedDate'] === NULL, 'le retrait ne garde pas la semaine sans jour, ou un `planned` posté a été lu');
		$this->assertTrue($data !== NULL and $data->grid, 'le geste ne répond pas en grille');
		$this->assertTrue(str_contains(json_encode($instructions), 'planning-grid-locked'), 'le geste ne remplace pas la raison des jours verrouillés');

		[$document] = $this->renderView('grid');

		$plans = array_map(fn(\Dom\Element $link) => $link->getAttribute('data-planning-grid-plan'), iterator_to_array($document->querySelectorAll('#planning-grid-task-'.$eTask['id'].' [data-planning-grid-plan]')));

		$this->assertEquals(array_values(array_filter(week_dates(\Clock::week()), fn(string $date) => $date > \Clock::date())), $plans, 'la ligne sans jour n\'a pas un geste sur chacun de ses jours à venir');

		$weekNext = TaskDayLib::getNextWeek(\Clock::week());
		$eTaskNext = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekNext, week_date_day($weekNext, 2));

		[, , $action] = $this->callTask('doUnplanned', $eTaskNext, ['view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \NotExpectedAction, 'le retrait du jour d\'une intervention d\'une autre semaine est accepté');
		$this->assertEquals($weekNext, \series\TaskLib::getById($eTaskNext['id'])['plannedWeek'], 'un retrait refusé a écrit la semaine');

	}

	/**
	 * Aujourd'hui se prévoit et se retire depuis le menu du temps de sa case, qui reste son seul geste (EH, retour
	 * 379) : la ligne du menu suit le jour prévu, survit à une personne affectée depuis lui, et manque à un jour
	 * passé comme à une intervention commencée. Sans suivi du temps, la case n'a pas de menu et prévoit
	 * aujourd'hui comme un jour à venir.
	 */
	public function testPlanToday(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$today = \Clock::date();
		$monday = week_date_day(\Clock::week(), 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderView('grid');

		$cell = $document->querySelectorAll('#planning-grid-task-'.$eTask['id'].' .planning-grid-day')->item(3);

		$this->assertTrue($cell?->querySelector('[data-planning-grid-time="'.$today.'"]') !== NULL, 'aujourd\'hui n\'ouvre pas le menu du temps');
		$this->assertEquals(0, $cell?->querySelectorAll('[data-planning-grid-plan], [data-planning-grid-unplan]')->length, 'aujourd\'hui a un second geste à côté du menu du temps');

		[, $instructions] = $this->callGrid('time', $eTask, ['date' => $today], 'GET');
		$menu = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$today);

		$this->assertTrue($menu->querySelector('[data-planning-grid-plan="'.$today.'"]') !== NULL, 'le menu d\'aujourd\'hui ne prévoit pas aujourd\'hui');

		[, , $action] = $this->callTask('doPlanned', $eTask, ['planned' => ['date' => $today], 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'aujourd\'hui ne se prévoit pas : '.implode(', ', $this->getFails($action)));
		$this->assertEquals($today, \series\TaskLib::getById($eTask['id'])['plannedDate'], 'aujourd\'hui n\'est pas écrit');

		[, $instructions] = $this->callGrid('time', $eTask, ['date' => $today], 'GET');
		$menu = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$today);

		// Aujourd'hui prévu, le menu n'a plus de geste sur le jour : un autre jour de la ligne prend sa place (retour 404).
		$this->assertTrue($menu->querySelector('[data-planning-grid-unplan]') === NULL, 'le menu d\'aujourd\'hui prévu retire encore le jour');
		$this->assertTrue($menu->querySelector('[data-planning-grid-plan]') === NULL, 'le menu d\'aujourd\'hui prévu le prévoit encore');

		[, $instructions] = $this->callGrid('doUser', $eTask, ['user' => $this->getMember()['id'], 'action' => 'add', 'date' => $today]);
		$menu = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$today);

		$this->assertTrue($menu->querySelector('[data-planning-grid-plan], [data-planning-grid-unplan]') === NULL, 'une personne affectée depuis le menu d\'aujourd\'hui prévu y remet un geste sur le jour');

		[, $instructions] = $this->callGrid('time', $eTask, ['date' => $monday], 'GET');
		$menu = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$monday);

		$this->assertTrue($menu->querySelector('[data-planning-grid-plan], [data-planning-grid-unplan]') === NULL, 'un jour passé se prévoit depuis son menu');

		[, , $action] = $this->callTask('doUnplanned', $eTask, ['view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'aujourd\'hui ne se retire pas : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(\series\TaskLib::getById($eTask['id'])['plannedDate'] === NULL, 'aujourd\'hui n\'est pas retiré');

		$eTaskStarted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $today);
		$this->createTimesheet($eTaskStarted, 0.5, $today);

		[, $instructions] = $this->callGrid('time', $eTaskStarted, ['date' => $today], 'GET');
		$menu = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTaskStarted['id'].'-'.$today);

		$this->assertTrue($menu->querySelector('[data-planning-grid-plan], [data-planning-grid-unplan]') === NULL, 'une intervention commencée retire son jour depuis le menu d\'aujourd\'hui');

		// Le réglage se pose sur la configuration mémorisée seulement, que la page relit par l'identifiant de la
		// structure (`HarvestTimeUntracked`), et se remet avant la fin de la méthode.
		$configuration = \structure\StructureLib::getById($this->eStructure['id'])->conf();
		$timeBefore = $configuration['time'];
		$configuration['time'] = FALSE;

		try {

			[$document] = $this->renderView('grid');

			$cell = $document->querySelectorAll('#planning-grid-task-'.$eTask['id'].' .planning-grid-day')->item(3);

			$this->assertTrue($cell?->querySelector('[data-planning-grid-plan="'.$today.'"]') !== NULL, 'sans suivi du temps, aujourd\'hui ne se prévoit pas depuis sa case');

		} finally {
			$configuration['time'] = $timeBefore;
		}

	}

	/**
	 * Une intervention commencée garde son jour (DF) : ni son jour ni ses autres jours à venir n'ont de geste, la raison s'affiche sous la grille, et le serveur refuse, par le code du verrou et sans
	 * rien écrire, de la prévoir en grille et de la retirer quel que soit l'affichage posté.
	 */
	public function testLocked(): void {

		$friday = week_date_day(\Clock::week(), 5);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $friday);
		$this->createTimesheet($eTask, 0.5, week_date_day(\Clock::week(), 1));

		[$document, $data] = $this->renderView('grid');

		$row = $document->getElementById('planning-grid-task-'.$eTask['id']);

		if($this->assertTrue($row !== NULL, 'la ligne de l\'intervention commencée manque') === FALSE) {
			return;
		}

		$this->assertEquals(0, $row->querySelectorAll('[data-planning-grid-unplan], [data-planning-grid-plan]')->length, 'une intervention commencée a un geste sur ses jours');
		$this->assertTrue($data->locked, 'la page ne voit pas l\'intervention verrouillée');
		$this->assertTrue($document->getElementById('planning-grid-locked') !== NULL, 'la raison des jours verrouillés ne s\'affiche pas sous la grille');

		[, , $action] = $this->callTask('doUnplanned', $eTask, ['view' => \structure\Member::GRID]);
		$this->assertTrue(in_array('Task::planned.started', $this->getFails($action), TRUE), 'le retrait du jour d\'une intervention commencée n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callTask('doUnplanned', $eTask, ['view' => \structure\Member::CARDS]);
		$this->assertTrue(in_array('Task::planned.started', $this->getFails($action), TRUE), 'le retrait du jour d\'une intervention commencée passe hors de la grille : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callTask('doPlanned', $eTask, ['planned' => ['date' => week_date_day(\Clock::week(), 6)], 'view' => \structure\Member::GRID]);
		$this->assertTrue(in_array('Task::planned.started', $this->getFails($action), TRUE), 'le déplacement d\'une intervention commencée n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$this->assertEquals($friday, \series\TaskLib::getById($eTask['id'])['plannedDate'], 'un geste refusé a écrit le jour');

	}

	/**
	 * Un commentaire seul ne fige pas le jour (DY, retour 366, tranché) : la ligne garde ses gestes,
	 * rien n'est dit sous la grille, et le serveur prévoit puis retire le jour. Seuls un temps (`testLocked()`)
	 * ou une récolte figent le jour.
	 */
	public function testCommentNotLocked(): void {

		$friday = week_date_day(\Clock::week(), 5);
		$saturday = week_date_day(\Clock::week(), 6);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), $friday);
		$this->createComment($eTask, 'Problème sanitaire', '08:00:00');

		[$document, $data] = $this->renderView('grid');

		$row = $document->getElementById('planning-grid-task-'.$eTask['id']);

		if($this->assertTrue($row !== NULL, 'la ligne de l\'intervention commentée manque') === FALSE) {
			return;
		}

		$this->assertTrue($row->querySelector('[data-planning-grid-unplan]') !== NULL, 'une intervention commentée ne retire pas son jour');
		$this->assertEquals(2, $row->querySelectorAll('[data-planning-grid-plan]')->length, 'une intervention commentée ne prévoit pas ses autres jours à venir');
		$this->assertFalse($data->locked, 'la page voit une intervention commentée comme verrouillée');

		[, , $action] = $this->callTask('doPlanned', $eTask, ['planned' => ['date' => $saturday], 'view' => \structure\Member::GRID]);
		$this->assertTrue($action instanceof \ViewAction, 'le déplacement d\'une intervention commentée est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals($saturday, \series\TaskLib::getById($eTask['id'])['plannedDate'], 'le jour d\'une intervention commentée n\'est pas écrit');

		[, , $action] = $this->callTask('doUnplanned', $eTask, ['view' => \structure\Member::GRID]);
		$this->assertTrue($action instanceof \ViewAction, 'le retrait du jour d\'une intervention commentée est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(\series\TaskLib::getById($eTask['id'])['plannedDate'] === NULL, 'le jour d\'une intervention commentée n\'est pas retiré');

		$this->assertFalse(new \series\Task(['status' => \series\Task::TODO, 'time' => 0.0, 'harvest' => 2.0])->acceptPlanned(), 'une récolte ne fige pas le jour');
		$this->assertTrue(new \series\Task(['status' => \series\Task::TODO, 'time' => 0.0, 'harvest' => NULL])->acceptPlanned(), 'une intervention sans temps ni récolte ne se déplace pas');

	}

	/**
	 * La légende du jour prévu (DY, retour 370) vit sous la grille tant que la semaine a un jour à prévoir, aujourd'hui
	 * compris : la semaine en cours la porte jusqu'à son dimanche, une semaine passée non. Ce qu'elle montre se regarde dans Chrome.
	 */
	public function testLegend(): void {

		[, $data] = $this->renderView('grid');

		$this->assertTrue($data->legend, 'la semaine en cours ne voit pas de jour à prévoir');

		[, $data] = $this->renderView('grid', toWeek(week_date_starts(\Clock::week()).' -7 days'));

		$this->assertFalse($data->legend, 'une semaine passée voit un jour à prévoir');

		// Le dimanche, aujourd'hui est le dernier jour de la semaine à prévoir (EH).
		$this->setClock(week_date_day(\Clock::week(), 7).' 10:00:00');

		[, $data] = $this->renderView('grid');

		$this->assertTrue($data->legend, 'le dimanche ne voit pas aujourd\'hui à prévoir');

	}

	/**
	 * Le temps prévu (DF) : une intervention à faire le règle dans sa cellule, par la fenêtre de durée, qui
	 * écrit et répond par le seul travail estimé de la semaine, zéro s'écrivant NULL ; une intervention faite le montre sans fenêtre,
	 * et refuse un envoi forgé sans rien écrire.
	 */
	public function testTimeExpected(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderView('grid');

		$this->assertTrue($document->querySelector('#planning-grid-task-'.$eTask['id'].' .planning-grid-expected .planning-duration-step') !== NULL, 'le temps prévu d\'une intervention à faire ne se règle pas dans sa cellule');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskDone['id'].' .planning-grid-expected .planning-duration-step')->length, 'le temps prévu d\'une intervention faite se règle encore');

		[$data, $instructions, $action] = $this->callGrid('doTimeExpected', $eTask, ['timeExpected' => 1.5, 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'le temps prévu est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(1.5, \series\TaskLib::getById($eTask['id'])['timeExpected'], 'le temps prévu n\'est pas écrit');
		$this->assertEquals(['#planning-week-estimated'], array_values(array_unique(array_map(fn($instruction) => $instruction[1] ?? NULL, $instructions))), 'la réponse touche autre chose que le travail estimé de la semaine');

		[, , $action] = $this->callGrid('doTimeExpected', $eTask, ['timeExpected' => 0, 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'le temps prévu ramené à zéro est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(NULL, \series\TaskLib::getById($eTask['id'])['timeExpected'], 'un temps prévu ramené à zéro s\'écrit 0 au lieu de « pas d\'estimation »');

		$timeExpectedDone = \series\TaskLib::getById($eTaskDone['id'])['timeExpected'];

		[, , $action] = $this->callGrid('doTimeExpected', $eTaskDone, ['timeExpected' => 2.0, 'view' => \structure\Member::GRID]);

		$this->assertFalse($action instanceof \ViewAction, 'le temps prévu d\'une intervention faite s\'écrit');
		$this->assertEquals($timeExpectedDone, \series\TaskLib::getById($eTaskDone['id'])['timeExpected'], 'un temps prévu refusé a été écrit');

	}

	/**
	 * Un jour passé ou aujourd'hui ouvre la saisie du temps de ce jour, sur une intervention faite comme à
	 * faire : le tableau de l'équipe de la fiche réduit à ce jour (retours 329 et 330), qui écrit par les routes de la
	 * grille et ajoute une personne sur place, sans ouvrir de menu dans le menu. Un jour à venir et un planning
	 * limité n'ont pas de saisie. Son « Enregistrer » écrit le temps de plusieurs personnes en une requête, chaque
	 * durée remplaçant celle du jour (ET, retour 398), et répond sans redessiner la ligne ni le contenu du menu,
	 * qui se ferme (FJ, retour 411) : seul le déclencheur du jour s'éclaire. Une durée nulle, celle de la
	 * poubelle (retour 412), supprime le temps du jour sans laisser de ligne à zéro.
	 * Un jour hors de la semaine affichée, un jour à venir, une journée de plus de 24 heures et une personne hors
	 * du menu se refusent par leur code, sans rien écrire, pas même le temps des autres personnes.
	 */
	public function testTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);
		$friday = week_date_day(\Clock::week(), 5);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);

		[$document] = $this->renderView('grid');

		$dates = array_map(fn(\Dom\Element $link) => $link->getAttribute('data-planning-grid-time'), iterator_to_array($document->querySelectorAll('#planning-grid-task-'.$eTask['id'].' [data-planning-grid-time]')));

		$this->assertEquals(array_slice(week_dates(\Clock::week()), 0, 4), $dates, 'les jours qui ouvrent la saisie ne sont pas les jours passés et aujourd\'hui');

		[$data, $instructions, $action] = $this->callGrid('time', $eTask, ['date' => $monday], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie d\'un jour passé ne s\'ouvre pas') === FALSE) {
			return;
		}

		$document = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$monday);
		$grid = $document->getElementById('planning-grid-team-'.$eTask['id'].'-'.$monday.'-grid');

		if($this->assertTrue($grid !== NULL, 'le menu du jour ne porte pas le tableau de l\'équipe de la fiche') === FALSE) {
			return;
		}

		$this->assertEquals('/planning/taskWeek:doTimeTeam', $grid->getAttribute('data-url-time'), 'le tableau du menu n\'écrit pas le temps par la route de la grille');
		$this->assertEquals('week', $grid->getAttribute('data-screen'), 'le tableau du menu ne réémet pas l\'état de l\'écran de la semaine');
		$this->assertEquals(['task' => $eTask['id'], 'date' => $monday], json_decode($grid->getAttribute('data-body'), TRUE), 'le tableau du menu ne réémet pas son intervention et son jour');
		$this->assertTrue($document->querySelector('a.util-table-add[data-dropdown]') === NULL, '« Ajouter une personne » ouvre un menu dans le menu, que Lime.Dropdown refermerait');

		// La personne connectée a déjà sa ligne, montrée d'office sur une intervention sans temps (retour 384) :
		// « Ajouter une personne » s'éprouve sur une autre, présente dans la semaine.
		$this->addPresenceTeam();

		$cUserAddable = \planning\TaskDetailLib::getUsers($eTask, \Clock::week())->find(fn(\user\User $eUser) => $eUser['addable']);

		if($this->assertDecorPresent($cUserAddable->notEmpty(), 'une personne à ajouter sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$member = $cUserAddable->first()['id'];

		[, $instructions, $action] = $this->callGrid('time', $eTask, ['date' => $monday, 'user' => $member], 'GET');

		$this->assertTrue($action instanceof \ViewAction, '« Ajouter une personne » est refusé dans le menu');

		$document = $this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$monday);
		$prefix = 'planning-grid-team-'.$eTask['id'].'-'.$monday;

		$this->assertEquals((string)$member, $document->getElementById($prefix.'-grid')?->getAttribute('data-added'), 'le tableau du menu ne porte pas la ligne ajoutée');
		$this->assertTrue($document->getElementById($prefix.'-cell-'.$member.'-'.$monday)?->querySelector('[data-duration]') !== NULL, 'la case de la personne ajoutée n\'a pas sa saisie');

		[, , $action] = $this->callGrid('time', $eTask, ['date' => $monday, 'user' => $member, 'added' => (string)$member], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'une personne déjà ajoutée s\'ajoute de nouveau');

		[, , $action] = $this->callGrid('time', $eTask, ['date' => $friday], 'GET');
		$this->assertTrue($action instanceof \NotExpectedAction, 'la saisie d\'un jour à venir s\'ouvre');

		$me = $this->getMember()['id'];

		[$data, $instructions, $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '1.5', $member => '0.75'], 'added' => (string)$member, 'view' => \structure\Member::GRID], view: 'doTimeTeam');

		if($this->assertTrue($action instanceof \ViewAction, 'le temps de l\'équipe un jour passé est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$monday => 1.5], $this->getTimesByDate($eTask), 'le temps de la personne connectée n\'est pas écrit au lundi');
		$this->assertEquals([$monday => 0.75], $this->getTimesByDate($eTask, $cUserAddable->first()), 'le temps de la personne ajoutée n\'est pas écrit au lundi, dans la même requête');
		$this->assertEquals('card', $data->change, 'une ligne qui reste dans son bloc ne garde pas le menu ouvert');

		$selectors = array_map(fn($instruction) => $instruction[1] ?? NULL, $instructions);

		$trigger = '#planning-grid-day-'.$eTask['id'].'-'.$monday.' > [data-planning-grid-time]';

		$this->assertFalse(in_array('#planning-grid-time-'.$eTask['id'].'-'.$monday, $selectors, TRUE), 'la réponse redessine le contenu du menu, qui se ferme');
		$this->assertTrue(in_array($trigger, $selectors, TRUE), 'la réponse ne redessine pas la durée du déclencheur du jour');
		$this->assertEquals($monday, $data->dateTeam, 'la réponse ne connaît pas le jour du menu');
		$this->assertFalse(isset($data->cUserTeam), 'la réponse relit les personnes du menu, qui se ferme');
		$this->assertTrue(in_array('#planning-grid-week-'.$eTask['id'], $selectors, TRUE), 'la réponse ne redessine pas le temps de la semaine de la ligne');
		$this->assertFalse(in_array('#planning-grid-task-'.$eTask['id'], $selectors, TRUE), 'la réponse redessine la ligne, ce qui ferme le menu');

		// Chaque durée remplace celle du jour : additionnée, la seconde dépasserait 24 heures.
		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '12']], view: 'doTimeTeam');
		$this->assertTrue($action instanceof \ViewAction, 'le temps de 12 h est refusé : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '20']], view: 'doTimeTeam');
		$this->assertTrue($action instanceof \ViewAction, 'un temps qui remplace celui du jour est compté en plus : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => 20.0], $this->getTimesByDate($eTask), 'le temps du jour n\'est pas remplacé');

		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '25']], view: 'doTimeTeam');
		$this->assertTrue(in_array('Timesheet::time.tooLong', $this->getFails($action), TRUE), 'une journée de plus de 24 heures n\'est pas refusée par son code : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => 20.0], $this->getTimesByDate($eTask), 'une journée de plus de 24 heures est écrite');

		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '0']], view: 'doTimeTeam');
		$this->assertTrue($action instanceof \ViewAction, 'un temps ramené à zéro est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([], $this->getTimesByDate($eTask), 'un temps ramené à zéro n\'efface pas le jour');
		$this->assertEquals(0, \series\Timesheet::model()->whereTask($eTask)->whereUser($this->getMember())->whereDate($monday)->count(), 'un temps ramené à zéro laisse une ligne de temps au jour');

		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '1.5']], view: 'doTimeTeam');
		$this->assertTrue($action instanceof \ViewAction, 'le temps d\'un jour passé est refusé : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $friday, 'time' => [$me => '1']], view: 'doTimeTeam');
		$this->assertTrue(in_array('Timesheet::date.future', $this->getFails($action), TRUE), 'un temps un jour à venir n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => date('Y-m-d', strtotime($monday.' - 1 DAY')), 'time' => [$me => '1']], view: 'doTimeTeam');
		$this->assertTrue(in_array('Timesheet::date.week', $this->getFails($action), TRUE), 'un temps hors de la semaine n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));

		$this->assertEquals([$monday => 1.5], $this->getTimesByDate($eTask), 'un temps refusé est écrit');

		$eUserGone = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('decor.sans.structure@example.org')
			->get();

		if($this->assertDecorPresent($eUserGone->notEmpty() and \structure\MemberLib::getUsersByStructure($this->eStructure)->offsetExists($eUserGone['id']) === FALSE, 'decor.sans.structure@example.org, hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		// Sans `build()`, qui vérifierait la durée d'un formulaire : le décor veut le temps d'une personne partie,
		// que la route refuse justement d'écrire.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUserGone,
			'date' => $monday,
			'time' => 0.5,
		]));

		// Une personne refusée n'en laisse écrire aucune autre.
		[, , $action] = $this->callGrid('doTimeTeam', $eTask, ['date' => $monday, 'time' => [$me => '2', $eUserGone['id'] => '0']], view: 'doTimeTeam');
		$this->assertTrue(in_array('Timesheet::user.check', $this->getFails($action), TRUE), 'le temps d\'une personne qui n\'est plus membre n\'est pas refusé par son code : '.implode(', ', $this->getFails($action)));
		$this->assertEquals([$monday => 0.5], $this->getTimesByDate($eTask, $eUserGone), 'le temps d\'une personne partie est effacé');
		$this->assertEquals([$monday => 1.5], $this->getTimesByDate($eTask), 'le temps d\'une autre personne est écrit malgré un refus');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		[$document] = $this->renderView('grid');
		$this->assertEquals(0, $document->querySelectorAll('[data-planning-grid-time]')->length, 'un planning limité ouvre la saisie du temps');

	}

	/**
	 * « Affecté à » : une intervention à faire a le déclencheur de la liste, une faite ne l'a pas. La liste propose les personnes affectables ; choisir une personne
	 * l'affecte puis la retire, et la réponse ne redessine que la classe et le contenu du déclencheur, et
	 * l'entrée, plus le tableau du jour quand le geste vient du menu d'un jour. Une intervention faite se refuse.
	 */
	public function testUsers(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderView('grid');

		$this->assertTrue($document->querySelector('#planning-grid-task-'.$eTask['id'].' [data-planning-grid-users]') !== NULL, 'une intervention à faire n\'a pas le bouton « Affecter à... »');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskDone['id'].' [data-planning-grid-users]')->length, 'une intervention faite a le bouton « Affecter à... »');

		$user = $this->getMember()['id'];

		[, $instructions, $action] = $this->callGrid('users', $eTask, [], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'la liste ne s\'ouvre pas') === FALSE) {
			return;
		}

		$this->assertTrue(str_contains(json_encode($instructions), 'planning-grid-user-'.$eTask['id'].'-'.$user), 'la liste ne propose pas la personne connectée');

		[, $instructions, $action] = $this->callGrid('doUser', $eTask, ['user' => $user, 'action' => 'add']);

		$this->assertTrue($action instanceof \ViewAction, 'l\'affectation est refusée');
		$this->assertEquals([$user], \series\TaskLib::getById($eTask['id'])['plannedUsers'], 'la personne n\'est pas affectée');

		$json = json_encode($instructions);
		$this->assertTrue(str_contains($json, 'planning-grid-toggle-'.$eTask['id']) and str_contains($json, 'planning-grid-user-'.$eTask['id'].'-'.$user), 'la réponse ne redessine pas le déclencheur et l\'entrée');
		$this->assertEquals(NULL, array_find($instructions, fn($instruction) => ($instruction[1] ?? NULL) === '#planning-grid-task-'.$eTask['id']), 'la réponse redessine la ligne, ce qui fermerait la liste');

		$toggle = array_find($instructions, fn($instruction) => ($instruction[1] ?? NULL) === '#planning-grid-toggle-'.$eTask['id']);
		$this->assertEquals(['setAttribute', 'innerHtml'], array_map(fn($call) => $call[0], $toggle[2] ?? []), 'le déclencheur est remplacé au lieu d\'être redessiné');

		[, $instructions] = $this->callGrid('doUser', $eTask, ['user' => $user, 'action' => 'delete']);
		$this->assertEquals([], \series\TaskLib::getById($eTask['id'])['plannedUsers'], 'la personne n\'est pas retirée');

		$monday = week_date_day(\Clock::week(), 1);

		[, $instructions, $action] = $this->callGrid('doUser', $eTask, ['user' => $user, 'action' => 'add', 'date' => $monday]);

		$this->assertTrue($action instanceof \ViewAction, 'l\'affectation depuis le tableau d\'un jour est refusée');
		$this->assertTrue($this->parseInnerHtml($instructions, '#planning-grid-time-'.$eTask['id'].'-'.$monday)->getElementById('planning-grid-team-'.$eTask['id'].'-'.$monday.'-grid') !== NULL, 'l\'affectation depuis le tableau d\'un jour ne le redessine pas');

		[, , $action] = $this->callGrid('doUser', $eTask, ['user' => $user, 'action' => 'delete', 'date' => week_date_day(\Clock::week(), 7)]);
		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'un tableau d\'un jour à venir s\'affecte');
		$this->assertEquals([$user], \series\TaskLib::getById($eTask['id'])['plannedUsers'], 'une affectation refusée retire la personne');

		[, , $action] = $this->callGrid('doUser', $eTaskDone, ['user' => $user, 'action' => 'add']);
		$this->assertTrue(($action instanceof \ViewAction) === FALSE, 'une intervention faite s\'affecte');

	}

	/**
	 * Ce que la recherche lit sur la ligne après une affectation (EJ) : la réponse de `doUser` prépare les mêmes
	 * valeurs que l'écran rechargé, d'où se relève la liste des personnes de l'omnibox. Affectée, la personne y
	 * est ; retirée, elle en sort, sauf si elle a du temps dans la semaine, qui l'y garde.
	 */
	public function testUsersSearch(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eUser = $this->getMember();
		$name = $eUser->getName();

		$check = function(array $input, bool $named, string $case) use($eTask, $name): void {

			[$data, , $action] = $this->callGrid('doUser', $eTask, $input);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : le geste est refusé') === FALSE) {
				return;
			}

			[, $dataReload] = $this->renderView('grid');
			$eTaskReload = $dataReload->cTask->find(fn(\series\Task $eTaskWeek) => $eTaskWeek['id'] === $eTask['id'])->first();

			$this->assertEquals($eTaskReload['search'], $data->eTask['search'], $case.' : la réponse ne prépare pas la recherche du rechargement');
			$this->assertEquals($named, in_array($name, $data->eTask['search']['user'], TRUE), $case.' : la recherche '.($named ? 'ne nomme pas' : 'nomme encore').' la personne');

		};

		$check(['user' => $eUser['id'], 'action' => 'add'], TRUE, 'affectée');
		$check(['user' => $eUser['id'], 'action' => 'delete'], FALSE, 'retirée');

		$monday = week_date_day(\Clock::week(), 1);
		$check(['user' => $eUser['id'], 'action' => 'add', 'date' => $monday], TRUE, 'affectée depuis le menu d\'un jour');

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		// Sans `build()`, qui vérifierait la durée d'un formulaire : le décor ne veut qu'un temps dans la semaine,
		// que la recherche lit à côté de l'affectation.
		\series\TimesheetLib::writeTimeForDay($eTask, new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'date' => $monday,
			'time' => 0.5,
		]));

		$check(['user' => $eUser['id'], 'action' => 'delete'], TRUE, 'retirée avec du temps dans la semaine');

	}

	/**
	 * Le cachet « Fait » porte l'état de l'intervention et l'identifiant que le client poste. Un planning limité
	 * le voit éteint, et le nom mène à la page de l'intervention exactement quand la carte y mène.
	 */
	public function testDone(): void {

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderView('grid');

		$this->checkDone($document, $eTaskTodo, FALSE, TRUE, 'Semaine complète');
		$this->checkDone($document, $eTaskDone, TRUE, TRUE, 'Semaine complète');
		$this->assertTrue($document->querySelector('#planning-grid-task-'.$eTaskDone['id'])?->classList->contains('planning-grid-row-done') ?? FALSE, 'la ligne faite ne le dit pas à sa feuille');
		$this->assertEquals(TaskDetailUi::url($eTaskTodo, \Clock::week()), $document->querySelector('#planning-grid-task-'.$eTaskTodo['id'].' .planning-grid-label > a')?->getAttribute('href'), 'le nom ne mène pas à la page de l\'intervention');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[$document] = $this->renderView('grid');

		$this->checkDone($document, $eTaskTodo, FALSE, FALSE, 'Semaine de Bruno');
		$this->checkDone($document, $eTaskDone, TRUE, FALSE, 'Semaine de Bruno');

		[$documentCards] = $this->renderView('cards');

		foreach([$eTaskTodo, $eTaskDone] as $eTask) {
			$this->assertEquals($documentCards->querySelector('#planning-task-'.$eTask['id'].' a.planning-task-main') !== NULL, $document->querySelector('#planning-grid-task-'.$eTask['id'].' .planning-grid-label > a') !== NULL, 'Semaine de Bruno : le nom de la ligne '.$eTask['id'].' et la carte ne mènent pas pareil à la page de l\'intervention');
		}

	}

	/**
	 * « En retard » passe en grille (DZ) : la page prépare ses interventions comme celles de la semaine, une
	 * colonne par jour de la semaine affichée et leurs personnes, et l'écran rend chacune en ligne sous le groupe
	 * de son action, là où les réponses la remplacent. En cartes, la zone garde ses cartes.
	 *
	 * Une intervention à faire dit sa semaine prévue quand ce n'est pas celle de l'écran, comme sa carte
	 * (`TaskCardUi::hasOtherWeek()`, FZ), sauf un retard d'une semaine dans « En retard », que la tête de la zone dit
	 * déjà. Rangée dans la semaine par le temps qu'on y a saisi, prévue la semaine d'avant ou décalée à la suivante,
	 * elle la dit. Les bornes se jugent sur les interventions que la page prépare, celles de la zone et celles de la
	 * semaine.
	 */
	public function testLate(): void {

		$weekOld = toWeek(\Clock::date().' - 21 DAYS');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$eTaskLate = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
		$eTaskOld = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekOld);

		$eTaskWeek = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		// Prévue la semaine suivante puis cochée, elle garde sa semaine prévue : seul son statut la fait taire.
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' + 7 DAYS'));
		\series\TaskLib::updateDoneCollection(new \Collection([$eTaskDone]), \Clock::week());
		$this->createTimesheet($eTaskDone, 0.5);

		$eTaskLateStarted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
		$eTaskNextStarted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' + 7 DAYS'));
		$eTaskUnplannedStarted = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		foreach([$eTaskLateStarted, $eTaskNextStarted, $eTaskUnplannedStarted] as $eTask) {
			$this->createTimesheet($eTask, 0.5);
		}

		[$document, $data] = $this->renderView('grid');

		$zone = $data->zones[TaskLateLib::LATE];
		$action = $eTaskLate['action']['id'];

		$this->assertTrue(planningTaskWeekZoneGrid($data->grid, TaskLateLib::LATE), 'la grille ne rend pas « En retard » en lignes');
		$this->assertFalse(planningTaskWeekZoneGrid($data->grid, TaskLateLib::UNPLANNED), 'la grille rend « Non planifié » en lignes');

		foreach([$eTaskLate, $eTaskOld] as $eTask) {

			$this->assertTrue(in_array($eTask['id'], $zone['blocks'][$action]['shown']), 'l\'intervention en retard '.$eTask['id'].' n\'est pas montrée dans le bloc de son action');

			if($this->assertTrue($zone['cTask']->offsetExists($eTask['id']), 'l\'intervention en retard '.$eTask['id'].' n\'est pas préparée') === FALSE) {
				continue;
			}

			$this->assertEquals(week_dates(\Clock::week()), array_keys($zone['cTask'][$eTask['id']]['timesByDate']), 'l\'intervention en retard '.$eTask['id'].' n\'a pas les jours de la semaine affichée');
			$this->assertTrue($document->querySelector('#planning-week-late-block-'.$action.' #planning-grid-task-'.$eTask['id'].'[data-task="'.$eTask['id'].'"]') !== NULL, 'l\'intervention en retard '.$eTask['id'].' n\'est pas une ligne du groupe de son action');
			$this->assertTrue($document->getElementById('planning-task-'.$eTask['id']) === NULL, 'la grille rend encore la carte en retard '.$eTask['id']);

		}

		$this->assertTrue(TaskCardUi::hasOtherWeek($zone['cTask'][$eTaskOld['id']], \Clock::week()), 'deux semaines de retard ne disent pas la semaine d\'origine');
		$this->assertFalse(TaskCardUi::hasOtherWeek($zone['cTask'][$eTaskLate['id']], \Clock::week()), 'une semaine de retard dit sa semaine d\'origine dans « En retard », dont la tête le dit déjà');

		$this->assertEquals(toWeek(\Clock::date().' + 7 DAYS'), \series\TaskLib::getById($eTaskDone['id'])['plannedWeek'], 'l\'intervention cochée a perdu sa semaine prévue, le cas de la faite ne juge plus son statut');

		foreach([
			[$eTaskWeek, FALSE, 'une intervention prévue dans la semaine affichée dit sa semaine'],
			[$eTaskDone, FALSE, 'une intervention faite dit sa semaine prévue'],
			[$eTaskLateStarted, TRUE, 'une intervention prévue la semaine d\'avant et commencée dans celle-ci ne dit pas sa semaine'],
			[$eTaskNextStarted, TRUE, 'une intervention décalée à la semaine suivante et commencée dans celle-ci ne dit pas sa semaine'],
			[$eTaskUnplannedStarted, FALSE, 'une intervention non planifiée et commencée dans la semaine dit une semaine'],
		] as [$eTask, $expected, $message]) {

			if($this->assertTrue($data->cTask->offsetExists($eTask['id']), 'l\'intervention '.$eTask['id'].' n\'est pas dans la semaine') === FALSE) {
				continue;
			}

			$this->assertEquals($expected, TaskCardUi::hasOtherWeek($data->cTask[$eTask['id']], \Clock::week()), $message);

		}

		[$document, $data] = $this->renderView('cards');

		$this->assertFalse(planningTaskWeekZoneGrid($data->grid, TaskLateLib::LATE), 'les cartes rendent « En retard » en lignes');
		$this->assertTrue($document->getElementById('planning-task-'.$eTaskLate['id']) !== NULL, 'les cartes ont perdu la carte en retard');
		$this->assertTrue($document->getElementById('planning-grid-late') === NULL, 'les cartes rendent le tableau « En retard »');

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		[$document] = $this->renderView('grid');

		$this->checkDone($document, $eTaskLate, FALSE, FALSE, 'Retard de Bruno');
		$this->assertEquals(0, $document->querySelectorAll('#planning-grid-task-'.$eTaskLate['id'].' :is([data-planning-grid-plan], [data-planning-grid-unplan], [data-planning-grid-time])')->length, 'Retard de Bruno : une ligne en retard offre un geste au planning limité');

	}

	/**
	 * Au-delà du plafond, un groupe en retard montre ses `LIMIT` plus récentes, et son lien redemande le groupe
	 * en grille, qui revient entier, en lignes.
	 */
	public function testLateCapped(): void {

		for($i = 0; $i < TaskLateLib::LIMIT + 2; $i++) {
			$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
		}

		$action = $eTask['action']['id'];

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			[$document, $data] = $this->renderView('grid');

			$block = $data->zones[TaskLateLib::LATE]['blocks'][$action];

			$this->assertTrue($data->zones[TaskLateLib::LATE]['capped'], 'la zone au-delà du seuil n\'est pas plafonnée');
			$this->assertEquals(TaskLateLib::LIMIT, count($block['shown']), 'le groupe plafonné ne montre pas ses '.TaskLateLib::LIMIT.' plus récentes');
			$this->assertTrue($block['count'] >= TaskLateLib::LIMIT + 2, 'le groupe plafonné ne compte pas toutes ses interventions');

			$link = $document->querySelector('#planning-week-late-block-'.$action.' [data-ajax*="taskWeek:more"]');

			if($this->assertTrue($link !== NULL, 'le groupe plafonné n\'a pas de lien vers le reste du groupe') === FALSE) {
				return;
			}

			$this->assertTrue(str_contains($link->getAttribute('data-ajax'), 'view='.\structure\Member::GRID), 'le lien vers le reste du groupe ne redemande pas la grille');

			$actionMore = $this->executePage('planning/taskWeek:more', [
				'id' => $this->eStructure['id'],
				'week' => \Clock::week(),
				'zone' => TaskLateLib::LATE,
				'action' => $action,
				'view' => \structure\Member::GRID,
			], 'GET');

			if($this->assertTrue($actionMore instanceof \ViewAction, '« Voir les autres » est refusé') === FALSE) {
				return;
			}

			[$dataMore, $template] = $this->runView('more', $actionMore, 'taskWeek');

			$blockMore = $dataMore->zones[TaskLateLib::LATE]['blocks'][$action];

			$this->assertTrue(planningTaskWeekZoneGrid($dataMore->grid, TaskLateLib::LATE), '« Voir les autres » ne répond pas en grille');
			$this->assertEquals($blockMore['ids'], $blockMore['shown'], '« Voir les autres » ne montre pas le groupe entier');
			$idsPrepared = $dataMore->zones[TaskLateLib::LATE]['cTask']->getIds();
			$idsExpected = $blockMore['ids'];

			sort($idsPrepared);
			sort($idsExpected);

			$this->assertEquals($idsExpected, $idsPrepared, '« Voir les autres » ne prépare pas tout le groupe');

			$instruction = $template->formatInstructions()[0];

			$this->assertEquals('#planning-week-late-block-'.$action, $instruction[1], '« Voir les autres » ne remplace pas le groupe');

			$group = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$instruction[2][0][1][0].'</body></html>', LIBXML_NOERROR);

			$this->assertEquals([], array_values(array_filter($blockMore['ids'], fn(int $id) => $group->getElementById('planning-grid-task-'.$id) === NULL)), '« Voir les autres » ne rend pas chaque intervention du groupe en ligne');
			$this->assertTrue($group->querySelector('[data-ajax*="taskWeek:more"]') === NULL, '« Voir les autres » rend encore le lien');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * Une ligne en retard se prévoit sur un jour à venir de la semaine, comme une ligne de la semaine : le geste
	 * écrit le jour, la sort du tableau « En retard » et la pose dans la grille de la semaine.
	 */
	public function testLatePlanned(): void {

		$friday = week_date_day(\Clock::week(), 5);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderView('grid');

		$plans = array_map(fn(\Dom\Element $link) => $link->getAttribute('data-planning-grid-plan'), iterator_to_array($document->querySelectorAll('#planning-grid-late #planning-grid-task-'.$eTask['id'].' [data-planning-grid-plan]')));

		$this->assertEquals(array_values(array_filter(week_dates(\Clock::week()), fn(string $date) => $date > \Clock::date())), $plans, 'la ligne en retard n\'a pas un geste sur chacun des jours à venir');

		[$data, $instructions, $action] = $this->callTask('doPlanned', $eTask, ['planned' => ['date' => $friday], 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'le jour d\'une ligne en retard est refusé : '.implode(', ', $this->getFails($action)));

		$eTaskAfter = \series\TaskLib::getById($eTask['id']);

		$this->assertTrue($eTaskAfter['plannedWeek'] === \Clock::week() and $eTaskAfter['plannedDate'] === $friday, 'le jour choisi n\'est pas écrit');
		$this->assertTrue($data !== NULL and $data->grid, 'le geste ne répond pas en grille');
		$this->assertTrue(str_contains(json_encode($instructions), 'planning-grid-task-'.$eTask['id']), 'le geste ne pose pas la ligne dans la semaine');

		[$document] = $this->renderView('grid');

		$this->assertTrue($document->querySelector('#planning-grid #planning-grid-task-'.$eTask['id']) !== NULL, 'la ligne prévue n\'est pas dans la grille de la semaine');
		$this->assertTrue($document->querySelector('#planning-grid-late #planning-grid-task-'.$eTask['id']) === NULL, 'la ligne prévue reste en retard');

	}

	/**
	 * Une intervention en retard commencée garde son jour comme une ligne de la semaine (DF) : sa ligne n'a pas
	 * de geste sur les jours à venir, et la raison s'affiche sous la grille même quand aucune ligne de la semaine
	 * n'est commencée (`TaskWeekLib::hasLocked()`).
	 */
	public function testLateLocked(): void {

		$weekBefore = toWeek(\Clock::date().' - 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);
		$this->createTimesheet($eTask, 0.5, week_date_day($weekBefore, 2));

		[$document, $data] = $this->renderView('grid');

		$row = $document->querySelector('#planning-grid-late #planning-grid-task-'.$eTask['id']);

		if($this->assertTrue($row !== NULL, 'l\'intervention en retard commencée n\'est pas une ligne du tableau « En retard »') === FALSE) {
			return;
		}

		$this->assertEquals(0, $row->querySelectorAll('[data-planning-grid-plan], [data-planning-grid-unplan]')->length, 'une ligne en retard commencée a un geste sur ses jours');
		$this->assertTrue($data->locked, 'la page ne voit pas l\'intervention en retard verrouillée');
		$this->assertTrue($document->getElementById('planning-grid-locked') !== NULL, 'la raison des jours verrouillés ne s\'affiche pas sous la grille');

		$this->assertTrue(TaskWeekLib::hasLocked($this->eStructure, new \Collection(), TRUE, \Clock::week()), 'une zone en retard commencée ne verrouille pas sans ligne de la semaine');
		$this->assertFalse(TaskWeekLib::hasLocked($this->eStructure, new \Collection(), FALSE, \Clock::week()), 'une semaine vide sans retard commencé se dit verrouillée');

	}

	/**
	 * Un téléphone reçoit la grille que le membre a choisie, et peut en sortir par le menu du jeton « Semaine » (FL).
	 */
	public function testPhoneOffersGrid(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$this->renderView('grid');

		// `DeviceLib` mémorise la réponse du processus dans une propriété privée : le test la pose, puis
		// l'efface pour que la suivante relise l'en-tête de la requête.
		$isMobile = new \ReflectionProperty(\util\DeviceLib::class, 'isMobile');
		$isMobile->setValue(NULL, TRUE);

		try {
			[$document] = $this->renderView(NULL);
		} finally {
			$isMobile->setValue(NULL, NULL);
		}

		$this->assertTrue($document->querySelector('#planning-grid-task-'.$eTask['id']) !== NULL, 'le téléphone ne rend pas la ligne de la grille');
		$this->assertTrue($document->querySelector('#planning-week .planning-period .util-filter-options [data-dropdown]') !== NULL, 'le jeton « Semaine » du téléphone n\'ouvre pas le menu des affichages');

	}

	/**
	 * Une méthode et un matériel de l'action « Traitement », que les interventions du cas portent.
	 *
	 * @return array [\work\Method, \work\Tool]
	 */
	private function createMethodTool(): array {

		$fw = new \FailWatch();

		$eMethod = new \work\Method(['structure' => $this->eStructure]);
		$eMethod->build(\work\MethodLib::getPropertiesCreate(), ['name' => 'Méthode '.uniqid(), 'action' => $this->getAction(ACTION_TRAITEMENT)['id']]);

		$eTool = new \work\Tool(['structure' => $this->eStructure]);
		$eTool->build(\work\ToolLib::getPropertiesCreate(), ['name' => 'Outil '.uniqid(), 'action' => $this->getAction(ACTION_TRAITEMENT)['id']]);

		$fw->validate();

		\work\MethodLib::create($eMethod);
		\work\ToolLib::create($eTool);

		// `askCollection()` lit méthodes et matériel dans un cache du processus, que ceux créés ici ne rejoignent pas.
		new \ReflectionProperty(\work\MethodCrud::class, 'cache')->setValue(NULL, []);
		new \ReflectionProperty(\work\ToolCrud::class, 'cache')->setValue(NULL, []);

		return [$eMethod, $eTool];

	}

	/**
	 * Un traitement à faire cette semaine, sur la série du décor ou sans série ni espèce, avec la note, les
	 * méthodes et le matériel du cas.
	 */
	private function createTaskTitled(bool $series, ?string $description, array $methods = [], array $tools = []): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $series ? $this->getCultivation()['series'] : new \series\Series(),
			'cultivation' => $series ? $this->getCultivation() : new \series\Cultivation(),
			'plant' => $series ? $this->getCultivation()['plant'] : new \plant\Plant(),
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'repeatMaster' => new \series\Repeat(),
			'methods' => $methods,
			'tools' => $tools,
			'description' => $description,
		]);

		// Sans `build()`, qui vérifierait l'action et la semaine saisies : le décor les pose en clair et le
		// test ne poste rien.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * L'écran d'une semaine, la courante par défaut, avec l'affichage demandé ou, sans paramètre, celui que le
	 * membre a retenu.
	 */
	private function renderView(?string $view, ?string $week = NULL): array {

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/semaine/'.($week ?? \Clock::week()), ($view === NULL) ? [] : ['view' => $view], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'l\'écran des interventions ne se rend pas');

		[$data, $template, $html] = $this->runView('taskWeek', $action, 'taskWeek');

		return [
			\Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR),
			$data
		];

	}

	/**
	 * Une route de la grille rendue par sa vue, ou par `$view` quand elle répond par une autre : ses données et
	 * ses instructions, ou l'action qu'elle a levée à la place d'une vue.
	 */
	private function callGrid(string $route, \series\Task $eTask, array $input, string $method = 'POST', ?string $view = NULL): array {

		$action = $this->executePage('planning/taskWeek:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'task' => $eTask['id'],
		], $method);

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView($view ?? $route, $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	/** Le contenu qu'une réponse pose dans le nœud `$selector`, lu comme un document. */
	private function parseInnerHtml(array $instructions, string $selector): \Dom\HTMLDocument {

		$instruction = array_find($instructions, fn($instruction) => ($instruction[1] ?? NULL) === $selector and ($instruction[2][0][0] ?? NULL) === 'innerHtml');

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.($instruction[2][0][1][0] ?? '').'</body></html>', LIBXML_NOERROR);

	}

	/** Le temps de la personne connectée sur l'intervention, jour par jour. */
	private function getTimesByDate(\series\Task $eTask, \user\User $eUser = new \user\User()): array {

		$times = [];

		$cTimesheet = \series\Timesheet::model()
			->select(['date', 'total' => new \Sql('SUM(time)', 'float')])
			->whereTask($eTask)
			->whereUser($eUser->empty() ? $this->getMember() : $eUser)
			->group('date')
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['date']] = round($eTimesheet['total'], 2);
		}

		return $times;

	}

	private function checkDone(\Dom\HTMLDocument $document, \series\Task $eTask, bool $done, bool $write, string $case): void {

		$box = $document->querySelector('#planning-grid-task-'.$eTask['id'].' [data-planning-grid-done]');

		if($this->assertTrue($box !== NULL, $case.' : le cachet de l\'intervention '.$eTask['id'].' manque') === FALSE) {
			return;
		}

		$this->assertEquals((string)$eTask['id'], $box->getAttribute('data-planning-grid-done'), $case.' : le cachet ne porte pas l\'intervention');
		$this->assertEquals($done, $box->hasAttribute('checked'), $case.' : le cachet ne porte pas l\'état de l\'intervention');
		$this->assertEquals($write === FALSE, $box->hasAttribute('disabled'), $case.' : '.($write ? 'le cachet est éteint pour qui peut écrire' : 'le cachet reste actif pour un planning limité'));

	}

}
?>
