<?php
namespace api;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepositoryLib implements ScopeRepositoryInterface {

	public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface {

		$eScope = Scope::model()
			->select(Scope::getSelection())
			->whereName($identifier)
			->get();

		if($eScope->empty()) {
			return NULL;
		}

		return new class($eScope['name']) implements ScopeEntityInterface {

			use EntityTrait, ScopeTrait;

			public function __construct(string $name) {
				$this->identifier = $name;
			}

		};

	}

	public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = NULL, ?string $authCodeId = NULL): array {

		$eClient = Client::model()
			->select(['id'])
			->whereClientId($clientEntity->getIdentifier())
			->get();

		$allowedNames = [];
		foreach(ClientScopeLib::getScopesByClient($eClient) as $eScope) {
			$allowedNames[] = $eScope['name'];
		}

		foreach($scopes as $scope) {
			if(in_array($scope->getIdentifier(), $allowedNames, TRUE) === FALSE) {
				throw OAuthServerException::invalidScope($scope->getIdentifier());
			}
		}

		return $scopes;

	}

}
?>
