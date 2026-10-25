<?php
namespace api;

class ClientScopeLib extends ClientScopeCrud {

	public static function getScopesByClient(Client $eClient): \Collection {

		return ClientScope::model()
			->select(['scope' => Scope::getSelection()])
			->whereClient($eClient)
			->getCollection()
			->getColumnCollection('scope');

	}

	public static function delegateByClient(): ClientScopeModel {

		return ClientScope::model()
			->select(['scope' => Scope::getSelection()])
			->delegateCollection('client', callback: fn(\Collection $cClientScope) => $cClientScope->getColumnCollection('scope'));

	}

}
?>
