<?php
new Page(function($data) {

	if(\api\ApiSetting::IS_API_ACTIVE === FALSE) {
		throw new NotExistsAction();
	}

})
	->post('/api/oauth/token', function($data) {

		$authServer = \api\ServerLib::getAuthorizationServer();

		try {
			$response = $authServer->respondToAccessTokenRequest(\api\ServerLib::getRequestFromGlobals(), \api\ServerLib::getResponse());
		} catch(\League\OAuth2\Server\Exception\OAuthServerException $exception) {
			throw new \api\PsrResponseAction($exception->generateHttpResponse(\api\ServerLib::getResponse()));
		}

		throw new \api\PsrResponseAction($response);

	});
?>
