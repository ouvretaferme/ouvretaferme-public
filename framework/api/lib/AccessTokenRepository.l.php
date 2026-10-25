<?php
namespace api;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepositoryLib implements AccessTokenRepositoryInterface {

	private static ?int $pendingReference = NULL;

	private static ?string $pendingName = NULL;

	public static function setPendingReference(int $reference): void {
		self::$pendingReference = $reference;
	}

	public static function setPendingName(?string $name): void {
		self::$pendingName = $name;
	}

	public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = NULL): AccessTokenEntityInterface {

		$eAccessToken = new class implements AccessTokenEntityInterface {
			use AccessTokenTrait, EntityTrait, TokenEntityTrait;
		};

		$eAccessToken->setClient($clientEntity);

		foreach($scopes as $eScope) {
			$eAccessToken->addScope($eScope);
		}

		if($userIdentifier !== NULL) {
			$eAccessToken->setUserIdentifier($userIdentifier);
		}

		return $eAccessToken;

	}

	public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {

		if(self::$pendingReference === NULL) {
			throw new \Exception('AccessTokenRepositoryLib::persistNewAccessToken() : no reference (structure) was set before persisting the token');
		}

		$reference = self::$pendingReference;
		self::$pendingReference = NULL;

		$name = self::$pendingName;
		self::$pendingName = NULL;

		$eClient = Client::model()
      ->select(Client::getSelection())
      ->whereClientId($accessTokenEntity->getClient()->getIdentifier())
      ->get();

		$eUser = \user\UserLib::getById($accessTokenEntity->getUserIdentifier());

		$eAccessToken = new AccessToken([
			'tokenId' => $accessTokenEntity->getIdentifier(),
			'client' => $eClient,
			'user' => $eUser,
			'reference' => $reference,
			'name' => $name,
			'scopes' => array_map(fn($eScope) => $eScope->getIdentifier(), $accessTokenEntity->getScopes()),
			'expiryDateTime' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
		]);

		AccessToken::model()->insert($eAccessToken);

	}

	public function revokeAccessToken(string $tokenId): void {

		AccessToken::model()->whereTokenId($tokenId)->update(AccessTokenLib::getRevokeValues());

	}

	public function isAccessTokenRevoked(string $tokenId): bool {

		$eAccessToken = AccessToken::model()
			->select(AccessToken::getSelection())
			->whereTokenId($tokenId)
			->get();

		return $eAccessToken['revoked'] ?? TRUE;

	}

}
?>
