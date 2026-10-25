<?php
// « Paramétrer la consommation d'eau », dans la roue de l'outil : le rythme du rappel des compteurs
// d'eau à relever, comme « Paramétrer le carnet d'entretien ». Une structure n'a qu'un réglage, que
// l'enregistrement pose ou remplace (`WaterConfigurationLib::create()`) : le panneau est donc toujours
// une création.
new \toolbox\WaterConfigurationPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\WaterConfiguration([
			'structure' => $data->eStructure
		]);

	})
	->create(function($data) {

		\toolbox\WaterConfigurationLib::fillPanel($data->e);

		throw new ViewAction($data);

	})
	->doCreate(fn($data) => throw new ReloadAction('toolbox', 'WaterConfiguration::created'));
?>
