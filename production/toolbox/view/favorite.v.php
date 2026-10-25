<?php
// Le menu n'est pas rendu à nouveau en navigation ajax : l'étoile renvoie ce qui la porte, la grille
// de la Boîte ou l'en-tête de l'outil, et l'onglet du menu, qui porte les favoris. Dans l'en-tête,
// l'étoile est la ligne du menu elle-même, d'où outerHtml.
new JsonView('doToggle', function($data, AjaxTemplate $t) {
	$uiTool = new \toolbox\ToolUi();
	$t->qs('#toolbox-list')->outerHtml($uiTool->getList($data->eStructure, $data->cFavorite, $data->cGauge, $data->cCompositionFormula, $data->treatments, $data->maintenances, $data->waters));
	$t->qs('#toolbox-header-star')->outerHtml($uiTool->getStar($data->eStructure, $data->tool, $data->cFavorite->offsetExists($data->tool), menu: TRUE));
	$t->qs('#structure-nav .structure-nav-toolbox')->outerHtml(new \structure\MenuUi()->getToolboxTab($data->eStructure, 'toolbox', NULL));
});
?>
