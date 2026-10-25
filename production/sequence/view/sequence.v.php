<?php
new AdaptativeView('display', function($data, StructureTemplate $t) {

	$t->title = $data->e['name'];
	$t->nav = 'cultivation';
	$t->subNav = 'sequence';

	$t->mainTitle = new \sequence\SequenceUi()->getHeader($data->eStructure, $data->e, $data->cFlow);
	echo new \sequence\SequenceUi()->getDetails($data->e);

	echo new \sequence\CropUi()->displayBySequence($data->e, $data->harvests, $data->cActionMain);

	if($data->e->canWrite()) {
		echo new \sequence\SequenceUi()->getComment($data->e);
	}

	echo new \note\NoteUi()->getLinked($data->eStructure, $data->cNoteLinked, withTargets: TRUE);

	echo '<div class="util-title">';
		echo '<h3>'.s("Interventions").'</h3>';
		echo new \sequence\FlowUi()->planTask($data->e);
	echo '</div>';

	echo new \sequence\FlowUi()->getTimeline($data->e, $data->events, $data->e->canWrite());
	echo new \sequence\SequenceUi()->getSeries($data->e, $data->ccSeries);
	echo new \sequence\SequenceUi()->getPhotos($data->e, $data->cPhoto);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \sequence\SequenceUi()->create($data->e);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \sequence\SequenceUi()->update($data->e);
});

new AdaptativeView('updateComment', function($data, PanelTemplate $t) {
	return new \sequence\SequenceUi()->updateComment($data->e);
});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \sequence\SequenceUi::getAutocomplete($data->cSequence, $data->cActionMain));

});
?>
