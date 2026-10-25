<?php
new AdaptativeView('importCultivations', function($data, StructureTemplate $t) {

	$t->title = s("Importer un plan de culture");
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	if(get_exists('created')) {

		echo '<div class="util-block-success">';
			echo '<p>'.s("Les séries contenues dans votre fichier CSV ont bien été ajoutées à votre planification !").'</p>';
			echo '<a href="'.\structure\StructureUi::urlCultivationSeries($data->eStructure).'" class="btn btn-transparent">'.s("Voir ma planification").'</a>';
		echo '</div>';

	}

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Importer un plan de culture"),
		back: \structure\StructureUi::urlDataImport($data->eStructure)
	);
	
	echo '<div class="util-block-info">';
		echo '<p>'.s("Vous pouvez importer sur {siteName} un plan de culture en CSV en choisissant l'un des deux formats suivants :").'</p>';
		echo '<ul>';
			echo '<li>'.s("le format {siteName}").'</li>';
			echo '<li>'.s("le format Brinjel / Qrop").'</li>';
		echo '</ul>';
		echo '<p>'.s("Un convertisseur permettant de convertir les fichiers d'export de Elzeard en un fichier compatible avec {siteName} est également proposé.").'</p>';
		echo '<a href="/doc/import:series" class="btn btn-secondary">'.Asset::icon('person-raised-hand').' '.s("Voir la documentation").'</a>';
	echo '</div>';

	echo new \main\CsvUi()->getImportButton($data->eStructure, '/series/csv:doImportCultivations');

});

new AdaptativeView('importFile', function($data, StructureTemplate $t) {

	$t->title = s("Importer un plan de culture");
	$t->nav = 'feature';
	$t->subNav = 'settings-production';

	$t->mainTitle = \main\DesignUi::getPageHeader(
		p("Votre fichier CSV contient {value} série", "Votre fichier CSV contient {value} séries", count($data->data['import'])),
		back: \structure\StructureUi::urlDataImport($data->eStructure),
		tools: '<a href="/series/csv:importCultivations?id='.$data->eStructure['id'].'&reset" class="btn btn-outline-secondary">'.\Asset::icon('upload').'<span class="hide-xs-down"> '.s("Téléverser un autre fichier").'</span></a>'
	);
	
	echo new \series\CsvUi()->getImportFile($data->eStructure, $data->data, $data->cAction);

});
?>
