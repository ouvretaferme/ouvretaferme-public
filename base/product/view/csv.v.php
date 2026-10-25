<?php
new AdaptativeView('importProducts', function($data, StructureTemplate $t) {

	$t->title = s("Importer des produits");
	$t->nav = 'feature';
	$t->subNav = 'settings-commercialisation';

	if(get_exists('created')) {

		echo '<div class="util-block-success">';
			echo '<p>'.s("Les produits contenus dans votre fichier CSV ont bien été ajoutées à votre gamme !").'</p>';
			echo '<a href="'.\structure\StructureUi::urlSellingProducts($data->eStructure).'" class="btn btn-transparent">'.s("Voir mes produits").'</a>';
		echo '</div>';

	}

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Importer des produits"),
		back: \structure\StructureUi::urlDataImport($data->eStructure)
	);
	
	echo '<div class="util-block-info">';
		echo '<p>'.s("Vous pouvez importer sur {siteName} votre gamme de produits au format CSV.<br/>Le fichier que vous importez doit respecter un format qui est décrit dans la documentation.").'</p>';
		echo '<a href="/doc/import:products" class="btn btn-secondary">'.Asset::icon('person-raised-hand').' '.s("Voir la documentation").'</a>';
	echo '</div>';

	echo new \main\CsvUi()->getImportButton($data->eStructure, '/product/csv:doImportProducts');

});

new AdaptativeView('importProductFile', function($data, StructureTemplate $t) {

	$t->title = s("Importer des produits");
	$t->nav = 'feature';
	$t->subNav = 'settings-commercialisation';

	$t->mainTitle = \main\DesignUi::getPageHeader(
		p("Votre fichier CSV contient {value} produit", "Votre fichier CSV contient {value} produits", count($data->data['import'])),
		back: \structure\StructureUi::urlDataImport($data->eStructure),
		tools: '<a href="/product/csv:importProducts?id='.$data->eStructure['id'].'&reset" class="btn btn-outline-secondary">'.\Asset::icon('upload').'<span class="hide-xs-down"> '.s("Téléverser un autre fichier").'</span></a>'
	);
	
	echo new \product\CsvUi()->getProducts($data->eStructure, $data->data);

});

?>
