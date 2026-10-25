<?php
new AdaptativeView('update', function($data, StructureTemplate $t) {

	$t->title = s("Les productions de {value}", $data->eStructure['name']);
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = \main\DesignUi::getPageHeader(
		encode($data->eStructure['name']),
		back: \structure\StructureUi::urlSettingsProduction($data->eStructure)
	);

	echo new \structure\SystemUi()->update($data->e);

});

new AdaptativeView('updateProductions', function($data, PanelTemplate $t) {
	return new \structure\SystemUi()->updateProductions($data->e);
});

new AdaptativeView('updatePlace', function($data, PanelTemplate $t) {
	return new \structure\SystemUi()->updatePlace($data->e);
});

// La réponse ne repeint pas le panneau : MainOnboarding::start() rejoue goTo(1) sur tout contenu
// réinjecté, ce qui ramènerait à l'étape que l'on vient de quitter.
new JsonView('doUpdateShare', function($data, AjaxTemplate $t) {
	$t->js()->eval('StructureShareOnboarding.goTo(2);');
});
?>
