<?php
new Page(function($data) {

	// Autorisation pour Paul Appert
	if($data->eUserOnline->empty() or ($data->eUserOnline->isAdmin() === FALSE and $data->eUserOnline['id'] !== 7445)) {
		throw new RedirectAction(Lime::getUrl());
	}

})
	->get('/design/', function($data) {

		$data->forDev = get_exists('dev');
		$data->forTests = get_exists('tests');
		$data->forAgents = $data->forDev ? NULL : get_exists('agents');

		if($data->forTests) {
			$data->decorStructures = \design\DecorAiLib::getStructures();
			$data->decorUsers = \design\DecorAiLib::getUsers();
		}

		throw new ViewAction($data);

	});

?>
