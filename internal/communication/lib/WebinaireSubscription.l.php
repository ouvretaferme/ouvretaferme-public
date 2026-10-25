<?php
namespace communication;

class WebinaireSubscriptionLib extends WebinaireSubscriptionCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['user', 'firstName', 'lastName', 'email', 'date'];
	}

	public static function getSubscription(): WebinaireSubscription {

		$eUserConnected = \user\ConnectionLib::getOnline();

		if($eUserConnected->empty()) {
			return new WebinaireSubscription();
		}

		return WebinaireSubscription::model()
			->select(WebinaireSubscription::getSelection())
			->whereEmail($eUserConnected['email'])
			->get();

	}

	public static function getCounts(): \Collection {

		return WebinaireSubscription::model()
			->select(['date', 'count' => new \Sql('COUNT(*)', 'int')])
			->group(['date'])
			->getCollection(index: 'date');

	}

}
