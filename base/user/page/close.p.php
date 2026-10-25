<?php
(new Page(function($data) {

		user\ConnectionLib::checkLogged();

		$data->eUserOnline = \user\UserLib::getById($data->eUserOnline['id']);

		if(user\SignUpLib::canUpdate($data->eUserOnline)['drop'] === FALSE) {
			throw new NotExpectedAction();
		}

		$data->can = \user\DropLib::canClose();

		$data->eUserOnline['cStructureOpen'] = \hr\PresenceLib::getStructuresOpenByUser($data->eUserOnline);

	}))
	/**
	 * Form to close its account
	 */
	->get('index', fn($data) => throw new ViewAction($data))
	/**
	 * Close account
	 */
	->post('do', function($data) {

		if($data->eUserOnline['deletedAt'] === NULL and $data->can === FALSE) {
			throw new NotExpectedAction('Too late to close account', new RedirectAction('/user/close'));
		}

		$fw = new FailWatch();

		user\DropLib::changeClose($data->eUserOnline);

		$fw->validate();

		throw new ReloadAction();

	});
?>
