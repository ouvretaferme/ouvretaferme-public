<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \series\CopyAiUi()->create(
		$data->eStructureSource,
		$data->eStructureCommunity,
		$data->mode,
		$data->seasons,
		$data->cStructureDestination,
		$data->seasonsDestination,
		$data->cSeriesSelection,
		$data->seasonSelection
	);

});

new AdaptativeView('analyze', function($data, StructureTemplate $t) {

	$t->title = s("Copier un plan de culture");

	// L'écran appartient à la planification de la ferme d'arrivée, pas à ses réglages : c'est là
	// qu'atterrissent les séries, et c'est là qu'on ira les voir juste après.
	$t->nav = 'cultivation';
	$t->subNav = 'series';

	$t->mainTitle = \main\DesignUi::getPageHeader(p(
		"{value} série à copier depuis {name}",
		"{value} séries à copier depuis {name}",
		count($data->data['import']),
		['name' => encode($data->eStructureSource['name'])]
	));

	echo '<div class="util-block-info">';
		echo '<p>'.s("Les séries arriveront dans {name}, en saison {season}.", [
			'name' => encode($data->eStructureDestination['name']),
			'season' => $data->target
		]).'</p>';
	echo '</div>';

	$arguments = 'post-structure="'.$data->eStructureSource['id'].'"'
		.' post-community="'.$data->eStructureCommunity['id'].'"'
		.' post-mode="'.$data->mode.'"'
		.' post-season="'.$data->season.'"'
		.' post-destination="'.$data->eStructureDestination['id'].'"'
		.' post-target="'.$data->target.'"'
		.' post-series="'.implode(',', $data->cSeriesSelection->getIds()).'"';

	echo new \series\CsvUi()->getImportFile(
		$data->eStructureDestination,
		$data->data,
		$data->cAction,
		action: '<a data-ajax="/series/copyAi:doCopy" '.$arguments.' class="btn btn-secondary" data-waiter="'.s("Copie en cours, merci de patienter...").'">'.p(
			"Copier la série",
			"Copier les {value} séries",
			count($data->data['import'])
		).'</a>',
		extra: new \series\CopyAiUi()->getPartial($arguments, $data->partial['series'], $data->partial['skipped']),
		documentation: ''
	);

});
?>
