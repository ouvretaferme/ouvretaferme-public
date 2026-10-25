<?php
/**
 * L'accueil en réglage, rendu sous le même gabarit que l'accueil (L43) : « Terminer » y ramène par un
 * lien, et la navigation ajax passe de l'un à l'autre sans recharger la page.
 */
new AdaptativeView('/ferme/{id}/accueil/personnaliser', function($data, StructureTemplate $t) {

	$t->title = s("Réglage du tableau de bord de {value}", $data->eStructure['name']);

	// Mêmes réglages de gabarit que l'accueil (`view/dashboard.v.php`), pour la même raison : le
	// réglage est l'accueil, et ses bandes prennent toute la largeur.
	$t->mainContainer = FALSE;
	$t->template .= ' structure-dashboard ';

	$t->homeNav = 'dashboard';
	$t->news = $data->hasNews;
	$t->home = $data->home;

	echo new \dashboard\DashboardUi()->getBoard($data->eStructure, $data->blocks, $data->contributions, $data->today, TRUE, $data->sectionsChosen);

});

/**
 * Le bloc touché, rendu à neuf : éteint, il revient en cadre estompé, rallumé, en bloc plein ou en
 * emplacement qui se remplit. L'interrupteur fait partie du nœud, il n'y a donc rien à basculer à part.
 *
 * La carte de son univers suit : allumer un bloc d'un univers éteint rallume l'univers, éteindre son
 * dernier bloc l'éteint, et l'interrupteur de la carte dirait sinon le contraire de ce que les
 * colonnes montrent.
 */
new JsonView('doUpdateHidden', function($data, AjaxTemplate $t) {

	$dashboardUi = new \dashboard\DashboardUi();

	$t->qs('#'.\dashboard\DashboardUi::getBlockId($data->name))->outerHtml($dashboardUi->getBlock($data->eStructure, $data->name, $data->entry, $data->section, $data->entry['declaration']['column']));

	$t->qs('#'.\dashboard\PreferenceUi::getSectionId($data->section))->outerHtml($dashboardUi->getSectionCard($data->eStructure, $data->section, $data->sectionOn));

});

/**
 * La carte de l'univers et chacun de ses blocs, un nœud par instruction : ils sont répartis dans les
 * colonnes et sous elles, entre les blocs des autres univers, et aucun conteneur ne les tient seuls.
 */
new JsonView('doUpdateSection', function($data, AjaxTemplate $t) {

	$dashboardUi = new \dashboard\DashboardUi();

	$t->qs('#'.\dashboard\PreferenceUi::getSectionId($data->section))->outerHtml($dashboardUi->getSectionCard($data->eStructure, $data->section, $data->sectionOn));

	foreach($data->blocks as $block => $entry) {

		if($entry['declaration']['section'] === $data->section) {
			$t->qs('#'.\dashboard\DashboardUi::getBlockId($block))->outerHtml($dashboardUi->getBlock($data->eStructure, $block, $entry, $data->section, $entry['declaration']['column']));
		}

	}

});

/**
 * Les colonnes, après un rang d'univers : ses blocs s'y déplacent tous, dans chaque colonne à la
 * fois, et les Sortable des nouvelles colonnes se réarment à leur rendu. Un rang de bloc ne passe
 * pas ici, l'action répond alors par un `VoidAction`.
 */
new JsonView('doUpdatePositions', function($data, AjaxTemplate $t) {

	$t->qs('#dashboard .dashboard-columns')->outerHtml(new \dashboard\DashboardUi()->getColumns($data->eStructure, $data->blocks, $data->contributions, TRUE));

});
?>
