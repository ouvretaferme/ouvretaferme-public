<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\EnrollmentUi()->create($data->e, $data->enrollFirst);

});

new JsonView('doCancelByCommunity', function($data, AjaxTemplate $t) {

	$t->qs('#learning-enrollment-requests')->outerHtml(new \learning\EnrollmentUi()->getRequests($data->e['course'], $data->cEnrollmentRequest));
	$t->js()->success('learning', 'Enrollment::canceledByCommunity');

});
?>
