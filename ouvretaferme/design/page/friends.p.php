<?php
new Page(function($data) {

	\user\ConnectionLib::checkLogged();

})
	->get('index', function($data) {

		$data->friendsFolder = 'friends';
		$data->friends = \design\DesignLib::getSvgFiles('main', $data->friendsFolder);

		throw new ViewAction($data);

	});

?>
