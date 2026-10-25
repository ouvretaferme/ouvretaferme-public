<?php
namespace series;

/**
 * Les trois requêtes seriesTask du plan d'assolement (récolte par série, semis et
 * plantation par culture, outils pour le filtre) sont devenues une seule, répartie
 * en PHP par TaskLib::spreadForSoil(). Ce test compare la répartition à la base.
 *
 * Lancement : dev/test package=series file=SoilTaskSpreadAi
 */
class SoilTaskSpreadAiTest extends \Test {

	public function testSpread(): void {

		$eStructure = \structure\StructureLib::getById(7);
		$season = 2026;

		$cZone = \map\ZoneLib::getByStructure($eStructure, season: $season);
		\map\PlotLib::putFromZoneWithSeries($eStructure, $cZone, $season, [$season + 1, $season, $season - 1], onlySeries: TRUE);

		$found = 0;

		foreach($cZone as $eZone) {
			foreach($eZone['cPlot'] as $ePlot) {
				foreach($ePlot['cBed'] as $eBed) {
					foreach($eBed['cPlace'] as $ePlace) {

						$eSeries = $ePlace['series'];

						if($eSeries->empty()) {
							continue;
						}

						$found++;

						$this->assertTrue($eSeries['cTask'] instanceof \Collection);
						$this->assertTrue($eSeries['cTaskSoil'] instanceof \Collection);

						foreach($eSeries['cCultivation'] as $eCultivation) {

							$this->assertTrue($eCultivation['cTask'] instanceof \Collection);

							$expected = Task::model()
								->select(['plannedWeek', 'doneWeek', 'status'])
								->whereCultivation($eCultivation)
								->whereAction('IN', \work\ActionLib::getByStructure($eStructure, fqn: [ACTION_SEMIS_DIRECT, ACTION_PLANTATION]))
								->sort(new \Sql('IF(status="'.Task::TODO.'", plannedWeek, doneWeek)'))
								->getCollection();

							$expectedWeek = $expected->empty() ? NULL : (($expected->first()['status'] === Task::TODO) ? $expected->first()['plannedWeek'] : $expected->first()['doneWeek']);

							$this->assertEquals($expectedWeek, $eCultivation['firstTaskWeek'], 'cultivation '.$eCultivation['id']);
							$this->assertEquals($expected->count(), $eCultivation['cTask']->count());

						}

					}
				}
			}
		}

		$this->assertTrue($found > 0);

	}

}
