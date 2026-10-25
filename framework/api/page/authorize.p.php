<?php
new Page(function($data) {

	if(\api\ApiSetting::IS_API_ACTIVE === FALSE) {
		throw new NotExistsAction();
	}

	\user\ConnectionLib::checkLogged();

})
	->get('/api/oauth/authorize', function($data) {

		$authServer = \api\ServerLib::getAuthorizationServer();

		try {
			$data->authorizationRequest = \api\ServerLib::validateAuthorizationRequest($authServer);
		} catch(\League\OAuth2\Server\Exception\OAuthServerException $exception) {
			throw new \api\PsrResponseAction($exception->generateHttpResponse(\api\ServerLib::getResponse()));
		}

		// Après la validation et non avant : la demande validée nomme l'application, que le choix
		// d'une structure peut afficher, et une demande invalide repart vers l'application sans
		// qu'on ait rien demandé à la personne.
		\api\ApiSetting::resolveReferenceContext($data);

		throw new ViewAction($data);

	})
	->post('/api/oauth/authorize', function($data) {

		$authServer = \api\ServerLib::getAuthorizationServer();

		try {
			$authorizationRequest = \api\ServerLib::validateAuthorizationRequest($authServer);
		} catch(\League\OAuth2\Server\Exception\OAuthServerException $exception) {
			throw new \api\PsrResponseAction($exception->generateHttpResponse(\api\ServerLib::getResponse()));
		}

		\api\ApiSetting::resolveReferenceContext($data);

		$eUser = \user\ConnectionLib::getOnline();

		$authorizationRequest->setUser(new class($eUser['id']) implements \League\OAuth2\Server\Entities\UserEntityInterface {

			public function __construct(
				private string|int $id
			) {}

			public function getIdentifier(): string {
				return (string)$this->id;
			}

		});

		$authorizationRequest->setAuthorizationApproved(POST('approved', 'bool'));

		try {
			$response = $authServer->completeAuthorizationRequest($authorizationRequest, \api\ServerLib::getResponse());
		} catch(\League\OAuth2\Server\Exception\OAuthServerException $exception) {
			throw new \api\PsrResponseAction($exception->generateHttpResponse(\api\ServerLib::getResponse()));
		}

		throw new \api\PsrResponseAction($response);

	});
?>
