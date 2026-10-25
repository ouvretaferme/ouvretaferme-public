<?php
new Page()
	/**
	 * Display login form
	 */
	->get('form', function($data) {

		$data->email = NULL;

		user\ConnectionLib::checkAnonymous();

		if(get_exists('invite')) {

			$eInvite = \structure\InviteLib::getByKey(GET('invite'));

			if($eInvite->isValid()) {
				$data->email = $eInvite['email'];
			}

		}

		$data->withSignUp = GET('withSignUp', 'bool', FALSE);

		// Le gabarit d'une vue est fixé par sa signature : la connexion demandée par ajax s'ouvre en
		// panneau par-dessus la page en cours, et toute autre ouverture (adresse tapée, redirection,
		// rechargement) a besoin de la page entière, qu'un panneau rendu seul ne sait pas dessiner.
		match(Route::getRequestedWith()) {
			'ajax' => throw new ViewAction($data, ':formPanel'),
			default => throw new ViewAction($data)
		};

	})
	/**
	 * Display forgotten password form
	 */
	->get('forgottenPassword', function($data) {

		user\ConnectionLib::checkAnonymous();

		$data->email = GET('email');

		throw new ViewAction($data);

	})
	/**
	 * Log in
	 */
	->post('in', function($data) {

		$fw = new FailWatch;

		$remember = POST('remember', 'bool');

		$loggedIn = user\ConnectionLib::logIn(
			POST('login'),
			POST('password'),
			['remember' => $remember, 'redirect' => POST('redirect', '?string')]
		);

		$fw->validate();

		if($loggedIn === FALSE) {
			throw new RedirectAction(Lime::getUrl().'/user/log:code');
		}

		$redirect = \user\ConnectionLib::getRedirectLogin();

		if($redirect) {
			throw new RedirectAction($redirect.'success=user\\User::welcome');
		} else {
			throw new RedirectAction(Lime::getUrl().'?success=user\\User::welcome');
		}

	})
	->get('code', function($data) {

		user\ConnectionLib::checkAnonymous();

		$data->eChallenge = user\ChallengeAiLib::getPending();

		if($data->eChallenge->notEmpty()) {
			throw new ViewAction($data);
		}

		$data->eChallenge = user\ChallengeAiLib::getFromSession();

		if($data->eChallenge->empty()) {
			throw new RedirectAction('/user/log:form');
		}

		$data->eChallenge['acceptResend'] = user\ChallengeAiLib::acceptResend($data->eChallenge);

		throw new ViewAction($data, ':codeExpired');

	})
	->post('doCode', function($data) {

		user\ConnectionLib::checkAnonymous();

		$fw = new FailWatch;

		$eChallenge = user\ChallengeAiLib::getPending();

		if($eChallenge->empty()) {
			throw new RedirectAction(Lime::getUrl().'/user/log:code');
		}

		user\ChallengeAiLib::verifyCode($eChallenge, POST('code', '?string'));

		if($fw->has('Challenge::exceeded')) {
			throw new RedirectAction(Lime::getUrl().'/user/log:form?error=user\\Challenge::exceeded');
		}

		$fw->validate();

		user\ConnectionLib::logInUser($eChallenge['user'], $eChallenge['remember']);

		$fw->validate();

		$redirect = \user\ConnectionLib::getRedirectLogin();

		if($redirect) {
			throw new RedirectAction($redirect.'success=user\\User::welcome');
		} else {
			throw new RedirectAction(Lime::getUrl().'?success=user\\User::welcome');
		}

	})
	->post('doResend', function($data) {

		user\ConnectionLib::checkAnonymous();

		$fw = new FailWatch;

		$eChallenge = user\ChallengeAiLib::getFromSession();

		if($eChallenge->empty()) {
			throw new RedirectAction(Lime::getUrl().'/user/log:form');
		}

		user\ChallengeAiLib::resend($eChallenge);

		$fw->validate();

		throw new RedirectAction(Lime::getUrl().'/user/log:code?success=user\\Challenge::sent');

	})
	->get('link', function($data) {

		user\ConnectionLib::checkAnonymous();

		$data->token = GET('token', '?string');
		$data->eChallenge = user\ChallengeAiLib::getByToken($data->token);

		if($data->eChallenge->empty()) {
			throw new ViewAction($data, ':linkExpired');
		}

		if($data->eChallenge['isSameBrowser'] === FALSE) {
			throw new ViewAction($data, ':linkElsewhere');
		}

		throw new ViewAction($data);

	})
	->post('doLink', function($data) {

		user\ConnectionLib::checkAnonymous();

		$fw = new FailWatch;

		$eChallenge = user\ChallengeAiLib::getByToken(POST('token', '?string'));

		if($eChallenge->empty()) {
			throw new RedirectAction(Lime::getUrl().'/user/log:form?error=user\\Challenge::linkExpired');
		}

		if($eChallenge['isSameBrowser'] === FALSE) {
			user\Challenge::fail('linkElsewhere');
		}

		$fw->validate();

		user\ChallengeAiLib::succeed($eChallenge, user\Challenge::LINK);

		user\ConnectionLib::logInUser($eChallenge['user'], $eChallenge['remember']);

		$fw->validate();

		$redirect = \user\ConnectionLib::getRedirectLogin();

		if($redirect) {
			throw new RedirectAction($redirect.'success=user\\User::welcome');
		} else {
			throw new RedirectAction(Lime::getUrl().'?success=user\\User::welcome');
		}

	})
	->get('loginLink', function($data) {

		$data->token = GET('token');
		$data->eLoginLink = user\LoginLinkAiLib::getByToken($data->token);

		if(
			user\ConnectionLib::isLogged() or
			$data->eLoginLink->empty()
		) {
			throw new NotExistsAction();
		}

		throw new ViewAction($data);

	})
	->post('doLoginLink', function($data) {

		$token = POST('token');
		$eLoginLink = user\LoginLinkAiLib::getByToken($token);

		if(
			user\ConnectionLib::isLogged() or
			$eLoginLink->empty() or
			user\LoginLinkAiLib::succeed($eLoginLink) === FALSE
		) {
			throw new RedirectAction(user\LoginLinkAiUi::getLink($token));
		}

		throw new RedirectAction(Lime::getUrl().'?success=user\\User::welcome');

	})
	/**
	 * Log out
	 */
	->post('out', function($data) {

		user\ConnectionLib::checkLogged();

		$eUser = user\ConnectionLib::getOnline();

		user\ConnectionLib::logOut($eUser);

		throw new RedirectAction(Lime::getRedirect(POST('redirect', '?string')).'?success=user\\User::bye');

	})
	/**
	 * Log in external
	 */
	->post('doLoginExternal', function($data) {

		\user\ConnectionLib::getOnline()->validateAdmin();

		$eUser = \user\UserLib::getById(
				POST('user', '?int')
			)
			->validate();

		if(user\ConnectionLib::logInExternal($eUser, $data->eUserOnline) === TRUE) {
			throw new RedirectAction(Lime::getRedirect(POST('redirect', '?string')));
		}

		throw new VoidAction();

	})
	/**
	 * Log from an external login
	 */
	->post('doLogoutExternal', function($data) {

		if(user\ConnectionLib::logOutExternal()) {
			throw new RedirectAction('/');
		}

		throw new VoidAction();

	});
?>
