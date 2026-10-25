<?php
namespace queue;

class DevObserverLib {

	public static function integrity(): \Generator {

		yield self::getStuck();

	}

	/**
	 * A queue that no longer empties means no runner is passing on that action.
	 *
	 * This is the only signal a broken queue gives: a pending task does not age, is not lost and is
	 * not reported, so a missing crontab line or a withdrawn consumer lets requests pile up with no
	 * error at all.
	 *
	 * ⚠️ `queueTask` lives in the common database: one check for the whole estate, with no loop over
	 * structures and no `connectDatabase()`.
	 */
	public static function getStuck(): ?array {

		$cTaskStuck = QueueLib::getStuck();

		if($cTaskStuck->empty()) {
			return NULL;
		}

		return [
			'Des tâches de la file sont bloquées depuis plus de '.(QueueLib::STUCK / 3600).' h : le runner de ces actions ne passe pas',
			$cTaskStuck->getArrayCopy()
		];

	}

}
?>
