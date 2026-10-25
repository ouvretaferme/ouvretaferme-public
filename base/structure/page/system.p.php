<?php
new \structure\SystemPage()
	->update(function($data) {

		$data->e['structure'] = \structure\StructureLib::getById($data->e['structure']);

		throw new ViewAction($data);

	}, page: 'updatePlace')
	->doUpdateProperties('doUpdatePlace', ['place', 'placeLngLat', 'altitude'], fn() => throw new ReloadAction('structure', 'System::updatedPlace'))
	->update(function($data) {

		$data->e['structure'] = \structure\StructureLib::getById($data->e['structure']);
		$data->e['cProduction'] = \structure\ProductionLib::getByStructure($data->e['structure']);
		$data->e['inviteKey'] = GET('inviteKey', '?string');

		throw new ViewAction($data);

	}, page: 'updateProductions')
	->doUpdateProperties('doUpdateProductions', ['productionsList'], fn($data) => throw new RedirectAction(\structure\StructureUi::urlStart($data->e['structure'], POST('inviteKey', '?string'))))
	->update(function($data) {

		$data->e['structure'] = \structure\StructureLib::getById($data->e['structure']);
		$data->e['cProduction'] = \structure\ProductionLib::getByStructure($data->e['structure']);

		$data->eStructure = $data->e['structure'];

		throw new ViewAction($data);

	})
	->doUpdate(fn($data) => throw new ReloadAction('structure', 'System::updated'))
	->doUpdateProperties(
		'doUpdateShare',
		fn(\structure\System $e) => \structure\SystemLib::getPropertiesShare(),
		fn($data) => throw new ViewAction($data),
		for: 'share'
	);
?>
