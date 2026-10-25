<?php
namespace planning;

/**
 * Le bloc `planning-reminder` de l'accueil : les rappels du pense-bête restés à faire et ceux cochés
 * aujourd'hui, dans l'ordre de l'écran de la Semaine, et la seule ligne d'ajout quand il n'y en a pas. Les gestes qui écrivent sont
 * dans `DashboardReminderWriteTest`.
 *
 * L'heure est figée sur une semaine de 2010, antérieure à tout rappel du décor : le bloc ne lit alors
 * que ce que le test pose.
 *
 * Isolation : la transaction de `SeriesFixtureAiTest`, annulée en tearDown().
 *
 * Lancement : dev/test package=planning file=DashboardReminder
 */
class DashboardReminderTest extends \series\SeriesFixtureAiTest {

	const string THURSDAY = '2010-06-17 10:00:00';

	/**
	 * Le rappel resté d'une semaine passée vient en tête, puis ceux de la semaine, le daté avant celui
	 * sans jour même créé après lui. Un rappel coché aujourd'hui garde sa place, barré.
	 */
	public function testRemindersFollowTheWeekOrder(): void {

		$this->setClock(self::THURSDAY);

		$week = \Clock::week();
		$previous = toWeek(date('Y-m-d', strtotime(\Clock::date().' - 14 days')));

		$this->createReminder($week, NULL, 'Commander les plants');
		$this->createReminder($week, week_date_day($week, 2), 'Appeler le maraîcher voisin');
		$this->createReminder($previous, NULL, 'Réparer la bâche');

		ReminderLib::check($this->createReminder($week, NULL, 'Déjà fait'));

		$values = DashboardLib::load('planning-reminder', $this->eStructure);

		$this->assertEquals(
			[['Réparer la bâche', TRUE, FALSE], ['Appeler le maraîcher voisin', FALSE, FALSE], ['Commander les plants', FALSE, FALSE], ['Déjà fait', FALSE, TRUE]],
			array_map(fn(array $reminder) => [$reminder['text'], $reminder['before'], $reminder['done']], $values['reminders']),
			'le resté d\'abord, puis le rappel daté de la semaine, puis ceux sans jour, le rappel coché aujourd\'hui barré à sa place'
		);

	}

	/**
	 * Un rappel coché depuis l'accueil, resté ou de la semaine, y reste barré jusqu'à la fin du jour de sa
	 * coche, et disparaît le lendemain (FX, décidé le 16/10). Un rappel d'une semaine à venir
	 * coché aujourd'hui n'y paraît pas, faute d'y avoir été. Chaque ligne porte la case de son rappel, qui
	 * décoche un rappel coché.
	 */
	public function testCheckedStaysUntilTheEndOfTheDay(): void {

		$this->setClock(self::THURSDAY);

		$week = \Clock::week();
		$previous = toWeek(date('Y-m-d', strtotime(\Clock::date().' - 14 days')));
		$next = toWeek(date('Y-m-d', strtotime(\Clock::date().' + 14 days')));

		$eReminderBefore = $this->createReminder($previous, NULL, 'Réparer la bâche');
		$eReminder = $this->createReminder($week, NULL, 'Commander les plants');
		ReminderLib::check($this->createReminder($next, NULL, 'Prévu dans deux semaines, déjà fait'));

		if($this->assertDecorPresent($this->eStructure->canPlanning(), 'le compte du décor écrit dans le planning') === FALSE) {
			return;
		}

		$html = new DashboardUi()->render('planning-reminder', $this->eStructure, DashboardLib::load('planning-reminder', $this->eStructure));

		$this->assertTrue(str_contains($html, 'post-reminder="'.$eReminder['id'].'"'), 'le rappel porte sa case');
		$this->assertTrue(str_contains($html, 'data-ajax="/planning/dashboard:doReminderCheck"'), 'la case ne poste pas à la route de l\'accueil');
		$this->assertTrue(str_contains($html, 'id="'.DashboardUi::getReminderId($eReminderBefore['id']).'"'), 'la ligne porte l\'identifiant que la réponse redessine');

		ReminderLib::check($eReminderBefore);
		ReminderLib::check($eReminder);

		$values = DashboardLib::load('planning-reminder', $this->eStructure);

		$this->assertEquals([[$eReminderBefore['id'], TRUE], [$eReminder['id'], TRUE]], array_map(fn(array $reminder) => [$reminder['id'], $reminder['done']], $values['reminders']), 'les rappels cochés aujourd\'hui ne restent pas barrés, ou le rappel à venir coché paraît');

		$html = new DashboardUi()->render('planning-reminder', $this->eStructure, $values);

		$this->assertTrue(str_contains($html, 'data-ajax="/planning/dashboard:doReminderUncheck"'), 'un rappel coché ne se décoche pas depuis l\'accueil');

		$this->setClock(\Clock::date().' 23:59:59');

		$this->assertEquals(2, count(DashboardLib::load('planning-reminder', $this->eStructure)['reminders']), 'un rappel coché part avant la fin du jour');

		$this->setClock(date('Y-m-d', strtotime(\Clock::date().' + 1 day')).' 00:00:00');

		$this->assertEquals(['reminders' => []], DashboardLib::load('planning-reminder', $this->eStructure), 'un rappel coché la veille paraît encore, ou la liste vide n\'est pas gardée à qui écrit au planning');

	}

	/**
	 * Sans rappel, qui écrit au planning garde le bloc, réduit à sa ligne d'ajout, même avec un rappel coché
	 * la veille. Le planning limité, lui, ne le voit pas (`DashboardReminderWriteTest`).
	 */
	public function testNothingToDoKeepsTheAddLine(): void {

		$this->setClock(self::THURSDAY);

		if($this->assertDecorPresent($this->eStructure->canPlanning(), 'le compte du décor écrit dans le planning') === FALSE) {
			return;
		}

		$this->assertEquals(['reminders' => []], DashboardLib::load('planning-reminder', $this->eStructure), 'aucun rappel');

		ReminderLib::check($this->createReminder(\Clock::week(), NULL, 'Fait la veille'));

		$this->setClock(date('Y-m-d', strtotime(\Clock::date().' + 1 day')).' 08:00:00');

		$values = DashboardLib::load('planning-reminder', $this->eStructure);

		$this->assertEquals(['reminders' => []], $values, 'un rappel coché la veille seulement');

		$html = new DashboardUi()->render('planning-reminder', $this->eStructure, $values);

		$this->assertTrue(str_contains($html, 'id="'.DashboardUi::getReminderAddId().'"'), 'le bloc vide n\'a pas sa ligne d\'ajout');
		$this->assertTrue(str_contains($html, '/planning/dashboard:doReminderCreate'), 'la ligne d\'ajout ne poste pas à la route de l\'accueil');

	}

	private function createReminder(string $week, ?string $date, string $text): Reminder {

		// Sans `build()` : il vérifierait le texte (1 à 250) et que le jour tombe dans la semaine, deux
		// choses que le décor pose lui-même, et que `ReminderTest::testBuildDate()` éprouve.
		$eReminder = new Reminder([
			'structure' => $this->eStructure,
			'week' => $week,
			'date' => $date,
			'text' => $text,
		]);

		ReminderLib::create($eReminder);

		return $eReminder;

	}

}
?>
