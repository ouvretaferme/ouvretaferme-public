<?php
new \hr\WorkingTimePage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));
		$data->eUserTime = \user\UserLib::getById(INPUT('user'));

		return new \hr\WorkingTime([
			'structure' => $data->eStructure,
			'user' => $data->eUserTime
		]);

	})
	// Sous horaires, la journée se poste par ses horaires : la liste dépend de la structure de l'élément.
	->doCreate(function($data) {
		throw new ReloadAction();
	}, propertiesCreate: fn(\hr\WorkingTime $e) => \hr\WorkingTimeLib::getPropertiesCreate(NULL, $e['structure']));

new Page()
	->post('getByUser', function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canPlanningLimited');
		$data->week = \series\Task::POST('week', 'plannedWeek', function($value) {
			throw new NotExpectedAction('Invalid week \''.encode($value).'\'');
		});

		$data->cUserStructure = \structure\MemberLib::getUsersByStructureForPeriod(
			$data->eStructure,
			week_date_starts($data->week),
			week_date_ends($data->week),
			withPresenceAbsence: TRUE
		);

		$data->eUserTime = \user\UserLib::getById(POST('user'))->validate('active');
		$data->eUserTime['cPresence'] = $data->cUserStructure[$data->eUserTime['id']]['cPresence'] ?? new Collection();
		$data->eUserTime['cAbsence'] = $data->cUserStructure[$data->eUserTime['id']]['cAbsence'] ?? new Collection();

		if($data->cUserStructure->offsetExists($data->eUserTime['id']) === FALSE) {
			throw new NotExpectedAction('Bad user');
		}

		new \hr\WorkingTime([
			'structure' => $data->eStructure,
			'user' => $data->eUserTime
		])->validate('canRead');

		$data->eUserTime['weekTimesheet'] = \series\TimesheetLib::getTimesByWeek($data->eStructure, $data->eUserTime, $data->week);
		$data->eUserTime['cWorkingTimeWeek'] = \hr\WorkingTimeLib::getByWeek($data->eStructure, $data->eUserTime, $data->week);


		throw new ViewAction($data);

	})
?>
