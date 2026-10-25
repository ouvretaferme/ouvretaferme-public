<?php
new Page(function($data) {

	\user\ConnectionLib::checkLogged();

})
	->get('index', function($data) {

		$data->plantsFile = 'plants.svg';
		$data->plants = \design\DesignLib::getSvgSymbols('plant', $data->plantsFile);

		throw new ViewAction($data);

	});

?>
