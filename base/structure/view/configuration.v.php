<?php
new AdaptativeView('updateVatHistory', function($data, PanelTemplate $t) {

	return new \structure\ConfigurationUi()->updateVatHistory($data->eStructure, $data->field, $data->periods);

});

new AdaptativeView('updateVat', function($data, StructureTemplate $t) {

	$t->title = s("Le paramétrage de TVA de {value}", $data->e['name']);

	$t->nav = 'structure';
	$t->subNav = 'vat';

	$t->mainTitle = \main\DesignUi::getPageHeader(s("TVA"));

	if($data->eStructure->isVerifiedCountry() === FALSE) {

		echo new \structure\StructureUi()->getLegalForm($data->eStructure, onlyCountry: TRUE);

	} else if($data->eStructure->isCountryVatAllowed() === FALSE) {

		echo '<div class="util-block-info">';
			echo s("Le paramétrage de la TVA sur {siteName} n'est possible que pour les pays suivants :");
			echo '<ul>';
				foreach(\product\ProductSetting::getVatAllowed() as $countryId) {
					echo '<li>';
						echo \user\Country::ask(\user\CountryLib::ask($countryId))['name'];
					echo '</li>';
				}
			echo '</ul>';
			echo '<p>'.s("La TVA est désactivée pour votre structure car vous avez indiqué être en {value}.", '<b>'.encode(\user\Country::ask($data->eStructure['legalCountry'])['name']).'</b>').'</p>';
		echo '</div>';

	} else {

		echo '<div class="util-block-info">';
			echo '<p>'.s("Tous les changements que vous effectuez dans cette page auront un impact sur les calculs de prix pour vos ventes, vos factures ainsi que dans les paramétrages de la comptabilité.").'</p>';
			echo '<p>'.s("Manipulez ces paramètres avec précaution pour rester conforme vis à vis de vos obligations fiscales !").'</p>';
		echo '</div>';

		echo new \structure\ConfigurationUi()->updateVat($data->e, $data->ccConfigurationHistory);

		foreach(\structure\StructureUi::getObserverEntries('getVatForms', $data->eStructure) as $form) {
			echo $form;
		}

	}


});

new AdaptativeView('updatePeriod', function($data, PanelTemplate $t) {
	return new \structure\ConfigurationUi()->updatePeriod($data->e);
});

new AdaptativeView('updateVatNumber', function($data, PanelTemplate $t) {

	return new \structure\ConfigurationUi()->updateVatNumberPanel($data->eStructure);

});

new AdaptativeView('updateEmail', function($data, DynamicTemplate $t) {

	$t->title = s("Les e-mails envoyés par {value}", $data->eStructure['name']);

	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
	}

	if($t instanceof StructureTemplate) {
		$t->nav = 'feature';
		$t->subNav = 'settings-commercialisation';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("E-mails"),
		back: \structure\StructureUi::urlSettingsCommercialisation($data->eStructure),
		backLabel: $data->eStructure->isAmap() ? s("Paramètres") : ''
	);

	echo new \structure\ConfigurationUi()->updateEmail($data->e);

});


new JsonView('vatWeigh', function($data, AjaxTemplate $t) {
	$t->push('weigh', new \structure\ConfigurationUi()->getVatWeigh($data->field, $data->cConfigurationHistory, $data->cDeclaration));
});
?>
