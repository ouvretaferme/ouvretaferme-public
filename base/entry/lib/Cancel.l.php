<?php
namespace entry;

/**
 * Ce que l'annulation d'une saisie défait sur son intervention, quelle que soit l'intention qui l'a
 * écrite : le temps posé, et l'intervention elle-même quand la saisie l'a créée et que rien ne s'y est
 * ajouté depuis. La récolte annule ainsi son temps (`HarvestIntentionLib::cancel()`), le temps seul aussi
 * (`TimeIntentionLib::cancel()`).
 */
class CancelLib {

	/**
	 * Le temps que la saisie a posé, retiré ligne par ligne de la journée de son auteur sur l'intervention,
	 * la plus ancienne d'abord. Rien n'interdit plusieurs lignes pour le même jour (l'API en crée), et
	 * `writeByTasks()` porterait l'écart entier sur une seule : passée sous zéro, elle défait toute la pile
	 * de transactions, la récolte supprimée comprise. Une ligne vidée part, comme quand la fiche de temps la
	 * remet à zéro (`TimesheetLib::writeTimeForDay()`) : à zéro, elle compterait encore dans les dates de
	 * temps de l'intervention (`recalculateTime()`). Chaque retrait s'inscrit tel quel dans l'historique.
	 */
	public static function removeTime(\series\Task $eTask, \user\User $eUser, string $date, float $time): void {

		$cTimesheet = \series\Timesheet::model()
			->select('id', 'time')
			->whereTask($eTask)
			->whereUser($eUser)
			->whereDate($date)
			->sort(['id' => SORT_ASC])
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {

			if($time <= 0.0) {
				break;
			}

			$taken = min($eTimesheet['time'], $time);
			$time = round($time - $taken, 6);

			$eTimesheet->merge([
				'task' => $eTask,
				'structure' => $eTask['structure'],
				'time' => round($eTimesheet['time'] - $taken, 6),
			]);

			if($eTimesheet['time'] <= 0.0) {
				\series\TimesheetLib::delete($eTimesheet);
			} else {
				\series\TimesheetLib::update($eTimesheet, ['time']);
			}

		}

	}

	/**
	 * L'intervention que la saisie a créée, le ticket le disant, part avec ce qu'elle a écrit si rien ne s'y
	 * est ajouté depuis : retouchée par rien d'autre qu'une saisie (`$taskTouched`, qui couvre son statut,
	 * faite après une récolte, à faire après un temps seul), sans autre récolte que celle de la saisie
	 * (`$eHarvest`, vide pour un temps seul) ni commentaire, et sans autre temps que celui de la saisie
	 * (`$times`, en heures par jour), une ligne par jour de son auteur. Un jour dont le temps a été retiré depuis
	 * ne compte pas, que sa ligne reste à zéro ou soit partie : il n'y a rien à reprendre, et l'intervention n'en
	 * est pas moins celle de la saisie. Une intervention retouchée depuis garde ce qu'on y a ajouté, seuls la
	 * récolte et son temps s'annulent.
	 */
	public static function isCreatedBy(\series\Task $eTask, \user\User $eUser, array $times, string $taskTouched, \series\Harvest $eHarvest = new \series\Harvest()): bool {

		$times = array_filter($times, fn(float $time) => $time > 0.0);

		$cTimesheet = \series\Timesheet::model()
			->select(['user', 'date', 'time'])
			->whereTask($eTask)
			->whereTime('>', 0)
			->getCollection();

		$timeOwn = (
			$cTimesheet->count() === count($times) and
			count(array_unique($cTimesheet->getColumn('date'))) === count($times)
		);

		foreach($cTimesheet as $eTimesheet) {

			$timeOwn = (
				$timeOwn and
				$eTimesheet['user']->is($eUser) and
				array_key_exists($eTimesheet['date'], $times) and
				round($eTimesheet['time'] - $times[$eTimesheet['date']], 6) === 0.0
			);

		}

		return (
			self::getTouched($eTask) === $taskTouched and
			\series\Harvest::model()
				->whereTask($eTask)
				->whereId('!=', $eHarvest, if: $eHarvest->notEmpty())
				->exists() === FALSE and
			($cTimesheet->empty() or $timeOwn) and
			\series\Comment::model()
				->whereTask($eTask)
				->exists() === FALSE
		);

	}

	/**
	 * L'empreinte de l'intervention hors de ce qu'une saisie y écrit, la récolte, son unité et le temps que
	 * `recalculateTime()` en tire : une seconde saisie sur la même intervention la laisse intacte, toute
	 * autre retouche la change. Le temps lui-même se juge sur ses lignes (`isCreatedBy()`). La date de
	 * modification n'en fait pas partie, chaque saisie la déplaçant.
	 */
	public static function getTouched(\series\Task $eTask): string {

		$properties = array_values(array_diff(\series\Task::model()->getProperties(), ['harvest', 'harvestUnit', 'time', 'timesheetStart', 'timesheetStop', 'updatedAt']));

		$values = \series\Task::model()
			->select($properties)
			->whereId($eTask['id'])
			->get()
			->extracts($properties);

		return md5(json_encode(array_map(function($value) {
			if($value instanceof \Element) {
				return $value->empty() ? NULL : $value['id'];
			}

			return $value;
		}, $values)));

	}

}
?>
