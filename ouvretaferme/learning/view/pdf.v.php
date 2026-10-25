<?php
new HtmlView('attestation', function($data, PdfTemplate $t) {

	$t->title = s("Attestation de suivi");

	echo new \learning\AttestationUi()->get($data->eCourse, $data->cChapter, $data->eUser, $data->eStructure, $data->eStructureIssuer, $data->issuedAt);

});

new HtmlView('report', function($data, PdfTemplate $t) {

	$t->title = s("Relevé de suivi");

	echo new \learning\ReportUi()->get($data->eCourse, $data->cChapter, $data->eUser, $data->eStructureCommunity, $data->journal, $data->issuedAt);

});

new HtmlView('presence', function($data, PdfTemplate $t) {

	$t->title = s("Attestation de présence");

	echo new \learning\AttestationUi()->getPresence($data->eDateEnrollment, $data->eStructureCommunity, $data->issuedAt);

});

new HtmlView('sheet', function($data, PdfTemplate $t) {

	$t->title = s("Feuille d'émargement");

	echo new \learning\SheetUi()->get($data->eDate, $data->cDateEnrollment, $data->cStaff, $data->eStructureCommunity, $data->halfDays, $data->issuedAt);

});
?>
