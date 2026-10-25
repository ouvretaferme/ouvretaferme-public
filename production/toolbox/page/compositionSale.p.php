<?php
/**
 * Mettre à jour la composition d'un produit composé depuis une semaine des paniers (r37b, r39) : la
 * page « Composition de <produit> », qui fait choisir le produit en vente de chaque légume du panier,
 * puis ouvre le panneau de composition de vente prérempli, que sert selling
 * (`CompositionSaleUi::urlPanel()`). La porte vient de la carte de la semaine
 * (`CompositionSaleUi::getDoor()`).
 *
 * Le droit est celui de la porte (`CompositionPlan::canSell()`, `acceptSell()`) : écrire le plan,
 * puisque le produit retenu s'enregistre sur ses espèces, et composer une vente. Un observateur n'a
 * ni l'un ni l'autre.
 */
new Page(function($data) {

	\structure\StructureLib::load($data, parameterName: 'id');

	$data->eStructure->validate('canManage', 'acceptComposition');

	$data->eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
	$data->eCompositionPlan['structure'] = $data->eStructure;
	$data->eCompositionPlan->validate('canSell', 'acceptSell');

	$data->eCompositionFormula = \toolbox\CompositionFormulaLib::getByPlanAndId($data->eCompositionPlan, INPUT('formula', 'int'))->validate();

	$data->eCompositionWeek = \toolbox\CompositionSaleLib::getWeek($data->eCompositionFormula, INPUT('week', 'int'))->validate();

	$data->eProductComposition = \toolbox\CompositionSaleLib::getComposition($data->eStructure, INPUT('composition', 'int'))->validate();
	$data->type = \toolbox\CompositionSaleLib::getType($data->eProductComposition);

})
	->get('/ferme/{id}/outils/paniers/vente', function($data) {

		\toolbox\CompositionSaleLib::applyLines($data->eStructure, $data->eCompositionPlan, $data->eCompositionWeek, $data->type);

		\toolbox\CompositionSaleLib::applyAlerts($data->eCompositionFormula, $data->eCompositionWeek, $data->eProductComposition);

		throw new ViewAction($data, ':page');

	})
	// Les choix se construisent sur l'espèce du plan de chaque ligne, jamais sur ce qui est posté : un
	// index qui ne désigne aucune ligne de la semaine n'est pas lu, et le contrôle du produit n'accepte
	// que les candidats de la ligne (`CompositionSpecies::properties()`).
	->post('doUpdate', function($data) {

		\toolbox\CompositionSaleLib::applyLines($data->eStructure, $data->eCompositionPlan, $data->eCompositionWeek, $data->type);

		$fw = new \FailWatch();

		foreach($data->eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
			$eCompositionLine['eCompositionSpecies']->buildIndex(['product'], $_POST, $eCompositionLine['species']['id']);
		}

		$fw->validate();

		if(\toolbox\CompositionSaleLib::hasProducts($data->eCompositionWeek) === FALSE) {
			\toolbox\CompositionSpecies::fail('product.none');
		}

		$fw->validate();

		\toolbox\CompositionSaleLib::updateProducts($data->eCompositionWeek);

		throw new ViewAction($data);

	});
?>
