<?php
/**
 * « Revenir à la proposition », sur la carte d'une semaine corrigée : la semaine seule reprend ce
 * que la règle lui propose (`CompositionLib::recomposeWeek()`).
 *
 * La semaine se relit sur le plan de la structure, comme la case de la grille
 * (`compositionLine.p.php`). Une semaine corrigée appartient à un plan écrit : sans plan, il n'y a
 * rien à rendre.
 */
new \toolbox\CompositionWeekPage(function($data) {

	\structure\StructureLib::load($data);

	$data->eStructure->validate('canManage', 'acceptComposition');

	$data->eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
	$data->eCompositionPlan['structure'] = $data->eStructure;

	$data->eCompositionFormula = \toolbox\CompositionFormulaLib::getByPlanAndId($data->eCompositionPlan, POST('formula', 'int'))->validate();

})
	->getElement(function($data) {
		return \toolbox\CompositionWeekLib::getByFormulaAndWeek($data->eCompositionFormula, POST('week', 'int'));
	})
	->write('doReset', function($data) {

		// Les espèces servies avant le retour se lisent avant lui : leur rang se rend aussi quand la
		// proposition les retire (`CompositionUi::getReset()`).
		$speciesIds = \toolbox\CompositionLineLib::getByWeek($data->e)->getColumnCollection('species')->getIds();

		$source = \toolbox\CompositionLib::getSourceByPlan($data->eCompositionPlan);

		\toolbox\CompositionLib::recomposeWeek($data->eCompositionPlan, $data->eCompositionFormula, $data->e, $source, $source['pricesPlan']);

		$data->week = $data->e['week'];

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->eCompositionFormula['id']);

		foreach($data->eCompositionPlan['eCompositionFormula']['cCompositionWeek'] as $eCompositionWeek) {
			if($eCompositionWeek['week'] === $data->week) {
				$speciesIds = array_merge($speciesIds, $eCompositionWeek['cCompositionLine']->getColumnCollection('species')->getIds());
			}
		}

		$data->speciesIds = array_values(array_unique($speciesIds));

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate']);
?>
