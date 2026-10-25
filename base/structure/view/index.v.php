<?php
new AdaptativeView('/ferme/{id}/configuration/production', function($data, StructureTemplate $t) {

	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->title = s("Paramétrer la production pour {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlSettingsProduction($data->eStructure);

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Production"));

	echo new \structure\StructureUi()->getSettingsProduction($data->eStructure);

});
new AdaptativeView('/ferme/{id}/configuration/commercialisation', function($data, StructureTemplate $t) {

	$t->nav = 'feature';
	$t->subNav = 'settings-commercialisation';

	$t->title = s("Paramétrer la commercialisation pour {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlSettingsCommercialisation($data->eStructure);

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Commercialisation"));

	echo new \structure\StructureUi()->getSettingsCommercialisation($data->eStructure);

});
new AdaptativeView('/ferme/{id}/configuration/adherents', function($data, AmapTemplate $t) {

	$t->nav = 'settings';

	$t->title = s("Les adhérents de {value}", $data->eStructure['name']);
	$t->canonical = \structure\StructureUi::urlDataCustomers($data->eStructure);

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Adhérents"), back: \structure\StructureUi::urlAmapSettings($data->eStructure), backLabel: s("Paramètres"));

	echo new \structure\StructureAmapUi()->getSettingsCustomers($data->eStructure);

});
?>
