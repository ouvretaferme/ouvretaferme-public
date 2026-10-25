<?php
new AdaptativeView('manage', function($data, StructureTemplate $t) {

	$t->nav = 'structure';
	$t->subNav = 'features';

	$t->title = s("Les fonctionnalités de {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlSettingsFeatures($data->eStructure);

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Fonctionnalités"));

	echo new \structure\FeatureUi()->getFeatures($data->eStructure, $data->hrSubscription);

});
?>
