<?php
/**
 * Ce que rend un réglage de l'espèce, tel que `CompositionUi` le prépare : des nœuds à retirer, à
 * poser (`insert`, r48a : le repère, sa place et le contenu) ou à remplacer, des contenus à réécrire
 * et des attributs à poser. Un attribut `NULL` se retire de son nœud
 * (`CompositionUi::getGridCellChanges()`), comme dans `compositionLine.v.php` : l'instruction
 * `setAttribute` du cadriciel poste la valeur telle quelle, et le navigateur écrirait la chaîne
 * `null`, qui allume la marque d'une case servie (`data-served`) et donne `null` pour bulle.
 */
function toolboxCompositionSpeciesRender(AjaxTemplate $t, array $changes): void {

	foreach($changes['remove'] ?? [] as $selector) {
		$t->qs($selector)->remove();
	}

	foreach($changes['insert'] ?? [] as [$selector, $position, $html]) {
		$t->qs($selector)->insertAdjacentHtml($position, $html);
	}

	foreach($changes['outer'] as $selector => $html) {
		$t->qs($selector)->outerHtml($html);
	}

	foreach($changes['inner'] as $selector => $html) {
		$t->qs($selector)->innerHtml($html);
	}

	foreach($changes['attributes'] as $selector => $attributes) {
		foreach($attributes as $name => $value) {
			if($value === NULL) {
				$t->qs($selector)->removeAttribute($name);
			} else {
				$t->qs($selector)->setAttribute($name, $value);
			}
		}
	}

}

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionSpeciesUi()->update($data->e, $data->formula, $data->origin);

});

new JsonView('queryProduct', function($data, AjaxTemplate $t) {

	$t->push('results', \toolbox\CompositionSpeciesUi::getProductResults($data->cProduct, $data->e));

});

/**
 * Le panneau « Régler une espèce » (lot r22e) se ferme, et l'écran rend ce que l'enregistrement a
 * changé : la saison (r61), comme le geste sur la grille (`CompositionUi::getSeasoned()`), puis ce
 * qu'une fréquence, une unité ou son poids ont recomposé
 * (`CompositionUi::getRecomposed()`, lot r28, et P9 du chantier Performances pour l'unité), et ce
 * que rendent le prix, le pas et le minimum (`CompositionUi::getPriced()`) et le poids
 * (`CompositionUi::getWeighed()`), que chacun rend seul depuis son formulaire rapide.
 *
 * Ouvert depuis l'onglet « Espèces » (r48a), le panneau ne rend que le rang de l'espèce dans son
 * tableau (`CompositionUi::getSpeciesUpdated()`) : la grille n'est pas à l'écran.
 */
new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#toolbox-composition-species-panel');

	$uiComposition = new \toolbox\CompositionUi();

	if($data->tab === \toolbox\CompositionPlan::TAB_SPECIES) {
		toolboxCompositionSpeciesRender($t, $uiComposition->getSpeciesUpdated($data->eCompositionPlan, $data->e['id']));
		return;
	}

	$changes = [];

	if($data->seasoned) {
		$changes[] = $uiComposition->getSeasoned($data->eCompositionPlan, $data->e['id'], $data->weeks);
	}

	if($data->recomposed) {
		$changes[] = $uiComposition->getRecomposed($data->eCompositionPlan, $data->e['id'], $data->changes);
	}

	if($data->priced) {
		$changes[] = $uiComposition->getPriced($data->eCompositionPlan, $data->e['id']);
	}

	if($data->weighed) {
		$changes[] = $uiComposition->getWeighed($data->eCompositionPlan, $data->e['id'], FALSE);
	}

	foreach($changes as $change) {
		toolboxCompositionSpeciesRender($t, $change);
	}

});

/**
 * Un prix changé au bout du nom (lot r21) ne recompose rien : la tête de la taille, le rang de
 * l'espèce et les semaines où elle entre se rendent (`CompositionUi::getPriced()`).
 */
new JsonView('doQuick', function($data, AjaxTemplate $t) {

	toolboxCompositionSpeciesRender($t, new \toolbox\CompositionUi()->getPriced($data->eCompositionPlan, $data->e['id']));

});

/**
 * Un poids réglé (lot L3) ne rend que le rang d'où il est parti : l'espèce dans la grille, ou ses kilos de l'année dans la synthèse (`CompositionUi::getWeighed()`).
 */
new JsonView('doQuickWeight', function($data, AjaxTemplate $t) {

	toolboxCompositionSpeciesRender($t, new \toolbox\CompositionUi()->getWeighed($data->eCompositionPlan, $data->e['id'], $data->synthesis));

});

/**
 * Une fréquence choisie ou rendue (lot L9) recompose les semaines non corrigées de toutes les
 * tailles. Les espèces cultivées ne changent pas, ni ce que « Recommencer à zéro » fait perdre, les
 * semaines corrigées restant ce qu'elles sont.
 *
 * Le geste part aussi du picto de la ligne (lot r7), mais sa réponse n'est pas celle du rang : une
 * espèce montée en base sur un plan de départ change le rang de 30 autres espèces sur 36, leurs
 * cases et leur fréquence courante, et toutes les bulles et cartes des semaines recomposées
 * (`CompositionFrequencyTest::testUpdateRecomposes()`). Seuls ces semaines et ces rangs se rendent
 * (`CompositionUi::getRecomposed()`, lot r28).
 */
new JsonView('doQuickFrequency', function($data, AjaxTemplate $t) {

	toolboxCompositionSpeciesRender($t, new \toolbox\CompositionUi()->getRecomposed($data->eCompositionPlan, $data->e['id'], $data->changes));

});

/**
 * Le poids d'une unité choisie (lot r14) change la quantité et le prix de l'espèce et recompose les
 * semaines non corrigées de toutes les tailles. Réglé depuis la synthèse, seule la synthèse se rend,
 * les onglets ne portant que le nom et le prix des tailles (lot r28) ; depuis la grille, ce que la
 * recomposition a changé, le rang de l'espèce entier (`CompositionUi::getRecomposed()`, chantier
 * Performances, P9) : un poids effacé ou posé rend l'espèce vendable ou non.
 */
new JsonView('doQuickUnit', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	if($data->synthesis) {
		$t->qs('#toolbox-composition-synthesis')->innerHtml($uiComposition->getQuantities($data->eCompositionPlan));
	} else {
		toolboxCompositionSpeciesRender($t, $uiComposition->getRecomposed($data->eCompositionPlan, $data->e['id'], $data->changes));
	}

});

/**
 * Une saison de vente réglée sur la grille (lot r27) ne rend que ce qu'elle change
 * (`CompositionUi::getSeasoned()`) : le rang de l'espèce, et les semaines qu'une saison raccourcie a
 * refaites.
 */
new JsonView('doUpdateSeason', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	toolboxCompositionSpeciesRender($t, $uiComposition->getSeasoned($data->eCompositionPlan, $data->e['id'], $data->weeks));

});

/**
 * Une espèce retirée depuis le panneau « Régler une espèce » recompose des semaines dans toutes les
 * tailles : le panneau se ferme, et les paniers rendent ce que la taille ouverte a changé, le rang de
 * l'espèce retiré (`CompositionUi::getExcluded()`, chantier Performances, P9). Depuis l'onglet
 * « Espèces » (r48a), par la corbeille de son rang ou par son panneau, le rang quitte le tableau
 * (`CompositionUi::getSpeciesRemoved()`) : l'espèce y revient par « Ajouter une espèce » (r48c).
 */
new JsonView('doExclude', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#toolbox-composition-species-panel');

	$uiComposition = new \toolbox\CompositionUi();

	if($data->tab === \toolbox\CompositionPlan::TAB_SPECIES) {
		toolboxCompositionSpeciesRender($t, $uiComposition->getSpeciesRemoved($data->eCompositionPlan, $data->e['id']));
	} else {
		toolboxCompositionSpeciesRender($t, $uiComposition->getExcluded($data->eCompositionPlan, $data->e, $data->changes));
	}

});
?>
