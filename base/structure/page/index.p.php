<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

	})
	->get('/ferme/{id}/configuration/production', function($data) {

		$data->eStructure->validate('canManage');

		$data->eStructure['cProduction'] = \structure\ProductionLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	->get('/ferme/{id}/configuration/commercialisation', function($data) {

		$data->eStructure->validate('canSettingsCommercialisation');

		if($data->eStructure->isAmap()) {
			throw new RedirectAction(\structure\StructureUi::urlSettingsCommercialisation($data->eStructure));
		}

		$data->eStructure['stripeExpressEligible'] = \structure\StructureLib::isStripeExpressEligible($data->eStructure);

		throw new ViewAction($data);

	})
	// Les distributions d'une AMAP se règlent sur sa page Paramètres : l'adresse reste pour les liens
	// déjà partagés.
	->get('/ferme/{id}/configuration/distributions', function($data) {

		$data->eStructure->validate('canSettingsCommercialisation', 'isAmap');

		throw new RedirectAction(\structure\StructureUi::urlSettingsCommercialisation($data->eStructure));

	})
	->get('/ferme/{id}/configuration/adherents', function($data) {

		$data->eStructure->validate('canManage', 'isAmap', 'acceptImportExport');

		throw new AmapAction($data);

	});

?>
