<?php
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->get('click', function($data) {

		$tip = GET('id', \structure\TipLib::getList());

		if($tip === NULL) {
			throw new NotExpectedAction('Invalid tip');
		}

		$link = \structure\TipLib::getLink($tip);

		if($link === NULL) {
			throw new NotExpectedAction('Tip without link');
		}

		\structure\TipLib::changeStatus($data->eUserOnline, $tip, 'clicked');

		throw new RedirectAction($link);

	})
	->get('close', function($data) {

		$tip = GET('id', \structure\TipLib::getList());

		if($tip === NULL) {
			throw new NotExpectedAction('Invalid tip');
		}

		\structure\TipLib::changeStatus($data->eUserOnline, $tip, 'closed');

		$data->tip = $tip;

		throw new ViewAction($data);

	});
?>
