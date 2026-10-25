<?php
new AdaptativeView('/ferme/{id}/outils/traitement', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::TREATMENT;

	$t->title = s("Registre phytosanitaire");

	$uiTreatment = new \toolbox\TreatmentUi();

	$t->mainTitle = $uiTreatment->getHeader($data->eStructure, $data->cFavorite);

	if($data->start) {
		echo $uiTreatment->getStart($data->eStructure);
	} else {
		echo $uiTreatment->getReprise($data->eToolReprise);
		echo $uiTreatment->getYears($data->eStructure, $data->eAction, $data->seasons, $data->years, $data->year, $data->search);
		echo $uiTreatment->getSearch($data->eStructure, $data->eAction, $data->year, $data->search);
		echo $uiTreatment->getBook($data->eStructure, $data->year, $data->search, $data->cTask, $data->tasksByMonth, $data->cTaskWithout);
	}

});

new AdaptativeView('updateProduct', function($data, PanelTemplate $t) {
	return new \toolbox\TreatmentUi()->updateProduct($data->eStructure, \toolbox\TreatmentUi::getState($data->year, $data->search), $data->cTaskSelected, $data->eTask);
});

new JsonView('doUpdateProduct', function($data, AjaxTemplate $t) {

	$t->qs('#treatment-book')->outerHtml(new \toolbox\TreatmentUi()->getBook($data->eStructure, $data->year, $data->search, $data->cTask, $data->tasksByMonth, $data->cTaskWithout));
	$t->js()->closePanel('#panel-treatment-product');
	$t->js()->success('toolbox', 'Treatment::productUpdated');

});

new AdaptativeView('getPdf', function($data, PdfTemplate $t) {

	$uiTreatmentPdf = new \toolbox\TreatmentPdfUi();

	$t->title = s("Registre phytosanitaire");

	echo \main\PdfUi::getHeader();
	echo \main\PdfUi::getStructure($data->e, $t->title, s("Année {value}", $data->year));
	echo $uiTreatmentPdf->getFigures($data->year, $data->cTask, $data->cTaskWithout);
	echo $uiTreatmentPdf->getMonths($data->year, $data->tasksByMonth);
	echo \main\PdfUi::getFooter($data->e, note: s("Registre phytosanitaire édité le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]));

});
?>
