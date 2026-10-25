<?php
namespace toolbox;

/**
 * Le décor des tests du carnet d'entretien au planning (`MaintenanceTaskTest`).
 *
 * Tout s'écrit sur F1, structure du décor des tests, dans une transaction annulée par méthode
 * (`TransactionTest`). Chaque matériel porte un nom propre au test, que la description de la tâche
 * reprend : c'est par elle qu'un cas retrouve ce qu'il a écrit au planning.
 */
abstract class MaintenanceTaskFixtureTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	private \user\User $eUserInitial;

	/**
	 * La tâche est créée au nom du compte connecté : le propriétaire de la structure l'est le temps
	 * des tests.
	 */
	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		$eMember = \structure\Member::model()
			->select(['user'])
			->whereStructure(static::STRUCTURE)
			->sort(['id' => SORT_ASC])
			->get();

		\user\ConnectionLib::setOnline($eMember['user']);

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	protected function getTransactionModel(): \ModuleModel {
		return Equipment::model();
	}

	protected function getStructure(): \structure\Structure {
		return \structure\StructureLib::getById(static::STRUCTURE);
	}

	/**
	 * Un matériel suivi, au nom propre au test, et un entretien planifié à la date donnée.
	 */
	protected function createMaintenance(\structure\Structure $eStructure, string $plannedDate = '2026-07-01', string $name = 'Vidange moteur'): Maintenance {

		$eTool = \work\ToolLib::getNewTool($eStructure, NULL);

		// Sans `build()` : le nom est choisi par le test, et « Mon matériel » n'est pas ce qu'il éprouve.
		$eTool['name'] = 'Tracteur '.uniqid();

		\work\ToolLib::create($eTool);

		$eEquipment = new Equipment([
			'structure' => $eStructure,
			'tool' => $eTool,
			'meter' => NULL,
			'eToolNew' => new \work\Tool(),
		]);

		EquipmentLib::follow($eEquipment, new EquipmentReading());

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => $name,
			'everyValue' => NULL,
			'everyMonths' => 12,
			'plannedDate' => $plannedDate,
			'plannedValue' => NULL,
		]);

		MaintenanceLib::create($eMaintenance);

		return $eMaintenance;

	}

	/**
	 * Le réglage de la ferme posé sur « Matériel », comme après un premier « Fait ».
	 */
	protected function configure(\structure\Structure $eStructure): \work\Action {

		$eAction = MaintenanceConfigurationLib::getDefaultAction(MaintenanceConfigurationLib::getActions($eStructure));

		// Sans `build()` : le réglage est le décor, l'intervention posée est lue dans celles que
		// `MaintenanceConfiguration::action.usable` accepterait, et le rythme du rappel est celui du schéma.
		MaintenanceConfigurationLib::create(new MaintenanceConfiguration([
			'structure' => $eStructure,
			'action' => $eAction,
			'readingEveryMonths' => MaintenanceConfigurationLib::getReadingEveryMonthsDefault()
		]));

		return $eAction;

	}

	/**
	 * Un entretien fait construit comme le panneau le poste. `$eMaintenance` vide est « Consigner un
	 * entretien », sur le matériel donné.
	 */
	protected function build(Equipment $eEquipment, Maintenance $eMaintenance, array $input): array {

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $eEquipment['structure'],
			'equipment' => $eEquipment,
			'maintenance' => $eMaintenance,
		]);

		$fw = new \FailWatch();

		$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), $input + [
			'name' => 'Changement de courroie',
			'date' => \Clock::date(),
			'meterUpdate' => '0',
			'value' => '',
			'cost' => '',
			'comment' => ''
		]);

		return [$eMaintenanceDone, $fw];

	}

	/**
	 * La tâche qu'un entretien fait a écrite : elle se retrouve par sa description, qui porte le nom
	 * du matériel, propre au test.
	 */
	protected function getTasks(Maintenance $eMaintenance): \Collection {

		return \series\Task::model()
			->select(['id', 'action', 'category', 'tools', 'description', 'doneDate', 'doneWeek', 'status', 'series', 'time'])
			->whereStructure($eMaintenance['structure'])
			->whereDescription($eMaintenance['name'].', '.$eMaintenance['equipment']['tool']['name'])
			->getCollection();

	}

	protected function getTimes(\series\Task $eTask): array {

		return \series\Timesheet::model()
			->select(['id', 'user', 'date', 'time'])
			->whereTask($eTask)
			->getCollection()
			->makeArray(fn(\series\Timesheet $eTimesheet) => [$eTimesheet['user']['id'], $eTimesheet['date'], $eTimesheet['time']]);

	}

}
?>
