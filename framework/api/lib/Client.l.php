<?php
namespace api;

class ClientLib extends ClientCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'redirectUris', 'cScope'];
	}

	public static function getAll(): \Collection {

		return Client::model()
			->select(Client::getSelection() + [
				'cScope' => ClientScopeLib::delegateByClient(),
			])
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	// Client réservé qui porte les clés API personnelles (hors flux OAuth authorize/PKCE)
	public static function getPersonalClient(): Client {

		return Client::model()
			->select(Client::getSelection())
			->whereClientId(ApiSetting::PERSONAL_CLIENT_ID)
			->get();

	}

	public static function getThirdParty(): \Collection {

		return Client::model()
			->select(['id', 'name'])
			->whereIsPersonal(FALSE)
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

	public static function create(Client $e): void {

		$e->expects(['name', 'redirectUris', 'cScope']);

		// L'identifiant circule en clair dans chaque URL d'autorisation : tiré au hasard, il ne dit
		// rien des autres applications et ne se devine pas depuis le nom.
		$e['clientId'] = bin2hex(random_bytes(16));
		$e['isConfidential'] = FALSE;
		$e['isPersonal'] = FALSE;
		$e['status'] = Client::ACTIVE;

		Client::model()->beginTransaction();

		parent::create($e);

		foreach($e['cScope'] as $eScope) {
			ClientScopeLib::create(new ClientScope([
				'client' => $e,
				'scope' => $eScope,
			]));
		}

		Client::model()->commit();

	}

	public static function disable(Client $e): void {

		$e->expects(['id', 'isPersonal', 'status']);

		Client::model()->beginTransaction();

		$e['status'] = Client::DISABLED;
		parent::update($e, ['status']);

		AccessTokenLib::revokeByClient($e);

		Client::model()->commit();

	}

	public static function enable(Client $e): void {

		$e->expects(['id', 'isPersonal', 'status']);

		$e['status'] = Client::ACTIVE;
		parent::update($e, ['status']);

	}

}
?>
