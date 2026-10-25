<?php
namespace series;

/**
 * Les interventions retardées dans l'export PDF du planning hebdomadaire.
 *
 * L'écran du planning affiche quatre sections : todo, delayed, unplanned, done. L'export PDF
 * n'en préparait qu'une, celle de la semaine (TaskLib::getForExport()), et les interventions
 * retardées disparaissaient donc du papier alors qu'elles sont ce que la semaine doit rattraper.
 *
 * TaskLib::getForExportDelayed() prépare les retardées exactement comme getForExport() prépare
 * la semaine : même remplissage (fillForWeek), même indexation par action, pour que
 * PlanningUi::getExportDelayed() puisse les rendre avec le même getTasks().
 *
 * L'intervention en retard est celle du décor (`SeriesFixtureAiTest`), à faire deux semaines avant
 * la semaine de référence, posée dans la transaction de la méthode : le test cherchait avant cela la
 * structure de la base qui portait un retard, et la trouvait ou non selon le rechargement.
 *
 * Les actions principales se posent comme le fait la page du PDF (`work/page/planning.p.php`,
 * `getWeeklyPdf`) : `ActionLib::saveMain()` avant toute lecture, sans quoi `CropUi::start()` reçoit
 * une collection absente au rendu d'une intervention de série.
 *
 * Lancement : dev/test package=series file=PlanningDelayedAi
 */
class PlanningDelayedAiTest extends SeriesFixtureAiTest {

	/** Deux semaines de retard : dans la fenêtre de rattrapage quelle que soit sa valeur, `planningDelayedMax` se comptant en mois. */
	const DELAY_DAYS = 14;

	private function getWeek(): string {

		return \Clock::week();

	}

	private function getWeekDelayed(): string {

		return toWeek(strtotime('NOW - '.self::DELAY_DAYS.' DAY'));

	}

	/** Les actions de la structure, indexées par identifiant, et retenues comme actions principales, comme le fait la page. */
	private function getActions(): \Collection {

		return $this->once('actions', function() {

			$cAction = \work\ActionLib::getByStructure($this->eStructure, index: 'id');

			\work\ActionLib::saveMain($cAction);

			return $cAction;

		});

	}

	private function createTaskDelayed(): Task {

		return $this->createTask($this->getCultivation(), ACTION_PLANTATION, Task::TODO, $this->getWeekDelayed());

	}

	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_PLANTATION));

	}

	public function testDelayedTasksAreCollectedForTheExport(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->createTaskDelayed();

		$ccDelayed = TaskLib::getForExportDelayed($this->eStructure, $this->getWeek(), $this->getActions());

		$this->assertTrue($ccDelayed->offsetExists($eTask['action']['id']), 'les retardées sont indexées par action');
		$this->assertTrue(in_array($eTask['id'], $ccDelayed[$eTask['action']['id']]->getIds()), 'l\'intervention à faire deux semaines avant la référence est retardée');

	}

	public function testAFutureWeekHasNoDelay(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$this->createTaskDelayed();

		$ccDelayed = TaskLib::getForExportDelayed($this->eStructure, toWeek(strtotime('NOW + 7 DAY')), $this->getActions());

		$this->assertEquals(0, $ccDelayed->count(), 'une semaine dans le futur ne rattrape rien');

	}

	public function testTheDelayedTaskIsOnThePaper(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->createTaskDelayed();

		$ccDelayed = TaskLib::getForExportDelayed($this->eStructure, $this->getWeek(), $this->getActions());

		$h = new PlanningUi()->getExportDelayed($this->eStructure, $this->getWeek(), $ccDelayed);

		$this->assertTrue(str_contains($h, encode($eTask['plant']['name'])), 'l\'espèce de l\'intervention retardée est sur le papier');

	}

	public function testAnEmptyCollectionRendersNothing(): void {

		$h = new PlanningUi()->getExportDelayed($this->eStructure, $this->getWeek(), new \Collection());

		$this->assertEquals('', $h, 'sans retard, pas de titre orphelin sur le PDF');

	}

}
?>
