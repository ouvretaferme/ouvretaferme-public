<?php
/**
 * La fiche de temps (L6b) : un jour de la semaine et une personne, sa journée de travail, le temps qu'elle
 * a passé sur les interventions et ce qui reste hors interventions, puis les interventions de la semaine,
 * celles du jour d'abord, le reste dessous (`TimeDayLib`).
 *
 * Tout ce qui s'écrit ici s'écrit au nom de la personne de l'onglet (Q2, tranchée par V-Planning), par
 * les méthodes du planning actuel : la journée par `\hr\WorkingTimeLib::create()`, le temps d'une
 * intervention par `\series\TimesheetLib::writeTimeForDay()`, la coche et la décoche par celles de la
 * Semaine (charte, point 10 c). Le droit est celui de chacune : la journée d'un autre comme son temps sur
 * une intervention se saisissent avec la gestion de la ferme (`WorkingTime::canWrite()`,
 * `Timesheet::canWrite()`). « Nouvelle intervention » fait exception (`doCreate`, retour 293) : son
 * panneau est celui des Interventions, où le temps se saisit par personne, et il écrit celui de chaque
 * personne postée dont on a le droit d'écrire le temps.
 *
 * Le jour (`date`) et la personne (`user`) sont des paramètres d'adresse, que chaque geste réémet pour
 * que sa réponse dessine la fiche que l'on regarde ; `user=team` ouvre la fiche de l'équipe, où seule la
 * journée de chacun se saisit, sur sa ligne (R158) ; « Masquer ce qui est fait » et « Uniquement assigné
 * à moi » sont des préférences du membre, relues à chaque requête comme dans la Semaine. Chaque geste
 * répond par ce qu'il change : les chiffres de la tête, la journée sous la pastille du jour, et la ligne
 * touchée, remplacée à la place que l'écran lui donnait (`TimeDayLib::getChange()`).
 *
 * Sous la liste, les zones « En retard » et « Non planifié » des Interventions (CF, `TimeDayLib::getZones()`),
 * dont les lignes se saisissent comme celles de la semaine. « Non planifié » ne se lit qu'à sa première
 * ouverture (`timeDay:unplanned`), ce que le client réémet (`unplanned`), avec les zones repliées
 * (`folded`), les blocs dépliés (`expanded`) et les tables qui ont la colonne des commentaires
 * (`commented`) et les lignes qu'un geste a gardées à leur place dans une zone (`retained`) : une ligne
 * qui entre dans une zone ou en sort s'y place comme l'écran la montre, sans que la réponse redessine la zone.
 */

/**
 * La fiche après un geste, avec l'intervention gardée même masquée ou sortie de la fiche, ou celles que
 * le geste vient de créer.
 */
function planningTimeDayRead(\stdClass $data, \series\Task $eTaskKept = new \series\Task(), ?string $dayWeekKept = NULL, ?string $zoneKept = NULL, bool $actionFirstKept = FALSE, \Collection $cTaskCreated = new \Collection()): void {

	// Le suivi RH sans temps de travail ne montre que les journées (`TimeDayLib::get()`).
	$data->cTask = $data->eStructure->hasTime() ? \planning\TaskWeekLib::get($data->eStructure, $data->week) : new Collection();
	$data->timeDay = \planning\TimeDayLib::get($data->eStructure, $data->week, $data->cTask, $data->date, $data->eUserTime, $data->hide, $data->mine, $eTaskKept, $dayWeekKept, $zoneKept, $actionFirstKept, $cTaskCreated);

	// Le contrat est de la RH, qui le ferme éteinte, et ne se lit entier que sous son droit : la page le vérifie avant
	// de le passer, l'écran ne fait que montrer ce qu'elle en tire. Ses jours bornent les jours à saisir pour la
	// personne et pour qui lit son contrat (19/10), jamais pour un collègue qui ouvre sa fiche : ils
	// diraient un temps partiel.
	$canReadContract = new \hr\Contract(['structure' => $data->eStructure])->canRead();

	if(
		$data->eStructure->acceptHr() and
		($canReadContract or $data->eUserTime->isOnline())
	) {
		$eContract = \hr\OvertimeLib::getContractByWeek($data->eStructure, $data->eUserTime, $data->week);
	} else {
		$eContract = new \hr\Contract();
	}

	$contractDays = \planning\TimeDayLib::getContractDays($eContract);

	if($canReadContract === FALSE) {
		$eContract = new \hr\Contract();
	}

	$data->entry = \planning\TimeDayLib::getEntry($data->timeDay['days'], $contractDays, $eContract);

}

/**
 * Le jour d'une ligne avant un geste : celui où l'écran la range, que la ligne réémet (`dayWeek`, R-Place),
 * et sans lui celui que lui donnerait un rechargement, lu sur l'intervention seule avant que le geste
 * n'écrive. Le jour réémis ne peut pas écrire, seulement placer la ligne dans la réponse : une date hors
 * de la semaine est forgée.
 */
function planningTimeDayPosition(\stdClass $data, \series\Task $eTask): ?string {

	if(post_exists('dayWeek') === FALSE) {
		return \planning\TimeDayLib::getDayWeek($eTask, $data->week);
	}

	$dayWeek = POST('dayWeek');

	if($dayWeek === '') {
		return NULL;
	}

	if(in_array($dayWeek, week_dates($data->week), TRUE) === FALSE) {
		throw new NotExpectedAction('Invalid position');
	}

	return $dayWeek;

}

/**
 * Un jour à venir n'a pas de zones, comme il n'a pas de liste : on n'y saisit pas de temps. Sans temps de travail
 * non plus : les zones rangent des interventions, dont seul le temps de travail ouvre le temps.
 */
function planningTimeDayHasZones(\stdClass $data): bool {
	return (
		$data->eStructure->hasTime() and
		$data->date <= \Clock::date()
	);
}

/**
 * Les deux zones sous la liste, aucune un jour à venir.
 */
function planningTimeDayZones(\stdClass $data): void {

	if(planningTimeDayHasZones($data) === FALSE) {
		$data->zones = [];
		return;
	}

	$data->zones = \planning\TimeDayLib::getZones($data->eStructure, $data->week, $data->eUserTime, $data->mine, $data->unplanned, $data->folded, $data->expanded);

}

/**
 * Les actions dépliées par leur « Voir les N autres », par zone, que l'écran réémet sous la forme
 * `late-12,unplanned-3` (`expanded`). Une valeur d'une autre forme ne déplie rien, comme une zone inconnue
 * dans `folded`.
 */
function planningTimeDayExpanded(): array {

	$expanded = [
		\planning\TaskLateLib::LATE => [],
		\planning\TaskLateLib::UNPLANNED => []
	];

	foreach(explode(',', INPUT('expanded', 'string', '')) as $value) {

		$parts = explode('-', $value);

		if(
			count($parts) === 2 and
			array_key_exists($parts[0], $expanded) and
			ctype_digit($parts[1])
		) {
			$expanded[$parts[0]][] = (int)$parts[1];
		}

	}

	return $expanded;

}

/**
 * Les lignes qu'un geste a gardées à leur place dans une zone alors que la base ne les y compte plus
 * (R-Place), par zone et par intervention, que l'écran réémet sous la forme `late-12-345-1` (`retained`) :
 * la zone, l'action, l'intervention, et si la ligne ouvre son bloc. Elles ne font que dire à la réponse ce
 * que l'écran montre : une valeur forgée ne retire que des lignes de l'écran de qui la forge. Une valeur
 * d'une autre forme n'est pas lue, comme dans `expanded`.
 */
function planningTimeDayRetained(): array {

	$retained = [
		\planning\TaskLateLib::LATE => [],
		\planning\TaskLateLib::UNPLANNED => []
	];

	foreach(explode(',', INPUT('retained', 'string', '')) as $value) {

		$parts = explode('-', $value);

		if(
			count($parts) === 4 and
			array_key_exists($parts[0], $retained) and
			ctype_digit($parts[1]) and
			ctype_digit($parts[2]) and
			in_array($parts[3], ['0', '1'], TRUE)
		) {

			$retained[$parts[0]][(int)$parts[2]] = [
				'action' => (int)$parts[1],
				'task' => (int)$parts[2],
				'first' => ($parts[3] === '1')
			];

		}

	}

	return $retained;

}

/**
 * La zone d'où vient une ligne (CF), que la ligne réémet avec son rang dans son action (`actionFirst`),
 * `NULL` pour une ligne de la semaine. Les deux ne font que placer la ligne dans la réponse, comme son jour
 * (`planningTimeDayPosition()`) : le geste la fait souvent sortir de sa zone, qui ne peut plus dire où elle
 * était.
 */
function planningTimeDayZone(): ?string {

	$zone = POST('zone', '?string');

	if($zone === NULL or $zone === '') {
		return NULL;
	}

	if(in_array($zone, [\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED], TRUE) === FALSE) {
		throw new NotExpectedAction('Invalid zone');
	}

	return $zone;

}

/**
 * La réponse d'un geste sur une ligne : la fiche relue, la ligne à la place qu'elle avait, et ce que le
 * geste change dans la table que l'écran affiche, avec ou sans la colonne des commentaires (`columns`,
 * réémis par le geste). Une zone qui change n'y touche que ses nœuds (`zoneChanges`,
 * `TimeDayLib::getZoneSteps()`), quels que soient les blocs que l'écran a dépliés.
 *
 * Une ligne de zone reste à sa place après un geste (R-Place), même quand la base ne la compte plus dans sa
 * zone : l'écran la retient (`retained`) jusqu'à ce que sa zone change ou que la liste se redessine, qui la
 * montre alors à sa place dans la semaine.
 *
 * La liste se redessine avec la rangée des outils (`listChanged`) quand le geste change la semaine ailleurs
 * que sur sa ligne : `TimeDayUi::getTools()` et `getList()` se décident sur la semaine entière, ce qu'elle
 * porte et ce que les filtres en masquent, et une instruction sur une ligne ou sur la table ne peut pas les
 * suivre.
 */
function planningTimeDayRespond(\stdClass $data, \series\Task $eTask, ?string $dayWeekBefore, string $gesture, ?string $zone, bool $actionFirst): void {

	$data->eTask = $eTask;
	$data->gesture = $gesture;
	$data->commented = (POST('columns', 'int') === 3);
	$data->zoneChanges = [];
	$data->listChanged = FALSE;
	$data->canCreate = new \series\Task(['structure' => $data->eStructure])->canCreate();
	$data->retained = planningTimeDayRetained();

	$retained = $data->retained;

	planningTimeDayRead($data, $eTask, $dayWeekBefore, $zone, $actionFirst);

	if($zone === NULL) {

		$data->change = \planning\TimeDayLib::getChange($data->timeDay, $eTask, $data->commented);
		$data->eTaskLine = \planning\TimeDayLib::getLine($data->timeDay, $eTask);

		// Le geste part d'une ligne, donc d'une table : une semaine relue sans ligne fait passer la liste à
		// l'état vide, qui n'est pas une table et que la rangée des outils suit.
		$data->listChanged = ($data->change === 'table' and $data->timeDay['ccTaskWeek']->empty());

		if($data->change === 'table' and planningTimeDayHasZones($data)) {

			// Une ligne que la semaine relue ne porte plus en est sortie, souvent pour une zone.
			$zoneArrival = $data->eTaskLine->empty() ? \planning\TaskLateLib::getZoneOf($data->eStructure, $data->week, $eTask) : NULL;

			foreach([\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED] as $name) {

				if($name === $zoneArrival) {
					planningTimeDayZoneSteps($data, $name, planningTimeDayCommented(), $data->retained[$name], $eTask);
				} else if($data->retained[$name] !== []) {
					planningTimeDayZoneSteps($data, $name, planningTimeDayCommented(), $data->retained[$name]);
				}

			}

		}

		$data->retainedChanged = ($data->retained !== $retained);

		return;

	}

	$data->eTaskLine = $data->timeDay['eTaskZoneKept'];

	// Hors de la semaine relue, l'intervention n'a pas de temps cette semaine (`TaskLateLib::getTaskKept()`).
	if($data->eTaskLine->empty()) {

		$eTaskKept = \planning\TaskLateLib::getTaskKept($data->eStructure, $eTask);

		if($eTaskKept->notEmpty()) {
			$data->eTaskLine = \planning\TimeDayLib::getZoneLine($eTaskKept, 0.0, $actionFirst);
		}

	}

	$data->change = \planning\TimeDayLib::getChangeZone($data->eTaskLine, $data->commented);

	$data->retained[$zone][$eTask['id']] = [
		'action' => $eTask['action']['id'],
		'task' => $eTask['id'],
		'first' => $actionFirst
	];

	if($data->change === 'variant') {
		$data->zoneChanges[] = \planning\TimeDayLib::getZoneVariant($zone, $data->expanded, $data->eTaskLine);
	} else if($data->change === 'remove') {

		// Une zone qui change lâche toutes ses lignes retenues, pas seulement celle du geste. Celles que la
		// semaine relue porte, pour un temps ou une coche, n'ont plus que la liste pour les montrer ; d'une ligne
		// que la semaine ne porte pas, la liste ne reçoit rien, où qu'elle aille.
		$data->listChanged = (array_intersect(array_keys($data->retained[$zone]), $data->cTask->getIds()) !== []);

		planningTimeDayZoneSteps($data, $zone, $data->commented ? [$zone] : [], $data->retained[$zone]);

		// La liste redessinée rend toute la semaine, donc aussi les lignes que l'autre zone retient : celle-ci
		// les lâche avec elle, comme quand un geste de la liste la redessine.
		if($data->listChanged) {

			foreach([\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED] as $name) {

				if($name !== $zone and $data->retained[$name] !== []) {
					planningTimeDayZoneSteps($data, $name, planningTimeDayCommented(), $data->retained[$name]);
				}

			}

		}

	}

	$data->retainedChanged = ($data->retained !== $retained);

}

/**
 * Une zone ramenée de ce que l'écran en montre à ce qu'un rechargement en montrerait : ses lignes retenues
 * en sortent toutes, donc l'écran n'en retient plus aucune.
 */
function planningTimeDayZoneSteps(\stdClass $data, string $name, array $commented, array $retained, \series\Task $eTaskArrival = new \series\Task()): void {

	$zoneChange = \planning\TimeDayLib::getZoneSteps($data->eStructure, $data->week, $data->eUserTime, $data->mine, $name, $data->unplanned, $data->folded, $data->expanded[$name], in_array($name, $commented, TRUE), $retained, $eTaskArrival);

	if($zoneChange['steps'] !== []) {
		$data->zoneChanges[] = $zoneChange;
	}

	$data->retained[$name] = [];

}

/**
 * Les zones dont la table a la colonne des commentaires, que l'écran réémet (`commented`), pour qu'une
 * ligne qui y entre se rende dans la variante de la table qui la reçoit. Une zone inconnue n'est pas lue,
 * comme dans `folded`.
 */
function planningTimeDayCommented(): array {
	return array_values(array_intersect(explode(',', POST('commented', 'string', '')), [\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED]));
}

/**
 * Les gestes de la fiche sur les interventions portent sur leur temps, que seul le temps de travail ouvre : sans
 * lui, l'onglet n'a que la journée (`doWorkingTime`) ou se lit seulement (`getWithoutTime()`).
 */
function planningTimeDayGesture(\stdClass $data): void {

	$data->eStructure->validate('hasTime');

	planningTimeDayNotTeam($data);

}

/**
 * Aucun geste n'écrit au nom de l'équipe, qui n'est personne : la journée saisie sur une ligne de
 * l'équipe porte la personne de la ligne et `origin=team`, jamais `user=team`. Une adresse qui le porte
 * est forgée.
 */
function planningTimeDayNotTeam(\stdClass $data): void {

	if($data->team) {
		throw new NotExpectedAction('No gesture on the team');
	}

}

/**
 * L'équipe de la semaine, chaque personne avec le droit d'écrire sa journée le jour de la fiche
 * (`canWorking`), que sa ligne lit pour s'ouvrir ou se lire seulement (R158), et les personnes du tableau
 * du jour (`cUserPresent`, `TimeWeekLib::getPresents()`). Les absents de chaque jour sont portés par le jour
 * de l'équipe (`cUserAbsent`, `TimeWeekLib::read()`), sous sa pastille (R-Retours-1210, FS).
 */
function planningTimeDayTeam(\stdClass $data): void {

	$data->timeWeek = \planning\TimeWeekLib::read($data->eStructure, $data->week);

	$data->cUserPresent = \planning\TimeWeekLib::getPresents($data->timeWeek['cUserTime'], $data->date);

	foreach($data->timeWeek['cUserTime'] as $eUser) {
		$eUser['canWorking'] = \planning\TimeDayLib::canWorking($data->eStructure, $eUser, $data->date);
	}

}

new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canPlanningLimited');

		$data->week = INPUT('week', fn($value) => Filter::check('week', $value), \Clock::week());

		if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($data->week)) === FALSE) {
			throw new NotExpectedAction('Invalid week');
		}

		if(input_exists('date')) {

			$data->date = INPUT('date', fn($value) => in_array($value, week_dates($data->week), TRUE));

			if($data->date === NULL) {
				throw new NotExpectedAction('Date out of the week');
			}

		} else {
			$data->date = \planning\TimeDayLib::getDefaultDate($data->week);
		}

		$data->eMember = $data->eStructure->getMember();
		$data->hide = $data->eStructure->getView('viewPlanningHideDone');
		$data->mine = $data->eStructure->getView('viewPlanningMine');

		$data->unplanned = INPUT('unplanned', 'bool');
		$data->folded = array_values(array_intersect(explode(',', INPUT('folded', 'string', '')), [\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED]));
		$data->expanded = planningTimeDayExpanded();

		$data->cUser = \planning\TimeDayLib::getUsers($data->eStructure, $data->week);

		$data->team = (INPUT('user') === \planning\TimeWeekLib::TEAM);
		$data->weekly = FALSE;

		// La semaine se choisit à la place d'un jour, pour l'équipe comme pour une personne (R-Retours-1210,
		// 326) : l'adresse ne porte pas les deux. Sans l'un ni l'autre, une semaine passée s'ouvre sur la semaine.
		if(input_exists('period')) {

			if(INPUT('period') !== \planning\TimeWeekLib::WEEK or input_exists('date')) {
				throw new NotExpectedAction('Invalid period');
			}

			$data->weekly = TRUE;

		} else if(input_exists('date') === FALSE) {
			$data->weekly = \planning\TimeDayLib::isWeeklyDefault($data->week);
		}

		if($data->team) {

			if($data->cUser->count() < 2) {
				throw new NotExpectedAction('No team under two users');
			}

			// Pas de `canWrite()` sur une personne vide, qui répond oui à qui gère la ferme.
			$data->eUserTime = new \user\User();
			$data->canWorking = FALSE;
			$data->canTime = FALSE;

			return;

		}

		$user = INPUT('user', '?int') ?? \user\ConnectionLib::getOnline()['id'];

		if($data->cUser->offsetExists($user) === FALSE) {
			throw new NotExpectedAction('User not in the time sheet');
		}

		$data->eUserTime = $data->cUser[$user];

		// Un geste part d'un jour choisi, que sa réponse garde : seule la semaine en champs peut partir de la semaine
		// choisie, et le dit (`doWorkingTime`).
		$data->dateSelected = $data->date;

		$data->canWorking = \planning\TimeDayLib::canWorking($data->eStructure, $data->eUserTime, $data->date);

		$data->canTime = new \series\Timesheet(['structure' => $data->eStructure, 'user' => $data->eUserTime])->assertWrite();

	})
	/**
	 * ⚠️ Une écriture dans un GET, déclarée comme dans la Semaine (`taskWeek.p.php`) : les interventions
	 * répétées de la semaine se matérialisent avant d'être lues.
	 */
	->get(['/ferme/{id}/planning-v2/temps', '/ferme/{id}/planning-v2/temps/{week@week}'], function($data) {

		// Sans temps de travail ni suivi RH, l'onglet garde les jours et les absences (décidé le 17/10), et
		// aucune lecture du temps ne part. Le suivi RH seul ouvre ses journées, sans les interventions (18/10).
		if($data->eStructure->hasWorkingTime() === FALSE) {

			$data->days = \planning\TimeWeekLib::readAbsences($data->cUser, $data->week);

			if($data->team) {
				$data->cAbsenceWeek = $data->eStructure->acceptHr() ?
					\hr\AbsenceLib::getValidatedBetween($data->eStructure, week_date_starts($data->week), week_date_ends($data->week), withType: $data->eStructure->canManage()) :
					new Collection();
			} else {
				$data->days = $data->eUserTime['timeDays'];
			}

			throw new ViewAction($data, ':timeDayWithoutTime');

		}

		if($data->team) {

			planningTimeDayTeam($data);

			// Une semaine sans chiffre n'a pas de bouton pour la choisir ni pour la quitter : elle montre son jour.
			if(($data->timeWeek['total']['working'] ?? 0.0) <= 0.0 and $data->timeWeek['total']['time'] <= 0.0) {
				$data->weekly = FALSE;
			}

			\planning\TimeWeekLib::fillTasksSpent($data->eStructure, $data->timeWeek['cUserTime'], $data->date);

			// Les heures comparées au contrat disent le contrat, que seul le gérant lit (`\hr\Contract::canRead()`) :
			// la page le vérifie avant de les lire, l'écran ne fait que les montrer. Elles ne se lisent que dans le
			// tableau de la semaine choisie, et sont de la RH, qui les ferme éteinte.
			$data->overtime = ($data->weekly and $data->eStructure->canManage() and $data->eStructure->acceptHr());

			if($data->overtime) {
				\hr\OvertimeLib::fillByWeek($data->eStructure, $data->timeWeek['cUserTime'], $data->week);
				$data->complementary = \hr\OvertimeLib::hasComplementary($data->timeWeek['cUserTime']);
			} else {
				$data->complementary = FALSE;
			}

			throw new ViewAction($data, ':timeDay');

		}

		if($data->eStructure->hasTime()) {
			\series\RepeatLib::createForWeek($data->eStructure, $data->week);
		}

		planningTimeDayRead($data);

		// Comme pour l'équipe, une semaine sans chiffre n'a pas de bouton : elle montre son jour.
		if(($data->timeDay['total']['working'] ?? 0.0) <= 0.0 and $data->timeDay['total']['time'] <= 0.0) {
			$data->weekly = FALSE;
		}

		// La semaine choisie ne rend que son tableau, sans les zones du jour.
		if($data->weekly) {
			$data->zones = [];
		} else {
			planningTimeDayZones($data);
		}

		$data->canCreate = (
			$data->eStructure->hasTime() and
			new \series\Task(['structure' => $data->eStructure])->canCreate()
		);

		throw new ViewAction($data, ':timeDay');

	})
	/**
	 * « Non planifié » à sa première ouverture : ses lignes. La réponse dit aussi aux gestes de réémettre
	 * que la zone est à l'écran.
	 */
	->get('unplanned', function($data) {

		planningTimeDayGesture($data);

		if(planningTimeDayHasZones($data) === FALSE) {
			throw new NotExpectedAction('No zone on a day to come');
		}

		$data->zones = [
			\planning\TaskLateLib::UNPLANNED => \planning\TimeDayLib::getZone($data->eStructure, $data->week, $data->eUserTime, $data->mine, \planning\TaskLateLib::UNPLANNED, TRUE, $data->folded, $data->expanded[\planning\TaskLateLib::UNPLANNED])
		];

		throw new ViewAction($data, ':unplanned');

	})
	/**
	 * « Voir les N autres » d'une action dans une zone : ses lignes au-delà du plafond, sous celles qui se
	 * montraient, après avoir ouvert la colonne des commentaires de la table quand l'une d'elles est commentée
	 * et que la table ne l'a pas (`variant`). Rien d'autre de la zone n'est lu ni rendu. L'action s'ajoute aux
	 * blocs dépliés que l'écran réémet (`expanded`), et les lignes que l'écran retient dans le bloc (`retained`)
	 * disent où commence ce qu'il ne montrait pas.
	 */
	->get('more', function($data) {

		planningTimeDayGesture($data);

		$data->zone = GET('zone');

		if(in_array($data->zone, [\planning\TaskLateLib::LATE, \planning\TaskLateLib::UNPLANNED], TRUE) === FALSE) {
			throw new NotExpectedAction('Invalid zone');
		}

		if(planningTimeDayHasZones($data) === FALSE) {
			throw new NotExpectedAction('No zone on a day to come');
		}

		$zone = \planning\TimeDayLib::getZoneKeys($data->eStructure, $data->week, $data->eUserTime, $data->mine, $data->zone, TRUE);

		$data->action = GET('action', 'int');

		if(array_key_exists($data->action, $zone['blocks']) === FALSE) {
			throw new NotExpectedAction('Action not in the zone');
		}

		// Les blocs dépliés restent ceux de l'écran, l'action en plus, et non ceux que la base plafonne encore
		// (`expandZone()`) : cette réponse ne replie rien, et un bloc où une ligne retenue tenait une place se
		// montre en entier alors que la base ne le plafonne plus. La suite des gestes doit le savoir déplié
		// (`TimeDayLib::getZoneSteps()`).
		$data->expanded[$data->zone] = array_values(array_unique(array_merge($data->expanded[$data->zone], [$data->action])));

		\planning\TimeDayLib::expandZone($zone, $data->expanded[$data->zone]);
		\planning\TaskLateLib::fill($data->eStructure, $data->week, $zone, [$data->action]);

		$data->cTaskRest = \planning\TimeDayLib::getZoneRest($zone, $data->action, planningTimeDayRetained()[$data->zone]);

		// La variante de la table est celle que l'écran réémet (`columns`), comme pour un geste.
		$commented = (GET('columns', 'int') === 3);

		$data->more = (
			$commented === FALSE and
			$data->cTaskRest->find(fn($eTask) => $eTask['cComment']->notEmpty())->notEmpty()
		) ? 'variant' : 'rows';

		$data->commented = ($commented or $data->more === 'variant');

		throw new ViewAction($data, ':more');

	})
	/**
	 * La journée de travail de la personne, un jour de la semaine : les heures, ou la durée tapée. Zéro la
	 * vide (`\hr\WorkingTimeLib::create()`), et elle redevient à saisir. Sur une structure à horaires, la journée
	 * se poste par son arrivée, son départ et sa pause, dont la durée est le calcul, et « Vider » (`clear`) l'efface
	 * après le même droit que l'écriture.
	 *
	 * La borne de 24 heures est une étape locale (`time.tooLong`), comme la conversion de `taskDetail.p.php` :
	 * `WorkingTime.e.php` est aussi celui de l'API, que cette borne ne touche pas (charte, point 10 c). Le jour
	 * est celui de la fiche (`date`), que le constructeur borne à la semaine affichée et que `build()` refuse
	 * à venir (`WorkingTime::date.check`).
	 *
	 * Saisie sur sa ligne de la fiche de l'équipe (R158, `origin=team`), la journée répond par cette ligne, la
	 * pastille du jour et le jour de l'équipe : la personne postée est celle de la ligne, dont le constructeur a
	 * vérifié qu'elle est sur la fiche, et le droit est le même `canWrite()` que sur sa propre fiche.
	 *
	 * Saisie sur un jour du récapitulatif du mois (`origin=recap`), la journée répond par sa semaine et les
	 * chiffres du mois. Le récapitulatif est au gérant, et la personne doit en être, ce que la page vérifie avant
	 * d'écrire, comme le fait le constructeur de `hr/recap`.
	 */
	->post('doWorkingTime', function($data) {

		// La journée se saisit sous le temps de travail comme sous le suivi RH seul : pas de `planningTimeDayGesture()`,
		// qui garde les gestes sur les interventions.
		$data->eStructure->validate('hasWorkingTime');

		planningTimeDayNotTeam($data);

		$origin = POST('origin', '?string');

		if($origin !== NULL and in_array($origin, [\planning\TimeWeekLib::TEAM, \hr\RecapLib::ORIGIN], TRUE) === FALSE) {
			throw new NotExpectedAction('Invalid origin');
		}

		// Une fiche d'une seule personne n'a pas d'équipe à redessiner : la réponse lèverait après l'écriture.
		if($origin === \planning\TimeWeekLib::TEAM and $data->cUser->count() < 2) {
			throw new NotExpectedAction('No team under two users');
		}

		if($origin === \hr\RecapLib::ORIGIN) {

			$data->eStructure->validate('canManage', 'acceptHr');

			$data->month = \hr\RecapLib::getMonth($data->eStructure, $data->date);

			if(
				\hr\RecapLib::isMonthValid($data->eStructure, $data->month) === FALSE or
				\hr\RecapLib::getUsers($data->eStructure, $data->month)->offsetExists($data->eUserTime['id']) === FALSE
			) {
				throw new NotExpectedAction('User not in the recap');
			}

		}

		// Le jour choisi à l'écran, vide quand c'est la semaine, que réémet la semaine en champs (`data-selected`) : la
		// réponse garde la pastille choisie et le lien de la semaine tels que l'écran les montre. Une date hors de la
		// semaine est forgée, et refusée avant l'écriture, comme les autres refus de la réponse.
		if(post_exists('selected')) {

			$selected = POST('selected');

			if($selected === '') {
				$data->dateSelected = NULL;
			} else if(in_array($selected, week_dates($data->week), TRUE)) {
				$data->dateSelected = $selected;
			} else {
				throw new NotExpectedAction('Selected date out of the week');
			}

		}

		$fw = new FailWatch();

		$eWorkingTime = new \hr\WorkingTime([
			'structure' => $data->eStructure,
			'user' => $data->eUserTime,
		]);

		// Le droit avant la construction : la journée d'un collègue se refuse comme telle, sans dire si le jour posté
		// est dans sa présence (`WorkingTime::date.outOfPresence`).
		$eWorkingTime->validate('canWrite');

		if(POST('clear', 'bool')) {

			// « Vider » n'est le geste que d'une structure à horaires : en durée, zéro vide déjà la journée.
			if($data->eStructure->hasTimeRange() === FALSE) {
				throw new NotExpectedAction('Clear only with time range');
			}

			$eWorkingTime->build(['date'], $_POST);

			$fw->validate();

			$eWorkingTimeCurrent = \hr\WorkingTimeLib::getByDate($data->eStructure, $data->eUserTime, $eWorkingTime['date']);

			if($eWorkingTimeCurrent->notEmpty()) {
				\hr\WorkingTimeLib::delete($eWorkingTimeCurrent);
			}

		} else {

			$l = new \PropertyList();
			$l->property('time')->addAfterCheck('tooLong', fn(float $value): bool => $value <= 24.0);

			// La structure et la personne sont celles de la fiche, que le constructeur a lues : restent la date, puis
			// la durée ou, sous horaires, l'arrivée, le départ et la pause.
			$properties = array_diff(\hr\WorkingTimeLib::getPropertiesCreate(NULL, $data->eStructure), ['structure', 'user']);

			$eWorkingTime->build($properties, $_POST, $l);

			$fw->validate();

			\hr\WorkingTimeLib::create($eWorkingTime);

		}

		if($origin === \planning\TimeWeekLib::TEAM) {

			planningTimeDayTeam($data);

			$data->eUserTeam = $data->timeWeek['cUserTime'][$data->eUserTime['id']];

			// Un absent dont la journée vient d'être vidée quitte le tableau, que la vue redessine entier, avec le
			// détail du jour de chacun. Le chemin inverse n'existe pas à l'écran : un absent hors du tableau n'y a
			// pas de ligne où saisir sa journée.
			$data->teamLeft = ($data->cUserPresent->offsetExists($data->eUserTeam['id']) === FALSE);

			if($data->teamLeft) {
				\planning\TimeWeekLib::fillTasksSpent($data->eStructure, $data->cUserPresent, $data->date);
			}

			throw new ViewAction($data, ':doWorkingTimeTeam');

		}

		if($origin === \hr\RecapLib::ORIGIN) {

			\hr\RecapLib::fillByMonth($data->eStructure, new Collection([$data->eUserTime['id'] => $data->eUserTime]), $data->month);

			$cPresence = \hr\PresenceLib::getByUserBetween($data->eStructure, $data->eUserTime, \hr\RecapLib::getStart($data->eStructure, $data->month), \hr\RecapLib::getStop($data->eStructure, $data->month));

			$data->weeks = \hr\RecapLib::getByUser($data->eStructure, $data->eUserTime, $data->month, $cPresence);

			throw new ViewAction($data, ':doWorkingTimeRecap');

		}

		planningTimeDayRead($data);

		throw new ViewAction($data, ':doWorkingTime');

	})
	/**
	 * Le temps de la personne sur une intervention le jour de la fiche, à faire comme faite : le temps seul
	 * laisse l'état (Q1, règle de L3a). Seul l'écart avec ce qu'elle a déjà ce jour-là s'écrit
	 * (`\series\TimesheetLib::writeTimeForDay()`), qui refuse une journée de plus de 24 heures. Le jour est
	 * celui de la fiche, borné à la semaine par le constructeur et refusé à venir par `build()`
	 * (`Timesheet::date.future`). La durée est exigée (`time.missing`, liste `day`) : absente, elle effacerait
	 * la ligne.
	 *
	 * Pas d'étape `date.week` dans `build()` : la date est celle que le constructeur a déjà bornée à la
	 * semaine (`Date out of the week`), et quand bien même une date forgée hors de la semaine passerait,
	 * elle écrirait le temps de la personne un jour passé, une valeur légitime que sa propre fiche, à
	 * cette semaine-là, pouvait déjà écrire. Le refus ne protégerait rien.
	 */
	->post('doTime', function($data) {

		planningTimeDayGesture($data);

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		$dayWeekBefore = planningTimeDayPosition($data, $eTask);
		$zone = planningTimeDayZone();
		$actionFirst = POST('actionFirst', 'bool');

		$fw = new FailWatch();

		$eTimesheet = new \series\Timesheet([
			'structure' => $data->eStructure,
			'user' => $data->eUserTime,
		]);

		$eTimesheet->build(['time', 'date'], $_POST, new \PropertyList('day'));

		$fw->validate();

		$eTimesheet->validate('canWrite', 'acceptWrite');

		\series\TimesheetLib::writeTimeForDay($eTask, $eTimesheet);

		$fw->validate();

		planningTimeDayRespond($data, $eTask, $dayWeekBefore, 'time', $zone, $actionFirst);

		throw new ViewAction($data, ':doLine');

	})
	/**
	 * La coche, comme dans la Semaine (`taskWeek.p.php`) : le temps prévu se pose à la première coche, au
	 * nom de la personne de l'onglet (Q2). Quand on ne peut pas écrire son temps, un planificateur sur
	 * l'onglet d'un autre, la coche s'écrit sans temps. Le jour du fait est celui du planning actuel (Q6) :
	 * une ligne du jour y est déjà posée ou faite, et le garde ; une ligne sans jour prend aujourd'hui.
	 */
	->post('doDone', function($data) {

		planningTimeDayGesture($data);

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$dayWeekBefore = planningTimeDayPosition($data, $eTask);
		$zone = planningTimeDayZone();
		$actionFirst = POST('actionFirst', 'bool');

		$eTimesheet = new \series\Timesheet([
			'structure' => $data->eStructure,
			'user' => $data->eUserTime,
		]);

		if($eTimesheet->assertWrite()) {
			\series\TaskLib::updateDoneWithTime($eTask, $eTimesheet, $data->week);
		} else {
			\series\TaskLib::updateDoneCollection(new Collection([$eTask]), $data->week);
		}

		planningTimeDayRespond($data, $eTask, $dayWeekBefore, 'done', $zone, $actionFirst);

		throw new ViewAction($data, ':doLine');

	})
	/**
	 * La décoche : l'intervention redevient à faire, le temps passé reste.
	 */
	->post('doTodo', function($data) {

		planningTimeDayGesture($data);

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		$dayWeekBefore = planningTimeDayPosition($data, $eTask);
		$zone = planningTimeDayZone();
		$actionFirst = POST('actionFirst', 'bool');

		\series\TaskLib::updateTodoCollection(new Collection([$eTask]));

		planningTimeDayRespond($data, $eTask, $dayWeekBefore, 'todo', $zone, $actionFirst);

		throw new ViewAction($data, ':doLine');

	})
	/**
	 * « Nouvelle intervention » ouvert depuis la fiche par « J'ai aussi fait » ou « + Fait »
	 * (`TaskCreateUi`, retour 293) : le jour ouvert est choisi, Fait quand le lien le demande (`status`)
	 * et que le jour le permet, et la personne de l'onglet est celle dont le temps se montre. Le panneau
	 * réémet le jour ouvert et la personne (`date`, `user`), pour que la réponse dessine la fiche que l'on
	 * regarde. Il ne s'offre qu'à qui écrit le temps de cette personne, comme ses entrées.
	 */
	->get('create', function($data) {

		planningTimeDayGesture($data);

		if(new \series\Task(['structure' => $data->eStructure])->canCreate() === FALSE or $data->canTime === FALSE) {
			throw new NotAllowedAction();
		}

		$data->page = 'timeDay';
		[$data->eAction, $data->eCategoryAction] = \planning\TaskCreateLib::getAction($data->eStructure, NULL);
		$data->eTask = \planning\TaskCreateLib::getNew($data->eStructure, $data->eAction, $data->eCategoryAction);
		$data->days = \planning\TaskCreateLib::getDays($data->week);
		$data->status = \planning\TaskCreateLib::getStatus($data->days, $data->date, GET('status', [\series\Task::TODO, \series\Task::DONE], NULL));
		$data->when = $data->week;
		$data->weeks = \planning\TaskCreateLib::getWeeks($data->eStructure, $data->status);
		$data->cActionShortcut = \planning\TaskCreateLib::getShortcuts($data->eStructure, $data->eAction);
		$data->post = [
			'user' => $data->eUserTime['id'],
			'date' => $data->date,
		];
		$data->cUserTime = \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->week, $data->eUserTime);

		throw new ViewAction($data, 'planning/taskDay:create');

	})
	/**
	 * Les interventions neuves, faites ou à faire dans la semaine choisie (`when`, `TaskCreateLib::createCollection()`,
	 * qui porte la validation et les droits de chaque temps écrit). La réponse est celle de la fiche : la
	 * liste, où elles entrent même masquées, le jour où elles sont faites (`dateDone`), qui peut ne pas être
	 * le jour ouvert, et les chiffres. Dans une autre semaine, elle ne fait que fermer le panneau et nommer la
	 * semaine (`TaskCreateLib::getSuccessOutside()`).
	 */
	->post('doCreate', function($data) {

		planningTimeDayGesture($data);

		if($data->canTime === FALSE) {
			throw new NotAllowedAction();
		}

		$when = POST('when', fn($value) => Filter::check('week', $value)) ?? throw new NotExpectedAction('Invalid week');

		$data->cTaskCreated = \planning\TaskCreateLib::createCollection($data->eStructure, $when, $_POST);
		$data->success = \planning\TaskCreateLib::getSuccessOutside($data->cTaskCreated, $when, [$data->week]);

		if($data->success !== NULL) {
			throw new ViewAction($data, ':doCreate');
		}

		$eTaskCreated = $data->cTaskCreated->first();

		$data->dateDone = $eTaskCreated->isDone() ? $eTaskCreated['doneDate'] : NULL;
		$data->canCreate = new \series\Task(['structure' => $data->eStructure])->canCreate();

		planningTimeDayRead($data, cTaskCreated: $data->cTaskCreated);

		throw new ViewAction($data, ':doCreate');

	});
?>
