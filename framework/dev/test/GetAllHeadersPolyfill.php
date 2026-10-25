<?php

/**
 * getallheaders() est fourni par le SAPI web (Apache/FPM), pas par le SAPI CLI. Le vrai serveur
 * API (FPM) n'en a jamais besoin ; seuls les tests, qui appellent \ApiRoute::run() directement en
 * CLI (cf dev\ApiTest), en ont besoin — d'où ce polyfill scoppé aux tests plutôt qu'un correctif
 * dans framework/core/Api.php ou framework/api/lib/Log.l.php (socle API, pas notre code).
 *
 * Grâce à la résolution de namespace de PHP, api\LogLib::createFromRequest() (namespace api, appel
 * non qualifié getallheaders()) retombera sur cette fonction globale si aucune n'existe déjà
 * (donc jamais en prod/FPM, seulement en CLI où elle est réellement absente).
 *
 * Elle dérive les en-têtes de $_SERVER au lieu de rendre un tableau vide, parce que
 * ServerRequestCreator::fromGlobals() la préfère à sa propre lecture de $_SERVER dès qu'elle
 * existe (nyholm/psr7-server, ServerRequestCreator:52) : un polyfill qui rend [] masque alors tout
 * en-tête qu'un test pose, Authorization compris, et l'appel part sans jeton sans que rien ne le
 * dise.
 *
 * CONTENT_TYPE et CONTENT_LENGTH se traitent à part : CGI les pose dans $_SERVER sans le préfixe
 * HTTP_, et le vrai getallheaders() de FPM les rend pourtant comme les autres.
 */
if(function_exists('getallheaders') === FALSE) {

	function getallheaders(): array {

		$headers = [];

		foreach($_SERVER as $name => $value) {

			if(str_starts_with($name, 'HTTP_')) {
				$name = substr($name, 5);
			} else if(in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH']) === FALSE) {
				continue;
			}

			$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = $value;

		}

		return $headers;

	}

}
?>
