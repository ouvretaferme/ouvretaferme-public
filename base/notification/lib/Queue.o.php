<?php
namespace notification;

class QueueObserverLib {

	/**
	 * Un consommateur par type, dérivé de l'inventaire pour la même raison que tout le reste : un
	 * type déclaré sans consommateur laisse `emit()` déposer une tâche que personne ne prend, elle
	 * vieillit jusqu'à `FAILED`, la purge l'emporte, et la notification n'a jamais existé pour
	 * personne.
	 */
	public static function getConsumers(): array {

		$consumers = [];

		foreach(array_keys(NotificationLib::getTypes()) as $type) {
			$consumers[NotificationLib::getAction($type)] = fn(\queue\Task $eTask) => NotificationLib::treat($eTask);
		}

		return $consumers;

	}

}
?>
