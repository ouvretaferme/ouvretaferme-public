<?php
namespace planning;

/**
 * La semaine en champs du Temps de travail d'une personne (planche rh-heures-pistes, piste B, 19/10) : la
 * journée de chaque jour se saisit par la tuile de son jour, dans le sélecteur de durée habituel. Le geste est
 * éprouvé tel que le client l'envoie : les `data-*` de la tuile rendue et le jour choisi de la fiche
 * (`data-selected`), postés à `timeDay:doWorkingTime`, puis la fiche relue.
 *
 * Le décor est celui de `HarvestFixtureTest` : le jeudi de la semaine 39, Alice seule présente, qui a le
 * planning complet.
 *
 * Lancement : dev/test package=planning file=TimeDayEntryAi
 */
class TimeDayEntryAiTest extends HarvestFixtureTest {

	/**
	 * Le lundi saisi depuis la fiche ouverte sur jeudi : la journée est écrite, la réponse ne remplace que le lundi
	 * et la somme de la semaine, puisque le jour choisi reste jeudi, et le lundi qu'elle rend est celui d'une fiche
	 * rechargée, que la saisie suivante relit.
	 */
	public function testPost(): void {

		$monday = week_date_day(\Clock::week(), 1);

		[$document] = $this->renderTimeDay();

		$input = $this->getPost($document, $monday);

		if($this->assertTrue($input !== NULL, 'le lundi n\'a pas de tuile') === FALSE) {
			return;
		}

		$this->assertEquals(['id', 'week', 'date', 'user', 'selected'], array_keys($input), 'le lundi ne poste pas la structure, la semaine, le jour, la personne et le jour choisi');
		$this->assertEquals(\Clock::date(), $input['selected'], 'la fiche ne réémet pas jeudi, le jour choisi');

		$action = $this->executePage('planning/timeDay:doWorkingTime', $input + ['time' => '7.5'], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la journée du lundi est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$data, $template] = $this->runView('doWorkingTime', $action, 'timeDay');
		$instructions = $template->formatInstructions();

		$this->assertEquals(7.5, $this->getWorkingTime($monday), 'la journée du lundi n\'est pas écrite');
		$this->assertEquals(['#planning-time-day-'.$monday, '#planning-time-week-total'], $this->getSelectors($instructions), 'la saisie d\'un autre jour que le jour choisi ne répond pas par ce jour et la somme de la semaine');

		[$documentReload, $dataReload] = $this->renderTimeDay();

		// Le champ du sélecteur de durée porte un identifiant tiré à chaque rendu (`FormUi`), écarté de la comparaison.
		$this->assertEquals(
			preg_replace('/field-[0-9a-f]+/', 'field-', new TimeDayUi()->getDay($dataReload->eStructure, $dataReload->week, $monday, FALSE, $dataReload->eUserTime, $dataReload->timeDay['days'][$monday], $dataReload->timeDay['cWorkingTime'][$monday], $dataReload->entry)),
			preg_replace('/field-[0-9a-f]+/', 'field-', $this->getHtml($instructions, '#planning-time-day-'.$monday)),
			'le lundi de la réponse n\'est pas celui d\'une fiche rechargée'
		);
		$this->assertEquals('7.5', $documentReload->querySelector('#planning-time-day-'.$monday.' .planning-time-working[data-date]')?->getAttribute('data-sent'), 'le lundi relu ne porte pas sa journée enregistrée');
		$this->assertFalse($dataReload->entry['warnings'][$monday], 'le lundi saisi reste à saisir');
		$this->assertEquals($data->entry, $dataReload->entry, 'la somme de la réponse n\'est pas celle d\'une fiche rechargée');

	}

	/**
	 * Le jour choisi que réémet la fiche décide de ce qui se redessine sous la rangée : le jour saisi, s'il est le
	 * jour choisi, redessine aussi le temps par intervention, dont la barre repère la journée ; la semaine choisie
	 * (vide) ne redessine que la rangée. Un jour hors de la semaine est refusé.
	 */
	public function testSelected(): void {

		$monday = week_date_day(\Clock::week(), 1);

		[, $instructions] = $this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '6', 'selected' => $monday]);
		$this->assertEquals(['#planning-time-day-'.$monday, '#planning-time-week-total', '#planning-time-tasks-day'], $this->getSelectors($instructions), 'le jour choisi saisi ne redessine pas son temps par intervention');

		[$data, $instructions] = $this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '5', 'selected' => '']);
		$this->assertEquals(NULL, $data->dateSelected, 'la semaine choisie se lit comme un jour');
		$this->assertEquals(['#planning-time-day-'.$monday, '#planning-time-week-total'], $this->getSelectors($instructions), 'la saisie sur la semaine choisie redessine sous la rangée');

		[, , $action] = $this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '4', 'selected' => date('Y-m-d', strtotime($monday.' + 1 WEEK'))]);
		$this->assertTrue($action instanceof \NotExpectedAction, 'un jour choisi hors de la semaine est accepté');
		$this->assertEquals(5.0, $this->getWorkingTime($monday), 'un jour choisi hors de la semaine écrit la journée');

	}

	/**
	 * Ce que la page prépare pour la somme : les jours à saisir sont ceux que la semaine compte comme tels
	 * (`TimeWeekLib`, `unfilled`), un jour saisi en sort ; sans contrat lu, ni horaire ni jauge.
	 */
	public function testEntry(): void {

		$monday = week_date_day(\Clock::week(), 1);

		[, $data] = $this->renderTimeDay();

		$unfilled = array_keys(array_filter($data->timeDay['days'], fn(array $day) => $day['unfilled']));

		$this->assertEquals($unfilled, array_keys(array_filter($data->entry['warnings'])), 'les jours à saisir ne sont pas ceux de la semaine');
		$this->assertEquals(count($unfilled), $data->entry['missing'], 'le nombre de jours à saisir n\'est pas celui des jours en alerte');
		$this->assertTrue(in_array($monday, $unfilled, TRUE), 'décor attendu : le lundi est à saisir');
		$this->assertEquals([NULL, NULL, NULL], [$data->entry['hours'], $data->entry['done'], $data->entry['todo']], 'une semaine sans contrat lu a un horaire ou une jauge');

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);

		[, $data] = $this->renderTimeDay();

		$this->assertEquals(count($unfilled) - 1, $data->entry['missing'], 'le lundi saisi compte encore parmi les jours à saisir');

	}

	/**
	 * Un contrat à la semaine de 35 h du lundi au vendredi, lu par la page : les jours à saisir se bornent à ses
	 * jours, l'horaire est le sien, et la jauge compte la semaine saisie puis les jours à saisir à 7 h chacun. Un
	 * contrat annualisé n'a ni horaire ni jauge.
	 */
	public function testContract(): void {

		$monday = week_date_day(\Clock::week(), 1);

		$this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '7']);

		[, $data] = $this->renderTimeDay();

		$eContract = new \hr\Contract([
			'weeklyHours' => 35.0,
			'hoursPeriod' => \hr\Contract::WEEK,
			'days' => new \Set(\hr\Contract::MONDAY | \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY | \hr\Contract::THURSDAY | \hr\Contract::FRIDAY),
			'hasVariableDays' => FALSE,
		]);

		$entry = TimeDayLib::getEntry($data->timeDay['days'], TimeDayLib::getContractDays($eContract), $eContract);

		$expected = [];

		foreach($data->timeDay['days'] as $date => $day) {
			$expected[$date] = ($day['unfilled'] and (int)date('N', strtotime($date)) <= 5);
		}

		$this->assertEquals([1, 2, 3, 4, 5], TimeDayLib::getContractDays($eContract), 'les jours du contrat ne sont pas du lundi au vendredi');
		$this->assertEquals($expected, $entry['warnings'], 'les jours à saisir ne se bornent pas aux jours du contrat');
		$this->assertEquals(35.0, $entry['hours'], 'l\'horaire n\'est pas celui du contrat');
		$this->assertEquals(20.0, $entry['done'], 'les 7 h saisies ne font pas le cinquième de la jauge');
		$this->assertEquals(round(100 * $entry['missing'] * 7 / 35, 2), $entry['todo'], 'chaque jour à saisir ne compte pas 7 h dans la jauge');

		$eContract['hasVariableDays'] = TRUE;

		$this->assertEquals([], TimeDayLib::getContractDays($eContract), 'des jours variables bornent les jours à saisir');

		$eContract['hasVariableDays'] = FALSE;
		$eContract['hoursPeriod'] = \hr\Contract::YEAR;

		$entry = TimeDayLib::getEntry($data->timeDay['days'], TimeDayLib::getContractDays($eContract), $eContract);

		$this->assertEquals([NULL, NULL, NULL], [$entry['hours'], $entry['done'], $entry['todo']], 'un contrat annualisé a un horaire ou une jauge à la semaine');

	}

	/**
	 * Sous horaires, une journée se dit par son arrivée, son départ et sa pause : la rangée de la semaine n'est pas rendue, et
	 * le jour choisi garde sa carte au-dessus du temps par intervention.
	 */
	public function testTimeRange(): void {

		$eConfiguration = \structure\StructureLib::getById($this->eStructure['id'])->conf();
		$timeRange = $eConfiguration['timeRange'];

		$eConfiguration['timeRange'] = TRUE;

		try {

			[$document, $data] = $this->renderTimeDay();

			if($this->assertDecorPresent($data->eStructure->hasTimeRange(), 'la structure est à horaires') === FALSE) {
				return;
			}

			$this->assertEquals(0, $document->querySelectorAll('.util-days-entry')->length, 'une structure à horaires saisit en durée');
			$this->assertTrue($document->getElementById('planning-time-working-day') !== NULL, 'le jour choisi d\'une structure à horaires perd sa carte');

		} finally {
			$eConfiguration['timeRange'] = $timeRange;
		}

	}

	/**
	 * Ce que la tuile d'un jour poste, comme `PlanningTimeWorking.getBody()` : ses `data-*` et le jour choisi de
	 * la fiche ; NULL quand le jour n'a pas de tuile.
	 */
	private function getPost(\Dom\HTMLDocument $document, string $date): ?array {

		$day = $document->querySelector('#planning-time-day-'.$date.' .planning-time-working[data-date]');

		if($day === NULL) {
			return NULL;
		}

		$input = [
			'id' => $day->getAttribute('data-structure'),
			'week' => $day->getAttribute('data-week'),
			'date' => $day->getAttribute('data-date'),
			'user' => $day->getAttribute('data-user'),
		];

		if($day->getAttribute('data-origin') !== '') {
			$input['origin'] = $day->getAttribute('data-origin');
		}

		$input['selected'] = $document->getElementById('planning-time')->getAttribute('data-selected');

		return $input;

	}

	/** Les nœuds qu'une réponse redessine, dans l'ordre. */
	private function getSelectors(array $instructions): array {

		return array_values(array_column(array_filter($instructions, fn(array $instruction) => $instruction[0] === 'qs'), 1));

	}

	/** Le fragment qu'une réponse pose sur un nœud. */
	private function getHtml(array $instructions, string $selector): ?string {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'qs' and $instruction[1] === $selector) {
				return $instruction[2][0][1][0];
			}

		}

		return NULL;

	}

	private function getWorkingTime(string $date): ?float {

		$eWorkingTime = \hr\WorkingTime::model()
			->select(['time'])
			->whereStructure($this->eStructure)
			->whereUser($this->getMember())
			->whereDate($date)
			->get();

		return $eWorkingTime->empty() ? NULL : $eWorkingTime['time'];

	}

}
?>
