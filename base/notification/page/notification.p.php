<?php
/**
 * ⚠️ Le gabarit d'une ferme ne sait pas s'afficher sans section, et un membre qui n'en ouvre aucune
 * n'a aucun écran de la structure. Ceux d'un groupe et d'une AMAP n'en demandent pas.
 */
function checkNotificationAddress(stdClass $data): void {

	$isCommunity = ($data->eStructure['type'] === \structure\Structure::COMMUNITY);

	if($isCommunity !== str_starts_with(Page::getName(), '/groupe/')) {
		throw new NotExistsAction();
	}

	if($isCommunity or $data->eStructure->isAmap()) {
		$data->section = NULL;
		return;
	}

	$data->section = \notification\NotificationLib::getSection($data->eStructure, GET('section'));

	if($data->section === NULL) {
		throw new NotExistsAction();
	}

}

new Page(function($data) {

	$data->eStructure = \structure\StructureLib::getById(REQUEST('id'))->validate('canRead');

	if(\notification\NotificationSetting::isEnabled($data->eStructure) === FALSE) {
		throw new NotExistsAction();
	}

	// Les notifications se reçoivent par adhésion : un lecteur qui n'est pas membre n'en a aucune.
	if(array_key_exists($data->eStructure['id'], $data->notificationsUnseen) === FALSE) {
		throw new NotExistsAction();
	}

	// Le compteur de l'adhésion compte les non vues, pas les non lues (cf. `NotificationLib`) : c'est
	// lui que la cloche porte, et il s'éteint dès que le centre ou le menu a montré ce qu'il compte.
	$data->unseen = $data->notificationsUnseen[$data->eStructure['id']];

	// Seule une connexion migre la base d'une structure, où vit la table des notifications. Aucun écran
	// de groupe ne se connecte : sans cet appel, une colonne neuve n'y arrivait qu'à la passe nocturne
	// de `securing/cron/clean`, et le centre levait jusque-là.
	if($data->eStructure['type'] === \structure\Structure::COMMUNITY) {
		\structure\StructureLib::connectDatabase($data->eStructure);
	}

})
	->get(['/ferme/{id}/notifications', '/groupe/{id}/notifications'], function($data) {

		checkNotificationAddress($data);

		$data->pilotage = $data->eStructure->hasPilotage();

		$cNotification = \notification\NotificationLib::getByStructure($data->eStructure, $data->eUserOnline);

		$data->unread = \notification\NotificationLib::countUnread($cNotification);

		$data->days = \notification\NotificationLib::getDays(\notification\NotificationLib::group($cNotification));

		$data->seen = \notification\NotificationLib::see($data->eStructure, $data->eUserOnline, $data->unseen);

		if($data->seen) {
			$data->notificationsUnseen[$data->eStructure['id']] = 0;
		}

		throw new DynamicAction($data);

	})
	->get('menu', function($data) {

		$data->section = \notification\NotificationLib::getSection($data->eStructure, GET('section'));
		$data->pilotage = $data->eStructure->hasPilotage();
		$data->free = (\Clock::date() <= \notification\NotificationSetting::FREE_UNTIL);

		$cNotification = \notification\NotificationLib::getByStructure($data->eStructure, $data->eUserOnline);

		$data->unread = \notification\NotificationLib::countUnread($cNotification);

		$cNotificationHead = \notification\NotificationLib::group($cNotification);

		$data->cNotificationHead = $cNotificationHead->slice(0, min(\notification\NotificationSetting::MENU, $cNotificationHead->count()));

		$data->seen = \notification\NotificationLib::see($data->eStructure, $data->eUserOnline, $data->unseen);

		if($data->seen) {
			$data->notificationsUnseen[$data->eStructure['id']] = 0;
		}

		throw new ViewAction($data);

	})
	->get(['/ferme/{id}/notifications/reglages', '/groupe/{id}/notifications/reglages'], function($data) {

		checkNotificationAddress($data);

		$data->pilotage = $data->eStructure->hasPilotage();

		$data->eMember = $data->eStructure->getMember();
		$data->settings = \notification\NotificationLib::getSettings($data->eStructure, $data->eMember);

		throw new DynamicAction($data);

	})
	->post('doUpdateSetting', function($data) {

		$data->eMember = $data->eStructure->getMember();
		$data->type = POST('type');
		$value = POST('value');

		if(\notification\NotificationLib::isSettable($data->eStructure, $data->eMember, $data->type, $value) === FALSE) {
			throw new NotExpectedAction('Ce type de notification ne se règle pas ici');
		}

		$fw = new FailWatch();

		$data->eMember->build(['notifications'], [
			'notifications' => \notification\NotificationLib::applySetting($data->eMember, $data->type, $value)
		]);

		$fw->validate();

		\structure\MemberLib::updateNotifications($data->eMember);

		$data->settings = \notification\NotificationLib::getSettings($data->eStructure, $data->eMember);

		throw new ViewAction($data);

	})
	->post('doRead', function($data) {

		$eNotification = \notification\NotificationLib::getByUser($data->eStructure, $data->eUserOnline, POST('notification', 'int'), POST('source', '?string'))->validate();

		\notification\NotificationLib::read($data->eStructure, $eNotification);

		$classLib = \notification\NotificationSetting::resolve($eNotification['type']);

		throw new RedirectAction($classLib::getUrl($eNotification));

	})
	->post('doReadAll', function($data) {

		\notification\NotificationLib::readAll($data->eStructure, $data->eUserOnline);

		throw new ReloadAction();

	});
?>
