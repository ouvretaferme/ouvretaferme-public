<?php
namespace series;

/**
 * Une série ne se décale pas de sa saison.
 *
 * Le calendrier d'une série ne sait représenter que l'année de sa saison, la précédente et la
 * suivante (`Series::calculatedBed()`, semaines négatives ou au-delà de 100). Deux chemins
 * posaient pourtant une intervention plus loin :
 * - une intervention se planifiait ou se faisait jusqu'à deux ans avant ou après la saison ;
 * - une série changeait de saison sans que ses interventions ne bougent, le panneau le dit.
 *
 * Cas vu le 23/09/2026 : une série de persil de la saison 2027, plantée en 2025 et récoltée en 2026,
 * occupait l'assolement 2026 sans y avoir été posée.
 *
 * Lancement : dev/test package=series file=SeriesSeasonAi
 */
class SeriesSeasonAiTest extends SeriesFixtureAiTest {

	private function buildsTask(string $status, string $property, string $week): array {

		$eTask = new Task([
			'season' => $this->getSeason(),
			'status' => $status,
		]);

		$fw = new \FailWatch();
		$eTask->build([$property], [$property => ['week' => $week]], new \PropertyList('update'));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	private function buildsSeason(Series $eSeries, int $season): array {

		$fw = new \FailWatch();
		$eSeries->build(['season'], ['season' => $season], new \PropertyList('update'));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	private function reload(Series $eSeries): Series {

		return Series::model()
			->select(Series::getSelection())
			->whereId($eSeries['id'])
			->get();

	}

	public function testTaskStaysWithinOneYearOfSeason(): void {

		$season = $this->getSeason();

		$this->assertEquals([], $this->buildsTask(Task::TODO, 'planned', ($season - 1).'-W40'), 'l\'année précédente est acceptée');
		$this->assertEquals([], $this->buildsTask(Task::TODO, 'planned', ($season + 1).'-W10'), 'l\'année suivante est acceptée');
		$this->assertEquals(['Task::planned.season'], $this->buildsTask(Task::TODO, 'planned', ($season - 2).'-W42'), 'deux ans avant, refusé');
		$this->assertEquals(['Task::planned.season'], $this->buildsTask(Task::TODO, 'planned', ($season + 2).'-W02'), 'deux ans après, refusé');

		$this->assertEquals([], $this->buildsTask(Task::DONE, 'done', ($season + 1).'-W10'), 'faite l\'année suivante, acceptée');
		$this->assertEquals(['Task::done.season'], $this->buildsTask(Task::DONE, 'done', ($season - 2).'-W42'), 'faite deux ans avant, refusée');

	}

	public function testSeasonChangeKeepsTasksWithinOneYear(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$season = $this->getSeason();

		// Les saisons essayées restent dans les bornes de la structure (S-1 à S+1) : au-delà, c'est le
		// contrôle du schéma (`Configuration::checkSeason()`) qui refuserait, pas celui des interventions.
		// Le décor n'ouvre que deux saisons : les bornes s'élargissent sur la configuration mémoïsée par
		// structure (`Structure::conf()`), que lit ce contrôle, et se rétablissent en sortie quel que soit
		// le résultat, comme `TaskDay::testAlsoWithoutTime()` coupe le temps de travail.
		$eConfiguration = $this->eStructure->conf(\map\Configuration::class);
		$seasons = [$eConfiguration['seasonFirst'], $eConfiguration['seasonLast']];

		$eConfiguration['seasonFirst'] = min($seasons[0], $season - 1);
		$eConfiguration['seasonLast'] = max($seasons[1], $season + 1);

		try {

			$eSeriesBefore = $this->createSeries();
			$this->createTask($this->createCultivation($eSeriesBefore), week: ($season - 1).'-W20');

			$this->assertEquals([], $this->buildsSeason($this->reload($eSeriesBefore), $season), 'l\'intervention reste dans l\'année précédente');
			$this->assertEquals(['Series::season.tasks'], $this->buildsSeason($this->reload($eSeriesBefore), $season + 1), 'l\'intervention tomberait deux ans avant');

			$eSeriesAfter = $this->createSeries();
			$this->createTask($this->createCultivation($eSeriesAfter), week: ($season + 1).'-W20');

			$this->assertEquals(['Series::season.tasks'], $this->buildsSeason($this->reload($eSeriesAfter), $season - 1), 'l\'intervention tomberait deux ans après');

		} finally {
			[$eConfiguration['seasonFirst'], $eConfiguration['seasonLast']] = $seasons;
		}

	}

	public function testDriftedSeriesStaysEditable(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$season = $this->getSeason();

		$eSeries = $this->createSeries();
		$eTask = $this->createTask($this->createCultivation($eSeries), week: $season.'-W20');

		// L'état d'avant le correctif, que plus aucun chemin du produit ne sait poser.
		Task::model()->update($eTask, ['plannedWeek' => ($season - 2).'-W42']);

		$this->assertEquals([], $this->buildsSeason($this->reload($eSeries), $season), 'une série déjà décalée se modifie sans changer de saison');

	}

}
?>
