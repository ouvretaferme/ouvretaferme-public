<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\SupervisorUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\SupervisorUi()->update($data->e, $data->e['cStaff']);

});

new JsonView('doUpdateStatus', function($data, AjaxTemplate $t) {

	$t->qs('#supervisor-row-'.$data->e['id'])->outerHtml(new \learning\SupervisorUi()->getRow($data->e));

});
?>
