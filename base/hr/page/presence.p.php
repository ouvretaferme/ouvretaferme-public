<?php
new \hr\PresencePage(function($data) {

	$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canManage');

	})
	->getCreateElement(function($data) {

		$e = new \hr\Presence([
			'structure' => $data->eStructure,
			'cUser' => \hr\TeammateLib::getUsersByStructure($data->eStructure)
		]);

		if(request_exists('user')) {
			$e['user'] = $e['cUser'][REQUEST('user', 'int')] ?? new \user\User();
		} else {
			$e['user'] = new \user\User();
		}

		return $e;

	})
	->create(function($data) {

		if($data->eStructure->acceptHr()) {

			$data->eContract = \hr\ContractLib::getNew();
			$data->eContract['structure'] = $data->eStructure;
			$data->eContract['reference'] = \hr\OvertimeLib::getReference($data->eStructure, \Clock::date());

		} else {
			$data->eContract = new \hr\Contract();
		}

		throw new \ViewAction($data);

	})
	/**
	 * Écrit à la main, et non par le `doCreate` de `ModulePage` : la période et son contrat passent tous deux par
	 * `build()`, puis s'écrivent ensemble dans la transaction de `PresenceLib`.
	 */
	->post('doCreate', function($data) {

		$fw = new FailWatch();

		$e = new \hr\Presence([
			'structure' => $data->eStructure
		]);

		$e->validate('canCreate', 'acceptCreate');

		$e->build(\hr\PresenceLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

		if(
			$data->eStructure->acceptHr() and
			\hr\ContractLib::isPosted()
		) {

			$e['eContract'] = new \hr\Contract();
			$e['eContract']->build(\hr\ContractLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

		}

		$fw->validate();

		\hr\PresenceLib::create($e);

		throw new BackAction('hr', 'Presence::created');

	});

new \hr\PresencePage()
	// `acceptHr()` lit le type et les drapeaux de la structure, qu'un délégué ne garantit pas au-delà de son id.
	->applyElement(function($data, \hr\Presence $e) {

		$e['structure'] = \structure\StructureLib::getById($e['structure']);

	})
	->update(function($data) {

		if($data->e['structure']->acceptHr()) {

			$data->eContract = \hr\ContractLib::getByPresence($data->e);

			if($data->eContract->empty()) {
				$data->eContract = \hr\ContractLib::getNew();
			}

			$data->eContract['structure'] = $data->e['structure'];
			$data->eContract['reference'] = \hr\OvertimeLib::getReference($data->e['structure'], \Clock::date());

		} else {
			$data->eContract = new \hr\Contract();
		}

		throw new \ViewAction($data);

	})
	->write('doUpdate', function($data, \hr\Presence $e) {

		$fw = new FailWatch();

		$e->build(\hr\PresenceLib::getPropertiesUpdate(), $_POST, new \PropertyList('update'));

		// RH éteinte, le formulaire ne porte pas le contrat, que `updateWithContract()` effacerait : la période
		// s'écrit seule, comme par l'API, et le contrat revient au rallumage.
		if($e['structure']->acceptHr() === FALSE) {

			$fw->validate();

			\hr\PresenceLib::update($e, \hr\PresenceLib::getPropertiesUpdate());

			throw new BackAction('hr', 'Presence::updated');

		}

		$eContract = new \hr\Contract();

		// Sans contrat posté, `updateWithContract()` efface celui que la période avait.
		if(\hr\ContractLib::isPosted()) {
			$eContract->build(\hr\ContractLib::getPropertiesUpdate(), $_POST, new \PropertyList('update'));
		}

		$fw->validate();

		\hr\PresenceLib::updateWithContract($e, \hr\PresenceLib::getPropertiesUpdate(), $eContract);

		throw new BackAction('hr', 'Presence::updated');

	}, validate: ['canUpdate', 'acceptUpdate'])
	->doDelete(fn() => throw new ReloadLayerAction('hr', 'Presence::deleted'));
?>
