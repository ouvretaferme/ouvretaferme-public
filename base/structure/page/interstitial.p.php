<?php
/**
 * Le contenu d'un interstitiel, servi en panel.
 *
 * Une page à part et non un bout de la page qui le demande : le panel se charge en
 * ajax, donc l'écran d'origine s'affiche d'abord et la modale arrive par-dessus.
 * L'inverse — rendre la modale dans le flux — la ferait clignoter au chargement.
 */
new \structure\StructurePage()
	->read('/ferme/{id}/interstitiel/{name}', function($data) {

		$data->name = GET('name', 'string');

		// `accept()` refuse un nom inconnu comme un interstitiel déjà vu : l'URL est
		// publique et se rejoue, elle ne doit pas ressortir un message consommé.
		if(\structure\InterstitialLib::accept($data->e, $data->name) === FALSE) {
			throw new NotExpectedAction('Unknown or already seen interstitial');
		}

		// Vu au moment où le contenu part, pas au chargement de la page qui l'appelle.
		\structure\InterstitialLib::ping($data->e, $data->name);

		throw new ViewAction($data);

	}, validate: ['canManage']);
?>
