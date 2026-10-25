<?php
/**
 * La fenêtre des nouvelles CGU, chargée par le déclencheur que pose `RootTemplate::getTos()`.
 *
 * Une page à part et non un morceau de l'écran : la page demandée s'affiche d'abord, la fenêtre
 * arrive par-dessus, à chaque page tant que la version annoncée n'est ni acceptée ni reportée, et une
 * seule fois pour une version acceptée tacitement.
 */
new Page()
	->get('index', function($data) {

		\user\ConnectionLib::checkLogged();

		// Deux onglets ouverts lancent chacun la requête : si l'un a déjà accepté, l'autre ne
		// trouve plus rien à présenter, ce qui n'est pas une erreur, et la vue ne pousse rien.
		if($data->tos !== NULL) {
			$data->tosEffectiveAt = \user\TosAiLib::getLatestEffectiveAt();
		}

		// Présentée, la version acceptée tacitement ne revient plus dans les autres onglets
		if($data->tos === \user\TosAiLib::EFFECTIVE) {
			\user\TosAiLib::consumeTacit();
		}

		throw new ViewAction($data);

	})
	->post('doLater', function($data) {

		\user\ConnectionLib::checkLogged();

		$refused = POST('close', 'bool');

		if($data->tos !== NULL) {

			if($refused) {
				\user\TosAiLib::refuse();
			} else {
				\user\TosAiLib::postpone();
			}

		}

		// Refuser passe par la fermeture du compte et les exports : la fenêtre, écartée, ne les recouvre plus
		if($refused) {
			throw new RedirectAction('/user/close');
		}

		throw new ViewAction($data);

	})
	->post('doAccept', function($data) {

		\user\ConnectionLib::checkLogged();

		// La même décision que la fenêtre : un client de boutique ou une connexion « en tant que »
		// n'accepte rien, même par un POST envoyé sans elle.
		$data->accepted = ($data->tos !== NULL);

		if($data->accepted) {
			\user\TosAiLib::acceptLatest($data->eUserOnline);
		}

		throw new ViewAction($data);

	});
?>
