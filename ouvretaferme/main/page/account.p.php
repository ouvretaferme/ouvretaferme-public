<?php
new Page()
	->get('index', function($data) {

		\user\ConnectionLib::checkLogged();

		$data->canUpdate = user\SignUpLib::canUpdate($data->eUserOnline);
		$data->hasCustomer = \main\CustomerSpaceAiLib::hasCustomer($data->eUserOnline);
		$data->canCreateAmap = $data->eUserOnline->acceptCreateAmap();

		throw new ViewAction($data);

	});
?>
