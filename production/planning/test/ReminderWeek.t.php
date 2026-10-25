<?php
namespace planning;

/**
 * Le pense-bête sur l'écran Semaine (R-PenseBete, habit) : l'interrupteur de « Personnaliser le
 * planning », le bloc qu'il fait paraître, et les gestes qui écrivent un rappel, l'ajout, la coche, la
 * décoche, la modification sur place et l'effacement, passés par les routes de la page comme le
 * navigateur les poste.
 *
 * Les gestes forgés s'éprouvent ici (rappel déjà fait ou déjà effacé, rappel d'une autre structure,
 * planning limité), comme l'encodage du texte au rendu.
 *
 * Chaque rappel est monté dans la transaction de la méthode, à l'heure figée par `HarvestFixtureTest`
 * (jeudi de la semaine 39). L'écran après chaque réponse se compare au rechargement dans `FingerprintTest`.
 *
 * Lancement : dev/test package=planning file=ReminderWeek
 */
class ReminderWeekTest extends HarvestFixtureTest {

	/**
	 * L'interrupteur allume le pense-bête pour le seul membre connecté, la réponse redessine les réglages,
	 * et la Semaine rechargée montre le bloc avec son « + ».
	 */
	public function testSwitch(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderCustomize();

		$this->assertTrue($document->querySelector('#planning-customize-tools [post-view-planning-reminders="1"]') !== NULL, 'la page de personnalisation n\'a pas l\'interrupteur du pense-bête');

		[, $instructions, $action] = $this->callWeek('doReminders', ['viewPlanningReminders' => '1']);

		if($this->assertTrue($action instanceof \ViewAction, 'l\'interrupteur est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue($this->getInstruction($instructions, '#planning-customize-tools', 'outerHtml') !== NULL, 'les réglages ne sont pas redessinés');
		$this->assertTrue($this->getStoredSwitch(\structure\StructureLib::getById($this->eStructure['id'])->getMember()), 'le pense-bête allumé n\'est pas en base');

		$this->reconnect($this->getMember());

		[$document] = $this->renderTasks();

		$block = $document->getElementById('planning-reminder-block');

		if($this->assertTrue($block !== NULL, 'le pense-bête allumé ne paraît pas sur la Semaine') === FALSE) {
			return;
		}

		$this->assertEquals('-1', $block->getAttribute('data-util-piles-rank'), 'le pense-bête ne passe pas avant les blocs d\'action');
		$this->assertFalse($block->classList->contains('planning-week-block'), 'le pense-bête porte la classe des blocs d\'intervention, que la recherche masquerait');
		$this->assertTrue($block->querySelector('a[href*="taskWeek:reminderCreate"][href*="view='.\structure\Member::CARDS.'"]') !== NULL, 'le pense-bête n\'a pas son « + », ou il ne dit pas les cartes au panneau');

		[, , $action] = $this->callWeek('doReminders', ['viewPlanningReminders' => '0']);

		$this->assertTrue($action instanceof \ViewAction and $this->getStoredSwitch(\structure\StructureLib::getById($this->eStructure['id'])->getMember()) === FALSE, 'le pense-bête ne s\'éteint pas');

	}

	/**
	 * Sur une semaine sans intervention, le pense-bête allumé ne compte pas dans la hauteur des piles, pour
	 * que la feuille du planning étende l'état vide à sa droite (lot CL, retour 296).
	 */
	public function testEmptyWeek(): void {

		$week = toWeek(week_date_starts(\Clock::week()).' +70 days');

		$this->switchOn();

		[$document] = $this->renderTasks(week: $week);

		$this->assertTrue($document->getElementById('planning-reminder-block')->hasAttribute('data-util-piles-uncounted'), 'le pense-bête compte dans la hauteur des piles : MainPiles.stack() rangerait l\'état vide dans une autre pile que la sienne, et la feuille ne le placerait plus à sa droite');

	}

	/**
	 * Le rappel ajouté prend la semaine de l'écran, ferme le panneau et ne redessine que le bloc. Un jour
	 * d'une autre semaine ou un texte trop long sont refusés par `build()` sans rien écrire. L'état et qui
	 * a coché, postés, sont ignorés.
	 */
	public function testCreate(): void {

		$this->switchOn();

		$week = \Clock::week();

		$action = $this->executePage('planning/taskWeek:reminderCreate', ['id' => $this->eStructure['id'], 'week' => $week], 'GET');
		$this->assertTrue($action instanceof \ViewAction, 'le panneau « Nouveau rappel » ne s\'ouvre pas');

		[, $instructions, $action] = $this->callWeek('doReminderCreate', [
			'text' => 'Appeler le garage',
			'date' => week_date_day($week, 3),
			'status' => Reminder::DONE,
			'doneBy' => $this->getMember()['id'],
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'le rappel est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$cReminder = ReminderLib::getForWeek($this->eStructure, $week)['cReminder'];

		$this->assertEquals(1, $cReminder->count(), 'le rappel n\'est pas dans la semaine de l\'écran');
		$this->assertEquals([Reminder::TODO, week_date_day($week, 3)], [$cReminder->first()['status'], $cReminder->first()['date']], 'le rappel n\'a pas l\'état et le jour attendus');
		$this->assertTrue($cReminder->first()['doneBy']->empty(), 'qui a coché, posté, a été écrit');

		$this->assertEquals(['#planning-reminder-block'], $this->getSelectors($instructions), 'la réponse touche autre chose que le pense-bête');
		$this->assertTrue(str_contains(json_encode($instructions), 'panel-planning-reminder-create'), 'le panneau ne se ferme pas');
		$this->assertEquals([['#planning-reminder-'.$cReminder->first()['id']]], $this->getJs($instructions, 'highlight'), 'le rappel ajouté ne s\'éclaire pas');
		$this->assertEquals([], $this->getJs($instructions, 'success'), 'l\'ajout dit encore son succès');

		[, , $action] = $this->callWeek('doReminderCreate', ['text' => 'Lundi suivant', 'date' => week_date_day($week, 8)]);
		$this->assertEquals(['Reminder::date.dateInWeek'], $this->getFails($action), 'un jour d\'une autre semaine n\'est pas refusé');

		[, , $action] = $this->callWeek('doReminderCreate', ['text' => str_repeat('a', 251)]);
		$this->assertEquals(['Reminder::text.check'], $this->getFails($action), '251 caractères ne sont pas refusés');

		$this->assertEquals(1, ReminderLib::getForWeek($this->eStructure, $week)['cReminder']->count(), 'un rappel refusé a été écrit');

	}

	/**
	 * La coche pose l'heure de l'horloge et la personne connectée ; la décoche les retire. L'une et
	 * l'autre ne redessinent que la ligne, à sa place, et la tête qui porte le compte.
	 */
	public function testCheckUncheck(): void {

		$this->switchOn();

		$eReminder = $this->createReminder(\Clock::week(), 'À cocher');

		[, $instructions, $action] = $this->callWeek('doReminderCheck', ['reminder' => $eReminder['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eReminderDone = ReminderLib::getById($eReminder['id']);

		$this->assertEquals(Reminder::DONE, $eReminderDone['status'], 'le rappel coché n\'est pas fait');
		$this->assertEquals(\Clock::datetime(), $eReminderDone['doneAt'], 'la coche ne porte pas l\'heure de l\'horloge');
		$this->assertEquals($this->getMember()['id'], $eReminderDone['doneBy']['id'], 'la coche ne porte pas la personne connectée');

		$this->assertEquals(['#planning-reminder-'.$eReminder['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'la coche ne redessine pas seulement la ligne et la tête');

		$html = $this->getInstruction($instructions, '#planning-reminder-'.$eReminder['id'], 'outerHtml');

		$this->assertTrue(str_contains($html, 'doReminderUncheck'), 'la ligne redessinée ne se décoche pas');

		[, $instructions, $action] = $this->callWeek('doReminderUncheck', ['reminder' => $eReminder['id']]);

		$eReminderTodo = ReminderLib::getById($eReminder['id']);

		$this->assertTrue($action instanceof \ViewAction and $eReminderTodo['status'] === Reminder::TODO and $eReminderTodo['doneAt'] === NULL and $eReminderTodo['doneBy']->empty(), 'la décoche ne remet pas le rappel à faire, sans personne ni heure');
		$this->assertEquals(['#planning-reminder-'.$eReminder['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'la décoche ne redessine pas seulement la ligne et la tête');

		// Un resté coché reste à sa place, barré : la semaine en cours est celle de sa coche (FX)
		$eReminderBefore = $this->createReminder(toWeek(week_date_starts(\Clock::week()).' -7 days'), 'Resté à cocher');

		[, $instructions] = $this->callWeek('doReminderCheck', ['reminder' => $eReminderBefore['id']]);

		$this->assertEquals(['#planning-reminder-'.$eReminderBefore['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'le resté coché ne redessine pas seulement sa ligne et la tête');

		$html = $this->getInstruction($instructions, '#planning-reminder-'.$eReminderBefore['id'], 'outerHtml');

		$this->assertTrue(str_contains($html, 'doReminderUncheck'), 'le resté coché ne se redessine pas avec sa décoche');

		[, $instructions] = $this->callWeek('doReminderUncheck', ['reminder' => $eReminderBefore['id']]);

		$this->assertEquals(['#planning-reminder-'.$eReminderBefore['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'le resté décoché ne redessine pas seulement sa ligne et la tête');

	}

	/**
	 * Des coches forgées : sur un rappel déjà fait, rien ne se réécrit ; sur un rappel d'une autre
	 * structure que celle de l'écran, refus ; par le planning limité, refus. Aucune n'écrit.
	 */
	public function testCheckForged(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$this->switchOn();

		$eReminder = $this->createReminder(\Clock::week(), 'Déjà fait');
		ReminderLib::check($eReminder);

		$doneAt = ReminderLib::getById($eReminder['id'])['doneAt'];

		$this->setClock(\Clock::format('Y-m-d').' 11:00:00');

		[, $instructions, $action] = $this->callWeek('doReminderCheck', ['reminder' => $eReminder['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'une coche sur un rappel déjà fait lève au lieu de redessiner la ligne');
		$this->assertEquals($doneAt, ReminderLib::getById($eReminder['id'])['doneAt'], 'une coche sur un rappel déjà fait a réécrit l\'heure');
		$this->assertEquals(['#planning-reminder-'.$eReminder['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'une coche sur un rappel déjà fait ne redessine pas la ligne et la tête');

		$eReminderForeign = $this->createReminder(\Clock::week(), 'Autre structure', $this->getStructureForeign());

		[, , $action] = $this->callWeek('doReminderCheck', ['reminder' => $eReminderForeign['id']]);

		$this->assertFalse($action instanceof \ViewAction, 'une coche sur le rappel d\'une autre structure est acceptée');
		$this->assertEquals(Reminder::TODO, ReminderLib::getById($eReminderForeign['id'])['status'], 'le rappel d\'une autre structure a été coché');

		$eReminderLimited = $this->createReminder(\Clock::week(), 'Pour le planning limité');

		$this->reconnect($this->getMemberPlanning());

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canPlanningLimited() and \structure\StructureLib::getById($this->eStructure['id'])->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		[, , $action] = $this->callWeek('doReminderCheck', ['reminder' => $eReminderLimited['id']]);

		$this->assertFalse($action instanceof \ViewAction, 'le planning limité coche un rappel');
		$this->assertEquals(Reminder::TODO, ReminderLib::getById($eReminderLimited['id'])['status'], 'le planning limité a coché un rappel');

		[, , $action] = $this->callWeek('doReminderUncheck', ['reminder' => $eReminder['id']]);

		$this->assertFalse($action instanceof \ViewAction, 'le planning limité décoche un rappel');
		$this->assertEquals(Reminder::DONE, ReminderLib::getById($eReminder['id'])['status'], 'le planning limité a décoché un rappel');

		[, , $action] = $this->callWeek('doReminderCreate', ['text' => 'Ajout du planning limité']);

		$this->assertFalse($action instanceof \ViewAction, 'le planning limité ajoute un rappel');

		$action = $this->executePage('planning/taskWeek:reminderCreate', ['id' => $this->eStructure['id'], 'week' => \Clock::week()], 'GET');

		$this->assertTrue($action instanceof \NotAllowedAction, 'le panneau « Nouveau rappel » s\'ouvre au planning limité');

		[$document] = $this->renderTasks();

		$block = $document->getElementById('planning-reminder-block');

		if($this->assertTrue($block !== NULL, 'le planning limité ne voit pas le pense-bête') === FALSE) {
			return;
		}

		$this->assertEquals(0, $block->querySelectorAll('[data-ajax], a[href]')->length, 'le pense-bête du planning limité porte un geste');
		$this->assertTrue($block->querySelectorAll('.planning-reminder')->length >= 2, 'le planning limité ne lit pas les rappels');

	}

	/**
	 * Une semaine passée montre ce qui y était vrai, et seul ce qui l'est encore s'y défait (FX, suites de la
	 * réponse 3) : le rappel coché dans sa semaine s'y décoche, puis revient à faire jusqu'à la semaine en
	 * cours. Celui qui s'y lit à faire, et celui coché plus tard, n'y ont pas de geste. Ni « + » ni
	 * correction sur place, en cartes comme en grille.
	 */
	public function testPastWeekUnchecksOnlyItsCheck(): void {

		$this->switchOn();

		$now = \Clock::datetime();
		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');

		$this->createReminder($weekBefore, 'À faire, semaine passée');
		ReminderLib::check($this->createReminder($weekBefore, 'Coché plus tard'));

		$eReminderChecked = $this->createReminder($weekBefore, 'Coché dans sa semaine');

		$this->setClock(week_date_day($weekBefore, 2).' 09:00:00');
		ReminderLib::check($eReminderChecked);
		$this->setClock($now);

		foreach([\structure\Member::CARDS, \structure\Member::GRID] as $view) {

			\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), $view);

			[$document] = $this->renderTasks(week: $weekBefore);

			$block = $document->getElementById('planning-reminder-block');

			if($this->assertTrue($block !== NULL, $view.' : le pense-bête ne paraît pas sur la semaine passée') === FALSE) {
				return;
			}

			$this->assertEquals(3, $block->querySelectorAll('.planning-reminder, .planning-grid-reminder')->length, $view.' : la semaine passée ne montre pas ses trois rappels');
			$this->assertEquals([$eReminderChecked['id']], $this->getCheckable($block, $view), $view.' : une autre ligne que le rappel barré dans sa semaine a un geste');
			$this->assertTrue($block->querySelector('a[href*="taskWeek:reminderCreate"]') === NULL, $view.' : une semaine passée prend un rappel');
			$this->assertTrue(str_contains($block->innerHTML, 'doReminderUpdate') === FALSE, $view.' : une semaine passée corrige un rappel sur place');

		}

		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), \structure\Member::CARDS);

		[, $instructions, $action] = $this->callWeek('doReminderUncheck', ['reminder' => $eReminderChecked['id'], 'week' => $weekBefore]);

		if($this->assertTrue($action instanceof \ViewAction, 'la décoche d\'une semaine passée est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(Reminder::TODO, ReminderLib::getById($eReminderChecked['id'])['status'], 'la décoche d\'une semaine passée n\'écrit pas');

		$html = $this->getInstruction($instructions, '#planning-reminder-'.$eReminderChecked['id'], 'outerHtml');

		$this->assertTrue($html !== NULL and str_contains($html, 'doReminderCheck') === FALSE, 'le rappel décoché dans une semaine passée s\'y coche de nouveau');

		[$document] = $this->renderTasks();

		$this->assertTrue($document->querySelector('#planning-reminder-'.$eReminderChecked['id'].' [data-ajax*="doReminderCheck"]') !== NULL, 'le rappel décoché ne revient pas à faire dans la semaine en cours');

	}

	/**
	 * Le serveur ferme ce que l'écran d'une semaine passée ne propose plus, un POST se forgeant : l'ajout,
	 * par l'étape `notPast` de la semaine, et la correction d'un rappel qu'aucune semaine en cours ou à
	 * venir ne montre, par `acceptUpdate()`. Un resté à faire se corrige encore, il s'affiche dans la
	 * semaine en cours. Aucun refus n'écrit.
	 */
	public function testPastWeekRefusesWrites(): void {

		$this->switchOn();

		$now = \Clock::datetime();
		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');

		$action = $this->executePage('planning/taskWeek:reminderCreate', ['id' => $this->eStructure['id'], 'week' => $weekBefore], 'GET');
		$this->assertTrue($action instanceof \NotAllowedAction, 'le panneau « Nouveau rappel » s\'ouvre sur une semaine passée');

		[, , $action] = $this->callWeek('doReminderCreate', ['text' => 'Trop tard', 'week' => $weekBefore]);
		$this->assertEquals(['Reminder::week.notPast'], $this->getFails($action), 'une semaine passée prend un rappel');
		$this->assertEquals(0, ReminderLib::getForWeek($this->eStructure, $weekBefore)['cReminderBefore']->count() + ReminderLib::getForWeek($this->eStructure, $weekBefore)['cReminder']->count(), 'le rappel refusé a été écrit');

		$eReminderChecked = $this->createReminder($weekBefore, 'Coché dans sa semaine');

		$this->setClock(week_date_day($weekBefore, 2).' 09:00:00');
		ReminderLib::check($eReminderChecked);
		$this->setClock($now);

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderChecked['id'], 'text' => 'Corrigé', 'week' => $weekBefore]);
		$this->assertEquals(['planning\\Reminder::updatePast'], $this->getFails($action), 'un rappel coché dans une semaine passée se corrige');
		$this->assertEquals('Coché dans sa semaine', ReminderLib::getById($eReminderChecked['id'])['text'], 'la correction refusée a été écrite');

		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderBefore['id'], 'text' => 'Resté corrigé']);
		$this->assertTrue($action instanceof \ViewAction, 'un resté à faire ne se corrige plus dans la semaine en cours : '.implode(', ', $this->getFails($action)));
		$this->assertEquals('Resté corrigé', ReminderLib::getById($eReminderBefore['id'])['text'], 'la correction du resté n\'est pas écrite');

	}

	/**
	 * L'effacement suit la correction (FX) : un rappel coché dans une semaine passée ne s'efface plus, même
	 * par une route forgée. Un rappel resté à faire et un rappel coché dans la semaine en cours s'effacent.
	 */
	public function testPastWeekRefusesDelete(): void {

		$this->switchOn();

		$now = \Clock::datetime();
		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');

		$eReminderChecked = $this->createReminder($weekBefore, 'Coché dans sa semaine');

		$this->setClock(week_date_day($weekBefore, 2).' 09:00:00');
		ReminderLib::check($eReminderChecked);
		$this->setClock($now);

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderChecked['id'], 'week' => $weekBefore]);
		$this->assertEquals(['planning\\Reminder::deletePast'], $this->getFails($action), 'un rappel coché dans une semaine passée s\'efface');
		$this->assertTrue(ReminderLib::getById($eReminderChecked['id'])->notEmpty(), 'l\'effacement refusé a été écrit');

		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderBefore['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'un resté à faire ne s\'efface pas dans la semaine en cours : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(ReminderLib::getById($eReminderBefore['id'])->empty(), 'l\'effacement du resté n\'est pas écrit');

		$eReminderNow = $this->createReminder($week, 'Coché cette semaine');
		ReminderLib::check($eReminderNow);

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderNow['id']]);
		$this->assertTrue($action instanceof \ViewAction, 'un rappel coché dans la semaine en cours ne s\'efface pas : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(ReminderLib::getById($eReminderNow['id'])->empty(), 'l\'effacement du rappel coché n\'est pas écrit');

	}

	/**
	 * Les rappels qu'une semaine passée laisse cocher ou décocher : les liens des cartes, les cases actives
	 * de la grille.
	 */
	private function getCheckable(\Dom\Element $block, string $view): array {

		$ids = [];

		$selector = ($view === \structure\Member::GRID) ?
			'[data-planning-grid-reminder]:not([disabled])' :
			'[data-ajax*="doReminderCheck"], [data-ajax*="doReminderUncheck"]';

		foreach($block->querySelectorAll($selector) as $node) {
			$ids[] = (int)substr($node->closest('[id^="planning-reminder-"]:not([id^="planning-reminder-edit"])')->getAttribute('id'), strlen('planning-reminder-'));
		}

		return $ids;

	}

	/**
	 * Le texte d'un rappel est rendu encodé : du HTML tapé s'affiche, il ne s'interprète pas.
	 */
	public function testEncode(): void {

		$this->switchOn();

		$this->createReminder(\Clock::week(), '<b>Appeler</b> l\'ostéo & <script>x</script>');

		[$document] = $this->renderTasks();

		$label = $document->querySelector('#planning-reminder-block .planning-reminder-label');

		if($this->assertTrue($label !== NULL, 'le rappel n\'est pas rendu') === FALSE) {
			return;
		}

		$this->assertEquals(0, $label->childElementCount, 'le texte du rappel est interprété comme du HTML');
		$this->assertEquals('<b>Appeler</b> l\'ostéo & <script>x</script>', $label->textContent, 'le texte du rappel n\'est pas rendu tel qu\'écrit');

	}

	/**
	 * Le bloc se range en tête des piles sans peser dans la sienne (R-Retours-0710, 168).
	 */
	public function testBlock(): void {

		$this->switchOn();

		[$document] = $this->renderTasks();

		$block = $document->getElementById('planning-reminder-block');

		$this->assertEquals('-1', $block->getAttribute('data-util-piles-rank'), 'le pense-bête ne se range pas avant les blocs d\'action');
		$this->assertTrue($block->hasAttribute('data-util-piles-uncounted'), 'la hauteur du pense-bête compte dans sa pile');

	}

	/**
	 * « Nouveau rappel » propose « Non défini » puis les sept jours de la semaine de l'écran. « Non défini »
	 * est choisi : le champ caché part vide, rappel sans jour.
	 */
	public function testPanel(): void {

		$this->switchOn();

		$week = \Clock::week();

		$action = $this->executePage('planning/taskWeek:reminderCreate', ['id' => $this->eStructure['id'], 'week' => $week], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Nouveau rappel » ne s\'ouvre pas') === FALSE) {
			return;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.new ReminderUi()->create($data->eReminder, $data->view)->body.'</body>', LIBXML_NOERROR);

		$days = array_map(fn($day) => $day->getAttribute('data-planning-reminder-day'), iterator_to_array($document->querySelectorAll('[data-planning-reminder-day]')));

		$this->assertEquals(array_merge([''], week_dates($week)), $days, 'le panneau ne propose pas « Non défini » puis les sept jours de la semaine de l\'écran');
		$input = $document->querySelector('input[type="hidden"][name="date"]');

		$this->assertTrue($input !== NULL and ($input->getAttribute('value') ?? '') === '', 'le champ caché du jour manque ou ne part pas vide');
		$this->assertTrue($document->querySelector('input[type="hidden"][name="view"][value="'.$data->view.'"]') !== NULL, 'le panneau ne réémet pas l\'affichage qui l\'a ouvert');
		$this->assertEquals([''], array_map(fn($day) => $day->getAttribute('data-planning-reminder-day'), iterator_to_array($document->querySelectorAll('.is-selected'))), '« Non défini » n\'est pas seul choisi d\'office');

	}

	/**
	 * « Non défini » ouvre la rangée des jours (R-Retours-1210, BM), dans le panneau comme sur place : choisi
	 * quand le rappel n'a pas de jour, et lui seul ; laissé quand un jour est retenu. Il porte un jour vide,
	 * que `PlanningReminderDay` écrit dans le champ caché, et un rappel daté repasse ainsi sans jour.
	 */
	public function testDayNone(): void {

		$this->switchOn();

		$week = \Clock::week();

		$action = $this->executePage('planning/taskWeek:reminderCreate', ['id' => $this->eStructure['id'], 'week' => $week], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le panneau « Nouveau rappel » ne s\'ouvre pas') === FALSE) {
			return;
		}

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$panel = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.new ReminderUi()->create($data->eReminder, $data->view)->body.'</body>', LIBXML_NOERROR);

		$this->assertDayNone($panel->querySelector('.planning-reminder-days'), TRUE, 'le panneau');

		$eReminderDated = $this->createReminder($week, 'Mardi', date: week_date_day($week, 2));
		$eReminderUndated = $this->createReminder($week, 'Dans la semaine');

		[$document] = $this->renderTasks();

		$this->assertDayNone($document->querySelector('[data-quick-inline-for="planning-reminder-edit-'.$eReminderDated['id'].'"] .planning-reminder-days'), FALSE, 'le rappel daté');
		$this->assertDayNone($document->querySelector('[data-quick-inline-for="planning-reminder-edit-'.$eReminderUndated['id'].'"] .planning-reminder-days'), TRUE, 'le rappel sans jour');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderDated['id'], 'text' => 'Mardi', 'date' => '']);

		$this->assertTrue($action instanceof \ViewAction and ReminderLib::getById($eReminderDated['id'])['date'] === NULL, '« Non défini » ne rend pas le rappel daté sans jour');

		[$document] = $this->renderTasks();

		$this->assertDayNone($document->querySelector('[data-quick-inline-for="planning-reminder-edit-'.$eReminderDated['id'].'"] .planning-reminder-days'), TRUE, 'le rappel repassé sans jour');

	}

	private function assertDayNone(?\Dom\Element $days, bool $selected, string $case): void {

		if($this->assertTrue($days !== NULL, $case.' n\'a pas de rangée des jours') === FALSE) {
			return;
		}

		$none = $days->firstElementChild;

		$this->assertTrue($none->getAttribute('data-planning-reminder-day') === '', $case.' : la première pastille n\'est pas « Non défini »');
		$this->assertEquals($selected, $none->classList->contains('is-selected'), $case.' : « Non défini » '.($selected ? 'n\'est pas choisi' : 'est choisi'));
		$this->assertEquals(1, $days->querySelectorAll('.is-selected')->length, $case.' : la rangée n\'a pas un seul choix');

	}

	/**
	 * Sur place, le texte et le jour se corrigent en une écriture, et la réponse redessine le bloc puis
	 * éclaire la ligne. Un jour hors de la semaine du rappel est refusé, un jour vidé rend le rappel sans
	 * jour. Un resté se date dans **sa** semaine, et non dans celle de l'écran. Sa semaine, postée, est
	 * ignorée.
	 */
	public function testUpdate(): void {

		$this->switchOn();

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');

		$eReminder = $this->createReminder($week, 'Appeler', date: week_date_day($week, 1));

		[, $instructions, $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Appeler le garage', 'date' => week_date_day($week, 4)]);

		if($this->assertTrue($action instanceof \ViewAction, 'la modification est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eReminderUpdated = ReminderLib::getById($eReminder['id']);

		$this->assertEquals(['Appeler le garage', week_date_day($week, 4), $week], [$eReminderUpdated['text'], $eReminderUpdated['date'], $eReminderUpdated['week']], 'le texte et le jour ne sont pas écrits ensemble');
		$this->assertEquals(['#planning-reminder-block'], $this->getSelectors($instructions), 'la modification touche autre chose que le pense-bête');
		$this->assertEquals([['#planning-reminder-'.$eReminder['id']]], $this->getJs($instructions, 'highlight'), 'le rappel modifié ne s\'éclaire pas');
		$this->assertEquals([], $this->getJs($instructions, 'success'), 'la modification dit son succès');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Ailleurs', 'date' => week_date_day($week, 8)]);

		$this->assertEquals(['Reminder::date.dateInWeek'], $this->getFails($action), 'un jour hors de la semaine du rappel n\'est pas refusé');
		$this->assertEquals('Appeler le garage', ReminderLib::getById($eReminder['id'])['text'], 'une modification refusée a écrit le texte');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => '', 'date' => '']);

		$this->assertEquals(['Reminder::text.check'], $this->getFails($action), 'un texte vide n\'est pas refusé');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Sans jour', 'date' => '']);

		$this->assertTrue($action instanceof \ViewAction and ReminderLib::getById($eReminder['id'])['date'] === NULL, 'le jour vidé n\'est pas retiré');

		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderBefore['id'], 'text' => 'Resté', 'date' => week_date_day($weekBefore, 2)]);

		$this->assertTrue($action instanceof \ViewAction and ReminderLib::getById($eReminderBefore['id'])['date'] === week_date_day($weekBefore, 2), 'un resté ne prend pas un jour de sa semaine');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderBefore['id'], 'text' => 'Resté', 'date' => week_date_day($week, 2)]);

		$this->assertEquals(['Reminder::date.dateInWeek'], $this->getFails($action), 'un resté prend un jour de la semaine de l\'écran');
		$this->assertEquals([$weekBefore, week_date_day($weekBefore, 2)], [ReminderLib::getById($eReminderBefore['id'])['week'], ReminderLib::getById($eReminderBefore['id'])['date']], 'le resté a changé de semaine ou de jour');

	}

	/**
	 * L'effacement, à ses trois granularités, décidées par la page : un rappel parmi d'autres s'efface à
	 * sa place, avec la tête qui porte le compte ; le dernier resté emmène le titre de sa section ; le
	 * dernier rappel rend le bloc entier, sans effacement animé dans un bloc remplacé.
	 */
	public function testDelete(): void {

		$this->switchOn();

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');

		$eReminder = $this->createReminder($week, 'Premier');
		$eReminderSecond = $this->createReminder($week, 'Second');
		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');

		[, $instructions, $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderSecond['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'l\'effacement est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue(ReminderLib::getById($eReminderSecond['id'])->empty(), 'le rappel effacé est encore en base');
		$this->assertEquals(['#planning-reminder-'.$eReminderSecond['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'l\'effacement ne touche pas seulement la ligne et la tête');
		$this->assertEquals([['vanish' => TRUE]], $this->getCall($instructions, '#planning-reminder-'.$eReminderSecond['id'], 'remove'), 'la ligne ne s\'efface pas à sa place');
		$this->assertEquals([], $this->getJs($instructions, 'success'), 'l\'effacement dit son succès');

		[, $instructions] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderBefore['id']]);

		$this->assertEquals(['#planning-reminder-restes', '#planning-reminder-'.$eReminderBefore['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'le dernier resté n\'emmène pas le titre de sa section');
		$this->assertEquals([['vanish' => TRUE]], $this->getCall($instructions, '#planning-reminder-restes', 'remove'), 'le titre des restés ne s\'efface pas à sa place');

		[, $instructions] = $this->callWeek('doReminderDelete', ['reminder' => $eReminder['id']]);

		$this->assertEquals(['#planning-reminder-block'], $this->getSelectors($instructions), 'le pense-bête vidé n\'est pas rendu entier');
		$this->assertEquals([], $this->getJs($instructions, 'highlight'), 'le pense-bête vidé éclaire un rappel effacé');

	}

	/**
	 * DC-bis · Dans la grille, le pense-bête est un groupe du tableau, sous les identifiants de la note :
	 * allumé sans rappel, il garde son « + ». Une ligne porte la case à cocher du rappel, hors de la sélection
	 * des interventions et de la recherche, et chaque geste dit la grille. Un rappel coché porte la classe
	 * d'une ligne faite. La coche répond par une ligne de grille, un départ par un retrait sans copie animée.
	 */
	public function testGrid(): void {

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		\structure\MemberLib::setView('viewPlanningWeek', $eStructure, \structure\Member::GRID);
		$this->switchOn();

		[$document] = $this->renderTasks();

		$group = $document->querySelector('#planning-grid > .planning-grid-reminders');

		if($this->assertTrue($group !== NULL and $group->getAttribute('id') === 'planning-reminder-block', 'le pense-bête allumé n\'est pas un groupe de la grille, sous l\'identifiant de la note') === FALSE) {
			return;
		}

		$this->assertTrue($group->querySelector('#planning-reminder-head a[href*="taskWeek:reminderCreate"]') !== NULL, 'le pense-bête vide n\'a pas son « + »');

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');
		$friday = week_date_day($week, 5);

		$eReminder = $this->createReminder($week, 'Vendredi', date: $friday);
		$eReminderSecond = $this->createReminder($week, 'Sans jour');
		$eReminderPast = $this->createReminder($week, 'Lundi', date: week_date_day($week, 1));
		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');

		[$document] = $this->renderTasks();

		$row = $document->getElementById('planning-reminder-'.$eReminder['id']);

		if($this->assertTrue($row !== NULL and $row->classList->contains('planning-grid-row') and $row->closest('#planning-reminder-block') !== NULL, 'le rappel n\'est pas une ligne du groupe') === FALSE) {
			return;
		}

		$this->assertTrue($row->querySelector('input[data-planning-grid-reminder="'.$eReminder['id'].'"]:not([checked]):not([disabled])') !== NULL, 'le rappel n\'a pas sa case à cocher');
		$this->assertEquals(0, $row->querySelectorAll('[name="batch[]"]')->length, 'le rappel entre dans la sélection des interventions (DG-3)');

		// Un jour passé n'a pas de geste, pas même celui qui retire le jour d'un rappel posé sur lui (DY, retour 365).
		$monday = week_date_day($week, 1);
		$rowPast = $document->getElementById('planning-reminder-'.$eReminderPast['id']);
		$this->assertEquals(
			array_values(array_map(fn(string $date) => ($date === $monday) ? '' : $date, array_filter(week_dates($week), fn(string $date) => $date >= \Clock::date()))),
			array_map(fn(\Dom\Element $link) => $link->getAttribute('post-date'), iterator_to_array($rowPast?->querySelectorAll('a[data-ajax$=":doReminderUpdate"][post-origin="date"]') ?? [])),
			'un jour passé du rappel a un geste, ou aujourd\'hui et les jours à venir n\'en ont pas'
		);
		$this->assertEquals(0, $row?->querySelectorAll('[data-search]')->length, 'un rappel porte ce que lit la recherche');

		// Chaque geste dit la grille, sans quoi la page retombe sur la préférence du membre, qu'un autre onglet
		// a pu changer depuis : le « + », l'édition sur place et « Effacer » (le cachet la lit sur `#planning-week`).
		$grid = \structure\Member::GRID;
		$this->assertTrue($document->querySelector('#planning-reminder-head a[href*="taskWeek:reminderCreate"][href*="view='.$grid.'"]') !== NULL, 'le « + » de la grille ne dit pas la grille au panneau');
		$this->assertTrue($row?->querySelector('input[type="hidden"][name="view"][value="'.$grid.'"]') !== NULL, 'l\'édition sur place ne dit pas la grille');
		$this->assertTrue($row?->querySelector('a[data-ajax$=":doReminderDelete"][post-view="'.$grid.'"]') !== NULL, '« Effacer » ne dit pas la grille');

		[, $instructions, $action] = $this->callWeek('doReminderCheck', ['reminder' => $eReminder['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée en grille : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(['#planning-reminder-'.$eReminder['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'la coche ne redessine pas seulement la ligne et la tête');

		$html = $this->getInstruction($instructions, '#planning-reminder-'.$eReminder['id'], 'outerHtml');

		$this->assertTrue(str_contains($html, 'planning-grid-row-done') and str_contains($html, 'data-planning-grid-reminder="'.$eReminder['id'].'"') and str_contains($html, 'checked="checked"'), 'la ligne cochée n\'est pas une ligne de grille faite, à décocher');

		// Le membre a choisi la grille, mais l'onglet qui fait le geste montre les cartes : la réponse est une ligne
		// de la note.
		[, $instructions] = $this->callWeek('doReminderUncheck', ['reminder' => $eReminder['id'], 'view' => \structure\Member::CARDS]);

		$html = $this->getInstruction($instructions, '#planning-reminder-'.$eReminder['id'], 'outerHtml') ?? '';

		$this->assertTrue(str_contains($html, 'planning-grid-row') === FALSE and str_contains($html, 'post-view="'.\structure\Member::CARDS.'"'), 'un geste fait depuis les cartes répond par une ligne de grille, sur la préférence du membre');

		[, $instructions] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderSecond['id']]);

		$this->assertEquals([[]], $this->getCall($instructions, '#planning-reminder-'.$eReminderSecond['id'], 'remove'), 'en grille, la ligne effacée part avec une copie animée');

		[, $instructions] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderBefore['id']]);

		$this->assertEquals(['#planning-reminder-'.$eReminderBefore['id'], '#planning-reminder-head'], $this->getSelectors($instructions), 'en grille, le dernier resté vise un titre de section');

	}

	/**
	 * DP · Dans la grille, un rappel à faire de la semaine se donne un jour depuis aujourd'hui et les jours à
	 * venir (DY, retour 365), et sa case le retire, par l'édition réduite au jour : le texte posté avec le
	 * geste n'est pas lu. La route accepte toujours un jour passé, que les Cartes proposent. Un resté, un rappel
	 * fait et le planning limité n'ont aucun geste.
	 */
	public function testGridDay(): void {

		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), \structure\Member::GRID);
		$this->switchOn();

		$week = \Clock::week();
		$monday = week_date_day($week, 1);
		$thursday = week_date_day($week, 4);

		$eReminder = $this->createReminder($week, 'Jeudi', date: $thursday);
		$eReminderDone = $this->createReminder($week, 'Fait');
		$eReminderBefore = $this->createReminder(toWeek(week_date_starts($week).' -7 days'), 'Resté');

		ReminderLib::check($eReminderDone);

		[$document] = $this->renderTasks();

		$row = $document->getElementById('planning-reminder-'.$eReminder['id']);
		$dates = array_map(fn(\Dom\Element $link) => $link->getAttribute('post-date'), iterator_to_array($row?->querySelectorAll('a[data-ajax$=":doReminderUpdate"][post-origin="date"]') ?? []));

		$this->assertEquals(array_values(array_map(fn(string $date) => ($date === $thursday) ? '' : $date, array_filter(week_dates($week), fn(string $date) => $date >= \Clock::date()))), $dates, 'aujourd\'hui et les jours à venir ne donnent pas leur jour au rappel, un jour passé en donne un, ou le jour posé ne se retire pas');
		$this->assertEquals(0, $row?->querySelectorAll('[data-planning-grid-plan], [data-planning-grid-unplan]')->length, 'un geste du rappel serait pris pour celui d\'une intervention');

		foreach(['un rappel fait' => $eReminderDone, 'un resté' => $eReminderBefore] as $case => $eReminderCase) {
			$this->assertEquals(0, $document->getElementById('planning-reminder-'.$eReminderCase['id'])?->querySelectorAll('a[post-origin="date"]')->length, $case.' a un geste sur ses jours');
		}

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminder['id'], 'origin' => 'date', 'date' => $monday, 'text' => 'Forgé', 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'le jour choisi dans la grille est refusé : '.implode(', ', $this->getFails($action)));

		$eReminderAfter = ReminderLib::getById($eReminder['id']);

		$this->assertEquals($monday, $eReminderAfter['date'], 'le jour passé choisi n\'est pas écrit');
		$this->assertEquals('Jeudi', $eReminderAfter['text'], 'le texte posté avec le jour a été lu');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminder['id'], 'origin' => 'date', 'date' => '', 'view' => \structure\Member::GRID]);

		$this->assertTrue($action instanceof \ViewAction, 'le retrait du jour est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(NULL, ReminderLib::getById($eReminder['id'])['date'], 'le retrait ne vide pas le jour');

		$this->reconnect($this->getMemberPlanning());

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), \structure\Member::GRID);
		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		[$document] = $this->renderTasks();

		$this->assertEquals(0, $document->querySelectorAll('#planning-reminder-block a[post-origin="date"]')->length, 'le planning limité donne un jour à un rappel');

	}

	/**
	 * Des modifications et des effacements forgés : rappel d'une autre structure que celle de l'écran,
	 * planning limité, rappel déjà effacé. Aucun n'écrit.
	 */
	public function testWriteForged(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$this->switchOn();

		$eReminderForeign = $this->createReminder(\Clock::week(), 'Autre structure', $this->getStructureForeign());

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderForeign['id'], 'text' => 'Forgé']);
		$this->assertFalse($action instanceof \ViewAction, 'une modification du rappel d\'une autre structure est acceptée');

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderForeign['id']]);
		$this->assertFalse($action instanceof \ViewAction, 'un effacement du rappel d\'une autre structure est accepté');

		$this->assertEquals('Autre structure', ReminderLib::getById($eReminderForeign['id'])['text'], 'le rappel d\'une autre structure a été modifié ou effacé');

		$eReminderDeleted = $this->createReminder(\Clock::week(), 'Effacé');
		ReminderLib::delete($eReminderDeleted);

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderDeleted['id'], 'text' => 'Ressuscité']);
		$this->assertFalse($action instanceof \ViewAction, 'une modification d\'un rappel déjà effacé est acceptée');

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderDeleted['id']]);
		$this->assertFalse($action instanceof \ViewAction, 'un effacement d\'un rappel déjà effacé est accepté');

		$this->assertTrue(ReminderLib::getById($eReminderDeleted['id'])->empty(), 'le rappel effacé est revenu');

		$eReminderLimited = $this->createReminder(\Clock::week(), 'Pour le planning limité');

		$this->reconnect($this->getMemberPlanning());

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canPlanningLimited() and \structure\StructureLib::getById($this->eStructure['id'])->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderLimited['id'], 'text' => 'Forgé']);
		$this->assertFalse($action instanceof \ViewAction, 'le planning limité modifie un rappel');

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderLimited['id']]);
		$this->assertFalse($action instanceof \ViewAction, 'le planning limité efface un rappel');

		$this->assertEquals('Pour le planning limité', ReminderLib::getById($eReminderLimited['id'])['text'], 'le planning limité a modifié ou effacé un rappel');

		[$document] = $this->renderTasks();

		$this->assertEquals(0, $document->querySelectorAll('#planning-reminder-block .util-quick-inline')->length, 'le planning limité ouvre un rappel sur place');

	}

	/**
	 * Le rappel d'une autre structure où la même personne écrit aussi au planning : son droit passe, c'est
	 * la structure de l'écran qui refuse, parce que la réponse redessine le pense-bête de cet écran-ci.
	 */
	public function testWriteForgedStructureWritable(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eStructureWritable = $this->joinStructureForeign();

		if($this->assertDecorPresent($eStructureWritable->canPlanning(), 'une autre structure où '.$this->getMember()['firstName'].' écrit au planning') === FALSE) {
			return;
		}

		$this->switchOn();

		$eReminderWritable = $this->createReminder(\Clock::week(), 'Autre structure, même personne', $eStructureWritable);

		$this->assertTrue(ReminderLib::getById($eReminderWritable['id'])->canWrite(), 'la personne n\'écrit pas au planning de l\'autre structure');

		[, , $action] = $this->callWeek('doReminderUpdate', ['reminder' => $eReminderWritable['id'], 'text' => 'Forgé']);
		$this->assertFalse($action instanceof \ViewAction, 'une modification du rappel d\'une autre structure de la même personne est acceptée');

		[, , $action] = $this->callWeek('doReminderDelete', ['reminder' => $eReminderWritable['id']]);
		$this->assertFalse($action instanceof \ViewAction, 'un effacement du rappel d\'une autre structure de la même personne est accepté');

		$this->assertEquals('Autre structure, même personne', ReminderLib::getById($eReminderWritable['id'])['text'], 'le rappel d\'une autre structure de la même personne a été modifié ou effacé');

	}

	/**
	 * Une ligne se modifie sur place pour qui écrit au planning : la boîte enregistre à la sortie du champ,
	 * ses pastilles sont celles de la semaine du rappel, le jour retenu choisi, et « Effacer » demande
	 * confirmation. Un rappel coché se corrige de même.
	 */
	public function testEditLine(): void {

		$this->switchOn();

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');

		$eReminder = $this->createReminder($week, 'Mercredi', date: week_date_day($week, 3));
		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');
		$eReminderDone = $this->createReminder($week, 'Fait');
		ReminderLib::check($eReminderDone);

		[$document] = $this->renderTasks();

		$box = $document->getElementById('planning-reminder-edit-'.$eReminder['id']);

		if($this->assertTrue($box !== NULL, 'le rappel ne s\'ouvre pas sur place') === FALSE) {
			return;
		}

		$this->assertTrue($box->hasAttribute('data-quick-inline-submit-on-blur'), 'la sortie du champ n\'enregistre pas');
		$this->assertTrue(str_contains($box->querySelector('form')->getAttribute('data-ajax-form'), 'doReminderUpdate'), 'la boîte n\'envoie pas à doReminderUpdate');
		$this->assertEquals(week_date_day($week, 3), $box->querySelector('input[name="date"]')->getAttribute('value'), 'le jour retenu ne part pas avec le texte');

		$linked = $document->querySelector('[data-quick-inline-for="planning-reminder-edit-'.$eReminder['id'].'"]');

		$this->assertEquals(week_date_day($week, 3), $linked->querySelector('.is-selected')?->getAttribute('data-planning-reminder-day'), 'le jour retenu n\'est pas choisi');
		$this->assertTrue($linked->querySelector('[data-ajax*="doReminderDelete"][data-confirm]') !== NULL, '« Effacer » manque ou ne demande pas confirmation');

		$linkedBefore = $document->querySelector('[data-quick-inline-for="planning-reminder-edit-'.$eReminderBefore['id'].'"]');

		$this->assertEquals(week_date_day($weekBefore, 1), $linkedBefore->querySelector('[data-planning-reminder-day]:not([data-planning-reminder-day=""])')->getAttribute('data-planning-reminder-day'), 'les pastilles du resté ne sont pas celles de sa semaine');

		$this->assertTrue($document->getElementById('planning-reminder-edit-'.$eReminderDone['id']) !== NULL, 'le rappel coché ne se corrige pas sur place');

	}

	/**
	 * L'ordre décidé le 06/10, que la coche ne change pas : les rappels datés dans l'ordre des
	 * jours, puis ceux sans jour dans l'ordre de leur création, faits ou non ; les restés en dessous,
	 * semaine par semaine, datés d'abord. Les rappels sont créés dans un ordre qui ne recoupe pas
	 * l'attendu, pour que le tri par id seul ne puisse pas passer, et un rappel coché au milieu de la
	 * liste n'en bouge pas. Le compte de la tête est ce qui reste à faire, restés compris.
	 */
	public function testOrder(): void {

		$this->switchOn();

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');
		$weekBeforeBefore = toWeek(week_date_starts($week).' -14 days');

		$this->createReminder($week, 'Sans jour A');
		ReminderLib::check($this->createReminder($week, 'Coché sans jour'));
		$this->createReminder($week, 'Vendredi', date: week_date_day($week, 5));
		ReminderLib::check($this->createReminder($week, 'Coché lundi', date: week_date_day($week, 1)));
		$eReminderMonday = $this->createReminder($week, 'Lundi', date: week_date_day($week, 1));
		$this->createReminder($week, 'Sans jour B');
		$this->createReminder($weekBefore, 'Resté sans jour');
		$this->createReminder($weekBefore, 'Resté mardi', date: week_date_day($weekBefore, 2));
		$this->createReminder($weekBeforeBefore, 'Resté ancien');

		$expected = [
			'Coché lundi', 'Lundi', 'Vendredi', 'Sans jour A', 'Coché sans jour', 'Sans jour B',
			'Resté ancien', 'Resté mardi', 'Resté sans jour',
		];

		$this->assertEquals([$expected, 7], $this->getReminders(), 'les rappels ne suivent pas l\'ordre des jours, puis de création, ou la tête ne compte pas ce qui reste à faire');

		$this->callWeek('doReminderCheck', ['reminder' => $eReminderMonday['id']]);

		$this->assertEquals([$expected, 6], $this->getReminders(), 'un rappel coché a changé de place, ou la tête le compte encore');

	}

	/**
	 * Les textes des rappels que la Semaine prépare, ceux de la semaine puis les restés, et le compte de la
	 * tête (`planningTaskWeekReminders()`).
	 */
	private function getReminders(): array {

		[, $data] = $this->renderTasks();

		$texts = [];

		foreach([$data->reminders['cReminder'], $data->reminders['cReminderBefore']] as $cReminder) {
			foreach($cReminder as $eReminder) {
				$texts[] = $eReminder['text'];
			}
		}

		return [$texts, $data->reminders['todo']];

	}

	/**
	 * Une autre structure que celle du décor, où son membre écrit aussi au planning : il y entre en
	 * administrateur, dans la transaction de la méthode, s'il n'en est pas déjà membre.
	 */
	private function joinStructureForeign(): \structure\Structure {

		$eStructure = \structure\StructureLib::getById($this->getStructureForeign()['id']);

		if(\structure\MemberLib::getByUserAndStructure($this->getMember(), $eStructure)->empty()) {

			$fw = new \FailWatch();

			// Sans `build()` : l'adhésion n'a pas de formulaire, `MemberLib::create()` est le chemin de
			// l'invitation acceptée, et le décor choisit lui-même le rôle qu'un administrateur donnerait.
			\structure\MemberLib::create(new \structure\Member([
				'user' => $this->getMember(),
				'structure' => $eStructure,
				'role' => \structure\RoleLib::getOne($eStructure, 'admin'),
				'status' => \structure\Member::IN,
			]));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion à la structure '.$eStructure['id'].' refusée : '.json_encode($fw->get())));

			\structure\MemberLib::clearCache();

		}

		return \structure\StructureLib::getById($eStructure['id']);

	}

	private function switchOn(): void {

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

	}

	private function createReminder(string $week, string $text, \structure\Structure $eStructure = new \structure\Structure(), ?string $date = NULL): Reminder {

		// Sans `build()` : il vérifierait le texte (1 à 250) et le jour dans la semaine, que le décor pose
		// lui-même, et que `testCreate()` éprouve par la route.
		$eReminder = new Reminder([
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure,
			'week' => $week,
			'date' => $date,
			'text' => $text,
		]);

		ReminderLib::create($eReminder);

		return $eReminder;

	}

	/**
	 * Une route de la Semaine postée sur l'écran de la semaine courante, puis rendue par sa vue : les
	 * gestes du pense-bête par `doReminder`, l'interrupteur par `doReminders`.
	 */
	private function callWeek(string $route, array $input): array {

		$action = $this->executePage('planning/taskWeek:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView($route === 'doReminders' ? 'doReminders' : 'doReminder', $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Les sélecteurs que les instructions de la réponse touchent, hors appels JavaScript.
	 */
	private function getSelectors(array $instructions): array {

		return array_values(array_unique(array_column(array_filter($instructions, fn(array $instruction) => in_array($instruction[0], ['qs', 'qsa'], TRUE)), 1)));

	}

	private function getInstruction(array $instructions, string $selector, string $method): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$methodCalled, $argumentsCalled]) {

					if($methodCalled === $method) {
						return $argumentsCalled[0];
					}

				}

			}

		}

		return NULL;

	}

	/**
	 * Les arguments de chaque appel JavaScript `$method` de la réponse (`$t->js()`).
	 */
	private function getJs(array $instructions, string $method): array {

		$calls = [];

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js') {

				foreach($instruction[1] as [$methodCalled, $argumentsCalled]) {

					if($methodCalled === $method) {
						$calls[] = $argumentsCalled[0];
					}

				}

			}

		}

		return $calls;

	}

	/**
	 * Les arguments du premier appel `$method` posé sur `$selector`, ou NULL.
	 */
	private function getCall(array $instructions, string $selector, string $method): ?array {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$methodCalled, $argumentsCalled]) {

					if($methodCalled === $method) {
						return $argumentsCalled;
					}

				}

			}

		}

		return NULL;

	}

	private function getStoredSwitch(\structure\Member $eMember): bool {

		return \structure\Member::model()
			->select(['viewPlanningReminders'])
			->whereId($eMember['id'])
			->get()['viewPlanningReminders'];

	}

	private function reconnect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
