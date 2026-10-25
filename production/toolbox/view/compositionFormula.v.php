<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionFormulaUi()->create($data->e, $data->prices, $data->formula, $data->origin);

});

/**
 * La taille ajoutée prend son onglet, et l'onglet ouvert reste celui qu'on lisait (`tab`) : la rangée
 * des onglets se rend, et depuis la synthèse, qui gagne une colonne, les paniers entiers.
 *
 * « Modifier les paniers », resté ouvert sous le panneau d'ajout, se rend de nouveau avec sa colonne de
 * plus (r51b, planche r51, piste A) : un panneau poussé sous un identifiant déjà à l'écran s'y
 * repeint (`Lime.Panel.render()`).
 */
new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	$t->js()->closePanel('#toolbox-composition-formula-create');

	if($data->tab === \toolbox\CompositionPlan::TAB_SYNTHESIS) {
		$t->qs('#toolbox-composition-proposal')->outerHtml($uiComposition->getProposal($data->eCompositionPlan, $data->tab));
	} else {
		$t->qs('#toolbox-composition-formulas')->outerHtml($uiComposition->getFormulas($data->eCompositionPlan, $data->tab));
	}

	$t->pushPanel($uiComposition->getSettingsPanel($data->eCompositionPlanSettings, $data->formula, $data->origin));

});

/**
 * Le rejeu refait toute la formule ouverte : son onglet se rend entier, tête, ligne du rejeu, grille
 * et semaines, et les autres onglets ne bougent pas.
 */
new JsonView('doReplay', function($data, AjaxTemplate $t) {

	$t->qs('#toolbox-composition-formula')->outerHtml(new \toolbox\CompositionUi()->getFormulaPanel($data->eCompositionPlan));

});
?>
