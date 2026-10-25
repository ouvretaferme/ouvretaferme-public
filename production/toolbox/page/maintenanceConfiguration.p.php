<?php
// « Paramétrer le carnet d'entretien », dans le menu du carnet : l'intervention que prend, au planning,
// la tâche d'un entretien fait (planche production-finir-carnet-planning, Q4 C), et le rythme du
// rappel des compteurs à relever. Une ferme n'a qu'un réglage, que l'enregistrement pose ou remplace
// (`MaintenanceConfigurationLib::create()`) : le panneau est donc toujours une création.
new \toolbox\MaintenanceConfigurationPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\MaintenanceConfiguration([
			'structure' => $data->eStructure
		]);

	})
	->create(function($data) {

		// L'intervention s'ouvre comme au premier « Fait » : sur celle qui est réglée, sinon sur celle que
		// le carnet propose. Un réglage dont l'intervention ne vaut plus est tenu pour absent, et ne
		// montre donc pas celle qu'un entretien fait ne pourrait plus prendre.
		\toolbox\MaintenanceConfigurationLib::fillPanel($data->e);

		throw new ViewAction($data);

	})
	->doCreate(fn($data) => throw new ReloadAction('toolbox', 'MaintenanceConfiguration::created'));
?>
