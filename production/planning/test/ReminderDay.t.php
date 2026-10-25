<?php
namespace planning;

/**
 * Le pense-bête sur l'écran Jour (CC) : chaque colonne porte en tête les rappels de son jour, « Non défini »
 * ceux sans jour et les restés des semaines passées. Les gestes sont ceux de la Semaine, coche, décoche,
 * correction sur place et effacement, par les routes du Jour (`taskDay:doReminder*`), qui ne redessinent
 * que les rappels des colonnes touchées. Un rappel ne s'ajoute pas depuis le Jour.
 *
 * Chaque rappel est monté dans la transaction de la méthode, à l'heure figée par `HarvestFixtureTest`
 * (jeudi de la semaine 39). L'écran après chaque réponse se compare au rechargement dans `FingerprintTest`.
 *
 * Lancement : dev/test package=planning file=ReminderDay
 */
class ReminderDayTest extends HarvestFixtureTest {

	/**
	 * Chaque colonne a sa collection, vide sans rappel : un rappel daté va dans sa date, un rappel sans jour
	 * et un resté dans « Non défini », le resté après ceux de la semaine.
	 */
	public function testByDay(): void {

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');

		$eReminderMonday = $this->createReminder($week, 'Lundi', date: week_date_day($week, 1));
		$eReminderBefore = $this->createReminder($weekBefore, 'Resté', date: week_date_day($weekBefore, 2));
		$eReminderNone = $this->createReminder($week, 'Sans jour');

		$reminders = ReminderLib::getByDay($this->eStructure, $week);

		$this->assertEquals(TaskDayLib::getColumnKeys($week), array_keys($reminders), 'le pense-bête du Jour n\'a pas une entrée par colonne');
		$this->assertEquals([$eReminderMonday['id']], $reminders[week_date_day($week, 1)]->getIds(), 'le rappel daté n\'est pas dans sa colonne');
		$this->assertEquals([$eReminderNone['id'], $eReminderBefore['id']], $reminders[TaskDayLib::NONE]->getIds(), '« Non défini » ne porte pas le rappel sans jour puis le resté');
		$this->assertTrue($reminders[week_date_day($week, 2)]->empty(), 'un jour sans rappel en porte un');

		$this->assertEquals(TaskDayLib::NONE, ReminderLib::getDayKey($eReminderBefore, $week), 'un resté daté dans sa semaine n\'est pas sans jour vu de la suivante');
		$this->assertEquals(week_date_day($weekBefore, 2), ReminderLib::getDayKey($eReminderBefore, $weekBefore), 'un rappel daté n\'est pas dans sa colonne vu de sa semaine');

	}

	/**
	 * Éteint, le Jour n'a pas de pense-bête. Allumé, chaque colonne a son conteneur, même vide, et seule une
	 * colonne avec rappel porte la feuille, au-dessus des cartes. La pastille du jour se tait, la colonne le
	 * dit ; celle d'un resté garde sa semaine. Les gestes visent les routes du Jour, et aucun « + » ne s'y
	 * trouve.
	 */
	public function testRender(): void {

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');
		$monday = week_date_day($week, 1);

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $monday);

		$eReminderMonday = $this->createReminder($week, 'Lundi', date: $monday);
		$eReminderBefore = $this->createReminder($weekBefore, 'Resté');

		[$document] = $this->renderTaskDay();

		$this->assertEquals(0, $document->querySelectorAll('[id^="planning-day-reminders-"]')->length, 'le pense-bête paraît au Jour sans avoir été allumé');

		$this->switchOn();

		[$document] = $this->renderTaskDay();

		$this->assertEquals(count(TaskDayLib::getColumnKeys($week)), $document->querySelectorAll('.planning-day-body > [id^="planning-day-reminders-"]:first-child')->length, 'chaque colonne n\'ouvre pas son corps par le conteneur du pense-bête');

		$container = $document->getElementById('planning-day-reminders-'.$monday);

		if($this->assertTrue($container?->querySelector('.util-note.planning-reminder-day #planning-reminder-'.$eReminderMonday['id']) !== NULL, 'le rappel du lundi n\'est pas sur sa feuille dans la colonne du lundi') === FALSE) {
			return;
		}

		$this->assertTrue($container->nextElementSibling !== NULL and $container->nextElementSibling->querySelector('.planning-task') !== NULL, 'les cartes du lundi ne suivent pas son pense-bête');
		$this->assertEquals('', trim($document->getElementById('planning-day-reminders-'.week_date_day($week, 2))->innerHTML), 'un jour sans rappel montre une feuille');
		$this->assertEquals(0, $container->querySelectorAll('.badge')->length, 'le rappel du lundi redit son jour dans la colonne du lundi');

		$lineBefore = $document->querySelector('#planning-day-reminders-'.TaskDayLib::NONE.' #planning-reminder-'.$eReminderBefore['id']);

		$this->assertTrue($lineBefore !== NULL and str_contains($lineBefore->querySelector('.badge')?->textContent ?? '', (string)week_number($weekBefore)), 'le resté n\'est pas dans « Non défini » avec sa semaine');

		$this->assertTrue($container->querySelector('[data-ajax="/planning/taskDay:doReminderCheck"]') !== NULL, 'la case ne poste pas au Jour');
		$this->assertTrue(str_contains($container->querySelector('form')?->getAttribute('data-ajax-form') ?? '', 'taskDay:doReminderUpdate'), 'la correction sur place ne poste pas au Jour');
		$this->assertEquals(0, $document->querySelectorAll('#planning-day a[href*="reminderCreate"]')->length, 'le Jour propose d\'ajouter un rappel');

	}

	/**
	 * Un écran sans intervention tait le corps de ses colonnes, mais pas le pense-bête : un rappel n'est pas
	 * une intervention.
	 */
	public function testEmptyScreen(): void {

		$week = toWeek(week_date_starts(\Clock::week()).' +70 days');

		$this->switchOn();

		$eReminder = $this->createReminder($week, 'Semaine vide', date: week_date_day($week, 3));

		[$document, $data] = $this->renderTaskDay(week: $week);

		$this->assertTrue($data->empty, 'la semaine du décor n\'est pas vide');
		$this->assertTrue($document->querySelector('#planning-day-reminders-'.week_date_day($week, 3).' #planning-reminder-'.$eReminder['id']) !== NULL, 'le pense-bête se tait sur un écran sans intervention');

	}

	/**
	 * La coche et la décoche ne redessinent que les rappels de la colonne du rappel, et l'éclairent. Un
	 * resté coché reste dans « Non défini », barré, la semaine de sa coche (FX). Un rappel déjà coché
	 * ailleurs répond sans rien écrire ni lever.
	 */
	public function testCheck(): void {

		$this->switchOn();

		$week = \Clock::week();
		$monday = week_date_day($week, 1);

		$eReminder = $this->createReminder($week, 'À cocher', date: $monday);

		[, $instructions, $action] = $this->callReminder('doReminderCheck', ['reminder' => $eReminder['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(Reminder::DONE, ReminderLib::getById($eReminder['id'])['status'], 'le rappel coché n\'est pas fait');
		$this->assertEquals(['#planning-day-reminders-'.$monday], $this->getSelectors($instructions), 'la coche ne redessine pas seulement les rappels du lundi');
		$this->assertTrue(str_contains($this->getInstruction($instructions, '#planning-day-reminders-'.$monday), 'planning-reminder-done'), 'la ligne redessinée ne montre pas le rappel coché');
		$this->assertEquals([['#planning-reminder-'.$eReminder['id']]], $this->getJs($instructions, 'highlight'), 'le rappel coché ne s\'éclaire pas');

		$doneAt = ReminderLib::getById($eReminder['id'])['doneAt'];

		[, $instructions, $action] = $this->callReminder('doReminderCheck', ['reminder' => $eReminder['id']]);

		$this->assertTrue($action instanceof \ViewAction, 'un rappel déjà coché ailleurs lève');
		$this->assertEquals($doneAt, ReminderLib::getById($eReminder['id'])['doneAt'], 'un rappel déjà coché est réécrit');

		[, $instructions] = $this->callReminder('doReminderUncheck', ['reminder' => $eReminder['id']]);

		$this->assertEquals(Reminder::TODO, ReminderLib::getById($eReminder['id'])['status'], 'la décoche ne remet pas le rappel à faire');
		$this->assertEquals(['#planning-day-reminders-'.$monday], $this->getSelectors($instructions), 'la décoche ne redessine pas seulement les rappels du lundi');

		$eReminderBefore = $this->createReminder(toWeek(week_date_starts($week).' -7 days'), 'Resté à cocher');

		[, $instructions] = $this->callReminder('doReminderCheck', ['reminder' => $eReminderBefore['id']]);

		$html = $this->getInstruction($instructions, '#planning-day-reminders-'.TaskDayLib::NONE);

		$this->assertTrue($html !== NULL and str_contains($html, 'planning-reminder-'.$eReminderBefore['id']), 'le resté coché quitte « Non défini » avant la fin de la semaine de sa coche');
		$this->assertEquals([['#planning-reminder-'.$eReminderBefore['id']]], $this->getJs($instructions, 'highlight'), 'le resté coché ne s\'éclaire pas');

	}

	/**
	 * Un rappel qui change de jour quitte sa colonne pour l'autre : les deux se redessinent, et la ligne
	 * s'éclaire à son arrivée. Sans changement de jour, seule sa colonne se redessine.
	 */
	public function testUpdate(): void {

		$this->switchOn();

		$week = \Clock::week();
		$monday = week_date_day($week, 1);
		$thursday = week_date_day($week, 4);

		$eReminder = $this->createReminder($week, 'Appeler', date: $monday);

		[, $instructions, $action] = $this->callReminder('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Appeler le garage', 'date' => $thursday]);

		if($this->assertTrue($action instanceof \ViewAction, 'la modification est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(['#planning-day-reminders-'.$monday, '#planning-day-reminders-'.$thursday], $this->getSelectors($instructions), 'le changement de jour ne redessine pas les deux colonnes');
		$this->assertFalse(str_contains($this->getInstruction($instructions, '#planning-day-reminders-'.$monday), 'planning-reminder-'.$eReminder['id']), 'le rappel reste dans la colonne qu\'il quitte');
		$this->assertTrue(str_contains($this->getInstruction($instructions, '#planning-day-reminders-'.$thursday), 'Appeler le garage'), 'le rappel n\'arrive pas dans sa nouvelle colonne');
		$this->assertEquals([['#planning-reminder-'.$eReminder['id']]], $this->getJs($instructions, 'highlight'), 'le rappel déplacé ne s\'éclaire pas');

		[, $instructions] = $this->callReminder('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Appeler', 'date' => $thursday]);

		$this->assertEquals(['#planning-day-reminders-'.$thursday], $this->getSelectors($instructions), 'une correction sans changement de jour touche une autre colonne');

		[, , $action] = $this->callReminder('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Ailleurs', 'date' => week_date_day($week, 8)]);

		$this->assertEquals(['Reminder::date.dateInWeek'], $this->getFails($action), 'un jour hors de la semaine du rappel n\'est pas refusé');

	}

	/**
	 * Le Jour d'une semaine passée suit la Semaine (FX) : seul le rappel barré dans la semaine de sa coche
	 * s'y décoche, et aucun ne s'y corrige sur place. La correction forgée d'un rappel coché dans une
	 * semaine passée est refusée, sans rien écrire.
	 */
	public function testPastWeek(): void {

		$this->switchOn();

		$now = \Clock::datetime();
		$weekBefore = toWeek(week_date_starts(\Clock::week()).' -7 days');

		$this->createReminder($weekBefore, 'À faire', date: week_date_day($weekBefore, 3));
		$eReminderChecked = $this->createReminder($weekBefore, 'Coché dans sa semaine');

		$this->setClock(week_date_day($weekBefore, 2).' 09:00:00');
		ReminderLib::check($eReminderChecked);
		$this->setClock($now);

		[$document] = $this->renderTaskDay(week: $weekBefore);

		$links = [];

		foreach($document->querySelectorAll('[data-ajax*="doReminderCheck"], [data-ajax*="doReminderUncheck"]') as $link) {
			$links[] = $link->closest('.planning-reminder')->getAttribute('id').' '.$link->getAttribute('data-ajax');
		}

		$this->assertEquals(['planning-reminder-'.$eReminderChecked['id'].' /planning/taskDay:doReminderUncheck'], $links, 'le Jour d\'une semaine passée ne laisse pas décocher que le rappel barré dans sa semaine');
		$this->assertEquals(0, $document->querySelectorAll('[id^="planning-reminder-edit-"]')->length, 'le Jour d\'une semaine passée corrige un rappel sur place');

		[, , $action] = $this->callReminder('doReminderUpdate', ['reminder' => $eReminderChecked['id'], 'text' => 'Corrigé', 'week' => $weekBefore]);

		$this->assertEquals(['planning\\Reminder::updatePast'], $this->getFails($action), 'un rappel coché dans une semaine passée se corrige au Jour');
		$this->assertEquals('Coché dans sa semaine', ReminderLib::getById($eReminderChecked['id'])['text'], 'la correction refusée a été écrite');

	}

	/**
	 * L'effacement redessine les rappels de la colonne qu'il quitte, sans rien éclairer.
	 */
	public function testDelete(): void {

		$this->switchOn();

		$eReminder = $this->createReminder(\Clock::week(), 'Sans jour');

		[, $instructions, $action] = $this->callReminder('doReminderDelete', ['reminder' => $eReminder['id']]);

		if($this->assertTrue($action instanceof \ViewAction, 'l\'effacement est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertTrue(ReminderLib::getById($eReminder['id'])->empty(), 'le rappel effacé est encore en base');
		$this->assertEquals(['#planning-day-reminders-'.TaskDayLib::NONE], $this->getSelectors($instructions), 'l\'effacement ne redessine pas seulement « Non défini »');
		$this->assertEquals('', trim(strip_tags((string)$this->getInstruction($instructions, '#planning-day-reminders-'.TaskDayLib::NONE))), '« Non défini » vidé garde une feuille');
		$this->assertEquals([], $this->getJs($instructions, 'highlight'), 'l\'effacement éclaire un rappel');

	}

	/**
	 * Des gestes forgés : un rappel d'une autre structure que celle de l'écran est refusé sans rien écrire.
	 * Le pense-bête éteint ailleurs, un geste répond sans rien redessiner.
	 */
	public function testForged(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$this->switchOn();

		$eReminderForeign = $this->createReminder(\Clock::week(), 'Autre structure', $this->getStructureForeign());

		foreach(['doReminderCheck', 'doReminderUncheck', 'doReminderUpdate', 'doReminderDelete'] as $route) {
			[, , $action] = $this->callReminder($route, ['reminder' => $eReminderForeign['id'], 'text' => 'Forgé']);
			$this->assertFalse($action instanceof \ViewAction, $route.' accepte le rappel d\'une autre structure');
		}

		$eReminderForeignAfter = ReminderLib::getById($eReminderForeign['id']);

		$this->assertTrue($eReminderForeignAfter->notEmpty() and $eReminderForeignAfter['text'] === 'Autre structure' and $eReminderForeignAfter['status'] === Reminder::TODO, 'le rappel d\'une autre structure a été écrit');

		$eReminder = $this->createReminder(\Clock::week(), 'Pense-bête éteint');

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), FALSE);

		[, $instructions, $action] = $this->callReminder('doReminderCheck', ['reminder' => $eReminder['id']]);

		$this->assertTrue($action instanceof \ViewAction and ReminderLib::getById($eReminder['id'])['status'] === Reminder::DONE, 'la coche pense-bête éteint est refusée');
		$this->assertEquals([], $this->getSelectors($instructions), 'le pense-bête éteint est redessiné');

	}

	/**
	 * Un geste de carte redessine sa colonne entière : le pense-bête du jour y reste.
	 */
	public function testColumnKeepsReminders(): void {

		$this->switchOn();

		$week = \Clock::week();
		$monday = week_date_day($week, 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $monday);
		$eReminder = $this->createReminder($week, 'Lundi', date: $monday);

		[, $instructions, $action] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => $monday]);

		if($this->assertTrue($action instanceof \ViewAction, 'la coche de la carte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$column = $this->getInstruction($instructions, '#planning-day-'.$monday);

		$this->assertTrue($column !== NULL and str_contains($column, 'id="planning-reminder-'.$eReminder['id'].'"'), 'la colonne redessinée perd son pense-bête');

	}

	private function switchOn(): void {

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

	}

	private function createReminder(string $week, string $text, \structure\Structure $eStructure = new \structure\Structure(), ?string $date = NULL): Reminder {

		// Sans `build()` : il vérifierait le texte (1 à 250) et le jour dans la semaine, que le décor pose
		// lui-même, et que `ReminderWeekTest::testCreate()` éprouve par la route.
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
	 * Un geste du pense-bête posté au Jour de la semaine courante, puis rendu par `doReminder`.
	 */
	private function callReminder(string $route, array $input): array {

		$action = $this->executePage('planning/taskDay:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doReminder', $action, 'taskDay');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Les sélecteurs que les instructions de la réponse touchent, hors appels JavaScript.
	 */
	private function getSelectors(array $instructions): array {

		return array_values(array_unique(array_column(array_filter($instructions, fn(array $instruction) => in_array($instruction[0], ['qs', 'qsa'], TRUE)), 1)));

	}

	/**
	 * Le HTML que la réponse pose en `outerHtml` sur `$selector`, ou NULL.
	 */
	private function getInstruction(array $instructions, string $selector): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {

				foreach($instruction[2] as [$methodCalled, $argumentsCalled]) {

					if($methodCalled === 'outerHtml') {
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

}
?>
