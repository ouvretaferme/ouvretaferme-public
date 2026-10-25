<?php
new Page()
	/**
	 * Run forgotten password
	 */
	->post('do', function($data) {

		$fw = new FailWatch;

		$email = POST('email');

		user\ThrottleAiLib::checkForgotten($email);

		$fw->validate();

		$eUserAuth = user\UserLib::checkForgottenPasswordLink($email);

		$fw->validate();

		if($eUserAuth !== NULL) {
			user\UserLib::sendForgottenPasswordLink($eUserAuth);
		}

		throw new ReloadAction('user', 'User::forgottenPasswordRequested');

	})
	/**
	 * Page to reset the password
	 *
	 */
	->get('set', function($data) {

		$hash = GET('hash');
		$email = GET('email');

		$fw = new FailWatch;

		$eUser = user\UserLib::getUserByHashAndEmail($hash, $email);

		if($fw->ok()) {

			$data->hash = $hash;
			$data->email = $eUser['email'];

			throw new ViewAction($data);

		} else {

			throw new ViewAction($data, ':setFailed');

		}

	})
	/**
	 * Change password
	 */
	->post('doReset', function($data) {

		$hash = POST('hash');
		$email = POST('email');

		$fw = new FailWatch;

		$eUser = user\UserLib::getUserByHashAndEmail($hash, $email);

		$fw->validate();

		user\SignUpLib::matchBasicPassword('reset', $eUser, $_POST);

		$fw->validate();

		user\SignUpLib::updatePassword($eUser);
		user\UserLib::cleanForgottenPasswordHashByUser($eUser);

		$fw->validate();

		throw new RedirectAction('/?success=user\\User::passwordReset');

	});
?>
