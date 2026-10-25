<?php
new AdaptativeView('/ferme/{id}/outils', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';

	$t->title = s("Boite à outils");

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Boite à outils"));

	echo new \toolbox\ToolUi()->getList($data->eStructure, $data->cFavorite, $data->cGauge, $data->cCompositionFormula, $data->treatments, $data->maintenances, $data->waters);

});
?>
