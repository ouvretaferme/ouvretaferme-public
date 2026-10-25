<?php
namespace hr;

class WorkingTimeLib extends WorkingTimeCrud {

	public static function getPropertiesCreate(?string $origin = NULL, \structure\Structure $eStructure = new \structure\Structure()): array {

		// Sans structure, la durée : c'est l'appel du cadriciel (WorkingTimePage, ModuleApi), que les
		// appelants qui connaissent la structure remplacent.
		if(
			$eStructure->notEmpty() and
			$eStructure->hasTimeRange()
		) {
			$properties = ['date', 'start', 'end', 'pause'];
		} else {
			$properties = ['date', 'time'];
		}

		// La structure vient du jeton en API, elle n'a pas à être postée.
		if($origin === 'api') {
			return array_merge(['user'], $properties);
		}

		return array_merge(['structure', 'user'], $properties);

	}

	public static function getByDate(\structure\Structure $eStructure, \user\User $eUser, string $date): WorkingTime {

		return WorkingTime::model()
			->select(WorkingTime::getSelection())
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereDate($date)
			->get();

	}

	public static function getByWeek(\structure\Structure $eStructure, \user\User $eUser, string $week): \Collection {

		$firstDate = week_date_starts($week);
		$lastDate = week_date_ends($week);

		return WorkingTime::model()
			->select([
				'date',
				'time', 'start', 'end', 'pause'
			])
			->whereStructure($eStructure)
			->whereUser($eUser)
			->where('date BETWEEN '.WorkingTime::model()->format($firstDate).' AND '.WorkingTime::model()->format($lastDate))
			->getCollection(NULL, NULL, 'date');

	}

	/**
	 * Pose sur chaque personne, pour chaque jour de la semaine, la durée de sa journée (`workingTime`, `NULL` à
	 * saisir) et la journée elle-même (`cWorkingTime`, par date, vide à saisir), dont les horaires remplissent la
	 * saisie d'une structure à horaires (`planning\TimeDayUi::getWorking()`).
	 */
	public static function fillByWeekFromUsers(\structure\Structure $eStructure, \Collection $cUser, string $week): void {

		$dates = week_dates($week);
		$firstDate = first($dates);
		$lastDate = last($dates);

		$cWorkingTime = WorkingTime::model()
			->select(['user', 'date', 'time', 'start', 'end', 'pause'])
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->whereDate('BETWEEN', new \Sql(WorkingTime::model()->format($firstDate).' AND '.WorkingTime::model()->format($lastDate)))
			->getCollection(NULL, NULL, ['user', 'date']);

		foreach($cUser as $eUser) {

			$cWorkingTimeUser = $cWorkingTime->offsetExists($eUser['id']) ? $cWorkingTime[$eUser['id']] : new \Collection();

			$eUser['workingTime'] = [];
			$eUser['cWorkingTime'] = new \Collection();

			foreach($dates as $date) {

				$eWorkingTime = $cWorkingTimeUser->offsetExists($date) ? $cWorkingTimeUser[$date] : new WorkingTime();

				$eUser['cWorkingTime'][$date] = $eWorkingTime;
				$eUser['workingTime'][$date] = $eWorkingTime->empty() ? NULL : $eWorkingTime['time'];

			}

		}

	}

	/**
	 * Sous horaires, la durée est le calcul des horaires, et c'est elle que lisent le planning, les
	 * analyses et le compteur d'heures.
	 */
	public static function prepareTime(WorkingTime $e): void {

		$e->expects(['start', 'end', 'pause']);

		if($e['start'] === NULL) {
			return;
		}

		if($e['pause'] === NULL) {
			$e['pause'] = 0.0;
		}

		$e['time'] = WorkingTime::getRange($e['start'], $e['end']) - $e['pause'];

	}

	public static function create(WorkingTime $e): void {

		$e->expects(['structure', 'user', 'date']);

		// Une journée saisie en durée n'a pas d'horaires : NULL efface ceux d'une saisie précédente du même
		// jour quand le doublon se met à jour.
		$e->add([
			'start' => NULL,
			'end' => NULL,
			'pause' => NULL
		]);

		self::prepareTime($e);

		$e->expects(['time']);

		if($e['time'] > 0.0) {

			try {
				WorkingTime::model()->insert($e);

			} catch(\DuplicateException) {

				unset($e['id']);

				// Une liste fixe et non getPropertiesCreate() : quel que soit le mode de saisie, la journée
				// réécrite remplace toute la précédente, durée et horaires.
				WorkingTime::model()
					->select(['time', 'start', 'end', 'pause'])
					->whereStructure($e['structure'])
					->whereUser($e['user'])
					->whereDate($e['date'])
					->update($e);

				// Sans ça l'appelant repart d'un create() avec un élément sans id, alors que la ligne
				// existe bien : ModuleApi::create() le lit pour rendre la ressource.
				$e['id'] = WorkingTime::model()
					->whereStructure($e['structure'])
					->whereUser($e['user'])
					->whereDate($e['date'])
					->getValue('id');

			}

		} else {

			WorkingTime::model()
				->whereStructure($e['structure'])
				->whereUser($e['user'])
				->whereDate($e['date'])
				->delete();

		}

	}

	/**
	 * Le temps de travail d'une personne sans compte passe au compte qui la remplace (`structure\MemberLib::transferGhost()`), qui n'en a aucun dans la structure (`TeammateLib::hasHistory()`).
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Écriture de masse : toutes les journées de la personne changent de titulaire, sans rien d'autre à recalculer.
		WorkingTime::model()
			->whereStructure($eStructure)
			->whereUser($eUserFrom)
			->update([
				'user' => $eUserTo
			]);

	}

}
