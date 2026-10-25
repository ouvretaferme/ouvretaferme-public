<?php
/**
 * L'Année de l'écran « Interventions » (R-Annee) : une colonne par semaine de l'année, ouverte sur la
 * semaine de l'adresse (`TaskYearUi`). La page rend la tête de chaque semaine et le contenu de celles qui
 * l'entourent (`TaskYearLib::getAround()`), le navigateur charge les autres par `weeks`.
 *
 * C'est une vue de lecture et de coche (écart c du plan, accepté par V-Planning) : la coche et la décoche,
 * comme le Jour et par les mêmes écritures, et « Nouvelle intervention » sur une semaine. Ni sélection, ni
 * temps : le temps se reprend sur la page de l'intervention.
 *
 * Chaque geste porte la semaine de sa carte (`week`), où la coche date l'intervention et où « Nouvelle
 * intervention » la prévoit, et réémet la semaine de l'écran (`current`). Il répond par les semaines
 * qu'il change (`TaskYearLib::getChange()`), carte et tête quand l'intervention garde ses semaines,
 * colonnes entières sinon.
 */

/**
 * La semaine de l'écran qu'un geste réémet, qui doit être de la même année que la semaine du geste.
 */
function planningTaskYearCurrent(\stdClass $data, ?string $current): string {

	if(in_array($current, \planning\TaskYearLib::getWeeks($data->year), TRUE) === FALSE) {
		throw new NotExpectedAction('Current week out of the year');
	}

	return $current;

}

/**
 * Les interventions faites que l'écran montre encore sous le masque (`TaskYearLib::getColumns()`), que
 * réémettent les gestes (POST) comme les chargements (GET) : une semaine chargée après une coche doit garder
 * la carte cochée, sans quoi la décoche de cette carte ne trouverait rien à remplacer dans sa colonne.
 */
function planningTaskYearShown(): array {

	return array_map('intval', INPUT('shown', 'array', []));

}

/**
 * La réponse d'un geste : les semaines d'avant et d'après l'intervention, relues, rendues à la granularité
 * que décide `TaskYearLib::getGranularity()`. L'intervention du geste reste à l'écran sous le masque, comme
 * dans la Semaine.
 *
 * @param array $weeksBefore Les semaines où l'intervention paraissait avant le geste.
 */
function planningTaskYearRespond(\stdClass $data, \series\Task $eTask, array $weeksBefore, string $gesture): void {

	$data->gesture = $gesture;

	$weeksAfter = \planning\TaskYearLib::getWeeksForTask($data->eStructure, \series\TaskLib::getById($eTask['id']));

	$data->change = \planning\TaskYearLib::getChange($data->year, $weeksBefore, $weeksAfter);

	$data->columns = \planning\TaskYearLib::getColumns($data->eStructure, $data->change, $data->change, $data->hide, array_merge(planningTaskYearShown(), [$eTask['id']]));
	$data->granularity = \planning\TaskYearLib::getGranularity($eTask, $weeksBefore, $weeksAfter, $data->columns);
	$data->eTask = $eTask;

	throw new ViewAction($data, ':doTask');

}

new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canPlanningLimited');

		$data->week = INPUT('week', fn($value) => Filter::check('week', $value), \Clock::week());
		$data->year = week_year($data->week);

		if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid($data->year) === FALSE) {
			throw new NotExpectedAction('Invalid week');
		}

		$data->hide = $data->eStructure->getView('viewPlanningHideDone');
		$data->canCreate = new \series\Task(['structure' => $data->eStructure])->canCreate();

	})
	/**
	 * ⚠️ Une écriture dans un GET, comme l'Année du planning actuel (`series/page/index.p.php`) : les
	 * interventions répétées de l'année se matérialisent avant d'être lues.
	 */
	->get(['/ferme/{id}/planning-v2/annee', '/ferme/{id}/planning-v2/annee/{week@week}'], function($data) {

		\series\RepeatLib::createForYear($data->eStructure, $data->year);

			$data->calendar = \planning\TaskYearLib::getCalendar($data->year);
		$data->eMember = $data->eStructure->getMember();

		// « + Fait » n'a rien à créer sur une semaine dont aucun jour ne peut porter Fait.
		$data->canCreateByStatus = [
			\series\Task::TODO => $data->canCreate,
			\series\Task::DONE => ($data->canCreate and \planning\TaskCreateLib::hasDone($data->week)),
		];
		$data->columns = \planning\TaskYearLib::getColumns($data->eStructure, \planning\TaskYearLib::getWeeks($data->year), \planning\TaskYearLib::getAround($data->week), $data->hide);

		throw new ViewAction($data, ':taskYear');

	})
	/**
	 * Le contenu des semaines qui approchent du cadre, huit au plus, toutes de l'année affichée.
	 */
	->get('weeks', function($data) {

		$weeks = GET('weeks', 'array', []);

		// Le jeton du chargement (`PlanningTaskYear.load()`) : la réponse ne vise que les colonnes qui le
		// portent encore, un geste parti entre-temps le leur ayant retiré (vue `doTask`).
		$data->load = GET('load', fn($value) => (is_string($value) and ctype_digit($value)));

		if($data->load === NULL) {
			throw new NotExpectedAction('Invalid load');
		}

		// `array_diff()` compare des chaînes : une entrée tableau forgée (`weeks[0][]`) lèverait une notice
		// avant le refus, d'où le contrôle du type en premier.
		if(
			$weeks === [] or
			count(array_filter($weeks, 'is_string')) !== count($weeks) or
			count($weeks) > \planning\TaskYearLib::LOAD_MAX or
			array_diff($weeks, \planning\TaskYearLib::getWeeks($data->year)) !== []
		) {
			throw new NotExpectedAction('Invalid weeks');
		}

		$data->change = array_values(array_unique($weeks));
		$data->columns = \planning\TaskYearLib::getColumns($data->eStructure, $data->change, $data->change, $data->hide, planningTaskYearShown());

		throw new ViewAction($data);

	})
	/**
	 * Les suggestions de la recherche (R-Recherche-2) : les valeurs des cartes de toute l'année, sous le
	 * masque, réduites à ce qui est tapé (`query`). Les semaines que l'écran n'a pas encore
	 * chargées comptent, d'où une lecture serveur, quand la Semaine et le Jour relèvent les leurs sur l'écran.
	 */
	->post('query', function($data) {

		$year = \planning\TaskSearchLib::getYear($data->eStructure, $data->week, $data->hide, []);

		$data->suggestions = \planning\TaskSearchLib::getSuggestions($year, POST('query'));
		$data->cUser = \hr\TeammateLib::getUsersForPlanning($data->eStructure);
		$data->cPlant = \planning\TaskSearchLib::getPlantsByName($data->eStructure, $data->suggestions['plant']);

		throw new ViewAction($data);

	})
	/**
	 * Ce que les jetons retiennent dans toute l'année (R-Recherche-2) : les interventions retenues, par
	 * semaine. L'écran en tire les semaines à charger et le décompte de ce qu'il n'a pas encore chargé ;
	 * les colonnes chargées filtrent leurs cartes sur place.
	 */
	->get('search', function($data) {

		$criteria = GET('criteria', 'array', []);

		if(\planning\TaskSearchLib::isCriteria($criteria) === FALSE) {
			throw new NotExpectedAction('Invalid criteria');
		}

		$year = \planning\TaskSearchLib::getYear($data->eStructure, $data->week, $data->hide, planningTaskYearShown());

		$data->matches = \planning\TaskSearchLib::getMatches($year, $criteria);

		throw new ViewAction($data);

	})
	/**
	 * La coche, comme au Jour (`taskDay.p.php`, `doDone`) : l'intervention passe en fait dans la semaine de sa
	 * carte, le temps prévu posé à la première coche de la personne.
	 */
	->post('doDone', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		$data->current = planningTaskYearCurrent($data, POST('current'));
		$weeksBefore = \planning\TaskYearLib::getWeeksForTask($data->eStructure, $eTask);

		if($data->eStructure->hasTime()) {

			$eTimesheet = new \series\Timesheet([
				'structure' => $data->eStructure,
				'user' => \user\ConnectionLib::getOnline(),
			]);

			$eTimesheet->validate('canWrite', 'acceptWrite');

			\series\TaskLib::updateDoneWithTime($eTask, $eTimesheet, $data->week);

		} else {
			\series\TaskLib::updateDoneCollection(new Collection([$eTask]), $data->week);
		}

		planningTaskYearRespond($data, $eTask, $weeksBefore, 'done');

	})
	/**
	 * La décoche : l'intervention redevient à faire, le temps passé reste.
	 */
	->post('doTodo', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		$data->current = planningTaskYearCurrent($data, POST('current'));
		$weeksBefore = \planning\TaskYearLib::getWeeksForTask($data->eStructure, $eTask);

		\series\TaskLib::updateTodoCollection(new Collection([$eTask]));

		planningTaskYearRespond($data, $eTask, $weeksBefore, 'todo');

	})
	/**
	 * « Nouvelle intervention » sur une semaine, depuis « + À faire » et « + Fait » de la rangée des outils, qui
	 * demandent leur état, ou depuis le « + » de sa colonne ou d'un de ses groupes d'action, qui ouvre à faire et
	 * dont le groupe pose l'action. Le panneau réémet la semaine de l'écran (`current`).
	 */
	->get('create', function($data) {

		if($data->canCreate === FALSE) {
			throw new NotAllowedAction();
		}

		$data->current = planningTaskYearCurrent($data, GET('current'));
		[$data->eAction, $data->eCategoryAction] = \planning\TaskCreateLib::getAction($data->eStructure, GET('action', '?int'));
		$data->eTask = \planning\TaskCreateLib::getNew($data->eStructure, $data->eAction, $data->eCategoryAction);
		$status = GET('status', [\series\Task::TODO, \series\Task::DONE], \series\Task::TODO);

		$data->days = \planning\TaskCreateLib::getDays($data->week);
		$data->date = \planning\TaskCreateLib::getDateDefault($data->days, $status);
		$data->status = \planning\TaskCreateLib::getStatus($data->days, $data->date, $status);
		$data->when = $data->week;
		$data->weeks = \planning\TaskCreateLib::getWeeks($data->eStructure, $data->status);
		$data->cActionShortcut = \planning\TaskCreateLib::getShortcuts($data->eStructure, $data->eAction);
		$data->cUserTime = \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->week);

		throw new ViewAction($data);

	})
	/**
	 * Les interventions neuves, faites ou à faire dans la semaine choisie (`when`), celle de sa colonne à
	 * l'ouverture, une par culture choisie (`TaskCreateLib::createCollection()`). Toutes paraissent dans cette
	 * seule semaine : la première dit les semaines que la réponse redessine pour toutes, aucune hors de l'année
	 * affichée, où seul un message dit leur semaine (`TaskCreateLib::getSuccessOutside()`).
	 */
	->post('doCreate', function($data) {

		$data->current = planningTaskYearCurrent($data, POST('current'));

		$when = POST('when', fn($value) => Filter::check('week', $value)) ?? throw new NotExpectedAction('Invalid week');

		$data->cTaskCreated = \planning\TaskCreateLib::createCollection($data->eStructure, $when, $_POST);
		$data->success = \planning\TaskCreateLib::getSuccessOutside($data->cTaskCreated, $when, \planning\TaskYearLib::getWeeks($data->year));

		planningTaskYearRespond($data, $data->cTaskCreated->first(), [], 'create');

	});
?>
