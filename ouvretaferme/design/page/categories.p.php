<?php
new Page(function($data) {

	\user\ConnectionLib::checkLogged();

})
	->get('index', function($data) {

		$data->categoriesFile = 'categories.svg';
		$data->categoriesIcons = \product\Category::getIcons();
		$data->categoriesSymbols = \design\DesignLib::getSvgSymbols('product', $data->categoriesFile);

		throw new ViewAction($data);

	});

?>
