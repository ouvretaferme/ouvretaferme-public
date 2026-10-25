<?php
new \hr\TeammatePage(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canManage');

	})
	->getCreateElement(fn($data) => new \hr\Teammate([
		'structure' => $data->eStructure,
		'user' => new \user\User()
	]))
	->create(function($data) {

		// Sans `user`, le panneau ajoute une personne sans compte. Avec, il met au planning quelqu'un qui a déjà
		// un accès, et une personne qui ne peut pas l'être n'a pas de panneau.
		if(request_exists('user')) {

			$cUser = \hr\TeammateLib::getCandidates($data->eStructure);

			if($cUser->offsetExists(REQUEST('user', 'int')) === FALSE) {
				throw new NotExistsAction();
			}

			$data->e['user'] = $cUser[REQUEST('user', 'int')];

		}

		$data->ePresence = new \hr\Presence([
			'structure' => $data->eStructure,
			'from' => \Clock::date()
		]);

		if($data->eStructure->acceptHr()) {

			$data->eContract = \hr\ContractLib::getNew();
			$data->eContract['structure'] = $data->eStructure;
			$data->eContract['reference'] = \hr\OvertimeLib::getReference($data->eStructure, \Clock::date());

		} else {
			$data->eContract = new \hr\Contract();
		}

		throw new ViewAction($data);

	})
	/**
	 * Écrit à la main, et non par le `doCreate` de `ModulePage` : le geste construit jusqu'à quatre éléments, la
	 * personne quand elle n'a pas de compte, le coéquipier, sa période et son contrat. Tous passent par
	 * `build()` avant la moindre écriture, puis `TeammateLib::create()` les écrit dans une transaction.
	 */
	->post('doCreate', function($data) {

		$fw = new FailWatch();

		$eTeammate = new \hr\Teammate([
			'structure' => $data->eStructure,
			'user' => new \user\User()
		]);

		$eTeammate->validate('canCreate', 'acceptCreate');

		if(POST('source') === 'ghost') {

			$eUser = \structure\MemberLib::getNewGhostUser();
			$eUser->build(['firstName', 'lastName'], $_POST);

			$eTeammate['user'] = $eUser;

		} else {
			$eTeammate->build(\hr\TeammateLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));
		}

		$ePresence = new \hr\Presence([
			'structure' => $data->eStructure,
			'user' => $eTeammate['user']
		]);

		$ePresence->build(['from', 'to'], $_POST, new \PropertyList('create'));

		if(
			$data->eStructure->acceptHr() and
			\hr\ContractLib::isPosted()
		) {

			$ePresence['eContract'] = new \hr\Contract();
			$ePresence['eContract']->build(\hr\ContractLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

		}

		$fw->validate();

		$eTeammate['ePresence'] = $ePresence;

		\hr\TeammateLib::create($eTeammate);

		throw new ReloadAction('hr', 'Teammate::created');

	});

new \hr\TeammatePage()
	// `PresenceLib::getByUser()` lit le type et les drapeaux de la structure, qu'un délégué ne garantit pas au-delà de son id.
	->applyElement(function($data, \hr\Teammate $e) {

		$e['structure'] = \structure\StructureLib::getById($e['structure']);
		$e['cPresence'] = \hr\PresenceLib::getByUser($e['structure'], $e['user']);

	})
	->write('doLeave', function($data, \hr\Teammate $e) {

		$fw = new FailWatch();

		\hr\TeammateLib::leave($e);

		$fw->validate();

		throw new ReloadAction('hr', 'Teammate::left');

	}, validate: ['canWrite', 'acceptLeave']);
?>
