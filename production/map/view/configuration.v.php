<?php
new AdaptativeView('updateCrop', function($data, StructureTemplate $t) {

	$t->title = s("Le paramétrage des cultures de {value}", $data->eStructure['name']);
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Les cultures"),
		back: \structure\StructureUi::urlSettingsProduction($data->eStructure)
	);

	echo new \map\ConfigurationUi()->updateCrop($data->e);

});

new AdaptativeView('calendarMonth', function($data, AjaxTemplate $t) {

	$t->qs('#map-configuration-calendar-month')->innerHtml(new \series\CultivationUi()->getListSeason($data->e, \Clock::year()));

});
?>
