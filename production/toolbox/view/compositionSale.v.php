<?php
new AdaptativeView('page', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::BASKET;

	$t->title = s("Composition de {product}", ['product' => $data->eProductComposition['name']]);

	echo new \toolbox\CompositionSaleUi()->getPage($data->eCompositionPlan, $data->eCompositionFormula, $data->eCompositionWeek, $data->eProductComposition, $data->type);

});

/**
 * Les produits enregistrés, la page ouvre le panneau de composition prérempli, sur elle : « Valider
 * la composition » crée la vente, et le fermer la rend telle quelle. Le jour de livraison se règle
 * dans le panneau, qui le propose (r39).
 */
new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->ajaxRedirect(\toolbox\CompositionSaleUi::urlPanel($data->eStructure, $data->eCompositionFormula, $data->eCompositionWeek['week'], $data->eProductComposition));

});
?>
