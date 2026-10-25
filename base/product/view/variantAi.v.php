<?php
/**
 * Le dépliage d'une famille sur la liste des produits : deux instructions, et seulement
 * deux. Les sous-lignes remplissent le `<tbody>` qui leur est réservé, la ligne de tête reçoit
 * la classe qui bascule ses deux chevrons ; rien d'autre de l'écran n'est retouché
 * (CLAUDE.md, « une action ne renvoie que ce qui change »). Le repli n'appelle rien, il vit
 * dans variantAi.js.
 *
 * Deux `<tbody>` et non un seul parce qu'un `<tr>` ne survit pas au DOMParser de
 * `renderAdjacentHTML()` (framework/util/static/js/lime.js) : hors table il est jeté par
 * l'analyseur HTML. `innerHtml` sur un `<tbody>` écrit dans le bon contexte d'analyse, et
 * c'est la seule instruction qui sait porter des lignes de tableau. La ligne de tête reste
 * dans son propre `<tbody>` : la renvoyer aussi obligerait à rejouer sa requête de chiffre
 * d'affaires, qui s'agrège sur un autre axe que celle des sous-lignes.
 */
new JsonView('list', function($data, AjaxTemplate $t) {

	$t->qs('#product-family-'.$data->e['id'].'-variants')
		->innerHtml(new \product\ProductUi()->getListVariants($data->eStructure, $data->cProductVariant, $data->displayStock, $data->displayColumn));

	$t->qs('#product-family-'.$data->e['id'])->addClass('product-family-open');

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \product\VariantAiUi::getAttachAutocompleteResults($data->cProduct));

});
?>
