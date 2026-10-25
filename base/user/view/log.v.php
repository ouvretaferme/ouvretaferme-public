<?php
new AdaptativeView('form', function($data, BaseTemplate $t) {

	$t->title = s("Connectez-vous !");
	$t->template .= ' presentation';

	echo '<div class="user-login-page">';
		echo new \user\UserUi()->logInBasic($data->email, withSignUp: $data->withSignUp);
	echo '</div>';

});

new AdaptativeView('formPanel', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-login',
		title: s("Connectez-vous !"),
		body: new \user\UserUi()->logInBasic($data->email, withSignUp: $data->withSignUp)
	);

});

new AdaptativeView('code', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-code',
		title: s("Un code vous attend"),
		body: new \user\ChallengeAiUi()->code($data->eChallenge)
	);

});

new AdaptativeView('codeExpired', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-code',
		title: s("Un code vous attend"),
		body: new \user\ChallengeAiUi()->codeExpired($data->eChallenge)
	);

});

new AdaptativeView('link', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-link',
		title: s("Me connecter"),
		body: new \user\ChallengeAiUi()->link($data->eChallenge, $data->token)
	);

});

new AdaptativeView('linkElsewhere', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-link',
		title: s("Me connecter"),
		body: new \user\ChallengeAiUi()->linkElsewhere($data->eChallenge)
	);

});

new AdaptativeView('linkExpired', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-link',
		title: s("Me connecter"),
		body: new \user\ChallengeAiUi()->linkExpired()
	);

});

new AdaptativeView('loginLink', function($data, PanelTemplate $t) {

	return new Panel(
		id: 'panel-user-link',
		title: s("Me connecter"),
		body: new \user\LoginLinkAiUi()->link($data->eLoginLink, $data->token)
	);

});

new AdaptativeView('forgottenPassword', function($data, BaseTemplate $t) {

	$t->title = s("Réinitialiser mon mot de passe");
	$t->metaNoindex = TRUE;
	$t->template .= ' presentation';

	echo new \user\UserUi()->forgottenPassword($data->email);


});
?>
