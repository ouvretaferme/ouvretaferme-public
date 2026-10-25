<?php
new AdaptativeView('start', function($data, StructureTemplate $t) {

	$uiOnboarding = new \map\OnboardingUi();

	$t->title = s("Plan de {value}", $data->eStructure['name']);
	$t->canonical = \map\OnboardingUi::url($data->eStructure);

	$t->nav = 'cultivation';
	$t->subNav = 'map';

	$t->mainTitle = $uiOnboarding->getTitle();

	echo $uiOnboarding->getStart($data->eStructure, $data->eSystem, $data->season);

});
?>
