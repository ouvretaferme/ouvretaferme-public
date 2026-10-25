<?php
new Page()
	->http('403', function($data) {

		\user\ConnectionLib::checkLogged();

		if(Route::getRequestedWith() === 'http') {
			$action = new ViewAction($data);
			$action->setStatusCode(403);
		} else if(Route::getRequestedWith() === 'ajax') {
			$action = new FailAction('dev\403');
		} else {
			$action = new StatusAction(403);
		}


		throw $action;

	})
	->http('404', function($data) {

		$data->error ??= dev\ErrorPhpLib::notFound();

		if(Route::getRequestedWith() === 'http') {

			$action = new ViewAction($data);
			$action->setStatusCode(404);

		} else if(Route::getRequestedWith() === 'cli') {

			// Une page introuvable ou une donnée absente est un refus : la ligne de commande sort en code 2,
			// comme un refus de la route, et non en 0 qui la ferait passer pour réussie.
			$action = new DataAction($data->error);
			$action->setExitCode(2);

		} else {

			// Le statut dit le refus, quel que soit le corps : un client qui lit le statut avant de lire la réponse ne
			// doit pas prendre une page introuvable pour une réponse reçue.
			$action = new DataAction($data->error);
			$action->setStatusCode(404);

		}

		throw $action;

	});
?>
