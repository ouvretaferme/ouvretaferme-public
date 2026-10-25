<?php
/**
 * Ce que l'onglet fait lui-même, dans sa session d'onglet : prendre le compte choisi pour lui
 * (`/user/admin/agent:doLogin`), et le rendre. La session d'onglet part sans compte : ce qui autorise
 * le compte est le jeton, pas la session.
 *
 * Et ce qu'un agent fait quand il a déconnecté le navigateur du compte du poste : la commande
 * `user/tab:browserToken` lui donne l'adresse qui le reconnecte, sans mot de passe.
 */
new Page(function($data) {

	if(\session\SessionLib::isTabEnabled() === FALSE) {
		throw new NotExistsAction();
	}

})
	->post('doLogin', function($data) {

		// Hors session d'onglet, la connexion remplacerait le compte de tous les onglets du navigateur
		if(\session\SessionLib::isTab() === FALSE) {
			throw new NotAllowedAction();
		}

		$fw = new FailWatch();

		\user\TabLib::logInByToken(POST('token'));

		$fw->validate();

		throw new RedirectAction('/');

	})
	->post('doQuit', function($data) {

		if(\session\SessionLib::isTab()) {
			\user\TabLib::logOut($data->eUserOnline);
		}

		throw new ViewAction($data);

	})
	->cli('browserToken', function($data) {

		$eUser = \user\TabLib::getWorkstationUser();

		if($eUser->empty()) {
			echo "Error: no workstation account, ouvretaferme/secret.c.php must call \\user\\TabLib::setWorkstationUser()\n";
			exit(2);
		}

		echo \Lime::getUrl().'/user/tab:browser?token='.\user\TabLib::createBrowserToken($eUser)."\n";

	})
	// Une session d'onglet n'atteint pas le cookie du navigateur, que ces deux routes reconnectent : l'adresse s'ouvre dans un onglet neuf
	->get('browser', function($data) {

		if(\session\SessionLib::isTab()) {
			throw new NotAllowedAction();
		}

		$data->token = GET('token');

		throw new ViewAction($data);

	})
	->post('doBrowser', function($data) {

		if(\session\SessionLib::isTab()) {
			throw new NotAllowedAction();
		}

		$fw = new FailWatch();

		\user\TabLib::logInBrowserByToken(POST('token'));

		$fw->validate();

		throw new RedirectAction('/');

	});
?>
