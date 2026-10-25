<?php
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		\structure\StructureLib::load($data);

		$data->eStructure->validate('canManage', 'acceptApi');

		if($data->eStructure->isApiPaid()) {
			\structure\StructureLib::validateSoftware($data, 'api');
		}

	})
	->get('manage', function($data) {

		$cAccessToken = \api\AccessTokenLib::getByReference($data->eStructure['id']);
		$data->limitsReached = \api\RateLimitLib::getLimitsReached($data->eStructure['id']);
		$data->dayLimit = \api\ApiSetting::getDayLimit($data->eStructure['id']);
		$data->logPage = GET('page', 'int');
		$data->logNumber = 50;

		list($data->cLog, $data->nLog) = \api\LogLib::getByReference($data->eStructure['id'], $data->logPage, $data->logNumber);

		$data->nLogPage = $data->nLog / $data->logNumber;

		$now = \Clock::datetime();
		$soon = date('Y-m-d H:i:s', \Clock::shift('+1 month'));

		foreach($cAccessToken as $eAccessToken) {

			$eAccessToken['accesses'] = \structure\ApiKeyLib::getAccesses($eAccessToken);
			$eAccessToken['isExpired'] = ($eAccessToken['expiryDateTime'] < $now);
			$eAccessToken['expiresSoon'] = ($eAccessToken['isExpired'] === FALSE and $eAccessToken['expiryDateTime'] < $soon);

		}

		$data->cAccessToken = $cAccessToken->find(fn($eAccessToken) => $eAccessToken['revoked'] === FALSE);
		$data->cAccessTokenRevoked = $cAccessToken->find(fn($eAccessToken) => $eAccessToken['revoked'] === TRUE);

		$data->cAccessTokenApplication = \api\AccessTokenLib::getApplicationsByReference($data->eStructure['id']);

		foreach($data->cAccessTokenApplication as $eAccessToken) {
			$eAccessToken['accesses'] = \structure\ApiKeyLib::getAccesses($eAccessToken);
		}

		if(\session\SessionLib::exists('apiKeyJwt')) {
			$data->jwt = \session\SessionLib::get('apiKeyJwt');
		} else {
			$data->jwt = NULL;
		}

		if($data->jwt !== NULL) {
			\session\SessionLib::delete('apiKeyJwt');
		}

		throw new DynamicAction($data);

	})
	->get('logResponse', function($data) {

		$data->eLog = \api\LogLib::getByIdAndReference(GET('id', 'int'), $data->eStructure['id']);

		if($data->eLog->empty()) {
			throw new NotExistsAction();
		}

		throw new ViewAction($data);

	})
	->get('create', function($data) {

		$data->eAccessTokenRenew = new \api\AccessToken();

		$renew = GET('renew', '?int');

		// Renouvellement d'une clé expirée : canRevoke() vérifie que la clé appartient bien à l'utilisateur connecté sur cette structure
		if($renew !== NULL) {

			$data->eAccessTokenRenew = \api\AccessTokenLib::getById($renew)->validate('canRevoke');
			$data->eAccessTokenRenew['accesses'] = \structure\ApiKeyLib::getAccesses($data->eAccessTokenRenew);

		}

		throw new ViewAction($data);

	})
	->post('doCreate', function($data) {

		$name = POST('name');
		$scopeNames = array_filter(POST('scopes', 'array', []));

		$allowedScopes = $data->eStructure->getApiScopes();

		foreach($scopeNames as $scopeName) {
			if(in_array($scopeName, $allowedScopes, TRUE) === FALSE) {
				throw new NotExpectedAction();
			}
		}

		// Le scope write donne aussi accès aux routes en read
		foreach($scopeNames as $scopeName) {

			if(str_ends_with($scopeName, ':write')) {

				$readScope = substr($scopeName, 0, -strlen(':write')).':read';

				if(in_array($readScope, $scopeNames, TRUE) === FALSE) {
					$scopeNames[] = $readScope;
				}

			}

		}

		$fw = new FailWatch();

		$jwt = \api\PersonalAccessTokenLib::issue($data->eUserOnline, $name, $data->eStructure['id'], $scopeNames);

		$fw->validate();

		\session\SessionLib::set('apiKeyJwt', $jwt);

		throw new ReloadAction('api', 'ApiKey::created');

	})
	->post('doRevoke', function($data) {

		$eAccessToken = \api\AccessTokenLib::getById(POST('id'))->validate('canRevoke', 'acceptRevoke');

		\api\AccessTokenLib::revoke($eAccessToken);

		throw new ReloadAction('api', 'ApiKey::revoked');

	})
	->post('doRevokeApplication', function($data) {

		$cAccessTokenApplication = \api\AccessTokenLib::getApplicationsByReference($data->eStructure['id']);

		$eAccessToken = $cAccessTokenApplication->find(fn($eAccessToken) => (
			$eAccessToken['client']['id'] === POST('client', 'int') and
			$eAccessToken['user']['id'] === POST('user', 'int')
		))->first();

		if($eAccessToken === NULL) {
			throw new NotExpectedAction();
		}

		\api\AccessTokenLib::revokeApplication($eAccessToken['client'], $eAccessToken['user'], $data->eStructure['id']);

		throw new ReloadAction('api', 'ApiKey::applicationRevoked');

	});
?>
