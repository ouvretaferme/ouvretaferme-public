<?php
/**
 * Le menu d'une case de la grille (lot r24) et ses gestes : le champ d'une case de saison ajoute
 * l'espèce à la semaine à la quantité tapée (lot r24b), la poubelle d'une case servie retire sa
 * ligne, le moins, le plus et la quantité tapée la règlent. La quantité se règle aussi sur la carte
 * de la semaine.
 *
 * Chaque geste relit le plan pour la semaine de la case (`CompositionPlanLib::getForScreen()`,
 * `$week`) : le menu que rend un geste dit ce que la case peut encore recevoir. L'ouverture du menu
 * ne lit que ce que la case en affiche (`CompositionPlanLib::getForCellMenu()`).
 *
 * La case poste sa semaine et son espèce, la formule et la saison comme les réglages. La semaine
 * se relit sur le plan de la structure et jamais par un `id` posté : c'est ce qui borne le geste à
 * ce que la structure connectée possède.
 */
new \toolbox\CompositionLinePage(function($data) {

	\structure\StructureLib::load($data);

	$data->eStructure->validate('canManage', 'acceptComposition');

	$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
	$eCompositionPlan['structure'] = $data->eStructure;

	$eCompositionFormula = \toolbox\CompositionFormulaLib::getByPlanAndId($eCompositionPlan, POST('formula', 'int'))->validate();

	$data->eCompositionWeek = \toolbox\CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, POST('week', 'int'))->validate();

})
	// Rien ne s'écrit : le menu se rend sur ce que la case lit du plan pour sa semaine, vide quand la
	// case n'a rien à proposer (`CompositionUi::getCellMenu()`). Pas l'écran entier : c'est le geste
	// le plus fréquent, et il ne rend que le menu (`CompositionPlanLib::getForCellMenu()`).
	->post('getMenu', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForCellMenu($data->eCompositionWeek);
		$data->species = POST('species', 'int');

		throw new ViewAction($data);

	})
	->getCreateElement(function($data) {

		return new \toolbox\CompositionLine([
			'week' => $data->eCompositionWeek,
			'origin' => \toolbox\CompositionLine::USER,
		]);

	})
	// Le champ du menu d'une case de saison (lot r24b) : l'espèce, son unité et la quantité tapée se
	// construisent, dans cet ordre, parce que le minimum et le pas de la quantité se lisent dans
	// l'unité de la ligne. Le prix est celui de la règle (`CompositionLineLib::create()`).
	->doCreate(function($data) {

		$data->switched = TRUE;

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->eCompositionWeek['formula']['id'], $data->eCompositionWeek['week']);

		throw new ViewAction($data);

	}, ['species', 'unit', 'quantity'])
	->getElement(function($data) {

		$eCompositionLine = \toolbox\CompositionLineLib::getByWeekAndSpecies($data->eCompositionWeek, new \toolbox\CompositionSpecies(['id' => POST('species', 'int')]));

		if($eCompositionLine->notEmpty()) {
			$eCompositionLine['week'] = $data->eCompositionWeek;
		}

		return $eCompositionLine;

	})
	// La quantité se règle sur la grille (`CompositionLineLib::update()`, qui marque la semaine
	// corrigée). La réponse est celle d'une case allumée à la main : la ligne ne change pas de rang
	// dans la grille, seulement de quantité et de valeur.
	->quick(['quantity'], action: function($data) {

		$data->switched = FALSE;

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->eCompositionWeek['formula']['id'], $data->eCompositionWeek['week']);

		throw new ViewAction($data, 'toolbox/compositionLine:doQuick');

	})
	->doDelete(function($data) {

		$data->switched = TRUE;

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->eCompositionWeek['formula']['id'], $data->eCompositionWeek['week']);

		throw new ViewAction($data);

	});
?>
