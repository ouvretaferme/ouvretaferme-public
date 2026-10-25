<?php
new AdaptativeView('gardenChairs', function($data, StructureTemplate $t) {

	$t->title = s("Rotation par jardin de {value}", $data->season);

	$t->nav = 'cultivation';
	$t->subNav = 'soil';

	$uiGardenChairs = new \series\GardenChairsAiUi();

	$t->mainTitle = $uiGardenChairs->getTitle($data->eStructure, $data->season);

	echo $uiGardenChairs->getGroups($data->eStructure, $data->season, $data->groups);

});
?>
