<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\MaintenanceDoneUi()->create($data->e, $data->next);

});

new JsonView('next', function($data, AjaxTemplate $t) {

	$t->qs('#maintenance-done-next')->outerHtml(new \toolbox\MaintenanceDoneUi()->getNext($data->e['equipment'], $data->next));

});

new AdaptativeView('photo', function($data, PanelTemplate $t) {

	return new \toolbox\MaintenanceDoneUi()->getPhoto($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\MaintenanceDoneUi()->update($data->e);

});
?>
