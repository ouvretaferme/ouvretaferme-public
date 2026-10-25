<?php

new AdaptativeView('index', function($data, BaseTemplate $t) {

	$t->title = s("Images de {value}", $data->plantsFile);

	$t->header = \design\NavigationUi::getHeader($t->title, '/plants');

	echo new \design\DesignUi()->plants($data->plantsFile, $data->plants);

});
