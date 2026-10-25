<?php
namespace api;

require_once __DIR__.'/../vendor/autoload.php';

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServerLib {

	public static function getAuthorizationServer(): AuthorizationServer {

		$server = new AuthorizationServer(
			new ClientRepositoryLib(),
			new AccessTokenRepositoryLib(),
			new ScopeRepositoryLib(),
			ApiSetting::getPrivateKeyPath(),
			ApiSetting::$encryptionKey,
			new TokenResponseAiLib()
		);

		$grant = new AuthCodeGrant(
			new AuthCodeRepositoryLib(),
			new RefreshTokenRepositoryLib(),
			new \DateInterval('PT1M') // durée de vie du code d'autorisation
		);

		$grant->setRefreshTokenTTL(new \DateInterval('P1M'));

		// PKCE obligatoire pour les clients publics : comportement par défaut de la lib, non désactivé.
		$server->enableGrantType($grant, new \DateInterval('PT1H'));

		$grantRefresh = new RefreshTokenGrant(new RefreshTokenRepositoryLib());
		$grantRefresh->setRefreshTokenTTL(new \DateInterval('P1M'));

		$server->enableGrantType($grantRefresh, new \DateInterval('PT1H'));

		return $server;

	}

	public static function validateAuthorizationRequest(AuthorizationServer $server): AuthorizationRequestInterface {

		$authorizationRequest = $server->validateAuthorizationRequest(self::getRequestFromGlobals());

		// Les clés personnelles naissent sans consentement (PersonalAccessTokenLib) : leur client ne
		// délivre rien par ce chemin.
		if($authorizationRequest->getClient()->getIdentifier() === ApiSetting::PERSONAL_CLIENT_ID) {
			throw OAuthServerException::invalidClient(self::getRequestFromGlobals());
		}

		// league accepte aussi la méthode plain, où le secret de PKCE voyage en clair dans l'URL
		// d'autorisation, et ne permet pas de la retirer.
		if(
			$authorizationRequest->getCodeChallenge() !== NULL and
			$authorizationRequest->getCodeChallengeMethod() !== 'S256'
		) {
			throw OAuthServerException::invalidRequest('code_challenge_method', 'Code challenge method must be S256');
		}

		return $authorizationRequest;

	}

	public static function getResourceServer(): ResourceServer {

		return new ResourceServer(
			new AccessTokenRepositoryLib(),
			ApiSetting::getPublicKeyPath()
		);

	}

	public static function getRequestFromGlobals(): ServerRequestInterface {

		$psr17Factory = new Psr17Factory();

		return new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory)->fromGlobals();

	}

	public static function getResponse(): ResponseInterface {

		return new \Nyholm\Psr7\Response();

	}

}

/**
 * Rejoue une réponse PSR-7 (générée par league/oauth2-server) via le système d'Action du framework.
 */
class PsrResponseAction extends \Action {

	public function __construct(
		private ResponseInterface $response
	) {
		parent::__construct();
	}

	public function run(): void {

		http_response_code($this->response->getStatusCode());

		foreach($this->response->getHeaders() as $name => $values) {
			foreach($values as $value) {
				header($name.': '.$value, FALSE);
			}
		}

		echo (string)$this->response->getBody();

	}

}
?>
