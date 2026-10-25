<?php
new AdaptativeView('/api/oauth/authorize', function($data, BaseTemplate $t) {

	$t->title = s("Autoriser l'accès à {value}", $data->eStructure['name']);
	$t->header = '';
	$t->metaNoindex = TRUE;

	echo new \api\AuthorizeUi()->consent($data->authorizationRequest, $data->eStructure['id'], $data->eStructure['name']);

});
?>
