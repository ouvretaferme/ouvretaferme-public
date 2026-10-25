<?php
/**
 * Une case allumée ou éteinte, une quantité réglée, depuis le menu d'une case comme depuis la carte
 * d'une semaine, ne rendent que ce qu'elles changent (`CompositionUi::getToggled()`). Un attribut
 * `NULL` se retire de sa case
 * (`CompositionUi::getGridCellChanges()`).
 *
 * Le menu de la case (lot r24) reste ouvert après le plus, le moins ou une quantité réglée : la case
 * reste en place, et le menu se rend de nouveau, sur le plan relu pour sa semaine. Une case allumée
 * ou éteinte le referme (`switched`) : la poubelle ne laisse rien à régler, et la quantité posée dans
 * une case vide termine le geste (r32, retour du 30/09, « ne pas afficher ça », sur la
 * poubelle, le moins, la quantité et le plus rendus à la place du champ) ; la case dit alors sa
 * quantité au survol, comme toute case servie. Un réglage depuis une carte rend aussi le menu, qui
 * est alors fermé, et que la prochaine case ouverte remplit de nouveau.
 */
new JsonView(['doCreate', 'doDelete', 'doQuick'], function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	$toggled = $uiComposition->getToggled($data->eCompositionPlan, $data->eCompositionWeek['week'], $data->e['species']['id']);

	foreach($toggled['outer'] as $selector => $html) {
		$t->qs($selector)->outerHtml($html);
	}

	foreach($toggled['inner'] as $selector => $html) {
		$t->qs($selector)->innerHtml($html);
	}

	foreach($toggled['attributes'] as $selector => $attributes) {
		foreach($attributes as $name => $value) {
			if($value === NULL) {
				$t->qs($selector)->removeAttribute($name);
			} else {
				$t->qs($selector)->setAttribute($name, $value);
			}
		}
	}

	if($data->switched) {
		$t->js()->closeDropdowns();
	} else {
		$t->qs('#toolbox-composition-cell-menu')->innerHtml($uiComposition->getCellMenu($data->eCompositionPlan, $data->e['species']['id'], $data->eCompositionWeek['week']));
	}

});

/**
 * Le menu d'une case, que `ToolboxCompositionGrid.menu()` pose dans la liste de la grille avant de
 * l'ouvrir, et n'ouvre pas quand il est vide.
 */
new JsonView('getMenu', function($data, AjaxTemplate $t) {

	$t->push('menu', new \toolbox\CompositionUi()->getCellMenu($data->eCompositionPlan, $data->species, $data->eCompositionWeek['week']));

});
?>
