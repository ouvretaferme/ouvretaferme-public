<?php
namespace series;

/**
 * Une intervention de récolte qui change de mise en culture emmène ses quantités récoltées.
 *
 * Relevé le 2026-10-08 sur la structure 7 : les interventions 4516002 et 1431314 avaient été
 * déplacées vers une autre culture de la même espèce et de la même saison, mais leurs lignes
 * series\Harvest pointaient toujours l'ancienne série et l'ancienne culture. TaskLib::update()
 * réalignait les feuilles de temps et le cumul de la culture (`harvestWeeks`), pas le journal des
 * récoltes, que le planning lit pour ranger une série dans les récoltes d'une semaine : la série
 * quittée y restait, celle qui recevait n'y venait pas.
 *
 * Lancement : dev/test package=series file=TaskMoveHarvestAi
 */
class TaskMoveHarvestAiTest extends SeriesFixtureAiTest {

	public function testHarvestFollowsTheTask(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivationFrom = $this->getCultivation();
		$eCultivationTo = $this->createCultivation($this->createSeries('Série qui reçoit la récolte'));

		$eTask = $this->createTaskHarvest($eCultivationFrom);
		$this->createHarvest($eTask, 12.0);

		$eTask = Task::model()
			->select(Task::getSelection())
			->whereId($eTask)
			->get();

		$fw = new \FailWatch();

		$eTask->build(['cultivation'], ['cultivation' => (string)$eCultivationTo['id']], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), 'le déplacement est accepté : '.$fw);

		TaskLib::update($eTask, ['cultivation']);

		$cHarvest = Harvest::model()
			->select('series', 'cultivation')
			->whereTask($eTask)
			->getCollection();

		$this->assertEquals(1, $cHarvest->count(), 'la quantité récoltée est toujours là');
		$this->assertEquals($eCultivationTo['id'], $cHarvest->first()['cultivation']['id'], 'la récolte suit la culture de l\'intervention');
		$this->assertEquals($eCultivationTo['series']['id'], $cHarvest->first()['series']['id'], 'la récolte suit la série de l\'intervention');

	}

}
?>
