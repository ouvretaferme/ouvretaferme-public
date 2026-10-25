<?php
new \communication\WebinaireSubscriptionPage(function($data) {

	if(\Clock::date() >= last(\communication\CommunicationSetting::WEBINAIRE_DATES)['date'] or \communication\CommunicationSetting::IS_WEBINAIRE_ACTIVATED === FALSE) {
		throw new NotExistsAction();
	}

})
	->getCreateElement(function($data) {

		return new \communication\WebinaireSubscription([
			'user' => \user\ConnectionLib::getOnline(),
		]);

	})
	->create(function($data) {

		$data->cWebinaireSubscriptionCount = \communication\WebinaireSubscriptionLib::getCounts();
		$data->eWebinaireSubscription = \communication\WebinaireSubscriptionLib::getSubscription();

		throw new \ViewAction($data);

	})
	->doCreate(function($data) {

		throw new ReloadAction('main', 'WebinaireSubscription::created');

	})
	->doDelete(function($data) {

		throw new ReloadAction('main', 'WebinaireSubscription::deleted');

	});
