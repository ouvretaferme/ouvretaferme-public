<?php
new Page(function($data) {

		$data->eUser = \user\UserLib::getNew();

		$data->chooseRole = TRUE;

		switch($data->__page['name']) {

			case '/inscription' :
				break;

			case '/inscription/client' :
				$data->eUser['roleCustomer'] = TRUE;
				break;

			case '/inscription/producteur' :
				$data->eUser['roleProducer'] = TRUE;
				break;

			case '/inscription/accompagnement' :
				$data->chooseRole = FALSE;
				$data->eUser['roleConsultant'] = TRUE;
				break;

			case '/inscription/formation' :
				$data->chooseRole = FALSE;
				$data->eUser['roleTeacher'] = TRUE;
				break;

			default :
				throw new UnsupportedException();

		}

		if($data->eUser['roleCustomer']) {
			$data->eUser['type'] = \user\User::GET('type', 'type', \user\User::PRIVATE);
		}

	})
	/**
	 * Display sign up form
	 */
	->get([
		'/inscription',
		'/inscription/client',
		'/inscription/producteur',
		'/inscription/accompagnement',
		'/inscription/formation',
		], function($data) {

		user\ConnectionLib::checkAnonymous();

		user\ConnectionLib::loadSignUp($data);

		throw new ViewAction($data, ':signUp');

	})
	/**
	 * Run sign up
	 */
	->post([
		'/inscription',
		'/inscription/client',
		'/inscription/producteur',
		'/inscription/accompagnement',
		'/inscription/formation',
		], function($data) {

		$redirect = Lime::getRedirect(POST('redirect', '?string'), Lime::getUrl());

		user\SignUpLib::createBasic($data->eUser, $_POST);

		if(post_exists('invite')) {
			$eInvite = \structure\InviteLib::getByKey(POST('invite'));
			if($eInvite->notEmpty()) {
				if($eInvite->isAmapProducer()) {
					$redirect = $eInvite->getLink();
				} else {
					\structure\InviteLib::accept($eInvite, $data->eUser);
				}
			}
		}

		user\ConnectionLib::logInUser($data->eUser, POST('remember', 'bool'));

		$redirect = \util\HttpUi::setArgument($redirect, 'success', 'user\\User::welcomeCreate');

		throw new RedirectAction($redirect);

	});

new Page(function($data) {

		$data->eUser = \user\UserLib::getNew()->merge([
			'roleCustomer' => TRUE,
			'type' => \user\User::GET('type', 'type')
		]);

	})
	->get('getCustomerFields', function($data) {

		$data->eInvite = \structure\InviteLib::getByKey(GET('invite'));

		throw new ViewAction($data);

	});
?>
