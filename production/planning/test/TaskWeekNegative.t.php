<?php
namespace planning;

/**
 * Le refus de la bulle quand une baisse rendrait le jour négatif (écart g de L5a, tranché par
 * V-Planning). Il ne se produit qu'avec deux lignes le même jour, que laissent deux premiers envois
 * simultanés ou une ligne créée par l'API (`series/api/Timesheet_0_1beta.a.php`), l'index
 * `(user, date, task)` n'étant pas unique : `writeByTasks()` retire l'écart d'une seule, qui passerait
 * sous zéro.
 *
 * ⚠️ Le `rollBack()` de `TimesheetLib::writeByTasks()` remet la pile du serveur à 0 quel que soit
 * l'étage (`framework/core/Module.php`), donc il défait aussi la transaction du test et le décor avec
 * elle : les lignes ne peuvent plus être comptées après le refus. Le cas vérifie le code du refus et
 * que plus aucune transaction n'est ouverte, ce qui n'arrive que par ce `rollBack()`. D'où
 * `CLOSED_BY_TEST`, et la classe à part, pour ne pas l'étendre aux cas de `TaskWeekTest`
 * (précédent : `base/entry/test/EntryAtomicity.t.php`).
 *
 * Lancement : dev/test package=planning file=TaskWeekNegative
 */
class TaskWeekNegativeTest extends HarvestFixtureTest {

	const CLOSED_BY_TEST = TRUE;

	public function testTimeNegativeRefused(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour la bulle') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		$this->callTask('doDone', $eTask);

		$this->createTimesheet($eTask, 1.0, \Clock::date());
		$this->createTimesheet($eTask, 1.0, \Clock::date());

		[, , $action] = $this->callTask('doTime', $eTask, ['time' => '0.25']);

		$this->assertTrue(in_array('Timesheet::time.negative', $this->getFails($action), TRUE), 'la baisse qui rend une ligne négative n\'est pas refusée par son code : '.implode(', ', $this->getFails($action)));
		$this->assertFalse(\series\Timesheet::model()->inTransaction(), 'le refus n\'a pas défait l\'écriture');

	}

}
?>
