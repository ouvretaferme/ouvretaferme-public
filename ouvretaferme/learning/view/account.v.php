<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\AccountUi()->create($data->eUser, $data->eCourse, $data->enrollFirst);

});

// Posté et non lu en GET, pour que le mot de passe déjà tapé ne passe pas dans l'adresse :
// `PanelTemplate` n'ouvre un panneau que sur GET, le panneau est donc poussé.
new JsonView('logIn', function($data, AjaxTemplate $t) {
	$t->pushPanel(new \learning\AccountUi()->logIn($data->email, $data->eCourse));
});

new AdaptativeView('/formations/compte', function($data, BaseTemplate $t) {

	$t->title = s("Créer votre compte pour vous former");

	echo new \learning\AccountUi()->getPage($data->eUser);

});
?>
