<?php
namespace api;

require_once __DIR__.'/../vendor/autoload.php';

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepositoryLib implements ClientRepositoryInterface {

	public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface {

		$eClient = $this->getByClientId($clientIdentifier);

		if($eClient->empty()) {
			return NULL;
		}

		return $this->toEntity($eClient);

	}

	public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool {

		$eClient = $this->getByClientId($clientIdentifier);

		if($eClient->empty()) {
			return FALSE;
		}

		if($eClient['isConfidential'] === FALSE) {
			return TRUE; // client public (PKCE) : pas de secret à vérifier
		}

		return $clientSecret !== NULL and password_verify($clientSecret, $eClient['secret']);

	}

	// Une application désactivée ne délivre plus rien : ni consentement, ni échange de code, ni
	// renouvellement, tous trois passant par ce dépôt.
	private function getByClientId(string $clientIdentifier): Client {

		return Client::model()
			->select(Client::getSelection())
			->whereClientId($clientIdentifier)
			->whereStatus(Client::ACTIVE)
			->get();

	}

	private function toEntity(Client $eClient): ClientEntityInterface {

		return new class($eClient) implements ClientEntityInterface {

			use ClientTrait, EntityTrait;

			public function __construct(Client $eClient) {
				$this->identifier = $eClient['clientId'];
				$this->name = $eClient['name'];
				$this->redirectUri = $eClient['redirectUris'];
				$this->isConfidential = $eClient['isConfidential'];
			}

		};

	}

}
?>
