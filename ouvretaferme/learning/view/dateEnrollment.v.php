<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	$ui = new \learning\DateEnrollmentUi();

	return match($data->step) {
		1 => $ui->createReading($data->e, $data->status, $data->cChapter, $data->cStaff, $data->commitments, $data->choices),
		2 => $ui->createCommitment($data->e, $data->status, $data->commitments, $data->choices),
	};

});

// Une réponse à un POST : le panneau ouvert par `create` se repeint par instruction, la navigation
// d'un PanelTemplate n'acceptant que GET.
new JsonView('doCommit', function($data, AjaxTemplate $t) {

	$t->pushPanel(new \learning\DateEnrollmentUi()->createInscription($data->e, $data->status));

});

new AdaptativeView('/formation/date/{id}/inscrits', function($data, CommunityTemplate $t) {

	$ui = new \learning\DateEnrollmentUi();

	$t->tab = 'learning';
	$t->title = s("Inscrits · {value}", $data->eCourse['name']);
	$t->mainTitle = $ui->getListTitle($data->eDate, $data->cDate);
	$t->mainFilter = $ui->getDateFilter($data->eDate, $data->cDate);
	$t->mainFigures = $ui->getListFigures($data->eDate);

	echo $ui->getList($data->eDate, $data->cDateEnrollment, $data->cDateSheet, $data->publicUrl);

});

// Le pointage d'une personne ou ses heures corrigées : sa ligne et le compte sous le titre.
new JsonView('attendance', function($data, AjaxTemplate $t) {

	$ui = new \learning\DateEnrollmentUi();

	$t->qs('#learning-date-enrollment-'.$data->eDateEnrollment['id'])->outerHtml($ui->getRow($data->eDate, $data->eDateEnrollment, TRUE));
	$t->qs('#learning-date-attendance')->outerHtml($ui->getAttendanceSummary($data->eDate));

	if($ui->hasAttestation($data->eDate)) {
		$t->qs('#learning-date-attestation-send')->outerHtml($ui->getAttestationButton($data->eDate));
	}

});
?>
