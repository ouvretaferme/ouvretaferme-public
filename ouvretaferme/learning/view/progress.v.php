<?php
new AdaptativeView([
	'/formation/{id}/suivi', '/formation/{id}/suivi/{section@int}', '/formation/{id}/suivi/ferme/{structure@int}',
	'/formation/{id}/suivi/lecon/{lesson@int}', '/formation/{id}/suivi/lecon/{lesson@int}/{section@int}', '/formation/{id}/suivi/lecon/{lesson@int}/ferme/{structure@int}'
], function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = s("Suivi de {value}", $data->eCourse['name']);
	$t->mainTitle = \learning\ProgressUi::getTrackTitle($data->eCourse);

	// Un réseau filtre par ferme là où le gabarit met la barre des promotions : sa ligne remplace
	// celle-là, qu'un groupe sans promotion ne rend pas.
	if($data->eStructure->isNetwork()) {
		$data->scopeLine = new \learning\ProgressUi()->getStructureLine($data->eStructure, $data->eCourse, $data->eLesson, $data->cStructureTrack, $data->eStructureTrack);
	}

	echo new \learning\EnrollmentUi()->getRequests($data->eCourse, $data->cEnrollmentRequest);
	echo new \learning\ProgressUi()->getTrack($data->eStructure, $data->eCourse, $data->cChapter, $data->cSectionTrack, $data->eSectionTrack, $data->cUser, $data->eLesson, $data->eStructureTrack);

});

new AdaptativeView('journal', function($data, PanelTemplate $t) {

	return new \learning\ReadingUi()->getPanel($data->eCourse, $data->cChapter, $data->eUser, $data->journal, TRUE);

});

new AdaptativeView('mine', function($data, PanelTemplate $t) {

	return new \learning\ReadingUi()->getPanel($data->eCourse, $data->cChapter, $data->eUser, $data->journal, FALSE);

});

new JsonView('doTrack', function($data, AjaxTemplate $t) {

	$t->qs('#learning-track-'.$data->eUser['id'])->outerHtml(new \learning\ProgressUi()->getTrackLessonRow($data->eCourse, $data->eLesson, $data->eUser));

});
?>
