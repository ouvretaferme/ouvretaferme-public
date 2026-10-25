<?php
/**
 * Demo pour l'usage de OAuth
 */
new Page(function($data) {

	if(\api\ApiSetting::IS_API_ACTIVE === FALSE) {
		throw new NotExistsAction();
	}

	\user\ConnectionLib::checkLogged();

})
	->get('/api/oauth/demo', function($data) {

		$verifier = bin2hex(random_bytes(32));
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, TRUE)), '+/', '-_'), '=');

		\session\SessionLib::set('apiDemoVerifier', $verifier);

		$url = Lime::getUrl().'/api/oauth/authorize?'.http_build_query([
			'client_id' => 'test-client',
			'redirect_uri' => Lime::getUrl().'/api/oauth/demo/callback',
			'response_type' => 'code',
			'scope' => 'general:read',
			'code_challenge' => $challenge,
			'code_challenge_method' => 'S256',
			'structure' => GET('structure', 'int', 7), // ferme de test par défaut, cf. Lime.s.php:ApiRoute::autoload()
		]);

		throw new TemporaryRedirectAction($url);

	})
	->get('/api/oauth/demo/callback', function($data) {

		if(get_exists('error')) {
			throw new JsonAction(['error' => GET('error'), 'error_description' => GET('error_description', '?string')]);
		}

		$verifier = \session\SessionLib::get('apiDemoVerifier');

		$ch = curl_init(Lime::getUrl().'/api/oauth/token');
		curl_setopt_array($ch, [
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => http_build_query([
				'grant_type' => 'authorization_code',
				'client_id' => 'test-client',
				'redirect_uri' => Lime::getUrl().'/api/oauth/demo/callback',
				'code_verifier' => $verifier,
				'code' => GET('code'),
			]),
			CURLOPT_RETURNTRANSFER => TRUE,
		]);
		$token = json_decode(curl_exec($ch), TRUE);
		curl_close($ch);

		if(isset($token['access_token']) === FALSE) {
			throw new JsonAction(['step' => 'token', 'response' => $token]);
		}

		$ch = curl_init(Lime::getProtocol().'://api.'.Lime::getDomain().'/0.1beta/me');
		curl_setopt_array($ch, [
			CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token['access_token']],
			CURLOPT_RETURNTRANSFER => TRUE,
		]);
		$structure = json_decode(curl_exec($ch), TRUE);
		curl_close($ch);

		throw new JsonAction(['token' => $token, 'structure' => $structure]);

	});
?>
