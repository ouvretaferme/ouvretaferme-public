<?php
new AdaptativeView('doPreview', function($data, AjaxTemplate $t) {

	$t->js()->eval('UserAvatarEditor.previewed('.json_encode($data->upload).')');

});

/**
 * Le modèle prend sa place dans l'éditeur sans le repeindre : l'onglet du modèle, ce qui dépend de lui au pied, le choix de
 * la photo de « Composer en ligne », que l'envoi a pu changer, et l'aperçu (`UserAvatarEditor.placed()`). Le message vert
 * ne se dit qu'une fois : l'onglet repeint montre le modèle au repos. La page sous le panneau se recharge, elle seule, pour
 * que l'en-tête et ce qu'elle montre de l'avatar le suivent.
 */
new AdaptativeView('doCreate', function($data, AjaxTemplate $t) {

	$uiUserAvatar = new \user\UserAvatarUi();
	$form = new \util\FormUi(['columns' => 1]);

	$t->qs('#panel-user-avatar .tab-panel[data-tab="upload"]')->innerHtml($uiUserAvatar->getUpload($form, $data->eUserAvatar));
	$t->qs('#panel-user-avatar .tab-panel[data-tab="compose"] [data-wrapper="replacesPhoto"]')->outerHtml($uiUserAvatar->getPhotoChoice($form, $data->eUserAvatar));
	$t->qs('#panel-user-avatar .panel-footer [type="submit"]')->setAttribute('data-confirm', $uiUserAvatar->getSaveConfirm($data->eUserAvatar));
	$t->qs('#panel-user-avatar .user-avatar-delete')->outerHtml($uiUserAvatar->getDeleteButton($data->eUserAvatar));

	$t->js()->eval('UserAvatarEditor.placed('.json_encode([
		'url' => \user\UserAvatarUploadUi::url($data->eUserAvatar['eUserAvatarUpload']),
		'index' => $data->eUserAvatar['eUserAvatarUpload']['bakedIndex']
	]).')');

	$t->js()->success('user', 'UserAvatarUpload::created');

	$t->ajaxReload(purgeLayers: FALSE);

});

// L'interrupteur et le choix de « Composer en ligne » disent la même case : les deux suivent, sans repeindre l'éditeur.
// La photo de profil a changé : la page sous le panneau se recharge pour que l'en-tête la suive
new AdaptativeView('doPhoto', function($data, AjaxTemplate $t) {

	$uiUserAvatar = new \user\UserAvatarUi();

	$t->qs('#user-avatar-photo')->outerHtml($uiUserAvatar->getPhotoSwitch($data->eUserAvatar));
	$t->qs('#panel-user-avatar .tab-panel[data-tab="compose"] [data-wrapper="replacesPhoto"]')->outerHtml($uiUserAvatar->getPhotoChoice(new \util\FormUi(['columns' => 1]), $data->eUserAvatar));
	$t->qs('#panel-user-avatar .user-avatar-delete')->setAttribute('data-confirm', $uiUserAvatar->getDeleteConfirm($data->eUserAvatar));

	$t->ajaxReload(purgeLayers: FALSE);

});
?>
