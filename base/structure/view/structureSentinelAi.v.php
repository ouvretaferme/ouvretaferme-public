<?php
new AdaptativeView('index', function($data, BaseTemplate $t) {

	$t->title = s("Pour qui ouvrir cette page ?");

	echo new \structure\StructureSentinelAiUi()->getChoice($data->cStructureSentinel);

});
?>
