<?php
namespace series;

/**
 * Le dénouement d'une série clôturée, et ce que la clôture retire du planning.
 *
 * Une série se clôture en disant, si on le veut, comment elle s'est terminée (`Series.outcome` :
 * réussie, ratée, annulée) et en laissant ses notes (`Series.comment`), par un même panneau ouvert
 * depuis le menu de la série ou depuis le plan pour un lot, où chaque ligne porte ses propres
 * champs (`outcome[<id>]`, `comment[<id>]`, `buildIndex()`). La règle tient dans
 * `Series::properties()` : pas de dénouement sans clôture, le dénouement restant facultatif à la
 * clôture. Rouvrir la série le vide donc, par le même `build()`, sans code dédié.
 *
 * La clôture n'écrit rien sur les interventions : ses interventions à faire sortent du planning
 * (`TaskLib::applyOpenSeries()`), restent sur la fiche de la série, et reviennent à la réouverture.
 * Les interventions faites, elles, restent dans le planning de la semaine où elles ont été faites.
 *
 * Le plan, enfin, sait dire quelles séries n'ont plus rien à faire (`Cultivation['idle']`) : c'est
 * le filtre « Sans intervention à venir » de l'omnibox, qui prépare une clôture de fin de saison.
 *
 * Lancement : dev/test package=series file=SeriesOutcomeAi
 */
class SeriesOutcomeAiTest extends SeriesFixtureAiTest {

	private function builds(Series $eSeries, array $input): array {

		$fw = new \FailWatch();
		$eSeries->build(['status', 'outcome'], $input, new \PropertyList('update'));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	public function testClosingMayTellAnOutcome(): void {

		$eSeries = $this->createSeries();

		$this->assertEquals([], $this->builds($eSeries, ['status' => Series::CLOSED]), 'une série se clôture sans dénouement');
		$this->assertEquals(NULL, $eSeries['outcome']);
		$this->assertEquals([], $this->builds($eSeries, ['status' => Series::CLOSED, 'outcome' => Series::FAILURE]), 'ou avec le sien');
		$this->assertEquals(Series::FAILURE, $eSeries['outcome']);

	}

	public function testAnOpenSeriesHasNoOutcome(): void {

		$eSeries = $this->createSeries();

		$this->assertEquals(['Series::outcome.closed'], $this->builds($eSeries, ['status' => Series::OPEN, 'outcome' => Series::SUCCESS]), 'une série ouverte ne porte pas de dénouement');
		$this->assertEquals(['Series::outcome.check'], $this->builds($eSeries, ['status' => Series::CLOSED, 'outcome' => 'fini']), 'le schéma refuse une valeur hors de l\'enum');

	}

	public function testReopeningClearsTheOutcome(): void {

		$eSeries = $this->createSeries();

		$this->builds($eSeries, ['status' => Series::CLOSED, 'outcome' => Series::SUCCESS]);
		SeriesLib::update($eSeries, ['status', 'outcome']);

		$this->assertEquals([], $this->builds($eSeries, ['status' => Series::OPEN]), 'la réouverture ne poste que le statut');
		SeriesLib::update($eSeries, ['status', 'outcome']);

		$eSeriesRead = Series::model()->select(['status', 'outcome'])->whereId($eSeries['id'])->get();

		$this->assertEquals(Series::OPEN, $eSeriesRead['status']);
		$this->assertEquals(NULL, $eSeriesRead['outcome'], 'le dénouement part avec la clôture');

	}

	public function testClosingByLotTakesOneOutcomeAndNotesPerSeries(): void {

		$eSeriesSuccess = $this->createSeries('Série réussie');
		$eSeriesCanceled = $this->createSeries('Série annulée');
		$eSeriesMixed = $this->createSeries('Série mitigée');
		$eSeriesSilent = $this->createSeries('Série sans dénouement');
		$eSeriesWrong = $this->createSeries('Série au dénouement inconnu');

		$input = [
			'outcome' => [
				$eSeriesSuccess['id'] => Series::SUCCESS,
				$eSeriesCanceled['id'] => Series::CANCELED,
				$eSeriesMixed['id'] => Series::MIXED,
				$eSeriesSilent['id'] => '',
				$eSeriesWrong['id'] => 'fini',
			],
			'comment' => [
				$eSeriesCanceled['id'] => '<p>Mildiou fin août</p>',
			],
		];

		$fw = new \FailWatch();

		foreach([$eSeriesSuccess, $eSeriesCanceled, $eSeriesMixed, $eSeriesSilent, $eSeriesWrong] as $eSeries) {
			$eSeries['status'] = Series::CLOSED;
			$eSeries->buildIndex(['outcome', 'comment'], $input, $eSeries['id']);
		}

		$this->assertEquals([['Series::outcome.check', 'outcome['.$eSeriesWrong['id'].']']], array_map(fn($fail) => [$fail[1], $fail[3]], $fw->get()), 'l\'échec est rattaché à la seule ligne fautive, « Aucun » passe');

		SeriesLib::closeCollection(new \Collection([$eSeriesSuccess, $eSeriesCanceled, $eSeriesMixed, $eSeriesSilent]));

		$cSeries = Series::model()
			->select(['id', 'status', 'outcome', 'comment'])
			->whereId('IN', [$eSeriesSuccess['id'], $eSeriesCanceled['id'], $eSeriesMixed['id'], $eSeriesSilent['id']])
			->getCollection(NULL, NULL, 'id');

		$this->assertEquals(Series::CLOSED, $cSeries[$eSeriesSuccess['id']]['status']);
		$this->assertEquals(Series::SUCCESS, $cSeries[$eSeriesSuccess['id']]['outcome']);
		$this->assertEquals(Series::CANCELED, $cSeries[$eSeriesCanceled['id']]['outcome']);
		$this->assertEquals(Series::MIXED, $cSeries[$eSeriesMixed['id']]['outcome'], 'la colonne connaît le dénouement mitigé');
		$this->assertEquals(Series::CLOSED, $cSeries[$eSeriesSilent['id']]['status'], 'close sans dénouement');
		$this->assertEquals(NULL, $cSeries[$eSeriesSilent['id']]['outcome']);
		$this->assertTrue(str_contains($cSeries[$eSeriesCanceled['id']]['comment'], 'Mildiou fin août'), 'les notes laissées à la clôture sont celles de la série');
		$this->assertEquals(NULL, $cSeries[$eSeriesSuccess['id']]['comment']);

	}

	public function testClosedSeriesLeavesThePlanningAndComesBack(): void {

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$week = \Clock::week();

		$eTaskTodo = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, $week);
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::DONE, $week);
		$eTaskOutOfSeries = $this->createTaskOutOfSeries();

		$planned = fn() => array_merge(...TaskLib::getForWeekTodo($this->eStructure, $week)->makeArray(fn($cTask) => $cTask->getIds()));
		$done = fn() => array_merge(...TaskLib::getForWeekDone($this->eStructure, $week)->makeArray(fn($cTask) => $cTask->getIds()));

		$this->assertTrue(in_array($eTaskTodo['id'], $planned()), 'une série ouverte sort ses interventions à faire');

		// Le filtre porte sur le statut, pas sur le dénouement : une série annulée
		// en sort comme une série réussie (2026-09-25, sur le signalement de Muïrgen).
		$eSeries = $this->getSeries();
		$eSeries['status'] = Series::CLOSED;
		$eSeries['outcome'] = Series::CANCELED;
		SeriesLib::update($eSeries, ['status', 'outcome']);

		$this->assertFalse(in_array($eTaskTodo['id'], $planned()), 'une série close ne les sort plus');
		$this->assertTrue(in_array($eTaskDone['id'], $done()), 'ce qui a été fait reste dans sa semaine');

		if($eTaskOutOfSeries['plannedWeek'] === $week) {
			$this->assertTrue(in_array($eTaskOutOfSeries['id'], $planned()), 'une intervention hors série n\'est pas concernée');
		}

		$this->assertTrue(in_array($eTaskTodo['id'], TaskLib::getBySeries($eSeries)->getIds()), 'la fiche de la série les garde');

		$eSeries['status'] = Series::OPEN;
		$eSeries['outcome'] = NULL;
		SeriesLib::update($eSeries, ['status', 'outcome']);

		$this->assertTrue(in_array($eTaskTodo['id'], $planned()), 'rouvrir la série les fait revenir');

		$eTaskTodoRead = Task::model()->select(['status'])->whereId($eTaskTodo['id'])->get();

		$this->assertEquals(Task::TODO, $eTaskTodoRead['status'], 'rien ne s\'est écrit sur l\'intervention');

	}

	/**
	 * Le plan garde toutes les interventions d'une série clôturée, faites et à faire, quel que soit
	 * son dénouement : c'est le planning de la semaine qui sort ses interventions à faire
	 * (`TaskLib::applyOpenSeries()`). Décidé le 2026-09-25 sur le signalement de Muïrgen
	 * (série de carottes annulée avant le semis, dont le semis avait disparu du plan quand la récolte
	 * attendue y restait) : soit tout reste, soit tout disparaît, et sur le plan tout reste.
	 */
	public function testClosedSeriesKeepsEverythingOnThePlan(): void {

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eSeries = $this->createSeries('Série éteinte');
		$eCultivationCreated = $this->createCultivation($eSeries);
		$eTaskDone = $this->createTask($eCultivationCreated, ACTION_PLANTATION, Task::DONE, $this->getSeason().'-W10');
		$eTaskTodo = $this->createTask($eCultivationCreated, ACTION_PLANTATION, Task::TODO, $this->getSeason().'-W20');

		$planned = function() use($eCultivationCreated): array {

			$tasks = [];

			foreach(CultivationLib::getForArea($this->eStructure, $this->getSeason()) as $cCultivation) {
				foreach($cCultivation as $eCultivationRead) {
					if($eCultivationRead['id'] === $eCultivationCreated['id']) {
						$tasks = $eCultivationRead['cTask']->getIds();
					}
				}
			}

			sort($tasks);

			return $tasks;

		};

		$expected = [$eTaskDone['id'], $eTaskTodo['id']];
		sort($expected);

		$this->assertEquals($expected, $planned(), 'ouverte : la plantation faite et celle à faire');

		foreach([Series::SUCCESS, Series::CANCELED] as $outcome) {

			$this->closeSeries($eSeries, $outcome);

			$this->assertEquals($expected, $planned(), $outcome.' : la plantation faite et celle à faire restent');

		}

	}

	public function testThePlanTellsWhichSeriesHaveNothingLeftToDo(): void {

		if($this->requireMember() === FALSE or $this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eSeriesBusy = $this->createSeries('Série à suivre');
		$eCultivationBusy = $this->createCultivation($eSeriesBusy);
		$this->createTask($eCultivationBusy, ACTION_PLANTATION, Task::TODO, \Clock::week());

		$eSeriesIdle = $this->createSeries('Série finie');
		$eCultivationIdle = $this->createCultivation($eSeriesIdle);
		$this->createTask($eCultivationIdle, ACTION_PLANTATION, Task::DONE, \Clock::week());

		$eSeriesClosed = $this->createSeries('Série close');
		$eCultivationClosed = $this->createCultivation($eSeriesClosed);
		$this->closeSeries($eSeriesClosed);

		$idle = [];

		foreach(CultivationLib::getForArea($this->eStructure, $this->getSeason()) as $cCultivation) {
			foreach($cCultivation as $eCultivation) {
				$idle[$eCultivation['id']] = $eCultivation['idle'];
			}
		}

		$this->assertFalse($idle[$eCultivationBusy['id']], 'une intervention à faire la garde hors du filtre');
		$this->assertTrue($idle[$eCultivationIdle['id']], 'tout est fait : elle est à clôturer');
		$this->assertFalse($idle[$eCultivationClosed['id']], 'une série déjà close n\'a plus rien à clôturer');

	}

}
?>
