<?php
new Page()
	/**
	 * Handle the post page which send the email confirmation mail.
	 */
	->post('doSend', function($data) {

		// Check that user is logged in
		user\ConnectionLib::checkLogged();

		$fw = new FailWatch();

		\user\UserLib::triggerSendVerifyEmail($data->eUserOnline, FALSE);

		$fw->validate();

		throw new ReloadAction('user', 'User::forgottenPasswordSend');

	})
	/**
	 * Handle get call to /user/verify:check
	 *
	 * L'ancienne adresse /mail/verify:check reste servie : les e-mails de confirmation envoyés avant
	 * que la page quitte base/mail la donnent.
	 */
	->get(['check', '/mail/verify:check'], function($data) {

		/***
		 * Strange case where email is NULL but user has got a hash to verify the email
		 **/
		if(\user\ConnectionLib::isLogged() and $data->eUserOnline['email'] === NULL) {
			throw new RedirectAction('/');
		}

		$fw = new FailWatch();

		\user\EmailLib::validate(GET('hash'));

		// La page est publique et sans couche ajax : un code refusé se dit sur l'accueil, pas par une
		// FailAction, qui n'a ici aucun formulaire où se poser
		$fw->validate(onKo: fn(\FailWatch $fw) => throw new RedirectAction('/?error='.$fw->getLast()));

		throw new ViewAction($data, ':check');

	});

?>
