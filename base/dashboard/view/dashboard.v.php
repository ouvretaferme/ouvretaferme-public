<?php
new AdaptativeView('/ferme/{id}/accueil', function($data, StructureTemplate $t) {

	$t->title = s("Le tableau de bord de {value}", $data->eStructure['name']);

	// Aucun `$t->nav` : l'accueil ne vit dans aucun univers, et la barre d'onglets est entièrement
	// bâtie par section. Le fil d'Ariane s'éteint du même coup, `getBreadcrumbs()` rendant une chaîne
	// vide sur un nav à NULL. Aucun `$t->mainTitle` non plus : la bande porte déjà le titre, le jour.
	$t->mainContainer = FALSE;

	// Un jeton de gabarit propre à cet écran, qui sert de bornage à la feuille du package : sans lui,
	// un sélecteur qui vise le mobilier du socle (`#structure-nav`) continuerait de s'appliquer après
	// qu'on a quitté l'accueil, la navigation ajax ne retirant pas la `<link>` du package.
	$t->template .= ' structure-dashboard ';

	$t->homeNav = 'dashboard';
	$t->news = $data->hasNews;
	$t->home = $data->home;

	// Le champ de saisie quitte le menu pour la tête de l'accueil (S6), au bureau comme au téléphone, où
	// l'accueil n'a pas d'en-tête pour le porter.
	$t->entryInPage = TRUE;

	echo new \dashboard\DashboardUi()->getBoard($data->eStructure, $data->blocks, $data->contributions, $data->today, entryField: $t->getEntryInPage(), team: $data->team);
	echo new \entry\EntryUi()->getHomeContext();

});

new JsonView('/ferme/{id}/accueil/bloc/{name}', function($data, AjaxTemplate $t) {

	$node = $t->qs('#'.\dashboard\DashboardUi::getBlockId($data->block));

	// Un bloc qui n'a rien à dire emporte son cadre : le socle a écrit l'emplacement avant de savoir,
	// et « jamais de tuile à zéro » vaut aussi pour une carte titrée qui resterait vide. En réglage, le
	// moteur rend un cadre à la place, et `NULL` ne dit plus que le refus du droit.
	if($data->entry === NULL) {
		$node->remove();
	} else {
		$node->outerHtml(new \dashboard\DashboardUi()->getBlock($data->eStructure, $data->block, $data->entry, $data->setting ? $data->entry['declaration']['section'] : NULL, $data->entry['declaration']['column']));
	}

});

new JsonView('/ferme/{id}/accueil/pilotage', function($data, AjaxTemplate $t) {

	$node = $t->qs('#'.\dashboard\PilotageUi::getNewsId().'[data-url="'.\dashboard\PilotageUi::url($data->eStructure).'"]');

	if($data->hasNews) {
		$node->outerHtml(new \dashboard\PilotageUi()->getNews($data->eStructure, FALSE));
	} else {
		$node->remove();
	}

});

new JsonView('/ferme/{id}/accueil/menu', function($data, AjaxTemplate $t) {

	$t->qs('#'.\dashboard\PilotageUi::getHomeId().'[data-url="'.\dashboard\PilotageUi::urlHome($data->eStructure).'"]')->outerHtml(new \dashboard\PilotageUi()->getHome($data->eStructure, $data->home));

});

/**
 * Une page sous Bureau, sans univers, comme les notes : aucun `$t->nav`, et `$t->homeNav` y choisit
 * l'entrée « Du nouveau ».
 */
new AdaptativeView('/ferme/{id}/accueil/nouveau', function($data, StructureTemplate $t) {

	$dashboardUi = new \dashboard\DashboardUi();

	$t->title = s("Du nouveau");
	$t->canonical = \dashboard\DashboardUi::urlNews($data->eStructure);
	$t->homeNav = 'news';
	$t->news = $data->hasNews;

	$t->mainTitle = \main\DesignUi::getPageHeader(
		$t->title,
		tools: $dashboardUi->getNewsLinks($data->newsUrls)
	);

	echo $dashboardUi->getNews($data->eStructure, $data->news);

});

new AdaptativeView('/ferme/{id}/accueil/choisir/{name}', function($data, PanelTemplate $t) {

	return new \dashboard\DashboardUi()->getChoice($data->eStructure, $data->block, $data->choice);

});
?>
