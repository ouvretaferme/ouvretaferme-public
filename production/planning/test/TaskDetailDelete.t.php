<?php
namespace planning;

/**
 * La suppression d'une intervention depuis sa page (FU) : la roue de la page poste son lieu
 * (`TaskDetailUi::ORIGIN`), et la réponse porte la semaine où revenir quand la page est seule
 * (`PlanningTaskDetail.deleted()`). Ailleurs, rien ne change : pas d'adresse, l'écran se recharge.
 *
 * Chaque cas passe par la vraie route (`series/task:doDelete`, `doDeleteRepeat`), dans la transaction du
 * décor (`HarvestFixtureTest`), dont la semaine est la 39.
 *
 * Lancement : dev/test package=planning file=TaskDetailDelete
 */
class TaskDetailDeleteTest extends HarvestFixtureTest {

	/**
	 * Une intervention à faire ramène à sa semaine prévue, et la suppression l'emporte.
	 */
	public function testPlanned(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$week = $this->getSeason().'-W38';

		$eTask = $this->createTask(week: $week);

		$data = $this->callDelete('doDelete', $eTask, \planning\TaskDetailUi::ORIGIN);

		$this->assertEquals(TaskWeekUi::url($this->eStructure, $week), $data?->urlBack, 'la page d\'une intervention prévue ne ramène pas à sa semaine');
		$this->assertFalse($this->exists($eTask), 'l\'intervention n\'est pas supprimée');

	}

	/**
	 * Une intervention faite ramène à la semaine de sa réalisation, non à celle d'où on l'avait prévue.
	 */
	public function testDone(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$week = $this->getSeason().'-W37';

		$eTask = $this->createTask(status: \series\Task::DONE, week: $week);

		// Une intervention prévue une semaine et faite une autre : le retour ne doit pas prendre la prévue.
		// Sans `build()`, qui passerait par `planned` (format de la semaine, écart d'un an au plus avec la
		// saison) : le décor pose en clair une semaine de sa saison et le test ne poste rien.
		$eTask['plannedWeek'] = $this->getSeason().'-W36';
		\series\TaskLib::update($eTask, ['plannedWeek']);

		$data = $this->callDelete('doDelete', $eTask, \planning\TaskDetailUi::ORIGIN);

		$this->assertEquals(TaskWeekUi::url($this->eStructure, $week), $data?->urlBack, 'la page d\'une intervention faite ne ramène pas à la semaine de sa réalisation');

	}

	/**
	 * Une intervention sans semaine ramène à la semaine en cours, où se tient la zone « Non planifié ».
	 */
	public function testUnplanned(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eTask = $this->createTask();

		$data = $this->callDelete('doDelete', $eTask, \planning\TaskDetailUi::ORIGIN);

		$this->assertEquals(TaskWeekUi::url($this->eStructure), $data?->urlBack, 'la page d\'une intervention non planifiée ne ramène pas à la semaine en cours');
		$this->assertFalse($this->exists($eTask), 'l\'intervention non planifiée n\'est pas supprimée');

	}

	/**
	 * Sans le lieu de la page (tiroir de l'ancien planning, `/tache/{id}`, liste), aucune adresse : la vue
	 * garde son rechargement.
	 */
	public function testElsewhere(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eTask = $this->createTask(week: $this->getSeason().'-W38');

		$data = $this->callDelete('doDelete', $eTask, NULL);

		$this->assertTrue($data !== NULL and $data->urlBack === NULL, 'une suppression hors de la page porte une adresse de retour');
		$this->assertFalse($this->exists($eTask), 'l\'intervention n\'est pas supprimée hors de la page');

	}

	/**
	 * « Cette intervention et toutes les suivantes » ramène à la semaine de celle qu'on supprime, et emporte
	 * les suivantes de la répétition.
	 */
	public function testRepeat(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$week = $this->getSeason().'-W38';

		$eTask = $this->createTaskRepeated($week);

		$repeated = \series\Task::model()->whereRepeat($eTask['repeat'])->count();

		if($this->assertDecorPresent($repeated > 1, 'une répétition hebdomadaire sur une série, qui crée ses interventions à la création') === FALSE) {
			return;
		}

		$data = $this->callDelete('doDeleteRepeat', $eTask, \planning\TaskDetailUi::ORIGIN);

		$this->assertEquals(TaskWeekUi::url($this->eStructure, $week), $data?->urlBack, 'la suppression de la répétition ne ramène pas à la semaine de l\'intervention');
		$this->assertEquals(0, \series\Task::model()->whereRepeat($eTask['repeat'])->wherePlannedWeek('>=', $week)->count(), 'la suppression de la répétition laisse des interventions à partir de la sienne');

	}

	/**
	 * Une route de suppression, exécutée puis rendue par sa vue : ses données, `NULL` si elle a levé autre
	 * chose qu'une vue.
	 */
	private function callDelete(string $route, \series\Task $eTask, ?string $origin): ?\stdClass {

		$input = ['id' => $eTask['id']];

		if($origin !== NULL) {
			$input['origin'] = $origin;
		}

		$action = $this->executePage('series/task:'.$route, $input, 'POST');

		if($this->assertTrue($action instanceof \ViewAction, $route.' est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return NULL;
		}

		[$data] = $this->runView('doDelete', $action, 'task', 'series');

		return $data;

	}

	/**
	 * Une intervention répétée chaque semaine pendant quatre semaines, sur la mise en culture du décor : la
	 * répétition d'une série crée toutes ses interventions d'un coup (`TaskLib::create()`).
	 */
	private function createTaskRepeated(string $week): \series\Task {

		$eCultivation = $this->getCultivation();

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_PLANTATION),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => $week,
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat([
				'frequency' => \series\Repeat::W1,
				'stop' => $this->getSeason().'-W41',
			]),
		]);

		// Sans `build()`, qui vérifierait la fréquence et la fin saisies : le décor les pose en clair et le
		// test ne poste rien.
		\series\TaskLib::create($eTask);

		return \series\TaskLib::getById($eTask['id']);

	}

	private function exists(\series\Task $eTask): bool {
		return \series\Task::model()->whereId($eTask['id'])->exists();
	}

}
?>
