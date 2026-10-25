<?php
new AdaptativeView('create', function($data, DynamicTemplate $t) {

	if($t instanceof StructureTemplate or $t instanceof AmapTemplate) {
		$t->nav = 'communications';
		$t->subNav = 'website';
	}

	$t->title = s("Ajouter une actualité");
	$t->mainTitle = new \website\NewsUi()->createTitle($data->e);

	echo new \website\NewsUi()->create($data->e);
});

new AdaptativeView('update', function($data, DynamicTemplate $t) {

	if($t instanceof StructureTemplate or $t instanceof AmapTemplate) {
		$t->nav = 'communications';
		$t->subNav = 'website';
	}

	$t->title = s("Modifier une actualité");
	$t->mainTitle = new \website\NewsUi()->updateTitle($data->e);

	echo new \website\NewsUi()->update($data->e);

});
?>
