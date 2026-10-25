<?php
namespace planning;

/**
 * Le pense-bête du planning : un rappel par ligne, avec sa semaine, son jour éventuel et son état.
 *
 * La liste rend les rappels tels qu'ils sont en base, pas ce qu'une semaine montre à l'écran : un rappel
 * resté à faire n'y est pas recopié dans les semaines qui suivent la sienne (`ReminderLib::getForWeek()`).
 * L'appelant qui veut ce que la semaine en cours montre lit les rappels à faire, toutes semaines
 * confondues, et ceux de la semaine.
 *
 * Cocher n'est pas modifier : `status`, `doneAt` et `doneBy` ne se construisent pas, ils se posent
 * ensemble par les routes `check` et `uncheck`, comme les cases de l'écran. La coche est signée du
 * compte d'API.
 */
class Reminder_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'reminderList',
			[
				'?week' => Reminder::getValidator('week'),
				'?status' => Reminder::getValidator('status')
			]
		);

		$this->create('reminder');
		$this->read('reminder/{id}');
		$this->update('reminder/{id}');
		$this->delete('reminder/{id}');

		$this->requestPatch('reminder/{id}/check', [], function(\ApiData $data) {

			if($data->e->acceptCheck() === FALSE) {
				throw new \ApiException('Reminder is already checked', 'accept_check', 412);
			}

			ReminderLib::check($data->e);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestPatch('reminder/{id}/uncheck', [], function(\ApiData $data) {

			if($data->e->acceptUncheck() === FALSE) {
				throw new \ApiException('Reminder is not checked', 'accept_uncheck', 412);
			}

			ReminderLib::uncheck($data->e);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->element(
			'planning\Reminder',
			fn() => [
				'id',
				'week', 'date', 'text',
				'status', 'doneAt', 'doneBy',
				'updatedAt'
			]
		);

	}

	public function getList(\ApiData $data): ReminderModel {

		return $this->getNewModel($data)
			->whereWeek(fn() => $data->get('week'), if: $data->exists('week'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): ReminderModel {

		return new \planning\ReminderModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Reminder {

		return new \planning\Reminder([
			'structure' => $data->eStructure
		]);

	}

}
?>
