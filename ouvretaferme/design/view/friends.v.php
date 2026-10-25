<?php

new AdaptativeView('index', function($data, BaseTemplate $t) {

	$t->title = s("Images du dossier {value}", $data->friendsFolder);

	$t->header = \design\NavigationUi::getHeader($t->title, '/friends');

	echo new \design\DesignUi()->friends($data->friendsFolder, $data->friends);

});
