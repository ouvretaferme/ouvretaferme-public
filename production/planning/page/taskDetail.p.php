<?php
/**
 * La page d'une intervention du nouveau planning (L5b) : la saisie du temps de travail et de la récolte,
 * puis les commentaires, le détail de `/tache/{id}` et l'historique, à la suite. Elle s'ouvre
 * depuis la carte de l'écran de la semaine, qui passe sa semaine (`week`) pour que la réponse d'une saisie
 * de temps remette aussi la carte à jour.
 *
 * Le détail se prépare exactement comme sur `/tache/{id}` (`\series\TaskLib::fillDetail()`), et ses
 * formulaires restent ceux du planning actuel : leur fermeture recharge ce panneau, dont l'adresse est
 * celle de la page (V-Planning, écart b).
 *
 * Trois routes écrivent : `doTime`, le temps d'une personne un jour donné
 * (`\series\TimesheetLib::writeTimeForDay()`), `doUser`, l'affectation d'une personne, et `doHarvest`, une
 * récolte d'un jour. `week` montre une autre semaine dans le tableau du « Temps de travail » et `user` y ajoute
 * la ligne d'une personne, sans rien écrire.
 *
 * Le tableau dépend de deux choses que l'écran porte et que chaque geste réémet : la semaine montrée
 * (`weekShown`) et les lignes ajoutées par « Ajouter une personne » (`added`), que rien n'a encore écrites.
 */

/**
 * La semaine que le tableau montre. Le calendrier de `FormUi::weekSelector()` ne borne que l'année et propose
 * donc les semaines à venir de l'année en cours : elles montrent la semaine en cours, où le temps s'écrit
 * encore. Aucun geste ne propose une semaine illisible ni une semaine que le tableau ne montre pas
 * (`TaskDetailLib::hasWeek()`) : en recevoir une, c'est un appel fabriqué.
 */
$getWeekShown = function(\structure\Structure $eStructure): string {

	$weekShown = INPUT('weekShown', fn($value) => Filter::check('week', $value));

	if(\planning\TaskDetailLib::hasWeek($eStructure, $weekShown) === FALSE) {
		throw new NotExpectedAction('Invalid week');
	}

	return min($weekShown, \Clock::week());

};

/**
 * Les personnes dont la ligne a été ajoutée à la main. `TaskDetailLib::getUsers()` écarte celles qui n'ont
 * pas à y être.
 */
$getUserIdsAdded = fn(): array => array_filter(array_map('intval', explode(',', INPUT('added'))));

/**
 * Le tableau après une écriture, sur la semaine montrée : les personnes, ce que l'écriture y change d'après
 * celles d'avant (`TaskDetailLib::getChange()`) et les jours.
 */
$fillGrid = function(\stdClass $data, \Collection $cUserBefore): void {

	$data->eTask = \series\TaskLib::getById($data->eTask['id']);

	$data->cUser = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown, $data->userIdsAdded);
	$data->change = \planning\TaskDetailLib::getChange($cUserBefore, $data->cUser);

	$worked = \planning\TaskDetailLib::getWorked($data->cUser);
	$data->days = \planning\TaskDetailLib::getDays($data->weekShown, $worked);
	$data->foot = \planning\TaskDetailLib::getFoot($data->cUser, $data->days);

};

/**
 * La carte de l'écran de la semaine resté derrière le panneau, après une écriture : passée d'une zone à la
 * semaine ou l'inverse, elle n'est plus là où on la remplacerait, et l'écran se redemande entier
 * (`PlanningTaskZone.screen()`). `$zoneBefore` est la zone lue avant l'écriture (L8,
 * `TaskLateLib::getKeys()`). La page ouverte seule n'a pas de semaine derrière elle, donc pas de carte.
 */
$fillCard = function(\stdClass $data, ?string $zoneBefore): void {

	$data->screen = FALSE;
	$data->eTaskCard = new \series\Task();
	$data->cUserCard = new \Collection();

	if($data->week === NULL) {
		return;
	}

	$zoneAfter = \planning\TaskLateLib::getZoneOf($data->eStructure, $data->week, $data->eTask);

	if($zoneAfter !== $zoneBefore) {
		$data->screen = TRUE;
	} else if($zoneAfter !== NULL) {
		$data->eTaskCard = \planning\TaskLateLib::getTasks($data->eStructure, $zoneAfter, $data->week, [$data->eTask['id']])[$data->eTask['id']];
	} else {
		$data->eTaskCard = \planning\TaskDetailLib::getCard($data->eStructure, $data->week, $data->eTask);
	}

	$data->cUserCard = \hr\TeammateLib::getUsersForPlanning($data->eStructure);

};

/**
 * Le stock que la récolte de la fiche propose à chaque saisie (DG-4), dans l'unité de l'intervention.
 */
$fillStock = function(\stdClass $data): void {

	if($data->eTask['action']['fqn'] === ACTION_RECOLTE) {
		$data->eTask['eTaskStock'] = \planning\HarvestStockLib::fill(\planning\TaskDetailLib::getHarvestStock($data->eTask));
	}

};

new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eTask = \series\TaskLib::getById(INPUT('task', 'int'))
			->validate('canRead')
			->validateProperty('structure', $data->eStructure);

		$data->week = INPUT('week', fn($value) => Filter::check('?week', $value));

	})
	->get('/ferme/{id}/planning-v2/intervention/{task}', function($data) use($fillStock) {

		if($data->eTask['series']->empty()) {
			$data->cPlace = \series\PlaceLib::getByElement($data->eTask);
		} else {
			$data->cPlace = \series\PlaceLib::getByElement($data->eTask['series']);
		}

		\series\TaskLib::fillDetail($data->eTask, $data->cPlace, GET('sprayer', '?int'));

		// Le chiffre de la récolte est la somme des jours que la frise affiche, et non `Task::$harvest` : ce
		// compteur s'incrémente en flottant et s'écarte des jours d'un dixième, et une récolte antérieure aux
		// jours n'en porte aucun. Le chiffre ne contredit ainsi jamais la frise à sa droite.
		$data->eTask['harvestTotal'] = array_sum($data->eTask['harvestDates']);

		// La frise se lit du plus ancien au plus récent, et le regroupement par jour ne garantit aucun ordre.
		ksort($data->eTask['harvestDates']);

		$fillStock($data);

		$data->cPhoto = \gallery\PhotoLib::getByTask($data->eTask);
		$data->cComment = \series\CommentLib::getByTask($data->eTask);

		// Aucun lien du produit ne porte `weekShown` : la page s'ouvre sur la semaine d'ouverture. Le paramètre
		// sert au rechargement complet sur la semaine montrée, auquel l'empreinte compare les réponses ciblées
		// de `week`, de `doTime` et de `doUser` (Fingerprint.t.php). Une semaine illisible, que le tableau ne
		// montre pas ou à venir y retombe sur la semaine d'ouverture, comme sans paramètre : il n'y a pas d'écran
		// pour dire l'erreur.
		$data->weekShown = GET('weekShown', fn($value) => Filter::check('week', $value));

		if(\planning\TaskDetailLib::hasWeek($data->eStructure, $data->weekShown) === FALSE or $data->weekShown > \Clock::week()) {
			$data->weekShown = \planning\TaskDetailLib::getOpeningWeek($data->eTask);
		}

		$data->cUser = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown);

		$worked = \planning\TaskDetailLib::getWorked($data->cUser);
		$data->days = \planning\TaskDetailLib::getDays($data->weekShown, $worked);
		$data->foot = \planning\TaskDetailLib::getFoot($data->cUser, $data->days);
		$data->navigation = \planning\TaskDetailLib::getNavigation($data->eStructure, $data->weekShown);
		$data->timesByDay = \planning\TaskDetailLib::getTimesByDay($data->cUser);

		$data->history = \planning\TaskDetailLib::getHistory($data->eTask, $data->cComment);

		throw new ViewAction($data, ':taskDetail');

	})
	/**
	 * Une autre semaine dans le tableau du « Temps de travail », choisie par les flèches, le calendrier ou un
	 * jour saisi du dépli du total. Les lignes ajoutées à la main ne la suivent pas : elles valaient pour la
	 * semaine où on les a demandées. Un jour saisi nomme aussi son jour (`day`), que la réponse montre, même
	 * quand sa semaine est déjà celle du tableau. Un jour hors de la semaine montrée ne montre rien.
	 */
	->get('week', function($data) use($getWeekShown) {

		$data->eStructure->validate('hasTime');

		$data->weekShown = $getWeekShown($data->eStructure);

		$day = GET('day');
		$data->day = in_array($day, week_dates($data->weekShown), TRUE) ? $day : NULL;

		$data->cUser = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown);

		$data->days = \planning\TaskDetailLib::getDays($data->weekShown, \planning\TaskDetailLib::getWorked($data->cUser));
		$data->foot = \planning\TaskDetailLib::getFoot($data->cUser, $data->days);
		$data->navigation = \planning\TaskDetailLib::getNavigation($data->eStructure, $data->weekShown);

		throw new ViewAction($data, ':week');

	})
	/**
	 * La ligne d'une personne choisie dans « Ajouter une personne ». Rien ne s'écrit : la ligne vit sur
	 * l'écran, qui la réémet à chaque geste (`added`), jusqu'à ce qu'un temps ou une affectation la retienne.
	 * La liste ne propose que les personnes où un geste est possible (`addable`) : une autre, c'est un appel
	 * fabriqué.
	 */
	->get('user', function($data) use($getWeekShown, $getUserIdsAdded) {

		$data->eStructure->validate('hasTime');

		$data->weekShown = $getWeekShown($data->eStructure);

		$user = GET('user', 'int');

		$userIdsAdded = $getUserIdsAdded();

		$cUserBefore = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown, $userIdsAdded);

		if(
			$cUserBefore->offsetExists($user) === FALSE or
			$cUserBefore[$user]['addable'] === FALSE
		) {
			throw new NotExpectedAction('User can not be added');
		}

		$data->userIdsAdded = array_merge($userIdsAdded, [$user]);

		$data->cUser = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown, $data->userIdsAdded);

		$data->days = \planning\TaskDetailLib::getDays($data->weekShown, \planning\TaskDetailLib::getWorked($data->cUser));
		$data->foot = \planning\TaskDetailLib::getFoot($data->cUser, $data->days);

		throw new ViewAction($data, ':user');

	})
	/**
	 * Le temps d'une personne un jour donné. Le droit est celui du temps (`Timesheet::canWrite()`) : un
	 * administrateur écrit pour tous les membres, les autres pour eux seuls. Le jour est contrôlé par le
	 * schéma et par l'étape `future` de `Timesheet` : pas de temps dans un jour à venir. La durée est exigée
	 * (`time.missing`, liste `day`) : absente, elle effacerait le jour.
	 *
	 * La réponse redessine ce que l'écriture change (`TaskDetailLib::getChange()`), d'après les personnes lues
	 * avant et après elle sur la semaine que l'écran montre.
	 */
	->post('doTime', function($data) use($fillCard, $fillGrid, $getWeekShown, $getUserIdsAdded) {

		$data->eStructure->validate('hasTime');

		$data->weekShown = $getWeekShown($data->eStructure);
		$data->userIdsAdded = $getUserIdsAdded();

		$cUserMember = \hr\TeammateLib::getUsersForPlanning($data->eStructure);
		$user = POST('user', 'int');

		if($cUserMember->offsetExists($user) === FALSE) {
			throw new NotExpectedAction('User not in structure');
		}

		$fw = new FailWatch();

		$eTimesheet = \series\TimesheetLib::prepareDay($data->eStructure, $cUserMember[$user], $_POST);

		$fw->validate();

		$eTimesheet->validate('canWrite', 'acceptWrite');

		$cUserBefore = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown, $data->userIdsAdded);

		// Du temps saisi cette semaine sur une intervention en retard ou non planifiée la fait passer dans la
		// semaine, et l'effacer l'y renvoie (L8, `TaskLateLib::getKeys()`) : la zone se lit avant l'écriture.
		$zoneBefore = ($data->week !== NULL) ? \planning\TaskLateLib::getZoneOf($data->eStructure, $data->week, $data->eTask) : NULL;

		\series\TimesheetLib::writeTimeForDay($data->eTask, $eTimesheet);

		$fw->validate();

		$fillGrid($data, $cUserBefore);

		$data->eUserTime = $data->cUser[$user];
		$data->date = $eTimesheet['date'];
		$data->dateShown = (toWeek($data->date) === $data->weekShown);

		$data->timesByDay = \planning\TaskDetailLib::getTimesByDay($data->cUser);

		$data->history = \planning\TaskDetailLib::getHistory($data->eTask, \series\CommentLib::getByTask($data->eTask));

		$fillCard($data, $zoneBefore);

		throw new ViewAction($data, ':doTime');

	})
	/**
	 * L'affectation d'une personne, depuis sa ligne du tableau : `add` la lui donne, `delete` la lui retire.
	 * Les gardes sont celles de `/series/task:doUpdateUserCollection`, dont c'est l'écriture
	 * (`\series\TaskLib::updateUser()`) : une intervention à faire, que la personne connectée peut écrire, et
	 * un membre de la ferme. Le tableau ne propose le geste que pour une personne affectable (`canAssign`) :
	 * pour une autre, c'est un appel fabriqué. La réponse est celle d'une saisie de temps, sur les mêmes nœuds.
	 */
	->post('doUser', function($data) use($fillCard, $fillGrid, $getWeekShown, $getUserIdsAdded) {

		$data->eStructure->validate('hasTime');
		$data->eTask->validate('isTodo', 'canWrite');

		$data->weekShown = $getWeekShown($data->eStructure);
		$data->userIdsAdded = $getUserIdsAdded();

		$cUserMember = \hr\TeammateLib::getUsersForPlanning($data->eStructure);
		$user = POST('user', 'int');

		if($cUserMember->offsetExists($user) === FALSE) {
			throw new NotExpectedAction('User not in structure');
		}

		$action = POST('action', ['add', 'delete'], fn() => throw new NotExpectedAction('Invalid action'));

		$cUserBefore = \planning\TaskDetailLib::getUsers($data->eTask, $data->weekShown, $data->userIdsAdded);

		if($cUserBefore[$user]['canAssign'] === FALSE) {
			throw new NotExpectedAction('User can not be assigned');
		}

		$zoneBefore = ($data->week !== NULL) ? \planning\TaskLateLib::getZoneOf($data->eStructure, $data->week, $data->eTask) : NULL;

		\series\TaskLib::updateUser(new Collection([$data->eTask]), $cUserMember[$user], $action);

		$fillGrid($data, $cUserBefore);

		$data->eUserAssigned = $data->cUser[$user];

		$fillCard($data, $zoneBefore);

		throw new ViewAction($data, ':doUser');

	})
	/**
	 * Une récolte d'un jour, depuis la dernière ligne de la récolte de la fiche (R-Retours-0710, 155), ou la
	 * modification d'une ligne (R-Retours-0810, 207), et son stock (`TaskDetailLib::writeHarvest()`).
	 *
	 * La réponse redessine la récolte de la fiche, l'historique, où la récolte vient d'écrire son écart, et la carte.
	 */
	->post('doHarvest', function($data) use($fillCard, $fillStock) {

		$data->eTask->validate('canWrite');

		$zoneBefore = ($data->week !== NULL) ? \planning\TaskLateLib::getZoneOf($data->eStructure, $data->week, $data->eTask) : NULL;

		\planning\TaskDetailLib::writeHarvest($data->eTask, $_POST);

		$data->eTask = \series\TaskLib::getById($data->eTask['id']);

		\series\TaskLib::fillHarvestDates($data->eTask);
		$data->eTask['harvestTotal'] = array_sum($data->eTask['harvestDates']);

		// La réponse redessine la frise du premier rendu, rangée de même (plus haut).
		ksort($data->eTask['harvestDates']);

		$fillStock($data);

		$data->history = \planning\TaskDetailLib::getHistory($data->eTask, \series\CommentLib::getByTask($data->eTask));

		$fillCard($data, $zoneBefore);

		throw new ViewAction($data, ':doHarvest');

	});
?>
