<?php
namespace series;

/**
 * Le refus `time.negative` de `TimesheetLib::writeByTasks()` emporte les lignes d'historique que la même
 * écriture a posées avant lui (lot L5f).
 *
 * Deux interventions reçoivent une baisse répartie à parts égales : la première a du temps et l'accepte,
 * elle écrit sa ligne de temps et sa ligne d'historique ; la seconde n'en a pas, et le refus tombe sur elle.
 *
 * ⚠️ Le `rollBack()` du refus remet la pile du serveur à 0 quel que soit l'étage (`framework/core/Module.php`),
 * donc il défait aussi la transaction du test et le décor avec elle. Ce que le cas prouve : aucune ligne
 * d'historique de la première intervention ne survit, ce qui n'arriverait pas si l'historique s'écrivait
 * hors de la transaction du geste. D'où `CLOSED_BY_TEST`, et la classe à part de `HistoryAiTest`
 * (précédent : `production/planning/test/TaskWeekNegative.t.php`).
 *
 * Lancement : dev/test package=series file=HistoryNegativeAi
 */
class HistoryNegativeAiTest extends SeriesFixtureAiTest {

	const CLOSED_BY_TEST = TRUE;

	public function testRefusalTakesHistoryAway(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eTaskWithTime = $this->createTask();
		$this->createTimesheet($eTaskWithTime, 1.0, '2024-03-04');

		$eTaskWithout = $this->createTask();

		$cTask = Task::model()
			->select(Task::getSelection())
			->whereId('IN', [$eTaskWithTime['id'], $eTaskWithout['id']])
			->sort(['id' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

		$fw = new \FailWatch();

		TimesheetLib::writeByTasks($cTask, new Timesheet([
			'structure' => $this->eStructure,
			'user' => $this->getMember(),
			'date' => '2024-03-04'
		]), -0.5, 'fair');

		$this->assertTrue($fw->has('Timesheet::time.negative'), 'la baisse sur une intervention sans temps n\'est pas refusée par time.negative');
		$this->assertFalse(Timesheet::model()->inTransaction(), 'le refus n\'a pas défait l\'écriture');
		$this->assertEquals(0, History::model()->whereTask($eTaskWithTime)->count(), 'une ligne d\'historique survit au refus de l\'écriture qui l\'a posée');

	}

}
?>
