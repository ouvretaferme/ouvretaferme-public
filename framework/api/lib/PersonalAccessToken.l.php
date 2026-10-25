<?php
namespace api;

require_once __DIR__.'/../vendor/autoload.php';

use League\OAuth2\Server\CryptKey;

/**
 * Émission de clés API personnelles : un token Bearer identique dans sa forme (JWT signé RS256)
 * à un access token OAuth, mais délivré directement à l'utilisateur déjà authentifié, sans passer
 * par /api/oauth/authorize (pas de client tiers, pas de consentement). Rattaché au client réservé
 * ApiSetting::PERSONAL_CLIENT_ID (Client::isPersonal = TRUE), ce qui permet de le stocker dans la
 * même table AccessToken et donc dans le même Log que les tokens OAuth.
 */
class PersonalAccessTokenLib {

	use \Notifiable;

	const TTL = 'P1Y';

	public static function issue(\user\User $eUser, string $name, int $reference, array $scopeNames): ?string {

		if(empty($scopeNames)) {
			\Fail::log('ApiKey::scopes.empty');
			return NULL;
		}

		// La paire RSA n'est pas versionnée (*.pem est ignoré par git) : sur un serveur qui ne l'a
		// jamais générée, la bibliothèque OAuth échoue sur « Invalid key supplied ». Cf. api/configure/keyAi.
		if(is_file(ApiSetting::getPrivateKeyPath()) === FALSE) {
			\Fail::log('ApiKey::internal');
			trigger_error('Missing API private key, run api/configure/keyAi');
			return NULL;
		}

		$eClient = ClientLib::getPersonalClient();

		if($eClient->empty()) {
			\Fail::log('ApiKey::internal');
			trigger_error('Personal access client not configured');
			return NULL;
		}

		$allowedNames = [];
		foreach(ClientScopeLib::getScopesByClient($eClient) as $eScope) {
			$allowedNames[] = $eScope['name'];
		}

		$scopeRepository = new ScopeRepositoryLib();
		$scopes = [];

		foreach($scopeNames as $scopeName) {

			if(in_array($scopeName, $allowedNames, TRUE) === FALSE) {
				\Fail::log('ApiKey::internal');
				trigger_error('Unknown scope \''.$scopeName.'\'');
				return NULL;
			}

			$scopes[] = $scopeRepository->getScopeEntityByIdentifier($scopeName);

		}

		$clientEntity = new ClientRepositoryLib()->getClientEntity($eClient['clientId']);

		$accessTokenRepository = new AccessTokenRepositoryLib();

		$accessToken = $accessTokenRepository->getNewToken($clientEntity, $scopes, (string)$eUser['id']);
		$accessToken->setIdentifier(bin2hex(random_bytes(40)));
		$accessToken->setExpiryDateTime(new \DateTimeImmutable('@'.\Clock::machine())->setTimezone(new \DateTimeZone(date_default_timezone_get()))->add(new \DateInterval(self::TTL)));
		$accessToken->setPrivateKey(new CryptKey(ApiSetting::getPrivateKeyPath()));

		AccessTokenRepositoryLib::setPendingReference($reference);
		AccessTokenRepositoryLib::setPendingName($name);
		$accessTokenRepository->persistNewAccessToken($accessToken);

		self::notify('keyCreate', $name, $reference, $scopeNames);

		return $accessToken->toString();

	}

}
?>
