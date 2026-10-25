<?php
namespace planning;

/**
 * Le temps d'une équipe sur une intervention qu'un autre écran crée faite, en un geste : les personnes
 * qui l'ont faite et la durée de chacune, un seul jour (planche production-finir-temps-bloc, retour de
 * du 04/10 : « il faut pouvoir saisir un temps de travail par personne »). Plusieurs jours se
 * reprennent au planning, sur l'intervention.
 *
 * Premier appelant : « Entretien fait » du carnet (`toolbox\MaintenanceTaskLib`). L'appelant construit
 * le temps avec son élément (`prepare()`), puis l'écrit dans sa transaction une fois l'intervention
 * créée (`create()`).
 */
class TimeTeamLib {

	/**
	 * Les personnes dont on peut écrire le temps, celles que le bloc propose : la règle de `Timesheet`,
	 * toute l'équipe pour qui gère la structure, sa propre personne sinon. Vide quand la structure ne
	 * suit pas le temps : le bloc ne s'affiche pas.
	 */
	public static function getUsers(\structure\Structure $eStructure): \Collection {

		if($eStructure->hasTime() === FALSE) {
			return new \Collection();
		}

		return \hr\TeammateLib::getUsersForPlanning($eStructure)->find(fn(\user\User $eUser) => new \series\Timesheet([
			'structure' => $eStructure,
			'user' => $eUser
		])->assertWrite());

	}

	/**
	 * Les personnes dont on peut écrire le temps dans une semaine du planning, celles que propose
	 * « Ajouter une personne » (retour 254, « n'afficher que les gens au planning ») : les personnes
	 * présentes dans la semaine, comme la fiche de temps (`TimeDayLib::getUsers()`), la personne connectée,
	 * qui écrit toujours son propre temps, et celles qui ont déjà du temps sur une intervention de
	 * `$cTask`, dont le temps saisi ne doit pas disparaître de l'écran qui le corrige. Seules restent les personnes de
	 * l'équipe (`hr\Teammate`), celles qui ont un accès et les personnes sans compte, dont le temps peut
	 * s'écrire : un temps survit au départ de la personne, et `Timesheet::canWrite()` laisse qui gère la
	 * structure écrire le temps de n'importe qui.
	 */
	public static function getUsersForWeek(\structure\Structure $eStructure, string $week, \Collection $cTask = new \Collection()): \Collection {

		if($eStructure->hasTime() === FALSE) {
			return new \Collection();
		}

		$cUser = \structure\MemberLib::getUsersByStructureForPeriod($eStructure, week_date_starts($week), week_date_ends($week));

		$eUserOnline = \user\ConnectionLib::getOnline();

		// Une copie : les écrans posent sur chaque personne de la liste ce qu'ils en affichent, et l'élément de la
		// connexion sert à toute la requête.
		if($cUser->offsetExists($eUserOnline['id']) === FALSE) {
			$cUser[$eUserOnline['id']] = clone $eUserOnline;
		}

		if($cTask->notEmpty()) {

			$cUser->appendCollection(
				\series\Timesheet::model()
					->select([
						'user' => \user\User::getSelection()
					])
					->whereStructure($eStructure)
					->whereTask('IN', $cTask)
					->getCollection()
					->getColumnCollection('user', 'user')
			);

		}

		$cUser->sort([
			'firstName' => SORT_ASC,
			'lastName' => SORT_ASC
		]);

		// L'équipe propose les personnes de la semaine. Une personne qui a déjà du temps sur une intervention reste
		// tant qu'elle a un accès, même hors de l'équipe : son temps se corrige ici. Une personne sans compte est
		// rattachée sortie (`MemberLib::createGhostUser()`), et seul son rôle la distingue d'un membre parti.
		$cUserKept = \hr\TeammateLib::getUsersByStructure($eStructure)
			->appendCollection(\structure\MemberLib::getUsersByStructure($eStructure))
			->appendCollection(\structure\MemberLib::getGhostByStructure($eStructure)->getColumnCollection('user', 'user'));

		return $cUser->find(fn(\user\User $eUser) => (
			$cUserKept->offsetExists($eUser['id']) and
			new \series\Timesheet([
				'structure' => $eStructure,
				'user' => $eUser
			])->assertWrite()
		));

	}

	/**
	 * Construit un temps par personne depuis `time[<user>]` (en heures), au jour `$date`, par `build()`
	 * de `Timesheet`. Le temps est facultatif (04/10) : une personne sans durée n'en reçoit
	 * aucun, et sans aucune durée l'intervention se crée sans temps. Une personne que le bloc ne propose
	 * pas (`$cUser`, la liste que l'appelant a montrée) est refusée, comme le planning refuse le temps
	 * d'un autre.
	 *
	 * Les 24 heures d'une journée se vérifient ici et non à l'écriture : `TimesheetLib::writeByTasks()`
	 * annule toute la pile de transactions sur un refus, celle de l'appelant comprise.
	 *
	 * `buildIndex()` nomme les refus `time[<user>]`, le wrapper de la personne sous laquelle l'écran les
	 * affiche ; celui d'une personne hors du bloc n'a pas de place et porte le wrapper du bloc.
	 */
	public static function prepare(\structure\Structure $eStructure, \Collection $cUser, string $date, array $input): \Collection {

		$cTimesheet = new \Collection();

		$times = $input['time'] ?? [];

		if($cUser->empty() or is_array($times) === FALSE) {
			return $cTimesheet;
		}

		foreach($times as $user => $time) {

			$eTimesheet = new \series\Timesheet([
				'structure' => $eStructure
			]);

			// Une liste par personne : un refus marque la sienne, et la suivante se construit sur une liste neuve.
			$l = new \PropertyList('create');

			$eTimesheet->buildIndex(['user', 'time', 'date'], [
				'user' => [$user => $user],
				'time' => [$user => $time],
				'date' => [$user => $date]
			], $user, $l);

			if($l->isInvalid('user') or $l->isInvalid('time') or $l->isInvalid('date')) {
				continue;
			}

			// Deux clés qui désignent la même personne (`time[41736]` et `time[041736]`) feraient passer chacune sous
			// les 24 heures, comptées séparément : une personne n'a qu'une durée.
			if(
				$eTimesheet['user']->empty() or
				$cUser->offsetExists($eTimesheet['user']['id']) === FALSE or
				$cTimesheet->offsetExists($eTimesheet['user']['id'])
			) {
				\series\Timesheet::fail('user.check', wrapper: 'timeTeam');
				continue;
			}

			if($eTimesheet['time'] <= 0.0) {
				continue;
			}

			if(round(\series\TimesheetLib::getTimeByUserDate($eStructure, $eTimesheet['user'], $date) + $eTimesheet['time'], 2) > 24.0) {
				\series\Timesheet::fail('time.tooLong', wrapper: 'time['.$eTimesheet['user']['id'].']');
				continue;
			}

			$cTimesheet[$eTimesheet['user']['id']] = $eTimesheet;

		}

		return $cTimesheet;

	}

	/**
	 * Le temps de plusieurs personnes sur une intervention un même jour, depuis `time[<user>]` (en heures), tel
	 * que l'écrit l'« Enregistrer » commun du menu d'un jour de la grille (ET, retour 398). Chaque durée
	 * remplace le temps de la personne sur l'intervention ce jour-là, 0 compris, qui l'efface, comme
	 * `TimesheetLib::writeTimeForDay()`. Une personne qui n'a pas de saisie dans le menu (`$cUser`, les lignes
	 * à `canTime` de `TaskDetailLib::getUsers()`) est refusée, comme le planning refuse le temps d'un autre.
	 *
	 * Tout se vérifie ici, la borne des 24 heures comprise (`TimesheetLib::exceedsDay()`), avant que
	 * `writeDay()` n'écrive la première durée : un refus de l'une n'en laisse aucune écrite.
	 *
	 * `buildIndex()` nomme les refus `time[<user>]`, le wrapper de la saisie de la personne dans le menu.
	 */
	public static function prepareDay(\structure\Structure $eStructure, \series\Task $eTask, \Collection $cUser, string $date, array $input): \Collection {

		$cTimesheet = new \Collection();

		$times = $input['time'] ?? [];

		if(is_array($times) === FALSE) {
			\series\Timesheet::fail('time.check');
			return $cTimesheet;
		}

		foreach($times as $user => $time) {

			$eTimesheet = new \series\Timesheet([
				'structure' => $eStructure
			]);

			// Une liste par personne : un refus marque la sienne, et la suivante se construit sur une liste neuve.
			// `day` exige la durée, qu'une valeur absente ne vaille pas zéro, qui effacerait le jour.
			$l = new \PropertyList('day');

			$eTimesheet->buildIndex(['user', 'time', 'date'], [
				'user' => [$user => $user],
				'time' => [$user => $time],
				'date' => [$user => $date]
			], $user, $l);

			if($l->isInvalid('user') or $l->isInvalid('time') or $l->isInvalid('date')) {
				continue;
			}

			// Deux clés qui désignent la même personne (`time[41736]` et `time[041736]`) écriraient deux fois sa
			// journée : une personne n'a qu'une durée.
			if(
				$eTimesheet['user']->empty() or
				$cUser->offsetExists($eTimesheet['user']['id']) === FALSE or
				$cUser[$eTimesheet['user']['id']]['canTime'] === FALSE or
				$cTimesheet->offsetExists($eTimesheet['user']['id'])
			) {
				\series\Timesheet::fail('user.check', wrapper: 'time['.$user.']');
				continue;
			}

			$eTimesheet->validate('canWrite', 'acceptWrite');

			if(\series\TimesheetLib::exceedsDay($eTask, $eTimesheet)) {
				\series\Timesheet::fail('time.tooLong', wrapper: 'time['.$eTimesheet['user']['id'].']');
				continue;
			}

			$cTimesheet[$eTimesheet['user']['id']] = $eTimesheet;

		}

		return $cTimesheet;

	}

	/**
	 * Écrit le temps que `prepareDay()` a construit et vérifié, en une transaction : l'« Enregistrer » commun
	 * écrit toutes les durées ou aucune. Entre les deux, une écriture concurrente peut avoir rempli la journée
	 * (`time.tooLong`) ou retiré du temps (`time.negative`), et `writeTimeForDay()` refuse alors la durée sans
	 * lever. Le refus annule tout ce que la boucle a déjà écrit et remonte par une exception : `rollBack()`
	 * vide toute la pile de transactions (`Module::rollBack()`), et rendre la main laisserait l'appelant
	 * écrire hors de la sienne.
	 */
	public static function writeDay(\series\Task $eTask, \Collection $cTimesheet): void {

		$fw = new \FailWatch();

		\series\Timesheet::model()->beginTransaction();

		foreach($cTimesheet as $eTimesheet) {

			\series\TimesheetLib::writeTimeForDay($eTask, $eTimesheet);

			if($fw->ok() === FALSE) {
				\series\Timesheet::model()->rollBack();
				throw new \FailAction($fw);
			}

		}

		\series\Timesheet::model()->commit();

	}

	/**
	 * Écrit le temps de chaque personne sur l'intervention que l'appelant vient de créer, dans sa
	 * transaction.
	 */
	public static function create(\series\Task $eTask, \Collection $cTimesheet): void {

		foreach($cTimesheet as $eTimesheet) {
			\series\TimesheetLib::writeTimeForDay($eTask, $eTimesheet);
		}

	}

	/**
	 * Écrit le temps de chaque personne sur les interventions que l'appelant vient de créer ensemble, dans sa
	 * transaction (« Nouvelle intervention » faite sur plusieurs séries, retour 245). Le temps saisi est celui
	 * de la personne, compté une fois : il se partage entre les interventions (`TaskLib::distribute()`), jamais
	 * il ne se répète sur chacune (arbitrage V-Planning du 11/10). Le partage est égal, sauf pour une récolte
	 * de plusieurs séries, qui prend la clé de sa quantité (DG-5, contrat de DG : une seule clé), validée par
	 * l'appelant (`TaskBatchLib::getHarvestDistribution()`) : `writeByTasks()` refuserait une clé sans base
	 * sans rien annuler de ce qui est déjà écrit. La journée de 24 heures a été vérifiée sur ce temps entier
	 * par `prepare()`.
	 */
	public static function createForTasks(\Collection $cTask, \Collection $cTimesheet, string $distribution = 'fair'): void {

		foreach($cTimesheet as $eTimesheet) {
			\series\TimesheetLib::writeByTasks($cTask, $eTimesheet, $eTimesheet['time'], $distribution);
		}

	}

}
?>
