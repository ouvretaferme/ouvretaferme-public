<?php
new AdaptativeView('index', function($data, BaseTemplate $t) {

	$t->title = s("Supprimer mon compte");

	echo new user\DropUi()->close($data->eUserOnline, $data->can);

});
?>
