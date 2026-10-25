<?php

new AdaptativeView('index', function($data, BaseTemplate $t) {

	$t->title = s("Icônes de catégorie");

	$t->header = \design\NavigationUi::getHeader($t->title, '/categories');

	echo new \design\DesignUi()->categories($data->categoriesFile, $data->categoriesIcons, $data->categoriesSymbols);

});
