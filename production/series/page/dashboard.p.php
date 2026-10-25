<?php
/**
 * Le choix des interventions que suit le bloc de la semaine sur l'accueil (`series-week`).
 *
 * La liste postée est entière, dans l'ordre du bloc : glisser une ligne et basculer un interrupteur
 * sont le même geste, qui réécrit `viewDashboardActions`. La réponse redemande le bloc par sa route,
 * le membre lu par `Structure::getMember()` étant celui du début de la requête.
 */
new \structure\MemberPage()
	->write('doUpdateActions', function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']);

		$actions = json_decode(POST('actions'), TRUE);

		if(is_array($actions) === FALSE) {
			throw new NotExpectedAction('Actions must be a list');
		}

		$fw = new FailWatch();

		$data->e['cAction'] = \series\DashboardLib::getActions($data->eStructure);
		$data->e->build(['viewDashboardActions'], ['viewDashboardActions' => $actions]);

		$fw->validate();

		\structure\MemberLib::update($data->e, ['viewDashboardActions']);

		throw new ViewAction($data);

	}, validate: ['canSelf']);

/**
 * La coche et la décoche d'une intervention depuis le bloc de la semaine, avec les droits, l'état et les
 * écritures de la marque d'une carte du planning (`planning/taskWeek:doDone` et `doTodo`), dans la semaine
 * en cours, la seule que l'accueil montre. Les routes du planning ne servent pas ici : elles redessinent
 * leur écran. Pas de bulle du temps passé : le temps prévu se pose comme au planning, et se corrige au
 * planning.
 *
 * La réponse ne touche que ce que le geste change, à la granularité décidée ici (`$data->change`) et
 * rendue par `doTask` :
 * - `line` : l'intervention reste dans la semaine, sa ligne se redessine à sa place, avec la tête de son
 *   action et le chiffre du bloc ;
 * - `remove` : l'intervention a quitté la semaine, sa ligne s'efface, la tête et le chiffre se redessinent ;
 * - `empty` : l'action n'a plus rien cette semaine, son pli s'efface et elle rejoint la ligne des actions
 *   sans rien.
 */
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

	})
	->post('doDone', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isTodo')
			->validateProperty('structure', $data->eStructure);

		\series\TaskLib::updateDoneByMark($data->eStructure, $eTask);

		seriesDashboardTaskRespond($data, $eTask);

	})
	->post('doTodo', function($data) {

		$eTask = \series\TaskLib::getById(POST('task', 'int'))
			->validate('canWrite', 'isDone')
			->validateProperty('structure', $data->eStructure);

		\series\TaskLib::updateTodoCollection(new Collection([$eTask]));

		seriesDashboardTaskRespond($data, $eTask);

	});

function seriesDashboardTaskRespond(\stdClass $data, \series\Task $eTask): void {

	$place = \series\DashboardLib::getWeekPlace($data->eStructure, $eTask);

	$data->eTask = $eTask;
	$data->week = $place['week'];
	$data->action = $place['action'];
	$data->task = $place['task'];

	if($place['action'] === NULL) {
		$data->change = 'empty';
	} else if($place['task'] === NULL) {
		$data->change = 'remove';
	} else {
		$data->change = 'line';
	}

	throw new ViewAction($data, ':doTask');

}
?>
