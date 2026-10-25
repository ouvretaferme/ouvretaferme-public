<?php
new AdaptativeView('set', function($data, BaseTemplate $t) {

	$t->title = s("Réinitialiser mon mot de passe");
	$t->template .= ' presentation';

	echo new user\UserUi()->updatePassword(new \user\User(), $data->hash, $data->email);

	echo '<div class="util-signature-mascot">'.\Asset::svg('main', 'friends/oiseau.svg').'</div>';

});

new AdaptativeView('setFailed', function($data, BaseTemplate $t) {

	$t->title = s("Réinitialiser mon mot de passe");
	$t->template .= ' presentation';

	echo '<p class="mb-2">'.s("Ce lien pour réinitialiser votre mot de passe a expiré ou alors vous avez déjà modifié votre mot de passe.").'</p>';

	echo '<a href="/" class="btn btn-primary">'.s("Revenir sur la page d'accueil").'</a>';
	echo ' <a href="/user/log:forgottenPassword" class="btn btn-outline-primary">'.s("Renvoyer un lien").'</a>';

});
?>
