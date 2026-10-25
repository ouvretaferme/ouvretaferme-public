<?php
/**
 * Les gestes du pense-bête de l'accueil (`planning-reminder`) : l'ajout, la coche, la décoche, la correction sur
 * place et l'effacement d'un rappel, avec les droits et l'état des mêmes gestes de la Semaine
 * (`taskWeek:doReminder*`). Les routes de la Semaine ne servent pas ici : elles lisent la semaine de leur
 * écran et redessinent son pense-bête.
 *
 * Un rappel d'une autre structure que celle de l'accueil est refusé, même à qui écrit dans les deux : la
 * réponse redessine le bloc de cet accueil-ci.
 *
 * La réponse ne touche que la ligne du rappel, à la granularité décidée ici (`$data->change`) et rendue
 * par `doReminder` :
 * - `insert` : un rappel neuf, posé devant celui qui le suit dans l'ordre du bloc (`$data->next`), ou
 *   devant la ligne d'ajout ;
 * - `line` : un rappel coché, décoché ou corrigé sans changer de jour, redessiné à sa place ;
 * - `move` : un rappel dont le jour a changé, donc le rang, l'ordre du bloc suivant les jours
 *   (`ReminderLib::getForToday()`). La liste des rangs se redessine : un rang ne se pose que devant un
 *   voisin présent à l'écran, et un accueil ouvert avant l'ajout d'un autre membre n'a pas ce voisin ;
 * - `remove` : un rappel effacé, ou qui a quitté la liste, coché un autre jour.
 * Le bloc n'est jamais redessiné ni retiré : qui fait un de ces gestes écrit au planning, et garde donc
 * la ligne d'ajout quand la liste se vide (`DashboardLib::load()`).
 */
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

	})
	/**
	 * Le rappel neuf, dans la semaine en cours : l'accueil n'a pas d'autre semaine à proposer, et celle du
	 * serveur passe avant ce que le formulaire poste.
	 */
	->post('doReminderCreate', function($data) {

		$data->eReminder = new \planning\Reminder(['structure' => $data->eStructure]);
		$data->eReminder->validate('canCreate');

		$fw = new FailWatch();

		$data->eReminder->build(\planning\ReminderLib::getPropertiesCreate(), ['week' => \Clock::week()] + $_POST, new \PropertyList('create'));

		$fw->validate();

		\planning\ReminderLib::create($data->eReminder);

		planningDashboardReminderRespond($data, 'insert');

	})
	/**
	 * La coche et la décoche, comme à la Semaine (`taskWeek:doReminderCheck`) : la ligne reste à sa place,
	 * barrée jusqu'au soir ou rendue à faire. Un rappel déjà dans l'état demandé par une autre personne
	 * depuis l'ouverture de l'accueil ne s'écrit pas et ne lève rien : l'intention est satisfaite.
	 */
	->post('doReminderCheck', function($data) {

		$data->eReminder = planningDashboardReminderGet($data)->validate('canWrite');

		if($data->eReminder->acceptCheck()) {
			\planning\ReminderLib::check($data->eReminder);
		}

		planningDashboardReminderRespond($data, 'line');

	})
	->post('doReminderUncheck', function($data) {

		$data->eReminder = planningDashboardReminderGet($data)->validate('canWrite');

		if($data->eReminder->acceptUncheck()) {
			\planning\ReminderLib::uncheck($data->eReminder);
		}

		planningDashboardReminderRespond($data, 'line');

	})
	/**
	 * Le texte et le jour corrigés sur place. La semaine ne se poste pas : le jour se borne à celle du
	 * rappel, qui reste la sienne pour un rappel resté d'une semaine passée.
	 */
	->post('doReminderUpdate', function($data) {

		$data->eReminder = planningDashboardReminderGet($data)
			->validate('canUpdate')
			->validate('acceptUpdate', onFail: fn() => throw new FailAction('planning\Reminder::updatePast'));

		$date = $data->eReminder['date'];

		$fw = new FailWatch();

		$data->eReminder->build(\planning\ReminderLib::getPropertiesUpdate(), $_POST, new \PropertyList('update'));

		$fw->validate();

		\planning\ReminderLib::update($data->eReminder, \planning\ReminderLib::getPropertiesUpdate());

		planningDashboardReminderRespond($data, ($data->eReminder['date'] === $date) ? 'line' : 'move');

	})
	->post('doReminderDelete', function($data) {

		$data->eReminder = planningDashboardReminderGet($data)
			->validate('canDelete')
			->validate('acceptDelete', onFail: fn() => throw new FailAction('planning\Reminder::deletePast'));

		\planning\ReminderLib::delete($data->eReminder);

		$data->change = 'remove';

		throw new ViewAction($data, ':doReminder');

	});

function planningDashboardReminderGet(\stdClass $data): \planning\Reminder {

	return \planning\ReminderLib::getById(POST('reminder', 'int'))
		->validateProperty('structure', $data->eStructure);

}

/**
 * La réponse d'un geste qui laisse le rappel dans le bloc : le rappel relu à son rang, et celui qui le
 * suit. Un rappel que le bloc ne montre plus, coché un autre jour, quitte la liste.
 */
function planningDashboardReminderRespond(\stdClass $data, string $change): void {

	$place = \planning\DashboardLib::getReminderPlace($data->eStructure, $data->eReminder);

	if($place === NULL) {
		$data->change = 'remove';
	} else {
		$data->change = $change;
		$data->reminder = $place['reminder'];
		$data->next = $place['next'];
		$data->reminders = $place['reminders'];
	}

	throw new ViewAction($data, ':doReminder');

}
?>
