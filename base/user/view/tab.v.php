<?php
new AdaptativeView('browser', function($data, BaseTemplate $t) {

	$t->title = s("Compte du poste");

	echo new \user\TabUi()->browser($data->token);

});

new JsonView('doQuit', function($data, AjaxTemplate $t) {

	$t->js()
		->eval('Ajax.Tab.stop()')
		->location('/');

});
?>
