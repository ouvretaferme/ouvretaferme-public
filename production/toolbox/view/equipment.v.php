<?php
new AdaptativeView('/ferme/{id}/outils/materiel', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::MAINTENANCE;

	$t->title = s("Carnet d'entretien du matériel");

	$uiEquipment = new \toolbox\EquipmentUi();

	$t->mainTitle = $uiEquipment->getHeader($data->eStructure, $data->cEquipment, $data->cFavorite);

	if($data->cEquipment->empty()) {
		echo $uiEquipment->getStart($data->eStructure);
	} else {
		echo $uiEquipment->getDue($data->eStructure, $data->cMaintenanceDue, $data->cEquipment, $data->eEquipmentSelected);
		echo $uiEquipment->getEquipments($data->eStructure, $data->cEquipment, $data->eEquipmentSelected);
	}

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\EquipmentUi()->create($data->e, $data->eEquipmentReading, $data->hasFollowed);

});

// Changer de page ne rend que l'historique du matériel, et pose la page dans l'adresse sans y toucher
// autrement : l'écran y revient après un geste sur une de ses lignes, qui le recharge.
new JsonView('history', function($data, AjaxTemplate $t) {

	$t->qs('#maintenance-history-'.$data->e['id'])->outerHtml(new \toolbox\MaintenanceDoneUi()->getHistory($data->e, $data->e->assertUpdate()));

	$t->js()->updateHistory('paged', (string)$data->e['id']);
	$t->js()->updateHistory('page', (string)$data->e['historyPage']);

});

new AdaptativeView('getPdf', function($data, PdfTemplate $t) {

	$uiEquipmentPdf = new \toolbox\EquipmentPdfUi();

	$t->title = \toolbox\EquipmentPdfUi::getTitle($data->eEquipment);

	echo \main\PdfUi::getHeader();
	echo \main\PdfUi::getStructure($data->e, $t->title);
	echo $uiEquipmentPdf->getFigures($data->eEquipment);
	echo $uiEquipmentPdf->getMaintenances($data->eEquipment);
	echo $uiEquipmentPdf->getHistory($data->eEquipment);
	echo \main\PdfUi::getFooter($data->e, note: s("Carnet d'entretien édité le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]));

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$results = $data->cTool->makeArray(fn($eTool) => \work\ToolUi::getAutocomplete($eTool));

	if($data->create) {
		$results[] = \toolbox\EquipmentUi::getToolAutocompleteValidate($data->query);
	}

	$t->push('results', $results);

});
?>
