<?php
new AdaptativeView('updateUser', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-update',
		title: s("Modifier mes informations personnelles"),
		body: new user\UserUi()->update($data->eUserOnline)
	);

});

new AdaptativeView('updateEmail', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-update-email',
		title: s("Changer mon adresse e-mail"),
		body: new user\UserUi()->updateEmail($data->eUserOnline)
	);

});

new AdaptativeView('updatePassword', function($data, PanelTemplate $t) {

	if($data->eUserOnline['canUpdate']['hasPassword'] === FALSE) {
		$title = s("Créer un mot de passe");
	} else {
		$title = s("Changer mon mot de passe");
	}

	return new Panel(
		id: 'panel-user-update-password',
		title: $title,
		body: new user\UserUi()->updatePassword($data->eUserOnline)
	);

});

new AdaptativeView('dropAccount', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-drop',
		title: s("Supprimer mon compte"),
		body: new user\DropUi()->close($data->eUserOnline, $data->canCloseDelay)
	);

});

new JsonView('doUpdateNav', function($data, AjaxTemplate $t) {
	$t->qs('body')->setAttribute('data-nav', $data->e['nav']);
	$t->qs('#user-nav-switch')->toggleSwitch('post-nav', [\user\User::DARK, \user\User::LIGHT]);
});
?>
