<?php
new JsonView('index', function($data, AjaxTemplate $t) {

	if($data->tos !== NULL) {
		$t->pushPanel(new \user\TosAiUi()->getPanel($data->tosEffectiveAt, $data->tos));
	}

});

new JsonView('doLater', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#user-tos');

});

new JsonView('doAccept', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#user-tos');

	if($data->accepted) {
		$t->js()->success('user', 'Tos::accepted');
	}

});
?>
