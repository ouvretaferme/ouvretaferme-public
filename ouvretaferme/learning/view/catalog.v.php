<?php
new AdaptativeView('/formations', function($data, BaseTemplate $t) {

	$t->title = s("Formations");
	$t->header = '';

	$catalogUi = new \learning\CatalogUi();

	echo $catalogUi->getHead($data->isLogged, withSearch: $data->withSearch);

	if($data->catalog === 0) {
		echo $catalogUi->getEmpty();
		return;
	}

	if($data->hasAgenda) {
		echo $catalogUi->getTabs($data->eThemeSelected, $data->eSubthemeSelected, $data->eLevelSelected, $data->tab, $data->courses, $data->dates);
	}

	echo $catalogUi->getSearch($data->cTheme, $data->cSubtheme, $data->cLevel, $data->eThemeSelected, $data->eSubthemeSelected, $data->eLevelSelected, $data->tab, $data->isFiltered);

	echo match($data->tab) {
		'formations' => $catalogUi->getFormations($data->cCourseFeatured, $data->cThemeCatalog, $data->courses, $data->isLogged),
		'dates' => $catalogUi->getAgenda($data->ccDateMonth, $data->dates, $data->datesRemaining, $data->eThemeSelected, $data->eSubthemeSelected, $data->eLevelSelected),
	};

});

new AdaptativeView('/formations/avis', function($data, BaseTemplate $t) {

	$t->title = s("Comment les avis sont publiés");
	$t->header = new \learning\ReviewUi()->getRulesTitle();

	echo new \learning\ReviewUi()->getRules();

});
?>
