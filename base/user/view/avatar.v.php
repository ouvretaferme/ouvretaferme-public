<?php
new AdaptativeView('index', function($data, PanelTemplate $t) {

	return new user\UserAvatarUi()->update($data->catalog, $data->families, $data->sharedPalettes, $data->eUserAvatar);

});

new AdaptativeView('render', function($data, PanelTemplate $t) {

	return new user\UserAvatarUi()->update($data->catalog, $data->families, $data->sharedPalettes, $data->e);

});
?>
