<?php
namespace api;

require_once __DIR__.'/../vendor/autoload.php';

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * La réponse de /api/oauth/token dit à quelle référence le jeton donne accès : l'utilisateur la
 * choisit au consentement, et une application qui en sert plusieurs n'a sinon aucun moyen de
 * distinguer ses jetons sans demander un scope de plus.
 */
class TokenResponseAiLib extends BearerTokenResponse {

	protected function getExtraParams(AccessTokenEntityInterface $accessToken): array {

		// persistNewAccessToken() a déjà écrit le jeton, et seule sa ligne porte la référence.
		$eAccessToken = AccessToken::model()
			->select('reference')
			->whereTokenId($accessToken->getIdentifier())
			->get();

		return ApiSetting::getTokenParams($eAccessToken['reference']);

	}

}
?>
