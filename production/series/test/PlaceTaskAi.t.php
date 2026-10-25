<?php
namespace series;

/**
 * L'assolement d'une intervention hors série.
 *
 * Le chantier assolement a remplacé le formulaire de planches à cocher par le bouton
 * « + Ajouter » et n'a pas porté ce geste aux interventions : le panneau
 * place:update?task= affichait le plan mais aucun bouton, aucune case, aucun bouton de
 * validation — entièrement inerte. La chaîne était typée Series de bout en bout, alors
 * que la page prépare déjà la Task comme une série en BED.
 *
 * L'écriture réelle ne se joue pas ici : seriesPlace.createdBy exige un utilisateur
 * connecté, que le CLI n'a pas. Ce test éprouve la construction, qui est pure.
 *
 * Aucun identifiant en dur : plusieurs sessions écrivent sur cette base, et un test
 * accroché à une intervention précise tombe dès que quelqu'un la touche.
 *
 * Lancement : dev/test package=series file=PlaceTaskAi
 */
class PlaceTaskAiTest extends \Test {

	private function task(): Task {

		$eTask = Task::model()
			->select(['id', 'structure', 'series', 'plannedWeek', 'doneWeek'])
			->where('series IS NULL')
			->where('structure', 7)
			->get();

		if($eTask->empty()) {
			return $eTask;
		}

		$eTask['structure'] = \structure\StructureLib::getById($eTask['structure']);
		$eTask['season'] = ($eTask['doneWeek'] ?? $eTask['plannedWeek']) ?
			week_year($eTask['doneWeek'] ?? $eTask['plannedWeek']) :
			\Clock::year();
		$eTask['use'] = Series::BED;
		$eTask['bedWidth'] = NULL;

		return $eTask;

	}

	private function beds(Task $eTask): array {

		return \map\Bed::model()
			->select(['id'])
			->whereStructure($eTask['structure'])
			->whereStatus(\map\Bed::ACTIVE)
			->getCollection(0, 2)
			->getIds();

	}

	public function testAStructureHasTasksOutOfAnySeries(): void {

		$eTask = $this->task();

		$this->assertTrue($eTask->notEmpty(), 'la structure de dev porte une intervention hors série');

	}

	public function testBuildingPlacesForATask(): void {

		$eTask = $this->task();
		$beds = $this->beds($eTask);

		$this->assertEquals(2, count($beds), 'deux planches actives pour éprouver la construction');

		$cPlace = PlaceLib::buildFromBeds('task', $eTask, $beds, array_fill_keys($beds, 25));

		echo "\n  intervention ".$eTask['id']." : ".$cPlace->count()." emplacement(s) construit(s) sur ".count($beds)." planche(s)\n";

		$this->assertEquals(count($beds), $cPlace->count(), 'une ligne par planche demandée');

		foreach($cPlace as $ePlace) {

			$this->assertTrue($ePlace['task']->is($eTask), 'la ligne porte l\'intervention');
			$this->assertFalse($ePlace->offsetExists('series'), 'et pas la moindre série');

			// Une intervention occupe la planche entière : pas de découpe en longueur,
			// contrairement à une série en BED.
			$this->assertEquals(NULL, $ePlace['length'], 'sans longueur');
			$this->assertEquals(NULL, $ePlace['area'], 'ni surface');

		}

	}

	public function testAWholeBedIsOfferedToATask(): void {

		$eTask = $this->task();
		$eBed = \map\BedLib::getById($this->beds($eTask)[0]);

		$libre = PlaceLib::getFreeLength($eTask, $eBed);

		echo "  planche ".$eBed['id']." : ".$libre." mL offerts, sur ".($eBed['length'] ?? 0)." mL\n";

		$this->assertEquals((int)($eBed['length'] ?? 0), $libre, 'une intervention n\'a pas de dates : la planche est offerte entière');

	}

}
?>
