<?php
new AdaptativeView('/ferme/{id}/outils/produits', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::CATALOG;

	$t->title = s("Catalogue E-Phy");

	$uiCatalog = new \toolbox\CatalogUi();

	$t->mainTitle = $uiCatalog->getHeader($data->eStructure, $data->cFavorite);

	if($data->start) {
		echo $uiCatalog->getStart($data->eStructure);
	} else {
		echo $uiCatalog->get($data->eStructure, $data->filters, $data->products, $data->page);
	}

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \toolbox\CatalogUi::getSuggestions($data->eStructure, $data->filters, $data->suggestions));

});

new AdaptativeView('product', function($data, PanelTemplate $t) {

	return new \toolbox\CatalogUi()->displayProduct($data->eStructure, $data->ePhytoProduct);

});
?>
