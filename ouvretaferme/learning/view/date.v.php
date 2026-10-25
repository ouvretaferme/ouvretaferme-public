<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->update($data->e);

});

new AdaptativeView('updateOffer', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->updateOffer($data->e);

});

new AdaptativeView('cancel', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->cancel($data->e);

});

new AdaptativeView('openQuiz', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->openQuiz($data->e);

});

new AdaptativeView('sendLink', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->sendLink($data->e);

});

new AdaptativeView('sendAttestations', function($data, PanelTemplate $t) {

	return new \learning\DateUi()->sendAttestations($data->e);

});

new JsonView('doUpdateEnrollmentPaused', function($data, AjaxTemplate $t) {

	$t->qs('#learning-date-pause-'.$data->e['id'])->outerHtml(\learning\DateUi::getEnrollmentBand($data->e));
	$t->js()->success('learning', $data->e['enrollmentPaused'] ? 'Date::enrollmentPaused' : 'Date::enrollmentResumed');

});

// La durée d'une date finie changée sur place : le tableau des places et la ligne de durée.
new JsonView('minutes', function($data, AjaxTemplate $t) {

	$ui = new \learning\DateEnrollmentUi();

	$t->qs('#learning-date-places')->outerHtml($ui->getPlaces($data->e, $data->cDateEnrollment));
	$t->qs('#learning-date-attendance')->outerHtml($ui->getAttendanceSummary($data->e));

});
?>
