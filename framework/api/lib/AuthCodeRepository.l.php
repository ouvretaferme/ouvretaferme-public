<?php
namespace api;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepositoryLib implements AuthCodeRepositoryInterface {

	use \Notifiable;

	public function getNewAuthCode(): AuthCodeEntityInterface {

		return new class implements AuthCodeEntityInterface {
			use EntityTrait, TokenEntityTrait, AuthCodeTrait;
		};

	}

	public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void {

		$eClient = Client::model()
			->select(Client::getSelection())
			->whereClientId($authCodeEntity->getClient()->getIdentifier())
			->get();

		$eUser = \user\UserLib::getById($authCodeEntity->getUserIdentifier());

		$eAuthCode = new AuthCode([
			'code' => $authCodeEntity->getIdentifier(),
			'client' => $eClient,
			'user' => $eUser,
			'reference' => ApiSetting::getReference(),
			'scopes' => array_map(fn($eScope) => $eScope->getIdentifier(), $authCodeEntity->getScopes()),
			'redirectUri' => $authCodeEntity->getRedirectUri() ?? '',
			'expiryDateTime' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
		]);

		AuthCode::model()->insert($eAuthCode);

		// league ne persiste un code qu'une fois le consentement donné : c'est le geste d'autorisation
		self::notify('applicationAuthorize', $eClient, $eAuthCode['reference'], $eAuthCode['scopes']);

	}

	public function revokeAuthCode(string $codeId): void {

		AuthCode::model()->whereCode($codeId)->update(['revoked' => TRUE]);

	}

	public function isAuthCodeRevoked(string $codeId): bool {

		$eAuthCode = AuthCode::model()
			->select(AuthCode::getSelection())
			->whereCode($codeId)
			->get();

		// Relais vers AccessTokenRepositoryLib::persistNewAccessToken(), appelé juste après par league
		// pour le même échange : league ne transmet jamais l'AuthCode d'origine à cette méthode.
		if($eAuthCode->notEmpty()) {
			AccessTokenRepositoryLib::setPendingReference($eAuthCode['reference']);
		}

		return $eAuthCode['revoked'] ?? TRUE;

	}

}
?>
