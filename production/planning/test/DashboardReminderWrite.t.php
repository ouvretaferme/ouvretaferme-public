<?php
namespace planning;

/**
 * Les gestes du pense-bête de l'accueil (bloc `planning-reminder`), passés par les routes de
 * `planning/dashboard` comme le navigateur les poste : l'ajout, la correction du texte et du jour,
 * l'effacement, la coche et la décoche, avec la réponse que chacun rend, bornée à la ligne du rappel.
 *
 * Les gestes forgés s'éprouvent ici (rappel d'une autre structure, planning limité), comme le bloc que
 * le planning limité ne voit pas quand il est vide.
 *
 * Chaque rappel est monté dans la transaction de la méthode, à l'heure figée par `HarvestFixtureTest`
 * (jeudi de la semaine 39). Les lectures se bornent aux rappels que la méthode pose : le décor peut en
 * porter d'autres.
 *
 * Lancement : dev/test package=planning file=DashboardReminderWrite
 */
class DashboardReminderWriteTest extends HarvestFixtureTest {

	/**
	 * Le rappel ajouté prend la semaine du serveur, quelle que soit celle postée, et se pose devant la
	 * ligne d'ajout, qui se redessine. L'état posté est ignoré, et le bloc n'est jamais redessiné.
	 */
	public function testCreate(): void {

		$week = \Clock::week();

		[, $instructions, $action] = $this->callDashboard('doReminderCreate', [
			'text' => 'Appeler le garage',
			'week' => toWeek(week_date_starts($week).' +70 days'),
			'status' => Reminder::DONE,
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'le rappel est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eReminder = $this->getReminderByText($week, 'Appeler le garage');

		if($this->assertTrue($eReminder->notEmpty(), 'le rappel n\'est pas dans la semaine du serveur') === FALSE) {
			return;
		}

		$this->assertEquals(Reminder::TODO, $eReminder['status'], 'l\'état posté a été écrit');
		$this->assertEquals(NULL, $eReminder['date'], 'un rappel ajouté sans jour en porte un');

		$add = '#'.DashboardUi::getReminderAddId();

		$this->assertEquals([$add], $this->getSelectors($instructions), 'la réponse touche autre chose que la ligne d\'ajout');

		$inserted = $this->getCall($instructions, $add, 'insertAdjacentHtml');

		if($this->assertTrue($inserted !== NULL, 'le rappel neuf n\'est pas posé devant la ligne d\'ajout') === FALSE) {
			return;
		}

		$this->assertEquals('beforebegin', $inserted[0], 'le rappel neuf n\'est pas posé avant la ligne d\'ajout');
		$this->assertTrue(str_contains($inserted[1], 'id="'.DashboardUi::getReminderId($eReminder['id']).'"'), 'la ligne posée ne porte pas l\'identifiant du rappel');
		$this->assertTrue($this->getCall($instructions, $add, 'outerHtml') !== NULL, 'la ligne d\'ajout ne se redessine pas pour se refermer');

	}

	/**
	 * Un texte vide ou trop long est refusé par `build()` sans rien écrire.
	 */
	public function testCreateRefused(): void {

		$week = \Clock::week();
		$count = ReminderLib::getForWeek($this->eStructure, $week)['cReminder']->count();

		[, , $action] = $this->callDashboard('doReminderCreate', ['text' => '']);

		$this->assertEquals(['Reminder::text.check'], $this->getFails($action), 'un rappel sans texte n\'est pas refusé par le schéma');

		[, , $action] = $this->callDashboard('doReminderCreate', ['text' => str_repeat('a', Reminder::model()->getPropertyRange('text')[1] + 1)]);

		$this->assertEquals(['Reminder::text.check'], $this->getFails($action), 'un rappel trop long n\'est pas refusé par le schéma');
		$this->assertEquals($count, ReminderLib::getForWeek($this->eStructure, $week)['cReminder']->count(), 'un rappel refusé a été écrit');

	}

	/**
	 * Le texte corrigé sans changer de jour redessine la ligne à sa place, et elle seule. Le texte est
	 * encodé au rendu.
	 */
	public function testUpdateText(): void {

		$week = \Clock::week();
		$date = week_date_day($week, 2);

		$eReminder = $this->createReminder($week, 'Commander les plants', date: $date);

		[, $instructions, $action] = $this->callDashboard('doReminderUpdate', [
			'reminder' => $eReminder['id'],
			'text' => 'Commander <b>les plants</b>',
			'date' => $date,
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'la correction est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$eReminderStored = ReminderLib::getById($eReminder['id']);

		$this->assertEquals('Commander <b>les plants</b>', $eReminderStored['text'], 'le texte corrigé n\'est pas en base');
		$this->assertEquals($date, $eReminderStored['date'], 'le jour a bougé avec le texte');
		$this->assertEquals($week, $eReminderStored['week'], 'la semaine a bougé avec le texte');

		$line = '#'.DashboardUi::getReminderId($eReminder['id']);

		$this->assertEquals([$line], $this->getSelectors($instructions), 'la réponse touche autre chose que la ligne du rappel');

		$html = $this->getCall($instructions, $line, 'outerHtml')[0] ?? '';

		$this->assertTrue(str_contains($html, 'Commander &lt;b&gt;les plants&lt;/b&gt;'), 'le texte n\'est pas encodé au rendu');
		$this->assertFalse(str_contains($html, '<b>les plants</b>'), 'le texte est rendu en HTML');

	}

	/**
	 * Le formulaire d'une ligne poste le jour avec le texte parce que `build()` construit les deux : un
	 * jour absent du POST se lit comme un jour retiré. La ligne rendue porte donc son jour en champ caché.
	 */
	public function testUpdatePostsTheDate(): void {

		$week = \Clock::week();
		$date = week_date_day($week, 2);

		$eReminder = $this->createReminder($week, 'Commander les plants', date: $date);

		$html = new DashboardUi()->getReminder($this->eStructure, DashboardLib::getReminderPlace($this->eStructure, $eReminder)['reminder']);

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$this->assertEquals($date, $document->querySelector('input[type="hidden"][name="date"]')?->getAttribute('value'), 'la ligne ne poste pas le jour du rappel avec son texte');

		[, , $action] = $this->callDashboard('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Sans jour posté']);

		$this->assertTrue($action instanceof \ViewAction, 'la correction sans jour est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(NULL, ReminderLib::getById($eReminder['id'])['date'], 'un jour absent du POST ne se lit plus comme un jour retiré : le champ caché de la ligne est à revoir');

	}

	/**
	 * Le jour changé déplace la ligne : elle est retirée puis posée devant le rappel qui la suit dans
	 * l'ordre du bloc, ou devant la ligne d'ajout quand elle passe en dernier.
	 */
	public function testUpdateDateMoves(): void {

		$week = \Clock::week();

		$eReminderFirst = $this->createReminder($week, 'Premier, le mardi', date: week_date_day($week, 2));
		$eReminderMoved = $this->createReminder($week, 'Déplacé, le vendredi', date: week_date_day($week, 5));

		[, $instructions, $action] = $this->callDashboard('doReminderUpdate', [
			'reminder' => $eReminderMoved['id'],
			'text' => 'Déplacé, le lundi',
			'date' => week_date_day($week, 1),
		]);

		if($this->assertTrue($action instanceof \ViewAction, 'le changement de jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$ids = array_column(DashboardLib::load('planning-reminder', $this->eStructure)['reminders'], 'id');

		$this->assertTrue(array_search($eReminderMoved['id'], $ids) < array_search($eReminderFirst['id'], $ids), 'le rappel passé au lundi ne précède pas celui du mardi dans le bloc');

		// La liste se redessine entière : poser le rang devant son voisin le perdrait sur un accueil
		// ouvert avant qu'un autre membre ajoute ce voisin.
		$rows = '#'.DashboardUi::getReminderRowsId();

		$this->assertEquals([$rows], $this->getSelectors($instructions), 'le déplacement ne redessine pas la seule liste des rangs');

		$html = $this->getCall($instructions, $rows, 'outerHtml')[0] ?? '';

		$this->assertTrue(str_contains($html, DashboardUi::getReminderAddId()), 'la liste redessinée perd sa ligne d\'ajout');

		// Le jour retiré range le rappel après tous les rappels datés de la semaine.
		[, $instructions, $action] = $this->callDashboard('doReminderUpdate', [
			'reminder' => $eReminderMoved['id'],
			'text' => 'Déplacé, sans jour',
		]);

		$this->assertTrue($action instanceof \ViewAction, 'le jour retiré est refusé : '.implode(', ', $this->getFails($action)));

		$ids = array_column(DashboardLib::load('planning-reminder', $this->eStructure)['reminders'], 'id');

		$this->assertTrue(array_search($eReminderMoved['id'], $ids) > array_search($eReminderFirst['id'], $ids), 'le rappel sans jour ne suit pas celui du mardi dans le bloc');

		$this->assertTrue($this->getCall($instructions, $rows, 'outerHtml') !== NULL, 'le jour retiré ne redessine pas la liste des rangs');

	}

	/**
	 * Un jour d'une autre semaine que celle du rappel est refusé par `build()`, et rien ne s'écrit.
	 */
	public function testUpdateRefused(): void {

		$week = \Clock::week();

		$eReminder = $this->createReminder($week, 'Commander les plants');

		[, , $action] = $this->callDashboard('doReminderUpdate', [
			'reminder' => $eReminder['id'],
			'text' => 'Forgé',
			'date' => date('Y-m-d', strtotime(week_date_starts($week).' +21 days')),
		]);

		$this->assertEquals(['Reminder::date.dateInWeek'], $this->getFails($action), 'un jour hors de la semaine du rappel n\'est pas refusé par sa propriété');
		$this->assertEquals('Commander les plants', ReminderLib::getById($eReminder['id'])['text'], 'une correction refusée a été écrite');

	}

	/**
	 * L'effacement retire la ligne, et elle seule : le bloc reste, avec sa ligne d'ajout, même quand la
	 * liste se vide.
	 */
	public function testDeleteRemovesTheLine(): void {

		$eReminderDeleted = $this->createReminder(\Clock::week(), 'À effacer');

		[, $instructions, $action] = $this->callDashboard('doReminderDelete', ['reminder' => $eReminderDeleted['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'l\'effacement est refusé : '.implode(', ', $this->getFails($action)));
		$this->assertTrue(ReminderLib::getById($eReminderDeleted['id'])->empty(), 'le rappel effacé est encore en base');
		$this->assertEquals(['#'.DashboardUi::getReminderId($eReminderDeleted['id'])], $this->getSelectors($instructions), 'l\'effacement touche autre chose que la ligne du rappel');
		$this->assertEquals([['vanish' => TRUE]], $this->getCall($instructions, '#'.DashboardUi::getReminderId($eReminderDeleted['id']), 'remove'), 'la ligne effacée ne s\'efface pas à sa place');

	}

	/**
	 * La coche et la décoche, comme à la Semaine (FX) : la ligne se redessine à sa place, barrée avec sa
	 * décoche, ou rendue à faire avec sa coche. Un geste sur un rappel déjà dans l'état demandé redessine la
	 * ligne sans rien réécrire.
	 */
	public function testCheckAndUncheckRedrawTheLine(): void {

		$eReminder = $this->createReminder(toWeek(week_date_starts(\Clock::week()).' -7 days'), 'Resté à cocher');
		$line = '#'.DashboardUi::getReminderId($eReminder['id']);

		[, $instructions, $action] = $this->callDashboard('doReminderCheck', ['reminder' => $eReminder['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(Reminder::DONE, ReminderLib::getById($eReminder['id'])['status'], 'le rappel coché n\'est pas fait en base');
		$this->assertEquals([$line], $this->getSelectors($instructions), 'la coche touche autre chose que la ligne du rappel');
		$this->assertTrue(str_contains($this->getCall($instructions, $line, 'outerHtml')[0] ?? '', 'dashboard:doReminderUncheck'), 'la ligne cochée ne se redessine pas avec sa décoche');

		$doneAt = ReminderLib::getById($eReminder['id'])['doneAt'];

		$this->setClock(\Clock::format('Y-m-d').' 11:00:00');

		[, $instructions, $action] = $this->callDashboard('doReminderCheck', ['reminder' => $eReminder['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'une coche sur un rappel déjà fait lève au lieu de redessiner la ligne');
		$this->assertEquals($doneAt, ReminderLib::getById($eReminder['id'])['doneAt'], 'une coche sur un rappel déjà fait a réécrit l\'heure');
		$this->assertTrue($this->getCall($instructions, $line, 'outerHtml') !== NULL, 'une coche sur un rappel déjà fait ne redessine pas la ligne');

		[, $instructions, $action] = $this->callDashboard('doReminderUncheck', ['reminder' => $eReminder['id']]);

		$eReminderTodo = ReminderLib::getById($eReminder['id']);

		$this->assertTrue($action instanceof \ViewAction and $eReminderTodo['status'] === Reminder::TODO and $eReminderTodo['doneAt'] === NULL and $eReminderTodo['doneBy']->empty(), 'la décoche ne remet pas le rappel à faire, sans personne ni heure');
		$this->assertEquals([$line], $this->getSelectors($instructions), 'la décoche touche autre chose que la ligne du rappel');
		$this->assertTrue(str_contains($this->getCall($instructions, $line, 'outerHtml')[0] ?? '', 'dashboard:doReminderCheck'), 'la ligne décochée ne se redessine pas avec sa coche');

		[, $instructions, $action] = $this->callDashboard('doReminderUncheck', ['reminder' => $eReminder['id']]);

		$this->assertTrue($action instanceof \ViewAction and $this->getCall($instructions, $line, 'outerHtml') !== NULL, 'une décoche sur un rappel déjà à faire ne redessine pas la ligne');

	}

	/**
	 * Un rappel coché aujourd'hui reste dans le bloc : sa correction le redessine. Coché la veille, il a
	 * quitté le bloc, et la réponse retire la ligne au lieu de la redessiner. La correction s'écrit dans les
	 * deux cas.
	 */
	public function testUpdateOfACheckedReminder(): void {

		$eReminder = $this->createReminder(\Clock::week(), 'Coché ailleurs');
		$line = '#'.DashboardUi::getReminderId($eReminder['id']);

		ReminderLib::check($eReminder);

		[, $instructions, $action] = $this->callDashboard('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Corrigé après la coche']);

		if($this->assertTrue($action instanceof \ViewAction, 'la correction d\'un rappel fait est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('Corrigé après la coche', ReminderLib::getById($eReminder['id'])['text'], 'la correction d\'un rappel fait n\'est pas en base');
		$this->assertEquals([$line], $this->getSelectors($instructions), 'la réponse touche autre chose que la ligne du rappel');
		$this->assertTrue($this->getCall($instructions, $line, 'outerHtml') !== NULL, 'un rappel coché aujourd\'hui quitte le bloc après sa correction');

		$this->setClock(date('Y-m-d', strtotime(\Clock::date().' + 1 day')).' 08:00:00');

		[, $instructions] = $this->callDashboard('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Corrigé le lendemain']);

		$this->assertEquals('Corrigé le lendemain', ReminderLib::getById($eReminder['id'])['text'], 'la correction du lendemain n\'est pas en base');
		$this->assertTrue($this->getCall($instructions, $line, 'remove') !== NULL, 'un rappel coché la veille reste dans le bloc après sa correction');

	}

	/**
	 * Des gestes forgés sur le rappel d'une autre structure que celle de l'accueil : refus, sans rien écrire.
	 */
	public function testForeignStructureRefused(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eReminderForeign = $this->createReminder(\Clock::week(), 'Autre structure', $this->getStructureForeign());

		foreach(['doReminderCheck', 'doReminderUncheck', 'doReminderUpdate', 'doReminderDelete'] as $route) {

			[, , $action] = $this->callDashboard($route, ['reminder' => $eReminderForeign['id'], 'text' => 'Forgé']);

			$this->assertFalse($action instanceof \ViewAction, $route.' sur le rappel d\'une autre structure est accepté');

		}

		$eReminderStored = ReminderLib::getById($eReminderForeign['id']);

		$this->assertTrue($eReminderStored->notEmpty(), 'le rappel d\'une autre structure a été effacé');
		$this->assertEquals([Reminder::TODO, 'Autre structure'], [$eReminderStored['status'], $eReminderStored['text']], 'le rappel d\'une autre structure a été écrit');

	}

	/**
	 * Le planning limité lit le pense-bête de l'accueil sans geste : aucune route ne lui écrit, sa ligne
	 * ne porte aucun geste, le bloc n'a pas de ligne d'ajout, et vide il ne paraît pas.
	 */
	public function testPlanningLimited(): void {

		$week = \Clock::week();

		$eReminder = $this->createReminder($week, 'Pour le planning limité');

		$this->reconnect($this->getMemberPlanning());

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		if($this->assertDecorPresent($eStructure->canPlanningLimited() and $eStructure->canPlanning() === FALSE, 'Bruno au planning limité sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		foreach(['doReminderCheck', 'doReminderUncheck', 'doReminderUpdate', 'doReminderDelete'] as $route) {

			[, , $action] = $this->callDashboard($route, ['reminder' => $eReminder['id'], 'text' => 'Forgé']);

			$this->assertFalse($action instanceof \ViewAction, 'le planning limité passe '.$route);

		}

		[, , $action] = $this->callDashboard('doReminderCreate', ['text' => 'Ajout du planning limité']);

		$this->assertFalse($action instanceof \ViewAction, 'le planning limité ajoute un rappel');
		$this->assertTrue($this->getReminderByText($week, 'Ajout du planning limité')->empty(), 'le rappel du planning limité a été écrit');

		$eReminderStored = ReminderLib::getById($eReminder['id']);

		$this->assertTrue($eReminderStored->notEmpty(), 'le planning limité a effacé un rappel');
		$this->assertEquals([Reminder::TODO, 'Pour le planning limité'], [$eReminderStored['status'], $eReminderStored['text']], 'le planning limité a écrit un rappel');

		$values = DashboardLib::load('planning-reminder', $eStructure);

		if($this->assertTrue($values !== NULL, 'le planning limité ne voit pas le pense-bête qui porte un rappel') === FALSE) {
			return;
		}

		$html = new DashboardUi()->render('planning-reminder', $eStructure, $values);

		$this->assertFalse(str_contains($html, 'data-ajax'), 'le pense-bête du planning limité porte un geste');
		$this->assertFalse(str_contains($html, DashboardUi::getReminderAddId()), 'le pense-bête du planning limité porte la ligne d\'ajout');

		// Le bloc vide : tous les rappels à faire du décor sont cochés par le Lib, dans la transaction, et
		// l'accueil se relit le lendemain, quand les rappels cochés l'ont quitté.
		foreach($values['reminders'] as $reminder) {
			ReminderLib::check(ReminderLib::getById($reminder['id']));
		}

		$this->setClock(date('Y-m-d', strtotime(\Clock::date().' + 1 day')).' 08:00:00');

		$this->assertEquals(NULL, DashboardLib::load('planning-reminder', $eStructure), 'le pense-bête vide paraît au planning limité, qui ne peut rien y écrire');

	}

	private function createReminder(string $week, string $text, \structure\Structure $eStructure = new \structure\Structure(), ?string $date = NULL): Reminder {

		// Sans `build()` : il vérifierait le texte (1 à 250) et le jour dans la semaine, que le décor pose
		// lui-même, et que `testCreateRefused()` et `testUpdateRefused()` éprouvent par les routes.
		$eReminder = new Reminder([
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure,
			'week' => $week,
			'date' => $date,
			'text' => $text,
		]);

		ReminderLib::create($eReminder);

		return $eReminder;

	}

	private function getReminderByText(string $week, string $text): Reminder {

		foreach(ReminderLib::getForWeek($this->eStructure, $week)['cReminder'] as $eReminder) {

			if($eReminder['text'] === $text) {
				return $eReminder;
			}

		}

		return new Reminder();

	}

	/**
	 * Une route du pense-bête de l'accueil postée sur la structure du décor, puis rendue par sa vue.
	 */
	private function callDashboard(string $route, array $input): array {

		$action = $this->executePage('planning/dashboard:'.$route, $input + ['id' => $this->eStructure['id']], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doReminder', $action, 'dashboard');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Les sélecteurs que les instructions de la réponse touchent, hors appels JavaScript.
	 */
	private function getSelectors(array $instructions): array {

		return array_values(array_unique(array_column(array_filter($instructions, fn(array $instruction) => in_array($instruction[0], ['qs', 'qsa'], TRUE)), 1)));

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

	private function reconnect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

}
?>
