<?php
namespace api;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepositoryLib implements RefreshTokenRepositoryInterface {

	public function getNewRefreshToken(): ?RefreshTokenEntityInterface {

		return new class implements RefreshTokenEntityInterface {
			use EntityTrait, RefreshTokenTrait;
		};

	}

	public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void {

		$eAccessToken = AccessToken::model()
			->select(AccessToken::getSelection())
			->whereTokenId($refreshTokenEntity->getAccessToken()->getIdentifier())
			->get();

		$eRefreshToken = new RefreshToken([
			'tokenId' => $refreshTokenEntity->getIdentifier(),
			'accessToken' => $eAccessToken,
			'expiryDateTime' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
		]);

		RefreshToken::model()->insert($eRefreshToken);

	}

	public function revokeRefreshToken(string $tokenId): void {

		RefreshToken::model()->whereTokenId($tokenId)->update(['revoked' => TRUE]);

	}

	public function isRefreshTokenRevoked(string $tokenId): bool {

		$eRefreshToken = RefreshToken::model()
			->select([
				'revoked',
				'accessToken' => ['reference', 'name', 'revoked']
			])
			->whereTokenId($tokenId)
			->get();

		// Le jeton d'accès révoqué par le départ d'un membre ou la perte de son rôle emporte le
		// renouvellement : sans ce renvoi, une application garderait l'accès un mois de plus.
		if(
			$eRefreshToken->empty() or
			$eRefreshToken['revoked'] or
			$eRefreshToken['accessToken']['revoked']
		) {
			return TRUE;
		}

		// Relais vers AccessTokenRepositoryLib::persistNewAccessToken(), appelé juste après par league
		// pour le même échange : league ne transmet jamais le jeton d'origine à cette méthode.
		AccessTokenRepositoryLib::setPendingReference($eRefreshToken['accessToken']['reference']);
		AccessTokenRepositoryLib::setPendingName($eRefreshToken['accessToken']['name']);

		return FALSE;

	}

}
?>
